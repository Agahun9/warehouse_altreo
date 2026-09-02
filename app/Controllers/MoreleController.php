<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\MoreleService;
use RuntimeException;
use Throwable;

class MoreleController extends Controller
{
    /** @var MoreleService */
    private $morele;

    public function __construct()
    {
        $this->morele = new MoreleService();
    }

    public function index(): void
    {
        $currentUser = $this->requireModule('morele');
        $flashSuccess = $this->getFlash('success');
        $flashError = $this->getFlash('error');
        $this->releaseSessionLock();

        $filters = $this->offerFilters();
        $page = max(1, (int) $this->input('page', 1));
        $allowedPerPage = array(50, 100, 200, 5000, 10000);
        $perPageInput = (int) $this->input('per_page', 50);
        $perPage = in_array($perPageInput, $allowedPerPage, true) ? $perPageInput : 50;
        $sortBy = trim((string) $this->input('sort_by', 'synced'));
        $sortDir = strtolower(trim((string) $this->input('sort_dir', 'desc')));
        $sortDir = $sortDir === 'asc' ? 'asc' : 'desc';

        $total = $this->morele->countOffers($filters);
        $totalPages = max(1, (int) ceil($total / max(1, $perPage)));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offers = $this->morele->offersPage($filters, $page, $perPage, $sortBy, $sortDir);
        $offers = $this->withListThumbnails($offers);

        $this->render('morele/index', array(
            'pageTitle' => 'Morele',
            'contentTitle' => 'Integracja Morele',
            'pageDescription' => 'Import ofert, kolejka zmian cen/stanow i masowe zakanczanie ofert Morele.',
            'breadcrumbCurrent' => 'Morele',
            'currentUser' => $currentUser,
            'flashSuccess' => $flashSuccess,
            'flashError' => $flashError,
            'accounts' => $this->morele->listAccounts(),
            'offers' => $offers,
            'filters' => $filters,
            'stats' => $this->morele->offerStats(),
            'queueStats' => $this->morele->queueCounts(),
            'page' => $page,
            'prevPage' => max(1, $page - 1),
            'nextPage' => min($totalPages, $page + 1),
            'perPage' => $perPage,
            'totalOffers' => $total,
            'totalPages' => $totalPages,
            'currentListUrl' => $this->buildOfferIndexUrl($filters, $sortBy, $sortDir, $page, $perPage),
        ));
    }

    public function sync(): void
    {
        if (!$this->wantsJson()) {
            $this->requireModuleWrite('morele');
        }
        $this->releaseSessionLock();

        try {
            $result = $this->morele->syncOffers(array(
                'max_pages' => (int) $this->input('max_pages', 0),
                'page_limit' => (int) $this->input('page_limit', 100),
                'debug' => $this->input('debug', '0') === '1',
            ));

            if ($this->wantsJson()) {
                $this->jsonResponse($result);
                return;
            }

            $this->setFlash('success', 'Synchronizacja Morele: zapisano ' . (int) ($result['synced_offers'] ?? 0) . ' ofert.');
        } catch (Throwable $exception) {
            if ($this->wantsJson()) {
                $this->jsonResponse(array('error' => $exception->getMessage()), 500);
                return;
            }
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=morele&action=index');
    }

    public function maintenance(): void
    {
        if (!$this->wantsJson()) {
            $this->requireModuleWrite('morele');
        }
        $this->releaseSessionLock();

        try {
            $result = array();
            if ($this->input('sync', '0') === '1') {
                $result['sync'] = $this->morele->syncOffers(array(
                    'max_pages' => (int) $this->input('max_pages', 0),
                    'page_limit' => (int) $this->input('page_limit', 100),
                    'debug' => $this->input('debug', '0') === '1',
                ));
            }

            if ($this->wantsJson()) {
                $this->jsonResponse($result);
                return;
            }

            $this->setFlash('success', 'Maintenance Morele zakonczone.');
        } catch (Throwable $exception) {
            if ($this->wantsJson()) {
                $this->jsonResponse(array('error' => $exception->getMessage()), 500);
                return;
            }
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=morele&action=index');
    }

    public function queue(): void
    {
        $this->requireModuleWrite('morele');
        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=morele&action=index');
        }
        $this->releaseSessionLock();

        try {
            $selectionScope = trim((string) $this->input('selection_scope', 'filtered'));
            $selectedOfferIds = $this->input('selected_offer_ids', array());
            $selectedIds = array();
            if ($selectionScope === 'selected' && is_array($selectedOfferIds)) {
                $selectedIds = array_values(array_filter(array_map('intval', $selectedOfferIds)));
                if ($selectedIds === array()) {
                    throw new RuntimeException('Zaznacz przynajmniej jedna oferte Morele albo wybierz biezacy filtr.');
                }
            }

            $result = $this->morele->enqueueOfferChanges(
                $this->offerFilters(),
                trim((string) $this->input('operation', '')),
                array(
                    'value' => $this->input('value', ''),
                    'selection_limit' => (int) $this->input('selection_limit', 1000),
                ),
                $selectedIds
            );

            if ((string) ($result['operation'] ?? '') === 'clear_queue') {
                $this->setFlash('success', 'Wyczyszczono kolejke Morele.');
            } else {
                $this->setFlash('success', 'Dodano do kolejki Morele: ' . (int) ($result['queued'] ?? 0) . ' ofert.');
            }
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $returnUrl = trim((string) $this->input('return_url', ''));
        if ($returnUrl !== '' && strpos($returnUrl, './index.php?') === 0) {
            $this->redirect($returnUrl);
        }
        $this->redirect('./index.php?controller=morele&action=index');
    }

    public function processqueue(): void
    {
        if (!$this->wantsJson()) {
            $this->requireModuleWrite('morele');
        }
        $this->releaseSessionLock();

        try {
            $result = $this->morele->processQueue(array('limit' => (int) $this->input('limit', 20)));
            if ($this->wantsJson()) {
                $this->jsonResponse($result);
                return;
            }

            $this->setFlash('success', 'Kolejka Morele przetworzona. OK: ' . (int) ($result['done'] ?? 0));
        } catch (Throwable $exception) {
            if ($this->wantsJson()) {
                $this->jsonResponse(array('error' => $exception->getMessage()), 500);
                return;
            }
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect('./index.php?controller=morele&action=index');
    }

    public function clearqueue(): void
    {
        $this->requireModuleWrite('morele');
        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=morele&action=index');
        }

        $mode = trim((string) $this->input('mode', 'statuses'));
        $result = $mode === 'all' ? $this->morele->clearWholeQueue() : $this->morele->clearQueueStatuses(true);
        $this->setFlash('success', 'Usunieto z kolejki Morele: ' . (int) ($result['removed'] ?? 0) . ' wpisow.');
        $this->redirect('./index.php?controller=morele&action=index');
    }

    public function offer(): void
    {
        $this->requireModule('morele');
        $offer = $this->morele->offerDetails((int) $this->input('id', 0));
        if (!$offer) {
            $this->setFlash('error', 'Nie znaleziono oferty Morele.');
            $this->redirect('./index.php?controller=morele&action=index');
        }

        $decoded = json_decode((string) ($offer['payload_json'] ?? ''), true);
        $decoded = is_array($decoded) ? $decoded : array();
        $this->render('morele/offer', array(
            'pageTitle' => 'Oferta Morele',
            'contentTitle' => (string) ($offer['product_name'] ?? 'Oferta Morele'),
            'pageDescription' => 'Podglad lokalnego wpisu oferty Morele.',
            'breadcrumbCurrent' => 'Oferta Morele',
            'offer' => $offer,
            'payloadJsonPretty' => json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ));
    }

    private function offerFilters(): array
    {
        return array(
            'q' => trim((string) $this->input('q', '')),
            'sku' => trim((string) $this->input('sku', '')),
            'status' => trim((string) $this->input('status', '')),
        );
    }

    private function buildOfferIndexUrl(array $filters, string $sortBy, string $sortDir, int $page, int $perPage): string
    {
        return './index.php?' . http_build_query(array_merge(
            array('controller' => 'morele', 'action' => 'index', 'page' => $page, 'per_page' => $perPage, 'sort_by' => $sortBy, 'sort_dir' => $sortDir),
            $filters
        ));
    }

    private function withListThumbnails(array $offers): array
    {
        foreach ($offers as $index => $offer) {
            $payload = json_decode((string) ($offer['payload_json'] ?? ''), true);
            $payload = is_array($payload) ? $payload : array();
            $offers[$index]['thumbnail_url'] = $this->firstImageUrl($payload);
        }

        return $offers;
    }

    private function firstImageUrl(array $payload): string
    {
        foreach (array(
            'main_image_src',
            'mainImageSrc',
            'image',
            'image_url',
            'imageUrl',
            'thumbnail',
            'thumbnail_url',
            'thumbnailUrl',
            'photo',
            'photo_url',
            'src',
            'url',
            'href',
        ) as $key) {
            if (!empty($payload[$key]) && is_scalar($payload[$key])) {
                $url = $this->normalizeImageUrl((string) $payload[$key]);
                if ($url !== '') {
                    return $url;
                }
            }
        }

        foreach (array('images', 'photos', 'pictures', 'media', 'gallery', 'image') as $key) {
            if (!isset($payload[$key]) || !is_array($payload[$key])) {
                continue;
            }

            $url = $this->firstImageUrlFromList($payload[$key]);
            if ($url !== '') {
                return $url;
            }
        }

        foreach ($payload as $key => $value) {
            if (is_scalar($value) && $this->looksLikeImageKey((string) $key)) {
                $url = $this->normalizeImageUrl((string) $value);
                if ($url !== '') {
                    return $url;
                }
            }
        }

        foreach ($payload as $value) {
            if (is_array($value)) {
                $url = $this->firstImageUrl($value);
                if ($url !== '') {
                    return $url;
                }
            }
        }

        return '';
    }

    private function looksLikeImageKey(string $key): bool
    {
        $key = strtolower($key);
        return strpos($key, 'image') !== false
            || strpos($key, 'img') !== false
            || strpos($key, 'photo') !== false
            || strpos($key, 'picture') !== false
            || strpos($key, 'thumbnail') !== false;
    }

    private function firstImageUrlFromList(array $items): string
    {
        foreach ($items as $item) {
            if (is_scalar($item)) {
                $url = $this->normalizeImageUrl((string) $item);
                if ($url !== '') {
                    return $url;
                }
                continue;
            }

            if (is_array($item)) {
                $url = $this->firstImageUrl($item);
                if ($url !== '') {
                    return $url;
                }
            }
        }

        return '';
    }

    private function normalizeImageUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }
        if (strpos($url, '/') === 0) {
            $url = 'https://www.morele.net' . $url;
        }
        if (!preg_match('#^https?://#i', $url)) {
            return '';
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('/\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i', $path)) {
            return $url;
        }

        return preg_match('#/(image|images|img|photo|photos|thumbnail|media)/#i', $url) ? $url : '';
    }

    private function wantsJson(): bool
    {
        return strtolower(trim((string) $this->input('format', ''))) === 'json';
    }

    private function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
    }
}
