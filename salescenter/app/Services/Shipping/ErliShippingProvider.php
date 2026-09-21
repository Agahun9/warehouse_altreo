<?php

declare(strict_types=1);
namespace App\Services\Shipping;

use App\Services\Integrations\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Wysyłam z Erli (Metody Dostaw ERLI) – ERLI Shop API /shipping/parcels.
 * Odbiorca i punkt odbioru są brane przez ERLI z zamówienia, numer śledzenia trafia do zamówienia automatycznie.
 */
final class ErliShippingProvider extends ShippingProvider
{
    private const BASE='https://erli.pl/svc/shop-api';
    /** Metody, które przyjmuje POST /shipping/parcels (CreateParcels.shipping.typeId). */
    private const PARCEL_TYPES=['erliPaczkomat','erliOrlenPaczkaS','erliOrlenPaczkaM','erliOrlenPaczkaL','erliDHLPunktyOdbioru5kg','erliDHLPunktyOdbioru10kg','erliDHLPunktyOdbioru25kg','erliKurier24InPost10kg','erliKurier24InPost15kg','erliKurier24InPost20kg','erliKurier24InPost30kg','erliKurier24InPost40kg','erliKurier24InPost50kg','erliDHL5kg','erliDHL10kg','erliDHL20kg','erliDHL32kg','erliPocztexPunktyS','erliPocztexPunktyM','erliPocztexPunktyL','erliPocztexPunktyXL','erliPocztexKurierS','erliPocztexKurierM','erliPocztexKurierL','erliPocztexKurierXL','erliPocztexKurier2XL','erliPocztexD2DKurierS','erliPocztexD2DKurierM','erliPocztexD2DKurierL','erliPocztexD2DKurierXL','erliPocztexD2DKurier2XL','erliPocztexD2DKurier3XL','erliDPDPickup5kg','erliDPDPickup10kg','erliDPDPickup15kg','erliDPDPickup20kg','erliDPDPickup25kg','erliDPDPickup31,5kg','erliDPDKurier5kg','erliDPDKurier10kg','erliDPDKurier15kg','erliDPDKurier20kg','erliDPDKurier25kg','erliDPDKurier31,5kg','erliDPDKurier40kg','erliDPDKurier50kg','erliDPDKurier100kg','erliDPDKurier200kg','erliDPDKurier300kg','erliDPDKurier500kg','erliDPDKurier700kg'];
    private const OPERATORS=['INPOST'=>'InPost','RUCH'=>'ORLEN Paczka','DHL'=>'DHL','POCZTA'=>'Pocztex','DPD'=>'DPD'];

    public static function definition(): array
    {
        return [
            'key'=>'erli_shipping','label'=>'Wysyłam z Erli','description'=>'Metody Dostaw ERLI – nadawanie na umowach ERLI (InPost, ORLEN Paczka, DHL, DPD, Pocztex).',
            'badge'=>['text'=>'e','tone'=>'erli'],'color'=>'#6d28d9','ink'=>'#fff','group'=>'Umowy marketplace',
            'steps'=>['Podłącz sklep ERLI w zakładce Konta (klucz API z panelu ERLI).','W panelu ERLI → Ustawienia sklepu → Metody integracji włącz Metody Dostaw ERLI i dodaj punkt nadania. Dla kuriera DPD wybierz sposób ubezpieczenia.','Wybierz tutaj sklep i zapisz – sprawdzimy klucz i pobierzemy punkty nadania.','W zamówieniu ERLI utwórz przesyłkę – metodę dobierzemy z zamówienia.'],'source_platform'=>'erli','source_label'=>'ERLI',
            'capabilities'=>['valuation'=>false,'cancel'=>true,'label'=>true,'tracking'=>true,'cod'=>'order','source_tracking'=>'auto','pickup_protocol'=>true],
            'info'=>[
                'Działa dla zamówień z wybranego sklepu ERLI; używa klucza API zapisanego przy podłączeniu sklepu w zakładce Konta.',
                'Metody Dostaw ERLI i punkty nadania włączasz w panelu ERLI → Ustawienia sklepu → Metody integracji.',
                'Adres odbiorcy i punkt odbioru ERLI bierze z zamówienia – kolejne paczki muszą mieć ten sam adres.',
                'Numer śledzenia ERLI dopisuje do zamówienia sam, nie trzeba go wysyłać do źródła.',
                'Etykieta jest gotowa chwilę po nadaniu – jeśli jej nie ma, odśwież status. Kurier DPD wymaga wcześniej wybrania ubezpieczenia w panelu ERLI.',
                'Pobranie wynika z zamówienia (metoda dostawy za pobraniem).',
            ],
            'docs'=>'https://erli.pl/svc/shop-api/doc/reference',
            'fields'=>[
                ['name'=>'name','label'=>'Nazwa połączenia','type'=>'text','default'=>'Wysyłam z Erli','required'=>true,'max'=>150],
                ['name'=>'erli_account_id','label'=>'Sklep ERLI','type'=>'source_account','required'=>true],
                ['name'=>'posting_point_id','label'=>'ID punktu nadania (opcjonalnie)','type'=>'number','required'=>false,'max'=>20,'help'=>'Puste = domyślny punkt nadania przypisany w ERLI do metody dostawy.'],
            ],
        ];
    }

    public function servicesTtl(): int { return 86400; }

    public function automaticService(array $carrier,array $order): string { return 'Automatycznie wg metody dostawy z zamówienia ERLI'; }

    public function services(array $carrier,array $order): array
    {
        $options=[];
        foreach ($this->api($carrier,'GET','/dictionaries/shippingMethods') as $method) {
            $id=(string)($method['id']??'');
            if (!in_array($id,self::PARCEL_TYPES,true)) { continue; }
            $name=trim((string)($method['name']??'')) ?: $id;
            if (!empty($method['cod'])) { $name.=' · pobranie'; }
            $options[]=['value'=>$id,'name'=>$name,'carrier'=>self::OPERATORS[(string)($method['operator']??'')]??'ERLI'];
        }
        return $options;
    }

    public function create(array $carrier,array $order,array $input): array
    {
        if (!$this->supportsOrder($order,$carrier['public'])) { throw new InvalidArgumentException('Wysyłam z Erli wymaga zamówienia z tego samego sklepu ERLI.'); }
        $package=ShipmentInput::package($input);
        $typeId=trim((string)($input['shipping_service']??''));
        if ($typeId==='') { $typeId=$this->typeFromOrder($carrier,$order,$package['weight']); }
        if (!in_array($typeId,self::PARCEL_TYPES,true)) { throw new InvalidArgumentException('Wybierz metodę Wysyłam z Erli.'); }
        $shipping=['typeId'=>$typeId];
        $postingPoint=(int)($carrier['public']['posting_point_id']??0);
        if ($postingPoint>0) { $shipping['postingPointId']=$postingPoint; }
        $info=mb_substr(ShipmentInput::content((int)$order['id'],$input,$this->defaults()),0,100,'UTF-8');
        if (mb_strlen($info,'UTF-8')>=3) { $shipping['additionalInformation']=$info; }
        if (!empty($input['non_standard'])) { $shipping['nonStandard']=true; }
        $parcel=['orderId'=>(string)$order['external_id'],'dimensions'=>['length'=>(int)round($package['length']*10),'width'=>(int)round($package['width']*10),'height'=>(int)round($package['height']*10),'weight'=>max(10,(int)round($package['weight']*1000))],'shipping'=>$shipping];
        $response=$this->api($carrier,'POST','/shipping/parcels/',[$parcel]);
        $created=is_array($response[0]??null)?$response[0]:[];
        $this->assertNoErrors($created);
        $meta=$this->parcelMeta($created);
        $serviceMeta=$this->serviceMeta($carrier,$order,$typeId,$typeId,'ERLI');
        return ['external_id'=>(string)($created['id']??''),'tracking'=>(string)($created['trackingNumber']??''),'state'=>(string)($created['status']??'preparing'),'service_code'=>$typeId,'service_name'=>$serviceMeta['service_name'],'carrier_name'=>$serviceMeta['carrier_name'],'response'=>$created,'meta'=>$meta];
    }

    public function refresh(array $carrier,array $shipment,array $meta): array
    {
        $parcel=$this->api($carrier,'GET','/shipping/parcels/'.rawurlencode((string)$shipment['external_id']));
        $state=(string)($parcel['status']??$shipment['state']);
        $meta=array_replace($meta,$this->parcelMeta($parcel));
        if (!empty($parcel['errors'][0]['errorMessage'])) { $meta['status_description']=(string)$parcel['errors'][0]['errorMessage']; }
        $tracking=trim((string)($parcel['trackingNumber']??'')) ?: (string)$shipment['tracking'];
        return ['state'=>$state,'external_id'=>(string)$shipment['external_id'],'tracking'=>$tracking,'response'=>$parcel,'meta'=>$meta];
    }

    public function cancel(array $carrier,array $shipment): array
    {
        return $this->api($carrier,'DELETE','/shipping/parcels/'.rawurlencode((string)$shipment['external_id']));
    }

    public function label(array $carrier,array $shipment,string $pageSize): string
    {
        $parcel=$this->api($carrier,'GET','/shipping/parcels/'.rawurlencode((string)$shipment['external_id']));
        $this->assertNoErrors($parcel);
        $url=(string)($parcel['shipping']['waybills'][0]??'');
        if ($url==='') { throw new RuntimeException('ERLI nie przygotowało jeszcze etykiety. Odśwież status za chwilę.'); }
        return $this->download($carrier,$url);
    }

    /** Protokół odbioru przez kuriera dla paczki. */
    public function pickupProtocol(array $carrier,array $shipment): string
    {
        $response=$this->api($carrier,'GET','/shipping/pickupProtocols?parcelIds='.rawurlencode((string)$shipment['external_id']));
        $url=(string)($response['url']??'');
        if ($url==='') { throw new RuntimeException('ERLI nie zwróciło protokołu odbioru.'); }
        return $this->download($carrier,$url);
    }

    /** Przy zapisie konta pobiera punkty nadania – potwierdza klucz API i pokazuje je na karcie. */
    protected function verify(array $public,array $secret): array
    {
        $points=[];
        foreach ($this->api(['public'=>$public],'GET','/shipping/postingPoints') as $point) {
            if (!is_array($point)) { continue; }
            $points[]=['id'=>(int)($point['id']??0),'name'=>mb_substr((string)($point['name']??''),0,120,'UTF-8'),'type'=>(string)($point['type']??''),'default'=>!empty($point['isDefault']),'address'=>trim((string)($point['street']??'').' '.(string)($point['buildingNumber']??'').', '.(string)($point['zip']??'').' '.(string)($point['city']??''),' ,')];
            if (count($points)>=20) { break; }
        }
        $selected=(int)($public['posting_point_id']??0);
        if ($selected>0 && !in_array($selected,array_column($points,'id'),true)) { throw new InvalidArgumentException('ERLI nie zna punktu nadania o ID '.$selected.'.'); }
        return ['posting_points'=>$points,'verified_at'=>gmdate('c')];
    }

    private function typeFromOrder(array $carrier,array $order,float $weightKg): string
    {
        $remote=$this->api($carrier,'GET','/orders/'.rawurlencode((string)$order['external_id']));
        $typeId=trim((string)($remote['delivery']['typeId']??''));
        if (in_array($typeId,self::PARCEL_TYPES,true)) { return $typeId; }
        // Metoda z zamówienia bywa grupą (np. erliKurier24InPost) – dobierz najmniejszy wariant wagowy, który mieści paczkę.
        foreach (self::PARCEL_TYPES as $candidate) {
            if ($typeId==='' || strpos($candidate,$typeId)!==0) { continue; }
            if (preg_match('/([\d,]+)kg$/',$candidate,$m) && (float)str_replace(',','.',$m[1])<$weightKg) { continue; }
            return $candidate;
        }
        throw new InvalidArgumentException('Zamówienie ERLI nie ma metody Wysyłam z Erli ('.($typeId!==''?$typeId:'brak').'). Wybierz metodę ręcznie.');
    }

    private function parcelMeta(array $parcel): array
    {
        $meta=[];
        $status=(string)($parcel['status']??'');
        if ($status!=='') { $meta['tracking_status']=$status; }
        $history=(array)($parcel['statusHistory']??[]);
        $last=end($history);
        $meta['tracking_updated_at']=is_array($last)&&!empty($last['changed'])?(string)$last['changed']:(string)($parcel['updatedAt']??gmdate('c'));
        $documents=[];
        if (!empty($parcel['shipping']['waybills'][0])) { $documents['waybill']=true; }
        if (!empty($parcel['shipping']['pickupProtocol'])) { $documents['pickup_protocol']=true; }
        if (!empty($parcel['shipping']['waybillExpiration'])) { $meta['label_expires_at']=(string)$parcel['shipping']['waybillExpiration']; }
        if (!empty($parcel['erliPro'])) { $meta['erli_pro']=true; }
        $meta['documents']=$documents;
        return $meta;
    }

    private function assertNoErrors(array $parcel): void
    {
        $error=$parcel['errors'][0]??null;
        if (is_array($error)) { throw new InvalidArgumentException('ERLI odrzuciło paczkę: '.mb_substr(trim((string)($error['errorMessage']??'')),0,240,'UTF-8').(isset($error['errorCode'])?' ('.$error['errorCode'].')':'')); }
        if (empty($parcel['id'])) { throw new RuntimeException('ERLI nie zwróciło identyfikatora paczki.'); }
    }

    private function download(array $carrier,string $url): string
    {
        $host=strtolower((string)parse_url($url,PHP_URL_HOST));
        if (parse_url($url,PHP_URL_SCHEME)!=='https' || $host==='') { throw new RuntimeException('ERLI zwróciło nieprawidłowy adres dokumentu.'); }
        // Klucz API wysyłamy tylko do domeny ERLI; linki do magazynów plików są podpisane.
        $headers=($host==='erli.pl'||substr($host,-8)==='.erli.pl')?['Authorization: Bearer '.$this->apiKey($carrier)]:[];
        return ShipmentInput::getBinary($url,$headers,true);
    }

    private function apiKey(array $carrier): string
    {
        $key=trim((string)($this->sourceAccount('erli',(int)($carrier['public']['order_account_id']??0))['api_key']??''));
        if ($key==='') { throw new RuntimeException('Sklep ERLI nie ma zapisanego klucza API.'); }
        return $key;
    }

    private function api(array $carrier,string $method,string $path,?array $body=null): array
    {
        $headers=['Accept: application/json','Authorization: Bearer '.$this->apiKey($carrier)];
        if ($body!==null) { $headers[]='Content-Type: application/json'; }
        return Http::json('ERLI',$method,self::BASE.$path,$headers,$body);
    }
}
