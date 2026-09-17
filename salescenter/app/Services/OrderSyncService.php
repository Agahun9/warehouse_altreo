<?php

declare(strict_types=1);
namespace App\Services;
use App\Models\OrderRepository;
use App\Models\SettingRepository;
use RuntimeException;

final class OrderSyncService
{
    private $repo;
    private $services = [];
    public const CLASSES = ['allegro'=>AllegroService::class,'empik'=>EmpikService::class,'mediamarkt'=>MediaMarktService::class,'erli'=>ErliService::class,'morele'=>MoreleService::class,'temu'=>TemuService::class,'prestashop'=>PrestaShopService::class,'woocommerce'=>WooCommerceService::class];
    public function __construct(OrderRepository $repo) { $this->repo=$repo; }
    private function service(string $platform)
    {
        $classes = self::CLASSES;
        if (!isset($classes[$platform])) { throw new RuntimeException('Nieznana platforma.'); }
        if (!isset($this->services[$platform])) { $this->services[$platform] = $platform === 'allegro' ? new AllegroService(true) : new $classes[$platform](); }
        return $this->services[$platform];
    }
    public function discover(): array
    {
        $errors=[];
        $shop=new AltreoShopOrdersService(new SettingRepository($this->repo->db()));
        if ($shop->configured()) {
            $this->repo->registerAccount('altreo',1,'Sklep internetowy (API)');
            $shopAccount=$this->repo->db()->fetch('SELECT id FROM om_accounts WHERE platform=:p AND source_id=1',['p'=>'altreo']);
            if ($shopAccount && !$this->repo->db()->fetchColumn('SELECT id FROM om_mappings WHERE account_id=:id LIMIT 1',['id'=>$shopAccount['id']])) {
                $names=['nowe'=>'Nowe','w_realizacji'=>'Do spakowania','wyslane'=>'Wysłane','zrealizowane'=>'Zakończone','anulowane'=>'Anulowane'];
                foreach ($names as $remote=>$local) {
                    $status=$this->repo->db()->fetchColumn('SELECT id FROM om_statuses WHERE name=:name LIMIT 1',['name'=>$local]);
                    if ($status) { $this->repo->db()->insert('om_mappings',['account_id'=>$shopAccount['id'],'remote_status'=>$remote,'status_id'=>$status]); }
                }
            }
        }
        foreach (array_keys(self::CLASSES) as $platform) {
            try {
                foreach ($this->service($platform)->listAccounts() as $account) {
                    if (!empty($account['is_active'])) { $this->repo->registerAccount($platform,(int)$account['id'],(string)$account['name']); }
                }
            } catch (\Throwable $e) { $errors[] = $platform.': nie udało się odczytać kont; sprawdź konfigurację integracji.'; }
        }
        return $errors;
    }
    /** One page/account per invocation; persisted cursors drain large accounts without blocking others. */
    public function sync(bool $test=false, ?int $onlyAccount=null): array
    {
        $results=[]; $db=$this->repo->db();
        foreach ($this->repo->accounts() as $account) {
            if (($onlyAccount !== null && (int)$account['id'] !== $onlyAccount) || (!$test && !(int)$account['enabled'])) { continue; }
            if (!$test && (int)$account['next_attempt']>time()) { continue; }
            $lock='om_sync_'.$account['id'];
            if (!$db->acquireAdvisoryLock($lock)) { $results[]=['account'=>$account['name'],'message'=>'Synchronizacja już trwa.']; continue; }
            try {
                // Reload cursor after obtaining the lock: another worker may have just advanced it.
                $account=$db->fetch('SELECT * FROM om_accounts WHERE id=:id',['id'=>$account['id']]);
                if ($account['platform']==='altreo') {
                    $results[]=$this->syncShop($account);
                    continue;
                }
                if (in_array($account['platform'],['api','manual'],true)) { $results[]=['account'=>$account['name'],'message'=>'Zamówienia przychodzą przez API własnego sklepu – nic do pobrania.']; continue; }
                $source=null;
                foreach ($this->service($account['platform'])->listAccounts() as $candidate) {
                    if ((int)$candidate['id']===(int)$account['source_id'] && !empty($candidate['is_active'])) { $source=$candidate; break; }
                }
                if (!$source) { throw new RuntimeException('Konto źródłowe jest nieaktywne lub zostało usunięte.'); }
                $now=time();
                // „Pobierz od daty”: import_from rozszerza okno ponad standardowe 7 dni do zakończenia pełnego przebiegu.
                $importFrom=!empty($account['import_from']) ? (int)strtotime($account['import_from'].' UTC') : 0;
                $cutoff=$importFrom>0 ? min($now-7*86400,$importFrom) : $now-7*86400;
                $state=$account['cursor_json'] ? json_decode($account['cursor_json'],true) : null;
                if (!$state || (int)$state['to']<$cutoff) { $state=['from'=>$cutoff,'to'=>$now,'cursor'=>'','updated_from'=>!$test && $account['synced_until'] ? max($cutoff,strtotime($account['synced_until'].' UTC')-300) : $cutoff]; }
                // Fixed window while paging; local admission always uses the rolling seven-day cutoff.
                $integration=$this->service($account['platform']);
                $payload=$integration->readOrderPage($source,gmdate('Y-m-d\TH:i:s\Z',(int)$state['from']),gmdate('Y-m-d\TH:i:s\Z',(int)$state['to']),(string)$state['cursor'],gmdate('Y-m-d\TH:i:s\Z',(int)($state['updated_from']??$state['from'])));
                $platform=$account['platform'];
                // Allegro limits offset pagination. Split the update window before approaching it.
                if ($platform==='allegro' && (int)($payload['totalCount']??0)>9500) {
                    $start=(int)($state['updated_from']??$state['from']);
                    if ((int)$state['to']-$start<=1) { throw new RuntimeException('Zbyt wiele zamówień w jednej sekundzie — potrzebny eksport zdarzeń.'); }
                    $state['horizon']=$state['horizon']??$state['to'];
                    $state['to']=$start+(int)floor(((int)$state['to']-$start)/2);
                    $state['cursor']='';
                    $db->update('om_accounts',['cursor_json'=>OrderRepository::json($state),'last_error'=>null,'next_attempt'=>0],'id=:id',['id'=>$account['id']]);
                    $results[]=['account'=>$account['name'],'more'=>true,'message'=>'Duże konto: podzielono okno importu. Kolejny przebieg pobierze mniejszy zakres.'];
                    continue;
                }
                $rows=$platform==='allegro' ? ($payload['checkoutForms']??null) : ($platform==='erli' ? $payload : ($payload['orders']??null));
                if (!is_array($rows) || ($rows !== [] && array_keys($rows)!==range(0,count($rows)-1))) { throw new RuntimeException('Nieoczekiwany format listy zamówień. Kursor zachowano.'); }
                $added=0; $updated=0; $skipped=0;
                foreach ($rows as $raw) {
                    if (method_exists($integration,'enrichOrderImages')) {
                        try { $raw=$integration->enrichOrderImages($source,$raw); }
                        catch (\Throwable $imageError) { /* A missing thumbnail must never block order import. */ }
                    }
                    $order=OrderNormalizer::normalize($platform,$raw,$cutoff,$now);
                    if ($order===null) {
                        // The seven-day window only admits new orders; already imported ones keep
                        // receiving source updates (e.g. a payment confirmed days after purchase).
                        $order=OrderNormalizer::normalize($platform,$raw,0,$now);
                        if ($order===null || !$db->fetchColumn('SELECT id FROM om_orders WHERE account_id=:a AND external_id=:e',['a'=>(int)$account['id'],'e'=>$order['external_id']])) { $skipped++; continue; }
                    }
                    if ($this->repo->import((int)$account['id'],$order)) { $added++; } else { $updated++; }
                    $this->autoAcceptIfEligible($platform,$integration,$source,$account,$raw,(string)$order['remote_status'],(string)$order['external_id']);
                }
                $advance=isset($payload['page_size']) ? (int)$payload['page_size'] : count($rows);
                $more=$advance>0;
                if ($more) {
                    if ($platform==='erli') {
                        $last=end($rows);
                        if (empty($last['cursor']) || $last['cursor']===$state['cursor']) { throw new RuntimeException('API nie zwróciło kolejnego kursora. Ponowienie nie utworzy duplikatów.'); }
                        $state['cursor']=$last['cursor'];
                    } else {
                        $state['cursor']=(string)((int)$state['cursor']+$advance);
                        if (isset($payload['total_count']) && (int)$state['cursor']>=(int)$payload['total_count']) { $more=false; }
                        if (isset($payload['totalCount']) && (int)$state['cursor']>=(int)$payload['totalCount']) { $more=false; }
                    }
                }
                $completedTo=(int)$state['to'];
                if (!$more && isset($state['horizon']) && (int)$state['to']<(int)$state['horizon']) {
                    $state['updated_from']=$state['to'];
                    $state['to']=$state['horizon'];
                    $state['cursor']='';
                    $more=true;
                }
                $accountUpdate=['cursor_json'=>$more ? OrderRepository::json($state) : null,'last_sync'=>gmdate('Y-m-d H:i:s'),'last_error'=>null,'next_attempt'=>0,'synced_until'=>$more ? $account['synced_until'] : gmdate('Y-m-d H:i:s',$completedTo)];
                if (!$more && $importFrom>0) { $accountUpdate['import_from']=null; }
                $db->update('om_accounts',$accountUpdate,'id=:id',['id'=>$account['id']]);
                $results[]=['account'=>$account['name'],'account_id'=>(int)$account['id'],'importing_from'=>$more && $importFrom>0 ? $account['import_from'] : null,'added'=>$added,'updated'=>$updated,'skipped'=>$skipped,'more'=>$more,'message'=>"Nowe: $added · odświeżone: $updated · pominięte: $skipped".($more?' · pozostały kolejne strony':' · zakończono')];
            } catch (\Throwable $e) {
                $diagnostic=OrderSyncError::log($e,['account_id'=>(int)$account['id'],'platform'=>$account['platform']]);
                $message=$diagnostic['message'].' [ID: '.$diagnostic['reference'].']';
                $db->update('om_accounts',['last_error'=>$message,'next_attempt'=>time()+120],'id=:id',['id'=>$account['id']]);
                $results[]=['account'=>$account['name'],'error'=>true,'message'=>$message,'code'=>$diagnostic['code'],'reference'=>$diagnostic['reference']];
            } finally { $db->releaseAdvisoryLock($lock); }
        }
        return $results;
    }

    /**
     * Automatycznie uzupełnia brakujące zdjęcia pozycji w zamówieniach z ostatnich 60 dni
     * (także pobranych wcześniej). Każde zamówienie sprawdzane najwyżej raz na 12 godzin.
     */
    public function repairImages(int $limit = 15): int
    {
        $db=$this->repo->db(); $repaired=0; $checked=0;
        $sources=[];
        $rows=$db->fetchAll("SELECT o.id,o.details_json,a.platform,a.source_id FROM om_orders o JOIN om_accounts a ON a.id=o.account_id WHERE a.platform IN ('allegro','empik','mediamarkt','erli','morele') AND o.ordered_at>=:since ORDER BY o.id DESC LIMIT 300",['since'=>gmdate('Y-m-d H:i:s',time()-60*86400)]);
        foreach ($rows as $row) {
            if ($checked>=$limit) { break; }
            try { $details=json_decode((string)$row['details_json'],true,512,JSON_THROW_ON_ERROR); } catch (\Throwable $e) { continue; }
            $items=is_array($details['items']??null)?$details['items']:[];
            $missing=array_filter($items,static function ($item) { return is_array($item) && trim((string)($item['image_url']??''))===''; });
            if (!$missing || !is_array($details['raw']??null)) { continue; }
            if ((int)($details['_images_checked_at']??0)>time()-43200) { continue; }
            $checked++;
            $platform=(string)$row['platform'];
            try {
                $integration=$this->service($platform);
                $key=$platform.'|'.$row['source_id'];
                if (!array_key_exists($key,$sources)) {
                    $sources[$key]=null;
                    foreach ($integration->listAccounts() as $candidate) { if ((int)$candidate['id']===(int)$row['source_id'] && !empty($candidate['is_active'])) { $sources[$key]=$candidate; break; } }
                }
                $found=0;
                if ($sources[$key] && method_exists($integration,'enrichOrderImages')) {
                    $fresh=OrderNormalizer::normalize($platform,$integration->enrichOrderImages($sources[$key],$details['raw']),0,time()+86400);
                    foreach ((array)($fresh['details']['items']??[]) as $index=>$item) {
                        $url=trim((string)($item['image_url']??''));
                        if ($url!=='' && isset($details['items'][$index]) && trim((string)($details['items'][$index]['image_url']??''))==='') { $details['items'][$index]['image_url']=$url; $found++; }
                    }
                }
                if ($found) { $repaired++; }
            } catch (\Throwable $e) { /* Brak zdjęcia nigdy nie blokuje zamówienia; ponowienie za 12 h. */ }
            $details['_images_checked_at']=time();
            $db->update('om_orders',['details_json'=>OrderRepository::json($details)],'id=:id',['id'=>(int)$row['id']]);
        }
        return $repaired;
    }

    private function syncShop(array $account): array
    {
        $db=$this->repo->db();
        $service=new AltreoShopOrdersService(new SettingRepository($db));
        $since=gmdate('Y-m-d H:i:s',time()-7*86400);
        $afterId=0; $added=0; $updated=0; $skipped=0;
        do {
            $page=$service->readPage($since,$afterId);
            foreach ($page['orders'] as $raw) {
                if (!is_array($raw)) { throw new RuntimeException('Nieprawidłowe zamówienie w odpowiedzi sklepu.'); }
                $order=OrderNormalizer::normalize('altreo',$raw,time()-7*86400,time());
                if ($order===null) {
                    $order=OrderNormalizer::normalize('altreo',$raw,0,time());
                    if ($order===null || !$db->fetchColumn('SELECT id FROM om_orders WHERE account_id=:a AND external_id=:e',['a'=>(int)$account['id'],'e'=>$order['external_id']])) { $skipped++; continue; }
                }
                if ($this->repo->import((int)$account['id'],$order)) { $added++; } else { $updated++; }
            }
            $next=$page['next_after_id']??null;
            if ($next!==null && (int)$next<=$afterId) { throw new RuntimeException('API sklepu zwróciło nieprawidłowy kursor.'); }
            $afterId=$next===null?0:(int)$next;
        } while ($afterId>0);
        $db->update('om_accounts',['last_sync'=>gmdate('Y-m-d H:i:s'),'last_error'=>null,'next_attempt'=>0,'synced_until'=>gmdate('Y-m-d H:i:s')],'id=:id',['id'=>$account['id']]);
        return ['account'=>$account['name'],'added'=>$added,'updated'=>$updated,'skipped'=>$skipped,'more'=>false,'message'=>"Nowe: $added · odświeżone: $updated · pominięte: $skipped"];
    }

    /**
     * Mirakl (Empik/MediaMarkt) orders arrive without full delivery data while
     * WAITING_ACCEPTANCE; accepting via OR21 lets Mirakl release the buyer's
     * address and payment confirmation on the next sync pass. One order's
     * acceptance failure must never block the rest of the page.
     */
    private function autoAcceptIfEligible(string $platform, $integration, array $source, array $account, array $raw, string $remoteStatus, string $externalId): void
    {
        if (!in_array($platform, ['empik', 'mediamarkt'], true) || empty($account['auto_accept'])) { return; }
        if (strtoupper($remoteStatus) !== 'WAITING_ACCEPTANCE') { return; }
        if (!method_exists($integration, 'acceptOrder')) { return; }
        $orderId=(int)$this->repo->db()->fetchColumn('SELECT id FROM om_orders WHERE account_id=:a AND external_id=:e',['a'=>(int)$account['id'],'e'=>$externalId]);
        try {
            $integration->acceptOrder($source, $raw);
            if ($orderId) { $this->repo->event($orderId,'Automatyczna akceptacja zamówienia w Mirakl (OR21).','auto-akceptacja'); }
        } catch (\Throwable $e) {
            $diagnostic=OrderSyncError::log($e,['account_id'=>(int)$account['id'],'platform'=>$platform,'external_id'=>$externalId]);
            if ($orderId) { $this->repo->event($orderId,'Automatyczna akceptacja nie powiodła się: '.$diagnostic['message'].' [ID: '.$diagnostic['reference'].']','auto-akceptacja'); }
        }
    }
}
