<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\MoreleStorageRepository;
use App\Models\ProductRepository;
use App\Models\SettingRepository;
use RuntimeException;

class MoreleService
{
    private const DEFAULT_API_BASE = 'https://api-marketplace.morele.net';

    /** @var SettingRepository */
    private $settings;

    /** @var MoreleStorageRepository */
    private $storage;

    /** @var ProductRepository */
    private $products;

    public function __construct()
    {
        $database = Database::instance();
        $this->settings = new SettingRepository(Database::instance());
        $this->settings->ensureSchema();
        $this->storage = new MoreleStorageRepository($database);
        $this->storage->ensureSchema();
        $this->products = new ProductRepository($database);
        $this->products->ensureSchema();
    }

    public function accountLabel(): string
    {
        $label = trim((string) $this->settings->get('morele_account', ''));
        return $label !== '' ? $label : 'ALTREO';
    }

    public function listAccounts(): array
    {
        return array(array(
            'id' => 1,
            'name' => $this->accountLabel(),
            'slug' => 'morele',
            'is_active' => $this->hasCredentials() ? 1 : 0,
        ));
    }

    public function offersPage(array $filters, int $page, int $perPage, string $sortBy = 'synced', string $sortDir = 'desc'): array
    {
        return $this->storage->listOffers($filters, $page, $perPage, $sortBy, $sortDir);
    }

    public function countOffers(array $filters): int
    {
        return $this->storage->countOffers($filters);
    }

    public function offerStats(): array
    {
        return $this->storage->offerStats();
    }

    public function queueCounts(): array
    {
        return $this->storage->queueCounts();
    }

    public function offerDetails(int $id)
    {
        return $this->storage->findOfferById($id);
    }

    public function enqueueOfferChanges(array $filters, string $operation, array $payload = array(), array $selectedOfferIds = array()): array
    {
        $operation = $this->normalizeQueueOperation($operation);
        if ($operation === '') {
            throw new RuntimeException('Wybierz poprawna operacje dla Morele.');
        }

        if (!in_array($operation, array('set_price', 'set_price_from_product', 'clear_queue'), true)) {
            throw new RuntimeException('Dla Morele wlaczone sa teraz tylko akcje zmiany ceny.');
        }

        if ($operation === 'clear_queue') {
            $cleared = $this->storage->clearQueueStatuses(false);
            return array('operation' => $operation, 'removed' => (int) ($cleared['removed'] ?? 0), 'offers' => 0);
        }

        if ($selectedOfferIds !== array()) {
            $targets = $this->storage->listOffersByIds($selectedOfferIds);
        } else {
            $limit = isset($payload['selection_limit']) ? max(1, min(5000, (int) $payload['selection_limit'])) : 1000;
            $targets = $this->storage->offerTargetsForFilters($filters, $limit);
        }

        if ($targets === array()) {
            throw new RuntimeException('Brak ofert Morele do przetworzenia dla wybranego zakresu.');
        }

        $normalizedPayload = $this->normalizeQueuePayload($operation, $payload);
        $queued = $this->storage->enqueueOfferChanges($targets, $operation, $normalizedPayload);

        return array(
            'operation' => $operation,
            'queued' => $queued,
            'offers' => count($targets),
        );
    }

    public function enqueueWarehouseUpdates(array $operations = array(), int $limit = 500): array
    {
        $operations = $operations !== array() ? $operations : array('set_price_from_product');
        $targets = $this->storage->offerTargetsForFilters(array('status' => 'active'), max(1, min(5000, $limit)));
        $result = array('offers' => count($targets), 'operations' => array(), 'queued' => 0);

        foreach ($operations as $operation) {
            $operation = $this->normalizeQueueOperation((string) $operation);
            if ($operation === '') {
                continue;
            }

            $queued = $this->storage->enqueueOfferChanges($targets, $operation, array(), null, true);
            $result['operations'][$operation] = $queued;
            $result['queued'] += $queued;
        }

        return $result;
    }

    public function processQueue(array $options = array()): array
    {
        $limit = max(1, min(100, (int) ($options['limit'] ?? 20)));
        $rows = $this->storage->fetchQueueBatch($limit);
        $summary = array('processed' => 0, 'done' => 0, 'error' => 0, 'retry' => 0, 'counts' => array());

        foreach ($rows as $row) {
            $queueId = (int) ($row['id'] ?? 0);
            if ($queueId <= 0) {
                continue;
            }

            $summary['processed']++;
            $this->storage->markQueueProcessing($queueId);

            try {
                $offer = $this->storage->findOfferById((int) ($row['offer_row_id'] ?? 0));
                if (!$offer) {
                    throw new RuntimeException('Nie znaleziono oferty Morele do przetworzenia.');
                }

                $this->executeQueueOperation($offer, (string) ($row['operation'] ?? ''), is_array($row['payload'] ?? null) ? $row['payload'] : array());
                $this->storage->markQueueDone($queueId);
                $summary['done']++;
            } catch (RuntimeException $exception) {
                $attempts = (int) ($row['attempts'] ?? 0);
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
        return array(
            'queue_worker' => $base . '?controller=morele&action=processqueue&format=json&limit=50',
            'sync_worker' => $base . '?controller=morele&action=maintenance&format=json&sync=1&max_pages=0&page_limit=500',
            'maintenance' => $base . '?controller=morele&action=maintenance&format=json&sync=1&max_pages=0&page_limit=500',
        );
    }

    public function syncOffers(array $options = array()): array
    {
        $pageLimit = max(1, min(500, (int) ($options['page_limit'] ?? 100)));
        $requestedMaxPages = (int) ($options['max_pages'] ?? 0);
        $maxPages = $requestedMaxPages > 0 ? max(1, min(1000, $requestedMaxPages)) : 1000;
        $synced = 0;
        $skipped = 0;
        $pages = 0;
        $removedInvalid = $this->storage->removeInvalidWrapperSnapshots();
        $seenPageFingerprints = array();
        $debug = !empty($options['debug']);
        $pageDebug = array();
        $stopReason = '';

        for ($page = 1; $page <= $maxPages; $page++) {
            $items = $this->fetchRemoteOfferPage($page, $pageLimit);
            if ($items === array()) {
                $stopReason = 'empty_page_' . $page;
                if ($debug) {
                    $pageDebug[] = array('page' => $page, 'items' => 0, 'status' => 'empty');
                }
                break;
            }

            $pageFingerprint = sha1(json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            if (isset($seenPageFingerprints[$pageFingerprint])) {
                $stopReason = 'duplicate_page_' . $page;
                if ($debug) {
                    $pageDebug[] = array('page' => $page, 'items' => count($items), 'status' => 'duplicate');
                }
                break;
            }
            $seenPageFingerprints[$pageFingerprint] = true;
            if ($debug) {
                $pageDebug[] = array('page' => $page, 'items' => count($items), 'status' => 'imported');
            }

            foreach ($items as $item) {
                if (is_array($item)) {
                    if ($this->storage->upsertRemoteOfferSnapshot($item, $this->accountLabel())) {
                        $synced++;
                    } else {
                        $skipped++;
                    }
                }
            }

            $pages++;
            if (count($items) < $pageLimit) {
                $stopReason = 'short_page_' . $page;
                break;
            }
        }

        $result = array(
            'synced_offers' => $synced,
            'skipped_offers' => $skipped,
            'removed_invalid' => $removedInvalid,
            'pages_processed' => $pages,
            'stats' => $this->storage->offerStats(),
        );

        if ($debug) {
            $result['debug'] = array(
                'page_limit' => $pageLimit,
                'max_pages' => $maxPages,
                'stop_reason' => $stopReason,
                'pages' => $pageDebug,
            );
        }

        return $result;
    }

    public function categoryCharacteristics(int $categoryId): array
    {
        if ($categoryId <= 0) {
            throw new RuntimeException('Brak poprawnego ID kategorii Morele.');
        }

        $accessToken = $this->resolveAccessToken(false);

        try {
            return $this->requestFeatures($categoryId, $accessToken);
        } catch (RuntimeException $exception) {
            if (strpos($exception->getMessage(), 'HTTP 401') === false) {
                throw $exception;
            }
        }

        $accessToken = $this->resolveAccessToken(true);
        return $this->requestFeatures($categoryId, $accessToken);
    }

    private function requestFeatures(int $categoryId, string $accessToken): array
    {
        $payload = $this->requestJson(
            '/offer/category/features/' . rawurlencode((string) $categoryId),
            'GET',
            array('accept: application/json', 'Authorization: Bearer ' . $accessToken)
        );

        if (!isset($payload['category_characteristics']) || !is_array($payload['category_characteristics'])) {
            throw new RuntimeException('Morele API nie zwrocilo listy cech w oczekiwanym formacie.');
        }

        return $payload;
    }

    private function resolveAccessToken(bool $forceRefresh): string
    {
        $accessToken = trim($this->settings->get('morele_access_token', ''));
        if (!$forceRefresh && $accessToken !== '') {
            return $accessToken;
        }

        $refreshToken = trim($this->settings->get('morele_refresh_token', ''));
        if ($refreshToken !== '') {
            try {
                return $this->refreshAccessToken($refreshToken);
            } catch (RuntimeException $exception) {
                if (!$forceRefresh) {
                    throw $exception;
                }
            }
        }

        return $this->registerAccessToken();
    }

    private function registerAccessToken(): string
    {
        $response = $this->requestJson(
            '/auth/register',
            'POST',
            array('Authorization: Basic ' . $this->basicAuthorizationHeader()),
            null
        );

        return $this->storeTokenPayload($response);
    }

    private function refreshAccessToken(string $refreshToken): string
    {
        $response = $this->requestJson(
            '/auth/refresh',
            'POST',
            array('Authorization: Basic ' . $this->basicAuthorizationHeader()),
            array('refresh_token' => $refreshToken)
        );

        return $this->storeTokenPayload($response);
    }

    private function storeTokenPayload(array $payload): string
    {
        $accessToken = trim((string) ($payload['access_token'] ?? ''));
        $refreshToken = trim((string) ($payload['refresh_token'] ?? ''));

        if ($accessToken === '') {
            throw new RuntimeException('Morele API nie zwrocilo access_token.');
        }

        $this->settings->set('morele_access_token', $accessToken);
        if ($refreshToken !== '') {
            $this->settings->set('morele_refresh_token', $refreshToken);
        }

        return $accessToken;
    }

    private function basicAuthorizationHeader(): string
    {
        $clientId = trim($this->settings->get('morele_client_id', ''));
        $clientSecret = trim($this->settings->get('morele_client_secret', ''));

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Brak konfiguracji Morele: uzupelnij Client ID i Client Secret w administracji.');
        }

        return base64_encode($clientId . ':' . $clientSecret);
    }

    private function apiBaseUrl(): string
    {
        $configured = rtrim(trim($this->settings->get('morele_api_url', self::DEFAULT_API_BASE)), '/');
        return $configured !== '' ? $configured : self::DEFAULT_API_BASE;
    }

    private function normalizeQueueOperation(string $operation): string
    {
        $operation = trim($operation);
        $allowed = array(
            'set_price',
            'set_price_from_product',
            'set_stock_from_product',
            'end_offer',
            'activate_offer',
            'sync_offer',
            'clear_queue',
        );

        return in_array($operation, $allowed, true) ? $operation : '';
    }

    private function normalizeQueuePayload(string $operation, array $payload): array
    {
        if ($operation === 'set_price') {
            $value = str_replace(',', '.', trim((string) ($payload['value'] ?? '')));
            if ($value === '' || !is_numeric($value)) {
                throw new RuntimeException('Cena Morele musi byc liczba.');
            }
            return array('value' => round((float) $value, 2));
        }

        return array();
    }

    private function executeQueueOperation(array $offer, string $operation, array $payload): void
    {
        switch ($operation) {
            case 'set_price':
                $this->storage->updateOfferOverrides((int) $offer['id'], array('price_override' => round((float) ($payload['value'] ?? 0), 2)));
                break;
            case 'set_price_from_product':
                $warehouse = $this->fetchWarehouseRowForOffer($offer);
                $this->storage->updateOfferOverrides((int) $offer['id'], array('price_override' => round((float) ($warehouse['price_gross'] ?? 0), 2)));
                break;
            case 'set_stock_from_product':
                throw new RuntimeException('Zmiana stanu Morele jest jeszcze wylaczona.');
                $warehouse = $this->fetchWarehouseRowForOffer($offer);
                $this->storage->updateOfferOverrides((int) $offer['id'], array('stock_override' => max(0, (int) ($warehouse['quantity'] ?? 0))));
                break;
            case 'end_offer':
                throw new RuntimeException('Zakanczanie ofert Morele jest jeszcze wylaczone.');
                $this->storage->updateOfferOverrides((int) $offer['id'], array('status_override' => 'inactive'));
                break;
            case 'activate_offer':
                throw new RuntimeException('Aktywowanie ofert Morele jest jeszcze wylaczone.');
                $this->storage->updateOfferOverrides((int) $offer['id'], array('status_override' => 'active'));
                break;
            case 'sync_offer':
                throw new RuntimeException('Pelna synchronizacja ofert Morele jest jeszcze wylaczona.');
                break;
            default:
                throw new RuntimeException('Nieobslugiwana operacja Morele.');
        }

        $fresh = $this->storage->findOfferById((int) $offer['id']);
        if (!$fresh) {
            throw new RuntimeException('Oferta Morele zniknela przed synchronizacja.');
        }

        $this->syncRemoteOffer($fresh, $operation);
    }

    private function fetchWarehouseRowForOffer(array $offer): array
    {
        $warehouseProductId = (int) ($offer['warehouse_product_id'] ?? 0);
        if ($warehouseProductId <= 0) {
            throw new RuntimeException('Brak powiazania oferty Morele z magazynem po SKU.');
        }

        $rows = $this->products->exportRows(array($warehouseProductId), 1);
        if ($rows === array() || !is_array($rows[0])) {
            throw new RuntimeException('Nie znaleziono produktu magazynowego dla SKU Morele.');
        }

        return $rows[0];
    }

    private function syncRemoteOffer(array $offer, string $operation): void
    {
        $externalId = trim((string) ($offer['external_id'] ?? ''));
        if ($externalId === '') {
            throw new RuntimeException('Brak ID oferty Morele.');
        }

        $payload = $this->buildRemoteOfferPayload($offer, $operation);
        if ($payload === array()) {
            return;
        }

        try {
            $response = $this->authenticatedJson('/offer', 'PUT', $payload);
            $active = strtolower((string) ($offer['effective_status'] ?? '')) === 'active';
            if ($operation === 'end_offer') {
                $active = false;
            } elseif ($operation === 'activate_offer') {
                $active = true;
            }
            $this->storage->markOfferSyncSuccess((int) $offer['id'], $this->localPayloadAfterRemoteSync($offer, $payload, $response), $active);
        } catch (RuntimeException $exception) {
            $this->storage->markOfferSyncError((int) $offer['id'], $exception->getMessage());
            throw $exception;
        }
    }

    private function localPayloadAfterRemoteSync(array $offer, array $requestPayload, array $responsePayload): array
    {
        $currentPayload = json_decode((string) ($offer['payload_json'] ?? ''), true);
        $currentPayload = is_array($currentPayload) ? $currentPayload : array();

        return array_merge($currentPayload, $requestPayload, $responsePayload);
    }

    private function buildRemoteOfferPayload(array $offer, string $operation): array
    {
        $price = round((float) ($offer['effective_price'] ?? 0), 2);
        $stock = max(0, (int) ($offer['effective_quantity'] ?? 0));
        $status = strtolower(trim((string) ($offer['effective_status'] ?? 'inactive')));

        switch ($operation) {
            case 'set_price':
            case 'set_price_from_product':
                return $this->buildRemotePricePayload($offer, $price);
            case 'set_stock_from_product':
                return array('quantity' => $stock, 'stock' => $stock);
            case 'end_offer':
                return array('status' => 'inactive', 'active' => false);
            case 'activate_offer':
                return array('status' => 'active', 'active' => true);
            case 'sync_offer':
                return array(
                    'price' => $price,
                    'quantity' => $stock,
                    'stock' => $stock,
                    'status' => $status === 'active' ? 'active' : 'inactive',
                    'active' => $status === 'active',
                );
        }

        return array();
    }

    private function buildRemotePricePayload(array $offer, float $price): array
    {
        $sku = trim((string) ($offer['sku'] ?? ''));
        if ($sku === '') {
            throw new RuntimeException('Brak SKU/vendor_part_number Morele do zmiany ceny.');
        }

        return array(
            'vendor_part_number' => $sku,
            'sale_price_brutto' => $price,
            'price' => $price,
            'salePriceBrutto' => $price,
        );
    }

    private function fetchRemoteOfferPage(int $page, int $limit): array
    {
        $start = max(0, ($page - 1) * $limit);
        $filterPayload = array(
            'vendor_part_number' => '',
            'marketplace_id' => '',
            'vendor_product_name' => '',
            'barcodes' => '',
            'availability' => '',
            'quantity_variant' => '',
            'brand_code' => '',
            'has_brand_code' => '',
            'vendor_category_name' => '',
            'marketplace_category_id' => '',
            'marketplace_brand_id' => '',
            'offer_status' => '',
            'has_ean' => '',
            'morele_product_name' => '',
            'limit' => (string) $limit,
            'start' => (string) $start,
            'offset' => (string) $start,
            'page' => (string) $page,
            'public' => '',
            'promotion_is_active' => '',
            'promotion_campaign_name' => '',
        );
        $query = http_build_query(array(
            'page' => $page,
            'quantity' => $limit,
            'limit' => $limit,
            'per_page' => $limit,
        ));
        $requests = array(
            array('path' => '/offer/filter?limit=' . rawurlencode((string) $limit) . '&start=' . rawurlencode((string) $start) . '&offset=' . rawurlencode((string) $start) . '&page=' . rawurlencode((string) $page), 'method' => 'POST', 'body' => $filterPayload),
            array('path' => '/offer/filter', 'method' => 'POST', 'body' => $filterPayload),
            array('path' => '/offer?' . $query, 'method' => 'GET', 'body' => null),
        );
        $lastException = null;

        foreach ($requests as $request) {
            try {
                $payload = $this->authenticatedJson($request['path'], $request['method'], $request['body']);
                return $this->extractOfferItems($payload);
            } catch (RuntimeException $exception) {
                $lastException = $exception;
            }
        }

        throw $lastException ?: new RuntimeException('Nie udalo sie pobrac ofert Morele.');
    }

    private function extractOfferItems(array $payload): array
    {
        $items = $this->extractKnownOfferList($payload);
        if ($items === array()) {
            $items = $this->collectOfferItems($payload);
        }

        $unique = array();
        $seen = array();

        foreach ($items as $item) {
            if (!is_array($item) || !$this->isPersistableOfferItem($item)) {
                continue;
            }

            $fingerprint = sha1(json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            if (isset($seen[$fingerprint])) {
                continue;
            }
            $seen[$fingerprint] = true;
            $unique[] = $item;
        }

        return $unique;
    }

    private function extractKnownOfferList(array $payload): array
    {
        foreach (array(
            array('products'),
            array('offers'),
            array('items'),
            array('data', 'products'),
            array('data', 'offers'),
            array('data', 'items'),
            array('data', 'results'),
            array('data', 'list'),
            array('data'),
            array('result', 'products'),
            array('result', 'offers'),
            array('result', 'items'),
            array('response', 'products'),
            array('response', 'offers'),
        ) as $path) {
            $container = $this->arrayAtPath($payload, $path);
            if ($container === null) {
                continue;
            }

            $items = $this->itemsFromKnownContainer($container);
            if ($items !== array()) {
                return $items;
            }
        }

        return $this->isListArray($payload) ? $payload : array();
    }

    private function arrayAtPath(array $payload, array $path): ?array
    {
        $current = $payload;
        foreach ($path as $segment) {
            if (!is_array($current) || !isset($current[$segment]) || !is_array($current[$segment])) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    private function itemsFromKnownContainer(array $container): array
    {
        $items = array();

        if ($this->isListArray($container)) {
            foreach ($container as $row) {
                if (is_array($row)) {
                    $items[] = $row;
                }
            }
            return $items;
        }

        foreach ($container as $key => $row) {
            if (!is_array($row)) {
                continue;
            }
            if (is_string($key) && $key !== '' && !isset($row['_morele_key'])) {
                $row['_morele_key'] = $key;
            }
            $items[] = $row;
        }

        return $items;
    }

    private function collectOfferItems(array $payload): array
    {
        $items = array();

        if ($this->isPersistableOfferItem($payload)) {
            return array($payload);
        }

        foreach (array('offers', 'items', 'products', 'results', 'records', 'rows', 'list') as $key) {
            if (!isset($payload[$key]) || !is_array($payload[$key])) {
                continue;
            }

            $items = array_merge($items, $this->collectOfferItemsFromContainer($payload[$key]));
        }

        if ($items !== array()) {
            return $items;
        }

        foreach (array('data', 'result', 'response') as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $items = array_merge($items, $this->collectOfferItems($payload[$key]));
            }
        }

        return $items;
    }

    private function collectOfferItemsFromContainer(array $container): array
    {
        if ($this->isPersistableOfferItem($container)) {
            return array($container);
        }

        $items = array();
        if ($this->isListArray($container)) {
            foreach ($container as $row) {
                if (is_array($row)) {
                    $items = array_merge($items, $this->collectOfferItems($row));
                }
            }
            return $items;
        }

        foreach ($container as $key => $row) {
            if (is_array($row)) {
                if (is_string($key) && $key !== '') {
                    $row['_morele_key'] = $key;
                }
                $items = array_merge($items, $this->collectOfferItems($row));
            }
        }

        return $items;
    }

    private function isPersistableOfferItem(array $item): bool
    {
        return $this->hasOfferIdentity($item) && $this->hasOfferContent($item);
    }

    private function hasOfferIdentity(array $item): bool
    {
        $identityKeys = array(
            'offer_id', 'offerId', 'offerIdentifier', 'marketplaceOfferId', 'marketplace_offer_id',
            'external_offer_id', 'externalOfferId', 'sku', 'shop_sku', 'shopSku', 'product_sku',
            'productSku', 'vendorCode', 'vendor_code', 'producer_code', 'vendor_part_number',
            'vendorPartNumber', 'product_id', 'productId',
        );
        foreach ($identityKeys as $key) {
            if (!empty($item[$key]) && is_scalar($item[$key])) {
                return true;
            }
        }

        $keyIdentity = trim((string) ($item['_morele_key'] ?? ''));
        if ($keyIdentity !== ''
            && !in_array(strtolower($keyIdentity), array('data', 'result', 'response', 'meta', 'metadata', 'pagination', 'stats', 'count', 'total'), true)
            && $this->hasStrongOfferContent($item)
        ) {
            return true;
        }

        foreach (array('offer', 'marketplaceOffer', 'saleOffer', 'productOffer', 'product') as $key) {
            if (isset($item[$key]) && is_array($item[$key]) && $this->hasOfferIdentity($item[$key])) {
                return true;
            }
        }

        return false;
    }

    private function hasStrongOfferContent(array $item): bool
    {
        foreach (array(
            'sku', 'shop_sku', 'shopSku', 'product_sku', 'productSku', 'vendorCode',
            'vendor_code', 'vendor_part_number', 'vendorPartNumber', 'name', 'title',
            'productName', 'product_name', 'productTitle', 'product_fullname',
            'vendor_product_name', 'morele_product_name', 'price', 'grossPrice',
            'salePrice', 'sale_price_brutto', 'price_with_category_margin',
        ) as $key) {
            if (!empty($item[$key]) && is_scalar($item[$key])) {
                return true;
            }
        }

        return false;
    }

    private function hasOfferContent(array $item): bool
    {
        foreach (array(
            'id', 'offer_id', 'offerId', 'externalId', 'external_id', 'identifier',
            'sku', 'shop_sku', 'shopSku', 'product_sku', 'productSku', 'vendorCode',
            'vendor_code', 'vendor_part_number', 'vendorPartNumber', 'product_id', 'productId',
            'name', 'title', 'productName', 'product_name', 'productTitle', 'product_fullname',
            'vendor_product_name', 'morele_product_name', 'price', 'grossPrice', 'salePrice',
            'sale_price_brutto', 'price_with_category_margin', 'quantity', 'stock', 'status',
            'state', 'offer_status',
        ) as $key) {
            if (!empty($item[$key]) && is_scalar($item[$key])) {
                return true;
            }
        }

        return false;
    }

    private function isListArray(array $value): bool
    {
        $index = 0;
        foreach (array_keys($value) as $key) {
            if ($key !== $index) {
                return false;
            }
            $index++;
        }

        return true;
    }

    private function authenticatedJson(string $path, string $method, ?array $body = null): array
    {
        $accessToken = $this->resolveAccessToken(false);
        try {
            return $this->requestJson($path, $method, array(
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ), $body);
        } catch (RuntimeException $exception) {
            if (strpos($exception->getMessage(), 'HTTP 401') === false) {
                throw $exception;
            }
        }

        $accessToken = $this->resolveAccessToken(true);
        return $this->requestJson($path, $method, array(
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ), $body);
    }

    private function hasCredentials(): bool
    {
        return trim((string) $this->settings->get('morele_client_id', '')) !== ''
            && trim((string) $this->settings->get('morele_client_secret', '')) !== '';
    }

    private function requestJson(string $path, string $method, array $headers, $postFields = null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Brak rozszerzenia cURL potrzebnego do integracji Morele.');
        }

        $url = $this->apiBaseUrl() . $path;
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Nie udalo sie zainicjalizowac polaczenia z Morele API.');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(array(
            'User-Agent: ALTREO-Morele/1.0',
            'Accept: application/json',
        ), $headers));

        if ($postFields !== null) {
            $isJson = false;
            foreach ($headers as $header) {
                if (stripos($header, 'content-type: application/json') !== false) {
                    $isJson = true;
                    break;
                }
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $isJson ? json_encode($postFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $postFields);
        }

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ((!is_string($raw) || $raw === '') && $httpCode >= 200 && $httpCode < 300) {
            return array();
        }

        if (!is_string($raw) || $raw === '') {
            throw new RuntimeException('Morele API nie zwrocilo odpowiedzi.');
        }

        $decoded = json_decode($raw, true);
        if ($httpCode < 200 || $httpCode >= 300) {
            $message = is_array($decoded) ? $this->extractErrorMessage($decoded) : '';
            if ($message === '' && $curlError !== '') {
                $message = $curlError;
            }

            throw new RuntimeException('Morele API zwrocilo blad HTTP ' . $httpCode . ($message !== '' ? ': ' . $message : '.'));
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Morele API zwrocilo niepoprawny JSON.');
        }

        return $decoded;
    }

    private function extractErrorMessage(array $payload): string
    {
        foreach (array('message', 'error', 'description', 'detail', 'msg') as $key) {
            if (!isset($payload[$key])) {
                continue;
            }

            if (is_scalar($payload[$key])) {
                $value = trim((string) $payload[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }
}
