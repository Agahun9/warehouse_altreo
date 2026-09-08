<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;

class ComputerProductCategoryRepository
{
    public const CATEGORIES_TABLE = 'computer_product_categories';
    public const ASSIGNMENTS_TABLE = 'computer_product_category_assignments';

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
            "CREATE TABLE IF NOT EXISTS " . self::CATEGORIES_TABLE . " (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "user_id INT UNSIGNED NOT NULL,\n"
            . "name VARCHAR(120) NOT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_computer_product_categories_user_name (user_id, name),\n"
            . "KEY idx_computer_product_categories_user (user_id, id)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS " . self::ASSIGNMENTS_TABLE . " (\n"
            . "category_id INT UNSIGNED NOT NULL,\n"
            . "product_id INT UNSIGNED NOT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (category_id, product_id),\n"
            . "KEY idx_computer_product_category_product (product_id, category_id)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::$schemaEnsured = true;
    }

    public function allForUser(int $userId): array
    {
        return $this->database->fetchAll(
            'SELECT categories.*, COUNT(DISTINCT products.id) AS product_count'
            . ' FROM ' . self::CATEGORIES_TABLE . ' categories'
            . ' LEFT JOIN ' . self::ASSIGNMENTS_TABLE . ' assignments ON assignments.category_id = categories.id'
            . ' LEFT JOIN pr_products_altreo products ON products.id = assignments.product_id'
            . ' WHERE categories.user_id = :user_id'
            . ' GROUP BY categories.id, categories.user_id, categories.name, categories.created_at, categories.updated_at'
            . ' ORDER BY categories.name ASC, categories.id ASC',
            array('user_id' => $userId)
        );
    }

    public function findForUser(int $categoryId, int $userId)
    {
        if ($categoryId <= 0 || $userId <= 0) {
            return false;
        }

        return $this->database->fetch(
            'SELECT * FROM ' . self::CATEGORIES_TABLE
            . ' WHERE id = :id AND user_id = :user_id LIMIT 1',
            array('id' => $categoryId, 'user_id' => $userId)
        );
    }

    public function existsByNameForUser(string $name, int $userId, int $ignoreId = 0): bool
    {
        $sql = 'SELECT COUNT(*) FROM ' . self::CATEGORIES_TABLE
            . ' WHERE user_id = :user_id AND name = :name';
        $params = array('user_id' => $userId, 'name' => $name);

        if ($ignoreId > 0) {
            $sql .= ' AND id <> :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }

        return (int) $this->database->fetchColumn($sql, $params) > 0;
    }

    public function createForUser(string $name, int $userId): int
    {
        return (int) $this->database->insert(self::CATEGORIES_TABLE, array(
            'user_id' => $userId,
            'name' => $name,
        ));
    }

    public function renameForUser(int $categoryId, string $name, int $userId): bool
    {
        return $this->database->update(
            self::CATEGORIES_TABLE,
            array('name' => $name),
            'id = :id AND user_id = :user_id',
            array('id' => $categoryId, 'user_id' => $userId)
        ) > 0;
    }

    public function deleteForUser(int $categoryId, int $userId): bool
    {
        if (!$this->findForUser($categoryId, $userId)) {
            return false;
        }

        $this->database->transaction(function () use ($categoryId, $userId): void {
            $this->database->delete(
                self::ASSIGNMENTS_TABLE,
                'category_id = :category_id',
                array('category_id' => $categoryId)
            );
            $this->database->delete(
                self::CATEGORIES_TABLE,
                'id = :id AND user_id = :user_id',
                array('id' => $categoryId, 'user_id' => $userId)
            );
        });

        return true;
    }

    public function addProductsForUser(int $categoryId, array $productIds, int $userId): int
    {
        if (!$this->findForUser($categoryId, $userId)) {
            return 0;
        }

        $affected = 0;
        foreach (array_chunk($this->normalizeIds($productIds), 1000) as $chunk) {
            if ($chunk === array()) {
                continue;
            }

            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $params = array_merge(array($categoryId), $chunk);
            $affected += $this->database->query(
                'INSERT IGNORE INTO ' . self::ASSIGNMENTS_TABLE . ' (category_id, product_id)'
                . ' SELECT ?, products.id FROM pr_products_altreo products'
                . ' WHERE products.id IN (' . $placeholders . ')',
                $params
            )->rowCount();
        }

        return $affected;
    }

    public function removeProductsForUser(int $categoryId, array $productIds, int $userId): int
    {
        if (!$this->findForUser($categoryId, $userId)) {
            return 0;
        }

        $affected = 0;
        foreach (array_chunk($this->normalizeIds($productIds), 1000) as $chunk) {
            if ($chunk === array()) {
                continue;
            }

            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $affected += $this->database->query(
                'DELETE FROM ' . self::ASSIGNMENTS_TABLE
                . ' WHERE category_id = ? AND product_id IN (' . $placeholders . ')',
                array_merge(array($categoryId), $chunk)
            )->rowCount();
        }

        return $affected;
    }

    public function categoriesForProducts(int $userId, array $productIds): array
    {
        $result = array();
        foreach ($this->normalizeIds($productIds) as $productId) {
            $result[$productId] = array();
        }

        $productIds = array_keys($result);
        if ($userId <= 0 || $productIds === array()) {
            return $result;
        }

        foreach (array_chunk($productIds, 1000) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $rows = $this->database->query(
                'SELECT assignments.product_id, categories.id, categories.name'
                . ' FROM ' . self::ASSIGNMENTS_TABLE . ' assignments'
                . ' INNER JOIN ' . self::CATEGORIES_TABLE . ' categories ON categories.id = assignments.category_id'
                . ' WHERE categories.user_id = ? AND assignments.product_id IN (' . $placeholders . ')'
                . ' ORDER BY categories.name ASC, categories.id ASC',
                array_merge(array($userId), $chunk)
            )->fetchAll();

            foreach ($rows as $row) {
                $productId = (int) ($row['product_id'] ?? 0);
                if (isset($result[$productId])) {
                    $result[$productId][] = array(
                        'id' => (int) ($row['id'] ?? 0),
                        'name' => (string) ($row['name'] ?? ''),
                    );
                }
            }
        }

        return $result;
    }

    public function purgeProductIds(array $productIds): void
    {
        foreach (array_chunk($this->normalizeIds($productIds), 1000) as $chunk) {
            if ($chunk === array()) {
                continue;
            }

            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $this->database->query(
                'DELETE FROM ' . self::ASSIGNMENTS_TABLE . ' WHERE product_id IN (' . $placeholders . ')',
                $chunk
            );
        }
    }

    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }
}
