<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\AccountController;
use App\Controllers\AdministrationController;
use App\Controllers\AuthController;
use App\Controllers\IntegrationsController;
use App\Controllers\MessagesController;
use App\Controllers\OrdersController;
use App\Controllers\PrintAgentController;

class Application
{
    public function run(): void
    {
        if ($this->handleApiRoutes()) {
            return;
        }

        $controllerName = strtolower((string) ($_GET['controller'] ?? 'orders'));
        $actionName = strtolower((string) ($_GET['action'] ?? 'index'));

        switch ($controllerName) {
            case '':
            case 'index':
            case 'orders':
                $controller = new OrdersController();
                break;
            case 'auth':
                $controller = new AuthController();
                break;
            case 'account':
                $controller = new AccountController();
                break;
            case 'api':
                $controller = new \App\Controllers\ApiController();
                break;
            case 'integrations':
                $controller = new IntegrationsController();
                break;
            case 'administration':
            case 'cron': // stare linki cron wklejone na serwerze
                $controller = new AdministrationController();
                break;
            case 'messages':
                $controller = new MessagesController();
                break;
            default:
                http_response_code(404);
                echo 'Kontroler nie istnieje.';
                return;
        }

        if (!method_exists($controller, $actionName) || !(new \ReflectionMethod($controller, $actionName))->isPublic()) {
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

        if (preg_match('#(?:^|/)api/print-agent/health$#',$path)===1) {
            (new PrintAgentController())->health();
            return true;
        }

        if (preg_match('#(?:^|/)api/print-agent/stations/heartbeat$#',$path)===1) {
            (new PrintAgentController())->heartbeat();
            return true;
        }

        if (preg_match('#(?:^|/)api/print-agent/jobs/next$#',$path)===1) {
            (new PrintAgentController())->next();
            return true;
        }

        if (preg_match('#(?:^|/)api/print-agent/jobs/([0-9a-f-]{36})/status$#i',$path,$printMatches)===1) {
            $_GET['job_id']=$printMatches[1];
            (new PrintAgentController())->status();
            return true;
        }

        if (preg_match('#(?:^|/)api/print-agent/jobs/([0-9a-f-]{36})/pdf$#i',$path,$printMatches)===1) {
            $_GET['job_id']=$printMatches[1];
            (new PrintAgentController())->pdf();
            return true;
        }

        if (preg_match('#(?:^|/)api/print-agent/fiscal/next$#',$path)===1) {
            (new PrintAgentController())->fiscalnext();
            return true;
        }

        if (preg_match('#(?:^|/)api/print-agent/fiscal/([0-9a-f-]{36})/status$#i',$path,$printMatches)===1) {
            $_GET['job_id']=$printMatches[1];
            (new PrintAgentController())->fiscalstatus();
            return true;
        }

        return false;
    }
}
