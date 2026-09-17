<?php

declare(strict_types=1);
namespace App\Services;

use App\Services\Integrations\Http;
use RuntimeException;

/**
 * Temu Open API (sprzedawcy lokalni UE): App Key, App Secret i Access Token z Seller Center.
 * Zamówienia: bg.order.list.v2.get + bg.order.shippinginfo.v2.get + bg.order.amount.query.
 */
final class TemuService extends MarketplaceIntegration
{
    public const DEFAULT_API_URL = 'https://openapi-b-eu.temu.com/openapi/router';
    private const STATUSES = [1 => 'PENDING', 2 => 'UN_SHIPPING', 3 => 'CANCELED', 4 => 'SHIPPED', 5 => 'RECEIPTED', 41 => 'PARTIALLY_SHIPPED', 51 => 'PARTIALLY_RECEIPTED'];

    public function platform(): string { return 'temu'; }
    protected function label(): string { return 'Temu'; }

    public function testConnection(array $account): array
    {
        $response = $this->call($account, 'bg.order.list.v2.get', ['pageNumber' => 1, 'pageSize' => 1]);
        $count = (int) ($response['result']['totalItemNum'] ?? 0);
        return ['name' => 'Temu · '.(trim((string) ($account['shop_name'] ?? '')) ?: 'sklep'), 'remote_id' => substr(hash('sha256', (string) $account['app_key'].'|'.(string) $account['access_token']), 0, 20), 'public' => [], 'message' => 'Połączono z Temu. Zamówień na koncie: '.$count.'.'];
    }

    public function readOrderPage(array $account, string $from, string $to, string $cursor = '', string $updatedFrom = ''): array
    {
        $response = $this->call($account, 'bg.order.list.v2.get', [
            'pageNumber' => intdiv((int) $cursor, 50) + 1, 'pageSize' => 50,
            'updateAtStart' => strtotime($updatedFrom !== '' ? $updatedFrom : $from), 'updateAtEnd' => strtotime($to),
        ]);
        $orders = [];
        $page = (array) ($response['result']['pageItems'] ?? []);
        foreach ($page as $item) {
            if (is_array($item) && is_array($item['parentOrderMap'] ?? null)) { $orders[] = $this->canonical($account, $item); }
        }
        return ['orders' => $orders, 'total_count' => (int) ($response['result']['totalItemNum'] ?? 0), 'page_size' => count($page)];
    }

    private function canonical(array $account, array $item): array
    {
        $parent = $item['parentOrderMap'];
        $sn = (string) ($parent['parentOrderSn'] ?? '');
        $ship = [];
        $amount = [];
        try { $ship = (array) ($this->call($account, 'bg.order.shippinginfo.v2.get', ['parentOrderSn' => $sn])['result'] ?? []); } catch (\Throwable $e) { /* Adres bywa niedostępny przed wysyłką lub bez uprawnienia. */ }
        try { $amount = (array) ($this->call($account, 'bg.order.amount.query', ['parentOrderSn' => $sn])['result'] ?? []); } catch (\Throwable $e) { /* Kwota opcjonalna. */ }
        $name = trim((string) self::find($ship, ['receiptName', 'receiverName', 'name']));
        $parts = preg_split('/\s+/u', $name, 2) ?: [''];
        $items = [];
        foreach ((array) ($item['orderList'] ?? []) as $line) {
            if (!is_array($line)) { continue; }
            $sku = (string) ($line['productList'][0]['extCode'] ?? '');
            $items[] = ['name' => trim((string) ($line['goodsName'] ?? '').(!empty($line['spec']) ? ' ('.$line['spec'].')' : '')), 'sku' => $sku !== '' ? $sku : (string) ($line['skuId'] ?? ''), 'quantity' => (int) ($line['quantity'] ?? 1), 'price' => OrderNormalizer::decimal(self::money(self::find($line, ['unitRetailPriceVat', 'unitPrice', 'retailPrice'])) ?? 0), 'image_url' => (string) ($line['thumbUrl'] ?? '')];
        }
        $total = self::money(self::find($amount, ['totalAmount', 'parentOrderTotalAmount', 'orderAmount', 'totalPrice']));
        $status = (int) ($parent['parentOrderStatus'] ?? 0);
        return [
            'id' => $sn, 'created_at' => gmdate('c', (int) ($parent['parentOrderTime'] ?? 0)),
            'status' => self::STATUSES[$status] ?? ('STATUS_'.$status), 'currency' => (string) (self::find($amount, ['currency', 'currencyCode']) ?: 'PLN'),
            'total' => $total !== null ? OrderNormalizer::decimal($total) : '',
            'paid' => ($parent['orderPaymentType'] ?? '') === 'PPD' && !in_array($status, [1, 3], true),
            'payment_method' => 'Temu', 'cash_on_delivery' => ($parent['orderPaymentType'] ?? '') === 'COD',
            'shipping' => ['method' => 'Temu', 'price' => '0.00'],
            'customer' => ['email' => (string) self::find($ship, ['mail', 'email']), 'phone' => (string) self::find($ship, ['mobile', 'phone']), 'note' => ''],
            'shipping_address' => [
                'first_name' => $parts[0] ?? '', 'last_name' => $parts[1] ?? '',
                'street' => trim(implode(' ', array_filter([(string) self::find($ship, ['addressLine1']), (string) self::find($ship, ['addressLine2']), (string) self::find($ship, ['addressLine3'])]))),
                'postal_code' => (string) self::find($ship, ['postCode', 'postcode', 'zipCode']), 'city' => (string) self::find($ship, ['regionName3', 'city']),
                'country' => strlen((string) self::find($ship, ['regionCode1', 'countryCode'])) === 2 ? (string) self::find($ship, ['regionCode1', 'countryCode']) : 'PL',
                'phone' => (string) self::find($ship, ['mobile', 'phone']),
            ],
            'invoice' => ['required' => false], 'items' => $items,
        ];
    }

    /** Pierwsza wartość skalarna o jednej z nazw, szukana rekurencyjnie (odpowiedzi Temu różnią się między regionami). */
    private static function find(array $data, array $keys)
    {
        foreach ($keys as $key) { if (array_key_exists($key, $data) && (is_scalar($data[$key]) || is_array($data[$key]))) { return $data[$key]; } }
        foreach ($data as $value) { if (is_array($value)) { $found = self::find($value, $keys); if ($found !== null && $found !== '') { return $found; } } }
        return null;
    }

    /** Kwoty Temu: liczba, tekst albo {amount, currency}; wartości w groszach oznaczane polem „amount” w jednostkach mniejszych. */
    private static function money($value): ?string
    {
        if (is_array($value)) { $value = $value['amount'] ?? $value['value'] ?? null; }
        if ($value === null || $value === '' || !is_numeric(str_replace(',', '.', (string) $value))) { return null; }
        return (string) $value;
    }

    private function call(array $account, string $type, array $parameters): array
    {
        foreach (['app_key', 'app_secret', 'access_token'] as $key) {
            if (trim((string) ($account[$key] ?? '')) === '') { throw new RuntimeException('Temu API error [401]: uzupełnij App Key, App Secret i Access Token.'); }
        }
        $payload = array_merge(['app_key' => trim((string) $account['app_key']), 'access_token' => trim((string) $account['access_token']), 'data_type' => 'JSON', 'timestamp' => time(), 'type' => $type, 'version' => 'V1'], $parameters);
        $payload['sign'] = self::signature($payload, trim((string) $account['app_secret']));
        $url = trim((string) ($account['api_url'] ?? '')) ?: self::DEFAULT_API_URL;
        $decoded = Http::json('Temu', 'POST', $url, ['Accept: application/json', 'Content-Type: application/json'], $payload);
        if (empty($decoded['success'])) {
            $code = (string) ($decoded['errorCode'] ?? '');
            $status = in_array($code, ['2000010', '2000011', '3000001', '7000002'], true) ? 401 : 400;
            throw new RuntimeException('Temu API error ['.$status.']: '.(trim((string) ($decoded['errorMsg'] ?? '')) ?: 'nieznany błąd').($code !== '' ? ' ('.$code.')' : ''));
        }
        return $decoded;
    }

    public static function signature(array $parameters, string $appSecret): string
    {
        unset($parameters['sign']);
        ksort($parameters, SORT_STRING);
        $plain = $appSecret;
        foreach ($parameters as $key => $value) {
            if (is_array($value) || is_object($value)) { $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''; }
            elseif (is_bool($value)) { $value = $value ? 'true' : 'false'; }
            $plain .= $key.$value;
        }
        return strtoupper(md5($plain.$appSecret));
    }
}
