<?php

declare(strict_types=1);
namespace App\Services;

use App\Models\OrderRepository;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use RuntimeException;

/**
 * KSeF integration for order documents: KSeF accounts (company NIP, sandbox / production, tokens) assigned to document series,
 * FA(3) XML built from immutable document snapshots, sending, status, UPO.
 */
final class KsefService
{
    public const NS='http://crd.gov.pl/wzor/2025/06/25/13775/';
    public const LABELS=['sandbox'=>'Sandbox (środowisko testowe)','production'=>'Produkcja'];
    private const RATE_FIELDS=['23'=>['P_13_1','P_14_1'],'8'=>['P_13_2','P_14_2'],'5'=>['P_13_3','P_14_3'],'0'=>['P_13_6_1',null],'zw'=>['P_13_7',null],'np'=>['P_13_8',null]];
    private const RATE_CODES=['23'=>'23','8'=>'8','5'=>'5','0'=>'0 KR','zw'=>'zw','np'=>'np I'];

    private $repo;
    private $db;
    private $transport;
    private $sleep;

    public function __construct(OrderRepository $repo,?callable $transport=null,?callable $sleep=null)
    {
        $this->repo=$repo; $this->db=$repo->db(); $this->transport=$transport;
        $this->sleep=$sleep?:static function (int $ms): void { usleep($ms*1000); };
    }

    public function ensureSchema(): void
    {
        $sqlite=$this->db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME)==='sqlite';
        $id=$sqlite?'INTEGER PRIMARY KEY AUTOINCREMENT':'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $suffix=$sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';
        $this->db->query("CREATE TABLE IF NOT EXISTS om_ksef_accounts (id $id, name VARCHAR(150) NOT NULL, environment VARCHAR(20) NOT NULL, nip VARCHAR(10) NOT NULL, tokens_json TEXT NOT NULL, exemption_basis VARCHAR(256) NOT NULL DEFAULT '', auto_send INTEGER NOT NULL DEFAULT 0, created_at VARCHAR(30) NOT NULL, updated_at VARCHAR(30) NOT NULL)$suffix");
        $this->db->query("CREATE TABLE IF NOT EXISTS om_ksef_submissions (id $id, document_id BIGINT NOT NULL, ksef_account_id BIGINT NULL, environment VARCHAR(20) NOT NULL, state VARCHAR(20) NOT NULL, session_reference VARCHAR(100) NULL, invoice_reference VARCHAR(100) NULL, ksef_number VARCHAR(100) NULL, invoice_hash VARCHAR(100) NULL, status_code INTEGER NULL, message TEXT NULL, xml LONGTEXT NOT NULL, upo LONGTEXT NULL, actor VARCHAR(150) NOT NULL, created_at VARCHAR(30) NOT NULL, updated_at VARCHAR(30) NOT NULL)$suffix");
        $columns=$sqlite?array_column($this->db->fetchAll('PRAGMA table_info(om_ksef_submissions)'),'name'):array_column($this->db->fetchAll('SHOW COLUMNS FROM om_ksef_submissions'),'Field');
        if (!in_array('ksef_account_id',$columns,true)) {
            try { $this->db->query('ALTER TABLE om_ksef_submissions ADD COLUMN ksef_account_id BIGINT NULL'); }
            catch (\PDOException $e) { if ((int)($e->errorInfo[1]??0)!==1060) { throw $e; } }
        }
        if ($sqlite) { $this->db->query('CREATE INDEX IF NOT EXISTS om_ksef_document ON om_ksef_submissions (document_id,id)'); }
        elseif (!$this->db->fetch("SHOW INDEX FROM om_ksef_submissions WHERE Key_name='om_ksef_document'")) {
            try { $this->db->query('CREATE INDEX om_ksef_document ON om_ksef_submissions (document_id,id)'); }
            catch (\PDOException $e) { if ((int)($e->errorInfo[1]??0)!==1061) { throw $e; } }
        }
        $this->migrateLegacySettings();
    }

    /** The first version kept one global KSeF configuration; it becomes an account assigned to all invoice series. */
    private function migrateLegacySettings(): void
    {
        $legacy=$this->repo->setting('ksef');
        if (empty($legacy['nip'])) { return; }
        $this->db->transaction(function () use ($legacy) {
            if ((int)$this->db->fetchColumn('SELECT COUNT(*) FROM om_ksef_accounts')) { return; }
            $now=gmdate('Y-m-d H:i:s');
            $accountId=(int)$this->db->insert('om_ksef_accounts',['name'=>'Konto KSeF '.$legacy['nip'],'environment'=>($legacy['environment']??'')==='production'?'production':'sandbox','nip'=>(string)$legacy['nip'],'tokens_json'=>OrderRepository::json(['sandbox'=>(string)($legacy['tokens']['sandbox']??''),'production'=>(string)($legacy['tokens']['production']??'')]),'exemption_basis'=>(string)($legacy['exemption_basis']??''),'auto_send'=>empty($legacy['auto_send'])?0:1,'created_at'=>$now,'updated_at'=>$now]);
            foreach ($this->db->fetchAll("SELECT id,document_settings_json FROM om_series WHERE kind IN ('invoice','invoice_correction')") as $series) {
                $settings=json_decode((string)($series['document_settings_json']??''),true)?:[];
                if (empty($settings['ksef_account_id'])) { $settings['ksef_account_id']=$accountId; $this->db->update('om_series',['document_settings_json'=>OrderRepository::json($settings)],'id=:id',['id'=>$series['id']]); }
            }
            $this->db->query('UPDATE om_ksef_submissions SET ksef_account_id=:a WHERE ksef_account_id IS NULL',['a'=>$accountId]);
        });
        $this->repo->saveSetting('ksef',[]);
    }

    /** Accounts safe to render: tokens are never exposed. */
    public function accounts(): array
    {
        $assigned=[];
        foreach ($this->db->fetchAll("SELECT id,name,document_settings_json FROM om_series WHERE kind IN ('invoice','invoice_correction') ORDER BY id") as $series) {
            $accountId=(int)((json_decode((string)($series['document_settings_json']??''),true)?:[])['ksef_account_id']??0);
            if ($accountId>0) { $assigned[$accountId][]=(string)$series['name']; }
        }
        $result=[];
        foreach ($this->db->fetchAll('SELECT * FROM om_ksef_accounts ORDER BY name,id') as $row) {
            $row=$this->decodeAccount($row);
            $tokenSet=['sandbox'=>$row['tokens']['sandbox']!=='','production'=>$row['tokens']['production']!==''];
            $result[]=['id'=>(int)$row['id'],'name'=>(string)$row['name'],'environment'=>(string)$row['environment'],'environment_label'=>self::LABELS[$row['environment']]??$row['environment'],'nip'=>(string)$row['nip'],'auto_send'=>(bool)$row['auto_send'],'exemption_basis'=>(string)$row['exemption_basis'],'token_set'=>$tokenSet,'ready'=>$tokenSet[$row['environment']]??false,'series'=>$assigned[(int)$row['id']]??[]];
        }
        return $result;
    }

    /** Creates (id 0) or updates a KSeF account; returns its id. */
    public function saveAccount(int $id,array $input,string $actor): int
    {
        $current=$id>0?$this->accountRow($id):null;
        if ($id>0 && !$current) { throw new InvalidArgumentException('Nie znaleziono konta KSeF.'); }
        $name=trim((string)($input['name']??''));
        if ($name==='' || mb_strlen($name)>150) { throw new InvalidArgumentException('Podaj nazwę konta KSeF (maks. 150 znaków), np. nazwę firmy.'); }
        $environment=(string)($input['environment']??'');
        if (!isset(KsefClient::ENVIRONMENTS[$environment])) { throw new InvalidArgumentException('Wybierz tryb KSeF: sandbox albo produkcja.'); }
        if ($environment==='production' && ($current['environment']??'')!=='production' && empty($input['production_confirm'])) {
            throw new InvalidArgumentException('Aby włączyć tryb produkcyjny dla konta „'.$name.'”, zaznacz potwierdzenie — faktury wysłane na produkcję mają skutki podatkowe.');
        }
        $nip=self::normalizeNip((string)($input['nip']??''));
        if (!self::validNip($nip)) { throw new InvalidArgumentException('Podaj prawidłowy 10-cyfrowy NIP firmy (kontekst KSeF).'); }
        $basis=trim((string)($input['exemption_basis']??''));
        if (mb_strlen($basis)>256) { throw new InvalidArgumentException('Podstawa zwolnienia z VAT może mieć maks. 256 znaków.'); }
        $tokens=$current['tokens']??['sandbox'=>'','production'=>''];
        foreach (array_keys(KsefClient::ENVIRONMENTS) as $env) {
            $token=trim((string)($input['token_'.$env]??''));
            if (!empty($input['token_'.$env.'_remove'])) { $tokens[$env]=''; }
            elseif ($token!=='') {
                if (strlen($token)>1000 || preg_match('/\s/',$token)) { throw new InvalidArgumentException('Token KSeF ('.self::LABELS[$env].') ma nieprawidłowy format.'); }
                $tokens[$env]=OrderSecretBox::encrypt(['token'=>$token]);
            }
            if ($current && ($tokens[$env]!==$current['tokens'][$env] || $nip!==$current['nip'])) { $this->repo->saveSetting('ksef_access_'.$id.'_'.$env,[]); }
        }
        $now=gmdate('Y-m-d H:i:s');
        $data=['name'=>$name,'environment'=>$environment,'nip'=>$nip,'tokens_json'=>OrderRepository::json($tokens),'exemption_basis'=>$basis,'auto_send'=>empty($input['auto_send'])?0:1,'updated_at'=>$now];
        if ($current) { $this->db->update('om_ksef_accounts',$data,'id=:id',['id'=>$id]); return $id; }
        return (int)$this->db->insert('om_ksef_accounts',$data+['created_at'=>$now]);
    }

    public function deleteAccount(int $id): string
    {
        $account=$this->accountRow($id);
        if (!$account) { throw new InvalidArgumentException('Nie znaleziono konta KSeF.'); }
        foreach ($this->accounts() as $view) {
            if ($view['id']===$id && $view['series']) { throw new InvalidArgumentException('Konto „'.$account['name'].'” jest przypisane do serii: '.implode(', ',$view['series']).'. Zmień przypisanie w zakładce Dokumenty.'); }
        }
        if ($this->db->fetchColumn("SELECT id FROM om_ksef_submissions WHERE ksef_account_id=:a AND state='processing' LIMIT 1",['a'=>$id])) { throw new InvalidArgumentException('Konto ma faktury w trakcie przetwarzania w KSeF — odśwież ich status przed usunięciem.'); }
        $this->db->delete('om_ksef_accounts','id=:id',['id'=>$id]);
        foreach (array_keys(KsefClient::ENVIRONMENTS) as $env) { $this->repo->saveSetting('ksef_access_'.$id.'_'.$env,[]); }
        return (string)$account['name'];
    }

    public function testConnection(int $accountId): string
    {
        $account=$this->accountRow($accountId);
        if (!$account) { throw new InvalidArgumentException('Nie znaleziono konta KSeF.'); }
        $access=$this->access($account,(string)$account['environment'],true);
        $until=strtotime((string)$access['valid_until']);
        return 'Połączenie z KSeF działa — '.$account['name'].' ('.self::LABELS[$account['environment']].'). Token dostępowy ważny do '.($until?date('H:i',$until):'—').'.';
    }

    public static function normalizeNip(string $nip): string
    {
        $nip=preg_replace('/^\s*PL/i','',trim($nip))??'';
        return preg_replace('/\D/','',$nip)??'';
    }

    public static function validNip(string $nip): bool
    {
        if (!preg_match('/^[1-9]((\d[1-9])|([1-9]\d))\d{7}$/D',$nip)) { return false; }
        $sum=0; foreach ([6,5,7,2,3,4,5,6,7] as $i=>$weight) { $sum+=$weight*(int)$nip[$i]; }
        return $sum%11===(int)$nip[9];
    }

    /** Sends the document to the active environment and waits briefly for KSeF processing. */
    /** Sends the document with the KSeF account of its series and waits briefly for KSeF processing. */
    public function send(int $documentId,string $actor): array
    {
        $document=$this->document($documentId);
        $account=$this->accountForDocument($document);
        if (!$account) { throw new InvalidArgumentException('Seria dokumentu '.$document['number'].' nie ma przypisanego konta KSeF — ustaw je w serii (zakładka Dokumenty).'); }
        $env=(string)$account['environment']; $label=$account['name'].' · '.self::LABELS[$env];
        $sellerNip=self::normalizeNip((string)($document['snapshot']['seller']['nip']??''));
        if ($sellerNip!==$account['nip']) { throw new InvalidArgumentException('NIP sprzedawcy na dokumencie '.$document['number'].' ('.($sellerNip?:'brak').') różni się od NIP konta KSeF „'.$account['name'].'” ('.$account['nip'].'). Sprawdź przypisanie konta do serii.'); }
        $latest=$this->latestFor($documentId,$env);
        if ($latest && $latest['state']==='accepted') { throw new InvalidArgumentException('Dokument '.$document['number'].' ma już numer KSeF '.$latest['ksef_number'].' ('.self::LABELS[$env].').'); }
        if ($latest && $latest['state']==='processing') { throw new InvalidArgumentException('Dokument '.$document['number'].' jest przetwarzany w KSeF. Odśwież status zamiast wysyłać ponownie.'); }
        $xml=$this->xml($document,$env,$account);
        $now=gmdate('Y-m-d H:i:s');
        $submissionId=(int)$this->db->insert('om_ksef_submissions',['document_id'=>$documentId,'ksef_account_id'=>(int)$account['id'],'environment'=>$env,'state'=>'sending','xml'=>$xml,'invoice_hash'=>base64_encode(hash('sha256',$xml,true)),'actor'=>mb_substr($actor,0,150),'created_at'=>$now,'updated_at'=>$now]);
        $client=$this->client($env); $invoiceSent=false;
        try {
            $keys=$this->keys($client);
            $access=$this->access($account,$env);
            try { $session=$client->openSession($access['token'],$keys); }
            catch (KsefAuthException $e) { $access=$this->access($account,$env,true); $session=$client->openSession($access['token'],$keys); }
            $this->db->update('om_ksef_submissions',['session_reference'=>$session['reference'],'updated_at'=>gmdate('Y-m-d H:i:s')],'id=:id',['id'=>$submissionId]);
            try {
                $sent=$client->sendInvoice($access['token'],$session,$xml);
                $invoiceSent=true;
                $this->db->update('om_ksef_submissions',['state'=>'processing','invoice_reference'=>$sent['reference'],'invoice_hash'=>$sent['hash'],'updated_at'=>gmdate('Y-m-d H:i:s')],'id=:id',['id'=>$submissionId]);
                $status=[];
                for ($attempt=0;$attempt<6;$attempt++) {
                    ($this->sleep)($attempt===0?1500:2000);
                    $status=$client->invoiceStatus($access['token'],$session['reference'],$sent['reference']);
                    if ((int)($status['status']['code']??0)>=200) { break; }
                }
            } finally {
                try { $client->closeSession($access['token'],$session['reference']); } catch (\Throwable $ignored) { }
            }
        } catch (\Throwable $e) {
            $message=mb_substr($e->getMessage(),0,900,'UTF-8');
            if ($invoiceSent) {
                $this->db->update('om_ksef_submissions',['message'=>'Faktura wysłana, ale nie pobrano statusu: '.$message,'updated_at'=>gmdate('Y-m-d H:i:s')],'id=:id',['id'=>$submissionId]);
                $this->repo->event((int)$document['order_id'],'Wysłano '.$document['number'].' do KSeF ('.$label.'); status do odświeżenia.',$actor);
                return $this->submission($submissionId);
            }
            $this->db->update('om_ksef_submissions',['state'=>'error','message'=>$message,'updated_at'=>gmdate('Y-m-d H:i:s')],'id=:id',['id'=>$submissionId]);
            $this->repo->event((int)$document['order_id'],'Błąd wysyłki '.$document['number'].' do KSeF ('.$label.'): '.$message,$actor);
            throw new InvalidArgumentException('Nie wysłano '.$document['number'].' do KSeF: '.$message,0,$e);
        }
        return $this->applyStatus($submissionId,$status,$document,$actor,true);
    }

    /** Re-reads status of the latest submission (with the account used to send it) and downloads UPO once accepted. */
    public function refresh(int $documentId,string $actor): array
    {
        $document=$this->document($documentId);
        $latest=$this->db->fetch('SELECT * FROM om_ksef_submissions WHERE document_id=:d ORDER BY id DESC LIMIT 1',['d'=>$documentId]);
        if (!$latest || empty($latest['invoice_reference'])) { throw new InvalidArgumentException('Dokument '.$document['number'].' nie został jeszcze przyjęty do przetwarzania w KSeF.'); }
        $account=$this->submissionAccount($latest);
        $client=$this->client((string)$latest['environment']);
        $status=$this->authorized($account,(string)$latest['environment'],static function (string $token) use ($client,$latest) { return $client->invoiceStatus($token,(string)$latest['session_reference'],(string)$latest['invoice_reference']); });
        $submission=$this->applyStatus((int)$latest['id'],$status,$document,$actor);
        if ($submission['state']==='accepted' && $submission['upo']===null) {
            try { $this->fetchUpo($submission); $submission=$this->submission((int)$submission['id']); }
            catch (\Throwable $e) { $submission['upo_error']=$e->getMessage(); }
        }
        return $submission;
    }

    /** UPO XML of the accepted submission; downloaded on first use. */
    public function upo(int $documentId): array
    {
        $document=$this->document($documentId);
        $row=$this->db->fetch("SELECT * FROM om_ksef_submissions WHERE document_id=:d AND state='accepted' ORDER BY id DESC LIMIT 1",['d'=>$documentId]);
        if (!$row) { throw new InvalidArgumentException('Brak UPO — dokument nie został przyjęty w KSeF.'); }
        $upo=$row['upo']!==null?(string)$row['upo']:$this->fetchUpo($row);
        return ['name'=>'UPO_'.preg_replace('/[^A-Za-z0-9_-]+/','_',(string)$document['number']).'.xml','xml'=>$upo];
    }

    /** XML preview for the account assigned to the document series (no network). */
    public function preview(int $documentId): array
    {
        $document=$this->document($documentId);
        $account=$this->accountForDocument($document);
        return ['name'=>'FA3_'.preg_replace('/[^A-Za-z0-9_-]+/','_',(string)$document['number']).'.xml','xml'=>$this->xml($document,$account?(string)$account['environment']:'sandbox',$account)];
    }

    public function latest(array $documentIds): array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$documentIds))));
        if (!$ids) { return []; }
        $result=[];
        foreach ($this->db->fetchAll('SELECT id,document_id,environment,state,ksef_number,status_code,message,updated_at,CASE WHEN upo IS NULL THEN 0 ELSE 1 END AS has_upo FROM om_ksef_submissions WHERE document_id IN ('.implode(',',$ids).') ORDER BY id') as $row) {
            $row['environment_label']=self::LABELS[$row['environment']]??$row['environment'];
            $result[(int)$row['document_id']]=$row;
        }
        return $result;
    }

    /** Documents registered (or being registered) in production KSeF must not change locally. */
    public function lockReason(int $documentId): ?string
    {
        $row=$this->db->fetch("SELECT ksef_number,state FROM om_ksef_submissions WHERE document_id=:d AND environment='production' AND state IN ('accepted','processing') ORDER BY id DESC LIMIT 1",['d'=>$documentId]);
        if (!$row) { return null; }
        return $row['state']==='accepted'
            ? 'Dokument ma numer KSeF '.$row['ksef_number'].' — nie można go zmienić ani usunąć. Wystaw korektę.'
            : 'Dokument jest przetwarzany w produkcyjnym KSeF — nie można go zmienić ani usunąć.';
    }

    /** Sends an invoice right after issuing when auto-send is on. Never throws; returns a message for the order history. */
    /** Sends an invoice right after issuing when the series account has auto-send on. Never throws; returns a message for the order history. */
    public function autoSend(int $documentId,string $actor): ?string
    {
        try {
            $document=$this->db->fetch('SELECT id,kind,series_id,parent_id,order_id FROM om_documents WHERE id=:id',['id'=>$documentId]);
            if (!$document || !in_array($document['kind'],['invoice','invoice_correction'],true)) { return null; }
            $account=$this->accountForDocument($document);
            if (!$account || empty($account['auto_send']) || $account['tokens'][$account['environment']]==='') { return null; }
            $attempts=(int)$this->db->fetchColumn('SELECT COUNT(*) FROM om_ksef_submissions WHERE document_id=:d',['d'=>$documentId]);
            try { return self::describe($this->send($documentId,$actor)); }
            catch (\Throwable $e) {
                $message='KSeF: '.mb_substr($e->getMessage(),0,300,'UTF-8');
                // send() records its own history once a submission exists; pre-flight failures (e.g. invalid data) are logged here.
                if ((int)$this->db->fetchColumn('SELECT COUNT(*) FROM om_ksef_submissions WHERE document_id=:d',['d'=>$documentId])===$attempts) {
                    $this->repo->event((int)$document['order_id'],'Automatyczna wysyłka do KSeF pominięta — '.$message,$actor);
                }
                return $message;
            }
        } catch (\Throwable $e) {
            return 'KSeF: '.mb_substr($e->getMessage(),0,300,'UTF-8');
        }
    }

    /** KSeF account (safe view) that would be used for each invoice document id. */
    public function targets(array $documentIds): array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$documentIds))));
        if (!$ids) { return []; }
        $result=[];
        foreach ($this->db->fetchAll("SELECT id,series_id,parent_id FROM om_documents WHERE kind IN ('invoice','invoice_correction') AND id IN (".implode(',',$ids).')') as $document) {
            $account=$this->accountForDocument($document);
            $result[(int)$document['id']]=$account?['id'=>(int)$account['id'],'name'=>(string)$account['name'],'environment'=>(string)$account['environment'],'ready'=>$account['tokens'][$account['environment']]!=='']:null;
        }
        return $result;
    }

    /** Account of the document series; corrections without their own assignment inherit it from the corrected invoice. */
    private function accountForDocument(array $document): ?array
    {
        $current=$document; $guard=0;
        while ($current && $guard++<100) {
            $settings=json_decode((string)$this->db->fetchColumn('SELECT document_settings_json FROM om_series WHERE id=:id',['id'=>(int)$current['series_id']]),true)?:[];
            if ((int)($settings['ksef_account_id']??0)>0) { return $this->accountRow((int)$settings['ksef_account_id']); }
            if (empty($current['parent_id'])) { break; }
            $current=$this->db->fetch('SELECT id,series_id,parent_id FROM om_documents WHERE id=:id',['id'=>(int)$current['parent_id']]);
        }
        return null;
    }

    public static function describe(array $submission): string
    {
        $label=self::LABELS[$submission['environment']]??$submission['environment'];
        switch ($submission['state']) {
            case 'accepted': return 'KSeF ('.$label.'): przyjęto, numer '.$submission['ksef_number'].'.';
            case 'processing': return 'KSeF ('.$label.'): faktura w przetwarzaniu — odśwież status za chwilę.';
            case 'rejected': return 'KSeF ('.$label.'): odrzucono — '.$submission['message'];
            default: return 'KSeF ('.$label.'): '.$submission['message'];
        }
    }

    /** Buyer block of a document: first line = name, "NIP: …" line, 2-letter country line, remaining lines = address. */
    public static function parseBuyer(string $buyer): array
    {
        $lines=[];
        foreach (preg_split('/\R/u',$buyer)?:[] as $line) {
            $line=trim(preg_replace('/\s+/u',' ',$line)??'');
            if ($line!=='' && !in_array($line,$lines,true)) { $lines[]=$line; }
        }
        $name=$lines[0]??''; $nip=null; $country='PL'; $rest=[];
        foreach (array_slice($lines,1) as $line) {
            if ($nip===null && preg_match('/^(?:NIP|VAT(?: ?ID)?|Tax ?ID)?\s*:?\s*(?:PL)?\s*(\d[\d \-]{8,16}\d)$/iu',$line,$match)) {
                $candidate=preg_replace('/\D/','',$match[1])??'';
                if (self::validNip($candidate)) { $nip=$candidate; continue; }
            }
            if (preg_match('/^[A-Z]{2}$/D',$line)) { $country=$line; continue; }
            $rest[]=$line;
        }
        if ($nip!==null) {
            foreach ($rest as $index=>$line) {
                if (preg_match('/(sp\.|spółka|s\.a\.|s\.c\.|z o\.o|firma|p\.h\.u|f\.h\.u|zakład|przedsiębiorstwo|\bltd\b|\bgmbh\b|\binc\b)/iu',$line)) { $name=$line; unset($rest[$index]); break; }
            }
        }
        $rest=array_values($rest); $address=[];
        for ($i=0;$i<count($rest);$i++) {
            if (preg_match('/^\d{2}-\d{3}$/D',$rest[$i]) && isset($rest[$i+1])) { $address[]=$rest[$i].' '.$rest[$i+1]; $i++; }
            else { $address[]=$rest[$i]; }
        }
        return ['name'=>$name,'nip'=>$nip,'country'=>$country,'address'=>$address];
    }

    /**
     * FA(3) XML for an invoice or invoice correction snapshot.
     * Options: corrected => [number, issue_date, ksef_number|null], exemption_basis, generated_at (DateTimeImmutable).
     */
    public static function buildInvoiceXml(array $document,array $snapshot,array $options=[]): string
    {
        $kind=(string)($document['kind']??'');
        if (!in_array($kind,['invoice','invoice_correction'],true)) { throw new InvalidArgumentException('Do KSeF wysyłane są tylko faktury i korekty faktur.'); }
        $correction=$kind==='invoice_correction';
        $currency=(string)($snapshot['currency']??'PLN');
        if ($currency!=='PLN') { throw new InvalidArgumentException('Wysyłka do KSeF obsługuje obecnie faktury w PLN (waluta obca wymaga kursu i kwot VAT w PLN).'); }
        $seller=(array)($snapshot['seller']??[]);
        $sellerNip=self::normalizeNip((string)($seller['nip']??''));
        if (!self::validNip($sellerNip)) { throw new InvalidArgumentException('NIP sprzedawcy na dokumencie jest nieprawidłowy — popraw dane sprzedawcy.'); }
        $buyer=self::parseBuyer((string)($snapshot['buyer']??''));
        if ($buyer['name']==='') { throw new InvalidArgumentException('Dokument nie ma nazwy nabywcy.'); }
        $items=(array)($snapshot['items']??[]);
        $before=$correction?(array)($snapshot['before']['items']??[]):[];
        if (!$items && !$before) { throw new InvalidArgumentException('Dokument nie ma pozycji.'); }
        foreach (['issue_date','sale_date'] as $field) {
            if (!empty($snapshot[$field]) && !preg_match('/^\d{4}-\d{2}-\d{2}$/D',(string)$snapshot[$field])) { throw new InvalidArgumentException('Nieprawidłowa data dokumentu.'); }
        }
        if (empty($snapshot['issue_date'])) { throw new InvalidArgumentException('Dokument nie ma daty wystawienia.'); }

        $text=static function ($value,int $max=256): string { return mb_substr(trim(preg_replace('/\s+/u',' ',(string)$value)??''),0,$max,'UTF-8'); };
        $amount=static function (int $cents): string { return ($cents<0?'-':'').intdiv(abs($cents),100).'.'.str_pad((string)(abs($cents)%100),2,'0',STR_PAD_LEFT); };
        $dom=new DOMDocument('1.0','UTF-8'); $dom->formatOutput=true;
        $add=static function (DOMElement $parent,string $name,?string $value=null) use ($dom): DOMElement {
            $element=$dom->createElementNS(self::NS,$name);
            if ($value!==null) { $element->appendChild($dom->createTextNode($value)); }
            $parent->appendChild($element);
            return $element;
        };
        $addressLines=static function (array $lines) use ($text): array {
            $lines=array_values(array_filter(array_map($text,$lines),'strlen'));
            return [$lines[0]??'',$lines?implode(', ',array_slice($lines,1)):''];
        };
        $root=$dom->createElementNS(self::NS,'Faktura'); $dom->appendChild($root);

        $header=$add($root,'Naglowek');
        $formCode=$add($header,'KodFormularza','FA'); $formCode->setAttribute('kodSystemowy','FA (3)'); $formCode->setAttribute('wersjaSchemy','1-0E');
        $add($header,'WariantFormularza','3');
        $generated=($options['generated_at']??new \DateTimeImmutable('now'))->setTimezone(new \DateTimeZone('UTC'));
        $add($header,'DataWytworzeniaFa',$generated->format('Y-m-d\TH:i:s\Z'));
        $add($header,'SystemInfo','SalesCenter');

        $podmiot1=$add($root,'Podmiot1');
        $ids=$add($podmiot1,'DaneIdentyfikacyjne'); $add($ids,'NIP',$sellerNip); $add($ids,'Nazwa',$text($seller['name']??'',512));
        [$sellerL1,$sellerL2]=$addressLines(preg_split('/\R/u',(string)($seller['address']??''))?:[]);
        if ($sellerL1==='') { throw new InvalidArgumentException('Uzupełnij adres sprzedawcy.'); }
        $address=$add($podmiot1,'Adres'); $add($address,'KodKraju','PL'); $add($address,'AdresL1',mb_substr($sellerL1,0,512)); if ($sellerL2!=='') { $add($address,'AdresL2',mb_substr($sellerL2,0,512)); }

        $podmiot2=$add($root,'Podmiot2');
        $ids=$add($podmiot2,'DaneIdentyfikacyjne');
        if ($buyer['nip']!==null) { $add($ids,'NIP',$buyer['nip']); } else { $add($ids,'BrakID','1'); }
        $add($ids,'Nazwa',$text($buyer['name'],512));
        [$buyerL1,$buyerL2]=$addressLines($buyer['address']);
        if ($buyerL1!=='') { $address=$add($podmiot2,'Adres'); $add($address,'KodKraju',$buyer['country']); $add($address,'AdresL1',mb_substr($buyerL1,0,512)); if ($buyerL2!=='') { $add($address,'AdresL2',mb_substr($buyerL2,0,512)); } }
        $add($podmiot2,'JST','2'); $add($podmiot2,'GV','2');

        $fa=$add($root,'Fa');
        $add($fa,'KodWaluty',$currency);
        $add($fa,'P_1',(string)$snapshot['issue_date']);
        $add($fa,'P_2',$text($document['number']??''));
        if (!empty($snapshot['sale_date'])) { $add($fa,'P_6',(string)$snapshot['sale_date']); }
        $totals=[]; $hasExempt=false;
        foreach ([[$items,1],[$before,-1]] as [$rows,$sign]) {
            foreach ($rows as $row) {
                $vat=(string)($row['vat']??'');
                if (!isset(self::RATE_FIELDS[$vat])) { throw new InvalidArgumentException('Nieobsługiwana stawka VAT: '.$vat); }
                if ($vat==='zw') { $hasExempt=true; }
                $totals[$vat]['net']=($totals[$vat]['net']??0)+$sign*(int)($row['net_cents']??0);
                $totals[$vat]['tax']=($totals[$vat]['tax']??0)+$sign*(int)($row['tax_cents']??0);
            }
        }
        foreach (self::RATE_FIELDS as $vat=>[$netField,$taxField]) {
            if (!isset($totals[$vat])) { continue; }
            $add($fa,$netField,$amount($totals[$vat]['net']));
            if ($taxField!==null) { $add($fa,$taxField,$amount($totals[$vat]['tax'])); }
        }
        $add($fa,'P_15',$amount($correction?(int)($snapshot['difference_cents']??0):(int)($snapshot['gross_cents']??0)));

        $notes=$add($fa,'Adnotacje');
        $add($notes,'P_16','2'); $add($notes,'P_17','2'); $add($notes,'P_18','2'); $add($notes,'P_18A',!empty($snapshot['split_payment'])?'1':'2');
        $exemption=$add($notes,'Zwolnienie');
        if ($hasExempt) {
            $basis=$text($options['exemption_basis']??'');
            if ($basis==='') { throw new InvalidArgumentException('Dokument zawiera pozycje „zw” — uzupełnij podstawę zwolnienia z VAT w ustawieniach KSeF.'); }
            $add($exemption,'P_19','1'); $add($exemption,'P_19A',$basis);
        } else { $add($exemption,'P_19N','1'); }
        $add($add($notes,'NoweSrodkiTransportu'),'P_22N','1');
        $add($notes,'P_23','2');
        $add($add($notes,'PMarzy'),'P_PMarzyN','1');
        $add($fa,'RodzajFaktury',$correction?'KOR':'VAT');
        if ($correction) {
            $corrected=(array)($options['corrected']??[]);
            if (empty($corrected['number']) || empty($corrected['issue_date'])) { throw new InvalidArgumentException('Brak danych faktury korygowanej.'); }
            $reason=$text($snapshot['reason']??'');
            if ($reason!=='') { $add($fa,'PrzyczynaKorekty',$reason); }
            $data=$add($fa,'DaneFaKorygowanej');
            $add($data,'DataWystFaKorygowanej',(string)$corrected['issue_date']);
            $add($data,'NrFaKorygowanej',$text($corrected['number']));
            if (!empty($corrected['ksef_number'])) { $add($data,'NrKSeF','1'); $add($data,'NrKSeFFaKorygowanej',(string)$corrected['ksef_number']); }
            else { $add($data,'NrKSeFN','1'); }
        }
        $orderNumber=$text($snapshot['order_number']??'');
        if ($orderNumber!=='') { $description=$add($fa,'DodatkowyOpis'); $add($description,'Klucz','Numer zamówienia'); $add($description,'Wartosc',$orderNumber); }
        $line=0;
        foreach ([[$before,true],[$items,false]] as [$rows,$stateBefore]) {
            foreach ($rows as $row) {
                $faRow=$add($fa,'FaWiersz');
                $add($faRow,'NrWierszaFa',(string)++$line);
                $add($faRow,'P_7',$text($row['name']??'',512));
                $add($faRow,'P_8A','szt.');
                $add($faRow,'P_8B',(string)(int)($row['quantity']??0));
                $add($faRow,'P_9B',$amount((int)($row['unit_cents']??0)));
                $add($faRow,'P_11A',$amount((int)($row['gross_cents']??0)));
                $add($faRow,'P_12',self::RATE_CODES[(string)$row['vat']]);
                if ($stateBefore) { $add($faRow,'StanPrzed','1'); }
            }
        }
        if (!$correction) {
            $payment=$dom->createElementNS(self::NS,'Platnosc');
            $gross=(int)($snapshot['gross_cents']??0);
            if ($gross>0 && (int)($snapshot['amount_paid_cents']??0)>=$gross) {
                $add($payment,'Zaplacono','1'); $add($payment,'DataZaplaty',(string)($snapshot['sale_date']??$snapshot['issue_date']));
            } elseif (!empty($snapshot['payment_due_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/D',(string)$snapshot['payment_due_date'])) {
                $add($add($payment,'TerminPlatnosci'),'Termin',(string)$snapshot['payment_due_date']);
            }
            $method=$text($snapshot['payment_method']??'');
            if ($method!=='') {
                $form=self::paymentForm($method);
                if ($form!==null) { $add($payment,'FormaPlatnosci',(string)$form); }
                else { $add($payment,'PlatnoscInna','1'); $add($payment,'OpisPlatnosci',$method); }
            }
            $bank=strtoupper(preg_replace('/\s+/','',(string)($seller['bank']??''))??'');
            if (preg_match('/^(?:[A-Z]{2})?\d{10,32}$/D',$bank)) { $add($add($payment,'RachunekBankowy'),'NrRB',$bank); }
            if ($payment->hasChildNodes()) { $fa->appendChild($payment); }
        }
        $footer=$text($snapshot['series_notes']??'',3500);
        if ($footer!=='') { $add($add($add($root,'Stopka'),'Informacje'),'StopkaFaktury',$footer); }
        $xml=(string)$dom->saveXML();
        self::validateXml($xml);
        return $xml;
    }

    public static function validateXml(string $xml): void
    {
        $previous=libxml_use_internal_errors(true); libxml_clear_errors();
        try {
            $dom=new DOMDocument();
            $valid=$dom->loadXML($xml,LIBXML_NONET) && $dom->schemaValidate(dirname(__DIR__).'/Support/ksef/schemat_FA3_v1-0E.xsd',LIBXML_NONET);
            if (!$valid) {
                $messages=array_map(static function ($error) { return trim($error->message); },array_slice(libxml_get_errors(),0,3));
                throw new InvalidArgumentException('Dokument nie spełnia schematu KSeF FA(3): '.mb_substr(implode(' | ',$messages),0,600,'UTF-8'));
            }
        } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    }

    private static function paymentForm(string $method): ?int
    {
        $method=mb_strtolower($method,'UTF-8');
        foreach ([1=>['pobran','gotów','gotow','przy odbiorze','cash'],2=>['kart','card'],7=>['blik','mobil','google pay','apple pay'],6=>['przelew','transfer','payu','przelewy24','p24','tpay','paynow','allegro pay','bank']] as $form=>$needles) {
            foreach ($needles as $needle) { if (strpos($method,$needle)!==false) { return $form; } }
        }
        return null;
    }

    private function document(int $documentId): array
    {
        $document=$this->db->fetch('SELECT * FROM om_documents WHERE id=:id',['id'=>$documentId]);
        if (!$document) { throw new InvalidArgumentException('Nie znaleziono dokumentu.'); }
        if (!in_array($document['kind'],['invoice','invoice_correction'],true)) { throw new InvalidArgumentException('Do KSeF wysyłane są tylko faktury i korekty faktur — paragony obsługuje drukarka fiskalna.'); }
        $document['snapshot']=json_decode((string)$document['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
        return $document;
    }

    private function xml(array $document,string $env,?array $account): string
    {
        $options=['exemption_basis'=>(string)($account['exemption_basis']??'')];
        if ($document['kind']==='invoice_correction') {
            $root=$document; $guard=0;
            while (!empty($root['parent_id']) && $guard++<100) {
                $parent=$this->db->fetch('SELECT * FROM om_documents WHERE id=:id',['id'=>(int)$root['parent_id']]);
                if (!$parent) { break; }
                $root=$parent;
            }
            if ((int)$root['id']===(int)$document['id']) { throw new InvalidArgumentException('Korekta nie wskazuje faktury korygowanej.'); }
            $rootSnapshot=json_decode((string)$root['snapshot_json'],true)?:[];
            $options['corrected']=['number'=>(string)$root['number'],'issue_date'=>(string)($rootSnapshot['issue_date']??substr((string)$root['created_at'],0,10)),'ksef_number'=>(string)($this->db->fetchColumn("SELECT ksef_number FROM om_ksef_submissions WHERE document_id=:d AND environment=:e AND state='accepted' ORDER BY id DESC LIMIT 1",['d'=>(int)$root['id'],'e'=>$env])?:'')];
        }
        return self::buildInvoiceXml($document,$document['snapshot'],$options);
    }

    private function applyStatus(int $submissionId,array $status,array $document,string $actor,bool $justSent=false): array
    {
        $code=(int)($status['status']['code']??0);
        $state=$code===200?'accepted':($code>=300?'rejected':'processing');
        $message=$code?KsefClient::statusText((array)$status['status']):'Oczekiwanie na status KSeF.';
        $previous=$this->submission($submissionId);
        $this->db->update('om_ksef_submissions',['state'=>$state,'status_code'=>$code?:null,'ksef_number'=>!empty($status['ksefNumber'])?(string)$status['ksefNumber']:$previous['ksef_number'],'message'=>$message,'updated_at'=>gmdate('Y-m-d H:i:s')],'id=:id',['id'=>$submissionId]);
        $submission=$this->submission($submissionId);
        if ($state!==$previous['state'] && $state!=='processing') {
            $this->repo->event((int)$document['order_id'],$document['number'].' · '.self::describe($submission),$actor);
        } elseif ($justSent) {
            $this->repo->event((int)$document['order_id'],'Wysłano '.$document['number'].' do KSeF ('.self::LABELS[$submission['environment']].').',$actor);
        }
        return $submission;
    }

    private function submission(int $id): array
    {
        $row=$this->db->fetch('SELECT * FROM om_ksef_submissions WHERE id=:id',['id'=>$id]);
        if (!$row) { throw new RuntimeException('Nie znaleziono wysyłki KSeF.'); }
        return $row;
    }

    private function latestFor(int $documentId,string $env): ?array
    {
        return $this->db->fetch('SELECT * FROM om_ksef_submissions WHERE document_id=:d AND environment=:e ORDER BY id DESC LIMIT 1',['d'=>$documentId,'e'=>$env])?:null;
    }

    private function fetchUpo(array $submission): string
    {
        $account=$this->submissionAccount($submission);
        $client=$this->client((string)$submission['environment']);
        $upo=$this->authorized($account,(string)$submission['environment'],static function (string $token) use ($client,$submission) { return $client->upo($token,(string)$submission['session_reference'],(string)$submission['invoice_reference']); });
        $this->db->update('om_ksef_submissions',['upo'=>$upo,'updated_at'=>gmdate('Y-m-d H:i:s')],'id=:id',['id'=>(int)$submission['id']]);
        return $upo;
    }

    private function submissionAccount(array $submission): array
    {
        $account=$this->accountRow((int)($submission['ksef_account_id']??0));
        if (!$account) { throw new InvalidArgumentException('Konto KSeF użyte do tej wysyłki zostało usunięte — dodaj je ponownie, aby pobrać status lub UPO.'); }
        return $account;
    }

    private function accountRow(int $id): ?array
    {
        $row=$id>0?$this->db->fetch('SELECT * FROM om_ksef_accounts WHERE id=:id',['id'=>$id]):null;
        return $row?$this->decodeAccount($row):null;
    }

    private function decodeAccount(array $row): array
    {
        $tokens=json_decode((string)($row['tokens_json']??''),true)?:[];
        $row['tokens']=['sandbox'=>(string)($tokens['sandbox']??''),'production'=>(string)($tokens['production']??'')];
        $row['auto_send']=(bool)$row['auto_send'];
        return $row;
    }

    private function client(string $env): KsefClient
    {
        return new KsefClient($env,$this->transport,$this->sleep);
    }

    /** Read-only call with one re-authentication when the cached access token was revoked. */
    private function authorized(array $account,string $env,callable $call)
    {
        try { return $call($this->access($account,$env)['token']); }
        catch (KsefAuthException $e) { return $call($this->access($account,$env,true)['token']); }
    }


    private function keys(KsefClient $client): array
    {
        $cacheKey='ksef_keys_'.$client->environment();
        $cached=$this->repo->setting($cacheKey);
        if (!empty($cached['keys']) && (int)($cached['fetched_at']??0)>time()-43200) { return $cached['keys']; }
        $keys=$client->publicKeys();
        $this->repo->saveSetting($cacheKey,['keys'=>$keys,'fetched_at'=>time()]);
        return $keys;
    }

    private function access(array $account,string $env,bool $force=false): array
    {
        if (($account['tokens'][$env]??'')==='') { throw new InvalidArgumentException('Konto KSeF „'.$account['name'].'” nie ma tokena dla trybu: '.self::LABELS[$env].'.'); }
        $token=(string)(OrderSecretBox::decrypt($account['tokens'][$env])['token']??'');
        $fingerprint=hash('sha256',$account['nip'].'|'.$token);
        $cacheKey='ksef_access_'.(int)$account['id'].'_'.$env;
        if (!$force) {
            $cached=$this->repo->setting($cacheKey);
            if (!empty($cached['secret'])) {
                try {
                    $access=OrderSecretBox::decrypt((string)$cached['secret']);
                    if (($access['fingerprint']??'')===$fingerprint && (strtotime((string)($access['valid_until']??''))?:0)>time()+120) { return $access; }
                } catch (\Throwable $ignored) { }
            }
        }
        $client=$this->client($env);
        $access=$client->authenticate((string)$account['nip'],$token,$this->keys($client))+['fingerprint'=>$fingerprint];
        $this->repo->saveSetting($cacheKey,['secret'=>OrderSecretBox::encrypt($access)]);
        return $access;
    }
}
