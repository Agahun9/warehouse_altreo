<?php

declare(strict_types=1);
namespace App\Services;

use App\Models\OrderRepository;
use InvalidArgumentException;
use RuntimeException;

final class OrderShipmentService
{
    private $repo;
    public function __construct(OrderRepository $repo) { $this->repo=$repo; }
    public static function defaults(OrderRepository $repo): array
    {
        $defaults=$repo->setting('shipping_defaults');
        $base=['default_carrier_account_id'=>0,'default_package'=>'auto','service'=>'auto','apaczka_service_id'=>0,'pickup_type'=>'SELF','default_point'=>'','content'=>'Towar','cod_bank_account'=>'','presets'=>['small'=>['length'=>23,'width'=>16,'height'=>10,'weight'=>0.5],'medium'=>['length'=>30,'width'=>20,'height'=>15,'weight'=>1],'large'=>['length'=>40,'width'=>30,'height'=>20,'weight'=>2]],'sender'=>['name'=>'','email'=>'','phone'=>'','street'=>'','building'=>'','postal_code'=>'','city'=>'']];
        return array_replace_recursive($base,$defaults);
    }
    /** Carrier account, package preset and InPost service suggested for an order. */
    public static function suggestion(array $order,array $accounts,array $defaults): array
    {
        $quantity=array_sum(array_map(static function ($item) { return max(0,(int)($item['quantity']??0)); },(array)($order['details']['items']??[])));
        $size=$defaults['default_package']==='auto'?($quantity<=1?'small':($quantity<=4?'medium':'large')):$defaults['default_package'];
        $delivery=strtolower((string)($order['details']['delivery']??'').' '.(string)($order['details']['pickup']??''));
        $service=$defaults['service']==='auto'?((strpos($delivery,'paczkomat')!==false||strpos($delivery,'locker')!==false||!empty($order['details']['pickup']))?'inpost_locker_standard':'inpost_courier_standard'):$defaults['service'];
        $selected=0; $reason='Pierwsze aktywne konto nadawcze';
        foreach ($accounts as $account) {
            if (!(int)$account['enabled']) { continue; }
            $public=json_decode((string)$account['public_config_json'],true)?:[];
            if ($order['platform']==='allegro' && $account['provider']==='allegro_wza' && (int)($public['order_account_id']??0)===(int)$order['account_id']) { $selected=(int)$account['id']; $reason='Dopasowano konto Wysyłam z Allegro do źródła zamówienia'; break; }
            if (!$selected && (strpos($delivery,'inpost')!==false||strpos($delivery,'paczkomat')!==false) && $account['provider']==='inpost_shipx') { $selected=(int)$account['id']; $reason='Dopasowano InPost na podstawie metody dostawy'; }
        }
        if (!$selected && (int)$defaults['default_carrier_account_id']) { foreach ($accounts as $account) { if ((int)$account['id']===(int)$defaults['default_carrier_account_id']&&(int)$account['enabled']) { $selected=(int)$account['id']; $reason='Użyto globalnego konta domyślnego'; break; } } }
        if (!$selected) { foreach ($accounts as $account) { if (!(int)$account['enabled'] || ($account['provider']==='allegro_wza' && $order['platform']!=='allegro')) { continue; } $selected=(int)$account['id']; $reason=$account['provider']==='apaczka'?'Dopasowano Apaczkę do zamówienia spoza Allegro':'Pierwsze zgodne konto nadawcze'; break; } }
        return ['carrier_account_id'=>$selected,'reason'=>$reason,'preset'=>$size,'package'=>$defaults['presets'][$size],'service'=>$service];
    }

    public function options(int $orderId,int $carrierAccountId): array
    {
        $order=$this->repo->order($orderId); $db=$this->repo->db();
        $carrier=$db->fetch('SELECT * FROM om_carrier_accounts WHERE id=:id AND enabled=1',['id'=>$carrierAccountId]);
        if (!$carrier) { throw new InvalidArgumentException('Wybierz aktywne konto nadawcze.'); }
        $provider=(string)$carrier['provider'];
        if ($provider==='inpost_shipx') {
            return ['provider'=>$provider,'options'=>[
                ['value'=>'inpost_locker_standard','name'=>'InPost Paczkomat 24/7','carrier'=>'InPost'],
                ['value'=>'inpost_courier_standard','name'=>'InPost Kurier standard','carrier'=>'InPost'],
            ]];
        }
        $cacheKey='carrier_services_'.$carrierAccountId;
        $cached=$this->repo->setting($cacheKey);
        $ttl=$provider==='apaczka'?86400:3600;
        if (!empty($cached['fetched_at']) && (int)$cached['fetched_at']>time()-$ttl && !empty($cached['options']) && is_array($cached['options'])) {
            return ['provider'=>$provider,'options'=>$cached['options']];
        }
        $public=json_decode((string)$carrier['public_config_json'],true,512,JSON_THROW_ON_ERROR);
        $secret=OrderSecretBox::decrypt($carrier['secret_config_json']);
        $options=[];
        if ($provider==='allegro_wza') {
            if ($order['platform']!=='allegro' || (int)($public['order_account_id']??0)!==(int)$order['account_id']) { throw new InvalidArgumentException('Wysyłam z Allegro wymaga zamówienia z tego samego konta Allegro.'); }
            $response=(new AllegroService(true))->shipmentServices($this->allegroAccount((int)$public['order_account_id']));
            foreach ((array)($response['services']??[]) as $service) {
                $id=is_array($service['id']??null)?$service['id']:[];
                $deliveryId=trim((string)($id['deliveryMethodId']??''));
                if ($deliveryId==='') { continue; }
                $credentials=trim((string)($id['credentialsId']??''));
                $options[]=['value'=>$deliveryId.'::'.$credentials,'name'=>(string)($service['name']??$deliveryId),'carrier'=>(string)($service['carrierId']??'Allegro')];
            }
        } elseif ($provider==='apaczka') {
            $response=$this->apaczka('service_structure',[],(string)$public['app_id'],(string)$secret['app_secret']);
            foreach ((array)($response['response']['services']??[]) as $serviceKey=>$service) {
                if (!is_array($service)) { continue; }
                $id=(int)($service['id']??$service['service_id']??$serviceKey); if ($id<1) { continue; }
                $options[]=['value'=>(string)$id,'name'=>(string)($service['name']??('Usługa '.$id)),'carrier'=>(string)($service['supplier']??'Apaczka')];
            }
        } else { throw new InvalidArgumentException('Nieobsługiwane konto nadawcze.'); }
        usort($options,static function (array $a,array $b): int { return strcasecmp($a['carrier'].' '.$a['name'],$b['carrier'].' '.$b['name']); });
        if (!$options) { throw new RuntimeException('Operator nie zwrócił dostępnych usług dostawy.'); }
        $this->repo->saveSetting($cacheKey,['fetched_at'=>time(),'options'=>$options]);
        return ['provider'=>$provider,'options'=>$options];
    }

    public function create(int $orderId,int $carrierAccountId,array $input,string $actor): int
    {
        $order=$this->repo->order($orderId); $db=$this->repo->db();
        $carrier=$db->fetch('SELECT * FROM om_carrier_accounts WHERE id=:id AND enabled=1',['id'=>$carrierAccountId]);
        if (!$carrier) { throw new InvalidArgumentException('Wybierz aktywne konto nadawcze.'); }
        $public=json_decode($carrier['public_config_json'],true,512,JSON_THROW_ON_ERROR);
        $secret=OrderSecretBox::decrypt($carrier['secret_config_json']);
        $weight=$this->number($input,'weight',0.01,1000); $length=$this->number($input,'length',1,400); $width=$this->number($input,'width',1,400); $height=$this->number($input,'height',1,400);
        $codAmountCents=!empty($input['cash_on_delivery'])?OrderNormalizer::money((string)($input['cod_amount']??'0')):0;
        $requestKey=(string)($input['request_key']??'');
        if (!preg_match('/^[a-f0-9]{32,80}$/D',$requestKey)) { throw new InvalidArgumentException('Odśwież formularz nadania.'); }
        $existing=$db->fetch('SELECT id FROM om_shipments WHERE command_id=:key',['key'=>$requestKey]);
        if ($existing) { return (int)$existing['id']; }
        $provider=$carrier['provider']; $response=[]; $external=''; $tracking='PENDING:'.$requestKey; $state='created';
        $deliveryMethod=trim((string)($order['details']['delivery']??''));
        $serviceCode=''; $serviceName=$deliveryMethod; $carrierName='';
        if ($provider==='allegro_wza') {
            if ($order['platform']!=='allegro' || (int)$public['order_account_id']!==(int)$order['account_id']) { throw new InvalidArgumentException('Wysyłam z Allegro wymaga zamówienia z tego samego konta Allegro.'); }
            $sourceId=(int)$db->fetchColumn('SELECT source_id FROM om_accounts WHERE id=:id',['id'=>$order['account_id']]);
            $allegro=new AllegroService(true); $source=null;
            foreach ($allegro->listAccounts() as $account) { if ((int)$account['id']===$sourceId) { $source=$account; break; } }
            if (!$source) { throw new RuntimeException('Nie znaleziono źródłowego konta Allegro.'); }
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
            $suggestedPackage=is_array($shipmentInput['packages'][0]??null)?$shipmentInput['packages'][0]:[];
            $package=$suggestedPackage;
            $package['type']=in_array((string)($package['type']??''),['DOX','PACKAGE','PALLET','OTHER'],true)?$package['type']:'PACKAGE';
            $package['length']=['value'=>$length,'unit'=>'CENTIMETER'];
            $package['width']=['value'=>$width,'unit'=>'CENTIMETER'];
            $package['height']=['value'=>$height,'unit'=>'CENTIMETER'];
            $package['weight']=['value'=>$weight,'unit'=>'KILOGRAMS'];
            $shipmentInput['packages']=[$package];
            $shipmentInput=$this->normalizeAllegroInput($shipmentInput);
            $serviceCode=trim((string)($shipmentInput['deliveryMethodId']??''));
            $serviceMeta=$this->serviceMeta($orderId,$carrierAccountId,$selectedService!==''?$selectedService:$serviceCode,$serviceName,'Allegro');
            $serviceName=$serviceMeta['service_name']; $carrierName=$serviceMeta['carrier_name'];
            $commandId=$this->uuidFromKey($requestKey);
            $response=$allegro->createShipmentCommand($source,$commandId,$shipmentInput);
            $external=(string)($response['commandId']??$commandId); $state=(string)($response['status']??'IN_PROGRESS');
        } elseif ($provider==='inpost_shipx') {
            $service=(string)($input['shipping_service']??$input['service']??'');
            if (!in_array($service,['inpost_locker_standard','inpost_courier_standard'],true)) { throw new InvalidArgumentException('Wybierz usługę InPost.'); }
            $serviceCode=$service; $serviceName=$service==='inpost_locker_standard'?'InPost Paczkomat 24/7':'InPost Kurier standard'; $carrierName='InPost';
            $payload=['receiver'=>$this->receiver($input,true),'parcels'=>[['dimensions'=>['length'=>$length*10,'width'=>$width*10,'height'=>$height*10,'unit'=>'mm'],'weight'=>['amount'=>$weight,'unit'=>'kg'],'is_non_standard'=>!empty($input['non_standard'])]],'service'=>$service,'reference'=>$order['external_id']];
            if ($service==='inpost_locker_standard') { $defaults=$this->repo->setting('shipping_defaults'); $point=trim((string)($order['shipping_address']['point']??$defaults['default_point']??'')); if ($point==='') throw new InvalidArgumentException('Ustaw domyślny punkt odbioru w zakładce Przesyłki i presety.'); $payload['custom_attributes']=['target_point'=>$point]; }
            $base=($public['environment']??'production')==='sandbox'?'https://sandbox-api-shipx-pl.easypack24.net/v1':'https://api-shipx-pl.easypack24.net/v1';
            $response=$this->jsonRequest($base.'/organizations/'.rawurlencode((string)$public['organization_id']).'/shipments',['Authorization: Bearer '.(string)$secret['token']],$payload);
            $external=(string)($response['id']??''); $tracking=(string)($response['tracking_number']??$tracking); $state=(string)($response['status']??'created');
        } elseif ($provider==='apaczka') {
            $serviceId=(int)($input['shipping_service']??$input['apaczka_service_id']??0); if ($serviceId<1) throw new InvalidArgumentException('Wybierz usługę Apaczka.');
            $serviceCode=(string)$serviceId; $serviceMeta=$this->serviceMeta($orderId,$carrierAccountId,$serviceCode,$serviceName,'Apaczka');
            $serviceName=$serviceMeta['service_name']; $carrierName=$serviceMeta['carrier_name'];
            $receiver=$this->receiver($input,false);
            $defaults=$this->repo->setting('shipping_defaults');
            $orderData=['service_id'=>$serviceId,'address'=>['sender'=>$this->sender(),'receiver'=>$receiver],'shipment_value'=>(int)$order['total_cents'],'shipment_currency'=>$order['currency'],'pickup'=>['type'=>(string)($defaults['pickup_type']??'SELF'),'date'=>'','hours_from'=>'','hours_to'=>''],'shipment'=>[['dimension1'=>$length,'dimension2'=>$width,'dimension3'=>$height,'weight'=>$weight,'is_nstd'=>0,'shipment_type_code'=>'PACZKA']],'content'=>$this->content($orderId,$input,$defaults)];
            $this->applyCod($orderData,$input,(string)$order['currency']);
            $response=$this->apaczka('order_send',['order'=>$orderData],(string)$public['app_id'],(string)$secret['app_secret']);
            $remote=$response['response']['order']??[]; $external=(string)($remote['id']??''); $tracking=(string)($remote['waybill_number']??$tracking); $state=(string)($remote['status']??'created');
        } else { throw new InvalidArgumentException('Nieobsługiwane konto nadawcze.'); }
        if ($external==='') { throw new RuntimeException('Operator nie zwrócił identyfikatora przesyłki.'); }
        $meta=['delivery_method'=>$deliveryMethod,'service_code'=>$serviceCode,'service_name'=>$serviceName,'carrier_name'=>$carrierName,'tracking_status'=>$provider==='allegro_wza'?'':$state,'tracking_updated_at'=>$provider==='allegro_wza'?'':gmdate('c')];
        $id=(int)$db->insert('om_shipments',['order_id'=>$orderId,'carrier_account_id'=>$carrierAccountId,'carrier'=>$carrier['name'],'tracking'=>$tracking,'weight'=>(string)$weight,'state'=>$state,'external_id'=>$external,'command_id'=>$requestKey,'payload_json'=>$this->shipmentPayload($provider,$response,$meta),'cod_amount_cents'=>$codAmountCents,'shipment_currency'=>(string)$order['currency'],'created_at'=>gmdate('Y-m-d H:i:s')]);
        $this->repo->event($orderId,'Utworzono przesyłkę przez '.$carrier['name'].'; status: '.$state,$actor);
        $this->repo->automationEvent($orderId,'shipment_created',['shipment_id'=>$id,'carrier_account_id'=>$carrierAccountId]);
        return $id;
    }

    public function valuation(int $orderId,int $carrierAccountId,array $input): array
    {
        $order=$this->repo->order($orderId); $db=$this->repo->db();
        $carrier=$db->fetch('SELECT * FROM om_carrier_accounts WHERE id=:id AND enabled=1',['id'=>$carrierAccountId]);
        if (!$carrier || $carrier['provider']!=='apaczka') { throw new InvalidArgumentException('Wycena jest dostępna dla konta Apaczka.'); }
        $serviceId=(int)($input['shipping_service']??0);
        $length=$this->number($input,'length',1,400); $width=$this->number($input,'width',1,400); $height=$this->number($input,'height',1,400); $weight=$this->number($input,'weight',0.01,1000);
        $public=json_decode((string)$carrier['public_config_json'],true,512,JSON_THROW_ON_ERROR); $secret=OrderSecretBox::decrypt($carrier['secret_config_json']); $defaults=$this->repo->setting('shipping_defaults');
        $orderData=['service_id'=>$serviceId,'address'=>['sender'=>$this->sender(),'receiver'=>$this->receiver($input,false)],'shipment_value'=>(int)$order['total_cents'],'shipment_currency'=>$order['currency'],'pickup'=>['type'=>(string)($defaults['pickup_type']??'SELF'),'date'=>'','hours_from'=>'','hours_to'=>''],'shipment'=>[['dimension1'=>$length,'dimension2'=>$width,'dimension3'=>$height,'weight'=>$weight,'is_nstd'=>0,'shipment_type_code'=>'PACZKA']],'content'=>$this->content($orderId,$input,$defaults)];
        $this->applyCod($orderData,$input,(string)$order['currency']);
        $response=$this->apaczka('order_valuation',['order'=>$orderData],(string)$public['app_id'],(string)$secret['app_secret']);
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

    private function applyCod(array &$orderData,array $input,string $currency): void
    {
        if (empty($input['cash_on_delivery'])) { return; }
        $amount=OrderNormalizer::money((string)($input['cod_amount']??'0'));
        if ($amount<1) { throw new InvalidArgumentException('Kwota pobrania musi być większa od zera.'); }
        $bankAccount=preg_replace('/\s+/','',trim((string)($this->repo->setting('shipping_defaults')['cod_bank_account']??'')))??'';
        if ($currency==='PLN' && strncasecmp($bankAccount,'PL',2)===0) { $bankAccount=substr($bankAccount,2); }
        if ($currency==='PLN' && !preg_match('/^\d{26}$/D',$bankAccount)) { throw new InvalidArgumentException('Uzupełnij poprawny numer konta pobrania w zakładce Przesyłki i presety.'); }
        $orderData['cod']=['amount'=>$amount,'currency'=>$currency,'bankaccount'=>$bankAccount];
    }

    public function refresh(int $shipmentId,string $actor): void
    {
        $db=$this->repo->db(); $shipment=$db->fetch('SELECT * FROM om_shipments WHERE id=:id',['id'=>$shipmentId]);
        if (!$shipment) throw new InvalidArgumentException('Nie znaleziono przesyłki.');
        $carrier=$db->fetch('SELECT * FROM om_carrier_accounts WHERE id=:id',['id'=>$shipment['carrier_account_id']]);
        if (!$carrier) throw new RuntimeException('Brak konfiguracji konta nadawczego.');
        $public=json_decode($carrier['public_config_json'],true,512,JSON_THROW_ON_ERROR); $secret=OrderSecretBox::decrypt($carrier['secret_config_json']);
        $state=$shipment['state']; $external=$shipment['external_id']; $tracking=$shipment['tracking']; $response=[]; $details=[];
        $stored=json_decode((string)($shipment['payload_json']??''),true); $meta=is_array($stored['meta']??null)?$stored['meta']:[];
        $previousTrackingStatus=(string)($meta['tracking_status']??'');
        if ($carrier['provider']==='allegro_wza') {
            $account=$this->allegroAccount((int)$public['order_account_id']); $allegro=new AllegroService(true);
            if ($state==='SUCCESS') { $details=$allegro->shipmentDetails($account,(string)$external); $response=$details; $tracking=(string)($details['packages'][0]['waybill']??$tracking); }
            else {
                $response=$allegro->shipmentCommandStatus($account,(string)$external); $state=(string)($response['status']??$state);
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
                    $serviceMeta=$this->serviceMeta((int)$shipment['order_id'],(int)$shipment['carrier_account_id'],$serviceCode,(string)($meta['service_name']??''),(string)($meta['carrier_name']??''));
                    $meta=array_replace($meta,$serviceMeta);
                }
                if ($carrierId!=='' && $tracking!=='' && strpos($tracking,'PENDING:')!==0) {
                    $latest=$this->latestAllegroTracking($allegro->shipmentTracking($account,$carrierId,$tracking),$tracking);
                    if ($latest) { $meta=array_replace($meta,$latest); }
                }
            }
        } elseif ($carrier['provider']==='inpost_shipx') {
            $base=($public['environment']??'production')==='sandbox'?'https://sandbox-api-shipx-pl.easypack24.net/v1':'https://api-shipx-pl.easypack24.net/v1';
            $response=$this->getJson($base.'/shipments/'.rawurlencode((string)$external),['Authorization: Bearer '.(string)$secret['token']]);
            $state=(string)($response['status']??$state); $tracking=(string)($response['tracking_number']??$tracking);
            $meta['tracking_status']=$state; $meta['tracking_updated_at']=(string)($response['updated_at']??gmdate('c'));
        } else {
            $response=$this->apaczka('order/'.rawurlencode((string)$external),[],(string)$public['app_id'],(string)$secret['app_secret']);
            $remote=$response['response']['order']??[]; $state=(string)($remote['status']??$state); $tracking=(string)($remote['waybill_number']??$tracking);
            $meta['tracking_status']=$state; $meta['tracking_updated_at']=gmdate('c');
        }
        $db->update('om_shipments',['state'=>$state,'external_id'=>$external,'tracking'=>$tracking,'payload_json'=>$this->shipmentPayload((string)$carrier['provider'],$response,$meta)],'id=:id',['id'=>$shipmentId]);
        $this->repo->event((int)$shipment['order_id'],'Odświeżono przesyłkę '.$carrier['name'].'; status: '.$state,$actor);
        if ((string)$state!==(string)$shipment['state'] || (string)($meta['tracking_status']??'')!==(string)$previousTrackingStatus) {
            $this->repo->automationEvent((int)$shipment['order_id'],'shipment_status',['shipment_id'=>$shipmentId,'shipment_state'=>(string)$state]);
        }
    }

    public function cancel(int $shipmentId,string $actor): void
    {
        $db=$this->repo->db(); $shipment=$db->fetch('SELECT * FROM om_shipments WHERE id=:id',['id'=>$shipmentId]);
        if (!$shipment) throw new InvalidArgumentException('Nie znaleziono przesyłki.');
        if (in_array(strtoupper((string)$shipment['state']),['CANCELLED','CANCELED','ANULOWANO'],true)) return;
        $carrier=$db->fetch('SELECT * FROM om_carrier_accounts WHERE id=:id',['id'=>$shipment['carrier_account_id']]);
        if (!$carrier || $carrier['provider']!=='apaczka') throw new InvalidArgumentException('Anulowanie z panelu jest obecnie dostępne dla przesyłek Apaczka.');
        $public=json_decode((string)$carrier['public_config_json'],true,512,JSON_THROW_ON_ERROR); $secret=OrderSecretBox::decrypt($carrier['secret_config_json']);
        $response=$this->apaczka('cancel_order/'.rawurlencode((string)$shipment['external_id']),[],(string)$public['app_id'],(string)$secret['app_secret']);
        $stored=json_decode((string)($shipment['payload_json']??''),true); $meta=is_array($stored['meta']??null)?$stored['meta']:[];
        $meta['tracking_status']='CANCELLED'; $meta['tracking_updated_at']=gmdate('c');
        $db->update('om_shipments',['state'=>'CANCELLED','payload_json'=>$this->shipmentPayload('apaczka',$response,$meta)],'id=:id',['id'=>$shipmentId]);
        $this->repo->event((int)$shipment['order_id'],'Anulowano przesyłkę Apaczka '.$shipment['tracking'],$actor);
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
        $db=$this->repo->db(); $shipment=$db->fetch('SELECT * FROM om_shipments WHERE id=:id',['id'=>$shipmentId]);
        if (!$shipment) throw new InvalidArgumentException('Nie znaleziono przesyłki.');
        $carrier=$db->fetch('SELECT * FROM om_carrier_accounts WHERE id=:id',['id'=>$shipment['carrier_account_id']]);
        if (!$carrier) throw new RuntimeException('Brak konfiguracji konta nadawczego.');
        $public=json_decode($carrier['public_config_json'],true,512,JSON_THROW_ON_ERROR);$secret=OrderSecretBox::decrypt($carrier['secret_config_json']);
        if ($carrier['provider']==='allegro_wza') {
            if ($shipment['state']!=='SUCCESS') throw new RuntimeException('Przesyłka Allegro nie jest jeszcze gotowa. Najpierw odśwież status.');
            $bytes=(new AllegroService(true))->shipmentLabel($this->allegroAccount((int)$public['order_account_id']),(string)$shipment['external_id'],$pageSize);
        } elseif ($carrier['provider']==='inpost_shipx') {
            $base=($public['environment']??'production')==='sandbox'?'https://sandbox-api-shipx-pl.easypack24.net/v1':'https://api-shipx-pl.easypack24.net/v1';
            $bytes=$this->getBinary($base.'/shipments/'.rawurlencode((string)$shipment['external_id']).'/label?format=pdf&type=normal',['Authorization: Bearer '.(string)$secret['token']]);
        } else {
            $response=$this->apaczka('waybill/'.rawurlencode((string)$shipment['external_id']),[],(string)$public['app_id'],(string)$secret['app_secret']);
            $bytes=base64_decode((string)($response['response']['waybill']??''),true);
            if ($bytes===false||$bytes==='') throw new RuntimeException('Apaczka nie zwróciła etykiety.');
        }
        return ['bytes'=>$bytes,'mime'=>'application/pdf','name'=>'etykieta-'.preg_replace('/[^a-zA-Z0-9._-]/','-',(string)$shipment['tracking']).'.pdf'];
    }

    private function receiver(array $input,bool $shipx): array
    {
        $name=$this->required($input,'receiver_name',150); $parts=preg_split('/\s+/',trim($name),2);
        if ($shipx) return ['first_name'=>$parts[0]??$name,'last_name'=>$parts[1]??'','email'=>$this->required($input,'receiver_email',200),'phone'=>$this->required($input,'receiver_phone',30),'address'=>['street'=>$this->required($input,'receiver_street',150),'building_number'=>$this->required($input,'receiver_building',30),'city'=>$this->required($input,'receiver_city',100),'post_code'=>$this->required($input,'receiver_postal_code',20),'country_code'=>$this->country($input['receiver_country']??'PL')]];
        return ['name'=>$name,'contact_person'=>$name,'email'=>$this->email($input,'receiver_email'),'phone'=>$this->phone($input,'receiver_phone'),'line1'=>$this->required($input,'receiver_street',150),'line2'=>$this->required($input,'receiver_building',30),'postal_code'=>$this->required($input,'receiver_postal_code',20),'city'=>$this->required($input,'receiver_city',100),'country_code'=>$this->country($input['receiver_country']??'PL'),'is_residential'=>0];
    }
    private function sender(): array { $sender=(array)($this->repo->setting('shipping_defaults')['sender']??[]); try { return ['name'=>$this->required($sender,'name',150),'contact_person'=>$this->required($sender,'name',150),'email'=>$this->email($sender,'email'),'phone'=>$this->phone($sender,'phone'),'line1'=>$this->required($sender,'street',150),'line2'=>$this->required($sender,'building',30),'postal_code'=>$this->required($sender,'postal_code',20),'city'=>$this->required($sender,'city',100),'country_code'=>'PL','is_residential'=>0]; } catch (InvalidArgumentException $e) { throw new InvalidArgumentException('Uzupełnij poprawne dane nadawcy Apaczki: nazwę, e-mail, telefon, ulicę, numer, kod pocztowy i miasto — w zakładce Przesyłki i presety.'); } }
    private function allegroAccount(int $localAccountId): array { $sourceId=(int)$this->repo->db()->fetchColumn('SELECT source_id FROM om_accounts WHERE id=:id AND platform=:p',['id'=>$localAccountId,'p'=>'allegro']);foreach((new AllegroService(true))->listAccounts() as $a){if((int)$a['id']===$sourceId)return $a;}throw new RuntimeException('Nie znaleziono konta Allegro.'); }
    private function apaczka(string $route,array $data,string $appId,string $secret): array { $route=trim($route,'/').'/';$json=json_encode((object)$data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$expires=time()+900;$signature=hash_hmac('sha256',$appId.':'.$route.':'.$json.':'.$expires,$secret);return $this->formRequest('https://www.apaczka.pl/api/v2/'.$route, ['app_id'=>$appId,'request'=>$json,'expires'=>$expires,'signature'=>$signature]); }
    private function jsonRequest(string $url,array $headers,array $payload): array { return $this->request($url,array_merge($headers,['Content-Type: application/json']),json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)); }
    private function formRequest(string $url,array $payload): array { return $this->request($url,['Content-Type: application/x-www-form-urlencoded'],http_build_query($payload)); }
    private function request(string $url,array $headers,string $body): array { $ch=curl_init($url);if($ch===false)throw new RuntimeException('Nie można uruchomić połączenia z operatorem.');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_POSTFIELDS=>$body,CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>10]);$raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$decoded=is_string($raw)?json_decode($raw,true):null;if($status<200||$status>=300||!is_array($decoded)||isset($decoded['status'])&&(int)$decoded['status']!==200){$message=is_array($decoded)?trim((string)($decoded['message']??'')):'';$message=preg_replace('/[\x00-\x1F\x7F]+/u',' ',$message)?:'';$message=mb_substr($message,0,240,'UTF-8');if($message!=='')throw new InvalidArgumentException('Operator przesyłek odrzucił dane: '.$message);throw new RuntimeException('Operator przesyłek odrzucił zlecenie (HTTP '.$status.').');}return $decoded; }
    private function getJson(string $url,array $headers): array { $raw=$this->getBinary($url,array_merge($headers,['Accept: application/json']));$json=json_decode($raw,true);if(!is_array($json))throw new RuntimeException('Operator zwrócił niepoprawny status przesyłki.');return $json; }
    private function getBinary(string $url,array $headers): string { $ch=curl_init($url);if($ch===false)throw new RuntimeException('Nie można uruchomić pobierania od operatora.');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>60,CURLOPT_CONNECTTIMEOUT=>10]);$raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if(!is_string($raw)||$status<200||$status>=300)throw new RuntimeException('Operator nie zwrócił pliku lub statusu (HTTP '.$status.').');return $raw; }
    public static function presentation(array $shipment,array $order): array
    {
        $stored=json_decode((string)($shipment['payload_json']??''),true); $meta=is_array($stored['meta']??null)?$stored['meta']:[];
        $provider=(string)($shipment['carrier_provider']??$stored['provider']??'');
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
        return ['delivery_method'=>$delivery!==''?$delivery:'Brak danych','carrier'=>$carrier,'service'=>$service,'service_code'=>(string)($meta['service_code']??''),'remote_status'=>$remote,'status_label'=>$label,'status_tone'=>$tone,'status_description'=>self::statusDescription((string)($meta['status_description']??''),$remote),'status_updated_at'=>$updatedAt,'technical_status'=>(string)($shipment['state']??''),'source_publication'=>$publication];
    }
    private function shipmentPayload(string $provider,array $response,array $meta): string { return OrderRepository::json(['provider'=>$provider,'response'=>$this->safeResponse($response),'meta'=>$meta]); }
    private function serviceMeta(int $orderId,int $carrierAccountId,string $selected,string $fallbackName,string $fallbackCarrier): array
    {
        try { $options=$this->options($orderId,$carrierAccountId)['options']??[]; }
        catch (\Throwable $error) { $options=[]; }
        $selectedCode=explode('::',$selected,2)[0];
        foreach ($options as $option) {
            $value=(string)($option['value']??'');
            if ($value===$selected || explode('::',$value,2)[0]===$selectedCode) { return ['service_name'=>(string)($option['name']??$fallbackName),'carrier_name'=>(string)($option['carrier']??$fallbackCarrier)]; }
        }
        return ['service_name'=>$fallbackName,'carrier_name'=>$fallbackCarrier];
    }
    private function latestAllegroTracking(array $response,string $waybill): array
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
    private static function prettyCarrier(string $carrier): string
    {
        $parts=array_map('trim',explode('→',$carrier));
        foreach ($parts as &$part) { foreach (['INPOST'=>'InPost','DPD'=>'DPD','DHL'=>'DHL','UPS'=>'UPS','GLS'=>'GLS','FEDEX'=>'FedEx','ORLEN'=>'ORLEN','POCZTA POLSKA'=>'Poczta Polska','PACKETA'=>'Packeta','ALLEGRO'=>'Allegro'] as $key=>$label) { if (strtoupper($part)===$key) { $part=$label; break; } } } unset($part);
        return implode(' → ',$parts);
    }
    private static function carrierFromDelivery(string $delivery,string $provider,string $fallback): string
    {
        foreach (['inpost'=>'InPost','dpd'=>'DPD','dhl'=>'DHL','ups'=>'UPS','gls'=>'GLS','fedex'=>'FedEx','orlen'=>'ORLEN Paczka','poczta'=>'Poczta Polska','pocztex'=>'Pocztex','packeta'=>'Packeta'] as $needle=>$label) { if (stripos($delivery,$needle)!==false) { return $label; } }
        if ($provider==='inpost_shipx') { return 'InPost'; } if ($provider==='apaczka') { return 'Apaczka'; }
        return $fallback!==''?$fallback:'Nie rozpoznano';
    }
    private static function statusLabel(string $status,string $provider): array
    {
        $key=strtoupper(trim($status));
        $map=['PENDING'=>['Przygotowana do nadania','pending'],'IN_PROGRESS'=>['Tworzenie przesyłki','pending'],'CREATED'=>['Utworzona','pending'],'CONFIRMED'=>['Potwierdzona','pending'],'IN_TRANSIT'=>['W drodze','transit'],'DISPATCHED_BY_SENDER'=>['Nadana przez sprzedawcę','transit'],'COLLECTED_FROM_SENDER'=>['Odebrana od nadawcy','transit'],'TAKEN_BY_COURIER'=>['Odebrana przez kuriera','transit'],'ADOPTED_AT_SOURCE_BRANCH'=>['Przyjęta w oddziale','transit'],'SENT_FROM_SOURCE_BRANCH'=>['Wysłana z oddziału','transit'],'RELEASED_FOR_DELIVERY'=>['W doręczeniu','delivery'],'OUT_FOR_DELIVERY'=>['W doręczeniu','delivery'],'AVAILABLE_FOR_PICKUP'=>['Gotowa do odbioru','pickup'],'READY_TO_PICKUP'=>['Gotowa do odbioru','pickup'],'NOTICE_LEFT'=>['Awizowana','issue'],'ISSUE'=>['Problem z przesyłką','issue'],'DELIVERED'=>['Doręczona','delivered'],'RETURNED'=>['Zwrócona','returned'],'RETURNED_TO_SENDER'=>['Zwrócona do nadawcy','returned'],'CANCELLED'=>['Anulowana','cancelled'],'CANCELED'=>['Anulowana','cancelled'],'ANULOWANO'=>['Anulowana','cancelled'],'ERROR'=>['Błąd przesyłki','issue'],'SUCCESS'=>['Przesyłka utworzona','created']];
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
    private function safeResponse(array $response): array { return array_intersect_key($response,array_flip(['id','commandId','status','tracking_number'])); }
    private function number(array $input,string $key,float $min,float $max): float { $value=filter_var($input[$key]??null,FILTER_VALIDATE_FLOAT);if($value===false||$value<$min||$value>$max)throw new InvalidArgumentException('Nieprawidłowa wartość pola '.$key.'.');return (float)$value; }
    private function required(array $input,string $key,int $max): string { $v=trim((string)($input[$key]??''));if($v===''||mb_strlen($v)>$max)throw new InvalidArgumentException('Uzupełnij pole '.$key.'.');return $v; }
    private function email(array $input,string $key): string { $value=$this->required($input,$key,200);if(filter_var($value,FILTER_VALIDATE_EMAIL)===false)throw new InvalidArgumentException('Nieprawidłowy adres e-mail.');return $value; }
    private function phone(array $input,string $key): string { $value=$this->required($input,$key,30);$value=preg_replace('/[\s().-]+/','',$value)??'';if(!preg_match('/^\+?\d{9,20}$/D',$value))throw new InvalidArgumentException('Nieprawidłowy numer telefonu.');return $value; }
    private function content(int $orderId,array $input,array $defaults): string { $value=trim((string)($input['shipment_content']??$defaults['content']??'Towar'));$value=preg_replace('/\s+[—-]\s+zamówienie ID\s+\d+$/iu','',$value)??$value;$value=mb_substr(trim($value),0,180,'UTF-8');if($value==='')$value='Towar';return $value.' — zamówienie ID '.$orderId; }
    private function country($value): string { $value=strtoupper(trim((string)$value));if(!preg_match('/^[A-Z]{2}$/D',$value))throw new InvalidArgumentException('Nieprawidłowy kod kraju.');return $value; }
    private function normalizeAllegroInput(array $value): array { $value=$this->withoutNulls($value);if(($value['additionalProperties']??null)===[])$value['additionalProperties']=new \stdClass();return $value; }
    private function withoutNulls(array $value): array { foreach($value as $key=>$item){if($item===null){unset($value[$key]);continue;}if(is_array($item))$value[$key]=$this->withoutNulls($item);}return $value; }
    private function uuidFromKey(string $key): string { $hex=substr(hash('sha256',$key),0,32);return substr($hex,0,8).'-'.substr($hex,8,4).'-4'.substr($hex,13,3).'-a'.substr($hex,17,3).'-'.substr($hex,20,12); }
}
