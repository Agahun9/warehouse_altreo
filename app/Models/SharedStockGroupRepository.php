<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;
use App\Services\ProductChangeAuditService;

class SharedStockGroupRepository
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
            "CREATE TABLE IF NOT EXISTS shared_stock_groups (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "quantity INT UNSIGNED NOT NULL DEFAULT 0,\n"
            . "localization VARCHAR(120) DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::$schemaEnsured = true;
    }

    public function attachToRows(array $rows): array
    {
        if ($rows === array()) {
            return $rows;
        }

        $groupIds = array();
        foreach ($rows as $row) {
            $groupId = isset($row['shared_stock_group_id']) ? (int) $row['shared_stock_group_id'] : 0;
            if ($groupId > 0) {
                $groupIds[] = $groupId;
            }
        }

        $groupIds = array_values(array_unique($groupIds));
        if ($groupIds === array()) {
            foreach ($rows as $index => $row) {
                $rows[$index]['shared_stock_enabled'] = false;
                $rows[$index]['shared_stock_group_members'] = array();
            }

            return $rows;
        }

        $placeholders = array();
        $params = array();
        foreach ($groupIds as $index => $groupId) {
            $key = 'group_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $groupId;
        }

        $groups = $this->database->fetchAll(
            'SELECT * FROM shared_stock_groups WHERE id IN (' . implode(', ', $placeholders) . ')',
            $params
        );

        $groupMap = array();
        foreach ($groups as $group) {
            $groupMap[(int) $group['id']] = $group;
        }

        $members = $this->database->fetchAll(
            'SELECT id, shared_stock_group_id, sku, product_name'
            . ' FROM products'
            . ' WHERE deleted_at IS NULL AND shared_stock_group_id IN (' . implode(', ', $placeholders) . ')'
            . ' ORDER BY product_name ASC, id ASC',
            $params
        );

        $membersByGroup = array();
        foreach ($members as $member) {
            $groupId = isset($member['shared_stock_group_id']) ? (int) $member['shared_stock_group_id'] : 0;
            if ($groupId <= 0) {
                continue;
            }

            if (!isset($membersByGroup[$groupId])) {
                $membersByGroup[$groupId] = array();
            }

            $membersByGroup[$groupId][] = array(
                'id' => isset($member['id']) ? (int) $member['id'] : 0,
                'sku' => isset($member['sku']) ? (string) $member['sku'] : '',
                'product_name' => isset($member['product_name']) ? (string) $member['product_name'] : '',
            );
        }

        foreach ($rows as $index => $row) {
            $groupId = isset($row['shared_stock_group_id']) ? (int) $row['shared_stock_group_id'] : 0;
            if ($groupId <= 0 || !isset($groupMap[$groupId])) {
                $rows[$index]['shared_stock_enabled'] = false;
                $rows[$index]['shared_stock_group_members'] = array();
                continue;
            }

            $group = $groupMap[$groupId];
            $rows[$index]['quantity'] = isset($group['quantity']) ? (int) $group['quantity'] : (int) $row['quantity'];
            $rows[$index]['localization'] = isset($group['localization']) ? $group['localization'] : $row['localization'];
            $rows[$index]['shared_stock_enabled'] = true;
            $rows[$index]['shared_stock_group_quantity'] = isset($group['quantity']) ? (int) $group['quantity'] : 0;
            $rows[$index]['shared_stock_group_localization'] = isset($group['localization']) ? $group['localization'] : null;
            $rows[$index]['shared_stock_group_members'] = isset($membersByGroup[$groupId]) ? $membersByGroup[$groupId] : array();
        }

        return $rows;
    }

    public function membersForProduct(int $productId): array
    {
        $product = $this->database->fetch(
            'SELECT id, shared_stock_group_id FROM products WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            array('id' => $productId)
        );

        if (!$product || empty($product['shared_stock_group_id'])) {
            return array();
        }

        return $this->database->fetchAll(
            'SELECT id, sku, product_name'
            . ' FROM products'
            . ' WHERE deleted_at IS NULL AND shared_stock_group_id = :group_id AND id <> :product_id'
            . ' ORDER BY product_name ASC, id ASC',
            array(
                'group_id' => (int) $product['shared_stock_group_id'],
                'product_id' => $productId,
            )
        );
    }

    public function updateStockValuesForProduct(int $productId, int $quantity, ?string $localization): void
    {
        $quantity = max(0, $quantity);
        $localization = $this->normalizeNullableString($localization);
        $audit = ProductChangeAuditService::instance($this->database);

        $product = $this->database->fetch(
            'SELECT id, shared_stock_group_id FROM products WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            array('id' => $productId)
        );

        if (!$product) {
            return;
        }

        $groupId = isset($product['shared_stock_group_id']) ? (int) $product['shared_stock_group_id'] : 0;
        $affectedIds = $groupId > 0 ? $this->memberIdsForGroup($groupId) : array($productId);
        $affectedIds = $audit->expandWithDerivedDependents($affectedIds);
        $audit->rememberBefore($affectedIds);

        if ($groupId > 0) {
            $this->updateGroup($groupId, $quantity, $localization);
            $audit->rememberAfter($affectedIds, 'update');
            return;
        }

        $this->database->update(
            'products',
            array(
                'quantity' => $quantity,
                'localization' => $localization,
            ),
            'id = :id',
            array('id' => $productId)
        );
        $audit->rememberAfter($affectedIds, 'update');
    }

    public function updateStockValuesForProductSilently(int $productId, int $quantity, ?string $localization): void
    {
        $quantity = max(0, $quantity);
        $localization = $this->normalizeNullableString($localization);

        $product = $this->database->fetch(
            'SELECT id, shared_stock_group_id FROM products WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            array('id' => $productId)
        );

        if (!$product) {
            return;
        }

        $groupId = isset($product['shared_stock_group_id']) ? (int) $product['shared_stock_group_id'] : 0;
        if ($groupId > 0) {
            $this->updateGroup($groupId, $quantity, $localization);
            return;
        }

        $this->database->update(
            'products',
            array(
                'quantity' => $quantity,
                'localization' => $localization,
            ),
            'id = :id',
            array('id' => $productId)
        );
    }

    public function syncProductRelations(int $productId, array $relatedProductIds, int $quantity, ?string $localization): void
    {
        $relatedProductIds = array_values(array_unique(array_filter(array_map('intval', $relatedProductIds))));
        $relatedProductIds = array_values(array_filter($relatedProductIds, function (int $id) use ($productId): bool {
            return $id > 0 && $id !== $productId;
        }));

        $quantity = max(0, $quantity);
        $localization = $this->normalizeNullableString($localization);
        $audit = ProductChangeAuditService::instance($this->database);
        $affectedIds = $this->affectedProductIdsForRelationSync($productId, $relatedProductIds);
        $audit->rememberBefore($affectedIds);

        $this->database->transaction(function () use ($productId, $relatedProductIds, $quantity, $localization) {
            $allIds = array_merge(array($productId), $relatedProductIds);
            $products = $this->fetchProductsByIds($allIds);
            if (!isset($products[$productId])) {
                return;
            }

            $currentGroupId = isset($products[$productId]['shared_stock_group_id']) ? (int) $products[$productId]['shared_stock_group_id'] : 0;
            $targetGroupId = 0;
            if (count($allIds) > 1) {
                $targetGroupId = $this->resolveTargetGroupId($products, $currentGroupId, $quantity, $localization);
            }

            $previousMemberIds = $currentGroupId > 0 ? $this->memberIdsForGroup($currentGroupId) : array();
            $detachedFromCurrentGroup = array_values(array_diff($previousMemberIds, $allIds));

            if (count($allIds) <= 1) {
                $this->database->update(
                    'products',
                    array(
                        'shared_stock_group_id' => null,
                        'quantity' => $quantity,
                        'localization' => $localization,
                    ),
                    'id = :id',
                    array('id' => $productId)
                );
            } else {
                if ($targetGroupId <= 0) {
                    $targetGroupId = $this->createGroup($quantity, $localization);
                } else {
                    $this->updateGroup($targetGroupId, $quantity, $localization);
                }

                foreach ($allIds as $id) {
                    $this->database->update(
                        'products',
                        array('shared_stock_group_id' => $targetGroupId),
                        'id = :id',
                        array('id' => $id)
                    );
                }
            }

            foreach ($detachedFromCurrentGroup as $detachedId) {
                $this->database->update(
                    'products',
                    array('shared_stock_group_id' => null),
                    'id = :id',
                    array('id' => $detachedId)
                );
            }

            $impactedGroupIds = array();
            foreach ($products as $product) {
                $groupId = isset($product['shared_stock_group_id']) ? (int) $product['shared_stock_group_id'] : 0;
                if ($groupId > 0) {
                    $impactedGroupIds[] = $groupId;
                }
            }
            if ($targetGroupId > 0) {
                $impactedGroupIds[] = $targetGroupId;
            }
            if ($currentGroupId > 0) {
                $impactedGroupIds[] = $currentGroupId;
            }

            $this->cleanupGroups(array_values(array_unique($impactedGroupIds)));
        });

        $audit->rememberAfter($affectedIds, 'update');
    }

    public function detachProduct(int $productId): void
    {
        $audit = ProductChangeAuditService::instance($this->database);
        $affectedIds = $this->affectedProductIdsForDetach(array($productId));
        $audit->rememberBefore($affectedIds);

        $this->database->transaction(function () use ($productId) {
            $this->detachProductInternal($productId);
        });

        $audit->rememberAfter($affectedIds, 'update');
    }

    public function detachProducts(array $productIds): void
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($productIds === array()) {
            return;
        }
        $audit = ProductChangeAuditService::instance($this->database);
        $affectedIds = $this->affectedProductIdsForDetach($productIds);
        $audit->rememberBefore($affectedIds);

        $this->database->transaction(function () use ($productIds) {
            foreach ($productIds as $productId) {
                $this->detachProductInternal($productId);
            }
        });

        $audit->rememberAfter($affectedIds, 'update');
    }

    public function detachProductsForDeletion(array $productIds): void
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($productIds === array()) {
            return;
        }

        $audit = ProductChangeAuditService::instance($this->database);
        $audit->rememberBefore($productIds);

        $this->database->transaction(function () use ($productIds) {
            $groupIds = array();

            foreach ($productIds as $productId) {
                $product = $this->database->fetch(
                    'SELECT id, shared_stock_group_id FROM products WHERE id = :id LIMIT 1',
                    array('id' => $productId)
                );

                if (!$product) {
                    continue;
                }

                $groupId = isset($product['shared_stock_group_id']) ? (int) $product['shared_stock_group_id'] : 0;
                if ($groupId > 0) {
                    $groupIds[] = $groupId;
                }

                $this->database->update(
                    'products',
                    array('shared_stock_group_id' => null),
                    'id = :id',
                    array('id' => $productId)
                );
            }

            $this->cleanupEmptyGroups(array_values(array_unique($groupIds)));
        });

        $audit->rememberAfter($productIds, 'update');
    }

    public function syncProductsWithinSharedGroup(array $productIds): void
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($productIds === array()) {
            return;
        }

        $products = $this->fetchProductsByIds($productIds);
        if ($products === array()) {
            return;
        }

        $orderedIds = array();
        foreach ($productIds as $productId) {
            if (isset($products[$productId])) {
                $orderedIds[] = $productId;
            }
        }

        if ($orderedIds === array()) {
            return;
        }

        $ownerProductId = (int) $orderedIds[0];
        $ownerProduct = $products[$ownerProductId];
        $quantity = isset($ownerProduct['quantity']) ? (int) $ownerProduct['quantity'] : 0;
        $localization = array_key_exists('localization', $ownerProduct) ? $ownerProduct['localization'] : null;

        if (count($orderedIds) <= 1) {
            $this->syncProductRelations($ownerProductId, array(), $quantity, $localization);
            return;
        }

        $relatedProductIds = array_slice($orderedIds, 1);
        $this->syncProductRelations($ownerProductId, $relatedProductIds, $quantity, $localization);
    }

    private function detachProductInternal(int $productId): void
    {
        $product = $this->database->fetch(
            'SELECT id, quantity, localization, shared_stock_group_id FROM products WHERE id = :id LIMIT 1',
            array('id' => $productId)
        );

        if (!$product) {
            return;
        }

        $groupId = isset($product['shared_stock_group_id']) ? (int) $product['shared_stock_group_id'] : 0;
        if ($groupId <= 0) {
            return;
        }

        $group = $this->database->fetch(
            'SELECT quantity, localization FROM shared_stock_groups WHERE id = :id LIMIT 1',
            array('id' => $groupId)
        );

        $quantity = $group && isset($group['quantity']) ? (int) $group['quantity'] : (int) $product['quantity'];
        $localization = $group && array_key_exists('localization', $group) ? $group['localization'] : $product['localization'];

        $this->database->update(
            'products',
            array(
                'shared_stock_group_id' => null,
                'quantity' => $quantity,
                'localization' => $localization,
            ),
            'id = :id',
            array('id' => $productId)
        );

        $this->cleanupGroups(array($groupId));
    }

    private function fetchProductsByIds(array $ids): array
    {
        if ($ids === array()) {
            return array();
        }

        $placeholders = array();
        $params = array();
        foreach ($ids as $index => $id) {
            $key = 'id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int) $id;
        }

        $rows = $this->database->fetchAll(
            'SELECT id, quantity, localization, shared_stock_group_id'
            . ' FROM products'
            . ' WHERE deleted_at IS NULL AND id IN (' . implode(', ', $placeholders) . ')',
            $params
        );

        $result = array();
        foreach ($rows as $row) {
            $result[(int) $row['id']] = $row;
        }

        return $result;
    }

    private function resolveTargetGroupId(array $products, int $currentGroupId, int $quantity, ?string $localization): int
    {
        if ($currentGroupId > 0) {
            return $currentGroupId;
        }

        foreach ($products as $product) {
            $groupId = isset($product['shared_stock_group_id']) ? (int) $product['shared_stock_group_id'] : 0;
            if ($groupId > 0) {
                return $groupId;
            }
        }

        return $this->createGroup($quantity, $localization);
    }

    private function createGroup(int $quantity, ?string $localization): int
    {
        return (int) $this->database->insert('shared_stock_groups', array(
            'quantity' => max(0, $quantity),
            'localization' => $this->normalizeNullableString($localization),
        ));
    }

    private function updateGroup(int $groupId, int $quantity, ?string $localization): void
    {
        $this->database->update(
            'shared_stock_groups',
            array(
                'quantity' => max(0, $quantity),
                'localization' => $this->normalizeNullableString($localization),
            ),
            'id = :id',
            array('id' => $groupId)
        );
    }

    private function memberIdsForGroup(int $groupId): array
    {
        if ($groupId <= 0) {
            return array();
        }

        $rows = $this->database->fetchAll(
            'SELECT id FROM products WHERE deleted_at IS NULL AND shared_stock_group_id = :group_id',
            array('group_id' => $groupId)
        );

        return array_map('intval', array_column($rows, 'id'));
    }

    private function cleanupGroups(array $groupIds): void
    {
        foreach ($groupIds as $groupId) {
            $groupId = (int) $groupId;
            if ($groupId <= 0) {
                continue;
            }

            $members = $this->memberIdsForGroup($groupId);
            if (count($members) > 1) {
                continue;
            }

            if (count($members) === 1) {
                $group = $this->database->fetch(
                    'SELECT quantity, localization FROM shared_stock_groups WHERE id = :id LIMIT 1',
                    array('id' => $groupId)
                );

                if ($group) {
                    $this->database->update(
                        'products',
                        array(
                            'shared_stock_group_id' => null,
                            'quantity' => isset($group['quantity']) ? (int) $group['quantity'] : 0,
                            'localization' => array_key_exists('localization', $group) ? $group['localization'] : null,
                        ),
                        'id = :id',
                        array('id' => (int) $members[0])
                    );
                }
            }

            $this->database->delete('shared_stock_groups', 'id = :id', array('id' => $groupId));
        }
    }

    private function cleanupEmptyGroups(array $groupIds): void
    {
        foreach ($groupIds as $groupId) {
            $groupId = (int) $groupId;
            if ($groupId <= 0) {
                continue;
            }

            if ($this->memberIdsForGroup($groupId) !== array()) {
                continue;
            }

            $this->database->delete('shared_stock_groups', 'id = :id', array('id' => $groupId));
        }
    }

    private function normalizeNullableString(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }

    private function affectedProductIdsForRelationSync(int $productId, array $relatedProductIds): array
    {
        $candidateIds = array_merge(array($productId), $relatedProductIds);
        $products = $this->fetchProductsByIds($candidateIds);
        $affectedIds = array_keys($products);

        foreach ($products as $product) {
            $groupId = isset($product['shared_stock_group_id']) ? (int) $product['shared_stock_group_id'] : 0;
            if ($groupId > 0) {
                $affectedIds = array_merge($affectedIds, $this->memberIdsForGroup($groupId));
            }
        }

        return ProductChangeAuditService::instance($this->database)->expandWithDerivedDependents($affectedIds);
    }

    private function affectedProductIdsForDetach(array $productIds): array
    {
        $products = $this->fetchProductsByIds($productIds);
        $affectedIds = array_keys($products);

        foreach ($products as $product) {
            $groupId = isset($product['shared_stock_group_id']) ? (int) $product['shared_stock_group_id'] : 0;
            if ($groupId > 0) {
                $affectedIds = array_merge($affectedIds, $this->memberIdsForGroup($groupId));
            }
        }

        return ProductChangeAuditService::instance($this->database)->expandWithDerivedDependents($affectedIds);
    }
}
