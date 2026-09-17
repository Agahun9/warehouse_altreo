<?php

declare(strict_types=1);

/*
 * SalesCenter – połączenie z NOWĄ bazą danych MySQL/MariaDB.
 * Skopiuj ten plik jako database.php i uzupełnij dane dostępowe.
 * Użytkownik bazy potrzebuje uprawnień: SELECT, INSERT, UPDATE, DELETE,
 * CREATE, ALTER, INDEX (tabele firm są tworzone automatycznie przy rejestracji).
 */
return [
    'driver' => 'mysql',
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'NAZWA_BAZY',
    'username' => 'UZYTKOWNIK_BAZY',
    'password' => 'HASLO_BAZY',
    'charset' => 'utf8mb4',
    'options' => [],
];
