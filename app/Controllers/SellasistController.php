<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\SmartyFactory;
use App\Models\SellasistStockCallFailureRepository;
use App\Services\SellasistService;
use Throwable;

class SellasistController extends Controller
{
    /** @var SellasistService */
    private $sellasist;

    /** @var SellasistStockCallFailureRepository */
    private $stockCallFailures;

    public function __construct()
    {
        $this->sellasist = new SellasistService($this->db());
        $this->stockCallFailures = new SellasistStockCallFailureRepository($this->db());
        $this->stockCallFailures->ensureSchema();
    }

    public function index(): void
    {
        $this->redirect('./index.php?controller=sellasist&action=zbieranie');
    }

    public function zbieranie(): void
    {
        $this->requireModule('sellasist');

        $orders = array();
        $config = $this->sellasist->configuration();

        try {
            $orders = $this->sellasist->listOrdersForPicking();
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->render('sellasist/index', array(
            'pageTitle' => 'Sellasist',
            'contentTitle' => 'Sellasist',
            'pageDescription' => 'Zbieranie zamowien i druk naklejek Sellasist.',
            'breadcrumbCurrent' => 'Sellasist',
            'sellasistTab' => 'zbieranie',
            'orders' => $orders,
            'sellasistConfigured' => $config['api_key'] !== '',
        ));
    }

    public function stickers(): void
    {
        $this->requireModuleWrite('sellasist');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=sellasist&action=zbieranie');
        }

        $orderIds = $this->input('order_id', array());
        if (!is_array($orderIds)) {
            $orderIds = array();
        }

        try {
            $payload = $this->sellasist->generateStickers($orderIds, true);
            $this->renderTemplateOnly('sellasist/stickers', array(
                'pageTitle' => 'Naklejki Sellasist',
                'caseStickers' => $payload['case_stickers'],
                'glassStickers' => $payload['glass_stickers'],
                'barcodeBaseUrl' => $payload['barcode_base_url'],
                'warnings' => $payload['warnings'],
            ));
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
            $this->redirect('./index.php?controller=sellasist&action=zbieranie');
        }
    }

    public function subtractstock(): void
    {
        $orderId = (int) $this->input('id', $this->input('order_id', 0));
        $this->acknowledgeSellasistWebhook('subtract_stock', $orderId);

        if (!$this->hasSellasistStockAccess()) {
            $this->recordStockCallFailure('subtract_stock', $orderId, 'Brak dostepu. Sellasist otrzymal HTTP 200, blad zapisany w CRM.', 200);
            return;
        }

        if ($orderId <= 0) {
            $this->recordStockCallFailure('subtract_stock', null, 'Brak poprawnego ID zamowienia. Sellasist otrzymal HTTP 200, blad zapisany w CRM.', 200);
            return;
        }

        try {
            $this->sellasist->subtractStockForOrder($orderId);
        } catch (Throwable $exception) {
            $this->recordStockCallFailure('subtract_stock', $orderId, $exception->getMessage() . ' Sellasist otrzymal HTTP 200, blad zapisany w CRM.', 200);
        }
    }

    public function addstock(): void
    {
        $orderId = (int) $this->input('id', $this->input('order_id', 0));
        $this->acknowledgeSellasistWebhook('add_stock', $orderId);

        if (!$this->hasSellasistStockAccess()) {
            $this->recordStockCallFailure('add_stock', $orderId, 'Brak dostepu. Sellasist otrzymal HTTP 200, blad zapisany w CRM.', 200);
            return;
        }

        if ($orderId <= 0) {
            $this->recordStockCallFailure('add_stock', null, 'Brak poprawnego ID zamowienia. Sellasist otrzymal HTTP 200, blad zapisany w CRM.', 200);
            return;
        }

        try {
            $this->sellasist->addStockForOrder($orderId);
        } catch (Throwable $exception) {
            $this->recordStockCallFailure('add_stock', $orderId, $exception->getMessage() . ' Sellasist otrzymal HTTP 200, blad zapisany w CRM.', 200);
        }
    }

    private function acknowledgeSellasistWebhook(string $operation, int $orderId): void
    {
        ignore_user_abort(true);

        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Connection: close');
        }

        echo 'OK ' . $operation . ' order_id=' . $orderId;

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            return;
        }

        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
        }

        flush();
    }

    private function renderTemplateOnly(string $template, array $data = array()): void
    {
        $smarty = SmartyFactory::create();

        foreach ($data as $key => $value) {
            $smarty->assign($key, $value);
        }

        $smarty->display($template . '.tpl');
    }

    private function recordStockCallFailure(string $operation, ?int $orderId, string $message, ?int $responseStatus = null): void
    {
        try {
            $this->stockCallFailures->record($operation, $orderId, $message, $responseStatus);
        } catch (Throwable $exception) {
            if (function_exists('app_log')) {
                app_log('Nie udalo sie zapisac bledu wywolania Sellasist: ' . $exception->getMessage(), 'ERROR');
            }
        }
    }

    private function hasSellasistStockAccess(): bool
    {
        $user = $this->currentUser();
        if (is_array($user)) {
            $modules = isset($user['modules']) && is_array($user['modules']) ? $user['modules'] : array();
            $permissionLevel = strtolower(trim((string) ($user['permission_level'] ?? 'edit')));
            if ($permissionLevel !== 'read' && ((string) ($user['role'] ?? '') === 'admin' || in_array('sellasist', $modules, true))) {
                return true;
            }
        }

        return true;
    }
}
