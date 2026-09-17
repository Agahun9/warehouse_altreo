<?php

declare(strict_types=1);
namespace App\Models;

use App\Core\Database;
use App\Services\OrderAutomationService;
use App\Services\OrderNormalizer;
use App\Services\OrderSyncError;
use InvalidArgumentException;
use RuntimeException;

final class OrderRepository
{
    private $db;
    private $automation;
    private $pendingAutomation = [];
    private $suppressAutomation = false;
    public function __construct(Database $db) { $this->db = $db; }
    public function db(): Database { return $this->db; }
    public static function json(array $value): string { return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); }
    public function ensureSchema(): void
    {
        $sqlite = $this->db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $id = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $suffix = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';
        $tables = [
            'om_statuses' => "id $id, name VARCHAR(100) NOT NULL, color VARCHAR(7) NOT NULL, position INTEGER NOT NULL DEFAULT 0, group_name VARCHAR(100) NOT NULL DEFAULT 'Pozostałe'",
            'om_accounts' => "id $id, platform VARCHAR(20) NOT NULL, source_id INTEGER NOT NULL, name VARCHAR(150) NOT NULL, enabled INTEGER NOT NULL DEFAULT 0, last_sync VARCHAR(30) NULL, last_error TEXT NULL, synced_until VARCHAR(30) NULL, next_attempt BIGINT NOT NULL DEFAULT 0, cursor_json TEXT NULL, UNIQUE(platform, source_id)",
            'om_orders' => "id $id, account_id BIGINT NOT NULL, external_id VARCHAR(190) NOT NULL, remote_status VARCHAR(100) NOT NULL, status_id BIGINT NOT NULL, status_manual INTEGER NOT NULL DEFAULT 0, ordered_at VARCHAR(30) NOT NULL, buyer_name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(80) NOT NULL, total_cents BIGINT NOT NULL, currency VARCHAR(3) NOT NULL, paid INTEGER NOT NULL DEFAULT 0, details_json LONGTEXT NOT NULL, note TEXT NULL, tags VARCHAR(1000) NOT NULL DEFAULT '', imported_at VARCHAR(30) NOT NULL, updated_at VARCHAR(30) NOT NULL, UNIQUE(account_id, external_id)",
            'om_mappings' => "id $id, account_id BIGINT NOT NULL, remote_status VARCHAR(100) NOT NULL, status_id BIGINT NOT NULL, UNIQUE(account_id, remote_status)",
            'om_events' => "id $id, order_id BIGINT NOT NULL, actor VARCHAR(150) NOT NULL, message TEXT NOT NULL, created_at VARCHAR(30) NOT NULL",
            'om_rules' => "id $id, name VARCHAR(150) NOT NULL, enabled INTEGER NOT NULL DEFAULT 1, trigger_name VARCHAR(30) NOT NULL, conditions_json TEXT NOT NULL, actions_json TEXT NOT NULL",
            'om_rule_runs' => "id $id, rule_id BIGINT NOT NULL, order_id BIGINT NOT NULL, event_key VARCHAR(80) NOT NULL, created_at VARCHAR(30) NOT NULL, UNIQUE(rule_id, order_id, event_key)",
            'om_settings' => "setting_key VARCHAR(100) PRIMARY KEY, value_json LONGTEXT NOT NULL",
            'om_series' => "id $id, name VARCHAR(100) NOT NULL, kind VARCHAR(30) NOT NULL, pattern VARCHAR(100) NOT NULL, next_number INTEGER NOT NULL DEFAULT 1, fiscal_printer_id BIGINT NULL, numbering_json TEXT NULL, numbering_period VARCHAR(7) NULL, document_settings_json TEXT NULL",
            'om_documents' => "id $id, order_id BIGINT NOT NULL, series_id BIGINT NOT NULL, kind VARCHAR(30) NOT NULL, number VARCHAR(190) NOT NULL UNIQUE, parent_id BIGINT NULL, request_key VARCHAR(80) NOT NULL UNIQUE, snapshot_json LONGTEXT NOT NULL, created_at VARCHAR(30) NOT NULL",
            'om_shipments' => "id $id, order_id BIGINT NOT NULL, carrier VARCHAR(60) NOT NULL, tracking VARCHAR(100) NOT NULL, weight VARCHAR(30) NOT NULL, state VARCHAR(30) NOT NULL, created_at VARCHAR(30) NOT NULL, UNIQUE(order_id, carrier, tracking)",
            'om_carrier_accounts' => "id $id, provider VARCHAR(30) NOT NULL, name VARCHAR(150) NOT NULL, enabled INTEGER NOT NULL DEFAULT 0, public_config_json TEXT NOT NULL, secret_config_json LONGTEXT NOT NULL, updated_at VARCHAR(30) NOT NULL, UNIQUE(provider, name)",
            'om_payment_methods' => "id $id, name VARCHAR(100) NOT NULL UNIQUE, is_cod INTEGER NOT NULL DEFAULT 0, position INTEGER NOT NULL DEFAULT 0, enabled INTEGER NOT NULL DEFAULT 1",
            'om_payment_mappings' => "id $id, platform VARCHAR(20) NOT NULL, source_method VARCHAR(190) NOT NULL, payment_method_id BIGINT NOT NULL, UNIQUE(platform, source_method)",
        ];
        foreach ($tables as $name => $columns) { $this->db->query("CREATE TABLE IF NOT EXISTS $name ($columns)$suffix"); }
        $statusColumns=$sqlite ? array_column($this->db->fetchAll('PRAGMA table_info(om_statuses)'),'name') : array_column($this->db->fetchAll('SHOW COLUMNS FROM om_statuses'),'Field');
        if (!in_array('group_name',$statusColumns,true)) {
            try { $this->db->query("ALTER TABLE om_statuses ADD COLUMN group_name VARCHAR(100) NOT NULL DEFAULT 'Pozostałe'"); }
            catch (\PDOException $e) { if ((int)($e->errorInfo[1]??0)!==1060) { throw $e; } }
            foreach ([1=>'Do realizacji',2=>'Do realizacji',3=>'Do realizacji',4=>'W drodze',5=>'Zamknięte',6=>'Zamknięte'] as $statusId=>$groupName) {
                $this->db->update('om_statuses',['group_name'=>$groupName],'id=:id',['id'=>$statusId]);
            }
        }
        // Upgrade installations created before incremental-sync columns were introduced.
        $accountColumns=$sqlite ? array_column($this->db->fetchAll('PRAGMA table_info(om_accounts)'),'name') : array_column($this->db->fetchAll('SHOW COLUMNS FROM om_accounts'),'Field');
        foreach (['synced_until'=>'VARCHAR(30) NULL','next_attempt'=>'BIGINT NOT NULL DEFAULT 0','cursor_json'=>'TEXT NULL','auto_accept'=>'INTEGER NOT NULL DEFAULT 0'] as $column=>$definition) {
            if (!in_array($column,$accountColumns,true)) {
                try { $this->db->query("ALTER TABLE om_accounts ADD COLUMN $column $definition"); }
                catch (\PDOException $e) { if ((int)($e->errorInfo[1]??0)!==1060) { throw $e; } }
            }
        }
        $seriesColumns=$sqlite ? array_column($this->db->fetchAll('PRAGMA table_info(om_series)'),'name') : array_column($this->db->fetchAll('SHOW COLUMNS FROM om_series'),'Field');
        if (!in_array('fiscal_printer_id',$seriesColumns,true)) {
            try { $this->db->query('ALTER TABLE om_series ADD COLUMN fiscal_printer_id BIGINT NULL'); }
            catch (\PDOException $e) { if ((int)($e->errorInfo[1]??0)!==1060) { throw $e; } }
        }
        foreach (['numbering_json'=>'TEXT NULL','numbering_period'=>'VARCHAR(7) NULL','document_settings_json'=>'TEXT NULL'] as $column=>$definition) {
            if (!in_array($column,$seriesColumns,true)) {
                try { $this->db->query("ALTER TABLE om_series ADD COLUMN $column $definition"); }
                catch (\PDOException $e) { if ((int)($e->errorInfo[1]??0)!==1060) { throw $e; } }
            }
        }
        foreach ([
            'om_orders'=>['status_changed_at'=>'VARCHAR(30) NULL'],
            'om_rules'=>['triggers_json'=>'TEXT NULL','options_json'=>'TEXT NULL','position'=>'INTEGER NOT NULL DEFAULT 0','updated_at'=>'VARCHAR(30) NULL'],
            'om_rule_runs'=>['trigger_name'=>'VARCHAR(30) NULL','result'=>'VARCHAR(20) NULL','message'=>'TEXT NULL'],
        ] as $table=>$columns) {
            $existing=$sqlite ? array_column($this->db->fetchAll("PRAGMA table_info($table)"),'name') : array_column($this->db->fetchAll("SHOW COLUMNS FROM $table"),'Field');
            foreach ($columns as $column=>$definition) {
                if (in_array($column,$existing,true)) { continue; }
                try { $this->db->query("ALTER TABLE $table ADD COLUMN $column $definition"); }
                catch (\PDOException $e) { if ((int)($e->errorInfo[1]??0)!==1060) { throw $e; } }
            }
        }
        $shipmentColumns=$sqlite ? array_column($this->db->fetchAll('PRAGMA table_info(om_shipments)'),'name') : array_column($this->db->fetchAll('SHOW COLUMNS FROM om_shipments'),'Field');
        foreach (['carrier_account_id'=>'BIGINT NULL','external_id'=>'VARCHAR(190) NULL','command_id'=>'VARCHAR(190) NULL','payload_json'=>'LONGTEXT NULL','cod_amount_cents'=>'BIGINT NULL','shipment_currency'=>'VARCHAR(3) NULL'] as $column=>$definition) {
            if (!in_array($column,$shipmentColumns,true)) {
                try { $this->db->query("ALTER TABLE om_shipments ADD COLUMN $column $definition"); }
                catch (\PDOException $e) { if ((int)($e->errorInfo[1]??0)!==1060) { throw $e; } }
            }
        }
        foreach ([
            'om_orders' => ['om_order_date'=>'ordered_at,id','om_order_status'=>'status_id,ordered_at','om_order_account'=>'account_id,ordered_at'],
            'om_events' => ['om_event_order'=>'order_id,id'],
            'om_documents' => ['om_document_order'=>'order_id,id','om_document_parent'=>'parent_id,id'],
            'om_shipments' => ['om_shipment_order'=>'order_id,id'],
            'om_rule_runs' => ['om_rule_run_order'=>'order_id,id','om_rule_run_date'=>'created_at'],
        ] as $table=>$indexes) {
            foreach ($indexes as $name=>$columns) {
                if ($sqlite) { $this->db->query("CREATE INDEX IF NOT EXISTS $name ON $table ($columns)"); }
                elseif (!$this->db->fetch("SHOW INDEX FROM $table WHERE Key_name=:name",['name'=>$name])) {
                    try { $this->db->query("CREATE INDEX $name ON $table ($columns)"); }
                    catch (\PDOException $e) { if ((int)($e->errorInfo[1]??0)!==1061) { throw $e; } }
                }
            }
        }
        if (!(int) $this->db->fetchColumn('SELECT COUNT(*) FROM om_statuses')) {
            // Fixed IDs and ignore make concurrent first requests safe.
            foreach ([1=>['Nowe','#6366f1'],2=>['Do spakowania','#f59e0b'],3=>['Gotowe do wysyłki','#06b6d4'],4=>['Wysłane','#10b981'],5=>['Zakończone','#64748b'],6=>['Anulowane','#ef4444']] as $key=>$status) {
                $groupName=$key<=3?'Do realizacji':($key===4?'W drodze':'Zamknięte');
                $this->insertIgnore('om_statuses', ['id'=>$key,'name'=>$status[0],'color'=>$status[1],'position'=>$key,'group_name'=>$groupName]);
            }
        }
        foreach ([1=>['Przelew',0],2=>['Płatność przy odbiorze',1]] as $key=>$method) {
            $this->insertIgnore('om_payment_methods',['id'=>$key,'name'=>$method[0],'is_cod'=>$method[1],'position'=>$key,'enabled'=>1]);
        }
    }
    private function insertIgnore(string $table, array $data): void
    {
        $verb = $this->db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
        $this->db->query($verb.' INTO '.$table.' ('.implode(',',array_keys($data)).') VALUES (:'.implode(',:',array_keys($data)).')', $data);
    }
    public function setting(string $key): array
    {
        $v = $this->db->fetchColumn('SELECT value_json FROM om_settings WHERE setting_key=:k',['k'=>$key]);
        return $v ? json_decode($v,true,512,JSON_THROW_ON_ERROR) : [];
    }
    public function saveSetting(string $key,array $value): void
    {
        $this->db->transaction(function () use ($key,$value) {
            $this->db->delete('om_settings','setting_key=:k',['k'=>$key]);
            $this->db->insert('om_settings',['setting_key'=>$key,'value_json'=>self::json($value)]);
        });
    }
    public function accounts(): array { return $this->db->fetchAll('SELECT * FROM om_accounts ORDER BY platform,name'); }
    public function carrierAccounts(): array
    {
        return $this->db->fetchAll('SELECT id,provider,name,enabled,public_config_json,updated_at FROM om_carrier_accounts ORDER BY provider,name');
    }
    public function registerAccount(string $platform, int $sourceId, string $name): void
    {
        $this->insertIgnore('om_accounts',['platform'=>$platform,'source_id'=>$sourceId,'name'=>$name,'enabled'=>0]);
        $this->db->update('om_accounts',['name'=>$name],'platform=:p AND source_id=:s',['p'=>$platform,'s'=>$sourceId]);
    }
    public function statuses(): array { return $this->db->fetchAll('SELECT * FROM om_statuses ORDER BY position,id'); }
    public function paymentMethods(bool $enabledOnly=false): array
    {
        return $this->db->fetchAll('SELECT * FROM om_payment_methods'.($enabledOnly?' WHERE enabled=1':'').' ORDER BY position,id');
    }
    public function savePaymentMethod(int $id,string $name,bool $isCod,int $position): void
    {
        $name=mb_substr(trim($name),0,100);
        if ($name==='') { throw new InvalidArgumentException('Podaj nazwę własnej metody płatności.'); }
        if ($this->db->fetchColumn('SELECT id FROM om_payment_methods WHERE name=:name AND id<>:id',['name'=>$name,'id'=>$id])) { throw new InvalidArgumentException('Taka własna metoda płatności już istnieje.'); }
        $data=['name'=>$name,'is_cod'=>$isCod?1:0,'position'=>$position,'enabled'=>1];
        if ($id>0) {
            if (!$this->db->fetchColumn('SELECT id FROM om_payment_methods WHERE id=:id',['id'=>$id])) { throw new InvalidArgumentException('Nieznana metoda płatności.'); }
            $this->db->update('om_payment_methods',$data,'id=:id',['id'=>$id]);
        } else { $id=(int)$this->db->insert('om_payment_methods',$data); }
        foreach ($this->db->fetchAll('SELECT platform,source_method FROM om_payment_mappings WHERE payment_method_id=:id',['id'=>$id]) as $mapping) {
            $this->refreshPaymentMapping((string)$mapping['platform'],(string)$mapping['source_method']);
        }
    }
    public function savePaymentMapping(string $platform,string $sourceMethod,int $paymentMethodId): int
    {
        $platform=mb_substr(trim($platform),0,20);
        $sourceMethod=mb_substr(trim($sourceMethod),0,190);
        if (!in_array($platform,['allegro','erli','empik','mediamarkt','morele','altreo'],true)) { throw new InvalidArgumentException('Nieznane źródło zamówień.'); }
        if (!$this->db->fetchColumn('SELECT id FROM om_payment_methods WHERE id=:id AND enabled=1',['id'=>$paymentMethodId])) { throw new InvalidArgumentException('Wybierz aktywną własną metodę płatności.'); }
        $this->db->transaction(function () use ($platform,$sourceMethod,$paymentMethodId) {
            $this->db->delete('om_payment_mappings','platform=:platform AND source_method=:source',['platform'=>$platform,'source'=>$sourceMethod]);
            $this->db->insert('om_payment_mappings',['platform'=>$platform,'source_method'=>$sourceMethod,'payment_method_id'=>$paymentMethodId]);
        });
        return $this->refreshPaymentMapping($platform,$sourceMethod);
    }
    public function removePaymentMapping(int $id): void
    {
        $this->db->delete('om_payment_mappings','id=:id',['id'=>$id]);
    }
    public function paymentSources(): array
    {
        $mappings=[];
        foreach ($this->db->fetchAll('SELECT pm.id mapping_id,pm.platform,pm.source_method,pm.payment_method_id,m.name payment_method_name,m.is_cod FROM om_payment_mappings pm JOIN om_payment_methods m ON m.id=pm.payment_method_id') as $mapping) {
            $mappings[$mapping['platform']."\0".$mapping['source_method']]=$mapping;
        }
        $sources=[];
        foreach ($this->db->fetchAll("SELECT a.platform,o.details_json FROM om_orders o JOIN om_accounts a ON a.id=o.account_id WHERE a.platform<>'manual'") as $row) {
            try { $details=json_decode((string)$row['details_json'],true,512,JSON_THROW_ON_ERROR); }
            catch (\Throwable $error) { continue; }
            $raw=is_array($details['raw']??null)?$details['raw']:[];
            $source=array_key_exists('source_payment_method',$details)
                ? mb_substr(trim((string)$details['source_payment_method']),0,190)
                : OrderNormalizer::sourcePaymentMethod((string)$row['platform'],$raw,(string)($details['delivery']??''));
            $key=$row['platform']."\0".$source;
            if (!isset($sources[$key])) { $sources[$key]=['platform'=>$row['platform'],'source_method'=>$source,'orders_count'=>0,'mapping_id'=>null,'payment_method_id'=>null,'payment_method_name'=>'','is_cod'=>null]; }
            $sources[$key]['orders_count']++;
            if (isset($mappings[$key])) { $sources[$key]=array_replace($sources[$key],$mappings[$key]); }
        }
        foreach ($mappings as $key=>$mapping) {
            if (!isset($sources[$key])) { $sources[$key]=array_replace(['orders_count'=>0],$mapping); }
        }
        $sources=array_values($sources);
        usort($sources,static function (array $a,array $b): int { return [$a['platform'],$a['source_method']]<=>[$b['platform'],$b['source_method']]; });
        return $sources;
    }
    private function mappedPayment(string $platform,string $sourceMethod): ?array
    {
        $row=$this->db->fetch('SELECT m.name,m.is_cod FROM om_payment_mappings pm JOIN om_payment_methods m ON m.id=pm.payment_method_id AND m.enabled=1 WHERE pm.platform=:platform AND pm.source_method=:source',['platform'=>$platform,'source'=>$sourceMethod]);
        return $row?:null;
    }
    private function applyPaymentMapping(int $accountId,array &$order,array &$details): void
    {
        $platform=(string)$this->db->fetchColumn('SELECT platform FROM om_accounts WHERE id=:id',['id'=>$accountId]);
        if ($platform==='' || $platform==='manual') { return; }
        $raw=is_array($details['raw']??null)?$details['raw']:[];
        $source=array_key_exists('source_payment_method',$details)
            ? mb_substr(trim((string)$details['source_payment_method']),0,190)
            : OrderNormalizer::sourcePaymentMethod($platform,$raw,(string)($details['delivery']??''));
        $details['source_payment_method']=$source;
        $mapping=$this->mappedPayment($platform,$source);
        if (!$mapping) { return; }
        $details['payment_method']=(string)$mapping['name'];
        $details['cash_on_delivery']=(int)(bool)$mapping['is_cod'];
        if ($details['cash_on_delivery']) { $order['paid']=0; $details['amount_paid_cents']=0; }
    }
    private function refreshPaymentMapping(string $platform,string $sourceMethod): int
    {
        $mapping=$this->mappedPayment($platform,$sourceMethod);
        if (!$mapping) { return 0; }
        $updated=0;
        foreach ($this->db->fetchAll('SELECT o.id,o.details_json FROM om_orders o JOIN om_accounts a ON a.id=o.account_id WHERE a.platform=:platform',['platform'=>$platform]) as $row) {
            try { $details=json_decode((string)$row['details_json'],true,512,JSON_THROW_ON_ERROR); }
            catch (\Throwable $error) { continue; }
            if (!empty($details['_manual'])) { continue; }
            $raw=is_array($details['raw']??null)?$details['raw']:[];
            $source=array_key_exists('source_payment_method',$details)
                ? mb_substr(trim((string)$details['source_payment_method']),0,190)
                : OrderNormalizer::sourcePaymentMethod($platform,$raw,(string)($details['delivery']??''));
            if ($source!==$sourceMethod) { continue; }
            $details['source_payment_method']=$source;
            $details['payment_method']=(string)$mapping['name'];
            $details['cash_on_delivery']=(int)(bool)$mapping['is_cod'];
            $data=['details_json'=>self::json($details),'updated_at'=>gmdate('Y-m-d H:i:s')];
            if ($details['cash_on_delivery']) { $data['paid']=0; $details['amount_paid_cents']=0; $data['details_json']=self::json($details); }
            $this->db->update('om_orders',$data,'id=:id',['id'=>$row['id']]);
            $updated++;
        }
        return $updated;
    }
    public function createManualOrder(array $input,string $actor): int
    {
        $this->requireStatus((int)($input['status_id']??0));
        $orderedAt=trim((string)($input['ordered_at']??''));
        $timestamp=strtotime($orderedAt);
        if ($orderedAt==='' || $timestamp===false) { throw new InvalidArgumentException('Podaj prawidłową datę zamówienia.'); }
        $total=OrderNormalizer::money((string)($input['shipping_price']??'0'));
        foreach ((array)($input['items']??[]) as $item) {
            if (!is_array($item)) { continue; }
            $quantity=filter_var($item['quantity']??null,FILTER_VALIDATE_INT);
            if ($quantity!==false && $quantity>0) { $total+=OrderNormalizer::money((string)($item['price']??'0'))*$quantity; }
        }
        $input['total']=number_format($total/100,2,'.','');
        if (stripos((string)($input['payment_method']??''),'pobrani')!==false) { $input['cash_on_delivery']='1'; }
        if (!empty($input['cash_on_delivery'])) { unset($input['paid']); $input['amount_paid']='0.00'; }
        $this->registerAccount('manual',0,'Zamówienia własne');
        $accountId=(int)$this->db->fetchColumn('SELECT id FROM om_accounts WHERE platform=:p AND source_id=0',['p'=>'manual']);
        $externalId='WLASNE-'.gmdate('Ymd-His').'-'.strtoupper(bin2hex(random_bytes(3)));
        $now=gmdate('Y-m-d H:i:s');
        $details=['items'=>[['name'=>'Pozycja','sku'=>'','quantity'=>1,'unit_cents'=>0,'vat'=>'23']],'address'=>[],'invoice_address'=>[],'delivery'=>'','pickup'=>'','shipping_cents'=>0,'payment_method'=>'','cash_on_delivery'=>0,'amount_paid_cents'=>0,'buyer_note'=>'','source'=>'manual'];
        $id=(int)$this->db->insert('om_orders',['account_id'=>$accountId,'external_id'=>$externalId,'remote_status'=>'Własne','status_id'=>(int)$input['status_id'],'status_manual'=>1,'ordered_at'=>gmdate('Y-m-d H:i:s',$timestamp),'buyer_name'=>'Nowy klient','email'=>'','phone'=>'','total_cents'=>0,'currency'=>'PLN','paid'=>0,'details_json'=>self::json($details),'note'=>'','tags'=>'własne','imported_at'=>$now,'updated_at'=>$now,'status_changed_at'=>$now]);
        try {
            $this->suppressAutomation=true;
            try { $this->updateOrderDetails($id,$input,$actor); }
            finally { $this->suppressAutomation=false; }
            $this->event($id,'Utworzono ręcznie nowe zamówienie.',$actor);
        } catch (\Throwable $error) {
            $this->db->delete('om_orders','id=:id',['id'=>$id]);
            throw $error;
        }
        $this->automationEvent($id,'order_created');
        if ((int)$this->db->fetchColumn('SELECT paid FROM om_orders WHERE id=:id',['id'=>$id])) { $this->automationEvent($id,'paid',['payment_source'=>'user']); }
        return $id;
    }
    private function rowLock(): string
    {
        return $this->db->pdo()->inTransaction() && $this->db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'sqlite' ? ' FOR UPDATE' : '';
    }
    public function requireStatus(int $id): void
    {
        if (!$this->db->fetchColumn('SELECT id FROM om_statuses WHERE id=:id',['id'=>$id])) { throw new InvalidArgumentException('Nieznany status.'); }
    }
    public function order(int $id): array
    {
        $row = $this->db->fetch('SELECT o.*,a.platform,a.source_id account_source_id,a.name account_name,s.name status_name,s.color FROM om_orders o JOIN om_accounts a ON a.id=o.account_id JOIN om_statuses s ON s.id=o.status_id WHERE o.id=:id'.$this->rowLock(),['id'=>$id]);
        if (!$row) { throw new InvalidArgumentException('Nie znaleziono zamówienia.'); }
        $row['details'] = json_decode($row['details_json'],true,512,JSON_THROW_ON_ERROR);
        $row['details']['address_lines'] = self::addressLines($row['details']['address'] ?? []);
        $row['details']['invoice_lines'] = self::addressLines($row['details']['invoice_address'] ?? []);
        $paymentText=strtolower((string)($row['details']['payment_method']??'').' '.json_encode($row['details']['raw']??[],JSON_UNESCAPED_UNICODE));
        $row['details']['cash_on_delivery']=isset($row['details']['cash_on_delivery']) ? (int)(bool)$row['details']['cash_on_delivery'] : (int)(strpos($paymentText,'pobran')!==false || strpos($paymentText,'cash_on_delivery')!==false || preg_match('/\bcod\b/',$paymentText));
        $paymentMethod=trim((string)($row['details']['payment_method']??''));
        if ($row['details']['cash_on_delivery'] && in_array(mb_strtolower($paymentMethod,'UTF-8'),['','nieustalona','płatność online'],true)) { $paymentMethod='Płatność przy odbiorze'; }
        $row['details']['payment_method']=$paymentMethod!==''?$paymentMethod:((int)$row['paid']?'Płatność online':'Nieustalona');
        $row['details']['amount_paid_cents']=isset($row['details']['amount_paid_cents']) ? max(0,(int)$row['details']['amount_paid_cents']) : ((int)$row['paid']?(int)$row['total_cents']:0);
        $row['details']['amount_due_cents']=max(0,(int)$row['total_cents']-(int)$row['details']['amount_paid_cents']);
        $rawInvoice=(array)($row['details']['raw']['invoice']??[]);
        $preference=(string)($row['details']['document_preference']??'');
        if (!in_array($preference,['invoice','receipt'],true)) { $preference=!empty($rawInvoice['required'])?'invoice':'receipt'; }
        $row['details']['document_preference']=$preference;
        $row['details']['invoice_required']=$preference==='invoice'?1:0;
        $address=$row['details']['address'] ?? [];
        $street=self::pick($address,['street','line1','address']);
        $building=self::pick($address,['buildingNumber','building_number','line2']);
        [$street,$building]=self::splitStreet($street,$building);
        $row['shipping_address']=[
            'name'=>self::personName($address,$row['buyer_name']),
            'email'=>self::pick($address,['email'],$row['email']),
            'phone'=>self::pick($address,['phoneNumber','phone'],$row['phone']),
            'street'=>$street,
            'building'=>$building,
            'postal_code'=>self::pick($address,['zip','zipCode','postCode','postalCode','postal_code']),
            'city'=>self::pick($address,['city']),
            'country'=>strtoupper(self::pick($address,['country','countryCode','country_code'],'PL')),
            'point'=>(string)($row['details']['pickup']??''),
        ];
        $invoice=(array)($row['details']['invoice_address']??[]);
        $invoiceStreet=self::pick($invoice,['street','line1','address']);
        $invoiceBuilding=self::pick($invoice,['buildingNumber','building_number','line2']);
        [$invoiceStreet,$invoiceBuilding]=self::splitStreet($invoiceStreet,$invoiceBuilding);
        $company=is_array($invoice['company']??null)?$invoice['company']:[];
        $taxId=self::pick($invoice,['nip','taxId','tax_id']);
        if ($taxId==='') { $taxId=self::pick($company,['taxId','tax_id','nip']); }
        $row['details']['invoice_form']=[
            'name'=>self::personName($invoice),
            'company'=>self::pick($invoice,['company_name','companyName'],self::pick($company,['name'])),
            'nip'=>$taxId,
            'street'=>$invoiceStreet,
            'building'=>$invoiceBuilding,
            'postal_code'=>self::pick($invoice,['zip','zipCode','postCode','postalCode','postal_code']),
            'city'=>self::pick($invoice,['city']),
            'country'=>strtoupper(self::pick($invoice,['country','countryCode','country_code'],'PL')),
        ];
        unset($row['details_json']);
        return $row;
    }

    public function updateOrderDetails(int $id,array $input,string $actor): void
    {
        $this->transactional(function () use ($id,$input,$actor) {
            $stored=$this->db->fetch('SELECT * FROM om_orders WHERE id=:id'.$this->rowLock(),['id'=>$id]);
            if (!$stored) { throw new InvalidArgumentException('Nie znaleziono zamówienia.'); }
            $details=json_decode((string)$stored['details_json'],true,512,JSON_THROW_ON_ERROR);
            $text=static function (array $source,string $key,int $limit): string { return mb_substr(trim((string)($source[$key]??'')),0,$limit); };
            $buyerName=$text($input,'buyer_name',255);
            $email=$text($input,'email',255);
            $phone=$text($input,'phone',80);
            if ($buyerName==='') { throw new InvalidArgumentException('Podaj nazwę kupującego.'); }
            if ($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)) { throw new InvalidArgumentException('Nieprawidłowy adres e-mail kupującego.'); }
            $currency=strtoupper($text($input,'currency',3));
            if (!preg_match('/^[A-Z]{3}$/D',$currency)) { throw new InvalidArgumentException('Waluta musi być trzyliterowym kodem, np. PLN.'); }
            $total=OrderNormalizer::money((string)($input['total']??''));
            $shipping=OrderNormalizer::money((string)($input['shipping_price']??'0'));
            $paidAmount=OrderNormalizer::money((string)($input['amount_paid']??'0'));
            $items=[];
            foreach (array_slice((array)($input['items']??[]),0,300) as $index=>$item) {
                if (!is_array($item)) { continue; }
                $name=$text($item,'name',300); if ($name==='') { throw new InvalidArgumentException('Każda pozycja musi mieć nazwę.'); }
                $quantity=filter_var($item['quantity']??null,FILTER_VALIDATE_INT);
                if ($quantity===false || $quantity<0 || $quantity>100000) { throw new InvalidArgumentException('Sprawdź ilość produktów.'); }
                $vat=strtolower($text($item,'vat',3)); if (!in_array($vat,['23','8','5','0','zw','np'],true)) { throw new InvalidArgumentException('Wybierz stawkę VAT każdej pozycji.'); }
                $existing=is_array($details['items'][$index]??null)?$details['items'][$index]:[];
                $items[]=$existing+[];
                $items[array_key_last($items)]['name']=$name;
                $items[array_key_last($items)]['sku']=$text($item,'sku',190);
                $items[array_key_last($items)]['quantity']=$quantity;
                $items[array_key_last($items)]['unit_cents']=OrderNormalizer::money((string)($item['price']??'0'));
                $items[array_key_last($items)]['vat']=$vat;
            }
            if (!$items) { throw new InvalidArgumentException('Zamówienie musi zawierać przynajmniej jedną pozycję.'); }
            $shippingEmail=$text($input,'shipping_email',255);
            if ($shippingEmail!=='' && !filter_var($shippingEmail,FILTER_VALIDATE_EMAIL)) { throw new InvalidArgumentException('Nieprawidłowy adres e-mail odbiorcy.'); }
            $address=['name'=>$text($input,'shipping_name',255),'email'=>$shippingEmail,'phoneNumber'=>$text($input,'shipping_phone',80),'street'=>$text($input,'shipping_street',150),'buildingNumber'=>$text($input,'shipping_building',30),'zip'=>$text($input,'shipping_postal_code',20),'city'=>$text($input,'shipping_city',100),'country'=>strtoupper($text($input,'shipping_country',2))];
            if (!preg_match('/^[A-Z]{2}$/D',$address['country'])) { throw new InvalidArgumentException('Nieprawidłowy kod kraju dostawy.'); }
            $invoice=['name'=>$text($input,'invoice_name',255),'company_name'=>$text($input,'invoice_company',255),'nip'=>$text($input,'invoice_nip',30),'street'=>$text($input,'invoice_street',150),'buildingNumber'=>$text($input,'invoice_building',30),'zip'=>$text($input,'invoice_postal_code',20),'city'=>$text($input,'invoice_city',100),'country'=>strtoupper($text($input,'invoice_country',2))];
            if ($invoice['country']!=='' && !preg_match('/^[A-Z]{2}$/D',$invoice['country'])) { throw new InvalidArgumentException('Nieprawidłowy kod kraju nabywcy.'); }
            $documentPreference=in_array((string)($input['document_preference']??''),['invoice','receipt'],true)?(string)$input['document_preference']:'receipt';
            $detailOverride=['items'=>$items,'address'=>$address,'invoice_address'=>$invoice,'invoice_required'=>$documentPreference==='invoice'?1:0,'document_preference'=>$documentPreference,'delivery'=>$text($input,'delivery',255),'pickup'=>$text($input,'pickup',190),'shipping_cents'=>$shipping,'payment_method'=>$text($input,'payment_method',150),'cash_on_delivery'=>empty($input['cash_on_delivery'])?0:1,'amount_paid_cents'=>$paidAmount,'buyer_note'=>$text($input,'buyer_note',10000)];
            $orderOverride=['buyer_name'=>$buyerName,'email'=>$email,'phone'=>$phone,'total_cents'=>$total,'currency'=>$currency,'paid'=>empty($input['paid'])?0:1];
            // Only fields the operator actually changed get frozen against future sync; untouched
            // fields (e.g. "paid" left as-is while only fixing the address) keep following the marketplace.
            $previousManual=is_array($details['_manual']??null)?$details['_manual']:[];
            $manualOrder=is_array($previousManual['order']??null)?$previousManual['order']:[];
            $manualDetails=is_array($previousManual['details']??null)?$previousManual['details']:[];
            foreach ($orderOverride as $field=>$value) {
                if ((string)$value!==(string)($stored[$field]??null)) { $manualOrder[$field]=$value; }
            }
            foreach ($detailOverride as $field=>$value) {
                if (json_encode($value,JSON_UNESCAPED_UNICODE)!==json_encode($details[$field]??null,JSON_UNESCAPED_UNICODE)) { $manualDetails[$field]=$value; }
            }
            $details=array_replace_recursive($details,$detailOverride);
            $details['_manual']=['order'=>$manualOrder,'details'=>$manualDetails,'updated_at'=>gmdate('Y-m-d H:i:s'),'actor'=>$actor];
            $this->db->update('om_orders',$orderOverride+['details_json'=>self::json($details),'updated_at'=>gmdate('Y-m-d H:i:s')],'id=:id',['id'=>$id]);
            $this->event($id,'Zmieniono dane klienta, płatności, dostawy, faktury lub pozycji zamówienia.',$actor);
            $this->automationEvent($id,'details_changed');
            if ((int)$stored['paid']!==$orderOverride['paid']) { $this->automationEvent($id,$orderOverride['paid']?'paid':'unpaid',['payment_source'=>'user']); }
        });
    }
    /**
     * Automation edits use the same field-level manual lock as the order form, so a later
     * marketplace sync does not silently revert a status of payment or delivery set by a rule.
     */
    public function patchOrder(int $id,array $orderFields,array $detailFields,string $actor,string $message): bool
    {
        return (bool)$this->transactional(function () use ($id,$orderFields,$detailFields,$actor,$message) {
            $stored=$this->db->fetch('SELECT * FROM om_orders WHERE id=:id'.$this->rowLock(),['id'=>$id]);
            if (!$stored) { throw new InvalidArgumentException('Nie znaleziono zamówienia.'); }
            $details=json_decode((string)$stored['details_json'],true,512,JSON_THROW_ON_ERROR);
            $manual=is_array($details['_manual']??null)?$details['_manual']:[];
            $manualOrder=is_array($manual['order']??null)?$manual['order']:[];
            $manualDetails=is_array($manual['details']??null)?$manual['details']:[];
            $changedOrder=[]; $changed=false;
            foreach ($orderFields as $field=>$value) {
                if (!in_array($field,['paid','buyer_name','email','phone','total_cents','currency'],true) || (string)$stored[$field]===(string)$value) { continue; }
                $changedOrder[$field]=$value; $manualOrder[$field]=$value; $changed=true;
            }
            foreach ($detailFields as $field=>$value) {
                if (json_encode($value,JSON_UNESCAPED_UNICODE)===json_encode($details[$field]??null,JSON_UNESCAPED_UNICODE)) { continue; }
                $details[$field]=$value; $manualDetails[$field]=$value; $changed=true;
            }
            if (!$changed) { return false; }
            $details['_manual']=['order'=>$manualOrder,'details'=>$manualDetails,'updated_at'=>gmdate('Y-m-d H:i:s'),'actor'=>$actor];
            $this->db->update('om_orders',$changedOrder+['details_json'=>self::json($details),'updated_at'=>gmdate('Y-m-d H:i:s')],'id=:id',['id'=>$id]);
            $this->event($id,$message,$actor);
            if (isset($changedOrder['paid'])) { $this->automationEvent($id,$changedOrder['paid']?'paid':'unpaid',['payment_source'=>'automation']); }
            return true;
        });
    }
    public function automation(): OrderAutomationService
    {
        if ($this->automation===null) { $this->automation=new OrderAutomationService($this); }
        return $this->automation;
    }
    /** Events raised inside a transaction wait for commit, so automations never hold row locks. */
    public function automationEvent(int $orderId,string $trigger,array $context=[]): void
    {
        if ($this->suppressAutomation) { return; }
        $this->pendingAutomation[$orderId.'|'.$trigger.'|'.md5(self::json($context))]=[$orderId,$trigger,$context];
        $this->flushAutomations();
    }
    public function flushAutomations(): void
    {
        if (!$this->pendingAutomation || $this->db->pdo()->inTransaction()) { return; }
        $pending=$this->pendingAutomation; $this->pendingAutomation=[];
        foreach ($pending as [$orderId,$trigger,$context]) {
            try { $this->automation()->dispatch((int)$orderId,(string)$trigger,$context); }
            catch (\Throwable $error) { OrderSyncError::log($error,['stage'=>'automation','order_id'=>$orderId,'trigger'=>$trigger]); }
        }
    }
    /** Events of a rolled-back transaction must never run. */
    public function discardAutomations(): void
    {
        if (!$this->db->pdo()->inTransaction()) { $this->pendingAutomation=[]; }
    }
    private function transactional(callable $callback)
    {
        try { $result=$this->db->transaction($callback); }
        catch (\Throwable $error) { $this->discardAutomations(); throw $error; }
        $this->flushAutomations();
        return $result;
    }
    private static function addressLines(array $address): array
    {
        $lines=[];
        foreach ($address as $key=>$value) {
            if (in_array($key,['phone','phoneNumber','email','firstName','lastName','firstname','lastname','type'],true)) { continue; }
            if (is_array($value)) { $lines=array_merge($lines,self::addressLines($value)); }
            elseif (is_scalar($value) && (string)$value!=='') { $lines[]=(string)$value; }
        }
        return array_values(array_unique($lines));
    }
    private static function pick(array $source,array $keys,string $default=''): string
    {
        foreach ($keys as $key) { if (isset($source[$key]) && is_scalar($source[$key]) && trim((string)$source[$key])!=='') { return trim((string)$source[$key]); } }
        return $default;
    }
    private static function personName(array $source,string $default=''): string
    {
        $direct=self::pick($source,['name']);
        if ($direct!=='') { return $direct; }
        $name=trim(self::pick($source,['firstName','firstname','first_name']).' '.self::pick($source,['lastName','lastname','last_name']));
        return $name!==''?$name:$default;
    }
    private static function splitStreet(string $street,string $building): array
    {
        if ($building==='' && preg_match('/^(.+?)\s+([0-9][\pL0-9.\/-]*)$/u',$street,$match)) { return [trim($match[1]),trim($match[2])]; }
        return [$street,$building];
    }
    private static function imageUrl($value): string
    {
        if (is_array($value)) {
            foreach (['image_url','imageUrl','media_url','mediaUrl','thumbnail_url','thumbnailUrl','thumbnail','image','mainImage','primaryImage','images','media','medias','url'] as $key) {
                if (array_key_exists($key,$value)) {
                    $candidate=self::imageUrl($value[$key]);
                    if ($candidate!=='') { return $candidate; }
                }
            }
            foreach (['offer','product'] as $key) {
                if (isset($value[$key])) {
                    $candidate=self::imageUrl($value[$key]);
                    if ($candidate!=='') { return $candidate; }
                }
            }
            foreach ($value as $candidate) {
                if (is_array($candidate)) {
                    $url=self::imageUrl($candidate);
                    if ($url!=='') { return $url; }
                }
            }
            return '';
        }
        if (!is_scalar($value)) { return ''; }
        $candidate=trim(explode('|',(string)$value)[0]);
        if (strpos($candidate,'//')===0) { $candidate='https:'.$candidate; }
        if (preg_match('#^https://[^\s]+$#i',$candidate)) { return $candidate; }
        if (preg_match('#^(?:uploads|img_components|dist)/[a-zA-Z0-9_./ -]+$#D',$candidate) && strpos($candidate,'..')===false) { return $candidate; }
        return '';
    }
    private static function shortDate(string $value): string
    {
        return preg_match('/^\d{2}(\d{2})-(\d{2})-(\d{2})[ T](\d{2}:\d{2})/',$value,$m) ? "$m[3].$m[2].$m[1] $m[4]" : $value;
    }

    private function tableExists(string $table): bool
    {
        try { $this->db->fetch("SELECT 1 FROM $table LIMIT 1"); return true; }
        catch (\Throwable $e) { return false; }
    }

    public function listing(array $filters): array
    {
        $where = ['1=1']; $params = [];
        foreach (['status_id','account_id','paid'] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') { $where[] = "o.$field=:$field"; $params[$field] = (int) $filters[$field]; }
        }
        if (empty($filters['status_id']) && !empty($filters['group']) && mb_strlen((string)$filters['group'])<=100) {
            $where[]='o.status_id IN (SELECT id FROM om_statuses WHERE group_name=:status_group)';
            $params['status_group']=(string)$filters['group'];
        }
        if (trim((string)($filters['q']??''))!=='') {
            $words=array_slice(preg_split('/\s+/u',mb_strtolower(mb_substr(trim((string)$filters['q']),0,200,'UTF-8'),'UTF-8'),-1,PREG_SPLIT_NO_EMPTY),0,8);
            $fiscalJobs=$this->tableExists('print_fiscal_jobs');
            foreach ($words as $w=>$word) {
                $like='%'.str_replace(['!','%','_'],['!!','!%','!_'],$word).'%';
                $parts=[];
                foreach (['external_id','buyer_name','email','phone','tags','note','remote_status','details_json'] as $i=>$field) {
                    $parts[]="LOWER(o.$field) LIKE :q{$w}_$i ESCAPE '!'"; $params["q{$w}_$i"]=$like;
                }
                $parts[]="LOWER(a.name) LIKE :q{$w}_acc ESCAPE '!'"; $params["q{$w}_acc"]=$like;
                $parts[]="EXISTS (SELECT 1 FROM om_shipments sh WHERE sh.order_id=o.id AND (LOWER(sh.tracking) LIKE :q{$w}_st ESCAPE '!' OR LOWER(COALESCE(sh.external_id,'')) LIKE :q{$w}_se ESCAPE '!'))";
                $params["q{$w}_st"]=$like; $params["q{$w}_se"]=$like;
                $parts[]="EXISTS (SELECT 1 FROM om_documents d WHERE d.order_id=o.id AND LOWER(d.number) LIKE :q{$w}_dn ESCAPE '!')";
                $params["q{$w}_dn"]=$like;
                if ($fiscalJobs) {
                    $parts[]="EXISTS (SELECT 1 FROM print_fiscal_jobs fj WHERE fj.order_id=o.id AND (LOWER(fj.local_number) LIKE :q{$w}_fl ESCAPE '!' OR LOWER(COALESCE(fj.fiscal_number,'')) LIKE :q{$w}_ff ESCAPE '!'))";
                    $params["q{$w}_fl"]=$like; $params["q{$w}_ff"]=$like;
                }
                if (ctype_digit($word) && strlen($word)<=18) { $parts[]="o.id=:q{$w}_id"; $params["q{$w}_id"]=(int)$word; }
                $where[]='('.implode(' OR ',$parts).')';
            }
        }
        if (!empty($filters['platform']) && in_array($filters['platform'],['manual','allegro','erli','empik','mediamarkt','morele'],true)) {
            $where[]='a.platform=:platform';
            $params['platform']=$filters['platform'];
        }
        foreach (['date_from'=>'>=','date_to'=>'<'] as $field=>$operator) {
            $value=(string)($filters[$field]??'');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value)) {
                if ($field==='date_to') { $value=gmdate('Y-m-d',strtotime($value.' +1 day')); }
                $where[]="o.ordered_at $operator :$field";
                $params[$field]=$value.' 00:00:00';
            }
        }
        foreach (['amount_from'=>'>=','amount_to'=>'<='] as $field=>$operator) {
            $value=str_replace(',','.',trim((string)($filters[$field]??'')));
            if (preg_match('/^\d+(?:\.\d{1,2})?$/D',$value)) {
                $parts=explode('.',$value);
                $params[$field]=(int)$parts[0]*100+(int)str_pad($parts[1]??'',2,'0');
                $where[]="o.total_cents $operator :$field";
            }
        }
        $where = implode(' AND ',$where);
        $count = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM om_orders o JOIN om_accounts a ON a.id=o.account_id WHERE $where",$params);
        $page = max(1,min((int)($filters['page']??1),max(1,(int)ceil($count/50))));
        $offset = ($page-1)*50;
        $orders=[
            'newest'=>'o.ordered_at DESC,o.id DESC',
            'oldest'=>'o.ordered_at ASC,o.id ASC',
            'amount_desc'=>'o.total_cents DESC,o.id DESC',
            'amount_asc'=>'o.total_cents ASC,o.id DESC',
            'buyer'=>'o.buyer_name ASC,o.id DESC',
        ];
        $orderBy=$orders[(string)($filters['sort']??'newest')]??$orders['newest'];
        $rows = $this->db->fetchAll("SELECT o.id,o.external_id,o.buyer_name,o.email,o.phone,o.total_cents,o.currency,o.paid,o.ordered_at,o.status_changed_at,o.remote_status,o.tags,o.note,o.status_id,o.details_json,a.platform,a.source_id account_source_id,a.name account_name,s.name status_name,s.color FROM om_orders o JOIN om_accounts a ON a.id=o.account_id JOIN om_statuses s ON s.id=o.status_id WHERE $where ORDER BY $orderBy LIMIT 50 OFFSET $offset",$params);
        $skus=[];$allegroOfferIds=[];$erliExternalIds=[];$empikShopSkus=[];$empikProductSkus=[];$empikProductIds=[];$sourceAccounts=[];
        foreach ($rows as &$row) {
            try { $details=json_decode((string)$row['details_json'],true,512,JSON_THROW_ON_ERROR); }
            catch (\Throwable $error) { $details=[]; }
            $items=is_array($details['items']??null)?$details['items']:[];
            $raw=is_array($details['raw']??null)?$details['raw']:[];
            $rawItems=$row['platform']==='allegro'?($raw['lineItems']??[]):($row['platform']==='erli'?($raw['items']??[]):($raw['order_lines']??[]));
            foreach ($items as $index=>&$item) {
                $rawItem=is_array($rawItems[$index]??null)?$rawItems[$index]:[];
                if ($row['platform']==='allegro' && empty($item['offer_id'])) { $item['offer_id']=(string)($rawItem['offer']['id']??''); }
                if ($row['platform']==='erli' && empty($item['external_id'])) { $item['external_id']=(string)($rawItem['productExternalId']??$rawItem['externalId']??$rawItem['product']['externalId']??$rawItem['product']['id']??''); }
                if ($row['platform']==='empik') {
                    if (empty($item['shop_sku'])) { $item['shop_sku']=(string)($rawItem['offer_sku']??$rawItem['shop_sku']??''); }
                    if (empty($item['product_sku'])) { $item['product_sku']=(string)($rawItem['product_sku']??''); }
                    if (empty($item['product_id'])) { $item['product_id']=(string)($rawItem['product_id']??''); }
                }
                $item['image_url']=self::imageUrl($item);
                if ($item['image_url']==='' && $rawItem) { $item['image_url']=self::imageUrl($rawItem); }
                $sku=trim((string)($item['sku']??''));
                if ($sku!=='') { $skus[$sku]=true; }
                $sourceAccounts[$row['platform']][(int)$row['account_source_id']]=true;
                if ($row['platform']==='allegro' && !empty($item['offer_id'])) { $allegroOfferIds[(string)$item['offer_id']]=true; }
                if ($row['platform']==='erli' && !empty($item['external_id'])) { $erliExternalIds[(string)$item['external_id']]=true; }
                if ($row['platform']==='empik') {
                    if (!empty($item['shop_sku'])) { $empikShopSkus[(string)$item['shop_sku']]=true; }
                    if (!empty($item['product_sku'])) { $empikProductSkus[(string)$item['product_sku']]=true; }
                    if (!empty($item['product_id'])) { $empikProductIds[(string)$item['product_id']]=true; }
                }
            }
            unset($item);
            $row['items']=array_slice($items,0,3);
            $row['item_lines']=count($items);
            $row['item_quantity']=array_sum(array_map(static function ($item) { return max(0,(int)($item['quantity']??0)); },$items));
            $row['delivery']=(string)($details['delivery']??'');
            $row['pickup']=(string)($details['pickup']??'');
            $row['shipping_cents']=(int)($details['shipping_cents']??0);
            $paymentText=strtolower((string)($details['payment_method']??''));
            $row['cash_on_delivery']=(int)(!empty($details['cash_on_delivery']) || strpos($paymentText,'pobran')!==false);
            $row['ordered_short']=self::shortDate((string)$row['ordered_at']);
            $row['status_changed_short']=self::shortDate((string)($row['status_changed_at']??''));
            $row['external_short']=(function (string $id): string { return mb_strlen($id,'UTF-8')>10?mb_substr($id,0,10,'UTF-8').'…':$id; })((string)$row['external_id']);
            unset($row['details_json']);
        }
        unset($row);
        $productImages=[];
        if ($skus) {
            try {
                $params=[];$marks=[];
                foreach (array_keys($skus) as $index=>$sku) { $key='sku'.$index;$marks[]=':'.$key;$params[$key]=$sku; }
                foreach ($this->db->fetchAll('SELECT sku,img FROM products WHERE sku IN ('.implode(',',$marks).')',$params) as $product) {
                    $url=self::imageUrl($product['img']??'');
                    if ($url!=='') { $productImages[(string)$product['sku']]=$url; }
                }
            } catch (\Throwable $error) { $productImages=[]; }
        }
        $marketImages=[];
        try {
            if ($allegroOfferIds && !empty($sourceAccounts['allegro'])) {
                $queryParams=[];$offerMarks=[];$accountMarks=[];
                foreach (array_keys($allegroOfferIds) as $index=>$id) { $key='ao'.$index;$offerMarks[]=':'.$key;$queryParams[$key]=$id; }
                foreach (array_keys($sourceAccounts['allegro']) as $index=>$id) { $key='aa'.$index;$accountMarks[]=':'.$key;$queryParams[$key]=$id; }
                foreach ($this->db->fetchAll('SELECT account_id,offer_id,sku,primary_image_url FROM allegro_offers WHERE account_id IN ('.implode(',',$accountMarks).') AND offer_id IN ('.implode(',',$offerMarks).')',$queryParams) as $offer) {
                    $url=self::imageUrl($offer['primary_image_url']??'');
                    if ($url!=='') { $marketImages['allegro|'.$offer['account_id'].'|offer|'.$offer['offer_id']]=$url; }
                }
            }
            if ($erliExternalIds && !empty($sourceAccounts['erli'])) {
                $queryParams=[];$externalMarks=[];$accountMarks=[];
                foreach (array_keys($erliExternalIds) as $index=>$id) { $key='ee'.$index;$externalMarks[]=':'.$key;$queryParams[$key]=$id; }
                foreach (array_keys($sourceAccounts['erli']) as $index=>$id) { $key='ea'.$index;$accountMarks[]=':'.$key;$queryParams[$key]=$id; }
                foreach ($this->db->fetchAll('SELECT account_id,external_id,sku,primary_image_url FROM erli_products WHERE account_id IN ('.implode(',',$accountMarks).') AND external_id IN ('.implode(',',$externalMarks).')',$queryParams) as $product) {
                    $url=self::imageUrl($product['primary_image_url']??'');
                    if ($url!=='') { $marketImages['erli|'.$product['account_id'].'|external|'.$product['external_id']]=$url; }
                }
            }
            if (($empikShopSkus || $empikProductSkus || $empikProductIds) && !empty($sourceAccounts['empik'])) {
                $queryParams=[];$accountMarks=[];$identifierGroups=[];
                foreach (array_keys($sourceAccounts['empik']) as $index=>$id) { $key='ema'.$index;$accountMarks[]=':'.$key;$queryParams[$key]=$id; }
                foreach ([['shop_sku',$empikShopSkus,'ems'],['product_sku',$empikProductSkus,'emps'],['product_id',$empikProductIds,'empi']] as [$column,$values,$prefix]) {
                    if (!$values) { continue; }
                    $marks=[];
                    foreach (array_keys($values) as $index=>$value) { $key=$prefix.$index;$marks[]=':'.$key;$queryParams[$key]=$value; }
                    $identifierGroups[]=$column.' IN ('.implode(',',$marks).')';
                }
                foreach ($this->db->fetchAll('SELECT account_id,shop_sku,product_sku,product_id,offer_json FROM empik_offers WHERE account_id IN ('.implode(',',$accountMarks).') AND ('.implode(' OR ',$identifierGroups).')',$queryParams) as $offer) {
                    $payload=[];
                    try { $payload=json_decode((string)($offer['offer_json']??''),true,512,JSON_THROW_ON_ERROR); }
                    catch (\Throwable $error) { $payload=[]; }
                    $url=self::imageUrl(is_array($payload)?$payload:[]);
                    if ($url==='') { continue; }
                    foreach (['shop_sku','product_sku','product_id'] as $field) {
                        $value=trim((string)($offer[$field]??''));
                        if ($value!=='') { $marketImages['empik|'.$offer['account_id'].'|'.$field.'|'.$value]=$url; }
                    }
                }
            }
        } catch (\Throwable $error) { /* Marketplace product cache is optional. */ }
        foreach ($rows as &$row) {
            foreach ($row['items'] as &$item) {
                $sku=(string)($item['sku']??'');
                if (($item['image_url']??'')==='') {
                    $keys=[];
                    if ($row['platform']==='allegro') { $keys[]='allegro|'.$row['account_source_id'].'|offer|'.($item['offer_id']??''); }
                    elseif ($row['platform']==='erli') { $keys[]='erli|'.$row['account_source_id'].'|external|'.($item['external_id']??''); }
                    elseif ($row['platform']==='empik') {
                        foreach (['shop_sku','product_sku','product_id'] as $field) {
                            $value=trim((string)($item[$field]??''));
                            if ($value!=='') { $keys[]='empik|'.$row['account_source_id'].'|'.$field.'|'.$value; }
                        }
                    }
                    foreach ($keys as $key) { if (isset($marketImages[$key])) { $item['image_url']=$marketImages[$key]; break; } }
                    if ($item['image_url']==='' && isset($productImages[$sku])) { $item['image_url']=$productImages[$sku]; }
                }
            }
            unset($item);
        }
        unset($row);
        return ['rows'=>$rows,'total'=>$count,'page'=>$page,'pages'=>max(1,(int)ceil($count/50))];
    }
    private static function preserveItemImages(array $current,array $previous): array
    {
        $oldItems=is_array($previous['items']??null)?$previous['items']:[];
        $byKey=[];
        foreach ($oldItems as $oldItem) {
            $url=self::imageUrl($oldItem);
            if ($url==='') { continue; }
            foreach (['offer_id','external_id','sku'] as $field) {
                $value=trim((string)($oldItem[$field]??''));
                if ($value!=='') { $byKey[$field.'|'.$value]=$url; }
            }
        }
        foreach ($current['items']??[] as $index=>&$item) {
            if (self::imageUrl($item)!=='') { continue; }
            $url='';
            foreach (['offer_id','external_id','sku'] as $field) {
                $key=$field.'|'.trim((string)($item[$field]??''));
                if (isset($byKey[$key])) { $url=$byKey[$key]; break; }
            }
            if ($url==='' && isset($oldItems[$index])) { $url=self::imageUrl($oldItems[$index]); }
            if ($url!=='') { $item['image_url']=$url; }
        }
        unset($item);
        return $current;
    }
    public function event(int $id,string $message,string $actor): void
    {
        $this->db->insert('om_events',['order_id'=>$id,'message'=>$message,'actor'=>$actor,'created_at'=>gmdate('Y-m-d H:i:s')]);
    }
    public function import(int $accountId,array $order): bool
    {
        return $this->transactional(function () use ($accountId,$order) {
            $old = $this->db->fetch('SELECT * FROM om_orders WHERE account_id=:a AND external_id=:e'.$this->rowLock(),['a'=>$accountId,'e'=>$order['external_id']]);
            $details = $order['details']; unset($order['details']);
            $this->applyPaymentMapping($accountId,$order,$details);
            if ($old) {
                try {
                    $previous=json_decode((string)$old['details_json'],true,512,JSON_THROW_ON_ERROR);
                    $details=self::preserveItemImages($details,$previous);
                    $manual=is_array($previous['_manual']??null)?$previous['_manual']:[];
                    if ($manual) {
                        $details=array_replace_recursive($details,is_array($manual['details']??null)?$manual['details']:[]);
                        $details['_manual']=$manual;
                        foreach ((array)($manual['order']??[]) as $field=>$value) { if (in_array($field,['buyer_name','email','phone','total_cents','currency','paid'],true)) { $order[$field]=$value; } }
                    }
                }
                catch (\Throwable $error) { /* Keep the fresh payload when a legacy snapshot is malformed. */ }
            }
            $order['details_json'] = self::json($details);
            $order['updated_at'] = gmdate('Y-m-d H:i:s');
            $mapping = $this->db->fetchColumn('SELECT status_id FROM om_mappings WHERE account_id=:a AND remote_status=:s',['a'=>$accountId,'s'=>$order['remote_status']]);
            if ($old) {
                $oldId=(int)$old['id'];
                if (!(int)$old['status_manual'] && $mapping) { $order['status_id'] = (int)$mapping; }
                $statusChanged=isset($order['status_id']) && (int)$old['status_id'] !== $order['status_id'];
                if ($statusChanged) { $order['status_changed_at']=gmdate('Y-m-d H:i:s'); }
                $this->db->update('om_orders',$order,'id=:id',['id'=>$oldId]);
                if ($old['remote_status'] !== $order['remote_status']) { $this->event($oldId,'Status źródłowy: '.$order['remote_status'],'synchronizacja'); }
                if ((int)$old['paid'] !== (int)$order['paid']) { $this->event($oldId,(int)$order['paid'] ? 'Płatność potwierdzona w źródle.' : 'Źródło nie potwierdza już płatności.','synchronizacja'); }
                if ($statusChanged) { $this->event($oldId,'Mapowanie statusu na #'.$order['status_id'],'synchronizacja'); }
                // Import automations can also become eligible after payment/data arrives.
                $this->automationEvent($oldId,'import');
                if ($old['remote_status'] !== $order['remote_status']) { $this->automationEvent($oldId,'remote_status',['previous_remote_status'=>(string)$old['remote_status']]); }
                if ($statusChanged) { $this->automationEvent($oldId,'status',['previous_status_id'=>(int)$old['status_id'],'status_source'=>'sync']); }
                if ((int)$old['paid'] !== (int)$order['paid']) { $this->automationEvent($oldId,(int)$order['paid']?'paid':'unpaid',['payment_source'=>'sync']); }
                return false;
            }
            $order += ['account_id'=>$accountId,'status_id'=>$mapping ? (int)$mapping : 1,'imported_at'=>gmdate('Y-m-d H:i:s'),'status_changed_at'=>gmdate('Y-m-d H:i:s')];
            $id = (int)$this->db->insert('om_orders',$order);
            $this->event($id,'Pobrano zamówienie. Marketplace pozostaje bez zmian.','synchronizacja');
            $this->automationEvent($id,'order_created');
            $this->automationEvent($id,'import');
            if ((int)$order['paid']) { $this->automationEvent($id,'paid',['payment_source'=>'sync']); }
            return true;
        });
    }
    /**
     * Marketplace orders are re-created by the next sync pass unless their account's
     * import is paused first — the confirm dialog on the delete button says so.
     */
    public function deleteOrder(int $id): void
    {
        $this->db->transaction(function () use ($id) {
            $order=$this->db->fetch('SELECT id FROM om_orders WHERE id=:id'.$this->rowLock(),['id'=>$id]);
            if (!$order) { throw new InvalidArgumentException('Nie znaleziono zamówienia.'); }
            if ($this->db->fetchColumn('SELECT id FROM om_documents WHERE order_id=:id LIMIT 1',['id'=>$id])) {
                throw new InvalidArgumentException('Nie można usunąć zamówienia, dla którego wystawiono paragon lub fakturę. Usuń najpierw dokumenty.');
            }
            $this->db->delete('om_shipments','order_id=:id',['id'=>$id]);
            $this->db->delete('om_rule_runs','order_id=:id',['id'=>$id]);
            $this->db->delete('om_events','order_id=:id',['id'=>$id]);
            $this->db->delete('om_orders','id=:id',['id'=>$id]);
        });
    }
    public function changeStatus(int $id,int $status,string $actor,bool $rules=true): void
    {
        $this->requireStatus($status);
        $old = $this->order($id);
        if ((int)$old['status_id'] === $status) { return; }
        $this->db->update('om_orders',['status_id'=>$status,'status_manual'=>1,'status_changed_at'=>gmdate('Y-m-d H:i:s')],'id=:id',['id'=>$id]);
        $this->event($id,'Status wewnętrzny: '.$old['status_name'].' → #'.$status,$actor);
        if ($rules) { $this->automationEvent($id,'status',['previous_status_id'=>(int)$old['status_id'],'status_source'=>strpos($actor,'automat')===0?'automation':'user']); }
    }
    /** Kept for callers of the former rule engine; preview only reports matching rule names. */
    public function runRules(int $id,string $trigger,string $eventKey,bool $preview=false): array
    {
        if ($preview) { return $this->automation()->matchingRuleNames($id,$trigger); }
        $this->automationEvent($id,$trigger);
        return [];
    }
    public function rules(): array
    {
        return $this->automation()->rulesForDisplay();
    }
    public function dashboard(): array
    {
        return ['statuses'=>$this->db->fetchAll('SELECT s.*,COUNT(o.id) total FROM om_statuses s LEFT JOIN om_orders o ON o.status_id=s.id GROUP BY s.id,s.name,s.color,s.position,s.group_name ORDER BY s.group_name,s.position,s.id'),
            'today'=>(int)$this->db->fetchColumn('SELECT COUNT(*) FROM om_orders WHERE ordered_at>=:d',['d'=>gmdate('Y-m-d')]),
            'unpaid'=>(int)$this->db->fetchColumn('SELECT COUNT(*) FROM om_orders WHERE paid=0'),
            'total'=>(int)$this->db->fetchColumn('SELECT COUNT(*) FROM om_orders')];
    }
}
