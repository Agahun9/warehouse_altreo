<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\DerivedStockLinkRepository;
use App\Models\ProductCustomFieldRepository;
use App\Models\ProductChangeLogRepository;
use App\Models\ProductRepository;
use App\Models\SellasistOrderSyncRepository;
use App\Models\SettingRepository;
use App\Models\SharedStockGroupRepository;
use RuntimeException;

class SellasistService
{
    const DEFAULT_BASE_URL = 'https://altreo.sellasist.pl';
    const PICKING_STATUS_ID = 23;
    const PRINTED_STATUS_ID = 3;
    const LEGACY_API_FILE = BASE_PATH . '/sellasist_api.php';

    /** @var Database */
    private $database;

    /** @var SettingRepository */
    private $settings;

    /** @var ProductRepository */
    private $products;

    /** @var ProductCustomFieldRepository */
    private $customFields;

    /** @var DerivedStockLinkRepository */
    private $derivedLinks;

    /** @var SharedStockGroupRepository */
    private $sharedStockGroups;

    /** @var ProductChangeLogRepository */
    private $productChangeLogs;

    /** @var SellasistOrderSyncRepository */
    private $syncLogs;

    /** @var array<string, array|null> */
    private $productCache = array();

    public function __construct(?Database $database = null, ?SettingRepository $settings = null)
    {
        $this->database = $database ?: Database::instance();
        $this->settings = $settings ?: new SettingRepository($this->database);
        $this->settings->ensureSchema();

        $this->products = new ProductRepository($this->database);
        $this->products->ensureSchema();

        $this->customFields = new ProductCustomFieldRepository($this->database);
        $this->customFields->ensureSchema();

        $this->derivedLinks = new DerivedStockLinkRepository($this->database);
        $this->derivedLinks->ensureSchema();

        $this->sharedStockGroups = new SharedStockGroupRepository($this->database);
        $this->sharedStockGroups->ensureSchema();

        $this->productChangeLogs = new ProductChangeLogRepository($this->database);
        $this->productChangeLogs->ensureSchema();

        $this->syncLogs = new SellasistOrderSyncRepository($this->database);
        $this->syncLogs->ensureSchema();
    }

    public function configuration(): array
    {
        return array(
            'base_url' => $this->baseUrl(),
            'api_key' => $this->settings->get('sellasist_api_key', ''),
            'picking_status_id' => $this->pickingStatusId(),
            'printed_status_id' => $this->printedStatusId(),
        );
    }

    public function listOrdersForPicking(?int $statusId = null): array
    {
        $statusId = $statusId === null ? $this->pickingStatusId() : $this->normalizeStatusId($statusId, self::PICKING_STATUS_ID);
        $orders = $this->getOrdersByStatus($statusId);
        $prepared = array();

        foreach ($orders as $order) {
            $prepared[] = $this->prepareOrderForList($this->orderDetailsForList($order));
        }

        return array_reverse($prepared);
    }

    public function generateStickers(array $orderIds, bool $markPrinted = true): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds), function (int $id): bool {
            return $id > 0;
        })));

        if ($orderIds === array()) {
            throw new RuntimeException('Wybierz przynajmniej jedno zamowienie.');
        }

        $caseStickers = array();
        $glassStickers = array();
        $warnings = array();

        foreach ($orderIds as $orderId) {
            $order = $this->getOrderById($orderId);
            if (!is_array($order) || empty($order['id'])) {
                $warnings[] = 'Nie udalo sie pobrac zamowienia #' . $orderId . '.';
                continue;
            }

            $entries = $this->buildOrderStickers($order);
            $caseStickers = array_merge($caseStickers, $entries['case']);
            $glassStickers = array_merge($glassStickers, $entries['glass']);

            if ($markPrinted) {
                $printedStatusId = $this->printedStatusId();
                if ($printedStatusId === 0) {
                    continue;
                }

                try {
                    $this->changeOrderStatus((int) $order['id'], $printedStatusId);
                } catch (\Throwable $exception) {
                    $warnings[] = 'Nie udalo sie zmienic statusu zamowienia #' . (int) $order['id'] . '.';
                }
            }
        }

        usort($caseStickers, array($this, 'compareStickerRows'));
        usort($glassStickers, array($this, 'compareStickerRows'));

        $glassNumbers = $this->buildGlassNumberMap($glassStickers);

        foreach ($glassStickers as $index => $sticker) {
            $glassKey = (string) ($sticker['glass_key'] ?? '');
            $glassStickers[$index]['glass_number'] = $glassKey !== '' && isset($glassNumbers[$glassKey]) ? $glassNumbers[$glassKey] : '';
        }

        foreach ($caseStickers as $index => $sticker) {
            $glassKey = (string) ($sticker['glass_key'] ?? '');
            $caseStickers[$index]['glass_number'] = $glassKey !== '' && isset($glassNumbers[$glassKey]) ? $glassNumbers[$glassKey] : '';
        }

        return array(
            'case_stickers' => $caseStickers,
            'glass_stickers' => $glassStickers,
            'warnings' => $warnings,
            'barcode_base_url' => $this->baseUrl() . '/api/get_barcode',
        );
    }

    public function subtractStockForOrder(int $orderId): array
    {
        $order = $this->getOrderById($orderId);
        if (!is_array($order) || empty($order['id'])) {
            throw new RuntimeException('Nie znaleziono zamowienia Sellasist #' . $orderId . '.');
        }

        $items = isset($order['carts']) && is_array($order['carts']) ? $order['carts'] : array();
        if ($items === array()) {
            throw new RuntimeException('Zamowienie #' . (int) $order['id'] . ' nie ma pozycji do odjecia.');
        }

        $result = array(
            'order_id' => (int) ($order['id'] ?? $orderId),
            'order_total' => $this->orderTotal($order),
            'currency' => $this->orderCurrency($order),
            'items_count' => count($items),
            'deductions' => array(),
        );

        $this->database->transaction(function () use ($order, $items, &$result): void {
            foreach ($items as $item) {
                $signature = trim((string) ($item['signature'] ?? ($item['symbol'] ?? '')));
                if ($signature === '') {
                    continue;
                }

                $resolved = $this->resolveProductBySignature($signature);
                $targets = $this->stockTargetsForResolvedProduct($resolved, $item);
                if ($targets === array()) {
                    $result['deductions'][] = array(
                        'signature' => $signature,
                        'source_name' => trim((string) ($item['name'] ?? '')),
                        'status' => 'not_found',
                        'message' => 'Nie znaleziono produktu magazynowego.',
                    );
                    continue;
                }

                $bundleMultiplier = $this->bundleMultiplier((string) ($item['name'] ?? ''));
                $orderQty = max(1, (int) round((float) ($item['quantity'] ?? 1)));
                $deductQty = max(1, $orderQty * $bundleMultiplier);

                foreach ($targets as $target) {
                    $result['deductions'][] = $this->subtractFromWarehouseProduct(
                        $target,
                        $deductQty,
                        $order,
                        $item,
                        $signature,
                        $resolved
                    );
                }
            }
        });

        $this->syncLogs->recordDailyOrder(
            'subtract_stock',
            (int) $result['order_id'],
            (float) $result['order_total'],
            (string) $result['currency'],
            (int) $result['items_count'],
            $result
        );

        return $result;
    }

    public function addStockForOrder(int $orderId): array
    {
        $order = $this->getOrderById($orderId);
        if (!is_array($order) || empty($order['id'])) {
            throw new RuntimeException('Nie znaleziono zamowienia Sellasist #' . $orderId . '.');
        }

        $items = isset($order['carts']) && is_array($order['carts']) ? $order['carts'] : array();
        if ($items === array()) {
            throw new RuntimeException('Zamowienie #' . (int) $order['id'] . ' nie ma pozycji do dodania.');
        }

        $result = array(
            'order_id' => (int) ($order['id'] ?? $orderId),
            'order_total' => $this->orderTotal($order),
            'currency' => $this->orderCurrency($order),
            'items_count' => count($items),
            'deductions' => array(),
        );

        $this->database->transaction(function () use ($order, $items, &$result): void {
            foreach ($items as $item) {
                $signature = trim((string) ($item['signature'] ?? ($item['symbol'] ?? '')));
                if ($signature === '') {
                    continue;
                }

                $resolved = $this->resolveProductBySignature($signature);
                $targets = $this->stockTargetsForResolvedProduct($resolved, $item);
                if ($targets === array()) {
                    $result['deductions'][] = array(
                        'signature' => $signature,
                        'source_name' => trim((string) ($item['name'] ?? '')),
                        'status' => 'not_found',
                        'message' => 'Nie znaleziono produktu magazynowego.',
                    );
                    continue;
                }

                $bundleMultiplier = $this->bundleMultiplier((string) ($item['name'] ?? ''));
                $orderQty = max(1, (int) round((float) ($item['quantity'] ?? 1)));
                $addQty = max(1, $orderQty * $bundleMultiplier);

                foreach ($targets as $target) {
                    $result['deductions'][] = $this->adjustWarehouseProductStock(
                        $target,
                        $addQty,
                        $order,
                        $item,
                        $signature,
                        $resolved,
                        'add'
                    );
                }
            }
        });

        $this->syncLogs->recordDailyOrder(
            'add_stock',
            (int) $result['order_id'],
            (float) $result['order_total'],
            (string) $result['currency'],
            (int) $result['items_count'],
            $result
        );

        return $result;
    }

    public function todaySubtractSummary(): array
    {
        return $this->syncLogs->todaySummary('subtract_stock');
    }

    public function getOrdersByStatus(int $statusId, int $maxOrders = 250): array
    {
        $this->ensureConfigured();

        $orders = array();
        $unique = array();
        $limit = 100;
        $offset = 0;

        do {
            $response = $this->request(
                'GET',
                '/api/v1/orders?status_id=' . $statusId . '&sort=asc&limit=' . $limit . '&offset=' . $offset,
                null,
                true
            );

            if (!is_array($response) || $response === array()) {
                break;
            }

            foreach ($response as $order) {
                $id = isset($order['id']) ? (int) $order['id'] : 0;
                if ($id <= 0 || isset($unique[$id])) {
                    continue;
                }

                $orders[] = $order;
                $unique[$id] = true;

                if (count($orders) >= $maxOrders) {
                    break 2;
                }
            }

            if (count($response) < $limit) {
                break;
            }

            $offset += $limit;
        } while ($offset < $maxOrders + $limit);

        return $orders;
    }

    public function getOrderById(int $orderId): array
    {
        $this->ensureConfigured();

        $response = $this->request('GET', '/api/v1/orders/' . $orderId);
        return is_array($response) ? $response : array();
    }

    public function changeOrderStatus(int $orderId, int $statusId): void
    {
        $this->ensureConfigured();

        $this->request('PUT', '/api/v1/orders/' . $orderId, array(
            'status' => $statusId,
        ));
    }

    public function addOrderNote(int $orderId, string $text): array
    {
        $this->ensureConfigured();

        $response = $this->request('POST', '/api/v1/notes', array(
            'order_id' => $orderId,
            'text' => $text,
        ));

        return is_array($response) ? $response : array();
    }

    public function pickingStatusId(): int
    {
        return $this->normalizeStatusId(
            (int) $this->settings->get('sellasist_picking_status_id', (string) self::PICKING_STATUS_ID),
            self::PICKING_STATUS_ID
        );
    }

    public function printedStatusId(): int
    {
        $value = (int) $this->settings->get('sellasist_printed_status_id', (string) self::PRINTED_STATUS_ID);
        if ($value === 0) {
            return 0;
        }

        return $this->normalizeStatusId($value, self::PRINTED_STATUS_ID);
    }

    private function normalizeStatusId(int $value, int $default): int
    {
        return $value > 0 ? $value : $default;
    }

    private function buildOrderStickers(array $order): array
    {
        $caseStickers = array();
        $glassStickers = array();
        $items = isset($order['carts']) && is_array($order['carts']) ? $order['carts'] : array();
        $productCount = count($items);

        foreach ($items as $item) {
            $signature = trim((string) ($item['signature'] ?? ($item['symbol'] ?? '')));
            if ($signature === '') {
                continue;
            }

            $resolved = $this->resolveProductBySignature($signature);
            $mainProduct = isset($resolved['main']) && is_array($resolved['main']) ? $resolved['main'] : null;
            $glassProduct = isset($resolved['glass']) && is_array($resolved['glass']) ? $resolved['glass'] : null;
            $fallbackProduct = $this->fallbackProduct($item);

            if ($mainProduct === null) {
                $mainProduct = $fallbackProduct;
            }

            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $bundleMultiplier = $this->bundleMultiplier((string) ($item['name'] ?? ''));

            if (!$this->sameWarehouseProduct($mainProduct, $glassProduct)) {
                $caseStickers[] = $this->buildStickerRow(
                    $order,
                    $item,
                    $mainProduct,
                    $productCount,
                    $qty * $bundleMultiplier,
                    $glassProduct
                );
            }

            if ($glassProduct !== null) {
                $glassStickers[] = $this->buildStickerRow(
                    $order,
                    $item,
                    $glassProduct,
                    $productCount,
                    $qty,
                    $glassProduct
                );
            }
        }

        return array(
            'case' => $caseStickers,
            'glass' => $glassStickers,
        );
    }

    private function sameWarehouseProduct(?array $left, ?array $right): bool
    {
        if ($left === null || $right === null) {
            return false;
        }

        $leftId = isset($left['id']) ? (int) $left['id'] : 0;
        $rightId = isset($right['id']) ? (int) $right['id'] : 0;
        if ($leftId > 0 && $rightId > 0) {
            return $leftId === $rightId;
        }

        $leftSku = trim((string) ($left['sku'] ?? ''));
        $rightSku = trim((string) ($right['sku'] ?? ''));
        return $leftSku !== '' && $rightSku !== '' && strcasecmp($leftSku, $rightSku) === 0;
    }

    private function buildStickerRow(array $order, array $item, array $product, int $productCount, int $qty, ?array $glassProduct): array
    {
        $delivery = str_replace('Allegro ', '', trim((string) ($order['external_data']['external_shipment_name'] ?? '')));
        $account = trim((string) ($order['creator'] ?? ''));
        $info = trim($this->joinNonEmpty(array(
            (string) ($order['comment'] ?? ''),
            (string) ($order['additional_fields'][0]['field_value'] ?? ''),
        )));
        $glassKey = '';

        if ($glassProduct !== null) {
            $glassKey = $this->glassKey($glassProduct);
        }

        return array(
            'order_id' => (int) ($order['id'] ?? 0),
            'product_count' => $productCount,
            'product' => array(
                'id' => isset($product['id']) ? (int) $product['id'] : 0,
                'sku' => isset($product['sku']) ? (string) $product['sku'] : '',
                'product_name' => isset($product['product_name']) ? (string) $product['product_name'] : trim((string) ($item['name'] ?? '')),
                'localization' => trim((string) ($product['localization'] ?? '')),
            ),
            'product_id' => trim((string) ($item['signature'] ?? ($item['symbol'] ?? ''))),
            'qty' => max(1, $qty),
            'delivery_fullname' => trim($this->joinNonEmpty(array(
                (string) ($order['bill_address']['name'] ?? ''),
                (string) ($order['bill_address']['surname'] ?? ''),
            ))),
            'account' => $account,
            'account_short' => $this->accountShort($account),
            'delivery' => $delivery,
            'carrier_short' => $this->carrierShort($delivery),
            'info' => $info,
            'display_info' => $info !== '' ? $info : 'WZOR Z MINIATURY',
            'glass' => $glassProduct !== null ? 1 : 0,
            'glass_key' => $glassKey,
            'glass_number' => '',
        );
    }

    private function resolveProductBySignature(string $signature): array
    {
        $cacheKey = mb_strtolower(trim($signature), 'UTF-8');
        if (array_key_exists($cacheKey, $this->productCache)) {
            return $this->productCache[$cacheKey] ?: array('owner' => null, 'main' => null, 'glass' => null);
        }

        $owner = $this->findWarehouseProductBySignature($signature);

        if (!$owner || !is_array($owner)) {
            $this->productCache[$cacheKey] = null;
            return array('owner' => null, 'main' => null, 'glass' => null);
        }

        $sources = $this->fullDerivedSourcesForProduct((int) $owner['id']);
        $glassProduct = null;
        $mainProduct = $owner;

        if ($sources !== array()) {
            foreach ($sources as $source) {
                if ($this->looksLikeGlass((string) ($source['product_name'] ?? ''))) {
                    $glassProduct = $source;
                    continue;
                }

                if ($mainProduct === $owner) {
                    $mainProduct = $source;
                }
            }
        } elseif ($this->looksLikeGlass((string) ($owner['product_name'] ?? ''))) {
            $glassProduct = $owner;
        }

        $resolved = array(
            'owner' => $owner,
            'main' => $mainProduct,
            'glass' => $glassProduct,
        );

        $this->productCache[$cacheKey] = $resolved;
        return $resolved;
    }

    private function findWarehouseProductBySignature(string $signature)
    {
        $signature = trim($signature);
        if ($signature === '') {
            return false;
        }

        $containsLetters = preg_match('/[a-z]/i', $signature) === 1;
        if ($containsLetters) {
            $product = $this->products->findBySku($signature);
            if ($product) {
                return $product;
            }

            return $this->findByOldSku($signature);
        }

        $product = $this->findByOldSku($signature);
        if ($product) {
            return $product;
        }

        return $this->products->findBySku($signature);
    }

    private function findByOldSku(string $value)
    {
        $productIds = $this->customFields->findProductIdsBySlugAndValue('old_sku', $value);
        if (!isset($productIds[0]) || (int) $productIds[0] <= 0) {
            return false;
        }

        return $this->products->find((int) $productIds[0]);
    }

    private function fullDerivedSourcesForProduct(int $productId): array
    {
        $sources = array();

        foreach ($this->derivedLinks->sourceIdsForProduct($productId) as $sourceId) {
            $source = $this->products->find((int) $sourceId);
            if ($source && is_array($source)) {
                $sources[] = $source;
            }
        }

        return $sources;
    }

    private function stockTargetsForResolvedProduct(array $resolved, array $item): array
    {
        $owner = isset($resolved['owner']) && is_array($resolved['owner']) ? $resolved['owner'] : null;
        if ($owner === null) {
            return array();
        }

        $sourceIds = $this->derivedLinks->sourceIdsForProduct((int) $owner['id']);
        if ($sourceIds === array()) {
            return array($owner);
        }

        $targets = array();
        $sources = $this->fullDerivedSourcesForProduct((int) $owner['id']);
        $itemName = (string) ($item['name'] ?? '');

        $bestMain = $this->bestMatchingSource($sources, $itemName, false);
        $bestGlass = $this->bestMatchingSource($sources, $itemName, true);

        if ($bestMain !== null) {
            $targets[] = $bestMain;
        }

        if ($bestGlass !== null) {
            $bestGlassId = isset($bestGlass['id']) ? (int) $bestGlass['id'] : 0;
            $alreadyAdded = false;
            foreach ($targets as $target) {
                if ((int) ($target['id'] ?? 0) === $bestGlassId && $bestGlassId > 0) {
                    $alreadyAdded = true;
                    break;
                }
            }

            if (!$alreadyAdded) {
                $targets[] = $bestGlass;
            }
        }

        return $targets !== array() ? $targets : array($owner);
    }

    private function bestMatchingSource(array $sources, string $itemName, bool $expectGlass): ?array
    {
        $best = null;
        $bestScore = -1;

        foreach ($sources as $source) {
            $isGlass = $this->looksLikeGlass((string) ($source['product_name'] ?? ''));
            if ($expectGlass !== $isGlass) {
                continue;
            }

            $score = $this->productMatchScore($source, $itemName);
            if ($score > $bestScore) {
                $best = $source;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function productMatchScore(array $product, string $itemName): int
    {
        $productName = $this->normalizeSearchValue((string) ($product['product_name'] ?? ''));
        $itemName = $this->normalizeSearchValue($itemName);
        if ($productName === '' || $itemName === '') {
            return 0;
        }

        $score = 0;
        if (strpos($itemName, $productName) !== false) {
            $score += 1000;
        }

        $productTokens = $this->searchTokens($productName);
        $itemTokens = $this->searchTokens($itemName);
        if ($productTokens === array() || $itemTokens === array()) {
            return $score;
        }

        $itemTokenMap = array_fill_keys($itemTokens, true);
        foreach ($productTokens as $token) {
            if (isset($itemTokenMap[$token])) {
                $score += mb_strlen($token, 'UTF-8') >= 3 ? 10 : 3;
            }
        }

        return $score;
    }

    private function normalizeSearchValue(string $value): string
    {
        $value = mb_strtolower($this->normalizeText($value), 'UTF-8');
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value);
        return trim((string) $value);
    }

    private function searchTokens(string $value): array
    {
        $parts = preg_split('/\s+/', trim($value));
        if (!is_array($parts)) {
            return array();
        }

        $tokens = array();
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            $tokens[] = $part;
        }

        return array_values(array_unique($tokens));
    }

    private function subtractFromWarehouseProduct(array $product, int $deductQty, array $order, array $item, string $signature, array $resolved): array
    {
        return $this->adjustWarehouseProductStock($product, $deductQty, $order, $item, $signature, $resolved, 'subtract');
    }

    private function adjustWarehouseProductStock(array $product, int $quantity, array $order, array $item, string $signature, array $resolved, string $mode): array
    {
        $productId = isset($product['id']) ? (int) $product['id'] : 0;
        if ($productId <= 0) {
            return array(
                'signature' => $signature,
                'source_name' => trim((string) ($item['name'] ?? '')),
                'status' => 'not_found',
                'message' => 'Nie znaleziono produktu magazynowego.',
            );
        }

        $current = $this->products->find($productId);
        if (!$current || !is_array($current)) {
            return array(
                'product_id' => $productId,
                'signature' => $signature,
                'source_name' => trim((string) ($item['name'] ?? '')),
                'status' => 'not_found',
                'message' => 'Produkt nie istnieje w magazynie.',
            );
        }

        $beforeQty = isset($current['quantity']) ? max(0, (int) $current['quantity']) : 0;
        $normalizedQty = max(1, $quantity);
        $isAdd = $mode === 'add';
        $afterQty = $isAdd
            ? $beforeQty + $normalizedQty
            : max(0, $beforeQty - $normalizedQty);

        $this->sharedStockGroups->updateStockValuesForProductSilently(
            (int) $current['id'],
            $afterQty,
            isset($current['localization']) ? (string) $current['localization'] : null
        );
        $updated = $this->products->find((int) $current['id']);

        $summary = ($isAdd ? 'Dodano' : 'Odjeto') . ' stan magazynowy przez Sellasist dla zamowienia #' . (int) ($order['id'] ?? 0)
            . ' | pozycja: ' . trim((string) ($item['name'] ?? ''))
            . ' | sygnatura: ' . $signature
            . ' | ilosc: ' . $normalizedQty . '.';

        if ($updated && is_array($updated)) {
            $loggedAfter = isset($updated['quantity']) ? (int) $updated['quantity'] : 0;
            $logProduct = isset($resolved['owner']) && is_array($resolved['owner']) ? $resolved['owner'] : $updated;
            $logProductId = isset($logProduct['id']) ? (int) $logProduct['id'] : 0;

            if ($logProductId > 0) {
                $this->productChangeLogs->add(array(
                    'product_id' => $logProductId,
                    'product_name_snapshot' => isset($logProduct['product_name']) ? (string) $logProduct['product_name'] : null,
                    'product_sku_snapshot' => isset($logProduct['sku']) ? (string) $logProduct['sku'] : null,
                    'actor_name' => 'Sellasist',
                    'actor_email' => 'Sellasist',
                    'action' => 'update',
                    'change_count' => 3,
                    'summary' => $summary,
                    'changes_json' => json_encode(array(
                        array(
                            'field' => 'quantity',
                            'label' => 'Ilosc skladnika',
                            'before' => (string) $beforeQty,
                            'after' => (string) $loggedAfter,
                        ),
                        array(
                            'field' => 'sellasist_component',
                            'label' => $isAdd ? 'Dodany skladnik' : 'Odjety skladnik',
                            'before' => 'brak',
                            'after' => (string) ($updated['sku'] ?? '') . ' - ' . (string) ($updated['product_name'] ?? ''),
                        ),
                        array(
                            'field' => 'sellasist_order',
                            'label' => 'Sellasist',
                            'before' => 'brak',
                            'after' => 'Zamowienie #' . (int) ($order['id'] ?? 0) . ', sygnatura ' . $signature,
                        ),
                    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ));
            }
        }

        return array(
            'product_id' => (int) $current['id'],
            'sku' => isset($current['sku']) ? (string) $current['sku'] : '',
            'product_name' => isset($current['product_name']) ? (string) $current['product_name'] : '',
            'signature' => $signature,
            'source_name' => trim((string) ($item['name'] ?? '')),
            'deducted_qty' => $normalizedQty,
            'before_qty' => $beforeQty,
            'after_qty' => $afterQty,
            'status' => 'ok',
        );
    }

    private function orderTotal(array $order): float
    {
        $itemsTotal = $this->orderItemsTotal($order);
        if ($itemsTotal > 0) {
            return $itemsTotal;
        }

        if (isset($order['total'])) {
            return round((float) $order['total'], 2);
        }

        if (isset($order['payment']['paid'])) {
            return round((float) $order['payment']['paid'], 2);
        }

        return 0.0;
    }

    private function orderItemsTotal(array $order): float
    {
        $items = isset($order['carts']) && is_array($order['carts']) ? $order['carts'] : array();
        if ($items === array()) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($items as $item) {
            $quantity = max(1, (int) round((float) ($item['quantity'] ?? 1)));
            $unitPrice = $this->itemUnitPrice($item);
            if ($unitPrice <= 0) {
                continue;
            }

            $total += $unitPrice * $quantity;
        }

        return round($total, 2);
    }

    private function itemUnitPrice(array $item): float
    {
        $candidates = array(
            $item['price_brutto'] ?? null,
            $item['price_gross'] ?? null,
            $item['gross_price'] ?? null,
            $item['price'] ?? null,
            $item['brutto'] ?? null,
            $item['total_brutto'] ?? null,
            $item['total_gross'] ?? null,
            $item['value_brutto'] ?? null,
            $item['value_gross'] ?? null,
            $item['sum_brutto'] ?? null,
            $item['sum_gross'] ?? null,
        );

        foreach ($candidates as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }

            $value = round((float) $candidate, 2);
            if ($value > 0) {
                return $value;
            }
        }

        return 0.0;
    }

    private function orderCurrency(array $order): string
    {
        $currency = trim((string) ($order['payment']['currency'] ?? 'PLN'));
        return $currency !== '' ? $currency : 'PLN';
    }

    private function prepareOrderForList(array $order): array
    {
        $items = $this->orderItems($order);
        $itemNames = array();
        $quantityCount = 0;

        foreach ($items as $item) {
            $name = trim((string) ($item['name'] ?? ($item['product_name'] ?? ($item['title'] ?? ''))));
            if ($name === '') {
                continue;
            }
            $itemNames[] = $name;
            $quantityCount += max(1, (int) round((float) ($item['quantity'] ?? ($item['qty'] ?? 1))));
        }

        return array(
            'id' => isset($order['id']) ? (int) $order['id'] : 0,
            'customer_name' => trim($this->joinNonEmpty(array(
                (string) ($order['bill_address']['name'] ?? ''),
                (string) ($order['bill_address']['surname'] ?? ''),
                (string) ($order['bill_address']['company_name'] ?? ''),
            ))),
            'delivery_name' => $this->orderDeliveryName($order),
            'comment' => $this->orderComment($order),
            'creator' => trim((string) ($order['creator'] ?? '')),
            'item_count' => count($items),
            'quantity_count' => $quantityCount,
            'items_summary' => implode(', ', array_slice($itemNames, 0, 4)),
        );
    }

    private function orderDetailsForList(array $order): array
    {
        if (!$this->orderNeedsDetailsForList($order)) {
            return $order;
        }

        $orderId = isset($order['id']) ? (int) $order['id'] : 0;
        if ($orderId <= 0) {
            return $order;
        }

        try {
            $details = $this->getOrderById($orderId);
        } catch (\Throwable $exception) {
            return $order;
        }

        if (!is_array($details) || $details === array()) {
            return $order;
        }

        return array_replace_recursive($order, $details);
    }

    private function orderNeedsDetailsForList(array $order): bool
    {
        return $this->orderItems($order) === array()
            || $this->orderDeliveryName($order) === ''
            || $this->orderComment($order) === '';
    }

    private function orderItems(array $order): array
    {
        foreach (array('carts', 'products', 'items', 'order_products') as $key) {
            if (isset($order[$key]) && is_array($order[$key])) {
                return $order[$key];
            }
        }

        return array();
    }

    private function orderDeliveryName(array $order): string
    {
        $candidates = array(
            $order['external_data']['external_shipment_name'] ?? null,
            $order['shipment']['name'] ?? null,
            $order['shipment_name'] ?? null,
            $order['delivery_name'] ?? null,
            $order['delivery']['name'] ?? null,
        );

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function orderComment(array $order): string
    {
        $candidates = array(
            $order['comment'] ?? null,
            $order['customer_comment'] ?? null,
            $order['buyer_comment'] ?? null,
            $order['additional_fields'][0]['field_value'] ?? null,
        );

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function request(string $method, string $path, ?array $payload = null, bool $allowNotFound = false)
    {
        $url = $this->baseUrl() . $path;
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $headers = array(
            'accept: application/json',
            'apiKey: ' . $this->apiKey(),
        );

        $method = strtoupper($method);
        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        if ($payload !== null && $method !== 'GET') {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Blad Sellasist API: ' . $error . ' | URL: ' . $url);
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);
        if ($httpCode >= 400) {
            if ($allowNotFound && $httpCode === 404) {
                return array();
            }

            $message = is_array($decoded) && isset($decoded['message']) ? (string) $decoded['message'] : ('HTTP ' . $httpCode);
            throw new RuntimeException('Sellasist API zwrocilo blad: ' . $message . ' | URL: ' . $url);
        }

        return $decoded;
    }

    private function baseUrl(): string
    {
        return $this->normalizeBaseUrl($this->settings->get('sellasist_base_url', self::DEFAULT_BASE_URL));
    }

    private function apiKey(): string
    {
        $apiKey = trim($this->settings->get('sellasist_api_key', ''));
        if ($apiKey !== '') {
            return $apiKey;
        }

        return $this->legacyApiKey();
    }

    private function ensureConfigured(): void
    {
        if ($this->apiKey() === '') {
            throw new RuntimeException('Brak klucza API Sellasist. Uzupelnij go w Administracja.');
        }
    }

    private function fallbackProduct(array $item): array
    {
        return array(
            'id' => 0,
            'sku' => trim((string) ($item['signature'] ?? ($item['symbol'] ?? ''))),
            'product_name' => trim((string) ($item['name'] ?? 'Produkt bez mapowania')),
            'localization' => '',
        );
    }

    private function bundleMultiplier(string $name): int
    {
        return strpos(mb_strtoupper($name, 'UTF-8'), '3SZT') !== false ? 3 : 1;
    }

    private function accountShort(string $account): string
    {
        $account = str_replace('Allegro.pl/', '', trim($account));
        $account = mb_strtoupper($account, 'UTF-8');
        return mb_substr($account, 0, 5, 'UTF-8');
    }

    private function carrierShort(string $delivery): string
    {
        $normalized = mb_strtolower($delivery, 'UTF-8');

        if (strpos($normalized, 'miniprzesy') !== false || strpos($normalized, 'pocztex') !== false || strpos($normalized, 'packeta') !== false) {
            return 'PP';
        }

        if (strpos($normalized, 'paczkomat') !== false || strpos($normalized, 'paczkomaty') !== false || strpos($normalized, 'inpost') !== false || strpos($normalized, 'international') !== false) {
            return 'IP';
        }

        if (strpos($normalized, 'ups') !== false) {
            return 'UPS';
        }

        if (strpos($normalized, 'dpd') !== false) {
            return 'DPD';
        }

        if (strpos($normalized, 'dhl') !== false) {
            return 'DHL';
        }

        if (strpos($normalized, 'orlen') !== false) {
            return 'ORL';
        }

        if (strpos($normalized, 'erli') !== false) {
            return 'ERLI';
        }

        return 'N/D';
    }

    private function looksLikeGlass(string $value): bool
    {
        $value = mb_strtolower($this->normalizeText($value), 'UTF-8');
        return strpos($value, 'szklo') !== false || strpos($value, 'glass') !== false;
    }

    private function normalizeText(string $value): string
    {
        $map = array(
            'ą' => 'a',
            'ć' => 'c',
            'ę' => 'e',
            'ł' => 'l',
            'ń' => 'n',
            'ó' => 'o',
            'ś' => 's',
            'ż' => 'z',
            'ź' => 'z',
            'Ą' => 'A',
            'Ć' => 'C',
            'Ę' => 'E',
            'Ł' => 'L',
            'Ń' => 'N',
            'Ó' => 'O',
            'Ś' => 'S',
            'Ż' => 'Z',
            'Ź' => 'Z',
        );

        return strtr($value, $map);
    }

    private function compareStickerRows(array $left, array $right): int
    {
        $leftLocalization = (string) ($left['product']['localization'] ?? '');
        $rightLocalization = (string) ($right['product']['localization'] ?? '');
        $compare = strcmp($leftLocalization, $rightLocalization);
        if ($compare !== 0) {
            return $compare;
        }

        $leftName = (string) ($left['product']['product_name'] ?? '');
        $rightName = (string) ($right['product']['product_name'] ?? '');
        $compare = strcmp($leftName, $rightName);
        if ($compare !== 0) {
            return $compare;
        }

        return ((int) ($left['order_id'] ?? 0)) <=> ((int) ($right['order_id'] ?? 0));
    }

    private function buildGlassNumberMap(array $glassStickers): array
    {
        $map = array();
        $index = 1;

        foreach ($glassStickers as $sticker) {
            $key = (string) ($sticker['glass_key'] ?? '');
            if ($key === '' || isset($map[$key])) {
                continue;
            }

            $map[$key] = $index;
            $index++;
        }

        return $map;
    }

    private function glassKey(array $product): string
    {
        if (!empty($product['id'])) {
            return 'id:' . (int) $product['id'];
        }

        return 'name:' . md5(
            mb_strtolower(
                trim((string) ($product['product_name'] ?? '')) . '|' . trim((string) ($product['localization'] ?? '')),
                'UTF-8'
            )
        );
    }

    private function joinNonEmpty(array $parts): string
    {
        $clean = array();
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part !== '') {
                $clean[] = $part;
            }
        }

        return implode(' ', $clean);
    }

    private function normalizeBaseUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return self::DEFAULT_BASE_URL;
        }

        if (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . ltrim($value, '/');
        }

        $parts = parse_url($value);
        if (!is_array($parts) || empty($parts['host'])) {
            return self::DEFAULT_BASE_URL;
        }

        $scheme = isset($parts['scheme']) && in_array(strtolower((string) $parts['scheme']), array('http', 'https'), true)
            ? strtolower((string) $parts['scheme'])
            : 'https';
        $host = (string) $parts['host'];
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';

        return $scheme . '://' . $host . $port;
    }

    private function legacyApiKey(): string
    {
        if (!is_file(self::LEGACY_API_FILE) || !is_readable(self::LEGACY_API_FILE)) {
            return '';
        }

        $contents = (string) @file_get_contents(self::LEGACY_API_FILE);
        if ($contents === '') {
            return '';
        }

        if (preg_match('/const\s+APIKEY\s*=\s*"([^"]+)"/', $contents, $matches) === 1) {
            return trim((string) $matches[1]);
        }

        return '';
    }
}
