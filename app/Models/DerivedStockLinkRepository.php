<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;
use App\Services\ProductChangeAuditService;

class DerivedStockLinkRepository
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
            "CREATE TABLE IF NOT EXISTS product_derived_stock_links (\n"
            . "owner_product_id INT UNSIGNED NOT NULL,\n"
            . "source_product_id INT UNSIGNED NOT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (owner_product_id, source_product_id),\n"
            . "KEY idx_derived_source_product_id (source_product_id),\n"
            . "CONSTRAINT fk_derived_owner_product FOREIGN KEY (owner_product_id) REFERENCES products(id) ON DELETE CASCADE,\n"
            . "CONSTRAINT fk_derived_source_product FOREIGN KEY (source_product_id) REFERENCES products(id) ON DELETE CASCADE\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::$schemaEnsured = true;
    }

    public function syncSourcesForProduct(int $productId, array $sourceProductIds): void
    {
        $sourceProductIds = array_values(array_unique(array_filter(array_map('intval', $sourceProductIds))));
        $sourceProductIds = array_values(array_filter($sourceProductIds, function (int $id) use ($productId): bool {
            return $id > 0 && $id !== $productId;
        }));
        $audit = ProductChangeAuditService::instance($this->database);
        $affectedIds = $audit->expandWithDerivedDependents(array($productId));
        $audit->rememberBefore($affectedIds);

        $this->database->transaction(function () use ($productId, $sourceProductIds) {
            $this->database->delete('product_derived_stock_links', 'owner_product_id = :owner_product_id', array(
                'owner_product_id' => $productId,
            ));

            if ($sourceProductIds === array()) {
                return;
            }

            $placeholders = array();
            $params = array();
            foreach ($sourceProductIds as $index => $sourceId) {
                $key = 'source_id_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $sourceId;
            }

            $validSources = $this->database->fetchAll(
                'SELECT id FROM products WHERE deleted_at IS NULL AND id IN (' . implode(', ', $placeholders) . ')',
                $params
            );

            foreach ($validSources as $sourceRow) {
                $sourceId = isset($sourceRow['id']) ? (int) $sourceRow['id'] : 0;
                if ($sourceId <= 0 || $sourceId === $productId) {
                    continue;
                }

                $this->database->insert('product_derived_stock_links', array(
                    'owner_product_id' => $productId,
                    'source_product_id' => $sourceId,
                ));
            }
        });

        $audit->rememberAfter($affectedIds, 'update');
    }

    public function sourcesForProduct(int $productId): array
    {
        return $this->database->fetchAll(
            'SELECT source.id, source.sku, source.product_name, source.category_id, categories.name AS category_name'
            . ' FROM product_derived_stock_links links'
            . ' INNER JOIN products source ON source.id = links.source_product_id AND source.deleted_at IS NULL'
            . ' LEFT JOIN categories ON categories.id = source.category_id'
            . ' WHERE links.owner_product_id = :owner_product_id'
            . ' ORDER BY source.product_name ASC, source.id ASC',
            array('owner_product_id' => $productId)
        );
    }

    public function sourceIdsForProduct(int $productId): array
    {
        $rows = $this->database->fetchAll(
            'SELECT source_product_id FROM product_derived_stock_links WHERE owner_product_id = :owner_product_id ORDER BY source_product_id ASC',
            array('owner_product_id' => $productId)
        );

        return array_map('intval', array_column($rows, 'source_product_id'));
    }

    public function syncProductsWithinCodeGroup(array $productIds): void
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds), function (int $id): bool {
            return $id > 0;
        })));

        if (count($productIds) <= 1) {
            foreach ($productIds as $productId) {
                $this->syncSourcesForProduct($productId, array());
            }
            return;
        }

        foreach ($productIds as $ownerProductId) {
            $sourceProductIds = array_values(array_filter($productIds, function (int $candidateId) use ($ownerProductId): bool {
                return $candidateId !== $ownerProductId;
            }));
            $this->syncSourcesForProduct($ownerProductId, $sourceProductIds);
        }
    }

    public function removeProduct(int $productId): void
    {
        $audit = ProductChangeAuditService::instance($this->database);
        $affectedIds = $audit->expandWithDerivedDependents(array($productId));
        $audit->rememberBefore($affectedIds);

        $this->database->delete(
            'product_derived_stock_links',
            'owner_product_id = :owner_product_id OR source_product_id = :source_product_id',
            array(
                'owner_product_id' => $productId,
                'source_product_id' => $productId,
            )
        );

        $audit->rememberAfter(array_values(array_filter($affectedIds, function (int $id) use ($productId): bool {
            return $id !== $productId;
        })), 'update');
    }

    public function attachToRows(array $rows): array
    {
        if ($rows === array()) {
            return $rows;
        }

        $ownerIds = array();
        foreach ($rows as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id > 0) {
                $ownerIds[] = $id;
            }
        }

        $ownerIds = array_values(array_unique($ownerIds));
        if ($ownerIds === array()) {
            return $rows;
        }

        $ownerPlaceholders = array();
        $ownerParams = array();
        foreach ($ownerIds as $index => $ownerId) {
            $key = 'owner_id_' . $index;
            $ownerPlaceholders[] = ':' . $key;
            $ownerParams[$key] = $ownerId;
        }

        $linkRows = $this->database->fetchAll(
            'SELECT links.owner_product_id, source.id, source.sku, source.product_name,'
            . ' COALESCE(shared_stock_groups.quantity, source.quantity) AS quantity,'
            . ' COALESCE(shared_stock_groups.localization, source.localization) AS localization'
            . ' FROM product_derived_stock_links links'
            . ' INNER JOIN products source ON source.id = links.source_product_id AND source.deleted_at IS NULL'
            . ' LEFT JOIN shared_stock_groups ON shared_stock_groups.id = source.shared_stock_group_id'
            . ' WHERE links.owner_product_id IN (' . implode(', ', $ownerPlaceholders) . ')'
            . ' ORDER BY source.product_name ASC, source.id ASC',
            $ownerParams
        );

        $sourcesByOwner = array();
        foreach ($linkRows as $linkRow) {
            $ownerId = isset($linkRow['owner_product_id']) ? (int) $linkRow['owner_product_id'] : 0;
            if ($ownerId <= 0) {
                continue;
            }

            if (!isset($sourcesByOwner[$ownerId])) {
                $sourcesByOwner[$ownerId] = array();
            }

            $sourcesByOwner[$ownerId][] = array(
                'id' => isset($linkRow['id']) ? (int) $linkRow['id'] : 0,
                'sku' => isset($linkRow['sku']) ? (string) $linkRow['sku'] : '',
                'product_name' => isset($linkRow['product_name']) ? (string) $linkRow['product_name'] : '',
                'quantity' => isset($linkRow['quantity']) ? max(0, (int) $linkRow['quantity']) : 0,
                'localization' => array_key_exists('localization', $linkRow) ? $this->normalizeNullableString($linkRow['localization']) : null,
            );
        }

        foreach ($rows as $index => $row) {
            $ownerId = isset($row['id']) ? (int) $row['id'] : 0;
            $sources = isset($sourcesByOwner[$ownerId]) ? $sourcesByOwner[$ownerId] : array();

            if ($sources === array()) {
                $rows[$index]['derived_stock_enabled'] = false;
                $rows[$index]['derived_stock_sources'] = array();
                continue;
            }

            $minQuantity = null;
            $localizations = array();
            foreach ($sources as $source) {
                $sourceQuantity = isset($source['quantity']) ? (int) $source['quantity'] : 0;
                $minQuantity = $minQuantity === null ? $sourceQuantity : min($minQuantity, $sourceQuantity);

                $sourceLocalization = isset($source['localization']) ? $this->normalizeNullableString($source['localization']) : null;
                if ($sourceLocalization !== null && !in_array($sourceLocalization, $localizations, true)) {
                    $localizations[] = $sourceLocalization;
                }
            }

            $rows[$index]['quantity'] = $minQuantity !== null ? max(0, (int) $minQuantity) : (int) $row['quantity'];
            $rows[$index]['localization'] = $localizations !== array() ? implode(' / ', $localizations) : null;
            $rows[$index]['derived_stock_enabled'] = true;
            $rows[$index]['derived_stock_source_count'] = count($sources);
            $rows[$index]['derived_stock_sources'] = $sources;
        }

        return $rows;
    }

    private function normalizeNullableString($value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }
}
