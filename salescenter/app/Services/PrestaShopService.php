<?php

declare(strict_types=1);
namespace App\Services;

use App\Services\Integrations\Http;
use RuntimeException;

/** PrestaShop Webservice (1.7 / 8 / 9): adres sklepu + klucz webservice. */
final class PrestaShopService extends MarketplaceIntegration
{
    /** @var array<string,array> */
    private $cache = [];

    public function platform(): string { return 'prestashop'; }
    protected function label(): string { return 'PrestaShop'; }

    public function testConnection(array $account): array
    {
        $this->get($account, 'orders', ['display' => '[id]', 'limit' => '1']);
        $name = parse_url((string) $account['shop_url'], PHP_URL_HOST) ?: 'sklep';
        try {
            $config = $this->get($account, 'configurations', ['filter[name]' => 'PS_SHOP_NAME', 'display' => '[value]']);
            $name = trim((string) ($config['configurations'][0]['value'] ?? '')) ?: $name;
        } catch (\Throwable $e) { /* Klucz może nie mieć dostępu do konfiguracji – nazwa z domeny wystarczy. */ }
        return ['name' => 'PrestaShop · '.$name, 'remote_id' => (string) $account['shop_url'], 'public' => ['shop_name' => $name, 'remote_id' => (string) $account['shop_url']], 'message' => 'Połączono ze sklepem „'.$name.'”.'];
    }

    public function readOrderPage(array $account, string $from, string $to, string $cursor = '', string $updatedFrom = ''): array
    {
        // Daty w webservice są w strefie sklepu; margines 2 h nie gubi zamówień (import nie tworzy duplikatów).
        $zone = new \DateTimeZone('Europe/Warsaw');
        $start = (new \DateTimeImmutable('@'.(strtotime($updatedFrom !== '' ? $updatedFrom : $from) - 7200)))->setTimezone($zone)->format('Y-m-d H:i:s');
        $end = (new \DateTimeImmutable('@'.(strtotime($to) + 7200)))->setTimezone($zone)->format('Y-m-d H:i:s');
        $offset = (int) $cursor;
        $response = $this->get($account, 'orders', ['display' => 'full', 'date' => '1', 'filter[date_upd]' => '['.$start.','.$end.']', 'sort' => '[date_upd_ASC]', 'limit' => $offset.',50']);
        $orders = [];
        foreach ((array) ($response['orders'] ?? []) as $order) {
            if (is_array($order)) { $orders[] = $this->canonical($account, $order); }
        }
        return ['orders' => $orders];
    }

    /** Wpisuje numer przesyłki w order_carriers zamówienia (widoczny dla klienta w sklepie). */
    public function publishOrderShipment(array $account, string $orderId, string $tracking, string $carrierCode, string $carrierName): void
    {
        $list = $this->get($account, 'order_carriers', ['filter[id_order]' => '['.(int) $orderId.']', 'display' => 'full']);
        $carrier = $list['order_carriers'][0] ?? null;
        if (!is_array($carrier)) { throw new RuntimeException('PrestaShop: zamówienie nie ma przypisanego przewoźnika.'); }
        $carrier['tracking_number'] = mb_substr(trim($tracking), 0, 64, 'UTF-8');
        $xml = new \SimpleXMLElement('<prestashop xmlns:xlink="http://www.w3.org/1999/xlink"><order_carrier/></prestashop>');
        foreach ($carrier as $field => $value) {
            if (is_scalar($value) || $value === null) { $xml->order_carrier->addChild((string) $field, htmlspecialchars((string) $value, ENT_XML1, 'UTF-8')); }
        }
        $response = Http::request('PrestaShop', 'PUT', $this->url($account, 'order_carriers/'.(int) $carrier['id'], []), $this->headers($account, 'Content-Type: text/xml'), (string) $xml->asXML(), 30, true);
        if ($response['status'] < 200 || $response['status'] >= 300) { throw new RuntimeException('PrestaShop API error ['.$response['status'].']: nie zapisano numeru przesyłki (klucz potrzebuje uprawnienia PUT dla order_carriers).'); }
    }

    private function canonical(array $account, array $order): array
    {
        $delivery = $this->resource($account, 'addresses', (int) ($order['id_address_delivery'] ?? 0), 'address');
        $invoiceAddress = $this->resource($account, 'addresses', (int) ($order['id_address_invoice'] ?? 0), 'address');
        $customer = $this->resource($account, 'customers', (int) ($order['id_customer'] ?? 0), 'customer');
        $state = $this->resource($account, 'order_states', (int) ($order['current_state'] ?? 0), 'order_state');
        $carrier = $this->resource($account, 'carriers', (int) ($order['id_carrier'] ?? 0), 'carrier');
        $currency = $this->resource($account, 'currencies', (int) ($order['id_currency'] ?? 0), 'currency');
        $address = function (array $a) use ($account): array {
            $country = $this->resource($account, 'countries', (int) ($a['id_country'] ?? 0), 'country');
            return ['first_name' => (string) ($a['firstname'] ?? ''), 'last_name' => (string) ($a['lastname'] ?? ''), 'company' => (string) ($a['company'] ?? ''), 'nip' => (string) ($a['vat_number'] ?? ''), 'street' => trim((string) ($a['address1'] ?? '').' '.(string) ($a['address2'] ?? '')), 'building' => '', 'postal_code' => (string) ($a['postcode'] ?? ''), 'city' => (string) ($a['city'] ?? ''), 'country' => (string) ($country['iso_code'] ?? 'PL'), 'phone' => (string) (($a['phone_mobile'] ?? '') ?: ($a['phone'] ?? ''))];
        };
        $items = [];
        foreach ((array) ($order['associations']['order_rows'] ?? []) as $row) {
            $items[] = ['name' => (string) ($row['product_name'] ?? ''), 'sku' => (string) (($row['product_reference'] ?? '') ?: ($row['product_ean13'] ?? '')), 'quantity' => (int) ($row['product_quantity'] ?? 1), 'price' => OrderNormalizer::decimal($row['unit_price_tax_incl'] ?? '0')];
        }
        $invoice = $invoiceAddress ? $address($invoiceAddress) : [];
        $invoice['required'] = trim((string) ($invoice['company'] ?? '')) !== '' || trim((string) ($invoice['nip'] ?? '')) !== '';
        $module = strtolower((string) ($order['module'] ?? ''));
        return [
            'id' => (string) $order['id'], 'number' => (string) ($order['reference'] ?? ''),
            'created_at' => (new \DateTimeImmutable((string) $order['date_add'], new \DateTimeZone('Europe/Warsaw')))->setTimezone(new \DateTimeZone('UTC'))->format('c'),
            'status' => $this->text($state['name'] ?? '') ?: 'Stan '.(string) ($order['current_state'] ?? ''),
            'currency' => (string) ($currency['iso_code'] ?? 'PLN'), 'total' => OrderNormalizer::decimal($order['total_paid_tax_incl'] ?? $order['total_paid'] ?? '0'),
            'paid' => !empty($state['paid']) || (float) ($order['total_paid_real'] ?? 0) > 0,
            'payment_method' => (string) ($order['payment'] ?? ''), 'cash_on_delivery' => strpos($module, 'cod') !== false || strpos($module, 'cashondelivery') !== false,
            'shipping' => ['method' => $this->text($carrier['name'] ?? ''), 'price' => OrderNormalizer::decimal($order['total_shipping_tax_incl'] ?? '0')],
            'customer' => ['email' => (string) ($customer['email'] ?? ''), 'phone' => (string) (($delivery['phone_mobile'] ?? '') ?: ($delivery['phone'] ?? '')), 'note' => ''],
            'shipping_address' => $delivery ? $address($delivery) : [], 'invoice' => $invoice, 'items' => $items,
        ];
    }

    private function text($value): string
    {
        if (is_array($value)) { $value = $value[0]['value'] ?? reset($value); if (is_array($value)) { $value = $value['value'] ?? ''; } }
        return trim((string) $value);
    }

    private function resource(array $account, string $type, int $id, string $key): array
    {
        if ($id < 1) { return []; }
        $cacheKey = $account['connection_id'].'|'.$type.'|'.$id;
        if (!isset($this->cache[$cacheKey])) {
            try { $this->cache[$cacheKey] = (array) ($this->get($account, $type.'/'.$id, [])[$key] ?? []); }
            catch (\Throwable $e) { $this->cache[$cacheKey] = []; }
        }
        return $this->cache[$cacheKey];
    }

    /** Odczyt zasobu webservice (JSON) – używany też przez wiadomości (customer_threads, customer_messages). */
    public function webserviceGet(array $account, string $path, array $query = []): array
    {
        return $this->get($account, $path, $query);
    }

    /** Utworzenie zasobu webservice z treścią XML (zapis przez API PrestaShop wymaga XML). */
    public function webserviceCreate(array $account, string $path, string $xml): array
    {
        $response = Http::request('PrestaShop', 'POST', $this->url($account, $path, ['output_format' => 'JSON']), $this->headers($account, 'Content-Type: application/xml'), $xml, 30, true);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            $hint = $response['status'] === 401 ? 'nieprawidłowy klucz webservice' : ($response['status'] === 403 || $response['status'] === 405 ? 'klucz nie ma uprawnienia POST do „'.$path.'”' : Http::reason(json_decode($response['body'], true), strip_tags($response['body'])));
            throw new RuntimeException('PrestaShop API error ['.$response['status'].']: '.$hint);
        }
        return json_decode($response['body'], true) ?: [];
    }

    private function get(array $account, string $path, array $query): array
    {
        $response = Http::request('PrestaShop', 'GET', $this->url($account, $path, $query + ['output_format' => 'JSON']), $this->headers($account), null, 30, true);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            $hint = $response['status'] === 401 ? 'nieprawidłowy klucz webservice' : ($response['status'] === 403 || $response['status'] === 405 ? 'klucz nie ma uprawnień do „'.$path.'” albo webservice jest wyłączony' : Http::reason(json_decode($response['body'], true), $response['body']));
            throw new RuntimeException('PrestaShop API error ['.$response['status'].']: '.$hint);
        }
        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) { throw new RuntimeException('PrestaShop: odpowiedź nie jest JSON – sprawdź, czy adres sklepu jest poprawny i webservice włączony.'); }
        return $decoded;
    }

    private function url(array $account, string $path, array $query): string
    {
        return rtrim((string) $account['shop_url'], '/').'/api/'.$path.($query ? '?'.http_build_query($query) : '');
    }

    private function headers(array $account, string ...$extra): array
    {
        return array_merge(['Authorization: Basic '.base64_encode((string) $account['api_key'].':'), 'Accept: application/json'], $extra);
    }
}
