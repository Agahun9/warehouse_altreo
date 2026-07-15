<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\AccountingWarehouseRepository;
use App\Services\AccountingWarehouseClassifier;
use App\Services\AccountingWarehouseSupplierResolver;
use App\Services\AccountingWarehouseXmlImporter;
use RuntimeException;
use Throwable;

class AccountingWarehouseController extends Controller
{
    /** @var AccountingWarehouseRepository */
    private $warehouse;

    /** @var AccountingWarehouseClassifier */
    private $classifier;

    /** @var AccountingWarehouseXmlImporter */
    private $xmlImporter;

    /** @var AccountingWarehouseSupplierResolver */
    private $supplierResolver;

    public function __construct()
    {
        $this->classifier = new AccountingWarehouseClassifier();
        $this->warehouse = new AccountingWarehouseRepository($this->db(), $this->classifier);
        $this->warehouse->ensureSchema();
        $this->xmlImporter = new AccountingWarehouseXmlImporter($this->classifier);
        $this->supplierResolver = new AccountingWarehouseSupplierResolver();
    }

    public function index(): void
    {
        $currentUser = $this->requireModule('accountingwarehouse');

        $this->render('accounting_warehouse/index', array(
            'pageTitle' => 'Magazyn ksiegowy',
            'contentTitle' => 'Magazyn ksiegowy',
            'pageDescription' => 'Widok stanu i ostatnich ruchow magazynu ksiegowego.',
            'breadcrumbCurrent' => 'Magazyn ksiegowy',
            'currentUser' => $currentUser,
            'flashSuccess' => $this->getFlash('success'),
            'flashError' => $this->getFlash('error'),
            'overview' => $this->warehouse->overview(),
            'stockSummary' => $this->warehouse->stockSummary(),
            'recentMovements' => $this->warehouse->recentMovements(20),
        ));
    }

    public function item(): void
    {
        $currentUser = $this->requireModule('accountingwarehouse');
        $itemId = (int) $this->input('id', 0);
        $item = $this->warehouse->stockItemDetail($itemId);

        if ($item === null) {
            $this->setFlash('error', 'Nie znaleziono pozycji magazynowej.');
            $this->redirect('./index.php?controller=accountingwarehouse&action=index');
        }

        $this->render('accounting_warehouse/item', array(
            'pageTitle' => 'Pozycja ksiegowa: ' . (string) ($item['name'] ?? ''),
            'contentTitle' => 'Pozycja ksiegowa',
            'pageDescription' => 'Podglad zrodlowych nazw i szybkie przepinanie pozycji na bardziej pasujace.',
            'breadcrumbCurrent' => (string) ($item['name'] ?? 'Pozycja'),
            'currentUser' => $currentUser,
            'flashSuccess' => $this->getFlash('success'),
            'flashError' => $this->getFlash('error'),
            'item' => $item,
            'itemSuggestions' => $this->warehouse->itemNameSuggestions(),
        ));
    }

    public function documents(): void
    {
        $currentUser = $this->requireModule('accountingwarehouse');
        $filters = array(
            'document_number' => trim((string) $this->input('document_number', '')),
            'supplier_name' => trim((string) $this->input('supplier_name', '')),
            'supplier_tax_id' => trim((string) $this->input('supplier_tax_id', '')),
            'document_kind' => trim((string) $this->input('document_kind', '')),
            'invoice_type' => trim((string) $this->input('invoice_type', '')),
            'source_type' => trim((string) $this->input('source_type', '')),
            'currency' => trim((string) $this->input('currency', '')),
            'notes' => trim((string) $this->input('notes', '')),
            'date_from' => trim((string) $this->input('date_from', '')),
            'date_to' => trim((string) $this->input('date_to', '')),
            'added_from' => trim((string) $this->input('added_from', '')),
            'added_to' => trim((string) $this->input('added_to', '')),
            'amount_min' => trim((string) $this->input('amount_min', '')),
            'amount_max' => trim((string) $this->input('amount_max', '')),
        );

        $allowedPerPage = array(10, 50, 100, 500);
        $perPage = (int) $this->input('per_page', 50);
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 50;
        }
        $page = max(1, (int) $this->input('page', 1));
        $totalDocuments = $this->warehouse->documentCount($filters);
        $totalPages = max(1, (int) ceil($totalDocuments / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $filterQuery = http_build_query(array_filter($filters, static function (string $value): bool {
            return $value !== '';
        }));

        $this->render('accounting_warehouse/documents', array(
            'pageTitle' => 'Dokumenty magazynu ksiegowego',
            'contentTitle' => 'Dokumenty',
            'pageDescription' => 'Lista wszystkich faktur, importow XML i korekt w magazynie ksiegowym.',
            'breadcrumbCurrent' => 'Dokumenty',
            'currentUser' => $currentUser,
            'flashSuccess' => $this->getFlash('success'),
            'flashError' => $this->getFlash('error'),
            'documents' => $this->warehouse->documentList($filters, $perPage, $offset),
            'filters' => $filters,
            'currencyOptions' => $this->currencyOptions(),
            'page' => $page,
            'perPage' => $perPage,
            'allowedPerPage' => $allowedPerPage,
            'totalPages' => $totalPages,
            'totalDocuments' => $totalDocuments,
            'filterQuery' => $filterQuery,
        ));
    }

    public function bulkdelete(): void
    {
        $this->requireModuleWrite('accountingwarehouse');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=accountingwarehouse&action=documents');
        }

        $ids = array_map('intval', (array) $this->input('document_ids', array()));
        $queryString = trim((string) $this->input('return_query', ''));
        $redirectUrl = './index.php?controller=accountingwarehouse&action=documents' . ($queryString !== '' ? '&' . $queryString : '');

        try {
            $deleted = $this->warehouse->deleteDocuments($ids);
            if ($deleted > 0) {
                $this->setFlash('success', 'Usunieto dokumentow: ' . $deleted . '.');
            } else {
                $this->setFlash('error', 'Nie zaznaczono zadnych dokumentow do usuniecia.');
            }
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect($redirectUrl);
    }

    public function checknumbers(): void
    {
        $currentUser = $this->requireModule('accountingwarehouse');
        $rawList = trim((string) $this->input('numbers', ''));

        $results = array();
        $foundCount = 0;
        $missingCount = 0;
        if ($rawList !== '') {
            $numbers = $this->splitPastedNumberList($rawList);
            $results = $this->warehouse->matchDocumentsByNumbers($numbers);
            foreach ($results as $result) {
                if (!empty($result['found'])) {
                    $foundCount++;
                } else {
                    $missingCount++;
                }
            }
        }

        $this->render('accounting_warehouse/checknumbers', array(
            'pageTitle' => 'Sprawdz numery FV',
            'contentTitle' => 'Sprawdz numery FV',
            'pageDescription' => 'Wklej liste numerow faktur (jeden pod drugim), aby sprawdzic ktore z nich sa juz w magazynie ksiegowym.',
            'breadcrumbCurrent' => 'Sprawdz numery FV',
            'currentUser' => $currentUser,
            'flashSuccess' => $this->getFlash('success'),
            'flashError' => $this->getFlash('error'),
            'rawList' => $rawList,
            'results' => $results,
            'foundCount' => $foundCount,
            'missingCount' => $missingCount,
        ));
    }

    private function splitPastedNumberList(string $rawList): array
    {
        $pieces = preg_split('/[\r\n]+/', $rawList) ?: array();
        $numbers = array();
        foreach ($pieces as $piece) {
            $piece = trim($piece);
            if ($piece === '') {
                continue;
            }
            $numbers[] = $piece;
        }

        return $numbers;
    }

    public function issues(): void
    {
        $currentUser = $this->requireModule('accountingwarehouse');
        $month = trim((string) $this->input('month', date('Y-m')));
        if (preg_match('/^\d{4}\-\d{2}$/', $month) !== 1) {
            $month = date('Y-m');
        }

        $this->render('accounting_warehouse/issues', array(
            'pageTitle' => 'Wyjscia z magazynu',
            'contentTitle' => 'Wyjscia z magazynu',
            'pageDescription' => 'Miesieczny raport rozchodu magazynu ksiegowego z przypisaniem do faktur zrodlowych.',
            'breadcrumbCurrent' => 'Wyjscia z magazynu',
            'currentUser' => $currentUser,
            'flashSuccess' => $this->getFlash('success'),
            'flashError' => $this->getFlash('error'),
            'selectedMonth' => $month,
            'issueRows' => $this->warehouse->monthlyIssueReport($month),
        ));
    }

    public function exportissuesxlsx(): void
    {
        $this->requireModule('accountingwarehouse');
        $month = trim((string) $this->input('month', date('Y-m')));
        if (preg_match('/^\d{4}\-\d{2}$/', $month) !== 1) {
            $month = date('Y-m');
        }

        $rows = $this->warehouse->monthlyIssueReport($month);
        $totalQuantity = 0.0;
        $totalNet = 0.0;
        $totalGross = 0.0;
        foreach ($rows as $row) {
            $totalQuantity += (float) ($row['quantity'] ?? 0);
            $totalNet += (float) ($row['issue_value_net'] ?? 0);
            $totalGross += (float) ($row['issue_value_gross'] ?? 0);
        }

        $sheetRows = array();
        $sheetRows[] = array('Data', 'Dokument wyjscia', 'Pozycja ksiegowa', 'Z jakiej FV', 'Dostawca z FV', 'Sztuki', 'Cena netto PLN', 'Cena brutto PLN');
        foreach ($rows as $row) {
            $sheetRows[] = array(
                (string) ($row['issue_date'] ?? ''),
                (string) ($row['issue_document_number'] ?? ''),
                (string) ($row['item_name'] ?? ''),
                (string) ($row['source_document_number'] ?? ''),
                (string) ($row['source_supplier_name'] ?? ''),
                round((float) ($row['quantity'] ?? 0), 0),
                round((float) ($row['issue_value_net'] ?? 0), 2),
                round((float) ($row['issue_value_gross'] ?? 0), 2),
            );
        }
        $sheetRows[] = array('', '', '', '', 'SUMA', round($totalQuantity, 0), round($totalNet, 2), round($totalGross, 2));

        try {
            $binary = $this->buildSimpleXlsx('Rozchod ' . $month, $sheetRows);
            $filename = 'accounting_warehouse_issues_' . str_replace('-', '_', $month) . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($binary));
            echo $binary;
            exit;
        } catch (Throwable $exception) {
            $this->setFlash('error', 'Nie udalo sie przygotowac eksportu XLSX: ' . $exception->getMessage());
            $this->redirect('./index.php?controller=accountingwarehouse&action=issues&month=' . rawurlencode($month));
        }
    }

    public function export(): void
    {
        $currentUser = $this->requireModule('accountingwarehouse');
        list($dateFrom, $dateTo) = $this->resolveExportDateRange();

        $documents = $this->warehouse->documentsForExport($dateFrom, $dateTo);
        $counts = array('koszt' => 0, 'towar' => 0, 'mieszane' => 0, 'korekta' => 0);
        $lineKindCounts = array('towar' => 0, 'koszt' => 0, 'korekta' => 0);
        $documentKindCounts = array();
        $costLineCountBreakdown = array();
        $itemsWithCostLines = array();
        foreach ($documents as $document) {
            $classification = (string) ($document['classification'] ?? 'mieszane');
            if (isset($counts[$classification])) {
                $counts[$classification]++;
            }

            $documentKind = trim((string) ($document['document_kind'] ?? 'receipt')) ?: 'receipt';
            $documentKindCounts[$documentKind] = ($documentKindCounts[$documentKind] ?? 0) + 1;

            $kosztLinesInDocument = 0;
            foreach ((array) ($document['lines'] ?? array()) as $line) {
                $kind = (string) ($line['item_kind'] ?? 'towar');
                if (isset($lineKindCounts[$kind])) {
                    $lineKindCounts[$kind]++;
                }
                if ($kind === 'koszt') {
                    $kosztLinesInDocument++;
                    $name = (string) ($line['canonical_name'] ?? '');
                    if ($name !== '') {
                        $itemsWithCostLines[$name] = ($itemsWithCostLines[$name] ?? 0) + 1;
                    }
                }
            }
            $costLineCountBreakdown[$kosztLinesInDocument] = ($costLineCountBreakdown[$kosztLinesInDocument] ?? 0) + 1;
        }
        ksort($costLineCountBreakdown);
        arsort($itemsWithCostLines);

        $this->render('accounting_warehouse/export', array(
            'pageTitle' => 'Eksport XLSX',
            'contentTitle' => 'Eksport kosztow i towaru',
            'pageDescription' => 'Eksport faktur z magazynu ksiegowego do plikow XLSX z podzialem na koszty, towar i pozycje mieszane.',
            'breadcrumbCurrent' => 'Eksport XLSX',
            'currentUser' => $currentUser,
            'flashSuccess' => $this->getFlash('success'),
            'flashError' => $this->getFlash('error'),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'counts' => $counts,
            'totalDocuments' => count($documents),
            'lineKindCounts' => $lineKindCounts,
            'documentKindCounts' => $documentKindCounts,
            'costLineCountBreakdown' => $costLineCountBreakdown,
            'itemsWithCostLines' => array_slice($itemsWithCostLines, 0, 15, true),
        ));
    }

    public function exportcostsxlsx(): void
    {
        $this->requireModule('accountingwarehouse');
        list($dateFrom, $dateTo) = $this->resolveExportDateRange();

        $documents = array_values(array_filter(
            $this->warehouse->documentsForExport($dateFrom, $dateTo),
            static function (array $document): bool {
                return ((string) ($document['classification'] ?? '')) === 'koszt';
            }
        ));

        $sheetRows = array();
        $sheetRows[] = array('Data', 'Numer dokumentu', 'Dostawca', 'NIP', 'Pozycje', 'Netto', 'Brutto', 'Waluta');
        $totalNet = 0.0;
        $totalGross = 0.0;
        foreach ($documents as $document) {
            $lineNames = array();
            $documentNet = 0.0;
            $documentGross = 0.0;
            foreach ((array) ($document['lines'] ?? array()) as $line) {
                $lineNames[] = (string) ($line['canonical_name'] ?? '');
                $documentNet += (float) ($line['line_net'] ?? 0);
                $documentGross += (float) ($line['line_gross'] ?? 0);
            }

            $sheetRows[] = array(
                (string) (($document['sale_date'] ?? '') ?: ($document['issue_date'] ?? '')),
                (string) ($document['document_number'] ?? ''),
                (string) ($document['supplier_name'] ?? ''),
                (string) ($document['supplier_tax_id'] ?? ''),
                implode(', ', array_unique($lineNames)),
                round($documentNet, 2),
                round($documentGross, 2),
                (string) ($document['currency'] ?? 'PLN'),
            );

            $totalNet += $documentNet;
            $totalGross += $documentGross;
        }
        $sheetRows[] = array('', '', '', '', 'SUMA', round($totalNet, 2), round($totalGross, 2), '');

        $this->respondWithXlsx('Koszty', $sheetRows, 'accounting_warehouse_koszty', $dateFrom, $dateTo);
    }

    public function exportgoodsxlsx(): void
    {
        $this->requireModule('accountingwarehouse');
        list($dateFrom, $dateTo) = $this->resolveExportDateRange();

        $documents = array_values(array_filter(
            $this->warehouse->documentsForExport($dateFrom, $dateTo),
            static function (array $document): bool {
                return ((string) ($document['classification'] ?? '')) === 'towar';
            }
        ));

        $sheetRows = array();
        $sheetRows[] = array(
            'Data', 'Numer dokumentu', 'Dostawca', 'NIP', 'Pozycja kosztowa',
            'Towar netto', 'Towar brutto', 'Koszt netto', 'Koszt brutto',
            'Razem netto', 'Razem brutto', 'Waluta',
        );
        $totals = array('goods_net' => 0.0, 'goods_gross' => 0.0, 'cost_net' => 0.0, 'cost_gross' => 0.0, 'net' => 0.0, 'gross' => 0.0);
        foreach ($documents as $document) {
            $goodsNet = 0.0;
            $goodsGross = 0.0;
            $costNet = 0.0;
            $costGross = 0.0;
            $costName = '';
            foreach ((array) ($document['lines'] ?? array()) as $line) {
                if (((string) ($line['item_kind'] ?? '')) === 'koszt') {
                    $costNet += (float) ($line['line_net'] ?? 0);
                    $costGross += (float) ($line['line_gross'] ?? 0);
                    $costName = (string) ($line['canonical_name'] ?? '');
                    continue;
                }

                $goodsNet += (float) ($line['line_net'] ?? 0);
                $goodsGross += (float) ($line['line_gross'] ?? 0);
            }

            $sheetRows[] = array(
                (string) (($document['sale_date'] ?? '') ?: ($document['issue_date'] ?? '')),
                (string) ($document['document_number'] ?? ''),
                (string) ($document['supplier_name'] ?? ''),
                (string) ($document['supplier_tax_id'] ?? ''),
                $costName,
                round($goodsNet, 2),
                round($goodsGross, 2),
                round($costNet, 2),
                round($costGross, 2),
                round($goodsNet + $costNet, 2),
                round($goodsGross + $costGross, 2),
                (string) ($document['currency'] ?? 'PLN'),
            );

            $totals['goods_net'] += $goodsNet;
            $totals['goods_gross'] += $goodsGross;
            $totals['cost_net'] += $costNet;
            $totals['cost_gross'] += $costGross;
            $totals['net'] += $goodsNet + $costNet;
            $totals['gross'] += $goodsGross + $costGross;
        }
        $sheetRows[] = array(
            '', '', '', '', 'SUMA',
            round($totals['goods_net'], 2), round($totals['goods_gross'], 2),
            round($totals['cost_net'], 2), round($totals['cost_gross'], 2),
            round($totals['net'], 2), round($totals['gross'], 2), '',
        );

        $this->respondWithXlsx('Towar', $sheetRows, 'accounting_warehouse_towar', $dateFrom, $dateTo);
    }

    public function exportmixedxlsx(): void
    {
        $this->requireModule('accountingwarehouse');
        list($dateFrom, $dateTo) = $this->resolveExportDateRange();

        $documents = array_values(array_filter(
            $this->warehouse->documentsForExport($dateFrom, $dateTo),
            static function (array $document): bool {
                return ((string) ($document['classification'] ?? '')) === 'mieszane';
            }
        ));

        $sheetRows = array();
        $sheetRows[] = array('Data', 'Numer dokumentu', 'Dostawca', 'NIP', 'Pozycja ksiegowa', 'Rodzaj', 'Ilosc', 'Netto', 'Brutto', 'Waluta');
        $totalNet = 0.0;
        $totalGross = 0.0;
        foreach ($documents as $document) {
            $date = (string) (($document['sale_date'] ?? '') ?: ($document['issue_date'] ?? ''));
            foreach ((array) ($document['lines'] ?? array()) as $line) {
                $lineNet = (float) ($line['line_net'] ?? 0);
                $lineGross = (float) ($line['line_gross'] ?? 0);
                $sheetRows[] = array(
                    $date,
                    (string) ($document['document_number'] ?? ''),
                    (string) ($document['supplier_name'] ?? ''),
                    (string) ($document['supplier_tax_id'] ?? ''),
                    (string) ($line['canonical_name'] ?? ''),
                    ((string) ($line['item_kind'] ?? '')) === 'koszt' ? 'koszt' : 'towar',
                    round((float) ($line['quantity'] ?? 0), 3),
                    round($lineNet, 2),
                    round($lineGross, 2),
                    (string) ($document['currency'] ?? 'PLN'),
                );

                $totalNet += $lineNet;
                $totalGross += $lineGross;
            }
        }
        $sheetRows[] = array('', '', '', '', '', 'SUMA', '', round($totalNet, 2), round($totalGross, 2), '');

        $this->respondWithXlsx('Mieszane', $sheetRows, 'accounting_warehouse_mieszane', $dateFrom, $dateTo);
    }

    public function exportadjustmentsxlsx(): void
    {
        $this->requireModule('accountingwarehouse');
        list($dateFrom, $dateTo) = $this->resolveExportDateRange();

        $documents = array_values(array_filter(
            $this->warehouse->documentsForExport($dateFrom, $dateTo),
            static function (array $document): bool {
                return ((string) ($document['classification'] ?? '')) === 'korekta';
            }
        ));

        $sheetRows = array();
        $sheetRows[] = array('Data', 'Numer dokumentu', 'Dostawca', 'NIP', 'Pozycje', 'Netto', 'Brutto', 'Waluta');
        $totalNet = 0.0;
        $totalGross = 0.0;
        foreach ($documents as $document) {
            $lineNames = array();
            $documentNet = 0.0;
            $documentGross = 0.0;
            foreach ((array) ($document['lines'] ?? array()) as $line) {
                $lineNames[] = (string) ($line['canonical_name'] ?? '');
                $documentNet += (float) ($line['line_net'] ?? 0);
                $documentGross += (float) ($line['line_gross'] ?? 0);
            }

            $sheetRows[] = array(
                (string) (($document['sale_date'] ?? '') ?: ($document['issue_date'] ?? '')),
                (string) ($document['document_number'] ?? ''),
                (string) ($document['supplier_name'] ?? ''),
                (string) ($document['supplier_tax_id'] ?? ''),
                implode(', ', array_unique($lineNames)),
                round($documentNet, 2),
                round($documentGross, 2),
                (string) ($document['currency'] ?? 'PLN'),
            );

            $totalNet += $documentNet;
            $totalGross += $documentGross;
        }
        $sheetRows[] = array('', '', '', '', 'SUMA', round($totalNet, 2), round($totalGross, 2), '');

        $this->respondWithXlsx('Korekty', $sheetRows, 'accounting_warehouse_korekty', $dateFrom, $dateTo);
    }

    public function issuecreate(): void
    {
        $currentUser = $this->requireModuleWrite('accountingwarehouse');
        $stockItems = array_values(array_filter(
            $this->warehouse->stockSummary(),
            static function (array $item): bool {
                return strtolower(trim((string) ($item['item_kind'] ?? ''))) === 'towar';
            }
        ));
        usort($stockItems, static function (array $left, array $right): int {
            $quantityCompare = ((float) ($right['quantity'] ?? 0)) <=> ((float) ($left['quantity'] ?? 0));
            if ($quantityCompare !== 0) {
                return $quantityCompare;
            }

            return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        $this->render('accounting_warehouse/issue_form', array(
            'pageTitle' => 'Wyjscie z magazynu',
            'contentTitle' => 'Wyjscie z magazynu',
            'pageDescription' => 'Osobny formularz rozchodu magazynu ksiegowego.',
            'breadcrumbCurrent' => 'Wyjscie z magazynu',
            'currentUser' => $currentUser,
            'flashSuccess' => $this->getFlash('success'),
            'flashError' => $this->getFlash('error'),
            'stockItems' => $stockItems,
            'currencyOptions' => $this->currencyOptions(),
            'formData' => $this->defaultFormData(),
        ));
    }

    public function issuebalancecreate(): void
    {
        $currentUser = $this->requireModuleWrite('accountingwarehouse');
        $balanceFormData = array(
            'document_number' => 'WYR-' . date('Ymd-His'),
            'issue_date' => date('Y-m-d'),
            'sale_date' => date('Y-m-d'),
            'currency' => 'PLN',
            'target_mode' => 'gross',
            'target_value' => '',
            'notes' => '',
        );

        if ($this->isPost()) {
            $balanceFormData = array(
                'document_number' => trim((string) $this->input('balance_document_number', $balanceFormData['document_number'])),
                'issue_date' => trim((string) $this->input('balance_issue_date', $balanceFormData['issue_date'])),
                'sale_date' => trim((string) $this->input('balance_sale_date', $balanceFormData['sale_date'])),
                'currency' => trim((string) $this->input('balance_currency', $balanceFormData['currency'])) ?: 'PLN',
                'target_mode' => strtolower(trim((string) $this->input('balance_target_mode', 'gross'))) === 'net' ? 'net' : 'gross',
                'target_value' => trim((string) $this->input('balance_target_value', '')),
                'notes' => trim((string) $this->input('balance_notes', '')),
            );
        }

        $previewResult = null;
        if ($this->isPost()) {
            try {
                $targetValue = $this->toDecimal($balanceFormData['target_value']);
                if ($targetValue == 0.0) {
                    throw new RuntimeException('Podaj kwote wyrownania rozna od 0.');
                }

                $header = array(
                    'source_type' => 'manual',
                    'document_kind' => 'issue',
                    'document_number' => $balanceFormData['document_number'],
                    'supplier_name' => 'Wyrownanie stanu magazynu',
                    'supplier_tax_id' => '',
                    'issue_date' => $balanceFormData['issue_date'],
                    'sale_date' => $balanceFormData['sale_date'],
                    'currency' => $balanceFormData['currency'],
                    'notes' => $balanceFormData['notes'],
                );

                $previewResult = $this->warehouse->previewAutoBalanceIssue($header, $targetValue, $balanceFormData['target_mode']);
            } catch (Throwable $exception) {
                $this->setFlash('error', $exception->getMessage());
                $this->redirect('./index.php?controller=accountingwarehouse&action=issuebalancecreate');
            }
        }

        $this->render('accounting_warehouse/issue_balance_form', array(
            'pageTitle' => 'Wyrownanie stanu magazynu',
            'contentTitle' => 'Wyrownanie stanu magazynu',
            'pageDescription' => 'Automatyczne odjecie sztuk z towarow bez wczesniejszego wyjscia, aby zblizyc sie do zadanej kwoty.',
            'breadcrumbCurrent' => 'Wyrownanie stanu',
            'currentUser' => $currentUser,
            'flashSuccess' => $this->getFlash('success'),
            'flashError' => $this->getFlash('error'),
            'currencyOptions' => $this->currencyOptions(),
            'balanceFormData' => $balanceFormData,
            'balancePreview' => $previewResult,
        ));
    }

    public function show(): void
    {
        $currentUser = $this->requireModule('accountingwarehouse');
        $documentId = (int) $this->input('id', 0);
        $document = $this->warehouse->documentWithLines($documentId);

        if ($document === null) {
            $this->setFlash('error', 'Nie znaleziono dokumentu.');
            $this->redirect('./index.php?controller=accountingwarehouse&action=documents');
        }

        $this->render('accounting_warehouse/show', array(
            'pageTitle' => 'Dokument #' . $documentId,
            'contentTitle' => 'Szczegoly dokumentu',
            'pageDescription' => 'Podglad faktury lub korekty zapisanej w magazynie ksiegowym.',
            'breadcrumbCurrent' => 'Szczegoly dokumentu',
            'currentUser' => $currentUser,
            'flashSuccess' => $this->getFlash('success'),
            'flashError' => $this->getFlash('error'),
            'document' => $document,
        ));
    }

    public function create(): void
    {
        $currentUser = $this->requireModuleWrite('accountingwarehouse');
        $showPreview = (string) $this->input('show_preview', '') === '1';
        if (!$showPreview) {
            $this->clearStoredXmlPreview();
        }

        $xmlPreview = $showPreview ? $this->annotatePreviewDocuments($this->loadStoredXmlPreview()) : array();
        $formData = $this->defaultFormData();
        if ($xmlPreview !== array()) {
            $formData['xml_documents'] = $xmlPreview;
        }

        $this->renderFormPage($currentUser, $formData, $xmlPreview !== array() ? $xmlPreview : null, false);
    }

    public function macros(): void
    {
        $currentUser = $this->requireModuleWrite('accountingwarehouse');
        $editId = (int) $this->input('id', 0);
        $editMacro = $editId > 0 ? $this->warehouse->macroDefinition($editId) : null;

        $this->render('accounting_warehouse/macros', array(
            'pageTitle' => 'Pozycje ksiegowe i aliasy',
            'contentTitle' => 'Pozycje ksiegowe i aliasy',
            'pageDescription' => 'Tutaj definiujesz pozycje ksiegowe i ich aliasy jeszcze przed importem XML lub recznym dodawaniem.',
            'breadcrumbCurrent' => 'Pozycje ksiegowe i aliasy',
            'currentUser' => $currentUser,
            'flashSuccess' => $this->getFlash('success'),
            'flashError' => $this->getFlash('error'),
            'macros' => $this->warehouse->macroCatalog(),
            'editMacro' => $editMacro,
        ));
    }

    public function edit(): void
    {
        $currentUser = $this->requireModuleWrite('accountingwarehouse');
        $documentId = (int) $this->input('id', 0);
        $document = $this->warehouse->documentWithLines($documentId);

        if ($document === null) {
            $this->setFlash('error', 'Nie znaleziono dokumentu do edycji.');
            $this->redirect('./index.php?controller=accountingwarehouse&action=documents');
        }

        $this->renderFormPage($currentUser, $this->mapDocumentToFormData($document), null, true);
    }

    public function previewxml(): void
    {
        $currentUser = $this->requireModuleWrite('accountingwarehouse');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=accountingwarehouse&action=create');
        }

        try {
            if (!isset($_FILES['invoice_xml']) || !is_array($_FILES['invoice_xml'])) {
                throw new RuntimeException('Wybierz plik XML do importu.');
            }

            $isBatchMode = (string) $this->input('batch_mode', '') === '1';
            $clearStored = (string) $this->input('clear_stored_preview', '') === '1';
            if ($clearStored) {
                $this->clearStoredXmlPreview();
            }

            $previews = array();
            foreach ($this->normalizeUploadedXmlFiles($_FILES['invoice_xml']) as $fileIndex => $file) {
                try {
                    $tmpName = (string) ($file['tmp_name'] ?? '');
                    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                        throw new RuntimeException('Brak poprawnego pliku tymczasowego XML.');
                    }

                    $content = file_get_contents($tmpName);
                    if ($content === false) {
                        throw new RuntimeException('Nie udalo sie odczytac przeslanego pliku XML.');
                    }

                    $preview = $this->xmlImporter->parseInvoiceXml($content, (string) ($file['name'] ?? 'faktura.xml'));
                    foreach ($preview['lines'] as $index => $line) {
                        $rememberedName = $this->warehouse->canonicalNameForSource((string) ($line['original_name'] ?? ''));
                        if ($rememberedName !== null) {
                            $preview['lines'][$index]['canonical_name'] = $rememberedName;
                            $preview['lines'][$index]['classification_confidence'] = 'high';
                            $preview['lines'][$index]['classification_rule'] = 'alias';
                        }
                    }

                    $preview['duplicate'] = $this->warehouse->findDuplicateDocument((array) ($preview['header'] ?? array()));

                    $previews[] = $preview;
                } catch (Throwable $exception) {
                    throw new RuntimeException($this->uploadedXmlFileErrorMessage($file, $fileIndex, $exception->getMessage()), 0, $exception);
                }
            }

            $previews = $this->annotatePreviewDocuments($previews);

            if ($isBatchMode) {
                $stored = $this->annotatePreviewDocuments($this->loadStoredXmlPreview());
                $merged = $this->annotatePreviewDocuments(array_merge($stored, $previews));
                $this->storeXmlPreview($merged);
                $this->jsonResponse(array(
                    'ok' => true,
                    'added' => count($previews),
                    'total' => count($merged),
                ));
                return;
            }

            $this->storeXmlPreview($previews);
            $formData = $this->defaultFormData();
            $formData['xml_documents'] = $previews;

            $this->renderFormPage($currentUser, $formData, $previews, false);
        } catch (Throwable $exception) {
            if ((string) $this->input('batch_mode', '') === '1') {
                $this->jsonResponse(array('ok' => false, 'error' => $exception->getMessage()), 500);
                return;
            }
            $this->setFlash('error', $exception->getMessage());
            $this->redirect('./index.php?controller=accountingwarehouse&action=create');
        }
    }

    public function storexml(): void
    {
        $currentUser = $this->requireModuleWrite('accountingwarehouse');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=accountingwarehouse&action=create');
        }

        try {
            $documents = $this->xmlDocumentsFromInput();
            if ($documents === array()) {
                throw new RuntimeException('Brak dokumentow XML do zapisania.');
            }

            $savedIds = array();
            $savedAdjustments = 0;
            $skippedDuplicates = 0;
            $skippedMarked = 0;
            foreach ($documents as $documentData) {
                $importStatus = trim((string) ($documentData['import_status'] ?? 'ready')) ?: 'ready';
                if ($importStatus === 'skipped') {
                    $skippedMarked++;
                    continue;
                }

                $documentKind = trim((string) ($documentData['document_kind'] ?? 'receipt')) ?: 'receipt';
                if ($this->looksLikeCorrectionDocument($documentData)) {
                    $documentKind = 'adjustment';
                }

                $xmlPayload = base64_decode((string) ($documentData['xml_payload_base64'] ?? ''), true);
                if ($xmlPayload === false || trim($xmlPayload) === '') {
                    throw new RuntimeException('Nie udalo sie odczytac danych XML do zapisu.');
                }

                $header = array(
                    'document_number' => (string) ($documentData['document_number'] ?? ''),
                    'supplier_name' => (string) ($documentData['supplier_name'] ?? ''),
                    'supplier_tax_id' => (string) ($documentData['supplier_tax_id'] ?? ''),
                    'issue_date' => (string) ($documentData['issue_date'] ?? ''),
                    'sale_date' => (string) ($documentData['sale_date'] ?? ''),
                    'currency' => (string) ($documentData['currency'] ?? 'PLN'),
                    'notes' => (string) ($documentData['notes'] ?? ''),
                    'source_type' => 'xml',
                    'document_kind' => $documentKind,
                    'affects_stock' => $documentKind === 'adjustment' ? 0 : 1,
                    'xml_filename' => (string) ($documentData['xml_filename'] ?? 'faktura.xml'),
                    'xml_hash' => (string) ($documentData['xml_hash'] ?? hash('sha256', $xmlPayload)),
                    'xml_payload' => $xmlPayload,
                );

                $this->validateSupplierHeader($header);
                $duplicate = $this->warehouse->findDuplicateDocument($header);
                if ($duplicate !== null) {
                    $skippedDuplicates++;
                    continue;
                }

                $savedIds[] = $this->warehouse->saveDocument($header, (array) ($documentData['lines'] ?? array()), (int) ($currentUser['id'] ?? 0));
                if ($documentKind === 'adjustment') {
                    $savedAdjustments++;
                }
            }

            if ($savedIds === array()) {
                $this->clearStoredXmlPreview();
                if ($skippedDuplicates > 0 || $skippedMarked > 0) {
                    $message = 'Nie zapisano zadnych dokumentow XML.';
                    if ($skippedDuplicates > 0) {
                        $message .= ' Pominieto duplikaty: ' . $skippedDuplicates . '.';
                    }
                    if ($skippedMarked > 0) {
                        $message .= ' Pominieto oznaczone do usuniecia: ' . $skippedMarked . '.';
                    }
                    $this->setFlash('success', $message);
                    $this->redirect('./index.php?controller=accountingwarehouse&action=create');
                }

                throw new RuntimeException('Brak dokumentow XML do zapisania.');
            }

            $firstDocumentId = (int) ($savedIds[0] ?? 0);
            $this->clearStoredXmlPreview();
            $message = 'Zapisano dokumenty XML: ' . count($savedIds) . '.';
            if ($savedAdjustments > 0) {
                $message .= ' W tym korekt (bez wplywu na stan magazynu): ' . $savedAdjustments . '.';
            }
            if ($skippedDuplicates > 0) {
                $message .= ' Pominieto duplikaty: ' . $skippedDuplicates . '.';
            }
            if ($skippedMarked > 0) {
                $message .= ' Pominieto oznaczone do usuniecia: ' . $skippedMarked . '.';
            }
            $this->setFlash('success', $message);
            $this->redirect('./index.php?controller=accountingwarehouse&action=show&id=' . $firstDocumentId);
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
            $this->redirect('./index.php?controller=accountingwarehouse&action=create');
        }
    }

    public function storemanual(): void
    {
        $currentUser = $this->requireModuleWrite('accountingwarehouse');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=accountingwarehouse&action=create');
        }

        try {
            $header = $this->headerFromPrefix('manual_');
            $header['source_type'] = 'manual';
            $documentKind = trim((string) $this->input('manual_document_kind', 'receipt'));
            $header['document_kind'] = in_array($documentKind, array('receipt', 'koszt', 'adjustment'), true) ? $documentKind : 'receipt';
            $header['affects_stock'] = $header['document_kind'] === 'adjustment' ? 0 : 1;

            $this->validateSupplierHeader($header);
            $this->warehouse->saveDocument($header, $this->linesFromPrefix('manual_'), (int) ($currentUser['id'] ?? 0));
            $documentNumber = trim((string) ($header['document_number'] ?? ''));
            $message = 'Dodano reczna fakture.';
            if ($documentNumber !== '') {
                $message = 'Dodano fakture o numerze ' . $documentNumber . '.';
            }
            $this->setFlash('success', $message);
            $this->redirect('./index.php?controller=accountingwarehouse&action=create');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
            $this->redirect('./index.php?controller=accountingwarehouse&action=create');
        }
    }

    public function savemacro(): void
    {
        $this->requireModuleWrite('accountingwarehouse');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=accountingwarehouse&action=macros');
        }

        try {
            $name = trim((string) $this->input('macro_name', ''));
            $unit = trim((string) $this->input('macro_unit', 'szt.'));
            $itemKind = trim((string) $this->input('macro_item_kind', 'towar'));
            $aliasesRaw = trim((string) $this->input('macro_aliases', ''));
            $aliases = preg_split('/[\r\n,;]+/', $aliasesRaw) ?: array();

            $this->warehouse->saveMacroDefinition($name, $unit, $aliases, $itemKind);
            $this->setFlash('success', 'Makro zostalo zapisane. Nowe aliasy beda brane pod uwage przy kolejnych importach.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=accountingwarehouse&action=macros');
    }

    public function updatemacro(): void
    {
        $this->requireModuleWrite('accountingwarehouse');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=accountingwarehouse&action=macros');
        }

        $macroId = (int) $this->input('id', 0);
        if ($macroId <= 0) {
            $this->setFlash('error', 'Brak pozycji ksiegowej do edycji.');
            $this->redirect('./index.php?controller=accountingwarehouse&action=macros');
        }

        try {
            $name = trim((string) $this->input('macro_name', ''));
            $unit = trim((string) $this->input('macro_unit', 'szt.'));
            $itemKind = trim((string) $this->input('macro_item_kind', 'towar'));
            $aliasesRaw = trim((string) $this->input('macro_aliases', ''));
            $aliases = preg_split('/[\r\n,;]+/', $aliasesRaw) ?: array();

            $this->warehouse->updateMacroDefinition($macroId, $name, $unit, $aliases, $itemKind);
            $this->setFlash('success', 'Makro zostalo zaktualizowane.');
            $this->redirect('./index.php?controller=accountingwarehouse&action=macros');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
            $this->redirect('./index.php?controller=accountingwarehouse&action=macros&id=' . $macroId);
        }
    }

    public function storeadjustment(): void
    {
        $currentUser = $this->requireModuleWrite('accountingwarehouse');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=accountingwarehouse&action=create');
        }

        try {
            $canonicalName = trim((string) $this->input('adjustment_canonical_name', ''));
            if ($canonicalName === '') {
                throw new RuntimeException('Podaj nazwe pozycji do korekty.');
            }

            $quantity = $this->toDecimal($this->input('adjustment_quantity', '0'));
            if ($quantity == 0.0) {
                throw new RuntimeException('Korekta stanu nie moze wynosic 0.');
            }

            $unit = trim((string) $this->input('adjustment_unit', 'szt.')) ?: 'szt.';
            $unitNet = $this->toDecimal($this->input('adjustment_unit_net', '0'));
            $unitGross = $this->toDecimal($this->input('adjustment_unit_gross', '0'));
            $header = array(
                'source_type' => 'manual',
                'document_kind' => 'adjustment',
                'document_number' => trim((string) $this->input('adjustment_document_number', 'KOR-' . date('Ymd-His'))),
                'supplier_name' => 'Korekta reczna',
                'supplier_tax_id' => '',
                'issue_date' => trim((string) $this->input('adjustment_date', date('Y-m-d'))),
                'sale_date' => trim((string) $this->input('adjustment_date', date('Y-m-d'))),
                'currency' => 'PLN',
                'notes' => trim((string) $this->input('adjustment_notes', '')),
            );

            $lines = array(
                array(
                    'original_name' => trim((string) $this->input('adjustment_original_name', $canonicalName)),
                    'canonical_name' => $canonicalName,
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'unit_net' => $unitNet,
                    'unit_gross' => $unitGross,
                    'vat_rate' => $this->toDecimal($this->input('adjustment_vat_rate', '23')),
                ),
            );

            $documentId = $this->warehouse->saveDocument($header, $lines, (int) ($currentUser['id'] ?? 0));
            $this->setFlash('success', 'Korekta stanu zostala zapisana.');
            $this->redirect('./index.php?controller=accountingwarehouse&action=show&id=' . $documentId);
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
            $this->redirect('./index.php?controller=accountingwarehouse&action=create');
        }
    }

    public function storeissue(): void
    {
        $currentUser = $this->requireModuleWrite('accountingwarehouse');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=accountingwarehouse&action=create');
        }

        try {
            $header = $this->headerFromPrefix('issue_');
            $header['source_type'] = 'manual';
            $header['document_kind'] = 'issue';
            if (trim((string) ($header['supplier_name'] ?? '')) === '') {
                $header['supplier_name'] = 'Wyjscie z magazynu';
            }

            $documentId = $this->warehouse->saveDocument($header, $this->linesFromPrefix('issue_'), (int) ($currentUser['id'] ?? 0));
            $this->setFlash('success', 'Wyjscie z magazynu zostalo zapisane.');
            $this->redirect('./index.php?controller=accountingwarehouse&action=show&id=' . $documentId);
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
            $this->redirect('./index.php?controller=accountingwarehouse&action=create');
        }
    }

    public function storeissuebalance(): void
    {
        $currentUser = $this->requireModuleWrite('accountingwarehouse');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=accountingwarehouse&action=issuebalancecreate');
        }

        try {
            $targetGross = $this->toDecimal($this->input('balance_target_value', '0'));
            if ($targetGross == 0.0) {
                throw new RuntimeException('Podaj kwote wyrownania rozna od 0.');
            }
            $targetMode = strtolower(trim((string) $this->input('balance_target_mode', 'gross'))) === 'net' ? 'net' : 'gross';

            $header = array(
                'source_type' => 'manual',
                'document_kind' => 'issue',
                'document_number' => trim((string) $this->input('balance_document_number', 'WYR-' . date('Ymd-His'))),
                'supplier_name' => 'Wyrownanie stanu magazynu',
                'supplier_tax_id' => '',
                'issue_date' => trim((string) $this->input('balance_issue_date', date('Y-m-d'))),
                'sale_date' => trim((string) $this->input('balance_sale_date', date('Y-m-d'))),
                'currency' => trim((string) $this->input('balance_currency', 'PLN')) ?: 'PLN',
                'notes' => trim((string) $this->input('balance_notes', '')),
            );

            $result = $this->warehouse->saveAutoBalanceIssue($header, $targetGross, $targetMode, (int) ($currentUser['id'] ?? 0));
            $this->setFlash(
                'success',
                'Wyrownanie zapisane. Cel ' . ($targetMode === 'net' ? 'netto' : 'brutto') . ': ' . number_format(abs($targetGross), 2, '.', '') . ' PLN,'
                . ' osiagniete brutto: ' . number_format((float) ($result['actual_gross'] ?? 0), 2, '.', '') . ' PLN,'
                . ' sztuk: ' . (int) ($result['quantity'] ?? 0) . '.'
            );
            $this->redirect('./index.php?controller=accountingwarehouse&action=show&id=' . (int) ($result['document_id'] ?? 0));
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
            $this->redirect('./index.php?controller=accountingwarehouse&action=issuebalancecreate');
        }
    }

    public function update(): void
    {
        $this->requireModuleWrite('accountingwarehouse');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=accountingwarehouse&action=documents');
        }

        $documentId = (int) $this->input('id', 0);
        if ($documentId <= 0) {
            $this->setFlash('error', 'Brak dokumentu do aktualizacji.');
            $this->redirect('./index.php?controller=accountingwarehouse&action=documents');
        }

        try {
            $header = $this->headerFromPrefix('edit_');
            $header['source_type'] = trim((string) $this->input('edit_source_type', 'manual')) ?: 'manual';
            $header['document_kind'] = trim((string) $this->input('edit_document_kind', 'receipt')) ?: 'receipt';
            $header['xml_filename'] = trim((string) $this->input('edit_xml_filename', ''));
            $header['xml_hash'] = trim((string) $this->input('edit_xml_hash', ''));
            $header['xml_payload'] = trim((string) $this->input('edit_xml_payload', ''));

            $this->validateSupplierHeader($header);
            $this->warehouse->updateDocument($documentId, $header, $this->linesFromPrefix('edit_'));
            $this->setFlash('success', 'Dokument zostal zaktualizowany.');
            $this->redirect('./index.php?controller=accountingwarehouse&action=show&id=' . $documentId);
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
            $this->redirect('./index.php?controller=accountingwarehouse&action=edit&id=' . $documentId);
        }
    }

    public function delete(): void
    {
        $this->requireModuleWrite('accountingwarehouse');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=accountingwarehouse&action=documents');
        }

        $documentId = (int) $this->input('id', 0);
        if ($documentId <= 0) {
            $this->setFlash('error', 'Brak dokumentu do usuniecia.');
            $this->redirect('./index.php?controller=accountingwarehouse&action=documents');
        }

        try {
            $this->warehouse->deleteDocument($documentId);
            $this->setFlash('success', 'Dokument zostal usuniety.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=accountingwarehouse&action=documents');
    }

    public function reassignitemsource(): void
    {
        $this->requireModuleWrite('accountingwarehouse');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=accountingwarehouse&action=index');
        }

        $itemId = (int) $this->input('id', 0);
        $sourceName = trim((string) $this->input('source_name', ''));
        $targetCanonicalName = trim((string) $this->input('target_canonical_name', ''));

        if ($itemId <= 0) {
            $this->setFlash('error', 'Brak pozycji magazynowej do przepiecia.');
            $this->redirect('./index.php?controller=accountingwarehouse&action=index');
        }

        try {
            $affected = $this->warehouse->reassignItemSource($itemId, $sourceName, $targetCanonicalName);
            $this->setFlash(
                'success',
                'Przepieto wpisy dla "' . $sourceName . '" na "' . $targetCanonicalName . '". Zmienionych wierszy: ' . $affected . '.'
            );
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=accountingwarehouse&action=item&id=' . $itemId);
    }

    public function exportbackup(): void
    {
        $this->requireModule('accountingwarehouse');

        try {
            $payload = $this->warehouse->backupSnapshot();
            $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new RuntimeException('Nie udalo sie wygenerowac pliku kopii.');
            }

            $filename = 'accounting_warehouse_backup_' . date('Ymd_His') . '.json';
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($json));
            echo $json;
            exit;
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
            $this->redirect('./index.php?controller=accountingwarehouse&action=index');
        }
    }

    public function importbackup(): void
    {
        $this->requireModuleWrite('accountingwarehouse');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=accountingwarehouse&action=index');
        }

        try {
            if ((string) $this->input('confirm_restore', '') !== '1') {
                throw new RuntimeException('Potwierdz, ze chcesz nadpisac wszystkie dane magazynu ksiegowego.');
            }

            if (!isset($_FILES['backup_file']) || !is_array($_FILES['backup_file'])) {
                throw new RuntimeException('Wybierz plik kopii JSON do przywrocenia.');
            }

            $tmpName = (string) ($_FILES['backup_file']['tmp_name'] ?? '');
            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                throw new RuntimeException('Brak poprawnego pliku tymczasowego kopii.');
            }

            $content = file_get_contents($tmpName);
            if ($content === false || trim($content) === '') {
                throw new RuntimeException('Nie udalo sie odczytac pliku kopii.');
            }

            $payload = json_decode($content, true);
            if (!is_array($payload)) {
                throw new RuntimeException('Plik kopii nie jest poprawnym JSON-em.');
            }

            if (($payload['module'] ?? '') !== 'accountingwarehouse') {
                throw new RuntimeException('Ten plik nie wyglada na kopie magazynu ksiegowego.');
            }

            $result = $this->warehouse->restoreSnapshot($payload);
            $this->setFlash(
                'success',
                'Przywrocono kopie magazynu ksiegowego. Pozycje: ' . (int) ($result['items'] ?? 0)
                . ', aliasy: ' . (int) ($result['aliases'] ?? 0)
                . ', dokumenty: ' . (int) ($result['documents'] ?? 0)
                . ', linie: ' . (int) ($result['lines'] ?? 0) . '.'
            );
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=accountingwarehouse&action=index');
    }

    public function supplierlookup(): void
    {
        $this->requireModule('accountingwarehouse');

        if ($this->requestMethod() !== 'GET') {
            $this->jsonResponse(array('error' => 'Dozwolona jest tylko metoda GET.'), 405);
            return;
        }

        try {
            $query = trim((string) $this->input('q', ''));
            $mode = strtolower(trim((string) $this->input('mode', 'mixed')));
            if (!in_array($mode, array('mixed', 'name', 'tax_id'), true)) {
                $mode = 'mixed';
            }

            $items = $this->warehouse->supplierLookup($query, $mode, 10);
            $this->jsonResponse(array('items' => $items));
        } catch (Throwable $exception) {
            $this->jsonResponse(array('items' => array(), 'error' => $exception->getMessage()), 500);
        }
    }

    public function supplierresolve(): void
    {
        $this->requireModule('accountingwarehouse');

        if ($this->requestMethod() !== 'GET') {
            $this->jsonResponse(array('error' => 'Dozwolona jest tylko metoda GET.'), 405);
            return;
        }

        try {
            $taxId = preg_replace('/\D+/', '', (string) $this->input('tax_id', ''));
            $supplierName = trim((string) $this->input('supplier_name', ''));

            if (is_string($taxId) && $taxId !== '') {
                $local = $this->warehouse->supplierByTaxId($taxId);
                if ($local !== null) {
                    $this->jsonResponse(array('item' => array_merge($local, array('source' => 'local'))));
                    return;
                }

                $official = $this->supplierResolver->resolveByTaxId($taxId, date('Y-m-d'));
                if ($official !== null) {
                    $this->jsonResponse(array('item' => $official));
                    return;
                }
            }

            if ($supplierName !== '') {
                $local = $this->warehouse->supplierByName($supplierName);
                if ($local !== null) {
                    $this->jsonResponse(array('item' => array_merge($local, array('source' => 'local'))));
                    return;
                }
            }

            $this->jsonResponse(array('item' => null));
        } catch (Throwable $exception) {
            $this->jsonResponse(array('item' => null, 'error' => $exception->getMessage()), 500);
        }
    }

    public function documentduplicatecheck(): void
    {
        $this->requireModule('accountingwarehouse');

        if ($this->requestMethod() !== 'GET') {
            $this->jsonResponse(array('error' => 'Dozwolona jest tylko metoda GET.'), 405);
            return;
        }

        try {
            $header = array(
                'document_number' => trim((string) $this->input('document_number', '')),
                'supplier_name' => trim((string) $this->input('supplier_name', '')),
                'supplier_tax_id' => trim((string) $this->input('supplier_tax_id', '')),
                'xml_hash' => trim((string) $this->input('xml_hash', '')),
            );
            $excludeId = (int) $this->input('exclude_id', 0);
            $duplicate = $this->warehouse->findDuplicateDocument($header, $excludeId);
            $this->jsonResponse(array('duplicate' => $duplicate));
        } catch (Throwable $exception) {
            $this->jsonResponse(array('duplicate' => null, 'error' => $exception->getMessage()), 500);
        }
    }

    public function documentnumberlookup(): void
    {
        $this->requireModule('accountingwarehouse');

        if ($this->requestMethod() !== 'GET') {
            $this->jsonResponse(array('error' => 'Dozwolona jest tylko metoda GET.'), 405);
            return;
        }

        try {
            $query = trim((string) $this->input('q', ''));
            $excludeId = (int) $this->input('exclude_id', 0);
            $items = $query === ''
                ? array()
                : $this->warehouse->searchDocumentsByNumber($query, 5, $excludeId);
            $this->jsonResponse(array('items' => $items));
        } catch (Throwable $exception) {
            $this->jsonResponse(array('items' => array(), 'error' => $exception->getMessage()), 500);
        }
    }

    public function itemnames(): void
    {
        $this->requireModule('accountingwarehouse');

        if ($this->requestMethod() !== 'GET') {
            $this->jsonResponse(array('error' => 'Dozwolona jest tylko metoda GET.'), 405);
            return;
        }

        try {
            $items = $this->warehouse->itemNameSuggestions();
            $sourceName = trim((string) $this->input('source_name', ''));
            $suggestion = null;
            $confidence = 'low';

            if ($sourceName !== '') {
                $rememberedName = $this->warehouse->canonicalNameForSource($sourceName);
                if ($rememberedName !== null) {
                    $suggestion = $rememberedName;
                    $confidence = 'high';
                } else {
                    $classification = $this->classifier->classify($sourceName);
                    $suggestion = (string) ($classification['canonical_name'] ?? 'pozostale');
                    $confidence = (string) ($classification['confidence'] ?? 'low');
                }
            }

            $this->jsonResponse(array(
                'items' => $items,
                'suggestion' => $suggestion,
                'confidence' => $confidence,
            ));
        } catch (Throwable $exception) {
            $this->jsonResponse(array('items' => array(), 'error' => $exception->getMessage()), 500);
        }
    }

    public function deletemacro(): void
    {
        $this->requireModuleWrite('accountingwarehouse');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=accountingwarehouse&action=macros');
        }

        $macroId = (int) $this->input('id', 0);
        if ($macroId <= 0) {
            $this->setFlash('error', 'Brak pozycji ksiegowej do usuniecia.');
            $this->redirect('./index.php?controller=accountingwarehouse&action=macros');
        }

        try {
            $this->warehouse->deleteMacroDefinition($macroId);
            $this->setFlash('success', 'Makro zostalo usuniete.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=accountingwarehouse&action=macros');
    }

    private function renderFormPage(array $currentUser, array $formData, ?array $xmlPreview, bool $isEdit): void
    {
        $this->render('accounting_warehouse/form', array(
            'pageTitle' => $isEdit ? 'Edycja dokumentu' : 'Dodaj dokument',
            'contentTitle' => $isEdit ? 'Edytuj dokument' : 'Dodaj dokument',
            'pageDescription' => $isEdit
                ? 'Edycja zapisanej faktury lub korekty magazynu ksiegowego.'
                : 'Dodawanie faktur recznych i importow XML.',
            'breadcrumbCurrent' => $isEdit ? 'Edytuj dokument' : 'Dodaj dokument',
            'currentUser' => $currentUser,
            'flashSuccess' => $this->getFlash('success'),
            'flashError' => $this->getFlash('error'),
            'itemSuggestions' => $this->warehouse->itemNameSuggestions(),
            'macroPresets' => $this->classifier->commonItemNames(),
            'currencyOptions' => $this->currencyOptions(),
            'todayDate' => date('Y-m-d'),
            'defaultAdjustmentNumber' => 'KOR-' . date('Ymd-His'),
            'formData' => $formData,
            'xmlPreview' => $xmlPreview,
            'isEdit' => $isEdit,
        ));
    }

    private function defaultFormData(): array
    {
        return array(
            'manual_header' => array(
                'document_number' => '',
                'supplier_name' => '',
                'supplier_tax_id' => '',
                'issue_date' => date('Y-m-d'),
                'sale_date' => date('Y-m-d'),
                'currency' => 'PLN',
                'notes' => '',
                'document_kind' => 'receipt',
            ),
            'manual_lines' => array(
                array(
                    'original_name' => '',
                    'canonical_name' => 'pozostale',
                    'quantity' => 1,
                    'unit' => 'szt.',
                    'unit_net' => 0,
                    'unit_gross' => 0,
                    'vat_rate' => 23,
                ),
            ),
            'issue_header' => array(
                'document_number' => 'WYD-' . date('Ymd-His'),
                'supplier_name' => 'Wyjscie z magazynu',
                'supplier_tax_id' => '',
                'issue_date' => date('Y-m-d'),
                'sale_date' => date('Y-m-d'),
                'currency' => 'PLN',
                'notes' => '',
            ),
            'issue_lines' => array(
                array(
                    'original_name' => '',
                    'canonical_name' => 'pozostale',
                    'quantity' => 1,
                    'unit' => 'szt.',
                    'unit_net' => 0,
                    'unit_gross' => 0,
                    'vat_rate' => 23,
                ),
            ),
            'xml_header' => array(),
            'xml_lines' => array(),
            'xml_documents' => array(),
            'edit_document' => null,
        );
    }

    private function mapDocumentToFormData(array $document): array
    {
        $lines = array();
        foreach ($document['lines'] as $line) {
            $lines[] = array(
                'original_name' => (string) ($line['original_name'] ?? ''),
                'canonical_name' => (string) ($line['canonical_name'] ?? $line['item_name'] ?? ''),
                'quantity' => (float) ($line['quantity'] ?? 0),
                'unit' => (string) ($line['unit'] ?? 'szt.'),
                'unit_net' => (float) ($line['unit_net'] ?? 0),
                'unit_gross' => (float) ($line['unit_gross'] ?? 0),
                'vat_rate' => (float) ($line['vat_rate'] ?? 23),
            );
        }

        return array(
            'manual_header' => array(),
            'manual_lines' => array(),
            'issue_header' => array(),
            'issue_lines' => array(),
            'xml_header' => array(),
            'xml_lines' => array(),
            'xml_documents' => array(),
            'edit_document' => array(
                'id' => (int) ($document['id'] ?? 0),
                'source_type' => (string) ($document['source_type'] ?? 'manual'),
                'document_kind' => (string) ($document['document_kind'] ?? 'receipt'),
                'document_number' => (string) ($document['document_number'] ?? ''),
                'supplier_name' => (string) ($document['supplier_name'] ?? ''),
                'supplier_tax_id' => (string) ($document['supplier_tax_id'] ?? ''),
                'issue_date' => (string) ($document['issue_date'] ?? ''),
                'sale_date' => (string) ($document['sale_date'] ?? ''),
                'currency' => (string) ($document['currency'] ?? 'PLN'),
                'notes' => (string) ($document['notes'] ?? ''),
                'xml_filename' => (string) ($document['xml_filename'] ?? ''),
                'xml_hash' => (string) ($document['xml_hash'] ?? ''),
                'xml_payload' => (string) ($document['xml_payload'] ?? ''),
                'lines' => $lines !== array() ? $lines : array(
                    array(
                        'original_name' => '',
                        'canonical_name' => 'pozostale',
                        'quantity' => 1,
                        'unit' => 'szt.',
                        'unit_net' => 0,
                        'unit_gross' => 0,
                        'vat_rate' => 23,
                    ),
                ),
            ),
        );
    }

    private function headerFromPrefix(string $prefix): array
    {
        return array(
            'document_number' => trim((string) $this->input($prefix . 'document_number', '')),
            'supplier_name' => trim((string) $this->input($prefix . 'supplier_name', '')),
            'supplier_tax_id' => trim((string) $this->input($prefix . 'supplier_tax_id', '')),
            'issue_date' => trim((string) $this->input($prefix . 'issue_date', '')),
            'sale_date' => trim((string) $this->input($prefix . 'sale_date', '')),
            'currency' => trim((string) $this->input($prefix . 'currency', 'PLN')) ?: 'PLN',
            'notes' => trim((string) $this->input($prefix . 'notes', '')),
            'total_net' => $this->toDecimal($this->input($prefix . 'total_net', '0')),
            'total_gross' => $this->toDecimal($this->input($prefix . 'total_gross', '0')),
        );
    }

    private function linesFromPrefix(string $prefix): array
    {
        $originalNames = $this->arrayInput($prefix . 'original_name');
        $canonicalNames = $this->arrayInput($prefix . 'canonical_name');
        $quantities = $this->arrayInput($prefix . 'quantity');
        $units = $this->arrayInput($prefix . 'unit');
        $unitNets = $this->arrayInput($prefix . 'unit_net');
        $unitGrosses = $this->arrayInput($prefix . 'unit_gross');
        $vatRates = $this->arrayInput($prefix . 'vat_rate');

        $count = max(count($originalNames), count($canonicalNames), count($quantities), count($units), count($unitNets), count($unitGrosses), count($vatRates));
        $lines = array();

        for ($index = 0; $index < $count; $index++) {
            $canonicalName = trim((string) ($canonicalNames[$index] ?? ''));
            $originalName = trim((string) ($originalNames[$index] ?? ''));
            if ($canonicalName === '' && $originalName === '') {
                continue;
            }

            $lines[] = array(
                'original_name' => $originalName,
                'canonical_name' => $canonicalName !== '' ? $canonicalName : 'pozostale',
                'quantity' => $this->toDecimal($quantities[$index] ?? 0),
                'unit' => trim((string) ($units[$index] ?? 'szt.')) ?: 'szt.',
                'unit_net' => $this->toDecimal($unitNets[$index] ?? 0),
                'unit_gross' => $this->toDecimal($unitGrosses[$index] ?? 0),
                'vat_rate' => $this->toDecimal($vatRates[$index] ?? 23),
            );
        }

        return $lines;
    }

    private function normalizeUploadedXmlFiles(array $files): array
    {
        if (!isset($files['name'])) {
            throw new RuntimeException('Brak plikow XML.');
        }

        if (!is_array($files['name'])) {
            $error = (int) ($files['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($error !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Nie udalo sie przeslac pliku XML.');
            }

            return array($files);
        }

        $normalized = array();
        foreach ($files['name'] as $index => $name) {
            $error = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($error !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Jeden z plikow XML nie przeslal sie poprawnie.');
            }

            $normalized[] = array(
                'name' => $name,
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $error,
                'size' => $files['size'][$index] ?? 0,
            );
        }

        if ($normalized === array()) {
            throw new RuntimeException('Nie wybrano zadnego pliku XML.');
        }

        return $normalized;
    }

    private function uploadedXmlFileErrorMessage(array $file, int $fileIndex, string $message): string
    {
        $filename = trim((string) ($file['name'] ?? ''));
        $label = 'Blad w pliku XML #' . ($fileIndex + 1);
        if ($filename !== '') {
            $label .= ' (' . $filename . ')';
        }

        return $label . ': ' . $message;
    }

    private function xmlDocumentsFromInput(): array
    {
        $documents = $this->input('xml_documents', array());
        if (!is_array($documents)) {
            return array();
        }

        $normalizedDocuments = array();
        foreach ($documents as $document) {
            if (!is_array($document)) {
                continue;
            }

            $documentNumber = trim((string) ($document['document_number'] ?? ''));
            $xmlPayloadBase64 = trim((string) ($document['xml_payload_base64'] ?? ''));
            $linesInput = isset($document['lines']) && is_array($document['lines']) ? $document['lines'] : array();
            $lines = array();

            foreach ($linesInput as $line) {
                if (!is_array($line)) {
                    continue;
                }

                $canonicalName = trim((string) ($line['canonical_name'] ?? ''));
                $originalName = trim((string) ($line['original_name'] ?? ''));
                if ($canonicalName === '' && $originalName === '') {
                    continue;
                }

                $lines[] = array(
                    'original_name' => $originalName,
                    'canonical_name' => $canonicalName !== '' ? $canonicalName : 'pozostale',
                    'quantity' => $this->toDecimal($line['quantity'] ?? 0),
                    'unit' => trim((string) ($line['unit'] ?? 'szt.')) ?: 'szt.',
                    'unit_net' => $this->toDecimal($line['unit_net'] ?? 0),
                    'unit_gross' => $this->toDecimal($line['unit_gross'] ?? 0),
                    'vat_rate' => $this->toDecimal($line['vat_rate'] ?? 23),
                );
            }

            if ($xmlPayloadBase64 === '' || $lines === array()) {
                continue;
            }

            $normalizedDocuments[] = array(
                'document_number' => $documentNumber,
                'import_status' => trim((string) ($document['import_status'] ?? 'ready')) === 'skipped' ? 'skipped' : 'ready',
                'document_kind' => trim((string) ($document['document_kind'] ?? 'receipt')) ?: 'receipt',
                'invoice_type' => trim((string) ($document['invoice_type'] ?? '')),
                'supplier_name' => trim((string) ($document['supplier_name'] ?? '')),
                'supplier_tax_id' => trim((string) ($document['supplier_tax_id'] ?? '')),
                'issue_date' => trim((string) ($document['issue_date'] ?? '')),
                'sale_date' => trim((string) ($document['sale_date'] ?? '')),
                'currency' => trim((string) ($document['currency'] ?? 'PLN')) ?: 'PLN',
                'notes' => trim((string) ($document['notes'] ?? '')),
                'xml_filename' => trim((string) ($document['xml_filename'] ?? 'faktura.xml')),
                'xml_hash' => trim((string) ($document['xml_hash'] ?? '')),
                'xml_payload_base64' => $xmlPayloadBase64,
                'lines' => $lines,
            );
        }

        return $normalizedDocuments;
    }

    private function arrayInput(string $key): array
    {
        $value = $this->input($key, array());
        return is_array($value) ? array_values($value) : array();
    }

    private function toDecimal($value): float
    {
        $normalized = trim(str_replace(',', '.', (string) $value));
        if ($normalized === '') {
            return 0.0;
        }

        return (float) $normalized;
    }

    private function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
    }

    private function currencyOptions(): array
    {
        return array('PLN', 'EUR', 'USD', 'GBP', 'CZK', 'HUF', 'NOK', 'SEK', 'CHF');
    }

    private function resolveExportDateRange(): array
    {
        $dateFrom = trim((string) $this->input('date_from', date('Y-m-01')));
        if (preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $dateFrom) !== 1) {
            $dateFrom = date('Y-m-01');
        }

        $dateTo = trim((string) $this->input('date_to', date('Y-m-t')));
        if (preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $dateTo) !== 1) {
            $dateTo = date('Y-m-t');
        }

        if ($dateTo < $dateFrom) {
            list($dateFrom, $dateTo) = array($dateTo, $dateFrom);
        }

        return array($dateFrom, $dateTo);
    }

    private function respondWithXlsx(string $sheetName, array $sheetRows, string $filenamePrefix, string $dateFrom, string $dateTo): void
    {
        try {
            $binary = $this->buildSimpleXlsx($sheetName, $sheetRows);
            $filename = $filenamePrefix . '_' . str_replace('-', '', $dateFrom) . '_' . str_replace('-', '', $dateTo) . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($binary));
            echo $binary;
            exit;
        } catch (Throwable $exception) {
            $this->setFlash('error', 'Nie udalo sie przygotowac eksportu XLSX: ' . $exception->getMessage());
            $this->redirect('./index.php?controller=accountingwarehouse&action=export&date_from=' . rawurlencode($dateFrom) . '&date_to=' . rawurlencode($dateTo));
        }
    }

    private function buildSimpleXlsx(string $sheetName, array $rows): string
    {
        if (!class_exists('\ZipArchive')) {
            throw new RuntimeException('Brak rozszerzenia ZipArchive na serwerze.');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'aw_xlsx_');
        if ($tempFile === false) {
            throw new RuntimeException('Nie udalo sie utworzyc pliku tymczasowego XLSX.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($tempFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($tempFile);
            throw new RuntimeException('Nie udalo sie otworzyc archiwum XLSX.');
        }

        $zip->addFromString('[Content_Types].xml', $this->xlsxContentTypesXml());
        $zip->addFromString('_rels/.rels', $this->xlsxRootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->xlsxWorkbookXml($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->xlsxWorkbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->xlsxStylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->xlsxSheetXml($rows));
        $zip->close();

        $binary = file_get_contents($tempFile);
        @unlink($tempFile);
        if ($binary === false) {
            throw new RuntimeException('Nie udalo sie odczytac gotowego pliku XLSX.');
        }

        return $binary;
    }

    private function xlsxContentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function xlsxRootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function xlsxWorkbookXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $this->xmlEscape(mb_substr($sheetName, 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function xlsxWorkbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function xlsxStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function xlsxSheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $xml .= '<row r="' . $excelRow . '">';
            foreach ($row as $columnIndex => $value) {
                $cellRef = $this->xlsxColumnName($columnIndex + 1) . $excelRow;
                $style = $rowIndex === 0 ? '1' : '0';
                if ($rowIndex > 0 && is_numeric($value)) {
                    $style = '2';
                    $xml .= '<c r="' . $cellRef . '" s="' . $style . '"><v>' . str_replace(',', '.', (string) $value) . '</v></c>';
                    continue;
                }

                $xml .= '<c r="' . $cellRef . '" s="' . $style . '" t="inlineStr"><is><t>'
                    . $this->xmlEscape((string) $value)
                    . '</t></is></c>';
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private function xlsxColumnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = (int) floor($index / 26);
        }

        return $name;
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function xmlPreviewStoragePath(): string
    {
        $this->ensureSessionStarted();
        $sessionId = session_id();
        if ($sessionId === '') {
            $sessionId = 'guest';
        }

        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'temporary' . DIRECTORY_SEPARATOR . 'accounting_warehouse_preview_' . $sessionId . '.json';
    }

    private function loadStoredXmlPreview(): array
    {
        $path = $this->xmlPreviewStoragePath();
        if (!is_file($path)) {
            return array();
        }

        $content = file_get_contents($path);
        if ($content === false || trim($content) === '') {
            return array();
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : array();
    }

    private function storeXmlPreview(array $preview): void
    {
        $path = $this->xmlPreviewStoragePath();
        file_put_contents($path, json_encode($preview, JSON_UNESCAPED_UNICODE));
    }

    private function clearStoredXmlPreview(): void
    {
        $path = $this->xmlPreviewStoragePath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function annotatePreviewDocuments(array $documents): array
    {
        $availableNames = array();
        foreach ($this->warehouse->itemNameSuggestions() as $name) {
            $availableNames[trim((string) $name)] = true;
        }
        $itemKinds = $this->warehouse->itemKindsByName();

        foreach ($documents as $documentIndex => $document) {
            if (!is_array($document) || !isset($document['lines']) || !is_array($document['lines'])) {
                continue;
            }

            if (isset($document['header']) && is_array($document['header']) && $this->looksLikeCorrectionDocument($document['header'])) {
                $documents[$documentIndex]['header']['document_kind'] = 'adjustment';
            } elseif ($this->looksLikeCostDocument($document['lines'], $itemKinds)) {
                $documents[$documentIndex]['header']['document_kind'] = 'koszt';
            }

            foreach ($document['lines'] as $lineIndex => $line) {
                $canonicalName = trim((string) ($line['canonical_name'] ?? ''));
                $confidence = trim((string) ($line['classification_confidence'] ?? 'low'));
                $isAvailable = $canonicalName !== '' && isset($availableNames[$canonicalName]);
                $documents[$documentIndex]['lines'][$lineIndex]['canonical_available'] = $isAvailable;
                $documents[$documentIndex]['lines'][$lineIndex]['highlight_unassigned'] = ($canonicalName === '' || $canonicalName === 'pozostale' || !$isAvailable || $confidence === 'low');
            }
        }

        return $documents;
    }

    private function looksLikeCostDocument(array $lines, array $itemKinds): bool
    {
        $totalValue = 0.0;
        $costValue = 0.0;

        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }

            $canonicalName = trim((string) ($line['canonical_name'] ?? ''));
            if ($canonicalName === '') {
                continue;
            }

            $lineValue = abs($this->toDecimal($line['line_net'] ?? 0));
            if ($lineValue <= 0.0) {
                $lineValue = abs($this->toDecimal($line['line_gross'] ?? 0));
            }
            if ($lineValue <= 0.0) {
                continue;
            }

            $normalized = $this->classifier->normalize($canonicalName);
            $itemKind = $itemKinds[$normalized] ?? 'towar';

            $totalValue += $lineValue;
            if ($itemKind === 'koszt') {
                $costValue += $lineValue;
            }
        }

        if ($totalValue <= 0.0) {
            return false;
        }

        return ($costValue / $totalValue) >= 0.6;
    }

    private function looksLikeCorrectionDocument(array $document): bool
    {
        $invoiceType = $this->classifier->normalize((string) ($document['invoice_type'] ?? ''));
        if ($invoiceType !== '' && ($invoiceType === 'kor' || strpos($invoiceType, 'kor') !== false || strpos($invoiceType, 'korekta') !== false || strpos($invoiceType, 'korygujaca') !== false)) {
            return true;
        }

        $documentNumber = strtoupper(trim((string) ($document['document_number'] ?? '')));
        if ($documentNumber !== '' && preg_match('/(?:^|[\/\-\s])K(?:$|[\/\-\s])/', $documentNumber) === 1) {
            return true;
        }

        foreach (array('total_net', 'total_gross') as $field) {
            if (isset($document[$field]) && $this->toDecimal($document[$field]) < 0) {
                return true;
            }
        }

        if (isset($document['lines']) && is_array($document['lines'])) {
            foreach ($document['lines'] as $line) {
                if (!is_array($line)) {
                    continue;
                }

                foreach (array('quantity', 'unit_net', 'unit_gross', 'line_net', 'line_gross') as $field) {
                    if (isset($line[$field]) && $this->toDecimal($line[$field]) < 0) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function validateSupplierHeader(array &$header): void
    {
        $supplierName = trim((string) ($header['supplier_name'] ?? ''));
        $supplierTaxId = preg_replace('/\D+/', '', (string) ($header['supplier_tax_id'] ?? ''));

        if (!is_string($supplierTaxId)) {
            $supplierTaxId = '';
        }

        if ($supplierName === '' && $supplierTaxId === '') {
            return;
        }

        if ($supplierTaxId === '') {
            $existingSupplier = $this->warehouse->supplierByName($supplierName);
            if ($existingSupplier === null || trim((string) ($existingSupplier['supplier_tax_id'] ?? '')) === '') {
                throw new RuntimeException('Dla nowego dostawcy uzupelnij NIP. Po NIP system moze tez pobrac nazwe firmy automatycznie.');
            }

            $header['supplier_tax_id'] = trim((string) ($existingSupplier['supplier_tax_id'] ?? ''));
            return;
        }

        if ($supplierName === '') {
            $resolved = $this->warehouse->supplierByTaxId($supplierTaxId);
            if ($resolved !== null) {
                $header['supplier_name'] = trim((string) ($resolved['supplier_name'] ?? ''));
                return;
            }

            $official = $this->supplierResolver->resolveByTaxId($supplierTaxId, date('Y-m-d'));
            if ($official !== null) {
                $header['supplier_name'] = trim((string) ($official['supplier_name'] ?? ''));
            }
        }
    }
}
