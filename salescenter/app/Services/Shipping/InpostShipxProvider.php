<?php

declare(strict_types=1);
namespace App\Services\Shipping;

use InvalidArgumentException;

final class InpostShipxProvider extends ShippingProvider
{
    private const SERVICES=['inpost_locker_standard'=>'InPost Paczkomat 24/7','inpost_courier_standard'=>'InPost Kurier standard'];

    public static function definition(): array
    {
        return [
            'key'=>'inpost_shipx','label'=>'InPost ShipX','description'=>'Bezpośrednie nadawanie przez organizację ShipX (własna umowa z InPost).',
            'badge'=>['text'=>'I','tone'=>'inpost'],'color'=>'#ffc800','ink'=>'#1d1d1b','group'=>'Przewoźnicy i brokerzy',
            'steps'=>['Zaloguj się do Managera Paczek InPost.','Moje konto → API: skopiuj token ShipX i ID organizacji.','Wklej dane, wybierz środowisko i zapisz.','Dla Paczkomatów ustaw domyślny punkt odbioru w sekcji „Domyślne nadawanie” poniżej.'],'source_platform'=>'',
            'capabilities'=>['valuation'=>false,'cancel'=>false,'label'=>true,'tracking'=>true,'cod'=>'none','source_tracking'=>'manual','pickup_protocol'=>false],
            'info'=>[
                'Token API i ID organizacji znajdziesz w Manager Paczek → Moje konto → API.',
                'Paczkomat bierze punkt z zamówienia, a gdy go brak – domyślny punkt z ustawień poniżej.',
                'Numer przesyłki do marketplace’u przekazujesz przyciskiem „Wyślij nr do źródła”.',
            ],
            'docs'=>'https://dokumentacja-inpost.atlassian.net/wiki/spaces/PL/pages/11731061',
            'fields'=>[
                ['name'=>'name','label'=>'Nazwa połączenia','type'=>'text','default'=>'InPost ShipX','required'=>true,'max'=>150],
                ['name'=>'organization_id','label'=>'ID organizacji','type'=>'text','required'=>true,'max'=>40],
                ['name'=>'token','label'=>'Token ShipX','type'=>'password','required'=>true,'max'=>1000],
                ['name'=>'environment','label'=>'Środowisko','type'=>'select','default'=>'production','options'=>['production'=>'Produkcyjne','sandbox'=>'Sandbox']],
            ],
        ];
    }

    public function servicesTtl(): int { return 0; }

    public function services(array $carrier,array $order): array
    {
        $options=[];
        foreach (self::SERVICES as $value=>$name) { $options[]=['value'=>$value,'name'=>$name,'carrier'=>'InPost']; }
        return $options;
    }

    public function preferredService(array $carrier,array $order,array $defaults): string
    {
        $service=(string)($defaults['service']??'auto');
        if (isset(self::SERVICES[$service])) { return $service; }
        $delivery=strtolower((string)($order['details']['delivery']??'').' '.(string)($order['details']['pickup']??''));
        return (strpos($delivery,'paczkomat')!==false||strpos($delivery,'locker')!==false||!empty($order['details']['pickup']))?'inpost_locker_standard':'inpost_courier_standard';
    }

    public function matchOrder(array $order,array $public): array
    {
        $delivery=strtolower((string)($order['details']['delivery']??'').' '.(string)($order['details']['pickup']??''));
        return (strpos($delivery,'inpost')!==false||strpos($delivery,'paczkomat')!==false)?[20,'Dopasowano InPost na podstawie metody dostawy']:[];
    }

    public function create(array $carrier,array $order,array $input): array
    {
        $service=(string)($input['shipping_service']??$input['service']??'');
        if (!isset(self::SERVICES[$service])) { throw new InvalidArgumentException('Wybierz usługę InPost.'); }
        $package=ShipmentInput::package($input);
        $payload=['receiver'=>$this->receiver($input),'parcels'=>[['dimensions'=>['length'=>$package['length']*10,'width'=>$package['width']*10,'height'=>$package['height']*10,'unit'=>'mm'],'weight'=>['amount'=>$package['weight'],'unit'=>'kg'],'is_non_standard'=>!empty($input['non_standard'])]],'service'=>$service,'reference'=>$order['external_id']];
        if ($service==='inpost_locker_standard') {
            $point=trim((string)($order['shipping_address']['point']??'')) ?: trim((string)($this->defaults()['default_point']??''));
            if ($point==='') { throw new InvalidArgumentException('Ustaw domyślny punkt odbioru w zakładce Przesyłki i presety.'); }
            $payload['custom_attributes']=['target_point'=>$point];
        }
        $response=ShipmentInput::jsonRequest($this->base($carrier).'/organizations/'.rawurlencode((string)$carrier['public']['organization_id']).'/shipments',$this->auth($carrier),$payload);
        return ['external_id'=>(string)($response['id']??''),'tracking'=>(string)($response['tracking_number']??''),'state'=>(string)($response['status']??'created'),'service_code'=>$service,'service_name'=>self::SERVICES[$service],'carrier_name'=>'InPost','response'=>$response];
    }

    public function refresh(array $carrier,array $shipment,array $meta): array
    {
        $response=ShipmentInput::getJson($this->base($carrier).'/shipments/'.rawurlencode((string)$shipment['external_id']),$this->auth($carrier));
        $state=(string)($response['status']??$shipment['state']);
        $meta['tracking_status']=$state; $meta['tracking_updated_at']=(string)($response['updated_at']??gmdate('c'));
        return ['state'=>$state,'external_id'=>(string)$shipment['external_id'],'tracking'=>(string)($response['tracking_number']??$shipment['tracking']),'response'=>$response,'meta'=>$meta];
    }

    public function label(array $carrier,array $shipment,string $pageSize): string
    {
        return ShipmentInput::getBinary($this->base($carrier).'/shipments/'.rawurlencode((string)$shipment['external_id']).'/label?format=pdf&type=normal',$this->auth($carrier));
    }

    private function receiver(array $input): array
    {
        $name=ShipmentInput::required($input,'receiver_name',150); $parts=preg_split('/\s+/',trim($name),2);
        return ['first_name'=>$parts[0]??$name,'last_name'=>$parts[1]??'','email'=>ShipmentInput::required($input,'receiver_email',200),'phone'=>ShipmentInput::required($input,'receiver_phone',30),'address'=>['street'=>ShipmentInput::required($input,'receiver_street',150),'building_number'=>ShipmentInput::required($input,'receiver_building',30),'city'=>ShipmentInput::required($input,'receiver_city',100),'post_code'=>ShipmentInput::required($input,'receiver_postal_code',20),'country_code'=>ShipmentInput::country($input['receiver_country']??'PL')]];
    }
    private function base(array $carrier): string { return ($carrier['public']['environment']??'production')==='sandbox'?'https://sandbox-api-shipx-pl.easypack24.net/v1':'https://api-shipx-pl.easypack24.net/v1'; }
    private function auth(array $carrier): array { return ['Authorization: Bearer '.(string)($carrier['secret']['token']??'')]; }
}
