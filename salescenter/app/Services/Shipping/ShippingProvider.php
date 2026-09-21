<?php

declare(strict_types=1);
namespace App\Services\Shipping;

use App\Models\OrderRepository;
use App\Services\AllegroService;
use App\Services\OrderSyncService;
use InvalidArgumentException;
use RuntimeException;

/**
 * Moduł operatora przesyłek. Nowy operator = nowa klasa w tym katalogu + wpis w ShippingProviders::CLASSES.
 * definition() opisuje operatora dla UI (karta, formularz, możliwości), metody instancji rozmawiają z API.
 * $carrier to wiersz om_carrier_accounts z rozkodowanymi kluczami 'public' i 'secret'.
 */
abstract class ShippingProvider
{
    /** @var OrderRepository */
    protected $repo;

    public function __construct(OrderRepository $repo) { $this->repo=$repo; }

    /**
     * key, label, description, badge[text,tone], source_platform ('' gdy konto nie jest związane z marketplace),
     * capabilities[valuation,cancel,label,tracking,cod(form|order|none),source_tracking(auto|manual),pickup_protocol],
     * info[] (co trzeba wiedzieć), docs (URL), fields[] (formularz podłączenia konta).
     */
    abstract public static function definition(): array;

    /** Lista usług: [['value'=>..,'name'=>..,'carrier'=>..],...]. */
    abstract public function services(array $carrier,array $order): array;

    /** Nadanie. Zwraca external_id, tracking, state, service_code, service_name, carrier_name, response, meta. */
    abstract public function create(array $carrier,array $order,array $input): array;

    /** Odświeżenie statusu. Zwraca state, external_id, tracking, response, meta. */
    abstract public function refresh(array $carrier,array $shipment,array $meta): array;

    /** Etykieta PDF jako bajty. */
    abstract public function label(array $carrier,array $shipment,string $pageSize): string;

    public function cancel(array $carrier,array $shipment): array { throw new InvalidArgumentException(static::definition()['label'].': anulowanie z panelu nie jest obsługiwane.'); }

    public function valuation(array $carrier,array $order,array $input): array { throw new InvalidArgumentException(static::definition()['label'].': wycena na żywo nie jest dostępna.'); }

    /** Czas cache listy usług w sekundach; 0 = bez cache. */
    public function servicesTtl(): int { return 3600; }

    /** Etykieta pustej opcji „automatycznie” w liście usług albo '' gdy usługę trzeba wybrać. */
    public function automaticService(array $carrier,array $order): string { return ''; }

    /** Usługa podpowiadana w formularzu i używana przez automatyzacje. */
    public function preferredService(array $carrier,array $order,array $defaults): string { return ''; }

    /** Czy konto może nadać to zamówienie (np. Wysyłam z Allegro tylko dla zamówień tego samego konta). */
    public function supportsOrder(array $order,array $public): bool
    {
        $platform=(string)(static::definition()['source_platform']??'');
        if ($platform==='') { return true; }
        return (string)($order['platform']??'')===$platform && (int)($public['order_account_id']??0)===(int)($order['account_id']??0);
    }

    /** Dopasowanie konta do zamówienia: [priorytet (mniejszy = lepszy), powód] albo []. */
    public function matchOrder(array $order,array $public): array
    {
        $definition=static::definition();
        if (($definition['source_platform']??'')!=='' && $this->supportsOrder($order,$public)) { return [10,'Dopasowano konto '.$definition['label'].' do źródła zamówienia']; }
        return [];
    }

    /** Dane konta z formularza: ['public'=>[],'secret'=>[]]. Domyślnie wg definition()['fields']. Puste hasło przy edycji = bez zmian. */
    public function configure(array $input,array $existingSecret=[]): array
    {
        $definition=static::definition(); $public=[]; $secret=[];
        foreach ((array)$definition['fields'] as $field) {
            $name=(string)$field['name']; if ($name==='name') { continue; }
            $type=(string)($field['type']??'text'); $value=trim((string)($input[$name]??($field['default']??'')));
            if ($type==='password' && $value==='' && isset($existingSecret[$name])) { $value=(string)$existingSecret[$name]; }
            if ($value==='' && !empty($field['required'])) { throw new InvalidArgumentException('Uzupełnij pole „'.$field['label'].'”.'); }
            if (mb_strlen($value,'UTF-8')>(int)($field['max']??1000)) { throw new InvalidArgumentException('Pole „'.$field['label'].'” jest za długie.'); }
            if ($type==='source_account') {
                $account=$this->repo->db()->fetch('SELECT id FROM om_accounts WHERE id=:id AND platform=:platform',['id'=>(int)$value,'platform'=>$definition['source_platform']]);
                if (!$account) { throw new InvalidArgumentException('Wybierz konto '.$definition['source_label'].'.'); }
                $public['order_account_id']=(int)$value; continue;
            }
            if ($type==='select' && !isset($field['options'][$value])) { $value=(string)($field['default']??''); }
            if ($type==='number' && $value!=='' && !ctype_digit($value)) { throw new InvalidArgumentException('Pole „'.$field['label'].'” musi być liczbą.'); }
            if ($type==='password') { $secret[$name]=$value; } else { $public[$name]=$value; }
        }
        return ['public'=>$public+$this->verify($public,$secret),'secret'=>$secret];
    }

    /** Sprawdzenie połączenia przy zapisie konta; zwraca dodatkowe dane publiczne do pokazania na karcie. */
    protected function verify(array $public,array $secret): array { return []; }

    /** Lista usług z cache ustawień (klucz per konto nadawcze). */
    public function cachedServices(array $carrier,array $order): array
    {
        $ttl=$this->servicesTtl();
        $cacheKey='carrier_services_'.(int)$carrier['id'];
        if ($ttl>0) {
            $cached=$this->repo->setting($cacheKey);
            if (!empty($cached['fetched_at']) && (int)$cached['fetched_at']>time()-$ttl && !empty($cached['options']) && is_array($cached['options'])) { return $cached['options']; }
        }
        $options=$this->services($carrier,$order);
        usort($options,static function (array $a,array $b): int { return strcasecmp($a['carrier'].' '.$a['name'],$b['carrier'].' '.$b['name']); });
        if (!$options) { throw new RuntimeException('Operator nie zwrócił dostępnych usług dostawy.'); }
        if ($ttl>0) { $this->repo->saveSetting($cacheKey,['fetched_at'=>time(),'options'=>$options]); }
        return $options;
    }

    /** Nazwa usługi i przewoźnika dla zapisanego kodu usługi. */
    protected function serviceMeta(array $carrier,array $order,string $selected,string $fallbackName,string $fallbackCarrier): array
    {
        try { $options=$this->cachedServices($carrier,$order); }
        catch (\Throwable $error) { $options=[]; }
        $selectedCode=explode('::',$selected,2)[0];
        foreach ($options as $option) {
            $value=(string)($option['value']??'');
            if ($value===$selected || explode('::',$value,2)[0]===$selectedCode) { return ['service_name'=>(string)($option['name']??$fallbackName),'carrier_name'=>(string)($option['carrier']??$fallbackCarrier)]; }
        }
        return ['service_name'=>$fallbackName,'carrier_name'=>$fallbackCarrier];
    }

    /** Konto marketplace (z tokenami / kluczem API) powiązane z lokalnym kontem zamówień. */
    protected function sourceAccount(string $platform,int $localAccountId): array
    {
        $sourceId=(int)$this->repo->db()->fetchColumn('SELECT source_id FROM om_accounts WHERE id=:id AND platform=:p',['id'=>$localAccountId,'p'=>$platform]);
        $classes=OrderSyncService::CLASSES;
        if (!isset($classes[$platform])) { throw new RuntimeException('Nieznana platforma '.$platform.'.'); }
        $service=$platform==='allegro'?new AllegroService(true):new $classes[$platform]();
        foreach ($service->listAccounts() as $account) {
            if ((int)($account['id']??0)===$sourceId && (!array_key_exists('is_active',$account) || !empty($account['is_active']))) { return $account; }
        }
        throw new RuntimeException('Nie znaleziono aktywnego konta '.$platform.' powiązanego z kontem nadawczym.');
    }

    /** Dane nadawcy z ustawień „Przesyłki i presety”. */
    protected function sender(): array
    {
        $sender=(array)($this->repo->setting('shipping_defaults')['sender']??[]);
        try { return ['name'=>ShipmentInput::required($sender,'name',150),'email'=>ShipmentInput::email($sender,'email'),'phone'=>ShipmentInput::phone($sender,'phone'),'street'=>ShipmentInput::required($sender,'street',150),'building'=>ShipmentInput::required($sender,'building',30),'postal_code'=>ShipmentInput::required($sender,'postal_code',20),'city'=>ShipmentInput::required($sender,'city',100)]; }
        catch (InvalidArgumentException $e) { throw new InvalidArgumentException('Uzupełnij dane nadawcy (nazwa, e-mail, telefon, ulica, numer, kod pocztowy, miasto) w zakładce Przesyłki i presety.'); }
    }

    protected function defaults(): array { return (array)$this->repo->setting('shipping_defaults'); }
}
