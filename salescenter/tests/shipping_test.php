<?php
/** Moduły operatorów przesyłek na atrapie HTTP i SQLite w pamięci. Brak połączeń sieciowych. */
declare(strict_types=1);
define('BASE_PATH',dirname(__DIR__));
require BASE_PATH.'/app/bootstrap.php';
use App\Core\Database;
use App\Core\Tenant;
use App\Models\ConnectionRepository;
use App\Models\OrderRepository;
use App\Services\Integrations\Http;
use App\Services\OrderNormalizer;
use App\Services\OrderSecretBox;
use App\Services\OrderShipmentService;
use App\Services\Shipping\ShippingProviders;

$checks=0;
function check(bool $condition,string $label): void { global $checks; $checks++; if (!$condition) { throw new RuntimeException($label); } }
function rejects(callable $fn,string $label,string $contains=''): void { try { $fn(); } catch (\Throwable $e) { check($contains==='' || strpos($e->getMessage(),$contains)!==false,$label.' ('.$e->getMessage().')'); return; } check(false,$label); }

$reflection=new ReflectionClass(Database::class);
$db=$reflection->newInstanceWithoutConstructor();
$pdo=new PDO('sqlite::memory:'); $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
foreach (['pdo'=>$pdo,'config'=>['driver'=>'sqlite','database'=>'test']] as $name=>$value) { $property=$reflection->getProperty($name); $property->setValue($db,$value); }
Database::useInstance($db);
Tenant::activate(1);
$repo=new OrderRepository($db); $repo->ensureSchema();
$connections=new ConnectionRepository($db); $connections->ensureSchema();

$calls=[]; $responses=[];
Http::$transport=function (string $method,string $url,array $headers,?string $body) use (&$calls,&$responses): array {
    $calls[]=compact('method','url','headers','body');
    foreach ($responses as [$pattern,$status,$payload]) {
        if (preg_match($pattern,$method.' '.$url)) { return ['status'=>$status,'body'=>is_string($payload)?$payload:json_encode($payload),'headers'=>[]]; }
    }
    return ['status'=>404,'body'=>'{"message":"not mocked"}','headers'=>[]];
};

// Rejestr: każdy moduł ma komplet opisu dla UI.
foreach (array_keys(ShippingProviders::CLASSES) as $key) {
    $definition=ShippingProviders::definition($key);
    check($definition['key']===$key && $definition['label']!=='' && $definition['info'] && isset($definition['capabilities']['cod'],$definition['capabilities']['source_tracking']),'Definition complete: '.$key);
    check(in_array('name',array_column($definition['fields'],'name'),true),'Connection name field: '.$key);
    foreach ($definition['fields'] as $field) { check(in_array($field['type'],['text','password','number','select','source_account'],true),'Known field type: '.$key.'.'.$field['name']); }
}
check(ShippingProviders::exists('erli_shipping') && !ShippingProviders::exists('unknown'),'Registry lookup');

// Konfiguracja generyczna: sekrety oddzielone od danych publicznych.
$inpost=ShippingProviders::get($repo,'inpost_shipx')->configure(['name'=>'InPost','organization_id'=>'123','token'=>'tok-SECRET','environment'=>'hack']);
check($inpost['secret']===['token'=>'tok-SECRET'] && $inpost['public']['organization_id']==='123' && $inpost['public']['environment']==='production' && !isset($inpost['public']['token']),'Password fields go to secret, invalid select falls back');
rejects(function () use ($repo) { ShippingProviders::get($repo,'apaczka')->configure(['name'=>'A','app_id'=>'x']); },'Required secret enforced','App Secret');
$kept=ShippingProviders::get($repo,'inpost_shipx')->configure(['name'=>'InPost','organization_id'=>'456','token'=>''],['token'=>'tok-OLD']);
check($kept['secret']['token']==='tok-OLD' && $kept['public']['organization_id']==='456','Blank secret on edit keeps stored secret');

// Sklep ERLI podłączony w zakładce Konta.
$erliConnection=$connections->create('erli','ERLI · Sklep',['shop_id'=>'9'],['api_key'=>'ERLI-KEY']);
$erliAccountId=(int)$db->fetchColumn('SELECT id FROM om_accounts WHERE platform=:p AND source_id=:s',['p'=>'erli','s'=>$erliConnection]);
$repo->registerAccount('allegro',1,'Allegro A');
$allegroAccountId=(int)$db->fetchColumn('SELECT id FROM om_accounts WHERE platform=:p',['p'=>'allegro']);
rejects(function () use ($repo) { ShippingProviders::get($repo,'erli_shipping')->configure(['name'=>'Erli','erli_account_id'=>'999']); },'Erli carrier requires ERLI shop','ERLI');
$responses=[['#GET .*/shipping/postingPoints#',200,[['id'=>11,'name'=>'Magazyn','companyName'=>'Firma','phone'=>'500500500','email'=>'m@example.invalid','street'=>'Magazynowa','buildingNumber'=>'1','zip'=>'00-001','city'=>'Warszawa','isDefault'=>true,'type'=>'address']]]];
$erliConfig=ShippingProviders::get($repo,'erli_shipping')->configure(['name'=>'Wysyłam z Erli','erli_account_id'=>(string)$erliAccountId,'posting_point_id'=>'']);
check($erliConfig['public']['order_account_id']===$erliAccountId && $erliConfig['public']['posting_points'][0]['id']===11 && $erliConfig['public']['posting_points'][0]['default'] && $erliConfig['secret']===[],'Erli account verified with posting points');
check(in_array('Authorization: Bearer ERLI-KEY',end($calls)['headers'],true),'Erli uses API key of the source shop');
rejects(function () use ($repo,$erliAccountId) { ShippingProviders::get($repo,'erli_shipping')->configure(['name'=>'Erli 2','erli_account_id'=>(string)$erliAccountId,'posting_point_id'=>'77']); },'Unknown posting point rejected','77');
$erliCarrierId=(int)$db->insert('om_carrier_accounts',['provider'=>'erli_shipping','name'=>'Wysyłam z Erli','enabled'=>1,'public_config_json'=>OrderRepository::json($erliConfig['public']),'secret_config_json'=>OrderSecretBox::encrypt([]),'updated_at'=>gmdate('Y-m-d H:i:s')]);
$db->insert('om_carrier_accounts',['provider'=>'allegro_wza','name'=>'WzA','enabled'=>1,'public_config_json'=>OrderRepository::json(['order_account_id'=>$allegroAccountId]),'secret_config_json'=>OrderSecretBox::encrypt([]),'updated_at'=>gmdate('Y-m-d H:i:s')]);
$inpostCarrierId=(int)$db->insert('om_carrier_accounts',['provider'=>'inpost_shipx','name'=>'InPost','enabled'=>1,'public_config_json'=>OrderRepository::json($inpost['public']),'secret_config_json'=>OrderSecretBox::encrypt($inpost['secret']),'updated_at'=>gmdate('Y-m-d H:i:s')]);

// Zamówienie ERLI.
$now=time();
$raw=['id'=>'260918x0001','status'=>'purchased','created'=>gmdate('c',$now-3600),'updated'=>gmdate('c',$now-3600),'totalPrice'=>4999,'currency'=>'PLN','user'=>['email'=>'k@example.invalid','deliveryAddress'=>['firstName'=>'Jan','lastName'=>'Kowalski','address'=>'Leśna 5','street'=>'Leśna','buildingNumber'=>'5','zip'=>'00-950','city'=>'Warszawa','country'=>'pl','phone'=>'600600600']],'items'=>[['name'=>'Produkt','quantity'=>1,'unitPrice'=>4999]],'delivery'=>['name'=>'Kurier InPost (Wysyłam z Erli)','typeId'=>'erliKurier24InPost','price'=>0,'cod'=>false],'payment'=>['status'=>'COMPLETED']];
$repo->import($erliAccountId,OrderNormalizer::normalize('erli',$raw,$now-86400*30,$now));
$orderId=(int)$db->fetchColumn('SELECT id FROM om_orders WHERE external_id=:e',['e'=>'260918x0001']);
check($orderId>0,'Erli order imported');
$order=$repo->order($orderId);

$suggestion=OrderShipmentService::suggestion($repo,$order,$repo->carrierAccounts(),OrderShipmentService::defaults($repo));
check($suggestion['carrier_account_id']===$erliCarrierId && strpos($suggestion['reason'],'Wysyłam z Erli')!==false,'Erli order suggests Wysyłam z Erli account');
check(!ShippingProviders::get($repo,'allegro_wza')->supportsOrder($order,['order_account_id'=>$allegroAccountId]),'Allegro WzA refuses non-Allegro order');

$service=new OrderShipmentService($repo);
$responses=[['#GET .*/dictionaries/shippingMethods#',200,[['id'=>'erliKurier24InPost10kg','name'=>'Kurier InPost do 10 kg','groupId'=>'erliKurier24InPost','operator'=>'INPOST','cod'=>false],['id'=>'erliDHLDE1kg','name'=>'DHL DE','groupId'=>'erliDHLDE','operator'=>'DHL','cod'=>false]]]];
$options=$service->options($orderId,$erliCarrierId);
check(count($options['options'])===1 && $options['options'][0]['carrier']==='InPost' && $options['automatic']!=='' && !$options['valuation'] && $options['cod']==='order','Erli services filtered to parcel methods with automatic option');

// Nadanie: metoda z zamówienia (grupa) → najmniejszy pasujący wariant wagowy.
$calls=[];
$responses=[
    ['#GET .*/orders/260918x0001$#',200,$raw],
    ['#POST .*/shipping/parcels/$#',200,[['id'=>501,'type'=>'internal','orderId'=>'260918x0001','status'=>'preparing','dimensions'=>[],'shipping'=>['typeId'=>'erliKurier24InPost15kg'],'createdAt'=>gmdate('c'),'updatedAt'=>gmdate('c')]]],
];
$key=str_repeat('ab',20);
$input=['length'=>'30','width'=>'20','height'=>'15','weight'=>'12','request_key'=>$key,'shipping_service'=>'','shipment_content'=>'Towar'];
$shipmentId=$service->create($orderId,$erliCarrierId,$input,'test');
$post=array_values(array_filter($calls,fn($c)=>$c['method']==='POST'))[0];
$sent=json_decode((string)$post['body'],true)[0];
check($sent['orderId']==='260918x0001' && $sent['shipping']['typeId']==='erliKurier24InPost15kg' && $sent['dimensions']===['length'=>300,'width'=>200,'height'=>150,'weight'=>12000],'Erli parcel sent in mm/grams with weight-matched method');
check(!isset($sent['shipping']['receiver']) && strpos($sent['shipping']['additionalInformation'],'zamówienie ID '.$orderId)!==false,'Erli takes receiver from order and prints order reference');
$row=$db->fetch('SELECT * FROM om_shipments WHERE id=:id',['id'=>$shipmentId]);
check($row['external_id']==='501' && $row['state']==='preparing' && strpos($row['tracking'],'PENDING:')===0,'Erli shipment stored pending tracking');
$calls=[];
check($service->create($orderId,$erliCarrierId,$input,'test')===$shipmentId && !$calls,'Shipment creation is idempotent per request key');

// Odświeżenie: numer, status camelCase, protokół odbioru.
$responses=[['#GET .*/shipping/parcels/501$#',200,['id'=>501,'type'=>'internal','orderId'=>'260918x0001','status'=>'readyToSend','statusHistory'=>[['status'=>'preparing','changed'=>'2026-09-18T08:00:00Z'],['status'=>'readyToSend','changed'=>'2026-09-18T08:05:00Z']],'trackingNumber'=>'6200000000001','shipping'=>['typeId'=>'erliKurier24InPost15kg','waybills'=>['https://files.erli.pl/w/501.pdf'],'pickupProtocol'=>'https://files.erli.pl/p/501.pdf'],'dimensions'=>[],'createdAt'=>gmdate('c'),'updatedAt'=>gmdate('c')]]];
$service->refresh($shipmentId,'test');
$row=$db->fetch('SELECT s.*,ca.provider carrier_provider FROM om_shipments s JOIN om_carrier_accounts ca ON ca.id=s.carrier_account_id WHERE s.id=:id',['id'=>$shipmentId]);
$presentation=OrderShipmentService::presentation($row,$order);
check($row['tracking']==='6200000000001' && $presentation['status_label']==='Gotowa do nadania' && $presentation['status_updated_at']==='2026-09-18 08:05 UTC','Erli refresh updates tracking and camelCase status');
check($presentation['provider_label']==='Wysyłam z Erli' && $presentation['source_tracking_auto'] && $presentation['can_cancel'] && $presentation['pickup_protocol'] && !$presentation['tracking_pending'],'Erli capabilities exposed in presentation');

// Błąd ERLI trafia do użytkownika czytelnie.
$responses=[['#POST .*/shipping/parcels/$#',400,[['errorCode'=>1211,'errorMessage'=>'Nieprawidłowa waga bądź wymiary paczki dla wybranej metody dostawy']]]];
rejects(function () use ($service,$orderId,$erliCarrierId,$input) { $service->create($orderId,$erliCarrierId,['request_key'=>str_repeat('cd',20),'shipping_service'=>'erliKurier24InPost10kg']+$input,'test'); },'Erli validation error surfaced','1211');

// Anulowanie: moduł z możliwością cancel, InPost bez.
$calls=[];
$responses=[['#DELETE .*/shipping/parcels/501$#',200,['id'=>501,'status'=>'canceled']]];
$service->cancel($shipmentId,'test');
check(end($calls)['method']==='DELETE' && $db->fetchColumn('SELECT state FROM om_shipments WHERE id=:id',['id'=>$shipmentId])==='CANCELLED','Erli parcel cancelled');
$row=$db->fetch('SELECT s.*,ca.provider carrier_provider FROM om_shipments s JOIN om_carrier_accounts ca ON ca.id=s.carrier_account_id WHERE s.id=:id',['id'=>$shipmentId]);
$cancelled=OrderShipmentService::presentation($row,$order);
check(!$cancelled['can_cancel'] && !$cancelled['can_label'] && !$cancelled['pickup_protocol'] && $cancelled['status_tone']==='cancelled','Cancelled shipment hides actions');
$inpostShipment=(int)$db->insert('om_shipments',['order_id'=>$orderId,'carrier_account_id'=>$inpostCarrierId,'carrier'=>'InPost','tracking'=>'X1','weight'=>'1','state'=>'created','external_id'=>'9','payload_json'=>'{}','created_at'=>gmdate('Y-m-d H:i:s')]);
rejects(function () use ($service,$inpostShipment) { $service->cancel($inpostShipment,'test'); },'Cancel refused for module without cancel capability','anulowanie');

// Zakładka Przesyłki renderuje karty z rejestru i nie ujawnia sekretów.
$catalog=ShippingProviders::catalog($repo->carrierAccounts(),$repo->accounts());
check(array_column($catalog,'key')===array_keys(ShippingProviders::CLASSES),'Catalog follows registry order');
$smarty=App\Core\SmartyFactory::create();
$smarty->setCompileDir(sys_get_temp_dir().'/om-smarty-shipping-test');
$register=$db->fetchAll('SELECT s.*,ca.provider carrier_provider FROM om_shipments s LEFT JOIN om_carrier_accounts ca ON ca.id=s.carrier_account_id ORDER BY s.id DESC');
foreach ($register as &$registered) { $registered['presentation']=OrderShipmentService::presentation($registered,['details'=>[]]); } unset($registered);
$smarty->assign(['tab'=>'shipments','csrf'=>'t','canWrite'=>true,'flashSuccess'=>null,'flashError'=>null,'dashboard'=>$repo->dashboard(),'statusGroups'=>[],'accounts'=>$repo->accounts(),'statuses'=>$repo->statuses(),'detail'=>null,'carrierAccounts'=>$repo->carrierAccounts(),'shippingProviders'=>$catalog,'shipments'=>$register,'shippingDefaults'=>OrderShipmentService::defaults($repo),'documentDefaults'=>['vat'=>'23'],'listing'=>['rows'=>[],'total'=>0,'page'=>1,'pages'=>1],'filters'=>['q'=>'','status_id'=>'','group'=>'','account_id'=>'','platform'=>'','paid'=>'','date_from'=>'','date_to'=>'','amount_from'=>'','amount_to'=>'','sort'=>'newest'],'listQuery'=>'','activeFilterCount'=>0]);
$html=$smarty->fetch('orders/index.tpl');
check(strpos($html,'data-setup="erli_shipping"')!==false && strpos($html,'name="erli_account_id"')!==false && strpos($html,'id="sc-carrier-'.$erliCarrierId.'"')!==false && strpos($html,'Punkty nadania (1)')!==false,'Erli connection and setup dialog render like sales channels');
check(strpos($html,'placeholder="•••••• (bez zmian)"')!==false && strpos($html,'name="carrier_account_id" value="'.$inpostCarrierId.'"')!==false,'Existing account edit form keeps secrets hidden');
check(substr_count($html,'data-pick=')===count(ShippingProviders::CLASSES),'Picker tile per module');
check(strpos($html,'Numer trafia do źródła sam')!==false && strpos($html,'Protokół odbioru')!==false && strpos($html,'Anulowana')!==false,'Capabilities and register status render');
check(strpos($html,'tok-SECRET')===false && strpos($html,'ERLI-KEY')===false,'Shipments tab never renders secrets');

Http::$transport=null;
echo "OK: $checks shipping checks; no network or production database used.\n";
