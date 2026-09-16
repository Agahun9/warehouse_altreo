<?php

declare(strict_types=1);
namespace App\Services;

use App\Models\OrderRepository;
use InvalidArgumentException;
use RuntimeException;

final class OrderMarketplaceShipmentService
{
    private $repo;
    private $services;

    public function __construct(OrderRepository $repo,array $services=[])
    {
        $this->repo=$repo;
        $this->services=$services;
    }

    public static function carrierOptions(): array
    {
        return ['inpost'=>'InPost','dpd'=>'DPD','gls'=>'GLS','dhl'=>'DHL','ups'=>'UPS','fedex'=>'FedEx','orlen'=>'ORLEN Paczka','pocztex'=>'Pocztex / Poczta Polska','other'=>'Inny przewoźnik'];
    }

    public static function miraklCarrierPayload(array $carriers,string $carrierCode,string $carrierName): array
    {
        $key=static function (string $value): string { return strtolower((string)preg_replace('/[^a-z0-9]+/i','',trim($value))); };
        $needles=array_filter([$key($carrierCode),$key($carrierName)]);
        $best=null; $bestScore=0;
        foreach ($carriers as $carrier) {
            if (!is_array($carrier) || trim((string)($carrier['code']??''))==='') { continue; }
            $values=[$key((string)($carrier['code']??'')),$key((string)($carrier['label']??'')),$key((string)($carrier['standard_code']??''))];
            $score=0;
            foreach ($needles as $needle) {
                foreach ($values as $value) {
                    if ($value==='' || $needle==='') { continue; }
                    if ($value===$needle) { $score=max($score,100); }
                    elseif (strlen($needle)>=3 && (strpos($value,$needle)!==false || strpos($needle,$value)!==false)) { $score=max($score,50); }
                }
            }
            if ($score>$bestScore) { $best=$carrier; $bestScore=$score; }
        }
        if ($best) { return ['carrier_code'=>(string)$best['code'],'carrier_name'=>(string)($best['label']??$carrierName)]; }
        return ['carrier_name'=>mb_substr(trim($carrierName),0,100,'UTF-8')];
    }

    public function publishShipment(int $shipmentId,string $carrierCode,string $otherCarrier,string $actor): string
    {
        $shipment=$this->repo->db()->fetch('SELECT * FROM om_shipments WHERE id=:id',['id'=>$shipmentId]);
        if (!$shipment) { throw new InvalidArgumentException('Nie znaleziono przesyłki.'); }
        $tracking=trim((string)$shipment['tracking']);
        if ($tracking==='' || strpos($tracking,'PENDING:')===0) { throw new InvalidArgumentException('Operator nie zwrócił jeszcze numeru przesyłki. Najpierw odśwież status.'); }
        $options=self::carrierOptions();
        if (!isset($options[$carrierCode])) { throw new InvalidArgumentException('Wybierz przewoźnika przesyłki.'); }
        $carrierName=$carrierCode==='other'?mb_substr(trim($otherCarrier),0,100,'UTF-8'):$options[$carrierCode];
        if ($carrierName==='') { throw new InvalidArgumentException('Podaj nazwę innego przewoźnika.'); }

        $order=$this->repo->order((int)$shipment['order_id']);
        $target=$this->target($order);
        if ((string)$order['platform']==='erli' && $carrierCode==='other') { throw new InvalidArgumentException('ERLI wymaga przewoźnika ze swojej listy. Wybierz konkretną firmę.'); }
        $signature=hash('sha256',(string)$order['platform'].'|'.(string)$order['external_id'].'|'.$tracking.'|'.$carrierCode.'|'.$carrierName);
        $stored=$this->payload($shipment);
        $meta=is_array($stored['meta']??null)?$stored['meta']:[];
        $publication=is_array($meta['source_publication']??null)?$meta['source_publication']:[];
        if (($publication['signature']??'')===$signature && ($publication['state']??'')==='received') { return 'Źródło zamówienia już potwierdziło odbiór tego numeru przesyłki.'; }

        $meta['source_publication']=['signature'=>$signature,'state'=>'attempting','carrier_code'=>$carrierCode,'carrier_name'=>$carrierName,'attempted_at'=>gmdate('c'),'sent_at'=>'','received_at'=>'','message'=>'Trwa przekazywanie numeru do źródła.','error'=>''];
        $stored['meta']=$meta;
        $this->savePayload($shipment,$stored);
        try {
            $this->send($target,$order,$tracking,$carrierCode,$carrierName);
            $message='Numer przesyłki przekazany do '.$this->platformLabel((string)$order['platform']).'; źródło potwierdziło odbiór.';
            $meta['source_publication']['state']='received';
            $meta['source_publication']['sent_at']=gmdate('c');
            $meta['source_publication']['received_at']=gmdate('c');
            $meta['source_publication']['message']=$message;
            $stored['meta']=$meta;
            $this->savePayload($shipment,$stored);
            $this->repo->event((int)$shipment['order_id'],$message,$actor);
            return $message;
        } catch (\Throwable $error) {
            $safe=mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u',' ',$error->getMessage())??''),0,240,'UTF-8');
            $meta['source_publication']['state']='rejected';
            $meta['source_publication']['message']='Źródło nie przyjęło numeru przesyłki.';
            $meta['source_publication']['error']=$safe;
            $stored['meta']=$meta;
            $this->savePayload($shipment,$stored);
            $this->repo->event((int)$shipment['order_id'],'Przekazanie numeru przesyłki bez potwierdzenia źródła'.($safe!==''?': '.$safe:''),$actor);
            throw $error;
        }
    }

    public function publish(array $order,string $tracking,string $carrierCode,string $carrierName): string
    {
        $target=$this->target($order);
        $this->send($target,$order,$tracking,$carrierCode,$carrierName);
        return 'Numer przesyłki przekazany do '.$this->platformLabel((string)$order['platform']).'; źródło potwierdziło odbiór.';
    }

    private function target(array $order): array
    {
        $platform=(string)($order['platform']??'');
        if ($platform==='manual') { throw new InvalidArgumentException('Zamówienie własne nie ma marketplace’u źródłowego.'); }
        if ($platform==='morele') { throw new RuntimeException('Przekazanie numeru do Morele wymaga włączenia Orders API dla tego konta.'); }
        if (!in_array($platform,['allegro','empik','mediamarkt','erli'],true)) { throw new RuntimeException('Brak obsługi przekazania przesyłki do źródła '.$platform.'.'); }
        $service=$this->service($platform);
        $account=$this->sourceAccount($service,(int)($order['account_source_id']??0),$platform);
        return ['platform'=>$platform,'service'=>$service,'account'=>$account];
    }

    private function send(array $target,array $order,string $tracking,string $carrierCode,string $carrierName): void
    {
        $target['service']->publishOrderShipment($target['account'],(string)$order['external_id'],$tracking,$carrierCode,$carrierName);
    }

    private function service(string $platform)
    {
        if (isset($this->services[$platform])) { return $this->services[$platform]; }
        $classes=['allegro'=>AllegroService::class,'empik'=>EmpikService::class,'mediamarkt'=>MediaMarktService::class,'erli'=>ErliService::class];
        return $platform==='allegro'?new AllegroService(true):new $classes[$platform]();
    }

    private function sourceAccount($service,int $sourceId,string $platform): array
    {
        foreach ($service->listAccounts() as $account) {
            if ((int)($account['id']??0)===$sourceId && (!array_key_exists('is_active',$account) || !empty($account['is_active']))) { return $account; }
        }
        throw new RuntimeException('Konto źródłowe '.$this->platformLabel($platform).' jest nieaktywne albo zostało usunięte.');
    }

    private function payload(array $shipment): array
    {
        $stored=json_decode((string)($shipment['payload_json']??''),true);
        return is_array($stored)?$stored:[];
    }

    private function savePayload(array $shipment,array $stored): void
    {
        $this->repo->db()->update('om_shipments',['payload_json'=>OrderRepository::json($stored)],'id=:id',['id'=>$shipment['id']]);
    }

    private function platformLabel(string $platform): string
    {
        return ['allegro'=>'Allegro','empik'=>'Empik','mediamarkt'=>'MediaMarkt','erli'=>'ERLI','morele'=>'Morele'][$platform]??ucfirst($platform);
    }
}
