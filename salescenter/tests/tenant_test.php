<?php
/** Izolacja firm i konta SaaS na SQLite w pamięci. Nie łączy się z bazą produkcyjną ani z siecią. */
declare(strict_types=1);
define('BASE_PATH',dirname(__DIR__));
require BASE_PATH.'/app/bootstrap.php';
use App\Core\Database;
use App\Core\Tenant;
use App\Models\OrderRepository;
use App\Models\SaasRepository;
use App\Services\TenantProvisioner;

$checks=0;
function check(bool $condition,string $label): void { global $checks; $checks++; if (!$condition) { throw new RuntimeException($label); } }
function rejects(callable $fn,string $label): void { try { $fn(); } catch (\Throwable $e) { check(true,$label); return; } check(false,$label); }

$reflection=new ReflectionClass(Database::class);
$db=$reflection->newInstanceWithoutConstructor();
$pdo=new PDO('sqlite::memory:'); $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
foreach (['pdo'=>$pdo,'config'=>['driver'=>'sqlite']] as $name=>$value) { $property=$reflection->getProperty($name); $property->setValue($db,$value); }
Database::useInstance($db);

// SQL rewrite
Tenant::activate(7);
check(Tenant::rewrite('SELECT o.id FROM om_orders o JOIN om_accounts a ON a.id=o.account_id')==='SELECT o.id FROM t7_om_orders o JOIN t7_om_accounts a ON a.id=o.account_id','Tenant tables are rewritten');
check(Tenant::rewrite('SELECT print_settings FROM print_agent_jobs')==='SELECT print_settings FROM t7_print_agent_jobs','Columns with similar names stay untouched');
check(Tenant::rewrite('CREATE INDEX om_order_date ON om_orders (ordered_at)')==='CREATE INDEX om_order_date ON t7_om_orders (ordered_at)','Index names stay untouched');
check(Tenant::rewrite('SELECT * FROM sc_users')==='SELECT * FROM sc_users','Global tables are not rewritten');
Tenant::clear();
rejects(fn()=>$db->fetchAll('SELECT * FROM om_orders'),'Query to company data without active company is blocked');
check($db->fetchColumn('SELECT 1')==1,'Queries without company tables still work');

// Registration
$saas=new SaasRepository($db); $saas->ensureSchema(); $saas->ensureSchema();
$a=$saas->register('Firma A','PL 525-267-47-98','Anna A','Anna@Example.invalid','haslo-bardzo-dlugie');
$b=$saas->register('Firma B','','Bartek B','bartek@example.invalid','inne-haslo-dlugie');
rejects(fn()=>$saas->register('Firma C','','Cezary','anna@example.invalid','haslo-bardzo-dlugie'),'Duplicate e-mail rejected (case-insensitive)');
rejects(fn()=>$saas->register('Firma C','','Cezary','c@example.invalid','krotkie'),'Short password rejected');
check($saas->tenant($a['tenant_id'])['nip']==='PL5252674798','NIP normalized');
check($saas->findUserById($a['user_id'])['role']==='owner','First user is owner');
check($saas->isHeadmaster($saas->findUserById($a['user_id'])),'First user controls global cron');
check(!$saas->isHeadmaster($saas->findUserById($b['user_id'])),'Other company owner cannot control global cron');
TenantProvisioner::provision($db,$a['tenant_id']); TenantProvisioner::provision($db,$b['tenant_id']); TenantProvisioner::provision($db,$a['tenant_id']);
check(!Tenant::active(),'Provisioning restores empty context');

// Isolation of orders and numbering
foreach ([$a['tenant_id']=>'A',$b['tenant_id']=>'B'] as $tenantId=>$label) {
    Tenant::activate($tenantId);
    $repo=new OrderRepository($db); $repo->ensureSchema();
    check(count($repo->statuses())===6,'Company '.$label.' has default statuses');
    $repo->createManualOrder(['status_id'=>1,'ordered_at'=>'2026-09-17 10:00:00','buyer_name'=>'Klient '.$label,'email'=>'k'.$label.'@example.invalid','phone'=>'','currency'=>'PLN','amount_paid'=>'0.00','shipping_price'=>'0.00','payment_method'=>'Przelew','document_preference'=>'receipt','delivery'=>'Kurier','pickup'=>'','shipping_street'=>'Testowa','shipping_building'=>'1','shipping_postal_code'=>'00-001','shipping_city'=>'Warszawa','shipping_country'=>'PL','invoice_name'=>'Klient','invoice_company'=>'','invoice_nip'=>'','invoice_street'=>'Testowa','invoice_building'=>'1','invoice_postal_code'=>'00-001','invoice_city'=>'Warszawa','invoice_country'=>'PL','buyer_note'=>'','items'=>[['name'=>'Produkt '.$label,'sku'=>'','quantity'=>1,'price'=>'10.00','vat'=>'23']]],'test');
    $db->insert('om_series',['name'=>'FV '.$label,'kind'=>'invoice','pattern'=>'FV/{N}/{YYYY}','next_number'=>$label==='A'?100:1]);
    $repo->saveSetting('seller',['name'=>'Sprzedawca '.$label]);
}
Tenant::activate($a['tenant_id']); $repoA=new OrderRepository($db);
check($repoA->listing([])['total']===1 && $repoA->order(1)['buyer_name']==='Klient A','Company A sees only its order');
check((int)$db->fetchColumn('SELECT next_number FROM om_series')===100 && $repoA->setting('seller')['name']==='Sprzedawca A','Company A has own numbering and settings');
Tenant::activate($b['tenant_id']); $repoB=new OrderRepository($db);
check($repoB->listing([])['total']===1 && $repoB->order(1)['buyer_name']==='Klient B','Company B has own order #1');
check((int)$db->fetchColumn('SELECT next_number FROM om_series')===1 && $repoB->setting('seller')['name']==='Sprzedawca B','Company B has own numbering and settings');
Tenant::clear();
$cron=App\Services\GlobalCronService::run('tokens');
check($cron['companies']===2 && $cron['errors']===0 && !Tenant::active(),'Token cron visits every active company and clears context');

// Login, lockout, reset
check(isset($saas->attemptLogin('anna@example.invalid','haslo-bardzo-dlugie')['user']),'Login with valid password');
$oldHash=password_hash('haslo-bardzo-dlugie', PASSWORD_BCRYPT, ['cost'=>4]);
$db->update('sc_users',['password_hash'=>$oldHash],'id=:id',['id'=>$a['user_id']]);
$rehashUser=$saas->attemptLogin('anna@example.invalid','haslo-bardzo-dlugie')['user'];
check($rehashUser['password_hash']===$saas->findUserById($a['user_id'])['password_hash'] && $rehashUser['password_hash']!==$oldHash,'Login returns current password hash after upgrade for session fingerprint');
check(isset($saas->attemptLogin('nobody@example.invalid','x')['error']),'Unknown e-mail gives generic error');
for ($i=0;$i<8;$i++) { $saas->attemptLogin('bartek@example.invalid','zle-haslo-123'); }
check(isset($saas->attemptLogin('bartek@example.invalid','inne-haslo-dlugie')['error']),'Account locked after 8 failed attempts');
$reset=$saas->createPasswordReset('bartek@example.invalid');
check($reset!==null && $saas->validReset($reset['token'])!==null,'Password reset token issued');
$saas->consumeReset($reset['token'],'nowe-haslo-dlugie');
check(isset($saas->attemptLogin('bartek@example.invalid','nowe-haslo-dlugie')['user']),'Reset clears lock and sets password');
rejects(fn()=>$saas->consumeReset($reset['token'],'kolejne-haslo-x'),'Reset token is single use');

// Team
$member=$saas->createUser($a['tenant_id'],'Pracownik','p@example.invalid','haslo-pracownika','member','read');
check($saas->findUserById($member)['access']==='read','Member read-only access stored');
rejects(fn()=>$saas->updateUser($b['tenant_id'],$member,'X','member','edit',false),'Company B cannot edit user of company A');
rejects(fn()=>$saas->deleteUser($a['tenant_id'],$a['user_id']),'Last owner cannot be deleted');
rejects(fn()=>$saas->updateUser($a['tenant_id'],$a['user_id'],'Anna','member','edit',false),'Last owner cannot be demoted');
$secondOwner=$saas->createUser($a['tenant_id'],'Drugi właściciel','owner@example.invalid','haslo-wlasciciela','member','edit');
$saas->updateUser($a['tenant_id'],$secondOwner,'Drugi właściciel','owner','edit',false);
$saas->updateUser($a['tenant_id'],$a['user_id'],'Anna','member','edit',false);
check(!$saas->isHeadmaster($saas->findUserById($a['user_id'])),'Demoted first user loses global cron access');
$saas->updateUser($a['tenant_id'],$member,'Pracownik','member','edit',true);
check(isset($saas->attemptLogin('p@example.invalid','haslo-pracownika')['error']),'Blocked user cannot log in');

echo 'OK: '.$checks." tenant checks; no network or production database used.\n";
