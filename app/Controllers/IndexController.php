<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\CategoryRepository;
use App\Models\ProductChangeLogRepository;
use App\Models\ProductRepository;
use App\Models\SellasistOrderSyncRepository;
use App\Models\UserRepository;
use App\Services\AllegroService;
use Throwable;

class IndexController extends Controller
{
    public function index(): void
    {
        $currentUser = $this->requireAuth();
        $flashSuccess = $this->getFlash('success');
        $flashError = $this->getFlash('error');
        $this->releaseSessionLock();

        $productCount = null;
        $lowStockCount = null;
        $outOfStockCount = null;
        $categoryCount = null;
        $userCount = null;
        $activeUserCount = null;
        $blockedUserCount = null;
        $recentProductChanges = array();
        $recentUsers = array();
        $allegroQueueStats = array(
            'pending' => 0,
            'processing' => 0,
            'done' => 0,
            'error' => 0,
            'retry' => 0,
        );
        $sellasistTodayStats = array(
            'orders_count' => 0,
            'total_value' => 0.0,
            'currency' => 'PLN',
        );
        $sellasistDailySeries = array();
        $sellasistChart = array(
            'orders_points' => '',
            'value_points' => '',
            'y_axis_orders' => array(0, 1),
            'y_axis_value' => array(0.0, 1.0),
        );

        try {
            $products = new ProductRepository($this->db());
            $products->ensureSchema();
            $productCount = $products->countAll();
            $lowStockCount = $products->countLowStock(5);
            $outOfStockCount = $products->countOutOfStock();
        } catch (Throwable $exception) {
            $productCount = null;
            $lowStockCount = null;
            $outOfStockCount = null;
        }

        try {
            $categories = new CategoryRepository($this->db());
            $categories->ensureSchema();
            $categoryCount = $categories->countAll();
        } catch (Throwable $exception) {
            $categoryCount = null;
        }

        try {
            $productChanges = new ProductChangeLogRepository($this->db());
            $productChanges->ensureSchema();
            $recentProductChanges = $productChanges->latest(12);
        } catch (Throwable $exception) {
            $recentProductChanges = array();
        }

        if ((string) $currentUser['role'] === 'admin') {
            try {
                $users = new UserRepository($this->db());
                $users->ensureSchema();
                $userCount = $users->countAll();
                $activeUserCount = $users->countActive();
                $blockedUserCount = $users->countBlocked();
                $recentUsers = $users->latest(6);
            } catch (Throwable $exception) {
                $userCount = null;
                $activeUserCount = null;
                $blockedUserCount = null;
                $recentUsers = array();
            }
        }

        try {
            $allegro = new AllegroService();
            $allegroQueueStats = $allegro->queueCounts();
        } catch (Throwable $exception) {
            $allegroQueueStats = array(
                'pending' => 0,
                'processing' => 0,
                'done' => 0,
                'error' => 0,
                'retry' => 0,
            );
        }

        try {
            $sellasistSync = new SellasistOrderSyncRepository($this->db());
            $sellasistSync->ensureSchema();
            $sellasistTodayStats = $sellasistSync->todaySummary('subtract_stock');
            $sellasistDailySeries = $sellasistSync->dailySeries('subtract_stock', 7);
            $sellasistChart = $this->buildSellasistChart($sellasistDailySeries);
        } catch (Throwable $exception) {
            $sellasistTodayStats = array(
                'orders_count' => 0,
                'total_value' => 0.0,
                'currency' => 'PLN',
            );
            $sellasistDailySeries = array();
        }

        $stats = array(
            array('value' => $productCount !== null ? (string) $productCount : '-', 'label' => 'Produkty', 'theme' => 'primary', 'icon' => 'bi-box-seam'),
            array('value' => $lowStockCount !== null ? (string) $lowStockCount : '-', 'label' => 'Niski stan (<= 5)', 'theme' => 'warning', 'icon' => 'bi-exclamation-triangle'),
            array('value' => $outOfStockCount !== null ? (string) $outOfStockCount : '-', 'label' => 'Brak stanu', 'theme' => 'danger', 'icon' => 'bi-x-octagon'),
            array('value' => $categoryCount !== null ? (string) $categoryCount : '-', 'label' => 'Kategorie', 'theme' => 'info', 'icon' => 'bi-tags'),
        );

        if ((string) $currentUser['role'] === 'admin') {
            $stats[] = array('value' => $userCount !== null ? (string) $userCount : '-', 'label' => 'Uzytkownicy', 'theme' => 'success', 'icon' => 'bi-people');
            $stats[] = array('value' => $activeUserCount !== null ? (string) $activeUserCount : '-', 'label' => 'Konta aktywne', 'theme' => 'success', 'icon' => 'bi-person-check');
            $stats[] = array('value' => $blockedUserCount !== null ? (string) $blockedUserCount : '-', 'label' => 'Konta zablokowane', 'theme' => 'secondary', 'icon' => 'bi-person-x');
        }

        $this->render('index', array(
            'pageTitle' => 'Dashboard',
            'contentTitle' => 'Panel glowny',
            'pageDescription' => 'Szybki podglad najwazniejszych danych magazynu i kont uzytkownikow.',
            'breadcrumbCurrent' => 'Dashboard',
            'currentUser' => $currentUser,
            'flashSuccess' => $flashSuccess,
            'flashError' => $flashError,
            'stats' => $stats,
            'recentProductChanges' => $recentProductChanges,
            'recentUsers' => $recentUsers,
            'allegroQueueStats' => $allegroQueueStats,
            'sellasistTodayStats' => $sellasistTodayStats,
            'sellasistDailySeries' => $sellasistDailySeries,
            'sellasistChart' => $sellasistChart,
            'activities' => array(
                'Sprawdz stany niskie i brakujace, aby unikac opoznien wysylek.',
                'Monitoruj dzisiejsze odjecia Sellasist i kolejke Allegro.',
                'Admin moze zarzadzac kontami, dostepami i modulami systemu.',
            ),
        ));
    }

    private function buildSellasistChart(array $series): array
    {
        $width = 320.0;
        $height = 132.0;
        $paddingLeft = 8.0;
        $paddingRight = 8.0;
        $paddingTop = 10.0;
        $paddingBottom = 18.0;
        $plotWidth = $width - $paddingLeft - $paddingRight;
        $plotHeight = $height - $paddingTop - $paddingBottom;

        if ($series === array()) {
            $series = array(
                array('orders_count' => 0, 'total_value' => 0.0, 'label' => date('d.m')),
                array('orders_count' => 0, 'total_value' => 0.0, 'label' => date('d.m')),
            );
        }

        $orderValues = array();
        $valueValues = array();
        foreach ($series as $point) {
            $orderValues[] = isset($point['orders_count']) ? (int) $point['orders_count'] : 0;
            $valueValues[] = isset($point['total_value']) ? (float) $point['total_value'] : 0.0;
        }

        $maxOrders = max($orderValues);
        $maxValue = max($valueValues);
        $ordersScaleMax = max(1, $maxOrders);
        $valueScaleMax = max(1.0, $maxValue);

        return array(
            'orders_points' => $this->chartPolylinePoints($orderValues, $paddingLeft, $paddingTop, $plotWidth, $plotHeight, (float) $ordersScaleMax),
            'value_points' => $this->chartPolylinePoints($valueValues, $paddingLeft, $paddingTop, $plotWidth, $plotHeight, $valueScaleMax),
            'y_axis_orders' => array(0, $ordersScaleMax),
            'y_axis_value' => array(0.0, $valueScaleMax),
        );
    }

    private function chartPolylinePoints(array $values, float $left, float $top, float $width, float $height, float $scaleMax): string
    {
        $count = count($values);
        if ($count <= 0) {
            return '';
        }

        if ($count === 1) {
            $values[] = $values[0];
            $count = 2;
        }

        $points = array();
        $stepX = $count > 1 ? $width / (float) ($count - 1) : 0.0;

        foreach ($values as $index => $value) {
            $normalized = $scaleMax > 0 ? max(0.0, min(1.0, ((float) $value) / $scaleMax)) : 0.0;
            $x = $left + ($stepX * $index);
            $y = $top + $height - ($height * $normalized);
            $points[] = number_format($x, 2, '.', '') . ',' . number_format($y, 2, '.', '');
        }

        return implode(' ', $points);
    }
}
