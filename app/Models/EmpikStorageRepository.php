<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;

class EmpikStorageRepository
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
            "CREATE TABLE IF NOT EXISTS empik_accounts (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "name VARCHAR(190) NOT NULL,\n"
            . "slug VARCHAR(190) NOT NULL,\n"
            . "api_url VARCHAR(255) NOT NULL,\n"
            . "api_key TEXT NOT NULL,\n"
            . "shop_id BIGINT UNSIGNED DEFAULT NULL,\n"
            . "locale VARCHAR(20) DEFAULT NULL,\n"
            . "is_active TINYINT(1) NOT NULL DEFAULT 1,\n"
            . "last_sync_at DATETIME DEFAULT NULL,\n"
            . "last_error_at DATETIME DEFAULT NULL,\n"
            . "last_error_message TEXT DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_empik_accounts_slug (slug),\n"
            . "KEY idx_empik_accounts_active (is_active)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS empik_offers (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "account_id INT UNSIGNED NOT NULL,\n"
            . "offer_id BIGINT UNSIGNED NOT NULL,\n"
            . "shop_sku VARCHAR(190) DEFAULT NULL,\n"
            . "product_sku VARCHAR(190) DEFAULT NULL,\n"
            . "product_id VARCHAR(190) DEFAULT NULL,\n"
            . "product_title VARCHAR(255) DEFAULT NULL,\n"
            . "description MEDIUMTEXT DEFAULT NULL,\n"
            . "category_code VARCHAR(190) DEFAULT NULL,\n"
            . "category_label VARCHAR(255) DEFAULT NULL,\n"
            . "state_code VARCHAR(120) DEFAULT NULL,\n"
            . "active TINYINT(1) NOT NULL DEFAULT 0,\n"
            . "quantity INT DEFAULT NULL,\n"
            . "price DECIMAL(12,2) DEFAULT NULL,\n"
            . "total_price DECIMAL(12,2) DEFAULT NULL,\n"
            . "currency_iso_code VARCHAR(10) DEFAULT NULL,\n"
            . "min_shipping_price DECIMAL(12,2) DEFAULT NULL,\n"
            . "leadtime_to_ship INT DEFAULT NULL,\n"
            . "offer_json LONGTEXT NOT NULL,\n"
            . "last_synced_at DATETIME NOT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_empik_offer_account_offer (account_id, offer_id),\n"
            . "KEY idx_empik_offers_account_id (account_id),\n"
            . "KEY idx_empik_offers_shop_sku (shop_sku),\n"
            . "KEY idx_empik_offers_product_sku (product_sku),\n"
            . "KEY idx_empik_offers_state_code (state_code),\n"
            . "KEY idx_empik_offers_active (active),\n"
            . "KEY idx_empik_offers_synced (last_synced_at)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS empik_offer_change_queue (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "account_id INT UNSIGNED NOT NULL,\n"
            . "offer_row_id INT UNSIGNED NOT NULL,\n"
            . "operation VARCHAR(80) NOT NULL,\n"
            . "payload_json LONGTEXT DEFAULT NULL,\n"
            . "status VARCHAR(40) NOT NULL DEFAULT 'pending',\n"
            . "error_message LONGTEXT DEFAULT NULL,\n"
            . "remote_import_id VARCHAR(190) DEFAULT NULL,\n"
            . "remote_import_type VARCHAR(40) DEFAULT NULL,\n"
            . "attempts INT UNSIGNED NOT NULL DEFAULT 0,\n"
            . "available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "started_at DATETIME DEFAULT NULL,\n"
            . "finished_at DATETIME DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "KEY idx_empik_offer_change_queue_status (status, available_at),\n"
            . "KEY idx_empik_offer_change_queue_offer_row (offer_row_id),\n"
            . "KEY idx_empik_offer_change_queue_account (account_id)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS empik_cache (\n"
            . "cache_key VARCHAR(190) NOT NULL,\n"
            . "payload LONGTEXT NOT NULL,\n"
            . "expires_at DATETIME NOT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (cache_key),\n"
            . "KEY idx_empik_cache_expires_at (expires_at)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // NOTE: shop_sku/product_sku may still be utf8mb4_general_ci on older installs, which
        // blocks index use for the computers-products marketplace filter (see ComputersController
        // ::computerProductFilterSql). Fixed manually via fixSkuCollation() / the CLI script
        // php/scripts/fix_marketplace_sku_collation.php - deliberately NOT run automatically
        // here, since a collation change is an ALGORITHM=COPY ALTER (full table rebuild) that
        // shared hosting can kill mid-flight ("MySQL server has gone away") if triggered from
        // a web request.

        // sync_offset is a resumable pagination cursor: the cron calling maintenance() every
        // ~10 minutes time-boxes each account's catalog sync (see EmpikService::syncAccount)
        // and needs to pick up where the previous run left off instead of restarting from
        // offset 0 every time. Unlike the collation fix above, ADD COLUMN with a fixed default
        // is a metadata-only change on modern MySQL/MariaDB (no full table rebuild), so it is
        // safe to run automatically here.
        $this->ensureColumnExists('empik_accounts', 'sync_offset', 'INT UNSIGNED NOT NULL DEFAULT 0');

        // Backs offersDueForOperation()'s per-offer correlated subqueries (is there an
        // unresolved queue entry for this offer+operation? when was it last queued for this
        // operation?) with a single covering index instead of a full scan of the whole,
        // ever-growing queue history on every maintenance tick.
        $this->ensureIndexExists(
            'empik_offer_change_queue',
            'idx_empik_offer_change_queue_offer_operation',
            '(offer_row_id, operation, status, created_at)'
        );

        self::$schemaEnsured = true;
    }

    private function ensureColumnExists(string $table, string $column, string $definitionSql): void
    {
        $exists = $this->database->fetchColumn(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name',
            array('table_name' => $table, 'column_name' => $column)
        );

        if ((int) $exists > 0) {
            return;
        }

        $this->database->query('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definitionSql);
    }

    private function ensureIndexExists(string $table, string $indexName, string $columnsSql): void
    {
        $exists = $this->database->fetchColumn(
            'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name',
            array('table_name' => $table, 'index_name' => $indexName)
        );

        if ((int) $exists > 0) {
            return;
        }

        $this->database->query('ALTER TABLE ' . $table . ' ADD INDEX ' . $indexName . ' ' . $columnsSql);
    }

    public function fixSkuCollation(string $collation = 'utf8mb4_unicode_ci'): array
    {
        return array_values(array_filter(array(
            $this->ensureColumnCollation('empik_offers', 'shop_sku', 'VARCHAR(190) DEFAULT NULL', $collation),
            $this->ensureColumnCollation('empik_offers', 'product_sku', 'VARCHAR(190) DEFAULT NULL', $collation),
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

    public function cleanupExpiredCache(): void
    {
        $this->database->delete('empik_cache', 'expires_at < :now', array('now' => date('Y-m-d H:i:s')));
    }

    public function allAccounts(): array
    {
        return $this->database->fetchAll('SELECT * FROM empik_accounts ORDER BY is_active DESC, name ASC, id ASC');
    }

    public function activeAccounts(): array
    {
        return $this->database->fetchAll('SELECT * FROM empik_accounts WHERE is_active = 1 ORDER BY name ASC, id ASC');
    }

    /**
     * Active accounts ordered so the one synced longest ago (or never synced) comes first.
     * Used by the maintenance cron when it must sync several accounts within one time-boxed
     * request: a fixed alphabetical order would let an early account starve the later ones
     * whenever there isn't enough of the request's time budget left to reach them all.
     */
    public function accountsDueForSync(): array
    {
        return $this->database->fetchAll(
            'SELECT * FROM empik_accounts WHERE is_active = 1'
            . ' ORDER BY (last_sync_at IS NULL) DESC, last_sync_at ASC, id ASC'
        );
    }

    public function updateAccountSyncOffset(int $accountId, int $offset): void
    {
        $this->database->update(
            'empik_accounts',
            array('sync_offset' => max(0, $offset)),
            'id = :id',
            array('id' => $accountId)
        );
    }

    public function findAccountById(int $id)
    {
        return $this->database->fetch('SELECT * FROM empik_accounts WHERE id = :id LIMIT 1', array('id' => $id));
    }

    public function findAccountBySlug(string $slug)
    {
        return $this->database->fetch('SELECT * FROM empik_accounts WHERE slug = :slug LIMIT 1', array('slug' => $slug));
    }

    public function saveAccount(array $data, ?int $accountId = null): int
    {
        if ($accountId !== null && $accountId > 0) {
            $this->database->update('empik_accounts', $data, 'id = :id', array('id' => $accountId));
            return $accountId;
        }

        return (int) $this->database->insert('empik_accounts', $data);
    }

    public function markAccountSyncSuccess(int $accountId): void
    {
        $this->database->update('empik_accounts', array(
            'last_sync_at' => date('Y-m-d H:i:s'),
            'last_error_at' => null,
            'last_error_message' => null,
        ), 'id = :id', array('id' => $accountId));
    }

    public function markAccountSyncError(int $accountId, string $message): void
    {
        $this->database->update('empik_accounts', array(
            'last_error_at' => date('Y-m-d H:i:s'),
            'last_error_message' => $message,
        ), 'id = :id', array('id' => $accountId));
    }

    public function upsertOffer(array $data): void
    {
        $this->upsertOffers(array($data));
    }

    /**
     * Batched atomic upsert for a page of synced offers. The previous implementation did a
     * SELECT followed by an INSERT/UPDATE per offer, i.e. two DB round-trips per row - for a
     * sync job that re-reads the whole catalog on every ~10 minute cron tick that's thousands
     * of extra queries and a real bottleneck. A single multi-row INSERT ... ON DUPLICATE KEY
     * UPDATE per page (up to 100 offers) is both faster and race-free, mirroring the same fix
     * already applied to putCache() below.
     */
    public function upsertOffers(array $rows): int
    {
        $rows = array_values(array_filter($rows, static function ($row): bool {
            return is_array($row) && (int) ($row['account_id'] ?? 0) > 0 && (int) ($row['offer_id'] ?? 0) > 0;
        }));

        if ($rows === array()) {
            return 0;
        }

        $columns = array(
            'account_id', 'offer_id', 'shop_sku', 'product_sku', 'product_id', 'product_title',
            'description', 'category_code', 'category_label', 'state_code', 'active', 'quantity',
            'price', 'total_price', 'currency_iso_code', 'min_shipping_price', 'leadtime_to_ship',
            'offer_json', 'last_synced_at',
        );

        $valueGroups = array();
        $params = array();

        foreach ($rows as $rowIndex => $row) {
            $placeholders = array();
            foreach ($columns as $column) {
                $paramName = $column . '_' . $rowIndex;
                $placeholders[] = ':' . $paramName;
                $params[$paramName] = array_key_exists($column, $row) ? $row[$column] : null;
            }
            $valueGroups[] = '(' . implode(', ', $placeholders) . ')';
        }

        $updateAssignments = array();
        foreach ($columns as $column) {
            if ($column === 'account_id' || $column === 'offer_id') {
                continue;
            }
            $updateAssignments[] = $column . ' = VALUES(' . $column . ')';
        }

        $this->database->query(
            'INSERT INTO empik_offers (' . implode(', ', $columns) . ') VALUES ' . implode(', ', $valueGroups)
            . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updateAssignments),
            $params
        );

        return count($rows);
    }

    public function countOffers(array $filters = array()): int
    {
        $params = array();
        $analysis = $this->analyzeOfferFilters($filters);
        $sql = 'SELECT COUNT(*) FROM empik_offers offers'
            . ' INNER JOIN empik_accounts accounts ON accounts.id = offers.account_id';

        if ($analysis['needs_warehouse']) {
            $sql .= $this->liveWarehouseJoinSql();
        }

        if ($analysis['needs_shared_stock']) {
            $sql .= ' LEFT JOIN shared_stock_groups ON shared_stock_groups.id = warehouse.shared_stock_group_id';
        }

        $where = $this->buildOfferWhere($filters, $params);

        return (int) $this->database->fetchColumn($sql . $where, $params);
    }

    public function listOffers(array $filters, int $page, int $perPage, string $sortBy = 'synced', string $sortDir = 'desc'): array
    {
        $params = array();
        $analysis = $this->analyzeOfferFilters($filters, $sortBy);
        $where = $this->buildOfferWhere($filters, $params);
        $offset = max(0, ($page - 1) * $perPage);
        $sortSql = $this->buildOfferSort($sortBy, $sortDir);

        $idSql = 'SELECT offers.id FROM empik_offers offers'
            . ' INNER JOIN empik_accounts accounts ON accounts.id = offers.account_id';

        if ($analysis['needs_warehouse']) {
            $idSql .= $this->liveWarehouseJoinSql();
        }

        if ($analysis['needs_shared_stock']) {
            $idSql .= ' LEFT JOIN shared_stock_groups ON shared_stock_groups.id = warehouse.shared_stock_group_id';
        }

        $idSql .= $where
            . ' ORDER BY ' . $sortSql
            . ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;

        $idRows = $this->database->fetchAll($idSql, $params);
        if ($idRows === array()) {
            return array();
        }

        $offerIds = array_map(static function (array $row): int {
            return (int) ($row['id'] ?? 0);
        }, $idRows);

        $detailParams = array();
        $placeholders = $this->buildIntegerPlaceholders('offer_id', $offerIds, $detailParams);
        $rows = $this->database->fetchAll(
            'SELECT offers.*,'
            . ' accounts.name AS account_name, accounts.slug AS account_slug, accounts.api_url, accounts.shop_id, accounts.locale,'
            . ' warehouse.id AS warehouse_product_live_id, warehouse.sku AS warehouse_sku, warehouse.product_name AS warehouse_product_name,'
            . ' warehouse.price_gross AS warehouse_price_gross, warehouse.category_id AS warehouse_category_id,'
            . ' COALESCE(shared_stock_groups.quantity, warehouse.quantity) AS warehouse_quantity,'
            . ' COALESCE(shared_stock_groups.localization, warehouse.localization) AS warehouse_localization'
            . ' FROM empik_offers offers'
            . ' INNER JOIN empik_accounts accounts ON accounts.id = offers.account_id'
            . $this->liveWarehouseJoinSql()
            . ' LEFT JOIN shared_stock_groups ON shared_stock_groups.id = warehouse.shared_stock_group_id'
            . ' WHERE offers.id IN (' . implode(', ', $placeholders) . ')',
            $detailParams
        );

        $rowsById = array();
        foreach ($rows as $row) {
            $rowsById[(int) ($row['id'] ?? 0)] = $row;
        }

        $queueMeta = $this->latestQueueMetaForOffers($offerIds);
        $orderedRows = array();

        foreach ($offerIds as $offerId) {
            if (!isset($rowsById[$offerId])) {
                continue;
            }

            $row = $rowsById[$offerId];
            $row['queue_meta'] = $queueMeta[$offerId] ?? array();
            $orderedRows[] = $row;
        }

        return $orderedRows;
    }

    public function findOfferRowById(int $id)
    {
        $rows = $this->listOffers(array('id' => (string) $id), 1, 1, 'id', 'desc');
        return isset($rows[0]) ? $rows[0] : null;
    }

    public function listOffersByIds(array $offerRowIds): array
    {
        $offerRowIds = array_values(array_unique(array_filter(array_map('intval', $offerRowIds))));
        if ($offerRowIds === array()) {
            return array();
        }

        $params = array();
        $placeholders = $this->buildIntegerPlaceholders('offer_row_lookup', $offerRowIds, $params);
        $rows = $this->database->fetchAll(
            'SELECT offers.id FROM empik_offers offers WHERE offers.id IN (' . implode(', ', $placeholders) . ') ORDER BY offers.id DESC',
            $params
        );

        if ($rows === array()) {
            return array();
        }

        $orderedIds = array_map(static function (array $row): int {
            return (int) ($row['id'] ?? 0);
        }, $rows);

        $result = array();
        foreach ($orderedIds as $offerRowId) {
            $row = $this->findOfferRowById($offerRowId);
            if ($row) {
                $result[] = $row;
            }
        }

        return $result;
    }

    public function findOfferByRowId(int $id)
    {
        return $this->database->fetch(
            'SELECT offers.*, accounts.name AS account_name, accounts.slug AS account_slug, accounts.api_url, accounts.api_key, accounts.shop_id, accounts.locale'
            . ' FROM empik_offers offers'
            . ' INNER JOIN empik_accounts accounts ON accounts.id = offers.account_id'
            . ' WHERE offers.id = :id LIMIT 1',
            array('id' => $id)
        );
    }

    public function offerTargetsForFilters(array $filters, int $limit = 5000): array
    {
        $page = 1;
        $batchSize = max(1, min(5000, $limit));
        return $this->listOffers($filters, $page, $batchSize, 'id', 'desc');
    }

    public function queueCounts(): array
    {
        $rows = $this->database->fetchAll(
            'SELECT queue_latest.status, COUNT(*) AS total'
            . ' FROM empik_offer_change_queue queue_latest'
            . ' INNER JOIN ('
            . '   SELECT offer_row_id, MAX(id) AS latest_id'
            . '   FROM empik_offer_change_queue'
            . '   GROUP BY offer_row_id'
            . ' ) queue_max ON queue_max.latest_id = queue_latest.id'
            . ' INNER JOIN empik_offers offers ON offers.id = queue_latest.offer_row_id'
            . ' INNER JOIN empik_accounts accounts ON accounts.id = offers.account_id'
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

    public function enqueueOfferChanges(array $targets, string $operation, array $payload, ?string $availableAt = null): int
    {
        if ($targets === array()) {
            return 0;
        }

        $queued = 0;
        $availableAt = $availableAt !== null && trim($availableAt) !== '' ? trim($availableAt) : date('Y-m-d H:i:s');

        foreach ($targets as $target) {
            $offerRowId = isset($target['id']) ? (int) $target['id'] : 0;
            $accountId = isset($target['account_id']) ? (int) $target['account_id'] : 0;
            if ($offerRowId <= 0 || $accountId <= 0) {
                continue;
            }

            $this->database->insert('empik_offer_change_queue', array(
                'account_id' => $accountId,
                'offer_row_id' => $offerRowId,
                'operation' => $operation,
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status' => 'pending',
                'available_at' => $availableAt,
            ));
            $queued++;
        }

        return $queued;
    }

    /**
     * Offers eligible for an automated warehouse-sync operation (set_price_from_product /
     * set_stock_from_product), skipping any offer that already has an unresolved queue entry
     * for that exact operation and prioritising offers that were queued for it longest ago
     * (or never). Without this, a maintenance cron always re-selecting "top N offers by id"
     * would enqueue the same handful of offers every ~10 minutes forever while the rest of
     * a large, active+linked catalog never gets its price/stock refreshed, and would keep
     * piling up duplicate queue rows for offers still waiting to be processed.
     */
    public function offersDueForOperation(array $filters, string $operation, int $limit): array
    {
        $params = array('due_operation' => $operation, 'due_operation_history' => $operation);
        $analysis = $this->analyzeOfferFilters($filters);

        // Both "is there already an unresolved queue entry for this offer+operation" and
        // "when was this offer last queued for this operation" are expressed as per-offer
        // correlated subqueries (backed by idx_empik_offer_change_queue_offer_operation)
        // rather than a JOIN against a derived table that GROUPs the *entire* queue history
        // by offer_row_id. The derived-table version scanned the whole, ever-growing
        // empik_offer_change_queue table on every maintenance tick regardless of how many
        // offers actually match the filters - on a queue table that accumulates "done"/
        // "error" rows over months, that query got slower every day and, combined with
        // set_time_limit() being unable to interrupt a single running SQL statement, was
        // able to tie up a web worker (and the whole shared-hosting panel) for minutes.
        $sql = 'SELECT offers.id, offers.account_id,'
            . '   (SELECT MAX(history_queue.created_at) FROM empik_offer_change_queue history_queue'
            . '     WHERE history_queue.offer_row_id = offers.id AND history_queue.operation = :due_operation_history'
            . '   ) AS last_queued_at'
            . ' FROM empik_offers offers'
            . ' INNER JOIN empik_accounts accounts ON accounts.id = offers.account_id';

        if ($analysis['needs_warehouse']) {
            $sql .= $this->liveWarehouseJoinSql();
        }

        if ($analysis['needs_shared_stock']) {
            $sql .= ' LEFT JOIN shared_stock_groups ON shared_stock_groups.id = warehouse.shared_stock_group_id';
        }

        $where = $this->buildOfferWhere($filters, $params);
        $sql .= $where
            . ' AND NOT EXISTS ('
            . '   SELECT 1 FROM empik_offer_change_queue active_queue'
            . '   WHERE active_queue.offer_row_id = offers.id'
            . '     AND active_queue.operation = :due_operation'
            . '     AND active_queue.status IN ("pending", "processing", "retry")'
            . ' )'
            . ' ORDER BY (last_queued_at IS NULL) DESC, last_queued_at ASC, offers.id ASC'
            . ' LIMIT ' . max(1, min(5000, $limit));

        // Belt-and-braces cap in case the filters (e.g. the "linked" warehouse match) still
        // turn out to be expensive on a particular install: a slow query here should degrade
        // to "skip this round, try again next tick" (caught in EmpikService), never to a
        // multi-minute request.
        return $this->database->withStatementTimeoutMs(8000, function () use ($sql, $params) {
            return $this->database->fetchAll($sql, $params);
        });
    }

    /**
     * Recovers queue rows left stuck in "processing" because the worker that claimed them
     * died mid-operation (PHP execution-time limit, fatal error, OOM, server restart) - none
     * of those trigger the try/catch in EmpikService::processQueue(), so without this the row
     * would sit in "processing" forever: fetchQueueBatch() only ever selects "pending"/"retry".
     * Rows past the attempt cap are given up on (status=error) instead of retried forever.
     */
    public function reclaimStaleProcessing(int $staleAfterSeconds = 300, int $maxAttempts = 5): array
    {
        $threshold = date('Y-m-d H:i:s', time() - max(30, $staleAfterSeconds));
        $now = date('Y-m-d H:i:s');

        $errored = $this->database->query(
            'UPDATE empik_offer_change_queue'
            . ' SET status = "error", finished_at = :now,'
            . '     error_message = "Przerwano: worker zakonczyl sie w trakcie przetwarzania (limit prob wyczerpany)."'
            . ' WHERE status = "processing" AND started_at IS NOT NULL AND started_at < :threshold AND attempts >= :max_attempts',
            array('now' => $now, 'threshold' => $threshold, 'max_attempts' => $maxAttempts)
        )->rowCount();

        $retried = $this->database->query(
            'UPDATE empik_offer_change_queue'
            . ' SET status = "retry", attempts = attempts + 1, available_at = :now,'
            . '     error_message = "Wznowiono: worker zakonczyl sie w trakcie poprzedniego przetwarzania."'
            . ' WHERE status = "processing" AND started_at IS NOT NULL AND started_at < :threshold AND attempts < :max_attempts',
            array('now' => $now, 'threshold' => $threshold, 'max_attempts' => $maxAttempts)
        )->rowCount();

        return array('errored' => $errored, 'retried' => $retried);
    }

    public function fetchQueueBatch(int $limit = 100, ?int $accountId = null): array
    {
        $params = array('now' => date('Y-m-d H:i:s'));
        $sql = 'SELECT queue.*, offers.shop_sku, offers.product_sku, offers.offer_id, offers.product_title'
            . ' FROM empik_offer_change_queue queue'
            . ' INNER JOIN empik_offers offers ON offers.id = queue.offer_row_id'
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
            'empik_offer_change_queue',
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

    public function markQueueDone(int $queueId, ?string $remoteImportId = null, ?string $remoteImportType = null): void
    {
        $this->database->update(
            'empik_offer_change_queue',
            array(
                'status' => 'done',
                'remote_import_id' => $remoteImportId,
                'remote_import_type' => $remoteImportType,
                'finished_at' => date('Y-m-d H:i:s'),
                'error_message' => null,
            ),
            'id = :id',
            array('id' => $queueId)
        );
    }

    public function markQueueRetry(int $queueId, string $message, int $delaySeconds = 60, ?string $remoteImportId = null, ?string $remoteImportType = null): void
    {
        $row = $this->database->fetch('SELECT attempts FROM empik_offer_change_queue WHERE id = :id LIMIT 1', array('id' => $queueId));
        $attempts = isset($row['attempts']) ? (int) $row['attempts'] + 1 : 1;

        $this->database->update(
            'empik_offer_change_queue',
            array(
                'status' => 'retry',
                'attempts' => $attempts,
                'error_message' => $message,
                'remote_import_id' => $remoteImportId,
                'remote_import_type' => $remoteImportType,
                'available_at' => date('Y-m-d H:i:s', time() + max(5, $delaySeconds)),
            ),
            'id = :id',
            array('id' => $queueId)
        );
    }

    public function markQueueError(int $queueId, string $message, ?string $remoteImportId = null, ?string $remoteImportType = null): void
    {
        $row = $this->database->fetch('SELECT attempts FROM empik_offer_change_queue WHERE id = :id LIMIT 1', array('id' => $queueId));
        $attempts = isset($row['attempts']) ? (int) $row['attempts'] + 1 : 1;

        $this->database->update(
            'empik_offer_change_queue',
            array(
                'status' => 'error',
                'attempts' => $attempts,
                'error_message' => $message,
                'remote_import_id' => $remoteImportId,
                'remote_import_type' => $remoteImportType,
                'finished_at' => date('Y-m-d H:i:s'),
            ),
            'id = :id',
            array('id' => $queueId)
        );
    }

    public function clearWholeQueue(): array
    {
        $removed = $this->database->delete('empik_offer_change_queue', '1=1', array());
        return array('removed' => (int) $removed);
    }

    public function clearQueueStatuses(bool $keepPending = true): array
    {
        $where = $keepPending ? 'status NOT IN ("pending", "retry", "processing")' : '1=1';
        $removed = $this->database->delete('empik_offer_change_queue', $where, array());
        return array('removed' => (int) $removed);
    }

    public function clearQueueForOffers(array $offerRowIds): int
    {
        $offerRowIds = array_values(array_unique(array_filter(array_map('intval', $offerRowIds))));
        if ($offerRowIds === array()) {
            return 0;
        }

        $params = array();
        $placeholders = $this->buildIntegerPlaceholders('offer_row_id', $offerRowIds, $params);
        $sql = 'DELETE FROM empik_offer_change_queue WHERE offer_row_id IN (' . implode(', ', $placeholders) . ')';
        return (int) $this->database->query($sql, $params)->rowCount();
    }

    public function removeOffersByIds(array $offerRowIds): int
    {
        $offerRowIds = array_values(array_unique(array_filter(array_map('intval', $offerRowIds))));
        if ($offerRowIds === array()) {
            return 0;
        }

        $params = array();
        $placeholders = $this->buildIntegerPlaceholders('offer_delete_id', $offerRowIds, $params);
        $sql = 'DELETE FROM empik_offers WHERE id IN (' . implode(', ', $placeholders) . ')';
        return (int) $this->database->query($sql, $params)->rowCount();
    }

    public function latestQueueMetaForOffers(array $offerIds): array
    {
        $offerIds = array_values(array_unique(array_filter(array_map('intval', $offerIds))));
        if ($offerIds === array()) {
            return array();
        }

        $params = array();
        $placeholders = $this->buildIntegerPlaceholders('queue_meta_offer_id', $offerIds, $params);
        $rows = $this->database->fetchAll(
            'SELECT queue.*'
            . ' FROM empik_offer_change_queue queue'
            . ' INNER JOIN ('
            . '   SELECT offer_row_id, MAX(id) AS latest_id'
            . '   FROM empik_offer_change_queue'
            . '   WHERE offer_row_id IN (' . implode(', ', $placeholders) . ')'
            . '   GROUP BY offer_row_id'
            . ' ) latest ON latest.latest_id = queue.id',
            $params
        );

        $result = array();
        foreach ($rows as $row) {
            $offerId = isset($row['offer_row_id']) ? (int) $row['offer_row_id'] : 0;
            if ($offerId <= 0) {
                continue;
            }

            $decoded = json_decode((string) ($row['payload_json'] ?? ''), true);
            $row['payload'] = is_array($decoded) ? $decoded : array();
            $result[$offerId] = $row;
        }

        return $result;
    }

    public function getCache(string $key)
    {
        $row = $this->database->fetch(
            'SELECT payload FROM empik_cache WHERE cache_key = :cache_key AND expires_at >= :now LIMIT 1',
            array('cache_key' => $key, 'now' => date('Y-m-d H:i:s'))
        );

        if (!$row || !isset($row['payload'])) {
            return null;
        }

        $decoded = json_decode((string) $row['payload'], true);
        return is_array($decoded) ? $decoded : null;
    }

    public function putCache(string $key, array $payload, int $ttl): void
    {
        $expiresAt = date('Y-m-d H:i:s', time() + max(60, $ttl));
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Atomic upsert on purpose: the previous "SELECT then INSERT/UPDATE" approach
        // raced under concurrent requests (cron worker + a browser search hitting the
        // same cache_key at once) and threw "Duplicate entry ... for key 'PRIMARY'",
        // which crashed the request before any JSON could be returned to the UI.
        $this->database->query(
            'INSERT INTO empik_cache (cache_key, payload, expires_at) VALUES (:cache_key, :payload, :expires_at) '
            . 'ON DUPLICATE KEY UPDATE payload = VALUES(payload), expires_at = VALUES(expires_at)',
            array('cache_key' => $key, 'payload' => $payloadJson, 'expires_at' => $expiresAt)
        );
    }

    private function analyzeOfferFilters(array $filters, string $sortBy = ''): array
    {
        $needsWarehouse = false;
        $needsSharedStock = false;

        foreach (array('linked', 'warehouse_quantity', 'warehouse_quantity_from', 'warehouse_quantity_to', 'product_query') as $key) {
            if (trim((string) ($filters[$key] ?? '')) !== '') {
                $needsWarehouse = true;
            }
        }

        if ($needsWarehouse || in_array($sortBy, array('warehouse_quantity', 'warehouse_sku', 'linked'), true)) {
            $needsWarehouse = true;
            $needsSharedStock = true;
        }

        return array(
            'needs_warehouse' => $needsWarehouse,
            'needs_shared_stock' => $needsSharedStock,
        );
    }

    private function buildOfferWhere(array $filters, array &$params): string
    {
        $whereParts = array(' WHERE 1=1');

        $id = trim((string) ($filters['id'] ?? ''));
        if ($id !== '' && ctype_digit($id)) {
            $whereParts[] = ' AND offers.id = :id';
            $params['id'] = (int) $id;
        }

        $accountId = trim((string) ($filters['account_id'] ?? ''));
        if ($accountId !== '' && ctype_digit($accountId) && (int) $accountId > 0) {
            $whereParts[] = ' AND offers.account_id = :account_id';
            $params['account_id'] = (int) $accountId;
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $whereParts[] = ' AND ('
                . ' CAST(offers.offer_id AS CHAR) LIKE :query_offer_id'
                . ' OR offers.product_title LIKE :query_product_title'
                . ' OR offers.description LIKE :query_description'
                . ' OR offers.category_label LIKE :query_category_label'
                . ' OR offers.shop_sku LIKE :query_shop_sku'
                . ' OR offers.product_sku LIKE :query_product_sku'
                . ' OR offers.product_id LIKE :query_product_id'
                . ' )';
            $queryLike = '%' . $query . '%';
            $params['query_offer_id'] = $queryLike;
            $params['query_product_title'] = $queryLike;
            $params['query_description'] = $queryLike;
            $params['query_category_label'] = $queryLike;
            $params['query_shop_sku'] = $queryLike;
            $params['query_product_sku'] = $queryLike;
            $params['query_product_id'] = $queryLike;
        }

        $sku = trim((string) ($filters['sku'] ?? ''));
        if ($sku !== '') {
            $whereParts[] = ' AND (offers.shop_sku LIKE :sku_shop OR offers.product_sku LIKE :sku_product OR offers.product_id LIKE :sku_product_id)';
            $skuLike = '%' . $sku . '%';
            $params['sku_shop'] = $skuLike;
            $params['sku_product'] = $skuLike;
            $params['sku_product_id'] = $skuLike;
        }

        $state = trim((string) ($filters['state'] ?? ''));
        if ($state !== '') {
            $whereParts[] = ' AND offers.state_code = :state_code';
            $params['state_code'] = $state;
        }

        $active = trim((string) ($filters['active'] ?? ''));
        if ($active === '1' || $active === '0') {
            $whereParts[] = ' AND offers.active = :active';
            $params['active'] = (int) $active;
        }

        $queueStatus = strtolower(trim((string) ($filters['queue_status'] ?? '')));
        if (in_array($queueStatus, array('pending', 'retry', 'error', 'done', 'processing'), true)) {
            $whereParts[] = ' AND EXISTS ('
                . ' SELECT 1 FROM empik_offer_change_queue queue_latest'
                . ' WHERE queue_latest.offer_row_id = offers.id'
                . '   AND queue_latest.id = ('
                . '     SELECT MAX(queue_max.id)'
                . '     FROM empik_offer_change_queue queue_max'
                . '     WHERE queue_max.offer_row_id = offers.id'
                . '   )'
                . '   AND queue_latest.status = :queue_status'
                . ' )';
            $params['queue_status'] = $queueStatus;
        }

        $errorQuery = trim((string) ($filters['error_query'] ?? ''));
        if ($errorQuery !== '') {
            $whereParts[] = ' AND EXISTS ('
                . ' SELECT 1 FROM empik_offer_change_queue queue_latest_error'
                . ' WHERE queue_latest_error.offer_row_id = offers.id'
                . '   AND queue_latest_error.id = ('
                . '     SELECT MAX(queue_max_error.id)'
                . '     FROM empik_offer_change_queue queue_max_error'
                . '     WHERE queue_max_error.offer_row_id = offers.id'
                . '   )'
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

    private function buildOfferSort(string $sortBy, string $sortDir): string
    {
        $sortDir = strtolower($sortDir) === 'asc' ? 'ASC' : 'DESC';
        $map = array(
            'id' => 'offers.id',
            'account' => 'accounts.name',
            'title' => 'offers.product_title',
            'sku' => 'offers.shop_sku',
            'product_sku' => 'offers.product_sku',
            'category' => 'offers.category_label',
            'state' => 'offers.state_code',
            'active' => 'offers.active',
            'quantity' => 'offers.quantity',
            'warehouse_quantity' => 'COALESCE(shared_stock_groups.quantity, warehouse.quantity)',
            'price' => 'offers.price',
            'synced' => 'offers.last_synced_at',
            'updated' => 'offers.updated_at',
            'queue_status' => '(SELECT queue_latest.status FROM empik_offer_change_queue queue_latest WHERE queue_latest.offer_row_id = offers.id ORDER BY queue_latest.id DESC LIMIT 1)',
        );

        $column = isset($map[$sortBy]) ? $map[$sortBy] : $map['synced'];
        return $column . ' ' . $sortDir . ', offers.id DESC';
    }

    private function liveWarehouseJoinSql(): string
    {
        return ' LEFT JOIN products warehouse ON warehouse.id = ('
            . '   SELECT warehouse_match.id'
            . '   FROM products warehouse_match'
            . '   LEFT JOIN product_custom_field_values warehouse_old_values ON warehouse_old_values.product_id = warehouse_match.id'
            . '   LEFT JOIN product_custom_field_definitions warehouse_old_definition'
            . '     ON warehouse_old_definition.id = warehouse_old_values.definition_id'
            . '     AND warehouse_old_definition.slug = "old_sku"'
            . '   WHERE warehouse_match.deleted_at IS NULL'
            . '     AND ('
            . '       (offers.shop_sku IS NOT NULL AND offers.shop_sku <> "" AND warehouse_match.sku = offers.shop_sku)'
            . '       OR (offers.product_sku IS NOT NULL AND offers.product_sku <> "" AND warehouse_match.sku = offers.product_sku)'
            . '       OR (warehouse_old_definition.id IS NOT NULL AND (warehouse_old_values.value = offers.shop_sku OR warehouse_old_values.value = offers.product_sku))'
            . '     )'
            . '   ORDER BY CASE'
            . '     WHEN offers.shop_sku IS NOT NULL AND offers.shop_sku <> "" AND warehouse_match.sku = offers.shop_sku THEN 0'
            . '     WHEN offers.product_sku IS NOT NULL AND offers.product_sku <> "" AND warehouse_match.sku = offers.product_sku THEN 1'
            . '     ELSE 2'
            . '   END, warehouse_match.id ASC'
            . '   LIMIT 1'
            . ' )';
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
}
