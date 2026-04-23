<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;

class ProductChangeLogRepository
{
    const TABLE = 'product_change_logs';
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
            "CREATE TABLE IF NOT EXISTS " . self::TABLE . " (\n"
            . "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "product_id INT UNSIGNED NOT NULL,\n"
            . "product_name_snapshot VARCHAR(255) DEFAULT NULL,\n"
            . "product_sku_snapshot VARCHAR(64) DEFAULT NULL,\n"
            . "actor_user_id INT UNSIGNED DEFAULT NULL,\n"
            . "actor_name VARCHAR(255) DEFAULT NULL,\n"
            . "actor_email VARCHAR(190) DEFAULT NULL,\n"
            . "action VARCHAR(32) NOT NULL DEFAULT 'update',\n"
            . "change_count INT UNSIGNED NOT NULL DEFAULT 0,\n"
            . "summary VARCHAR(1000) DEFAULT NULL,\n"
            . "changes_json LONGTEXT NOT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "KEY idx_product_change_logs_product_created (product_id, created_at),\n"
            . "KEY idx_product_change_logs_created_at (created_at),\n"
            . "KEY idx_product_change_logs_actor_user_id (actor_user_id)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->ensureColumn('actor_name', "ALTER TABLE " . self::TABLE . " ADD COLUMN actor_name VARCHAR(255) DEFAULT NULL AFTER actor_user_id");
        self::$schemaEnsured = true;
    }

    public function add(array $entry): void
    {
        $productId = isset($entry['product_id']) ? (int) $entry['product_id'] : 0;
        if ($productId <= 0) {
            return;
        }

        $this->database->insert(self::TABLE, array(
            'product_id' => $productId,
            'product_name_snapshot' => $this->normalizeNullableString($entry['product_name_snapshot'] ?? null),
            'product_sku_snapshot' => $this->normalizeNullableString($entry['product_sku_snapshot'] ?? null),
            'actor_user_id' => isset($entry['actor_user_id']) ? (int) $entry['actor_user_id'] : null,
            'actor_name' => $this->normalizeNullableString($entry['actor_name'] ?? null),
            'actor_email' => $this->normalizeNullableString($entry['actor_email'] ?? null),
            'action' => $this->normalizeAction((string) ($entry['action'] ?? 'update')),
            'change_count' => max(0, (int) ($entry['change_count'] ?? 0)),
            'summary' => $this->normalizeNullableString($entry['summary'] ?? null),
            'changes_json' => isset($entry['changes_json']) ? (string) $entry['changes_json'] : '[]',
        ));

        $this->trimToLatest($productId, 100);
    }

    public function latest(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $rows = $this->database->fetchAll(
            'SELECT * FROM ' . self::TABLE . ' ORDER BY created_at DESC, id DESC LIMIT ' . $limit
        );

        return $this->hydrateRows($rows);
    }

    public function historyForProduct(int $productId, int $limit = 20): array
    {
        $productId = (int) $productId;
        if ($productId <= 0) {
            return array();
        }

        $limit = max(1, min(100, $limit));
        $rows = $this->database->fetchAll(
            'SELECT * FROM ' . self::TABLE . ' WHERE product_id = :product_id ORDER BY created_at DESC, id DESC LIMIT ' . $limit,
            array('product_id' => $productId)
        );

        return $this->hydrateRows($rows);
    }

    private function trimToLatest(int $productId, int $keep): void
    {
        $keep = max(1, $keep);

        $this->database->query(
            'DELETE FROM ' . self::TABLE
            . ' WHERE product_id = :product_id'
            . ' AND id NOT IN ('
            . ' SELECT kept.id FROM ('
            . '   SELECT id FROM ' . self::TABLE
            . '   WHERE product_id = :keep_product_id'
            . '   ORDER BY created_at DESC, id DESC'
            . '   LIMIT ' . $keep
            . ' ) kept'
            . ' )',
            array(
                'product_id' => $productId,
                'keep_product_id' => $productId,
            )
        );
    }

    private function hydrateRows(array $rows): array
    {
        foreach ($rows as $index => $row) {
            $changes = json_decode((string) ($row['changes_json'] ?? '[]'), true);
            $rows[$index]['changes'] = is_array($changes) ? $changes : array();
            $rows[$index]['summary'] = isset($row['summary']) ? (string) $row['summary'] : '';
            $rows[$index]['actor_display'] = $this->actorDisplay($row);
            $rows[$index]['action_label'] = $this->actionLabel((string) ($row['action'] ?? 'update'));
        }

        return $rows;
    }

    private function actorDisplay(array $row): string
    {
        $name = trim((string) ($row['actor_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $email = trim((string) ($row['actor_email'] ?? ''));
        if ($email !== '') {
            return $email;
        }

        $userId = isset($row['actor_user_id']) ? (int) $row['actor_user_id'] : 0;
        if ($userId > 0) {
            return 'Uzytkownik #' . $userId;
        }

        return 'System';
    }

    private function actionLabel(string $action): string
    {
        $labels = array(
            'create' => 'Utworzenie',
            'copy' => 'Kopia',
            'update' => 'Edycja',
            'delete' => 'Usuniecie',
        );

        return isset($labels[$action]) ? $labels[$action] : 'Zmiana';
    }

    private function normalizeAction(string $action): string
    {
        $allowed = array('create', 'copy', 'update', 'delete');
        return in_array($action, $allowed, true) ? $action : 'update';
    }

    private function normalizeNullableString($value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }

    private function ensureColumn(string $columnName, string $ddl): void
    {
        $exists = (int) $this->database->fetchColumn(
            'SELECT COUNT(*) FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE()'
            . ' AND TABLE_NAME = :table_name'
            . ' AND COLUMN_NAME = :column_name',
            array(
                'table_name' => self::TABLE,
                'column_name' => $columnName,
            )
        );

        if ($exists <= 0) {
            $this->database->query($ddl);
        }
    }
}
