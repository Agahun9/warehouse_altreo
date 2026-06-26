<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;

class MoreleStorageRepository
{
    /** @var Database */
    private $database;

    /** @var bool */
    private static $schemaEnsured = false;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        $config = Config::get('database');
        if (!isset($config['driver']) || (string) $config['driver'] !== 'mysql') {
            self::$schemaEnsured = true;
            return;
        }

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS morele_offers (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "account_id INT UNSIGNED NOT NULL DEFAULT 1,\n"
            . "account_name VARCHAR(190) DEFAULT NULL,\n"
            . "external_id VARCHAR(190) NOT NULL,\n"
            . "sku VARCHAR(190) DEFAULT NULL,\n"
            . "ean VARCHAR(32) DEFAULT NULL,\n"
            . "product_name VARCHAR(255) DEFAULT NULL,\n"
            . "quantity INT DEFAULT NULL,\n"
            . "price DECIMAL(12,2) DEFAULT NULL,\n"
            . "price_override DECIMAL(12,2) DEFAULT NULL,\n"
            . "stock_override INT DEFAULT NULL,\n"
            . "status VARCHAR(40) DEFAULT NULL,\n"
            . "status_override VARCHAR(40) DEFAULT NULL,\n"
            . "active TINYINT(1) NOT NULL DEFAULT 0,\n"
            . "payload_json LONGTEXT DEFAULT NULL,\n"
            . "remote_updated_at DATETIME DEFAULT NULL,\n"
            . "last_synced_at DATETIME DEFAULT NULL,\n"
            . "last_error_at DATETIME DEFAULT NULL,\n"
            . "last_error_message TEXT DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_morele_offers_account_external (account_id, external_id),\n"
            . "KEY idx_morele_offers_sku (sku),\n"
            . "KEY idx_morele_offers_active (active),\n"
            . "KEY idx_morele_offers_synced (last_synced_at)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS morele_offer_change_queue (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "offer_row_id INT UNSIGNED NOT NULL,\n"
            . "operation VARCHAR(80) NOT NULL,\n"
            . "payload_json LONGTEXT DEFAULT NULL,\n"
            . "status VARCHAR(40) NOT NULL DEFAULT 'pending',\n"
            . "error_message LONGTEXT DEFAULT NULL,\n"
            . "attempts INT UNSIGNED NOT NULL DEFAULT 0,\n"
            . "available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "started_at DATETIME DEFAULT NULL,\n"
            . "finished_at DATETIME DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "KEY idx_morele_queue_status (status, available_at),\n"
            . "KEY idx_morele_queue_offer (offer_row_id)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::$schemaEnsured = true;
    }

    public function listOffers(array $filters, int $page, int $perPage, string $sortBy = 'synced', string $sortDir = 'desc'): array
    {
        list($where, $params) = $this->filterSql($filters);
        $orderMap = array(
            'title' => 'offer.product_name',
            'sku' => 'offer.sku',
            'status' => 'effective_status',
            'quantity' => 'effective_quantity',
            'price' => 'effective_price',
            'queue_status' => 'queue_status',
            'synced' => 'offer.last_synced_at',
            'updated' => 'offer.updated_at',
            'id' => 'offer.id',
        );
        $orderColumn = isset($orderMap[$sortBy]) ? $orderMap[$sortBy] : $orderMap['synced'];
        $direction = strtolower($sortDir) === 'asc' ? 'ASC' : 'DESC';
        $offset = max(0, ($page - 1) * $perPage);

        return $this->database->fetchAll(
            $this->selectSql()
            . $where
            . ' ORDER BY ' . $orderColumn . ' ' . $direction . ', offer.id DESC LIMIT ' . max(1, $perPage) . ' OFFSET ' . $offset,
            $params
        );
    }

    public function countOffers(array $filters): int
    {
        list($where, $params) = $this->filterSql($filters);
        return (int) $this->database->fetchColumn('SELECT COUNT(*) FROM morele_offers offer' . $where, $params);
    }

    public function offerStats(): array
    {
        $row = $this->database->fetch(
            "SELECT COUNT(*) AS all_count,\n"
            . "SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) AS active_count,\n"
            . "SUM(CASE WHEN active = 0 THEN 1 ELSE 0 END) AS inactive_count\n"
            . "FROM morele_offers"
        );

        return array(
            'all' => (int) ($row['all_count'] ?? 0),
            'active' => (int) ($row['active_count'] ?? 0),
            'inactive' => (int) ($row['inactive_count'] ?? 0),
        );
    }

    public function removeInvalidWrapperSnapshots(): int
    {
        return $this->database->delete(
            'morele_offers',
            "(external_id LIKE 'hash:%' AND (sku IS NULL OR sku = '') AND (product_name IS NULL OR product_name = ''))"
            . " OR (sku IS NULL AND product_name IS NULL AND price IS NULL)"
        );
    }

    public function queueCounts(): array
    {
        $counts = array('pending' => 0, 'processing' => 0, 'done' => 0, 'error' => 0, 'retry' => 0);
        foreach ($this->database->fetchAll('SELECT status, COUNT(*) AS cnt FROM morele_offer_change_queue GROUP BY status') as $row) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) ($row['cnt'] ?? 0);
            }
        }
        return $counts;
    }

    public function findOfferById(int $id)
    {
        $rows = $this->database->fetchAll($this->selectSql() . ' WHERE offer.id = :id LIMIT 1', array('id' => $id));
        return isset($rows[0]) ? $rows[0] : false;
    }

    public function listOffersByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === array()) {
            return array();
        }

        $params = array();
        $placeholders = array();
        foreach ($ids as $index => $id) {
            $key = 'id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

        return $this->database->fetchAll($this->selectSql() . ' WHERE offer.id IN (' . implode(',', $placeholders) . ')', $params);
    }

    public function offerTargetsForFilters(array $filters, int $limit): array
    {
        list($where, $params) = $this->filterSql($filters);
        return $this->database->fetchAll(
            $this->selectSql() . $where . ' ORDER BY offer.id DESC LIMIT ' . max(1, min(5000, $limit)),
            $params
        );
    }

    public function upsertRemoteOfferSnapshot(array $item, string $accountName): bool
    {
        $sku = $this->firstScalar($item, array(
            'sku',
            'shop_sku',
            'shopSku',
            'product_sku',
            'productSku',
            'vendor_part_number',
            'vendorPartNumber',
            'vendorCode',
            'vendor_code',
            'producer_code',
            'externalId',
        ));
        $externalId = $this->remoteOfferIdentifier($item, $sku);
        if ($externalId === '') {
            return false;
        }

        $name = $this->firstScalar($item, array(
            'name',
            'title',
            'product_name',
            'productName',
            'productTitle',
            'product_fullname',
            'vendor_product_name',
            'morele_product_name',
        ));
        $status = strtolower($this->firstScalar($item, array('status', 'state', 'publication_status', 'publicationStatus', 'offerStatus', 'offer_status')));
        $quantity = $this->firstNumeric($item, array('quantity', 'stock', 'availableQuantity', 'available_quantity', 'available'));
        $price = $this->firstNumeric($item, array(
            'price',
            'grossPrice',
            'gross_price',
            'salePrice',
            'sale_price',
            'sale_price_brutto',
            'price_with_category_margin',
            'amount',
            'value',
        ));
        $active = $this->isActiveStatus($status, $quantity, $item);

        $existing = $this->database->fetch(
            'SELECT id FROM morele_offers WHERE account_id = 1 AND external_id = :external_id LIMIT 1',
            array('external_id' => $externalId)
        );

        $data = array(
            'account_id' => 1,
            'account_name' => $accountName,
            'external_id' => $externalId,
            'sku' => $sku !== '' ? $sku : null,
            'ean' => $this->normalizeEan($item),
            'product_name' => $name !== '' ? $name : null,
            'quantity' => $quantity,
            'price' => $price,
            'status' => $status !== '' ? $status : null,
            'active' => $active ? 1 : 0,
            'payload_json' => json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'last_synced_at' => date('Y-m-d H:i:s'),
            'last_error_at' => null,
            'last_error_message' => null,
        );

        if ($existing) {
            $this->database->update('morele_offers', $data, 'id = :id', array('id' => (int) $existing['id']));
            return true;
        }

        $this->database->insert('morele_offers', $data);
        return true;
    }

    public function enqueueOfferChanges(array $targets, string $operation, array $payload = array(), ?array $selectedIds = null, bool $dedupe = false): int
    {
        $queued = 0;
        foreach ($targets as $target) {
            $id = (int) ($target['id'] ?? 0);
            if ($id <= 0 || ($selectedIds !== null && !in_array($id, $selectedIds, true))) {
                continue;
            }
            if ($dedupe && $this->hasPendingOperation($id, $operation)) {
                continue;
            }

            $this->database->insert('morele_offer_change_queue', array(
                'offer_row_id' => $id,
                'operation' => $operation,
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status' => 'pending',
                'available_at' => date('Y-m-d H:i:s'),
            ));
            $queued++;
        }

        return $queued;
    }

    public function fetchQueueBatch(int $limit): array
    {
        $rows = $this->database->fetchAll(
            "SELECT * FROM morele_offer_change_queue\n"
            . "WHERE status IN ('pending', 'retry') AND available_at <= NOW()\n"
            . "ORDER BY available_at ASC, id ASC LIMIT " . max(1, min(100, $limit))
        );

        foreach ($rows as $index => $row) {
            $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
            $rows[$index]['payload'] = is_array($payload) ? $payload : array();
        }

        return $rows;
    }

    public function markQueueProcessing(int $id): void
    {
        $this->database->update('morele_offer_change_queue', array(
            'status' => 'processing',
            'attempts' => $this->queueAttemptsSql($id) + 1,
            'started_at' => date('Y-m-d H:i:s'),
        ), 'id = :id', array('id' => $id));
    }

    public function markQueueDone(int $id): void
    {
        $this->database->update('morele_offer_change_queue', array(
            'status' => 'done',
            'error_message' => null,
            'finished_at' => date('Y-m-d H:i:s'),
        ), 'id = :id', array('id' => $id));
    }

    public function markQueueRetry(int $id, string $message, int $delaySeconds): void
    {
        $this->database->update('morele_offer_change_queue', array(
            'status' => 'retry',
            'error_message' => $message,
            'available_at' => date('Y-m-d H:i:s', time() + max(1, $delaySeconds)),
            'finished_at' => date('Y-m-d H:i:s'),
        ), 'id = :id', array('id' => $id));
    }

    public function markQueueError(int $id, string $message): void
    {
        $this->database->update('morele_offer_change_queue', array(
            'status' => 'error',
            'error_message' => $message,
            'finished_at' => date('Y-m-d H:i:s'),
        ), 'id = :id', array('id' => $id));
    }

    public function updateOfferOverrides(int $id, array $data): void
    {
        $this->database->update('morele_offers', $data, 'id = :id', array('id' => $id));
    }

    public function markOfferSyncSuccess(int $id, array $payload, bool $active): void
    {
        $this->database->update('morele_offers', array(
            'active' => $active ? 1 : 0,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'last_synced_at' => date('Y-m-d H:i:s'),
            'last_error_at' => null,
            'last_error_message' => null,
        ), 'id = :id', array('id' => $id));
    }

    public function markOfferSyncError(int $id, string $message): void
    {
        $this->database->update('morele_offers', array(
            'last_error_at' => date('Y-m-d H:i:s'),
            'last_error_message' => $message,
        ), 'id = :id', array('id' => $id));
    }

    public function clearWholeQueue(): array
    {
        return array('removed' => $this->database->delete('morele_offer_change_queue', '1 = 1'));
    }

    public function clearQueueStatuses(bool $keepPending = true): array
    {
        $where = $keepPending ? "status IN ('done', 'error')" : "status <> 'processing'";
        return array('removed' => $this->database->delete('morele_offer_change_queue', $where));
    }

    private function selectSql(): string
    {
        return "SELECT offer.*,\n"
            . "COALESCE(offer.price_override, offer.price) AS effective_price,\n"
            . "COALESCE(offer.stock_override, offer.quantity) AS effective_quantity,\n"
            . "CASE WHEN offer.status_override IS NOT NULL AND offer.status_override <> '' THEN LOWER(offer.status_override)\n"
            . " WHEN offer.status IS NOT NULL AND offer.status <> '' THEN LOWER(offer.status)\n"
            . " WHEN offer.active = 1 THEN 'active' ELSE 'inactive' END AS effective_status,\n"
            . "(SELECT q.status FROM morele_offer_change_queue q WHERE q.offer_row_id = offer.id ORDER BY q.id DESC LIMIT 1) AS queue_status,\n"
            . "(SELECT p.id FROM pr_products_altreo p WHERE p.sku COLLATE utf8mb4_unicode_ci = offer.sku COLLATE utf8mb4_unicode_ci"
            . " OR CAST(p.id AS CHAR) COLLATE utf8mb4_unicode_ci = offer.sku COLLATE utf8mb4_unicode_ci"
            . " OR CAST(p.offerid AS CHAR) COLLATE utf8mb4_unicode_ci = offer.sku COLLATE utf8mb4_unicode_ci"
            . " OR CONCAT('ALTREO_', p.id) COLLATE utf8mb4_unicode_ci = offer.sku COLLATE utf8mb4_unicode_ci"
            . " OR CONCAT('ALTREO_', p.offerid) COLLATE utf8mb4_unicode_ci = offer.sku COLLATE utf8mb4_unicode_ci LIMIT 1) AS warehouse_product_id\n"
            . "FROM morele_offers offer";
    }

    private function filterSql(array $filters): array
    {
        $where = array();
        $params = array();
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(offer.product_name LIKE :q OR offer.external_id LIKE :q OR offer.sku LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        $sku = trim((string) ($filters['sku'] ?? ''));
        if ($sku !== '') {
            $where[] = 'offer.sku LIKE :sku';
            $params['sku'] = '%' . $sku . '%';
        }

        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        if ($status === 'active') {
            $where[] = 'offer.active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'offer.active = 0';
        }

        return array($where === array() ? '' : ' WHERE ' . implode(' AND ', $where), $params);
    }

    private function hasPendingOperation(int $offerId, string $operation): bool
    {
        return (int) $this->database->fetchColumn(
            "SELECT COUNT(*) FROM morele_offer_change_queue WHERE offer_row_id = :offer_id AND operation = :operation AND status IN ('pending', 'retry', 'processing')",
            array('offer_id' => $offerId, 'operation' => $operation)
        ) > 0;
    }

    private function queueAttemptsSql(int $id): int
    {
        return (int) $this->database->fetchColumn('SELECT attempts FROM morele_offer_change_queue WHERE id = :id', array('id' => $id));
    }

    private function normalizeEan(array $item): ?string
    {
        $ean = $this->firstScalar($item, array('ean', 'EAN', 'barcode'));
        if ($ean === '') {
            $barcodes = $this->firstScalar($item, array('barcodes'));
            if ($barcodes !== '') {
                $decoded = json_decode($barcodes, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $value) {
                        if (is_scalar($value) && trim((string) $value) !== '') {
                            $ean = trim((string) $value);
                            break;
                        }
                    }
                } else {
                    $ean = $barcodes;
                }
            }
        }

        if ($ean === '') {
            return null;
        }

        $ean = preg_replace('/[^0-9A-Za-z_-]/', '', $ean);
        return $ean !== '' ? substr($ean, 0, 32) : null;
    }

    private function firstScalar(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_scalar($data[$key])) {
                return trim((string) $data[$key]);
            }
        }

        foreach ($data as $value) {
            if (!is_array($value)) {
                continue;
            }

            $nested = $this->firstScalar($value, $keys);
            if ($nested !== '') {
                return $nested;
            }
        }

        return '';
    }

    private function firstTopLevelScalar(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_scalar($data[$key])) {
                return trim((string) $data[$key]);
            }
        }

        return '';
    }

    private function scalarAtPath(array $data, array $path): string
    {
        $current = $data;
        foreach ($path as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return '';
            }
            $current = $current[$segment];
        }

        return is_scalar($current) ? trim((string) $current) : '';
    }

    private function remoteOfferIdentifier(array $item, string $sku): string
    {
        $id = $this->firstTopLevelScalar($item, array(
            'offer_id',
            'offerId',
            'offerIdentifier',
            'marketplaceOfferId',
            'marketplace_offer_id',
            'external_offer_id',
            'externalOfferId',
            'product_id',
            'productId',
            'id',
            'external_id',
            'externalId',
            '_morele_key',
        ));
        if ($id !== '') {
            return $id;
        }

        foreach (array(
            array('offer', 'id'),
            array('offer', 'offerId'),
            array('offer', 'externalId'),
            array('offer', 'identifier'),
            array('product', 'id'),
            array('product', 'product_id'),
            array('product', 'productId'),
            array('marketplaceOffer', 'id'),
            array('marketplaceOffer', 'offerId'),
            array('saleOffer', 'id'),
            array('productOffer', 'id'),
        ) as $path) {
            $id = $this->scalarAtPath($item, $path);
            if ($id !== '') {
                return $id;
            }
        }

        if ($sku !== '') {
            return 'sku:' . $sku;
        }

        $json = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) && $json !== '' ? 'hash:' . sha1($json) : '';
    }

    private function firstNumeric(array $data, array $keys)
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_scalar($data[$key]) && is_numeric(str_replace(',', '.', (string) $data[$key]))) {
                return round((float) str_replace(',', '.', (string) $data[$key]), 2);
            }
        }

        foreach ($data as $value) {
            if (!is_array($value)) {
                continue;
            }

            $nested = $this->firstNumeric($value, $keys);
            if ($nested !== null) {
                return $nested;
            }
        }

        return null;
    }

    private function firstBool(array $data, array $keys): ?bool
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data) || is_array($data[$key])) {
                continue;
            }

            $value = $data[$key];
            if (is_bool($value)) {
                return $value;
            }

            $normalized = strtolower(trim((string) $value));
            if (in_array($normalized, array('1', 'true', 'yes', 'tak'), true)) {
                return true;
            }
            if (in_array($normalized, array('0', 'false', 'no', 'nie'), true)) {
                return false;
            }
        }

        foreach ($data as $value) {
            if (!is_array($value)) {
                continue;
            }

            $nested = $this->firstBool($value, $keys);
            if ($nested !== null) {
                return $nested;
            }
        }

        return null;
    }

    private function isActiveStatus(string $status, $quantity, array $item): bool
    {
        $explicitActive = $this->firstBool($item, array('active', 'is_active', 'isActive', 'published', 'enabled'));
        if ($explicitActive !== null) {
            return $explicitActive;
        }

        if (in_array($status, array('active', 'published', 'enabled', 'available', 'new', 'accepted', 'visible', '1'), true)) {
            return true;
        }
        if (in_array($status, array('inactive', 'ended', 'closed', 'disabled', 'archived', 'deleted', 'rejected', '0'), true)) {
            return false;
        }

        return true;
    }
}
