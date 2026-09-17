<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

class Config
{
    /** @var array<string,array> */
    private static $cache = array();

    public static function get(string $name): array
    {
        if (isset(self::$cache[$name])) {
            return self::$cache[$name];
        }

        $filePath = BASE_PATH . '/app/Config/' . $name . '.php';

        if (!is_file($filePath)) {
            throw new RuntimeException('Brak pliku konfiguracji: app/Config/' . $name . '.php (skopiuj ' . $name . '.example.php).');
        }

        $config = require $filePath;

        if (!is_array($config)) {
            throw new RuntimeException('Plik konfiguracji musi zwracac tablice: ' . $name);
        }

        return self::$cache[$name] = $config;
    }

    /** Tylko dla testów: podmienia konfigurację bez plików. */
    public static function override(string $name, array $config): void
    {
        self::$cache[$name] = $config;
    }
}
