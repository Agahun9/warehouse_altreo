<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Models\CategoryRepository;
use App\Models\ProductRepository;
use App\Services\AllegroService;
use Throwable;

/**
 * Zewnetrzne API tylko do odczytu, autoryzowane tokenami z Administracja -> Tokeny API.
 * Instrukcja dla integratorow: index.php?controller=administration&action=apidocs
 */
class ApiController extends Controller
{
    const API_VERSION = '1.0';
    const DEFAULT_PER_PAGE = 100;
    const MAX_PER_PAGE = 500;

    /** @var ProductRepository */
    private $products;

    /** @var CategoryRepository */
    private $categories;

    /** @var array|null */
    private $categoryMap = null;

    /** @var AllegroService|null */
    private $allegro = null;

    /** @var array */
    private $allegroParameterCache = array();

    public function __construct()
    {
        $this->categories = new CategoryRepository($this->db());
        $this->categories->ensureSchema();
        $this->products = new ProductRepository($this->db());
        $this->products->ensureSchema();
    }

    public function index(): void
    {
        $this->requireApiModuleAccess('products');

        $this->jsonResponse(array(
            'api_version' => self::API_VERSION,
            'endpoints' => array(
                'categories' => '?controller=api&action=categories',
                'products' => '?controller=api&action=products&category_id={id}&page=1&per_page=100',
                'product' => '?controller=api&action=product&id={id} | &sku={sku}',
            ),
        ));
    }

    public function categories(): void
    {
        $this->requireApiModuleAccess('products');
        if (!$this->allowGet()) {
            return;
        }

        try {
            $items = array();
            foreach ($this->categories->allWithProductCounts() as $category) {
                $item = $this->normalizeCategory($category);
                $item['products_count'] = (int) ($category['products_count'] ?? 0);
                $items[] = $item;
            }

            $this->jsonResponse(array(
                'api_version' => self::API_VERSION,
                'count' => count($items),
                'items' => $items,
            ));
        } catch (Throwable $exception) {
            $this->jsonResponse(array('error' => $exception->getMessage()), 500);
        }
    }

    public function products(): void
    {
        $this->requireApiModuleAccess('products');
        if (!$this->allowGet()) {
            return;
        }

        $categoryIds = $this->parseCategoryIds((string) $this->input('category_id', ''));
        if ($categoryIds === array()) {
            $this->jsonResponse(array('error' => 'Podaj category_id (liczba lub lista po przecinku, np. 5 albo 5,7).'), 400);
            return;
        }

        $knownCategories = $this->categoryMap();
        $missing = array();
        foreach ($categoryIds as $categoryId) {
            if (!isset($knownCategories[$categoryId])) {
                $missing[] = $categoryId;
            }
        }
        if ($missing !== array()) {
            $this->jsonResponse(array('error' => 'Nie znaleziono kategorii o ID: ' . implode(', ', $missing) . '.'), 404);
            return;
        }

        $updatedSince = trim((string) $this->input('updated_since', ''));
        if ($updatedSince !== '') {
            $timestamp = strtotime($updatedSince);
            if ($timestamp === false) {
                $this->jsonResponse(array('error' => 'Niepoprawny format updated_since. Uzyj np. 2026-01-31 albo 2026-01-31T12:00:00.'), 400);
                return;
            }
            $updatedSince = date('Y-m-d H:i:s', $timestamp);
        }

        $filters = array(
            'updated_since' => $updatedSince,
            'in_stock' => (string) $this->input('in_stock', '0') === '1',
        );
        $page = max(1, (int) $this->input('page', 1));
        $perPage = max(1, min(self::MAX_PER_PAGE, (int) $this->input('per_page', self::DEFAULT_PER_PAGE)));
        $withParameterNames = (string) $this->input('parameter_names', '1') !== '0';

        try {
            $total = $this->products->apiCategoryProductCount($categoryIds, $filters);
            $ids = $this->products->apiCategoryProductIds($categoryIds, $filters, $perPage, ($page - 1) * $perPage);
            $items = $this->loadProducts($ids, $withParameterNames);
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;

            $this->jsonResponse(array(
                'api_version' => self::API_VERSION,
                'category_ids' => $categoryIds,
                'filters' => array(
                    'updated_since' => $updatedSince !== '' ? $updatedSince : null,
                    'in_stock' => $filters['in_stock'],
                ),
                'pagination' => array(
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                ),
                'count' => count($items),
                'items' => $items,
            ));
        } catch (Throwable $exception) {
            $this->jsonResponse(array('error' => $exception->getMessage()), 500);
        }
    }

    public function product(): void
    {
        $this->requireApiModuleAccess('products');
        if (!$this->allowGet()) {
            return;
        }

        $productId = (int) $this->input('id', 0);
        $sku = trim((string) $this->input('sku', ''));

        try {
            if ($productId <= 0 && $sku !== '') {
                $found = $this->products->findBySku($sku);
                $productId = $found ? (int) $found['id'] : 0;
            }

            if ($productId <= 0) {
                $this->jsonResponse(array('error' => $sku !== '' ? 'Nie znaleziono produktu.' : 'Podaj id albo sku produktu.'), $sku !== '' ? 404 : 400);
                return;
            }

            $items = $this->loadProducts(array($productId), (string) $this->input('parameter_names', '1') !== '0');
            if ($items === array()) {
                $this->jsonResponse(array('error' => 'Nie znaleziono produktu.'), 404);
                return;
            }

            $this->jsonResponse(array(
                'api_version' => self::API_VERSION,
                'item' => $items[0],
            ));
        } catch (Throwable $exception) {
            $this->jsonResponse(array('error' => $exception->getMessage()), 500);
        }
    }

    private function loadProducts(array $ids, bool $withParameterNames): array
    {
        if ($ids === array()) {
            return array();
        }

        $rowsById = array();
        foreach ($this->products->exportRows($ids) as $row) {
            $rowsById[(int) $row['id']] = $row;
        }

        $items = array();
        foreach ($ids as $id) {
            if (isset($rowsById[$id])) {
                $items[] = $this->normalizeProduct($rowsById[$id], $withParameterNames);
            }
        }

        return $items;
    }

    private function normalizeProduct(array $row, bool $withParameterNames): array
    {
        $categoryId = (int) ($row['category_id'] ?? 0);
        $categoryMap = $this->categoryMap();
        $category = isset($categoryMap[$categoryId]) ? $this->normalizeCategory($categoryMap[$categoryId]) : null;
        $allegroCategoryId = is_array($category) ? (string) $category['allegro_category_id'] : '';

        $images = array();
        foreach ((isset($row['images']) && is_array($row['images']) ? $row['images'] : array()) as $position => $image) {
            $url = (string) ($image['url'] ?? '');
            if ($url === '') {
                continue;
            }
            $images[] = array(
                'position' => $position + 1,
                'url' => $url,
                'absolute_url' => $this->absoluteAssetUrl($url),
            );
        }

        $sharedStockEnabled = !empty($row['shared_stock_enabled']);
        $derivedStockEnabled = !empty($row['derived_stock_enabled']);

        $knownKeys = array(
            'id', 'sku', 'ean', 'product_name', 'description', 'category_id', 'quantity', 'localization',
            'shared_stock_group_id', 'dimensions', 'contours', 'img', 'images', 'price_net', 'price_gross', 'vat_rate',
            'created_at', 'updated_at', 'deleted_at',
            'category_name', 'category_slug', 'category_allegro_id', 'category_empik_id', 'category_mediamarkt_id',
            'allegro_parameters_raw', 'allegro_compatibility_list_raw', 'empik_parameters_raw', 'mediamarkt_parameters_raw',
            'temu_parameters_raw', 'custom_fields',
            'shared_stock_enabled', 'shared_stock_group_quantity', 'shared_stock_group_localization', 'shared_stock_group_members',
            'derived_stock_enabled', 'derived_stock_source_count', 'derived_stock_sources', 'has_derived_dependents', 'derived_dependents_count',
        );
        $extraFields = array();
        foreach ($row as $key => $value) {
            if (!in_array((string) $key, $knownKeys, true) && (is_scalar($value) || $value === null)) {
                $extraFields[(string) $key] = $value;
            }
        }

        return array(
            'id' => (int) ($row['id'] ?? 0),
            'sku' => (string) ($row['sku'] ?? ''),
            'ean' => (string) ($row['ean'] ?? ''),
            'name' => (string) ($row['product_name'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'category' => $category,
            'stock' => array(
                'quantity' => (int) ($row['quantity'] ?? 0),
                'localization' => (string) ($row['localization'] ?? ''),
                'shared_stock' => array(
                    'enabled' => $sharedStockEnabled,
                    'group_id' => $sharedStockEnabled ? (int) ($row['shared_stock_group_id'] ?? 0) : null,
                    'members' => $sharedStockEnabled && isset($row['shared_stock_group_members']) && is_array($row['shared_stock_group_members'])
                        ? array_values($row['shared_stock_group_members'])
                        : array(),
                ),
                'derived_stock' => array(
                    'enabled' => $derivedStockEnabled,
                    'sources' => $derivedStockEnabled && isset($row['derived_stock_sources']) && is_array($row['derived_stock_sources'])
                        ? array_values($row['derived_stock_sources'])
                        : array(),
                    'dependents_count' => (int) ($row['derived_dependents_count'] ?? 0),
                ),
            ),
            'price' => array(
                'net' => round((float) ($row['price_net'] ?? 0), 2),
                'gross' => round((float) ($row['price_gross'] ?? 0), 2),
                'vat_rate' => round((float) ($row['vat_rate'] ?? 0), 2),
                'currency' => 'PLN',
            ),
            'dimensions' => (string) ($row['dimensions'] ?? ''),
            'contours' => (string) ($row['contours'] ?? ''),
            'images' => $images,
            'custom_fields' => isset($row['custom_fields']) && is_array($row['custom_fields']) ? $row['custom_fields'] : array(),
            'parameters' => array(
                'allegro' => $this->normalizeAllegroParameters(
                    isset($row['allegro_parameters_raw']) && is_array($row['allegro_parameters_raw']) ? $row['allegro_parameters_raw'] : array(),
                    $withParameterNames ? $allegroCategoryId : ''
                ),
                'allegro_compatibility_list' => isset($row['allegro_compatibility_list_raw']) && is_array($row['allegro_compatibility_list_raw'])
                    ? $row['allegro_compatibility_list_raw']
                    : array(),
                'empik' => $this->normalizeRawParameters($row['empik_parameters_raw'] ?? array()),
                'mediamarkt' => $this->normalizeRawParameters($row['mediamarkt_parameters_raw'] ?? array()),
                'temu' => $this->normalizeRawParameters($row['temu_parameters_raw'] ?? array()),
            ),
            'extra_fields' => $extraFields,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        );
    }

    private function normalizeCategory(array $category): array
    {
        return array(
            'id' => (int) ($category['id'] ?? 0),
            'name' => (string) ($category['name'] ?? ''),
            'slug' => (string) ($category['slug'] ?? ''),
            'sku_prefix' => (string) ($category['sku_prefix'] ?? ''),
            'description' => (string) ($category['description'] ?? ''),
            'allegro_category_id' => (string) ($category['allegro_category_id'] ?? ''),
            'empik_category_id' => (string) ($category['empik_category_id'] ?? ''),
            'mediamarkt_category_id' => (string) ($category['mediamarkt_category_id'] ?? ''),
            'temu_category_id' => (string) ($category['temu_category_id'] ?? ''),
            'temu_category_name' => (string) ($category['temu_category_name'] ?? ''),
            'temu_category_path' => (string) ($category['temu_category_path'] ?? ''),
        );
    }

    private function normalizeRawParameters($parameters): array
    {
        $result = array();
        if (!is_array($parameters)) {
            return $result;
        }

        foreach ($parameters as $parameterId => $value) {
            $result[] = array(
                'id' => (string) $parameterId,
                'value' => $value,
            );
        }

        return $result;
    }

    private function normalizeAllegroParameters(array $parameters, string $allegroCategoryId): array
    {
        $definitions = $allegroCategoryId !== '' ? $this->allegroParameterDefinitions($allegroCategoryId) : array();
        $result = array();

        foreach ($parameters as $parameterId => $value) {
            $parameterId = (string) $parameterId;
            $definition = $definitions[$parameterId] ?? null;
            $labels = array();

            $scalarValues = is_array($value) ? $value : array($value);
            array_walk_recursive($scalarValues, function ($item) use (&$labels, $definition): void {
                if (!is_scalar($item)) {
                    return;
                }
                $item = trim((string) $item);
                if ($item === '') {
                    return;
                }
                $labels[] = ($definition !== null && isset($definition['dictionary'][$item])) ? $definition['dictionary'][$item] : $item;
            });

            $result[] = array(
                'id' => $parameterId,
                'name' => $definition !== null ? $definition['name'] : null,
                'unit' => $definition !== null && $definition['unit'] !== '' ? $definition['unit'] : null,
                'value' => $value,
                'value_labels' => array_values(array_unique($labels)),
            );
        }

        return $result;
    }

    private function allegroParameterDefinitions(string $allegroCategoryId): array
    {
        if (array_key_exists($allegroCategoryId, $this->allegroParameterCache)) {
            return $this->allegroParameterCache[$allegroCategoryId];
        }

        $definitions = array();
        try {
            if ($this->allegro === null) {
                $this->allegro = new AllegroService();
            }

            foreach ($this->allegro->categoryParameters($allegroCategoryId) as $parameter) {
                $dictionary = array();
                foreach ((isset($parameter['dictionary']) && is_array($parameter['dictionary']) ? $parameter['dictionary'] : array()) as $option) {
                    $dictionary[(string) ($option['id'] ?? '')] = (string) ($option['value'] ?? '');
                }

                $definitions[(string) $parameter['id']] = array(
                    'name' => (string) ($parameter['name'] ?? ''),
                    'unit' => (string) ($parameter['unit'] ?? ''),
                    'dictionary' => $dictionary,
                );
            }
        } catch (Throwable $exception) {
            // Nazwy parametrow sa dodatkiem; brak polaczenia z Allegro nie blokuje odpowiedzi.
            $definitions = array();
        }

        $this->allegroParameterCache[$allegroCategoryId] = $definitions;
        return $definitions;
    }

    private function categoryMap(): array
    {
        if ($this->categoryMap === null) {
            $this->categoryMap = array();
            foreach ($this->categories->all() as $category) {
                $this->categoryMap[(int) $category['id']] = $category;
            }
        }

        return $this->categoryMap;
    }

    private function parseCategoryIds(string $raw): array
    {
        $ids = array();
        foreach (preg_split('/[\s,;|]+/', trim($raw)) ?: array() as $part) {
            if ($part !== '' && ctype_digit($part) && (int) $part > 0) {
                $ids[(int) $part] = (int) $part;
            }
        }

        return array_values($ids);
    }

    private function absoluteAssetUrl(string $url): string
    {
        if (preg_match('#^(https?:)?//#i', $url) === 1) {
            return $url;
        }

        $appConfig = Config::get('app');
        $indexUrl = trim((string) ($appConfig['public_base_url'] ?? ''));
        if ($indexUrl === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $indexUrl = $scheme . '://' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost') . (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
        }

        return rtrim(str_replace('\\', '/', dirname($indexUrl)), '/') . '/' . ltrim($url, '/');
    }

    private function allowGet(): bool
    {
        if ($this->requestMethod() === 'GET') {
            return true;
        }

        $this->jsonResponse(array('error' => 'Dozwolona jest tylko metoda GET.'), 405);
        return false;
    }

    private function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
