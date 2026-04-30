<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;

class SellasistStockCallFailureRepository
{
    const TABLE = 'sellasist_request_failures';

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
            . "operation VARCHAR(32) NOT NULL,\n"
            . "request_method VARCHAR(16) DEFAULT NULL,\n"
            . "order_id BIGINT UNSIGNED DEFAULT NULL,\n"
            . "response_status INT UNSIGNED DEFAULT NULL,\n"
            . "request_uri VARCHAR(1000) DEFAULT NULL,\n"
            . "remote_addr VARCHAR(64) DEFAULT NULL,\n"
            . "user_agent VARCHAR(500) DEFAULT NULL,\n"
            . "error_message TEXT NOT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "KEY idx_sellasist_request_failures_created (created_at),\n"
            . "KEY idx_sellasist_request_failures_operation (operation),\n"
            . "KEY idx_sellasist_request_failures_order (order_id)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::$schemaEnsured = true;
    }

    public function record(string $operation, ?int $orderId, string $errorMessage, ?int $responseStatus = null): void
    {
        $operation = $this->normalizeOperation($operation);
        $orderId = $orderId !== null && $orderId > 0 ? $orderId : null;
        $errorMessage = trim($errorMessage) !== '' ? trim($errorMessage) : 'Nieznany blad wywolania Sellasist.';
        $responseStatus = $responseStatus !== null && $responseStatus > 0 ? $responseStatus : null;

        $this->database->insert(self::TABLE, array(
            'operation' => $operation,
            'request_method' => $this->truncate($this->serverValue('REQUEST_METHOD'), 16),
            'order_id' => $orderId,
            'response_status' => $responseStatus,
            'request_uri' => $this->truncate($this->serverValue('REQUEST_URI'), 1000),
            'remote_addr' => $this->truncate($this->serverValue('REMOTE_ADDR'), 64),
            'user_agent' => $this->truncate($this->serverValue('HTTP_USER_AGENT'), 500),
            'error_message' => $errorMessage,
        ));
    }

    public function summary(int $latestLimit = 5): array
    {
        $latestLimit = max(1, min(20, $latestLimit));
        $since = date('Y-m-d H:i:s', strtotime('-24 hours'));

        $row = $this->database->fetch(
            'SELECT COUNT(*) AS total_count,'
            . ' SUM(CASE WHEN created_at >= :since THEN 1 ELSE 0 END) AS last_24h_count,'
            . ' MAX(created_at) AS latest_at'
            . ' FROM ' . self::TABLE,
            array('since' => $since)
        );

        $latest = $this->database->fetchAll(
            'SELECT id, operation, request_method, order_id, response_status, error_message, created_at'
            . ' FROM ' . self::TABLE
            . ' ORDER BY created_at DESC, id DESC'
            . ' LIMIT ' . $latestLimit
        );

        return array(
            'total_count' => isset($row['total_count']) ? (int) $row['total_count'] : 0,
            'last_24h_count' => isset($row['last_24h_count']) ? (int) $row['last_24h_count'] : 0,
            'latest_at' => isset($row['latest_at']) && trim((string) $row['latest_at']) !== '' ? (string) $row['latest_at'] : null,
            'latest' => $this->hydrateLatest($latest),
        );
    }

    public function deleteAll(): int
    {
        return $this->database->delete(self::TABLE, '1 = 1');
    }

    private function hydrateLatest(array $rows): array
    {
        foreach ($rows as $index => $row) {
            $rows[$index]['operation_label'] = $this->operationLabel((string) ($row['operation'] ?? ''));
            $rows[$index]['error_message'] = trim((string) ($row['error_message'] ?? ''));
        }

        return $rows;
    }

    private function operationLabel(string $operation): string
    {
        if ($operation === 'add_stock') {
            return 'Dodawanie stanu';
        }

        if ($operation === 'subtract_stock') {
            return 'Odejmowanie stanu';
        }

        return 'Wywolanie Sellasist';
    }

    private function normalizeOperation(string $operation): string
    {
        $operation = trim($operation);
        return in_array($operation, array('subtract_stock', 'add_stock'), true) ? $operation : 'stock_call';
    }

    private function serverValue(string $key): string
    {
        return isset($_SERVER[$key]) ? trim((string) $_SERVER[$key]) : '';
    }

    private function truncate(string $value, int $limit): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $limit, 'UTF-8');
        }

        return substr($value, 0, $limit);
    }
}
