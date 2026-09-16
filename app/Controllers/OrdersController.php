<?php

declare(strict_types=1);
namespace App\Controllers;
use App\Core\Controller;
use App\Core\Config;
use App\Core\SmartyFactory;
use App\Models\OrderRepository;
use App\Models\PrintAgentRepository;
use App\Services\OrderSyncService;
use App\Services\OrderDocumentService;
use App\Services\OrderMarketplaceShipmentService;
use App\Services\OrderShipmentService;
use InvalidArgumentException;

final class OrdersController extends Controller
{
    private function repository(): OrderRepository
    {
        $repo=new OrderRepository($this->db()); $repo->ensureSchema(); return $repo;
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
        if (!in_array($tab,['list','new','accounts','statuses','rules','documents','shipments','payments','printing'],true)) { $tab='list'; }
        $filters=[
            'q'=>(string)$this->input('q',''),
            'status_id'=>$this->input('status_id',''),
            'account_id'=>$this->input('account_id',''),
            'platform'=>(string)$this->input('platform',''),
            'paid'=>$this->input('paid',''),
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
            return !in_array($key,['page','sort','status_id'],true) && $value!=='' && $value!==null;
        },ARRAY_FILTER_USE_BOTH));
        $detail=null; $events=[]; $orderDocs=[]; $orderShipments=[]; $issuedDocuments=['receipt'=>null,'invoice'=>null];
        if ((int)$this->input('id',0)>0) {
            $detail=$repo->order((int)$this->input('id'));
            $sellerId=$detail['platform']==='allegro'?$this->allegroSellerId($repo,(int)($detail['account_source_id']??0)):'';
            $detail['source_order_url']=$this->sourceOrderUrl((string)$detail['platform'],(string)$detail['external_id'],$sellerId);
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
        }
        $carrierAccounts=$repo->carrierAccounts();
        $shippingDefaults=$this->shippingDefaults($repo);
        $shipmentSuggestion=$detail?$this->shipmentSuggestion($detail,$carrierAccounts,$shippingDefaults):[];
        $documentDefaults=$repo->setting('document_defaults')+['vat'=>'23'];
        $receiptPrinterSettings=$repo->setting('receipt_printer')+['printer_id'=>0];
        $series=$this->db()->fetchAll('SELECT * FROM om_series ORDER BY id');
        $seriesUsage=array_column($this->db()->fetchAll('SELECT series_id,COUNT(*) c FROM om_documents GROUP BY series_id'),'c','series_id');
        foreach ($series as &$item) {
            $item['numbering']=json_decode((string)($item['numbering_json']??''),true)?:[];
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
            $docRow['gross_cents']=(int)($snap['gross_cents']??0);
            $docRow['currency']=(string)($snap['currency']??'PLN');
            $docRow['has_correction']=isset($correctionParents[$docRow['id']]);
            $docRow['items']=array_map(static function ($it) {
                return ['name'=>(string)($it['name']??''),'quantity'=>(int)($it['quantity']??0),'vat'=>(string)($it['vat']??'23'),'price'=>number_format(((int)($it['unit_cents']??0))/100,2,'.','')];
            },(array)($snap['items']??[]));
            $documents[]=$docRow;
        }
        $settingsAccess=$this->moduleAccessLevel($user,'orders')==='edit';
        $this->renderOrders([
            'pageTitle'=>'Centrum zamówień','tab'=>$tab,'csrf'=>$csrf,'canWrite'=>$settingsAccess,
            'listing'=>$repo->listing($filters),'filters'=>$filters,'listQuery'=>$listQuery,'activeFilterCount'=>$activeFilterCount,'dashboard'=>$repo->dashboard(),
            'accounts'=>$repo->accounts(),'statuses'=>$repo->statuses(),'detail'=>$detail,'events'=>$events,'orderDocs'=>$orderDocs,'issuedDocuments'=>$issuedDocuments,'orderShipments'=>$orderShipments,
            'paymentMethods'=>$repo->paymentMethods(),'paymentSources'=>$repo->paymentSources(),
            'mappings'=>$this->db()->fetchAll('SELECT m.*,a.name account_name,a.platform,s.name status_name FROM om_mappings m JOIN om_accounts a ON a.id=m.account_id JOIN om_statuses s ON s.id=m.status_id ORDER BY a.platform,a.name,m.remote_status'),
            'rules'=>$repo->rules(),
            'series'=>$series,
            'seller'=>$repo->setting('seller')+['name'=>'','nip'=>'','address'=>'','bank'=>''],
            'documents'=>$documents,'documentSeriesFilter'=>$documentSeriesFilter,
            'shipments'=>$this->db()->fetchAll('SELECT * FROM om_shipments ORDER BY id DESC LIMIT 100'),
            'carrierAccounts'=>$carrierAccounts,'shippingDefaults'=>$shippingDefaults,'shipmentSuggestion'=>$shipmentSuggestion,'documentDefaults'=>$documentDefaults,'receiptPrinterSettings'=>$receiptPrinterSettings,'sourceCarrierOptions'=>OrderMarketplaceShipmentService::carrierOptions(),
            'printStations'=>$printAgents->stations(),'printJobs'=>$printAgents->jobs(),'printFiscalPrinters'=>$printAgents->fiscalPrinters(),'printFiscalJobs'=>$printAgents->fiscalJobs(),'printAgentApiUrl'=>$this->printAgentApiBase(),
            'documentKey'=>bin2hex(random_bytes(24)),
        ]);
    }
    private function renderOrders(array $data): void
    {
        $data['flashSuccess']=$this->getFlash('success');
        $data['flashError']=$this->getFlash('error');
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
            $db->transaction(function () use ($repo,$db,$id,$status,$actor) {
                $order=$repo->order($id);
                if ((int)$order['status_id']!==$status) { $repo->changeStatus($id,$status,$actor); }
                $note=mb_substr((string)($_POST['note']??''),0,10000);
                $tags=mb_substr((string)($_POST['tags']??''),0,1000);
                if ((string)$order['note']!==$note || (string)$order['tags']!==$tags) {
                    $db->update('om_orders',['note'=>$note,'tags'=>$tags,'updated_at'=>gmdate('Y-m-d H:i:s')],'id=:id',['id'=>$id]);
                }
            });
            $this->jsonResponse(['ok'=>true,'saved_at'=>date('H:i:s')]);
        } catch (InvalidArgumentException $e) { $this->jsonResponse(['error'=>$e->getMessage(),'code'=>'INVALID_INPUT'],422); }
        catch (\Throwable $e) { $this->apiFailure($e); }
    }
    private function apiFailure(\Throwable $error): void
    {
        $d=\App\Services\OrderSyncError::log($error,['stage'=>'http']);
        $this->jsonResponse(['error'=>$d['message'],'code'=>$d['code'],'reference'=>$d['reference']],500);
    }
    public function sync(): void
    {
        try {
            if (!$this->apiUser(true)) { return; }
            if (!$this->isPost()) { $this->jsonResponse(['error'=>'Wymagany POST.','code'=>'METHOD_NOT_ALLOWED'],405); return; }
            if (!hash_equals($this->token(),(string)($_POST['csrf']??''))) {
                $this->releaseSessionLock();
                $this->jsonResponse(['error'=>'Token formularza wymaga odświeżenia.','code'=>'CSRF_EXPIRED'],419); return;
            }
            $this->releaseSessionLock();
            $repo=$this->repository();
            $this->jsonResponse(['results'=>(new OrderSyncService($repo))->sync(!empty($_POST['test']),!empty($_POST['account_id'])?(int)$_POST['account_id']:null)]);
        } catch (\Throwable $e) { $this->apiFailure($e); }
    }
    public function save(): void
    {
        $user=$this->writeGuard(); $repo=$this->repository(); $db=$this->db();
        $actor=(string)($user['name']??$user['email']??('użytkownik #'.$user['id']));
        $op=(string)($_POST['operation']??''); $tab=(string)($_POST['tab']??'list'); $id=(int)($_POST['order_id']??0);
        $successMessage='Zapisano.';
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
                    $data=['name'=>$name,'color'=>$color,'position'=>(int)($_POST['position']??0)];
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
                        $db->update('om_orders',['note'=>substr((string)($_POST['note']??''),0,10000),'tags'=>substr((string)($_POST['tags']??''),0,1000)],'id=:id',['id'=>$id]);
                        $repo->event($id,'Zapisano notatkę i tagi.',$actor);
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
                case 'rule':
                    $conditions=[]; $actions=[];
                    foreach (['account_id','status_id','paid'] as $field) { if (isset($_POST['when_'.$field]) && $_POST['when_'.$field]!=='') { $conditions[$field]=(int)$_POST['when_'.$field]; } }
                    if (isset($conditions['status_id'])) { $repo->requireStatus($conditions['status_id']); }
                    if (!empty($_POST['then_status_id'])) { $repo->requireStatus((int)$_POST['then_status_id']); $actions['status_id']=(int)$_POST['then_status_id']; }
                    foreach (['tag','note'] as $field) { if (trim((string)($_POST['then_'.$field]??''))!=='') { $actions[$field]=substr(trim((string)$_POST['then_'.$field]),0,500); } }
                    if (!$actions) { throw new InvalidArgumentException('Dodaj przynajmniej jedną akcję.'); }
                    $trigger=(string)($_POST['trigger_name']??'');
                    if (!in_array($trigger,['import','status'],true)) { throw new InvalidArgumentException('Nieprawidłowy wyzwalacz.'); }
                    if (isset($conditions['account_id']) && !$db->fetchColumn('SELECT id FROM om_accounts WHERE id=:id',['id'=>$conditions['account_id']])) { throw new InvalidArgumentException('Nieznane konto w warunku.'); }
                    if (isset($conditions['paid']) && !in_array($conditions['paid'],[0,1],true)) { throw new InvalidArgumentException('Nieprawidłowy warunek płatności.'); }
                    $db->insert('om_rules',['name'=>$this->required('name',150),'trigger_name'=>$trigger,'conditions_json'=>OrderRepository::json($conditions),'actions_json'=>OrderRepository::json($actions),'enabled'=>1]);
                    break;
                case 'toggle_rule': $db->update('om_rules',['enabled'=>empty($_POST['enabled'])?0:1],'id=:id',['id'=>(int)$_POST['rule_id']]); break;
                case 'preview_rule':
                    $matched=$repo->runRules($id,'import','preview',true);
                    $this->setFlash('success','Test bez wykonania — pasujące reguły: '.($matched?implode(', ',$matched):'brak'));
                    $this->redirect('./index.php?controller=orders&id='.$id);
                    break;
                case 'seller':
                    $repo->saveSetting('seller',['name'=>$this->required('name',200),'address'=>$this->required('address',1000),'nip'=>$this->required('nip',30),'bank'=>substr((string)($_POST['bank']??''),0,100)]);
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
                    $db->insert('om_series',['name'=>$this->required('name',100),'kind'=>$kind,'pattern'=>$pattern,'next_number'=>$next,'fiscal_printer_id'=>$this->seriesPrinterId($db,$kind),'numbering_json'=>$numbering?OrderRepository::json($numbering):null]);
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
                    $db->update('om_series',['name'=>$this->required('name',100),'kind'=>$kind,'pattern'=>$pattern,'next_number'=>$next,'fiscal_printer_id'=>$this->seriesPrinterId($db,$kind),'numbering_json'=>$numbering?OrderRepository::json($numbering):null,'numbering_period'=>$period],'id=:id',['id'=>$seriesId]);
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
                    $buyer=trim((string)($_POST['buyer']??''));
                    if ($buyer==='') { throw new InvalidArgumentException('Uzupełnij dane nabywcy.'); }
                    $calculated=OrderDocumentService::calculate($_POST['items']??[]);
                    $old=json_decode($document['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
                    $snapshot=$calculated+['seller'=>$old['seller'],'buyer'=>$buyer,'currency'=>$old['currency'],'sale_date'=>$old['sale_date'],'issue_date'=>$old['issue_date'],'reason'=>$old['reason']??'','order_number'=>$old['order_number'],'settlement_state'=>$old['settlement_state']??'local','fiscalized'=>$old['fiscalized']??false];
                    if (isset($old['before'])) { $snapshot['before']=$old['before']; $snapshot['difference_cents']=$snapshot['gross_cents']-(int)$old['before']['gross_cents']; $snapshot['parent_number']=$old['parent_number']; }
                    $snapshot['edited_at']=gmdate('Y-m-d H:i:s');
                    $db->update('om_documents',['snapshot_json'=>OrderRepository::json($snapshot)],'id=:id',['id'=>$documentId]);
                    $repo->event((int)$document['order_id'],'Zaktualizowano dokument '.$document['number'].' (edycja nabywcy/pozycji).',$actor);
                    $successMessage='Zaktualizowano dokument '.$document['number'].'.';
                    break;
                case 'document_delete':
                    $documentId=(int)($_POST['document_id']??0);
                    $document=$db->fetch('SELECT * FROM om_documents WHERE id=:id',['id'=>$documentId]);
                    if (!$document) { throw new InvalidArgumentException('Nie znaleziono dokumentu.'); }
                    if ($db->fetchColumn('SELECT id FROM om_documents WHERE parent_id=:id LIMIT 1',['id'=>$documentId])) { throw new InvalidArgumentException('Nie można usunąć dokumentu, do którego wystawiono korektę. Usuń najpierw korektę.'); }
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
                    $receiptPrinterId=0;
                    if ($kind==='receipt') {
                        $receiptPrinterId=$this->receiptPrinterId($series,$repo);
                        if ($receiptPrinterId>0) { (new PrintAgentRepository($db))->ensureSchema(); }
                        if ($receiptPrinterId>0 && !$db->fetchColumn('SELECT id FROM print_fiscal_printers WHERE id=:id AND enabled=1',['id'=>$receiptPrinterId])) {
                            throw new InvalidArgumentException('Przypisana drukarka paragonów jest nieaktywna. Zmień ją w zakładce Dokumenty.');
                        }
                    }
                    $doc=(new OrderDocumentService($repo))->issue($id,$this->documentPayload($repo,$repo->order($id),$kind,(string)($_POST['request_key']??''))+['series_id'=>$series['id']],$actor);
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
                    $kind=$parent['kind'].'_correction'; $series=$this->documentSeries($db,$kind);
                    $payload=$this->documentPayload($repo,$repo->order($id),$parent['kind'],(string)($_POST['request_key']??''));
                    $payload+=['series_id'=>$series['id'],'parent_id'=>$parent['id'],'reason'=>$reason];
                    $doc=(new OrderDocumentService($repo))->issue($id,$payload,$actor);
                    $this->redirect('./index.php?controller=orders&action=printdocument&id='.$doc);
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
                    $successMessage='Stanowisko utworzone. Token (skopiuj teraz): '.$plainToken;
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
                    $successMessage='Wygenerowano nowy token; poprzedni przestał działać. Skopiuj teraz: '.$plainToken;
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
                    if (!in_array($provider,['allegro_wza','inpost_shipx','apaczka'],true)) { throw new InvalidArgumentException('Nieznany operator przesyłek.'); }
                    $name=$this->required('name',150);
                    $public=[]; $secret=[];
                    if ($provider==='allegro_wza') {
                        $accountId=(int)($_POST['allegro_account_id']??0);
                        $account=$db->fetch('SELECT * FROM om_accounts WHERE id=:id AND platform=:platform',['id'=>$accountId,'platform'=>'allegro']);
                        if (!$account) { throw new InvalidArgumentException('Wybierz konto Allegro.'); }
                        $public=['order_account_id'=>$accountId];
                    } elseif ($provider==='inpost_shipx') {
                        $public=['organization_id'=>$this->required('organization_id',40),'environment'=>($_POST['environment']??'production')==='sandbox'?'sandbox':'production'];
                        $secret=['token'=>$this->required('token',1000)];
                    } else {
                        $public=['app_id'=>$this->required('app_id',200)];
                        $secret=['app_secret'=>$this->required('app_secret',1000)];
                    }
                    $cipher=\App\Services\OrderSecretBox::encrypt($secret);
                    $db->transaction(function () use ($db,$provider,$name,$public,$cipher) {
                        $existing=$db->fetch('SELECT id FROM om_carrier_accounts WHERE provider=:p AND name=:n',['p'=>$provider,'n'=>$name]);
                        $data=['enabled'=>1,'public_config_json'=>OrderRepository::json($public),'secret_config_json'=>$cipher,'updated_at'=>gmdate('Y-m-d H:i:s')];
                        if ($existing) { $db->update('om_carrier_accounts',$data,'id=:id',['id'=>$existing['id']]); }
                        else { $db->insert('om_carrier_accounts',$data+['provider'=>$provider,'name'=>$name]); }
                    });
                    break;
                case 'shipping_defaults':
                    $accountId=(int)($_POST['default_carrier_account_id']??0);
                    if ($accountId && !$db->fetchColumn('SELECT id FROM om_carrier_accounts WHERE id=:id AND enabled=1',['id'=>$accountId])) { throw new InvalidArgumentException('Wybierz aktywne konto nadawcze.'); }
                    $number=static function (array $source,string $key,float $min,float $max): float { $value=filter_var($source[$key]??null,FILTER_VALIDATE_FLOAT); if ($value===false||$value<$min||$value>$max) { throw new InvalidArgumentException('Sprawdź wartość '.$key.'.'); } return (float)$value; };
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
            $this->setFlash('success',$successMessage);
        } catch (\Throwable $e) {
            $safeMessage=$e instanceof InvalidArgumentException ? $e->getMessage() : 'Nie udało się zapisać. Sprawdź, czy numer dokumentu lub przesyłki nie został już użyty.';
            if ($op==='publish_shipment') {
                $diagnostic=\App\Services\OrderSyncError::log($e,['stage'=>'source_shipment','order_id'=>$id]);
                $reason=mb_substr(trim((string)$e->getMessage()),0,300,'UTF-8');
                $safeMessage='Nie udało się przekazać numeru przesyłki do źródła. '.$reason.' [ID: '.$diagnostic['reference'].']';
            } elseif (in_array($op,['create_shipment','refresh_shipment','cancel_shipment'],true)) {
                $diagnostic=\App\Services\OrderSyncError::log($e,['stage'=>'shipment','order_id'=>$id]);
                $reason=$e instanceof InvalidArgumentException ? $e->getMessage() : $diagnostic['message'];
                $safeMessage='Nie udało się obsłużyć przesyłki. '.$reason.' [ID: '.$diagnostic['reference'].']';
            }
            $this->setFlash('error',$safeMessage);
        }
        $this->redirect('./index.php?controller=orders&tab='.rawurlencode($tab).($id?'&id='.$id:''));
    }
    private function required(string $key,int $limit): string
    {
        $value=trim((string)($_POST[$key]??''));
        if ($value==='' || mb_strlen($value)>$limit) { throw new InvalidArgumentException('Uzupełnij pole '.$key.' (maks. '.$limit.' znaków).'); }
        return $value;
    }
    private function printAgentApiBase(): string
    {
        $config=Config::get('app');
        $public=rtrim((string)($config['public_base_url']??''),'/');
        if ($public==='') { return 'print-agent-api.php'; }
        return preg_replace('#/index\.php$#','/print-agent-api.php',$public)?:'print-agent-api.php';
    }
    private function documentSeries($db,string $kind): array
    {
        $series=$db->fetch('SELECT * FROM om_series WHERE kind=:kind ORDER BY id LIMIT 1',['kind'=>$kind]);
        if ($series) { return $series; }
        $labels=['invoice'=>['Faktury','FV'],'receipt'=>['Paragony','PAR'],'invoice_correction'=>['Korekty faktur','KOR-FV'],'receipt_correction'=>['Korekty paragonów','KOR-PAR']];
        if (!isset($labels[$kind])) { throw new InvalidArgumentException('Nieprawidłowy rodzaj dokumentu.'); }
        $id=(int)$db->insert('om_series',['name'=>$labels[$kind][0],'kind'=>$kind,'pattern'=>$labels[$kind][1].'/{YYYY}/{N}','next_number'=>1,'fiscal_printer_id'=>null]);
        return $db->fetch('SELECT * FROM om_series WHERE id=:id',['id'=>$id]);
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
    private function seriesPattern(array $numbering): string
    {
        $pattern=$numbering['prefix'].'/{N}/'.($numbering['format']==='MONTHLY'?'{MM}/':'').'{YYYY}'.$numbering['suffix'];
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
    private function documentPayload(OrderRepository $repo,array $order,string $buyerKind,string $requestKey): array
    {
        $defaultVat=(string)(($repo->setting('document_defaults')['vat']??'23'));
        if (!in_array($defaultVat,['23','8','5','0','zw','np'],true)) { $defaultVat='23'; }
        $items=[]; $sum=0;
        foreach ((array)($order['details']['items']??[]) as $item) {
            $vat=(string)($item['vat']??$defaultVat); if (!in_array($vat,['23','8','5','0','zw','np'],true)) { $vat=$defaultVat; }
            $quantity=max(0,(int)($item['quantity']??0)); $unit=(int)($item['unit_cents']??0); $sum+=$quantity*$unit;
            $items[]=['name'=>(string)($item['name']??'Produkt'),'quantity'=>$quantity,'price'=>number_format($unit/100,2,'.',''),'vat'=>$vat];
        }
        $shipping=(int)($order['details']['shipping_cents']??0);
        if ($shipping>0) { $items[]=['name'=>'Dostawa','quantity'=>1,'price'=>number_format($shipping/100,2,'.',''),'vat'=>$defaultVat]; $sum+=$shipping; }
        $difference=(int)$order['total_cents']-$sum;
        if ($difference!==0) { $items[]=['name'=>$difference<0?'Rabat / korekta wartości':'Pozostałe opłaty','quantity'=>1,'price'=>number_format($difference/100,2,'.',''),'vat'=>$defaultVat]; }
        $buyerLines=$buyerKind==='invoice'?(array)($order['details']['invoice_lines']??[]):(array)($order['details']['address_lines']??[]);
        array_unshift($buyerLines,(string)$order['buyer_name']);
        return ['request_key'=>$requestKey,'buyer'=>implode("\n",array_filter($buyerLines)),'items'=>$items];
    }
    private function shippingDefaults(OrderRepository $repo): array
    {
        $defaults=$repo->setting('shipping_defaults');
        $base=['default_carrier_account_id'=>0,'default_package'=>'auto','service'=>'auto','apaczka_service_id'=>0,'pickup_type'=>'SELF','default_point'=>'','content'=>'Towar','cod_bank_account'=>'','presets'=>['small'=>['length'=>23,'width'=>16,'height'=>10,'weight'=>0.5],'medium'=>['length'=>30,'width'=>20,'height'=>15,'weight'=>1],'large'=>['length'=>40,'width'=>30,'height'=>20,'weight'=>2]],'sender'=>['name'=>'','email'=>'','phone'=>'','street'=>'','building'=>'','postal_code'=>'','city'=>'']];
        return array_replace_recursive($base,$defaults);
    }
    private function allegroSellerId(OrderRepository $repo,int $sourceId): string
    {
        if ($sourceId<1) { return ''; }
        $cache=$repo->setting('allegro_seller_ids');
        $cached=trim((string)($cache[(string)$sourceId]??''));
        if (preg_match('/^\d{1,30}$/D',$cached)) { return $cached; }
        try { $sellerId=(new AllegroService(true))->sellerIdForAccount($sourceId); }
        catch (\Throwable $error) { return ''; }
        if ($sellerId!=='') { $cache[(string)$sourceId]=$sellerId;$repo->saveSetting('allegro_seller_ids',$cache); }
        return $sellerId;
    }
    private function sourceOrderUrl(string $platform,string $externalId,string $sellerId=''): string
    {
        $externalId=trim($externalId);
        if ($externalId==='' || mb_strlen($externalId,'UTF-8')>190 || preg_match('/[\x00-\x20\x7F]/u',$externalId)) { return ''; }
        $encoded=rawurlencode($externalId);
        if ($platform==='allegro' && preg_match('/^[a-f0-9-]{20,60}$/iD',$externalId)) { return 'https://salescenter.allegro.com/orders/'.$encoded.(preg_match('/^\d{1,30}$/D',$sellerId)?'?sellerId='.rawurlencode($sellerId):''); }
        if ($platform==='erli' && preg_match('/^[A-Za-z0-9._-]{1,190}$/D',$externalId)) { return 'https://erli.pl/manager-sklepu/33561/zamowienia/'.$encoded; }
        if ($platform==='morele' && preg_match('/^[A-Za-z0-9._-]{1,190}$/D',$externalId)) { return 'https://marketplace.morele.net/order/'.$encoded; }
        if ($platform==='empik' && preg_match('/^[A-Za-z0-9._-]{1,190}$/D',$externalId)) { return 'https://marketplace.empik.com/mmp/shop/order/'.$encoded; }
        return '';
    }
    private function shipmentSuggestion(array $order,array $accounts,array $defaults): array
    {
        $quantity=array_sum(array_map(static function ($item) { return max(0,(int)($item['quantity']??0)); },(array)($order['details']['items']??[])));
        $size=$defaults['default_package']==='auto'?($quantity<=1?'small':($quantity<=4?'medium':'large')):$defaults['default_package'];
        $delivery=strtolower((string)($order['details']['delivery']??'').' '.(string)($order['details']['pickup']??''));
        $service=$defaults['service']==='auto'?((strpos($delivery,'paczkomat')!==false||strpos($delivery,'locker')!==false||!empty($order['details']['pickup']))?'inpost_locker_standard':'inpost_courier_standard'):$defaults['service'];
        $selected=0; $reason='Pierwsze aktywne konto nadawcze';
        foreach ($accounts as $account) {
            if (!(int)$account['enabled']) { continue; }
            $public=json_decode((string)$account['public_config_json'],true)?:[];
            if ($order['platform']==='allegro' && $account['provider']==='allegro_wza' && (int)($public['order_account_id']??0)===(int)$order['account_id']) { $selected=(int)$account['id']; $reason='Dopasowano konto Wysyłam z Allegro do źródła zamówienia'; break; }
            if (!$selected && (strpos($delivery,'inpost')!==false||strpos($delivery,'paczkomat')!==false) && $account['provider']==='inpost_shipx') { $selected=(int)$account['id']; $reason='Dopasowano InPost na podstawie metody dostawy'; }
        }
        if (!$selected && (int)$defaults['default_carrier_account_id']) { foreach ($accounts as $account) { if ((int)$account['id']===(int)$defaults['default_carrier_account_id']&&(int)$account['enabled']) { $selected=(int)$account['id']; $reason='Użyto globalnego konta domyślnego'; break; } } }
        if (!$selected) { foreach ($accounts as $account) { if (!(int)$account['enabled'] || ($account['provider']==='allegro_wza' && $order['platform']!=='allegro')) { continue; } $selected=(int)$account['id']; $reason=$account['provider']==='apaczka'?'Dopasowano Apaczkę do zamówienia spoza Allegro':'Pierwsze zgodne konto nadawcze'; break; } }
        return ['carrier_account_id'=>$selected,'reason'=>$reason,'preset'=>$size,'package'=>$defaults['presets'][$size],'service'=>$service];
    }
    public function printdocument(): void
    {
        $this->requireModule('orders'); $this->repository();
        $document=$this->db()->fetch('SELECT * FROM om_documents WHERE id=:id',['id'=>(int)$this->input('id',0)]);
        if (!$document) { http_response_code(404); exit('Nie znaleziono dokumentu.'); }
        $document['snapshot']=json_decode($document['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
        header('Cache-Control: no-store');
        $smarty=SmartyFactory::create(); $smarty->assign('document',$document); $smarty->display('orders/print.tpl');
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
}
