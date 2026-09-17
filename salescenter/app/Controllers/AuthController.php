<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Services\MailService;
use App\Services\OrderSyncError;
use App\Services\TenantProvisioner;
use InvalidArgumentException;

final class AuthController extends Controller
{
    public function index(): void
    {
        $this->redirect('./index.php?controller=auth&action=login');
    }

    public function login(): void
    {
        if ($this->currentUser()) {
            $this->redirect('./index.php?controller=orders');
        }

        $email = '';
        if ($this->isPost()) {
            $this->requireCsrf();
            $email = trim((string) ($_POST['email'] ?? ''));
            $result = $this->saas()->attemptLogin($email, (string) ($_POST['password'] ?? ''));
            if (isset($result['user'])) {
                $this->loginUser($result['user']);
                $this->redirect('./index.php?controller=orders');
            }
            $this->setFlash('error', (string) $result['error']);
        }

        $this->render('auth/login', array('pageTitle' => 'Logowanie', 'email' => $email, 'csrf' => $this->csrfToken()));
    }

    public function register(): void
    {
        $config = Config::get('app');
        if (empty($config['registration_enabled'])) {
            http_response_code(403);
            exit('Rejestracja nowych firm jest wyłączona.');
        }
        if ($this->currentUser()) {
            $this->redirect('./index.php?controller=orders');
        }

        $form = array('company' => '', 'nip' => '', 'name' => '', 'email' => '');
        if ($this->isPost()) {
            $this->requireCsrf();
            foreach (array_keys($form) as $key) {
                $form[$key] = trim((string) ($_POST[$key] ?? ''));
            }
            try {
                if ((string) ($_POST['password'] ?? '') !== (string) ($_POST['password_confirm'] ?? '')) {
                    throw new InvalidArgumentException('Hasła nie są identyczne.');
                }
                if (empty($_POST['terms'])) {
                    throw new InvalidArgumentException('Zaakceptuj regulamin, aby utworzyć konto.');
                }
                $saas = $this->saas();
                $created = $saas->register($form['company'], $form['nip'], $form['name'], $form['email'], (string) $_POST['password']);
                TenantProvisioner::provision($this->db(), $created['tenant_id']);
                $this->loginUser($saas->findUserById($created['user_id']));
                $this->setFlash('success', 'Witaj w SalesCenter! Konto firmy jest gotowe. Zacznij od ustawienia danych sprzedawcy i serii dokumentów.');
                $this->redirect('./index.php?controller=orders&tab=general');
            } catch (InvalidArgumentException $e) {
                $this->setFlash('error', $e->getMessage());
            } catch (\Throwable $e) {
                $diagnostic = OrderSyncError::log($e, array('stage' => 'register'));
                $this->setFlash('error', 'Nie udało się utworzyć konta. Spróbuj ponownie. [ID: ' . $diagnostic['reference'] . ']');
            }
        }

        $this->render('auth/register', array('pageTitle' => 'Rejestracja firmy', 'form' => $form, 'csrf' => $this->csrfToken()));
    }

    public function logout(): void
    {
        $this->requireCsrf();
        $this->logoutUser();
        $this->ensureSessionStarted();
        $this->setFlash('success', 'Wylogowano.');
        $this->redirect('./index.php?controller=auth&action=login');
    }

    public function forgot(): void
    {
        if ($this->isPost()) {
            $this->requireCsrf();
            try {
                $reset = $this->saas()->createPasswordReset((string) ($_POST['email'] ?? ''));
                if ($reset) {
                    $link = $this->publicUrl('?controller=auth&action=reset&token=' . rawurlencode($reset['token']));
                    $name = htmlspecialchars((string) $reset['user']['name'], ENT_QUOTES, 'UTF-8');
                    $html = '<p>Cześć ' . $name . ',</p><p>Aby ustawić nowe hasło, otwórz link (ważny 60 minut):</p><p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '</a></p><p>Jeśli to nie Ty, zignoruj tę wiadomość.</p>';
                    (new MailService())->send((string) $reset['user']['email'], 'Reset hasła – SalesCenter', $html, 'Reset hasła: ' . $link);
                }
            } catch (\Throwable $e) {
                OrderSyncError::log($e, array('stage' => 'password_reset'));
            }
            // Ten sam komunikat niezależnie od tego, czy konto istnieje.
            $this->setFlash('success', 'Jeśli konto istnieje, wysłaliśmy wiadomość z linkiem do ustawienia nowego hasła.');
            $this->redirect('./index.php?controller=auth&action=login');
        }

        $this->render('auth/forgot', array('pageTitle' => 'Reset hasła', 'csrf' => $this->csrfToken()));
    }

    public function reset(): void
    {
        $token = (string) $this->input('token', '');
        if ($this->isPost()) {
            $this->requireCsrf();
            try {
                if ((string) ($_POST['password'] ?? '') !== (string) ($_POST['password_confirm'] ?? '')) {
                    throw new InvalidArgumentException('Hasła nie są identyczne.');
                }
                $this->saas()->consumeReset($token, (string) ($_POST['password'] ?? ''));
                $this->setFlash('success', 'Hasło zostało zmienione. Zaloguj się nowym hasłem.');
                $this->redirect('./index.php?controller=auth&action=login');
            } catch (InvalidArgumentException $e) {
                $this->setFlash('error', $e->getMessage());
            }
        }

        $this->render('auth/reset', array('pageTitle' => 'Nowe hasło', 'token' => $token, 'tokenValid' => $this->saas()->validReset($token) !== null, 'csrf' => $this->csrfToken()));
    }

    private function publicUrl(string $query): string
    {
        $config = Config::get('app');
        $base = trim((string) ($config['public_base_url'] ?? ''));
        if ($base === '') {
            // Bez stałego adresu linku nie budujemy z nagłówka Host (mógłby go podstawić atakujący).
            throw new \RuntimeException('Brak publicznego adresu SalesCenter – link resetu hasła nie został wysłany.');
        }
        return $base . $query;
    }
}
