<?php

declare(strict_types=1);

return array(
    'app_name' => 'SalesCenter',
    'base_url' => './index.php',
    // Pełny publiczny adres index.php, np. https://panel.twojadomena.pl/index.php
    // (używany w linkach resetu hasła i w adresach dla agenta druku).
    'public_base_url' => '',
    // Klucz szyfrowania danych przewoźników i tokenów KSeF (64 znaki hex).
    // Wygeneruj: php -r "echo bin2hex(random_bytes(32));"  NIE ZMIENIAJ po uruchomieniu – zaszyfrowane dane przestaną działać.
    'encryption_key' => '',
    // Klucz do jednorazowego uruchomienia install.php?key=... (po instalacji można wyczyścić).
    'install_key' => '',
    'registration_enabled' => true,
    'mail_from' => 'no-reply@twojadomena.pl',
    'mail_from_name' => 'SalesCenter',
    'mail_log_dir' => BASE_PATH . '/app/Storage/mail',
    // Integracje. Allegro: operator SalesCenter rejestruje RAZ aplikację na https://apps.developer.allegro.pl
    // (typ: aplikacja ma dostęp do przeglądarki, redirect URI: <adres SalesCenter>/allegro-callback.php).
    // Potem każda firma łączy konto samym logowaniem do Allegro.
    'integrations' => array(
        'allegro' => array(
            'client_id' => '',
            'client_secret' => '',
            'application_name' => 'SalesCenter',
            'sandbox' => false,
        ),
    ),
);
