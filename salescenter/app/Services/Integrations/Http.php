<?php

declare(strict_types=1);
namespace App\Services\Integrations;

use InvalidArgumentException;
use RuntimeException;

/** Wspólny klient HTTP integracji. Błędy mają format „<Platforma> API error [HTTP]: powód”, rozpoznawany przez OrderSyncError. */
final class Http
{
    /** @var callable|null Transport testowy: fn(method,url,headers,body): ['status'=>int,'body'=>string,'headers'=>array] */
    public static $transport = null;

    public static function request(string $label, string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 30, bool $publicOnly = false): array
    {
        if (self::$transport !== null) {
            return (self::$transport)($method, $url, $headers, $body);
        }
        if (!function_exists('curl_init')) { throw new RuntimeException('Brak rozszerzenia cURL potrzebnego do integracji '.$label.'.'); }
        $resolve = $publicOnly ? self::publicResolve($url) : null;
        $responseHeaders = [];
        $ch = curl_init($url);
        if ($ch === false) { throw new RuntimeException($label.': nie można uruchomić połączenia.'); }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => strtoupper($method), CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'SalesCenter/1.0',
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) { $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]); }
                return strlen($line);
            },
        ]);
        if ($resolve) { curl_setopt($ch, CURLOPT_RESOLVE, [$resolve]); }
        if (defined('CURLOPT_PROTOCOLS')) { curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS | ($publicOnly ? 0 : CURLPROTO_HTTP)); }
        if ($body !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, $body); }
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($raw === false) { throw new RuntimeException($label.': błąd połączenia cURL ('.$error.').'); }
        return ['status' => $status, 'body' => (string) $raw, 'headers' => $responseHeaders];
    }

    /** JSON request; zwraca zdekodowaną odpowiedź albo zgłasza błąd z kodem HTTP. */
    public static function json(string $label, string $method, string $url, array $headers = [], $body = null, int $timeout = 30, bool $publicOnly = false, ?array &$responseHeaders = null): array
    {
        $payload = $body === null ? null : (is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $response = self::request($label, $method, $url, $headers, $payload, $timeout, $publicOnly);
        $responseHeaders = $response['headers'];
        $decoded = trim($response['body']) === '' ? [] : json_decode($response['body'], true);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException($label.' API error ['.$response['status'].']: '.self::reason($decoded, $response['body']));
        }
        if (!is_array($decoded)) { throw new RuntimeException($label.': odpowiedź API nie jest poprawnym JSON.'); }
        return $decoded;
    }

    public static function reason($decoded, string $raw): string
    {
        if (is_array($decoded)) {
            foreach (['error_description', 'message', 'errorMsg', 'error', 'detail', 'title'] as $key) {
                $value = $decoded[$key] ?? null;
                if (is_array($value)) { $value = $value['message'] ?? json_encode($value, JSON_UNESCAPED_UNICODE); }
                if (is_scalar($value) && trim((string) $value) !== '') { return mb_substr(trim((string) $value), 0, 220, 'UTF-8'); }
            }
            if (isset($decoded[0]['errorMessage'])) {
                return mb_substr(trim((string) $decoded[0]['errorMessage']).(isset($decoded[0]['errorCode']) ? ' ('.$decoded[0]['errorCode'].')' : ''), 0, 220, 'UTF-8');
            }
            if (isset($decoded['errors'][0])) {
                $first = $decoded['errors'][0];
                return mb_substr(is_array($first) ? (string) ($first['userMessage'] ?? $first['message'] ?? json_encode($first, JSON_UNESCAPED_UNICODE)) : (string) $first, 0, 220, 'UTF-8');
            }
        }
        return mb_substr(trim(strip_tags($raw)), 0, 160, 'UTF-8');
    }

    /** Adres sklepu klienta musi być publicznym HTTPS – blokuje odpytywanie sieci wewnętrznej serwera. */
    public static function normalizeShopUrl(string $url): string
    {
        $url = trim($url);
        if ($url !== '' && !preg_match('#^[a-z]+://#i', $url)) { $url = 'https://'.$url; }
        $url = rtrim($url, '/');
        $parts = parse_url($url);
        if (!$parts || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('Podaj adres sklepu zaczynający się od https:// (np. https://mojsklep.pl).');
        }
        self::publicResolve($url);
        return $url;
    }

    private static function publicResolve(string $url): string
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = (int) ($parts['port'] ?? 443);
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if (!$addresses) { throw new InvalidArgumentException('Nie można odnaleźć adresu '.$host.'. Sprawdź domenę sklepu.'); }
        foreach ($addresses as $address) {
            if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('Adres sklepu nie może wskazywać sieci prywatnej ani lokalnej.');
            }
        }
        return $host.':'.$port.':'.$addresses[0];
    }

    /** Query string, w którym wartości listowe są powtarzane: ['s'=>['A','B']] → s=A&s=B. */
    public static function query(array $query): string
    {
        $parts = [];
        foreach ($query as $key => $value) {
            if (is_array($value) && array_keys($value) === range(0, count($value) - 1)) {
                foreach ($value as $item) { $parts[] = rawurlencode((string) $key).'='.rawurlencode((string) $item); }
                continue;
            }
            $parts[] = http_build_query([$key => $value], '', '&', PHP_QUERY_RFC3986);
        }
        return implode('&', $parts);
    }

    /**
     * Treść multipart/form-data. Część: ['name'=>…, 'content'=>…, opcjonalnie 'type' i 'filename'].
     * Zwraca [nagłówek Content-Type, treść].
     */
    public static function multipart(array $parts): array
    {
        $boundary = 'sc'.bin2hex(random_bytes(12));
        $body = '';
        foreach ($parts as $part) {
            $name = str_replace(['"', "\r", "\n"], '', (string) $part['name']);
            $body .= '--'.$boundary."\r\n".'Content-Disposition: form-data; name="'.$name.'"';
            if (isset($part['filename'])) { $body .= '; filename="'.str_replace(['"', "\r", "\n"], '', (string) $part['filename']).'"'; }
            $body .= "\r\n";
            if (!empty($part['type'])) { $body .= 'Content-Type: '.$part['type']."\r\n"; }
            $body .= "\r\n".(string) $part['content']."\r\n";
        }
        return ['Content-Type: multipart/form-data; boundary='.$boundary, $body.'--'.$boundary."--\r\n"];
    }

    public static function iso(int $timestamp): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }

    /** Kwota jako tekst z 2 miejscami (wymagany przez OrderNormalizer::money). */
    public static function amount($value): string
    {
        return number_format(round((float) str_replace(',', '.', (string) $value), 2), 2, '.', '');
    }
}
