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
            'authBaseUrl' => $this->tokens->baseUrl(),
        ));
    }

    public function store(): void
    {
        if (!$this->isPost()) {
            $this->redirect($this->authUrl('register'));
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
                $this->redirect($this->authUrl('login'));
            }

            $this->users->replaceModulePermissions((int) $userId, array(
                'products' => 'edit',
            ));
            $token = $this->tokens->generate();
            $ttl = (int) Config::get('app')['email_verification_ttl'];
            $expiresAt = date('Y-m-d H:i:s', time() + $ttl);
            $this->users->createEmailVerification((int) $userId, $token, $expiresAt);
            $this->sendActivationMail($email, $firstName, $token, $expiresAt);

            $this->setFlash('success', 'Konto utworzone. Sprawdz email i potwierdz rejestracje.');
            $this->redirect($this->authUrl('login'));
        } catch (Throwable $exception) {
            $this->render('auth/register', array(
                'pageTitle' => 'Rejestracja',
                'contentTitle' => 'Utworz konto',
                'pageDescription' => 'Zarejestruj nowe konto i potwierdz email.',
                'breadcrumbCurrent' => 'Rejestracja',
                'authBaseUrl' => $this->tokens->baseUrl(),
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
            $this->redirect($this->authUrl('login'));
        }

        $this->users->updateUser((int) $record['user_id'], array('is_active' => 1));
        $this->users->useEmailVerification((int) $record['id']);
        $this->setFlash('success', 'Email zostal potwierdzony. Mozesz sie zalogowac.');
        $this->redirect($this->authUrl('login'));
    }

    public function login(): void
    {
        $this->render('auth/login', array(
            'pageTitle' => 'Logowanie',
            'contentTitle' => 'Zaloguj sie',
            'pageDescription' => 'Podaj email i haslo.',
            'breadcrumbCurrent' => 'Logowanie',
            'authBaseUrl' => $this->tokens->baseUrl(),
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
            $this->redirect($this->authUrl('login'));
        }

        if ((int) $user['is_active'] !== 1) {
            $this->setFlash('error', 'Konto nie jest jeszcze aktywne.');
            $this->redirect($this->authUrl('login'));
        }

        if ((int) $user['is_blocked'] === 1) {
            $this->setFlash('error', 'Konto jest zablokowane.');
            $this->redirect($this->authUrl('login'));
        }

        $token = $this->jwt->issue(array('user_id' => (int) $user['id'], 'email' => (string) $user['email'], 'role' => (string) $user['role']));
        $config = Config::get('app');
        setcookie((string) $config['jwt_cookie'], $token, time() + (int) $config['jwt_ttl'], '/', '', false, true);

        $this->setFlash('success', 'Zalogowano pomyslnie.');
        $this->redirect($this->appUrl('index'));
    }

    public function logout(): void
    {
        $config = Config::get('app');
        setcookie((string) $config['jwt_cookie'], '', time() - 3600, '/', '', false, true);
        $this->setFlash('success', 'Wylogowano.');
        $this->redirect($this->authUrl('login'));
    }

    public function forgotPassword(): void
    {
        $this->render('auth/forgot_password', array(
            'pageTitle' => 'Reset hasla',
            'contentTitle' => 'Zapomniane haslo',
            'pageDescription' => 'Podaj email, a wyslemy link do resetu.',
            'breadcrumbCurrent' => 'Reset hasla',
            'authBaseUrl' => $this->tokens->baseUrl(),
        ));
    }

    public function sendReset(): void
    {
        if (!$this->isPost()) {
            $this->redirect($this->authUrl('forgotPassword'));
        }

        $email = strtolower(trim((string) $this->input('email', '')));
        $user = $this->users->findByEmail($email);

        if ($user) {
            $token = $this->tokens->generate();
            $ttl = (int) Config::get('app')['password_reset_ttl'];
            $expiresAt = date('Y-m-d H:i:s', time() + $ttl);
            $this->users->createPasswordReset((int) $user['id'], $token, $expiresAt);
            $this->sendPasswordResetMail($email, (string) ($user['first_name'] ?? ''), $token, $expiresAt);
        }

        $this->setFlash('success', 'Jesli konto istnieje, link resetujacy zostal wyslany.');
        $this->redirect($this->authUrl('login'));
    }

    public function resetPassword(): void
    {
        $token = (string) $this->input('token', '');
        $record = $this->users->findPasswordReset($token);

        if (!$record || !empty($record['used_at']) || strtotime((string) $record['expires_at']) < time()) {
            $this->setFlash('error', 'Token resetu jest nieprawidlowy lub wygasl.');
            $this->redirect($this->authUrl('forgotPassword'));
        }

        $this->render('auth/reset_password', array(
            'pageTitle' => 'Nowe haslo',
            'contentTitle' => 'Ustaw nowe haslo',
            'pageDescription' => 'Wpisz nowe haslo dla swojego konta.',
            'breadcrumbCurrent' => 'Nowe haslo',
            'authBaseUrl' => $this->tokens->baseUrl(),
            'resetToken' => $token,
        ));
    }

    public function updatePassword(): void
    {
        if (!$this->isPost()) {
            $this->redirect($this->authUrl('forgotPassword'));
        }

        $token = (string) $this->input('token', '');
        $password = (string) $this->input('password', '');
        $confirm = (string) $this->input('password_confirm', '');
        $record = $this->users->findPasswordReset($token);

        if (!$record || !empty($record['used_at']) || strtotime((string) $record['expires_at']) < time()) {
            $this->setFlash('error', 'Token resetu jest nieprawidlowy lub wygasl.');
            $this->redirect($this->authUrl('forgotPassword'));
        }

        if (strlen($password) < 8) {
            $this->setFlash('error', 'Haslo musi miec co najmniej 8 znakow.');
            $this->redirect($this->authUrl('resetPassword', array('token' => $token)));
        }

        if ($password !== $confirm) {
            $this->setFlash('error', 'Hasla nie sa takie same.');
            $this->redirect($this->authUrl('resetPassword', array('token' => $token)));
        }

        $hash = password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
        $this->users->updateUser((int) $record['user_id'], array('password_hash' => $hash));
        $this->users->usePasswordReset((int) $record['id']);
        $this->setFlash('success', 'Haslo zostalo zmienione.');
        $this->redirect($this->authUrl('login'));
    }

    private function sendActivationMail(string $email, string $firstName, string $token, string $expiresAt): void
    {
        $link = $this->tokens->buildUrl('verify', $token);
        $subject = 'Aktywuj konto w ALTREO CRM';
        $greetingName = $firstName !== '' ? $firstName : $email;
        $loginUrl = $this->authUrl('login');
        $expiresLabel = date('d.m.Y H:i', strtotime($expiresAt));

        $html = $this->mailLayout(
            'Aktywacja konta',
            '<p>Czesc ' . htmlspecialchars($greetingName, ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>Dziekujemy za rejestracje w <strong>ALTREO CRM</strong>. Aby aktywowac konto, kliknij przycisk ponizej.</p>'
            . '<p style="margin:24px 0;"><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:12px 20px;border-radius:10px;background:#0d6efd;color:#ffffff;text-decoration:none;font-weight:700;">Aktywuj konto</a></p>'
            . '<p>Link wygasa: <strong>' . htmlspecialchars($expiresLabel, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
            . '<p>Jesli przycisk nie dziala, skopiuj ten adres do przegladarki:</p>'
            . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '</a></p>'
            . '<p>Po aktywacji zalogujesz sie tutaj: <a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '</a></p>'
        );

        $text = "Aktywacja konta ALTREO CRM\n\n"
            . "Czesc " . $greetingName . ",\n\n"
            . "Aby aktywowac konto, otworz ten link:\n" . $link . "\n\n"
            . "Link wygasa: " . $expiresLabel . "\n\n"
            . "Logowanie: " . $loginUrl . "\n";

        $this->mail->send($email, $subject, $html, $text);
    }

    private function sendPasswordResetMail(string $email, string $firstName, string $token, string $expiresAt): void
    {
        $link = $this->tokens->buildUrl('resetPassword', $token);
        $subject = 'Ustaw nowe haslo w ALTREO CRM';
        $greetingName = $firstName !== '' ? $firstName : $email;
        $forgotUrl = $this->authUrl('forgotPassword');
        $expiresLabel = date('d.m.Y H:i', strtotime($expiresAt));

        $html = $this->mailLayout(
            'Reset hasla',
            '<p>Czesc ' . htmlspecialchars($greetingName, ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>Otrzymalismy prosbe o ustawienie nowego hasla do <strong>ALTREO CRM</strong>.</p>'
            . '<p style="margin:24px 0;"><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:12px 20px;border-radius:10px;background:#0d6efd;color:#ffffff;text-decoration:none;font-weight:700;">Ustaw nowe haslo</a></p>'
            . '<p>Link wygasa: <strong>' . htmlspecialchars($expiresLabel, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
            . '<p>Jesli to nie Ty wysylales prosbe, po prostu zignoruj ta wiadomosc.</p>'
            . '<p>Jesli przycisk nie dziala, skopiuj ten adres do przegladarki:</p>'
            . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '</a></p>'
            . '<p>Formularz resetu jest tez dostepny tutaj: <a href="' . htmlspecialchars($forgotUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($forgotUrl, ENT_QUOTES, 'UTF-8') . '</a></p>'
        );

        $text = "Reset hasla ALTREO CRM\n\n"
            . "Czesc " . $greetingName . ",\n\n"
            . "Aby ustawic nowe haslo, otworz ten link:\n" . $link . "\n\n"
            . "Link wygasa: " . $expiresLabel . "\n\n"
            . "Jesli to nie Ty, zignoruj ta wiadomosc.\n";

        $this->mail->send($email, $subject, $html, $text);
    }

    private function mailLayout(string $title, string $bodyHtml): string
    {
        return '<div style="margin:0;padding:24px;background:#f3f6fb;font-family:Arial,sans-serif;color:#17202a;">'
            . '<div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #dbe4f0;border-radius:16px;overflow:hidden;">'
            . '<div style="padding:20px 24px;background:linear-gradient(135deg,#0f172a,#1d4ed8);color:#ffffff;">'
            . '<div style="font-size:13px;opacity:0.82;letter-spacing:0.08em;text-transform:uppercase;">ALTREO CRM</div>'
            . '<h1 style="margin:8px 0 0;font-size:24px;line-height:1.2;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>'
            . '</div>'
            . '<div style="padding:24px;font-size:15px;line-height:1.7;">' . $bodyHtml . '</div>'
            . '<div style="padding:16px 24px;background:#f8fafc;color:#64748b;font-size:13px;line-height:1.6;">'
            . 'Ta wiadomosc zostala wyslana automatycznie. Jesli potrzebujesz pomocy, skontaktuj sie z administratorem systemu.'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    private function authUrl(string $action, array $params = array()): string
    {
        return $this->appUrl('auth', $action, $params);
    }

    private function appUrl(string $controller, string $action = '', array $params = array()): string
    {
        $query = array_merge(array('controller' => $controller), $action !== '' ? array('action' => $action) : array(), $params);
        return $this->tokens->baseUrl() . '?' . http_build_query($query);
    }
}
