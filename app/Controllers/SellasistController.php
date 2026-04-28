<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\SmartyFactory;
use App\Services\SellasistService;
use Throwable;

class SellasistController extends Controller
{
    /** @var SellasistService */
    private $sellasist;

    public function __construct()
    {
        $this->sellasist = new SellasistService($this->db());
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
        if (!$this->hasSellasistStockAccess()) {
            http_response_code(403);
            exit('Brak dostepu.');
        }

        $orderId = (int) $this->input('id', $this->input('order_id', 0));
        if ($orderId <= 0) {
            http_response_code(400);
            exit('Brak poprawnego ID zamowienia.');
        }

        try {
            $result = $this->sellasist->subtractStockForOrder($orderId);
            $this->renderTemplateOnly('sellasist/subtract_stock_result', array(
                'pageTitle' => 'Sellasist - odjecie stanu',
                'operationLabel' => 'Odjecie stanu',
                'quantityLabel' => 'Odjeto',
                'result' => $result,
            ));
        } catch (Throwable $exception) {
            http_response_code(500);
            echo 'Blad: ' . $exception->getMessage();
        }
    }

    public function addstock(): void
    {
        if (!$this->hasSellasistStockAccess()) {
            http_response_code(403);
            exit('Brak dostepu.');
        }

        $orderId = (int) $this->input('id', $this->input('order_id', 0));
        if ($orderId <= 0) {
            http_response_code(400);
            exit('Brak poprawnego ID zamowienia.');
        }

        try {
            $result = $this->sellasist->addStockForOrder($orderId);
            $this->renderTemplateOnly('sellasist/subtract_stock_result', array(
                'pageTitle' => 'Sellasist - dodanie stanu',
                'operationLabel' => 'Dodanie stanu',
                'quantityLabel' => 'Dodano',
                'result' => $result,
            ));
        } catch (Throwable $exception) {
            http_response_code(500);
            echo 'Blad: ' . $exception->getMessage();
        }
    }

    private function renderTemplateOnly(string $template, array $data = array()): void
    {
        $smarty = SmartyFactory::create();

        foreach ($data as $key => $value) {
            $smarty->assign($key, $value);
        }

        $smarty->display($template . '.tpl');
    }

    private function hasSellasistStockAccess(): bool
    {
        $user = $this->currentUser();
        if (!is_array($user)) {
            return false;
        }

        return $this->moduleAccessLevel($user, 'sellasist') === 'edit';
    }
}
