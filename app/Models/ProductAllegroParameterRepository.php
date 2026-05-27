<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;
use App\Services\AllegroService;
use App\Services\ProductChangeAuditService;
use Throwable;

class ProductAllegroParameterRepository
{
    public const COMPATIBILITY_LIST_KEY = '__compatibility_list__';

    /** @var bool */
    private static $schemaEnsured = false;

    /** @var Database */
    private $database;

    /** @var AllegroService|null */
    private $allegro;

    public function __construct(Database $database)
    {
        $this->database = $database;
        $this->allegro = null;
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
            "CREATE TABLE IF NOT EXISTS product_allegro_parameters (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "product_id INT UNSIGNED NOT NULL,\n"
            . "parameter_id VARCHAR(64) NOT NULL,\n"
            . "value LONGTEXT NOT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_product_allegro_param (product_id, parameter_id),\n"
            . "KEY idx_product_allegro_product_id (product_id),\n"
            . "CONSTRAINT fk_product_allegro_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::$schemaEnsured = true;
    }

    public function allForProduct($productId): array
    {
        $rows = $this->database->fetchAll(
            'SELECT parameter_id, value FROM product_allegro_parameters WHERE product_id = :product_id AND parameter_id <> :compatibility_key ORDER BY parameter_id',
            array(
                'product_id' => (int) $productId,
                'compatibility_key' => self::COMPATIBILITY_LIST_KEY,
            )
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

        app_log("Rozpoczynam replaceForProduct dla produktu ID: $productId");

        try {
            $this->database->transaction(function () use ($productId, $values) {
                app_log("W transakcji - usuwam parametry dla produktu ID: $productId");
                $this->database->delete(
                    'product_allegro_parameters',
                    'product_id = :product_id AND parameter_id <> :compatibility_key',
                    array(
                        'product_id' => $productId,
                        'compatibility_key' => self::COMPATIBILITY_LIST_KEY,
                    )
                );

                foreach ($values as $parameterId => $value) {
                    app_log("Dodaje parametr $parameterId dla produktu $productId");
                    $this->database->insert('product_allegro_parameters', array(
                        'product_id' => $productId,
                        'parameter_id' => (string) $parameterId,
                        'value' => json_encode($value),
                    ));
                }

                return true;
            });

            app_log("Transakcja replaceForProduct zakonczona sukcesem dla produktu ID: $productId");
            $audit->rememberAfter(array($productId), 'update');
        } catch (Throwable $e) {
            app_log("Blad w transakcji replaceForProduct dla produktu ID $productId: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    public function usedParameterFieldOptions(): array
    {
        $rows = $this->database->fetchAll(
            'SELECT DISTINCT pap.parameter_id, c.allegro_category_id, c.name AS category_name'
            . ' FROM product_allegro_parameters pap'
            . ' INNER JOIN products p ON p.id = pap.product_id'
            . ' INNER JOIN categories c ON c.id = p.category_id'
            . ' WHERE p.deleted_at IS NULL'
            . ' AND pap.parameter_id <> :compatibility_key'
            . ' ORDER BY pap.parameter_id ASC, c.name ASC'
            ,
            array('compatibility_key' => self::COMPATIBILITY_LIST_KEY)
        );

        if ($rows === array()) {
            return array();
        }

        $nameMapByCategory = array();
        $categoriesByParameter = array();
        $options = array();

        foreach ($rows as $row) {
            $parameterId = isset($row['parameter_id']) ? (string) $row['parameter_id'] : '';
            $categoryAllegroId = isset($row['allegro_category_id']) ? (string) $row['allegro_category_id'] : '';
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

            if ($categoryAllegroId !== '' && !isset($nameMapByCategory[$categoryAllegroId])) {
                $nameMapByCategory[$categoryAllegroId] = $this->parameterNamesForCategory($categoryAllegroId);
            }
        }

        foreach ($rows as $row) {
            $parameterId = isset($row['parameter_id']) ? (string) $row['parameter_id'] : '';
            $categoryAllegroId = isset($row['allegro_category_id']) ? (string) $row['allegro_category_id'] : '';

            if ($parameterId === '' || isset($options['product.allegro_parameter.' . $parameterId])) {
                continue;
            }

            $parameterName = isset($nameMapByCategory[$categoryAllegroId][$parameterId])
                ? $nameMapByCategory[$categoryAllegroId][$parameterId]
                : $parameterId;

            $label = 'Allegro: ' . $parameterName . ' [' . $parameterId . ']';
            if (!empty($categoriesByParameter[$parameterId])) {
                $label .= ' | Kategorie: ' . implode(', ', $categoriesByParameter[$parameterId]);
            }

            $options['product.allegro_parameter.' . $parameterId] = $label;
        }

        return $options;
    }

    public function compatibilityListForProduct($productId): array
    {
        $row = $this->database->fetch(
            'SELECT value FROM product_allegro_parameters WHERE product_id = :product_id AND parameter_id = :compatibility_key LIMIT 1',
            array(
                'product_id' => (int) $productId,
                'compatibility_key' => self::COMPATIBILITY_LIST_KEY,
            )
        );

        if (!$row || !isset($row['value'])) {
            return array();
        }

        $decoded = json_decode((string) $row['value'], true);
        return is_array($decoded) ? $decoded : array();
    }

    public function replaceCompatibilityListForProduct($productId, array $compatibilityList): void
    {
        $productId = (int) $productId;

        $this->database->delete(
            'product_allegro_parameters',
            'product_id = :product_id AND parameter_id = :compatibility_key',
            array(
                'product_id' => $productId,
                'compatibility_key' => self::COMPATIBILITY_LIST_KEY,
            )
        );

        if ($compatibilityList === array()) {
            return;
        }

        $this->database->insert('product_allegro_parameters', array(
            'product_id' => $productId,
            'parameter_id' => self::COMPATIBILITY_LIST_KEY,
            'value' => json_encode($compatibilityList),
        ));
    }

    private function parameterNamesForCategory(string $categoryAllegroId): array
    {
        if ($categoryAllegroId === '') {
            return array();
        }

        try {
            if ($this->allegro === null) {
                $this->allegro = new AllegroService();
            }

            $definitions = $this->allegro->categoryParameters($categoryAllegroId);
        } catch (\Throwable $exception) {
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
