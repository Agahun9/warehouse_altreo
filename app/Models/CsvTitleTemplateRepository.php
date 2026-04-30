<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;

class CsvTitleTemplateRepository
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
            "CREATE TABLE IF NOT EXISTS csv_title_templates (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "name VARCHAR(190) NOT NULL,\n"
            . "description TEXT DEFAULT NULL,\n"
            . "template_body TEXT NOT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_csv_title_templates_name (name)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::$schemaEnsured = true;
    }

    public function all(): array
    {
        return $this->database->fetchAll('SELECT * FROM csv_title_templates ORDER BY updated_at DESC, id DESC');
    }

    public function allForSelect(): array
    {
        return $this->database->fetchAll('SELECT id, name, template_body FROM csv_title_templates ORDER BY name ASC');
    }

    public function findById(int $id)
    {
        return $this->database->fetch('SELECT * FROM csv_title_templates WHERE id = :id LIMIT 1', array('id' => $id));
    }

    public function existsByName(string $name, int $ignoreId = 0): bool
    {
        $sql = 'SELECT COUNT(*) FROM csv_title_templates WHERE name = :name';
        $params = array('name' => $name);

        if ($ignoreId > 0) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }

        return (int) $this->database->fetchColumn($sql, $params) > 0;
    }

    public function create(array $data): int
    {
        return (int) $this->database->insert('csv_title_templates', $data);
    }

    public function update(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->database->update('csv_title_templates', $data, 'id = :id', array('id' => $id));
    }

    public function delete(int $id): void
    {
        $this->database->delete('csv_title_templates', 'id = :id', array('id' => $id));
    }
}
