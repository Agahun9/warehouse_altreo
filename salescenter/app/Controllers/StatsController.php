<?php

declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Models\OrderStatsRepository;

/** Statystyki sprzedaży: strona z wykresami i jej źródło danych JSON (tylko odczyt). */
final class StatsController extends Controller
{
    public function index(): void
    {
        $user = $this->requireModule('orders');
        $options = (new OrderStatsRepository($this->db()))->options();
        header('Cache-Control: no-store, private');
        $this->render('stats/index', [
            'pageTitle' => 'Statystyki sprzedaży',
            'statsOptionsJson' => json_encode($options, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR),
            'statsTenantId' => (int) $user['tenant']['id'],
        ]);
    }

    public function data(): void
    {
        $user = $this->currentUser();
        if (!$user) {
            $this->jsonResponse(['error' => 'Zaloguj się ponownie do aplikacji.', 'code' => 'AUTH_REQUIRED', 'login_url' => 'index.php?controller=auth&action=login'], 401);
            return;
        }
        if ($this->moduleAccessLevel($user, 'orders') === 'none') {
            $this->jsonResponse(['error' => 'Brak dostępu do statystyk centrum zamówień.', 'code' => 'ACCESS_DENIED'], 403);
            return;
        }
        // Liczenie dużego zakresu może potrwać – nie blokujemy w tym czasie innych kart tej sesji.
        $this->releaseSessionLock();
        @set_time_limit(60);
        try {
            $stats = new OrderStatsRepository($this->db());
            $this->jsonResponse($stats->report($stats->filters($_GET)));
        } catch (\InvalidArgumentException $error) {
            $this->jsonResponse(['error' => $error->getMessage(), 'code' => 'RANGE_TOO_LARGE'], 422);
        } catch (\Throwable $error) {
            if (function_exists('app_log')) {
                app_log('Statystyki: ' . get_class($error) . ': ' . $error->getMessage() . ' w ' . $error->getFile() . ':' . $error->getLine(), 'ERROR');
            }
            $this->jsonResponse(['error' => 'Nie udało się policzyć statystyk. Spróbuj ponownie za chwilę.', 'code' => 'STATS_FAILED'], 500);
        }
    }

    private function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }
}
