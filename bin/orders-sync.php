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
    echo json_encode(['at'=>gmdate('c'),'results'=>$result],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
    exit(count(array_filter($result,static function ($r) { return !empty($r['error']); })) ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Order sync failed: '.get_class($e).PHP_EOL);
    exit(1);
}
