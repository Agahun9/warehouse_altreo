<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;

class AllegroStorageRepository
{
    private const SCHEMA_CACHE_KEY = 'schema:allegro_storage:v3';
    private const OFFER_COUNT_CACHE_TTL = 30;
    private const STATS_CACHE_TTL = 60;
    private const QUEUE_COUNT_CACHE_TTL = 15;
    /** @var bool */
    private static $schemaEnsured = false;

    /** @var Database */
    private $database;

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
            "CREATE TABLE IF NOT EXISTS allegro_accounts (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "name VARCHAR(120) NOT NULL,\n"
            . "slug VARCHAR(140) NOT NULL,\n"
            . "client_id VARCHAR(190) NOT NULL,\n"
            . "client_secret VARCHAR(255) NOT NULL,\n"
            . "redirect_uri VARCHAR(255) NOT NULL,\n"
            . "is_active TINYINT(1) NOT NULL DEFAULT 1,\n"
            . "oauth_state VARCHAR(128) DEFAULT NULL,\n"
            . "oauth_state_expires_at DATETIME DEFAULT NULL,\n"
            . "sync_token VARCHAR(96) NOT NULL,\n"
            . "last_auth_at DATETIME DEFAULT NULL,\n"
            . "last_sync_at DATETIME DEFAULT NULL,\n"
            . "last_error_at DATETIME DEFAULT NULL,\n"
            . "last_error_message TEXT DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_allegro_accounts_slug (slug),\n"
            . "UNIQUE KEY ux_allegro_accounts_sync_token (sync_token),\n"
            . "KEY idx_allegro_accounts_active (is_active)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS allegro_account_tokens (\n"
            . "account_id INT UNSIGNED NOT NULL,\n"
            . "access_token LONGTEXT NOT NULL,\n"
            . "refresh_token LONGTEXT DEFAULT NULL,\n"
            . "expires_at DATETIME NOT NULL,\n"
            . "token_type VARCHAR(30) DEFAULT NULL,\n"
            . "scope VARCHAR(255) DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (account_id),\n"
            . "CONSTRAINT fk_allegro_account_tokens_account FOREIGN KEY (account_id) REFERENCES allegro_accounts(id) ON DELETE CASCADE\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS allegro_offers (\n"
            . "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "account_id INT UNSIGNED NOT NULL,\n"
            . "offer_id VARCHAR(40) NOT NULL,\n"
            . "sku VARCHAR(190) DEFAULT NULL,\n"
            . "external_id VARCHAR(190) DEFAULT NULL,\n"
            . "warehouse_product_id INT UNSIGNED DEFAULT NULL,\n"
            . "linked_by VARCHAR(40) DEFAULT NULL,\n"
            . "name VARCHAR(255) NOT NULL,\n"
            . "primary_image_url TEXT DEFAULT NULL,\n"
            . "primary_image_hash CHAR(64) DEFAULT NULL,\n"
            . "image_count INT UNSIGNED NOT NULL DEFAULT 0,\n"
            . "images_json LONGTEXT DEFAULT NULL,\n"
            . "price_amount DECIMAL(15,2) DEFAULT NULL,\n"
            . "price_currency VARCHAR(8) DEFAULT NULL,\n"
            . "publication_status VARCHAR(40) DEFAULT NULL,\n"
            . "publication_ended_by VARCHAR(40) DEFAULT NULL,\n"
            . "stock_available INT DEFAULT NULL,\n"
            . "stock_sold INT DEFAULT NULL,\n"
            . "invoice_type VARCHAR(40) DEFAULT NULL,\n"
            . "allegro_product_id VARCHAR(64) DEFAULT NULL,\n"
            . "category_id VARCHAR(40) DEFAULT NULL,\n"
            . "category_name VARCHAR(255) DEFAULT NULL,\n"
            . "marketplaces_json LONGTEXT DEFAULT NULL,\n"
            . "product_set_json LONGTEXT DEFAULT NULL,\n"
            . "parameters_json LONGTEXT DEFAULT NULL,\n"
            . "offer_json LONGTEXT DEFAULT NULL,\n"
            . "summary_checksum CHAR(64) DEFAULT NULL,\n"
            . "details_checksum CHAR(64) DEFAULT NULL,\n"
            . "last_seen_cycle CHAR(36) DEFAULT NULL,\n"
            . "last_event_id VARCHAR(100) DEFAULT NULL,\n"
            . "last_event_at DATETIME DEFAULT NULL,\n"
            . "last_synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_allegro_offers_account_offer (account_id, offer_id),\n"
            . "KEY idx_allegro_offers_account_status (account_id, publication_status),\n"
            . "KEY idx_allegro_offers_account_sku (account_id, sku),\n"
            . "KEY idx_allegro_offers_account_name (account_id, name),\n"
            . "KEY idx_allegro_offers_seen (account_id, last_seen_cycle),\n"
            . "KEY idx_allegro_offers_updated (account_id, updated_at),\n"
            . "CONSTRAINT fk_allegro_offers_account FOREIGN KEY (account_id) REFERENCES allegro_accounts(id) ON DELETE CASCADE\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS allegro_sync_states (\n"
            . "account_id INT UNSIGNED NOT NULL,\n"
            . "is_running TINYINT(1) NOT NULL DEFAULT 0,\n"
            . "mode VARCHAR(20) NOT NULL DEFAULT 'full',\n"
            . "current_cycle CHAR(36) DEFAULT NULL,\n"
            . "offer_offset INT UNSIGNED NOT NULL DEFAULT 0,\n"
            . "last_event_id VARCHAR(100) DEFAULT NULL,\n"
            . "last_event_at DATETIME DEFAULT NULL,\n"
            . "last_full_sync_at DATETIME DEFAULT NULL,\n"
            . "last_incremental_sync_at DATETIME DEFAULT NULL,\n"
            . "last_success_at DATETIME DEFAULT NULL,\n"
            . "last_error_at DATETIME DEFAULT NULL,\n"
            . "last_error_message TEXT DEFAULT NULL,\n"
            . "heartbeat_at DATETIME DEFAULT NULL,\n"
            . "locked_until DATETIME DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (account_id),\n"
            . "CONSTRAINT fk_allegro_sync_states_account FOREIGN KEY (account_id) REFERENCES allegro_accounts(id) ON DELETE CASCADE\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS allegro_cache (\n"
            . "cache_key VARCHAR(190) NOT NULL,\n"
            . "payload LONGTEXT NOT NULL,\n"
            . "expires_at DATETIME NOT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (cache_key),\n"
            . "KEY idx_allegro_cache_expires_at (expires_at)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS allegro_offer_change_queue (\n"
            . "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "account_id INT UNSIGNED NOT NULL,\n"
            . "offer_row_id BIGINT UNSIGNED NOT NULL,\n"
            . "offer_id VARCHAR(40) NOT NULL,\n"
            . "operation VARCHAR(64) NOT NULL,\n"
            . "payload_json LONGTEXT DEFAULT NULL,\n"
            . "status VARCHAR(20) NOT NULL DEFAULT 'pending',\n"
            . "attempts INT UNSIGNED NOT NULL DEFAULT 0,\n"
            . "error_message TEXT DEFAULT NULL,\n"
            . "available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "processed_at DATETIME DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "KEY idx_allegro_offer_change_queue_status (status, available_at),\n"
            . "KEY idx_allegro_offer_change_queue_offer (offer_row_id),\n"
            . "KEY idx_allegro_offer_change_queue_account (account_id),\n"
            . "CONSTRAINT fk_allegro_offer_change_queue_account FOREIGN KEY (account_id) REFERENCES allegro_accounts(id) ON DELETE CASCADE,\n"
            . "CONSTRAINT fk_allegro_offer_change_queue_offer FOREIGN KEY (offer_row_id) REFERENCES allegro_offers(id) ON DELETE CASCADE\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS allegro_offer_exclusions (\n"
            . "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "account_id INT UNSIGNED NOT NULL,\n"
            . "offer_id VARCHAR(40) NOT NULL,\n"
            . "mode VARCHAR(32) NOT NULL DEFAULT 'permanent',\n"
            . "note VARCHAR(255) DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_allegro_offer_exclusions_account_offer (account_id, offer_id),\n"
            . "KEY idx_allegro_offer_exclusions_mode (mode),\n"
            . "CONSTRAINT fk_allegro_offer_exclusions_account FOREIGN KEY (account_id) REFERENCES allegro_accounts(id) ON DELETE CASCADE\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $schemaState = $this->getCache(self::SCHEMA_CACHE_KEY);
        if (is_array($schemaState) && !empty($schemaState['ready'])) {
            self::$schemaEnsured = true;
            return;
        }

        $this->ensureOfferExtensions();
        $this->ensureOfferIndexes();
        $this->putCache(self::SCHEMA_CACHE_KEY, array('ready' => 1), 86400);
        self::$schemaEnsured = true;
    }

    public function getCache(string $key)
    {
        $row = $this->database->fetch(
            'SELECT payload, expires_at FROM allegro_cache WHERE cache_key = :cache_key LIMIT 1',
            array('cache_key' => $key)
        );

        if (!$row) {
            return null;
        }

        if (strtotime((string) $row['expires_at']) < time()) {
            return null;
        }

        $decoded = json_decode((string) $row['payload'], true);
        return is_array($decoded) ? $decoded : null;
    }

    public function putCache(string $key, array $payload, int $ttlSeconds): void
    {
        $expiresAt = date('Y-m-d H:i:s', time() + max(60, $ttlSeconds));
        $row = $this->database->fetch(
            'SELECT cache_key FROM allegro_cache WHERE cache_key = :cache_key LIMIT 1',
            array('cache_key' => $key)
        );

        if ($row) {
            $this->database->update(
                'allegro_cache',
                array(
                    'payload' => json_encode($payload),
                    'expires_at' => $expiresAt,
                ),
                'cache_key = :cache_key',
                array('cache_key' => $key)
            );
            return;
        }

        $this->database->insert(
            'allegro_cache',
            array(
                'cache_key' => $key,
                'payload' => json_encode($payload),
                'expires_at' => $expiresAt,
            )
        );
    }

    public function cleanupExpiredCache(): void
    {
        $this->database->delete('allegro_cache', 'expires_at < :now', array('now' => date('Y-m-d H:i:s')));
    }

    private function liveWarehouseJoinSql(): string
    {
        return ' LEFT JOIN products warehouse_sku'
            . '   ON offers.sku IS NOT NULL'
            . '  AND offers.sku <> ""'
            . '  AND offers.sku REGEXP "[A-Za-z]"'
            . '  AND warehouse_sku.deleted_at IS NULL'
            . '  AND warehouse_sku.sku = offers.sku'
            . ' LEFT JOIN ('
            . '   SELECT old_sku_values.value AS old_sku_value, MIN(products_old.id) AS product_id'
            . '   FROM product_custom_field_values old_sku_values'
            . '   INNER JOIN product_custom_field_definitions old_sku_definition'
            . '     ON old_sku_definition.id = old_sku_values.definition_id'
            . '    AND old_sku_definition.slug = "old_sku"'
            . '   INNER JOIN products products_old'
            . '     ON products_old.id = old_sku_values.product_id'
            . '    AND products_old.deleted_at IS NULL'
            . '   GROUP BY old_sku_values.value'
            . ' ) old_sku_lookup'
            . '   ON offers.sku IS NOT NULL'
            . '  AND offers.sku REGEXP "^[0-9]+$"'
            . '  AND old_sku_lookup.old_sku_value = offers.sku'
            . ' LEFT JOIN products warehouse'
            . '   ON warehouse.id = COALESCE(warehouse_sku.id, old_sku_lookup.product_id)';
    }

    public function allAccounts(): array
    {
        return $this->database->fetchAll(
            'SELECT accounts.*,'
            . ' tokens.expires_at AS token_expires_at,'
            . ' tokens.updated_at AS token_updated_at,'
            . ' sync.last_full_sync_at,'
            . ' sync.last_incremental_sync_at,'
            . ' sync.last_success_at,'
            . ' sync.last_error_at AS sync_last_error_at,'
            . ' sync.last_error_message AS sync_last_error_message,'
            . ' sync.is_running,'
            . ' sync.offer_offset'
            . ' FROM allegro_accounts accounts'
            . ' LEFT JOIN allegro_account_tokens tokens ON tokens.account_id = accounts.id'
            . ' LEFT JOIN allegro_sync_states sync ON sync.account_id = accounts.id'
            . ' ORDER BY accounts.name ASC, accounts.id ASC'
        );
    }

    public function findAccountById(int $id)
    {
        return $this->database->fetch(
            'SELECT * FROM allegro_accounts WHERE id = :id LIMIT 1',
            array('id' => $id)
        );
    }

    public function findAccountBySlug(string $slug)
    {
        return $this->database->fetch(
            'SELECT * FROM allegro_accounts WHERE slug = :slug LIMIT 1',
            array('slug' => $slug)
        );
    }

    public function findAccountBySyncToken(string $syncToken)
    {
        return $this->database->fetch(
            'SELECT * FROM allegro_accounts WHERE sync_token = :sync_token LIMIT 1',
            array('sync_token' => $syncToken)
        );
    }

    public function findAccountByOauthState(string $state)
    {
        return $this->database->fetch(
            'SELECT * FROM allegro_accounts WHERE oauth_state = :state AND (oauth_state_expires_at IS NULL OR oauth_state_expires_at >= :now) LIMIT 1',
            array(
                'state' => $state,
                'now' => date('Y-m-d H:i:s'),
            )
        );
    }

    public function firstAuthorizedAccount()
    {
        return $this->database->fetch(
            'SELECT accounts.*'
            . ' FROM allegro_accounts accounts'
            . ' INNER JOIN allegro_account_tokens tokens ON tokens.account_id = accounts.id'
            . ' WHERE accounts.is_active = 1'
            . ' ORDER BY accounts.last_auth_at DESC, accounts.id ASC'
            . ' LIMIT 1'
        );
    }

    public function saveAccount(array $payload, ?int $id = null): int
    {
        if ($id !== null) {
            $this->database->update('allegro_accounts', $payload, 'id = :id', array('id' => $id));
            return $id;
        }

        return (int) $this->database->insert('allegro_accounts', $payload);
    }

    public function storeOauthState(int $accountId, string $state, string $expiresAt): void
    {
        $this->database->update(
            'allegro_accounts',
            array(
                'oauth_state' => $state,
                'oauth_state_expires_at' => $expiresAt,
            ),
            'id = :id',
            array('id' => $accountId)
        );
    }

    public function clearOauthState(int $accountId): void
    {
        $this->database->update(
            'allegro_accounts',
            array(
                'oauth_state' => null,
                'oauth_state_expires_at' => null,
            ),
            'id = :id',
            array('id' => $accountId)
        );
    }

    public function tokenRowForAccount(int $accountId)
    {
        return $this->database->fetch(
            'SELECT * FROM allegro_account_tokens WHERE account_id = :account_id LIMIT 1',
            array('account_id' => $accountId)
        );
    }

    public function saveToken(int $accountId, array $tokenData): void
    {
        $existing = $this->tokenRowForAccount($accountId);
        $payload = array(
            'access_token' => (string) $tokenData['access_token'],
            'refresh_token' => isset($tokenData['refresh_token']) ? (string) $tokenData['refresh_token'] : null,
            'expires_at' => (string) $tokenData['expires_at'],
            'token_type' => isset($tokenData['token_type']) ? (string) $tokenData['token_type'] : null,
            'scope' => isset($tokenData['scope']) ? (string) $tokenData['scope'] : null,
        );

        if ($existing) {
            $this->database->update(
                'allegro_account_tokens',
                $payload,
                'account_id = :account_id',
                array('account_id' => $accountId)
            );
        } else {
            $payload['account_id'] = $accountId;
            $this->database->insert('allegro_account_tokens', $payload);
        }

        $this->database->update(
            'allegro_accounts',
            array(
                'last_auth_at' => date('Y-m-d H:i:s'),
                'last_error_at' => null,
                'last_error_message' => null,
            ),
            'id = :id',
            array('id' => $accountId)
        );
    }

    public function listOffers(array $filters, int $page, int $perPage, string $sortBy, string $sortDir): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $effectiveWarehouseQuantityFilter = $this->effectiveWarehouseQuantityFilter($filters);
        if (!empty($effectiveWarehouseQuantityFilter['active'])) {
            $matchingOfferIds = $this->matchingOfferIdsForEffectiveWarehouseQuantityFilter($filters, $sortBy, $sortDir);
            if ($matchingOfferIds === array()) {
                return array();
            }

            $offerIds = array_slice($matchingOfferIds, $offset, $perPage);
            if ($offerIds === array()) {
                return array();
            }

            return $this->loadOfferRowsByIds($offerIds, trim((string) ($filters['duplicates'] ?? '')) === '1', $filters);
        }

        $params = array();
        $analysis = $this->analyzeOfferFilters($filters, $sortBy);
        $includeDuplicateMeta = trim((string) ($filters['duplicates'] ?? '')) === '1';
        $whereSql = $this->buildOfferWhere($filters, $params);
        $sortSql = $this->buildOfferSort($sortBy, $sortDir);
        $idSql = 'SELECT offers.id'
            . ' FROM allegro_offers offers'
            . ' INNER JOIN allegro_accounts accounts ON accounts.id = offers.account_id';

        if ($analysis['needs_warehouse']) {
            $idSql .= $this->liveWarehouseJoinSql();
        }

        if ($analysis['needs_shared_stock']) {
            $idSql .= ' LEFT JOIN shared_stock_groups ON shared_stock_groups.id = warehouse.shared_stock_group_id';
        }

        $idSql .= $whereSql
            . ' ORDER BY ' . $sortSql
            . ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;

        $idRows = $this->database->fetchAll($idSql, $params);
        if ($idRows === array()) {
            return array();
        }

        $offerIds = array_map(static function (array $row): int {
            return (int) ($row['id'] ?? 0);
        }, $idRows);

        return $this->loadOfferRowsByIds($offerIds, $includeDuplicateMeta, $filters);
    }

    public function countOffers(array $filters): int
    {
        $effectiveWarehouseQuantityFilter = $this->effectiveWarehouseQuantityFilter($filters);
        if (!empty($effectiveWarehouseQuantityFilter['active'])) {
            return count($this->matchingOfferIdsForEffectiveWarehouseQuantityFilter($filters));
        }

        $cacheKey = $this->offerCountCacheKey($filters);
        $cached = $this->getCache($cacheKey);
        if (is_array($cached) && isset($cached['total'])) {
            return (int) $cached['total'];
        }

        $params = array();
        $analysis = $this->analyzeOfferFilters($filters);
        $whereSql = $this->buildOfferWhere($filters, $params);
        $sql = 'SELECT COUNT(*) FROM allegro_offers offers'
            . ' INNER JOIN allegro_accounts accounts ON accounts.id = offers.account_id';

        if ($analysis['needs_warehouse']) {
            $sql .= $this->liveWarehouseJoinSql();
        }

        if ($analysis['needs_shared_stock']) {
            $sql .= ' LEFT JOIN shared_stock_groups ON shared_stock_groups.id = warehouse.shared_stock_group_id';
        }

        $total = (int) $this->database->fetchColumn($sql . $whereSql, $params);
        $this->putCache($cacheKey, array('total' => $total), self::OFFER_COUNT_CACHE_TTL);

        return $total;
    }

    private function loadOfferRowsByIds(array $offerIds, bool $includeDuplicateMeta, array $filters): array
    {
        $detailParams = array();
        $placeholders = $this->buildIntegerPlaceholders('offer_id', $offerIds, $detailParams);
        $sql = 'SELECT offers.id, offers.account_id, offers.offer_id, offers.sku, offers.external_id, offers.warehouse_product_id,'
            . ' offers.linked_by, warehouse.id AS warehouse_product_live_id, offers.name, offers.primary_image_url, offers.primary_image_hash, offers.image_count,'
            . ' offers.price_amount, offers.price_currency, offers.publication_status, offers.publication_ended_by,'
            . ' offers.stock_available, offers.stock_sold, offers.invoice_type, offers.allegro_product_id,'
            . ' offers.category_id, offers.category_name, offers.marketplaces_json,'
            . ' offers.last_synced_at, offers.created_at, offers.updated_at,'
            . ' accounts.name AS account_name, accounts.slug AS account_slug,'
            . ' warehouse.sku AS warehouse_sku, warehouse.product_name AS warehouse_product_name, warehouse.price_gross AS warehouse_price_gross, warehouse.vat_rate AS warehouse_vat_rate,'
            . ' warehouse.category_id AS warehouse_category_id, warehouse_categories.name AS warehouse_category_name, warehouse_categories.allegro_category_id AS warehouse_category_allegro_id,'
            . ' COALESCE(shared_stock_groups.quantity, warehouse.quantity) AS warehouse_quantity,'
            . ' COALESCE(shared_stock_groups.localization, warehouse.localization) AS warehouse_localization'
            . ' FROM allegro_offers offers'
            . ' INNER JOIN allegro_accounts accounts ON accounts.id = offers.account_id'
            . $this->liveWarehouseJoinSql()
            . ' LEFT JOIN categories warehouse_categories ON warehouse_categories.id = warehouse.category_id'
            . ' LEFT JOIN shared_stock_groups ON shared_stock_groups.id = warehouse.shared_stock_group_id'
            . ' WHERE offers.id IN (' . implode(', ', $placeholders) . ')';

        $rows = $this->database->fetchAll($sql, $detailParams);
        $rowsById = array();
        foreach ($rows as $row) {
            $rowsById[(int) ($row['id'] ?? 0)] = $row;
        }

        $orderedRows = array();
        foreach ($offerIds as $offerId) {
            if (isset($rowsById[$offerId])) {
                $orderedRows[] = $rowsById[$offerId];
            }
        }

        foreach ($orderedRows as &$row) {
            $row['marketplaces'] = $this->decodeJsonList($row['marketplaces_json'] ?? null);
        }

        unset($row);

        $queueMetaByOfferId = $this->latestQueueMetaForOffers($offerIds);
        $duplicateMetaByOfferId = $includeDuplicateMeta
            ? $this->duplicateMetaForOffers($offerIds, $filters)
            : array();
        foreach ($orderedRows as $index => $row) {
            $offerId = (int) ($row['id'] ?? 0);
            $orderedRows[$index]['queue_meta'] = $queueMetaByOfferId[$offerId] ?? array();
            $orderedRows[$index]['duplicate_meta'] = $duplicateMetaByOfferId[$offerId] ?? array(
                'is_duplicate' => false,
                'duplicate_count' => 0,
            );
            $orderedRows[$index] = $this->normalizeOfferViewData($orderedRows[$index]);
        }

        return $this->applyEffectiveWarehouseStockToRows($orderedRows);
    }

    private function matchingOfferIdsForEffectiveWarehouseQuantityFilter(array $filters, string $sortBy = 'id', string $sortDir = 'desc'): array
    {
        $filter = $this->effectiveWarehouseQuantityFilter($filters);
        if (empty($filter['active'])) {
            return array();
        }

        $baseFilters = $this->filtersWithoutWarehouseQuantity($filters);
        $params = array();
        $analysis = $this->analyzeOfferFilters($baseFilters, $sortBy);
        $whereSql = $this->buildOfferWhere($baseFilters, $params);
        $sortSql = $sortBy === 'warehouse_quantity'
            ? 'offers.id DESC'
            : $this->buildOfferSort($sortBy, $sortDir);
        $sql = 'SELECT offers.id, warehouse.id AS warehouse_product_live_id'
            . ' FROM allegro_offers offers'
            . ' INNER JOIN allegro_accounts accounts ON accounts.id = offers.account_id'
            . $this->liveWarehouseJoinSql();

        if ($analysis['needs_shared_stock']) {
            $sql .= ' LEFT JOIN shared_stock_groups ON shared_stock_groups.id = warehouse.shared_stock_group_id';
        }

        $sql .= $whereSql . ' ORDER BY ' . $sortSql;

        $candidateRows = $this->database->fetchAll($sql, $params);
        if ($candidateRows === array()) {
            return array();
        }

        $productIds = array_values(array_unique(array_filter(array_map(static function (array $row): int {
            return isset($row['warehouse_product_live_id']) ? (int) $row['warehouse_product_live_id'] : 0;
        }, $candidateRows), static function (int $id): bool {
            return $id > 0;
        })));

        $effectiveStock = array();
        if ($productIds !== array()) {
            $products = new ProductRepository($this->database);
            $products->ensureSchema();
            $effectiveStock = $products->effectiveStockByProductIds($productIds);
        }

        $matchingRows = array();
        foreach ($candidateRows as $row) {
            $productId = isset($row['warehouse_product_live_id']) ? (int) $row['warehouse_product_live_id'] : 0;
            if ($productId <= 0 || !isset($effectiveStock[$productId])) {
                continue;
            }

            $quantity = isset($effectiveStock[$productId]['quantity']) ? (int) $effectiveStock[$productId]['quantity'] : 0;
            if (!$this->matchesEffectiveWarehouseQuantityFilter($quantity, $filter)) {
                continue;
            }

            $row['effective_warehouse_quantity'] = $quantity;
            $matchingRows[] = $row;
        }

        if ($sortBy === 'warehouse_quantity') {
            $direction = strtolower($sortDir) === 'asc' ? 1 : -1;
            usort($matchingRows, static function (array $left, array $right) use ($direction): int {
                $quantityComparison = ((int) ($left['effective_warehouse_quantity'] ?? 0)) <=> ((int) ($right['effective_warehouse_quantity'] ?? 0));
                if ($quantityComparison !== 0) {
                    return $quantityComparison * $direction;
                }

                return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
            });
        }

        return array_values(array_map(static function (array $row): int {
            return (int) ($row['id'] ?? 0);
        }, $matchingRows));
    }

    public function offerStats(?int $accountId = null): array
    {
        $cacheKey = 'allegro:offer-stats:' . ($accountId !== null ? (string) $accountId : 'all');
        $cached = $this->getCache($cacheKey);
        if (is_array($cached) && isset($cached['all'])) {
            return $cached;
        }

        $params = array();
        $where = '';

        if ($accountId !== null) {
            $where = ' WHERE account_id = :account_id';
            $params['account_id'] = $accountId;
        }

        $rows = $this->database->fetchAll(
            'SELECT publication_status, COUNT(*) AS total FROM allegro_offers' . $where . ' GROUP BY publication_status',
            $params
        );

        $result = array(
            'all' => 0,
            'active' => 0,
            'ended' => 0,
            'inactive' => 0,
        );

        foreach ($rows as $row) {
            $status = strtoupper((string) ($row['publication_status'] ?? ''));
            $total = (int) ($row['total'] ?? 0);
            $result['all'] += $total;

            if ($status === 'ACTIVE') {
                $result['active'] += $total;
            } elseif ($status === 'ENDED') {
                $result['ended'] += $total;
            } else {
                $result['inactive'] += $total;
            }
        }

        $this->putCache($cacheKey, $result, self::STATS_CACHE_TTL);

        return $result;
    }

    public function findOfferById(int $id)
    {
        $row = $this->database->fetch(
            'SELECT offers.*, warehouse.id AS warehouse_product_live_id, accounts.name AS account_name, accounts.slug AS account_slug,'
            . ' warehouse.sku AS warehouse_sku, warehouse.product_name AS warehouse_product_name, warehouse.price_gross AS warehouse_price_gross, warehouse.vat_rate AS warehouse_vat_rate,'
            . ' warehouse.category_id AS warehouse_category_id, warehouse_categories.name AS warehouse_category_name, warehouse_categories.allegro_category_id AS warehouse_category_allegro_id,'
            . ' COALESCE(shared_stock_groups.quantity, warehouse.quantity) AS warehouse_quantity,'
            . ' COALESCE(shared_stock_groups.localization, warehouse.localization) AS warehouse_localization'
            . ' FROM allegro_offers offers'
            . ' INNER JOIN allegro_accounts accounts ON accounts.id = offers.account_id'
            . $this->liveWarehouseJoinSql()
            . ' LEFT JOIN categories warehouse_categories ON warehouse_categories.id = warehouse.category_id'
            . ' LEFT JOIN shared_stock_groups ON shared_stock_groups.id = warehouse.shared_stock_group_id'
            . ' WHERE offers.id = :id LIMIT 1',
            array('id' => $id)
        );

        if (!$row) {
            return null;
        }

        $row['images'] = $this->decodeJsonList($row['images_json'] ?? null);
        $row['parameters'] = $this->decodeJsonList($row['parameters_json'] ?? null);
        $row['marketplaces'] = $this->decodeJsonList($row['marketplaces_json'] ?? null);
        $row['product_set'] = $this->decodeJsonList($row['product_set_json'] ?? null);
        $row['offer_payload'] = $this->decodeJsonAny($row['offer_json'] ?? null);

        return $this->applyEffectiveWarehouseStockToRow($this->normalizeOfferViewData($row));
    }

    public function findOfferByAccountAndOfferId(int $accountId, string $offerId)
    {
        $offerId = trim($offerId);
        if ($accountId <= 0 || $offerId === '') {
            return null;
        }

        $row = $this->database->fetch(
            'SELECT offers.*, warehouse.id AS warehouse_product_live_id,'
            . ' warehouse.sku AS warehouse_sku, warehouse.product_name AS warehouse_product_name, warehouse.price_gross AS warehouse_price_gross,'
            . ' warehouse.category_id AS warehouse_category_id, warehouse_categories.name AS warehouse_category_name, warehouse_categories.allegro_category_id AS warehouse_category_allegro_id,'
            . ' COALESCE(shared_stock_groups.quantity, warehouse.quantity) AS warehouse_quantity,'
            . ' COALESCE(shared_stock_groups.localization, warehouse.localization) AS warehouse_localization'
            . ' FROM allegro_offers offers'
            . $this->liveWarehouseJoinSql()
            . ' LEFT JOIN categories warehouse_categories ON warehouse_categories.id = warehouse.category_id'
            . ' LEFT JOIN shared_stock_groups ON shared_stock_groups.id = warehouse.shared_stock_group_id'
            . ' WHERE offers.account_id = :account_id AND offers.offer_id = :offer_id LIMIT 1',
            array(
                'account_id' => $accountId,
                'offer_id' => $offerId,
            )
        );

        if (!$row) {
            return null;
        }

        $row['images'] = $this->decodeJsonList($row['images_json'] ?? null);
        $row['parameters'] = $this->decodeJsonList($row['parameters_json'] ?? null);
        $row['marketplaces'] = $this->decodeJsonList($row['marketplaces_json'] ?? null);
        $row['product_set'] = $this->decodeJsonList($row['product_set_json'] ?? null);
        $row['offer_payload'] = $this->decodeJsonAny($row['offer_json'] ?? null);

        return $this->applyEffectiveWarehouseStockToRow($this->normalizeOfferViewData($row));
    }

    public function findOfferChecksums(int $accountId, array $offerIds): array
    {
        $cleanIds = array();
        foreach ($offerIds as $offerId) {
            $offerId = trim((string) $offerId);
            if ($offerId !== '') {
                $cleanIds[] = $offerId;
            }
        }

        if ($cleanIds === array()) {
            return array();
        }

        $placeholders = array();
        $params = array('account_id' => $accountId);
        foreach ($cleanIds as $index => $offerId) {
            $key = 'offer_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $offerId;
        }

        $sql = 'SELECT offer_id, summary_checksum, details_checksum FROM allegro_offers'
            . ' WHERE account_id = :account_id AND offer_id IN (' . implode(', ', $placeholders) . ')';

        $rows = $this->database->fetchAll($sql, $params);
        $map = array();

        foreach ($rows as $row) {
            $map[(string) $row['offer_id']] = $row;
        }

        return $map;
    }

    public function upsertOffer(array $payload): void
    {
        $sql = 'INSERT INTO allegro_offers ('
            . 'account_id, offer_id, sku, external_id, warehouse_product_id, linked_by, name, primary_image_url, image_count, images_json, price_amount, price_currency,'
            . ' primary_image_hash,'
            . ' publication_status, publication_ended_by, stock_available, stock_sold, invoice_type, allegro_product_id, category_id, category_name, marketplaces_json, product_set_json,'
            . ' parameters_json, offer_json, summary_checksum, details_checksum, last_seen_cycle, last_event_id, last_event_at, last_synced_at'
            . ') VALUES ('
            . ' :account_id, :offer_id, :sku, :external_id, :warehouse_product_id, :linked_by, :name, :primary_image_url, :image_count, :images_json, :price_amount, :price_currency,'
            . ' :primary_image_hash,'
            . ' :publication_status, :publication_ended_by, :stock_available, :stock_sold, :invoice_type, :allegro_product_id, :category_id, :category_name, :marketplaces_json, :product_set_json,'
            . ' :parameters_json, :offer_json, :summary_checksum, :details_checksum, :last_seen_cycle, :last_event_id, :last_event_at, :last_synced_at'
            . ') ON DUPLICATE KEY UPDATE'
            . ' sku = VALUES(sku),'
            . ' external_id = VALUES(external_id),'
            . ' warehouse_product_id = VALUES(warehouse_product_id),'
            . ' linked_by = VALUES(linked_by),'
            . ' name = VALUES(name),'
            . ' primary_image_url = VALUES(primary_image_url),'
            . ' primary_image_hash = VALUES(primary_image_hash),'
            . ' image_count = VALUES(image_count),'
            . ' images_json = VALUES(images_json),'
            . ' price_amount = VALUES(price_amount),'
            . ' price_currency = VALUES(price_currency),'
            . ' publication_status = VALUES(publication_status),'
            . ' publication_ended_by = VALUES(publication_ended_by),'
            . ' stock_available = VALUES(stock_available),'
            . ' stock_sold = VALUES(stock_sold),'
            . ' invoice_type = VALUES(invoice_type),'
            . ' allegro_product_id = VALUES(allegro_product_id),'
            . ' category_id = VALUES(category_id),'
            . ' category_name = VALUES(category_name),'
            . ' marketplaces_json = VALUES(marketplaces_json),'
            . ' product_set_json = VALUES(product_set_json),'
            . ' parameters_json = VALUES(parameters_json),'
            . ' offer_json = VALUES(offer_json),'
            . ' summary_checksum = VALUES(summary_checksum),'
            . ' details_checksum = VALUES(details_checksum),'
            . ' last_seen_cycle = VALUES(last_seen_cycle),'
            . ' last_event_id = VALUES(last_event_id),'
            . ' last_event_at = VALUES(last_event_at),'
            . ' last_synced_at = VALUES(last_synced_at)';

        $this->database->query($sql, $payload);
    }

    public function clearStoredOfferLinks(): int
    {
        return $this->database->query(
            'UPDATE allegro_offers'
            . ' SET warehouse_product_id = NULL, linked_by = NULL'
            . ' WHERE warehouse_product_id IS NOT NULL OR linked_by IS NOT NULL'
        )->rowCount();
    }

    public function fetchOffersForCompaction(int $limit = 500, ?int $accountId = null): array
    {
        $limit = max(1, min(5000, $limit));
        $params = array();
        $sql = 'SELECT id, account_id, offer_id, offer_json, product_set_json, marketplaces_json'
            . ' FROM allegro_offers'
            . ' WHERE ('
            . ' (offer_json IS NOT NULL AND offer_json <> "" AND (offer_json LIKE :offer_json_description OR offer_json LIKE :offer_json_sections OR offer_json LIKE :offer_json_safety))'
            . ' OR (product_set_json IS NOT NULL AND product_set_json <> "" AND product_set_json LIKE :product_set_parameters)'
            . ' OR (marketplaces_json IS NOT NULL AND marketplaces_json LIKE :marketplaces_array)'
            . ' )';
        $params['offer_json_description'] = '%"description"%';
        $params['offer_json_sections'] = '%"sections"%';
        $params['offer_json_safety'] = '%"safetyInformation"%';
        $params['product_set_parameters'] = '%"parameters"%';
        $params['marketplaces_array'] = '%Array%';

        if ($accountId !== null) {
            $sql .= ' AND account_id = :account_id';
            $params['account_id'] = $accountId;
        }

        $sql .= ' ORDER BY id ASC LIMIT ' . $limit;
        return $this->database->fetchAll($sql, $params);
    }

    public function updateOfferCompactedPayloads(int $id, ?string $offerJson, ?string $productSetJson, ?string $marketplacesJson): void
    {
        $this->database->update(
            'allegro_offers',
            array(
                'offer_json' => $offerJson,
                'product_set_json' => $productSetJson,
                'marketplaces_json' => $marketplacesJson,
            ),
            'id = :id',
            array('id' => $id)
        );
    }

    public function touchOffer(int $accountId, string $offerId, string $cycle, ?string $eventId = null, ?string $eventAt = null): void
    {
        $this->database->update(
            'allegro_offers',
            array(
                'last_seen_cycle' => $cycle,
                'last_event_id' => $eventId,
                'last_event_at' => $eventAt,
                'last_synced_at' => date('Y-m-d H:i:s'),
            ),
            'account_id = :account_id AND offer_id = :offer_id',
            array(
                'account_id' => $accountId,
                'offer_id' => $offerId,
            )
        );
    }

    public function linkOfferToProduct(int $offerRowId, ?int $productId, string $linkedBy = 'manual'): void
    {
        $this->database->update(
            'allegro_offers',
            array(
                'warehouse_product_id' => $productId !== null && $productId > 0 ? $productId : null,
                'linked_by' => $productId !== null && $productId > 0 ? $linkedBy : null,
            ),
            'id = :id',
            array('id' => $offerRowId)
        );
    }

    public function autoLinkOffersBySku(?int $accountId = null, int $limit = 500): int
    {
        $limit = max(1, min(5000, $limit));
        $params = array();
        $where = 'offers.warehouse_product_id IS NULL AND offers.sku IS NOT NULL AND offers.sku <> "" AND products.deleted_at IS NULL';

        if ($accountId !== null) {
            $where .= ' AND offers.account_id = :account_id';
            $params['account_id'] = $accountId;
        }

        $rows = $this->database->fetchAll(
            'SELECT offers.id AS offer_row_id, products.id AS product_id'
            . ' FROM allegro_offers offers'
            . ' INNER JOIN products ON products.sku = offers.sku'
            . ' WHERE ' . $where
            . ' ORDER BY offers.id ASC'
            . ' LIMIT ' . $limit,
            $params
        );

        $count = 0;
        foreach ($rows as $row) {
            $offerRowId = isset($row['offer_row_id']) ? (int) $row['offer_row_id'] : 0;
            $productId = isset($row['product_id']) ? (int) $row['product_id'] : 0;
            if ($offerRowId <= 0 || $productId <= 0) {
                continue;
            }

            $this->linkOfferToProduct($offerRowId, $productId, 'sku');
            $count++;
        }

        return $count;
    }

    public function unlinkedOffersForAutoLink(?int $accountId = null, int $limit = 500): array
    {
        $limit = max(1, min(5000, $limit));
        $params = array();
        $where = 'offers.warehouse_product_id IS NULL AND offers.sku IS NOT NULL AND offers.sku <> ""';

        if ($accountId !== null) {
            $where .= ' AND offers.account_id = :account_id';
            $params['account_id'] = $accountId;
        }

        return $this->database->fetchAll(
            'SELECT offers.id, offers.account_id, offers.offer_id, offers.sku, offers.name'
            . ' FROM allegro_offers offers'
            . ' WHERE ' . $where
            . ' ORDER BY offers.id ASC'
            . ' LIMIT ' . $limit,
            $params
        );
    }

    public function resolveOfferTargets(array $identifiers, ?int $accountId = null): array
    {
        $tokens = array_values(array_unique(array_filter(array_map(static function ($value): string {
            return trim((string) $value);
        }, $identifiers))));

        if ($tokens === array()) {
            return array();
        }

        $placeholders = array();
        $params = array();
        foreach ($tokens as $index => $token) {
            $key = 'token_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $token;
        }

        $sql = 'SELECT id, account_id, offer_id FROM allegro_offers WHERE (offer_id IN (' . implode(', ', $placeholders) . ')';
        $numericTokens = array_values(array_filter($tokens, 'ctype_digit'));
        if ($numericTokens !== array()) {
            $idPlaceholders = array();
            foreach ($numericTokens as $index => $token) {
                $key = 'id_token_' . $index;
                $idPlaceholders[] = ':' . $key;
                $params[$key] = (int) $token;
            }
            $sql .= ' OR id IN (' . implode(', ', $idPlaceholders) . ')';
        }
        $sql .= ')';

        if ($accountId !== null) {
            $sql .= ' AND account_id = :account_id';
            $params['account_id'] = $accountId;
        }

        $sql .= ' ORDER BY id ASC';
        return $this->database->fetchAll($sql, $params);
    }

    public function offerTargetsForFilters(array $filters, ?int $limit = null): array
    {
        $params = array();
        $whereSql = $this->buildOfferWhere($filters, $params);
        $sql = 'SELECT offers.id, offers.account_id, offers.offer_id'
            . ' FROM allegro_offers offers'
            . ' INNER JOIN allegro_accounts accounts ON accounts.id = offers.account_id'
            . $this->liveWarehouseJoinSql()
            . ' LEFT JOIN shared_stock_groups ON shared_stock_groups.id = warehouse.shared_stock_group_id'
            . $whereSql
            . ' ORDER BY offers.id ASC';

        if ($limit !== null && $limit > 0) {
            $sql .= ' LIMIT ' . max(1, min(50000, $limit));
        }

        return $this->database->fetchAll($sql, $params);
    }

    public function previewAutoEndOfferCandidates(?int $accountId = null, int $limit = 1000, int $scanLimit = 3000): array
    {
        $limit = max(1, min(5000, $limit));
        $scanLimit = max($limit, min(20000, $scanLimit));

        $productRows = $this->previewAutoEndCandidateProducts($scanLimit);
        if ($productRows === array()) {
            return array(
                'items' => array(),
                'scanned_rows' => 0,
                'has_more_candidates' => false,
                'scan_limit_reached' => false,
            );
        }

        $productIds = array_map(static function (array $row): int {
            return (int) ($row['id'] ?? 0);
        }, $productRows);
        $products = new ProductRepository($this->database);
        $products->ensureSchema();
        $effectiveStock = $products->effectiveStockByProductIds($productIds);

        $eligibleProducts = array();
        foreach ($productRows as $row) {
            $productId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($productId <= 0 || !isset($effectiveStock[$productId])) {
                continue;
            }

            $threshold = isset($row['category_end_offers_below_quantity']) ? (int) $row['category_end_offers_below_quantity'] : null;
            if ($threshold === null) {
                continue;
            }

            $quantity = isset($effectiveStock[$productId]['quantity']) ? (int) $effectiveStock[$productId]['quantity'] : 0;
            if ($quantity > $threshold) {
                continue;
            }

            $eligibleProducts[$productId] = array(
                'id' => $productId,
                'sku' => (string) ($row['sku'] ?? ''),
                'old_sku' => (string) ($row['old_sku'] ?? ''),
                'product_name' => (string) ($row['product_name'] ?? ''),
                'category_id' => isset($row['category_id']) ? (int) $row['category_id'] : null,
                'category_name' => (string) ($row['category_name'] ?? ''),
                'category_end_offers_below_quantity' => $threshold,
                'warehouse_quantity' => $quantity,
                'warehouse_localization' => (string) ($effectiveStock[$productId]['localization'] ?? ''),
                'difference_to_threshold' => $threshold - $quantity,
            );
        }

        if ($eligibleProducts === array()) {
            return array(
                'items' => array(),
                'scanned_rows' => count($productRows),
                'has_more_candidates' => count($productRows) >= $scanLimit,
                'scan_limit_reached' => count($productRows) >= $scanLimit,
            );
        }

        $offerRows = $this->previewAutoEndOffersForProducts($eligibleProducts, $accountId, $limit);
        if ($offerRows === array()) {
            return array(
                'items' => array(),
                'scanned_rows' => count($productRows),
                'has_more_candidates' => count($productRows) >= $scanLimit,
                'scan_limit_reached' => count($productRows) >= $scanLimit,
            );
        }

        foreach ($offerRows as $index => $row) {
            $productId = isset($row['warehouse_product_id']) ? (int) $row['warehouse_product_id'] : 0;
            if ($productId > 0 && isset($eligibleProducts[$productId])) {
                $offerRows[$index]['warehouse_quantity'] = $eligibleProducts[$productId]['warehouse_quantity'];
                $offerRows[$index]['warehouse_localization'] = $eligibleProducts[$productId]['warehouse_localization'];
                $offerRows[$index]['category_end_offers_below_quantity'] = $eligibleProducts[$productId]['category_end_offers_below_quantity'];
                $offerRows[$index]['difference_to_threshold'] = $eligibleProducts[$productId]['difference_to_threshold'];
            }
        }

        $targets = array_map(static function (array $row): array {
            return array(
                'id' => (int) ($row['id'] ?? 0),
                'account_id' => (int) ($row['account_id'] ?? 0),
                'offer_id' => (string) ($row['offer_id'] ?? ''),
            );
        }, $offerRows);

        $duplicateResult = $this->filterTerminableEndOfferTargets($targets);
        $allowedMap = array();
        foreach ($duplicateResult['allowed'] ?? array() as $target) {
            $allowedMap[(int) ($target['id'] ?? 0)] = true;
        }

        $blockedMap = array();
        foreach ($duplicateResult['blocked'] ?? array() as $blocked) {
            $blockedMap[(int) ($blocked['id'] ?? 0)] = $blocked;
        }

        foreach ($offerRows as $index => $row) {
            $offerRowId = isset($row['id']) ? (int) $row['id'] : 0;
            $offerRows[$index]['can_end_offer'] = isset($allowedMap[$offerRowId]);
            $offerRows[$index]['duplicate_block'] = $blockedMap[$offerRowId] ?? null;
        }

        return array(
            'items' => array_slice($offerRows, 0, $limit),
            'scanned_rows' => count($productRows),
            'has_more_candidates' => count($productRows) >= $scanLimit || count($offerRows) >= $limit,
            'scan_limit_reached' => count($productRows) >= $scanLimit,
        );
    }

    private function previewAutoEndCandidateProducts(int $scanLimit): array
    {
        $params = array(
            'old_sku_slug' => 'old_sku',
        );
        $sql = 'SELECT products.id, products.sku, products.product_name, products.category_id,'
            . ' categories.name AS category_name, categories.end_offers_below_quantity AS category_end_offers_below_quantity,'
            . ' old_sku_values.value AS old_sku'
            . ' FROM products'
            . ' INNER JOIN categories ON categories.id = products.category_id'
            . ' LEFT JOIN product_custom_field_definitions old_sku_definition ON old_sku_definition.slug = :old_sku_slug'
            . ' LEFT JOIN product_custom_field_values old_sku_values'
            . '   ON old_sku_values.product_id = products.id AND old_sku_values.definition_id = old_sku_definition.id'
            . ' WHERE products.deleted_at IS NULL'
            . '   AND categories.end_offers_below_quantity IS NOT NULL'
            . ' ORDER BY products.id DESC'
            . ' LIMIT ' . $scanLimit;

        return $this->database->fetchAll($sql, $params);
    }

    private function previewAutoEndOffersForProducts(array $eligibleProducts, ?int $accountId, int $limit): array
    {
        if ($eligibleProducts === array()) {
            return array();
        }

        $skuToProduct = array();
        $skuValues = array();
        foreach ($eligibleProducts as $productId => $product) {
            $sku = trim((string) ($product['sku'] ?? ''));
            $oldSku = trim((string) ($product['old_sku'] ?? ''));
            if ($sku !== '') {
                $skuToProduct[$sku] = (int) $productId;
                $skuValues[$sku] = $sku;
            }
            if ($oldSku !== '') {
                $skuToProduct[$oldSku] = (int) $productId;
                $skuValues[$oldSku] = $oldSku;
            }
        }

        if ($skuValues === array()) {
            return array();
        }

        $rows = array();
        foreach (array_chunk(array_values($skuValues), 200) as $chunkIndex => $skuChunk) {
            $params = array();
            $placeholders = array();
            foreach ($skuChunk as $skuIndex => $skuValue) {
                $key = 'preview_sku_' . $chunkIndex . '_' . $skuIndex;
                $placeholders[] = ':' . $key;
                $params[$key] = $skuValue;
            }

            if ($placeholders === array()) {
                continue;
            }

            $sql = 'SELECT offers.id, offers.account_id, offers.offer_id, offers.sku, offers.name, offers.publication_status,'
                . ' offers.linked_by, accounts.name AS account_name, accounts.slug AS account_slug'
                . ' FROM allegro_offers offers'
                . ' INNER JOIN allegro_accounts accounts ON accounts.id = offers.account_id'
                . ' WHERE accounts.is_active = 1'
                . '   AND offers.publication_status = "ACTIVE"'
                . '   AND offers.sku IN (' . implode(', ', $placeholders) . ')';

            if ($accountId !== null && $accountId > 0) {
                $sql .= ' AND offers.account_id = :account_id';
                $params['account_id'] = $accountId;
            }

            $sql .= ' ORDER BY offers.id DESC';
            $chunkRows = $this->database->fetchAll($sql, $params);
            foreach ($chunkRows as $row) {
                $offerSku = trim((string) ($row['sku'] ?? ''));
                $productId = $skuToProduct[$offerSku] ?? 0;
                if ($productId <= 0 || !isset($eligibleProducts[$productId])) {
                    continue;
                }

                $product = $eligibleProducts[$productId];
                $rows[] = array(
                    'id' => (int) ($row['id'] ?? 0),
                    'account_id' => (int) ($row['account_id'] ?? 0),
                    'offer_id' => (string) ($row['offer_id'] ?? ''),
                    'sku' => (string) ($row['sku'] ?? ''),
                    'name' => (string) ($row['name'] ?? ''),
                    'publication_status' => (string) ($row['publication_status'] ?? ''),
                    'warehouse_product_live_id' => $productId,
                    'warehouse_product_id' => $productId,
                    'linked_by' => (string) ($row['linked_by'] ?? 'sku'),
                    'warehouse_sku' => (string) ($product['sku'] ?? ''),
                    'warehouse_product_name' => (string) ($product['product_name'] ?? ''),
                    'warehouse_category_id' => $product['category_id'] ?? null,
                    'warehouse_category_name' => (string) ($product['category_name'] ?? ''),
                    'account_name' => (string) ($row['account_name'] ?? ''),
                    'account_slug' => (string) ($row['account_slug'] ?? ''),
                );
            }
        }

        usort($rows, static function (array $left, array $right): int {
            return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
        });

        return array_slice($rows, 0, $limit);
    }

    public function diagnoseAutoEndOffer(string $offerIdentifier): ?array
    {
        $offerIdentifier = trim($offerIdentifier);
        if ($offerIdentifier === '') {
            return null;
        }

        $params = array(
            'offer_identifier_offer_id' => $offerIdentifier,
            'offer_identifier_sku' => $offerIdentifier,
        );
        $sql = 'SELECT offers.id, offers.account_id, offers.offer_id, offers.sku, offers.name, offers.publication_status,'
            . ' offers.warehouse_product_id, offers.linked_by,'
            . ' accounts.name AS account_name, accounts.slug AS account_slug,'
            . ' warehouse.id AS warehouse_product_live_id, warehouse.sku AS warehouse_sku, warehouse.product_name AS warehouse_product_name,'
            . ' warehouse.category_id AS warehouse_category_id, warehouse_categories.name AS warehouse_category_name,'
            . ' warehouse_categories.end_offers_below_quantity AS category_end_offers_below_quantity,'
            . ' COALESCE(shared_stock_groups.quantity, warehouse.quantity) AS warehouse_quantity,'
            . ' COALESCE(shared_stock_groups.localization, warehouse.localization) AS warehouse_localization'
            . ' FROM allegro_offers offers'
            . ' INNER JOIN allegro_accounts accounts ON accounts.id = offers.account_id'
            . $this->liveWarehouseJoinSql()
            . ' LEFT JOIN categories warehouse_categories ON warehouse_categories.id = warehouse.category_id'
            . ' LEFT JOIN shared_stock_groups ON shared_stock_groups.id = warehouse.shared_stock_group_id'
            . ' WHERE offers.offer_id = :offer_identifier_offer_id OR offers.sku = :offer_identifier_sku';

        if (ctype_digit($offerIdentifier)) {
            $sql .= ' OR offers.id = :offer_row_id';
            $params['offer_row_id'] = (int) $offerIdentifier;
        }

        $sql .= ' ORDER BY offers.id DESC LIMIT 1';
        $row = $this->database->fetch($sql, $params);
        if (!$row) {
            return null;
        }

        $row = $this->normalizeOfferViewData($row);
        $row = $this->applyEffectiveWarehouseStockToRow($row);

        $reasons = array();
        $isActive = strtoupper((string) ($row['publication_status'] ?? '')) === 'ACTIVE';
        if (!$isActive) {
            $reasons[] = 'status_not_active';
        }

        $hasLiveWarehouseLink = !empty($row['warehouse_product_id']);
        if (!$hasLiveWarehouseLink) {
            $reasons[] = 'missing_live_warehouse_link';
        }

        $thresholdRaw = $row['category_end_offers_below_quantity'] ?? null;
        $threshold = $thresholdRaw !== null && $thresholdRaw !== '' ? (int) $thresholdRaw : null;
        if ($threshold === null) {
            $reasons[] = 'missing_category_threshold';
        }

        $quantity = isset($row['warehouse_quantity']) ? (int) $row['warehouse_quantity'] : 0;
        if ($threshold !== null && $quantity > $threshold) {
            $reasons[] = 'quantity_above_threshold';
        }

        $duplicateMeta = $this->filterTerminableEndOfferTargets(array(array(
            'id' => (int) ($row['id'] ?? 0),
            'account_id' => (int) ($row['account_id'] ?? 0),
            'offer_id' => (string) ($row['offer_id'] ?? ''),
        )));
        $duplicateBlock = null;
        if (!empty($duplicateMeta['blocked'][0])) {
            $duplicateBlock = $duplicateMeta['blocked'][0];
            $reasons[] = 'blocked_by_duplicates';
        }

        $eligible = $reasons === array();

        return array(
            'id' => (int) ($row['id'] ?? 0),
            'account_id' => (int) ($row['account_id'] ?? 0),
            'account_name' => (string) ($row['account_name'] ?? ''),
            'account_slug' => (string) ($row['account_slug'] ?? ''),
            'offer_id' => (string) ($row['offer_id'] ?? ''),
            'offer_name' => (string) ($row['name'] ?? ''),
            'offer_sku' => (string) ($row['sku'] ?? ''),
            'publication_status' => (string) ($row['publication_status'] ?? ''),
            'warehouse_product_id' => isset($row['warehouse_product_id']) ? (int) $row['warehouse_product_id'] : null,
            'warehouse_sku' => (string) ($row['warehouse_sku'] ?? ''),
            'warehouse_product_name' => (string) ($row['warehouse_product_name'] ?? ''),
            'warehouse_category_id' => isset($row['warehouse_category_id']) ? (int) $row['warehouse_category_id'] : null,
            'warehouse_category_name' => (string) ($row['warehouse_category_name'] ?? ''),
            'warehouse_quantity' => $quantity,
            'warehouse_localization' => (string) ($row['warehouse_localization'] ?? ''),
            'end_offers_below_quantity' => $threshold,
            'can_end_offer' => $eligible,
            'reasons' => $reasons,
            'duplicate_block' => $duplicateBlock,
        );
    }

    public function enqueueOfferChanges(array $targets, string $operation, array $payload, ?string $availableAt = null, bool $deduplicatePending = false): int
    {
        $queued = 0;
        $availableAt = $availableAt !== null && trim($availableAt) !== '' ? trim($availableAt) : date('Y-m-d H:i:s');

        foreach ($targets as $target) {
            $offerRowId = isset($target['id']) ? (int) $target['id'] : 0;
            $accountId = isset($target['account_id']) ? (int) $target['account_id'] : 0;
            $offerId = trim((string) ($target['offer_id'] ?? ''));
            if ($offerRowId <= 0 || $accountId <= 0 || $offerId === '') {
                continue;
            }

            if ($deduplicatePending) {
                $existing = $this->database->fetch(
                    'SELECT id FROM allegro_offer_change_queue'
                    . ' WHERE offer_row_id = :offer_row_id AND operation = :operation AND status IN ("pending", "retry", "processing")'
                    . ' ORDER BY id DESC LIMIT 1',
                    array(
                        'offer_row_id' => $offerRowId,
                        'operation' => $operation,
                    )
                );

                if ($existing && !empty($existing['id'])) {
                    $existingRow = $this->database->fetch(
                        'SELECT status FROM allegro_offer_change_queue WHERE id = :id LIMIT 1',
                        array('id' => (int) $existing['id'])
                    );
                    $existingStatus = strtolower(trim((string) ($existingRow['status'] ?? '')));

                    if ($existingStatus === 'pending' || $existingStatus === 'retry') {
                        $this->database->update(
                            'allegro_offer_change_queue',
                            array(
                                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                'status' => 'pending',
                                'attempts' => 0,
                                'error_message' => null,
                                'available_at' => $availableAt,
                                'processed_at' => null,
                            ),
                            'id = :id',
                            array('id' => (int) $existing['id'])
                        );
                    }
                    $queued++;
                    continue;
                }
            }

            $this->database->insert('allegro_offer_change_queue', array(
                'account_id' => $accountId,
                'offer_row_id' => $offerRowId,
                'offer_id' => $offerId,
                'operation' => $operation,
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status' => 'pending',
                'available_at' => $availableAt,
            ));
            $queued++;
        }

        return $queued;
    }

    public function existingQueuedOfferOperationMap(array $targets, string $operation, array $statuses = array('pending', 'retry', 'processing')): array
    {
        $offerRowIds = array_values(array_unique(array_filter(array_map(static function ($target): int {
            return isset($target['id']) ? (int) $target['id'] : 0;
        }, $targets), static function (int $id): bool {
            return $id > 0;
        })));

        if ($offerRowIds === array()) {
            return array();
        }

        $normalizedStatuses = array_values(array_unique(array_filter(array_map(static function ($status): string {
            return strtolower(trim((string) $status));
        }, $statuses), static function (string $status): bool {
            return in_array($status, array('pending', 'retry', 'processing', 'done', 'error'), true);
        })));

        if ($normalizedStatuses === array()) {
            return array();
        }

        $offerPlaceholders = array();
        $statusPlaceholders = array();
        $params = array(
            'operation' => $operation,
        );

        foreach ($offerRowIds as $index => $offerRowId) {
            $key = 'offer_row_id_' . $index;
            $offerPlaceholders[] = ':' . $key;
            $params[$key] = $offerRowId;
        }

        foreach ($normalizedStatuses as $index => $status) {
            $key = 'status_' . $index;
            $statusPlaceholders[] = ':' . $key;
            $params[$key] = $status;
        }

        $rows = $this->database->fetchAll(
            'SELECT offer_row_id, id, status, available_at, created_at'
            . ' FROM allegro_offer_change_queue'
            . ' WHERE operation = :operation'
            . '   AND offer_row_id IN (' . implode(', ', $offerPlaceholders) . ')'
            . '   AND status IN (' . implode(', ', $statusPlaceholders) . ')'
            . ' ORDER BY id DESC',
            $params
        );

        $map = array();
        foreach ($rows as $row) {
            $offerRowId = isset($row['offer_row_id']) ? (int) $row['offer_row_id'] : 0;
            if ($offerRowId <= 0 || isset($map[$offerRowId])) {
                continue;
            }

            $map[$offerRowId] = array(
                'queue_id' => isset($row['id']) ? (int) $row['id'] : 0,
                'status' => (string) ($row['status'] ?? ''),
                'available_at' => (string) ($row['available_at'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
            );
        }

        return $map;
    }

    public function clearQueueForOffers(array $targets): int
    {
        $offerRowIds = array_values(array_unique(array_filter(array_map(static function ($target): int {
            return isset($target['id']) ? (int) $target['id'] : 0;
        }, $targets), static function (int $id): bool {
            return $id > 0;
        })));

        if ($offerRowIds === array()) {
            return 0;
        }

        $placeholders = array();
        $params = array();
        foreach ($offerRowIds as $index => $offerRowId) {
            $key = 'offer_row_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $offerRowId;
        }

        return (int) $this->database->execute(
            'DELETE FROM allegro_offer_change_queue'
            . ' WHERE offer_row_id IN (' . implode(', ', $placeholders) . ')'
            . ' AND status IN ("pending", "retry", "processing", "error")',
            $params
        );
    }

    public function clearQueueByStatuses(array $statuses): int
    {
        $statuses = array_values(array_unique(array_filter(array_map(static function ($status): string {
            return strtolower(trim((string) $status));
        }, $statuses), static function (string $status): bool {
            return in_array($status, array('pending', 'retry', 'processing', 'done', 'error'), true);
        })));

        if ($statuses === array()) {
            return 0;
        }

        $placeholders = array();
        $params = array();
        foreach ($statuses as $index => $status) {
            $key = 'status_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $status;
        }

        return $this->database->query(
            'DELETE FROM allegro_offer_change_queue WHERE status IN (' . implode(', ', $placeholders) . ')',
            $params
        )->rowCount();
    }

    public function clearWholeQueue(): int
    {
        return $this->database->query('DELETE FROM allegro_offer_change_queue')->rowCount();
    }

    public function deleteOffersByTargets(array $targets): int
    {
        $offerRowIds = array_values(array_unique(array_filter(array_map(static function ($target): int {
            return isset($target['id']) ? (int) $target['id'] : 0;
        }, $targets), static function (int $id): bool {
            return $id > 0;
        })));

        if ($offerRowIds === array()) {
            return 0;
        }

        $placeholders = array();
        $params = array();
        foreach ($offerRowIds as $index => $offerRowId) {
            $key = 'offer_row_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $offerRowId;
        }

        return $this->database->query(
            'DELETE FROM allegro_offers WHERE id IN (' . implode(', ', $placeholders) . ')',
            $params
        )->rowCount();
    }

    public function addOfferExclusion(int $accountId, string $offerId, string $mode = 'permanent', ?string $note = null): void
    {
        $offerId = trim($offerId);
        if ($accountId <= 0 || $offerId === '') {
            return;
        }

        $this->database->query(
            'INSERT INTO allegro_offer_exclusions (account_id, offer_id, mode, note)'
            . ' VALUES (:account_id, :offer_id, :mode, :note)'
            . ' ON DUPLICATE KEY UPDATE mode = VALUES(mode), note = VALUES(note)',
            array(
                'account_id' => $accountId,
                'offer_id' => $offerId,
                'mode' => $mode,
                'note' => $note,
            )
        );
    }

    public function excludedOfferIds(int $accountId, array $offerIds): array
    {
        $offerIds = array_values(array_unique(array_filter(array_map(static function ($value): string {
            return trim((string) $value);
        }, $offerIds), static function (string $value): bool {
            return $value !== '';
        })));

        if ($accountId <= 0 || $offerIds === array()) {
            return array();
        }

        $placeholders = array();
        $params = array('account_id' => $accountId);
        foreach ($offerIds as $index => $offerId) {
            $key = 'offer_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $offerId;
        }

        $rows = $this->database->fetchAll(
            'SELECT offer_id FROM allegro_offer_exclusions'
            . ' WHERE account_id = :account_id AND offer_id IN (' . implode(', ', $placeholders) . ')',
            $params
        );

        return array_values(array_map(static function (array $row): string {
            return (string) ($row['offer_id'] ?? '');
        }, $rows));
    }

    public function isOfferExcluded(int $accountId, string $offerId): bool
    {
        $offerId = trim($offerId);
        if ($accountId <= 0 || $offerId === '') {
            return false;
        }

        return (int) $this->database->fetchColumn(
            'SELECT COUNT(*) FROM allegro_offer_exclusions WHERE account_id = :account_id AND offer_id = :offer_id',
            array(
                'account_id' => $accountId,
                'offer_id' => $offerId,
            )
        ) > 0;
    }

    public function offerTargetsForWarehouseProductIds(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds), static function (int $id): bool {
            return $id > 0;
        })));

        if ($productIds === array()) {
            return array();
        }

        $skuPlaceholders = array();
        $oldSkuPlaceholders = array();
        $params = array();
        foreach ($productIds as $index => $productId) {
            $skuKey = 'product_id_sku_' . $index;
            $oldSkuKey = 'product_id_old_sku_' . $index;
            $skuPlaceholders[] = ':' . $skuKey;
            $oldSkuPlaceholders[] = ':' . $oldSkuKey;
            $params[$skuKey] = $productId;
            $params[$oldSkuKey] = $productId;
        }

        return $this->database->fetchAll(
            'SELECT DISTINCT offers.id, offers.account_id, offers.offer_id'
            . ' FROM allegro_offers offers'
            . ' WHERE ('
            . '   EXISTS ('
            . '     SELECT 1'
            . '     FROM products warehouse_sku'
            . '     WHERE warehouse_sku.deleted_at IS NULL'
            . '       AND warehouse_sku.id IN (' . implode(', ', $skuPlaceholders) . ')'
            . '       AND warehouse_sku.sku = offers.sku'
            . '   )'
            . '   OR EXISTS ('
            . '     SELECT 1'
            . '     FROM products warehouse_old'
            . '     INNER JOIN product_custom_field_values warehouse_old_values ON warehouse_old_values.product_id = warehouse_old.id'
            . '     INNER JOIN product_custom_field_definitions warehouse_old_definition ON warehouse_old_definition.id = warehouse_old_values.definition_id'
            . '     WHERE warehouse_old.deleted_at IS NULL'
            . '       AND warehouse_old.id IN (' . implode(', ', $oldSkuPlaceholders) . ')'
            . '       AND warehouse_old_definition.slug = "old_sku"'
            . '       AND warehouse_old_values.value = offers.sku'
            . '   )'
            . ' )'
            . ' ORDER BY offers.id ASC',
            $params
        );
    }

    public function offersForWarehouseSyncCycle(int $accountId, string $cycle): array
    {
        if ($accountId <= 0 || trim($cycle) === '') {
            return array();
        }

        $rows = $this->database->fetchAll(
            'SELECT offers.*, warehouse.id AS warehouse_product_live_id,'
            . ' warehouse.sku AS warehouse_sku, warehouse.product_name AS warehouse_product_name, warehouse.price_gross AS warehouse_price_gross,'
            . ' warehouse.category_id AS warehouse_category_id, warehouse_categories.name AS warehouse_category_name, warehouse_categories.allegro_category_id AS warehouse_category_allegro_id,'
            . ' COALESCE(shared_stock_groups.quantity, warehouse.quantity) AS warehouse_quantity,'
            . ' COALESCE(shared_stock_groups.localization, warehouse.localization) AS warehouse_localization'
            . ' FROM allegro_offers offers'
            . $this->liveWarehouseJoinSql()
            . ' LEFT JOIN categories warehouse_categories ON warehouse_categories.id = warehouse.category_id'
            . ' LEFT JOIN shared_stock_groups ON shared_stock_groups.id = warehouse.shared_stock_group_id'
            . ' WHERE offers.account_id = :account_id AND offers.last_seen_cycle = :cycle AND warehouse.id IS NOT NULL'
            . ' ORDER BY offers.id ASC',
            array(
                'account_id' => $accountId,
                'cycle' => trim($cycle),
            )
        );

        foreach ($rows as $index => $row) {
            $rows[$index]['images'] = $this->decodeJsonList($row['images_json'] ?? null);
            $rows[$index]['parameters'] = $this->decodeJsonList($row['parameters_json'] ?? null);
            $rows[$index]['marketplaces'] = $this->decodeJsonList($row['marketplaces_json'] ?? null);
            $rows[$index]['product_set'] = $this->decodeJsonList($row['product_set_json'] ?? null);
            $rows[$index]['offer_payload'] = $this->decodeJsonAny($row['offer_json'] ?? null);
            $rows[$index] = $this->normalizeOfferViewData($rows[$index]);
        }

        return $this->applyEffectiveWarehouseStockToRows($rows);
    }

    public function queueCounts(): array
    {
        $cacheKey = 'allegro:queue-counts:v1';
        $cached = $this->getCache($cacheKey);
        if (is_array($cached) && isset($cached['pending'])) {
            return $cached;
        }

        $rows = $this->database->fetchAll(
            'SELECT status, COUNT(*) AS total FROM allegro_offer_change_queue GROUP BY status'
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

        $this->putCache($cacheKey, $result, self::QUEUE_COUNT_CACHE_TTL);
        return $result;
    }

    public function fetchQueueBatch(int $limit = 100, ?int $accountId = null): array
    {
        $params = array(
            'now' => date('Y-m-d H:i:s'),
            'remove_forever_retry_error' => '%Oferta nie jest jeszcze%',
        );
        $sql = 'SELECT queue.*, offers.warehouse_product_id, offers.sku, offers.name'
            . ' FROM allegro_offer_change_queue queue'
            . ' INNER JOIN allegro_offers offers ON offers.id = queue.offer_row_id'
            . ' WHERE ('
            . '   queue.status IN ("pending", "retry")'
            . '   OR ('
            . '     queue.status = "error"'
            . '     AND queue.operation = "remove_from_system_forever"'
            . '     AND queue.error_message LIKE :remove_forever_retry_error'
            . '   )'
            . ' )'
            . ' AND queue.available_at <= :now';

        if ($accountId !== null) {
            $sql .= ' AND queue.account_id = :account_id';
            $params['account_id'] = $accountId;
        }

        $sql .= ' ORDER BY queue.id ASC LIMIT ' . max(1, min(1000, $limit));
        $rows = $this->database->fetchAll($sql, $params);

        foreach ($rows as $index => $row) {
            $rows[$index]['payload'] = $this->decodeJsonAny($row['payload_json'] ?? null);
        }

        return $rows;
    }

    public function markQueueProcessing(int $queueId): void
    {
        $this->database->update(
            'allegro_offer_change_queue',
            array(
                'status' => 'processing',
                'error_message' => null,
            ),
            'id = :id',
            array('id' => $queueId)
        );
    }

    public function markQueueDone(int $queueId): void
    {
        $this->database->update(
            'allegro_offer_change_queue',
            array(
                'status' => 'done',
                'processed_at' => date('Y-m-d H:i:s'),
                'error_message' => null,
            ),
            'id = :id',
            array('id' => $queueId)
        );
    }

    public function markQueueRetry(int $queueId, string $errorMessage, int $attempts, int $delaySeconds, ?string $statusOverride = null): void
    {
        $status = $statusOverride !== null && in_array($statusOverride, array('retry', 'error', 'pending', 'processing', 'done'), true)
            ? $statusOverride
            : ($attempts >= 5 ? 'error' : 'retry');

        $this->database->update(
            'allegro_offer_change_queue',
            array(
                'status' => $status,
                'attempts' => $attempts,
                'error_message' => $errorMessage,
                'available_at' => date('Y-m-d H:i:s', time() + max(30, $delaySeconds)),
            ),
            'id = :id',
            array('id' => $queueId)
        );
    }

    public function updateOfferSnapshot(int $offerRowId, array $changes): void
    {
        if ($changes === array()) {
            return;
        }

        $this->database->update(
            'allegro_offers',
            $changes,
            'id = :id',
            array('id' => $offerRowId)
        );
    }

    public function markMissingOffersAsEnded(int $accountId, string $cycle): int
    {
        return $this->database->query(
            'UPDATE allegro_offers'
            . ' SET publication_status = CASE WHEN publication_status = "ACTIVE" THEN "ENDED" ELSE publication_status END,'
            . ' last_synced_at = :now'
            . ' WHERE account_id = :account_id AND (last_seen_cycle IS NULL OR last_seen_cycle <> :cycle)',
            array(
                'now' => date('Y-m-d H:i:s'),
                'account_id' => $accountId,
                'cycle' => $cycle,
            )
        )->rowCount();
    }

    public function syncState(int $accountId): array
    {
        $row = $this->database->fetch(
            'SELECT * FROM allegro_sync_states WHERE account_id = :account_id LIMIT 1',
            array('account_id' => $accountId)
        );

        if ($row) {
            return $row;
        }

        $this->database->insert('allegro_sync_states', array('account_id' => $accountId));

        return (array) $this->database->fetch(
            'SELECT * FROM allegro_sync_states WHERE account_id = :account_id LIMIT 1',
            array('account_id' => $accountId)
        );
    }

    public function acquireSyncLock(int $accountId, int $ttlSeconds = 900, int $staleHeartbeatSeconds = 300, bool $force = false): bool
    {
        $state = $this->syncState($accountId);
        $now = time();
        $lockedUntil = !empty($state['locked_until']) ? strtotime((string) $state['locked_until']) : false;
        $heartbeatAt = !empty($state['heartbeat_at']) ? strtotime((string) $state['heartbeat_at']) : false;
        $isStale = $heartbeatAt === false || ($heartbeatAt + max(60, $staleHeartbeatSeconds)) <= $now;

        if (!$force && !empty($state['is_running']) && $lockedUntil !== false && $lockedUntil > $now && !$isStale) {
            return false;
        }

        $this->database->update(
            'allegro_sync_states',
            array(
                'is_running' => 1,
                'locked_until' => date('Y-m-d H:i:s', $now + max(60, $ttlSeconds)),
                'heartbeat_at' => date('Y-m-d H:i:s', $now),
            ),
            'account_id = :account_id',
            array('account_id' => $accountId)
        );

        return true;
    }

    public function updateSyncState(int $accountId, array $payload): void
    {
        $this->syncState($accountId);
        $this->database->update(
            'allegro_sync_states',
            $payload,
            'account_id = :account_id',
            array('account_id' => $accountId)
        );
    }

    public function releaseSyncLock(int $accountId, ?string $errorMessage = null): void
    {
        $payload = array(
            'is_running' => 0,
            'locked_until' => null,
            'heartbeat_at' => date('Y-m-d H:i:s'),
        );

        if ($errorMessage !== null && $errorMessage !== '') {
            $payload['last_error_at'] = date('Y-m-d H:i:s');
            $payload['last_error_message'] = $errorMessage;
        }

        $this->database->update(
            'allegro_sync_states',
            $payload,
            'account_id = :account_id',
            array('account_id' => $accountId)
        );
    }

    public function markSyncSuccess(int $accountId): void
    {
        $now = date('Y-m-d H:i:s');

        $this->database->update(
            'allegro_sync_states',
            array(
                'last_success_at' => $now,
                'last_error_at' => null,
                'last_error_message' => null,
            ),
            'account_id = :account_id',
            array('account_id' => $accountId)
        );

        $this->database->update(
            'allegro_accounts',
            array(
                'last_sync_at' => $now,
                'last_error_at' => null,
                'last_error_message' => null,
            ),
            'id = :id',
            array('id' => $accountId)
        );
    }

    public function markAccountError(int $accountId, string $message): void
    {
        $now = date('Y-m-d H:i:s');

        $this->database->update(
            'allegro_accounts',
            array(
                'last_error_at' => $now,
                'last_error_message' => $message,
            ),
            'id = :id',
            array('id' => $accountId)
        );
    }

    public function purgeQueueHistory(int $doneOlderThanDays = 14, int $errorOlderThanDays = 30): array
    {
        $doneOlderThanDays = max(1, $doneOlderThanDays);
        $errorOlderThanDays = max(1, $errorOlderThanDays);

        $done = $this->database->delete(
            'allegro_offer_change_queue',
            'status = :status AND processed_at IS NOT NULL AND processed_at < :cutoff',
            array(
                'status' => 'done',
                'cutoff' => date('Y-m-d H:i:s', time() - ($doneOlderThanDays * 86400)),
            )
        );

        $error = $this->database->query(
            'DELETE FROM allegro_offer_change_queue'
            . ' WHERE status IN ("error", "retry")'
            . ' AND updated_at < :cutoff',
            array(
                'cutoff' => date('Y-m-d H:i:s', time() - ($errorOlderThanDays * 86400)),
            )
        )->rowCount();

        return array(
            'queue_done_deleted' => $done,
            'queue_error_deleted' => $error,
        );
    }

    public function detachOffersFromDeletedProducts(): int
    {
        return $this->database->query(
            'UPDATE allegro_offers offers'
            . ' INNER JOIN products warehouse ON warehouse.id = offers.warehouse_product_id'
            . ' SET offers.warehouse_product_id = NULL, offers.linked_by = NULL'
            . ' WHERE warehouse.deleted_at IS NOT NULL'
        )->rowCount();
    }

    private function buildOfferWhere(array $filters, array &$params): string
    {
        $whereParts = array('accounts.is_active = 1');

        $accountId = isset($filters['account_id']) ? trim((string) $filters['account_id']) : '';
        if ($accountId !== '' && ctype_digit($accountId)) {
            $whereParts[] = 'offers.account_id = :account_id';
            $params['account_id'] = (int) $accountId;
        }

        $query = isset($filters['q']) ? trim((string) $filters['q']) : '';
        if ($query !== '') {
            $searchTokens = $this->splitOfferSearchTokens($query);
            $includeClauses = array();
            foreach ($searchTokens['include'] as $index => $token) {
                $tokenKey = 'q_in_' . $index;
                if (!empty($searchTokens['list_mode']) && ctype_digit($token)) {
                    $includeClauses[] = 'offers.offer_id = :' . $tokenKey . '_offer_id_exact';
                    $params[$tokenKey . '_offer_id_exact'] = $token;
                    continue;
                }

                $includeClauses[] = $this->buildOfferSearchIncludeClause($tokenKey);
                $tokenLike = '%' . $token . '%';
                $params[$tokenKey . '_offer_id'] = $tokenLike;
                $params[$tokenKey . '_offer_sku'] = $tokenLike;
                $params[$tokenKey . '_offer_name'] = $tokenLike;
                $params[$tokenKey . '_warehouse_sku'] = $tokenLike;
                $params[$tokenKey . '_warehouse_name'] = $tokenLike;
                $params[$tokenKey . '_old_sku_definition'] = 'old_sku';
            }

            if ($includeClauses !== array()) {
                $glue = !empty($searchTokens['list_mode']) ? ' OR ' : ' AND ';
                $whereParts[] = '(' . implode($glue, $includeClauses) . ')';
            }

            foreach ($searchTokens['exclude'] as $index => $token) {
                $tokenKey = 'q_ex_' . $index;
                $whereParts[] = $this->buildOfferSearchExcludeClause($tokenKey);
                $tokenLike = '%' . $token . '%';
                $params[$tokenKey . '_offer_id'] = $tokenLike;
                $params[$tokenKey . '_offer_sku'] = $tokenLike;
                $params[$tokenKey . '_offer_name'] = $tokenLike;
                $params[$tokenKey . '_warehouse_sku'] = $tokenLike;
                $params[$tokenKey . '_warehouse_name'] = $tokenLike;
                $params[$tokenKey . '_old_sku_definition'] = 'old_sku';
            }
        }

        $status = isset($filters['status']) ? trim((string) $filters['status']) : '';
        if ($status !== '') {
            $whereParts[] = 'offers.publication_status = :status';
            $params['status'] = $status;
        }

        $queueStatus = strtolower(trim((string) ($filters['queue_status'] ?? '')));
        if (in_array($queueStatus, array('pending', 'retry', 'error', 'done', 'processing'), true)) {
            $whereParts[] = 'EXISTS ('
                . ' SELECT 1'
                . ' FROM allegro_offer_change_queue queue_latest'
                . ' WHERE queue_latest.offer_row_id = offers.id'
                . '   AND queue_latest.id = ('
                . '     SELECT MAX(queue_max.id)'
                . '     FROM allegro_offer_change_queue queue_max'
                . '     WHERE queue_max.offer_row_id = offers.id'
                . '   )'
                . '   AND queue_latest.status = :queue_status'
                . ' )';
            $params['queue_status'] = $queueStatus;
        }

        $duplicates = trim((string) ($filters['duplicates'] ?? ''));
        if ($duplicates === '1') {
            $this->appendDuplicateWhereClause($whereParts, $params, 'offers', $filters);
        }

        $sku = isset($filters['sku']) ? trim((string) $filters['sku']) : '';
        if ($sku !== '') {
            $this->appendLikeFilter($whereParts, $params, $sku, 'offers.sku', 'sku');
        }

        $linked = isset($filters['linked']) ? trim((string) $filters['linked']) : '';
        if ($linked === '1') {
            $whereParts[] = 'warehouse.id IS NOT NULL';
        } elseif ($linked === '0') {
            $whereParts[] = 'warehouse.id IS NULL';
        }

        $market = isset($filters['market']) ? trim((string) $filters['market']) : '';
        if ($market !== '') {
            $this->appendLikeFilter($whereParts, $params, $market, 'offers.marketplaces_json', 'market', '"');
        }

        $invoice = isset($filters['invoice']) ? trim((string) $filters['invoice']) : '';
        if ($invoice !== '') {
            $whereParts[] = 'offers.invoice_type = :invoice';
            $params['invoice'] = $invoice;
        }

        $warehouseQuantityFrom = isset($filters['warehouse_quantity_from']) ? trim((string) $filters['warehouse_quantity_from']) : '';
        $warehouseQuantityTo = isset($filters['warehouse_quantity_to']) ? trim((string) $filters['warehouse_quantity_to']) : '';
        if ($warehouseQuantityFrom !== '' || $warehouseQuantityTo !== '') {
            $this->appendQuantityRangeFilter(
                $whereParts,
                $params,
                $warehouseQuantityFrom,
                $warehouseQuantityTo,
                'COALESCE(shared_stock_groups.quantity, warehouse.quantity)',
                'warehouse_quantity'
            );
        } else {
            $warehouseQuantity = isset($filters['warehouse_quantity']) ? trim((string) $filters['warehouse_quantity']) : '';
            if ($warehouseQuantity !== '') {
                $this->appendQuantityFilter($whereParts, $params, $warehouseQuantity, 'COALESCE(shared_stock_groups.quantity, warehouse.quantity)', 'warehouse_quantity');
            }
        }

        return ' WHERE ' . implode(' AND ', $whereParts);
    }

    private function splitOfferSearchTokens(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return array(
                'include' => array(),
                'exclude' => array(),
                'list_mode' => false,
            );
        }

        $listMode = preg_match('/[;,]/u', $query) === 1;
        $query = preg_replace('/[;,]+/u', ' ', $query);
        preg_match_all('/([!-]?"[^"]+"|[!-]?\'[^\']+\'|[!-]?\S+)/u', $query, $matches);
        $rawTokens = isset($matches[0]) && is_array($matches[0]) ? $matches[0] : array();

        $result = array(
            'include' => array(),
            'exclude' => array(),
            'list_mode' => $listMode,
        );

        foreach ($rawTokens as $rawToken) {
            $rawToken = trim((string) $rawToken);
            if ($rawToken === '') {
                continue;
            }

            $isExclude = false;
            if ($rawToken[0] === '-' || $rawToken[0] === '!') {
                $isExclude = true;
                $rawToken = substr($rawToken, 1);
            }

            $token = trim($rawToken, " \t\n\r\0\x0B\"'");
            if ($token === '') {
                continue;
            }

            $bucket = $isExclude ? 'exclude' : 'include';
            if (!in_array($token, $result[$bucket], true)) {
                $result[$bucket][] = $token;
            }
        }

        return $result;
    }

    private function parseNegatedFilterValue(string $rawValue): array
    {
        $rawValue = trim($rawValue);
        if ($rawValue === '') {
            return array(
                'negated' => false,
                'value' => '',
            );
        }

        $negated = false;
        if ($rawValue[0] === '-' || $rawValue[0] === '!') {
            $negated = true;
            $rawValue = substr($rawValue, 1);
        }

        return array(
            'negated' => $negated,
            'value' => trim($rawValue, " \t\n\r\0\x0B\"'"),
        );
    }

    private function appendLikeFilter(array &$whereParts, array &$params, string $rawValue, string $columnSql, string $prefix, string $valueWrapper = ''): void
    {
        $parsed = $this->parseNegatedFilterValue($rawValue);
        $value = (string) ($parsed['value'] ?? '');
        if ($value === '') {
            return;
        }

        $operator = !empty($parsed['negated']) ? 'NOT LIKE' : 'LIKE';
        $wrappedValue = $valueWrapper !== '' ? $valueWrapper . $value . $valueWrapper : $value;
        $whereParts[] = $columnSql . ' ' . $operator . ' :' . $prefix;
        $params[$prefix] = '%' . $wrappedValue . '%';
    }

    private function analyzeOfferFilters(array $filters, string $sortBy = ''): array
    {
        $warehouseQuantity = isset($filters['warehouse_quantity']) ? trim((string) $filters['warehouse_quantity']) : '';
        $warehouseQuantityFrom = isset($filters['warehouse_quantity_from']) ? trim((string) $filters['warehouse_quantity_from']) : '';
        $warehouseQuantityTo = isset($filters['warehouse_quantity_to']) ? trim((string) $filters['warehouse_quantity_to']) : '';
        $linked = isset($filters['linked']) ? trim((string) $filters['linked']) : '';

        $needsWarehouse = false;
        $needsSharedStock = $warehouseQuantity !== '' || $warehouseQuantityFrom !== '' || $warehouseQuantityTo !== '';

        if (in_array($sortBy, array('warehouse_quantity', 'warehouse_sku', 'linked'), true)) {
            $needsWarehouse = true;
            if ($sortBy === 'warehouse_quantity') {
                $needsSharedStock = true;
            }
        }

        if ($needsSharedStock) {
            $needsWarehouse = true;
        }

        if ($linked !== '') {
            $needsWarehouse = true;
        }

        return array(
            'needs_warehouse' => $needsWarehouse,
            'needs_shared_stock' => $needsSharedStock,
        );
    }

    private function buildOfferSearchIncludeClause(string $tokenKey): string
    {
        return '(offers.offer_id LIKE :' . $tokenKey . '_offer_id'
            . ' OR offers.sku LIKE :' . $tokenKey . '_offer_sku'
            . ' OR offers.name LIKE :' . $tokenKey . '_offer_name'
            . ' OR EXISTS ('
            . '   SELECT 1'
            . '   FROM products warehouse_search'
            . '   LEFT JOIN product_custom_field_definitions warehouse_old_definition'
            . '     ON warehouse_old_definition.slug = :' . $tokenKey . '_old_sku_definition'
            . '   LEFT JOIN product_custom_field_values warehouse_old_values'
            . '     ON warehouse_old_values.product_id = warehouse_search.id'
            . '    AND warehouse_old_values.definition_id = warehouse_old_definition.id'
            . '   WHERE warehouse_search.deleted_at IS NULL'
            . '     AND (warehouse_search.sku = offers.sku OR warehouse_old_values.value = offers.sku)'
            . '     AND (warehouse_search.sku LIKE :' . $tokenKey . '_warehouse_sku'
            . '       OR warehouse_search.product_name LIKE :' . $tokenKey . '_warehouse_name)'
            . ' ))';
    }

    private function buildOfferSearchExcludeClause(string $tokenKey): string
    {
        return '(offers.offer_id NOT LIKE :' . $tokenKey . '_offer_id'
            . ' AND offers.sku NOT LIKE :' . $tokenKey . '_offer_sku'
            . ' AND offers.name NOT LIKE :' . $tokenKey . '_offer_name'
            . ' AND NOT EXISTS ('
            . '   SELECT 1'
            . '   FROM products warehouse_search'
            . '   LEFT JOIN product_custom_field_definitions warehouse_old_definition'
            . '     ON warehouse_old_definition.slug = :' . $tokenKey . '_old_sku_definition'
            . '   LEFT JOIN product_custom_field_values warehouse_old_values'
            . '     ON warehouse_old_values.product_id = warehouse_search.id'
            . '    AND warehouse_old_values.definition_id = warehouse_old_definition.id'
            . '   WHERE warehouse_search.deleted_at IS NULL'
            . '     AND (warehouse_search.sku = offers.sku OR warehouse_old_values.value = offers.sku)'
            . '     AND (warehouse_search.sku LIKE :' . $tokenKey . '_warehouse_sku'
            . '       OR warehouse_search.product_name LIKE :' . $tokenKey . '_warehouse_name)'
            . ' ))';
    }

    private function buildOfferSort(string $sortBy, string $sortDir): string
    {
        $direction = strtolower($sortDir) === 'asc' ? 'ASC' : 'DESC';
        $map = array(
            'id' => 'offers.id',
            'account' => 'accounts.name',
            'offer_id' => 'offers.offer_id',
            'sku' => 'offers.sku',
            'name' => 'offers.name',
            'warehouse_sku' => 'warehouse.sku',
            'linked' => 'CASE WHEN warehouse.id IS NULL THEN 0 ELSE 1 END',
            'price' => 'offers.price_amount',
            'invoice' => 'offers.invoice_type',
            'status' => 'offers.publication_status',
            'market' => 'offers.marketplaces_json',
            'allegro_quantity' => 'offers.stock_available',
            'sold' => 'offers.stock_sold',
            'images' => 'offers.image_count',
            'warehouse_quantity' => 'COALESCE(shared_stock_groups.quantity, warehouse.quantity)',
            'synced' => 'offers.last_synced_at',
            'updated' => 'offers.updated_at',
            'created' => 'offers.created_at',
        );

        if (!isset($map[$sortBy])) {
            return 'offers.id DESC';
        }

        return $map[$sortBy] . ' ' . $direction . ', offers.id DESC';
    }

    private function normalizeOfferViewData(array $row): array
    {
        $liveWarehouseProductId = isset($row['warehouse_product_live_id']) ? (int) $row['warehouse_product_live_id'] : 0;
        $row['stored_warehouse_product_id'] = isset($row['warehouse_product_id']) ? $row['warehouse_product_id'] : null;
        $row['warehouse_product_id'] = $liveWarehouseProductId > 0 ? $liveWarehouseProductId : null;
        if (empty($row['warehouse_product_id'])) {
            $row['linked_by'] = null;
        } elseif (trim((string) ($row['linked_by'] ?? '')) === '') {
            $row['linked_by'] = 'sku';
        }
        $marketplaceEntries = $this->extractMarketplaceEntriesForView($row);
        $row['marketplace_entries'] = $marketplaceEntries;
        $row['marketplace_labels'] = array_values(array_map(static function (array $entry): string {
            return (string) ($entry['label'] ?? '');
        }, $marketplaceEntries));
        $row['marketplace_price_entries'] = array_values(array_filter($marketplaceEntries, static function (array $entry): bool {
            return !empty($entry['price']);
        }));
        $row['status_label'] = $this->normalizeStatusLabel((string) ($row['publication_status'] ?? ''));
        $row['queue_meta'] = $this->normalizeQueueMetaForView(isset($row['queue_meta']) && is_array($row['queue_meta']) ? $row['queue_meta'] : array());

        return $row;
    }

    private function applyEffectiveWarehouseStockToRows(array $rows): array
    {
        $productIds = array_values(array_unique(array_filter(array_map(static function (array $row): int {
            return isset($row['warehouse_product_id']) ? (int) $row['warehouse_product_id'] : 0;
        }, $rows), static function (int $id): bool {
            return $id > 0;
        })));

        if ($productIds === array()) {
            return $rows;
        }

        $products = new ProductRepository($this->database);
        $products->ensureSchema();
        $effectiveStock = $products->effectiveStockByProductIds($productIds);

        foreach ($rows as $index => $row) {
            $productId = isset($row['warehouse_product_id']) ? (int) $row['warehouse_product_id'] : 0;
            if ($productId <= 0 || !isset($effectiveStock[$productId])) {
                continue;
            }

            $rows[$index]['warehouse_quantity'] = $effectiveStock[$productId]['quantity'];
            $rows[$index]['warehouse_localization'] = $effectiveStock[$productId]['localization'];
        }

        return $rows;
    }

    private function applyEffectiveWarehouseStockToRow(array $row): array
    {
        $rows = $this->applyEffectiveWarehouseStockToRows(array($row));
        return isset($rows[0]) ? $rows[0] : $row;
    }

    private function duplicateMetaForOffers(array $offerIds, array $filters = array()): array
    {
        $offerIds = array_values(array_unique(array_filter(array_map('intval', $offerIds), static function (int $id): bool {
            return $id > 0;
        })));

        if ($offerIds === array()) {
            return array();
        }

        $statusFilter = trim((string) ($filters['status'] ?? ''));
        $groupRows = $this->fetchDuplicateGroupRowsForOffers($offerIds, $statusFilter !== '' ? $statusFilter : null, false);

        return $this->buildDuplicateMetaIndex($groupRows);
    }

    public function filterTerminableEndOfferTargets(array $targets): array
    {
        $offerIds = array_values(array_unique(array_filter(array_map(static function (array $target): int {
            return isset($target['id']) ? (int) $target['id'] : 0;
        }, $targets), static function (int $id): bool {
            return $id > 0;
        })));

        if ($offerIds === array()) {
            return array(
                'allowed' => array(),
                'blocked' => array(),
            );
        }

        $groupRows = $this->fetchDuplicateGroupRowsForOffers($offerIds, null, true);
        $metaByOfferId = $this->buildDuplicateMetaIndex($groupRows);
        $allowed = array();
        $blocked = array();

        foreach ($targets as $target) {
            $offerRowId = isset($target['id']) ? (int) $target['id'] : 0;
            if ($offerRowId <= 0) {
                continue;
            }

            $meta = $metaByOfferId[$offerRowId] ?? array();
            $canEndOffer = !isset($meta['can_end_offer']) || (bool) $meta['can_end_offer'];
            if ($canEndOffer) {
                $allowed[] = $target;
                continue;
            }

            $blocked[] = array(
                'id' => $offerRowId,
                'offer_id' => (string) ($target['offer_id'] ?? ''),
                'oldest_offer_id' => (string) ($meta['oldest_offer_id'] ?? ''),
                'duplicate_count' => (int) ($meta['duplicate_count'] ?? 0),
            );
        }

        return array(
            'allowed' => $allowed,
            'blocked' => $blocked,
        );
    }

    private function appendDuplicateWhereClause(array &$whereParts, array &$params, string $offerAlias, array $filters): void
    {
        $status = trim((string) ($filters['status'] ?? ''));

        $whereParts[] = $offerAlias . '.primary_image_url IS NOT NULL AND ' . $offerAlias . '.primary_image_url <> ""';
        $sql = 'EXISTS ('
            . ' SELECT 1'
            . ' FROM allegro_offers dup'
            . ' WHERE dup.id <> ' . $offerAlias . '.id'
            . '   AND dup.account_id = ' . $offerAlias . '.account_id'
            . '   AND dup.name = ' . $offerAlias . '.name'
            . '   AND ('
            . '     ('
            . '       ' . $offerAlias . '.primary_image_hash IS NOT NULL'
            . '       AND ' . $offerAlias . '.primary_image_hash <> ""'
            . '       AND dup.primary_image_hash = ' . $offerAlias . '.primary_image_hash'
            . '     )'
            . '     OR ('
            . '       (' . $offerAlias . '.primary_image_hash IS NULL OR ' . $offerAlias . '.primary_image_hash = "")'
            . '       AND (dup.primary_image_hash IS NULL OR dup.primary_image_hash = "")'
            . '       AND dup.primary_image_url = ' . $offerAlias . '.primary_image_url'
            . '     )'
            . '   )';

        if ($status !== '') {
            $sql .= '   AND dup.publication_status = :duplicate_status';
            $params['duplicate_status'] = $status;
        }

        $sql .= ' )';
        $whereParts[] = $sql;
    }

    private function fetchDuplicateGroupRowsForOffers(array $offerIds, ?string $statusFilter = null, bool $matchBaseStatus = false): array
    {
        $params = array();
        $placeholders = $this->buildIntegerPlaceholders('duplicate_offer_row_id', $offerIds, $params);
        $sql = 'SELECT base.id AS base_offer_row_id,'
            . ' grouped.id AS group_offer_row_id,'
            . ' grouped.offer_id AS group_offer_id,'
            . ' grouped.publication_status AS group_status'
            . ' FROM allegro_offers base'
            . ' INNER JOIN allegro_offers grouped'
            . '   ON grouped.account_id = base.account_id'
            . '  AND grouped.name = base.name'
            . '  AND ('
            . '    ('
            . '      base.primary_image_hash IS NOT NULL'
            . '      AND base.primary_image_hash <> ""'
            . '      AND grouped.primary_image_hash = base.primary_image_hash'
            . '    )'
            . '    OR ('
            . '      (base.primary_image_hash IS NULL OR base.primary_image_hash = "")'
            . '      AND (grouped.primary_image_hash IS NULL OR grouped.primary_image_hash = "")'
            . '      AND grouped.primary_image_url = base.primary_image_url'
            . '    )'
            . '  )'
            . ' WHERE base.id IN (' . implode(', ', $placeholders) . ')'
            . '   AND base.primary_image_url IS NOT NULL AND base.primary_image_url <> ""';

        if ($statusFilter !== null && $statusFilter !== '') {
            $sql .= ' AND grouped.publication_status = :duplicate_group_status';
            $params['duplicate_group_status'] = $statusFilter;
        } elseif ($matchBaseStatus) {
            $sql .= ' AND grouped.publication_status = base.publication_status';
        }

        $sql .= ' ORDER BY base.id ASC, grouped.id ASC';

        return $this->database->fetchAll($sql, $params);
    }

    private function buildDuplicateMetaIndex(array $groupRows): array
    {
        $groupedMembers = array();

        foreach ($groupRows as $row) {
            $baseOfferRowId = (int) ($row['base_offer_row_id'] ?? 0);
            $groupOfferRowId = (int) ($row['group_offer_row_id'] ?? 0);
            if ($baseOfferRowId <= 0 || $groupOfferRowId <= 0) {
                continue;
            }

            if (!isset($groupedMembers[$baseOfferRowId])) {
                $groupedMembers[$baseOfferRowId] = array();
            }

            $groupedMembers[$baseOfferRowId][$groupOfferRowId] = array(
                'row_id' => $groupOfferRowId,
                'offer_id' => (string) ($row['group_offer_id'] ?? ''),
                'status' => (string) ($row['group_status'] ?? ''),
                'status_label' => $this->normalizeStatusLabel((string) ($row['group_status'] ?? '')),
            );
        }

        $result = array();

        foreach ($groupedMembers as $baseOfferRowId => $membersByRowId) {
            $members = array_values($membersByRowId);
            if (count($members) <= 1) {
                continue;
            }

            usort($members, function (array $left, array $right): int {
                return $this->compareDuplicateMembers($left, $right);
            });

            $oldestMember = $members[0];
            $peers = array_values(array_filter($members, static function (array $member) use ($baseOfferRowId): bool {
                return (int) ($member['row_id'] ?? 0) !== $baseOfferRowId;
            }));

            $result[$baseOfferRowId] = array(
                'is_duplicate' => true,
                'duplicate_count' => count($members) - 1,
                'peer_offer_ids' => array_values(array_map(static function (array $peer): string {
                    return (string) ($peer['offer_id'] ?? '');
                }, $peers)),
                'peer_details' => $peers,
                'oldest_offer_id' => (string) ($oldestMember['offer_id'] ?? ''),
                'is_oldest' => (int) ($oldestMember['row_id'] ?? 0) === $baseOfferRowId,
                'can_end_offer' => (int) ($oldestMember['row_id'] ?? 0) !== $baseOfferRowId,
            );
        }

        return $result;
    }

    private function compareDuplicateMembers(array $left, array $right): int
    {
        $offerComparison = $this->compareOfferIdentifiers(
            (string) ($left['offer_id'] ?? ''),
            (string) ($right['offer_id'] ?? '')
        );

        if ($offerComparison !== 0) {
            return $offerComparison;
        }

        return ((int) ($left['row_id'] ?? 0)) <=> ((int) ($right['row_id'] ?? 0));
    }

    private function compareOfferIdentifiers(string $left, string $right): int
    {
        $left = trim($left);
        $right = trim($right);
        $leftIsNumeric = $left !== '' && ctype_digit($left);
        $rightIsNumeric = $right !== '' && ctype_digit($right);

        if ($leftIsNumeric && $rightIsNumeric) {
            $leftNormalized = ltrim($left, '0');
            $rightNormalized = ltrim($right, '0');
            $leftNormalized = $leftNormalized === '' ? '0' : $leftNormalized;
            $rightNormalized = $rightNormalized === '' ? '0' : $rightNormalized;

            $lengthComparison = strlen($leftNormalized) <=> strlen($rightNormalized);
            if ($lengthComparison !== 0) {
                return $lengthComparison;
            }

            return strcmp($leftNormalized, $rightNormalized);
        }

        return strcmp($left, $right);
    }

    private function latestQueueMetaForOffers(array $offerIds): array
    {
        $offerIds = array_values(array_unique(array_filter(array_map('intval', $offerIds), static function (int $id): bool {
            return $id > 0;
        })));

        if ($offerIds === array()) {
            return array();
        }

        $placeholders = array();
        $params = array();
        foreach ($offerIds as $index => $offerId) {
            $key = 'offer_row_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $offerId;
        }

        $rows = $this->database->fetchAll(
            'SELECT queue.offer_row_id, queue.operation, queue.status, queue.attempts, queue.error_message, queue.available_at, queue.processed_at, queue.updated_at'
            . ' FROM allegro_offer_change_queue queue'
            . ' INNER JOIN ('
            . '   SELECT offer_row_id, MAX(id) AS max_id'
            . '   FROM allegro_offer_change_queue'
            . '   WHERE offer_row_id IN (' . implode(', ', $placeholders) . ')'
            . '   GROUP BY offer_row_id'
            . ' ) latest ON latest.max_id = queue.id',
            $params
        );

        $result = array();
        foreach ($rows as $row) {
            $offerRowId = isset($row['offer_row_id']) ? (int) $row['offer_row_id'] : 0;
            if ($offerRowId <= 0) {
                continue;
            }
            $result[$offerRowId] = $row;
        }

        return $result;
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
            'pending' => array(
                'label' => 'W kolejce',
                'badge_class' => 'text-bg-warning',
                'row_class' => 'table-warning',
            ),
            'processing' => array(
                'label' => 'Przetwarzanie',
                'badge_class' => 'text-bg-info',
                'row_class' => 'table-info',
            ),
            'done' => array(
                'label' => 'Zrobiono',
                'badge_class' => 'text-bg-success',
                'row_class' => 'table-success',
            ),
            'retry' => array(
                'label' => 'Retry',
                'badge_class' => 'text-bg-warning',
                'row_class' => 'table-warning',
            ),
            'error' => array(
                'label' => 'Blad',
                'badge_class' => 'text-bg-danger',
                'row_class' => 'table-danger',
            ),
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
            'updated_at' => trim((string) ($queueMeta['updated_at'] ?? $queueMeta['processed_at'] ?? $queueMeta['available_at'] ?? '')),
        );
    }

    private function extractMarketplaceEntriesForView(array $row): array
    {
        $entries = array();
        $seen = array();
        $payload = isset($row['offer_payload']) && is_array($row['offer_payload']) ? $row['offer_payload'] : array();
        $marketplaces = isset($row['marketplaces']) && is_array($row['marketplaces']) ? $row['marketplaces'] : array();

        foreach ($marketplaces as $market) {
            $label = $this->marketplaceLabelFromValue($market);
            if ($label === '') {
                continue;
            }

            $entryKey = strtolower($label);
            if (!isset($seen[$entryKey])) {
                $entries[] = array(
                    'label' => $label,
                    'price' => null,
                    'currency' => '',
                );
                $seen[$entryKey] = true;
            }
        }

        $marketplaceCollections = array(
            $payload['additionalMarketplaces'] ?? null,
            $payload['additionalMarketplacesConfiguration'] ?? null,
            $payload['publication']['additionalMarketplaces'] ?? null,
            $payload['publication']['marketplacesAdditional'] ?? null,
        );

        foreach ($marketplaceCollections as $collection) {
            if (!is_array($collection)) {
                continue;
            }

            foreach ($collection as $item) {
                $this->appendMarketplaceEntry($item, $entries, $seen);
            }
        }

        return $entries;
    }

    private function appendMarketplaceEntry($node, array &$entries, array &$seen): void
    {
        if (!is_array($node)) {
            return;
        }

        $label = $this->marketplaceLabelFromValue($node);
        if ($label === '') {
            return;
        }

        $priceData = $this->extractMarketplacePrice($node);
        $entryKey = strtolower($label) . '|' . ($priceData['price'] ?? '');
        if (!isset($seen[$entryKey])) {
            $entries[] = array(
                'label' => $label,
                'price' => $priceData['price'],
                'currency' => $priceData['currency'],
            );
            $seen[$entryKey] = true;
        }
    }

    private function marketplaceLabelFromValue($value): string
    {
        if (is_string($value)) {
            $value = trim($value);
            if ($value !== '' && strcasecmp($value, 'Array') !== 0 && preg_match('/^allegro[\-_]/i', $value) === 1) {
                return $value;
            }
            return '';
        }

        if (!is_array($value)) {
            return '';
        }

        $candidates = array(
            $value['marketplace']['id'] ?? null,
            $value['marketplace']['name'] ?? null,
            $value['baseMarketplace']['id'] ?? null,
            $value['baseMarketplace']['name'] ?? null,
            $value['market']['id'] ?? null,
            $value['market']['name'] ?? null,
        );

        foreach ($candidates as $candidate) {
            if (!is_scalar($candidate)) {
                continue;
            }

            $label = trim((string) $candidate);
            if ($label !== '' && preg_match('/^allegro[\-_]/i', $label) === 1) {
                return $label;
            }
        }

        return '';
    }

    private function extractMarketplacePrice(array $node): array
    {
        $priceNodes = array(
            $node['sellingMode']['price'] ?? null,
            $node['price'] ?? null,
            $node['sellingPrice'] ?? null,
            $node['offerPrice'] ?? null,
        );

        foreach ($priceNodes as $priceNode) {
            if (!is_array($priceNode)) {
                continue;
            }

            $amount = trim((string) ($priceNode['amount'] ?? ''));
            $currency = trim((string) ($priceNode['currency'] ?? ''));
            if ($amount !== '') {
                return array(
                    'price' => $amount,
                    'currency' => $currency,
                );
            }
        }

        return array(
            'price' => null,
            'currency' => '',
        );
    }

    private function normalizeStatusLabel(string $status): string
    {
        $status = strtoupper(trim($status));
        if ($status === '') {
            return '-';
        }

        $map = array(
            'ACTIVE' => 'Aktywna',
            'ENDED' => 'Zakonczona',
            'INACTIVE' => 'Nieaktywna',
            'ACTIVATING' => 'Aktywacja',
        );

        return $map[$status] ?? $status;
    }

    private function decodeJsonList($value): array
    {
        if (!is_string($value) || $value === '') {
            return array();
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : array();
    }

    private function decodeJsonAny($value)
    {
        if (!is_string($value) || $value === '') {
            return array();
        }

        $decoded = json_decode($value, true);
        return $decoded !== null ? $decoded : array();
    }

    private function ensureOfferExtensions(): void
    {
        $this->ensureColumn('allegro_offers', 'warehouse_product_id', 'INT UNSIGNED DEFAULT NULL');
        $this->ensureColumn('allegro_offers', 'linked_by', 'VARCHAR(40) DEFAULT NULL');
        $this->ensureColumn('allegro_offers', 'image_count', 'INT UNSIGNED NOT NULL DEFAULT 0');
        $this->ensureColumn('allegro_offers', 'primary_image_hash', 'CHAR(64) DEFAULT NULL');
        $this->ensureColumn('allegro_offers', 'invoice_type', 'VARCHAR(40) DEFAULT NULL');
        $this->ensureColumn('allegro_offers', 'allegro_product_id', 'VARCHAR(64) DEFAULT NULL');
        $this->ensureColumn('allegro_offers', 'marketplaces_json', 'LONGTEXT DEFAULT NULL');
        $this->ensureColumn('allegro_offers', 'product_set_json', 'LONGTEXT DEFAULT NULL');
    }

    private function ensureOfferIndexes(): void
    {
        $this->ensureIndex('allegro_offers', 'idx_allegro_offers_account_linked', 'CREATE INDEX idx_allegro_offers_account_linked ON allegro_offers (account_id, warehouse_product_id)');
        $this->ensureIndex('allegro_offers', 'idx_allegro_offers_account_invoice', 'CREATE INDEX idx_allegro_offers_account_invoice ON allegro_offers (account_id, invoice_type)');
        $this->ensureIndex('allegro_offers', 'idx_allegro_offers_account_stock', 'CREATE INDEX idx_allegro_offers_account_stock ON allegro_offers (account_id, stock_available)');
        $this->ensureIndex('allegro_offers', 'idx_allegro_offers_status_updated', 'CREATE INDEX idx_allegro_offers_status_updated ON allegro_offers (publication_status, updated_at)');
        $this->ensureIndex('allegro_offers', 'idx_allegro_offers_status_id', 'CREATE INDEX idx_allegro_offers_status_id ON allegro_offers (publication_status, id)');
        $this->ensureIndex('allegro_offers', 'idx_allegro_offers_account_status_id', 'CREATE INDEX idx_allegro_offers_account_status_id ON allegro_offers (account_id, publication_status, id)');
        $this->ensureIndex('allegro_offers', 'idx_allegro_offers_duplicate_probe', 'CREATE INDEX idx_allegro_offers_duplicate_probe ON allegro_offers (allegro_product_id, primary_image_hash)');
        $this->ensureIndex('allegro_offers', 'idx_allegro_offers_duplicate_name_image', 'CREATE INDEX idx_allegro_offers_duplicate_name_image ON allegro_offers (account_id, publication_status, name, primary_image_url(191))');
        $this->ensureIndex('allegro_offers', 'idx_allegro_offers_duplicate_hash_status', 'CREATE INDEX idx_allegro_offers_duplicate_hash_status ON allegro_offers (account_id, publication_status, name, primary_image_hash, id)');
        $this->ensureIndex('allegro_offer_change_queue', 'idx_allegro_offer_change_queue_offer_latest', 'CREATE INDEX idx_allegro_offer_change_queue_offer_latest ON allegro_offer_change_queue (offer_row_id, id, status)');
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        $schema = $this->mysqlSchema();
        if ($schema === '') {
            return;
        }

        $exists = (int) $this->database->fetchColumn(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name',
            array(
                'schema' => $schema,
                'table_name' => $table,
                'column_name' => $column,
            )
        ) > 0;

        if ($exists) {
            return;
        }

        $this->database->query('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    }

    private function ensureIndex(string $table, string $indexName, string $sql): void
    {
        $schema = $this->mysqlSchema();
        if ($schema === '') {
            return;
        }

        $exists = (int) $this->database->fetchColumn(
            'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name',
            array(
                'schema' => $schema,
                'table_name' => $table,
                'index_name' => $indexName,
            )
        ) > 0;

        if ($exists) {
            return;
        }

        $this->database->query($sql);
    }

    private function mysqlSchema(): string
    {
        $config = Config::get('database');
        return isset($config['database']) ? (string) $config['database'] : '';
    }

    private function appendQuantityFilter(array &$whereParts, array &$params, string $rawValue, string $columnSql, string $prefix): void
    {
        $parsed = $this->parseNegatedFilterValue($rawValue);
        $value = (string) ($parsed['value'] ?? '');
        if ($value === '') {
            return;
        }

        $negated = !empty($parsed['negated']);

        if (preg_match('/^\s*(\d+)\s*\-\s*(\d+)\s*$/', $value, $matches) === 1) {
            $from = (int) $matches[1];
            $to = (int) $matches[2];
            if ($from > $to) {
                $tmp = $from;
                $from = $to;
                $to = $tmp;
            }
            $whereParts[] = $columnSql . ($negated ? ' NOT BETWEEN ' : ' BETWEEN ') . ':' . $prefix . '_from AND :' . $prefix . '_to';
            $params[$prefix . '_from'] = $from;
            $params[$prefix . '_to'] = $to;
            return;
        }

        $whereParts[] = 'CAST(' . $columnSql . ' AS CHAR) ' . ($negated ? 'NOT LIKE' : 'LIKE') . ' :' . $prefix;
        $params[$prefix] = '%' . $value . '%';
    }

    private function appendQuantityRangeFilter(
        array &$whereParts,
        array &$params,
        string $fromRaw,
        string $toRaw,
        string $columnSql,
        string $prefix
    ): void {
        $fromRaw = trim($fromRaw);
        $toRaw = trim($toRaw);

        $hasFrom = $fromRaw !== '' && preg_match('/^\d+$/', $fromRaw) === 1;
        $hasTo = $toRaw !== '' && preg_match('/^\d+$/', $toRaw) === 1;

        if (!$hasFrom && !$hasTo) {
            return;
        }

        if ($hasFrom && $hasTo) {
            $from = (int) $fromRaw;
            $to = (int) $toRaw;
            if ($from > $to) {
                $tmp = $from;
                $from = $to;
                $to = $tmp;
            }
            $whereParts[] = $columnSql . ' BETWEEN :' . $prefix . '_from AND :' . $prefix . '_to';
            $params[$prefix . '_from'] = $from;
            $params[$prefix . '_to'] = $to;
            return;
        }

        if ($hasFrom) {
            $whereParts[] = $columnSql . ' >= :' . $prefix . '_from';
            $params[$prefix . '_from'] = (int) $fromRaw;
            return;
        }

        $whereParts[] = $columnSql . ' <= :' . $prefix . '_to';
        $params[$prefix . '_to'] = (int) $toRaw;
    }

    private function effectiveWarehouseQuantityFilter(array $filters): array
    {
        $fromRaw = isset($filters['warehouse_quantity_from']) ? trim((string) $filters['warehouse_quantity_from']) : '';
        $toRaw = isset($filters['warehouse_quantity_to']) ? trim((string) $filters['warehouse_quantity_to']) : '';

        $hasFrom = $fromRaw !== '' && preg_match('/^\d+$/', $fromRaw) === 1;
        $hasTo = $toRaw !== '' && preg_match('/^\d+$/', $toRaw) === 1;

        if (!$hasFrom && !$hasTo) {
            return array(
                'active' => false,
                'from' => null,
                'to' => null,
            );
        }

        $from = $hasFrom ? (int) $fromRaw : null;
        $to = $hasTo ? (int) $toRaw : null;
        if ($from !== null && $to !== null && $from > $to) {
            $tmp = $from;
            $from = $to;
            $to = $tmp;
        }

        return array(
            'active' => true,
            'from' => $from,
            'to' => $to,
        );
    }

    private function filtersWithoutWarehouseQuantity(array $filters): array
    {
        unset($filters['warehouse_quantity_from'], $filters['warehouse_quantity_to'], $filters['warehouse_quantity']);
        return $filters;
    }

    private function matchesEffectiveWarehouseQuantityFilter(int $quantity, array $filter): bool
    {
        if (empty($filter['active'])) {
            return true;
        }

        if (isset($filter['from']) && $filter['from'] !== null && $quantity < (int) $filter['from']) {
            return false;
        }

        if (isset($filter['to']) && $filter['to'] !== null && $quantity > (int) $filter['to']) {
            return false;
        }

        return true;
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

    private function offerCountCacheKey(array $filters): string
    {
        $normalized = array();

        foreach ($filters as $key => $value) {
            $normalized[(string) $key] = trim((string) $value);
        }

        ksort($normalized);

        return 'allegro:offers:count:' . sha1((string) json_encode($normalized));
    }
}
