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

    /** @var callable|null Log diagnostyczny centrum komunikacji (ustawiany przez MoreleMessages); nagłówków nie logujemy. */
    public $logger = null;

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
            $items[] = ['name' => (string) $pick($line, ['vendor_product_name', 'name', 'product_name', 'title']), 'sku' => (string) $pick($line, ['part_number', 'sku', 'offer_id', 'ean', 'code', 'product_id']), 'quantity' => (int) $pick($line, ['quantity', 'qty', 'amount'], 1), 'price' => OrderNormalizer::decimal($pick($line, ['sale_price_brutto', 'price_gross', 'price', 'unit_price', 'priceGross'], 0)), 'image_url' => (string) $pick($line, ['image_url', 'thumbnail_url'], ''), 'offer_id' => (string) $pick($line, ['offer_id', 'product_id', 'id'], ''), 'offer_url' => (string) $pick($line, ['product_url', 'offer_url', 'url', 'link'], '')];
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

    /** Statusy zamówienia Morele: 1 nowe, 2 w realizacji, 3 wysłane, 4 zrealizowane, 5 kosz. */
    private const STATUS_COMPLETED = 4;
    private const STATUS_TRASH = 5;

    /** Linki śledzenia według kodów z OrderMarketplaceShipmentService::carrierOptions(). */
    private const TRACKING_URLS = ['inpost' => 'https://inpost.pl/sledzenie-przesylek?number=', 'dpd' => 'https://tracktrace.dpd.com.pl/parcelDetails?typ=1&p1=', 'gls' => 'https://gls-group.com/PL/pl/sledzenie-paczek?match=', 'dhl' => 'https://www.dhl.com/pl-pl/home/sledzenie-przesylek.html?tracking-id=', 'ups' => 'https://www.ups.com/track?tracknum=', 'fedex' => 'https://www.fedex.com/fedextrack/?trknbr=', 'orlen' => 'https://www.orlenpaczka.pl/sledz-paczke/?numer=', 'pocztex' => 'https://emonitoring.poczta-polska.pl/?numer='];

    /**
     * Numer przesyłki: POST /order/waybill, potem status „zrealizowane”: POST /order {order_id, status}.
     * Przykłady ze specyfikacji GET /v1/docs nie działają: serwer zamienia klucze na camelCase i odrzuca nieznane
     * pola („Field waybill not found!”, „Field trackingNumber not found!” – także tracking_number w POST /order).
     * W /order/waybill rozpoznaje waybill_number (jak GET /orders), ale sam numer kończy się „Wrong input data!”.
     * Dlatego dokładamy kolejne pola-kandydatów (link śledzenia, przewoźnik, status): „Field … not found!” = pola
     * nie ma, więc je pomijamy; inna odpowiedź = pole istnieje, zostaje w treści. Odrzucone żądania nic nie zapisują.
     * Przyjęcie potwierdzamy odczytem waybill_number z GET /orders. Zamówienia już zrealizowanego (4) nie przestawiamy.
     */
    public function publishOrderShipment(array $account, string $orderId, string $tracking, string $carrierCode, string $carrierName): void
    {
        $orderId = trim($orderId); $tracking = trim($tracking);
        if ($orderId === '' || $tracking === '') { throw new RuntimeException('Morele: brak numeru zamówienia albo przesyłki.'); }
        $id = ctype_digit($orderId) ? (int) $orderId : $orderId;
        $before = $this->orderState($account, $id);
        if ($before !== null && $before['status'] >= self::STATUS_TRASH) { throw new RuntimeException('Morele: zamówienie '.$orderId.' jest w koszu – nie można przekazać numeru przesyłki.'); }
        $status = self::STATUS_COMPLETED;
        $completed = $before !== null && $before['status'] >= self::STATUS_COMPLETED;
        if ($completed && $before['waybill'] === $tracking) { return; }

        $error = $this->sendWaybill($account, $id, $tracking, $carrierCode, $carrierName, $status);
        $response = $completed ? ['ok' => true, 'message' => ''] : $this->send($account, '/order', ['order_id' => $id, 'status' => $status]);
        if ($error !== null) { throw new RuntimeException('Morele nie przyjęło numeru przesyłki (POST /order/waybill) – '.$error.($response['ok'] ? '. Status „zrealizowane” ustawiony.' : '')); }
        if (!$response['ok']) { throw new RuntimeException('Morele zapisało numer przesyłki '.$tracking.', ale nie zmieniło statusu na „zrealizowane” (POST /order): '.$response['message']); }
    }

    /** Zapis listu przewozowego; null po potwierdzonym przyjęciu, inaczej opis odpowiedzi Morele. */
    private function sendWaybill(array $account, $id, string $tracking, string $carrierCode, string $carrierName, int $status): ?string
    {
        $link = isset(self::TRACKING_URLS[$carrierCode]) ? self::TRACKING_URLS[$carrierCode].rawurlencode($tracking) : '';
        $carrierName = trim($carrierName);
        $candidates = ['waybill_tracking_link' => $link, 'tracking_link' => $link, 'waybill_url' => $link, 'tracking_url' => $link, 'courier' => $carrierName, 'courier_name' => $carrierName, 'carrier' => $carrierName, 'carrier_name' => $carrierName, 'delivery_company' => $carrierName, 'status' => $status];
        $body = ['order_id' => $id, 'waybill_number' => $tracking];
        $response = $this->send($account, '/order/waybill', $body);
        $unknown = [];
        foreach ($candidates as $key => $value) {
            if ($response['ok'] || $response['status'] !== 400) { break; }
            if ($value === '') { continue; }
            $trial = $body + [$key => $value];
            $result = $this->send($account, '/order/waybill', $trial);
            if (!$result['ok'] && preg_match('/Field \S+ not found/i', $result['message'])) { $unknown[] = $key; continue; }
            $body = $trial; $response = $result;
        }
        if ($response['ok']) {
            $after = $this->orderState($account, $id);
            if ($after === null || $after['waybill'] === $tracking) { return null; }
            return 'odpowiedź OK, ale Morele pokazuje list „'.$after['waybill'].'” (pola: '.implode(', ', array_keys($body)).')';
        }
        return $response['message'].' (pola rozpoznane przez Morele: '.implode(', ', array_keys($body)).($unknown ? '; nieznane: '.implode(', ', $unknown) : '').')';
    }

    /** Status i waybill_number zamówienia z GET /orders; null, gdy odczyt się nie udał. */
    private function orderState(array $account, $id): ?array
    {
        try {
            foreach (self::list($this->api($account, 'GET', '/orders', ['order_id' => $id])) as $order) {
                if ((string) ($order['order_id'] ?? '') !== (string) $id) { continue; }
                return ['status' => (int) ($order['status'] ?? 0), 'waybill' => trim((string) ($order['waybill_number'] ?? $order['waybill'] ?? ''))];
            }
        } catch (RuntimeException $e) { /* bez odczytu działamy na statusie „wysłane” i odpowiedzi OK */ }
        return null;
    }

    /** Statusy Morele: 1 nowe, 2 w realizacji, 3 wysłane, 4 zrealizowane (POST /order). */
    public function setOrderStatus(array $account, string $orderId, string $status, array $raw = []): void
    {
        $orderId = trim($orderId);
        $response = $this->send($account, '/order', ['order_id' => ctype_digit($orderId) ? (int) $orderId : $orderId, 'status' => (int) $status]);
        if (!$response['ok']) { throw new RuntimeException('Morele nie zmieniło statusu (POST /order): '.$response['message']); }
    }

    /** POST JSON bez wyjątku: ['ok', 'status', 'message']; {"status":"FAILED"} przy 200 też jest błędem. Po 401 token jest odświeżany raz. */
    private function send(array $account, string $path, array $body): array
    {
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $request = function (bool $force) use (&$account, $path, $payload): array {
            return Http::request('Morele', 'POST', self::BASE.$path, ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer '.$this->token($account, $force)], $payload);
        };
        $response = $request(false);
        if ((int) $response['status'] === 401) { $response = $request(true); }
        $text = trim((string) $response['body']); $decoded = json_decode($text, true);
        $failed = is_array($decoded) && strtoupper((string) ($decoded['status'] ?? '')) === 'FAILED';
        $ok = $response['status'] >= 200 && $response['status'] < 300 && !$failed;
        return ['ok' => $ok, 'status' => (int) $response['status'], 'message' => $ok ? '' : 'HTTP '.$response['status'].': '.mb_substr(Http::reason($decoded, $text), 0, 200, 'UTF-8')];
    }

    /**
     * $multipart: części multipart (Http::multipart); $json: treść wysyłana jako application/json –
     * tego oczekuje centrum wiadomości (POST /communication-center/message).
     * Po 401 token jest odświeżany i żądanie ponawiane raz.
     */
    public function api(array $account, string $method, string $path, array $query = [], ?array $multipart = null, ?array $json = null): array
    {
        $url = self::BASE.$path.($query ? '?'.http_build_query($query) : '');
        $call = function (bool $force) use (&$account, $method, $url, $multipart, $json): array {
            $headers = ['Accept: application/json', 'Authorization: Bearer '.$this->token($account, $force)];
            $body = null;
            if ($multipart !== null) { [$contentType, $body] = Http::multipart($multipart); $headers[] = $contentType; }
            if ($json !== null) { $headers[] = 'Content-Type: application/json'; $body = $json; }
            return Http::json('Morele', $method, $url, $headers, $body);
        };
        $log = $this->logger;
        if ($log !== null) { $log('żądanie', ['method' => $method, 'url' => $url, 'body' => $json]); }
        try {
            $response = $call(false);
        } catch (RuntimeException $e) {
            if (strpos($e->getMessage(), '[401]') === false || strpos($e->getMessage(), 'Wygeneruj') !== false) {
                if ($log !== null) { $log('błąd', ['url' => $url, 'message' => $e->getMessage()]); }
                throw $e;
            }
            try {
                $response = $call(true);
            } catch (RuntimeException $retry) {
                if ($log !== null) { $log('błąd po odświeżeniu tokenu', ['url' => $url, 'message' => $retry->getMessage()]); }
                throw $retry;
            }
        }
        if ($log !== null) { $log('odpowiedź', ['url' => $url, 'data' => $response]); }
        return $response;
    }

    /**
     * Diagnostyka centrum komunikacji: surowy kod HTTP i początek odpowiedzi, bez zgłaszania wyjątku.
     * Morele nie publikuje specyfikacji tych zasobów – to jedyny sposób, żeby zobaczyć, co naprawdę zwraca API.
     */
    public function probe(array $account, string $method, string $path, array $query = [], array $extraHeaders = [], int $limit = 300, ?array $multipart = null, ?array $json = null): array
    {
        try {
            $headers = array_merge(['Accept: application/json', 'Authorization: Bearer '.$this->token($account, false)], $extraHeaders);
            $body = null;
            if ($multipart !== null) { [$contentType, $body] = Http::multipart($multipart); $headers[] = $contentType; }
            if ($json !== null) { $headers[] = 'Content-Type: application/json'; $body = json_encode($json, JSON_UNESCAPED_UNICODE); }
            $response = Http::request('Morele', $method, self::BASE.$path.($query ? '?'.http_build_query($query) : ''), $headers, $body);
            return ['status' => (int) $response['status'], 'body' => mb_substr(trim((string) $response['body']), 0, $limit, 'UTF-8')];
        } catch (\Throwable $e) {
            return ['status' => 0, 'body' => $e->getMessage()];
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
