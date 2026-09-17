<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\SaasRepository;

abstract class Controller
{
    /** @var bool */
    private $currentUserResolved = false;

    /** @var array|null */
    private $currentUserCache = null;

    protected function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
                || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
            session_name('SALESCENTER');
            session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
            ini_set('session.use_strict_mode', '1');
            session_start();
        }
    }

    protected function releaseSessionLock(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    protected function db(): Database
    {
        return Database::instance();
    }

    protected function saas(): SaasRepository
    {
        $repository = new SaasRepository($this->db());
        $repository->ensureSchema();
        return $repository;
    }

    protected function isPost(): bool
    {
        return $this->requestMethod() === 'POST';
    }

    protected function requestMethod(): string
    {
        return strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET');
    }

    protected function input(string $key, $default = null)
    {
        if (isset($_POST[$key])) {
            return $_POST[$key];
        }

        if (isset($_GET[$key])) {
            return $_GET[$key];
        }

        return $default;
    }

    protected function authorizationHeader(): string
    {
        foreach (array($_SERVER['HTTP_AUTHORIZATION'] ?? '', $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '') as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    protected function bearerToken(): string
    {
        if (preg_match('/^Bearer\s+(.+)$/i', $this->authorizationHeader(), $matches) !== 1) {
            return '';
        }

        return trim((string) $matches[1]);
    }

    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    protected function setFlash(string $type, string $message): void
    {
        $this->ensureSessionStarted();
        $_SESSION['flash'][$type] = $message;
    }

    protected function getFlash(string $type)
    {
        $this->ensureSessionStarted();

        if (!isset($_SESSION['flash'][$type])) {
            return null;
        }

        $message = (string) $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);

        return $message;
    }

    /** Wspólny token CSRF sesji (ta sama wartość co w centrum zamówień). */
    protected function csrfToken(): string
    {
        $this->ensureSessionStarted();
        if (empty($_SESSION['orders_csrf'])) {
            $_SESSION['orders_csrf'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['orders_csrf'];
    }

    protected function requireCsrf(): void
    {
        if (!$this->isPost()) {
            http_response_code(405);
            exit('Wymagany POST.');
        }
        if (!hash_equals($this->csrfToken(), (string) ($_POST['csrf'] ?? ''))) {
            http_response_code(403);
            exit('Sesja formularza wygasła. Odśwież stronę.');
        }
    }

    /** Zapisuje zalogowanie w nowej sesji i odcisk hasła (zmiana hasła wylogowuje inne sesje). */
    protected function loginUser(array $user): void
    {
        $this->ensureSessionStarted();
        session_regenerate_id(true);
        $_SESSION = array(
            'user_id' => (int) $user['id'],
            'tenant_id' => (int) $user['tenant_id'],
            'auth_fingerprint' => $this->authFingerprint($user),
            'orders_csrf' => bin2hex(random_bytes(32)),
        );
    }

    protected function logoutUser(): void
    {
        $this->ensureSessionStarted();
        $_SESSION = array();
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', array('expires' => time() - 42000, 'path' => $params['path'], 'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Lax'));
        }
        session_destroy();
        Tenant::clear();
    }

    private function authFingerprint(array $user): string
    {
        return hash('sha256', (string) $user['id'] . '|' . (string) $user['tenant_id'] . '|' . (string) $user['password_hash']);
    }

    /**
     * Zalogowany użytkownik wraz z firmą. Aktywuje kontekst firmy (Tenant),
     * dzięki czemu wszystkie zapytania centrum zamówień trafiają do jej tabel.
     */
    protected function currentUser()
    {
        if ($this->currentUserResolved) {
            return $this->currentUserCache;
        }

        $this->currentUserResolved = true;
        $this->ensureSessionStarted();
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId < 1) {
            return null;
        }

        $saas = $this->saas();
        $user = $saas->findUserById($userId);
        $tenant = $user ? $saas->tenant((int) $user['tenant_id']) : null;
        if (!$user || !$tenant || (int) $user['is_blocked'] === 1 || $tenant['status'] !== 'active'
            || (int) ($_SESSION['tenant_id'] ?? 0) !== (int) $user['tenant_id']
            || !hash_equals((string) ($_SESSION['auth_fingerprint'] ?? ''), $this->authFingerprint($user))) {
            $_SESSION = array();
            Tenant::clear();
            return null;
        }

        unset($user['password_hash']);
        $user['tenant'] = $tenant;
        $user['role_label'] = SaasRepository::ROLES[$user['role']] ?? $user['role'];
        $user['is_headmaster'] = $saas->isHeadmaster($user);
        $user['modules'] = array('orders');
        Tenant::activate((int) $tenant['id']);
        $this->currentUserCache = $user;

        return $this->currentUserCache;
    }

    protected function requireAuth(): array
    {
        $user = $this->currentUser();

        if (!$user) {
            $this->setFlash('error', 'Zaloguj się, aby kontynuować.');
            $this->redirect('./index.php?controller=auth&action=login');
        }

        return $user;
    }

    /** Właściciel lub administrator firmy (zespół, dane firmy). */
    protected function requireTenantAdmin(): array
    {
        $user = $this->requireAuth();

        if (!in_array((string) $user['role'], array('owner', 'admin'), true)) {
            http_response_code(403);
            exit('Tę sekcję może zmieniać tylko właściciel lub administrator firmy.');
        }

        return $user;
    }

    protected function requireModule($module)
    {
        $user = $this->requireAuth();

        if ($this->moduleAccessLevel($user, (string) $module) === 'none') {
            http_response_code(403);
            exit('Brak dostępu do modułu.');
        }

        return $user;
    }

    protected function requireModuleWrite($module)
    {
        $user = $this->requireModule($module);

        if ($this->moduleAccessLevel($user, (string) $module) !== 'edit') {
            http_response_code(403);
            exit('To konto ma dostęp do tego modułu tylko w trybie odczytu.');
        }

        return $user;
    }

    /** owner/admin: edycja; pracownik: poziom nadany w zespole (edit/read). */
    protected function moduleAccessLevel(array $user, string $module): string
    {
        if (strtolower(trim($module)) !== 'orders') {
            return 'none';
        }

        if (in_array((string) ($user['role'] ?? ''), array('owner', 'admin'), true)) {
            return 'edit';
        }

        return (string) ($user['access'] ?? '') === 'edit' ? 'edit' : 'read';
    }

    protected function render(string $template, array $data = array()): void
    {
        $smarty = SmartyFactory::create();
        $appConfig = Config::get('app');
        $currentUser = array_key_exists('currentUser', $data) ? $data['currentUser'] : $this->currentUser();
        $flashSuccess = array_key_exists('flashSuccess', $data) ? $data['flashSuccess'] : $this->getFlash('success');
        $flashError = array_key_exists('flashError', $data) ? $data['flashError'] : $this->getFlash('error');
        $csrf = $this->csrfToken();

        // Flash messages are already loaded, so we can release the session lock
        // before template rendering to avoid blocking parallel browser requests.
        $this->releaseSessionLock();

        $defaultData = array(
            'assetBase' => 'dist',
            'appName' => (string) ($appConfig['app_name'] ?? 'SalesCenter'),
            'currentYear' => date('Y'),
            'baseUrl' => (string) $appConfig['base_url'],
            'currentController' => strtolower(isset($_GET['controller']) ? (string) $_GET['controller'] : 'orders'),
            'currentAction' => strtolower(isset($_GET['action']) ? (string) $_GET['action'] : 'index'),
            'flashSuccess' => $flashSuccess,
            'flashError' => $flashError,
            'currentUser' => $currentUser,
            'layoutCsrf' => $csrf,
            'registrationEnabled' => !empty($appConfig['registration_enabled']),
        );

        foreach (array_merge($defaultData, $data) as $key => $value) {
            $smarty->assign($key, $value);
        }

        $smarty->display('layout/header.tpl');
        $smarty->display($template . '.tpl');
        $smarty->display('layout/footer.tpl');
    }
}
