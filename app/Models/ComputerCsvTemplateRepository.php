<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;
use RuntimeException;

class ComputerCsvTemplateRepository
{
    /** @var Database */
    private $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function ensureSchema(): void
    {
        $config = Config::get('database');
        if (!isset($config['driver']) || (string) $config['driver'] !== 'mysql') {
            return;
        }

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS computer_csv_templates (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "slug VARCHAR(80) NOT NULL,\n"
            . "name VARCHAR(190) NOT NULL,\n"
            . "description TEXT DEFAULT NULL,\n"
            . "filename_prefix VARCHAR(120) NOT NULL DEFAULT 'computers_export',\n"
            . "delimiter VARCHAR(1) NOT NULL DEFAULT ';',\n"
            . "encoding VARCHAR(30) NOT NULL DEFAULT 'UTF-8',\n"
            . "add_bom TINYINT(1) NOT NULL DEFAULT 1,\n"
            . "is_active TINYINT(1) NOT NULL DEFAULT 1,\n"
            . "description_template LONGTEXT DEFAULT NULL,\n"
            . "columns_json LONGTEXT NOT NULL,\n"
            . "is_system TINYINT(1) NOT NULL DEFAULT 0,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_computer_csv_templates_slug (slug),\n"
            . "KEY idx_computer_csv_templates_name (name)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $hasDescriptionTemplate = (int) $this->database->fetchColumn(
            'SELECT COUNT(*) FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column',
            array(
                'table' => 'computer_csv_templates',
                'column' => 'description_template',
            )
        );
        if ($hasDescriptionTemplate === 0) {
            $this->database->query(
                'ALTER TABLE computer_csv_templates ADD COLUMN description_template LONGTEXT DEFAULT NULL AFTER add_bom'
            );
        }

        $hasIsActive = (int) $this->database->fetchColumn(
            'SELECT COUNT(*) FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column',
            array(
                'table' => 'computer_csv_templates',
                'column' => 'is_active',
            )
        );
        if ($hasIsActive === 0) {
            $this->database->query(
                'ALTER TABLE computer_csv_templates ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER add_bom'
            );
        }
    }

    public function seed(array $templates): void
    {
        foreach ($templates as $template) {
            $slug = trim((string) ($template['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $exists = (int) $this->database->fetchColumn(
                'SELECT COUNT(*) FROM computer_csv_templates WHERE slug = :slug',
                array('slug' => $slug)
            );
            if ($exists > 0) {
                continue;
            }

            $this->database->insert('computer_csv_templates', array(
                'slug' => $slug,
                'name' => (string) ($template['name'] ?? $slug),
                'description' => (string) ($template['description'] ?? ''),
                'filename_prefix' => (string) ($template['filename_prefix'] ?? $slug . '_export'),
                'delimiter' => (string) ($template['delimiter'] ?? ';'),
                'encoding' => (string) ($template['encoding'] ?? 'UTF-8'),
                'add_bom' => !empty($template['add_bom']) ? 1 : 0,
                'is_active' => array_key_exists('is_active', $template) ? (!empty($template['is_active']) ? 1 : 0) : 1,
                'description_template' => (string) ($template['description_template'] ?? ''),
                'columns_json' => json_encode($template['columns'] ?? array(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'is_system' => 1,
            ));
        }
    }

    public function fillEmptyDescriptionTemplates(string $descriptionTemplate): void
    {
        if (trim($descriptionTemplate) === '') {
            return;
        }

        $this->database->query(
            'UPDATE computer_csv_templates SET description_template = :description_template'
            . ' WHERE description_template IS NULL OR description_template = :empty_description_template',
            array(
                'description_template' => $descriptionTemplate,
                'empty_description_template' => '',
            )
        );
    }

    public function all(): array
    {
        $rows = $this->database->fetchAll(
            'SELECT * FROM computer_csv_templates ORDER BY is_active DESC, is_system DESC, name ASC, id ASC'
        );

        return $this->hydrateRows($rows);
    }

    public function active(): array
    {
        $rows = $this->database->fetchAll(
            'SELECT * FROM computer_csv_templates WHERE is_active = 1 ORDER BY is_system DESC, name ASC, id ASC'
        );

        return $this->hydrateRows($rows);
    }

    public function setActive(int $id, bool $isActive): void
    {
        if (!$this->find($id)) {
            throw new RuntimeException('Nie znaleziono szablonu CSV komputerow.');
        }

        $this->database->update(
            'computer_csv_templates',
            array(
                'is_active' => $isActive ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ),
            'id = :id',
            array('id' => $id)
        );
    }

    public function find(int $id)
    {
        $row = $this->database->fetch(
            'SELECT * FROM computer_csv_templates WHERE id = :id LIMIT 1',
            array('id' => $id)
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function save(int $id, array $data, array $columns): void
    {
        if (!$this->find($id)) {
            throw new RuntimeException('Nie znaleziono szablonu CSV komputerow.');
        }

        $this->database->update(
            'computer_csv_templates',
            array(
                'name' => (string) $data['name'],
                'description' => (string) $data['description'],
                'filename_prefix' => (string) $data['filename_prefix'],
                'delimiter' => (string) $data['delimiter'],
                'encoding' => (string) $data['encoding'],
                'add_bom' => !empty($data['add_bom']) ? 1 : 0,
                'is_active' => !empty($data['is_active']) ? 1 : 0,
                'description_template' => (string) ($data['description_template'] ?? ''),
                'columns_json' => json_encode($columns, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => date('Y-m-d H:i:s'),
            ),
            'id = :id',
            array('id' => $id)
        );
    }

    public function duplicate(int $id): int
    {
        $template = $this->find($id);
        if (!$template) {
            throw new RuntimeException('Nie znaleziono szablonu do duplikacji.');
        }

        $slug = trim((string) $template['slug']) . '-copy-' . str_replace('.', '', uniqid('', true));
        return (int) $this->database->insert('computer_csv_templates', array(
            'slug' => $slug,
            'name' => trim((string) $template['name']) . ' (kopia)',
            'description' => (string) ($template['description'] ?? ''),
            'filename_prefix' => (string) ($template['filename_prefix'] ?? 'computers_export'),
            'delimiter' => (string) ($template['delimiter'] ?? ';'),
            'encoding' => (string) ($template['encoding'] ?? 'UTF-8'),
            'add_bom' => (int) ($template['add_bom'] ?? 1),
            'is_active' => (int) ($template['is_active'] ?? 1),
            'description_template' => (string) ($template['description_template'] ?? ''),
            'columns_json' => json_encode($template['columns'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_system' => 0,
        ));
    }

    public function delete(int $id): void
    {
        $template = $this->find($id);
        if (!$template) {
            return;
        }
        if (!empty($template['is_system'])) {
            throw new RuntimeException('Gotowego szablonu systemowego nie mozna usunac. Mozesz go edytowac lub zduplikowac.');
        }

        $this->database->delete('computer_csv_templates', 'id = :id', array('id' => $id));
    }

    private function hydrate(array $row): array
    {
        $columns = json_decode((string) ($row['columns_json'] ?? ''), true);
        $row['columns'] = is_array($columns) ? $columns : array();
        $row['is_active'] = (int) ($row['is_active'] ?? 1);
        return $row;
    }

    private function hydrateRows(array $rows): array
    {
        foreach ($rows as $index => $row) {
            $rows[$index] = $this->hydrate($row);
            $rows[$index]['columns_count'] = count($rows[$index]['columns']);
        }

        return $rows;
    }
}
