<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

class PrintTemplateController extends Controller
{
    public function filemanager(): void
    {
        $this->requireModule('printtemplates');
        $this->redirect('http://192.168.1.149/tinyfilemanager.php?p=');
    }
}
