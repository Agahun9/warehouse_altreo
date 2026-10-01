<?php

declare(strict_types=1);
namespace App\Services;

use App\Models\OrderRepository;
use InvalidArgumentException;

/**
 * Szablony druku zamówień: dane zamówienia → kontekst pól, prosty silnik szablonów i eksport PDF/HTML/CSV.
 *
 * Składnia: {{order.number}}, filtry {{item.name|upper|truncate:40}}, pętle {{#each items}}…{{/each}}
 * (wewnątrz {{item.*}} i {{loop.index}}), warunki {{#if pole}}…{{else}}…{{/if}} oraz {{#unless pole}}…{{/unless}}.
 * Wartości w HTML są zawsze escapowane — dane z marketplace nie mogą wstrzyknąć znaczników.
 */
final class OrderPrintTemplateService
{
    public const MAX_ORDERS = 500;
    private const LOOP_ALIASES = ['items'=>'item','shipments'=>'shipment','documents'=>'document','notes'=>'note','products'=>'product','orders'=>null];
    private const PAGE_MM = ['A4'=>[210,297],'A5'=>[148,210],'A6'=>[105,148],'label100x150'=>[100,150],'Letter'=>[215.9,279.4]];
    private const PLATFORMS = ['manual'=>'Własne','api'=>'Własny sklep (API)','allegro'=>'Allegro','erli'=>'ERLI','empik'=>'Empik','mediamarkt'=>'MediaMarkt','morele'=>'Morele','temu'=>'Temu','prestashop'=>'PrestaShop','woocommerce'=>'WooCommerce','altreo'=>'Altreo.pl'];
    /** Code 128: szerokości kresek/odstępów dla wartości 0–106 (103–105 start A/B/C, 106 stop). */
    public const CODE128 = ['212222','222122','222221','121223','121322','131222','122213','122312','132212','221213','221312','231212','112232','122132','122231','113222','123122','123221','223211','221132','221231','213212','223112','312131','311222','321122','321221','312212','322112','322211','212123','212321','232121','111323','131123','131321','112313','132113','132311','211313','231113','231311','112133','112331','132131','113123','113321','133121','313121','211331','231131','213113','213311','213131','311123','311321','331121','312113','312311','332111','314111','221411','431111','111224','111422','121124','121421','141122','141221','112214','112412','122114','122411','142112','142211','241211','221114','413111','241112','134111','111242','121142','121241','114212','124112','124211','411212','421112','421211','212141','214121','412121','111143','111341','131141','114113','114311','411113','411311','113141','114131','311141','411131','211412','211214','211232','2331112'];
    private const DOCUMENT_KINDS = ['invoice'=>'Faktura','receipt'=>'Paragon','invoice_correction'=>'Korekta faktury','receipt_correction'=>'Korekta paragonu','proforma'=>'Proforma'];

    /** @var OrderRepository */
    private $repo;
    /** @var int */
    private $depth = 0;

    public function __construct(OrderRepository $repo) { $this->repo = $repo; }

    /** Lista pól do palety edytora: grupa => [[pole, opis, przykład]]. */
    public static function catalog(): array
    {
        return [
            'Zamówienie'=>[
                ['order.number','Numer zamówienia (ze źródła)','A1B2-C3D4'],['order.id','Wewnętrzne ID','1024'],['order.internal_number','Numer wewnętrzny z #','#1024'],
                ['order.platform_label','Źródło (nazwa)','Allegro'],['order.platform','Źródło (kod)','allegro'],['order.account','Konto sprzedaży','Sklep główny'],
                ['order.status','Status wewnętrzny','Do spakowania'],['order.status_color','Kolor statusu','#f59e0b'],['order.remote_status','Status w źródle','READY_FOR_PROCESSING'],
                ['order.date','Data i godzina zamówienia','01.10.2026 14:05'],['order.date_only','Data zamówienia','01.10.2026'],['order.time','Godzina zamówienia','14:05'],['order.date_iso','Data zamówienia (RRRR-MM-DD)','2026-10-01'],
                ['order.imported_at','Data importu','01.10.2026 14:06'],['order.updated_at','Ostatnia zmiana','01.10.2026 15:00'],['order.status_changed_at','Zmiana statusu','01.10.2026 15:00'],
                ['order.total','Kwota razem','249,99'],['order.total_dot','Kwota razem (kropka)','249.99'],['order.total_cents','Kwota w groszach','24999'],['order.currency','Waluta','PLN'],
                ['order.items_total','Wartość produktów','235,00'],['order.shipping_cost','Koszt dostawy','14,99'],['order.adjustment','Rabat / dopłata','-10,00'],['order.adjustment_label','Opis rabatu / dopłaty','Rabat'],['order.adjustment_cents','Rabat / dopłata w groszach','-1000'],
                ['order.items_count','Liczba pozycji','2'],['order.items_quantity','Liczba sztuk','3'],['order.items_summary','Produkty w jednej linii','2× Kubek; 1× Talerz'],
                ['order.tags','Tagi','pilne'],['order.note','Notatka (pierwsza)','Zadzwonić przed wysyłką'],['order.notes_text','Wszystkie notatki','…'],['order.buyer_note','Wiadomość od kupującego','Proszę o fakturę'],
                ['order.starred','Oznaczone gwiazdką (Tak/Nie)','Nie'],['order.document_type','Preferowany dokument','Faktura'],
                ['order.tracking_numbers','Numery przesyłek','6200123456789'],['order.carriers','Przewoźnicy','InPost'],
                ['order.barcode_url','Kod kreskowy numeru zamówienia (do <img src>)',''],['order.id_barcode_url','Kod kreskowy ID wewnętrznego (do <img src>)',''],
                ['order.invoice_number','Numer faktury','FV/12/10/2026'],['order.receipt_number','Numer paragonu','PAR/5/10/2026'],['order.document_numbers','Wszystkie dokumenty','FV/12/10/2026'],
            ],
            'Kupujący'=>[
                ['buyer.name','Imię i nazwisko / nazwa','Anna Kowalska'],['buyer.email','E-mail','anna@example.com'],['buyer.phone','Telefon','+48 600 100 200'],['buyer.login','Login w serwisie','anna_k'],
            ],
            'Adres dostawy'=>[
                ['shipping.name','Odbiorca','Anna Kowalska'],['shipping.company','Firma odbiorcy',''],['shipping.street','Ulica','Prosta'],['shipping.building','Numer domu/lokalu','12/3'],['shipping.street_full','Ulica z numerem','Prosta 12/3'],
                ['shipping.postal_code','Kod pocztowy','00-001'],['shipping.city','Miasto','Warszawa'],['shipping.country','Kraj (kod)','PL'],['shipping.phone','Telefon odbiorcy','+48 600 100 200'],['shipping.email','E-mail odbiorcy','anna@example.com'],
                ['shipping.address','Adres w jednej linii','Anna Kowalska, Prosta 12/3, 00-001 Warszawa, PL'],['shipping.address_lines','Adres w wielu liniach (użyj |nl2br)','…'],
                ['shipping.method','Metoda dostawy','InPost Paczkomat'],['shipping.pickup_point','Punkt odbioru','WAW01M'],['shipping.cost','Koszt dostawy','14,99'],
            ],
            'Dane do faktury'=>[
                ['invoice.required','Faktura wymagana (Tak/Nie)','Tak'],['invoice.company','Firma','Firma Sp. z o.o.'],['invoice.nip','NIP','5252674798'],['invoice.name','Imię i nazwisko','Anna Kowalska'],
                ['invoice.street','Ulica','Firmowa'],['invoice.building','Numer','8'],['invoice.street_full','Ulica z numerem','Firmowa 8'],['invoice.postal_code','Kod pocztowy','00-002'],['invoice.city','Miasto','Warszawa'],['invoice.country','Kraj (kod)','PL'],
                ['invoice.address','Adres w jednej linii','Firma Sp. z o.o., Firmowa 8, 00-002 Warszawa'],['invoice.address_lines','Adres w wielu liniach','…'],
            ],
            'Płatność'=>[
                ['payment.method','Metoda płatności','Przelew'],['payment.source_method','Metoda ze źródła','ONLINE'],['payment.paid','Opłacone (Tak/Nie)','Tak'],['payment.status','Status płatności','Opłacone'],
                ['payment.paid_amount','Zapłacono','249,99'],['payment.due_amount','Do zapłaty','0,00'],['payment.cod','Za pobraniem (Tak/Nie)','Nie'],
            ],
            'Pozycje — w pętli {{#each items}}'=>[
                ['item.lp','Lp.','1'],['item.name','Nazwa','Kubek ceramiczny'],['item.sku','SKU','KUB-01'],['item.ean','EAN','5901234123457'],['item.quantity','Ilość','2'],
                ['item.unit_price','Cena brutto','49,00'],['item.total_price','Wartość brutto','98,00'],['item.unit_net','Cena netto','39,84'],['item.total_net','Wartość netto','79,67'],['item.vat','Stawka VAT','23'],
                ['item.unit_cents','Cena w groszach','4900'],['item.image_url','Adres zdjęcia',''],['item.offer_id','ID oferty','12345678'],['item.offer_url','Link do oferty',''],['item.external_id','ID produktu w źródle',''],
            ],
            'Przesyłki — w pętli {{#each shipments}}'=>[
                ['shipment.tracking','Numer przesyłki','6200123456789'],['shipment.carrier','Przewoźnik','InPost'],['shipment.service','Usługa','Paczkomat'],['shipment.provider','Operator nadania','InPost ShipX'],
                ['shipment.status','Status','Nadana'],['shipment.weight','Waga','1'],['shipment.cod_amount','Kwota pobrania','0,00'],['shipment.created_at','Data utworzenia','01.10.2026 15:00'],
            ],
            'Dokumenty — w pętli {{#each documents}}'=>[
                ['document.number','Numer dokumentu','FV/12/10/2026'],['document.kind','Rodzaj','Faktura'],['document.created_at','Data wystawienia','01.10.2026 15:10'],
            ],
            'Notatki — w pętli {{#each notes}}'=>[
                ['notes.0.body','Pierwsza notatka (bez pętli)',''],['notes.1.body','Druga notatka (bez pętli)',''],['note.body','Treść','Zadzwonić przed wysyłką'],['note.author','Autor','Jan'],['note.created_at','Data','01.10.2026 15:00'],
            ],
            'Sprzedawca (Dokumenty → dane sprzedawcy)'=>[
                ['seller.name','Nazwa firmy',''],['seller.nip','NIP',''],['seller.address','Adres',''],['seller.email','E-mail',''],['seller.phone','Telefon',''],
                ['seller.bank','Numer konta',''],['seller.bank_name','Nazwa banku',''],['seller.swift','SWIFT',''],['seller.regon','REGON',''],['seller.krs','KRS',''],['seller.bdo','BDO',''],
            ],
            'Wydruk / zestawienie'=>[
                ['print.date','Data wydruku','01.10.2026'],['print.datetime','Data i godzina wydruku','01.10.2026 16:00'],['print.user','Kto drukuje','Jan Nowak'],
                ['print.count','Liczba zamówień','12'],['print.total','Suma kwot (wg walut)','2 999,00 PLN'],['print.items_quantity','Suma sztuk','31'],['print.template','Nazwa szablonu','Lista zamówień'],
            ],
            'Lista kompletacyjna — w pętli {{#each products}}'=>[
                ['product.lp','Lp.','1'],['product.name','Nazwa','Kubek ceramiczny'],['product.sku','SKU','KUB-01'],['product.ean','EAN',''],['product.quantity','Łączna ilość','5'],
                ['product.orders','Numery zamówień','A1B2, C3D4'],['product.orders_count','Liczba zamówień','2'],['product.image_url','Adres zdjęcia',''],
            ],
            'Pętle i warunki'=>[
                ['#each orders','Pętla po zamówieniach (tryb „lista”)',''],['#each items','Pętla po pozycjach zamówienia',''],['#each shipments','Pętla po przesyłkach',''],['#each documents','Pętla po dokumentach',''],
                ['#each notes','Pętla po notatkach',''],['#each products','Pętla po produktach zbiorczo',''],['loop.index','Numer w pętli (od 1)',''],['loop.first','Pierwszy element (Tak/Nie)',''],['loop.last','Ostatni element (Tak/Nie)',''],
                ['raw.','Dowolne pole ze źródła, np. raw.buyer.login',''],
            ],
        ];
    }

    /** Konteksty zamówień w kolejności podanych ID (pomija nieistniejące). */
    public function contexts(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval',$ids),static function (int $id): bool { return $id > 0; })));
        if (!$ids) { throw new InvalidArgumentException('Zaznacz co najmniej jedno zamówienie.'); }
        if (count($ids) > self::MAX_ORDERS) { throw new InvalidArgumentException('Jednorazowo można wyeksportować maks. '.self::MAX_ORDERS.' zamówień.'); }
        $db = $this->repo->db(); $in = implode(',',$ids);
        $shipments = []; $documents = [];
        foreach ($db->fetchAll('SELECT s.*,ca.provider AS carrier_provider FROM om_shipments s LEFT JOIN om_carrier_accounts ca ON ca.id=s.carrier_account_id WHERE s.order_id IN ('.$in.') ORDER BY s.id') as $row) { $shipments[(int)$row['order_id']][] = $row; }
        foreach ($db->fetchAll('SELECT id,order_id,number,kind,created_at FROM om_documents WHERE order_id IN ('.$in.') ORDER BY id') as $row) { $documents[(int)$row['order_id']][] = $row; }
        $contexts = [];
        foreach ($ids as $id) {
            try { $order = $this->repo->order($id); } catch (InvalidArgumentException $e) { continue; }
            $contexts[] = $this->orderContext($order,$shipments[$id] ?? [],$documents[$id] ?? [],$this->repo->notes($id));
        }
        if (!$contexts) { throw new InvalidArgumentException('Nie znaleziono zaznaczonych zamówień.'); }
        return $contexts;
    }

    /** Syntetyczne zamówienie do podglądu, gdy firma nie ma jeszcze zamówień. */
    public function sampleContexts(): array
    {
        $raw = ['buyer'=>['login'=>'anna_k']];
        $order = [
            'id'=>1024,'external_id'=>'PRZYKLAD-1024','platform'=>'allegro','account_name'=>'Sklep główny','status_name'=>'Do spakowania','color'=>'#f59e0b','remote_status'=>'READY_FOR_PROCESSING',
            'ordered_at'=>gmdate('Y-m-d H:i:s',time()-3600),'imported_at'=>gmdate('Y-m-d H:i:s'),'updated_at'=>gmdate('Y-m-d H:i:s'),'status_changed_at'=>gmdate('Y-m-d H:i:s'),
            'buyer_name'=>'Anna Kowalska','email'=>'anna@example.com','phone'=>'+48 600 100 200','total_cents'=>24499,'currency'=>'PLN','paid'=>1,'tags'=>'przykład','note'=>'','starred'=>0,
            'shipping_address'=>['name'=>'Anna Kowalska','email'=>'anna@example.com','phone'=>'+48 600 100 200','street'=>'Prosta','building'=>'12/3','postal_code'=>'00-001','city'=>'Warszawa','country'=>'PL','point'=>'WAW01M'],
            'details'=>[
                'items'=>[
                    ['name'=>'Kubek ceramiczny 350 ml','sku'=>'KUB-01','ean'=>'5901234123457','quantity'=>2,'unit_cents'=>4900,'vat'=>'23','image_url'=>''],
                    ['name'=>'Talerz obiadowy biały','sku'=>'TAL-02','ean'=>'','quantity'=>1,'unit_cents'=>13700,'vat'=>'23','image_url'=>''],
                ],
                'shipping_cents'=>1499,'delivery'=>'InPost Paczkomat 24/7','pickup'=>'WAW01M','payment_method'=>'Płatność online','source_payment_method'=>'ONLINE','cash_on_delivery'=>0,
                'amount_paid_cents'=>24499,'amount_due_cents'=>0,'buyer_note'=>'Proszę o staranne zapakowanie.','document_preference'=>'invoice','invoice_required'=>1,
                'invoice_form'=>['name'=>'Anna Kowalska','company'=>'Przykładowa Firma Sp. z o.o.','nip'=>'5252674798','street'=>'Firmowa','building'=>'8','postal_code'=>'00-002','city'=>'Warszawa','country'=>'PL'],
                'raw'=>$raw,
            ],
        ];
        $order['total_cents'] = 2*4900+13700+1499;
        $order['details']['amount_paid_cents'] = $order['total_cents'];
        $shipments = [['carrier'=>'inpost','tracking'=>'620012345678901234567890','weight'=>'1','state'=>'confirmed','created_at'=>gmdate('Y-m-d H:i:s'),'carrier_provider'=>'inpost','cod_amount_cents'=>0,'payload_json'=>'']];
        $documents = [['id'=>1,'number'=>'FV/1/'.gmdate('m/Y'),'kind'=>'invoice','created_at'=>gmdate('Y-m-d H:i:s')]];
        $notes = [['body'=>'Klient prosi o kontakt telefoniczny przed wysyłką.','author'=>'Jan','created_at'=>gmdate('Y-m-d H:i:s')]];
        $second = $order;
        $second['id'] = 1025; $second['external_id'] = 'PRZYKLAD-1025'; $second['buyer_name'] = 'Piotr Nowak'; $second['email'] = 'piotr@example.com'; $second['paid'] = 0; $second['platform'] = 'manual'; $second['account_name'] = 'Zamówienia własne';
        $second['shipping_address'] = ['name'=>'Piotr Nowak','email'=>'piotr@example.com','phone'=>'+48 700 200 300','street'=>'Długa','building'=>'5','postal_code'=>'30-001','city'=>'Kraków','country'=>'PL','point'=>''];
        $second['details']['items'] = [['name'=>'Kubek ceramiczny 350 ml','sku'=>'KUB-01','ean'=>'5901234123457','quantity'=>3,'unit_cents'=>4900,'vat'=>'23','image_url'=>'']];
        $second['details']['delivery'] = 'Kurier DPD pobranie'; $second['details']['pickup'] = ''; $second['details']['cash_on_delivery'] = 1; $second['details']['payment_method'] = 'Płatność przy odbiorze';
        $second['details']['shipping_cents'] = 1999; $second['total_cents'] = 3*4900+1999; $second['details']['amount_paid_cents'] = 0; $second['details']['amount_due_cents'] = $second['total_cents'];
        $second['details']['document_preference'] = 'receipt'; $second['details']['invoice_required'] = 0; $second['details']['invoice_form'] = []; $second['details']['invoice_form'] = []; $second['details']['buyer_note'] = '';
        return [$this->orderContext($order,$shipments,$documents,$notes),$this->orderContext($second,[],[],[])];
    }

    /** Pola wspólne dla całego wydruku (sprzedawca, podsumowanie, lista kompletacyjna). */
    public function globals(array $contexts,string $user,string $templateName): array
    {
        $seller = $this->repo->setting('seller') + ['name'=>'','nip'=>'','address'=>'','bank'=>'','bank_name'=>'','swift'=>'','email'=>'','phone'=>'','regon'=>'','krs'=>'','bdo'=>''];
        $now = new \DateTimeImmutable('now',new \DateTimeZone('Europe/Warsaw'));
        $totals = []; $quantity = 0; $products = [];
        foreach ($contexts as $context) {
            $currency = (string)$context['order']['currency'];
            $totals[$currency] = ($totals[$currency] ?? 0) + (int)$context['order']['total_cents'];
            foreach ($context['items'] as $item) {
                $quantity += (int)$item['quantity'];
                $key = $item['sku'] !== '' ? 'sku:'.mb_strtolower($item['sku']) : 'name:'.mb_strtolower($item['name']);
                if (!isset($products[$key])) { $products[$key] = ['name'=>$item['name'],'sku'=>$item['sku'],'ean'=>$item['ean'],'image_url'=>$item['image_url'],'quantity'=>0,'order_numbers'=>[]]; }
                $products[$key]['quantity'] += (int)$item['quantity'];
                $products[$key]['order_numbers'][$context['order']['number']] = true;
                if ($products[$key]['image_url'] === '' && $item['image_url'] !== '') { $products[$key]['image_url'] = $item['image_url']; }
                if ($products[$key]['ean'] === '' && $item['ean'] !== '') { $products[$key]['ean'] = $item['ean']; }
            }
        }
        uasort($products,static function (array $a,array $b): int { return [$a['sku'] === '' ? 1 : 0,$a['sku'],$a['name']] <=> [$b['sku'] === '' ? 1 : 0,$b['sku'],$b['name']]; });
        $list = []; $lp = 0;
        foreach ($products as $product) {
            $numbers = array_keys($product['order_numbers']);
            $list[] = ['lp'=>++$lp,'name'=>$product['name'],'sku'=>$product['sku'],'ean'=>$product['ean'],'image_url'=>$product['image_url'],'quantity'=>$product['quantity'],'orders'=>implode(', ',$numbers),'orders_count'=>count($numbers)];
        }
        ksort($totals);
        return [
            'seller'=>array_map('strval',array_intersect_key($seller,array_flip(['name','nip','address','bank','bank_name','swift','email','phone','regon','krs','bdo']))),
            'print'=>[
                'date'=>$now->format('d.m.Y'),'datetime'=>$now->format('d.m.Y H:i'),'user'=>$user,'count'=>count($contexts),'items_quantity'=>$quantity,'template'=>$templateName,
                'total'=>implode('; ',array_map(static function (string $currency,int $cents): string { return number_format($cents/100,2,',',' ').' '.$currency; },array_keys($totals),$totals)),
            ],
            'products'=>$list,
            'orders'=>$contexts,
        ];
    }

    /** Pełny dokument HTML. $mode: 'print' (pasek + automatyczne okno druku), 'file' (samodzielny plik), 'preview'. */
    public function renderDocument(array $template,array $contexts,array $globals,string $mode='file',string $nonce=''): string
    {
        $content = $template['content'];
        [$width,$height] = self::PAGE_MM[$content['page_size']] ?? self::PAGE_MM['A4'];
        if ($content['orientation'] === 'landscape') { [$width,$height] = [$height,$width]; }
        $margin = (float)$content['margin'];
        $sheets = [];
        if ($content['mode'] === 'list') { $sheets[] = $this->render((string)$content['body'],[$globals],true); }
        else {
            $count = count($contexts);
            foreach ($contexts as $index => $context) {
                $sheets[] = $this->render((string)$content['body'],[$globals,$context,['loop'=>self::loopInfo($index,$count)]],true);
            }
        }
        $sheetClass = 'sc-sheet'.($content['mode'] === 'per_order' && $content['page_break'] ? ' sc-break' : '');
        $body = '';
        foreach ($sheets as $sheet) { $body .= '<section class="'.$sheetClass.'">'.$sheet.'</section>'; }
        $fmt = static function (float $value): string { return rtrim(rtrim(number_format($value,1,'.',''),'0'),'.'); };
        $css = '@page{size:'.$fmt($width).'mm '.$fmt($height).'mm;margin:'.$fmt($margin).'mm}'
            .'*{box-sizing:border-box}html,body{margin:0;padding:0}img{max-width:100%}'
            .'@media screen{body{background:#e5e7eb;padding:24px 12px}.sc-sheet{width:'.$fmt($width).'mm;max-width:100%;min-height:'.$fmt($height).'mm;margin:0 auto 18px;padding:'.$fmt($margin).'mm;background:#fff;box-shadow:0 6px 24px rgba(15,23,42,.14);overflow:hidden}}'
            .'@media print{.sc-sheet.sc-break{break-after:page;page-break-after:always}.sc-sheet.sc-break:last-child{break-after:auto;page-break-after:auto}.sc-toolbar{display:none!important}}'
            .'.sc-toolbar{position:sticky;top:0;z-index:10;display:flex;gap:12px;align-items:center;justify-content:space-between;max-width:'.$fmt(max($width,180)).'mm;margin:-24px auto 18px;padding:12px 16px;background:#0f172a;color:#fff;border-radius:0 0 10px 10px;font:500 13px Arial,sans-serif}'
            .'.sc-toolbar button{border:0;border-radius:6px;padding:9px 16px;background:#6366f1;color:#fff;font:600 13px Arial,sans-serif;cursor:pointer}';
        $userCss = str_ireplace('</style','<\/style',(string)$content['css']);
        $title = htmlspecialchars((string)($template['name'] ?? 'Wydruk'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $toolbar = ''; $script = '';
        if ($mode === 'print') {
            $toolbar = '<div class="sc-toolbar"><span><strong>'.$title.'</strong> · zamówień: '.count($contexts).' · w oknie druku wybierz „Zapisz jako PDF”</span><button type="button" id="sc-print">Zapisz PDF / drukuj</button></div>';
            $script = '<script nonce="'.htmlspecialchars($nonce,ENT_QUOTES,'UTF-8').'">document.getElementById("sc-print").addEventListener("click",function(){window.print();});window.addEventListener("load",function(){setTimeout(function(){window.print();},350);});</script>';
        }
        return '<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$title.'</title>'
            .'<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap">'
            .'<style>'.$css."\n".$userCss.'</style></head><body>'.$toolbar.$body.$script.'</body></html>';
    }

    /** Wiersze CSV (pierwszy to nagłówek, jeśli włączony). */
    public function csvRows(array $template,array $contexts,array $globals): array
    {
        $content = $template['content'];
        $rows = [];
        if ($content['header_row']) { $rows[] = array_column($content['columns'],'header'); }
        $count = count($contexts);
        foreach ($contexts as $index => $context) {
            $orderScopes = [$globals,$context,['loop'=>self::loopInfo($index,$count)]];
            if ($content['rows'] === 'item') {
                $items = $context['items'] ?: [self::emptyItem()];
                $itemCount = count($items);
                foreach ($items as $itemIndex => $item) { $rows[] = $this->csvRow($content['columns'],array_merge($orderScopes,[['item'=>$item,'loop'=>self::loopInfo($itemIndex,$itemCount)]])); }
            } else {
                $rows[] = $this->csvRow($content['columns'],$orderScopes);
            }
        }
        return $rows;
    }

    public function csv(array $template,array $contexts,array $globals): string
    {
        $content = $template['content'];
        $separator = $content['separator'] === 'tab' ? "\t" : $content['separator'];
        $handle = fopen('php://temp','w+');
        foreach ($this->csvRows($template,$contexts,$globals) as $row) { fputcsv($handle,$row,$separator,'"',''); }
        rewind($handle);
        $csv = (string)stream_get_contents($handle);
        fclose($handle);
        return ($content['bom'] ? "\xEF\xBB\xBF" : '').$csv;
    }

    /** Renderuje fragment szablonu dla stosu zakresów (ostatni ma pierwszeństwo). */
    public function render(string $template,array $scopes,bool $escape): string
    {
        return $this->renderNodes(self::parse($template),$scopes,$escape);
    }

    private function csvRow(array $columns,array $scopes): array
    {
        $row = [];
        foreach ($columns as $column) {
            $value = trim(preg_replace("/[\r\n]+/",' ',$this->render((string)$column['value'],$scopes,false)) ?? '');
            // Ochrona przed formułami w Excelu (CSV injection); liczby ujemne zostają bez zmian.
            if ($value !== '' && strpbrk($value[0],"=+-@\t") !== false && !preg_match('/^-?\d+([.,]\d+)?$/',$value)) { $value = "'".$value; }
            $row[] = $value;
        }
        return $row;
    }

    private function orderContext(array $order,array $shipments,array $documents,array $notes): array
    {
        $details = (array)($order['details'] ?? []);
        $currency = (string)($order['currency'] ?? 'PLN');
        $items = []; $itemsCents = 0; $quantity = 0; $summary = [];
        foreach (array_values((array)($details['items'] ?? [])) as $index => $item) {
            if (!is_array($item)) { continue; }
            $qty = max(0,(int)($item['quantity'] ?? 0)); $unit = (int)($item['unit_cents'] ?? 0);
            $vat = trim((string)($item['vat'] ?? ''));
            $rate = is_numeric($vat) ? (float)$vat : 0.0;
            $line = $qty * $unit; $itemsCents += $line; $quantity += $qty;
            $offerLink = is_array($item['offer_link'] ?? null) ? $item['offer_link'] : [];
            $items[] = [
                'lp'=>$index + 1,'name'=>(string)($item['name'] ?? ''),'sku'=>(string)($item['sku'] ?? ''),'ean'=>(string)($item['ean'] ?? ''),'quantity'=>$qty,
                'unit_cents'=>$unit,'unit_price'=>self::money($unit),'total_price'=>self::money($line),
                'unit_net'=>self::money((int)round($unit / (1 + $rate / 100))),'total_net'=>self::money((int)round($line / (1 + $rate / 100))),'vat'=>$vat,
                'image_url'=>(string)($item['image_url'] ?? ''),'offer_id'=>(string)($item['offer_id'] ?? ''),'offer_url'=>(string)($offerLink['url'] ?? ''),'external_id'=>(string)($item['external_id'] ?? ''),
            ];
            $summary[] = $qty.'× '.(string)($item['name'] ?? '');
        }
        $shippingCents = (int)($details['shipping_cents'] ?? 0);
        $totalCents = (int)($order['total_cents'] ?? 0);
        $adjustment = $totalCents - $itemsCents - $shippingCents;
        $address = (array)($order['shipping_address'] ?? []);
        $invoice = (array)($details['invoice_form'] ?? []);
        $raw = is_array($details['raw'] ?? null) ? $details['raw'] : [];
        $shippingStreet = trim((string)($address['street'] ?? '').' '.(string)($address['building'] ?? ''));
        $invoiceStreet = trim((string)($invoice['street'] ?? '').' '.(string)($invoice['building'] ?? ''));
        $shippingCompany = trim((string)($details['address']['companyName'] ?? $details['address']['company'] ?? ''));
        $shippingLines = array_values(array_filter([(string)($address['name'] ?? ''),$shippingCompany,$shippingStreet,trim((string)($address['postal_code'] ?? '').' '.(string)($address['city'] ?? '')),(string)($address['country'] ?? '')],'strlen'));
        $invoiceLines = array_values(array_filter([(string)($invoice['company'] ?? ''),(string)($invoice['name'] ?? ''),$invoiceStreet,trim((string)($invoice['postal_code'] ?? '').' '.(string)($invoice['city'] ?? '')),!empty($invoice['nip']) ? 'NIP: '.$invoice['nip'] : ''],'strlen'));
        $shipmentRows = []; $tracking = []; $carriers = [];
        foreach ($shipments as $shipment) {
            $presentation = OrderShipmentService::presentation($shipment,$order);
            $number = (string)($shipment['tracking'] ?? '');
            if (strpos($number,'PENDING:') === 0) { $number = ''; }
            if ($number !== '' && empty($presentation['cancelled'])) { $tracking[] = $number; $carriers[(string)$presentation['carrier']] = true; }
            $shipmentRows[] = ['tracking'=>$number,'carrier'=>(string)$presentation['carrier'],'service'=>(string)$presentation['service'],'provider'=>(string)$presentation['provider_label'],'status'=>(string)$presentation['status_label'],
                'weight'=>(string)($shipment['weight'] ?? ''),'cod_amount'=>self::money((int)($shipment['cod_amount_cents'] ?? 0)),'created_at'=>self::localDate((string)($shipment['created_at'] ?? ''))];
        }
        $documentRows = []; $firstDocument = [];
        foreach ($documents as $document) {
            $kind = (string)$document['kind'];
            $documentRows[] = ['number'=>(string)$document['number'],'kind'=>self::DOCUMENT_KINDS[$kind] ?? $kind,'kind_code'=>$kind,'created_at'=>self::localDate((string)$document['created_at'])];
            if (!isset($firstDocument[$kind])) { $firstDocument[$kind] = (string)$document['number']; }
        }
        $noteRows = array_map(static function (array $note): array {
            return ['body'=>(string)($note['body'] ?? ''),'author'=>(string)($note['author'] ?? ''),'created_at'=>self::localDate((string)($note['created_at'] ?? ''))];
        },$notes);
        $orderedAt = (string)($order['ordered_at'] ?? '');
        $paid = (bool)(int)($order['paid'] ?? 0); $cod = (bool)(int)($details['cash_on_delivery'] ?? 0);
        $paidCents = (int)($details['amount_paid_cents'] ?? ($paid ? $totalCents : 0));
        $platform = (string)($order['platform'] ?? '');
        return [
            'order'=>[
                'id'=>(int)$order['id'],'number'=>(string)($order['external_id'] ?? ''),'internal_number'=>'#'.(int)$order['id'],
                'platform'=>$platform,'platform_label'=>self::PLATFORMS[$platform] ?? ucfirst($platform),'account'=>(string)($order['account_name'] ?? ''),
                'status'=>(string)($order['status_name'] ?? ''),'status_color'=>(string)($order['color'] ?? ''),'remote_status'=>(string)($order['remote_status'] ?? ''),
                'date'=>self::localDate($orderedAt),'date_only'=>self::localDate($orderedAt,'d.m.Y'),'time'=>self::localDate($orderedAt,'H:i'),'date_iso'=>self::localDate($orderedAt,'Y-m-d'),
                'imported_at'=>self::localDate((string)($order['imported_at'] ?? '')),'updated_at'=>self::localDate((string)($order['updated_at'] ?? '')),'status_changed_at'=>self::localDate((string)($order['status_changed_at'] ?? '')),
                'total'=>self::money($totalCents),'total_dot'=>number_format($totalCents / 100,2,'.',''),'total_cents'=>$totalCents,'currency'=>$currency,
                'items_total'=>self::money($itemsCents),'shipping_cost'=>self::money($shippingCents),'adjustment'=>self::money($adjustment),'adjustment_cents'=>$adjustment,'adjustment_label'=>$adjustment < 0 ? 'Rabat' : 'Pozostałe opłaty',
                'items_count'=>count($items),'items_quantity'=>$quantity,'items_summary'=>implode('; ',$summary),
                'tags'=>(string)($order['tags'] ?? ''),'note'=>(string)($noteRows[0]['body'] ?? ($order['note'] ?? '')),'notes_text'=>implode("\n",array_column($noteRows,'body')),'buyer_note'=>(string)($details['buyer_note'] ?? ''),
                'starred'=>(bool)(int)($order['starred'] ?? 0),'document_type'=>($details['document_preference'] ?? '') === 'invoice' ? 'Faktura' : 'Paragon',
                'tracking_numbers'=>implode(', ',$tracking),'carriers'=>implode(', ',array_keys($carriers)),
                'barcode_url'=>self::barcodeDataUri((string)($order['external_id'] ?? '')),'id_barcode_url'=>self::barcodeDataUri((string)(int)$order['id']),
                'invoice_number'=>$firstDocument['invoice'] ?? '','receipt_number'=>$firstDocument['receipt'] ?? '','document_numbers'=>implode(', ',array_column($documentRows,'number')),
            ],
            'buyer'=>['name'=>(string)($order['buyer_name'] ?? ''),'email'=>(string)($order['email'] ?? ''),'phone'=>(string)($order['phone'] ?? ''),'login'=>(string)($raw['buyer']['login'] ?? $raw['customer']['login'] ?? '')],
            'shipping'=>[
                'name'=>(string)($address['name'] ?? ''),'company'=>$shippingCompany,'street'=>(string)($address['street'] ?? ''),'building'=>(string)($address['building'] ?? ''),'street_full'=>$shippingStreet,
                'postal_code'=>(string)($address['postal_code'] ?? ''),'city'=>(string)($address['city'] ?? ''),'country'=>(string)($address['country'] ?? ''),
                'phone'=>(string)($address['phone'] ?? ''),'email'=>(string)($address['email'] ?? ''),'address'=>implode(', ',$shippingLines),'address_lines'=>implode("\n",$shippingLines),
                'method'=>(string)($details['delivery'] ?? ''),'pickup_point'=>(string)($details['pickup'] ?? ''),'cost'=>self::money($shippingCents),
            ],
            'invoice'=>[
                'required'=>(bool)(int)($details['invoice_required'] ?? 0),'company'=>(string)($invoice['company'] ?? ''),'nip'=>(string)($invoice['nip'] ?? ''),'name'=>(string)($invoice['name'] ?? ''),
                'street'=>(string)($invoice['street'] ?? ''),'building'=>(string)($invoice['building'] ?? ''),'street_full'=>$invoiceStreet,'postal_code'=>(string)($invoice['postal_code'] ?? ''),
                'city'=>(string)($invoice['city'] ?? ''),'country'=>(string)($invoice['country'] ?? ''),'address'=>implode(', ',$invoiceLines),'address_lines'=>implode("\n",$invoiceLines),
            ],
            'payment'=>[
                'method'=>(string)($details['payment_method'] ?? ''),'source_method'=>(string)($details['source_payment_method'] ?? ''),'paid'=>$paid,'cod'=>$cod,
                'status'=>$cod ? 'Za pobraniem' : ($paid ? 'Opłacone' : 'Nieopłacone'),
                'paid_amount'=>self::money($paidCents),'due_amount'=>self::money((int)($details['amount_due_cents'] ?? max(0,$totalCents - $paidCents))),
            ],
            'items'=>$items,'shipments'=>$shipmentRows,'documents'=>$documentRows,'notes'=>$noteRows,
            'raw'=>$raw,
        ];
    }

    /** Kod kreskowy Code 128 (zestaw B) jako obraz SVG w data URI; znaki spoza ASCII 32–126 są pomijane. */
    public static function barcodeDataUri(string $text): string
    {
        $text = (string)preg_replace('/[^\x20-\x7E]/','',$text);
        if ($text === '' || strlen($text) > 60) { return ''; }
        $codes = [104];
        foreach (str_split($text) as $char) { $codes[] = ord($char) - 32; }
        $checksum = 104;
        foreach (array_slice($codes,1) as $position => $code) { $checksum += ($position + 1) * $code; }
        $codes[] = $checksum % 103; $codes[] = 106;
        $x = 10; $bars = '';
        foreach ($codes as $code) {
            foreach (str_split(self::CODE128[$code]) as $index => $width) {
                if ($index % 2 === 0) { $bars .= 'M'.$x.' 0h'.$width.'v60h-'.$width.'z'; }
                $x += (int)$width;
            }
        }
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.($x + 10).' 60" width="'.(($x + 10) * 2).'" height="120" preserveAspectRatio="none" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="#fff"/><path d="'.$bars.'" fill="#000"/></svg>';
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private static function emptyItem(): array
    {
        return ['lp'=>'','name'=>'','sku'=>'','ean'=>'','quantity'=>'','unit_cents'=>'','unit_price'=>'','total_price'=>'','unit_net'=>'','total_net'=>'','vat'=>'','image_url'=>'','offer_id'=>'','offer_url'=>'','external_id'=>''];
    }

    private static function loopInfo(int $index,int $count): array
    {
        return ['index'=>$index + 1,'index0'=>$index,'first'=>$index === 0,'last'=>$index === $count - 1,'count'=>$count];
    }

    public static function money(int $cents): string
    {
        return number_format($cents / 100,2,',','');
    }

    private static function localDate(string $utc,string $format='d.m.Y H:i'): string
    {
        if (trim($utc) === '') { return ''; }
        try { return (new \DateTimeImmutable($utc,new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Warsaw'))->format($format); }
        catch (\Throwable $e) { return $utc; }
    }

    /** Drzewo węzłów: ['t',tekst] | ['v',wyrażenie] | ['type'=>'each'|'if',…]. Niedomknięte bloki kończą się z końcem szablonu. */
    private static function parse(string $template): array
    {
        $tokens = preg_split('/(\{\{.*?\}\})/s',$template,-1,PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY) ?: [];
        $position = 0;
        return self::parseBlock($tokens,$position,'')[0];
    }

    private static function parseBlock(array $tokens,int &$position,string $closing): array
    {
        $children = []; $else = null;
        while ($position < count($tokens)) {
            $part = $tokens[$position++];
            $isTag = strncmp($part,'{{',2) === 0 && substr($part,-2) === '}}';
            $tag = $isTag ? trim(substr($part,2,-2)) : '';
            if (!$isTag) { $node = ['t',$part]; }
            elseif (preg_match('/^#each\s+([A-Za-z0-9_.]+)(?:\s+as\s+([A-Za-z_][A-Za-z0-9_]*))?$/',$tag,$m)) {
                $node = ['type'=>'each','path'=>$m[1],'alias'=>$m[2] ?? '','children'=>self::parseBlock($tokens,$position,'each')[0]];
            } elseif (preg_match('/^#(if|unless)\s+([A-Za-z0-9_.]+)$/',$tag,$m)) {
                [$inner,$innerElse] = self::parseBlock($tokens,$position,'if');
                $node = ['type'=>'if','path'=>$m[2],'negate'=>$m[1] === 'unless','children'=>$inner,'else'=>$innerElse];
            } elseif ($tag === 'else' && $closing === 'if' && $else === null) { $else = []; continue; }
            elseif (preg_match('#^/(each|if|unless)$#',$tag,$m) && $closing !== '' && $closing === ($m[1] === 'unless' ? 'if' : $m[1])) { break; }
            elseif (preg_match('#^(/|\#|else$)#',$tag)) { $node = ['t',$part]; }
            else { $node = ['v',$tag]; }
            if ($else !== null) { $else[] = $node; } else { $children[] = $node; }
        }
        return [$children,$else ?? []];
    }

    private function renderNodes(array $nodes,array $scopes,bool $escape): string
    {
        if (++$this->depth > 40) { $this->depth--; return ''; }
        $out = '';
        foreach ($nodes as $node) {
            if (isset($node[0]) && $node[0] === 't') { $out .= $node[1]; continue; }
            if (isset($node[0]) && $node[0] === 'v') { $out .= $this->expression((string)$node[1],$scopes,$escape); continue; }
            if (($node['type'] ?? '') === 'if') {
                $truthy = self::truthy(self::lookup($node['path'],$scopes));
                if ($node['negate']) { $truthy = !$truthy; }
                $out .= $this->renderNodes($truthy ? $node['children'] : $node['else'],$scopes,$escape);
                continue;
            }
            if (($node['type'] ?? '') === 'each') {
                $list = self::lookup($node['path'],$scopes);
                if (!is_array($list) || !$list) { continue; }
                $list = array_values($list);
                $last = substr((string)strrchr('.'.$node['path'],'.'),1);
                $alias = $node['alias'] !== '' ? $node['alias'] : (array_key_exists($last,self::LOOP_ALIASES) ? self::LOOP_ALIASES[$last] : 'this');
                $count = count($list);
                foreach (array_slice($list,0,5000) as $index => $element) {
                    $scope = $alias === null && is_array($element) ? $element : [$alias=>$element];
                    $scope['loop'] = self::loopInfo($index,$count);
                    $scopes[] = $scope;
                    $out .= $this->renderNodes($node['children'],$scopes,$escape);
                    array_pop($scopes);
                }
            }
        }
        $this->depth--;
        return $out;
    }

    private function expression(string $expression,array $scopes,bool $escape): string
    {
        preg_match_all('/\|\s*([a-z_0-9]+)((?:\s*:\s*(?:"(?:[^"\\\\]|\\\\.)*"|[^|:"]*))*)/i',$expression,$filters,PREG_SET_ORDER);
        $path = trim((string)strtok($expression,'|'));
        $value = self::stringify(self::lookup($path,$scopes));
        $html = false;
        foreach ($filters as $filter) {
            $name = strtolower($filter[1]);
            preg_match_all('/:\s*("(?:[^"\\\\]|\\\\.)*"|[^|:"]*)/',(string)($filter[2] ?? ''),$argMatches);
            $args = array_map(static function (string $arg): string { $arg = trim($arg); return $arg !== '' && $arg[0] === '"' ? stripcslashes(substr($arg,1,-1)) : $arg; },$argMatches[1]);
            $arg = $args[0] ?? '';
            switch ($name) {
                case 'upper': $value = mb_strtoupper($value,'UTF-8'); break;
                case 'lower': $value = mb_strtolower($value,'UTF-8'); break;
                case 'trim': $value = trim($value); break;
                case 'truncate': $limit = max(1,(int)$arg ?: 50); if (mb_strlen($value,'UTF-8') > $limit) { $value = rtrim(mb_substr($value,0,$limit,'UTF-8')).'…'; } break;
                case 'default': if (trim($value) === '') { $value = $arg; } break;
                case 'nl2br': $html = true; break;
                case 'replace': if ($arg !== '') { $value = str_replace($arg,$args[1] ?? '',$value); } break;
                case 'lines':
                    // Dzieli po enterach i po separatorze; separator ze spacjami (np. " / ") nie łamie "WiFi/BT", a separator na brzegu linii jest ucinany.
                    $separator = $arg !== '' ? $arg : '/'; $core = trim($separator);
                    $lines = [];
                    foreach (preg_split('/\R/u',$value) ?: [] as $line) {
                        foreach (explode($separator,$line) as $piece) {
                            $piece = trim($piece);
                            if ($core !== '') { $piece = trim((string)preg_replace('/^'.preg_quote($core,'/').'|'.preg_quote($core,'/').'$/u','',$piece)); }
                            if ($piece !== '') { $lines[] = $piece; }
                        }
                    }
                    $value = implode("\n",$lines);
                    break;
            }
        }
        if (!$escape) { return $value; }
        $value = htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        return $html ? nl2br($value,false) : $value;
    }

    private static function lookup(string $path,array $scopes)
    {
        $segments = explode('.',$path);
        $first = array_shift($segments);
        if ($first === '' ) { return null; }
        $value = null; $found = false;
        for ($i = count($scopes) - 1; $i >= 0; $i--) {
            if (is_array($scopes[$i]) && array_key_exists($first,$scopes[$i])) { $value = $scopes[$i][$first]; $found = true; break; }
        }
        if (!$found) { return null; }
        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment,$value)) { $value = $value[$segment]; }
            else { return null; }
        }
        return $value;
    }

    private static function stringify($value): string
    {
        if ($value === null) { return ''; }
        if (is_bool($value)) { return $value ? 'Tak' : 'Nie'; }
        if (is_scalar($value)) { return (string)$value; }
        if (is_array($value)) {
            $scalars = array_filter($value,'is_scalar');
            return count($scalars) === count($value) ? implode(', ',array_map('strval',$value)) : json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }
        return '';
    }

    private static function truthy($value): bool
    {
        if (is_string($value)) { return trim($value) !== '' && $value !== '0'; }
        return !empty($value);
    }
}
