<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\SmartyFactory;
use App\Models\SellasistStockCallFailureRepository;
use App\Services\SellasistService;
use RuntimeException;
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
            'sellasistPickingStatusId' => (int) ($config['picking_status_id'] ?? SellasistService::PICKING_STATUS_ID),
            'sellasistPrintedStatusId' => (int) ($config['printed_status_id'] ?? SellasistService::PRINTED_STATUS_ID),
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

    public function computers_spec(): void
    {
        $orderId = (int) $this->input('id', 0);

        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        if ($orderId <= 0) {
            http_response_code(400);
            echo 'Brak poprawnego ID zamowienia.';
            return;
        }

        try {
            $order = $this->sellasist->getOrderById($orderId);
            $notes = $this->buildComputerSpecificationNotes($order);
            $this->sellasist->replaceFirstTwoOrderNotes($orderId, $notes['html'], $notes['plain_text']);
            echo 'OK';
        } catch (Throwable $exception) {
            http_response_code(502);
            echo $exception->getMessage();
        }
    }

    private function buildComputerSpecificationNotes(array $order): array
    {
        $items = array();
        foreach (array('carts', 'products', 'items', 'order_products') as $key) {
            if (isset($order[$key]) && is_array($order[$key])) {
                $items = $order[$key];
                break;
            }
        }

        $htmlSections = array();
        $plainTextSections = array();
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $sku = trim((string) ($item['signature'] ?? ''));
            if ($sku === '') {
                $sku = trim((string) ($item['symbol'] ?? ''));
            }
            if ($sku === '') {
                continue;
            }

            $product = $this->findComputerProductBySku($sku);
            if (!is_array($product)) {
                continue;
            }

            $components = $this->computerComponents((string) ($product['id_components'] ?? ''));
            $htmlSections[] = $this->computerSpecificationSection($product, $components, $sku);
            $plainTextSections[] = $this->computerPlainTextSpecification($components);
        }

        if ($htmlSections === array()) {
            throw new RuntimeException('Nie znaleziono komputera magazynowego dla SKU z zamówienia.');
        }

        return array(
            'html' => '<div style="font-family:Arial,sans-serif;color:#1f2937">'
                . '<div style="font-size:18px;font-weight:700;margin-bottom:12px">Specyfikacja zamówienia</div>'
                . implode('<div style="height:12px"></div>', $htmlSections)
                . '</div>',
            'plain_text' => implode("\n", $plainTextSections),
        );
    }

    private function findComputerProductBySku(string $sku)
    {
        return $this->db()->fetch(
            'SELECT id, id_components, sku, name, price FROM pr_products_altreo'
            . ' WHERE sku = :sku OR CONCAT("ALTREO_", id) = :derived_sku'
            . ' ORDER BY CASE WHEN sku = :exact_sku THEN 0 ELSE 1 END LIMIT 1',
            array(
                'sku' => $sku,
                'derived_sku' => $sku,
                'exact_sku' => $sku,
            )
        );
    }

    private function computerComponents(string $componentIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            'intval',
            preg_split('/\s*,\s*/', trim($componentIds)) ?: array()
        ))));
        if ($ids === array()) {
            return array();
        }

        $params = array();
        $placeholders = array();
        foreach ($ids as $index => $id) {
            $key = 'computer_spec_component_' . $index;
            $params[$key] = $id;
            $placeholders[] = ':' . $key;
        }

        $rows = $this->db()->fetchAll(
            'SELECT id, category, name, name_title, name_spec FROM pr_components_altreo'
            . ' WHERE id IN (' . implode(', ', $placeholders) . ')',
            $params
        );
        $byId = array();
        foreach ($rows as $row) {
            if (is_array($row)) {
                $byId[(int) ($row['id'] ?? 0)] = $row;
            }
        }

        $components = array();
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $components[] = $byId[$id];
            }
        }

        usort($components, function (array $left, array $right): int {
            $leftRank = $this->computerComponentSortRank((string) ($left['category'] ?? ''));
            $rightRank = $this->computerComponentSortRank((string) ($right['category'] ?? ''));
            if ($leftRank !== $rightRank) {
                return $leftRank <=> $rightRank;
            }

            $categoryCompare = strnatcasecmp(
                trim((string) ($left['category'] ?? '')),
                trim((string) ($right['category'] ?? ''))
            );
            if ($categoryCompare !== 0) {
                return $categoryCompare;
            }

            $leftName = trim((string) ($left['name_spec'] ?? ($left['name'] ?? '')));
            $rightName = trim((string) ($right['name_spec'] ?? ($right['name'] ?? '')));
            $nameCompare = strnatcasecmp($leftName, $rightName);
            if ($nameCompare !== 0) {
                return $nameCompare;
            }

            return ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
        });

        return $components;
    }

    private function computerComponentSortRank(string $category): int
    {
        $category = mb_strtoupper(trim($category), 'UTF-8');
        $aliases = array(
            'PŁYTA GŁÓWNA' => 'MB',
            'PLYTA GLOWNA' => 'MB',
            'ZASILACZ' => 'PSU',
        );
        $category = $aliases[$category] ?? $category;

        $priority = array(
            'CPU' => 10,
            'GPU' => 20,
            'RAM' => 30,
            'SSD' => 40,
            'MB' => 50,
            'PSU' => 60,
        );

        return $priority[$category] ?? 100;
    }

    private function computerSpecificationSection(array $product, array $components, string $sku): string
    {
        $rows = '';
        foreach ($components as $component) {
            $category = trim((string) ($component['category'] ?? ''));
            $specification = trim((string) ($component['name_spec'] ?? ''));
            if ($specification === '') {
                $specification = trim((string) ($component['name'] ?? ''));
            }
            if ($specification === '') {
                $specification = trim((string) ($component['name_title'] ?? ''));
            }
            if ($specification === '') {
                continue;
            }

            $rows .= '<tr>'
                . '<td style="padding:7px 10px;border-bottom:1px solid #e5e7eb;font-weight:700;width:28%;background:#f8fafc">'
                . $this->escapeNoteHtml($category !== '' ? $category : 'Komponent')
                . '</td>'
                . '<td style="padding:7px 10px;border-bottom:1px solid #e5e7eb">'
                . $this->escapeNoteHtml($specification)
                . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td style="padding:8px 10px;color:#6b7280">Brak przypisanych komponentów.</td></tr>';
        }

        $price = number_format((float) ($product['price'] ?? 0), 2, ',', ' ');
        $name = trim((string) ($product['name'] ?? 'Komputer'));

        return '<div style="border:1px solid #cbd5e1;border-radius:8px;overflow:hidden">'
            . '<div style="padding:10px 12px;background:#1d4ed8;color:#ffffff">'
            . '<div style="font-size:15px;font-weight:700">' . $this->escapeNoteHtml($name) . '</div>'
            . '<div style="font-size:12px;margin-top:3px">SKU: ' . $this->escapeNoteHtml($sku) . '</div>'
            . '</div>'
            . '<table style="width:100%;border-collapse:collapse;font-size:13px"><tbody>' . $rows . '</tbody></table>'
            . '<div style="padding:11px 12px;background:#ecfdf5;color:#065f46;font-size:15px">'
            . '<strong>Sugerowana cena magazynowa: ' . $price . ' zł</strong>'
            . '</div>'
            . '</div>';
    }

    private function computerPlainTextSpecification(array $components): string
    {
        $values = array();
        foreach ($components as $component) {
            $specification = trim((string) ($component['name_spec'] ?? ''));
            if ($specification === '') {
                $specification = trim((string) ($component['name'] ?? ''));
            }
            if ($specification === '') {
                $specification = trim((string) ($component['name_title'] ?? ''));
            }
            if (mb_strtoupper($specification, 'UTF-8') === 'BRAK') {
                $specification = 'SAM KOMPUTER';
            }
            if ($specification !== '') {
                $values[] = $specification;
            }
        }

        if ($values === array()) {
            return 'Specyfikacja';
        }

        return "Specyfikacja\n" . implode(" /\n", $values) . ' /';
    }

    private function escapeNoteHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
