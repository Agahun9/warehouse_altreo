<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;

class UserRepository
{
    private const MODULE_ACCESS_NONE = 'none';
    private const MODULE_ACCESS_READ = 'read';
    private const MODULE_ACCESS_EDIT = 'edit';

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
            "CREATE TABLE IF NOT EXISTS users (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "email VARCHAR(190) NOT NULL,\n"
            . "first_name VARCHAR(120) DEFAULT NULL,\n"
            . "last_name VARCHAR(120) DEFAULT NULL,\n"
            . "gutenberg_nick VARCHAR(190) DEFAULT NULL,\n"
            . "password_hash VARCHAR(255) NOT NULL,\n"
            . "role VARCHAR(20) NOT NULL DEFAULT 'user',\n"
            . "permission_level VARCHAR(20) NOT NULL DEFAULT 'edit',\n"
            . "loader_enabled TINYINT(1) NOT NULL DEFAULT 1,\n"
            . "is_active TINYINT(1) NOT NULL DEFAULT 0,\n"
            . "is_blocked TINYINT(1) NOT NULL DEFAULT 0,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_users_email (email),\n"
            . "KEY idx_users_role (role),\n"
            . "KEY idx_users_active (is_active),\n"
            . "KEY idx_users_blocked (is_blocked)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS email_verifications (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "user_id INT UNSIGNED NOT NULL,\n"
            . "token VARCHAR(128) NOT NULL,\n"
            . "expires_at DATETIME NOT NULL,\n"
            . "used_at DATETIME DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_email_verifications_token (token),\n"
            . "KEY idx_email_verifications_user (user_id)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS password_resets (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "user_id INT UNSIGNED NOT NULL,\n"
            . "token VARCHAR(128) NOT NULL,\n"
            . "expires_at DATETIME NOT NULL,\n"
            . "used_at DATETIME DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_password_resets_token (token),\n"
            . "KEY idx_password_resets_user (user_id)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS modules (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "code VARCHAR(50) NOT NULL,\n"
            . "name VARCHAR(100) NOT NULL,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_modules_code (code)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS user_modules (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "user_id INT UNSIGNED NOT NULL,\n"
            . "module_code VARCHAR(50) NOT NULL,\n"
            . "access_level VARCHAR(20) NOT NULL DEFAULT 'edit',\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_user_modules_pair (user_id, module_code),\n"
            . "KEY idx_user_modules_module (module_code)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->ensureUserColumn('first_name', "ALTER TABLE users ADD COLUMN first_name VARCHAR(120) DEFAULT NULL AFTER email");
        $this->ensureUserColumn('last_name', "ALTER TABLE users ADD COLUMN last_name VARCHAR(120) DEFAULT NULL AFTER first_name");
        $this->ensureUserColumn('gutenberg_nick', "ALTER TABLE users ADD COLUMN gutenberg_nick VARCHAR(190) DEFAULT NULL AFTER last_name");
        $this->ensureUserColumn('permission_level', "ALTER TABLE users ADD COLUMN permission_level VARCHAR(20) NOT NULL DEFAULT 'edit' AFTER role");
        $this->ensureUserColumn('loader_enabled', "ALTER TABLE users ADD COLUMN loader_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER permission_level");
        $this->ensureUserModulesColumn('access_level', "ALTER TABLE user_modules ADD COLUMN access_level VARCHAR(20) DEFAULT NULL AFTER module_code");
        $this->syncAvailableModules();
        $this->backfillLegacyModuleAccessLevels();
        self::$schemaEnsured = true;
    }

    public function createUser(array $data): string
    {
        return $this->database->insert('users', $data);
    }

    public function findByEmail($email)
    {
        return $this->database->fetch('SELECT * FROM users WHERE email = :email LIMIT 1', array('email' => $email));
    }

    public function findById($id)
    {
        return $this->database->fetch('SELECT * FROM users WHERE id = :id LIMIT 1', array('id' => $id));
    }

    public function allUsers(): array
    {
        return $this->database->fetchAll('SELECT * FROM users ORDER BY created_at DESC, id DESC');
    }

    public function countAll(): int
    {
        return (int) $this->database->fetchColumn('SELECT COUNT(*) FROM users');
    }

    public function countActive(): int
    {
        return (int) $this->database->fetchColumn('SELECT COUNT(*) FROM users WHERE is_active = 1');
    }

    public function countBlocked(): int
    {
        return (int) $this->database->fetchColumn('SELECT COUNT(*) FROM users WHERE is_blocked = 1');
    }

    public function countAdmins(): int
    {
        return (int) $this->database->fetchColumn('SELECT COUNT(*) FROM users WHERE role = :role', array('role' => 'admin'));
    }

    public function latest(int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        return $this->database->fetchAll('SELECT id, email, first_name, last_name, gutenberg_nick, role, permission_level, loader_enabled, is_active, is_blocked, created_at FROM users ORDER BY created_at DESC, id DESC LIMIT ' . $limit);
    }

    public function updateUser($id, array $data): int
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->database->update('users', $data, 'id = :id', array('id' => $id));
    }

    public function createEmailVerification($userId, $token, $expiresAt): string
    {
        return $this->database->insert('email_verifications', array(
            'user_id' => $userId,
            'token' => $token,
            'expires_at' => $expiresAt,
        ));
    }

    public function findEmailVerification($token)
    {
        return $this->database->fetch('SELECT * FROM email_verifications WHERE token = :token LIMIT 1', array('token' => $token));
    }

    public function useEmailVerification($id): int
    {
        return $this->database->update('email_verifications', array('used_at' => date('Y-m-d H:i:s')), 'id = :id', array('id' => $id));
    }

    public function createPasswordReset($userId, $token, $expiresAt): string
    {
        return $this->database->insert('password_resets', array(
            'user_id' => $userId,
            'token' => $token,
            'expires_at' => $expiresAt,
        ));
    }

    public function findPasswordReset($token)
    {
        return $this->database->fetch('SELECT * FROM password_resets WHERE token = :token LIMIT 1', array('token' => $token));
    }

    public function usePasswordReset($id): int
    {
        return $this->database->update('password_resets', array('used_at' => date('Y-m-d H:i:s')), 'id = :id', array('id' => $id));
    }

    public function modulesForUser($userId): array
    {
        return array_keys($this->modulePermissionsForUser($userId));
    }

    public function modulePermissionsForUser($userId): array
    {
        $rows = $this->database->fetchAll(
            'SELECT module_code, access_level FROM user_modules WHERE user_id = :user_id ORDER BY module_code',
            array('user_id' => $userId)
        );

        $permissions = array();
        foreach ($rows as $row) {
            $moduleCode = strtolower(trim((string) ($row['module_code'] ?? '')));
            $accessLevel = $this->normalizeModuleAccessLevel($row['access_level'] ?? self::MODULE_ACCESS_NONE);
            if ($moduleCode === '' || $accessLevel === self::MODULE_ACCESS_NONE) {
                continue;
            }

            $permissions[$moduleCode] = $accessLevel;
        }

        return $permissions;
    }

    public function replaceModules($userId, array $modules): void
    {
        $permissions = array();
        foreach ($modules as $moduleCode) {
            $normalizedCode = strtolower(trim((string) $moduleCode));
            if ($normalizedCode === '') {
                continue;
            }

            $permissions[$normalizedCode] = self::MODULE_ACCESS_EDIT;
        }

        $this->replaceModulePermissions($userId, $permissions);
    }

    public function replaceModulePermissions($userId, array $permissions): void
    {
        $this->database->delete('user_modules', 'user_id = :user_id', array('user_id' => $userId));

        foreach ($permissions as $moduleCode => $accessLevel) {
            $normalizedCode = strtolower(trim((string) $moduleCode));
            $normalizedAccessLevel = $this->normalizeModuleAccessLevel($accessLevel);
            if ($normalizedCode === '' || $normalizedAccessLevel === self::MODULE_ACCESS_NONE) {
                continue;
            }

            $this->database->insert('user_modules', array(
                'user_id' => $userId,
                'module_code' => $normalizedCode,
                'access_level' => $normalizedAccessLevel,
            ));
        }
    }

    public function availableModules(): array
    {
        return $this->configuredModules();
    }

    public function deleteUserById(int $id): int
    {
        return (int) $this->database->transaction(function () use ($id) {
            $this->database->delete('user_modules', 'user_id = :user_id', array('user_id' => $id));
            $this->database->delete('email_verifications', 'user_id = :user_id', array('user_id' => $id));
            $this->database->delete('password_resets', 'user_id = :user_id', array('user_id' => $id));

            return $this->database->delete('users', 'id = :id', array('id' => $id));
        });
    }

    private function ensureUserColumn(string $columnName, string $ddl): void
    {
        $exists = (int) $this->database->fetchColumn(
            'SELECT COUNT(*) FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE()'
            . ' AND TABLE_NAME = :table_name'
            . ' AND COLUMN_NAME = :column_name',
            array(
                'table_name' => 'users',
                'column_name' => $columnName,
            )
        );

        if ($exists <= 0) {
            $this->database->query($ddl);
        }
    }

    private function ensureUserModulesColumn(string $columnName, string $ddl): void
    {
        $exists = (int) $this->database->fetchColumn(
            'SELECT COUNT(*) FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE()'
            . ' AND TABLE_NAME = :table_name'
            . ' AND COLUMN_NAME = :column_name',
            array(
                'table_name' => 'user_modules',
                'column_name' => $columnName,
            )
        );

        if ($exists <= 0) {
            $this->database->query($ddl);
        }
    }

    private function syncAvailableModules(): void
    {
        foreach ($this->configuredModules() as $module) {
            $code = (string) ($module['code'] ?? '');
            $name = (string) ($module['name'] ?? $code);
            if ($code === '' || $name === '') {
                continue;
            }

            $existing = $this->database->fetch('SELECT id, name FROM modules WHERE code = :code LIMIT 1', array('code' => $code));
            if ($existing) {
                if ((string) ($existing['name'] ?? '') !== $name) {
                    $this->database->update('modules', array('name' => $name), 'id = :id', array('id' => (int) $existing['id']));
                }
                continue;
            }

            $this->database->insert('modules', array(
                'code' => $code,
                'name' => $name,
            ));
        }
    }

    private function backfillLegacyModuleAccessLevels(): void
    {
        $this->database->query(
            'UPDATE user_modules user_modules'
            . ' INNER JOIN users users ON users.id = user_modules.user_id'
            . ' SET user_modules.access_level = CASE'
            . '   WHEN LOWER(TRIM(COALESCE(users.permission_level, "edit"))) = "read" THEN "read"'
            . '   ELSE "edit"'
            . ' END'
            . ' WHERE user_modules.access_level IS NULL OR TRIM(user_modules.access_level) = ""'
        );
    }

    private function configuredModules(): array
    {
        $appConfig = Config::get('app');
        $modules = isset($appConfig['modules']) && is_array($appConfig['modules']) ? $appConfig['modules'] : array();
        // Media is part of the application itself. Keep it available even when
        // the environment-specific app/Config directory is not deployed from Git.
        $modules[] = array('code' => 'media', 'name' => 'Media');
        $modules[] = array('code' => 'mediamarkt', 'name' => 'MediaMarkt');
        $normalized = array();

        foreach ($modules as $module) {
            if (is_array($module)) {
                $code = strtolower(trim((string) ($module['code'] ?? '')));
                $name = trim((string) ($module['name'] ?? $code));
            } else {
                $raw = trim((string) $module);
                $code = strtolower($raw);
                $name = $raw;
            }

            if ($code === '' || $name === '') {
                continue;
            }

            $normalized[$code] = array(
                'code' => $code,
                'name' => $name,
            );
        }

        return array_values($normalized);
    }

    private function normalizeModuleAccessLevel($accessLevel): string
    {
        $normalized = strtolower(trim((string) $accessLevel));
        if ($normalized === self::MODULE_ACCESS_READ) {
            return self::MODULE_ACCESS_READ;
        }

        if ($normalized === self::MODULE_ACCESS_EDIT) {
            return self::MODULE_ACCESS_EDIT;
        }

        return self::MODULE_ACCESS_NONE;
    }
}

