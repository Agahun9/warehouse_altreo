<?php

declare(strict_types=1);
namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class OrderNormalizer
{
    public const CANONICAL_PLATFORMS = ['prestashop', 'woocommerce', 'temu', 'morele', 'altreo', 'api'];

    /** Kwota z liczby lub tekstu (przecinek/kropka) do formatu z 2 miejscami. */
    public static function decimal($value): string
    {
        if (is_string($value)) { $value = str_replace([' ', ','], ['', '.'], trim($value)); }
        if ($value === '' || $value === null || !is_numeric($value)) { return '0.00'; }
        return number_format(round((float) $value, 2), 2, '.', '');
    }

    private static function miraklAddress(array $address, string $fallbackEmail = ''): array
    {
        $street=trim((string)($address['street_1']??$address['street']??$address['address']??''));
        $street=preg_replace('/^\s*ul(?:ica)?\.?\s*:?\s*/iu','',$street)??$street;
        $country=strtoupper(trim((string)($address['country_iso_code']??$address['country_code']??$address['country']??'PL')));
        $countries=['POL'=>'PL','DEU'=>'DE','CZE'=>'CZ','SVK'=>'SK','HUN'=>'HU','AUT'=>'AT','ROU'=>'RO','GBR'=>'GB','USA'=>'US','FRA'=>'FR','NLD'=>'NL','BEL'=>'BE','ITA'=>'IT','ESP'=>'ES','POLSKA'=>'PL','POLAND'=>'PL'];
        $country=$countries[$country]??$country;
        if (!preg_match('/^[A-Z]{2}$/D',$country)) { $country='PL'; }
        return [
            'firstName'=>(string)($address['firstName']??$address['firstname']??$address['first_name']??''),
            'lastName'=>(string)($address['lastName']??$address['lastname']??$address['last_name']??''),
            'company_name'=>(string)($address['company_name']??$address['company']??''),
            'tax_id'=>(string)($address['tax_id']??$address['taxId']??$address['nip']??''),
            'email'=>(string)($address['email']??$fallbackEmail),
            'phoneNumber'=>(string)($address['phone']??$address['phone_number']??$address['phoneNumber']??''),
            'street'=>$street,
            'buildingNumber'=>(string)($address['street_2']??$address['building_number']??$address['buildingNumber']??''),
            'zip'=>(string)($address['zip_code']??$address['postal_code']??$address['zip']??''),
            'city'=>(string)($address['city']??''),
            'country'=>$country,
            'additional_info'=>(string)($address['additional_info']??''),
        ];
    }

    public static function sourcePaymentMethod(string $platform,array $raw,string $delivery=''): string
    {
        $payment=is_array($raw['payment']??null)?$raw['payment']:[];
        $candidates=$platform==='allegro'
            ? [$payment['type']??'', $payment['provider']??'', $payment['method']??'']
            : ($platform==='erli'
                ? [$payment['methodName']??'', $payment['methodCode']??'', $payment['method']??'', $payment['type']??'', $raw['paymentMethod']??'', $raw['payment_method']??'']
                : [$raw['payment_type']??'', $raw['payment_method']??'', $raw['paymentMethod']??'', $payment['type']??'', $payment['method']??'']);
        foreach ($candidates as $candidate) {
            $candidate=mb_substr(trim((string)$candidate),0,190,'UTF-8');
            if ($candidate!=='') { return $candidate; }
        }
        return self::cashOnDelivery($raw,$delivery)?mb_substr(trim($delivery),0,190,'UTF-8'):'';
    }

    public static function cashOnDelivery(array $raw, string $delivery = ''): bool
    {
        $payment=is_array($raw['payment']??null)?$raw['payment']:[];
        $text=strtolower(implode(' ',array_filter([
            $delivery,
            (string)($raw['payment_type']??''),
            (string)($raw['payment_method']??''),
            (string)($raw['paymentMethod']??''),
            (string)($payment['type']??''),
            (string)($payment['method']??''),
            (string)($payment['name']??''),
        ])));
        if (($raw['delivery']['cod']??null)===true) { return true; }
        return strpos($text,'pobran')!==false || strpos($text,'cash_on_delivery')!==false || preg_match('/\bcod\b/',$text)===1;
    }

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
            $cashOnDelivery = self::cashOnDelivery($raw,(string)($raw['delivery']['name']??''));
            $paid = !$cashOnDelivery && ($raw['payment']['status'] ?? '') === 'COMPLETED';
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
            // Empik exposes the real buyer e-mail only after acceptance, as an order additional field.
            $email='';
            foreach ((array)($raw['order_additional_fields']??[]) as $field) {
                if (is_array($field) && ($field['code']??'')==='customer-email' && filter_var(trim((string)($field['value']??'')),FILTER_VALIDATE_EMAIL)) { $email=trim((string)$field['value']); break; }
            }
            if ($email==='') { $email=(string)($raw['customer_notification_email']??$buyer['email']??''); }
            $address = self::miraklAddress((array)($buyer['shipping_address'] ?? $buyer),$email);
            $invoice = self::miraklAddress((array)($buyer['billing_address'] ?? $address),$email);
            // Empik: NIP przychodzi jako pole dodatkowe "nip", a w adresach bywa wpisany w lastname;
            // nazwa firmy jest powielana w polach imienia/nazwiska.
            $nip='';
            foreach ((array)($raw['order_additional_fields']??[]) as $field) {
                if (is_array($field) && in_array(strtolower((string)($field['code']??'')),['nip','tax-id','vat-number'],true)) { $nip=trim((string)($field['value']??'')); break; }
            }
            $nipDigits=preg_replace('/\D/','',$nip)??'';
            $cleanNames=static function (array $a,bool $dropCompany) use ($nipDigits): array {
                $company=mb_strtolower(trim($a['company_name']),'UTF-8');
                foreach (['firstName','lastName'] as $nameField) {
                    $value=trim($a[$nameField]);
                    $digits=preg_replace('/[\s-]/','',$value)??'';
                    if (($nipDigits!=='' && $digits===$nipDigits) || preg_match('/^(PL)?\d{10}$/iD',$digits)) { $a[$nameField]=''; }
                    elseif ($dropCompany && $company!=='' && mb_strtolower($value,'UTF-8')===$company) { $a[$nameField]=''; }
                }
                return $a;
            };
            $address=$cleanNames($address,false);
            $invoice=$cleanNames($invoice,true);
            if ($invoice['tax_id']==='' && $nip!=='') { $invoice['tax_id']=$nip; }
            if (trim($invoice['firstName'].$invoice['lastName'])==='') {
                $invoice['firstName']=(string)($buyer['firstname']??$buyer['first_name']??'');
                $invoice['lastName']=(string)($buyer['lastname']??$buyer['last_name']??'');
            }
            $id = $raw['order_id'] ?? '';
            $status = $raw['order_state'] ?? 'UNKNOWN';
            $total = self::money($raw['total_price'] ?? '0');
            $currency = $raw['currency_iso_code'] ?? 'PLN';
            $delivery = (string)($raw['shipping_type_label'] ?? '');
            $cashOnDelivery = self::cashOnDelivery($raw,$delivery) || strtoupper((string)($raw['payment_workflow'] ?? '')) === 'PAY_ON_DELIVERY';
            // OR11 returns no payment status: "customer_debited" exists only as a query filter,
            // the response carries customer_debited_date once the customer has been charged.
            $paid = !$cashOnDelivery && trim((string)($raw['customer_debited_date'] ?? '')) !== '';
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
            $pickup = $address['additional_info'] ?? '';
            if ($platform === 'empik' && preg_match('/paczkomat|inpost|automat paczkowy/iu',$delivery)) {
                foreach (['firstName','lastName'] as $nameField) {
                    $value=trim((string)($address[$nameField]??''));
                    if (preg_match('/^[A-Z]{3}\d{2,3}[A-Z0-9]{0,2}$/iD',$value)) {
                        $pickup=$value;
                        $address[$nameField]='';
                    }
                }
                $billingName=self::miraklAddress((array)($buyer['billing_address']??[]),$email);
                $buyerName=trim($address['firstName'].' '.$address['lastName']);
                if ($buyerName==='') { $buyerName=trim($billingName['firstName'].' '.$billingName['lastName']); }
                if ($buyerName==='') { $buyerName=trim((string)($buyer['firstname']??$buyer['first_name']??'').' '.(string)($buyer['lastname']??$buyer['last_name']??'')); }
                if (preg_match('/^[A-Z]{3}\d{2,3}[A-Z0-9]{0,2}$/iD',$buyerName)) { $buyerName=''; }
                if ($buyerName==='') { $buyerName='Klient Empik'; }
            }
            $phone = $address['phoneNumber'] ?? '';
            $invoiceRequired = !empty($raw['invoice_required']) || !empty($raw['invoiceRequired']) || !empty($invoice['company_name']) || !empty($invoice['tax_id']);
            $documentPreference = $invoiceRequired ? 'invoice' : 'receipt';
        } elseif (in_array($platform, self::CANONICAL_PLATFORMS, true)) {
            // Wspólny format SalesCenter: PrestaShop, WooCommerce, Temu, Morele, Altreo.pl i API własnego sklepu.
            $created = (string) ($raw['created_at'] ?? '');
            $id = (string) ($raw['id'] ?? '');
            $status = mb_substr(trim((string) ($raw['status'] ?? 'new')), 0, 100, 'UTF-8') ?: 'new';
            $currency = strtoupper(substr(trim((string) ($raw['currency'] ?? 'PLN')), 0, 3)) ?: 'PLN';
            $customer = is_array($raw['customer'] ?? null) ? $raw['customer'] : [];
            $email = mb_substr(trim((string) ($customer['email'] ?? '')), 0, 255, 'UTF-8');
            $ship = is_array($raw['shipping_address'] ?? null) ? $raw['shipping_address'] : [];
            $inv = is_array($raw['invoice'] ?? null) ? $raw['invoice'] : [];
            $canonicalAddress = static function (array $a) use ($email): array {
                return self::miraklAddress(['first_name' => $a['first_name'] ?? '', 'last_name' => $a['last_name'] ?? '', 'company_name' => $a['company'] ?? '', 'tax_id' => $a['nip'] ?? '', 'email' => $a['email'] ?? $email, 'phone' => $a['phone'] ?? '', 'street' => $a['street'] ?? '', 'building_number' => $a['building'] ?? '', 'postal_code' => $a['postal_code'] ?? '', 'city' => $a['city'] ?? '', 'country_code' => $a['country'] ?? 'PL'], $email);
            };
            $address = $canonicalAddress($ship);
            $invoiceRequired = !empty($inv['required']) || trim((string) ($inv['nip'] ?? '')) !== '';
            $invoice = $invoiceRequired ? $canonicalAddress($inv + $ship) : [];
            $documentPreference = $invoiceRequired ? 'invoice' : 'receipt';
            $shippingInfo = is_array($raw['shipping'] ?? null) ? $raw['shipping'] : [];
            $delivery = mb_substr(trim((string) ($shippingInfo['method'] ?? '')), 0, 255, 'UTF-8');
            $pickup = mb_substr(trim((string) ($shippingInfo['pickup_point'] ?? '')), 0, 100, 'UTF-8');
            $shipping = self::money(self::decimal($shippingInfo['price'] ?? '0'));
            $items = [];
            $sum = $shipping;
            foreach ((array) ($raw['items'] ?? []) as $item) {
                if (!is_array($item)) { continue; }
                $quantity = max(1, (int) ($item['quantity'] ?? 1));
                $unit = self::money(self::decimal($item['price'] ?? '0'));
                $sum += $unit * $quantity;
                $vat = isset($item['vat']) && $item['vat'] !== '' ? strtolower(trim((string) $item['vat'])) : null;
                $items[] = ['name' => mb_substr((string) ($item['name'] ?? ''), 0, 500, 'UTF-8'), 'sku' => mb_substr((string) ($item['sku'] ?? ''), 0, 190, 'UTF-8'), 'quantity' => $quantity, 'unit_cents' => $unit, 'vat' => $vat, 'image_url' => self::imageUrl($item)];
            }
            $total = isset($raw['total']) && $raw['total'] !== '' ? self::money(self::decimal($raw['total'])) : $sum;
            $cashOnDelivery = !empty($raw['cash_on_delivery']) || self::cashOnDelivery(['payment_method' => (string) ($raw['payment_method'] ?? '')], $delivery);
            $paid = !$cashOnDelivery && !empty($raw['paid']);
            $phone = mb_substr(trim((string) ($customer['phone'] ?? $ship['phone'] ?? '')), 0, 80, 'UTF-8');
            $buyerName = trim($address['firstName'].' '.$address['lastName']);
            if ($buyerName === '') { $buyerName = trim((string) ($customer['name'] ?? '')) ?: trim((string) ($inv['company'] ?? '')); }
            $raw['payment_method'] = (string) ($raw['payment_method'] ?? '');
            $raw['notes'] = (string) ($customer['note'] ?? $raw['note'] ?? '');
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
            'buyer_name' => $buyerName ?? trim(($address['firstName'] ?? $address['firstname'] ?? $buyer['firstname'] ?? '') . ' ' . ($address['lastName'] ?? $address['lastname'] ?? $buyer['lastname'] ?? '')),
            'email' => $email ?? ($buyer['email'] ?? ''), 'phone' => $phone,
            'total_cents' => $total, 'currency' => $currency, 'paid' => $paid ? 1 : 0,
            'details' => ['items' => $items, 'address' => $address, 'invoice_address' => $invoice, 'invoice_required' => $invoiceRequired ? 1 : 0, 'document_preference' => $documentPreference, 'shipping_cents' => $shipping, 'delivery' => $delivery, 'pickup' => $pickup, 'source_payment_method' => self::sourcePaymentMethod($platform,$raw,(string)$delivery), 'payment_method' => isset($cashOnDelivery) && $cashOnDelivery ? 'Płatność przy odbiorze' : ($platform === 'morele' ? self::sourcePaymentMethod($platform,$raw,(string)$delivery) : ''), 'cash_on_delivery' => isset($cashOnDelivery) && $cashOnDelivery ? 1 : 0, 'amount_paid_cents' => $paid ? $total : 0, 'buyer_note' => $raw['notes'] ?? $raw['messageToSeller'] ?? $raw['comment'] ?? '', 'raw' => $raw],
        ];
    }
}
