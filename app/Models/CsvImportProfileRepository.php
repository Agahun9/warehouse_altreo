<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;

class CsvImportProfileRepository
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
            "CREATE TABLE IF NOT EXISTS csv_import_profiles (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "name VARCHAR(190) NOT NULL,\n"
            . "source_context VARCHAR(50) DEFAULT NULL,\n"
            . "import_mode VARCHAR(20) NOT NULL DEFAULT 'create',\n"
            . "delimiter VARCHAR(10) NOT NULL DEFAULT 'auto',\n"
            . "encoding VARCHAR(30) NOT NULL DEFAULT 'UTF-8',\n"
            . "has_header TINYINT(1) NOT NULL DEFAULT 1,\n"
            . "target_category_id INT UNSIGNED NOT NULL DEFAULT 0,\n"
            . "update_identifier_column VARCHAR(10) DEFAULT NULL,\n"
            . "derived_link_columns_json LONGTEXT DEFAULT NULL,\n"
            . "derived_link_old_sku_columns_json LONGTEXT DEFAULT NULL,\n"
            . "derived_link_old_sku_match_column VARCHAR(10) DEFAULT NULL,\n"
            . "mapping_json LONGTEXT DEFAULT NULL,\n"
            . "column_transforms_json LONGTEXT DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_csv_import_profiles_name (name),\n"
            . "KEY idx_csv_import_profiles_source (source_context)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::$schemaEnsured = true;
    }

    public function allForSelect(string $sourceContext = ''): array
    {
        $sourceContext = trim($sourceContext);
        if ($sourceContext === '') {
            return $this->database->fetchAll(
                'SELECT id, name, source_context, import_mode FROM csv_import_profiles ORDER BY name ASC'
            );
        }

        return $this->database->fetchAll(
            'SELECT id, name, source_context, import_mode'
            . ' FROM csv_import_profiles'
            . ' WHERE source_context IS NULL OR source_context = :source_context OR source_context = \'\''
            . ' ORDER BY name ASC',
            array('source_context' => $sourceContext)
        );
    }

    public function findById(int $id)
    {
        if ($id <= 0) {
            return null;
        }

        return $this->decodeRow(
            $this->database->fetch(
                'SELECT * FROM csv_import_profiles WHERE id = :id LIMIT 1',
                array('id' => $id)
            )
        );
    }

    public function findByName(string $name)
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        return $this->decodeRow(
            $this->database->fetch(
                'SELECT * FROM csv_import_profiles WHERE name = :name LIMIT 1',
                array('name' => $name)
            )
        );
    }

    public function save(int $id, string $name, array $payload): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new \RuntimeException('Podaj nazwe profilu importu.');
        }

        $data = array(
            'name' => $name,
            'source_context' => $this->normalizeNullableString($payload['source_context'] ?? null),
            'import_mode' => (string) ($payload['import_mode'] ?? 'create'),
            'delimiter' => (string) ($payload['delimiter'] ?? 'auto'),
            'encoding' => (string) ($payload['encoding'] ?? 'UTF-8'),
            'has_header' => !empty($payload['has_header']) ? 1 : 0,
            'target_category_id' => max(0, (int) ($payload['target_category_id'] ?? 0)),
            'update_identifier_column' => $this->normalizeNullableString($payload['update_identifier_column'] ?? null),
            'derived_link_columns_json' => json_encode(isset($payload['derived_link_columns']) && is_array($payload['derived_link_columns']) ? array_values($payload['derived_link_columns']) : array()),
            'derived_link_old_sku_columns_json' => json_encode(isset($payload['derived_link_old_sku_columns']) && is_array($payload['derived_link_old_sku_columns']) ? array_values($payload['derived_link_old_sku_columns']) : array()),
            'derived_link_old_sku_match_column' => $this->normalizeNullableString($payload['derived_link_old_sku_match_column'] ?? null),
            'mapping_json' => json_encode(isset($payload['mapping']) && is_array($payload['mapping']) ? $payload['mapping'] : array()),
            'column_transforms_json' => json_encode(isset($payload['column_transforms']) && is_array($payload['column_transforms']) ? $payload['column_transforms'] : array()),
        );

        if ($id > 0) {
            $this->database->update('csv_import_profiles', $data, 'id = :id', array('id' => $id));
            return $id;
        }

        $existing = $this->findByName($name);
        if ($existing) {
            $this->database->update('csv_import_profiles', $data, 'id = :id', array('id' => (int) $existing['id']));
            return (int) $existing['id'];
        }

        return (int) $this->database->insert('csv_import_profiles', $data);
    }

    private function decodeRow($row)
    {
        if (!$row || !is_array($row)) {
            return null;
        }

        $row['derived_link_columns'] = $this->decodeJsonArray($row['derived_link_columns_json'] ?? null);
        $row['derived_link_old_sku_columns'] = $this->decodeJsonArray($row['derived_link_old_sku_columns_json'] ?? null);
        $row['mapping'] = $this->decodeJsonArray($row['mapping_json'] ?? null);
        $row['column_transforms'] = $this->decodeJsonArray($row['column_transforms_json'] ?? null);

        return $row;
    }

    private function decodeJsonArray($value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return array();
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : array();
    }

    private function normalizeNullableString($value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }
}
