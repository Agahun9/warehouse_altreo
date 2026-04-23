<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\SettingRepository;
use App\Models\UserRepository;
use App\Services\AllegroService;
use RuntimeException;
use Throwable;

class AdminController extends Controller
{
    /** @var UserRepository */
    private $users;

    /** @var AllegroService */
    private $allegro;

    /** @var SettingRepository */
    private $settings;

    public function __construct()
    {
        $this->users = new UserRepository($this->db());
        $this->users->ensureSchema();
        $this->allegro = new AllegroService();
        $this->settings = new SettingRepository($this->db());
        $this->settings->ensureSchema();
    }

    public function users(): void
    {
        $currentUser = $this->requireRole('admin');
        $users = $this->users->allUsers();
        $modules = $this->users->availableModules();

        foreach ($users as $index => $user) {
            $users[$index]['modules'] = $this->users->modulesForUser((int) $user['id']);
        }

        $this->render('admin/users', array(
            'pageTitle' => 'Panel admina',
            'contentTitle' => 'Uzytkownicy',
            'pageDescription' => 'Zarzadzaj kontami, rolami i modulami.',
            'breadcrumbCurrent' => 'Admin',
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
            $this->redirect('./index.php?controller=admin&action=users');
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
            $isActive = $this->input('is_active', '0') === '1' ? 1 : 0;
            $blocked = $this->input('is_blocked', '0') === '1' ? 1 : 0;
            $modules = $this->input('modules', array());
            if (!is_array($modules)) {
                $modules = array();
            }

            $update = array(
                'role' => ($role === 'admin' ? 'admin' : 'user'),
                'first_name' => ($firstName !== '' ? $firstName : null),
                'last_name' => ($lastName !== '' ? $lastName : null),
                'permission_level' => ($permissionLevel === 'read' ? 'read' : 'edit'),
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
            $this->users->replaceModules($id, $modules);
            $this->setFlash('success', 'Dane uzytkownika zostaly zaktualizowane.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=admin&action=users');
    }

    public function automation(): void
    {
        $this->requireRole('admin');

        $baseUrl = $this->absoluteBaseUrl();
        $accounts = $this->allegro->listAccounts();

        foreach ($accounts as &$account) {
            $account['trigger_url'] = $this->allegro->triggerUrl($account, $baseUrl);
        }
        unset($account);

        $this->render('admin/automation', array(
            'pageTitle' => 'Administracja',
            'contentTitle' => 'Administracja',
            'pageDescription' => 'Gotowe linki do cronow Allegro oraz opcjonalny auto-worker uruchamiany z panelu.',
            'breadcrumbCurrent' => 'Administracja',
            'automation' => $this->allegro->automationLinks($baseUrl),
            'queueStats' => $this->allegro->queueCounts(),
            'accounts' => $accounts,
            'defaultRedirectUri' => $baseUrl . '?controller=allegro&action=callback',
            'sellasistBaseUrl' => $this->settings->get('sellasist_base_url', 'https://altreo.sellasist.pl'),
            'sellasistApiKey' => $this->settings->get('sellasist_api_key', ''),
        ));
    }

    public function allegroaccounts(): void
    {
        $this->requireRole('admin');
        $this->redirect('./index.php?controller=admin&action=automation');
    }

    public function cleanup(): void
    {
        $this->requireRole('admin');
        $this->requireWriteAccess();

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=admin&action=automation');
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

        $this->redirect('./index.php?controller=admin&action=automation');
    }

    public function savesellasist(): void
    {
        $this->requireRole('admin');
        $this->requireWriteAccess();

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=admin&action=automation');
        }

        try {
            $baseUrl = rtrim(trim((string) $this->input('sellasist_base_url', 'https://altreo.sellasist.pl')), '/');
            $apiKey = trim((string) $this->input('sellasist_api_key', ''));

            if ($baseUrl === '') {
                $baseUrl = 'https://altreo.sellasist.pl';
            }

            $baseUrl = preg_replace('#/api(?:/v1)?$#i', '', $baseUrl);

            $this->settings->set('sellasist_base_url', $baseUrl);
            $this->settings->set('sellasist_api_key', $apiKey);

            $this->setFlash('success', 'Ustawienia Sellasist zostaly zapisane.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=admin&action=automation');
    }

    public function deleteUser(): void
    {
        $admin = $this->requireRole('admin');
        $this->requireWriteAccess();

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=admin&action=users');
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

        $this->redirect('./index.php?controller=admin&action=users');
    }

    private function absoluteBaseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');

        return $scheme . '://' . $host . $script;
    }
}
