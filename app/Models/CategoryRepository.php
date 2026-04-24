<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;

class CategoryRepository
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

        $databaseName = isset($config['database']) ? (string) $config['database'] : '';

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS categories (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "name VARCHAR(190) NOT NULL,\n"
            . "slug VARCHAR(190) NOT NULL,\n"
            . "sku_prefix VARCHAR(20) NOT NULL DEFAULT 'PRD',\n"
            . "allegro_category_id VARCHAR(64) DEFAULT NULL,\n"
            . "empik_category_id VARCHAR(190) DEFAULT NULL,\n"
            . "description TEXT DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_categories_slug (slug),\n"
            . "KEY idx_categories_name (name),\n"
            . "KEY idx_categories_allegro (allegro_category_id),\n"
            . "KEY idx_categories_empik (empik_category_id)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        if ($databaseName !== '') {
            $hasPrefixColumn = (int) $this->database->fetchColumn(
                'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND COLUMN_NAME = :column',
                array('schema' => $databaseName, 'table' => 'categories', 'column' => 'sku_prefix')
            ) > 0;

            if (!$hasPrefixColumn) {
                $this->database->query("ALTER TABLE categories ADD COLUMN sku_prefix VARCHAR(20) NOT NULL DEFAULT 'PRD' AFTER slug");
            }

            $hasAllegroColumn = (int) $this->database->fetchColumn(
                'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND COLUMN_NAME = :column',
                array('schema' => $databaseName, 'table' => 'categories', 'column' => 'allegro_category_id')
            ) > 0;

            if (!$hasAllegroColumn) {
                $this->database->query("ALTER TABLE categories ADD COLUMN allegro_category_id VARCHAR(64) NULL AFTER sku_prefix");
            }

            $hasEmpikColumn = (int) $this->database->fetchColumn(
                'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND COLUMN_NAME = :column',
                array('schema' => $databaseName, 'table' => 'categories', 'column' => 'empik_category_id')
            ) > 0;

            if (!$hasEmpikColumn) {
                $this->database->query("ALTER TABLE categories ADD COLUMN empik_category_id VARCHAR(190) NULL AFTER allegro_category_id");
            }
        }

        $this->ensureDefaultCategory();
        self::$schemaEnsured = true;
    }

    public function all(): array
    {
        return $this->database->fetchAll('SELECT * FROM categories ORDER BY name ASC');
    }

    public function countAll(): int
    {
        return (int) $this->database->fetchColumn('SELECT COUNT(*) FROM categories');
    }

    public function findById($id)
    {
        return $this->database->fetch('SELECT * FROM categories WHERE id = :id LIMIT 1', array('id' => $id));
    }

    public function findBySlug($slug)
    {
        return $this->database->fetch('SELECT * FROM categories WHERE slug = :slug LIMIT 1', array('slug' => $slug));
    }

    public function nameExists(string $name, int $excludeId = 0): bool
    {
        $sql = 'SELECT COUNT(*) FROM categories WHERE name = :name';
        $params = array('name' => trim($name));

        if ($excludeId > 0) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }

        return (int) $this->database->fetchColumn($sql, $params) > 0;
    }

    public function skuPrefixExists(string $skuPrefix, int $excludeId = 0): bool
    {
        $sql = 'SELECT COUNT(*) FROM categories WHERE sku_prefix = :sku_prefix';
        $params = array('sku_prefix' => $this->normalizeSkuPrefix($skuPrefix));

        if ($excludeId > 0) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }

        return (int) $this->database->fetchColumn($sql, $params) > 0;
    }

    public function create(array $data): string
    {
        return $this->database->insert('categories', $data);
    }

    public function updateById($id, array $data): int
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->database->update('categories', $data, 'id = :id', array('id' => $id));
    }

    public function deleteById($id): int
    {
        return $this->database->delete('categories', 'id = :id', array('id' => $id));
    }

    public function productsCount($categoryId): int
    {
        return (int) $this->database->fetchColumn('SELECT COUNT(*) FROM products WHERE category_id = :category_id AND deleted_at IS NULL', array('category_id' => $categoryId));
    }

    public function ensureDefaultCategory(): int
    {
        $existing = $this->findBySlug('bez-kategorii');
        if ($existing) {
            if (empty($existing['sku_prefix'])) {
                $this->updateById((int) $existing['id'], array('sku_prefix' => 'PRD'));
            }

            return (int) $existing['id'];
        }

        return (int) $this->create(array(
            'name' => 'Bez kategorii',
            'slug' => 'bez-kategorii',
            'sku_prefix' => 'PRD',
            'allegro_category_id' => null,
            'empik_category_id' => null,
            'description' => 'Domyslna kategoria systemowa.',
        ));
    }

    public function uniqueSlug($name, $ignoreId = 0): string
    {
        $base = $this->slugify($name);
        $slug = $base;
        $suffix = 1;

        while (true) {
            $row = $this->findBySlug($slug);
            if (!$row || ((int) $row['id'] === (int) $ignoreId)) {
                return $slug;
            }

            $suffix++;
            $slug = $base . '-' . $suffix;
        }
    }

    public function normalizeSkuPrefix($prefix): string
    {
        $prefix = strtoupper(trim((string) $prefix));
        $prefix = preg_replace('/[^A-Z0-9\-]+/', '', $prefix);
        $prefix = trim((string) $prefix, '-');

        return $prefix !== '' ? $prefix : 'PRD';
    }

    public function normalizeAllegroCategoryId($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/[^A-Za-z0-9\-_]+/', '', $value);
        return $value !== '' ? $value : null;
    }

    public function normalizeEmpikCategoryId($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/[[:cntrl:]]+/u', '', $value);
        $value = trim((string) $value);

        return $value !== '' ? mb_substr($value, 0, 190, 'UTF-8') : null;
    }

    public function slugify($text): string
    {
        $text = trim((string) $text);
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($transliterated !== false) {
            $text = $transliterated;
        }

        $text = trim(strtolower($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim((string) $text, '-');

        return $text !== '' ? $text : 'kategoria';
    }
}

