<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\TaskboardRepository;
use App\Models\UserRepository;
use App\Models\SettingRepository;
use App\Services\JwtService;

abstract class Controller
{
    /** @var bool */
    private $currentUserResolved = false;

    /** @var array|null */
    private $currentUserCache = null;

    protected function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
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

    protected function isPost(): bool
    {
        return strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET') === 'POST';
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
        $candidates = array(
            isset($_SERVER['HTTP_AUTHORIZATION']) ? (string) $_SERVER['HTTP_AUTHORIZATION'] : '',
            isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) ? (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] : '',
        );

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    protected function bearerToken(): string
    {
        $header = $this->authorizationHeader();
        if ($header === '') {
            return '';
        }

        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches) !== 1) {
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

    protected function currentUser()
    {
        if ($this->currentUserResolved) {
            return $this->currentUserCache;
        }

        $this->currentUserResolved = true;
        $config = Config::get('app');
        $cookieName = (string) $config['jwt_cookie'];

        if (empty($_COOKIE[$cookieName])) {
            $this->currentUserCache = null;
            return null;
        }

        $jwt = new JwtService();
        $payload = $jwt->validate($_COOKIE[$cookieName]);

        if (!is_array($payload) || empty($payload['user_id'])) {
            $this->currentUserCache = null;
            return null;
        }

        $users = new UserRepository($this->db());
        $users->ensureSchema();
        $user = $users->findById((int) $payload['user_id']);

        if (!$user || (int) $user['is_blocked'] === 1) {
            $this->currentUserCache = null;
            return null;
        }

        $user['module_permissions'] = $users->modulePermissionsForUser((int) $user['id']);
        $user['modules'] = array_keys($user['module_permissions']);
        $this->currentUserCache = $user;
        return $this->currentUserCache;
    }

    protected function requireAuth(): array
    {
        $user = $this->currentUser();

        if (!$user) {
            $this->setFlash('error', 'Zaloguj sie, aby kontynuowac.');
            $this->redirect('./index.php?controller=auth&action=login');
        }

        return $user;
    }

    protected function requireRole($role)
    {
        $user = $this->requireAuth();

        if (!isset($user['role']) || $user['role'] !== $role) {
            http_response_code(403);
            exit('Brak uprawnien.');
        }

        return $user;
    }

    protected function requireModule($module)
    {
        $user = $this->requireAuth();
        $accessLevel = $this->moduleAccessLevel($user, (string) $module);

        if (($user['role'] !== 'admin') && $accessLevel === 'none') {
            http_response_code(403);
            exit('Brak dostepu do modulu.');
        }

        return $user;
    }

    protected function requireWriteAccess(): array
    {
        $user = $this->requireAuth();
        $permissionLevel = strtolower(trim((string) ($user['permission_level'] ?? 'edit')));

        if ($permissionLevel === 'read') {
            http_response_code(403);
            exit('To konto ma dostep tylko do odczytu.');
        }

        return $user;
    }

    protected function requireModuleWrite($module)
    {
        $user = $this->requireModule($module);
        $accessLevel = $this->moduleAccessLevel($user, (string) $module);

        if (($user['role'] !== 'admin') && $accessLevel !== 'edit') {
            http_response_code(403);
            exit('To konto ma dostep do tego modulu tylko w trybie odczytu.');
        }

        return $user;
    }

    protected function moduleAccessLevel(array $user, string $module): string
    {
        if ((string) ($user['role'] ?? '') === 'admin') {
            return 'edit';
        }

        $modulePermissions = isset($user['module_permissions']) && is_array($user['module_permissions'])
            ? $user['module_permissions']
            : array();
        $moduleCode = strtolower(trim($module));
        $accessLevel = strtolower(trim((string) ($modulePermissions[$moduleCode] ?? 'none')));

        if ($accessLevel === 'edit') {
            return 'edit';
        }

        if ($accessLevel === 'read') {
            return 'read';
        }

        return 'none';
    }

    protected function requireApiModuleAccess(string $module): void
    {
        $config = Config::get('app');
        $configuredToken = trim((string) ($config['api_bearer_token'] ?? ''));
        $storedToken = '';

        try {
            $settings = new SettingRepository($this->db());
            $settings->ensureSchema();
            $storedToken = trim($settings->get('api_bearer_token', ''));
        } catch (\Throwable $exception) {
            $storedToken = '';
        }

        if ($storedToken !== '') {
            $configuredToken = $storedToken;
        }

        $providedToken = $this->bearerToken();

        if ($configuredToken !== '' && $providedToken !== '') {
            if (hash_equals($configuredToken, $providedToken)) {
                return;
            }

            $this->apiErrorResponse('Nieprawidlowy token API.', 403);
        }

        $user = $this->currentUser();
        if ($user !== null) {
            $accessLevel = $this->moduleAccessLevel($user, $module);
            if ((string) ($user['role'] ?? '') === 'admin' || $accessLevel !== 'none') {
                return;
            }

            $this->apiErrorResponse('Brak dostepu do modulu.', 403);
        }

        if ($configuredToken === '') {
            $this->apiErrorResponse('API token nie jest skonfigurowany. Ustaw app.api_bearer_token.', 503);
        }

        $this->apiErrorResponse('Brak autoryzacji API.', 401);
    }

    protected function render(string $template, array $data = array()): void
    {
        $smarty = SmartyFactory::create();
        $appConfig = Config::get('app');
        $currentUser = array_key_exists('currentUser', $data)
            ? $data['currentUser']
            : $this->currentUser();
        $flashSuccess = array_key_exists('flashSuccess', $data)
            ? $data['flashSuccess']
            : $this->getFlash('success');
        $flashError = array_key_exists('flashError', $data)
            ? $data['flashError']
            : $this->getFlash('error');
        $taskboardMenu = $this->taskboardMenuData(is_array($currentUser) ? $currentUser : null, $data);

        // Flash messages are already loaded, so we can release the session lock
        // before template rendering to avoid blocking parallel browser requests.
        $this->releaseSessionLock();

        $defaultData = array(
            'assetBase' => 'dist',
            'appName' => 'ALTREO CRM',
            'currentYear' => date('Y'),
            'baseUrl' => (string) $appConfig['base_url'],
            'currentController' => strtolower(isset($_GET['controller']) ? (string) $_GET['controller'] : 'index'),
            'currentAction' => strtolower(isset($_GET['action']) ? (string) $_GET['action'] : 'index'),
            'flashSuccess' => $flashSuccess,
            'flashError' => $flashError,
            'currentUser' => $currentUser,
            'taskboardMenuBoards' => $taskboardMenu['boards'],
            'taskboardMenuCanWrite' => $taskboardMenu['can_write'],
            'taskboardMenuSelectedBoardId' => $taskboardMenu['selected_board_id'],
        );

        foreach (array_merge($defaultData, $data) as $key => $value) {
            $smarty->assign($key, $value);
        }

        $smarty->display('layout/header.tpl');
        $smarty->display($template . '.tpl');
        $smarty->display('layout/footer.tpl');
    }

    private function apiErrorResponse(string $message, int $status): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('error' => $message));
        exit;
    }

    private function taskboardMenuData(?array $currentUser, array $data): array
    {
        $result = array(
            'boards' => array(),
            'can_write' => false,
            'selected_board_id' => isset($data['selectedBoardId']) ? (int) $data['selectedBoardId'] : (int) ($_GET['board_id'] ?? 0),
        );

        if ($currentUser === null) {
            return $result;
        }

        $accessLevel = $this->moduleAccessLevel($currentUser, 'taskboard');
        if ((string) ($currentUser['role'] ?? '') !== 'admin' && $accessLevel === 'none') {
            return $result;
        }

        $result['can_write'] = $accessLevel === 'edit';

        if (isset($data['boards']) && is_array($data['boards'])) {
            $result['boards'] = $this->sortTaskboardMenuBoards($data['boards']);
            return $result;
        }

        try {
            $taskboard = new TaskboardRepository($this->db());
            $taskboard->ensureSchema();
            $result['boards'] = $this->sortTaskboardMenuBoards($taskboard->boards(true));
        } catch (\Throwable $exception) {
            $result['boards'] = array();
        }

        return $result;
    }

    private function sortTaskboardMenuBoards(array $boards): array
    {
        usort($boards, static function (array $left, array $right): int {
            $leftArchived = (int) ($left['is_archived'] ?? 0);
            $rightArchived = (int) ($right['is_archived'] ?? 0);
            if ($leftArchived !== $rightArchived) {
                return $leftArchived <=> $rightArchived;
            }

            $leftCreated = (string) ($left['created_at'] ?? '');
            $rightCreated = (string) ($right['created_at'] ?? '');
            if ($leftCreated !== $rightCreated) {
                return strcmp($leftCreated, $rightCreated);
            }

            return (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0);
        });

        return $boards;
    }
}
