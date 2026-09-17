<?php
/** php bin/install.php – tworzy/aktualizuje wszystkie tabele SalesCenter. Bezpieczne do wielokrotnego uruchomienia. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH.'/app/bootstrap.php';
try {
    foreach (App\Services\Installer::run() as $step) { echo '✔ '.$step.PHP_EOL; }
    echo 'Instalacja zakończona.'.PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '✘ '.$e->getMessage().PHP_EOL);
    exit(1);
}
