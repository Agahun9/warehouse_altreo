<?php

declare(strict_types=1);
namespace App\Services\Shipping;

use App\Services\OrderNormalizer;
use InvalidArgumentException;
use RuntimeException;

final class ApaczkaProvider extends ShippingProvider
{
    public static function definition(): array
    {
        return [
            'key'=>'apaczka','label'=>'Apaczka / Alsendo','description'=>'Broker wielu przewoźników (DPD, DHL, InPost, UPS, Pocztex…) przez Web API v2.',
            'badge'=>['text'=>'AP','tone'=>'apaczka'],'color'=>'#1462ba','ink'=>'#fff','group'=>'Przewoźnicy i brokerzy',
            'steps'=>['Zaloguj się do panelu Apaczka.pl.','Moje konto → Web API: utwórz aplikację i skopiuj App ID oraz App Secret.','Wklej klucze i zapisz.','Uzupełnij dane nadawcy i rachunek do pobrań w sekcji „Domyślne nadawanie” poniżej.'],'source_platform'=>'',
            'capabilities'=>['valuation'=>true,'cancel'=>true,'label'=>true,'tracking'=>true,'cod'=>'form','source_tracking'=>'manual','pickup_protocol'=>false,'pickup_order'=>true],
            'info'=>[
                'App ID i App Secret wygenerujesz w panelu Apaczki → Moje konto → Web API.',
                'Wymaga uzupełnionych danych nadawcy i – dla pobrań – numeru konta bankowego w ustawieniach poniżej.',
                'W zamówieniu pokazujemy wycenę na żywo i tylko usługi dostępne dla adresu odbiorcy.',
                'Anulowanie przesyłki działa bezpośrednio z panelu zamówienia.',
                'Podjazd kuriera zamawiasz przy tworzeniu przesyłki: „Zamów podjazd kuriera” i wybór dnia oraz godzin odbioru (Apaczka nie pozwala dodać podjazdu do już utworzonej przesyłki).',
            ],
            'docs'=>'https://panel.apaczka.pl/dokumentacja_api_v2.php',
            'fields'=>[
                ['name'=>'name','label'=>'Nazwa połączenia','type'=>'text','default'=>'Apaczka','required'=>true,'max'=>150],
                ['name'=>'app_id','label'=>'App ID','type'=>'text','required'=>true,'max'=>200],
                ['name'=>'app_secret','label'=>'App Secret','type'=>'password','required'=>true,'max'=>1000],
            ],
        ];
    }

    public function servicesTtl(): int { return 86400; }

    public function services(array $carrier,array $order): array
    {
        $response=$this->api($carrier,'service_structure',[]);
        $options=[];
        foreach ((array)($response['response']['services']??[]) as $serviceKey=>$service) {
            if (!is_array($service)) { continue; }
            $id=(int)($service['id']??$service['service_id']??$serviceKey); if ($id<1) { continue; }
            // Flagi sposobu nadania/doręczenia: pickup_courier 0=bez kuriera, 1=opcjonalny, 2=wymagany; door_to_point itd. = 0/1.
            $flags=[]; foreach (self::SERVICE_FLAGS as $flag) { $flags[$flag]=(string)($service[$flag]??''); }
            $options[]=['value'=>(string)$id,'name'=>(string)($service['name']??('Usługa '.$id)),'carrier'=>(string)($service['supplier']??'Apaczka')]+$flags;
        }
        return $options;
    }

    /** Przewoźnicy rozpoznawani w nazwie metody dostawy: klucz => wzorzec w metodzie dostawy i w nazwie/dostawcy usługi Apaczki. */
    private const CARRIERS=['inpost'=>'/inpost|paczkomat/iu','dpd'=>'/\\bdpd\\b/iu','dhl'=>'/\\bdhl\\b/iu','pocztex'=>'/pocztex|poczta polska/iu','ups'=>'/\\bups\\b/iu','fedex'=>'/fedex/iu','gls'=>'/\\bgls\\b/iu','orlen'=>'/orlen/iu'];
    private const SERVICE_FLAGS=['pickup_courier','door_to_door','door_to_point','point_to_point','point_to_door'];
    private const POINT_PATTERN='/paczkomat|automat|punkt|point|pickup|\\bbox\\b|\\bpop\\b|odbi[oó]r w/iu';

    public function preferredService(array $carrier,array $order,array $defaults): string
    {
        $id=(int)($defaults['apaczka_service_id']??0); if ($id>0) { return (string)$id; }
        return $this->serviceForDelivery((int)($carrier['id']??0),$order);
    }

    public function matchOrder(array $order,array $public): array
    {
        $carrier=self::deliveryCarrier($order);
        return $carrier!==''?[40,'Dopasowano '.strtoupper($carrier).' w Apaczce na podstawie metody dostawy']:[];
    }

    /** Przewoźnik z metody dostawy albo '' (zagranica, Allegro One, brak nazwy przewoźnika – wtedy potrzebne ręczne mapowanie). */
    private static function deliveryCarrier(array $order): string
    {
        $delivery=(string)($order['details']['delivery']??'');
        $address=(array)($order['details']['address']??[]);
        $country=strtoupper(trim((string)($address['country']??$address['countryCode']??'PL')));
        if (($country!=='' && $country!=='PL') || preg_match('/international|zagranic|z polski do|one box|one punkt|allegro one/iu',$delivery)) { return ''; }
        foreach (self::CARRIERS as $key=>$pattern) { if (preg_match($pattern,$delivery)) { return $key; } }
        return '';
    }

    /** Usługa Apaczki pasująca do przewoźnika i typu dostawy (punkt/kurier) – tylko z zapisanej listy usług, bez zapytań do API. */
    private function serviceForDelivery(int $carrierAccountId,array $order): string
    {
        $carrier=self::deliveryCarrier($order); if ($carrier==='' || $carrierAccountId<1) { return ''; }
        $toPoint=trim((string)($order['details']['pickup']??''))!=='' || preg_match(self::POINT_PATTERN,(string)($order['details']['delivery']??''));
        $best=''; $bestScore=PHP_INT_MIN;
        foreach ((array)($this->repo->setting('carrier_services_'.$carrierAccountId)['options']??[]) as $option) {
            $text=(string)($option['carrier']??'').' '.(string)($option['name']??'');
            if (!preg_match(self::CARRIERS[$carrier],$text)) { continue; }
            $isPoint=(bool)preg_match(self::POINT_PATTERN,(string)($option['name']??''));
            if ($isPoint!==$toPoint) { continue; }
            // Najprostsza usługa: bez ekspresów, gwarancji godzinowych, sobót, palet i wysyłek zagranicznych.
            $score=-mb_strlen((string)($option['name']??''),'UTF-8');
            if (preg_match('/express|ekspres|\\d{1,2}[:.]\\d{2}|sobot|saturday|palet|pallet|international|zagranic|europ|\\bnstd\\b|niestandard/iu',$text)) { $score-=1000; }
            if (preg_match('/standard|classic|parcel|kurier|courier/iu',$text)) { $score+=5; }
            if ($score>$bestScore) { $bestScore=$score; $best=(string)($option['value']??''); }
        }
        return $best;
    }

    public function create(array $carrier,array $order,array $input): array
    {
        $serviceId=(int)($input['shipping_service']??$input['apaczka_service_id']??0); if ($serviceId<1) throw new InvalidArgumentException('Wybierz usługę Apaczka.');
        $serviceMeta=$this->serviceMeta($carrier,$order,(string)$serviceId,trim((string)($order['details']['delivery']??'')),'Apaczka');
        $data=$this->orderData($carrier,$order,$input,$serviceId);
        $service=$this->serviceInfo($carrier,$order,$serviceId);
        $data['pickup']=$this->pickup($carrier,$service,$serviceId,(string)$data['pickup']['type'],$input);
        if (self::flag($service,'door_to_point')||self::flag($service,'point_to_point')) {
            if ((string)($data['address']['receiver']['foreign_address_id']??'')==='' && !self::flag($service,'door_to_door') && !self::flag($service,'point_to_door')) { throw new InvalidArgumentException('Usługa „'.$serviceMeta['service_name'].'” doręcza do punktu – podaj kod punktu odbioru w sekcji „Sprawdź odbiorcę i dane zaawansowane”.'); }
        }
        $response=$this->api($carrier,'order_send',['order'=>$data]);
        $remote=$response['response']['order']??[];
        return ['external_id'=>(string)($remote['id']??''),'tracking'=>(string)($remote['waybill_number']??''),'state'=>(string)($remote['status']??'created'),'service_code'=>(string)$serviceId,'service_name'=>$serviceMeta['service_name'],'carrier_name'=>$serviceMeta['carrier_name'],'meta'=>['pickup'=>$data['pickup']],'response'=>$response];
    }

    /** Terminy podjazdu kuriera (dzień + okno godzin) dla usługi i kodu pocztowego nadawcy. */
    public function pickupSlots(array $carrier,array $order,array $input): array
    {
        $serviceId=(int)($input['shipping_service']??0); if ($serviceId<1) throw new InvalidArgumentException('Wybierz usługę Apaczka, aby pobrać terminy podjazdu.');
        if (self::forcedPickup($this->serviceInfo($carrier,$order,$serviceId))==='SELF') { throw new InvalidArgumentException('Ta usługa nie obsługuje podjazdu kuriera – paczkę nadajesz w punkcie.'); }
        return ['slots'=>$this->pickupWindows($carrier,$serviceId)];
    }

    public function valuation(array $carrier,array $order,array $input): array
    {
        $serviceId=(int)($input['shipping_service']??0);
        $response=$this->api($carrier,'order_valuation',['order'=>$this->orderData($carrier,$order,$input,$serviceId)]);
        $prices=[];
        foreach ((array)($response['response']['price_table']??[]) as $id=>$entry) {
            $gross=(int)($entry['price_gross']??0);
            if ($gross<1) { continue; }
            $prices[(string)$id]=['price_gross_cents'=>$gross,'price_gross'=>number_format($gross/100,2,',',' ').' PLN'];
        }
        if (!$prices) { throw new RuntimeException('Apaczka nie zwróciła dostępnych usług dla podanego adresu i parametrów paczki.'); }
        $result=['provider'=>'apaczka','prices'=>$prices];
        if ($serviceId>0) {
            if (!isset($prices[(string)$serviceId])) { throw new RuntimeException('Wybrana usługa Apaczka nie jest dostępna dla podanego adresu i parametrów paczki.'); }
            $result+=$prices[(string)$serviceId];
        }
        return $result;
    }

    public function refresh(array $carrier,array $shipment,array $meta): array
    {
        $response=$this->api($carrier,'order/'.rawurlencode((string)$shipment['external_id']),[]);
        $remote=$response['response']['order']??[]; $state=(string)($remote['status']??$shipment['state']);
        $meta['tracking_status']=$state; $meta['tracking_updated_at']=gmdate('c');
        return ['state'=>$state,'external_id'=>(string)$shipment['external_id'],'tracking'=>(string)($remote['waybill_number']??$shipment['tracking']),'response'=>$response,'meta'=>$meta];
    }

    public function cancel(array $carrier,array $shipment): array { return $this->api($carrier,'cancel_order/'.rawurlencode((string)$shipment['external_id']),[]); }

    public function label(array $carrier,array $shipment,string $pageSize): string
    {
        $response=$this->api($carrier,'waybill/'.rawurlencode((string)$shipment['external_id']),[]);
        $bytes=base64_decode((string)($response['response']['waybill']??''),true);
        if ($bytes===false||$bytes==='') throw new RuntimeException('Apaczka nie zwróciła etykiety.');
        return $bytes;
    }

    private function orderData(array $carrier,array $order,array $input,int $serviceId): array
    {
        $package=ShipmentInput::package($input); $defaults=$this->defaults();
        // is_zebra=1: etykieta 10x15 pod drukarkę etykiet zamiast strony A4 (bez tego Apaczka bierze format z ustawień konta).
        $data=['is_zebra'=>1,'service_id'=>$serviceId,'address'=>['sender'=>$this->address($this->sender()),'receiver'=>$this->receiver($input)],'shipment_value'=>(int)$order['total_cents'],'shipment_currency'=>$order['currency'],'pickup'=>['type'=>(string)($defaults['pickup_type']??'SELF'),'date'=>'','hours_from'=>'','hours_to'=>''],'shipment'=>[['dimension1'=>$package['length'],'dimension2'=>$package['width'],'dimension3'=>$package['height'],'weight'=>$package['weight'],'is_nstd'=>0,'shipment_type_code'=>'PACZKA']],'content'=>ShipmentInput::content((int)$order['id'],$input,$defaults)];
        // Dostawa do punktu (np. Paczkomat z Allegro): kod punktu z formularza albo z zamówienia.
        $pickup=trim((string)($input['receiver_point']??$order['details']['pickup']??''));
        if ($pickup!=='' && $serviceId>0) {
            $service=$this->serviceInfo($carrier,$order,$serviceId);
            $toPoint=isset($service['door_to_point'])&&$service['door_to_point']!==''?(self::flag($service,'door_to_point')||self::flag($service,'point_to_point')):(bool)preg_match(self::POINT_PATTERN,(string)($service['name']??''));
            if ($toPoint) { $data['address']['receiver']['foreign_address_id']=mb_substr($pickup,0,100,'UTF-8'); }
        }
        $this->applyCod($data,$input,(string)$order['currency']);
        return $data;
    }
    /** Wpis usługi z listy kont (z flagami nadania); stary cache bez flag jest odświeżany raz. */
    private function serviceInfo(array $carrier,array $order,int $serviceId): array
    {
        $find=function (array $options) use ($serviceId): array { foreach ($options as $option) { if ((string)($option['value']??'')===(string)$serviceId) { return (array)$option; } } return []; };
        try {
            $service=$find($this->cachedServices($carrier,$order));
            if ($service && !array_key_exists('pickup_courier',$service)) {
                $this->repo->saveSetting('carrier_services_'.(int)$carrier['id'],['fetched_at'=>0,'options'=>[]]);
                $service=$find($this->cachedServices($carrier,$order));
            }
            return $service;
        } catch (\Throwable $error) { return []; }
    }
    private static function flag(array $service,string $flag): bool { return (string)($service[$flag]??'')==='1'; }

    /** Sposób nadania wymuszony przez usługę: kurier wymagany (pickup_courier=2) albo tylko „od drzwi” => COURIER; bez kuriera albo tylko „z punktu” => SELF; inaczej ''. */
    private static function forcedPickup(array $service): string
    {
        $courier=(string)($service['pickup_courier']??'');
        $fromDoor=self::flag($service,'door_to_door')||self::flag($service,'door_to_point'); $fromPoint=self::flag($service,'point_to_point')||self::flag($service,'point_to_door');
        if ($courier==='2' || ($fromDoor && !$fromPoint)) { return 'COURIER'; }
        if ($courier==='0' || ($fromPoint && !$fromDoor)) { return 'SELF'; }
        return '';
    }

    /**
     * Sposób nadania: wybór z formularza (pickup_mode COURIER/SELF + pickup_slot „data|od|do”), a bez wyboru (np. automatyzacje)
     * wymóg usługi albo ustawienie domyślne. COURIER bez wybranego terminu => najbliższe okno z pickup_hours.
     */
    private function pickup(array $carrier,array $service,int $serviceId,string $defaultType,array $input): array
    {
        $forced=self::forcedPickup($service);
        $type=$forced!==''?$forced:($defaultType==='COURIER'?'COURIER':'SELF');
        $mode=strtoupper(trim((string)($input['pickup_mode']??'')));
        if ($mode==='COURIER' || $mode==='SELF') {
            if ($forced!=='' && $forced!==$mode) { throw new InvalidArgumentException($forced==='COURIER'?'Ta usługa wymaga podjazdu kuriera – wybierz „Zamów podjazd kuriera” i termin.':'Ta usługa nie obsługuje podjazdu kuriera – paczkę nadajesz w punkcie.'); }
            $type=$mode;
        }
        if ($type!=='COURIER') { return ['type'=>'SELF','date'=>'','hours_from'=>'','hours_to'=>'']; }
        $slots=$this->pickupWindows($carrier,$serviceId);
        $chosen=trim((string)($input['pickup_slot']??''));
        if ($chosen!=='') {
            foreach ($slots as $slot) { if (implode('|',$slot)===$chosen) { return ['type'=>'COURIER']+$slot; } }
            throw new InvalidArgumentException('Wybrany termin podjazdu kuriera nie jest już dostępny – wybierz inny termin.');
        }
        if ($slots) { return ['type'=>'COURIER']+$slots[0]; }
        throw new RuntimeException('Apaczka nie zwróciła wolnego terminu odbioru przez kuriera dla tej usługi i kodu pocztowego nadawcy.');
    }

    /** Okna odbioru z pickup_hours (dziś + kolejne dni robocze), najpierw dla wybranej usługi, rosnąco wg daty. */
    private function pickupWindows(array $carrier,int $serviceId): array
    {
        $hours=(array)($this->api($carrier,'pickup_hours',['postal_code'=>$this->sender()['postal_code'],'service_id'=>$serviceId,'remove_index'=>false])['response']['hours']??[]);
        ksort($hours); $own=[]; $other=[];
        foreach ($hours as $date=>$day) {
            foreach ((array)($day['services']??[]) as $window) {
                if ((string)($window['timefrom']??'')==='' || (string)($window['timeto']??'')==='') { continue; }
                $slot=['date'=>(string)($day['date']??$date),'hours_from'=>(string)$window['timefrom'],'hours_to'=>(string)$window['timeto']];
                if ((string)($window['service']??'')===(string)$serviceId) { $own[implode('|',$slot)]=$slot; } else { $other[implode('|',$slot)]=$slot; }
            }
        }
        return array_values($own?:$other);
    }
    private function applyCod(array &$orderData,array $input,string $currency): void
    {
        if (empty($input['cash_on_delivery'])) { return; }
        $amount=OrderNormalizer::money((string)($input['cod_amount']??'0'));
        if ($amount<1) { throw new InvalidArgumentException('Kwota pobrania musi być większa od zera.'); }
        $bankAccount=preg_replace('/\s+/','',trim((string)($this->defaults()['cod_bank_account']??'')))??'';
        if ($currency==='PLN' && strncasecmp($bankAccount,'PL',2)===0) { $bankAccount=substr($bankAccount,2); }
        if ($currency==='PLN' && !preg_match('/^\d{26}$/D',$bankAccount)) { throw new InvalidArgumentException('Uzupełnij poprawny numer konta pobrania w zakładce Przesyłki i presety.'); }
        $orderData['cod']=['amount'=>$amount,'currency'=>$currency,'bankaccount'=>$bankAccount];
    }
    private function address(array $sender): array { return ['name'=>$sender['name'],'contact_person'=>self::contactPerson($sender['name']),'email'=>$sender['email'],'phone'=>$sender['phone'],'line1'=>$sender['street'],'line2'=>$sender['building'],'postal_code'=>$sender['postal_code'],'city'=>$sender['city'],'country_code'=>'PL','is_residential'=>0]; }
    private function receiver(array $input): array
    {
        $name=ShipmentInput::required($input,'receiver_name',150);
        return ['name'=>$name,'contact_person'=>self::contactPerson($name),'email'=>ShipmentInput::email($input,'receiver_email'),'phone'=>ShipmentInput::phone($input,'receiver_phone'),'line1'=>ShipmentInput::required($input,'receiver_street',150),'line2'=>ShipmentInput::required($input,'receiver_building',30),'postal_code'=>ShipmentInput::required($input,'receiver_postal_code',20),'city'=>ShipmentInput::required($input,'receiver_city',100),'country_code'=>ShipmentInput::country($input['receiver_country']??'PL'),'is_residential'=>0];
    }
    /** Osoba kontaktowa max 30 znaków (np. ORLEN Paczka dzieli ją na imię/nazwisko z limitem 30) – ucięta na granicy słowa. */
    private static function contactPerson(string $name): string
    {
        $name=trim((string)preg_replace('/\s+/u',' ',$name)); if (mb_strlen($name,'UTF-8')<=30) { return $name; }
        $short=mb_substr($name,0,30,'UTF-8'); $space=mb_strrpos($short,' ',0,'UTF-8');
        return rtrim($space!==false && $space>=10?mb_substr($short,0,$space,'UTF-8'):$short,' ,.-');
    }
    private function api(array $carrier,string $route,array $data): array
    {
        $appId=(string)($carrier['public']['app_id']??''); $secret=(string)($carrier['secret']['app_secret']??'');
        $route=trim($route,'/').'/';$json=json_encode((object)$data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$expires=time()+900;
        $signature=hash_hmac('sha256',$appId.':'.$route.':'.$json.':'.$expires,$secret);
        return ShipmentInput::formRequest('https://www.apaczka.pl/api/v2/'.$route,['app_id'=>$appId,'request'=>$json,'expires'=>$expires,'signature'=>$signature]);
    }
}
