<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\SaasRepository;
use InvalidArgumentException;

/** Dane firmy, zespół i własne hasło. Każda operacja działa wyłącznie w firmie zalogowanego użytkownika. */
final class AccountController extends Controller
{
    /** Firma, zespół i hasło są w Centrum zamówień → Ustawienia ogólne; tu zostały tylko akcje zapisu. */
    private const GENERAL = './orders.php?tab=general';

    public function index(): void
    {
        $this->redirect(self::GENERAL.'#sc-team');
    }

    public function company(): void
    {
        $user = $this->requireAuth();
        if ($this->isPost()) {
            $user = $this->requireTenantAdmin();
            $this->requireCsrf();
            try {
                $this->saas()->updateTenant((int) $user['tenant_id'], (string) ($_POST['name'] ?? ''), (string) ($_POST['nip'] ?? ''));
                $this->setFlash('success', 'Zapisano dane firmy.');
            } catch (InvalidArgumentException $e) {
                $this->setFlash('error', $e->getMessage());
            }
            $this->redirect(self::GENERAL.'#sc-company');
        }

        $this->redirect(self::GENERAL.'#sc-company');
    }

    public function team(): void
    {
        $this->requireTenantAdmin();
        $this->redirect(self::GENERAL.'#sc-team');
    }

    public function saveuser(): void
    {
        $user = $this->requireTenantAdmin();
        $this->requireCsrf();
        $tenantId = (int) $user['tenant_id'];
        $saas = $this->saas();
        try {
            $operation = (string) ($_POST['operation'] ?? '');
            $targetId = (int) ($_POST['user_id'] ?? 0);
            $role = (string) ($_POST['role'] ?? 'member');
            // Administrator nie nadaje ani nie zmienia roli właściciela.
            if ($user['role'] !== 'owner') {
                if ($role === 'owner') { throw new InvalidArgumentException('Tylko właściciel może nadać rolę właściciela.'); }
                if ($targetId > 0 && $saas->tenantUser($tenantId, $targetId)['role'] === 'owner') { throw new InvalidArgumentException('Tylko właściciel może zmieniać konto właściciela.'); }
            }
            switch ($operation) {
                case 'create':
                    $saas->createUser($tenantId, (string) ($_POST['name'] ?? ''), (string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''), $role, (string) ($_POST['access'] ?? 'read'));
                    $this->setFlash('success', 'Dodano użytkownika. Przekaż mu adres logowania i hasło.');
                    break;
                case 'update':
                    if ($targetId === (int) $user['id'] && (!empty($_POST['is_blocked']) || $role !== $user['role'])) {
                        throw new InvalidArgumentException('Nie możesz zablokować siebie ani zmienić własnej roli.');
                    }
                    $saas->updateUser($tenantId, $targetId, (string) ($_POST['name'] ?? ''), $role, (string) ($_POST['access'] ?? 'read'), !empty($_POST['is_blocked']));
                    if ((string) ($_POST['new_password'] ?? '') !== '') {
                        $saas->tenantUser($tenantId, $targetId);
                        $saas->setPassword($targetId, (string) $_POST['new_password']);
                        if ($targetId === (int) $user['id']) {
                            $this->loginUser($saas->findUserById($targetId));
                        }
                    }
                    $this->setFlash('success', 'Zapisano użytkownika.');
                    break;
                case 'delete':
                    if ($targetId === (int) $user['id']) { throw new InvalidArgumentException('Nie możesz usunąć własnego konta.'); }
                    $saas->deleteUser($tenantId, $targetId);
                    $this->setFlash('success', 'Usunięto użytkownika.');
                    break;
                default:
                    throw new InvalidArgumentException('Nieznana operacja.');
            }
        } catch (InvalidArgumentException $e) {
            $this->setFlash('error', $e->getMessage());
        }
        $this->redirect(self::GENERAL.'#sc-team');
    }

    public function password(): void
    {
        $user = $this->requireAuth();
        if ($this->isPost()) {
            $this->requireCsrf();
            $saas = $this->saas();
            try {
                $full = $saas->findUserById((int) $user['id']);
                if (!$full || !password_verify((string) ($_POST['current_password'] ?? ''), (string) $full['password_hash'])) {
                    throw new InvalidArgumentException('Obecne hasło jest nieprawidłowe.');
                }
                if ((string) ($_POST['password'] ?? '') !== (string) ($_POST['password_confirm'] ?? '')) {
                    throw new InvalidArgumentException('Hasła nie są identyczne.');
                }
                $saas->setPassword((int) $user['id'], (string) $_POST['password']);
                // Nowy odcisk hasła: ta sesja zostaje, pozostałe zostaną wylogowane.
                $this->loginUser($saas->findUserById((int) $user['id']));
                $this->setFlash('success', 'Zmieniono hasło. Inne sesje zostały wylogowane.');
            } catch (InvalidArgumentException $e) {
                $this->setFlash('error', $e->getMessage());
            }
            $this->redirect(self::GENERAL.'#sc-password');
        }

        $this->redirect(self::GENERAL.'#sc-password');
    }
}
