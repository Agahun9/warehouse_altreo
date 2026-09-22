<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\ComputerSpecificationService;
use Throwable;

/**
 * Wywolania z automatyzacji SalesCenter (akcja "Wywolaj webhook").
 * SalesCenter wysyla POST JSON z danymi zamowienia, a notatki z odpowiedzi
 * ("notes") dopisuje do zamowienia.
 */
class SalescenterController extends Controller
{
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

    private function respond(int $status, array $data): void
    {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
