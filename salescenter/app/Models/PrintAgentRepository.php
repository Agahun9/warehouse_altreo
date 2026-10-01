<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;
use App\Core\Tenant;
use App\Services\KsefService;
use App\Services\OrderDocumentService;
use InvalidArgumentException;

final class PrintAgentRepository
{
    public const DEFAULT_VAT_RATES=['A'=>'23','B'=>'8','C'=>'7','D'=>'5','E'=>'0','F'=>'0','G'=>'zw'];

    private $db;

    public function __construct(Database $db) { $this->db=$db; }

    /** Public print-agent endpoint used in queued label jobs. */
    public static function apiBase(): string
    {
        $config=Config::get('app');
        $public=rtrim((string)($config['public_base_url']??''),'/');
        if ($public==='') { return 'print-agent-api.php'; }
        return preg_replace('#/index\.php$#','/print-agent-api.php',$public)?:'print-agent-api.php';
    }

    public function ensureSchema(): void
    {
        $sqlite=$this->db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME)==='sqlite';
        $stationId=$sqlite?'INTEGER PRIMARY KEY AUTOINCREMENT':'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $suffix=$sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';
        $this->db->query("CREATE TABLE IF NOT EXISTS print_agent_stations (id $stationId, name VARCHAR(150) NOT NULL, token_hash CHAR(64) NOT NULL UNIQUE, enabled INTEGER NOT NULL DEFAULT 1, printers_json LONGTEXT NOT NULL, default_printer VARCHAR(300) NULL, agent_version VARCHAR(50) NULL, last_seen_at VARCHAR(30) NULL, created_at VARCHAR(30) NOT NULL)$suffix");
        $this->db->query("CREATE TABLE IF NOT EXISTS print_agent_jobs (id CHAR(36) PRIMARY KEY, station_id BIGINT NOT NULL, shipment_id BIGINT NOT NULL, pdf_url LONGTEXT NOT NULL, download_token_hash CHAR(64) NOT NULL, download_expires_at VARCHAR(30) NOT NULL, printer_name VARCHAR(300) NOT NULL, print_settings VARCHAR(300) NULL, status VARCHAR(30) NOT NULL DEFAULT 'queued', status_message VARCHAR(1000) NULL, created_by VARCHAR(150) NOT NULL, created_at VARCHAR(30) NOT NULL, claimed_at VARCHAR(30) NULL, reported_at VARCHAR(30) NULL)$suffix");
        $this->db->query("CREATE TABLE IF NOT EXISTS print_fiscal_printers (id $stationId, station_id BIGINT NOT NULL, device_key VARCHAR(190) NOT NULL, name VARCHAR(150) NOT NULL, host VARCHAR(255) NOT NULL, port INTEGER NOT NULL, serial_number VARCHAR(100) NULL, receipt_series VARCHAR(40) NOT NULL DEFAULT 'POS', next_number BIGINT NOT NULL DEFAULT 1, environment VARCHAR(20) NOT NULL DEFAULT 'sandbox', enabled INTEGER NOT NULL DEFAULT 0, last_seen_at VARCHAR(30) NULL, created_at VARCHAR(30) NOT NULL, deleted_at VARCHAR(30) NULL, UNIQUE(station_id,device_key))$suffix");
        $this->db->query("CREATE TABLE IF NOT EXISTS print_fiscal_jobs (id CHAR(36) PRIMARY KEY, printer_id BIGINT NOT NULL, order_id BIGINT NOT NULL, local_number VARCHAR(100) NOT NULL, payload_json LONGTEXT NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'queued', status_message VARCHAR(1000) NULL, fiscal_number VARCHAR(100) NULL, created_by VARCHAR(150) NOT NULL, created_at VARCHAR(30) NOT NULL, claimed_at VARCHAR(30) NULL, reported_at VARCHAR(30) NULL, UNIQUE(printer_id,order_id))$suffix");
        $printerColumns=$sqlite ? array_column($this->db->fetchAll('PRAGMA table_info(print_fiscal_printers)'),'name') : array_column($this->db->fetchAll('SHOW COLUMNS FROM print_fiscal_printers'),'Field');
        if (!in_array('deleted_at',$printerColumns,true)) {
            try { $this->db->query('ALTER TABLE print_fiscal_printers ADD COLUMN deleted_at VARCHAR(30) NULL'); }
            catch (\PDOException $e) { if ((int)($e->errorInfo[1]??0)!==1060) { throw $e; } }
        }
        if (!in_array('vat_rates_json',$printerColumns,true)) {
            try { $this->db->query('ALTER TABLE print_fiscal_printers ADD COLUMN vat_rates_json LONGTEXT NULL'); }
            catch (\PDOException $e) { if ((int)($e->errorInfo[1]??0)!==1060) { throw $e; } }
        }
        foreach (['print_agent_job_next'=>'station_id,status,created_at','print_agent_job_shipment'=>'shipment_id,created_at'] as $name=>$columns) {
            if ($sqlite) { $this->db->query("CREATE INDEX IF NOT EXISTS $name ON print_agent_jobs ($columns)"); }
            elseif (!$this->db->fetch("SHOW INDEX FROM print_agent_jobs WHERE Key_name=:name",['name'=>$name])) {
                try { $this->db->query("CREATE INDEX $name ON print_agent_jobs ($columns)"); }
                catch (\PDOException $e) { if ((int)($e->errorInfo[1]??0)!==1061) { throw $e; } }
            }
        }
    }

    public function stations(): array
    {
        $rows=$this->db->fetchAll('SELECT * FROM print_agent_stations ORDER BY enabled DESC,name,id');
        foreach ($rows as &$row) {
            $printers=json_decode((string)$row['printers_json'],true);
            $row['printers']=is_array($printers)?array_values(array_filter($printers,'is_string')):[];
            $row['online']=!empty($row['last_seen_at']) && strtotime((string)$row['last_seen_at'].' UTC')>=time()-180;
        }
        unset($row);
        return $rows;
    }

    public function jobs(int $limit=100): array
    {
        $limit=max(1,min(250,$limit));
        return $this->db->fetchAll('SELECT j.*,s.name station_name,sh.order_id FROM print_agent_jobs j JOIN print_agent_stations s ON s.id=j.station_id LEFT JOIN om_shipments sh ON sh.id=j.shipment_id ORDER BY j.created_at DESC LIMIT '.$limit);
    }

    public function fiscalPrinters(): array
    {
        $rows=$this->db->fetchAll('SELECT p.*,s.name station_name,s.enabled station_enabled,s.last_seen_at station_last_seen_at FROM print_fiscal_printers p JOIN print_agent_stations s ON s.id=p.station_id WHERE p.deleted_at IS NULL ORDER BY p.enabled DESC,p.name,p.id');
        foreach ($rows as &$row) { $row['vat_rates']=json_decode((string)($row['vat_rates_json']??''),true)?:self::DEFAULT_VAT_RATES; $row['station_online']=$row['station_enabled'] && !empty($row['station_last_seen_at']) && strtotime((string)$row['station_last_seen_at'].' UTC')>=time()-180; }
        unset($row);
        return $rows;
    }

    public function fiscalJobs(int $limit=100): array
    {
        $limit=max(1,min(250,$limit));
        return $this->db->fetchAll('SELECT j.*,p.name printer_name,p.environment,p.enabled printer_enabled,p.deleted_at printer_deleted_at,p.station_id printer_station_id FROM print_fiscal_jobs j JOIN print_fiscal_printers p ON p.id=j.printer_id ORDER BY j.created_at DESC LIMIT '.$limit);
    }

    public function createStation(string $name): string
    {
        $name=mb_substr(trim($name),0,150,'UTF-8');
        if ($name==='') { throw new InvalidArgumentException('Podaj nazwę stanowiska.'); }
        if ($this->db->fetchColumn('SELECT id FROM print_agent_stations WHERE name=:name',['name'=>$name])) { throw new InvalidArgumentException('Stanowisko o tej nazwie już istnieje.'); }
        $token=$this->newToken();
        $this->db->insert('print_agent_stations',['name'=>$name,'token_hash'=>hash('sha256',$token),'enabled'=>1,'printers_json'=>'[]','default_printer'=>null,'agent_version'=>null,'last_seen_at'=>null,'created_at'=>gmdate('Y-m-d H:i:s')]);
        return $token;
    }

    public function regenerateToken(int $stationId): string
    {
        $this->requireStation($stationId);
        $token=$this->newToken();
        $this->db->update('print_agent_stations',['token_hash'=>hash('sha256',$token)],'id=:id',['id'=>$stationId]);
        return $token;
    }

    public function setStationEnabled(int $stationId,bool $enabled): void
    {
        $this->requireStation($stationId);
        $this->db->update('print_agent_stations',['enabled'=>$enabled?1:0],'id=:id',['id'=>$stationId]);
    }

    public function deleteStation(int $stationId): void
    {
        $this->requireStation($stationId);
        $activeLabels=(int)$this->db->fetchColumn("SELECT COUNT(*) FROM print_agent_jobs WHERE station_id=:station AND status IN ('queued','processing')",['station'=>$stationId]);
        $activeFiscal=(int)$this->db->fetchColumn("SELECT COUNT(*) FROM print_fiscal_jobs j JOIN print_fiscal_printers p ON p.id=j.printer_id WHERE p.station_id=:station AND j.status IN ('queued','processing')",['station'=>$stationId]);
        if ($activeLabels+$activeFiscal>0) { throw new InvalidArgumentException('Nie można usunąć agenta z aktywnymi zadaniami. Poczekaj na zakończenie albo najpierw je rozstrzygnij.'); }
        $this->db->transaction(function () use ($stationId) {
            $printerIds=array_map('intval',array_column($this->db->fetchAll('SELECT id FROM print_fiscal_printers WHERE station_id=:station',['station'=>$stationId]),'id'));
            foreach ($printerIds as $printerId) { $this->db->delete('print_fiscal_jobs','printer_id=:printer',['printer'=>$printerId]); }
            $this->db->delete('print_fiscal_printers','station_id=:station',['station'=>$stationId]);
            $this->db->delete('print_agent_jobs','station_id=:station',['station'=>$stationId]);
            $this->db->delete('print_agent_stations','id=:station',['station'=>$stationId]);
        });
    }

    /** Called before an order is deleted: blocks while a print job for it is still in flight, otherwise purges finished job history. */
    public function purgeOrderJobs(int $orderId): void
    {
        $activeFiscal=(int)$this->db->fetchColumn("SELECT COUNT(*) FROM print_fiscal_jobs WHERE order_id=:order AND status IN ('queued','processing')",['order'=>$orderId]);
        $activeLabels=(int)$this->db->fetchColumn("SELECT COUNT(*) FROM print_agent_jobs WHERE status IN ('queued','processing') AND shipment_id IN (SELECT id FROM om_shipments WHERE order_id=:order)",['order'=>$orderId]);
        if ($activeFiscal+$activeLabels>0) { throw new InvalidArgumentException('Nie można usunąć zamówienia z aktywnymi zadaniami druku. Poczekaj na zakończenie albo najpierw je rozstrzygnij.'); }
        $this->db->transaction(function () use ($orderId) {
            $this->db->delete('print_fiscal_jobs','order_id=:order',['order'=>$orderId]);
            $this->db->query('DELETE FROM print_agent_jobs WHERE shipment_id IN (SELECT id FROM om_shipments WHERE order_id=:order)',['order'=>$orderId]);
        });
    }

    public function authenticate(string $plainToken): ?array
    {
        if ($plainToken==='' || strlen($plainToken)>500) { return null; }
        $station=$this->db->fetch('SELECT * FROM print_agent_stations WHERE token_hash=:hash AND enabled=1',['hash'=>hash('sha256',$plainToken)]);
        return $station?:null;
    }

    public function heartbeat(int $stationId,array $input,string $reportedName): array
    {
        $printers=[];
        foreach ((array)($input['printers']??[]) as $printer) {
            $printer=mb_substr(trim((string)$printer),0,300,'UTF-8');
            if ($printer!=='' && !in_array($printer,$printers,true) && count($printers)<100) { $printers[]=$printer; }
        }
        natcasesort($printers); $printers=array_values($printers);
        $default=mb_substr(trim((string)($input['defaultPrinter']??'')),0,300,'UTF-8');
        if ($default!=='' && !in_array($default,$printers,true)) { $default=''; }
        $this->db->update('print_agent_stations',[
            'printers_json'=>json_encode($printers,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
            'default_printer'=>$default!==''?$default:null,
            'agent_version'=>mb_substr(trim((string)($input['agentVersion']??'')),0,50,'UTF-8')?:null,
            'last_seen_at'=>gmdate('Y-m-d H:i:s'),
        ],'id=:id',['id'=>$stationId]);
        $fiscalCount=$this->saveDiscoveredFiscalPrinters($stationId,(array)($input['fiscalPrinters']??[]),(string)($input['environment']??'sandbox'));
        return ['ok'=>true,'station'=>(string)$this->db->fetchColumn('SELECT name FROM print_agent_stations WHERE id=:id',['id'=>$stationId]),'reportedName'=>$reportedName,'printers'=>count($printers),'fiscalPrinters'=>$fiscalCount];
    }

    public function configureFiscalPrinter(int $printerId,string $series,string $environment,bool $enabled,?array $vatRates=null): void
    {
        $printer=$this->db->fetch('SELECT id,environment,vat_rates_json FROM print_fiscal_printers WHERE id=:id AND deleted_at IS NULL',['id'=>$printerId]);
        if (!$printer) { throw new InvalidArgumentException('Nie znaleziono drukarki fiskalnej.'); }
        $series=strtoupper(trim($series));
        if (preg_match('/^[A-Z0-9_-]{1,40}$/D',$series)!==1) { throw new InvalidArgumentException('Seria może zawierać litery, cyfry, _ i -.'); }
        $environment=$environment==='production'?'production':'sandbox';
        $currentRates=json_decode((string)($printer['vat_rates_json']??''),true)?:self::DEFAULT_VAT_RATES;
        $vatRates=$vatRates??$currentRates;
        if (array_keys($vatRates)!==array_keys(self::DEFAULT_VAT_RATES)) { throw new InvalidArgumentException('Uzupełnij wszystkie stawki VAT A–G.'); }
        foreach ($vatRates as $rate) {
            if (!is_string($rate) || !in_array($rate,['23','8','7','5','0','zw','nieaktywna'],true)) { throw new InvalidArgumentException('Nieprawidłowa stawka VAT drukarki.'); }
        }
        if (($environment!==$printer['environment'] || $vatRates!==$currentRates) && (int)$this->db->fetchColumn("SELECT COUNT(*) FROM print_fiscal_jobs WHERE printer_id=:id AND status IN ('queued','processing')",['id'=>$printerId])>0) {
            throw new InvalidArgumentException('Najpierw rozstrzygnij oczekujące zadania drukarki; nie można zmienić ich trybu ani stawek w kolejce.');
        }
        $this->db->update('print_fiscal_printers',['receipt_series'=>$series,'environment'=>$environment,'enabled'=>$enabled?1:0,'vat_rates_json'=>json_encode($vatRates,JSON_THROW_ON_ERROR)],'id=:id',['id'=>$printerId]);
    }

    public function deleteFiscalPrinter(int $printerId): void
    {
        $printer=$this->db->fetch('SELECT id FROM print_fiscal_printers WHERE id=:id AND deleted_at IS NULL',['id'=>$printerId]);
        if (!$printer) { throw new InvalidArgumentException('Nie znaleziono drukarki fiskalnej.'); }
        $active=(int)$this->db->fetchColumn("SELECT COUNT(*) FROM print_fiscal_jobs WHERE printer_id=:id AND status IN ('queued','processing')",['id'=>$printerId]);
        if ($active>0) { throw new InvalidArgumentException('Drukarka ma aktywne zadania fiskalne. Najpierw je rozstrzygnij.'); }
        $this->db->transaction(function () use ($printerId) {
            $this->db->update('print_fiscal_printers',['enabled'=>0,'deleted_at'=>gmdate('Y-m-d H:i:s')],'id=:id',['id'=>$printerId]);
            $this->db->query('UPDATE om_series SET fiscal_printer_id=0 WHERE fiscal_printer_id=:id',['id'=>$printerId]);
            $setting=$this->db->fetch("SELECT value_json FROM om_settings WHERE setting_key='receipt_printer'");
            if ($setting) {
                $value=json_decode((string)$setting['value_json'],true);
                if (is_array($value) && (int)($value['printer_id']??0)===$printerId) {
                    $value['printer_id']=0;
                    $this->db->update('om_settings',['value_json'=>json_encode($value,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)],'setting_key=:key',['key'=>'receipt_printer']);
                }
            }
        });
    }

    public function addFiscalPrinter(int $stationId,string $name,string $host,int $port): int
    {
        $this->requireStation($stationId,true);
        $name=mb_substr(trim($name),0,150,'UTF-8'); $host=mb_substr(trim($host),0,255,'UTF-8');
        if ($name==='') { throw new InvalidArgumentException('Podaj nazwę drukarki fiskalnej.'); }
        if ($host==='' || preg_match('/^[A-Za-z0-9._:-]+$/D',$host)!==1) { throw new InvalidArgumentException('Podaj poprawny adres IP lub nazwę hosta.'); }
        if ($port<1 || $port>65535) { throw new InvalidArgumentException('Port musi mieścić się między 1 a 65535.'); }
        $key='manual:'.strtolower($host).':'.$port;
        $existing=$this->db->fetch('SELECT id,deleted_at FROM print_fiscal_printers WHERE station_id=:station AND device_key=:key',['station'=>$stationId,'key'=>$key]);
        if ($existing && $existing['deleted_at']===null) { throw new InvalidArgumentException('Ta drukarka jest już dodana do stanowiska.'); }
        if ($existing) {
            $this->db->update('print_fiscal_printers',['name'=>$name,'host'=>$host,'port'=>$port,'enabled'=>0,'deleted_at'=>null],'id=:id',['id'=>$existing['id']]);
            return (int)$existing['id'];
        }
        return (int)$this->db->insert('print_fiscal_printers',['station_id'=>$stationId,'device_key'=>$key,'name'=>$name,'host'=>$host,'port'=>$port,'serial_number'=>null,'receipt_series'=>'POS','next_number'=>1,'environment'=>'sandbox','enabled'=>0,'last_seen_at'=>null,'created_at'=>gmdate('Y-m-d H:i:s')]);
    }

    public function queueFiscalReceipt(int $orderId,int $printerId,string $createdBy,int $documentId=0): string
    {
        $queue=function () use ($orderId,$printerId,$createdBy,$documentId) {
            $printerLock=$this->db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME)==='sqlite'?'':' FOR UPDATE';
            $printer=$this->db->fetch('SELECT * FROM print_fiscal_printers WHERE id=:id AND enabled=1 AND deleted_at IS NULL'.$printerLock,['id'=>$printerId]);
            if (!$printer) { throw new InvalidArgumentException('Wybierz aktywną drukarkę fiskalną.'); }
            $this->requireStation((int)$printer['station_id'],true);
            $vatRates=json_decode((string)($printer['vat_rates_json']??''),true)?:self::DEFAULT_VAT_RATES;
            $orderLock=$this->db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME)==='sqlite'?'':' FOR UPDATE';
            $order=$this->db->fetch('SELECT * FROM om_orders WHERE id=:id'.$orderLock,['id'=>$orderId]);
            if (!$order) { throw new InvalidArgumentException('Nie znaleziono zamówienia.'); }
            if ($this->db->fetchColumn('SELECT id FROM print_fiscal_jobs WHERE order_id=:order',['order'=>$orderId])) { throw new InvalidArgumentException('Paragon dla tego zamówienia jest już w kolejce fiskalnej.'); }
            $details=json_decode((string)$order['details_json'],true,512,JSON_THROW_ON_ERROR);
            $document=null;
            if ($documentId>0) {
                $document=$this->db->fetch("SELECT snapshot_json,number FROM om_documents WHERE id=:id AND order_id=:order AND kind='receipt'",['id'=>$documentId,'order'=>$orderId]);
                if (!$document) { throw new InvalidArgumentException('Nie znaleziono wystawionego paragonu.'); }
                $snapshot=json_decode((string)$document['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
                $details['items']=$snapshot['items']??[];
                $details['shipping_cents']=0;
                $details['payment_method']=$snapshot['payment_method']??$details['payment_method']??'';
                $buyerNip=isset($snapshot['buyer_nip'])?(string)$snapshot['buyer_nip']:OrderDocumentService::receiptBuyerNip((string)($snapshot['buyer']??''));
            } else {
                $buyerNip=KsefService::normalizeNip((string)($details['invoice_form']['nip']??''));
                if (!KsefService::validNip($buyerNip)) { $buyerNip=null; }
            }
            OrderDocumentService::assertReceiptNipLimit($buyerNip,(int)$order['total_cents'],(string)$order['currency']);
            $items=[];
            foreach ((array)($details['items']??[]) as $item) {
                $quantity=(int)($item['quantity']??1); $unitCents=(int)($item['unit_cents']??0);
                if ($quantity<1) { throw new InvalidArgumentException('Paragon zawiera pozycję z zerową ilością.'); }
                if ($unitCents<0) { throw new InvalidArgumentException('Paragon zawiera pozycję z ujemną ceną.'); }
                $vat=strtolower(trim((string)($item['vat']??'23')));
                if (!in_array($vat,['23','8','7','5','0','zw'],true)) { throw new InvalidArgumentException('Fiskalizacja nie obsługuje stawki VAT '.$vat.'.'); }
                if (!in_array($vat,$vatRates,true)) { throw new InvalidArgumentException('Drukarka nie ma skonfigurowanej stawki VAT '.$vat.'.'); }
                if (trim((string)($item['name']??''))==='') { throw new InvalidArgumentException('Pozycja paragonu musi mieć nazwę.'); }
                $items[]=['name'=>mb_substr(trim((string)($item['name']??'Towar')),0,80,'UTF-8'),'quantity'=>$quantity,'unitCents'=>$unitCents,'vat'=>$vat];
            }
            $shipping=(int)($details['shipping_cents']??0);
            if ($shipping>0) { $items[]=['name'=>'Dostawa','quantity'=>1,'unitCents'=>$shipping,'vat'=>'23']; }
            if (!$items) { throw new InvalidArgumentException('Zamówienie nie ma pozycji do fiskalizacji.'); }
            if (strtoupper((string)$order['currency'])!=='PLN') { throw new InvalidArgumentException('Automatyczna fiskalizacja obsługuje wyłącznie walutę PLN.'); }
            foreach ($items as $item) { if (!in_array($item['vat'],$vatRates,true)) { throw new InvalidArgumentException('Drukarka nie ma skonfigurowanej stawki VAT '.$item['vat'].'.'); } }
            $reference=(string)($document['number']??$order['external_id']).' zam:#'.$orderId;
            if ($printer['environment']==='production' && mb_strlen($reference,'UTF-8')>30) { throw new InvalidArgumentException('Numer dokumentu i zamówienia przekracza 30 znaków pola Posnet.'); }
            $number=(int)$printer['next_number'];
            $localNumber=$printer['receipt_series'].'/'.gmdate('Y').'/'.$number;
            $id=$this->uuid();
            $itemsTotal=0; foreach ($items as $item) { $itemsTotal+=(int)$item['quantity']*(int)$item['unitCents']; }
            if ($itemsTotal!==(int)$order['total_cents']) { throw new InvalidArgumentException('Suma pozycji paragonu nie zgadza się z kwotą zamówienia.'); }
            [$paymentType,$paymentName]=$this->fiscalPayment($details);
            $payload=['orderId'=>$orderId,'orderNumber'=>(string)($document['number']??$order['external_id']),'currency'=>(string)$order['currency'],'totalCents'=>(int)$order['total_cents'],'items'=>$items,'paymentType'=>$paymentType,'paymentName'=>$paymentName,'vatRates'=>$vatRates];
            if ($buyerNip!==null && $buyerNip!=='') { $payload['buyerNip']=$buyerNip; }
            $this->db->insert('print_fiscal_jobs',['id'=>$id,'printer_id'=>$printerId,'order_id'=>$orderId,'local_number'=>$localNumber,'payload_json'=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'status'=>'queued','status_message'=>'Oczekuje na agenta.','fiscal_number'=>null,'created_by'=>mb_substr($createdBy,0,150,'UTF-8'),'created_at'=>gmdate('Y-m-d H:i:s'),'claimed_at'=>null,'reported_at'=>null]);
            $this->db->update('print_fiscal_printers',['next_number'=>$number+1],'id=:id',['id'=>$printerId]);
            return $id;
        };
        return $this->db->pdo()->inTransaction()?$queue():$this->db->transaction($queue);
    }

    public function nextFiscalJob(int $stationId,string $environment): ?array
    {
        $environment=$environment==='production'?'production':'sandbox';
        for ($attempt=0;$attempt<3;$attempt++) {
            $job=$this->db->transaction(function () use ($stationId,$environment) {
                $candidate=$this->db->fetch("SELECT j.*,p.device_key,p.name printer_name,p.host,p.port,p.serial_number,p.environment FROM print_fiscal_jobs j JOIN print_fiscal_printers p ON p.id=j.printer_id WHERE p.station_id=:station AND p.enabled=1 AND p.environment=:environment AND j.status='queued' ORDER BY j.created_at,j.id LIMIT 1",['station'=>$stationId,'environment'=>$environment]);
                if (!$candidate) { return null; }
                $updated=$this->db->query("UPDATE print_fiscal_jobs SET status='processing',status_message='Agent odebrał zadanie.',claimed_at=:now WHERE id=:id AND status='queued'",['now'=>gmdate('Y-m-d H:i:s'),'id'=>$candidate['id']])->rowCount();
                return $updated===1?$candidate:false;
            });
            if ($job===null) { return null; }
            if (is_array($job)) { return ['id'=>$job['id'],'localNumber'=>$job['local_number'],'environment'=>$job['environment'],'deviceKey'=>$job['device_key'],'printerName'=>$job['printer_name'],'host'=>$job['host'],'port'=>(int)$job['port'],'serialNumber'=>$job['serial_number'],'receipt'=>json_decode((string)$job['payload_json'],true,512,JSON_THROW_ON_ERROR)]; }
        }
        return null;
    }

    /** Legacy VAT mismatch is raised before trinit; no fiscal transaction was attempted. */
    public static function fiscalRetryAllowed(array $job): bool
    {
        return ($job['status']??'')==='error' && empty($job['fiscal_number'])
            && preg_match('/^Stawka VAT [A-G] w drukarce różni się od ustawień SalesCenter\. Sprawdź stawki A–G\.$/uD',(string)($job['status_message']??''))===1;
    }

    public function retryFiscalReceipt(int $documentId,string $actor): string
    {
        return $this->db->transaction(function () use ($documentId,$actor) {
            $document=$this->db->fetch("SELECT d.id,d.order_id,d.number,s.document_settings_json FROM om_documents d LEFT JOIN om_series s ON s.id=d.series_id WHERE d.id=:id AND d.kind='receipt'",['id'=>$documentId]);
            if (!$document) { throw new InvalidArgumentException('Wybierz istniejący paragon.'); }
            $documentSettings=json_decode((string)($document['document_settings_json']??''),true)?:[];
            if (!empty($documentSettings['non_fiscal'])) { throw new InvalidArgumentException('Seria niefiskalna nie może ponowić fiskalizacji. Sprawdź ustawienia serii.'); }
            $lock=$this->db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME)==='sqlite'?'':' FOR UPDATE';
            $job=$this->db->fetch('SELECT * FROM print_fiscal_jobs WHERE order_id=:order'.$lock,['order'=>$document['order_id']]);
            if (!$job || !self::fiscalRetryAllowed($job)) { throw new InvalidArgumentException('Ponowienie jest dozwolone tylko po błędzie stawek VAT przed rozpoczęciem transakcji. Przy niepewnym wyniku najpierw sprawdź kopię paragonu w urządzeniu.'); }
            $printer=$this->db->fetch('SELECT * FROM print_fiscal_printers WHERE id=:id AND enabled=1 AND deleted_at IS NULL'.$lock,['id'=>$job['printer_id']]);
            if (!$printer || $printer['environment']!=='production') { throw new InvalidArgumentException('Włącz tę samą drukarkę w trybie PRODUKCJA. Ponowienie nie zmienia drukarki ani trybu.'); }
            $this->requireStation((int)$printer['station_id'],true);
            $payload=json_decode((string)$job['payload_json'],true,512,JSON_THROW_ON_ERROR);
            if ((string)($payload['orderNumber']??'')!==(string)$document['number']) { throw new InvalidArgumentException('Zadanie dotyczy innego dokumentu. Ponowienie zablokowane.'); }
            $rates=json_decode((string)($printer['vat_rates_json']??''),true)?:self::DEFAULT_VAT_RATES;
            foreach ((array)($payload['items']??[]) as $item) {
                if (!in_array((string)($item['vat']??''),$rates,true)) { throw new InvalidArgumentException('Skonfigurowana drukarka nie obsługuje stawki VAT '.$item['vat'].' z paragonu.'); }
            }
            $payload['vatRates']=$rates;
            $newId=$this->uuid();
            // Rotate the ID so delayed reports from the old attempt cannot overwrite this attempt.
            $updated=$this->db->query("UPDATE print_fiscal_jobs SET id=:new_id,payload_json=:payload,status='queued',status_message=:message,created_by=:actor,created_at=:now,claimed_at=NULL,reported_at=NULL WHERE id=:old_id AND status='error' AND (fiscal_number IS NULL OR fiscal_number='') AND status_message=:previous_message",['new_id'=>$newId,'payload'=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'message'=>'Ponowienie po poprawieniu stawek VAT. Oczekuje na agenta.','actor'=>mb_substr($actor,0,150,'UTF-8'),'now'=>gmdate('Y-m-d H:i:s'),'old_id'=>$job['id'],'previous_message'=>$job['status_message']])->rowCount();
            if ($updated!==1) { throw new InvalidArgumentException('Status zadania zmienił się. Odśwież historię dokumentów.'); }
            (new OrderRepository($this->db))->event((int)$document['order_id'],'Ponowiono zadanie Posnet '.$job['id'].' jako '.$newId.' po błędzie przed transakcją: '.$job['status_message'],$actor);
            return $newId;
        });
    }

    public function receiptLockReason(int $orderId): ?string
    {
        if ($this->db->fetchColumn('SELECT id FROM print_fiscal_jobs WHERE order_id=:order',['order'=>$orderId])) {
            return 'Paragon przekazano do kolejki Posnet. Nie można zmieniać ani usuwać jego danych; najpierw sprawdź wynik fiskalizacji.';
        }
        return null;
    }

    public function reportFiscal(int $stationId,string $jobId,string $status,string $message,?string $fiscalNumber): bool
    {
        if (!in_array($status,['processing','printed','error','printer_offline'],true)) { throw new InvalidArgumentException('Nieprawidłowy status zadania fiskalnego.'); }
        $job=$this->db->fetch('SELECT j.status FROM print_fiscal_jobs j JOIN print_fiscal_printers p ON p.id=j.printer_id WHERE j.id=:id AND p.station_id=:station',['id'=>$jobId,'station'=>$stationId]);
        if (!$job) { return false; }
        if (in_array((string)$job['status'],['printed','error','printer_offline'],true) && $job['status']!==$status) { throw new InvalidArgumentException('Zadanie fiskalne ma już status końcowy.'); }
        $this->db->update('print_fiscal_jobs',['status'=>$status,'status_message'=>mb_substr(trim($message),0,1000,'UTF-8'),'fiscal_number'=>$fiscalNumber?mb_substr(trim($fiscalNumber),0,100,'UTF-8'):null,'reported_at'=>gmdate('Y-m-d H:i:s')],'id=:id',['id'=>$jobId]);
        return true;
    }

    public function touch(int $stationId): void
    {
        $this->db->update('print_agent_stations',['last_seen_at'=>gmdate('Y-m-d H:i:s')],'id=:id',['id'=>$stationId]);
    }

    public function queueShipmentLabel(int $shipmentId,int $stationId,string $printer,float $widthMm,float $heightMm,string $createdBy,string $apiBase): string
    {
        $station=$this->requireStation($stationId,true);
        $printers=json_decode((string)$station['printers_json'],true);
        $printers=is_array($printers)?$printers:[];
        $printer=trim($printer);
        if ($printer==='' || !in_array($printer,$printers,true)) { throw new InvalidArgumentException('Wybierz drukarkę zgłoszoną przez aktywnego agenta.'); }
        if (!$this->db->fetchColumn('SELECT id FROM om_shipments WHERE id=:id',['id'=>$shipmentId])) { throw new InvalidArgumentException('Nie znaleziono przesyłki.'); }
        $widthMm=$this->dimension($widthMm); $heightMm=$this->dimension($heightMm);
        $id=$this->uuid();
        $pdfUrl=substr($apiBase,-4)==='.php'
            ? $apiBase.'?route='.rawurlencode('jobs/'.$id.'/pdf')
            : rtrim($apiBase,'/').'/jobs/'.rawurlencode($id).'/pdf';
        $this->db->insert('print_agent_jobs',[
            'id'=>$id,'station_id'=>$stationId,'shipment_id'=>$shipmentId,'pdf_url'=>$pdfUrl,
            'download_token_hash'=>str_repeat('0',64),'download_expires_at'=>gmdate('Y-m-d H:i:s'),
            'printer_name'=>$printer,'print_settings'=>'fit,paper='.$this->formatDimension($widthMm).'mm x '.$this->formatDimension($heightMm).'mm','status'=>'queued','status_message'=>'Oczekuje na agenta.',
            'created_by'=>mb_substr($createdBy,0,150,'UTF-8'),'created_at'=>gmdate('Y-m-d H:i:s'),'claimed_at'=>null,'reported_at'=>null,
        ]);
        return $id;
    }

    public function queueOrderLabels(int $orderId,string $scope,int $stationId,string $printer,float $widthMm,float $heightMm,string $createdBy,string $apiBase): array
    {
        if (!in_array($scope,['newest','all'],true)) { throw new InvalidArgumentException('Nieprawidłowy zakres etykiet.'); }
        $cancelled=['CANCELLED','CANCELED','ANULOWANO'];
        $rows=$this->db->fetchAll('SELECT id,state FROM om_shipments WHERE order_id=:order ORDER BY id DESC',['order'=>$orderId]);
        $shipmentIds=[];
        foreach ($rows as $row) {
            if (in_array(strtoupper((string)$row['state']),$cancelled,true)) { continue; }
            $shipmentIds[]=(int)$row['id'];
            if ($scope==='newest') { break; }
        }
        if (!$shipmentIds) { throw new InvalidArgumentException('To zamówienie nie ma aktywnej przesyłki z etykietą.'); }
        $jobs=[];
        foreach ($shipmentIds as $shipmentId) { $jobs[]=$this->queueShipmentLabel($shipmentId,$stationId,$printer,$widthMm,$heightMm,$createdBy,$apiBase); }
        return $jobs;
    }

    public function nextJob(int $stationId): ?array
    {
        for ($attempt=0;$attempt<3;$attempt++) {
            $job=$this->db->transaction(function () use ($stationId) {
                $candidate=$this->db->fetch("SELECT * FROM print_agent_jobs WHERE station_id=:station AND status='queued' ORDER BY created_at,id LIMIT 1",['station'=>$stationId]);
                if (!$candidate) { return null; }
                $downloadToken=$this->newToken();
                $updated=$this->db->query("UPDATE print_agent_jobs SET status='processing',status_message='Agent odebrał zadanie.',claimed_at=:now,download_token_hash=:hash,download_expires_at=:expires WHERE id=:id AND station_id=:station AND status='queued'",['now'=>gmdate('Y-m-d H:i:s'),'hash'=>hash('sha256',$downloadToken),'expires'=>gmdate('Y-m-d H:i:s',time()+900),'id'=>$candidate['id'],'station'=>$stationId])->rowCount();
                if ($updated!==1) { return false; }
                $candidate['download_token']=$downloadToken;
                return $candidate;
            });
            if ($job===null) { return null; }
            if (is_array($job)) { return ['id'=>$job['id'],'pdfUrl'=>$job['pdf_url'].(strpos($job['pdf_url'],'?')===false?'?':'&').'token='.rawurlencode($job['download_token']),'printerName'=>$job['printer_name'],'printSettings'=>$job['print_settings']?:null]; }
        }
        return null;
    }

    public function report(int $stationId,string $jobId,string $status,string $message): bool
    {
        if (!in_array($status,['processing','printed','error','printer_offline'],true)) { throw new InvalidArgumentException('Nieprawidłowy status zadania.'); }
        $job=$this->db->fetch('SELECT status FROM print_agent_jobs WHERE id=:id AND station_id=:station',['id'=>$jobId,'station'=>$stationId]);
        if (!$job) { return false; }
        if (in_array((string)$job['status'],['printed','error','printer_offline'],true) && $job['status']!==$status) { throw new InvalidArgumentException('Zadanie ma już status końcowy.'); }
        $this->db->update('print_agent_jobs',['status'=>$status,'status_message'=>mb_substr(trim($message),0,1000,'UTF-8'),'reported_at'=>gmdate('Y-m-d H:i:s')],'id=:id AND station_id=:station',['id'=>$jobId,'station'=>$stationId]);
        return true;
    }

    public function downloadableJob(string $jobId,string $plainToken): ?array
    {
        if ($plainToken==='') { return null; }
        $job=$this->db->fetch("SELECT * FROM print_agent_jobs WHERE id=:id AND status='processing'",['id'=>$jobId]);
        if (!$job || strtotime((string)$job['download_expires_at'].' UTC')<time() || !hash_equals((string)$job['download_token_hash'],hash('sha256',$plainToken))) { return null; }
        return $job;
    }

    private function requireStation(int $stationId,bool $enabled=false): array
    {
        $station=$this->db->fetch('SELECT * FROM print_agent_stations WHERE id=:id'.($enabled?' AND enabled=1':''),['id'=>$stationId]);
        if (!$station) { throw new InvalidArgumentException($enabled?'Wybierz aktywne stanowisko druku.':'Nie znaleziono stanowiska druku.'); }
        return $station;
    }

    private function saveDiscoveredFiscalPrinters(int $stationId,array $devices,string $environment): int
    {
        $environment=$environment==='production'?'production':'sandbox';
        $count=0;
        foreach (array_slice($devices,0,50) as $device) {
            if (!is_array($device)) { continue; }
            $key=mb_substr(trim((string)($device['deviceKey']??'')),0,190,'UTF-8');
            $name=mb_substr(trim((string)($device['name']??'')),0,150,'UTF-8');
            $host=mb_substr(trim((string)($device['host']??'')),0,255,'UTF-8'); $port=(int)($device['port']??0);
            if ($key==='' || $name==='' || $host==='' || $port<1 || $port>65535) { continue; }
            $serial=mb_substr(trim((string)($device['serialNumber']??'')),0,100,'UTF-8')?:null;
            $existing=$this->db->fetch('SELECT id FROM print_fiscal_printers WHERE station_id=:station AND device_key=:key',['station'=>$stationId,'key'=>$key]);
            $data=['name'=>$name,'host'=>$host,'port'=>$port,'serial_number'=>$serial,'last_seen_at'=>gmdate('Y-m-d H:i:s')];
            if ($existing) { $this->db->update('print_fiscal_printers',$data,'id=:id AND deleted_at IS NULL',['id'=>$existing['id']]); }
            else { $this->db->insert('print_fiscal_printers',$data+['station_id'=>$stationId,'device_key'=>$key,'receipt_series'=>'POS','next_number'=>1,'environment'=>$environment,'enabled'=>1,'created_at'=>gmdate('Y-m-d H:i:s')]); }
            $count++;
        }
        return $count;
    }

    private function fiscalPayment(array $details): array
    {
        $name=mb_substr(trim((string)($details['payment_method']??'Płatność online')),0,20,'UTF-8');
        $lower=mb_strtolower($name,'UTF-8');
        if (!empty($details['cash_on_delivery']) || strpos($lower,'gotów')!==false || strpos($lower,'pobran')!==false) { return [0,$name?:'Gotówka']; }
        if (strpos($lower,'kart')!==false) { return [2,$name?:'Karta']; }
        if (strpos($lower,'bon')!==false) { return [4,$name?:'Bon']; }
        if (strpos($lower,'przelew')!==false) { return [8,$name?:'Przelew']; }
        return [6,$name?:'Płatność online'];
    }

    /** Prefiks t{id}. wskazuje firmę agentowi API (patrz Tenant::idFromToken); autoryzuje wyłącznie hash całego tokenu. */
    private function newToken(): string { return Tenant::tokenPrefix().rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'='); }

    private function dimension(float $value): float
    {
        if (!is_finite($value) || $value<30 || $value>500) { throw new InvalidArgumentException('Wymiar etykiety musi mieścić się między 30 a 500 mm.'); }
        return round($value,1);
    }

    private function formatDimension(float $value): string
    {
        return rtrim(rtrim(number_format($value,1,'.',''),'0'),'.');
    }

    private function uuid(): string
    {
        $bytes=random_bytes(16); $bytes[6]=chr((ord($bytes[6])&0x0f)|0x40); $bytes[8]=chr((ord($bytes[8])&0x3f)|0x80);
        $hex=bin2hex($bytes);
        return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
    }
}
