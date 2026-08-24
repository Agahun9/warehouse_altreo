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
        $this->ensureIndex(
            'idx_product_change_logs_actor_product_created',
            'ALTER TABLE ' . self::TABLE . ' ADD KEY idx_product_change_logs_actor_product_created (actor_name(64), product_id, created_at)'
        );
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

    public function lastSellasistSubtractDatesForProducts(array $productIds): array
    {
        $normalizedIds = array();
        foreach ($productIds as $productId) {
            $productId = (int) $productId;
            if ($productId > 0) {
                $normalizedIds[$productId] = $productId;
            }
        }

        if ($normalizedIds === array()) {
            return array();
        }

        $params = array();
        $placeholders = array();
        foreach (array_values($normalizedIds) as $index => $productId) {
            $key = 'product_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $productId;
        }

        $rows = $this->database->fetchAll(
            'SELECT product_id, MAX(created_at) AS last_sale_date'
            . ' FROM ' . self::TABLE
            . ' WHERE product_id IN (' . implode(', ', $placeholders) . ')'
            . '   AND actor_name = "Sellasist"'
            . '   AND summary LIKE "Odjeto stan magazynowy przez Sellasist%"'
            . ' GROUP BY product_id',
            $params
        );

        $dates = array();
        foreach ($rows as $row) {
            $productId = isset($row['product_id']) ? (int) $row['product_id'] : 0;
            if ($productId > 0) {
                $dates[$productId] = (string) ($row['last_sale_date'] ?? '');
            }
        }

        return $dates;
    }

    public function salesHistoryForProduct(int $productId, int $limit = 0): array
    {
        $productId = (int) $productId;
        if ($productId <= 0) {
            return $this->emptySalesHistory();
        }

        $limit = max(0, min(10000, $limit));
        $sql = 'SELECT id, product_id, summary, changes_json, created_at'
            . ' FROM ' . self::TABLE
            . ' WHERE product_id = :product_id'
            . '   AND actor_name = "Sellasist"'
            . '   AND summary LIKE "Odjeto stan magazynowy przez Sellasist%"'
            . ' ORDER BY created_at DESC, id DESC';

        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }

        $rows = $this->database->fetchAll($sql, array('product_id' => $productId));

        $dailyMap = array();
        $events = array();
        $totalQuantity = 0;

        foreach ($rows as $row) {
            $summary = (string) ($row['summary'] ?? '');
            $createdAt = (string) ($row['created_at'] ?? '');
            $saleDate = substr($createdAt, 0, 10);
            if ($saleDate === '') {
                $saleDate = 'brak-daty';
            }

            $quantity = $this->extractSellasistQuantity($summary, (string) ($row['changes_json'] ?? '[]'));
            if ($quantity <= 0) {
                $quantity = 1;
            }

            if (!isset($dailyMap[$saleDate])) {
                $dailyMap[$saleDate] = array(
                    'date' => $saleDate,
                    'quantity' => 0,
                    'orders_count' => 0,
                    'signatures' => array(),
                    'last_event_at' => $createdAt,
                );
            }

            $signature = $this->extractSummaryPart($summary, '/sygnatura:\s*([^|.]+)/i');
            $orderId = $this->extractSummaryPart($summary, '/zamowienia\s+#([0-9]+)/i');
            $sourceName = $this->extractSummaryPart($summary, '/pozycja:\s*(.*?)\s*\|\s*sygnatura:/i');

            $dailyMap[$saleDate]['quantity'] += $quantity;
            $dailyMap[$saleDate]['orders_count']++;
            if ($signature !== '') {
                $dailyMap[$saleDate]['signatures'][$signature] = $signature;
            }
            if ($createdAt > (string) $dailyMap[$saleDate]['last_event_at']) {
                $dailyMap[$saleDate]['last_event_at'] = $createdAt;
            }

            $totalQuantity += $quantity;
            $events[] = array(
                'created_at' => $createdAt,
                'date' => $saleDate,
                'quantity' => $quantity,
                'order_id' => $orderId,
                'signature' => $signature,
                'source_name' => $sourceName,
                'summary' => $summary,
            );
        }

        krsort($dailyMap);
        $dailyRows = array_values($dailyMap);
        foreach ($dailyRows as $index => $day) {
            $signatures = array_values($day['signatures']);
            $dailyRows[$index]['signatures'] = $signatures;
            $dailyRows[$index]['signatures_preview'] = implode(', ', array_slice($signatures, 0, 6));
            $dailyRows[$index]['more_signatures_count'] = max(0, count($signatures) - 6);
        }

        $peakDay = array();
        foreach ($dailyRows as $day) {
            if ($peakDay === array() || (int) $day['quantity'] > (int) $peakDay['quantity']) {
                $peakDay = $day;
            }
        }

        return array(
            'total_quantity' => $totalQuantity,
            'days_count' => count($dailyRows),
            'events_count' => count($events),
            'last_sale_date' => isset($events[0]['created_at']) ? (string) $events[0]['created_at'] : '',
            'average_per_day' => count($dailyRows) > 0 ? round($totalQuantity / count($dailyRows), 2) : 0,
            'peak_day' => $peakDay,
            'daily' => $dailyRows,
            'events' => $events,
        );
    }

    private function trimToLatest(int $productId, int $keep): void
    {
        $keep = max(1, $keep);

        $this->database->query(
            'DELETE FROM ' . self::TABLE
            . ' WHERE product_id = :product_id'
            . ' AND NOT (actor_name = "Sellasist" AND summary LIKE "Odjeto stan magazynowy przez Sellasist%")'
            . ' AND id NOT IN ('
            . ' SELECT kept.id FROM ('
            . '   SELECT id FROM ' . self::TABLE
            . '   WHERE product_id = :keep_product_id'
            . '   AND NOT (actor_name = "Sellasist" AND summary LIKE "Odjeto stan magazynowy przez Sellasist%")'
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

    private function ensureIndex(string $indexName, string $ddl): void
    {
        $exists = (int) $this->database->fetchColumn(
            'SELECT COUNT(*) FROM information_schema.STATISTICS'
            . ' WHERE TABLE_SCHEMA = DATABASE()'
            . ' AND TABLE_NAME = :table_name'
            . ' AND INDEX_NAME = :index_name',
            array(
                'table_name' => self::TABLE,
                'index_name' => $indexName,
            )
        );

        if ($exists <= 0) {
            $this->database->query($ddl);
        }
    }

    private function emptySalesHistory(): array
    {
        return array(
            'total_quantity' => 0,
            'days_count' => 0,
            'events_count' => 0,
            'last_sale_date' => '',
            'average_per_day' => 0,
            'peak_day' => array(),
            'daily' => array(),
            'events' => array(),
        );
    }

    private function extractSellasistQuantity(string $summary, string $changesJson): int
    {
        if (preg_match('/ilosc:\s*(\d+)/i', $summary, $matches) === 1) {
            return max(0, (int) $matches[1]);
        }

        $changes = json_decode($changesJson, true);
        if (!is_array($changes)) {
            return 0;
        }

        foreach ($changes as $change) {
            if (!is_array($change) || (string) ($change['field'] ?? '') !== 'quantity') {
                continue;
            }

            $before = (int) ($change['before'] ?? 0);
            $after = (int) ($change['after'] ?? 0);
            return max(0, $before - $after);
        }

        return 0;
    }

    private function extractSummaryPart(string $summary, string $pattern): string
    {
        if (preg_match($pattern, $summary, $matches) !== 1) {
            return '';
        }

        return trim((string) ($matches[1] ?? ''));
    }
}
