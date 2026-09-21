<?php

declare(strict_types=1);
namespace App\Services;

use App\Services\Integrations\Http;
use RuntimeException;

/**
 * WooCommerce REST API v3. Połączenie przez logowanie do WordPressa
 * (wc-auth – sklep sam przekazuje klucze) albo ręcznie wklejone klucze REST.
 */
final class WooCommerceService extends MarketplaceIntegration
{
    public function platform(): string { return 'woocommerce'; }
    protected function label(): string { return 'WooCommerce'; }

    /** Adres, pod którym właściciel sklepu zatwierdza dostęp (bez ręcznego tworzenia kluczy). */
    public static function authorizeUrl(string $shopUrl, string $userId, string $returnUrl, string $callbackUrl): string
    {
        return rtrim($shopUrl, '/').'/wc-auth/v1/authorize?'.http_build_query([
            'app_name' => 'SalesCenter', 'scope' => 'read_write', 'user_id' => $userId, 'return_url' => $returnUrl, 'callback_url' => $callbackUrl,
        ]);
    }

    public function testConnection(array $account): array
    {
        $this->api($account, 'GET', 'orders', ['per_page' => 1]);
        $name = parse_url((string) $account['shop_url'], PHP_URL_HOST) ?: 'sklep';
        try {
            $site = Http::json('WooCommerce', 'GET', rtrim((string) $account['shop_url'], '/').'/wp-json/', ['Accept: application/json'], null, 15, true);
            $name = trim(html_entity_decode((string) ($site['name'] ?? ''), ENT_QUOTES, 'UTF-8')) ?: $name;
        } catch (\Throwable $e) { /* Nazwa witryny jest opcjonalna. */ }
        return ['name' => 'WooCommerce · '.$name, 'remote_id' => (string) $account['shop_url'], 'public' => ['shop_name' => $name, 'remote_id' => (string) $account['shop_url']], 'message' => 'Połączono ze sklepem „'.$name.'”.'];
    }

    public function readOrderPage(array $account, string $from, string $to, string $cursor = '', string $updatedFrom = ''): array
    {
        $page = intdiv((int) $cursor, 100) + 1;
        $headers = [];
        $rows = $this->api($account, 'GET', 'orders', [
            'per_page' => 100, 'page' => $page, 'orderby' => 'modified', 'order' => 'asc', 'dates_are_gmt' => 'true',
            'after' => $from, 'modified_after' => $updatedFrom !== '' ? $updatedFrom : $from, 'modified_before' => $to,
        ], null, $headers);
        $orders = [];
        foreach ($rows as $order) {
            if (is_array($order) && ($order['status'] ?? '') !== 'checkout-draft') { $orders[] = $this->canonical($order); }
        }
        // Wersje draft są pomijane, ale liczą się do stronicowania – kursor przesuwa się o całą stronę.
        return ['orders' => $orders, 'total_count' => (int) ($headers['x-wp-total'] ?? 0), 'page_size' => count($rows)];
    }

    /** Numer przesyłki trafia jako notatka widoczna dla klienta (WooCommerce nie ma natywnego pola). */
    public function publishOrderShipment(array $account, string $orderId, string $tracking, string $carrierCode, string $carrierName): void
    {
        $this->api($account, 'POST', 'orders/'.(int) $orderId.'/notes', [], ['note' => 'Twoja przesyłka została nadana. Przewoźnik: '.trim($carrierName).', numer przesyłki: '.trim($tracking), 'customer_note' => true]);
    }

    private function canonical(array $order): array
    {
        $meta = [];
        foreach ((array) ($order['meta_data'] ?? []) as $entry) { if (is_array($entry) && is_scalar($entry['value'] ?? null)) { $meta[strtolower((string) $entry['key'])] = trim((string) $entry['value']); } }
        $nip = '';
        foreach (['_billing_nip', 'billing_nip', '_billing_vat', 'nip', '_nip', 'vat_number', '_billing_vat_number'] as $key) { if (($meta[$key] ?? '') !== '') { $nip = $meta[$key]; break; } }
        $billing = (array) ($order['billing'] ?? []); $shipping = (array) ($order['shipping'] ?? []);
        if (trim((string) ($shipping['address_1'] ?? '')) === '') { $shipping = $billing + $shipping; }
        $address = static function (array $a): array {
            return ['first_name' => (string) ($a['first_name'] ?? ''), 'last_name' => (string) ($a['last_name'] ?? ''), 'company' => (string) ($a['company'] ?? ''), 'street' => trim((string) ($a['address_1'] ?? '').' '.(string) ($a['address_2'] ?? '')), 'building' => '', 'postal_code' => (string) ($a['postcode'] ?? ''), 'city' => (string) ($a['city'] ?? ''), 'country' => (string) ($a['country'] ?? 'PL'), 'phone' => (string) ($a['phone'] ?? '')];
        };
        $items = [];
        foreach ((array) ($order['line_items'] ?? []) as $line) {
            $quantity = max(1, (int) ($line['quantity'] ?? 1));
            $gross = ((float) ($line['total'] ?? 0) + (float) ($line['total_tax'] ?? 0)) / $quantity;
            $items[] = ['name' => (string) ($line['name'] ?? ''), 'sku' => (string) ($line['sku'] ?? ''), 'quantity' => $quantity, 'price' => OrderNormalizer::decimal($gross), 'image_url' => (string) ($line['image']['src'] ?? '')];
        }
        $shippingLine = (array) ($order['shipping_lines'][0] ?? []);
        $created = (string) (($order['date_created_gmt'] ?? '') ?: ($order['date_created'] ?? ''));
        return [
            'id' => (string) $order['id'], 'number' => (string) ($order['number'] ?? ''),
            'created_at' => $created !== '' ? $created.'Z' : '', 'status' => (string) ($order['status'] ?? ''),
            'currency' => (string) ($order['currency'] ?? 'PLN'), 'total' => OrderNormalizer::decimal($order['total'] ?? '0'),
            'paid' => !empty($order['date_paid_gmt']) || in_array((string) ($order['status'] ?? ''), ['processing', 'completed'], true),
            'payment_method' => (string) (($order['payment_method_title'] ?? '') ?: ($order['payment_method'] ?? '')), 'cash_on_delivery' => ($order['payment_method'] ?? '') === 'cod',
            'shipping' => ['method' => (string) ($shippingLine['method_title'] ?? ''), 'price' => OrderNormalizer::decimal((float) ($order['shipping_total'] ?? 0) + (float) ($order['shipping_tax'] ?? 0))],
            'customer' => ['email' => (string) ($billing['email'] ?? ''), 'phone' => (string) ($billing['phone'] ?? ''), 'note' => (string) ($order['customer_note'] ?? '')],
            'shipping_address' => $address($shipping), 'invoice' => $address($billing) + ['nip' => $nip, 'required' => $nip !== ''], 'items' => $items,
        ];
    }

    /** Notatka zamówienia; customer_note=true – WooCommerce wysyła ją klientowi e-mailem (wiadomości SalesCenter). */
    public function addOrderNote(array $account, string $orderId, string $note, bool $forCustomer): array
    {
        if (!ctype_digit($orderId)) { throw new RuntimeException('WooCommerce: nieprawidłowy numer zamówienia.'); }
        return $this->api($account, 'POST', 'orders/'.$orderId.'/notes', [], ['note' => $note, 'customer_note' => $forCustomer]);
    }

    private function api(array $account, string $method, string $path, array $query = [], ?array $body = null, ?array &$responseHeaders = null): array
    {
        $key = trim((string) ($account['consumer_key'] ?? '')); $secret = trim((string) ($account['consumer_secret'] ?? ''));
        if ($key === '' || $secret === '') { throw new RuntimeException('WooCommerce API error [401]: brak kluczy REST API – połącz sklep ponownie.'); }
        // Klucze w parametrach zapytania (HTTPS) – wiele hostingów usuwa nagłówek Authorization.
        $url = rtrim((string) $account['shop_url'], '/').'/wp-json/wc/v3/'.$path.'?'.http_build_query($query + ['consumer_key' => $key, 'consumer_secret' => $secret]);
        $headers = ['Accept: application/json'];
        if ($body !== null) { $headers[] = 'Content-Type: application/json'; }
        return Http::json('WooCommerce', $method, $url, $headers, $body, 45, true, $responseHeaders);
    }
}
