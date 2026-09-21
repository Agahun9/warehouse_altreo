<?php
/** Konektory kanałów sprzedaży na atrapie HTTP i SQLite w pamięci. Brak połączeń sieciowych. */
declare(strict_types=1);
define('BASE_PATH',dirname(__DIR__));
require BASE_PATH.'/app/bootstrap.php';
use App\Core\Database;
use App\Core\Tenant;
use App\Models\ConnectionRepository;
use App\Models\OrderRepository;
use App\Services\Integrations\Http;
use App\Services\OrderNormalizer;
use App\Services\OrderSyncService;

$checks=0;
function check(bool $condition,string $label): void { global $checks; $checks++; if (!$condition) { throw new RuntimeException($label); } }
function rejects(callable $fn,string $label): void { try { $fn(); } catch (\Throwable $e) { check(true,$label); return; } check(false,$label); }

$reflection=new ReflectionClass(Database::class);
$db=$reflection->newInstanceWithoutConstructor();
$pdo=new PDO('sqlite::memory:'); $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
foreach (['pdo'=>$pdo,'config'=>['driver'=>'sqlite','database'=>'test']] as $name=>$value) { $property=$reflection->getProperty($name); $property->setValue($db,$value); }
Database::useInstance($db);
App\Core\Config::override('app',['app_name'=>'SalesCenter','base_url'=>'./index.php','public_base_url'=>'https://panel.example.invalid/index.php','encryption_key'=>str_repeat('ab',32),'integrations'=>['allegro'=>['client_id'=>'cid','client_secret'=>'secret']]]);

Tenant::activate(1);
$repo=new OrderRepository($db); $repo->ensureSchema();
$connections=new ConnectionRepository($db); $connections->ensureSchema();

// Transport HTTP: kolejka odpowiedzi + zapis żądań.
$calls=[]; $responses=[];
Http::$transport=function (string $method,string $url,array $headers,?string $body) use (&$calls,&$responses): array {
    $calls[]=compact('method','url','headers','body');
    foreach ($responses as $index=>[$pattern,$status,$payload]) {
        if (preg_match($pattern,$method.' '.$url)) { return ['status'=>$status,'body'=>is_string($payload)?$payload:json_encode($payload),'headers'=>['x-wp-total'=>'1']]; }
    }
    return ['status'=>404,'body'=>'{"message":"not mocked"}','headers'=>[]];
};

// Połączenie i szyfrowanie sekretów.
$empikId=$connections->create('empik','Empik · Test',['shop_id'=>'5','remote_id'=>'77'],['api_key'=>'SECRET-KEY']);
$row=$db->fetch('SELECT * FROM om_connections WHERE id=:id',['id'=>$empikId]);
check(strpos((string)$row['secret_json'],'SECRET-KEY')===false,'API key is stored encrypted');
check($connections->credentials($row)['api_key']==='SECRET-KEY','API key decrypts for requests');
$empikAccount=$connections->account($empikId);
check($empikAccount && (int)$empikAccount['enabled']===1 && $empikAccount['platform']==='empik','New connection creates an enabled import account');
check(count((new App\Services\EmpikService())->listAccounts())===1,'Connector lists company connections');

// Mirakl: nagłówek, shop_id, akceptacja.
$responses=[['#GET https://marketplace\.empik\.com/api/account#',200,['shop_id'=>77,'shop_name'=>'Księgarnia']]];
$test=(new App\Services\EmpikService())->testConnection(['api_key'=>'K1','shop_id'=>'5']);
check($test['name']==='Empik · Księgarnia' && $test['remote_id']==='77','Mirakl test reads shop name');
check(in_array('Authorization: K1',$calls[0]['headers'],true) && strpos($calls[0]['url'],'shop_id=5')!==false,'Mirakl sends API key and shop_id');
$responses=[['#PUT .*/api/orders/E-1/accept#',204,'']];
(new App\Services\EmpikService())->acceptOrder(['api_key'=>'K1'],['order_id'=>'E-1','order_lines'=>[['order_line_id'=>'E-1-1']]]);
check(json_decode((string)end($calls)['body'],true)==['order_lines'=>[['id'=>'E-1-1','accepted'=>true]]],'Mirakl OR21 accepts every line');
$responses=[['#GET https://marketplace\.empik\.com/api/account#',401,['message'=>'Unauthorized']]];
rejects(fn()=>(new App\Services\EmpikService())->testConnection(['api_key'=>'bad']),'Invalid Mirakl key is rejected');
check(App\Services\OrderSyncError::describe(new RuntimeException('Empik API error [401]: Unauthorized'))['code']==='MARKETPLACE_AUTH','Connector errors are classified');

// WooCommerce → format wspólny → normalizacja i import.
$wooOrder=['id'=>501,'number'=>'501','status'=>'processing','currency'=>'PLN','total'=>'135.00','date_created_gmt'=>gmdate('Y-m-d\TH:i:s',time()-3600),'date_paid_gmt'=>gmdate('Y-m-d\TH:i:s',time()-3500),'payment_method'=>'bacs','payment_method_title'=>'Przelew','shipping_total'=>'12.20','shipping_tax'=>'2.80','customer_note'=>'Szybko proszę',
    'billing'=>['first_name'=>'Jan','last_name'=>'Nowak','company'=>'Nowak sp.','email'=>'jan@example.invalid','phone'=>'500','address_1'=>'Polna 1','postcode'=>'00-950','city'=>'Warszawa','country'=>'PL'],
    'shipping'=>['first_name'=>'Jan','last_name'=>'Nowak','address_1'=>'Leśna 5','postcode'=>'30-001','city'=>'Kraków','country'=>'PL'],
    'meta_data'=>[['key'=>'_billing_nip','value'=>'5252674798']],'shipping_lines'=>[['method_title'=>'Kurier DPD']],
    'line_items'=>[['name'=>'Lampa','sku'=>'L-1','quantity'=>2,'total'=>'97.56','total_tax'=>'22.44','image'=>['src'=>'https://img.example.invalid/l.jpg']]]];
$responses=[['#GET https://sklep\.example\.invalid/wp-json/wc/v3/orders\?#',200,[$wooOrder,['id'=>502,'status'=>'checkout-draft']]]];
$page=(new App\Services\WooCommerceService())->readOrderPage(['shop_url'=>'https://sklep.example.invalid','consumer_key'=>'ck_1','consumer_secret'=>'cs_1','connection_id'=>0],gmdate('Y-m-d\TH:i:s\Z',time()-86400),gmdate('Y-m-d\TH:i:s\Z'));
check(count($page['orders'])===1 && $page['page_size']===2,'WooCommerce skips drafts but pages by full page size');
check(strpos(end($calls)['url'],'consumer_key=ck_1')!==false && strpos(end($calls)['url'],'modified_after=')!==false,'WooCommerce request carries keys and update filter');
$normalized=OrderNormalizer::normalize('woocommerce',$page['orders'][0],time()-86400,time());
check($normalized['total_cents']===13500 && $normalized['paid']===1 && $normalized['details']['items'][0]['unit_cents']===6000,'WooCommerce gross prices and total');
check($normalized['details']['invoice_required']===1 && $normalized['details']['invoice_address']['tax_id']==='5252674798','WooCommerce NIP from meta requests invoice');
check($normalized['details']['address']['city']==='Kraków' && $normalized['buyer_name']==='Jan Nowak' && $normalized['details']['shipping_cents']===1500,'WooCommerce shipping address and cost');
$responses=[['#POST https://sklep\.example\.invalid/wp-json/wc/v3/orders/501/notes#',201,['id'=>1]]];
(new App\Services\WooCommerceService())->publishOrderShipment(['shop_url'=>'https://sklep.example.invalid','consumer_key'=>'ck_1','consumer_secret'=>'cs_1'],'501','TRACK123','dpd','DPD');
check(strpos((string)end($calls)['body'],'TRACK123')!==false && json_decode((string)end($calls)['body'],true)['customer_note']===true,'WooCommerce tracking becomes customer note');
check(strpos(App\Services\WooCommerceService::authorizeUrl('https://sklep.example.invalid','t1.abc','https://r','https://c'),'/wc-auth/v1/authorize?app_name=SalesCenter&scope=read_write&user_id=t1.abc')!==false,'WooCommerce login URL');

// PrestaShop.
$responses=[
    ['#GET https://presta\.example\.invalid/api/orders\?#',200,['orders'=>[['id'=>9,'reference'=>'XKBKNABJK','id_customer'=>3,'id_address_delivery'=>4,'id_address_invoice'=>4,'id_currency'=>1,'current_state'=>2,'id_carrier'=>7,'payment'=>'Płatność przy odbiorze','module'=>'ps_cashondelivery','total_paid_tax_incl'=>'61.500000','total_shipping_tax_incl'=>'11.500000','date_add'=>date('Y-m-d H:i:s',time()-7200),'associations'=>['order_rows'=>[['product_name'=>'Kubek','product_reference'=>'KUB','product_quantity'=>'2','unit_price_tax_incl'=>'25.000000']]]]]]],
    ['#/api/addresses/4#',200,['address'=>['firstname'=>'Ewa','lastname'=>'Lis','address1'=>'Długa 3','postcode'=>'80-001','city'=>'Gdańsk','id_country'=>14,'phone_mobile'=>'600']]],
    ['#/api/customers/3#',200,['customer'=>['email'=>'ewa@example.invalid']]],
    ['#/api/order_states/2#',200,['order_state'=>['name'=>[['id'=>1,'value'=>'Płatność przyjęta']],'paid'=>'1']]],
    ['#/api/carriers/7#',200,['carrier'=>['name'=>'InPost']]],
    ['#/api/currencies/1#',200,['currency'=>['iso_code'=>'PLN']]],
    ['#/api/countries/14#',200,['country'=>['iso_code'=>'PL']]],
];
$presta=(new App\Services\PrestaShopService())->readOrderPage(['shop_url'=>'https://presta.example.invalid','api_key'=>'WSKEY','connection_id'=>1],gmdate('Y-m-d\TH:i:s\Z',time()-86400),gmdate('Y-m-d\TH:i:s\Z'));
$prestaOrder=OrderNormalizer::normalize('prestashop',$presta['orders'][0],time()-86400,time());
check($prestaOrder['total_cents']===6150 && $prestaOrder['remote_status']==='Płatność przyjęta' && $prestaOrder['details']['cash_on_delivery']===1 && $prestaOrder['paid']===0,'PrestaShop order, state name and COD');
check($prestaOrder['email']==='ewa@example.invalid' && $prestaOrder['details']['address']['city']==='Gdańsk' && $prestaOrder['details']['delivery']==='InPost','PrestaShop customer, address and carrier');
check(in_array('Authorization: Basic '.base64_encode('WSKEY:'),$calls[count($calls)-7]['headers'],true),'PrestaShop uses webservice key');

// Temu: podpis i format wspólny.
check(App\Services\TemuService::signature(['b'=>'2','a'=>'1','sign'=>'x'],'S')===strtoupper(md5('Sa1b2S')),'Temu signature sorts parameters and wraps secret');
$responses=[
    ['#openapi#',200,['success'=>true,'result'=>['totalItemNum'=>1,'pageItems'=>[['parentOrderMap'=>['parentOrderSn'=>'PO-1','parentOrderStatus'=>2,'parentOrderTime'=>time()-600,'orderPaymentType'=>'PPD'],'orderList'=>[['goodsName'=>'Etui','spec'=>'Czarne','quantity'=>1,'skuId'=>55,'thumbUrl'=>'https://img.example.invalid/e.jpg','productList'=>[['extCode'=>'ETUI-1']]]]]]]]],
];
$temu=(new App\Services\TemuService())->readOrderPage(['app_key'=>'k','app_secret'=>'s','access_token'=>'t'],gmdate('Y-m-d\TH:i:s\Z',time()-86400),gmdate('Y-m-d\TH:i:s\Z'));
$temuOrder=OrderNormalizer::normalize('temu',$temu['orders'][0],time()-86400,time());
check($temuOrder['external_id']==='PO-1' && $temuOrder['remote_status']==='UN_SHIPPING' && $temuOrder['paid']===1 && $temuOrder['details']['items'][0]['sku']==='ETUI-1','Temu parent order mapped');
$responses=[['#openapi#',200,['success'=>false,'errorCode'=>'2000010','errorMsg'=>'token invalid']]];
rejects(fn()=>(new App\Services\TemuService())->testConnection(['app_key'=>'k','app_secret'=>'s','access_token'=>'t']),'Temu API error surfaces');

// Allegro: odświeżenie tokenu zapisuje nowe dane.
$allegroId=$connections->create('allegro','Allegro · sprzedawca',['login'=>'sprzedawca','remote_id'=>'123','seller_id'=>'123'],['access_token'=>'old','refresh_token'=>'r1','expires_at'=>time()-10,'redirect_uri'=>'https://panel.example.invalid/allegro-callback.php']);
$responses=[
    ['#POST https://allegro\.pl/auth/oauth/token#',200,['access_token'=>'new-token','refresh_token'=>'r2','expires_in'=>43200]],
    ['#GET https://api\.allegro\.pl/me#',200,['id'=>'123','login'=>'sprzedawca']],
];
$allegro=new App\Services\AllegroService(true);
$account=$allegro->listAccounts()[0];
check($allegro->testConnection($account)['remote_id']==='123','Allegro reads seller after refresh');
check(in_array('Authorization: Bearer new-token',end($calls)['headers'],true),'Allegro uses refreshed token');
check($connections->credentials($connections->find($allegroId))['refresh_token']==='r2','Allegro refresh token rotated in storage');
$responses=[['#POST https://allegro\.pl/auth/oauth/token#',200,['access_token'=>'daily-token','refresh_token'=>'r3','expires_in'=>43200]]];
$allegro->refreshAccessToken($allegro->listAccounts()[0]);
check($connections->credentials($connections->find($allegroId))['refresh_token']==='r3','Daily Allegro cron refreshes even an unexpired token');
check(strpos(App\Services\AllegroService::authorizationUrl('st','https://panel.example.invalid/allegro-callback.php'),'client_id=cid')!==false,'Allegro login URL uses operator application');

// Aplikacja Allegro zapisana w panelu (bez edycji plików).
App\Core\Config::override('app',['app_name'=>'SalesCenter','base_url'=>'./index.php','public_base_url'=>'https://panel.example.invalid/index.php','encryption_key'=>str_repeat('ab',32)]);
$saas=new App\Models\SaasRepository($db); $saas->ensureSchema();
check(!App\Services\AllegroService::configured(),'Allegro login disabled without application');
$responses=[['#POST https://allegro\.pl/auth/oauth/token#',400,['error'=>'invalid_client']]];
rejects(fn()=>(new App\Services\AllegroService(true))->verifyApp('bad-client-id','bad-secret'),'Invalid Allegro application is rejected');
$responses=[['#POST https://allegro\.pl/auth/oauth/token#',200,['access_token'=>'app-token','expires_in'=>3600]]];
(new App\Services\AllegroService(true))->verifyApp('panel-client-id','panel-secret');
check(strpos((string)end($calls)['body'],'grant_type=client_credentials')!==false && in_array('Authorization: Basic '.base64_encode('panel-client-id:panel-secret'),end($calls)['headers'],true),'Allegro application verified with client credentials');
$saas->saveSetting('allegro_app',['client_id'=>'panel-client-id','secret'=>App\Services\OrderSecretBox::encrypt(['client_secret'=>'panel-secret'])]);
check(App\Services\AllegroService::configured() && App\Services\AllegroService::config()['source']==='platform','Platform-wide Allegro application enables login for every company');
check(strpos((string)$db->fetchColumn("SELECT value_json FROM sc_settings WHERE setting_key='allegro_app'"),'panel-secret')===false,'Allegro client secret stored encrypted');
check($saas->isPlatformOperator(['tenant_id'=>1,'role'=>'owner'])===false,'Operator requires an existing first company');

// Pobieranie od daty (backfill) omija okno 7 dni i czyści się po zakończeniu.
$wooId=$connections->create('woocommerce','WooCommerce · sklep',['shop_url'=>'https://sklep.example.invalid','remote_id'=>'https://sklep.example.invalid'],['consumer_key'=>'ck_1','consumer_secret'=>'cs_1']);
$wooAccount=$connections->account($wooId);
$old=$wooOrder; $old['id']=400; $old['date_created_gmt']=gmdate('Y-m-d\TH:i:s',time()-40*86400);
$responses=[['#page=1&#',200,[$old]],['#page=2&#',200,[]]];
$sync=new OrderSyncService($repo);
$sync->sync(true,(int)$wooAccount['id']);
check(!$db->fetchColumn('SELECT id FROM om_orders WHERE external_id=:e',['e'=>'400']),'Without backfill old orders are skipped');
$db->update('om_accounts',['import_from'=>gmdate('Y-m-d H:i:s',time()-60*86400),'cursor_json'=>null,'synced_until'=>null],'id=:id',['id'=>$wooAccount['id']]);
$first=$sync->sync(true,(int)$wooAccount['id'])[0];
check((bool)$db->fetchColumn('SELECT id FROM om_orders WHERE external_id=:e',['e'=>'400']) && $first['added']===1,'Backfill imports orders older than 7 days');
check($first['more']===false && $db->fetchColumn('SELECT import_from FROM om_accounts WHERE id=:id',['id'=>$wooAccount['id']])===null,'Backfill clears after full pass');

// Zdjęcia Empik (OR11 product_medias względne + P11) i ERLI (karta produktu) – automatycznie.
$empikImages=(new App\Services\EmpikService())->enrichOrderImages(['api_key'=>'K1','connection_id'=>9],['order_lines'=>[['product_sku'=>'P1','product_medias'=>[['media_url'=>'/media/product/abc.jpg','type'=>'SMALL']]],['product_sku'=>'P2']]]);
check($empikImages['order_lines'][0]['image_url']==='https://marketplace.empik.com/media/product/abc.jpg','Empik relative product media becomes absolute image');
$responses=[['#GET https://marketplace\.empik\.com/api/products/offers\?product_ids=P2#',200,['products'=>[['product_sku'=>'P2','product_media'=>['media_url'=>'https://cdn.empik.invalid/p2.jpg'],'offers'=>[]]]]]];
$empikImages=(new App\Services\EmpikService())->enrichOrderImages(['api_key'=>'K1','connection_id'=>9],['order_lines'=>[['product_sku'=>'P2']]]);
check($empikImages['order_lines'][0]['image_url']==='https://cdn.empik.invalid/p2.jpg' && strpos(end($calls)['url'],'locale=pl_PL')!==false,'Empik falls back to P11 product media');
$responses=[['#GET https://erli\.pl/svc/shop-api/products/EXT-1#',200,['externalId'=>'EXT-1','name'=>'Etui','url'=>'https://erli.pl/produkt/etui','images'=>[['url'=>'https://img.erli.invalid/etui']]]]];
$erliImages=(new App\Services\ErliService())->enrichOrderImages(['api_key'=>'E','connection_id'=>3],['items'=>[['externalId'=>'EXT-1','name'=>'Etui']]]);
check($erliImages['items'][0]['imageUrl']==='https://img.erli.invalid/etui','ERLI image from product card (not product page link)');

// Uzupełnianie zdjęć w już pobranych zamówieniach – bez przycisku.
$erliId=$connections->create('erli','ERLI · sklep',['remote_id'=>'33'],['api_key'=>'E']);
$erliAccount=$connections->account($erliId);
$erliRaw=['id'=>'ER-9','created'=>gmdate('c',time()-3600),'status'=>'purchased','totalPrice'=>1000,'items'=>[['externalId'=>'EXT-1','name'=>'Etui','quantity'=>1,'unitPrice'=>1000]],'user'=>['email'=>'k@example.invalid','deliveryAddress'=>['firstName'=>'Ola','lastName'=>'K']]];
$repo->import((int)$erliAccount['id'],OrderNormalizer::normalize('erli',$erliRaw,0,time()));
$orderId=(int)$db->fetchColumn('SELECT id FROM om_orders WHERE external_id=:e',['e'=>'ER-9']);
check(($repo->order($orderId)['details']['items'][0]['image_url']??'')==='','Imported order starts without image');
$repairer=new OrderSyncService($repo);
check($repairer->repairImages(10)>=1 && $repo->order($orderId)['details']['items'][0]['image_url']==='https://img.erli.invalid/etui','Missing images are filled automatically');
$callCount=count($calls); $repairer->repairImages(10);
check(count($calls)===$callCount,'Orders are not re-checked more often than every 12 hours');

// Morele: udokumentowane GET /orders, pola odpowiedzi i odświeżanie tokenów.
$moreleId=$connections->create('morele','Morele · sklep',['client_id'=>'cid','remote_id'=>'cid'],['client_id'=>'cid','client_secret'=>'cs','access_token'=>'tok']);
$responses=[
    ['#GET https://api-marketplace\.morele\.net/orders$#',200,['data'=>[['order_id'=>101,'date_created'=>gmdate('c',time()-600),'status'=>1,'payment_mode_id'=>4,'payment_status'=>0,'order_value'=>'49.99','products'=>[['vendor_product_name'=>'Mysz','part_number'=>'SKU1','quantity'=>1,'sale_price_brutto'=>'49.99']],'customer'=>['name'=>'Piotr Z','email'=>'p@example.invalid','shipping_city'=>'Łódź']]]]],
];
$morele=new App\Services\MoreleService();
$moreleAccount=$morele->listAccounts()[0];
$moreleRequestsBefore=count($calls);
$morelePage=$morele->readOrderPage($moreleAccount,gmdate('Y-m-d\TH:i:s\Z',time()-86400),gmdate('Y-m-d\TH:i:s\Z'));
check(count($morelePage['orders'])===1 && $morelePage['page_size']===0,'Morele reads documented order list');
check(strpos($calls[$moreleRequestsBefore]['url'],'?')===false,'Morele avoids rejected date filter');
$moreleNormalized=OrderNormalizer::normalize('morele',$morelePage['orders'][0],time()-86400,time()); check($moreleNormalized['total_cents']===4999,'Morele order normalized');
check($moreleNormalized['details']['items'][0]['sku']==='SKU1','Morele product SKU mapped');
check($moreleNormalized['details']['source_payment_method']==='Raty' && $moreleNormalized['details']['payment_method']==='Raty' && $moreleNormalized['paid']===0,'Morele installment method and unpaid status are preserved');
check(strpos(App\Services\OrderSyncError::describe(new RuntimeException('Morele API error [400]: Parameter date_from is not allowed'))['message'],'Parameter date_from is not allowed')!==false,'Rejected request shows platform reason');

// Morele: token wydawany raz – zapis przy łączeniu, odświeżanie refresh_token, czytelny błąd „already registered”.
$responses=[['#POST https://api-marketplace\.morele\.net/auth/register#',200,['access_token'=>'m-access','refresh_token'=>'m-refresh']],['#GET https://api-marketplace\.morele\.net/orders$#',200,['data'=>[]]]];
$moreleTest=(new App\Services\MoreleService())->testConnection(['client_id'=>'new-id','client_secret'=>'new-secret']);
check(($moreleTest['secret']['access_token']??'')==='m-access' && ($moreleTest['secret']['refresh_token']??'')==='m-refresh','Morele tokens from first registration are returned for saving');
$responses=[
    ['#POST https://api-marketplace\.morele\.net/auth/refresh#',200,['access_token'=>'m-access-2','refresh_token'=>'m-refresh-2']],
    ['#GET https://api-marketplace\.morele\.net/orders$#',200,['data'=>[]]],
];
$db->update('om_connections',['secret_json'=>App\Services\OrderSecretBox::encrypt(['client_secret'=>'cs','refresh_token'=>'m-refresh'])],'id=:id',['id'=>$moreleId]);
$moreleCallsBefore=count($calls);
$morele2=new App\Services\MoreleService(); $morele2->readOrderPage($morele2->listAccounts()[0],gmdate('Y-m-d\TH:i:s\Z',time()-3600),gmdate('Y-m-d\TH:i:s\Z'));
$storedMorele=$connections->credentials($connections->find($moreleId));
check($storedMorele['access_token']==='m-access-2' && $storedMorele['refresh_token']==='m-refresh-2','Morele refreshes token instead of registering again');
$responses=[['#POST https://api-marketplace\.morele\.net/auth/refresh#',200,['access_token'=>'m-access-3','refresh_token'=>'m-refresh-3']]];
$morele2->refreshAccessToken($morele2->listAccounts()[0]);
check($connections->credentials($connections->find($moreleId))['refresh_token']==='m-refresh-3','Daily Morele cron rotates stored refresh token');
check(!preg_grep('#auth/register#',array_column(array_slice($calls,$moreleCallsBefore),'url')),'Morele does not call register when refresh token exists');
$responses=[['#POST https://api-marketplace\.morele\.net/auth/register#',400,['status'=>'FAILED','message'=>'App already registered!']]];
try { (new App\Services\MoreleService())->testConnection(['client_id'=>'used','client_secret'=>'used']); check(false,'Already registered must fail'); }
catch (RuntimeException $e) { check(strpos($e->getMessage(),'Wygeneruj w panelu Morele nowe API')!==false,'Already registered explains how to fix'); }

// Własny sklep (API): format wspólny bez okna dat.
$apiOrder=OrderNormalizer::normalize('api',['id'=>'SHOP-1','created_at'=>'2024-01-05T10:00:00Z','items'=>[['name'=>'Pozycja','quantity'=>3,'price'=>'10.5','vat'=>'8']],'shipping'=>['price'=>'5']],0,time());
check($apiOrder['total_cents']===3650 && $apiOrder['details']['items'][0]['vat']==='8' && $apiOrder['currency']==='PLN','API order total computed from items and shipping');
rejects(fn()=>OrderNormalizer::normalize('api',['id'=>'X','created_at'=>'','items'=>[]],0,time()),'API order requires creation date');

// Altreo.pl: ping, strona zamówień w formacie sklepu, pełny przebieg synchronizacji i numer przesyłki.
$altreoToken=str_repeat('a1',32);
$responses=[['#GET https://altreo\.example\.invalid/api/salescenter/ping#',200,['ok'=>true,'shop_name'=>'ALTREO','api_version'=>1]]];
$altreoTest=(new App\Services\AltreoService())->testConnection(['shop_url'=>'https://altreo.example.invalid','api_key'=>$altreoToken]);
check($altreoTest['name']==='Altreo.pl · ALTREO' && $altreoTest['remote_id']==='https://altreo.example.invalid','Altreo ping reads shop name');
check(in_array('X-Api-Key: '.$altreoToken,end($calls)['headers'],true) && in_array('Authorization: Bearer '.$altreoToken,end($calls)['headers'],true),'Altreo sends token in both headers');
rejects(fn()=>(new App\Services\AltreoService())->testConnection(['shop_url'=>'https://altreo.example.invalid','api_key'=>'short']),'Altreo rejects a short token before calling the shop');
$responses=[['#/api/salescenter/ping#',401,['error'=>'Nieprawidłowy token API SalesCenter.']]];
rejects(fn()=>(new App\Services\AltreoService())->testConnection(['shop_url'=>'https://altreo.example.invalid','api_key'=>$altreoToken]),'Altreo invalid token is rejected');
$altreoOrder=['order_number'=>'ALT20260918-ABC123','created_at'=>gmdate('c',time()-1800),'updated_at'=>gmdate('c',time()-60),'status'=>'w_realizacji','payment_status'=>'oplacone','payment_method'=>'Tpay','shipping_method'=>'Kurier InPost',
    'currency'=>'PLN','subtotal'=>'2999.00','shipping_cost'=>'19.99','payment_fee'=>'1.50','discount_amount'=>'100.00','coupon_code'=>'LATO','total'=>'2920.49',
    'email'=>'adam@example.invalid','phone'=>'600100200','full_name'=>'Adam Nowak Kowalski','street'=>'Polna 5/2','city'=>'Poznań','postcode'=>'60-001','country'=>'Polska','notes'=>'Proszę o telefon',
    'invoice'=>['requested'=>true,'company_name'=>'Nowak IT','nip'=>'5252674798','street'=>'Biurowa 1','city'=>'Poznań','postcode'=>'60-002'],'tracking_number'=>'',
    'admin_url'=>'https://altreo.example.invalid/admin/zamowienia/41',
    'items'=>[['product_id'=>7,'name'=>'Komputer Gamer','sku'=>'PC-7','ean'=>'','image_url'=>'https://altreo.example.invalid/assets/img/products/pc.webp','price'=>'2999.00','qty'=>1,'options'=>[['group'=>'RAM','value'=>'32 GB']]]]];
$responses=[['#GET https://altreo\.example\.invalid/api/salescenter/orders\?#',200,['orders'=>[$altreoOrder],'total_count'=>1,'offset'=>0,'limit'=>50]]];
$altreoPage=(new App\Services\AltreoService())->readOrderPage(['shop_url'=>'https://altreo.example.invalid','api_key'=>$altreoToken],gmdate('Y-m-d\TH:i:s\Z',time()-86400),gmdate('Y-m-d\TH:i:s\Z'),'0',gmdate('Y-m-d\TH:i:s\Z',time()-3600));
check(strpos(end($calls)['url'],'updated_from=')!==false && strpos(end($calls)['url'],'offset=0')!==false && $altreoPage['total_count']===1 && $altreoPage['page_size']===1,'Altreo pages changed orders by offset');
$altreoNormalized=OrderNormalizer::normalize('altreo',$altreoPage['orders'][0],time()-86400,time());
check($altreoNormalized['external_id']==='ALT20260918-ABC123' && $altreoNormalized['remote_status']==='w_realizacji' && $altreoNormalized['paid']===1 && $altreoNormalized['total_cents']===292049,'Altreo order id, status, payment and total');
check($altreoNormalized['details']['items'][0]['name']==='Komputer Gamer (RAM: 32 GB)' && $altreoNormalized['details']['items'][0]['unit_cents']===299900 && $altreoNormalized['details']['items'][0]['image_url']!=='','Altreo item options, price and image');
check($altreoNormalized['details']['items'][1]['unit_cents']===150 && $altreoNormalized['details']['shipping_cents']===1999 && $altreoNormalized['details']['delivery']==='Kurier InPost','Altreo payment fee line and shipping');
check($altreoNormalized['buyer_name']==='Adam Nowak Kowalski' && $altreoNormalized['details']['address']['country']==='PL' && $altreoNormalized['details']['address']['street']==='Polna 5/2','Altreo buyer and Polish address');
check($altreoNormalized['details']['invoice_required']===1 && $altreoNormalized['details']['invoice_address']['tax_id']==='5252674798' && $altreoNormalized['details']['invoice_address']['company_name']==='Nowak IT','Altreo invoice data');
check(strpos((string)$altreoNormalized['details']['buyer_note'],'Kod rabatowy: LATO')!==false,'Altreo buyer note carries coupon code');
$altreoId=$connections->create('altreo','Altreo.pl · ALTREO',['shop_url'=>'https://altreo.example.invalid','remote_id'=>'https://altreo.example.invalid'],['api_key'=>$altreoToken]);
$altreoAccount=$connections->account($altreoId);
$altreoSync=(new OrderSyncService($repo))->sync(true,(int)$altreoAccount['id'])[0];
check(empty($altreoSync['error']) && $altreoSync['added']===1 && $altreoSync['more']===false,'Altreo sync imports the page and finishes');
$altreoLocal=$repo->order((int)$db->fetchColumn('SELECT id FROM om_orders WHERE external_id=:e',['e'=>'ALT20260918-ABC123']));
check($altreoLocal['platform']==='altreo' && (int)$altreoLocal['account_source_id']===$altreoId,'Altreo order belongs to its connection');
$responses=[['#POST https://altreo\.example\.invalid/api/salescenter/orders/ALT20260918-ABC123/shipment#',200,['ok'=>true,'status'=>'wyslane','changed'=>true]]];
$altreoPublish=(new App\Services\OrderMarketplaceShipmentService($repo))->publish($altreoLocal,'620111222333','inpost','InPost');
$altreoBody=json_decode((string)end($calls)['body'],true);
check($altreoBody['tracking_number']==='620111222333' && $altreoBody['carrier_name']==='InPost' && strpos($altreoPublish,'Altreo.pl')!==false,'Altreo receives tracking number and carrier');
$responses=[['#/shipment#',409,['error'=>'Zamówienie ALT20260918-ABC123 jest anulowane.']]];
rejects(fn()=>(new App\Services\OrderMarketplaceShipmentService($repo))->publish($altreoLocal,'620111222333','inpost','InPost'),'Altreo refusal surfaces as an error');

// Bezpieczeństwo adresów sklepów.
rejects(fn()=>Http::normalizeShopUrl('http://sklep.example.invalid'),'Shop URL must use https');
rejects(fn()=>Http::normalizeShopUrl('https://127.0.0.1'),'Shop URL cannot target loopback');
rejects(fn()=>Http::normalizeShopUrl('https://10.0.0.5'),'Shop URL cannot target private network');

// Izolacja połączeń między firmami.
Tenant::activate(2);
$repo2=new OrderRepository($db); $repo2->ensureSchema(); (new ConnectionRepository($db))->ensureSchema();
check((new App\Services\EmpikService())->listAccounts()===[] && (new ConnectionRepository($db))->all()===[],'Company 2 does not see company 1 connections');
Tenant::activate(1);
$connections->delete($empikId);
check((int)$db->fetchColumn('SELECT enabled FROM om_accounts WHERE id=:id',['id'=>$empikAccount['id']])===0 && strpos((string)$db->fetchColumn('SELECT name FROM om_accounts WHERE id=:id',['id'=>$empikAccount['id']]),'odłączone')!==false,'Disconnect keeps orders and pauses import');

Http::$transport=null;
echo 'OK: '.$checks." integration checks; no network or production database used.\n";
