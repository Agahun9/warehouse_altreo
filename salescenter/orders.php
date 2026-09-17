<?php
/** Standalone order workspace, using the application's existing authentication. */
declare(strict_types=1);
// Domyślnie centrum zamówień; jawny ?controller= (np. integrations z formularzy) ma pierwszeństwo.
if (!isset($_GET['controller']) || $_GET['controller'] === '') { $_GET['controller']='orders'; }
require __DIR__.'/index.php';
