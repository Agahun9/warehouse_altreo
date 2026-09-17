<?php

declare(strict_types=1);
namespace App\Services;

use App\Services\Integrations\Http;
use RuntimeException;

/**
 * Morele Marketplace API: Client ID + Client Secret z panelu (API → Wygeneruj nowe API).
 * Autoryzacja: POST /auth/register (Basic) → para tokenów; zamówienia: GET /orders.
 */
final class MoreleService extends MarketplaceIntegration
{
    private const BASE = 'https://api-marketplace.morele.net';

    public function platform(): string { return 'morele'; }
    protected function label(): string { return 'Morele'; }

    /** @var array Tokeny uzyskane w tym żądaniu – zapisywane w połączeniu także przy pierwszym łączeniu. */
    private $issued = [];
    private $offerImages = [];

    public function testConnection(array $account): array
    {
        $this->issued = [];
        $this->api($account, 'GET', '/orders');
        return ['name' => 'Morele · '.(trim((string) ($account['shop_name'] ?? '')) ?: 'sklep'), 'remote_id' => (string) $account['client_id'], 'public' => ['remote_id' => (string) $account['client_id']], 'secret' => $this->issued, 'message' => 'Morele potwierdziło dane dostępowe API.'];
    }

    public function refreshAccessToken(array $account): void
    {
        $this->token($account, true);
    }

    /**
     * Dokumentacja: GET /orders. Daty filtrujemy lokalnie, bo serwer odrzuca
     * format ISO 8601 opisany w specyfikacji parametru date_created_from.
     */
    public function readOrderPage(array $account, string $from, string $to, string $cursor = '', string $updatedFrom = ''): array
    {
        $response = $this->api($account, 'GET', '/orders');
        $orders = [];
        foreach (self::list($response) as $order) {
            $canonical = $this->canonical($order);
            if ($canonical['id'] !== '' && $canonical['created_at'] !== '') { $orders[] = $canonical; }
        }
        return ['orders' => $orders, 'page_size' => 0];
    }

    private static function list(array $response): array
    {
        if ($response !== [] && array_keys($response) === range(0, count($response) - 1)) { return array_filter($response, 'is_array'); }
        foreach (['orders', 'items', 'data', 'list', 'results'] as $key) {
            if (is_array($response[$key] ?? null)) { return self::list($response[$key]); }
        }
        return [];
    }

    private function canonical(array $o): array
    {
        $pick = static function (array $data, array $keys, $default = '') {
            foreach ($keys as $key) { if (isset($data[$key]) && $data[$key] !== '') { return $data[$key]; } }
            return $default;
        };
        $address = (array) $pick($o, ['delivery_address', 'shipping_address', 'address', 'deliveryAddress', 'customer'], []);
        $buyer = (array) $pick($o, ['customer', 'buyer', 'client'], []);
        $invoice = (array) $pick($o, ['invoice_address', 'billing_address', 'invoice', 'invoiceAddress'], []);
        $items = [];
        foreach ((array) $pick($o, ['products', 'items', 'order_items', 'lines', 'positions'], []) as $line) {
            if (!is_array($line)) { continue; }
            $items[] = ['name' => (string) $pick($line, ['vendor_product_name', 'name', 'product_name', 'title']), 'sku' => (string) $pick($line, ['part_number', 'sku', 'offer_id', 'ean', 'code', 'product_id']), 'quantity' => (int) $pick($line, ['quantity', 'qty', 'amount'], 1), 'price' => OrderNormalizer::decimal($pick($line, ['sale_price_brutto', 'price_gross', 'price', 'unit_price', 'priceGross'], 0)), 'image_url' => (string) $pick($line, ['image_url', 'thumbnail_url'], '')];
        }
        $map = static function (array $a, string $prefix = '') use ($pick): array {
            $name = trim((string) $pick($a, [$prefix.'name', 'name']));
            $parts = explode(' ', $name, 2);
            return ['first_name' => (string) $pick($a, ['first_name', 'firstname', 'firstName'], $parts[0] ?? ''), 'last_name' => (string) $pick($a, ['last_name', 'lastname', 'lastName', 'surname'], $parts[1] ?? ''), 'company' => (string) $pick($a, [$prefix.'company', 'company', 'company_name']), 'nip' => (string) $pick($a, [$prefix.'nip', 'nip', 'vat_id', 'tax_id']), 'street' => (string) $pick($a, [$prefix.'street_name', $prefix.'street', 'street', 'address', 'address1']), 'building' => (string) $pick($a, [$prefix.'street_number', 'building', 'house_number', 'building_number']), 'postal_code' => (string) $pick($a, [$prefix.'postal_code', 'postal_code', 'post_code', 'postcode', 'zip']), 'city' => (string) $pick($a, [$prefix.'city', 'city']), 'country' => (string) $pick($a, [$prefix.'country', 'country_code', 'country'], 'PL'), 'phone' => (string) $pick($a, [$prefix.'phone', 'phone', 'phone_number'])];
        };
        $invoiceData = $map($invoice ?: $buyer, $invoice ? '' : 'billing_');
        $paymentModes = [1 => 'Płatność przy odbiorze', 2 => 'Przelew', 3 => 'Karta online', 4 => 'Raty', 5 => 'Leasing'];
        $paymentMode = (int) ($o['payment_mode_id'] ?? 0);
        $paymentMethod = $paymentModes[$paymentMode] ?? (string) $pick($o, ['payment_method', 'payment', 'payment_type']);
        return [
            'id' => (string) $pick($o, ['order_id', 'id', 'orderId', 'number']), 'created_at' => (string) $pick($o, ['date_created', 'created_at', 'createdAt', 'date_add', 'order_date', 'created', 'date']),
            'status' => (string) $pick($o, ['status', 'state', 'order_status'], 'new'), 'currency' => (string) $pick($o, ['currency'], 'PLN'),
            'total' => ($total = $pick($o, ['order_value', 'total_gross', 'total', 'amount', 'price_gross', 'total_price', 'total_amount'], '')) === '' ? '' : OrderNormalizer::decimal($total),
            'paid' => array_key_exists('payment_status', $o) ? (int) $o['payment_status'] === 1 : (bool) $pick($o, ['paid', 'is_paid', 'payment_status_paid'], false), 'payment_method' => $paymentMethod,
            'shipping' => ['method' => (string) $pick($o, ['delivery_method', 'shipping_method', 'delivery']), 'price' => OrderNormalizer::decimal($pick($o, ['delivery_price', 'shipping_price', 'shipping_cost'], 0)), 'pickup_point' => (string) $pick($o, ['pickup_point', 'pickup_point_id'])],
            'customer' => ['email' => (string) $pick($buyer + $o, ['email', 'customer_email']), 'phone' => (string) $pick($buyer + $address, ['phone', 'phone_number', 'shipping_phone']), 'note' => (string) $pick($o, ['comment', 'note', 'customer_comment'])],
            'shipping_address' => $map($address, $address === $buyer ? 'shipping_' : ''), 'invoice' => $invoiceData + ['required' => $invoiceData['nip'] !== ''], 'items' => $items,
        ];
    }

    /** GET /offer returns images[].src; order products themselves contain no image. */
    public function enrichOrderImages(array $account, array $order): array
    {
        foreach ((array) ($order['items'] ?? []) as $index => $item) {
            if (!is_array($item) || !empty($item['image_url'])) { continue; }
            $sku = trim((string) ($item['sku'] ?? ''));
            if ($sku === '') { continue; }
            if (!array_key_exists($sku, $this->offerImages)) {
                $this->offerImages[$sku] = '';
                try {
                    $offers = self::list($this->api($account, 'GET', '/offer', ['vendor_part_number' => $sku, 'limit' => 1]));
                    foreach ($offers as $offer) {
                        if ((string) ($offer['vendor_part_number'] ?? '') !== $sku) { continue; }
                        foreach ((array) ($offer['images'] ?? []) as $image) {
                            $url = trim((string) ($image['src'] ?? ''));
                            if (preg_match('#^https://[^\s]+$#i', $url)) {
                                $this->offerImages[$sku] = $url;
                                if (!empty($image['is_main'])) { break; }
                            }
                        }
                    }
                } catch (\Throwable $e) { /* Brak miniatury nie zatrzymuje importu. */ }
            }
            if ($this->offerImages[$sku] !== '') { $order['items'][$index]['image_url'] = $this->offerImages[$sku]; }
        }
        return $order;
    }

    private function api(array $account, string $method, string $path, array $query = []): array
    {
        $url = self::BASE.$path.($query ? '?'.http_build_query($query) : '');
        try {
            return Http::json('Morele', $method, $url, ['Accept: application/json', 'Authorization: Bearer '.$this->token($account, false)]);
        } catch (RuntimeException $e) {
            if (strpos($e->getMessage(), '[401]') === false || strpos($e->getMessage(), 'Wygeneruj') !== false) { throw $e; }
            return Http::json('Morele', $method, $url, ['Accept: application/json', 'Authorization: Bearer '.$this->token($account, true)]);
        }
    }

    /**
     * Morele wydaje token tylko raz dla pary Client ID/Secret (POST /auth/register); później wyłącznie
     * POST /auth/refresh z refresh_token. Dlatego tokeny zapisujemy natychmiast po otrzymaniu.
     */
    private function token(array &$account, bool $force): string
    {
        if (!$force && trim((string) ($account['access_token'] ?? '')) !== '') { return (string) $account['access_token']; }
        $clientId = trim((string) ($account['client_id'] ?? '')); $clientSecret = trim((string) ($account['client_secret'] ?? ''));
        if ($clientId === '' || $clientSecret === '') { throw new RuntimeException('Morele API error [401]: uzupełnij Client ID i Client Secret.'); }
        $basic = 'Authorization: Basic '.base64_encode($clientId.':'.$clientSecret);
        $response = null;
        $refresh = trim((string) ($account['refresh_token'] ?? ''));
        if ($refresh !== '') {
            $response = Http::json('Morele', 'POST', self::BASE.'/auth/refresh', ['Accept: application/json', $basic, 'Content-Type: application/x-www-form-urlencoded'], http_build_query(['refresh_token' => $refresh]));
        } else {
            try {
                $response = Http::json('Morele', 'POST', self::BASE.'/auth/register', ['Accept: application/json', $basic]);
            } catch (RuntimeException $e) {
                if (stripos($e->getMessage(), 'already registered') !== false) {
                    throw new RuntimeException('Morele API error [401]: te dane API zostały już raz użyte do pobrania tokenu (np. w innym programie), a Morele nie wyda go ponownie. Wygeneruj w panelu Morele nowe API („API → Wygeneruj nowe API”) tylko dla SalesCenter i wklej nowe Client ID oraz Client Secret w ustawieniach połączenia.');
                }
                throw $e;
            }
        }
        $token = trim((string) ($response['access_token'] ?? ''));
        if ($token === '') { throw new RuntimeException('Morele API error [401]: API nie zwróciło tokenu dostępu.'); }
        $secret = ['access_token' => $token, 'refresh_token' => (string) (($response['refresh_token'] ?? '') ?: $refresh)];
        $account = array_merge($account, $secret);
        $this->issued = $secret;
        if (!empty($account['connection_id'])) { $this->storeSecret($account, $secret); }
        return $token;
    }

}
