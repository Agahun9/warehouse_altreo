<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;

class SellasistOrderSyncRepository
{
    const TABLE = 'sellasist_order_sync_logs';
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
            . "operation VARCHAR(32) NOT NULL DEFAULT 'subtract_stock',\n"
            . "process_date DATE NOT NULL,\n"
            . "order_id BIGINT UNSIGNED NOT NULL,\n"
            . "order_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,\n"
            . "currency VARCHAR(10) DEFAULT 'PLN',\n"
            . "items_count INT UNSIGNED NOT NULL DEFAULT 0,\n"
            . "payload_json LONGTEXT NOT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_sellasist_sync_daily_order (operation, process_date, order_id),\n"
            . "KEY idx_sellasist_sync_process_date (process_date),\n"
            . "KEY idx_sellasist_sync_order_id (order_id)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::$schemaEnsured = true;
    }

    public function recordDailyOrder(string $operation, int $orderId, float $orderTotal, string $currency, int $itemsCount, array $payload): void
    {
        $operation = trim($operation) !== '' ? trim($operation) : 'subtract_stock';
        $processDate = date('Y-m-d');

        $existing = $this->database->fetch(
            'SELECT id FROM ' . self::TABLE . ' WHERE operation = :operation AND process_date = :process_date AND order_id = :order_id LIMIT 1',
            array(
                'operation' => $operation,
                'process_date' => $processDate,
                'order_id' => $orderId,
            )
        );

        $data = array(
            'operation' => $operation,
            'process_date' => $processDate,
            'order_id' => $orderId,
            'order_total' => round($orderTotal, 2),
            'currency' => trim($currency) !== '' ? trim($currency) : 'PLN',
            'items_count' => max(0, $itemsCount),
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        if ($existing && isset($existing['id'])) {
            $this->database->update(
                self::TABLE,
                array(
                    'order_total' => $data['order_total'],
                    'currency' => $data['currency'],
                    'items_count' => $data['items_count'],
                    'payload_json' => $data['payload_json'],
                ),
                'id = :id',
                array('id' => (int) $existing['id'])
            );

            return;
        }

        $this->database->insert(self::TABLE, $data);
    }

    public function todaySummary(string $operation = 'subtract_stock'): array
    {
        $row = $this->database->fetch(
            'SELECT COUNT(*) AS orders_count, COALESCE(SUM(order_total), 0) AS total_value, MAX(currency) AS currency'
            . ' FROM ' . self::TABLE
            . ' WHERE operation = :operation AND process_date = :process_date',
            array(
                'operation' => $operation,
                'process_date' => date('Y-m-d'),
            )
        );

        return array(
            'orders_count' => isset($row['orders_count']) ? (int) $row['orders_count'] : 0,
            'total_value' => isset($row['total_value']) ? (float) $row['total_value'] : 0.0,
            'currency' => isset($row['currency']) && trim((string) $row['currency']) !== '' ? (string) $row['currency'] : 'PLN',
        );
    }

    public function dailySeries(string $operation = 'subtract_stock', int $days = 7): array
    {
        $days = max(2, min(31, $days));
        $startDate = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));

        $rows = $this->database->fetchAll(
            'SELECT process_date, COUNT(*) AS orders_count, COALESCE(SUM(order_total), 0) AS total_value, MAX(currency) AS currency'
            . ' FROM ' . self::TABLE
            . ' WHERE operation = :operation AND process_date >= :start_date'
            . ' GROUP BY process_date'
            . ' ORDER BY process_date ASC',
            array(
                'operation' => $operation,
                'start_date' => $startDate,
            )
        );

        $rowsByDate = array();
        foreach ($rows as $row) {
            $date = isset($row['process_date']) ? (string) $row['process_date'] : '';
            if ($date === '') {
                continue;
            }

            $rowsByDate[$date] = array(
                'date' => $date,
                'label' => date('d.m', strtotime($date)),
                'orders_count' => isset($row['orders_count']) ? (int) $row['orders_count'] : 0,
                'total_value' => isset($row['total_value']) ? (float) $row['total_value'] : 0.0,
                'currency' => isset($row['currency']) && trim((string) $row['currency']) !== '' ? (string) $row['currency'] : 'PLN',
            );
        }

        $series = array();
        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $date = date('Y-m-d', strtotime('-' . $offset . ' days'));
            $series[] = isset($rowsByDate[$date]) ? $rowsByDate[$date] : array(
                'date' => $date,
                'label' => date('d.m', strtotime($date)),
                'orders_count' => 0,
                'total_value' => 0.0,
                'currency' => 'PLN',
            );
        }

        return $series;
    }
}
