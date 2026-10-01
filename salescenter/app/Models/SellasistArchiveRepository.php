<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Archiwum Sellasist firmy: stare zamówienia (om_archive_orders) i dokumenty sprzedaży
 * (om_archive_documents) pobrane przez API Sellasist. Dane są tylko do odczytu – nie trafiają
 * do bieżącej kolejki zamówień ani do numeracji dokumentów SalesCenter.
 * Konfiguracja i postęp importu: om_settings `sellasist_archive` i `sellasist_archive_state`.
 */
final class SellasistArchiveRepository
{
    public const DOC_KINDS = [
        'invoice' => 'Faktura',
        'correct' => 'Faktura korygująca',
        'receipt' => 'Paragon',
        'receipt_correct' => 'Korekta paragonu',
    ];

    public const PER_PAGE = 50;

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
            'om_archive_orders' => "id $id, sellasist_id BIGINT NOT NULL UNIQUE, ordered_at VARCHAR(30) NOT NULL DEFAULT '', status_id INTEGER NOT NULL DEFAULT 0, status_name VARCHAR(150) NOT NULL DEFAULT '', source VARCHAR(100) NOT NULL DEFAULT '', shop VARCHAR(190) NOT NULL DEFAULT '', creator VARCHAR(190) NOT NULL DEFAULT '', external_id VARCHAR(190) NOT NULL DEFAULT '', buyer_name VARCHAR(255) NOT NULL DEFAULT '', company VARCHAR(255) NOT NULL DEFAULT '', nip VARCHAR(40) NOT NULL DEFAULT '', email VARCHAR(255) NOT NULL DEFAULT '', phone VARCHAR(60) NOT NULL DEFAULT '', city VARCHAR(120) NOT NULL DEFAULT '', country VARCHAR(10) NOT NULL DEFAULT '', total_cents BIGINT NOT NULL DEFAULT 0, currency VARCHAR(10) NOT NULL DEFAULT 'PLN', payment_name VARCHAR(190) NOT NULL DEFAULT '', payment_status VARCHAR(30) NOT NULL DEFAULT '', paid_cents BIGINT NOT NULL DEFAULT 0, cod INTEGER NOT NULL DEFAULT 0, delivery_name VARCHAR(190) NOT NULL DEFAULT '', tracking VARCHAR(500) NOT NULL DEFAULT '', document_number VARCHAR(190) NOT NULL DEFAULT '', item_count INTEGER NOT NULL DEFAULT 0, items_preview VARCHAR(500) NOT NULL DEFAULT '', search_text TEXT NOT NULL, detail_json LONGTEXT NULL, detail_state INTEGER NOT NULL DEFAULT 0, imported_at VARCHAR(30) NOT NULL, updated_at VARCHAR(30) NOT NULL",
            'om_archive_documents' => "id $id, kind VARCHAR(20) NOT NULL, remote_id BIGINT NOT NULL, number VARCHAR(190) NOT NULL DEFAULT '', related_number VARCHAR(190) NOT NULL DEFAULT '', issue_date VARCHAR(30) NOT NULL DEFAULT '', order_remote_id BIGINT NOT NULL DEFAULT 0, buyer_name VARCHAR(255) NOT NULL DEFAULT '', buyer_nip VARCHAR(40) NOT NULL DEFAULT '', total_cents BIGINT NOT NULL DEFAULT 0, currency VARCHAR(10) NOT NULL DEFAULT 'PLN', search_text TEXT NOT NULL, detail_json LONGTEXT NULL, detail_state INTEGER NOT NULL DEFAULT 0, imported_at VARCHAR(30) NOT NULL, updated_at VARCHAR(30) NOT NULL, UNIQUE(kind, remote_id)",
        ];
        foreach ($tables as $name => $columns) { $this->db->query("CREATE TABLE IF NOT EXISTS $name ($columns)$suffix"); }
        foreach ([
            'om_archive_orders' => ['om_arch_order_date' => 'ordered_at', 'om_arch_order_status' => 'status_id,ordered_at', 'om_arch_order_source' => 'source', 'om_arch_order_detail' => 'detail_state,sellasist_id'],
            'om_archive_documents' => ['om_arch_doc_order' => 'order_remote_id', 'om_arch_doc_date' => 'kind,issue_date', 'om_arch_doc_detail' => 'detail_state,id'],
        ] as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if ($sqlite) { $this->db->query("CREATE INDEX IF NOT EXISTS $name ON $table ($columns)"); }
                elseif (!$this->db->fetch("SHOW INDEX FROM $table WHERE Key_name=:name", ['name' => $name])) {
                    try { $this->db->query("CREATE INDEX $name ON $table ($columns)"); }
                    catch (\PDOException $e) { if ((int) ($e->errorInfo[1] ?? 0) !== 1061) { throw $e; } }
                }
            }
        }
        $this->backfillPickupTracking();
        if (!$this->settingValue('sellasist_archive_receipts_v1')) {
            $this->syncReceiptNumbers();
            $this->saveSettingValue('sellasist_archive_receipts_v1', ['done' => gmdate('Y-m-d H:i:s')]);
        }
    }

    /** Jednorazowo uzupełnia nr nadania z pickup_code w już zaimportowanych zamówieniach (z zapisanych szczegółów). */
    private function backfillPickupTracking(): void
    {
        if ($this->settingValue('sellasist_archive_tracking_v1')) { return; }
        $lastId = 0;
        do {
            $rows = $this->db->fetchAll("SELECT id,detail_json FROM om_archive_orders WHERE id>:last AND tracking='' AND detail_state=1 AND detail_json LIKE '%pickup_code%' ORDER BY id LIMIT 500", ['last' => $lastId]);
            foreach ($rows as $r) {
                $lastId = (int) $r['id'];
                $order = json_decode((string) $r['detail_json'], true);
                if (!is_array($order)) { continue; }
                $row = self::orderRow($order);
                if ($row['tracking'] !== '') { $this->db->update('om_archive_orders', ['tracking' => $row['tracking'], 'search_text' => $row['search_text']], 'id=:id', ['id' => $r['id']]); }
            }
        } while (count($rows) === 500);
        $this->saveSettingValue('sellasist_archive_tracking_v1', ['done' => gmdate('Y-m-d H:i:s')]);
    }

    // ---- ustawienia i stan importu -------------------------------------------------------

    public function settings(): array { return $this->settingValue('sellasist_archive') + ['account' => '', 'secret' => '', 'key_hint' => '', 'enabled' => false]; }

    public function saveSettings(array $value): void { $this->saveSettingValue('sellasist_archive', $value); }

    public function state(): array { return $this->settingValue('sellasist_archive_state') + ['phases' => [], 'last_run' => '', 'last_error' => '', 'backoff_until' => 0, 'requests' => 0, 'started_at' => '', 'completed_at' => '']; }

    public function saveState(array $value): void { $this->saveSettingValue('sellasist_archive_state', $value); }

    private function settingValue(string $key): array
    {
        $value = $this->db->fetchColumn('SELECT value_json FROM om_settings WHERE setting_key=:k', ['k' => $key]);
        return $value ? (json_decode((string) $value, true) ?: []) : [];
    }

    private function saveSettingValue(string $key, array $value): void
    {
        $this->db->transaction(function () use ($key, $value) {
            $this->db->delete('om_settings', 'setting_key=:k', ['k' => $key]);
            $this->db->insert('om_settings', ['setting_key' => $key, 'value_json' => OrderRepository::json($value)]);
        });
    }

    // ---- zapis danych z API ----------------------------------------------------------------

    /** Zapisuje zamówienie z listy (uproszczone) albo ze szczegółów; szczegóły nie są nadpisywane danymi z listy. */
    public function upsertOrder(array $order, bool $detail): void
    {
        $sellasistId = (int) ($order['id'] ?? 0);
        if ($sellasistId < 1) { return; }
        $now = gmdate('Y-m-d H:i:s');
        $existing = $this->db->fetch('SELECT id,detail_state FROM om_archive_orders WHERE sellasist_id=:id', ['id' => $sellasistId]);
        if ($existing && !$detail && (int) $existing['detail_state'] === 1) { return; }
        $row = self::orderRow($order);
        $row['updated_at'] = $now;
        if ($detail) { $row['detail_json'] = OrderRepository::json($order); $row['detail_state'] = 1; }
        if ($existing) {
            $this->db->update('om_archive_orders', $row, 'id=:id', ['id' => $existing['id']]);
        } else {
            $this->db->insert('om_archive_orders', $row + ['sellasist_id' => $sellasistId, 'imported_at' => $now, 'detail_state' => 0]);
        }
    }

    public function markOrderDetail(int $sellasistId, int $state): void
    {
        $this->db->update('om_archive_orders', ['detail_state' => $state, 'updated_at' => gmdate('Y-m-d H:i:s')], 'sellasist_id=:id', ['id' => $sellasistId]);
    }

    public function upsertDocument(string $kind, array $document, bool $detail): void
    {
        $remoteId = (int) ($document['id'] ?? 0);
        if ($remoteId < 1 || !isset(self::DOC_KINDS[$kind])) { return; }
        $now = gmdate('Y-m-d H:i:s');
        $existing = $this->db->fetch('SELECT id,detail_state FROM om_archive_documents WHERE kind=:k AND remote_id=:r', ['k' => $kind, 'r' => $remoteId]);
        if ($existing && !$detail && (int) $existing['detail_state'] === 1) { return; }
        $row = self::documentRow($kind, $document);
        $row['updated_at'] = $now;
        if ($detail) { $row['detail_json'] = OrderRepository::json($document); $row['detail_state'] = 1; }
        if ($existing) {
            $this->db->update('om_archive_documents', $row, 'id=:id', ['id' => $existing['id']]);
        } else {
            $this->db->insert('om_archive_documents', $row + ['kind' => $kind, 'remote_id' => $remoteId, 'imported_at' => $now, 'detail_state' => 0]);
        }
    }

    /**
     * Sellasist zwraca w paragonie własny numer (np. P21299/10/2025); właściwy numer paragonu (PA/946/08/2026)
     * jest w document_number zamówienia. Pomijamy zamówienia z fakturą – tam document_number to numer faktury.
     */
    public function syncReceiptNumbers(): int
    {
        $rows = $this->db->fetchAll("SELECT d.id,d.search_text,o.document_number FROM om_archive_documents d JOIN om_archive_orders o ON o.sellasist_id=d.order_remote_id
            WHERE d.kind='receipt' AND d.order_remote_id>0 AND o.document_number<>'' AND o.document_number<>d.number
            AND NOT EXISTS (SELECT 1 FROM om_archive_documents i WHERE i.order_remote_id=d.order_remote_id AND i.kind IN ('invoice','correct'))");
        foreach ($rows as $r) {
            $number = self::cut((string) $r['document_number'], 190);
            $this->db->update('om_archive_documents', ['number' => $number, 'search_text' => mb_substr($r['search_text'].' | '.mb_strtolower($number, 'UTF-8'), 0, 20000, 'UTF-8')], 'id=:id', ['id' => $r['id']]);
        }
        return count($rows);
    }

    public function markDocumentDetail(string $kind, int $remoteId, int $state): void
    {
        $this->db->update('om_archive_documents', ['detail_state' => $state, 'updated_at' => gmdate('Y-m-d H:i:s')], 'kind=:k AND remote_id=:r', ['k' => $kind, 'r' => $remoteId]);
    }

    /** @return int[] identyfikatory Sellasist zamówień bez pobranych szczegółów */
    public function pendingOrderDetails(int $limit): array
    {
        return array_map('intval', array_column($this->db->fetchAll('SELECT sellasist_id FROM om_archive_orders WHERE detail_state=0 ORDER BY sellasist_id LIMIT '.max(1, $limit)), 'sellasist_id'));
    }

    public function pendingDocumentDetails(int $limit): array
    {
        return $this->db->fetchAll('SELECT kind,remote_id FROM om_archive_documents WHERE detail_state=0 ORDER BY id LIMIT '.max(1, $limit));
    }

    /** Ponowne pobranie szczegółów (np. po błędach) – rekordy wracają do kolejki. */
    public function requeueDetails(bool $failedOnly): int
    {
        $where = $failedOnly ? 'detail_state=2' : 'detail_state<>0';
        $count = 0;
        foreach (['om_archive_orders', 'om_archive_documents'] as $table) { $count += $this->db->update($table, ['detail_state' => 0], $where); }
        return $count;
    }

    public function clear(): void
    {
        $this->db->delete('om_archive_orders', '1=1');
        $this->db->delete('om_archive_documents', '1=1');
    }

    // ---- normalizacja ----------------------------------------------------------------------

    public static function orderRow(array $o): array
    {
        $bill = is_array($o['bill_address'] ?? null) ? $o['bill_address'] : [];
        $ship = is_array($o['shipment_address'] ?? null) ? $o['shipment_address'] : [];
        $person = static function (array $a): string { return trim(self::str($a['name'] ?? '').' '.self::str($a['surname'] ?? '')); };
        $buyer = $person($bill) !== '' ? $person($bill) : $person($ship);
        $company = self::str($bill['company_name'] ?? '') ?: self::str($ship['company_name'] ?? '');
        $status = is_array($o['status'] ?? null) ? $o['status'] : ['id' => (int) ($o['status'] ?? 0), 'name' => ''];
        $payment = is_array($o['payment'] ?? null) ? $o['payment'] : [];
        $shipment = is_array($o['shipment'] ?? null) ? $o['shipment'] : [];
        $external = is_array($o['external_data'] ?? null) ? $o['external_data'] : [];
        $carts = is_array($o['carts'] ?? null) ? array_values(array_filter($o['carts'], 'is_array')) : [];
        $tracking = [self::str($o['tracking_number'] ?? '')];
        if (is_array($o['tracking_numbers'] ?? null)) { $tracking[] = self::str($o['tracking_numbers']['trackingNumber'] ?? ''); }
        foreach ((array) ($o['shipments'] ?? []) as $sent) {
            if (!is_array($sent)) { continue; }
            $tracking[] = self::str($sent['tracking_number'] ?? '');
            if (is_array($sent['tracking_numbers'] ?? null)) { $tracking[] = self::str($sent['tracking_numbers']['trackingNumber'] ?? ''); }
        }
        $tracking = array_values(array_unique(array_filter($tracking, 'strlen')));
        // Przesyłki kurierskie (np. Furgonetka InPost Kurier): Sellasist zapisuje nr nadania w pickup_code, a tracking_number zostaje pusty.
        if (!$tracking && empty($o['is_parcel_locker'])) {
            $code = self::str($shipment['pickup_code'] ?? '') ?: (is_array($o['pickup_point'] ?? null) ? self::str($o['pickup_point']['code'] ?? '') : '');
            if ($code !== '') { $tracking[] = $code; }
        }
        $items = []; $search = []; $count = 0;
        foreach ($carts as $cart) {
            $qty = (int) ($cart['quantity'] ?? 1);
            $count += max(0, $qty);
            $items[] = $qty.'× '.self::str($cart['name'] ?? '');
            foreach (['name', 'symbol', 'ean', 'catalog_number', 'external_offer_id'] as $field) { $search[] = self::str($cart[$field] ?? ''); }
        }
        $currency = strtoupper(self::str($payment['currency'] ?? ''));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) { $currency = 'PLN'; }
        $row = [
            'ordered_at' => self::date($o['date'] ?? ''),
            'status_id' => (int) ($status['id'] ?? 0),
            'status_name' => self::cut(self::str($status['name'] ?? ''), 150),
            'source' => self::cut(self::str($o['source'] ?? ''), 100),
            'shop' => self::cut(self::str($o['shop'] ?? ''), 190),
            'creator' => self::cut(self::str($o['creator'] ?? '') ?: self::str($external['external_login'] ?? ''), 190),
            'external_id' => self::cut(self::str($external['external_id'] ?? ''), 190),
            'buyer_name' => self::cut($buyer, 255),
            'company' => self::cut($company, 255),
            'nip' => self::cut(self::str($bill['company_nip'] ?? ''), 40),
            'email' => self::cut(self::str($o['email'] ?? ''), 255),
            'phone' => self::cut(self::str($bill['phone'] ?? '') ?: self::str($ship['phone'] ?? ''), 60),
            'city' => self::cut(self::str($ship['city'] ?? '') ?: self::str($bill['city'] ?? ''), 120),
            'country' => self::cut(strtoupper(self::countryCode($ship['country'] ?? ($bill['country'] ?? ''))), 10),
            'total_cents' => self::cents($o['total'] ?? 0),
            'currency' => $currency,
            'payment_name' => self::cut(self::str($payment['name'] ?? ''), 190),
            'payment_status' => self::cut(self::str($payment['status'] ?? ''), 30),
            'paid_cents' => self::cents($payment['paid'] ?? 0),
            'cod' => !empty($payment['cod']) ? 1 : 0,
            'delivery_name' => self::cut(self::str($shipment['name'] ?? ''), 190),
            'tracking' => self::cut(implode(', ', $tracking), 500),
            'document_number' => self::cut(self::str($o['document_number'] ?? ''), 190),
            'item_count' => $count,
            'items_preview' => self::cut(implode(' · ', $items), 500),
        ];
        $search = array_merge($search, [(string) ($o['id'] ?? ''), $row['external_id'], $row['document_number'], $row['buyer_name'], $row['company'], $row['nip'], $row['email'], $row['phone'],
            $row['city'], $row['creator'], $row['tracking'], self::str($o['comment'] ?? ''), self::str($bill['street'] ?? ''), self::str($bill['postcode'] ?? ''), self::str($ship['street'] ?? ''), self::str($ship['postcode'] ?? ''), $person($ship)]);
        $row['search_text'] = self::searchText($search);
        return $row;
    }

    public static function documentRow(string $kind, array $d): array
    {
        $buyer = is_array($d['buyer'] ?? null) ? $d['buyer'] : [];
        $number = self::str($d['number'] ?? '') ?: self::str($d['receipt_correct_number'] ?? '');
        $related = '';
        if ($kind === 'receipt_correct') { $related = self::str($d['receipt_number'] ?? ''); }
        if ($kind === 'correct' && !empty($d['invoice_id'])) { $related = 'FV id '.(int) $d['invoice_id']; }
        $currency = strtoupper(self::str($d['currency'] ?? ''));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) { $currency = 'PLN'; }
        $total = $d['total'] ?? null;
        if ($total === null && is_array($d['lines'] ?? null)) {
            $total = 0.0;
            foreach ($d['lines'] as $line) { if (is_array($line)) { $total += (float) ($line['price_gross'] ?? 0) * (float) ($line['quantity'] ?? 0); } }
        }
        $row = [
            'number' => self::cut($number, 190),
            'related_number' => self::cut($related, 190),
            'issue_date' => self::date($d['issue_date'] ?? ($d['date'] ?? '')),
            'order_remote_id' => (int) ($d['order_id'] ?? 0),
            'buyer_name' => self::cut(self::str($buyer['name'] ?? ''), 255),
            'buyer_nip' => self::cut(self::str($buyer['nip'] ?? ''), 40),
            'total_cents' => self::cents($total ?? 0),
            'currency' => $currency,
        ];
        $search = [$row['number'], $row['related_number'], (string) $row['order_remote_id'], $row['buyer_name'], $row['buyer_nip'], self::str($buyer['email'] ?? ''), self::str($buyer['city'] ?? ''), self::str($d['package_number'] ?? '')];
        foreach ((array) ($d['lines'] ?? []) as $line) { if (is_array($line)) { $search[] = self::str($line['name'] ?? ''); } }
        $row['search_text'] = self::searchText($search);
        return $row;
    }

    // ---- odczyt dla widoku -----------------------------------------------------------------

    public function orders(array $filters, ?int $limit = null): array
    {
        [$where, $params] = $this->orderWhere($filters);
        $sort = [
            'newest' => 'ordered_at DESC, sellasist_id DESC', 'oldest' => 'ordered_at ASC, sellasist_id ASC',
            'amount_desc' => 'total_cents DESC', 'amount_asc' => 'total_cents ASC', 'buyer' => 'buyer_name ASC',
        ][(string) ($filters['sort'] ?? '')] ?? 'ordered_at DESC, sellasist_id DESC';
        if (isset($params['qid'])) { $sort = 'CASE WHEN sellasist_id='.(int) $params['qid'].' THEN 0 ELSE 1 END, '.$sort; }
        $columns = 'id,sellasist_id,ordered_at,status_id,status_name,source,shop,creator,external_id,buyer_name,company,nip,email,phone,city,country,total_cents,currency,payment_name,payment_status,paid_cents,cod,delivery_name,tracking,document_number,item_count,items_preview,detail_state';
        if ($limit !== null) {
            return ['rows' => $this->db->fetchAll("SELECT $columns FROM om_archive_orders $where ORDER BY $sort LIMIT ".max(1, $limit), $params)];
        }
        $total = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM om_archive_orders $where", $params);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($pages, max(1, (int) ($filters['page'] ?? 1)));
        $rows = $this->db->fetchAll("SELECT $columns FROM om_archive_orders $where ORDER BY $sort LIMIT ".self::PER_PAGE.' OFFSET '.(($page - 1) * self::PER_PAGE), $params);
        if ($rows) {
            $ids = array_map('intval', array_column($rows, 'sellasist_id'));
            $docs = [];
            foreach ($this->db->fetchAll('SELECT id,kind,number,order_remote_id FROM om_archive_documents WHERE order_remote_id IN ('.implode(',', $ids).') ORDER BY issue_date,id') as $doc) {
                $docs[(int) $doc['order_remote_id']][] = $doc;
            }
            foreach ($rows as &$row) { $row['documents'] = $docs[(int) $row['sellasist_id']] ?? []; }
            unset($row);
        }
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    private function orderWhere(array $f): array
    {
        $where = []; $params = [];
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $terms = preg_split('/\s+/u', mb_strtolower($q, 'UTF-8')) ?: [];
            foreach (array_slice($terms, 0, 6) as $i => $term) {
                $where[] = "search_text LIKE :q$i ESCAPE '!'";
                $params["q$i"] = '%'.self::like($term).'%';
            }
            if (ctype_digit($q)) {
                // Numer zamówienia Sellasist ma pierwszeństwo przed dopasowaniem tekstowym.
                $last = array_pop($where);
                $where[] = '(sellasist_id=:qid OR '.$last.')';
                $params['qid'] = (int) $q;
            }
        }
        if ((string) ($f['status_id'] ?? '') !== '') { $where[] = 'status_id=:status'; $params['status'] = (int) $f['status_id']; }
        if ((string) ($f['source'] ?? '') !== '') { $where[] = 'source=:source'; $params['source'] = (string) $f['source']; }
        if ((string) ($f['payment_status'] ?? '') !== '') { $where[] = 'payment_status=:pay'; $params['pay'] = (string) $f['payment_status']; }
        if ((string) ($f['document'] ?? '') === '1') { $where[] = "(document_number<>'' OR sellasist_id IN (SELECT order_remote_id FROM om_archive_documents))"; }
        if ((string) ($f['document'] ?? '') === '0') { $where[] = "document_number='' AND sellasist_id NOT IN (SELECT order_remote_id FROM om_archive_documents)"; }
        if (self::validDate((string) ($f['date_from'] ?? ''))) { $where[] = 'ordered_at>=:df'; $params['df'] = $f['date_from'].' 00:00:00'; }
        if (self::validDate((string) ($f['date_to'] ?? ''))) { $where[] = 'ordered_at<=:dt'; $params['dt'] = $f['date_to'].' 23:59:59'; }
        if (($from = self::amount((string) ($f['amount_from'] ?? ''))) !== null) { $where[] = 'total_cents>=:af'; $params['af'] = $from; }
        if (($to = self::amount((string) ($f['amount_to'] ?? ''))) !== null) { $where[] = 'total_cents<=:at'; $params['at'] = $to; }
        return [$where ? 'WHERE '.implode(' AND ', $where) : '', $params];
    }

    public function order(int $id): ?array
    {
        $row = $this->db->fetch('SELECT * FROM om_archive_orders WHERE id=:id', ['id' => $id]);
        if (!$row) { return null; }
        $row['detail'] = $row['detail_json'] ? (json_decode((string) $row['detail_json'], true) ?: []) : [];
        unset($row['detail_json'], $row['search_text']);
        $row['documents'] = $this->db->fetchAll('SELECT id,kind,number,related_number,issue_date,total_cents,currency FROM om_archive_documents WHERE order_remote_id=:o ORDER BY issue_date,id', ['o' => $row['sellasist_id']]);
        return $row;
    }

    public function documents(array $f, ?int $limit = null): array
    {
        $where = []; $params = [];
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            foreach (array_slice(preg_split('/\s+/u', mb_strtolower($q, 'UTF-8')) ?: [], 0, 6) as $i => $term) {
                $where[] = "d.search_text LIKE :q$i ESCAPE '!'"; $params["q$i"] = '%'.self::like($term).'%';
            }
        }
        if (isset(self::DOC_KINDS[(string) ($f['kind'] ?? '')])) { $where[] = 'd.kind=:kind'; $params['kind'] = (string) $f['kind']; }
        if (self::validDate((string) ($f['date_from'] ?? ''))) { $where[] = 'd.issue_date>=:df'; $params['df'] = $f['date_from']; }
        if (self::validDate((string) ($f['date_to'] ?? ''))) { $where[] = 'd.issue_date<=:dt'; $params['dt'] = $f['date_to'].' 23:59:59'; }
        if (($from = self::amount((string) ($f['amount_from'] ?? ''))) !== null) { $where[] = 'd.total_cents>=:af'; $params['af'] = $from; }
        if (($to = self::amount((string) ($f['amount_to'] ?? ''))) !== null) { $where[] = 'd.total_cents<=:at'; $params['at'] = $to; }
        $whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';
        $sort = ['oldest' => 'd.issue_date ASC, d.id ASC', 'amount_desc' => 'd.total_cents DESC', 'amount_asc' => 'd.total_cents ASC', 'number' => 'd.number ASC'][(string) ($f['sort'] ?? '')] ?? 'd.issue_date DESC, d.id DESC';
        $select = "SELECT d.id,d.kind,d.remote_id,d.number,d.related_number,d.issue_date,d.order_remote_id,d.buyer_name,d.buyer_nip,d.total_cents,d.currency,d.detail_state,o.id AS archive_order_id FROM om_archive_documents d LEFT JOIN om_archive_orders o ON o.sellasist_id=d.order_remote_id AND d.order_remote_id>0 $whereSql ORDER BY $sort";
        if ($limit !== null) { return ['rows' => $this->db->fetchAll($select.' LIMIT '.max(1, $limit), $params)]; }
        $total = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM om_archive_documents d $whereSql", $params);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($pages, max(1, (int) ($f['page'] ?? 1)));
        return ['rows' => $this->db->fetchAll($select.' LIMIT '.self::PER_PAGE.' OFFSET '.(($page - 1) * self::PER_PAGE), $params), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public function document(int $id): ?array
    {
        $row = $this->db->fetch('SELECT d.*,o.id AS archive_order_id FROM om_archive_documents d LEFT JOIN om_archive_orders o ON o.sellasist_id=d.order_remote_id AND d.order_remote_id>0 WHERE d.id=:id', ['id' => $id]);
        if (!$row) { return null; }
        $row['detail'] = $row['detail_json'] ? (json_decode((string) $row['detail_json'], true) ?: []) : [];
        unset($row['detail_json'], $row['search_text']);
        return $row;
    }

    public function stats(): array
    {
        $orders = $this->db->fetch('SELECT COUNT(*) total, SUM(CASE WHEN detail_state=1 THEN 1 ELSE 0 END) detailed, SUM(CASE WHEN detail_state=2 THEN 1 ELSE 0 END) failed, MIN(ordered_at) oldest, MAX(ordered_at) newest FROM om_archive_orders') ?: [];
        $docs = $this->db->fetch('SELECT COUNT(*) total, SUM(CASE WHEN detail_state=1 THEN 1 ELSE 0 END) detailed, SUM(CASE WHEN detail_state=2 THEN 1 ELSE 0 END) failed FROM om_archive_documents') ?: [];
        $kinds = array_column($this->db->fetchAll('SELECT kind, COUNT(*) c FROM om_archive_documents GROUP BY kind'), 'c', 'kind');
        return [
            'orders' => (int) ($orders['total'] ?? 0), 'orders_detailed' => (int) ($orders['detailed'] ?? 0), 'orders_failed' => (int) ($orders['failed'] ?? 0),
            'oldest' => (string) ($orders['oldest'] ?? ''), 'newest' => (string) ($orders['newest'] ?? ''),
            'documents' => (int) ($docs['total'] ?? 0), 'documents_detailed' => (int) ($docs['detailed'] ?? 0), 'documents_failed' => (int) ($docs['failed'] ?? 0),
            'kinds' => array_map('intval', $kinds),
        ];
    }

    /** Wartości do filtrów: statusy, źródła, statusy płatności. */
    public function facets(): array
    {
        return [
            'statuses' => $this->db->fetchAll("SELECT status_id, MAX(status_name) status_name, COUNT(*) c FROM om_archive_orders GROUP BY status_id ORDER BY status_id"),
            'sources' => $this->db->fetchAll("SELECT source, COUNT(*) c FROM om_archive_orders WHERE source<>'' GROUP BY source ORDER BY c DESC"),
            'payment_statuses' => array_column($this->db->fetchAll("SELECT DISTINCT payment_status FROM om_archive_orders WHERE payment_status<>'' ORDER BY payment_status"), 'payment_status'),
        ];
    }

    // ---- pomocnicze ------------------------------------------------------------------------

    private static function str($value): string
    {
        if (is_array($value) || is_object($value) || $value === null || is_bool($value)) { return ''; }
        return trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
    }

    private static function cut(string $value, int $length): string { return mb_substr($value, 0, $length, 'UTF-8'); }

    private static function cents($value): int
    {
        if (!is_scalar($value)) { return 0; }
        return (int) round((float) str_replace([',', ' '], ['.', ''], (string) $value) * 100);
    }

    /** Data w formacie „Y-m-d H:i:s” (czas Sellasist, Europe/Warsaw). API zwraca tekst, czasem obiekt {date: …}. */
    private static function date($value): string
    {
        if (is_array($value)) { $value = $value['date'] ?? ''; }
        $value = self::str($value);
        if ($value === '') { return ''; }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})(?:[ T](\d{2}:\d{2}(?::\d{2})?))?/', $value, $m)) {
            $time = $m[2] ?? '';
            if ($time === '') { return $m[1]; }
            return $m[1].' '.(strlen($time) === 5 ? $time.':00' : $time);
        }
        return self::cut($value, 30);
    }

    /** Sellasist podaje kraj jako ISO2, ISO3 albo nazwę – w liście pokazujemy krótki kod. */
    private static function countryCode($value): string
    {
        $value = self::str($value);
        if (is_array($value)) { return ''; }
        $map = ['POL' => 'PL', 'POLSKA' => 'PL', 'POLAND' => 'PL', 'DEU' => 'DE', 'CZE' => 'CZ', 'SVK' => 'SK', 'LTU' => 'LT', 'HUN' => 'HU', 'AUT' => 'AT', 'FRA' => 'FR', 'ITA' => 'IT', 'ESP' => 'ES', 'GBR' => 'GB', 'NLD' => 'NL', 'BEL' => 'BE', 'ROU' => 'RO', 'UKR' => 'UA'];
        $upper = mb_strtoupper($value, 'UTF-8');
        return $map[$upper] ?? (preg_match('/^[A-Z]{2,3}$/', $upper) ? $upper : '');
    }

    private static function searchText(array $parts): string
    {
        $parts = array_values(array_unique(array_filter(array_map(static function ($part) { return is_scalar($part) ? trim((string) $part) : ''; }, $parts), 'strlen')));
        $text = mb_strtolower(implode(' | ', $parts), 'UTF-8');
        // Numery telefonów i NIP także bez spacji/myślników.
        $digits = [];
        foreach ($parts as $part) { $plain = preg_replace('/[\s\-]/', '', $part); if ($plain !== $part && preg_match('/^\+?\d{6,}$/', (string) $plain)) { $digits[] = $plain; } }
        if ($digits) { $text .= ' | '.implode(' | ', $digits); }
        return mb_substr($text, 0, 20000, 'UTF-8');
    }

    private static function like(string $term): string { return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term); }

    private static function validDate(string $value): bool { return preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) === 1; }

    private static function amount(string $value): ?int
    {
        $value = trim(str_replace([' ', ','], ['', '.'], $value));
        return $value !== '' && is_numeric($value) ? (int) round((float) $value * 100) : null;
    }
}
