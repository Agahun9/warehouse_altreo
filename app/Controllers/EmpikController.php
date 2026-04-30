<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\EmpikService;
use Throwable;

class EmpikController extends Controller
{
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
        $perPageInput = (int) $this->input('per_page', 50);
        $perPage = in_array($perPageInput, array(25, 50, 100, 250), true) ? $perPageInput : 50;
        $total = $this->empik->countOffers($filters);
        $offers = $this->empik->offersPage($filters, $page, $perPage);
        $totalPages = max(1, (int) ceil($total / max(1, $perPage)));

        if ($page > $totalPages) {
            $page = $totalPages;
            $offers = $this->empik->offersPage($filters, $page, $perPage);
        }

        $this->render('empik/index', array(
            'pageTitle' => 'Empik',
            'contentTitle' => 'Integracja Empik Marketplace',
            'pageDescription' => 'Mirakl Seller API: konfiguracja kont, synchronizacja ofert i wyszukiwanie kategorii Empik.',
            'breadcrumbCurrent' => 'Empik',
            'currentUser' => $currentUser,
            'flashSuccess' => $flashSuccess,
            'flashError' => $flashError,
            'accounts' => $this->empik->listAccounts(),
            'offers' => $offers,
            'filters' => $filters,
            'page' => $page,
            'perPage' => $perPage,
            'totalOffers' => $total,
            'totalPages' => $totalPages,
            'prevPage' => max(1, $page - 1),
            'nextPage' => min($totalPages, $page + 1),
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
        $this->requireModuleWrite('empik');

        try {
            $result = $this->empik->syncAccount(trim((string) $this->input('account', '')));
            $this->setFlash(
                'success',
                'Synchronizacja Empik zakonczona dla konta "' . (string) $result['account']['name'] . '". Pobrano ' . (int) $result['synced_offers'] . ' ofert.'
            );
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
        );
    }

    private function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
    }
}
