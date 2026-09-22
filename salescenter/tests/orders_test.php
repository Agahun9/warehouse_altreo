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
App\Core\Tenant::activate(1);
$repo=new OrderRepository($db); $repo->ensureSchema(); $repo->ensureSchema();
check(count($repo->statuses())===6,'Schema idempotency');
check(($repo->statuses()[0]['group_name']??'')==='Do realizacji','Default status groups seeded');
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
// Wzbogacanie miniatur z cache ofert marketplace wróci razem z integracjami firm.
$old=$raw; $old['lineItems'][0]['boughtAt']='2026-09-01T11:59:59Z';
check(OrderNormalizer::normalize('allegro',$old,$cutoff,$now)===null,'Reject order older than 7 days');
$old['lineItems'][0]['boughtAt']='2026-09-01T12:00:00Z';
check(OrderNormalizer::normalize('allegro',$old,$cutoff,$now)!==null,'Include exact cutoff');
$old['lineItems'][0]['boughtAt']='2026-09-09T12:00:00Z';
check(OrderNormalizer::normalize('allegro',$old,$cutoff,$now)===null,'Reject future order');
$missing=$raw;unset($missing['lineItems'][0]['boughtAt']);rejects(fn()=>OrderNormalizer::normalize('allegro',$missing,$cutoff,$now),'Missing date');
check($repo->import(1,$order),'Initial import'); check(!$repo->import(1,$order),'Duplicate updates'); check($repo->import(2,$order),'Same external ID, distinct account');
check($repo->dashboard()['total']===2,'Uniqueness enforced');
check($repo->listing(['group'=>'Do realizacji'])['total']===2 && $repo->listing(['group'=>'Zamknięte'])['total']===0,'Status group filter');
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
$listed=array_column($repo->listing(['q'=>'test@example','account_id'=>1])['rows']??[],'document_choice','id');
check(($listed[1]??'')==='invoice','List shows invoice without NIP');
check($repo->listing(['q'=>'test@example','account_id'=>1])['total']===1,'Search with account filter');
check($repo->listing(['q'=>"' OR 1=1 --"])['total']===0,'SQL injection search');
check($repo->listing(['q'=>'TEST@EXAMPLE'])['total']>=1,'Search is case-insensitive');
check($repo->listing(['q'=>'test@example zzz-nonexistent'])['total']===0,'Search requires every word to match');
check($repo->listing(['q'=>'100%'])['total']===0,'Search escapes LIKE wildcards');
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
$empikLocker=$mirakl;
$empikLocker['shipping_type_label']='Paczkomaty InPost';
$empikLocker['customer']=['shipping_address'=>['firstname'=>'BUS03M','lastname'=>'','additional_info'=>'Na parkingu po prawej stronie','street_1'=>'Wojska Polskiego','street_2'=>'33','zip_code'=>'28-100','city'=>'Busko-Zdrój'],'billing_address'=>['firstname'=>'Anna','lastname'=>'Testowa']];
$lockerOrder=OrderNormalizer::normalize('empik',$empikLocker,$cutoff,$now);
check($lockerOrder['details']['pickup']==='BUS03M' && $lockerOrder['details']['address']['firstName']==='' && $lockerOrder['buyer_name']==='Anna Testowa','Empik locker code mapped to pickup instead of customer name');
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
$miraklAccepted=$miraklCod;$miraklAccepted['order_additional_fields']=[['code'=>'customer-email','type'=>'STRING','value'=>'kupujacy@example.invalid']];
check(OrderNormalizer::normalize('empik',$miraklAccepted,$cutoff,$now)['email']==='kupujacy@example.invalid','Mirakl customer-email additional field used after acceptance');
check($miraklCodOrder['details']['address']['street']==='Testowa' && $miraklCodOrder['details']['address']['buildingNumber']==='37' && $miraklCodOrder['details']['address']['zip']==='23-204' && $miraklCodOrder['details']['address']['country']==='PL','Mirakl delivery address normalization');
check($miraklOrder['paid']===0,'Mirakl order without customer_debited_date stays unpaid');
$miraklPaid=$mirakl;$miraklPaid['customer_debited_date']='2026-09-08T11:00:00Z';
check(OrderNormalizer::normalize('empik',$miraklPaid,$cutoff,$now)['paid']===1,'Mirakl payment recognized from OR11 customer_debited_date');
$miraklCod['customer_debited_date']='2026-09-08T11:00:00Z';
check(OrderNormalizer::normalize('empik',$miraklCod,$cutoff,$now)['paid']===0,'Mirakl COD never marked paid');
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
$split=['buyer_name'=>'Anna Testowa','shipping_address'=>['name'=>'Jan Odbiorca','street'=>'Dostawcza','building'=>'5','postal_code'=>'00-100','city'=>'Kraków','country'=>'PL'],'details'=>['pickup'=>'KRA01M','delivery'=>'Paczkomaty InPost','invoice_form'=>['name'=>'Anna Testowa','company'=>'Firma Test sp. z o.o.','nip'=>'5252674798','street'=>'Firmowa','building'=>'8','postal_code'=>'00-002','city'=>'Warszawa','country'=>'PL']]];
check(OrderDocumentService::buyerText($split)==="Firma Test sp. z o.o.\nNIP: 5252674798\nFirmowa 8\n00-002 Warszawa",'Buyer block uses billing data');
check(OrderDocumentService::recipientText($split)==="Jan Odbiorca\nDostawcza 5\n00-100 Kraków\nPunkt odbioru: KRA01M\nDostawa: Paczkomaty InPost",'Recipient block uses delivery data');
$noBilling=$split; $noBilling['details']['invoice_form']=['name'=>'','company'=>'','nip'=>'','street'=>'','country'=>'PL'];
check(OrderDocumentService::buyerText($noBilling)==="Jan Odbiorca\nDostawcza 5\n00-100 Kraków",'Empty billing data falls back to delivery');
check(json_decode($db->fetchColumn('SELECT snapshot_json FROM om_documents WHERE id=1'),true)['recipient']!==null,'Snapshot stores delivery data');
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

// Konektory marketplace testuje tests/integrations_test.php.
Database::useInstance($db);
check((new App\Services\EmpikService())->listAccounts()===[],'Company without connections exposes no marketplace accounts');

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
$miraklStub = new class {
    public $pages=[];
    public function listAccounts(): array { return [['id'=>7,'is_active'=>1,'name'=>'Empik test']]; }
    public function readOrderPage(array $account,string $from,string $to,string $cursor='',string $updatedFrom=''): array { return array_shift($this->pages) ?? ['orders'=>[],'total_count'=>0]; }
};
$syncRef->getProperty('services')->setValue($sync,['allegro'=>$stub,'empik'=>$miraklStub]);
$paidLater=$mirakl;$paidLater['created_date']=gmdate('c',time()-10*86400);$paidLater['order_state']='SHIPPING';$paidLater['customer_debited_date']=gmdate('c',time()-60);
$unknownOld=$paidLater;$unknownOld['order_id']='m-old-unknown';
$miraklStub->pages=[['orders'=>[$paidLater,$unknownOld],'total_count'=>2]];
$result=$sync->sync(true,$empikAccountId);
check($result[0]['added']===0 && $result[0]['updated']===1 && $result[0]['skipped']===1 && !$db->fetchColumn('SELECT id FROM om_orders WHERE external_id=:e',['e'=>'m-old-unknown']),'Orders older than seven days are updated but never created');
check((int)$db->fetchColumn('SELECT paid FROM om_orders WHERE id=:id',['id'=>$empikOrderId])===1 && (int)$db->fetchColumn('SELECT COUNT(*) FROM om_events WHERE order_id=:id AND message=:m',['id'=>$empikOrderId,'m'=>'Płatność potwierdzona w źródle.'])===1,'Late Mirakl payment updates existing order and history');

// Compile and render all Smarty branches, with synthetic records only.
$smarty=App\Core\SmartyFactory::create();
$smarty->setCompileDir(sys_get_temp_dir().'/om-smarty-test');
$smarty->assign(['csrf'=>'test','canWrite'=>true,'flashSuccess'=>null,'flashError'=>null,'listing'=>$repo->listing([]),'filters'=>['q'=>'','status_id'=>'','group'=>'','account_id'=>'','platform'=>'','paid'=>'','date_from'=>'','date_to'=>'','amount_from'=>'','amount_to'=>'','sort'=>'newest'],'listQuery'=>'','activeFilterCount'=>0,'dashboard'=>$repo->dashboard(),'statusGroups'=>array_reduce($repo->dashboard()['statuses'],static function ($groups,$status) { $name=$status['group_name']; if (!isset($groups[$name])) { $groups[$name]=['name'=>$name,'total'=>0,'statuses'=>[]]; } $groups[$name]['total']+=(int)$status['total']; $groups[$name]['statuses'][]=$status; return $groups; },[]),'accounts'=>$repo->accounts(),'statuses'=>$repo->statuses(),'paymentMethods'=>$repo->paymentMethods(),'paymentSources'=>$repo->paymentSources(),'detail'=>$repo->order(1),'events'=>[],'orderDocs'=>$db->fetchAll('SELECT * FROM om_documents'),'orderShipments'=>[],'mappings'=>[],'rules'=>$repo->rules(),'series'=>$db->fetchAll('SELECT * FROM om_series'),'seller'=>['name'=>'Test','address'=>'Test','nip'=>'TEST','bank'=>''],'documents'=>$db->fetchAll('SELECT * FROM om_documents'),'shipments'=>[],'carrierAccounts'=>[],'sourceCarrierOptions'=>App\Services\OrderMarketplaceShipmentService::carrierOptions(),'printStations'=>[],'printJobs'=>[],'printFiscalPrinters'=>[],'printFiscalJobs'=>[],'receiptPrinterSettings'=>['printer_id'=>0],'orderGeneralSettings'=>['default_currency'=>'PLN'],'printAgentApiUrl'=>'https://example.test/print-agent-api.php','documentKey'=>str_repeat('a',40)]);
foreach (['list','accounts','statuses','rules','documents','shipments','payments','printing','general'] as $tab) { $smarty->assign('tab',$tab);$html=$smarty->fetch('orders/index.tpl');check(strpos($html,'Centrum zamówień')!==false,'Render '.$tab); }
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
check(strpos($detailHtml,'form="om-issue-receipt" name="series_id" value="'.$receiptSeriesId.'"')!==false && strpos($detailHtml,'<div class="oc-doc-series">')!==false,'Receipt series renders as its own issue button');
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
$doc=$db->fetch('SELECT * FROM om_documents WHERE id=2');$doc['snapshot']=json_decode($doc['snapshot_json'],true);$doc['vat_summary']=[];foreach ($doc['snapshot']['items'] as $item) { $vat=$item['vat'];if (!isset($doc['vat_summary'][$vat])) $doc['vat_summary'][$vat]=['vat'=>$vat,'net_cents'=>0,'tax_cents'=>0,'gross_cents'=>0];foreach (['net_cents','tax_cents','gross_cents'] as $field) $doc['vat_summary'][$vat][$field]+=$item[$field]; }$smarty->assign('document',$doc);check(strpos($smarty->fetch('orders/print.tpl'),'Faktura korygująca')!==false,'Render correction A4');
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
$parentCorrection=$db->fetch('SELECT * FROM om_documents WHERE id=2');$parentCorrection['snapshot']=json_decode($parentCorrection['snapshot_json'],true);$parentCorrection['source_revision_id']=2;$parentCorrection['source_revision_number']=$parentCorrection['number'];
$smarty->assign(['parent'=>$parentCorrection,'series'=>[['id'=>2,'name'=>'Korekty']],'csrf'=>'test','requestKey'=>str_repeat('7',48),'correctionKind'=>'invoice_correction','today'=>'2026-09-16']);
$correctionForm=$smarty->fetch('orders/correct.tpl');
if (getenv('OM_PREVIEW_DIR')) file_put_contents(getenv('OM_PREVIEW_DIR').'/correct.html',$correctionForm);
check(strpos($correctionForm,'Wystaw korektę')!==false && strpos($correctionForm,$parentCorrection['number'])!==false,'Correction form rendered from selected document');
$reCorrection=['operation'=>'document_correction','series_id'=>2,'parent_id'=>2,'source_revision_id'=>2,'request_key'=>str_repeat('7',48),'buyer'=>'Nowy nabywca','seller_name'=>'Poprawiony sprzedawca','seller_address'=>'Nowa 2, Warszawa','seller_nip'=>'1234567890','seller_bank'=>'PL001','sale_date'=>'2026-09-15','issue_date'=>'2026-09-16','payment_due_date'=>'2026-09-23','currency'=>'PLN','order_number'=>'ZAM-1','payment_method'=>'Przelew testowy','amount_paid'=>'5.00','split_payment'=>'1','series_notes'=>'Po zmianie','reason'=>'Zmiana ilości i danych','items'=>[['name'=>'Test','quantity'=>2,'price'=>'12.30','vat'=>'23']]];
$reCorrectionId=$docService->issue(1,$reCorrection,'test');
$reSnapshot=json_decode($db->fetchColumn('SELECT snapshot_json FROM om_documents WHERE id=:id',['id'=>$reCorrectionId]),true);
check($reSnapshot['before']['gross_cents']===1230 && $reSnapshot['gross_cents']===2460 && $reSnapshot['difference_cents']===1230 && $reSnapshot['seller']['name']==='Poprawiony sprzedawca' && $reSnapshot['buyer']==='Nowy nabywca' && $reSnapshot['payment_method']==='Przelew testowy' && $reSnapshot['amount_paid_cents']===500,'Correction of correction updates quantities, parties and payment');
$invalidCorrection=$reCorrection;$invalidCorrection['request_key']=str_repeat('8',48);$invalidCorrection['source_revision_id']=$reCorrectionId;$invalidCorrection['currency']='EUR';rejects(fn()=>$docService->issue(1,$invalidCorrection,'test'),'Reject correction with unconverted currency');
$invalidCorrection['currency']='PLN';$invalidCorrection['issue_date']='2026-02-30';rejects(fn()=>$docService->issue(1,$invalidCorrection,'test'),'Reject impossible issue date');
$invalidCorrection['issue_date']='2026-09-16';$invalidCorrection['source_revision_id']=2;rejects(fn()=>$docService->issue(1,$invalidCorrection,'test'),'Reject stale correction form');
$receiptSeriesId=$db->insert('om_series',['name'=>'Paragony korekta test','kind'=>'receipt','pattern'=>'PTEST/{YYYY}/{N}','next_number'=>1]);
$receiptCorrectionSeriesId=$db->insert('om_series',['name'=>'Korekty paragonów test','kind'=>'receipt_correction','pattern'=>'KPTEST/{YYYY}/{N}','next_number'=>1]);
$receiptInput=$input;$receiptInput['series_id']=$receiptSeriesId;$receiptInput['request_key']=str_repeat('9',48);$receiptId=$docService->issue(1,$receiptInput,'test');
$receiptCorrection=$reCorrection;$receiptCorrection['series_id']=$receiptCorrectionSeriesId;$receiptCorrection['parent_id']=$receiptId;$receiptCorrection['source_revision_id']=$receiptId;$receiptCorrection['request_key']=str_repeat('0',48);$receiptCorrectionId=$docService->issue(1,$receiptCorrection,'test');
check($db->fetchColumn('SELECT kind FROM om_documents WHERE id=:id',['id'=>$receiptCorrectionId])==='receipt_correction','Receipt can be corrected');
$nipReceiptInput=$receiptInput;$nipReceiptInput['request_key']=str_repeat('8',48);$nipReceiptInput['buyer']="Firma Test sp. z o.o.\nNIP: 526-025-02-74";$nipReceiptId=$docService->issue(1,$nipReceiptInput,'test');
check((json_decode((string)$db->fetchColumn('SELECT snapshot_json FROM om_documents WHERE id=:id',['id'=>$nipReceiptId]),true)['buyer_nip']??null)==='5260250274','Receipt stores buyer NIP for fiscal printer');
rejects(fn()=>OrderDocumentService::assertReceiptNipLimit('5260250274',45001,'PLN'),'Receipt with buyer NIP above 450 PLN is rejected');
rejects(fn()=>OrderDocumentService::assertReceiptNipLimit('5260250274',10001,'EUR'),'Receipt with buyer NIP above 100 EUR is rejected');
OrderDocumentService::assertReceiptNipLimit('5260250274',45000,'PLN');OrderDocumentService::assertReceiptNipLimit(null,999999,'PLN');
check(true,'Receipt NIP limit allows 450 PLN and receipts without NIP');
foreach ([$docId=>'Faktura ',$receiptId=>'Paragon ',$reCorrectionId=>'Faktura korygująca',$receiptCorrectionId=>'Korekta paragonu'] as $printId=>$expectedTitle) {
    $printRow=$db->fetch('SELECT * FROM om_documents WHERE id=:id',['id'=>$printId]);$printRow['snapshot']=json_decode($printRow['snapshot_json'],true);$printRow['vat_summary']=[];
    foreach ($printRow['snapshot']['items'] as $item) { $vat=$item['vat'];if (!isset($printRow['vat_summary'][$vat])) $printRow['vat_summary'][$vat]=['vat'=>$vat,'net_cents'=>0,'tax_cents'=>0,'gross_cents'=>0];foreach (['net_cents','tax_cents','gross_cents'] as $field) $printRow['vat_summary'][$vat][$field]+=$item[$field]; }
    $smarty->assign('document',$printRow);$html=$smarty->fetch('orders/print.tpl');
    check(strpos($html,$expectedTitle)!==false && strpos($html,'m-document')===false && strpos($html,'Wartość brutto')!==false,'A4 layout for '.$expectedTitle);
}
$printRow['ksef']=['state'=>'accepted','environment'=>'production','ksef_number'=>'5252674798-20260917-ABC','qr_url'=>'https://qr.ksef.mf.gov.pl/invoice/5252674798/17-09-2026/abc'];
$smarty->assign('document',$printRow);$html=$smarty->fetch('orders/print.tpl');
check(strpos($html,'data-ksef-qr="https://qr.ksef.mf.gov.pl/invoice/5252674798/17-09-2026/abc"')!==false && strpos($html,'qrcode-generator.js')!==false && strpos($html,'Dokument lokalny')===false,'A4 print shows KSeF QR code after acceptance');

// Automations: triggers → conditions → ordered actions, chains, loop protection, buttons, schedule.
$db->query('DELETE FROM om_rule_runs'); $db->query('DELETE FROM om_rules');
$automation=$repo->automation();
$ruleBase=['enabled'=>true,'triggers'=>['order_created'],'actions'=>[['type'=>'add_tags','params'=>['tags'=>'x']]]];
rejects(fn()=>$automation->saveRule(0,['name'=>'Bez wyzwalacza','triggers'=>[]]+$ruleBase),'Automation requires a trigger');
rejects(fn()=>$automation->saveRule(0,['name'=>'Zły warunek','conditions'=>[['field'=>'nope','op'=>'in','value'=>['1']]]]+$ruleBase),'Automation rejects unknown condition');
rejects(fn()=>$automation->saveRule(0,['name'=>'Zły status','actions'=>[['type'=>'set_status','params'=>['status_id'=>'999']]]]+$ruleBase),'Automation rejects unknown status');
rejects(fn()=>$automation->saveRule(0,['name'=>'Brak efektów','actions'=>[]]+$ruleBase),'Automation requires an action');
rejects(fn()=>$automation->saveRule(0,['name'=>'Webhook http','actions'=>[['type'=>'webhook','params'=>['url'=>'http://example.invalid/hook']]]]+$ruleBase),'Webhook requires https');
rejects(fn()=>$automation->saveRule(0,['name'=>'Czas','triggers'=>['scheduled'],'options'=>['delay'=>['value'=>61,'unit'=>'days','from'=>'ordered']]]+$ruleBase),'Scheduled delay is limited');
$packRule=$automation->saveRule(0,['name'=>'Allegro → pakowanie','enabled'=>true,'triggers'=>['order_created'],'conditions'=>[['field'=>'platform','op'=>'in','value'=>['allegro']],['field'=>'total','op'=>'between','value'=>['10','100,50']],['field'=>'sku','op'=>'any','value'=>'AUTO-*, INNE'],['field'=>'delivery_method','op'=>'not_contains','value'=>'kurier'],['field'=>'country','op'=>'not_in','value'=>['DE']]],'actions'=>[['type'=>'add_tags','params'=>['tags'=>'auto, allegro']],['type'=>'set_status','params'=>['status_id'=>'2']],['type'=>'append_note','params'=>['text'=>'Kwota {kwota} {waluta} dla {kupujacy}']]],'options'=>['run_limit'=>'once']]);
$chainRule=$automation->saveRule(0,['name'=>'Po pakowaniu','enabled'=>true,'triggers'=>['status'],'conditions'=>[['field'=>'status','op'=>'in','value'=>['2']],['field'=>'status_source','op'=>'in','value'=>['automation']]],'actions'=>[['type'=>'add_tags','params'=>['tags'=>'łańcuch']],['type'=>'set_status','params'=>['status_id'=>'3']]]]);
$loopRule=$automation->saveRule(0,['name'=>'Pętla','enabled'=>true,'triggers'=>['status'],'conditions'=>[['field'=>'status','op'=>'in','value'=>['3']]],'actions'=>[['type'=>'set_status','params'=>['status_id'=>'2']]]]);
$autoRaw=$raw; $autoRaw['id']='auto-1'; $autoRaw['status']='AUTO_NEW'; $autoRaw['lineItems'][0]['offer']['external']['id']='AUTO-7';
$repo->import(1,OrderNormalizer::normalize('allegro',$autoRaw,$cutoff,$now));
$autoId=(int)$db->fetchColumn('SELECT id FROM om_orders WHERE external_id=:e',['e'=>'auto-1']);
$autoOrder=$repo->order($autoId);
check($autoOrder['tags']==='auto, allegro, łańcuch','Automation steps run in order and trigger chained rules');
check((int)$autoOrder['status_id']===2 && (int)$db->fetchColumn('SELECT COUNT(*) FROM om_rule_runs WHERE order_id=:o',['o'=>$autoId])===3,'Automation chain never loops');
check(strpos((string)$autoOrder['note'],'Kwota 24,60 PLN dla ')===0,'Automation placeholders rendered');
$repo->import(1,OrderNormalizer::normalize('allegro',$autoRaw,$cutoff,$now));
check((int)$db->fetchColumn('SELECT COUNT(*) FROM om_rule_runs WHERE rule_id=:r',['r'=>$packRule])===1,'New order automation runs once');
$otherRaw=$raw; $otherRaw['id']='auto-2'; $otherRaw['status']='AUTO_NEW'; $otherRaw['lineItems'][0]['offer']['external']['id']='BRAK-1';
$repo->import(1,OrderNormalizer::normalize('allegro',$otherRaw,$cutoff,$now));
$otherId=(int)$db->fetchColumn('SELECT id FROM om_orders WHERE external_id=:e',['e'=>'auto-2']);
check(!$db->fetchColumn('SELECT id FROM om_rule_runs WHERE order_id=:o',['o'=>$otherId]) && $repo->order($otherId)['tags']==='','Unmatched conditions skip automation');
$db->query('UPDATE om_rules SET enabled=0');
$deferredRule=$automation->saveRule(0,['name'=>'Ręczna zmiana','enabled'=>true,'triggers'=>['status'],'conditions'=>[['field'=>'status_source','op'=>'in','value'=>['user']]],'actions'=>[['type'=>'add_event','params'=>['text'=>'Po zatwierdzeniu {status}']]]]);
$deferredRuns=fn()=>(int)$db->fetchColumn('SELECT COUNT(*) FROM om_rule_runs WHERE rule_id=:r',['r'=>$deferredRule]);
$db->transaction(function () use ($repo,$autoId,$deferredRuns) { $repo->changeStatus($autoId,4,'Tester'); check($deferredRuns()===0,'Automation waits for transaction commit'); });
$repo->flushAutomations();
check($deferredRuns()===1,'Automation runs after commit');
try { $db->transaction(function () use ($repo,$autoId) { $repo->changeStatus($autoId,5,'Tester'); throw new RuntimeException('rollback'); }); } catch (RuntimeException $e) { $repo->discardAutomations(); }
$repo->flushAutomations();
check($deferredRuns()===1 && (int)$repo->order($autoId)['status_id']===4,'Rolled back events never run');
$manualRule=$automation->saveRule(0,['name'=>'Przycisk','enabled'=>true,'triggers'=>['manual'],'conditions'=>[['field'=>'tags','op'=>'any','value'=>'łańcuch']],'actions'=>[['type'=>'set_paid','params'=>['state'=>'unpaid']],['type'=>'add_tags','params'=>['tags'=>'ręcznie']]],'options'=>['button_order'=>true,'button_list'=>true,'run_limit'=>'once']]);
$report=$automation->runManual([$autoId,$otherId],$manualRule,'Tester');
check($report['executed']===1 && $report['skipped']===1 && $report['errors']===0,'Manual run checks conditions per order');
check($automation->runManual([$autoId],$manualRule,'Tester')['executed']===1,'Manual run ignores run limit');
check((int)$repo->order($autoId)['paid']===0 && $repo->order($autoId)['tags']==='auto, allegro, łańcuch, ręcznie','Manual automation changes payment and tags');
$repo->import(1,OrderNormalizer::normalize('allegro',$autoRaw,$cutoff,$now));
check((int)$repo->order($autoId)['paid']===0,'Automation payment change survives sync');
check(array_column($automation->manualRules()['order'],'id')===[$manualRule] && array_column($automation->manualRules()['list'],'id')===[$manualRule],'Manual rule buttons listed');
$explained=array_values(array_filter($automation->explain($autoId),fn($rule)=>$rule['id']===$manualRule))[0];
check($explained['match'] && $explained['conditions'][0]['state']==='pass','Order preview explains each condition');
check($repo->runRules($autoId,'manual','preview',true)===['Przycisk'],'Legacy preview reports matching rule names');
rejects(fn()=>$automation->runManual([$autoId],$packRule,'Tester'),'Disabled automation cannot be run');
// One order button per name: variants differ by IF, the first matching one (top to bottom) runs.
$packAction=static function (string $tag): array { return [['type'=>'add_tags','params'=>['tags'=>$tag]]]; };
$packA=$automation->saveRule(0,['name'=>'Pakuj','enabled'=>true,'triggers'=>['manual'],'conditions'=>[['field'=>'tags','op'=>'any','value'=>'nie-ma-takiego']],'actions'=>$packAction('pakuj-a'),'options'=>['button_order'=>true,'shortcut'=>'alt + p']]);
$packB=$automation->saveRule(0,['name'=>' pakuj ','enabled'=>true,'triggers'=>['manual'],'conditions'=>[['field'=>'tags','op'=>'any','value'=>'ręcznie']],'actions'=>$packAction('pakuj-b'),'options'=>['button_order'=>true]]);
$packC=$automation->saveRule(0,['name'=>'Pakuj','enabled'=>true,'triggers'=>['manual'],'conditions'=>[],'actions'=>$packAction('pakuj-c'),'options'=>['button_order'=>true,'button_list'=>true,'shortcut'=>'Alt+P']]);
check($automation->rule($packA)['options']['shortcut']==='Alt+P' && $automation->rule($packB)['options']['shortcut']==='','Shortcut normalized and optional');
rejects(fn()=>$automation->saveRule(0,['name'=>'PAKUJ','enabled'=>true,'triggers'=>['order_created'],'conditions'=>[['field'=>'tags','op'=>'any','value'=>'ręcznie']],'actions'=>$packAction('x')]),'Same name and same IF rejected');
rejects(fn()=>$automation->saveRule($packB,['name'=>'Pakuj','enabled'=>true,'triggers'=>['manual'],'conditions'=>[],'actions'=>$packAction('x')]),'Editing into a duplicate IF rejected');
check($automation->saveRule($packB,$automation->rule($packB)+$automation->rule($packB)['options'])===$packB,'Re-saving a rule does not clash with itself');
$automation->saveRule(0,['name'=>'Sygnatura','enabled'=>false,'triggers'=>['manual'],'conditions'=>[['field'=>'platform','op'=>'in','value'=>['allegro','erli']],['field'=>'paid','op'=>'is','value'=>'yes']],'actions'=>$packAction('x')]);
rejects(fn()=>$automation->saveRule(0,['name'=>'sygnatura','triggers'=>['manual'],'conditions'=>[['field'=>'paid','op'=>'is','value'=>'yes'],['field'=>'platform','op'=>'in','value'=>['erli','allegro']]],'actions'=>$packAction('y')]),'IF comparison ignores condition and value order');
check($automation->saveRule(0,['name'=>'Sygnatura','triggers'=>['manual'],'conditions'=>[['field'=>'paid','op'=>'is','value'=>'no']],'actions'=>$packAction('x')])>0,'Same name with different IF is allowed');
rejects(fn()=>$automation->saveRule(0,['name'=>'Inna','triggers'=>['manual'],'actions'=>$packAction('x'),'options'=>['button_order'=>true,'shortcut'=>'Alt+P']]),'One shortcut cannot serve two buttons');
rejects(fn()=>$automation->saveRule(0,['name'=>'Pakuj','triggers'=>['manual'],'conditions'=>[['field'=>'paid','op'=>'is','value'=>'no']],'actions'=>$packAction('x'),'options'=>['button_order'=>true,'shortcut'=>'Alt+K']]),'Variants share one shortcut');
check($automation->rule($automation->saveRule(0,['name'=>'Bez przycisku','triggers'=>['manual'],'actions'=>$packAction('x'),'options'=>['shortcut'=>'Alt+P']]))['options']['shortcut']==='','Shortcut dropped without order button');
rejects(fn()=>App\Services\OrderAutomationService::normalizeShortcut('P'),'Letter shortcut needs Ctrl or Alt');
rejects(fn()=>App\Services\OrderAutomationService::normalizeShortcut('Ctrl+C'),'Browser shortcut rejected');
rejects(fn()=>App\Services\OrderAutomationService::normalizeShortcut('Win+P'),'Unknown modifier rejected');
check(App\Services\OrderAutomationService::normalizeShortcut('shift+ctrl+1')==='Ctrl+Shift+1' && App\Services\OrderAutomationService::normalizeShortcut('f2')==='F2','Shortcut modifiers ordered');
$packButtons=array_values(array_filter($automation->manualRules()['order'],fn($button)=>mb_strtolower($button['name'])==='pakuj'));
check(count($packButtons)===1 && $packButtons[0]['id']===$packA && $packButtons[0]['variants']===3 && $packButtons[0]['shortcut']==='Alt+P','Same-name rules form one order button');
$packList=array_values(array_filter($automation->manualRules()['list'],fn($button)=>mb_strtolower($button['name'])==='pakuj'));
check(count($packList)===1 && $packList[0]['id']===$packC && $packList[0]['variants']===1,'List button groups only rules with that button');
$groupReport=$automation->runManual([$autoId,$otherId],$packA,'Tester','order');
$autoTags=$repo->order($autoId)['tags']; $otherTags=$repo->order($otherId)['tags'];
check($groupReport['executed']===2 && $groupReport['skipped']===0 && $groupReport['errors']===0,'Group button runs one variant per order');
check(strpos($autoTags,'pakuj-b')!==false && strpos($autoTags,'pakuj-a')===false && strpos($autoTags,'pakuj-c')===false && strpos($otherTags,'pakuj-c')!==false && strpos($otherTags,'pakuj-b')===false,'First matching variant wins');
check($automation->runManual([$otherId],$packA,'Tester')['skipped']===1,'Single-rule run still checks only that rule');
$db->update('om_rules',['enabled'=>0],'id=:id',['id'=>$packC]);
$noneReport=$automation->runManual([$otherId],$packA,'Tester','order');
check($noneReport['executed']===0 && $noneReport['skipped']===1,'Group button skips order once when no variant matches');
$db->update('om_rules',['enabled'=>0],'id=:id',['id'=>$deferredRule]);
$scheduledRule=$automation->saveRule(0,['name'=>'Po czasie','enabled'=>true,'triggers'=>['scheduled'],'conditions'=>[['field'=>'payment_state','op'=>'in','value'=>['unpaid']]],'actions'=>[['type'=>'add_event','params'=>['text'=>'Minął czas']]],'options'=>['delay'=>['value'=>1,'unit'=>'hours','from'=>'ordered']]]);
$db->update('om_orders',['ordered_at'=>gmdate('Y-m-d H:i:s',time()-7200)],'id=:id',['id'=>$autoId]);
$automation->runScheduled(); $automation->runScheduled();
check((int)$db->fetchColumn('SELECT COUNT(*) FROM om_rule_runs WHERE rule_id=:r AND order_id=:o',['r'=>$scheduledRule,'o'=>$autoId])===1,'Scheduled automation runs once per reference time');
$hookRule=$automation->saveRule(0,['name'=>'Webhook lokalny','enabled'=>true,'triggers'=>['manual'],'actions'=>[['type'=>'webhook','params'=>['url'=>'https://127.0.0.1/hook']],['type'=>'add_tags','params'=>['tags'=>'po webhooku']]],'options'=>['stop_on_error'=>true]]);
$hookReport=$automation->runManual([$autoId],$hookRule,'Tester');
$hookRun=$db->fetch('SELECT result,message FROM om_rule_runs WHERE rule_id=:r',['r'=>$hookRule]);
check($hookReport['errors']===1 && $hookRun['result']==='error' && strpos($hookRun['message'],'prywatnego')!==false && strpos($repo->order($autoId)['tags'],'po webhooku')===false,'Webhook blocks private addresses and error stops later steps');
$copyId=$automation->duplicateRule($manualRule);
check(!$automation->rule($copyId)['enabled'] && $automation->rule($copyId)['actions']===$automation->rule($manualRule)['actions'],'Duplicated automation starts paused');
$automation->moveRule($copyId,'up');
$ruleOrder=array_column($automation->allRules(),'id');
check(array_search($copyId,$ruleOrder,true)===array_search($hookRule,$ruleOrder,true)-1 && end($ruleOrder)===$hookRule,'Automation order can be changed');
$xssRule=$automation->saveRule(0,['name'=>'</script><script>alert(1)</script>','enabled'=>true,'triggers'=>['manual'],'actions'=>[['type'=>'add_tags','params'=>['tags'=>'x']]],'options'=>['button_list'=>true]]);
$jsonFlags=JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR;
$smarty->assign(['tab'=>'rules','detail'=>null,'rules'=>$repo->rules(),'manualRules'=>$automation->manualRules(),'automation'=>['stats'=>$automation->stats(),'log'=>$automation->log(),'edit'=>['id'=>$packRule,'name'=>'x'],'catalog_json'=>json_encode($automation->catalog(),$jsonFlags),'rule_json'=>json_encode($automation->rule($packRule),$jsonFlags),'template'=>'']]);
$rulesHtml=$smarty->fetch('orders/index.tpl');
check(strpos($rulesHtml,'data-oa-form')!==false && strpos($rulesHtml,'id="oa-rule-'.$packRule.'"')!==false && strpos($rulesHtml,'WYKONAJ PO KOLEI')!==false,'Automation page and editor render');
check(strpos($rulesHtml,'<script>alert(1)')===false && strpos($rulesHtml,'&lt;/script&gt;')!==false,'Automation names escaped in HTML and JSON');
$smarty->assign(['tab'=>'list','listing'=>$repo->listing([])]);
check(strpos($smarty->fetch('orders/index.tpl'),'data-oa-bulk-run')!==false,'List bulk automation action renders');
$automationDetail=$repo->order($autoId); $automationDetail['raw_debug']='{}';
$smarty->assign(['detail'=>$automationDetail,'orderShipments'=>[],'series'=>[],'orderAutomation'=>['rules'=>$automation->explain($autoId),'runs'=>$automation->log(12,$autoId)]]);
$automationDetailHtml=$smarty->fetch('orders/index.tpl');
check(strpos($automationDetailHtml,'name="rule_group" value="'.$packA.'" data-oa-shortcut="Alt+P"')!==false && strpos($automationDetailHtml,'>Alt+P</kbd>')!==false,'Order button renders shortcut');
check(strpos($automationDetailHtml,'id="oa-run-order"')!==false && strpos($automationDetailHtml,'id="om-automation"')!==false && strpos($automationDetailHtml,'value="'.$manualRule.'"')!==false,'Order automation menu and panel render');
$automation->deleteRule($hookRule);
check(!$automation->rule($hookRule) && !$db->fetchColumn('SELECT id FROM om_rule_runs WHERE rule_id=:r',['r'=>$hookRule]),'Deleting automation removes its log');

// KSeF: FA(3) XML, RSA-OAEP and the full send flow against an in-memory fake of the KSeF API.
use App\Services\KsefService;
use App\Services\KsefClient;
check(KsefService::validNip('5252674798') && KsefService::validNip(KsefService::normalizeNip('PL 525-267-47-98')) && !KsefService::validNip('5252674797'),'KSeF NIP checksum');
$parsedBuyer=KsefService::parseBuyer("Anna Testowa\nFirma Test sp. z o.o.\nNIP: 525-267-47-98\nFirmowa 8\n00-002\nWarszawa\nPL");
check($parsedBuyer['name']==='Firma Test sp. z o.o.' && $parsedBuyer['nip']==='5252674798' && $parsedBuyer['country']==='PL' && $parsedBuyer['address']===['Firmowa 8','00-002 Warszawa'],'KSeF buyer parsing');
check(KsefService::parseBuyer("Jan Kowalski\nDługa 1\n00-001 Kraków")['nip']===null,'KSeF consumer buyer without NIP');
$ksefSnapshot=['seller'=>['name'=>'Firma testowa','address'=>"Testowa 1\n00-001 Warszawa",'nip'=>'5252674798','bank'=>'61 1090 1014 0000 0712 1981 2874'],'buyer'=>"Firma Test sp. z o.o.\nNIP 5252674798\nFirmowa 8, 00-002 Warszawa",'currency'=>'PLN','issue_date'=>'2026-09-17','sale_date'=>'2026-09-16','items'=>[['name'=>'Produkt','quantity'=>2,'unit_cents'=>1230,'vat'=>'23','net_cents'=>2000,'tax_cents'=>460,'gross_cents'=>2460],['name'=>'Książka','quantity'=>1,'unit_cents'=>1080,'vat'=>'8','net_cents'=>1000,'tax_cents'=>80,'gross_cents'=>1080]],'net_cents'=>3000,'tax_cents'=>540,'gross_cents'=>3540,'amount_paid_cents'=>3540,'payment_method'=>'Przelew','order_number'=>'ORD-1','split_payment'=>false,'series_notes'=>''];
$ksefXml=KsefService::buildInvoiceXml(['kind'=>'invoice','number'=>'FV/KSEF/1'],$ksefSnapshot,['generated_at'=>new DateTimeImmutable('2026-09-17T10:00:00Z')]);
check(strpos($ksefXml,'<P_13_1>20.00</P_13_1>')!==false && strpos($ksefXml,'<P_14_2>0.80</P_14_2>')!==false && strpos($ksefXml,'<P_15>35.40</P_15>')!==false && strpos($ksefXml,'<Zaplacono>1</Zaplacono>')!==false,'KSeF FA(3) invoice totals, payment and XSD validation');
$ksefFull=$ksefSnapshot; $ksefFull['seller']+=['swift'=>'BREXPLPWMBK','bank_name'=>'Mbank','email'=>'kontakt@example.pl','regon'=>'388377942','bdo'=>'000559182']; $ksefFull['items'][0]['sku']='ALTREO_1'; $ksefFull['recipient']="Jan Odbiorca\nDługa 1\n00-001 Kraków\nDostawa: KURIER"; $ksefFull['order_number']='ZAM-1'; $ksefFull['order_date']='2026-09-15';
$ksefFullXml=KsefService::buildInvoiceXml(['kind'=>'invoice','number'=>'FV/KSEF/2'],$ksefFull);
check(strpos($ksefFullXml,'<Rola>2</Rola>')!==false && strpos($ksefFullXml,'<Nazwa>Jan Odbiorca</Nazwa>')!==false && strpos($ksefFullXml,'<Indeks>ALTREO_1</Indeks>')!==false && strpos($ksefFullXml,'<P_9A>10.00</P_9A>')!==false && strpos($ksefFullXml,'<P_11>20.00</P_11>')!==false && strpos($ksefFullXml,'<SWIFT>BREXPLPWMBK</SWIFT>')!==false && strpos($ksefFullXml,'<NrZamowienia>ZAM-1</NrZamowienia>')!==false && strpos($ksefFullXml,'<BDO>000559182</BDO>')!==false && strpos($ksefFullXml,'<Email>kontakt@example.pl</Email>')!==false,'KSeF FA(3) recipient, SKU, net prices, bank, order and registers');
check(KsefService::qrUrl('sandbox','525-267-47-98','2026-09-17','ab+/cd==')==='https://qr-test.ksef.mf.gov.pl/invoice/5252674798/17-09-2026/ab-_cd','KSeF QR verification link');
$ksefCorrection=$ksefSnapshot; $ksefCorrection['items']=[$ksefSnapshot['items'][1]]; $ksefCorrection['before']=['items'=>$ksefSnapshot['items']]; $ksefCorrection['difference_cents']=-2460; $ksefCorrection['reason']='Zwrot towaru';
$ksefCorrectionXml=KsefService::buildInvoiceXml(['kind'=>'invoice_correction','number'=>'KOR/KSEF/1'],$ksefCorrection,['corrected'=>['number'=>'FV/KSEF/1','issue_date'=>'2026-09-17','ksef_number'=>'']]);
check(strpos($ksefCorrectionXml,'<RodzajFaktury>KOR</RodzajFaktury>')!==false && strpos($ksefCorrectionXml,'<P_13_1>-20.00</P_13_1>')!==false && substr_count($ksefCorrectionXml,'<StanPrzed>1</StanPrzed>')===2 && strpos($ksefCorrectionXml,'<NrKSeFN>1</NrKSeFN>')!==false,'KSeF FA(3) correction with state before');
$exempt=$ksefSnapshot; $exempt['items'][1]['vat']='zw';
rejects(fn()=>KsefService::buildInvoiceXml(['kind'=>'invoice','number'=>'FV/ZW'],$exempt),'KSeF exempt items require exemption basis');
check(strpos(KsefService::buildInvoiceXml(['kind'=>'invoice','number'=>'FV/ZW'],$exempt,['exemption_basis'=>'art. 113 ust. 1 ustawy o VAT']),'<P_19A>art. 113 ust. 1 ustawy o VAT</P_19A>')!==false,'KSeF exemption basis in XML');
rejects(fn()=>KsefService::buildInvoiceXml(['kind'=>'invoice','number'=>'FV/EUR'],['currency'=>'EUR']+$ksefSnapshot),'KSeF rejects foreign currency without rate');
rejects(fn()=>KsefService::buildInvoiceXml(['kind'=>'receipt','number'=>'PAR/1'],$ksefSnapshot),'KSeF rejects receipts');
rejects(fn()=>KsefService::validateXml('<Faktura xmlns="'.KsefService::NS.'"/>'),'KSeF XSD validation rejects incomplete XML');
$ksefPrivate=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
openssl_x509_export(openssl_csr_sign(openssl_csr_new(['commonName'=>'KSeF test'],$ksefPrivate),null,$ksefPrivate,30),$ksefPem);
$ksefCertificate=preg_replace('/-----[^-]+-----|\s+/','',$ksefPem);
$oaepDecrypt=static function (string $cipher) use ($ksefPrivate): string {
    // Reverse RSA-OAEP(SHA-256) manually so the check does not depend on the PHP version.
    openssl_private_decrypt($cipher,$em,$ksefPrivate,OPENSSL_NO_PADDING); $em=str_pad($em,256,"\0",STR_PAD_LEFT);
    $mgf=static function (string $seed,int $len): string { $m=''; for ($c=0;strlen($m)<$len;$c++) { $m.=hash('sha256',$seed.pack('N',$c),true); } return substr($m,0,$len); };
    $seed=substr($em,1,32)^$mgf(substr($em,33),32); $db=substr($em,33)^$mgf($seed,223);
    if (substr($db,0,32)!==hash('sha256','',true)) { throw new RuntimeException('OAEP label hash mismatch'); }
    return substr($db,strpos($db,"\x01",32)+1);
};
check($oaepDecrypt(KsefClient::rsaOaepEncrypt('token|1726560000000',$ksefCertificate))==='token|1726560000000','KSeF RSA-OAEP SHA-256 encryption');
$ksefCalls=[]; $ksefSessionKey=null; $ksefSentXml=null;
$ksefTransport=function (string $method,string $url,array $headers,?string $body) use (&$ksefCalls,&$ksefSessionKey,&$ksefSentXml,$ksefCertificate,$oaepDecrypt): array {
    $path=preg_replace('#^https://api-test\.ksef\.mf\.gov\.pl/v2#','',$url,1,$count);
    if ($count!==1) { throw new RuntimeException('Test transport called a non-sandbox URL: '.$url); }
    $ksefCalls[]=$method.' '.$path; $json=$body!==null?json_decode($body,true):null;
    $auth=$headers['Authorization']??'';
    switch ($method.' '.$path) {
        case 'GET /security/public-key-certificates': return [200,json_encode([['certificate'=>$ksefCertificate,'certificateId'=>'c1','publicKeyId'=>str_repeat('A',44),'usage'=>['KsefTokenEncryption','SymmetricKeyEncryption'],'validFrom'=>'2025-01-01T00:00:00Z','validTo'=>'2030-01-01T00:00:00Z']])];
        case 'POST /auth/challenge': return [200,json_encode(['challenge'=>str_repeat('c',36),'timestamp'=>'2026-09-17T10:00:00Z','timestampMs'=>1726560000000,'clientIp'=>'127.0.0.1'])];
        case 'POST /auth/ksef-token':
            if ($json['contextIdentifier']!==['type'=>'Nip','value'=>'5252674798'] || $oaepDecrypt(base64_decode($json['encryptedToken']))!=='sandbox-token|1726560000000') { return [400,'{"title":"bad token"}']; }
            return [202,json_encode(['referenceNumber'=>'AUTH-1','authenticationToken'=>['token'=>'auth-jwt','validUntil'=>'2030-01-01T00:00:00Z']])];
        case 'GET /auth/AUTH-1': return [$auth==='Bearer auth-jwt'?200:401,json_encode(['status'=>['code'=>200,'description'=>'Uwierzytelnianie zakończone sukcesem']])];
        case 'POST /auth/token/redeem': return [200,json_encode(['accessToken'=>['token'=>'access-jwt','validUntil'=>gmdate('c',time()+900)],'refreshToken'=>['token'=>'refresh-jwt','validUntil'=>'2030-01-01T00:00:00Z']])];
        case 'POST /sessions/online':
            if ($auth!=='Bearer access-jwt' || $json['formCode']!==KsefClient::FORM_CODE) { return [401,'{}']; }
            $ksefSessionKey=[$oaepDecrypt(base64_decode($json['encryption']['encryptedSymmetricKey'])),base64_decode($json['encryption']['initializationVector'])];
            return [201,json_encode(['referenceNumber'=>'SES-1','validUntil'=>'2030-01-01T00:00:00Z'])];
        case 'POST /sessions/online/SES-1/invoices':
            $ksefSentXml=openssl_decrypt(base64_decode($json['encryptedInvoiceContent']),'aes-256-cbc',$ksefSessionKey[0],OPENSSL_RAW_DATA,$ksefSessionKey[1]);
            if ($ksefSentXml===false || base64_encode(hash('sha256',$ksefSentXml,true))!==$json['invoiceHash']) { return [400,'{"title":"hash"}']; }
            return [202,json_encode(['referenceNumber'=>'INV-1'])];
        case 'GET /sessions/SES-1/invoices/INV-1': return [200,json_encode(['ordinalNumber'=>1,'referenceNumber'=>'INV-1','invoiceHash'=>'x','invoicingDate'=>'2026-09-17T10:00:00Z','ksefNumber'=>'5252674798-20260917-0123456789AB-CD','status'=>['code'=>200,'description'=>'Sukces']])];
        case 'POST /sessions/online/SES-1/close': return [204,''];
        case 'GET /sessions/SES-1/invoices/INV-1/upo': return [200,'<UPO>test</UPO>'];
    }
    return [404,'{"title":"not mocked"}'];
};
$repo->saveSetting('ksef',['environment'=>'sandbox','nip'=>'5252674798','auto_send'=>false,'exemption_basis'=>'','tokens'=>['sandbox'=>App\Services\OrderSecretBox::encrypt(['token'=>'sandbox-token']),'production'=>'']]);
$ksefService=new KsefService($repo,$ksefTransport,static function (int $ms): void { });
$ksefService->ensureSchema(); $ksefService->ensureSchema();
$ksefAccounts=$ksefService->accounts();
check(count($ksefAccounts)===1 && $ksefAccounts[0]['ready'] && $ksefAccounts[0]['series']===['Faktury','Korekty','Seria miesięczna'] && $repo->setting('ksef')===[],'Legacy KSeF settings migrated to one account assigned to invoice series');
$ksefAccountId=$ksefAccounts[0]['id'];
rejects(fn()=>$ksefService->saveAccount($ksefAccountId,['name'=>'Firma A','environment'=>'production','nip'=>'5252674798'],'test'),'KSeF production requires explicit confirmation');
check($ksefService->saveAccount($ksefAccountId,['name'=>'Firma A','environment'=>'sandbox','nip'=>'525-267-47-98','auto_send'=>'1'],'test')===$ksefAccountId,'KSeF account updated without re-entering token');
check(strpos((string)$db->fetchColumn('SELECT tokens_json FROM om_ksef_accounts WHERE id=:id',['id'=>$ksefAccountId]),'sandbox-token')===false && $ksefService->accounts()[0]['ready'],'KSeF account token kept encrypted');
$ksefAccountB=$ksefService->saveAccount(0,['name'=>'Firma B','environment'=>'sandbox','nip'=>'1234563218','token_sandbox'=>'company-b-token'],'test');
$seriesB=(int)$db->insert('om_series',['name'=>'Faktury firma B','kind'=>'invoice','pattern'=>'FB/{YYYY}/{N}','next_number'=>1,'document_settings_json'=>OrderRepository::json(['ksef_account_id'=>$ksefAccountB])]);
rejects(fn()=>$ksefService->deleteAccount($ksefAccountB),'KSeF account assigned to a series cannot be deleted');
$ksefDocId=(int)$db->insert('om_documents',['order_id'=>1,'series_id'=>1,'kind'=>'invoice','number'=>'FV/KSEF/1','request_key'=>str_repeat('k',40),'snapshot_json'=>OrderRepository::json($ksefSnapshot),'created_at'=>'2026-09-17 10:00:00']);
$ksefDocB=(int)$db->insert('om_documents',['order_id'=>1,'series_id'=>$seriesB,'kind'=>'invoice','number'=>'FB/KSEF/1','request_key'=>str_repeat('n',40),'snapshot_json'=>OrderRepository::json($ksefSnapshot),'created_at'=>'2026-09-17 10:00:00']);
$ksefTargets=$ksefService->targets([$ksefDocId,$ksefDocB]);
check($ksefTargets[$ksefDocId]['name']==='Firma A' && $ksefTargets[$ksefDocB]['name']==='Firma B','KSeF account resolved from document series');
$ksefCallsBefore=count($ksefCalls);
rejects(fn()=>$ksefService->send($ksefDocB,'test'),'KSeF blocks seller NIP different from series account NIP');
check(count($ksefCalls)===$ksefCallsBefore,'KSeF NIP mismatch stops before any API call');
$ksefSubmission=$ksefService->send($ksefDocId,'test');
check($ksefSubmission['state']==='accepted' && (int)$ksefSubmission['ksef_account_id']===$ksefAccountId && $ksefSubmission['ksef_number']==='5252674798-20260917-0123456789AB-CD' && strpos((string)$ksefSentXml,'<P_2>FV/KSEF/1</P_2>')!==false,'KSeF sandbox send accepted with KSeF number');
check(in_array('POST /sessions/online/SES-1/close',$ksefCalls,true),'KSeF session closed after sending');
rejects(fn()=>$ksefService->send($ksefDocId,'test'),'KSeF blocks duplicate send of accepted invoice');
$ksefRefreshed=$ksefService->refresh($ksefDocId,'test');
check($ksefRefreshed['upo']==='<UPO>test</UPO>' && $ksefService->upo($ksefDocId)['xml']==='<UPO>test</UPO>','KSeF UPO downloaded and stored');
check($ksefService->lockReason($ksefDocId)===null,'Sandbox submissions do not lock documents');
$db->update('om_series',['document_settings_json'=>OrderRepository::json([])],'id=2');
$ksefCallCount=count($ksefCalls); $ksefCorrectionDoc=(int)$db->insert('om_documents',['order_id'=>1,'series_id'=>2,'kind'=>'invoice_correction','number'=>'KOR/KSEF/1','parent_id'=>$ksefDocId,'request_key'=>str_repeat('m',40),'snapshot_json'=>OrderRepository::json($ksefCorrection),'created_at'=>'2026-09-17 11:00:00']);
check($ksefService->autoSend($ksefCorrectionDoc,'test')!==null && strpos((string)$ksefSentXml,'<NrKSeFFaKorygowanej>5252674798-20260917-0123456789AB-CD</NrKSeFFaKorygowanej>')!==false,'KSeF correction inherits invoice account and references corrected KSeF number');
check(!in_array('POST /auth/challenge',array_slice($ksefCalls,$ksefCallCount),true),'KSeF access token reused from encrypted cache');
$ksefInvalidMessage=$ksefService->autoSend(1,'test');
check(strpos((string)$ksefInvalidMessage,'KSeF: ')===0 && (bool)$db->fetchColumn("SELECT id FROM om_events WHERE order_id=1 AND message LIKE 'Automatyczna wysyłka do KSeF pominięta%'"),'KSeF auto-send reports invalid data without throwing');
$db->update('om_ksef_submissions',['environment'=>'production'],'document_id=:d',['d'=>$ksefDocId]);
check(strpos((string)$ksefService->lockReason($ksefDocId),'5252674798-20260917')!==false,'Production KSeF invoice is locked against edits');
$smarty->assign(['tab'=>'documents','detail'=>null,'series'=>$db->fetchAll('SELECT * FROM om_series'),'documents'=>$db->fetchAll('SELECT * FROM om_documents WHERE id=:id',['id'=>$ksefDocId]),'ksefAccounts'=>$ksefService->accounts(),'ksefTargets'=>$ksefService->targets([$ksefDocId]),'ksefSubmissions'=>$ksefService->latest([$ksefDocId])]);
$ksefHtml=$smarty->fetch('orders/index.tpl');
check(strpos($ksefHtml,'przyjęto w KSeF · 5252674798-20260917-0123456789AB-CD')!==false && strpos($ksefHtml,'name="ksef_account_id"')!==false && strpos($ksefHtml,'id="om-ksef"')===false,'KSeF document status and series account select render on documents tab');
$smarty->assign('tab','general');
$ksefGeneralHtml=$smarty->fetch('orders/index.tpl');
check(strpos($ksefGeneralHtml,'id="om-ksef-'.$ksefAccountB.'"')!==false && strpos($ksefGeneralHtml,'Faktury firma B')!==false && strpos($ksefGeneralHtml,'sandbox-token')===false && strpos($ksefGeneralHtml,'company-b-token')===false,'KSeF accounts render in general settings without secrets');
// Multiple internal notes: legacy single note adopted, add/edit/delete, webhook replaces its own notes.
$notesOrderId=(int)$db->fetchColumn('SELECT id FROM om_orders WHERE external_id=:e',['e'=>'auto-2']);
$db->update('om_orders',['note'=>'Stara notatka'],'id=:id',['id'=>$notesOrderId]);
$legacyNotes=$repo->notes($notesOrderId);
check(count($legacyNotes)===1 && $legacyNotes[0]['body']==='Stara notatka' && $legacyNotes[0]['source']==='legacy','Legacy single note becomes first note');
$firstNote=$repo->addNote($notesOrderId,"  Druga\r\nlinia  ",'Tester');
check(count($repo->notes($notesOrderId))===2 && $repo->notes($notesOrderId)[1]['body']==="Druga\nlinia",'Note added and normalized');
check($repo->order($notesOrderId)['note']==="Stara notatka\n\nDruga\nlinia",'Order note column mirrors notes for search and conditions');
$repo->updateNote($notesOrderId,$firstNote,'Poprawiona','Tester');
check($repo->notes($notesOrderId)[1]['body']==='Poprawiona','Note edited');
$repo->updateNote($notesOrderId,$firstNote,'Poprawiona','Tester');
rejects(fn()=>$repo->updateNote($notesOrderId,$firstNote,'   ','Tester'),'Empty note rejected');
rejects(fn()=>$repo->updateNote($autoId,$firstNote,'Obce','Tester'),'Note cannot be edited through another order');
rejects(fn()=>$repo->deleteNote($autoId,$firstNote),'Note cannot be deleted through another order');
check($repo->replaceSourceNotes($notesOrderId,'webhook:9',['Spec A',"Specyfikacja\nX /",''],'Automat')===2,'Webhook notes stored');
$repo->replaceSourceNotes($notesOrderId,'webhook:9',['Spec B'],'Automat');
check(array_column($repo->notes($notesOrderId),'body')===['Stara notatka','Poprawiona','Spec B'],'Webhook re-run replaces only its own notes');
$repo->deleteNote($notesOrderId,$firstNote);
check(array_column($repo->notes($notesOrderId),'body')===['Stara notatka','Spec B'] && $repo->order($notesOrderId)['note']==="Stara notatka\n\nSpec B",'Note deleted and mirror updated');
$webhookNotes=new ReflectionMethod(App\Services\OrderAutomationService::class,'webhookNotes'); if (PHP_VERSION_ID<80100) { $webhookNotes->setAccessible(true); }
$automationForNotes=$repo->automation();
check($webhookNotes->invoke($automationForNotes,'{"ok":true,"notes":["A"," ",5,"B"]}')===['A','B'] && $webhookNotes->invoke($automationForNotes,'{"note":"C"}')===['C'],'Webhook reply notes parsed');
check($webhookNotes->invoke($automationForNotes,'OK')===null && $webhookNotes->invoke($automationForNotes,'{"notes":[]}')===null,'Webhook reply without notes ignored');
$notesDetail=$repo->order($notesOrderId); $notesDetail['notes']=$repo->notes($notesOrderId);
$smarty->assign(['tab'=>'list','detail'=>$notesDetail,'orderShipments'=>[]]);
$notesHtml=$smarty->fetch('orders/index.tpl');
preg_match('/data-notes="([^"]*)"/',$notesHtml,$notesAttr);
check(array_column(json_decode(html_entity_decode($notesAttr[1]??'',ENT_QUOTES,'UTF-8'),true)?:[],'body')===['Stara notatka','Spec B'],'Notes JSON attribute decodes');
check(strpos($notesHtml,'data-order-notes')!==false && strpos($notesHtml,'Spec B')!==false && strpos($notesHtml,'textarea name="note"')===false,'Notes panel renders with notes data');
echo "OK: $checks checks; no network or production database used.\n";
