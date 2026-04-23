<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;

class SettingRepository
{
    const TABLE = 'app_settings';
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
            . "setting_key VARCHAR(190) NOT NULL,\n"
            . "setting_value LONGTEXT DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_app_settings_key (setting_key)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::$schemaEnsured = true;
    }

    public function get(string $key, string $default = ''): string
    {
        $value = $this->database->fetchColumn(
            'SELECT setting_value FROM ' . self::TABLE . ' WHERE setting_key = :setting_key LIMIT 1',
            array('setting_key' => $key)
        );

        if ($value === false || $value === null) {
            return $default;
        }

        return (string) $value;
    }

    public function set(string $key, ?string $value): void
    {
        $existing = $this->database->fetch(
            'SELECT id FROM ' . self::TABLE . ' WHERE setting_key = :setting_key LIMIT 1',
            array('setting_key' => $key)
        );

        if ($existing && isset($existing['id'])) {
            $this->database->update(
                self::TABLE,
                array('setting_value' => $value),
                'id = :id',
                array('id' => (int) $existing['id'])
            );

            return;
        }

        $this->database->insert(self::TABLE, array(
            'setting_key' => $key,
            'setting_value' => $value,
        ));
    }
}
