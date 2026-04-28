<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Models\UserRepository;
use App\Services\JwtService;
use App\Services\MailService;
use App\Services\TokenService;
use RuntimeException;
use Throwable;

class AuthController extends Controller
{
    /** @var UserRepository */
    private $users;

    /** @var JwtService */
    private $jwt;

    /** @var MailService */
    private $mail;

    /** @var TokenService */
    private $tokens;

    public function __construct()
    {
        $this->users = new UserRepository($this->db());
        $this->users->ensureSchema();
        $this->jwt = new JwtService();
        $this->mail = new MailService();
        $this->tokens = new TokenService();
    }

    public function register(): void
    {
        $this->render('auth/register', array(
            'pageTitle' => 'Rejestracja',
            'contentTitle' => 'Utworz konto',
            'pageDescription' => 'Zarejestruj nowe konto i potwierdz email.',
            'breadcrumbCurrent' => 'Rejestracja',
        ));
    }

    public function store(): void
    {
        if (!$this->isPost()) {
            $this->redirect('https://magazyn.altreo.pl/crm/new_version/index.php?controller=auth&action=register');
        }

        try {
            $email = strtolower(trim((string) $this->input('email', '')));
            $firstName = trim((string) $this->input('first_name', ''));
            $lastName = trim((string) $this->input('last_name', ''));
            $password = (string) $this->input('password', '');
            $passwordConfirm = (string) $this->input('password_confirm', '');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Podaj poprawny adres email.');
            }

            if (strlen($password) < 8) {
                throw new RuntimeException('Haslo musi miec co najmniej 8 znakow.');
            }

            if ($password !== $passwordConfirm) {
                throw new RuntimeException('Hasla nie sa takie same.');
            }

            if ($this->users->findByEmail($email)) {
                throw new RuntimeException('Uzytkownik o takim emailu juz istnieje.');
            }

            $allUsers = $this->users->allUsers();
            $isFirstUser = count($allUsers) === 0;
            $role = $isFirstUser ? 'admin' : 'user';
            $isActive = $isFirstUser ? 1 : 0;

            $hash = password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
            $userId = $this->users->createUser(array(
                'email' => $email,
                'first_name' => ($firstName !== '' ? $firstName : null),
                'last_name' => ($lastName !== '' ? $lastName : null),
                'password_hash' => $hash,
                'role' => $role,
                'permission_level' => 'edit',
                'is_active' => $isActive,
                'is_blocked' => 0,
            ));

            $moduleRows = $this->users->availableModules();
            $modulePermissions = array();
            foreach ($moduleRows as $moduleRow) {
                $moduleCode = strtolower(trim((string) ($moduleRow['code'] ?? '')));
                if ($moduleCode === '') {
                    continue;
                }

                $modulePermissions[$moduleCode] = 'edit';
            }

            if ($role === 'admin') {
                $this->users->replaceModulePermissions((int) $userId, $modulePermissions);
                $this->setFlash('success', 'Pierwsze konto zostalo utworzone jako admin i jest aktywne. Mozesz sie zalogowac.');
                $this->redirect('https://magazyn.altreo.pl/crm/new_version/index.php?controller=auth&action=login');
            }

            $this->users->replaceModulePermissions((int) $userId, array(
                'products' => 'edit',
            ));
            $token = $this->tokens->generate();
            $ttl = (int) Config::get('app')['email_verification_ttl'];
            $expiresAt = date('Y-m-d H:i:s', time() + $ttl);
            $this->users->createEmailVerification((int) $userId, $token, $expiresAt);
            $link = $this->tokens->buildUrl('verify', $token);
            $this->mail->send($email, 'Potwierdzenie rejestracji', '<p>Kliknij aby aktywowac konto:</p><p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '</a></p>');

            $this->setFlash('success', 'Konto utworzone. Sprawdz email i potwierdz rejestracje.');
            $this->redirect('./index.php?controller=auth&action=login');
        } catch (Throwable $exception) {
            $this->render('auth/register', array(
                'pageTitle' => 'Rejestracja',
                'contentTitle' => 'Utworz konto',
                'pageDescription' => 'Zarejestruj nowe konto i potwierdz email.',
                'breadcrumbCurrent' => 'Rejestracja',
                'flashError' => $exception->getMessage(),
                'formEmail' => (string) $this->input('email', ''),
                'formFirstName' => (string) $this->input('first_name', ''),
                'formLastName' => (string) $this->input('last_name', ''),
            ));
        }
    }

    public function verify(): void
    {
        $token = (string) $this->input('token', '');
        $record = $this->users->findEmailVerification($token);

        if (!$record || !empty($record['used_at']) || strtotime((string) $record['expires_at']) < time()) {
            $this->setFlash('error', 'Token aktywacyjny jest nieprawidlowy lub wygasl.');
            $this->redirect('https://magazyn.altreo.pl/crm/new_version/index.php?controller=auth&action=login');
        }

        $this->users->updateUser((int) $record['user_id'], array('is_active' => 1));
        $this->users->useEmailVerification((int) $record['id']);
        $this->setFlash('success', 'Email zostal potwierdzony. Mozesz sie zalogowac.');
        $this->redirect('https://magazyn.altreo.pl/crm/new_version/index.php?controller=auth&action=login');
    }

    public function login(): void
    {
        $this->render('auth/login', array(
            'pageTitle' => 'Logowanie',
            'contentTitle' => 'Zaloguj sie',
            'pageDescription' => 'Podaj email i haslo.',
            'breadcrumbCurrent' => 'Logowanie',
        ));
    }

    public function authenticate(): void
    {
        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=auth&action=login');
        }

        $email = strtolower(trim((string) $this->input('email', '')));
        $password = (string) $this->input('password', '');
        $user = $this->users->findByEmail($email);

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            $this->setFlash('error', 'Niepoprawny email lub haslo.');
            $this->redirect('https://magazyn.altreo.pl/crm/new_version/index.php?controller=auth&action=login');
        }

        if ((int) $user['is_active'] !== 1) {
            $this->setFlash('error', 'Konto nie jest jeszcze aktywne.');
            $this->redirect('https://magazyn.altreo.pl/crm/new_version/index.php?controller=auth&action=login');
        }

        if ((int) $user['is_blocked'] === 1) {
            $this->setFlash('error', 'Konto jest zablokowane.');
            $this->redirect('https://magazyn.altreo.pl/crm/new_version/index.php?controller=auth&action=login');
        }

        $token = $this->jwt->issue(array('user_id' => (int) $user['id'], 'email' => (string) $user['email'], 'role' => (string) $user['role']));
        $config = Config::get('app');
        setcookie((string) $config['jwt_cookie'], $token, time() + (int) $config['jwt_ttl'], '/', '', false, true);

        $this->setFlash('success', 'Zalogowano pomyslnie.');
        $this->redirect('https://magazyn.altreo.pl/crm/new_version/index.php?controller=index');
    }

    public function logout(): void
    {
        $config = Config::get('app');
        setcookie((string) $config['jwt_cookie'], '', time() - 3600, '/', '', false, true);
        $this->setFlash('success', 'Wylogowano.');
        $this->redirect('https://magazyn.altreo.pl/crm/new_version/index.php?controller=auth&action=login');
    }

    public function forgotPassword(): void
    {
        $this->render('auth/forgot_password', array(
            'pageTitle' => 'Reset hasla',
            'contentTitle' => 'Zapomniane haslo',
            'pageDescription' => 'Podaj email, a wyslemy link do resetu.',
            'breadcrumbCurrent' => 'Reset hasla',
        ));
    }

    public function sendReset(): void
    {
        if (!$this->isPost()) {
            $this->redirect('https://magazyn.altreo.pl/crm/new_version/index.php?controller=auth&action=forgotPassword');
        }

        $email = strtolower(trim((string) $this->input('email', '')));
        $user = $this->users->findByEmail($email);

        if ($user) {
            $token = $this->tokens->generate();
            $ttl = (int) Config::get('app')['password_reset_ttl'];
            $expiresAt = date('Y-m-d H:i:s', time() + $ttl);
            $this->users->createPasswordReset((int) $user['id'], $token, $expiresAt);
            $link = $this->tokens->buildUrl('resetPassword', $token);
            $this->mail->send($email, 'Reset hasla', '<p>Kliknij aby ustawic nowe haslo:</p><p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '</a></p>');
        }

        $this->setFlash('success', 'Jesli konto istnieje, link resetujacy zostal wyslany.');
        $this->redirect('https://magazyn.altreo.pl/crm/new_version/index.php?controller=auth&action=login');
    }

    public function resetPassword(): void
    {
        $token = (string) $this->input('token', '');
        $record = $this->users->findPasswordReset($token);

        if (!$record || !empty($record['used_at']) || strtotime((string) $record['expires_at']) < time()) {
            $this->setFlash('error', 'Token resetu jest nieprawidlowy lub wygasl.');
            $this->redirect('https://magazyn.altreo.pl/crm/new_version/index.php?controller=auth&action=forgotPassword');
        }

        $this->render('auth/reset_password', array(
            'pageTitle' => 'Nowe haslo',
            'contentTitle' => 'Ustaw nowe haslo',
            'pageDescription' => 'Wpisz nowe haslo dla swojego konta.',
            'breadcrumbCurrent' => 'Nowe haslo',
            'resetToken' => $token,
        ));
    }

    public function updatePassword(): void
    {
        if (!$this->isPost()) {
            $this->redirect('https://magazyn.altreo.pl/crm/new_version/index.php?controller=auth&action=forgotPassword');
        }

        $token = (string) $this->input('token', '');
        $password = (string) $this->input('password', '');
        $confirm = (string) $this->input('password_confirm', '');
        $record = $this->users->findPasswordReset($token);

        if (!$record || !empty($record['used_at']) || strtotime((string) $record['expires_at']) < time()) {
            $this->setFlash('error', 'Token resetu jest nieprawidlowy lub wygasl.');
            $this->redirect('https://magazyn.altreo.pl/crm/new_version/index.php?controller=auth&action=forgotPassword');
        }

        if (strlen($password) < 8) {
            $this->setFlash('error', 'Haslo musi miec co najmniej 8 znakow.');
            $this->redirect('https://magazyn.altreo.pl/crm/new_version/index.php?controller=auth&action=resetPassword&token=' . urlencode($token));
        }

        if ($password !== $confirm) {
            $this->setFlash('error', 'Hasla nie sa takie same.');
            $this->redirect('https://magazyn.altreo.pl/crm/new_version/index.php?controller=auth&action=resetPassword&token=' . urlencode($token));
        }

        $hash = password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
        $this->users->updateUser((int) $record['user_id'], array('password_hash' => $hash));
        $this->users->usePasswordReset((int) $record['id']);
        $this->setFlash('success', 'Haslo zostalo zmienione.');
        $this->redirect('https://magazyn.altreo.pl/crm/new_version/index.php?controller=auth&action=login');
    }
}
