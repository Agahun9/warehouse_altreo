<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;
use App\Services\AccountingWarehouseClassifier;
use RuntimeException;

class AccountingWarehouseRepository
{
    private const ITEMS_TABLE = 'accounting_warehouse_items';
    private const ALIASES_TABLE = 'accounting_warehouse_aliases';
    private const DOCUMENTS_TABLE = 'accounting_warehouse_documents';
    private const LINES_TABLE = 'accounting_warehouse_lines';

    /** @var bool */
    private static $schemaEnsured = false;

    /** @var Database */
    private $database;

    /** @var AccountingWarehouseClassifier */
    private $classifier;

    public function __construct(Database $database, ?AccountingWarehouseClassifier $classifier = null)
    {
        $this->database = $database;
        $this->classifier = $classifier ?? new AccountingWarehouseClassifier();
    }

    public function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        $config = Config::get('database');
        if (((string) ($config['driver'] ?? 'mysql')) !== 'mysql') {
            self::$schemaEnsured = true;
            return;
        }

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS " . self::ITEMS_TABLE . " (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "name VARCHAR(190) NOT NULL,\n"
            . "slug VARCHAR(190) NOT NULL,\n"
            . "item_kind VARCHAR(20) NOT NULL DEFAULT 'towar',\n"
            . "unit VARCHAR(20) NOT NULL DEFAULT 'szt.',\n"
            . "is_active TINYINT(1) NOT NULL DEFAULT 1,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_accounting_warehouse_items_slug (slug)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS " . self::ALIASES_TABLE . " (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "warehouse_item_id INT UNSIGNED NOT NULL,\n"
            . "source_name VARCHAR(255) NOT NULL,\n"
            . "normalized_source_name VARCHAR(255) NOT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_accounting_warehouse_aliases_normalized (normalized_source_name),\n"
            . "KEY idx_accounting_warehouse_aliases_item (warehouse_item_id),\n"
            . "CONSTRAINT fk_accounting_warehouse_aliases_item FOREIGN KEY (warehouse_item_id) REFERENCES " . self::ITEMS_TABLE . "(id) ON DELETE CASCADE\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS " . self::DOCUMENTS_TABLE . " (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "source_type VARCHAR(20) NOT NULL DEFAULT 'manual',\n"
            . "document_kind VARCHAR(20) NOT NULL DEFAULT 'receipt',\n"
            . "document_number VARCHAR(120) DEFAULT NULL,\n"
            . "supplier_name VARCHAR(190) DEFAULT NULL,\n"
            . "supplier_tax_id VARCHAR(40) DEFAULT NULL,\n"
            . "issue_date DATE DEFAULT NULL,\n"
            . "sale_date DATE DEFAULT NULL,\n"
            . "receipt_date DATE DEFAULT NULL,\n"
            . "currency VARCHAR(10) NOT NULL DEFAULT 'PLN',\n"
            . "total_net DECIMAL(12,2) NOT NULL DEFAULT 0.00,\n"
            . "total_gross DECIMAL(12,2) NOT NULL DEFAULT 0.00,\n"
            . "notes TEXT DEFAULT NULL,\n"
            . "xml_filename VARCHAR(255) DEFAULT NULL,\n"
            . "xml_hash VARCHAR(64) DEFAULT NULL,\n"
            . "xml_payload LONGTEXT DEFAULT NULL,\n"
            . "created_by_user_id INT UNSIGNED DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_accounting_warehouse_documents_xml_hash (xml_hash),\n"
            . "KEY idx_accounting_warehouse_documents_created_at (created_at),\n"
            . "KEY idx_accounting_warehouse_documents_number (document_number),\n"
            . "KEY idx_accounting_warehouse_documents_supplier_name (supplier_name),\n"
            . "KEY idx_accounting_warehouse_documents_supplier_tax_id (supplier_tax_id),\n"
            . "KEY idx_accounting_warehouse_documents_sale_date (sale_date)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->ensureDocumentsColumn('sale_date', 'ALTER TABLE ' . self::DOCUMENTS_TABLE . ' ADD COLUMN sale_date DATE DEFAULT NULL AFTER issue_date');
        $this->ensureItemsColumn('item_kind', 'ALTER TABLE ' . self::ITEMS_TABLE . ' ADD COLUMN item_kind VARCHAR(20) NOT NULL DEFAULT \'towar\' AFTER slug');

        $invoiceTypeColumnAdded = $this->ensureDocumentsColumn(
            'invoice_type',
            'ALTER TABLE ' . self::DOCUMENTS_TABLE . ' ADD COLUMN invoice_type VARCHAR(20) DEFAULT NULL AFTER document_kind'
        );
        if ($invoiceTypeColumnAdded) {
            $this->database->query(
                'UPDATE ' . self::DOCUMENTS_TABLE . ' SET invoice_type = CASE document_kind'
                . ' WHEN \'koszt\' THEN \'koszt\''
                . ' WHEN \'adjustment\' THEN \'korekta\''
                . ' WHEN \'issue\' THEN \'towar\''
                . ' ELSE \'towar\' END'
                . ' WHERE invoice_type IS NULL'
            );
        }

        $this->ensureDocumentsColumn(
            'affects_stock',
            'ALTER TABLE ' . self::DOCUMENTS_TABLE . ' ADD COLUMN affects_stock TINYINT(1) NOT NULL DEFAULT 1 AFTER invoice_type'
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS " . self::LINES_TABLE . " (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "document_id INT UNSIGNED NOT NULL,\n"
            . "warehouse_item_id INT UNSIGNED NOT NULL,\n"
            . "related_line_id INT UNSIGNED DEFAULT NULL,\n"
            . "original_name VARCHAR(255) DEFAULT NULL,\n"
            . "canonical_name VARCHAR(190) NOT NULL,\n"
            . "quantity DECIMAL(12,3) NOT NULL DEFAULT 0.000,\n"
            . "unit VARCHAR(20) NOT NULL DEFAULT 'szt.',\n"
            . "unit_net DECIMAL(12,2) NOT NULL DEFAULT 0.00,\n"
            . "unit_gross DECIMAL(12,2) NOT NULL DEFAULT 0.00,\n"
            . "line_net DECIMAL(12,2) NOT NULL DEFAULT 0.00,\n"
            . "line_gross DECIMAL(12,2) NOT NULL DEFAULT 0.00,\n"
            . "vat_rate DECIMAL(5,2) NOT NULL DEFAULT 23.00,\n"
            . "stock_event_date DATE DEFAULT NULL,\n"
            . "stock_before_quantity DECIMAL(12,3) DEFAULT NULL,\n"
            . "stock_after_quantity DECIMAL(12,3) DEFAULT NULL,\n"
            . "stock_before_value DECIMAL(12,2) DEFAULT NULL,\n"
            . "stock_after_value DECIMAL(12,2) DEFAULT NULL,\n"
            . "deducted_value DECIMAL(12,2) DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "KEY idx_accounting_warehouse_lines_document (document_id),\n"
            . "KEY idx_accounting_warehouse_lines_item (warehouse_item_id),\n"
            . "KEY idx_accounting_warehouse_lines_related (related_line_id),\n"
            . "CONSTRAINT fk_accounting_warehouse_lines_document FOREIGN KEY (document_id) REFERENCES " . self::DOCUMENTS_TABLE . "(id) ON DELETE CASCADE,\n"
            . "CONSTRAINT fk_accounting_warehouse_lines_item FOREIGN KEY (warehouse_item_id) REFERENCES " . self::ITEMS_TABLE . "(id)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->ensureLinesColumn('related_line_id', 'ALTER TABLE ' . self::LINES_TABLE . ' ADD COLUMN related_line_id INT UNSIGNED DEFAULT NULL AFTER warehouse_item_id');
        $this->ensureLinesColumn('stock_event_date', 'ALTER TABLE ' . self::LINES_TABLE . ' ADD COLUMN stock_event_date DATE DEFAULT NULL AFTER vat_rate');
        $this->ensureLinesColumn('stock_before_quantity', 'ALTER TABLE ' . self::LINES_TABLE . ' ADD COLUMN stock_before_quantity DECIMAL(12,3) DEFAULT NULL AFTER vat_rate');
        $this->ensureLinesColumn('stock_after_quantity', 'ALTER TABLE ' . self::LINES_TABLE . ' ADD COLUMN stock_after_quantity DECIMAL(12,3) DEFAULT NULL AFTER stock_before_quantity');
        $this->ensureLinesColumn('stock_before_value', 'ALTER TABLE ' . self::LINES_TABLE . ' ADD COLUMN stock_before_value DECIMAL(12,2) DEFAULT NULL AFTER stock_after_quantity');
        $this->ensureLinesColumn('stock_after_value', 'ALTER TABLE ' . self::LINES_TABLE . ' ADD COLUMN stock_after_value DECIMAL(12,2) DEFAULT NULL AFTER stock_before_value');
        $this->ensureLinesColumn('deducted_value', 'ALTER TABLE ' . self::LINES_TABLE . ' ADD COLUMN deducted_value DECIMAL(12,2) DEFAULT NULL AFTER stock_after_value');

        self::$schemaEnsured = true;
    }

    public function overview(): array
    {
        $documentsCount = (int) $this->database->fetchColumn('SELECT COUNT(*) FROM ' . self::DOCUMENTS_TABLE);
        $row = $this->database->fetch(
            'SELECT COALESCE(SUM(overview_lines.line_net), 0) AS total_net,'
            . ' COALESCE(SUM(overview_lines.line_gross), 0) AS total_gross,'
            . ' COALESCE(SUM(overview_lines.quantity), 0) AS total_quantity'
            . ' FROM ' . self::LINES_TABLE . ' overview_lines'
            . ' INNER JOIN ' . self::DOCUMENTS_TABLE . ' overview_docs ON overview_docs.id = overview_lines.document_id'
            . ' WHERE overview_docs.affects_stock = 1'
        );

        $itemsOnStock = (int) $this->database->fetchColumn(
            'SELECT COUNT(*) FROM ('
            . ' SELECT stock_lines.warehouse_item_id'
            . ' FROM ' . self::LINES_TABLE . ' stock_lines'
            . ' INNER JOIN ' . self::DOCUMENTS_TABLE . ' stock_docs ON stock_docs.id = stock_lines.document_id'
            . ' WHERE stock_docs.affects_stock = 1'
            . ' GROUP BY stock_lines.warehouse_item_id'
            . ' HAVING ROUND(SUM(stock_lines.quantity), 3) <> 0'
            . ' ) stock_items'
        );

        return array(
            'documents_count' => $documentsCount,
            'items_on_stock' => $itemsOnStock,
            'total_quantity' => (float) ($row['total_quantity'] ?? 0),
            'total_net' => (float) ($row['total_net'] ?? 0),
            'total_gross' => (float) ($row['total_gross'] ?? 0),
        );
    }

    public function stockSummary(): array
    {
        return $this->database->fetchAll(
            'SELECT items.id, items.name, items.item_kind, items.unit,'
            . ' ROUND(COALESCE(SUM(warehouse_lines.quantity), 0), 3) AS quantity,'
            . ' ROUND(COALESCE(SUM(warehouse_lines.line_net), 0), 2) AS total_net,'
            . ' ROUND(COALESCE(SUM(warehouse_lines.line_gross), 0), 2) AS total_gross,'
            . ' COUNT(warehouse_lines.id) AS movements_count'
            . ' FROM ' . self::ITEMS_TABLE . ' items'
            . ' LEFT JOIN ' . self::LINES_TABLE . ' warehouse_lines ON warehouse_lines.warehouse_item_id = items.id'
            . ' LEFT JOIN ' . self::DOCUMENTS_TABLE . ' warehouse_docs ON warehouse_docs.id = warehouse_lines.document_id AND warehouse_docs.affects_stock = 1'
            . ' GROUP BY items.id, items.name, items.item_kind, items.unit'
            . ' HAVING ROUND(COALESCE(SUM(CASE WHEN warehouse_docs.id IS NOT NULL THEN warehouse_lines.quantity ELSE 0 END), 0), 3) <> 0'
            . ' ORDER BY items.name ASC'
        );
    }

    public function stockItemDetail(int $itemId): ?array
    {
        $item = $this->database->fetch(
            'SELECT items.id, items.name, items.item_kind, items.unit, items.is_active,'
            . ' ROUND(COALESCE(SUM(stock_lines.quantity), 0), 3) AS quantity,'
            . ' ROUND(COALESCE(SUM(stock_lines.line_net), 0), 2) AS total_net,'
            . ' ROUND(COALESCE(SUM(stock_lines.line_gross), 0), 2) AS total_gross,'
            . ' COUNT(stock_lines.id) AS movements_count'
            . ' FROM ' . self::ITEMS_TABLE . ' items'
            . ' LEFT JOIN ' . self::LINES_TABLE . ' stock_lines ON stock_lines.warehouse_item_id = items.id'
            . ' LEFT JOIN ' . self::DOCUMENTS_TABLE . ' stock_docs ON stock_docs.id = stock_lines.document_id AND stock_docs.affects_stock = 1'
            . ' WHERE items.id = :id AND (stock_lines.id IS NULL OR stock_docs.id IS NOT NULL)'
            . ' GROUP BY items.id, items.name, items.item_kind, items.unit, items.is_active'
            . ' LIMIT 1',
            array('id' => $itemId)
        );

        if (!$item) {
            return null;
        }

        $item['sources'] = $this->database->fetchAll(
            'SELECT'
            . ' COALESCE(NULLIF(detail_lines.original_name, \'\'), detail_lines.canonical_name) AS source_name,'
            . ' detail_lines.unit,'
            . ' COUNT(detail_lines.id) AS rows_count,'
            . ' COUNT(DISTINCT detail_lines.document_id) AS documents_count,'
            . ' ROUND(COALESCE(SUM(detail_lines.quantity), 0), 3) AS quantity,'
            . ' ROUND(COALESCE(SUM(detail_lines.line_net), 0), 2) AS total_net,'
            . ' ROUND(COALESCE(SUM(detail_lines.line_gross), 0), 2) AS total_gross,'
            . ' MAX(documents.sale_date) AS last_sale_date'
            . ' FROM ' . self::LINES_TABLE . ' detail_lines'
            . ' INNER JOIN ' . self::DOCUMENTS_TABLE . ' documents ON documents.id = detail_lines.document_id'
            . ' WHERE detail_lines.warehouse_item_id = :item_id AND documents.affects_stock = 1'
            . ' GROUP BY COALESCE(NULLIF(detail_lines.original_name, \'\'), detail_lines.canonical_name), detail_lines.unit'
            . ' ORDER BY total_gross DESC, source_name ASC',
            array('item_id' => $itemId)
        );

        $item['recent_lines'] = $this->database->fetchAll(
            'SELECT detail_lines.id, detail_lines.document_id, detail_lines.original_name, detail_lines.canonical_name, detail_lines.quantity,'
            . ' detail_lines.unit, detail_lines.line_net, detail_lines.line_gross, documents.document_number, documents.sale_date, documents.issue_date'
            . ' FROM ' . self::LINES_TABLE . ' detail_lines'
            . ' INNER JOIN ' . self::DOCUMENTS_TABLE . ' documents ON documents.id = detail_lines.document_id'
            . ' WHERE detail_lines.warehouse_item_id = :item_id'
            . ' ORDER BY COALESCE(documents.sale_date, documents.issue_date, detail_lines.created_at) DESC, detail_lines.id DESC'
            . ' LIMIT 20',
            array('item_id' => $itemId)
        );

        return $item;
    }

    public function allItems(): array
    {
        return $this->database->fetchAll(
            'SELECT items.id, items.name, items.item_kind, items.unit, items.is_active, items.created_at, items.updated_at,'
            . ' COUNT(aliases.id) AS aliases_count'
            . ' FROM ' . self::ITEMS_TABLE . ' items'
            . ' LEFT JOIN ' . self::ALIASES_TABLE . ' aliases ON aliases.warehouse_item_id = items.id'
            . ' GROUP BY items.id, items.name, items.item_kind, items.unit, items.is_active, items.created_at, items.updated_at'
            . ' ORDER BY items.name ASC'
        );
    }

    public function backupSnapshot(): array
    {
        $items = $this->database->fetchAll(
            'SELECT id, name, slug, item_kind, unit, is_active, created_at, updated_at'
            . ' FROM ' . self::ITEMS_TABLE
            . ' ORDER BY id ASC'
        );
        $aliases = $this->database->fetchAll(
            'SELECT id, warehouse_item_id, source_name, normalized_source_name, created_at, updated_at'
            . ' FROM ' . self::ALIASES_TABLE
            . ' ORDER BY id ASC'
        );
        $documents = $this->database->fetchAll(
            'SELECT id, source_type, document_kind, invoice_type, document_number, supplier_name, supplier_tax_id, issue_date, sale_date, receipt_date,'
            . ' currency, total_net, total_gross, notes, xml_filename, xml_hash, xml_payload, created_by_user_id, created_at'
            . ' FROM ' . self::DOCUMENTS_TABLE
            . ' ORDER BY id ASC'
        );
        $lines = $this->database->fetchAll(
            'SELECT id, document_id, warehouse_item_id, related_line_id, original_name, canonical_name, quantity, unit, unit_net, unit_gross,'
            . ' line_net, line_gross, vat_rate, stock_event_date, stock_before_quantity, stock_after_quantity, stock_before_value, stock_after_value,'
            . ' deducted_value, created_at'
            . ' FROM ' . self::LINES_TABLE
            . ' ORDER BY id ASC'
        );

        return array(
            'module' => 'accountingwarehouse',
            'format' => 'accounting-warehouse-backup-v1',
            'generated_at' => date('c'),
            'counts' => array(
                'items' => count($items),
                'aliases' => count($aliases),
                'documents' => count($documents),
                'lines' => count($lines),
            ),
            'tables' => array(
                self::ITEMS_TABLE => $items,
                self::ALIASES_TABLE => $aliases,
                self::DOCUMENTS_TABLE => $documents,
                self::LINES_TABLE => $lines,
            ),
        );
    }

    public function restoreSnapshot(array $payload): array
    {
        if (($payload['format'] ?? '') !== 'accounting-warehouse-backup-v1') {
            throw new RuntimeException('Nieobslugiwany format kopii magazynu ksiegowego.');
        }

        $tables = isset($payload['tables']) && is_array($payload['tables']) ? $payload['tables'] : array();
        foreach (array(self::ITEMS_TABLE, self::ALIASES_TABLE, self::DOCUMENTS_TABLE, self::LINES_TABLE) as $tableName) {
            if (!array_key_exists($tableName, $tables)) {
                throw new RuntimeException('W pliku kopii brakuje tabeli "' . $tableName . '".');
            }
        }

        $items = $this->normalizeBackupRows($tables[self::ITEMS_TABLE] ?? array(), array(
            'id', 'name', 'slug', 'item_kind', 'unit', 'is_active', 'created_at', 'updated_at',
        ));
        $aliases = $this->normalizeBackupRows($tables[self::ALIASES_TABLE] ?? array(), array(
            'id', 'warehouse_item_id', 'source_name', 'normalized_source_name', 'created_at', 'updated_at',
        ));
        $documents = $this->normalizeBackupRows($tables[self::DOCUMENTS_TABLE] ?? array(), array(
            'id', 'source_type', 'document_kind', 'invoice_type', 'document_number', 'supplier_name', 'supplier_tax_id', 'issue_date', 'sale_date', 'receipt_date',
            'currency', 'total_net', 'total_gross', 'notes', 'xml_filename', 'xml_hash', 'xml_payload', 'created_by_user_id', 'created_at',
        ));
        $lines = $this->normalizeBackupRows($tables[self::LINES_TABLE] ?? array(), array(
            'id', 'document_id', 'warehouse_item_id', 'related_line_id', 'original_name', 'canonical_name', 'quantity', 'unit', 'unit_net', 'unit_gross',
            'line_net', 'line_gross', 'vat_rate', 'stock_event_date', 'stock_before_quantity', 'stock_after_quantity', 'stock_before_value',
            'stock_after_value', 'deducted_value', 'created_at',
        ));

        $this->database->transaction(function (Database $database) use ($items, $aliases, $documents, $lines): void {
            $database->delete(self::LINES_TABLE, '1 = 1');
            $database->delete(self::ALIASES_TABLE, '1 = 1');
            $database->delete(self::DOCUMENTS_TABLE, '1 = 1');
            $database->delete(self::ITEMS_TABLE, '1 = 1');

            foreach ($items as $row) {
                $database->insert(self::ITEMS_TABLE, $row);
            }

            foreach ($aliases as $row) {
                $database->insert(self::ALIASES_TABLE, $row);
            }

            foreach ($documents as $row) {
                $database->insert(self::DOCUMENTS_TABLE, $row);
            }

            foreach ($lines as $row) {
                $database->insert(self::LINES_TABLE, $row);
            }

            $this->resetAutoIncrement(self::ITEMS_TABLE, $items);
            $this->resetAutoIncrement(self::ALIASES_TABLE, $aliases);
            $this->resetAutoIncrement(self::DOCUMENTS_TABLE, $documents);
            $this->resetAutoIncrement(self::LINES_TABLE, $lines);
        });

        return array(
            'items' => count($items),
            'aliases' => count($aliases),
            'documents' => count($documents),
            'lines' => count($lines),
        );
    }

    public function macroCatalog(): array
    {
        $items = $this->database->fetchAll(
            'SELECT items.id, items.name, items.item_kind, items.unit, items.is_active, items.created_at, items.updated_at'
            . ' FROM ' . self::ITEMS_TABLE . ' items'
            . ' WHERE items.is_active = 1'
            . ' ORDER BY items.name ASC'
        );

        foreach ($items as $index => $item) {
            $aliases = $this->macroAliasesForItem((int) ($item['id'] ?? 0), false);
            $items[$index]['aliases_count'] = count($aliases);
            $items[$index]['aliases_list'] = implode('||', $aliases);
        }

        return $items;
    }

    public function macroDefinition(int $itemId): ?array
    {
        $macro = $this->database->fetch(
            'SELECT id, name, slug, item_kind, unit, is_active, created_at, updated_at'
            . ' FROM ' . self::ITEMS_TABLE
            . ' WHERE id = :id'
            . ' LIMIT 1',
            array('id' => $itemId)
        );
        if (!$macro) {
            return null;
        }

        $macro['aliases'] = $this->macroAliasesForItem($itemId, true);

        return $macro;
    }

    public function deleteMacroDefinition(int $itemId): void
    {
        $macro = $this->macroDefinition($itemId);
        if ($macro === null) {
            throw new RuntimeException('Nie znaleziono pozycji ksiegowej do usuniecia.');
        }

        $usageCount = (int) $this->database->fetchColumn(
            'SELECT COUNT(*) FROM ' . self::LINES_TABLE . ' WHERE warehouse_item_id = :item_id',
            array('item_id' => $itemId)
        );

        $this->database->transaction(function (Database $database) use ($itemId, $usageCount): void {
            if ($usageCount > 0) {
                $database->update(
                    self::ITEMS_TABLE,
                    array('is_active' => 0),
                    'id = :id',
                    array('id' => $itemId)
                );
                $database->delete(self::ALIASES_TABLE, 'warehouse_item_id = :item_id', array('item_id' => $itemId));
                return;
            }

            $database->delete(self::ALIASES_TABLE, 'warehouse_item_id = :item_id', array('item_id' => $itemId));
            $database->delete(self::ITEMS_TABLE, 'id = :id', array('id' => $itemId));
        });
    }

    public function reassignItemSource(int $itemId, string $sourceName, string $targetCanonicalName): int
    {
        $item = $this->macroDefinition($itemId);
        if ($item === null) {
            throw new RuntimeException('Nie znaleziono pozycji ksiegowej do przepiecia.');
        }

        $sourceName = trim($sourceName);
        if ($sourceName === '') {
            throw new RuntimeException('Brak nazwy zrodlowej do przepiecia.');
        }

        $targetCanonicalName = trim($targetCanonicalName);
        if ($targetCanonicalName === '') {
            throw new RuntimeException('Wybierz docelowa pozycje ksiegowa.');
        }

        $targetItemId = $this->findOrCreateItemId($targetCanonicalName, (string) ($item['unit'] ?? 'szt.'));
        $this->rememberAlias($sourceName, $targetItemId);

        return (int) $this->database->transaction(function (Database $database) use ($itemId, $sourceName, $targetCanonicalName, $targetItemId): int {
            $sql = 'UPDATE ' . self::LINES_TABLE
                . ' SET warehouse_item_id = :target_item_id, canonical_name = :target_name'
                . ' WHERE warehouse_item_id = :current_item_id'
                . ' AND COALESCE(NULLIF(original_name, \'\'), canonical_name) = :source_name';

            return (int) $database->query($sql, array(
                'target_item_id' => $targetItemId,
                'target_name' => $targetCanonicalName,
                'current_item_id' => $itemId,
                'source_name' => $sourceName,
            ))->rowCount();
        });
    }

    public function documentList(array $filters = array(), int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);
        list($where, $params) = $this->buildDocumentListWhere($filters);

        $sql = 'SELECT documents.*, COUNT(document_lines.id) AS lines_count'
            . ' FROM ' . self::DOCUMENTS_TABLE . ' documents'
            . ' LEFT JOIN ' . self::LINES_TABLE . ' document_lines ON document_lines.document_id = documents.id';

        if ($where !== array()) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY documents.id'
            . ' ORDER BY COALESCE(documents.sale_date, documents.issue_date, documents.created_at) DESC, documents.id DESC'
            . ' LIMIT ' . $limit . ' OFFSET ' . $offset;

        return $this->database->fetchAll($sql, $params);
    }

    public function documentCount(array $filters = array()): int
    {
        list($where, $params) = $this->buildDocumentListWhere($filters);

        $sql = 'SELECT COUNT(*) FROM ' . self::DOCUMENTS_TABLE . ' documents';
        if ($where !== array()) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        return (int) $this->database->fetchColumn($sql, $params);
    }

    private function buildDocumentListWhere(array $filters): array
    {
        $params = array();
        $where = array();

        $documentNumber = trim((string) ($filters['document_number'] ?? ''));
        if ($documentNumber !== '') {
            $where[] = 'documents.document_number LIKE :document_number';
            $params['document_number'] = '%' . $documentNumber . '%';
        }

        $supplierName = trim((string) ($filters['supplier_name'] ?? ''));
        if ($supplierName !== '') {
            $where[] = 'documents.supplier_name LIKE :supplier_name';
            $params['supplier_name'] = '%' . $supplierName . '%';
        }

        $supplierTaxId = trim((string) ($filters['supplier_tax_id'] ?? ''));
        if ($supplierTaxId !== '') {
            $where[] = 'documents.supplier_tax_id LIKE :supplier_tax_id';
            $params['supplier_tax_id'] = '%' . $supplierTaxId . '%';
        }

        $documentKind = trim((string) ($filters['document_kind'] ?? ''));
        if ($documentKind !== '' && in_array($documentKind, array('receipt', 'koszt', 'adjustment', 'issue'), true)) {
            $where[] = 'documents.document_kind = :document_kind';
            $params['document_kind'] = $documentKind;
        }

        $invoiceType = trim((string) ($filters['invoice_type'] ?? ''));
        if ($invoiceType !== '' && in_array($invoiceType, array('towar', 'koszt', 'korekta'), true)) {
            $where[] = 'documents.invoice_type = :invoice_type';
            $params['invoice_type'] = $invoiceType;
        }

        $sourceType = trim((string) ($filters['source_type'] ?? ''));
        if ($sourceType !== '' && in_array($sourceType, array('manual', 'xml', 'legacy_sql'), true)) {
            $where[] = 'documents.source_type = :source_type';
            $params['source_type'] = $sourceType;
        }

        $currency = trim((string) ($filters['currency'] ?? ''));
        if ($currency !== '') {
            $where[] = 'documents.currency = :currency';
            $params['currency'] = $currency;
        }

        $notes = trim((string) ($filters['notes'] ?? ''));
        if ($notes !== '') {
            $where[] = 'documents.notes LIKE :notes';
            $params['notes'] = '%' . $notes . '%';
        }

        $dateFrom = $this->nullableDate($filters['date_from'] ?? null);
        if ($dateFrom !== null) {
            $where[] = 'COALESCE(documents.sale_date, documents.issue_date) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        $dateTo = $this->nullableDate($filters['date_to'] ?? null);
        if ($dateTo !== null) {
            $where[] = 'COALESCE(documents.sale_date, documents.issue_date) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $addedFrom = $this->nullableDate($filters['added_from'] ?? null);
        if ($addedFrom !== null) {
            $where[] = 'DATE(documents.created_at) >= :added_from';
            $params['added_from'] = $addedFrom;
        }

        $addedTo = $this->nullableDate($filters['added_to'] ?? null);
        if ($addedTo !== null) {
            $where[] = 'DATE(documents.created_at) <= :added_to';
            $params['added_to'] = $addedTo;
        }

        $amountMin = trim((string) ($filters['amount_min'] ?? ''));
        if ($amountMin !== '') {
            $where[] = 'documents.total_gross >= :amount_min';
            $params['amount_min'] = $this->toDecimal($amountMin);
        }

        $amountMax = trim((string) ($filters['amount_max'] ?? ''));
        if ($amountMax !== '') {
            $where[] = 'documents.total_gross <= :amount_max';
            $params['amount_max'] = $this->toDecimal($amountMax);
        }

        return array($where, $params);
    }

    public function recentMovements(int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));

        return $this->database->fetchAll(
            'SELECT movement_lines.*, documents.document_number, documents.document_kind, documents.source_type, documents.sale_date, documents.issue_date, documents.supplier_name,'
            . ' items.name AS item_name'
            . ' FROM ' . self::LINES_TABLE . ' movement_lines'
            . ' INNER JOIN ' . self::DOCUMENTS_TABLE . ' documents ON documents.id = movement_lines.document_id'
            . ' INNER JOIN ' . self::ITEMS_TABLE . ' items ON items.id = movement_lines.warehouse_item_id'
            . ' ORDER BY movement_lines.created_at DESC, movement_lines.id DESC'
            . ' LIMIT ' . $limit
        );
    }

    public function documentWithLines(int $documentId): ?array
    {
        $document = $this->database->fetch(
            'SELECT * FROM ' . self::DOCUMENTS_TABLE . ' WHERE id = :id LIMIT 1',
            array('id' => $documentId)
        );
        if (!$document) {
            return null;
        }

        $document['lines'] = $this->database->fetchAll(
            'SELECT'
            . ' MIN(document_lines.id) AS id,'
            . ' document_lines.document_id,'
            . ' document_lines.warehouse_item_id,'
            . ' document_lines.original_name,'
            . ' document_lines.canonical_name,'
            . ' SUM(document_lines.quantity) AS quantity,'
            . ' document_lines.unit,'
            . ' document_lines.unit_net,'
            . ' document_lines.unit_gross,'
            . ' SUM(document_lines.line_net) AS line_net,'
            . ' SUM(document_lines.line_gross) AS line_gross,'
            . ' document_lines.vat_rate,'
            . ' items.name AS item_name,'
            . ' items.item_kind'
            . ' FROM ' . self::LINES_TABLE . ' document_lines'
            . ' INNER JOIN ' . self::ITEMS_TABLE . ' items ON items.id = document_lines.warehouse_item_id'
            . ' WHERE document_lines.document_id = :document_id'
            . ' GROUP BY document_lines.document_id, document_lines.warehouse_item_id, document_lines.original_name, document_lines.canonical_name,'
            . ' document_lines.unit, document_lines.unit_net, document_lines.unit_gross, document_lines.vat_rate, items.name, items.item_kind'
            . ' ORDER BY MIN(document_lines.id) ASC',
            array('document_id' => $documentId)
        );

        return $document;
    }

    public function updateDocument(int $documentId, array $header, array $lines): void
    {
        $document = $this->documentWithLines($documentId);
        if ($document === null) {
            throw new RuntimeException('Nie znaleziono dokumentu do edycji.');
        }

        if ($lines === array()) {
            throw new RuntimeException('Dokument musi zawierac przynajmniej jedna pozycje.');
        }

        $duplicate = $this->findDuplicateDocument($header, $documentId);
        if ($duplicate !== null) {
            if ((string) ($duplicate['duplicate_reason'] ?? '') === 'xml_hash') {
                throw new RuntimeException('Inny dokument z tym XML juz istnieje.');
            }

            throw new RuntimeException('Podobny dokument juz istnieje: #' . (int) ($duplicate['id'] ?? 0) . '.');
        }

        if (((string) ($document['document_kind'] ?? 'receipt')) === 'issue'
            || trim((string) ($header['document_kind'] ?? 'receipt')) === 'issue'
        ) {
            throw new RuntimeException('Edycja dokumentow wyjscia z magazynu nie jest jeszcze dostepna.');
        }

        $affectsStock = (bool) ((int) ($document['affects_stock'] ?? 1));

        list($preparedLines, $totalNet, $totalGross) = $this->prepareInboundLines(
            $lines,
            trim((string) ($header['document_kind'] ?? ($document['document_kind'] ?? 'receipt'))) ?: 'receipt',
            $affectsStock
        );

        if ($preparedLines === array()) {
            throw new RuntimeException('Nie znaleziono poprawnych pozycji do zapisania.');
        }

        $this->database->transaction(function (Database $database) use ($documentId, $header, $preparedLines, $totalNet, $totalGross): void {
            $documentKindValue = trim((string) ($header['document_kind'] ?? 'receipt')) ?: 'receipt';
            $database->update(self::DOCUMENTS_TABLE, array(
                'source_type' => trim((string) ($header['source_type'] ?? 'manual')) ?: 'manual',
                'document_kind' => $documentKindValue,
                'invoice_type' => $this->invoiceTypeForDocumentKind($documentKindValue),
                'document_number' => $this->nullableString($header['document_number'] ?? null),
                'supplier_name' => $this->nullableString($header['supplier_name'] ?? null),
                'supplier_tax_id' => $this->nullableString($header['supplier_tax_id'] ?? null),
                'issue_date' => $this->nullableDate($header['issue_date'] ?? null),
                'sale_date' => $this->nullableDate($header['sale_date'] ?? null),
                'receipt_date' => $this->nullableDate($header['sale_date'] ?? $header['receipt_date'] ?? null),
                'currency' => trim((string) ($header['currency'] ?? 'PLN')) ?: 'PLN',
                'total_net' => round($this->toDecimal($header['total_net'] ?? $totalNet) ?: $totalNet, 2),
                'total_gross' => round($this->toDecimal($header['total_gross'] ?? $totalGross) ?: $totalGross, 2),
                'notes' => $this->nullableString($header['notes'] ?? null),
                'xml_filename' => $this->nullableString($header['xml_filename'] ?? null),
                'xml_hash' => $this->nullableString($header['xml_hash'] ?? null),
                'xml_payload' => $this->nullableString($header['xml_payload'] ?? null),
            ), 'id = :id', array('id' => $documentId));

            $database->delete(self::LINES_TABLE, 'document_id = :document_id', array('document_id' => $documentId));

            foreach ($preparedLines as $line) {
                $database->insert(self::LINES_TABLE, array(
                    'document_id' => $documentId,
                    'warehouse_item_id' => $line['warehouse_item_id'],
                    'related_line_id' => $line['related_line_id'] ?? null,
                    'original_name' => $line['original_name'],
                    'canonical_name' => $line['canonical_name'],
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit'],
                    'unit_net' => $line['unit_net'],
                    'unit_gross' => $line['unit_gross'],
                    'line_net' => $line['line_net'],
                    'line_gross' => $line['line_gross'],
                    'vat_rate' => $line['vat_rate'],
                    'stock_event_date' => $line['stock_event_date'] ?? $this->nullableDate($header['sale_date'] ?? $header['issue_date'] ?? null),
                    'stock_before_quantity' => null,
                    'stock_after_quantity' => null,
                    'stock_before_value' => null,
                    'stock_after_value' => null,
                    'deducted_value' => null,
                ));
            }
        });
    }

    public function itemKindsByName(): array
    {
        $kinds = array();
        foreach ($this->macroCatalog() as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $normalized = $this->classifier->normalize($name);
            if ($normalized === '') {
                continue;
            }
            $kinds[$normalized] = trim((string) ($row['item_kind'] ?? 'towar')) ?: 'towar';
        }

        return $kinds;
    }

    public function itemNameSuggestions(): array
    {
        $names = array();
        foreach ($this->macroCatalog() as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '') {
                $names[$name] = $name;
            }
        }

        return array_values($names);
    }

    public function canonicalNameForSource(string $sourceName): ?string
    {
        $normalized = $this->classifier->normalize($sourceName);
        if ($normalized === '') {
            return null;
        }

        $items = $this->database->fetchAll(
            'SELECT name FROM ' . self::ITEMS_TABLE . ' WHERE is_active = 1 ORDER BY CHAR_LENGTH(name) DESC'
        );
        foreach ($items as $item) {
            $itemName = trim((string) ($item['name'] ?? ''));
            if ($itemName === '') {
                continue;
            }

            $normalizedItemName = $this->classifier->normalize($itemName);
            if ($normalizedItemName !== '' && ($normalized === $normalizedItemName || mb_strpos($normalized, $normalizedItemName) !== false)) {
                return $itemName;
            }
        }

        $row = $this->database->fetch(
            'SELECT items.name'
            . ' FROM ' . self::ALIASES_TABLE . ' aliases'
            . ' INNER JOIN ' . self::ITEMS_TABLE . ' items ON items.id = aliases.warehouse_item_id'
            . ' WHERE aliases.normalized_source_name = :normalized'
            . ' LIMIT 1',
            array('normalized' => $normalized)
        );

        if (!$row) {
            $row = $this->database->fetch(
                'SELECT items.name'
                . ' FROM ' . self::ALIASES_TABLE . ' aliases'
                . ' INNER JOIN ' . self::ITEMS_TABLE . ' items ON items.id = aliases.warehouse_item_id'
                . ' WHERE :normalized LIKE CONCAT("%", aliases.normalized_source_name, "%")'
                . ' ORDER BY CHAR_LENGTH(aliases.normalized_source_name) DESC'
                . ' LIMIT 1',
                array('normalized' => $normalized)
            );
            if (!$row) {
                return null;
            }
        }

        $name = trim((string) ($row['name'] ?? ''));
        return $name !== '' ? $name : null;
    }

    public function supplierLookup(string $query = '', string $mode = 'mixed', int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        $query = trim($query);
        $where = array();
        $params = array();

        if ($query !== '') {
            if ($mode === 'tax_id') {
                $where[] = 'supplier_tax_id LIKE :query';
                $params['query'] = '%' . $query . '%';
            } elseif ($mode === 'name') {
                $where[] = 'supplier_name LIKE :query';
                $params['query'] = '%' . $query . '%';
            } else {
                $where[] = '(supplier_name LIKE :query_name OR supplier_tax_id LIKE :query_tax_id)';
                $params['query_name'] = '%' . $query . '%';
                $params['query_tax_id'] = '%' . $query . '%';
            }
        }

        $sql = 'SELECT supplier_name, supplier_tax_id, COUNT(*) AS documents_count,'
            . ' MAX(COALESCE(receipt_date, sale_date, issue_date, created_at)) AS latest_date'
            . ' FROM ' . self::DOCUMENTS_TABLE
            . ' WHERE supplier_name IS NOT NULL AND TRIM(supplier_name) <> ""';

        if ($where !== array()) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY supplier_name, supplier_tax_id'
            . ' ORDER BY latest_date DESC, documents_count DESC, supplier_name ASC'
            . ' LIMIT ' . $limit;

        return $this->database->fetchAll($sql, $params);
    }

    public function supplierByTaxId(string $taxId): ?array
    {
        $taxId = preg_replace('/\D+/', '', $taxId);
        if (!is_string($taxId) || $taxId === '') {
            return null;
        }

        $row = $this->database->fetch(
            'SELECT supplier_name, supplier_tax_id,'
            . ' MAX(COALESCE(sale_date, issue_date, created_at)) AS latest_date'
            . ' FROM ' . self::DOCUMENTS_TABLE
            . ' WHERE supplier_tax_id = :tax_id'
            . ' AND supplier_name IS NOT NULL AND TRIM(supplier_name) <> ""'
            . ' GROUP BY supplier_name, supplier_tax_id'
            . ' ORDER BY latest_date DESC'
            . ' LIMIT 1',
            array('tax_id' => $taxId)
        );

        return $row ?: null;
    }

    public function supplierByName(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $row = $this->database->fetch(
            'SELECT supplier_name, supplier_tax_id,'
            . ' MAX(COALESCE(sale_date, issue_date, created_at)) AS latest_date'
            . ' FROM ' . self::DOCUMENTS_TABLE
            . ' WHERE LOWER(TRIM(supplier_name)) = LOWER(TRIM(:supplier_name))'
            . ' GROUP BY supplier_name, supplier_tax_id'
            . ' ORDER BY latest_date DESC'
            . ' LIMIT 1',
            array('supplier_name' => $name)
        );

        return $row ?: null;
    }

    public function findDuplicateDocument(array $header, int $excludeDocumentId = 0): ?array
    {
        $xmlHash = trim((string) ($header['xml_hash'] ?? ''));
        if ($xmlHash !== '') {
            $params = array('xml_hash' => $xmlHash);
            $sql = 'SELECT id, document_number, supplier_name, supplier_tax_id, issue_date, sale_date, currency'
                . ' FROM ' . self::DOCUMENTS_TABLE
                . ' WHERE xml_hash = :xml_hash';
            if ($excludeDocumentId > 0) {
                $sql .= ' AND id <> :exclude_id';
                $params['exclude_id'] = $excludeDocumentId;
            }
            $sql .= ' LIMIT 1';

            $row = $this->database->fetch($sql, $params);
            if ($row) {
                $row['duplicate_reason'] = 'xml_hash';
                return $row;
            }
        }

        $documentNumber = trim((string) ($header['document_number'] ?? ''));
        if ($documentNumber === '') {
            return null;
        }

        // Ta sama faktura (dokladnie ten sam numer) moze wystapic u dwoch roznych sprzedawcow -
        // dubel rozpoznajemy dopiero po dopasowaniu NIP (a przy jego braku - nazwy sprzedawcy),
        // wiec najpierw pobieramy WSZYSTKICH kandydatow po numerze i porownujemy w PHP z
        // normalizacja NIP (bez spacji/myslnikow/prefiksu kraju), zeby roznice w formacie zapisu
        // nie psuly ani nie falszowaly dopasowania.
        $supplierTaxId = (string) preg_replace('/\D+/', '', (string) ($header['supplier_tax_id'] ?? ''));
        $supplierName = trim((string) ($header['supplier_name'] ?? ''));
        if ($supplierTaxId === '' && $supplierName === '') {
            return null;
        }

        $params = array('document_number' => $documentNumber);
        $where = array('document_number = :document_number');
        if ($excludeDocumentId > 0) {
            $where[] = 'id <> :exclude_id';
            $params['exclude_id'] = $excludeDocumentId;
        }

        $candidates = $this->database->fetchAll(
            'SELECT id, document_number, supplier_name, supplier_tax_id, issue_date, sale_date, currency'
            . ' FROM ' . self::DOCUMENTS_TABLE
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY id DESC',
            $params
        );

        foreach ($candidates as $candidate) {
            $candidateTaxId = (string) preg_replace('/\D+/', '', (string) ($candidate['supplier_tax_id'] ?? ''));
            if ($supplierTaxId !== '' && $candidateTaxId !== '') {
                if ($supplierTaxId === $candidateTaxId) {
                    $candidate['duplicate_reason'] = 'document_supplier';
                    return $candidate;
                }
                continue;
            }

            if ($supplierName !== '' && mb_strtolower(trim((string) ($candidate['supplier_name'] ?? ''))) === mb_strtolower($supplierName)) {
                $candidate['duplicate_reason'] = 'document_supplier';
                return $candidate;
            }
        }

        return null;
    }

    public function searchDocumentsByNumber(string $query, int $limit = 5, int $excludeDocumentId = 0): array
    {
        $query = trim($query);
        if ($query === '') {
            return array();
        }

        $limit = max(1, min(20, $limit));
        $params = array(
            'query_exact' => $query,
            'query_exact_order' => $query,
            'query_like' => '%' . $query . '%',
        );
        $where = array(
            '(document_number = :query_exact OR document_number LIKE :query_like)',
        );

        if ($excludeDocumentId > 0) {
            $where[] = 'id <> :exclude_id';
            $params['exclude_id'] = $excludeDocumentId;
        }

        return $this->database->fetchAll(
            'SELECT id, document_number, supplier_name, supplier_tax_id, issue_date, sale_date, currency, source_type, document_kind'
            . ' FROM ' . self::DOCUMENTS_TABLE
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY CASE WHEN document_number = :query_exact_order THEN 0 ELSE 1 END, id DESC'
            . ' LIMIT ' . (int) $limit,
            $params
        );
    }

    public function matchDocumentsByNumbers(array $numbers): array
    {
        $seen = array();
        $queries = array();
        foreach ($numbers as $number) {
            $trimmed = trim((string) $number);
            if ($trimmed === '' || isset($seen[$trimmed])) {
                continue;
            }
            $seen[$trimmed] = true;
            $queries[] = $trimmed;
        }

        if ($queries === array()) {
            return array();
        }

        $placeholders = array();
        $params = array();
        foreach ($queries as $index => $number) {
            $placeholder = ':number_' . $index;
            $placeholders[] = $placeholder;
            $params['number_' . $index] = mb_strtolower((string) $number);
        }

        $rows = $this->database->fetchAll(
            'SELECT id, document_number, supplier_name, supplier_tax_id, issue_date, sale_date, currency, document_kind, invoice_type'
            . ' FROM ' . self::DOCUMENTS_TABLE
            . ' WHERE LOWER(TRIM(document_number)) IN (' . implode(', ', $placeholders) . ')'
            . ' ORDER BY id ASC',
            $params
        );

        $documentsByNumber = array();
        foreach ($rows as $row) {
            $key = mb_strtolower(trim((string) ($row['document_number'] ?? '')));
            if ($key === '') {
                continue;
            }
            $documentsByNumber[$key][] = $row;
        }

        $results = array();
        foreach ($queries as $number) {
            $key = mb_strtolower($number);
            $matches = $documentsByNumber[$key] ?? array();
            $results[] = array(
                'query' => $number,
                'found' => $matches !== array(),
                'documents' => $matches,
            );
        }

        return $results;
    }

    /**
     * Normalizuje numer dokumentu do postaci porownywalnej miedzy roznymi zrodlami
     * (magazyn ksiegowy vs plik od ksiegowej): usuwa spacje (w tym twarde \xC2\xA0),
     * ujednolica wielkosc liter i separatory, oraz usuwa zbedne wiodace zera w kazdym
     * numerycznym segmencie (np. "06" i "6" albo "00003506" i "3506" to ten sam numer).
     * Dzieki temu roznice w formatowaniu numeru faktury miedzy systemami nie powoduja
     * falszywego braku dopasowania przy porownaniu z ksiegowa.
     */
    public function normalizeDocumentNumberKey(string $value): string
    {
        $value = str_replace("\xC2\xA0", ' ', trim($value));
        if ($value === '') {
            return '';
        }

        $value = mb_strtolower($value);
        $value = preg_replace_callback('/\d+/', static function (array $matches): string {
            $stripped = ltrim($matches[0], '0');
            return $stripped === '' ? '0' : $stripped;
        }, $value) ?? $value;

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }

    /**
     * Dla zadanej listy numerow dokumentow zwraca mape znormalizowany_numer => lista dokumentow
     * wraz z klasyfikacja (koszt/towar/mieszane/korekta) i sumami netto/brutto - do porownania
     * z zestawieniem ksiegowej (Towary/Koszt). Dopasowanie numerow jest odporne na roznice
     * w formatowaniu (spacje, separatory, wiodace zera) - patrz normalizeDocumentNumberKey().
     */
    public function findDocumentsByNumbersForReconciliation(array $numbers): array
    {
        $wantedKeys = array();
        foreach ($numbers as $number) {
            $key = $this->normalizeDocumentNumberKey((string) $number);
            if ($key !== '') {
                $wantedKeys[$key] = true;
            }
        }

        if ($wantedKeys === array()) {
            return array();
        }

        $rows = $this->database->fetchAll(
            'SELECT documents.id AS document_id, documents.document_kind, documents.document_number, documents.supplier_name,'
            . ' documents.supplier_tax_id, documents.issue_date, documents.sale_date, documents.currency,'
            . ' documents.total_net, documents.total_gross,'
            . ' export_lines.id AS line_id, export_lines.canonical_name, export_lines.line_net, export_lines.line_gross, items.item_kind'
            . ' FROM ' . self::DOCUMENTS_TABLE . ' documents'
            . ' LEFT JOIN ' . self::LINES_TABLE . ' export_lines ON export_lines.document_id = documents.id'
            . ' LEFT JOIN ' . self::ITEMS_TABLE . ' items ON items.id = export_lines.warehouse_item_id'
            . ' WHERE documents.document_number IS NOT NULL AND TRIM(documents.document_number) <> \'\''
            . ' ORDER BY documents.id ASC, export_lines.id ASC',
            array()
        );

        $documents = array();
        foreach ($rows as $row) {
            $documentId = (int) ($row['document_id'] ?? 0);
            if (!isset($documents[$documentId])) {
                $documents[$documentId] = array(
                    'id' => $documentId,
                    'document_kind' => trim((string) ($row['document_kind'] ?? 'receipt')) ?: 'receipt',
                    'document_number' => (string) ($row['document_number'] ?? ''),
                    'supplier_name' => (string) ($row['supplier_name'] ?? ''),
                    'supplier_tax_id' => (string) ($row['supplier_tax_id'] ?? ''),
                    'issue_date' => (string) ($row['issue_date'] ?? ''),
                    'sale_date' => (string) ($row['sale_date'] ?? ''),
                    'currency' => (string) ($row['currency'] ?? 'PLN'),
                    'total_net' => (float) ($row['total_net'] ?? 0),
                    'total_gross' => (float) ($row['total_gross'] ?? 0),
                    'lines' => array(),
                );
            }

            if ($row['line_id'] !== null) {
                $documents[$documentId]['lines'][] = array(
                    'canonical_name' => (string) ($row['canonical_name'] ?? ''),
                    'item_kind' => $this->normalizeItemKind((string) ($row['item_kind'] ?? 'towar')),
                    'line_net' => (float) ($row['line_net'] ?? 0),
                    'line_gross' => (float) ($row['line_gross'] ?? 0),
                );
            }
        }

        $documentsByNumber = array();
        foreach ($documents as $documentId => $document) {
            $key = $this->normalizeDocumentNumberKey((string) ($document['document_number'] ?? ''));
            if ($key === '' || !isset($wantedKeys[$key])) {
                continue;
            }
            $document['classification'] = $this->classifyDocument($document);
            $documentsByNumber[$key][] = $document;
        }

        return $documentsByNumber;
    }

    public function saveDocument(array $header, array $lines, int $userId): int
    {
        if ($lines === array()) {
            throw new RuntimeException('Dokument musi zawierac przynajmniej jedna pozycje.');
        }

        $duplicate = $this->findDuplicateDocument($header);
        if ($duplicate !== null) {
            if ((string) ($duplicate['duplicate_reason'] ?? '') === 'xml_hash') {
                throw new RuntimeException('Ten plik XML zostal juz wczesniej zaimportowany.');
            }

            throw new RuntimeException('Podobny dokument juz istnieje: #' . (int) ($duplicate['id'] ?? 0) . '.');
        }

        $documentKind = trim((string) ($header['document_kind'] ?? 'receipt')) ?: 'receipt';
        if ($documentKind === 'issue') {
            return $this->saveIssueDocument($header, $lines, $userId);
        }

        $affectsStock = $this->resolveAffectsStock($header);

        list($preparedLines, $totalNet, $totalGross) = $this->prepareInboundLines(
            $lines,
            $documentKind,
            $affectsStock
        );

        if ($preparedLines === array()) {
            throw new RuntimeException('Nie znaleziono poprawnych pozycji do zapisania.');
        }

        return (int) $this->database->transaction(function (Database $database) use ($header, $preparedLines, $userId, $totalNet, $totalGross, $affectsStock): int {
            $documentKindValue = trim((string) ($header['document_kind'] ?? 'receipt')) ?: 'receipt';
            $documentId = (int) $database->insert(self::DOCUMENTS_TABLE, array(
                'source_type' => trim((string) ($header['source_type'] ?? 'manual')) ?: 'manual',
                'affects_stock' => $affectsStock ? 1 : 0,
                'document_kind' => $documentKindValue,
                'invoice_type' => $this->invoiceTypeForDocumentKind($documentKindValue),
                'document_number' => $this->nullableString($header['document_number'] ?? null),
                'supplier_name' => $this->nullableString($header['supplier_name'] ?? null),
                'supplier_tax_id' => $this->nullableString($header['supplier_tax_id'] ?? null),
                'issue_date' => $this->nullableDate($header['issue_date'] ?? null),
                'sale_date' => $this->nullableDate($header['sale_date'] ?? null),
                'receipt_date' => $this->nullableDate($header['sale_date'] ?? $header['receipt_date'] ?? null),
                'currency' => trim((string) ($header['currency'] ?? 'PLN')) ?: 'PLN',
                'total_net' => round($this->toDecimal($header['total_net'] ?? $totalNet) ?: $totalNet, 2),
                'total_gross' => round($this->toDecimal($header['total_gross'] ?? $totalGross) ?: $totalGross, 2),
                'notes' => $this->nullableString($header['notes'] ?? null),
                'xml_filename' => $this->nullableString($header['xml_filename'] ?? null),
                'xml_hash' => $this->nullableString($header['xml_hash'] ?? null),
                'xml_payload' => $this->nullableString($header['xml_payload'] ?? null),
                'created_by_user_id' => $userId > 0 ? $userId : null,
            ));

            foreach ($preparedLines as $line) {
                $database->insert(self::LINES_TABLE, array(
                    'document_id' => $documentId,
                    'warehouse_item_id' => $line['warehouse_item_id'],
                    'related_line_id' => $line['related_line_id'] ?? null,
                    'original_name' => $line['original_name'],
                    'canonical_name' => $line['canonical_name'],
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit'],
                    'unit_net' => $line['unit_net'],
                    'unit_gross' => $line['unit_gross'],
                    'line_net' => $line['line_net'],
                    'line_gross' => $line['line_gross'],
                    'vat_rate' => $line['vat_rate'],
                    'stock_event_date' => $line['stock_event_date'] ?? $this->nullableDate($header['sale_date'] ?? $header['issue_date'] ?? null),
                    'stock_before_quantity' => null,
                    'stock_after_quantity' => null,
                    'stock_before_value' => null,
                    'stock_after_value' => null,
                    'deducted_value' => null,
                ));
            }

            return $documentId;
        });
    }

    public function previewAutoBalanceIssue(array $header, float $targetValue, string $targetMode = 'gross'): array
    {
        $targetMode = strtolower(trim($targetMode)) === 'net' ? 'net' : 'gross';
        $requestedValue = abs(round($targetValue, 2));
        if ($requestedValue <= 0.0) {
            throw new RuntimeException('Podaj kwote wyrownania rozna od 0.');
        }

        $lots = $this->untouchedTowarSourceLots();
        if ($lots === array()) {
            throw new RuntimeException('Nie znaleziono pozycji typu towar bez wczesniejszego wyjscia magazynowego.');
        }

        $selection = $this->selectLotsClosestToTarget($lots, $requestedValue, $targetMode);
        $selectedLots = isset($selection['lots']) && is_array($selection['lots']) ? $selection['lots'] : array();
        if ($selectedLots === array()) {
            throw new RuntimeException('Nie udalo sie dobrac pozycji do wyrownania dla wskazanej kwoty.');
        }

        $preparedLines = array();
        $totalNet = 0.0;
        $totalGross = 0.0;
        $totalQuantity = 0;
        $summaryRows = array();
        $previewRows = array();
        foreach ($selectedLots as $selectedLot) {
            $row = isset($selectedLot['row']) && is_array($selectedLot['row']) ? $selectedLot['row'] : array();
            $quantity = (int) ($selectedLot['quantity'] ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            $itemId = (int) ($row['warehouse_item_id'] ?? 0);
            $canonicalName = trim((string) ($row['canonical_name'] ?? ''));
            $originalName = trim((string) ($row['original_name'] ?? ''));
            $unit = trim((string) ($row['unit'] ?? 'szt.')) ?: 'szt.';
            $unitNet = (float) ($row['unit_net'] ?? 0);
            $unitGross = (float) ($row['unit_gross'] ?? 0);
            $vatRate = (float) ($row['vat_rate'] ?? 23);
            $sourceLineId = (int) ($row['id'] ?? 0);

            for ($index = 0; $index < $quantity; $index++) {
                $preparedLines[] = array(
                    'warehouse_item_id' => $itemId,
                    'related_line_id' => $sourceLineId,
                    'original_name' => $originalName !== '' ? $originalName : $canonicalName,
                    'canonical_name' => $canonicalName,
                    'quantity' => -1,
                    'unit' => $unit,
                    'unit_net' => $unitNet,
                    'unit_gross' => $unitGross,
                    'line_net' => round(-1 * $unitNet, 2),
                    'line_gross' => round(-1 * $unitGross, 2),
                    'vat_rate' => $vatRate,
                    'stock_event_date' => $this->nullableDate($header['sale_date'] ?? $header['issue_date'] ?? null),
                );
            }

            $totalQuantity += $quantity;
            $totalNet += $unitNet * $quantity;
            $totalGross += $unitGross * $quantity;
            $summaryRows[] = $canonicalName . ' x ' . $quantity . ' (' . number_format($unitGross, 2, '.', '') . ' brutto/szt.)';
            $previewRows[] = array(
                'canonical_name' => $canonicalName,
                'original_name' => $originalName,
                'quantity' => $quantity,
                'unit' => $unit,
                'unit_net' => $unitNet,
                'unit_gross' => $unitGross,
                'line_net' => round($unitNet * $quantity, 2),
                'line_gross' => round($unitGross * $quantity, 2),
                'source_document_id' => (int) ($row['document_id'] ?? 0),
                'source_document_number' => (string) ($row['document_number'] ?? ''),
                'source_supplier_name' => (string) ($row['supplier_name'] ?? ''),
                'source_date' => (string) (($row['sale_date'] ?? '') ?: ($row['issue_date'] ?? '')),
            );
        }

        if ($preparedLines === array()) {
            throw new RuntimeException('Nie znaleziono poprawnych pozycji do wyrownania.');
        }

        $header['source_type'] = trim((string) ($header['source_type'] ?? 'manual')) ?: 'manual';
        $header['document_kind'] = 'issue';
        $header['supplier_name'] = trim((string) ($header['supplier_name'] ?? 'Wyrownanie stanu magazynu')) ?: 'Wyrownanie stanu magazynu';
        $header['total_net'] = round($totalNet, 2);
        $header['total_gross'] = round($totalGross, 2);

        $notes = trim((string) ($header['notes'] ?? ''));
        $targetLabel = $targetMode === 'net' ? 'netto' : 'brutto';
        $autoNote = 'Wyrownanie automatyczne. Cel ' . $targetLabel . ': ' . number_format($requestedValue, 2, '.', '')
            . ' PLN. Osiagniete netto: ' . number_format($totalNet, 2, '.', '')
            . ' PLN. Osiagniete brutto: ' . number_format($totalGross, 2, '.', '')
            . ' PLN. Pozycje: ' . implode('; ', $summaryRows) . '.';
        $header['notes'] = $notes !== '' ? ($notes . ' | ' . $autoNote) : $autoNote;

        return array(
            'header' => $header,
            'prepared_lines' => $preparedLines,
            'preview_rows' => $previewRows,
            'requested_value' => $requestedValue,
            'requested_mode' => $targetMode,
            'actual_gross' => round($totalGross, 2),
            'actual_net' => round($totalNet, 2),
            'quantity' => $totalQuantity,
        );
    }

    public function saveAutoBalanceIssue(array $header, float $targetValue, string $targetMode, int $userId): array
    {
        $plan = $this->previewAutoBalanceIssue($header, $targetValue, $targetMode);
        $documentId = $this->persistIssueDocument(
            (array) ($plan['header'] ?? $header),
            (array) ($plan['prepared_lines'] ?? array()),
            $userId,
            (float) ($plan['actual_net'] ?? 0),
            (float) ($plan['actual_gross'] ?? 0)
        );

        $plan['document_id'] = $documentId;
        return $plan;
    }

    public function monthlyIssueReport(string $month): array
    {
        if (preg_match('/^\d{4}\-\d{2}$/', $month) !== 1) {
            $month = date('Y-m');
        }

        $dateFrom = $month . '-01';
        $dateTo = date('Y-m-t', strtotime($dateFrom));

        $rows = $this->database->fetchAll(
            'SELECT issue_lines.id, issue_lines.document_id, issue_lines.warehouse_item_id, issue_lines.original_name, issue_lines.canonical_name,'
            . ' issue_lines.quantity, issue_lines.stock_event_date, issue_lines.unit_net, issue_lines.unit_gross,'
            . ' issue_docs.document_number AS issue_document_number, issue_docs.sale_date AS issue_sale_date, issue_docs.issue_date AS issue_issue_date,'
            . ' items.name AS item_name,'
            . ' source_lines.document_id AS source_document_id,'
            . ' source_docs.document_number AS source_document_number, source_docs.supplier_name AS source_supplier_name,'
            . ' source_docs.sale_date AS source_sale_date, source_docs.issue_date AS source_issue_date'
            . ' FROM ' . self::LINES_TABLE . ' issue_lines'
            . ' INNER JOIN ' . self::DOCUMENTS_TABLE . ' issue_docs ON issue_docs.id = issue_lines.document_id'
            . ' INNER JOIN ' . self::ITEMS_TABLE . ' items ON items.id = issue_lines.warehouse_item_id'
            . ' LEFT JOIN ' . self::LINES_TABLE . ' source_lines ON source_lines.id = issue_lines.related_line_id'
            . ' LEFT JOIN ' . self::DOCUMENTS_TABLE . ' source_docs ON source_docs.id = source_lines.document_id'
            . ' WHERE issue_docs.document_kind = :document_kind'
            . ' AND COALESCE(issue_docs.sale_date, issue_docs.issue_date) BETWEEN :date_from AND :date_to'
            . ' ORDER BY COALESCE(issue_docs.sale_date, issue_docs.issue_date) ASC, issue_docs.id ASC, issue_lines.id ASC',
            array(
                'document_kind' => 'issue',
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            )
        );

        $grouped = array();
        foreach ($rows as $row) {
            $groupKey = implode('|', array(
                (int) ($row['document_id'] ?? 0),
                (int) ($row['warehouse_item_id'] ?? 0),
                (int) ($row['source_document_id'] ?? 0),
                (string) ($row['original_name'] ?? ''),
            ));

            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = array(
                    'issue_document_id' => (int) ($row['document_id'] ?? 0),
                    'issue_document_number' => (string) ($row['issue_document_number'] ?? ''),
                    'issue_date' => (string) (($row['stock_event_date'] ?? '') ?: (($row['issue_sale_date'] ?? '') ?: ($row['issue_issue_date'] ?? ''))),
                    'item_name' => (string) (($row['item_name'] ?? '') ?: ($row['canonical_name'] ?? '')),
                    'original_name' => (string) ($row['original_name'] ?? ''),
                    'source_document_id' => (int) ($row['source_document_id'] ?? 0),
                    'source_document_number' => (string) ($row['source_document_number'] ?? ''),
                    'source_supplier_name' => (string) ($row['source_supplier_name'] ?? ''),
                    'source_date' => (string) (($row['source_sale_date'] ?? '') ?: ($row['source_issue_date'] ?? '')),
                    'quantity' => 0,
                    'issue_value_net' => 0.0,
                    'issue_value_gross' => 0.0,
                );
            }

            $grouped[$groupKey]['quantity'] += abs((float) ($row['quantity'] ?? 0));
            $grouped[$groupKey]['issue_value_net'] += abs((float) ($row['unit_net'] ?? 0));
            $grouped[$groupKey]['issue_value_gross'] += abs((float) ($row['unit_gross'] ?? 0));
        }

        return array_values($grouped);
    }

    public function documentsForExport(string $dateFrom, string $dateTo): array
    {
        $rows = $this->database->fetchAll(
            'SELECT documents.id AS document_id, documents.document_kind, documents.document_number, documents.supplier_name, documents.supplier_tax_id,'
            . ' documents.issue_date, documents.sale_date, documents.currency, documents.notes,'
            . ' export_lines.id AS line_id, export_lines.original_name, export_lines.canonical_name, export_lines.quantity, export_lines.unit,'
            . ' export_lines.unit_net, export_lines.unit_gross, export_lines.line_net, export_lines.line_gross,'
            . ' items.item_kind'
            . ' FROM ' . self::DOCUMENTS_TABLE . ' documents'
            . ' LEFT JOIN ' . self::LINES_TABLE . ' export_lines ON export_lines.document_id = documents.id'
            . ' LEFT JOIN ' . self::ITEMS_TABLE . ' items ON items.id = export_lines.warehouse_item_id'
            . ' WHERE documents.document_kind IN (\'receipt\', \'koszt\', \'adjustment\')'
            . ' AND COALESCE(documents.sale_date, documents.issue_date) BETWEEN :date_from AND :date_to'
            . ' ORDER BY COALESCE(documents.sale_date, documents.issue_date) ASC, documents.id ASC, export_lines.id ASC',
            array(
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            )
        );

        $documents = array();
        foreach ($rows as $row) {
            $documentId = (int) ($row['document_id'] ?? 0);
            if (!isset($documents[$documentId])) {
                $documents[$documentId] = array(
                    'id' => $documentId,
                    'document_kind' => trim((string) ($row['document_kind'] ?? 'receipt')) ?: 'receipt',
                    'document_number' => (string) ($row['document_number'] ?? ''),
                    'supplier_name' => (string) ($row['supplier_name'] ?? ''),
                    'supplier_tax_id' => (string) ($row['supplier_tax_id'] ?? ''),
                    'issue_date' => (string) ($row['issue_date'] ?? ''),
                    'sale_date' => (string) ($row['sale_date'] ?? ''),
                    'currency' => (string) ($row['currency'] ?? 'PLN'),
                    'notes' => (string) ($row['notes'] ?? ''),
                    'lines' => array(),
                );
            }

            if ($row['line_id'] === null) {
                // Dokument bez pozycji lub z pozycja wskazujaca na usuniety wpis w katalogu -
                // nie pomijamy calego dokumentu w eksporcie z powodu brakujacego JOIN-a.
                continue;
            }

            $documents[$documentId]['lines'][] = array(
                'original_name' => (string) ($row['original_name'] ?? ''),
                'canonical_name' => (string) ($row['canonical_name'] ?? ''),
                'item_kind' => $this->normalizeItemKind((string) ($row['item_kind'] ?? 'towar')),
                'quantity' => (float) ($row['quantity'] ?? 0),
                'unit' => (string) ($row['unit'] ?? 'szt.'),
                'unit_net' => (float) ($row['unit_net'] ?? 0),
                'unit_gross' => (float) ($row['unit_gross'] ?? 0),
                'line_net' => (float) ($row['line_net'] ?? 0),
                'line_gross' => (float) ($row['line_gross'] ?? 0),
            );
        }

        foreach ($documents as $documentId => $document) {
            $documents[$documentId]['classification'] = $this->classifyDocument($document);
        }

        return array_values($documents);
    }

    private function classifyDocument(array $document): string
    {
        $documentKind = trim((string) ($document['document_kind'] ?? 'receipt')) ?: 'receipt';

        if ($documentKind === 'adjustment') {
            return 'korekta';
        }

        // document_kind = 'koszt' to recznie/automatycznie oznaczona cala faktura kosztowa -
        // w praktyce jedyny wiarygodny sygnal, bo pozycje rzadko sa tagowane pojedynczo.
        if ($documentKind === 'koszt') {
            return 'koszt';
        }

        return $this->classifyDocumentLines((array) ($document['lines'] ?? array()));
    }

    private function classifyDocumentLines(array $lines): string
    {
        $totalLineCount = count($lines);
        if ($totalLineCount === 0) {
            return 'towar';
        }

        $kosztLineCount = 0;
        $kosztNonShippingLineCount = 0;
        foreach ($lines as $line) {
            if ($this->normalizeItemKind((string) ($line['item_kind'] ?? 'towar')) === 'koszt') {
                $kosztLineCount++;
                if (!$this->isShippingLine($line)) {
                    $kosztNonShippingLineCount++;
                }
            }
        }

        // Faktura, w ktorej kazda pozycja jest typu koszt (np. sama wysylka) - traktujemy jako FV koszt,
        // nawet jesli nikt recznie nie oznaczyl calego dokumentu jako 'koszt'.
        if ($kosztLineCount === $totalLineCount) {
            return 'koszt';
        }

        // Mieszana: faktura typu towar, ktora ma przynajmniej jedna pozycje typu koszt inna niz
        // wysylka - wysylka wystepuje niemal na kazdej fakturze towarowej i sama w sobie nie
        // powinna oznaczac dokumentu jako "mieszany".
        if ($kosztNonShippingLineCount >= 1) {
            return 'mieszane';
        }

        return 'towar';
    }

    private function isShippingLine(array $line): bool
    {
        $normalized = $this->classifier->normalize((string) ($line['canonical_name'] ?? ''));

        return $normalized === 'wysylka';
    }

    private function prepareInboundLines(array $lines, string $documentKind = 'receipt', bool $affectsStock = true): array
    {
        $preparedLines = array();
        $totalNet = 0.0;
        $totalGross = 0.0;
        $runningStockByItem = array();

        foreach ($lines as $line) {
            $canonicalName = trim((string) ($line['canonical_name'] ?? ''));
            if ($canonicalName === '') {
                throw new RuntimeException('Kazda pozycja musi miec nazwe ksiegowa.');
            }

            $quantity = $this->toDecimal($line['quantity'] ?? 0);
            if ($quantity == 0.0 && $affectsStock) {
                continue;
            }

            if (abs($quantity - round($quantity)) > 0.0001) {
                throw new RuntimeException('Ilosc moze byc tylko liczba calkowita dla pozycji: ' . $canonicalName);
            }

            $unit = trim((string) ($line['unit'] ?? 'szt.'));
            if ($unit === '') {
                $unit = 'szt.';
            }

            $unitNet = $this->toDecimal($line['unit_net'] ?? 0);
            $unitGross = $this->toDecimal($line['unit_gross'] ?? 0);
            $lineNet = $this->toDecimal($line['line_net'] ?? round($quantity * $unitNet, 2));
            $lineGross = $this->toDecimal($line['line_gross'] ?? round($quantity * $unitGross, 2));
            $vatRate = $this->toDecimal($line['vat_rate'] ?? 23);

            if ($lineNet == 0.0 && $unitNet != 0.0) {
                $lineNet = round($quantity * $unitNet, 2);
            }
            if ($lineGross == 0.0 && $unitGross != 0.0) {
                $lineGross = round($quantity * $unitGross, 2);
            }

            // item_kind (towar/koszt/korekta) jest ustawiany wylacznie przez makra
            // (AccountingWarehouseController::savemacro/updatemacro). Zapis lub edycja
            // dokumentu NIE moze zmieniac klasyfikacji istniejacej pozycji - stad brak
            // aktualizacji item_kind ponizej, poza pierwszym utworzeniem pozycji.
            $itemKind = $this->normalizeItemKind((string) ($line['item_kind'] ?? 'towar'));
            $itemId = $this->findOrCreateItemId($canonicalName, $unit, $itemKind);
            $originalName = trim((string) ($line['original_name'] ?? ''));
            if ($originalName !== '') {
                $this->rememberAlias($originalName, $itemId);
            }

            if (!isset($runningStockByItem[$itemId])) {
                $runningStockByItem[$itemId] = $this->currentStockQuantityForItem($itemId);
            }

            if ($affectsStock && $documentKind === 'adjustment' && $quantity < 0) {
                $projectedQuantity = $runningStockByItem[$itemId] + $quantity;
                if ($projectedQuantity < -0.0001) {
                    throw new RuntimeException(
                        'Korekta nie moze zdjac wiecej sztuk niz jest na magazynie dla pozycji: ' . $canonicalName
                        . '. Dostepne: ' . number_format($runningStockByItem[$itemId], 3, '.', '') . '.'
                    );
                }
            }

            foreach ($this->explodeInboundUnits($quantity) as $unitQuantity) {
                $preparedLines[] = array(
                    'warehouse_item_id' => $itemId,
                    'related_line_id' => null,
                    'original_name' => $originalName !== '' ? $originalName : $canonicalName,
                    'canonical_name' => $canonicalName,
                    'quantity' => $unitQuantity,
                    'unit' => $unit,
                    'unit_net' => $unitNet,
                    'unit_gross' => $unitGross,
                    'line_net' => round($unitQuantity * $unitNet, 2),
                    'line_gross' => round($unitQuantity * $unitGross, 2),
                    'vat_rate' => $vatRate,
                );
            }

            $runningStockByItem[$itemId] += $quantity;

            $totalNet += $lineNet;
            $totalGross += $lineGross;
        }

        return array($preparedLines, $totalNet, $totalGross);
    }

    private function saveIssueDocument(array $header, array $lines, int $userId): int
    {
        $preparedLines = array();
        $totalNet = 0.0;
        $totalGross = 0.0;
        $reservedSourceCounts = array();
        $usedItemIds = array();

        foreach ($lines as $line) {
            $canonicalName = trim((string) ($line['canonical_name'] ?? ''));
            if ($canonicalName === '') {
                throw new RuntimeException('Kazda pozycja musi miec nazwe ksiegowa.');
            }

            $requestedQuantity = $this->toDecimal($line['quantity'] ?? 0);
            if ($requestedQuantity <= 0) {
                continue;
            }

            if (abs($requestedQuantity - round($requestedQuantity)) > 0.0001) {
                throw new RuntimeException('Wyjscie z magazynu obsluguje tylko pelne sztuki.');
            }

            $unit = trim((string) ($line['unit'] ?? 'szt.')) ?: 'szt.';
            $item = $this->findItemByCanonicalName($canonicalName);
            if ($item === null) {
                throw new RuntimeException('Nie znaleziono pozycji magazynowej dla: ' . $canonicalName . '.');
            }

            if ($this->normalizeItemKind((string) ($item['item_kind'] ?? '')) !== 'towar') {
                throw new RuntimeException('Wyjscie z magazynu jest dostepne tylko dla pozycji typu towar: ' . $canonicalName . '.');
            }

            $itemId = (int) ($item['id'] ?? 0);
            if ($itemId <= 0) {
                throw new RuntimeException('Nie znaleziono poprawnej pozycji magazynowej dla: ' . $canonicalName . '.');
            }

            if (isset($usedItemIds[$itemId])) {
                throw new RuntimeException('Pozycja "' . $canonicalName . '" moze wystapic tylko raz w jednym dokumencie wyjscia.');
            }
            $usedItemIds[$itemId] = true;

            $availableUnits = $this->availableSourceUnits($itemId, $reservedSourceCounts);

            if (count($availableUnits) < (int) round($requestedQuantity)) {
                throw new RuntimeException('Brak wystarczajacego stanu dla pozycji: ' . $canonicalName . '.');
            }

            $originalName = trim((string) ($line['original_name'] ?? ''));

            for ($index = 0; $index < (int) round($requestedQuantity); $index++) {
                $sourceUnit = $availableUnits[$index];
                $unitGross = (float) ($sourceUnit['unit_gross'] ?? 0);
                $unitNet = (float) ($sourceUnit['unit_net'] ?? 0);

                $preparedLines[] = array(
                    'warehouse_item_id' => $itemId,
                    'related_line_id' => (int) ($sourceUnit['id'] ?? 0),
                    'original_name' => $originalName !== '' ? $originalName : $canonicalName,
                    'canonical_name' => $canonicalName,
                    'quantity' => -1,
                    'unit' => $unit,
                    'unit_net' => $unitNet,
                    'unit_gross' => $unitGross,
                    'line_net' => round(-1 * $unitNet, 2),
                    'line_gross' => round(-1 * $unitGross, 2),
                    'vat_rate' => (float) ($sourceUnit['vat_rate'] ?? 23),
                    'stock_event_date' => $this->nullableDate($header['sale_date'] ?? $header['issue_date'] ?? null),
                );

                $sourceLineId = (int) ($sourceUnit['id'] ?? 0);
                $reservedSourceCounts[$sourceLineId] = (int) ($reservedSourceCounts[$sourceLineId] ?? 0) + 1;
                $totalNet += $unitNet;
                $totalGross += $unitGross;
            }
        }

        if ($preparedLines === array()) {
            throw new RuntimeException('Nie znaleziono poprawnych pozycji do wydania z magazynu.');
        }

        return $this->persistIssueDocument($header, $preparedLines, $userId, $totalNet, $totalGross);
    }

    private function explodeInboundUnits(float $quantity): array
    {
        $units = array();
        $whole = (int) floor(abs($quantity) + 0.0001);
        for ($index = 0; $index < $whole; $index++) {
            $units[] = $quantity >= 0 ? 1.0 : -1.0;
        }

        $remainder = round(abs($quantity) - $whole, 3);
        if ($remainder > 0) {
            $units[] = $quantity >= 0 ? $remainder : -$remainder;
        }

        if ($units === array()) {
            $units[] = $quantity;
        }

        return $units;
    }

    private function currentStockQuantityForItem(int $itemId): float
    {
        $row = $this->database->fetch(
            'SELECT ROUND(COALESCE(SUM(stock_lines.quantity), 0), 3) AS quantity'
            . ' FROM ' . self::LINES_TABLE . ' stock_lines'
            . ' INNER JOIN ' . self::DOCUMENTS_TABLE . ' stock_docs ON stock_docs.id = stock_lines.document_id'
            . ' WHERE stock_lines.warehouse_item_id = :item_id AND stock_docs.affects_stock = 1',
            array('item_id' => $itemId)
        );

        return (float) ($row['quantity'] ?? 0);
    }

    private function currentStockQuantitiesForItems(array $itemIds): array
    {
        $itemIds = array_values(array_unique(array_map('intval', $itemIds)));
        $itemIds = array_values(array_filter($itemIds, static function (int $itemId): bool {
            return $itemId > 0;
        }));
        if ($itemIds === array()) {
            return array();
        }

        $placeholders = array();
        $params = array();
        foreach ($itemIds as $index => $itemId) {
            $placeholder = ':item_id_' . $index;
            $placeholders[] = $placeholder;
            $params['item_id_' . $index] = $itemId;
        }

        $rows = $this->database->fetchAll(
            'SELECT stock_lines.warehouse_item_id, ROUND(COALESCE(SUM(stock_lines.quantity), 0), 3) AS quantity'
            . ' FROM ' . self::LINES_TABLE . ' stock_lines'
            . ' INNER JOIN ' . self::DOCUMENTS_TABLE . ' stock_docs ON stock_docs.id = stock_lines.document_id'
            . ' WHERE stock_lines.warehouse_item_id IN (' . implode(', ', $placeholders) . ')'
            . ' AND stock_docs.affects_stock = 1'
            . ' GROUP BY stock_lines.warehouse_item_id',
            $params
        );

        $quantities = array_fill_keys($itemIds, 0.0);
        foreach ($rows as $row) {
            $itemId = (int) ($row['warehouse_item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }
            $quantities[$itemId] = (float) ($row['quantity'] ?? 0);
        }

        return $quantities;
    }

    private function findItemByCanonicalName(string $name): ?array
    {
        $slug = $this->slugify($name);
        $row = $this->database->fetch(
            'SELECT id, name, item_kind, unit, is_active'
            . ' FROM ' . self::ITEMS_TABLE
            . ' WHERE slug = :slug'
            . ' LIMIT 1',
            array('slug' => $slug)
        );

        return $row ?: null;
    }

    private function availableSourceUnits(int $itemId, array $reservedSourceCounts = array()): array
    {
        $rows = $this->database->fetchAll(
            'SELECT source_lines.*,'
            . ' (source_lines.quantity - COALESCE(('
            . '   SELECT ABS(SUM(used_lines.quantity))'
            . '   FROM ' . self::LINES_TABLE . ' used_lines'
            . '   WHERE used_lines.related_line_id = source_lines.id'
            . '   AND used_lines.quantity < 0'
            . ' ), 0)) AS available_quantity'
            . ' FROM ' . self::LINES_TABLE . ' source_lines'
            . ' INNER JOIN ' . self::DOCUMENTS_TABLE . ' source_docs ON source_docs.id = source_lines.document_id'
            . ' WHERE source_lines.warehouse_item_id = :item_id'
            . ' AND source_lines.quantity > 0'
            . ' HAVING available_quantity >= 1'
            // FIFO: zdejmujemy towar od najstarszego przyjecia.
            . ' ORDER BY COALESCE(source_docs.sale_date, source_docs.issue_date, source_docs.created_at) ASC, source_lines.id ASC',
            array('item_id' => $itemId)
        );

        $units = array();
        foreach ($rows as $row) {
            $sourceLineId = (int) ($row['id'] ?? 0);
            $availableQuantity = (float) ($row['available_quantity'] ?? 0);
            $availableQuantity -= (float) ($reservedSourceCounts[$sourceLineId] ?? 0);
            $wholeUnits = (int) floor($availableQuantity + 0.0001);
            for ($index = 0; $index < $wholeUnits; $index++) {
                $units[] = $row;
            }
        }

        return $units;
    }

    private function untouchedTowarSourceLots(): array
    {
        $rows = $this->database->fetchAll(
            'SELECT source_lines.*, items.name AS item_name, items.item_kind, source_docs.document_number, source_docs.supplier_name, source_docs.sale_date, source_docs.issue_date,'
            . ' COALESCE(used_lines.issue_units, 0) AS issue_units'
            . ' FROM ' . self::LINES_TABLE . ' source_lines'
            . ' INNER JOIN ' . self::DOCUMENTS_TABLE . ' source_docs ON source_docs.id = source_lines.document_id'
            . ' INNER JOIN ' . self::ITEMS_TABLE . ' items ON items.id = source_lines.warehouse_item_id'
            . ' LEFT JOIN ('
            . '   SELECT related_line_id, COUNT(*) AS issue_units'
            . '   FROM ' . self::LINES_TABLE
            . '   WHERE quantity < 0 AND related_line_id IS NOT NULL'
            . '   GROUP BY related_line_id'
            . ' ) used_lines ON used_lines.related_line_id = source_lines.id'
            . ' WHERE source_lines.quantity > 0'
            . ' AND source_docs.affects_stock = 1'
            . ' AND LOWER(TRIM(items.item_kind)) = :item_kind'
            . ' AND COALESCE(used_lines.issue_units, 0) = 0'
            . ' ORDER BY COALESCE(source_docs.sale_date, source_docs.issue_date, source_docs.created_at) ASC, source_lines.id ASC',
            array('item_kind' => 'towar')
        );

        $itemIds = array();
        foreach ($rows as $row) {
            $itemId = (int) ($row['warehouse_item_id'] ?? 0);
            if ($itemId > 0) {
                $itemIds[] = $itemId;
            }
        }

        $stockQuantities = $this->currentStockQuantitiesForItems($itemIds);
        $remainingStockByItem = array();
        $lots = array();
        foreach ($rows as $row) {
            $itemId = (int) ($row['warehouse_item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }

            if (!isset($remainingStockByItem[$itemId])) {
                $remainingStockByItem[$itemId] = max(0, (int) floor(((float) ($stockQuantities[$itemId] ?? 0)) + 0.0001));
            }

            $lineQuantity = max(0, (int) floor(abs((float) ($row['quantity'] ?? 0)) + 0.0001));
            $usableQuantity = min($lineQuantity, (int) $remainingStockByItem[$itemId]);
            if ($usableQuantity <= 0) {
                continue;
            }

            $remainingStockByItem[$itemId] -= $usableQuantity;
            $lots[] = array(
                'row' => $row,
                'quantity' => $usableQuantity,
                'unit_net' => (float) ($row['unit_net'] ?? 0),
                'unit_gross' => (float) ($row['unit_gross'] ?? 0),
                'gross_cents' => max(0, (int) round(abs((float) ($row['unit_gross'] ?? 0)) * 100)),
            );
        }

        return $lots;
    }

    private function selectLotsClosestToTarget(array $lots, float $targetValue, string $targetMode = 'gross'): array
    {
        $targetCents = max(1, (int) round($targetValue * 100));
        $valueKey = $targetMode === 'net' ? 'net_cents' : 'gross_cents';
        $workingLots = array();

        foreach (array_values($lots) as $index => $lot) {
            $lot['lot_index'] = $index;
            $lot['net_cents'] = max(0, (int) round(abs((float) ($lot['unit_net'] ?? 0)) * 100));
            $lot['gross_cents'] = max(0, (int) round(abs((float) ($lot['unit_gross'] ?? 0)) * 100));
            $workingLots[] = $lot;
        }

        usort($workingLots, static function (array $left, array $right) use ($valueKey): int {
            $leftValue = (int) ($left[$valueKey] ?? 0);
            $rightValue = (int) ($right[$valueKey] ?? 0);
            if ($leftValue !== $rightValue) {
                return $rightValue <=> $leftValue;
            }
            return ((int) (($left['lot_index'] ?? 0))) <=> ((int) (($right['lot_index'] ?? 0)));
        });

        $selectedQuantities = array();
        $currentSum = 0;
        foreach ($workingLots as $lot) {
            $unitValue = (int) ($lot[$valueKey] ?? 0);
            $availableQuantity = (int) ($lot['quantity'] ?? 0);
            $lotIndex = (int) ($lot['lot_index'] ?? 0);
            if ($unitValue <= 0 || $availableQuantity <= 0) {
                continue;
            }

            $missing = max(0, $targetCents - $currentSum);
            $take = $missing > 0 ? min($availableQuantity, (int) floor($missing / $unitValue)) : 0;
            if ($take > 0) {
                $selectedQuantities[$lotIndex] = $take;
                $currentSum += $take * $unitValue;
            }
        }

        $bestSum = $currentSum;
        $bestDiff = abs($targetCents - $currentSum);
        $iterations = 0;
        do {
            $iterations++;
            $improved = false;
            $bestMove = null;
            $iterationBestDiff = $bestDiff;
            $iterationBestSum = $bestSum;

            foreach ($workingLots as $lot) {
                $lotIndex = (int) ($lot['lot_index'] ?? 0);
                $unitValue = (int) ($lot[$valueKey] ?? 0);
                $availableQuantity = (int) ($lot['quantity'] ?? 0);
                $selectedQuantity = (int) ($selectedQuantities[$lotIndex] ?? 0);
                if ($unitValue <= 0) {
                    continue;
                }

                if ($selectedQuantity < $availableQuantity) {
                    $newSum = $currentSum + $unitValue;
                    $newDiff = abs($targetCents - $newSum);
                    if ($newDiff < $iterationBestDiff || ($newDiff === $iterationBestDiff && $newSum <= $targetCents && $iterationBestSum > $targetCents)) {
                        $bestMove = array('type' => 'add', 'lot_index' => $lotIndex, 'new_sum' => $newSum, 'new_diff' => $newDiff);
                        $iterationBestDiff = $newDiff;
                        $iterationBestSum = $newSum;
                        $improved = true;
                    }
                }

                if ($selectedQuantity > 0) {
                    $newSum = $currentSum - $unitValue;
                    $newDiff = abs($targetCents - $newSum);
                    if ($newDiff < $iterationBestDiff || ($newDiff === $iterationBestDiff && $newSum <= $targetCents && $iterationBestSum > $targetCents)) {
                        $bestMove = array('type' => 'remove', 'lot_index' => $lotIndex, 'new_sum' => $newSum, 'new_diff' => $newDiff);
                        $iterationBestDiff = $newDiff;
                        $iterationBestSum = $newSum;
                        $improved = true;
                    }
                }
            }

            if ($improved && is_array($bestMove)) {
                $moveLotIndex = (int) ($bestMove['lot_index'] ?? 0);
                if (($bestMove['type'] ?? '') === 'add') {
                    $selectedQuantities[$moveLotIndex] = (int) ($selectedQuantities[$moveLotIndex] ?? 0) + 1;
                } else {
                    $selectedQuantities[$moveLotIndex] = max(0, (int) ($selectedQuantities[$moveLotIndex] ?? 0) - 1);
                }
                $currentSum = (int) ($bestMove['new_sum'] ?? $currentSum);
                $bestDiff = $iterationBestDiff;
                $bestSum = $iterationBestSum;
            }
        } while ($improved && $iterations < 300);

        $selectedLots = array();
        $actualGross = 0.0;
        foreach ($lots as $index => $lot) {
            $quantity = (int) ($selectedQuantities[$index] ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            $selectedLots[] = array(
                'row' => $lot['row'],
                'quantity' => $quantity,
            );
            $actualGross += ((float) ($lot['unit_gross'] ?? 0)) * $quantity;
        }

        return array(
            'lots' => $selectedLots,
            'actual_gross' => round($actualGross, 2),
        );
    }

    private function persistIssueDocument(array $header, array $preparedLines, int $userId, float $totalNet, float $totalGross): int
    {
        return (int) $this->database->transaction(function (Database $database) use ($header, $preparedLines, $userId, $totalNet, $totalGross): int {
            $documentId = (int) $database->insert(self::DOCUMENTS_TABLE, array(
                'source_type' => trim((string) ($header['source_type'] ?? 'manual')) ?: 'manual',
                'document_kind' => 'issue',
                'invoice_type' => $this->invoiceTypeForDocumentKind('issue'),
                'document_number' => $this->nullableString($header['document_number'] ?? null),
                'supplier_name' => $this->nullableString($header['supplier_name'] ?? 'Wyjscie z magazynu'),
                'supplier_tax_id' => $this->nullableString($header['supplier_tax_id'] ?? null),
                'issue_date' => $this->nullableDate($header['issue_date'] ?? null),
                'sale_date' => $this->nullableDate($header['sale_date'] ?? null),
                'receipt_date' => $this->nullableDate($header['sale_date'] ?? null),
                'currency' => trim((string) ($header['currency'] ?? 'PLN')) ?: 'PLN',
                'total_net' => round($totalNet, 2),
                'total_gross' => round($totalGross, 2),
                'notes' => $this->nullableString($header['notes'] ?? null),
                'created_by_user_id' => $userId > 0 ? $userId : null,
            ));

            foreach ($preparedLines as $line) {
                $database->insert(self::LINES_TABLE, array(
                    'document_id' => $documentId,
                    'warehouse_item_id' => $line['warehouse_item_id'],
                    'related_line_id' => $line['related_line_id'],
                    'original_name' => $line['original_name'],
                    'canonical_name' => $line['canonical_name'],
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit'],
                    'unit_net' => $line['unit_net'],
                    'unit_gross' => $line['unit_gross'],
                    'line_net' => $line['line_net'],
                    'line_gross' => $line['line_gross'],
                    'vat_rate' => $line['vat_rate'],
                    'stock_event_date' => $line['stock_event_date'] ?? $this->nullableDate($header['sale_date'] ?? $header['issue_date'] ?? null),
                    'stock_before_quantity' => null,
                    'stock_after_quantity' => null,
                    'stock_before_value' => null,
                    'stock_after_value' => null,
                    'deducted_value' => null,
                ));
            }

            return $documentId;
        });
    }

    public function saveMacroDefinition(string $name, string $unit, array $aliases = array(), string $itemKind = 'towar'): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('Podaj nazwe pozycji ksiegowej.');
        }

        $unit = trim($unit) !== '' ? trim($unit) : 'szt.';
        $itemKind = $this->normalizeItemKind($itemKind);
        $itemId = $this->findOrCreateItemId($name, $unit, $itemKind);

        $this->database->update(
            self::ITEMS_TABLE,
            array(
                'name' => $name,
                'item_kind' => $itemKind,
                'unit' => $unit,
                'is_active' => 1,
            ),
            'id = :id',
            array('id' => $itemId)
        );

        $remembered = array();
        $remembered[$this->classifier->normalize($name)] = true;

        foreach ($aliases as $alias) {
            $alias = trim((string) $alias);
            $normalized = $this->classifier->normalize($alias);
            if ($alias === '' || $normalized === '' || isset($remembered[$normalized])) {
                continue;
            }

            $this->rememberAlias($alias, $itemId);
            $remembered[$normalized] = true;
        }

        return $itemId;
    }

    public function updateMacroDefinition(int $itemId, string $name, string $unit, array $aliases = array(), string $itemKind = 'towar'): void
    {
        $macro = $this->macroDefinition($itemId);
        if ($macro === null) {
            throw new RuntimeException('Nie znaleziono pozycji ksiegowej do edycji.');
        }

        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('Podaj nazwe pozycji ksiegowej.');
        }

        $unit = trim($unit) !== '' ? trim($unit) : 'szt.';
        $itemKind = $this->normalizeItemKind($itemKind);
        $slug = $this->slugify($name);

        $existing = $this->database->fetch(
            'SELECT id FROM ' . self::ITEMS_TABLE . ' WHERE slug = :slug AND id <> :id LIMIT 1',
            array('slug' => $slug, 'id' => $itemId)
        );
        if ($existing) {
            throw new RuntimeException('Makro o takiej nazwie juz istnieje.');
        }

        $this->database->transaction(function (Database $database) use ($itemId, $name, $unit, $itemKind, $slug, $aliases): void {
            $database->update(
                self::ITEMS_TABLE,
                array(
                    'name' => $name,
                    'slug' => $slug,
                    'item_kind' => $itemKind,
                    'unit' => $unit,
                    'is_active' => 1,
                ),
                'id = :id',
                array('id' => $itemId)
            );

            $database->update(
                self::LINES_TABLE,
                array(
                    'canonical_name' => $name,
                ),
                'warehouse_item_id = :item_id',
                array('item_id' => $itemId)
            );

            $database->delete(
                self::ALIASES_TABLE,
                'warehouse_item_id = :item_id',
                array('item_id' => $itemId)
            );

            $remembered = array();
            $normalizedName = $this->classifier->normalize($name);
            if ($normalizedName !== '') {
                $remembered[$normalizedName] = true;
            }

            foreach ($aliases as $alias) {
                $alias = trim((string) $alias);
                $normalized = $this->classifier->normalize($alias);
                if ($alias === '' || $normalized === '' || isset($remembered[$normalized])) {
                    continue;
                }

                $duplicate = $database->fetch(
                    'SELECT id FROM ' . self::ALIASES_TABLE . ' WHERE normalized_source_name = :normalized LIMIT 1',
                    array('normalized' => $normalized)
                );
                if ($duplicate) {
                    throw new RuntimeException('Alias "' . $alias . '" jest juz przypisany do innej pozycji ksiegowej.');
                }

                $database->insert(self::ALIASES_TABLE, array(
                    'warehouse_item_id' => $itemId,
                    'source_name' => $alias,
                    'normalized_source_name' => $normalized,
                ));
                $remembered[$normalized] = true;
            }
        });
    }

    private function findOrCreateItemId(string $name, string $unit, string $itemKind = 'towar'): int
    {
        $slug = $this->slugify($name);
        $existing = $this->database->fetch(
            'SELECT id FROM ' . self::ITEMS_TABLE . ' WHERE slug = :slug LIMIT 1',
            array('slug' => $slug)
        );
        if ($existing) {
            return (int) $existing['id'];
        }

        return (int) $this->database->insert(self::ITEMS_TABLE, array(
            'name' => $name,
            'slug' => $slug,
            'item_kind' => $this->normalizeItemKind($itemKind),
            'unit' => $unit,
            'is_active' => 1,
        ));
    }

    private function macroAliasesForItem(int $itemId, bool $includeMetadata = false): array
    {
        $macro = $this->database->fetch(
            'SELECT name FROM ' . self::ITEMS_TABLE . ' WHERE id = :id LIMIT 1',
            array('id' => $itemId)
        );
        $normalizedOwnName = $this->classifier->normalize((string) ($macro['name'] ?? ''));

        $aliases = $this->database->fetchAll(
            'SELECT id, source_name, normalized_source_name, created_at, updated_at'
            . ' FROM ' . self::ALIASES_TABLE
            . ' WHERE warehouse_item_id = :item_id'
            . ' ORDER BY source_name ASC',
            array('item_id' => $itemId)
        );

        $result = array();
        foreach ($aliases as $alias) {
            $normalized = trim((string) ($alias['normalized_source_name'] ?? ''));
            if ($normalized !== '' && $normalized === $normalizedOwnName) {
                continue;
            }

            if ($includeMetadata) {
                $result[] = $alias;
            } else {
                $sourceName = trim((string) ($alias['source_name'] ?? ''));
                if ($sourceName !== '') {
                    $result[] = $sourceName;
                }
            }
        }

        return $result;
    }

    private function normalizeItemKind(string $itemKind): string
    {
        $itemKind = strtolower(trim($itemKind));
        if (!in_array($itemKind, array('towar', 'koszt', 'korekta'), true)) {
            return 'towar';
        }

        return $itemKind;
    }

    private function invoiceTypeForDocumentKind(string $documentKind): string
    {
        switch (trim($documentKind)) {
            case 'koszt':
                return 'koszt';
            case 'adjustment':
                return 'korekta';
            default:
                return 'towar';
        }
    }

    private function resolveAffectsStock(array $header): bool
    {
        if (!array_key_exists('affects_stock', $header)) {
            return true;
        }

        $value = $header['affects_stock'];
        if (is_string($value)) {
            return !in_array(strtolower(trim($value)), array('0', 'false', ''), true);
        }

        return (bool) $value;
    }

    private function rememberAlias(string $sourceName, int $itemId): void
    {
        $normalized = $this->classifier->normalize($sourceName);
        if ($normalized === '') {
            return;
        }

        $existing = $this->database->fetch(
            'SELECT id FROM ' . self::ALIASES_TABLE . ' WHERE normalized_source_name = :normalized LIMIT 1',
            array('normalized' => $normalized)
        );

        if ($existing) {
            $this->database->update(
                self::ALIASES_TABLE,
                array(
                    'warehouse_item_id' => $itemId,
                    'source_name' => $sourceName,
                ),
                'id = :id',
                array('id' => (int) $existing['id'])
            );
            return;
        }

        $this->database->insert(self::ALIASES_TABLE, array(
            'warehouse_item_id' => $itemId,
            'source_name' => $sourceName,
            'normalized_source_name' => $normalized,
        ));
    }

    private function slugify(string $value): string
    {
        $value = $this->classifier->normalize($value);
        $value = str_replace(' ', '-', $value);
        $value = trim($value, '-');

        return $value !== '' ? $value : 'pozycja-' . substr(hash('sha1', microtime(true) . $value), 0, 8);
    }

    private function toDecimal($value): float
    {
        $normalized = trim(str_replace(',', '.', (string) $value));
        if ($normalized === '') {
            return 0.0;
        }

        return (float) $normalized;
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function nullableDate($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $value) === 1 ? $value : null;
    }

    private function normalizeBackupRows($rows, array $columns): array
    {
        if (!is_array($rows)) {
            throw new RuntimeException('Plik kopii ma niepoprawny format danych.');
        }

        $normalizedRows = array();
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                throw new RuntimeException('Plik kopii ma niepoprawny wiersz #' . ($index + 1) . '.');
            }

            $normalized = array();
            foreach ($columns as $column) {
                $normalized[$column] = array_key_exists($column, $row) ? $row[$column] : null;
            }

            $normalizedRows[] = $normalized;
        }

        return $normalizedRows;
    }

    private function resetAutoIncrement(string $table, array $rows): void
    {
        $config = Config::get('database');
        if (((string) ($config['driver'] ?? 'mysql')) !== 'mysql') {
            return;
        }

        $maxId = 0;
        foreach ($rows as $row) {
            $maxId = max($maxId, (int) ($row['id'] ?? 0));
        }

        $nextId = max(1, $maxId + 1);
        $this->database->query('ALTER TABLE ' . $table . ' AUTO_INCREMENT = ' . $nextId);
    }

    public function deleteDocument(int $documentId): void
    {
        $document = $this->documentWithLines($documentId);
        if ($document === null) {
            throw new RuntimeException('Nie znaleziono dokumentu do usuniecia.');
        }

        $this->database->transaction(function (Database $database) use ($documentId): void {
            $database->delete(self::LINES_TABLE, 'document_id = :document_id', array('document_id' => $documentId));
            $database->delete(self::DOCUMENTS_TABLE, 'id = :id', array('id' => $documentId));
        });
    }

    public function deleteDocuments(array $documentIds): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $documentIds), static function (int $id): bool {
            return $id > 0;
        })));

        if ($ids === array()) {
            return 0;
        }

        return (int) $this->database->transaction(function (Database $database) use ($ids): int {
            $deleted = 0;
            foreach ($ids as $documentId) {
                $database->delete(self::LINES_TABLE, 'document_id = :document_id', array('document_id' => $documentId));
                $deleted += $database->delete(self::DOCUMENTS_TABLE, 'id = :id', array('id' => $documentId));
            }

            return $deleted;
        });
    }

    private function ensureDocumentsColumn(string $columnName, string $ddl): bool
    {
        $exists = (int) $this->database->fetchColumn(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name',
            array('table_name' => self::DOCUMENTS_TABLE, 'column_name' => $columnName)
        );

        if ($exists <= 0) {
            $this->database->query($ddl);
            return true;
        }

        return false;
    }

    private function ensureItemsColumn(string $columnName, string $ddl): void
    {
        $exists = (int) $this->database->fetchColumn(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name',
            array('table_name' => self::ITEMS_TABLE, 'column_name' => $columnName)
        );

        if ($exists <= 0) {
            $this->database->query($ddl);
        }
    }

    private function ensureLinesColumn(string $columnName, string $ddl): void
    {
        $exists = (int) $this->database->fetchColumn(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name',
            array('table_name' => self::LINES_TABLE, 'column_name' => $columnName)
        );

        if ($exists <= 0) {
            $this->database->query($ddl);
        }
    }
}
