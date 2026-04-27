<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Models\AllegroStorageRepository;
use App\Models\ProductCustomFieldRepository;
use App\Models\ProductRepository;
use App\Models\SharedStockGroupRepository;
use App\Services\ProductChangeAuditService;
use RuntimeException;

class AllegroService
{
    /** @var array<string, string> */
    private $imageHashCache = array();

    /** @var array */
    private $config;

    /** @var AllegroStorageRepository */
    private $storage;

    public function __construct()
    {
        $app = Config::get('app');
        $this->config = isset($app['allegro']) && is_array($app['allegro']) ? $app['allegro'] : array();
        $this->storage = new AllegroStorageRepository(Database::instance());
        $this->storage->ensureSchema();
        $customFields = new ProductCustomFieldRepository(Database::instance());
        $customFields->ensureSchema();
        $this->storage->cleanupExpiredCache();
        $this->disableStoredWarehouseLinks();
    }

    public function listAccounts(): array
    {
        return $this->storage->allAccounts();
    }

    public function saveAccount(array $input, ?int $accountId = null): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $clientId = trim((string) ($input['client_id'] ?? ''));
        $clientSecret = trim((string) ($input['client_secret'] ?? ''));
        $redirectUri = trim((string) ($input['redirect_uri'] ?? ''));
        $isActive = !empty($input['is_active']) ? 1 : 0;

        if ($name === '' || $clientId === '' || $clientSecret === '' || $redirectUri === '') {
            throw new RuntimeException('Uzupelnij nazwe konta, client_id, client_secret i redirect_uri.');
        }

        if (filter_var($redirectUri, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Redirect URI musi byc poprawnym adresem URL.');
        }

        $existing = $accountId !== null ? $this->storage->findAccountById($accountId) : null;
        if ($accountId !== null && !$existing) {
            throw new RuntimeException('Nie znaleziono konta Allegro do edycji.');
        }

        $payload = array(
            'name' => $name,
            'slug' => $this->uniqueSlug($name, $accountId),
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'is_active' => $isActive,
            'sync_token' => $existing && !empty($existing['sync_token']) ? (string) $existing['sync_token'] : $this->randomToken(48),
        );

        $id = $this->storage->saveAccount($payload, $accountId);

        return (array) $this->storage->findAccountById($id);
    }

    public function authorizationUrl(int $accountId): string
    {
        $account = $this->storage->findAccountById($accountId);
        if (!$account) {
            throw new RuntimeException('Nie znaleziono konta Allegro.');
        }

        $state = $this->randomToken(24);
        $this->storage->storeOauthState($accountId, $state, date('Y-m-d H:i:s', time() + 900));

        return rtrim((string) $this->configValue('auth_base', 'https://allegro.pl/auth/oauth'), '/')
            . '/authorize?'
            . http_build_query(array(
                'response_type' => 'code',
                'client_id' => (string) $account['client_id'],
                'redirect_uri' => (string) $account['redirect_uri'],
                'state' => $state,
            ));
    }

    public function handleAuthorizationCallback(string $code, string $state): array
    {
        if ($code === '' || $state === '') {
            throw new RuntimeException('Brakuje kodu autoryzacyjnego lub state.');
        }

        $account = $this->storage->findAccountByOauthState($state);
        if (!$account) {
            throw new RuntimeException('Niepoprawny lub wygasniety state autoryzacji Allegro.');
        }

        $response = $this->oauthTokenForAccount(
            $account,
            array(
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => (string) $account['redirect_uri'],
            )
        );

        $this->saveTokenPayload((int) $account['id'], $response);
        $this->storage->clearOauthState((int) $account['id']);

        return (array) $this->storage->findAccountById((int) $account['id']);
    }

    public function offersPage(array $filters, int $page, int $perPage, string $sortBy, string $sortDir): array
    {
        return $this->storage->listOffers($filters, $page, $perPage, $sortBy, $sortDir);
    }

    public function countOffers(array $filters): int
    {
        return $this->storage->countOffers($filters);
    }

    public function offerStats(?int $accountId = null): array
    {
        return $this->storage->offerStats($accountId);
    }

    public function queueCounts(): array
    {
        return $this->storage->queueCounts();
    }

    public function clearQueueStatuses(bool $keepPending = true): array
    {
        $statuses = $keepPending
            ? array('done', 'error', 'retry')
            : array('pending', 'processing', 'done', 'error', 'retry');

        return array(
            'removed' => $this->storage->clearQueueByStatuses($statuses),
            'kept_pending' => $keepPending,
            'counts' => $this->storage->queueCounts(),
        );
    }

    public function clearWholeQueue(): array
    {
        return array(
            'removed' => $this->storage->clearWholeQueue(),
            'counts' => $this->storage->queueCounts(),
        );
    }

    public function automationLinks(string $baseUrl): array
    {
        $accounts = $this->listAccounts();
        $links = array(
            'queue_worker' => rtrim($baseUrl, '?&') . '?controller=allegro&action=maintenance&queue_limit=200',
            'full_maintenance' => rtrim($baseUrl, '?&') . '?controller=allegro&action=maintenance&sync=1&queue_limit=200&max_batches=3&offer_limit=100&max_runtime=30',
            'refresh_tokens' => rtrim($baseUrl, '?&') . '?controller=allegro&action=refreshtoken&format=json',
            'accounts' => array(),
        );

        foreach ($accounts as $account) {
            $trigger = $this->triggerUrl($account, $baseUrl);
            $maintenance = rtrim($baseUrl, '?&')
                . '?controller=allegro&action=maintenance&account=' . rawurlencode((string) $account['slug'])
                . '&sync=1&queue_limit=100&max_batches=3&offer_limit=100&max_runtime=30';
            $queueOnly = rtrim($baseUrl, '?&')
                . '?controller=allegro&action=maintenance&account=' . rawurlencode((string) $account['slug'])
                . '&queue_limit=100';

            $links['accounts'][] = array(
                'id' => (int) $account['id'],
                'name' => (string) $account['name'],
                'slug' => (string) $account['slug'],
                'is_active' => (int) ($account['is_active'] ?? 0) === 1,
                'sync' => $trigger,
                'maintenance' => $maintenance,
                'queue_only' => $queueOnly,
            );
        }

        return $links;
    }

    public function cleanupStorage(int $queueDoneDays = 14, int $queueErrorDays = 30, int $deletedProductsDays = 30): array
    {
        $queueDoneDays = max(0, $queueDoneDays);
        $queueErrorDays = max(0, $queueErrorDays);
        $deletedProductsDays = max(0, $deletedProductsDays);

        $products = new ProductRepository(Database::instance());
        $products->ensureSchema();

        $result = $this->storage->purgeQueueHistory($queueDoneDays, $queueErrorDays);
        $result['offers_detached_from_deleted_products'] = $this->storage->detachOffersFromDeletedProducts();

        return array_merge($result, $products->purgeDeletedProducts($deletedProductsDays));
    }

    public function offerDetails(int $id)
    {
        return $this->storage->findOfferById($id);
    }

    public function triggerUrl(array $account, string $baseUrl): string
    {
        return rtrim($baseUrl, '?&')
            . '?controller=allegro&action=sync&account=' . rawurlencode((string) $account['slug']);
    }

    public function refreshAccountToken(string $accountSelector): array
    {
        $account = $this->resolveAccount($accountSelector);
        if (!$account) {
            throw new RuntimeException('Nie znaleziono konta Allegro do odswiezenia tokena.');
        }

        $token = $this->forceRefreshAccessToken($account);
        $tokenRow = $this->storage->tokenRowForAccount((int) $account['id']);

        return array(
            'account' => array(
                'id' => (int) $account['id'],
                'name' => (string) $account['name'],
                'slug' => (string) $account['slug'],
            ),
            'token_expires_at' => (string) ($tokenRow['expires_at'] ?? ''),
            'token_updated_at' => (string) ($tokenRow['updated_at'] ?? ''),
            'refreshed' => $token !== '',
        );
    }

    public function refreshAllTokens(bool $activeOnly = true): array
    {
        $results = array();

        foreach ($this->listAccounts() as $account) {
            if ($activeOnly && (int) ($account['is_active'] ?? 0) !== 1) {
                continue;
            }

            try {
                $this->forceRefreshAccessToken($account);
                $tokenRow = $this->storage->tokenRowForAccount((int) $account['id']);
                $results[] = array(
                    'account' => (string) $account['name'],
                    'status' => 'ok',
                    'token_expires_at' => (string) ($tokenRow['expires_at'] ?? ''),
                    'token_updated_at' => (string) ($tokenRow['updated_at'] ?? ''),
                );
            } catch (RuntimeException $exception) {
                $results[] = array(
                    'account' => (string) $account['name'],
                    'status' => 'error',
                    'message' => $exception->getMessage(),
                );
            }
        }

        return $results;
    }

    public function maintenance(array $options = array()): array
    {
        $accountSelector = trim((string) ($options['account'] ?? ''));
        $queueLimit = max(1, min(1000, (int) ($options['queue_limit'] ?? 100)));

        $result = array(
            'refreshed_tokens' => $accountSelector !== ''
                ? array($this->refreshAccountToken($accountSelector))
                : $this->refreshAllTokens(true),
            'queue' => $this->processQueue(array(
                'limit' => $queueLimit,
                'account' => $accountSelector !== '' ? $accountSelector : null,
            )),
        );

        if (!empty($options['sync'])) {
            $syncOptions = array(
                'max_batches' => (int) ($options['max_batches'] ?? 5),
                'offer_limit' => (int) ($options['offer_limit'] ?? 100),
                'max_runtime' => (int) ($options['max_runtime'] ?? 20),
                'force_details' => !empty($options['force_details']),
            );

            if ($accountSelector !== '') {
                $result['sync'] = $this->syncAccount($accountSelector, $syncOptions);
            } else {
                $result['sync'] = array();
                foreach ($this->listAccounts() as $account) {
                    if ((int) ($account['is_active'] ?? 0) !== 1) {
                        continue;
                    }

                    try {
                        $result['sync'][] = array(
                            'account' => (string) $account['name'],
                            'result' => $this->syncAccount((string) $account['slug'], $syncOptions),
                        );
                    } catch (RuntimeException $exception) {
                        $result['sync'][] = array(
                            'account' => (string) $account['name'],
                            'error' => $exception->getMessage(),
                        );
                    }
                }
            }
        }

        if (!empty($options['compact_offers'])) {
            $result['offer_compaction'] = $this->compactStoredOffers(array(
                'account' => $accountSelector !== '' ? $accountSelector : null,
                'limit' => (int) ($options['compact_limit'] ?? 500),
            ));
        }

        return $result;
    }

    public function compactStoredOffers(array $options = array()): array
    {
        $accountSelector = trim((string) ($options['account'] ?? ''));
        $accountId = null;

        if ($accountSelector !== '') {
            $account = $this->resolveAccount($accountSelector);
            if (!$account) {
                throw new RuntimeException('Nie znaleziono konta Allegro do kompaktacji ofert.');
            }
            $accountId = (int) ($account['id'] ?? 0);
        }

        $limit = max(1, min(5000, (int) ($options['limit'] ?? 500)));
        $rows = $this->storage->fetchOffersForCompaction($limit, $accountId);
        $processed = 0;
        $updated = 0;
        $skipped = 0;
        $bytesBefore = 0;
        $bytesAfter = 0;

        foreach ($rows as $row) {
            $processed++;
            $offerJson = (string) ($row['offer_json'] ?? '');
            $productSetJson = (string) ($row['product_set_json'] ?? '');
            $marketplacesJson = (string) ($row['marketplaces_json'] ?? '');
            $beforeSize = strlen($offerJson) + strlen($productSetJson) + strlen($marketplacesJson);
            $bytesBefore += $beforeSize;

            $decoded = json_decode($offerJson, true);
            if (!is_array($decoded)) {
                $skipped++;
                $bytesAfter += $beforeSize;
                continue;
            }

            $compactedOffer = $this->compactOfferDetailsForStorage($decoded);
            $compactedProductSet = $this->compactProductSetForStorage($decoded);
            $compactedMarketplaces = $this->extractMarketplaces($decoded);

            $newOfferJson = json_encode($compactedOffer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $newProductSetJson = json_encode($compactedProductSet, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $newMarketplacesJson = json_encode($compactedMarketplaces, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $afterSize = strlen((string) $newOfferJson) + strlen((string) $newProductSetJson) + strlen((string) $newMarketplacesJson);
            $bytesAfter += $afterSize;

            if ($newOfferJson === $offerJson && $newProductSetJson === $productSetJson && $newMarketplacesJson === $marketplacesJson) {
                $skipped++;
                continue;
            }

            $this->storage->updateOfferCompactedPayloads(
                (int) ($row['id'] ?? 0),
                $newOfferJson !== false ? $newOfferJson : null,
                $newProductSetJson !== false ? $newProductSetJson : null,
                $newMarketplacesJson !== false ? $newMarketplacesJson : null
            );
            $updated++;
        }

        return array(
            'processed' => $processed,
            'updated' => $updated,
            'skipped' => $skipped,
            'bytes_before' => $bytesBefore,
            'bytes_after' => $bytesAfter,
            'bytes_saved' => max(0, $bytesBefore - $bytesAfter),
        );
    }

    public function linkOfferToProduct(int $offerRowId, ?int $productId, string $linkedBy = 'manual'): array
    {
        $offer = $this->storage->findOfferById($offerRowId);
        if (!$offer) {
            throw new RuntimeException('Nie znaleziono oferty Allegro.');
        }

        return (array) $offer;
    }

    public function enqueueOfferChanges(array $filters, string $operation, array $payload = array(), string $manualIdentifiers = ''): array
    {
        $targets = array();
        $accountId = isset($filters['account_id']) && ctype_digit((string) $filters['account_id'])
            ? (int) $filters['account_id']
            : null;

        $manualIdentifiers = trim($manualIdentifiers);
        if ($manualIdentifiers !== '') {
            $tokens = preg_split('/[\s,;]+/', $manualIdentifiers) ?: array();
            $targets = $this->storage->resolveOfferTargets($tokens, $accountId);
        } else {
            $targets = $this->storage->offerTargetsForFilters($filters);
        }

        if ($targets === array()) {
            throw new RuntimeException('Brak ofert pasujacych do kolejki zmian.');
        }

        if ($operation === 'clear_queue') {
            return array(
                'removed' => $this->storage->clearQueueForOffers($targets),
                'offers' => count($targets),
                'operation' => $operation,
            );
        }

        if ($operation === 'remove_from_system') {
            return array(
                'removed' => $this->storage->deleteOffersByTargets($targets),
                'offers' => count($targets),
                'operation' => $operation,
            );
        }

        $filteredOut = 0;
        if ($operation === 'end_offer') {
            $endOfferTargets = $this->storage->filterTerminableEndOfferTargets($targets);
            $targets = $endOfferTargets['allowed'] ?? array();
            $filteredOut = count($endOfferTargets['blocked'] ?? array());

            if ($targets === array()) {
                throw new RuntimeException('Nie ma ofert, ktore mozna zakonczyc. Najstarsze oferty w grupach dubli zostaly automatycznie odfiltrowane.');
            }
        }

        $normalizedPayload = $this->normalizeQueuePayload($operation, $payload);
        $queued = $this->storage->enqueueOfferChanges($targets, $operation, $normalizedPayload);

        return array(
            'queued' => $queued,
            'operation' => $operation,
            'filtered_out' => $filteredOut,
        );
    }

    public function queueWarehouseProductSync(array $productIds, int $delaySeconds = 180): array
    {
        $expandedProductIds = $this->expandWarehouseSyncProductIds($productIds);
        if ($expandedProductIds === array()) {
            return array(
                'products' => 0,
                'offers' => 0,
                'queued' => 0,
                'available_at' => null,
            );
        }

        $targets = $this->storage->offerTargetsForWarehouseProductIds($expandedProductIds);
        if ($targets === array()) {
            return array(
                'products' => count($expandedProductIds),
                'offers' => 0,
                'queued' => 0,
                'available_at' => null,
            );
        }

        $availableAt = date('Y-m-d H:i:s', time() + max(0, $delaySeconds));
        $queued = 0;
        // $queued += $this->storage->enqueueOfferChanges($targets, 'set_price_from_product', array(), $availableAt, true);
        // $queued += $this->storage->enqueueOfferChanges($targets, 'set_stock_from_product', array(), $availableAt, true);

        return array(
            'products' => count($expandedProductIds),
            'offers' => count($targets),
            'queued' => $queued,
            'available_at' => $availableAt,
        );
    }

    public function processQueue(array $options = array()): array
    {
        $accountSelector = trim((string) ($options['account'] ?? ''));
        $accountId = null;
        if ($accountSelector !== '') {
            $account = $this->resolveAccount($accountSelector);
            if (!$account) {
                throw new RuntimeException('Nie znaleziono konta Allegro do przetwarzania kolejki.');
            }
            $accountId = (int) $account['id'];
        }

        $limit = max(1, min(1000, (int) ($options['limit'] ?? 100)));
        $batch = $this->storage->fetchQueueBatch($limit, $accountId);
        $summary = array(
            'processed' => 0,
            'done' => 0,
            'retry' => 0,
            'error' => 0,
        );

        foreach ($batch as $item) {
            $queueId = (int) ($item['id'] ?? 0);
            if ($queueId <= 0) {
                continue;
            }

            $summary['processed']++;
            $this->storage->markQueueProcessing($queueId);

            try {
                $this->applyQueuedOperation($item);
                $this->storage->markQueueDone($queueId);
                $summary['done']++;
            } catch (RuntimeException $exception) {
                $attempts = (int) ($item['attempts'] ?? 0) + 1;
                $retryPolicy = $this->queueRetryPolicy($item, $exception, $attempts);
                $this->storage->markQueueRetry(
                    $queueId,
                    $exception->getMessage(),
                    $attempts,
                    (int) $retryPolicy['delay_seconds'],
                    isset($retryPolicy['status']) ? (string) $retryPolicy['status'] : null
                );
                if (($retryPolicy['status'] ?? '') === 'error') {
                    $summary['error']++;
                } else {
                    $summary['retry']++;
                }
            }
        }

        $summary['counts'] = $this->storage->queueCounts();
        return $summary;
    }

    private function queueRetryPolicy(array $item, RuntimeException $exception, int $attempts): array
    {
        $operation = trim((string) ($item['operation'] ?? ''));
        $message = trim($exception->getMessage());

        if (
            $operation === 'remove_from_system_forever'
            && stripos($message, 'Oferta nie jest jeszcze zakonczona') !== false
        ) {
            return array(
                'status' => 'retry',
                'delay_seconds' => max(900, min(7200, $attempts * 900)),
            );
        }

        return array(
            'status' => $attempts >= 5 ? 'error' : 'retry',
            'delay_seconds' => $attempts * 60,
        );
    }

    public function syncAccount(string $accountSelector, array $options = array()): array
    {
        $account = $this->resolveAccount($accountSelector);
        if (!$account) {
            throw new RuntimeException('Nie znaleziono konta Allegro do synchronizacji.');
        }

        if ((int) ($account['is_active'] ?? 0) !== 1) {
            throw new RuntimeException('Konto Allegro jest nieaktywne.');
        }

        $accountId = (int) $account['id'];
        $lockTtl = max(120, (int) ($options['lock_ttl'] ?? 900));
        $staleHeartbeatSeconds = max(60, (int) ($options['stale_heartbeat_seconds'] ?? 300));
        $forceLock = !empty($options['force_lock']);

        if (!$this->storage->acquireSyncLock($accountId, $lockTtl, $staleHeartbeatSeconds, $forceLock)) {
            return array(
                'status' => 'busy',
                'account' => $this->publicAccountData($account),
                'state' => $this->storage->syncState($accountId),
            );
        }

        $maxBatches = max(1, min(100, (int) ($options['max_batches'] ?? 5)));
        $offerLimit = max(1, min(100, (int) ($options['offer_limit'] ?? 100)));
        $maxRuntime = max(10, min(240, (int) ($options['max_runtime'] ?? 20)));
        $forceDetails = !empty($options['force_details']);
        $startTime = time();
        $summary = array(
            'status' => 'ok',
            'account' => $this->publicAccountData($account),
            'offers_processed' => 0,
            'details_refreshed' => 0,
            'pages_processed' => 0,
            'events_processed' => 0,
            'finished_cycle' => false,
        );

        try {
            $state = $this->storage->syncState($accountId);
            $cycle = !empty($state['current_cycle']) ? (string) $state['current_cycle'] : $this->uuidV4();

            $this->storage->updateSyncState($accountId, array(
                'mode' => 'full',
                'current_cycle' => $cycle,
                'heartbeat_at' => date('Y-m-d H:i:s'),
            ));

            $summary['events_processed'] = $this->syncOfferEvents($account, $cycle, $state, $maxRuntime, $startTime);
            $offset = (int) ($state['offer_offset'] ?? 0);

            for ($page = 0; $page < $maxBatches; $page++) {
                if ((time() - $startTime) >= $maxRuntime) {
                    $summary['status'] = 'partial';
                    break;
                }

                $payload = $this->requestApiWithAccount(
                    $account,
                    'GET',
                    '/sale/offers',
                    array(
                        'limit' => $offerLimit,
                        'offset' => $offset,
                    )
                );

                $offers = isset($payload['offers']) && is_array($payload['offers']) ? $payload['offers'] : array();
                if ($offers === array()) {
                    $this->finalizeFullCycle($accountId, $cycle);
                    $summary['finished_cycle'] = true;
                    break;
                }

                $checksums = $this->storage->findOfferChecksums($accountId, array_column($offers, 'id'));
                $excludedOfferIds = array_fill_keys($this->storage->excludedOfferIds($accountId, array_column($offers, 'id')), true);

                foreach ($offers as $offer) {
                    $normalized = $this->normalizeOfferSummary($offer);
                    if ($normalized['offer_id'] === '') {
                        continue;
                    }

                    if (isset($excludedOfferIds[$normalized['offer_id']])) {
                        continue;
                    }

                    $existing = $checksums[$normalized['offer_id']] ?? null;
                    $needsDetails = $forceDetails
                        || !$existing
                        || (string) ($existing['summary_checksum'] ?? '') !== $normalized['summary_checksum']
                        || (string) ($existing['details_checksum'] ?? '') === '';

                    if ($needsDetails) {
                        $details = $this->requestApiWithAccount(
                            $account,
                            'GET',
                            '/sale/product-offers/' . rawurlencode($normalized['offer_id'])
                        );
                        $this->storage->upsertOffer($this->buildOfferPayload($accountId, $cycle, $normalized, $details, null, null));
                        $this->syncLinkedWarehouseProductFromOffer($accountId, $normalized['offer_id']);
                        $summary['details_refreshed']++;
                    } else {
                        $this->storage->touchOffer($accountId, $normalized['offer_id'], $cycle);
                    }

                    $summary['offers_processed']++;
                }

                $offset += $offerLimit;
                $summary['pages_processed']++;

                $this->storage->updateSyncState($accountId, array(
                    'offer_offset' => $offset,
                    'heartbeat_at' => date('Y-m-d H:i:s'),
                    'last_incremental_sync_at' => date('Y-m-d H:i:s'),
                ));

                if (count($offers) < $offerLimit) {
                    $this->finalizeFullCycle($accountId, $cycle);
                    $summary['finished_cycle'] = true;
                    break;
                }
            }

            $this->storage->markSyncSuccess($accountId);
            $summary['auto_linked'] = $this->autoLinkOffersToWarehouse($accountId, 1000);
            $summary['warehouse_products_refreshed'] = $this->syncWarehouseProductsFromCycle($accountId, $cycle);
            $summary['state'] = $this->storage->syncState($accountId);
        } catch (RuntimeException $exception) {
            $this->storage->markAccountError($accountId, $exception->getMessage());
            $this->storage->releaseSyncLock($accountId, $exception->getMessage());
            throw $exception;
        }

        $this->storage->releaseSyncLock($accountId);
        return $summary;
    }

    private function publicAccountData(array $account): array
    {
        return array(
            'id' => isset($account['id']) ? (int) $account['id'] : 0,
            'name' => (string) ($account['name'] ?? ''),
            'slug' => (string) ($account['slug'] ?? ''),
            'is_active' => (int) ($account['is_active'] ?? 0),
            'last_auth_at' => (string) ($account['last_auth_at'] ?? ''),
            'last_sync_at' => (string) ($account['last_sync_at'] ?? ''),
            'last_error_at' => isset($account['last_error_at']) ? (string) $account['last_error_at'] : null,
            'last_error_message' => isset($account['last_error_message']) ? (string) $account['last_error_message'] : null,
            'created_at' => (string) ($account['created_at'] ?? ''),
            'updated_at' => (string) ($account['updated_at'] ?? ''),
        );
    }

    public function searchCategories($search, $forceRefresh = false): array
    {
        $search = trim((string) $search);
        if ($search === '') {
            return array();
        }

        $cacheKey = 'allegro_categories_v5_' . md5(mb_strtolower($search, 'UTF-8'));
        $cacheTtl = min(3600, $this->cacheTtl());

        if (!$forceRefresh) {
            $cached = $this->storage->getCache($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $result = array();

        try {
            $payload = $this->requestApi('GET', '/sale/matching-categories', array('name' => $search));
            foreach ($this->extractMatchingCollection($payload) as $item) {
                $normalized = $this->normalizeCategorySearchItem($item);
                if ($normalized !== null && $this->matchesSearch($normalized, $search)) {
                    $result[$normalized['id']] = $normalized;
                }
            }
        } catch (RuntimeException $exception) {
        }

        if ($result === array()) {
            try {
                $fallback = $this->requestApi('GET', '/sale/categories', array('name' => $search));
                $categories = isset($fallback['categories']) && is_array($fallback['categories']) ? $fallback['categories'] : array();

                foreach ($categories as $item) {
                    $normalized = $this->normalizeCategorySearchItem($item);
                    if ($normalized !== null && $this->matchesSearch($normalized, $search)) {
                        $result[$normalized['id']] = $normalized;
                    }
                }
            } catch (RuntimeException $exception) {
            }
        }

        if ($result === array() && $this->shouldUseTreeSearchFallback($search, (bool) $forceRefresh)) {
            foreach ($this->searchByTree($search, (bool) $forceRefresh) as $item) {
                $result[$item['id']] = $item;
            }
        }

        $items = $this->enrichPaths(array_values($result), (bool) $forceRefresh);
        usort($items, function (array $a, array $b): int {
            $aLeaf = !empty($a['leaf']) ? 1 : 0;
            $bLeaf = !empty($b['leaf']) ? 1 : 0;
            if ($aLeaf !== $bLeaf) {
                return $bLeaf - $aLeaf;
            }

            return strcmp((string) ($a['path'] ?? $a['name']), (string) ($b['path'] ?? $b['name']));
        });

        $this->storage->putCache($cacheKey, $items, $cacheTtl);
        return $items;
    }

    private function shouldUseTreeSearchFallback(string $search, bool $forceRefresh): bool
    {
        if ($forceRefresh) {
            return true;
        }

        $search = trim($search);
        if ($search === '') {
            return false;
        }

        // Heavy tree scanning is too slow for longer, phrase-based searches like "etui i pokrowce".
        if (mb_strlen($search, 'UTF-8') > 12) {
            return false;
        }

        return mb_strpos($search, ' ', 0, 'UTF-8') === false;
    }

    public function categoryParameters($categoryId): array
    {
        $categoryId = trim((string) $categoryId);
        if ($categoryId === '') {
            return array();
        }

        $cacheKey = 'allegro_category_params_v5_' . md5($categoryId);
        $cached = $this->storage->getCache($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $payload = $this->requestApi('GET', '/sale/categories/' . rawurlencode($categoryId) . '/parameters');
        $parameters = isset($payload['parameters']) && is_array($payload['parameters']) ? $payload['parameters'] : array();
        $result = array();

        foreach ($parameters as $parameter) {
            if (!is_array($parameter) || !isset($parameter['id'])) {
                continue;
            }

            $dictionary = array();
            $dictionaryRows = array();

            if (isset($parameter['dictionary']) && is_array($parameter['dictionary'])) {
                if (isset($parameter['dictionary']['values']) && is_array($parameter['dictionary']['values'])) {
                    $dictionaryRows = $parameter['dictionary']['values'];
                } elseif (array_keys($parameter['dictionary']) === range(0, count($parameter['dictionary']) - 1)) {
                    $dictionaryRows = $parameter['dictionary'];
                }
            }

            foreach ($dictionaryRows as $option) {
                $id = is_array($option) ? (string) ($option['id'] ?? $option['value'] ?? '') : (string) $option;
                $label = is_array($option) ? (string) ($option['value'] ?? $option['name'] ?? $id) : (string) $option;
                if ($id !== '') {
                    $dictionary[] = array('id' => $id, 'value' => $label);
                }
            }

            $multipleChoices = isset($parameter['restrictions']['multipleChoices']) && !empty($parameter['restrictions']['multipleChoices']);
            $type = strtolower((string) ($parameter['type'] ?? 'string'));
            if ($type === 'dictionary' && $multipleChoices) {
                $type = 'multidictionary';
            }

            $result[] = array(
                'id' => (string) $parameter['id'],
                'name' => (string) ($parameter['name'] ?? ''),
                'required' => !empty($parameter['required']),
                'required_for_product' => !empty($parameter['requiredForProduct']),
                'describes_product' => !empty($parameter['options']['describesProduct']),
                'type' => $type,
                'unit' => (string) ($parameter['unit'] ?? ''),
                'multiple' => $multipleChoices,
                'options' => isset($parameter['options']) && is_array($parameter['options']) ? $parameter['options'] : array(),
                'restrictions' => isset($parameter['restrictions']) && is_array($parameter['restrictions']) ? $parameter['restrictions'] : array(),
                'dictionary' => $dictionary,
            );
        }

        $this->storage->putCache($cacheKey, $result, $this->cacheTtl());
        return $result;
    }

    public function validateParameterValues(array $definitions, array $inputValues): array
    {
        $validated = array();

        foreach ($definitions as $definition) {
            $id = (string) ($definition['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $type = (string) ($definition['type'] ?? 'string');
            $dictionary = isset($definition['dictionary']) && is_array($definition['dictionary']) ? $definition['dictionary'] : array();
            $multiple = !empty($definition['multiple']) || $type === 'multidictionary';
            $raw = array_key_exists($id, $inputValues) ? $inputValues[$id] : null;

            if ($dictionary !== array()) {
                $allowed = array_map(static function (array $item): string {
                    return (string) ($item['id'] ?? '');
                }, $dictionary);

                if ($multiple) {
                    $values = is_array($raw) ? $raw : array($raw);
                    $clean = array();
                    foreach ($values as $value) {
                        $value = trim((string) $value);
                        if ($value === '') {
                            continue;
                        }
                        if (!in_array($value, $allowed, true)) {
                            throw new RuntimeException('Niepoprawna wartosc parametru "' . (string) ($definition['name'] ?? $id) . '".');
                        }
                        $clean[] = $value;
                    }
                    if ($clean !== array()) {
                        $validated[$id] = $clean;
                    }
                    continue;
                }

                $single = trim((string) $raw);
                if ($single !== '') {
                    if (!in_array($single, $allowed, true)) {
                        throw new RuntimeException('Niepoprawna wartosc parametru "' . (string) ($definition['name'] ?? $id) . '".');
                    }
                    $validated[$id] = $single;
                }
                continue;
            }

            if ($multiple) {
                $items = is_array($raw) ? $raw : preg_split('/\r\n|\r|\n/', trim((string) $raw));
                $cleanValues = array();
                foreach ($items as $item) {
                    $item = trim((string) $item);
                    if ($item !== '') {
                        $cleanValues[] = $item;
                    }
                }
                if ($cleanValues !== array()) {
                    $validated[$id] = $cleanValues;
                }
                continue;
            }

            $single = trim((string) $raw);
            if ($single !== '') {
                $validated[$id] = $single;
            }
        }

        return $validated;
    }

    private function normalizeQueuePayload(string $operation, array $payload): array
    {
        switch ($operation) {
            case 'set_name':
                $value = trim((string) ($payload['value'] ?? ''));
                if ($value === '') {
                    throw new RuntimeException('Nowa nazwa nie moze byc pusta.');
                }
                return array('value' => $value);

            case 'replace_name':
                $search = (string) ($payload['search'] ?? '');
                if ($search === '') {
                    throw new RuntimeException('Fraza do zamiany nie moze byc pusta.');
                }
                return array(
                    'search' => $search,
                    'replace' => (string) ($payload['replace'] ?? ''),
                );

            case 'set_price':
                $value = $this->normalizeDecimal($payload['value'] ?? null);
                if ($value === null) {
                    throw new RuntimeException('Cena musi byc poprawna liczba.');
                }
                return array('value' => $value);

            case 'set_price_from_product':
            case 'set_stock_from_product':
            case 'link_product_auto':
            case 'end_offer':
            case 'resume_offer':
            case 'clear_queue':
            case 'remove_from_system_forever':
                return array();

            case 'set_delivery':
                $value = trim((string) ($payload['delivery_value'] ?? ''));
                $allowed = array('PT0H', 'PT24H', 'PT48H', 'PT72H', 'P7D');
                if (!in_array($value, $allowed, true)) {
                    throw new RuntimeException('Wybierz poprawny czas wysylki.');
                }
                return array('delivery_value' => $value);

            case 'set_invoice':
                $value = trim((string) ($payload['invoice_value'] ?? ''));
                $allowed = array('VAT', 'NO_INVOICE', 'B2B');
                if (!in_array($value, $allowed, true)) {
                    throw new RuntimeException('Wybierz poprawna opcje faktury.');
                }
                return array('invoice_value' => $value);

            case 'set_sku':
                $value = trim((string) ($payload['value'] ?? ''));
                $warehouseProductId = trim((string) ($payload['warehouse_product_id'] ?? ''));
                if ($value === '' && $warehouseProductId === '') {
                    throw new RuntimeException('Podaj reczne SKU albo wybierz produkt z magazynu.');
                }
                return array(
                    'value' => $value,
                    'warehouse_product_id' => $warehouseProductId,
                );

            case 'set_category_parameters':
                $categoryId = trim((string) ($payload['category_id'] ?? ''));
                if ($categoryId === '') {
                    throw new RuntimeException('Wybierz kategorie Allegro.');
                }

                $definitions = $this->categoryParameters($categoryId);
                if ($definitions === array()) {
                    throw new RuntimeException('Nie udalo sie pobrac parametrow dla wybranej kategorii.');
                }

                $inputParameters = isset($payload['category_parameters']) && is_array($payload['category_parameters'])
                    ? $payload['category_parameters']
                    : array();
                $validatedParameters = $this->validateParameterValues($definitions, $inputParameters);

                return array(
                    'category_id' => $categoryId,
                    'parameters' => $validatedParameters,
                );

            case 'link_product_id':
                $productId = trim((string) ($payload['product_id'] ?? ''));
                if ($productId === '') {
                    throw new RuntimeException('Podaj Allegro product ID.');
                }
                return array('product_id' => $productId);
        }

        throw new RuntimeException('Nieobslugiwana operacja kolejki: ' . $operation);
    }

    private function applyQueuedOperation(array $item): void
    {
        $account = $this->storage->findAccountById((int) ($item['account_id'] ?? 0));
        if (!$account) {
            throw new RuntimeException('Brak konta Allegro dla pozycji kolejki.');
        }

        $offerId = trim((string) ($item['offer_id'] ?? ''));
        if ($offerId === '') {
            throw new RuntimeException('Brak offer_id dla pozycji kolejki.');
        }

        $offer = $this->offerDetails((int) ($item['offer_row_id'] ?? 0));
        if (!$offer) {
            throw new RuntimeException('Oferta nie istnieje juz lokalnie.');
        }

        $operation = (string) ($item['operation'] ?? '');
        $payload = isset($item['payload']) && is_array($item['payload']) ? $item['payload'] : array();

        if ($operation === 'end_offer') {
            $this->changePublicationAction($account, $offerId, 'END');
            $this->refreshOfferSnapshotFromApi($account, (int) $offer['id'], $offerId);
            return;
        }

        if ($operation === 'resume_offer') {
            $this->changePublicationAction($account, $offerId, 'ACTIVATE');
            $this->refreshOfferSnapshotFromApi($account, (int) $offer['id'], $offerId);
            return;
        }

        if ($operation === 'remove_from_system_forever') {
            $details = $this->requestApiWithAccount(
                $account,
                'GET',
                '/sale/product-offers/' . rawurlencode($offerId)
            );
            $summary = $this->normalizeOfferSummary($details);
            $status = strtoupper((string) ($summary['publication_status'] ?? ''));

            if (!in_array($status, array('ENDED', 'INACTIVE'), true)) {
                $this->changePublicationAction($account, $offerId, 'END');
                $this->refreshOfferSnapshotFromApi($account, (int) $offer['id'], $offerId);
                throw new RuntimeException('Oferta nie jest jeszcze zakończona. Wysłano zakończenie i ponowimy sprawdzenie.');
            }

            $this->storage->addOfferExclusion((int) $account['id'], $offerId, 'permanent', 'removed_from_system_forever');
            $this->storage->deleteOffersByTargets(array(array('id' => (int) ($offer['id'] ?? 0))));
            return;
        }

        $patch = array();

        switch ($operation) {
            case 'set_name':
                $patch['name'] = (string) $payload['value'];
                break;

            case 'replace_name':
                $currentName = (string) ($offer['name'] ?? '');
                $patch['name'] = str_replace((string) $payload['search'], (string) ($payload['replace'] ?? ''), $currentName);
                break;

            case 'set_price':
                $patch['sellingMode'] = array(
                    'price' => array(
                        'amount' => (string) $payload['value'],
                        'currency' => (string) ($offer['price_currency'] ?? 'PLN'),
                    ),
                );
                break;

            case 'set_price_from_product':
                if (!isset($offer['warehouse_quantity']) && empty($offer['warehouse_sku'])) {
                    $offer = $this->offerDetails((int) $offer['id']);
                }
                $price = $offer && isset($offer['warehouse_product_id']) && $offer['warehouse_product_id'] ? $this->warehousePriceFromOffer($offer) : null;
                if ($price === null) {
                    throw new RuntimeException('Oferta nie ma przypisanego produktu magazynowego z cena.');
                }
                $patch['sellingMode'] = array(
                    'price' => array(
                        'amount' => $price,
                        'currency' => (string) ($offer['price_currency'] ?? 'PLN'),
                    ),
                );
                break;

            case 'set_stock_from_product':
                if (!isset($offer['warehouse_quantity']) && empty($offer['warehouse_sku'])) {
                    $offer = $this->offerDetails((int) $offer['id']);
                }
                if (!isset($offer['warehouse_product_id']) || !(int) $offer['warehouse_product_id']) {
                    throw new RuntimeException('Oferta nie ma przypisanego produktu magazynowego ze stanem.');
                }
                $stockAvailable = isset($offer['warehouse_quantity']) ? max(0, (int) $offer['warehouse_quantity']) : null;
                if ($stockAvailable === null) {
                    throw new RuntimeException('Nie znaleziono stanu magazynowego do wyslania.');
                }
                $patch['stock'] = array(
                    'available' => $stockAvailable,
                    'unit' => (string) ($offer['offer_payload']['stock']['unit'] ?? 'UNIT'),
                );
                break;

            case 'set_delivery':
                $patch['delivery'] = array(
                    'handlingTime' => (string) $payload['delivery_value'],
                );
                break;

            case 'set_invoice':
                $patch['payments'] = array(
                    'invoice' => (string) $payload['invoice_value'],
                );
                break;

            case 'set_sku':
                $warehouseSku = '';
                $selectedWarehouseProductId = trim((string) ($payload['warehouse_product_id'] ?? ''));
                if ($selectedWarehouseProductId !== '' && ctype_digit($selectedWarehouseProductId)) {
                    $warehouseSku = $this->warehouseSkuByProductId((int) $selectedWarehouseProductId);
                }
                if ($warehouseSku === '') {
                    $warehouseSku = trim((string) ($payload['value'] ?? ''));
                }
                if ($warehouseSku === '') {
                    $warehouseSku = trim((string) ($offer['warehouse_sku'] ?? ''));
                }
                if ($warehouseSku === '') {
                    throw new RuntimeException('Nie znaleziono SKU do ustawienia.');
                }
                $patch['external'] = array('id' => $warehouseSku);
                break;

            case 'link_product_id':
                $patch['name'] = (string) ($offer['name'] ?? '');
                $patch['images'] = $this->patchImagesFromOffer($offer);
                $patch['productSet'] = array(
                    array(
                        'product' => array(
                            'id' => (string) $payload['product_id'],
                        ),
                    ),
                );
                break;

            case 'link_product_auto':
                $productId = $this->detectBestAllegroProductId($account, $offer);
                if ($productId === null) {
                    throw new RuntimeException('Nie udalo sie znalezc produktu Allegro do automatycznego podpiecia.');
                }
                $patch['name'] = (string) ($offer['name'] ?? '');
                $patch['images'] = $this->patchImagesFromOffer($offer);
                $patch['productSet'] = array(
                    array(
                        'product' => array(
                            'id' => $productId,
                        ),
                    ),
                );
                break;

            case 'set_category_parameters':
                $categoryId = trim((string) ($payload['category_id'] ?? ''));
                if ($categoryId === '') {
                    throw new RuntimeException('Brak kategorii Allegro do ustawienia.');
                }

                $definitions = $this->categoryParameters($categoryId);
                if ($definitions === array()) {
                    throw new RuntimeException('Nie udalo sie pobrac parametrow dla wybranej kategorii.');
                }

                $currentDetails = $this->requestApiWithAccount(
                    $account,
                    'GET',
                    '/sale/product-offers/' . rawurlencode($offerId)
                );
                $currentCategoryId = trim((string) ($currentDetails['category']['id'] ?? ''));
                $submittedParameters = isset($payload['parameters']) && is_array($payload['parameters'])
                    ? $payload['parameters']
                    : array();

                $parameterPayload = $this->buildCategoryParameterPatchPayload(
                    $definitions,
                    $submittedParameters,
                    isset($currentDetails['parameters']) && is_array($currentDetails['parameters']) ? $currentDetails['parameters'] : array(),
                    false,
                    $currentCategoryId !== $categoryId
                );
                $productParameterPayload = $this->buildCategoryParameterPatchPayload(
                    $definitions,
                    $submittedParameters,
                    $this->extractProductParametersFromOfferDetails($currentDetails),
                    true,
                    $currentCategoryId !== $categoryId
                );

                $patch['category'] = array('id' => $categoryId);
                $patch['parameters'] = $parameterPayload;

                $existingProductSet = isset($currentDetails['productSet']) && is_array($currentDetails['productSet'])
                    ? $currentDetails['productSet']
                    : array();
                if ($existingProductSet !== array()) {
                    $firstItem = isset($existingProductSet[0]) && is_array($existingProductSet[0]) ? $existingProductSet[0] : array();
                    if (isset($firstItem['product']) && is_array($firstItem['product'])) {
                        $firstItem['product']['parameters'] = $productParameterPayload;
                        $existingProductSet[0] = $firstItem;
                        $patch['productSet'] = $existingProductSet;
                    }
                }
                break;

            default:
                throw new RuntimeException('Nieobslugiwana operacja kolejki: ' . $operation);
        }

        $this->patchOffer($account, $offerId, $patch);
        $this->refreshOfferSnapshotFromApi($account, (int) $offer['id'], $offerId);
    }

    private function syncOfferEvents(array $account, string $cycle, array $state, int $maxRuntime, int $startTime): int
    {
        $processed = 0;
        $lastEventId = trim((string) ($state['last_event_id'] ?? ''));

        if ($lastEventId === '') {
            return 0;
        }

        try {
            $payload = $this->requestApiWithAccount(
                $account,
                'GET',
                '/sale/offer-events',
                array('from' => $lastEventId)
            );
        } catch (RuntimeException $exception) {
            return 0;
        }

        $events = isset($payload['offerEvents']) && is_array($payload['offerEvents'])
            ? $payload['offerEvents']
            : (isset($payload['events']) && is_array($payload['events']) ? $payload['events'] : array());
        if ($events === array()) {
            return 0;
        }

        foreach ($events as $event) {
            if ((time() - $startTime) >= $maxRuntime) {
                break;
            }

            $offerId = (string) ($event['offer']['id'] ?? '');
            $eventId = (string) ($event['id'] ?? '');
            $occurredAt = $this->normalizeDateTime($event['occurredAt'] ?? null);

            if ($offerId === '' || $eventId === '') {
                continue;
            }

            if ($this->storage->isOfferExcluded((int) $account['id'], $offerId)) {
                $lastEventId = $eventId;
                $processed++;
                continue;
            }

            try {
                $details = $this->requestApiWithAccount($account, 'GET', '/sale/product-offers/' . rawurlencode($offerId));
                $summary = $this->normalizeOfferSummary($details);
                $this->storage->upsertOffer($this->buildOfferPayload((int) $account['id'], $cycle, $summary, $details, $eventId, $occurredAt));
                $this->syncLinkedWarehouseProductFromOffer((int) $account['id'], $offerId);
            } catch (RuntimeException $exception) {
                $this->storage->touchOffer((int) $account['id'], $offerId, $cycle, $eventId, $occurredAt);
            }

            $lastEventId = $eventId;
            $processed++;
        }

        if ($lastEventId !== '') {
            $this->storage->updateSyncState((int) $account['id'], array(
                'last_event_id' => $lastEventId,
                'last_event_at' => date('Y-m-d H:i:s'),
                'last_incremental_sync_at' => date('Y-m-d H:i:s'),
                'heartbeat_at' => date('Y-m-d H:i:s'),
            ));
        }

        return $processed;
    }

    private function finalizeFullCycle(int $accountId, string $cycle): void
    {
        $now = date('Y-m-d H:i:s');
        $this->storage->markMissingOffersAsEnded($accountId, $cycle);
        $this->storage->updateSyncState($accountId, array(
            'offer_offset' => 0,
            'current_cycle' => null,
            'last_full_sync_at' => $now,
            'last_incremental_sync_at' => $now,
            'heartbeat_at' => $now,
        ));
    }

    private function buildOfferPayload(int $accountId, string $cycle, array $summary, array $details, ?string $eventId, ?string $eventAt): array
    {
        $images = $this->extractImages($details);
        $parameters = $this->extractParameters($details);
        $marketplaces = $this->extractMarketplaces($details);
        $productSet = $this->compactProductSetForStorage($details);
        $compactedDetails = $this->compactOfferDetailsForStorage($details);

        return array(
            'account_id' => $accountId,
            'offer_id' => $summary['offer_id'],
            'sku' => $summary['sku'] !== '' ? $summary['sku'] : ($summary['external_id'] !== '' ? $summary['external_id'] : null),
            'external_id' => $summary['external_id'] !== '' ? $summary['external_id'] : null,
            'warehouse_product_id' => null,
            'linked_by' => null,
            'name' => $summary['name'],
            'primary_image_url' => $summary['primary_image_url'] !== '' ? $summary['primary_image_url'] : ($images[0]['url'] ?? null),
            'primary_image_hash' => $this->hashFirstOfferImage($summary, $images),
            'image_count' => count($images),
            'images_json' => json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'price_amount' => $summary['price_amount'],
            'price_currency' => $summary['price_currency'] !== '' ? $summary['price_currency'] : null,
            'publication_status' => $summary['publication_status'] !== '' ? $summary['publication_status'] : null,
            'publication_ended_by' => $summary['publication_ended_by'] !== '' ? $summary['publication_ended_by'] : null,
            'stock_available' => $summary['stock_available'],
            'stock_sold' => $summary['stock_sold'],
            'invoice_type' => $this->extractInvoiceType($details),
            'allegro_product_id' => $this->extractAllegroProductId($details),
            'category_id' => ($details['category']['id'] ?? $summary['category_id'] ?? '') !== '' ? (string) ($details['category']['id'] ?? $summary['category_id']) : null,
            'category_name' => ($details['category']['name'] ?? $summary['category_name'] ?? '') !== '' ? (string) ($details['category']['name'] ?? $summary['category_name']) : null,
            'marketplaces_json' => json_encode($marketplaces, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'product_set_json' => json_encode($productSet, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'parameters_json' => json_encode($parameters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'offer_json' => json_encode($compactedDetails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'summary_checksum' => $summary['summary_checksum'],
            'details_checksum' => hash('sha256', json_encode($details)),
            'last_seen_cycle' => $cycle,
            'last_event_id' => $eventId,
            'last_event_at' => $eventAt,
            'last_synced_at' => date('Y-m-d H:i:s'),
        );
    }

    private function normalizeOfferSummary(array $offer): array
    {
        $externalId = '';
        if (isset($offer['external']) && is_array($offer['external'])) {
            $externalId = (string) ($offer['external']['id'] ?? '');
        }

        $summary = array(
            'offer_id' => trim((string) ($offer['id'] ?? '')),
            'sku' => $externalId,
            'external_id' => $externalId,
            'name' => trim((string) ($offer['name'] ?? '')),
            'primary_image_url' => trim((string) ($offer['primaryImage']['url'] ?? '')),
            'price_amount' => $this->normalizeDecimal($offer['sellingMode']['price']['amount'] ?? null),
            'price_currency' => trim((string) ($offer['sellingMode']['price']['currency'] ?? '')),
            'publication_status' => trim((string) ($offer['publication']['status'] ?? '')),
            'publication_ended_by' => trim((string) ($offer['publication']['endedBy'] ?? '')),
            'stock_available' => $this->normalizeInteger($offer['stock']['available'] ?? null),
            'stock_sold' => $this->normalizeInteger($offer['stock']['sold'] ?? null),
            'category_id' => trim((string) ($offer['category']['id'] ?? '')),
            'category_name' => trim((string) ($offer['category']['name'] ?? '')),
        );

        $summary['summary_checksum'] = hash(
            'sha256',
            json_encode(
                array(
                    'offer_id' => $summary['offer_id'],
                    'sku' => $summary['sku'],
                    'name' => $summary['name'],
                    'primary_image_url' => $summary['primary_image_url'],
                    'price_amount' => $summary['price_amount'],
                    'price_currency' => $summary['price_currency'],
                    'publication_status' => $summary['publication_status'],
                    'publication_ended_by' => $summary['publication_ended_by'],
                    'stock_available' => $summary['stock_available'],
                    'stock_sold' => $summary['stock_sold'],
                ),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        return $summary;
    }

    private function extractImages(array $details): array
    {
        $items = array();
        $seen = array();
        $candidates = array();

        if (isset($details['images']) && is_array($details['images'])) {
            $candidates = $details['images'];
        } elseif (isset($details['productSet'][0]['product']['images']) && is_array($details['productSet'][0]['product']['images'])) {
            $candidates = $details['productSet'][0]['product']['images'];
        }

        foreach ($candidates as $image) {
            $url = is_array($image) ? (string) ($image['url'] ?? '') : (string) $image;
            if ($url === '' || isset($seen[$url])) {
                continue;
            }

            $seen[$url] = true;
            $items[] = array('url' => $url);
        }

        return $items;
    }

    private function hashFirstOfferImage(array $summary, array $images): ?string
    {
        $url = trim((string) ($summary['primary_image_url'] ?? ''));
        if ($url === '' && isset($images[0]['url'])) {
            $url = trim((string) $images[0]['url']);
        }

        if ($url === '') {
            return null;
        }

        if (isset($this->imageHashCache[$url])) {
            return $this->imageHashCache[$url] !== '' ? $this->imageHashCache[$url] : null;
        }

        if (!function_exists('curl_init')) {
            $this->imageHashCache[$url] = '';
            return null;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            $this->imageHashCache[$url] = '';
            return null;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Altreo-AllegroSync/1.0');

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($raw) || $raw === '' || $httpCode < 200 || $httpCode >= 300) {
            $this->imageHashCache[$url] = '';
            return null;
        }

        $hash = hash('sha256', $raw);
        $this->imageHashCache[$url] = $hash;
        return $hash;
    }

    private function extractParameters(array $details): array
    {
        $parameters = array();
        $sources = array();

        if (isset($details['parameters']) && is_array($details['parameters'])) {
            $sources = array_merge($sources, $details['parameters']);
        }

        if (isset($details['productSet']) && is_array($details['productSet'])) {
            foreach ($details['productSet'] as $productSetItem) {
                if (isset($productSetItem['product']['parameters']) && is_array($productSetItem['product']['parameters'])) {
                    $sources = array_merge($sources, $productSetItem['product']['parameters']);
                }
            }
        }

        foreach ($sources as $parameter) {
            if (!is_array($parameter)) {
                continue;
            }

            $values = array();
            if (isset($parameter['values']) && is_array($parameter['values'])) {
                foreach ($parameter['values'] as $value) {
                    if (is_array($value)) {
                        $values[] = (string) ($value['name'] ?? $value['value'] ?? $value['id'] ?? '');
                    } else {
                        $values[] = (string) $value;
                    }
                }
            } elseif (isset($parameter['valuesIds']) && is_array($parameter['valuesIds'])) {
                foreach ($parameter['valuesIds'] as $value) {
                    $values[] = (string) $value;
                }
            }

            $parameters[] = array(
                'id' => (string) ($parameter['id'] ?? ''),
                'name' => (string) ($parameter['name'] ?? ''),
                'values' => array_values(array_filter($values, static function (string $value): bool {
                    return $value !== '';
                })),
            );
        }

        return $parameters;
    }

    private function extractMarketplaces(array $details): array
    {
        $items = array();
        $this->appendMarketplaceId($items, $details['publication']['marketplaces']['base']['id'] ?? null);

        if (isset($details['publication']['marketplaces']['additional']) && is_array($details['publication']['marketplaces']['additional'])) {
            foreach ($details['publication']['marketplaces']['additional'] as $market) {
                $this->appendMarketplaceId($items, is_array($market) ? ($market['id'] ?? null) : $market);
            }
        }

        if (isset($details['additionalMarketplaces']) && is_array($details['additionalMarketplaces'])) {
            foreach (array_keys($details['additionalMarketplaces']) as $marketId) {
                $this->appendMarketplaceId($items, $marketId);
            }
        }

        return $items;
    }

    private function compactOfferDetailsForStorage(array $details): array
    {
        $payload = array(
            'id' => (string) ($details['id'] ?? ''),
            'name' => (string) ($details['name'] ?? ''),
            'external' => array(
                'id' => (string) ($details['external']['id'] ?? ''),
            ),
            'category' => array(
                'id' => (string) ($details['category']['id'] ?? ''),
                'name' => (string) ($details['category']['name'] ?? ''),
            ),
            'publication' => array(
                'status' => (string) ($details['publication']['status'] ?? ''),
                'endedBy' => (string) ($details['publication']['endedBy'] ?? ''),
                'marketplaces' => array(
                    'base' => array(
                        'id' => (string) ($details['publication']['marketplaces']['base']['id'] ?? ''),
                    ),
                    'additional' => array_values(array_map(static function ($market): array {
                        return array('id' => (string) ((is_array($market) ? ($market['id'] ?? '') : $market) ?? ''));
                    }, isset($details['publication']['marketplaces']['additional']) && is_array($details['publication']['marketplaces']['additional']) ? $details['publication']['marketplaces']['additional'] : array())),
                ),
            ),
            'sellingMode' => array(
                'format' => (string) ($details['sellingMode']['format'] ?? ''),
                'price' => array(
                    'amount' => (string) ($details['sellingMode']['price']['amount'] ?? ''),
                    'currency' => (string) ($details['sellingMode']['price']['currency'] ?? ''),
                ),
            ),
            'stock' => array(
                'available' => $details['stock']['available'] ?? null,
                'sold' => $details['stock']['sold'] ?? null,
                'unit' => (string) ($details['stock']['unit'] ?? ''),
            ),
            'payments' => array(
                'invoice' => (string) ($details['payments']['invoice'] ?? ''),
            ),
            'delivery' => array(
                'handlingTime' => (string) ($details['delivery']['handlingTime'] ?? ''),
            ),
            'images' => array_values(array_map(static function (array $image): string {
                return (string) ($image['url'] ?? '');
            }, $this->extractImages($details))),
            'productSet' => $this->compactProductSetForStorage($details),
            'additionalMarketplaces' => $this->compactAdditionalMarketplacesForStorage($details),
            'updatedAt' => (string) ($details['updatedAt'] ?? ''),
            'createdAt' => (string) ($details['createdAt'] ?? ''),
        );

        return $this->removeEmptyValuesRecursively($payload);
    }

    private function compactProductSetForStorage(array $details): array
    {
        $result = array();
        $productSet = isset($details['productSet']) && is_array($details['productSet']) ? $details['productSet'] : array();

        foreach ($productSet as $item) {
            if (!is_array($item)) {
                continue;
            }

            $productId = trim((string) ($item['product']['id'] ?? ''));
            if ($productId === '') {
                continue;
            }

            $entry = array(
                'product' => array(
                    'id' => $productId,
                    'publication' => array(
                        'status' => (string) ($item['product']['publication']['status'] ?? ''),
                    ),
                ),
            );

            $result[] = $this->removeEmptyValuesRecursively($entry);
        }

        return $result;
    }

    private function compactAdditionalMarketplacesForStorage(array $details): array
    {
        $result = array();
        $markets = isset($details['additionalMarketplaces']) && is_array($details['additionalMarketplaces'])
            ? $details['additionalMarketplaces']
            : array();

        foreach ($markets as $marketId => $marketData) {
            if (!is_array($marketData)) {
                continue;
            }

            $result[(string) $marketId] = $this->removeEmptyValuesRecursively(array(
                'sellingMode' => array(
                    'price' => array(
                        'amount' => (string) ($marketData['sellingMode']['price']['amount'] ?? ''),
                        'currency' => (string) ($marketData['sellingMode']['price']['currency'] ?? ''),
                    ),
                ),
                'publication' => array(
                    'state' => (string) ($marketData['publication']['state'] ?? ''),
                ),
            ));
        }

        return $result;
    }

    private function appendMarketplaceId(array &$items, $value): void
    {
        $marketId = trim((string) $value);
        if ($marketId !== '' && !in_array($marketId, $items, true)) {
            $items[] = $marketId;
        }
    }

    private function removeEmptyValuesRecursively($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        $result = array();
        foreach ($value as $key => $item) {
            $cleaned = $this->removeEmptyValuesRecursively($item);

            if (is_array($cleaned) && $cleaned === array()) {
                continue;
            }

            if ($cleaned === '' || $cleaned === null) {
                continue;
            }

            $result[$key] = $cleaned;
        }

        return $result;
    }

    private function extractInvoiceType(array $details): ?string
    {
        $invoice = trim((string) ($details['payments']['invoice'] ?? ''));
        return $invoice !== '' ? $invoice : null;
    }

    private function extractAllegroProductId(array $details): ?string
    {
        if (isset($details['productSet'][0]['product']['id'])) {
            $value = trim((string) $details['productSet'][0]['product']['id']);
            return $value !== '' ? $value : null;
        }

        if (isset($details['product']['id'])) {
            $value = trim((string) $details['product']['id']);
            return $value !== '' ? $value : null;
        }

        return null;
    }

    private function requestApi(string $method, string $path, array $query = array(), $body = null, array $headers = array()): array
    {
        $account = $this->storage->firstAuthorizedAccount();
        if (!$account) {
            throw new RuntimeException('Brak autoryzowanego konta Allegro. Dodaj konto i wykonaj logowanie Allegro.');
        }

        return $this->requestApiWithAccount($account, $method, $path, $query, $body, $headers);
    }

    private function requestApiWithAccount(array $account, string $method, string $path, array $query = array(), $body = null, array $headers = array()): array
    {
        return $this->requestApiWithTokenRetry($account, $method, $path, $query, $body, $headers, false);
    }

    private function requestApiWithTokenRetry(array $account, string $method, string $path, array $query = array(), $body = null, array $headers = array(), bool $forceRefresh = false): array
    {
        $accessToken = $forceRefresh ? $this->forceRefreshAccessToken($account) : $this->accessTokenForAccount($account);
        $url = rtrim((string) $this->configValue('api_base', 'https://api.allegro.pl'), '/') . $path;

        if ($query !== array()) {
            $url .= '?' . http_build_query($query);
        }

        $requestHeaders = array_merge(
            array(
                'Accept: ' . (string) $this->configValue('accept', 'application/vnd.allegro.public.v1+json'),
                'Authorization: Bearer ' . $accessToken,
            ),
            $headers
        );

        try {
            return $this->request($method, $url, $requestHeaders, $body);
        } catch (RuntimeException $exception) {
            if ($forceRefresh || strpos($exception->getMessage(), '[401]') === false) {
                throw $exception;
            }

            return $this->requestApiWithTokenRetry($account, $method, $path, $query, $body, $headers, true);
        }
    }

    private function accessTokenForAccount(array $account): string
    {
        $accountId = (int) $account['id'];
        $refreshMargin = (int) $this->configValue('token_refresh_margin', 60);
        $tokenRow = $this->storage->tokenRowForAccount($accountId);

        if ($tokenRow && !empty($tokenRow['access_token']) && !empty($tokenRow['expires_at'])) {
            $expiresAt = strtotime((string) $tokenRow['expires_at']);
            if ($expiresAt !== false && ($expiresAt - $refreshMargin) > time()) {
                return (string) $tokenRow['access_token'];
            }
        }

        $refreshToken = $tokenRow && !empty($tokenRow['refresh_token']) ? (string) $tokenRow['refresh_token'] : '';
        if ($refreshToken === '') {
            throw new RuntimeException('Konto "' . (string) $account['name'] . '" nie ma refresh tokena. Zaloguj konto ponownie.');
        }

        $response = $this->oauthTokenForAccount(
            $account,
            array(
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'redirect_uri' => (string) $account['redirect_uri'],
            )
        );

        $this->saveTokenPayload($accountId, $response);
        return (string) ($response['access_token'] ?? '');
    }

    private function forceRefreshAccessToken(array $account): string
    {
        $accountId = (int) $account['id'];
        $tokenRow = $this->storage->tokenRowForAccount($accountId);
        $refreshToken = $tokenRow && !empty($tokenRow['refresh_token']) ? (string) $tokenRow['refresh_token'] : '';
        if ($refreshToken === '') {
            throw new RuntimeException('Konto "' . (string) $account['name'] . '" nie ma refresh tokena. Zaloguj konto ponownie.');
        }

        $response = $this->oauthTokenForAccount(
            $account,
            array(
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'redirect_uri' => (string) $account['redirect_uri'],
            )
        );

        $this->saveTokenPayload($accountId, $response);
        return (string) ($response['access_token'] ?? '');
    }

    private function oauthTokenForAccount(array $account, array $params): array
    {
        return $this->request(
            'POST',
            rtrim((string) $this->configValue('auth_base', 'https://allegro.pl/auth/oauth'), '/') . '/token',
            array(
                'Authorization: Basic ' . base64_encode((string) $account['client_id'] . ':' . (string) $account['client_secret']),
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ),
            http_build_query($params)
        );
    }

    private function saveTokenPayload(int $accountId, array $response): void
    {
        if (empty($response['access_token'])) {
            throw new RuntimeException('Allegro OAuth nie zwrocilo access_token.');
        }

        $expiresIn = isset($response['expires_in']) ? (int) $response['expires_in'] : 3600;
        $this->storage->saveToken($accountId, array(
            'access_token' => (string) $response['access_token'],
            'refresh_token' => isset($response['refresh_token']) ? (string) $response['refresh_token'] : null,
            'expires_at' => date('Y-m-d H:i:s', time() + max(60, $expiresIn)),
            'token_type' => isset($response['token_type']) ? (string) $response['token_type'] : null,
            'scope' => isset($response['scope']) ? (string) $response['scope'] : null,
        ));
    }

    private function request(string $method, string $url, array $headers = array(), $body = null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Brak rozszerzenia cURL - nie mozna polaczyc z Allegro API.');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Nie mozna zainicjowac polaczenia HTTP.');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Blad polaczenia z Allegro API: ' . $error);
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            $decoded = array();
        }

        if ($httpCode >= 400) {
            $message = isset($decoded['errors'][0]['message']) ? (string) $decoded['errors'][0]['message'] : ('HTTP ' . $httpCode);
            throw new RuntimeException('Allegro API error [' . $httpCode . ']: ' . $message);
        }

        return $decoded;
    }

    private function resolveAccount(string $accountSelector)
    {
        $selector = trim($accountSelector);
        if ($selector === '') {
            return null;
        }

        if (ctype_digit($selector)) {
            return $this->storage->findAccountById((int) $selector);
        }

        return $this->storage->findAccountBySlug($selector);
    }

    private function patchOffer(array $account, string $offerId, array $patch): void
    {
        $headers = array('Content-Type: application/vnd.allegro.public.v1+json');
        $this->requestApiWithAccount(
            $account,
            'PATCH',
            '/sale/product-offers/' . rawurlencode($offerId),
            array(),
            json_encode($patch, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $headers
        );
    }

    private function changePublicationAction(array $account, string $offerId, string $action): void
    {
        $commandId = $this->uuidV4();
        $payload = array(
            'publication' => array(
                'action' => $action,
            ),
            'offerCriteria' => array(
                array(
                    'type' => 'CONTAINS_OFFERS',
                    'offers' => array(
                        array('id' => $offerId),
                    ),
                ),
            ),
        );

        $this->requestApiWithAccount(
            $account,
            'PUT',
            '/sale/offer-publication-commands/' . $commandId,
            array(),
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            array('Content-Type: application/vnd.allegro.public.v1+json')
        );
    }

    private function refreshOfferSnapshotFromApi(array $account, int $offerRowId, string $offerId): void
    {
        $details = $this->requestApiWithAccount(
            $account,
            'GET',
            '/sale/product-offers/' . rawurlencode($offerId)
        );
        $summary = $this->normalizeOfferSummary($details);
        $payload = $this->buildOfferPayload((int) $account['id'], $this->uuidV4(), $summary, $details, null, null);

        $this->storage->upsertOffer($payload);
    }

    private function autoLinkOffersToWarehouse(?int $accountId = null, int $limit = 500): int
    {
        return 0;
    }

    private function ensureAutoLinkedOfferOnRead(array $offer): bool
    {
        return false;
    }

    private function resolveWarehouseProductIdForAllegroSku(
        ProductRepository $products,
        ProductCustomFieldRepository $customFields,
        string $allegroSku,
        string $offerName = ''
    ): ?int {
        if (preg_match('/[a-z]/i', $allegroSku) === 1) {
            $product = $products->findBySku($allegroSku);
            return $product ? (int) ($product['id'] ?? 0) : null;
        }

        if (ctype_digit($allegroSku)) {
            $productIds = $customFields->findProductIdsBySlugAndValue('old_sku', $allegroSku);
            return $this->bestProductIdForOldSkuCandidates($products, $productIds, $offerName);
        }

        return null;
    }

    private function bestProductIdForOldSkuCandidates(ProductRepository $products, array $productIds, string $offerName): ?int
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds), static function (int $id): bool {
            return $id > 0;
        })));
        if ($productIds === array()) {
            return null;
        }

        if (count($productIds) === 1) {
            return $productIds[0];
        }

        $activeCandidates = array();
        $scored = array();
        foreach ($productIds as $productId) {
            $product = $products->find($productId);
            if (!$product) {
                continue;
            }

            $activeCandidates[] = (int) ($product['id'] ?? 0);

            $score = $this->offerNameMatchScore(
                (string) ($product['product_name'] ?? ''),
                (string) ($product['sku'] ?? ''),
                $offerName
            );
            $scored[] = array(
                'id' => $productId,
                'score' => $score,
            );
        }

        if ($scored === array()) {
            return null;
        }

        $activeCandidates = array_values(array_unique(array_filter($activeCandidates)));
        if (count($activeCandidates) === 1) {
            return (int) $activeCandidates[0];
        }

        usort($scored, static function (array $a, array $b): int {
            $scoreDiff = (int) ($b['score'] ?? 0) - (int) ($a['score'] ?? 0);
            if ($scoreDiff !== 0) {
                return $scoreDiff;
            }

            return (int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0);
        });

        $best = $scored[0];
        $secondScore = isset($scored[1]) ? (int) ($scored[1]['score'] ?? 0) : -1;
        $bestScore = (int) ($best['score'] ?? 0);

        if ($bestScore <= 0) {
            return count($scored) === 1 ? (int) ($best['id'] ?? 0) : null;
        }

        if ($secondScore === $bestScore) {
            return null;
        }

        return (int) ($best['id'] ?? 0) > 0 ? (int) $best['id'] : null;
    }

    private function offerNameMatchScore(string $productName, string $sku, string $offerName): int
    {
        $productNameLower = mb_strtolower($productName, 'UTF-8');
        $skuLower = mb_strtolower($sku, 'UTF-8');
        $tokens = $this->offerMatchTokens($offerName);
        $score = 0;

        foreach ($tokens as $token) {
            if ($productNameLower === $token) {
                $score += 25;
                continue;
            }

            if (mb_stripos($productNameLower, $token, 0, 'UTF-8') !== false) {
                $score += preg_match('/[a-z]+\d+|\d+[a-z]+/iu', $token) ? 35 : 15;
            }

            if (mb_stripos($skuLower, $token, 0, 'UTF-8') !== false) {
                $score += 10;
            }
        }

        return $score;
    }

    private function offerMatchTokens(string $value): array
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[^a-z0-9]+/iu', ' ', $value);
        $parts = preg_split('/\s+/', trim((string) $value)) ?: array();
        $stopwords = array(
            'etui', 'silikonowe', 'silikon', 'clear', 'na', 'do', 'wzory', 'wzor', 'szklo', 'szkło',
            'case', 'pokrowiec', 'plecki', 'plus', 'ochronne', 'ochrona', 'glass',
            'fc', 'barcelona', 'barca', 'yamal', 'pedri', 'lewandowski'
        );
        $tokens = array();

        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '' || in_array($part, $stopwords, true)) {
                continue;
            }
            if (mb_strlen($part, 'UTF-8') < 2) {
                continue;
            }
            if (!in_array($part, $tokens, true)) {
                $tokens[] = $part;
            }
        }

        return array_slice($tokens, 0, 8);
    }

    private function warehousePriceFromOffer(array $offer): ?string
    {
        return isset($offer['warehouse_price_gross']) && $offer['warehouse_price_gross'] !== null
            ? $this->normalizeDecimal($offer['warehouse_price_gross'])
            : null;
    }

    private function syncLinkedWarehouseProductFromOffer(int $accountId, string $offerId): bool
    {
        $offer = $this->storage->findOfferByAccountAndOfferId($accountId, $offerId);
        if (!$offer || empty($offer['warehouse_product_id'])) {
            return false;
        }

        // Allegro moze byc powiazane z produktem, ale nie nadpisuje danych magazynowych.
        // Lista produktow w magazynie pozostaje zrodlem prawdy.
        return false;
    }

    private function syncWarehouseProductsFromCycle(int $accountId, string $cycle): int
    {
        $count = 0;
        foreach ($this->storage->offersForWarehouseSyncCycle($accountId, $cycle) as $offer) {
            if ($this->syncLinkedWarehouseProductFromOffer($accountId, (string) ($offer['offer_id'] ?? ''))) {
                $count++;
            }
        }

        return $count;
    }

    private function extractProductParametersFromOfferDetails(array $details): array
    {
        $items = array();
        if (!isset($details['productSet']) || !is_array($details['productSet'])) {
            return $items;
        }

        foreach ($details['productSet'] as $productSetItem) {
            if (!isset($productSetItem['product']['parameters']) || !is_array($productSetItem['product']['parameters'])) {
                continue;
            }

            foreach ($productSetItem['product']['parameters'] as $parameter) {
                if (is_array($parameter)) {
                    $items[] = $parameter;
                }
            }
        }

        return $items;
    }

    private function buildCategoryParameterPatchPayload(
        array $definitions,
        array $submittedValues,
        array $existingParameters,
        bool $describesProduct,
        bool $replaceAll
    ): array {
        $definitionMap = array();
        foreach ($definitions as $definition) {
            $parameterId = (string) ($definition['id'] ?? '');
            if ($parameterId === '' || !array_key_exists('describes_product', $definition)) {
                continue;
            }

            if ((bool) $definition['describes_product'] !== $describesProduct) {
                continue;
            }

            $definitionMap[$parameterId] = $definition;
        }

        if ($definitionMap === array()) {
            return array();
        }

        $merged = array();
        if (!$replaceAll) {
            foreach ($existingParameters as $parameter) {
                if (!is_array($parameter)) {
                    continue;
                }

                $parameterId = trim((string) ($parameter['id'] ?? ''));
                if ($parameterId === '' || !isset($definitionMap[$parameterId])) {
                    continue;
                }

                $merged[$parameterId] = $parameter;
            }
        }

        foreach ($submittedValues as $parameterId => $value) {
            $parameterId = trim((string) $parameterId);
            if ($parameterId === '' || !isset($definitionMap[$parameterId])) {
                continue;
            }

            $normalized = $this->buildSingleParameterPatchEntry($definitionMap[$parameterId], $value);
            if ($normalized !== null) {
                $merged[$parameterId] = $normalized;
            }
        }

        return array_values($merged);
    }

    private function buildSingleParameterPatchEntry(array $definition, $value): ?array
    {
        $parameterId = (string) ($definition['id'] ?? '');
        if ($parameterId === '') {
            return null;
        }

        $multiple = !empty($definition['multiple']) || (string) ($definition['type'] ?? '') === 'multidictionary';
        $dictionary = isset($definition['dictionary']) && is_array($definition['dictionary']) ? $definition['dictionary'] : array();

        if ($dictionary !== array()) {
            $values = is_array($value) ? $value : array($value);
            $valuesIds = array_values(array_filter(array_map(static function ($item): string {
                return trim((string) $item);
            }, $values), static function (string $item): bool {
                return $item !== '';
            }));

            if ($valuesIds === array()) {
                return null;
            }

            if (!$multiple) {
                $valuesIds = array($valuesIds[0]);
            }

            return array(
                'id' => $parameterId,
                'valuesIds' => $valuesIds,
            );
        }

        if ($multiple) {
            $items = is_array($value) ? $value : preg_split('/\r\n|\r|\n/', trim((string) $value));
            $cleanValues = array_values(array_filter(array_map(static function ($item): string {
                return trim((string) $item);
            }, is_array($items) ? $items : array()), static function (string $item): bool {
                return $item !== '';
            }));

            if ($cleanValues === array()) {
                return null;
            }

            return array(
                'id' => $parameterId,
                'values' => $cleanValues,
            );
        }

        $singleValue = trim((string) $value);
        if ($singleValue === '') {
            return null;
        }

        return array(
            'id' => $parameterId,
            'values' => array($singleValue),
        );
    }

    private function expandWarehouseSyncProductIds(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds), static function (int $id): bool {
            return $id > 0;
        })));

        if ($productIds === array()) {
            return array();
        }

        $sharedStock = new SharedStockGroupRepository(Database::instance());
        $expanded = array_fill_keys($productIds, true);

        foreach ($productIds as $productId) {
            foreach ($sharedStock->membersForProduct($productId) as $member) {
                $memberId = isset($member['id']) ? (int) $member['id'] : 0;
                if ($memberId > 0) {
                    $expanded[$memberId] = true;
                }
            }
        }

        $audit = ProductChangeAuditService::instance(Database::instance());
        $derivedExpanded = $audit->expandWithDerivedDependents(array_map('intval', array_keys($expanded)));

        return array_values(array_unique(array_filter(array_map('intval', $derivedExpanded), static function (int $id): bool {
            return $id > 0;
        })));
    }

    private function warehouseSkuByProductId(int $productId): string
    {
        if ($productId <= 0) {
            return '';
        }

        $row = Database::instance()->fetch(
            'SELECT sku FROM products WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            array('id' => $productId)
        );

        return $row && !empty($row['sku']) ? trim((string) $row['sku']) : '';
    }

    private function disableStoredWarehouseLinks(): void
    {
        $cacheKey = 'allegro:warehouse-links-disabled:v1';
        $cached = $this->storage->getCache($cacheKey);
        if (is_array($cached) && !empty($cached['done'])) {
            return;
        }

        $this->storage->clearStoredOfferLinks();
        $this->storage->putCache($cacheKey, array('done' => 1), 31536000);
    }

    private function patchImagesFromOffer(array $offer): array
    {
        $images = isset($offer['images']) && is_array($offer['images']) ? $offer['images'] : array();
        $result = array();

        foreach ($images as $image) {
            $url = trim((string) ($image['url'] ?? ''));
            if ($url !== '') {
                $result[] = array('url' => $url);
            }
        }

        return $result;
    }

    private function detectBestAllegroProductId(array $account, array $offer): ?string
    {
        $existing = trim((string) ($offer['allegro_product_id'] ?? ''));
        if ($existing !== '') {
            return $existing;
        }

        $codes = $this->detectProductCodes($offer);
        foreach ($codes as $code) {
            $payload = $this->requestApiWithAccount($account, 'GET', '/sale/products', array(
                'phrase' => $code,
                'limit' => 1,
            ));
            if (!empty($payload['products'][0]['id'])) {
                return (string) $payload['products'][0]['id'];
            }
        }

        $phrase = trim((string) ($offer['name'] ?? ''));
        if ($phrase === '') {
            return null;
        }

        $payload = $this->requestApiWithAccount($account, 'GET', '/sale/products', array(
            'phrase' => $phrase,
            'limit' => 1,
        ));

        return !empty($payload['products'][0]['id']) ? (string) $payload['products'][0]['id'] : null;
    }

    private function detectProductCodes(array $offer): array
    {
        $codes = array();
        $parameters = isset($offer['parameters']) && is_array($offer['parameters']) ? $offer['parameters'] : array();

        foreach ($parameters as $parameter) {
            $name = mb_strtolower((string) ($parameter['name'] ?? ''), 'UTF-8');
            if (mb_strpos($name, 'ean', 0, 'UTF-8') === false && mb_strpos($name, 'gtin', 0, 'UTF-8') === false) {
                continue;
            }

            foreach ((array) ($parameter['values'] ?? array()) as $value) {
                $clean = preg_replace('/\D+/', '', (string) $value);
                if ($clean !== '' && strlen($clean) >= 8 && strlen($clean) <= 14 && !in_array($clean, $codes, true)) {
                    $codes[] = $clean;
                }
            }
        }

        return $codes;
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        if ($base === '') {
            $base = 'allegro-account';
        }

        $slug = $base;
        $suffix = 2;

        while (true) {
            $existing = $this->storage->findAccountBySlug($slug);
            if (!$existing || ($ignoreId !== null && (int) $existing['id'] === $ignoreId)) {
                return $slug;
            }

            $slug = $base . '-' . $suffix;
            $suffix++;
        }
    }

    private function randomToken(int $bytes): string
    {
        try {
            return bin2hex(random_bytes(max(8, $bytes)));
        } catch (\Throwable $exception) {
            return sha1(uniqid('', true) . mt_rand());
        }
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private function normalizeDecimal($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(',', '.', (string) $value);
        return is_numeric($normalized) ? number_format((float) $normalized, 2, '.', '') : null;
    }

    private function normalizeInteger($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function normalizeDateTime($value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime($value);
        return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    private function configValue(string $key, $default = null)
    {
        return array_key_exists($key, $this->config) ? $this->config[$key] : $default;
    }

    private function cacheTtl(): int
    {
        return (int) $this->configValue('cache_ttl', 86400);
    }

    private function enrichPaths(array $items, bool $forceRefresh): array
    {
        $result = array();
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['id'])) {
                continue;
            }

            $path = $this->pathByCategoryId((string) $item['id'], $forceRefresh);
            $item['path'] = $path !== '' ? $path : (string) ($item['name'] ?? '');
            $result[] = $item;
        }

        return $result;
    }

    private function pathByCategoryId(string $categoryId, bool $forceRefresh): string
    {
        $cacheKey = 'allegro_category_lineage_v2_' . $categoryId;
        if (!$forceRefresh) {
            $cached = $this->storage->getCache($cacheKey);
            if (is_array($cached) && isset($cached['path'])) {
                return (string) $cached['path'];
            }
        }

        $parts = array();
        $visited = array();
        $currentId = $categoryId;
        $maxDepth = 20;

        while ($currentId !== '' && $maxDepth > 0) {
            if (isset($visited[$currentId])) {
                break;
            }

            $visited[$currentId] = true;
            $details = $this->categoryDetails($currentId, $forceRefresh);
            if (!$details || empty($details['name'])) {
                break;
            }

            array_unshift($parts, (string) $details['name']);
            $parentId = (string) ($details['parent_id'] ?? '');
            if ($parentId === '' || $parentId === $currentId) {
                break;
            }

            $currentId = $parentId;
            $maxDepth--;
        }

        $path = implode(' > ', $parts);
        if ($path !== '') {
            $this->storage->putCache($cacheKey, array('path' => $path), max(3600, min(86400, $this->cacheTtl())));
        }

        return $path;
    }

    private function categoryDetails(string $categoryId, bool $forceRefresh)
    {
        $cacheKey = 'allegro_category_details_v2_' . $categoryId;
        if (!$forceRefresh) {
            $cached = $this->storage->getCache($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        try {
            $payload = $this->requestApi('GET', '/sale/categories/' . rawurlencode($categoryId));
        } catch (RuntimeException $exception) {
            return null;
        }

        $details = array(
            'id' => (string) ($payload['id'] ?? ''),
            'name' => (string) ($payload['name'] ?? ''),
            'parent_id' => (string) ($payload['parent']['id'] ?? ''),
        );

        if ($details['id'] === '' || $details['name'] === '') {
            return null;
        }

        $this->storage->putCache($cacheKey, $details, max(3600, min(86400, $this->cacheTtl())));
        return $details;
    }

    private function searchByTree(string $search, bool $forceRefresh): array
    {
        $result = array();
        $visited = array();
        $queue = $this->childrenByParent(null, '', $forceRefresh);
        $maxNodes = 4000;
        $scanned = 0;

        while ($queue !== array() && $scanned < $maxNodes) {
            $node = array_shift($queue);
            if (!is_array($node) || empty($node['id'])) {
                continue;
            }

            $id = (string) $node['id'];
            if (isset($visited[$id])) {
                continue;
            }

            $visited[$id] = true;
            $scanned++;

            if ($this->matchesSearch($node, $search)) {
                $result[$id] = $node;
                if (count($result) >= 120) {
                    break;
                }
            }

            if (empty($node['leaf'])) {
                foreach ($this->childrenByParent($id, (string) ($node['path'] ?? ''), $forceRefresh) as $child) {
                    $queue[] = $child;
                }
            }
        }

        return array_values($result);
    }

    private function childrenByParent($parentId, string $parentPath, bool $forceRefresh): array
    {
        $cacheKey = 'allegro_children_v2_' . (($parentId === null || $parentId === '') ? 'root' : (string) $parentId);
        if (!$forceRefresh) {
            $cached = $this->storage->getCache($cacheKey);
            if (is_array($cached)) {
                return $this->applyPathToChildren($cached, $parentPath);
            }
        }

        $query = array();
        if ($parentId !== null && (string) $parentId !== '') {
            $query['parent.id'] = (string) $parentId;
        }

        $payload = $this->requestApi('GET', '/sale/categories', $query);
        $rows = isset($payload['categories']) && is_array($payload['categories']) ? $payload['categories'] : array();
        $children = array();

        foreach ($rows as $item) {
            $normalized = $this->normalizeCategorySearchItem($item);
            if ($normalized !== null) {
                $normalized['path'] = '';
                $children[] = $normalized;
            }
        }

        $this->storage->putCache($cacheKey, $children, max(3600, min(86400, $this->cacheTtl())));
        return $this->applyPathToChildren($children, $parentPath);
    }

    private function applyPathToChildren(array $children, string $parentPath): array
    {
        $result = array();
        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }

            $name = (string) ($child['name'] ?? '');
            $child['path'] = $parentPath !== '' ? ($parentPath . ' > ' . $name) : $name;
            $result[] = $child;
        }

        return $result;
    }

    private function extractMatchingCollection(array $payload): array
    {
        if (isset($payload['matching_categories']) && is_array($payload['matching_categories'])) {
            return $payload['matching_categories'];
        }

        if (isset($payload['matchingCategories']) && is_array($payload['matchingCategories'])) {
            return $payload['matchingCategories'];
        }

        return isset($payload['categories']) && is_array($payload['categories']) ? $payload['categories'] : array();
    }

    private function matchesSearch(array $item, string $search): bool
    {
        $needle = $this->normalizeSearchText($search);
        if ($needle === '') {
            return false;
        }

        $name = $this->normalizeSearchText((string) ($item['name'] ?? ''));
        $path = $this->normalizeSearchText((string) ($item['path'] ?? ''));

        return mb_stripos($name, $needle, 0, 'UTF-8') !== false
            || mb_stripos($path, $needle, 0, 'UTF-8') !== false
            || $this->matchesSearchTokens($name, $needle)
            || $this->matchesSearchTokens($path, $needle);
    }

    private function matchesSearchTokens(string $haystack, string $needle): bool
    {
        $searchTokens = $this->tokenizeSearchText($needle);
        if ($searchTokens === array()) {
            return false;
        }

        $haystackTokens = $this->tokenizeSearchText($haystack);
        if ($haystackTokens === array()) {
            return false;
        }

        foreach ($searchTokens as $token) {
            if (mb_strlen($token, 'UTF-8') < 2) {
                continue;
            }

            $matched = false;
            foreach ($haystackTokens as $haystackToken) {
                if (mb_stripos($haystackToken, $token, 0, 'UTF-8') !== false || mb_stripos($token, $haystackToken, 0, 'UTF-8') !== false) {
                    $matched = true;
                    break;
                }

                if (mb_strlen($token, 'UTF-8') >= 4 && mb_strpos($haystackToken, $token, 0, 'UTF-8') === 0) {
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    private function normalizeSearchText(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($transliterated !== false) {
            $value = $transliterated;
        }

        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value);
        return trim((string) $value);
    }

    private function tokenizeSearchText(string $value): array
    {
        $value = $this->normalizeSearchText($value);
        if ($value === '') {
            return array();
        }

        return array_values(array_filter(preg_split('/\s+/u', $value)));
    }

    private function normalizeCategorySearchItem($item)
    {
        if (!is_array($item)) {
            return null;
        }

        $source = isset($item['category']) && is_array($item['category']) ? $item['category'] : $item;
        $id = (string) ($source['id'] ?? '');
        $name = (string) ($source['name'] ?? '');
        if ($id === '' || $name === '') {
            return null;
        }

        $pathParts = array();
        if (isset($item['path']) && is_array($item['path'])) {
            foreach ($item['path'] as $node) {
                if (is_array($node) && isset($node['name'])) {
                    $pathParts[] = (string) $node['name'];
                }
            }
        }

        return array(
            'id' => $id,
            'name' => $name,
            'leaf' => !empty($source['leaf']),
            'path' => implode(' > ', $pathParts),
        );
    }
}
