<?php

declare(strict_types=1);
namespace App\Services\Shipping;

use App\Services\AllegroService;
use InvalidArgumentException;
use RuntimeException;

final class AllegroWzaProvider extends ShippingProvider
{
    public static function definition(): array
    {
        return [
            'key'=>'allegro_wza','label'=>'Wysyłam z Allegro','description'=>'Nadawanie na umowach Allegro z autoryzacją konkretnego konta sprzedawcy.',
            'badge'=>['text'=>'A','tone'=>'allegro'],'color'=>'#ff5a00','ink'=>'#fff','group'=>'Umowy marketplace',
            'steps'=>['Podłącz konto Allegro w zakładce Konta (logowanie przez Allegro).','W Allegro Sales Center włącz Wysyłam z Allegro i zaakceptuj regulamin usługi.','Wybierz tutaj konto Allegro i zapisz połączenie.','W zamówieniu z tego konta wybierz konto nadawcze i utwórz przesyłkę.'],'source_platform'=>'allegro','source_label'=>'Allegro',
            'capabilities'=>['valuation'=>false,'cancel'=>false,'label'=>true,'tracking'=>true,'cod'=>'order','source_tracking'=>'manual','pickup_protocol'=>false],
            'info'=>[
                'Działa tylko dla zamówień z tego samego konta Allegro, które wybierzesz przy podłączeniu.',
                'Metoda dostawy i dane odbiorcy pochodzą z propozycji nadania Allegro; możesz zmienić usługę i gabaryty.',
                'Przesyłka powstaje asynchronicznie – po utworzeniu odśwież status, aby pobrać numer i etykietę.',
                'Pobranie ustawia Allegro na podstawie zamówienia.',
            ],
            'docs'=>'https://developer.allegro.pl/tutorials/jak-zarzadzac-przesylkami-przez-wysylam-z-allegro-LRVjK7K21sY',
            'fields'=>[
                ['name'=>'name','label'=>'Nazwa połączenia','type'=>'text','default'=>'Wysyłam z Allegro','required'=>true,'max'=>150],
                ['name'=>'allegro_account_id','label'=>'Konto Allegro','type'=>'source_account','required'=>true],
            ],
        ];
    }

    public function automaticService(array $carrier,array $order): string { return 'Automatycznie z zamówienia Allegro'; }

    public function services(array $carrier,array $order): array
    {
        $this->assertOrder($carrier,$order);
        $response=(new AllegroService(true))->shipmentServices($this->account($carrier));
        $options=[];
        foreach ((array)($response['services']??[]) as $service) {
            $id=is_array($service['id']??null)?$service['id']:[];
            $deliveryId=trim((string)($id['deliveryMethodId']??''));
            if ($deliveryId==='') { continue; }
            $credentials=trim((string)($id['credentialsId']??''));
            $options[]=['value'=>$deliveryId.'::'.$credentials,'name'=>(string)($service['name']??$deliveryId),'carrier'=>(string)($service['carrierId']??'Allegro')];
        }
        return $options;
    }

    public function create(array $carrier,array $order,array $input): array
    {
        $this->assertOrder($carrier,$order);
        $package=ShipmentInput::package($input);
        $allegro=new AllegroService(true); $source=$this->account($carrier);
        $proposal=$allegro->shipmentProposal($source,$order['external_id']);
        $shipmentInput=$proposal['suggestedInput']??null;
        if (!is_array($shipmentInput)) { throw new RuntimeException('Allegro nie zwróciło propozycji nadania dla zamówienia.'); }
        $selectedService=trim((string)($input['shipping_service']??''));
        if ($selectedService!=='') {
            [$deliveryMethodId,$credentialsId]=array_pad(explode('::',$selectedService,2),2,'');
            if (!preg_match('/^[a-zA-Z0-9-]{1,100}$/D',$deliveryMethodId) || ($credentialsId!==''&&!preg_match('/^[a-zA-Z0-9-]{1,100}$/D',$credentialsId))) { throw new InvalidArgumentException('Nieprawidłowa usługa Wysyłam z Allegro.'); }
            $shipmentInput['deliveryMethodId']=$deliveryMethodId;
            if ($credentialsId!=='') { $shipmentInput['credentialsId']=$credentialsId; } else { unset($shipmentInput['credentialsId']); }
        }
        $shipmentInput['referenceNumber']=$order['external_id'];
        $parcel=is_array($shipmentInput['packages'][0]??null)?$shipmentInput['packages'][0]:[];
        $parcel['type']=in_array((string)($parcel['type']??''),['DOX','PACKAGE','PALLET','OTHER'],true)?$parcel['type']:'PACKAGE';
        $parcel['length']=['value'=>$package['length'],'unit'=>'CENTIMETER'];
        $parcel['width']=['value'=>$package['width'],'unit'=>'CENTIMETER'];
        $parcel['height']=['value'=>$package['height'],'unit'=>'CENTIMETER'];
        $parcel['weight']=['value'=>$package['weight'],'unit'=>'KILOGRAMS'];
        $shipmentInput['packages']=[$parcel];
        $shipmentInput=$this->normalizeInput($shipmentInput);
        $serviceCode=trim((string)($shipmentInput['deliveryMethodId']??''));
        $serviceMeta=$this->serviceMeta($carrier,$order,$selectedService!==''?$selectedService:$serviceCode,trim((string)($order['details']['delivery']??'')),'Allegro');
        $commandId=$this->uuidFromKey((string)$input['request_key']);
        $response=$allegro->createShipmentCommand($source,$commandId,$shipmentInput);
        return ['external_id'=>(string)($response['commandId']??$commandId),'state'=>(string)($response['status']??'IN_PROGRESS'),'service_code'=>$serviceCode,'service_name'=>$serviceMeta['service_name'],'carrier_name'=>$serviceMeta['carrier_name'],'response'=>$response,'tracked'=>false];
    }

    public function refresh(array $carrier,array $shipment,array $meta): array
    {
        $account=$this->account($carrier); $allegro=new AllegroService(true);
        $state=(string)$shipment['state']; $external=(string)$shipment['external_id']; $tracking=(string)$shipment['tracking']; $details=[];
        if ($state==='SUCCESS') { $details=$allegro->shipmentDetails($account,$external); $response=$details; $tracking=(string)($details['packages'][0]['waybill']??$tracking); }
        else {
            $response=$allegro->shipmentCommandStatus($account,$external); $state=(string)($response['status']??$state);
            if ($state==='SUCCESS'&&!empty($response['shipmentId'])) {
                $external=(string)$response['shipmentId']; $details=$allegro->shipmentDetails($account,$external);
                $tracking=(string)($details['packages'][0]['waybill']??$tracking);
            }
        }
        if ($details) {
            $carrierId=trim((string)($details['carrier']??''));
            $serviceCode=trim((string)($details['deliveryMethodId']??''));
            $transport=array_values(array_filter(array_map('strval',(array)($details['transport']??[]))));
            $meta['carrier_id']=$carrierId;
            if ($transport) { $meta['carrier_name']=implode(' → ',array_map([$this,'carrierLabel'],$transport)); }
            elseif ($carrierId!=='') { $meta['carrier_name']=$this->carrierLabel($carrierId); }
            if ($serviceCode!=='') {
                $meta['service_code']=$serviceCode;
                $meta=array_replace($meta,$this->serviceMeta($carrier,['platform'=>'allegro','account_id'=>(int)($carrier['public']['order_account_id']??0)],$serviceCode,(string)($meta['service_name']??''),(string)($meta['carrier_name']??'')));
            }
            if ($carrierId!=='' && $tracking!=='' && strpos($tracking,'PENDING:')!==0) {
                $latest=$this->latestTracking($allegro->shipmentTracking($account,$carrierId,$tracking),$tracking);
                if ($latest) { $meta=array_replace($meta,$latest); }
            }
        }
        return ['state'=>$state,'external_id'=>$external,'tracking'=>$tracking,'response'=>$response,'meta'=>$meta];
    }

    public function label(array $carrier,array $shipment,string $pageSize): string
    {
        if ($shipment['state']!=='SUCCESS') { throw new RuntimeException('Przesyłka Allegro nie jest jeszcze gotowa. Najpierw odśwież status.'); }
        return (new AllegroService(true))->shipmentLabel($this->account($carrier),(string)$shipment['external_id'],$pageSize);
    }

    private function assertOrder(array $carrier,array $order): void
    {
        if (!$this->supportsOrder($order,$carrier['public'])) { throw new InvalidArgumentException('Wysyłam z Allegro wymaga zamówienia z tego samego konta Allegro.'); }
    }
    private function account(array $carrier): array { return $this->sourceAccount('allegro',(int)($carrier['public']['order_account_id']??0)); }
    private function latestTracking(array $response,string $waybill): array
    {
        $statuses=[];
        foreach ((array)($response['waybills']??[]) as $item) {
            if ((string)($item['waybill']??'')===$waybill) { $statuses=(array)($item['trackingDetails']['statuses']??[]); break; }
        }
        usort($statuses,static function (array $a,array $b): int { return strcmp((string)($a['occurredAt']??''),(string)($b['occurredAt']??'')); });
        $latest=end($statuses); if (!is_array($latest)) { return []; }
        return ['tracking_status'=>(string)($latest['code']??''),'tracking_updated_at'=>(string)($latest['occurredAt']??''),'status_description'=>(string)($latest['description']??'')];
    }
    private function carrierLabel(string $carrier): string { return ucwords(strtolower(str_replace('_',' ',$carrier))); }
    private function normalizeInput(array $value): array { $value=$this->withoutNulls($value);if(($value['additionalProperties']??null)===[])$value['additionalProperties']=new \stdClass();return $value; }
    private function withoutNulls(array $value): array { foreach($value as $key=>$item){if($item===null){unset($value[$key]);continue;}if(is_array($item))$value[$key]=$this->withoutNulls($item);}return $value; }
    private function uuidFromKey(string $key): string { $hex=substr(hash('sha256',$key),0,32);return substr($hex,0,8).'-'.substr($hex,8,4).'-4'.substr($hex,13,3).'-a'.substr($hex,17,3).'-'.substr($hex,20,12); }
}
