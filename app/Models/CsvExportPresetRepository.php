<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;

class CsvExportPresetRepository
{
    const TABLE = 'csv_export_presets';

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
            "CREATE TABLE IF NOT EXISTS " . self::TABLE . " (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "user_id INT UNSIGNED NOT NULL,\n"
            . "template_id INT UNSIGNED NOT NULL,\n"
            . "title_template_id INT UNSIGNED NOT NULL DEFAULT 0,\n"
            . "collection_name VARCHAR(255) DEFAULT NULL,\n"
            . "image_collection_code VARCHAR(120) DEFAULT NULL,\n"
            . "price_to_csv VARCHAR(120) DEFAULT NULL,\n"
            . "thumbnail_count INT UNSIGNED NOT NULL DEFAULT 0,\n"
            . "mockup_count INT UNSIGNED NOT NULL DEFAULT 0,\n"
            . "image_count INT UNSIGNED NOT NULL DEFAULT 0,\n"
            . "image_base_directory VARCHAR(255) DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "KEY idx_csv_export_presets_user_created (user_id, created_at),\n"
            . "KEY idx_csv_export_presets_template (template_id)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::$schemaEnsured = true;
    }

    public function create(array $data): int
    {
        return (int) $this->database->insert(self::TABLE, $data);
    }

    public function trimForUser(int $userId, int $keep = 10): void
    {
        $keep = max(1, min(10, $keep));

        $this->database->query(
            'DELETE FROM ' . self::TABLE
            . ' WHERE user_id = :user_id'
            . ' AND id NOT IN ('
            . ' SELECT id FROM ('
            . ' SELECT id FROM ' . self::TABLE
            . ' WHERE user_id = :user_id_inner'
            . ' ORDER BY created_at DESC, id DESC'
            . ' LIMIT ' . $keep
            . ' ) AS latest_rows'
            . ' )',
            array(
                'user_id' => $userId,
                'user_id_inner' => $userId,
            )
        );
    }

    public function latestForUser(int $userId, int $limit = 10): array
    {
        $limit = max(1, min(10, $limit));

        return $this->database->fetchAll(
            'SELECT p.*,'
            . ' t.name AS template_name,'
            . ' tt.name AS title_template_name'
            . ' FROM ' . self::TABLE . ' p'
            . ' LEFT JOIN csv_templates t ON t.id = p.template_id'
            . ' LEFT JOIN csv_title_templates tt ON tt.id = p.title_template_id'
            . ' WHERE p.user_id = :user_id'
            . ' ORDER BY p.created_at DESC, p.id DESC'
            . ' LIMIT ' . $limit,
            array('user_id' => $userId)
        );
    }
}
