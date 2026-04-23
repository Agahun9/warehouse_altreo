<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

class Config
{
    public static function get(string $name): array
    {
        $filePath = BASE_PATH . '/app/Config/' . $name . '.php';

        if (!is_file($filePath)) {
            throw new RuntimeException('Brak pliku konfiguracji: ' . $name);
        }

        $config = require $filePath;

        if (!is_array($config)) {
            throw new RuntimeException('Plik konfiguracji musi zwracac tablice: ' . $name);
        }

        return $config;
    }
}
