<?php

declare(strict_types=1);
namespace App\Services;

use App\Services\Integrations\Http;
use RuntimeException;

/**
 * Sklep altreo.pl: adres sklepu + token API z panelu sklepu (Ustawienia → SalesCenter).
 * Sklep udostępnia /api/salescenter/{ping,orders,orders/{numer}/shipment}.
 */
final class AltreoService extends MarketplaceIntegration
{
    /** Statusy sklepu; kod trafia do „Status źródłowy” i służy do mapowania na status wewnętrzny. */
    public const STATUSES = ['nowe' => 'Nowe', 'w_realizacji' => 'W realizacji', 'wyslane' => 'Wysłane', 'zrealizowane' => 'Zrealizowane', 'anulowane' => 'Anulowane'];

    public function platform(): string { return 'altreo'; }
    protected function label(): string { return 'Altreo.pl'; }

    public function testConnection(array $account): array
    {
        $info = $this->api($account, 'GET', 'ping');
        if (empty($info['ok'])) { throw new RuntimeException('Altreo.pl: sklep nie potwierdził połączenia.'); }
        $name = trim((string) ($info['shop_name'] ?? '')) ?: (parse_url((string) $account['shop_url'], PHP_URL_HOST) ?: 'altreo.pl');
        return ['name' => 'Altreo.pl · '.$name, 'remote_id' => (string) $account['shop_url'], 'public' => ['shop_name' => $name, 'remote_id' => (string) $account['shop_url']], 'message' => 'Połączono ze sklepem „'.$name.'”.'];
    }

    public function readOrderPage(array $account, string $from, string $to, string $cursor = '', string $updatedFrom = ''): array
    {
        $response = $this->api($account, 'GET', 'orders', ['updated_from' => $updatedFrom !== '' ? $updatedFrom : $from, 'updated_to' => $to, 'offset' => (int) $cursor, 'limit' => 50]);
        if (!is_array($response['orders'] ?? null)) { throw new RuntimeException('Altreo.pl: nieoczekiwany format listy zamówień.'); }
        $orders = [];
        foreach ($response['orders'] as $order) {
            if (is_array($order)) { $orders[] = $this->canonical($account, $order); }
        }
        return ['orders' => $orders, 'total_count' => (int) ($response['total_count'] ?? 0), 'page_size' => count($response['orders'])];
    }

    /** Sklep zapisuje numer przesyłki, zmienia status na „Wysłane” i powiadamia klienta e-mailem. */
    public function publishOrderShipment(array $account, string $orderId, string $tracking, string $carrierCode, string $carrierName): void
    {
        $this->api($account, 'POST', 'orders/'.rawurlencode($orderId).'/shipment', [], ['tracking_number' => trim($tracking), 'carrier_code' => $carrierCode, 'carrier_name' => trim($carrierName)]);
    }

    private function canonical(array $account, array $order): array
    {
        $shopUrl = rtrim((string) $account['shop_url'], '/');
        $country = static function ($value): string {
            $value = mb_strtolower(trim((string) $value), 'UTF-8');
            return in_array($value, ['', 'polska', 'poland', 'pl'], true) ? 'PL' : strtoupper($value);
        };
        [$firstName, $lastName] = array_pad(preg_split('/\s+/u', trim((string) ($order['full_name'] ?? '')), 2) ?: [], 2, '');
        $shippingAddress = ['first_name' => $firstName, 'last_name' => $lastName, 'street' => (string) ($order['street'] ?? ''), 'postal_code' => (string) ($order['postcode'] ?? ''), 'city' => (string) ($order['city'] ?? ''), 'country' => $country($order['country'] ?? ''), 'phone' => (string) ($order['phone'] ?? '')];
        $invoiceData = is_array($order['invoice'] ?? null) ? $order['invoice'] : [];
        $invoice = ['required' => !empty($invoiceData['requested'])];
        if ($invoice['required']) {
            $invoice += ['company' => (string) ($invoiceData['company_name'] ?? ''), 'nip' => (string) ($invoiceData['nip'] ?? ''), 'street' => (string) (($invoiceData['street'] ?? '') ?: $shippingAddress['street']), 'postal_code' => (string) (($invoiceData['postcode'] ?? '') ?: $shippingAddress['postal_code']), 'city' => (string) (($invoiceData['city'] ?? '') ?: $shippingAddress['city'])];
        }
        $items = [];
        foreach ((array) ($order['items'] ?? []) as $item) {
            if (!is_array($item)) { continue; }
            $name = (string) ($item['name'] ?? '');
            $options = array_filter(array_map(static function ($option): string {
                return is_array($option) ? trim((string) ($option['group'] ?? '').': '.(string) ($option['value'] ?? ''), ': ') : '';
            }, (array) ($item['options'] ?? [])), 'strlen');
            if ($options) { $name .= ' ('.implode(', ', $options).')'; }
            $items[] = ['name' => $name, 'sku' => (string) ($item['sku'] ?? ''), 'ean' => (string) ($item['ean'] ?? ''), 'quantity' => (int) ($item['qty'] ?? 1), 'price' => OrderNormalizer::decimal($item['price'] ?? '0'), 'image_url' => (string) ($item['image_url'] ?? '')];
        }
        // Dopłata za metodę płatności jest osobną pozycją; rabat z kuponu wyrównuje linia korekty dokumentu.
        $paymentFee = OrderNormalizer::decimal($order['payment_fee'] ?? '0');
        if ((float) $paymentFee > 0) { $items[] = ['name' => 'Opłata za płatność: '.(string) ($order['payment_method'] ?? ''), 'sku' => '', 'quantity' => 1, 'price' => $paymentFee]; }
        $status = (string) ($order['status'] ?? 'nowe');
        return [
            'id' => (string) ($order['order_number'] ?? ''), 'number' => (string) ($order['order_number'] ?? ''),
            'created_at' => (string) ($order['created_at'] ?? ''),
            'status' => isset(self::STATUSES[$status]) ? $status : 'nowe',
            'currency' => (string) ($order['currency'] ?? 'PLN'), 'total' => OrderNormalizer::decimal($order['total'] ?? '0'),
            'paid' => ($order['payment_status'] ?? '') === 'oplacone',
            'payment_method' => (string) ($order['payment_method'] ?? ''),
            'shipping' => ['method' => (string) ($order['shipping_method'] ?? ''), 'price' => OrderNormalizer::decimal($order['shipping_cost'] ?? '0')],
            'customer' => ['email' => (string) ($order['email'] ?? ''), 'phone' => (string) ($order['phone'] ?? ''), 'name' => (string) ($order['full_name'] ?? ''), 'note' => trim(implode("\n", array_filter([(string) ($order['notes'] ?? ''), !empty($order['coupon_code']) ? 'Kod rabatowy: '.$order['coupon_code'] : ''], 'strlen')))],
            'shipping_address' => $shippingAddress, 'invoice' => $invoice, 'items' => $items,
            'admin_url' => strpos((string) ($order['admin_url'] ?? ''), $shopUrl.'/admin/') === 0 ? (string) $order['admin_url'] : '',
        ];
    }

    private function api(array $account, string $method, string $path, array $query = [], ?array $body = null): array
    {
        $token = trim((string) ($account['api_key'] ?? ''));
        if (strlen($token) < 32) { throw new RuntimeException('Altreo.pl: token API jest za krótki – skopiuj go z panelu sklepu (Ustawienia → SalesCenter).'); }
        $url = rtrim((string) $account['shop_url'], '/').'/api/salescenter/'.$path.($query ? '?'.http_build_query($query) : '');
        $headers = ['Authorization: Bearer '.$token, 'X-Api-Key: '.$token, 'Accept: application/json'];
        if ($body !== null) { $headers[] = 'Content-Type: application/json'; }
        try {
            return Http::json('Altreo.pl', $method, $url, $headers, $body, 30, true);
        } catch (RuntimeException $e) {
            if (strpos($e->getMessage(), '[401]') !== false) { throw new RuntimeException('Altreo.pl API error [401]: nieprawidłowy token API – wygeneruj go ponownie w panelu sklepu (Ustawienia → SalesCenter).', 0, $e); }
            if (strpos($e->getMessage(), '[404]') !== false && $path === 'ping') { throw new RuntimeException('Altreo.pl API error [404]: sklep nie ma jeszcze API SalesCenter albo adres sklepu jest błędny.', 0, $e); }
            throw $e;
        }
    }
}
