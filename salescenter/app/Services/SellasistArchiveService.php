<?php

declare(strict_types=1);
namespace App\Services;

use App\Models\SellasistArchiveRepository;
use App\Services\Integrations\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Import archiwum z Sellasist (https://{konto}.sellasist.pl/api/v1, nagłówek apiKey) w małych porcjach.
 *
 * Kolejność: listy (zamówienia, faktury, korekty, paragony, korekty paragonów, dokumenty operacyjne – od najstarszych,
 * offset rosnąco po ID), potem szczegóły każdego zamówienia i dokumentu. Każde wywołanie run() ma limit czasu,
 * zapisuje kursory w om_settings i kończy się bez utraty postępu – kolejne uruchomienie (cron co minutę
 * albo przycisk „Pobieraj teraz”) kontynuuje. Po pobraniu wszystkiego listy są co 15 min sprawdzane
 * pod kątem nowych rekordów. Import wyłącznie czyta dane z Sellasist.
 */
final class SellasistArchiveService
{
    public const LIST_PHASES = [
        'orders' => ['/orders', 'Zamówienia'],
        'invoice' => ['/invoices', 'Faktury'],
        'correct' => ['/corrects', 'Faktury korygujące'],
        'receipt' => ['/receipts', 'Paragony'],
        'receipt_correct' => ['/receiptcorrects', 'Korekty paragonów'],
        // Moduł „Dokumenty operacyjne” (od ~11.2025 tu powstają faktury i paragony, np. PA/…); dokumenty magazynowe są pomijane.
        'op_sale' => ['/operationdocuments', 'Dokumenty operacyjne'],
        'op_correct' => ['/operationdocuments', 'Korekty (dok. operacyjne)'],
    ];
    private const LIST_FILTERS = ['op_sale' => ['type' => 'sale'], 'op_correct' => ['type' => 'correct']];
    private const DETAIL_PATHS = ['invoice' => '/invoices/', 'correct' => '/corrects/', 'receipt' => '/receipts/', 'receipt_correct' => '/receiptcorrects/'];
    private const OPERATION_DETAIL_PATH = '/operationdocuments/';
    private const PAGE = 100;
    private const RECHECK_SECONDS = 900;
    private const LOCK = 'sellasist_archive';

    /** Przerwa między zapytaniami (µs); testy ustawiają 0. */
    public static $pauseMicro = 100000;

    /** @var SellasistArchiveRepository */
    private $repo;

    /** @var int */
    private $requests = 0;

    public function __construct(SellasistArchiveRepository $repo) { $this->repo = $repo; }

    public function repo(): SellasistArchiveRepository { return $this->repo; }

    /** „altreo”, „altreo.sellasist.pl” albo pełny adres → host konta. Tylko domeny *.sellasist.pl. */
    public static function normalizeAccount(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') { throw new InvalidArgumentException('Podaj nazwę konta Sellasist (np. altreo z adresu altreo.sellasist.pl).'); }
        if (preg_match('#^[a-z]+://#', $value)) { $value = (string) parse_url($value, PHP_URL_HOST); }
        $value = explode('/', $value, 2)[0];
        if (strpos($value, '.') === false) { $value .= '.sellasist.pl'; }
        if (preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.sellasist\.pl$/D', $value) !== 1) {
            throw new InvalidArgumentException('Adres konta musi mieć postać nazwa.sellasist.pl.');
        }
        return $value;
    }

    public function configured(): bool
    {
        $settings = $this->repo->settings();
        return $settings['account'] !== '' && $settings['secret'] !== '';
    }

    /** Zapisuje konto i klucz (pusty klucz = bez zmian) po sprawdzeniu połączenia z API. */
    public function configure(string $account, string $apiKey): array
    {
        $settings = $this->repo->settings();
        $host = self::normalizeAccount($account);
        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            if ($settings['secret'] === '') { throw new InvalidArgumentException('Podaj klucz API z Sellasist (Integracje → Klucze API).'); }
            $apiKey = (string) (OrderSecretBox::decrypt((string) $settings['secret'])['api_key'] ?? '');
        }
        if (strlen($apiKey) > 300 || preg_match('/[\r\n]/', $apiKey)) { throw new InvalidArgumentException('Nieprawidłowy klucz API.'); }
        [$status, $body] = $this->get($host, $apiKey, '/statuses', []);
        if ($status === 401 || $status === 403) { throw new InvalidArgumentException('Sellasist odrzucił klucz API (HTTP '.$status.'). Sprawdź klucz i jego uprawnienia.'); }
        if ($status < 200 || $status >= 300 || !is_array($body)) { throw new RuntimeException('Nie udało się połączyć z '.$host.' (HTTP '.$status.').'); }
        $resetCursors = $settings['account'] !== '' && $settings['account'] !== $host;
        $this->repo->saveSettings([
            'account' => $host,
            'secret' => OrderSecretBox::encrypt(['api_key' => $apiKey]),
            'key_hint' => '…'.substr($apiKey, -4),
            'enabled' => $settings['account'] === '' ? true : (bool) $settings['enabled'],
            'statuses' => $this->statusList($body),
        ]);
        if ($resetCursors) { $this->reset(); }
        return ['host' => $host, 'statuses' => count($body)];
    }

    public function setEnabled(bool $enabled): void
    {
        $settings = $this->repo->settings();
        $settings['enabled'] = $enabled;
        $this->repo->saveSettings($settings);
        if ($enabled) { $state = $this->repo->state(); $state['backoff_until'] = 0; $this->repo->saveState($state); }
    }

    /** Pobierz wszystko od początku (dane zostają, rekordy są aktualizowane). */
    public function reset(): void
    {
        $this->repo->saveState(['phases' => [], 'last_run' => '', 'last_error' => '', 'backoff_until' => 0, 'requests' => 0, 'started_at' => '', 'completed_at' => '']);
    }

    /**
     * Jedna porcja importu. $manual – uruchomione z przeglądarki (działa także przy wstrzymanym imporcie cron).
     * Zwraca raport: nowe/zaktualizowane rekordy, liczba zapytań, stan (busy/backoff/done).
     */
    public function run(int $budgetSeconds, bool $manual = false): array
    {
        $settings = $this->repo->settings();
        if ($settings['account'] === '' || $settings['secret'] === '') { return ['skipped' => 'not_configured']; }
        if (!$manual && empty($settings['enabled'])) { return ['skipped' => 'disabled']; }
        $state = $this->repo->state();
        if ((int) $state['backoff_until'] > time()) { return ['skipped' => 'backoff', 'retry_in' => (int) $state['backoff_until'] - time()]; }
        $db = $this->repo->db();
        if (!$db->acquireAdvisoryLock(self::LOCK)) { return ['skipped' => 'busy']; }
        $report = ['orders' => 0, 'documents' => 0, 'details' => 0, 'requests' => 0, 'errors' => []];
        $this->requests = 0;
        $deadline = microtime(true) + max(3, $budgetSeconds);
        try {
            $host = (string) $settings['account'];
            $apiKey = (string) (OrderSecretBox::decrypt((string) $settings['secret'])['api_key'] ?? '');
            if ($state['started_at'] === '') { $state['started_at'] = gmdate('Y-m-d H:i:s'); }
            $state['last_error'] = '';
            try {
                foreach (self::LIST_PHASES as $phase => [$path]) {
                    if (microtime(true) >= $deadline) { break; }
                    $this->listPhase($host, $apiKey, $phase, $path, $state, $report, $deadline, $manual);
                }
                $this->detailPhase($host, $apiKey, $state, $report, $deadline);
            } catch (ArchiveStopException $stop) {
                $state['last_error'] = $stop->getMessage();
                $state['backoff_until'] = time() + $stop->backoff;
                $report['errors'][] = $stop->getMessage();
            }
            $allListed = true;
            foreach (array_keys(self::LIST_PHASES) as $phase) { if (empty($state['phases'][$phase]['done'])) { $allListed = false; } }
            $pending = $this->repo->pendingOrderDetails(1) || $this->repo->pendingDocumentDetails(1);
            $report['done'] = $allListed && !$pending;
            if ($report['done'] && $state['completed_at'] === '') { $state['completed_at'] = gmdate('Y-m-d H:i:s'); }
            if (!$report['done']) { $state['completed_at'] = ''; }
            $state['last_run'] = gmdate('Y-m-d H:i:s');
            $state['requests'] = (int) $state['requests'] + $this->requests;
            $report['requests'] = $this->requests;
            $this->repo->saveState($state);
        } finally {
            $db->releaseAdvisoryLock(self::LOCK);
        }
        return $report;
    }

    private function listPhase(string $host, string $apiKey, string $phase, string $path, array &$state, array &$report, float $deadline, bool $manual): void
    {
        $ph = ($state['phases'][$phase] ?? []) + ['cursor' => 0, 'done' => false, 'checked_at' => 0, 'count' => 0, 'error' => ''];
        if ($ph['done'] && time() - (int) $ph['checked_at'] < ($manual ? 120 : self::RECHECK_SECONDS)) { return; }
        while (microtime(true) < $deadline) {
            // Rosnąco po ID: nowe rekordy dochodzą na końcu, więc offset pozostaje stabilny między porcjami.
            $query = ['limit' => self::PAGE, 'offset' => (int) $ph['cursor'], 'sort' => 'asc'] + (self::LIST_FILTERS[$phase] ?? []);
            // Moduł operacyjny jest w BETA – brak uprawnień klucza (403) nie może blokować reszty archiwum.
            [$status, $body] = $this->call($host, $apiKey, $path, $query, isset(self::LIST_FILTERS[$phase]));
            if ($status === 404) { $body = []; }
            elseif ($status < 200 || $status >= 300) {
                // Moduł niedostępny na koncie (np. brak paragonów) – etap jest pomijany i sprawdzany ponownie co 15 min.
                $ph['error'] = 'HTTP '.$status.': '.Http::reason($body, '');
                $ph['done'] = true;
                $ph['checked_at'] = time();
                $report['errors'][] = self::LIST_PHASES[$phase][1].': '.$ph['error'];
                break;
            }
            $rows = self::rows($body);
            if (!$rows) { $ph['done'] = true; $ph['checked_at'] = time(); $ph['error'] = ''; break; }
            $this->repo->db()->transaction(function () use ($rows, $phase) {
                foreach ($rows as $row) {
                    if ($phase === 'orders') { $this->repo->upsertOrder($row, false); }
                    elseif (isset(self::LIST_FILTERS[$phase])) { $kind = self::operationKind($phase, $row); if ($kind !== '') { $this->repo->upsertDocument($kind, $row, false); } }
                    else { $this->repo->upsertDocument($phase, $row, false); }
                }
            });
            $ph['cursor'] = (int) $ph['cursor'] + count($rows);
            $report[$phase === 'orders' ? 'orders' : 'documents'] += count($rows);
            $ph['count'] = (int) $ph['count'] + count($rows);
            $ph['error'] = '';
            $ph['done'] = false;
            $state['phases'][$phase] = $ph;
            $this->repo->saveState($state);
        }
        $state['phases'][$phase] = $ph;
    }

    private function detailPhase(string $host, string $apiKey, array &$state, array &$report, float $deadline): void
    {
        while (microtime(true) < $deadline) {
            $orderIds = $this->repo->pendingOrderDetails(10);
            $docs = $orderIds ? [] : $this->repo->pendingDocumentDetails(10);
            if (!$orderIds && !$docs) { return; }
            foreach ($orderIds as $id) {
                if (microtime(true) >= $deadline) { return; }
                [$status, $body] = $this->call($host, $apiKey, '/orders/'.$id, []);
                if ($status >= 200 && $status < 300 && is_array($body) && (int) ($body['id'] ?? 0) === $id) { $this->repo->upsertOrder($body, true); $report['details']++; }
                else { $this->repo->markOrderDetail($id, 2); $report['errors'][] = 'Zamówienie '.$id.': HTTP '.$status; }
            }
            foreach ($docs as $doc) {
                if (microtime(true) >= $deadline) { return; }
                $kind = (string) $doc['kind']; $id = (int) $doc['remote_id'];
                $operation = SellasistArchiveRepository::baseKind($kind) !== $kind;
                [$status, $body] = $this->call($host, $apiKey, ($operation ? self::OPERATION_DETAIL_PATH : self::DETAIL_PATHS[$kind]).$id, [], $operation);
                if ($status >= 200 && $status < 300 && is_array($body) && $body) {
                    $body = isset($body[0]) && is_array($body[0]) ? $body[0] : $body;
                    if (empty($body['id'])) { $body['id'] = $id; }
                    if ($operation) {
                        $actual = self::operationKind(strpos($kind, 'correct') !== false ? 'op_correct' : 'op_sale', $body) ?: $kind;
                        if ($actual === 'op_correct' && !empty($body['main_document_id']) && $this->repo->hasDocument('op_receipt', (int) $body['main_document_id'])) { $actual = 'op_receipt_correct'; }
                        $kind = $actual;
                    }
                    $this->repo->upsertDocument($kind, $body, true); $report['details']++;
                } else { $this->repo->markDocumentDetail($kind, $id, 2); $report['errors'][] = SellasistArchiveRepository::DOC_KINDS[SellasistArchiveRepository::baseKind($kind)].' '.$id.': HTTP '.$status; }
            }
            $report['errors'] = array_slice($report['errors'], -20);
        }
    }

    /**
     * Rodzaj dokumentu operacyjnego z pól type/subtype (API podaje je różnie: type=sale + subtype=receipt albo odwrotnie).
     * Pusty wynik = dokument magazynowy (WZ, PZ, MM, rezerwacja) – pomijany.
     */
    private static function operationKind(string $phase, array $row): string
    {
        $text = '';
        foreach (['type', 'subtype'] as $field) { if (is_scalar($row[$field] ?? null)) { $text .= ' '.strtolower((string) $row[$field]); } }
        if (preg_match('/reservation|release|admission|\bmov\b|stock/', $text)) { return ''; }
        if ($phase === 'op_correct' || strpos($text, 'correct') !== false) { return 'op_correct'; }
        foreach (['receipt' => 'op_receipt', 'proforma' => 'op_proforma', 'bill' => 'op_bill', 'invoice' => 'op_invoice'] as $needle => $kind) {
            if (strpos($text, $needle) !== false) { return $kind; }
        }
        return 'op_invoice';
    }

    /** Zapytanie z obsługą limitów: 429 i 5xx wstrzymują import na chwilę, 401/403 na dłużej ($optional: 403 zwracany jako wynik). */
    private function call(string $host, string $apiKey, string $path, array $query, bool $optional = false): array
    {
        if ($this->requests > 0 && self::$pauseMicro > 0) { usleep(self::$pauseMicro); }
        $this->requests++;
        try {
            $result = $this->get($host, $apiKey, $path, $query);
        } catch (\RuntimeException $e) {
            throw new ArchiveStopException('Błąd połączenia z Sellasist: '.$e->getMessage(), 120);
        }
        [$status, $body] = $result;
        if ($status === 429) { throw new ArchiveStopException('Sellasist: przekroczono limit zapytań – import wznowi się za minutę.', 60); }
        if ($status === 403 && $optional) { return $result; }
        if ($status === 401 || $status === 403) { throw new ArchiveStopException('Sellasist odrzucił klucz API (HTTP '.$status.'). Zaktualizuj klucz w ustawieniach archiwum.', 900); }
        if ($status >= 500) { throw new ArchiveStopException('Sellasist chwilowo nie odpowiada (HTTP '.$status.') – ponowienie za 2 min.', 120); }
        return $result;
    }

    /** @return array{0:int,1:mixed} */
    private function get(string $host, string $apiKey, string $path, array $query): array
    {
        $url = 'https://'.$host.'/api/v1'.$path.($query ? '?'.Http::query($query) : '');
        $response = null;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $response = Http::request('Sellasist', 'GET', $url, ['accept: application/json', 'apiKey: '.$apiKey], null, 40, false, CURL_HTTP_VERSION_1_1);
                break;
            } catch (\RuntimeException $e) {
                if ($attempt === 1 || strpos($e->getMessage(), 'błąd połączenia cURL') === false) { throw $e; }
                usleep(300000);
            }
        }
        $decoded = trim($response['body']) === '' ? [] : json_decode($response['body'], true);
        return [(int) $response['status'], $decoded];
    }

    /** Lista rekordów z odpowiedzi (tablica albo obiekt z polem data/items). */
    private static function rows($body): array
    {
        if (!is_array($body)) { return []; }
        foreach (['data', 'items', 'results'] as $key) { if (isset($body[$key]) && is_array($body[$key])) { $body = $body[$key]; break; } }
        if ($body && array_keys($body) !== range(0, count($body) - 1)) { return isset($body['id']) ? [$body] : []; }
        return array_values(array_filter($body, static function ($row) { return is_array($row) && !empty($row['id']); }));
    }

    private function statusList(array $body): array
    {
        $list = [];
        foreach ($body as $row) { if (is_array($row) && isset($row['id'])) { $list[(int) $row['id']] = mb_substr((string) ($row['name'] ?? ''), 0, 150, 'UTF-8'); } }
        return $list;
    }

    /** Stan do widoku: etapy, liczniki i opis. */
    public function progress(): array
    {
        $settings = $this->repo->settings();
        $state = $this->repo->state();
        $stats = $this->repo->stats();
        $phases = [];
        foreach (self::LIST_PHASES as $phase => [, $label]) {
            $ph = ($state['phases'][$phase] ?? []) + ['done' => false, 'count' => 0, 'error' => '', 'checked_at' => 0];
            if ($phase === 'orders') { $stored = $stats['orders']; }
            elseif (isset(self::LIST_FILTERS[$phase])) {
                $stored = 0;
                foreach ($stats['raw_kinds'] as $kind => $count) {
                    if (SellasistArchiveRepository::baseKind((string) $kind) !== $kind && (strpos((string) $kind, 'correct') !== false) === ($phase === 'op_correct')) { $stored += $count; }
                }
            } else { $stored = (int) ($stats['raw_kinds'][$phase] ?? 0); }
            $phases[] = ['key' => $phase, 'label' => $label, 'done' => (bool) $ph['done'], 'count' => (int) $ph['count'], 'error' => (string) $ph['error'], 'stored' => $stored];
        }
        $detailTotal = $stats['orders'] + $stats['documents'];
        $detailDone = $stats['orders_detailed'] + $stats['orders_failed'] + $stats['documents_detailed'] + $stats['documents_failed'];
        $backoff = (int) $state['backoff_until'] > time() ? (int) $state['backoff_until'] - time() : 0;
        return [
            'configured' => $settings['account'] !== '' && $settings['secret'] !== '',
            'enabled' => !empty($settings['enabled']),
            'account' => (string) $settings['account'],
            'key_hint' => (string) $settings['key_hint'],
            'phases' => $phases,
            'stats' => $stats,
            'details_total' => $detailTotal,
            'details_done' => $detailDone,
            'details_percent' => $detailTotal ? (int) floor($detailDone * 100 / $detailTotal) : 0,
            'lists_done' => !in_array(false, array_column($phases, 'done'), true),
            'completed' => $state['completed_at'] !== '',
            'last_run' => (string) $state['last_run'],
            'last_error' => (string) $state['last_error'],
            'backoff' => $backoff,
            'requests' => (int) $state['requests'],
        ];
    }

    /** Zadanie cron dla aktywnej firmy: działa tylko, gdy archiwum skonfigurowano i import jest włączony. */
    public static function cron(\App\Core\Database $db, int $budgetSeconds = 30): array
    {
        $repo = new SellasistArchiveRepository($db);
        if (!$db->fetchColumn("SELECT value_json FROM om_settings WHERE setting_key='sellasist_archive'")) { return ['skipped' => 'not_configured']; }
        $repo->ensureSchema();
        return (new self($repo))->run($budgetSeconds, false);
    }
}

/** Przerywa bieżącą porcję importu i wstrzymuje kolejne na $backoff sekund. */
final class ArchiveStopException extends RuntimeException
{
    /** @var int */
    public $backoff;

    public function __construct(string $message, int $backoff)
    {
        parent::__construct($message);
        $this->backoff = $backoff;
    }
}
