<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Models\ErliStorageRepository;
use App\Models\ProductRepository;
use RuntimeException;

class ErliService
{
    /** @var array */
    private $config;

    /** @var ErliStorageRepository */
    private $storage;

    /** @var ProductRepository */
    private $products;

    public function __construct()
    {
        $app = Config::get('app');
        $this->config = isset($app['erli']) && is_array($app['erli']) ? $app['erli'] : array();
        $database = Database::instance();
        $this->storage = new ErliStorageRepository($database);
        $this->storage->ensureSchema();
        $this->products = new ProductRepository($database);
        $this->products->ensureSchema();
    }

    public function listAccounts(): array
    {
        return $this->storage->allAccounts();
    }

    public function saveAccount(array $input, ?int $accountId = null): array
    {
        $existing = $accountId !== null ? $this->storage->findAccountById($accountId) : null;
        if ($accountId !== null && !$existing) {
            throw new RuntimeException('Nie znaleziono konta Erli do edycji.');
        }

        $name = trim((string) ($input['name'] ?? ''));
        $apiUrl = rtrim(trim((string) ($input['api_url'] ?? '')), '/');
        $apiKey = trim((string) ($input['api_key'] ?? ''));
        $priceListTag = trim((string) ($input['default_price_list_tag'] ?? ''));
        $dispatchDays = max(1, (int) ($input['default_dispatch_days'] ?? 1));
        $defaultWeight = trim((string) ($input['default_weight_g'] ?? ''));
        $isActive = !empty($input['is_active']) ? 1 : 0;

        if ($existing) {
            $name = $name !== '' ? $name : (string) ($existing['name'] ?? '');
            $apiUrl = $apiUrl !== '' ? $apiUrl : rtrim((string) ($existing['api_url'] ?? ''), '/');
            $apiKey = $apiKey !== '' ? $apiKey : (string) ($existing['api_key'] ?? '');
        }

        if ($name === '' || $apiUrl === '' || $apiKey === '') {
            throw new RuntimeException('Uzupelnij nazwe konta, adres API i API key Erli.');
        }

        if (filter_var($apiUrl, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Adres API Erli musi byc poprawnym URL.');
        }

        $payload = array(
            'name' => $name,
            'slug' => $this->uniqueSlug($name, $accountId),
            'api_url' => $apiUrl,
            'api_key' => $apiKey,
            'default_price_list_tag' => ($priceListTag !== '' ? $priceListTag : null),
            'default_dispatch_days' => $dispatchDays,
            'default_weight_g' => (preg_match('/^\d+$/', $defaultWeight) === 1 ? (int) $defaultWeight : null),
            'is_active' => $isActive,
        );

        $id = $this->storage->saveAccount($payload, $accountId);
        return (array) $this->storage->findAccountById($id);
    }

    public function productsPage(array $filters, int $page, int $perPage, string $sortBy = 'synced', string $sortDir = 'desc'): array
    {
        return $this->storage->listProducts($filters, $page, $perPage, $sortBy, $sortDir);
    }

    public function countProducts(array $filters): int
    {
        return $this->storage->countProducts($filters);
    }

    public function productStats(): array
    {
        return $this->storage->productStats();
    }

    public function productDetails(int $id)
    {
        return $this->storage->findProductRowById($id);
    }

    public function queueCounts(): array
    {
        return $this->storage->queueCounts();
    }

    public function enqueueProductChanges(array $filters, string $operation, array $payload = array(), array $selectedProductIds = array()): array
    {
        $operation = $this->normalizeQueueOperation($operation);
        if ($operation === '') {
            throw new RuntimeException('Wybierz poprawna operacje dla Erli.');
        }

        if ($selectedProductIds !== array()) {
            $targets = $this->storage->listProductsByIds($selectedProductIds);
        } else {
            $selectionLimit = isset($payload['selection_limit']) ? max(1, min(5000, (int) $payload['selection_limit'])) : 1000;
            $targets = $this->storage->productTargetsForFilters($filters, $selectionLimit);
        }

        if ($targets === array()) {
            throw new RuntimeException('Brak produktow Erli do przetworzenia dla wybranego zakresu.');
        }

        if ($operation === 'clear_queue') {
            $removed = $this->storage->clearQueueForProducts(array_map(static function (array $row): int {
                return (int) ($row['id'] ?? 0);
            }, $targets));

            return array(
                'operation' => $operation,
                'removed' => $removed,
                'products' => count($targets),
            );
        }

        if ($operation === 'remove_from_system') {
            $removed = $this->storage->removeProductsByIds(array_map(static function (array $row): int {
                return (int) ($row['id'] ?? 0);
            }, $targets));

            return array(
                'operation' => $operation,
                'removed' => $removed,
                'products' => count($targets),
            );
        }

        $normalizedPayload = $this->normalizeQueuePayload($operation, $payload);
        $queued = $this->storage->enqueueProductChanges($targets, $operation, $normalizedPayload);

        return array(
            'operation' => $operation,
            'queued' => $queued,
            'products' => count($targets),
        );
    }

    public function enqueueWarehouseUpdates(string $accountSelector = '', array $operations = array(), int $limit = 500): array
    {
        $operations = $operations !== array() ? $operations : array('set_price_from_product', 'set_stock_from_product');
        $filters = array(
            'linked' => '1',
            'status' => 'active',
        );

        if (trim($accountSelector) !== '') {
            $account = $this->resolveAccount($accountSelector);
            if (!$account) {
                throw new RuntimeException('Brak aktywnego konta Erli do dodania aktualizacji.');
            }
            $filters['account_id'] = (string) ((int) $account['id']);
        }

        $targets = $this->storage->productTargetsForFilters($filters, max(1, min(5000, $limit)));
        $result = array(
            'products' => count($targets),
            'operations' => array(),
            'queued' => 0,
        );

        foreach ($operations as $operation) {
            $operation = $this->normalizeQueueOperation((string) $operation);
            if ($operation === '') {
                continue;
            }

            $queued = $this->storage->enqueueProductChanges($targets, $operation, array(), null, true);
            $result['operations'][$operation] = $queued;
            $result['queued'] += $queued;
        }

        return $result;
    }

    public function processQueue(array $options = array()): array
    {
        $limit = max(1, min(100, (int) ($options['limit'] ?? 20)));
        $accountSelector = trim((string) ($options['account'] ?? ''));
        $accountId = null;

        if ($accountSelector !== '') {
            $account = $this->resolveAccount($accountSelector);
            if ($account) {
                $accountId = (int) $account['id'];
            }
        }

        $rows = $this->storage->fetchQueueBatch($limit, $accountId);
        $summary = array(
            'processed' => 0,
            'done' => 0,
            'error' => 0,
            'retry' => 0,
            'counts' => array(),
        );

        foreach ($rows as $row) {
            $queueId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($queueId <= 0) {
                continue;
            }

            $summary['processed']++;
            $this->storage->markQueueProcessing($queueId);

            try {
                $product = $this->storage->findProductRowById((int) ($row['product_row_id'] ?? 0));
                if (!$product) {
                    throw new RuntimeException('Nie znaleziono produktu Erli do przetworzenia.');
                }

                $operation = (string) ($row['operation'] ?? '');
                $payload = is_array($row['payload'] ?? null) ? $row['payload'] : array();
                $this->executeQueueOperation($product, $operation, $payload);
                $this->storage->markQueueDone($queueId);
                $summary['done']++;
            } catch (RuntimeException $exception) {
                $attempts = isset($row['attempts']) ? (int) $row['attempts'] : 0;
                if ($attempts < 1) {
                    $this->storage->markQueueRetry($queueId, $exception->getMessage(), 90);
                    $summary['retry']++;
                } else {
                    $this->storage->markQueueError($queueId, $exception->getMessage());
                    $summary['error']++;
                }
            }
        }

        $summary['counts'] = $this->storage->queueCounts();
        return $summary;
    }

    public function clearWholeQueue(): array
    {
        return $this->storage->clearWholeQueue();
    }

    public function clearQueueStatuses(bool $keepPending = true): array
    {
        return $this->storage->clearQueueStatuses($keepPending);
    }

    public function automationLinks(string $baseUrl): array
    {
        $base = rtrim($baseUrl, '?&');
        $links = array(
            'queue_worker' => $base . '?controller=erli&action=processqueue&format=json&limit=50',
            'sync_worker' => $base . '?controller=erli&action=maintenance&format=json&sync=1&max_batches=2&page_limit=100',
            'maintenance' => $base . '?controller=erli&action=maintenance&format=json&sync=1&max_batches=2&page_limit=100',
            'accounts' => array(),
        );

        foreach ($this->listAccounts() as $account) {
            $accountSlug = rawurlencode((string) ($account['slug'] ?? ''));
            $links['accounts'][] = array(
                'id' => (int) ($account['id'] ?? 0),
                'name' => (string) ($account['name'] ?? ''),
                'slug' => (string) ($account['slug'] ?? ''),
                'is_active' => (int) ($account['is_active'] ?? 0) === 1,
                'sync' => $base . '?controller=erli&action=sync&format=json&account=' . $accountSlug . '&max_batches=2&page_limit=100',
                'queue_only' => $base . '?controller=erli&action=processqueue&format=json&account=' . $accountSlug . '&limit=50',
                'maintenance' => $base . '?controller=erli&action=maintenance&format=json&account=' . $accountSlug . '&sync=1&max_batches=2&page_limit=100',
            );
        }

        return $links;
    }

    public function syncAccount(string $accountSelector = ''): array
    {
        return $this->syncAccountBatch($accountSelector);
    }

    public function syncAccountBatch(string $accountSelector = '', array $options = array()): array
    {
        $account = $this->resolveAccount($accountSelector);
        if (!$account) {
            throw new RuntimeException('Brak aktywnego konta Erli do synchronizacji.');
        }

        $accountId = (int) $account['id'];
        $maxBatches = isset($options['max_batches']) ? (int) $options['max_batches'] : 5;
        $pageLimit = isset($options['page_limit']) ? (int) $options['page_limit'] : 100;
        $maxBatches = max(1, min(20, $maxBatches));
        $pageLimit = max(1, min(200, $pageLimit));
        $synced = 0;
        $pagesProcessed = 0;
        $currentCycle = trim((string) ($account['current_cycle'] ?? ''));
        $afterExternalId = trim((string) ($account['sync_after_external_id'] ?? ''));
        $cycle = $currentCycle !== '' ? $currentCycle : $this->uuidV4();
        $after = $afterExternalId !== '' ? $afterExternalId : null;
        $finishedCycle = false;
        try {
            $this->storage->markAccountSyncStarted($accountId, $cycle, $after);

            for ($batch = 0; $batch < $maxBatches; $batch++) {
                $items = $this->fetchRemoteProductBatch($account, $after, $pageLimit);
                if ($items === array()) {
                    $finishedCycle = true;
                    break;
                }

                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $externalId = trim((string) ($item['externalId'] ?? ''));
                    if ($externalId === '') {
                        continue;
                    }

                    $after = $externalId;
                    $this->storage->upsertRemoteProductSnapshot($accountId, $item, $cycle);
                    $synced++;
                }

                $pagesProcessed++;
                $this->storage->markAccountSyncProgress($accountId, $cycle, $after);

                if (count($items) < $pageLimit) {
                    $finishedCycle = true;
                    break;
                }
            }

            if ($finishedCycle) {
                $this->storage->deleteSnapshotsOutsideCycle($accountId, $cycle);
                $this->storage->markAccountSyncSuccess($accountId);
            } else {
                $this->storage->markAccountSyncPaused($accountId, $cycle, $after);
            }
        } catch (RuntimeException $exception) {
            $this->storage->markAccountSyncError($accountId, $exception->getMessage());
            throw $exception;
        }

        return array(
            'account' => array(
                'id' => $accountId,
                'name' => (string) $account['name'],
                'slug' => (string) $account['slug'],
            ),
            'synced_products' => $synced,
            'pages_processed' => $pagesProcessed,
            'finished_cycle' => $finishedCycle,
            'next_after' => $finishedCycle ? null : $after,
        );
    }

    private function normalizeQueueOperation(string $operation): string
    {
        $operation = trim($operation);
        $allowed = array(
            'set_price',
            'set_price_from_product',
            'set_title',
            'set_title_from_product',
            'replace_title',
            'set_description',
            'replace_description',
            'set_stock_from_product',
            'activate_product',
            'deactivate_product',
            'sync_product',
            'clear_queue',
            'remove_from_system',
        );

        return in_array($operation, $allowed, true) ? $operation : '';
    }

    private function normalizeQueuePayload(string $operation, array $payload): array
    {
        switch ($operation) {
            case 'set_price':
                $value = str_replace(',', '.', trim((string) ($payload['value'] ?? '')));
                if ($value === '' || !is_numeric($value)) {
                    throw new RuntimeException('Cena Erli musi byc liczba.');
                }

                return array('value' => round((float) $value, 2));
            case 'set_title':
                $value = trim((string) ($payload['value'] ?? ''));
                if ($value === '') {
                    throw new RuntimeException('Podaj tytul dla Erli.');
                }

                return array('value' => $value);
            case 'replace_title':
            case 'replace_description':
                $search = trim((string) ($payload['search'] ?? ''));
                if ($search === '') {
                    throw new RuntimeException('Podaj szukany fragment do podmiany.');
                }

                return array(
                    'search' => $search,
                    'replace' => (string) ($payload['replace'] ?? ''),
                );
            case 'set_description':
                $value = trim((string) ($payload['value'] ?? ''));
                if ($value === '') {
                    throw new RuntimeException('Podaj opis dla Erli.');
                }

                return array('value' => $value);
        }

        return array();
    }

    private function executeQueueOperation(array $product, string $operation, array $payload): void
    {
        switch ($operation) {
            case 'set_price':
                $this->storage->updateProductOverrides((int) $product['id'], array('price_override' => round((float) ($payload['value'] ?? 0), 2)));
                break;
            case 'set_price_from_product':
                $warehouseRow = $this->fetchWarehouseRowForProduct($product);
                $this->storage->updateProductOverrides((int) $product['id'], array(
                    'price_override' => round((float) ($warehouseRow['price_gross'] ?? 0), 2),
                ));
                break;
            case 'set_title':
                $this->storage->updateProductOverrides((int) $product['id'], array('title_override' => (string) ($payload['value'] ?? '')));
                break;
            case 'set_title_from_product':
                $warehouseRow = $this->fetchWarehouseRowForProduct($product);
                $this->storage->updateProductOverrides((int) $product['id'], array(
                    'title_override' => trim((string) ($warehouseRow['product_name'] ?? '')),
                ));
                break;
            case 'replace_title':
                $currentTitle = trim((string) ($product['effective_title'] ?? ''));
                $search = (string) ($payload['search'] ?? '');
                if ($currentTitle !== '' && $search !== '') {
                    $this->storage->updateProductOverrides((int) $product['id'], array(
                        'title_override' => str_replace($search, (string) ($payload['replace'] ?? ''), $currentTitle),
                    ));
                }
                break;
            case 'set_description':
                $this->storage->updateProductOverrides((int) $product['id'], array('description_override' => (string) ($payload['value'] ?? '')));
                break;
            case 'replace_description':
                $currentDescription = (string) ($product['effective_description'] ?? '');
                $search = (string) ($payload['search'] ?? '');
                if ($currentDescription !== '' && $search !== '') {
                    $this->storage->updateProductOverrides((int) $product['id'], array(
                        'description_override' => str_replace($search, (string) ($payload['replace'] ?? ''), $currentDescription),
                    ));
                }
                break;
            case 'activate_product':
                $this->storage->updateProductOverrides((int) $product['id'], array('status_override' => 'active'));
                break;
            case 'deactivate_product':
                $this->storage->updateProductOverrides((int) $product['id'], array('status_override' => 'inactive'));
                break;
            case 'set_stock_from_product':
                $warehouseRow = $this->fetchWarehouseRowForProduct($product);
                $this->storage->updateProductOverrides((int) $product['id'], array(
                    'stock_override' => max(0, (int) ($warehouseRow['quantity'] ?? 0)),
                ));
                break;
            case 'sync_product':
                break;
            default:
                throw new RuntimeException('Nieobslugiwana operacja Erli.');
        }

        $fresh = $this->storage->findProductRowById((int) $product['id']);
        if (!$fresh) {
            throw new RuntimeException('Produkt Erli zniknal przed synchronizacja.');
        }

        $this->syncRemoteProduct($fresh, $operation);
    }

    private function fetchWarehouseRowForProduct(array $product): array
    {
        $warehouseProductId = isset($product['warehouse_product_id']) ? (int) $product['warehouse_product_id'] : 0;
        if ($warehouseProductId <= 0) {
            throw new RuntimeException('Brak powiazania produktu Erli z magazynem po SKU.');
        }

        $rows = $this->products->exportRows(array($warehouseProductId), 1);
        if ($rows === array() || !is_array($rows[0])) {
            throw new RuntimeException('Nie znaleziono produktu magazynowego dla SKU powiazanego z Erli.');
        }

        return $rows[0];
    }

    private function syncRemoteProduct(array $product, string $operation): void
    {
        $account = $this->resolveAccount((string) ($product['account_id'] ?? ''));
        if (!$account) {
            throw new RuntimeException('Brak konta Erli dla produktu.');
        }

        $externalId = trim((string) ($product['external_id'] ?? ''));
        if ($externalId === '') {
            throw new RuntimeException('Brak externalId produktu Erli.');
        }

        $payload = $this->buildRemoteProductPayload($product, $operation);
        if ($payload === array()) {
            return;
        }

        try {
            $this->requestApi($account, 'PATCH', '/products/' . rawurlencode($externalId), array(), $payload);
            $effectiveStatus = strtolower(trim((string) ($product['effective_status'] ?? 'inactive')));
            $this->storage->markProductSyncSuccess((int) $product['id'], $payload, $effectiveStatus, true);
        } catch (RuntimeException $exception) {
            $this->storage->markProductSyncError((int) $product['id'], $exception->getMessage());
            throw $exception;
        }
    }

    private function buildRemoteProductPayload(array $product, string $operation): array
    {
        $title = trim((string) ($product['effective_title'] ?? $product['product_name'] ?? ''));
        $description = (string) ($product['effective_description'] ?? $product['description'] ?? '');
        $status = strtolower(trim((string) ($product['effective_status'] ?? 'inactive')));
        $price = round((float) ($product['effective_price'] ?? 0), 2);
        $priceInGrosze = (int) round($price * 100);
        $stock = max(0, (int) ($product['effective_quantity'] ?? $product['quantity'] ?? 0));

        switch ($operation) {
            case 'set_price':
            case 'set_price_from_product':
                return array('price' => $priceInGrosze);
            case 'set_title':
            case 'set_title_from_product':
            case 'replace_title':
                return array('name' => $title !== '' ? $title : trim((string) ($product['external_id'] ?? '')));
            case 'set_description':
            case 'replace_description':
                return $description !== '' ? array('description' => $this->normalizeDescriptionPayload($description)) : array();
            case 'set_stock_from_product':
                return array('stock' => $stock);
            case 'activate_product':
            case 'deactivate_product':
                return array('status' => in_array($status, array('active', 'inactive'), true) ? $status : 'inactive');
            case 'sync_product':
                $payload = array(
                    'name' => $title !== '' ? $title : trim((string) ($product['external_id'] ?? '')),
                    'price' => $priceInGrosze,
                    'stock' => $stock,
                    'status' => in_array($status, array('active', 'inactive'), true) ? $status : 'inactive',
                );

                if ($description !== '') {
                    $payload['description'] = $this->normalizeDescriptionPayload($description);
                }

                return $payload;
        }

        return array();
    }

    private function normalizeDescriptionPayload(string $description): array
    {
        return array(
            'sections' => array(
                array(
                    'items' => array(
                        array(
                            'type' => 'TEXT',
                            'content' => trim($description),
                        ),
                    ),
                ),
            ),
        );
    }

    private function fetchRemoteProductBatch(array $account, ?string $after, int $pageLimit): array
    {
        $body = array(
            'pagination' => array(
                'limit' => max(1, min(100, $pageLimit)),
                'order' => 'ASC',
                'sortField' => 'externalId',
            ),
            'filter' => array(
                'field' => 'archived',
                'operator' => '=',
                'value' => false,
            ),
            'fields' => array(
                'externalId',
                'sku',
                'name',
                'price',
            ),
        );

        if ($after !== null && trim($after) !== '') {
            $body['pagination']['after'] = trim($after);
        }

        $response = $this->requestApi($account, 'POST', '/products/_search', array(), $body);
        return $this->extractRemoteProductItems($response);
    }

    private function extractRemoteProductItems(array $response): array
    {
        if (isset($response['items']) && is_array($response['items'])) {
            return $response['items'];
        }

        if ($this->isListArray($response)) {
            return $response;
        }

        return array();
    }

    private function isListArray(array $items): bool
    {
        $expectedKey = 0;

        foreach ($items as $key => $value) {
            if ($key !== $expectedKey) {
                return false;
            }

            $expectedKey++;
        }

        return true;
    }

    private function requestApi(array $account, string $method, string $path, array $query = array(), $body = null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Brak rozszerzenia cURL potrzebnego do integracji Erli.');
        }

        $baseUrl = rtrim((string) ($account['api_url'] ?? ''), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('Brak adresu API Erli.');
        }

        $url = $baseUrl . $path;
        if ($query !== array()) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Nie udalo sie zainicjalizowac polaczenia z Erli API.');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Authorization: Bearer ' . (string) ($account['api_key'] ?? ''),
            'User-Agent: ALTREO-Erli/1.0',
            'Content-Type: application/json',
        ));

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (!is_string($raw)) {
            throw new RuntimeException('Erli API nie zwrocilo odpowiedzi.');
        }

        $decoded = $raw !== '' ? json_decode($raw, true) : array();
        if ($httpCode < 200 || $httpCode >= 300) {
            $message = $this->extractApiError($decoded);
            if ($message === '' && $raw !== '' && !is_array($decoded)) {
                $message = trim($raw);
            }

            if ($message === '' && $curlError !== '') {
                $message = $curlError;
            }

            throw new RuntimeException('Erli API zwrocilo blad HTTP ' . $httpCode . ($message !== '' ? ': ' . $message : '.'));
        }

        return is_array($decoded) ? $decoded : array();
    }

    private function extractApiError($decoded): string
    {
        if (!is_array($decoded)) {
            return '';
        }

        foreach (array('message', 'error_message', 'error', 'description') as $key) {
            if (!empty($decoded[$key]) && is_scalar($decoded[$key])) {
                return trim((string) $decoded[$key]);
            }
        }

        if (isset($decoded['errors']) && is_array($decoded['errors'])) {
            foreach ($decoded['errors'] as $error) {
                if (is_array($error)) {
                    foreach (array('message', 'error_message', 'description') as $key) {
                        if (!empty($error[$key]) && is_scalar($error[$key])) {
                            return trim((string) $error[$key]);
                        }
                    }
                } elseif (is_scalar($error)) {
                    return trim((string) $error);
                }
            }
        }

        return '';
    }

    private function resolveAccount(string $selector = '')
    {
        $selector = trim($selector);
        if ($selector !== '') {
            if (ctype_digit($selector)) {
                $account = $this->storage->findAccountById((int) $selector);
                if ($account) {
                    return $account;
                }
            }

            $account = $this->storage->findAccountBySlug($selector);
            if ($account) {
                return $account;
            }
        }

        $active = $this->storage->activeAccounts();
        return $active !== array() ? $active[0] : null;
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
        if ($base === '') {
            $base = 'erli';
        }

        $slug = $base;
        $suffix = 2;

        while (true) {
            $existing = $this->storage->findAccountBySlug($slug);
            if (!$existing || ($ignoreId !== null && (int) $existing['id'] === $ignoreId)) {
                return $slug;
            }

            $slug = $base . '-' . $suffix;
            $suffix++;
        }
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
