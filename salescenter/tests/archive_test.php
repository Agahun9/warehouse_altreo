<?php
/** Archiwum Sellasist: import porcjami z atrapy API, wyszukiwanie, filtry, limity API i render widoków (SQLite w pamięci). */
declare(strict_types=1);
define('BASE_PATH',dirname(__DIR__));
require BASE_PATH.'/app/bootstrap.php';
use App\Core\Database;
use App\Core\Tenant;
use App\Models\OrderRepository;
use App\Models\SellasistArchiveRepository;
use App\Services\Integrations\Http;
use App\Services\SellasistArchiveService;

$checks=0;
function check(bool $condition,string $label): void { global $checks; $checks++; if (!$condition) { throw new RuntimeException($label); } }
function rejects(callable $fn,string $label,string $contains=''): void { try { $fn(); } catch (\Throwable $e) { check($contains==='' || strpos($e->getMessage(),$contains)!==false,$label.' ('.$e->getMessage().')'); return; } check(false,$label); }

$reflection=new ReflectionClass(Database::class);
$db=$reflection->newInstanceWithoutConstructor();
$pdo=new PDO('sqlite::memory:'); $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
foreach (['pdo'=>$pdo,'config'=>['driver'=>'sqlite','database'=>'test']] as $name=>$value) { $property=$reflection->getProperty($name); $property->setValue($db,$value); }
Database::useInstance($db);
App\Core\Config::override('app',['app_name'=>'SalesCenter','base_url'=>'./index.php','public_base_url'=>'https://panel.example.invalid/index.php','encryption_key'=>str_repeat('ab',32)]);
Tenant::activate(1);
(new OrderRepository($db))->ensureSchema();
$repo=new SellasistArchiveRepository($db); $repo->ensureSchema(); $repo->ensureSchema();
check(Tenant::rewrite('SELECT * FROM om_archive_orders o JOIN om_archive_documents d')==='SELECT * FROM t1_om_archive_orders o JOIN t1_om_archive_documents d','Archive tables are tenant tables');

// Atrapa Sellasist: 230 zamówień, 120 faktur, 3 korekty, 5 paragonów; korekty paragonów niedostępne (403 nie dla list → tu 400).
$orders=[];
for ($i=1;$i<=230;$i++) {
    $orders[$i]=['id'=>$i,'date'=>sprintf('2023-%02d-%02d 10:%02d:00',1+$i%12,1+$i%28,$i%60),'status'=>['id'=>$i%3+1,'name'=>['Nowe','W realizacji','Zrealizowane'][$i%3]],
        'email'=>"klient$i@example.com",'total'=>$i*10+0.99,'source'=>$i%2?'allegro':'shop','creator'=>'Allegro/konto',
        'bill_address'=>['name'=>'Jan','surname'=>"Kowalski$i",'company_name'=>$i===7?'Firma Siedem Sp. z o.o.':'','company_nip'=>$i===7?'822-19-90-318':'','phone'=>'600 100 '.sprintf('%03d',$i),'city'=>'Kraków','street'=>'Długa','postcode'=>'30-001','country'=>'PL'],
        'payment'=>['id'=>3,'name'=>'Przelew','paid'=>$i*10+0.99,'status'=>$i%5?'paid':'unpaid','currency'=>'PLN','cod'=>0]];
}
$detail=function (int $id) use ($orders): array {
    return $orders[$id]+['shipment_address'=>$orders[$id]['bill_address'],'shipment'=>['id'=>1,'name'=>'Kurier InPost','total'=>14.99],'tracking_number'=>"TRK$id",
        'external_data'=>['external_id'=>"ALG-$id",'external_login'=>'kupujacy'],'document_number'=>$id<=120?"FV/$id/2023":'',
        'carts'=>[['name'=>$id===42?'Laptop Dell Latitude 5490':"Produkt $id",'symbol'=>"SKU-$id",'ean'=>'59000000'.$id,'quantity'=>2,'price'=>5,'tax_rate'=>23,'image'=>'https://img.example/a.jpg']],
        'payments'=>[['amount'=>1,'currency'=>'PLN','date'=>'2023-01-01 10:00:00','name'=>'P24']],'additional_fields'=>[['field_name'=>'Uwagi','field_value'=>'brak']]];
};
$invoices=[]; for ($i=1;$i<=120;$i++) { $invoices[$i]=['id'=>$i,'number'=>"FV/$i/2023",'date'=>'2023-02-01']; }
$calls=[]; $mode='ok';
Http::$transport=function (string $method,string $url,array $headers,?string $body) use (&$calls,&$mode,$orders,$detail,$invoices): array {
    $calls[]=$url;
    $json=function ($data,int $status=200) { return ['status'=>$status,'body'=>json_encode($data),'headers'=>[]]; };
    if (!in_array('apiKey: secret-key',$headers,true)) { return $json(['message'=>'Unauthorized'],401); }
    if ($mode==='limit') { return $json(['message'=>'Too many'],429); }
    $parts=parse_url($url); parse_str($parts['query']??'',$q);
    check(strpos($url,'https://altreo.sellasist.pl/api/v1/')===0,'Requests go to the account API');
    $path=substr($parts['path'],strlen('/api/v1'));
    $page=function (array $all) use ($q) { check(($q['sort']??'')==='asc','Lists are read oldest first'); return array_values(array_slice($all,(int)($q['offset']??0),(int)($q['limit']??100))); };
    if ($path==='/statuses') { return $json([['id'=>1,'name'=>'Nowe']]); }
    if ($path==='/orders') { $rows=$page($orders); return $rows?$json(array_map(function ($o) { return array_diff_key($o,['bill_address'=>0]); },$rows)):$json(['message'=>'no results'],404); }
    if (preg_match('#^/orders/(\d+)$#',$path,$m)) { return (int)$m[1]===13?$json(['message'=>'gone'],404):$json($detail((int)$m[1])); }
    if ($path==='/invoices') { return $json($page($invoices)); }
    if (preg_match('#^/invoices/(\d+)$#',$path,$m)) { $id=(int)$m[1]; return $json(['id'=>$id,'number'=>"FV/$id/2023",'issue_date'=>'2023-02-01','sale_date'=>'2023-02-01','order_id'=>$id,'currency'=>'PLN','total'=>24.6,'buyer'=>['name'=>$id===7?'Firma Siedem Sp. z o.o.':"Jan Kowalski$id",'nip'=>$id===7?'8221990318':''],'seller'=>['name'=>'ALTREO','nip'=>'1234567890'],'lines'=>[['name'=>"Produkt $id",'quantity'=>2,'price_gross'=>12.3,'price_net'=>10,'vat'=>23]]]); }
    if ($path==='/corrects') { return $json($page([['id'=>1,'number'=>'KOR/1/2023','date'=>'2023-03-01'],['id'=>2,'number'=>'KOR/2/2023','date'=>'2023-03-02'],['id'=>3,'number'=>'KOR/3/2023','date'=>'2023-03-03']])); }
    if (preg_match('#^/corrects/(\d+)$#',$path,$m)) { return $json(['id'=>(int)$m[1],'number'=>'KOR/'.$m[1].'/2023','invoice_id'=>5,'order_id'=>5,'issue_date'=>'2023-03-01','total'=>-12.3,'buyer'=>['name'=>'Jan Kowalski5']]); }
    if ($path==='/receipts') { return $json($page(array_map(function ($i) { return ['id'=>$i,'date'=>'2023-04-0'.$i.'T10:00:00.000Z','printed'=>1]; },range(1,5)))); }
    if (preg_match('#^/receipts/(\d+)$#',$path,$m)) { return $json(['id'=>(int)$m[1],'number'=>'PAR/'.$m[1],'order_id'=>200+(int)$m[1],'issue_date'=>'2023-04-01','total'=>30,'buyer'=>['name'=>'Paragonowy']]); }
    if ($path==='/receiptcorrects') { return $json(['message'=>'Brak modułu'],400); }
    // Dokumenty operacyjne: ID kolidują ze starymi paragonami/fakturami; WZ (release) ma zostać pominięte.
    if ($path==='/operationdocuments') {
        $all=($q['type']??'')==='sale' ? [['id'=>1,'type'=>'sale','subtype'=>'receipt','number'=>'PA/2/10/2026'],['id'=>2,'type'=>'sale','subtype'=>'invoice','number'=>'OSS/5/09/2026'],['id'=>4,'type'=>'stock','subtype'=>'release','number'=>'WZ/1']]
            : (($q['type']??'')==='correct' ? [['id'=>3,'type'=>'correct','subtype'=>'correction','number'=>'KPA/1/10/2026']] : []);
        return ($rows=$page($all)) ? $json($rows) : $json(['message'=>'Nie znaleziono'],404);
    }
    if (preg_match('#^/operationdocuments/(\d+)$#',$path,$m)) {
        $id=(int)$m[1];
        $base=['id'=>$id,'issue_date'=>'2026-10-01','currency'=>'PLN','email'=>'musial@example.com','buyer_address'=>['name'=>'Natalia','surname'=>'Musiał','company_nip'=>'0','city'=>'Dopiewo','street'=>'Leśna','home_number'=>'76','postcode'=>'62-070'],
            'seller_data'=>['name'=>'ALTREO','nip'=>'1234567890','city'=>'Kraków'],'products'=>[['name'=>'Kalendarz A5','quantity'=>2,'price_gross'=>100,'price_gross_unit'=>50,'price_net_unit'=>40.65,'vat'=>23]]];
        $docs=[1=>['type'=>'sale','subtype'=>'receipt','number'=>'PA/2/10/2026','order_id'=>206,'total'=>100],2=>['type'=>'sale','subtype'=>'invoice','number'=>'OSS/5/09/2026','order_id'=>207,'total'=>100],
            3=>['type'=>'correct','subtype'=>'correction','number'=>'KPA/1/10/2026','order_id'=>206,'main_document_id'=>1,'total'=>-50]];
        return isset($docs[$id]) ? $json($docs[$id]+$base) : $json(['message'=>'gone'],404);
    }
    return $json(['message'=>'not mocked'],404);
};
SellasistArchiveService::$pauseMicro=0;
$service=new SellasistArchiveService($repo);

// Konfiguracja: walidacja konta, klucza i szyfrowanie.
check(SellasistArchiveService::normalizeAccount('altreo')==='altreo.sellasist.pl','Short account name');
check(SellasistArchiveService::normalizeAccount('https://Altreo.sellasist.pl/admin/orders')==='altreo.sellasist.pl','Account from URL');
rejects(function () { SellasistArchiveService::normalizeAccount('evil.example.com'); },'Only sellasist.pl hosts','sellasist.pl');
rejects(function () { SellasistArchiveService::normalizeAccount('a.b.sellasist.pl.evil.com'); },'No host suffix tricks');
rejects(function () use ($service) { $service->configure('altreo','bad-key'); },'Rejected key is reported','odrzucił');
check(!$service->configured(),'Nothing saved for a rejected key');
check($service->run(10)['skipped']==='not_configured','Import waits for configuration');
$service->configure('altreo','secret-key');
check($service->configured(),'Configured');
$stored=(string)$db->fetchColumn("SELECT value_json FROM t1_om_settings WHERE setting_key='sellasist_archive'");
check(strpos($stored,'secret-key')===false && strpos($stored,'…-key')!==false,'API key is encrypted, only a hint is visible');
$service->configure('altreo','');
check($service->configured(),'Empty key keeps the saved key');
rejects(function () use ($service) { $service->configure('bad host!',''); },'Invalid host rejected','sellasist');

// Pierwsza porcja z małym budżetem nie kończy wszystkiego; kolejne kontynuują bez duplikatów.
$first=$service->run(3,false);
check(empty($first['skipped']) && $first['requests']>0,'First batch ran');
$guard=0;
do { $report=$service->run(30,true); $guard++; } while (empty($report['done']) && $guard<40);
check(!empty($report['done']),'Import finishes in batches');
$stats=$repo->stats();
check($stats['orders']===230,'All orders stored once ('.$stats['orders'].')');
check($stats['orders_detailed']===229 && $stats['orders_failed']===1,'Details fetched; a missing order is marked failed');
check($stats['kinds']===['correct'=>3,'invoice'=>121,'receipt'=>6,'receipt_correct'=>1],'All available documents stored ('.json_encode($stats['kinds']).')');
check($stats['raw_kinds']['op_receipt']===1 && $stats['raw_kinds']['op_receipt_correct']===1 && !isset($stats['raw_kinds']['op_stock']),'Operation documents keep their own IDs; stock documents skipped');
check($stats['documents_detailed']===131,'Document details fetched');
$progress=$service->progress();
check($progress['completed'] && $progress['lists_done'] && strpos($progress['phases'][4]['error'],'Brak modułu')!==false,'Unavailable endpoint is reported without blocking the rest');
check($progress['details_percent']===100,'Progress is 100%');
check($progress['phases'][5]['stored']===2 && $progress['phases'][6]['stored']===1,'Operation document phases show their counts');

// Wyszukiwanie i filtry.
$found=$repo->orders(['q'=>'laptop dell']);
check($found['total']===1 && (int)$found['rows'][0]['sellasist_id']===42,'Search by product name');
check($repo->orders(['q'=>'SKU-42'])['total']===1,'Search by SKU (case-insensitive)');
check($repo->orders(['q'=>'600100007'])['total']===1,'Search by phone without spaces');
check($repo->orders(['q'=>'firma siedem'])['rows'][0]['nip']==='822-19-90-318','Search by company');
check($repo->orders(['q'=>'FV/7/2023'])['total']===1,'Search by invoice number from the order');
check($repo->orders(['q'=>'TRK99'])['total']===1,'Search by tracking number');
check($repo->orders(['q'=>'100'])['rows'][0]['sellasist_id']==100,'Numeric search matches the Sellasist number');
check($repo->orders(['q'=>'%'])['total']===0,'LIKE wildcards are escaped');
check($repo->orders(['status_id'=>'3'])['total']===77,'Status filter');
check($repo->orders(['source'=>'allegro'])['total']===115,'Source filter');
check($repo->orders(['payment_status'=>'unpaid'])['total']===46,'Payment filter');
check($repo->orders(['amount_from'=>'2000','amount_to'=>'2100,50'])['total']===10,'Amount filter');
check($repo->orders(['date_from'=>'2023-01-01','date_to'=>'2023-01-31'])['total']===19,'Date filter');
check($repo->orders(['document'=>'1'])['total']===127 && $repo->orders(['document'=>'0'])['total']===103,'Document filter');
$page=$repo->orders(['sort'=>'amount_desc','page'=>2]);
check($page['pages']===5 && $page['page']===2 && count($page['rows'])===50 && (int)$page['rows'][0]['sellasist_id']===180,'Pagination and sorting');
check(count($repo->order((int)$repo->orders(['q'=>'5'])['rows'][0]['id'])['documents'])===4,'Order shows its invoice and corrections');
check($repo->documents(['q'=>'8221990318'])['total']===1,'Document search by NIP');
check($repo->documents(['kind'=>'correct'])['total']===3 && $repo->documents(['kind'=>'receipt','q'=>'paragonowy'])['total']===5,'Document filters');
$opReceipt=$repo->documents(['kind'=>'receipt','q'=>'PA/2/10/2026'])['rows'];
check(count($opReceipt)===1 && $opReceipt[0]['kind']==='receipt' && $opReceipt[0]['buyer_name']==='Natalia Musiał' && $opReceipt[0]['total_cents']==10000 && (int)$opReceipt[0]['archive_order_id']>0,'Receipt from operation documents is filtered as a receipt and linked to its order');
$orderDocs=$repo->order((int)$opReceipt[0]['archive_order_id'])['documents'];
check(in_array('PA/2/10/2026',array_column($orderDocs,'number'),true) && in_array('receipt_correct',array_column($orderDocs,'kind'),true),'Order shows the receipt and its correction');
$receipt=$repo->documents(['kind'=>'receipt','sort'=>'oldest'])['rows'][0];
check((int)$receipt['archive_order_id']>0 && $receipt['number']==='PAR/1','Receipt detail fills its number and links to the order');

// Kolejny przebieg nie powtarza pobierania (listy sprawdzane co 15 min).
$before=count($calls);
$idle=$service->run(10);
check($idle["requests"]===0 && count($calls)===$before,'Completed archive is idle between checks');

// Limit API wstrzymuje import, ponowne pobranie szczegółów działa.
check($repo->requeueDetails(true)===1,'Failed record requeued');
$mode='limit';
$limited=$service->run(10,true);
check(strpos(implode(' ',$limited['errors']),'limit')!==false && $service->run(10,true)['skipped']==='backoff','429 pauses the import');
$mode='ok';
$service->setEnabled(true);
$service->run(10,true);
check($repo->stats()['orders_failed']===1,'Missing order stays failed after retry');
$service->setEnabled(false);
check($service->run(10)['skipped']==='disabled','Paused import does not run from cron');

// Reset nie dubluje danych.
$service->reset(); $guard=0;
do { $report=$service->run(30,true); $guard++; } while (empty($report['done']) && $guard<40);
check($repo->stats()['orders']===230 && $repo->stats()['documents']===131,'Restart does not duplicate records');

// Render widoków.
$smarty=App\Core\SmartyFactory::create();
$compile=sys_get_temp_dir().'/sc_archive_test_'.getmypid(); @mkdir($compile);
$smarty->setCompileDir($compile);
$orderRow=$repo->order((int)$repo->orders(['q'=>'laptop'])['rows'][0]['id']);
$controller=new ReflectionClass(App\Controllers\ArchiveController::class);
$view=$controller->getMethod('orderView');
$base=['tab'=>'orders','csrf'=>'x','canWrite'=>true,'progress'=>$service->progress(),'docKinds'=>SellasistArchiveRepository::DOC_KINDS,'flashSuccess'=>null,'flashError'=>null,'activeFilterCount'=>0,'listQuery'=>'q=a&b'];
$render=function (array $data) use ($smarty): string { $smarty->clearAllAssign(); $smarty->assign($data); return $smarty->fetch('archive/index.tpl'); };
$html=$render($base+['order'=>null,'view'=>[],'orderRaw'=>'','filters'=>['q'=>'<x>','status_id'=>'','source'=>'','payment_status'=>'','document'=>'','date_from'=>'','date_to'=>'','amount_from'=>'','amount_to'=>'','sort'=>'newest','page'=>1],'listing'=>$repo->orders(['q'=>'kowalski4']),'facets'=>$repo->facets()]);
check(strpos($html,'&lt;x&gt;')!==false && strpos($html,'Kowalski42')!==false && strpos($html,'FV/42/2023')!==false,'Order list renders');
$html=$render($base+['order'=>$orderRow,'view'=>$view->invoke(null,$orderRow['detail'],'PLN'),'orderRaw'=>'{}','filters'=>[],'listing'=>['rows'=>[]],'facets'=>[]]);
check(strpos($html,'Laptop Dell Latitude 5490')!==false && strpos($html,'Kurier InPost')!==false && strpos($html,'10.00 PLN')!==false && strpos($html,'Długa')!==false,'Order detail renders');
$html=$render(array_merge($base,['tab'=>'documents','order'=>null,'view'=>[],'orderRaw'=>'','filters'=>['q'=>'','kind'=>'','date_from'=>'','date_to'=>'','amount_from'=>'','amount_to'=>'','sort'=>'newest','page'=>1],'listing'=>$repo->documents([]),'facets'=>[]]));
check(strpos($html,'KOR/3/2023')!==false && strpos($html,'Faktura korygująca')!==false,'Documents render');
$html=$render(array_merge($base,['tab'=>'settings','order'=>null,'view'=>[],'orderRaw'=>'','filters'=>[],'listing'=>['rows'=>[]],'facets'=>[]]));
check(strpos($html,'altreo.sellasist.pl')!==false && strpos($html,'secret-key')===false,'Settings render without the key');
$doc=$repo->document((int)$repo->documents(['q'=>'FV/7/2023'])['rows'][0]['id']);
$smarty->clearAllAssign();
$smarty->assign(['document'=>$doc,'detail'=>array_filter($doc['detail'],'is_scalar'),'lines'=>[['name'=>'Produkt 7','quantity'=>2,'price_gross'=>12.3,'price_net'=>10.0,'vat'=>'23','discount'=>'','net'=>20.0,'gross'=>24.6]],'vatSummary'=>[['vat'=>'23','net'=>20.0,'gross'=>24.6]],'sum'=>['net'=>20,'gross'=>24.6],'kindLabel'=>'Faktura','seller'=>array_filter($doc['detail']['seller'],'is_scalar'),'buyer'=>array_filter($doc['detail']['buyer'],'is_scalar'),'raw'=>'{}']);
$html=$smarty->fetch('archive/document.tpl');
check(strpos($html,'Faktura FV/7/2023')!==false && strpos($html,'NIP: 8221990318')!==false && strpos($html,'24.60 PLN')!==false,'Document print renders');
$op=$repo->document((int)$opReceipt[0]['id']);
$opDetail=$controller->getMethod('operationDetail')->invoke(null,$op['detail']);
check($opDetail['lines'][0]['price_gross']===50.0 && $opDetail['lines'][0]['quantity']===2.0 && $opDetail['buyer']['name']==='Natalia Musiał' && !isset($opDetail['buyer']['nip']) && $opDetail['seller']['nip']==='1234567890','Operation document maps to the print layout');
array_map('unlink',glob($compile.'/*')?:[]); @rmdir($compile);

// Kurier bez tracking_number: nr nadania z pickup_code (nie dla paczkomatów); stare rekordy uzupełnia ensureSchema.
$courier=['id'=>52123,'is_parcel_locker'=>false,'tracking_number'=>'','shipment'=>['name'=>'Furgonetka Kurier Inpost','pickup_code'=>'GD-602735-C6-90'],'pickup_point'=>['code'=>'GD-602735-C6-90']];
check(SellasistArchiveRepository::orderRow($courier)['tracking']==='GD-602735-C6-90','Courier pickup_code is tracking');
check(SellasistArchiveRepository::orderRow(['is_parcel_locker'=>true,'shipment'=>['pickup_code'=>'WAW01M']])['tracking']==='','Parcel locker code is not tracking');
$repo->upsertOrder($courier,true);
$db->update('om_archive_orders',['tracking'=>''],'sellasist_id=:id',['id'=>52123]);
$db->delete('om_settings','setting_key=:k',['k'=>'sellasist_archive_tracking_v1']);
$repo->ensureSchema();
check($repo->orders(['q'=>'GD-602735-C6-90'])['rows'][0]['tracking']==='GD-602735-C6-90','Backfill fills tracking from stored detail');

// Numery paragonów nadpisane przez starą synchronizację (numer z zamówienia) wracają z zapisanych szczegółów.
$db->update('om_archive_documents',['number'=>'PA/946/08/2026'],"kind='receipt' AND remote_id=1");
$db->delete('om_settings','setting_key=:k',['k'=>'sellasist_archive_receipts_v2']);
$repo->ensureSchema();
check($repo->documents(['kind'=>'receipt','q'=>'PAR/1'])['rows'][0]['number']==='PAR/1' && $repo->documents(['q'=>'PA/946/08/2026'])['total']===0,'Overwritten receipt numbers are restored');

echo "OK archive_test: $checks checks\n";
