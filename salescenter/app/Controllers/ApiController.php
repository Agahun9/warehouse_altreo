<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Tenant;
use App\Models\ConnectionRepository;
use App\Models\OrderRepository;
use App\Services\OrderNormalizer;
use App\Services\OrderSyncError;
use InvalidArgumentException;

/** API dla własnych sklepów: wysyłka zamówień do SalesCenter i odczyt statusów. */
final class ApiController extends Controller
{
    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        try {
            $connection = $this->authenticate();
            if ($connection === null) { $this->json(['error' => 'Nieprawidłowy token API.', 'code' => 'UNAUTHORIZED'], 401); return; }
            $repo = new OrderRepository($this->db()); $repo->ensureSchema();
            $account = (new ConnectionRepository($this->db()))->account((int) $connection['id']);
            if (!$account) { $this->json(['error' => 'Połączenie API nie jest aktywne.', 'code' => 'INACTIVE'], 403); return; }
            $route = trim((string) ($_GET['api_route'] ?? ''), '/');
            $method = $this->requestMethod();
            if ($route === 'v1/ping' && $method === 'GET') {
                $tenant = $this->saas()->tenant(Tenant::id());
                $this->json(['ok' => true, 'company' => $tenant['name'] ?? '', 'connection' => $connection['name']]);
            } elseif ($route === 'v1/statuses' && $method === 'GET') {
                $this->json(['statuses' => array_map(static function ($s) { return ['id' => (int) $s['id'], 'name' => $s['name'], 'group' => $s['group_name']]; }, $repo->statuses())]);
            } elseif ($route === 'v1/orders' && $method === 'POST') {
                $this->upsert($repo, (int) $account['id']);
            } elseif ($route === 'v1/orders' && $method === 'GET') {
                $this->changed($repo, (int) $account['id']);
            } elseif (preg_match('#^v1/orders/([^/]{1,190})$#', $route, $match) === 1 && $method === 'GET') {
                $id = (int) $this->db()->fetchColumn('SELECT id FROM om_orders WHERE account_id=:a AND external_id=:e', ['a' => $account['id'], 'e' => rawurldecode($match[1])]);
                if ($id < 1) { $this->json(['error' => 'Nie znaleziono zamówienia.', 'code' => 'NOT_FOUND'], 404); return; }
                $this->json(['order' => $this->present($repo, $id)]);
            } else {
                $this->json(['error' => 'Nieznany endpoint. Dostępne: GET v1/ping, GET v1/statuses, POST v1/orders, GET v1/orders, GET v1/orders/{id}.', 'code' => 'NOT_FOUND'], 404);
            }
        } catch (InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage(), 'code' => 'INVALID_INPUT'], 422);
        } catch (\Throwable $e) {
            $diagnostic = OrderSyncError::log($e, ['stage' => 'shop_api']);
            $this->json(['error' => 'Błąd serwera SalesCenter.', 'code' => 'SERVER_ERROR', 'reference' => $diagnostic['reference']], 500);
        } finally {
            Tenant::clear();
        }
    }

    private function authenticate(): ?array
    {
        $token = $this->bearerToken();
        if ($token === '' && isset($_SERVER['HTTP_X_API_KEY'])) { $token = trim((string) $_SERVER['HTTP_X_API_KEY']); }
        $tenantId = Tenant::idFromToken($token);
        if ($tenantId === null || strlen($token) > 200) { return null; }
        $tenant = $this->saas()->tenant($tenantId);
        if (!$tenant || $tenant['status'] !== 'active') { return null; }
        Tenant::activate($tenantId);
        $connections = new ConnectionRepository($this->db()); $connections->ensureSchema();
        $row = $connections->findByTokenHash(hash('sha256', $token));
        return $row && $row['platform'] === 'api' && $row['status'] === 'active' ? $row : null;
    }

    private function upsert(OrderRepository $repo, int $accountId): void
    {
        $raw = (string) file_get_contents('php://input');
        if (strlen($raw) > 5000000) { throw new InvalidArgumentException('Zbyt duże żądanie (maks. 5 MB).'); }
        $body = json_decode($raw, true);
        if (!is_array($body)) { throw new InvalidArgumentException('Treść żądania musi być poprawnym JSON.'); }
        $orders = isset($body['orders']) && is_array($body['orders']) ? $body['orders'] : [$body];
        if (count($orders) > 100) { throw new InvalidArgumentException('Wyślij maksymalnie 100 zamówień w jednym żądaniu.'); }
        $results = []; $failed = 0;
        foreach ($orders as $index => $input) {
            try {
                if (!is_array($input)) { throw new InvalidArgumentException('Zamówienie musi być obiektem JSON.'); }
                if (trim((string) ($input['id'] ?? '')) === '' || mb_strlen((string) $input['id'], 'UTF-8') > 190) { throw new InvalidArgumentException('Pole „id” jest wymagane (maks. 190 znaków).'); }
                if (!is_array($input['items'] ?? null) || !$input['items']) { throw new InvalidArgumentException('Pole „items” musi zawierać co najmniej jedną pozycję.'); }
                $order = OrderNormalizer::normalize('api', $input, 0, time() + 86400);
                if ($order === null) { throw new InvalidArgumentException('Data „created_at” nie może być z przyszłości.'); }
                $created = $repo->import($accountId, $order);
                $repo->flushAutomations();
                $localId = (int) $this->db()->fetchColumn('SELECT id FROM om_orders WHERE account_id=:a AND external_id=:e', ['a' => $accountId, 'e' => $order['external_id']]);
                $results[] = ['id' => $order['external_id'], 'local_id' => $localId, 'created' => $created];
            } catch (\Throwable $e) {
                $repo->discardAutomations();
                $failed++;
                $results[] = ['index' => $index, 'id' => is_array($input) ? (string) ($input['id'] ?? '') : '', 'error' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Nie udało się zapisać zamówienia.'];
            }
        }
        $this->json(['results' => $results], $failed === count($orders) ? 422 : ($failed ? 207 : 200));
    }

    /** Zamówienia z tego sklepu zmienione od podanej daty – do synchronizacji statusów w sklepie. */
    private function changed(OrderRepository $repo, int $accountId): void
    {
        $since = strtotime((string) ($_GET['updated_since'] ?? ''));
        if ($since === false) { throw new InvalidArgumentException('Podaj parametr updated_since, np. 2026-09-01T00:00:00Z.'); }
        $rows = $this->db()->fetchAll('SELECT id FROM om_orders WHERE account_id=:a AND (status_changed_at>=:s OR updated_at>=:s2) ORDER BY id LIMIT 100', ['a' => $accountId, 's' => gmdate('Y-m-d H:i:s', $since), 's2' => gmdate('Y-m-d H:i:s', $since)]);
        $this->json(['orders' => array_map(function ($row) use ($repo) { return $this->present($repo, (int) $row['id']); }, $rows)]);
    }

    private function present(OrderRepository $repo, int $id): array
    {
        $order = $repo->order($id);
        $shipments = $this->db()->fetchAll('SELECT carrier,tracking,state,created_at FROM om_shipments WHERE order_id=:id ORDER BY id', ['id' => $id]);
        $documents = $this->db()->fetchAll('SELECT kind,number,created_at FROM om_documents WHERE order_id=:id ORDER BY id', ['id' => $id]);
        return [
            'id' => $order['external_id'], 'local_id' => (int) $order['id'], 'status' => $order['status_name'], 'status_id' => (int) $order['status_id'],
            'status_changed_at' => $order['status_changed_at'] ? gmdate('c', strtotime($order['status_changed_at'].' UTC')) : null,
            'paid' => (bool) (int) $order['paid'], 'total' => number_format((int) $order['total_cents'] / 100, 2, '.', ''), 'currency' => $order['currency'],
            'shipments' => array_map(static function ($s) { return ['carrier' => $s['carrier'], 'tracking_number' => strpos((string) $s['tracking'], 'PENDING:') === 0 ? null : $s['tracking'], 'state' => $s['state']]; }, $shipments),
            'documents' => array_map(static function ($d) { return ['kind' => $d['kind'], 'number' => $d['number']]; }, $documents),
        ];
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
