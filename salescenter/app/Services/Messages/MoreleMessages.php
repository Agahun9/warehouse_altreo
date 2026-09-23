<?php

declare(strict_types=1);
namespace App\Services\Messages;

use App\Models\MessageRepository;
use App\Services\MoreleService;
use InvalidArgumentException;

/**
 * Morele – Centrum komunikacji. Kształt odpowiedzi potwierdzony logiem produkcyjnym:
 * GET /communication-center/threads (start, limit, type_id, needs_answer, date_created_from, date_created_to,
 * thread_id, resource_id) zwraca {"data":[...],"total":N}, posortowane od najnowszych wątków.
 * Wątek: id (długi identyfikator base64 – używany też jako thread_id w filtrze), sender (nazwa klienta),
 * createdAt (założenie wątku, NIE ostatnia wiadomość), subject, resourceId (numer zamówienia), needsResponse,
 * messages[] – pełna treść wątku jest już na liście, więc osobne pobieranie szczegółów nie jest potrzebne.
 * Wiadomość: sender/receiver (adresy e-mail), isSentByOperator, messageBody (HTML zakodowany encjami),
 * attachments[] (adresy URL), createdAt; kolejność od najnowszej.
 * Rodzaju wątku API nie podaje – odczytujemy go z tematu („Pytanie o zamówienie nr: …”).
 * POST /communication-center/message (application/json) wysyła odpowiedź. Trasy panelu
 * (/thread/list, /thread/{no}, /send) zwracają 403 – klucz API ich nie obsługuje.
 * Rodzaje wątków (typeId): 1 pytanie o zamówienie, 2 pytanie o produkt, 3 inne, 4 reklamacja, 5 zwrot 14-dniowy.
 */
final class MoreleMessages implements MessageSource
{
    public const TYPES = [1 => 'Pytanie o zamówienie', 2 => 'Pytanie o produkt', 3 => 'Inne', 4 => 'Reklamacja', 5 => 'Zwrot 14-dniowy'];

    /** Właściwy zasób API marketplace; /communication-center/thread/list to trasa panelu (HTTP 403). */
    private const THREADS = '/communication-center/threads';
    private const PAGE = 10;
    /** Limit wątków dociąganych w jednym przebiegu: zwykła synchronizacja i tryb „pobierz starsze”. */
    private const RUN_CAP = 80;
    private const FULL_CAP = 250;
    /** Panel Morele podaje daty bez strefy, w czasie polskim. */
    private const ZONE = 'Europe/Warsaw';

    /** @var MoreleService */
    private $api;
    /** @var int Indeks adresu szczegółów wątku, który zadziałał; -1 = jeszcze nie sprawdzono. */
    private $detailPath = -1;

    /** @var bool Czy pisać do tymczasowego logu diagnostycznego (ustawienie kanału „Log diagnostyczny”). */
    private $logging = false;

    public function __construct(?MoreleService $api = null) { $this->api = $api ?? new MoreleService(); }

    /** Włącza log surowych odpowiedzi API i decyzji synchronizacji. */
    private function logging(array $settings): void
    {
        $this->logging = !empty($settings['debug_log']);
        $this->api->logger = $this->logging ? static function (string $event, $data): void { MoreleLog::write($event, $data); } : null;
    }

    private function log(string $event, $data = null): void
    {
        if ($this->logging) { MoreleLog::write($event, $data); }
    }

    public function fetch(array $account, array $settings, array $state, MessageRepository $repo, callable $emit): array
    {
        $state['_errors'] = [];
        $state['_listed'] = 0;
        $state['_notes'] = [];
        $this->logging($settings);
        $this->log('--- synchronizacja Morele: start', ['connection_id' => $account['connection_id'] ?? 0, 'sync_messages' => (int) !empty($settings['sync_messages']), 'history_days' => $settings['history_days'] ?? null]);
        if (empty($settings['sync_messages'])) {
            $state['_notes'][] = 'Wiadomości Morele są wyłączone w ustawieniach kanału – zaznacz „Wiadomości”.';
            return $state;
        }
        // Tryb „pobierz starsze”: bez pomijania po znaczniku czasu i bez skrótów dla wątków, które lokalnie wyglądają na aktualne.
        $full = !empty($state['force_full']);
        $budget = $full ? self::FULL_CAP : self::RUN_CAP;
        $connectionId = (int) $account['connection_id'];
        $since = (string) ($state['threads_since'] ?? gmdate('Y-m-d H:i:s', time() - (int) $settings['history_days'] * 86400));
        $newest = $since;
        $empties = [];
        // Najpierw wątki czekające na odpowiedź (filtr needs_answer). Nowa wiadomość klienta w starym wątku
        // nie musi zmienić pól daty rozpoznawanych z listy, więc takie wątki dociągamy w całości za każdym razem.
        $seen = [];
        $awaiting = $this->awaiting($account);
        $this->log('wątki czekające na odpowiedź (needs_answer)', ['liczba' => count($awaiting)]);
        foreach ($awaiting as $item) {
            $no = self::pick($item, ['no', 'threadNo', 'thread_no', 'number', 'identifier', 'id']);
            if ($no === '' || isset($seen[$no])) { continue; }
            $seen[$no] = true;
            $budget--;
            try { $thread = $this->mapThread($account, $item, true); }
            catch (\RuntimeException $e) { $state['_errors'][] = 'Morele: wątek '.$no.': '.$e->getMessage(); continue; }
            if (!array_filter(array_column($thread['messages'], 'body'), 'strlen')) { $empties[] = $no; }
            $this->log('wątek pobrany (czeka na odpowiedź)', ['no' => $no, 'temat' => $thread['subject'], 'wiadomości' => count($thread['messages']), 'meta' => $thread['meta']]);
            $newest = max($newest, (string) $thread['last_message_at']);
            $emit($thread);
        }
        $start = 0;
        $reverse = false;
        for ($page = 0; $page < ($full ? 100 : 30); $page++) {
            try {
                $response = $this->list($account, $start);
            } catch (\RuntimeException $e) {
                if ($page > 0) { throw $e; }
                // Odmowa dostępu nic nie mówi bez kontekstu – dokładamy wynik diagnostyki wprost do błędu przy koncie.
                $state['_errors'][] = $e->getMessage().' '.$this->diagnosis($account, $state);
                return $state;
            }
            $items = self::items($response);
            // Bez specyfikacji Morele nie zgadujemy w ciemno: nierozpoznana odpowiedź ma być widoczna przy koncie.
            if ($page === 0 && !self::hasList($response)) {
                $state['_errors'][] = 'Morele zwróciło listę wątków w nieznanym formacie (klucze odpowiedzi: '.(implode(', ', array_slice(array_keys($response), 0, 10)) ?: 'brak').'). Użyj „Diagnostyka Morele” i prześlij wynik.';
                break;
            }
            $state['_listed'] += count($items);
            $older = 0;
            foreach ($items as $item) {
                $no = self::pick($item, ['no', 'threadNo', 'thread_no', 'number', 'identifier', 'id']);
                if ($no === '' || isset($seen[$no])) { continue; }
                $last = MessageRepository::date(self::lastAt($item), self::ZONE);
                $needs = self::needsResponse($item);
                if ($last !== '' && $last <= $since && !$needs) {
                    $older++;
                    if (!$full) { $this->log('wątek pominięty: starszy niż znacznik', ['no' => $no, 'ostatnia_wiadomość' => $last, 'znacznik' => $since, 'klucze' => array_keys($item)]); continue; }
                }
                $newest = max($newest, $last);
                $closed = self::isClosed($item);
                $local = $repo->findByExternal($connectionId, 'message', $no);
                // Wątek bez treści dociągamy zawsze – inaczej nieudane mapowanie zostałoby z nami na stałe.
                // Pusty podgląd znaczy, że wiadomości są, ale wszystkie mają pustą treść (źle odczytane pola).
                // Niekompletne meta znaczy, że z wątku nie da się odpowiedzieć – też wymaga ponownego pobrania.
                $meta = (array) ($local['meta'] ?? []);
                $empty = $local && ((int) $local['message_count'] === 0 || trim((string) $local['last_preview']) === ''
                    || empty($meta['thread_id']) || empty($meta['type_id']));
                if (!$full && $local && !$empty && (string) $local['last_message_at'] >= $last && (bool) $local['needs_reply'] === $needs && (bool) $local['remote_closed'] === $closed) {
                    $this->log('wątek pominięty: lokalnie aktualny', ['no' => $no, 'lokalna_ostatnia' => (string) $local['last_message_at'], 'z_listy' => $last]);
                    continue;
                }
                // Wątek zamknięty w Morele: sam status, bez dociągania szczegółów (przy „pobierz starsze” dociągamy też treść).
                if (!$full && $closed && $local && !$empty) {
                    $emit(['kind' => 'message', 'external_id' => $no, 'remote_status' => 'CLOSED', 'closed' => true, 'needs_reply' => false, 'last_message_at' => $last, 'messages' => null]);
                    continue;
                }
                if ($budget-- <= 0) { $this->log('limit wątków w przebiegu osiągnięty', ['no' => $no]); break 2; }
                $thread = $this->mapThread($account, $item);
                $this->log('wątek pobrany', ['no' => $no, 'temat' => $thread['subject'], 'wiadomości' => count($thread['messages']), 'meta' => $thread['meta']]);
                // Same puste treści są tak samo bezużyteczne jak brak wiadomości – zgłaszamy oba przypadki.
                if (!array_filter(array_column($thread['messages'], 'body'), 'strlen')) { $empties[] = $no; }
                $emit($thread);
            }
            $total = (int) ($response['filtered'] ?? $response['total'] ?? $response['count'] ?? 0);
            // Kolejność listy nie jest udokumentowana. Przy sortowaniu od najstarszych najnowsze wątki są na końcu –
            // wtedy po pierwszej stronie przechodzimy od ostatniej strony wstecz.
            if ($page === 0 && $total > self::PAGE && self::ascending($items)) {
                $reverse = true;
                $start = (int) (ceil($total / self::PAGE) - 1) * self::PAGE;
                continue;
            }
            // „Cała strona starsza” kończy przegląd tylko wtedy, gdy idziemy od najnowszych i nie pobieramy historii.
            $allOlder = !$full && $items && $older === count($items);
            if ($reverse) {
                $start -= self::PAGE;
                if ($start < self::PAGE || $allOlder) { break; }
                continue;
            }
            if (count($items) < self::PAGE || $allOlder || ($total > 0 && $start + self::PAGE >= $total)) { break; }
            $start += self::PAGE;
        }
        // Wątek bez treści znaczy, że pola wiadomości nazywają się inaczej – dokładamy surową odpowiedź API.
        if ($empties) {
            $state['_errors'][] = 'Morele: wątki bez rozpoznanych treści wiadomości ('.implode(', ', array_slice($empties, 0, 5)).'). '.$this->shapeHint($account, $state);
        }
        $this->log('--- synchronizacja Morele: koniec', ['wątki_na_liście' => $state['_listed'], 'błędy' => $state['_errors']]);
        if ($full) {
            unset($state['force_full']);
            $state['_notes'][] = $budget > 0
                ? 'Morele: przejrzano historię wątków do '.mb_substr($since, 0, 10, 'UTF-8').'.'
                : 'Morele: osiągnięto limit '.self::FULL_CAP.' wątków w jednym przebiegu – uruchom „Pobierz starsze” ponownie, aby pobrać kolejne.';
        }
        $state['threads_since'] = $newest;
        return $state;
    }

    /** Zrzut kształtu odpowiedzi przy błędzie mapowania, nie częściej niż raz na godzinę. */
    private function shapeHint(array $account, array &$state): string
    {
        if (empty($state['shape']) || (int) ($state['shape_at'] ?? 0) < time() - 3600) {
            try { $state['shape'] = $this->diagnoseShape($account); }
            catch (\Throwable $e) { $state['shape'] = 'zrzut nieudany: '.$e->getMessage(); }
            $state['shape_at'] = time();
        }
        return 'Surowa odpowiedź: '.$state['shape'];
    }

    /**
     * Lista wątków. Zestaw parametrów podało samo API w odpowiedzi 400 na błędne zapytanie:
     * start, limit, type_id, needs_answer, date_created_from, date_created_to, thread_id, resource_id.
     * Dat nie filtrujemy po stronie Morele – tak jak przy /orders serwer bywa wybredny co do formatu.
     */
    private function list(array $account, int $start): array
    {
        return $this->api->api($account, 'GET', self::THREADS, ['start' => $start, 'limit' => self::PAGE]);
    }

    /** Limit wątków czekających na odpowiedź dociąganych w jednym przebiegu. */
    private const AWAITING_CAP = 40;

    /**
     * Wątki z filtrem needs_answer=1. Filtrowi ufamy tylko wtedy, gdy liczniki odpowiedzi potwierdzają,
     * że serwer go zastosował (filtered ≤ needResponse albo filtered < total) – inaczej każdy wątek
     * zostałby błędnie oznaczony jako czekający. Błąd tej listy nie przerywa zwykłej synchronizacji.
     */
    private function awaiting(array $account): array
    {
        $found = [];
        for ($start = 0; $start < self::AWAITING_CAP; $start += self::PAGE) {
            try { $response = $this->api->api($account, 'GET', self::THREADS, ['start' => $start, 'limit' => self::PAGE, 'needs_answer' => 1]); }
            catch (\RuntimeException $e) { return $found; }
            if (!isset($response['filtered'])) { return $found; }
            $filtered = (int) $response['filtered'];
            $trusted = isset($response['needResponse']) ? $filtered <= (int) $response['needResponse'] : $filtered < (int) ($response['total'] ?? 0);
            if (!$trusted) { return $found; }
            $items = self::items($response);
            foreach ($items as $item) { $found[] = (array) $item; }
            if (count($items) < self::PAGE || $start + self::PAGE >= $filtered) { break; }
        }
        return $found;
    }

    /** Czy strona listy jest posortowana od najstarszych (pierwsza data < ostatniej). */
    private static function ascending(array $items): bool
    {
        $dates = array_values(array_filter(array_map(static function ($item): string { return is_array($item) ? MessageRepository::date(self::lastAt($item), self::ZONE) : ''; }, $items), 'strlen'));
        return count($dates) >= 2 && $dates[0] < $dates[count($dates) - 1];
    }

    private static function isClosed(array $item): bool
    {
        foreach (['closed', 'is_closed', 'isClosed'] as $key) {
            if (array_key_exists($key, $item)) { return !empty($item[$key]); }
        }
        $status = mb_strtolower(self::pick($item, ['status', 'state', 'thread_status']), 'UTF-8');
        return in_array($status, ['closed', 'zamkniety', 'zamknięty', 'finished', 'archived'], true);
    }

    /** Klucze, pod którymi Morele może zwrócić listę wątków (specyfikacji brak – sprawdzamy wszystkie znane). */
    private const LIST_KEYS = ['data', 'threads', 'items', 'list', 'results', 'rows', 'content'];

    /** Wątki bywają listą albo mapą (no => wątek), pod różnymi kluczami – rozpakowujemy oba warianty. */
    private static function items(array $response): array
    {
        foreach (self::LIST_KEYS as $key) {
            if (is_array($response[$key] ?? null)) { return self::items($response[$key]); }
        }
        $values = array_values(array_filter($response, 'is_array'));
        return $values !== [] && count($values) === count($response) ? $values : [];
    }

    /** Czy odpowiedź zawiera kontener listy (pusta lista to poprawny wynik, brak kontenera – nie). */
    private static function hasList(array $response): bool
    {
        if ($response === []) { return true; }
        foreach (self::LIST_KEYS as $key) {
            if (is_array($response[$key] ?? null)) { return true; }
        }
        return count(array_filter($response, 'is_array')) === count($response);
    }

    private static function pick(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_scalar($data[$key]) && trim((string) $data[$key]) !== '') { return trim((string) $data[$key]); }
        }
        return '';
    }

    /**
     * Ostatnia aktywność wątku. Morele podaje w wątku tylko createdAt (data założenia), więc bierzemy
     * najnowszą z dołączonych wiadomości – inaczej stary wątek z nową wiadomością wyglądałby na stary.
     */
    private static function lastAt(array $item): string
    {
        $last = self::pick($item, ['lastResponseAt', 'last_response_at', 'last_response_date', 'lastMessageAt', 'last_message_at', 'last_message_date', 'lastMessageDate', 'date_last_message',
            'updatedAt', 'updated_at', 'date_updated', 'dateUpdated', 'date_modified', 'modified_at', 'createdAt', 'created_at', 'date_created', 'dateCreated', 'date']);
        foreach (self::rawMessages($item) as $message) {
            $at = self::pick($message, self::MESSAGE_DATE_KEYS);
            if ($at > $last) { $last = $at; }
        }
        return $last;
    }

    /** Wiadomości dołączone wprost do wątku na liście (Morele zwraca je razem z wątkiem). */
    private static function rawMessages(array $item): array
    {
        return is_array($item['messages'] ?? null) ? array_values(array_filter($item['messages'], 'is_array')) : [];
    }

    private static function needsResponse(array $item): bool
    {
        // needs_answer to nazwa filtra listy w API, needResponse – licznik w odpowiedzi; pole wątku bywa nazwane podobnie.
        foreach (['needsResponse', 'needs_response', 'needResponse', 'need_response', 'needs_answer', 'needsAnswer', 'need_answer', 'needAnswer',
            'waitingForResponse', 'waiting_for_response', 'requiresResponse'] as $key) {
            if (array_key_exists($key, $item)) { return !empty($item[$key]); }
        }
        return false;
    }

    /** Ścieżki sprawdzane w diagnostyce – pozostałe zasoby centrum komunikacji nie mają specyfikacji. */
    private const PROBE_PATHS = [self::THREADS, '/communication-center/messages', '/communication-center/message', '/communication-center/send', '/communication-center/thread-types'];

    /**
     * Surowe odpowiedzi Morele dla kandydujących adresów – do pokazania w panelu.
     * /orders to punkt kontrolny: jego HTTP 200 dowodzi, że sam token działa, więc odmowa na centrum
     * komunikacji jest brakiem uprawnień po stronie Morele, a nie błędem autoryzacji w SalesCenter.
     */
    public function diagnose(array $account): string
    {
        $query = ['start' => 0, 'limit' => 2];
        $report = [];
        $control = $this->api->probe($account, 'GET', '/orders');
        $report[] = 'kontrola tokenu /orders → HTTP '.$control['status'].(($control['status'] >= 200 && $control['status'] < 300) ? ' (token działa)' : ': '.self::flat($control['body']));
        foreach (self::PROBE_PATHS as $path) {
            $result = $this->api->probe($account, 'GET', $path, $query);
            $report[] = $path.' → HTTP '.$result['status'].(self::flat($result['body']) === '' ? ' (pusta odpowiedź)' : ': '.self::flat($result['body']));
        }
        // Panel sprzedawcy woła te zasoby XHR-em – sprawdzamy, czy 403 nie wynika z braku nagłówków przeglądarki.
        $asPanel = $this->api->probe($account, 'GET', self::PROBE_PATHS[0], $query, ['X-Requested-With: XMLHttpRequest', 'Referer: https://marketplace.morele.net/']);
        $report[] = self::PROBE_PATHS[0].' (nagłówki panelu) → HTTP '.$asPanel['status'].': '.self::flat($asPanel['body']);
        return implode(' | ', $report);
    }

    /**
     * Surowa lista i surowe szczegóły pierwszego wątku – stąd odczytujemy prawdziwe nazwy pól,
     * gdy wątek się zaciąga, ale któregoś fragmentu (np. treści wiadomości) brakuje.
     */
    public function diagnoseShape(array $account): string
    {
        $list = $this->api->probe($account, 'GET', self::THREADS, ['start' => 0, 'limit' => 1], [], 900);
        $report = ['lista → HTTP '.$list['status'].': '.self::flat($list['body'])];
        $no = '';
        try {
            $first = self::items($this->api->api($account, 'GET', self::THREADS, ['start' => 0, 'limit' => 1]));
            $no = $first ? self::pick((array) $first[0], ['no', 'threadNo', 'thread_no', 'number', 'identifier', 'id']) : '';
        } catch (\Throwable $e) { $report[] = 'odczyt listy nieudany: '.$e->getMessage(); }
        if ($no !== '') {
            $detail = $this->api->probe($account, 'GET', self::THREADS, ['thread_id' => $no], [], 1200);
            $report[] = 'szczegóły thread_id='.$no.' → HTTP '.$detail['status'].': '.self::flat($detail['body']);
        }
        return implode(' | ', $report);
    }

    /**
     * Zasób wysyłki (POST, application/json; multipart kończy się 500 „JsonException”).
     * Pola: typeId (thread.type_id), resourceIdentifier (thread.resource_id), messageBody. Klucz „identifier”
     * z formularza panelu API odrzuca („Expected the key "identifier" to not exist.”).
     * API sprawdza obecność kluczy po kolei („Expected the key "typeId" to exist.”).
     */
    private const SEND = '/communication-center/message';
    /** Specyfikacja OpenAPI marketplace (ta sama, którą pokazuje panel w zakładce API → dokumentacja). */
    private const SPEC = '/v1/docs';

    /**
     * Kontrakt wysyłki do pokazania przy błędzie: opis operacji ze specyfikacji Morele
     * oraz odpowiedź walidacji na pusty obiekt (odrzucany przed zapisem – nic nie zostaje wysłane).
     */
    public function diagnoseSend(array $account): string
    {
        $post = $this->api->probe($account, 'POST', self::SEND, [], [], 400, null, []);
        return 'specyfikacja: '.$this->specFor($account, 'communication-center').' | POST {} → HTTP '.$post['status'].': '.self::flat($post['body']);
    }

    /** Operacje ze specyfikacji /v1/docs, których ścieżka zawiera $needle (parametry i schemat treści). */
    public function specFor(array $account, string $needle, int $limit = 3000): string
    {
        $docs = $this->api->probe($account, 'GET', self::SPEC, [], [], 5000000);
        if ($docs['status'] !== 200) { return self::SPEC.' → HTTP '.$docs['status'].': '.mb_substr(self::flat($docs['body']), 0, 200, 'UTF-8'); }
        $spec = json_decode($docs['body'], true);
        if (!is_array($spec) || !is_array($spec['paths'] ?? null)) { return self::SPEC.' zwróciło nie-JSON: '.mb_substr(self::flat($docs['body']), 0, 200, 'UTF-8'); }
        $found = [];
        foreach ($spec['paths'] as $path => $operations) {
            if (strpos((string) $path, $needle) === false || !is_array($operations)) { continue; }
            foreach ($operations as $method => $operation) {
                if (!is_array($operation)) { continue; }
                $found[strtoupper((string) $method).' '.$path] = self::resolve($spec, array_intersect_key($operation, array_flip(['summary', 'parameters', 'requestBody', 'consumes'])));
            }
        }
        if (!$found) { return 'brak ścieżek „'.$needle.'” w '.self::SPEC.' ('.count($spec['paths']).' ścieżek)'; }
        return mb_substr((string) json_encode($found, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, $limit, 'UTF-8');
    }

    /** Rozwija lokalne odwołania $ref (#/components/..., #/definitions/...) – bez nich schemat nic nie mówi. */
    private static function resolve(array $spec, $node, int $depth = 0)
    {
        if (!is_array($node) || $depth > 6) { return $node; }
        if (isset($node['$ref']) && is_string($node['$ref']) && strpos($node['$ref'], '#/') === 0) {
            $target = $spec;
            foreach (explode('/', substr($node['$ref'], 2)) as $part) { $target = is_array($target) ? ($target[$part] ?? null) : null; }
            return self::resolve($spec, $target, $depth + 1);
        }
        foreach ($node as $key => $value) { $node[$key] = self::resolve($spec, $value, $depth + 1); }
        return $node;
    }

    /** Diagnostyka przy błędzie synchronizacji, nie częściej niż raz na godzinę (to kilka dodatkowych żądań). */
    private function diagnosis(array $account, array &$state): string
    {
        if (empty($state['diagnosis']) || (int) ($state['diagnosis_at'] ?? 0) < time() - 3600) {
            try { $state['diagnosis'] = $this->diagnose($account); }
            catch (\Throwable $e) { $state['diagnosis'] = 'diagnostyka nieudana: '.$e->getMessage(); }
            $state['diagnosis_at'] = time();
        }
        return 'Diagnostyka: '.$state['diagnosis'];
    }

    private static function flat(string $body): string
    {
        return trim(preg_replace('/\s+/', ' ', $body) ?? $body);
    }

    /**
     * Szczegóły wątku. /communication-center/thread/{no} nie istnieje (API: „No route found”), więc
     * pobieramy je filtrem thread_id na liście; adres, który odpowie, zostaje na czas przebiegu.
     */
    private function detail(array $account, string $no): array
    {
        $candidates = [[self::THREADS, ['thread_id' => $no]], ['/communication-center/messages', ['thread_id' => $no]]];
        if ($this->detailPath >= 0) {
            [$path, $query] = $candidates[$this->detailPath];
            return $this->api->api($account, 'GET', $path, $query);
        }
        $last = null;
        foreach ($candidates as $index => [$path, $query]) {
            try {
                $detail = $this->api->api($account, 'GET', $path, $query);
                $this->detailPath = $index;
                return $detail;
            } catch (\RuntimeException $e) { $last = $e; }
        }
        throw $last;
    }

    /** Nagłówek wątku: klucz „thread”, zagnieżdżone „data”, albo pierwsza pozycja listy. */
    private static function threadOf(array $detail): array
    {
        foreach (['thread', 'data'] as $key) {
            $value = $detail[$key] ?? null;
            if (is_array($value) && isset($value['thread']) && is_array($value['thread'])) { return $value['thread']; }
            if (is_array($value) && !isset($value[0])) { return $value; }
        }
        $items = self::items($detail);
        return $items ? (array) $items[0] : [];
    }

    /** Wiadomości wątku – pod kluczem „messages” na dowolnym z dwóch pierwszych poziomów. */
    private static function messagesOf(array $detail): array
    {
        $candidates = [$detail['messages'] ?? null, $detail['data']['messages'] ?? null, $detail['thread']['messages'] ?? null];
        foreach (self::items($detail) as $item) { $candidates[] = $item['messages'] ?? null; }
        foreach ($candidates as $messages) {
            if (is_array($messages) && $messages !== []) { return array_values(array_filter($messages, 'is_array')); }
        }
        return [];
    }

    private static function sender(array $message): string
    {
        return self::pick($message, ['sender', 'sender_name', 'senderName', 'author_name', 'authorName', 'author', 'user_name', 'userName', 'login']);
    }

    private static function flag(array $data, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) { return !empty($data[$key]); }
        }
        return false;
    }

    /**
     * Autor wiadomości. U Morele rozstrzyga adres nadawcy: wszystko z adresu klienta to wiadomość klienta.
     * isSentByOperator nie mówi, kto pisze – bot Morele wysyła powiadomienia z adresu klienta – więc nie
     * używamy go do ustalenia roli.
     */
    private static function role(array $message, string $sender, string $customer): string
    {
        if (self::flag($message, ['sent_by_vendor', 'sentByVendor', 'is_vendor', 'vendor', 'is_seller', 'sent_by_seller'])) { return 'seller'; }
        if (self::flag($message, ['sent_by_client', 'sentByClient', 'is_client', 'is_customer'])) { return 'customer'; }
        $type = mb_strtolower(self::pick($message, ['sender_type', 'senderType', 'author_type', 'type', 'direction']), 'UTF-8');
        if (in_array($type, ['vendor', 'seller', 'shop', 'sprzedawca', 'outgoing', 'out'], true)) { return 'seller'; }
        if (in_array($type, ['client', 'customer', 'buyer', 'klient', 'incoming', 'in'], true)) { return 'customer'; }
        if ($sender === '' || $customer === '') { return 'customer'; }
        return mb_strtolower($sender, 'UTF-8') === mb_strtolower($customer, 'UTF-8') ? 'customer' : 'seller';
    }

    /** Załączniki: mapa nazwa=>url, lista obiektów albo ta sama mapa zakodowana jako JSON. */
    private static function files(array $message): array
    {
        $value = $message['attachments'] ?? $message['files'] ?? null;
        if (is_string($value)) { $value = json_decode($value, true); }
        $files = [];
        foreach ((array) $value as $name => $entry) {
            if (is_array($entry)) {
                $url = self::pick($entry, ['url', 'src', 'link', 'path']);
                if ($url !== '') { $files[] = ['name' => self::pick($entry, ['name', 'filename', 'title']) ?: 'załącznik', 'url' => $url]; }
                continue;
            }
            $url = is_string($entry) ? $entry : '';
            $files[] = ['name' => is_string($name) ? $name : (self::fileName($url) ?: 'załącznik'), 'url' => $url];
        }
        return $files;
    }

    /** Nazwa załącznika z adresu URL (Morele podaje same adresy). */
    private static function fileName(string $url): string
    {
        $name = rawurldecode((string) parse_url($url, PHP_URL_PATH));
        return mb_substr(basename($name), 0, 120, 'UTF-8');
    }

    /** Gdy API nie poda rodzaju wątku, odczytujemy go z tematu („Pytanie o zamówienie nr: …”). */
    private static function typeFromSubject(string $subject): int
    {
        $subject = mb_strtolower($subject, 'UTF-8');
        foreach (self::TYPES as $id => $label) {
            if ($subject !== '' && mb_strpos($subject, mb_strtolower($label, 'UTF-8'), 0, 'UTF-8') === 0) { return $id; }
        }
        return 0;
    }

    private const BODY_KEYS = ['message_body', 'messageBody', 'body', 'message', 'content', 'text', 'description'];
    private const ID_KEYS = ['no', 'threadNo', 'thread_no', 'number', 'identifier', 'id'];
    private const MESSAGE_DATE_KEYS = ['created_at', 'createdAt', 'date_created', 'dateCreated', 'send_date', 'date'];

    /** $awaiting: wątek pochodzi z listy needs_answer, więc na pewno czeka na odpowiedź. */
    public function mapThread(array $account, array $item, bool $awaiting = false): array
    {
        $no = self::pick($item, self::ID_KEYS);
        // Wiadomości są już w wątku z listy; szczegóły dociągamy tylko wtedy, gdy ich tam nie ma.
        $thread = $item;
        $raw = self::rawMessages($item);
        if (!$raw) {
            $detail = $this->detail($account, $no);
            $thread = self::threadOf($detail) ?: $item;
            $raw = self::messagesOf($detail);
        }
        usort($raw, static function (array $a, array $b): int { return strcmp(self::pick($a, self::MESSAGE_DATE_KEYS), self::pick($b, self::MESSAGE_DATE_KEYS)); });
        // Wątek zakłada klient, więc nadawca najstarszej wiadomości to jego adres; reszta od tego adresu to klient.
        $customerAddress = self::sender($raw[0] ?? []);
        $customerName = self::pick($thread, ['sender', 'sender_name', 'senderName', 'customer_name', 'client_name']) ?: self::pick($item, ['sender', 'sender_name', 'customer_name', 'client_name']);
        $messages = [];
        foreach ($raw as $message) {
            $sender = self::sender($message);
            $role = self::role($message, $sender, $customerAddress);
            $messages[] = [
                // Morele nie nadaje wiadomościom identyfikatorów – budujemy własny, stały (wątek + czas + nadawca),
                // żeby ponowne pobranie wątku nie tworzyło duplikatów.
                'external_id' => self::pick($message, ['id', 'message_id', 'messageId'])
                    ?: 'morele:'.sha1($no.'|'.self::pick($message, self::MESSAGE_DATE_KEYS).'|'.$sender),
                'author_role' => $role,
                'author_name' => $role === 'customer' ? ($customerName !== '' ? $customerName : $sender) : $sender,
                'body' => self::text(self::pick($message, self::BODY_KEYS)),
                'attachments' => self::files($message),
                'created_at' => MessageRepository::date(self::pick($message, self::MESSAGE_DATE_KEYS), self::ZONE),
            ];
        }
        $typeKeys = ['type_id', 'typeId', 'type', 'thread_type_id', 'threadType', 'thread_type', 'category_id', 'categoryId'];
        $resourceKeys = ['resource_id', 'resourceId', 'resource', 'order_id', 'orderId', 'order_no', 'reference'];
        $subject = (self::pick($thread, ['subject', 'title']) ?: self::pick($item, ['subject', 'title']));
        $type = (int) (self::pick($thread, $typeKeys) ?: self::pick($item, $typeKeys)) ?: self::typeFromSubject($subject);
        $resource = self::pick($thread, $resourceKeys) ?: self::pick($item, $resourceKeys);
        // Morele oznacza needsResponse tylko dla części wątków, więc traktujemy jako czekający także taki,
        // w którym ostatnia wiadomość jest od klienta i nie ma po niej odpowiedzi sprzedawcy.
        $lastRole = $messages ? (string) $messages[count($messages) - 1]['author_role'] : '';
        $needs = $awaiting || self::needsResponse($item) || self::needsResponse($thread) || $lastRole === 'customer';
        return [
            'kind' => 'message', 'external_id' => $no, 'subject' => $subject ?: (self::TYPES[$type] ?? 'Wiadomość'),
            'customer_name' => $customerName ?: $customerAddress, 'customer_login' => '', 'order_external_id' => in_array($type, [1, 4, 5], true) ? $resource : '',
            'remote_status' => $needs ? 'NEEDS_RESPONSE' : 'OK', 'needs_reply' => $needs, 'closed' => self::isClosed($item) || self::isClosed($thread),
            'last_message_at' => MessageRepository::date(self::lastAt($item) ?: self::lastAt($thread), self::ZONE),
            // Identyfikator do wysyłki: własne pole wątku, a w ostateczności jego numer z listy.
            'meta' => ['thread_id' => self::pick($thread, ['id', 'thread_id', 'threadId', 'identifier']) ?: $no, 'type_id' => $type, 'category' => self::TYPES[$type] ?? '', 'resource_id' => $resource,
                'email' => $customerAddress],
            'messages' => $messages,
        ];
    }

    /**
     * Treść wiadomości → tekst z zachowaniem akapitów. Morele zwraca HTML zakodowany encjami
     * (&lt;p&gt;…), a część wiadomości to czysty tekst – dlatego najpierw dekodujemy encje, a dopiero
     * potem zamieniamy znaczniki na nowe linie.
     */
    public static function text(string $html): string
    {
        // Treść bez znaczników, ale z zakodowanymi (&lt;p&gt;) – najpierw odkodowanie, żeby akapity przetrwały.
        // Prawdziwy HTML zostawiamy, inaczej zniknąłby tekst, który sami zakodowaliśmy przy wysyłce.
        if (strpos($html, '&lt;') !== false && preg_match('#<[a-z!/]#i', $html) !== 1) {
            $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $text = preg_replace(['#<br\s*/?>#i', '#</(p|div|li|h\d)>#i'], "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $text)) ?? $text);
    }

    public function reply(array $account, array $thread, string $text, array $options, MessageRepository $repo): string
    {
        $this->logging($repo->platformSettings('morele'));
        $this->log('--- wysyłka odpowiedzi Morele', ['wątek' => $thread['external_id'] ?? '', 'meta' => $thread['meta'] ?? []]);
        $meta = (array) ($thread['meta'] ?? []);
        $missing = array_keys(array_filter(['identyfikator wątku' => empty($meta['thread_id']), 'rodzaj wątku' => empty($meta['type_id'])]));
        if ($missing) {
            throw new InvalidArgumentException('Morele nie podało w wątku: '.implode(' i ', $missing).'. Zsynchronizuj wiadomości ponownie; jeśli błąd wróci, użyj „Diagnostyka Morele” w ustawieniach i prześlij wynik.');
        }
        $html = implode('', array_map(static function (string $paragraph): string { return '<p>'.nl2br(htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8'), false).'</p>'; }, preg_split("/\n{2,}/", trim($text)) ?: [trim($text)]));
        // Wątek wskazują typeId + resourceIdentifier; klucz „identifier” z panelu API odrzuca („Expected the key to not exist”).
        $payload = ['typeId' => (int) $meta['type_id'], 'resourceIdentifier' => (string) ($meta['resource_id'] ?? ''), 'messageBody' => $html];
        $dropped = [];
        for ($attempt = 0; ; $attempt++) {
            try {
                $response = $this->api->api($account, 'POST', self::SEND, [], null, $payload);
                // Morele zgłasza błędy także polem status w treści – samo HTTP 200 nie potwierdza wysyłki.
                $status = strtoupper(self::pick($response, ['status']));
                $errors = $response['errors'] ?? $response['error'] ?? null;
                if (in_array($status, ['FAILED', 'FAIL', 'ERROR'], true) || (array_key_exists('success', $response) && empty($response['success'])) || !empty($errors)) {
                    throw new \RuntimeException('Morele odrzuciło wiadomość: '.(self::pick($response, ['message']) ?: self::flat((string) json_encode($response, JSON_UNESCAPED_UNICODE))));
                }
                $sentId = self::pick($response, ['id', 'message_id', 'messageId']) ?: self::pick((array) ($response['data'] ?? []), ['id', 'message_id', 'messageId']);
                return $this->confirm($account, $thread, $text, $sentId, $response, $dropped);
            } catch (\RuntimeException $e) {
                if (strpos($e->getMessage(), 'Morele odrzuciło wiadomość') === 0 || strpos($e->getMessage(), 'Morele przyjęło żądanie') === 0) { throw $e; }
                // Walidacja kluczy odrzuca żądanie przed zapisem, więc nadmiarowy klucz można usunąć i ponowić bez ryzyka dubla.
                if ($attempt < 3 && preg_match('/\[400\].*Expected the key "([^"]+)" to not exist/', $e->getMessage(), $match) && $match[1] !== 'messageBody' && array_key_exists($match[1], $payload)) {
                    unset($payload[$match[1]]);
                    $dropped[] = $match[1];
                    continue;
                }
                // Przy odmowie dokładamy kontrakt ze specyfikacji Morele i odpowiedź walidacji.
                if (!preg_match('/\[(400|403|404|405|422|500)\]/', $e->getMessage())) { throw $e; }
                throw new \RuntimeException($e->getMessage().' Diagnostyka wysyłki: '.$this->diagnoseSend($account));
            }
        }
    }

    /** Odstęp (s) między kolejnymi odczytami wątku przy potwierdzaniu wysyłki. */
    public $confirmDelay = 2;

    /**
     * Potwierdzenie wysyłki: HTTP 200 od Morele nie znaczy, że wiadomość trafiła do wątku klienta.
     * Odczytujemy wątek i szukamy wysłanej treści; gdy jej nie ma – błąd zamiast „wysłano”.
     * Nieudany sam odczyt nie podważa wysyłki (ponowienie przez użytkownika groziłoby dublem).
     */
    private function confirm(array $account, array $thread, string $text, string $sentId, array $response, array $dropped): string
    {
        $no = (string) $thread['external_id'];
        $wanted = mb_substr(self::normalize($text), 0, 200, 'UTF-8');
        $this->log('potwierdzanie wysyłki – szukana treść', ['wątek' => $no, 'fragment' => $wanted]);
        for ($check = 0; $check < 3; $check++) {
            if ($check > 0 && $this->confirmDelay > 0) { sleep($this->confirmDelay); }
            try { $messages = self::messagesOf($this->detail($account, $no)); }
            catch (\Throwable $e) { return $sentId; }
            foreach ($messages as $message) {
                $body = self::normalize(self::text(self::pick($message, self::BODY_KEYS)));
                if ($body !== '' && mb_strpos($body, $wanted, 0, 'UTF-8') !== false) {
                    return self::pick($message, ['id', 'message_id', 'messageId']) ?: $sentId;
                }
            }
        }
        // Wiadomość mogła trafić do innego (nowego) wątku tego samego zamówienia/produktu – wskazujemy go.
        $elsewhere = '';
        $resource = (string) ($thread['meta']['resource_id'] ?? '');
        if ($resource !== '') {
            try {
                $others = array_filter(array_map(static function ($item): string { return is_array($item) ? self::pick($item, ['no', 'threadNo', 'thread_no', 'number', 'identifier', 'id']) : ''; },
                    self::items($this->api->api($account, 'GET', self::THREADS, ['start' => 0, 'limit' => self::PAGE, 'resource_id' => $resource]))), static function (string $other) use ($no): bool { return $other !== '' && $other !== $no; });
                if ($others) { $elsewhere = ' Inne wątki Morele dla '.$resource.': '.implode(', ', array_slice($others, 0, 5)).'.'; }
            } catch (\Throwable $e) { /* Tylko podpowiedź do komunikatu. */ }
        }
        throw new \RuntimeException('Morele przyjęło żądanie (odpowiedź: '.mb_substr(self::flat((string) json_encode($response, JSON_UNESCAPED_UNICODE)), 0, 300, 'UTF-8').'), ale wiadomość nie pojawiła się w wątku '.$no.'.'
            .($dropped ? ' Wysłano bez pól odrzuconych przez API: '.implode(', ', $dropped).'.' : '').$elsewhere
            .' Nie oznaczono jej jako wysłanej – sprawdź panel Morele przed ponownym wysłaniem.');
    }

    private static function normalize(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? $text), 'UTF-8');
    }
}
