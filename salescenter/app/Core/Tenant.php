<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Aktywna firma (tenant) bieżącego żądania.
 *
 * Każda firma ma własny komplet tabel centrum zamówień w tej samej bazie,
 * np. `t12_om_orders`. Kod zamówień dalej używa nazw `om_*` / `print_*`,
 * a Database::query() podmienia je na tabele aktywnej firmy. Zapytanie do
 * tabeli firmy bez aktywnego kontekstu jest odrzucane (fail closed), więc
 * pominięty warunek WHERE nie może ujawnić danych innej firmy.
 */
final class Tenant
{
    /** Tabele należące do firmy. Tabele globalne SaaS mają prefiks `sc_` i nie są tu wymienione. */
    public const TABLES = [
        'om_statuses', 'om_accounts', 'om_orders', 'om_mappings', 'om_events', 'om_rules', 'om_rule_runs',
        'om_settings', 'om_series', 'om_documents', 'om_shipments', 'om_carrier_accounts', 'om_payment_methods',
        'om_payment_mappings', 'om_ksef_accounts', 'om_ksef_submissions', 'om_connections',
        'print_agent_stations', 'print_agent_jobs', 'print_fiscal_printers', 'print_fiscal_jobs',
    ];

    /** @var int|null */
    private static $id = null;

    /** @var string|null */
    private static $pattern = null;

    public static function activate(int $tenantId): void
    {
        if ($tenantId < 1) {
            throw new RuntimeException('Nieprawidłowy identyfikator firmy.');
        }
        self::$id = $tenantId;
    }

    public static function clear(): void
    {
        self::$id = null;
    }

    public static function active(): bool
    {
        return self::$id !== null;
    }

    public static function id(): int
    {
        if (self::$id === null) {
            throw new RuntimeException('Brak aktywnej firmy dla operacji na danych centrum zamówień.');
        }
        return self::$id;
    }

    public static function prefix(): string
    {
        return 't' . self::id() . '_';
    }

    /** Fizyczna nazwa tabeli firmy, np. om_orders -> t12_om_orders. */
    public static function table(string $table, ?int $tenantId = null): string
    {
        if (!in_array($table, self::TABLES, true)) {
            throw new RuntimeException('Nieznana tabela firmy: ' . $table);
        }
        return 't' . ($tenantId ?? self::id()) . '_' . $table;
    }

    /** Podmienia nazwy tabel firmy w SQL. Parametry zapytania nie są modyfikowane. */
    public static function rewrite(string $sql): string
    {
        if (self::$pattern === null) {
            self::$pattern = '/(?<![A-Za-z0-9_$])(' . implode('|', self::TABLES) . ')(?![A-Za-z0-9_$])/';
        }
        if (preg_match(self::$pattern, $sql) !== 1) {
            return $sql;
        }
        if (self::$id === null) {
            throw new RuntimeException('Zapytanie do danych firmy bez aktywnej firmy zostało zablokowane.');
        }
        $prefix = 't' . self::$id . '_';
        return (string) preg_replace(self::$pattern, $prefix . '$1', $sql);
    }

    /**
     * Tokeny agentów druku niosą identyfikator firmy (`t12.xxxxx`), bo agent
     * nie ma sesji. Sam prefiks niczego nie autoryzuje – hash całego tokenu
     * jest dalej sprawdzany w tabelach wskazanej firmy.
     */
    public static function tokenPrefix(): string
    {
        return 't' . self::id() . '.';
    }

    public static function idFromToken(string $token): ?int
    {
        if (preg_match('/^t([1-9][0-9]{0,9})\./D', substr($token, 0, 16), $match) !== 1) {
            return null;
        }
        return (int) $match[1];
    }
}
