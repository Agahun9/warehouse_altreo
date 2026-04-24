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

        self::$schemaEnsured = true;
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
        $existing = $this->database->fetch(
            'SELECT id FROM empik_offers WHERE account_id = :account_id AND offer_id = :offer_id LIMIT 1',
            array('account_id' => $data['account_id'], 'offer_id' => $data['offer_id'])
        );

        if ($existing) {
            $this->database->update('empik_offers', $data, 'id = :id', array('id' => (int) $existing['id']));
            return;
        }

        $this->database->insert('empik_offers', $data);
    }

    public function countOffers(array $filters = array()): int
    {
        $params = array();
        $where = $this->buildOfferWhere($filters, $params);

        return (int) $this->database->fetchColumn(
            'SELECT COUNT(*) FROM empik_offers offers'
            . ' INNER JOIN empik_accounts accounts ON accounts.id = offers.account_id'
            . ' WHERE ' . $where,
            $params
        );
    }

    public function listOffers(array $filters, int $page, int $perPage): array
    {
        $params = array();
        $where = $this->buildOfferWhere($filters, $params);
        $offset = max(0, ($page - 1) * $perPage);

        $sql = 'SELECT offers.*, accounts.name AS account_name'
            . ' FROM empik_offers offers'
            . ' INNER JOIN empik_accounts accounts ON accounts.id = offers.account_id'
            . ' WHERE ' . $where
            . ' ORDER BY offers.last_synced_at DESC, offers.id DESC'
            . ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;

        return $this->database->fetchAll($sql, $params);
    }

    public function findOfferRowById(int $id)
    {
        return $this->database->fetch(
            'SELECT offers.*, accounts.name AS account_name, accounts.api_url, accounts.shop_id, accounts.locale'
            . ' FROM empik_offers offers'
            . ' INNER JOIN empik_accounts accounts ON accounts.id = offers.account_id'
            . ' WHERE offers.id = :id LIMIT 1',
            array('id' => $id)
        );
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
        $data = array(
            'cache_key' => $key,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'expires_at' => $expiresAt,
        );

        $existing = $this->database->fetch('SELECT cache_key FROM empik_cache WHERE cache_key = :cache_key LIMIT 1', array('cache_key' => $key));
        if ($existing) {
            $this->database->update('empik_cache', array(
                'payload' => $data['payload'],
                'expires_at' => $data['expires_at'],
            ), 'cache_key = :cache_key', array('cache_key' => $key));
            return;
        }

        $this->database->insert('empik_cache', $data);
    }

    private function buildOfferWhere(array $filters, array &$params): string
    {
        $where = array('1=1');

        $accountId = trim((string) ($filters['account_id'] ?? ''));
        if ($accountId !== '' && ctype_digit($accountId) && (int) $accountId > 0) {
            $where[] = 'offers.account_id = :account_id';
            $params['account_id'] = (int) $accountId;
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(offers.product_title LIKE :query OR offers.description LIKE :query OR offers.category_label LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        $sku = trim((string) ($filters['sku'] ?? ''));
        if ($sku !== '') {
            $where[] = '(offers.shop_sku LIKE :sku OR offers.product_sku LIKE :sku OR offers.product_id LIKE :sku)';
            $params['sku'] = '%' . $sku . '%';
        }

        $state = trim((string) ($filters['state'] ?? ''));
        if ($state !== '') {
            $where[] = 'offers.state_code = :state_code';
            $params['state_code'] = $state;
        }

        $active = trim((string) ($filters['active'] ?? ''));
        if ($active === '1' || $active === '0') {
            $where[] = 'offers.active = :active';
            $params['active'] = (int) $active;
        }

        return implode(' AND ', $where);
    }
}
