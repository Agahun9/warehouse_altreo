<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\AdminController;
use App\Controllers\AllegroController;
use App\Controllers\AuthController;
use App\Controllers\CategoryController;
use App\Controllers\CsvTemplateController;
use App\Controllers\EmpikController;
use App\Controllers\IndexController;
use App\Controllers\ProductController;
use App\Controllers\SellasistController;
use App\Controllers\TaskboardController;

class Application
{
    public function run(): void
    {
        if ($this->handleApiRoutes()) {
            return;
        }

        $controllerName = strtolower((string) ($_GET['controller'] ?? 'index'));
        $actionName = strtolower((string) ($_GET['action'] ?? 'index'));

        switch ($controllerName) {
            case 'index':
            case '':
                $controller = new IndexController();
                break;
            case 'products':
                $controller = new ProductController();
                break;
            case 'categories':
                $controller = new CategoryController();
                break;
            case 'allegro':
                $controller = new AllegroController();
                break;
            case 'empik':
                $controller = new EmpikController();
                break;
            case 'auth':
                $controller = new AuthController();
                break;
            case 'admin':
                $controller = new AdminController();
                break;
            case 'csvtemplates':
                $controller = new CsvTemplateController();
                break;
            case 'sellasist':
                $controller = new SellasistController();
                break;
            case 'taskboard':
                $controller = new TaskboardController();
                break;
            default:
                http_response_code(404);
                echo 'Kontroler nie istnieje.';
                return;
        }

        if (!method_exists($controller, $actionName)) {
            http_response_code(404);
            echo 'Akcja nie istnieje.';
            return;
        }

        $controller->{$actionName}();
    }

    private function handleApiRoutes(): bool
    {
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $path = trim((string) parse_url($requestUri, PHP_URL_PATH), '/');

        if ($path === '') {
            return false;
        }

        if (preg_match('#^api/export/csv/(\d+)$#', $path, $matches) !== 1) {
            return false;
        }

        $_GET['id'] = (int) $matches[1];

        $controller = new CsvTemplateController();
        $controller->apiexport();

        return true;
    }
}
