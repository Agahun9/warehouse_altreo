<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Tenant;
use App\Models\ConnectionRepository;
use App\Models\OrderRepository;
use App\Services\AllegroService;
use App\Services\Integrations\Catalog;
use App\Services\Integrations\Http;
use App\Services\OrderSyncError;
use App\Services\OrderSyncService;
use App\Services\WooCommerceService;
use InvalidArgumentException;

/** Moduł „Integracje”: łączenie kanałów sprzedaży firmy (zakładka Konta i import). */
final class IntegrationsController extends Controller
{
    /** Publiczny adres pliku aplikacji, np. allegro-callback.php. */
    public static function appUrl(string $file): string
    {
        $config = Config::get('app');
        $base = trim((string) ($config['public_base_url'] ?? ''));
        if ($base === '' && isset($_SERVER['HTTP_HOST'])) {
            $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
            $host = preg_replace('/[^A-Za-z0-9.:\-\[\]]/', '', (string) $_SERVER['HTTP_HOST']);
            $base = ($https ? 'https://' : 'http://').$host.(string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
        }
        if ($base === '') { return $file; }
        return preg_replace('#/[^/]*$#', '/', $base).$file;
    }

    public static function apiBase(): string
    {
        return self::appUrl('api.php/v1');
    }

    private function connections(): ConnectionRepository
    {
        $orders = new OrderRepository($this->db()); $orders->ensureSchema();
        $repository = new ConnectionRepository($this->db()); $repository->ensureSchema();
        return $repository;
    }

    private function back(string $query = ''): void
    {
        $this->redirect('./index.php?controller=orders&tab=accounts'.$query);
    }

    private function writeGuard(): array
    {
        $user = $this->requireModuleWrite('orders');
        $this->requireCsrf();
        return $user;
    }

    private function service(string $platform)
    {
        $classes = OrderSyncService::CLASSES;
        if (!isset($classes[$platform])) { throw new InvalidArgumentException('Nieznany kanał sprzedaży.'); }
        return new $classes[$platform]();
    }

    /** Dane z formularza podzielone na jawne i niejawne według definicji pól katalogu. */
    private function formValues(array $definition, bool $requireAll): array
    {
        $public = []; $secret = [];
        foreach ($definition['fields'] as $field) {
            if (!empty($field['manual']) && $definition['auth'] === 'woo' && $field['name'] !== 'shop_url' && empty($_POST['manual'])) { continue; }
            $value = trim((string) ($_POST[$field['name']] ?? ''));
            if (mb_strlen($value, 'UTF-8') > 2000) { throw new InvalidArgumentException('Za długa wartość pola „'.$field['label'].'”.'); }
            if ($field['name'] === 'shop_url' && $value !== '') { $value = Http::normalizeShopUrl($value); }
            if ($field['name'] === 'api_url' && $value !== '') { $value = Http::normalizeShopUrl($value); }
            if ($field['name'] === 'shop_id' && $value !== '' && !ctype_digit($value)) { throw new InvalidArgumentException('ID sklepu musi być liczbą.'); }
            $required = !empty($field['required']) || (!empty($field['manual']) && !empty($_POST['manual']));
            if ($requireAll && $required && $value === '') { throw new InvalidArgumentException('Uzupełnij pole „'.$field['label'].'”.'); }
            if (!empty($field['secret'])) { $secret[$field['name']] = $value; } else { $public[$field['name']] = $value; }
        }
        return [$public, $secret];
    }

    public function connect(): void
    {
        $this->writeGuard();
        $platform = (string) ($_POST['platform'] ?? '');
        try {
            $definition = Catalog::get($platform);
            if ($definition['auth'] !== 'fields' && !($definition['auth'] === 'woo' && !empty($_POST['manual']))) { throw new InvalidArgumentException('Ten kanał łączy się przez logowanie.'); }
            [$public, $secret] = $this->formValues($definition, true);
            $test = $this->service($platform)->testConnection($public + $secret);
            $repository = $this->connections();
            $remoteId = (string) ($test['remote_id'] ?? '');
            $existing = $remoteId !== '' ? $repository->findByRemoteId($platform, $remoteId) : null;
            $public = array_merge($public, (array) ($test['public'] ?? []), ['remote_id' => $remoteId]);
            $secret = array_merge($secret, (array) ($test['secret'] ?? []));
            $name = trim((string) ($_POST['name'] ?? '')) ?: (string) $test['name'];
            if ($existing) {
                $repository->update((int) $existing['id'], ['name' => $name, 'public' => $public, 'secret' => $secret, 'status' => 'active', 'last_error' => null, 'checked' => true]);
                $id = (int) $existing['id'];
                $message = 'Zaktualizowano połączenie. '.$test['message'];
            } else {
                $id = $repository->create($platform, $name, $public, $secret);
                $message = $test['message'].' Import ostatnich 7 dni ruszy automatycznie. Chcesz starsze zamówienia? Użyj „Pobierz od daty”.';
            }
            $this->setFlash('success', $message);
            $this->back('&connection='.$id.'#sc-connection-'.$id);
        } catch (\Throwable $e) {
            $this->setFlash('error', $this->safeError($e, $platform));
            $this->back('&add='.rawurlencode($platform));
        }
    }

    public function update(): void
    {
        $this->writeGuard();
        $id = (int) ($_POST['connection_id'] ?? 0);
        try {
            $repository = $this->connections();
            $row = $repository->find($id);
            $definition = Catalog::get((string) $row['platform']);
            $changes = ['name' => (string) ($_POST['name'] ?? $row['name'])];
            if ($definition['auth'] === 'fields' || ($definition['auth'] === 'woo' && !empty($_POST['manual']))) {
                [$public, $secret] = $this->formValues($definition, false);
                $current = $repository->credentials($row);
                $candidate = array_merge($current, array_filter($public, 'strlen'), array_filter($secret, 'strlen'));
                // Nowe dane logowania = nowe tokeny (stare należą do poprzedniej aplikacji).
                foreach (['client_id', 'client_secret', 'api_key', 'app_key', 'app_secret', 'consumer_key', 'consumer_secret'] as $credential) {
                    if (isset($candidate[$credential], $current[$credential]) && (string) $candidate[$credential] !== (string) $current[$credential]) { unset($candidate['access_token'], $candidate['refresh_token']); break; }
                }
                $test = $this->service((string) $row['platform'])->testConnection(array_diff_key($candidate, ['connection_id' => 1]));
                $changes += ['public' => array_filter($public, 'strlen') + (array) ($test['public'] ?? []), 'secret' => array_merge($secret, (array) ($test['secret'] ?? [])), 'status' => 'active', 'last_error' => null, 'checked' => true];
            }
            $repository->update($id, $changes);
            $this->setFlash('success', 'Zapisano ustawienia połączenia.');
        } catch (\Throwable $e) {
            $this->setFlash('error', $this->safeError($e, 'update'));
        }
        $this->back('&connection='.$id.'#sc-connection-'.$id);
    }

    public function test(): void
    {
        $this->writeGuard();
        $id = (int) ($_POST['connection_id'] ?? 0);
        $repository = $this->connections();
        try {
            $row = $repository->find($id);
            $test = $this->service((string) $row['platform'])->testConnection($repository->credentials($row));
            $repository->update($id, ['status' => 'active', 'last_error' => null, 'checked' => true, 'public' => (array) ($test['public'] ?? [])]);
            $this->setFlash('success', (string) $test['message']);
        } catch (\Throwable $e) {
            $message = $this->safeError($e, 'test');
            try { $repository->update($id, ['status' => 'error', 'last_error' => $message, 'checked' => true]); } catch (\Throwable $ignored) {}
            $this->setFlash('error', $message);
        }
        $this->back('&connection='.$id.'#sc-connection-'.$id);
    }

    public function settings(): void
    {
        $this->writeGuard();
        $id = (int) ($_POST['connection_id'] ?? 0);
        try {
            $repository = $this->connections();
            $row = $repository->find($id);
            $account = $repository->account($id);
            if (!$account) { throw new InvalidArgumentException('Połączenie nie ma konta importu.'); }
            $data = ['enabled' => !empty($_POST['enabled']) ? 1 : 0];
            if (in_array($row['platform'], ['empik', 'mediamarkt'], true)) { $data['auto_accept'] = !empty($_POST['auto_accept']) ? 1 : 0; }
            if ($row['platform'] === 'api') { $data['enabled'] = 0; }
            $this->db()->update('om_accounts', $data, 'id=:id', ['id' => $account['id']]);
            $this->setFlash('success', $data['enabled'] ? 'Zapisano. Automatyczny import jest włączony.' : 'Zapisano. Automatyczny import jest wstrzymany.');
        } catch (\Throwable $e) {
            $this->setFlash('error', $this->safeError($e, 'settings'));
        }
        $this->back('&connection='.$id.'#sc-connection-'.$id);
    }

    /** Pobieranie starszych zamówień: ustawia zakres do przetworzenia przez cron. */
    public function backfill(): void
    {
        $this->writeGuard();
        $id = (int) ($_POST['connection_id'] ?? 0);
        try {
            $repository = $this->connections();
            $row = $repository->find($id);
            if (in_array($row['platform'], ['api'], true)) { throw new InvalidArgumentException('Własny sklep wysyła zamówienia sam – wyślij starsze zamówienia przez API.'); }
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($_POST['from_date'] ?? ''), new \DateTimeZone('Europe/Warsaw'));
            if (!$date) { throw new InvalidArgumentException('Wybierz datę początkową.'); }
            $from = $date->getTimestamp();
            if ($from > time()) { throw new InvalidArgumentException('Data nie może być z przyszłości.'); }
            if ($from < strtotime('-3 years')) { throw new InvalidArgumentException('Można pobrać zamówienia maksymalnie z ostatnich 3 lat.'); }
            $account = $repository->account($id);
            if (!$account) { throw new InvalidArgumentException('Połączenie nie ma konta importu.'); }
            $this->db()->update('om_accounts', ['import_from' => gmdate('Y-m-d H:i:s', $from), 'cursor_json' => null, 'synced_until' => null, 'next_attempt' => 0, 'last_error' => null, 'enabled' => 1], 'id=:id', ['id' => $account['id']]);
            $this->setFlash('success', 'Zaplanowano import zamówień od '.$date->format('d.m.Y').'. Zadanie cron pobierze je w kolejnych przebiegach.');
            $this->back('&connection='.$id.'#sc-connection-'.$id);
        } catch (\Throwable $e) {
            $this->setFlash('error', $this->safeError($e, 'backfill'));
            $this->back('&connection='.$id.'#sc-connection-'.$id);
        }
    }

    /** Jednorazowy import wskazanego połączenia na żądanie użytkownika. */
    public function fetchnow(): void
    {
        $this->writeGuard();
        $id = (int) ($_POST['connection_id'] ?? 0);
        try {
            $repository = $this->connections();
            $row = $repository->find($id);
            if ($row['platform'] === 'api' || $row['status'] !== 'active') { throw new InvalidArgumentException('To połączenie nie może teraz pobrać zamówień.'); }
            $account = $repository->account($id);
            if (!$account) { throw new InvalidArgumentException('Połączenie nie ma konta importu.'); }
            $sync = new OrderSyncService(new OrderRepository($this->db()));
            $results = $sync->sync(true, (int) $account['id']);
            $result = $results[0] ?? null;
            if (!$result) { throw new InvalidArgumentException('Nie uruchomiono importu tego konta.'); }
            if (empty($result['error'])) { $sync->repairImages(40); }
            $this->setFlash(!empty($result['error']) ? 'error' : 'success', 'Import '.$row['name'].': '.$result['message']);
        } catch (\Throwable $e) {
            $this->setFlash('error', $this->safeError($e, 'fetch_now'));
        }
        $this->back('&connection='.$id.'#sc-connection-'.$id);
    }

    public function disconnect(): void
    {
        $this->writeGuard();
        try {
            $this->connections()->delete((int) ($_POST['connection_id'] ?? 0));
            $this->setFlash('success', 'Odłączono konto. Pobrane wcześniej zamówienia zostały zachowane.');
        } catch (\Throwable $e) {
            $this->setFlash('error', $this->safeError($e, 'disconnect'));
        }
        $this->back();
    }

    /** Token API własnego sklepu: nowe połączenie albo rotacja istniejącego (stary token przestaje działać). */
    public function apitoken(): void
    {
        $this->writeGuard();
        try {
            $repository = $this->connections();
            $token = Tenant::tokenPrefix().rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $id = (int) ($_POST['connection_id'] ?? 0);
            if ($id > 0) {
                if ($repository->find($id)['platform'] !== 'api') { throw new InvalidArgumentException('To połączenie nie jest API sklepu.'); }
                $repository->update($id, ['token_hash' => hash('sha256', $token), 'public' => ['token_hint' => substr($token, -4)], 'status' => 'active', 'checked' => true]);
            } else {
                $name = trim((string) ($_POST['name'] ?? '')) ?: 'Własny sklep';
                $id = $repository->create('api', $name, ['token_hint' => substr($token, -4)], [], 'active', hash('sha256', $token));
            }
            $this->ensureSessionStarted();
            $_SESSION['sc_api_token'] = ['id' => $id, 'token' => $token];
            $this->setFlash('success', 'Utworzono token API. Skopiuj go teraz – nie pokażemy go ponownie.');
            $this->back('&connection='.$id.'#sc-connection-'.$id);
        } catch (\Throwable $e) {
            $this->setFlash('error', $this->safeError($e, 'api_token'));
            $this->back('&add=api');
        }
    }

    /**
     * Zapisuje dane aplikacji Allegro z panelu (bez edycji plików). Operator platformy
     * udostępnia logowanie wszystkim firmom; inna firma może użyć własnej aplikacji.
     */
    public function allegroapp(): void
    {
        $user = $this->writeGuard();
        try {
            $clientId = trim((string) ($_POST['client_id'] ?? ''));
            $clientSecret = trim((string) ($_POST['client_secret'] ?? ''));
            if (!preg_match('/^[A-Za-z0-9_-]{8,100}$/D', $clientId) || strlen($clientSecret) < 8 || strlen($clientSecret) > 300) {
                throw new InvalidArgumentException('Wklej Client ID i Client Secret skopiowane z apps.developer.allegro.pl.');
            }
            (new AllegroService(true))->verifyApp($clientId, $clientSecret);
            $value = ['client_id' => $clientId, 'secret' => \App\Services\OrderSecretBox::encrypt(['client_secret' => $clientSecret]), 'saved_by' => (string) ($user['email'] ?? ''), 'saved_at' => gmdate('c')];
            $saas = $this->saas();
            if ($saas->isPlatformOperator($user) && !empty($_POST['all_companies'])) {
                $saas->saveSetting('allegro_app', $value);
                $this->setFlash('success', 'Aplikacja Allegro zweryfikowana. Logowanie Allegro działa teraz dla wszystkich firm – kliknij „Zaloguj przez Allegro”.');
            } else {
                (new OrderRepository($this->db()))->saveSetting('allegro_app', $value);
                $this->setFlash('success', 'Aplikacja Allegro zweryfikowana i zapisana dla Twojej firmy. Kliknij „Zaloguj przez Allegro”.');
            }
        } catch (\Throwable $e) {
            $message = $e instanceof InvalidArgumentException ? $e->getMessage() : (strpos($e->getMessage(), '[401]') !== false || strpos($e->getMessage(), '[400]') !== false ? 'Allegro odrzuciło Client ID lub Client Secret. Skopiuj je ponownie z apps.developer.allegro.pl (bez spacji).' : $this->safeError($e, 'allegro_app'));
            $this->setFlash('error', $message);
        }
        $this->back('&add=allegro');
    }

    public function allegroconnect(): void
    {
        $user = $this->writeGuard();
        if (!AllegroService::configured()) {
            $this->setFlash('error', 'Najpierw uzupełnij dane aplikacji Allegro w karcie Allegro.');
            $this->back('&add=allegro');
        }
        $state = bin2hex(random_bytes(24));
        $_SESSION['sc_allegro_oauth'] = ['state' => $state, 'tenant' => (int) $user['tenant_id'], 'expires' => time() + 900];
        $this->redirect(AllegroService::authorizationUrl($state, self::appUrl('allegro-callback.php')));
    }

    public function allegrocallback(): void
    {
        $user = $this->requireModuleWrite('orders');
        $pending = $_SESSION['sc_allegro_oauth'] ?? null;
        unset($_SESSION['sc_allegro_oauth']);
        try {
            if (isset($_GET['error'])) { throw new InvalidArgumentException('Allegro nie udzieliło dostępu ('.preg_replace('/[^a-z_]/i', '', (string) $_GET['error']).'). Spróbuj ponownie i kliknij „Zezwól”.'); }
            if (!is_array($pending) || $pending['expires'] < time() || (int) $pending['tenant'] !== (int) $user['tenant_id'] || !hash_equals((string) $pending['state'], (string) ($_GET['state'] ?? ''))) {
                throw new InvalidArgumentException('Sesja logowania Allegro wygasła. Kliknij „Zaloguj przez Allegro” jeszcze raz.');
            }
            $redirectUri = self::appUrl('allegro-callback.php');
            $result = (new AllegroService(true))->exchangeCode((string) ($_GET['code'] ?? ''), $redirectUri);
            $repository = $this->connections();
            $existing = $result['remote_id'] !== '' ? $repository->findByRemoteId('allegro', $result['remote_id']) : null;
            if ($existing) {
                $repository->update((int) $existing['id'], ['secret' => $result['secret'], 'public' => $result['public'], 'status' => 'active', 'last_error' => null, 'checked' => true]);
                $id = (int) $existing['id'];
                $this->setFlash('success', 'Odnowiono połączenie z kontem Allegro „'.$result['public']['login'].'”.');
            } else {
                $id = $repository->create('allegro', $result['name'], $result['public'], $result['secret']);
                $this->setFlash('success', 'Połączono konto Allegro „'.$result['public']['login'].'”. Import ostatnich 7 dni ruszy automatycznie.');
            }
            $this->back('&connection='.$id.'#sc-connection-'.$id);
        } catch (\Throwable $e) {
            $this->setFlash('error', $this->safeError($e, 'allegro_oauth'));
            $this->back('&add=allegro');
        }
    }

    /** WooCommerce: zapisuje oczekujące połączenie i przekierowuje do ekranu zatwierdzenia w sklepie. */
    public function woostart(): void
    {
        $this->writeGuard();
        try {
            $shopUrl = Http::normalizeShopUrl((string) ($_POST['shop_url'] ?? ''));
            $callback = self::appUrl('woocommerce-callback.php');
            if (stripos($callback, 'https://') !== 0) { throw new InvalidArgumentException('Logowanie WooCommerce działa tylko, gdy SalesCenter jest otwarte przez https://. Użyj „Połącz kluczami”.'); }
            $userId = Tenant::tokenPrefix().bin2hex(random_bytes(16));
            $repository = $this->connections();
            $name = trim((string) ($_POST['name'] ?? '')) ?: 'WooCommerce · '.(parse_url($shopUrl, PHP_URL_HOST) ?: 'sklep');
            $repository->create('woocommerce', $name, ['shop_url' => $shopUrl, 'remote_id' => $shopUrl], [], 'pending', hash('sha256', $userId));
            $this->redirect(WooCommerceService::authorizeUrl($shopUrl, $userId, self::appUrl('index.php?controller=integrations&action=wooreturn'), $callback));
        } catch (\Throwable $e) {
            $this->setFlash('error', $this->safeError($e, 'woo_start'));
            $this->back('&add=woocommerce');
        }
    }

    /** Wywołanie serwer–serwer ze sklepu WooCommerce z kluczami REST (bez sesji użytkownika). */
    public function woocallback(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $payload = json_decode((string) file_get_contents('php://input'), true);
        $userId = is_array($payload) ? (string) ($payload['user_id'] ?? '') : '';
        $tenantId = Tenant::idFromToken($userId);
        if ($tenantId === null || !$this->saas()->tenant($tenantId) || empty($payload['consumer_key']) || empty($payload['consumer_secret'])) {
            http_response_code(400); echo '{"error":"invalid"}'; return;
        }
        Tenant::activate($tenantId);
        try {
            $repository = $this->connections();
            $row = $repository->findByTokenHash(hash('sha256', $userId));
            if (!$row || $row['platform'] !== 'woocommerce' || $row['status'] !== 'pending') { http_response_code(404); echo '{"error":"unknown"}'; return; }
            $secret = ['consumer_key' => (string) $payload['consumer_key'], 'consumer_secret' => (string) $payload['consumer_secret']];
            $credentials = $repository->credentials($row);
            $public = [];
            try {
                $test = (new WooCommerceService())->testConnection(array_diff_key($credentials, ['connection_id' => 1]) + $secret);
                $public = (array) ($test['public'] ?? []);
            } catch (\Throwable $testError) { /* Klucze zapisujemy; błąd testu pokaże karta połączenia. */ }
            $repository->update((int) $row['id'], ['secret' => $secret, 'public' => $public + ['key_permissions' => (string) ($payload['key_permissions'] ?? '')], 'status' => 'active', 'token_hash' => null, 'checked' => true]);
            echo '{"ok":true}';
        } catch (\Throwable $e) {
            OrderSyncError::log($e, ['stage' => 'woo_callback']);
            http_response_code(500); echo '{"error":"failed"}';
        } finally {
            Tenant::clear();
        }
    }

    public function wooreturn(): void
    {
        $this->requireModuleWrite('orders');
        if ((string) ($_GET['success'] ?? '') !== '1') {
            $this->setFlash('error', 'Połączenie WooCommerce zostało anulowane w sklepie.');
            $this->back('&add=woocommerce');
        }
        $pending = $this->db()->fetch("SELECT id,status FROM om_connections WHERE platform='woocommerce' ORDER BY updated_at DESC,id DESC LIMIT 1");
        if ($pending && $pending['status'] === 'active') {
            $this->setFlash('success', 'Połączono sklep WooCommerce. Import ostatnich 7 dni ruszy automatycznie.');
            $this->back('&connection='.(int) $pending['id'].'#sc-connection-'.(int) $pending['id']);
        }
        $this->setFlash('error', 'Sklep zatwierdził dostęp, ale nie przekazał kluczy do SalesCenter (często blokuje to firewall lub wtyczka bezpieczeństwa). Użyj „Połącz kluczami” – instrukcja na karcie WooCommerce.');
        $this->back('&add=woocommerce&manual=1');
    }

    private function safeError(\Throwable $e, string $stage): string
    {
        if ($e instanceof InvalidArgumentException) { return $e->getMessage(); }
        $diagnostic = OrderSyncError::log($e, ['stage' => 'integration_'.$stage]);
        $reason = mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $e->getMessage())), 0, 260, 'UTF-8');
        $hint = strpos($reason, '[401]') !== false || strpos($reason, '[403]') !== false ? ' Sprawdź, czy dane dostępowe są aktualne i mają wymagane uprawnienia.' : '';
        return 'Nie udało się połączyć: '.$reason.$hint.' [ID: '.$diagnostic['reference'].']';
    }
}
