<?php

declare(strict_types=1);
namespace App\Services;

use App\Models\OrderRepository;
use App\Services\Shipping\ShipmentInput;
use App\Services\Shipping\ShippingProvider;
use App\Services\Shipping\ShippingProviders;
use InvalidArgumentException;
use RuntimeException;

/**
 * Wspólna obsługa przesyłek: konto nadawcze, idempotencja, zapis w om_shipments, zdarzenia i automatyzacje.
 * Rozmowa z API operatora jest w modułach App\Services\Shipping\* (rejestr: ShippingProviders).
 */
final class OrderShipmentService
{
    private $repo;
    public function __construct(OrderRepository $repo) { $this->repo=$repo; }
    public const BUILTIN_PRESETS=['small'=>['name'=>'Mała','length'=>23,'width'=>16,'height'=>10,'weight'=>0.5],'medium'=>['name'=>'Średnia','length'=>30,'width'=>20,'height'=>15,'weight'=>1],'large'=>['name'=>'Duża','length'=>40,'width'=>30,'height'=>20,'weight'=>2]];
    public const MAX_PRESETS=30;
    /** carrier_account_id w mapowaniu metody dostawy oznaczające „nie nadawaj automatycznie”. */
    public const MAPPING_SKIP=-1;

    public static function defaults(OrderRepository $repo): array
    {
        $defaults=$repo->setting('shipping_defaults');
        $base=['default_carrier_account_id'=>0,'default_package'=>'auto','service'=>'auto','apaczka_service_id'=>0,'pickup_type'=>'SELF','default_point'=>'','content'=>'Towar','cod_bank_account'=>'','sender'=>['name'=>'','email'=>'','phone'=>'','street'=>'','building'=>'','postal_code'=>'','city'=>'']];
        $stored=$defaults['presets']??null; unset($defaults['presets']);
        $merged=array_replace_recursive($base,$defaults);
        $merged['presets']=self::presets(is_array($stored)?$stored:[]);
        if ($merged['default_package']!=='auto' && !isset($merged['presets'][$merged['default_package']])) { $merged['default_package']='auto'; }
        return $merged;
    }
    /** Gabaryty paczek: klucz => name, length, width, height, weight. Zapisana lista zastępuje wbudowane Mała/Średnia/Duża. */
    public static function presets(array $stored): array
    {
        $presets=[];
        foreach ($stored as $key=>$preset) {
            if (!is_array($preset) || !preg_match('/^[a-z0-9_]{1,40}$/D',(string)$key)) { continue; }
            $name=trim((string)($preset['name']??'')) ?: (self::BUILTIN_PRESETS[$key]['name']??(string)$key);
            $presets[(string)$key]=['name'=>$name,'length'=>(float)($preset['length']??30),'width'=>(float)($preset['width']??20),'height'=>(float)($preset['height']??15),'weight'=>(float)($preset['weight']??1)];
        }
        return $presets ?: self::BUILTIN_PRESETS;
    }
    /** Gabaryt „automatycznie”: Mała do 1 szt., Średnia do 4 szt., potem Duża; brakujące zastępuje pierwszy zdefiniowany. */
    public static function autoPreset(array $presets,int $quantity): string
    {
        $wanted=$quantity<=1?'small':($quantity<=4?'medium':'large');
        foreach ([$wanted,'medium','small','large'] as $key) { if (isset($presets[$key])) { return $key; } }
        return (string)array_key_first($presets);
    }
    public static function presetLabel(array $preset): string
    {
        $number=static function ($value): string { return rtrim(rtrim(number_format((float)$value,2,',',''),'0'),','); };
        return $preset['name'].' ('.$number($preset['length']).'×'.$number($preset['width']).'×'.$number($preset['height']).' cm, '.$number($preset['weight']).' kg)';
    }

    /** Mapowanie metod dostawy: klucz platform|metoda => ['platform','delivery','carrier_account_id','service']. */
    public static function deliveryMappings(OrderRepository $repo): array
    {
        $rows=[];
        foreach ((array)($repo->setting('shipping_delivery_map')['rows']??[]) as $row) {
            if (!is_array($row) || trim((string)($row['delivery']??''))==='') { continue; }
            $row=['platform'=>(string)($row['platform']??''),'delivery'=>trim((string)$row['delivery']),'carrier_account_id'=>(int)($row['carrier_account_id']??0),'service'=>trim((string)($row['service']??''))];
            $rows[self::deliveryKey($row['platform'],$row['delivery'])]=$row;
        }
        return $rows;
    }
    public static function deliveryKey(string $platform,string $delivery): string
    {
        return $platform.'|'.mb_strtolower(trim((string)preg_replace('/\s+/u',' ',$delivery)),'UTF-8');
    }
    public static function saveDeliveryMapping(OrderRepository $repo,string $platform,string $delivery,int $carrierAccountId,string $service): void
    {
        $delivery=trim($delivery); $service=trim($service);
        if ($delivery==='' || mb_strlen($delivery,'UTF-8')>255) { throw new InvalidArgumentException('Brak nazwy metody dostawy.'); }
        if (mb_strlen($service,'UTF-8')>100) { throw new InvalidArgumentException('Za długi kod usługi.'); }
        if ($carrierAccountId>0 && !$repo->db()->fetchColumn('SELECT id FROM om_carrier_accounts WHERE id=:id',['id'=>$carrierAccountId])) { throw new InvalidArgumentException('Nie znaleziono konta nadawczego.'); }
        $rows=self::deliveryMappings($repo);
        $key=self::deliveryKey($platform,$delivery);
        if ($carrierAccountId===0) { unset($rows[$key]); }
        else { $rows[$key]=['platform'=>$platform,'delivery'=>$delivery,'carrier_account_id'=>$carrierAccountId<0?self::MAPPING_SKIP:$carrierAccountId,'service'=>$carrierAccountId<0?'':$service]; }
        $repo->saveSetting('shipping_delivery_map',['rows'=>array_values($rows)]);
    }

    /**
     * Konto nadawcze, preset paczki i usługa podpowiadane dla zamówienia.
     * source: mapping (ręczne mapowanie metody dostawy), skip (mapowanie „nie nadawaj”), match (konto zgodne ze źródłem/metodą),
     * default (globalne konto domyślne), first (pierwsze zgodne – wymaga potwierdzenia), none (brak konta).
     */
    public static function suggestion(OrderRepository $repo,array $order,array $accounts,array $defaults,?array $mappings=null): array
    {
        $quantity=array_sum(array_map(static function ($item) { return max(0,(int)($item['quantity']??0)); },(array)($order['details']['items']??[])));
        $presets=(array)$defaults['presets'];
        $size=$defaults['default_package']==='auto' || !isset($presets[$defaults['default_package']])?self::autoPreset($presets,$quantity):$defaults['default_package'];
        $delivery=trim((string)($order['details']['delivery']??''));
        $mappings=$mappings??self::deliveryMappings($repo);
        $mapping=$delivery!==''?($mappings[self::deliveryKey((string)($order['platform']??''),$delivery)]??$mappings[self::deliveryKey('',$delivery)]??null):null;
        $selected=null; $reason='Wybierz aktywne konto nadawcze'; $source='none'; $best=PHP_INT_MAX; $default=null; $first=null; $mapped=null;
        foreach ($accounts as $account) {
            if (!(int)$account['enabled'] || !ShippingProviders::exists((string)$account['provider'])) { continue; }
            $provider=ShippingProviders::get($repo,(string)$account['provider']);
            $public=json_decode((string)$account['public_config_json'],true)?:[];
            if (!$provider->supportsOrder($order,$public)) { continue; }
            if ($mapping && (int)$account['id']===(int)$mapping['carrier_account_id']) { $mapped=$account; }
            $match=$provider->matchOrder($order,$public);
            if ($match && $match[0]<$best) { $best=$match[0]; $selected=$account; $reason=$match[1]; $source='match'; }
            if ((int)$account['id']===(int)$defaults['default_carrier_account_id']) { $default=$account; }
            if (!$first) { $first=$account; }
        }
        $service='';
        if ($mapping && (int)$mapping['carrier_account_id']===self::MAPPING_SKIP) {
            $selected=null; $source='skip'; $reason='Metoda dostawy „'.$delivery.'” jest oznaczona jako „nie nadawaj automatycznie”';
        } elseif ($mapped) {
            $selected=$mapped; $source='mapping'; $reason='Mapowanie metody dostawy „'.$delivery.'”'; $service=(string)$mapping['service'];
        } elseif ($mapping) {
            $reason='Mapowanie „'.$delivery.'” wskazuje wyłączone albo niezgodne konto nadawcze'; if (!$selected) { $source='none'; }
        }
        if (!$selected && $source==='none' && $default) { $selected=$default; $reason='Użyto globalnego konta domyślnego'; $source='default'; }
        if (!$selected && $source==='none' && $first) { $selected=$first; $reason='Pierwsze zgodne konto nadawcze – ustaw mapowanie metody dostawy'; $source='first'; }
        if ($selected && $service==='') { $service=ShippingProviders::get($repo,(string)$selected['provider'])->preferredService(['id'=>(int)$selected['id'],'public'=>json_decode((string)$selected['public_config_json'],true)?:[]],$order,$defaults); }
        return ['carrier_account_id'=>$selected?(int)$selected['id']:0,'reason'=>$reason,'source'=>$source,'delivery'=>$delivery,'preset'=>$size,'package'=>$presets[$size],'service'=>$service];
    }

    /** Metody dostawy z ostatnich zamówień i to, jak zostaną nadane automatycznie (zakładka Przesyłki). */
    public static function deliveryOverview(OrderRepository $repo,int $limit=3000): array
    {
        $defaults=self::defaults($repo); $accounts=$repo->carrierAccounts(); $mappings=self::deliveryMappings($repo);
        $groups=[];
        foreach ($repo->db()->fetchAll('SELECT o.id,o.account_id,o.details_json,a.platform FROM om_orders o JOIN om_accounts a ON a.id=o.account_id ORDER BY o.id DESC LIMIT '.max(1,min(10000,$limit))) as $row) {
            $details=json_decode((string)$row['details_json'],true); if (!is_array($details)) { continue; }
            $delivery=trim((string)($details['delivery']??'')); if ($delivery==='') { continue; }
            $key=self::deliveryKey((string)$row['platform'],$delivery);
            if (isset($groups[$key])) { $groups[$key]['orders']++; continue; }
            $order=['id'=>(int)$row['id'],'account_id'=>(int)$row['account_id'],'platform'=>(string)$row['platform'],'details'=>$details];
            $groups[$key]=['key'=>$key,'platform'=>(string)$row['platform'],'delivery'=>$delivery,'orders'=>1,'order_id'=>(int)$row['id'],'suggestion'=>self::suggestion($repo,$order,$accounts,$defaults,$mappings),'mapping'=>$mappings[$key]??null];
        }
        foreach ($mappings as $key=>$mapping) {
            if (!isset($groups[$key])) { $groups[$key]=['key'=>$key,'platform'=>$mapping['platform'],'delivery'=>$mapping['delivery'],'orders'=>0,'order_id'=>0,'suggestion'=>['source'=>'mapping','carrier_account_id'=>$mapping['carrier_account_id'],'reason'=>'Mapowanie metody dostawy','service'=>$mapping['service']],'mapping'=>$mapping]; }
        }
        $names=array_column($accounts,'name','id'); $byId=array_column($accounts,null,'id');
        foreach ($groups as &$group) {
            $group['needs_mapping']=in_array($group['suggestion']['source'],['first','none'],true);
            $group['platform_label']=OrderAutomationService::PLATFORMS[$group['platform']]??($group['platform']!==''?$group['platform']:'Każda integracja');
            $group['carrier_name']=(string)($names[$group['suggestion']['carrier_account_id']]??'');
            $group['service_name']=self::cachedServiceName($repo,(int)$group['suggestion']['carrier_account_id'],(string)$group['suggestion']['service']);
            $group['needs_service']=false;
            if (!$group['needs_mapping'] && $group['order_id'] && (string)$group['suggestion']['service']==='' && isset($byId[$group['suggestion']['carrier_account_id']])) {
                // Operator bez automatycznego doboru usługi (np. Apaczka) – bez wybranego kuriera automat nie nada paczki.
                $account=$byId[$group['suggestion']['carrier_account_id']];
                if (ShippingProviders::exists((string)$account['provider'])) {
                    $public=json_decode((string)$account['public_config_json'],true)?:[];
                    $group['needs_service']=ShippingProviders::get($repo,(string)$account['provider'])->automaticService(['id'=>(int)$account['id'],'public'=>$public],['id'=>$group['order_id'],'platform'=>$group['platform'],'details'=>[]])==='';
                }
            }
        }
        unset($group);
        uasort($groups,static function (array $a,array $b): int { return [$b['needs_mapping']||$b['needs_service'],$a['platform'],$b['orders']]<=>[$a['needs_mapping']||$a['needs_service'],$b['platform'],$a['orders']]; });
        return array_values($groups);
    }
    /** Nazwa usługi z cache listy usług konta (bez zapytań do operatora). */
    private static function cachedServiceName(OrderRepository $repo,int $carrierAccountId,string $service): string
    {
        if ($carrierAccountId<1 || $service==='') { return ''; }
        foreach ((array)($repo->setting('carrier_services_'.$carrierAccountId)['options']??[]) as $option) {
            if ((string)($option['value']??'')===$service) { return trim((string)($option['carrier']??'').' · '.(string)($option['name']??''),' ·'); }
        }
        return '';
    }

    public function options(int $orderId,int $carrierAccountId): array
    {
        // Bez zamówienia (mapowanie metod dostawy) – lista usług konta, np. kurierzy Apaczki.
        $order=$orderId>0?$this->repo->order($orderId):['id'=>0,'platform'=>'','account_id'=>0,'external_id'=>'','details'=>[]];
        [$carrier,$provider]=$this->carrier($carrierAccountId,true);
        $definition=$provider::definition();
        $defaults=self::defaults($this->repo);
        $preferred=$provider->preferredService($carrier,$order,$defaults);
        if ($orderId>0) {
            // Usługa z mapowania metody dostawy ma pierwszeństwo, gdy mapowanie wskazuje to samo konto.
            $delivery=trim((string)($order['details']['delivery']??''));
            $mappings=self::deliveryMappings($this->repo);
            $mapping=$delivery!==''?($mappings[self::deliveryKey((string)$order['platform'],$delivery)]??$mappings[self::deliveryKey('',$delivery)]??null):null;
            if ($mapping && (int)$mapping['carrier_account_id']===$carrierAccountId && $mapping['service']!=='') { $preferred=(string)$mapping['service']; }
        }
        return ['provider'=>$definition['key'],'options'=>$provider->cachedServices($carrier,$order),'automatic'=>$provider->automaticService($carrier,$order),'preferred'=>$preferred,'valuation'=>!empty($definition['capabilities']['valuation']),'cod'=>(string)$definition['capabilities']['cod']];
    }

    public function create(int $orderId,int $carrierAccountId,array $input,string $actor): int
    {
        $order=$this->repo->order($orderId); $db=$this->repo->db();
        [$carrier,$provider]=$this->carrier($carrierAccountId,true);
        $weight=ShipmentInput::package($input)['weight'];
        $codAmountCents=!empty($input['cash_on_delivery'])?OrderNormalizer::money((string)($input['cod_amount']??'0')):0;
        $requestKey=(string)($input['request_key']??'');
        if (!preg_match('/^[a-f0-9]{32,80}$/D',$requestKey)) { throw new InvalidArgumentException('Odśwież formularz nadania.'); }
        $existing=$db->fetch('SELECT id FROM om_shipments WHERE command_id=:key',['key'=>$requestKey]);
        if ($existing) { return (int)$existing['id']; }
        $result=$provider->create($carrier,$order,$input);
        $external=(string)($result['external_id']??'');
        if ($external==='') { throw new RuntimeException('Operator nie zwrócił identyfikatora przesyłki.'); }
        $state=(string)($result['state']??'created');
        $tracking=trim((string)($result['tracking']??'')) ?: 'PENDING:'.$requestKey;
        $tracked=!array_key_exists('tracked',$result) || $result['tracked'];
        $meta=array_replace(['delivery_method'=>trim((string)($order['details']['delivery']??'')),'service_code'=>(string)($result['service_code']??''),'service_name'=>(string)($result['service_name']??''),'carrier_name'=>(string)($result['carrier_name']??''),'tracking_status'=>$tracked?$state:'','tracking_updated_at'=>$tracked?gmdate('c'):''],(array)($result['meta']??[]));
        $key=(string)$carrier['provider'];
        $id=(int)$db->insert('om_shipments',['order_id'=>$orderId,'carrier_account_id'=>$carrierAccountId,'carrier'=>$carrier['name'],'tracking'=>$tracking,'weight'=>(string)$weight,'state'=>$state,'external_id'=>$external,'command_id'=>$requestKey,'payload_json'=>$this->payload($key,(array)($result['response']??[]),$meta),'cod_amount_cents'=>$codAmountCents,'shipment_currency'=>(string)$order['currency'],'created_at'=>gmdate('Y-m-d H:i:s')]);
        $this->repo->event($orderId,'Utworzono przesyłkę przez '.$carrier['name'].'; status: '.$state,$actor);
        $this->repo->automationEvent($orderId,'shipment_created',['shipment_id'=>$id,'carrier_account_id'=>$carrierAccountId]);
        return $id;
    }

    public function valuation(int $orderId,int $carrierAccountId,array $input): array
    {
        $order=$this->repo->order($orderId);
        [$carrier,$provider]=$this->carrier($carrierAccountId,true);
        return $provider->valuation($carrier,$order,$input);
    }

    public function refresh(int $shipmentId,string $actor): void
    {
        $db=$this->repo->db(); [$shipment,$carrier,$provider]=$this->shipment($shipmentId);
        $stored=json_decode((string)($shipment['payload_json']??''),true); $meta=is_array($stored['meta']??null)?$stored['meta']:[];
        $previousTrackingStatus=(string)($meta['tracking_status']??'');
        $result=$provider->refresh($carrier,$shipment,$meta);
        $state=(string)$result['state']; $meta=(array)$result['meta'];
        $db->update('om_shipments',['state'=>$state,'external_id'=>(string)$result['external_id'],'tracking'=>(string)$result['tracking'],'payload_json'=>$this->payload((string)$carrier['provider'],(array)$result['response'],$meta)],'id=:id',['id'=>$shipmentId]);
        $this->repo->event((int)$shipment['order_id'],'Odświeżono przesyłkę '.$carrier['name'].'; status: '.$state,$actor);
        if ($state!==(string)$shipment['state'] || (string)($meta['tracking_status']??'')!==$previousTrackingStatus) {
            $this->repo->automationEvent((int)$shipment['order_id'],'shipment_status',['shipment_id'=>$shipmentId,'shipment_state'=>$state]);
        }
    }

    public function cancel(int $shipmentId,string $actor): void
    {
        $db=$this->repo->db(); [$shipment,$carrier,$provider]=$this->shipment($shipmentId);
        if (self::isCancelled((string)$shipment['state'])) return;
        $definition=$provider::definition();
        if (empty($definition['capabilities']['cancel'])) { throw new InvalidArgumentException($definition['label'].': anulowanie z panelu nie jest obsługiwane – anuluj przesyłkę u operatora.'); }
        $response=$provider->cancel($carrier,$shipment);
        $stored=json_decode((string)($shipment['payload_json']??''),true); $meta=is_array($stored['meta']??null)?$stored['meta']:[];
        $meta['tracking_status']='CANCELLED'; $meta['tracking_updated_at']=gmdate('c');
        $db->update('om_shipments',['state'=>'CANCELLED','payload_json'=>$this->payload((string)$carrier['provider'],$response,$meta)],'id=:id',['id'=>$shipmentId]);
        $this->repo->event((int)$shipment['order_id'],'Anulowano przesyłkę '.$definition['label'].' '.$shipment['tracking'],$actor);
    }

    public function deleteLocal(int $shipmentId,string $actor): void
    {
        $db=$this->repo->db(); $shipment=$db->fetch('SELECT * FROM om_shipments WHERE id=:id',['id'=>$shipmentId]);
        if (!$shipment) throw new InvalidArgumentException('Nie znaleziono przesyłki.');
        $db->transaction(function () use ($db,$shipment,$shipmentId,$actor): void {
            $this->repo->event((int)$shipment['order_id'],'Usunięto lokalny wpis przesyłki '.$shipment['tracking'].' bez anulowania u przewoźnika',$actor);
            $db->delete('om_shipments','id=:id',['id'=>$shipmentId]);
        });
    }

    public function label(int $shipmentId,string $pageSize='A6'): array
    {
        [$shipment,$carrier,$provider]=$this->shipment($shipmentId);
        $bytes=$provider->label($carrier,$shipment,$pageSize);
        return ['bytes'=>$bytes,'mime'=>'application/pdf','name'=>'etykieta-'.preg_replace('/[^a-zA-Z0-9._-]/','-',(string)$shipment['tracking']).'.pdf'];
    }

    /** Dokument dodatkowy operatora (np. protokół odbioru ERLI). */
    public function document(int $shipmentId,string $kind): array
    {
        [$shipment,$carrier,$provider]=$this->shipment($shipmentId);
        if ($kind!=='pickup_protocol' || empty($provider::definition()['capabilities']['pickup_protocol']) || !method_exists($provider,'pickupProtocol')) { throw new InvalidArgumentException('Operator nie udostępnia tego dokumentu.'); }
        return ['bytes'=>$provider->pickupProtocol($carrier,$shipment),'mime'=>'application/pdf','name'=>'protokol-odbioru-'.preg_replace('/[^a-zA-Z0-9._-]/','-',(string)$shipment['tracking']).'.pdf'];
    }

    public static function isCancelled(string $state): bool { return in_array(strtoupper($state),['CANCELLED','CANCELED','ANULOWANO'],true); }

    public static function presentation(array $shipment,array $order): array
    {
        $stored=json_decode((string)($shipment['payload_json']??''),true); $meta=is_array($stored['meta']??null)?$stored['meta']:[];
        $provider=(string)($shipment['carrier_provider']??$stored['provider']??'');
        $definition=ShippingProviders::definition($provider);
        $delivery=trim((string)($meta['delivery_method']??$order['details']['delivery']??''));
        $service=trim((string)($meta['service_name']??'')) ?: ($delivery!==''?$delivery:'Nie zapisano');
        $carrier=trim((string)($meta['carrier_name']??''));
        if ($carrier==='') { $carrier=self::carrierFromDelivery($delivery,$provider,(string)($shipment['carrier']??'')); }
        else { $carrier=self::prettyCarrier($carrier); }
        $remote=trim((string)($meta['tracking_status']??''));
        if ($remote==='' && $provider!=='allegro_wza') { $remote=(string)($shipment['state']??''); }
        [$label,$tone]=self::statusLabel($remote,$provider);
        $updatedAt=trim((string)($meta['tracking_updated_at']??'')); $updatedTimestamp=$updatedAt!==''?strtotime($updatedAt):false;
        if ($updatedTimestamp!==false) { $updatedAt=gmdate('Y-m-d H:i',$updatedTimestamp).' UTC'; }
        $publication=is_array($meta['source_publication']??null)?$meta['source_publication']:[];
        if (($publication['state']??'')!=='received' && empty($publication['attempted_at']) && !empty($publication['sent_at'])) {
            $publication['attempted_at']=$publication['sent_at'];
            $publication['sent_at']='';
        }
        foreach (['attempted_at','sent_at','received_at'] as $field) {
            $timestamp=trim((string)($publication[$field]??''));
            $parsed=$timestamp!==''?strtotime($timestamp):false;
            $publication[$field.'_label']=$parsed!==false?gmdate('Y-m-d H:i',$parsed).' UTC':'';
        }
        $cancelled=self::isCancelled((string)($shipment['state']??''));
        $capabilities=(array)$definition['capabilities'];
        return ['delivery_method'=>$delivery!==''?$delivery:'Brak danych','carrier'=>$carrier,'service'=>$service,'service_code'=>(string)($meta['service_code']??''),'remote_status'=>$remote,'status_label'=>$label,'status_tone'=>$tone,'status_description'=>self::statusDescription((string)($meta['status_description']??''),$remote),'status_updated_at'=>$updatedAt,'technical_status'=>(string)($shipment['state']??''),'source_publication'=>$publication,
            'provider_label'=>(string)$definition['label'],'tracking_pending'=>strpos((string)($shipment['tracking']??''),'PENDING:')===0,'cancelled'=>$cancelled,'can_cancel'=>!$cancelled && !empty($capabilities['cancel']),'can_label'=>!$cancelled && !empty($capabilities['label']),'source_tracking_auto'=>($capabilities['source_tracking']??'')==='auto','pickup_protocol'=>!$cancelled && !empty($capabilities['pickup_protocol']) && !empty($meta['documents']['pickup_protocol'])];
    }

    /** @return array{0:array,1:ShippingProvider} */
    private function carrier(int $carrierAccountId,bool $enabledOnly): array
    {
        $carrier=$this->repo->db()->fetch('SELECT * FROM om_carrier_accounts WHERE id=:id'.($enabledOnly?' AND enabled=1':''),['id'=>$carrierAccountId]);
        if (!$carrier) { throw $enabledOnly?new InvalidArgumentException('Wybierz aktywne konto nadawcze.'):new RuntimeException('Brak konfiguracji konta nadawczego.'); }
        $carrier['public']=json_decode((string)$carrier['public_config_json'],true)?:[];
        $carrier['secret']=OrderSecretBox::decrypt($carrier['secret_config_json']);
        return [$carrier,ShippingProviders::get($this->repo,(string)$carrier['provider'])];
    }
    /** @return array{0:array,1:array,2:ShippingProvider} */
    private function shipment(int $shipmentId): array
    {
        $shipment=$this->repo->db()->fetch('SELECT * FROM om_shipments WHERE id=:id',['id'=>$shipmentId]);
        if (!$shipment) throw new InvalidArgumentException('Nie znaleziono przesyłki.');
        [$carrier,$provider]=$this->carrier((int)$shipment['carrier_account_id'],false);
        return [$shipment,$carrier,$provider];
    }
    private function payload(string $provider,array $response,array $meta): string { return OrderRepository::json(['provider'=>$provider,'response'=>array_intersect_key($response,array_flip(['id','commandId','status','tracking_number','trackingNumber'])),'meta'=>$meta]); }
    private static function prettyCarrier(string $carrier): string
    {
        $parts=array_map('trim',explode('→',$carrier));
        foreach ($parts as &$part) { foreach (['INPOST'=>'InPost','DPD'=>'DPD','DHL'=>'DHL','UPS'=>'UPS','GLS'=>'GLS','FEDEX'=>'FedEx','ORLEN'=>'ORLEN','POCZTA POLSKA'=>'Poczta Polska','PACKETA'=>'Packeta','ALLEGRO'=>'Allegro'] as $key=>$label) { if (strtoupper($part)===$key) { $part=$label; break; } } } unset($part);
        return implode(' → ',$parts);
    }
    private static function carrierFromDelivery(string $delivery,string $provider,string $fallback): string
    {
        foreach (['inpost'=>'InPost','dpd'=>'DPD','dhl'=>'DHL','ups'=>'UPS','gls'=>'GLS','fedex'=>'FedEx','orlen'=>'ORLEN Paczka','poczta'=>'Poczta Polska','pocztex'=>'Pocztex','packeta'=>'Packeta'] as $needle=>$label) { if (stripos($delivery,$needle)!==false) { return $label; } }
        if ($provider==='inpost_shipx') { return 'InPost'; } if ($provider==='apaczka') { return 'Apaczka'; } if ($provider==='erli_shipping') { return 'ERLI'; }
        return $fallback!==''?$fallback:'Nie rozpoznano';
    }
    private static function statusLabel(string $status,string $provider): array
    {
        // ERLI zwraca camelCase (readyToSend) – sprowadzamy do READY_TO_SEND.
        $key=strtoupper((string)preg_replace('/([a-z])([A-Z])/','$1_$2',trim($status)));
        $map=['PENDING'=>['Przygotowana do nadania','pending'],'PREPARING'=>['Przygotowywana','pending'],'IN_PROGRESS'=>['Tworzenie przesyłki','pending'],'CREATED'=>['Utworzona','pending'],'CONFIRMED'=>['Potwierdzona','pending'],'READY_TO_SEND'=>['Gotowa do nadania','pending'],'WAITING_FOR_COURIER'=>['Czeka na kuriera','pending'],'SENT'=>['Nadana','transit'],'ON_THE_WAY'=>['W drodze','transit'],'IN_TRANSIT'=>['W drodze','transit'],'DISPATCHED_BY_SENDER'=>['Nadana przez sprzedawcę','transit'],'COLLECTED_FROM_SENDER'=>['Odebrana od nadawcy','transit'],'TAKEN_BY_COURIER'=>['Odebrana przez kuriera','transit'],'ADOPTED_AT_SOURCE_BRANCH'=>['Przyjęta w oddziale','transit'],'SENT_FROM_SOURCE_BRANCH'=>['Wysłana z oddziału','transit'],'REDIRECTED'=>['Przekierowana','transit'],'RELEASED_FOR_DELIVERY'=>['W doręczeniu','delivery'],'OUT_FOR_DELIVERY'=>['W doręczeniu','delivery'],'READY_TO_DELIVER'=>['W doręczeniu','delivery'],'AVAILABLE_FOR_PICKUP'=>['Gotowa do odbioru','pickup'],'READY_TO_PICKUP'=>['Gotowa do odbioru','pickup'],'NOTICE_LEFT'=>['Awizowana','issue'],'PICKUP_TIME_EXPIRED'=>['Minął czas odbioru','issue'],'DELIVERY_UNSUCCESSFUL'=>['Nieudane doręczenie','issue'],'CLAIMED'=>['Reklamowana','issue'],'ISSUE'=>['Problem z przesyłką','issue'],'DELIVERED'=>['Doręczona','delivered'],'RETURNED'=>['Zwrócona','returned'],'RETURNED_TO_SENDER'=>['Zwrócona do nadawcy','returned'],'CANCELLED'=>['Anulowana','cancelled'],'CANCELED'=>['Anulowana','cancelled'],'ANULOWANO'=>['Anulowana','cancelled'],'ERROR'=>['Błąd przesyłki','issue'],'TECHNICAL'=>['Status techniczny','unknown'],'TRACKING_UNAVAILABLE'=>['Śledzenie niedostępne','unknown'],'TRACKING_EXPIRED'=>['Śledzenie wygasło','unknown'],'UNKNOWN'=>['Status nieznany','unknown'],'SUCCESS'=>['Przesyłka utworzona','created']];
        if (isset($map[$key])) { return $map[$key]; }
        if ($key==='') { return [$provider==='allegro_wza'?'Odśwież, aby pobrać status':'Brak statusu','unknown']; }
        return [ucfirst(strtolower(str_replace('_',' ',$key))),'unknown'];
    }
    private static function statusDescription(string $description,string $status): string
    {
        $description=trim($description); $key=strtolower($description);
        $translations=['prepared by sender'=>'Przesyłka została przygotowana przez nadawcę.','picked up by courier'=>'Przesyłka została odebrana przez kuriera.','in transit'=>'Przesyłka jest w drodze.','out for delivery'=>'Przesyłka jest w trakcie doręczenia.','delivered'=>'Przesyłka została doręczona.','available for pickup'=>'Przesyłka oczekuje na odbiór w punkcie.'];
        if (isset($translations[$key])) { return $translations[$key]; }
        return $description;
    }
}
