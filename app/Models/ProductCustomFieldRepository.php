<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;
use App\Services\ProductChangeAuditService;

class ProductCustomFieldRepository
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
            "CREATE TABLE IF NOT EXISTS product_custom_field_definitions (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "name VARCHAR(190) NOT NULL,\n"
            . "slug VARCHAR(190) NOT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_product_custom_field_slug (slug),\n"
            . "UNIQUE KEY ux_product_custom_field_name (name)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS product_custom_field_values (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "product_id INT UNSIGNED NOT NULL,\n"
            . "definition_id INT UNSIGNED NOT NULL,\n"
            . "value TEXT DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_product_custom_field_value (product_id, definition_id),\n"
            . "KEY idx_product_custom_field_product (product_id),\n"
            . "KEY idx_product_custom_field_definition (definition_id),\n"
            . "CONSTRAINT fk_product_custom_field_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,\n"
            . "CONSTRAINT fk_product_custom_field_definition FOREIGN KEY (definition_id) REFERENCES product_custom_field_definitions(id) ON DELETE CASCADE\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->ensureIndex(
            'product_custom_field_values',
            'idx_product_custom_field_definition_value',
            'CREATE INDEX idx_product_custom_field_definition_value ON product_custom_field_values (definition_id, value(191), product_id)'
        );
        self::$schemaEnsured = true;
    }

    public function definitions(): array
    {
        return $this->database->fetchAll('SELECT * FROM product_custom_field_definitions ORDER BY name ASC, id ASC');
    }

    public function valuesForProduct(int $productId): array
    {
        $rows = $this->database->fetchAll(
            'SELECT d.id, d.name, d.slug, v.value'
            . ' FROM product_custom_field_definitions d'
            . ' LEFT JOIN product_custom_field_values v ON v.definition_id = d.id AND v.product_id = :product_id'
            . ' ORDER BY d.name ASC, d.id ASC',
            array('product_id' => $productId)
        );

        $result = array();
        foreach ($rows as $row) {
            $slug = isset($row['slug']) ? (string) $row['slug'] : '';
            if ($slug === '') {
                continue;
            }

            $result[$slug] = array(
                'definition_id' => isset($row['id']) ? (int) $row['id'] : 0,
                'name' => isset($row['name']) ? (string) $row['name'] : $slug,
                'slug' => $slug,
                'value' => isset($row['value']) && $row['value'] !== null ? (string) $row['value'] : '',
            );
        }

        return $result;
    }

    public function findProductIdsBySlugAndValue(string $slug, string $value): array
    {
        $slug = trim($slug);
        $value = trim($value);

        if ($slug === '' || $value === '') {
            return array();
        }

        $rows = $this->database->fetchAll(
            'SELECT v.product_id'
            . ' FROM product_custom_field_values v'
            . ' INNER JOIN product_custom_field_definitions d ON d.id = v.definition_id'
            . ' INNER JOIN products p ON p.id = v.product_id'
            . ' WHERE d.slug = :slug AND v.value = :value AND p.deleted_at IS NULL'
            . ' ORDER BY v.product_id ASC',
            array(
                'slug' => $slug,
                'value' => $value,
            )
        );

        return array_values(array_unique(array_map('intval', array_column($rows, 'product_id'))));
    }

    public function findDefinitionById(int $id)
    {
        return $this->database->fetch(
            'SELECT * FROM product_custom_field_definitions WHERE id = :id LIMIT 1',
            array('id' => $id)
        );
    }

    public function ensureDefinition(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return array();
        }

        $slug = $this->slugify($name);
        if ($slug === '') {
            return array();
        }

        $existing = $this->database->fetch(
            'SELECT * FROM product_custom_field_definitions WHERE slug = :slug LIMIT 1',
            array('slug' => $slug)
        );

        if ($existing) {
            return $existing;
        }

        $id = (int) $this->database->insert('product_custom_field_definitions', array(
            'name' => $name,
            'slug' => $slug,
        ));

        return $this->database->fetch('SELECT * FROM product_custom_field_definitions WHERE id = :id LIMIT 1', array('id' => $id));
    }

    public function replaceForProduct(int $productId, array $values): void
    {
        $audit = ProductChangeAuditService::instance($this->database);
        $audit->rememberBefore(array($productId));

        $this->database->transaction(function () use ($productId, $values) {
            $this->database->delete('product_custom_field_values', 'product_id = :product_id', array('product_id' => $productId));

            foreach ($values as $definitionId => $value) {
                $definitionId = (int) $definitionId;
                $value = trim((string) $value);
                if ($definitionId <= 0 || $value === '') {
                    continue;
                }

                $this->database->insert('product_custom_field_values', array(
                    'product_id' => $productId,
                    'definition_id' => $definitionId,
                    'value' => $value,
                ));
            }
        });

        $audit->rememberAfter(array($productId), 'update');
    }

    public function deleteDefinition(int $id): void
    {
        $this->database->delete('product_custom_field_definitions', 'id = :id', array('id' => $id));
    }

    public function attachToRows(array $rows): array
    {
        if ($rows === array()) {
            return $rows;
        }

        $productIds = array();
        foreach ($rows as $row) {
            if (isset($row['id'])) {
                $productIds[] = (int) $row['id'];
            }
        }

        $productIds = array_values(array_unique(array_filter($productIds)));
        if ($productIds === array()) {
            return $rows;
        }

        $placeholders = array();
        $params = array();
        foreach ($productIds as $index => $productId) {
            $key = 'product_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $productId;
        }

        $valueRows = $this->database->fetchAll(
            'SELECT v.product_id, v.value, d.name, d.slug'
            . ' FROM product_custom_field_values v'
            . ' INNER JOIN product_custom_field_definitions d ON d.id = v.definition_id'
            . ' WHERE v.product_id IN (' . implode(', ', $placeholders) . ')'
            . ' ORDER BY d.name ASC, d.id ASC',
            $params
        );

        $byProduct = array();
        foreach ($valueRows as $valueRow) {
            $productId = isset($valueRow['product_id']) ? (int) $valueRow['product_id'] : 0;
            $slug = isset($valueRow['slug']) ? (string) $valueRow['slug'] : '';
            if ($productId <= 0 || $slug === '') {
                continue;
            }

            if (!isset($byProduct[$productId])) {
                $byProduct[$productId] = array();
            }

            $byProduct[$productId][$slug] = isset($valueRow['value']) ? (string) $valueRow['value'] : '';
        }

        foreach ($rows as $index => $row) {
            $productId = isset($row['id']) ? (int) $row['id'] : 0;
            $rows[$index]['custom_fields'] = isset($byProduct[$productId]) ? $byProduct[$productId] : array();
        }

        return $rows;
    }

    private function slugify(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($transliterated !== false) {
            $value = $transliterated;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);
        return trim((string) $value, '_');
    }

    private function ensureIndex(string $table, string $indexName, string $sql): void
    {
        $config = Config::get('database');
        $schema = isset($config['database']) ? (string) $config['database'] : '';
        if ($schema === '') {
            return;
        }

        $exists = (int) $this->database->fetchColumn(
            'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name',
            array(
                'schema' => $schema,
                'table_name' => $table,
                'index_name' => $indexName,
            )
        ) > 0;

        if ($exists) {
            return;
        }

        $this->database->query($sql);
    }
}
