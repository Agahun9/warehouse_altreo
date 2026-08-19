<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\AdministrationController;
use App\Controllers\AccountingWarehouseController;
use App\Controllers\AllegroController;
use App\Controllers\AuthController;
use App\Controllers\CategoryController;
use App\Controllers\ComputersController;
use App\Controllers\CsvTemplateController;
use App\Controllers\EmpikController;
use App\Controllers\ErliController;
use App\Controllers\IndexController;
use App\Controllers\MoreleController;
use App\Controllers\MediaController;
use App\Controllers\MediaMarktController;
use App\Controllers\ProductController;
use App\Controllers\PrintTemplateController;
use App\Controllers\SellasistController;
use App\Controllers\TaskboardController;
use App\Controllers\WorkTimeController;

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
            case 'computers':
                $controller = new ComputersController();
                break;
            case 'accountingwarehouse':
                $controller = new AccountingWarehouseController();
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
            case 'mediamarkt':
                $controller = new MediaMarktController();
                break;
            case 'erli':
                $controller = new ErliController();
                break;
            case 'morele':
                $controller = new MoreleController();
                break;
            case 'media':
                $controller = new MediaController();
                break;
            case 'auth':
                $controller = new AuthController();
                break;
            case 'admin':
            case 'administration':
                $controller = new AdministrationController();
                break;
            case 'csvtemplates':
                $controller = new CsvTemplateController();
                break;
            case 'sellasist':
                $controller = new SellasistController();
                break;
            case 'printtemplates':
                $controller = new PrintTemplateController();
                break;
            case 'taskboard':
                $controller = new TaskboardController();
                break;
            case 'worktime':
                $controller = new WorkTimeController();
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
            if ($path === 'api/products/search') {
                $controller = new ProductController();
                $controller->apisearch();
                return true;
            }

            if (preg_match('#^api/products/sku/(.+)$#', $path, $matches) === 1) {
                $_GET['sku'] = urldecode((string) $matches[1]);

                $controller = new ProductController();
                $controller->apibysku();
                return true;
            }

            if (preg_match('#^api/products/(\d+)$#', $path, $matches) === 1) {
                $_GET['id'] = (int) $matches[1];

                $controller = new ProductController();
                $controller->apishow();
                return true;
            }

            return false;
        }

        $_GET['id'] = (int) $matches[1];

        $controller = new CsvTemplateController();
        $controller->apiexport();

        return true;
    }
}
