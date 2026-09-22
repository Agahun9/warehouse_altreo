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

    public function availableParameterFieldOptions(): array
    {
        $rows = $this->database->fetchAll(
            "SELECT name, temu_category_parameters FROM categories WHERE temu_category_parameters IS NOT NULL AND TRIM(temu_category_parameters) <> '' ORDER BY name ASC"
        );

        $definitionsById = array();
        $categoriesById = array();
        foreach ($rows as $row) {
            $decoded = json_decode((string) ($row['temu_category_parameters'] ?? ''), true);
            if (!is_array($decoded)) {
                continue;
            }

            foreach ($decoded as $definition) {
                if (!is_array($definition)) {
                    continue;
                }
                $parameterId = trim((string) ($definition['id'] ?? $definition['code'] ?? ''));
                if ($parameterId === '') {
                    continue;
                }

                if (!isset($definitionsById[$parameterId])) {
                    $definitionsById[$parameterId] = $definition;
                    $categoriesById[$parameterId] = array();
                }

                $categoryName = trim((string) ($row['name'] ?? ''));
                if ($categoryName !== '' && !in_array($categoryName, $categoriesById[$parameterId], true)) {
                    $categoriesById[$parameterId][] = $categoryName;
                }
            }
        }

        $options = array();
        foreach ($definitionsById as $parameterId => $definition) {
            $name = trim((string) ($definition['name'] ?? $definition['label'] ?? $parameterId));
            $label = 'Temu: ' . ($name !== '' ? $name : $parameterId) . ' [' . $parameterId . ']';
            if (!empty($categoriesById[$parameterId])) {
                $label .= ' | Kategorie: ' . implode(', ', $categoriesById[$parameterId]);
            }

            $fieldKey = 'product.temu_parameter.' . $parameterId;
            $options[$fieldKey] = $label;

            if (!empty($definition['multiple'])) {
                $restrictions = isset($definition['restrictions']) && is_array($definition['restrictions']) ? $definition['restrictions'] : array();
                $max = max(1, min(10, (int) ($restrictions['choose_max_num'] ?? 5)));
                for ($index = 0; $index < $max; $index++) {
                    $options[$fieldKey . '[' . $index . ']'] = $label . ' | wartość ' . ($index + 1);
                }
            }
        }

        return $options;
    }
}
