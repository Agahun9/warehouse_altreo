<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Models\ProductChangeLogRepository;
use App\Models\ProductRepository;
use App\Models\UserRepository;

class ProductChangeAuditService
{
    /** @var self[] */
    private static $instances = array();

    /** @var bool */
    private static $shutdownRegistered = false;

    /** @var Database */
    private $database;

    /** @var array<int, array<string, mixed>|null> */
    private $beforeSnapshots;

    /** @var array<int, array<string, mixed>|null> */
    private $afterSnapshots;

    /** @var array<int, string> */
    private $actionsByProduct;

    /** @var array<int, bool> */
    private $createdProducts;

    /** @var array<int, bool> */
    private $deletedProducts;

    private function __construct(Database $database)
    {
        $this->database = $database;
        $this->beforeSnapshots = array();
        $this->afterSnapshots = array();
        $this->actionsByProduct = array();
        $this->createdProducts = array();
        $this->deletedProducts = array();
    }

    public static function instance(Database $database): self
    {
        $key = spl_object_hash($database);
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new self($database);
        }

        if (!self::$shutdownRegistered) {
            self::$shutdownRegistered = true;
            register_shutdown_function(array(__CLASS__, 'flushAll'));
        }

        return self::$instances[$key];
    }

    public static function flushAll(): void
    {
        foreach (self::$instances as $instance) {
            $instance->flush();
        }
    }

    public function rememberBefore(array $productIds): void
    {
        $productIds = $this->normalizeIds($productIds);
        $productIds = $this->expandWithDerivedDependents($productIds);
        if ($productIds === array()) {
            return;
        }

        $missingIds = array();
        foreach ($productIds as $productId) {
            if (isset($this->beforeSnapshots[$productId]) || isset($this->createdProducts[$productId])) {
                continue;
            }

            $missingIds[] = $productId;
        }

        if ($missingIds === array()) {
            return;
        }

        $snapshots = $this->snapshotMap($missingIds);
        foreach ($missingIds as $productId) {
            $this->beforeSnapshots[$productId] = isset($snapshots[$productId]) ? $snapshots[$productId] : null;
            if (!isset($this->actionsByProduct[$productId])) {
                $this->actionsByProduct[$productId] = 'update';
            }
        }
    }

    public function rememberAfter(array $productIds, string $action = 'update'): void
    {
        $productIds = $this->normalizeIds($productIds);
        $productIds = $this->expandWithDerivedDependents($productIds);
        if ($productIds === array()) {
            return;
        }

        $snapshots = $this->snapshotMap($productIds);
        foreach ($productIds as $productId) {
            $this->afterSnapshots[$productId] = isset($snapshots[$productId]) ? $snapshots[$productId] : null;
            $this->actionsByProduct[$productId] = $this->mergeAction(
                isset($this->actionsByProduct[$productId]) ? $this->actionsByProduct[$productId] : 'update',
                $action
            );
        }
    }

    public function markCreated(array $productIds, string $action = 'create'): void
    {
        $productIds = $this->normalizeIds($productIds);
        if ($productIds === array()) {
            return;
        }

        foreach ($productIds as $productId) {
            $this->beforeSnapshots[$productId] = null;
            $this->createdProducts[$productId] = true;
            $this->actionsByProduct[$productId] = $this->mergeAction(
                isset($this->actionsByProduct[$productId]) ? $this->actionsByProduct[$productId] : 'update',
                $action
            );
        }

        $this->rememberAfter($productIds, $action);
    }

    public function markDeleted(array $productIds): void
    {
        $productIds = $this->normalizeIds($productIds);
        if ($productIds === array()) {
            return;
        }

        foreach ($productIds as $productId) {
            $this->deletedProducts[$productId] = true;
            $this->afterSnapshots[$productId] = null;
            $this->actionsByProduct[$productId] = 'delete';
        }
    }

    public function expandWithDerivedDependents(array $productIds): array
    {
        $queue = $this->normalizeIds($productIds);
        $seen = array_fill_keys($queue, true);

        while ($queue !== array()) {
            $batch = $queue;
            $queue = array();

            $placeholders = array();
            $params = array();
            foreach ($batch as $index => $productId) {
                $key = 'product_id_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $productId;
            }

            $rows = $this->database->fetchAll(
                'SELECT owner_product_id FROM product_derived_stock_links WHERE source_product_id IN (' . implode(', ', $placeholders) . ')',
                $params
            );

            foreach ($rows as $row) {
                $ownerId = isset($row['owner_product_id']) ? (int) $row['owner_product_id'] : 0;
                if ($ownerId <= 0 || isset($seen[$ownerId])) {
                    continue;
                }

                $seen[$ownerId] = true;
                $queue[] = $ownerId;
            }
        }

        $productIds = array_map('intval', array_keys($seen));
        sort($productIds);

        return $productIds;
    }

    public function flush(): void
    {
        if ($this->actionsByProduct === array()) {
            return;
        }

        $logRepository = new ProductChangeLogRepository($this->database);
        $logRepository->ensureSchema();
        $actor = $this->resolveActor();

        foreach ($this->actionsByProduct as $productId => $action) {
            $before = array_key_exists($productId, $this->beforeSnapshots) ? $this->beforeSnapshots[$productId] : null;
            $after = array_key_exists($productId, $this->afterSnapshots) ? $this->afterSnapshots[$productId] : null;
            $changes = $this->diffSnapshots($before, $after, $action);

            if ($changes === array() && $action === 'update') {
                continue;
            }

            $productSnapshot = is_array($after) ? $after : $before;
            $logRepository->add(array(
                'product_id' => $productId,
                'product_name_snapshot' => is_array($productSnapshot) ? ($productSnapshot['product_name'] ?? null) : null,
                'product_sku_snapshot' => is_array($productSnapshot) ? ($productSnapshot['sku'] ?? null) : null,
                'actor_user_id' => $actor['user_id'],
                'actor_name' => $actor['name'],
                'actor_email' => $actor['email'],
                'action' => $action,
                'change_count' => count($changes),
                'summary' => $this->buildSummary($changes, $action),
                'changes_json' => json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ));
        }

        $this->beforeSnapshots = array();
        $this->afterSnapshots = array();
        $this->actionsByProduct = array();
        $this->createdProducts = array();
        $this->deletedProducts = array();
    }

    private function snapshotMap(array $productIds): array
    {
        $productIds = $this->normalizeIds($productIds);
        if ($productIds === array()) {
            return array();
        }

        $products = new ProductRepository($this->database);
        $products->ensureSchema();
        $rows = $products->exportRows($productIds, count($productIds));

        $map = array();
        foreach ($rows as $row) {
            $productId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($productId <= 0) {
                continue;
            }

            $map[$productId] = $this->normalizeSnapshot($row);
        }

        return $map;
    }

    private function normalizeSnapshot(array $row): array
    {
        unset(
            $row['id'],
            $row['category_id'],
            $row['updated_at'],
            $row['created_at'],
            $row['deleted_at'],
            $row['category_slug'],
            $row['category_allegro_id'],
            $row['shared_stock_group_id']
        );

        ksort($row);

        return $this->sortRecursive($row);
    }

    private function sortRecursive($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        if ($this->isList($value)) {
            $normalized = array();
            foreach ($value as $item) {
                $normalized[] = $this->sortRecursive($item);
            }

            usort($normalized, function ($left, $right): int {
                return strcmp(
                    json_encode($left, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    json_encode($right, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                );
            });

            return $normalized;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->sortRecursive($item);
        }

        ksort($value);
        return $value;
    }

    private function diffSnapshots($before, $after, string $action): array
    {
        $before = is_array($before) ? $before : array();
        $after = is_array($after) ? $after : array();
        $keys = array_values(array_unique(array_merge(array_keys($before), array_keys($after))));
        sort($keys);

        $changes = array();
        foreach ($keys as $key) {
            $beforeValue = array_key_exists($key, $before) ? $before[$key] : null;
            $afterValue = array_key_exists($key, $after) ? $after[$key] : null;

            if ($this->valuesEqual($beforeValue, $afterValue)) {
                continue;
            }

            $changes[] = array(
                'field' => $key,
                'label' => $this->fieldLabel($key),
                'before' => $this->displayValue($beforeValue),
                'after' => $this->displayValue($afterValue),
            );
        }

        if ($changes !== array()) {
            return $changes;
        }

        if ($action === 'create') {
            return array(array(
                'field' => 'product',
                'label' => 'Produkt',
                'before' => 'brak',
                'after' => 'utworzono',
            ));
        }

        if ($action === 'copy') {
            return array(array(
                'field' => 'product',
                'label' => 'Produkt',
                'before' => 'brak',
                'after' => 'utworzono jako kopia',
            ));
        }

        if ($action === 'delete') {
            return array(array(
                'field' => 'product',
                'label' => 'Produkt',
                'before' => 'istnial',
                'after' => 'usunieto',
            ));
        }

        return array();
    }

    private function buildSummary(array $changes, string $action): string
    {
        if ($changes === array()) {
            if ($action === 'delete') {
                return 'Usunieto produkt.';
            }

            if ($action === 'copy') {
                return 'Utworzono kopie produktu.';
            }

            if ($action === 'create') {
                return 'Dodano produkt.';
            }

            return 'Zapisano zmiany.';
        }

        $parts = array();
        foreach (array_slice($changes, 0, 3) as $change) {
            $parts[] = sprintf(
                '%s: %s -> %s',
                (string) ($change['label'] ?? 'Pole'),
                (string) ($change['before'] ?? 'brak'),
                (string) ($change['after'] ?? 'brak')
            );
        }

        if (count($changes) > 3) {
            $parts[] = '+' . (count($changes) - 3) . ' kolejnych';
        }

        return implode('; ', $parts);
    }

    private function valuesEqual($left, $right): bool
    {
        return json_encode($left, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            === json_encode($right, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function displayValue($value): string
    {
        if ($value === null || $value === '') {
            return 'brak';
        }

        if (is_bool($value)) {
            return $value ? 'tak' : 'nie';
        }

        if (is_scalar($value)) {
            return $this->limitValue((string) $value);
        }

        if (!is_array($value)) {
            return $this->limitValue((string) $value);
        }

        if ($value === array()) {
            return 'brak';
        }

        $items = array();
        if ($this->isList($value)) {
            foreach ($value as $item) {
                $items[] = $this->displayValue($item);
            }
        } else {
            foreach ($value as $key => $item) {
                $items[] = $this->fieldLabel((string) $key) . ': ' . $this->displayValue($item);
            }
        }

        return $this->limitValue(implode(', ', array_slice($items, 0, 6)));
    }

    private function limitValue(string $value, int $limit = 180): string
    {
        $value = trim($value);
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $limit - 1)) . '...';
    }

    private function fieldLabel(string $field): string
    {
        $map = array(
            'sku' => 'SKU',
            'product_name' => 'Nazwa',
            'description' => 'Opis',
            'category_id' => 'Kategoria ID',
            'category_name' => 'Kategoria',
            'quantity' => 'Ilosc',
            'localization' => 'Lokalizacja',
            'dimensions' => 'Wymiary',
            'contours' => 'Obrys',
            'img' => 'Obrazek',
            'images' => 'Galeria',
            'price_net' => 'Cena netto',
            'price_gross' => 'Cena brutto',
            'vat_rate' => 'VAT',
            'custom_fields' => 'Pola wlasne',
            'allegro_parameters_raw' => 'Parametry Allegro',
            'shared_stock_enabled' => 'Wspolny stan',
            'shared_stock_group_members' => 'Powiazane produkty',
            'shared_stock_group_quantity' => 'Wspolny stan ilosci',
            'shared_stock_group_localization' => 'Wspolna lokalizacja',
            'derived_stock_enabled' => 'Stan wyliczany',
            'derived_stock_source_count' => 'Liczba zrodel stanu',
            'derived_stock_sources' => 'Zrodla stanu',
        );

        if (isset($map[$field])) {
            return $map[$field];
        }

        $label = str_replace('_', ' ', trim($field));
        return $label !== '' ? ucfirst($label) : 'Pole';
    }

    private function mergeAction(string $current, string $next): string
    {
        $priority = array(
            'update' => 1,
            'create' => 2,
            'copy' => 3,
            'delete' => 4,
        );

        return ($priority[$next] ?? 1) >= ($priority[$current] ?? 1) ? $next : $current;
    }

    private function resolveActor(): array
    {
        $config = Config::get('app');
        $cookieName = isset($config['jwt_cookie']) ? (string) $config['jwt_cookie'] : '';
        if ($cookieName === '' || empty($_COOKIE[$cookieName])) {
            return array('user_id' => null, 'name' => null, 'email' => null);
        }

        try {
            $jwt = new JwtService();
            $payload = $jwt->validate((string) $_COOKIE[$cookieName]);
            if (!is_array($payload) || empty($payload['user_id'])) {
                return array('user_id' => null, 'name' => null, 'email' => null);
            }

            $users = new UserRepository($this->database);
            $users->ensureSchema();
            $user = $users->findById((int) $payload['user_id']);
            $fullName = trim(implode(' ', array_filter(array(
                $user && !empty($user['first_name']) ? (string) $user['first_name'] : '',
                $user && !empty($user['last_name']) ? (string) $user['last_name'] : '',
            ))));

            return array(
                'user_id' => $user ? (int) ($user['id'] ?? 0) : (int) $payload['user_id'],
                'name' => $fullName !== '' ? $fullName : ($user && !empty($user['email']) ? (string) $user['email'] : null),
                'email' => $user && !empty($user['email']) ? (string) $user['email'] : null,
            );
        } catch (\Throwable $exception) {
            return array('user_id' => null, 'name' => null, 'email' => null);
        }
    }

    private function normalizeIds(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        sort($productIds);
        return $productIds;
    }

    private function isList(array $value): bool
    {
        if ($value === array()) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
