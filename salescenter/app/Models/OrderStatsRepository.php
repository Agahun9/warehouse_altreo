<?php

declare(strict_types=1);
namespace App\Models;

use App\Core\Database;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Statystyki sprzedaży centrum zamówień. Wyłącznie odczyt: zamówienia z om_orders,
 * pozycje (sztuki, produkty) z details_json. Dni, tygodnie i godziny liczone w czasie polskim,
 * bo ordered_at jest zapisane w UTC.
 */
final class OrderStatsRepository
{
    private const TIMEZONE = 'Europe/Warsaw';
    private const MAX_DAYS = 1100;
    private const MAX_BUCKETS = 400;
    private const CHUNK = 2000;
    private const TIME_BUDGET = 20.0;
    private const PRODUCT_LIMIT = 200;
    private const DELIVERY_LIMIT = 8;
    /** Statusy domyślnie nieliczone do sprzedaży (użytkownik może je włączyć filtrem). */
    private const EXCLUDED_STATUS_PATTERN = '/anul|zwrot|zwróc|odrzuc|cancel|refund|return/iu';
    public const PLATFORM_LABELS = ['manual'=>'Własne','api'=>'Własny sklep (API)','allegro'=>'Allegro','erli'=>'ERLI','empik'=>'Empik','mediamarkt'=>'MediaMarkt','morele'=>'Morele','temu'=>'Temu','prestashop'=>'PrestaShop','woocommerce'=>'WooCommerce','altreo'=>'Altreo.pl'];

    /** @var Database */
    private $db;
    /** @var bool */
    private $sqlJson = true;
    /** @var float */
    private $started = 0.0;

    public function __construct(Database $db) { $this->db = $db; }

    /** Konta i statusy do filtrów strony. */
    public function options(): array
    {
        $accounts=[];
        foreach ($this->db->fetchAll('SELECT id,platform,name,enabled FROM om_accounts ORDER BY platform,name,id') as $row) {
            $accounts[]=['id'=>(int)$row['id'],'platform'=>(string)$row['platform'],'platform_label'=>self::PLATFORM_LABELS[$row['platform']]??(string)$row['platform'],'name'=>(string)$row['name'],'enabled'=>(int)$row['enabled']];
        }
        $statuses=[];
        foreach ($this->db->fetchAll('SELECT id,name,color,group_name FROM om_statuses ORDER BY position,id') as $row) {
            $statuses[]=['id'=>(int)$row['id'],'name'=>(string)$row['name'],'color'=>preg_match('/^#[0-9a-fA-F]{6}$/D',(string)$row['color'])?(string)$row['color']:'#64748b','group'=>(string)$row['group_name'],'excluded_default'=>preg_match(self::EXCLUDED_STATUS_PATTERN,(string)$row['name'])===1];
        }
        return ['accounts'=>$accounts,'statuses'=>$statuses,'today'=>(new DateTimeImmutable('now',new DateTimeZone(self::TIMEZONE)))->format('Y-m-d')];
    }

    /** Normalizuje parametry żądania (GET) do bezpiecznych filtrów raportu. */
    public function filters(array $input): array
    {
        $zone=new DateTimeZone(self::TIMEZONE);
        $today=new DateTimeImmutable('today',$zone);
        $date=static function ($value) use ($zone): ?DateTimeImmutable {
            $value=is_scalar($value)?(string)$value:'';
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$value,$m)!==1 || !checkdate((int)$m[2],(int)$m[3],(int)$m[1])) { return null; }
            return new DateTimeImmutable($value.' 00:00:00',$zone);
        };
        $to=$date($input['to']??null)?:$today;
        $from=$date($input['from']??null)?:$to->modify('-29 days');
        if ($from>$to) { [$from,$to]=[$to,$from]; }
        if ((int)$from->diff($to)->days>=self::MAX_DAYS) { $from=$to->modify('-'.(self::MAX_DAYS-1).' days'); }
        $ids=static function ($value): ?array {
            if ($value===null) { return null; }
            $list=is_array($value)?$value:explode(',',(string)$value);
            $clean=[];
            foreach ($list as $item) { if (is_scalar($item) && ctype_digit(trim((string)$item)) && strlen(trim((string)$item))<=18 && (int)$item>0) { $clean[(int)$item]=true; } }
            return array_slice(array_keys($clean),0,500);
        };
        $paid=is_scalar($input['paid']??null)?(string)$input['paid']:'';
        $currency=strtoupper(is_scalar($input['currency']??null)?trim((string)$input['currency']):'');
        $group=is_scalar($input['group']??null)?(string)$input['group']:'auto';
        return [
            'from'=>$from->format('Y-m-d'),'to'=>$to->format('Y-m-d'),
            'accounts'=>$ids($input['accounts']??null)?:[],
            'statuses'=>$ids($input['statuses']??null)?:null,
            'paid'=>in_array($paid,['0','1'],true)?$paid:'',
            'currency'=>preg_match('/^[A-Z]{3}$/D',$currency)===1?$currency:'',
            'group'=>in_array($group,['day','week','month'],true)?$group:'auto',
        ];
    }

    /** Pełny raport dla strony statystyk; $filters pochodzi z filters(). */
    public function report(array $filters): array
    {
        $this->started=microtime(true);
        $zone=new DateTimeZone(self::TIMEZONE);
        $from=new DateTimeImmutable($filters['from'].' 00:00:00',$zone);
        $to=new DateTimeImmutable($filters['to'].' 00:00:00',$zone);
        $days=(int)$from->diff($to)->days+1;
        $group=$filters['group']==='auto'?($days<=92?'day':($days<=550?'week':'month')):$filters['group'];
        if ($group==='day' && $days>self::MAX_BUCKETS) { $group='week'; }

        $options=$this->options();
        $accountIndex=[]; foreach ($options['accounts'] as $account) { $accountIndex[$account['id']]=$account; }
        $accountFilter=array_values(array_filter($filters['accounts'],static function (int $id) use ($accountIndex): bool { return isset($accountIndex[$id]); }));
        $included=[];
        foreach ($options['statuses'] as $status) {
            if ($filters['statuses']===null ? !$status['excluded_default'] : in_array($status['id'],$filters['statuses'],true)) { $included[$status['id']]=true; }
        }
        if (!$included) { foreach ($options['statuses'] as $status) { if (!$status['excluded_default']) { $included[$status['id']]=true; } } }
        $scope=['accounts'=>$accountFilter,'paid'=>$filters['paid']];

        $currencies=$this->currencies($from,$to->modify('+1 day'),$scope,array_keys($included));
        $currency=$filters['currency'];
        if ($currency==='' || !isset($currencies[$currency])) { $currency=$currencies?(string)array_key_first($currencies):'PLN'; }

        $buckets=self::buckets($from,$to,$group);
        $current=$this->aggregate($from,$to->modify('+1 day'),$scope,$included,$group,$currency,true);
        $previous=null; $previousBuckets=[]; $previousRange=null;
        if ($days<=366) {
            $previousTo=$from->modify('-1 day'); $previousFrom=$from->modify('-'.$days.' days');
            $previousRange=['from'=>$previousFrom->format('Y-m-d'),'to'=>$previousTo->format('Y-m-d')];
            $previousBuckets=self::buckets($previousFrom,$previousTo,$group);
            $previous=$this->aggregate($previousFrom,$from,$scope,$included,$group,$currency,false);
        }

        $series=[];
        foreach ($current['series'] as $accountId=>$byBucket) {
            $row=['orders'=>[],'units'=>[],'revenue'=>[]];
            foreach ($buckets as $bucket) {
                $cell=$byBucket[$bucket['key']]??[0,0,0];
                $row['orders'][]=$cell[0]; $row['units'][]=$cell[1]; $row['revenue'][]=$cell[2];
            }
            $series[(string)$accountId]=$row;
        }
        $previousSeries=null;
        if ($previous!==null) {
            $previousSeries=['orders'=>[],'units'=>[],'revenue'=>[]];
            foreach ($previousBuckets as $bucket) {
                $cell=[0,0,0];
                foreach ($previous['series'] as $byBucket) { if (isset($byBucket[$bucket['key']])) { foreach ([0,1,2] as $i) { $cell[$i]+=$byBucket[$bucket['key']][$i]; } } }
                $previousSeries['orders'][]=$cell[0]; $previousSeries['units'][]=$cell[1]; $previousSeries['revenue'][]=$cell[2];
            }
        }

        $accounts=[]; $platforms=[];
        foreach ($current['accounts']+($previous['accounts']??[]) as $accountId=>$unused) {
            $meta=$accountIndex[$accountId]??['id'=>(int)$accountId,'platform'=>'','platform_label'=>'','name'=>'Konto #'.$accountId,'enabled'=>0];
            $row=$current['accounts'][$accountId]??self::emptyAccount();
            $before=$previous['accounts'][$accountId]??null;
            if (!$row['orders'] && !$row['excluded_orders'] && !($before && $before['orders'])) { continue; }
            arsort($row['revenue']);
            $accounts[]=['id'=>(int)$accountId,'name'=>$meta['name'],'platform'=>$meta['platform'],'platform_label'=>$meta['platform_label'],'enabled'=>$meta['enabled'],
                'orders'=>$row['orders'],'units'=>$row['units'],'orders_cur'=>$row['orders_cur'],'revenue_cents'=>$row['revenue_cents'],'revenue'=>$row['revenue'],'excluded_orders'=>$row['excluded_orders'],
                'previous'=>$previous===null?null:['orders'=>$before['orders']??0,'units'=>$before['units']??0,'revenue_cents'=>$before['revenue_cents']??0]];
            if ($row['orders']) {
                $key=$meta['platform']!==''?$meta['platform']:'?';
                $platforms[$key]=$platforms[$key]??['name'=>$meta['platform_label']!==''?$meta['platform_label']:'Inne','orders'=>0,'units'=>0,'revenue_cents'=>0];
                $platforms[$key]['orders']+=$row['orders']; $platforms[$key]['units']+=$row['units']; $platforms[$key]['revenue_cents']+=$row['revenue_cents'];
            }
        }
        usort($accounts,static function (array $a,array $b): int { return [$b['units'],$b['orders'],$a['id']]<=>[$a['units'],$a['orders'],$b['id']]; });
        $platforms=array_values($platforms);
        usort($platforms,static function (array $a,array $b): int { return [$b['units'],$b['orders']]<=>[$a['units'],$a['orders']]; });

        $statuses=[];
        foreach ($options['statuses'] as $status) {
            $row=$current['statuses'][$status['id']]??[0,0,0];
            $statuses[]=['id'=>$status['id'],'name'=>$status['name'],'color'=>$status['color'],'group'=>$status['group'],'included'=>isset($included[$status['id']]),'orders'=>$row[0],'units'=>$row[1],'revenue_cents'=>$row[2]];
        }

        $products=array_values($current['products']);
        $productCount=count($products);
        usort($products,static function (array $a,array $b): int { return [$b['units'],$b['revenue_cents'],$a['name']]<=>[$a['units'],$a['revenue_cents'],$b['name']]; });
        $products=array_slice($products,0,self::PRODUCT_LIMIT);

        $delivery=$current['delivery'];
        uasort($delivery,static function (array $a,array $b): int { return [$b['orders'],$b['units']]<=>[$a['orders'],$a['units']]; });
        $deliveryTop=array_slice(array_values($delivery),0,self::DELIVERY_LIMIT);
        $rest=array_slice(array_values($delivery),self::DELIVERY_LIMIT);
        if ($rest) {
            $other=['name'=>'Pozostałe ('.count($rest).')','orders'=>0,'units'=>0,'revenue_cents'=>0];
            foreach ($rest as $row) { $other['orders']+=$row['orders']; $other['units']+=$row['units']; $other['revenue_cents']+=$row['revenue_cents']; }
            $deliveryTop[]=$other;
        }

        $currencyList=[];
        foreach ($currencies as $code=>$row) { $currencyList[]=['code'=>(string)$code,'orders'=>$row['orders'],'revenue_cents'=>$row['revenue_cents']]; }

        return [
            'filters'=>['from'=>$filters['from'],'to'=>$filters['to'],'accounts'=>$accountFilter,'statuses'=>array_keys($included),'paid'=>$filters['paid'],'group'=>$group],
            'period'=>['from'=>$filters['from'],'to'=>$filters['to'],'days'=>$days],
            'previous_period'=>$previousRange,
            'currency'=>$currency,'currencies'=>$currencyList,
            'totals'=>$current['totals'],'previous_totals'=>$previous['totals']??null,
            'excluded'=>$current['excluded'],'previous_excluded'=>$previous['excluded']??null,
            'buckets'=>$buckets,'previous_buckets'=>$previousBuckets,
            'series'=>$series,'previous_series'=>$previousSeries,
            'accounts'=>$accounts,'platforms'=>$platforms,'statuses'=>$statuses,
            'heatmap'=>$current['heatmap'],
            'products'=>$products,'products_count'=>$productCount,
            'delivery'=>$deliveryTop,
            'payment'=>array_values($current['payment']),
            'basket'=>array_values(array_filter($current['basket'],static function (array $row): bool { return $row['orders']>0; })),
            'generated_at'=>(new DateTimeImmutable('now',$zone))->format('Y-m-d H:i:s'),
            'elapsed_ms'=>(int)round((microtime(true)-$this->started)*1000),
        ];
    }

    private static function emptyAccount(): array
    {
        return ['orders'=>0,'units'=>0,'orders_cur'=>0,'revenue_cents'=>0,'revenue'=>[],'excluded_orders'=>0];
    }

    private static function utc(DateTimeImmutable $local): string
    {
        return $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private static function bucketKey(DateTimeImmutable $local,string $group): string
    {
        if ($group==='month') { return $local->format('Y-m'); }
        if ($group==='week') { return $local->modify('-'.((int)$local->format('N')-1).' days')->format('Y-m-d'); }
        return $local->format('Y-m-d');
    }

    /** Kolejne przedziały osi czasu (także puste), przycięte do wybranego zakresu. */
    private static function buckets(DateTimeImmutable $from,DateTimeImmutable $to,string $group): array
    {
        $buckets=[]; $index=[];
        for ($day=$from; $day<=$to; $day=$day->modify('+1 day')) {
            $key=self::bucketKey($day,$group);
            if (!isset($index[$key])) { $index[$key]=count($buckets); $buckets[]=['key'=>$key,'from'=>$day->format('Y-m-d'),'to'=>$day->format('Y-m-d')]; }
            else { $buckets[$index[$key]]['to']=$day->format('Y-m-d'); }
        }
        return $buckets;
    }

    private function scopeSql(array $scope,array &$params): string
    {
        $sql='';
        if ($scope['accounts']) { $sql.=' AND o.account_id IN ('.implode(',',array_map('intval',$scope['accounts'])).')'; }
        if ($scope['paid']!=='') { $sql.=' AND o.paid=:paid'; $params['paid']=(int)$scope['paid']; }
        return $sql;
    }

    /** Waluty zamówień liczonych do sprzedaży, od najczęstszej. */
    private function currencies(DateTimeImmutable $from,DateTimeImmutable $toExclusive,array $scope,array $statusIds): array
    {
        $params=['from'=>self::utc($from),'to'=>self::utc($toExclusive)];
        $where=$this->scopeSql($scope,$params);
        if ($statusIds) { $where.=' AND o.status_id IN ('.implode(',',array_map('intval',$statusIds)).')'; }
        $rows=$this->db->fetchAll("SELECT o.currency,COUNT(*) orders,SUM(o.total_cents) revenue_cents FROM om_orders o WHERE o.ordered_at>=:from AND o.ordered_at<:to$where GROUP BY o.currency ORDER BY orders DESC,o.currency",$params);
        $currencies=[];
        foreach ($rows as $row) { $currencies[strtoupper((string)$row['currency'])]=['orders'=>(int)$row['orders'],'revenue_cents'=>(int)$row['revenue_cents']]; }
        return $currencies;
    }

    /**
     * Jedno przejście po zamówieniach okresu. Liczniki zamówień i sztuk obejmują wszystkie waluty,
     * kwoty (revenue_cents) tylko walutę raportu. $detailed=false pomija przekroje potrzebne wyłącznie
     * dla bieżącego okresu (produkty, mapa godzin, dostawy).
     */
    private function aggregate(DateTimeImmutable $from,DateTimeImmutable $toExclusive,array $scope,array $included,string $group,string $currency,bool $detailed): array
    {
        $zone=new DateTimeZone(self::TIMEZONE); $utc=new DateTimeZone('UTC');
        $totals=['orders'=>0,'units'=>0,'lines'=>0,'orders_cur'=>0,'revenue_cents'=>0,'paid_orders'=>0];
        $excluded=['orders'=>0,'units'=>0];
        $series=[]; $accounts=[]; $statuses=[]; $products=[]; $delivery=[];
        $heatmap=['orders'=>array_fill(0,7,array_fill(0,24,0)),'units'=>array_fill(0,7,array_fill(0,24,0)),'revenue'=>array_fill(0,7,array_fill(0,24,0))];
        $payment=['paid'=>['key'=>'paid','name'=>'Opłacone','orders'=>0,'units'=>0,'revenue_cents'=>0],'cod'=>['key'=>'cod','name'=>'Pobranie','orders'=>0,'units'=>0,'revenue_cents'=>0],'unpaid'=>['key'=>'unpaid','name'=>'Nieopłacone','orders'=>0,'units'=>0,'revenue_cents'=>0]];
        $basket=[];
        foreach (['0'=>'Bez pozycji','1'=>'1 szt.','2'=>'2 szt.','3'=>'3 szt.','4'=>'4–5 szt.','6'=>'6–10 szt.','11'=>'11 i więcej'] as $key=>$name) { $basket[$key]=['name'=>$name,'orders'=>0,'units'=>0,'revenue_cents'=>0]; }
        $hours=[];

        $toUtc=self::utc($toExclusive); $cursor=self::utc($from); $lastId=0;
        while (true) {
            if (microtime(true)-$this->started>self::TIME_BUDGET) {
                throw new InvalidArgumentException('Ten zakres zawiera zbyt wiele zamówień, żeby policzyć go na żywo. Zawęź daty albo wybierz mniej kont.');
            }
            $rows=$this->chunk($cursor,$toUtc,$lastId,$scope);
            foreach ($rows as $row) {
                $cursor=(string)$row['ordered_at']; $lastId=(int)$row['id'];
                $accountId=(int)$row['account_id']; $statusId=(int)$row['status_id'];
                [$items,$deliveryName,$cod]=$this->details($row);
                $units=0;
                foreach ($items as $item) { if (is_array($item)) { $units+=max(0,(int)($item['quantity']??0)); } }
                $sameCurrency=strtoupper((string)$row['currency'])===$currency;
                $amount=$sameCurrency?(int)$row['total_cents']:0;
                if (!isset($statuses[$statusId])) { $statuses[$statusId]=[0,0,0]; }
                $statuses[$statusId][0]++; $statuses[$statusId][1]+=$units; $statuses[$statusId][2]+=$amount;
                if (!isset($accounts[$accountId])) { $accounts[$accountId]=self::emptyAccount(); }
                if (!isset($included[$statusId])) {
                    $excluded['orders']++; $excluded['units']+=$units; $accounts[$accountId]['excluded_orders']++;
                    continue;
                }
                $hourKey=substr($cursor,0,13);
                if (!isset($hours[$hourKey])) {
                    try { $local=(new DateTimeImmutable($hourKey.':00:00',$utc))->setTimezone($zone); }
                    catch (\Throwable $error) { $local=$from; }
                    $hours[$hourKey]=[self::bucketKey($local,$group),(int)$local->format('N')-1,(int)$local->format('G')];
                }
                [$bucket,$weekday,$hour]=$hours[$hourKey];

                $totals['orders']++; $totals['units']+=$units; $totals['lines']+=count($items); $totals['revenue_cents']+=$amount;
                if ($sameCurrency) { $totals['orders_cur']++; }
                if ((int)$row['paid']) { $totals['paid_orders']++; }
                $account=&$accounts[$accountId];
                $account['orders']++; $account['units']+=$units; $account['revenue_cents']+=$amount;
                if ($sameCurrency) { $account['orders_cur']++; }
                $code=strtoupper((string)$row['currency']);
                $account['revenue'][$code]=($account['revenue'][$code]??0)+(int)$row['total_cents'];
                unset($account);
                if (!isset($series[$accountId][$bucket])) { $series[$accountId][$bucket]=[0,0,0]; }
                $series[$accountId][$bucket][0]++; $series[$accountId][$bucket][1]+=$units; $series[$accountId][$bucket][2]+=$amount;
                if (!$detailed) { continue; }

                $heatmap['orders'][$weekday][$hour]++; $heatmap['units'][$weekday][$hour]+=$units; $heatmap['revenue'][$weekday][$hour]+=$amount;
                $paymentKey=$cod?'cod':((int)$row['paid']?'paid':'unpaid');
                $payment[$paymentKey]['orders']++; $payment[$paymentKey]['units']+=$units; $payment[$paymentKey]['revenue_cents']+=$amount;
                $basketKey=$units<=3?(string)$units:($units<=5?'4':($units<=10?'6':'11'));
                $basket[$basketKey]['orders']++; $basket[$basketKey]['units']+=$units; $basket[$basketKey]['revenue_cents']+=$amount;
                $deliveryKey=$deliveryName!==''?mb_substr($deliveryName,0,120,'UTF-8'):'Brak danych';
                if (!isset($delivery[$deliveryKey])) { $delivery[$deliveryKey]=['name'=>$deliveryKey,'orders'=>0,'units'=>0,'revenue_cents'=>0]; }
                $delivery[$deliveryKey]['orders']++; $delivery[$deliveryKey]['units']+=$units; $delivery[$deliveryKey]['revenue_cents']+=$amount;
                $seen=[];
                foreach ($items as $item) {
                    if (!is_array($item)) { continue; }
                    $quantity=max(0,(int)($item['quantity']??0));
                    $name=trim(is_scalar($item['name']??null)?(string)$item['name']:'');
                    $sku=trim(is_scalar($item['sku']??null)?(string)$item['sku']:'');
                    if ($quantity<1 || ($name==='' && $sku==='')) { continue; }
                    $key=$sku!==''?'s:'.$sku:'n:'.mb_strtolower($name,'UTF-8');
                    if (!isset($products[$key])) { $products[$key]=['name'=>mb_substr($name!==''?$name:$sku,0,300,'UTF-8'),'sku'=>mb_substr($sku,0,190,'UTF-8'),'units'=>0,'orders'=>0,'revenue_cents'=>0]; }
                    $products[$key]['units']+=$quantity;
                    if ($sameCurrency) { $products[$key]['revenue_cents']+=$quantity*(int)($item['unit_cents']??0); }
                    if (!isset($seen[$key])) { $seen[$key]=true; $products[$key]['orders']++; }
                }
            }
            if (count($rows)<self::CHUNK) { break; }
        }
        return ['totals'=>$totals,'excluded'=>$excluded,'series'=>$series,'accounts'=>$accounts,'statuses'=>$statuses,'heatmap'=>$heatmap,'products'=>$products,'delivery'=>$delivery,'payment'=>$payment,'basket'=>$basket];
    }

    /** Porcja zamówień po indeksie (ordered_at,id); z JSON-a baza zwraca tylko potrzebne pola. */
    private function chunk(string $cursor,string $toUtc,int $lastId,array $scope): array
    {
        $params=['lo'=>$cursor,'lo2'=>$cursor,'id'=>$lastId,'to'=>$toUtc];
        $where=$this->scopeSql($scope,$params);
        $sql='SELECT o.id,o.account_id,o.status_id,o.ordered_at,o.total_cents,o.currency,o.paid,%s FROM om_orders o'
            .' WHERE o.ordered_at>=:lo AND o.ordered_at<:to AND (o.ordered_at>:lo2 OR o.id>:id)'.$where
            .' ORDER BY o.ordered_at,o.id LIMIT '.self::CHUNK;
        if ($this->sqlJson) {
            try { return $this->db->fetchAll(sprintf($sql,"JSON_EXTRACT(o.details_json,'$.items','$.delivery','$.cash_on_delivery') stat_json"),$params); }
            catch (\PDOException $error) { $this->sqlJson=false; } // np. uszkodzony JSON w jednym zamówieniu
        }
        return $this->db->fetchAll(sprintf($sql,'o.details_json'),$params);
    }

    /** @return array{0:array,1:string,2:bool} pozycje, metoda dostawy, pobranie */
    private function details(array $row): array
    {
        $items=[]; $delivery=''; $cod=false;
        if (array_key_exists('details_json',$row)) {
            $details=json_decode((string)$row['details_json'],true);
            if (is_array($details)) {
                $items=is_array($details['items']??null)?$details['items']:[];
                $delivery=is_string($details['delivery']??null)?trim($details['delivery']):'';
                $cod=!empty($details['cash_on_delivery']);
            }
            return [$items,$delivery,$cod];
        }
        $values=json_decode((string)($row['stat_json']??''),true);
        if (!is_array($values)) { return [$items,$delivery,$cod]; }
        // Wynik to lista dopasowań w kolejności ścieżek; brakujące pola baza pomija albo zwraca jako null.
        $itemsFound=false; $deliveryFound=false;
        foreach ($values as $value) {
            if (is_array($value)) { if (!$itemsFound) { $items=$value; $itemsFound=true; } }
            elseif (is_string($value) && !$deliveryFound) { $delivery=trim($value); $deliveryFound=true; }
            elseif ($value!==null) { $cod=$cod || !in_array($value,['','0',0,false],true); }
        }
        return [$items,$delivery,$cod];
    }
}
