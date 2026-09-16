<?php
/** Isolated in-memory database. Never reads credentials or calls marketplace APIs. */
declare(strict_types=1);
define('BASE_PATH',dirname(__DIR__));
require BASE_PATH.'/app/bootstrap.php';
use App\Core\Database;
use App\Models\OrderRepository;
use App\Services\OrderNormalizer;
use App\Services\OrderDocumentService;

$checks=0;
function check(bool $condition,string $label): void { global $checks; $checks++; if (!$condition) { throw new RuntimeException($label); } }
function rejects(callable $fn,string $label): void { try { $fn(); } catch (\Throwable $e) { check(true,$label); return; } check(false,$label); }
$reflection=new ReflectionClass(Database::class);
$db=$reflection->newInstanceWithoutConstructor();
$pdo=new PDO('sqlite::memory:'); $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
foreach (['pdo'=>$pdo,'config'=>['driver'=>'sqlite']] as $name=>$value) { $property=$reflection->getProperty($name); $property->setValue($db,$value); }
$repo=new OrderRepository($db); $repo->ensureSchema(); $repo->ensureSchema();
check(count($repo->statuses())===6,'Schema idempotency');
check(in_array('fiscal_printer_id',array_column($db->fetchAll('PRAGMA table_info(om_series)'),'name'),true),'Series printer column exists');
check(array_column($repo->paymentMethods(),'name')===['Przelew','Płatność przy odbiorze'] && (int)$repo->paymentMethods()[1]['is_cod']===1,'Default payment methods seeded');
$repo->registerAccount('allegro',1,'Sklep A'); $repo->registerAccount('allegro',2,'Sklep B'); $repo->registerAccount('erli',1,'Sklep C');
$repo->registerAccount('allegro',1,'Sklep A');
check(count($repo->accounts())===3,'Account dedupe');
$now=strtotime('2026-09-08T12:00:00Z'); $cutoff=$now-7*86400;
$raw=['id'=>'same-ID','status'=>'READY_FOR_PROCESSING','buyer'=>['email'=>'test@example.invalid','firstName'=>'Anna','lastName'=>'Testowa'],'payment'=>['finishedAt'=>'2026-09-08T10:00:00Z'],'delivery'=>['address'=>['firstName'=>'Anna','lastName'=>'Testowa','street'=>'Testowa 12/3','zipCode'=>'00-001','city'=>'Warszawa','countryCode'=>'PL','phoneNumber'=>'+48123123123']],'invoice'=>['required'=>true,'address'=>['street'=>'Firmowa 8','zipCode'=>'00-002','city'=>'Warszawa','countryCode'=>'PL','company'=>['name'=>'Firma Test','taxId'=>'5252674798']]],'lineItems'=>[['boughtAt'=>'2026-09-08T10:00:00Z','offer'=>['id'=>'offer-1','name'=>'Produkt testowy','external'=>['id'=>'SKU-01'],'primaryImage'=>['url'=>'https://img.example.invalid/test.jpg']],'quantity'=>2,'price'=>['amount'=>'12.30']]],'summary'=>['totalToPay'=>['amount'=>'24.60','currency'=>'PLN']]];
$order=OrderNormalizer::normalize('allegro',$raw,$cutoff,$now);
check($order['total_cents']===2460 && $order['paid']===1,'Allegro monetary normalization');
check($order['details']['items'][0]['image_url']==='https://img.example.invalid/test.jpg','Order product image normalization');
check($order['details']['document_preference']==='invoice' && $order['details']['invoice_required']===1,'Allegro invoice preference normalization');
$cod=$raw;
$cod['id']='cod-ID';
$cod['payment']['type']='CASH_ON_DELIVERY';
$cod['delivery']['method']['name']='Kurier DPD pobranie';
$codOrder=OrderNormalizer::normalize('allegro',$cod,$cutoff,$now);
check($codOrder['paid']===0 && $codOrder['details']['cash_on_delivery']===1 && $codOrder['details']['amount_paid_cents']===0,'Allegro COD is never marked paid from finishedAt');
$allegroImageService=(new ReflectionClass(App\Services\AllegroService::class))->newInstanceWithoutConstructor();
$allegroImageStorage=new class { public function findOfferByAccountAndOfferId(int $accountId,string $offerId): array { return ['primary_image_url'=>'https://img.example.invalid/allegro-cache.jpg']; } };
$allegroImageProperty=(new ReflectionClass($allegroImageService))->getProperty('storage');$allegroImageProperty->setValue($allegroImageService,$allegroImageStorage);
$enrichedAllegro=$allegroImageService->enrichOrderImages(['id'=>1],['lineItems'=>[['offer'=>['id'=>'123']]]]);
check($enrichedAllegro['lineItems'][0]['imageUrl']==='https://img.example.invalid/allegro-cache.jpg','Allegro order image enrichment');
$erliImageService=(new ReflectionClass(App\Services\ErliService::class))->newInstanceWithoutConstructor();
$erliImageStorage=new class { public function findProductForOrder(int $accountId,string $externalId,string $sku=''): array { return ['primary_image_url'=>'https://img.example.invalid/erli-cache.jpg']; } };
$erliImageProperty=(new ReflectionClass($erliImageService))->getProperty('storage');$erliImageProperty->setValue($erliImageService,$erliImageStorage);
$enrichedErli=$erliImageService->enrichOrderImages(['id'=>1],['items'=>[['externalId'=>'ABC-1']]]);
check($enrichedErli['items'][0]['imageUrl']==='https://img.example.invalid/erli-cache.jpg','ERLI order image enrichment');
$empikImageService=(new ReflectionClass(App\Services\EmpikService::class))->newInstanceWithoutConstructor();
$empikImageStorage=new class { public function findOfferForOrder(int $accountId,string $shopSku='',string $productSku='',string $productId=''): array { return ['offer_json'=>'{"product":{"medias":[{"media_url":"https://img.example.invalid/empik-cache.jpg"}]}}']; } };
$empikImageProperty=(new ReflectionClass($empikImageService))->getProperty('storage');$empikImageProperty->setValue($empikImageService,$empikImageStorage);
$enrichedEmpik=$empikImageService->enrichOrderImages(['id'=>1],['order_lines'=>[['offer_sku'=>'EMP-1','product_sku'=>'P-1']]]);
check($enrichedEmpik['order_lines'][0]['image_url']==='https://img.example.invalid/empik-cache.jpg','Empik order image enrichment');
$old=$raw; $old['lineItems'][0]['boughtAt']='2026-09-01T11:59:59Z';
check(OrderNormalizer::normalize('allegro',$old,$cutoff,$now)===null,'Reject order older than 7 days');
$old['lineItems'][0]['boughtAt']='2026-09-01T12:00:00Z';
check(OrderNormalizer::normalize('allegro',$old,$cutoff,$now)!==null,'Include exact cutoff');
$old['lineItems'][0]['boughtAt']='2026-09-09T12:00:00Z';
check(OrderNormalizer::normalize('allegro',$old,$cutoff,$now)===null,'Reject future order');
$missing=$raw;unset($missing['lineItems'][0]['boughtAt']);rejects(fn()=>OrderNormalizer::normalize('allegro',$missing,$cutoff,$now),'Missing date');
check($repo->import(1,$order),'Initial import'); check(!$repo->import(1,$order),'Duplicate updates'); check($repo->import(2,$order),'Same external ID, distinct account');
check($repo->dashboard()['total']===2,'Uniqueness enforced');
$canonical=$repo->order(1);
check($canonical['shipping_address']['postal_code']==='00-001' && $canonical['shipping_address']['street']==='Testowa' && $canonical['shipping_address']['building']==='12/3','Allegro delivery address canonical fields');
check($canonical['details']['invoice_form']['company']==='Firma Test' && $canonical['details']['invoice_form']['nip']==='5252674798' && $canonical['details']['invoice_form']['postal_code']==='00-002','Allegro invoice address canonical fields');
$db->insert('om_mappings',['account_id'=>1,'remote_status'=>'READY_FOR_PROCESSING','status_id'=>2]);
$repo->import(1,$order); check((int)$repo->order(1)['status_id']===2,'Map status');
$db->transaction(fn()=>$repo->changeStatus(1,4,'test')); $repo->import(1,$order);
check((int)$repo->order(1)['status_id']===4,'Manual status preserved');
$db->insert('om_rules',['name'=>'Opłacone','trigger_name'=>'import','conditions_json'=>'{"paid":1}','actions_json'=>'{"tag":"pilne","note":"TEST"}']);
$repo->import(1,$order); $repo->import(1,$order);
check((int)$db->fetchColumn('SELECT COUNT(*) FROM om_rule_runs')===1,'Import rule executed once');
check($repo->order(1)['tags']==='pilne','Automation tag');
check($repo->runRules(2,'import','preview',true)===['Opłacone'],'Rule preview');
check((int)$db->fetchColumn('SELECT COUNT(*) FROM om_rule_runs')===1,'Preview has no side effects');
$repo->updateOrderDetails(1,['buyer_name'=>'Anna Edytowana','email'=>'test@example.invalid','phone'=>'+48123123123','currency'=>'PLN','total'=>'24.60','amount_paid'=>'0.00','shipping_price'=>'0.00','payment_method'=>'Pobranie','cash_on_delivery'=>'1','document_preference'=>'invoice','delivery'=>'Kurier pobranie','pickup'=>'','shipping_street'=>'Testowa','shipping_building'=>'1','shipping_postal_code'=>'00-001','shipping_city'=>'Warszawa','shipping_country'=>'PL','invoice_name'=>'Anna Edytowana','invoice_company'=>'','invoice_nip'=>'','invoice_street'=>'Testowa','invoice_building'=>'1','invoice_postal_code'=>'00-001','invoice_city'=>'Warszawa','invoice_country'=>'PL','buyer_note'=>'Test ręcznej edycji','items'=>[['name'=>'Produkt testowy','sku'=>'SKU-01','quantity'=>2,'price'=>'12.30','vat'=>'23']]],'test');
$repo->import(1,$order); $edited=$repo->order(1);
check($edited['buyer_name']==='Anna Edytowana' && $edited['details']['delivery']==='Kurier pobranie','Manual order details preserved after sync');
check($edited['details']['cash_on_delivery']===1 && $edited['details']['amount_due_cents']===2460,'COD and amount due derived');
$legacyDetailsJson=(string)$db->fetchColumn('SELECT details_json FROM om_orders WHERE id=1');
$legacyDetails=json_decode($legacyDetailsJson,true);$legacyDetails['payment_method']='Nieustalona';$legacyDetails['cash_on_delivery']=1;
$db->update('om_orders',['details_json'=>OrderRepository::json($legacyDetails)],'id=:id',['id'=>1]);
check($repo->order(1)['details']['payment_method']==='Płatność przy odbiorze','Legacy unresolved payment name corrected for COD display');
$db->update('om_orders',['details_json'=>$legacyDetailsJson],'id=:id',['id'=>1]);
check($edited['details']['document_preference']==='invoice','Manual document preference preserved after sync');
check($repo->listing(['q'=>'test@example','account_id'=>1])['total']===1,'Search with account filter');
check($repo->listing(['q'=>"' OR 1=1 --"])['total']===0,'SQL injection search');
$erli=['id'=>'e1','created'=>'2026-09-08T10:00:00Z','status'=>'purchased','totalPrice'=>1230,'currency'=>'PLN','items'=>[['name'=>'E','quantity'=>1,'unitPrice'=>1230,'taxRate'=>'TAX_23']]];
$en=OrderNormalizer::normalize('erli',$erli,$cutoff,$now); check($en['total_cents']===1230 && $en['details']['items'][0]['vat']==='23','Erli cents and VAT');
$repo->import(3,$en);
check($repo->listing(['platform'=>'erli'])['total']===1,'Filter by marketplace');
check($repo->listing(['amount_to'=>'12,30'])['total']===1,'Filter by maximum amount with Polish decimal separator');
check($repo->listing(['date_from'=>'2026-09-09'])['total']===0,'Filter by date range');
check($repo->listing(['sort'=>'amount_asc'])['rows'][0]['external_id']==='e1','Sort by amount');
$mirakl=['order_id'=>'m1','created_date'=>'2026-09-08T10:00:00Z','total_price'=>12.30,'currency_iso_code'=>'PLN','payment_type'=>'CARD','order_lines'=>[['product_title'=>'M','offer_sku'=>'EMP-1','product_sku'=>'P-1','product_id'=>'EAN-1','image_url'=>'https://img.example.invalid/empik.jpg','quantity'=>1,'price_unit'=>12.30]]];
$miraklOrder=OrderNormalizer::normalize('empik',$mirakl,$cutoff,$now);
check($miraklOrder['total_cents']===1230,'Mirakl money');
check($miraklOrder['details']['items'][0]['image_url']==='https://img.example.invalid/empik.jpg' && $miraklOrder['details']['items'][0]['product_sku']==='P-1','Empik image and identifiers normalization');
check($miraklOrder['details']['source_payment_method']==='CARD','Marketplace payment method preserved');
$repo->registerAccount('empik',7,'Empik test');
$empikAccountId=(int)$db->fetchColumn('SELECT id FROM om_accounts WHERE platform=:platform AND source_id=:source',['platform'=>'empik','source'=>7]);
$repo->import($empikAccountId,$miraklOrder);
check(count(array_filter($repo->paymentSources(),fn($source)=>$source['platform']==='empik' && $source['source_method']==='CARD'))===1,'Payment source discovered from stored orders');
$empikOrderId=(int)$db->fetchColumn('SELECT id FROM om_orders WHERE account_id=:account AND external_id=:external',['account'=>$empikAccountId,'external'=>'m1']);
check($repo->savePaymentMapping('empik','CARD',1)===1 && $repo->order($empikOrderId)['details']['payment_method']==='Przelew','Payment mapping updates existing order');
$miraklCod=$mirakl;
$miraklCod['order_id']='m-cod';
$miraklCod['shipping_type_label']='Kurier - płatność za pobraniem';
$miraklCod['payment_status']='UNPAID';
$miraklCod['customer_notification_email']='relay@example.invalid';
$miraklCod['customer']=['shipping_address'=>['firstname'=>'Ewelina','lastname'=>'Testowa','street_1'=>'Ul.: Testowa','street_2'=>'37','zip_code'=>'23-204','city'=>'Kraśnik','country_iso_code'=>'POL','phone'=>'+48123123123']];
$miraklCodOrder=OrderNormalizer::normalize('empik',$miraklCod,$cutoff,$now);
check($miraklCodOrder['details']['cash_on_delivery']===1 && $miraklCodOrder['paid']===0,'Mirakl COD recognized from delivery label');
check($miraklCodOrder['email']==='relay@example.invalid' && $miraklCodOrder['phone']==='+48123123123','Mirakl customer contact normalization');
check($miraklCodOrder['details']['address']['street']==='Testowa' && $miraklCodOrder['details']['address']['buildingNumber']==='37' && $miraklCodOrder['details']['address']['zip']==='23-204' && $miraklCodOrder['details']['address']['country']==='PL','Mirakl delivery address normalization');
check($repo->savePaymentMapping('allegro','',1)===1 && $repo->order(1)['details']['payment_method']==='Pobranie','Payment mapping preserves manually edited order');
rejects(fn()=>OrderNormalizer::money('1e3'),'Reject scientific money'); rejects(fn()=>OrderNormalizer::money('12.345'),'Reject subcent money');
$calc=OrderDocumentService::calculate([['name'=>'Test','quantity'=>2,'price'=>'12.30','vat'=>'23']]);
check($calc['net_cents']===2000 && $calc['tax_cents']===460 && $calc['gross_cents']===2460,'VAT calculation');
$discounted=OrderDocumentService::calculate([['name'=>'Towar','quantity'=>1,'price'=>'100.00','vat'=>'23'],['name'=>'Rabat','quantity'=>1,'price'=>'-10.00','vat'=>'23']]);
check($discounted['gross_cents']===9000,'Negative adjustment supported for marketplace discounts');
rejects(fn()=>OrderDocumentService::calculate([['name'=>'X','quantity'=>1,'price'=>'10.00','vat'=>'']]),'No assumed VAT');
$repo->saveSetting('seller',['name'=>'Firma testowa','address'=>'Testowa 1, Warszawa','nip'=>'TEST','bank'=>'']);
$db->insert('om_series',['name'=>'Faktury','kind'=>'invoice','pattern'=>'FV/{YYYY}/{N}','next_number'=>1]);
$db->insert('om_series',['name'=>'Korekty','kind'=>'invoice_correction','pattern'=>'KOR/{YYYY}/{N}','next_number'=>1]);
$docService=new OrderDocumentService($repo);
$input=['series_id'=>1,'request_key'=>str_repeat('a',40),'buyer'=>'Anna Testowa','items'=>[['name'=>'Test','quantity'=>2,'price'=>'12.30','vat'=>'23']]];
$docId=$docService->issue(1,$input,'test');check($docId===1,'Issue invoice');
check($docService->issue(1,$input,'test')===$docId,'Document idempotency');
check((int)$db->fetchColumn('SELECT next_number FROM om_series WHERE id=1')===2,'No skipped sequence on retry');
$cor=$input; $cor['series_id']=2;$cor['parent_id']=1;$cor['reason']='Zwrot jednej sztuki';$cor['request_key']=str_repeat('b',40);$cor['items'][0]['quantity']=1;
$corId=$docService->issue(1,$cor,'test');$snapshot=json_decode($db->fetchColumn('SELECT snapshot_json FROM om_documents WHERE id=2'),true);
check($snapshot['difference_cents']===-1230,'Correction delta');
$cor['request_key']=str_repeat('c',40);$cor['items'][0]['quantity']=0;$docService->issue(1,$cor,'test');
$snapshot=json_decode($db->fetchColumn('SELECT snapshot_json FROM om_documents WHERE id=3'),true);check($snapshot['difference_cents']===-1230,'Chained correction compares latest state');
$repo->saveSetting('seller',['name'=>'Zmieniona firma']);$original=json_decode($db->fetchColumn('SELECT snapshot_json FROM om_documents WHERE id=1'),true);check($original['seller']['name']==='Firma testowa','Immutable document snapshot');
$cor['parent_id']=999;$cor['request_key']=str_repeat('d',40);rejects(fn()=>$docService->issue(1,$cor,'test'),'Reject invalid correction parent');
check((int)$db->fetchColumn('SELECT next_number FROM om_series WHERE id=2')===3,'Failure rolls sequence back');

$secret=['token'=>'test-secret-not-for-network'];
$encrypted=App\Services\OrderSecretBox::encrypt($secret);
check(strpos($encrypted,$secret['token'])===false && App\Services\OrderSecretBox::decrypt($encrypted)===$secret,'Carrier credentials encrypted');
$authError=App\Services\OrderSyncError::describe(new RuntimeException('Allegro API error [401]: Unauthorized'));
check($authError['code']==='MARKETPLACE_AUTH','Allegro auth error classified');
$permissionError=App\Services\OrderSyncError::describe(new RuntimeException('Allegro API error [403]: Forbidden'));
check($permissionError['code']==='MARKETPLACE_PERMISSION' && strpos($permissionError['message'],'Forbidden')!==false,'Allegro permission error classified');
$uaError=App\Services\OrderSyncError::describe(new RuntimeException('Allegro API error [403] {AccessDenied}: Invalid User-Agent header'));
check($uaError['code']==='ALLEGRO_USER_AGENT_REJECTED','Allegro User-Agent rejection classified');
$shipmentError=App\Services\OrderSyncError::describeShipment(new RuntimeException('Allegro API error [400] {VALIDATION_ERROR}: Package dimensions are invalid'));
check($shipmentError['code']==='SHIPMENT_REQUEST' && strpos($shipmentError['message'],'Package dimensions are invalid')!==false && strpos($shipmentError['message'],'kursor')===false,'Shipment API error uses shipment-specific reason');
$shipmentPresentation=App\Services\OrderShipmentService::presentation(['carrier'=>'Wysyłam z Allegro TEST','carrier_provider'=>'allegro_wza','state'=>'SUCCESS','payload_json'=>json_encode(['provider'=>'allegro_wza','meta'=>['delivery_method'=>'Allegro Paczkomaty InPost','carrier_name'=>'InPost','service_name'=>'InPost Paczkomat 24/7','service_code'=>'service-1','tracking_status'=>'IN_TRANSIT','tracking_updated_at'=>'2026-09-10T08:00:00Z']])],$repo->order(1));
check($shipmentPresentation['carrier']==='InPost' && $shipmentPresentation['service']==='InPost Paczkomat 24/7','Shipment carrier and service presentation');
check($shipmentPresentation['status_label']==='W drodze' && $shipmentPresentation['status_tone']==='transit','Carrier tracking status presentation');
$legacyShipmentPresentation=App\Services\OrderShipmentService::presentation(['carrier'=>'Wysyłam z Allegro TEST','carrier_provider'=>'allegro_wza','state'=>'SUCCESS','payload_json'=>'{}'],$repo->order(1));
check($legacyShipmentPresentation['status_label']==='Odśwież, aby pobrać status','Legacy Allegro shipment does not mislabel command success as carrier status');

$allegroReflection=new ReflectionClass(App\Services\AllegroService::class);
$allegroTransport=$allegroReflection->newInstanceWithoutConstructor();
$allegroConfig=$allegroReflection->getProperty('config');
$allegroConfig->setValue($allegroTransport,['application_name'=>'accra_shop magazyn nowy','application_version'=>'2026.09.08','documentation_url'=>'https://magazyn.altreo.pl/crm/new_version/allegro-app-info.php']);
check($allegroTransport->userAgent()==='accra_shop-magazyn-nowy/2026.09.08 (+https://magazyn.altreo.pl/crm/new_version/allegro-app-info.php)','Allegro User-Agent format');
$legacyUserAgent=$allegroTransport->userAgent(['application_name'=>'Accra_shop Magazyn']);
check($legacyUserAgent==='Accra_shop-Magazyn/2026.09.08 (+https://magazyn.altreo.pl/crm/new_version/allegro-app-info.php)','Allegro User-Agent follows account application');
$allegroHeaders=$allegroReflection->getMethod('headersWithUserAgent')->invoke($allegroTransport,['Accept: application/json','User-Agent: curl/8']);
check(count(array_filter($allegroHeaders,fn($header)=>stripos($header,'User-Agent:')===0))===1 && end($allegroHeaders)==='User-Agent: accra_shop-magazyn-nowy/2026.09.08 (+https://magazyn.altreo.pl/crm/new_version/allegro-app-info.php)','Allegro User-Agent injected once');
$allegroConfig->setValue($allegroTransport,['application_name'=>'Bad/name','application_version'=>'1.0.0','documentation_url'=>'https://altreo.pl/']);
rejects(fn()=>$allegroTransport->userAgent(),'Reject invalid Allegro application name');

// Read-only transport stubs exercise resumable import and failure recovery without APIs.
$sync = new App\Services\OrderSyncService($repo);
$stub = new class {
    public $pages=[]; public $calls=[]; public $fail=false;
    public function listAccounts(): array { return [['id'=>1,'is_active'=>1,'name'=>'A'],['id'=>2,'is_active'=>1,'name'=>'B']]; }
    public function readOrderPage(array $account,string $from,string $to,string $cursor='',string $updatedFrom=''): array {
        $this->calls[]=compact('from','to','cursor','updatedFrom');
        if ($this->fail) throw new RuntimeException('HTTP 429 secret upstream body');
        return array_shift($this->pages) ?? ['checkoutForms'=>[]];
    }
};
$syncRef = new ReflectionClass($sync); $syncRef->getProperty('services')->setValue($sync,['allegro'=>$stub]);
$live=$raw; $live['id']='live'; $live['lineItems'][0]['boughtAt']=gmdate('c',time()-60);
$tooOld=$live; $tooOld['id']='too-old'; $tooOld['lineItems'][0]['boughtAt']=gmdate('c',time()-7*86400-60);
$stub->pages=[['checkoutForms'=>[$live,$tooOld]],['checkoutForms'=>[]]];
$result=$sync->sync(true,1);check($result[0]['added']===1 && $result[0]['skipped']===1,'Sync enforces date admission');
$account=$db->fetch('SELECT * FROM om_accounts WHERE id=1');check($account['cursor_json']!==null && $account['synced_until']===null,'Page cursor persisted, watermark not advanced early');
$firstCursor=$account['cursor_json'];$stub->fail=true;$result=$sync->sync(true,1);
check(!empty($result[0]['error']) && strpos($result[0]['message'],'secret')===false,'Upstream error sanitized');
check($db->fetchColumn('SELECT cursor_json FROM om_accounts WHERE id=1')===$firstCursor,'Failure keeps cursor');
$stub->fail=false;$result=$sync->sync(true,1);check(!$result[0]['more'],'Empty page completes sweep');
$account=$db->fetch('SELECT * FROM om_accounts WHERE id=1');check($account['cursor_json']===null && $account['synced_until']!==null,'Successful sweep advances watermark');
$db->update('om_accounts',['enabled'=>1],'id=:id',['id'=>1]);
$stub->pages=[['checkoutForms'=>[$live],'totalCount'=>1]];$result=$sync->sync(false,1);
check($result[0]['added']===0 && $result[0]['updated']===1,'Overlap does not duplicate');
$lastCall=end($stub->calls);check(strtotime($lastCall['updatedFrom'])>=time()-310,'Incremental overlap is five minutes');
$stub->pages=[['checkoutForms'=>[],'totalCount'=>10001]];
$result=$sync->sync(true,1);$split=json_decode($db->fetchColumn('SELECT cursor_json FROM om_accounts WHERE id=1'),true);
check($result[0]['more'] && $split['to']<$split['horizon'],'Large Allegro window split before offset limit');
$stub->pages=[['checkoutForms'=>[],'totalCount'=>0]];$sync->sync(true,1);
$nextWindow=json_decode($db->fetchColumn('SELECT cursor_json FROM om_accounts WHERE id=1'),true);
check($nextWindow['updated_from']===$split['to'] && $nextWindow['to']===$split['horizon'],'Split window resumes remaining range');
$db->update('om_accounts',['enabled'=>0],'id=:id',['id'=>1]);check($sync->sync(false,1)===[],'Disabled account not polled');

// Compile and render all Smarty branches, with synthetic records only.
$smarty=App\Core\SmartyFactory::create();
$smarty->setCompileDir(sys_get_temp_dir().'/om-smarty-test');
$smarty->assign(['csrf'=>'test','canWrite'=>true,'flashSuccess'=>null,'flashError'=>null,'listing'=>$repo->listing([]),'filters'=>['q'=>'','status_id'=>'','account_id'=>'','platform'=>'','paid'=>'','date_from'=>'','date_to'=>'','amount_from'=>'','amount_to'=>'','sort'=>'newest'],'listQuery'=>'','activeFilterCount'=>0,'dashboard'=>$repo->dashboard(),'accounts'=>$repo->accounts(),'statuses'=>$repo->statuses(),'paymentMethods'=>$repo->paymentMethods(),'paymentSources'=>$repo->paymentSources(),'detail'=>$repo->order(1),'events'=>[],'orderDocs'=>$db->fetchAll('SELECT * FROM om_documents'),'orderShipments'=>[],'mappings'=>[],'rules'=>$repo->rules(),'series'=>$db->fetchAll('SELECT * FROM om_series'),'seller'=>['name'=>'Test','address'=>'Test','nip'=>'TEST','bank'=>''],'documents'=>$db->fetchAll('SELECT * FROM om_documents'),'shipments'=>[],'carrierAccounts'=>[],'sourceCarrierOptions'=>App\Services\OrderMarketplaceShipmentService::carrierOptions(),'printStations'=>[],'printJobs'=>[],'printFiscalPrinters'=>[],'printFiscalJobs'=>[],'receiptPrinterSettings'=>['printer_id'=>0],'printAgentApiUrl'=>'https://example.test/print-agent-api.php','documentKey'=>str_repeat('a',40)]);
foreach (['list','accounts','statuses','rules','documents','shipments','payments','printing'] as $tab) { $smarty->assign('tab',$tab);$html=$smarty->fetch('orders/index.tpl');check(strpos($html,'Centrum zamówień')!==false,'Render '.$tab); }
$receiptSeriesId=$db->insert('om_series',['name'=>'Paragony punkt A','kind'=>'receipt','pattern'=>'PA/{YYYY}/{N}','next_number'=>1,'fiscal_printer_id'=>7]);
$smarty->assign(['series'=>array_merge($db->fetchAll('SELECT * FROM om_series WHERE id<>:id ORDER BY id',['id'=>$receiptSeriesId]),[['id'=>$receiptSeriesId,'name'=>'Paragony punkt A','kind'=>'receipt','pattern'=>'PA/{YYYY}/{N}','next_number'=>1,'fiscal_printer_id'=>7,'effective_printer_id'=>7]]),'printFiscalPrinters'=>[['id'=>7,'name'=>'Posnet punkt A','host'=>'127.0.0.1','port'=>6666,'enabled'=>1,'environment'=>'sandbox']]]);
$smarty->assign('tab','documents');$seriesHtml=$smarty->fetch('orders/index.tpl');
check(strpos($seriesHtml,'name="series_id" value="'.$receiptSeriesId.'"')!==false && strpos($seriesHtml,'Posnet punkt A')!==false,'Series edit renders assigned fiscal printer');
$smarty->assign(['tab'=>'list','operatorName'=>'Operator testowy']);
check(strpos($smarty->fetch('orders/shell.tpl'),'orders-standalone')!==false,'Render standalone order shell');
$smarty->assign(['tab'=>'list','detail'=>null,'listing'=>['rows'=>[],'total'=>0,'page'=>1,'pages'=>1]]);
check(strpos($smarty->fetch('orders/index.tpl'),'Nie znaleziono zamówień')!==false,'Empty list renders');
$smarty->assign(['detail'=>null,'listing'=>$repo->listing([])]);
$listHtml=$smarty->fetch('orders/index.tpl');
check(strpos($listHtml,'data-copy-order=')!==false && strpos($listHtml,'data-product-image')!==false,'Order IDs and product thumbnails render');
$attack=$repo->order(1);$attack['buyer_name']='<script>alert(1)</script>';
$smarty->assign(['detail'=>$attack,'listing'=>$repo->listing([])]);
$escaped=$smarty->fetch('orders/index.tpl');
check(strpos($escaped,'<script>alert(1)</script>')===false && strpos($escaped,'&lt;script&gt;')!==false,'Imported customer HTML escaped');
$renderShipment=['id'=>1,'carrier'=>'Wysyłam z Allegro TEST','carrier_provider'=>'allegro_wza','state'=>'SUCCESS','tracking'=>'TEST123','created_at'=>'2026-09-10 08:00:00','cod_amount_cents'=>0,'shipment_currency'=>'PLN','presentation'=>$shipmentPresentation];
$smarty->assign(['detail'=>$repo->order(1),'orderShipments'=>[$renderShipment]]);
$detailHtml=$smarty->fetch('orders/index.tpl');
check(strpos($detailHtml,'om-order-console')!==false && strpos($detailHtml,'data-inline-order-form')!==false && strpos($detailHtml,'id="om-data-grid"')!==false && strpos($detailHtml,'id="om-shipping"')!==false,'Compact order console renders');
check(strpos($detailHtml,'STATUS U PRZEWOŹNIKA')!==false && strpos($detailHtml,'InPost Paczkomat 24/7')!==false && strpos($detailHtml,'W drodze')!==false,'Shipment status, carrier and service render');
check(strpos($detailHtml,'Drukuj najnowszą')!==false && strpos($detailHtml,'Drukuj wszystkie')!==false,'Newest and all label actions render without Smarty syntax errors');
$published=[];
$publisherStub=new class($published) {
    public $published;
    public function __construct(&$published) { $this->published=&$published; }
    public function listAccounts(): array { return [['id'=>7,'is_active'=>1,'name'=>'Empik test']]; }
    public function publishOrderShipment(array $account,string $orderId,string $tracking,string $carrierCode,string $carrierName): void { $this->published=compact('account','orderId','tracking','carrierCode','carrierName'); }
};
$publisher=new App\Services\OrderMarketplaceShipmentService($repo,['empik'=>$publisherStub]);
$message=$publisher->publish(['platform'=>'empik','account_source_id'=>7,'external_id'=>'M-1'],'TRACK-1','dpd','DPD');
check($published['orderId']==='M-1' && $published['tracking']==='TRACK-1' && $published['carrierCode']==='dpd' && strpos($message,'Empik')!==false,'Shipment tracking routed to source marketplace account');
$miraklCarrier=App\Services\OrderMarketplaceShipmentService::miraklCarrierPayload([['code'=>'DPD_PL','label'=>'DPD Polska','standard_code'=>'dpd']], 'dpd','DPD');
check($miraklCarrier['carrier_code']==='DPD_PL' && $miraklCarrier['carrier_name']==='DPD Polska','Mirakl carrier uses marketplace SH21 code instead of guessed generic code');
$miraklOther=App\Services\OrderMarketplaceShipmentService::miraklCarrierPayload([['code'=>'UPS_EMP','label'=>'UPS']], 'other','Kurier lokalny');
check(!isset($miraklOther['carrier_code']) && $miraklOther['carrier_name']==='Kurier lokalny','Unknown Mirakl carrier is sent as unregistered carrier name');
$publishShipmentId=(int)$db->insert('om_shipments',['order_id'=>$empikOrderId,'carrier'=>'Apaczka','tracking'=>'TRACK-2','weight'=>'1','state'=>'created','payload_json'=>OrderRepository::json(['provider'=>'apaczka','meta'=>[]]),'created_at'=>'2026-09-15 08:00:00']);
$publisher->publishShipment($publishShipmentId,'inpost','','Tester');
$publication=json_decode((string)$db->fetchColumn('SELECT payload_json FROM om_shipments WHERE id=:id',['id'=>$publishShipmentId]),true)['meta']['source_publication'];
check($publication['state']==='received' && $publication['carrier_name']==='InPost' && $publication['sent_at']!=='' && $publication['received_at']!=='','Shipment source send and receipt confirmation persisted');
$rejectedShipmentId=(int)$db->insert('om_shipments',['order_id'=>$empikOrderId,'carrier'=>'Apaczka','tracking'=>'TRACK-3','weight'=>'1','state'=>'created','payload_json'=>OrderRepository::json(['provider'=>'apaczka','meta'=>[]]),'created_at'=>'2026-09-15 08:01:00']);
$rejectingStub=new class {
    public function listAccounts(): array { return [['id'=>7,'is_active'=>1,'name'=>'Empik test']]; }
    public function publishOrderShipment(array $account,string $orderId,string $tracking,string $carrierCode,string $carrierName): void { throw new RuntimeException('HTTP 400'); }
};
$rejectingPublisher=new App\Services\OrderMarketplaceShipmentService($repo,['empik'=>$rejectingStub]);
rejects(fn()=>$rejectingPublisher->publishShipment($rejectedShipmentId,'dpd','','Tester'),'Rejected marketplace publication throws');
$rejectedPublication=json_decode((string)$db->fetchColumn('SELECT payload_json FROM om_shipments WHERE id=:id',['id'=>$rejectedShipmentId]),true)['meta']['source_publication'];
check($rejectedPublication['state']==='rejected' && $rejectedPublication['attempted_at']!=='' && $rejectedPublication['sent_at']==='' && $rejectedPublication['received_at']==='','Rejected marketplace request is not displayed as sent');
$doc=$db->fetch('SELECT * FROM om_documents WHERE id=2');$doc['snapshot']=json_decode($doc['snapshot_json'],true);$smarty->assign('document',$doc);check(strpos($smarty->fetch('orders/print.tpl'),'Faktura korygująca')!==false,'Render correction A4');
if (getenv('OM_PREVIEW_DIR')) {
    $dir=getenv('OM_PREVIEW_DIR'); if (!is_dir($dir)) mkdir($dir,0700,true);
    file_put_contents($dir.'/detail.html','<!doctype html><html lang="pl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;font-family:Inter,Arial,sans-serif}*{box-sizing:border-box}</style><style>'.file_get_contents(BASE_PATH.'/dist/css/orders.css').'</style>'.$detailHtml.'</html>');
    $smarty->assign('detail',null);
    foreach (['list','accounts','statuses','rules','documents','shipments','payments'] as $tab) {
        $smarty->assign('tab',$tab);$body=$smarty->fetch('orders/index.tpl');
        file_put_contents($dir.'/'.$tab.'.html','<!doctype html><html lang="pl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;font-family:Arial,sans-serif}*{box-sizing:border-box}</style>'.$body.'</html>');
    }
    file_put_contents($dir.'/print.html',$smarty->fetch('orders/print.tpl'));
}
$repo->saveSetting('seller',['name'=>'Firma testowa','address'=>'Testowa 1, Warszawa','nip'=>'TEST','bank'=>'']);
$numbering=['format'=>'MONTHLY','reset'=>true,'start'=>4,'length'=>3,'prefix'=>'TEST','suffix'=>'','color'=>'#123456','notes'=>'Uwagi serii'];
$documentSettings=['sale_date_source'=>'issue_date','payment_term_days'=>'7','split_payment'=>'1','seller_name'=>'Firma dla serii','vat_source'=>'static','vat_rate'=>'8'];
$numberingId=$db->insert('om_series',['name'=>'Seria miesięczna','kind'=>'invoice','pattern'=>'TEST/{N}/{MM}/{YYYY}','next_number'=>42,'numbering_json'=>OrderRepository::json($numbering),'numbering_period'=>'2020-01','document_settings_json'=>OrderRepository::json($documentSettings)]);
$standard=$input;$standard['series_id']=$numberingId;$standard['request_key']=str_repeat('e',40);
$standardId=$docService->issue(1,$standard,'test');
$standardRow=$db->fetch('SELECT number,snapshot_json FROM om_documents WHERE id=:id',['id'=>$standardId]);
check(strpos($standardRow['number'],'TEST/004/')===0 && json_decode($standardRow['snapshot_json'],true)['series_notes']==='Uwagi serii','Monthly numbering resets, pads and snapshots notes');
$seriesSnapshot=json_decode($standardRow['snapshot_json'],true);
check($seriesSnapshot['seller']['name']==='Firma dla serii' && $seriesSnapshot['sale_date']===$seriesSnapshot['issue_date'] && $seriesSnapshot['split_payment']===true && $seriesSnapshot['payment_due_date']!=='' && $seriesSnapshot['items'][0]['vat']==='8','Series seller, VAT, date and payment settings applied');
check((int)$db->fetchColumn('SELECT next_number FROM om_series WHERE id=:id',['id'=>$numberingId])===5,'Monthly counter advances after reset');
$standard['request_key']=str_repeat('f',40);$nextStandard=$docService->issue(1,$standard,'test');
check(strpos((string)$db->fetchColumn('SELECT number FROM om_documents WHERE id=:id',['id'=>$nextStandard]),'TEST/005/')===0,'Monthly counter does not reset again in same period');
echo "OK: $checks checks; no network or production database used.\n";
