<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\TemuService;
use Throwable;

class TemuController extends Controller
{
    /** @var TemuService */
    private $temu;

    public function __construct()
    {
        $this->temu = new TemuService();
    }

    public function categories(): void
    {
        $this->requireModule('categories');
        try {
            $this->jsonResponse(array('items' => $this->temu->searchCategories(trim((string) $this->input('search', '')))));
        } catch (Throwable $exception) {
            $this->jsonResponse(array('error' => $exception->getMessage()), 500);
        }
    }

    public function parameters(): void
    {
        $this->requireModule('categories');
        try {
            $this->jsonResponse(array('items' => $this->temu->categoryParameters(trim((string) $this->input('id', '')))));
        } catch (Throwable $exception) {
            $this->jsonResponse(array('error' => $exception->getMessage()), 500);
        }
    }

    public function category(): void
    {
        $this->requireModule('categories');
        try {
            $this->jsonResponse(array('item' => $this->temu->categoryById(trim((string) $this->input('id', '')))));
        } catch (Throwable $exception) {
            $this->jsonResponse(array('error' => $exception->getMessage()), 500);
        }
    }

    public function test(): void
    {
        $this->requireRole('admin');
        try {
            if ($this->isPost()) {
                $this->requireWriteAccess();
                $this->temu->saveConnectionSettings(array(
                    'api_url' => $this->input('temu_api_url', ''),
                    'app_key' => $this->input('temu_app_key', ''),
                    'app_secret' => $this->input('temu_app_secret', ''),
                    'access_token' => $this->input('temu_access_token', ''),
                    'shop_id' => $this->input('temu_shop_id', ''),
                    'region' => $this->input('temu_region', 'PL'),
                ));
            }
            $this->jsonResponse($this->temu->testConnection());
        } catch (Throwable $exception) {
            $this->jsonResponse(array('error' => $exception->getMessage()), 500);
        }
    }

    private function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
