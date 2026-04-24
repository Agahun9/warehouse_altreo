<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;
use App\Services\EmpikService;
use App\Services\ProductChangeAuditService;
use Throwable;

class ProductEmpikParameterRepository
{
    /** @var bool */
    private static $schemaEnsured = false;

    /** @var Database */
    private $database;

    /** @var EmpikService|null */
    private $empik;

    public function __construct(Database $database)
    {
        $this->database = $database;
        $this->empik = null;
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
            "CREATE TABLE IF NOT EXISTS product_empik_parameters (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "product_id INT UNSIGNED NOT NULL,\n"
            . "parameter_id VARCHAR(190) NOT NULL,\n"
            . "value LONGTEXT NOT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_product_empik_param (product_id, parameter_id),\n"
            . "KEY idx_product_empik_product_id (product_id),\n"
            . "CONSTRAINT fk_product_empik_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::$schemaEnsured = true;
    }

    public function allForProduct($productId): array
    {
        $rows = $this->database->fetchAll(
            'SELECT parameter_id, value FROM product_empik_parameters WHERE product_id = :product_id ORDER BY parameter_id',
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
                $this->database->delete('product_empik_parameters', 'product_id = :product_id', array('product_id' => $productId));

                foreach ($values as $parameterId => $value) {
                    $this->database->insert('product_empik_parameters', array(
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
            'SELECT DISTINCT pep.parameter_id, c.empik_category_id'
            . ' FROM product_empik_parameters pep'
            . ' INNER JOIN products p ON p.id = pep.product_id'
            . ' INNER JOIN categories c ON c.id = p.category_id'
            . ' WHERE p.deleted_at IS NULL'
            . ' ORDER BY pep.parameter_id ASC'
        );

        if ($rows === array()) {
            return array();
        }

        $nameMapByCategory = array();
        $options = array();

        foreach ($rows as $row) {
            $parameterId = isset($row['parameter_id']) ? (string) $row['parameter_id'] : '';
            $categoryEmpikId = isset($row['empik_category_id']) ? (string) $row['empik_category_id'] : '';

            if ($parameterId === '' || isset($options['product.empik_parameter.' . $parameterId])) {
                continue;
            }

            if ($categoryEmpikId !== '' && !isset($nameMapByCategory[$categoryEmpikId])) {
                $nameMapByCategory[$categoryEmpikId] = $this->parameterNamesForCategory($categoryEmpikId);
            }

            $parameterName = isset($nameMapByCategory[$categoryEmpikId][$parameterId])
                ? $nameMapByCategory[$categoryEmpikId][$parameterId]
                : $parameterId;

            $options['product.empik_parameter.' . $parameterId] = 'Empik: ' . $parameterName;
        }

        return $options;
    }

    private function parameterNamesForCategory(string $categoryEmpikId): array
    {
        if ($categoryEmpikId === '') {
            return array();
        }

        try {
            if ($this->empik === null) {
                $this->empik = new EmpikService();
            }

            $definitions = $this->empik->categoryAttributes($categoryEmpikId);
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
