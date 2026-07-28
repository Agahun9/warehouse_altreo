<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;

class ErliStorageRepository
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

        $databaseName = isset($config['database']) ? (string) $config['database'] : '';
        if ($databaseName === '') {
            self::$schemaEnsured = true;
            return;
        }

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS erli_accounts (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "name VARCHAR(190) NOT NULL,\n"
            . "slug VARCHAR(190) NOT NULL,\n"
            . "api_url VARCHAR(255) NOT NULL,\n"
            . "api_key TEXT NOT NULL,\n"
            . "default_price_list_tag VARCHAR(120) DEFAULT NULL,\n"
            . "default_dispatch_days INT UNSIGNED NOT NULL DEFAULT 1,\n"
            . "default_weight_g INT UNSIGNED DEFAULT NULL,\n"
            . "is_active TINYINT(1) NOT NULL DEFAULT 1,\n"
            . "is_running TINYINT(1) NOT NULL DEFAULT 0,\n"
            . "current_cycle CHAR(36) DEFAULT NULL,\n"
            . "sync_after_external_id VARCHAR(190) DEFAULT NULL,\n"
            . "sync_started_at DATETIME DEFAULT NULL,\n"
            . "heartbeat_at DATETIME DEFAULT NULL,\n"
            . "last_sync_at DATETIME DEFAULT NULL,\n"
            . "last_error_at DATETIME DEFAULT NULL,\n"
            . "last_error_message TEXT DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_erli_accounts_slug (slug),\n"
            . "KEY idx_erli_accounts_active (is_active)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS erli_products (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "account_id INT UNSIGNED NOT NULL,\n"
            . "product_id INT UNSIGNED DEFAULT NULL,\n"
            . "external_id VARCHAR(190) NOT NULL,\n"
            . "sku VARCHAR(190) DEFAULT NULL,\n"
            . "ean VARCHAR(32) DEFAULT NULL,\n"
            . "product_name VARCHAR(255) DEFAULT NULL,\n"
            . "description MEDIUMTEXT DEFAULT NULL,\n"
            . "last_seen_cycle CHAR(36) DEFAULT NULL,\n"
            . "category_id BIGINT DEFAULT NULL,\n"
            . "category_name VARCHAR(255) DEFAULT NULL,\n"
            . "quantity INT DEFAULT NULL,\n"
            . "price DECIMAL(12,2) DEFAULT NULL,\n"
            . "images_json LONGTEXT DEFAULT NULL,\n"
            . "primary_image_url TEXT DEFAULT NULL,\n"
            . "title_override VARCHAR(255) DEFAULT NULL,\n"
            . "description_override MEDIUMTEXT DEFAULT NULL,\n"
            . "price_override DECIMAL(12,2) DEFAULT NULL,\n"
            . "stock_override INT DEFAULT NULL,\n"
            . "status_override VARCHAR(20) DEFAULT NULL,\n"
            . "remote_exists TINYINT(1) NOT NULL DEFAULT 0,\n"
            . "remote_status VARCHAR(20) DEFAULT NULL,\n"
            . "marketplace_id BIGINT DEFAULT NULL,\n"
            . "payload_json LONGTEXT DEFAULT NULL,\n"
            . "remote_created_at DATETIME DEFAULT NULL,\n"
            . "remote_updated_at DATETIME DEFAULT NULL,\n"
            . "archived_at DATETIME DEFAULT NULL,\n"
            . "last_synced_at DATETIME DEFAULT NULL,\n"
            . "last_error_at DATETIME DEFAULT NULL,\n"
            . "last_error_message TEXT DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_erli_products_account_external (account_id, external_id),\n"
            . "KEY idx_erli_products_account_id (account_id),\n"
            . "KEY idx_erli_products_product_id (product_id),\n"
            . "KEY idx_erli_products_sku (sku),\n"
            . "KEY idx_erli_products_synced (last_synced_at)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->ensureAccountsTableShape($databaseName);
        $this->ensureProductsTableShape($databaseName);


        $this->database->query(
            "CREATE TABLE IF NOT EXISTS erli_product_change_queue (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "account_id INT UNSIGNED NOT NULL,\n"
            . "product_row_id INT UNSIGNED NOT NULL,\n"
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
            . "KEY idx_erli_product_change_queue_status (status, available_at),\n"
            . "KEY idx_erli_product_change_queue_product_row (product_row_id),\n"
            . "KEY idx_erli_product_change_queue_account (account_id)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::$schemaEnsured = true;
    }

    public function allAccounts(): array
    {
        return $this->database->fetchAll('SELECT * FROM erli_accounts ORDER BY is_active DESC, name ASC, id ASC');
    }

    public function activeAccounts(): array
    {
        return $this->database->fetchAll('SELECT * FROM erli_accounts WHERE is_active = 1 ORDER BY name ASC, id ASC');
    }

    public function findAccountById(int $id)
    {
        return $this->database->fetch('SELECT * FROM erli_accounts WHERE id = :id LIMIT 1', array('id' => $id));
    }

    public function findAccountBySlug(string $slug)
    {
        return $this->database->fetch('SELECT * FROM erli_accounts WHERE slug = :slug LIMIT 1', array('slug' => $slug));
    }

    public function saveAccount(array $data, ?int $accountId = null): int
    {
        if ($accountId !== null && $accountId > 0) {
            $this->database->update('erli_accounts', $data, 'id = :id', array('id' => $accountId));
            return $accountId;
        }

        return (int) $this->database->insert('erli_accounts', $data);
    }

    public function markAccountSyncSuccess(int $accountId): void
    {
        $this->database->update('erli_accounts', array(
            'is_running' => 0,
            'current_cycle' => null,
            'sync_after_external_id' => null,
            'sync_started_at' => null,
            'heartbeat_at' => null,
            'last_sync_at' => date('Y-m-d H:i:s'),
            'last_error_at' => null,
            'last_error_message' => null,
        ), 'id = :id', array('id' => $accountId));
    }

    public function markAccountSyncError(int $accountId, string $message): void
    {
        $this->database->update('erli_accounts', array(
            'is_running' => 0,
            'heartbeat_at' => null,
            'last_error_at' => date('Y-m-d H:i:s'),
            'last_error_message' => $message,
        ), 'id = :id', array('id' => $accountId));
    }

    public function markAccountSyncStarted(int $accountId, string $cycle, ?string $afterExternalId): void
    {
        $now = date('Y-m-d H:i:s');
        $this->database->update('erli_accounts', array(
            'is_running' => 1,
            'current_cycle' => $cycle,
            'sync_after_external_id' => $afterExternalId,
            'sync_started_at' => $now,
            'heartbeat_at' => $now,
            'last_error_at' => null,
            'last_error_message' => null,
        ), 'id = :id', array('id' => $accountId));
    }

    public function markAccountSyncProgress(int $accountId, string $cycle, ?string $afterExternalId): void
    {
        $this->database->update('erli_accounts', array(
            'is_running' => 1,
            'current_cycle' => $cycle,
            'sync_after_external_id' => $afterExternalId,
            'heartbeat_at' => date('Y-m-d H:i:s'),
        ), 'id = :id', array('id' => $accountId));
    }

    public function markAccountSyncPaused(int $accountId, string $cycle, ?string $afterExternalId): void
    {
        $this->database->update('erli_accounts', array(
            'is_running' => 0,
            'current_cycle' => $cycle,
            'sync_after_external_id' => $afterExternalId,
            'heartbeat_at' => null,
        ), 'id = :id', array('id' => $accountId));
    }

    public function upsertRemoteProductSnapshot(int $accountId, array $remoteProduct, ?string $cycle = null): void
    {
        $normalized = $this->normalizeRemoteProduct($accountId, $remoteProduct, $cycle);
        if ($normalized['external_id'] === '') {
            return;
        }

        $existing = $this->database->fetch(
            'SELECT id FROM erli_products WHERE account_id = :account_id AND external_id = :external_id LIMIT 1',
            array(
                'account_id' => $accountId,
                'external_id' => $normalized['external_id'],
            )
        );

        if ($existing) {
            $this->database->update('erli_products', $normalized, 'id = :id', array('id' => (int) $existing['id']));
            return;
        }

        $this->database->insert('erli_products', $normalized);
    }

    public function deleteSnapshotsMissingExternalIds(int $accountId, array $externalIds): int
    {
        $externalIds = array_values(array_unique(array_filter(array_map('strval', $externalIds), static function (string $value): bool {
            return trim($value) !== '';
        })));

        if ($externalIds === array()) {
            return (int) $this->database->delete('erli_products', 'account_id = :account_id', array('account_id' => $accountId));
        }

        $params = array('account_id' => $accountId);
        $placeholders = $this->buildStringPlaceholders('external_keep_id', $externalIds, $params);
        $sql = 'DELETE FROM erli_products WHERE account_id = :account_id AND external_id NOT IN (' . implode(', ', $placeholders) . ')';

        return (int) $this->database->query($sql, $params)->rowCount();
    }

    public function deleteSnapshotsOutsideCycle(int $accountId, string $cycle): int
    {
        return (int) $this->database->query(
            'DELETE FROM erli_products WHERE account_id = :account_id AND (last_seen_cycle IS NULL OR last_seen_cycle <> :cycle)',
            array(
                'account_id' => $accountId,
                'cycle' => $cycle,
            )
        )->rowCount();
    }

    public function countProducts(array $filters = array()): int
    {
        $params = array();
        $where = $this->buildProductWhere($filters, $params);
        $sql = 'SELECT COUNT(*) FROM erli_products products'
            . ' INNER JOIN erli_accounts accounts ON accounts.id = products.account_id'
            . ' ' . $this->warehouseJoinSql();

        return (int) $this->database->fetchColumn($sql . $where, $params);
    }

    public function productStats(): array
    {
        $rows = $this->database->fetchAll(
            'SELECT'
            . ' COUNT(*) AS all_count,'
            . ' SUM(CASE WHEN ' . $this->effectiveStatusSql() . ' = "active" THEN 1 ELSE 0 END) AS active_count,'
            . ' SUM(CASE WHEN ' . $this->effectiveStatusSql() . ' = "inactive" THEN 1 ELSE 0 END) AS inactive_count,'
            . ' SUM(CASE WHEN warehouse.id IS NOT NULL THEN 1 ELSE 0 END) AS linked_count,'
            . ' SUM(CASE WHEN warehouse.id IS NULL THEN 1 ELSE 0 END) AS unlinked_count'
            . ' FROM erli_products products'
            . ' INNER JOIN erli_accounts accounts ON accounts.id = products.account_id'
            . ' ' . $this->warehouseJoinSql()
        );

        $row = isset($rows[0]) && is_array($rows[0]) ? $rows[0] : array();

        return array(
            'all' => (int) ($row['all_count'] ?? 0),
            'active' => (int) ($row['active_count'] ?? 0),
            'inactive' => (int) ($row['inactive_count'] ?? 0),
            'linked' => (int) ($row['linked_count'] ?? 0),
            'unlinked' => (int) ($row['unlinked_count'] ?? 0),
        );
    }

    public function listProducts(array $filters, int $page, int $perPage, string $sortBy = 'synced', string $sortDir = 'desc'): array
    {
        $params = array();
        $where = $this->buildProductWhere($filters, $params);
        $offset = max(0, ($page - 1) * $perPage);
        $sortSql = $this->buildProductSort($sortBy, $sortDir);

        $idSql = 'SELECT products.id FROM erli_products products'
            . ' INNER JOIN erli_accounts accounts ON accounts.id = products.account_id'
            . ' ' . $this->warehouseJoinSql()
            . $where
            . ' ORDER BY ' . $sortSql
            . ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;

        $idRows = $this->database->fetchAll($idSql, $params);
        if ($idRows === array()) {
            return array();
        }

        $productIds = array_map(static function (array $row): int {
            return (int) ($row['id'] ?? 0);
        }, $idRows);

        $detailParams = array();
        $placeholders = $this->buildIntegerPlaceholders('product_id', $productIds, $detailParams);
        $rows = $this->database->fetchAll(
            'SELECT products.*,'
            . ' accounts.name AS account_name, accounts.slug AS account_slug, accounts.api_url, accounts.api_key,'
            . ' accounts.default_price_list_tag, accounts.default_dispatch_days, accounts.default_weight_g,'
            . ' warehouse.id AS warehouse_product_id, warehouse.sku AS warehouse_sku, warehouse.product_name AS warehouse_product_name,'
            . ' COALESCE(shared_stock_groups.quantity, warehouse.quantity) AS warehouse_quantity,'
            . ' COALESCE(shared_stock_groups.localization, warehouse.localization) AS warehouse_localization'
            . ' FROM erli_products products'
            . ' INNER JOIN erli_accounts accounts ON accounts.id = products.account_id'
            . ' ' . $this->warehouseJoinSql()
            . ' WHERE products.id IN (' . implode(', ', $placeholders) . ')',
            $detailParams
        );

        $rowsById = array();
        foreach ($rows as $row) {
            $row = $this->decorateProductRow($row);
            $rowsById[(int) ($row['id'] ?? 0)] = $row;
        }

        $queueMeta = $this->latestQueueMetaForProducts($productIds);
        $orderedRows = array();

        foreach ($productIds as $productId) {
            if (!isset($rowsById[$productId])) {
                continue;
            }

            $row = $rowsById[$productId];
            $row['queue_meta'] = $queueMeta[$productId] ?? array();
            $orderedRows[] = $row;
        }

        return $orderedRows;
    }

    public function findProductRowById(int $id)
    {
        $rows = $this->listProducts(array('id' => (string) $id), 1, 1, 'id', 'desc');
        return isset($rows[0]) ? $rows[0] : null;
    }

    public function listProductsByIds(array $productRowIds): array
    {
        $productRowIds = array_values(array_unique(array_filter(array_map('intval', $productRowIds))));
        if ($productRowIds === array()) {
            return array();
        }

        $params = array();
        $placeholders = $this->buildIntegerPlaceholders('product_row_lookup', $productRowIds, $params);
        $rows = $this->database->fetchAll(
            'SELECT products.id FROM erli_products products WHERE products.id IN (' . implode(', ', $placeholders) . ') ORDER BY products.id DESC',
            $params
        );

        if ($rows === array()) {
            return array();
        }

        $orderedIds = array_map(static function (array $row): int {
            return (int) ($row['id'] ?? 0);
        }, $rows);

        $result = array();
        foreach ($orderedIds as $productRowId) {
            $row = $this->findProductRowById($productRowId);
            if ($row) {
                $result[] = $row;
            }
        }

        return $result;
    }

    public function productTargetsForFilters(array $filters, int $limit = 5000): array
    {
        return $this->listProducts($filters, 1, max(1, min(5000, $limit)), 'id', 'desc');
    }

    public function queueCounts(): array
    {
        $rows = $this->database->fetchAll(
            'SELECT queue_latest.status, COUNT(*) AS total'
            . ' FROM erli_product_change_queue queue_latest'
            . ' INNER JOIN ('
            . '   SELECT product_row_id, MAX(id) AS latest_id'
            . '   FROM erli_product_change_queue'
            . '   GROUP BY product_row_id'
            . ' ) queue_max ON queue_max.latest_id = queue_latest.id'
            . ' INNER JOIN erli_products products ON products.id = queue_latest.product_row_id'
            . ' INNER JOIN erli_accounts accounts ON accounts.id = products.account_id'
            . ' WHERE accounts.is_active = 1'
            . ' GROUP BY queue_latest.status'
        );

        $result = array(
            'pending' => 0,
            'processing' => 0,
            'done' => 0,
            'error' => 0,
            'retry' => 0,
        );

        foreach ($rows as $row) {
            $status = strtolower((string) ($row['status'] ?? ''));
            if (isset($result[$status])) {
                $result[$status] = (int) ($row['total'] ?? 0);
            }
        }

        return $result;
    }

    public function enqueueProductChanges(array $targets, string $operation, array $payload, ?string $availableAt = null, bool $skipExistingActive = false): int
    {
        if ($targets === array()) {
            return 0;
        }

        $queued = 0;
        $availableAt = $availableAt !== null && trim($availableAt) !== '' ? trim($availableAt) : date('Y-m-d H:i:s');

        foreach ($targets as $target) {
            $productRowId = isset($target['id']) ? (int) $target['id'] : 0;
            $accountId = isset($target['account_id']) ? (int) $target['account_id'] : 0;
            if ($productRowId <= 0 || $accountId <= 0) {
                continue;
            }
            if ($skipExistingActive && $this->hasActiveQueueEntry($productRowId, $operation)) {
                continue;
            }

            $this->database->insert('erli_product_change_queue', array(
                'account_id' => $accountId,
                'product_row_id' => $productRowId,
                'operation' => $operation,
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status' => 'pending',
                'available_at' => $availableAt,
            ));
            $queued++;
        }

        return $queued;
    }

    private function hasActiveQueueEntry(int $productRowId, string $operation): bool
    {
        return (int) $this->database->fetchColumn(
            'SELECT COUNT(*) FROM erli_product_change_queue'
            . ' WHERE product_row_id = :product_row_id'
            . ' AND operation = :operation'
            . ' AND status IN ("pending", "retry", "processing")',
            array(
                'product_row_id' => $productRowId,
                'operation' => $operation,
            )
        ) > 0;
    }

    public function fetchQueueBatch(int $limit = 100, ?int $accountId = null): array
    {
        $params = array('now' => date('Y-m-d H:i:s'));
        $sql = 'SELECT queue.*, products.external_id, products.sku, products.product_name'
            . ' FROM erli_product_change_queue queue'
            . ' INNER JOIN erli_products products ON products.id = queue.product_row_id'
            . ' WHERE queue.status IN ("pending", "retry") AND queue.available_at <= :now';

        if ($accountId !== null) {
            $sql .= ' AND queue.account_id = :account_id';
            $params['account_id'] = $accountId;
        }

        $sql .= ' ORDER BY queue.id ASC LIMIT ' . max(1, min(1000, $limit));
        $rows = $this->database->fetchAll($sql, $params);

        foreach ($rows as $index => $row) {
            $decoded = json_decode((string) ($row['payload_json'] ?? ''), true);
            $rows[$index]['payload'] = is_array($decoded) ? $decoded : array();
        }

        return $rows;
    }

    public function markQueueProcessing(int $queueId): void
    {
        $this->database->update(
            'erli_product_change_queue',
            array(
                'status' => 'processing',
                'started_at' => date('Y-m-d H:i:s'),
                'finished_at' => null,
                'error_message' => null,
            ),
            'id = :id',
            array('id' => $queueId)
        );
    }

    public function markQueueDone(int $queueId): void
    {
        $this->database->update(
            'erli_product_change_queue',
            array(
                'status' => 'done',
                'finished_at' => date('Y-m-d H:i:s'),
                'error_message' => null,
            ),
            'id = :id',
            array('id' => $queueId)
        );
    }

    public function markQueueRetry(int $queueId, string $message, int $delaySeconds = 60): void
    {
        $row = $this->database->fetch('SELECT attempts FROM erli_product_change_queue WHERE id = :id LIMIT 1', array('id' => $queueId));
        $attempts = isset($row['attempts']) ? (int) $row['attempts'] + 1 : 1;

        $this->database->update(
            'erli_product_change_queue',
            array(
                'status' => 'retry',
                'attempts' => $attempts,
                'error_message' => $message,
                'available_at' => date('Y-m-d H:i:s', time() + max(5, $delaySeconds)),
            ),
            'id = :id',
            array('id' => $queueId)
        );
    }

    public function markQueueError(int $queueId, string $message): void
    {
        $row = $this->database->fetch('SELECT attempts FROM erli_product_change_queue WHERE id = :id LIMIT 1', array('id' => $queueId));
        $attempts = isset($row['attempts']) ? (int) $row['attempts'] + 1 : 1;

        $this->database->update(
            'erli_product_change_queue',
            array(
                'status' => 'error',
                'attempts' => $attempts,
                'error_message' => $message,
                'finished_at' => date('Y-m-d H:i:s'),
            ),
            'id = :id',
            array('id' => $queueId)
        );
    }

    public function clearWholeQueue(): array
    {
        $removed = $this->database->delete('erli_product_change_queue', '1=1', array());
        return array('removed' => (int) $removed);
    }

    public function clearQueueStatuses(bool $keepPending = true): array
    {
        $where = $keepPending ? 'status NOT IN ("pending", "retry", "processing")' : '1=1';
        $removed = $this->database->delete('erli_product_change_queue', $where, array());
        return array('removed' => (int) $removed);
    }

    public function clearQueueForProducts(array $productRowIds): int
    {
        $productRowIds = array_values(array_unique(array_filter(array_map('intval', $productRowIds))));
        if ($productRowIds === array()) {
            return 0;
        }

        $params = array();
        $placeholders = $this->buildIntegerPlaceholders('product_row_id', $productRowIds, $params);
        $sql = 'DELETE FROM erli_product_change_queue WHERE product_row_id IN (' . implode(', ', $placeholders) . ')';
        return (int) $this->database->query($sql, $params)->rowCount();
    }

    public function removeProductsByIds(array $productRowIds): int
    {
        $productRowIds = array_values(array_unique(array_filter(array_map('intval', $productRowIds))));
        if ($productRowIds === array()) {
            return 0;
        }

        $params = array();
        $placeholders = $this->buildIntegerPlaceholders('product_delete_id', $productRowIds, $params);
        $sql = 'DELETE FROM erli_products WHERE id IN (' . implode(', ', $placeholders) . ')';
        return (int) $this->database->query($sql, $params)->rowCount();
    }

    public function latestQueueMetaForProducts(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($productIds === array()) {
            return array();
        }

        $params = array();
        $placeholders = $this->buildIntegerPlaceholders('queue_meta_product_id', $productIds, $params);
        $rows = $this->database->fetchAll(
            'SELECT queue.*'
            . ' FROM erli_product_change_queue queue'
            . ' INNER JOIN ('
            . '   SELECT product_row_id, MAX(id) AS latest_id'
            . '   FROM erli_product_change_queue'
            . '   WHERE product_row_id IN (' . implode(', ', $placeholders) . ')'
            . '   GROUP BY product_row_id'
            . ' ) latest ON latest.latest_id = queue.id',
            $params
        );

        $result = array();
        foreach ($rows as $row) {
            $productId = isset($row['product_row_id']) ? (int) ($row['product_row_id'] ?? 0) : 0;
            if ($productId <= 0) {
                continue;
            }

            $decoded = json_decode((string) ($row['payload_json'] ?? ''), true);
            $row['payload'] = is_array($decoded) ? $decoded : array();
            $result[$productId] = $this->normalizeQueueMetaForView($row);
        }

        return $result;
    }

    public function updateProductOverrides(int $productRowId, array $changes): void
    {
        if ($changes === array()) {
            return;
        }

        $allowed = array('title_override', 'description_override', 'price_override', 'stock_override', 'status_override');
        $payload = array();

        foreach ($allowed as $key) {
            if (array_key_exists($key, $changes)) {
                $payload[$key] = $changes[$key];
            }
        }

        if ($payload === array()) {
            return;
        }

        $this->database->update('erli_products', $payload, 'id = :id', array('id' => $productRowId));
    }

    public function markProductSyncSuccess(int $productRowId, array $payload, string $status, bool $remoteExists = true): void
    {
        $this->database->update('erli_products', array(
            'remote_exists' => $remoteExists ? 1 : 0,
            'remote_status' => $status,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'last_synced_at' => date('Y-m-d H:i:s'),
            'last_error_at' => null,
            'last_error_message' => null,
        ), 'id = :id', array('id' => $productRowId));
    }

    public function markProductSyncError(int $productRowId, string $message): void
    {
        $this->database->update('erli_products', array(
            'last_error_at' => date('Y-m-d H:i:s'),
            'last_error_message' => $message,
        ), 'id = :id', array('id' => $productRowId));
    }

    private function ensureProductsTableShape(string $databaseName): void
    {
        $existingColumns = $this->database->fetchAll(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table',
            array('schema' => $databaseName, 'table' => 'erli_products')
        );
        $existingColumnMap = array_fill_keys(array_column($existingColumns, 'COLUMN_NAME'), true);

        $columnDefinitions = array(
            'product_id' => 'INT UNSIGNED DEFAULT NULL',
            'stock_override' => 'INT DEFAULT NULL',
            'marketplace_id' => 'BIGINT DEFAULT NULL',
            'remote_created_at' => 'DATETIME DEFAULT NULL',
            'remote_updated_at' => 'DATETIME DEFAULT NULL',
            'archived_at' => 'DATETIME DEFAULT NULL',
            'last_seen_cycle' => 'CHAR(36) DEFAULT NULL',
        );

        foreach ($columnDefinitions as $column => $definition) {
            if (!isset($existingColumnMap[$column])) {
                $this->database->query('ALTER TABLE erli_products ADD COLUMN ' . $column . ' ' . $definition);
            }
        }

        if (isset($existingColumnMap['product_id'])) {
            $this->database->query('ALTER TABLE erli_products MODIFY product_id INT UNSIGNED DEFAULT NULL');
        }

        $indexRows = $this->database->fetchAll(
            'SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table',
            array('schema' => $databaseName, 'table' => 'erli_products')
        );
        $indexes = array_fill_keys(array_column($indexRows, 'INDEX_NAME'), true);

        if (isset($indexes['ux_erli_products_account_product'])) {
            $this->database->query('ALTER TABLE erli_products DROP INDEX ux_erli_products_account_product');
        }

        if (!isset($indexes['ux_erli_products_account_external'])) {
            $this->database->query('ALTER TABLE erli_products ADD UNIQUE KEY ux_erli_products_account_external (account_id, external_id)');
        }

        if (!isset($indexes['idx_erli_products_product_id'])) {
            $this->database->query('ALTER TABLE erli_products ADD KEY idx_erli_products_product_id (product_id)');
        }

        // NOTE: sku may still be utf8mb4_general_ci on older installs, which blocks index use
        // for the computers-products marketplace filter (see ComputersController::
        // computerProductFilterSql). Fixed manually via fixSkuCollation() / the CLI script
        // php/scripts/fix_marketplace_sku_collation.php - deliberately NOT run automatically
        // here, since a collation change is an ALGORITHM=COPY ALTER (full table rebuild) that
        // shared hosting can kill mid-flight ("MySQL server has gone away") if triggered from
        // a web request.
    }

    public function fixSkuCollation(string $collation = 'utf8mb4_unicode_ci'): array
    {
        return array_values(array_filter(array(
            $this->ensureColumnCollation('erli_products', 'sku', 'VARCHAR(190) DEFAULT NULL', $collation),
        )));
    }

    private function ensureColumnCollation(string $table, string $column, string $columnDefinition, string $collation = 'utf8mb4_unicode_ci'): ?string
    {
        $currentCollation = (string) $this->database->fetchColumn(
            'SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name',
            array(
                'table_name' => $table,
                'column_name' => $column,
            )
        );

        if ($currentCollation === '' || strcasecmp($currentCollation, $collation) === 0) {
            return null;
        }

        $this->database->query('ALTER TABLE ' . $table . ' MODIFY COLUMN ' . $column . ' ' . $columnDefinition . ' COLLATE ' . $collation);

        return $table . '.' . $column . ': ' . $currentCollation . ' -> ' . $collation;
    }

    private function ensureAccountsTableShape(string $databaseName): void
    {
        $existingColumns = $this->database->fetchAll(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table',
            array('schema' => $databaseName, 'table' => 'erli_accounts')
        );
        $existingColumnMap = array_fill_keys(array_column($existingColumns, 'COLUMN_NAME'), true);

        $columnDefinitions = array(
            'is_running' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'current_cycle' => 'CHAR(36) DEFAULT NULL',
            'sync_after_external_id' => 'VARCHAR(190) DEFAULT NULL',
            'sync_started_at' => 'DATETIME DEFAULT NULL',
            'heartbeat_at' => 'DATETIME DEFAULT NULL',
        );

        foreach ($columnDefinitions as $column => $definition) {
            if (!isset($existingColumnMap[$column])) {
                $this->database->query('ALTER TABLE erli_accounts ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
    }

    private function normalizeRemoteProduct(int $accountId, array $remoteProduct, ?string $cycle = null): array
    {
        $externalId = trim((string) ($remoteProduct['externalId'] ?? ''));
        $category = $this->extractCategoryMeta($remoteProduct);
        $images = $this->extractImageUrls(isset($remoteProduct['images']) && is_array($remoteProduct['images']) ? $remoteProduct['images'] : array());
        $status = $this->normalizeRemoteStatus($remoteProduct);

        return array(
            'account_id' => $accountId,
            'product_id' => null,
            'external_id' => $externalId,
            'sku' => $this->stringOrNull($remoteProduct['sku'] ?? null),
            'ean' => $this->stringOrNull($remoteProduct['ean'] ?? null),
            'product_name' => $this->stringOrNull($remoteProduct['name'] ?? null),
            'description' => null,
            'last_seen_cycle' => $cycle,
            'category_id' => $category['id'],
            'category_name' => $category['name'],
            'quantity' => isset($remoteProduct['stock']) ? (int) $remoteProduct['stock'] : null,
            // ERLI transfers prices as an integer number of grosze.
            'price' => isset($remoteProduct['price']) ? round(((float) $remoteProduct['price']) / 100, 2) : null,
            'images_json' => json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'primary_image_url' => isset($images[0]) ? (string) $images[0] : null,
            'remote_exists' => 1,
            'remote_status' => $status,
            'marketplace_id' => isset($remoteProduct['marketplaceId']) ? (int) $remoteProduct['marketplaceId'] : null,
            'payload_json' => json_encode($remoteProduct, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'remote_created_at' => $this->dateTimeOrNull($remoteProduct['created'] ?? null),
            'remote_updated_at' => $this->dateTimeOrNull($remoteProduct['updated'] ?? null),
            'archived_at' => $this->dateTimeOrNull($remoteProduct['archivedAt'] ?? null),
            'last_synced_at' => date('Y-m-d H:i:s'),
            'last_error_at' => null,
            'last_error_message' => null,
        );
    }

    private function decorateProductRow(array $row): array
    {
        $decoded = json_decode((string) ($row['images_json'] ?? ''), true);
        $row['images'] = is_array($decoded) ? $decoded : array();
        $row['image_count'] = count($row['images']);
        $row['effective_title'] = trim((string) ($row['title_override'] ?? '')) !== ''
            ? (string) $row['title_override']
            : (string) ($row['product_name'] ?? '');
        $row['effective_description'] = trim((string) ($row['description_override'] ?? '')) !== ''
            ? (string) $row['description_override']
            : (string) ($row['description'] ?? '');
        if ($row['price_override'] !== null && $row['price_override'] !== '') {
            $row['effective_price'] = (float) $row['price_override'];
        } else {
            $storedPrice = (float) ($row['price'] ?? 0);
            $remotePayload = json_decode((string) ($row['payload_json'] ?? ''), true);
            $remotePriceInGrosze = is_array($remotePayload) && isset($remotePayload['price'])
                ? (float) $remotePayload['price']
                : null;

            // Compatibility for rows synchronized before prices were normalized to PLN.
            $row['effective_price'] = $remotePriceInGrosze !== null && abs($storedPrice - $remotePriceInGrosze) < 0.001
                ? round($storedPrice / 100, 2)
                : $storedPrice;
        }
        $row['effective_quantity'] = $row['stock_override'] !== null && $row['stock_override'] !== ''
            ? (int) $row['stock_override']
            : (int) ($row['quantity'] ?? 0);
        $row['effective_status'] = trim((string) ($row['status_override'] ?? '')) !== ''
            ? strtolower(trim((string) $row['status_override']))
            : (trim((string) ($row['remote_status'] ?? '')) !== ''
                ? strtolower(trim((string) $row['remote_status']))
                : ($row['effective_quantity'] > 0 ? 'active' : 'inactive'));

        return $row;
    }

    private function normalizeRemoteStatus(array $remoteProduct): ?string
    {
        $candidates = array(
            $remoteProduct['status'] ?? null,
            $remoteProduct['state'] ?? null,
            isset($remoteProduct['publication']) && is_array($remoteProduct['publication']) ? ($remoteProduct['publication']['status'] ?? null) : null,
            isset($remoteProduct['offer']) && is_array($remoteProduct['offer']) ? ($remoteProduct['offer']['status'] ?? null) : null,
            isset($remoteProduct['product']) && is_array($remoteProduct['product']) ? ($remoteProduct['product']['status'] ?? null) : null,
        );

        foreach ($candidates as $candidate) {
            $status = strtolower(trim((string) $candidate));
            if ($status === '') {
                continue;
            }

            if (in_array($status, array('active', 'published', 'enabled', 'visible', 'available', 'on', 'true', '1'), true)) {
                return 'active';
            }

            if (in_array($status, array('inactive', 'disabled', 'hidden', 'archived', 'deleted', 'unavailable', 'off', 'false', '0'), true)) {
                return 'inactive';
            }
        }

        if (array_key_exists('archived', $remoteProduct)) {
            return !empty($remoteProduct['archived']) ? 'inactive' : null;
        }

        return null;
    }

    private function normalizeQueueMetaForView(array $queueMeta): array
    {
        if ($queueMeta === array()) {
            return array(
                'has_queue_entry' => false,
                'status' => '',
                'status_label' => '',
                'badge_class' => 'text-bg-light border',
                'row_class' => '',
                'operation' => '',
                'error_message' => '',
                'attempts' => 0,
                'updated_at' => '',
            );
        }

        $status = strtolower(trim((string) ($queueMeta['status'] ?? '')));
        $map = array(
            'pending' => array('label' => 'W kolejce', 'badge_class' => 'text-bg-warning', 'row_class' => 'table-warning'),
            'processing' => array('label' => 'Przetwarzanie', 'badge_class' => 'text-bg-info', 'row_class' => 'table-info'),
            'done' => array('label' => 'Zrobiono', 'badge_class' => 'text-bg-success', 'row_class' => 'table-success'),
            'retry' => array('label' => 'Retry', 'badge_class' => 'text-bg-warning', 'row_class' => 'table-warning'),
            'error' => array('label' => 'Blad', 'badge_class' => 'text-bg-danger', 'row_class' => 'table-danger'),
        );

        $meta = isset($map[$status]) ? $map[$status] : array(
            'label' => strtoupper($status),
            'badge_class' => 'text-bg-light border',
            'row_class' => '',
        );

        return array(
            'has_queue_entry' => true,
            'status' => $status,
            'status_label' => (string) $meta['label'],
            'badge_class' => (string) $meta['badge_class'],
            'row_class' => (string) $meta['row_class'],
            'operation' => trim((string) ($queueMeta['operation'] ?? '')),
            'error_message' => trim((string) ($queueMeta['error_message'] ?? '')),
            'attempts' => isset($queueMeta['attempts']) ? (int) $queueMeta['attempts'] : 0,
            'updated_at' => trim((string) ($queueMeta['updated_at'] ?? $queueMeta['finished_at'] ?? $queueMeta['started_at'] ?? $queueMeta['available_at'] ?? '')),
        );
    }

    private function buildProductWhere(array $filters, array &$params): string
    {
        $whereParts = array(' WHERE 1=1');

        $id = trim((string) ($filters['id'] ?? ''));
        if ($id !== '' && ctype_digit($id)) {
            $whereParts[] = ' AND products.id = :id';
            $params['id'] = (int) $id;
        }

        $accountId = trim((string) ($filters['account_id'] ?? ''));
        if ($accountId !== '' && ctype_digit($accountId) && (int) $accountId > 0) {
            $whereParts[] = ' AND products.account_id = :account_id';
            $params['account_id'] = (int) $accountId;
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $whereParts[] = ' AND ('
                . 'products.external_id LIKE :query_external_id'
                . ' OR products.sku LIKE :query_sku'
                . ' OR products.product_name LIKE :query_product_name'
                . ' OR products.description LIKE :query_description'
                . ' OR products.category_name LIKE :query_category_name'
                . ')';
            $queryLike = '%' . $query . '%';
            $params['query_external_id'] = $queryLike;
            $params['query_sku'] = $queryLike;
            $params['query_product_name'] = $queryLike;
            $params['query_description'] = $queryLike;
            $params['query_category_name'] = $queryLike;
        }

        $sku = trim((string) ($filters['sku'] ?? ''));
        if ($sku !== '') {
            $whereParts[] = ' AND (products.sku LIKE :sku_product OR products.external_id LIKE :sku_external)';
            $skuLike = '%' . $sku . '%';
            $params['sku_product'] = $skuLike;
            $params['sku_external'] = $skuLike;
        }

        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        if (in_array($status, array('active', 'inactive'), true)) {
            $whereParts[] = ' AND ' . $this->effectiveStatusSql() . ' = :status';
            $params['status'] = $status;
        }

        $queueStatus = strtolower(trim((string) ($filters['queue_status'] ?? '')));
        if (in_array($queueStatus, array('pending', 'retry', 'error', 'done', 'processing'), true)) {
            $whereParts[] = ' AND EXISTS ('
                . ' SELECT 1 FROM erli_product_change_queue queue_latest'
                . ' WHERE queue_latest.product_row_id = products.id'
                . '   AND queue_latest.id = (SELECT MAX(queue_max.id) FROM erli_product_change_queue queue_max WHERE queue_max.product_row_id = products.id)'
                . '   AND queue_latest.status = :queue_status'
                . ' )';
            $params['queue_status'] = $queueStatus;
        }

        $errorQuery = trim((string) ($filters['error_query'] ?? ''));
        if ($errorQuery !== '') {
            $whereParts[] = ' AND EXISTS ('
                . ' SELECT 1 FROM erli_product_change_queue queue_latest_error'
                . ' WHERE queue_latest_error.product_row_id = products.id'
                . '   AND queue_latest_error.id = (SELECT MAX(queue_max_error.id) FROM erli_product_change_queue queue_max_error WHERE queue_max_error.product_row_id = products.id)'
                . '   AND queue_latest_error.error_message IS NOT NULL'
                . '   AND queue_latest_error.error_message LIKE :error_query'
                . ' )';
            $params['error_query'] = '%' . $errorQuery . '%';
        }

        $linked = trim((string) ($filters['linked'] ?? ''));
        if ($linked === '1') {
            $whereParts[] = ' AND warehouse.id IS NOT NULL';
        } elseif ($linked === '0') {
            $whereParts[] = ' AND warehouse.id IS NULL';
        }

        $warehouseQuantityFrom = trim((string) ($filters['warehouse_quantity_from'] ?? ''));
        $warehouseQuantityTo = trim((string) ($filters['warehouse_quantity_to'] ?? ''));
        if ($warehouseQuantityFrom !== '' || $warehouseQuantityTo !== '') {
            $this->appendQuantityRangeBounds(
                $whereParts,
                $params,
                $warehouseQuantityFrom,
                $warehouseQuantityTo,
                'COALESCE(shared_stock_groups.quantity, warehouse.quantity)',
                'warehouse_quantity'
            );
        }

        return implode('', $whereParts);
    }

    private function buildProductSort(string $sortBy, string $sortDir): string
    {
        $sortDir = strtolower($sortDir) === 'asc' ? 'ASC' : 'DESC';
        $map = array(
            'images' => 'JSON_LENGTH(COALESCE(products.images_json, "[]"))',
            'id' => 'products.id',
            'account' => 'accounts.name',
            'title' => 'COALESCE(NULLIF(products.title_override, ""), products.product_name)',
            'sku' => 'products.sku',
            'category' => 'products.category_name',
            'status' => $this->effectiveStatusSql(),
            'quantity' => 'COALESCE(products.stock_override, products.quantity)',
            'warehouse_quantity' => 'COALESCE(shared_stock_groups.quantity, warehouse.quantity)',
            'price' => 'COALESCE(products.price_override, products.price)',
            'synced' => 'products.last_synced_at',
            'updated' => 'COALESCE(products.remote_updated_at, products.updated_at)',
            'queue_status' => '(SELECT queue_latest.status FROM erli_product_change_queue queue_latest WHERE queue_latest.product_row_id = products.id ORDER BY queue_latest.id DESC LIMIT 1)',
        );

        $column = isset($map[$sortBy]) ? $map[$sortBy] : $map['synced'];
        return $column . ' ' . $sortDir . ', products.id DESC';
    }

    private function warehouseJoinSql(): string
    {
        return ' LEFT JOIN ('
            . ' SELECT warehouse_source.*'
            . ' FROM products warehouse_source'
            . ' INNER JOIN ('
            . '   SELECT sku, MAX(id) AS max_id'
            . '   FROM products'
            . '   WHERE deleted_at IS NULL AND sku IS NOT NULL AND sku <> ""'
            . '   GROUP BY sku'
            . ' ) warehouse_latest ON warehouse_latest.max_id = warehouse_source.id'
            . ' ) warehouse ON warehouse.sku = products.sku'
            . ' LEFT JOIN shared_stock_groups ON shared_stock_groups.id = warehouse.shared_stock_group_id';
    }

    private function effectiveStatusSql(): string
    {
        return '(CASE'
            . ' WHEN products.status_override IS NOT NULL AND products.status_override <> "" THEN LOWER(products.status_override)'
            . ' WHEN products.remote_status IS NOT NULL AND products.remote_status <> "" THEN LOWER(products.remote_status)'
            . ' WHEN COALESCE(products.stock_override, products.quantity, 0) > 0 THEN "active"'
            . ' ELSE "inactive"'
            . ' END)';
    }

    private function appendQuantityRangeBounds(
        array &$whereParts,
        array &$params,
        string $rawFrom,
        string $rawTo,
        string $columnSql,
        string $prefix
    ): void {
        $hasFrom = preg_match('/^\d+$/', $rawFrom) === 1;
        $hasTo = preg_match('/^\d+$/', $rawTo) === 1;

        if (!$hasFrom && !$hasTo) {
            return;
        }

        if ($hasFrom && $hasTo) {
            $from = (int) $rawFrom;
            $to = (int) $rawTo;
            if ($from > $to) {
                $tmp = $from;
                $from = $to;
                $to = $tmp;
            }

            $whereParts[] = ' AND ' . $columnSql . ' BETWEEN :' . $prefix . '_from AND :' . $prefix . '_to';
            $params[$prefix . '_from'] = $from;
            $params[$prefix . '_to'] = $to;
            return;
        }

        if ($hasFrom) {
            $whereParts[] = ' AND ' . $columnSql . ' >= :' . $prefix . '_from';
            $params[$prefix . '_from'] = (int) $rawFrom;
        }

        if ($hasTo) {
            $whereParts[] = ' AND ' . $columnSql . ' <= :' . $prefix . '_to';
            $params[$prefix . '_to'] = (int) $rawTo;
        }
    }

    private function buildIntegerPlaceholders(string $prefix, array $values, array &$params): array
    {
        $placeholders = array();

        foreach ($values as $index => $value) {
            $key = $prefix . '_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int) $value;
        }

        return $placeholders;
    }

    private function buildStringPlaceholders(string $prefix, array $values, array &$params): array
    {
        $placeholders = array();

        foreach ($values as $index => $value) {
            $key = $prefix . '_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (string) $value;
        }

        return $placeholders;
    }

    private function extractCategoryMeta(array $remoteProduct): array
    {
        $categories = isset($remoteProduct['categories']) && is_array($remoteProduct['categories']) ? $remoteProduct['categories'] : array();
        foreach ($categories as $path) {
            if (!is_array($path) || $path === array()) {
                continue;
            }

            $leaf = end($path);
            if (is_array($leaf)) {
                return array(
                    'id' => isset($leaf['id']) ? (int) $leaf['id'] : null,
                    'name' => $this->stringOrNull($leaf['name'] ?? null),
                );
            }
        }

        return array('id' => null, 'name' => null);
    }

    private function extractImageUrls(array $images): array
    {
        $result = array();

        foreach ($images as $image) {
            $url = null;
            if (is_array($image) && !empty($image['url'])) {
                $url = trim((string) $image['url']);
            } elseif (is_string($image)) {
                $url = trim($image);
            }

            if ($url === null || $url === '' || preg_match('#^https?://#i', $url) !== 1) {
                continue;
            }

            $result[] = $url;
        }

        return array_values(array_unique($result));
    }

    private function extractDescriptionText($description): string
    {
        if (is_string($description)) {
            return trim($description);
        }

        if (!is_array($description)) {
            return '';
        }

        $sections = isset($description['sections']) && is_array($description['sections']) ? $description['sections'] : array();
        $parts = array();

        foreach ($sections as $section) {
            if (!is_array($section) || !isset($section['items']) || !is_array($section['items'])) {
                continue;
            }

            foreach ($section['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                if (strtoupper((string) ($item['type'] ?? '')) !== 'TEXT') {
                    continue;
                }

                $content = trim((string) ($item['content'] ?? ''));
                if ($content !== '') {
                    $parts[] = $content;
                }
            }
        }

        return trim(implode("\n\n", $parts));
    }

    private function dateTimeOrNull($value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime($value);
        return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    private function stringOrNull($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }
}
