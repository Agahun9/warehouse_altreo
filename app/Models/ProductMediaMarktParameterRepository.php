<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;
use App\Services\MediaMarktService;
use App\Services\ProductChangeAuditService;
use Throwable;

class ProductMediaMarktParameterRepository
{
    /** @var bool */
    private static $schemaEnsured = false;

    /** @var Database */
    private $database;

    /** @var MediaMarktService|null */
    private $mediamarkt;

    public function __construct(Database $database)
    {
        $this->database = $database;
        $this->mediamarkt = null;
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
            "CREATE TABLE IF NOT EXISTS product_mediamarkt_parameters (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "product_id INT UNSIGNED NOT NULL,\n"
            . "parameter_id VARCHAR(190) NOT NULL,\n"
            . "value LONGTEXT NOT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_product_mediamarkt_param (product_id, parameter_id),\n"
            . "KEY idx_product_mediamarkt_product_id (product_id),\n"
            . "CONSTRAINT fk_product_mediamarkt_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::$schemaEnsured = true;
    }

    public function allForProduct($productId): array
    {
        $rows = $this->database->fetchAll(
            'SELECT parameter_id, value FROM product_mediamarkt_parameters WHERE product_id = :product_id ORDER BY parameter_id',
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
                $this->database->delete('product_mediamarkt_parameters', 'product_id = :product_id', array('product_id' => $productId));

                foreach ($values as $parameterId => $value) {
                    $this->database->insert('product_mediamarkt_parameters', array(
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

    public function usedParameterFieldOptions(): array
    {
        $rows = $this->database->fetchAll(
            'SELECT DISTINCT pep.parameter_id, c.mediamarkt_category_id, c.name AS category_name'
            . ' FROM product_mediamarkt_parameters pep'
            . ' INNER JOIN products p ON p.id = pep.product_id'
            . ' INNER JOIN categories c ON c.id = p.category_id'
            . ' WHERE p.deleted_at IS NULL'
            . ' ORDER BY pep.parameter_id ASC, c.name ASC'
        );

        if ($rows === array()) {
            return array();
        }

        $nameMapByCategory = array();
        $categoriesByParameter = array();
        $options = array();

        foreach ($rows as $row) {
            $parameterId = isset($row['parameter_id']) ? (string) $row['parameter_id'] : '';
            $categoryMediaMarktId = isset($row['mediamarkt_category_id']) ? (string) $row['mediamarkt_category_id'] : '';
            $categoryName = trim((string) ($row['category_name'] ?? ''));

            if ($parameterId === '') {
                continue;
            }

            if (!isset($categoriesByParameter[$parameterId])) {
                $categoriesByParameter[$parameterId] = array();
            }

            if ($categoryName !== '' && !in_array($categoryName, $categoriesByParameter[$parameterId], true)) {
                $categoriesByParameter[$parameterId][] = $categoryName;
            }

            if ($categoryMediaMarktId !== '' && !isset($nameMapByCategory[$categoryMediaMarktId])) {
                $nameMapByCategory[$categoryMediaMarktId] = $this->parameterNamesForCategory($categoryMediaMarktId);
            }
        }

        foreach ($rows as $row) {
            $parameterId = isset($row['parameter_id']) ? (string) $row['parameter_id'] : '';
            $categoryMediaMarktId = isset($row['mediamarkt_category_id']) ? (string) $row['mediamarkt_category_id'] : '';

            if ($parameterId === '' || isset($options['product.mediamarkt_parameter.' . $parameterId])) {
                continue;
            }

            $parameterName = isset($nameMapByCategory[$categoryMediaMarktId][$parameterId])
                ? $nameMapByCategory[$categoryMediaMarktId][$parameterId]
                : $parameterId;

            $label = 'MediaMarkt: ' . $parameterName . ' [' . $parameterId . ']';
            if (!empty($categoriesByParameter[$parameterId])) {
                $label .= ' | Kategorie: ' . implode(', ', $categoriesByParameter[$parameterId]);
            }

            $options['product.mediamarkt_parameter.' . $parameterId] = $label;
        }

        return $options;
    }

    private function parameterNamesForCategory(string $categoryMediaMarktId): array
    {
        if ($categoryMediaMarktId === '') {
            return array();
        }

        try {
            if ($this->mediamarkt === null) {
                $this->mediamarkt = new MediaMarktService();
            }

            $definitions = $this->mediamarkt->categoryAttributes($categoryMediaMarktId);
        } catch (Throwable $exception) {
            return array();
        }

        $map = array();
        foreach ($definitions as $definition) {
            if (!is_array($definition) || empty($definition['id'])) {
                continue;
            }

            $map[(string) $definition['id']] = isset($definition['name']) ? (string) $definition['name'] : (string) $definition['id'];
        }

        return $map;
    }
}
