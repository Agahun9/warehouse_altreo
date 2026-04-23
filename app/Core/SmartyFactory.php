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

        return $smarty;
    }
}
