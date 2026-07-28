<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class AltreoSqlImportService
{
    private const PRODUCTS_TABLE = 'pr_products_altreo';
    private const COMPONENTS_TABLE = 'pr_components_altreo';
    private const TEMPLATES_TABLE = 'pr_altreo_template';

    /** @var Database */
    private $database;

    /** @var array<string, string> */
    private $primaryKeys = array(
        self::PRODUCTS_TABLE => 'id',
        self::COMPONENTS_TABLE => 'id',
        self::TEMPLATES_TABLE => 'id_template',
    );

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function ensureSchema(): void
    {
        $db = $this->database;

        $db->query(
            "CREATE TABLE IF NOT EXISTS " . self::PRODUCTS_TABLE . " (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "id_components VARCHAR(255) NOT NULL DEFAULT '',\n"
            . "sku VARCHAR(190) DEFAULT NULL,\n"
            . "name VARCHAR(255) NOT NULL,\n"
            . "price DECIMAL(12,2) NOT NULL DEFAULT 0.00,\n"
            . "price_allegro DECIMAL(12,2) DEFAULT NULL,\n"
            . "profit DECIMAL(12,2) NOT NULL DEFAULT 0.00,\n"
            . "EAN VARCHAR(64) DEFAULT NULL,\n"
            . "img TEXT DEFAULT NULL,\n"
            . "img_morele TEXT DEFAULT NULL,\n"
            . "img_empik TEXT DEFAULT NULL,\n"
            . "offerid VARCHAR(64) DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "KEY idx_products_altreo_sku (sku),\n"
            . "KEY idx_products_altreo_offerid (offerid),\n"
            . "KEY idx_products_altreo_name (name)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $db->query(
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

        $db->query(
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

        $this->ensureColumn(self::PRODUCTS_TABLE, 'id_components', "ALTER TABLE " . self::PRODUCTS_TABLE . " ADD COLUMN id_components VARCHAR(255) NOT NULL DEFAULT '' AFTER id");
        $this->ensureColumn(self::PRODUCTS_TABLE, 'sku', "ALTER TABLE " . self::PRODUCTS_TABLE . " ADD COLUMN sku VARCHAR(190) DEFAULT NULL AFTER id_components");
        $this->ensureColumn(self::PRODUCTS_TABLE, 'name', "ALTER TABLE " . self::PRODUCTS_TABLE . " ADD COLUMN name VARCHAR(255) NOT NULL AFTER sku");
        $this->ensureColumn(self::PRODUCTS_TABLE, 'price', "ALTER TABLE " . self::PRODUCTS_TABLE . " ADD COLUMN price DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER name");
        $this->ensureColumn(self::PRODUCTS_TABLE, 'price_allegro', "ALTER TABLE " . self::PRODUCTS_TABLE . " ADD COLUMN price_allegro DECIMAL(12,2) DEFAULT NULL AFTER price");
        $this->ensureColumn(self::PRODUCTS_TABLE, 'profit', "ALTER TABLE " . self::PRODUCTS_TABLE . " ADD COLUMN profit DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER price_allegro");
        $this->ensureColumn(self::PRODUCTS_TABLE, 'EAN', "ALTER TABLE " . self::PRODUCTS_TABLE . " ADD COLUMN EAN VARCHAR(64) DEFAULT NULL AFTER profit");
        $this->ensureColumn(self::PRODUCTS_TABLE, 'img', "ALTER TABLE " . self::PRODUCTS_TABLE . " ADD COLUMN img TEXT DEFAULT NULL AFTER EAN");
        $this->ensureColumn(self::PRODUCTS_TABLE, 'img_morele', "ALTER TABLE " . self::PRODUCTS_TABLE . " ADD COLUMN img_morele TEXT DEFAULT NULL AFTER img");
        $this->ensureColumn(self::PRODUCTS_TABLE, 'img_empik', "ALTER TABLE " . self::PRODUCTS_TABLE . " ADD COLUMN img_empik TEXT DEFAULT NULL AFTER img_morele");
        $this->ensureColumn(self::PRODUCTS_TABLE, 'offerid', "ALTER TABLE " . self::PRODUCTS_TABLE . " ADD COLUMN offerid VARCHAR(64) DEFAULT NULL AFTER img_empik");
        $this->ensureColumnType(self::PRODUCTS_TABLE, 'img', 'text', "ALTER TABLE " . self::PRODUCTS_TABLE . " MODIFY COLUMN img TEXT DEFAULT NULL");
        $this->ensureColumnType(self::PRODUCTS_TABLE, 'img_morele', 'text', "ALTER TABLE " . self::PRODUCTS_TABLE . " MODIFY COLUMN img_morele TEXT DEFAULT NULL");
        $this->ensureColumnType(self::PRODUCTS_TABLE, 'img_empik', 'text', "ALTER TABLE " . self::PRODUCTS_TABLE . " MODIFY COLUMN img_empik TEXT DEFAULT NULL");
        $this->ensureColumn(self::PRODUCTS_TABLE, 'created_at', "ALTER TABLE " . self::PRODUCTS_TABLE . " ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER offerid");
        $this->ensureColumn(self::PRODUCTS_TABLE, 'updated_at', "ALTER TABLE " . self::PRODUCTS_TABLE . " ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");

        $this->ensureColumn(self::COMPONENTS_TABLE, 'name', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN name VARCHAR(255) NOT NULL AFTER id");
        $this->ensureColumn(self::COMPONENTS_TABLE, 'name_title', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN name_title VARCHAR(255) DEFAULT NULL AFTER name");
        $this->ensureColumn(self::COMPONENTS_TABLE, 'name_spec', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN name_spec VARCHAR(255) DEFAULT NULL AFTER name_title");
        $this->ensureColumn(self::COMPONENTS_TABLE, 'price', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN price DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER name_spec");
        $this->ensureColumn(self::COMPONENTS_TABLE, 'description', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN description MEDIUMTEXT DEFAULT NULL AFTER price");
        $this->ensureColumn(self::COMPONENTS_TABLE, 'description_morele', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN description_morele MEDIUMTEXT DEFAULT NULL AFTER description");
        $this->ensureColumn(self::COMPONENTS_TABLE, 'description_empik', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN description_empik MEDIUMTEXT DEFAULT NULL AFTER description_morele");
        $this->ensureColumn(self::COMPONENTS_TABLE, 'parameters_eu', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN parameters_eu LONGTEXT DEFAULT NULL AFTER description_empik");
        $this->ensureColumn(self::COMPONENTS_TABLE, 'parameters_morele', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN parameters_morele LONGTEXT DEFAULT NULL AFTER parameters_eu");
        $this->ensureColumn(self::COMPONENTS_TABLE, 'parameters_empik', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN parameters_empik LONGTEXT DEFAULT NULL AFTER parameters_morele");
        $this->ensureColumn(self::COMPONENTS_TABLE, 'img', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN img TEXT DEFAULT NULL AFTER parameters_empik");
        $this->ensureColumn(self::COMPONENTS_TABLE, 'img_morele', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN img_morele TEXT DEFAULT NULL AFTER img");
        $this->ensureColumn(self::COMPONENTS_TABLE, 'img_empik', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN img_empik TEXT DEFAULT NULL AFTER img_morele");
        $this->ensureColumn(self::COMPONENTS_TABLE, 'category', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN category VARCHAR(120) DEFAULT NULL AFTER img_empik");
        $this->ensureColumn(self::COMPONENTS_TABLE, 'created_at', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER category");
        $this->ensureColumn(self::COMPONENTS_TABLE, 'updated_at', "ALTER TABLE " . self::COMPONENTS_TABLE . " ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");

        $this->ensureColumn(self::TEMPLATES_TABLE, 'name', "ALTER TABLE " . self::TEMPLATES_TABLE . " ADD COLUMN name VARCHAR(120) NOT NULL AFTER id_template");
        $this->ensureColumn(self::TEMPLATES_TABLE, 'template', "ALTER TABLE " . self::TEMPLATES_TABLE . " ADD COLUMN template LONGTEXT DEFAULT NULL AFTER name");
        $this->ensureColumn(self::TEMPLATES_TABLE, 'created_at', "ALTER TABLE " . self::TEMPLATES_TABLE . " ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER template");
        $this->ensureColumn(self::TEMPLATES_TABLE, 'updated_at', "ALTER TABLE " . self::TEMPLATES_TABLE . " ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
    }

    /**
     * @param array<int, string> $paths
     * @return array<string, int>
     */
    public function importFiles(array $paths, bool $clearBeforeImport = false): array
    {
        if ($paths === array()) {
            throw new RuntimeException('Wybierz przynajmniej jeden plik SQL do importu.');
        }

        $this->ensureSchema();

        $summary = array(
            self::PRODUCTS_TABLE => 0,
            self::COMPONENTS_TABLE => 0,
            self::TEMPLATES_TABLE => 0,
            'ignored_columns' => 0,
        );

        $this->database->transaction(function () use ($paths, $clearBeforeImport, &$summary): void {
            if ($clearBeforeImport) {
                $this->database->query('DELETE FROM ' . self::PRODUCTS_TABLE);
                $this->database->query('DELETE FROM ' . self::COMPONENTS_TABLE);
                $this->database->query('DELETE FROM ' . self::TEMPLATES_TABLE);
            }

            foreach ($paths as $path) {
                $content = file_get_contents($path);
                if ($content === false || trim($content) === '') {
                    throw new RuntimeException('Nie udalo sie odczytac pliku SQL.');
                }

                foreach ($this->splitSqlStatements($content) as $statement) {
                    $result = $this->importInsertStatement($statement);
                    foreach ($result as $table => $count) {
                        $summary[$table] = (int) ($summary[$table] ?? 0) + $count;
                    }
                }
            }

        });

        $this->refreshAutoIncrement(self::PRODUCTS_TABLE, 'id');
        $this->refreshAutoIncrement(self::COMPONENTS_TABLE, 'id');
        $this->refreshAutoIncrement(self::TEMPLATES_TABLE, 'id_template');
        $this->backfillMissingProductSkus();

        return $summary;
    }

    /**
     * Imported rows without their own sku must get one, otherwise the computers products
     * marketplace matching (ComputersController::computerProductSkuCandidates) has nothing
     * reliable to join on and products end up either unmatched or - worse - coincidentally
     * matching an unrelated listing on another shop. Convention: id<=1000 keeps the legacy
     * bare-id sku (those already exist on the marketplace under that plain numeric sku),
     * everything else gets 'ALTREO_'+id, which no other shop's sku will ever coincidentally
     * equal. Only fills gaps - never overwrites a sku the import already brought in.
     */
    private function backfillMissingProductSkus(): void
    {
        $this->database->query(
            'UPDATE ' . self::PRODUCTS_TABLE . ' SET sku = CASE'
            . ' WHEN id <= 1000 THEN CAST(id AS CHAR)'
            . " ELSE CONCAT('ALTREO_', id) END"
            . " WHERE sku IS NULL OR sku = '' OR sku = '0'"
        );
    }

    private function ensureColumn(string $table, string $column, string $alterSql): void
    {
        $exists = (int) $this->database->fetchColumn(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name',
            array('table_name' => $table, 'column_name' => $column)
        );

        if ($exists === 0) {
            $this->database->query($alterSql);
        }
    }

    private function ensureColumnType(string $table, string $column, string $expectedDataType, string $alterSql): void
    {
        $currentType = (string) $this->database->fetchColumn(
            'SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name',
            array('table_name' => $table, 'column_name' => $column)
        );

        if ($currentType !== '' && strtolower($currentType) !== strtolower($expectedDataType)) {
            $this->database->query($alterSql);
        }
    }

    /**
     * @return array<int, string>
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = array();
        $buffer = '';
        $quote = null;
        $escaped = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $buffer .= $char;

            if ($quote !== null) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '\'' || $char === '"') {
                $quote = $char;
                continue;
            }

            if ($char === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
            }
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }

    /**
     * @return array<string, int>
     */
    private function importInsertStatement(string $statement): array
    {
        if (preg_match('/INSERT\s+INTO\s+`?([a-zA-Z0-9_]+)`?\s*\((.*?)\)\s+VALUES\s*(.*);?$/is', trim($statement), $matches) !== 1) {
            return array();
        }

        $table = (string) $matches[1];
        if (!isset($this->primaryKeys[$table])) {
            return array();
        }

        $sourceColumns = $this->parseColumnList((string) $matches[2]);
        $targetColumnMap = array_fill_keys($this->tableColumns($table), true);
        $primaryKey = $this->primaryKeys[$table];
        $valuesSql = rtrim(trim((string) $matches[3]), ';');
        $rows = $this->splitValueTuples($valuesSql);
        $imported = 0;
        $ignoredColumns = 0;

        foreach ($rows as $tuple) {
            $values = $this->parseTupleValues($tuple);
            if (count($values) !== count($sourceColumns)) {
                throw new RuntimeException('Niepoprawna liczba wartosci w imporcie tabeli ' . $table . '.');
            }

            $row = array();
            foreach ($sourceColumns as $index => $column) {
                if (!isset($targetColumnMap[$column])) {
                    $ignoredColumns++;
                    continue;
                }
                $row[$column] = $values[$index];
            }

            if (!isset($row[$primaryKey]) || $row[$primaryKey] === null || (string) $row[$primaryKey] === '') {
                throw new RuntimeException('Brak klucza glownego w imporcie tabeli ' . $table . '.');
            }

            $row = $this->sanitizeImportedRow($table, $row);
            $this->upsertRow($table, $primaryKey, $row);
            $imported++;
        }

        return array($table => $imported, 'ignored_columns' => $ignoredColumns);
    }

    /**
     * @return array<int, string>
     */
    private function parseColumnList(string $columnsSql): array
    {
        $columns = array();
        foreach (explode(',', $columnsSql) as $column) {
            $column = trim($column);
            $column = trim($column, "` \t\n\r\0\x0B");
            if ($column !== '') {
                $columns[] = $column;
            }
        }
        return $columns;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function sanitizeImportedRow(string $table, array $row): array
    {
        if ($table === self::PRODUCTS_TABLE) {
            unset($row['offerid']);
        }

        foreach ($row as $column => $value) {
            if (is_string($value)) {
                $row[$column] = trim($value);
            }
        }

        if ($table === self::COMPONENTS_TABLE) {
            foreach (array('parameters_eu', 'parameters_morele', 'parameters_empik') as $column) {
                if (isset($row[$column])) {
                    $row[$column] = $this->sanitizeImportedJsonMap((string) $row[$column]);
                }
            }
        }

        return $row;
    }

    private function sanitizeImportedJsonMap(string $json): string
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

    /**
     * @return array<int, string>
     */
    private function tableColumns(string $table): array
    {
        $rows = $this->database->fetchAll(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name',
            array('table_name' => $table)
        );

        return array_map('strval', array_column($rows, 'COLUMN_NAME'));
    }

    /**
     * @return array<int, string>
     */
    private function splitValueTuples(string $valuesSql): array
    {
        $tuples = array();
        $buffer = '';
        $quote = null;
        $escaped = false;
        $depth = 0;
        $length = strlen($valuesSql);

        for ($i = 0; $i < $length; $i++) {
            $char = $valuesSql[$i];

            if ($quote !== null) {
                $buffer .= $char;
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '\'' || $char === '"') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === '(') {
                $depth++;
                if ($depth > 1) {
                    $buffer .= $char;
                }
                continue;
            }

            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $tuples[] = $buffer;
                    $buffer = '';
                    continue;
                }
                $buffer .= $char;
                continue;
            }

            if ($depth > 0) {
                $buffer .= $char;
            }
        }

        return $tuples;
    }

    /**
     * @return array<int, mixed>
     */
    private function parseTupleValues(string $tuple): array
    {
        $values = array();
        $buffer = '';
        $quote = null;
        $escaped = false;
        $quotedValue = false;
        $length = strlen($tuple);

        for ($i = 0; $i < $length; $i++) {
            $char = $tuple[$i];

            if ($quote !== null) {
                if ($escaped) {
                    $buffer .= $this->unescapeMysqlCharacter($char);
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                    continue;
                }
                $buffer .= $char;
                continue;
            }

            if ($char === '\'' || $char === '"') {
                $quote = $char;
                $quotedValue = true;
                continue;
            }

            if ($char === ',') {
                $values[] = $quotedValue ? $buffer : $this->normalizeSqlValue($buffer);
                $buffer = '';
                $quotedValue = false;
                continue;
            }

            $buffer .= $char;
        }

        $values[] = $quotedValue ? $buffer : $this->normalizeSqlValue($buffer);
        return $values;
    }

    private function unescapeMysqlCharacter(string $char): string
    {
        switch ($char) {
            case '0':
                return "\0";
            case 'n':
                return "\n";
            case 'r':
                return "\r";
            case 't':
                return "\t";
            case 'b':
                return "\x08";
            case 'Z':
                return "\x1a";
            default:
                return $char;
        }
    }

    private function normalizeSqlValue(string $value)
    {
        $trimmed = trim($value);
        if (strcasecmp($trimmed, 'NULL') === 0) {
            return null;
        }

        return $trimmed;
    }

    private function refreshAutoIncrement(string $table, string $primaryKey): void
    {
        $nextId = (int) $this->database->fetchColumn('SELECT COALESCE(MAX(`' . $primaryKey . '`), 0) + 1 FROM `' . $table . '`');
        $this->database->query('ALTER TABLE `' . $table . '` AUTO_INCREMENT = ' . max(1, $nextId));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function upsertRow(string $table, string $primaryKey, array $row): void
    {
        $columns = array_keys($row);
        $placeholders = array_map(static function (string $column): string {
            return ':' . $column;
        }, $columns);
        $updates = array();

        foreach ($columns as $column) {
            if ($column === $primaryKey) {
                continue;
            }
            $updates[] = '`' . $column . '` = VALUES(`' . $column . '`)';
        }

        if (in_array('updated_at', $this->tableColumns($table), true) && !isset($row['updated_at'])) {
            $updates[] = '`updated_at` = CURRENT_TIMESTAMP';
        }

        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', $placeholders) . ')'
            . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);

        $statement = $this->database->pdo()->prepare($sql);
        foreach ($row as $column => $value) {
            $statement->bindValue(':' . $column, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        }
        $statement->execute();
    }
}
