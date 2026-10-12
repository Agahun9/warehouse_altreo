<?php
/** Statystyki sprzedaży na bazie w pamięci. Nie czyta danych dostępowych i nie łączy się z marketplace'ami. */
declare(strict_types=1);
define('BASE_PATH',dirname(__DIR__));
require BASE_PATH.'/app/bootstrap.php';
use App\Core\Database;
use App\Models\OrderRepository;
use App\Models\OrderStatsRepository;

$checks=0;
function check(bool $condition,string $label): void { global $checks; $checks++; if (!$condition) { throw new RuntimeException($label); } }
$reflection=new ReflectionClass(Database::class);
$db=$reflection->newInstanceWithoutConstructor();
$pdo=new PDO('sqlite::memory:'); $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
foreach (['pdo'=>$pdo,'config'=>['driver'=>'sqlite']] as $name=>$value) { $property=$reflection->getProperty($name); $property->setValue($db,$value); }
App\Core\Tenant::activate(1);
$repo=new OrderRepository($db); $repo->ensureSchema();
$repo->registerAccount('allegro',1,'Sklep A'); $repo->registerAccount('erli',1,'Sklep C');
$stats=new OrderStatsRepository($db);

$number=0;
$order=static function (int $accountId,int $statusId,string $orderedAtUtc,array $items,int $totalCents,array $extra=[],?string $rawDetails=null) use ($db,&$number): int {
    $details=['items'=>$items,'delivery'=>$extra['delivery']??'InPost Paczkomat','cash_on_delivery'=>$extra['cod']??0,'raw'=>['padding'=>str_repeat('x',200)]];
    if (array_key_exists('delivery',$extra) && $extra['delivery']===null) { unset($details['delivery']); }
    return (int)$db->insert('om_orders',['account_id'=>$accountId,'external_id'=>'T-'.(++$number),'remote_status'=>'NEW','status_id'=>$statusId,'status_manual'=>0,'ordered_at'=>$orderedAtUtc,
        'buyer_name'=>'Klient Testowy','email'=>'klient@example.invalid','phone'=>'','total_cents'=>$totalCents,'currency'=>$extra['currency']??'PLN','paid'=>$extra['paid']??1,
        'details_json'=>$rawDetails??OrderRepository::json($details),'note'=>'','tags'=>'','imported_at'=>$orderedAtUtc,'updated_at'=>$orderedAtUtc]);
};
$item=static function (string $name,string $sku,int $quantity,int $unitCents): array { return ['name'=>$name,'sku'=>$sku,'quantity'=>$quantity,'unit_cents'=>$unitCents,'vat'=>'23']; };

// Czas letni: 21:30 UTC to 23:30 w Polsce (poniedziałek 5.10), 22:30 UTC to już wtorek 6.10 00:30.
$order(1,4,'2026-10-05 21:30:00',[$item('Laptop X','SKU-X',2,4000),$item('Mysz Y','SKU-Y',1,2000)],10000);
$order(1,1,'2026-10-05 22:30:00',[$item('Laptop X','SKU-X',5,1000)],5000,['paid'=>0,'cod'=>1,'delivery'=>'Kurier DPD pobranie']);
$order(2,6,'2026-10-06 10:00:00',[$item('Laptop X','SKU-X',10,1000)],10000);
$order(2,4,'2026-10-07 09:00:00',[$item('Kabel','',1,2500)],2500,['currency'=>'EUR','delivery'=>null]);
$order(1,4,'2026-10-03 12:00:00',[$item('Laptop X','SKU-X',4,1000)],4000);
// 22:00 UTC 7.10 to północ 8.10 w Polsce – poza zakresem „do 7.10”.
$order(1,4,'2026-10-07 22:00:00',[$item('Monitor','SKU-M',7,1000)],7000);

$filters=$stats->filters(['from'=>'2026-10-05','to'=>'2026-10-07']);
check($filters['from']==='2026-10-05' && $filters['to']==='2026-10-07' && $filters['statuses']===null && $filters['accounts']===[] && $filters['group']==='auto','Filter defaults');
$swapped=$stats->filters(['from'=>'2026-10-07','to'=>'2026-10-05','accounts'=>'2,x,2,-1','statuses'=>['4','6'],'paid'=>'9','currency'=>'pln','group'=>'year']);
check($swapped['from']==='2026-10-05' && $swapped['to']==='2026-10-07' && $swapped['accounts']===[2] && $swapped['statuses']===[4,6] && $swapped['paid']==='' && $swapped['currency']==='PLN' && $swapped['group']==='auto','Filter input is normalized');
$invalid=$stats->filters(['from'=>'2026-02-31','to'=>'bad']);
check(preg_match('/^\d{4}-\d{2}-\d{2}$/D',$invalid['from'])===1 && $invalid['from']<=$invalid['to'],'Invalid dates fall back to the last 30 days');
$long=$stats->filters(['from'=>'2000-01-01','to'=>'2026-10-07']);
check($long['from']>'2023-01-01','Range is capped');

$verify=static function (array $report,string $path): void {
    $totals=$report['totals'];
    check($totals['orders']===3 && $totals['units']===9 && $totals['lines']===4,"$path: orders, units and lines of counted statuses");
    check($report['currency']==='PLN' && $totals['revenue_cents']===15000 && $totals['orders_cur']===2,"$path: revenue only in the report currency");
    check(array_column($report['currencies'],'orders','code')===['PLN'=>2,'EUR'=>1],"$path: currency list");
    check($report['excluded']===['orders'=>1,'units'=>10],"$path: cancelled order is excluded by default");
    check(array_column($report['buckets'],'key')===['2026-10-05','2026-10-06','2026-10-07'] && $report['filters']['group']==='day',"$path: daily buckets");
    check($report['series']['1']['units']===[3,5,0] && $report['series']['2']['units']===[0,0,1] && $report['series']['1']['revenue']===[10000,5000,0],"$path: series use Polish local days");
    check($report['heatmap']['units'][0][23]===3 && $report['heatmap']['units'][1][0]===5 && $report['heatmap']['orders'][2][11]===1,"$path: weekday and hour in Polish time");
    check($report['previous_period']===['from'=>'2026-10-02','to'=>'2026-10-04'] && $report['previous_totals']['units']===4 && $report['previous_series']['units']===[0,4,0],"$path: previous period");
    $accounts=array_column($report['accounts'],null,'id');
    check($accounts[1]['units']===8 && $accounts[1]['orders']===2 && $accounts[1]['revenue']===['PLN'=>15000] && $accounts[1]['previous']['units']===4,"$path: account 1");
    check($accounts[2]['units']===1 && $accounts[2]['revenue']===['EUR'=>2500] && $accounts[2]['revenue_cents']===0 && $accounts[2]['excluded_orders']===1,"$path: account 2 keeps its own currency");
    check($report['accounts'][0]['id']===1,"$path: accounts sorted by units");
    $products=array_column($report['products'],null,'name');
    check($report['products'][0]['sku']==='SKU-X' && $products['Laptop X']['units']===7 && $products['Laptop X']['orders']===2 && $products['Laptop X']['revenue_cents']===13000,"$path: top product");
    check($products['Kabel']['units']===1 && $products['Kabel']['revenue_cents']===0 && $report['products_count']===3,"$path: product without SKU, foreign currency revenue skipped");
    $statuses=array_column($report['statuses'],null,'id');
    check($statuses[6]['included']===false && $statuses[6]['orders']===1 && $statuses[6]['units']===10 && $statuses[4]['included']===true && $statuses[4]['orders']===2,"$path: status breakdown keeps excluded statuses");
    $payment=array_column($report['payment'],'orders','key');
    check($payment===['paid'=>2,'cod'=>1,'unpaid'=>0],"$path: payment split");
    $delivery=array_column($report['delivery'],'orders','name');
    check($delivery===['InPost Paczkomat'=>1,'Kurier DPD pobranie'=>1,'Brak danych'=>1] || count($delivery)===3 && $delivery['Brak danych']===1 && $delivery['Kurier DPD pobranie']===1,"$path: delivery methods");
    check(array_column($report['basket'],'orders','name')===['1 szt.'=>1,'3 szt.'=>1,'4–5 szt.'=>1],"$path: basket sizes");
    check(array_column($report['platforms'],'units','name')===['Allegro'=>8,'ERLI'=>1],"$path: platforms");
};
$verify($stats->report($filters),'SQL JSON');
$sqlJson=(new ReflectionClass(OrderStatsRepository::class))->getProperty('sqlJson');
check($sqlJson->getValue($stats)===true,'Details are extracted by the database');

check($stats->report($stats->filters(['from'=>'2026-10-05','to'=>'2026-10-07','accounts'=>'2']))['totals']['orders']===1,'Account filter');
$cancelled=$stats->report($stats->filters(['from'=>'2026-10-05','to'=>'2026-10-07','statuses'=>'6']));
check($cancelled['totals']['orders']===1 && $cancelled['totals']['units']===10 && $cancelled['excluded']['orders']===3,'Explicit status filter');
$unpaid=$stats->report($stats->filters(['from'=>'2026-10-05','to'=>'2026-10-07','paid'=>'0']));
check($unpaid['totals']['orders']===1 && $unpaid['totals']['units']===5,'Payment filter');
$euro=$stats->report($stats->filters(['from'=>'2026-10-05','to'=>'2026-10-07','currency'=>'EUR']));
check($euro['currency']==='EUR' && $euro['totals']['revenue_cents']===2500 && $euro['totals']['units']===9,'Currency switch changes amounts only');
$weekly=$stats->report($stats->filters(['from'=>'2026-10-03','to'=>'2026-10-07','group'=>'week']));
check(array_column($weekly['buckets'],'key')===['2026-09-28','2026-10-05'] && $weekly['buckets'][0]['from']==='2026-10-03' && $weekly['buckets'][0]['to']==='2026-10-04' && $weekly['series']['1']['units']===[4,8],'Weekly buckets start on Monday and are clipped to the range');
$monthly=$stats->report($stats->filters(['from'=>'2026-09-20','to'=>'2026-10-07','group'=>'month']));
check(array_column($monthly['buckets'],'key')===['2026-09','2026-10'] && $monthly['series']['1']['units']===[0,12],'Monthly buckets');
$empty=$stats->report($stats->filters(['from'=>'2020-01-01','to'=>'2020-01-31']));
check($empty['totals']['orders']===0 && $empty['accounts']===[] && $empty['products']===[] && $empty['currency']==='PLN' && count($empty['buckets'])===31,'Empty range');

// Uszkodzony JSON jednego zamówienia nie blokuje raportu: przejście na dekodowanie w PHP daje ten sam wynik.
$broken=$order(2,6,'2026-10-06 11:00:00',[],100,[],'{"items":[');
$fallbackStats=new OrderStatsRepository($db);
$fallback=$fallbackStats->report($filters);
check($sqlJson->getValue($fallbackStats)===false,'Broken JSON switches to PHP decoding');
check($fallback['excluded']===['orders'=>2,'units'=>10],'Broken JSON order counted with zero units');
$fallback['excluded']=['orders'=>1,'units'=>10];
$fallbackStatuses=array_column($fallback['statuses'],null,'id');
check($fallbackStatuses[6]['orders']===2,'Fallback status breakdown');
foreach ($fallback['statuses'] as &$status) { if ($status['id']===6) { $status['orders']=1; $status['revenue_cents']-=100; } } unset($status);
foreach ($fallback['accounts'] as &$account) { if ($account['id']===2) { $account['excluded_orders']=1; } } unset($account);
$verify($fallback,'PHP JSON fallback');
$db->delete('om_orders','id=:id',['id'=>$broken]);

// Stronicowanie po (ordered_at,id): więcej zamówień niż jedna porcja, w tym wiele o tej samej sekundzie.
$db->pdo()->beginTransaction();
for ($i=0; $i<4500; $i++) { $order(2,4,$i<2500?'2026-11-10 08:00:00':sprintf('2026-11-10 %02d:%02d:00',9+intdiv($i,600),$i%60),[$item('Seria','SKU-S',2,100)],200); }
$db->pdo()->commit();
$bulk=$stats->report($stats->filters(['from'=>'2026-11-10','to'=>'2026-11-10']));
check($bulk['totals']['orders']===4500 && $bulk['totals']['units']===9000 && $bulk['products'][0]['orders']===4500,'Keyset pagination returns every order exactly once');

echo "OK: $checks checks\n";
