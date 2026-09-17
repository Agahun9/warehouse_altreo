<?php

declare(strict_types=1);
namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Models\SaasRepository;
use RuntimeException;

/** Tworzy/aktualizuje schemat: globalne tabele sc_* oraz komplet tabel każdej istniejącej firmy. */
final class Installer
{
    /** @return string[] kolejne kroki instalacji */
    public static function run(): array
    {
        $config = Config::get('database');
        foreach (['database', 'username', 'password'] as $key) {
            if (in_array((string) ($config[$key] ?? ''), ['', 'NAZWA_BAZY', 'UZYTKOWNIK_BAZY', 'HASLO_BAZY'], true) && !($key === 'password' && ($config['driver'] ?? 'mysql') === 'sqlite')) {
                throw new RuntimeException('Uzupełnij app/Config/database.php (pole "'.$key.'").');
            }
        }
        $app = Config::get('app');
        if (!preg_match('/^[0-9a-f]{64}$/D', (string) ($app['encryption_key'] ?? ''))) {
            throw new RuntimeException('Ustaw encryption_key (64 znaki hex) w app/Config/app.php.');
        }

        $steps = [];
        $db = Database::instance();
        $steps[] = 'Połączono z bazą "'.$config['database'].'" ('.$db->fetchColumn('SELECT VERSION()').').';
        $saas = new SaasRepository($db);
        $saas->ensureSchema();
        $steps[] = 'Tabele globalne: sc_tenants, sc_users, sc_password_resets, sc_settings.';
        $tenants = $saas->allTenants();
        foreach ($tenants as $tenant) {
            TenantProvisioner::provision($db, (int) $tenant['id']);
            $steps[] = 'Firma #'.$tenant['id'].' ('.$tenant['name'].'): tabele t'.$tenant['id'].'_om_* i t'.$tenant['id'].'_print_* aktualne.';
        }
        if (!$tenants) { $steps[] = 'Brak firm – tabele firmy powstaną automatycznie przy rejestracji.'; }
        return $steps;
    }
}
