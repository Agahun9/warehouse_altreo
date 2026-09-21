<?php

declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Models\ConnectionRepository;
use App\Models\MessageRepository;
use App\Models\OrderRepository;
use App\Services\Messages\AllegroMessages;
use App\Services\Messages\MessageCenter;
use App\Services\Messages\MoreleMessages;
use InvalidArgumentException;

/** Wiadomości z marketplace'ów: skrzynka z podziałem na kanały, autoodpowiedzi i ustawienia. */
final class MessagesController extends Controller
{
    private function center(): MessageCenter
    {
        (new OrderRepository($this->db()))->ensureSchema();
        (new ConnectionRepository($this->db()))->ensureSchema();
        $repo = new MessageRepository($this->db());
        $repo->ensureSchema();
        return new MessageCenter($repo);
    }

    public function index(): void
    {
        $user = $this->requireModule('orders');
        $canWrite = $this->moduleAccessLevel($user, 'orders') === 'edit';
        $center = $this->center();
        $repo = $center->repo();
        $tab = (string) $this->input('tab', 'inbox');
        if (!in_array($tab, ['inbox', 'rules', 'settings'], true)) { $tab = 'inbox'; }
        $accounts = $center->connectedAccounts();
        $filters = [
            'platform' => preg_replace('/[^a-z]/', '', (string) $this->input('platform', '')),
            'kind' => preg_replace('/[^a-z]/', '', (string) $this->input('kind', '')),
            'status' => preg_replace('/[^a-z]/', '', (string) $this->input('status', 'open')) ?: 'open',
            'connection' => (int) $this->input('connection', 0),
            'q' => mb_substr(trim((string) $this->input('q', '')), 0, 100, 'UTF-8'),
            'page' => max(1, (int) $this->input('page', 1)),
        ];
        $query = http_build_query(array_filter($filters, static function ($value, $key) { return $key !== 'page' && $value !== '' && $value !== 0 && !($key === 'status' && $value === 'open'); }, ARRAY_FILTER_USE_BOTH));
        $thread = null; $messages = []; $threadView = [];
        $threadId = (int) $this->input('id', 0);
        if ($tab === 'inbox' && $threadId > 0) {
            try {
                $thread = $repo->findThread($threadId);
                if ($canWrite) { $center->opened($thread); $thread = $repo->findThread($threadId); }
                $messages = array_map(static function (array $message): array { $message['time_label'] = self::local((string) $message['created_at']); return $message; }, $repo->messages($threadId));
                $thread = self::decorate($thread);
                $threadView = $this->threadView($center, $thread);
            } catch (InvalidArgumentException $e) { $thread = null; }
        }
        $settings = $repo->settings();
        $platforms = [];
        foreach (MessageRepository::PLATFORMS as $platform => $label) {
            $connected = array_values(array_filter($accounts, static function (array $account) use ($platform): bool { return $account['platform'] === $platform; }));
            $platforms[$platform] = ['label' => $label, 'kinds' => MessageRepository::KINDS[$platform], 'accounts' => $connected, 'settings' => $settings[$platform]];
        }
        $editRule = null;
        if ($tab === 'rules' && (string) $this->input('rule', '') !== '') {
            $editRule = (string) $this->input('rule') === 'new'
                ? ['id' => 0, 'name' => '', 'enabled' => 1, 'platform' => '', 'connection_id' => 0, 'kinds' => ['message'], 'trigger_name' => 'first_message', 'keywords' => '', 'delay_minutes' => 0, 'cooldown_hours' => 24, 'template' => '', 'after_status' => 'auto']
                : $repo->rule((int) $this->input('rule'));
        }
        $listing = $tab === 'inbox' ? $repo->listing($filters) : ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
        $listing['rows'] = array_map([self::class, 'decorate'], $listing['rows']);
        $templates = [];
        foreach ($repo->rules() as $rule) { $templates[] = ['name' => (string) $rule['name'], 'text' => (string) $rule['template']]; }
        header('Cache-Control: no-store, private');
        $this->render('messages/index', [
            'pageTitle' => 'Wiadomości', 'tab' => $tab, 'csrf' => $this->csrfToken(), 'canWrite' => $canWrite,
            'filters' => $filters, 'filterQuery' => $query, 'backQuery' => 'tab=inbox'.($query !== '' ? '&'.$query : '').($listing['page'] > 1 ? '&page='.$listing['page'] : '').($thread ? '&id='.(int) $thread['id'] : ''), 'listing' => $listing,
            'counters' => $repo->counters(), 'platforms' => $platforms, 'accounts' => $accounts,
            'thread' => $thread, 'messages' => $messages, 'threadView' => $threadView,
            'statuses' => MessageRepository::STATUSES, 'kindLabels' => MessageRepository::KIND_LABELS, 'platformLabels' => MessageRepository::PLATFORMS,
            'rules' => $tab === 'rules' ? $repo->rules() : [], 'editRule' => $editRule, 'runLog' => $tab === 'rules' ? $repo->runLog(30) : [],
            'triggers' => MessageRepository::TRIGGERS, 'afterStatuses' => MessageRepository::AFTER_STATUSES, 'placeholders' => MessageCenter::PLACEHOLDERS,
            'issueStatuses' => AllegroMessages::ISSUE_STATUSES, 'moreleTypes' => MoreleMessages::TYPES,
            'replyTemplatesJson' => json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            'threadJson' => $thread ? json_encode(['klient' => $thread['customer_name'] ?: $thread['customer_login'], 'zamowienie' => $thread['order_external_id'], 'temat' => $thread['subject'], 'numer' => $thread['meta']['reference'] ?? '', 'platforma' => MessageRepository::PLATFORMS[$thread['platform']] ?? '', 'podpis' => $settings[$thread['platform']]['signature'] ?? ''], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '{}',
            'syncStates' => array_map(static function (array $account): array { $sync = (array) $account['sync']; return ['last' => !empty($sync['last_sync']) ? self::local((string) $sync['last_sync']) : '', 'error' => (string) ($sync['error'] ?? '')]; }, array_column($accounts, null, 'id')),
        ]);
    }

    /** Dane panelu wątku: akcje dostępne dla marketplace i rodzaju. */
    private function threadView(MessageCenter $center, array $thread): array
    {
        $view = ['order_url' => $thread['order_id'] ? 'orders.php?id='.(int) $thread['order_id'] : '', 'message_types' => [], 'decisions' => [], 'reasons' => [], 'reasons_error' => '', 'recipients' => [], 'meta_rows' => [],
            'can_reply' => MessageCenter::canReply($thread), 'reply_hint' => ''];
        $meta = $thread['meta'];
        if (!$view['can_reply']) {
            $view['reply_hint'] = $thread['kind'] === 'return'
                ? 'ERLI nie udostępnia w API odpowiedzi na zwroty – obsłuż zwrot w panelu ERLI, a tutaj ustaw status wątku.'
                : (MessageRepository::PLATFORMS[$thread['platform']] ?? $thread['platform']).' nie udostępnia wysyłania wiadomości przez API. Skontaktuj się z klientem'.(($meta['email'] ?? '') !== '' ? ' ('.$meta['email'].')' : '').' innym kanałem i oznacz wątek jako obsłużony.';
        }
        if (in_array($thread['kind'], ['note', 'return'], true)) {
            $view['meta_rows'] = array_filter(['E-mail kupującego' => (string) ($meta['email'] ?? ''), 'Odpowiedź' => $view['can_reply'] ? ($thread['platform'] === 'woocommerce' ? 'notatka dla klienta – WooCommerce wyśle ją e-mailem' : ($thread['platform'] === 'allegro' ? 'wiadomość w Centrum wiadomości Allegro' : 'wiadomość do klienta w wątku zamówienia')) : ''], 'strlen');
            if ($thread['kind'] === 'note' && $thread['platform'] === 'allegro') { $view['message_types'] = []; }
        } elseif ($thread['platform'] === 'prestashop') {
            $view['meta_rows'] = array_filter(['Status w PrestaShop' => (string) ($meta['ps_status'] ?? ''), 'E-mail' => (string) ($meta['email'] ?? ''), 'Odpowiedź' => 'zapisywana w wątku klienta w sklepie (PrestaShop nie wysyła e-maila dla odpowiedzi z API)'], 'strlen');
        } elseif ($thread['platform'] === 'allegro') {
            $view['message_types'] = AllegroMessages::MESSAGE_TYPES[$thread['kind']] ?? [];
            if ($thread['kind'] === 'claim' && $thread['remote_status'] === 'CLAIM_SUBMITTED') {
                foreach (AllegroMessages::DECISIONS as $code => $label) { $view['decisions'][strpos($code, 'ACCEPTED') === 0 ? 'Uznanie' : 'Odrzucenie'][$code] = $label; }
            }
            if (in_array($thread['kind'], ['dispute', 'claim'], true)) {
                $rows = [
                    'Status w Allegro' => AllegroMessages::ISSUE_STATUSES[$thread['remote_status']] ?? $thread['remote_status'],
                    'Numer' => $meta['reference'] ?? '', 'Podstawa' => AllegroMessages::RIGHTS[$meta['right'] ?? ''] ?? ($meta['right'] ?? ''),
                    'Powód' => trim((AllegroMessages::REASONS[$meta['reason_type'] ?? ''] ?? '').(($meta['reason_description'] ?? '') !== '' ? ' – '.$meta['reason_description'] : '')),
                    'Opis kupującego' => $meta['description'] ?? '',
                    'Oczekiwanie kupującego' => implode(', ', array_map(static function (array $item): string { return (AllegroMessages::EXPECTATIONS[$item['name']] ?? $item['name']).($item['amount'] !== '' ? ' '.$item['amount'].' '.$item['currency'] : ''); }, (array) ($meta['expectations'] ?? []))),
                    'Zwrot produktu' => ($meta['return_required'] ?? null) === null ? ($thread['kind'] === 'claim' ? 'nie zdecydowano' : '') : (!empty($meta['return_required']) ? 'wymagany' : 'niewymagany'),
                    'Oferta' => ($meta['offer_id'] ?? '') !== '' ? $meta['offer_id'].(!empty($meta['quantity']) ? ' (szt. '.$meta['quantity'].')' : '') : '',
                    'Termin decyzji' => $thread['due_at'] ? self::local((string) $thread['due_at']) : '',
                    'Załączniki kupującego' => implode(', ', array_filter((array) ($meta['files'] ?? []))),
                    'Czat' => array_key_exists('chat_active', $meta) ? (!empty($meta['chat_active']) ? 'aktywny' : 'zamknięty – nie można wysyłać wiadomości') : '',
                ];
                $view['meta_rows'] = array_filter($rows, 'strlen');
            } elseif (!empty($meta['offer_id'])) {
                $view['meta_rows'] = ['Oferta' => $meta['offer_id']];
            }
        } elseif (in_array($thread['platform'], ['empik', 'mediamarkt'], true)) {
            if ($thread['kind'] === 'message') {
                foreach ((array) ($meta['participants'] ?? []) as $participant) {
                    if (in_array($participant['type'], ['CUSTOMER', 'OPERATOR'], true)) { $view['recipients'][$participant['type']] = ($participant['type'] === 'CUSTOMER' ? 'Klient' : 'Operator marketplace').($participant['display_name'] !== '' ? ' ('.$participant['display_name'].')' : ''); }
                }
                if (!$view['recipients']) { $view['recipients'] = ['CUSTOMER' => 'Klient']; }
                $view['meta_rows'] = array_filter(['Dotyczy' => $meta['entity_label'] ?? '', 'Wątek' => !empty($meta['operator_thread']) ? 'z operatorem marketplace' : ''], 'strlen');
            } else {
                $view['meta_rows'] = array_filter(['Stan zamówienia' => $meta['order_state'] ?? '', 'Status incydentu' => $thread['remote_closed'] ? 'zamknięty' : 'otwarty'], 'strlen');
                if (!$thread['remote_closed'] && !empty($meta['lines'])) {
                    try { $view['reasons'] = $center->incidentReasons((int) $thread['connection_id']); }
                    catch (\Throwable $e) { $view['reasons_error'] = 'Nie udało się pobrać powodów zamknięcia z marketplace: '.$e->getMessage(); }
                }
            }
        } elseif ($thread['platform'] === 'morele') {
            $view['meta_rows'] = array_filter(['Kategoria' => $meta['category'] ?? '', 'Dotyczy' => ($meta['resource_id'] ?? '') !== '' ? (string) $meta['resource_id'] : ''], 'strlen');
        }
        return $view;
    }

    /** Etykiety czasu dla widoku (Europe/Warsaw) i ostrzeżenie o terminie. */
    private static function decorate(array $thread): array
    {
        $zone = new \DateTimeZone('Europe/Warsaw');
        $thread['last_label'] = '';
        if (!empty($thread['last_message_at'])) {
            $last = (new \DateTimeImmutable((string) $thread['last_message_at'], new \DateTimeZone('UTC')))->setTimezone($zone);
            $thread['last_label'] = $last->format('Y-m-d') === (new \DateTimeImmutable('now', $zone))->format('Y-m-d') ? $last->format('H:i') : $last->format('d.m H:i');
        }
        $thread['due_label'] = !empty($thread['due_at']) && empty($thread['remote_closed']) ? self::local((string) $thread['due_at']) : '';
        $thread['due_soon'] = $thread['due_label'] !== '' && strtotime($thread['due_at'].' UTC') < time() + 2 * 86400;
        return $thread;
    }

    private static function local(string $utc): string
    {
        try { return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Warsaw'))->format('d.m.Y H:i'); }
        catch (\Throwable $e) { return $utc; }
    }

    public function save(): void
    {
        $user = $this->requireModuleWrite('orders');
        $this->requireCsrf();
        $center = $this->center();
        $repo = $center->repo();
        $actor = (string) ($user['name'] ?: $user['email']);
        $operation = (string) ($_POST['operation'] ?? '');
        $back = './index.php?controller=messages'.$this->backQuery();
        try {
            switch ($operation) {
                case 'sync':
                    // Synchronizacja trwa długo (wiele wywołań API) – zwolnij sesję, żeby inne karty nie czekały; setFlash() otworzy ją ponownie.
                    $this->releaseSessionLock();
                    $report =$center->sync(true, (int) ($_POST['connection_id'] ?? 0));
                    $errors = array_filter(array_column($report, 'error'));
                    $threads = array_sum(array_column($report, 'threads'));
                    $auto = array_sum(array_column($report, 'auto'));
                    if (!$report) { $this->setFlash('error', 'Brak aktywnych kont z włączonymi wiadomościami. Podłącz konto w „Konta i import” albo włącz synchronizację w ustawieniach.'); }
                    else { $this->setFlash($errors ? 'error' : 'success', 'Synchronizacja: '.count($report).' kont, zaktualizowane wątki: '.$threads.($auto ? ', autoodpowiedzi: '.$auto : '').'.'.($errors ? ' Błędy: '.implode(' | ', $errors) : '')); }
                    break;
                case 'probe':
                    $this->releaseSessionLock();
                    $account =$center->account((int) ($_POST['connection_id'] ?? 0));
                    if (($account['platform'] ?? '') !== 'morele') { throw new InvalidArgumentException('Diagnostyka dotyczy połączeń Morele.'); }
                    $source = $center->source('morele');
                    if (!$source instanceof MoreleMessages) { throw new InvalidArgumentException('Diagnostyka dotyczy połączeń Morele.'); }
                    // Najpierw kształt odpowiedzi (wątki się zaciągają), a gdy to zawiedzie – sprawdzenie tras.
                    try { $report = $source->diagnoseShape($account); }
                    catch (\Throwable $e) { $report = $e->getMessage().' | '.$source->diagnose($account); }
                    $this->setFlash('success', 'Diagnostyka Morele: '.$report);
                    break;
                case 'reply':
                    $threadId = (int) $_POST['thread_id'];
                    $center->reply($threadId, (string) ($_POST['text'] ?? ''), ['signature' => !empty($_POST['signature']), 'message_type' => (string) ($_POST['message_type'] ?? 'REGULAR'), 'recipients' => (array) ($_POST['recipients'] ?? ['CUSTOMER']), 'status' => ($_POST['after_status'] ?? '') === 'closed' ? 'closed' : 'answered'], $actor);
                    $this->setFlash('success', 'Odpowiedź wysłana.');
                    break;
                case 'status':
                    $repo->setStatus((int) $_POST['thread_id'], (string) ($_POST['status'] ?? ''));
                    $this->setFlash('success', 'Status wątku zmieniony.');
                    break;
                case 'bulk_status':
                    $ids = array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])));
                    if (!$ids) { throw new InvalidArgumentException('Zaznacz wątki.'); }
                    foreach ($ids as $id) { $repo->setStatus($id, (string) ($_POST['status'] ?? '')); }
                    $this->setFlash('success', 'Zmieniono status '.count($ids).' wątków.');
                    break;
                case 'decision':
                    $center->decideClaim((int) $_POST['thread_id'], (string) ($_POST['decision'] ?? ''), (string) ($_POST['message'] ?? ''), (string) ($_POST['refund'] ?? ''), $actor);
                    $this->setFlash('success', 'Decyzja w reklamacji została przekazana do Allegro.');
                    break;
                case 'resolve_incident':
                    $center->resolveIncident((int) $_POST['thread_id'], array_map('strval', (array) ($_POST['lines'] ?? [])), (string) ($_POST['reason'] ?? ''), $actor);
                    $this->setFlash('success', 'Incydent oznaczony jako rozwiązany.');
                    break;
                case 'settings':
                    $platform = (string) ($_POST['platform'] ?? '');
                    $repo->saveSettings($platform, $_POST);
                    $this->setFlash('success', 'Zapisano ustawienia wiadomości: '.(MessageRepository::PLATFORMS[$platform] ?? $platform).'.');
                    break;
                case 'rule_save':
                    $id = $repo->saveRule((int) ($_POST['rule_id'] ?? 0), $_POST);
                    $this->setFlash('success', 'Reguła autoodpowiedzi zapisana.');
                    $back = './index.php?controller=messages&tab=rules&rule='.$id;
                    break;
                case 'rule_toggle': $repo->toggleRule((int) $_POST['rule_id']); break;
                case 'rule_delete': $repo->deleteRule((int) $_POST['rule_id']); $this->setFlash('success', 'Reguła usunięta.'); break;
                case 'rule_move': $repo->moveRule((int) $_POST['rule_id'], (int) ($_POST['direction'] ?? 1)); break;
                default:
                    throw new InvalidArgumentException('Nieznana operacja.');
            }
        } catch (\Throwable $e) {
            $this->setFlash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'Operacja nie powiodła się: '.$e->getMessage());
            if ($operation === 'rule_save') { $back = './index.php?controller=messages&tab=rules&rule='.((int) ($_POST['rule_id'] ?? 0) ?: 'new'); }
        }
        $this->redirect($back);
    }

    /** Powrót do tego samego widoku (zakładka, filtry, wątek) – tylko znane parametry. */
    private function backQuery(): string
    {
        $allowed = ['tab' => '/^(inbox|rules|settings)$/', 'platform' => '/^[a-z]{1,20}$/', 'kind' => '/^[a-z]{1,20}$/', 'status' => '/^[a-z]{1,20}$/', 'connection' => '/^\d{1,10}$/', 'q' => '/^.{0,100}$/u', 'page' => '/^\d{1,5}$/', 'id' => '/^\d{1,12}$/'];
        $params = [];
        parse_str((string) ($_POST['back'] ?? ''), $source);
        foreach ($allowed as $key => $pattern) {
            if (isset($source[$key]) && is_string($source[$key]) && $source[$key] !== '' && preg_match($pattern, $source[$key])) { $params[$key] = $source[$key]; }
        }
        return $params ? '&'.http_build_query($params) : '';
    }
}
