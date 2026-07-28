<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\ProductRepository;
use App\Services\AllegroService;
use App\Services\MailService;
use Throwable;

class AllegroController extends Controller
{
    /** @var AllegroService */
    private $allegro;

    /** @var MailService */
    private $mail;

    public function __construct()
    {
        $this->allegro = new AllegroService();
        $this->mail = new MailService();
    }

    public function index(): void
    {
        $this->requireModule('allegro');

        $accounts = $this->allegro->listAccounts();
        $filters = $this->offerFilters();
        $page = max(1, (int) $this->input('page', 1));
        $allowedPerPage = array(50, 100, 200, 5000, 10000);
        $perPageInput = (int) $this->input('per_page', 50);
        $perPage = in_array($perPageInput, $allowedPerPage, true) ? $perPageInput : 50;
        $sortBy = trim((string) $this->input('sort_by', 'id'));
        $sortDir = strtolower(trim((string) $this->input('sort_dir', 'desc')));
        if ($sortDir !== 'asc' && $sortDir !== 'desc') {
            $sortDir = 'desc';
        }
        $total = $this->allegro->countOffers($filters);
        $offers = $this->allegro->offersPage($filters, $page, $perPage, $sortBy, $sortDir);
        $totalPages = max(1, (int) ceil($total / max(1, $perPage)));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offers = $this->allegro->offersPage($filters, $page, $perPage, $sortBy, $sortDir);
        }
        $pageWindow = $this->buildPageWindow($page, $totalPages);
        $sortableColumns = array(
            'images',
            'account',
            'name',
            'warehouse_quantity',
            'sold',
            'sku',
            'warehouse_sku',
            'linked',
            'price',
            'invoice',
            'status',
            'market',
            'allegro_quantity',
            'synced',
            'updated',
            'created',
        );
        $sortIndicators = array();
        $sortUrls = array();

        foreach ($sortableColumns as $column) {
            $sortIndicators[$column] = ($sortBy === $column) ? $sortDir : '';
            $sortUrls[$column] = $this->buildOfferIndexUrl($filters, $column, $this->nextSortDirection($column, $sortBy, $sortDir), 1, $perPage);
        }

        $triggerBaseUrl = $this->absoluteBaseUrl();
        $currentListUrl = $this->buildOfferIndexUrl($filters, $sortBy, $sortDir, $page, $perPage);
        foreach ($accounts as &$account) {
            $account['trigger_url'] = $this->allegro->triggerUrl($account, $triggerBaseUrl);
        }
        unset($account);

        $this->render('allegro/index', array(
            'pageTitle' => 'Allegro',
            'contentTitle' => 'Integracja Allegro',
            'pageDescription' => 'Wielokontowa autoryzacja, szybki import ofert i listing pod cron.',
            'breadcrumbCurrent' => 'Allegro',
            'accounts' => $accounts,
            'offers' => $offers,
            'filters' => $filters,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'page' => $page,
            'perPage' => $perPage,
            'totalOffers' => $total,
            'totalPages' => $totalPages,
            'pageWindow' => $pageWindow,
            'sortIndicators' => $sortIndicators,
            'sortUrls' => $sortUrls,
            'stats' => $this->allegro->offerStats(),
            'queueStats' => $this->allegro->queueCounts(),
            'currentListUrl' => $currentListUrl,
            'duplicatesOnly' => (($filters['duplicates'] ?? '') === '1'),
            'defaultRedirectUri' => $triggerBaseUrl . '?controller=allegro&action=callback',
        ));
    }

    public function saveaccount(): void
    {
        $this->requireRole('admin');
        $this->requireWriteAccess();

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=administration&action=automation');
        }

        try {
            $accountId = (int) $this->input('account_id', 0);
            $this->allegro->saveAccount(array(
                'name' => $this->input('name', ''),
                'client_id' => $this->input('client_id', ''),
                'client_secret' => $this->input('client_secret', ''),
                'redirect_uri' => $this->input('redirect_uri', ''),
                'is_active' => $this->input('is_active', '0') === '1' ? 1 : 0,
            ), $accountId > 0 ? $accountId : null);

            $this->setFlash('success', 'Konto Allegro zostalo zapisane.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=administration&action=automation');
    }

    public function connect(): void
    {
        $this->requireRole('admin');
        $this->requireWriteAccess();

        try {
            $accountId = (int) $this->input('id', 0);
            $this->redirect($this->allegro->authorizationUrl($accountId));
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
            $this->redirect('./index.php?controller=administration&action=automation');
        }
    }

    public function callback(): void
    {
        try {
            $error = trim((string) $this->input('error', ''));
            if ($error !== '') {
                throw new \RuntimeException('Allegro OAuth zwrocilo blad: ' . $error);
            }

            $code = trim((string) $this->input('code', ''));
            $state = trim((string) $this->input('state', ''));
            $account = $this->allegro->handleAuthorizationCallback($code, $state);
            $this->setFlash('success', 'Autoryzacja Allegro zakonczona dla konta "' . (string) $account['name'] . '".');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=administration&action=automation');
    }

    public function sync(): void
    {
        if (!$this->authorizeSyncRequest()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('error' => 'Brak autoryzacji do synchronizacji.'));
            return;
        }

        $account = trim((string) $this->input('account', ''));
        if ($account === '') {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('error' => 'Brak parametru account.'));
            return;
        }

        $this->releaseSessionLock();

        try {
            $result = $this->allegro->syncAccount($account, array(
                'max_batches' => (int) $this->input('max_batches', 5),
                'offer_limit' => (int) $this->input('offer_limit', 100),
                'max_runtime' => (int) $this->input('max_runtime', 20),
                'force_details' => $this->input('force_details', '0') === '1',
                'force_lock' => $this->input('force', '0') === '1',
                'stale_heartbeat_seconds' => (int) $this->input('stale_heartbeat_seconds', 300),
            ));

            $this->jsonResponse($result);
        } catch (Throwable $exception) {
            $this->jsonResponse(array('error' => $exception->getMessage()), 500);
        }
    }

    public function refreshtoken(): void
    {
        if (!$this->authorizeSyncRequest()) {
            if ($this->wantsJson()) {
                $this->jsonResponse(array('error' => 'Brak autoryzacji do odswiezenia tokenow.'), 403);
                return;
            }
            $this->requireRole('admin');
        }

        $this->releaseSessionLock();
        $detached = $this->wantsJson() ? $this->beginAsyncCronResponse('refreshtoken') : false;

        try {
            $account = trim((string) $this->input('account', ''));
            if ($account !== '') {
                $result = $this->allegro->refreshAccountToken($account);
                if (!$detached) {
                    $this->setFlash('success', 'Token konta Allegro zostal odswiezony.');
                }
            } else {
                $result = $this->allegro->refreshAllTokens(true);
                $this->notifyRefreshTokenFailuresFromBatch($result);
                if (!$detached) {
                    $this->setFlash('success', 'Odswiezono tokeny aktywnych kont Allegro.');
                }
            }

            if ($detached) {
                return;
            }

            if ($this->wantsJson()) {
                $this->jsonResponse($result);
                return;
            }
        } catch (Throwable $exception) {
            $this->notifyRefreshTokenFailure($this->input('account', ''), $exception->getMessage());
            if ($detached) {
                $this->logDetachedCronError('refreshtoken', $exception);
                return;
            }

            if ($this->wantsJson()) {
                $this->jsonResponse(array('error' => $exception->getMessage()), 500);
                return;
            }
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=administration&action=automation');
    }

    public function maintenance(): void
    {
        if (!$this->authorizeSyncRequest()) {
            $this->jsonResponse(array('error' => 'Brak autoryzacji do maintenance Allegro.'), 403);
            return;
        }

        $this->releaseSessionLock();
        $detached = $this->beginAsyncCronResponse('maintenance');

        try {
            $result = $this->allegro->maintenance(array(
                'account' => $this->input('account', ''),
                'queue_limit' => (int) $this->input('queue_limit', 100),
                'sync' => $this->input('sync', '0') === '1',
                'compact_offers' => $this->input('compact_offers', '0') === '1',
                'compact_limit' => (int) $this->input('compact_limit', 500),
                'max_batches' => (int) $this->input('max_batches', 5),
                'offer_limit' => (int) $this->input('offer_limit', 100),
                'max_runtime' => (int) $this->input('max_runtime', 20),
                'force_details' => $this->input('force_details', '0') === '1',
            ));

            $mailRecipients = $this->maintenanceMailRecipients();
            if ($mailRecipients !== array() && $this->maintenanceMailOnSuccess()) {
                $mailStatus = $this->sendMaintenanceReport($mailRecipients, $result);
                $result['mail'] = $mailStatus;
            }

            if ($detached) {
                return;
            }

            $this->jsonResponse($result);
        } catch (Throwable $exception) {
            $mailRecipients = $this->maintenanceMailRecipients();
            if ($mailRecipients !== array()) {
                $this->sendMaintenanceErrorReport($mailRecipients, $exception->getMessage());
            }

            if ($detached) {
                $this->logDetachedCronError('maintenance', $exception);
                return;
            }

            $this->jsonResponse(array('error' => $exception->getMessage()), 500);
        }
    }

    public function autoendoffers(): void
    {
        if (!$this->authorizeSyncRequest()) {
            $this->jsonResponse(array('error' => 'Brak autoryzacji do automatycznego konczenia ofert Allegro.'), 403);
            return;
        }

        $this->releaseSessionLock();
        $detached = false;

        try {
            $result = $this->allegro->autoEndOffersBelowCategoryThreshold((int) $this->input('limit', 2000), false);
            $mailRecipients = $this->maintenanceMailRecipients();
            if ($mailRecipients !== array() && (int) ($result['queued'] ?? 0) > 0) {
                $result['mail'] = $this->sendAutoEndOffersReport($mailRecipients, $result);
            }

            if ($detached) {
                return;
            }

            if ($this->wantsJson() || $this->input('raw_json', '0') === '1') {
                $this->jsonResponse($result);
                return;
            }

            $this->renderAutoEndOffersDebug($result);
        } catch (Throwable $exception) {
            if ($detached) {
                $this->logDetachedCronError('autoendoffers', $exception);
                return;
            }

            $this->jsonResponse(array('error' => $exception->getMessage()), 500);
        }
    }

    public function queue(): void
    {
        $this->requireModule('allegro');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=allegro&action=index');
        }

        $this->releaseSessionLock();

        try {
            $selectionScope = trim((string) $this->input('selection_scope', 'filtered'));
            $selectedOfferIds = $this->input('selected_offer_ids', array());
            $manualIdentifiers = trim((string) $this->input('manual_offer_ids', ''));

            if ($selectionScope === 'selected' && is_array($selectedOfferIds)) {
                $manualIdentifiers = implode(' ', array_values(array_filter(array_map(static function ($value): string {
                    return trim((string) $value);
                }, $selectedOfferIds))));
            }

            $result = $this->allegro->enqueueOfferChanges(
                $this->offerFilters(),
                trim((string) $this->input('operation', '')),
                array(
                    'value' => $this->input('value', ''),
                    'search' => $this->input('search', ''),
                    'replace' => $this->input('replace', ''),
                    'product_id' => $this->input('product_id', ''),
                    'producer_by_account' => $this->input('producer_by_account', array()),
                    'producer_name_by_account' => $this->input('producer_name_by_account', array()),
                    'safety_description' => $this->input('safety_description', ''),
                    'category_id' => $this->input('category_id', ''),
                    'category_parameters' => $this->input('category_parameters', array()),
                    'delivery_value' => $this->input('delivery_value', ''),
                    'invoice_value' => $this->input('invoice_value', ''),
                    'warehouse_product_id' => $this->input('warehouse_product_id', ''),
                    'selection_limit' => (int) $this->input('selection_limit', 5000),
                ),
                $manualIdentifiers
            );

            if ((string) ($result['operation'] ?? '') === 'clear_queue') {
                $this->setFlash(
                    'success',
                    'Usunieto z kolejki: '
                    . (int) ($result['removed'] ?? 0)
                    . ' wpisow dla '
                    . (int) ($result['offers'] ?? 0)
                    . ' ofert z aktualnego zakresu akcji masowej.'
                );
            } elseif ((string) ($result['operation'] ?? '') === 'remove_from_system') {
                $this->setFlash(
                    'success',
                    'Usunięto z systemu lokalnie: '
                    . (int) ($result['removed'] ?? 0)
                    . ' ofert z '
                    . (int) ($result['offers'] ?? 0)
                    . ' wybranych pozycji.'
                );
            } else {
                $message = 'Dodano do kolejki: ' . (int) ($result['queued'] ?? 0) . ' ofert.';
                if (in_array((string) ($result['operation'] ?? ''), array('end_offer', 'remove_from_system_forever'), true)
                    && (int) ($result['filtered_out'] ?? 0) > 0) {
                    $message .= ' Pozostawiono chronione oferty z grup dubli: ' . (int) ($result['filtered_out'] ?? 0) . '.';
                }
                $this->setFlash('success', $message);
            }
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $returnUrl = trim((string) $this->input('return_url', ''));
        if ($returnUrl !== '' && strpos($returnUrl, './index.php?') === 0) {
            $this->redirect($returnUrl);
        }

        $this->redirect('./index.php?' . http_build_query(array_merge(
            array('controller' => 'allegro', 'action' => 'index'),
            $this->offerFilters()
        )));
    }

    public function processqueue(): void
    {
        if (!$this->authorizeSyncRequest()) {
            $this->jsonResponse(array('error' => 'Brak autoryzacji do kolejki zmian.'), 403);
            return;
        }

        $this->releaseSessionLock();

        try {
            $result = $this->allegro->processQueue(array(
                'account' => $this->input('account', ''),
                'limit' => (int) $this->input('limit', 100),
            ));

            if ($this->wantsJson()) {
                $this->jsonResponse($result);
                return;
            }

            $this->setFlash('success', 'Kolejka przetworzona. OK: ' . (int) ($result['done'] ?? 0));
            $this->redirect('./index.php?controller=allegro&action=index');
        } catch (Throwable $exception) {
            if ($this->wantsJson()) {
                $this->jsonResponse(array('error' => $exception->getMessage()), 500);
                return;
            }
            $this->setFlash('error', $exception->getMessage());
            $this->redirect('./index.php?controller=allegro&action=index');
        }
    }

    public function clearqueue(): void
    {
        $this->requireModuleWrite('allegro');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=allegro&action=index');
        }

        $this->releaseSessionLock();

        try {
            $mode = trim((string) $this->input('mode', 'statuses'));
            if ($mode === 'all') {
                $result = $this->allegro->clearWholeQueue();
                $this->setFlash('success', 'Usunięto całą kolejkę: ' . (int) ($result['removed'] ?? 0) . ' wpisów.');
            } else {
                $result = $this->allegro->clearQueueStatuses(true);
                $this->setFlash('success', 'Wyczyszczono statusy kolejki: ' . (int) ($result['removed'] ?? 0) . ' wpisów. Pozostawiono oczekujące.');
            }
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=allegro&action=index');
    }

    public function offer(): void
    {
        $this->requireModule('allegro');

        $id = (int) $this->input('id', 0);
        $offer = $this->allegro->offerDetails($id);

        if (!$offer) {
            http_response_code(404);
            echo 'Oferta Allegro nie istnieje.';
            return;
        }

        $this->render('allegro/offer', array(
            'pageTitle' => 'Oferta Allegro',
            'contentTitle' => (string) ($offer['name'] ?? 'Oferta Allegro'),
            'pageDescription' => 'Pelny podglad zaimportowanej oferty wraz z parametrami i payloadem API.',
            'breadcrumbCurrent' => 'Oferta Allegro',
            'offer' => $offer,
            'offerPayloadJson' => json_encode($offer['offer_payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ));
    }

    public function linkwarehouse(): void
    {
        $this->requireModuleWrite('allegro');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=allegro&action=index');
        }

        $offerRowId = (int) $this->input('offer_row_id', 0);
        $productIdRaw = trim((string) $this->input('warehouse_product_id', ''));
        $productId = ($productIdRaw !== '' && ctype_digit($productIdRaw)) ? (int) $productIdRaw : null;

        try {
            $this->allegro->linkOfferToProduct($offerRowId, $productId, $productId !== null ? 'manual' : 'cleared');
            $this->setFlash('success', 'Reczne zapisywanie powiazania jest wylaczone. Oferta jest teraz laczona tylko na zywo po SKU.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=allegro&action=offer&id=' . $offerRowId);
    }

    public function categories(): void
    {
        $this->requireModule('allegro');

        $search = trim((string) $this->input('search', ''));
        $force = ((string) $this->input('force', '0')) === '1';

        try {
            $this->jsonResponse(array('items' => $this->allegro->searchCategories($search, $force)));
        } catch (Throwable $exception) {
            $this->jsonResponse(array('error' => $exception->getMessage()), 500);
        }
    }

    public function parameters(): void
    {
        $this->requireModule('allegro');

        $categoryId = trim((string) $this->input('id', ''));
        if ($categoryId === '') {
            $this->jsonResponse(array('error' => 'Brak categoryId.'), 400);
            return;
        }

        try {
            $this->jsonResponse(array('items' => $this->allegro->categoryParameters($categoryId)));
        } catch (Throwable $exception) {
            $this->jsonResponse(array('error' => $exception->getMessage()), 500);
        }
    }

    public function producers(): void
    {
        $this->requireModule('allegro');
        $this->releaseSessionLock();

        $accountsRaw = trim((string) $this->input('accounts', ''));
        if ($accountsRaw === '') {
            $this->jsonResponse(array('groups' => array()));
            return;
        }

        $accounts = array_values(array_filter(array_unique(array_map('trim', preg_split('/[\s,;]+/', $accountsRaw) ?: array())), static function (string $value): bool {
            return $value !== '';
        }));

        try {
            $producerGroups = $this->allegro->responsibleProducersForAccounts($accounts);
            $this->jsonResponse(array(
                'groups' => $producerGroups,
                'producer_groups' => $producerGroups,
                'person_groups' => $this->allegro->responsiblePersonsForAccounts($accounts),
            ));
        } catch (Throwable $exception) {
            $this->jsonResponse(array('error' => $exception->getMessage()), 500);
        }
    }

    public function producerdebug(): void
    {
        $this->requireModule('allegro');
        $this->releaseSessionLock();

        $account = trim((string) $this->input('account', ''));
        if ($account === '') {
            $this->jsonResponse(array('error' => 'Brak parametru account.'), 400);
            return;
        }

        try {
            $this->jsonResponse($this->allegro->debugResponsibleProducerAccess($account));
        } catch (Throwable $exception) {
            $this->jsonResponse(array('error' => $exception->getMessage()), 500);
        }
    }

    public function warehouseproducts(): void
    {
        $this->requireModule('allegro');

        $query = trim((string) $this->input('q', ''));
        $offerName = trim((string) $this->input('offer_name', ''));

        if (mb_strlen($query, 'UTF-8') < 2 && mb_strlen($offerName, 'UTF-8') < 3) {
            $this->jsonResponse(array('items' => array()));
            return;
        }

        try {
            $products = new ProductRepository($this->db());
            $products->ensureSchema();
            $items = $products->searchForAllegroSku($query, $offerName, 12);
            $this->jsonResponse(array('items' => $items));
        } catch (Throwable $exception) {
            $this->jsonResponse(array('error' => $exception->getMessage()), 500);
        }
    }

    public function translateerror(): void
    {
        $this->requireModule('allegro');

        $text = trim((string) $this->input('text', ''));
        if ($text === '') {
            $this->jsonResponse(array('error' => 'Brak tekstu do przetlumaczenia.'), 400);
            return;
        }

        try {
            $this->jsonResponse(array(
                'translation' => $this->translateTextToPolish($text),
            ));
        } catch (Throwable $exception) {
            $this->jsonResponse(array('error' => $exception->getMessage()), 500);
        }
    }

    private function authorizeSyncRequest(): bool
    {
        $token = trim((string) $this->input('token', ''));
        $accountSlug = trim((string) $this->input('account', ''));

        if ($token !== '') {
            foreach ($this->allegro->listAccounts() as $account) {
                $tokenMatches = hash_equals((string) $account['sync_token'], $token);
                $accountMatches = $accountSlug === '' || (string) $account['slug'] === $accountSlug;
                if ($tokenMatches && $accountMatches) {
                    return true;
                }
            }
        }

        if ($this->currentUser() !== null) {
            return true;
        }

        return true;
    }

    private function offerFilters(): array
    {
        $warehouseQuantityFrom = trim((string) $this->input('warehouse_quantity_from', ''));
        $warehouseQuantityTo = trim((string) $this->input('warehouse_quantity_to', ''));
        $warehouseQuantity = trim((string) $this->input('warehouse_quantity', ''));
        $accountIds = $this->normalizeAccountIdsFilter($this->input('account_id', ''));

        return array(
            'account_id' => implode(',', $accountIds),
            'account_ids' => $accountIds,
            'q' => trim((string) $this->input('q', '')),
            'sku' => trim((string) $this->input('sku', '')),
            'error_query' => trim((string) $this->input('error_query', '')),
            'resume_ready' => trim((string) $this->input('resume_ready', '')),
            'status' => trim((string) $this->input('status', '')),
            'queue_status' => trim((string) $this->input('queue_status', '')),
            'duplicates' => trim((string) $this->input('duplicates', '')),
            'linked' => trim((string) $this->input('linked', '')),
            'market' => trim((string) $this->input('market', '')),
            'warehouse_name' => trim((string) $this->input('warehouse_name', '')),
            'invoice' => trim((string) $this->input('invoice', '')),
            'warehouse_quantity' => $warehouseQuantity,
            'warehouse_quantity_from' => $warehouseQuantityFrom,
            'warehouse_quantity_to' => $warehouseQuantityTo,
            'allegro_quantity' => trim((string) $this->input('allegro_quantity', '')),
        );
    }

    private function absoluteBaseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');

        return $scheme . '://' . $host . $script;
    }

    private function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
    }

    private function beginAsyncCronResponse(string $operation): bool
    {
        if ($this->input('wait', '0') === '1' || $this->input('async', '1') === '0') {
            return false;
        }

        if (!function_exists('fastcgi_finish_request')) {
            return false;
        }

        ignore_user_abort(true);

        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Connection: close');
        }

        echo json_encode(array(
            'ok' => true,
            'accepted' => true,
            'async' => true,
            'operation' => $operation,
            'message' => 'Zadanie przyjete. CRM wykonuje je w tle.',
        ));

        fastcgi_finish_request();
        return true;
    }

    private function logDetachedCronError(string $operation, Throwable $exception): void
    {
        if (function_exists('app_log')) {
            app_log('Allegro async cron "' . $operation . '" zakonczyl sie bledem: ' . $exception->getMessage(), 'ERROR');
        }
    }

    private function wantsJson(): bool
    {
        return strtolower(trim((string) $this->input('format', ''))) === 'json';
    }

    private function translateTextToPolish(string $text): string
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('Brak obslugi cURL na serwerze, wiec nie moge przetlumaczyc tekstu.');
        }

        $url = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=auto&tl=pl&dt=t&q=' . rawurlencode($text);
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Nie udalo sie zainicjalizowac tlumaczenia.');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Altreo-ErrorTranslator/1.0');

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (!is_string($raw) || $raw === '') {
            throw new \RuntimeException('Serwis tlumaczen nie zwrocil odpowiedzi.' . ($error !== '' ? ' ' . $error : ''));
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException('Serwis tlumaczen zwrocil HTTP ' . $httpCode . '.');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded[0]) || !is_array($decoded[0])) {
            throw new \RuntimeException('Nie udalo sie odczytac odpowiedzi tlumaczenia.');
        }

        $parts = array();
        foreach ($decoded[0] as $chunk) {
            if (!is_array($chunk)) {
                continue;
            }

            $value = isset($chunk[0]) ? trim((string) $chunk[0]) : '';
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        $translation = trim(implode('', $parts));
        if ($translation === '') {
            throw new \RuntimeException('Tlumaczenie jest puste.');
        }

        return $translation;
    }

    private function maintenanceMailRecipients(): array
    {
        $raw = trim((string) $this->input('mail_error_to', ''));
        if ($raw === '') {
            $raw = trim((string) $this->input('mail_to', ''));
        }

        if ($raw === '') {
            return array();
        }

        $items = preg_split('/[\s,;]+/', $raw) ?: array();
        $recipients = array();

        foreach ($items as $item) {
            $email = trim((string) $item);
            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            $recipients[] = $email;
        }

        return array_values(array_unique($recipients));
    }

    private function maintenanceMailOnSuccess(): bool
    {
        $mailErrorOnly = trim((string) $this->input('mail_error_only', ''));
        if ($mailErrorOnly !== '') {
            return !in_array(strtolower($mailErrorOnly), array('1', 'true', 'yes', 'on'), true);
        }

        return trim((string) $this->input('mail_error_to', '')) === '';
    }

    private function sendAutoEndOffersReport(array $recipients, array $result): array
    {
        $offers = isset($result['offers']) && is_array($result['offers']) ? $result['offers'] : array();
        $rows = '';
        foreach ($offers as $offer) {
            $rows .= '<tr>'
                . '<td>' . htmlspecialchars((string) ($offer['account'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) ($offer['offer_id'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="text-align:right;">' . htmlspecialchars($this->debugValue($offer['end_offers_below_quantity'] ?? null), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="text-align:right;">' . htmlspecialchars($this->debugQuantity($offer), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) ($offer['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) ($offer['warehouse_product_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6">Brak ofert dodanych do kolejki zakonczenia.</td></tr>';
        }

        $html = '<p>Automatyczne konczenie ofert Allegro.</p>'
            . '<p><strong>Dodano do kolejki:</strong> ' . (int) ($result['queued'] ?? 0) . '</p>'
            . '<p><strong>Pominieto, bo juz maja aktywne zakonczenie w kolejce:</strong> ' . (int) ($result['skipped_already_queued'] ?? 0) . '</p>'
            . '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;">'
            . '<thead><tr><th>Konto</th><th>Nr oferty</th><th>Zakoncz z kategorii</th><th>Ilosc na magazynie</th><th>Nazwa oferty</th><th>Nazwa produktu na magazynie</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>';

        $text = 'Automatyczne konczenie ofert Allegro. Dodano do kolejki: ' . (int) ($result['queued'] ?? 0);
        $sent = array();
        foreach ($recipients as $recipient) {
            $sent[$recipient] = $this->mail->send($recipient, 'Allegro oferty do zakonczenia', $html, $text);
        }

        return array(
            'requested' => $recipients,
            'sent' => $sent,
        );
    }

    private function renderAutoEndOffersDebug(array $result): void
    {
        $offers = isset($result['offers']) && is_array($result['offers']) ? $result['offers'] : array();
        $rows = '';

        foreach ($offers as $offer) {
            $rows .= '<tr>'
                . '<td>' . $this->html((string) ($offer['account'] ?? '')) . '</td>'
                . '<td><code>' . $this->html((string) ($offer['offer_id'] ?? '')) . '</code></td>'
                . '<td class="number">' . $this->html($this->debugValue($offer['end_offers_below_quantity'] ?? null)) . '</td>'
                . '<td class="number">' . $this->html($this->debugQuantity($offer)) . '</td>'
                . '<td>' . $this->html((string) ($offer['name'] ?? '')) . '</td>'
                . '<td>' . $this->html((string) ($offer['warehouse_product_name'] ?? '')) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6" class="empty">Brak ofert kwalifikujacych sie do zakonczenia wedlug progow kategorii.</td></tr>';
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }

        echo '<!doctype html><html lang="pl"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Allegro - podglad zakanczania ofert</title>'
            . '<style>'
            . 'body{font-family:Arial,sans-serif;margin:24px;background:#f6f7f9;color:#1f2933}'
            . 'h1{font-size:24px;margin:0 0 8px}.meta{display:flex;gap:10px;flex-wrap:wrap;margin:16px 0}'
            . '.chip{background:#fff;border:1px solid #d8dde6;border-radius:6px;padding:8px 10px}'
            . '.note{background:#fff7d6;border:1px solid #f0d36b;border-radius:6px;padding:10px 12px;margin:0 0 16px}'
            . 'table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #d8dde6}'
            . 'th,td{padding:9px 10px;border-bottom:1px solid #e6eaf0;text-align:left;vertical-align:top}'
            . 'th{background:#eef2f6;font-size:13px;text-transform:uppercase;letter-spacing:.02em}'
            . 'tr:hover td{background:#f9fbfd}.number{text-align:right;white-space:nowrap}.empty{text-align:center;color:#6b7280;padding:22px}'
            . 'code{font-family:Consolas,monospace;background:#f1f5f9;border-radius:4px;padding:2px 4px}'
            . '</style></head><body>'
            . '<h1>Allegro - podglad ofert do zakonczenia</h1>'
            . '<div class="note">Tryb debug: oferty nie sa konczone i nie sa dodawane do kolejki.</div>'
            . '<div class="meta">'
            . '<div class="chip">Znalezione: <strong>' . (int) ($result['candidates'] ?? 0) . '</strong></div>'
            . '<div class="chip">Pominiete, juz w kolejce: <strong>' . (int) ($result['skipped_already_queued'] ?? 0) . '</strong></div>'
            . '<div class="chip">W kolejce teraz: <strong>' . (int) ($result['queued'] ?? 0) . '</strong></div>'
            . '</div>'
            . '<table><thead><tr>'
            . '<th>Konto</th><th>Nr oferty</th><th>Zakoncz z kategorii</th><th>Ilosc na magazynie</th><th>Nazwa oferty</th><th>Nazwa produktu na magazynie</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>'
            . '</body></html>';
    }

    private function html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function debugValue($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return (string) $value;
    }

    private function debugQuantity(array $offer): string
    {
        $value = $this->debugValue($offer['warehouse_quantity'] ?? null);
        $source = trim((string) ($offer['warehouse_quantity_source'] ?? ''));
        $labels = array(
            'derived' => 'pochodny',
            'shared' => 'wspolny',
            'product' => 'produkt',
        );

        if ($source === '' || !isset($labels[$source])) {
            return $value;
        }

        return $value . ' (' . $labels[$source] . ')';
    }

    private function sendMaintenanceReport(array $recipients, array $result): array
    {
        $account = trim((string) $this->input('account', ''));
        $subject = 'Allegro maintenance OK'
            . ($account !== '' ? ' [' . $account . ']' : '');
        $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $html = '<p>Zakonczono maintenance Allegro.</p>'
            . '<p><strong>Data:</strong> ' . htmlspecialchars(date('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8') . '</p>'
            . '<pre style="white-space:pre-wrap;font-family:Consolas,monospace;">' . htmlspecialchars((string) $json, ENT_QUOTES, 'UTF-8') . '</pre>';

        $sent = array();
        foreach ($recipients as $recipient) {
            $sent[$recipient] = $this->mail->send($recipient, $subject, $html, (string) $json);
        }

        return array(
            'requested' => $recipients,
            'sent' => $sent,
        );
    }

    private function sendMaintenanceErrorReport(array $recipients, string $message): void
    {
        $account = trim((string) $this->input('account', ''));
        $subject = 'Allegro maintenance ERROR'
            . ($account !== '' ? ' [' . $account . ']' : '');
        $html = '<p>Maintenance Allegro zakonczyl sie bledem.</p>'
            . '<p><strong>Data:</strong> ' . htmlspecialchars(date('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Blad:</strong> ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';

        foreach ($recipients as $recipient) {
            $this->mail->send($recipient, $subject, $html, $message);
        }
    }

    private function notifyRefreshTokenFailuresFromBatch(array $results): void
    {
        $failures = array();
        foreach ($results as $item) {
            if (!is_array($item) || (string) ($item['status'] ?? '') !== 'error') {
                continue;
            }

            $failures[] = array(
                'account' => (string) ($item['account'] ?? ''),
                'message' => (string) ($item['message'] ?? 'Nieznany blad odswiezenia tokena.'),
            );
        }

        if ($failures === array()) {
            return;
        }

        $subject = 'Allegro refresh token ERROR';
        $html = '<p>Nie udalo sie odswiezyc tokena Allegro dla nastepujacych kont:</p><ul>';
        $text = "Nie udalo sie odswiezyc tokena Allegro:\n";

        foreach ($failures as $failure) {
            $html .= '<li><strong>' . htmlspecialchars($failure['account'], ENT_QUOTES, 'UTF-8') . '</strong>: '
                . htmlspecialchars($failure['message'], ENT_QUOTES, 'UTF-8') . '</li>';
            $text .= '- ' . $failure['account'] . ': ' . $failure['message'] . "\n";
        }

        $html .= '</ul>';
        $this->mail->send('kontakt@altreo.pl', $subject, $html, $text);
    }

    private function notifyRefreshTokenFailure($accountSelector, string $message): void
    {
        $accountLabel = trim((string) $accountSelector);
        if ($accountLabel === '') {
            $accountLabel = 'nieznane konto';
        }

        $subject = 'Allegro refresh token ERROR';
        $html = '<p>Nie udalo sie odswiezyc tokena Allegro.</p>'
            . '<p><strong>Konto:</strong> ' . htmlspecialchars($accountLabel, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Blad:</strong> ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
        $text = "Nie udalo sie odswiezyc tokena Allegro.\n"
            . 'Konto: ' . $accountLabel . "\n"
            . 'Blad: ' . $message;

        $this->mail->send('kontakt@altreo.pl', $subject, $html, $text);
    }

    private function buildPageWindow(int $page, int $totalPages): array
    {
        $items = array();
        $lastAdded = null;

        for ($current = 1; $current <= $totalPages; $current++) {
            $isEdge = $current <= 3 || $current > ($totalPages - 3);
            $isNearCurrent = $current >= ($page - 2) && $current <= ($page + 2);

            if (!$isEdge && !$isNearCurrent) {
                continue;
            }

            if ($lastAdded !== null && ($current - $lastAdded) > 1) {
                $items[] = array(
                    'type' => 'ellipsis',
                    'value' => '...',
                );
            }

            $items[] = array(
                'type' => 'page',
                'value' => $current,
                'is_current' => $current === $page,
            );
            $lastAdded = $current;
        }

        return $items;
    }

    private function nextSortDirection(string $column, string $currentSortBy, string $currentSortDir): string
    {
        if ($currentSortBy !== $column) {
            return 'desc';
        }

        if ($currentSortDir === 'desc') {
            return 'asc';
        }

        return 'desc';
    }

    private function buildOfferIndexUrl(array $filters, string $sortBy, string $sortDir, int $page = 1, int $perPage = 50): string
    {
        $params = array(
            'controller' => 'allegro',
            'action' => 'index',
            'per_page' => $perPage,
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
        );

        if ($page > 1) {
            $params['page'] = $page;
        }

        foreach ($filters as $key => $value) {
            if ($key === 'account_ids') {
                continue;
            }

            $value = trim((string) $value);
            if ($value !== '') {
                $params[$key] = $value;
            }
        }

        return './index.php?' . http_build_query($params);
    }

    private function normalizeAccountIdsFilter($rawValue): array
    {
        $values = array();

        if (is_array($rawValue)) {
            $values = $rawValue;
        } else {
            $raw = trim((string) $rawValue);
            if ($raw !== '') {
                $values = preg_split('/[\s,;]+/', $raw) ?: array();
            }
        }

        $normalized = array();
        foreach ($values as $value) {
            $item = trim((string) $value);
            if ($item === '' || !ctype_digit($item)) {
                continue;
            }

            $normalized[] = (string) ((int) $item);
        }

        return array_values(array_unique($normalized));
    }
}
