<?php

declare(strict_types=1);

namespace App\Services;

class AccountingWarehouseSupplierResolver
{
    private const API_BASE = 'https://wl-api.mf.gov.pl/api/search/nip/';

    public function resolveByTaxId(string $taxId, ?string $date = null): ?array
    {
        $taxId = preg_replace('/\D+/', '', $taxId);
        if (!is_string($taxId) || strlen($taxId) !== 10) {
            return null;
        }

        $date = $this->normalizeDate($date) ?: date('Y-m-d');
        $url = self::API_BASE . rawurlencode($taxId) . '?date=' . rawurlencode($date);
        $payload = $this->requestJson($url);
        if (!is_array($payload)) {
            return null;
        }

        $subject = isset($payload['result']['subject']) && is_array($payload['result']['subject'])
            ? $payload['result']['subject']
            : null;
        if ($subject === null) {
            return null;
        }

        $name = trim((string) ($subject['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        return array(
            'supplier_name' => $name,
            'supplier_tax_id' => $taxId,
            'source' => 'official',
            'status_vat' => trim((string) ($subject['statusVat'] ?? '')),
            'working_address' => trim((string) ($subject['workingAddress'] ?? '')),
            'request_id' => trim((string) ($payload['result']['requestId'] ?? '')),
        );
    }

    private function requestJson(string $url): ?array
    {
        $body = null;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch !== false) {
                curl_setopt_array($ch, array(
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 8,
                    CURLOPT_CONNECTTIMEOUT => 4,
                    CURLOPT_HTTPHEADER => array('Accept: application/json'),
                    CURLOPT_USERAGENT => 'AccountingWarehouse/1.0',
                ));
                $response = curl_exec($ch);
                $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if (is_string($response) && $response !== '' && $statusCode >= 200 && $statusCode < 300) {
                    $body = $response;
                }
            }
        }

        if ($body === null) {
            $context = stream_context_create(array(
                'http' => array(
                    'method' => 'GET',
                    'timeout' => 8,
                    'header' => "Accept: application/json\r\nUser-Agent: AccountingWarehouse/1.0\r\n",
                ),
            ));
            $response = @file_get_contents($url, false, $context);
            if (is_string($response) && $response !== '') {
                $body = $response;
            }
        }

        if ($body === null) {
            return null;
        }

        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function normalizeDate(?string $date): ?string
    {
        $date = trim((string) $date);
        if ($date === '') {
            return null;
        }

        return preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $date) === 1 ? $date : null;
    }
}
