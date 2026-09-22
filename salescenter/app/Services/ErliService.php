<?php

declare(strict_types=1);
namespace App\Services;

use App\Services\Integrations\Http;
use App\Services\Integrations\Images;
use RuntimeException;

/** ERLI Shop API – klucz z panelu: Metoda integracji → Własna integracja po API. */
final class ErliService extends MarketplaceIntegration
{
    private const BASE = 'https://erli.pl/svc/shop-api';

    public function platform(): string { return 'erli'; }
    protected function label(): string { return 'ERLI'; }

    public function testConnection(array $account): array
    {
        $shop = $this->api($account, 'GET', '/me');
        $name = (string) ($shop['company']['name'] ?? $shop['name'] ?? 'sklep');
        return ['name' => 'ERLI · '.$name, 'remote_id' => (string) ($shop['id'] ?? ''), 'public' => ['shop_name' => $name, 'remote_id' => (string) ($shop['id'] ?? ''), 'shop_id' => (string) ($shop['id'] ?? '')], 'message' => 'Połączono ze sklepem ERLI „'.$name.'”.'];
    }

    public function readOrderPage(array $account, string $from, string $to, string $cursor = '', string $updatedFrom = ''): array
    {
        $pagination = ['sortField' => 'updated', 'order' => 'ASC', 'limit' => 100];
        if ($cursor !== '') { $pagination['after'] = $cursor; }
        $orders = $this->api($account, 'POST', '/orders/_search', [
            'pagination' => $pagination,
            'filter' => ['operator' => 'and', 'value' => [
                ['field' => 'created', 'operator' => '>=', 'value' => $from],
                ['field' => 'created', 'operator' => '<=', 'value' => $to],
                ['field' => 'updated', 'operator' => '>=', 'value' => $updatedFrom !== '' ? $updatedFrom : $from],
                ['field' => 'updated', 'operator' => '<=', 'value' => $to],
            ]],
        ]);
        try { return $this->withPayments($account, $orders); }
        catch (\Throwable $e) { return $orders; /* Brak metody płatności nigdy nie blokuje importu. */ }
    }

    /**
     * Zamówienie ERLI zawiera tylko payment.id/status (pole przestarzałe) – metodę
     * płatności (methodName/methodCode) zwraca wyłącznie POST /payments/_search.
     */
    public function withPayments(array $account, array $orders): array
    {
        $ids = [];
        foreach ($orders as $order) {
            if (is_array($order) && isset($order['payment']['id']) && is_numeric($order['payment']['id'])) { $ids[(int) $order['payment']['id']] = true; }
        }
        if (!$ids) { return $orders; }
        $payments = [];
        foreach (array_chunk(array_keys($ids), 200) as $chunk) {
            $rows = $this->api($account, 'POST', '/payments/_search', ['pagination' => ['sortField' => 'id', 'order' => 'ASC', 'limit' => 200], 'filter' => ['field' => 'id', 'operator' => 'in', 'value' => $chunk]]);
            foreach ($rows as $row) { if (is_array($row) && isset($row['id'])) { $payments[(int) $row['id']] = $row; } }
        }
        foreach ($orders as $index => $order) {
            $payment = is_array($order) && isset($order['payment']['id']) ? ($payments[(int) $order['payment']['id']] ?? null) : null;
            if (!$payment) { continue; }
            foreach (['methodName', 'methodCode', 'operator', 'status', 'completedAt'] as $field) {
                if (isset($payment[$field]) && $payment[$field] !== '') { $orders[$index]['payment'][$field] = $payment[$field]; }
            }
        }
        return $orders;
    }

    /** @var array<string,string> */
    private $imageCache = [];

    /** Zdjęcia pozycji z karty produktu ERLI (GET /products/{externalId}). */
    public function enrichOrderImages(array $account, array $order): array
    {
        foreach ($order['items'] ?? [] as $index => $line) {
            if (!is_array($line) || Images::first($line, "", false) !== '') { continue; }
            $externalId = trim((string) ($line['productExternalId'] ?? $line['externalId'] ?? $line['product']['externalId'] ?? ''));
            if ($externalId === '') { continue; }
            $key = ($account['connection_id'] ?? 0).'|'.$externalId;
            if (!array_key_exists($key, $this->imageCache)) {
                try { $this->imageCache[$key] = Images::first($this->api($account, 'GET', '/products/'.rawurlencode($externalId)), '', false); }
                catch (\Throwable $e) { $this->imageCache[$key] = ''; }
            }
            if ($this->imageCache[$key] !== '') { $order['items'][$index]['imageUrl'] = $this->imageCache[$key]; }
        }
        return $order;
    }

    public function publishOrderShipment(array $account, string $orderId, string $tracking, string $carrierCode, string $carrierName): void
    {
        $vendors = ['inpost' => 'inpost', 'pocztex' => 'pocztex24', 'dhl' => 'dhl', 'dpd' => 'dpd', 'fedex' => 'fedex', 'gls' => 'gls', 'ups' => 'ups', 'orlen' => 'orlen'];
        $vendor = $vendors[$carrierCode] ?? '';
        if ($vendor === '') { throw new RuntimeException('ERLI nie obsługuje wybranego przewoźnika „'.$carrierName.'”.'); }
        $response = $this->api($account, 'POST', '/shipping/external', [['vendor' => $vendor, 'status' => 'readyToSend', 'trackingNumber' => trim($tracking), 'orderId' => $orderId]]);
        if (!empty($response[0]['error'])) { throw new RuntimeException('ERLI odrzuciło numer przesyłki.'); }
    }

    private function api(array $account, string $method, string $path, ?array $body = null): array
    {
        $key = trim((string) ($account['api_key'] ?? ''));
        if ($key === '') { throw new RuntimeException('ERLI API error [401]: brak klucza API.'); }
        $headers = ['Accept: application/json', 'Authorization: Bearer '.$key];
        if ($body !== null) { $headers[] = 'Content-Type: application/json'; }
        return Http::json('ERLI', $method, self::BASE.$path, $headers, $body);
    }
}
