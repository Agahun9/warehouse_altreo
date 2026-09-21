<?php

declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use App\Core\Tenant;
use App\Models\OrderRepository;
use App\Models\PrintAgentRepository;

/** Tworzy lub aktualizuje komplet tabel centrum zamówień jednej firmy (t{id}_om_*, t{id}_print_*). */
final class TenantProvisioner
{
    public static function provision(Database $db, int $tenantId): void
    {
        $previous = Tenant::active() ? Tenant::id() : null;
        Tenant::activate($tenantId);
        try {
            $orders = new OrderRepository($db);
            $orders->ensureSchema();
            (new KsefService($orders))->ensureSchema();
            (new PrintAgentRepository($db))->ensureSchema();
            (new \App\Models\ConnectionRepository($db))->ensureSchema();
            (new \App\Models\MessageRepository($db))->ensureSchema();
        } finally {
            if ($previous === null) { Tenant::clear(); } else { Tenant::activate($previous); }
        }
    }
}
