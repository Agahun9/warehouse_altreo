<?php

declare(strict_types=1);
namespace App\Services;

use App\Core\Config;
use App\Services\Integrations\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Allegro REST API. Firma loguje się przez Allegro (OAuth 2.0 authorization code);
 * dane aplikacji Allegro wpisuje się raz w panelu (karta Allegro) – zapis w sc_settings lub ustawieniach firmy.
 */
final class AllegroService extends MarketplaceIntegration
{
    /** @var array<string,string> */
    private $imageCache = [];

    public function __construct(bool $ordersOnly = false) {}

    public function platform(): string { return 'allegro'; }

    protected function label(): string { return 'Allegro'; }

    public static function config(): array
    {
        $app = Config::get('app');
        $config = array_filter((array) ($app['integrations']['allegro'] ?? []), static function ($value) { return $value !== '' && $value !== null; });
        $config['source'] = 'file';
        if (trim((string) ($config['client_id'] ?? '')) === '') {
            // Aplikacja zapisana w panelu: wspólna dla wszystkich firm albo własna aplikacja firmy.
            $stored = self::storedApp();
            if ($stored) { $config = $stored + $config; }
        }
        $sandbox = !empty($config['sandbox']);
        return $config + [
            'client_id' => '', 'client_secret' => '', 'application_name' => 'SalesCenter',
            'api_base' => $sandbox ? 'https://api.allegro.pl.allegrosandbox.pl' : 'https://api.allegro.pl',
            'auth_base' => $sandbox ? 'https://allegro.pl.allegrosandbox.pl/auth/oauth' : 'https://allegro.pl/auth/oauth',
            'panel_base' => $sandbox ? 'https://salescenter.allegro.com.allegrosandbox.pl' : 'https://salescenter.allegro.com',
        ];
    }

    /** Dane aplikacji Allegro wpisane w panelu (szyfrowany sekret). */
    private static function storedApp(): array
    {
        $decode = static function (array $value, string $source): array {
            if (trim((string) ($value['client_id'] ?? '')) === '' || trim((string) ($value['secret'] ?? '')) === '') { return []; }
            try { $secret = (string) (OrderSecretBox::decrypt((string) $value['secret'])['client_secret'] ?? ''); }
            catch (\Throwable $e) { return []; }
            return $secret === '' ? [] : ['client_id' => (string) $value['client_id'], 'client_secret' => $secret, 'source' => $source];
        };
        try {
            $platform = $decode((new \App\Models\SaasRepository(\App\Core\Database::instance()))->setting('allegro_app'), 'platform');
            if ($platform) { return $platform; }
            if (\App\Core\Tenant::active()) { return $decode((new \App\Models\OrderRepository(\App\Core\Database::instance()))->setting('allegro_app'), 'tenant'); }
        } catch (\Throwable $e) { /* Brak tabel przed instalacją – traktujemy jak brak aplikacji. */ }
        return [];
    }

    /** Sprawdza Client ID i Client Secret (grant client_credentials) przed zapisaniem w panelu. */
    public function verifyApp(string $clientId, string $clientSecret): void
    {
        $this->oauth(['grant_type' => 'client_credentials'], ['client_id' => $clientId, 'client_secret' => $clientSecret]);
    }

    public static function configured(): bool
    {
        $config = self::config();
        return trim((string) $config['client_id']) !== '' && trim((string) $config['client_secret']) !== '';
    }

    public static function authorizationUrl(string $state, string $redirectUri): string
    {
        $config = self::config();
        return rtrim((string) $config['auth_base'], '/').'/authorize?'.http_build_query([
            'response_type' => 'code', 'client_id' => $config['client_id'], 'redirect_uri' => $redirectUri, 'state' => $state, 'prompt' => 'confirm',
        ]);
    }

    /** Wymienia kod z Allegro na tokeny i odczytuje konto sprzedawcy (/me). */
    public function exchangeCode(string $code, string $redirectUri): array
    {
        if ($code === '') { throw new InvalidArgumentException('Allegro nie przekazało kodu autoryzacji.'); }
        $tokens = $this->oauth(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $redirectUri]);
        $secret = $this->tokenSecret($tokens, $redirectUri);
        $app = self::config();
        $secret += ['client_id' => (string) $app['client_id'], 'client_secret' => (string) $app['client_secret']];
        $me = $this->api(['access_token' => $secret['access_token']], 'GET', '/me');
        return ['secret' => $secret, 'name' => 'Allegro · '.(string) ($me['login'] ?? 'konto'), 'remote_id' => (string) ($me['id'] ?? ''), 'public' => ['login' => (string) ($me['login'] ?? ''), 'remote_id' => (string) ($me['id'] ?? ''), 'seller_id' => (string) ($me['id'] ?? '')]];
    }

    public function testConnection(array $account): array
    {
        $me = $this->api($account, 'GET', '/me');
        return ['name' => 'Allegro · '.(string) ($me['login'] ?? ''), 'remote_id' => (string) ($me['id'] ?? ''), 'public' => ['login' => (string) ($me['login'] ?? ''), 'seller_id' => (string) ($me['id'] ?? '')], 'message' => 'Połączono z kontem Allegro „'.($me['login'] ?? '').'”.'];
    }

    public function readOrderPage(array $account, string $from, string $to, string $cursor = '', string $updatedFrom = ''): array
    {
        return $this->api($account, 'GET', '/order/checkout-forms', [
            'lineItems.boughtAt.gte' => $from, 'lineItems.boughtAt.lte' => $to,
            'updatedAt.gte' => $updatedFrom !== '' ? $updatedFrom : $from, 'updatedAt.lte' => $to,
            'limit' => 100, 'offset' => (int) $cursor, 'sort' => 'updatedAt',
        ]);
    }

    public function enrichOrderImages(array $account, array $order): array
    {
        foreach ($order['lineItems'] ?? [] as $index => $line) {
            $offerId = trim((string) ($line['offer']['id'] ?? ''));
            if ($offerId === '' || !empty($line['imageUrl'])) { continue; }
            $key = $account['connection_id'].'|'.$offerId;
            if (!array_key_exists($key, $this->imageCache)) {
                try {
                    $offer = $this->api($account, 'GET', '/sale/product-offers/'.rawurlencode($offerId));
                    $this->imageCache[$key] = (string) ($offer['images'][0]['url'] ?? $offer['images'][0] ?? $offer['primaryImage']['url'] ?? '');
                } catch (\Throwable $e) { $this->imageCache[$key] = ''; }
            }
            if ($this->imageCache[$key] !== '') { $order['lineItems'][$index]['imageUrl'] = $this->imageCache[$key]; }
        }
        return $order;
    }

    public function sellerIdForAccount(int $connectionId): string
    {
        foreach ($this->listAccounts() as $account) {
            if ((int) $account['id'] === $connectionId) { return preg_match('/^\d{1,30}$/D', (string) ($account['seller_id'] ?? '')) ? (string) $account['seller_id'] : ''; }
        }
        return '';
    }

    public function shipmentProposal(array $account, string $orderId): array
    {
        return $this->api($account, 'GET', '/shipment-management/delivery-proposals/'.rawurlencode($orderId));
    }

    public function shipmentServices(array $account): array
    {
        return $this->api($account, 'GET', '/shipment-management/delivery-services');
    }

    public function createShipmentCommand(array $account, string $commandId, array $input): array
    {
        return $this->api($account, 'POST', '/shipment-management/shipments/create-commands', [], ['commandId' => $commandId, 'input' => $input]);
    }

    public function shipmentCommandStatus(array $account, string $commandId): array
    {
        return $this->api($account, 'GET', '/shipment-management/shipments/create-commands/'.rawurlencode($commandId));
    }

    public function shipmentDetails(array $account, string $shipmentId): array
    {
        return $this->api($account, 'GET', '/shipment-management/shipments/'.rawurlencode($shipmentId));
    }

    public function shipmentTracking(array $account, string $carrierId, string $waybill): array
    {
        return $this->api($account, 'GET', '/order/carriers/'.rawurlencode($carrierId).'/tracking', ['waybill' => $waybill]);
    }

    public function publishOrderShipment(array $account, string $orderId, string $tracking, string $carrierCode, string $carrierName): void
    {
        $known = ['inpost' => 'INPOST', 'dpd' => 'DPD', 'dhl' => 'DHL', 'ups' => 'UPS', 'gls' => 'GLS', 'fedex' => 'FEDEX', 'orlen' => 'ORLEN', 'pocztex' => 'POCZTA_POLSKA'];
        $carrierId = $known[$carrierCode] ?? 'OTHER';
        $existing = $this->api($account, 'GET', '/order/checkout-forms/'.rawurlencode($orderId).'/shipments');
        foreach ((array) ($existing['shipments'] ?? []) as $shipment) { if ((string) ($shipment['waybill'] ?? '') === $tracking) { return; } }
        $payload = ['carrierId' => $carrierId, 'waybill' => $tracking];
        if ($carrierId === 'OTHER') { $payload['carrierName'] = mb_substr(trim($carrierName), 0, 30, 'UTF-8'); }
        $this->api($account, 'POST', '/order/checkout-forms/'.rawurlencode($orderId).'/shipments', [], $payload);
    }

    public function shipmentLabel(array $account, string $shipmentId, string $pageSize = 'A6'): string
    {
        $config = self::config();
        $response = Http::request('Allegro', 'POST', rtrim((string) $config['api_base'], '/').'/shipment-management/label', [
            'Accept: application/octet-stream', 'Authorization: Bearer '.$this->accessToken($account), 'Content-Type: application/vnd.allegro.public.v1+json', 'User-Agent: '.$this->userAgent(),
        ], json_encode(['shipmentIds' => [$shipmentId], 'pageSize' => $pageSize, 'cutLine' => $pageSize === 'A4'], JSON_THROW_ON_ERROR), 60);
        if ($response['status'] < 200 || $response['status'] >= 300) { throw new RuntimeException('Allegro nie zwróciło etykiety (HTTP '.$response['status'].').'); }
        return $response['body'];
    }

    public function userAgent(): string
    {
        $config = self::config();
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim((string) $config['application_name'])) ?: 'SalesCenter';
        $app = Config::get('app');
        $url = trim((string) ($app['public_base_url'] ?? ''));
        return $name.'/1.0'.(stripos($url, 'https://') === 0 ? ' (+'.$url.')' : '');
    }

    private function api(array $account, string $method, string $path, array $query = [], ?array $body = null): array
    {
        $config = self::config();
        $url = rtrim((string) $config['api_base'], '/').$path.($query ? '?'.http_build_query($query) : '');
        $headers = ['Accept: application/vnd.allegro.public.v1+json', 'Authorization: Bearer '.$this->accessToken($account), 'User-Agent: '.$this->userAgent()];
        if ($body !== null) { $headers[] = 'Content-Type: application/vnd.allegro.public.v1+json'; }
        return Http::json('Allegro', $method, $url, $headers, $body);
    }

    /** Token dostępu; odświeżany 5 minut przed wygaśnięciem i zapisywany w połączeniu. */
    public function refreshAccessToken(array $account): void
    {
        $this->accessToken($account, true);
    }

    private function accessToken(array $account, bool $force = false): string
    {
        if (empty($account['connection_id'])) { return (string) ($account['access_token'] ?? ''); }
        if (!$force && (int) ($account['expires_at'] ?? 0) > time() + 300 && !empty($account['access_token'])) { return (string) $account['access_token']; }
        if (empty($account['refresh_token'])) { throw new RuntimeException('Allegro API error [401]: brak refresh token – zaloguj konto Allegro ponownie.'); }
        // Odświeżenie tą samą aplikacją, która wydała token.
        $client = !empty($account['client_id']) && !empty($account['client_secret']) ? ['client_id' => (string) $account['client_id'], 'client_secret' => (string) $account['client_secret']] : null;
        $tokens = $this->oauth(['grant_type' => 'refresh_token', 'refresh_token' => (string) $account['refresh_token'], 'redirect_uri' => (string) ($account['redirect_uri'] ?? '')], $client);
        $secret = $this->tokenSecret($tokens, (string) ($account['redirect_uri'] ?? ''));
        $this->storeSecret($account, $secret);
        return $secret['access_token'];
    }

    private function tokenSecret(array $tokens, string $redirectUri): array
    {
        if (empty($tokens['access_token'])) { throw new RuntimeException('Allegro nie zwróciło tokenu dostępu.'); }
        return ['access_token' => (string) $tokens['access_token'], 'refresh_token' => (string) ($tokens['refresh_token'] ?? ''), 'expires_at' => time() + (int) ($tokens['expires_in'] ?? 43200), 'redirect_uri' => $redirectUri];
    }

    private function oauth(array $form, ?array $client = null): array
    {
        $config = self::config();
        $client = $client ?? ['client_id' => (string) $config['client_id'], 'client_secret' => (string) $config['client_secret']];
        if (trim($client['client_id']) === '' || trim($client['client_secret']) === '') { throw new InvalidArgumentException('Logowanie Allegro nie jest jeszcze skonfigurowane – uzupełnij dane aplikacji Allegro w karcie Allegro.'); }
        return Http::json('Allegro', 'POST', rtrim((string) $config['auth_base'], '/').'/token', [
            'Authorization: Basic '.base64_encode($client['client_id'].':'.$client['client_secret']),
            'Content-Type: application/x-www-form-urlencoded', 'Accept: application/json', 'User-Agent: '.$this->userAgent(),
        ], http_build_query($form));
    }
}
