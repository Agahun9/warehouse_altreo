<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\SettingRepository;
use App\Services\ComputerSpecificationService;
use App\Services\SellasistService;
use Throwable;

/**
 * Wywolania z automatyzacji SalesCenter (akcja "Wywolaj webhook").
 * SalesCenter wysyla POST JSON z danymi zamowienia, a notatki z odpowiedzi
 * ("notes") dopisuje do zamowienia.
 */
class SalescenterController extends Controller
{
    const STOCK_KEY_SETTING = 'salescenter_stock_key';

    /** Staly klucz dla linkow stanow (tworzony przy pierwszym wyswietleniu w Administracji). */
    public static function stockKey(SettingRepository $settings): string
    {
        $key = $settings->get(self::STOCK_KEY_SETTING, '');
        if (!preg_match('/^[a-f0-9]{40}$/D', $key)) {
            $key = bin2hex(random_bytes(20));
            $settings->set(self::STOCK_KEY_SETTING, $key);
        }

        return $key;
    }

    public function subtractstock(): void
    {
        $this->changeStock('subtract');
    }

    public function addstock(): void
    {
        $this->changeStock('add');
    }

    public function computers_spec(): void
    {
        $orderId = (int) $this->input('id', 0);

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        if ($orderId <= 0) {
            $this->respond(400, array('ok' => false, 'error' => 'Brak poprawnego ID zamowienia.'));
            return;
        }

        $payload = json_decode((string) file_get_contents('php://input'), true);
        $order = is_array($payload) && isset($payload['order']) && is_array($payload['order']) ? $payload['order'] : array();
        if (isset($order['id']) && (int) $order['id'] !== $orderId) {
            $this->respond(400, array('ok' => false, 'error' => 'ID zamowienia w adresie nie zgadza sie z danymi webhooka.'));
            return;
        }

        $skus = array();
        foreach ((isset($order['items']) && is_array($order['items']) ? $order['items'] : array()) as $item) {
            if (is_array($item) && trim((string) ($item['sku'] ?? '')) !== '') {
                $skus[] = trim((string) $item['sku']);
            }
        }
        if ($skus === array()) {
            $this->respond(400, array('ok' => false, 'error' => 'Brak pozycji z SKU. Adres wywoluje automatyzacja SalesCenter (Wywolaj webhook), ktora wysyla dane zamowienia.'));
            return;
        }

        try {
            $notes = (new ComputerSpecificationService($this->db()))->build($skus);
            $this->respond(200, array(
                'ok' => true,
                'order_id' => $orderId,
                'notes' => array($notes['text'], $notes['plain_text']),
            ));
        } catch (Throwable $exception) {
            $this->respond(422, array('ok' => false, 'error' => $exception->getMessage()));
        }
    }

    private function changeStock(string $mode): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        $settings = new SettingRepository($this->db());
        $settings->ensureSchema();
        $storedKey = $settings->get(self::STOCK_KEY_SETTING, '');
        $key = (string) $this->input('key', '');
        if ($storedKey === '' || $key === '' || !hash_equals($storedKey, $key)) {
            $this->respond(403, array('ok' => false, 'error' => 'Nieprawidlowy klucz w adresie. Skopiuj link z Administracja -> Automatyzacje -> Sellasist / SalesCenter.'));
            return;
        }

        $orderId = (int) $this->input('id', 0);
        if ($orderId <= 0) {
            $this->respond(400, array('ok' => false, 'error' => 'Brak poprawnego ID zamowienia.'));
            return;
        }

        $payload = json_decode((string) file_get_contents('php://input'), true);
        $order = is_array($payload) && isset($payload['order']) && is_array($payload['order']) ? $payload['order'] : array();
        if ((int) ($order['id'] ?? 0) !== $orderId) {
            $this->respond(400, array('ok' => false, 'error' => 'Brak danych zamowienia albo ID w adresie nie zgadza sie z danymi webhooka. Adres wywoluje automatyzacja SalesCenter (Wywolaj webhook).'));
            return;
        }

        try {
            $result = (new SellasistService($this->db()))->changeStockForSalescenterOrder($order, $mode);
        } catch (Throwable $exception) {
            $this->respond(422, array('ok' => false, 'error' => $exception->getMessage()));
            return;
        }

        $verb = $mode === 'add' ? 'Dodano' : 'Odjeto';
        $notes = array();
        if (!empty($result['skipped'])) {
            $notes[] = 'Magazyn: ' . (string) ($result['message'] ?? 'pominieto.');
        } else {
            foreach ($result['deductions'] as $row) {
                if (($row['status'] ?? '') === 'ok') {
                    $notes[] = 'Magazyn: ' . $verb . ' ' . (int) $row['deducted_qty'] . ' szt. ' . (string) $row['sku']
                        . ' (' . (int) $row['before_qty'] . ' -> ' . (int) $row['after_qty'] . ')';
                } else {
                    $notes[] = 'Magazyn: nie znaleziono produktu dla SKU ' . ((string) ($row['signature'] ?? '') !== '' ? (string) $row['signature'] : '(brak SKU)')
                        . ' - ' . (string) ($row['source_name'] ?? '');
                }
            }
        }

        $this->respond(200, array(
            'ok' => true,
            'order_id' => $orderId,
            'skipped' => !empty($result['skipped']),
            'notes' => array_slice($notes, 0, 10),
        ));
    }

    private function respond(int $status, array $data): void
    {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
