<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\AllegroStorageRepository;
use App\Models\ComputerCsvTemplateRepository;
use App\Models\EmpikStorageRepository;
use App\Models\ErliStorageRepository;
use App\Models\MoreleStorageRepository;
use App\Services\AllegroService;
use App\Services\EmpikService;
use App\Services\MoreleService;
use RuntimeException;
use Throwable;

class ComputersController extends Controller
{
    private const PRODUCTS_TABLE = 'pr_products_altreo';
    private const COMPONENTS_TABLE = 'pr_components_altreo';
    private const TEMPLATES_TABLE = 'pr_altreo_template';
    private const TASK_QUEUE_TABLE = 'pr_task_queue';
    private const DEFAULT_DESKTOP_EU_CATEGORY_ID = '486';
    private const DEFAULT_DESKTOP_MORELE_CATEGORY_ID = 672;
    private const DEFAULT_DESKTOP_EMPIK_CATEGORY_ID = '21-16-1';
    private const DESKTOP_CATEGORY_KEYWORDS = array(
        'komputery stacjonarne',
        'komputer stacjonarny',
        'komputery',
        'desktop',
        'pc',
    );

    /** @var bool */
    private static $schemaEnsured = false;

    /** @var \App\Models\SettingRepository|null */
    private $settings = null;

    /** @var ComputerCsvTemplateRepository */
    private $computerCsvTemplates;

    /** @var array<string, bool> */
    private $tableExistsCache = array();

    public function __construct()
    {
        $this->ensureSchema();
        $this->settings = new \App\Models\SettingRepository($this->db());
        $this->settings->ensureSchema();
        $this->computerCsvTemplates = new ComputerCsvTemplateRepository($this->db());
        $this->computerCsvTemplates->ensureSchema();
        $this->computerCsvTemplates->seed($this->defaultComputerCsvTemplates());
        $this->computerCsvTemplates->fillEmptyDescriptionTemplates($this->defaultComputerDescriptionTemplate());
    }

    public function index(): void
    {
        $this->requireModule('products');
        $this->redirect('./index.php?controller=computers&action=products');
    }

    public function products(): void
    {
        $currentUser = $this->requireModule('products');

        if (trim((string) $this->input('price_market_accounts', '')) === '1') {
            $this->priceMarketAccountsForSelection();
            return;
        }

        if ($this->isPost()) {
            $this->handleProductsPost();
        }

        $deleteId = (int) $this->input('delete_id', 0);
        if ($deleteId > 0) {
            $this->deleteProduct($deleteId);
        }

        $components = $this->db()->fetchAll('SELECT * FROM ' . self::COMPONENTS_TABLE . ' ORDER BY category, name');
        $componentsById = array();
        $grouped = array();
        foreach ($components as $index => $component) {
            if (is_array($component)) {
                $component = $this->normalizeComponentTextFields($component);
                $components[$index] = $component;
            }
            $componentId = (int) ($component['id'] ?? 0);
            $componentsById[$componentId] = $component;
            $category = trim((string) ($component['category'] ?? ''));
            if ($category === '') {
                $category = 'Inne';
            }
            if (!isset($grouped[$category])) {
                $grouped[$category] = array();
            }
            $grouped[$category][] = $component;
        }

        $filterComponents = array_values(array_filter(array_map('intval', (array) $this->input('filter_components', array()))));
        $filterName = trim((string) $this->input('filter_name', ''));
        $filterMarketAccounts = $this->selectedMarketAccountFilters((array) $this->input('filter_market_accounts', array()));
        $filterOfferStatus = $this->input('filter_status_offer', '');
        if ($filterMarketAccounts === array() && $filterOfferStatus !== '') {
            $filterMarketAccounts[] = (string) ((int) $filterOfferStatus);
        }
        $allegroMarketAccounts = $this->activeComputerAllegroAccounts();
        $empikMarketAccounts = $this->activeComputerEmpikAccounts();
        $erliMarketAccounts = $this->activeComputerErliAccounts();
        $moreleMarketAccounts = $this->activeComputerMoreleAccounts();
        $allegroMarketAccounts = $this->markSelectedMarketAccounts($allegroMarketAccounts, 'allegro', $filterMarketAccounts);
        $empikMarketAccounts = $this->markSelectedMarketAccounts($empikMarketAccounts, 'empik', $filterMarketAccounts);
        $erliMarketAccounts = $this->markSelectedMarketAccounts($erliMarketAccounts, 'erli', $filterMarketAccounts);
        $moreleMarketAccounts = $this->markSelectedMarketAccounts($moreleMarketAccounts, 'morele', $filterMarketAccounts);

        $allowedPerPage = array(10, 20, 50, 100, 1000, 10000);
        $perPage = (int) $this->input('per_page', 10);
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $filters = array(
            'components' => $filterComponents,
            'name' => $filterName,
            'market_accounts' => $filterMarketAccounts,
        );
        list($filterSql, $filterParams) = $this->computerProductFilterSql($filters);
        $totalProducts = (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM ' . self::PRODUCTS_TABLE . ' products' . $filterSql,
            $filterParams
        );
        $currentPage = max(1, (int) $this->input('page', 1));
        $totalPages = max(1, (int) ceil($totalProducts / $perPage));
        if ($currentPage > $totalPages) {
            $currentPage = $totalPages;
        }
        $offset = ($currentPage - 1) * $perPage;
        $products = $this->db()->fetchAll(
            'SELECT products.* FROM ' . self::PRODUCTS_TABLE . ' products'
            . $filterSql
            . ' ORDER BY products.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $filterParams
        );
        $products = $this->attachActiveMoreleOffers($this->attachActiveErliProducts($this->attachActiveEmpikOffers($this->attachActiveAllegroOffers($products))));
        $pagedProducts = array();
        foreach ($products as $product) {
            $product = $this->normalizeProductImageFields($product);
            if (!isset($product['allegro_accounts']) || !is_array($product['allegro_accounts'])) {
                $product['allegro_accounts'] = array();
            }
            if (!isset($product['erli_accounts']) || !is_array($product['erli_accounts'])) {
                $product['erli_accounts'] = array();
            }
            if (!isset($product['empik_accounts']) || !is_array($product['empik_accounts'])) {
                $product['empik_accounts'] = array();
            }
            if (!isset($product['morele_accounts']) || !is_array($product['morele_accounts'])) {
                $product['morele_accounts'] = array();
            }
            $componentIds = $this->csvIds((string) ($product['id_components'] ?? ''));
            $product['component_ids'] = $componentIds;
            $product['components'] = array();
            foreach ($componentIds as $componentId) {
                if (isset($componentsById[$componentId])) {
                    $product['components'][] = $componentsById[$componentId];
                }
            }

            $pagedProducts[] = $product;
        }

        $queryParams = $_GET;
        unset($queryParams['controller'], $queryParams['action'], $queryParams['page'], $queryParams['per_page']);
        $baseQuery = http_build_query($queryParams);
        $paginationBaseQuery = './index.php?controller=computers&action=products';
        if ($baseQuery !== '') {
            $paginationBaseQuery .= '&' . $baseQuery . '&';
        } else {
            $paginationBaseQuery .= '&';
        }

        $pageLinks = $this->pageLinks($currentPage, $totalPages);

        $this->render('computers/products', array(
            'pageTitle' => 'Komputery',
            'contentTitle' => 'Panel komputerow',
            'pageDescription' => 'Stary panel komputerow w nowej aplikacji.',
            'breadcrumbCurrent' => 'Komputery',
            'currentUser' => $currentUser,
            'success' => $this->getFlash('success') ?? '',
            'errors' => $this->normalizeErrors($this->getFlash('error')),
            'products' => $pagedProducts,
            'components' => $components,
            'grouped' => $grouped,
            'profit' => (float) $this->input('profit', 0),
            'filterComponents' => $filterComponents,
            'filterName' => $filterName,
            'filterMarketAccounts' => $filterMarketAccounts,
            'allegroMarketAccounts' => $allegroMarketAccounts,
            'empikMarketAccounts' => $empikMarketAccounts,
            'erliMarketAccounts' => $erliMarketAccounts,
            'moreleMarketAccounts' => $moreleMarketAccounts,
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'page_links' => $pageLinks,
            'total_products' => $totalProducts,
            'shown_count' => count($pagedProducts),
            'pagination_base_query' => $paginationBaseQuery,
            'productsImageBase' => './img_computers_products',
            'computerTab' => 'products',
            'csvTemplates' => $this->computerCsvTemplates->all(),
        ));
    }

    public function empikparameteroptions(): void
    {
        $this->requireModule('products');
        $attributeId = trim((string) $this->input('attribute_id', ''));
        $query = trim((string) $this->input('q', ''));
        $limit = max(1, min(100, (int) $this->input('limit', 40)));
        $payload = $this->loadEmpikParameterPayload();
        $categoryId = trim((string) ($payload['meta']['category_id'] ?? ''));

        header('Content-Type: application/json; charset=utf-8');
        if ($categoryId === '' || $attributeId === '') {
            echo json_encode(array('items' => array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        try {
            $service = new EmpikService();
            echo json_encode(array(
                'items' => $service->searchAttributeOptions($categoryId, $attributeId, $query, $limit),
            ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $exception) {
            http_response_code(500);
            echo json_encode(array(
                'items' => array(),
                'error' => $exception->getMessage(),
            ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        exit;
    }

    public function csvtemplates(): void
    {
        $currentUser = $this->requireModule('products');

        $this->render('computers/csv_templates', array(
            'pageTitle' => 'Szablony CSV komputerow',
            'contentTitle' => 'Szablony CSV komputerow',
            'pageDescription' => 'Osobne konfiguracje eksportu dla produktow komputerowych.',
            'breadcrumbCurrent' => 'Szablony CSV',
            'currentUser' => $currentUser,
            'success' => $this->getFlash('success') ?? '',
            'errors' => $this->normalizeErrors($this->getFlash('error')),
            'templates' => $this->computerCsvTemplates->all(),
            'computerTab' => 'csvtemplates',
        ));
    }

    public function editcsvtemplate(): void
    {
        $this->requireModuleWrite('products');
        $id = (int) $this->input('id', 0);
        $template = $this->computerCsvTemplates->find($id);
        if (!$template) {
            $this->setFlash('error', json_encode(array('Nie znaleziono szablonu CSV.')));
            $this->redirect('./index.php?controller=computers&action=csvtemplates');
        }

        $this->render('computers/csv_template_form', array(
            'pageTitle' => 'Edycja szablonu CSV',
            'contentTitle' => 'Edytuj szablon CSV komputerow',
            'pageDescription' => 'Ustal kolejnosc, nazwy i zrodla kolumn eksportu.',
            'breadcrumbCurrent' => 'Edycja szablonu CSV',
            'template' => $template,
            'columnsJson' => json_encode($template['columns'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'sourceOptions' => $this->computerCsvSourceOptions(),
            'sourceOptionsJson' => json_encode($this->computerCsvSourceOptions(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'descriptionTokensJson' => json_encode($this->computerDescriptionTokens(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'computerTab' => 'csvtemplates',
        ));
    }

    public function savecsvtemplate(): void
    {
        $this->requireModuleWrite('products');
        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=computers&action=csvtemplates');
        }

        $id = (int) $this->input('id', 0);
        try {
            $columns = $this->validatedComputerCsvColumns();
            $name = trim((string) $this->input('name', ''));
            if ($name === '') {
                throw new RuntimeException('Nazwa szablonu jest wymagana.');
            }
            $delimiter = (string) $this->input('delimiter', ';');
            if (!in_array($delimiter, array(';', ',', "\t", '|'), true)) {
                $delimiter = ';';
            }
            $this->computerCsvTemplates->save($id, array(
                'name' => $name,
                'description' => trim((string) $this->input('description', '')),
                'filename_prefix' => $this->safeCsvFilenamePrefix((string) $this->input('filename_prefix', 'computers_export')),
                'delimiter' => $delimiter,
                'encoding' => strtoupper(trim((string) $this->input('encoding', 'UTF-8'))) === 'WINDOWS-1250' ? 'WINDOWS-1250' : 'UTF-8',
                'add_bom' => $this->input('add_bom', '0') === '1',
                'description_template' => (string) $this->input('description_template', ''),
            ), $columns);
            $this->setFlash('success', 'Szablon CSV zostal zapisany.');
        } catch (Throwable $exception) {
            $this->setFlash('error', json_encode(array($exception->getMessage())));
        }

        $this->redirect('./index.php?controller=computers&action=csvtemplates');
    }

    public function duplicatecsvtemplate(): void
    {
        $this->requireModuleWrite('products');
        if ($this->isPost()) {
            try {
                $this->computerCsvTemplates->duplicate((int) $this->input('id', 0));
                $this->setFlash('success', 'Szablon CSV zostal zduplikowany.');
            } catch (Throwable $exception) {
                $this->setFlash('error', json_encode(array($exception->getMessage())));
            }
        }
        $this->redirect('./index.php?controller=computers&action=csvtemplates');
    }

    public function deletecsvtemplate(): void
    {
        $this->requireModuleWrite('products');
        if ($this->isPost()) {
            try {
                $this->computerCsvTemplates->delete((int) $this->input('id', 0));
                $this->setFlash('success', 'Szablon CSV zostal usuniety.');
            } catch (Throwable $exception) {
                $this->setFlash('error', json_encode(array($exception->getMessage())));
            }
        }
        $this->redirect('./index.php?controller=computers&action=csvtemplates');
    }

    public function exportcsv(): void
    {
        $this->requireModule('products');
        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=computers&action=products');
        }

        $template = $this->computerCsvTemplates->find((int) $this->input('csv_template_id', 0));
        $productIds = $this->selectedComputerProductIdsFromRequest();
        if (!$template || $productIds === array()) {
            $this->setFlash('error', json_encode(array('Wybierz szablon CSV i co najmniej jeden produkt.')));
            $this->redirect('./index.php?controller=computers&action=products');
        }

        $this->streamComputerTemplateCsv($template, $productIds);
    }

    public function searchcsvpreviewproducts(): void
    {
        $this->requireModule('products');
        $query = trim((string) $this->input('q', ''));
        $rows = array();

        if ($query !== '') {
            $like = '%' . $query . '%';
            $rows = $this->db()->fetchAll(
                'SELECT id, sku, name, EAN, price, img, img_morele, img_empik'
                . ' FROM ' . self::PRODUCTS_TABLE
                . ' WHERE CAST(id AS CHAR) LIKE :id_query'
                . ' OR name LIKE :name_query'
                . ' OR sku LIKE :sku_query'
                . ' OR CAST(EAN AS CHAR) LIKE :ean_query'
                . ' ORDER BY CASE WHEN CAST(id AS CHAR) = :exact_query THEN 0 ELSE 1 END, id DESC'
                . ' LIMIT 20',
                array(
                    'id_query' => $like,
                    'name_query' => $like,
                    'sku_query' => $like,
                    'ean_query' => $like,
                    'exact_query' => $query,
                )
            );
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('products' => $rows), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function priceMarketAccountsForSelection(): void
    {
        $productIds = $this->selectedComputerProductIdsFromRequest();
        $accounts = array();

        foreach (array_chunk($productIds, 500) as $chunk) {
            if ($chunk === array()) {
                continue;
            }

            $params = array();
            $placeholders = array();
            foreach ($chunk as $index => $productId) {
                $key = 'product_id_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = (int) $productId;
            }

            $products = $this->db()->fetchAll(
                'SELECT * FROM ' . self::PRODUCTS_TABLE . ' WHERE id IN (' . implode(',', $placeholders) . ')',
                $params
            );
            $products = $this->attachActiveMoreleOffers($this->attachActiveErliProducts($this->attachActiveEmpikOffers($this->attachActiveAllegroOffers($products))));

            foreach ($products as $product) {
                if (!is_array($product)) {
                    continue;
                }

                $this->collectPriceMarketAccounts($accounts, (array) ($product['allegro_accounts'] ?? array()), 'allegro', 'Allegro');
                $this->collectPriceMarketAccounts($accounts, (array) ($product['empik_accounts'] ?? array()), 'empik', 'Empik');
                $this->collectPriceMarketAccounts($accounts, (array) ($product['erli_accounts'] ?? array()), 'erli', 'Erli');
                $this->collectPriceMarketAccounts($accounts, (array) ($product['morele_accounts'] ?? array()), 'morele', 'Morele');
            }
        }

        uasort($accounts, static function (array $left, array $right): int {
            return strcmp((string) $left['label'], (string) $right['label']);
        });

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('accounts' => array_values($accounts)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function collectPriceMarketAccounts(array &$accounts, array $offers, string $market, string $labelPrefix): void
    {
        foreach ($offers as $offer) {
            if (!is_array($offer)) {
                continue;
            }

            $accountId = (int) ($offer['account_id'] ?? 0);
            if ($accountId <= 0) {
                continue;
            }

            $value = $market . ':' . $accountId;
            if (!isset($accounts[$value])) {
                $accounts[$value] = array(
                    'value' => $value,
                    'label' => $labelPrefix . ' ' . (string) ($offer['account_name'] ?? ''),
                );
            }
        }
    }

    public function previewcsvdescription(): void
    {
        $this->requireModule('products');
        if (!$this->isPost()) {
            http_response_code(405);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('error' => 'Podglad wymaga zapytania POST.'));
            exit;
        }

        $product = $this->productById((int) $this->input('product_id', 0));
        if (!$product) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('error' => 'Nie znaleziono produktu.'));
            exit;
        }

        $components = $this->computerComponentsForProduct($product);
        $html = $this->renderComputerDescription(
            $product,
            $components,
            (string) $this->input('description_template', '')
        );

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'html' => $html,
            'product' => array(
                'id' => (int) ($product['id'] ?? 0),
                'name' => (string) ($product['name'] ?? ''),
                'sku' => (string) ($product['sku'] ?? ''),
                'ean' => (string) ($product['EAN'] ?? ''),
            ),
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function components(): void
    {
        $currentUser = $this->requireModule('products');

        $ajaxParams = trim((string) $this->input('ajax_params', ''));
        if ($ajaxParams !== '') {
            $this->renderComponentParams($ajaxParams);
            return;
        }

        if ($this->isPost()) {
            $this->handleComponentsPost();
        }

        $deleteId = (int) $this->input('delete_id', 0);
        if ($deleteId > 0) {
            $this->deleteComponent($deleteId);
        }

        $editId = (int) $this->input('edit_id', 0);
        $editItem = null;
        if ($editId > 0) {
            $editItem = $this->db()->fetch('SELECT * FROM ' . self::COMPONENTS_TABLE . ' WHERE id = :id', array('id' => $editId));
            if (is_array($editItem)) {
                $editItem = $this->normalizeComponentTextFields($editItem);
                $editItem = $this->hydrateComponentParameterMaps($editItem);
                $editItem = $this->normalizeComponentImageFields($editItem);
            }
        }

        $items = $this->db()->fetchAll(
            'SELECT *, JSON_LENGTH(parameters_morele) AS parameters_morele_count, JSON_LENGTH(parameters_eu) AS parameters_eu_count, JSON_LENGTH(parameters_empik) AS parameters_empik_count
             FROM ' . self::COMPONENTS_TABLE . ' ORDER BY category ASC, name ASC'
        );
        foreach ($items as $index => $item) {
            if (is_array($item)) {
                $item = $this->normalizeComponentTextFields($item);
                $items[$index] = $this->normalizeComponentImageFields($item);
            }
        }
        $templates = $this->db()->fetchAll('SELECT * FROM ' . self::TEMPLATES_TABLE . ' ORDER BY name ASC');
        $columns = $this->db()->fetchAll(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = :table_name AND TABLE_SCHEMA = DATABASE() ORDER BY ORDINAL_POSITION ASC',
            array('table_name' => self::COMPONENTS_TABLE)
        );

        $this->render('computers/components', array(
            'pageTitle' => 'Komponenty',
            'contentTitle' => 'Panel komponentow',
            'pageDescription' => 'Zarzadzanie komponentami komputerow.',
            'breadcrumbCurrent' => 'Komponenty',
            'currentUser' => $currentUser,
            'success' => $this->getFlash('success') ?? '',
            'errors' => $this->normalizeErrors($this->getFlash('error')),
            'items' => $items,
            'editItem' => $editItem,
            'product' => $editItem ?? array(),
            'parameters' => array(),
            'morele_parameters' => array('category_characteristics' => array()),
            'templates' => $templates,
            'columns' => $columns,
            'imgFolder' => './img_components',
            'computerTab' => 'components',
        ));
    }

    private function handleProductsPost(): void
    {
        if ($this->input('create_variants', null) !== null) {
            $this->createVariants();
        }

        $saveProduct = (int) $this->input('save_product', 0);
        if ($saveProduct > 0) {
            $this->saveProduct($saveProduct);
        }

        $bulkAction = trim((string) $this->input('bulk_action', ''));
        $productIds = $this->selectedComputerProductIdsFromRequest();
        if ($bulkAction !== '' && $productIds !== array()) {
            $this->handleProductsBulkAction($bulkAction, $productIds);
        }
    }

    private function selectedComputerProductIdsFromRequest(): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', (array) $this->input('product_ids', array())))));
        if ((string) $this->input('selection_scope', '') !== 'filtered') {
            return $productIds;
        }

        $filters = array(
            'components' => array_values(array_filter(array_map('intval', (array) $this->input('selection_filter_components', array())))),
            'name' => trim((string) $this->input('selection_filter_name', '')),
            'market_accounts' => $this->selectedMarketAccountFilters(
                (array) $this->input('selection_filter_market_accounts', array())
            ),
        );
        list($filterSql, $filterParams) = $this->computerProductFilterSql($filters);
        $rows = $this->db()->fetchAll(
            'SELECT products.id FROM ' . self::PRODUCTS_TABLE . ' products' . $filterSql . ' ORDER BY products.id DESC',
            $filterParams
        );
        $productIds = array_values(array_map(static function (array $row): int {
            return (int) ($row['id'] ?? 0);
        }, $rows));

        $excludedIds = array_values(array_unique(array_filter(array_map(
            'intval',
            (array) $this->input('excluded_product_ids', array())
        ))));
        if ($excludedIds !== array()) {
            $productIds = array_values(array_diff($productIds, $excludedIds));
        }

        return array_values(array_filter($productIds));
    }

    private function createVariants(): void
    {
        $selectedComponents = array_values(array_filter(array_map('intval', (array) $this->input('components', array()))));
        $profit = (float) $this->input('profit', 0);
        if (count($selectedComponents) < 2) {
            $this->setFlash('error', json_encode(array('Wybierz co najmniej dwa komponenty.')));
            $this->redirect('./index.php?controller=computers&action=products');
        }

        $placeholders = implode(',', array_fill(0, count($selectedComponents), '?'));
        $componentsData = $this->db()->query(
            'SELECT * FROM ' . self::COMPONENTS_TABLE . ' WHERE id IN (' . $placeholders . ')',
            $selectedComponents
        )->fetchAll();

        $groupedComponents = array();
        foreach ($componentsData as $component) {
            $groupedComponents[(string) ($component['category'] ?? 'Inne')][] = $component;
        }

        $combinations = $this->cartesianProduct($groupedComponents);
        $created = 0;
        foreach ($combinations as $combination) {
            $idsForDb = array();
            $componentsByCategory = array();
            $priceSum = 0.0;
            foreach ($combination as $component) {
                $idsForDb[] = (int) $component['id'];
                $componentsByCategory[(string) ($component['category'] ?? '')] = (string) ($component['name_title'] ?? '');
                $priceSum += (float) ($component['price'] ?? 0);
            }

            sort($idsForDb);
            $idComponentsStr = implode(',', $idsForDb);
            $productNameParts = array('Komputer Gamingowy');
            foreach (array('Monitor', 'CPU', 'GPU', 'RAM') as $category) {
                if (!empty($componentsByCategory[$category])) {
                    $productNameParts[] = $componentsByCategory[$category];
                }
            }
            if (!empty($componentsByCategory['SSD'])) {
                $productNameParts[] = '/' . $componentsByCategory['SSD'];
            }
            $productNameParts[] = 'WIN11';
            $exists = (int) $this->db()->fetchColumn(
                'SELECT COUNT(*) FROM ' . self::PRODUCTS_TABLE . ' WHERE id_components = :id_components',
                array('id_components' => $idComponentsStr)
            );
            if ($exists > 0) {
                continue;
            }

            $this->db()->insert(self::PRODUCTS_TABLE, array(
                'id_components' => $idComponentsStr,
                'name' => trim(implode(' ', $productNameParts)),
                'price' => $priceSum + $profit,
                'profit' => $profit,
                'img' => '',
            ));
            $created++;
        }

        if ($created > 0) {
            $this->setFlash('success', 'Utworzono ' . $created . ' nowych wariantow produktow.');
        } else {
            $this->setFlash('error', json_encode(array('Nie utworzono zadnych nowych wariantow (wszystkie juz istnieja).')));
        }
        $this->redirect('./index.php?controller=computers&action=products');
    }

    private function saveProduct(int $productId): void
    {
        $products = isset($_POST['products']) && is_array($_POST['products']) ? $_POST['products'] : array();
        if (!isset($products[$productId]) || !is_array($products[$productId])) {
            $this->setFlash('error', json_encode(array('Nie znaleziono danych produktu do zapisu.')));
            $this->redirect('./index.php?controller=computers&action=products');
        }

        $data = $products[$productId];
        $componentsById = $this->componentsById();
        $selectedComponents = array_values(array_filter(array_map('intval', (array) ($data['components'] ?? array()))));
        $profit = (float) ($data['profit'] ?? 0);
        $ean = trim((string) ($data['EAN'] ?? ''));
        $priceSum = 0.0;
        foreach ($selectedComponents as $componentId) {
            if (isset($componentsById[$componentId])) {
                $priceSum += (float) ($componentsById[$componentId]['price'] ?? 0);
            }
        }

        $img = trim((string) ($data['img'] ?? ''));
        $imgMorele = trim((string) ($data['img_morele'] ?? ''));
        $imgEmpik = trim((string) ($data['img_empik'] ?? ''));
        $uploaded = $this->handleProductImageSet($productId, 'img_file', 'prod_');
        if ($uploaded !== null) {
            $img = $uploaded;
        }
        $uploadedMorele = $this->handleProductImageSet($productId, 'img_morele_file', 'prod_morele_');
        if ($uploadedMorele !== null) {
            $imgMorele = $uploadedMorele;
        }
        $uploadedEmpik = $this->handleProductImageSet($productId, 'img_empik_file', 'prod_empik_');
        if ($uploadedEmpik !== null) {
            $imgEmpik = $uploadedEmpik;
        }

        $this->db()->update(self::PRODUCTS_TABLE, array(
            'name' => trim((string) ($data['name'] ?? '')),
            'profit' => $profit,
            'EAN' => $ean,
            'id_components' => implode(',', $selectedComponents),
            'price' => $priceSum + $profit,
            'img' => $img,
            'img_morele' => $imgMorele,
            'img_empik' => $imgEmpik,
        ), 'id = :id', array('id' => $productId));

        $this->setFlash('success', 'Produkt zostal zapisany.');
        $this->redirect('./index.php?controller=computers&action=products');
    }

    private function handleProductsBulkAction(string $bulkAction, array $productIds): void
    {
        $componentsById = $this->componentsById();
        $successCount = 0;
        $errors = array();
        $successMessage = '';

        if ($bulkAction === 'delete') {
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $this->db()->query('DELETE FROM ' . self::PRODUCTS_TABLE . ' WHERE id IN (' . $placeholders . ')', $productIds);
            $successCount = count($productIds);
        } elseif ($bulkAction === 'change_profit') {
            $newProfit = (float) $this->input('bulk_profit', 0);
            foreach ($productIds as $productId) {
                $product = $this->productById($productId);
                if ($product === null) {
                    continue;
                }
                $componentIds = $this->csvIds((string) ($product['id_components'] ?? ''));
                $priceSum = $this->priceSumForComponents($componentIds, $componentsById);
                $this->db()->update(self::PRODUCTS_TABLE, array(
                    'profit' => $newProfit,
                    'price' => $priceSum + $newProfit,
                ), 'id = :id', array('id' => $productId));
                $successCount++;
            }
        } elseif ($bulkAction === 'replace_name') {
            $find = trim((string) $this->input('bulk_find', ''));
            $replace = trim((string) $this->input('bulk_replace', ''));
            if ($find === '') {
                $errors[] = 'Pole "Znajdz" nie moze byc puste.';
            } else {
                foreach ($productIds as $productId) {
                    $product = $this->productById($productId);
                    if ($product === null) {
                        continue;
                    }
                    $newName = str_ireplace($find, $replace, (string) ($product['name'] ?? ''));
                    $this->db()->update(self::PRODUCTS_TABLE, array('name' => $newName), 'id = :id', array('id' => $productId));
                    $successCount++;
                }
            }
        } elseif ($bulkAction === 'change_images') {
            $target = trim((string) $this->input('bulk_img_target', 'img'));
            if (!in_array($target, array('img', 'img_morele', 'img_empik'), true)) {
                $target = 'img';
            }
            $filename = $this->handleFlatUpload('bulk_img', $this->productUploadDir(), 'prod_bulk_');
            if ($filename === null) {
                $errors[] = 'Nie przeslano pliku obrazu do masowej zmiany.';
            } else {
                $idPlaceholders = array();
                $params = array('filename' => $filename);
                foreach ($productIds as $index => $productId) {
                    $placeholder = 'id_' . $index;
                    $idPlaceholders[] = ':' . $placeholder;
                    $params[$placeholder] = $productId;
                }
                $this->db()->query('UPDATE ' . self::PRODUCTS_TABLE . ' SET ' . $target . ' = :filename WHERE id IN (' . implode(',', $idPlaceholders) . ')', $params);
                $successCount = count($productIds);
            }
        } elseif ($bulkAction === 'set_ean') {
            $rows = array();
            foreach ($productIds as $productId) {
                $product = $this->productById($productId);
                if ($product === null) {
                    continue;
                }
                $rows[] = array(
                    $product['id'] ?? '',
                    $product['name'] ?? '',
                    $product['profit'] ?? '',
                    $product['EAN'] ?? '',
                );
            }
            $this->streamCsv('EAN_export_' . date('Ymd_His') . '.csv', array('IDENTITY', 'NAME', 'profit', 'EAN'), $rows);
        } elseif ($bulkAction === 'import_ean') {
            $this->importEanCsv();
            return;
        } elseif ($bulkAction === 'update_price') {
            $selectedMarketAccounts = $this->selectedMarketAccountFilters((array) $this->input('bulk_price_market_accounts', array()));
            if ($selectedMarketAccounts === array()) {
                $errors[] = 'Wybierz przynajmniej jedno konto marketplace do aktualizacji ceny.';
            }

            $marketQueuedCounts = array(
                'allegro' => 0,
                'empik' => 0,
                'erli' => 0,
                'morele' => 0,
            );
            foreach ($productIds as $productId) {
                if ($selectedMarketAccounts === array()) {
                    break;
                }
                $product = $this->productById($productId);
                if ($product === null) {
                    continue;
                }
                $queuedCounts = $this->queueMarketplacePriceUpdatesForProduct($product, $selectedMarketAccounts);
                $queuedTotal = array_sum($queuedCounts);
                if ($queuedTotal > 0) {
                    $successCount++;
                    foreach ($marketQueuedCounts as $market => $count) {
                        $marketQueuedCounts[$market] += (int) ($queuedCounts[$market] ?? 0);
                    }
                }
            }
            $totalQueued = array_sum($marketQueuedCounts);
            if ($totalQueued > 0) {
                $successMessage = 'Dodano do kolejki aktualizacji cen: ' . $successCount . ' produktow, Allegro: ' . $marketQueuedCounts['allegro'] . ', Empik: ' . $marketQueuedCounts['empik'] . ', Erli: ' . $marketQueuedCounts['erli'] . ', Morele: ' . $marketQueuedCounts['morele'] . ' ofert.';
            } elseif ($selectedMarketAccounts !== array()) {
                $successCount = 0;
                $errors[] = 'Nie znaleziono aktywnych ofert na wybranych kontach dla zaznaczonych produktow.';
            }
        } elseif (in_array($bulkAction, array('remove_component', 'replace_component', 'add_component'), true)) {
            $successCount = $this->handleProductComponentBulkChange($bulkAction, $productIds, $componentsById, $errors);
        } else {
            $errors[] = 'Nieznana akcja masowa.';
        }

        if ($successMessage !== '') {
            $this->setFlash('success', $successMessage);
        } elseif ($successCount > 0) {
            $this->setFlash('success', 'Akcja masowa zakonczona sukcesem. Zaktualizowano ' . $successCount . ' produktow.');
        }
        if ($errors !== array()) {
            $this->setFlash('error', json_encode($errors));
        }
        $this->redirect('./index.php?controller=computers&action=products');
    }

    private function handleProductComponentBulkChange(string $bulkAction, array $productIds, array $componentsById, array &$errors): int
    {
        $successCount = 0;
        $fromId = (int) $this->input('bulk_replace_from_id', 0);
        $toId = (int) $this->input('bulk_replace_to_id', 0);
        $removeId = (int) $this->input('bulk_remove_comp_id', 0);
        $addId = (int) $this->input('bulk_add_comp_id', 0);

        foreach ($productIds as $productId) {
            $product = $this->productById($productId);
            if ($product === null) {
                continue;
            }
            $componentIds = $this->csvIds((string) ($product['id_components'] ?? ''));

            if ($bulkAction === 'remove_component' && $removeId > 0) {
                $componentIds = array_values(array_diff($componentIds, array($removeId)));
            } elseif ($bulkAction === 'replace_component' && $fromId > 0 && $toId > 0) {
                $key = array_search($fromId, $componentIds, true);
                if ($key === false) {
                    continue;
                }
                $componentIds[$key] = $toId;
            } elseif ($bulkAction === 'add_component' && $addId > 0) {
                if (!in_array($addId, $componentIds, true)) {
                    $componentIds[] = $addId;
                    sort($componentIds);
                } else {
                    continue;
                }
            } else {
                $errors[] = 'Brakuje danych do zmiany komponentow.';
                return $successCount;
            }

            $priceSum = $this->priceSumForComponents($componentIds, $componentsById);
            $this->db()->update(self::PRODUCTS_TABLE, array(
                'id_components' => implode(',', $componentIds),
                'price' => $priceSum + (float) ($product['profit'] ?? 0),
            ), 'id = :id', array('id' => $productId));
            $successCount++;
        }

        return $successCount;
    }

    private function deleteProduct(int $productId): void
    {
        $this->requireModuleWrite('products');
        $this->db()->delete(self::PRODUCTS_TABLE, 'id = :id', array('id' => $productId));
        $this->setFlash('success', 'Produkt ID ' . $productId . ' zostal usuniety.');
        $this->redirect('./index.php?controller=computers&action=products');
    }

    private function handleComponentsPost(): void
    {
        if ($this->input('save_template', null) !== null) {
            $templateId = (int) $this->input('id_template', 0);
            $templateInfo = (string) $this->input('template_info', '');
            if ($templateId > 0) {
                $this->db()->update(self::TEMPLATES_TABLE, array('template' => $templateInfo), 'id_template = :id', array('id' => $templateId));
                $this->setFlash('success', 'Szablon zostal zapisany.');
            }
            $this->redirect('./index.php?controller=computers&action=components');
        }

        $bulkAction = trim((string) $this->input('bulk_action', ''));
        $componentIds = array_values(array_filter(array_map('intval', (array) $this->input('component_ids', array()))));
        if ($bulkAction !== '' && $componentIds !== array()) {
            $this->handleComponentsBulkAction($bulkAction, $componentIds);
        }

        $name = trim((string) $this->input('name', ''));
        if ($name !== '' || (int) $this->input('id', 0) > 0) {
            $this->saveComponent();
        }
    }

    private function handleComponentsBulkAction(string $bulkAction, array $componentIds): void
    {
        $updated = 0;
        if ($bulkAction === 'delete') {
            foreach ($componentIds as $componentId) {
                $this->deleteComponentFiles($componentId);
                $this->db()->delete(self::COMPONENTS_TABLE, 'id = :id', array('id' => $componentId));
                $updated++;
            }
            $this->setFlash('success', 'Usunieto ' . $updated . ' komponentow.');
            $this->redirect('./index.php?controller=computers&action=components');
        }

        $fieldMap = array(
            'assign_image' => array('file' => 'bulk_img', 'column' => 'img', 'prefix' => 'comp_bulk_'),
            'assign_image_morele' => array('file' => 'bulk_img_morele', 'column' => 'img_morele', 'prefix' => 'comp_morele_bulk_'),
            'assign_image_empik' => array('file' => 'bulk_img_empik', 'column' => 'img_empik', 'prefix' => 'comp_empik_bulk_'),
        );
        if (!isset($fieldMap[$bulkAction])) {
            $this->setFlash('error', json_encode(array('Nieznana akcja masowa komponentow.')));
            $this->redirect('./index.php?controller=computers&action=components');
        }

        $files = $this->handleMultipleFlatUploads($fieldMap[$bulkAction]['file'], $this->componentUploadDir(), $fieldMap[$bulkAction]['prefix']);
        if ($files === array()) {
            $this->setFlash('error', json_encode(array('Nie przeslano zadnych plikow.')));
            $this->redirect('./index.php?controller=computers&action=components');
        }

        $fieldValue = implode(',', array_slice($files, 0, 16));
        foreach ($componentIds as $componentId) {
            $row = $this->db()->fetch('SELECT * FROM ' . self::COMPONENTS_TABLE . ' WHERE id = :id', array('id' => $componentId));
            if (!is_array($row)) {
                continue;
            }
            $this->deleteImageList((string) ($row[$fieldMap[$bulkAction]['column']] ?? ''), $this->componentUploadDir());
            $this->db()->update(self::COMPONENTS_TABLE, array(
                $fieldMap[$bulkAction]['column'] => $fieldValue,
            ), 'id = :id', array('id' => $componentId));
            $updated++;
        }

        $this->setFlash('success', 'Zaktualizowano ' . $updated . ' komponentow.');
        $this->redirect('./index.php?controller=computers&action=components');
    }

    private function saveComponent(): void
    {
        $id = (int) $this->input('id', 0);
        $name = trim((string) $this->input('name', ''));
        if ($name === '') {
            $this->setFlash('error', json_encode(array('Pole "Nazwa" jest wymagane.')));
            $this->redirect('./index.php?controller=computers&action=components' . ($id > 0 ? '&edit_id=' . $id : ''));
        }

        $existingComponent = null;
        if ($id > 0) {
            $row = $this->db()->fetch('SELECT * FROM ' . self::COMPONENTS_TABLE . ' WHERE id = :id', array('id' => $id));
            $existingComponent = is_array($row) ? $this->normalizeComponentTextFields($row) : null;
        }

        $oldImg = trim((string) $this->input('img_old', ''));
        $oldImgMorele = trim((string) $this->input('img_morele_old', ''));
        $oldImgEmpik = trim((string) $this->input('img_empik_old', ''));
        $img = $this->mergeComponentImages($oldImg, (array) $this->input('remove_img', array()), 'img_file', 'comp_', $id);
        $imgMorele = $this->mergeComponentImages($oldImgMorele, (array) $this->input('remove_img_morele', array()), 'img_file_morele', 'comp_morele_', $id);
        $imgEmpik = $this->mergeComponentImages($oldImgEmpik, (array) $this->input('remove_img_empik', array()), 'img_file_empik', 'comp_empik_', $id);

        $payload = array(
            'name' => $name,
            'name_title' => trim((string) $this->input('name_title', '')),
            'price' => (float) $this->input('price', 0),
            'description' => trim((string) $this->input('description', '')),
            'description_morele' => trim((string) $this->input('description_morele', '')),
            'description_empik' => trim((string) $this->input('description_empik', '')),
            'parameters_eu' => $this->postedComponentParamsJson(
                'params_eu_loaded',
                $this->collectMarketParams((array) $this->input('param', array()), (array) $this->input('param_type', array())),
                $existingComponent,
                'parameters_eu'
            ),
            'parameters_morele' => $this->postedComponentParamsJson(
                'params_morele_loaded',
                $this->collectMarketParams((array) $this->input('morele_param', array()), (array) $this->input('morele_param_type', array())),
                $existingComponent,
                'parameters_morele'
            ),
            'parameters_empik' => $this->postedComponentParamsJson(
                'params_empik_loaded',
                $this->collectEmpikParams(),
                $existingComponent,
                'parameters_empik'
            ),
            'name_spec' => trim((string) $this->input('name_spec', '')),
            'img' => $img,
            'img_morele' => $imgMorele,
            'img_empik' => $imgEmpik,
            'category' => trim((string) $this->input('category', '')),
        );

        if ($id > 0) {
            $this->db()->update(self::COMPONENTS_TABLE, $payload, 'id = :id', array('id' => $id));
            $componentId = $id;
            $message = 'Rekord zostal zaktualizowany.';
        } else {
            $componentId = (int) $this->db()->insert(self::COMPONENTS_TABLE, $payload);
            $message = 'Nowy rekord zostal dodany.';
        }

        $updatedProducts = $this->refreshPricesForProductsUsingComponent($componentId);
        if ($updatedProducts > 0) {
            $message .= ' Przeliczono ceny magazynu dla produktow: ' . $updatedProducts . '.';
        }
        $this->setFlash('success', $message);
        $this->redirect('./index.php?controller=computers&action=components');
    }

    private function deleteComponent(int $componentId): void
    {
        $this->requireModuleWrite('products');
        $this->deleteComponentFiles($componentId);
        $this->db()->delete(self::COMPONENTS_TABLE, 'id = :id', array('id' => $componentId));
        $this->setFlash('success', 'Komponent zostal usuniety.');
        $this->redirect('./index.php?controller=computers&action=components');
    }

    private function renderComponentParams(string $which): void
    {
        $this->requireModule('products');
        $editId = (int) $this->input('edit_id', 0);
        $componentCategory = trim((string) $this->input('component_category', ''));
        $product = array();
        if ($editId > 0) {
            $row = $this->db()->fetch('SELECT * FROM ' . self::COMPONENTS_TABLE . ' WHERE id = :id', array('id' => $editId));
            if (is_array($row)) {
                $row = $this->normalizeComponentTextFields($row);
                $product = $this->hydrateComponentParameterMaps($row);
                if ($componentCategory === '') {
                    $componentCategory = trim((string) ($row['category'] ?? ''));
                }
            }
        }

        if ($which === 'empik') {
            $payload = $this->loadEmpikParameterPayload($componentCategory);
            $this->partial('computers/partials/params_empik', array(
                'product' => $product,
                'empik_parameters' => $payload['items'],
                'empik_parameters_error' => $payload['error'],
                'empik_parameters_meta' => $payload['meta'],
            ));
            return;
        }
        if ($which === 'eu') {
            $payload = $this->loadEuParameterPayload($componentCategory);
            $this->partial('computers/partials/params_eu', array(
                'product' => $product,
                'parameters' => $payload['items'],
                'parameters_error' => $payload['error'],
                'parameters_meta' => $payload['meta'],
            ));
            return;
        }
        if ($which === 'morele') {
            $payload = $this->loadMoreleParameterPayload($componentCategory);
            $this->partial('computers/partials/params_morele', array(
                'product' => $product,
                'morele_parameters' => $payload['items'],
                'morele_parameters_error' => $payload['error'],
                'morele_parameters_meta' => $payload['meta'],
            ));
            return;
        }

        http_response_code(404);
        echo 'Nieznany zestaw parametrow.';
    }

    private function partial(string $template, array $data): void
    {
        $smarty = \App\Core\SmartyFactory::create();
        foreach ($data as $key => $value) {
            $smarty->assign($key, $value);
        }
        $smarty->display($template . '.tpl');
        exit;
    }

    private function componentsById(): array
    {
        $rows = $this->db()->fetchAll('SELECT * FROM ' . self::COMPONENTS_TABLE);
        $result = array();
        foreach ($rows as $row) {
            $result[(int) ($row['id'] ?? 0)] = $row;
        }
        return $result;
    }

    private function productById(int $productId): ?array
    {
        $product = $this->db()->fetch('SELECT * FROM ' . self::PRODUCTS_TABLE . ' WHERE id = :id', array('id' => $productId));
        return is_array($product) ? $this->normalizeProductImageFields($product) : null;
    }

    private function queueMarketplacePriceUpdatesForProduct(array $product, array $selectedMarketAccounts): array
    {
        $attachedProducts = $this->attachActiveMoreleOffers($this->attachActiveErliProducts($this->attachActiveEmpikOffers($this->attachActiveAllegroOffers(array($product)))));
        $attachedProduct = isset($attachedProducts[0]) && is_array($attachedProducts[0]) ? $attachedProducts[0] : $product;

        return array(
            'allegro' => $this->queueAllegroPriceUpdatesForProduct($attachedProduct, $selectedMarketAccounts),
            'empik' => $this->queueEmpikPriceUpdatesForProduct($attachedProduct, $selectedMarketAccounts),
            'erli' => $this->queueErliPriceUpdatesForProduct($attachedProduct, $selectedMarketAccounts),
            'morele' => $this->queueMorelePriceUpdatesForProduct($attachedProduct, $selectedMarketAccounts),
        );
    }

    private function queueAllegroPriceUpdatesForProduct(array $product, array $selectedMarketAccounts = array()): int
    {
        $offers = isset($product['allegro_accounts']) && is_array($product['allegro_accounts'])
            ? $product['allegro_accounts']
            : array();

        if ($offers === array()) {
            return 0;
        }

        $price = $this->normalizeQueuePrice($product['price'] ?? null);
        if ($price === null) {
            return 0;
        }

        $targets = array();
        foreach ($offers as $offer) {
            if (!is_array($offer)) {
                continue;
            }

            $offerRowId = (int) ($offer['offer_row_id'] ?? 0);
            $accountId = (int) ($offer['account_id'] ?? 0);
            $offerId = trim((string) ($offer['offer_id'] ?? ''));
            if ($offerRowId <= 0 || $accountId <= 0 || $offerId === '') {
                continue;
            }
            if ($selectedMarketAccounts !== array() && !in_array('allegro:' . $accountId, $selectedMarketAccounts, true)) {
                continue;
            }

            $targets[] = array(
                'id' => $offerRowId,
                'account_id' => $accountId,
                'offer_id' => $offerId,
            );
        }

        if ($targets === array()) {
            return 0;
        }

        $storage = new AllegroStorageRepository($this->db());
        $storage->ensureSchema();
        return $storage->enqueueOfferChanges($targets, 'set_price', array('value' => $price), null, true);
    }

    private function queueEmpikPriceUpdatesForProduct(array $product, array $selectedMarketAccounts = array()): int
    {
        $offers = isset($product['empik_accounts']) && is_array($product['empik_accounts'])
            ? $product['empik_accounts']
            : array();

        if ($offers === array()) {
            return 0;
        }

        $price = $this->normalizeQueuePrice($product['price'] ?? null);
        if ($price === null) {
            return 0;
        }

        $targets = array();
        foreach ($offers as $offer) {
            if (!is_array($offer)) {
                continue;
            }

            $offerRowId = (int) ($offer['offer_row_id'] ?? 0);
            $accountId = (int) ($offer['account_id'] ?? 0);
            if ($offerRowId <= 0 || $accountId <= 0) {
                continue;
            }
            if ($selectedMarketAccounts !== array() && !in_array('empik:' . $accountId, $selectedMarketAccounts, true)) {
                continue;
            }

            $targets[] = array(
                'id' => $offerRowId,
                'account_id' => $accountId,
            );
        }

        if ($targets === array()) {
            return 0;
        }

        $storage = new EmpikStorageRepository($this->db());
        $storage->ensureSchema();
        return $storage->enqueueOfferChanges($targets, 'set_price', array('value' => $price));
    }

    private function queueErliPriceUpdatesForProduct(array $product, array $selectedMarketAccounts = array()): int
    {
        $offers = isset($product['erli_accounts']) && is_array($product['erli_accounts'])
            ? $product['erli_accounts']
            : array();

        if ($offers === array()) {
            return 0;
        }

        $price = $this->normalizeQueuePrice($product['price'] ?? null);
        if ($price === null) {
            return 0;
        }

        $targets = array();
        foreach ($offers as $offer) {
            if (!is_array($offer)) {
                continue;
            }

            $productRowId = (int) ($offer['product_row_id'] ?? 0);
            $accountId = (int) ($offer['account_id'] ?? 0);
            if ($productRowId <= 0 || $accountId <= 0) {
                continue;
            }
            if ($selectedMarketAccounts !== array() && !in_array('erli:' . $accountId, $selectedMarketAccounts, true)) {
                continue;
            }

            $targets[] = array(
                'id' => $productRowId,
                'account_id' => $accountId,
            );
        }

        if ($targets === array()) {
            return 0;
        }

        $storage = new ErliStorageRepository($this->db());
        $storage->ensureSchema();
        return $storage->enqueueProductChanges($targets, 'set_price', array('value' => $price), null, true);
    }

    private function queueMorelePriceUpdatesForProduct(array $product, array $selectedMarketAccounts = array()): int
    {
        $offers = isset($product['morele_accounts']) && is_array($product['morele_accounts'])
            ? $product['morele_accounts']
            : array();

        if ($offers === array()) {
            return 0;
        }

        $price = $this->normalizeQueuePrice($product['price'] ?? null);
        if ($price === null) {
            return 0;
        }

        $targets = array();
        foreach ($offers as $offer) {
            if (!is_array($offer)) {
                continue;
            }

            $offerRowId = (int) ($offer['offer_row_id'] ?? 0);
            $accountId = (int) ($offer['account_id'] ?? 0);
            if ($offerRowId <= 0 || $accountId <= 0) {
                continue;
            }
            if ($selectedMarketAccounts !== array() && !in_array('morele:' . $accountId, $selectedMarketAccounts, true)) {
                continue;
            }

            $targets[] = array('id' => $offerRowId, 'account_id' => $accountId);
        }

        if ($targets === array()) {
            return 0;
        }

        $storage = new MoreleStorageRepository($this->db());
        $storage->ensureSchema();
        return $storage->enqueueOfferChanges($targets, 'set_price', array('value' => $price), null, true);
    }

    private function normalizeQueuePrice($value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $normalized = str_replace(',', '.', trim((string) $value));
        if (!is_numeric($normalized)) {
            return null;
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    private function normalizeProductImageFields(array $product): array
    {
        foreach (array('img', 'img_morele', 'img_empik') as $field) {
            if (array_key_exists($field, $product)) {
                $product[$field] = trim((string) $product[$field]);
            }
        }

        return $product;
    }

    private function selectedMarketAccountFilters(array $values): array
    {
        $selected = array();
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value === '1' || $value === '0' || preg_match('/^(allegro|empik|erli|morele):\d+$/', $value) === 1) {
                $selected[] = $value;
            }
        }

        return array_values(array_unique($selected));
    }

    private function computerProductFilterSql(array $filters): array
    {
        $where = array();
        $params = array();
        $name = trim((string) ($filters['name'] ?? ''));
        if ($name !== '') {
            $where[] = 'products.name LIKE :computer_filter_name';
            $params['computer_filter_name'] = '%' . $name . '%';
        }

        $componentIds = array_values(array_unique(array_filter(array_map(
            'intval',
            (array) ($filters['components'] ?? array())
        ))));
        foreach ($componentIds as $index => $componentId) {
            $key = 'computer_filter_component_' . $index;
            $where[] = 'FIND_IN_SET(:' . $key . ', products.id_components) > 0';
            $params[$key] = (string) $componentId;
        }

        $marketFilters = $this->selectedMarketAccountFilters((array) ($filters['market_accounts'] ?? array()));
        if ($marketFilters !== array()) {
            $hasAllegroTables = $this->tableExists('allegro_offers') && $this->tableExists('allegro_accounts');
            $hasEmpikTables = $this->tableExists('empik_offers') && $this->tableExists('empik_accounts');
            $hasErliTables = $this->tableExists('erli_products') && $this->tableExists('erli_accounts');
            $hasMoreleTables = $this->tableExists('morele_offers');
            $skuMatch = '(market_items.sku = products.sku'
                . ' OR market_items.sku = CAST(products.offerid AS CHAR)'
                . ' OR market_items.sku = CAST(products.id AS CHAR)'
                . " OR market_items.sku = CONCAT('ALTREO_', products.id)"
                . " OR market_items.sku = CONCAT('ALTREO_', products.offerid))";
            $moreleSkuMatch = '(market_items.sku COLLATE utf8mb4_unicode_ci = products.sku COLLATE utf8mb4_unicode_ci'
                . ' OR market_items.sku COLLATE utf8mb4_unicode_ci = CAST(products.offerid AS CHAR) COLLATE utf8mb4_unicode_ci'
                . ' OR market_items.sku COLLATE utf8mb4_unicode_ci = CAST(products.id AS CHAR) COLLATE utf8mb4_unicode_ci'
                . " OR market_items.sku COLLATE utf8mb4_unicode_ci = CONCAT('ALTREO_', products.id) COLLATE utf8mb4_unicode_ci"
                . " OR market_items.sku COLLATE utf8mb4_unicode_ci = CONCAT('ALTREO_', products.offerid) COLLATE utf8mb4_unicode_ci)";
            $empikSkuMatch = "(market_items.shop_sku = CONCAT('ALTREO_', products.id)"
                . " OR market_items.product_sku = CONCAT('ALTREO_', products.id))";
            $allegroExists = $hasAllegroTables
                ? 'EXISTS (SELECT 1 FROM allegro_offers market_items'
                    . ' INNER JOIN allegro_accounts market_accounts ON market_accounts.id = market_items.account_id'
                    . " WHERE market_items.publication_status = 'ACTIVE' AND market_accounts.is_active = 1"
                    . ' AND ' . $skuMatch . ')'
                : '0 = 1';
            $empikExists = $hasEmpikTables
                ? 'EXISTS (SELECT 1 FROM empik_offers market_items'
                    . ' INNER JOIN empik_accounts market_accounts ON market_accounts.id = market_items.account_id'
                    . ' WHERE market_items.active = 1 AND market_accounts.is_active = 1'
                    . ' AND ' . $empikSkuMatch . ')'
                : '0 = 1';
            $erliExists = $hasErliTables
                ? 'EXISTS (SELECT 1 FROM erli_products market_items'
                    . ' INNER JOIN erli_accounts market_accounts ON market_accounts.id = market_items.account_id'
                    . ' WHERE market_accounts.is_active = 1'
                    . " AND (CASE"
                    . " WHEN market_items.status_override IS NOT NULL AND market_items.status_override <> '' THEN LOWER(market_items.status_override)"
                    . " WHEN market_items.remote_status IS NOT NULL AND market_items.remote_status <> '' THEN LOWER(market_items.remote_status)"
                    . " WHEN COALESCE(market_items.stock_override, market_items.quantity, 0) > 0 THEN 'active'"
                    . " ELSE 'inactive' END) = 'active'"
                    . ' AND ' . $skuMatch . ')'
                : '0 = 1';
            $moreleExists = $hasMoreleTables
                ? 'EXISTS (SELECT 1 FROM morele_offers market_items'
                    . ' WHERE market_items.active = 1'
                    . ' AND ' . $moreleSkuMatch . ')'
                : '0 = 1';

            $marketConditions = array();
            foreach ($marketFilters as $index => $marketFilter) {
                if ($marketFilter === '1') {
                    $marketConditions[] = '(' . $allegroExists . ' OR ' . $empikExists . ' OR ' . $erliExists . ' OR ' . $moreleExists . ')';
                    continue;
                }
                if ($marketFilter === '0') {
                    $marketConditions[] = '(NOT (' . $allegroExists . ') AND NOT (' . $empikExists . ') AND NOT (' . $erliExists . ') AND NOT (' . $moreleExists . '))';
                    continue;
                }

                list($market, $accountId) = explode(':', $marketFilter, 2);
                $key = 'computer_filter_market_account_' . $index;
                $params[$key] = (int) $accountId;
                if ($market === 'allegro' && $hasAllegroTables) {
                    $marketConditions[] = substr($allegroExists, 0, -1)
                        . ' AND market_items.account_id = :' . $key . ')';
                } elseif ($market === 'empik' && $hasEmpikTables) {
                    $marketConditions[] = substr($empikExists, 0, -1)
                        . ' AND market_items.account_id = :' . $key . ')';
                } elseif ($market === 'erli' && $hasErliTables) {
                    $marketConditions[] = substr($erliExists, 0, -1)
                        . ' AND market_items.account_id = :' . $key . ')';
                } elseif ($market === 'morele' && $hasMoreleTables) {
                    $marketConditions[] = substr($moreleExists, 0, -1)
                        . ' AND market_items.account_id = :' . $key . ')';
                } else {
                    $marketConditions[] = '0 = 1';
                }
            }

            if ($marketConditions !== array()) {
                $where[] = '(' . implode(' OR ', $marketConditions) . ')';
            }
        }

        return array(
            $where === array() ? '' : ' WHERE ' . implode(' AND ', $where),
            $params,
        );
    }

    private function productMatchesMarketAccountFilters(array $product, array $filters): bool
    {
        $hasAnyOffer = in_array('1', $filters, true);
        $hasNoOffer = in_array('0', $filters, true);
        $hasActiveOffer = false;

        foreach ((array) ($product['allegro_accounts'] ?? array()) as $account) {
            if (!is_array($account)) {
                continue;
            }
            $hasActiveOffer = true;
            if (in_array('allegro:' . (int) ($account['account_id'] ?? 0), $filters, true)) {
                return true;
            }
        }

        foreach ((array) ($product['erli_accounts'] ?? array()) as $account) {
            if (!is_array($account)) {
                continue;
            }
            $hasActiveOffer = true;
            if (in_array('erli:' . (int) ($account['account_id'] ?? 0), $filters, true)) {
                return true;
            }
        }

        foreach ((array) ($product['empik_accounts'] ?? array()) as $account) {
            if (!is_array($account)) {
                continue;
            }
            $hasActiveOffer = true;
            if (in_array('empik:' . (int) ($account['account_id'] ?? 0), $filters, true)) {
                return true;
            }
        }

        foreach ((array) ($product['morele_accounts'] ?? array()) as $account) {
            if (!is_array($account)) {
                continue;
            }
            $hasActiveOffer = true;
            if (in_array('morele:' . (int) ($account['account_id'] ?? 0), $filters, true)) {
                return true;
            }
        }

        if ($hasAnyOffer && $hasActiveOffer) {
            return true;
        }

        return $hasNoOffer && !$hasActiveOffer;
    }

    private function markSelectedMarketAccounts(array $accounts, string $market, array $filters): array
    {
        foreach ($accounts as $index => $account) {
            $value = $market . ':' . (int) ($account['id'] ?? 0);
            $accounts[$index]['filter_value'] = $value;
            $accounts[$index]['selected'] = in_array($value, $filters, true);
        }

        return $accounts;
    }

    private function activeComputerAllegroAccounts(): array
    {
        if (!$this->tableExists('allegro_accounts')) {
            return array();
        }

        return $this->db()->fetchAll('SELECT id, name, slug FROM allegro_accounts WHERE is_active = 1 ORDER BY name ASC, id ASC');
    }

    private function activeComputerErliAccounts(): array
    {
        if (!$this->tableExists('erli_accounts')) {
            return array();
        }

        return $this->db()->fetchAll('SELECT id, name, slug FROM erli_accounts WHERE is_active = 1 ORDER BY name ASC, id ASC');
    }

    private function activeComputerEmpikAccounts(): array
    {
        if (!$this->tableExists('empik_accounts')) {
            return array();
        }

        return $this->db()->fetchAll('SELECT id, name, slug FROM empik_accounts WHERE is_active = 1 ORDER BY name ASC, id ASC');
    }

    private function activeComputerMoreleAccounts(): array
    {
        $storage = new MoreleStorageRepository($this->db());
        $storage->ensureSchema();
        if (!$this->tableExists('morele_offers')) {
            return array();
        }

        $accountName = $this->settings ? trim((string) $this->settings->get('morele_account', '')) : '';
        return array(array(
            'id' => 1,
            'name' => $accountName !== '' ? $accountName : 'ALTREO',
            'slug' => 'morele',
        ));
    }

    private function attachActiveAllegroOffers(array $products): array
    {
        foreach ($products as $index => $product) {
            if (is_array($product)) {
                $products[$index]['offerid'] = '';
                $products[$index]['price_allegro'] = null;
                $products[$index]['allegro_accounts'] = array();
            }
        }

        if ($products === array() || !$this->tableExists('allegro_offers') || !$this->tableExists('allegro_accounts')) {
            return $products;
        }

        $skuMap = array();
        foreach ($products as $index => $product) {
            if (!is_array($product)) {
                continue;
            }

            foreach ($this->allegroSkuCandidatesForComputerProduct($product) as $sku) {
                if (!isset($skuMap[$sku])) {
                    $skuMap[$sku] = array();
                }
                $skuMap[$sku][] = $index;
            }
        }

        if ($skuMap === array()) {
            return $products;
        }

        $attachedAccra = array();
        $attachedOffers = array();
        foreach (array_chunk(array_keys($skuMap), 500) as $skuChunk) {
            $params = array();
            $placeholders = array();
            foreach ($skuChunk as $index => $sku) {
                $key = 'sku_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $sku;
            }

            $rows = $this->db()->fetchAll(
                'SELECT offers.id AS offer_row_id, offers.account_id, offers.offer_id, offers.sku, offers.price_amount, offers.price_currency, offers.publication_status, offers.last_synced_at, accounts.name AS account_name, accounts.slug AS account_slug'
                . ' FROM allegro_offers offers'
                . ' INNER JOIN allegro_accounts accounts ON accounts.id = offers.account_id'
                . ' WHERE offers.publication_status = :publication_status'
                . ' AND accounts.is_active = 1'
                . ' AND offers.sku IN (' . implode(',', $placeholders) . ')'
                . ' ORDER BY accounts.name ASC, offers.updated_at DESC, offers.id DESC',
                array_merge(array(
                    'publication_status' => 'ACTIVE',
                ), $params)
            );

            foreach ($rows as $row) {
                $sku = trim((string) ($row['sku'] ?? ''));
                if ($sku === '' || empty($skuMap[$sku])) {
                    continue;
                }

                foreach ($skuMap[$sku] as $productIndex) {
                    $offerKey = $productIndex . ':' . (string) ($row['account_slug'] ?? '') . ':' . (string) ($row['offer_id'] ?? '');
                    if (!isset($attachedOffers[$offerKey])) {
                        $products[$productIndex]['allegro_accounts'][] = array(
                            'offer_row_id' => (int) ($row['offer_row_id'] ?? 0),
                            'account_id' => (int) ($row['account_id'] ?? 0),
                            'account_name' => (string) ($row['account_name'] ?? ''),
                            'account_slug' => (string) ($row['account_slug'] ?? ''),
                            'offer_id' => trim((string) ($row['offer_id'] ?? '')),
                            'price_amount' => $row['price_amount'] !== null ? (float) $row['price_amount'] : null,
                            'sku' => $sku,
                            'last_synced_at' => (string) ($row['last_synced_at'] ?? ''),
                        );
                        $attachedOffers[$offerKey] = true;
                    }

                    if (!isset($attachedAccra[$productIndex]) && $this->isAccraAllegroAccount($row)) {
                        $products[$productIndex]['offerid'] = trim((string) ($row['offer_id'] ?? ''));
                        $products[$productIndex]['price_allegro'] = $row['price_amount'] !== null ? (float) $row['price_amount'] : null;
                        $products[$productIndex]['allegro_sku'] = $sku;
                        $products[$productIndex]['allegro_account_name'] = (string) ($row['account_name'] ?? '');
                        $products[$productIndex]['allegro_publication_status'] = (string) ($row['publication_status'] ?? '');
                        $products[$productIndex]['allegro_last_synced_at'] = (string) ($row['last_synced_at'] ?? '');
                        $attachedAccra[$productIndex] = true;
                    }
                }
            }
        }

        return $products;
    }

    private function isAccraAllegroAccount(array $row): bool
    {
        foreach (array($row['account_slug'] ?? '', $row['account_name'] ?? '') as $value) {
            $normalized = strtolower(str_replace(array('-', ' '), '_', trim((string) $value)));
            if ($normalized === 'accra_shop') {
                return true;
            }
        }

        return false;
    }

    private function attachActiveErliProducts(array $products): array
    {
        foreach ($products as $index => $product) {
            if (is_array($product)) {
                $products[$index]['erli_accounts'] = array();
            }
        }

        if ($products === array() || !$this->tableExists('erli_products') || !$this->tableExists('erli_accounts')) {
            return $products;
        }

        $storage = new ErliStorageRepository($this->db());
        $storage->ensureSchema();

        $skuMap = array();
        foreach ($products as $index => $product) {
            if (!is_array($product)) {
                continue;
            }

            foreach ($this->allegroSkuCandidatesForComputerProduct($product) as $sku) {
                if (!isset($skuMap[$sku])) {
                    $skuMap[$sku] = array();
                }
                $skuMap[$sku][] = $index;
            }
        }

        if ($skuMap === array()) {
            return $products;
        }

        $attachedProducts = array();
        foreach (array_chunk(array_keys($skuMap), 500) as $skuChunk) {
            $params = array();
            $placeholders = array();
            foreach ($skuChunk as $index => $sku) {
                $key = 'erli_sku_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $sku;
            }

            $rows = $this->db()->fetchAll(
                'SELECT products.id AS product_row_id, products.account_id, products.external_id, products.sku, products.marketplace_id, products.payload_json,'
                . ' COALESCE(products.price_override, products.price) AS effective_price,'
                . ' CASE'
                . ' WHEN products.status_override IS NOT NULL AND products.status_override <> "" THEN LOWER(products.status_override)'
                . ' WHEN products.remote_status IS NOT NULL AND products.remote_status <> "" THEN LOWER(products.remote_status)'
                . ' WHEN COALESCE(products.stock_override, products.quantity, 0) > 0 THEN "active"'
                . ' ELSE "inactive" END AS effective_status,'
                . ' products.last_synced_at, accounts.name AS account_name, accounts.slug AS account_slug'
                . ' FROM erli_products products'
                . ' INNER JOIN erli_accounts accounts ON accounts.id = products.account_id'
                . ' WHERE accounts.is_active = 1'
                . ' AND products.sku IN (' . implode(',', $placeholders) . ')'
                . " HAVING effective_status = 'active'"
                . ' ORDER BY accounts.name ASC, products.updated_at DESC, products.id DESC',
                $params
            );

            foreach ($rows as $row) {
                $sku = trim((string) ($row['sku'] ?? ''));
                if ($sku === '' || empty($skuMap[$sku])) {
                    continue;
                }

                foreach ($skuMap[$sku] as $productIndex) {
                    $productKey = $productIndex . ':' . (string) ($row['account_slug'] ?? '') . ':' . (string) ($row['external_id'] ?? '');
                    if (isset($attachedProducts[$productKey])) {
                        continue;
                    }

                    $products[$productIndex]['erli_accounts'][] = array(
                        'product_row_id' => (int) ($row['product_row_id'] ?? 0),
                        'account_id' => (int) ($row['account_id'] ?? 0),
                        'account_name' => (string) ($row['account_name'] ?? ''),
                        'account_slug' => (string) ($row['account_slug'] ?? ''),
                        'external_id' => trim((string) ($row['external_id'] ?? '')),
                        'price_amount' => $this->normalizeErliDisplayPrice($row['effective_price'] ?? null),
                        'sku' => $sku,
                        'erli_url' => $this->erliPublicProductUrl($row),
                        'last_synced_at' => (string) ($row['last_synced_at'] ?? ''),
                    );
                    $attachedProducts[$productKey] = true;
                }
            }
        }

        return $products;
    }

    private function attachActiveMoreleOffers(array $products): array
    {
        foreach ($products as $index => $product) {
            if (is_array($product)) {
                $products[$index]['morele_accounts'] = array();
            }
        }

        $storage = new MoreleStorageRepository($this->db());
        $storage->ensureSchema();
        if ($products === array() || !$this->tableExists('morele_offers')) {
            return $products;
        }

        $skuMap = array();
        foreach ($products as $index => $product) {
            if (!is_array($product)) {
                continue;
            }

            foreach ($this->allegroSkuCandidatesForComputerProduct($product) as $sku) {
                if (!isset($skuMap[$sku])) {
                    $skuMap[$sku] = array();
                }
                $skuMap[$sku][] = $index;
            }
        }

        if ($skuMap === array()) {
            return $products;
        }

        $attachedOffers = array();
        foreach (array_chunk(array_keys($skuMap), 500) as $skuChunk) {
            $params = array();
            $placeholders = array();
            foreach ($skuChunk as $index => $sku) {
                $key = 'morele_sku_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $sku;
            }

            $rows = $this->db()->fetchAll(
                'SELECT id AS offer_row_id, account_id, account_name, external_id, sku,'
                . ' COALESCE(price_override, price) AS effective_price,'
                . ' COALESCE(stock_override, quantity) AS effective_quantity,'
                . ' last_synced_at'
                . ' FROM morele_offers'
                . ' WHERE active = 1 AND sku IN (' . implode(',', $placeholders) . ')'
                . ' ORDER BY account_name ASC, updated_at DESC, id DESC',
                $params
            );

            foreach ($rows as $row) {
                $sku = trim((string) ($row['sku'] ?? ''));
                if ($sku === '' || empty($skuMap[$sku])) {
                    continue;
                }

                foreach ($skuMap[$sku] as $productIndex) {
                    $offerKey = $productIndex . ':morele:' . (string) ($row['external_id'] ?? '');
                    if (isset($attachedOffers[$offerKey])) {
                        continue;
                    }

                    $products[$productIndex]['morele_accounts'][] = array(
                        'offer_row_id' => (int) ($row['offer_row_id'] ?? 0),
                        'account_id' => (int) ($row['account_id'] ?? 1),
                        'account_name' => (string) ($row['account_name'] ?? 'ALTREO'),
                        'account_slug' => 'morele',
                        'external_id' => trim((string) ($row['external_id'] ?? '')),
                        'price_amount' => $row['effective_price'] !== null ? (float) $row['effective_price'] : null,
                        'quantity' => $row['effective_quantity'] !== null ? (int) $row['effective_quantity'] : null,
                        'sku' => $sku,
                        'morele_url' => './index.php?controller=morele&action=offer&id=' . (int) ($row['offer_row_id'] ?? 0),
                        'last_synced_at' => (string) ($row['last_synced_at'] ?? ''),
                    );
                    $attachedOffers[$offerKey] = true;
                }
            }
        }

        return $products;
    }

    private function attachActiveEmpikOffers(array $products): array
    {
        foreach ($products as $index => $product) {
            if (is_array($product)) {
                $products[$index]['empik_accounts'] = array();
            }
        }

        if ($products === array() || !$this->tableExists('empik_offers') || !$this->tableExists('empik_accounts')) {
            return $products;
        }

        $skuMap = array();
        foreach ($products as $index => $product) {
            if (!is_array($product)) {
                continue;
            }

            foreach ($this->empikSkuCandidatesForComputerProduct($product) as $sku) {
                if (!isset($skuMap[$sku])) {
                    $skuMap[$sku] = array();
                }
                $skuMap[$sku][] = $index;
            }
        }

        if ($skuMap === array()) {
            return $products;
        }

        $attachedOffers = array();
        foreach (array_chunk(array_keys($skuMap), 500) as $skuChunk) {
            $params = array();
            $shopSkuPlaceholders = array();
            $productSkuPlaceholders = array();
            foreach ($skuChunk as $index => $sku) {
                $shopKey = 'empik_shop_sku_' . $index;
                $productKey = 'empik_product_sku_' . $index;
                $shopSkuPlaceholders[] = ':' . $shopKey;
                $productSkuPlaceholders[] = ':' . $productKey;
                $params[$shopKey] = $sku;
                $params[$productKey] = $sku;
            }

            $rows = $this->db()->fetchAll(
                'SELECT offers.id AS offer_row_id, offers.account_id, offers.offer_id, offers.shop_sku, offers.product_sku, offers.product_title,'
                . ' offers.price, offers.total_price, offers.currency_iso_code, offers.quantity, offers.last_synced_at, accounts.name AS account_name, accounts.slug AS account_slug'
                . ' FROM empik_offers offers'
                . ' INNER JOIN empik_accounts accounts ON accounts.id = offers.account_id'
                . ' WHERE offers.active = 1'
                . ' AND accounts.is_active = 1'
                . ' AND (offers.shop_sku IN (' . implode(',', $shopSkuPlaceholders) . ') OR offers.product_sku IN (' . implode(',', $productSkuPlaceholders) . '))'
                . ' ORDER BY accounts.name ASC, offers.updated_at DESC, offers.id DESC',
                $params
            );

            foreach ($rows as $row) {
                $matchedSkus = array_unique(array_filter(array(
                    trim((string) ($row['shop_sku'] ?? '')),
                    trim((string) ($row['product_sku'] ?? '')),
                )));

                foreach ($matchedSkus as $sku) {
                    if ($sku === '' || empty($skuMap[$sku])) {
                        continue;
                    }

                    foreach ($skuMap[$sku] as $productIndex) {
                        $offerKey = $productIndex . ':' . (string) ($row['account_slug'] ?? '') . ':' . (string) ($row['offer_id'] ?? '');
                        if (isset($attachedOffers[$offerKey])) {
                            continue;
                        }

                        $products[$productIndex]['empik_accounts'][] = array(
                            'offer_row_id' => (int) ($row['offer_row_id'] ?? 0),
                            'account_id' => (int) ($row['account_id'] ?? 0),
                            'account_name' => (string) ($row['account_name'] ?? ''),
                            'account_slug' => (string) ($row['account_slug'] ?? ''),
                            'offer_id' => trim((string) ($row['offer_id'] ?? '')),
                            'price_amount' => $row['price'] !== null ? (float) $row['price'] : ($row['total_price'] !== null ? (float) $row['total_price'] : null),
                            'currency' => (string) ($row['currency_iso_code'] ?? ''),
                            'quantity' => $row['quantity'] !== null ? (int) $row['quantity'] : null,
                            'sku' => $sku,
                            'shop_sku' => trim((string) ($row['shop_sku'] ?? '')),
                            'product_sku' => trim((string) ($row['product_sku'] ?? '')),
                            'empik_url' => './index.php?controller=empik&action=offer&id=' . (int) ($row['offer_row_id'] ?? 0),
                            'last_synced_at' => (string) ($row['last_synced_at'] ?? ''),
                        );
                        $attachedOffers[$offerKey] = true;
                    }
                }
            }
        }

        return $products;
    }

    private function normalizeErliDisplayPrice($value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $price = (float) $value;
        if ($price >= 100000) {
            return round($price / 100, 2);
        }

        return $price;
    }

    private function erliPublicProductUrl(array $row): string
    {
        $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
        if (is_array($payload)) {
            $url = $this->findUrlInArray($payload, array('productUrl', 'product_url', 'offerUrl', 'offer_url', 'marketplaceUrl', 'marketplace_url'));
            if ($url !== '') {
                return $url;
            }
        }

        return './index.php?controller=erli&action=product&id=' . (int) ($row['product_row_id'] ?? 0);
    }

    private function findUrlInArray(array $data, array $preferredKeys): string
    {
        foreach ($preferredKeys as $key) {
            if (!empty($data[$key]) && is_scalar($data[$key])) {
                $url = trim((string) $data[$key]);
                if (preg_match('#^https?://#i', $url) === 1) {
                    return $url;
                }
            }
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $url = $this->findUrlInArray($value, $preferredKeys);
                if ($url !== '') {
                    return $url;
                }
            }
        }

        return '';
    }

    private function allegroSkuCandidatesForComputerProduct(array $product): array
    {
        $raw = array(
            $product['sku'] ?? '',
            $product['offerid'] ?? '',
            $product['id'] ?? '',
        );

        if (!empty($product['id'])) {
            $raw[] = 'ALTREO_' . (string) $product['id'];
        }
        if (!empty($product['offerid'])) {
            $raw[] = 'ALTREO_' . (string) $product['offerid'];
        }

        $values = array();
        foreach ($raw as $value) {
            $sku = trim((string) $value);
            if ($sku !== '') {
                $values[] = $sku;
            }
        }

        return array_values(array_unique($values));
    }

    private function empikSkuCandidatesForComputerProduct(array $product): array
    {
        $raw = array();
        if (!empty($product['id'])) {
            $raw[] = 'ALTREO_' . (string) $product['id'];
        }

        $values = array();
        foreach ($raw as $value) {
            $sku = trim((string) $value);
            if ($sku !== '') {
                $values[] = $sku;
            }
        }

        return array_values(array_unique($values));
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        $this->tableExistsCache[$table] = (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name',
            array('table_name' => $table)
        ) > 0;

        return $this->tableExistsCache[$table];
    }

    private function normalizeComponentImageFields(array $component): array
    {
        foreach (array('img', 'img_morele', 'img_empik') as $field) {
            if (array_key_exists($field, $component)) {
                $component[$field] = implode(',', $this->existingComponentImages((string) $component[$field]));
            }
        }

        return $component;
    }

    private function normalizeComponentTextFields(array $component): array
    {
        foreach ($component as $field => $value) {
            if (is_string($value)) {
                $component[$field] = trim($value);
            }
        }

        foreach (array('parameters_eu', 'parameters_morele', 'parameters_empik') as $field) {
            if (array_key_exists($field, $component)) {
                $component[$field] = $this->normalizeJsonMapString((string) $component[$field]);
            }
        }

        return $component;
    }

    private function normalizeJsonMapString(string $json): string
    {
        $json = trim($json);
        if ($json === '') {
            return '';
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return $json;
        }

        $encoded = json_encode($this->trimArrayRecursive($decoded), JSON_UNESCAPED_UNICODE);
        return is_string($encoded) ? $encoded : $json;
    }

    private function postedComponentParamsJson(string $loadedMarker, array $params, ?array $existingComponent, string $column): string
    {
        if (!$this->hasPostedField($loadedMarker) && is_array($existingComponent)) {
            return $this->normalizeJsonMapString((string) ($existingComponent[$column] ?? ''));
        }

        return $this->encodeJsonMap($params);
    }

    private function encodeJsonMap(array $params): string
    {
        $encoded = json_encode($this->trimArrayRecursive($params), JSON_UNESCAPED_UNICODE);
        return is_string($encoded) ? $encoded : '{}';
    }

    private function hasPostedField(string $key): bool
    {
        return array_key_exists($key, $_POST);
    }

    private function existingComponentImages(string $csv): array
    {
        $dir = $this->componentUploadDir();
        $files = array();

        foreach (array_filter(array_map('trim', explode(',', $csv))) as $file) {
            if ($file === '' || basename($file) !== $file) {
                continue;
            }

            if (is_file($dir . DIRECTORY_SEPARATOR . $file)) {
                $files[] = $file;
            }
        }

        return array_values(array_unique($files));
    }

    private function cartesianProduct(array $input): array
    {
        $result = array(array());
        foreach ($input as $key => $values) {
            $append = array();
            foreach ($result as $product) {
                foreach ($values as $item) {
                    $product[$key] = $item;
                    $append[] = $product;
                }
            }
            $result = $append;
        }
        return $result;
    }

    private function csvIds($csv): array
    {
        if (is_array($csv)) {
            $parts = $csv;
        } elseif (is_object($csv)) {
            $parts = array();
        } else {
            $parts = explode(',', (string) $csv);
        }

        $ids = array();
        foreach ($parts as $part) {
            $value = (int) trim((string) $part);
            if ($value > 0) {
                $ids[] = $value;
            }
        }

        return array_values(array_unique($ids));
    }

    private function pageLinks(int $currentPage, int $totalPages): array
    {
        $window = 3;
        $startPage = max(1, $currentPage - $window);
        $endPage = min($totalPages, $currentPage + $window);
        $pageLinks = array();

        if ($startPage > 1) {
            $pageLinks[] = array('num' => 1, 'is_current' => false);
            if ($startPage > 2) {
                $pageLinks[] = array('ellipsis' => true);
            }
        }
        for ($page = $startPage; $page <= $endPage; $page++) {
            $pageLinks[] = array('num' => $page, 'is_current' => $page === $currentPage);
        }
        if ($endPage < $totalPages) {
            if ($endPage < $totalPages - 1) {
                $pageLinks[] = array('ellipsis' => true);
            }
            $pageLinks[] = array('num' => $totalPages, 'is_current' => false);
        }

        return $pageLinks;
    }

    private function productUploadDir(): string
    {
        $path = dirname(__DIR__, 2) . '/img_computers_products';
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        return $path;
    }

    private function componentUploadDir(): string
    {
        $path = dirname(__DIR__, 2) . '/img_components';
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        return $path;
    }

    private function handleProductImageSet(int $productId, string $field, string $prefix): ?string
    {
        $file = $this->nestedFile($productId, $field);
        if ($file === null || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $this->moveUploadedFile($file, $this->productUploadDir(), $prefix);
    }

    private function nestedFile(int $productId, string $field): ?array
    {
        if (!isset($_FILES['products']['name'][$productId][$field])) {
            return null;
        }
        return array(
            'name' => $_FILES['products']['name'][$productId][$field] ?? '',
            'type' => $_FILES['products']['type'][$productId][$field] ?? '',
            'tmp_name' => $_FILES['products']['tmp_name'][$productId][$field] ?? '',
            'error' => $_FILES['products']['error'][$productId][$field] ?? UPLOAD_ERR_NO_FILE,
            'size' => $_FILES['products']['size'][$productId][$field] ?? 0,
        );
    }

    private function handleFlatUpload(string $field, string $targetDir, string $prefix): ?string
    {
        if (!isset($_FILES[$field])) {
            return null;
        }
        $file = $_FILES[$field];
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        return $this->moveUploadedFile($file, $targetDir, $prefix);
    }

    private function handleMultipleFlatUploads(string $field, string $targetDir, string $prefix): array
    {
        if (!isset($_FILES[$field]['name']) || !is_array($_FILES[$field]['name'])) {
            return array();
        }
        $saved = array();
        foreach ($_FILES[$field]['name'] as $index => $name) {
            $file = array(
                'name' => $name,
                'type' => $_FILES[$field]['type'][$index] ?? '',
                'tmp_name' => $_FILES[$field]['tmp_name'][$index] ?? '',
                'error' => $_FILES[$field]['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $_FILES[$field]['size'][$index] ?? 0,
            );
            $stored = $this->moveUploadedFile($file, $targetDir, $prefix . $index . '_');
            if ($stored !== null) {
                $saved[] = $stored;
            }
        }
        return $saved;
    }

    private function moveUploadedFile(array $file, string $targetDir, string $prefix): ?string
    {
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, array('jpg', 'jpeg', 'png', 'gif', 'webp'), true)) {
            throw new RuntimeException('Nieobslugiwany format pliku.');
        }
        $filename = uniqid($prefix, true) . '.' . $extension;
        $destination = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $destination)) {
            throw new RuntimeException('Nie udalo sie zapisac pliku.');
        }
        return $filename;
    }

    private function streamCsv(string $filename, array $headers, array $rows): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        $output = fopen('php://output', 'wb');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($output, $row, ';');
        }
        fclose($output);
        exit;
    }

    private function streamComputerTemplateCsv(array $template, array $productIds): void
    {
        $headers = array();
        foreach ($template['columns'] as $column) {
            $headers[] = (string) ($column['header'] ?? '');
        }

        $componentsById = $this->componentsById();
        $rows = array();
        foreach ($productIds as $productId) {
            $product = $this->productById($productId);
            if (!$product) {
                continue;
            }

            $components = array();
            foreach ($this->csvIds((string) ($product['id_components'] ?? '')) as $componentId) {
                if (isset($componentsById[$componentId]) && is_array($componentsById[$componentId])) {
                    $components[] = $this->normalizeComponentTextFields($componentsById[$componentId]);
                }
            }
            usort($components, static function (array $left, array $right): int {
                return strcmp((string) ($left['category'] ?? ''), (string) ($right['category'] ?? ''));
            });

            $context = $this->computerCsvContext($product, $components, (string) ($template['description_template'] ?? ''));
            $row = array();
            foreach ($template['columns'] as $column) {
                $row[] = $this->resolveComputerCsvColumn($column, $context);
            }
            $rows[] = $row;
        }

        $delimiter = (string) ($template['delimiter'] ?? ';');
        $encoding = strtoupper((string) ($template['encoding'] ?? 'UTF-8'));
        $stream = fopen('php://temp', 'w+b');
        fputcsv($stream, $headers, $delimiter);
        foreach ($rows as $row) {
            fputcsv($stream, $row, $delimiter);
        }
        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        if ($encoding === 'WINDOWS-1250') {
            $converted = @iconv('UTF-8', 'Windows-1250//TRANSLIT', $csv);
            if ($converted !== false) {
                $csv = $converted;
            }
        } elseif (!empty($template['add_bom'])) {
            $csv = "\xEF\xBB\xBF" . $csv;
        }

        $filename = $this->safeCsvFilenamePrefix((string) ($template['filename_prefix'] ?? 'computers_export'))
            . '_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=' . ($encoding === 'WINDOWS-1250' ? 'windows-1250' : 'utf-8'));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $csv;
        exit;
    }

    private function computerCsvContext(array $product, array $components, string $descriptionTemplate = ''): array
    {
        $imagesEasy = $this->computerExportImages($product, $components, 'easy');
        $imagesMorele = $this->computerExportImages($product, $components, 'morele');
        $imagesEmpik = $this->computerExportImages($product, $components, 'empik');
        $mainEmpikImage = '';
        $productEmpikImage = trim((string) ($product['img_empik'] ?? ''));
        if ($productEmpikImage !== '') {
            $mainEmpikImage = (string) $this->publicImageUrl($this->publicAppBaseUrl(), 'img_computers_products', $productEmpikImage);
        } elseif ($imagesEmpik !== array()) {
            $mainEmpikImage = (string) $imagesEmpik[0];
        }
        $empikParams = array();
        $moreleParams = array();
        foreach ($components as $component) {
            foreach ($this->decodeJsonMap((string) ($component['parameters_empik'] ?? '')) as $key => $value) {
                $empikParams[(string) $key] = is_array($value) ? implode(' | ', $value) : (string) $value;
            }
            foreach ($this->decodeJsonMap((string) ($component['parameters_morele'] ?? '')) as $key => $value) {
                if (!array_key_exists($key, $moreleParams)) {
                    $moreleParams[$key] = is_array($value) ? implode('|', $value) : (string) $value;
                }
            }
        }

        $componentValues = array();
        $componentImages = array();
        foreach ($components as $component) {
            $category = strtoupper(trim((string) ($component['category'] ?? '')));
            if ($category === '') {
                continue;
            }
            foreach (array('name', 'name_title', 'name_spec', 'description', 'description_morele', 'description_empik') as $field) {
                $componentValues[$category . '.' . $field] = (string) ($component[$field] ?? '');
            }
            foreach (array('easy' => 'img', 'morele' => 'img_morele', 'empik' => 'img_empik') as $channel => $imageField) {
                $images = array();
                foreach (preg_split('/\s*\|\s*|\s*,\s*|\r\n|\r|\n/', (string) ($component[$imageField] ?? '')) ?: array() as $image) {
                    $url = $this->publicImageUrl($this->publicAppBaseUrl(), 'img_components', trim((string) $image));
                    if ($url !== null) {
                        $images[] = $url;
                    }
                }
                $componentImages[$channel . '.' . $category] = array_values(array_unique($images));
            }
        }

        return array(
            'product' => $product,
            'components' => $components,
            'component_values' => $componentValues,
            'component_images' => $componentImages,
            'images.easy' => $imagesEasy,
            'images.morele' => $imagesMorele,
            'images.empik' => $imagesEmpik,
            'main_image.empik' => $mainEmpikImage,
            'empik_params' => $empikParams,
            'parameters.easy' => $this->easyUploaderParameters($components, (string) ($product['name'] ?? ''), (string) ($product['EAN'] ?? '')),
            'parameters.morele' => implode("|\n", array_values($moreleParams)),
            'description' => $this->renderComputerDescription($product, $components, $descriptionTemplate),
        );
    }

    private function computerComponentsForProduct(array $product): array
    {
        $componentsById = $this->componentsById();
        $components = array();
        foreach ($this->csvIds((string) ($product['id_components'] ?? '')) as $componentId) {
            if (isset($componentsById[$componentId]) && is_array($componentsById[$componentId])) {
                $components[] = $this->normalizeComponentTextFields($componentsById[$componentId]);
            }
        }
        usort($components, static function (array $left, array $right): int {
            return strcmp((string) ($left['category'] ?? ''), (string) ($right['category'] ?? ''));
        });
        return $components;
    }

    private function computerExportImages(array $product, array $components, string $channel): array
    {
        $productField = $channel === 'morele' ? 'img_morele' : ($channel === 'empik' ? 'img_empik' : 'img');
        $componentField = $productField;
        $images = array();
        $base = $this->publicAppBaseUrl();
        $main = trim((string) ($product[$productField] ?? ''));
        if ($channel !== 'empik' && $main !== '') {
            $url = $this->publicImageUrl($base, 'img_computers_products', $main);
            if ($url !== null) {
                $images[] = $url;
            }
        }
        foreach ($components as $component) {
            foreach (preg_split('/\s*\|\s*|\s*,\s*|\r\n|\r|\n/', (string) ($component[$componentField] ?? '')) ?: array() as $image) {
                $url = $this->publicImageUrl($base, 'img_components', trim((string) $image));
                if ($url !== null) {
                    $images[] = $url;
                }
            }
        }

        return array_values(array_unique($images));
    }

    private function resolveComputerCsvColumn(array $column, array $context): string
    {
        $type = (string) ($column['type'] ?? 'source');
        if ($type === 'static') {
            return (string) ($column['value'] ?? '');
        }
        if ($type === 'template') {
            return (string) preg_replace_callback(
                '/\{\{\s*([^{}]+?)\s*\}\}/',
                function (array $matches) use ($context): string {
                    return $this->resolveComputerCsvSource(trim((string) $matches[1]), $context);
                },
                (string) ($column['value'] ?? '')
            );
        }

        $source = (string) ($column['value'] ?? '');
        return $this->resolveComputerCsvSource($source, $context);
    }

    private function resolveComputerCsvSource(string $source, array $context): string
    {
        $product = $context['product'];
        if (strpos($source, 'product.') === 0) {
            $field = substr($source, 8);
            if ($field === 'code') {
                return 'ALTREO_' . (string) ($product['id'] ?? '');
            }
            if ($field === 'producer_code') {
                return substr((string) ($product['name'] ?? ''), 0, 45);
            }
            return (string) ($product[$field] ?? '');
        }
        if (strpos($source, 'component.') === 0) {
            return (string) ($context['component_values'][substr($source, 10)] ?? '');
        }
        if (preg_match('/^component_image\.(easy|morele|empik)\.([A-Z0-9_-]+)\.(\d+)$/', $source, $matches) === 1) {
            $images = $context['component_images'][$matches[1] . '.' . $matches[2]] ?? array();
            return (string) ($images[max(0, (int) $matches[3] - 1)] ?? '');
        }
        if (preg_match('/^product_image\.(easy|morele|empik)\.1$/', $source, $matches) === 1) {
            $field = $matches[1] === 'morele' ? 'img_morele' : ($matches[1] === 'empik' ? 'img_empik' : 'img');
            return (string) ($this->publicImageUrl(
                $this->publicAppBaseUrl(),
                'img_computers_products',
                (string) ($product[$field] ?? '')
            ) ?? '');
        }
        if (strpos($source, 'empik_param:') === 0) {
            return (string) ($context['empik_params'][substr($source, 12)] ?? '');
        }
        if ($source === 'description') {
            return (string) $context['description'];
        }
        if ($source === 'parameters.easy' || $source === 'parameters.morele') {
            return (string) $context[$source];
        }
        if ($source === 'main_image.empik') {
            return (string) $context['main_image.empik'];
        }
        if (preg_match('/^image\\.(easy|morele|empik)\\.(\\d+)$/', $source, $matches) === 1) {
            $images = $context['images.' . $matches[1]];
            return (string) ($images[max(0, (int) $matches[2] - 1)] ?? '');
        }
        if (preg_match('/^images\\.(easy|morele|empik)$/', $source, $matches) === 1) {
            $separator = $matches[1] === 'easy' ? "\n" : ($matches[1] === 'morele' ? ',' : '|');
            return implode($separator, $context[$source]);
        }
        if ($source === 'date.today') {
            return date('Y-m-d');
        }

        return '';
    }

    private function renderComputerDescription(array $product, array $components, string $template = ''): string
    {
        if (trim($template) === '') {
            $template = $this->defaultComputerDescriptionTemplate();
        }
        if ($template === '') {
            return (string) ($product['name'] ?? '');
        }

        $vars = array(
            'title' => (string) ($product['name'] ?? ''),
            'main_img_allegro' => (string) ($this->computerExportImages($product, array(), 'easy')[0] ?? ''),
            'main_img_morele' => (string) ($this->computerExportImages($product, array(), 'morele')[0] ?? ''),
            'main_img_empik' => trim((string) ($product['img_empik'] ?? '')) !== ''
                ? (string) $this->publicImageUrl($this->publicAppBaseUrl(), 'img_computers_products', (string) $product['img_empik'])
                : '',
            'product.id' => (string) ($product['id'] ?? ''),
            'product.code' => 'ALTREO_' . (string) ($product['id'] ?? ''),
            'product.name' => (string) ($product['name'] ?? ''),
            'product.price' => (string) ($product['price'] ?? ''),
            'product.EAN' => (string) ($product['EAN'] ?? ''),
        );
        foreach (array('easy' => 'img', 'morele' => 'img_morele', 'empik' => 'img_empik') as $channel => $imageField) {
            $vars['product_image.' . $channel . '.1'] = (string) ($this->publicImageUrl(
                $this->publicAppBaseUrl(),
                'img_computers_products',
                (string) ($product[$imageField] ?? '')
            ) ?? '');
        }
        foreach (array('easy', 'morele', 'empik') as $channel) {
            $descriptionImages = $this->computerExportImages($product, $components, $channel);
            foreach ($descriptionImages as $index => $imageUrl) {
                $vars['image.' . $channel . '.' . ($index + 1)] = $imageUrl;
            }
        }
        foreach ($components as $component) {
            $category = trim((string) ($component['category'] ?? ''));
            if ($category === '') {
                continue;
            }
            foreach ($component as $field => $value) {
                $vars[$category . '_' . $field] = $value;
                $vars['component.' . strtoupper($category) . '.' . $field] = $value;
            }
            foreach (array('img', 'img_morele', 'img_empik') as $imageField) {
                $images = array_values(array_filter(array_map('trim', preg_split('/,|\\r\\n|\\r|\\n/', (string) ($component[$imageField] ?? '')) ?: array())));
                foreach ($images as $index => $image) {
                    $imageUrl = (string) $this->publicImageUrl($this->publicAppBaseUrl(), 'img_components', $image);
                    $vars[$category . '_' . $imageField . '[' . $index . ']'] = $imageUrl;
                    $channel = $imageField === 'img_morele' ? 'morele' : ($imageField === 'img_empik' ? 'empik' : 'easy');
                    $vars['component_image.' . $channel . '.' . strtoupper($category) . '.' . ($index + 1)] = $imageUrl;
                }
            }
        }

        $template = preg_replace_callback('/\\{%\\s*if\\s+([a-zA-Z0-9_.\\[\\]]+)\\s*%\\}(.*?)\\{%\\s*endif\\s*%\\}/s', static function (array $matches) use ($vars): string {
            $parts = preg_split('/\\{%\\s*else\\s*%\\}/', $matches[2]);
            $value = $vars[$matches[1]] ?? '';
            return trim((string) $value) !== '' ? (string) ($parts[0] ?? '') : (string) ($parts[1] ?? '');
        }, $template);

        return (string) preg_replace_callback('/\\{\\{\\s*([a-zA-Z0-9_.\\[\\]]+)\\s*\\}\\}/', static function (array $matches) use ($vars): string {
            return is_scalar($vars[$matches[1]] ?? '') ? (string) ($vars[$matches[1]] ?? '') : '';
        }, $template);
    }

    private function validatedComputerCsvColumns(): array
    {
        $headers = (array) $this->input('column_header', array());
        $types = (array) $this->input('column_type', array());
        $values = (array) $this->input('column_value', array());
        $columns = array();
        foreach ($headers as $index => $header) {
            $header = trim((string) $header);
            if ($header === '') {
                continue;
            }
            $type = (string) ($types[$index] ?? 'source');
            $columns[] = array(
                'header' => $header,
                'type' => in_array($type, array('source', 'static', 'template'), true) ? $type : 'source',
                'value' => (string) ($values[$index] ?? ''),
            );
        }
        if ($columns === array()) {
            throw new RuntimeException('Szablon musi zawierac co najmniej jedna kolumne.');
        }
        return $columns;
    }

    private function safeCsvFilenamePrefix(string $value): string
    {
        $value = preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim($value));
        return trim((string) $value, '_') !== '' ? trim((string) $value, '_') : 'computers_export';
    }

    private function importEanCsv(): void
    {
        if (!isset($_FILES['csv_file']) || (int) ($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->setFlash('error', json_encode(array('Blad przesylania pliku CSV.')));
            $this->redirect('./index.php?controller=computers&action=products');
        }
        $handle = fopen((string) $_FILES['csv_file']['tmp_name'], 'rb');
        if ($handle === false) {
            $this->setFlash('error', json_encode(array('Nie mozna otworzyc pliku CSV.')));
            $this->redirect('./index.php?controller=computers&action=products');
        }

        $headers = fgetcsv($handle, 0, ';');
        if ($headers === false) {
            fclose($handle);
            $this->setFlash('error', json_encode(array('Nieprawidlowy plik CSV.')));
            $this->redirect('./index.php?controller=computers&action=products');
        }
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);

        $updatedCount = 0;
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $data = array_combine($headers, $row);
            if (!is_array($data)) {
                continue;
            }
            $id = (int) ($data['IDENTITY'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $this->db()->update(self::PRODUCTS_TABLE, array(
                'profit' => (float) ($data['profit'] ?? 0),
                'EAN' => trim((string) ($data['EAN'] ?? '')),
            ), 'id = :id', array('id' => $id));
            $updatedCount++;
        }
        fclose($handle);
        $this->setFlash('success', 'Import EAN zakonczony. Zaktualizowano ' . $updatedCount . ' rekordow.');
        $this->redirect('./index.php?controller=computers&action=products');
    }

    private function easyUploaderParameters(array $components, string $productName, string $ean): string
    {
        $params = array();
        foreach ($components as $component) {
            $decoded = $this->decodeJsonMap((string) ($component['parameters_eu'] ?? ''));
            foreach ($decoded as $key => $value) {
                if (!array_key_exists($key, $params)) {
                    $params[$key] = $value;
                }
            }
        }

        $lines = array();
        foreach ($params as $paramId => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $item = trim((string) $item);
                    if ($item !== '') {
                        $lines[] = (string) $paramId . $item . '|';
                    }
                }
                continue;
            }

            $value = trim((string) $value);
            if ($value !== '') {
                $lines[] = (string) $paramId . $value . '|';
            }
        }

        $cleanName = trim(str_ireplace(array('komputer gamingowy z monitorem', 'komputer gamingowy'), '', $productName));
        $lines[] = '237206|0|' . substr($cleanName, 0, 50) . '|';
        $lines[] = '224017|0|' . substr($cleanName, 0, 45) . '|';
        if (trim($ean) !== '') {
            $lines[] = '225693|0|' . trim($ean) . '|';
        }

        return implode("\n", $lines);
    }

    private function publicImageUrl(string $base, string $folder, string $image): ?string
    {
        $image = trim($image);
        if ($image === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $image) === 1) {
            return $image;
        }

        $image = str_replace('\\', '/', $image);
        $image = preg_replace('#^\./#', '', $image);
        $image = ltrim($image, '/');
        $folder = trim($folder, '/');
        if (strpos($image, $folder . '/') === 0) {
            $image = substr($image, strlen($folder) + 1);
        }
        $image = ltrim($image, '/');

        return rtrim($base, '/') . '/' . $folder . '/' . rawurlencode($image);
    }

    private function publicAppBaseUrl(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
        $scheme = $https ? 'https' : 'http';
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'magazyn.altreo.pl'));
        $script = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/crm/new_version/index.php')));
        $script = rtrim($script, '/');

        return $scheme . '://' . $host . ($script !== '' ? $script : '');
    }

    private function collectMarketParams(array $values, array $types): array
    {
        $result = array();
        foreach ($values as $id => $value) {
            $type = (string) ($types[$id] ?? '');
            if (is_array($value)) {
                $filtered = array();
                foreach ($value as $item) {
                    $item = trim((string) $item);
                    if ($item !== '') {
                        $filtered[] = $item;
                    }
                }
                if ($filtered === array()) {
                    continue;
                }
                $result[$id . '|' . $type . '|'] = $filtered;
                continue;
            }
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $result[$id . '|' . $type . '|'] = $value;
        }
        return $result;
    }

    private function collectEmpikParams(): array
    {
        $result = $this->collectStructuredEmpikParams();
        $names = (array) $this->input('empik_custom_name', array());
        $values = (array) $this->input('empik_custom_value', array());
        foreach ($names as $index => $name) {
            $name = trim((string) $name);
            $value = trim((string) ($values[$index] ?? ''));
            if ($name === '' || $value === '') {
                continue;
            }
            $result[$name] = $value;
        }
        return $result;
    }

    private function collectStructuredEmpikParams(): array
    {
        $input = $this->input('empik_parameters', array());
        if (!is_array($input)) {
            return array();
        }

        $payload = $this->loadEmpikParameterPayload();
        $definitions = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : array();
        if ($definitions === array()) {
            return array();
        }

        $result = array();
        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $id = trim((string) ($definition['id'] ?? ''));
            $name = trim((string) ($definition['name'] ?? $id));
            if ($id === '' || $name === '') {
                continue;
            }

            $rawValue = array_key_exists($id, $input) ? $input[$id] : null;
            $normalized = $this->normalizeEmpikPostedValue($definition, $rawValue);
            if ($normalized === null || $normalized === '') {
                continue;
            }

            $result[$name] = $normalized;
        }

        return $result;
    }

    private function normalizeEmpikPostedValue(array $definition, $rawValue): ?string
    {
        $type = strtolower(trim((string) ($definition['type'] ?? 'text')));
        $multiple = !empty($definition['multiple']);
        $dictionary = isset($definition['dictionary']) && is_array($definition['dictionary']) ? $definition['dictionary'] : array();
        $labelsById = array();

        foreach ($dictionary as $option) {
            if (!is_array($option)) {
                continue;
            }

            $optionId = trim((string) ($option['id'] ?? ''));
            $optionLabel = trim((string) ($option['value'] ?? $optionId));
            if ($optionId !== '') {
                $labelsById[$optionId] = $optionLabel !== '' ? $optionLabel : $optionId;
            }
        }

        if ($multiple) {
            $values = is_array($rawValue) ? $rawValue : ($rawValue !== null && trim((string) $rawValue) !== '' ? preg_split('/\r\n|\r|\n|\|/', (string) $rawValue) : array());
            $values = is_array($values) ? $values : array();
            $normalized = array();

            foreach ($values as $value) {
                $value = trim((string) $value);
                if ($value === '') {
                    continue;
                }

                $normalized[] = isset($labelsById[$value]) ? $labelsById[$value] : $value;
            }

            $normalized = array_values(array_unique(array_filter($normalized, static function (string $value): bool {
                return trim($value) !== '';
            })));

            return $normalized !== array() ? implode(' | ', $normalized) : null;
        }

        if (is_array($rawValue)) {
            return null;
        }

        $value = trim((string) $rawValue);
        if ($value === '') {
            return null;
        }

        if (isset($labelsById[$value])) {
            return $labelsById[$value];
        }

        if ($type === 'dictionary') {
            $lowerValue = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
            foreach ($labelsById as $optionId => $optionLabel) {
                $lowerId = function_exists('mb_strtolower') ? mb_strtolower($optionId, 'UTF-8') : strtolower($optionId);
                $lowerLabel = function_exists('mb_strtolower') ? mb_strtolower($optionLabel, 'UTF-8') : strtolower($optionLabel);
                if ($lowerValue === $lowerId || $lowerValue === $lowerLabel) {
                    return $optionLabel;
                }
            }
        }

        return $value;
    }

    private function decodeJsonMap(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $this->trimArrayRecursive($decoded) : array();
    }

    private function trimArrayRecursive(array $input): array
    {
        $result = array();
        foreach ($input as $key => $value) {
            $cleanKey = is_string($key) ? trim($key) : $key;
            if (is_array($value)) {
                $result[$cleanKey] = $this->trimArrayRecursive($value);
                continue;
            }

            $result[$cleanKey] = is_string($value) ? trim($value) : $value;
        }

        return $result;
    }

    private function hydrateComponentParameterMaps(array $row): array
    {
        $row['param'] = $this->decodeJsonMap((string) ($row['parameters_eu'] ?? ''));
        $row['param_morele'] = $this->decodeJsonMap((string) ($row['parameters_morele'] ?? ''));
        $row['param_empik'] = $this->decodeJsonMap((string) ($row['parameters_empik'] ?? ''));
        $row['param_values_by_id'] = $this->normalizeStoredMarketParamValues(isset($row['param']) && is_array($row['param']) ? $row['param'] : array());
        $row['param_morele_values_by_id'] = $this->normalizeStoredMarketParamValues(isset($row['param_morele']) && is_array($row['param_morele']) ? $row['param_morele'] : array());
        $row['param_empik_normalized'] = $this->normalizeStoredLabelMap(isset($row['param_empik']) && is_array($row['param_empik']) ? $row['param_empik'] : array());

        return $row;
    }

    private function normalizeStoredMarketParamValues(array $values): array
    {
        $result = array();
        foreach ($values as $fullKey => $value) {
            $fullKey = trim((string) $fullKey);
            if ($fullKey === '') {
                continue;
            }

            $parts = explode('|', $fullKey);
            $paramId = trim((string) ($parts[0] ?? ''));
            if ($paramId === '') {
                continue;
            }

            $result[$paramId] = $value;
        }

        return $result;
    }

    private function normalizeStoredLabelMap(array $values): array
    {
        $result = array();
        foreach ($values as $key => $value) {
            $normalizedKey = $this->normalizeLookupText((string) $key);
            if ($normalizedKey === '') {
                continue;
            }

            if (is_array($value)) {
                $labels = array();
                foreach ($value as $item) {
                    $item = trim((string) $item);
                    if ($item !== '') {
                        $labels[] = $this->normalizeLookupText($item);
                    }
                }
                $result[$normalizedKey] = array_values(array_unique(array_filter($labels, static function (string $item): bool {
                    return $item !== '';
                })));
                continue;
            }

            $chunks = preg_split('/\s*\|\s*|\r\n|\r|\n/', (string) $value) ?: array();
            $labels = array();
            foreach ($chunks as $chunk) {
                $chunk = trim((string) $chunk);
                if ($chunk !== '') {
                    $labels[] = $this->normalizeLookupText($chunk);
                }
            }
            $result[$normalizedKey] = array_values(array_unique(array_filter($labels, static function (string $item): bool {
                return $item !== '';
            })));
        }

        return $result;
    }

    private function normalizeLookupText(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private function loadEuParameterPayload(string $componentCategory = ''): array
    {
        $meta = array(
            'label' => 'Allegro EU',
            'category_id' => '',
            'source' => '',
        );

        try {
            $desktopCategory = $this->findDesktopCategoryMapping();
            $categoryId = trim((string) ($desktopCategory['allegro_category_id'] ?? ''));
            $meta['source'] = trim((string) ($desktopCategory['name'] ?? ''));

            if ($categoryId === '') {
                $categoryId = self::DEFAULT_DESKTOP_EU_CATEGORY_ID;
                $meta['source'] = 'Fallback kategorii desktop';
            }

            $meta['category_id'] = $categoryId;
            $service = new AllegroService();
            $items = $this->markUsedComputerParameters(
                $service->categoryParameters($categoryId),
                $this->computerParameterUsage('parameters_eu', $componentCategory),
                'id'
            );
            $meta['component_category'] = $componentCategory;

            return array(
                'items' => $items,
                'error' => '',
                'meta' => $this->parameterUsageMeta($meta, $items),
            );
        } catch (Throwable $exception) {
            return array(
                'items' => array(),
                'error' => 'Nie udalo sie pobrac parametrow EU z API: ' . $exception->getMessage(),
                'meta' => $meta,
            );
        }
    }

    private function loadEmpikParameterPayload(string $componentCategory = ''): array
    {
        $meta = array(
            'label' => 'Empik',
            'category_id' => '',
            'source' => '',
        );

        try {
            $desktopCategory = $this->findDesktopCategoryMapping();
            $categoryId = trim((string) ($desktopCategory['empik_category_id'] ?? ''));
            $meta['source'] = trim((string) ($desktopCategory['name'] ?? ''));

            if ($categoryId === '') {
                $categoryId = trim($this->settings ? $this->settings->get('computers_empik_category_id', self::DEFAULT_DESKTOP_EMPIK_CATEGORY_ID) : self::DEFAULT_DESKTOP_EMPIK_CATEGORY_ID);
                $meta['source'] = $meta['source'] !== '' ? $meta['source'] : 'Fallback ustawien administracji';
            }

            $meta['category_id'] = $categoryId;
            $service = new EmpikService();

            $items = $service->categoryAttributes($categoryId);
            foreach ($items as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }
                if ($this->empikAttributeRequiresManualInput($item)) {
                    $items[$index]['option_lookup'] = false;
                    $items[$index]['dictionary'] = array();
                    $items[$index]['type'] = !empty($item['multiple']) ? 'textarea' : 'text';
                }
            }
            $items = $this->markUsedComputerParameters(
                $items,
                $this->computerParameterUsage('parameters_empik', $componentCategory),
                'name'
            );
            $meta['component_category'] = $componentCategory;

            return array(
                'items' => $items,
                'error' => '',
                'meta' => $this->parameterUsageMeta($meta, $items),
            );
        } catch (Throwable $exception) {
            return array(
                'items' => array(),
                'error' => 'Nie udalo sie pobrac parametrow Empik z API: ' . $exception->getMessage(),
                'meta' => $meta,
            );
        }
    }

    private function empikAttributeRequiresManualInput(array $attribute): bool
    {
        $value = $this->normalizeLookupText(
            (string) ($attribute['name'] ?? '') . ' ' . (string) ($attribute['id'] ?? '')
        );
        if ($value === '') {
            return false;
        }

        foreach (array(
            'dodatkowe zdjecia',
            'dodatkowe zdjęcia',
            'zdjecia dodatkowe',
            'zdjęcia dodatkowe',
            'additional images',
            'additional image',
            'extra images',
            'extra image',
            'image url',
            'image urls',
            'adres zdjecia',
            'adres zdjęcia',
            'url zdjecia',
            'url zdjęcia',
        ) as $phrase) {
            if (strpos($value, $this->normalizeLookupText($phrase)) !== false) {
                return true;
            }
        }

        $hasImageWord = strpos($value, 'zdjec') !== false
            || strpos($value, 'zdję') !== false
            || strpos($value, 'image') !== false;
        $hasManualWord = strpos($value, 'dodatk') !== false
            || strpos($value, 'additional') !== false
            || strpos($value, 'extra') !== false
            || strpos($value, 'url') !== false;

        return $hasImageWord && $hasManualWord;
    }

    private function computerParameterUsage(string $column, string $componentCategory = ''): array
    {
        if (!in_array($column, array('parameters_eu', 'parameters_morele', 'parameters_empik'), true)) {
            return array();
        }

        $componentCategory = trim($componentCategory);
        if ($componentCategory === '') {
            return array();
        }

        $rows = $this->db()->fetchAll(
            'SELECT ' . $column . ' AS parameters_json FROM ' . self::COMPONENTS_TABLE
            . ' WHERE category = :component_category'
            . ' AND ' . $column . ' IS NOT NULL AND ' . $column . " <> '' AND " . $column . " <> '{}'",
            array('component_category' => $componentCategory)
        );
        $usage = array();
        foreach ($rows as $row) {
            $parameters = $this->decodeJsonMap((string) ($row['parameters_json'] ?? ''));
            foreach ($parameters as $key => $value) {
                if (!$this->hasMarketParameterValue($value)) {
                    continue;
                }
                $identifier = trim((string) $key);
                if ($column !== 'parameters_empik') {
                    $identifier = trim((string) (explode('|', $identifier)[0] ?? ''));
                }
                $identifier = $this->normalizeLookupText($identifier);
                if ($identifier !== '') {
                    $usage[$identifier] = (int) ($usage[$identifier] ?? 0) + 1;
                }
            }
        }

        return $usage;
    }

    private function hasMarketParameterValue($value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->hasMarketParameterValue($item)) {
                    return true;
                }
            }
            return false;
        }

        return trim((string) $value) !== '';
    }

    private function markUsedComputerParameters(array $items, array $usage, string $identifierField): array
    {
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $identifier = $this->normalizeLookupText((string) ($item[$identifierField] ?? ''));
            $items[$index]['usage_count'] = (int) ($usage[$identifier] ?? 0);
            $items[$index]['is_used'] = $items[$index]['usage_count'] > 0;
        }

        usort($items, static function (array $left, array $right): int {
            $used = ((int) ($right['usage_count'] ?? 0)) <=> ((int) ($left['usage_count'] ?? 0));
            if ($used !== 0) {
                return $used;
            }
            $required = ((int) !empty($right['required'])) <=> ((int) !empty($left['required']));
            if ($required !== 0) {
                return $required;
            }
            return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        return $items;
    }

    private function parameterUsageMeta(array $meta, array $items): array
    {
        $usedCount = 0;
        foreach ($items as $item) {
            if (is_array($item) && !empty($item['is_used'])) {
                $usedCount++;
            }
        }
        $meta['used_count'] = $usedCount;
        $meta['unused_count'] = max(0, count($items) - $usedCount);
        return $meta;
    }

    private function markUsedMoreleParameters(array $payload, string $componentCategory = ''): array
    {
        $items = isset($payload['category_characteristics']) && is_array($payload['category_characteristics'])
            ? $payload['category_characteristics']
            : array();
        $usage = $this->computerParameterUsage('parameters_morele', $componentCategory);

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $identifier = $this->normalizeLookupText((string) ($item['characteristics_id'] ?? ''));
            $items[$index]['usage_count'] = (int) ($usage[$identifier] ?? 0);
            $items[$index]['is_used'] = $items[$index]['usage_count'] > 0;
        }
        usort($items, static function (array $left, array $right): int {
            $used = ((int) ($right['usage_count'] ?? 0)) <=> ((int) ($left['usage_count'] ?? 0));
            if ($used !== 0) {
                return $used;
            }
            return strcasecmp(
                (string) ($left['characteristics_name'] ?? ''),
                (string) ($right['characteristics_name'] ?? '')
            );
        });
        $payload['category_characteristics'] = $items;
        return $payload;
    }

    private function moreleParameterUsageMeta(array $meta, array $payload): array
    {
        $items = isset($payload['category_characteristics']) && is_array($payload['category_characteristics'])
            ? $payload['category_characteristics']
            : array();
        return $this->parameterUsageMeta($meta, $items);
    }

    private function loadMoreleParameterPayload(string $componentCategory = ''): array
    {
        $meta = array(
            'label' => 'Morele',
            'category_id' => (string) ($this->settings ? $this->settings->get('computers_morele_category_id', (string) self::DEFAULT_DESKTOP_MORELE_CATEGORY_ID) : (string) self::DEFAULT_DESKTOP_MORELE_CATEGORY_ID),
            'source' => 'API Morele / ustawienia administracji',
        );

        try {
            $categoryId = (int) $meta['category_id'];
            if ($categoryId <= 0) {
                $categoryId = self::DEFAULT_DESKTOP_MORELE_CATEGORY_ID;
            }

            if ($this->hasConfiguredMoreleApiCredentials()) {
                $service = new MoreleService();
                $items = $service->categoryCharacteristics($categoryId);
                $items = $this->markUsedMoreleParameters($items, $componentCategory);
                $meta['component_category'] = $componentCategory;
                return array(
                    'items' => $items,
                    'error' => '',
                    'meta' => $this->moreleParameterUsageMeta($meta, $items),
                );
            }

            $this->includeLegacyMoreleApi();
            if (!function_exists('morele_get_all_parameters')) {
                throw new RuntimeException('Brak konfiguracji Morele API w administracji oraz brak legacy adaptera morele_api.php.');
            }

            $meta['source'] = 'Legacy API / fallback';
            $payload = morele_get_all_parameters($categoryId);
            $items = is_array($payload) ? $payload : array();
            if (!isset($items['category_characteristics']) || !is_array($items['category_characteristics'])) {
                $items['category_characteristics'] = array();
            }
            $items = $this->markUsedMoreleParameters($items, $componentCategory);
            $meta['component_category'] = $componentCategory;

            return array(
                'items' => $items,
                'error' => '',
                'meta' => $this->moreleParameterUsageMeta($meta, $items),
            );
        } catch (Throwable $exception) {
            if (strpos($exception->getMessage(), 'Brak konfiguracji Morele API') !== false) {
                $message = 'Brak konfiguracji Morele API w administracji albo legacy adaptera morele_api.php na serwerze.';
            } else {
                $message = 'Nie udalo sie pobrac parametrow Morele: ' . $exception->getMessage();
            }
            return array(
                'items' => array('category_characteristics' => array()),
                'error' => $message,
                'meta' => $meta,
            );
        }
    }

    private function includeLegacyMoreleApi(): void
    {
        $configuredApiUrl = trim($this->settings ? $this->settings->get('morele_api_url', '') : '');
        if ($configuredApiUrl !== '' && !defined('MORELE_API_URL')) {
            define('MORELE_API_URL', $configuredApiUrl);
        }

        $candidates = array(
            BASE_PATH . '/temporary/partials/morele_api.php',
            BASE_PATH . '/temporary/morele/morele_api.php',
            dirname(BASE_PATH) . '/temporary/partials/morele_api.php',
            dirname(BASE_PATH) . '/temporary/morele/morele_api.php',
            dirname(BASE_PATH, 2) . '/temporary/partials/morele_api.php',
            dirname(BASE_PATH, 2) . '/temporary/morele/morele_api.php',
        );

        foreach ($candidates as $path) {
            if (is_file($path)) {
                require_once $path;
                return;
            }
        }
    }

    private function hasConfiguredMoreleApiCredentials(): bool
    {
        if ($this->settings === null) {
            return false;
        }

        $clientId = trim((string) $this->settings->get('morele_client_id', ''));
        $clientSecret = trim((string) $this->settings->get('morele_client_secret', ''));

        return $clientId !== '' && $clientSecret !== '';
    }

    private function findDesktopCategoryMapping(): array
    {
        $rows = $this->db()->fetchAll('SELECT id, name, slug, allegro_category_id, empik_category_id FROM categories ORDER BY name ASC');
        if ($rows === array()) {
            return array();
        }

        $scored = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $slug = trim((string) ($row['slug'] ?? ''));
            $haystack = function_exists('mb_strtolower') ? mb_strtolower($name . ' ' . $slug, 'UTF-8') : strtolower($name . ' ' . $slug);
            $score = 0;

            foreach (self::DESKTOP_CATEGORY_KEYWORDS as $index => $keyword) {
                $needle = function_exists('mb_strtolower') ? mb_strtolower($keyword, 'UTF-8') : strtolower($keyword);
                if ($needle !== '' && strpos($haystack, $needle) !== false) {
                    $score += 100 - ($index * 10);
                }
            }

            if ($score <= 0) {
                continue;
            }

            if (trim((string) ($row['allegro_category_id'] ?? '')) !== '') {
                $score += 15;
            }
            if (trim((string) ($row['empik_category_id'] ?? '')) !== '') {
                $score += 15;
            }

            $row['_score'] = $score;
            $scored[] = $row;
        }

        if ($scored === array()) {
            return array();
        }

        usort($scored, static function (array $left, array $right): int {
            return (int) ($right['_score'] ?? 0) <=> (int) ($left['_score'] ?? 0);
        });

        $best = $scored[0];
        unset($best['_score']);

        return $best;
    }

    private function mergeComponentImages(string $oldCsv, array $removeImages, string $field, string $prefix, int $componentId): string
    {
        $oldImages = $this->existingComponentImages($oldCsv);
        $removeImages = array_values(array_filter(array_map('trim', $removeImages)));
        if ($removeImages !== array()) {
            $oldImages = array_values(array_diff($oldImages, $removeImages));
            foreach ($removeImages as $image) {
                if ($image === '' || basename($image) !== $image) {
                    continue;
                }
                $path = $this->componentUploadDir() . DIRECTORY_SEPARATOR . $image;
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }

        $newImages = array();
        if (isset($_FILES[$field]['name']) && is_array($_FILES[$field]['name'])) {
            foreach ($_FILES[$field]['name'] as $index => $name) {
                $file = array(
                    'name' => $name,
                    'type' => $_FILES[$field]['type'][$index] ?? '',
                    'tmp_name' => $_FILES[$field]['tmp_name'][$index] ?? '',
                    'error' => $_FILES[$field]['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $_FILES[$field]['size'][$index] ?? 0,
                );
                $stored = $this->moveUploadedFile($file, $this->componentUploadDir(), $prefix . $componentId . '_');
                if ($stored !== null) {
                    $newImages[] = $stored;
                }
            }
        }

        return implode(',', array_slice(array_merge($oldImages, $newImages), 0, 16));
    }

    private function refreshPricesForProductsUsingComponent(int $componentId): int
    {
        $componentsById = $this->componentsById();
        $products = $this->db()->fetchAll(
            'SELECT id, id_components, profit FROM ' . self::PRODUCTS_TABLE
            . ' WHERE CONCAT(",", REPLACE(id_components, " ", ""), ",") LIKE :component_token',
            array('component_token' => '%,' . (string) $componentId . ',%')
        );
        $updated = 0;
        foreach ($products as $product) {
            $componentIds = $this->csvIds((string) ($product['id_components'] ?? ''));
            if (!in_array($componentId, $componentIds, true)) {
                continue;
            }
            $priceSum = $this->priceSumForComponents($componentIds, $componentsById);
            $this->db()->update(self::PRODUCTS_TABLE, array(
                'price' => $priceSum + (float) ($product['profit'] ?? 0),
            ), 'id = :id', array('id' => (int) $product['id']));
            $updated++;
        }

        return $updated;
    }

    private function deleteComponentFiles(int $componentId): void
    {
        $row = $this->db()->fetch('SELECT img, img_morele, img_empik FROM ' . self::COMPONENTS_TABLE . ' WHERE id = :id', array('id' => $componentId));
        if (!is_array($row)) {
            return;
        }
        $this->deleteImageList((string) ($row['img'] ?? ''), $this->componentUploadDir());
        $this->deleteImageList((string) ($row['img_morele'] ?? ''), $this->componentUploadDir());
        $this->deleteImageList((string) ($row['img_empik'] ?? ''), $this->componentUploadDir());
    }

    private function deleteImageList(string $csv, string $dir): void
    {
        foreach (array_filter(array_map('trim', explode(',', $csv))) as $file) {
            $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $file;
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function priceSumForComponents(array $componentIds, array $componentsById): float
    {
        $priceSum = 0.0;
        foreach ($componentIds as $componentId) {
            if (isset($componentsById[$componentId])) {
                $priceSum += (float) ($componentsById[$componentId]['price'] ?? 0);
            }
        }
        return $priceSum;
    }

    private function computerCsvSourceOptions(): array
    {
        $options = array(
            'product.id' => 'Produkt: ID',
            'product.code' => 'Produkt: kod ALTREO_ID',
            'product.sku' => 'Produkt: SKU',
            'product.name' => 'Produkt: nazwa',
            'product.price' => 'Produkt: cena',
            'product.profit' => 'Produkt: marza',
            'product.EAN' => 'Produkt: EAN',
            'product.producer_code' => 'Produkt: kod producenta (45 znakow nazwy)',
            'product.offerid' => 'Produkt: ID oferty',
            'description' => 'Opis z szablonu komputera',
            'parameters.easy' => 'Parametry EasyUploader',
            'parameters.morele' => 'Parametry Morele',
            'product_image.easy.1' => 'EasyUploader — zdjecie produktu 1',
            'product_image.morele.1' => 'Morele — zdjecie produktu 1',
            'product_image.empik.1' => 'Empik — zdjecie produktu 1',
            'main_image.empik' => 'Empik — zdjecie produktu 1 (zgodnosc ze starym szablonem)',
            'date.today' => 'Dzisiejsza data',
        );
        $componentImageLimits = $this->computerComponentImageLimits();
        foreach (array('easy' => 'EasyUploader', 'morele' => 'Morele', 'empik' => 'Empik') as $channel => $channelLabel) {
            foreach (($componentImageLimits[$channel] ?? array()) as $category => $maxImages) {
                for ($index = 1; $index <= $maxImages; $index++) {
                    $options['component_image.' . $channel . '.' . $category . '.' . $index]
                        = $category . ' — ' . $channelLabel . ' — zdjecie ' . $index;
                }
            }
        }
        foreach (array('GPU', 'CPU', 'RAM', 'SSD', 'MONITOR', 'PSU', 'CASE', 'COOLING', 'MB') as $category) {
            foreach (array(
                'name' => 'nazwa',
                'name_title' => 'nazwa tytulowa',
                'name_spec' => 'specyfikacja',
                'description' => 'opis EasyUploader',
                'description_morele' => 'opis Morele',
                'description_empik' => 'opis Empik',
            ) as $field => $label) {
                $options['component.' . $category . '.' . $field] = $category . ': ' . $label;
            }
        }
        foreach ($this->empikComputerParameterNames() as $name) {
            $options['empik_param:' . $name] = 'Empik parametr: ' . $name;
        }
        return $options;
    }

    private function computerComponentImageLimits(): array
    {
        $limits = array(
            'easy' => array(),
            'morele' => array(),
            'empik' => array(),
        );
        $fields = array(
            'easy' => 'img',
            'morele' => 'img_morele',
            'empik' => 'img_empik',
        );
        $rows = $this->db()->fetchAll(
            'SELECT category, img, img_morele, img_empik FROM ' . self::COMPONENTS_TABLE
            . ' WHERE category IS NOT NULL AND category <> ""'
        );

        foreach ($rows as $row) {
            $category = strtoupper(trim((string) ($row['category'] ?? '')));
            if ($category === '') {
                continue;
            }
            foreach ($fields as $channel => $field) {
                $count = count(array_values(array_filter(array_map('trim', preg_split(
                    '/\s*\|\s*|\s*,\s*|\r\n|\r|\n/',
                    (string) ($row[$field] ?? '')
                ) ?: array()))));
                if ($count > (int) ($limits[$channel][$category] ?? 0)) {
                    $limits[$channel][$category] = min(16, $count);
                }
            }
        }

        foreach ($limits as $channel => $categories) {
            ksort($categories, SORT_NATURAL);
            $limits[$channel] = $categories;
        }

        return $limits;
    }

    private function computerDescriptionTokens(): array
    {
        $tokens = array(
            'product.name' => 'Produkt: nazwa',
            'product.code' => 'Produkt: kod ALTREO_ID',
            'product.id' => 'Produkt: ID',
            'product.price' => 'Produkt: cena',
            'product.EAN' => 'Produkt: EAN',
            'main_img_allegro' => 'EasyUploader: glowne zdjecie produktu',
            'main_img_morele' => 'Morele: glowne zdjecie produktu',
            'main_img_empik' => 'Empik: glowne zdjecie produktu (img_empik produktu)',
            'product_image.easy.1' => 'EasyUploader — zdjecie produktu 1',
            'product_image.morele.1' => 'Morele — zdjecie produktu 1',
            'product_image.empik.1' => 'Empik — zdjecie produktu 1',
        );
        foreach (array('GPU', 'CPU', 'RAM', 'SSD', 'MONITOR', 'PSU', 'CASE', 'COOLING', 'MB') as $category) {
            foreach (array(
                'name' => 'nazwa',
                'name_title' => 'nazwa tytulowa',
                'name_spec' => 'specyfikacja',
                'description' => 'opis',
                'description_morele' => 'opis Morele',
                'description_empik' => 'opis Empik',
            ) as $field => $label) {
                $tokens['component.' . $category . '.' . $field] = $category . ': ' . $label;
            }
        }
        $componentImageLimits = $this->computerComponentImageLimits();
        foreach (array('easy' => 'EasyUploader', 'morele' => 'Morele', 'empik' => 'Empik') as $channel => $label) {
            foreach (($componentImageLimits[$channel] ?? array()) as $category => $maxImages) {
                for ($index = 1; $index <= $maxImages; $index++) {
                    $tokens['component_image.' . $channel . '.' . $category . '.' . $index]
                        = $category . ' — ' . $label . ' — zdjecie ' . $index;
                }
            }
        }
        return $tokens;
    }

    private function defaultComputerDescriptionTemplate(): string
    {
        return (string) $this->db()->fetchColumn(
            'SELECT template FROM ' . self::TEMPLATES_TABLE . ' WHERE id_template = 1 LIMIT 1'
        );
    }

    private function defaultComputerCsvTemplates(): array
    {
        return array(
            array(
                'slug' => 'easyuploader',
                'name' => 'EasyUploader',
                'description' => 'Eksport komputerow zgodny z dotychczasowym exportToEasyUploader.',
                'filename_prefix' => 'easyuploader_export',
                'delimiter' => ';',
                'encoding' => 'UTF-8',
                'add_bom' => 1,
                'description_template' => $this->defaultComputerDescriptionTemplate(),
                'columns' => $this->easyUploaderTemplateColumns(),
            ),
            array(
                'slug' => 'empik',
                'name' => 'Empik',
                'description' => 'Eksport komputerow zgodny z dotychczasowym exportToEmpik.',
                'filename_prefix' => 'empik_export',
                'delimiter' => ';',
                'encoding' => 'UTF-8',
                'add_bom' => 1,
                'description_template' => $this->defaultComputerDescriptionTemplate(),
                'columns' => $this->empikTemplateColumns(),
            ),
            array(
                'slug' => 'morele',
                'name' => 'Morele',
                'description' => 'Eksport komputerow zgodny z dotychczasowym exportToMorele.',
                'filename_prefix' => 'morele_export',
                'delimiter' => ';',
                'encoding' => 'UTF-8',
                'add_bom' => 1,
                'description_template' => $this->defaultComputerDescriptionTemplate(),
                'columns' => $this->moreleTemplateColumns(),
            ),
        );
    }

    private function easyUploaderTemplateColumns(): array
    {
        $columns = array(
            $this->csvSourceColumn('KOD', 'product.id'),
            $this->csvSourceColumn('TYTUŁ', 'product.name'),
            $this->csvSourceColumn('CENA_KT', 'product.price'),
            $this->csvSourceColumn('PARAMETRY', 'parameters.easy'),
            $this->csvStaticColumn('KAT_ALLEGRO_ID', '486'),
            $this->csvStaticColumn('LICZBA', '1000'),
            $this->csvStaticColumn('LICZBA_RODZAJ', '0'),
            $this->csvStaticColumn('STAW_VAT', '23'),
            $this->csvSourceColumn('ZDJĘCIA', 'images.easy'),
        );
        foreach (array('GPU', 'CPU', 'RAM', 'SSD', 'MONITOR', 'PSU', 'CASE', 'COOLING') as $category) {
            $columns[] = $this->csvSourceColumn('[' . $category . ']', 'component.' . $category . '.description');
        }
        foreach (array('GPU', 'CPU', 'RAM', 'SSD', 'MONITOR', 'PSU', 'CASE', 'COOLING') as $category) {
            $columns[] = $this->csvSourceColumn('[' . $category . '_SPEC]', 'component.' . $category . '.name_spec');
        }
        return $columns;
    }

    private function moreleTemplateColumns(): array
    {
        return array(
            $this->csvSourceColumn('vendorPartNumber', 'product.id'),
            $this->csvSourceColumn('salePriceBrutto', 'product.price'),
            $this->csvStaticColumn('quantity', '1000'),
            $this->csvSourceColumn('vendorProductName', 'product.name'),
            $this->csvSourceColumn('brandCode', 'product.EAN'),
            $this->csvStaticColumn('currency', 'PLN'),
            $this->csvSourceColumn('barcodes', 'product.EAN'),
            $this->csvStaticColumn('availability', '1'),
            $this->csvStaticColumn('vat', '23'),
            $this->csvSourceColumn('images', 'images.morele'),
            $this->csvSourceColumn('vendorDescription', 'description'),
            $this->csvStaticColumn('warranty', '24'),
            $this->csvStaticColumn('vendorCategoryName', 'Komputery stacjonarne'),
            $this->csvSourceColumn('vendorCharacteristic', 'parameters.morele'),
            $this->csvStaticColumn('vendorBrandName', 'ALTREO'),
        );
    }

    private function empikTemplateColumns(): array
    {
        $headers = array(
            'Certyfikaty i Instrukcje GPSR', 'description', 'ean', 'EAN', 'EAN13', 'img', 'Kategoria', 'KOD', 'name',
            'Numer katalogowy', 'Opis', 'Opis oferty', 'Stawka VAT', 'STAW_VAT', 'Sygnatura', 'Tytul', 'TYTUŁ',
            'Tytuł .com (pełny)', 'VAT', 'ZDJĘCIA', 'zdjecie_1', 'Zdjęcie okładki/produktu', 'Data premiery',
            'Dla graczy', 'Dodatkowe zdjęcia (1)', 'Dodatkowe zdjęcia (10)', 'Dodatkowe zdjęcia (2)',
            'Dodatkowe zdjęcia (3)', 'Dodatkowe zdjęcia (4)', 'Dodatkowe zdjęcia (5)', 'Dodatkowe zdjęcia (6)',
            'Dodatkowe zdjęcia (7)', 'Dodatkowe zdjęcia (8)', 'Dodatkowe zdjęcia (9)', 'Dysk', 'Ekran dotykowy',
            'Głębokość produktu (mm)', 'Głębokość w opak. (mm)', 'Gniazdo procesora (Socket)', 'Gwarancja',
            'Karta dźwiękowa', 'Karta graficzna', 'Kod modelu', 'Kod producenta', 'Kolor główny',
            'Kolor - szczegóły', 'Komunikacja urządzenia', 'Liczba rdzeni procesora Elektro', 'Liczba złączy HDMI',
            'Liczba złączy USB', 'Marka', 'Marka procesora', 'Model procesora', 'Napędy/czytniki(2130_dict)',
            'Pamięć karty graficznej', 'Pamięć RAM', 'Pamięć wewnętrzna', 'Producent(2100)', 'Przeznaczenie',
            'Rodzaj obudowy', 'Rodzaj ogniw', 'Rozdzielczość(284)', 'Seria do konceptu', 'Seria procesorów',
            'System operacyjny', 'Szerokość produktu (mm)', 'Szerokość w opak. (mm)', 'Taktowanie', 'Typ chipsetu',
            'Typ dysku (SSD/HDD)', 'Typ pamięci', 'Waga produktu (g)', 'Waga produktu w opak. (g)',
            'Wejścia/wyjścia(2161_dict)', 'Wielkość ekranu(2177)', 'Wymiary(665)', 'Wyposażenie urządzenia',
            'Wysokość produktu (mm)', 'Zestaw', 'Zestaw bez kodu ean', 'Złącza połączeniowe', 'sku', 'product-id',
            'product-id-type', 'offer-description', 'internal-description', 'price', 'price-additional-info',
            'quantity', 'min-quantity-alert', 'state', 'available-start-date', 'available-end-date',
            'logistic-class', 'favorite-rank', 'discount-price', 'discount-start-date', 'discount-end-date',
            'leadtime-to-ship', 'update-delete', 'vatmargin', 'price-calibration-enabled', 'gpsr-entity-name',
            'gpsr-address', 'gpsr-country', 'gpsr-city', 'gpsr-zip-code', 'gpsr-email', 'gpsr-phone',
        );
        $sources = array(
            'description' => 'description', 'ean' => 'product.EAN', 'EAN' => 'product.EAN', 'EAN13' => 'product.EAN',
            'img' => 'main_image.empik', 'KOD' => 'product.code', 'name' => 'product.name',
            'Numer katalogowy' => 'product.code', 'Opis' => 'description', 'Opis oferty' => 'description',
            'Sygnatura' => 'product.code', 'Tytul' => 'product.name', 'TYTUŁ' => 'product.name',
            'Tytuł .com (pełny)' => 'product.name', 'ZDJĘCIA' => 'images.empik', 'zdjecie_1' => 'main_image.empik',
            'Zdjęcie okładki/produktu' => 'main_image.empik', 'Kod producenta' => 'product.producer_code',
            'sku' => 'product.code', 'product-id' => 'product.EAN', 'offer-description' => 'description',
            'internal-description' => 'description', 'price' => 'product.price', 'available-start-date' => 'date.today',
        );
        $statics = array(
            'Kategoria' => 'Komputery PC', 'Stawka VAT' => '23', 'STAW_VAT' => '23', 'VAT' => '23',
            'product-id-type' => 'EAN', 'quantity' => '1000', 'state' => '11', 'logistic-class' => '3',
            'leadtime-to-ship' => '1', 'update-delete' => 'aktualizuj', 'vatmargin' => 'true',
            'gpsr-entity-name' => 'ACCRA Sp. z o.o.', 'gpsr-address' => 'LIPOWA 3D', 'gpsr-country' => 'Polska',
            'gpsr-city' => 'Kraków', 'gpsr-zip-code' => '30-702', 'gpsr-email' => 'kontakt@altreo.pl',
            'gpsr-phone' => '+48 660858061',
        );
        $paramAliases = array(
            'Producent(2100)' => 'Marka', 'Rozdzielczość(284)' => 'Rozdzielczość',
            'Wielkość ekranu(2177)' => 'Wielkość ekranu',
        );
        $imageNumbers = array(
            'Dodatkowe zdjęcia (1)' => 1, 'Dodatkowe zdjęcia (2)' => 2, 'Dodatkowe zdjęcia (3)' => 3,
            'Dodatkowe zdjęcia (4)' => 4, 'Dodatkowe zdjęcia (5)' => 5, 'Dodatkowe zdjęcia (6)' => 6,
            'Dodatkowe zdjęcia (7)' => 7, 'Dodatkowe zdjęcia (8)' => 8, 'Dodatkowe zdjęcia (9)' => 9,
            'Dodatkowe zdjęcia (10)' => 10,
        );
        $parameterNames = array_flip($this->empikComputerParameterNames());
        $columns = array();
        foreach ($headers as $header) {
            if (isset($sources[$header])) {
                $columns[] = $this->csvSourceColumn($header, $sources[$header]);
            } elseif (isset($statics[$header])) {
                $columns[] = $this->csvStaticColumn($header, $statics[$header]);
            } elseif (isset($imageNumbers[$header])) {
                $columns[] = $this->csvSourceColumn($header, 'image.empik.' . $imageNumbers[$header]);
            } elseif (isset($paramAliases[$header])) {
                $columns[] = $this->csvSourceColumn($header, 'empik_param:' . $paramAliases[$header]);
            } elseif (isset($parameterNames[$header])) {
                $columns[] = $this->csvSourceColumn($header, 'empik_param:' . $header);
            } else {
                $columns[] = $this->csvStaticColumn($header, '');
            }
        }
        return $columns;
    }

    private function empikComputerParameterNames(): array
    {
        return array(
            'Dla graczy', 'Dysk', 'Gniazdo procesora (Socket)', 'Gwarancja', 'Karta dźwiękowa',
            'Karta graficzna', 'Kod modelu', 'Kolor główny', 'Kolor - szczegóły', 'Komunikacja urządzenia',
            'Liczba rdzeni procesora Elektro', 'Liczba złączy HDMI', 'Marka', 'Marka procesora',
            'Model procesora', 'Pamięć karty graficznej', 'Pamięć RAM', 'Pamięć wewnętrzna',
            'Przeznaczenie', 'Rodzaj obudowy', 'Rozdzielczość', 'Seria procesorów', 'System operacyjny',
            'Taktowanie', 'Typ chipsetu', 'Typ dysku (SSD/HDD)', 'Typ pamięci', 'Wielkość ekranu',
            'Wyposażenie urządzenia', 'Zestaw', 'Złącza połączeniowe',
        );
    }

    private function csvSourceColumn(string $header, string $source): array
    {
        return array('header' => $header, 'type' => 'source', 'value' => $source);
    }

    private function csvStaticColumn(string $header, string $value): array
    {
        return array('header' => $header, 'type' => 'static', 'value' => $value);
    }

    private function normalizeErrors($value): array
    {
        if ($value === null || $value === '') {
            return array();
        }
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        if (is_array($decoded)) {
            return array_values(array_map('strval', $decoded));
        }
        return array((string) $value);
    }

    private function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        $this->db()->query(
            "CREATE TABLE IF NOT EXISTS " . self::PRODUCTS_TABLE . " (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "id_components VARCHAR(255) NOT NULL DEFAULT '',\n"
            . "sku VARCHAR(190) DEFAULT NULL,\n"
            . "name VARCHAR(255) NOT NULL,\n"
            . "price DECIMAL(12,2) NOT NULL DEFAULT 0.00,\n"
            . "price_allegro DECIMAL(12,2) DEFAULT NULL,\n"
            . "profit DECIMAL(12,2) NOT NULL DEFAULT 0.00,\n"
            . "EAN VARCHAR(64) DEFAULT NULL,\n"
            . "img VARCHAR(255) DEFAULT NULL,\n"
            . "img_morele VARCHAR(255) DEFAULT NULL,\n"
            . "img_empik VARCHAR(255) DEFAULT NULL,\n"
            . "offerid VARCHAR(64) DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "KEY idx_products_altreo_sku (sku),\n"
            . "KEY idx_products_altreo_offerid (offerid),\n"
            . "KEY idx_products_altreo_name (name)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db()->query(
            "CREATE TABLE IF NOT EXISTS " . self::COMPONENTS_TABLE . " (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "name VARCHAR(255) NOT NULL,\n"
            . "name_title VARCHAR(255) DEFAULT NULL,\n"
            . "name_spec VARCHAR(255) DEFAULT NULL,\n"
            . "price DECIMAL(12,2) NOT NULL DEFAULT 0.00,\n"
            . "description MEDIUMTEXT DEFAULT NULL,\n"
            . "description_morele MEDIUMTEXT DEFAULT NULL,\n"
            . "description_empik MEDIUMTEXT DEFAULT NULL,\n"
            . "parameters_eu LONGTEXT DEFAULT NULL,\n"
            . "parameters_morele LONGTEXT DEFAULT NULL,\n"
            . "parameters_empik LONGTEXT DEFAULT NULL,\n"
            . "img TEXT DEFAULT NULL,\n"
            . "img_morele TEXT DEFAULT NULL,\n"
            . "img_empik TEXT DEFAULT NULL,\n"
            . "category VARCHAR(120) DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "KEY idx_components_altreo_category (category),\n"
            . "KEY idx_components_altreo_name (name)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db()->query(
            "CREATE TABLE IF NOT EXISTS " . self::TEMPLATES_TABLE . " (\n"
            . "id_template INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "name VARCHAR(120) NOT NULL,\n"
            . "template LONGTEXT DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id_template),\n"
            . "UNIQUE KEY ux_altreo_template_name (name)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db()->query(
            "CREATE TABLE IF NOT EXISTS " . self::TASK_QUEUE_TABLE . " (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "offerId VARCHAR(64) NOT NULL,\n"
            . "action VARCHAR(64) NOT NULL,\n"
            . "new_varriable TEXT DEFAULT NULL,\n"
            . "date_add DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "KEY idx_task_queue_offerid (offerId),\n"
            . "KEY idx_task_queue_action (action),\n"
            . "KEY idx_task_queue_date_add (date_add)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->ensureTableColumn(self::PRODUCTS_TABLE, 'id_components', "ALTER TABLE " . self::PRODUCTS_TABLE . " ADD COLUMN id_components VARCHAR(255) NOT NULL DEFAULT '' AFTER id");
        $this->ensureTableColumn(self::PRODUCTS_TABLE, 'sku', "ALTER TABLE " . self::PRODUCTS_TABLE . " ADD COLUMN sku VARCHAR(190) DEFAULT NULL AFTER id_components");
        $this->ensureTableColumn(self::PRODUCTS_TABLE, 'name', "ALTER TABLE " . self::PRODUCTS_TABLE . " ADD COLUMN name VARCHAR(255) NOT NULL AFTER sku");
        $this->ensureTableColumn(self::PRODUCTS_TABLE, 'price', "ALTER TABLE " . self::PRODUCTS_TABLE . " ADD COLUMN price DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER name");
        $this->ensureTableColumn(self::PRODUCTS_TABLE, 'price_allegro', "ALTER TABLE " . self::PRODUCTS_TABLE . " ADD COLUMN price_allegro DECIMAL(12,2) DEFAULT NULL AFTER price");
        $this->ensureTableColumn(self::PRODUCTS_TABLE, 'profit', "ALTER TABLE " . self::PRODUCTS_TABLE . " ADD COLUMN profit DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER price_allegro");
        $this->ensureTableColumn(self::PRODUCTS_TABLE, 'EAN', "ALTER TABLE " . self::PRODUCTS_TABLE . " ADD COLUMN EAN VARCHAR(64) DEFAULT NULL AFTER profit");
        $this->ensureTableColumn(self::PRODUCTS_TABLE, 'img', "ALTER TABLE " . self::PRODUCTS_TABLE . " ADD COLUMN img VARCHAR(255) DEFAULT NULL AFTER EAN");
        $this->ensureTableColumn(self::PRODUCTS_TABLE, 'img_morele', "ALTER TABLE " . self::PRODUCTS_TABLE . " ADD COLUMN img_morele VARCHAR(255) DEFAULT NULL AFTER img");
        $this->ensureTableColumn(self::PRODUCTS_TABLE, 'img_empik', "ALTER TABLE " . self::PRODUCTS_TABLE . " ADD COLUMN img_empik VARCHAR(255) DEFAULT NULL AFTER img_morele");
        $this->ensureTableColumn(self::PRODUCTS_TABLE, 'offerid', "ALTER TABLE " . self::PRODUCTS_TABLE . " ADD COLUMN offerid VARCHAR(64) DEFAULT NULL AFTER img_empik");
        $this->ensureTableColumn(self::PRODUCTS_TABLE, 'created_at', "ALTER TABLE " . self::PRODUCTS_TABLE . " ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER offerid");
        $this->ensureTableColumn(self::PRODUCTS_TABLE, 'updated_at', "ALTER TABLE " . self::PRODUCTS_TABLE . " ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");

        $this->ensureTableColumn(self::COMPONENTS_TABLE, 'name', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN name VARCHAR(255) NOT NULL AFTER id");
        $this->ensureTableColumn(self::COMPONENTS_TABLE, 'name_title', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN name_title VARCHAR(255) DEFAULT NULL AFTER name");
        $this->ensureTableColumn(self::COMPONENTS_TABLE, 'name_spec', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN name_spec VARCHAR(255) DEFAULT NULL AFTER name_title");
        $this->ensureTableColumn(self::COMPONENTS_TABLE, 'price', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN price DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER name_spec");
        $this->ensureTableColumn(self::COMPONENTS_TABLE, 'description', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN description MEDIUMTEXT DEFAULT NULL AFTER price");
        $this->ensureTableColumn(self::COMPONENTS_TABLE, 'description_morele', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN description_morele MEDIUMTEXT DEFAULT NULL AFTER description");
        $this->ensureTableColumn(self::COMPONENTS_TABLE, 'description_empik', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN description_empik MEDIUMTEXT DEFAULT NULL AFTER description_morele");
        $this->ensureTableColumn(self::COMPONENTS_TABLE, 'parameters_eu', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN parameters_eu LONGTEXT DEFAULT NULL AFTER description_empik");
        $this->ensureTableColumn(self::COMPONENTS_TABLE, 'parameters_morele', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN parameters_morele LONGTEXT DEFAULT NULL AFTER parameters_eu");
        $this->ensureTableColumn(self::COMPONENTS_TABLE, 'parameters_empik', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN parameters_empik LONGTEXT DEFAULT NULL AFTER parameters_morele");
        $this->ensureTableColumn(self::COMPONENTS_TABLE, 'img', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN img TEXT DEFAULT NULL AFTER parameters_empik");
        $this->ensureTableColumn(self::COMPONENTS_TABLE, 'img_morele', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN img_morele TEXT DEFAULT NULL AFTER img");
        $this->ensureTableColumn(self::COMPONENTS_TABLE, 'img_empik', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN img_empik TEXT DEFAULT NULL AFTER img_morele");
        $this->ensureTableColumn(self::COMPONENTS_TABLE, 'category', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN category VARCHAR(120) DEFAULT NULL AFTER img_empik");
        $this->ensureTableColumn(self::COMPONENTS_TABLE, 'created_at', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER category");
        $this->ensureTableColumn(self::COMPONENTS_TABLE, 'updated_at', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");

        $this->ensureTableColumn(self::TEMPLATES_TABLE, 'name', "ALTER TABLE " . self::TEMPLATES_TABLE . " ADD COLUMN name VARCHAR(120) NOT NULL AFTER id_template");
        $this->ensureTableColumn(self::TEMPLATES_TABLE, 'template', "ALTER TABLE " . self::TEMPLATES_TABLE . " ADD COLUMN template LONGTEXT DEFAULT NULL AFTER name");
        $this->ensureTableColumn(self::TEMPLATES_TABLE, 'created_at', "ALTER TABLE " . self::TEMPLATES_TABLE . " ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER template");
        $this->ensureTableColumn(self::TEMPLATES_TABLE, 'updated_at', "ALTER TABLE " . self::TEMPLATES_TABLE . " ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");

        $this->ensureTableColumn(self::TASK_QUEUE_TABLE, 'offerId', "ALTER TABLE " . self::TASK_QUEUE_TABLE . " ADD COLUMN offerId VARCHAR(64) NOT NULL AFTER id");
        $this->ensureTableColumn(self::TASK_QUEUE_TABLE, 'action', "ALTER TABLE " . self::TASK_QUEUE_TABLE . " ADD COLUMN action VARCHAR(64) NOT NULL AFTER offerId");
        $this->ensureTableColumn(self::TASK_QUEUE_TABLE, 'new_varriable', "ALTER TABLE " . self::TASK_QUEUE_TABLE . " ADD COLUMN new_varriable TEXT DEFAULT NULL AFTER action");
        $this->ensureTableColumn(self::TASK_QUEUE_TABLE, 'date_add', "ALTER TABLE " . self::TASK_QUEUE_TABLE . " ADD COLUMN date_add DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER new_varriable");

        $this->seedTemplates();

        self::$schemaEnsured = true;
    }

    private function ensureTableColumn(string $table, string $column, string $alterSql): void
    {
        $exists = (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name',
            array(
                'table_name' => $table,
                'column_name' => $column,
            )
        );

        if ($exists === 0) {
            $this->db()->query($alterSql);
        }
    }

    private function seedTemplates(): void
    {
        $templates = array(
            1 => array(
                'name' => 'Komputery opis podstawowy',
                'template' => '<p>{{title}}</p>',
            ),
            2 => array(
                'name' => 'Komputery opis zapasowy',
                'template' => '<p>{{title}}</p>',
            ),
            3 => array(
                'name' => 'Etui opis JSON',
                'template' => '<p>{{title}}</p>',
            ),
        );

        foreach ($templates as $id => $template) {
            $exists = (int) $this->db()->fetchColumn(
                'SELECT COUNT(*) FROM ' . self::TEMPLATES_TABLE . ' WHERE id_template = :id',
                array('id' => $id)
            );

            if ($exists > 0) {
                continue;
            }

            $this->db()->query(
                'INSERT INTO ' . self::TEMPLATES_TABLE . ' (id_template, name, template) VALUES (:id, :name, :template)',
                array(
                    'id' => $id,
                    'name' => (string) $template['name'],
                    'template' => (string) $template['template'],
                )
            );
        }
    }
}
