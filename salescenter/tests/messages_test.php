<?php
/** Centrum wiadomości: Allegro, Mirakl (Empik/MediaMarkt), Morele i autoodpowiedzi na atrapie HTTP i SQLite w pamięci. */
declare(strict_types=1);
define('BASE_PATH',dirname(__DIR__));
require BASE_PATH.'/app/bootstrap.php';
use App\Core\Database;
use App\Core\Tenant;
use App\Models\ConnectionRepository;
use App\Models\MessageRepository;
use App\Models\OrderRepository;
use App\Services\Integrations\Http;
use App\Services\Messages\MessageCenter;
use App\Services\Messages\MoreleMessages;

$checks=0;
function check(bool $condition,string $label): void { global $checks; $checks++; if (!$condition) { throw new RuntimeException($label); } }
function rejects(callable $fn,string $label,string $contains=''): void { try { $fn(); } catch (\Throwable $e) { check($contains==='' || strpos($e->getMessage(),$contains)!==false,$label.' ('.$e->getMessage().')'); return; } check(false,$label); }

$reflection=new ReflectionClass(Database::class);
$db=$reflection->newInstanceWithoutConstructor();
$pdo=new PDO('sqlite::memory:'); $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
foreach (['pdo'=>$pdo,'config'=>['driver'=>'sqlite','database'=>'test']] as $name=>$value) { $property=$reflection->getProperty($name); $property->setValue($db,$value); }
Database::useInstance($db);
App\Core\Config::override('app',['app_name'=>'SalesCenter','base_url'=>'./index.php','public_base_url'=>'https://panel.example.invalid/index.php','encryption_key'=>str_repeat('ab',32),'integrations'=>['allegro'=>['client_id'=>'cid','client_secret'=>'secret']]]);
Tenant::activate(1);
$orders=new OrderRepository($db); $orders->ensureSchema();
$connections=new ConnectionRepository($db); $connections->ensureSchema();
$repo=new MessageRepository($db); $repo->ensureSchema(); $repo->ensureSchema();
check(Tenant::rewrite('SELECT * FROM om_msg_threads t JOIN om_msg_messages m')==='SELECT * FROM t1_om_msg_threads t JOIN t1_om_msg_messages m','Message tables are tenant tables');

$calls=[]; $responses=[];
Http::$transport=function (string $method,string $url,array $headers,?string $body) use (&$calls,&$responses): array {
    $calls[]=compact('method','url','headers','body');
    foreach ($responses as [$pattern,$status,$payload]) {
        if (preg_match($pattern,$method.' '.$url)) { return ['status'=>$status,'body'=>is_string($payload)?$payload:json_encode($payload),'headers'=>[]]; }
    }
    return ['status'=>404,'body'=>'{"message":"not mocked"}','headers'=>[]];
};
$find=function (string $pattern) use (&$calls): array { foreach (array_reverse($calls) as $call) { if (preg_match($pattern,$call['method'].' '.$call['url'])) { return $call; } } return []; };
$iso=function (int $offset): string { return gmdate('Y-m-d\TH:i:s.000\Z',time()+$offset); };

// Ustawienia per marketplace.
$settings=$repo->settings();
check($settings['allegro']['sync_issues']===1 && $settings['allegro']['autoresponder']===0 && !isset($settings['morele']['sync_issues']) && $settings['empik']['sync_incidents']===1,'Default settings per marketplace');
$repo->saveSettings('allegro',['enabled'=>'1','sync_messages'=>'1','sync_issues'=>'1','mark_read'=>'1','autoresponder'=>'1','interval'=>'1','history_days'=>'999','hours_days'=>['0','1','2','3','4','5','6','7'],'hours_from'=>'00:00','hours_to'=>'23:59','signature'=>'Pozdrawiamy, Sklep']);
$allegroSettings=$repo->platformSettings('allegro');
check($allegroSettings['interval']===2 && $allegroSettings['history_days']===60 && $allegroSettings['hours']['days']===[1,2,3,4,5,6,7] && $allegroSettings['signature']==='Pozdrawiamy, Sklep','Settings are clamped and validated');
rejects(function () use ($repo) { $repo->saveSettings('ebay',[]); },'Unknown marketplace rejected');
check(MessageCenter::withinHours(['days'=>[1,2,3,4,5],'from'=>'08:00','to'=>'16:00'],strtotime('2026-09-16 10:00:00 Europe/Warsaw')) && !MessageCenter::withinHours(['days'=>[1,2,3,4,5],'from'=>'08:00','to'=>'16:00'],strtotime('2026-09-19 10:00:00 Europe/Warsaw')) && MessageCenter::withinHours(['days'=>[1,2,3,4,5,6,7],'from'=>'22:00','to'=>'06:00'],strtotime('2026-09-16 23:30:00 Europe/Warsaw')),'Working hours incl. weekends and overnight window');

// ---------------- Allegro: centrum wiadomości + dyskusje/reklamacje
$allegroId=$connections->create('allegro','Allegro · sklep',['login'=>'sklep','remote_id'=>'9','seller_id'=>'9'],['access_token'=>'tok','refresh_token'=>'r','expires_at'=>time()+86400]);
$accountId=(int)$db->fetchColumn('SELECT id FROM om_accounts WHERE platform=:p AND source_id=:s',['p'=>'allegro','s'=>$allegroId]);
$db->insert('om_orders',['account_id'=>$accountId,'external_id'=>'cf-1','remote_status'=>'READY_FOR_PROCESSING','status_id'=>1,'ordered_at'=>gmdate('Y-m-d H:i:s'),'buyer_name'=>'Jan','email'=>'','phone'=>'','total_cents'=>1000,'currency'=>'PLN','details_json'=>'{}','imported_at'=>gmdate('Y-m-d H:i:s'),'updated_at'=>gmdate('Y-m-d H:i:s')]);
$localOrderId=(int)$db->fetchColumn('SELECT id FROM om_orders WHERE external_id=:e',['e'=>'cf-1']);
$claim=['id'=>'11111111-aaaa-4bbb-8ccc-000000000001','type'=>'CLAIM','referenceNumber'=>'7/2026','decisionDueDate'=>$iso(86400),'openedDate'=>$iso(-3600),'subject'=>null,'buyer'=>['id'=>'5','login'=>'kupujacy'],'checkoutForm'=>['id'=>'cf-1'],
    'currentState'=>['status'=>'CLAIM_SUBMITTED','statusDueDate'=>$iso(86400),'returnRequired'=>null,'chatActive'=>true],'chat'=>['lastMessage'=>['status'=>'NEW','createdAt'=>$iso(-3500)],'messagesCount'=>1],
    'expectations'=>[['name'=>'PARTIAL_REFUND','refund'=>['amount'=>'50.00','currency'=>'PLN']]],'reason'=>['type'=>'ITEM_IS_DAMAGED','description'=>'Pęknięta obudowa'],'right'=>'COMPLAINT','offer'=>['id'=>'777','quantity'=>1],'attachments'=>[['fileName'=>'foto.jpg']]];
$dispute=['id'=>'22222222-aaaa-4bbb-8ccc-000000000002','type'=>'DISPUTE','subject'=>'nie otrzymałem towaru','openedDate'=>$iso(-7200),'buyer'=>['login'=>'kupujacy2'],'checkoutForm'=>['id'=>'cf-x'],
    'currentState'=>['status'=>'DISPUTE_ONGOING','chatActive'=>true],'chat'=>['lastMessage'=>['status'=>'SELLER_REPLIED','createdAt'=>$iso(-7000)],'messagesCount'=>2]];
$responses=[
    ['#GET https://api\.allegro\.pl/messaging/threads\?limit=20&offset=0#',200,['threads'=>[['id'=>'T1','read'=>false,'lastMessageDateTime'=>$iso(-60),'interlocutor'=>['login'=>'anna']],['id'=>'T0','read'=>true,'lastMessageDateTime'=>$iso(-90*86400),'interlocutor'=>['login'=>'stary']]],'offset'=>0,'limit'=>20]],
    ['#GET https://api\.allegro\.pl/messaging/threads/T1/messages#',200,['messages'=>[
        ['id'=>'M2','status'=>'DELIVERED','type'=>'MESSAGE_CENTER','createdAt'=>$iso(-60),'author'=>['login'=>'anna','isInterlocutor'=>true],'text'=>'Kiedy wysyłka?','relatesTo'=>['order'=>['id'=>'cf-1']],'attachments'=>[],'hasAdditionalAttachments'=>false],
        ['id'=>'M1','status'=>'DELIVERED','type'=>'ASK_QUESTION','createdAt'=>$iso(-3600),'subject'=>'Pytanie o zamówienie','author'=>['login'=>'anna','isInterlocutor'=>true],'text'=>'Dzień dobry','relatesTo'=>['offer'=>['id'=>'777']],'attachments'=>[['fileName'=>'a.pdf','status'=>'SAFE']],'hasAdditionalAttachments'=>false],
    ],'offset'=>0,'limit'=>20]],
    ['#GET https://api\.allegro\.pl/sale/issues\?status=DISPUTE_ONGOING&status=DISPUTE_UNRESOLVED&status=CLAIM_SUBMITTED#',200,['issues'=>[$claim,$dispute]]],
    ['#GET https://api\.allegro\.pl/sale/issues\?limit=50#',200,['issues'=>[$claim]]],
    ['#GET https://api\.allegro\.pl/sale/issues/11111111[^/]*/chat#',200,['chat'=>[['id'=>'C1','text'=>'Obudowa pęknięta','author'=>['login'=>'kupujacy','role'=>'BUYER'],'createdAt'=>$iso(-3500),'attachments'=>[['fileName'=>'foto.jpg']]]]]],
    ['#GET https://api\.allegro\.pl/sale/issues/22222222[^/]*/chat#',200,['chat'=>[['id'=>'D1','text'=>'Nie mam paczki','author'=>['login'=>'kupujacy2','role'=>'BUYER'],'createdAt'=>$iso(-7100)],['id'=>'D2','text'=>'Sprawdzamy','author'=>['login'=>'sklep','role'=>'SELLER'],'createdAt'=>$iso(-7000)]]]],
];
$center=new MessageCenter($repo);
$report=$center->sync(true);
check(count($report)===1 && $report[0]['error']===null && $report[0]['threads']===3 && $report[0]['new']===3,'Allegro sync imports thread, claim and dispute');
$issuesCall=$find('#/sale/issues\?status=#');
check(in_array('Accept: application/vnd.allegro.beta.v1+json',$issuesCall['headers'],true) && in_array('Accept-Language: pl-PL',$issuesCall['headers'],true),'Issues use beta media type and Polish language');
check($find('#/messaging/threads/T0/#')===[],'Threads older than history window are not fetched');
$t1=$repo->findByExternal($allegroId,'message','T1');
$messagesT1=$repo->messages((int)$t1['id']);
check($t1['status']==='new' && (int)$t1['needs_reply']===1 && $t1['customer_login']==='anna' && $t1['subject']==='Pytanie o zamówienie' && $t1['order_external_id']==='cf-1' && (int)$t1['order_id']===$localOrderId,'Allegro thread mapped and linked to local order');
check(count($messagesT1)===2 && $messagesT1[0]['body']==='Dzień dobry' && $messagesT1[0]['attachments'][0]['name']==='a.pdf' && $messagesT1[1]['author_role']==='customer','Allegro messages sorted oldest first with attachments');
$claimRow=$repo->findByExternal($allegroId,'claim',$claim['id']);
check($claimRow['subject']==='Reklamacja 7/2026: Towar uszkodzony' && $claimRow['remote_status']==='CLAIM_SUBMITTED' && (int)$claimRow['needs_reply']===1 && $claimRow['due_at']!==null && $claimRow['meta']['expectations'][0]['amount']==='50.00' && $claimRow['meta']['reason_description']==='Pęknięta obudowa','Allegro claim mapped with deadline, expectations and reason');
$disputeRow=$repo->findByExternal($allegroId,'dispute',$dispute['id']);
check($disputeRow['status']==='answered' && (int)$disputeRow['needs_reply']===0 && count($repo->messages((int)$disputeRow['id']))===2,'Dispute last answered by seller is not waiting');
$counters=$repo->counters();
check($counters['platforms']['allegro']['kinds']['message']['open']===1 && $counters['platforms']['allegro']['kinds']['claim']['open']===1 && $counters['platforms']['allegro']['kinds']['dispute']['open']===0 && $counters['all']['open']===2 && $counters['overdue']===1,'Counters per marketplace section');
check(MessageRepository::openCount($db)===2 && $repo->listing(['status'=>'open'])['total']===2 && $repo->listing(['status'=>'all','platform'=>'allegro','kind'=>'dispute'])['total']===1 && $repo->listing(['status'=>'all','q'=>'kupujacy2'])['total']===1,'Listing filters by section, status and search');

// Kolejna synchronizacja nie pobiera ponownie niezmienionych wątków; stan jest zapisany per połączenie.
$before=count($calls);
$center->sync();
check(count($calls)===$before,'Interval throttles sync until next_sync');
$state=$repo->syncState()[(string)$allegroId];
check(!empty($state['threads_since']) && !empty($state['last_sync']) && $state['error']===null,'Connection sync state stored');

// Odpowiedź w centrum wiadomości + podpis; ponowny import tej samej wiadomości nie dubluje jej.
$responses[]=['#POST https://api\.allegro\.pl/messaging/threads/T1/messages#',201,['id'=>'M3']];
$center->reply((int)$t1['id'],'Wysyłamy jutro.',['signature'=>true],'Ola');
$post=$find('#POST .*/messaging/threads/T1/messages#');
check(json_decode($post['body'],true)['text']==="Wysyłamy jutro.\n\nPozdrawiamy, Sklep" && in_array('Content-Type: application/vnd.allegro.public.v1+json',$post['headers'],true),'Reply sent to Allegro with signature');
$t1=$repo->findThread((int)$t1['id']);
check($t1['status']==='answered' && (int)$t1['needs_reply']===0 && $t1['last_author']==='seller' && $repo->messages((int)$t1['id'])[2]['external_id']==='M3','Reply recorded locally with remote id');
rejects(function () use ($center,$t1) { $center->reply((int)$t1['id'],str_repeat('x',2001),[],'Ola'); },'Allegro 2000 char limit','2000');

// Nowa wiadomość klienta po odpowiedzi → „Do odpowiedzi”.
$ingest=$repo->ingest($allegroId,'allegro',['kind'=>'message','external_id'=>'T1','needs_reply'=>true,'last_message_at'=>$iso(-5),'messages'=>[['external_id'=>'M4','author_role'=>'customer','author_name'=>'anna','body'=>'Dziękuję, a numer paczki?','created_at'=>$iso(-5)]]]);
check($ingest['new_customer_messages']===1 && $repo->findThread((int)$t1['id'])['status']==='waiting','New customer message reopens answered thread');
$repo->ingest($allegroId,'allegro',['kind'=>'message','external_id'=>'T1','needs_reply'=>false,'last_message_at'=>$iso(-4),'messages'=>[['external_id'=>'M5','author_role'=>'seller','author_name'=>'sklep','body'=>'Odpisane w panelu Allegro','created_at'=>$iso(-4)]]]);
check($repo->findThread((int)$t1['id'])['status']==='answered','Reply sent outside SalesCenter marks thread answered');
$repo->setStatus((int)$t1['id'],'closed');
$repo->ingest($allegroId,'allegro',['kind'=>'message','external_id'=>'T1','needs_reply'=>false,'last_message_at'=>$iso(-3),'messages'=>null]);
check($repo->findThread((int)$t1['id'])['status']==='closed','Manual close survives sync without new messages');

// Reklamacja: wiadomość z decyzją o zwrocie i decyzja (częściowy zwrot).
$responses[]=['#POST https://api\.allegro\.pl/sale/issues/11111111[^/]*/message#',201,['id'=>'C2']];
$center->reply((int)$claimRow['id'],'Proszę odesłać produkt.',['message_type'=>'RETURN_REQUIRED_CUSTOM'],'Ola');
check(json_decode($find('#/sale/issues/11111111[^/]*/message#')['body'],true)==['text'=>'Proszę odesłać produkt.','type'=>'RETURN_REQUIRED_CUSTOM'],'Claim message carries return decision type');
$center->reply((int)$claimRow['id'],'Test',['message_type'=>'END_REQUEST'],'Ola');
check(json_decode($find('#/sale/issues/11111111[^/]*/message#')['body'],true)['type']==='REGULAR','Dispute-only type is not sent for a claim');
rejects(function () use ($center,$claimRow) { $center->decideClaim((int)$claimRow['id'],'ACCEPTED_PARTIAL_REFUND','Zwracamy część','','Ola'); },'Partial refund needs amount','kwotę');
rejects(function () use ($center,$claimRow) { $center->decideClaim((int)$claimRow['id'],'ACCEPTED_REFUND','','','Ola'); },'Decision needs justification','Uzasadnienie');
rejects(function () use ($center,$disputeRow) { $center->decideClaim((int)$disputeRow['id'],'ACCEPTED_REFUND','x','','Ola'); },'Decision only for claims','reklamacji');
$responses[]=['#POST https://api\.allegro\.pl/sale/issues/11111111[^/]*/status#',200,''];
$center->decideClaim((int)$claimRow['id'],'ACCEPTED_PARTIAL_REFUND','Zwracamy 30 zł','30,5','Ola');
check(json_decode($find('#/sale/issues/11111111[^/]*/status#')['body'],true)==['status'=>'ACCEPTED_PARTIAL_REFUND','message'=>'Zwracamy 30 zł','partialRefund'=>['amount'=>'30.50','currency'=>'PLN']],'Claim decision payload');
check($repo->findThread((int)$claimRow['id'])['meta']['decision']==='ACCEPTED_PARTIAL_REFUND','Decision stored on thread');

// Zamknięcie reklamacji po stronie Allegro.
$closedClaim=$claim; $closedClaim['currentState']['status']='CLAIM_ACCEPTED';
$repo->ingest($allegroId,'allegro',(function () use ($closedClaim) { return ['kind'=>'claim','external_id'=>$closedClaim['id'],'remote_status'=>'CLAIM_ACCEPTED','closed'=>true,'messages'=>null]; })());
check($repo->findThread((int)$claimRow['id'])['status']==='closed' && (int)$repo->findThread((int)$claimRow['id'])['remote_closed']===1,'Remote closed claim becomes closed');

// Otwarcie wątku oznacza go w Allegro jako przeczytany.
$repo->ingest($allegroId,'allegro',['kind'=>'message','external_id'=>'T9','subject'=>'Nowy','needs_reply'=>true,'last_message_at'=>$iso(-2),'meta'=>['read'=>false],'messages'=>[['external_id'=>'N1','author_role'=>'customer','author_name'=>'ewa','body'=>'Hej','created_at'=>$iso(-2)]]]);
$t9=$repo->findByExternal($allegroId,'message','T9');
$responses[]=['#PUT https://api\.allegro\.pl/messaging/threads/T9/read#',200,['id'=>'T9','read'=>true]];
$center->opened($t9);
check($repo->findThread((int)$t9['id'])['status']==='waiting' && $find('#PUT .*/messaging/threads/T9/read#') && json_decode($find('#PUT .*/T9/read#')['body'],true)===['read'=>true],'Opening marks new thread as waiting and read in Allegro');

// Brak uprawnień → wskazówka, wiadomości z drugiej części nadal się synchronizują.
$responses=array_merge([['#GET https://api\.allegro\.pl/sale/issues#',403,['errors'=>[['message'=>'Access denied']]]]],$responses);
$report=$center->sync(true);
check(strpos((string)$report[0]['error'],'allegro:api:disputes')!==false,'Missing Allegro scope shows hint');

// ---------------- Empik (Mirakl): wiadomości i incydenty
$empikId=$connections->create('empik','Empik · Księgarnia',['shop_id'=>'','remote_id'=>'77'],['api_key'=>'EMPIK-KEY']);
$repo->saveSettings('empik',['enabled'=>'1','sync_messages'=>'1','sync_incidents'=>'1','operator_threads'=>'0','autoresponder'=>'1','hours_days'=>['0','1','2','3','4','5','6','7'],'hours_from'=>'00:00','hours_to'=>'23:59']);
$order=['order_id'=>'EMP-1-A','commercial_id'=>'EMP-1','order_state'=>'SHIPPED','customer'=>['firstname'=>'Ewa','lastname'=>'Lis'],'order_lines'=>[
    ['order_line_id'=>'EMP-1-A-1','order_line_state'=>'INCIDENT_OPEN','order_line_state_reason_code'=>'DAMAGED','order_line_state_reason_label'=>'Produkt uszkodzony','product_title'=>'Książka','offer_sku'=>'K-1','quantity'=>1,'last_updated_date'=>$iso(-600)],
    ['order_line_id'=>'EMP-1-A-2','order_line_state'=>'SHIPPED','product_title'=>'Zakładka','quantity'=>1,'last_updated_date'=>$iso(-9000)],
]];
$responses=[
    ['#GET https://marketplace\.empik\.com/api/inbox/threads\?#',200,['data'=>[
        ['id'=>'th-1','topic'=>['type'=>'FREE_TEXT','value'=>'Gdzie paczka?'],'entities'=>[['type'=>'MMP_ORDER','id'=>'EMP-2-A','label'=>'EMP-2']],'authorized_participants'=>[['type'=>'CUSTOMER','id'=>'c-9','display_name'=>'Piotr N.'],['type'=>'OPERATOR','display_name'=>'Empik'],['type'=>'SHOP','id'=>'77','display_name'=>'Księgarnia']],
         'date_created'=>$iso(-4000),'date_updated'=>$iso(-300),'metadata'=>['last_message_date'=>$iso(-300),'shop_reply_needed_since'=>$iso(-300),'total_count'=>1],
         'messages'=>[['id'=>'mm-1','body'=>'Gdzie jest moja paczka?','date_created'=>$iso(-300),'from'=>['type'=>'CUSTOMER_USER','display_name'=>'Piotr N.'],'to'=>[['type'=>'SHOP','display_name'=>'Księgarnia']],'attachments'=>[['id'=>'at1','name'=>'zdj.png','size'=>10]]]]],
        ['id'=>'th-op','topic'=>['type'=>'FREE_TEXT','value'=>'Operator'],'entities'=>[['type'=>'SELLER_OPERATOR','id'=>'x','label'=>'x']],'authorized_participants'=>[],'date_created'=>$iso(-100),'date_updated'=>$iso(-100),'metadata'=>['last_message_date'=>$iso(-100),'shop_reply_needed_since'=>null,'total_count'=>1],'messages'=>[]],
    ]]],
    ['#GET https://marketplace\.empik\.com/api/orders\?has_incident=true#',200,['orders'=>[$order],'total_count'=>1]],
];
$report=$center->sync(true,$empikId);
check(count($report)===1 && $report[0]['error']===null && $report[0]['threads']===2,'Empik sync imports thread and incident (operator thread skipped)');
check(in_array('Authorization: EMPIK-KEY',$find('#/api/inbox/threads#')['headers'],true) && strpos($find('#/api/inbox/threads#')['url'],'with_messages=true')!==false && strpos($find('#/api/inbox/threads#')['url'],'updated_since=')!==false,'Mirakl M11 request with messages and updated_since');
$mt=$repo->findByExternal($empikId,'message','th-1');
check($mt['customer_name']==='Piotr N.' && $mt['order_external_id']==='EMP-2-A' && (int)$mt['needs_reply']===1 && $repo->messages((int)$mt['id'])[0]['attachments'][0]['name']==='zdj.png','Mirakl thread mapped');
check($repo->findByExternal($empikId,'message','th-op')===null,'Operator threads can be excluded');
$incident=$repo->findByExternal($empikId,'incident','EMP-1-A');
check($incident['subject']==='Incydent: Produkt uszkodzony' && $incident['customer_name']==='Ewa Lis' && count($incident['meta']['lines'])===1 && $incident['meta']['lines'][0]['id']==='EMP-1-A-1' && $repo->messages((int)$incident['id'])[0]['author_role']==='system','Incident built from INCIDENT_OPEN lines');

// Autoodpowiedź na nowy incydent (reguła new_case) – odpowiedź przez OR43, bo zamówienie nie ma wątku.
$ruleId=$repo->saveRule(0,['name'=>'Incydent','enabled'=>'1','platform'=>'empik','kinds'=>['incident'],'trigger_name'=>'new_case','template'=>'Dzień dobry {klient}, sprawdzamy zamówienie {zamowienie}. {podpis}','after_status'=>'auto']);
$db->update('om_msg_rules',['active_since'=>gmdate('Y-m-d H:i:s',time()-86400)],'id=:id',['id'=>$ruleId]);
$responses[]=['#POST https://marketplace\.empik\.com/api/orders/EMP-1-A/threads#',201,['thread_id'=>'th-new','message_id'=>'mm-new']];
$sent=$center->autoRespond($center->account($empikId),$repo->platformSettings('empik'));
$or43=$find('#POST .*/api/orders/EMP-1-A/threads#');
check($sent===1 && strpos($or43['body'],'name="thread_input"')!==false && strpos($or43['body'],'"to":["CUSTOMER"]')!==false && strpos($or43['body'],'Dzień dobry Ewa Lis, sprawdzamy zamówienie EMP-1-A.')!==false,'Incident auto-reply creates OR43 thread to customer');
check(preg_match('#^Content-Type: multipart/form-data; boundary=#',(string)end($or43['headers']))===1,'OR43 is multipart');
check($repo->findThread((int)$incident['id'])['status']==='auto' && $center->autoRespond($center->account($empikId),$repo->platformSettings('empik'))===0,'Auto-reply runs once and sets status');
check(count(array_filter($repo->runLog(),function ($run) { return $run['result']==='sent'; }))===1,'Run logged');

// Odpowiedź w wątku Mirakl (M12) z wyborem odbiorców.
$responses[]=['#POST https://marketplace\.empik\.com/api/inbox/threads/th-1/message#',201,['message_id'=>'mm-2','thread_id'=>'th-1']];
$center->reply((int)$mt['id'],'Paczka wyszła wczoraj.',['recipients'=>['CUSTOMER','OPERATOR']],'Ola');
$m12=$find('#/api/inbox/threads/th-1/message#');
preg_match('/\{.*\}/s',$m12['body'],$json);
check(json_decode($json[0],true)==['body'=>'Paczka wyszła wczoraj.','to'=>[['type'=>'CUSTOMER','id'=>'c-9'],['type'=>'OPERATOR']]],'M12 message_input with recipients');

// Incydent: powody RE01 i rozwiązanie OR64; zniknięcie z listy zamyka incydent.
$responses[]=['#GET https://marketplace\.empik\.com/api/reasons#',200,['reasons'=>[['code'=>'RESOLVED','label'=>'Problem rozwiązany','type'=>'INCIDENT_CLOSE','is_shop_right'=>true],['code'=>'X','label'=>'Refund','type'=>'REFUND','is_shop_right'=>true]]]];
check($center->incidentReasons($empikId)===['RESOLVED'=>'Problem rozwiązany'],'Incident close reasons from RE01');
$responses[]=['#PUT https://marketplace\.empik\.com/api/orders/EMP-1-A/lines/EMP-1-A-1/resolve_incident#',204,''];
$center->resolveIncident((int)$incident['id'],[],'RESOLVED','Ola');
check(json_decode($find('#resolve_incident#')['body'],true)===['reason_code'=>'RESOLVED'] && $repo->findThread((int)$incident['id'])['status']==='closed','OR64 resolves incident lines');
rejects(function () use ($center,$mt) { $center->resolveIncident((int)$mt['id'],[],'RESOLVED','Ola'); },'Resolve only for incidents');
$responses=array_merge([['#GET https://marketplace\.empik\.com/api/orders\?has_incident=true#',200,['orders'=>[],'total_count'=>0]]],$responses);
$center->sync(true,$empikId);
$incident=$repo->findThread((int)$incident['id']);
check((int)$incident['remote_closed']===1 && $incident['remote_status']==='INCIDENT_CLOSED' && $incident['status']==='closed','Incident missing from has_incident list is closed');

// ---------------- Morele: centrum komunikacji
$moreleId=$connections->create('morele','Morele · sklep',['remote_id'=>'cid'],['client_id'=>'cid','client_secret'=>'sec','access_token'=>'mtok','refresh_token'=>'mref']);
$responses=[
    ['#GET https://api-marketplace\.morele\.net/communication-center/threads\?start=0&limit=10#',200,['data'=>['a'=>['no'=>501,'subject'=>'Reklamacja słuchawek','lastResponseAt'=>date('Y-m-d H:i:s',time()-120),'sender'=>'Adam Nowak','needsResponse'=>true]],'filtered'=>1,'total'=>1,'needResponse'=>1]],
    ['#GET https://api-marketplace\.morele\.net/communication-center/threads\?thread_id=501#',200,['thread'=>['id'=>'abc/501','no'=>501,'subject'=>'Reklamacja słuchawek','type_id'=>'4','resource_id'=>'998877'],'messages'=>[
        ['sender_name'=>'Adam Nowak','created_at'=>date('Y-m-d H:i:s',time()-3600),'message_body'=>'<p>Nie działa lewa słuchawka.</p><p>Proszę o&nbsp;naprawę</p>','attachments'=>'{"film.mp4":"https://api-marketplace.morele.net/x"}','sent_by_operator'=>false],
        ['sender_name'=>'Sklep XYZ','created_at'=>date('Y-m-d H:i:s',time()-1800),'message_body'=>'Prosimy o numer seryjny.','attachments'=>null,'sent_by_operator'=>false],
        ['sender_name'=>'Adam Nowak','created_at'=>date('Y-m-d H:i:s',time()-120),'message_body'=>'SN123<br>pozdrawiam','attachments'=>null,'sent_by_operator'=>false],
    ]]],
];
$report=$center->sync(true,$moreleId);
check($report[0]['error']===null && $report[0]['threads']===1,'Morele sync');
check(in_array('Authorization: Bearer mtok',$find('#communication-center/threads\?thread_id=501#')['headers'],true),'Morele uses bearer token');
$mo=$repo->findByExternal($moreleId,'message','501');
$moMessages=$repo->messages((int)$mo['id']);
check($mo['meta']['category']==='Reklamacja' && $mo['order_external_id']==='998877' && (int)$mo['needs_reply']===1 && $mo['customer_name']==='Adam Nowak','Morele thread type and order resource');
check(array_column($moMessages,'author_role')===['customer','seller','customer'] && $moMessages[0]['body']==="Nie działa lewa słuchawka.\nProszę o\u{00a0}naprawę" && $moMessages[2]['body']==="SN123\npozdrawiam" && $moMessages[0]['attachments'][0]['name']==='film.mp4','Morele roles, HTML to text and attachments');
check(MoreleMessages::text('<b>A</b> &amp; B')==='A & B','HTML entity decoding');
$responses[]=['#POST https://api-marketplace\.morele\.net/communication-center/message#',200,['status'=>'OK']];
$center->reply((int)$mo['id'],"Dziękujemy.\n\nPrzyjmujemy <reklamację>.",[],'Ola');
$send=$find('#communication-center/message#');
check(json_decode($send['body'],true)===['typeId'=>4,'resourceIdentifier'=>'998877','messageBody'=>'<p>Dziękujemy.</p><p>Przyjmujemy &lt;reklamację&gt;.</p>'],'Morele send payload (typeId, resourceIdentifier, messageBody; no identifier)');
$mockTransport=Http::$transport;
Http::$transport=function (string $method,string $url,array $headers,?string $body) use (&$calls,$mockTransport): array {
    if ($method==='POST' && strpos($url,'/communication-center/message')!==false && strpos((string)$body,'resourceIdentifier')!==false) {
        $calls[]=compact('method','url','headers','body');
        return ['status'=>400,'body'=>json_encode(['status'=>'FAILED','message'=>'Expected the key "resourceIdentifier" to not exist.']),'headers'=>[]];
    }
    return $mockTransport($method,$url,$headers,$body);
};
$center->reply((int)$mo['id'],'Druga odpowiedź.',[],'Ola');
Http::$transport=$mockTransport;
$send=$find('#communication-center/message#');
check(json_decode($send['body'],true)===['typeId'=>4,'messageBody'=>'<p>Druga odpowiedź.</p>'],'Morele send drops a key rejected by validation and retries');
check(in_array('Content-Type: application/json',$send['headers'],true),'Morele send uses application/json');
$saved=$responses;
$spec=['openapi'=>'3.0.0','paths'=>['/communication-center/message'=>['post'=>['summary'=>'Dodanie wiadomości','requestBody'=>['content'=>['application/json'=>['schema'=>['$ref'=>'#/components/schemas/Msg']]]]]],'/orders'=>['get'=>['summary'=>'x']]],'components'=>['schemas'=>['Msg'=>['required'=>['typeId','messageBody'],'properties'=>['typeId'=>['type'=>'integer']]]]]];
$responses=array_merge([['#POST https://api-marketplace\.morele\.net/communication-center/message#',400,['status'=>'FAILED','message'=>'Expected the key "typeId" to exist.']],['#GET https://api-marketplace\.morele\.net/v1/docs$#',200,$spec]],$responses);
rejects(function () use ($center,$mo) { $center->reply((int)$mo['id'],'Test',[],'Ola'); },'Morele send error carries spec contract','"required":["typeId","messageBody"]');
$error=''; try { $center->reply((int)$mo['id'],'Test',[],'Ola'); } catch (\Throwable $e) { $error=$e->getMessage(); }
check(strpos($error,'POST /communication-center/message')!==false && strpos($error,'/orders')===false && strpos($error,'POST {} → HTTP 400')!==false,'Morele diagnostics show only communication-center operations and validation reply');
$responses=$saved;
$responses=array_merge([['#GET https://api-marketplace\.morele\.net/communication-center/threads\?start=0&limit=10#',200,['data'=>[['no'=>501,'lastResponseAt'=>date('Y-m-d H:i:s'),'closed'=>true]],'total'=>1]]],$responses);
$center->sync(true,$moreleId);
check((int)$repo->findThread((int)$mo['id'])['remote_closed']===1,'Morele closed thread detected');

// ---------------- Autoodpowiedzi: warunki reguł
$repo->saveSettings('morele',['enabled'=>'1','sync_messages'=>'1','autoresponder'=>'1','hours_days'=>['0'],'signature'=>'Zespół XYZ']);
$moreleSettings=$repo->platformSettings('morele');
$moreleAccount=$center->account($moreleId);
$repo->ingest($moreleId,'morele',['kind'=>'message','external_id'=>'600','subject'=>'Faktura','customer_name'=>'Kasia','needs_reply'=>true,'last_message_at'=>$iso(-30),'meta'=>['thread_id'=>'t600','type_id'=>3,'resource_id'=>''],'messages'=>[['external_id'=>'k1','author_role'=>'customer','author_name'=>'Kasia','body'=>'Poproszę fakturę VAT','created_at'=>$iso(-30)]]]);
$kasia=$repo->findByExternal($moreleId,'message','600');
$trigger=$repo->lastCustomerMessage((int)$kasia['id']);
$rule=function (array $overrides) { return array_merge(['id'=>1,'kinds'=>['message'],'active_since'=>gmdate('Y-m-d H:i:s',time()-3600),'delay_minutes'=>0,'keywords'=>'','trigger_name'=>'first_message'],$overrides); };
check($center->ruleMatches($rule([]),$kasia,$trigger,$moreleSettings)==='m:'.$trigger['id'],'First message rule matches');
check($center->ruleMatches($rule(['active_since'=>gmdate('Y-m-d H:i:s')]),$kasia,$trigger,$moreleSettings)===null,'Messages older than rule activation are skipped');
check($center->ruleMatches($rule(['delay_minutes'=>60]),$kasia,$trigger,$moreleSettings)===null,'Delay waits for human reply');
check($center->ruleMatches($rule(['keywords'=>'zwrot, faktur']),$kasia,$trigger,$moreleSettings)!==null && $center->ruleMatches($rule(['keywords'=>'zwrot']),$kasia,$trigger,$moreleSettings)===null,'Keywords filter');
check($center->ruleMatches($rule(['trigger_name'=>'outside_hours']),$kasia,$trigger,$moreleSettings)!==null && $center->ruleMatches($rule(['trigger_name'=>'outside_hours']),$kasia,$trigger,$allegroSettings)===null,'Outside hours trigger');
check($center->ruleMatches($rule(['kinds'=>['claim']]),$kasia,$trigger,$moreleSettings)===null && $center->ruleMatches($rule(['trigger_name'=>'new_case']),$kasia,$trigger,$moreleSettings)===null,'Kind and new_case filters');
$old=$trigger; $old['created_at']=gmdate('Y-m-d H:i:s',time()-4*86400);
check($center->ruleMatches($rule(['active_since'=>'2000-01-01 00:00:00']),$kasia,$old,$moreleSettings)===null,'Never auto-reply to messages older than 72h');
check($center->render('{klient}|{zamowienie}|{platforma}|{konto}|{godziny}|{podpis}',$kasia,$moreleAccount,$moreleSettings)==='Kasia||Morele|Morele · sklep|08:00–16:00|Zespół XYZ' && $center->render('{godziny}',$kasia,$moreleAccount,$allegroSettings)==='pon., wt., śr., czw., pt., sob., niedz. 00:00–23:59','Placeholders rendered');
$repo->saveRule(0,['name'=>'Tylko Allegro','enabled'=>'1','platform'=>'allegro','kinds'=>['message'],'trigger_name'=>'any_message','template'=>'x']);
$moreleRule=$repo->saveRule(0,['name'=>'Faktura','enabled'=>'1','platform'=>'','kinds'=>['message'],'trigger_name'=>'any_message','keywords'=>'faktur','template'=>"Dzień dobry {klient},\nfakturę wyślemy mailem.\n{podpis}",'after_status'=>'waiting']);
$db->update('om_msg_rules',['active_since'=>gmdate('Y-m-d H:i:s',time()-3600)],'id=:id',['id'=>$moreleRule]);
$sent=$center->autoRespond($moreleAccount,$moreleSettings);
check($sent===1 && strpos((string)json_decode($find('#communication-center/message#')['body'],true)['messageBody'],"<p>Dzień dobry Kasia,<br>\nfakturę wyślemy mailem.<br>\nZespół XYZ</p>")!==false,'Morele auto-reply sent by matching rule only');
$kasia=$repo->findThread((int)$kasia['id']);
check($kasia['status']==='waiting' && (int)$kasia['needs_reply']===0 && (function (array $list) { return end($list); })($repo->messages((int)$kasia['id']))['source']==='auto','After-status and auto source recorded');
$repo->ingest($moreleId,'morele',['kind'=>'message','external_id'=>'600','needs_reply'=>true,'last_message_at'=>$iso(-1),'messages'=>[['external_id'=>'k2','author_role'=>'customer','author_name'=>'Kasia','body'=>'A faktura korygująca?','created_at'=>$iso(-1)]]]);
check($center->autoRespond($moreleAccount,$moreleSettings)===0,'Cooldown blocks a second auto-reply in the same thread');
$off=$moreleSettings; $off['autoresponder']=0;
$db->update('om_msg_rules',['cooldown_hours'=>0],'id=:id',['id'=>$moreleRule]);
check($center->autoRespond($moreleAccount,$off)===0,'Autoresponder switch per marketplace');
check($center->autoRespond($moreleAccount,$moreleSettings)===1,'Next customer message answered after cooldown');
$responses=[['#POST https://api-marketplace\.morele\.net/communication-center/message#',500,['message'=>'Internal error']]];
$repo->ingest($moreleId,'morele',['kind'=>'message','external_id'=>'600','needs_reply'=>true,'last_message_at'=>$iso(0),'messages'=>[['external_id'=>'k3','author_role'=>'customer','author_name'=>'Kasia','body'=>'faktura?','created_at'=>$iso(0)]]]);
$db->update('om_msg_threads',['status'=>'waiting'],'external_id=:e',['e'=>'600']);
check($center->autoRespond($moreleAccount,$moreleSettings)===0 && $repo->runLog(1)[0]['result']==='error','Failed auto-reply is logged, not retried as sent');

// ---------------- ERLI, WooCommerce, Temu, własny sklep: uwagi do zamówień i zwroty z importu
$addOrder=function (string $platform,int $connectionId,string $externalId,array $details,string $buyer='Klient') use ($db): int {
    $accountId=(int)$db->fetchColumn('SELECT id FROM om_accounts WHERE platform=:p AND source_id=:s',['p'=>$platform,'s'=>$connectionId]);
    $now=gmdate('Y-m-d H:i:s');
    return (int)$db->insert('om_orders',['account_id'=>$accountId,'external_id'=>$externalId,'remote_status'=>'x','status_id'=>1,'ordered_at'=>gmdate('Y-m-d H:i:s',time()-600),'buyer_name'=>$buyer,'email'=>'k@example.invalid','phone'=>'','total_cents'=>100,'currency'=>'PLN','details_json'=>json_encode($details),'imported_at'=>$now,'updated_at'=>$now]);
};
$erliId=$connections->create('erli','ERLI · Sklep',['shop_id'=>'9'],['api_key'=>'ERLI-KEY']);
$addOrder('erli',$erliId,'ER-1',['buyer_note'=>'Proszę o fakturę na firmę','raw'=>['id'=>'ER-1','items'=>[['name'=>'Kabel HDMI'],['name'=>'Adapter']],'comment'=>'Proszę o fakturę na firmę',
    'returns'=>[['items'=>[['index'=>1,'quantity'=>2]],'reason'=>'itemDamaged','comment'=>'Adapter pęknięty','created'=>$iso(-300),'bankAccount'=>['number'=>str_repeat('1',26),'name'=>'Jan']]]]],'Jan Kabel');
$addOrder('erli',$erliId,'ER-2',['buyer_note'=>'','raw'=>['id'=>'ER-2']]);
$responses=[];
$before=count($calls);
$report=$center->sync(true,$erliId);
check($report[0]['error']===null && $report[0]['threads']===2 && count($calls)===$before,'ERLI note and return built from imported orders without API calls');
$erliNote=$repo->findByExternal($erliId,'note','order:ER-1');
$erliReturn=$repo->findByExternal($erliId,'return','order:ER-1');
check($erliNote['status']==='new' && $repo->messages((int)$erliNote['id'])[0]['body']==='Proszę o fakturę na firmę' && $erliNote['customer_name']==='Jan Kabel','ERLI buyer note thread');
$returnBody=$repo->messages((int)$erliReturn['id'])[0]['body'];
check($erliReturn['subject']==='Zwrot: Przedmiot uszkodzony (paczka cała)' && strpos($returnBody,'Adapter × 2')!==false && strpos($returnBody,'Komentarz kupującego: Adapter pęknięty')!==false && strpos($returnBody,str_repeat('1',26))!==false,'ERLI return with reason, items, comment and bank account');
check($repo->findByExternal($erliId,'note','order:ER-2')===null,'Orders without note are skipped');
check(!MessageCenter::canReply($erliReturn) && !MessageCenter::canReply($erliNote),'ERLI threads are read-only');
rejects(function () use ($center,$erliReturn) { $center->reply((int)$erliReturn['id'],'x',[],'Ola'); },'ERLI return reply rejected','panelu ERLI');
$center->sync(true,$erliId);
check(count($repo->messages((int)$erliReturn['id']))===1,'Re-sync does not duplicate return messages');

$wooId=$connections->create('woocommerce','WooCommerce · sklep',['shop_url'=>'https://sklep.example.invalid'],['consumer_key'=>'ck_1','consumer_secret'=>'cs_1']);
$addOrder('woocommerce',$wooId,'501',['buyer_note'=>'Proszę zadzwonić przed dostawą','raw'=>['number'=>'501']],'Ewa');
$center->sync(true,$wooId);
$wooNote=$repo->findByExternal($wooId,'note','order:501');
check($wooNote['subject']==='Uwaga do zamówienia 501' && MessageCenter::canReply($wooNote),'WooCommerce note is answerable');
$responses=[['#POST https://sklep\.example\.invalid/wp-json/wc/v3/orders/501/notes#',201,['id'=>77]]];
$center->reply((int)$wooNote['id'],'Zadzwonimy.',[],'Ola');
check(json_decode($find('#/orders/501/notes#')['body'],true)==['note'=>'Zadzwonimy.','customer_note'=>true] && $repo->findThread((int)$wooNote['id'])['status']==='answered','WooCommerce reply is a customer note (e-mailed by the shop)');

$addOrder('allegro',$allegroId,'cf-note',['buyer_note'=>'Paczkomat KRA01 proszę','raw'=>['buyer'=>['login'=>'marek']]],'Marek');
$db->update('om_msg_threads',['status'=>'closed'],'1=1');
$center->sync(true,$allegroId);
$allegroNote=$repo->findByExternal($allegroId,'note','order:cf-note');
$responses=[['#POST https://api\.allegro\.pl/messaging/messages#',201,['id'=>'NM1']]];
$center->reply((int)$allegroNote['id'],'Wyślemy do KRA01.',[],'Ola');
check(json_decode($find('#POST .*/messaging/messages#')['body'],true)==['recipient'=>['login'=>'marek'],'text'=>'Wyślemy do KRA01.','order'=>['id'=>'cf-note']],'Allegro note reply opens a message to the buyer about the order');

$temuId=$connections->create('temu','Temu · sklep',[],['app_key'=>'k','app_secret'=>'s','access_token'=>'t']);
$addOrder('temu',$temuId,'PO-9',['buyer_note'=>'Gift wrap','raw'=>[]]);
$apiId=$connections->create('api','Sklep własny',[],[],'active',hash('sha256','tok'));
$addOrder('api',$apiId,'W-1',['buyer_note'=>'Odbiór osobisty','raw'=>[]]);
$center->sync(true);
$temuNote=$repo->findByExternal($temuId,'note','order:PO-9');
check($temuNote!==null && $repo->findByExternal($apiId,'note','order:W-1')!==null && !MessageCenter::canReply($temuNote),'Temu and own shop notes imported read-only');
rejects(function () use ($center,$temuNote) { $center->reply((int)$temuNote['id'],'x',[],'Ola'); },'Temu reply rejected','nie udostępnia');
$repo->saveSettings('temu',['enabled'=>'1','sync_notes'=>'0']);
check($repo->platformSettings('temu')['sync_notes']===0 && !isset($repo->platformSettings('temu')['sync_messages_native']),'Notes can be switched off per channel');

// ---------------- PrestaShop: obsługa klienta (customer_threads / customer_messages)
$psId=$connections->create('prestashop','PrestaShop · sklep',['shop_url'=>'https://presta.example.invalid'],['api_key'=>'WSKEY']);
$repo->saveSettings('prestashop',['enabled'=>'1','sync_messages'=>'1','employee_id'=>'3']);
$responses=[
    ['#GET https://presta\.example\.invalid/api/customer_threads\?#',200,['customer_threads'=>[['id'=>12,'id_customer'=>4,'id_order'=>9,'id_contact'=>1,'email'=>'ewa@example.invalid','status'=>'open','date_add'=>date('Y-m-d H:i:s',time()-7200),'date_upd'=>date('Y-m-d H:i:s',time()-60)]]]],
    ['#GET https://presta\.example\.invalid/api/customer_messages\?#',200,['customer_messages'=>[
        ['id'=>30,'id_employee'=>0,'id_customer_thread'=>12,'message'=>'Czy mogę zmienić adres?','private'=>'0','date_add'=>date('Y-m-d H:i:s',time()-7200),'file_name'=>''],
        ['id'=>31,'id_employee'=>2,'id_customer_thread'=>12,'message'=>'Tak, jaki adres?','private'=>'0','date_add'=>date('Y-m-d H:i:s',time()-3600)],
        ['id'=>32,'id_employee'=>2,'id_customer_thread'=>12,'message'=>'VIP','private'=>'1','date_add'=>date('Y-m-d H:i:s',time()-3500)],
        ['id'=>33,'id_employee'=>0,'id_customer_thread'=>12,'message'=>'ul. Nowa 1 &amp; 2','private'=>'0','date_add'=>date('Y-m-d H:i:s',time()-60),'file_name'=>'mapa.png'],
    ]]],
    ['#GET https://presta\.example\.invalid/api/customers/4\?#',200,['customer'=>['firstname'=>'Ewa','lastname'=>'Lis']]],
];
$report=$center->sync(true,$psId);
$threadsCall=$find('#/api/customer_threads\?#');
check($report[0]['error']===null && strpos(urldecode($threadsCall['url']),'filter[date_upd]=[')!==false && strpos($threadsCall['url'],'date=1')!==false && in_array('Authorization: Basic '.base64_encode('WSKEY:'),$threadsCall['headers'],true),'PrestaShop customer_threads request with date filter');
$ps=$repo->findByExternal($psId,'message','12');
$psMessages=$repo->messages((int)$ps['id']);
check($ps['customer_name']==='Ewa Lis' && $ps['order_external_id']==='9' && (int)$ps['needs_reply']===1 && $ps['meta']['ps_status']==='Otwarty','PrestaShop thread mapped');
check(array_column($psMessages,'author_role')===['customer','seller','system','customer'] && $psMessages[3]['body']==='ul. Nowa 1 & 2' && $psMessages[3]['attachments'][0]['name']==='mapa.png','PrestaShop roles, private note and attachment');
$responses[]=['#POST https://presta\.example\.invalid/api/customer_messages#',201,['customer_message'=>['id'=>34]]];
$center->reply((int)$ps['id'],'Zmienione ]]> <ok>',[],'Ola');
$post=$find('#POST .*/api/customer_messages#');
check(strpos($post['body'],'<id_employee>3</id_employee><id_customer_thread>12</id_customer_thread>')!==false && strpos($post['body'],'<![CDATA[Zmienione ]]]]><![CDATA[> <ok>]]>')!==false && in_array('Content-Type: application/xml',$post['headers'],true),'PrestaShop reply posts XML customer_message with safe CDATA');
check((function (array $l) { return end($l); })($repo->messages((int)$ps['id']))['external_id']==='34','PrestaShop reply id stored');
$responses=array_merge([['#GET https://presta\.example\.invalid/api/customer_threads\?#',403,'Forbidden']],$responses);
$report=$center->sync(true,$psId);
check(strpos((string)$report[0]['error'],'customer_threads')!==false,'PrestaShop permission hint');

// Autoodpowiedzi pomijają wątki bez kanału odpowiedzi.
$repo->saveSettings('erli',['enabled'=>'1','sync_notes'=>'1','sync_returns'=>'1','autoresponder'=>'1']);
$anyRule=$repo->saveRule(0,['name'=>'Wszystko','enabled'=>'1','kinds'=>['note','return'],'trigger_name'=>'any_message','template'=>'x']);
$db->update('om_msg_rules',['active_since'=>gmdate('Y-m-d H:i:s',time()-86400)],'id=:id',['id'=>$anyRule]);
$db->update('om_msg_threads',['status'=>'waiting','needs_reply'=>1],'connection_id=:c',['c'=>$erliId]);
check($center->autoRespond($center->account($erliId),$repo->platformSettings('erli'))===0,'Auto-replies skip read-only channels');
$repo->deleteRule($anyRule);

// Reguły: walidacja, przełączanie, kolejność.
rejects(function () use ($repo) { $repo->saveRule(0,['name'=>'x','trigger_name'=>'first_message','kinds'=>['message'],'template'=>str_repeat('a',2001)]); },'Template limit','2000');
rejects(function () use ($repo) { $repo->saveRule(0,['name'=>'x','trigger_name'=>'first_message','kinds'=>[],'template'=>'a']); },'Kinds required','rodzaj');
$noReply=$repo->saveRule(0,['name'=>'Brak odp.','trigger_name'=>'no_reply','kinds'=>['message'],'template'=>'a']);
check((int)$repo->rule($noReply)['delay_minutes']===60 && (int)$repo->rule($noReply)['enabled']===0,'No-reply rule gets default delay; unchecked = disabled');
$repo->toggleRule($noReply);
check((int)$repo->rule($noReply)['enabled']===1 && $repo->rule($noReply)['active_since']>=gmdate('Y-m-d H:i:s',time()-5),'Enabling a rule resets activation time');
$ids=array_map('intval',array_column($repo->rules(),'id'));
$repo->moveRule(end($ids),-1);
$moved=array_map('intval',array_column($repo->rules(),'id'));
check($moved[count($moved)-2]===end($ids),'Rule moved up');
$repo->deleteRule($noReply);
check($repo->rule($noReply)===null,'Rule deleted');

// Widoki: skrzynka, wątki każdego rodzaju, reguły, ustawienia.
$smarty=App\Core\SmartyFactory::create();
$accounts=$center->connectedAccounts();
$settings=$repo->settings();
$platforms=[];
foreach (MessageRepository::PLATFORMS as $code=>$label) { $platforms[$code]=['label'=>$label,'kinds'=>MessageRepository::KINDS[$code],'accounts'=>array_values(array_filter($accounts,function ($a) use ($code) { return $a['platform']===$code; })),'settings'=>$settings[$code]]; }
$base=['canWrite'=>true,'csrf'=>'x','flashSuccess'=>null,'flashError'=>null,'filters'=>['platform'=>'','kind'=>'','status'=>'open','connection'=>0,'q'=>'','page'=>1],'filterQuery'=>'','backQuery'=>'tab=inbox','counters'=>$repo->counters(),'platforms'=>$platforms,'accounts'=>$accounts,
    'statuses'=>MessageRepository::STATUSES,'kindLabels'=>MessageRepository::KIND_LABELS,'platformLabels'=>MessageRepository::PLATFORMS,'rules'=>$repo->rules(),'editRule'=>null,'runLog'=>$repo->runLog(),'triggers'=>MessageRepository::TRIGGERS,'afterStatuses'=>MessageRepository::AFTER_STATUSES,'placeholders'=>MessageCenter::PLACEHOLDERS,
    'replyTemplatesJson'=>'[]','threadJson'=>'{}','syncStates'=>array_map(function () { return ['last'=>'','error'=>'Błąd <testowy>']; },array_column($accounts,null,'id')),'thread'=>null,'messages'=>[],'threadView'=>[]];
$listing=$repo->listing(['status'=>'all']);
foreach ($listing['rows'] as &$row) { $row+=['last_label'=>'12:00','due_label'=>'','due_soon'=>false]; } unset($row);
$smarty->assign(array_merge($base,['tab'=>'inbox','listing'=>$listing]));
$html=$smarty->fetch('messages/index.tpl');
check(strpos($html,'Wszystkie kanały')!==false && strpos($html,'ms-p-allegro')!==false && strpos($html,'Reklamacje')!==false && strpos($html,'Incydenty')!==false && strpos($html,'Morele')!==false,'Inbox renders sections per marketplace');
foreach ($repo->db()->fetchAll('SELECT id FROM om_msg_threads') as $threadRow) {
    $thread=$repo->findThread((int)$threadRow['id']) + ['last_label'=>'','due_label'=>'1.01.2026 10:00','due_soon'=>true];
    $view=['order_url'=>'','message_types'=>App\Services\Messages\AllegroMessages::MESSAGE_TYPES[$thread['kind']]??[],'decisions'=>$thread['kind']==='claim'?['Uznanie'=>['ACCEPTED_REFUND'=>'x'],'Odrzucenie'=>['REJECTED_OTHER'=>'y']]:[],'reasons'=>$thread['kind']==='incident'?['R'=>'Powód']:[],'reasons_error'=>'','recipients'=>$thread['platform']==='empik'?['CUSTOMER'=>'Klient']:[],'meta_rows'=>['Powód'=>'<b>x</b>'],'can_reply'=>MessageCenter::canReply($thread),'reply_hint'=>'Brak API <odpowiedzi>'];
    $thread['meta']['lines']=$thread['kind']==='incident'?[['id'=>'L1','title'=>'Książka','sku'=>'K','quantity'=>1,'reason_code'=>'D','reason_label'=>'Uszkodzony']]:($thread['meta']['lines']??[]);
    $thread['remote_closed']=0;
    $messages=array_map(function ($m) { return $m+['time_label'=>'10:00']; },$repo->messages((int)$thread['id']));
    $smarty->assign(array_merge($base,['tab'=>'inbox','listing'=>$listing,'thread'=>$thread,'messages'=>$messages,'threadView'=>$view]));
    $html=$smarty->fetch('messages/index.tpl');
    check(strpos($html,"ms-conversation")!==false && (MessageCenter::canReply($thread) ? strpos($html,"data-ms-reply")!==false : strpos($html,'Brak API &lt;odpowiedzi&gt;')!==false && strpos($html,'data-ms-reply')===false) && strpos($html,'<b>x</b>')===false,'Thread view renders and escapes: '.$thread['platform'].'/'.$thread['kind']);
    check(($thread['kind']==='claim')===(strpos($html,'Decyzja w reklamacji')!==false) && ($thread['kind']==='incident')===(strpos($html,'Rozwiąż incydent')!==false),'Kind-specific actions: '.$thread['platform'].'/'.$thread['kind']);
}
$smarty->assign(array_merge($base,['tab'=>'rules','listing'=>$listing,'editRule'=>$repo->rule($moreleRule)]));
$html=$smarty->fetch('messages/index.tpl');
check(strpos($html,'Zapisz regułę')!==false && strpos($html,'{klient}')!==false && strpos($html,'Faktura')!==false,'Rules view renders editor and placeholders');
$smarty->assign(array_merge($base,['tab'=>'settings','listing'=>$listing]));
$html=$smarty->fetch('messages/index.tpl');
check(strpos($html,'Dyskusje i reklamacje')!==false && strpos($html,'Incydenty')!==false && strpos($html,'Błąd &lt;testowy&gt;')!==false && substr_count($html,'name="operation" value="settings"')===count(MessageRepository::PLATFORMS) && strpos($html,'name="sync_returns"')!==false && strpos($html,'name="employee_id"')!==false,'Settings view renders one form per marketplace');

Http::$transport=null;
echo 'OK: '.$checks." message checks; no network or production database used.\n";
