<?php
/** Run every minute from cron. No public HTTP endpoint or secret URL. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH.'/app/bootstrap.php';
try {
    $repo=new App\Models\OrderRepository(App\Core\Database::instance());
    $repo->ensureSchema();
    $result=(new App\Services\OrderSyncService($repo))->sync();
    // Time-based automations ("Upływ czasu") are evaluated once per cron pass after the import.
    try { $scheduled=$repo->automation()->runScheduled(); }
    catch (Throwable $automationError) { $scheduled=['error'=>App\Services\OrderSyncError::log($automationError,['stage'=>'automation_schedule'])['reference']]; }
    echo json_encode(['at'=>gmdate('c'),'results'=>$result,'automations'=>$scheduled],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
    exit(count(array_filter($result,static function ($r) { return !empty($r['error']); })) ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Order sync failed: '.get_class($e).PHP_EOL);
    exit(1);
}
