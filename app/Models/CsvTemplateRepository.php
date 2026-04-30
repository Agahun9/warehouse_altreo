<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;
use RuntimeException;

class CsvTemplateRepository
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
            "CREATE TABLE IF NOT EXISTS csv_templates (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "name VARCHAR(190) NOT NULL,\n"
            . "description TEXT DEFAULT NULL,\n"
            . "delimiter VARCHAR(1) NOT NULL DEFAULT ';',\n"
            . "encoding VARCHAR(30) NOT NULL DEFAULT 'UTF-8',\n"
            . "add_bom TINYINT(1) NOT NULL DEFAULT 1,\n"
            . "array_separator VARCHAR(10) NOT NULL DEFAULT '|',\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_csv_templates_name (name)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS csv_template_columns (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "template_id INT UNSIGNED NOT NULL,\n"
            . "header_name VARCHAR(190) NOT NULL,\n"
            . "source_type VARCHAR(20) NOT NULL DEFAULT 'field',\n"
            . "source_value TEXT DEFAULT NULL,\n"
            . "settings_json JSON DEFAULT NULL,\n"
            . "sort_order INT UNSIGNED NOT NULL DEFAULT 0,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "KEY idx_csv_template_columns_template (template_id),\n"
            . "CONSTRAINT fk_csv_template_columns_template FOREIGN KEY (template_id) REFERENCES csv_templates(id) ON DELETE CASCADE\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS csv_template_mappings (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "column_id INT UNSIGNED NOT NULL,\n"
            . "from_value VARCHAR(190) NOT NULL,\n"
            . "to_value VARCHAR(190) NOT NULL,\n"
            . "PRIMARY KEY (id),\n"
            . "KEY idx_csv_template_mappings_column (column_id),\n"
            . "CONSTRAINT fk_csv_template_mappings_column FOREIGN KEY (column_id) REFERENCES csv_template_columns(id) ON DELETE CASCADE\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::$schemaEnsured = true;
    }

    public function all(): array
    {
        return $this->database->fetchAll(
            'SELECT t.*, COUNT(c.id) AS columns_count'
            . ' FROM csv_templates t'
            . ' LEFT JOIN csv_template_columns c ON c.template_id = t.id'
            . ' GROUP BY t.id'
            . ' ORDER BY t.updated_at DESC, t.id DESC'
        );
    }

    public function allForSelect(): array
    {
        return $this->database->fetchAll('SELECT id, name FROM csv_templates ORDER BY name ASC');
    }

    public function findById(int $id)
    {
        return $this->database->fetch('SELECT * FROM csv_templates WHERE id = :id LIMIT 1', array('id' => $id));
    }

    public function findFullById(int $id)
    {
        $template = $this->findById($id);
        if (!$template) {
            return null;
        }

        $columns = $this->database->fetchAll(
            'SELECT * FROM csv_template_columns WHERE template_id = :template_id ORDER BY sort_order ASC, id ASC',
            array('template_id' => $id)
        );

        foreach ($columns as $index => $column) {
            $settings = array();
            if (!empty($column['settings_json'])) {
                $decoded = json_decode((string) $column['settings_json'], true);
                if (is_array($decoded)) {
                    $settings = $decoded;
                }
            }

            $mappings = $this->database->fetchAll(
                'SELECT from_value, to_value FROM csv_template_mappings WHERE column_id = :column_id ORDER BY id ASC',
                array('column_id' => (int) $column['id'])
            );

            $columns[$index]['settings'] = $settings;
            $columns[$index]['mappings'] = $mappings;
        }

        $template['columns'] = $columns;
        return $template;
    }

    public function create(array $templateData, array $columns): int
    {
        return (int) $this->database->transaction(function () use ($templateData, $columns) {
            $templateId = (int) $this->database->insert('csv_templates', $templateData);
            $this->saveColumns($templateId, $columns);
            return $templateId;
        });
    }

    public function update(int $id, array $templateData, array $columns): void
    {
        $this->database->transaction(function () use ($id, $templateData, $columns) {
            $templateData['updated_at'] = date('Y-m-d H:i:s');
            $this->database->update('csv_templates', $templateData, 'id = :id', array('id' => $id));
            $this->database->delete('csv_template_columns', 'template_id = :template_id', array('template_id' => $id));
            $this->saveColumns($id, $columns);
        });
    }

    public function delete(int $id): void
    {
        $this->database->delete('csv_templates', 'id = :id', array('id' => $id));
    }

    public function duplicate(int $id): int
    {
        $template = $this->findFullById($id);
        if (!$template) {
            throw new RuntimeException('Nie znaleziono szablonu do duplikacji.');
        }

        $baseName = trim((string) $template['name']) . ' (kopia)';
        $name = $baseName;
        $suffix = 2;

        while ($this->existsByName($name)) {
            $name = $baseName . ' ' . $suffix;
            $suffix++;
        }

        $columns = array();
        foreach ($template['columns'] as $column) {
            $columns[] = array(
                'header_name' => (string) $column['header_name'],
                'source_type' => (string) $column['source_type'],
                'source_value' => (string) $column['source_value'],
                'settings' => isset($column['settings']) && is_array($column['settings']) ? $column['settings'] : array(),
                'mappings' => isset($column['mappings']) && is_array($column['mappings']) ? $column['mappings'] : array(),
            );
        }

        return $this->create(
            array(
                'name' => $name,
                'description' => isset($template['description']) ? (string) $template['description'] : null,
                'delimiter' => isset($template['delimiter']) ? (string) $template['delimiter'] : ';',
                'encoding' => isset($template['encoding']) ? (string) $template['encoding'] : 'UTF-8',
                'add_bom' => isset($template['add_bom']) ? (int) $template['add_bom'] : 1,
                'array_separator' => isset($template['array_separator']) ? (string) $template['array_separator'] : '|',
            ),
            $columns
        );
    }

    public function existsByName(string $name, int $ignoreId = 0): bool
    {
        $sql = 'SELECT COUNT(*) FROM csv_templates WHERE name = :name';
        $params = array('name' => $name);

        if ($ignoreId > 0) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }

        return (int) $this->database->fetchColumn($sql, $params) > 0;
    }

    private function saveColumns(int $templateId, array $columns): void
    {
        foreach ($columns as $index => $column) {
            $columnId = (int) $this->database->insert('csv_template_columns', array(
                'template_id' => $templateId,
                'header_name' => (string) $column['header_name'],
                'source_type' => (string) $column['source_type'],
                'source_value' => isset($column['source_value']) ? (string) $column['source_value'] : '',
                'settings_json' => json_encode(isset($column['settings']) && is_array($column['settings']) ? $column['settings'] : array()),
                'sort_order' => $index + 1,
            ));

            $mappings = isset($column['mappings']) && is_array($column['mappings']) ? $column['mappings'] : array();
            foreach ($mappings as $mapping) {
                $from = isset($mapping['from_value']) ? trim((string) $mapping['from_value']) : '';
                $to = isset($mapping['to_value']) ? (string) $mapping['to_value'] : '';

                if ($from === '') {
                    continue;
                }

                $this->database->insert('csv_template_mappings', array(
                    'column_id' => $columnId,
                    'from_value' => $from,
                    'to_value' => $to,
                ));
            }
        }
    }
}
