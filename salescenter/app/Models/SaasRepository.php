<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;

/**
 * Globalne tabele SaaS: firmy, użytkownicy i resety haseł (prefiks `sc_`).
 * Dane centrum zamówień każdej firmy leżą w osobnych tabelach `t{id}_om_*`.
 */
final class SaasRepository
{
    public const ROLES = ['owner' => 'Właściciel', 'admin' => 'Administrator', 'member' => 'Pracownik'];

    /** @var Database */
    private $db;

    public function __construct(Database $db) { $this->db = $db; }

    public function ensureSchema(): void
    {
        $sqlite = $this->db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $id = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $suffix = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        foreach (self::schema($id) as $table => $columns) {
            $this->db->query("CREATE TABLE IF NOT EXISTS $table ($columns)$suffix");
        }
        $index = $sqlite ? 'CREATE INDEX IF NOT EXISTS %s ON %s (%s)' : null;
        foreach (['sc_users' => ['sc_user_tenant' => 'tenant_id,id'], 'sc_password_resets' => ['sc_reset_user' => 'user_id,id']] as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if ($index !== null) { $this->db->query(sprintf($index, $name, $table, $columns)); continue; }
                if (!$this->db->fetch("SHOW INDEX FROM $table WHERE Key_name=:name", ['name' => $name])) {
                    try { $this->db->query("CREATE INDEX $name ON $table ($columns)"); }
                    catch (\PDOException $e) { if ((int) ($e->errorInfo[1] ?? 0) !== 1061) { throw $e; } }
                }
            }
        }
    }

    /** Definicje kolumn współdzielone z bin/install.php i database/schema.sql. */
    public static function schema(string $id): array
    {
        return [
            'sc_tenants' => "id $id, name VARCHAR(200) NOT NULL, nip VARCHAR(30) NULL, status VARCHAR(20) NOT NULL DEFAULT 'active', created_at VARCHAR(30) NOT NULL, updated_at VARCHAR(30) NULL",
            'sc_users' => "id $id, tenant_id BIGINT NOT NULL, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, name VARCHAR(150) NOT NULL, role VARCHAR(20) NOT NULL DEFAULT 'member', access VARCHAR(10) NOT NULL DEFAULT 'edit', is_blocked INTEGER NOT NULL DEFAULT 0, failed_logins INTEGER NOT NULL DEFAULT 0, locked_until BIGINT NOT NULL DEFAULT 0, last_login_at VARCHAR(30) NULL, created_at VARCHAR(30) NOT NULL, updated_at VARCHAR(30) NULL",
            'sc_password_resets' => "id $id, user_id BIGINT NOT NULL, token_hash CHAR(64) NOT NULL UNIQUE, expires_at BIGINT NOT NULL, used_at VARCHAR(30) NULL, created_at VARCHAR(30) NOT NULL",
            'sc_settings' => "setting_key VARCHAR(100) NOT NULL PRIMARY KEY, value_json LONGTEXT NOT NULL, updated_at VARCHAR(30) NOT NULL",
        ];
    }

    /** Ustawienia całej platformy (np. aplikacja Allegro wspólna dla wszystkich firm). */
    public function setting(string $key): array
    {
        $value = $this->db->fetchColumn('SELECT value_json FROM sc_settings WHERE setting_key=:k', ['k' => $key]);
        return $value ? (json_decode((string) $value, true) ?: []) : [];
    }

    public function saveSetting(string $key, array $value): void
    {
        $this->db->transaction(function () use ($key, $value) {
            $this->db->delete('sc_settings', 'setting_key=:k', ['k' => $key]);
            $this->db->insert('sc_settings', ['setting_key' => $key, 'value_json' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'updated_at' => gmdate('Y-m-d H:i:s')]);
        });
    }

    /** Operator platformy = właściciel lub administrator pierwszej zarejestrowanej firmy. */
    public function isPlatformOperator(array $user): bool
    {
        $first = (int) $this->db->fetchColumn('SELECT MIN(id) FROM sc_tenants');
        return $first > 0 && (int) ($user['tenant_id'] ?? 0) === $first && in_array((string) ($user['role'] ?? ''), ['owner', 'admin'], true);
    }

    /** Pierwsze konto użytkownika ma wyłączny dostęp do globalnych harmonogramów. */
    public function isHeadmaster(array $user): bool
    {
        $first = (int) $this->db->fetchColumn('SELECT MIN(id) FROM sc_users');
        return $first > 0 && (int) ($user['id'] ?? 0) === $first
            && (int) ($user['is_blocked'] ?? 0) === 0
            && in_array((string) ($user['role'] ?? ''), ['owner', 'admin'], true);
    }

    public static function normalizeEmail(string $email): string
    {
        $email = mb_strtolower(trim($email), 'UTF-8');
        if ($email === '' || strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Podaj poprawny adres e-mail.');
        }
        return $email;
    }

    public static function validatePassword(string $password): void
    {
        if (strlen($password) < 10 || strlen($password) > 200) {
            throw new InvalidArgumentException('Hasło musi mieć od 10 do 200 znaków.');
        }
    }

    public static function passwordHash(string $password): string
    {
        self::validatePassword($password);
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /** @return array{tenant_id:int,user_id:int} */
    public function register(string $companyName, string $nip, string $userName, string $email, string $password): array
    {
        $companyName = self::text($companyName, 200, 'Podaj nazwę firmy.');
        $userName = self::text($userName, 150, 'Podaj imię i nazwisko.');
        $nip = preg_replace('/[^0-9A-Za-z]/', '', $nip) ?? '';
        if (strlen($nip) > 30) { throw new InvalidArgumentException('NIP jest za długi.'); }
        $email = self::normalizeEmail($email);
        $hash = self::passwordHash($password);
        if ($this->findUserByEmail($email)) { throw new InvalidArgumentException('Konto z tym adresem e-mail już istnieje. Zaloguj się albo zresetuj hasło.'); }
        $now = gmdate('Y-m-d H:i:s');
        return $this->db->transaction(function () use ($companyName, $nip, $userName, $email, $hash, $now) {
            $tenantId = (int) $this->db->insert('sc_tenants', ['name' => $companyName, 'nip' => $nip !== '' ? $nip : null, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            $userId = (int) $this->db->insert('sc_users', ['tenant_id' => $tenantId, 'email' => $email, 'password_hash' => $hash, 'name' => $userName, 'role' => 'owner', 'access' => 'edit', 'is_blocked' => 0, 'failed_logins' => 0, 'locked_until' => 0, 'created_at' => $now, 'updated_at' => $now]);
            return ['tenant_id' => $tenantId, 'user_id' => $userId];
        });
    }

    public function tenant(int $id): ?array
    {
        $row = $this->db->fetch('SELECT * FROM sc_tenants WHERE id=:id', ['id' => $id]);
        return $row ?: null;
    }

    public function activeTenants(): array
    {
        return $this->db->fetchAll("SELECT * FROM sc_tenants WHERE status='active' ORDER BY id");
    }

    public function allTenants(): array
    {
        return $this->db->fetchAll('SELECT * FROM sc_tenants ORDER BY id');
    }

    public function updateTenant(int $id, string $name, string $nip): void
    {
        $nip = preg_replace('/[^0-9A-Za-z]/', '', $nip) ?? '';
        if (strlen($nip) > 30) { throw new InvalidArgumentException('NIP jest za długi.'); }
        $this->db->update('sc_tenants', ['name' => self::text($name, 200, 'Podaj nazwę firmy.'), 'nip' => $nip !== '' ? $nip : null, 'updated_at' => gmdate('Y-m-d H:i:s')], 'id=:id', ['id' => $id]);
    }

    public function findUserById(int $id): ?array
    {
        $row = $this->db->fetch('SELECT * FROM sc_users WHERE id=:id', ['id' => $id]);
        return $row ?: null;
    }

    public function findUserByEmail(string $email): ?array
    {
        $row = $this->db->fetch('SELECT * FROM sc_users WHERE email=:email', ['email' => mb_strtolower(trim($email), 'UTF-8')]);
        return $row ?: null;
    }

    public function users(int $tenantId): array
    {
        return $this->db->fetchAll('SELECT id,tenant_id,email,name,role,access,is_blocked,last_login_at,created_at FROM sc_users WHERE tenant_id=:t ORDER BY id', ['t' => $tenantId]);
    }

    public function tenantUser(int $tenantId, int $userId): array
    {
        $user = $this->db->fetch('SELECT * FROM sc_users WHERE id=:id AND tenant_id=:t', ['id' => $userId, 't' => $tenantId]);
        if (!$user) { throw new InvalidArgumentException('Nie znaleziono użytkownika w tej firmie.'); }
        return $user;
    }

    public function createUser(int $tenantId, string $name, string $email, string $password, string $role, string $access): int
    {
        $email = self::normalizeEmail($email);
        if ($this->findUserByEmail($email)) { throw new InvalidArgumentException('Ten adres e-mail jest już używany.'); }
        [$role, $access] = self::roleAccess($role, $access);
        if ($role === 'owner') { throw new InvalidArgumentException('Nowy użytkownik nie może być właścicielem. Zmień rolę po utworzeniu.'); }
        $now = gmdate('Y-m-d H:i:s');
        return (int) $this->db->insert('sc_users', ['tenant_id' => $tenantId, 'email' => $email, 'password_hash' => self::passwordHash($password), 'name' => self::text($name, 150, 'Podaj imię i nazwisko.'), 'role' => $role, 'access' => $access, 'is_blocked' => 0, 'failed_logins' => 0, 'locked_until' => 0, 'created_at' => $now, 'updated_at' => $now]);
    }

    public function updateUser(int $tenantId, int $userId, string $name, string $role, string $access, bool $blocked): void
    {
        $this->db->transaction(function () use ($tenantId, $userId, $name, $role, $access, $blocked) {
            $user = $this->tenantUser($tenantId, $userId);
            [$role, $access] = self::roleAccess($role, $access);
            if ($user['role'] === 'owner' && ($role !== 'owner' || $blocked) && $this->activeOwners($tenantId) <= 1) {
                throw new InvalidArgumentException('Firma musi mieć co najmniej jednego aktywnego właściciela.');
            }
            $this->db->update('sc_users', ['name' => self::text($name, 150, 'Podaj imię i nazwisko.'), 'role' => $role, 'access' => $access, 'is_blocked' => $blocked ? 1 : 0, 'updated_at' => gmdate('Y-m-d H:i:s')], 'id=:id AND tenant_id=:t', ['id' => $userId, 't' => $tenantId]);
        });
    }

    public function deleteUser(int $tenantId, int $userId): void
    {
        $this->db->transaction(function () use ($tenantId, $userId) {
            $user = $this->tenantUser($tenantId, $userId);
            if ($user['role'] === 'owner' && $this->activeOwners($tenantId) <= 1) {
                throw new InvalidArgumentException('Nie można usunąć ostatniego właściciela firmy.');
            }
            $this->db->delete('sc_password_resets', 'user_id=:id', ['id' => $userId]);
            $this->db->delete('sc_users', 'id=:id AND tenant_id=:t', ['id' => $userId, 't' => $tenantId]);
        });
    }

    public function setPassword(int $userId, string $password): void
    {
        $this->db->update('sc_users', ['password_hash' => self::passwordHash($password), 'failed_logins' => 0, 'locked_until' => 0, 'updated_at' => gmdate('Y-m-d H:i:s')], 'id=:id', ['id' => $userId]);
    }

    /**
     * Weryfikuje dane logowania z blokadą po 8 nieudanych próbach (15 minut).
     * Zwraca użytkownika albo komunikat błędu, nie ujawniając, czy e-mail istnieje.
     */
    public function attemptLogin(string $email, string $password): array
    {
        $generic = 'Nieprawidłowy e-mail lub hasło.';
        $user = $this->findUserByEmail($email);
        if (!$user) { password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG'); return ['error' => $generic]; }
        if ((int) $user['locked_until'] > time()) { return ['error' => 'Zbyt wiele nieudanych prób. Spróbuj ponownie za kilka minut.']; }
        if (!password_verify($password, (string) $user['password_hash'])) {
            $failed = (int) $user['failed_logins'] + 1;
            $this->db->update('sc_users', ['failed_logins' => $failed >= 8 ? 0 : $failed, 'locked_until' => $failed >= 8 ? time() + 900 : 0], 'id=:id', ['id' => $user['id']]);
            return ['error' => $generic];
        }
        if ((int) $user['is_blocked'] === 1) { return ['error' => 'Konto zostało zablokowane przez administratora firmy.']; }
        $tenant = $this->tenant((int) $user['tenant_id']);
        if (!$tenant || $tenant['status'] !== 'active') { return ['error' => 'Konto firmy jest nieaktywne.']; }
        $data = ['failed_logins' => 0, 'locked_until' => 0, 'last_login_at' => gmdate('Y-m-d H:i:s')];
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $user['password_hash'] = $data['password_hash'];
        }
        $this->db->update('sc_users', $data, 'id=:id', ['id' => $user['id']]);
        return ['user' => $user];
    }

    /** Zwraca jednorazowy token (ważny 60 minut) albo null, gdy konto nie istnieje. */
    public function createPasswordReset(string $email): ?array
    {
        $user = $this->findUserByEmail($email);
        if (!$user || (int) $user['is_blocked'] === 1) { return null; }
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->db->delete('sc_password_resets', 'user_id=:id AND used_at IS NULL', ['id' => $user['id']]);
        $this->db->insert('sc_password_resets', ['user_id' => $user['id'], 'token_hash' => hash('sha256', $token), 'expires_at' => time() + 3600, 'used_at' => null, 'created_at' => gmdate('Y-m-d H:i:s')]);
        return ['user' => $user, 'token' => $token];
    }

    public function validReset(string $token): ?array
    {
        if ($token === '' || strlen($token) > 100) { return null; }
        $row = $this->db->fetch('SELECT * FROM sc_password_resets WHERE token_hash=:h AND used_at IS NULL', ['h' => hash('sha256', $token)]);
        return $row && (int) $row['expires_at'] >= time() ? $row : null;
    }

    public function consumeReset(string $token, string $password): void
    {
        self::validatePassword($password);
        $this->db->transaction(function () use ($token, $password) {
            $reset = $this->validReset($token);
            if (!$reset) { throw new InvalidArgumentException('Link resetu hasła jest nieprawidłowy lub wygasł.'); }
            $this->db->update('sc_password_resets', ['used_at' => gmdate('Y-m-d H:i:s')], 'id=:id', ['id' => $reset['id']]);
            $this->setPassword((int) $reset['user_id'], $password);
        });
    }

    private function activeOwners(int $tenantId): int
    {
        return (int) $this->db->fetchColumn("SELECT COUNT(*) FROM sc_users WHERE tenant_id=:t AND role='owner' AND is_blocked=0", ['t' => $tenantId]);
    }

    private static function roleAccess(string $role, string $access): array
    {
        if (!array_key_exists($role, self::ROLES)) { throw new InvalidArgumentException('Nieznana rola użytkownika.'); }
        $access = $role === 'member' ? $access : 'edit';
        if (!in_array($access, ['edit', 'read'], true)) { throw new InvalidArgumentException('Nieznany poziom dostępu.'); }
        return [$role, $access];
    }

    private static function text(string $value, int $max, string $message): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value, 'UTF-8') > $max) { throw new InvalidArgumentException($message.' (maks. '.$max.' znaków)'); }
        return $value;
    }
}
