<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;

class ApiTokenRepository
{
    const TABLE = 'api_tokens';
    const TOKEN_PREFIX = 'altr_';

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
            . "name VARCHAR(190) NOT NULL,\n"
            . "token_hash CHAR(64) NOT NULL,\n"
            . "token_hint VARCHAR(32) NOT NULL,\n"
            . "is_active TINYINT(1) NOT NULL DEFAULT 1,\n"
            . "created_by INT UNSIGNED DEFAULT NULL,\n"
            . "created_by_name VARCHAR(190) DEFAULT NULL,\n"
            . "last_used_at DATETIME DEFAULT NULL,\n"
            . "last_used_ip VARCHAR(64) DEFAULT NULL,\n"
            . "usage_count INT UNSIGNED NOT NULL DEFAULT 0,\n"
            . "revoked_at DATETIME DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_api_tokens_hash (token_hash)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::$schemaEnsured = true;
    }

    public function all(): array
    {
        return $this->database->fetchAll(
            'SELECT id, name, token_hint, is_active, created_by, created_by_name, last_used_at, last_used_ip, usage_count, revoked_at, created_at'
            . ' FROM ' . self::TABLE . ' ORDER BY is_active DESC, id DESC'
        );
    }

    /**
     * Tworzy token i zwraca jego pelna wartosc. W bazie zapisywany jest wylacznie hash.
     */
    public function create(string $name, ?array $user = null): string
    {
        $token = self::TOKEN_PREFIX . bin2hex(random_bytes(24));
        $userName = '';
        if (is_array($user)) {
            $userName = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
            if ($userName === '') {
                $userName = (string) ($user['email'] ?? $user['username'] ?? '');
            }
        }

        $this->database->insert(self::TABLE, array(
            'name' => $name,
            'token_hash' => hash('sha256', $token),
            'token_hint' => substr($token, 0, 10) . '…' . substr($token, -4),
            'is_active' => 1,
            'created_by' => is_array($user) && isset($user['id']) ? (int) $user['id'] : null,
            'created_by_name' => $userName !== '' ? $userName : null,
        ));

        return $token;
    }

    public function revoke(int $id): int
    {
        return $this->database->update(
            self::TABLE,
            array('is_active' => 0, 'revoked_at' => date('Y-m-d H:i:s')),
            'id = :id',
            array('id' => $id)
        );
    }

    public function deleteById(int $id): int
    {
        return $this->database->delete(self::TABLE, 'id = :id', array('id' => $id));
    }

    public function validate(string $token, string $ip = ''): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        $row = $this->database->fetch(
            'SELECT id FROM ' . self::TABLE . ' WHERE token_hash = :token_hash AND is_active = 1 LIMIT 1',
            array('token_hash' => hash('sha256', $token))
        );

        if (!$row) {
            return false;
        }

        $this->database->execute(
            'UPDATE ' . self::TABLE . ' SET last_used_at = NOW(), last_used_ip = :ip, usage_count = usage_count + 1 WHERE id = :id',
            array('ip' => substr($ip, 0, 64), 'id' => (int) $row['id'])
        );

        return true;
    }
}
