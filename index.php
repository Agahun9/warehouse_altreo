<?php

declare(strict_types=1);

define('BASE_PATH', __DIR__);

// Funkcja logowania aplikacji
function app_log(string $message, string $level = 'INFO'): void
{
    $logDir = BASE_PATH . '/app/Storage/logs';
    $logFile = $logDir . '/app.log';

    // Utwórz katalog jeśli nie istnieje
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;

    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

require BASE_PATH . '/app/bootstrap.php';

try {
    $application = new App\Core\Application();
    $application->run();
} catch (Throwable $exception) {
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $details = get_class($exception) . ': ' . $exception->getMessage()
        . ' w ' . $exception->getFile() . ':' . $exception->getLine()
        . ' (URL: ' . $requestUri . ')' . PHP_EOL . $exception->getTraceAsString();
    app_log("BŁĄD APLIKACJI: " . $details, 'ERROR');
    http_response_code(500);
    echo nl2br(htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8'));
}
