<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;
use RuntimeException;

final class MarketplaceAccountDeletionService
{
    private const TABLES = [
        'allegro' => ['account'=>'allegro_accounts','offers'=>'allegro_offers','related'=>['allegro_offer_change_queue','allegro_offer_exclusions','allegro_account_tokens','allegro_sync_states']],
        'empik' => ['account'=>'empik_accounts','offers'=>'empik_offers','related'=>['empik_offer_change_queue']],
        'mediamarkt' => ['account'=>'mediamarkt_accounts','offers'=>'mediamarkt_offers','related'=>['mediamarkt_offer_change_queue']],
        'erli' => ['account'=>'erli_accounts','offers'=>'erli_products','related'=>['erli_product_change_queue']],
    ];

    private Database $db;

    public function __construct(Database $db) { $this->db=$db; }

    public function offerCounts(string $platform): array
    {
        if ($platform==='morele') {
            return [1=>(int)$this->db->fetchColumn('SELECT COUNT(*) FROM morele_offers WHERE account_id=1')];
        }
        $tables=$this->tables($platform);
        $result=[];
        foreach ($this->db->fetchAll('SELECT account_id,COUNT(*) total FROM '.$tables['offers'].' GROUP BY account_id') as $row) {
            $result[(int)$row['account_id']]=(int)$row['total'];
        }
        return $result;
    }

    public function delete(string $platform,int $accountId): array
    {
        if ($accountId<1) { throw new InvalidArgumentException('Nieprawidłowe konto.'); }
        if ($platform==='morele') { return $this->deleteMorele($accountId); }
        if ($platform==='temu') { return $this->deleteTemu($accountId); }
        $tables=$this->tables($platform);
        return $this->db->transaction(function () use ($platform,$accountId,$tables): array {
            $params=['id'=>$accountId];
            $lock=$this->db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            $account=$this->db->fetch('SELECT id,name FROM '.$tables['account'].' WHERE id=:id'.$lock,$params);
            if (!$account) { throw new InvalidArgumentException('Konto nie istnieje lub zostało już usunięte.'); }
            if ($platform==='erli' && (int)$this->db->fetchColumn('SELECT is_running FROM erli_accounts WHERE id=:id',$params)===1) {
                throw new RuntimeException('Trwa synchronizacja tego konta. Spróbuj ponownie po jej zakończeniu.');
            }
            if ($platform==='allegro' && (int)$this->db->fetchColumn('SELECT is_running FROM allegro_sync_states WHERE account_id=:id',$params)===1) {
                throw new RuntimeException('Trwa synchronizacja tego konta. Spróbuj ponownie po jej zakończeniu.');
            }
            $offers=(int)$this->db->fetchColumn('SELECT COUNT(*) FROM '.$tables['offers'].' WHERE account_id=:id',$params);
            foreach ($tables['related'] as $table) { $this->db->delete($table,'account_id=:id',$params); }
            $this->db->delete($tables['offers'],'account_id=:id',$params);
            $this->db->delete($tables['account'],'id=:id',$params);
            return ['name'=>(string)$account['name'],'offers'=>$offers];
        });
    }

    private function deleteMorele(int $accountId): array
    {
        if ($accountId!==1) { throw new InvalidArgumentException('Konto Morele nie istnieje.'); }
        return $this->db->transaction(function (): array {
            $keys=['morele_account','morele_client_id','morele_client_secret','morele_access_token','morele_refresh_token'];
            $settings=$this->db->fetchAll("SELECT setting_key,setting_value FROM app_settings WHERE setting_key IN ('morele_account','morele_client_id','morele_client_secret','morele_access_token','morele_refresh_token')");
            $values=[];
            foreach ($settings as $setting) { $values[$setting['setting_key']]=(string)$setting['setting_value']; }
            if (trim($values['morele_client_id']??'')==='' && trim($values['morele_client_secret']??'')==='') {
                throw new InvalidArgumentException('Konto Morele nie jest skonfigurowane.');
            }
            $offers=(int)$this->db->fetchColumn('SELECT COUNT(*) FROM morele_offers WHERE account_id=1');
            $this->db->query('DELETE FROM morele_offer_change_queue WHERE offer_row_id IN (SELECT id FROM morele_offers WHERE account_id=1)');
            $this->db->delete('morele_offers','account_id=1');
            foreach ($keys as $key) { $this->db->delete('app_settings','setting_key=:key',['key'=>$key]); }
            return ['name'=>trim($values['morele_account']??'')?:'Morele','offers'=>$offers];
        });
    }

    private function deleteTemu(int $accountId): array
    {
        if ($accountId!==1) { throw new InvalidArgumentException('Konto Temu nie istnieje.'); }
        return $this->db->transaction(function (): array {
            $configured=(string)$this->db->fetchColumn("SELECT setting_value FROM app_settings WHERE setting_key='temu_app_key'");
            if (trim($configured)==='') { throw new InvalidArgumentException('Konto Temu nie jest skonfigurowane.'); }
            foreach (['temu_app_key','temu_app_secret','temu_access_token','temu_shop_id'] as $key) {
                $this->db->delete('app_settings','setting_key=:key',['key'=>$key]);
            }
            return ['name'=>'Temu','offers'=>0];
        });
    }

    private function tables(string $platform): array
    {
        if (!isset(self::TABLES[$platform])) { throw new InvalidArgumentException('Nieobsługiwana platforma.'); }
        return self::TABLES[$platform];
    }
}
