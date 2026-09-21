<?php

declare(strict_types=1);
namespace App\Services;

final class OrderSyncError
{
    /** Public diagnostics are classified, never raw API responses or credentials. */
    public static function describe(\Throwable $error): array
    {
        $text=$error->getMessage(); $status=0;
        if (preg_match('/(?:HTTP\s+|API error\s*\[)(\d{3})/i',$text,$match)) { $status=(int)$match[1]; }
        $code='SYNC_INTERNAL'; $message='Nie udało się wykonać synchronizacji. Sprawdź identyfikator diagnostyczny w logu serwera.';
        if ($status===401 || stripos($text,'refresh token')!==false || stripos($text,'invalid_grant')!==false) {
            $code='MARKETPLACE_AUTH'; $message='Autoryzacja konta marketplace wygasła lub nie została zakończona. Połącz to konto ponownie w ustawieniach integracji.';
        } elseif ($status===403) {
            $reason=self::allegroReason($text);
            if (stripos($text,'user-agent')!==false || stripos($text,'user agent')!==false) {
                $code='ALLEGRO_USER_AGENT_REJECTED';
                $message='Allegro odrzuciło nagłówek User-Agent. Sprawdź, czy nazwa aplikacji odpowiada client_id używanemu przez to konto.';
            } else {
                $code='MARKETPLACE_PERMISSION';
                $message='Allegro odmówiło odczytu zamówień (HTTP 403).'.($reason!==''?' Powód Allegro: '.$reason:' Sprawdź autoryzację konta oraz aplikację przypisaną do client_id.');
            }
        } elseif ($status===429) {
            $code='MARKETPLACE_RATE_LIMIT'; $message='Limit zapytań marketplace (HTTP 429). Import zostanie ponowiony po przerwie; kursor jest zachowany.';
        } elseif ($status===400 || $status===422) {
            $reason=self::apiReason($text);
            $code='MARKETPLACE_REQUEST'; $message='Marketplace odrzucił zapytanie o zamówienia (HTTP '.$status.').'.($reason!==''?' Odpowiedź: '.$reason.'.':'').' Kursor pozostał bez zmian.';
        } elseif ($status>=500) {
            $code='MARKETPLACE_UNAVAILABLE'; $message='Serwer marketplace jest chwilowo niedostępny (HTTP '.$status.'). Ponów import później.';
        } elseif ($status===404) {
            $code='MARKETPLACE_ENDPOINT'; $message='Marketplace nie znalazł zasobu zamówień (HTTP 404). Sprawdź adres API konta.';
        } elseif (stripos($text,'cURL')!==false || stripos($text,'polaczenia z Allegro')!==false || stripos($text,'timed out')!==false) {
            $code='MARKETPLACE_CONNECTION'; $message='Nie udało się połączyć z API. Sprawdź cURL, certyfikaty TLS i połączenie serwera z marketplace.';
        } elseif (strpos($text,'Morele:')===0 || strpos($text,'Konto źródłowe')===0) {
            $code='ACCOUNT_CONFIGURATION'; $message=$text;
        } elseif ($error instanceof \PDOException || stripos($text,'SQLSTATE')!==false) {
            $code='SYNC_DATABASE'; $message='Błąd lokalnej bazy danych podczas synchronizacji. Sprawdź migrację tabel centrum zamówień i uprawnienia bazy.';
        } elseif ($error instanceof \InvalidArgumentException || stripos($text,'format')!==false || stripos($text,'JSON')!==false || stripos($text,'kursora')!==false) {
            $code='MARKETPLACE_RESPONSE'; $message='Odpowiedź marketplace ma niepoprawne lub niepełne dane zamówienia. Kursor zachowano do ponowienia.';
        }
        return ['code'=>$code,'message'=>$message,'http_status'=>$status];
    }

    public static function describeShipment(\Throwable $error): array
    {
        $text=$error->getMessage(); $status=0;
        if (preg_match('/(?:HTTP\s+|API error\s*\[)(\d{3})/i',$text,$match)) { $status=(int)$match[1]; }
        $reason=self::allegroReason($text); $platform='Allegro';
        if ($reason==='' && preg_match('/^([A-Za-z]+) API error \[\d{3}\]:\s*(.+)$/u',$text,$match)) { $platform=$match[1]; $reason=mb_substr(preg_replace('/[\x00-\x1F\x7F]+/u',' ',trim($match[2])) ?: '',0,220,'UTF-8'); }
        $code='SHIPMENT_INTERNAL';
        $message='Operator nie utworzył przesyłki. Ponów próbę lub sprawdź identyfikator diagnostyczny w logu serwera.';
        if ($status===401) {
            $code='SHIPMENT_AUTH';
            $message='Autoryzacja konta nadawczego wygasła. Połącz konto ponownie.';
        } elseif ($status===403) {
            $code='SHIPMENT_PERMISSION';
            $message='Operator odmówił dostępu do obsługi przesyłek (HTTP 403).'.($reason!==''?' Powód '.$platform.': '.$reason:' Sprawdź uprawnienia konta nadawczego.');
        } elseif ($status===400 || $status===422) {
            $code='SHIPMENT_REQUEST';
            $message='Operator odrzucił parametry przesyłki (HTTP '.$status.').'.($reason!==''?' Powód '.$platform.': '.$reason:' Sprawdź usługę, wymiary, wagę i dane odbiorcy.');
        } elseif ($status===429) {
            $code='SHIPMENT_RATE_LIMIT';
            $message='Operator ograniczył liczbę zapytań (HTTP 429). Odczekaj chwilę i ponów nadanie.';
        } elseif ($status===404) {
            $code='SHIPMENT_NOT_FOUND';
            $message='Operator nie znalazł zamówienia lub przesyłki (HTTP 404). Sprawdź zgodność konta nadawczego z zamówieniem.';
        } elseif ($status>=500) {
            $code='SHIPMENT_UNAVAILABLE';
            $message='System przewoźnika jest chwilowo niedostępny (HTTP '.$status.'). Ponów nadanie później.';
        } elseif (stripos($text,'cURL')!==false || stripos($text,'polaczenia z Allegro')!==false || stripos($text,'timed out')!==false) {
            $code='SHIPMENT_CONNECTION';
            $message='Nie udało się połączyć z systemem przewoźnika.';
        }
        return ['code'=>$code,'message'=>$message,'http_status'=>$status];
    }

    /** Powód błędu podany przez API platformy (bez nagłówków, tokenów i danych klientów). */
    private static function apiReason(string $text): string
    {
        if (!preg_match('/API error \[\d{3}\](?: \{[A-Za-z0-9_.-]+\})?:\s*(.+)$/u',$text,$match)) { return ''; }
        $reason=preg_replace('/[\x00-\x1F\x7F]+/u',' ',trim($match[1])) ?: '';
        return mb_substr($reason,0,180,'UTF-8');
    }

    private static function allegroReason(string $text): string
    {
        if (!preg_match('/Allegro API error \[\d{3}\](?: \{[A-Za-z0-9_.-]+\})?:\s*(.+)$/u',$text,$match)) { return ''; }
        $reason=preg_replace('/[\x00-\x1F\x7F]+/u',' ',trim($match[1])) ?: '';
        return mb_substr($reason,0,220,'UTF-8');
    }
    public static function log(\Throwable $error,array $context=[]): array
    {
        $diagnostic=($context['stage']??'')==='shipment'?self::describeShipment($error):self::describe($error);
        $diagnostic['reference']=bin2hex(random_bytes(6));
        // No raw exception message, URL, headers, customer data or tokens in this log.
        $record=$diagnostic+['tenant'=>\App\Core\Tenant::active()?\App\Core\Tenant::id():null,'exception'=>get_class($error),'file'=>basename($error->getFile()),'line'=>$error->getLine(),'context'=>$context];
        error_log('[orders] '.json_encode($record,JSON_UNESCAPED_UNICODE));
        return $diagnostic;
    }
}
