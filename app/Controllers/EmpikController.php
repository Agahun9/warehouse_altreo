<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\SettingRepository;
use App\Services\EmpikRateLimitException;
use App\Services\EmpikService;
use Throwable;

class EmpikController extends Controller
{
    private const MAINTENANCE_LOCK_TTL_SECONDS = 900;

    /** @var EmpikService */
    private $empik;

    public function __construct()
    {
        $this->empik = new EmpikService();
    }

    public function index(): void
    {
        $currentUser = $this->requireModule('empik');
        $flashSuccess = $this->getFlash('success');
        $flashError = $this->getFlash('error');
        $this->releaseSessionLock();

        $filters = $this->offerFilters();
        $page = max(1, (int) $this->input('page', 1));
        $allowedPerPage = array(50, 100, 200, 5000);
        $perPageInput = (int) $this->input('per_page', 50);
        $perPage = in_array($perPageInput, $allowedPerPage, true) ? $perPageInput : 50;
        $sortBy = trim((string) $this->input('sort_by', 'synced'));
        $sortDir = strtolower(trim((string) $this->input('sort_dir', 'desc')));
        if ($sortDir !== 'asc' && $sortDir !== 'desc') {
            $sortDir = 'desc';
        }

        $total = $this->empik->countOffers($filters);
        $offers = $this->empik->offersPage($filters, $page, $perPage, $sortBy, $sortDir);
        $totalPages = max(1, (int) ceil($total / max(1, $perPage)));

        if ($page > $totalPages) {
            $page = $totalPages;
            $offers = $this->empik->offersPage($filters, $page, $perPage, $sortBy, $sortDir);
        }

        $pageWindow = $this->buildPageWindow($page, $totalPages);
        $sortableColumns = array('id', 'account', 'title', 'sku', 'product_sku', 'category', 'state', 'active', 'quantity', 'warehouse_quantity', 'price', 'queue_status', 'synced', 'updated');
        $sortIndicators = array();
        $sortUrls = array();

        foreach ($sortableColumns as $column) {
            $sortIndicators[$column] = ($sortBy === $column) ? $sortDir : '';
            $sortUrls[$column] = $this->buildOfferIndexUrl($filters, $column, $this->nextSortDirection($column, $sortBy, $sortDir), 1, $perPage);
        }

        $currentListUrl = $this->buildOfferIndexUrl($filters, $sortBy, $sortDir, $page, $perPage);

        $this->render('empik/index', array(
            'pageTitle' => 'Empik',
            'contentTitle' => 'Integracja Empik Marketplace',
            'pageDescription' => 'Mirakl Seller API: konta, filtrowanie, worker kolejki i masowe akcje na ofertach Empik.',
            'breadcrumbCurrent' => 'Empik',
            'currentUser' => $currentUser,
            'flashSuccess' => $flashSuccess,
            'flashError' => $flashError,
            'accounts' => $this->empik->listAccounts(),
            'offers' => $offers,
            'filters' => $filters,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'sortIndicators' => $sortIndicators,
            'sortUrls' => $sortUrls,
            'queueStats' => $this->empik->queueCounts(),
            'page' => $page,
            'perPage' => $perPage,
            'totalOffers' => $total,
            'totalPages' => $totalPages,
            'pageWindow' => $pageWindow,
            'currentListUrl' => $currentListUrl,
        ));
    }

    public function saveaccount(): void
    {
        $this->requireRole('admin');
        $this->requireWriteAccess();

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=empik&action=index');
        }

        try {
            $accountId = (int) $this->input('account_id', 0);
            $this->empik->saveAccount(array(
                'name' => $this->input('name', ''),
                'api_url' => $this->input('api_url', ''),
                'api_key' => $this->input('api_key', ''),
                'shop_id' => $this->input('shop_id', ''),
                'locale' => $this->input('locale', 'pl_PL'),
                'is_active' => $this->input('is_active', '0') === '1' ? 1 : 0,
            ), $accountId > 0 ? $accountId : null);

            $this->setFlash('success', 'Konto Empik zostalo zapisane.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=empik&action=index');
    }

    public function sync(): void
    {
        if (!$this->wantsJson()) {
            $this->requireModuleWrite('empik');
        }
        $this->releaseSessionLock();

        // Kept short on purpose: this endpoint runs inside a normal web worker (PHP-FPM /
        // Apache), and shared hosting typically only has a handful of those. A multi-minute
        // request here ties one up for that whole time and, with a small worker pool, can
        // make the entire admin panel unresponsive for everyone else. The sync is fully
        // resumable (see EmpikService::syncAccount), so many short ticks are just as
        // effective as one long one and don't block the panel.
        if (function_exists('set_time_limit')) {
            @set_time_limit(60);
        }

        try {
            $result = $this->empik->syncAccount(trim((string) $this->input('account', '')), array(
                'max_runtime' => max(5, min(240, (int) $this->input('max_runtime', 12))),
            ));
            if ($this->wantsJson()) {
                $this->jsonResponse($result);
                return;
            }

            $this->setFlash(
                'success',
                'Synchronizacja Empik zakonczona dla konta "' . (string) $result['account']['name'] . '". Pobrano ' . (int) $result['synced_offers'] . ' ofert.'
            );
        } catch (Throwable $exception) {
            if ($this->wantsJson()) {
                $this->jsonResponse(array('error' => $exception->getMessage()), 500);
                return;
            }
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=empik&action=index');
    }

    public function queue(): void
    {
        $this->requireModuleWrite('empik');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=empik&action=index');
        }

        $this->releaseSessionLock();

        try {
            $selectionScope = trim((string) $this->input('selection_scope', 'filtered'));
            $selectedOfferIds = $this->input('selected_offer_ids', array());
            $selectedIds = array();

            if ($selectionScope === 'selected' && is_array($selectedOfferIds)) {
                $selectedIds = array_values(array_filter(array_map('intval', $selectedOfferIds)));
            }

            $result = $this->empik->enqueueOfferChanges(
                $this->offerFilters(),
                trim((string) $this->input('operation', '')),
                array(
                    'value' => $this->input('value', ''),
                    'search' => $this->input('search', ''),
                    'replace' => $this->input('replace', ''),
                    'selection_limit' => (int) $this->input('selection_limit', 1000),
                ),
                $selectedIds
            );

            if ((string) ($result['operation'] ?? '') === 'clear_queue') {
                $this->setFlash('success', 'Usunieto z kolejki: ' . (int) ($result['removed'] ?? 0) . ' wpisow.');
            } elseif ((string) ($result['operation'] ?? '') === 'remove_from_system') {
                $this->setFlash('success', 'Usunieto lokalnie z systemu: ' . (int) ($result['removed'] ?? 0) . ' ofert.');
            } else {
                $this->setFlash('success', 'Dodano do kolejki Empik: ' . (int) ($result['queued'] ?? 0) . ' ofert.');
            }
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $returnUrl = trim((string) $this->input('return_url', ''));
        if ($returnUrl !== '' && strpos($returnUrl, './index.php?') === 0) {
            $this->redirect($returnUrl);
        }

        $this->redirect('./index.php?' . http_build_query(array_merge(
            array('controller' => 'empik', 'action' => 'index'),
            $this->offerFilters()
        )));
    }

    public function processqueue(): void
    {
        if (!$this->wantsQueueCron()) {
            $this->requireModuleWrite('empik');
        }
        $this->releaseSessionLock();

        // See sync() above for why this is kept short: a long-running request here holds a
        // web worker (and often, on shared hosting, a big chunk of the whole worker pool)
        // for its whole duration and can freeze the panel for other users. Unprocessed rows
        // simply stay "pending"/"retry" and get picked up on the next tick.
        if (function_exists('set_time_limit')) {
            @set_time_limit(70);
        }

        try {
            $result = $this->empik->processQueue(array(
                'account' => $this->input('account', ''),
                'limit' => (int) $this->input('limit', 20),
                'max_runtime' => max(5, min(280, (int) $this->input('max_runtime', 45))),
            ));

            if ($this->wantsQueueCron()) {
                $this->jsonResponse($result);
                return;
            }

            $this->setFlash('success', 'Kolejka Empik przetworzona. OK: ' . (int) ($result['done'] ?? 0));
        } catch (Throwable $exception) {
            if ($this->wantsQueueCron()) {
                $this->jsonResponse(array('error' => $exception->getMessage()), 500);
                return;
            }
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=empik&action=index');
    }

    public function maintenance(): void
    {
        if (!$this->wantsJson()) {
            $this->requireModuleWrite('empik');
        }
        $this->releaseSessionLock();

        // Sync + enqueue + queue processing below are all individually time-boxed (see
        // EmpikService) and kept short on purpose: this runs inside a normal web worker
        // (PHP-FPM/Apache), and shared hosting typically only has a handful of those. A
        // multi-minute request here ties one up for its whole duration and, with a small
        // worker pool, can make the entire admin panel unresponsive for everyone else. The
        // sync/queue work is fully resumable, so many short cron ticks make the same
        // progress as one long one without blocking the panel. set_time_limit is a safety
        // margin above the sum of the budgets below, not the primary control - harmless
        // no-op if the host disables it.
        if (function_exists('set_time_limit')) {
            @set_time_limit(90);
        }

        $settings = new SettingRepository($this->db());
        $settings->ensureSchema();
        $lockKey = 'empik_maintenance_lock';
        $lockedAt = (int) $settings->get($lockKey, '0');

        if ($lockedAt > 0 && (time() - $lockedAt) < self::MAINTENANCE_LOCK_TTL_SECONDS) {
            $message = 'Poprzednie zadanie maintenance Empik jeszcze trwa (start: ' . date('H:i:s', $lockedAt) . '), pomijam to wywolanie.';
            if ($this->wantsJson()) {
                $this->jsonResponse(array('skipped' => true, 'reason' => $message));
                return;
            }
            $this->setFlash('error', $message);
            $this->redirect('./index.php?controller=empik&action=index');
            return;
        }

        $settings->set($lockKey, (string) time());

        try {
            $accountSelector = trim((string) $this->input('account', ''));
            $result = array(
                'queue' => null,
                'sync' => array(),
                'enqueue' => null,
            );

            $syncOptions = array(
                'max_runtime' => max(5, min(240, (int) $this->input('sync_account_runtime', 12))),
            );

            if ($this->input('sync', '0') === '1') {
                if ($accountSelector !== '') {
                    try {
                        $result['sync'][] = $this->empik->syncAccount($accountSelector, $syncOptions);
                    } catch (EmpikRateLimitException $exception) {
                        $result['sync'][] = array(
                            'account' => $accountSelector,
                            'error' => $exception->getMessage(),
                            'rate_limited' => true,
                        );
                    }
                } else {
                    $maxAccounts = max(1, min(50, (int) $this->input('max_accounts', 50)));
                    // Aggregate budget across every account synced in this single request, on
                    // top of each account's own per-call budget: without it, a maintenance run
                    // with several accounts (or one account with several full-catalog pages)
                    // could keep going well past the next cron tick or the host's execution
                    // time limit. accountsDueForSync() (oldest-synced-first) means whichever
                    // accounts don't fit this time get priority on the next tick instead of
                    // the same early accounts starving the rest forever.
                    $syncBudgetSeconds = max(10, min(300, (int) $this->input('sync_budget', 25)));
                    $syncBudgetStart = time();
                    $processedAccounts = 0;

                    foreach ($this->empik->accountsDueForSync() as $account) {
                        if ($processedAccounts >= $maxAccounts) {
                            break;
                        }
                        if ((time() - $syncBudgetStart) >= $syncBudgetSeconds) {
                            break;
                        }

                        $accountSlug = (string) ($account['slug'] ?? '');
                        try {
                            $result['sync'][] = $this->empik->syncAccount($accountSlug, $syncOptions);
                        } catch (EmpikRateLimitException $exception) {
                            $result['sync'][] = array(
                                'account' => $accountSlug,
                                'error' => $exception->getMessage(),
                                'rate_limited' => true,
                            );
                        }
                        $processedAccounts++;
                    }
                }
            }

            $enqueueOperations = $this->input('enqueue', '');
            if (trim((string) $enqueueOperations) !== '') {
                $operations = array_values(array_filter(array_map('trim', explode(',', (string) $enqueueOperations))));
                $result['enqueue'] = $this->empik->enqueueWarehouseUpdates(
                    $accountSelector,
                    $operations,
                    (int) $this->input('enqueue_limit', 500)
                );
            }

            $queueLimit = (int) $this->input('queue_limit', 50);
            if ($queueLimit > 0) {
                $result['queue'] = $this->empik->processQueue(array(
                    'account' => $accountSelector,
                    'limit' => $queueLimit,
                    'max_runtime' => max(5, min(280, (int) $this->input('queue_max_runtime', 15))),
                ));
            }

            if ($this->wantsJson()) {
                $this->jsonResponse($result);
                return;
            }

            $this->setFlash('success', 'Maintenance Empik zakonczone. Sync kont: ' . count($result['sync']) . '.');
        } catch (Throwable $exception) {
            if ($this->wantsJson()) {
                $this->jsonResponse(array('error' => $exception->getMessage()), 500);
                return;
            }
            $this->setFlash('error', $exception->getMessage());
        } finally {
            $settings->set($lockKey, '');
        }

        $this->redirect('./index.php?controller=empik&action=index');
    }

    public function clearqueue(): void
    {
        $this->requireModuleWrite('empik');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=empik&action=index');
        }

        try {
            $mode = trim((string) $this->input('mode', 'statuses'));
            if ($mode === 'all') {
                $result = $this->empik->clearWholeQueue();
                $this->setFlash('success', 'Usunieto cala kolejke Empik: ' . (int) ($result['removed'] ?? 0) . ' wpisow.');
            } else {
                $result = $this->empik->clearQueueStatuses(true);
                $this->setFlash('success', 'Wyczyszczono statusy zakonczonych pozycji kolejki Empik: ' . (int) ($result['removed'] ?? 0) . ' wpisow.');
            }
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=empik&action=index');
    }

    public function offer(): void
    {
        $this->requireModule('empik');

        $id = (int) $this->input('id', 0);
        $offer = $this->empik->offerDetails($id);
        if (!$offer) {
            $this->setFlash('error', 'Nie znaleziono oferty Empik.');
            $this->redirect('./index.php?controller=empik&action=index');
        }

        $decoded = array();
        if (!empty($offer['offer_json'])) {
            $decoded = json_decode((string) $offer['offer_json'], true);
            if (!is_array($decoded)) {
                $decoded = array();
            }
        }

        $this->render('empik/offer', array(
            'pageTitle' => 'Oferta Empik',
            'contentTitle' => (string) ($offer['product_title'] ?? ('Oferta #' . $offer['offer_id'])),
            'pageDescription' => 'Podglad zapisanej oferty z Empik Marketplace.',
            'breadcrumbCurrent' => 'Oferta Empik',
            'offer' => $offer,
            'offerData' => $decoded,
            'offerJsonPretty' => json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ));
    }

    public function categories(): void
    {
        $this->requireModule('empik');

        $search = trim((string) $this->input('search', ''));
        $force = ((string) $this->input('force', '0')) === '1';
        $accountId = (int) $this->input('account_id', 0);

        try {
            $this->jsonResponse(array('items' => $this->empik->searchCategories($search, $force, $accountId > 0 ? $accountId : null)));
        } catch (Throwable $exception) {
            $this->jsonResponse(array('error' => $exception->getMessage()), 500);
        }
    }

    private function offerFilters(): array
    {
        return array(
            'account_id' => trim((string) $this->input('account_id', '')),
            'q' => trim((string) $this->input('q', '')),
            'sku' => trim((string) $this->input('sku', '')),
            'state' => trim((string) $this->input('state', '')),
            'active' => trim((string) $this->input('active', '')),
            'queue_status' => trim((string) $this->input('queue_status', '')),
            'error_query' => trim((string) $this->input('error_query', '')),
            'linked' => trim((string) $this->input('linked', '')),
            'warehouse_quantity_from' => trim((string) $this->input('warehouse_quantity_from', '')),
            'warehouse_quantity_to' => trim((string) $this->input('warehouse_quantity_to', '')),
        );
    }

    private function buildOfferIndexUrl(array $filters, string $sortBy, string $sortDir, int $page = 1, int $perPage = 50): string
    {
        $query = array(
            'controller' => 'empik',
            'action' => 'index',
            'page' => $page,
            'per_page' => $perPage,
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
        );

        foreach ($filters as $key => $value) {
            $query[$key] = $value;
        }

        return './index.php?' . http_build_query($query);
    }

    private function nextSortDirection(string $column, string $sortBy, string $sortDir): string
    {
        if ($column === $sortBy) {
            return $sortDir === 'asc' ? 'desc' : 'asc';
        }

        return 'asc';
    }

    private function buildPageWindow(int $page, int $totalPages): array
    {
        $items = array();
        $pages = array_unique(array_filter(array(1, 2, $page - 1, $page, $page + 1, $totalPages - 1, $totalPages), static function (int $item) use ($totalPages): bool {
            return $item >= 1 && $item <= $totalPages;
        }));
        sort($pages);

        $previous = 0;
        foreach ($pages as $pageNumber) {
            if ($previous > 0 && $pageNumber > $previous + 1) {
                $items[] = array('type' => 'ellipsis');
            }

            $items[] = array(
                'type' => 'page',
                'value' => $pageNumber,
                'is_current' => $pageNumber === $page,
            );
            $previous = $pageNumber;
        }

        return $items;
    }

    private function wantsJson(): bool
    {
        return strtolower(trim((string) $this->input('format', ''))) === 'json';
    }

    private function wantsQueueCron(): bool
    {
        return $this->wantsJson() || strtolower(trim((string) $this->input('action', ''))) === 'processqueue';
    }

    private function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
    }
}
