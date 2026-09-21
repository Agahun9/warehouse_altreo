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
            'capabilities'=>['valuation'=>true,'cancel'=>true,'label'=>true,'tracking'=>true,'cod'=>'form','source_tracking'=>'manual','pickup_protocol'=>false],
            'info'=>[
                'App ID i App Secret wygenerujesz w panelu Apaczki → Moje konto → Web API.',
                'Wymaga uzupełnionych danych nadawcy i – dla pobrań – numeru konta bankowego w ustawieniach poniżej.',
                'W zamówieniu pokazujemy wycenę na żywo i tylko usługi dostępne dla adresu odbiorcy.',
                'Anulowanie przesyłki działa bezpośrednio z panelu zamówienia.',
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
            $options[]=['value'=>(string)$id,'name'=>(string)($service['name']??('Usługa '.$id)),'carrier'=>(string)($service['supplier']??'Apaczka')];
        }
        return $options;
    }

    public function preferredService(array $carrier,array $order,array $defaults): string { $id=(int)($defaults['apaczka_service_id']??0); return $id>0?(string)$id:''; }

    public function create(array $carrier,array $order,array $input): array
    {
        $serviceId=(int)($input['shipping_service']??$input['apaczka_service_id']??0); if ($serviceId<1) throw new InvalidArgumentException('Wybierz usługę Apaczka.');
        $serviceMeta=$this->serviceMeta($carrier,$order,(string)$serviceId,trim((string)($order['details']['delivery']??'')),'Apaczka');
        $response=$this->api($carrier,'order_send',['order'=>$this->orderData($order,$input,$serviceId)]);
        $remote=$response['response']['order']??[];
        return ['external_id'=>(string)($remote['id']??''),'tracking'=>(string)($remote['waybill_number']??''),'state'=>(string)($remote['status']??'created'),'service_code'=>(string)$serviceId,'service_name'=>$serviceMeta['service_name'],'carrier_name'=>$serviceMeta['carrier_name'],'response'=>$response];
    }

    public function valuation(array $carrier,array $order,array $input): array
    {
        $serviceId=(int)($input['shipping_service']??0);
        $response=$this->api($carrier,'order_valuation',['order'=>$this->orderData($order,$input,$serviceId)]);
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

    private function orderData(array $order,array $input,int $serviceId): array
    {
        $package=ShipmentInput::package($input); $defaults=$this->defaults();
        $data=['service_id'=>$serviceId,'address'=>['sender'=>$this->address($this->sender()),'receiver'=>$this->receiver($input)],'shipment_value'=>(int)$order['total_cents'],'shipment_currency'=>$order['currency'],'pickup'=>['type'=>(string)($defaults['pickup_type']??'SELF'),'date'=>'','hours_from'=>'','hours_to'=>''],'shipment'=>[['dimension1'=>$package['length'],'dimension2'=>$package['width'],'dimension3'=>$package['height'],'weight'=>$package['weight'],'is_nstd'=>0,'shipment_type_code'=>'PACZKA']],'content'=>ShipmentInput::content((int)$order['id'],$input,$defaults)];
        $this->applyCod($data,$input,(string)$order['currency']);
        return $data;
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
    private function address(array $sender): array { return ['name'=>$sender['name'],'contact_person'=>$sender['name'],'email'=>$sender['email'],'phone'=>$sender['phone'],'line1'=>$sender['street'],'line2'=>$sender['building'],'postal_code'=>$sender['postal_code'],'city'=>$sender['city'],'country_code'=>'PL','is_residential'=>0]; }
    private function receiver(array $input): array
    {
        $name=ShipmentInput::required($input,'receiver_name',150);
        return ['name'=>$name,'contact_person'=>$name,'email'=>ShipmentInput::email($input,'receiver_email'),'phone'=>ShipmentInput::phone($input,'receiver_phone'),'line1'=>ShipmentInput::required($input,'receiver_street',150),'line2'=>ShipmentInput::required($input,'receiver_building',30),'postal_code'=>ShipmentInput::required($input,'receiver_postal_code',20),'city'=>ShipmentInput::required($input,'receiver_city',100),'country_code'=>ShipmentInput::country($input['receiver_country']??'PL'),'is_residential'=>0];
    }
    private function api(array $carrier,string $route,array $data): array
    {
        $appId=(string)($carrier['public']['app_id']??''); $secret=(string)($carrier['secret']['app_secret']??'');
        $route=trim($route,'/').'/';$json=json_encode((object)$data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$expires=time()+900;
        $signature=hash_hmac('sha256',$appId.':'.$route.':'.$json.':'.$expires,$secret);
        return ShipmentInput::formRequest('https://www.apaczka.pl/api/v2/'.$route,['app_id'=>$appId,'request'=>$json,'expires'=>$expires,'signature'=>$signature]);
    }
}
