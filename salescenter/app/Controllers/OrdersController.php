<?php

declare(strict_types=1);
namespace App\Controllers;
use App\Core\Controller;
use App\Core\Config;
use App\Core\SmartyFactory;
use App\Models\OrderRepository;
use App\Models\SaasRepository;
use App\Models\PrintAgentRepository;
use App\Services\OrderSyncService;
use App\Services\OrderDocumentService;
use App\Services\KsefService;
use App\Services\OrderMarketplaceShipmentService;
use App\Services\OrderShipmentService;
use App\Services\Shipping\ShippingProviders;
use InvalidArgumentException;

final class OrdersController extends Controller
{
    private function repository(): OrderRepository
    {
        $repo=new OrderRepository($this->db()); $repo->ensureSchema();
        (new \App\Models\ConnectionRepository($this->db()))->ensureSchema();
        return $repo;
    }
    private function ksef(OrderRepository $repo): KsefService
    {
        $ksef=new KsefService($repo); $ksef->ensureSchema(); return $ksef;
    }
    /** Auto-send to KSeF after issuing an invoice; failures never block the issued document. */
    private function ksefAutoSend(OrderRepository $repo,int $documentId,string $actor): void
    {
        $this->ksef($repo)->autoSend($documentId,$actor);
    }
    private function token(): string
    {
        $this->ensureSessionStarted();
        if (empty($_SESSION['orders_csrf'])) { $_SESSION['orders_csrf']=bin2hex(random_bytes(32)); }
        return $_SESSION['orders_csrf'];
    }
    private function writeGuard(): array
    {
        $user=$this->requireModuleWrite('orders');
        if (!$this->isPost()) { http_response_code(405); exit('Wymagany POST.'); }
        if (!hash_equals($this->token(),(string)($_POST['csrf']??''))) { http_response_code(403); exit('Sesja formularza wygasła. Odśwież stronę.'); }
        return $user;
    }
    public function index(): void
    {
        $user=$this->requireModule('orders'); $csrf=$this->token(); $repo=$this->repository();
        $printAgents=new PrintAgentRepository($this->db()); $printAgents->ensureSchema();
        $tab=(string)$this->input('tab','list');
        if (!in_array($tab,['list','new','accounts','statuses','rules','documents','shipments','payments','printing','general'],true)) { $tab='list'; }
        $filters=[
            'q'=>(string)$this->input('q',''),
            'status_id'=>$this->input('status_id',''),
            'group'=>(string)$this->input('group',''),
            'account_id'=>$this->input('account_id',''),
            'platform'=>(string)$this->input('platform',''),
            'paid'=>$this->input('paid',''),
            'payment_source'=>mb_substr((string)$this->input('payment_source',''),0,190),
            'date_from'=>(string)$this->input('date_from',''),
            'date_to'=>(string)$this->input('date_to',''),
            'amount_from'=>(string)$this->input('amount_from',''),
            'amount_to'=>(string)$this->input('amount_to',''),
            'sort'=>(string)$this->input('sort','newest'),
            'page'=>$this->input('page',1),
        ];
        $listQuery=http_build_query(array_filter($filters,static function ($value,$key) {
            return $key!=='page' && $value!=='' && $value!==null;
        },ARRAY_FILTER_USE_BOTH));
        $activeFilterCount=count(array_filter($filters,static function ($value,$key) {
            return !in_array($key,['page','sort','status_id','group'],true) && $value!=='' && $value!==null;
        },ARRAY_FILTER_USE_BOTH));
        $detail=null; $events=[]; $orderDocs=[]; $orderShipments=[]; $issuedDocuments=['receipt'=>null,'invoice'=>null];
        if ((int)$this->input('id',0)>0) {
            $detail=$repo->order((int)$this->input('id'));
            $sellerId=$detail['platform']==='allegro'?$this->allegroSellerId($repo,(int)($detail['account_source_id']??0)):'';
            if (in_array($detail['platform'],['erli','woocommerce'],true)) {
                try { $sourceConnection=json_decode((string)($this->db()->fetchColumn('SELECT public_json FROM om_connections WHERE id=:id AND platform=:p',['id'=>(int)$detail['account_source_id'],'p'=>$detail['platform']])?:'{}'),true)?:[]; }
                catch (\Throwable $e) { $sourceConnection=[]; }
                $sellerId=(string)($detail['platform']==='erli'?($sourceConnection['shop_id']??''):rtrim((string)($sourceConnection['shop_url']??''),'/'));
            }
            if ($detail['platform']==='altreo') { $sellerId=(string)($detail['details']['raw']['admin_url']??''); }
            $detail['source_order_url']=$this->sourceOrderUrl((string)$detail['platform'],(string)$detail['external_id'],$sellerId);
            $detail['raw_debug']=json_encode($detail['details']['raw']??[],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $detail['notes']=$repo->notes((int)$detail['id']);
            $events=$this->db()->fetchAll('SELECT * FROM om_events WHERE order_id=:id ORDER BY id DESC LIMIT 100',['id'=>$detail['id']]);
            $orderDocs=$this->db()->fetchAll('SELECT id,number,kind,created_at FROM om_documents WHERE order_id=:id ORDER BY id DESC',['id'=>$detail['id']]);
            foreach ($orderDocs as $orderDocument) {
                $kind=(string)$orderDocument['kind'];
                if (array_key_exists($kind,$issuedDocuments) && $issuedDocuments[$kind]===null) { $issuedDocuments[$kind]=$orderDocument; }
            }
            $orderShipments=$this->db()->fetchAll('SELECT s.*,ca.provider AS carrier_provider FROM om_shipments s LEFT JOIN om_carrier_accounts ca ON ca.id=s.carrier_account_id WHERE s.order_id=:id ORDER BY s.id DESC',['id'=>$detail['id']]);
            foreach ($orderShipments as &$shipment) {
                if ($shipment['cod_amount_cents']===null) { $shipment['cod_amount_cents']=!empty($detail['details']['cash_on_delivery'])?(int)($detail['details']['amount_due_cents']??$detail['total_cents']):0; }
                $shipment['shipment_currency']=trim((string)($shipment['shipment_currency']??'')) ?: (string)$detail['currency'];
                $shipment['presentation']=OrderShipmentService::presentation($shipment,$detail);
            }
            unset($shipment);
            $detail['payment_info']=$this->paymentInfo($detail,$events);
        }
        $carrierAccounts=$repo->carrierAccounts();
        $shippingDefaults=$this->shippingDefaults($repo);
        $shipmentSuggestion=$detail?$this->shipmentSuggestion($detail,$carrierAccounts,$shippingDefaults):[];
        $documentDefaults=$repo->setting('document_defaults')+['vat'=>'23'];
        $shipmentRegister=[];
        if ($tab==='shipments') {
            $shipmentRegister=$this->db()->fetchAll('SELECT s.*,ca.provider AS carrier_provider FROM om_shipments s LEFT JOIN om_carrier_accounts ca ON ca.id=s.carrier_account_id ORDER BY s.id DESC LIMIT 100');
            foreach ($shipmentRegister as &$registered) { $registered['presentation']=OrderShipmentService::presentation($registered,['details'=>[]]); }
            unset($registered);
        }
        $canManageTenant=in_array((string)($user['role']??''),['owner','admin'],true);
        $accountData=['tenant'=>[],'teamUsers'=>[],'teamRoles'=>SaasRepository::ROLES,'canManageTenant'=>$canManageTenant];
        if ($tab==='general') {
            $accountData['tenant']=$this->saas()->tenant((int)$user['tenant_id'])??[];
            if ($canManageTenant) { $accountData['teamUsers']=$this->saas()->users((int)$user['tenant_id']); }
        }
        $receiptPrinterSettings=$repo->setting('receipt_printer')+['printer_id'=>0];
        $series=$this->db()->fetchAll('SELECT * FROM om_series ORDER BY id');
        $seriesUsage=array_column($this->db()->fetchAll('SELECT series_id,COUNT(*) c FROM om_documents GROUP BY series_id'),'c','series_id');
        foreach ($series as &$item) {
            $item['numbering']=json_decode((string)($item['numbering_json']??''),true)?:[];
            $item['document_settings']=json_decode((string)($item['document_settings_json']??''),true)?:[];
            $item['effective_printer_id']=$item['fiscal_printer_id']===null?(int)$receiptPrinterSettings['printer_id']:(int)$item['fiscal_printer_id'];
            $item['document_count']=(int)($seriesUsage[$item['id']]??0);
        }
        unset($item);
        $documentSeriesFilter=(int)$this->input('series_id',0);
        $documentsSql='SELECT d.*,s.name AS series_name FROM om_documents d LEFT JOIN om_series s ON s.id=d.series_id';
        $documentsParams=[];
        if ($documentSeriesFilter>0) { $documentsSql.=' WHERE d.series_id=:sid'; $documentsParams['sid']=$documentSeriesFilter; }
        $documentsSql.=' ORDER BY d.id DESC LIMIT 100';
        $correctionParents=array_flip(array_column($this->db()->fetchAll('SELECT DISTINCT parent_id FROM om_documents WHERE parent_id IS NOT NULL'),'parent_id'));
        $documents=[];
        foreach ($this->db()->fetchAll($documentsSql,$documentsParams) as $docRow) {
            $snap=json_decode($docRow['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
            $docRow['buyer']=(string)($snap['buyer']??'');
            $docRow['recipient']=(string)($snap['recipient']??'');
            $docRow['additional_info']=(string)($snap['additional_info']??'');
            $docRow['gross_cents']=(int)($snap['gross_cents']??0);
            $docRow['currency']=(string)($snap['currency']??'PLN');
            $docRow['has_correction']=isset($correctionParents[$docRow['id']]);
            $docRow['items']=array_map(static function ($it) {
                return ['name'=>(string)($it['name']??''),'sku'=>(string)($it['sku']??''),'ean'=>(string)($it['ean']??''),'quantity'=>(int)($it['quantity']??0),'vat'=>(string)($it['vat']??'23'),'price'=>number_format(((int)($it['unit_cents']??0))/100,2,'.','')];
            },(array)($snap['items']??[]));
            $documents[]=$docRow;
        }
        $ksef=$this->ksef($repo);
        $ksefDocumentIds=array_merge(array_column($documents,'id'),array_column($orderDocs,'id'));
        $ksefSubmissions=$ksef->latest($ksefDocumentIds);
        $settingsAccess=$this->moduleAccessLevel($user,'orders')==='edit';
        $automation=$repo->automation();
        $automationView=['stats'=>['active'=>0,'paused'=>0,'runs_24h'=>0,'errors_24h'=>0],'log'=>[],'edit'=>null,'catalog_json'=>'{}','rule_json'=>'null','template'=>''];
        if ($tab==='rules') {
            $automationView['stats']=$automation->stats();
            $automationView['log']=$automation->log(40);
            $ruleParam=(string)$this->input('rule','');
            if ($settingsAccess && $ruleParam!=='') {
                $editId=$ruleParam==='new'?0:max(0,(int)$ruleParam);
                $editRule=$editId>0?$automation->rule($editId):null;
                if ($editId===0 || $editRule) {
                    $draft=$_SESSION['orders_rule_draft']??null; unset($_SESSION['orders_rule_draft']);
                    $draftRule=is_array($draft) && (int)($draft['id']??-1)===$editId && is_array($draft['rule']??null)?$draft['rule']:null;
                    $jsonFlags=JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR;
                    $automationView['edit']=['id'=>$editId,'name'=>(string)($editRule['name']??'')];
                    $automationView['catalog_json']=json_encode($automation->catalog(),$jsonFlags);
                    $automationView['rule_json']=json_encode($draftRule??$editRule,$jsonFlags);
                    $automationView['template']=preg_replace('/[^a-z_]/','',(string)$this->input('template',''));
                }
            }
        }
        $dashboard=$repo->dashboard();
        $statusGroups=[];
        foreach ($dashboard['statuses'] as $status) {
            $groupName=(string)($status['group_name']??'Pozostałe');
            if (!isset($statusGroups[$groupName])) { $statusGroups[$groupName]=['name'=>$groupName,'total'=>0,'statuses'=>[]]; }
            $statusGroups[$groupName]['total']+=(int)$status['total'];
            $statusGroups[$groupName]['statuses'][]=$status;
        }
        $integrationsView=[];
        if ($tab==='accounts') {
            $this->ensureSessionStarted();
            $apiToken=$_SESSION['sc_api_token']??null; unset($_SESSION['sc_api_token']);
            $catalog=\App\Services\Integrations\Catalog::all();
            $connections=(new \App\Models\ConnectionRepository($this->db()))->all();
            foreach ($connections as &$connection) { $connection['meta']=$catalog[$connection['platform']]??['label'=>$connection['platform'],'color'=>'#64748b','logo'=>'?','features'=>[]]; }
            unset($connection);
            $integrationsView=['catalog'=>$catalog,'connections'=>$connections,'allegroConfigured'=>\App\Services\AllegroService::configured(),'allegroSource'=>(string)(\App\Services\AllegroService::config()['source']??''),'allegroRedirectUri'=>\App\Controllers\IntegrationsController::appUrl('allegro-callback.php'),'platformOperator'=>(new \App\Models\SaasRepository($this->db()))->isPlatformOperator($user),'apiBase'=>\App\Controllers\IntegrationsController::apiBase(),'apiToken'=>is_array($apiToken)?$apiToken:null,
                'selected'=>(int)$this->input('connection',0),'add'=>preg_replace('/[^a-z]/','',(string)$this->input('add','')),'backfill'=>(int)$this->input('backfill',0),'manual'=>(string)$this->input('manual','')==='1','today'=>(new \DateTimeImmutable('now',new \DateTimeZone('Europe/Warsaw')))->format('Y-m-d'),
                'callbackHttps'=>stripos(\App\Controllers\IntegrationsController::appUrl('x'),'https://')===0];
        }
        $this->renderOrders($accountData+[
            'integrations'=>$integrationsView,
            'pageTitle'=>'Centrum zamówień','tab'=>$tab,'csrf'=>$csrf,'canWrite'=>$settingsAccess,
            'listing'=>$repo->listing($filters),'filters'=>$filters,'listQuery'=>$listQuery,'activeFilterCount'=>$activeFilterCount,'dashboard'=>$dashboard,'statusGroups'=>$statusGroups,
            'accounts'=>$repo->accounts(),'statuses'=>$repo->statuses(),'detail'=>$detail,'events'=>$events,'orderDocs'=>$orderDocs,'issuedDocuments'=>$issuedDocuments,'orderShipments'=>$orderShipments,
            'paymentMethods'=>$repo->paymentMethods(),'paymentSources'=>$repo->paymentSources(),
            'mappings'=>$this->db()->fetchAll('SELECT m.*,a.name account_name,a.platform,s.name status_name FROM om_mappings m JOIN om_accounts a ON a.id=m.account_id JOIN om_statuses s ON s.id=m.status_id ORDER BY a.platform,a.name,m.remote_status'),
            'rules'=>$tab==='rules'?$repo->rules():[],'automation'=>$automationView,'manualRules'=>$automation->manualRules(),
            'orderAutomation'=>$detail?['rules'=>$automation->explain((int)$detail['id']),'runs'=>$automation->log(12,(int)$detail['id'])]:['rules'=>[],'runs'=>[]],
            'series'=>$series,
            'seller'=>$repo->setting('seller')+['name'=>'','nip'=>'','address'=>'','bank'=>'','bank_name'=>'','swift'=>'','email'=>'','phone'=>'','regon'=>'','krs'=>'','bdo'=>''],
            'documents'=>$documents,'documentSeriesFilter'=>$documentSeriesFilter,'ksefAccounts'=>$ksef->accounts(),'ksefTargets'=>$ksef->targets($ksefDocumentIds),'ksefSubmissions'=>$ksefSubmissions,
            'shipments'=>$shipmentRegister,'shippingProviders'=>$tab==='shipments'?ShippingProviders::catalog($this->carrierAccountStats($carrierAccounts),$repo->accounts()):[],
            'shippingView'=>['selected'=>(int)$this->input('carrier',0),'add'=>preg_replace('/[^a-z_]/','',(string)$this->input('add',''))],
            'carrierAccounts'=>$carrierAccounts,'shippingDefaults'=>$shippingDefaults,'shipmentSuggestion'=>$shipmentSuggestion,'documentDefaults'=>$documentDefaults,'receiptPrinterSettings'=>$receiptPrinterSettings,'sourceCarrierOptions'=>OrderMarketplaceShipmentService::carrierOptions(),
            'printStations'=>$printAgents->stations(),'printJobs'=>$printAgents->jobs(),'printFiscalPrinters'=>$printAgents->fiscalPrinters(),'printFiscalJobs'=>$printAgents->fiscalJobs(),'printAgentApiUrl'=>$this->printAgentApiBase(),
            'documentKey'=>bin2hex(random_bytes(24)),
        ]);
    }
    private function renderOrders(array $data): void
    {
        $data['flashSuccess']=$this->getFlash('success');
        $data['flashError']=$this->getFlash('error');
        $data['printAgentToken']=$this->getFlash('print_token');
        header('Cache-Control: no-store, private');
        $this->render('orders/index',$data);
    }
    private function jsonResponse(array $data,int $status=200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }
    private function apiUser(bool $write): bool
    {
        $user=$this->currentUser();
        if (!$user) { $this->jsonResponse(['error'=>'Zaloguj się ponownie do aplikacji.','code'=>'AUTH_REQUIRED','login_url'=>'index.php?controller=auth&action=login'],401); return false; }
        $access=$this->moduleAccessLevel($user,'orders');
        if ($access==='none' || ($write && $access!=='edit')) {
            $this->jsonResponse(['error'=>'Brak uprawnień do tej operacji w centrum zamówień.','code'=>'ACCESS_DENIED'],403); return false;
        }
        return true;
    }
    public function session(): void
    {
        try {
            if (!$this->apiUser(false)) { return; }
            $csrf=$this->token(); $this->releaseSessionLock();
            $repo=$this->repository();
            $accounts=array_map(static function ($a) { return ['id'=>(int)$a['id'],'name'=>$a['name'],'enabled'=>(int)$a['enabled']]; },$repo->accounts());
            $this->jsonResponse(['csrf'=>$csrf,'accounts'=>$accounts]);
        } catch (\Throwable $e) { $this->apiFailure($e); }
    }
    public function shippingoptions(): void
    {
        try {
            if (!$this->apiUser(false)) { return; }
            $orderId=(int)$this->input('order_id',0); $carrierAccountId=(int)$this->input('carrier_account_id',0);
            if ($orderId<1 || $carrierAccountId<1) { $this->jsonResponse(['error'=>'Wybierz zamówienie i konto nadawcze.','code'=>'INVALID_INPUT'],422); return; }
            $this->releaseSessionLock();
            $this->jsonResponse((new OrderShipmentService($this->repository()))->options($orderId,$carrierAccountId));
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(['error'=>$e->getMessage(),'code'=>'INVALID_INPUT'],422);
        } catch (\Throwable $e) { $this->apiFailure($e); }
    }
    public function shippingvaluation(): void
    {
        try {
            if (!$this->apiUser(false)) { return; }
            if (!$this->isPost()) { $this->jsonResponse(['error'=>'Wymagany POST.','code'=>'METHOD_NOT_ALLOWED'],405); return; }
            if (!hash_equals($this->token(),(string)($_POST['csrf']??''))) { $this->jsonResponse(['error'=>'Token formularza wymaga odświeżenia.','code'=>'CSRF_EXPIRED'],419); return; }
            $orderId=(int)($_POST['order_id']??0); $carrierAccountId=(int)($_POST['carrier_account_id']??0);
            if ($orderId<1 || $carrierAccountId<1) { throw new InvalidArgumentException('Wybierz zamówienie i konto nadawcze.'); }
            $this->releaseSessionLock();
            $this->jsonResponse((new OrderShipmentService($this->repository()))->valuation($orderId,$carrierAccountId,$_POST));
        } catch (InvalidArgumentException $e) { $this->jsonResponse(['error'=>$e->getMessage(),'code'=>'INVALID_INPUT'],422); }
        catch (\Throwable $e) { $this->apiFailure($e); }
    }
    public function orderautosave(): void
    {
        try {
            if (!$this->apiUser(true)) { return; }
            if (!$this->isPost()) { $this->jsonResponse(['error'=>'Wymagany POST.','code'=>'METHOD_NOT_ALLOWED'],405); return; }
            if (!hash_equals($this->token(),(string)($_POST['csrf']??''))) { $this->jsonResponse(['error'=>'Token formularza wymaga odświeżenia.','code'=>'CSRF_EXPIRED'],419); return; }
            $id=(int)($_POST['order_id']??0); $status=(int)($_POST['status_id']??0);
            if ($id<1 || $status<1) { throw new InvalidArgumentException('Nieprawidłowe zamówienie lub status.'); }
            $user=$this->currentUser(); $actor=(string)($user['name']??$user['email']??('użytkownik #'.($user['id']??0)));
            $repo=$this->repository(); $db=$this->db();
            try {
                $db->transaction(function () use ($repo,$db,$id,$status,$actor) {
                    $order=$repo->order($id);
                    if ((int)$order['status_id']!==$status) { $repo->changeStatus($id,$status,$actor); }
                    // Notes are edited one by one through ordernote().
                    $tags=mb_substr((string)($_POST['tags']??''),0,1000);
                    if ((string)$order['tags']!==$tags) {
                        $db->update('om_orders',['tags'=>$tags,'updated_at'=>gmdate('Y-m-d H:i:s')],'id=:id',['id'=>$id]);
                    }
                });
            } catch (\Throwable $e) { $repo->discardAutomations(); throw $e; }
            $repo->flushAutomations();
            $this->jsonResponse(['ok'=>true,'saved_at'=>date('H:i:s')]);
        } catch (InvalidArgumentException $e) { $this->jsonResponse(['error'=>$e->getMessage(),'code'=>'INVALID_INPUT'],422); }
        catch (\Throwable $e) { $this->apiFailure($e); }
    }
    /** Adds (op=add), edits (op=update) or deletes (op=delete) one internal note; returns the order's notes. */
    public function ordernote(): void
    {
        try {
            if (!$this->apiUser(true)) { return; }
            if (!$this->isPost()) { $this->jsonResponse(['error'=>'Wymagany POST.','code'=>'METHOD_NOT_ALLOWED'],405); return; }
            if (!hash_equals($this->token(),(string)($_POST['csrf']??''))) { $this->jsonResponse(['error'=>'Token formularza wymaga odświeżenia.','code'=>'CSRF_EXPIRED'],419); return; }
            $orderId=(int)($_POST['order_id']??0); $noteId=(int)($_POST['note_id']??0); $op=(string)($_POST['op']??'');
            if ($orderId<1) { throw new InvalidArgumentException('Nieprawidłowe zamówienie.'); }
            $user=$this->currentUser(); $actor=(string)($user['name']??$user['email']??('użytkownik #'.($user['id']??0)));
            $repo=$this->repository(); $body=(string)($_POST['body']??'');
            if ($op==='add') {
                $repo->addNote($orderId,$body,$actor);
                $repo->event($orderId,'Dodano notatkę.',$actor);
            } elseif ($op==='update' && $noteId>0) {
                $repo->updateNote($orderId,$noteId,$body,$actor);
                $repo->event($orderId,'Zmieniono notatkę.',$actor);
            } elseif ($op==='delete' && $noteId>0) {
                $repo->deleteNote($orderId,$noteId);
                $repo->event($orderId,'Usunięto notatkę.',$actor);
            } else {
                throw new InvalidArgumentException('Nieznana operacja na notatce.');
            }
            $this->jsonResponse(['ok'=>true,'notes'=>$repo->notes($orderId)]);
        } catch (InvalidArgumentException $e) { $this->jsonResponse(['error'=>$e->getMessage(),'code'=>'INVALID_INPUT'],422); }
        catch (\Throwable $e) { $this->apiFailure($e); }
    }
    private function apiFailure(\Throwable $error): void
    {
        $d=\App\Services\OrderSyncError::log($error,['stage'=>'http']);
        $this->jsonResponse(['error'=>$d['message'],'code'=>$d['code'],'reference'=>$d['reference']],500);
    }
    public function save(): void
    {
        $user=$this->writeGuard(); $repo=$this->repository(); $db=$this->db();
        $actor=(string)($user['name']??$user['email']??('użytkownik #'.$user['id']));
        $op=(string)($_POST['operation']??''); $tab=(string)($_POST['tab']??'list'); $id=(int)($_POST['order_id']??0);
        $successMessage='Zapisano.'; $redirectQuery='';
        try {
            switch ($op) {
                case 'discover':
                    $errors=(new OrderSyncService($repo))->discover();
                    if ($errors) { $this->setFlash('error',implode(' ',$errors)); }
                    break;
                case 'account':
                    $account=$db->fetch('SELECT * FROM om_accounts WHERE id=:id',['id'=>(int)$_POST['account_id']]);
                    if (!$account) { throw new InvalidArgumentException('Nieznane konto.'); }
                    if ($account['platform']==='morele' && !empty($_POST['enabled'])) { throw new InvalidArgumentException('Morele oczekuje na specyfikację zamówień API.'); }
                    $db->update('om_accounts',['enabled'=>empty($_POST['enabled'])?0:1],'id=:id',['id'=>$account['id']]);
                    break;
                case 'account_auto_accept':
                    $account=$db->fetch('SELECT * FROM om_accounts WHERE id=:id',['id'=>(int)$_POST['account_id']]);
                    if (!$account) { throw new InvalidArgumentException('Nieznane konto.'); }
                    if (!in_array($account['platform'],['empik','mediamarkt'],true)) { throw new InvalidArgumentException('Automatyczna akceptacja jest dostępna tylko dla kont Empik i MediaMarkt.'); }
                    $db->update('om_accounts',['auto_accept'=>empty($_POST['auto_accept'])?0:1],'id=:id',['id'=>$account['id']]);
                    $successMessage=empty($_POST['auto_accept'])?'Wyłączono automatyczną akceptację zamówień.':'Włączono automatyczną akceptację zamówień.';
                    break;
                case 'status':
                    $name=$this->required('name',100); $color=(string)($_POST['color']??'');
                    if (!preg_match('/^#[a-fA-F0-9]{6}$/D',$color)) { throw new InvalidArgumentException('Nieprawidłowy kolor.'); }
                    $groupName=$this->required('group_name',100);
                    $data=['name'=>$name,'color'=>$color,'position'=>(int)($_POST['position']??0),'group_name'=>$groupName];
                    if (!empty($_POST['status_id'])) { $repo->requireStatus((int)$_POST['status_id']); $db->update('om_statuses',$data,'id=:id',['id'=>(int)$_POST['status_id']]); }
                    else { $db->insert('om_statuses',$data); }
                    break;
                case 'mapping':
                    $status=(int)$_POST['status_id']; $repo->requireStatus($status);
                    $account=(int)$_POST['account_id'];
                    if (!$db->fetchColumn('SELECT id FROM om_accounts WHERE id=:id',['id'=>$account])) { throw new InvalidArgumentException('Nieznane konto.'); }
                    $remote=$this->required('remote_status',100);
                    $db->transaction(function () use ($db,$account,$remote,$status) {
                        $db->delete('om_mappings','account_id=:a AND remote_status=:s',['a'=>$account,'s'=>$remote]);
                        $db->insert('om_mappings',['account_id'=>$account,'remote_status'=>$remote,'status_id'=>$status]);
                    });
                    break;
                case 'unmap': $db->delete('om_mappings','id=:id',['id'=>(int)$_POST['mapping_id']]); break;
                case 'payment_method':
                    $repo->savePaymentMethod((int)($_POST['payment_method_id']??0),$this->required('name',100),!empty($_POST['is_cod']),(int)($_POST['position']??0));
                    $successMessage='Zapisano własną metodę płatności.';
                    break;
                case 'payment_mapping':
                    $updated=$repo->savePaymentMapping((string)($_POST['platform']??''),(string)($_POST['source_method']??''),(int)($_POST['payment_method_id']??0));
                    $successMessage='Zapisano przypisanie. Zaktualizowano zamówienia: '.$updated.'.';
                    break;
                case 'unmap_payment':
                    $repo->removePaymentMapping((int)($_POST['payment_mapping_id']??0));
                    $successMessage='Usunięto przypisanie metody płatności.';
                    break;
                case 'order':
                    $db->transaction(function () use ($repo,$db,$id,$actor) {
                        $repo->changeStatus($id,(int)$_POST['status_id'],$actor);
                        $db->update('om_orders',['tags'=>substr((string)($_POST['tags']??''),0,1000)],'id=:id',['id'=>$id]);
                        $repo->event($id,'Zapisano tagi.',$actor);
                    });
                    break;
                case 'order_details':
                    $repo->updateOrderDetails($id,$_POST,$actor);
                    break;
                case 'delete_order':
                    if ($db->fetchColumn('SELECT id FROM om_documents WHERE order_id=:id LIMIT 1',['id'=>$id])) { throw new InvalidArgumentException('Nie można usunąć zamówienia, dla którego wystawiono paragon lub fakturę. Usuń najpierw dokumenty.'); }
                    $printAgents=new PrintAgentRepository($db); $printAgents->ensureSchema();
                    $printAgents->purgeOrderJobs($id);
                    $repo->deleteOrder($id);
                    $successMessage='Usunięto zamówienie.';
                    $tab='list'; $id=0;
                    break;
                case 'create_order':
                    $id=$repo->createManualOrder($_POST,$actor);
                    $tab='list';
                    $this->setFlash('success','Utworzono nowe zamówienie.');
                    break;
                case 'bulk':
                    $ids=array_unique(array_map('intval',(array)($_POST['ids']??[])));
                    if (!$ids || count($ids)>50) { throw new InvalidArgumentException('Zaznacz od 1 do 50 zamówień.'); }
                    $db->transaction(function () use ($repo,$ids,$actor) { foreach ($ids as $oid) { $repo->changeStatus($oid,(int)$_POST['status_id'],$actor); } });
                    break;
                case 'bulk_delete':
                    $ids=array_unique(array_map('intval',(array)($_POST['ids']??[])));
                    if (!$ids || count($ids)>50) { throw new InvalidArgumentException('Zaznacz od 1 do 50 zamówień.'); }
                    $printAgents=new PrintAgentRepository($db); $printAgents->ensureSchema();
                    $deleted=0; $skipped=[];
                    foreach ($ids as $oid) {
                        try {
                            if ($db->fetchColumn('SELECT id FROM om_documents WHERE order_id=:id LIMIT 1',['id'=>$oid])) { throw new InvalidArgumentException('wystawiono dokument'); }
                            $printAgents->purgeOrderJobs($oid);
                            $repo->deleteOrder($oid);
                            $deleted++;
                        } catch (\Throwable $e) { $skipped[]='#'.$oid.' ('.($e instanceof InvalidArgumentException?$e->getMessage():'błąd').')'; }
                    }
                    $successMessage=$deleted.' usuniętych zamówień.'.($skipped?' Pominięto: '.implode(', ',$skipped).'.':'');
                    $tab='list';
                    break;
                case 'rule_save':
                    $ruleId=max(0,(int)($_POST['rule_id']??0));
                    $ruleInput=json_decode((string)($_POST['rule_json']??''),true);
                    try {
                        if (!is_array($ruleInput) || strlen((string)$_POST['rule_json'])>200000) { throw new InvalidArgumentException('Nie udało się odczytać formularza automatyzacji. Odśwież stronę.'); }
                        $savedId=$repo->automation()->saveRule($ruleId,$ruleInput);
                    } catch (InvalidArgumentException $e) {
                        if (is_array($ruleInput)) { $_SESSION['orders_rule_draft']=['id'=>$ruleId,'rule'=>$ruleInput]; }
                        $redirectQuery='&rule='.($ruleId>0?$ruleId:'new').'#oa-editor';
                        throw $e;
                    }
                    $successMessage=$ruleId>0?'Zapisano automatyzację.':'Utworzono automatyzację.';
                    $redirectQuery='#oa-rule-'.$savedId;
                    break;
                case 'toggle_rule':
                    $db->update('om_rules',['enabled'=>empty($_POST['enabled'])?0:1,'updated_at'=>gmdate('Y-m-d H:i:s')],'id=:id',['id'=>(int)$_POST['rule_id']]);
                    $successMessage=empty($_POST['enabled'])?'Wstrzymano automatyzację.':'Włączono automatyzację.';
                    $redirectQuery='#oa-rule-'.(int)$_POST['rule_id'];
                    break;
                case 'rule_duplicate':
                    $copyId=$repo->automation()->duplicateRule((int)($_POST['rule_id']??0));
                    $successMessage='Utworzono wstrzymaną kopię automatyzacji. Sprawdź ją i włącz.';
                    $redirectQuery='&rule='.$copyId.'#oa-editor';
                    break;
                case 'rule_delete':
                    $repo->automation()->deleteRule((int)($_POST['rule_id']??0));
                    $successMessage='Usunięto automatyzację i jej dziennik.';
                    break;
                case 'rule_move':
                    $repo->automation()->moveRule((int)($_POST['rule_id']??0),($_POST['direction']??'')==='up'?'up':'down');
                    $successMessage='Zmieniono kolejność wykonywania automatyzacji.';
                    $redirectQuery='#oa-rule-'.(int)($_POST['rule_id']??0);
                    break;
                case 'run_rule':
                    $ids=isset($_POST['ids'])?array_values(array_unique(array_filter(array_map('intval',(array)$_POST['ids'])))):($id>0?[$id]:[]);
                    if (!$ids || count($ids)>50) { throw new InvalidArgumentException('Zaznacz od 1 do 50 zamówień.'); }
                    $button=isset($_POST['rule_group'])?(isset($_POST['ids'])?'list':'order'):'';
                    $report=$repo->automation()->runManual($ids,(int)($_POST['rule_group']??$_POST['rule_id']??0),$actor,$button);
                    if (isset($_POST['ids'])) { $tab='list'; $id=0; } else { $redirectQuery='#om-automation'; }
                    if ($report['errors']) { throw new InvalidArgumentException($report['message']); }
                    $successMessage=$report['message'];
                    break;
                case 'preview_rule':
                    $this->redirect('./index.php?controller=orders&id='.$id.'#om-automation');
                    break;
                case 'seller':
                    $optional=static function (string $key,int $max): string { return mb_substr(trim((string)($_POST[$key]??'')),0,$max,'UTF-8'); };
                    $repo->saveSetting('seller',['name'=>$this->required('name',200),'address'=>$this->required('address',1000),'nip'=>$this->required('nip',30),'bank'=>substr((string)($_POST['bank']??''),0,100),'bank_name'=>$optional('bank_name',100),'swift'=>$optional('swift',11),'email'=>$optional('email',255),'phone'=>$optional('phone',16),'regon'=>$optional('regon',14),'krs'=>$optional('krs',10),'bdo'=>$optional('bdo',9)]);
                    break;
                case 'receipt_printer':
                    (new PrintAgentRepository($db))->ensureSchema();
                    $printerId=(int)($_POST['fiscal_printer_id']??0);
                    if ($printerId>0 && !$db->fetchColumn('SELECT id FROM print_fiscal_printers WHERE id=:id AND enabled=1',['id'=>$printerId])) {
                        throw new InvalidArgumentException('Wybierz aktywną drukarkę z zakładki Drukowanie.');
                    }
                    $repo->saveSetting('receipt_printer',['printer_id'=>$printerId]);
                    $successMessage=$printerId>0?'Zapisano drukarkę do automatycznego druku paragonów.':'Wyłączono automatyczny druk paragonów.';
                    $tab='documents';
                    break;
                case 'series':
                    $kind=(string)($_POST['kind']??''); $numbering=$this->seriesNumbering(); $pattern=$numbering ? $this->seriesPattern($numbering) : $this->required('pattern',100);
                    if (!in_array($kind,['invoice','receipt','invoice_correction','receipt_correction'],true) || strpos($pattern,'{N}')===false || preg_match('/[{}]/',strtr($pattern,['{N}'=>'','{YYYY}'=>'','{MM}'=>'']))) { throw new InvalidArgumentException('Wzór musi zawierać {N}; dostępne także {YYYY} i {MM}.'); }
                    $next=(int)($_POST['next_number']??1);
                    if ($next<1 || $next>100000000) { throw new InvalidArgumentException('Numer początkowy musi być dodatni.'); }
                    $db->insert('om_series',['name'=>$this->required('name',100),'kind'=>$kind,'pattern'=>$pattern,'next_number'=>$next,'fiscal_printer_id'=>$this->seriesPrinterId($db,$kind),'numbering_json'=>$numbering?OrderRepository::json($numbering):null,'document_settings_json'=>OrderRepository::json($this->seriesDocumentSettings($kind))]);
                    break;
                case 'series_update':
                    $seriesId=(int)($_POST['series_id']??0);
                    $existing=$db->fetch('SELECT * FROM om_series WHERE id=:id',['id'=>$seriesId]);
                    if (!$existing) { throw new InvalidArgumentException('Nie znaleziono serii.'); }
                    $kind=(string)($_POST['kind']??''); $numbering=$this->seriesNumbering(); $pattern=$numbering ? $this->seriesPattern($numbering) : $this->required('pattern',100);
                    if (!in_array($kind,['invoice','receipt','invoice_correction','receipt_correction'],true) || strpos($pattern,'{N}')===false || preg_match('/[{}]/',strtr($pattern,['{N}'=>'','{YYYY}'=>'','{MM}'=>'']))) { throw new InvalidArgumentException('Wzór musi zawierać {N}; dostępne także {YYYY} i {MM}.'); }
                    $next=(int)($_POST['next_number']??0);
                    if ($next<1 || $next>100000000) { throw new InvalidArgumentException('Następny numer musi być dodatni.'); }
                    $used=(bool)$db->fetchColumn('SELECT id FROM om_documents WHERE series_id=:id LIMIT 1',['id'=>$seriesId]);
                    if ($used && ($kind!==$existing['kind'] || $next<(int)$existing['next_number'])) { throw new InvalidArgumentException('Dla używanej serii nie można zmienić typu ani cofnąć licznika.'); }
                    $period=$numbering && $used ? (new \DateTimeImmutable('now',new \DateTimeZone('Europe/Warsaw')))->format($numbering['format']==='MONTHLY'?'Y-m':'Y') : null;
                    $db->update('om_series',['name'=>$this->required('name',100),'kind'=>$kind,'pattern'=>$pattern,'next_number'=>$next,'fiscal_printer_id'=>$this->seriesPrinterId($db,$kind),'numbering_json'=>$numbering?OrderRepository::json($numbering):null,'numbering_period'=>$period,'document_settings_json'=>OrderRepository::json($this->seriesDocumentSettings($kind))],'id=:id',['id'=>$seriesId]);
                    $successMessage='Zapisano serię numeracji.';
                    break;
                case 'series_delete':
                    $seriesId=(int)($_POST['series_id']??0);
                    $existingSeries=$db->fetch('SELECT * FROM om_series WHERE id=:id',['id'=>$seriesId]);
                    if (!$existingSeries) { throw new InvalidArgumentException('Nie znaleziono serii.'); }
                    if ($db->fetchColumn('SELECT id FROM om_documents WHERE series_id=:id LIMIT 1',['id'=>$seriesId])) { throw new InvalidArgumentException('Nie można usunąć serii, w której wystawiono już dokumenty.'); }
                    $db->delete('om_series','id=:id',['id'=>$seriesId]);
                    $successMessage='Usunięto serię „'.$existingSeries['name'].'”.';
                    break;
                case 'document_update':
                    $documentId=(int)($_POST['document_id']??0);
                    $document=$db->fetch('SELECT * FROM om_documents WHERE id=:id',['id'=>$documentId]);
                    if (!$document) { throw new InvalidArgumentException('Nie znaleziono dokumentu.'); }
                    if ($ksefLock=$this->ksef($repo)->lockReason($documentId)) { throw new InvalidArgumentException($ksefLock); }
                    $buyer=trim((string)($_POST['buyer']??''));
                    if ($buyer==='') { throw new InvalidArgumentException('Uzupełnij dane nabywcy.'); }
                    $calculated=OrderDocumentService::calculate($_POST['items']??[]);
                    $old=json_decode($document['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
                    $recipient=trim((string)($_POST['recipient']??''));
                    $additionalInfo=trim((string)($_POST['additional_info']??''));
                    if (mb_strlen($buyer)>2000 || mb_strlen($recipient)>2000) { throw new InvalidArgumentException('Dane nabywcy lub dostawy są za długie.'); }
                    if (mb_strlen($additionalInfo)>2000) { throw new InvalidArgumentException('Dodatkowa informacja jest za długa (maks. 2000 znaków).'); }
                    $snapshot=array_replace($old,$calculated,['buyer'=>$buyer,'recipient'=>$recipient,'additional_info'=>$additionalInfo]);
                    if (isset($old['before'])) { $snapshot['before']=$old['before']; $snapshot['difference_cents']=$snapshot['gross_cents']-(int)$old['before']['gross_cents']; $snapshot['difference_net_cents']=$snapshot['net_cents']-(int)$old['before']['net_cents']; $snapshot['difference_tax_cents']=$snapshot['tax_cents']-(int)$old['before']['tax_cents']; $snapshot['parent_number']=$old['parent_number']; }
                    $snapshot['edited_at']=gmdate('Y-m-d H:i:s');
                    $db->update('om_documents',['snapshot_json'=>OrderRepository::json($snapshot)],'id=:id',['id'=>$documentId]);
                    $repo->event((int)$document['order_id'],'Zaktualizowano dokument '.$document['number'].' (edycja nabywcy, dostawy lub pozycji).',$actor);
                    $successMessage='Zaktualizowano dokument '.$document['number'].'.';
                    break;
                case 'document_delete':
                    $documentId=(int)($_POST['document_id']??0);
                    $document=$db->fetch('SELECT * FROM om_documents WHERE id=:id',['id'=>$documentId]);
                    if (!$document) { throw new InvalidArgumentException('Nie znaleziono dokumentu.'); }
                    if ($db->fetchColumn('SELECT id FROM om_documents WHERE parent_id=:id LIMIT 1',['id'=>$documentId])) { throw new InvalidArgumentException('Nie można usunąć dokumentu, do którego wystawiono korektę. Usuń najpierw korektę.'); }
                    if ($ksefLock=$this->ksef($repo)->lockReason($documentId)) { throw new InvalidArgumentException($ksefLock); }
                    $db->delete('om_documents','id=:id',['id'=>$documentId]);
                    $repo->event((int)$document['order_id'],'Usunięto dokument '.$document['number'].'.',$actor);
                    $successMessage='Usunięto dokument '.$document['number'].'.';
                    break;
                case 'document':
                    $documentSeries=$db->fetch('SELECT * FROM om_series WHERE id=:id',['id'=>(int)($_POST['series_id']??0)]);
                    $receiptPrinterId=$documentSeries && $documentSeries['kind']==='receipt'?$this->receiptPrinterId($documentSeries,$repo):0;
                    if ($receiptPrinterId>0) {
                        (new PrintAgentRepository($db))->ensureSchema();
                        if (!$db->fetchColumn('SELECT id FROM print_fiscal_printers WHERE id=:id AND enabled=1',['id'=>$receiptPrinterId])) {
                            throw new InvalidArgumentException('Przypisana drukarka paragonów jest nieaktywna. Zmień ją w zakładce Dokumenty.');
                        }
                    }
                    $doc=(new OrderDocumentService($repo))->issue($id,$_POST,$actor);
                    $this->ksefAutoSend($repo,$doc,$actor);
                    if ($receiptPrinterId>0) {
                        try { $fiscalJobId=(new PrintAgentRepository($db))->queueFiscalReceipt($id,$receiptPrinterId,$actor,$doc); }
                        catch (\Throwable $printError) { throw new InvalidArgumentException('Paragon został wystawiony, ale nie trafił do drukarki: '.$printError->getMessage(),0,$printError); }
                        $repo->event($id,'Automatycznie dodano wystawiony paragon do kolejki drukarki Posnet (zadanie '.$fiscalJobId.').',$actor);
                    }
                    $this->redirect('./index.php?controller=orders&action=printdocument&id='.$doc);
                    break;
                case 'quick_document':
                    $kind=(string)($_POST['kind']??'');
                    if (!in_array($kind,['invoice','receipt'],true)) { throw new InvalidArgumentException('Wybierz fakturę albo paragon.'); }
                    if ($db->fetchColumn('SELECT id FROM om_documents WHERE order_id=:order_id AND kind=:kind LIMIT 1',['order_id'=>$id,'kind'=>$kind])) {
                        throw new InvalidArgumentException($kind==='receipt'?'Paragon dla tego zamówienia został już wystawiony.':'Faktura dla tego zamówienia została już wystawiona.');
                    }
                    $series=!empty($_POST['series_id'])?$db->fetch('SELECT * FROM om_series WHERE id=:id AND kind=:kind',['id'=>(int)$_POST['series_id'],'kind'=>$kind]):$this->documentSeries($db,$kind);
                    if (!$series) { throw new InvalidArgumentException('Wybierz serię właściwego typu.'); }
                    $seriesSettings=json_decode((string)($series['document_settings_json']??''),true)?:[];
                    $receiptPrinterId=0;
                    if ($kind==='receipt' && empty($seriesSettings['non_fiscal'])) {
                        $receiptPrinterId=$this->receiptPrinterId($series,$repo);
                        if ($receiptPrinterId>0) { (new PrintAgentRepository($db))->ensureSchema(); }
                        if ($receiptPrinterId>0 && !$db->fetchColumn('SELECT id FROM print_fiscal_printers WHERE id=:id AND enabled=1',['id'=>$receiptPrinterId])) {
                            throw new InvalidArgumentException('Przypisana drukarka paragonów jest nieaktywna. Zmień ją w zakładce Dokumenty.');
                        }
                    }
                    $doc=(new OrderDocumentService($repo))->issue($id,$this->documentPayload($repo,$repo->order($id),$kind,(string)($_POST['request_key']??''),$series)+['series_id'=>$series['id']],$actor);
                    $this->ksefAutoSend($repo,$doc,$actor);
                    if ($kind==='receipt' && $receiptPrinterId>0) {
                        $printAgents=new PrintAgentRepository($db); $printAgents->ensureSchema();
                        try { $fiscalJobId=$printAgents->queueFiscalReceipt($id,$receiptPrinterId,$actor,$doc); }
                        catch (\Throwable $printError) { throw new InvalidArgumentException('Paragon został wystawiony, ale nie trafił do drukarki: '.$printError->getMessage(),0,$printError); }
                        $repo->event($id,'Automatycznie dodano wystawiony paragon do kolejki drukarki Posnet (zadanie '.$fiscalJobId.').',$actor);
                    }
                    $this->redirect('./index.php?controller=orders&action=printdocument&id='.$doc);
                    break;
                case 'quick_correction':
                    $parent=$db->fetch('SELECT * FROM om_documents WHERE id=:id AND order_id=:order_id',['id'=>(int)($_POST['parent_id']??0),'order_id'=>$id]);
                    if (!$parent || !in_array($parent['kind'],['invoice','receipt'],true)) { throw new InvalidArgumentException('Wybierz fakturę albo paragon do korekty.'); }
                    $reason=$this->required('reason',500);
                    $kind=$parent['kind'].'_correction';
                    $parentSeries=$db->fetch('SELECT document_settings_json FROM om_series WHERE id=:id',['id'=>$parent['series_id']]);
                    $parentSettings=json_decode((string)($parentSeries['document_settings_json']??''),true)?:[];
                    $series=!empty($parentSettings['correct_series_id'])?$db->fetch('SELECT * FROM om_series WHERE id=:id AND kind=:kind',['id'=>(int)$parentSettings['correct_series_id'],'kind'=>$kind]):null;
                    $series=$series?:$this->documentSeries($db,$kind);
                    $payload=$this->documentPayload($repo,$repo->order($id),$parent['kind'],(string)($_POST['request_key']??''),$series);
                    $payload+=['series_id'=>$series['id'],'parent_id'=>$parent['id'],'reason'=>$reason];
                    $doc=(new OrderDocumentService($repo))->issue($id,$payload,$actor);
                    $this->ksefAutoSend($repo,$doc,$actor);
                    $this->redirect('./index.php?controller=orders&action=printdocument&id='.$doc);
                    break;
                case 'document_correction':
                    $parent=$db->fetch('SELECT id,kind,order_id FROM om_documents WHERE id=:id AND order_id=:order_id',['id'=>(int)($_POST['parent_id']??0),'order_id'=>$id]);
                    if (!$parent || !in_array($parent['kind'],['invoice','receipt','invoice_correction','receipt_correction'],true)) { throw new InvalidArgumentException('Wybierz istniejący dokument do korekty.'); }
                    $kind=strpos($parent['kind'],'invoice')===0?'invoice_correction':'receipt_correction';
                    $seriesId=(int)($_POST['series_id']??0);
                    $series=$seriesId>0?$db->fetch('SELECT id FROM om_series WHERE id=:id AND kind=:kind',['id'=>$seriesId,'kind'=>$kind]):$this->documentSeries($db,$kind);
                    if (!$series) { throw new InvalidArgumentException('Wybierz serię korekt właściwego typu.'); }
                    $payload=$_POST; $payload['series_id']=$series['id']; $payload['parent_id']=$parent['id'];
                    $doc=(new OrderDocumentService($repo))->issue($id,$payload,$actor);
                    $this->ksefAutoSend($repo,$doc,$actor);
                    $this->redirect('./index.php?controller=orders&action=printdocument&id='.$doc);
                    break;
                case 'ksef_account':
                    $ksefAccountId=(int)($_POST['ksef_account_id']??0);
                    $tab='general'; $redirectQuery='#om-ksef';
                    $savedAccountId=$this->ksef($repo)->saveAccount($ksefAccountId,$_POST,$actor);
                    $successMessage=$ksefAccountId>0?'Zapisano konto KSeF.':'Dodano konto KSeF. Przypisz je do serii faktur w zakładce Dokumenty.';
                    $redirectQuery='#om-ksef-'.$savedAccountId;
                    break;
                case 'ksef_account_delete':
                    $tab='general'; $redirectQuery='#om-ksef';
                    $successMessage='Usunięto konto KSeF „'.$this->ksef($repo)->deleteAccount((int)($_POST['ksef_account_id']??0)).'”.';
                    break;
                case 'ksef_test':
                    $tab='general'; $redirectQuery='#om-ksef-'.(int)($_POST['ksef_account_id']??0);
                    $successMessage=$this->ksef($repo)->testConnection((int)($_POST['ksef_account_id']??0));
                    break;
                case 'ksef_send':
                case 'ksef_refresh':
                    $documentId=(int)($_POST['document_id']??0);
                    $ksef=$this->ksef($repo);
                    $submission=$op==='ksef_send'?$ksef->send($documentId,$actor):$ksef->refresh($documentId,$actor);
                    $successMessage=KsefService::describe($submission).(!empty($submission['upo_error'])?' Nie pobrano UPO: '.$submission['upo_error']:'');
                    if ($submission['state']==='rejected') { throw new InvalidArgumentException($successMessage); }
                    break;
                case 'create_shipment':
                    (new OrderShipmentService($repo))->create($id,(int)($_POST['carrier_account_id']??0),$_POST,$actor);
                    break;
                case 'refresh_shipment':
                    (new OrderShipmentService($repo))->refresh((int)($_POST['shipment_id']??0),$actor);
                    break;
                case 'publish_shipment':
                    $successMessage=(new OrderMarketplaceShipmentService($repo))->publishShipment((int)($_POST['shipment_id']??0),(string)($_POST['source_carrier']??''),(string)($_POST['source_carrier_other']??''),$actor);
                    break;
                case 'cancel_shipment':
                    (new OrderShipmentService($repo))->cancel((int)($_POST['shipment_id']??0),$actor);
                    break;
                case 'delete_shipment':
                    (new OrderShipmentService($repo))->deleteLocal((int)($_POST['shipment_id']??0),$actor);
                    break;
                case 'queue_label':
                    $printAgents=new PrintAgentRepository($db); $printAgents->ensureSchema();
                    [$stationId,$printerName]=array_pad(explode('|',(string)($_POST['printer_target']??''),2),2,'');
                    $width=(float)str_replace(',','.',(string)($_POST['label_width_mm']??'100'));
                    $height=(float)str_replace(',','.',(string)($_POST['label_height_mm']??'150'));
                    $scope=(string)($_POST['label_scope']??'shipment');
                    if ($scope==='shipment') {
                        $shipmentId=(int)($_POST['shipment_id']??0);
                        $jobIds=[$printAgents->queueShipmentLabel($shipmentId,(int)$stationId,$printerName,$width,$height,$actor,$this->printAgentApiBase())];
                        $orderId=(int)$db->fetchColumn('SELECT order_id FROM om_shipments WHERE id=:id',['id'=>$shipmentId]);
                    } else {
                        $orderId=(int)($_POST['order_id']??0);
                        $jobIds=$printAgents->queueOrderLabels($orderId,$scope,(int)$stationId,$printerName,$width,$height,$actor,$this->printAgentApiBase());
                    }
                    if ($orderId>0) { $repo->event($orderId,'Dodano '.count($jobIds).' etykiet do kolejki druku ('.implode(', ',$jobIds).').',$actor); $id=$orderId; }
                    $successMessage=count($jobIds)===1?'Etykieta trafiła do kolejki wybranej drukarki.':'Dodano '.count($jobIds).' etykiet do kolejki wybranej drukarki.';
                    break;
                case 'print_station_create':
                    $printAgents=new PrintAgentRepository($db); $printAgents->ensureSchema();
                    $plainToken=$printAgents->createStation($this->required('station_name',150));
                    $this->setFlash('print_token',$plainToken);
                    $successMessage='Stanowisko utworzone. Skopiuj token z pola w sekcji „Podłącz agent”.';
                    $tab='printing';
                    break;
                case 'print_station_toggle':
                    $printAgents=new PrintAgentRepository($db); $printAgents->ensureSchema();
                    $printAgents->setStationEnabled((int)($_POST['station_id']??0),!empty($_POST['enabled']));
                    $successMessage=!empty($_POST['enabled'])?'Włączono stanowisko druku.':'Wyłączono stanowisko druku.';
                    $tab='printing';
                    break;
                case 'print_station_token':
                    $printAgents=new PrintAgentRepository($db); $printAgents->ensureSchema();
                    $plainToken=$printAgents->regenerateToken((int)($_POST['station_id']??0));
                    $this->setFlash('print_token',$plainToken);
                    $successMessage='Wygenerowano nowy token; poprzedni przestał działać. Skopiuj go z pola w sekcji „Podłącz agent”.';
                    $tab='printing';
                    break;
                case 'print_station_delete':
                    $printAgents=new PrintAgentRepository($db); $printAgents->ensureSchema();
                    $printAgents->deleteStation((int)($_POST['station_id']??0));
                    $successMessage='Agent został usunięty, jego token unieważniono, a zakończoną historię druku usunięto.';
                    $tab='printing';
                    break;
                case 'fiscal_printer_configure':
                    $printAgents=new PrintAgentRepository($db); $printAgents->ensureSchema();
                    $printAgents->configureFiscalPrinter((int)($_POST['fiscal_printer_id']??0),(string)($_POST['receipt_series']??''),(string)($_POST['environment']??'sandbox'),!empty($_POST['enabled']));
                    $successMessage='Zapisano ustawienia drukarki fiskalnej.'; $tab='printing';
                    break;
                case 'fiscal_printer_add':
                    $printAgents=new PrintAgentRepository($db); $printAgents->ensureSchema();
                    $printAgents->addFiscalPrinter((int)($_POST['station_id']??0),$this->required('fiscal_printer_name',150),$this->required('fiscal_printer_host',255),(int)($_POST['fiscal_printer_port']??6666));
                    $successMessage='Dodano drukarkę fiskalną. Ustaw serię, tryb i włącz urządzenie.'; $tab='printing';
                    break;
                case 'fiscal_printer_delete':
                    $printAgents=new PrintAgentRepository($db); $printAgents->ensureSchema();
                    $printAgents->deleteFiscalPrinter((int)($_POST['fiscal_printer_id']??0));
                    $successMessage='Usunięto drukarkę fiskalną i odłączono ją od serii paragonów. Historia zakończonych zadań została zachowana.'; $tab='printing';
                    break;
                case 'carrier_account':
                    $provider=(string)($_POST['provider']??'');
                    if (!ShippingProviders::exists($provider)) { throw new InvalidArgumentException('Nieznany operator przesyłek.'); }
                    $carrierId=(int)($_POST['carrier_account_id']??0);
                    $redirectQuery=$carrierId?'&carrier='.$carrierId:'&add='.rawurlencode($provider);
                    $name=$this->required('name',150);
                    $existingSecret=[];
                    if ($carrierId) {
                        $current=$db->fetch('SELECT * FROM om_carrier_accounts WHERE id=:id AND provider=:p',['id'=>$carrierId,'p'=>$provider]);
                        if (!$current) { throw new InvalidArgumentException('Nie znaleziono konta nadawczego.'); }
                        $existingSecret=\App\Services\OrderSecretBox::decrypt($current['secret_config_json']);
                    }
                    $config=ShippingProviders::get($repo,$provider)->configure($_POST,$existingSecret);
                    $cipher=\App\Services\OrderSecretBox::encrypt($config['secret']);
                    $public=$config['public'];
                    $carrierId=$db->transaction(function () use ($db,$provider,$name,$public,$cipher,$carrierId) {
                        $existing=$db->fetch('SELECT id FROM om_carrier_accounts WHERE provider=:p AND name=:n',['p'=>$provider,'n'=>$name]);
                        if ($carrierId && $existing && (int)$existing['id']!==$carrierId) { throw new InvalidArgumentException('Konto nadawcze o tej nazwie już istnieje.'); }
                        $data=['public_config_json'=>OrderRepository::json($public),'secret_config_json'=>$cipher,'updated_at'=>gmdate('Y-m-d H:i:s')];
                        if ($carrierId) { $db->update('om_carrier_accounts',$data+['name'=>$name],'id=:id',['id'=>$carrierId]); return $carrierId; }
                        if ($existing) { $db->update('om_carrier_accounts',$data+['enabled'=>1],'id=:id',['id'=>$existing['id']]); return (int)$existing['id']; }
                        return (int)$db->insert('om_carrier_accounts',$data+['enabled'=>1,'provider'=>$provider,'name'=>$name]);
                    });
                    $db->delete('om_settings','setting_key=:k',['k'=>'carrier_services_'.(int)$carrierId]);
                    $redirectQuery='&carrier='.(int)$carrierId;
                    $successMessage='Zapisano konto '.ShippingProviders::definition($provider)['label'].' „'.$name.'”.';
                    break;
                case 'carrier_account_delete':
                    $carrierId=(int)($_POST['carrier_account_id']??0);
                    $redirectQuery='&carrier='.$carrierId;
                    if (!$db->fetchColumn('SELECT id FROM om_carrier_accounts WHERE id=:id',['id'=>$carrierId])) { throw new InvalidArgumentException('Nie znaleziono konta nadawczego.'); }
                    if ((int)$db->fetchColumn('SELECT COUNT(*) FROM om_shipments WHERE carrier_account_id=:id',['id'=>$carrierId])>0) { throw new InvalidArgumentException('To konto ma już przesyłki – wyłącz je zamiast odłączać, aby zachować statusy i etykiety.'); }
                    $db->delete('om_carrier_accounts','id=:id',['id'=>$carrierId]);
                    $db->delete('om_settings','setting_key=:k',['k'=>'carrier_services_'.$carrierId]);
                    $redirectQuery='';
                    $successMessage='Odłączono konto nadawcze.';
                    break;
                case 'carrier_account_toggle':
                    $carrierId=(int)($_POST['carrier_account_id']??0);
                    $enabled=!empty($_POST['enabled'])?1:0;
                    if (!$db->fetchColumn('SELECT id FROM om_carrier_accounts WHERE id=:id',['id'=>$carrierId])) { throw new InvalidArgumentException('Nie znaleziono konta nadawczego.'); }
                    $db->update('om_carrier_accounts',['enabled'=>$enabled,'updated_at'=>gmdate('Y-m-d H:i:s')],'id=:id',['id'=>$carrierId]);
                    $redirectQuery='&carrier='.$carrierId;
                    $successMessage=$enabled?'Włączono konto nadawcze.':'Wyłączono konto nadawcze. Istniejące przesyłki nadal możesz odświeżać i drukować.';
                    break;
                case 'shipping_defaults':
                    $accountId=(int)($_POST['default_carrier_account_id']??0);
                    if ($accountId && !$db->fetchColumn('SELECT id FROM om_carrier_accounts WHERE id=:id AND enabled=1',['id'=>$accountId])) { throw new InvalidArgumentException('Wybierz aktywne konto nadawcze.'); }
                    $number=static function (array $source,string $key,float $min,float $max): float { $value=filter_var($source[$key]??null,FILTER_VALIDATE_FLOAT); if ($value===false||$value<$min||$value>$max) { [$size,$field]=array_pad(explode('_',$key,2),2,''); throw new InvalidArgumentException('Sprawdź '.(['length'=>'długość','width'=>'szerokość','height'=>'wysokość','weight'=>'wagę'][$field]??$key).' w presecie „'.(['small'=>'Mała','medium'=>'Średnia','large'=>'Duża'][$size]??$size).'” (dozwolone '.$min.'–'.$max.').'); } return (float)$value; };
                    $presets=[];
                    foreach (['small','medium','large'] as $size) { $presets[$size]=['length'=>$number($_POST,$size.'_length',1,400),'width'=>$number($_POST,$size.'_width',1,400),'height'=>$number($_POST,$size.'_height',1,400),'weight'=>$number($_POST,$size.'_weight',0.01,1000)]; }
                    $defaultPackage=(string)($_POST['default_package']??'auto'); if (!in_array($defaultPackage,['auto','small','medium','large'],true)) { $defaultPackage='auto'; }
                    $service=(string)($_POST['service']??'auto'); if (!in_array($service,['auto','inpost_locker_standard','inpost_courier_standard'],true)) { $service='auto'; }
                    $bankAccount=preg_replace('/\s+/','',trim((string)($_POST['cod_bank_account']??'')))??'';
                    if (strncasecmp($bankAccount,'PL',2)===0) { $bankAccount=substr($bankAccount,2); }
                    if ($bankAccount!=='' && !preg_match('/^\d{26}$/D',$bankAccount)) { throw new InvalidArgumentException('Numer konta pobrania musi zawierać 26 cyfr (prefiks PL możesz pominąć).'); }
                    $repo->saveSetting('shipping_defaults',['default_carrier_account_id'=>$accountId,'default_package'=>$defaultPackage,'service'=>$service,'apaczka_service_id'=>(int)($_POST['apaczka_service_id']??0),'pickup_type'=>($_POST['pickup_type']??'SELF')==='COURIER'?'COURIER':'SELF','default_point'=>substr(trim((string)($_POST['default_point']??'')),0,100),'content'=>mb_substr(trim((string)($_POST['shipment_content']??'')),0,180,'UTF-8'),'cod_bank_account'=>$bankAccount,'presets'=>$presets,'sender'=>['name'=>substr(trim((string)($_POST['sender_name']??'')),0,150),'email'=>substr(trim((string)($_POST['sender_email']??'')),0,200),'phone'=>substr(trim((string)($_POST['sender_phone']??'')),0,30),'street'=>substr(trim((string)($_POST['sender_street']??'')),0,150),'building'=>substr(trim((string)($_POST['sender_building']??'')),0,30),'postal_code'=>substr(trim((string)($_POST['sender_postal_code']??'')),0,20),'city'=>substr(trim((string)($_POST['sender_city']??'')),0,100)]]);
                    $repo->saveSetting('document_defaults',['vat'=>in_array((string)($_POST['default_vat']??'23'),['23','8','5','0','zw','np'],true)?(string)$_POST['default_vat']:'23']);
                    break;
                default: throw new InvalidArgumentException('Nieznana operacja.');
            }
            $repo->flushAutomations();
            $this->setFlash('success',$successMessage);
        } catch (\Throwable $e) {
            $repo->discardAutomations();
            $safeMessage=$e instanceof InvalidArgumentException ? $e->getMessage() : 'Nie udało się zapisać. Sprawdź, czy numer dokumentu lub przesyłki nie został już użyty.';
            if ($op==='publish_shipment') {
                $diagnostic=\App\Services\OrderSyncError::log($e,['stage'=>'source_shipment','order_id'=>$id]);
                $reason=mb_substr(trim((string)$e->getMessage()),0,300,'UTF-8');
                $safeMessage='Nie udało się przekazać numeru przesyłki do źródła. '.$reason.' [ID: '.$diagnostic['reference'].']';
            } elseif (in_array($op,['ksef_test','ksef_send','ksef_refresh'],true) && !$e instanceof InvalidArgumentException) {
                $diagnostic=\App\Services\OrderSyncError::log($e,['stage'=>'ksef','order_id'=>$id]);
                $safeMessage='Operacja KSeF nie powiodła się: '.mb_substr(trim($e->getMessage()),0,300,'UTF-8').' [ID: '.$diagnostic['reference'].']';
            } elseif ($op==='carrier_account' && !$e instanceof InvalidArgumentException) {
                $diagnostic=\App\Services\OrderSyncError::log($e,['stage'=>'shipment']);
                $safeMessage='Nie udało się sprawdzić konta u operatora. '.$diagnostic['message'].' [ID: '.$diagnostic['reference'].']';
            } elseif (in_array($op,['create_shipment','refresh_shipment','cancel_shipment'],true)) {
                $diagnostic=\App\Services\OrderSyncError::log($e,['stage'=>'shipment','order_id'=>$id]);
                $reason=$e instanceof InvalidArgumentException ? $e->getMessage() : $diagnostic['message'];
                $safeMessage='Nie udało się obsłużyć przesyłki. '.$reason.' [ID: '.$diagnostic['reference'].']';
            }
            $this->setFlash('error',$safeMessage);
        }
        if ($op==='document_correction' && !empty($_POST['parent_id'])) { $this->redirect('./index.php?controller=orders&action=correctdocument&id='.(int)$_POST['parent_id']); }
        $this->redirect('./index.php?controller=orders&tab='.rawurlencode($tab).($id?'&id='.$id:'').$redirectQuery);
    }
    private function required(string $key,int $limit): string
    {
        $value=trim((string)($_POST[$key]??''));
        if ($value==='' || mb_strlen($value)>$limit) { throw new InvalidArgumentException('Uzupełnij pole '.$key.' (maks. '.$limit.' znaków).'); }
        return $value;
    }
    private function printAgentApiBase(): string
    {
        return PrintAgentRepository::apiBase();
    }
    private function documentSeries($db,string $kind): array
    {
        return OrderDocumentService::defaultSeries($db,$kind);
    }
    private function seriesNumbering(): ?array
    {
        if ((string)($_POST['numbering_mode']??'custom')!=='standard') { return null; }
        $format=(string)($_POST['numbering_format']??'');
        $start=(int)($_POST['numbering_start']??0);
        $length=(int)($_POST['document_number_length']??0);
        $prefix=trim((string)($_POST['prefix']??'')); $suffix=trim((string)($_POST['suffix']??''));
        $notes=trim((string)($_POST['additional_text']??''));
        $color=(string)($_POST['color_series']??'#64748b');
        if (!in_array($format,['MONTHLY','YEARLY'],true) || $start<1 || $start>100000000 || $length<0 || $length>8 || mb_strlen($prefix)>20 || mb_strlen($suffix)>20 || mb_strlen($notes)>2000 || !preg_match('/^#[0-9a-fA-F]{6}$/D',$color) || preg_match('/[{}\x00-\x1f]/',$prefix.$suffix)) { throw new InvalidArgumentException('Sprawdź ustawienia numeracji serii.'); }
        return ['format'=>$format,'reset'=>!empty($_POST['reset_numbering']),'start'=>$start,'length'=>$length,'prefix'=>$prefix,'suffix'=>$suffix,'color'=>$color,'notes'=>$notes];
    }
    private function seriesDocumentSettings(string $kind): array
    {
        $choices=['sale_date_source'=>['order','payment','issue_date'],'vat_source'=>['order','static'],'vat_rate'=>['23','8','5','0','zw','np'],'shipment_vat_type'=>['order','static'],'shipment_vat'=>['23','8','5','0','zw','np'],'payment_term_days'=>['0','3','5','7','10','14','21','30','45','60','90','120','365'],'split_payment'=>['0','1']];
        $defaults=['sale_date_source'=>'order','vat_source'=>'order','vat_rate'=>'23','shipment_vat_type'=>'order','shipment_vat'=>'23','payment_term_days'=>'0','split_payment'=>'0'];
        $settings=[];
        foreach ($choices as $key=>$allowed) {
            $value=(string)($_POST[$key]??$defaults[$key]);
            if (!in_array($value,$allowed,true)) { throw new InvalidArgumentException('Nieprawidłowe ustawienie serii: '.$key); }
            $settings[$key]=$value;
        }
        foreach (['shipment_name'=>100,'seller_name'=>200,'seller_address'=>1000,'seller_nip'=>30,'seller_bank'=>200,'seller_bank_name'=>100,'seller_swift'=>11,'seller_email'=>255,'seller_phone'=>16,'seller_regon'=>14,'seller_krs'=>10,'seller_bdo'=>9] as $key=>$max) {
            $value=trim((string)($_POST[$key]??''));
            if (mb_strlen($value)>$max) { throw new InvalidArgumentException('Za długa wartość pola: '.$key); }
            $settings[$key]=$value;
        }
        $settings['shipment_name']=$settings['shipment_name']?:'Dostawa';
        foreach (['add_shipment_name','buyer_validation_disabled'] as $key) { $settings[$key]=!empty($_POST[$key]); }
        $settings['non_fiscal']=!empty($_POST['non_fiscal']) && $kind==='receipt';
        $correctionId=(int)($_POST['correct_series_id']??0);
        if ($correctionId>0) {
            $correctKind=$kind==='invoice'?'invoice_correction':($kind==='receipt'?'receipt_correction':'');
            if ($correctKind==='' || !$this->db()->fetchColumn('SELECT id FROM om_series WHERE id=:id AND kind=:kind',['id'=>$correctionId,'kind'=>$correctKind])) { throw new InvalidArgumentException('Wybierz serię korekt właściwego typu.'); }
        }
        $settings['correct_series_id']=$correctionId;
        $ksefAccountId=in_array($kind,['invoice','invoice_correction'],true)?(int)($_POST['ksef_account_id']??0):0;
        if ($ksefAccountId>0) {
            $this->ksef($this->repository());
            if (!$this->db()->fetchColumn('SELECT id FROM om_ksef_accounts WHERE id=:id',['id'=>$ksefAccountId])) { throw new InvalidArgumentException('Wybierz istniejące konto KSeF.'); }
        }
        $settings['ksef_account_id']=$ksefAccountId;
        return $settings;
    }
    private function seriesPattern(array $numbering): string
    {
        $pattern=($numbering['prefix']!==''?$numbering['prefix'].'/':'').'{N}/'.($numbering['format']==='MONTHLY'?'{MM}/':'').'{YYYY}'.($numbering['suffix']!==''?'/'.$numbering['suffix']:'');
        if (strlen($pattern)>100) { throw new InvalidArgumentException('Wzór numeru jest za długi.'); }
        return $pattern;
    }
    private function seriesPrinterId($db,string $kind): int
    {
        $printerId=(int)($_POST['fiscal_printer_id']??0);
        if ($printerId<0 || ($printerId>0 && $kind!=='receipt')) { throw new InvalidArgumentException('Drukarkę można przypisać tylko do serii paragonów.'); }
        if ($printerId>0) {
            (new PrintAgentRepository($db))->ensureSchema();
            if (!$db->fetchColumn('SELECT id FROM print_fiscal_printers WHERE id=:id AND enabled=1',['id'=>$printerId])) { throw new InvalidArgumentException('Wybierz aktywną drukarkę z zakładki Drukowanie.'); }
        }
        return $printerId;
    }
    private function receiptPrinterId(array $series,OrderRepository $repo): int
    {
        return $series['fiscal_printer_id']===null?(int)($repo->setting('receipt_printer')['printer_id']??0):(int)$series['fiscal_printer_id'];
    }
    private function documentPayload(OrderRepository $repo,array $order,string $buyerKind,string $requestKey,array $series=[]): array
    {
        return OrderDocumentService::orderPayload($repo,$order,$buyerKind,$requestKey,$series);
    }
    private function shippingDefaults(OrderRepository $repo): array
    {
        return OrderShipmentService::defaults($repo);
    }
    private function allegroSellerId(OrderRepository $repo,int $sourceId): string
    {
        if ($sourceId<1) { return ''; }
        $cache=$repo->setting('allegro_seller_ids');
        $cached=trim((string)($cache[(string)$sourceId]??''));
        if (preg_match('/^\d{1,30}$/D',$cached)) { return $cached; }
        try { $sellerId=(new \App\Services\AllegroService(true))->sellerIdForAccount($sourceId); }
        catch (\Throwable $error) { return ''; }
        if ($sellerId!=='') { $cache[(string)$sourceId]=$sellerId;$repo->saveSetting('allegro_seller_ids',$cache); }
        return $sellerId;
    }
    /**
     * Karta „Płatność” w szczegółach zamówienia: kto przyjął pieniądze, kiedy płatność
     * została zaksięgowana wg źródła i skąd system wie, że zamówienie jest opłacone.
     */
    private function paymentInfo(array $detail,array $events): array
    {
        $platform=(string)$detail['platform'];
        $raw=is_array($detail['details']['raw']??null)?$detail['details']['raw']:[];
        $payment=is_array($raw['payment']??null)?$raw['payment']:[];
        $cod=!empty($detail['details']['cash_on_delivery']);
        $date=static function ($value,bool $utc=true): string {
            $value=trim((string)$value);
            if ($value==='') { return ''; }
            try { return (new \DateTimeImmutable($value,new \DateTimeZone($utc?'UTC':'Europe/Warsaw')))->setTimezone(new \DateTimeZone('Europe/Warsaw'))->format('Y-m-d H:i'); }
            catch (\Exception $e) { return $value; }
        };
        $labels=['allegro'=>'Allegro','erli'=>'ERLI','empik'=>'Empik','mediamarkt'=>'MediaMarkt','temu'=>'Temu','morele'=>'Morele','woocommerce'=>'WooCommerce','prestashop'=>'PrestaShop','altreo'=>'Altreo.pl','manual'=>'Zamówienie własne'];
        $platformLabel=$labels[$platform]??ucfirst($platform);
        $info=['collector'=>'','operator'=>'','source_method'=>trim((string)($detail['details']['source_payment_method']??'')),'transaction_id'=>'','source_status'=>'','booked_at'=>'','booked_label'=>'Zaksięgowano w źródle','note'=>''];
        if ($platform==='allegro') {
            $providers=['P24'=>'Przelewy24','PAYU'=>'PayU','AF'=>'Allegro Finance','OFFLINE'=>'Poza Allegro (przy odbiorze)'];
            $types=['ONLINE'=>'Płatność online','CASH_ON_DELIVERY'=>'Za pobraniem','SPLIT_PAYMENT'=>'Płatność dzielona','EXTENDED_TERM'=>'Allegro Pay (odroczona)'];
            $provider=strtoupper((string)($payment['provider']??''));
            $type=strtoupper((string)($payment['type']??''));
            $info['collector']=$cod?'Kurier (pobranie)':'Allegro — Płatności Allegro';
            $info['operator']=$providers[$provider]??(string)($payment['provider']??'');
            $info['source_method']=$types[$type]??$info['source_method'];
            $info['transaction_id']=(string)($payment['id']??'');
            $info['source_status']=(string)($raw['status']??'');
            $info['booked_at']=$date($payment['finishedAt']??'');
            if (isset($payment['paidAmount']['amount'])) { $info['note']='Allegro potwierdza wpłatę '.$payment['paidAmount']['amount'].' '.($payment['paidAmount']['currency']??$detail['currency']).'.'; }
        } elseif ($platform==='erli') {
            $info['collector']=$cod?'Kurier (pobranie)':'ERLI — płatność przez marketplace';
            $info['operator']=(string)($payment['operator']??'');
            $info['source_method']=trim((string)($payment['methodName']??''))?:$info['source_method'];
            $info['transaction_id']=(string)($payment['id']??'');
            $info['source_status']=(string)($payment['status']??'');
            $info['booked_at']=$date($payment['completedAt']??'');
        } elseif (in_array($platform,['empik','mediamarkt'],true)) {
            $info['collector']=$cod?'Kurier (pobranie)':$platformLabel.' — rozliczenie przez marketplace (Mirakl)';
            $info['operator']=(string)($raw['payment_type']??$raw['paymentType']??'');
            $info['transaction_id']=(string)($raw['transaction_number']??'');
            $info['source_status']=trim((string)($raw['payment_workflow']??''));
            $info['booked_at']=$date($raw['customer_debited_date']??'');
            $info['booked_label']='Obciążono klienta';
            if (!empty($raw['transaction_date'])) { $info['note']='Data transakcji w '.$platformLabel.': '.$date($raw['transaction_date']).'.'; }
        } elseif ($platform==='temu') {
            $info['collector']=$cod?'Kurier (pobranie)':'Temu — płatność przyjmuje marketplace';
        } elseif ($platform==='manual') {
            $info['collector']='Rozliczenie poza marketplace';
        } else {
            $info['collector']=$cod?'Kurier (pobranie)':'Sklep '.$platformLabel;
        }
        // Skąd system wie o opłaceniu: ręczna zmiana ma pierwszeństwo przed synchronizacją.
        $manual=is_array($detail['details']['_manual']??null)?$detail['details']['_manual']:[];
        $confirmedBy=''; $confirmedAt='';
        if (array_key_exists('paid',(array)($manual['order']??[]))) {
            $confirmedBy='Ręcznie — '.((string)($manual['actor']??'')?:'użytkownik');
            $confirmedAt=$date($manual['updated_at']??'');
        } else {
            foreach ($events as $event) {
                $message=(string)($event['message']??'');
                if (strpos($message,'Płatność potwierdzona w źródle')===0 || strpos($message,'Źródło nie potwierdza już płatności')===0 || stripos($message,'opłacone')!==false) {
                    $confirmedBy=$message; $confirmedAt=$date($event['created_at']??''); break;
                }
            }
            if ($confirmedBy==='' && (int)$detail['paid']) {
                $confirmedBy=$platform==='manual'?'Oznaczone przy tworzeniu zamówienia':'Opłacone już przy imporcie z '.$platformLabel;
                $confirmedAt=$date($detail['imported_at']??'');
            }
        }
        $state=$cod?'cod':((int)$detail['paid']?'paid':((int)($detail['details']['amount_paid_cents']??0)>0?'partial':'unpaid'));
        $stateLabels=['cod'=>'Za pobraniem','paid'=>'Opłacone','partial'=>'Częściowo opłacone','unpaid'=>'Nieopłacone'];
        return $info+['platform_label'=>$platformLabel,'state'=>$state,'state_label'=>$stateLabels[$state],'confirmed_by'=>$confirmedBy,'confirmed_at'=>$confirmedAt];
    }
    private function sourceOrderUrl(string $platform,string $externalId,string $sellerId=''): string
    {
        $externalId=trim($externalId);
        if ($externalId==='' || mb_strlen($externalId,'UTF-8')>190 || preg_match('/[\x00-\x20\x7F]/u',$externalId)) { return ''; }
        $encoded=rawurlencode($externalId);
        if ($platform==='allegro' && preg_match('/^[a-f0-9-]{20,60}$/iD',$externalId)) { return 'https://salescenter.allegro.com/orders/'.$encoded.(preg_match('/^\d{1,30}$/D',$sellerId)?'?sellerId='.rawurlencode($sellerId):''); }
        if ($platform==='erli' && preg_match('/^[A-Za-z0-9._-]{1,190}$/D',$externalId) && preg_match('/^\d+$/D',$sellerId)) { return 'https://erli.pl/manager-sklepu/'.$sellerId.'/zamowienia/'.$encoded; }
        if ($platform==='woocommerce' && preg_match('#^https://#',$sellerId) && ctype_digit($externalId)) { return $sellerId.'/wp-admin/post.php?post='.$encoded.'&action=edit'; }
        if ($platform==='altreo' && preg_match('#^https://[^\s"\'<>]+/admin/zamowienia/\d+$#D',$sellerId)) { return $sellerId; }
        if ($platform==='morele' && preg_match('/^[A-Za-z0-9._-]{1,190}$/D',$externalId)) { return 'https://marketplace.morele.net/order/'.$encoded; }
        if ($platform==='empik' && preg_match('/^[A-Za-z0-9._-]{1,190}$/D',$externalId)) { return 'https://marketplace.empik.com/mmp/shop/order/'.$encoded; }
        return '';
    }
    /** Liczba przesyłek i ostatnie nadanie per konto nadawcze (karty w zakładce Przesyłki). */
    private function carrierAccountStats(array $accounts): array
    {
        $stats=[];
        foreach ($this->db()->fetchAll('SELECT carrier_account_id,COUNT(*) total,MAX(created_at) last_at FROM om_shipments GROUP BY carrier_account_id') as $row) { $stats[(int)$row['carrier_account_id']]=$row; }
        foreach ($accounts as &$account) { $account['shipment_count']=(int)($stats[(int)$account['id']]['total']??0); $account['last_shipment_at']=(string)($stats[(int)$account['id']]['last_at']??''); }
        unset($account);
        return $accounts;
    }
    private function shipmentSuggestion(array $order,array $accounts,array $defaults): array
    {
        return OrderShipmentService::suggestion($this->repository(),$order,$accounts,$defaults);
    }
    public function printdocument(): void
    {
        $user=$this->requireModule('orders'); $this->repository();
        $document=$this->db()->fetch('SELECT * FROM om_documents WHERE id=:id',['id'=>(int)$this->input('id',0)]);
        if (!$document) { http_response_code(404); exit('Nie znaleziono dokumentu.'); }
        $document['snapshot']=json_decode($document['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
        $document['vat_summary']=[];
        foreach ((array)($document['snapshot']['items']??[]) as $item) {
            $vat=(string)($item['vat']??'');
            if (!isset($document['vat_summary'][$vat])) { $document['vat_summary'][$vat]=['vat'=>$vat,'net_cents'=>0,'tax_cents'=>0,'gross_cents'=>0]; }
            foreach (['net_cents','tax_cents','gross_cents'] as $field) { $document['vat_summary'][$vat][$field]+=(int)($item[$field]??0); }
        }
        $ksef=new KsefService(new OrderRepository($this->db())); $ksef->ensureSchema();
        $document['ksef']=$ksef->latest([(int)$document['id']])[(int)$document['id']]??null;
        if ($document['ksef'] && $document['ksef']['state']==='accepted' && !empty($document['ksef']['invoice_hash'])) {
            $document['ksef']['qr_url']=KsefService::qrUrl((string)$document['ksef']['environment'],(string)($document['snapshot']['seller']['nip']??''),(string)($document['snapshot']['issue_date']??''),(string)$document['ksef']['invoice_hash']);
        }
        header('Cache-Control: no-store');
        $smarty=SmartyFactory::create(); $smarty->assign(['document'=>$document,'canWrite'=>$this->moduleAccessLevel($user,'orders')==='edit']); $smarty->display('orders/print.tpl');
    }
    /** Downloads FA(3) XML preview (kind=xml) or the UPO of an accepted KSeF submission (kind=upo). */
    public function ksefdownload(): void
    {
        $this->requireModule('orders');
        try {
            $ksef=$this->ksef($this->repository());
            $documentId=(int)$this->input('id',0);
            $file=(string)$this->input('kind','xml')==='upo'?$ksef->upo($documentId):$ksef->preview($documentId);
            header('Content-Type: application/xml; charset=utf-8');
            header('Content-Disposition: attachment; filename="'.$file['name'].'"');
            header('Cache-Control: no-store, private');
            echo $file['xml'];
        } catch (InvalidArgumentException $e) { http_response_code(409); header('Content-Type: text/plain; charset=utf-8'); echo $e->getMessage(); }
        catch (\Throwable $e) { $d=\App\Services\OrderSyncError::log($e,['stage'=>'ksef']); http_response_code(502); header('Content-Type: text/plain; charset=utf-8'); echo 'Nie udało się pobrać pliku z KSeF: '.$e->getMessage().' [ID: '.$d['reference'].']'; }
    }
    public function correctdocument(): void
    {
        $this->requireModuleWrite('orders'); $this->repository();
        $parent=$this->db()->fetch('SELECT * FROM om_documents WHERE id=:id',['id'=>(int)$this->input('id',0)]);
        if (!$parent || !in_array($parent['kind'],['invoice','receipt','invoice_correction','receipt_correction'],true)) { http_response_code(404); exit('Nie znaleziono dokumentu do korekty.'); }
        $descendants=[(int)$parent['id']=>true]; $latest=null;
        foreach ($this->db()->fetchAll('SELECT id,parent_id,number,snapshot_json FROM om_documents WHERE order_id=:order_id AND parent_id IS NOT NULL ORDER BY id',['order_id'=>$parent['order_id']]) as $candidate) {
            if (isset($descendants[(int)$candidate['parent_id']])) { $descendants[(int)$candidate['id']]=true; $latest=$candidate; }
        }
        $parent['source_revision_id']=(int)($latest['id']??$parent['id']);
        $parent['source_revision_number']=(string)($latest['number']??$parent['number']);
        $parent['snapshot']=json_decode($latest['snapshot_json']??$parent['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
        $correctionKind=strpos($parent['kind'],'invoice')===0?'invoice_correction':'receipt_correction';
        $series=$this->db()->fetchAll('SELECT id,name FROM om_series WHERE kind=:kind ORDER BY id',['kind'=>$correctionKind]);
        header('Cache-Control: no-store, private');
        $smarty=SmartyFactory::create();
        $smarty->assign(['parent'=>$parent,'series'=>$series,'csrf'=>$this->token(),'requestKey'=>bin2hex(random_bytes(24)),'correctionKind'=>$correctionKind,'today'=>(new \DateTimeImmutable('now',new \DateTimeZone('Europe/Warsaw')))->format('Y-m-d'),'flashError'=>$this->getFlash('error')]);
        $smarty->display('orders/correct.tpl');
    }
    public function labelshipment(): void
    {
        $this->requireModule('orders'); $repo=$this->repository();
        try {
            $label=(new OrderShipmentService($repo))->label((int)$this->input('id',0),(string)$this->input('page_size','A6'));
            header('Content-Type: '.$label['mime']); header('Content-Disposition: inline; filename="'.$label['name'].'"'); header('Cache-Control: no-store, private');
            echo $label['bytes'];
        } catch (\Throwable $e) { http_response_code(409); echo htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'); }
    }
    /** Dokument operatora inny niż etykieta, np. protokół odbioru ERLI. */
    public function shipmentdocument(): void
    {
        $this->requireModule('orders'); $repo=$this->repository();
        try {
            $document=(new OrderShipmentService($repo))->document((int)$this->input('id',0),(string)$this->input('kind',''));
            header('Content-Type: '.$document['mime']); header('Content-Disposition: inline; filename="'.$document['name'].'"'); header('Cache-Control: no-store, private');
            echo $document['bytes'];
        } catch (\Throwable $e) { http_response_code(409); echo htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'); }
    }
}
