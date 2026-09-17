<?php

declare(strict_types=1);

define('BASE_PATH',dirname(__DIR__));
require BASE_PATH.'/app/bootstrap.php';

use App\Core\Database;
use App\Services\MarketplaceAccountDeletionService;

$reflection=new ReflectionClass(Database::class);
$db=$reflection->newInstanceWithoutConstructor();
$pdo=new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
foreach (['pdo'=>$pdo,'config'=>['driver'=>'sqlite']] as $name=>$value) { $reflection->getProperty($name)->setValue($db,$value); }
$service=new MarketplaceAccountDeletionService($db);
$db->query('CREATE TABLE om_accounts (platform TEXT,source_id INTEGER,enabled INTEGER)');
foreach (['allegro','empik','mediamarkt','erli'] as $platform) {
    $account=$platform.'_accounts';
    $offers=$platform==='erli'?'erli_products':$platform.'_offers';
    $db->query("CREATE TABLE $account (id INTEGER PRIMARY KEY,name TEXT,is_running INTEGER DEFAULT 0)");
    $db->query("CREATE TABLE $offers (id INTEGER PRIMARY KEY,account_id INTEGER)");
    $db->query("INSERT INTO $account (id,name) VALUES (1,'Usuwane'),(2,'Zachowane')");
    $db->query("INSERT INTO $offers (id,account_id) VALUES (1,1),(2,1),(3,2)");
    $db->query('INSERT INTO om_accounts (platform,source_id,enabled) VALUES (:platform,1,1),(:other,2,1)',['platform'=>$platform,'other'=>$platform]);
}
foreach (['allegro_offer_change_queue','allegro_offer_exclusions','allegro_account_tokens','allegro_sync_states','empik_offer_change_queue','mediamarkt_offer_change_queue','erli_product_change_queue'] as $table) {
    $db->query("CREATE TABLE $table (account_id INTEGER,is_running INTEGER DEFAULT 0)");
    $db->query("INSERT INTO $table (account_id) VALUES (1),(2)");
}
$check=function (bool $condition,string $message): void { if (!$condition) { throw new RuntimeException($message); } };
foreach (['allegro','empik','mediamarkt','erli'] as $platform) {
    $offers=$platform==='erli'?'erli_products':$platform.'_offers';
    $check($service->offerCounts($platform)===[1=>2,2=>1],$platform.' preview counts');
    $result=$service->delete($platform,1);
    $check($result['offers']===2 && $result['name']==='Usuwane',$platform.' deletion summary');
    $check((int)$db->fetchColumn("SELECT COUNT(*) FROM $offers WHERE account_id=1")===0,$platform.' offers removed');
    $check((int)$db->fetchColumn("SELECT COUNT(*) FROM $offers WHERE account_id=2")===1,$platform.' other offers kept');
    $check((int)$db->fetchColumn('SELECT enabled FROM om_accounts WHERE platform=:platform AND source_id=1',['platform'=>$platform])===0,$platform.' order import disabled');
    $check((int)$db->fetchColumn('SELECT enabled FROM om_accounts WHERE platform=:platform AND source_id=2',['platform'=>$platform])===1,$platform.' other import kept');
}
foreach (['allegro_offer_change_queue','allegro_offer_exclusions','allegro_account_tokens','allegro_sync_states','empik_offer_change_queue','mediamarkt_offer_change_queue','erli_product_change_queue'] as $table) {
    $check((int)$db->fetchColumn("SELECT COUNT(*) FROM $table WHERE account_id=1")===0,$table.' related rows removed');
    $check((int)$db->fetchColumn("SELECT COUNT(*) FROM $table WHERE account_id=2")===1,$table.' other related rows kept');
}
try { $service->delete('empik',1); throw new RuntimeException('Second deletion accepted'); }
catch (InvalidArgumentException $expected) {}
try { $service->delete('unknown',2); throw new RuntimeException('Unknown platform accepted'); }
catch (InvalidArgumentException $expected) {}
$db->update('erli_accounts',['is_running'=>1],'id=2');
try { $service->delete('erli',2); throw new RuntimeException('Running sync deleted'); }
catch (RuntimeException $expected) { $check($expected->getMessage()!=='Running sync deleted','Running sync blocked'); }
$check((int)$db->fetchColumn('SELECT COUNT(*) FROM erli_accounts WHERE id=2')===1,'Blocked account kept');
$db->query('CREATE TABLE app_settings (setting_key TEXT,setting_value TEXT)');
$db->query('CREATE TABLE morele_offers (id INTEGER PRIMARY KEY,account_id INTEGER)');
$db->query('CREATE TABLE morele_offer_change_queue (offer_row_id INTEGER)');
$db->query("INSERT INTO app_settings VALUES ('morele_account','Sklep Morele'),('morele_client_id','test'),('morele_client_secret','test'),('morele_access_token','test'),('morele_refresh_token','test'),('computers_morele_category_id','672')");
$db->query('INSERT INTO morele_offers (id,account_id) VALUES (1,1),(2,2)');
$db->query('INSERT INTO morele_offer_change_queue (offer_row_id) VALUES (1),(2)');
$check($service->offerCounts('morele')===[1=>1],'Morele preview count');
$result=$service->delete('morele',1);
$check($result['name']==='Sklep Morele' && $result['offers']===1,'Morele deletion summary');
$check((int)$db->fetchColumn('SELECT COUNT(*) FROM morele_offers WHERE account_id=1')===0,'Morele offer removed');
$check((int)$db->fetchColumn('SELECT COUNT(*) FROM morele_offer_change_queue WHERE offer_row_id=2')===1,'Other queue kept');
$check((int)$db->fetchColumn("SELECT COUNT(*) FROM app_settings WHERE setting_key LIKE 'morele_%'")===0,'Morele credentials removed');
$check((int)$db->fetchColumn("SELECT COUNT(*) FROM app_settings WHERE setting_key='computers_morele_category_id'")===1,'Morele category kept');
$db->query("INSERT INTO app_settings VALUES ('temu_app_key','test'),('temu_app_secret','test'),('temu_access_token','test'),('temu_shop_id','test'),('temu_region','PL')");
$result=$service->delete('temu',1);
$check($result['name']==='Temu' && $result['offers']===0,'Temu deletion summary');
$check((int)$db->fetchColumn("SELECT COUNT(*) FROM app_settings WHERE setting_key IN ('temu_app_key','temu_app_secret','temu_access_token','temu_shop_id')")===0,'Temu credentials removed');
$check((int)$db->fetchColumn("SELECT COUNT(*) FROM app_settings WHERE setting_key='temu_region'")===1,'Temu region kept');
echo "OK: account deletion, related offers, isolation and sync guard.\n";
