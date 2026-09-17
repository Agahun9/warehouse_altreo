<?php
declare(strict_types=1);

// Przykładowy router dla PHP 8.2 + MySQL 8. Podłącz go pod:
// /api/print-agent/health, /jobs/next oraz /jobs/{uuid}/status.

header('Content-Type: application/json; charset=utf-8');
$pdo = new PDO(
    getenv('PRINT_AGENT_DSN') ?: 'mysql:host=127.0.0.1;dbname=magazyn;charset=utf8mb4',
    getenv('PRINT_AGENT_DB_USER') ?: 'magazyn',
    getenv('PRINT_AGENT_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

function answer(int $status, ?array $body = null): never {
    http_response_code($status);
    if ($body !== null) echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function bearerToken(): string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $match)) answer(401, ['error' => 'missing_token']);
    return trim($match[1]);
}

$tokenHash = hash('sha256', bearerToken());
$stationQuery = $pdo->prepare('SELECT id, name FROM print_agent_stations WHERE token_hash = ? AND enabled = 1');
$stationQuery->execute([$tokenHash]);
$station = $stationQuery->fetch();
if (!$station) answer(401, ['error' => 'invalid_token']);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
$path = preg_replace('#^.*?/api/print-agent/?#', '', $path);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET' && $path === 'health') {
    answer(200, ['ok' => true, 'station' => $station['name']]);
}

if ($method === 'GET' && $path === 'jobs/next') {
    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare("SELECT id, pdf_url, printer_name, print_settings
            FROM print_agent_jobs
            WHERE station_id = ? AND status = 'queued'
            ORDER BY created_at
            LIMIT 1 FOR UPDATE SKIP LOCKED");
        $query->execute([$station['id']]);
        $job = $query->fetch();
        if (!$job) {
            $pdo->commit();
            answer(204);
        }
        $claim = $pdo->prepare("UPDATE print_agent_jobs SET status = 'processing', claimed_at = NOW(), status_message = 'Pobrane przez agenta' WHERE id = ?");
        $claim->execute([$job['id']]);
        $pdo->commit();
        answer(200, [
            'id' => $job['id'],
            'pdfUrl' => $job['pdf_url'],
            'printerName' => $job['printer_name'],
            'printSettings' => $job['print_settings'],
        ]);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

if ($method === 'POST' && preg_match('#^jobs/([0-9a-fA-F-]{36})/status$#', $path, $match)) {
    $input = json_decode(file_get_contents('php://input'), true, flags: JSON_THROW_ON_ERROR);
    $allowed = ['processing', 'printed', 'error', 'printer_offline'];
    if (!in_array($input['status'] ?? null, $allowed, true)) answer(422, ['error' => 'invalid_status']);
    $message = mb_substr((string)($input['message'] ?? ''), 0, 1000);
    $update = $pdo->prepare('UPDATE print_agent_jobs SET status = ?, status_message = ?, reported_at = NOW() WHERE id = ? AND station_id = ?');
    $update->execute([$input['status'], $message, $match[1], $station['id']]);
    if ($update->rowCount() === 0) answer(404, ['error' => 'job_not_found']);
    answer(200, ['ok' => true]);
}

answer(404, ['error' => 'not_found']);
