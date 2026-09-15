<?php

declare(strict_types=1);
namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class OrderNormalizer
{
    public static function money($value): int
    {
        $value = (string) $value;
        if (!preg_match('/^-?\d+(?:\.\d{1,2})?$/D', $value)) {
            throw new InvalidArgumentException('Nieprawidłowa kwota: wymagane maksymalnie dwa miejsca dziesiętne.');
        }
        $negative = strpos($value, '-') === 0;
        $parts = explode('.', ltrim($value, '-'));
        $cents = (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
        return $negative ? -$cents : $cents;
    }

    private static function imageUrl(array $item): string
    {
        foreach (['image_url','imageUrl','media_url','mediaUrl','thumbnail_url','thumbnailUrl','thumbnail','image','mainImage','primaryImage','images','media','medias'] as $key) {
            if (!array_key_exists($key,$item)) { continue; }
            $candidate=$item[$key];
            if (is_array($candidate)) {
                if (isset($candidate['url'])) { $candidate=$candidate['url']; }
                elseif (isset($candidate[0])) { $candidate=is_array($candidate[0])?($candidate[0]['url']??''):$candidate[0]; }
            }
            $candidate=trim((string)$candidate);
            if (strpos($candidate,'//')===0) { $candidate='https:'.$candidate; }
            if (preg_match('#^https://[^\s]+$#i',$candidate)) { return $candidate; }
        }
        foreach (['offer','product'] as $key) {
            if (isset($item[$key]) && is_array($item[$key])) {
                $candidate=self::imageUrl($item[$key]);
                if ($candidate!=='') { return $candidate; }
            }
        }
        return '';
    }

    public static function normalize(string $platform, array $raw, int $cutoff, int $now): ?array
    {
        if ($platform === 'allegro') {
            $dates = array_column($raw['lineItems'] ?? [], 'boughtAt');
            sort($dates);
            $created = $dates[0] ?? '';
            $buyer = $raw['buyer'] ?? [];
            $address = $raw['delivery']['address'] ?? $buyer;
            $invoiceRequired = !empty($raw['invoice']['required']);
            $invoice = $invoiceRequired ? (array)($raw['invoice']['address'] ?? []) : [];
            $documentPreference = $invoiceRequired ? 'invoice' : 'receipt';
            $id = $raw['id'] ?? '';
            $status = $raw['status'] ?? 'UNKNOWN';
            $total = self::money($raw['summary']['totalToPay']['amount'] ?? '0');
            $currency = $raw['summary']['totalToPay']['currency'] ?? 'PLN';
            $paymentType = strtoupper(trim((string) ($raw['payment']['type'] ?? '')));
            $deliveryName = (string) ($raw['delivery']['method']['name'] ?? '');
            $cashOnDelivery = $paymentType === 'CASH_ON_DELIVERY'
                || stripos($deliveryName, 'pobrani') !== false;
            $paid = !$cashOnDelivery
                && isset($raw['payment']['finishedAt'])
                && $status === 'READY_FOR_PROCESSING';
            $items = [];
            foreach ($raw['lineItems'] ?? [] as $item) {
                $items[] = ['name' => $item['offer']['name'] ?? '', 'sku' => $item['offer']['external']['id'] ?? '', 'offer_id'=>(string)($item['offer']['id']??''), 'quantity' => (int) ($item['quantity'] ?? 1), 'unit_cents' => self::money($item['price']['amount'] ?? '0'), 'vat' => null, 'image_url'=>self::imageUrl($item)];
            }
            $shipping = self::money($raw['delivery']['cost']['amount'] ?? '0');
            $delivery = $deliveryName;
            $pickup = $raw['delivery']['pickupPoint']['id'] ?? '';
            $phone = $address['phoneNumber'] ?? $buyer['phoneNumber'] ?? '';
        } elseif ($platform === 'erli') {
            $created = $raw['created'] ?? '';
            $buyer = $raw['user'] ?? [];
            $address = $buyer['deliveryAddress'] ?? [];
            $invoice = $buyer['invoiceAddress'] ?? $address;
            $id = $raw['id'] ?? '';
            $status = $raw['status'] ?? 'unknown';
            $total = (int) ($raw['totalPrice'] ?? 0);
            $currency = $raw['currency'] ?? 'PLN';
            $paid = ($raw['payment']['status'] ?? '') === 'COMPLETED';
            $items = [];
            foreach ($raw['items'] ?? [] as $item) {
                $items[] = ['name' => $item['name'] ?? '', 'sku' => $item['sku'] ?? $item['externalId'] ?? '', 'external_id'=>(string)($item['productExternalId']??$item['externalId']??$item['product']['externalId']??$item['product']['id']??''), 'quantity' => (int) ($item['quantity'] ?? 1), 'unit_cents' => (int) ($item['unitPrice'] ?? 0), 'vat' => isset($item['taxRate']) ? strtolower(str_replace('TAX_', '', $item['taxRate'])) : null, 'image_url'=>self::imageUrl($item)];
            }
            $shipping = (int) ($raw['delivery']['price'] ?? 0);
            $delivery = $raw['delivery']['name'] ?? '';
            $pickup = $raw['delivery']['pickupPlace']['externalId'] ?? '';
            $phone = $address['phone'] ?? '';
            $invoiceRequired = !empty($raw['invoiceRequired']) || !empty($raw['invoice_required']) || !empty($invoice['taxId']) || !empty($invoice['nip']);
            $documentPreference = $invoiceRequired ? 'invoice' : 'receipt';
        } elseif (in_array($platform, ['empik', 'mediamarkt'], true)) {
            $created = $raw['created_date'] ?? '';
            $buyer = $raw['customer'] ?? [];
            $address = $buyer['shipping_address'] ?? $buyer;
            $invoice = $buyer['billing_address'] ?? $address;
            $id = $raw['order_id'] ?? '';
            $status = $raw['order_state'] ?? 'UNKNOWN';
            $total = self::money($raw['total_price'] ?? '0');
            $currency = $raw['currency_iso_code'] ?? 'PLN';
            $paid = ($raw['payment_status'] ?? '') === 'PAID';
            $items = [];
            foreach ($raw['order_lines'] ?? [] as $item) {
                $items[] = [
                    'name' => $item['product_title'] ?? '',
                    'sku' => $item['offer_sku'] ?? $item['shop_sku'] ?? $item['product_sku'] ?? '',
                    'shop_sku' => (string) ($item['offer_sku'] ?? $item['shop_sku'] ?? ''),
                    'product_sku' => (string) ($item['product_sku'] ?? ''),
                    'product_id' => (string) ($item['product_id'] ?? ''),
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'unit_cents' => self::money($item['price_unit'] ?? '0'),
                    'vat' => null,
                    'image_url' => self::imageUrl($item),
                ];
            }
            $shipping = self::money($raw['shipping_price'] ?? '0');
            $delivery = $raw['shipping_type_label'] ?? '';
            $pickup = $address['additional_info'] ?? '';
            $phone = $address['phone'] ?? '';
            $invoiceRequired = !empty($raw['invoice_required']) || !empty($raw['invoiceRequired']) || !empty($invoice['company']) || !empty($invoice['tax_id']);
            $documentPreference = $invoiceRequired ? 'invoice' : 'receipt';
        } else {
            throw new InvalidArgumentException('Brak zweryfikowanego adaptera zamówień dla tej platformy.');
        }
        if ($created === '' || $id === '') {
            throw new InvalidArgumentException('API zwróciło zamówienie bez ID lub daty utworzenia.');
        }
        try { $date = new DateTimeImmutable($created, new DateTimeZone('UTC')); }
        catch (\Exception $e) { throw new InvalidArgumentException('Niepoprawna data utworzenia zamówienia.'); }
        if ($date->getTimestamp() < $cutoff || $date->getTimestamp() > $now) { return null; }
        return [
            'external_id' => (string) $id, 'remote_status' => (string) $status,
            'ordered_at' => $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'buyer_name' => trim(($address['firstName'] ?? $address['firstname'] ?? $buyer['firstname'] ?? '') . ' ' . ($address['lastName'] ?? $address['lastname'] ?? $buyer['lastname'] ?? '')),
            'email' => $buyer['email'] ?? '', 'phone' => $phone,
            'total_cents' => $total, 'currency' => $currency, 'paid' => $paid ? 1 : 0,
            'details' => ['items' => $items, 'address' => $address, 'invoice_address' => $invoice, 'invoice_required' => $invoiceRequired ? 1 : 0, 'document_preference' => $documentPreference, 'shipping_cents' => $shipping, 'delivery' => $delivery, 'pickup' => $pickup, 'payment_method' => isset($cashOnDelivery) && $cashOnDelivery ? 'Płatność przy odbiorze' : '', 'cash_on_delivery' => isset($cashOnDelivery) && $cashOnDelivery ? 1 : 0, 'amount_paid_cents' => $paid ? $total : 0, 'buyer_note' => $raw['messageToSeller'] ?? $raw['comment'] ?? '', 'raw' => $raw],
        ];
    }
}
