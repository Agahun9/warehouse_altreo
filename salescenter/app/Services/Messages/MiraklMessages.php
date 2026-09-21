<?php

declare(strict_types=1);
namespace App\Services\Messages;

use App\Models\MessageRepository;
use App\Services\Integrations\Http;
use App\Services\MiraklIntegration;
use InvalidArgumentException;
use RuntimeException;

/**
 * Mirakl (Empik, MediaMarkt): wątki M11/M10 z odpowiedzią M12 i nowym wątkiem na zamówieniu OR43,
 * incydenty – zamówienia OR11 z has_incident=true (pozycje INCIDENT_OPEN), rozwiązanie OR64
 * z powodem typu INCIDENT_CLOSE z RE01.
 */
final class MiraklMessages implements MessageSource
{
    private const ROLES = ['CUSTOMER_USER' => 'customer', 'SHOP_USER' => 'seller', 'OPERATOR_USER' => 'operator'];

    /** @var MiraklIntegration */
    private $api;

    public function __construct(MiraklIntegration $api) { $this->api = $api; }

    public function fetch(array $account, array $settings, array $state, MessageRepository $repo, callable $emit): array
    {
        $errors = [];
        if (!empty($settings['sync_messages'])) {
            try { $state = $this->syncThreads($account, $settings, $state, $emit); }
            catch (\Throwable $e) { $errors[] = 'Wiadomości: '.$e->getMessage(); }
        }
        if (!empty($settings['sync_incidents'])) {
            try { $open = $this->syncIncidents($account, $emit); if ($open !== null) { $state['_open'] = ['incident' => $open]; } }
            catch (\Throwable $e) { $errors[] = 'Incydenty: '.$e->getMessage(); }
        }
        $state['_errors'] = $errors;
        return $state;
    }

    private function syncThreads(array $account, array $settings, array $state, callable $emit): array
    {
        $since = (string) ($state['threads_since'] ?? gmdate('Y-m-d H:i:s', time() - (int) $settings['history_days'] * 86400));
        $newest = $since;
        $token = '';
        for ($page = 0; $page < 20; $page++) {
            $query = ['with_messages' => 'true', 'updated_since' => Http::iso((int) strtotime($since.' UTC')), 'limit' => 50];
            if ($token !== '') { $query['page_token'] = $token; }
            $response = $this->api->api($account, 'GET', '/api/inbox/threads', $query);
            foreach ((array) ($response['data'] ?? []) as $thread) {
                if (!is_array($thread) || empty($thread['id'])) { continue; }
                $newest = max($newest, MessageRepository::date($thread['date_updated'] ?? ''));
                $types = array_column((array) ($thread['entities'] ?? []), 'type');
                if (in_array('SELLER_OPERATOR', $types, true) && empty($settings['operator_threads'])) { continue; }
                $emit($this->mapThread($thread));
            }
            $token = (string) ($response['next_page_token'] ?? '');
            if ($token === '') { break; }
        }
        $state['threads_since'] = $newest;
        return $state;
    }

    public function mapThread(array $thread): array
    {
        $participants = [];
        $customer = '';
        foreach ((array) ($thread['authorized_participants'] ?? []) as $participant) {
            $participants[] = ['type' => (string) ($participant['type'] ?? ''), 'id' => (string) ($participant['id'] ?? ''), 'display_name' => (string) ($participant['display_name'] ?? '')];
            if (($participant['type'] ?? '') === 'CUSTOMER' && $customer === '') { $customer = (string) ($participant['display_name'] ?? ''); }
        }
        $order = ''; $label = ''; $entityType = '';
        foreach ((array) ($thread['entities'] ?? []) as $entity) {
            $entityType = $entityType ?: (string) ($entity['type'] ?? '');
            $label = $label ?: (string) ($entity['label'] ?? '');
            if (($entity['type'] ?? '') === 'MMP_ORDER' && $order === '') { $order = (string) ($entity['id'] ?? ''); }
        }
        $messages = [];
        foreach ((array) ($thread['messages'] ?? []) as $message) {
            $messages[] = [
                'external_id' => (string) ($message['id'] ?? ''), 'author_role' => self::ROLES[$message['from']['type'] ?? ''] ?? 'operator',
                'author_name' => (string) (($message['from']['organization_details']['display_name'] ?? '') ?: ($message['from']['display_name'] ?? '')),
                'body' => (string) ($message['body'] ?? ''), 'created_at' => (string) ($message['date_created'] ?? ''),
                'attachments' => array_map(static function ($file): array { return ['name' => (string) ($file['name'] ?? 'załącznik'), 'id' => (string) ($file['id'] ?? ''), 'size' => (int) ($file['size'] ?? 0)]; }, (array) ($message['attachments'] ?? [])),
                'to' => array_column((array) ($message['to'] ?? []), 'type'),
            ];
        }
        $topic = trim((string) ($thread['topic']['value'] ?? ''));
        $isOperator = $entityType === 'SELLER_OPERATOR';
        return [
            'kind' => 'message', 'external_id' => (string) $thread['id'],
            'subject' => $topic !== '' ? $topic : ($label !== '' ? $label : 'Wiadomość'),
            'customer_name' => $customer !== '' ? $customer : ($isOperator ? 'Operator marketplace' : ''), 'customer_login' => '', 'order_external_id' => $order,
            'remote_status' => !empty($thread['metadata']['shop_reply_needed_since']) ? 'REPLY_NEEDED' : 'OK',
            'needs_reply' => !empty($thread['metadata']['shop_reply_needed_since']), 'closed' => false,
            'last_message_at' => (string) ($thread['metadata']['last_message_date'] ?? $thread['date_updated'] ?? ''),
            'meta' => ['participants' => $participants, 'entity_type' => $entityType, 'entity_label' => $label, 'topic_type' => (string) ($thread['topic']['type'] ?? ''), 'operator_thread' => $isOperator],
            'messages' => $messages,
        ];
    }

    /** Zwraca listę order_id z otwartymi incydentami albo null, gdy lista nie została pobrana w całości. */
    private function syncIncidents(array $account, callable $emit): ?array
    {
        $open = [];
        $total = null;
        $offset = 0;
        for ($page = 0; $page < 20; $page++) {
            $response = $this->api->api($account, 'GET', '/api/orders', ['has_incident' => 'true', 'max' => 100, 'offset' => $offset, 'paginate' => 'true']);
            $orders = (array) ($response['orders'] ?? []);
            $total = isset($response['total_count']) ? (int) $response['total_count'] : $total;
            foreach ($orders as $order) {
                $incident = $this->mapIncident($order);
                if ($incident) { $open[] = $incident['external_id']; $emit($incident); }
            }
            $offset += count($orders);
            if (count($orders) < 100 || ($total !== null && $offset >= $total)) { return $open; }
        }
        return null;
    }

    public function mapIncident(array $order): ?array
    {
        $orderId = (string) ($order['order_id'] ?? '');
        $lines = [];
        $messages = [];
        foreach ((array) ($order['order_lines'] ?? []) as $line) {
            if (($line['order_line_state'] ?? '') !== 'INCIDENT_OPEN') { continue; }
            $item = [
                'id' => (string) ($line['order_line_id'] ?? ''), 'title' => (string) ($line['product_title'] ?? ''), 'sku' => (string) ($line['offer_sku'] ?? $line['product_sku'] ?? ''),
                'quantity' => (int) ($line['quantity'] ?? 0), 'reason_code' => (string) ($line['order_line_state_reason_code'] ?? ''),
                'reason_label' => (string) ($line['order_line_state_reason_label'] ?? ''), 'updated_at' => (string) ($line['last_updated_date'] ?? ''),
            ];
            $lines[] = $item;
            $messages[] = [
                'external_id' => 'incident:'.$item['id'].':'.$item['reason_code'], 'author_role' => 'system', 'author_name' => 'Incydent',
                'body' => 'Klient zgłosił incydent dla pozycji „'.$item['title'].'”'.($item['quantity'] > 0 ? ' (szt. '.$item['quantity'].')' : '').'. Powód: '.($item['reason_label'] !== '' ? $item['reason_label'] : ($item['reason_code'] ?: 'nie podano')).'.',
                'created_at' => $item['updated_at'],
            ];
        }
        if ($orderId === '' || !$lines) { return null; }
        $reasons = array_values(array_unique(array_filter(array_column($lines, 'reason_label'))));
        $customer = trim((string) ($order['customer']['firstname'] ?? '').' '.(string) ($order['customer']['lastname'] ?? ''));
        return [
            'kind' => 'incident', 'external_id' => $orderId, 'subject' => 'Incydent: '.($reasons ? implode(', ', $reasons) : 'zgłoszenie klienta'),
            'customer_name' => $customer, 'customer_login' => '', 'order_external_id' => $orderId, 'remote_status' => 'INCIDENT_OPEN',
            'needs_reply' => true, 'closed' => false, 'last_message_at' => max(array_column($lines, 'updated_at')),
            'meta' => ['lines' => $lines, 'order_state' => (string) ($order['order_state'] ?? ''), 'commercial_id' => (string) ($order['commercial_id'] ?? '')],
            'messages' => $messages,
        ];
    }

    public function reply(array $account, array $thread, string $text, array $options, MessageRepository $repo): string
    {
        $text = trim($text);
        if ($thread['kind'] === 'incident') { return $this->replyOnOrder($account, $thread, $text, (string) ($options['topic'] ?? 'Zgłoszony incydent'), $repo); }
        return $this->replyInThread($account, $thread, $text, $options);
    }

    /**
     * Wiadomość do klienta o zamówieniu (incydent, uwaga do zamówienia): w istniejącym wątku zamówienia,
     * a gdy go nie ma – nowy wątek OR43.
     */
    public function replyOnOrder(array $account, array $thread, string $text, string $topic, MessageRepository $repo): string
    {
        foreach ($repo->threadsForOrder((int) $thread['connection_id'], (string) $thread['order_external_id'], 'message') as $orderThread) {
            if (empty($orderThread['meta']['operator_thread'])) { return $this->replyInThread($account, $orderThread, $text, ['recipients' => ['CUSTOMER']]); }
        }
        $response = $this->api->apiMultipart($account, '/api/orders/'.rawurlencode((string) $thread['order_external_id']).'/threads', [[
            'name' => 'thread_input', 'type' => 'application/json',
            'content' => json_encode(['body' => $text, 'to' => ['CUSTOMER'], 'topic' => ['type' => 'FREE_TEXT', 'value' => mb_substr($topic, 0, 120, 'UTF-8')]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]]);
        return (string) ($response['message_id'] ?? '');
    }

    private function replyInThread(array $account, array $thread, string $text, array $options): string
    {
        $wanted = array_values(array_intersect(['CUSTOMER', 'OPERATOR'], (array) ($options['recipients'] ?? ['CUSTOMER'])));
        $participants = (array) ($thread['meta']['participants'] ?? []);
        $available = array_column($participants, 'type');
        $to = [];
        foreach ($wanted ?: ['CUSTOMER'] as $type) {
            if ($available && !in_array($type, $available, true)) { continue; }
            $recipient = ['type' => $type];
            foreach ($participants as $participant) { if ($participant['type'] === $type && $participant['id'] !== '') { $recipient['id'] = $participant['id']; break; } }
            $to[] = $recipient;
        }
        if (!$to) { $to = [['type' => 'OPERATOR']]; }
        $response = $this->api->apiMultipart($account, '/api/inbox/threads/'.rawurlencode((string) $thread['external_id']).'/message', [[
            'name' => 'message_input', 'type' => 'application/json',
            'content' => json_encode(['body' => $text, 'to' => $to], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]]);
        return (string) ($response['message_id'] ?? '');
    }

    /** Powody zamknięcia incydentu dostępne dla sklepu (RE01, typ INCIDENT_CLOSE). */
    public function incidentReasons(array $account): array
    {
        $reasons = [];
        foreach ((array) ($this->api->api($account, 'GET', '/api/reasons')['reasons'] ?? []) as $reason) {
            if (($reason['type'] ?? '') === 'INCIDENT_CLOSE' && ($reason['is_shop_right'] ?? true)) { $reasons[(string) $reason['code']] = (string) ($reason['label'] ?? $reason['code']); }
        }
        return $reasons;
    }

    /** OR64 dla wskazanych pozycji z otwartym incydentem. */
    public function resolveIncident(array $account, array $thread, array $lineIds, string $reason): array
    {
        if ($thread['kind'] !== 'incident') { throw new InvalidArgumentException('To nie jest incydent.'); }
        if (trim($reason) === '') { throw new InvalidArgumentException('Wybierz powód rozwiązania incydentu.'); }
        $lines = array_column((array) ($thread['meta']['lines'] ?? []), 'id');
        $lineIds = array_values(array_intersect($lines, $lineIds ?: $lines));
        if (!$lineIds) { throw new InvalidArgumentException('Brak pozycji z otwartym incydentem.'); }
        $done = [];
        foreach ($lineIds as $lineId) {
            try {
                $this->api->api($account, 'PUT', '/api/orders/'.rawurlencode((string) $thread['external_id']).'/lines/'.rawurlencode((string) $lineId).'/resolve_incident', [], ['reason_code' => $reason]);
                $done[] = $lineId;
            } catch (RuntimeException $e) {
                if ($done) { throw new RuntimeException('Rozwiązano '.count($done).' z '.count($lineIds).' pozycji. '.$e->getMessage()); }
                throw $e;
            }
        }
        return $done;
    }
}
