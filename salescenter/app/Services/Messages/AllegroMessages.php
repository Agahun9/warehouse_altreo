<?php

declare(strict_types=1);
namespace App\Services\Messages;

use App\Models\MessageRepository;
use App\Services\AllegroService;
use App\Services\Integrations\Http;
use InvalidArgumentException;

/**
 * Allegro: Centrum wiadomości (/messaging, public.v1) oraz Dyskusje i reklamacje (/sale/issues, beta.v1).
 * Uprawnienia aplikacji: allegro:api:messaging i allegro:api:disputes.
 */
final class AllegroMessages implements MessageSource
{
    public const ISSUE_STATUSES = [
        'DISPUTE_ONGOING' => 'Dyskusja otwarta', 'DISPUTE_UNRESOLVED' => 'Dyskusja nierozwiązana', 'DISPUTE_CLOSED' => 'Dyskusja zakończona',
        'CLAIM_SUBMITTED' => 'Reklamacja – czeka na decyzję', 'CLAIM_ACCEPTED' => 'Reklamacja uznana', 'CLAIM_REJECTED' => 'Reklamacja odrzucona',
    ];

    public const REASONS = [
        'NO_PRODUCT_RECEIVED' => 'Nie otrzymano produktu', 'NO_PRODUCT_IN_PARCEL' => 'Brak produktu w paczce',
        'NO_PROOF_OF_PURCHASE_MANUAL_OR_WARRANTY' => 'Brak dowodu zakupu, instrukcji lub gwarancji', 'MISSING_PRODUCT_ELEMENT' => 'Brak elementu produktu',
        'PRODUCT_AND_PARCEL_DAMAGED_IN_TRANSIT' => 'Produkt i paczka uszkodzone w transporcie', 'PRODUCT_DAMAGED_PARCEL_INTACT' => 'Produkt uszkodzony, paczka nienaruszona',
        'DEFECT_FOUND_DURING_USE' => 'Wada ujawniona podczas używania', 'NOT_AS_DESCRIBED' => 'Produkt niezgodny z opisem',
        'SELLER_DOES_NOT_WANT_TO_ACCEPT_RETURN' => 'Sprzedający nie przyjmuje zwrotu', 'PROBLEM_WITH_SENDING_PRODUCT_BACK' => 'Problem z odesłaniem produktu',
        'NO_REFUND_AFTER_RETURNING_PRODUCT' => 'Brak zwrotu pieniędzy po odesłaniu produktu', 'NO_REFUND_AFTER_CANCELING_ORDER' => 'Brak zwrotu pieniędzy po anulowaniu',
        'DID_NOT_RECEIVE_GOODS_AFTER_PAYMENT' => 'Nie otrzymano towaru po wpłacie', 'RECEIVED_ITEMS_NOT_MATCHING_DESCRIPTION' => 'Otrzymany towar niezgodny z opisem',
        'RECEIVED_INCOMPLETE_ORDER' => 'Niekompletne zamówienie', 'ITEM_IS_DAMAGED' => 'Towar uszkodzony',
        'PROBLEM_WITH_WITHDRAWAL_CANCELLATION_OF_PURCHASE' => 'Problem z odstąpieniem od umowy', 'PROBLEM_WITH_GOODS_RETURN_CANCELLATION_OF_PURCHASE' => 'Problem ze zwrotem towaru',
        'OTHER' => 'Inny powód',
    ];

    public const EXPECTATIONS = ['REPAIR' => 'Naprawa', 'EXCHANGE' => 'Wymiana', 'REFUND' => 'Zwrot pieniędzy', 'PARTIAL_REFUND' => 'Częściowy zwrot pieniędzy'];

    public const RIGHTS = ['COMPLAINT' => 'Reklamacja (niezgodność z umową)', 'WARRANTY' => 'Gwarancja'];

    /** Decyzje w reklamacji – POST /sale/issues/{id}/status. */
    public const DECISIONS = [
        'ACCEPTED_REFUND' => 'Uznaję – zwrot pieniędzy', 'ACCEPTED_PARTIAL_REFUND' => 'Uznaję – częściowy zwrot (obniżenie ceny)',
        'ACCEPTED_EXCHANGE' => 'Uznaję – wymiana', 'ACCEPTED_REPAIR' => 'Uznaję – naprawa',
        'REJECTED_PRODUCT_CONFORMS_TO_CONTRACT' => 'Odrzucam – produkt zgodny z umową', 'REJECTED_PRODUCT_DAMAGED_BY_USER' => 'Odrzucam – uszkodzenie z winy kupującego',
        'REJECTED_MINOR_DEFECT' => 'Odrzucam – wada nieistotna', 'REJECTED_PRODUCT_NOT_RETURNED' => 'Odrzucam – produkt nie został odesłany',
        'REJECTED_ADDITIONAL_REQUIREMENTS_NOT_COMPLETED' => 'Odrzucam – brak wymaganych informacji od kupującego',
        'REJECTED_CLAIM_WITHDRAWN_BY_BUYER' => 'Odrzucam – kupujący wycofał reklamację', 'REJECTED_OTHER' => 'Odrzucam – inny powód',
    ];

    /** Rodzaje wiadomości w dyskusji/reklamacji (pole type w POST /sale/issues/{id}/message). */
    public const MESSAGE_TYPES = [
        'dispute' => ['REGULAR' => 'Zwykła wiadomość', 'END_REQUEST' => 'Wiadomość + prośba o zakończenie dyskusji'],
        'claim' => ['REGULAR' => 'Zwykła wiadomość', 'RETURN_REQUIRED_CUSTOM' => 'Wymagam odesłania produktu (instrukcje w treści)', 'RETURN_REQUIRED_SELLER_LABEL' => 'Wymagam odesłania – przekażę etykietę zwrotną', 'RETURN_NOT_REQUIRED' => 'Odesłanie produktu nie jest wymagane'],
    ];

    private const OPEN_ISSUES = ['DISPUTE_ONGOING', 'DISPUTE_UNRESOLVED', 'CLAIM_SUBMITTED'];
    private const CLOSED_ISSUES = ['DISPUTE_CLOSED', 'CLAIM_ACCEPTED', 'CLAIM_REJECTED'];

    /** @var AllegroService */
    private $api;

    public function __construct(?AllegroService $api = null) { $this->api = $api ?? new AllegroService(true); }

    public function fetch(array $account, array $settings, array $state, MessageRepository $repo, callable $emit): array
    {
        $errors = [];
        if (!empty($settings['sync_messages'])) {
            try { $state = $this->syncThreads($account, $settings, $state, $repo, $emit); }
            catch (\Throwable $e) { $errors[] = 'Centrum wiadomości: '.$e->getMessage(); }
        }
        if (!empty($settings['sync_issues'])) {
            try { $this->syncIssues($account, $repo, $emit); }
            catch (\Throwable $e) { $errors[] = 'Dyskusje i reklamacje: '.$e->getMessage(); }
        }
        $state['_errors'] = $errors;
        return $state;
    }

    /** Wątki są posortowane od najnowszej wiadomości – czytamy do daty ostatniej synchronizacji. */
    private function syncThreads(array $account, array $settings, array $state, MessageRepository $repo, callable $emit): array
    {
        $connectionId = (int) $account['connection_id'];
        $since = (string) ($state['threads_since'] ?? gmdate('Y-m-d H:i:s', time() - (int) $settings['history_days'] * 86400));
        $newest = $since;
        for ($offset = 0; $offset < 400; $offset += 20) {
            $threads = (array) ($this->api->api($account, 'GET', '/messaging/threads', ['limit' => 20, 'offset' => $offset])['threads'] ?? []);
            foreach ($threads as $thread) {
                $id = (string) ($thread['id'] ?? '');
                $last = MessageRepository::date($thread['lastMessageDateTime'] ?? '');
                if ($id === '') { continue; }
                if ($last !== '' && $last <= $since) { break 2; }
                $newest = max($newest, $last);
                $local = $repo->findByExternal($connectionId, 'message', $id);
                if ($local && (string) $local['last_message_at'] >= $last) { continue; }
                $emit($this->mapThread($account, $thread, $local));
            }
            if (count($threads) < 20) { break; }
        }
        $state['threads_since'] = $newest;
        return $state;
    }

    private function mapThread(array $account, array $thread, ?array $local): array
    {
        $id = (string) $thread['id'];
        $after = $local && !empty($local['last_message_at']) ? Http::iso((int) strtotime($local['last_message_at'].' UTC') - 1) : '';
        $messages = [];
        for ($offset = 0; $offset < 100; $offset += 20) {
            $query = ['limit' => 20, 'offset' => $offset];
            if ($after !== '') { $query['after'] = $after; }
            $page = (array) ($this->api->api($account, 'GET', '/messaging/threads/'.rawurlencode($id).'/messages', $query)['messages'] ?? []);
            $messages = array_merge($messages, $page);
            if (count($page) < 20) { break; }
        }
        usort($messages, static function (array $a, array $b): int { return strcmp((string) ($a['createdAt'] ?? ''), (string) ($b['createdAt'] ?? '')); });
        $login = (string) ($thread['interlocutor']['login'] ?? '');
        $subject = ''; $order = ''; $offer = '';
        $mapped = [];
        foreach ($messages as $message) {
            $subject = $subject !== '' ? $subject : trim((string) ($message['subject'] ?? ''));
            $order = $order !== '' ? $order : (string) ($message['relatesTo']['order']['id'] ?? '');
            $offer = $offer !== '' ? $offer : (string) ($message['relatesTo']['offer']['id'] ?? '');
            $mapped[] = [
                'external_id' => (string) ($message['id'] ?? ''), 'author_role' => !empty($message['author']['isInterlocutor']) ? 'customer' : 'seller',
                'author_name' => (string) ($message['author']['login'] ?? ''), 'body' => (string) ($message['text'] ?? ''), 'created_at' => (string) ($message['createdAt'] ?? ''),
                'attachments' => array_map(static function ($file): array { return ['name' => (string) ($file['fileName'] ?? 'załącznik'), 'status' => (string) ($file['status'] ?? '')]; }, (array) ($message['attachments'] ?? [])),
            ];
        }
        $lastMessage = end($mapped) ?: null;
        $meta = ['read' => !empty($thread['read'])];
        if ($offer !== '') { $meta['offer_id'] = $offer; }
        return [
            'kind' => 'message', 'external_id' => $id,
            'subject' => $subject !== '' ? $subject : ($offer !== '' ? 'Pytanie o ofertę '.$offer : ($order !== '' ? 'Wiadomość do zamówienia' : 'Wiadomość od '.$login)),
            'customer_name' => $login, 'customer_login' => $login, 'order_external_id' => $order,
            'remote_status' => !empty($thread['read']) ? 'READ' : 'UNREAD',
            'needs_reply' => $lastMessage ? $lastMessage['author_role'] === 'customer' : (bool) ($local['needs_reply'] ?? false),
            'closed' => false, 'last_message_at' => (string) ($thread['lastMessageDateTime'] ?? ''), 'meta' => $meta, 'messages' => $mapped,
        ];
    }

    /** Otwarte sprawy (wszystkie strony) + ostatnie sprawy + lokalnie otwarte, których nie było na listach. */
    private function syncIssues(array $account, MessageRepository $repo, callable $emit): void
    {
        $connectionId = (int) $account['connection_id'];
        $issues = [];
        for ($offset = 0; $offset < 500; $offset += 100) {
            $page = (array) ($this->api->api($account, 'GET', '/sale/issues', ['status' => self::OPEN_ISSUES, 'limit' => 100, 'offset' => $offset], null, 'beta.v1')['issues'] ?? []);
            foreach ($page as $issue) { $issues[(string) ($issue['id'] ?? '')] = $issue; }
            if (count($page) < 100) { break; }
        }
        foreach ((array) ($this->api->api($account, 'GET', '/sale/issues', ['limit' => 50], null, 'beta.v1')['issues'] ?? []) as $issue) {
            $issues[(string) ($issue['id'] ?? '')] = $issue;
        }
        unset($issues['']);
        $missing = 0;
        foreach (['dispute', 'claim'] as $kind) {
            foreach ($repo->openThreads($connectionId, $kind) as $local) {
                if (isset($issues[$local['external_id']]) || ++$missing > 25) { continue; }
                try { $issues[$local['external_id']] = $this->api->api($account, 'GET', '/sale/issues/'.rawurlencode($local['external_id']), [], null, 'beta.v1'); }
                catch (\Throwable $e) { if (strpos($e->getMessage(), '[404]') === false) { throw $e; } }
            }
        }
        foreach ($issues as $issue) { $emit($this->mapIssue($account, $issue, $repo)); }
    }

    private function mapIssue(array $account, array $issue, MessageRepository $repo): array
    {
        $id = (string) $issue['id'];
        $kind = strtoupper((string) ($issue['type'] ?? '')) === 'CLAIM' ? 'claim' : 'dispute';
        $status = (string) ($issue['currentState']['status'] ?? '');
        $lastAt = MessageRepository::date($issue['chat']['lastMessage']['createdAt'] ?? '');
        $count = (int) ($issue['chat']['messagesCount'] ?? 0);
        $local = $repo->findByExternal((int) $account['connection_id'], $kind, $id);
        $messages = null;
        if (!$local || (string) $local['last_message_at'] < $lastAt || (string) $local['remote_status'] !== $status || $count > (int) $local['message_count']) {
            $chat = [];
            for ($offset = 0; $offset < 500; $offset += 100) {
                $page = (array) ($this->api->api($account, 'GET', '/sale/issues/'.rawurlencode($id).'/chat', ['limit' => 100, 'offset' => $offset], null, 'beta.v1')['chat'] ?? []);
                $chat = array_merge($chat, $page);
                if (count($page) < 100) { break; }
            }
            if (!$chat && !empty($issue['chat']['initialMessage'])) { $chat[] = $issue['chat']['initialMessage']; }
            $messages = [];
            foreach ($chat as $entry) {
                $text = trim((string) ($entry['text'] ?? ''));
                $files = array_map(static function ($file): array { return ['name' => (string) ($file['fileName'] ?? 'załącznik')]; }, (array) ($entry['attachments'] ?? []));
                if ($text === '' && !$files) { continue; }
                $role = strtoupper((string) ($entry['author']['role'] ?? ''));
                $messages[] = [
                    'external_id' => (string) ($entry['id'] ?? ''), 'author_role' => $role === 'BUYER' ? 'customer' : ($role === 'SELLER' ? 'seller' : 'operator'),
                    'author_name' => (string) ($entry['author']['login'] ?? ($role === 'SELLER' ? 'Ty' : 'Allegro')), 'body' => $text, 'attachments' => $files, 'created_at' => (string) ($entry['createdAt'] ?? ''),
                ];
            }
        }
        $reasonType = (string) ($issue['reason']['type'] ?? '');
        $reason = self::REASONS[$reasonType] ?? '';
        $reference = trim((string) ($issue['referenceNumber'] ?? ''));
        $subject = trim((string) ($issue['subject'] ?? '')) ?: ($reason !== '' ? $reason : trim((string) ($issue['description'] ?? '')));
        if ($kind === 'claim') { $subject = 'Reklamacja'.($reference !== '' ? ' '.$reference : '').($subject !== '' ? ': '.$subject : ''); }
        $lastStatus = (string) ($issue['chat']['lastMessage']['status'] ?? '');
        $chatActive = !array_key_exists('chatActive', (array) ($issue['currentState'] ?? [])) || !empty($issue['currentState']['chatActive']);
        $closed = in_array($status, self::CLOSED_ISSUES, true);
        return [
            'kind' => $kind, 'external_id' => $id, 'subject' => $subject !== '' ? $subject : ($kind === 'claim' ? 'Reklamacja' : 'Dyskusja'),
            'customer_name' => (string) ($issue['buyer']['login'] ?? ''), 'customer_login' => (string) ($issue['buyer']['login'] ?? ''),
            'order_external_id' => (string) ($issue['checkoutForm']['id'] ?? ''), 'remote_status' => $status,
            'needs_reply' => !$closed && ($chatActive || $kind === 'claim') && (in_array($lastStatus, ['NEW', 'BUYER_REPLIED', 'ALLEGRO_ADVISOR_REPLIED'], true) || ($kind === 'claim' && $status === 'CLAIM_SUBMITTED' && $lastStatus === '')),
            'closed' => $closed, 'last_message_at' => (string) ($issue['chat']['lastMessage']['createdAt'] ?? $issue['openedDate'] ?? ''),
            'due_at' => $kind === 'claim' && !$closed ? (string) (($issue['currentState']['statusDueDate'] ?? '') ?: ($issue['decisionDueDate'] ?? '')) : '',
            'meta' => [
                'issue_type' => (string) ($issue['type'] ?? ''), 'reference' => $reference, 'right' => (string) ($issue['right'] ?? ''), 'reason_type' => $reasonType,
                'reason_description' => (string) ($issue['reason']['description'] ?? ''), 'description' => (string) ($issue['description'] ?? ''),
                'expectations' => array_values(array_map(static function ($item): array { return ['name' => (string) ($item['name'] ?? ''), 'amount' => (string) ($item['refund']['amount'] ?? ''), 'currency' => (string) ($item['refund']['currency'] ?? '')]; }, (array) ($issue['expectations'] ?? []))),
                'return_required' => $issue['currentState']['returnRequired'] ?? null, 'chat_active' => $chatActive, 'last_message_status' => $lastStatus,
                'offer_id' => (string) ($issue['offer']['id'] ?? ''), 'quantity' => (int) ($issue['offer']['quantity'] ?? 0), 'opened_at' => (string) ($issue['openedDate'] ?? ''),
                'decision_due' => (string) ($issue['decisionDueDate'] ?? ''), 'files' => array_map(static function ($file): string { return (string) ($file['fileName'] ?? ''); }, (array) ($issue['attachments'] ?? [])),
            ],
            'messages' => $messages,
        ];
    }

    public function reply(array $account, array $thread, string $text, array $options, MessageRepository $repo): string
    {
        $text = trim($text);
        if ($thread['kind'] === 'message') {
            if (mb_strlen($text, 'UTF-8') > 2000) { throw new InvalidArgumentException('Allegro przyjmuje wiadomości do 2000 znaków.'); }
            $response = $this->api->api($account, 'POST', '/messaging/threads/'.rawurlencode((string) $thread['external_id']).'/messages', [], ['text' => $text]);
            return (string) ($response['id'] ?? '');
        }
        $type = (string) ($options['message_type'] ?? 'REGULAR');
        if (!isset(self::MESSAGE_TYPES[$thread['kind']][$type])) { $type = 'REGULAR'; }
        if (mb_strlen($text, 'UTF-8') > 20000) { throw new InvalidArgumentException('Wiadomość w dyskusji może mieć maks. 20000 znaków.'); }
        $response = $this->api->api($account, 'POST', '/sale/issues/'.rawurlencode((string) $thread['external_id']).'/message', [], ['text' => $text, 'type' => $type], 'beta.v1');
        return (string) ($response['id'] ?? '');
    }

    /** Decyzja w reklamacji (uznanie/odrzucenie), opcjonalnie z kwotą częściowego zwrotu. */
    public function decide(array $account, array $thread, string $decision, string $message, string $refund, string $currency): void
    {
        if ($thread['kind'] !== 'claim') { throw new InvalidArgumentException('Decyzję można podjąć tylko w reklamacji.'); }
        if (!isset(self::DECISIONS[$decision])) { throw new InvalidArgumentException('Wybierz decyzję.'); }
        if (trim($message) === '') { throw new InvalidArgumentException('Uzasadnienie decyzji dla kupującego jest wymagane.'); }
        $body = ['status' => $decision, 'message' => trim($message)];
        if ($decision === 'ACCEPTED_PARTIAL_REFUND') {
            $amount = (float) str_replace(',', '.', $refund);
            if ($amount <= 0) { throw new InvalidArgumentException('Podaj kwotę częściowego zwrotu.'); }
            $body['partialRefund'] = ['amount' => Http::amount($amount), 'currency' => preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'PLN'];
        }
        $this->api->api($account, 'POST', '/sale/issues/'.rawurlencode((string) $thread['external_id']).'/status', [], $body, 'beta.v1');
    }

    public function markRead(array $account, array $thread): void
    {
        if ($thread['kind'] !== 'message') { return; }
        $this->api->api($account, 'PUT', '/messaging/threads/'.rawurlencode((string) $thread['external_id']).'/read', [], ['read' => true]);
    }
}
