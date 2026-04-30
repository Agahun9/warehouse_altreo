<?php

declare(strict_types=1);

$query = http_build_query(array(
    'controller' => 'allegro',
    'action' => 'callback',
    'code' => isset($_GET['code']) ? (string) $_GET['code'] : '',
    'state' => isset($_GET['state']) ? (string) $_GET['state'] : '',
    'error' => isset($_GET['error']) ? (string) $_GET['error'] : '',
    'error_description' => isset($_GET['error_description']) ? (string) $_GET['error_description'] : '',
));

header('Location: ./index.php?' . $query);
exit;
