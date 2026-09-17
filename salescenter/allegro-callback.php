<?php
/** Adres powrotu z logowania Allegro (redirect URI aplikacji Allegro). */
declare(strict_types=1);
$_GET['controller']='integrations';
$_GET['action']='allegrocallback';
require __DIR__.'/index.php';
