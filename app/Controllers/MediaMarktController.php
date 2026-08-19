<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\MediaMarktRateLimitException;
use App\Services\MediaMarktService;
use Throwable;

class MediaMarktController extends Controller
{
    private const MAINTENANCE_LOCK_NAME = 'altreo_mediamarkt_maintenance';
    private const QUEUE_LOCK_NAME = 'altreo_mediamarkt_queue_worker';

    /** @var MediaMarktService */
    private $mediamarkt;

    public function __construct()
    {
        $this->mediamarkt = new MediaMarktService();
    }

    public function index(): void
    {
        $currentUser = $this->requireModule('mediamarkt');
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

        $total = $this->mediamarkt->countOffers($filters);
        $offers = $this->mediamarkt->offersPage($filters, $page, $perPage, $sortBy, $sortDir);
        $totalPages = max(1, (int) ceil($total / max(1, $perPage)));

        if ($page > $totalPages) {
            $page = $totalPages;
            $offers = $this->mediamarkt->offersPage($filters, $page, $perPage, $sortBy, $sortDir);
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

        $this->render('mediamarkt/index', array(
            'pageTitle' => 'MediaMarkt',
            'contentTitle' => 'Integracja MediaMarkt Marketplace',
            'pageDescription' => 'Mirakl Seller API: konta, filtrowanie, worker kolejki i masowe akcje na ofertach MediaMarkt.',
            'breadcrumbCurrent' => 'MediaMarkt',
            'currentUser' => $currentUser,
            'flashSuccess' => $flashSuccess,
            'flashError' => $flashError,
            'accounts' => $this->mediamarkt->listAccounts(),
            'offers' => $offers,
            'filters' => $filters,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'sortIndicators' => $sortIndicators,
            'sortUrls' => $sortUrls,
            'queueStats' => $this->mediamarkt->queueCounts(),
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
            $this->redirect('./index.php?controller=mediamarkt&action=index');
        }

        try {
            $accountId = (int) $this->input('account_id', 0);
            $this->mediamarkt->saveAccount(array(
                'name' => $this->input('name', ''),
                'api_url' => $this->input('api_url', ''),
                'api_key' => $this->input('api_key', ''),
                'shop_id' => $this->input('shop_id', ''),
                'locale' => $this->input('locale', 'de_DE'),
                'is_active' => $this->input('is_active', '0') === '1' ? 1 : 0,
            ), $accountId > 0 ? $accountId : null);

            $this->setFlash('success', 'Konto MediaMarkt zostalo zapisane.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=mediamarkt&action=index');
    }

    public function sync(): void
    {
        if (!$this->wantsJson()) {
            $this->requireModuleWrite('mediamarkt');
        }
        $this->releaseSessionLock();

        // Kept short on purpose: this endpoint runs inside a normal web worker (PHP-FPM /
        // Apache), and shared hosting typically only has a handful of those. A multi-minute
        // request here ties one up for that whole time and, with a small worker pool, can
        // make the entire admin panel unresponsive for everyone else. The sync is fully
        // resumable (see MediaMarktService::syncAccount), so many short ticks are just as
        // effective as one long one and don't block the panel.
        if (function_exists('set_time_limit')) {
            @set_time_limit(60);
        }

        try {
            $result = $this->mediamarkt->syncAccount(trim((string) $this->input('account', '')), array(
                'max_runtime' => max(5, min(240, (int) $this->input('max_runtime', 12))),
            ));
            if ($this->wantsJson()) {
                $this->jsonResponse($result);
                return;
            }

            $this->setFlash(
                'success',
                'Synchronizacja MediaMarkt zakonczona dla konta "' . (string) $result['account']['name'] . '". Pobrano ' . (int) $result['synced_offers'] . ' ofert.'
            );
        } catch (Throwable $exception) {
            if ($this->wantsJson()) {
                $this->jsonResponse(array('error' => $exception->getMessage()), 500);
                return;
            }
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=mediamarkt&action=index');
    }

    public function queue(): void
    {
        $this->requireModuleWrite('mediamarkt');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=mediamarkt&action=index');
        }

        $this->releaseSessionLock();

        try {
            $selectionScope = trim((string) $this->input('selection_scope', 'filtered'));
            $selectedOfferIds = $this->input('selected_offer_ids', array());
            $selectedIds = array();

            if ($selectionScope === 'selected' && is_array($selectedOfferIds)) {
                $selectedIds = array_values(array_filter(array_map('intval', $selectedOfferIds)));
            }

            $result = $this->mediamarkt->enqueueOfferChanges(
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
                $this->setFlash('success', 'Dodano do kolejki MediaMarkt: ' . (int) ($result['queued'] ?? 0) . ' ofert.');
            }
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $returnUrl = trim((string) $this->input('return_url', ''));
        if ($returnUrl !== '' && strpos($returnUrl, './index.php?') === 0) {
            $this->redirect($returnUrl);
        }

        $this->redirect('./index.php?' . http_build_query(array_merge(
            array('controller' => 'mediamarkt', 'action' => 'index'),
            $this->offerFilters()
        )));
    }

    public function processqueue(): void
    {
        if (!$this->wantsQueueCron()) {
            $this->requireModuleWrite('mediamarkt');
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
            $result = $this->processMediaMarktQueue(array(
                'account' => $this->input('account', ''),
                'limit' => (int) $this->input('limit', 20),
                'max_runtime' => max(5, min(280, (int) $this->input('max_runtime', 45))),
            ));

            if ($this->wantsQueueCron()) {
                $this->jsonResponse($result);
                return;
            }

            $this->setFlash('success', 'Kolejka MediaMarkt przetworzona. OK: ' . (int) ($result['done'] ?? 0));
        } catch (Throwable $exception) {
            if ($this->wantsQueueCron()) {
                $this->jsonResponse(array('error' => $exception->getMessage()), 500);
                return;
            }
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=mediamarkt&action=index');
    }

    public function maintenance(): void
    {
        if (!$this->wantsJson()) {
            $this->requireModuleWrite('mediamarkt');
        }
        $this->releaseSessionLock();

        // This endpoint is intentionally read-only toward MediaMarkt: it only downloads offers
        // and stores them locally. enqueue/queue_limit parameters in old cron URLs are
        // ignored. The sync is time-boxed because this runs inside a normal web worker
        // (PHP-FPM/Apache), and shared hosting typically only has a handful of those. A
        // multi-minute request here ties one up for its whole duration and, with a small
        // worker pool, can make the entire admin panel unresponsive for everyone else. The
        // sync is fully resumable, so many short cron ticks make the same
        // progress as one long one without blocking the panel. set_time_limit is a safety
        // margin above the sum of the budgets below, not the primary control - harmless
        // no-op if the host disables it.
        if (function_exists('set_time_limit')) {
            @set_time_limit(90);
        }

        // A timestamp stored in app_settings used to guard this endpoint. If PHP-FPM killed
        // the request (timeout/OOM/restart), its finally block never cleared that timestamp
        // and every following cron tick was incorrectly skipped for up to 15 minutes. A
        // MySQL advisory lock is tied to this request's DB connection and is automatically
        // released by MySQL even when the worker dies.
        $database = $this->db();
        $lockAcquired = $database->acquireAdvisoryLock(self::MAINTENANCE_LOCK_NAME);
        $lockRecovered = false;

        // Explicit recovery is deliberately narrow: interrupt only statements run by the
        // connection which currently owns this exact MediaMarkt lock. This is useful once after
        // deploying the server-side statement timeout while a pre-deployment query is still
        // stuck. KILL QUERY leaves the connection alive, so its finally block can clean up.
        if (!$lockAcquired && $this->input('force', '0') === '1') {
            $recoveryDeadline = microtime(true) + 12;
            while (!$lockAcquired && microtime(true) < $recoveryDeadline) {
                $owner = $database->advisoryLockOwner(self::MAINTENANCE_LOCK_NAME);
                if ($owner !== null) {
                    try {
                        $database->interruptConnectionQuery($owner);
                        $lockRecovered = true;
                    } catch (Throwable $exception) {
                        // The owner may have completed between IS_USED_LOCK and KILL QUERY.
                    }
                }
                usleep(250000);
                $lockAcquired = $database->acquireAdvisoryLock(self::MAINTENANCE_LOCK_NAME);
            }
        }

        if (!$lockAcquired) {
            $message = 'Inne zadanie maintenance MediaMarkt rzeczywiscie jest teraz uruchomione; pomijam tylko to wywolanie.';
            if ($this->wantsJson()) {
                $this->jsonResponse(array(
                    'ok' => true,
                    'skipped' => true,
                    'reason' => $message,
                    'checked_at' => date(DATE_ATOM),
                ));
                return;
            }
            $this->setFlash('error', $message);
            $this->redirect('./index.php?controller=mediamarkt&action=index');
            return;
        }

        $startedAt = microtime(true);
        $runId = date('Ymd-His') . '-' . substr(uniqid('', true), -6);
        try {
            $accountSelector = trim((string) $this->input('account', ''));
            $result = array(
                'ok' => true,
                'mode' => 'sync_only',
                'run_id' => $runId,
                'started_at' => date(DATE_ATOM),
                'recovered_previous_run' => $lockRecovered,
                'queue' => array(
                    'disabled' => true,
                    'message' => 'Maintenance nie przetwarza kolejki i nie wysyla zmian do MediaMarktu.',
                ),
                'sync' => array(),
                'local_update_summary' => array(
                    'inserted' => 0,
                    'updated' => 0,
                    'unchanged' => 0,
                ),
                'local_updates' => array(),
                'rate_limited' => false,
                'enqueue' => array(
                    'disabled' => true,
                    'message' => 'Parametr enqueue jest ignorowany; maintenance tylko pobiera oferty.',
                ),
                'remote_write_attempts' => 0,
            );

            $syncOptions = array(
                'max_runtime' => max(5, min(240, (int) $this->input('sync_account_runtime', 12))),
            );

            if ($this->input('sync', '0') === '1') {
                if ($accountSelector !== '') {
                    try {
                        $result['sync'][] = $this->mediamarkt->syncAccount($accountSelector, $syncOptions);
                    } catch (MediaMarktRateLimitException $exception) {
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

                    foreach ($this->mediamarkt->accountsDueForSync() as $account) {
                        if ($processedAccounts >= $maxAccounts) {
                            break;
                        }
                        if ((time() - $syncBudgetStart) >= $syncBudgetSeconds) {
                            break;
                        }

                        $accountSlug = (string) ($account['slug'] ?? '');
                        try {
                            $result['sync'][] = $this->mediamarkt->syncAccount($accountSlug, $syncOptions);
                        } catch (MediaMarktRateLimitException $exception) {
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

            foreach ($result['sync'] as $syncResult) {
                if (!is_array($syncResult)) {
                    continue;
                }

                if (!empty($syncResult['rate_limited'])) {
                    $result['rate_limited'] = true;
                }

                $summary = is_array($syncResult['local_update_summary'] ?? null)
                    ? $syncResult['local_update_summary']
                    : array();
                foreach (array('inserted', 'updated', 'unchanged') as $status) {
                    $result['local_update_summary'][$status] += (int) ($summary[$status] ?? 0);
                }

                $updates = is_array($syncResult['local_updates'] ?? null)
                    ? $syncResult['local_updates']
                    : array();
                foreach ($updates as $update) {
                    if (is_array($update)) {
                        $result['local_updates'][] = $update;
                    }
                }
            }

            $result['finished_at'] = date(DATE_ATOM);
            $result['duration_ms'] = (int) round((microtime(true) - $startedAt) * 1000);

            if ($this->wantsJson()) {
                $this->jsonResponse($result);
                return;
            }

            $this->setFlash('success', 'Maintenance MediaMarkt zakonczone. Sync kont: ' . count($result['sync']) . '.');
        } catch (Throwable $exception) {
            if ($this->wantsJson()) {
                $this->jsonResponse(array(
                    'ok' => false,
                    'run_id' => $runId,
                    'error' => $exception->getMessage(),
                    'finished_at' => date(DATE_ATOM),
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ), 500);
                return;
            }
            $this->setFlash('error', $exception->getMessage());
        } finally {
            $database->releaseAdvisoryLock(self::MAINTENANCE_LOCK_NAME);
        }

        $this->redirect('./index.php?controller=mediamarkt&action=index');
    }

    public function clearqueue(): void
    {
        $this->requireModuleWrite('mediamarkt');

        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=mediamarkt&action=index');
        }

        try {
            $mode = trim((string) $this->input('mode', 'statuses'));
            if ($mode === 'all') {
                $result = $this->mediamarkt->clearWholeQueue();
                $this->setFlash('success', 'Usunieto cala kolejke MediaMarkt: ' . (int) ($result['removed'] ?? 0) . ' wpisow.');
            } else {
                $result = $this->mediamarkt->clearQueueStatuses(true);
                $this->setFlash('success', 'Wyczyszczono statusy zakonczonych pozycji kolejki MediaMarkt: ' . (int) ($result['removed'] ?? 0) . ' wpisow.');
            }
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=mediamarkt&action=index');
    }

    public function offer(): void
    {
        $this->requireModule('mediamarkt');

        $id = (int) $this->input('id', 0);
        $offer = $this->mediamarkt->offerDetails($id);
        if (!$offer) {
            $this->setFlash('error', 'Nie znaleziono oferty MediaMarkt.');
            $this->redirect('./index.php?controller=mediamarkt&action=index');
        }

        $decoded = array();
        if (!empty($offer['offer_json'])) {
            $decoded = json_decode((string) $offer['offer_json'], true);
            if (!is_array($decoded)) {
                $decoded = array();
            }
        }

        $this->render('mediamarkt/offer', array(
            'pageTitle' => 'Oferta MediaMarkt',
            'contentTitle' => (string) ($offer['product_title'] ?? ('Oferta #' . $offer['offer_id'])),
            'pageDescription' => 'Podglad zapisanej oferty z MediaMarkt Marketplace.',
            'breadcrumbCurrent' => 'Oferta MediaMarkt',
            'offer' => $offer,
            'offerData' => $decoded,
            'offerJsonPretty' => json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ));
    }

    public function categories(): void
    {
        $this->requireModule('mediamarkt');

        $search = trim((string) $this->input('search', ''));
        $force = ((string) $this->input('force', '0')) === '1';
        $accountId = (int) $this->input('account_id', 0);

        try {
            $this->jsonResponse(array('items' => $this->mediamarkt->searchCategories($search, $force, $accountId > 0 ? $accountId : null)));
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
            'controller' => 'mediamarkt',
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

    private function processMediaMarktQueue(array $options): array
    {
        $database = $this->db();
        if (!$database->acquireAdvisoryLock(self::QUEUE_LOCK_NAME)) {
            return array(
                'skipped' => true,
                'reason' => 'Inny worker kolejki MediaMarkt jest teraz uruchomiony.',
                'counts' => $this->mediamarkt->queueCounts(),
            );
        }

        try {
            return $this->mediamarkt->processQueue($options);
        } finally {
            $database->releaseAdvisoryLock(self::QUEUE_LOCK_NAME);
        }
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
