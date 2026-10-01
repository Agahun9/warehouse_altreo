<?php

declare(strict_types=1);
namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Tenant;
use App\Models\ConnectionRepository;
use App\Models\OrderRepository;
use App\Models\SaasRepository;
use App\Services\AllegroService;
use App\Services\GlobalCronService;
use App\Services\OrderSecretBox;
use InvalidArgumentException;

/**
 * Administracja SalesCenter – ustawienia całej platformy widoczne tylko dla głównego
 * administratora (globalne crony, wspólna aplikacja Allegro). Firmy tego nie widzą.
 */
final class AdministrationController extends Controller
{
    private const SETTING = 'global_cron_keys';

    public function index(): void
    {
        $user = $this->requireHeadmaster();
        $saas = $this->saas();
        $keys = self::keys($saas);
        $base = (string) (Config::get('app')['public_base_url'] ?? '');
        $urls = [];
        foreach (['tokens', 'orders'] as $task) {
            $urls[$task] = $base.'?controller=administration&action=run&task='.$task.'&key='.$keys[$task];
        }
        $commands = [];
        foreach ($urls as $task => $url) { $commands[$task] = '/usr/bin/curl --silent --show-error --fail "'.$url.'" >/dev/null'; }
        $allegro = AllegroService::config();
        $stored = $saas->setting('allegro_app');
        $orders = new OrderRepository($this->db());
        $orders->ensureSchema();
        $connections = new ConnectionRepository($this->db());
        $connections->ensureSchema();
        $warehouseApi = null;
        foreach ($connections->all() as $connection) {
            if ($connection['platform'] === 'api' && ($connection['public']['purpose'] ?? '') === 'warehouse_picking') {
                $warehouseApi = ['name' => $connection['name'], 'status' => $connection['status'], 'token_hint' => (string) ($connection['public']['token_hint'] ?? '')];
                break;
            }
        }
        $this->ensureSessionStarted();
        $newWarehouseToken = (string) ($_SESSION['sc_warehouse_api_token'] ?? '');
        unset($_SESSION['sc_warehouse_api_token']);
        header('Cache-Control: no-store, private');
        header('Referrer-Policy: no-referrer');
        $this->render('administration/index', [
            'pageTitle' => 'Administracja SalesCenter', 'cronUrls' => $urls, 'cronCommands' => $commands,
            'csrf' => $this->csrfToken(),
            'warehouseApi' => $warehouseApi, 'warehouseApiToken' => $newWarehouseToken,
            'warehouseApiTenant' => (string) ($user['tenant']['name'] ?? ''),
            'warehouseApiBase' => IntegrationsController::apiBase(),
            'warehouseApiPickingUrl' => IntegrationsController::apiBase().'/picking/orders?status_id={STATUS_ID}',
            'adminNotes' => (string) ($saas->setting('admin_notes')['text'] ?? ''),
            'allegroApp' => [
                'configured' => AllegroService::configured(),
                'source' => (string) ($allegro['source'] ?? ''),
                'client_id_hint' => AllegroService::configured() ? substr((string) $allegro['client_id'], 0, 6).'…' : '',
                'saved_by' => (string) ($stored['saved_by'] ?? ''),
                'saved_at' => (string) ($stored['saved_at'] ?? ''),
                'redirect_uri' => IntegrationsController::appUrl('allegro-callback.php'),
                'docs_url' => IntegrationsController::appUrl('allegro-app-info.php'),
            ],
        ]);
    }

    /** Wspólna aplikacja Allegro dla wszystkich firm – firmy łączą konta samym logowaniem. */
    public function allegroapp(): void
    {
        $user = $this->requireHeadmaster();
        $this->requireCsrf();
        try {
            $clientId = trim((string) ($_POST['client_id'] ?? ''));
            $clientSecret = trim((string) ($_POST['client_secret'] ?? ''));
            if (!preg_match('/^[A-Za-z0-9_-]{8,100}$/D', $clientId) || strlen($clientSecret) < 8 || strlen($clientSecret) > 300) {
                throw new InvalidArgumentException('Wklej Client ID i Client Secret skopiowane z apps.developer.allegro.pl.');
            }
            (new AllegroService(true))->verifyApp($clientId, $clientSecret);
            $this->saas()->saveSetting('allegro_app', ['client_id' => $clientId, 'secret' => OrderSecretBox::encrypt(['client_secret' => $clientSecret]), 'saved_by' => (string) ($user['email'] ?? ''), 'saved_at' => gmdate('c')]);
            $this->setFlash('success', 'Aplikacja Allegro zweryfikowana. Wszystkie firmy łączą teraz konta Allegro samym logowaniem.');
        } catch (\Throwable $e) {
            $this->setFlash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : (strpos($e->getMessage(), '[401]') !== false || strpos($e->getMessage(), '[400]') !== false ? 'Allegro odrzuciło Client ID lub Client Secret. Skopiuj je ponownie z apps.developer.allegro.pl (bez spacji).' : 'Nie udało się sprawdzić aplikacji w Allegro. Spróbuj ponownie za chwilę.'));
        }
        $this->redirect('./index.php?controller=administration#allegro-app');
    }

    /** Token ograniczony do API zbierania magazynowego bieżącej firmy. */
    public function warehouseapitoken(): void
    {
        $this->requireHeadmaster();
        $this->requireCsrf();
        try {
            $orders = new OrderRepository($this->db());
            $orders->ensureSchema();
            $repository = new ConnectionRepository($this->db());
            $repository->ensureSchema();
            $existing = null;
            foreach ($repository->all() as $connection) {
                if ($connection['platform'] === 'api' && ($connection['public']['purpose'] ?? '') === 'warehouse_picking') {
                    $existing = $connection;
                    break;
                }
            }
            $token = Tenant::tokenPrefix().rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $public = ['token_hint' => substr($token, -4), 'purpose' => 'warehouse_picking'];
            if ($existing) {
                $repository->update((int) $existing['id'], ['token_hash' => hash('sha256', $token), 'public' => $public, 'status' => 'active', 'checked' => true]);
            } else {
                $repository->create('api', 'Zbieranie magazynowe', $public, [], 'active', hash('sha256', $token));
            }
            $this->ensureSessionStarted();
            $_SESSION['sc_warehouse_api_token'] = $token;
            $this->setFlash('success', 'Token API gotowy. Skopiuj go teraz – nie pokażemy go ponownie.');
        } catch (\Throwable $e) {
            $this->setFlash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'Nie udało się wygenerować tokenu API.');
        }
        $this->redirect('./index.php?controller=administration#warehouse-api');
    }

    /** Notatki głównego administratora (np. linki webhooków magazynu). */
    public function savenotes(): void
    {
        $user = $this->requireHeadmaster();
        $this->requireCsrf();
        $text = str_replace("\r\n", "\n", (string) ($_POST['notes'] ?? ''));
        if (mb_strlen($text, 'UTF-8') > 20000) {
            $this->setFlash('error', 'Notatki mogą mieć maksymalnie 20 000 znaków.');
        } else {
            $this->saas()->saveSetting('admin_notes', ['text' => $text, 'saved_by' => (string) ($user['email'] ?? ''), 'saved_at' => gmdate('c')]);
            $this->setFlash('success', 'Notatki zapisane.');
        }
        $this->redirect('./index.php?controller=administration#notes');
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
            // Błędy pojedynczych kont są zapisane w om_accounts.last_error i ponawiane – 500 tylko przy awarii przebiegu.
            http_response_code(!empty($report['fatal']) ? 500 : 200);
            echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\Throwable $error) {
            error_log('[global-cron] '.get_class($error));
            http_response_code(500);
            echo '{"error":"cron_failed"}';
        }
    }

    private function requireHeadmaster(): array
    {
        $user = $this->requireAuth();
        if (!$this->saas()->isHeadmaster($user)) { http_response_code(403); exit('Brak dostępu.'); }
        return $user;
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
