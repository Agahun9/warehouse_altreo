<?php

declare(strict_types=1);
namespace App\Services;

/**
 * Znane statusy źródłowe (remote_status) zamówień według platformy, z polskimi nazwami.
 * Mapowanie na status wewnętrzny jest wspólne dla wszystkich kont danej platformy.
 * Statusy spoza listy (np. stany PrestaShop zdefiniowane w sklepie) są dokładane z pobranych zamówień.
 */
final class RemoteStatusCatalog
{
    public const STATUSES = [
        // Allegro: opłacone zamówienia mają status realizacji z panelu sprzedawcy (fulfillment.status),
        // nieopłacone i anulowane – status formularza zakupu (checkout-form). Patrz OrderNormalizer.
        'allegro' => [
            'BOUGHT' => ['Kupione – bez formularza dostawy', 'Kupujący kliknął „Kup”, ale nie wypełnił jeszcze danych.'],
            'FILLED_IN' => ['Nieopłacone', 'Formularz wypełniony, czeka na płatność.'],
            'NEW' => ['Nowe', 'Opłacone albo za pobraniem – czeka na realizację.'],
            'PROCESSING' => ['W realizacji', ''],
            'SUSPENDED' => ['Wstrzymane', ''],
            'READY_FOR_SHIPMENT' => ['Do wysłania', ''],
            'READY_FOR_PICKUP' => ['Do odbioru', 'Gotowe do odbioru osobistego.'],
            'SENT' => ['Wysłane', 'Allegro ustawia je samo po dodaniu numeru przesyłki, jeśli masz to włączone.'],
            'PICKED_UP' => ['Odebrane', ''],
            'RETURNED' => ['Zwrócone', 'Ustawiane przez Allegro po zwrocie i refundacji wszystkich pozycji.'],
            'CANCELLED' => ['Anulowane', ''],
        ],
        // Empik i MediaMarkt (Mirakl): order_state.
        'empik' => self::MIRAKL,
        'mediamarkt' => self::MIRAKL,
        // ERLI: nieopłacone/anulowane – status zamówienia, opłacone – status u sprzedawcy (sellerStatus).
        'erli' => [
            'pending' => ['Nieopłacone', 'Czeka na płatność kupującego.'],
            'purchased' => ['Zakupione', 'Opłacone, bez statusu realizacji u sprzedawcy.'],
            'created' => ['Nowe', 'Opłacone – czeka na realizację.'],
            'readyToProcess' => ['Do realizacji', ''],
            'inProgress' => ['W realizacji', ''],
            'readyToPickup' => ['Do odbioru', ''],
            'sent' => ['Wysłane', ''],
            'received' => ['Odebrane', ''],
            'returningToSender' => ['Wraca do nadawcy', ''],
            'returned' => ['Zwrócone', ''],
            'canceled' => ['Anulowane przez sprzedawcę', ''],
            'cancelled' => ['Anulowane', 'Anulowane w ERLI (np. przez kupującego).'],
        ],
        'temu' => [
            'PENDING' => ['Oczekujące', 'Zamówienie jeszcze nie trafiło do realizacji.'],
            'UN_SHIPPING' => ['Do wysyłki', ''],
            'PARTIALLY_SHIPPED' => ['Częściowo wysłane', ''],
            'SHIPPED' => ['Wysłane', ''],
            'PARTIALLY_RECEIPTED' => ['Częściowo doręczone', ''],
            'RECEIPTED' => ['Doręczone', ''],
            'CANCELED' => ['Anulowane', ''],
        ],
        // Morele zwraca numer statusu (1–5).
        'morele' => [
            '1' => ['Nowe', ''],
            '2' => ['W realizacji', ''],
            '3' => ['Wysłane', ''],
            '4' => ['Zrealizowane', ''],
            '5' => ['Kosz', 'Zamówienie usunięte w panelu Morele.'],
        ],
        'woocommerce' => [
            'pending' => ['Oczekuje na płatność', ''],
            'on-hold' => ['Wstrzymane', 'Zwykle przelew tradycyjny – czeka na zaksięgowanie.'],
            'processing' => ['W trakcie realizacji', 'Opłacone. Można wysyłać.'],
            'completed' => ['Zrealizowane', ''],
            'cancelled' => ['Anulowane', ''],
            'refunded' => ['Zwrócone', ''],
            'failed' => ['Nieudane', 'Płatność odrzucona lub nieukończona.'],
            'checkout-draft' => ['Szkic', ''],
        ],
        'altreo' => [
            'nowe' => ['Nowe', ''],
            'w_realizacji' => ['W realizacji', ''],
            'wyslane' => ['Wysłane', ''],
            'zrealizowane' => ['Zrealizowane', ''],
            'anulowane' => ['Anulowane', ''],
        ],
    ];

    private const MIRAKL = [
        'STAGING' => ['Weryfikacja przez marketplace', 'Wstępna kontrola oszustw, zanim zamówienie trafi do sklepu.'],
        'WAITING_ACCEPTANCE' => ['Oczekuje na akceptację', 'Sprzedawca musi zaakceptować pozycje zamówienia.'],
        'WAITING_DEBIT' => ['Nieopłacone', 'Zaakceptowane, czeka na obciążenie klienta.'],
        'WAITING_DEBIT_PAYMENT' => ['Nieopłacone – w trakcie płatności', 'Płatność klienta jest przetwarzana.'],
        'SHIPPING' => ['Opłacone – do wysyłki', 'Można wysyłać.'],
        'SHIPPED' => ['Wysłane', ''],
        'TO_COLLECT' => ['Do odbioru', 'Czeka w punkcie odbioru.'],
        'RECEIVED' => ['Odebrane', ''],
        'CLOSED' => ['Zamknięte', ''],
        'REFUSED' => ['Odrzucone', 'Sprzedawca nie zaakceptował zamówienia.'],
        'CANCELED' => ['Anulowane', ''],
    ];

    /** Statusy, które SalesCenter może ustawić w marketplace po zmianie statusu wewnętrznego (PrestaShop – stany pobrane ze sklepu). */
    public const SETTABLE = [
        'allegro' => ['NEW', 'PROCESSING', 'SUSPENDED', 'READY_FOR_SHIPMENT', 'READY_FOR_PICKUP', 'SENT', 'PICKED_UP', 'CANCELLED'],
        'erli' => ['created', 'readyToProcess', 'inProgress', 'readyToPickup', 'sent', 'received', 'returningToSender', 'returned', 'canceled'],
        'empik' => ['WAITING_DEBIT', 'REFUSED', 'SHIPPED', 'CANCELED'],
        'mediamarkt' => ['WAITING_DEBIT', 'REFUSED', 'SHIPPED', 'CANCELED'],
        'morele' => ['1', '2', '3', '4'],
        'woocommerce' => ['pending', 'on-hold', 'processing', 'completed', 'cancelled', 'failed'],
        'prestashop' => [],
    ];

    /** Nazwy akcji tam, gdzie marketplace nie ustawia statusu wprost, tylko wykonuje operację. */
    private const PUSH_LABELS = [
        'empik' => self::MIRAKL_ACTIONS,
        'mediamarkt' => self::MIRAKL_ACTIONS,
    ];
    private const MIRAKL_ACTIONS = ['WAITING_DEBIT' => 'Zaakceptuj zamówienie', 'REFUSED' => 'Odrzuć zamówienie', 'SHIPPED' => 'Potwierdź wysyłkę', 'CANCELED' => 'Anuluj zamówienie'];

    /** Z jakich statusów źródłowych można przejść do danego statusu; '*' = wszystkie statusy danej platformy. */
    private const PUSH_FROM = [
        'allegro' => ['*' => ['NEW', 'PROCESSING', 'SUSPENDED', 'READY_FOR_SHIPMENT', 'READY_FOR_PICKUP', 'SENT', 'PICKED_UP', 'CANCELLED']],
        'erli' => ['*' => ['purchased', 'created', 'readyToProcess', 'inProgress', 'readyToPickup', 'sent', 'received', 'returningToSender', 'returned', 'canceled']],
        'empik' => self::MIRAKL_FROM,
        'mediamarkt' => self::MIRAKL_FROM,
        'morele' => ['*' => ['1', '2', '3', '4']],
    ];
    private const MIRAKL_FROM = [
        'WAITING_DEBIT' => ['WAITING_ACCEPTANCE'], 'REFUSED' => ['WAITING_ACCEPTANCE'], 'SHIPPED' => ['SHIPPING'],
        'CANCELED' => ['WAITING_DEBIT', 'WAITING_DEBIT_PAYMENT', 'SHIPPING'],
    ];

    public static function pushLabel(string $platform, string $code): string
    {
        return self::PUSH_LABELS[$platform][$code] ?? self::label($platform, $code);
    }

    /** Czy zamówienie w statusie $current może przejść do $target (brak reguły = dowolny status, poza szkicem/koszem). */
    public static function canPush(string $platform, string $current, string $target): bool
    {
        $rules = self::PUSH_FROM[$platform] ?? null;
        if ($rules === null) { return !in_array($current, ['checkout-draft', 'trash'], true); }
        $allowed = $rules[$target] ?? $rules['*'] ?? null;
        return $allowed === null || in_array($current, $allowed, true);
    }

    /** Kody, które mogą jeszcze występować w starszych zamówieniach – tylko do wyświetlania nazwy. */
    private const LEGACY = [
        'allegro' => ['READY_FOR_PROCESSING' => 'Opłacone (stary kod przed statusami realizacji)'],
    ];

    /** @return array<string,array{0:string,1:string}> */
    public static function known(string $platform): array
    {
        return self::STATUSES[$platform] ?? [];
    }

    /** Platformy, do których SalesCenter wysyła status. */
    public static function pushPlatforms(): array
    {
        return array_keys(self::SETTABLE);
    }

    /** @return string[] */
    public static function settable(string $platform): array
    {
        return self::SETTABLE[$platform] ?? [];
    }

    public static function label(string $platform, string $code): string
    {
        return self::STATUSES[$platform][$code][0] ?? self::LEGACY[$platform][$code] ?? $code;
    }
}
