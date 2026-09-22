<?php

/** Isolated print-agent queue test. Never reads credentials, prints, or calls external APIs. */
declare(strict_types=1);

define('BASE_PATH',dirname(__DIR__));
require BASE_PATH.'/app/bootstrap.php';

use App\Core\Database;
use App\Models\OrderRepository;
use App\Models\PrintAgentRepository;

$checks=0;
function printCheck(bool $condition,string $label): void { global $checks; $checks++; if (!$condition) { throw new RuntimeException($label); } }

$reflection=new ReflectionClass(Database::class);
$db=$reflection->newInstanceWithoutConstructor();
$pdo=new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
foreach (['pdo'=>$pdo,'config'=>['driver'=>'sqlite']] as $name=>$value) { $property=$reflection->getProperty($name); $property->setValue($db,$value); }

App\Core\Tenant::activate(1);
$orders=new OrderRepository($db); $orders->ensureSchema();
$db->insert('om_shipments',['order_id'=>1,'carrier'=>'test','tracking'=>'TEST-1','weight'=>'1','state'=>'SUCCESS','created_at'=>gmdate('Y-m-d H:i:s')]);
$repository=new PrintAgentRepository($db); $repository->ensureSchema(); $repository->ensureSchema();

$token=$repository->createStation('Pakowanie 1');
$station=$repository->authenticate($token);
printCheck(is_array($station) && $station['name']==='Pakowanie 1','Station token authenticates');
printCheck($repository->authenticate('wrong-token')===null,'Wrong station token rejected');
printCheck(strpos($token,'t1.')===0 && App\Core\Tenant::idFromToken($token)===1 && App\Core\Tenant::idFromToken('x1.abc')===null,'Station token carries its company id');
App\Core\Tenant::activate(2);
(new PrintAgentRepository($db))->ensureSchema();
printCheck((new PrintAgentRepository($db))->authenticate($token)===null,'Station token of company 1 is rejected in company 2');
App\Core\Tenant::activate(1);

$heartbeat=$repository->heartbeat((int)$station['id'],['printers'=>['Zebra ZD421','Biuro A4','Zebra ZD421'],'defaultPrinter'=>'Zebra ZD421','agentVersion'=>'1.0.0','fiscalPrinters'=>[['deviceKey'=>'tcp:192.168.1.45:9100','name'=>'Posnet Trio','host'=>'192.168.1.45','port'=>9100]]],'HOST-1');
printCheck($heartbeat['printers']===2,'Heartbeat stores a deduplicated printer inventory');
$stations=$repository->stations();
printCheck($stations[0]['printers']===['Biuro A4','Zebra ZD421'] && $stations[0]['online'],'Panel receives sorted online printers');

$originalTimezone=date_default_timezone_get();
date_default_timezone_set('Europe/Warsaw');
$jobId=$repository->queueShipmentLabel(1,(int)$station['id'],'Zebra ZD421',100,150,'tester','https://example.test/print-agent-api.php');
$stored=$db->fetch('SELECT * FROM print_agent_jobs WHERE id=:id',['id'=>$jobId]);
printCheck(strpos((string)$stored['pdf_url'],'token=')===false && $stored['download_token_hash']===str_repeat('0',64),'Plain download token is not stored');
printCheck($stored['print_settings']==='fit,paper=100mm x 150mm','Custom 10x15 cm paper dimensions are stored');
$job=$repository->nextJob((int)$station['id']);
printCheck($job!==null && $job['printerName']==='Zebra ZD421' && strpos($job['pdfUrl'],'&token=')!==false,'Agent atomically receives printer and short-lived PDF URL');
printCheck($repository->nextJob((int)$station['id'])===null,'Claimed job cannot be claimed twice');
parse_str((string)parse_url($job['pdfUrl'],PHP_URL_QUERY),$query);
printCheck($repository->downloadableJob($jobId,(string)($query['token']??''))!==null,'Signed PDF token authorizes claimed job');
printCheck($repository->downloadableJob($jobId,'bad')===null,'Invalid PDF token rejected');
date_default_timezone_set($originalTimezone);
printCheck($repository->report((int)$station['id'],$jobId,'printed','Przekazano do spoolera.'),'Final status accepted');
printCheck($repository->downloadableJob($jobId,(string)$query['token'])===null,'PDF link closes after final status');

$db->insert('om_shipments',['order_id'=>1,'carrier'=>'test','tracking'=>'TEST-2','weight'=>'1','state'=>'SUCCESS','created_at'=>gmdate('Y-m-d H:i:s')]);
$newest=$repository->queueOrderLabels(1,'newest',(int)$station['id'],'Zebra ZD421',100,150,'tester','https://example.test/print-agent-api.php');
printCheck(count($newest)===1,'Newest-label action queues exactly one shipment');
$all=$repository->queueOrderLabels(1,'all',(int)$station['id'],'Zebra ZD421',100,150,'tester','https://example.test/print-agent-api.php');
printCheck(count($all)===2,'All-labels action queues every active shipment');

$fiscalPrinter=$repository->fiscalPrinters()[0]??null;
printCheck(is_array($fiscalPrinter) && $fiscalPrinter['name']==='Posnet Trio','Heartbeat discovers a Posnet network printer');
$repository->configureFiscalPrinter((int)$fiscalPrinter['id'],'POS1','sandbox',true);
$db->insert('om_orders',['account_id'=>1,'external_id'=>'ORDER-1','remote_status'=>'new','status_id'=>1,'status_manual'=>0,'ordered_at'=>gmdate('Y-m-d H:i:s'),'buyer_name'=>'Test','email'=>'','phone'=>'','total_cents'=>12300,'currency'=>'PLN','paid'=>1,'details_json'=>json_encode(['items'=>[['name'=>'Produkt','quantity'=>1,'unit_cents'=>12300,'vat'=>'23']]]),'note'=>'','tags'=>'','imported_at'=>gmdate('Y-m-d H:i:s'),'updated_at'=>gmdate('Y-m-d H:i:s')]);
$documentId=$db->insert('om_documents',['order_id'=>1,'series_id'=>1,'kind'=>'receipt','number'=>'PAR/1','parent_id'=>null,'request_key'=>str_repeat('a',40),'snapshot_json'=>json_encode(['items'=>[['name'=>'Produkt z paragonu','quantity'=>1,'unit_cents'=>12300,'vat'=>'23']]]),'created_at'=>gmdate('Y-m-d H:i:s')]);
$fiscalJobId=$repository->queueFiscalReceipt(1,(int)$fiscalPrinter['id'],'tester',(int)$documentId);
$fiscalJob=$repository->nextFiscalJob((int)$station['id'],'sandbox');
printCheck($fiscalJob['id']===$fiscalJobId && $fiscalJob['localNumber']==='POS1/'.gmdate('Y').'/1','Fiscal receipt uses the selected printer series');
printCheck($fiscalJob['receipt']['orderNumber']==='PAR/1' && $fiscalJob['receipt']['items'][0]['name']==='Produkt z paragonu','Printer receives immutable issued document snapshot');
printCheck($fiscalJob['receipt']['paymentType']===6 && $fiscalJob['receipt']['paymentName']==='Płatność online','Fiscal receipt carries its payment form');
printCheck($repository->reportFiscal((int)$station['id'],$fiscalJobId,'printed','Sandbox OK','SANDBOX-'.$fiscalJob['localNumber']),'Fiscal result and number are accepted');
try { $repository->queueFiscalReceipt(1,(int)$fiscalPrinter['id'],'tester'); printCheck(false,'Duplicate fiscal receipt rejected'); }
catch (InvalidArgumentException $e) { printCheck(true,'Duplicate fiscal receipt rejected'); }

$db->insert('om_orders',['account_id'=>1,'external_id'=>'ORDER-NIP','remote_status'=>'new','status_id'=>1,'status_manual'=>0,'ordered_at'=>gmdate('Y-m-d H:i:s'),'buyer_name'=>'Firma','email'=>'','phone'=>'','total_cents'=>45000,'currency'=>'PLN','paid'=>1,'details_json'=>json_encode(['invoice_form'=>['company'=>'Firma','nip'=>'PL 526-025-02-74'],'items'=>[['name'=>'Produkt','quantity'=>1,'unit_cents'=>45000,'vat'=>'23']]]),'note'=>'','tags'=>'','imported_at'=>gmdate('Y-m-d H:i:s'),'updated_at'=>gmdate('Y-m-d H:i:s')]);
$nipOrderId=(int)$db->fetchColumn("SELECT id FROM om_orders WHERE external_id='ORDER-NIP'");
$repository->queueFiscalReceipt($nipOrderId,(int)$fiscalPrinter['id'],'tester');
$nipJob=$repository->nextFiscalJob((int)$station['id'],'sandbox');
printCheck(($nipJob['receipt']['buyerNip']??null)==='5260250274','Fiscal receipt carries buyer NIP from the order');
printCheck(!array_key_exists('buyerNip',$fiscalJob['receipt']),'Fiscal receipt without NIP has no buyerNip');
$db->insert('om_orders',['account_id'=>1,'external_id'=>'ORDER-NIP-BIG','remote_status'=>'new','status_id'=>1,'status_manual'=>0,'ordered_at'=>gmdate('Y-m-d H:i:s'),'buyer_name'=>'Firma','email'=>'','phone'=>'','total_cents'=>45001,'currency'=>'PLN','paid'=>1,'details_json'=>json_encode(['invoice_form'=>['company'=>'Firma','nip'=>'5260250274'],'items'=>[['name'=>'Produkt','quantity'=>1,'unit_cents'=>45001,'vat'=>'23']]]),'note'=>'','tags'=>'','imported_at'=>gmdate('Y-m-d H:i:s'),'updated_at'=>gmdate('Y-m-d H:i:s')]);
try { $repository->queueFiscalReceipt((int)$db->fetchColumn("SELECT id FROM om_orders WHERE external_id='ORDER-NIP-BIG'"),(int)$fiscalPrinter['id'],'tester'); printCheck(false,'Fiscal receipt with NIP above 450 PLN rejected'); }
catch (InvalidArgumentException $e) { printCheck(strpos($e->getMessage(),'450 zł')!==false,'Fiscal receipt with NIP above 450 PLN rejected'); }

$newToken=$repository->regenerateToken((int)$station['id']);
printCheck($repository->authenticate($token)===null && $repository->authenticate($newToken)!==null,'Token rotation invalidates the old token');

$deleteToken=$repository->createStation('Do usunięcia');
$deleteStation=$repository->authenticate($deleteToken);
$repository->deleteStation((int)$deleteStation['id']);
printCheck($repository->authenticate($deleteToken)===null,'Deleting a station revokes its token');

echo 'OK: '.$checks." print-agent checks; no network, printer, or production database used.\n";
