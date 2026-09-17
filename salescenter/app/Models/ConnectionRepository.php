<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\OrderSecretBox;
use InvalidArgumentException;

/**
 * Połączenia firmy z kanałami sprzedaży (t{id}_om_connections).
 * Dane niejawne (tokeny, klucze API) są szyfrowane. Każde połączenie ma jedno
 * konto importu w om_accounts: platform + source_id = id połączenia.
 */
final class ConnectionRepository
{
    /** @var Database */
    private $db;

    public function __construct(Database $db) { $this->db = $db; }

    public function ensureSchema(): void
    {
        $sqlite = $this->db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $id = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $suffix = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';
        $this->db->query("CREATE TABLE IF NOT EXISTS om_connections (id $id, platform VARCHAR(20) NOT NULL, name VARCHAR(150) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'active', public_json TEXT NOT NULL, secret_json LONGTEXT NULL, token_hash CHAR(64) NULL UNIQUE, last_check_at VARCHAR(30) NULL, last_error TEXT NULL, created_at VARCHAR(30) NOT NULL, updated_at VARCHAR(30) NOT NULL)$suffix");
    }

    /** Połączenia z danymi konta importu (bez sekretów). */
    public function all(): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM om_connections ORDER BY platform,name,id');
        $accounts = [];
        foreach ($this->db->fetchAll('SELECT * FROM om_accounts') as $account) { $accounts[$account['platform'].'|'.$account['source_id']] = $account; }
        foreach ($rows as &$row) {
            $row['public'] = json_decode((string) $row['public_json'], true) ?: [];
            $row['account'] = $accounts[$row['platform'].'|'.$row['id']] ?? null;
            $row['order_count'] = $row['account'] ? (int) $this->db->fetchColumn('SELECT COUNT(*) FROM om_orders WHERE account_id=:a', ['a' => $row['account']['id']]) : 0;
            unset($row['secret_json'], $row['token_hash']);
        }
        unset($row);
        return $rows;
    }

    public function find(int $id): array
    {
        $row = $this->db->fetch('SELECT * FROM om_connections WHERE id=:id', ['id' => $id]);
        if (!$row) { throw new InvalidArgumentException('Nie znaleziono połączenia.'); }
        return $row;
    }

    /** Pełne dane połączenia do wywołań API: publiczne + odszyfrowane sekrety. */
    public function credentials(array $row): array
    {
        $public = json_decode((string) $row['public_json'], true) ?: [];
        $secret = trim((string) ($row['secret_json'] ?? '')) !== '' ? OrderSecretBox::decrypt((string) $row['secret_json']) : [];
        return array_merge($public, $secret, ['id' => (int) $row['id'], 'connection_id' => (int) $row['id'], 'name' => (string) $row['name'], 'platform' => (string) $row['platform'], 'is_active' => $row['status'] === 'active' ? 1 : 0, 'status' => (string) $row['status']]);
    }

    /** Konta w formacie listAccounts() dla konektora danej platformy. */
    public function accountsFor(string $platform): array
    {
        $result = [];
        foreach ($this->db->fetchAll('SELECT * FROM om_connections WHERE platform=:p ORDER BY id', ['p' => $platform]) as $row) {
            try { $result[] = $this->credentials($row); }
            catch (\Throwable $e) { /* Uszkodzone dane jednego połączenia nie blokują pozostałych. */ }
        }
        return $result;
    }

    public function create(string $platform, string $name, array $public, array $secret, string $status = 'active', ?string $tokenHash = null): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $name = self::name($name);
        $id = (int) $this->db->insert('om_connections', ['platform' => $platform, 'name' => $name, 'status' => $status, 'public_json' => OrderRepository::json($public), 'secret_json' => $secret ? OrderSecretBox::encrypt($secret) : null, 'token_hash' => $tokenHash, 'last_check_at' => $status === 'active' ? $now : null, 'last_error' => null, 'created_at' => $now, 'updated_at' => $now]);
        if ($status === 'active') { $this->syncAccount($id); }
        return $id;
    }

    /** Aktualizuje dane; sekrety są scalane z istniejącymi (puste pole = bez zmian). */
    public function update(int $id, array $changes): void
    {
        $row = $this->find($id);
        $data = ['updated_at' => gmdate('Y-m-d H:i:s')];
        if (array_key_exists('name', $changes)) { $data['name'] = self::name((string) $changes['name']); }
        if (array_key_exists('status', $changes)) { $data['status'] = (string) $changes['status']; }
        if (array_key_exists('last_error', $changes)) { $data['last_error'] = $changes['last_error']; }
        if (array_key_exists('token_hash', $changes)) { $data['token_hash'] = $changes['token_hash']; }
        if (!empty($changes['checked'])) { $data['last_check_at'] = gmdate('Y-m-d H:i:s'); }
        if (isset($changes['public'])) {
            $data['public_json'] = OrderRepository::json(array_merge(json_decode((string) $row['public_json'], true) ?: [], $changes['public']));
        }
        if (isset($changes['secret'])) {
            $current = trim((string) ($row['secret_json'] ?? '')) !== '' ? OrderSecretBox::decrypt((string) $row['secret_json']) : [];
            $merged = array_merge($current, array_filter($changes['secret'], static function ($value) { return $value !== '' && $value !== null; }));
            $data['secret_json'] = $merged ? OrderSecretBox::encrypt($merged) : null;
        }
        $this->db->update('om_connections', $data, 'id=:id', ['id' => $id]);
        $this->syncAccount($id);
    }

    public function findByTokenHash(string $hash): ?array
    {
        $row = $this->db->fetch('SELECT * FROM om_connections WHERE token_hash=:h', ['h' => $hash]);
        return $row ?: null;
    }

    /** Połączenie tej samej platformy o tym samym identyfikatorze konta (np. ID sprzedawcy Allegro). */
    public function findByRemoteId(string $platform, string $remoteId): ?array
    {
        foreach ($this->db->fetchAll('SELECT * FROM om_connections WHERE platform=:p', ['p' => $platform]) as $row) {
            $public = json_decode((string) $row['public_json'], true) ?: [];
            if ($remoteId !== '' && (string) ($public['remote_id'] ?? '') === $remoteId) { return $row; }
        }
        return null;
    }

    /**
     * Odłącza konto: usuwa dane dostępowe, ale zostawia pobrane zamówienia
     * (konto importu zostaje wstrzymane i oznaczone jako odłączone).
     */
    public function delete(int $id): void
    {
        $row = $this->find($id);
        $this->db->transaction(function () use ($row) {
            $this->db->update('om_accounts', ['enabled' => 0, 'cursor_json' => null, 'name' => mb_substr($row['name'].' (odłączone)', 0, 150, 'UTF-8')], 'platform=:p AND source_id=:s', ['p' => $row['platform'], 's' => $row['id']]);
            $this->db->delete('om_connections', 'id=:id', ['id' => $row['id']]);
        });
    }

    public function account(int $connectionId): ?array
    {
        $row = $this->find($connectionId);
        $account = $this->db->fetch('SELECT * FROM om_accounts WHERE platform=:p AND source_id=:s', ['p' => $row['platform'], 's' => $row['id']]);
        return $account ?: null;
    }

    /** Konto importu odpowiada połączeniu; nowe konto startuje z włączonym importem. */
    private function syncAccount(int $id): void
    {
        $row = $this->find($id);
        if ($row['status'] === 'pending') { return; }
        $existing = $this->db->fetch('SELECT id FROM om_accounts WHERE platform=:p AND source_id=:s', ['p' => $row['platform'], 's' => $row['id']]);
        if ($existing) {
            $this->db->update('om_accounts', ['name' => $row['name']], 'id=:id', ['id' => $existing['id']]);
        } else {
            $this->db->insert('om_accounts', ['platform' => $row['platform'], 'source_id' => $row['id'], 'name' => $row['name'], 'enabled' => $row['platform'] === 'api' ? 0 : 1, 'next_attempt' => 0]);
        }
    }

    private static function name(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name, 'UTF-8') > 150) { throw new InvalidArgumentException('Podaj nazwę połączenia (maks. 150 znaków).'); }
        return $name;
    }
}
