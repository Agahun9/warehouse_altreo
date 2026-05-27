<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;
use App\Services\ProductChangeAuditService;
use Throwable;

class ProductTemuParameterRepository
{
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
            "CREATE TABLE IF NOT EXISTS product_temu_parameters (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "product_id INT UNSIGNED NOT NULL,\n"
            . "parameter_id VARCHAR(190) NOT NULL,\n"
            . "value LONGTEXT NOT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_product_temu_param (product_id, parameter_id),\n"
            . "KEY idx_product_temu_product_id (product_id),\n"
            . "CONSTRAINT fk_product_temu_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::$schemaEnsured = true;
    }

    public function allForProduct($productId): array
    {
        $rows = $this->database->fetchAll(
            'SELECT parameter_id, value FROM product_temu_parameters WHERE product_id = :product_id ORDER BY parameter_id',
            array('product_id' => (int) $productId)
        );

        $result = array();
        foreach ($rows as $row) {
            $decoded = json_decode((string) $row['value'], true);
            $result[(string) $row['parameter_id']] = $decoded;
        }

        return $result;
    }

    public function replaceForProduct($productId, array $values): void
    {
        $productId = (int) $productId;
        $audit = ProductChangeAuditService::instance($this->database);
        $audit->rememberBefore(array($productId));

        try {
            $this->database->transaction(function () use ($productId, $values) {
                $this->database->delete('product_temu_parameters', 'product_id = :product_id', array('product_id' => $productId));

                foreach ($values as $parameterId => $value) {
                    $this->database->insert('product_temu_parameters', array(
                        'product_id' => $productId,
                        'parameter_id' => (string) $parameterId,
                        'value' => json_encode($value),
                    ));
                }

                return true;
            });

            $audit->rememberAfter(array($productId), 'update');
        } catch (Throwable $exception) {
            throw $exception;
        }
    }
}
