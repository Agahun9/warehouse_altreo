<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Ustawienia integracji aktywnej firmy (np. adres i token API własnego sklepu).
 * Odpowiednik app_settings z aplikacji magazynowej, przechowywany w om_settings
 * firmy pod kluczem `integrations`, więc każda firma ma własne wartości.
 */
class SettingRepository
{
    private const KEY = 'integrations';

    /** @var OrderRepository */
    private $orders;

    public function __construct(Database $database)
    {
        $this->orders = new OrderRepository($database);
    }

    public function ensureSchema(): void
    {
        $this->orders->ensureSchema();
    }

    public function get(string $key, string $default = ''): string
    {
        $values = $this->orders->setting(self::KEY);
        return array_key_exists($key, $values) ? (string) $values[$key] : $default;
    }

    public function set(string $key, string $value): void
    {
        $values = $this->orders->setting(self::KEY);
        $values[$key] = $value;
        $this->orders->saveSetting(self::KEY, $values);
    }
}
