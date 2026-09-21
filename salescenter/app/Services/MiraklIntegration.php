<?php

declare(strict_types=1);
namespace App\Services;

use App\Services\Integrations\Http;
use App\Services\Integrations\Images;
use RuntimeException;

/** Mirakl Marketplace Platform (Empik, MediaMarkt): klucz API sprzedawcy w nagłówku Authorization. */
abstract class MiraklIntegration extends MarketplaceIntegration
{
    abstract public function defaultApiUrl(): string;

    public function testConnection(array $account): array
    {
        $shop = $this->api($account, 'GET', '/api/account');
        $name = trim((string) ($shop['shop_name'] ?? ''));
        return ['name' => $this->label().($name !== '' ? ' · '.$name : ''), 'remote_id' => (string) ($shop['shop_id'] ?? ''), 'public' => ['shop_name' => $name, 'remote_id' => (string) ($shop['shop_id'] ?? '')], 'message' => 'Połączono ze sklepem „'.($name !== '' ? $name : $this->label()).'”.'];
    }

    /** @var array<string,string> */
    private $imageCache = [];

    /** Locale opisów produktów w P11 (Empik: pl_PL). */
    protected function productLocale(): string { return ''; }

    /**
     * Zdjęcia pozycji: product_medias z OR11 (adresy względne uzupełniane o adres Mirakl),
     * a gdy brak – P11 (produkty i oferty) po SKU produktu.
     */
    public function enrichOrderImages(array $account, array $order): array
    {
        $base = rtrim(trim((string) ($account['api_url'] ?? '')) ?: $this->defaultApiUrl(), '/');
        foreach ($order['order_lines'] ?? [] as $index => $line) {
            if (!is_array($line) || Images::first($line['image_url'] ?? '') !== '') { continue; }
            $url = Images::first($line['product_medias'] ?? [], $base);
            $productSku = trim((string) ($line['product_sku'] ?? ''));
            if ($url === '' && $productSku !== '') {
                $key = ($account['connection_id'] ?? 0).'|'.$productSku;
                if (!array_key_exists($key, $this->imageCache)) {
                    try {
                        $query = ['product_ids' => $productSku, 'max' => 1];
                        if ($this->productLocale() !== '') { $query['locale'] = $this->productLocale(); }
                        $this->imageCache[$key] = Images::first($this->api($account, 'GET', '/api/products/offers', $query), $base, false);
                    } catch (\Throwable $e) { $this->imageCache[$key] = ''; }
                }
                $url = $this->imageCache[$key];
            }
            if ($url !== '') { $order['order_lines'][$index]['image_url'] = $url; }
        }
        return $order;
    }

    /** OR11 – filtr po dacie aktualizacji, żeby docierały też późniejsze zmiany (np. płatność). */
    public function readOrderPage(array $account, string $from, string $to, string $cursor = '', string $updatedFrom = ''): array
    {
        return $this->api($account, 'GET', '/api/orders', [
            'max' => 100, 'offset' => (int) $cursor, 'paginate' => 'true',
            'start_update_date' => $updatedFrom !== '' ? $updatedFrom : $from, 'end_update_date' => $to,
            'sort' => 'dateCreated', 'order' => 'asc',
        ]);
    }

    /** OR21 – akceptuje wszystkie pozycje zamówienia w stanie WAITING_ACCEPTANCE. */
    public function acceptOrder(array $account, array $rawOrder): void
    {
        $orderId = trim((string) ($rawOrder['order_id'] ?? ''));
        $lines = [];
        foreach ($rawOrder['order_lines'] ?? [] as $line) {
            $lineId = trim((string) ($line['order_line_id'] ?? $line['id'] ?? ''));
            if ($lineId !== '') { $lines[] = ['id' => $lineId, 'accepted' => true]; }
        }
        if ($orderId === '' || !$lines) { throw new RuntimeException($this->label().': zamówienie bez pozycji do akceptacji.'); }
        $this->api($account, 'PUT', '/api/orders/'.rawurlencode($orderId).'/accept', [], ['order_lines' => $lines]);
    }

    /** OR23 – numer przesyłki z dopasowaniem przewoźnika z listy Mirakl (SH21). */
    public function publishOrderShipment(array $account, string $orderId, string $tracking, string $carrierCode, string $carrierName): void
    {
        $carriers = $this->api($account, 'GET', '/api/shipping/carriers');
        $payload = OrderMarketplaceShipmentService::miraklCarrierPayload(is_array($carriers['carriers'] ?? null) ? $carriers['carriers'] : $carriers, $carrierCode, $carrierName);
        $payload['tracking_number'] = trim($tracking);
        $this->api($account, 'PUT', '/api/orders/'.rawurlencode($orderId).'/tracking', [], $payload);
    }

    public function api(array $account, string $method, string $path, array $query = [], ?array $body = null): array
    {
        [$url, $headers] = $this->endpoint($account, $path, $query);
        if ($body !== null) { $headers[] = 'Content-Type: application/json'; }
        return Http::json($this->label(), $method, $url, $headers, $body);
    }

    /** POST multipart/form-data (wiadomości M12, OR43): część JSON przekazywana jako pole formularza. */
    public function apiMultipart(array $account, string $path, array $parts, array $query = []): array
    {
        [$url, $headers] = $this->endpoint($account, $path, $query);
        [$contentType, $body] = Http::multipart($parts);
        $headers[] = $contentType;
        return Http::json($this->label(), 'POST', $url, $headers, $body);
    }

    public function displayName(): string { return $this->label(); }

    private function endpoint(array $account, string $path, array $query): array
    {
        $key = trim((string) ($account['api_key'] ?? ''));
        if ($key === '') { throw new RuntimeException($this->label().' API error [401]: brak klucza API.'); }
        if (trim((string) ($account['shop_id'] ?? '')) !== '') { $query['shop_id'] = (int) $account['shop_id']; }
        $base = rtrim(trim((string) ($account['api_url'] ?? '')) ?: $this->defaultApiUrl(), '/');
        return [$base.$path.($query ? '?'.http_build_query($query) : ''), ['Accept: application/json', 'Authorization: '.$key]];
    }
}
