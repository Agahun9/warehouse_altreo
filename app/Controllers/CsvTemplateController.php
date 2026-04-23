<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\CategoryRepository;
use App\Models\CsvImportProfileRepository;
use App\Models\CsvTitleTemplateRepository;
use App\Models\CsvTemplateRepository;
use App\Models\DerivedStockLinkRepository;
use App\Models\ProductAllegroParameterRepository;
use App\Models\ProductCustomFieldRepository;
use App\Models\ProductRepository;
use App\Models\SharedStockGroupRepository;
use App\Services\CsvExportService;
use RuntimeException;
use Throwable;

class CsvTemplateController extends Controller
{
    /** @var CsvTemplateRepository */
    private $templates;

    /** @var ProductRepository */
    private $products;

    /** @var ProductAllegroParameterRepository */
    private $allegroParameters;

    /** @var CsvExportService */
    private $exportService;

    /** @var CsvTitleTemplateRepository */
    private $titleTemplates;

    /** @var ProductCustomFieldRepository */
    private $customFields;

    /** @var CategoryRepository */
    private $categories;

    /** @var CsvImportProfileRepository */
    private $importProfiles;

    /** @var SharedStockGroupRepository */
    private $sharedStockGroups;

    /** @var DerivedStockLinkRepository */
    private $derivedStockLinks;

    public function __construct()
    {
        $this->templates = new CsvTemplateRepository($this->db());
        $this->templates->ensureSchema();

        $this->products = new ProductRepository($this->db());
        $this->products->ensureSchema();

        $this->categories = new CategoryRepository($this->db());
        $this->categories->ensureSchema();

        $this->importProfiles = new CsvImportProfileRepository($this->db());
        $this->importProfiles->ensureSchema();

        $this->allegroParameters = new ProductAllegroParameterRepository($this->db());
        $this->allegroParameters->ensureSchema();

        $this->customFields = new ProductCustomFieldRepository($this->db());
        $this->customFields->ensureSchema();

        $this->titleTemplates = new CsvTitleTemplateRepository($this->db());
        $this->titleTemplates->ensureSchema();

        $this->sharedStockGroups = new SharedStockGroupRepository($this->db());
        $this->sharedStockGroups->ensureSchema();

        $this->derivedStockLinks = new DerivedStockLinkRepository($this->db());
        $this->derivedStockLinks->ensureSchema();

        $this->exportService = new CsvExportService();
    }

    public function index(): void
    {
        $this->requireRole('admin');

        $this->render('csv_templates/index', array(
            'pageTitle' => 'Szablony CSV',
            'contentTitle' => 'Szablony eksportu CSV',
            'pageDescription' => 'Tworzenie i zarzadzanie konfiguracjami eksportu produktow.',
            'breadcrumbCurrent' => 'Szablony CSV',
            'templates' => $this->templates->all(),
            'presets' => $this->presetDefinitions(),
        ));
    }

    public function importproducts(): void
    {
        $this->requireRole('admin');

        $this->renderImportPage(array(
            'flashError' => $this->getFlash('error'),
            'flashSuccess' => $this->getFlash('success'),
        ));
    }

    public function previewimport(): void
    {
        $this->requireRole('admin');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=csvtemplates&action=importproducts');
        }

        $this->releaseSessionLock();

        try {
            $upload = $this->storeImportUpload('csv_file');
            $config = $this->importConfigFromInput();
            $analysis = $this->readImportedCsv($upload['path'], $config, 5);

            $this->renderImportPage(array(
                'stage' => 'mapping',
                'importToken' => $upload['token'],
                'importConfig' => $config,
                'importMapping' => $this->normalizedImportMapping(array(), count($analysis['headers'])),
                'columnTransforms' => $this->normalizedImportColumnTransforms(array(), count($analysis['headers'])),
                'selectedImportProfile' => null,
                'csvHeaders' => $analysis['headers'],
                'csvSampleRows' => $analysis['rows'],
                'mappingOptions' => $this->availableImportFieldOptions(),
                'detectedDelimiter' => $analysis['delimiter'],
                'detectedEncoding' => $analysis['encoding'],
            ));
        } catch (Throwable $exception) {
            $this->renderImportPage(array(
                'flashError' => $exception->getMessage(),
            ));
        }
    }

    public function remapimport(): void
    {
        $this->requireRole('admin');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=csvtemplates&action=importproducts');
        }

        $this->releaseSessionLock();

        try {
            $token = trim((string) $this->input('import_token', ''));
            $path = $this->importFilePathFromToken($token);
            $config = $this->importConfigFromInput();
            $selectedProfile = $this->selectedImportProfileFromInput($this->input('source', ''));
            if ($selectedProfile) {
                $config = $this->mergeImportConfigWithProfile($config, $selectedProfile);
            }

            $analysis = $this->readImportedCsv($path, $config, 5);
            $mapping = $this->normalizedImportMapping(
                $selectedProfile && isset($selectedProfile['mapping']) && is_array($selectedProfile['mapping']) ? $selectedProfile['mapping'] : $this->input('column_mapping', array()),
                count($analysis['headers'])
            );
            $columnTransforms = $this->normalizedImportColumnTransforms(
                $selectedProfile && isset($selectedProfile['column_transforms']) && is_array($selectedProfile['column_transforms']) ? $selectedProfile['column_transforms'] : $this->input('column_transforms', array()),
                count($analysis['headers'])
            );

            $this->renderImportPage(array(
                'stage' => 'mapping',
                'importToken' => $token,
                'importConfig' => $config,
                'importMapping' => $mapping,
                'columnTransforms' => $columnTransforms,
                'selectedImportProfile' => $selectedProfile,
                'csvHeaders' => $analysis['headers'],
                'csvSampleRows' => $analysis['rows'],
                'mappingOptions' => $this->availableImportFieldOptions(),
                'detectedDelimiter' => $analysis['delimiter'],
                'detectedEncoding' => $analysis['encoding'],
            ));
        } catch (Throwable $exception) {
            $this->renderImportPage(array(
                'flashError' => $exception->getMessage(),
            ));
        }
    }

    public function runimport(): void
    {
        $this->requireRole('admin');
        $this->requireWriteAccess();

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=csvtemplates&action=importproducts');
        }

        $this->releaseSessionLock();

        try {
            $token = trim((string) $this->input('import_token', ''));
            $path = $this->importFilePathFromToken($token);
            $config = $this->importConfigFromInput();
            $analysis = $this->readImportedCsv($path, $config);
            $mapping = $this->normalizedImportMapping($this->input('column_mapping', array()), count($analysis['headers']));
            $columnTransforms = $this->normalizedImportColumnTransforms($this->input('column_transforms', array()), count($analysis['headers']));
            $config['column_transforms'] = $columnTransforms;
            $this->assertImportUpdateIdentifierMapping($mapping, $config, $analysis['headers']);
            $savedProfileId = $this->saveImportProfileFromInput($config, $mapping, $columnTransforms);
            $result = $this->importCsvRows($analysis['rows'], $mapping, $config);

            $this->removeImportFile($path);

            $this->renderImportPage(array(
                'stage' => 'result',
                'importResult' => $result,
                'savedImportProfileId' => $savedProfileId,
                'mappingOptions' => $this->availableImportFieldOptions(),
            ));
        } catch (Throwable $exception) {
            $token = trim((string) $this->input('import_token', ''));
            if ($token !== '') {
                try {
                    $path = $this->importFilePathFromToken($token);
                    $config = $this->importConfigFromInput();
                    $analysis = $this->readImportedCsv($path, $config, 5);
                    $mapping = $this->normalizedImportMapping($this->input('column_mapping', array()), count($analysis['headers']));
                    $columnTransforms = $this->normalizedImportColumnTransforms($this->input('column_transforms', array()), count($analysis['headers']));

                    $this->renderImportPage(array(
                        'stage' => 'mapping',
                        'flashError' => $exception->getMessage(),
                        'importToken' => $token,
                        'importConfig' => $config,
                        'importMapping' => $mapping,
                        'columnTransforms' => $columnTransforms,
                        'selectedImportProfile' => $this->selectedImportProfileFromInput($this->input('source', '')),
                        'csvHeaders' => $analysis['headers'],
                        'csvSampleRows' => $analysis['rows'],
                        'mappingOptions' => $this->availableImportFieldOptions(),
                        'detectedDelimiter' => $analysis['delimiter'],
                        'detectedEncoding' => $analysis['encoding'],
                    ));
                    return;
                } catch (Throwable $ignored) {
                }
            }

            $this->renderImportPage(array(
                'flashError' => $exception->getMessage(),
            ));
        }
    }

    public function create(): void
    {
        $this->requireRole('admin');

        $presetKey = trim((string) $this->input('preset', ''));
        $template = $this->defaultTemplateFormData();

        $presets = $this->presetDefinitions();
        if ($presetKey !== '' && isset($presets[$presetKey])) {
            $template = $presets[$presetKey];
        }

        $this->render('csv_templates/form', array(
            'pageTitle' => 'Nowy szablon CSV',
            'contentTitle' => 'Dodaj szablon CSV',
            'pageDescription' => 'Skonfiguruj kolumny i format eksportu.',
            'breadcrumbCurrent' => 'Nowy szablon',
            'formAction' => './index.php?controller=csvtemplates&action=store',
            'template' => $template,
            'availableFields' => $this->availableFieldOptions(),
            'availableFieldsJson' => json_encode($this->availableFieldOptions()),
            'availableFunctions' => $this->availableComputedFunctions(),
            'availableFunctionsJson' => json_encode($this->availableComputedFunctions()),
            'presets' => $presets,
            'previewCsv' => '',
            'templateColumnsJson' => json_encode(isset($template['columns']) ? $template['columns'] : array()),
        ));
    }

    public function titlegenerator(): void
    {
        $this->requireRole('admin');

        $this->render('csv_templates/title_generator', array(
            'pageTitle' => 'Generator tytulow CSV',
            'contentTitle' => 'Generator tytulow CSV',
            'pageDescription' => 'Gotowe schematy tytulow do uzycia w eksporcie CSV.',
            'breadcrumbCurrent' => 'Generator tytulow',
            'titleTemplates' => $this->titleTemplates->all(),
            'availableTitleTokens' => $this->availableTitleTokens(),
        ));
    }

    public function createtitle(): void
    {
        $this->requireRole('admin');

        $this->render('csv_templates/title_form', array(
            'pageTitle' => 'Nowy szablon tytulu',
            'contentTitle' => 'Dodaj szablon tytulu',
            'pageDescription' => 'Zbuduj wzor tytulu z tokenow wybieranych z listy.',
            'breadcrumbCurrent' => 'Nowy szablon tytulu',
            'formAction' => './index.php?controller=csvtemplates&action=storetitle',
            'titleTemplate' => $this->defaultTitleTemplateData(),
            'availableTitleTokens' => $this->availableTitleTokens(),
        ));
    }

    public function storetitle(): void
    {
        $this->requireRole('admin');
        $this->requireWriteAccess();

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=csvtemplates&action=titlegenerator');
        }

        try {
            $data = $this->validatedTitleTemplateData();
            if ($this->titleTemplates->existsByName($data['name'])) {
                throw new RuntimeException('Szablon tytulu o takiej nazwie juz istnieje.');
            }

            $this->titleTemplates->create($data);
            $this->setFlash('success', 'Szablon tytulu zostal dodany.');
            $this->redirect('./index.php?controller=csvtemplates&action=titlegenerator');
        } catch (Throwable $exception) {
            $this->renderTitleFormWithError('storetitle', null, $exception->getMessage());
        }
    }

    public function edittitle(): void
    {
        $this->requireRole('admin');

        $id = (int) $this->input('id', 0);
        $titleTemplate = $this->titleTemplates->findById($id);

        if (!$titleTemplate) {
            $this->setFlash('error', 'Nie znaleziono szablonu tytulu.');
            $this->redirect('./index.php?controller=csvtemplates&action=titlegenerator');
        }

        $this->render('csv_templates/title_form', array(
            'pageTitle' => 'Edycja szablonu tytulu',
            'contentTitle' => 'Edytuj szablon tytulu',
            'pageDescription' => 'Zmien wzor i tokeny dla tytulu eksportowego.',
            'breadcrumbCurrent' => 'Edycja szablonu tytulu',
            'formAction' => './index.php?controller=csvtemplates&action=updatetitle&id=' . $id,
            'titleTemplate' => $titleTemplate,
            'availableTitleTokens' => $this->availableTitleTokens(),
        ));
    }

    public function updatetitle(): void
    {
        $this->requireRole('admin');
        $this->requireWriteAccess();

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=csvtemplates&action=titlegenerator');
        }

        $id = (int) $this->input('id', 0);
        $existing = $this->titleTemplates->findById($id);
        if (!$existing) {
            $this->setFlash('error', 'Nie znaleziono szablonu tytulu.');
            $this->redirect('./index.php?controller=csvtemplates&action=titlegenerator');
        }

        try {
            $data = $this->validatedTitleTemplateData();
            if ($this->titleTemplates->existsByName($data['name'], $id)) {
                throw new RuntimeException('Szablon tytulu o takiej nazwie juz istnieje.');
            }

            $this->titleTemplates->update($id, $data);
            $this->setFlash('success', 'Szablon tytulu zostal zaktualizowany.');
            $this->redirect('./index.php?controller=csvtemplates&action=titlegenerator');
        } catch (Throwable $exception) {
            $this->renderTitleFormWithError('updatetitle', $id, $exception->getMessage());
        }
    }

    public function deletetitle(): void
    {
        $this->requireRole('admin');
        $this->requireWriteAccess();

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=csvtemplates&action=titlegenerator');
        }

        try {
            $id = (int) $this->input('id', 0);
            if ($id <= 0) {
                throw new RuntimeException('Nieprawidlowe ID szablonu tytulu.');
            }

            $this->titleTemplates->delete($id);
            $this->setFlash('success', 'Szablon tytulu zostal usuniety.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=csvtemplates&action=titlegenerator');
    }

    public function store(): void
    {
        $this->requireRole('admin');
        $this->requireWriteAccess();

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=csvtemplates&action=index');
        }

        try {
            $templateData = $this->validatedTemplateData();
            $columns = $this->validatedColumnsPayload();

            if ($this->templates->existsByName($templateData['name'])) {
                throw new RuntimeException('Szablon o takiej nazwie juz istnieje.');
            }

            $this->templates->create($templateData, $columns);
            $this->setFlash('success', 'Szablon CSV zostal dodany.');
            $this->redirect('./index.php?controller=csvtemplates&action=index');
        } catch (Throwable $exception) {
            $this->renderFormWithError('store', null, $exception->getMessage());
        }
    }

    public function edit(): void
    {
        $this->requireRole('admin');

        $id = (int) $this->input('id', 0);
        $template = $this->templates->findFullById($id);

        if (!$template) {
            $this->setFlash('error', 'Nie znaleziono szablonu.');
            $this->redirect('./index.php?controller=csvtemplates&action=index');
        }

        $this->render('csv_templates/form', array(
            'pageTitle' => 'Edycja szablonu CSV',
            'contentTitle' => 'Edytuj szablon CSV',
            'pageDescription' => 'Zmien ustawienia eksportu i kolumny.',
            'breadcrumbCurrent' => 'Edycja szablonu',
            'formAction' => './index.php?controller=csvtemplates&action=update&id=' . $id,
            'template' => $template,
            'availableFields' => $this->availableFieldOptions(),
            'availableFieldsJson' => json_encode($this->availableFieldOptions()),
            'availableFunctions' => $this->availableComputedFunctions(),
            'availableFunctionsJson' => json_encode($this->availableComputedFunctions()),
            'presets' => $this->presetDefinitions(),
            'previewCsv' => '',
            'templateColumnsJson' => json_encode(isset($template['columns']) ? $template['columns'] : array()),
        ));
    }

    public function update(): void
    {
        $this->requireRole('admin');
        $this->requireWriteAccess();

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=csvtemplates&action=index');
        }

        $id = (int) $this->input('id', 0);
        $existing = $this->templates->findById($id);

        if (!$existing) {
            $this->setFlash('error', 'Nie znaleziono szablonu.');
            $this->redirect('./index.php?controller=csvtemplates&action=index');
        }

        try {
            $templateData = $this->validatedTemplateData();
            $columns = $this->validatedColumnsPayload();

            if ($this->templates->existsByName($templateData['name'], $id)) {
                throw new RuntimeException('Szablon o takiej nazwie juz istnieje.');
            }

            $this->templates->update($id, $templateData, $columns);
            $this->setFlash('success', 'Szablon CSV zostal zaktualizowany.');
            $this->redirect('./index.php?controller=csvtemplates&action=index');
        } catch (Throwable $exception) {
            $this->renderFormWithError('update', $id, $exception->getMessage());
        }
    }

    public function delete(): void
    {
        $this->requireRole('admin');
        $this->requireWriteAccess();

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=csvtemplates&action=index');
        }

        try {
            $id = (int) $this->input('id', 0);
            if ($id <= 0) {
                throw new RuntimeException('Nieprawidlowe ID szablonu.');
            }

            $this->templates->delete($id);
            $this->setFlash('success', 'Szablon CSV zostal usuniety.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=csvtemplates&action=index');
    }

    public function duplicate(): void
    {
        $this->requireRole('admin');
        $this->requireWriteAccess();

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=csvtemplates&action=index');
        }

        try {
            $id = (int) $this->input('id', 0);
            if ($id <= 0) {
                throw new RuntimeException('Nieprawidlowe ID szablonu.');
            }

            $this->templates->duplicate($id);
            $this->setFlash('success', 'Szablon CSV zostal zduplikowany.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=csvtemplates&action=index');
    }

    public function preview(): void
    {
        $this->requireRole('admin');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=csvtemplates&action=index');
        }

        $templateId = (int) $this->input('id', 0);
        $mode = $templateId > 0 ? 'update' : 'store';

        try {
            $templateData = $this->validatedTemplateData();
            $columns = $this->validatedColumnsPayload();

            $previewTemplate = $templateData;
            $previewTemplate['id'] = $templateId;
            $previewTemplate['columns'] = $this->columnsForPreview($columns);

            $products = $this->products->exportRows(array(), 10);
            $csv = $this->exportService->buildCsv($previewTemplate, $products, 10, $this->exportOptionsFromInput());

            $formAction = $mode === 'update'
                ? './index.php?controller=csvtemplates&action=update&id=' . $templateId
                : './index.php?controller=csvtemplates&action=store';

            $this->render('csv_templates/form', array(
                'pageTitle' => 'Podglad szablonu CSV',
                'contentTitle' => 'Podglad szablonu CSV',
                'pageDescription' => 'Zweryfikuj pierwsze 10 rekordow przed zapisem.',
                'breadcrumbCurrent' => 'Podglad CSV',
                'formAction' => $formAction,
                'template' => $previewTemplate,
                'availableFields' => $this->availableFieldOptions(),
                'availableFieldsJson' => json_encode($this->availableFieldOptions()),
                'availableFunctions' => $this->availableComputedFunctions(),
                'availableFunctionsJson' => json_encode($this->availableComputedFunctions()),
                'presets' => $this->presetDefinitions(),
                'previewCsv' => $csv,
                'templateColumnsJson' => json_encode(isset($previewTemplate['columns']) ? $previewTemplate['columns'] : array()),
            ));
        } catch (Throwable $exception) {
            $this->renderFormWithError($mode, $templateId > 0 ? $templateId : null, $exception->getMessage());
        }
    }

    public function exportcsv(): void
    {
        $this->requireModule('products');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=products&action=index');
        }

        $this->releaseSessionLock();

        try {
            $result = $this->prepareExportResponseData((int) $this->input('template_id', 0), false);
            $this->sendCsvDownload($result['csv'], $result['template']);
            return;
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
            $this->redirect('./index.php?controller=products&action=index');
        }
    }

    public function apiexport(): void
    {
        $this->requireModule('products');

        if (!$this->isPost()) {
            http_response_code(405);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('error' => 'Dozwolona jest tylko metoda POST.'));
            return;
        }

        $this->releaseSessionLock();

        try {
            $templateId = (int) $this->input('id', 0);
            $result = $this->prepareExportResponseData($templateId, true);
            $this->sendCsvDownload($result['csv'], $result['template']);
        } catch (Throwable $exception) {
            http_response_code(422);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('error' => $exception->getMessage()));
        }
    }

    private function validatedTemplateData(): array
    {
        $name = trim((string) $this->input('name', ''));
        $description = trim((string) $this->input('description', ''));
        $delimiter = (string) $this->input('delimiter', ';');
        $encoding = strtoupper(trim((string) $this->input('encoding', 'UTF-8')));
        $addBom = $this->input('add_bom', '1') === '1' ? 1 : 0;
        $arraySeparator = (string) $this->input('array_separator', '|');

        if ($name === '') {
            throw new RuntimeException('Nazwa szablonu jest wymagana.');
        }

        if (!in_array($delimiter, array(',', ';', '|'), true)) {
            throw new RuntimeException('Niepoprawny separator CSV.');
        }

        if (!in_array($encoding, array('UTF-8', 'WINDOWS-1250'), true)) {
            throw new RuntimeException('Niepoprawne kodowanie.');
        }

        if ($arraySeparator === '') {
            $arraySeparator = '|';
        }

        return array(
            'name' => $name,
            'description' => ($description !== '' ? $description : null),
            'delimiter' => $delimiter,
            'encoding' => $encoding,
            'add_bom' => $addBom,
            'array_separator' => $arraySeparator,
        );
    }

    private function validatedTitleTemplateData(): array
    {
        $name = trim((string) $this->input('name', ''));
        $description = trim((string) $this->input('description', ''));
        $templateBody = trim((string) $this->input('template_body', ''));

        if ($name === '') {
            throw new RuntimeException('Nazwa szablonu tytulu jest wymagana.');
        }

        if ($templateBody === '') {
            throw new RuntimeException('Wzor tytulu jest wymagany.');
        }

        return array(
            'name' => $name,
            'description' => ($description !== '' ? $description : null),
            'template_body' => $templateBody,
        );
    }

    private function validatedColumnsPayload(): array
    {
        $payloadRaw = (string) $this->input('columns_payload', '[]');
        $payload = json_decode($payloadRaw, true);

        if (!is_array($payload)) {
            throw new RuntimeException('Niepoprawny format kolumn szablonu.');
        }

        if ($payload === array()) {
            throw new RuntimeException('Dodaj przynajmniej jedna kolumne eksportu.');
        }

        $columns = array();
        foreach ($payload as $column) {
            if (!is_array($column)) {
                continue;
            }

            $header = trim((string) ($column['header_name'] ?? ''));
            $sourceType = strtolower(trim((string) ($column['source_type'] ?? 'field')));
            $sourceValue = trim((string) ($column['source_value'] ?? ''));
            $settings = isset($column['settings']) && is_array($column['settings']) ? $this->normalizeColumnSettings($column['settings']) : $this->normalizeColumnSettings(array());
            $mappings = isset($column['mappings']) && is_array($column['mappings']) ? $column['mappings'] : array();

            if ($header === '') {
                throw new RuntimeException('Kazda kolumna musi miec naglowek.');
            }

            if (!in_array($sourceType, array('field', 'static', 'computed'), true)) {
                throw new RuntimeException('Niepoprawny typ zrodla kolumny: ' . $header);
            }

            if ($sourceType !== 'static' && $sourceValue === '') {
                throw new RuntimeException('Brak zrodla wartosci dla kolumny: ' . $header);
            }

            if ($sourceType === 'computed' && $settings['function'] === '') {
                throw new RuntimeException('Wybierz funkcje dla kolumny obliczanej: ' . $header);
            }

            $normalizedMappings = array();
            foreach ($mappings as $mapping) {
                if (!is_array($mapping)) {
                    continue;
                }

                $from = trim((string) ($mapping['from_value'] ?? ''));
                $to = (string) ($mapping['to_value'] ?? '');

                if ($from === '') {
                    continue;
                }

                $normalizedMappings[] = array(
                    'from_value' => $from,
                    'to_value' => $to,
                );
            }

            $columns[] = array(
                'header_name' => $header,
                'source_type' => $sourceType,
                'source_value' => $sourceValue,
                'settings' => $settings,
                'mappings' => $normalizedMappings,
            );
        }

        if ($columns === array()) {
            throw new RuntimeException('Brak poprawnych kolumn eksportu.');
        }

        return $columns;
    }

    private function renderFormWithError(string $mode, ?int $id, string $error): void
    {
        $templateData = $this->defaultTemplateFormData();

        $templateData['id'] = $id ?? 0;
        $templateData['name'] = (string) $this->input('name', '');
        $templateData['description'] = (string) $this->input('description', '');
        $templateData['delimiter'] = (string) $this->input('delimiter', ';');
        $templateData['encoding'] = (string) $this->input('encoding', 'UTF-8');
        $templateData['add_bom'] = $this->input('add_bom', '1') === '1' ? 1 : 0;
        $templateData['array_separator'] = (string) $this->input('array_separator', '|');

        $columnsRaw = (string) $this->input('columns_payload', '[]');
        $columns = json_decode($columnsRaw, true);
        $templateData['columns'] = is_array($columns) ? $this->columnsForPreview($columns) : array();

        $this->render('csv_templates/form', array(
            'pageTitle' => $mode === 'update' ? 'Edycja szablonu CSV' : 'Nowy szablon CSV',
            'contentTitle' => $mode === 'update' ? 'Edytuj szablon CSV' : 'Dodaj szablon CSV',
            'pageDescription' => 'Popraw bledy formularza i zapisz szablon.',
            'breadcrumbCurrent' => $mode === 'update' ? 'Edycja szablonu' : 'Nowy szablon',
            'formAction' => $mode === 'update' && $id !== null
                ? './index.php?controller=csvtemplates&action=update&id=' . $id
                : './index.php?controller=csvtemplates&action=store',
            'template' => $templateData,
            'availableFields' => $this->availableFieldOptions(),
            'availableFieldsJson' => json_encode($this->availableFieldOptions()),
            'availableFunctions' => $this->availableComputedFunctions(),
            'availableFunctionsJson' => json_encode($this->availableComputedFunctions()),
            'presets' => $this->presetDefinitions(),
            'previewCsv' => '',
            'templateColumnsJson' => json_encode(isset($templateData['columns']) ? $templateData['columns'] : array()),
            'flashError' => $error,
        ));
    }

    private function renderTitleFormWithError(string $mode, ?int $id, string $error): void
    {
        $titleTemplate = $this->defaultTitleTemplateData();
        $titleTemplate['id'] = $id ?? 0;
        $titleTemplate['name'] = (string) $this->input('name', '');
        $titleTemplate['description'] = (string) $this->input('description', '');
        $titleTemplate['template_body'] = (string) $this->input('template_body', '');

        $this->render('csv_templates/title_form', array(
            'pageTitle' => $mode === 'updatetitle' ? 'Edycja szablonu tytulu' : 'Nowy szablon tytulu',
            'contentTitle' => $mode === 'updatetitle' ? 'Edytuj szablon tytulu' : 'Dodaj szablon tytulu',
            'pageDescription' => 'Popraw bledy formularza i zapisz szablon tytulu.',
            'breadcrumbCurrent' => $mode === 'updatetitle' ? 'Edycja szablonu tytulu' : 'Nowy szablon tytulu',
            'formAction' => $mode === 'updatetitle' && $id !== null
                ? './index.php?controller=csvtemplates&action=updatetitle&id=' . $id
                : './index.php?controller=csvtemplates&action=storetitle',
            'titleTemplate' => $titleTemplate,
            'availableTitleTokens' => $this->availableTitleTokens(),
            'flashError' => $error,
        ));
    }

    private function renderImportPage(array $data = array()): void
    {
        $requestedImportMode = $this->normalizeImportMode($this->input('import_mode', ''));
        $defaults = array(
            'pageTitle' => 'Import produktow CSV',
            'contentTitle' => 'Import produktow CSV',
            'pageDescription' => 'Wczytaj plik CSV, sprawdz kolumny i zdecyduj, do jakich pol produktow maja trafic dane.',
            'breadcrumbCurrent' => 'Import CSV',
            'sourceContext' => trim((string) $this->input('source', '')),
            'backUrl' => './index.php?controller=csvtemplates&action=index',
            'backLabel' => 'Wroc do szablonow',
            'stage' => 'upload',
            'importToken' => '',
            'importConfig' => array(
                'import_mode' => $requestedImportMode,
                'delimiter' => 'auto',
                'encoding' => 'UTF-8',
                'has_header' => 1,
                'target_category_id' => 0,
                'update_identifier_column' => '',
                'derived_link_columns' => array(),
                'derived_link_old_sku_columns' => array(),
                'derived_link_old_sku_match_column' => '',
            ),
            'importMapping' => array(),
            'columnTransforms' => array(),
            'selectedImportProfile' => null,
            'savedImportProfileId' => 0,
            'csvHeaders' => array(),
            'csvSampleRows' => array(),
            'mappingOptions' => $this->availableImportFieldOptions(),
            'importResult' => array(),
            'detectedDelimiter' => '',
            'detectedEncoding' => '',
            'categories' => $this->categories->all(),
            'importProfiles' => $this->importProfiles->allForSelect(trim((string) $this->input('source', ''))),
        );

        if ($defaults['sourceContext'] === 'products') {
            $defaults['backUrl'] = './index.php?controller=products&action=index';
            $defaults['backLabel'] = 'Wroc do produktow';
        }

        $this->render('csv_templates/import', array_merge($defaults, $data));
    }

    private function availableImportFieldOptions(): array
    {
        $options = array('__skip__' => 'Pomin kolumne');
        foreach ($this->availableFieldOptions() as $key => $label) {
            $options[$key] = $label;
        }

        return $options;
    }

    private function importConfigFromInput(): array
    {
        $importMode = $this->normalizeImportMode($this->input('import_mode', ''));
        $delimiter = trim((string) $this->input('delimiter', 'auto'));
        $encoding = strtoupper(trim((string) $this->input('encoding', 'UTF-8')));
        $hasHeader = $this->input('has_header', '1') === '1' ? 1 : 0;

        if (!in_array($delimiter, array('auto', ';', ',', '|', "\t"), true)) {
            $delimiter = 'auto';
        }

        if (!in_array($encoding, array('UTF-8', 'WINDOWS-1250'), true)) {
            $encoding = 'UTF-8';
        }

        return array(
            'import_mode' => $importMode,
            'delimiter' => $delimiter,
            'encoding' => $encoding,
            'has_header' => $hasHeader,
            'target_category_id' => max(0, (int) $this->input('target_category_id', 0)),
            'update_identifier_column' => $this->normalizeImportSingleColumnIndex($this->input('update_identifier_column', '')),
            'derived_link_columns' => $this->normalizeImportColumnIndexes($this->input('derived_link_columns', array())),
            'derived_link_old_sku_columns' => $this->normalizeImportColumnIndexes($this->input('derived_link_old_sku_columns', array())),
            'derived_link_old_sku_match_column' => $this->normalizeImportSingleColumnIndex($this->input('derived_link_old_sku_match_column', '')),
        );
    }

    private function storeImportUpload(string $field): array
    {
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
            throw new RuntimeException('Wybierz plik CSV do importu.');
        }

        $file = $_FILES[$field];
        $error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Nie udalo sie przeslac pliku CSV.');
        }

        $tmpName = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Brak poprawnego pliku tymczasowego CSV.');
        }

        $storageDir = $this->importStorageDir();
        if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
            throw new RuntimeException('Nie udalo sie utworzyc katalogu importu CSV.');
        }

        $token = bin2hex(random_bytes(16));
        $target = $storageDir . DIRECTORY_SEPARATOR . $token . '.csv';

        if (!move_uploaded_file($tmpName, $target)) {
            throw new RuntimeException('Nie udalo sie zapisac przeslanego CSV.');
        }

        return array(
            'token' => $token,
            'path' => $target,
        );
    }

    private function importStorageDir(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Storage' . DIRECTORY_SEPARATOR . 'imports';
    }

    private function importFilePathFromToken(string $token): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            throw new RuntimeException('Nieprawidlowy token importu CSV.');
        }

        $path = $this->importStorageDir() . DIRECTORY_SEPARATOR . $token . '.csv';
        if (!is_file($path)) {
            throw new RuntimeException('Plik importu wygasl albo nie istnieje. Wgraj CSV ponownie.');
        }

        return $path;
    }

    private function removeImportFile(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function readImportedCsv(string $path, array $config, int $limit = 0): array
    {
        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines) || $lines === array()) {
            throw new RuntimeException('CSV jest pusty albo nie mozna go odczytac.');
        }

        $delimiter = $config['delimiter'] === 'auto'
            ? $this->detectCsvDelimiter((string) $lines[0])
            : (string) $config['delimiter'];

        $encoding = (string) ($config['encoding'] ?? 'UTF-8');
        $rows = array();
        $headers = array();
        $rowIndex = 0;

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Nie mozna otworzyc przeslanego pliku CSV.');
        }

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $converted = array();
            foreach ($row as $cell) {
                $converted[] = $this->convertImportEncoding((string) $cell, $encoding, $rowIndex === 0);
            }

            if ($rowIndex === 0 && !empty($config['has_header'])) {
                $headers = $this->normalizeImportedHeaders($converted);
            } else {
                if ($headers === array()) {
                    $headers = $this->generatedImportHeaders(count($converted));
                }

                $rows[] = $this->combineImportRow($headers, $converted);
                if ($limit > 0 && count($rows) >= $limit) {
                    break;
                }
            }

            $rowIndex++;
        }

        fclose($handle);

        if ($headers === array()) {
            throw new RuntimeException('Nie udalo sie odczytac kolumn z CSV.');
        }

        return array(
            'headers' => $headers,
            'rows' => $rows,
            'delimiter' => $delimiter === "\t" ? 'TAB' : $delimiter,
            'encoding' => $encoding,
        );
    }

    private function detectCsvDelimiter(string $line): string
    {
        $candidates = array(';', ',', '|', "\t");
        $best = ';';
        $bestCount = -1;

        foreach ($candidates as $candidate) {
            $count = substr_count($line, $candidate);
            if ($count > $bestCount) {
                $best = $candidate;
                $bestCount = $count;
            }
        }

        return $best;
    }

    private function convertImportEncoding(string $value, string $encoding, bool $isFirstRow = false): string
    {
        if ($encoding === 'WINDOWS-1250') {
            $converted = @iconv('WINDOWS-1250', 'UTF-8//IGNORE', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }

        if ($isFirstRow) {
            $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
        }

        return trim($value);
    }

    private function normalizeImportedHeaders(array $headers): array
    {
        $result = array();
        foreach ($headers as $index => $header) {
            $header = trim((string) $header);
            $result[] = $header !== '' ? $header : 'Kolumna ' . ($index + 1);
        }

        return $result;
    }

    private function generatedImportHeaders(int $count): array
    {
        $headers = array();
        for ($index = 0; $index < $count; $index++) {
            $headers[] = 'Kolumna ' . ($index + 1);
        }

        return $headers;
    }

    private function combineImportRow(array $headers, array $values): array
    {
        $row = array();
        foreach ($headers as $index => $header) {
            $row[$header] = isset($values[$index]) ? (string) $values[$index] : '';
        }

        return $row;
    }

    private function normalizedImportMapping($input, int $columnCount): array
    {
        $mapping = is_array($input) ? $input : array();
        $normalized = array();

        for ($index = 0; $index < $columnCount; $index++) {
            $value = isset($mapping[$index]) ? trim((string) $mapping[$index]) : '__skip__';
            $normalized[$index] = $value !== '' ? $value : '__skip__';
        }

        return $normalized;
    }

    private function importCsvRows(array $rows, array $mapping, array $config = array()): array
    {
        $summary = array(
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => array(),
            'warnings' => array(),
        );
        $derivedGroups = array();
        $derivedLinksByOwner = array();
        $importedDerivedLookup = array();
        $derivedMatchColumnIndex = $this->derivedOldSkuMatchColumnIndex($config);

        foreach ($rows as $rowNumber => $row) {
            try {
                $prepared = $this->prepareImportedRow($row, $mapping, $config);
                if ($prepared['empty']) {
                    $summary['skipped']++;
                    continue;
                }

                foreach ($prepared['warnings'] as $warning) {
                    $summary['warnings'][] = 'Wiersz ' . ($rowNumber + 2) . ': ' . $warning;
                }

                $existing = $this->findImportedProductTarget($prepared['lookup'], $prepared, $mapping, $config);
                $productId = 0;

                if ($existing) {
                    $productId = (int) $existing['id'];
                    $manualTimestamps = array();
                    if (isset($prepared['product_data']['created_at'])) {
                        $manualTimestamps['created_at'] = (string) $prepared['product_data']['created_at'];
                        unset($prepared['product_data']['created_at']);
                    }
                    if (isset($prepared['product_data']['updated_at'])) {
                        $manualTimestamps['updated_at'] = (string) $prepared['product_data']['updated_at'];
                        unset($prepared['product_data']['updated_at']);
                    }
                    if ($prepared['product_data'] !== array()) {
                        $this->products->updateById($productId, $prepared['product_data']);
                    }
                    if ($manualTimestamps !== array()) {
                        $this->db()->update('products', $manualTimestamps, 'id = :id', array('id' => $productId));
                    }
                    $summary['updated']++;
                } else {
                    $data = $prepared['product_data'];
                    if (empty($data['product_name'])) {
                        throw new RuntimeException('Brak nazwy produktu do utworzenia nowego rekordu.');
                    }

                    if (empty($data['category_id'])) {
                        $data['category_id'] = $this->categories->ensureDefaultCategory();
                    }

                    if (empty($data['sku'])) {
                        $category = $this->categories->findById((int) $data['category_id']);
                        $data['sku'] = $this->products->generateNextSkuByPrefix(isset($category['sku_prefix']) ? (string) $category['sku_prefix'] : 'PRD');
                    }

                    $productId = (int) $this->products->create($data);
                    $summary['created']++;
                }

                $this->applyImportedRelatedData($productId, $prepared);
                $this->rememberImportedDerivedLookupValue($importedDerivedLookup, $row, $derivedMatchColumnIndex, $productId);

                if (!empty($prepared['derived_link_groups']) && $productId > 0) {
                    foreach ($prepared['derived_link_groups'] as $group) {
                        $code = isset($group['code']) ? trim((string) $group['code']) : '';
                        $customFieldSlug = isset($group['custom_field_slug']) ? trim((string) $group['custom_field_slug']) : '';
                        if ($code === '') {
                            continue;
                        }

                        $groupKey = $customFieldSlug . '|' . $code;
                        if (!isset($derivedGroups[$groupKey])) {
                            $derivedGroups[$groupKey] = array(
                                'code' => $code,
                                'custom_field_slug' => $customFieldSlug,
                                'product_ids' => array(),
                            );
                        }

                        $derivedGroups[$groupKey]['product_ids'][] = $productId;
                    }
                }

                if (!empty($prepared['derived_old_sku_values']) && $productId > 0) {
                    if (!isset($derivedLinksByOwner[$productId])) {
                        $derivedLinksByOwner[$productId] = array();
                    }

                    $derivedLinksByOwner[$productId] = array_values(array_unique(array_merge(
                        $derivedLinksByOwner[$productId],
                        $prepared['derived_old_sku_values']
                    )));
                }
            } catch (Throwable $exception) {
                $summary['errors'][] = 'Wiersz ' . ($rowNumber + 2) . ': ' . $exception->getMessage();
            }
        }

        foreach ($derivedGroups as $group) {
            $uniqueProductIds = array_values(array_unique(array_filter(array_map('intval', $group['product_ids']))));
            if (!empty($group['custom_field_slug']) && !empty($group['code'])) {
                $existingProductIds = $this->customFields->findProductIdsBySlugAndValue((string) $group['custom_field_slug'], (string) $group['code']);
                $uniqueProductIds = array_values(array_unique(array_merge($uniqueProductIds, $existingProductIds)));
            }

            if ($uniqueProductIds === array()) {
                continue;
            }

            $this->sharedStockGroups->syncProductsWithinSharedGroup($uniqueProductIds);
            if (count($uniqueProductIds) > 1) {
                $summary['warnings'][] = 'Powiazano produkty dla kodu ' . $group['code'] . ' w grupie ' . count($uniqueProductIds) . ' rekordow.';
            }
        }

        foreach ($derivedLinksByOwner as $ownerProductId => $oldSkuValues) {
            $sourceProductIds = $this->resolveImportedDerivedSourceIds(
                $oldSkuValues,
                $mapping,
                $config,
                $importedDerivedLookup,
                (int) $ownerProductId
            );
            $this->derivedStockLinks->syncSourcesForProduct((int) $ownerProductId, $sourceProductIds);

            if ($sourceProductIds !== array()) {
                $summary['warnings'][] = 'Ustawiono powiazania pochodne po OLD_SKU dla produktu ID '
                    . (int) $ownerProductId
                    . ': '
                    . count($sourceProductIds)
                    . ' zrodel.';
            }
        }

        return $summary;
    }

    private function prepareImportedRow(array $row, array $mapping, array $config = array()): array
    {
        $headers = array_values(array_keys($row));
        $productData = array();
        $lookup = array('id' => 0, 'sku' => '');
        $categoryContext = array();
        $customFieldValues = array();
        $allegroParameterValues = array();
        $derivedLinkGroups = array();
        $warnings = array();
        $nonEmptyValues = 0;
        $derivedColumnIndexes = isset($config['derived_link_columns']) && is_array($config['derived_link_columns'])
            ? array_values(array_unique(array_map('intval', $config['derived_link_columns'])))
            : array();
        $derivedOldSkuColumnIndexes = isset($config['derived_link_old_sku_columns']) && is_array($config['derived_link_old_sku_columns'])
            ? array_values(array_unique(array_map('intval', $config['derived_link_old_sku_columns'])))
            : array();
        $derivedOldSkuValues = array();

        foreach ($headers as $index => $header) {
            $field = isset($mapping[$index]) ? (string) $mapping[$index] : '__skip__';
            $value = isset($row[$header]) ? trim((string) $row[$header]) : '';
            $value = $this->applyImportColumnTransform($value, $config, $index);

            if ($value !== '') {
                $nonEmptyValues++;
            }

            if (in_array($index, $derivedColumnIndexes, true)) {
                $derivedCode = strtoupper($value);
                if ($derivedCode !== '') {
                    $derivedLinkGroup = array(
                        'code' => $derivedCode,
                        'custom_field_slug' => strpos($field, 'product.custom_fields.') === 0 ? substr($field, 22) : '',
                    );
                    if (!in_array($derivedLinkGroup, $derivedLinkGroups, true)) {
                        $derivedLinkGroups[] = $derivedLinkGroup;
                    }
                }
            }

            if (in_array($index, $derivedOldSkuColumnIndexes, true)) {
                foreach ($this->splitImportedDerivedOldSkuList($value) as $oldSkuValue) {
                    if (!in_array($oldSkuValue, $derivedOldSkuValues, true)) {
                        $derivedOldSkuValues[] = $oldSkuValue;
                    }
                }
            }

            if ($field === '' || $field === '__skip__' || $value === '') {
                continue;
            }

            switch ($field) {
                case 'product.id':
                    $lookup['id'] = ctype_digit($value) ? (int) $value : 0;
                    break;
                case 'product.sku':
                    $lookup['sku'] = $value;
                    $productData['sku'] = $value;
                    break;
                case 'product.ean':
                    $productData['ean'] = $value;
                    break;
                case 'product.product_name':
                    $productData['product_name'] = $value;
                    break;
                case 'product.description':
                    $productData['description'] = $value;
                    break;
                case 'product.quantity':
                    $productData['quantity'] = max(0, (int) preg_replace('/[^\d\-]+/', '', str_replace(',', '.', $value)));
                    break;
                case 'product.localization':
                    $productData['localization'] = $value;
                    break;
                case 'product.dimensions':
                    $productData['dimensions'] = $value;
                    break;
                case 'product.contours':
                    $productData['contours'] = $value;
                    break;
                case 'product.img':
                case 'product.images[0].url':
                    $productData['img'] = $value;
                    break;
                case 'product.images':
                    $productData['img'] = $this->normalizeImportImageList($value);
                    break;
                case 'product.price_net':
                    $productData['price_net'] = $this->normalizeImportedDecimal($value);
                    break;
                case 'product.price_gross':
                    $productData['price_gross'] = $this->normalizeImportedDecimal($value);
                    break;
                case 'product.vat_rate':
                    $productData['vat_rate'] = $this->normalizeImportedDecimal($value);
                    break;
                case 'product.category_name':
                    $categoryContext['name'] = $value;
                    break;
                case 'product.category_slug':
                    $categoryContext['slug'] = $value;
                    break;
                case 'product.category_id_allegro':
                    $categoryContext['allegro_category_id'] = $value;
                    break;
                case 'product.created_at':
                    $productData['created_at'] = $this->normalizeImportedDateTime($value);
                    break;
                case 'product.updated_at':
                    $productData['updated_at'] = $this->normalizeImportedDateTime($value);
                    break;
                case 'product.allegro_parameters':
                case 'product.generated_title':
                case 'product.generated_images':
                case 'product.price_to_csv':
                    $warnings[] = 'pole ' . $field . ' jest przeznaczone glownie do eksportu i zostalo pominiete';
                    break;
                default:
                    if (strpos($field, 'product.custom_fields.') === 0) {
                        $customFieldValues[substr($field, 22)] = $value;
                    } elseif (strpos($field, 'product.allegro_parameter.') === 0) {
                        $allegroParameterValues[substr($field, 26)] = $value;
                    }
                    break;
            }
        }

        $forcedCategoryId = max(0, (int) ($config['target_category_id'] ?? 0));
        if ($forcedCategoryId > 0) {
            $productData['category_id'] = $forcedCategoryId;
        } elseif ($categoryContext !== array()) {
            $productData['category_id'] = $this->resolveImportedCategoryId($categoryContext);
        }

        return array(
            'empty' => $nonEmptyValues === 0,
            'lookup' => $lookup,
            'product_data' => $productData,
            'custom_fields' => $customFieldValues,
            'allegro_parameters' => $allegroParameterValues,
            'derived_link_groups' => $derivedLinkGroups,
            'derived_old_sku_values' => $derivedOldSkuValues,
            'warnings' => $warnings,
        );
    }

    private function splitImportedDerivedOldSkuList(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return array();
        }

        $tokens = preg_split('/[\s,;|]+/', $value) ?: array();
        $result = array();

        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token !== '') {
                $result[] = $token;
            }
        }

        return array_values(array_unique($result));
    }

    private function resolveImportedDerivedSourceIds(
        array $referenceValues,
        array $mapping,
        array $config,
        array $importedLookup,
        int $ownerProductId
    ): array
    {
        $sourceProductIds = array();
        $matchField = $this->derivedOldSkuMatchField($mapping, $config);

        foreach ($referenceValues as $referenceValue) {
            $referenceValue = trim((string) $referenceValue);
            if ($referenceValue === '') {
                continue;
            }

            if (isset($importedLookup[$referenceValue])) {
                $sourceProductIds = array_merge($sourceProductIds, $importedLookup[$referenceValue]);
            }

            $matchedIds = $this->resolveImportedDerivedSourceIdsByField($matchField, $referenceValue);
            if ($matchedIds !== array()) {
                $sourceProductIds = array_merge($sourceProductIds, $matchedIds);
            }
        }

        return array_values(array_unique(array_filter(array_map('intval', $sourceProductIds), static function (int $id): bool {
            return $id > 0 && $id !== $ownerProductId;
        })));
    }

    private function derivedOldSkuMatchColumnIndex(array $config): ?int
    {
        if (!isset($config['derived_link_old_sku_match_column'])) {
            return null;
        }

        $value = trim((string) $config['derived_link_old_sku_match_column']);
        if ($value === '' || !ctype_digit($value)) {
            return null;
        }

        return (int) $value;
    }

    private function derivedOldSkuMatchField(array $mapping, array $config): string
    {
        $columnIndex = $this->derivedOldSkuMatchColumnIndex($config);
        if ($columnIndex === null || !isset($mapping[$columnIndex])) {
            return 'product.custom_fields.old_sku';
        }

        $field = trim((string) $mapping[$columnIndex]);
        return $field !== '' && $field !== '__skip__' ? $field : 'product.custom_fields.old_sku';
    }

    private function rememberImportedDerivedLookupValue(array &$lookup, array $row, ?int $columnIndex, int $productId): void
    {
        if ($productId <= 0 || $columnIndex === null) {
            return;
        }

        $columnValue = $this->importedColumnValueByIndex($row, $columnIndex);
        if ($columnValue === '') {
            return;
        }

        if (!isset($lookup[$columnValue])) {
            $lookup[$columnValue] = array();
        }

        if (!in_array($productId, $lookup[$columnValue], true)) {
            $lookup[$columnValue][] = $productId;
        }
    }

    private function importedColumnValueByIndex(array $row, int $index): string
    {
        $headers = array_values(array_keys($row));
        if (!isset($headers[$index])) {
            return '';
        }

        return trim((string) ($row[$headers[$index]] ?? ''));
    }

    private function resolveImportedDerivedSourceIdsByField(string $field, string $referenceValue): array
    {
        if ($field === 'product.id' && ctype_digit($referenceValue)) {
            $product = $this->products->find((int) $referenceValue);
            return $product ? array((int) $product['id']) : array();
        }

        if ($field === 'product.sku') {
            $product = $this->products->findBySku($referenceValue);
            return $product ? array((int) $product['id']) : array();
        }

        if ($field === 'product.ean') {
            $matches = $this->db()->fetchAll(
                'SELECT id FROM products WHERE deleted_at IS NULL AND ean = :ean',
                array('ean' => $referenceValue)
            );

            return array_values(array_filter(array_map(static function (array $row): int {
                return isset($row['id']) ? (int) $row['id'] : 0;
            }, $matches)));
        }

        if (strpos($field, 'product.custom_fields.') === 0) {
            return $this->customFields->findProductIdsBySlugAndValue(substr($field, 22), $referenceValue);
        }

        return $this->customFields->findProductIdsBySlugAndValue('old_sku', $referenceValue);
    }

    private function findImportedProductTarget(array $lookup, array $prepared = array(), array $mapping = array(), array $config = array())
    {
        if (!$this->isUpdateImportMode($config)) {
            return false;
        }

        $identifierField = $this->importUpdateIdentifierField($mapping, $config);
        if ($identifierField !== '') {
            $identifierValue = $this->importedIdentifierValueForField($identifierField, $lookup, $prepared);
            if ($identifierValue !== '') {
                $matched = $this->findImportedProductTargetByField($identifierField, $identifierValue);
                if ($matched) {
                    return $matched;
                }
            }
        }

        if (!empty($lookup['sku'])) {
            $bySku = $this->products->findBySku((string) $lookup['sku']);
            if ($bySku) {
                return $bySku;
            }
        }

        if (!empty($lookup['id'])) {
            return $this->products->find((int) $lookup['id']);
        }

        return false;
    }

    private function importUpdateIdentifierColumnIndex(array $config): ?int
    {
        if (!$this->isUpdateImportMode($config)) {
            return null;
        }

        if (!isset($config['update_identifier_column'])) {
            return null;
        }

        $value = trim((string) $config['update_identifier_column']);
        if ($value === '' || !ctype_digit($value)) {
            return null;
        }

        return (int) $value;
    }

    private function importUpdateIdentifierField(array $mapping, array $config): string
    {
        $columnIndex = $this->importUpdateIdentifierColumnIndex($config);
        if ($columnIndex === null || !isset($mapping[$columnIndex])) {
            return '';
        }

        $field = trim((string) $mapping[$columnIndex]);
        if ($field === 'product.id' || $field === 'product.sku' || $field === 'product.ean') {
            return $field;
        }

        if (strpos($field, 'product.custom_fields.') === 0) {
            return $field;
        }

        return '';
    }

    private function assertImportUpdateIdentifierMapping(array $mapping, array $config, array $headers = array()): void
    {
        $columnIndex = $this->importUpdateIdentifierColumnIndex($config);
        if ($columnIndex === null) {
            return;
        }

        $field = $this->importUpdateIdentifierField($mapping, $config);
        if ($field !== '') {
            return;
        }

        $headerLabel = isset($headers[$columnIndex]) ? (string) $headers[$columnIndex] : ('kolumna #' . ($columnIndex + 1));
        throw new RuntimeException(
            'Wybrana kolumna identyfikatora aktualizacji ("' . $headerLabel . '") musi byc zmapowana do ID produktu, SKU, EAN albo pola wlasnego.'
        );
    }

    private function importedIdentifierValueForField(string $field, array $lookup, array $prepared): string
    {
        if ($field === 'product.id') {
            return !empty($lookup['id']) ? (string) $lookup['id'] : '';
        }

        if ($field === 'product.sku') {
            return trim((string) ($lookup['sku'] ?? ''));
        }

        if ($field === 'product.ean') {
            return trim((string) (($prepared['product_data']['ean'] ?? '')));
        }

        if (strpos($field, 'product.custom_fields.') === 0) {
            $slug = substr($field, 22);
            return trim((string) (($prepared['custom_fields'][$slug] ?? '')));
        }

        return '';
    }

    private function findImportedProductTargetByField(string $field, string $value)
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        if ($field === 'product.id' && ctype_digit($value)) {
            return $this->products->find((int) $value);
        }

        if ($field === 'product.sku') {
            return $this->products->findBySku($value);
        }

        if ($field === 'product.ean') {
            $row = $this->db()->fetch(
                'SELECT id FROM products WHERE deleted_at IS NULL AND ean = :ean ORDER BY id ASC LIMIT 1',
                array('ean' => $value)
            );

            return !empty($row['id']) ? $this->products->find((int) $row['id']) : false;
        }

        if (strpos($field, 'product.custom_fields.') === 0) {
            $slug = substr($field, 22);
            $ids = $this->customFields->findProductIdsBySlugAndValue($slug, $value);
            if (!empty($ids[0])) {
                return $this->products->find((int) $ids[0]);
            }
        }

        return false;
    }

    private function applyImportedRelatedData(int $productId, array $prepared): void
    {
        if ($productId <= 0) {
            return;
        }

        if (!empty($prepared['custom_fields'])) {
            $existing = $this->customFields->valuesForProduct($productId);
            $merged = array();

            foreach ($existing as $field) {
                if (!empty($field['definition_id']) && isset($field['value']) && (string) $field['value'] !== '') {
                    $merged[(int) $field['definition_id']] = (string) $field['value'];
                }
            }

            foreach ($prepared['custom_fields'] as $slug => $value) {
                $definition = $this->customFields->ensureDefinition((string) $slug);
                if (!empty($definition['id'])) {
                    $merged[(int) $definition['id']] = (string) $value;
                }
            }

            $this->customFields->replaceForProduct($productId, $merged);
        }

        if (!empty($prepared['allegro_parameters'])) {
            $existing = $this->allegroParameters->allForProduct($productId);
            foreach ($prepared['allegro_parameters'] as $parameterId => $value) {
                $existing[(string) $parameterId] = array((string) $value);
            }

            $this->allegroParameters->replaceForProduct($productId, $existing);
        }
    }

    private function normalizeImportImageList(string $value): string
    {
        $parts = preg_split('/\s*(\||,|;|\r\n|\n)\s*/', $value) ?: array();
        $items = array();

        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part !== '' && !in_array($part, $items, true)) {
                $items[] = $part;
            }
        }

        return implode(' | ', $items);
    }

    private function normalizeImportColumnIndexes($input): array
    {
        if (!is_array($input)) {
            return array();
        }

        $indexes = array();
        foreach ($input as $index) {
            if (is_numeric($index)) {
                $indexes[] = (int) $index;
            }
        }

        return array_values(array_unique(array_filter($indexes, function (int $index): bool {
            return $index >= 0;
        })));
    }

    private function normalizeImportSingleColumnIndex($input): string
    {
        if (is_numeric($input)) {
            $index = (int) $input;
            if ($index >= 0) {
                return (string) $index;
            }
        }

        return '';
    }

    private function normalizedImportColumnTransforms($input, int $columnCount): array
    {
        $input = is_array($input) ? $input : array();
        $normalized = array();

        for ($index = 0; $index < $columnCount; $index++) {
            $row = isset($input[$index]) && is_array($input[$index]) ? $input[$index] : array();
            $normalized[$index] = array(
                'find' => trim((string) ($row['find'] ?? '')),
                'replace' => (string) ($row['replace'] ?? ''),
            );
        }

        return $normalized;
    }

    private function normalizeImportMode($input): string
    {
        $mode = strtolower(trim((string) $input));
        return $mode === 'update' ? 'update' : 'create';
    }

    private function isUpdateImportMode(array $config): bool
    {
        return $this->normalizeImportMode($config['import_mode'] ?? '') === 'update';
    }

    private function applyImportColumnTransform(string $value, array $config, int $columnIndex): string
    {
        $transforms = isset($config['column_transforms']) && is_array($config['column_transforms'])
            ? $config['column_transforms']
            : array();

        $transform = isset($transforms[$columnIndex]) && is_array($transforms[$columnIndex])
            ? $transforms[$columnIndex]
            : array();

        $find = trim((string) ($transform['find'] ?? ''));
        if ($find === '') {
            return $value;
        }

        return str_replace($find, (string) ($transform['replace'] ?? ''), $value);
    }

    private function selectedImportProfileFromInput($sourceContext = '')
    {
        $profileId = max(0, (int) $this->input('import_profile_id', 0));
        if ($profileId <= 0) {
            return null;
        }

        $profile = $this->importProfiles->findById($profileId);
        if (!$profile) {
            return null;
        }

        $sourceContext = trim((string) $sourceContext);
        $profileSource = trim((string) ($profile['source_context'] ?? ''));
        if ($sourceContext !== '' && $profileSource !== '' && $profileSource !== $sourceContext) {
            return null;
        }

        return $profile;
    }

    private function mergeImportConfigWithProfile(array $config, array $profile): array
    {
        $config['import_mode'] = $this->normalizeImportMode($profile['import_mode'] ?? ($config['import_mode'] ?? 'create'));
        $config['delimiter'] = trim((string) ($profile['delimiter'] ?? ($config['delimiter'] ?? 'auto')));
        $config['encoding'] = strtoupper(trim((string) ($profile['encoding'] ?? ($config['encoding'] ?? 'UTF-8'))));
        $config['has_header'] = !empty($profile['has_header']) ? 1 : 0;
        $config['target_category_id'] = max(0, (int) ($profile['target_category_id'] ?? ($config['target_category_id'] ?? 0)));
        $config['update_identifier_column'] = $this->normalizeImportSingleColumnIndex($profile['update_identifier_column'] ?? ($config['update_identifier_column'] ?? ''));
        $config['derived_link_columns'] = $this->normalizeImportColumnIndexes($profile['derived_link_columns'] ?? ($config['derived_link_columns'] ?? array()));
        $config['derived_link_old_sku_columns'] = $this->normalizeImportColumnIndexes($profile['derived_link_old_sku_columns'] ?? ($config['derived_link_old_sku_columns'] ?? array()));
        $config['derived_link_old_sku_match_column'] = $this->normalizeImportSingleColumnIndex($profile['derived_link_old_sku_match_column'] ?? ($config['derived_link_old_sku_match_column'] ?? ''));

        return $config;
    }

    private function saveImportProfileFromInput(array $config, array $mapping, array $columnTransforms): int
    {
        $saveProfile = $this->input('save_import_profile', '') === '1';
        if (!$saveProfile) {
            return 0;
        }

        $profileName = trim((string) $this->input('import_profile_name', ''));
        if ($profileName === '') {
            throw new RuntimeException('Podaj nazwe profilu importu, jesli chcesz zapisac ustawienia.');
        }

        $profileId = max(0, (int) $this->input('import_profile_id', 0));
        return $this->importProfiles->save($profileId, $profileName, array(
            'source_context' => trim((string) $this->input('source', '')),
            'import_mode' => $config['import_mode'] ?? 'create',
            'delimiter' => $config['delimiter'] ?? 'auto',
            'encoding' => $config['encoding'] ?? 'UTF-8',
            'has_header' => !empty($config['has_header']) ? 1 : 0,
            'target_category_id' => max(0, (int) ($config['target_category_id'] ?? 0)),
            'update_identifier_column' => $config['update_identifier_column'] ?? '',
            'derived_link_columns' => $config['derived_link_columns'] ?? array(),
            'derived_link_old_sku_columns' => $config['derived_link_old_sku_columns'] ?? array(),
            'derived_link_old_sku_match_column' => $config['derived_link_old_sku_match_column'] ?? '',
            'mapping' => $mapping,
            'column_transforms' => $columnTransforms,
        ));
    }

    private function normalizeImportedDecimal(string $value): string
    {
        $value = str_replace(' ', '', trim($value));
        $value = str_replace(',', '.', $value);
        if ($value === '' || !is_numeric($value)) {
            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function normalizeImportedDateTime(string $value): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new RuntimeException('Niepoprawna data: ' . $value);
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function resolveImportedCategoryId(array $context): int
    {
        if (!empty($context['allegro_category_id'])) {
            foreach ($this->categories->all() as $category) {
                if ((string) ($category['allegro_category_id'] ?? '') === (string) $context['allegro_category_id']) {
                    return (int) $category['id'];
                }
            }
        }

        if (!empty($context['slug'])) {
            $category = $this->categories->findBySlug((string) $context['slug']);
            if ($category) {
                return (int) $category['id'];
            }
        }

        if (!empty($context['name'])) {
            $needle = function_exists('mb_strtolower')
                ? mb_strtolower((string) $context['name'], 'UTF-8')
                : strtolower((string) $context['name']);

            foreach ($this->categories->all() as $category) {
                $candidate = function_exists('mb_strtolower')
                    ? mb_strtolower((string) ($category['name'] ?? ''), 'UTF-8')
                    : strtolower((string) ($category['name'] ?? ''));
                if ($candidate === $needle) {
                    return (int) $category['id'];
                }
            }

            $name = trim((string) $context['name']);
            if ($name !== '') {
                return (int) $this->categories->create(array(
                    'name' => $name,
                    'slug' => $this->categories->uniqueSlug($name),
                    'sku_prefix' => 'PRD',
                    'allegro_category_id' => !empty($context['allegro_category_id']) ? (string) $context['allegro_category_id'] : null,
                    'description' => 'Kategoria utworzona podczas importu CSV.',
                ));
            }
        }

        return $this->categories->ensureDefaultCategory();
    }

    private function defaultTemplateFormData(): array
    {
        return array(
            'id' => 0,
            'name' => '',
            'description' => '',
            'delimiter' => ';',
            'encoding' => 'UTF-8',
            'add_bom' => 1,
            'array_separator' => '|',
            'columns' => array(),
        );
    }

    private function defaultTitleTemplateData(): array
    {
        return array(
            'id' => 0,
            'name' => '',
            'description' => '',
            'template_body' => '',
        );
    }

    private function availableFieldOptions(): array
    {
        $options = array(
            'product.id' => 'ID produktu',
            'product.sku' => 'SKU',
            'product.ean' => 'EAN',
            'product.product_name' => 'Nazwa produktu',
            'product.description' => 'Opis',
            'product.quantity' => 'Ilosc',
            'product.localization' => 'Lokalizacja',
            'product.dimensions' => 'Wymiary',
            'product.contours' => 'Obrys',
            'product.img' => 'Sciezka obrazka',
            'product.images[0].url' => 'Pierwsze zdjecie URL',
            'product.images' => 'Wszystkie zdjecia',
            'product.allegro_parameters' => 'Parametry Allegro (nazwa: wartosc)',
            'product.generated_title' => 'Generowany tytul CSV',
            'product.generated_images' => 'Generowane sciezki obrazów (EU)',
            'product.price_net' => 'Cena netto',
            'product.price_gross' => 'Cena brutto',
            'product.price_to_csv' => 'Cena wpisana przy eksporcie CSV',
            'product.vat_rate' => 'VAT',
            'product.category_name' => 'Nazwa kategorii',
            'product.category_slug' => 'Slug kategorii',
            'product.category_id_allegro' => 'ID kategorii Allegro',
            'product.created_at' => 'Data utworzenia',
            'product.updated_at' => 'Data modyfikacji',
        );

        foreach ($this->allegroParameters->usedParameterFieldOptions() as $fieldKey => $fieldLabel) {
            $options[$fieldKey] = $fieldLabel;
        }

        foreach ($this->customFields->definitions() as $definition) {
            $slug = isset($definition['slug']) ? (string) $definition['slug'] : '';
            $name = isset($definition['name']) ? (string) $definition['name'] : $slug;
            if ($slug === '') {
                continue;
            }

            $options['product.custom_fields.' . $slug] = 'Pole wlasne: ' . $name;
        }

        return $options;
    }

    private function availableComputedFunctions(): array
    {
        return array(
            'concat' => 'concat (laczenie wartosci)',
            'upper' => 'upper',
            'lower' => 'lower',
            'substring' => 'substring',
            'replace' => 'replace',
            'number_format' => 'number_format',
            'custom' => 'custom callback',
        );
    }

    private function availableTitleTokens(): array
    {
        $tokens = array(
            '{{option:collection_name}}' => 'Kolekcja wpisana przy eksporcie',
            '{{option:price_to_csv}}' => 'Cena wpisana przy eksporcie',
        );

        foreach ($this->availableFieldOptions() as $fieldKey => $fieldLabel) {
            if ($fieldKey === 'product.generated_title') {
                continue;
            }
            $tokens['{{field:' . $fieldKey . '}}'] = $fieldLabel;
        }

        return $tokens;
    }

    private function normalizeColumnSettings(array $settings): array
    {
        $args = isset($settings['args']) && is_array($settings['args']) ? $settings['args'] : array();
        $condition = isset($settings['condition']) && is_array($settings['condition']) ? $settings['condition'] : array();

        return array(
            'function' => trim((string) ($settings['function'] ?? '')),
            'args' => $args,
            'format' => trim((string) ($settings['format'] ?? '')),
            'array_separator' => trim((string) ($settings['array_separator'] ?? '')),
            'condition' => array(
                'field' => trim((string) ($condition['field'] ?? '')),
                'operator' => trim((string) ($condition['operator'] ?? 'eq')),
                'value' => (string) ($condition['value'] ?? ''),
                'then' => (string) ($condition['then'] ?? ''),
                'else' => (string) ($condition['else'] ?? ''),
            ),
        );
    }

    private function prepareExportResponseData(int $templateId, bool $allowJsonPayload): array
    {
        if ($templateId <= 0) {
            throw new RuntimeException('Wybierz szablon eksportu.');
        }

        $template = $this->templates->findFullById($templateId);
        if (!$template) {
            throw new RuntimeException('Nie znaleziono wybranego szablonu.');
        }

        $requestData = $allowJsonPayload ? $this->requestDataForApi() : array();
        $mode = isset($requestData['export_mode']) ? (string) $requestData['export_mode'] : (string) $this->input('export_mode', 'selected');
        $ids = isset($requestData['product_ids']) ? $requestData['product_ids'] : $this->input('product_ids', array());
        $productIds = $this->normalizeProductIds($ids);

        if ($mode !== 'all' && $productIds === array()) {
            throw new RuntimeException('Zaznacz produkty lub wybierz eksport wszystkich.');
        }

        $rows = $mode === 'all' ? $this->products->exportRows(array()) : $this->products->exportRows($productIds);

        if ($rows === array()) {
            $debugContext = array(
                'template_id' => $templateId,
                'mode' => $mode,
                'product_ids' => $productIds,
            );

            if (function_exists('app_log')) {
                app_log('CSV export returned no product rows: ' . json_encode($debugContext), 'WARNING');
            }
        }

        $csv = $this->exportService->buildCsv($template, $rows, 0, $this->exportOptionsFromInput($requestData));

        return array(
            'template' => $template,
            'csv' => $csv,
        );
    }

    private function requestDataForApi(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return array();
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : array();
    }

    private function normalizeProductIds($ids): array
    {
        if (!is_array($ids)) {
            return array();
        }

        $productIds = array();
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $productIds[] = $id;
            }
        }

        return array_values(array_unique($productIds));
    }

    private function sendCsvDownload(string $csv, array $template): void
    {
        $filenameBase = trim((string) ($template['name'] ?? 'produkty'));
        $filenameBase = preg_replace('/[^A-Za-z0-9\-_]+/', '-', $filenameBase);
        $filenameBase = trim((string) $filenameBase, '-');

        if ($filenameBase === '') {
            $filenameBase = 'produkty';
        }

        $filename = $filenameBase . '-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=' . ($template['encoding'] ?? 'UTF-8'));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $csv;
    }

    private function exportOptionsFromInput(array $requestData = array()): array
    {
        $source = function (string $key, $default = '') use ($requestData) {
            if (array_key_exists($key, $requestData)) {
                return $requestData[$key];
            }

            return $this->input($key, $default);
        };

        return array(
            'title_template_id' => max(0, (int) $source('title_template_id', 0)),
            'collection_name' => trim((string) $source('collection_name', $source('image_collection_name', ''))),
            'image_collection_code' => trim((string) $source('image_collection_code', '')),
            'image_collection_name' => trim((string) $source('image_collection_name', '')),
            'image_title_suffix' => trim((string) $source('image_title_suffix', '')),
            'price_to_csv' => trim((string) $source('price_to_csv', '')),
            'image_count' => max(0, (int) $source('image_count', 0)),
            'thumbnail_count' => max(0, (int) $source('thumbnail_count', 0)),
            'grid_count' => max(0, (int) $source('grid_count', 0)),
            'image_base_directory' => trim((string) $source('image_base_directory', 'T:\\wygnerowane_do_EU')),
        ) + $this->titleTemplateExportOptions(max(0, (int) $source('title_template_id', 0)));
    }

    private function columnsForPreview(array $columns): array
    {
        $result = array();

        foreach ($columns as $index => $column) {
            if (!is_array($column)) {
                continue;
            }

            $result[] = array(
                'id' => $index + 1,
                'header_name' => (string) ($column['header_name'] ?? ''),
                'source_type' => (string) ($column['source_type'] ?? 'field'),
                'source_value' => $this->normalizeLegacySourceValue((string) ($column['source_value'] ?? ''), (string) ($column['source_type'] ?? 'field')),
                'settings' => isset($column['settings']) && is_array($column['settings']) ? $column['settings'] : array(),
                'mappings' => isset($column['mappings']) && is_array($column['mappings']) ? $column['mappings'] : array(),
            );
        }

        return $result;
    }

    private function presetDefinitions(): array
    {
        return array(
            'allegro' => array(
                'name' => 'Allegro basic',
                'description' => 'Preset eksportu pod wystawianie ofert Allegro.',
                'delimiter' => ';',
                'encoding' => 'UTF-8',
                'add_bom' => 1,
                'array_separator' => '|',
                'columns' => array(
                    array('header_name' => 'SKU', 'source_type' => 'field', 'source_value' => 'product.sku', 'settings' => array(), 'mappings' => array()),
                    array('header_name' => 'Nazwa', 'source_type' => 'field', 'source_value' => 'product.product_name', 'settings' => array(), 'mappings' => array()),
                    array('header_name' => 'Cena', 'source_type' => 'field', 'source_value' => 'product.price_gross', 'settings' => array(), 'mappings' => array()),
                    array('header_name' => 'Stan', 'source_type' => 'field', 'source_value' => 'product.quantity', 'settings' => array(), 'mappings' => array()),
                ),
            ),
            'google_merchant' => array(
                'name' => 'Google Merchant basic',
                'description' => 'Preset zgodny z podstawowymi polami Google Merchant.',
                'delimiter' => ',',
                'encoding' => 'UTF-8',
                'add_bom' => 1,
                'array_separator' => '|',
                'columns' => array(
                    array('header_name' => 'id', 'source_type' => 'field', 'source_value' => 'product.sku', 'settings' => array(), 'mappings' => array()),
                    array('header_name' => 'title', 'source_type' => 'field', 'source_value' => 'product.product_name', 'settings' => array(), 'mappings' => array()),
                    array('header_name' => 'description', 'source_type' => 'field', 'source_value' => 'product.description', 'settings' => array(), 'mappings' => array()),
                    array('header_name' => 'price', 'source_type' => 'computed', 'source_value' => 'computed', 'settings' => array('function' => 'concat', 'args' => array('separator' => ' ', 'parts' => array('field:product.price_gross', 'PLN'))), 'mappings' => array()),
                    array('header_name' => 'image_link', 'source_type' => 'field', 'source_value' => 'product.images[0].url', 'settings' => array(), 'mappings' => array()),
                ),
            ),
            'facebook_ads' => array(
                'name' => 'Facebook Ads basic',
                'description' => 'Preset katalogu produktowego Facebook Ads.',
                'delimiter' => ';',
                'encoding' => 'UTF-8',
                'add_bom' => 1,
                'array_separator' => '|',
                'columns' => array(
                    array('header_name' => 'id', 'source_type' => 'field', 'source_value' => 'product.sku', 'settings' => array(), 'mappings' => array()),
                    array('header_name' => 'title', 'source_type' => 'field', 'source_value' => 'product.product_name', 'settings' => array(), 'mappings' => array()),
                    array('header_name' => 'description', 'source_type' => 'field', 'source_value' => 'product.description', 'settings' => array(), 'mappings' => array()),
                    array('header_name' => 'availability', 'source_type' => 'field', 'source_value' => 'product.quantity', 'settings' => array(), 'mappings' => array(
                        array('from_value' => '0', 'to_value' => 'out of stock'),
                    )),
                    array('header_name' => 'price', 'source_type' => 'computed', 'source_value' => 'computed', 'settings' => array('function' => 'concat', 'args' => array('separator' => ' ', 'parts' => array('field:product.price_gross', 'PLN'))), 'mappings' => array()),
                ),
            ),
        );
    }

    private function titleTemplateDefinitions(): array
    {
        return array(
            'phone_case_basic' => array(
                'name' => 'Szablon 1',
                'pattern' => 'Etui na Telefon {{dedicated_model}} {{dedicated_brand}} wzory {{collection}}',
                'description' => 'Buduje tytul z modelu i marki z parametrow Allegro oraz z kolekcji wpisanej przy generowaniu CSV.',
                'tokens' => array(
                    'dedicated_model' => 'Rozszyfrowana wartosc parametru Allegro dedykowanego modelu',
                    'dedicated_brand' => 'Rozszyfrowana wartosc parametru Allegro dedykowanej marki',
                    'collection' => 'Kolekcja wpisana podczas generowania CSV',
                ),
                'example' => 'Etui na Telefon Galaxy S24 Samsung wzory Marble',
            ),
        );
    }

    private function titleTemplateExportOptions(int $titleTemplateId): array
    {
        if ($titleTemplateId <= 0) {
            return array(
                'title_template_pattern' => '',
                'title_template_name' => '',
            );
        }

        $titleTemplate = $this->titleTemplates->findById($titleTemplateId);
        if (!$titleTemplate) {
            return array(
                'title_template_pattern' => '',
                'title_template_name' => '',
            );
        }

        return array(
            'title_template_pattern' => isset($titleTemplate['template_body']) ? (string) $titleTemplate['template_body'] : '',
            'title_template_name' => isset($titleTemplate['name']) ? (string) $titleTemplate['name'] : '',
        );
    }

    private function normalizeLegacySourceValue(string $sourceValue, string $sourceType): string
    {
        $sourceType = strtolower(trim($sourceType));
        $sourceValue = trim($sourceValue);

        if ($sourceType !== 'field') {
            return $sourceValue;
        }

        $aliases = array(
            'id' => 'product.id',
            'sku' => 'product.sku',
            'ean' => 'product.ean',
            'product.ean' => 'product.ean',
            'name' => 'product.product_name',
            'product.name' => 'product.product_name',
            'products.name' => 'product.product_name',
            'title' => 'product.product_name',
            'product.title' => 'product.product_name',
            'description' => 'product.description',
            'products.description' => 'product.description',
            'price' => 'product.price_gross',
            'products.price' => 'product.price_gross',
            'price_net' => 'product.price_net',
            'price_gross' => 'product.price_gross',
            'category' => 'product.category_name',
            'category.name' => 'product.category_name',
            'product.category.name' => 'product.category_name',
            'categories.name' => 'product.category_name',
        );

        return isset($aliases[$sourceValue]) ? $aliases[$sourceValue] : $sourceValue;
    }
}
