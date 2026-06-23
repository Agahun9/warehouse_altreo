<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\ErliService;
use Throwable;

class ErliController extends Controller
{
    /** @var ErliService */
    private $erli;

    public function __construct()
    {
        $this->erli = new ErliService();
    }

    public function index(): void
    {
        $currentUser = $this->requireModule('erli');
        $flashSuccess = $this->getFlash('success');
        $flashError = $this->getFlash('error');
        $this->releaseSessionLock();

        $filters = $this->offerFilters();
        $page = max(1, (int) $this->input('page', 1));
        $allowedPerPage = array(50, 100, 200, 5000, 10000);
        $perPageInput = (int) $this->input('per_page', 50);
        $perPage = in_array($perPageInput, $allowedPerPage, true) ? $perPageInput : 50;
        $sortBy = trim((string) $this->input('sort_by', 'id'));
        $sortDir = strtolower(trim((string) $this->input('sort_dir', 'desc')));
        if ($sortDir !== 'asc' && $sortDir !== 'desc') {
            $sortDir = 'desc';
        }

        $total = $this->erli->countProducts($filters);
        $products = $this->erli->productsPage($filters, $page, $perPage, $sortBy, $sortDir);
        $totalPages = max(1, (int) ceil($total / max(1, $perPage)));

        if ($page > $totalPages) {
            $page = $totalPages;
            $products = $this->erli->productsPage($filters, $page, $perPage, $sortBy, $sortDir);
        }

        $pageWindow = $this->buildPageWindow($page, $totalPages);
        $sortableColumns = array('images', 'account', 'title', 'sku', 'category', 'status', 'quantity', 'warehouse_quantity', 'price', 'queue_status', 'synced', 'updated', 'id');
        $sortIndicators = array();
        $sortUrls = array();

        foreach ($sortableColumns as $column) {
            $sortIndicators[$column] = ($sortBy === $column) ? $sortDir : '';
            $sortUrls[$column] = $this->buildOfferIndexUrl($filters, $column, $this->nextSortDirection($column, $sortBy, $sortDir), 1, $perPage);
        }

        $currentListUrl = $this->buildOfferIndexUrl($filters, $sortBy, $sortDir, $page, $perPage);

        $this->render('erli/index', array(
            'pageTitle' => 'Erli',
            'contentTitle' => 'Integracja Erli',
            'pageDescription' => 'Erli Marketplace API: import produktow z Erli, kolejka zmian i masowe akcje jak w Allegro.',
            'breadcrumbCurrent' => 'Erli',
            'currentUser' => $currentUser,
            'flashSuccess' => $flashSuccess,
            'flashError' => $flashError,
            'accounts' => $this->erli->listAccounts(),
            'products' => $products,
            'filters' => $filters,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'sortIndicators' => $sortIndicators,
            'sortUrls' => $sortUrls,
            'stats' => $this->erli->productStats(),
            'queueStats' => $this->erli->queueCounts(),
            'page' => $page,
            'prevPage' => max(1, $page - 1),
            'nextPage' => min($totalPages, $page + 1),
            'perPage' => $perPage,
            'totalProducts' => $total,
            'totalPages' => $totalPages,
            'pageWindow' => $pageWindow,
            'currentListUrl' => $currentListUrl,
        ));
    }

    public function saveaccount(): void
    {
        $this->requireRole('admin');
        $this->requireWriteAccess();

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=erli&action=index');
        }

        try {
            $accountId = (int) $this->input('account_id', 0);
            $this->erli->saveAccount(array(
                'name' => $this->input('name', ''),
                'api_url' => $this->input('api_url', ''),
                'api_key' => $this->input('api_key', ''),
                'default_price_list_tag' => $this->input('default_price_list_tag', ''),
                'default_dispatch_days' => $this->input('default_dispatch_days', '1'),
                'default_weight_g' => $this->input('default_weight_g', ''),
                'is_active' => $this->input('is_active', '0') === '1' ? 1 : 0,
            ), $accountId > 0 ? $accountId : null);

            $this->setFlash('success', 'Konto Erli zostalo zapisane.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=erli&action=index');
    }

    public function sync(): void
    {
        if (!$this->wantsJson()) {
            $this->requireModuleWrite('erli');
        }
        $this->releaseSessionLock();

        try {
            $result = $this->erli->syncAccountBatch(trim((string) $this->input('account', '')), array(
                'max_batches' => (int) $this->input('max_batches', 5),
                'page_limit' => (int) $this->input('page_limit', 50),
            ));
            if ($this->wantsJson()) {
                $this->jsonResponse($result);
                return;
            }

            if (!empty($result['finished_cycle'])) {
                $this->setFlash(
                    'success',
                    'Synchronizacja Erli zakonczona dla konta "' . (string) $result['account']['name'] . '". Zaktualizowano ' . (int) $result['synced_products'] . ' produktow w ' . (int) ($result['pages_processed'] ?? 0) . ' batchach.'
                );
            } else {
                $this->setFlash(
                    'success',
                    'Synchronizacja Erli zapisala kolejny batch dla konta "' . (string) $result['account']['name'] . '". Zaktualizowano ' . (int) $result['synced_products'] . ' produktow, batchy: ' . (int) ($result['pages_processed'] ?? 0) . '. Nastepne wywolanie ruszy od kolejnego offsetu.'
                );
            }
        } catch (Throwable $exception) {
            if ($this->wantsJson()) {
                $this->jsonResponse(array('error' => $exception->getMessage()), 500);
                return;
            }
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=erli&action=index');
    }

    public function maintenance(): void
    {
        if (!$this->wantsJson()) {
            $this->requireModuleWrite('erli');
        }
        $this->releaseSessionLock();

        try {
            $accountSelector = trim((string) $this->input('account', ''));
            $result = array(
                'queue' => null,
                'sync' => array(),
                'enqueue' => null,
            );

            if ($this->input('sync', '0') === '1') {
                $syncOptions = array(
                    'max_batches' => (int) $this->input('max_batches', 2),
                    'page_limit' => (int) $this->input('page_limit', 50),
                );

                if ($accountSelector !== '') {
                    $result['sync'][] = $this->erli->syncAccountBatch($accountSelector, $syncOptions);
                } else {
                    $maxAccounts = max(1, min(50, (int) $this->input('max_accounts', 50)));
                    $processedAccounts = 0;
                    foreach ($this->erli->listAccounts() as $account) {
                        if ((int) ($account['is_active'] ?? 0) !== 1) {
                            continue;
                        }
                        if ($processedAccounts >= $maxAccounts) {
                            break;
                        }

                        $result['sync'][] = $this->erli->syncAccountBatch((string) ($account['slug'] ?? ''), $syncOptions);
                        $processedAccounts++;
                    }
                }
            }

            $enqueueOperations = $this->input('enqueue', '');
            if (trim((string) $enqueueOperations) !== '') {
                $operations = array_values(array_filter(array_map('trim', explode(',', (string) $enqueueOperations))));
                $result['enqueue'] = $this->erli->enqueueWarehouseUpdates(
                    $accountSelector,
                    $operations,
                    (int) $this->input('enqueue_limit', 500)
                );
            }

            $queueLimit = (int) $this->input('queue_limit', 50);
            if ($queueLimit > 0) {
                $result['queue'] = $this->erli->processQueue(array(
                    'account' => $accountSelector,
                    'limit' => $queueLimit,
                ));
            }

            if ($this->wantsJson()) {
                $this->jsonResponse($result);
                return;
            }

            $this->setFlash('success', 'Maintenance Erli zakonczone. Sync kont: ' . count($result['sync']) . '.');
        } catch (Throwable $exception) {
            if ($this->wantsJson()) {
                $this->jsonResponse(array('error' => $exception->getMessage()), 500);
                return;
            }
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=erli&action=index');
    }

    public function queue(): void
    {
        $this->requireModuleWrite('erli');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=erli&action=index');
        }

        $this->releaseSessionLock();

        try {
            $selectionScope = trim((string) $this->input('selection_scope', 'filtered'));
            $selectedProductIds = $this->input('selected_product_ids', array());
            $selectedIds = array();

            if ($selectionScope === 'selected' && is_array($selectedProductIds)) {
                $selectedIds = array_values(array_filter(array_map('intval', $selectedProductIds)));
                if ($selectedIds === array()) {
                    throw new \RuntimeException('Zaznacz przynajmniej jeden produkt Erli albo wybierz zakres "Biezacy filtr".');
                }
            }

            $result = $this->erli->enqueueProductChanges(
                $this->offerFilters(),
                trim((string) $this->input('operation', '')),
                array(
                    'value' => $this->input('value', ''),
                    'search' => $this->input('search', ''),
                    'replace' => $this->input('replace', ''),
                    'selection_limit' => (int) $this->input('selection_limit', 1000),
                ),
                $selectedIds
            );

            if ((string) ($result['operation'] ?? '') === 'clear_queue') {
                $this->setFlash('success', 'Usunieto z kolejki: ' . (int) ($result['removed'] ?? 0) . ' wpisow.');
            } elseif ((string) ($result['operation'] ?? '') === 'remove_from_system') {
                $this->setFlash('success', 'Usunieto lokalnie z systemu: ' . (int) ($result['removed'] ?? 0) . ' produktow.');
            } else {
                $this->setFlash('success', 'Dodano do kolejki Erli: ' . (int) ($result['queued'] ?? 0) . ' produktow.');
            }
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $returnUrl = trim((string) $this->input('return_url', ''));
        if ($returnUrl !== '' && strpos($returnUrl, './index.php?') === 0) {
            $this->redirect($returnUrl);
        }

        $this->redirect('./index.php?' . http_build_query(array_merge(
            array('controller' => 'erli', 'action' => 'index'),
            $this->offerFilters()
        )));
    }

    public function processqueue(): void
    {
        if (!$this->wantsJson()) {
            $this->requireModuleWrite('erli');
        }
        $this->releaseSessionLock();

        try {
            $result = $this->erli->processQueue(array(
                'account' => $this->input('account', ''),
                'limit' => (int) $this->input('limit', 20),
            ));

            if ($this->wantsJson()) {
                $this->jsonResponse($result);
                return;
            }

            $this->setFlash('success', 'Kolejka Erli przetworzona. OK: ' . (int) ($result['done'] ?? 0));
        } catch (Throwable $exception) {
            if ($this->wantsJson()) {
                $this->jsonResponse(array('error' => $exception->getMessage()), 500);
                return;
            }
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=erli&action=index');
    }

    public function clearqueue(): void
    {
        $this->requireModuleWrite('erli');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=erli&action=index');
        }

        try {
            $mode = trim((string) $this->input('mode', 'statuses'));
            if ($mode === 'all') {
                $result = $this->erli->clearWholeQueue();
                $this->setFlash('success', 'Usunieto cala kolejke Erli: ' . (int) ($result['removed'] ?? 0) . ' wpisow.');
            } else {
                $result = $this->erli->clearQueueStatuses(true);
                $this->setFlash('success', 'Wyczyszczono statusy zakonczonych pozycji kolejki Erli: ' . (int) ($result['removed'] ?? 0) . ' wpisow.');
            }
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=erli&action=index');
    }

    public function product(): void
    {
        $this->requireModule('erli');

        $id = (int) $this->input('id', 0);
        $product = $this->erli->productDetails($id);
        if (!$product) {
            $this->setFlash('error', 'Nie znaleziono produktu Erli.');
            $this->redirect('./index.php?controller=erli&action=index');
        }

        $decoded = array();
        if (!empty($product['payload_json'])) {
            $decoded = json_decode((string) $product['payload_json'], true);
            if (!is_array($decoded)) {
                $decoded = array();
            }
        }

        $this->render('erli/product', array(
            'pageTitle' => 'Produkt Erli',
            'contentTitle' => (string) ($product['effective_title'] ?? ('Produkt #' . $product['id'])),
            'pageDescription' => 'Podglad lokalnego wpisu przygotowanego do synchronizacji z Erli.',
            'breadcrumbCurrent' => 'Produkt Erli',
            'product' => $product,
            'payloadData' => $decoded,
            'payloadJsonPretty' => json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ));
    }

    private function offerFilters(): array
    {
        return array(
            'account_id' => trim((string) $this->input('account_id', '')),
            'q' => trim((string) $this->input('q', '')),
            'sku' => trim((string) $this->input('sku', '')),
            'status' => trim((string) $this->input('status', '')),
            'queue_status' => trim((string) $this->input('queue_status', '')),
            'error_query' => trim((string) $this->input('error_query', '')),
            'linked' => trim((string) $this->input('linked', '')),
            'warehouse_quantity_from' => trim((string) $this->input('warehouse_quantity_from', '')),
            'warehouse_quantity_to' => trim((string) $this->input('warehouse_quantity_to', '')),
        );
    }

    private function buildOfferIndexUrl(array $filters, string $sortBy, string $sortDir, int $page = 1, int $perPage = 50): string
    {
        $query = array(
            'controller' => 'erli',
            'action' => 'index',
            'page' => $page,
            'per_page' => $perPage,
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
        );

        foreach ($filters as $key => $value) {
            $query[$key] = $value;
        }

        return './index.php?' . http_build_query($query);
    }

    private function nextSortDirection(string $column, string $sortBy, string $sortDir): string
    {
        if ($column !== $sortBy) {
            return 'desc';
        }

        return $sortDir === 'desc' ? 'asc' : 'desc';
    }

    private function buildPageWindow(int $page, int $totalPages): array
    {
        $items = array();
        $lastAdded = null;

        for ($current = 1; $current <= $totalPages; $current++) {
            $isEdge = $current <= 3 || $current > ($totalPages - 3);
            $isNearCurrent = $current >= ($page - 2) && $current <= ($page + 2);

            if (!$isEdge && !$isNearCurrent) {
                continue;
            }

            if ($lastAdded !== null && ($current - $lastAdded) > 1) {
                $items[] = array(
                    'type' => 'ellipsis',
                    'value' => '...',
                );
            }

            $items[] = array(
                'type' => 'page',
                'value' => $current,
                'is_current' => $current === $page,
            );
            $lastAdded = $current;
        }

        return $items;
    }

    private function wantsJson(): bool
    {
        return strtolower(trim((string) $this->input('format', ''))) === 'json';
    }

    private function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
    }
}
