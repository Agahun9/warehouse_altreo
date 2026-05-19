<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\SettingRepository;
use App\Models\UserRepository;
use App\Services\AllegroService;
use App\Services\EmpikService;
use App\Services\ErliService;
use RuntimeException;
use Throwable;

class AdministrationController extends Controller
{
    /** @var UserRepository */
    private $users;

    /** @var AllegroService */
    private $allegro;

    /** @var SettingRepository */
    private $settings;

    /** @var EmpikService */
    private $empik;

    /** @var ErliService */
    private $erli;

    public function __construct()
    {
        $this->users = new UserRepository($this->db());
        $this->users->ensureSchema();
        $this->allegro = new AllegroService();
        $this->empik = new EmpikService();
        $this->erli = new ErliService();
        $this->settings = new SettingRepository($this->db());
        $this->settings->ensureSchema();
    }

    public function users(): void
    {
        $currentUser = $this->requireRole('admin');
        $flashSuccess = $this->getFlash('success');
        $flashError = $this->getFlash('error');
        $this->releaseSessionLock();
        $users = $this->users->allUsers();
        $modules = $this->users->availableModules();

        foreach ($users as $index => $user) {
            $users[$index]['modules'] = $this->users->modulesForUser((int) $user['id']);
            $users[$index]['module_permissions'] = $this->users->modulePermissionsForUser((int) $user['id']);
        }

        $this->render('administration/users', array(
            'pageTitle' => 'Administracja',
            'contentTitle' => 'Uzytkownicy',
            'pageDescription' => 'Zarzadzaj kontami, rolami i modulami.',
            'breadcrumbCurrent' => 'Uzytkownicy',
            'currentUser' => $currentUser,
            'flashSuccess' => $flashSuccess,
            'flashError' => $flashError,
            'users' => $users,
            'modules' => $modules,
            'currentAdminId' => (int) $currentUser['id'],
        ));
    }

    public function updateUser(): void
    {
        $this->requireRole('admin');
        $this->requireWriteAccess();

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=administration&action=users');
        }

        try {
            $id = (int) $this->input('id', 0);
            $user = $this->users->findById($id);
            if (!$user) {
                throw new RuntimeException('Nie znaleziono uzytkownika.');
            }

            $role = (string) $this->input('role', 'user');
            $firstName = trim((string) $this->input('first_name', ''));
            $lastName = trim((string) $this->input('last_name', ''));
            $password = (string) $this->input('new_password', '');
            $permissionLevel = (string) $this->input('permission_level', 'edit');
            $loaderEnabled = $this->input('loader_enabled', '1') === '1' ? 1 : 0;
            $isActive = $this->input('is_active', '0') === '1' ? 1 : 0;
            $blocked = $this->input('is_blocked', '0') === '1' ? 1 : 0;
            $modulePermissions = $this->input('module_permissions', array());
            if (!is_array($modulePermissions)) {
                $modulePermissions = array();
            }

            $update = array(
                'role' => ($role === 'admin' ? 'admin' : 'user'),
                'first_name' => ($firstName !== '' ? $firstName : null),
                'last_name' => ($lastName !== '' ? $lastName : null),
                'permission_level' => ($permissionLevel === 'read' ? 'read' : 'edit'),
                'loader_enabled' => $loaderEnabled,
                'is_active' => $isActive,
                'is_blocked' => $blocked,
            );

            if ($password !== '') {
                if (strlen($password) < 8) {
                    throw new RuntimeException('Nowe haslo musi miec minimum 8 znakow.');
                }

                $update['password_hash'] = password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
            }

            $this->users->updateUser($id, $update);
            $this->users->replaceModulePermissions($id, $modulePermissions);
            $this->setFlash('success', 'Dane uzytkownika zostaly zaktualizowane.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=administration&action=users');
    }

    public function automation(): void
    {
        $currentUser = $this->requireRole('admin');
        $flashSuccess = $this->getFlash('success');
        $flashError = $this->getFlash('error');
        $this->releaseSessionLock();

        $baseUrl = $this->absoluteBaseUrl();
        $accounts = $this->allegro->listAccounts();
        $empikAccounts = $this->empik->listAccounts();
        $erliAccounts = $this->erli->listAccounts();

        foreach ($accounts as &$account) {
            $account['trigger_url'] = $this->allegro->triggerUrl($account, $baseUrl);
            $account['auto_end_offers'] = rtrim($baseUrl, '?&')
                . '?controller=allegro&action=autoendoffers&format=json&account='
                . rawurlencode((string) ($account['slug'] ?? ''));
            $account['auto_end_offers_mail_example'] = (string) $account['auto_end_offers'] . '&mail_to=twoj%40adres.pl';
        }
        unset($account);

        $this->render('administration/automation', array(
            'pageTitle' => 'Administracja',
            'contentTitle' => 'Administracja',
            'pageDescription' => 'Konta marketplace, integracje, crony i maintenance w jednym uporzadkowanym miejscu.',
            'breadcrumbCurrent' => 'Administracja',
            'currentUser' => $currentUser,
            'flashSuccess' => $flashSuccess,
            'flashError' => $flashError,
            'automation' => $this->allegro->automationLinks($baseUrl),
            'queueStats' => $this->allegro->queueCounts(),
            'accounts' => $accounts,
            'empikAccounts' => $empikAccounts,
            'erliAccounts' => $erliAccounts,
            'defaultRedirectUri' => $baseUrl . '?controller=allegro&action=callback',
            'sellasistBaseUrl' => $this->settings->get('sellasist_base_url', 'https://altreo.sellasist.pl'),
            'sellasistApiKey' => $this->settings->get('sellasist_api_key', ''),
            'sellasistPickingStatusId' => (int) $this->settings->get('sellasist_picking_status_id', '23'),
            'sellasistPrintedStatusId' => (int) $this->settings->get('sellasist_printed_status_id', '3'),
            'apiBearerToken' => $this->settings->get('api_bearer_token', ''),
            'apiBaseUrl' => $this->apiBaseUrl(),
        ));
    }

    public function allegroaccounts(): void
    {
        $this->requireRole('admin');
        $this->redirect('./index.php?controller=administration&action=automation');
    }

    public function cleanup(): void
    {
        $this->requireRole('admin');
        $this->requireWriteAccess();

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=administration&action=automation');
        }

        try {
            $queueDoneDays = max(0, (int) $this->input('queue_done_days', 14));
            $queueErrorDays = max(0, (int) $this->input('queue_error_days', 30));
            $deletedProductsDays = max(0, (int) $this->input('deleted_products_days', 30));

            $result = $this->allegro->cleanupStorage($queueDoneDays, $queueErrorDays, $deletedProductsDays);

            $summary = array(
                'usunieto zakonczone wpisy kolejki: ' . (int) ($result['queue_done_deleted'] ?? 0),
                'usunieto bledne/stare retry z kolejki: ' . (int) ($result['queue_error_deleted'] ?? 0),
                'odpielo oferty od usunietych produktow: ' . (int) ($result['offers_detached_from_deleted_products'] ?? 0),
                'usunieto produkty oznaczone jako skasowane: ' . (int) ($result['products_deleted'] ?? 0),
                'usunieto logi zmian produktow: ' . (int) ($result['change_logs_deleted'] ?? 0),
            );

            $this->setFlash('success', 'Sprzatanie bazy zakonczone, ' . implode('; ', $summary) . '.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=administration&action=automation');
    }

    public function savesellasist(): void
    {
        $this->requireRole('admin');
        $this->requireWriteAccess();

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=administration&action=automation');
        }

        try {
            $baseUrl = rtrim(trim((string) $this->input('sellasist_base_url', 'https://altreo.sellasist.pl')), '/');
            $apiKey = trim((string) $this->input('sellasist_api_key', ''));
            $pickingStatusId = max(1, (int) $this->input('sellasist_picking_status_id', 23));
            $printedStatusId = max(0, (int) $this->input('sellasist_printed_status_id', 3));

            if ($baseUrl === '') {
                $baseUrl = 'https://altreo.sellasist.pl';
            }

            $baseUrl = preg_replace('#/api(?:/v1)?$#i', '', $baseUrl);

            $this->settings->set('sellasist_base_url', $baseUrl);
            $this->settings->set('sellasist_api_key', $apiKey);
            $this->settings->set('sellasist_picking_status_id', (string) $pickingStatusId);
            $this->settings->set('sellasist_printed_status_id', (string) $printedStatusId);

            $this->setFlash('success', 'Ustawienia Sellasist zostaly zapisane.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=administration&action=automation');
    }

    public function saveempik(): void
    {
        $this->requireRole('admin');
        $this->requireWriteAccess();

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=administration&action=automation');
        }

        try {
            $accountId = (int) $this->input('account_id', 0);
            $this->empik->saveAccount(array(
                'name' => $this->input('name', ''),
                'api_url' => $this->input('api_url', ''),
                'api_key' => $this->input('api_key', ''),
                'shop_id' => $this->input('shop_id', ''),
                'locale' => $this->input('locale', 'pl_PL'),
                'is_active' => $this->input('is_active', '0') === '1' ? 1 : 0,
            ), $accountId > 0 ? $accountId : null);

            $this->setFlash('success', 'Ustawienia Empik API zostaly zapisane.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=administration&action=automation');
    }

    public function saveerli(): void
    {
        $this->requireRole('admin');
        $this->requireWriteAccess();

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=administration&action=automation');
        }

        try {
            $accountId = (int) $this->input('account_id', 0);
            $this->erli->saveAccount(array(
                'name' => $this->input('name', ''),
                'api_url' => $this->input('api_url', ''),
                'api_key' => $this->input('api_key', ''),
                'default_price_list_tag' => $this->input('default_price_list_tag', ''),
                'default_dispatch_days' => $this->input('default_dispatch_days', '1'),
                'default_weight_g' => $this->input('default_weight_g', ''),
                'is_active' => $this->input('is_active', '0') === '1' ? 1 : 0,
            ), $accountId > 0 ? $accountId : null);

            $this->setFlash('success', 'Ustawienia Erli API zostaly zapisane.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=administration&action=automation');
    }

    public function saveapi(): void
    {
        $this->requireRole('admin');
        $this->requireWriteAccess();

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=administration&action=automation');
        }

        try {
            $token = trim((string) $this->input('api_bearer_token', ''));

            if ($token !== '' && strlen($token) < 16) {
                throw new RuntimeException('Token API powinien miec co najmniej 16 znakow.');
            }

            $this->settings->set('api_bearer_token', $token);

            if ($token === '') {
                $this->setFlash('success', 'Token API zostal wyczyszczony.');
            } else {
                $this->setFlash('success', 'Token API zostal zapisany.');
            }
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=administration&action=automation');
    }

    public function deleteUser(): void
    {
        $admin = $this->requireRole('admin');
        $this->requireWriteAccess();

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=administration&action=users');
        }

        try {
            $id = (int) $this->input('id', 0);
            if ($id <= 0) {
                throw new RuntimeException('Niepoprawne ID uzytkownika.');
            }

            if ((int) $admin['id'] === $id) {
                throw new RuntimeException('Nie mozna usunac wlasnego konta.');
            }

            $user = $this->users->findById($id);
            if (!$user) {
                throw new RuntimeException('Nie znaleziono uzytkownika.');
            }

            if ((string) $user['role'] === 'admin' && $this->users->countAdmins() <= 1) {
                throw new RuntimeException('Nie mozna usunac ostatniego konta admina.');
            }

            $deleted = $this->users->deleteUserById($id);
            if ($deleted <= 0) {
                throw new RuntimeException('Nie udalo sie usunac uzytkownika.');
            }

            $this->setFlash('success', 'Uzytkownik zostal usuniety.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=administration&action=users');
    }

    private function absoluteBaseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');

        return $scheme . '://' . $host . $script;
    }

    private function apiBaseUrl(): string
    {
        $appConfig = \App\Core\Config::get('app');
        $publicBaseUrl = trim((string) ($appConfig['public_base_url'] ?? ''));

        if ($publicBaseUrl !== '') {
            return rtrim(str_replace('\\', '/', dirname($publicBaseUrl)), '/');
        }

        return rtrim(str_replace('\\', '/', dirname($this->absoluteBaseUrl())), '/');
    }
}
