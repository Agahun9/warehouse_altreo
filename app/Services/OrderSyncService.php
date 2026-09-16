<?php

declare(strict_types=1);
namespace App\Services;
use App\Models\OrderRepository;
use RuntimeException;

final class OrderSyncService
{
    private $repo;
    private $services = [];
    public function __construct(OrderRepository $repo) { $this->repo=$repo; }
    private function service(string $platform)
    {
        $classes = ['allegro'=>AllegroService::class,'empik'=>EmpikService::class,'mediamarkt'=>MediaMarktService::class,'erli'=>ErliService::class,'morele'=>MoreleService::class];
        if (!isset($classes[$platform])) { throw new RuntimeException('Nieznana platforma.'); }
        if (!isset($this->services[$platform])) { $this->services[$platform] = $platform === 'allegro' ? new AllegroService(true) : new $classes[$platform](); }
        return $this->services[$platform];
    }
    public function discover(): array
    {
        $errors=[];
        foreach (['allegro','empik','mediamarkt','erli','morele'] as $platform) {
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
                if ($account['platform']==='morele') { throw new RuntimeException('Morele: import czeka na specyfikację Orders API dla konta. Nie wysłano żądania.'); }
                $source=null;
                foreach ($this->service($account['platform'])->listAccounts() as $candidate) {
                    if ((int)$candidate['id']===(int)$account['source_id'] && !empty($candidate['is_active'])) { $source=$candidate; break; }
                }
                if (!$source) { throw new RuntimeException('Konto źródłowe jest nieaktywne lub zostało usunięte.'); }
                $now=time(); $cutoff=$now-7*86400;
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
                    if ($order===null) { $skipped++; continue; }
                    if ($this->repo->import((int)$account['id'],$order)) { $added++; } else { $updated++; }
                    $this->autoAcceptIfEligible($platform,$integration,$source,$account,$raw,(string)$order['remote_status'],(string)$order['external_id']);
                }
                $more=count($rows)>0;
                if ($more) {
                    if ($platform==='erli') {
                        $last=end($rows);
                        if (empty($last['cursor']) || $last['cursor']===$state['cursor']) { throw new RuntimeException('API nie zwróciło kolejnego kursora. Ponowienie nie utworzy duplikatów.'); }
                        $state['cursor']=$last['cursor'];
                    } else {
                        $state['cursor']=(string)((int)$state['cursor']+count($rows));
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
                $db->update('om_accounts',['cursor_json'=>$more ? OrderRepository::json($state) : null,'last_sync'=>gmdate('Y-m-d H:i:s'),'last_error'=>null,'next_attempt'=>0,'synced_until'=>$more ? $account['synced_until'] : gmdate('Y-m-d H:i:s',$completedTo)],'id=:id',['id'=>$account['id']]);
                $results[]=['account'=>$account['name'],'added'=>$added,'updated'=>$updated,'skipped'=>$skipped,'more'=>$more,'message'=>"Nowe: $added · odświeżone: $updated · pominięte: $skipped".($more?' · pozostały kolejne strony':' · zakończono')];
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
