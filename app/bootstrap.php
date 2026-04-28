<?php

declare(strict_types=1);

spl_autoload_register(static function (string $className): void {
    $prefix = 'App\\';

    if (strncmp($className, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($className, strlen($prefix));
    $filePath = BASE_PATH . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($filePath)) {
        require $filePath;
    }
});

$vendorAutoload = BASE_PATH . '/vendor/autoload.php';
$localSmartyAutoload = BASE_PATH . '/smarty-5.8.0/libs/Smarty.class.php';

if (is_file($vendorAutoload)) {
    require $vendorAutoload;
} elseif (is_file($localSmartyAutoload)) {
    require $localSmartyAutoload;
}
