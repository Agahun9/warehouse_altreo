<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SettingRepository;
use App\Core\Database;
use RuntimeException;

/** Odczyt zamówień z SalesCenter do magazynowego zbierania. */
class SalescenterPickingService
{
    private $settings;

    public function __construct(?SettingRepository $settings = null)
    {
        $this->settings = $settings ?: new SettingRepository(Database::instance());
        $this->settings->ensureSchema();
    }

    public function configuration(): array
    {
        return [
            'base_url' => rtrim(trim($this->settings->get('salescenter_api_url', '')), '/'),
            'api_key' => trim($this->settings->get('salescenter_api_key', '')),
            'picking_status_id' => max(1, (int) $this->settings->get('salescenter_picking_status_id', '2')),
            'printed_status_id' => max(0, (int) $this->settings->get('salescenter_printed_status_id', '3')),
        ];
    }

    public function configured(): bool
    {
        $config = $this->configuration();
        return $config['base_url'] !== '' && $config['api_key'] !== '';
    }

    public function listOrders(?int $statusId = null): array
    {
        $config = $this->requireConfigured();
        $statusId = $statusId !== null ? max(1, $statusId) : $config['picking_status_id'];
        $orders = [];
        for ($offset = 0; ; $offset += 100) {
            $response = $this->request('GET', 'v1/picking/orders', ['status_id' => $statusId, 'limit' => 100, 'offset' => $offset]);
            $page = is_array($response['orders'] ?? null) ? $response['orders'] : [];
            foreach ($page as $order) {
                if (!is_array($order)) { continue; }
                $orders[] = $this->toStickerOrder($order);
            }
            if (count($page) < 100) { break; }
        }
        return array_reverse($orders);
    }

    public function generateStickers(array $selectedIds, SellasistService $stickers): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $selectedIds), static function ($id) { return $id > 0; })));
        if (!$ids) { throw new RuntimeException('Wybierz przynajmniej jedno zamówienie.'); }
        $config = $this->requireConfigured();
        $orders = $this->listOrders($config['picking_status_id']);
        $wanted = array_fill_keys($ids, true);
        $selected = array_values(array_filter($orders, static function ($order) use ($wanted) { return isset($wanted[$order['id']]); }));
        if (!$selected) { throw new RuntimeException('Wybrane zamówienia nie są już w skonfigurowanym statusie zbierania. Odśwież listę.'); }
        $payload = $stickers->generateStickersFromOrders($selected);
        if ($config['printed_status_id'] > 0) {
            foreach ($selected as $order) {
                try {
                    $this->changeStatus((int) $order['id'], $config['printed_status_id']);
                } catch (\Throwable $exception) {
                    $payload['warnings'][] = 'Nie udało się zmienić statusu SalesCenter zamówienia #' . (int) $order['id'] . '.';
                }
            }
        }
        return $payload;
    }

    private function toStickerOrder(array $order): array
    {
        $customer = preg_split('/\s+/u', trim((string) ($order['shipping_name'] ?? $order['customer_name'] ?? '')), 2) ?: [];
        $items = [];
        foreach ((array) ($order['items'] ?? []) as $item) {
            if (!is_array($item)) { continue; }
            $items[] = ['name' => (string) ($item['name'] ?? ''), 'signature' => (string) ($item['sku'] ?? ''), 'symbol' => (string) ($item['sku'] ?? ''), 'quantity' => max(1, (int) ($item['quantity'] ?? 1))];
        }
        return [
            'id' => (int) ($order['id'] ?? 0),
            'bill_address' => ['name' => (string) ($customer[0] ?? ''), 'surname' => (string) ($customer[1] ?? '')],
            'creator' => (string) ($order['creator'] ?? ''),
            'external_data' => ['external_shipment_name' => (string) ($order['delivery_name'] ?? '')],
            'comment' => (string) ($order['comment'] ?? ''),
            'carts' => $items,
        ];
    }

    private function changeStatus(int $id, int $statusId): void
    {
        $this->request('PUT', 'v1/picking/orders/' . $id . '/status', [], ['status_id' => $statusId]);
    }

    private function requireConfigured(): array
    {
        $config = $this->configuration();
        if ($config['base_url'] === '' || $config['api_key'] === '') {
            throw new RuntimeException('Uzupełnij adres i token API SalesCenter w Administracja → Automatyzacje.');
        }
        if (strlen($config['api_key']) < 32) { throw new RuntimeException('Token API SalesCenter jest nieprawidłowy lub za krótki.'); }
        return $config;
    }

    private function request(string $method, string $route, array $query = [], ?array $body = null): array
    {
        $config = $this->requireConfigured();
        // Accept either the SalesCenter application URL or the API base copied from its admin page.
        $baseUrl = preg_replace('#/api\.php(?:/v1)?(?:/picking/orders)?(?:\?.*)?$#i', '', $config['base_url']);
        $url = rtrim((string) $baseUrl, '/') . '/api.php/' . ltrim($route, '/') . ($query ? '?' . http_build_query($query) : '');
        $ch = curl_init($url);
        $headers = ['Accept: application/json', 'Authorization: Bearer ' . $config['api_key']];
        if ($body !== null) { $headers[] = 'Content-Type: application/json'; }
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10]);
        if ($body !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE)); }
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($raw === false) { throw new RuntimeException('Nie można połączyć z API SalesCenter: ' . $error); }
        $decoded = json_decode((string) $raw, true);
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            $message = is_array($decoded) ? (string) ($decoded['error'] ?? '') : '';
            throw new RuntimeException('API SalesCenter zwróciło HTTP ' . $status . ($message !== '' ? ': ' . $message : '.'));
        }
        return $decoded;
    }
}
