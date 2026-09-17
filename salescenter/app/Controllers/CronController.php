<?php

declare(strict_types=1);
namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Tenant;
use App\Models\SaasRepository;
use App\Services\GlobalCronService;
use App\Services\OrderSecretBox;

final class CronController extends Controller
{
    private const SETTING = 'global_cron_keys';

    public function index(): void
    {
        $user = $this->requireAuth();
        $saas = $this->saas();
        if (!$saas->isHeadmaster($user)) { http_response_code(403); exit('Brak dostępu.'); }
        $keys = self::keys($saas);
        $base = (string) (Config::get('app')['public_base_url'] ?? '');
        $urls = [];
        foreach (['tokens', 'orders'] as $task) {
            $urls[$task] = $base.'?controller=cron&action=run&task='.$task.'&key='.$keys[$task];
        }
        $commands = [];
        foreach ($urls as $task => $url) { $commands[$task] = '/usr/bin/curl --silent --show-error --fail "'.$url.'" >/dev/null'; }
        header('Cache-Control: no-store, private');
        header('Referrer-Policy: no-referrer');
        $this->render('account/cron', ['pageTitle' => 'Globalne zadania cron', 'cronUrls' => $urls, 'cronCommands' => $commands]);
    }

    public function run(): void
    {
        header('Cache-Control: no-store, private');
        header('Referrer-Policy: no-referrer');
        header('Content-Type: application/json; charset=utf-8');
        if ($this->requestMethod() !== 'GET') { http_response_code(405); echo '{"error":"method"}'; return; }
        $task = (string) ($_GET['task'] ?? '');
        $key = (string) ($_GET['key'] ?? '');
        if (!in_array($task, ['tokens', 'orders'], true) || !preg_match('/^[a-f0-9]{64}$/D', $key)) { http_response_code(403); echo '{"error":"forbidden"}'; return; }
        $saas = $this->saas();
        $keys = self::keys($saas, false);
        if (!isset($keys[$task]) || !hash_equals($keys[$task], $key)) { http_response_code(403); echo '{"error":"forbidden"}'; return; }
        Tenant::clear();
        try {
            $report = GlobalCronService::run($task);
            http_response_code(!empty($report['errors']) ? 500 : 200);
            echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\Throwable $error) {
            error_log('[global-cron] '.get_class($error));
            http_response_code(500);
            echo '{"error":"cron_failed"}';
        }
    }

    private static function keys(SaasRepository $saas, bool $create = true): array
    {
        $setting = $saas->setting(self::SETTING);
        if (!empty($setting['encrypted'])) {
            $keys = OrderSecretBox::decrypt((string) $setting['encrypted']);
            if (isset($keys['tokens'], $keys['orders'])) { return $keys; }
        }
        if (!$create) { return []; }
        $keys = ['tokens' => bin2hex(random_bytes(32)), 'orders' => bin2hex(random_bytes(32))];
        $saas->saveSetting(self::SETTING, ['encrypted' => OrderSecretBox::encrypt($keys)]);
        return $keys;
    }
}
