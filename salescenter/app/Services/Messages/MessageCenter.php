<?php

declare(strict_types=1);
namespace App\Services\Messages;

use App\Models\ConnectionRepository;
use App\Models\MessageRepository;
use App\Services\EmpikService;
use App\Services\MediaMarktService;
use InvalidArgumentException;
use RuntimeException;

/**
 * Centrum wiadomości: synchronizacja podłączonych kont (Allegro, Empik, MediaMarkt, Morele),
 * odpowiedzi, decyzje w reklamacjach, incydenty i autoodpowiedzi.
 */
final class MessageCenter
{
    /** Autoodpowiedź tylko na świeże wiadomości – bez zalewania klientów po pierwszym imporcie historii. */
    private const AUTO_MAX_AGE = 72 * 3600;
    private const AUTO_PER_RUN = 30;

    /** @var MessageRepository */
    private $repo;
    /** @var ConnectionRepository */
    private $connections;
    /** @var array<string,MessageSource> */
    private $sources;

    public function __construct(MessageRepository $repo, array $sources = [])
    {
        $this->repo = $repo;
        $this->connections = new ConnectionRepository($repo->db());
        $this->sources = $sources;
    }

    public function repo(): MessageRepository { return $this->repo; }

    public function source(string $platform): MessageSource
    {
        if (!isset($this->sources[$platform])) {
            switch ($platform) {
                case 'allegro': $this->sources[$platform] = new AllegroMessages(); break;
                case 'empik': $this->sources[$platform] = new MiraklMessages(new EmpikService()); break;
                case 'mediamarkt': $this->sources[$platform] = new MiraklMessages(new MediaMarktService()); break;
                case 'morele': $this->sources[$platform] = new MoreleMessages(); break;
                case 'prestashop': $this->sources[$platform] = new PrestaShopMessages(); break;
                case 'notes': $this->sources[$platform] = new OrderNotesSource(); break;
                default: throw new InvalidArgumentException('Ten kanał nie ma API wiadomości.');
            }
        }
        return $this->sources[$platform];
    }

    /** Podłączone konta marketplace z wiadomościami (bez sekretów) – do widoków. */
    public function connectedAccounts(): array
    {
        $platforms = array_keys(MessageRepository::PLATFORMS);
        $rows = $this->repo->db()->fetchAll("SELECT id,platform,name,status FROM om_connections WHERE platform IN ('".implode("','", $platforms)."') ORDER BY platform,name,id");
        $state = $this->repo->syncState();
        foreach ($rows as &$row) { $row['sync'] = $state[(string) $row['id']] ?? []; }
        unset($row);
        return $rows;
    }

    public function account(int $connectionId): array
    {
        $account = $this->connections->credentials($this->connections->find($connectionId));
        if (!isset(MessageRepository::PLATFORMS[$account['platform']])) { throw new InvalidArgumentException('To połączenie nie obsługuje wiadomości.'); }
        return $account;
    }

    /**
     * Synchronizuje konta, których termin minął (albo wszystkie przy $force), i uruchamia autoodpowiedzi.
     * Zwraca raport per połączenie.
     */
    public function sync(bool $force = false, int $onlyConnection = 0): array
    {
        $db = $this->repo->db();
        if (!$db->acquireAdvisoryLock('messages_sync')) { return [['busy' => true, 'error' => 'Synchronizacja wiadomości już trwa – spróbuj za chwilę.']]; }
        $report = [];
        try {
            $settings = $this->repo->settings();
            $states = $this->repo->syncState();
            foreach (array_keys(MessageRepository::PLATFORMS) as $platform) {
                foreach ($this->connections->accountsFor($platform) as $account) {
                    $id = (int) $account['connection_id'];
                    if (($onlyConnection > 0 && $id !== $onlyConnection) || empty($account['is_active']) || empty($settings[$platform]['enabled'])) { continue; }
                    $state = (array) ($states[(string) $id] ?? []);
                    if (!$force && (int) ($state['next_sync'] ?? 0) > time()) { continue; }
                    $report[] = $this->syncAccount($account, $settings[$platform], $state);
                }
            }
        } finally {
            $db->releaseAdvisoryLock('messages_sync');
        }
        return $report;
    }

    /**
     * Ponowne pobranie starszych wiadomości: cofa znacznik czasu ostatniej synchronizacji i każe źródłu
     * odczytać wątki w całości, także te, które lokalnie wyglądają na aktualne.
     */
    public function backfill(int $connectionId, int $days): array
    {
        $account = $this->account($connectionId);
        $platform = (string) $account['platform'];
        $settings = $this->repo->platformSettings($platform);
        if (empty($settings['enabled'])) { throw new InvalidArgumentException('Najpierw włącz synchronizację wiadomości dla: '.(MessageRepository::PLATFORMS[$platform] ?? $platform).'.'); }
        $state = (array) ($this->repo->syncState()[(string) $connectionId] ?? []);
        $since = gmdate('Y-m-d H:i:s', time() - max(1, min(365, $days)) * 86400);
        foreach (['threads_since', 'orders_since'] as $key) { $state[$key] = $since; }
        // Morele nie nadaje wiadomościom identyfikatorów, a pełne pobranie i tak odtworzy każdy wątek –
        // kasujemy więc wcześniej pobrane treści, żeby historia odbudowała się bez duplikatów.
        if ($platform === 'morele') { $this->repo->purgeRemoteMessages($connectionId); }
        $state['force_full'] = 1;
        $state['next_sync'] = 0;
        $this->repo->saveConnectionState($connectionId, $state);
        return $this->sync(true, $connectionId);
    }

    private function syncAccount(array $account, array $settings, array $state): array
    {
        $id = (int) $account['connection_id'];
        $platform = (string) $account['platform'];
        $result = ['connection_id' => $id, 'platform' => $platform, 'name' => (string) $account['name'], 'threads' => 0, 'new' => 0, 'auto' => 0, 'listed' => 0, 'notes' => [], 'error' => null];
        $created = [];
        $emit = function (array $thread) use ($id, $platform, &$result, &$created): void {
            $saved = $this->repo->ingest($id, $platform, $thread);
            $result['threads']++;
            if ($saved['created']) { $result['new']++; $created[] = $saved['id']; }
        };
        $errors = [];
        // Natywne API wiadomości kanału, a następnie uwagi/zwroty z zaimportowanych zamówień.
        $sources = in_array($platform, MessageRepository::NATIVE, true) ? [$platform, 'notes'] : ['notes'];
        foreach ($sources as $sourceName) {
            try {
                $next = $this->source($sourceName)->fetch($account, $settings, $state, $this->repo, $emit);
                foreach ((array) ($next['_open'] ?? []) as $kind => $openIds) { $this->closeMissing($id, $platform, (string) $kind, (array) $openIds); }
                $errors = array_merge($errors, (array) ($next['_errors'] ?? []));
                // Liczba wątków widzianych na liście marketplace i uwagi źródła – w raporcie widać, czy API w ogóle coś zwraca.
                $result['listed'] += (int) ($next['_listed'] ?? 0);
                $result['notes'] = array_merge($result['notes'], (array) ($next['_notes'] ?? []));
                unset($next['_open'], $next['_errors'], $next['_listed'], $next['_notes']);
                $state = $next;
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
        $state['error'] = $errors ? self::hint($platform, implode(' · ', $errors)) : null;
        $result['error'] = $state['error'];
        try { $result['auto'] = $this->autoRespond($account, $settings, $created); }
        catch (\Throwable $e) { $result['error'] = trim(($result['error'] ?? '').' Autoodpowiedzi: '.$e->getMessage()); }
        $state['last_sync'] = MessageRepository::now();
        $state['next_sync'] = time() + (int) $settings['interval'] * 60;
        $this->repo->saveConnectionState($id, $state);
        return $result;
    }

    /** Incydenty Mirakl, których nie ma już na liście otwartych, zostały zamknięte po stronie marketplace. */
    private function closeMissing(int $connectionId, string $platform, string $kind, array $openIds): void
    {
        foreach ($this->repo->openThreads($connectionId, $kind) as $thread) {
            if (in_array($thread['external_id'], $openIds, true)) { continue; }
            $this->repo->ingest($connectionId, $platform, [
                'kind' => $kind, 'external_id' => $thread['external_id'], 'remote_status' => 'INCIDENT_CLOSED', 'closed' => true, 'needs_reply' => false,
                'messages' => [['external_id' => 'incident-closed:'.$thread['last_message_at'], 'author_role' => 'system', 'author_name' => 'Incydent', 'body' => 'Incydent został zamknięty w marketplace.', 'created_at' => MessageRepository::now()]],
            ]);
        }
    }

    /** Czytelna wskazówka dla typowych błędów uprawnień. */
    private static function hint(string $platform, string $message): string
    {
        // Limit z zapasem na wynik diagnostyki Morele doklejany do treści błędu.
        $message = mb_substr(trim($message), 0, 4000, 'UTF-8');
        if ($platform === 'allegro' && preg_match('/\[(401|403)\]/', $message)) {
            return $message.' – Aplikacja Allegro musi mieć uprawnienia „allegro:api:messaging” i „allegro:api:disputes”. Włącz je w apps.developer.allegro.pl i połącz konto ponownie.';
        }
        if ($platform === 'prestashop' && preg_match('/\[(401|403|405)\]/', $message)) {
            return $message.' – W PrestaShop (Parametry zaawansowane → Webservice) nadaj kluczowi uprawnienia GET do customer_threads, customer_messages, customers oraz POST do customer_messages.';
        }
        if ($platform === 'morele' && preg_match('/\[(401|403|404)\]/', $message)) {
            return $message.' – Morele nie udostępniło centrum komunikacji dla tego klucza API. Sprawdź uprawnienia API w panelu Morele albo skontaktuj się z opiekunem.';
        }
        return $message;
    }

    // ---------------------------------------------------------------- działania użytkownika

    public function reply(int $threadId, string $text, array $options, string $actor): void
    {
        $thread = $this->repo->findThread($threadId);
        $text = trim(str_replace("\r\n", "\n", $text));
        if (!empty($options['signature'])) {
            $signature = (string) $this->repo->platformSettings($thread['platform'])['signature'];
            if ($signature !== '' && mb_strpos($text, $signature, 0, 'UTF-8') === false) { $text .= "\n\n".$signature; }
        }
        if ($text === '') { throw new InvalidArgumentException('Wpisz treść odpowiedzi.'); }
        if (!empty($thread['remote_closed']) && $thread['platform'] !== 'allegro') { throw new InvalidArgumentException('Wątek jest zamknięty w marketplace.'); }
        if (!self::canReply($thread)) { throw new InvalidArgumentException($thread['kind'] === 'return' ? 'Zwroty ERLI obsłuż w panelu ERLI – API nie pozwala na odpowiedź.' : 'Ten kanał nie udostępnia wysyłania wiadomości przez API – odpowiedz klientowi innym kanałem i oznacz wątek jako obsłużony.'); }
        $account = $this->account((int) $thread['connection_id']);
        $source = in_array($thread['kind'], ['note', 'return'], true) ? $this->source('notes') : $this->source($thread['platform']);
        $remoteId = $source->reply($account, $thread, $text, $options, $this->repo);
        $this->repo->recordOutgoing($threadId, $text, $actor, !empty($options['auto']) ? 'auto' : 'user', isset($options['rule_id']) ? (int) $options['rule_id'] : null, $remoteId);
        $this->repo->markReplied($threadId, (string) ($options['status'] ?? 'answered'));
    }

    /** Czy z SalesCenter można odpowiedzieć w tym wątku (kanał ma API wysyłki). */
    public static function canReply(array $thread): bool
    {
        if (in_array($thread['kind'], ['note', 'return'], true)) { return OrderNotesSource::canReply((string) $thread['platform'], (string) $thread['kind']); }
        return in_array($thread['platform'], MessageRepository::NATIVE, true);
    }

    public function decideClaim(int $threadId, string $decision, string $message, string $refund, string $actor): void
    {
        $thread = $this->repo->findThread($threadId);
        if ($thread['platform'] !== 'allegro') { throw new InvalidArgumentException('Decyzje w reklamacjach obsługuje Allegro.'); }
        $account = $this->account((int) $thread['connection_id']);
        $currency = (string) ($thread['meta']['expectations'][0]['currency'] ?? 'PLN');
        $source = $this->source('allegro');
        if (!$source instanceof AllegroMessages) { throw new RuntimeException('Nieprawidłowe źródło Allegro.'); }
        $source->decide($account, $thread, $decision, $message, $refund, $currency);
        $label = AllegroMessages::DECISIONS[$decision];
        $this->repo->recordOutgoing($threadId, 'Decyzja: '.$label.($decision === 'ACCEPTED_PARTIAL_REFUND' ? ' ('.$refund.' '.$currency.')' : '')."\n\n".trim($message), $actor, 'user', null, 'decision:'.$decision.':'.MessageRepository::now());
        $this->repo->updateMeta($threadId, ['decision' => $decision, 'decision_at' => MessageRepository::now(), 'decision_by' => $actor]);
        $this->repo->markReplied($threadId, 'answered');
    }

    public function incidentReasons(int $connectionId): array
    {
        $state = (array) ($this->repo->syncState()[(string) $connectionId] ?? []);
        if (!empty($state['incident_reasons']) && (int) ($state['incident_reasons_at'] ?? 0) > time() - 86400) { return (array) $state['incident_reasons']; }
        $account = $this->account($connectionId);
        $source = $this->source($account['platform']);
        if (!$source instanceof MiraklMessages) { return []; }
        $reasons = $source->incidentReasons($account);
        $state['incident_reasons'] = $reasons;
        $state['incident_reasons_at'] = time();
        $this->repo->saveConnectionState($connectionId, $state);
        return $reasons;
    }

    public function resolveIncident(int $threadId, array $lineIds, string $reason, string $actor): void
    {
        $thread = $this->repo->findThread($threadId);
        $account = $this->account((int) $thread['connection_id']);
        $source = $this->source($thread['platform']);
        if (!$source instanceof MiraklMessages) { throw new InvalidArgumentException('Incydenty obsługują Empik i MediaMarkt.'); }
        $done = $source->resolveIncident($account, $thread, $lineIds, $reason);
        $labels = $this->incidentReasonsSafe((int) $thread['connection_id']);
        $this->repo->recordOutgoing($threadId, 'Oznaczono incydent jako rozwiązany ('.count($done).' poz.). Powód: '.($labels[$reason] ?? $reason).'.', $actor, 'user', null, 'resolve:'.implode(',', $done).':'.MessageRepository::now(), 'system');
        $remaining = array_values(array_filter((array) ($thread['meta']['lines'] ?? []), static function (array $line) use ($done): bool { return !in_array($line['id'], $done, true); }));
        $this->repo->updateMeta($threadId, ['lines' => $remaining]);
        $this->repo->markReplied($threadId, $remaining ? 'answered' : 'closed');
    }

    private function incidentReasonsSafe(int $connectionId): array
    {
        try { return $this->incidentReasons($connectionId); } catch (\Throwable $e) { return []; }
    }

    /** Otwarcie wątku: nowy → do odpowiedzi; Allegro opcjonalnie oznacza wątek jako przeczytany. */
    public function opened(array $thread): void
    {
        if ($thread['status'] === 'new') { $this->repo->setStatus((int) $thread['id'], $thread['needs_reply'] ? 'waiting' : 'answered'); }
        if ($thread['platform'] === 'allegro' && $thread['kind'] === 'message' && empty($thread['meta']['read']) && !empty($this->repo->platformSettings('allegro')['mark_read'])) {
            try {
                $source = $this->source('allegro');
                if ($source instanceof AllegroMessages) { $source->markRead($this->account((int) $thread['connection_id']), $thread); }
                $this->repo->updateMeta((int) $thread['id'], ['read' => true]);
            } catch (\Throwable $e) { /* Oznaczenie przeczytania nie blokuje podglądu. */ }
        }
    }

    // ---------------------------------------------------------------- autoodpowiedzi

    /** Uruchamia reguły dla wątków połączenia czekających na odpowiedź; zwraca liczbę wysłanych. */
    public function autoRespond(array $account, array $settings, array $createdIds = []): int
    {
        if (empty($settings['autoresponder'])) { return 0; }
        $platform = (string) $account['platform'];
        $connectionId = (int) $account['connection_id'];
        $rules = array_values(array_filter($this->repo->rules(true), static function (array $rule) use ($platform, $connectionId): bool {
            return in_array($rule['platform'], ['', $platform], true) && in_array((int) $rule['connection_id'], [0, $connectionId], true);
        }));
        if (!$rules) { return 0; }
        $sent = 0;
        foreach (array_slice($this->repo->awaitingThreads($connectionId), 0, self::AUTO_PER_RUN) as $thread) {
            $trigger = $this->repo->lastCustomerMessage((int) $thread['id']);
            if (!$trigger || !self::canReply($thread)) { continue; }
            foreach ($rules as $rule) {
                $key = $this->ruleMatches($rule, $thread, $trigger, $settings, in_array((int) $thread['id'], $createdIds, true));
                if ($key === null || $this->repo->ruleRan((int) $rule['id'], (int) $thread['id'], $key)) { continue; }
                $cooldown = $this->repo->lastAutoReplyAt((int) $thread['id']);
                if ($cooldown !== '' && strtotime($cooldown.' UTC') > time() - (int) $rule['cooldown_hours'] * 3600) { continue; }
                try {
                    $this->reply((int) $thread['id'], $this->render((string) $rule['template'], $thread, $account, $settings), ['auto' => true, 'rule_id' => (int) $rule['id'], 'status' => $rule['after_status'], 'topic' => $thread['subject']], 'Autoodpowiedź: '.$rule['name']);
                    $this->repo->logRun((int) $rule['id'], (int) $thread['id'], $key, 'sent');
                    $sent++;
                } catch (\Throwable $e) {
                    $this->repo->logRun((int) $rule['id'], (int) $thread['id'], $key, 'error', $e->getMessage());
                }
                break;
            }
        }
        return $sent;
    }

    /** Klucz uruchomienia (jedna odpowiedź reguły na wiadomość/sprawę) albo null, gdy reguła nie pasuje. */
    public function ruleMatches(array $rule, array $thread, array $trigger, array $settings, bool $createdNow = false): ?string
    {
        if (!in_array($thread['kind'], (array) $rule['kinds'], true)) { return null; }
        $at = (string) $trigger['created_at'];
        $time = (int) strtotime($at.' UTC');
        if ($at < (string) $rule['active_since'] || $time < time() - self::AUTO_MAX_AGE) { return null; }
        if ($time > time() - (int) $rule['delay_minutes'] * 60) { return null; }
        $keywords = array_filter(array_map('trim', explode(',', (string) $rule['keywords'])), 'strlen');
        if ($keywords) {
            $haystack = mb_strtolower($thread['subject'].' '.$trigger['body'], 'UTF-8');
            $found = false;
            foreach ($keywords as $keyword) { if (mb_strpos($haystack, mb_strtolower($keyword, 'UTF-8')) !== false) { $found = true; break; } }
            if (!$found) { return null; }
        }
        $key = 'm:'.$trigger['id'];
        switch ($rule['trigger_name']) {
            case 'first_message':
                return $this->repo->hasSellerMessageBefore((int) $thread['id'], $at) ? null : $key;
            case 'outside_hours':
                return self::withinHours($settings['hours'] ?? [], $time) ? null : $key;
            case 'new_case':
                if (!in_array($thread['kind'], ['dispute', 'claim', 'incident'], true) || $this->repo->hasSellerMessageBefore((int) $thread['id'], $at)) { return null; }
                return (string) $thread['created_at'] >= (string) $rule['active_since'] || $createdNow ? 'case' : null;
            case 'any_message':
            case 'no_reply':
                return $key;
        }
        return null;
    }

    public static function withinHours(array $hours, int $timestamp): bool
    {
        $local = (new \DateTimeImmutable('@'.$timestamp))->setTimezone(new \DateTimeZone('Europe/Warsaw'));
        if (!in_array((int) $local->format('N'), array_map('intval', (array) ($hours['days'] ?? [1, 2, 3, 4, 5])), true)) { return false; }
        $now = $local->format('H:i');
        $from = (string) ($hours['from'] ?? '08:00');
        $to = (string) ($hours['to'] ?? '16:00');
        return $from <= $to ? ($now >= $from && $now < $to) : ($now >= $from || $now < $to);
    }

    public const PLACEHOLDERS = [
        '{klient}' => 'nazwa / login klienta', '{zamowienie}' => 'numer zamówienia w marketplace', '{temat}' => 'temat wątku',
        '{numer}' => 'numer reklamacji Allegro', '{platforma}' => 'nazwa marketplace', '{konto}' => 'nazwa połączenia',
        '{godziny}' => 'godziny pracy z ustawień', '{podpis}' => 'podpis z ustawień marketplace',
    ];

    public function render(string $template, array $thread, array $account, array $settings): string
    {
        $days = ['1' => 'pon.', '2' => 'wt.', '3' => 'śr.', '4' => 'czw.', '5' => 'pt.', '6' => 'sob.', '7' => 'niedz.'];
        $hours = (array) ($settings['hours'] ?? []);
        $dayList = array_map('intval', (array) ($hours['days'] ?? []));
        $range = $dayList === [1, 2, 3, 4, 5] ? 'pon.–pt.' : implode(', ', array_map(static function (int $day) use ($days): string { return $days[(string) $day]; }, $dayList));
        $text = strtr($template, [
            '{klient}' => (string) ($thread['customer_name'] ?: $thread['customer_login']), '{zamowienie}' => (string) $thread['order_external_id'],
            '{temat}' => (string) $thread['subject'], '{numer}' => (string) ($thread['meta']['reference'] ?? ''),
            '{platforma}' => MessageRepository::PLATFORMS[$thread['platform']] ?? $thread['platform'], '{konto}' => (string) ($account['name'] ?? ''),
            '{godziny}' => trim($range.' '.($hours['from'] ?? '').'–'.($hours['to'] ?? '')), '{podpis}' => (string) ($settings['signature'] ?? ''),
        ]);
        return trim(preg_replace("/[ \t]+\n/", "\n", $text) ?? $text);
    }
}
