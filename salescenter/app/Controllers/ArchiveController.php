<?php

declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\SmartyFactory;
use App\Models\OrderRepository;
use App\Models\SellasistArchiveRepository;
use App\Services\SellasistArchiveService;

/** Archiwum Sellasist: stare zamówienia i dokumenty sprzedaży (tylko odczyt) oraz import w tle. */
final class ArchiveController extends Controller
{
    private function service(): SellasistArchiveService
    {
        (new OrderRepository($this->db()))->ensureSchema();
        $repo = new SellasistArchiveRepository($this->db());
        $repo->ensureSchema();
        return new SellasistArchiveService($repo);
    }

    private function orderFilters(): array
    {
        return [
            'q' => mb_substr(trim((string) $this->input('q', '')), 0, 200, 'UTF-8'),
            'status_id' => (string) $this->input('status_id', ''),
            'source' => mb_substr((string) $this->input('source', ''), 0, 100, 'UTF-8'),
            'payment_status' => mb_substr((string) $this->input('payment_status', ''), 0, 30, 'UTF-8'),
            'document' => (string) $this->input('document', ''),
            'date_from' => (string) $this->input('date_from', ''),
            'date_to' => (string) $this->input('date_to', ''),
            'amount_from' => (string) $this->input('amount_from', ''),
            'amount_to' => (string) $this->input('amount_to', ''),
            'sort' => (string) $this->input('sort', 'newest'),
            'page' => max(1, (int) $this->input('page', 1)),
        ];
    }

    private function documentFilters(): array
    {
        return [
            'q' => mb_substr(trim((string) $this->input('q', '')), 0, 200, 'UTF-8'),
            'kind' => (string) $this->input('kind', ''),
            'date_from' => (string) $this->input('date_from', ''),
            'date_to' => (string) $this->input('date_to', ''),
            'amount_from' => (string) $this->input('amount_from', ''),
            'amount_to' => (string) $this->input('amount_to', ''),
            'sort' => (string) $this->input('sort', 'newest'),
            'page' => max(1, (int) $this->input('page', 1)),
        ];
    }

    private static function query(array $filters, array $defaults): string
    {
        return http_build_query(array_filter($filters, static function ($value, $key) use ($defaults) {
            return $key !== 'page' && $value !== '' && $value !== null && ($defaults[$key] ?? null) !== $value;
        }, ARRAY_FILTER_USE_BOTH));
    }

    public function index(): void
    {
        $user = $this->requireModule('orders');
        $service = $this->service();
        $repo = $service->repo();
        $tab = (string) $this->input('tab', 'orders');
        if (!in_array($tab, ['orders', 'documents', 'settings'], true)) { $tab = 'orders'; }
        $progress = $service->progress();
        if (!$progress['configured'] && $tab !== 'settings' && !$progress['stats']['orders']) { $tab = 'settings'; }
        $data = [
            'pageTitle' => 'Archiwum Sellasist', 'tab' => $tab, 'csrf' => $this->csrfToken(),
            'canWrite' => $this->moduleAccessLevel($user, 'orders') === 'edit',
            'progress' => $progress, 'docKinds' => SellasistArchiveRepository::DOC_KINDS,
            'order' => null, 'view' => [], 'orderRaw' => '', 'activeFilterCount' => 0, 'filters' => [], 'listQuery' => '', 'listing' => ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1], 'facets' => [],
        ];
        if ($tab === 'orders') {
            $filters = $this->orderFilters();
            $data['filters'] = $filters;
            $data['listQuery'] = self::query($filters, ['sort' => 'newest']);
            $data['activeFilterCount'] = count(array_filter($filters, static function ($v, $k) { return !in_array($k, ['q', 'page', 'sort'], true) && $v !== ''; }, ARRAY_FILTER_USE_BOTH));
            $data['facets'] = $repo->facets();
            $id = (int) $this->input('id', 0);
            if ($id > 0) {
                $order = $repo->order($id);
                if (!$order) { $this->setFlash('error', 'Nie znaleziono zamówienia w archiwum.'); $this->redirect('./index.php?controller=archive'); }
                $data['order'] = $order;
                $data['view'] = self::orderView($order['detail'], (string) $order['currency']);
                $data['orderRaw'] = json_encode($order['detail'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                $data['listing'] = $repo->orders($filters);
            }
        } elseif ($tab === 'documents') {
            $filters = $this->documentFilters();
            $data['filters'] = $filters;
            $data['listQuery'] = self::query($filters, ['sort' => 'newest']);
            $data['activeFilterCount'] = count(array_filter($filters, static function ($v, $k) { return !in_array($k, ['q', 'page', 'sort'], true) && $v !== ''; }, ARRAY_FILTER_USE_BOTH));
            $data['listing'] = $repo->documents($filters);
        }
        header('Cache-Control: no-store, private');
        $this->render('archive/index', $data);
    }

    /** Dane szczegółów zamówienia Sellasist przygotowane jako proste teksty dla szablonu. */
    private static function orderView(array $d, string $currency): array
    {
        $text = static function ($value): string { return is_scalar($value) ? trim((string) $value) : ''; };
        $money = static function ($value): string { return is_numeric($value) ? number_format((float) $value, 2, '.', '') : ''; };
        $view = ['addresses' => [], 'carts' => [], 'sections' => [], 'comment' => $text($d['comment'] ?? ''),
            'paid_date' => $text($d['payment']['paid_date'] ?? ''), 'shipping_total' => $money($d['shipment']['total'] ?? ''), 'pickup' => ''];
        if (is_array($d['pickup_point'] ?? null)) { $view['pickup'] = trim($text($d['pickup_point']['code'] ?? '').' · '.$text($d['pickup_point']['address'] ?? ''), ' ·'); }
        foreach (['bill_address' => 'Adres rozliczeniowy', 'shipment_address' => 'Adres dostawy'] as $key => $label) {
            $a = is_array($d[$key] ?? null) ? $d[$key] : null;
            if (!$a) { continue; }
            $country = is_array($a['country'] ?? null) ? $text($a['country']['code'] ?? ($a['country']['name'] ?? '')) : $text($a['country'] ?? '');
            $number = $text($a['home_number'] ?? '').($text($a['flat_number'] ?? '') !== '' ? '/'.$text($a['flat_number']) : '');
            $view['addresses'][] = ['label' => $label, 'name' => trim($text($a['name'] ?? '').' '.$text($a['surname'] ?? '')), 'company' => $text($a['company_name'] ?? ''),
                'street' => trim($text($a['street'] ?? '').' '.$number), 'city' => trim($text($a['postcode'] ?? '').' '.$text($a['city'] ?? '').($country !== '' ? ', '.$country : '')),
                'phone' => $text($a['phone'] ?? ''), 'nip' => $text($a['company_nip'] ?? '')];
        }
        foreach ((array) ($d['carts'] ?? []) as $c) {
            if (!is_array($c)) { continue; }
            $qty = (float) ($c['quantity'] ?? 0);
            $price = (float) ($c['price'] ?? ($c['price_gross'] ?? 0));
            $codes = [];
            foreach (['symbol' => 'SKU', 'ean' => 'EAN', 'catalog_number' => 'Nr kat.'] as $field => $prefix) { if ($text($c[$field] ?? '') !== '') { $codes[] = $prefix.' '.$text($c[$field]); } }
            $auctionId = $text($c['external_offer_id'] ?? ($c['auction_id'] ?? ($c['offer_id'] ?? '')));
            if ($auctionId !== '') { $codes[] = 'Nr aukcji '.$auctionId; }
            $view['carts'][] = ['name' => $text($c['name'] ?? ''), 'notes' => array_values(array_filter([$text($c['selected_options'] ?? ''), $text($c['additional_information'] ?? '')], 'strlen')),
                'codes' => $codes, 'quantity' => rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.'), 'price' => number_format($price, 2, '.', '').' '.$currency,
                'vat' => $text($c['tax_rate'] ?? '') !== '' ? $text($c['tax_rate']).'%' : '', 'total' => number_format($price * $qty, 2, '.', '').' '.$currency,
                'image' => preg_match('#^https?://#i', $text($c['image_thumb'] ?? '') ?: $text($c['image'] ?? '')) ? ($text($c['image_thumb'] ?? '') ?: $text($c['image'] ?? '')) : ''];
        }
        $rows = [];
        foreach ((array) ($d['payments'] ?? []) as $p) { if (is_array($p)) { $rows[] = [$text($p['date'] ?? ''), trim($money($p['amount'] ?? '').' '.$text($p['currency'] ?? '').' · '.$text($p['name'] ?? ''), ' ·')]; } }
        if ($rows) { $view['sections'][] = ['label' => 'Wpłaty', 'rows' => $rows]; }
        $rows = [];
        foreach ((array) ($d['shipments'] ?? []) as $p) {
            if (!is_array($p)) { continue; }
            $state = is_array($p['tracking_numbers'] ?? null) ? $text($p['tracking_numbers']['deliveryStatusInternal'] ?? '') : '';
            $rows[] = [$text($p['service'] ?? '') ?: 'Przesyłka', trim($text($p['tracking_number'] ?? '').($state !== '' ? ' · '.$state : ''), ' ·')];
        }
        if ($rows) { $view['sections'][] = ['label' => 'Przesyłki', 'rows' => $rows]; }
        $rows = [];
        foreach ((array) ($d['additional_fields'] ?? []) as $f) {
            if (!is_array($f)) { continue; }
            $value = $text($f['field_value'] ?? '');
            $rows[] = [$text($f['field_name'] ?? ''), mb_strlen($value, 'UTF-8') > 200 ? mb_substr($value, 0, 200, 'UTF-8').'…' : $value];
        }
        if ($rows) { $view['sections'][] = ['label' => 'Pola dodatkowe', 'rows' => $rows]; }
        return $view;
    }

    /** Podgląd/wydruk dokumentu z archiwum (dane z API Sellasist – Sellasist nie udostępnia PDF przez API). */
    public function document(): void
    {
        $this->requireModule('orders');
        $document = $this->service()->repo()->document((int) $this->input('id', 0));
        if (!$document) { http_response_code(404); exit('Nie znaleziono dokumentu.'); }
        $detail = $document['detail'];
        if (SellasistArchiveRepository::baseKind((string) $document['kind']) !== $document['kind']) { $detail = self::operationDetail($detail); }
        $lines = []; $vat = []; $sum = ['net' => 0.0, 'gross' => 0.0];
        foreach ((array) ($detail['lines'] ?? []) as $line) {
            if (!is_array($line)) { continue; }
            $qty = (float) ($line['quantity'] ?? 0);
            $gross = round((float) ($line['price_gross'] ?? 0) * $qty, 2);
            $net = isset($line['price_net']) ? round((float) $line['price_net'] * $qty, 2) : round($gross / (1 + ((float) ($line['vat'] ?? 0)) / 100), 2);
            $rate = is_numeric($line['vat'] ?? null) ? (string) (0 + $line['vat']) : (string) ($line['vat'] ?? '');
            $lines[] = ['name' => (string) ($line['name'] ?? ''), 'quantity' => $qty, 'price_gross' => (float) ($line['price_gross'] ?? 0), 'price_net' => isset($line['price_net']) ? (float) $line['price_net'] : null, 'vat' => $rate, 'discount' => $line['discount'] ?? '', 'net' => $net, 'gross' => $gross];
            if (!isset($vat[$rate])) { $vat[$rate] = ['vat' => $rate, 'net' => 0.0, 'gross' => 0.0]; }
            $vat[$rate]['net'] += $net; $vat[$rate]['gross'] += $gross;
            $sum['net'] += $net; $sum['gross'] += $gross;
        }
        header('Cache-Control: no-store, private');
        $smarty = SmartyFactory::create();
        $smarty->assign([
            'document' => $document, 'detail' => array_filter($detail, 'is_scalar'), 'lines' => $lines, 'vatSummary' => array_values($vat), 'sum' => $sum,
            'kindLabel' => SellasistArchiveRepository::DOC_KINDS[SellasistArchiveRepository::baseKind((string) $document['kind'])] ?? $document['kind'],
            'seller' => is_array($detail['seller'] ?? null) ? array_filter($detail['seller'], 'is_scalar') : [], 'buyer' => is_array($detail['buyer'] ?? null) ? array_filter($detail['buyer'], 'is_scalar') : [],
            'raw' => json_encode($detail, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $smarty->display('archive/document.tpl');
    }

    /** Dokument operacyjny → układ starych dokumentów (lines z cenami jednostkowymi, seller, buyer) dla wydruku. */
    private static function operationDetail(array $d): array
    {
        $text = static function ($value): string { return is_scalar($value) ? trim((string) $value) : ''; };
        $address = static function (array $a) use ($text): array {
            $street = trim($text($a['street'] ?? '').' '.$text($a['home_number'] ?? '').($text($a['flat_number'] ?? '') !== '' ? '/'.$text($a['flat_number']) : ''));
            $nip = $text($a['company_nip'] ?? ($a['nip'] ?? ''));
            return array_filter([
                'name' => $text($a['company_name'] ?? '') ?: ($text($a['name'] ?? '').' '.$text($a['surname'] ?? '')),
                'address' => $street, 'postcode' => $text($a['postcode'] ?? ''), 'city' => $text($a['city'] ?? ''),
                'nip' => trim($nip, '0') === '' ? '' : $nip, 'phone' => $text($a['phone'] ?? ''),
            ], static function ($v) { return trim($v) !== ''; });
        };
        $lines = [];
        foreach ((array) ($d['products'] ?? []) as $p) {
            if (!is_array($p)) { continue; }
            $qty = (float) ($p['quantity'] ?? 0);
            // price_gross/price_net to wartość pozycji; ceny jednostkowe w *_unit.
            $gross = isset($p['price_gross_unit']) ? (float) $p['price_gross_unit'] : ($qty ? (float) ($p['price_gross'] ?? 0) / $qty : 0.0);
            $net = isset($p['price_net_unit']) ? (float) $p['price_net_unit'] : (isset($p['price_net']) && $qty ? (float) $p['price_net'] / $qty : null);
            $lines[] = ['name' => $text($p['name'] ?? ''), 'quantity' => $qty, 'price_gross' => $gross, 'price_net' => $net, 'vat' => $p['vat'] ?? '', 'discount' => $p['discount'] ?? ''];
        }
        $buyer = $address(is_array($d['buyer_address'] ?? null) ? $d['buyer_address'] : []);
        if ($text($d['email'] ?? '') !== '') { $buyer['email'] = $text($d['email']); }
        $d['lines'] = $lines;
        $d['buyer'] = $buyer;
        $d['seller'] = $address(is_array($d['seller_data'] ?? null) ? $d['seller_data'] + ['company_name' => $d['seller_data']['name'] ?? '', 'company_nip' => $d['seller_data']['nip'] ?? ''] : []);
        unset($d['products'], $d['buyer_address'], $d['seller_data']);
        return $d;
    }

    /** Eksport wyników filtrowania do CSV (Excel, średnik, UTF-8 z BOM). */
    public function export(): void
    {
        $this->requireModule('orders');
        $repo = $this->service()->repo();
        $this->releaseSessionLock();
        @set_time_limit(120);
        $documents = (string) $this->input('tab', '') === 'documents';
        $rows = $documents ? $repo->documents($this->documentFilters(), 100000)['rows'] : $repo->orders($this->orderFilters(), 100000)['rows'];
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="archiwum-sellasist-'.($documents ? 'dokumenty' : 'zamowienia').'-'.date('Y-m-d').'.csv"');
        header('Cache-Control: no-store, private');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        $cell = static function ($value) { $value = (string) $value; return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value; };
        if ($documents) {
            fputcsv($out, ['Rodzaj', 'Numer', 'Dotyczy', 'Data wystawienia', 'Zamówienie Sellasist', 'Nabywca', 'NIP', 'Kwota brutto', 'Waluta'], ';', '"', '\\');
            foreach ($rows as $r) {
                fputcsv($out, array_map($cell, [SellasistArchiveRepository::DOC_KINDS[$r['kind']] ?? $r['kind'], $r['number'], $r['related_number'], $r['issue_date'], $r['order_remote_id'] ?: '', $r['buyer_name'], $r['buyer_nip'], number_format($r['total_cents'] / 100, 2, ',', ''), $r['currency']]), ';', '"', '\\');
            }
        } else {
            fputcsv($out, ['Nr Sellasist', 'Data', 'Status', 'Źródło', 'Konto', 'Nr zewnętrzny', 'Klient', 'Firma', 'NIP', 'E-mail', 'Telefon', 'Miasto', 'Kraj', 'Produkty', 'Kwota', 'Waluta', 'Płatność', 'Status płatności', 'Dostawa', 'Nr przesyłki', 'Dokument'], ';', '"', '\\');
            foreach ($rows as $r) {
                fputcsv($out, array_map($cell, [$r['sellasist_id'], $r['ordered_at'], $r['status_name'], $r['source'], $r['creator'], $r['external_id'], $r['buyer_name'], $r['company'], $r['nip'], $r['email'], $r['phone'], $r['city'], $r['country'], $r['items_preview'], number_format($r['total_cents'] / 100, 2, ',', ''), $r['currency'], $r['payment_name'], $r['payment_status'], $r['delivery_name'], $r['tracking'], $r['document_number']]), ';', '"', '\\');
            }
        }
        fclose($out);
    }

    /** Jedna porcja importu z przeglądarki (AJAX). Strona wywołuje ją w pętli, dopóki jest otwarta. */
    public function step(): void
    {
        $user = $this->currentUser();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        if (!$user || $this->moduleAccessLevel($user, 'orders') !== 'edit') { http_response_code(403); echo json_encode(['error' => 'Brak uprawnień.']); return; }
        if (!$this->isPost() || !hash_equals($this->csrfToken(), (string) ($_POST['csrf'] ?? ''))) { http_response_code(403); echo json_encode(['error' => 'Sesja wygasła. Odśwież stronę.']); return; }
        $this->releaseSessionLock();
        @set_time_limit(90);
        $service = $this->service();
        try {
            $report = (string) ($_POST['run'] ?? '1') === '1' ? $service->run(20, true) : ['skipped' => 'status'];
            echo json_encode(['report' => $report, 'progress' => $service->progress()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => mb_substr($e->getMessage(), 0, 300, 'UTF-8')], JSON_UNESCAPED_UNICODE);
        }
    }

    public function save(): void
    {
        $this->requireModuleWrite('orders');
        $this->requireCsrf();
        $service = $this->service();
        $operation = (string) ($_POST['operation'] ?? '');
        try {
            switch ($operation) {
                case 'connect':
                    $result = $service->configure((string) ($_POST['account'] ?? ''), (string) ($_POST['api_key'] ?? ''));
                    $this->setFlash('success', 'Połączono z '.$result['host'].'. Import archiwum ruszy w tle (cron co minutę) – możesz też przyspieszyć go przyciskiem „Pobieraj teraz”.');
                    break;
                case 'enable':
                case 'disable':
                    $service->setEnabled($operation === 'enable');
                    $this->setFlash('success', $operation === 'enable' ? 'Import w tle włączony.' : 'Import w tle wstrzymany. Pobrane dane zostają w archiwum.');
                    break;
                case 'restart':
                    $service->reset();
                    $this->setFlash('success', 'Import zacznie się od początku. Istniejące rekordy zostaną zaktualizowane, nic nie zostanie zdublowane.');
                    break;
                case 'retry_failed':
                    $count = $service->repo()->requeueDetails(true);
                    $this->setFlash('success', 'Ponownie w kolejce: '.$count.' rekordów.');
                    break;
                case 'refresh_details':
                    $count = $service->repo()->requeueDetails(false);
                    $this->setFlash('success', 'Szczegóły '.$count.' rekordów zostaną pobrane ponownie.');
                    break;
                case 'clear':
                    if ((string) ($_POST['confirm'] ?? '') !== 'USUŃ') { throw new \InvalidArgumentException('Aby wyczyścić archiwum, wpisz USUŃ.'); }
                    $service->repo()->clear();
                    $service->reset();
                    $this->setFlash('success', 'Archiwum wyczyszczone. Import zacznie się od początku.');
                    break;
                default:
                    throw new \InvalidArgumentException('Nieznana operacja.');
            }
        } catch (\Throwable $e) {
            $this->setFlash('error', $e->getMessage());
        }
        $this->redirect('./index.php?controller=archive&tab=settings');
    }
}
