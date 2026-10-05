<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

class SmartyFactory
{
    public static function create(): object
    {
        if (class_exists(\Smarty\Smarty::class)) {
            $smarty = new \Smarty\Smarty();
        } elseif (class_exists(\Smarty::class)) {
            $smarty = new \Smarty();
        } else {
            throw new RuntimeException(
                'Smarty nie zostalo zaladowane. Umiesc biblioteke w katalogu "smarty-5.8.0" albo dodaj vendor/autoload.php.'
            );
        }

        $smarty->setTemplateDir(BASE_PATH . '/app/Views/templates');
        $smarty->setCompileDir(BASE_PATH . '/app/Views/templates_c');
        $smarty->setCacheDir(BASE_PATH . '/app/Views/cache');
        $smarty->setConfigDir(BASE_PATH . '/app/Views/configs');
        $smarty->assign('baseUrl', './index.php');
        $smarty->registerPlugin('modifier', 'pl_time', [self::class, 'plTime']);

        return $smarty;
    }

    /** Czas zapisany w bazie w UTC (gmdate) pokazany w strefie Europe/Warsaw, z uwzględnieniem czasu letniego. */
    public static function plTime($value, string $format = 'Y-m-d H:i:s'): string
    {
        $value = trim((string) $value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $value)) {
            return $value;
        }
        try {
            return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Warsaw'))->format($format);
        } catch (\Throwable $e) {
            return $value;
        }
    }
}
