<?php

declare(strict_types=1);
namespace App\Services;

use App\Models\OrderRepository;
use RuntimeException;

/**
 * Po zmianie statusu wewnętrznego ustawia odpowiadający mu status w marketplace
 * według mapowania „status SalesCenter → status platformy” (om_status_push).
 */
final class OrderMarketplaceStatusService
{
    private $repo;
    private $services;

    public function __construct(OrderRepository $repo,array $services=[])
    {
        $this->repo=$repo;
        $this->services=$services;
    }

    /** Zwraca ustawiony kod statusu albo null, gdy nie było nic do zmiany. Błędy trafiają do historii zamówienia. */
    public function push(int $orderId,string $actor): ?string
    {
        $order=$this->repo->order($orderId);
        $platform=(string)($order['platform']??'');
        if (!in_array($platform,RemoteStatusCatalog::pushPlatforms(),true)) { return null; }
        $target=(string)$this->repo->db()->fetchColumn('SELECT remote_status FROM om_status_push WHERE platform=:p AND status_id=:s',['p'=>$platform,'s'=>(int)$order['status_id']]);
        $current=(string)$order['remote_status'];
        if ($target==='' || $target===$current) { return null; }
        $label=RemoteStatusCatalog::pushLabel($platform,$target);
        $name=$this->platformLabel($platform);
        // Np. nieopłacone zamówienie Allegro albo Empik po akceptacji – marketplace nie przyjmie tej zmiany.
        if (!RemoteStatusCatalog::canPush($platform,$current,$target)) {
            $this->repo->event($orderId,'Pominięto „'.$label.'” w '.$name.': zamówienie ma tam status „'.RemoteStatusCatalog::label($platform,$current).'”.',$actor);
            return null;
        }
        try {
            $service=$this->service($platform);
            $raw=is_array($order['details']['raw']??null)?$order['details']['raw']:[];
            $service->setOrderStatus($this->sourceAccount($service,(int)($order['account_source_id']??0)),(string)$order['external_id'],$target,$raw);
        } catch (\Throwable $error) {
            $this->repo->event($orderId,'Nie udało się wykonać „'.$label.'” w '.$name.': '.mb_substr($error->getMessage(),0,300,'UTF-8'),$actor);
            OrderSyncError::log($error,['stage'=>'status_push','order_id'=>$orderId,'platform'=>$platform]);
            return null;
        }
        // Zapis kodu chroni przed ponownym mapowaniem przy najbliższym imporcie.
        $this->repo->db()->update('om_orders',['remote_status'=>$target],'id=:id',['id'=>$orderId]);
        $this->repo->event($orderId,$name.': '.$label.' ('.$target.').',$actor);
        return $target;
    }

    private function platformLabel(string $platform): string
    {
        return ['allegro'=>'Allegro','empik'=>'Empik','mediamarkt'=>'MediaMarkt','erli'=>'ERLI','morele'=>'Morele','prestashop'=>'PrestaShop','woocommerce'=>'WooCommerce'][$platform]??ucfirst($platform);
    }

    /** Odświeża zapamiętaną listę stanów PrestaShop (używaną w mapowaniu); zwraca liczbę stanów. */
    public function refreshPrestaShopStates(): int
    {
        $service=$this->service('prestashop'); $names=[];
        foreach ($service->listAccounts() as $account) {
            if (array_key_exists('is_active',$account) && empty($account['is_active'])) { continue; }
            foreach ($service->orderStates($account) as $stateName) { $names[$stateName]=true; }
        }
        $this->repo->saveSetting('remote_states_prestashop',['names'=>array_keys($names),'at'=>time()]);
        return count($names);
    }

    private function service(string $platform)
    {
        if (isset($this->services[$platform])) { return $this->services[$platform]; }
        $classes=OrderSyncService::CLASSES;
        return new $classes[$platform]();
    }

    private function sourceAccount($service,int $sourceId): array
    {
        foreach ($service->listAccounts() as $account) {
            if ((int)($account['id']??0)===$sourceId && (!array_key_exists('is_active',$account) || !empty($account['is_active']))) { return $account; }
        }
        throw new RuntimeException('konto źródłowe jest nieaktywne albo zostało usunięte.');
    }
}
