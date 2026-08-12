<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Models\EmpikStorageRepository;
use RuntimeException;

class EmpikService
{
    private const CACHE_CLEANUP_THROTTLE_SECONDS = 600;
    private const RATE_LIMIT_MAX_ATTEMPTS = 8;
    private const RATE_LIMIT_BASE_DELAY_SECONDS = 30;
    private const RATE_LIMIT_MAX_DELAY_SECONDS = 900;

    /** @var array */
    private $config;

    /** @var EmpikStorageRepository */
    private $storage;

    /** @var float */
    private static $lastRequestAtMicros = 0.0;

    public function __construct()
    {
        $app = Config::get('app');
        $this->config = isset($app['empik']) && is_array($app['empik']) ? $app['empik'] : array();
        $this->storage = new EmpikStorageRepository(Database::instance());
        $this->storage->ensureSchema();
        $this->maybeCleanupExpiredCache();
    }

    public function listAccounts(): array
    {
        return $this->storage->allAccounts();
    }

    public function accountsDueForSync(): array
    {
        return $this->storage->accountsDueForSync();
    }

    public function saveAccount(array $input, ?int $accountId = null): array
    {
        $existing = $accountId !== null ? $this->storage->findAccountById($accountId) : null;
        if ($accountId !== null && !$existing) {
            throw new RuntimeException('Nie znaleziono konta Empik do edycji.');
        }

        $name = trim((string) ($input['name'] ?? ''));
        $apiUrl = rtrim(trim((string) ($input['api_url'] ?? '')), '/');
        $apiKey = trim((string) ($input['api_key'] ?? ''));
        $shopIdRaw = trim((string) ($input['shop_id'] ?? ''));
        $locale = trim((string) ($input['locale'] ?? $this->defaultLocale()));
        $isActive = !empty($input['is_active']) ? 1 : 0;

        if ($existing) {
            $name = $name !== '' ? $name : (string) ($existing['name'] ?? '');
            $apiUrl = $apiUrl !== '' ? $apiUrl : rtrim((string) ($existing['api_url'] ?? ''), '/');
            $apiKey = $apiKey !== '' ? $apiKey : (string) ($existing['api_key'] ?? '');
            $locale = $locale !== '' ? $locale : (string) ($existing['locale'] ?? $this->defaultLocale());
        }

        if ($name === '' || $apiUrl === '' || $apiKey === '') {
            throw new RuntimeException('Uzupelnij nazwe konta, adres API i API key Empik.');
        }

        if (filter_var($apiUrl, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Adres API Empik musi byc poprawnym URL.');
        }

        $payload = array(
            'name' => $name,
            'slug' => $this->uniqueSlug($name, $accountId),
            'api_url' => $apiUrl,
            'api_key' => $apiKey,
            'shop_id' => ($shopIdRaw !== '' && ctype_digit($shopIdRaw)) ? (int) $shopIdRaw : null,
            'locale' => $locale !== '' ? $locale : $this->defaultLocale(),
            'is_active' => $isActive,
        );

        $id = $this->storage->saveAccount($payload, $accountId);
        return (array) $this->storage->findAccountById($id);
    }

    public function offersPage(array $filters, int $page, int $perPage, string $sortBy = 'synced', string $sortDir = 'desc'): array
    {
        return $this->storage->listOffers($filters, $page, $perPage, $sortBy, $sortDir);
    }

    public function countOffers(array $filters): int
    {
        return $this->storage->countOffers($filters);
    }

    public function offerDetails(int $id)
    {
        return $this->storage->findOfferRowById($id);
    }

    public function queueCounts(): array
    {
        return $this->storage->queueCounts();
    }

    public function enqueueOfferChanges(array $filters, string $operation, array $payload = array(), array $selectedOfferIds = array()): array
    {
        $operation = $this->normalizeQueueOperation($operation);
        if ($operation === '') {
            throw new RuntimeException('Wybierz poprawna operacje dla Empika.');
        }

        if ($selectedOfferIds !== array()) {
            $targets = $this->storage->listOffersByIds($selectedOfferIds);
        } else {
            $selectionLimit = isset($payload['selection_limit']) ? max(1, min(5000, (int) $payload['selection_limit'])) : 1000;
            $targets = $this->storage->offerTargetsForFilters($filters, $selectionLimit);
        }

        if ($targets === array()) {
            throw new RuntimeException('Brak ofert Empik do przetworzenia dla wybranego zakresu.');
        }

        if ($operation === 'clear_queue') {
            $removed = $this->storage->clearQueueForOffers(array_map(static function (array $row): int {
                return (int) ($row['id'] ?? 0);
            }, $targets));

            return array(
                'operation' => $operation,
                'removed' => $removed,
                'offers' => count($targets),
            );
        }

        if ($operation === 'remove_from_system') {
            $removed = $this->storage->removeOffersByIds(array_map(static function (array $row): int {
                return (int) ($row['id'] ?? 0);
            }, $targets));

            return array(
                'operation' => $operation,
                'removed' => $removed,
                'offers' => count($targets),
            );
        }

        $normalizedPayload = $this->normalizeQueuePayload($operation, $payload);
        $queued = $this->storage->enqueueOfferChanges($targets, $operation, $normalizedPayload);

        return array(
            'operation' => $operation,
            'queued' => $queued,
            'offers' => count($targets),
        );
    }

    public function processQueue(array $options = array()): array
    {
        // Recover anything a previous, abruptly-terminated run left stuck in "processing"
        // before we look at what's pending - see reclaimStaleProcessing() for why this is
        // needed at all.
        $this->storage->reclaimStaleProcessing();

        $limit = max(1, min(300, (int) ($options['limit'] ?? 20)));
        $accountSelector = trim((string) ($options['account'] ?? ''));
        $accountId = null;

        if ($accountSelector !== '') {
            $account = $this->resolveAccount($accountSelector);
            if ($account) {
                $accountId = (int) $account['id'];
            }
        }

        // Bounded so a batch of slow imports (each submit + status poll can take several
        // seconds) can't run past the next cron tick or the host's execution-time limit;
        // whatever doesn't fit stays "pending"/"retry" and is picked up next run.
        $maxRuntime = max(5, min(280, (int) ($options['max_runtime'] ?? 15)));
        $startTime = time();

        $rows = $this->storage->fetchQueueBatch($limit, $accountId);
        $summary = array(
            'processed' => 0,
            'done' => 0,
            'submitted' => 0,
            'waiting' => 0,
            'error' => 0,
            'retry' => 0,
            'log' => array(),
            'counts' => array(),
        );

        foreach ($rows as $row) {
            if ((time() - $startTime) >= $maxRuntime) {
                break;
            }

            $queueId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($queueId <= 0) {
                continue;
            }

            $summary['processed']++;
            $this->storage->markQueueProcessing($queueId);
            $logEntry = $this->queueLogEntry($row);

            try {
                $offer = $this->storage->findOfferByRowId((int) ($row['offer_row_id'] ?? 0));
                if (!$offer) {
                    throw new RuntimeException('Nie znaleziono oferty Empik do przetworzenia.');
                }
                $logEntry = $this->queueLogEntry($row, $offer);

                $remoteImportId = trim((string) ($row['remote_import_id'] ?? ''));
                $remoteImportType = trim((string) ($row['remote_import_type'] ?? ''));

                // A previous tick already submitted this update. Check that exact import
                // instead of sending a duplicate price/stock change on every retry.
                if ($remoteImportId !== '' && $remoteImportType !== '') {
                    $importState = $this->inspectImport($offer, $remoteImportType, $remoteImportId);
                    $logEntry['import_id'] = $remoteImportId;
                    $logEntry['import_type'] = $remoteImportType;
                    $logEntry['remote_status'] = $importState['status'];

                    if (!$importState['complete']) {
                        $this->storage->markQueueWaiting($queueId, $remoteImportId, $remoteImportType);
                        $summary['waiting']++;
                        $logEntry['status'] = 'waiting';
                        $logEntry['message'] = 'Empik nadal przetwarza import; status zostanie sprawdzony ponownie.';
                        $summary['log'][] = $logEntry;
                        continue;
                    }

                    $this->storage->markQueueDone($queueId, $remoteImportId, $remoteImportType);
                    $summary['done']++;
                    $logEntry['status'] = 'updated';
                    $logEntry['message'] = 'Empik potwierdzil aktualizacje oferty.';
                    $summary['log'][] = $logEntry;
                    continue;
                }

                $result = $this->executeQueueOperation($offer, (string) ($row['operation'] ?? ''), is_array($row['payload'] ?? null) ? $row['payload'] : array());
                $remoteImportId = trim((string) ($result['import_id'] ?? ''));
                $remoteImportType = trim((string) ($result['import_type'] ?? ''));
                if ($remoteImportId === '' || $remoteImportType === '') {
                    throw new RuntimeException('Empik nie zwrocil danych potrzebnych do sprawdzenia importu.');
                }

                $this->storage->markQueueWaiting(
                    $queueId,
                    $remoteImportId,
                    $remoteImportType,
                    10,
                    array(
                        'previous_value' => $logEntry['previous_value'],
                        'requested_value' => $logEntry['requested_value'],
                        'submitted_at' => date(DATE_ATOM),
                    )
                );
                $summary['submitted']++;
                $logEntry['status'] = 'submitted';
                $logEntry['import_id'] = $remoteImportId;
                $logEntry['import_type'] = $remoteImportType;
                $logEntry['message'] = 'Aktualizacja zostala przyjeta przez Empik; potwierdzenie nastapi w kolejnym przebiegu.';
                $summary['log'][] = $logEntry;
            } catch (EmpikRateLimitException $exception) {
                $attempts = isset($row['attempts']) ? (int) $row['attempts'] : 0;
                if ($attempts < self::RATE_LIMIT_MAX_ATTEMPTS) {
                    $this->storage->markQueueRetry(
                        $queueId,
                        $exception->getMessage(),
                        $this->rateLimitRetryDelay($attempts, $exception->getRetryAfterSeconds())
                    );
                    $summary['retry']++;
                    $logEntry['status'] = 'retry';
                } else {
                    $this->storage->markQueueError($queueId, $exception->getMessage());
                    $summary['error']++;
                    $logEntry['status'] = 'error';
                }
                $logEntry['message'] = $exception->getMessage();
                $summary['log'][] = $logEntry;
            } catch (RuntimeException $exception) {
                $attempts = isset($row['attempts']) ? (int) $row['attempts'] : 0;
                if ($attempts < 1) {
                    $this->storage->markQueueRetry($queueId, $exception->getMessage(), 90);
                    $summary['retry']++;
                    $logEntry['status'] = 'retry';
                } else {
                    $this->storage->markQueueError($queueId, $exception->getMessage());
                    $summary['error']++;
                    $logEntry['status'] = 'error';
                }
                $logEntry['message'] = $exception->getMessage();
                $summary['log'][] = $logEntry;
            }
        }

        $summary['counts'] = $this->storage->queueCounts();
        $summary['recent_log'] = $this->recentQueueLog(25);
        return $summary;
    }

    public function clearWholeQueue(): array
    {
        return $this->storage->clearWholeQueue();
    }

    public function cancelAutomatedWarehouseUpdates(): int
    {
        return $this->storage->cancelUnresolvedWarehouseUpdates();
    }

    public function clearQueueStatuses(bool $keepPending = true): array
    {
        return $this->storage->clearQueueStatuses($keepPending);
    }

    public function automationLinks(string $baseUrl): array
    {
        $base = rtrim($baseUrl, '?&');
        $links = array(
            'queue_worker' => $base . '?controller=empik&action=processqueue&format=json&limit=50',
            'sync_worker' => $base . '?controller=empik&action=maintenance&format=json&sync=1',
            'maintenance' => $base . '?controller=empik&action=maintenance&format=json&sync=1',
            'accounts' => array(),
        );

        foreach ($this->listAccounts() as $account) {
            $accountSlug = rawurlencode((string) ($account['slug'] ?? ''));
            $links['accounts'][] = array(
                'id' => (int) ($account['id'] ?? 0),
                'name' => (string) ($account['name'] ?? ''),
                'slug' => (string) ($account['slug'] ?? ''),
                'is_active' => (int) ($account['is_active'] ?? 0) === 1,
                'sync' => $base . '?controller=empik&action=sync&format=json&account=' . $accountSlug,
                'queue_only' => $base . '?controller=empik&action=processqueue&format=json&account=' . $accountSlug . '&limit=50',
                'maintenance' => $base . '?controller=empik&action=maintenance&format=json&account=' . $accountSlug . '&sync=1',
            );
        }

        return $links;
    }

    public function enqueueWarehouseUpdates(string $accountSelector, array $operations, int $limit = 500): array
    {
        $allowed = array('set_price_from_product', 'set_stock_from_product');
        $operations = array_values(array_unique(array_filter(array_map(static function ($operation): string {
            return trim((string) $operation);
        }, $operations))));

        $operations = array_values(array_filter($operations, static function (string $operation) use ($allowed): bool {
            return in_array($operation, $allowed, true);
        }));

        if ($operations === array()) {
            throw new RuntimeException('Wybierz poprawna operacje aktualizacji Empik z magazynu.');
        }

        $filters = array(
            'active' => '1',
            'linked' => '1',
        );

        $accountSelector = trim($accountSelector);
        if ($accountSelector !== '') {
            $account = $this->resolveAccount($accountSelector);
            if (!$account) {
                throw new RuntimeException('Nie znaleziono konta Empik do aktualizacji z magazynu.');
            }
            $filters['account_id'] = (string) ((int) $account['id']);
        }

        $perOperationLimit = max(1, min(5000, $limit));
        $queued = 0;
        $offersConsidered = 0;
        $log = array();

        // Each operation gets its own rotating target list via offersDueForOperation()
        // instead of reusing one shared "top N offers by id" batch for all operations: with
        // a 10-minute cron and a large active+linked catalog, always grabbing the same
        // highest-id rows meant most of the catalog never had its price/stock refreshed, and
        // offers already sitting in the queue for that operation would get queued again on
        // every tick. offersDueForOperation() excludes unresolved entries and advances a
        // persisted cursor, so repeated runs sweep through the whole eligible set. The
        // backlog cap below prevents enqueue_limit=500 / queue_limit=50 from growing the
        // pending queue by 450 rows on every cron tick.
        foreach ($operations as $operation) {
            $queueAccountId = isset($filters['account_id']) ? (int) $filters['account_id'] : null;
            $unresolved = $this->storage->unresolvedQueueCount($operation, $queueAccountId);
            $availableSlots = max(0, $perOperationLimit - $unresolved);
            if ($availableSlots <= 0) {
                $log[] = array(
                    'operation' => $operation,
                    'status' => 'backlog_full',
                    'offers' => 0,
                    'queued' => 0,
                    'backlog' => $unresolved,
                    'message' => 'Kolejka ma juz limit oczekujacych wpisow; nowe oferty wejda po jej oproznieniu.',
                );
                continue;
            }

            try {
                // offersDueForOperation() runs under a DB-side statement timeout - if it
                // still trips (e.g. an unexpectedly expensive "linked" warehouse match on
                // this install), skip this operation for this tick rather than let it take
                // the whole maintenance run down; nothing was queued, so it's picked back up
                // automatically on the next cron tick.
                $targets = $this->storage->offersDueForOperation($filters, $operation, $availableSlots);
            } catch (\Throwable $exception) {
                $log[] = array(
                    'operation' => $operation,
                    'status' => 'error',
                    'offers' => 0,
                    'queued' => 0,
                    'message' => $exception->getMessage(),
                );
                continue;
            }

            if ($targets === array()) {
                $log[] = array(
                    'operation' => $operation,
                    'status' => 'nothing_to_enqueue',
                    'offers' => 0,
                    'queued' => 0,
                    'message' => 'Brak nowych kwalifikujacych sie ofert dla tej operacji.',
                );
                continue;
            }

            $offersConsidered += count($targets);
            $operationQueued = $this->storage->enqueueOfferChanges($targets, $operation, array());
            $queued += $operationQueued;
            $log[] = array(
                'operation' => $operation,
                'status' => 'queued',
                'offers' => count($targets),
                'queued' => $operationQueued,
                'backlog_before' => $unresolved,
                'message' => 'Oferty dodane do kolejki aktualizacji Empik.',
            );
        }

        return array(
            'operations' => $operations,
            'offers' => $offersConsidered,
            'queued' => $queued,
            'log' => $log,
            'diagnostics' => $this->storage->warehouseUpdateDiagnostics(),
            'counts' => $this->storage->queueCounts(),
        );
    }

    public function syncAccount(string $accountSelector = '', array $options = array()): array
    {
        $account = $this->resolveAccount($accountSelector);
        if (!$account) {
            throw new RuntimeException('Brak aktywnego konta Empik do synchronizacji.');
        }

        $accountId = (int) $account['id'];
        $limit = 100;
        $synced = 0;
        $total = null;
        $localUpdates = array();
        $localUpdateSummary = array(
            'inserted' => 0,
            'updated' => 0,
            'unchanged' => 0,
        );
        $rateLimit = null;

        // Mirakl's synchronous offer listing (used here) has no "updated since" filter -
        // only the asynchronous export endpoint (OF52) supports differential mode - so a
        // full catalog re-read is the only option through this API. Because this runs from
        // a cron every ~10 minutes, we resume from the offset the previous call left off at
        // (persisted on the account) instead of restarting from 0 every time; otherwise a
        // catalog too large to page through inside one time-boxed call would never reach its
        // later pages.
        $offset = max(0, (int) ($account['sync_offset'] ?? 0));
        $maxRuntime = max(5, min(240, (int) ($options['max_runtime'] ?? 12)));
        $startTime = time();
        $finishedCycle = false;

        try {
            while (true) {
                if ((time() - $startTime) >= $maxRuntime) {
                    break;
                }

                $payload = $this->requestApi($account, 'GET', '/api/offers', array(
                    'max' => $limit,
                    'offset' => $offset,
                    'locale' => (string) ($account['locale'] ?? $this->defaultLocale()),
                ));

                $offers = isset($payload['offers']) && is_array($payload['offers']) ? $payload['offers'] : array();
                $total = isset($payload['total_count']) ? (int) $payload['total_count'] : null;

                $rows = array();
                foreach ($offers as $offer) {
                    if (!is_array($offer)) {
                        continue;
                    }
                    $rows[] = $this->normalizeOfferRow($account, $offer);
                }

                if ($rows !== array()) {
                    $existingRows = $this->storage->offersForSyncComparison(
                        $accountId,
                        array_column($rows, 'offer_id')
                    );
                    $pageUpdates = $this->buildLocalUpdateLog($rows, $existingRows, $account);
                    $this->storage->upsertOffers($rows);
                    $synced += count($rows);

                    foreach ($pageUpdates as $pageUpdate) {
                        $status = (string) ($pageUpdate['local_status'] ?? 'unchanged');
                        if (isset($localUpdateSummary[$status])) {
                            $localUpdateSummary[$status]++;
                        }
                        $localUpdates[] = $pageUpdate;
                    }
                }

                $offset += count($offers);
                $this->storage->updateAccountSyncOffset($accountId, $offset);

                if ($offers === array() || ($total === null ? count($offers) < $limit : $offset >= $total)) {
                    $finishedCycle = true;
                    break;
                }
            }

            if ($finishedCycle) {
                $this->storage->updateAccountSyncOffset($accountId, 0);
            }

            $this->storage->markAccountSyncSuccess($accountId);
        } catch (EmpikRateLimitException $exception) {
            // Pages committed before the limit was reached remain valid local updates. Do
            // not discard their report and do not retry aggressively from this web request;
            // requestApi() stores a shared cooldown respected by subsequent workers.
            $this->storage->markAccountSyncError($accountId, $exception->getMessage());
            $retryAfter = max(1, (int) ($exception->getRetryAfterSeconds() ?? 60));
            $rateLimit = array(
                'message' => $exception->getMessage(),
                'retry_after_seconds' => $retryAfter,
                'retry_at' => date(DATE_ATOM, time() + $retryAfter),
            );
        } catch (RuntimeException $exception) {
            $this->storage->markAccountSyncError($accountId, $exception->getMessage());
            throw $exception;
        }

        $result = array(
            'account' => array(
                'id' => $accountId,
                'name' => (string) $account['name'],
                'slug' => (string) $account['slug'],
            ),
            'status' => $rateLimit !== null
                ? ($synced > 0 ? 'partially_synced_rate_limited' : 'rate_limited')
                : ($finishedCycle ? 'completed' : 'partially_synced'),
            'synced_offers' => $synced,
            'total_count' => $total !== null ? $total : $synced,
            'finished_cycle' => $finishedCycle,
            'resume_offset' => $finishedCycle ? 0 : $offset,
            'local_update_summary' => $localUpdateSummary,
            'local_updates' => $localUpdates,
        );

        if ($rateLimit !== null) {
            $result['rate_limited'] = true;
            $result['rate_limit'] = $rateLimit;
        }

        return $result;
    }

    public function searchCategories(string $search, bool $forceRefresh = false, ?int $accountId = null): array
    {
        $search = trim($search);
        if ($search === '') {
            return array();
        }

        $account = $this->resolveAccount($accountId !== null ? (string) $accountId : '');
        if (!$account) {
            throw new RuntimeException('Najpierw skonfiguruj aktywne konto Empik, zeby pobierac kategorie.');
        }

        $cacheKey = 'empik_hierarchy_search_v1_' . (int) $account['id'] . '_' . md5(mb_strtolower($search, 'UTF-8'));
        if (!$forceRefresh) {
            $cached = $this->storage->getCache($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $tree = $this->loadHierarchyTree($account, $forceRefresh);
        $items = array();
        $needles = $this->searchTokens($search);

        foreach ($tree as $item) {
            $haystacks = array(
                mb_strtolower((string) ($item['name'] ?? ''), 'UTF-8'),
                mb_strtolower((string) ($item['path'] ?? ''), 'UTF-8'),
                mb_strtolower((string) ($item['id'] ?? ''), 'UTF-8'),
            );

            $matches = true;
            foreach ($needles as $needle) {
                $found = false;
                foreach ($haystacks as $haystack) {
                    if ($haystack !== '' && mb_strpos($haystack, $needle, 0, 'UTF-8') !== false) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                $items[] = $item;
            }
        }

        usort($items, static function (array $a, array $b): int {
            $leafDiff = ((int) !empty($b['leaf'])) - ((int) !empty($a['leaf']));
            if ($leafDiff !== 0) {
                return $leafDiff;
            }

            return strcmp((string) ($a['path'] ?? ''), (string) ($b['path'] ?? ''));
        });

        $items = array_slice($items, 0, 120);
        $this->storage->putCache($cacheKey, $items, min(3600, $this->cacheTtl()));

        return $items;
    }

    public function categoryAttributes(string $hierarchyCode, bool $forceRefresh = false, ?int $accountId = null): array
    {
        $hierarchyCode = trim($hierarchyCode);
        if ($hierarchyCode === '') {
            return array();
        }

        $account = $this->resolveAccount($accountId !== null ? (string) $accountId : '');
        if (!$account) {
            throw new RuntimeException('Najpierw skonfiguruj aktywne konto Empik, zeby pobierac parametry.');
        }

        $cacheKey = 'empik_attributes_v5_' . (int) $account['id'] . '_' . md5($hierarchyCode);
        if (!$forceRefresh) {
            $cached = $this->storage->getCache($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $attributes = $this->loadCategoryAttributesPayload($account, $hierarchyCode, $forceRefresh);
        $result = array();

        foreach ($attributes as $attribute) {
            if (!is_array($attribute)) {
                continue;
            }

            $normalized = $this->normalizeAttributeDefinition($attribute);
            if ($normalized !== null) {
                $result[] = $normalized;
            }
        }

        usort($result, static function (array $a, array $b): int {
            $requiredDiff = ((int) !empty($b['required'])) - ((int) !empty($a['required']));
            if ($requiredDiff !== 0) {
                return $requiredDiff;
            }

            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        $result = $this->deduplicateAttributeDefinitions($result);

        $this->storage->putCache($cacheKey, $result, min(3600, $this->cacheTtl()));
        return $result;
    }

    public function searchAttributeOptions(string $hierarchyCode, string $attributeId, string $query = '', int $limit = 40, ?int $accountId = null): array
    {
        $hierarchyCode = trim($hierarchyCode);
        $attributeId = trim($attributeId);
        $query = trim($query);

        if ($hierarchyCode === '' || $attributeId === '') {
            return array();
        }

        $account = $this->resolveAccount($accountId !== null ? (string) $accountId : '');
        if (!$account) {
            throw new RuntimeException('Najpierw skonfiguruj aktywne konto Empik, zeby pobierac parametry.');
        }

        $attributes = $this->loadCategoryAttributesPayload($account, $hierarchyCode, false);
        $matchedAttribute = null;

        foreach ($attributes as $attribute) {
            if (!is_array($attribute)) {
                continue;
            }

            $currentId = trim((string) ($attribute['code'] ?? $attribute['attribute_code'] ?? $attribute['id'] ?? ''));
            if ($currentId !== '' && $currentId === $attributeId) {
                $matchedAttribute = $attribute;
                break;
            }
        }

        if (!is_array($matchedAttribute)) {
            return array();
        }

        $matchedId = trim((string) ($matchedAttribute['code'] ?? $matchedAttribute['attribute_code'] ?? $matchedAttribute['id'] ?? ''));
        $matchedName = trim((string) ($matchedAttribute['label'] ?? $matchedAttribute['name'] ?? $matchedAttribute['code'] ?? $matchedId));
        if ($this->isFreeTextIdentityAttribute($matchedId, $matchedName)) {
            return array();
        }

        $dictionaryType = $this->normalizeAttributeDictionaryType((string) ($matchedAttribute['type'] ?? $matchedAttribute['value_type'] ?? $matchedAttribute['input_type'] ?? 'text'));
        $dictionary = $this->normalizeAttributeDictionary($matchedAttribute, $account, $dictionaryType);
        if ($dictionary === array()) {
            return array();
        }

        if ($query === '') {
            return array_slice(array_values($dictionary), 0, max(1, $limit));
        }

        $needle = function_exists('mb_strtolower') ? mb_strtolower($query, 'UTF-8') : strtolower($query);
        $ranked = array();
        foreach (array_values($dictionary) as $index => $option) {
            $id = isset($option['id']) ? (string) $option['id'] : '';
            $label = isset($option['value']) ? (string) $option['value'] : $id;
            $labelLower = function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : strtolower($label);
            $idLower = function_exists('mb_strtolower') ? mb_strtolower($id, 'UTF-8') : strtolower($id);

            $rank = null;
            if ($labelLower === $needle || $idLower === $needle) {
                $rank = 0;
            } elseif (strpos($labelLower, $needle) === 0 || strpos($idLower, $needle) === 0) {
                $rank = 1;
            } elseif (strpos($labelLower . ' ' . $idLower, $needle) !== false) {
                $rank = 2;
            }

            if ($rank === null) {
                continue;
            }

            $ranked[] = array('rank' => $rank, 'index' => $index, 'option' => $option);
        }

        usort($ranked, static function (array $a, array $b): int {
            return $a['rank'] <=> $b['rank'] ?: $a['index'] <=> $b['index'];
        });

        // No cap here: with results ranked by relevance the exact/prefix matches
        // always lead, so truncating would risk hiding them behind unrelated
        // substring hits instead of just showing everything as-is.
        return array_map(static function (array $entry) {
            return $entry['option'];
        }, $ranked);
    }

    public function validateAttributeValues(array $definitions, array $input): array
    {
        if ($definitions === array()) {
            return array();
        }

        $result = array();

        foreach ($definitions as $definition) {
            if (!is_array($definition) || empty($definition['id'])) {
                continue;
            }

            $id = (string) $definition['id'];
            $type = strtolower((string) ($definition['type'] ?? 'text'));
            $multiple = !empty($definition['multiple']);
            $rawValue = array_key_exists($id, $input) ? $input[$id] : null;

            if ($multiple) {
                $values = is_array($rawValue) ? $rawValue : ($rawValue !== null && $rawValue !== '' ? array($rawValue) : array());
                $normalizedValues = array();

                foreach ($values as $value) {
                    $clean = $this->normalizeAttributeValue($type, $value);
                    if ($clean !== null && $clean !== '') {
                        $normalizedValues[] = $clean;
                    }
                }

                $normalizedValues = array_values(array_unique($normalizedValues, SORT_REGULAR));

                if ($normalizedValues !== array()) {
                    $result[$id] = $normalizedValues;
                }

                continue;
            }

            $clean = $this->normalizeAttributeValue($type, $rawValue);
            if ($clean !== null && $clean !== '') {
                $result[$id] = $clean;
            }
        }

        return $result;
    }

    private function loadHierarchyTree(array $account, bool $forceRefresh): array
    {
        $cacheKey = 'empik_hierarchy_tree_v1_' . (int) $account['id'];
        if (!$forceRefresh) {
            $cached = $this->storage->getCache($cacheKey);
            if (is_array($cached) && $cached !== array()) {
                return $cached;
            }
        }

        $payload = $this->requestApi($account, 'GET', '/api/hierarchies', array(
            'locale' => (string) ($account['locale'] ?? $this->defaultLocale()),
        ));

        $rows = isset($payload['hierarchies']) && is_array($payload['hierarchies']) ? $payload['hierarchies'] : array();
        if ($rows === array()) {
            throw new RuntimeException('Empik nie zwrocil drzewa kategorii.');
        }

        $map = array();
        $children = array();

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = trim((string) ($row['code'] ?? ''));
            if ($id === '') {
                continue;
            }

            $parentId = trim((string) ($row['parent_code'] ?? ''));
            $map[$id] = array(
                'id' => $id,
                'name' => $this->hierarchyLabel($row, (string) ($account['locale'] ?? $this->defaultLocale())),
                'parent_id' => $parentId !== '' ? $parentId : null,
                'level' => isset($row['level']) ? (int) $row['level'] : null,
            );

            if ($parentId !== '') {
                if (!isset($children[$parentId])) {
                    $children[$parentId] = array();
                }
                $children[$parentId][] = $id;
            }
        }

        $pathCache = array();
        $items = array();

        foreach ($map as $id => $item) {
            $id = (string) $id;
            $parentId = isset($item['parent_id']) && $item['parent_id'] !== null ? (string) $item['parent_id'] : null;

            $items[] = array(
                'id' => $id,
                'name' => $item['name'],
                'path' => $this->buildHierarchyPath($id, $map, $pathCache),
                'leaf' => empty($children[$id]),
                'parent_id' => $parentId,
                'level' => $item['level'],
            );
        }

        $this->storage->putCache($cacheKey, $items, $this->cacheTtl());
        return $items;
    }

    private function normalizeAttributeDefinition(array $attribute): ?array
    {
        $id = trim((string) ($attribute['code'] ?? $attribute['attribute_code'] ?? $attribute['id'] ?? ''));
        if ($id === '') {
            return null;
        }

        $name = trim((string) ($attribute['label'] ?? $attribute['name'] ?? $attribute['code'] ?? $id));
        $rawType = strtolower(trim((string) ($attribute['type'] ?? $attribute['value_type'] ?? $attribute['input_type'] ?? 'text')));
        $type = str_replace(array('-', ' '), '_', $rawType);
        $multiple = $this->truthyAttributeFlag($attribute['multivalued'] ?? null)
            || $this->truthyAttributeFlag($attribute['multi_valued'] ?? null)
            || $this->truthyAttributeFlag($attribute['multiple'] ?? null)
            || in_array($type, array('multidictionary', 'multi_dictionary', 'multi_select', 'multiple_select', 'list_multiple', 'multiple_list', 'multi_value_list', 'multiple_values_list'), true);
        $required = $this->truthyAttributeFlag($attribute['required'] ?? null)
            || $this->truthyAttributeFlag($attribute['mandatory'] ?? null)
            || in_array(strtolower(trim((string) ($attribute['requirement_level'] ?? $attribute['requirement'] ?? ''))), array('required', 'mandatory'), true);
        $dictionary = array();

        if (in_array($type, array('enum', 'enumeration', 'list', 'lov', 'select', 'choice'), true)) {
            $type = $multiple ? 'multidictionary' : 'dictionary';
        } elseif (in_array($type, array('multidictionary', 'multi_dictionary', 'multi_select', 'multiple_select', 'list_multiple', 'multiple_list', 'multi_value_list', 'multiple_values_list'), true)) {
            $type = 'multidictionary';
            $multiple = true;
        } elseif (in_array($type, array('numeric', 'decimal', 'double'), true)) {
            $type = 'number';
        } elseif (in_array($type, array('int', 'long'), true)) {
            $type = 'integer';
        } elseif (in_array($type, array('bool', 'boolean'), true)) {
            $type = 'dictionary';
            $dictionary = array(
                array('id' => 'true', 'value' => 'Tak'),
                array('id' => 'false', 'value' => 'Nie'),
            );
        } elseif (in_array($type, array('textarea', 'text_area', 'long_text', 'description', 'html'), true)) {
            $type = 'textarea';
        } elseif (in_array($type, array('string', 'char', 'varchar'), true)) {
            $type = 'text';
        } elseif (!in_array($type, array('dictionary', 'multidictionary', 'integer', 'number', 'textarea', 'text'), true)) {
            $type = $multiple ? 'textarea' : 'text';
        }

        if ($this->isFreeTextIdentityAttribute($id, $name)) {
            $type = 'text';
            $multiple = false;
            $dictionary = array();
            $hasLookup = false;
        } else {
            $hasLookup = $dictionary === array() && $this->attributeSupportsLookup($attribute, $type);
        }

        return array(
            'id' => $id,
            'name' => $name !== '' ? $name : $id,
            'type' => $type,
            'required' => $required,
            'multiple' => $multiple,
            'dictionary' => $dictionary,
            'option_lookup' => $hasLookup,
            'raw_type' => (string) ($attribute['type'] ?? ''),
        );
    }

    private function attributeSupportsLookup(array $attribute, string $type): bool
    {
        if (in_array($type, array('dictionary', 'multidictionary', 'enum', 'enumeration', 'list', 'lov', 'select', 'choice'), true)) {
            return true;
        }

        foreach (array('values', 'allowed_values', 'accepted_values', 'options', 'available_values', 'possible_values') as $key) {
            if (isset($attribute[$key]) && is_array($attribute[$key]) && $attribute[$key] !== array()) {
                return true;
            }
        }

        $codes = $this->resolveValueListCodes($attribute);
        if ($codes !== array()) {
            return true;
        }

        return false;
    }

    private function loadCategoryAttributesPayload(array $account, string $hierarchyCode, bool $forceRefresh): array
    {
        $cacheKey = 'empik_attributes_payload_v1_' . (int) $account['id'] . '_' . md5($hierarchyCode);
        if (!$forceRefresh) {
            $cached = $this->storage->getCache($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $payload = $this->requestApi($account, 'GET', '/api/products/attributes', array(
            'hierarchy' => $hierarchyCode,
            'max_level' => 0,
            'all_operator_attributes' => 'true',
            'shop_id' => isset($account['shop_id']) && $account['shop_id'] !== null ? (int) $account['shop_id'] : null,
            'locale' => (string) ($account['locale'] ?? $this->defaultLocale()),
        ));

        $attributes = isset($payload['attributes']) && is_array($payload['attributes']) ? $payload['attributes'] : array();
        $this->storage->putCache($cacheKey, $attributes, min(3600, $this->cacheTtl()));

        return $attributes;
    }

    private function normalizeAttributeDictionary(array $attribute, array $account, string $type): array
    {
        $dictionary = array();
        $sources = array();

        foreach (array('values', 'allowed_values', 'accepted_values', 'options', 'available_values', 'possible_values') as $key) {
            if (isset($attribute[$key]) && is_array($attribute[$key])) {
                $sources = $attribute[$key];
                break;
            }
        }

        foreach ($sources as $option) {
            if (is_array($option)) {
                $id = trim((string) ($option['code'] ?? $option['id'] ?? $option['value'] ?? ''));
                $label = trim((string) ($option['label'] ?? $option['name'] ?? $option['value'] ?? $id));
            } else {
                $id = trim((string) $option);
                $label = $id;
            }

            if ($id !== '') {
                $dictionary[] = array('id' => $id, 'value' => $label !== '' ? $label : $id);
            }
        }

        if ($dictionary !== array()) {
            return $dictionary;
        }

        $valueListCodes = $this->resolveValueListCodes($attribute);
        foreach ($valueListCodes as $valueListCode) {
            $dictionary = $this->fetchValueListOptions($valueListCode, $account);
            if ($dictionary !== array()) {
                return $dictionary;
            }
        }

        if (!in_array($type, array('list', 'dictionary', 'multidictionary', 'boolean', 'enum', 'enumeration', 'lov', 'select', 'choice'), true)) {
            return array();
        }

        foreach ($this->fallbackValueListCodes($attribute) as $fallbackCode) {
            $dictionary = $this->fetchValueListOptions($fallbackCode, $account, true);
            if ($dictionary !== array()) {
                return $dictionary;
            }
        }

        return array();
    }

    private function resolveValueListCodes(array $attribute): array
    {
        $codes = array();
        $this->collectValueListCodes($attribute, $codes);

        return array_values(array_unique(array_filter($codes, static function ($value): bool {
            return is_string($value) && trim($value) !== '';
        })));
    }

    private function collectValueListCodes(array $attribute, array &$codes, int $depth = 0): void
    {
        if ($depth > 6) {
            return;
        }

        foreach ($attribute as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (is_array($value)) {
                if (preg_match('/value.?list|values.?list/', $normalizedKey)) {
                    $directCode = trim((string) ($value['code'] ?? $value['id'] ?? $value['value'] ?? ''));
                    if ($directCode !== '') {
                        $codes[] = $directCode;
                    }
                }

                $this->collectValueListCodes($value, $codes, $depth + 1);
                continue;
            }

            if (!is_scalar($value)) {
                continue;
            }

            if (preg_match('/value.?list|values.?list/', $normalizedKey)) {
                $code = trim((string) $value);
                if ($code !== '') {
                    $codes[] = $code;
                }
            }
        }
    }

    private function fallbackValueListCodes(array $attribute): array
    {
        $candidates = array();

        foreach (array('code', 'attribute_code', 'id') as $key) {
            if (!isset($attribute[$key]) || !is_scalar($attribute[$key])) {
                continue;
            }

            $value = trim((string) $attribute[$key]);
            if ($value !== '') {
                $candidates[] = $value;
            }
        }

        return array_values(array_unique($candidates));
    }

    private function normalizeAttributeDictionaryType(string $type): string
    {
        $type = strtolower(trim($type));
        $type = str_replace(array('-', ' '), '_', $type);

        if (in_array($type, array('multidictionary', 'multi_dictionary', 'multi_select', 'multiple_select', 'list_multiple', 'multiple_list', 'multi_value_list', 'multiple_values_list'), true)) {
            return 'multidictionary';
        }

        if (in_array($type, array('enum', 'enumeration', 'list', 'lov', 'select', 'choice'), true)) {
            return 'dictionary';
        }

        if (in_array($type, array('bool', 'boolean'), true)) {
            return 'boolean';
        }

        return $type;
    }

    private function deduplicateAttributeDefinitions(array $definitions): array
    {
        $result = array();
        $seen = array();

        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $key = $this->attributeDisplayNameKey((string) ($definition['name'] ?? ''));
            if ($key === '') {
                $key = $this->attributeDisplayNameKey((string) ($definition['id'] ?? ''));
            }

            if ($key !== '' && isset($seen[$key])) {
                continue;
            }

            if ($key !== '') {
                $seen[$key] = true;
            }

            $result[] = $definition;
        }

        return $result;
    }

    private function attributeDisplayNameKey(string $name): string
    {
        $name = preg_replace('/\s*\(\s*\d+\s*\)\s*$/', '', $name);
        return $this->normalizeAttributeTokenText((string) $name);
    }

    private function isFreeTextIdentityAttribute(string $id, string $name): bool
    {
        $normalized = $this->normalizeAttributeTokenText($id . ' ' . $name);

        foreach (array('ean', 'gtin', 'isbn', 'issn', 'upc', 'sku', 'mpn') as $token) {
            if (preg_match('/(^|[^a-z0-9])' . preg_quote($token, '/') . '([^a-z0-9]|$)/', $normalized)) {
                return true;
            }
        }

        foreach (array('kod producenta', 'numer katalogowy', 'kod kreskowy') as $phrase) {
            if (strpos($normalized, $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    private function normalizeAttributeTokenText(string $value): string
    {
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $map = array(
            'ą' => 'a',
            'ć' => 'c',
            'ę' => 'e',
            'ł' => 'l',
            'ń' => 'n',
            'ó' => 'o',
            'ś' => 's',
            'ź' => 'z',
            'ż' => 'z',
        );
        $value = strtr($value, $map);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);

        return trim((string) $value);
    }

    private function truthyAttributeFlag($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        if (!is_scalar($value)) {
            return false;
        }

        return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'y', 'tak'), true);
    }

    private function fetchValueListOptions(string $valueListCode, array $account, bool $suppressErrors = false): array
    {
        $cacheKey = 'empik_value_list_v1_' . (int) $account['id'] . '_' . md5($valueListCode);
        $cached = $this->storage->getCache($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $payload = $this->requestApi($account, 'GET', '/api/values_lists', array(
                'code' => $valueListCode,
                'shop_id' => isset($account['shop_id']) && $account['shop_id'] !== null ? (int) $account['shop_id'] : null,
                'locale' => (string) ($account['locale'] ?? $this->defaultLocale()),
            ));
        } catch (RuntimeException $exception) {
            if (!$suppressErrors) {
                throw $exception;
            }

            $this->storage->putCache($cacheKey, array(), min(1800, $this->cacheTtl()));
            return array();
        }

        $rows = isset($payload['values_lists']) && is_array($payload['values_lists']) ? $payload['values_lists'] : array();
        $result = array();

        foreach ($rows as $row) {
            if (!is_array($row) || trim((string) ($row['code'] ?? '')) !== $valueListCode) {
                continue;
            }

            $values = isset($row['values']) && is_array($row['values']) ? $row['values'] : array();
            foreach ($values as $value) {
                if (is_array($value)) {
                    $id = trim((string) ($value['code'] ?? $value['id'] ?? $value['value'] ?? ''));
                    $label = trim((string) ($value['label'] ?? $value['name'] ?? $value['value'] ?? $id));
                } else {
                    $id = trim((string) $value);
                    $label = $id;
                }

                if ($id !== '') {
                    $result[] = array('id' => $id, 'value' => $label !== '' ? $label : $id);
                }
            }
        }

        $this->storage->putCache($cacheKey, $result, min(3600, $this->cacheTtl()));
        return $result;
    }

    private function normalizeAttributeValue(string $type, $value)
    {
        if (is_array($value)) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if ($type === 'integer') {
            return (string) (int) $value;
        }

        if ($type === 'number') {
            return number_format((float) str_replace(',', '.', $value), 2, '.', '');
        }

        return $value;
    }

    private function hierarchyLabel(array $row, string $locale): string
    {
        $label = trim((string) ($row['label'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        $translations = isset($row['label_translations']) && is_array($row['label_translations']) ? $row['label_translations'] : array();
        $language = strtolower(substr($locale, 0, 2));

        foreach ($translations as $translation) {
            if (!is_array($translation)) {
                continue;
            }

            $translationLocale = strtolower((string) ($translation['locale'] ?? ''));
            if ($translationLocale === strtolower($locale) || strpos($translationLocale, $language) === 0) {
                $value = trim((string) ($translation['value'] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        foreach ($translations as $translation) {
            if (!is_array($translation)) {
                continue;
            }

            $value = trim((string) ($translation['value'] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return trim((string) ($row['code'] ?? ''));
    }

    private function buildHierarchyPath(string $id, array $map, array &$pathCache): string
    {
        $id = (string) $id;

        if (isset($pathCache[$id])) {
            return $pathCache[$id];
        }

        if (!isset($map[$id])) {
            return $id;
        }

        $item = $map[$id];
        $name = trim((string) ($item['name'] ?? $id));
        $parentId = trim((string) ($item['parent_id'] ?? ''));

        if ($parentId === '' || !isset($map[$parentId]) || $parentId === $id) {
            $pathCache[$id] = $name;
            return $pathCache[$id];
        }

        $pathCache[$id] = $this->buildHierarchyPath($parentId, $map, $pathCache) . ' > ' . $name;
        return $pathCache[$id];
    }

    private function normalizeOfferRow(array $account, array $offer): array
    {
        $price = $this->normalizeDecimal($offer['price'] ?? null);
        $totalPrice = $this->normalizeDecimal($offer['total_price'] ?? null);
        $shipping = $this->normalizeDecimal($offer['min_shipping_price'] ?? null);

        return array(
            'account_id' => (int) $account['id'],
            'offer_id' => (int) ($offer['offer_id'] ?? 0),
            'shop_sku' => $this->stringOrNull($offer['shop_sku'] ?? null),
            'product_sku' => $this->stringOrNull($offer['product_sku'] ?? null),
            'product_id' => $this->stringOrNull($offer['product_id'] ?? null),
            'product_title' => $this->stringOrNull($offer['product_title'] ?? null),
            'description' => $this->stringOrNull($offer['description'] ?? null),
            'category_code' => $this->stringOrNull($offer['category_code'] ?? null),
            'category_label' => $this->stringOrNull($offer['category_label'] ?? null),
            'state_code' => $this->stringOrNull($offer['state_code'] ?? null),
            'active' => !empty($offer['active']) ? 1 : 0,
            'quantity' => isset($offer['quantity']) ? (int) $offer['quantity'] : null,
            'price' => $price,
            'total_price' => $totalPrice,
            'currency_iso_code' => $this->stringOrNull($offer['currency_iso_code'] ?? null),
            'min_shipping_price' => $shipping,
            'leadtime_to_ship' => isset($offer['leadtime_to_ship']) ? (int) $offer['leadtime_to_ship'] : null,
            'offer_json' => json_encode($offer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'last_synced_at' => date('Y-m-d H:i:s'),
        );
    }

    /**
     * Builds a compact audit list returned by sync-only maintenance. Every downloaded row
     * is listed: "inserted" and "updated" describe business-data changes, while
     * "unchanged" means only last_synced_at was refreshed locally.
     */
    private function buildLocalUpdateLog(array $rows, array $existingRows, array $account): array
    {
        $comparisonFields = array(
            'shop_sku', 'product_sku', 'product_id', 'product_title', 'description',
            'category_code', 'category_label', 'state_code', 'active', 'quantity', 'price',
            'total_price', 'currency_iso_code', 'min_shipping_price', 'leadtime_to_ship',
        );
        $result = array();

        foreach ($rows as $row) {
            $offerId = (int) ($row['offer_id'] ?? 0);
            $previous = $existingRows[$offerId] ?? null;
            $changedFields = array();

            if (is_array($previous)) {
                foreach ($comparisonFields as $field) {
                    if (!$this->sameSyncedValue($field, $previous[$field] ?? null, $row[$field] ?? null)) {
                        $changedFields[] = $field;
                    }
                }
            }

            $result[] = array(
                'account_id' => (int) ($account['id'] ?? 0),
                'account' => (string) ($account['slug'] ?? ''),
                'offer_id' => (string) $offerId,
                'shop_sku' => (string) ($row['shop_sku'] ?? ''),
                'product_sku' => (string) ($row['product_sku'] ?? ''),
                'title' => (string) ($row['product_title'] ?? ''),
                'local_status' => $previous === null
                    ? 'inserted'
                    : ($changedFields !== array() ? 'updated' : 'unchanged'),
                'changed_fields' => $changedFields,
                'active' => (int) ($row['active'] ?? 0) === 1,
                'quantity' => isset($row['quantity']) ? (int) $row['quantity'] : null,
                'price' => $row['price'] ?? null,
                'state_code' => (string) ($row['state_code'] ?? ''),
                'synced_at' => (string) ($row['last_synced_at'] ?? ''),
            );
        }

        return $result;
    }

    private function sameSyncedValue(string $field, $previous, $current): bool
    {
        if ($previous === null || $current === null) {
            return $previous === null && $current === null;
        }

        if (in_array($field, array('active', 'quantity', 'leadtime_to_ship'), true)) {
            return (int) $previous === (int) $current;
        }

        if (in_array($field, array('price', 'total_price', 'min_shipping_price'), true)) {
            return number_format((float) $previous, 2, '.', '') === number_format((float) $current, 2, '.', '');
        }

        return (string) $previous === (string) $current;
    }

    private function queueLogEntry(array $row, array $offer = array()): array
    {
        $source = $offer !== array() ? $offer : $row;
        $operation = trim((string) ($row['operation'] ?? ''));
        $payload = is_array($row['payload'] ?? null) ? $row['payload'] : array();
        $requestedValue = null;
        $previousValue = null;

        switch ($operation) {
            case 'set_price':
                $requestedValue = isset($payload['value']) ? (string) $payload['value'] : null;
                $previousValue = isset($source['price']) ? (string) $source['price'] : null;
                break;
            case 'set_price_from_product':
                $requestedValue = isset($source['warehouse_price_gross']) && $source['warehouse_price_gross'] !== null
                    ? number_format((float) $source['warehouse_price_gross'], 2, '.', '')
                    : null;
                $previousValue = isset($source['price']) ? (string) $source['price'] : null;
                break;
            case 'set_stock_from_product':
                $requestedValue = isset($source['warehouse_quantity']) && $source['warehouse_quantity'] !== null
                    ? (int) $source['warehouse_quantity']
                    : null;
                $previousValue = isset($source['quantity']) ? (int) $source['quantity'] : null;
                break;
            case 'set_description':
                $requestedValue = isset($payload['value']) ? (string) $payload['value'] : null;
                break;
            case 'replace_description':
                $requestedValue = array(
                    'search' => (string) ($payload['search'] ?? ''),
                    'replace' => (string) ($payload['replace'] ?? ''),
                );
                break;
            case 'end_offer':
                $requestedValue = false;
                break;
            case 'resume_offer':
                $requestedValue = true;
                break;
        }

        return array(
            'queue_id' => (int) ($row['id'] ?? 0),
            'offer_row_id' => (int) ($row['offer_row_id'] ?? 0),
            'offer_id' => (string) ($source['offer_id'] ?? ''),
            'shop_sku' => (string) ($source['shop_sku'] ?? ''),
            'product_sku' => (string) ($source['product_sku'] ?? ''),
            'title' => (string) ($source['product_title'] ?? ''),
            'account' => (string) ($source['account_name'] ?? ''),
            'operation' => $operation,
            'previous_value' => $previousValue,
            'requested_value' => $requestedValue,
        );
    }

    private function recentQueueLog(int $limit): array
    {
        $result = array();
        foreach ($this->storage->recentQueueEntries($limit) as $row) {
            $payload = is_array($row['payload'] ?? null) ? $row['payload'] : array();
            $maintenanceLog = is_array($payload['_maintenance_log'] ?? null)
                ? $payload['_maintenance_log']
                : array();
            $status = strtolower(trim((string) ($row['status'] ?? '')));

            $result[] = array(
                'queue_id' => (int) ($row['queue_id'] ?? 0),
                'offer_row_id' => (int) ($row['offer_row_id'] ?? 0),
                'offer_id' => (string) ($row['offer_id'] ?? ''),
                'shop_sku' => (string) ($row['shop_sku'] ?? ''),
                'product_sku' => (string) ($row['product_sku'] ?? ''),
                'title' => (string) ($row['product_title'] ?? ''),
                'account' => (string) ($row['account_name'] ?? ''),
                'operation' => (string) ($row['operation'] ?? ''),
                'status' => $status === 'done' ? 'updated' : $status,
                'previous_value' => $maintenanceLog['previous_value'] ?? null,
                'requested_value' => $maintenanceLog['requested_value'] ?? ($payload['value'] ?? null),
                'import_id' => (string) ($row['remote_import_id'] ?? ''),
                'import_type' => (string) ($row['remote_import_type'] ?? ''),
                'error' => (string) ($row['error_message'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'finished_at' => (string) ($row['finished_at'] ?? ''),
            );
        }

        return $result;
    }

    private function normalizeQueueOperation(string $operation): string
    {
        $operation = trim($operation);
        $allowed = array(
            'set_price',
            'set_price_from_product',
            'set_stock_from_product',
            'set_description',
            'replace_description',
            'end_offer',
            'resume_offer',
            'clear_queue',
            'remove_from_system',
        );

        return in_array($operation, $allowed, true) ? $operation : '';
    }

    private function normalizeQueuePayload(string $operation, array $payload): array
    {
        switch ($operation) {
            case 'set_price':
                $value = $this->normalizeDecimal($payload['value'] ?? null);
                if ($value === null) {
                    throw new RuntimeException('Podaj poprawna cene dla Empika.');
                }
                return array('value' => $value);
            case 'set_price_from_product':
            case 'set_stock_from_product':
            case 'end_offer':
            case 'resume_offer':
                return array();
            case 'set_description':
                $value = trim((string) ($payload['value'] ?? ''));
                if ($value === '') {
                    throw new RuntimeException('Wpisz opis dla operacji Empik.');
                }
                return array('value' => $value);
            case 'replace_description':
                $search = trim((string) ($payload['search'] ?? ''));
                if ($search === '') {
                    throw new RuntimeException('Wpisz fraze do znalezienia w opisie.');
                }
                return array(
                    'search' => $search,
                    'replace' => (string) ($payload['replace'] ?? ''),
                );
        }

        return array();
    }

    private function executeQueueOperation(array $offer, string $operation, array $payload): array
    {
        switch ($operation) {
            case 'set_price':
                return $this->submitPriceImport($offer, (string) ($payload['value'] ?? ''));
            case 'set_price_from_product':
                if (!isset($offer['warehouse_price_gross']) || $offer['warehouse_price_gross'] === null || $offer['warehouse_price_gross'] === '') {
                    throw new RuntimeException('Oferta Empik nie ma powiazanego produktu z cena magazynowa.');
                }
                return $this->submitPriceImport($offer, number_format((float) $offer['warehouse_price_gross'], 2, '.', ''));
            case 'set_stock_from_product':
                if (!isset($offer['warehouse_quantity']) || $offer['warehouse_quantity'] === null || $offer['warehouse_quantity'] === '') {
                    throw new RuntimeException('Oferta Empik nie ma powiazanego produktu z iloscia magazynowa.');
                }
                return $this->submitStockImport($offer, (int) $offer['warehouse_quantity']);
            case 'set_description':
                return $this->submitOfferPatch($offer, array(
                    'description' => (string) ($payload['value'] ?? ''),
                ));
            case 'replace_description':
                $currentDescription = trim((string) ($offer['description'] ?? ''));
                $search = (string) ($payload['search'] ?? '');
                if ($currentDescription === '' || $search === '') {
                    throw new RuntimeException('Brak opisu lub frazy do podmiany w ofercie Empik.');
                }
                return $this->submitOfferPatch($offer, array(
                    'description' => str_replace($search, (string) ($payload['replace'] ?? ''), $currentDescription),
                ));
            case 'end_offer':
                return $this->submitOfferPatch($offer, array('active' => false));
            case 'resume_offer':
                return $this->submitOfferPatch($offer, array('active' => true));
        }

        throw new RuntimeException('Nieobslugiwana operacja Empik: ' . $operation);
    }

    private function submitPriceImport(array $offer, string $price): array
    {
        $shopSku = trim((string) ($offer['shop_sku'] ?? ''));
        if ($shopSku === '') {
            throw new RuntimeException('Oferta Empik nie ma shop_sku, wiec nie mozna wyslac ceny.');
        }

        $csv = "\"offer-sku\";\"price\";\"discount-price\";\"discount-start-date\";\"discount-end-date\"\n"
            . $this->csvRow(array($shopSku, $price, '', '', ''));

        $response = $this->requestMultipartImport($offer, '/api/offers/pricing/imports', $csv);
        $importId = trim((string) ($response['import_id'] ?? $response['importId'] ?? ''));
        if ($importId === '') {
            throw new RuntimeException('Empik nie zwrocil identyfikatora importu cen.');
        }

        return array('import_id' => $importId, 'import_type' => 'price');
    }

    private function submitStockImport(array $offer, int $quantity): array
    {
        $shopSku = trim((string) ($offer['shop_sku'] ?? ''));
        if ($shopSku === '') {
            throw new RuntimeException('Oferta Empik nie ma shop_sku, wiec nie mozna wyslac stanu.');
        }

        $csv = "\"offer-sku\";\"quantity\";\"warehouse-code\";\"update-delete\"\n"
            . $this->csvRow(array($shopSku, (string) $quantity, '', ''));

        $response = $this->requestMultipartImport($offer, '/api/offers/stock/imports', $csv);
        $importId = trim((string) ($response['import_id'] ?? ''));
        if ($importId === '') {
            throw new RuntimeException('Empik nie zwrocil identyfikatora importu stanow.');
        }

        return array('import_id' => $importId, 'import_type' => 'stock');
    }

    private function submitOfferPatch(array $offer, array $patch): array
    {
        $payload = json_decode((string) ($offer['offer_json'] ?? ''), true);
        if (!is_array($payload) || $payload === array()) {
            throw new RuntimeException('Brak kompletnego payloadu oferty Empik do aktualizacji.');
        }

        $payload = $this->sanitizeOfferPayloadForUpdate($payload);

        foreach ($patch as $key => $value) {
            $payload[$key] = $value;
        }

        $response = $this->requestApi(
            $offer,
            'POST',
            '/api/offers',
            array(),
            array('offers' => array($payload)),
            array('Content-Type: application/json')
        );

        $importId = trim((string) ($response['import_id'] ?? $response['importId'] ?? ''));
        if ($importId === '') {
            throw new RuntimeException('Empik nie zwrocil identyfikatora importu ofert.');
        }

        return array('import_id' => $importId, 'import_type' => 'offer');
    }

    private function requestMultipartImport(array $accountOrOffer, string $path, string $csv): array
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'empik_');
        if ($tmpFile === false) {
            throw new RuntimeException('Nie udalo sie przygotowac pliku importu Empik.');
        }

        file_put_contents($tmpFile, $csv);

        try {
            $file = new \CURLFile($tmpFile, 'text/csv', 'empik-import.csv');
            return $this->requestApi(
                $accountOrOffer,
                'POST',
                $path,
                array(),
                array('file' => $file),
                array()
            );
        } finally {
            @unlink($tmpFile);
        }
    }

    /**
     * Checks one already-submitted import. It deliberately does not sleep/poll: maintenance
     * must return promptly, and the queue row retains the import id for the next cron tick.
     */
    private function inspectImport(array $offer, string $type, string $importId): array
    {
        $status = $this->fetchImportStatus($offer, $type, $importId);
        $state = strtoupper(trim((string) ($status['status'] ?? '')));

        if ($state === 'FAILED') {
            $error = $this->fetchImportErrorReport($offer, $type, $importId);
            throw new RuntimeException($error !== '' ? $error : ('Import Empik zakonczyl sie statusem FAILED (' . $type . ').'));
        }

        if ($state === 'COMPLETE' && !empty($status['has_error_report'])) {
            $error = $this->fetchImportErrorReport($offer, $type, $importId);
            if ($error !== '') {
                throw new RuntimeException($error);
            }
        }

        return array(
            'complete' => $state === 'COMPLETE',
            'status' => $state !== '' ? $state : 'UNKNOWN',
        );
    }

    private function fetchImportStatus(array $offer, string $type, string $importId): array
    {
        switch ($type) {
            case 'price':
                $payload = $this->requestApi($offer, 'GET', '/api/offers/pricing/imports', array(
                    'import_id' => $importId,
                ));
                $items = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : array();
                return isset($items[0]) && is_array($items[0]) ? $items[0] : array();
            case 'stock':
                return $this->requestApi($offer, 'GET', '/api/offers/stock/imports/' . rawurlencode($importId) . '/status', array());
            case 'offer':
                return $this->requestApi($offer, 'GET', '/api/offers/imports/' . rawurlencode($importId), array());
        }

        throw new RuntimeException('Nieznany typ importu Empik: ' . $type);
    }

    private function fetchImportErrorReport(array $offer, string $type, string $importId): string
    {
        switch ($type) {
            case 'price':
                $path = '/api/offers/pricing/imports/' . rawurlencode($importId) . '/error_report';
                break;
            case 'stock':
                $path = '/api/offers/stock/imports/' . rawurlencode($importId) . '/error_report';
                break;
            case 'offer':
                $path = '/api/offers/imports/' . rawurlencode($importId) . '/error_report';
                break;
            default:
                return '';
        }

        $raw = $this->requestRaw($offer, 'GET', $path, array());
        return $this->parseImportErrorReport($raw);
    }

    private function parseImportErrorReport(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        $lines = preg_split('/\r\n|\n|\r/', $raw) ?: array();
        $messages = array();

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $line = trim($line, "\" \t");
            if (stripos($line, 'error') !== false || preg_match('/^\d+[;,]/', $line) === 1) {
                $messages[] = str_replace(array('";"', '";', ';"'), ' | ', $line);
            }
        }

        if ($messages === array()) {
            return function_exists('mb_substr') ? mb_substr($raw, 0, 500, 'UTF-8') : substr($raw, 0, 500);
        }

        return implode(' || ', array_slice($messages, 0, 3));
    }

    private function sanitizeOfferPayloadForUpdate(array $payload): array
    {
        foreach (array(
            'offer_id',
            'total_price',
            'currency_iso_code',
            'favorite_rank',
            'deleted',
            'date_created',
            'last_updated',
            'shop_name',
            'shop_id',
        ) as $key) {
            unset($payload[$key]);
        }

        if (isset($payload['shop_sku']) && !isset($payload['sku'])) {
            $payload['sku'] = $payload['shop_sku'];
        }

        return $payload;
    }

    private function requestApi(array $account, string $method, string $path, array $query = array(), $body = null, array $extraHeaders = array()): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Brak rozszerzenia cURL potrzebnego do integracji Empik.');
        }

        $baseUrl = rtrim((string) ($account['api_url'] ?? ''), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('Brak adresu API Empik.');
        }

        if (isset($account['shop_id']) && $account['shop_id'] !== null && $account['shop_id'] !== '') {
            $query['shop_id'] = (int) $account['shop_id'];
        }

        $url = $baseUrl . $path;
        if ($query !== array()) {
            $url .= '?' . http_build_query($query);
        }

        $this->enforceApiCooldown($account);
        $this->throttleBeforeRequest();

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Nie udalo sie zainicjalizowac polaczenia z Empik API.');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_TIMEOUT, max(5, min(30, (int) $this->configValue('request_timeout_seconds', 15))));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, max(2, min(15, (int) $this->configValue('connect_timeout_seconds', 5))));
        $headers = array(
            'Accept: ' . (string) $this->configValue('accept', 'application/json'),
            'Authorization: ' . (string) ($account['api_key'] ?? ''),
            'User-Agent: ALTREO-Empik-Mirakl/1.0',
        );

        foreach ($extraHeaders as $header) {
            $headers[] = $header;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($body !== null) {
            if (is_array($body) && !$this->bodyContainsCurlFile($body) && $this->hasJsonContentType($extraHeaders)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        }

        $responseHeaders = array();
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($curlHandle, string $headerLine) use (&$responseHeaders): int {
            $parts = explode(':', $headerLine, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }

            return strlen($headerLine);
        });

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (!is_string($raw)) {
            throw new RuntimeException('Empik API nie zwrocilo odpowiedzi.');
        }

        if ($httpCode === 429) {
            $retryAfter = $this->parseRetryAfterSeconds($responseHeaders);
            $retryAfter = $this->rememberApiCooldown($account, $retryAfter);
            throw new EmpikRateLimitException(
                'Empik API zwrocilo HTTP 429. Kolejne wywolanie API dla tego konta zostalo wstrzymane do '
                    . date(DATE_ATOM, time() + $retryAfter) . '.',
                $retryAfter
            );
        }

        $decoded = json_decode($raw, true);
        if ($httpCode < 200 || $httpCode >= 300) {
            $message = $this->extractApiError($decoded);
            if ($message === '' && $curlError !== '') {
                $message = $curlError;
            }

            throw new RuntimeException('Empik API zwrocilo blad HTTP ' . $httpCode . ($message !== '' ? ': ' . $message : '.'));
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Empik API zwrocilo niepoprawny JSON.');
        }

        return $decoded;
    }

    private function requestRaw(array $account, string $method, string $path, array $query = array()): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Brak rozszerzenia cURL potrzebnego do integracji Empik.');
        }

        $baseUrl = rtrim((string) ($account['api_url'] ?? ''), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('Brak adresu API Empik.');
        }

        if (isset($account['shop_id']) && $account['shop_id'] !== null && $account['shop_id'] !== '') {
            $query['shop_id'] = (int) $account['shop_id'];
        }

        $url = $baseUrl . $path;
        if ($query !== array()) {
            $url .= '?' . http_build_query($query);
        }

        $this->enforceApiCooldown($account);
        $this->throttleBeforeRequest();

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Nie udalo sie zainicjalizowac polaczenia z Empik API.');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_TIMEOUT, max(5, min(30, (int) $this->configValue('request_timeout_seconds', 15))));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, max(2, min(15, (int) $this->configValue('connect_timeout_seconds', 5))));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: text/plain, text/csv, application/octet-stream',
            'Authorization: ' . (string) ($account['api_key'] ?? ''),
            'User-Agent: ALTREO-Empik-Mirakl/1.0',
        ));

        $responseHeaders = array();
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($curlHandle, string $headerLine) use (&$responseHeaders): int {
            $parts = explode(':', $headerLine, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }

            return strlen($headerLine);
        });

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (!is_string($raw)) {
            throw new RuntimeException('Empik API nie zwrocilo odpowiedzi.');
        }

        if ($httpCode === 429) {
            $retryAfter = $this->parseRetryAfterSeconds($responseHeaders);
            $retryAfter = $this->rememberApiCooldown($account, $retryAfter);
            throw new EmpikRateLimitException(
                'Empik API zwrocilo HTTP 429. Kolejne wywolanie API dla tego konta zostalo wstrzymane do '
                    . date(DATE_ATOM, time() + $retryAfter) . '.',
                $retryAfter
            );
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException('Empik API zwrocilo blad HTTP ' . $httpCode . ($curlError !== '' ? ': ' . $curlError : '.'));
        }

        return $raw;
    }

    private function throttleBeforeRequest(): void
    {
        // Keep the protection in tracked application code as well as configuration. The
        // config directory is deployment-specific on this installation, so an older
        // production config must not silently restore the former ~3 requests/second pace.
        $minIntervalMicros = max(1100, (int) $this->configValue('min_request_interval_ms', 1100)) * 1000;
        if ($minIntervalMicros <= 0) {
            return;
        }

        $elapsedMicros = (microtime(true) - self::$lastRequestAtMicros) * 1000000;
        $wait = $minIntervalMicros - $elapsedMicros;

        if ($wait > 0) {
            usleep((int) $wait);
        }

        self::$lastRequestAtMicros = microtime(true);
    }

    private function enforceApiCooldown(array $account): void
    {
        $accountId = (int) ($account['id'] ?? 0);
        if ($accountId <= 0) {
            return;
        }

        $cached = $this->storage->getCache('empik:api-rate-limit:account:' . $accountId);
        $retryAt = is_array($cached) ? (int) ($cached['retry_at'] ?? 0) : 0;
        $remaining = $retryAt - time();
        if ($remaining <= 0) {
            return;
        }

        throw new EmpikRateLimitException(
            'Empik API pozostaje w okresie ochronnym po HTTP 429 do ' . date(DATE_ATOM, $retryAt) . '.',
            $remaining
        );
    }

    private function rememberApiCooldown(array $account, ?int $retryAfterSeconds): int
    {
        $retryAfter = max(
            60,
            min(86400, (int) ($retryAfterSeconds ?? $this->configValue('rate_limit_cooldown_seconds', 120)))
        );
        $accountId = (int) ($account['id'] ?? 0);

        if ($accountId > 0) {
            $this->storage->putCache(
                'empik:api-rate-limit:account:' . $accountId,
                array('retry_at' => time() + $retryAfter),
                $retryAfter
            );
        }

        return $retryAfter;
    }

    private function rateLimitRetryDelay(int $attempts, ?int $retryAfterSeconds): int
    {
        $backoff = self::RATE_LIMIT_BASE_DELAY_SECONDS * (2 ** max(0, $attempts));
        $delay = $retryAfterSeconds !== null ? max($retryAfterSeconds, self::RATE_LIMIT_BASE_DELAY_SECONDS) : $backoff;

        return min(self::RATE_LIMIT_MAX_DELAY_SECONDS, max(self::RATE_LIMIT_BASE_DELAY_SECONDS, $delay));
    }

    private function parseRetryAfterSeconds(array $headers): ?int
    {
        $value = trim((string) ($headers['retry-after'] ?? ''));
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return max(0, $timestamp - time());
    }

    private function bodyContainsCurlFile(array $body): bool
    {
        foreach ($body as $value) {
            if ($value instanceof \CURLFile) {
                return true;
            }
        }

        return false;
    }

    private function hasJsonContentType(array $headers): bool
    {
        foreach ($headers as $header) {
            if (stripos((string) $header, 'Content-Type: application/json') === 0) {
                return true;
            }
        }

        return false;
    }

    private function csvRow(array $values): string
    {
        $escaped = array_map(static function ($value): string {
            $value = (string) $value;
            return '"' . str_replace('"', '""', $value) . '"';
        }, $values);

        return implode(';', $escaped) . "\n";
    }

    private function extractApiError($decoded): string
    {
        if (!is_array($decoded)) {
            return '';
        }

        foreach (array('message', 'error_message', 'error', 'description') as $key) {
            if (!empty($decoded[$key]) && is_scalar($decoded[$key])) {
                return trim((string) $decoded[$key]);
            }
        }

        if (isset($decoded['errors']) && is_array($decoded['errors'])) {
            foreach ($decoded['errors'] as $error) {
                if (is_array($error)) {
                    foreach (array('message', 'error_message', 'description') as $key) {
                        if (!empty($error[$key]) && is_scalar($error[$key])) {
                            return trim((string) $error[$key]);
                        }
                    }
                } elseif (is_scalar($error)) {
                    return trim((string) $error);
                }
            }
        }

        return '';
    }

    private function resolveAccount(string $selector = '')
    {
        $selector = trim($selector);
        if ($selector !== '') {
            if (ctype_digit($selector)) {
                $account = $this->storage->findAccountById((int) $selector);
                if ($account) {
                    return $account;
                }
            }

            $account = $this->storage->findAccountBySlug($selector);
            if ($account) {
                return $account;
            }
        }

        $active = $this->storage->activeAccounts();
        return $active !== array() ? $active[0] : null;
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = $this->slugify($name);
        $slug = $base;
        $suffix = 1;

        while (true) {
            $row = $this->storage->findAccountBySlug($slug);
            if (!$row || ($ignoreId !== null && (int) $row['id'] === $ignoreId)) {
                return $slug;
            }

            $suffix++;
            $slug = $base . '-' . $suffix;
        }
    }

    private function slugify(string $text): string
    {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($transliterated !== false) {
            $text = $transliterated;
        }

        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim((string) $text, '-');

        return $text !== '' ? $text : 'empik';
    }

    private function normalizeDecimal($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function stringOrNull($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }

    private function searchTokens(string $search): array
    {
        $search = mb_strtolower(trim($search), 'UTF-8');
        $parts = preg_split('/\s+/u', $search);
        if (!is_array($parts)) {
            return array($search);
        }

        return array_values(array_filter(array_unique($parts), static function (string $value): bool {
            return $value !== '';
        }));
    }

    private function cacheTtl(): int
    {
        return max(300, (int) $this->configValue('cache_ttl', 86400));
    }

    private function defaultLocale(): string
    {
        return (string) $this->configValue('default_locale', 'pl_PL');
    }

    private function configValue(string $key, $default)
    {
        return array_key_exists($key, $this->config) ? $this->config[$key] : $default;
    }

    private function maybeCleanupExpiredCache(): void
    {
        $cacheKey = 'empik:cache-cleanup-throttle:v1';
        $cached = $this->storage->getCache($cacheKey);
        if (is_array($cached) && !empty($cached['done'])) {
            return;
        }

        $this->storage->cleanupExpiredCache();
        $this->storage->putCache($cacheKey, array('done' => 1), self::CACHE_CLEANUP_THROTTLE_SECONDS);
    }
}
