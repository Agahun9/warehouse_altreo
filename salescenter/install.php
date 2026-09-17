<?php
/**
 * Instalator przez przeglądarkę (gdy nie ma dostępu do SSH):
 *   https://twoja-domena/install.php?key=<install_key z app/Config/app.php>
 * Tworzy wszystkie tabele w bazie. Po udanej instalacji zapisuje blokadę
 * app/Storage/installed.lock – kolejne uruchomienie wymaga jej usunięcia
 * (albo użyj CLI: php bin/install.php).
 */
declare(strict_types=1);
define('BASE_PATH', __DIR__);
require BASE_PATH.'/app/bootstrap.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
$lock = BASE_PATH.'/app/Storage/installed.lock';
$e = static function (string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
echo '<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalacja SalesCenter</title><style>body{font:15px system-ui,sans-serif;max-width:720px;margin:40px auto;padding:0 16px;color:#0f172a}li{margin:.3rem 0}.ok{color:#047857}.err{color:#b91c1c}button{padding:.6rem 1.2rem;font-size:15px}</style></head><body><h1>Instalacja SalesCenter</h1>';
try {
    $config = App\Core\Config::get('app');
    $expected = (string) ($config['install_key'] ?? '');
    if ($expected === '' || !hash_equals($expected, (string) ($_GET['key'] ?? ''))) {
        http_response_code(403);
        echo '<p class="err">Nieprawidłowy klucz instalacji. Użyj adresu install.php?key=… z wartością install_key z app/Config/app.php.</p></body></html>';
        exit;
    }
    if (is_file($lock)) {
        echo '<p>Instalacja została już wykonana ('.$e((string) file_get_contents($lock)).'). Aby uruchomić ją ponownie, usuń plik app/Storage/installed.lock albo użyj <code>php bin/install.php</code>.</p><p><a href="index.php">Przejdź do aplikacji</a></p></body></html>';
        exit;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        echo '<p>Instalator utworzy tabele w bazie skonfigurowanej w <code>app/Config/database.php</code>. Operację można bezpiecznie powtórzyć – istniejące dane nie są usuwane.</p><form method="post"><button type="submit">Utwórz tabele</button></form></body></html>';
        exit;
    }
    echo '<ul>';
    foreach (App\Services\Installer::run() as $step) { echo '<li class="ok">✔ '.$e($step).'</li>'; }
    echo '</ul>';
    if (!is_dir(dirname($lock))) { mkdir(dirname($lock), 0755, true); }
    file_put_contents($lock, gmdate('Y-m-d H:i:s').' UTC');
    echo '<p class="ok"><strong>Gotowe.</strong></p><p><a href="index.php?controller=auth&action=register">Zarejestruj pierwszą firmę</a></p>';
} catch (Throwable $error) {
    http_response_code(500);
    echo '<p class="err">✘ '.$e($error->getMessage()).'</p>';
}
echo '</body></html>';
