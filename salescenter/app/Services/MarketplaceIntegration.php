<?php

declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use App\Models\ConnectionRepository;
use RuntimeException;

/**
 * Wspólna baza konektorów kanałów sprzedaży. Konto = połączenie firmy
 * (om_connections), więc każda firma widzi wyłącznie własne konta.
 * Metody wołane przez centrum zamówień: listAccounts, readOrderPage,
 * testConnection, opcjonalnie acceptOrder, publishOrderShipment.
 */
abstract class MarketplaceIntegration
{
    /** @var ConnectionRepository|null */
    private $connections;

    abstract public function platform(): string;

    abstract protected function label(): string;

    /** Sprawdza dane dostępowe; zwraca ['name'=>sugerowana nazwa, 'remote_id'=>..., 'public'=>[...]] */
    abstract public function testConnection(array $account): array;

    protected function connections(): ConnectionRepository
    {
        if ($this->connections === null) {
            $this->connections = new ConnectionRepository(Database::instance());
            $this->connections->ensureSchema();
        }
        return $this->connections;
    }

    public function listAccounts(): array
    {
        return $this->connections()->accountsFor($this->platform());
    }

    /** Zapisuje odświeżone tokeny połączenia (szyfrowane). */
    protected function storeSecret(array $account, array $secret, array $public = []): void
    {
        $changes = ['secret' => $secret];
        if ($public) { $changes['public'] = $public; }
        $this->connections()->update((int) $account['connection_id'], $changes);
    }

    public function __call(string $method, array $arguments)
    {
        throw new RuntimeException($this->label().': ta operacja nie jest obsługiwana przez API tej platformy.');
    }
}
