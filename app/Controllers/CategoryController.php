<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\CategoryRepository;
use App\Services\AllegroService;
use App\Services\EmpikService;
use RuntimeException;
use Throwable;

class CategoryController extends Controller
{
    /** @var CategoryRepository */
    private $categories;

    /** @var AllegroService */
    private $allegro;

    /** @var EmpikService */
    private $empik;

    public function __construct()
    {
        $this->categories = new CategoryRepository($this->db());
        $this->categories->ensureSchema();
        $this->allegro = new AllegroService();
        $this->empik = new EmpikService();
    }

    public function index(): void
    {
        $this->requireRole('admin');
        $categories = $this->categories->all();
        foreach ($categories as $index => $category) {
            $categories[$index]['products_count'] = $this->categories->productsCount((int) $category['id']);
        }

        $this->render('categories/index', array(
            'pageTitle' => 'Kategorie',
            'contentTitle' => 'Kategorie produktow',
            'pageDescription' => 'Tworzenie, edycja i usuwanie kategorii.',
            'breadcrumbCurrent' => 'Kategorie',
            'categories' => $categories,
        ));
    }

    public function create(): void
    {
        $this->requireRole('admin');
        $this->renderForm('Dodaj kategorie', 'Nowa kategoria', array(
            'name' => '',
            'sku_prefix' => '',
            'allegro_category_id' => '',
            'empik_category_id' => '',
            'end_offers_below_quantity' => '',
            'description' => '',
        ));
    }

    public function store(): void
    {
        $this->requireRole('admin');
        $this->requireWriteAccess();
        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=categories&action=index');
        }

        try {
            $name = trim((string) $this->input('name', ''));
            $description = trim((string) $this->input('description', ''));
            $skuPrefix = $this->categories->normalizeSkuPrefix($this->input('sku_prefix', ''));
            $allegroCategoryId = $this->categories->normalizeAllegroCategoryId($this->input('allegro_category_id', ''));
            $empikCategoryId = $this->categories->normalizeEmpikCategoryId($this->input('empik_category_id', ''));
            $endOffersBelowQuantity = $this->categories->normalizeEndOffersBelowQuantity($this->input('end_offers_below_quantity', ''));

            if ($name === '') {
                throw new RuntimeException('Nazwa kategorii jest wymagana.');
            }

            if ($this->categories->nameExists($name)) {
                throw new RuntimeException('Kategoria o tej nazwie juz istnieje.');
            }

            if ($this->categories->skuPrefixExists($skuPrefix)) {
                throw new RuntimeException('Kategoria z takim przedrostkiem SKU juz istnieje.');
            }

            $slug = $this->categories->uniqueSlug($name);
            $this->categories->create(array(
                'name' => $name,
                'slug' => $slug,
                'sku_prefix' => $skuPrefix,
                'allegro_category_id' => $allegroCategoryId,
                'empik_category_id' => $empikCategoryId,
                'end_offers_below_quantity' => $endOffersBelowQuantity,
                'description' => ($description !== '' ? $description : null),
            ));
            $this->setFlash('success', 'Kategoria zostala dodana.');
            $this->redirect('./index.php?controller=categories&action=index');
        } catch (Throwable $exception) {
            $this->renderForm('Dodaj kategorie', 'Nowa kategoria', array(
                'name' => (string) $this->input('name', ''),
                'sku_prefix' => (string) $this->input('sku_prefix', ''),
                'allegro_category_id' => (string) $this->input('allegro_category_id', ''),
                'empik_category_id' => (string) $this->input('empik_category_id', ''),
                'end_offers_below_quantity' => (string) $this->input('end_offers_below_quantity', ''),
                'description' => (string) $this->input('description', ''),
            ), $exception->getMessage());
        }
    }

    public function edit(): void
    {
        $this->requireRole('admin');
        $id = (int) $this->input('id', 0);
        $category = $this->categories->findById($id);

        if (!$category) {
            $this->setFlash('error', 'Nie znaleziono kategorii.');
            $this->redirect('./index.php?controller=categories&action=index');
        }

        $this->renderForm('Edytuj kategorie', 'Edycja kategorii', $category);
    }

    public function update(): void
    {
        $this->requireRole('admin');
        $this->requireWriteAccess();
        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=categories&action=index');
        }

        $id = (int) $this->input('id', 0);

        try {
            $category = $this->categories->findById($id);
            if (!$category) {
                throw new RuntimeException('Nie znaleziono kategorii.');
            }

            $name = trim((string) $this->input('name', ''));
            $description = trim((string) $this->input('description', ''));
            $skuPrefix = $this->categories->normalizeSkuPrefix($this->input('sku_prefix', ''));
            $allegroCategoryId = $this->categories->normalizeAllegroCategoryId($this->input('allegro_category_id', ''));
            $empikCategoryId = $this->categories->normalizeEmpikCategoryId($this->input('empik_category_id', ''));
            $endOffersBelowQuantity = $this->categories->normalizeEndOffersBelowQuantity($this->input('end_offers_below_quantity', ''));

            if ($name === '') {
                throw new RuntimeException('Nazwa kategorii jest wymagana.');
            }

            if ($this->categories->nameExists($name, $id)) {
                throw new RuntimeException('Kategoria o tej nazwie juz istnieje.');
            }

            if ($this->categories->skuPrefixExists($skuPrefix, $id)) {
                throw new RuntimeException('Kategoria z takim przedrostkiem SKU juz istnieje.');
            }

            $slug = $this->categories->uniqueSlug($name, $id);
            $this->categories->updateById($id, array(
                'name' => $name,
                'slug' => $slug,
                'sku_prefix' => $skuPrefix,
                'allegro_category_id' => $allegroCategoryId,
                'empik_category_id' => $empikCategoryId,
                'end_offers_below_quantity' => $endOffersBelowQuantity,
                'description' => ($description !== '' ? $description : null),
            ));
            $this->setFlash('success', 'Kategoria zostala zaktualizowana.');
            $this->redirect('./index.php?controller=categories&action=index');
        } catch (Throwable $exception) {
            $this->renderForm('Edytuj kategorie', 'Edycja kategorii', array(
                'id' => $id,
                'name' => (string) $this->input('name', ''),
                'sku_prefix' => (string) $this->input('sku_prefix', ''),
                'allegro_category_id' => (string) $this->input('allegro_category_id', ''),
                'empik_category_id' => (string) $this->input('empik_category_id', ''),
                'end_offers_below_quantity' => (string) $this->input('end_offers_below_quantity', ''),
                'description' => (string) $this->input('description', ''),
            ), $exception->getMessage());
        }
    }

    public function setallegro(): void
    {
        $this->requireRole('admin');
        $this->requireWriteAccess();
        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=categories&action=index');
        }

        $id = (int) $this->input('id', 0);
        $category = $this->categories->findById($id);

        if (!$category) {
            $this->setFlash('error', 'Nie znaleziono kategorii.');
            $this->redirect('./index.php?controller=categories&action=index');
        }

        $allegroCategoryId = $this->categories->normalizeAllegroCategoryId($this->input('allegro_category_id', ''));

        try {
            if ($allegroCategoryId !== null) {
                $this->allegro->categoryParameters($allegroCategoryId);
            }

            $this->categories->updateById($id, array('allegro_category_id' => $allegroCategoryId));
            $this->setFlash('success', 'Mapowanie Allegro zostalo zapisane.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=categories&action=edit&id=' . $id);
    }

    public function setempik(): void
    {
        $this->requireRole('admin');
        $this->requireWriteAccess();
        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=categories&action=index');
        }

        $id = (int) $this->input('id', 0);
        $category = $this->categories->findById($id);

        if (!$category) {
            $this->setFlash('error', 'Nie znaleziono kategorii.');
            $this->redirect('./index.php?controller=categories&action=index');
        }

        $empikCategoryId = $this->categories->normalizeEmpikCategoryId($this->input('empik_category_id', ''));

        try {
            if ($empikCategoryId !== null) {
                $matches = $this->empik->searchCategories($empikCategoryId, false);
                $isKnown = false;

                foreach ($matches as $item) {
                    if ((string) ($item['id'] ?? '') === $empikCategoryId) {
                        $isKnown = true;
                        break;
                    }
                }

                if (!$isKnown && $matches === array()) {
                    $this->empik->searchCategories($empikCategoryId, true);
                }
            }

            $this->categories->updateById($id, array('empik_category_id' => $empikCategoryId));
            $this->setFlash('success', 'Mapowanie Empik zostalo zapisane.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=categories&action=edit&id=' . $id);
    }

    public function delete(): void
    {
        $this->requireRole('admin');
        $this->requireWriteAccess();
        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=categories&action=index');
        }

        $id = (int) $this->input('id', 0);
        $count = $this->categories->productsCount($id);

        if ($count > 0) {
            $this->setFlash('error', 'Nie mozna usunac kategorii przypisanej do produktow.');
            $this->redirect('./index.php?controller=categories&action=index');
        }

        $this->categories->deleteById($id);
        $this->setFlash('success', 'Kategoria zostala usunieta.');
        $this->redirect('./index.php?controller=categories&action=index');
    }

    private function renderForm($pageTitle, $contentTitle, array $category, $flashError = null): void
    {
        $this->render('categories/form', array(
            'pageTitle' => $pageTitle,
            'contentTitle' => $contentTitle,
            'pageDescription' => 'Uzupelnij dane kategorii produktu.',
            'breadcrumbCurrent' => $contentTitle,
            'category' => $category,
            'flashError' => $flashError,
        ));
    }
}
