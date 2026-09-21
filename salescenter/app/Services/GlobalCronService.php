<?php

declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use App\Core\Tenant;
use App\Models\OrderRepository;
use App\Models\SaasRepository;
use RuntimeException;

final class GlobalCronService
{
    public static function run(string $task): array
    {
        if (!in_array($task, ['orders', 'tokens'], true)) { throw new RuntimeException('Nieznane zadanie cron.'); }
        $db = Database::instance();
        $saas = new SaasRepository($db);
        $saas->ensureSchema();
        Tenant::clear();
        if (!$db->acquireAdvisoryLock('global_cron')) { return ['task' => $task, 'busy' => true]; }
        $report = ['task' => $task, 'at' => gmdate('c'), 'companies' => 0, 'accounts' => 0, 'refreshed' => 0, 'new_orders' => 0, 'errors' => 0];
        try {
            foreach ($saas->activeTenants() as $tenant) {
                Tenant::activate((int) $tenant['id']);
                $report['companies']++;
                try {
                    if ($task === 'tokens') {
                        foreach ([new AllegroService(true), new MoreleService()] as $service) {
                            foreach ($service->listAccounts() as $account) {
                                if (empty($account['is_active'])) { continue; }
                                $report['accounts']++;
                                try { $service->refreshAccessToken($account); $report['refreshed']++; }
                                catch (\Throwable $error) {
                                    $report['errors']++;
                                    OrderSyncError::log($error, ['stage' => 'token_refresh', 'platform' => $service->platform(), 'connection_id' => (int) $account['id']]);
                                }
                            }
                        }
                    } else {
                        $repo = new OrderRepository($db);
                        $repo->ensureSchema();
                        foreach ($repo->accounts() as $account) { if (!empty($account['enabled'])) { $report['accounts']++; } }
                        $sync = new OrderSyncService($repo);
                        foreach ($sync->sync() as $result) {
                            if (!empty($result['error'])) { $report['errors']++; }
                            $report['new_orders'] += (int) ($result['added'] ?? 0);
                        }
                        try { $sync->repairImages(40); } catch (\Throwable $ignored) {}
                        try { $repo->automation()->runScheduled(); }
                        catch (\Throwable $error) { $report['errors']++; OrderSyncError::log($error, ['stage' => 'automation_schedule']); }
                        // Wiadomości marketplace – każde połączenie według własnego interwału z ustawień.
                        try {
                            $messages = new \App\Models\MessageRepository($db);
                            $messages->ensureSchema();
                            foreach ((new \App\Services\Messages\MessageCenter($messages))->sync() as $result) {
                                $report['messages'] = ($report['messages'] ?? 0) + (int) ($result['new'] ?? 0);
                                $report['auto_replies'] = ($report['auto_replies'] ?? 0) + (int) ($result['auto'] ?? 0);
                            }
                        } catch (\Throwable $error) { $report['errors']++; OrderSyncError::log($error, ['stage' => 'messages_sync']); }
                    }
                } catch (\Throwable $error) {
                    $report['errors']++;
                    OrderSyncError::log($error, ['stage' => $task.'_cron', 'tenant_id' => (int) $tenant['id']]);
                } finally {
                    Tenant::clear();
                }
            }
        } finally {
            Tenant::clear();
            $db->releaseAdvisoryLock('global_cron');
        }
        return $report;
    }
}
