<?php
/** Run every minute from cron. Iterates over all active companies. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH.'/app/bootstrap.php';
try {
    $report=App\Services\GlobalCronService::run('orders');
    echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
    exit(!empty($report['errors']) ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Order sync failed: '.get_class($e).PHP_EOL);
    exit(1);
}
