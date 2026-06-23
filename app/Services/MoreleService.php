<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\SettingRepository;
use RuntimeException;

class MoreleService
{
    private const DEFAULT_API_BASE = 'https://api-marketplace.morele.net';

    /** @var SettingRepository */
    private $settings;

    public function __construct()
    {
        $this->settings = new SettingRepository(Database::instance());
        $this->settings->ensureSchema();
    }

    public function categoryCharacteristics(int $categoryId): array
    {
        if ($categoryId <= 0) {
            throw new RuntimeException('Brak poprawnego ID kategorii Morele.');
        }

        $accessToken = $this->resolveAccessToken(false);

        try {
            return $this->requestFeatures($categoryId, $accessToken);
        } catch (RuntimeException $exception) {
            if (strpos($exception->getMessage(), 'HTTP 401') === false) {
                throw $exception;
            }
        }

        $accessToken = $this->resolveAccessToken(true);
        return $this->requestFeatures($categoryId, $accessToken);
    }

    private function requestFeatures(int $categoryId, string $accessToken): array
    {
        $payload = $this->requestJson(
            '/offer/category/features/' . rawurlencode((string) $categoryId),
            'GET',
            array('accept: application/json', 'Authorization: Bearer ' . $accessToken)
        );

        if (!isset($payload['category_characteristics']) || !is_array($payload['category_characteristics'])) {
            throw new RuntimeException('Morele API nie zwrocilo listy cech w oczekiwanym formacie.');
        }

        return $payload;
    }

    private function resolveAccessToken(bool $forceRefresh): string
    {
        $accessToken = trim($this->settings->get('morele_access_token', ''));
        if (!$forceRefresh && $accessToken !== '') {
            return $accessToken;
        }

        $refreshToken = trim($this->settings->get('morele_refresh_token', ''));
        if ($refreshToken !== '') {
            try {
                return $this->refreshAccessToken($refreshToken);
            } catch (RuntimeException $exception) {
                if (!$forceRefresh) {
                    throw $exception;
                }
            }
        }

        return $this->registerAccessToken();
    }

    private function registerAccessToken(): string
    {
        $response = $this->requestJson(
            '/auth/register',
            'POST',
            array('Authorization: Basic ' . $this->basicAuthorizationHeader()),
            null
        );

        return $this->storeTokenPayload($response);
    }

    private function refreshAccessToken(string $refreshToken): string
    {
        $response = $this->requestJson(
            '/auth/refresh',
            'POST',
            array('Authorization: Basic ' . $this->basicAuthorizationHeader()),
            array('refresh_token' => $refreshToken)
        );

        return $this->storeTokenPayload($response);
    }

    private function storeTokenPayload(array $payload): string
    {
        $accessToken = trim((string) ($payload['access_token'] ?? ''));
        $refreshToken = trim((string) ($payload['refresh_token'] ?? ''));

        if ($accessToken === '') {
            throw new RuntimeException('Morele API nie zwrocilo access_token.');
        }

        $this->settings->set('morele_access_token', $accessToken);
        if ($refreshToken !== '') {
            $this->settings->set('morele_refresh_token', $refreshToken);
        }

        return $accessToken;
    }

    private function basicAuthorizationHeader(): string
    {
        $clientId = trim($this->settings->get('morele_client_id', ''));
        $clientSecret = trim($this->settings->get('morele_client_secret', ''));

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Brak konfiguracji Morele: uzupelnij Client ID i Client Secret w administracji.');
        }

        return base64_encode($clientId . ':' . $clientSecret);
    }

    private function apiBaseUrl(): string
    {
        $configured = rtrim(trim($this->settings->get('morele_api_url', self::DEFAULT_API_BASE)), '/');
        return $configured !== '' ? $configured : self::DEFAULT_API_BASE;
    }

    private function requestJson(string $path, string $method, array $headers, ?array $postFields = null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Brak rozszerzenia cURL potrzebnego do integracji Morele.');
        }

        $url = $this->apiBaseUrl() . $path;
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Nie udalo sie zainicjalizowac polaczenia z Morele API.');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(array(
            'User-Agent: ALTREO-Morele/1.0',
            'Accept: application/json',
        ), $headers));

        if ($postFields !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        }

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (!is_string($raw) || $raw === '') {
            throw new RuntimeException('Morele API nie zwrocilo odpowiedzi.');
        }

        $decoded = json_decode($raw, true);
        if ($httpCode < 200 || $httpCode >= 300) {
            $message = is_array($decoded) ? $this->extractErrorMessage($decoded) : '';
            if ($message === '' && $curlError !== '') {
                $message = $curlError;
            }

            throw new RuntimeException('Morele API zwrocilo blad HTTP ' . $httpCode . ($message !== '' ? ': ' . $message : '.'));
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Morele API zwrocilo niepoprawny JSON.');
        }

        return $decoded;
    }

    private function extractErrorMessage(array $payload): string
    {
        foreach (array('message', 'error', 'description', 'detail', 'msg') as $key) {
            if (!isset($payload[$key])) {
                continue;
            }

            if (is_scalar($payload[$key])) {
                $value = trim((string) $payload[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }
}
