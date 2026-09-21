<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;

/**
 * Centrum wiadomości firmy: wątki z marketplace'ów (wiadomości, dyskusje, reklamacje, incydenty),
 * ich wiadomości, reguły autoodpowiedzi i ustawienia per marketplace (om_settings: messages, messages_state).
 *
 * Status lokalny wątku (obsługa w SalesCenter):
 *  new – nowy, nieotwarty; waiting – klient czeka na odpowiedź; auto – wysłano autoodpowiedź;
 *  answered – odpowiedziano; closed – zamknięty (także zamknięty po stronie marketplace).
 */
final class MessageRepository
{
    /** Wszystkie kanały z „Konta i import”. */
    public const PLATFORMS = [
        'allegro' => 'Allegro', 'empik' => 'Empik', 'mediamarkt' => 'MediaMarkt', 'erli' => 'ERLI', 'morele' => 'Morele', 'temu' => 'Temu',
        'prestashop' => 'PrestaShop', 'woocommerce' => 'WooCommerce', 'altreo' => 'Altreo.pl', 'api' => 'Własny sklep',
    ];

    /**
     * Rodzaje wątków każdego kanału (kolejność = kolejność sekcji w menu).
     * note – uwaga kupującego do zamówienia (z importu zamówień), return – zwrot zgłoszony w marketplace.
     */
    public const KINDS = [
        'allegro' => ['message', 'dispute', 'claim', 'note'],
        'empik' => ['message', 'incident', 'note'],
        'mediamarkt' => ['message', 'incident', 'note'],
        'erli' => ['note', 'return'],
        'morele' => ['message', 'note'],
        'temu' => ['note'],
        'prestashop' => ['message'],
        'woocommerce' => ['note'],
        'altreo' => ['note'],
        'api' => ['note'],
    ];

    public const KIND_LABELS = ['message' => 'Wiadomości', 'dispute' => 'Dyskusje', 'claim' => 'Reklamacje', 'incident' => 'Incydenty', 'return' => 'Zwroty', 'note' => 'Uwagi do zamówień'];

    /** Kanały z natywnym API wiadomości (pozostałe mają tylko wątki z danych zamówień). */
    public const NATIVE = ['allegro', 'empik', 'mediamarkt', 'morele', 'prestashop'];

    public const STATUSES = [
        'new' => ['Nowa', '#6366f1'],
        'waiting' => ['Do odpowiedzi', '#f59e0b'],
        'auto' => ['Autoodpowiedź', '#0ea5e9'],
        'answered' => ['Odpowiedziano', '#10b981'],
        'closed' => ['Zamknięta', '#64748b'],
    ];

    /** Statusy wymagające działania – licznik w menu i domyślny filtr skrzynki. */
    public const OPEN_STATUSES = ['new', 'waiting'];

    /** @var Database */
    private $db;

    public function __construct(Database $db) { $this->db = $db; }

    public function db(): Database { return $this->db; }

    public function ensureSchema(): void
    {
        $sqlite = $this->db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $id = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $suffix = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';
        $tables = [
            'om_msg_threads' => "id $id, connection_id BIGINT NOT NULL, platform VARCHAR(20) NOT NULL, kind VARCHAR(20) NOT NULL, external_id VARCHAR(190) NOT NULL, subject VARCHAR(500) NOT NULL DEFAULT '', customer_name VARCHAR(255) NOT NULL DEFAULT '', customer_login VARCHAR(190) NOT NULL DEFAULT '', order_external_id VARCHAR(190) NOT NULL DEFAULT '', order_id BIGINT NULL, status VARCHAR(20) NOT NULL DEFAULT 'new', remote_status VARCHAR(60) NOT NULL DEFAULT '', needs_reply INTEGER NOT NULL DEFAULT 0, remote_closed INTEGER NOT NULL DEFAULT 0, message_count INTEGER NOT NULL DEFAULT 0, last_message_at VARCHAR(30) NULL, last_customer_at VARCHAR(30) NULL, last_preview VARCHAR(300) NOT NULL DEFAULT '', last_author VARCHAR(20) NOT NULL DEFAULT '', due_at VARCHAR(30) NULL, meta_json LONGTEXT NULL, created_at VARCHAR(30) NOT NULL, updated_at VARCHAR(30) NOT NULL, UNIQUE(connection_id, kind, external_id)",
            'om_msg_messages' => "id $id, thread_id BIGINT NOT NULL, external_id VARCHAR(190) NOT NULL, author_role VARCHAR(20) NOT NULL, author_name VARCHAR(255) NOT NULL DEFAULT '', body LONGTEXT NOT NULL, attachments_json TEXT NULL, source VARCHAR(20) NOT NULL DEFAULT 'remote', actor VARCHAR(150) NOT NULL DEFAULT '', rule_id BIGINT NULL, created_at VARCHAR(30) NOT NULL, UNIQUE(thread_id, external_id)",
            'om_msg_rules' => "id $id, name VARCHAR(150) NOT NULL, enabled INTEGER NOT NULL DEFAULT 1, position INTEGER NOT NULL DEFAULT 0, platform VARCHAR(20) NOT NULL DEFAULT '', connection_id BIGINT NOT NULL DEFAULT 0, kinds_json TEXT NOT NULL, trigger_name VARCHAR(30) NOT NULL, keywords VARCHAR(1000) NOT NULL DEFAULT '', delay_minutes INTEGER NOT NULL DEFAULT 0, cooldown_hours INTEGER NOT NULL DEFAULT 24, template TEXT NOT NULL, after_status VARCHAR(20) NOT NULL DEFAULT 'auto', active_since VARCHAR(30) NOT NULL, created_at VARCHAR(30) NOT NULL, updated_at VARCHAR(30) NOT NULL",
            'om_msg_rule_runs' => "id $id, rule_id BIGINT NOT NULL, thread_id BIGINT NOT NULL, trigger_key VARCHAR(190) NOT NULL, result VARCHAR(20) NOT NULL, message TEXT NULL, created_at VARCHAR(30) NOT NULL, UNIQUE(rule_id, thread_id, trigger_key)",
        ];
        foreach ($tables as $name => $columns) { $this->db->query("CREATE TABLE IF NOT EXISTS $name ($columns)$suffix"); }
        foreach ([
            'om_msg_threads' => ['om_msg_thread_inbox' => 'status,last_message_at', 'om_msg_thread_source' => 'platform,kind,status', 'om_msg_thread_order' => 'order_id'],
            'om_msg_messages' => ['om_msg_message_thread' => 'thread_id,created_at'],
            'om_msg_rule_runs' => ['om_msg_rule_run_date' => 'created_at'],
        ] as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if ($sqlite) { $this->db->query("CREATE INDEX IF NOT EXISTS $name ON $table ($columns)"); continue; }
                if ($this->db->fetch("SHOW INDEX FROM $table WHERE Key_name=:name", ['name' => $name])) { continue; }
                try { $this->db->query("CREATE INDEX $name ON $table ($columns)"); }
                catch (\PDOException $e) { if ((int) ($e->errorInfo[1] ?? 0) !== 1061) { throw $e; } }
            }
        }
    }

    public static function now(): string { return gmdate('Y-m-d H:i:s'); }

    /**
     * Data z API (ISO 8601 ze strefą albo „Y-m-d H:i:s”) → UTC „Y-m-d H:i:s”; pusty tekst gdy brak.
     * Daty bez strefy są w $plainZone (wewnętrznie zawsze UTC; Morele podaje czas polski).
     */
    public static function date($value, string $plainZone = 'UTC'): string
    {
        $value = trim((string) $value);
        if ($value === '') { return ''; }
        try {
            $date = new \DateTimeImmutable($value, new \DateTimeZone($plainZone));
        } catch (\Throwable $e) { return ''; }
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    // ---------------------------------------------------------------- ustawienia

    public function settings(): array
    {
        $stored = $this->setting('messages');
        $result = [];
        foreach (array_keys(self::PLATFORMS) as $platform) {
            $result[$platform] = self::normalizeSettings($platform, (array) ($stored[$platform] ?? []));
        }
        return $result;
    }

    public function platformSettings(string $platform): array
    {
        return $this->settings()[$platform] ?? self::normalizeSettings($platform, []);
    }

    public function saveSettings(string $platform, array $input): void
    {
        if (!isset(self::PLATFORMS[$platform])) { throw new InvalidArgumentException('Nieznany marketplace.'); }
        $stored = $this->setting('messages');
        $stored[$platform] = self::normalizeSettings($platform, $input);
        $this->saveSetting('messages', $stored);
    }

    public static function normalizeSettings(string $platform, array $input): array
    {
        $flag = static function (string $key, int $default) use ($input): int { return array_key_exists($key, $input) ? (!empty($input[$key]) ? 1 : 0) : $default; };
        $time = static function (string $key, string $default) use ($input): string {
            $value = trim((string) ($input['hours'][$key] ?? $input['hours_'.$key] ?? ''));
            return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : $default;
        };
        $days = $input['hours']['days'] ?? $input['hours_days'] ?? null;
        $days = is_array($days) ? array_values(array_unique(array_filter(array_map('intval', $days), static function (int $day): bool { return $day >= 1 && $day <= 7; }))) : [1, 2, 3, 4, 5];
        sort($days);
        $settings = [
            'enabled' => $flag('enabled', 1),
            'sync_messages' => $flag('sync_messages', 1),
            'interval' => max(2, min(120, (int) ($input['interval'] ?? 5))),
            'history_days' => max(1, min(60, (int) ($input['history_days'] ?? 14))),
            'autoresponder' => $flag('autoresponder', 0),
            'signature' => mb_substr(trim((string) ($input['signature'] ?? '')), 0, 500, 'UTF-8'),
            'hours' => ['days' => $days, 'from' => $time('from', '08:00'), 'to' => $time('to', '16:00')],
        ];
        if ($platform !== 'prestashop') { $settings['sync_notes'] = $flag('sync_notes', 1); }
        if ($platform === 'erli') { $settings['sync_returns'] = $flag('sync_returns', 1); }
        if ($platform === 'prestashop') { $settings['employee_id'] = max(1, min(99999, (int) ($input['employee_id'] ?? 1))); }
        if ($platform === 'allegro') {
            $settings['sync_issues'] = $flag('sync_issues', 1);
            $settings['mark_read'] = $flag('mark_read', 1);
        }
        if (in_array($platform, ['empik', 'mediamarkt'], true)) {
            $settings['sync_incidents'] = $flag('sync_incidents', 1);
            $settings['operator_threads'] = $flag('operator_threads', 1);
        }
        return $settings;
    }

    /** Stan synchronizacji połączeń: [connection_id => ['last_sync'=>…, 'next_sync'=>int, 'error'=>…, …]]. */
    public function syncState(): array { return $this->setting('messages_state'); }

    public function saveConnectionState(int $connectionId, array $state): void
    {
        $all = $this->setting('messages_state');
        $all[(string) $connectionId] = $state;
        $this->saveSetting('messages_state', $all);
    }

    private function setting(string $key): array
    {
        $value = $this->db->fetchColumn('SELECT value_json FROM om_settings WHERE setting_key=:k', ['k' => $key]);
        return $value ? (json_decode((string) $value, true) ?: []) : [];
    }

    private function saveSetting(string $key, array $value): void
    {
        $this->db->transaction(function () use ($key, $value) {
            $this->db->delete('om_settings', 'setting_key=:k', ['k' => $key]);
            $this->db->insert('om_settings', ['setting_key' => $key, 'value_json' => OrderRepository::json($value)]);
        });
    }

    // ---------------------------------------------------------------- wątki

    public function findThread(int $id): array
    {
        $row = $this->db->fetch('SELECT * FROM om_msg_threads WHERE id=:id', ['id' => $id]);
        if (!$row) { throw new InvalidArgumentException('Nie znaleziono wątku.'); }
        return self::hydrate($row);
    }

    public function findByExternal(int $connectionId, string $kind, string $externalId): ?array
    {
        $row = $this->db->fetch('SELECT * FROM om_msg_threads WHERE connection_id=:c AND kind=:k AND external_id=:e', ['c' => $connectionId, 'k' => $kind, 'e' => $externalId]);
        return $row ? self::hydrate($row) : null;
    }

    /** Otwarte lokalnie wątki danego rodzaju (np. incydenty do sprawdzenia, czy nadal są otwarte). */
    public function openThreads(int $connectionId, string $kind): array
    {
        return array_map([self::class, 'hydrate'], $this->db->fetchAll('SELECT * FROM om_msg_threads WHERE connection_id=:c AND kind=:k AND remote_closed=0', ['c' => $connectionId, 'k' => $kind]));
    }

    private static function hydrate(array $row): array
    {
        $row['meta'] = json_decode((string) ($row['meta_json'] ?? ''), true) ?: [];
        unset($row['meta_json']);
        return $row;
    }

    /**
     * Zapisuje wątek pobrany z marketplace i jego wiadomości; wylicza status lokalny.
     * $remote: kind, external_id, subject, customer_name, customer_login, order_external_id, remote_status,
     *          needs_reply, closed, last_message_at, due_at, meta, messages (lista albo null = bez zmian).
     * Zwraca ['id'=>…, 'created'=>bool, 'new_customer_messages'=>int].
     */
    public function ingest(int $connectionId, string $platform, array $remote): array
    {
        return $this->db->transaction(function () use ($connectionId, $platform, $remote) {
            $now = self::now();
            $kind = (string) $remote['kind'];
            $externalId = mb_substr((string) $remote['external_id'], 0, 190, 'UTF-8');
            $current = $this->findByExternal($connectionId, $kind, $externalId);
            $closed = !empty($remote['closed']);
            $needsReply = !$closed && !empty($remote['needs_reply']);
            $data = [
                'subject' => mb_substr(trim((string) ($remote['subject'] ?? '')), 0, 500, 'UTF-8'),
                'customer_name' => mb_substr(trim((string) ($remote['customer_name'] ?? '')), 0, 255, 'UTF-8'),
                'customer_login' => mb_substr(trim((string) ($remote['customer_login'] ?? '')), 0, 190, 'UTF-8'),
                'order_external_id' => mb_substr(trim((string) ($remote['order_external_id'] ?? '')), 0, 190, 'UTF-8'),
                'remote_status' => mb_substr((string) ($remote['remote_status'] ?? ''), 0, 60, 'UTF-8'),
                'needs_reply' => $needsReply ? 1 : 0,
                'remote_closed' => $closed ? 1 : 0,
                'due_at' => ($due = self::date($remote['due_at'] ?? '')) !== '' ? $due : null,
                'updated_at' => $now,
            ];
            if (isset($remote['meta'])) { $data['meta_json'] = OrderRepository::json(array_merge($current['meta'] ?? [], (array) $remote['meta'])); }
            foreach (['subject', 'customer_name', 'customer_login', 'order_external_id'] as $keep) {
                if ($data[$keep] === '' && $current) { $data[$keep] = (string) $current[$keep]; }
            }
            if ($data['order_external_id'] !== '') { $data['order_id'] = $this->localOrderId($platform, $connectionId, $data['order_external_id']); }
            if ($current) {
                $threadId = (int) $current['id'];
                $this->db->update('om_msg_threads', $data, 'id=:id', ['id' => $threadId]);
            } else {
                $threadId = (int) $this->db->insert('om_msg_threads', $data + ['connection_id' => $connectionId, 'platform' => $platform, 'kind' => $kind, 'external_id' => $externalId, 'status' => 'new', 'created_at' => $now]);
            }
            $newCustomer = 0;
            if (is_array($remote['messages'] ?? null)) {
                foreach ($remote['messages'] as $message) {
                    if ($this->addMessage($threadId, $message) && ($message['author_role'] ?? '') === 'customer') { $newCustomer++; }
                }
            }
            $this->refreshSummary($threadId, (string) ($remote['last_message_at'] ?? ''));
            $status = $this->nextStatus($current, $closed, $needsReply, $newCustomer);
            if ($status !== null) { $this->db->update('om_msg_threads', ['status' => $status], 'id=:id', ['id' => $threadId]); }
            return ['id' => $threadId, 'created' => $current === null, 'new_customer_messages' => $newCustomer];
        });
    }

    /** Status lokalny po synchronizacji; null = bez zmian (zachowuje ręczne ustawienie). */
    private function nextStatus(?array $current, bool $closed, bool $needsReply, int $newCustomer): ?string
    {
        if ($closed) { return 'closed'; }
        if (!$current) { return $needsReply ? 'new' : 'answered'; }
        $status = (string) $current['status'];
        if ($needsReply) {
            if ($newCustomer > 0 && in_array($status, ['answered', 'auto', 'closed'], true)) { return 'waiting'; }
            if ($status === 'closed' && !empty($current['remote_closed'])) { return 'waiting'; }
            return null;
        }
        // Odpowiedź wysłana poza SalesCenter (np. w panelu marketplace).
        return in_array($status, ['new', 'waiting'], true) ? 'answered' : null;
    }

    /** Dodaje wiadomość, jeśli jeszcze jej nie ma; zwraca true dla nowej. */
    public function addMessage(int $threadId, array $message): bool
    {
        $externalId = mb_substr(trim((string) ($message['external_id'] ?? '')), 0, 190, 'UTF-8');
        $createdAt = self::date($message['created_at'] ?? '') ?: self::now();
        $body = trim((string) ($message['body'] ?? ''));
        if ($externalId === '') { $externalId = 'h:'.sha1($createdAt.'|'.($message['author_name'] ?? '').'|'.$body); }
        if ($this->db->fetchColumn('SELECT id FROM om_msg_messages WHERE thread_id=:t AND external_id=:e', ['t' => $threadId, 'e' => $externalId])) { return false; }
        // Wiadomość wysłana z SalesCenter wraca z API z identyfikatorem marketplace – nie dublujemy jej.
        if (($message['author_role'] ?? '') === 'seller' && $body !== '') {
            $local = $this->db->fetch("SELECT id,body FROM om_msg_messages WHERE thread_id=:t AND source IN ('user','auto') AND external_id LIKE 'local:%' ORDER BY id DESC LIMIT 5", ['t' => $threadId]);
            if ($local && self::sameText((string) $local['body'], $body)) {
                $this->db->update('om_msg_messages', ['external_id' => $externalId], 'id=:id', ['id' => $local['id']]);
                return false;
            }
        }
        $this->db->insert('om_msg_messages', [
            'thread_id' => $threadId, 'external_id' => $externalId,
            'author_role' => in_array($message['author_role'] ?? '', ['customer', 'seller', 'operator', 'system'], true) ? $message['author_role'] : 'system',
            'author_name' => mb_substr(trim((string) ($message['author_name'] ?? '')), 0, 255, 'UTF-8'),
            'body' => $body, 'attachments_json' => !empty($message['attachments']) ? OrderRepository::json(array_values((array) $message['attachments'])) : null,
            'source' => (string) ($message['source'] ?? 'remote'), 'actor' => mb_substr((string) ($message['actor'] ?? ''), 0, 150, 'UTF-8'),
            'rule_id' => isset($message['rule_id']) ? (int) $message['rule_id'] : null, 'created_at' => $createdAt,
        ]);
        return true;
    }

    private static function sameText(string $a, string $b): bool
    {
        $normalize = static function (string $text): string { return preg_replace('/\s+/u', ' ', trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? ''; };
        return $normalize($a) === $normalize($b);
    }

    /** Wiadomość wysłana z SalesCenter (ręcznie lub autoodpowiedź). */
    public function recordOutgoing(int $threadId, string $body, string $actor, string $source = 'user', ?int $ruleId = null, string $externalId = '', string $role = 'seller'): void
    {
        $this->addMessage($threadId, ['external_id' => $externalId !== '' ? $externalId : 'local:'.bin2hex(random_bytes(8)), 'author_role' => $role, 'author_name' => $actor, 'body' => $body, 'source' => $source, 'actor' => $actor, 'rule_id' => $ruleId, 'created_at' => self::now()]);
        $this->refreshSummary($threadId);
    }

    public function refreshSummary(int $threadId, string $remoteLast = ''): void
    {
        $last = $this->db->fetch('SELECT author_role,body,created_at FROM om_msg_messages WHERE thread_id=:t ORDER BY created_at DESC,id DESC LIMIT 1', ['t' => $threadId]);
        $lastCustomer = (string) $this->db->fetchColumn("SELECT MAX(created_at) FROM om_msg_messages WHERE thread_id=:t AND author_role='customer'", ['t' => $threadId]);
        $remoteLast = self::date($remoteLast);
        $lastAt = max((string) ($last['created_at'] ?? ''), $remoteLast);
        $preview = $last ? preg_replace('/\s+/u', ' ', trim(strip_tags((string) $last['body']))) : '';
        $this->db->update('om_msg_threads', [
            'message_count' => (int) $this->db->fetchColumn('SELECT COUNT(*) FROM om_msg_messages WHERE thread_id=:t', ['t' => $threadId]),
            'last_message_at' => $lastAt !== '' ? $lastAt : null, 'last_customer_at' => $lastCustomer !== '' ? $lastCustomer : null,
            'last_preview' => mb_substr((string) $preview, 0, 300, 'UTF-8'), 'last_author' => (string) ($last['author_role'] ?? ''),
        ], 'id=:id', ['id' => $threadId]);
    }

    public function setStatus(int $threadId, string $status): void
    {
        if (!isset(self::STATUSES[$status])) { throw new InvalidArgumentException('Nieznany status wiadomości.'); }
        $this->db->update('om_msg_threads', ['status' => $status, 'updated_at' => self::now()], 'id=:id', ['id' => $threadId]);
    }

    /** Po wysłaniu odpowiedzi: marketplace nie czeka już na sprzedawcę. */
    public function markReplied(int $threadId, string $status): void
    {
        $this->db->update('om_msg_threads', ['status' => $status, 'needs_reply' => 0, 'updated_at' => self::now()], 'id=:id', ['id' => $threadId]);
    }

    public function updateMeta(int $threadId, array $meta): void
    {
        $thread = $this->findThread($threadId);
        $this->db->update('om_msg_threads', ['meta_json' => OrderRepository::json(array_merge($thread['meta'], $meta)), 'updated_at' => self::now()], 'id=:id', ['id' => $threadId]);
    }

    public function messages(int $threadId): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM om_msg_messages WHERE thread_id=:t ORDER BY created_at,id', ['t' => $threadId]);
        foreach ($rows as &$row) { $row['attachments'] = json_decode((string) ($row['attachments_json'] ?? ''), true) ?: []; unset($row['attachments_json']); }
        unset($row);
        return $rows;
    }

    public function lastCustomerMessage(int $threadId): ?array
    {
        $row = $this->db->fetch("SELECT * FROM om_msg_messages WHERE thread_id=:t AND author_role IN ('customer','system') ORDER BY created_at DESC,id DESC LIMIT 1", ['t' => $threadId]);
        return $row ?: null;
    }

    public function hasSellerMessageBefore(int $threadId, string $before): bool
    {
        return (bool) $this->db->fetchColumn("SELECT COUNT(*) FROM om_msg_messages WHERE thread_id=:t AND author_role='seller' AND created_at<=:b", ['t' => $threadId, 'b' => $before]);
    }

    public function lastAutoReplyAt(int $threadId): string
    {
        return (string) $this->db->fetchColumn("SELECT MAX(created_at) FROM om_msg_messages WHERE thread_id=:t AND source='auto'", ['t' => $threadId]);
    }

    /** Wątki czekające na sprzedawcę – kandydaci dla autoodpowiedzi. */
    public function awaitingThreads(int $connectionId): array
    {
        return array_map([self::class, 'hydrate'], $this->db->fetchAll("SELECT * FROM om_msg_threads WHERE connection_id=:c AND needs_reply=1 AND remote_closed=0 AND status IN ('new','waiting') ORDER BY last_message_at", ['c' => $connectionId]));
    }

    private function localOrderId(string $platform, int $connectionId, string $externalId): ?int
    {
        $id = $this->db->fetchColumn('SELECT o.id FROM om_orders o JOIN om_accounts a ON a.id=o.account_id WHERE a.platform=:p AND a.source_id=:s AND o.external_id=:e', ['p' => $platform, 's' => $connectionId, 'e' => $externalId]);
        return $id ? (int) $id : null;
    }

    /** Wątki powiązane z zamówieniem (do karty zamówienia i incydentów Mirakl). */
    public function threadsForOrder(int $connectionId, string $orderExternalId, string $kind = ''): array
    {
        $sql = 'SELECT * FROM om_msg_threads WHERE connection_id=:c AND order_external_id=:o';
        $params = ['c' => $connectionId, 'o' => $orderExternalId];
        if ($kind !== '') { $sql .= ' AND kind=:k'; $params['k'] = $kind; }
        return array_map([self::class, 'hydrate'], $this->db->fetchAll($sql.' ORDER BY last_message_at DESC', $params));
    }

    // ---------------------------------------------------------------- skrzynka

    /** Liczniki sekcji menu: [platform][kind] => ['open'=>…, 'total'=>…] oraz sumy. */
    public function counters(): array
    {
        $result = ['all' => ['open' => 0, 'total' => 0], 'platforms' => []];
        foreach (self::KINDS as $platform => $kinds) {
            $result['platforms'][$platform] = ['open' => 0, 'total' => 0, 'kinds' => array_fill_keys($kinds, ['open' => 0, 'new' => 0, 'total' => 0])];
        }
        $rows = $this->db->fetchAll("SELECT platform,kind,SUM(CASE WHEN status IN ('new','waiting') THEN 1 ELSE 0 END) open_count,SUM(CASE WHEN status='new' THEN 1 ELSE 0 END) new_count,COUNT(*) total FROM om_msg_threads GROUP BY platform,kind");
        foreach ($rows as $row) {
            $platform = (string) $row['platform'];
            $result['platforms'][$platform]['kinds'][$row['kind']] = ['open' => (int) $row['open_count'], 'new' => (int) $row['new_count'], 'total' => (int) $row['total']];
            $result['platforms'][$platform]['open'] = ($result['platforms'][$platform]['open'] ?? 0) + (int) $row['open_count'];
            $result['platforms'][$platform]['total'] = ($result['platforms'][$platform]['total'] ?? 0) + (int) $row['total'];
            $result['all']['open'] += (int) $row['open_count'];
            $result['all']['total'] += (int) $row['total'];
        }
        $result['statuses'] = array_map('intval', array_column($this->db->fetchAll('SELECT status,COUNT(*) c FROM om_msg_threads GROUP BY status'), 'c', 'status'));
        $result['overdue'] = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM om_msg_threads WHERE remote_closed=0 AND due_at IS NOT NULL AND due_at<=:d", ['d' => gmdate('Y-m-d H:i:s', time() + 2 * 86400)]);
        return $result;
    }

    public static function openCount(Database $db): int
    {
        return (int) $db->fetchColumn("SELECT COUNT(*) FROM om_msg_threads WHERE status IN ('new','waiting')");
    }

    public function listing(array $filters): array
    {
        $where = ['1=1'];
        $params = [];
        $platform = (string) ($filters['platform'] ?? '');
        if (isset(self::PLATFORMS[$platform])) { $where[] = 't.platform=:p'; $params['p'] = $platform; }
        $kind = (string) ($filters['kind'] ?? '');
        if (isset(self::KIND_LABELS[$kind])) { $where[] = 't.kind=:k'; $params['k'] = $kind; }
        $status = (string) ($filters['status'] ?? 'open');
        if ($status === 'open') { $where[] = "t.status IN ('new','waiting')"; }
        elseif ($status === 'due') { $where[] = 't.remote_closed=0 AND t.due_at IS NOT NULL'; }
        elseif (isset(self::STATUSES[$status])) { $where[] = 't.status=:s'; $params['s'] = $status; }
        if ((int) ($filters['connection'] ?? 0) > 0) { $where[] = 't.connection_id=:c'; $params['c'] = (int) $filters['connection']; }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(t.subject LIKE :q OR t.customer_name LIKE :q OR t.customer_login LIKE :q OR t.order_external_id LIKE :q OR t.last_preview LIKE :q OR t.external_id LIKE :q)';
            $params['q'] = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
        }
        $whereSql = implode(' AND ', $where);
        $total = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM om_msg_threads t WHERE $whereSql", $params);
        $perPage = 40;
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($pages, (int) ($filters['page'] ?? 1)));
        $order = $status === 'due' ? 't.due_at ASC' : "CASE t.status WHEN 'new' THEN 0 WHEN 'waiting' THEN 1 ELSE 2 END, t.last_message_at DESC";
        if (!in_array($status, ['open', 'due'], true)) { $order = 't.last_message_at DESC'; }
        $rows = $this->db->fetchAll("SELECT t.*,c.name AS connection_name FROM om_msg_threads t LEFT JOIN om_connections c ON c.id=t.connection_id WHERE $whereSql ORDER BY $order, t.id DESC LIMIT $perPage OFFSET ".(($page - 1) * $perPage), $params);
        return ['rows' => array_map([self::class, 'hydrate'], $rows), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    // ---------------------------------------------------------------- reguły autoodpowiedzi

    public function rules(bool $enabledOnly = false): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM om_msg_rules'.($enabledOnly ? ' WHERE enabled=1' : '').' ORDER BY position,id');
        $runs = array_column($this->db->fetchAll("SELECT rule_id,COUNT(*) c FROM om_msg_rule_runs WHERE result='sent' GROUP BY rule_id"), 'c', 'rule_id');
        foreach ($rows as &$row) { $row['kinds'] = json_decode((string) $row['kinds_json'], true) ?: []; $row['sent_count'] = (int) ($runs[$row['id']] ?? 0); }
        unset($row);
        return $rows;
    }

    public function rule(int $id): ?array
    {
        foreach ($this->rules() as $rule) { if ((int) $rule['id'] === $id) { return $rule; } }
        return null;
    }

    public const TRIGGERS = [
        'first_message' => 'Pierwsza wiadomość klienta w wątku',
        'any_message' => 'Każda nowa wiadomość klienta',
        'outside_hours' => 'Wiadomość poza godzinami pracy',
        'no_reply' => 'Brak odpowiedzi po czasie opóźnienia',
        'new_case' => 'Nowa dyskusja / reklamacja / incydent',
    ];

    public const AFTER_STATUSES = ['auto' => 'Autoodpowiedź (nadal widoczna do obsługi)', 'waiting' => 'Do odpowiedzi', 'answered' => 'Odpowiedziano'];

    public function saveRule(int $id, array $input): int
    {
        $name = trim((string) ($input['name'] ?? ''));
        $template = trim((string) ($input['template'] ?? ''));
        if ($name === '' || mb_strlen($name, 'UTF-8') > 150) { throw new InvalidArgumentException('Podaj nazwę reguły (maks. 150 znaków).'); }
        if ($template === '' || mb_strlen($template, 'UTF-8') > 2000) { throw new InvalidArgumentException('Treść odpowiedzi jest wymagana (maks. 2000 znaków – limit Allegro).'); }
        $trigger = (string) ($input['trigger_name'] ?? '');
        if (!isset(self::TRIGGERS[$trigger])) { throw new InvalidArgumentException('Wybierz zdarzenie uruchamiające regułę.'); }
        $platform = (string) ($input['platform'] ?? '');
        if ($platform !== '' && !isset(self::PLATFORMS[$platform])) { throw new InvalidArgumentException('Nieznany marketplace.'); }
        $kinds = array_values(array_intersect(array_keys(self::KIND_LABELS), (array) ($input['kinds'] ?? [])));
        if (!$kinds) { throw new InvalidArgumentException('Zaznacz co najmniej jeden rodzaj wątku.'); }
        if ($trigger === 'no_reply' && (int) ($input['delay_minutes'] ?? 0) < 1) { $input['delay_minutes'] = 60; }
        $after = (string) ($input['after_status'] ?? 'auto');
        $now = self::now();
        $data = [
            'name' => $name, 'enabled' => !empty($input['enabled']) ? 1 : 0, 'platform' => $platform, 'connection_id' => max(0, (int) ($input['connection_id'] ?? 0)),
            'kinds_json' => OrderRepository::json($kinds), 'trigger_name' => $trigger,
            'keywords' => mb_substr(trim((string) ($input['keywords'] ?? '')), 0, 1000, 'UTF-8'),
            'delay_minutes' => max(0, min(10080, (int) ($input['delay_minutes'] ?? 0))), 'cooldown_hours' => max(0, min(720, (int) ($input['cooldown_hours'] ?? 24))),
            'template' => $template, 'after_status' => isset(self::AFTER_STATUSES[$after]) ? $after : 'auto', 'updated_at' => $now,
        ];
        if ($id > 0) {
            $current = $this->rule($id);
            if (!$current) { throw new InvalidArgumentException('Nie znaleziono reguły.'); }
            // Włączenie reguły nie odpowiada na wiadomości sprzed włączenia.
            if (!$current['enabled'] && $data['enabled']) { $data['active_since'] = $now; }
            $this->db->update('om_msg_rules', $data, 'id=:id', ['id' => $id]);
            return $id;
        }
        $position = (int) $this->db->fetchColumn('SELECT COALESCE(MAX(position),0)+1 FROM om_msg_rules');
        return (int) $this->db->insert('om_msg_rules', $data + ['position' => $position, 'active_since' => $now, 'created_at' => $now]);
    }

    public function toggleRule(int $id): void
    {
        $rule = $this->rule($id);
        if (!$rule) { throw new InvalidArgumentException('Nie znaleziono reguły.'); }
        $data = ['enabled' => $rule['enabled'] ? 0 : 1, 'updated_at' => self::now()];
        if (!$rule['enabled']) { $data['active_since'] = self::now(); }
        $this->db->update('om_msg_rules', $data, 'id=:id', ['id' => $id]);
    }

    public function deleteRule(int $id): void
    {
        $this->db->transaction(function () use ($id) {
            $this->db->delete('om_msg_rules', 'id=:id', ['id' => $id]);
            $this->db->delete('om_msg_rule_runs', 'rule_id=:id', ['id' => $id]);
        });
    }

    public function moveRule(int $id, int $direction): void
    {
        $rules = $this->rules();
        $index = array_search($id, array_map('intval', array_column($rules, 'id')), true);
        $target = $index === false ? false : $index + ($direction < 0 ? -1 : 1);
        if ($index === false || !isset($rules[$target])) { return; }
        [$rules[$index], $rules[$target]] = [$rules[$target], $rules[$index]];
        $this->db->transaction(function () use ($rules) {
            foreach (array_values($rules) as $position => $rule) { $this->db->update('om_msg_rules', ['position' => $position + 1], 'id=:id', ['id' => $rule['id']]); }
        });
    }

    public function ruleRan(int $ruleId, int $threadId, string $triggerKey): bool
    {
        return (bool) $this->db->fetchColumn('SELECT COUNT(*) FROM om_msg_rule_runs WHERE rule_id=:r AND thread_id=:t AND trigger_key=:k', ['r' => $ruleId, 't' => $threadId, 'k' => $triggerKey]);
    }

    public function logRun(int $ruleId, int $threadId, string $triggerKey, string $result, string $message = ''): void
    {
        $verb = $this->db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
        $this->db->query($verb.' INTO om_msg_rule_runs (rule_id,thread_id,trigger_key,result,message,created_at) VALUES (:r,:t,:k,:res,:m,:c)', ['r' => $ruleId, 't' => $threadId, 'k' => mb_substr($triggerKey, 0, 190, 'UTF-8'), 'res' => $result, 'm' => mb_substr($message, 0, 1000, 'UTF-8'), 'c' => self::now()]);
    }

    public function runLog(int $limit = 30): array
    {
        return $this->db->fetchAll('SELECT r.*,m.name AS rule_name,t.subject,t.customer_name,t.customer_login,t.platform,t.kind FROM om_msg_rule_runs r LEFT JOIN om_msg_rules m ON m.id=r.rule_id LEFT JOIN om_msg_threads t ON t.id=r.thread_id ORDER BY r.id DESC LIMIT '.max(1, min(200, $limit)));
    }
}
