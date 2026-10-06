<?php

declare(strict_types=1);
namespace App\Services;

use App\Models\OrderRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * Wniosek o zwrot prowizji Allegro (POST /order/refund-claims) dla każdej pozycji zamówienia.
 * Pozycje z aktywnym lub rozpatrzonym wnioskiem są pomijane, więc ponowne uruchomienie jest bezpieczne.
 */
final class OrderRefundClaimService
{
    private const CLOSED=['CANCELLED','CANCELED'];

    private $repo;
    private $service;

    public function __construct(OrderRepository $repo,?AllegroService $service=null)
    {
        $this->repo=$repo;
        $this->service=$service;
    }

    /** @return array{state:string,message:string} state: ok albo skipped */
    public function request(int $orderId,string $actor): array
    {
        $order=$this->repo->order($orderId);
        if ((string)$order['platform']!=='allegro') { return ['state'=>'skipped','message'=>'Zwrot prowizji dotyczy tylko zamówień Allegro']; }
        $raw=is_array($order['details']['raw']??null)?$order['details']['raw']:[];
        $lineItems=array_values(array_filter((array)($raw['lineItems']??[]),static function ($item): bool { return is_array($item) && trim((string)($item['id']??''))!==''; }));
        if (!$lineItems) { throw new InvalidArgumentException('Zamówienie nie ma pozycji Allegro – odśwież je synchronizacją.'); }
        $service=$this->service ?: new AllegroService();
        $account=null;
        foreach ($service->listAccounts() as $candidate) {
            if ((int)($candidate['id']??0)===(int)$order['account_source_id'] && (!array_key_exists('is_active',$candidate) || !empty($candidate['is_active']))) { $account=$candidate; break; }
        }
        if (!$account) { throw new RuntimeException('Konto źródłowe Allegro jest nieaktywne albo zostało usunięte.'); }

        $existing=[];
        foreach (array_unique(array_map(static function (array $item): string { return (string)($item['offer']['id']??''); },$lineItems)) as $offerId) {
            if ($offerId==='') { continue; }
            try {
                foreach ($service->refundClaims($account,$offerId) as $claim) {
                    $status=strtoupper((string)($claim['status']??''));
                    $lineItemId=(string)($claim['lineItem']['id']??'');
                    if ($lineItemId!=='' && !in_array($status,self::CLOSED,true)) { $existing[$lineItemId]=$status; }
                }
            } catch (\Throwable $error) { /* Lista jest tylko zabezpieczeniem; Allegro samo odrzuci duplikat. */ }
        }

        $created=0; $skipped=[]; $errors=[];
        foreach ($lineItems as $item) {
            $lineItemId=(string)$item['id'];
            $name=mb_substr(trim((string)($item['offer']['name']??$lineItemId)),0,60,'UTF-8');
            if (isset($existing[$lineItemId])) { $skipped[]=$name.' ('.$existing[$lineItemId].')'; continue; }
            try {
                $service->createRefundClaim($account,$lineItemId,(int)($item['quantity']??1));
                $created++;
            } catch (\Throwable $error) {
                $errors[]=$name.': '.self::reason($error);
            }
        }

        if ($created) { $this->repo->event($orderId,'Złożono wniosek o zwrot prowizji w Allegro ('.$created.' poz.).',$actor); }
        if ($errors) { $this->repo->event($orderId,'Allegro odrzuciło wniosek o zwrot prowizji: '.implode('; ',$errors),$actor); }
        if (!$created && $errors) { throw new RuntimeException('Allegro odrzuciło wniosek o zwrot prowizji: '.implode('; ',$errors)); }
        if (!$created) { return ['state'=>'skipped','message'=>'Wniosek o zwrot prowizji już istnieje: '.implode(', ',$skipped)]; }
        $message='Złożono wniosek o zwrot prowizji ('.$created.' poz.)';
        if ($skipped) { $message.='; pominięto z istniejącym wnioskiem: '.implode(', ',$skipped); }
        if ($errors) { $message.='; błędy: '.implode('; ',$errors); }
        return ['state'=>'ok','message'=>$message];
    }

    /** Komunikat Allegro bez prefiksu technicznego. */
    private static function reason(\Throwable $error): string
    {
        $text=trim($error->getMessage());
        if (preg_match('/API error \[(\d{3})\]:\s*(.+)$/su',$text,$match)) { $text=trim($match[2]).' (HTTP '.$match[1].')'; }
        return mb_substr((string)preg_replace('/[\x00-\x1F\x7F]+/u',' ',$text),0,220,'UTF-8');
    }
}
