<?php

declare(strict_types=1);
namespace App\Services;

use App\Models\OrderRepository;
use App\Models\PrintAgentRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * Order automations: triggers → conditions → actions executed one after another.
 * OrderRepository queues events raised inside a transaction and dispatches them after commit,
 * so carrier, marketplace, e-mail and webhook calls never run while row locks are held.
 * Events raised by actions are processed after the current event (depth-limited), and one rule
 * runs at most once per order within a single chain, which prevents automation loops.
 */
final class OrderAutomationService
{
    private const MAX_DEPTH=4;
    private const MAX_CONDITIONS=30;
    private const MAX_ACTIONS=20;
    private const DELAY_UNITS=['minutes'=>60,'hours'=>3600,'days'=>86400];
    private const DELAY_FROM=['status'=>'zmiany statusu','ordered'=>'złożenia zamówienia','imported'=>'pobrania zamówienia'];
    private const CANCELLED=['CANCELLED','CANCELED','ANULOWANO'];
    private const PLATFORMS=['manual'=>'Własne','allegro'=>'Allegro','erli'=>'ERLI','empik'=>'Empik','mediamarkt'=>'MediaMarkt','morele'=>'Morele','temu'=>'Temu','prestashop'=>'PrestaShop','woocommerce'=>'WooCommerce','altreo'=>'Altreo.pl','api'=>'Własny sklep (API)'];
    private const OPERATORS=[
        'select'=>['in'=>'jest jednym z','not_in'=>'nie jest żadnym z'],
        'bool'=>['is'=>'jest'],
        'number'=>['gte'=>'co najmniej (≥)','lte'=>'najwyżej (≤)','gt'=>'więcej niż (>)','lt'=>'mniej niż (<)','eq'=>'równe (=)','neq'=>'różne od (≠)','between'=>'pomiędzy'],
        'text'=>['contains'=>'zawiera','not_contains'=>'nie zawiera','equals'=>'jest równe','not_equals'=>'jest różne od','starts_with'=>'zaczyna się od','empty'=>'jest puste','not_empty'=>'nie jest puste'],
        'list'=>['any'=>'zawiera dowolny z','all'=>'zawiera wszystkie','none'=>'nie zawiera żadnego z','empty'=>'jest puste','not_empty'=>'nie jest puste'],
        'time'=>['between'=>'pomiędzy'],
    ];

    private $repo;
    private $db;
    private $queue=[];
    private $running=false;
    private $depth=0;
    private $chain=[];
    private $report=null;
    private $options=null;

    public function __construct(OrderRepository $repo)
    {
        $this->repo=$repo;
        $this->db=$repo->db();
    }

    public static function triggerDefinitions(): array
    {
        return [
            'order_created'=>['label'=>'Nowe zamówienie','icon'=>'bi-bag-plus','group'=>'Zamówienie','hint'=>'Zamówienie pobrane pierwszy raz z marketplace lub dodane ręcznie.'],
            'import'=>['label'=>'Import lub odświeżenie','icon'=>'bi-cloud-arrow-down','group'=>'Zamówienie','hint'=>'Każdy odczyt zamówienia z marketplace. Zwykle z limitem „raz na zamówienie”.'],
            'details_changed'=>['label'=>'Edycja danych zamówienia','icon'=>'bi-pencil-square','group'=>'Zamówienie','hint'=>'Operator zapisał dane klienta, dostawy, płatności lub pozycji.'],
            'status'=>['label'=>'Zmiana statusu','icon'=>'bi-arrow-left-right','group'=>'Status','hint'=>'Status wewnętrzny zmieniony ręcznie, przez mapowanie lub automatyzację.'],
            'remote_status'=>['label'=>'Zmiana statusu w marketplace','icon'=>'bi-broadcast','group'=>'Status','hint'=>'Marketplace przesłał nowy status źródłowy.'],
            'paid'=>['label'=>'Opłacenie zamówienia','icon'=>'bi-cash-coin','group'=>'Płatność','hint'=>'Płatność zmieniła się na potwierdzoną albo nowe zamówienie jest już opłacone.'],
            'unpaid'=>['label'=>'Cofnięcie płatności','icon'=>'bi-x-octagon','group'=>'Płatność','hint'=>'Źródło, operator lub automatyzacja oznaczyli zamówienie jako nieopłacone.'],
            'document_issued'=>['label'=>'Wystawienie dokumentu','icon'=>'bi-receipt','group'=>'Dokumenty i wysyłka','hint'=>'Wystawiono paragon, fakturę albo korektę.'],
            'shipment_created'=>['label'=>'Utworzenie przesyłki','icon'=>'bi-box-seam','group'=>'Dokumenty i wysyłka','hint'=>'Przesyłka została założona u przewoźnika.'],
            'shipment_status'=>['label'=>'Zmiana statusu przesyłki','icon'=>'bi-truck','group'=>'Dokumenty i wysyłka','hint'=>'Odświeżenie przesyłki przyniosło nowy status od przewoźnika.'],
            'tracking_sent'=>['label'=>'Numer przesyłki w marketplace','icon'=>'bi-cloud-check','group'=>'Dokumenty i wysyłka','hint'=>'Marketplace potwierdził odbiór numeru przesyłki.'],
            'scheduled'=>['label'=>'Upływ czasu','icon'=>'bi-hourglass-split','group'=>'Specjalne','hint'=>'Sprawdzane co minutę przez cron bin/orders-sync.php.'],
            'manual'=>['label'=>'Uruchomienie ręczne','icon'=>'bi-hand-index-thumb','group'=>'Specjalne','hint'=>'Tylko z przycisku w zamówieniu albo z akcji masowej na liście.'],
        ];
    }

    public static function conditionDefinitions(): array
    {
        return [
            'account'=>['label'=>'Konto sprzedaży','group'=>'Zamówienie','type'=>'select','options'=>'accounts'],
            'platform'=>['label'=>'Kanał sprzedaży','group'=>'Zamówienie','type'=>'select','options'=>'platforms'],
            'status'=>['label'=>'Status wewnętrzny','group'=>'Zamówienie','type'=>'select','options'=>'statuses'],
            'remote_status'=>['label'=>'Status w marketplace','group'=>'Zamówienie','type'=>'text'],
            'total'=>['label'=>'Wartość zamówienia','group'=>'Zamówienie','type'=>'number','unit'=>'brutto'],
            'currency'=>['label'=>'Waluta','group'=>'Zamówienie','type'=>'select','options'=>'currencies'],
            'tags'=>['label'=>'Tagi','group'=>'Zamówienie','type'=>'list','hint'=>'Rozdziel tagi przecinkami.'],
            'note'=>['label'=>'Notatka wewnętrzna','group'=>'Zamówienie','type'=>'text'],
            'order_age'=>['label'=>'Czas od złożenia zamówienia','group'=>'Zamówienie','type'=>'number','unit'=>'h'],
            'status_age'=>['label'=>'Czas w obecnym statusie','group'=>'Zamówienie','type'=>'number','unit'=>'h'],
            'rule_executed'=>['label'=>'Wcześniej wykonana automatyzacja','group'=>'Zamówienie','type'=>'select','options'=>'rules'],
            'buyer_name'=>['label'=>'Kupujący','group'=>'Klient','type'=>'text'],
            'email'=>['label'=>'E-mail kupującego','group'=>'Klient','type'=>'text'],
            'phone'=>['label'=>'Telefon kupującego','group'=>'Klient','type'=>'text'],
            'buyer_note'=>['label'=>'Wiadomość od kupującego','group'=>'Klient','type'=>'text'],
            'document_preference'=>['label'=>'Dokument wybrany przez klienta','group'=>'Klient','type'=>'select','options'=>'document_preferences'],
            'has_tax_id'=>['label'=>'Podano NIP do faktury','group'=>'Klient','type'=>'bool'],
            'customer_orders'=>['label'=>'Liczba zamówień klienta (po e-mailu)','group'=>'Klient','type'=>'number'],
            'paid'=>['label'=>'Płatność potwierdzona','group'=>'Płatność','type'=>'bool'],
            'payment_state'=>['label'=>'Status płatności','group'=>'Płatność','type'=>'select','options'=>'payment_states'],
            'payment_method'=>['label'=>'Metoda płatności','group'=>'Płatność','type'=>'select','options'=>'payment_methods'],
            'payment_method_text'=>['label'=>'Nazwa metody płatności','group'=>'Płatność','type'=>'text'],
            'cod'=>['label'=>'Płatność przy odbiorze (pobranie)','group'=>'Płatność','type'=>'bool'],
            'amount_due'=>['label'=>'Kwota do zapłaty','group'=>'Płatność','type'=>'number'],
            'delivery_method'=>['label'=>'Metoda dostawy','group'=>'Dostawa','type'=>'text'],
            'pickup_point'=>['label'=>'Dostawa do punktu / Paczkomatu','group'=>'Dostawa','type'=>'bool'],
            'country'=>['label'=>'Kraj dostawy','group'=>'Dostawa','type'=>'select','options'=>'countries'],
            'postal_code'=>['label'=>'Kod pocztowy dostawy','group'=>'Dostawa','type'=>'text'],
            'city'=>['label'=>'Miasto dostawy','group'=>'Dostawa','type'=>'text'],
            'shipping_cost'=>['label'=>'Koszt dostawy','group'=>'Dostawa','type'=>'number'],
            'sku'=>['label'=>'SKU w zamówieniu','group'=>'Produkty','type'=>'list','hint'=>'Rozdziel przecinkami; gwiazdka zastępuje dowolny ciąg, np. PEN-*.'],
            'product_name'=>['label'=>'Nazwa produktu','group'=>'Produkty','type'=>'text'],
            'item_lines'=>['label'=>'Liczba różnych produktów','group'=>'Produkty','type'=>'number'],
            'item_quantity'=>['label'=>'Łączna liczba sztuk','group'=>'Produkty','type'=>'number'],
            'has_receipt'=>['label'=>'Wystawiono paragon','group'=>'Dokumenty','type'=>'bool'],
            'has_invoice'=>['label'=>'Wystawiono fakturę','group'=>'Dokumenty','type'=>'bool'],
            'has_correction'=>['label'=>'Wystawiono korektę','group'=>'Dokumenty','type'=>'bool'],
            'fiscal_printed'=>['label'=>'Paragon wydrukowany fiskalnie','group'=>'Dokumenty','type'=>'bool'],
            'has_shipment'=>['label'=>'Utworzono przesyłkę','group'=>'Przesyłki','type'=>'bool'],
            'shipment_count'=>['label'=>'Liczba aktywnych przesyłek','group'=>'Przesyłki','type'=>'number'],
            'carrier_account'=>['label'=>'Konto nadawcze przesyłki','group'=>'Przesyłki','type'=>'select','options'=>'carrier_accounts'],
            'shipment_stage'=>['label'=>'Etap przesyłki u przewoźnika','group'=>'Przesyłki','type'=>'select','options'=>'shipment_stages'],
            'has_tracking'=>['label'=>'Nadano numer przesyłki','group'=>'Przesyłki','type'=>'bool'],
            'tracking_sent'=>['label'=>'Numer przekazany do marketplace','group'=>'Przesyłki','type'=>'bool'],
            'label_printed'=>['label'=>'Etykieta wydrukowana','group'=>'Przesyłki','type'=>'bool'],
            'previous_status'=>['label'=>'Poprzedni status','group'=>'Zdarzenie','type'=>'select','options'=>'statuses','events'=>['status']],
            'status_source'=>['label'=>'Kto zmienił status','group'=>'Zdarzenie','type'=>'select','options'=>'status_sources','events'=>['status']],
            'document_kind'=>['label'=>'Rodzaj wystawionego dokumentu','group'=>'Zdarzenie','type'=>'select','options'=>'document_kinds','events'=>['document_issued']],
            'weekday'=>['label'=>'Dzień tygodnia (teraz)','group'=>'Czas wykonania','type'=>'select','options'=>'weekdays'],
            'hour'=>['label'=>'Godzina wykonania','group'=>'Czas wykonania','type'=>'time'],
        ];
    }

    public static function actionDefinitions(): array
    {
        $mail=[
            ['key'=>'subject','label'=>'Temat','type'=>'text','required'=>true,'max'=>200,'placeholders'=>true,'placeholder'=>'Zamówienie {numer_zamowienia}'],
            ['key'=>'body','label'=>'Treść','type'=>'textarea','required'=>true,'max'=>10000,'placeholders'=>true],
        ];
        return [
            'set_status'=>['label'=>'Zmień status','group'=>'Zamówienie','icon'=>'bi-arrow-left-right','params'=>[['key'=>'status_id','label'=>'Nowy status','type'=>'select','options'=>'statuses','required'=>true]]],
            'add_tags'=>['label'=>'Dodaj tagi','group'=>'Zamówienie','icon'=>'bi-tags','params'=>[['key'=>'tags','label'=>'Tagi (po przecinku)','type'=>'text','required'=>true,'max'=>300,'placeholder'=>'np. priorytet, opłacone']]],
            'remove_tags'=>['label'=>'Usuń tagi','group'=>'Zamówienie','icon'=>'bi-tag','params'=>[['key'=>'tags','label'=>'Tagi do usunięcia','type'=>'text','required'=>true,'max'=>300]]],
            'append_note'=>['label'=>'Dopisz notatkę wewnętrzną','group'=>'Zamówienie','icon'=>'bi-sticky','params'=>[['key'=>'text','label'=>'Treść notatki','type'=>'textarea','required'=>true,'max'=>2000,'placeholders'=>true]]],
            'add_event'=>['label'=>'Dodaj wpis do historii','group'=>'Zamówienie','icon'=>'bi-journal-text','params'=>[['key'=>'text','label'=>'Treść wpisu','type'=>'text','required'=>true,'max'=>500,'placeholders'=>true]]],
            'set_paid'=>['label'=>'Oznacz płatność','group'=>'Zamówienie','icon'=>'bi-cash-coin','hint'=>'Zmiana jest chroniona przed nadpisaniem przez synchronizację.','params'=>[['key'=>'state','label'=>'Ustaw jako','type'=>'select','options'=>[['paid','Opłacone w całości'],['unpaid','Nieopłacone']],'required'=>true,'default'=>'paid']]],
            'set_document_preference'=>['label'=>'Ustaw dokument sprzedaży','group'=>'Zamówienie','icon'=>'bi-file-earmark-check','params'=>[['key'=>'kind','label'=>'Dokument','type'=>'select','options'=>'document_preferences','required'=>true,'default'=>'invoice']]],
            'set_payment_method'=>['label'=>'Ustaw metodę płatności','group'=>'Zamówienie','icon'=>'bi-credit-card','params'=>[['key'=>'payment_method_id','label'=>'Metoda płatności','type'=>'select','options'=>'payment_method_ids','required'=>true]]],
            'set_delivery'=>['label'=>'Zmień metodę dostawy','group'=>'Zamówienie','icon'=>'bi-signpost-split','params'=>[['key'=>'delivery','label'=>'Nazwa metody dostawy','type'=>'text','required'=>true,'max'=>255]]],
            'issue_receipt'=>['label'=>'Wystaw paragon','group'=>'Dokumenty','icon'=>'bi-receipt','hint'=>'Pomija zamówienie, dla którego paragon już istnieje.','params'=>[['key'=>'series_id','label'=>'Seria','type'=>'select','options'=>'receipt_series','default'=>'0'],['key'=>'print','label'=>'Wyślij do drukarki fiskalnej przypisanej do serii','type'=>'checkbox','default'=>true]]],
            'issue_invoice'=>['label'=>'Wystaw fakturę','group'=>'Dokumenty','icon'=>'bi-file-earmark-text','hint'=>'Pomija zamówienie, dla którego faktura już istnieje.','params'=>[['key'=>'series_id','label'=>'Seria','type'=>'select','options'=>'invoice_series','default'=>'0']]],
            'issue_preferred'=>['label'=>'Wystaw dokument wybrany przez klienta','group'=>'Dokumenty','icon'=>'bi-file-earmark-medical','hint'=>'Faktura, gdy klient jej chce; w przeciwnym razie paragon.','params'=>[['key'=>'receipt_series_id','label'=>'Seria paragonów','type'=>'select','options'=>'receipt_series','default'=>'0'],['key'=>'invoice_series_id','label'=>'Seria faktur','type'=>'select','options'=>'invoice_series','default'=>'0'],['key'=>'print','label'=>'Paragon wyślij do drukarki fiskalnej','type'=>'checkbox','default'=>true]]],
            'print_fiscal'=>['label'=>'Drukuj paragon fiskalny','group'=>'Dokumenty','icon'=>'bi-printer','hint'=>'Wymaga wystawionego paragonu.','params'=>[['key'=>'printer_id','label'=>'Drukarka fiskalna','type'=>'select','options'=>'fiscal_printers','default'=>'0']]],
            'create_shipment'=>['label'=>'Nadaj przesyłkę','group'=>'Wysyłka','icon'=>'bi-box-seam','warning'=>'Utworzenie przesyłki może naliczyć opłatę u przewoźnika.','params'=>[['key'=>'carrier_account_id','label'=>'Konto nadawcze','type'=>'select','options'=>'carrier_accounts_auto','default'=>'0'],['key'=>'package','label'=>'Gabaryt','type'=>'select','options'=>[['auto','Automatycznie wg liczby sztuk'],['small','Mała paczka'],['medium','Średnia paczka'],['large','Duża paczka']],'default'=>'auto'],['key'=>'service','label'=>'Usługa (opcjonalnie)','type'=>'text','max'=>100,'placeholder'=>'np. inpost_locker_standard albo ID usługi Apaczki'],['key'=>'allow_multiple','label'=>'Nadaj także, gdy zamówienie ma już aktywną przesyłkę','type'=>'checkbox','default'=>false]]],
            'refresh_shipments'=>['label'=>'Odśwież status przesyłek','group'=>'Wysyłka','icon'=>'bi-arrow-repeat','params'=>[]],
            'publish_tracking'=>['label'=>'Przekaż numer przesyłki do marketplace','group'=>'Wysyłka','icon'=>'bi-cloud-arrow-up','params'=>[['key'=>'carrier','label'=>'Przewoźnik','type'=>'select','options'=>'source_carriers','default'=>'auto'],['key'=>'carrier_other','label'=>'Nazwa innego przewoźnika','type'=>'text','max'=>100]]],
            'print_label'=>['label'=>'Drukuj etykietę','group'=>'Wysyłka','icon'=>'bi-printer-fill','params'=>[['key'=>'printer','label'=>'Drukarka etykiet','type'=>'select','options'=>'label_printers','required'=>true],['key'=>'scope','label'=>'Zakres','type'=>'select','options'=>[['newest','Najnowsza przesyłka'],['all','Wszystkie aktywne przesyłki']],'default'=>'newest'],['key'=>'width','label'=>'Szerokość (mm)','type'=>'number','default'=>'100','min'=>30,'max'=>500],['key'=>'height','label'=>'Wysokość (mm)','type'=>'number','default'=>'150','min'=>30,'max'=>500]]],
            'email_customer'=>['label'=>'Wyślij e-mail do klienta','group'=>'Komunikacja','icon'=>'bi-envelope','params'=>$mail],
            'email_address'=>['label'=>'Wyślij e-mail na adres','group'=>'Komunikacja','icon'=>'bi-envelope-at','params'=>array_merge([['key'=>'to','label'=>'Adres e-mail','type'=>'email','required'=>true,'max'=>200,'placeholder'=>'magazyn@firma.pl']],$mail)],
            'webhook'=>['label'=>'Wywołaj webhook (POST JSON)','group'=>'Komunikacja','icon'=>'bi-broadcast-pin','hint'=>'Wysyła dane zamówienia bez surowej odpowiedzi marketplace. Tylko publiczne adresy HTTPS.','params'=>[['key'=>'url','label'=>'Adres HTTPS','type'=>'url','required'=>true,'max'=>500,'placeholder'=>'https://…']]],
            'accept_order'=>['label'=>'Zaakceptuj zamówienie (Empik / MediaMarkt)','group'=>'Marketplace','icon'=>'bi-check2-circle','hint'=>'Mirakl OR21 — tylko dla zamówień oczekujących na akceptację.','params'=>[]],
            'run_rule'=>['label'=>'Uruchom inną automatyzację','group'=>'Sterowanie','icon'=>'bi-diagram-3','hint'=>'Warunki tamtej automatyzacji są sprawdzane.','params'=>[['key'=>'rule_id','label'=>'Automatyzacja','type'=>'select','options'=>'rules','required'=>true]]],
            'stop'=>['label'=>'Zatrzymaj kolejne automatyzacje','group'=>'Sterowanie','icon'=>'bi-sign-stop','hint'=>'Pozostałe reguły tego zdarzenia nie zostaną sprawdzone.','params'=>[]],
        ];
    }

    public static function placeholders(): array
    {
        return ['{id}'=>'ID zamówienia','{numer_zamowienia}'=>'Numer w marketplace','{kupujacy}'=>'Kupujący','{email}'=>'E-mail','{telefon}'=>'Telefon','{kwota}'=>'Wartość','{waluta}'=>'Waluta','{status}'=>'Status','{platforma}'=>'Kanał','{konto}'=>'Konto','{dostawa}'=>'Metoda dostawy','{platnosc}'=>'Metoda płatności','{numer_przesylki}'=>'Numer przesyłki','{data_zamowienia}'=>'Data zamówienia','{dzisiaj}'=>'Dzisiejsza data'];
    }

    public static function triggerLabel(string $trigger): string
    {
        return self::triggerDefinitions()[$trigger]['label']??$trigger;
    }

    /** Data for the rule builder. Option lists are shared with server-side validation. */
    public function catalog(): array
    {
        $options=$this->optionSets();
        $conditions=[];
        foreach (self::conditionDefinitions() as $key=>$definition) {
            if (is_string($definition['options']??null)) { $definition['options']=$options[$definition['options']]; }
            $conditions[$key]=$definition;
        }
        $actions=[];
        foreach (self::actionDefinitions() as $key=>$definition) {
            foreach ($definition['params'] as &$param) {
                if (is_string($param['options']??null)) { $param['options']=$options[$param['options']]; }
            }
            unset($param);
            $actions[$key]=$definition;
        }
        return ['triggers'=>self::triggerDefinitions(),'conditions'=>$conditions,'operators'=>self::OPERATORS,'actions'=>$actions,'placeholders'=>self::placeholders(),'delay_units'=>['minutes'=>'minut','hours'=>'godzin','days'=>'dni'],'delay_from'=>self::DELAY_FROM];
    }

    private function optionSets(): array
    {
        if ($this->options!==null) { return $this->options; }
        $statuses=array_map(static function (array $s): array { return [(string)$s['id'],(string)$s['name'],(string)$s['color']]; },$this->repo->statuses());
        $accounts=array_map(static function (array $a): array { return [(string)$a['id'],(self::PLATFORMS[$a['platform']]??$a['platform']).' · '.$a['name']]; },$this->repo->accounts());
        $paymentMethods=$this->repo->paymentMethods();
        $carriers=array_map(static function (array $c): array { return [(string)$c['id'],(string)$c['name'].((int)$c['enabled']?'':' (wyłączone)')]; },$this->repo->carrierAccounts());
        $series=$this->db->fetchAll('SELECT id,name,kind FROM om_series ORDER BY id');
        $seriesOptions=static function (string $kind,string $default) use ($series): array {
            $rows=[['0',$default]];
            foreach ($series as $item) { if ($item['kind']===$kind) { $rows[]=[(string)$item['id'],(string)$item['name']]; } }
            return $rows;
        };
        $fiscal=[['0','Drukarka przypisana do serii paragonu']]; $labels=[];
        try {
            $printAgents=new PrintAgentRepository($this->db); $printAgents->ensureSchema();
            foreach ($printAgents->fiscalPrinters() as $printer) { $fiscal[]=[(string)$printer['id'],$printer['name'].' ('.($printer['environment']==='production'?'produkcja':'sandbox').')']; }
            foreach ($printAgents->stations() as $station) {
                if (!(int)$station['enabled']) { continue; }
                foreach ($station['printers'] as $printer) { $labels[]=[$station['id'].'|'.$printer,$printer,(string)$station['name']]; }
            }
        } catch (\Throwable $error) { /* Print agents are optional for automations. */ }
        $sourceCarriers=[['auto','Rozpoznaj z przesyłki i metody dostawy']];
        foreach (OrderMarketplaceShipmentService::carrierOptions() as $code=>$name) { $sourceCarriers[]=[$code,$name]; }
        $rules=array_map(static function (array $r): array { return [(string)$r['id'],(string)$r['name']]; },$this->db->fetchAll('SELECT id,name FROM om_rules ORDER BY position,id'));
        $pairs=static function (array $map): array { $rows=[]; foreach ($map as $value=>$label) { $rows[]=[(string)$value,(string)$label]; } return $rows; };
        return $this->options=[
            'statuses'=>$statuses,
            'accounts'=>$accounts,
            'platforms'=>$pairs(self::PLATFORMS),
            'currencies'=>$pairs(['PLN'=>'PLN','EUR'=>'EUR','USD'=>'USD','GBP'=>'GBP','CZK'=>'CZK','HUF'=>'HUF','CHF'=>'CHF','SEK'=>'SEK','NOK'=>'NOK','DKK'=>'DKK','RON'=>'RON','BGN'=>'BGN','UAH'=>'UAH']),
            'countries'=>$pairs(['PL'=>'Polska','CZ'=>'Czechy','SK'=>'Słowacja','DE'=>'Niemcy','AT'=>'Austria','HU'=>'Węgry','LT'=>'Litwa','LV'=>'Łotwa','EE'=>'Estonia','RO'=>'Rumunia','BG'=>'Bułgaria','HR'=>'Chorwacja','SI'=>'Słowenia','IT'=>'Włochy','FR'=>'Francja','ES'=>'Hiszpania','PT'=>'Portugalia','NL'=>'Holandia','BE'=>'Belgia','LU'=>'Luksemburg','DK'=>'Dania','SE'=>'Szwecja','FI'=>'Finlandia','IE'=>'Irlandia','GR'=>'Grecja','GB'=>'Wielka Brytania','UA'=>'Ukraina','NO'=>'Norwegia','CH'=>'Szwajcaria','US'=>'USA']),
            'weekdays'=>$pairs([1=>'Poniedziałek',2=>'Wtorek',3=>'Środa',4=>'Czwartek',5=>'Piątek',6=>'Sobota',7=>'Niedziela']),
            'payment_states'=>$pairs(['paid'=>'Opłacone w całości','partial'=>'Częściowo opłacone','unpaid'=>'Nieopłacone','overpaid'=>'Nadpłata','cod'=>'Za pobraniem']),
            'payment_methods'=>array_map(static function (array $m): array { return [(string)$m['name'],(string)$m['name']]; },$paymentMethods),
            'payment_method_ids'=>array_map(static function (array $m): array { return [(string)$m['id'],(string)$m['name'].((int)$m['is_cod']?' (pobranie)':'')]; },$paymentMethods),
            'document_preferences'=>$pairs(['receipt'=>'Paragon','invoice'=>'Faktura']),
            'document_kinds'=>$pairs(['receipt'=>'Paragon','invoice'=>'Faktura','receipt_correction'=>'Korekta paragonu','invoice_correction'=>'Korekta faktury']),
            'status_sources'=>$pairs(['user'=>'Operator','sync'=>'Synchronizacja (mapowanie)','automation'=>'Automatyzacja']),
            'carrier_accounts'=>$carriers,
            'carrier_accounts_auto'=>array_merge([['0','Dobierz jak podpowiedź w zamówieniu']],$carriers),
            'shipment_stages'=>$pairs(['pending'=>'Przygotowywana','created'=>'Utworzona','transit'=>'W drodze','delivery'=>'W doręczeniu','pickup'=>'Czeka w punkcie odbioru','delivered'=>'Doręczona','returned'=>'Zwrot','issue'=>'Problem z doręczeniem','cancelled'=>'Anulowana','unknown'=>'Nierozpoznany status']),
            'receipt_series'=>$seriesOptions('receipt','Domyślna seria paragonów'),
            'invoice_series'=>$seriesOptions('invoice','Domyślna seria faktur'),
            'fiscal_printers'=>$fiscal,
            'label_printers'=>$labels,
            'source_carriers'=>$sourceCarriers,
            'rules'=>$rules,
        ];
    }

    /* ---------- Rules: storage, validation, presentation ---------- */

    public function rule(int $id): ?array
    {
        $row=$this->db->fetch('SELECT * FROM om_rules WHERE id=:id',['id'=>$id]);
        return $row?$this->hydrate($row):null;
    }

    public function allRules(bool $enabledOnly=false): array
    {
        return array_map([$this,'hydrate'],$this->db->fetchAll('SELECT * FROM om_rules'.($enabledOnly?' WHERE enabled=1':'').' ORDER BY position,id'));
    }

    /** Converts the legacy {"field":value} format (import/status rules) into condition and action lists. */
    private function hydrate(array $row): array
    {
        $conditions=json_decode((string)$row['conditions_json'],true); $conditions=is_array($conditions)?$conditions:[];
        $actions=json_decode((string)$row['actions_json'],true); $actions=is_array($actions)?$actions:[];
        $triggers=json_decode((string)($row['triggers_json']??''),true);
        $options=json_decode((string)($row['options_json']??''),true);
        if ($conditions && array_keys($conditions)!==range(0,count($conditions)-1)) {
            $legacy=$conditions; $conditions=[];
            if (isset($legacy['account_id'])) { $conditions[]=['field'=>'account','op'=>'in','value'=>[(string)$legacy['account_id']]]; }
            if (isset($legacy['status_id'])) { $conditions[]=['field'=>'status','op'=>'in','value'=>[(string)$legacy['status_id']]]; }
            if (isset($legacy['paid'])) { $conditions[]=['field'=>'paid','op'=>'is','value'=>$legacy['paid']?'yes':'no']; }
        }
        if ($actions && array_keys($actions)!==range(0,count($actions)-1)) {
            $legacy=$actions; $actions=[];
            if (isset($legacy['status_id'])) { $actions[]=['type'=>'set_status','params'=>['status_id'=>(string)$legacy['status_id']]]; }
            if (!empty($legacy['tag'])) { $actions[]=['type'=>'add_tags','params'=>['tags'=>(string)$legacy['tag']]]; }
            if (!empty($legacy['note'])) { $actions[]=['type'=>'add_event','params'=>['text'=>(string)$legacy['note']]]; }
        }
        if (!is_array($triggers) || !$triggers) { $triggers=[(string)$row['trigger_name']]; }
        $legacyRule=!is_array($options);
        $options=is_array($options)?$options:[];
        $options+=['match'=>'all'];
        foreach ($conditions as $index=>&$condition) {
            if (is_array($condition)) { $condition['join']=$index>0 && (string)($condition['join']??($options['match']==='any'?'or':'and'))==='or'?'or':'and'; }
        }
        unset($condition);
        $options['match']=self::matchMode($conditions);
        $options+=['run_limit'=>$legacyRule && (string)$row['trigger_name']==='import'?'once':'every','button_order'=>false,'button_list'=>false,'stop_on_error'=>false,'delay'=>null];
        return ['id'=>(int)$row['id'],'name'=>(string)$row['name'],'enabled'=>(bool)(int)$row['enabled'],'position'=>(int)($row['position']??0),'triggers'=>array_values(array_map('strval',$triggers)),'conditions'=>array_values($conditions),'actions'=>array_values($actions),'options'=>$options];
    }

    public function saveRule(int $id,array $input): int
    {
        if ($id>0 && !$this->db->fetchColumn('SELECT id FROM om_rules WHERE id=:id',['id'=>$id])) { throw new InvalidArgumentException('Nie znaleziono automatyzacji.'); }
        $rule=$this->normalizeRule($input,$id);
        $data=['name'=>$rule['name'],'enabled'=>$rule['enabled']?1:0,'trigger_name'=>$rule['triggers'][0],'triggers_json'=>OrderRepository::json($rule['triggers']),'conditions_json'=>OrderRepository::json($rule['conditions']),'actions_json'=>OrderRepository::json($rule['actions']),'options_json'=>OrderRepository::json($rule['options']),'updated_at'=>gmdate('Y-m-d H:i:s')];
        if ($id>0) { $this->db->update('om_rules',$data,'id=:id',['id'=>$id]); return $id; }
        $data['position']=(int)$this->db->fetchColumn('SELECT COALESCE(MAX(position),0) FROM om_rules')+10;
        return (int)$this->db->insert('om_rules',$data);
    }

    public function duplicateRule(int $id): int
    {
        $rule=$this->rule($id);
        if (!$rule) { throw new InvalidArgumentException('Nie znaleziono automatyzacji.'); }
        $rule['name']=mb_substr($rule['name'],0,140,'UTF-8').' (kopia)';
        $rule['enabled']=false;
        return $this->saveRule(0,$rule+$rule['options']);
    }

    public function deleteRule(int $id): void
    {
        $this->db->transaction(function () use ($id) {
            if (!$this->db->fetchColumn('SELECT id FROM om_rules WHERE id=:id',['id'=>$id])) { throw new InvalidArgumentException('Nie znaleziono automatyzacji.'); }
            $this->db->delete('om_rule_runs','rule_id=:id',['id'=>$id]);
            $this->db->delete('om_rules','id=:id',['id'=>$id]);
        });
    }

    public function moveRule(int $id,string $direction): void
    {
        $this->db->transaction(function () use ($id,$direction) {
            $rows=$this->db->fetchAll('SELECT id FROM om_rules ORDER BY position,id');
            $ids=array_map('intval',array_column($rows,'id'));
            $index=array_search($id,$ids,true);
            if ($index===false) { throw new InvalidArgumentException('Nie znaleziono automatyzacji.'); }
            $target=$direction==='up'?$index-1:$index+1;
            if ($target<0 || $target>=count($ids)) { return; }
            [$ids[$index],$ids[$target]]=[$ids[$target],$ids[$index]];
            foreach ($ids as $position=>$ruleId) { $this->db->update('om_rules',['position'=>($position+1)*10],'id=:id',['id'=>$ruleId]); }
        });
    }

    public function normalizeRule(array $input,int $ruleId=0): array
    {
        $name=trim((string)($input['name']??''));
        if ($name==='' || mb_strlen($name,'UTF-8')>150) { throw new InvalidArgumentException('Podaj nazwę automatyzacji (maks. 150 znaków).'); }
        $triggerDefinitions=self::triggerDefinitions();
        $triggers=array_values(array_unique(array_map('strval',is_array($input['triggers']??null)?$input['triggers']:[])));
        if (!$triggers) { throw new InvalidArgumentException('Wybierz przynajmniej jeden wyzwalacz.'); }
        foreach ($triggers as $trigger) { if (!isset($triggerDefinitions[$trigger])) { throw new InvalidArgumentException('Nieznany wyzwalacz automatyzacji.'); } }
        $source=is_array($input['options']??null)?$input['options']+$input:$input;
        $options=['match'=>($source['match']??'all')==='any'?'any':'all','run_limit'=>($source['run_limit']??'every')==='once'?'once':'every','button_order'=>!empty($source['button_order']),'button_list'=>!empty($source['button_list']),'stop_on_error'=>!empty($source['stop_on_error']),'delay'=>null];
        if (in_array('scheduled',$triggers,true)) {
            $delay=is_array($source['delay']??null)?$source['delay']:[];
            $value=filter_var($delay['value']??null,FILTER_VALIDATE_INT); $unit=(string)($delay['unit']??''); $from=(string)($delay['from']??'');
            if ($value===false || $value<1 || !isset(self::DELAY_UNITS[$unit]) || !isset(self::DELAY_FROM[$from])) { throw new InvalidArgumentException('Ustaw czas i punkt odniesienia wyzwalacza „Upływ czasu”.'); }
            if ($value*self::DELAY_UNITS[$unit]>60*86400) { throw new InvalidArgumentException('Wyzwalacz „Upływ czasu” obsługuje maksymalnie 60 dni.'); }
            $options['delay']=['value'=>$value,'unit'=>$unit,'from'=>$from];
        }
        $optionSets=$this->optionSets();
        $values=static function ($set) use ($optionSets): array { return array_map(static function ($row) { return (string)$row[0]; },is_string($set)?$optionSets[$set]:$set); };
        $conditionDefinitions=self::conditionDefinitions();
        $conditions=[];
        $rawConditions=is_array($input['conditions']??null)?array_values($input['conditions']):[];
        if (count($rawConditions)>self::MAX_CONDITIONS) { throw new InvalidArgumentException('Automatyzacja może mieć maksymalnie '.self::MAX_CONDITIONS.' warunków.'); }
        foreach ($rawConditions as $condition) {
            if (!is_array($condition)) { continue; }
            $field=(string)($condition['field']??'');
            $definition=$conditionDefinitions[$field]??null;
            if (!$definition) { throw new InvalidArgumentException('Nieznany warunek automatyzacji.'); }
            $op=(string)($condition['op']??'');
            if (!isset(self::OPERATORS[$definition['type']][$op])) { throw new InvalidArgumentException('Nieprawidłowy operator warunku „'.$definition['label'].'”.'); }
            $value=$condition['value']??'';
            $error='Uzupełnij wartość warunku „'.$definition['label'].'”.';
            switch ($definition['type']) {
                case 'select':
                    $selected=array_values(array_unique(array_map('strval',is_array($value)?$value:[$value])));
                    $selected=array_values(array_filter($selected,static function (string $item): bool { return $item!==''; }));
                    if (!$selected || count($selected)>200) { throw new InvalidArgumentException($error); }
                    if ($field==='rule_executed' && $ruleId>0) { $selected=array_values(array_diff($selected,[(string)$ruleId])) ?: $selected; }
                    if (array_diff($selected,$values($definition['options']))) { throw new InvalidArgumentException('Warunek „'.$definition['label'].'” wskazuje nieistniejącą wartość.'); }
                    $value=$selected;
                    break;
                case 'bool':
                    if (!in_array($value,['yes','no'],true)) { throw new InvalidArgumentException($error); }
                    break;
                case 'number':
                    $parse=static function ($number) use ($error): string {
                        $number=str_replace([' ',','],['','.'],trim((string)$number));
                        if (!preg_match('/^-?\d{1,9}(?:\.\d{1,2})?$/D',$number)) { throw new InvalidArgumentException($error); }
                        return $number;
                    };
                    $value=$op==='between'?[$parse(is_array($value)?($value[0]??''):''),$parse(is_array($value)?($value[1]??''):'')]:$parse(is_array($value)?'':$value);
                    break;
                case 'text':
                case 'list':
                    $value=in_array($op,['empty','not_empty'],true)?'':trim((string)(is_array($value)?'':$value));
                    if (!in_array($op,['empty','not_empty'],true) && ($value==='' || mb_strlen($value,'UTF-8')>($definition['type']==='list'?1000:500))) { throw new InvalidArgumentException($error); }
                    if ($definition['type']==='list' && $value!=='' && !self::splitList($value)) { throw new InvalidArgumentException($error); }
                    break;
                case 'time':
                    $range=is_array($value)?array_values($value):[];
                    if (count($range)!==2 || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D',(string)$range[0]) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D',(string)$range[1])) { throw new InvalidArgumentException($error); }
                    $value=[(string)$range[0],(string)$range[1]];
                    break;
            }
            $conditions[]=['field'=>$field,'op'=>$op,'value'=>$value,'join'=>$conditions?((string)($condition['join']??($options['match']==='any'?'or':'and'))==='or'?'or':'and'):'and'];
        }
        $options['match']=self::matchMode($conditions);
        $actionDefinitions=self::actionDefinitions();
        $actions=[];
        $rawActions=is_array($input['actions']??null)?array_values($input['actions']):[];
        if (!$rawActions) { throw new InvalidArgumentException('Dodaj przynajmniej jeden efekt automatyzacji.'); }
        if (count($rawActions)>self::MAX_ACTIONS) { throw new InvalidArgumentException('Automatyzacja może mieć maksymalnie '.self::MAX_ACTIONS.' efektów.'); }
        foreach ($rawActions as $action) {
            if (!is_array($action)) { continue; }
            $type=(string)($action['type']??'');
            $definition=$actionDefinitions[$type]??null;
            if (!$definition) { throw new InvalidArgumentException('Nieznany efekt automatyzacji.'); }
            $rawParams=is_array($action['params']??null)?$action['params']:[];
            $params=[];
            foreach ($definition['params'] as $param) {
                $key=$param['key']; $raw=$rawParams[$key]??($param['default']??'');
                $label='„'.$definition['label'].'” → '.$param['label'];
                switch ($param['type']) {
                    case 'checkbox':
                        $params[$key]=!empty($raw) && $raw!=='0';
                        break;
                    case 'select':
                        $raw=(string)(is_array($raw)?'':$raw);
                        if ($raw==='' && empty($param['required'])) { $raw=(string)($param['default']??''); }
                        if ($raw==='' || !in_array($raw,$values($param['options']),true)) { throw new InvalidArgumentException('Wybierz wartość: '.$label.'.'); }
                        if ($key==='rule_id' && (int)$raw===$ruleId) { throw new InvalidArgumentException('Automatyzacja nie może uruchamiać samej siebie.'); }
                        $params[$key]=$raw;
                        break;
                    case 'number':
                        $raw=str_replace(',','.',trim((string)(is_array($raw)?'':$raw)));
                        if (!is_numeric($raw) || (float)$raw<(float)$param['min'] || (float)$raw>(float)$param['max']) { throw new InvalidArgumentException('Sprawdź wartość: '.$label.'.'); }
                        $params[$key]=$raw;
                        break;
                    default:
                        $raw=trim((string)(is_array($raw)?'':$raw));
                        if (mb_strlen($raw,'UTF-8')>(int)($param['max']??500)) { throw new InvalidArgumentException('Za długa wartość: '.$label.'.'); }
                        if ($raw==='' && !empty($param['required'])) { throw new InvalidArgumentException('Uzupełnij: '.$label.'.'); }
                        if ($raw!=='' && $param['type']==='email' && !filter_var($raw,FILTER_VALIDATE_EMAIL)) { throw new InvalidArgumentException('Nieprawidłowy adres e-mail: '.$label.'.'); }
                        if ($raw!=='' && $param['type']==='url' && (!filter_var($raw,FILTER_VALIDATE_URL) || stripos($raw,'https://')!==0)) { throw new InvalidArgumentException('Webhook wymaga pełnego adresu https://.'); }
                        $params[$key]=$raw;
                }
            }
            if ($type==='publish_tracking' && $params['carrier']==='other' && $params['carrier_other']==='') { throw new InvalidArgumentException('Podaj nazwę innego przewoźnika w efekcie „'.$definition['label'].'”.'); }
            if (in_array($type,['add_tags','remove_tags'],true) && !self::splitList($params['tags'])) { throw new InvalidArgumentException('Podaj tagi w efekcie „'.$definition['label'].'”.'); }
            $actions[]=['type'=>$type,'params'=>$params];
        }
        return ['name'=>$name,'enabled'=>!empty($input['enabled']),'triggers'=>$triggers,'conditions'=>$conditions,'actions'=>$actions,'options'=>$options];
    }

    public function rulesForDisplay(): array
    {
        $stats=[];
        foreach ($this->db->fetchAll("SELECT rule_id,COUNT(*) total,SUM(CASE WHEN result IN ('error','partial') THEN 1 ELSE 0 END) errors,MAX(created_at) last_run FROM om_rule_runs GROUP BY rule_id") as $row) { $stats[(int)$row['rule_id']]=$row; }
        $rules=[];
        foreach ($this->allRules() as $index=>$rule) {
            $rule=$this->describe($rule);
            $rule['number']=$index+1;
            $rule['runs_total']=(int)($stats[$rule['id']]['total']??0);
            $rule['runs_errors']=(int)($stats[$rule['id']]['errors']??0);
            $rule['last_run_at']=(string)($stats[$rule['id']]['last_run']??'');
            $rules[]=$rule;
        }
        return $rules;
    }

    public function describe(array $rule): array
    {
        $triggerDefinitions=self::triggerDefinitions();
        $rule['trigger_items']=array_map(static function (string $key) use ($triggerDefinitions): array { return ['key'=>$key,'label'=>$triggerDefinitions[$key]['label']??$key,'icon'=>$triggerDefinitions[$key]['icon']??'bi-lightning']; },$rule['triggers']);
        $rule['condition_items']=array_map([$this,'describeCondition'],$rule['conditions']);
        $rule['action_items']=array_map([$this,'describeAction'],$rule['actions']);
        $delay=$rule['options']['delay']??null;
        $units=['minutes'=>'min','hours'=>'godz.','days'=>'dni'];
        $rule['delay_label']=is_array($delay)?'Po '.$delay['value'].' '.($units[$delay['unit']]??'').' od '.(self::DELAY_FROM[$delay['from']]??''):'';
        $rule['has_costly_action']=(bool)array_intersect(array_column($rule['actions'],'type'),['create_shipment','email_customer','email_address','webhook','issue_receipt','issue_invoice','issue_preferred','publish_tracking','accept_order']);
        return $rule;
    }

    private function optionLabel($set,string $value): string
    {
        $rows=is_string($set)?($this->optionSets()[$set]??[]):$set;
        foreach ($rows as $row) { if ((string)$row[0]===$value) { return (string)$row[1]; } }
        return $value==='0'?'domyślnie':'usunięte (#'.$value.')';
    }

    public function describeCondition(array $condition): string
    {
        $definition=self::conditionDefinitions()[$condition['field']]??null;
        if (!$definition) { return 'Nieznany warunek'; }
        $label=$definition['label']; $op=$condition['op']; $value=$condition['value'];
        switch ($definition['type']) {
            case 'select':
                $names=array_map(function ($item) use ($definition) { return $this->optionLabel($definition['options'],(string)$item); },(array)$value);
                return $label.($op==='not_in'?' nie jest: ':': ').implode(', ',$names);
            case 'bool':
                return $label.': '.($value==='yes'?'tak':'nie');
            case 'number':
                $unit=($definition['unit']??'')==='h'?' h':'';
                if ($op==='between') { return $label.' od '.$value[0].' do '.$value[1].$unit; }
                return $label.' '.['gte'=>'≥','lte'=>'≤','gt'=>'>','lt'=>'<','eq'=>'=','neq'=>'≠'][$op].' '.$value.$unit;
            case 'time':
                return $label.': '.$value[0].'–'.$value[1];
            default:
                if ($op==='empty') { return $label.' jest puste'; }
                if ($op==='not_empty') { return $label.' nie jest puste'; }
                return $label.' '.self::OPERATORS[$definition['type']][$op].' „'.$value.'”';
        }
    }

    public function describeAction(array $action): array
    {
        $definition=self::actionDefinitions()[$action['type']]??null;
        if (!$definition) { return ['icon'=>'bi-question-circle','label'=>'Nieznany efekt','detail'=>'','warning'=>false]; }
        $params=$action['params']??[];
        $detail=[];
        foreach ($definition['params'] as $param) {
            $value=$params[$param['key']]??null;
            if ($param['type']==='checkbox') { if ($value) { $detail[]=$param['label']; } continue; }
            if ($value===null || $value==='') { continue; }
            if ($param['type']==='select') {
                if ((string)$value==='0' || ((string)$value==='auto' && $param['key']!=='package')) { continue; }
                $detail[]=$this->optionLabel($param['options'],(string)$value);
            } elseif ($param['type']==='textarea') {
                $detail[]=mb_strimwidth(preg_replace('/\s+/u',' ',(string)$value)??'',0,80,'…','UTF-8');
            } elseif ($param['type']==='url') {
                $detail[]=(string)parse_url((string)$value,PHP_URL_HOST);
            } else {
                $detail[]=(string)$value.($param['type']==='number'?' mm':'');
            }
        }
        return ['icon'=>$definition['icon'],'label'=>$definition['label'],'detail'=>implode(' · ',$detail),'warning'=>!empty($definition['warning'])];
    }

    public function manualRules(): array
    {
        $result=['order'=>[],'list'=>[]];
        foreach ($this->allRules(true) as $rule) {
            $item=['id'=>$rule['id'],'name'=>$rule['name'],'confirm'=>$this->describe($rule)['has_costly_action']];
            if ($rule['options']['button_order']) { $result['order'][]=$item; }
            if ($rule['options']['button_list']) { $result['list'][]=$item; }
        }
        return $result;
    }

    public function stats(): array
    {
        $since=gmdate('Y-m-d H:i:s',time()-86400);
        return [
            'active'=>(int)$this->db->fetchColumn('SELECT COUNT(*) FROM om_rules WHERE enabled=1'),
            'paused'=>(int)$this->db->fetchColumn('SELECT COUNT(*) FROM om_rules WHERE enabled=0'),
            'runs_24h'=>(int)$this->db->fetchColumn('SELECT COUNT(*) FROM om_rule_runs WHERE created_at>=:since',['since'=>$since]),
            'errors_24h'=>(int)$this->db->fetchColumn("SELECT COUNT(*) FROM om_rule_runs WHERE created_at>=:since AND result IN ('error','partial')",['since'=>$since]),
        ];
    }

    public function log(int $limit=40,int $orderId=0): array
    {
        $limit=max(1,min(200,$limit));
        $rows=$this->db->fetchAll('SELECT r.id,r.rule_id,r.order_id,r.event_key,r.trigger_name,r.result,r.message,r.created_at,ru.name rule_name FROM om_rule_runs r LEFT JOIN om_rules ru ON ru.id=r.rule_id'.($orderId>0?' WHERE r.order_id=:order':'').' ORDER BY r.id DESC LIMIT '.$limit,$orderId>0?['order'=>$orderId]:[]);
        $labels=['ok'=>'Wykonano','partial'=>'Częściowo','error'=>'Błąd','running'=>'W toku'];
        foreach ($rows as &$row) {
            $row['result']=(string)($row['result']??'ok') ?: 'ok';
            $row['result_label']=$labels[$row['result']]??$row['result'];
            $row['trigger_label']=$row['trigger_name']?self::triggerLabel((string)$row['trigger_name']):'Import (starsza reguła)';
            $row['rule_name']=(string)($row['rule_name']??'Usunięta automatyzacja');
        }
        unset($row);
        return $rows;
    }

    /** Dry run for the order view: which enabled rules would match right now, condition by condition. */
    public function explain(int $orderId): array
    {
        $rules=$this->allRules(true);
        if (!$rules) { return []; }
        $ctx=$this->context($orderId,'preview',[]);
        $lastRuns=[];
        foreach ($this->db->fetchAll('SELECT rule_id,MAX(created_at) last_run FROM om_rule_runs WHERE order_id=:order GROUP BY rule_id',['order'=>$orderId]) as $row) { $lastRuns[(int)$row['rule_id']]=$row['last_run']; }
        $result=[];
        foreach ($rules as $rule) {
            $items=[];
            foreach ($rule['conditions'] as $condition) {
                $state=$this->test($condition,$ctx);
                $items[]=['label'=>$this->describeCondition($condition),'join'=>$condition['join']??'and','state'=>$state===null?'event':($state?'pass':'fail')];
            }
            $matches=self::combine(array_map(static function (array $condition,array $item): array { return [$condition['join']??'and',['pass'=>true,'fail'=>false,'event'=>null][$item['state']]]; },$rule['conditions'],$items));
            $described=$this->describe($rule);
            $result[]=['id'=>$rule['id'],'name'=>$rule['name'],'match'=>$matches,'conditions'=>$items,'triggers'=>$described['trigger_items'],'actions'=>$described['action_items'],'last_run_at'=>(string)($lastRuns[$rule['id']]??''),'button_order'=>$rule['options']['button_order'],'confirm'=>$described['has_costly_action']];
        }
        return $result;
    }

    public function matchingRuleNames(int $orderId,string $trigger): array
    {
        $ctx=$this->context($orderId,$trigger,[]); $names=[];
        foreach ($this->rulesForTrigger($trigger) as $rule) { if ($this->matches($rule,$ctx,true)) { $names[]=$rule['name']; } }
        return $names;
    }

    /* ---------- Execution ---------- */

    public function dispatch(int $orderId,string $trigger,array $event=[]): void
    {
        $this->enqueue(['order_id'=>$orderId,'trigger'=>$trigger,'event'=>$event]);
    }

    /** Runs one rule on selected orders from a button; conditions are checked, run limits are not. */
    public function runManual(array $orderIds,int $ruleId,string $actor): array
    {
        $rule=$this->rule($ruleId);
        if (!$rule || !$rule['enabled']) { throw new InvalidArgumentException('Automatyzacja nie istnieje albo jest wstrzymana.'); }
        $this->report=['executed'=>0,'skipped'=>0,'errors'=>0,'messages'=>[]];
        foreach (array_values(array_unique(array_map('intval',$orderIds))) as $orderId) {
            if ($orderId<1) { continue; }
            $this->enqueue(['order_id'=>$orderId,'trigger'=>'manual','event'=>['actor'=>$actor],'rule_ids'=>[$ruleId],'manual'=>true]);
        }
        $report=$this->report; $this->report=null;
        $parts=['wykonano: '.$report['executed']];
        if ($report['skipped']) { $parts[]='pominięto (warunki niespełnione): '.$report['skipped']; }
        if ($report['errors']) { $parts[]='z błędami: '.$report['errors']; }
        $report['message']='Automatyzacja „'.$rule['name'].'” — '.implode(', ',$parts).'.'.($report['messages']?' '.implode(' ',array_slice($report['messages'],0,3)):'');
        return $report;
    }

    /** Cron entry point for the „Upływ czasu” trigger. Each order runs once per reference timestamp. */
    public function runScheduled(int $limit=1000): array
    {
        $summary=['rules'=>0,'checked'=>0];
        foreach ($this->rulesForTrigger('scheduled') as $rule) {
            $delay=$rule['options']['delay']??null;
            if (!is_array($delay) || !isset(self::DELAY_UNITS[$delay['unit']??''])) { continue; }
            $summary['rules']++;
            $seconds=(int)$delay['value']*self::DELAY_UNITS[$delay['unit']];
            $column=['status'=>'COALESCE(o.status_changed_at,o.imported_at)','ordered'=>'o.ordered_at','imported'=>'o.imported_at'][$delay['from']]??'o.ordered_at';
            $where=["$column<=:due","$column>=:oldest"];
            $params=['due'=>gmdate('Y-m-d H:i:s',time()-$seconds),'oldest'=>gmdate('Y-m-d H:i:s',time()-$seconds-30*86400)];
            if ($rule['options']['match']==='all') {
                foreach ($rule['conditions'] as $index=>$condition) {
                    if ($condition['field']!=='status' || $condition['op']!=='in') { continue; }
                    $marks=[];
                    foreach ($condition['value'] as $valueIndex=>$statusId) { $marks[]=':s'.$index.'_'.$valueIndex; $params['s'.$index.'_'.$valueIndex]=(int)$statusId; }
                    $where[]='o.status_id IN ('.implode(',',$marks).')';
                }
            }
            $rows=$this->db->fetchAll("SELECT o.id,$column base FROM om_orders o WHERE ".implode(' AND ',$where)." ORDER BY $column DESC LIMIT 500",$params);
            if (!$rows) { continue; }
            $done=[];
            foreach (array_chunk(array_map('intval',array_column($rows,'id')),200) as $chunk) {
                $marks=[]; $chunkParams=['rule'=>$rule['id']];
                foreach ($chunk as $index=>$id) { $marks[]=':o'.$index; $chunkParams['o'.$index]=$id; }
                foreach ($this->db->fetchAll("SELECT order_id,event_key FROM om_rule_runs WHERE rule_id=:rule AND event_key LIKE 'time:%' AND order_id IN (".implode(',',$marks).')',$chunkParams) as $run) { $done[$run['order_id'].'|'.$run['event_key']]=true; }
            }
            foreach ($rows as $row) {
                $key='time:'.$row['base'];
                if (isset($done[$row['id'].'|'.$key])) { continue; }
                if ($summary['checked']>=$limit) { break 2; }
                $summary['checked']++;
                $this->enqueue(['order_id'=>(int)$row['id'],'trigger'=>'scheduled','event'=>['event_key'=>$key],'rule_ids'=>[$rule['id']]]);
            }
        }
        return $summary;
    }

    private function enqueue(array $item): void
    {
        $item['depth']=$this->running?$this->depth+1:0;
        $this->queue[]=$item;
        if ($this->running) { return; }
        $this->running=true; $this->chain=[];
        try {
            while ($this->queue) {
                $next=array_shift($this->queue);
                if ($next['depth']>self::MAX_DEPTH) {
                    if ($this->rulesForTrigger($next['trigger'])) { $this->safeEvent((int)$next['order_id'],'Przerwano łańcuch automatyzacji — zbyt wiele zdarzeń wywołanych po sobie („'.self::triggerLabel($next['trigger']).'”).','automat'); }
                    continue;
                }
                $this->depth=$next['depth'];
                try { $this->processEvent($next); }
                catch (\Throwable $error) { OrderSyncError::log($error,['stage'=>'automation','order_id'=>$next['order_id'],'trigger'=>$next['trigger']]); }
            }
        } finally {
            $this->running=false; $this->depth=0; $this->chain=[]; $this->queue=[];
        }
    }

    private function rulesForTrigger(string $trigger): array
    {
        return array_values(array_filter($this->allRules(true),static function (array $rule) use ($trigger): bool { return in_array($trigger,$rule['triggers'],true); }));
    }

    private function processEvent(array $item): void
    {
        if (isset($item['rule_ids'])) {
            $rules=[];
            foreach ($item['rule_ids'] as $ruleId) { $rule=$this->rule((int)$ruleId); if ($rule && $rule['enabled']) { $rules[]=$rule; } }
        } else {
            $rules=$this->rulesForTrigger($item['trigger']);
        }
        if (!$rules) { return; }
        if ($item['trigger']==='document_issued' && !empty($item['event']['document_id']) && !$this->db->fetchColumn('SELECT id FROM om_documents WHERE id=:id',['id'=>(int)$item['event']['document_id']])) { return; }
        $ctx=null;
        foreach ($rules as $rule) {
            $chainKey=$rule['id'].':'.$item['order_id'];
            if (isset($this->chain[$chainKey])) { continue; }
            if ($ctx===null || $ctx['dirty']) {
                try { $ctx=$this->context((int)$item['order_id'],$item['trigger'],$item['event']); }
                catch (InvalidArgumentException $missing) { return; }
            }
            $manual=!empty($item['manual']);
            $eventKey=$manual?'manual:'.bin2hex(random_bytes(8)):(string)($item['event']['event_key']??'');
            if (!$manual && $rule['options']['run_limit']==='once') {
                if ($this->db->fetchColumn("SELECT id FROM om_rule_runs WHERE rule_id=:rule AND order_id=:order AND event_key NOT LIKE 'manual:%' LIMIT 1",['rule'=>$rule['id'],'order'=>$item['order_id']])) { continue; }
                if ($eventKey==='') { $eventKey='once'; }
            }
            if ($eventKey==='') { $eventKey=bin2hex(random_bytes(16)); }
            if (!$this->matches($rule,$ctx)) {
                if ($manual && $this->report!==null && $item['depth']===0) { $this->report['skipped']++; }
                continue;
            }
            $this->chain[$chainKey]=true;
            $outcome=$this->execute($rule,$ctx,$item,$eventKey);
            if ($manual && $this->report!==null && $item['depth']===0) {
                $this->report[$outcome['state']==='ok'?'executed':'errors']++;
                if ($outcome['state']!=='ok') { $this->report['messages'][]='#'.$item['order_id'].': '.$outcome['message']; }
            }
            $ctx['dirty']=true;
            if ($outcome['stop']) { break; }
        }
    }

    private function execute(array $rule,array &$ctx,array $item,string $eventKey): array
    {
        $orderId=(int)$ctx['order']['id'];
        try {
            $runId=(int)$this->db->insert('om_rule_runs',['rule_id'=>$rule['id'],'order_id'=>$orderId,'event_key'=>$eventKey,'trigger_name'=>$item['trigger'],'result'=>'running','message'=>'','created_at'=>gmdate('Y-m-d H:i:s')]);
        } catch (\PDOException $duplicate) {
            return ['stop'=>false,'state'=>'ok','message'=>''];
        }
        $actor='automat: '.$rule['name'];
        $steps=[]; $errors=0; $done=0; $stop=false;
        foreach ($rule['actions'] as $index=>$action) {
            $label=self::actionDefinitions()[$action['type']]['label']??$action['type'];
            try {
                if ($index>0) { $ctx=$this->context($orderId,$ctx['trigger'],$ctx['event']); }
                $outcome=$this->perform($action,$ctx,$rule,$item,$eventKey,$actor);
                $done++;
                $steps[]=($outcome['state']==='skipped'?'↷ ':'✓ ').$outcome['message'];
                if ($outcome['state']==='stop') { $stop=true; break; }
            } catch (\Throwable $error) {
                $errors++;
                $steps[]='✗ '.$label.': '.$this->errorMessage($error,$action['type'],$orderId);
                if ($rule['options']['stop_on_error']) { $steps[]='⏹ zatrzymano pozostałe kroki'; break; }
            }
        }
        $state=$errors?($done?'partial':'error'):'ok';
        $message=mb_substr(implode(' · ',$steps),0,2000,'UTF-8');
        try { $this->db->update('om_rule_runs',['result'=>$state,'message'=>$message],'id=:id',['id'=>$runId]); }
        catch (\Throwable $error) { OrderSyncError::log($error,['stage'=>'automation','rule_id'=>$rule['id']]); }
        $this->safeEvent($orderId,'Automatyzacja „'.$rule['name'].'” ('.self::triggerLabel($item['trigger']).'): '.$message,'automat');
        return ['stop'=>$stop,'state'=>$state,'message'=>$message];
    }

    private function errorMessage(\Throwable $error,string $type,int $orderId): string
    {
        if ($error instanceof InvalidArgumentException) { return $error->getMessage(); }
        $diagnostic=OrderSyncError::log($error,['stage'=>$type==='create_shipment'?'shipment':'automation','order_id'=>$orderId,'action'=>$type]);
        if ($error instanceof \PDOException) { return $diagnostic['message'].' [ID: '.$diagnostic['reference'].']'; }
        $reason=mb_substr(trim((string)preg_replace('/[\x00-\x1F\x7F]+/u',' ',$error->getMessage())),0,240,'UTF-8');
        return ($reason!==''?$reason:$diagnostic['message']).' [ID: '.$diagnostic['reference'].']';
    }

    private function safeEvent(int $orderId,string $message,string $actor): void
    {
        try {
            if ($this->db->fetchColumn('SELECT id FROM om_orders WHERE id=:id',['id'=>$orderId])) { $this->repo->event($orderId,mb_substr($message,0,4000,'UTF-8'),$actor); }
        } catch (\Throwable $error) { OrderSyncError::log($error,['stage'=>'automation','order_id'=>$orderId]); }
    }

    /* ---------- Conditions ---------- */

    private function context(int $orderId,string $trigger,array $event): array
    {
        return ['order'=>$this->repo->order($orderId),'trigger'=>$trigger,'event'=>$event,'cache'=>[],'dirty'=>false];
    }

    private function matches(array $rule,array &$ctx,bool $ignoreEventOnly=false): bool
    {
        if (!$rule['conditions']) { return true; }
        // ORAZ binds tighter than LUB: the list is an OR of AND-groups, each group starting at a „lub” join.
        $applicable=false; $groupOk=true; $groupApplicable=false;
        foreach ($rule['conditions'] as $index=>$condition) {
            if ($index>0 && ($condition['join']??'and')==='or') {
                if ($groupApplicable && $groupOk) { return true; }
                $groupOk=true; $groupApplicable=false;
            }
            if (!$groupOk) { continue; }
            $state=$this->test($condition,$ctx);
            if ($state===null) {
                if ($ignoreEventOnly) { continue; }
                $state=false;
            }
            $applicable=$groupApplicable=true;
            if (!$state) { $groupOk=false; }
        }
        return ($groupApplicable && $groupOk) || !$applicable;
    }

    /** @param array<int,array{0:string,1:?bool}> $states join + result (null = not applicable) */
    private static function combine(array $states): bool
    {
        $applicable=false; $groups=[]; $group=null;
        foreach ($states as $index=>[$join,$state]) {
            if ($index===0 || $join==='or') { $groups[]=$group; $group=null; }
            if ($state===null) { continue; }
            $applicable=true; $group=($group??true) && $state;
        }
        $groups[]=$group;
        return !$applicable || in_array(true,$groups,true);
    }

    /** all = only „oraz”, any = only „lub”, mixed otherwise. */
    private static function matchMode(array $conditions): string
    {
        $joins=array_unique(array_map(static function ($condition): string { return (string)($condition['join']??'and'); },array_slice($conditions,1)));
        if (count($joins)>1) { return 'mixed'; }
        return $joins===['or']?'any':'all';
    }

    /** @return bool|null null when the condition only makes sense for another event */
    private function test(array $condition,array &$ctx): ?bool
    {
        $definition=self::conditionDefinitions()[$condition['field']]??null;
        if (!$definition) { return false; }
        if (isset($definition['events']) && !in_array($ctx['trigger'],$definition['events'],true)) { return null; }
        $value=$this->fieldValue($condition['field'],$ctx);
        if ($value===null) { return false; }
        $op=$condition['op']; $expected=$condition['value'];
        switch ($definition['type']) {
            case 'select':
                $hit=(bool)array_intersect(array_map('strval',(array)$value),array_map('strval',(array)$expected));
                return $op==='not_in'?!$hit:$hit;
            case 'bool':
                return (bool)$value===($expected==='yes');
            case 'number':
                $number=(float)$value;
                switch ($op) {
                    case 'gt': return $number>(float)$expected;
                    case 'gte': return $number>=(float)$expected-0.00001;
                    case 'lt': return $number<(float)$expected;
                    case 'lte': return $number<=(float)$expected+0.00001;
                    case 'eq': return abs($number-(float)$expected)<0.005;
                    case 'neq': return abs($number-(float)$expected)>=0.005;
                    case 'between': return $number>=min((float)$expected[0],(float)$expected[1])-0.00001 && $number<=max((float)$expected[0],(float)$expected[1])+0.00001;
                }
                return false;
            case 'time':
                $now=(new \DateTimeImmutable('now',new \DateTimeZone('Europe/Warsaw')))->format('H:i');
                return $expected[0]<=$expected[1]?($now>=$expected[0] && $now<=$expected[1]):($now>=$expected[0] || $now<=$expected[1]);
            case 'list':
                $items=array_map(static function ($item) { return mb_strtolower((string)$item,'UTF-8'); },(array)$value);
                if ($op==='empty') { return !$items; }
                if ($op==='not_empty') { return (bool)$items; }
                $hits=0; $needles=self::splitList((string)$expected);
                foreach ($needles as $needle) {
                    $pattern='/^'.str_replace('\*','.*',preg_quote(mb_strtolower($needle,'UTF-8'),'/')).'$/u';
                    foreach ($items as $item) { if (preg_match($pattern,$item)) { $hits++; break; } }
                }
                if ($op==='all') { return $hits===count($needles); }
                return $op==='none'?$hits===0:$hits>0;
            default:
                $texts=array_map(static function ($item) { return mb_strtolower(trim((string)$item),'UTF-8'); },is_array($value)?$value:[$value]);
                $needle=mb_strtolower(trim((string)$expected),'UTF-8');
                $check=static function (string $text) use ($op,$needle): bool {
                    switch ($op) {
                        case 'contains': return $needle!=='' && mb_strpos($text,$needle,0,'UTF-8')!==false;
                        case 'equals': return $text===$needle;
                        case 'starts_with': return $needle!=='' && mb_strpos($text,$needle,0,'UTF-8')===0;
                        case 'not_empty': return $text!=='';
                    }
                    return false;
                };
                if ($op==='not_contains') { foreach ($texts as $text) { if ($needle!=='' && mb_strpos($text,$needle,0,'UTF-8')!==false) { return false; } } return true; }
                if ($op==='not_equals') { return !in_array($needle,$texts,true); }
                if ($op==='empty') { return !array_filter($texts,static function (string $text): bool { return $text!==''; }); }
                foreach ($texts as $text) { if ($check($text)) { return true; } }
                return false;
        }
    }

    private function fieldValue(string $field,array &$ctx)
    {
        $order=$ctx['order']; $details=$order['details']; $event=$ctx['event'];
        $items=is_array($details['items']??null)?$details['items']:[];
        switch ($field) {
            case 'account': return (string)$order['account_id'];
            case 'platform': return (string)$order['platform'];
            case 'status': return (string)$order['status_id'];
            case 'remote_status': return (string)$order['remote_status'];
            case 'total': return (int)$order['total_cents']/100;
            case 'currency': return (string)$order['currency'];
            case 'tags': return self::splitList((string)$order['tags']);
            case 'note': return (string)($order['note']??'');
            case 'order_age': return max(0,time()-(int)strtotime($order['ordered_at'].' UTC'))/3600;
            case 'status_age': return max(0,time()-(int)strtotime((string)($order['status_changed_at']??'' ?: $order['imported_at']).' UTC'))/3600;
            case 'rule_executed': return array_map('strval',$this->cached($ctx,'rule_runs',function () use ($order) { return array_column($this->db->fetchAll("SELECT DISTINCT rule_id FROM om_rule_runs WHERE order_id=:order AND result IN ('ok','partial')",['order'=>$order['id']]),'rule_id'); }));
            case 'buyer_name': return (string)$order['buyer_name'];
            case 'email': return (string)$order['email'];
            case 'phone': return (string)$order['phone'];
            case 'buyer_note': return (string)($details['buyer_note']??'');
            case 'document_preference': return (string)$details['document_preference'];
            case 'has_tax_id': return trim((string)($details['invoice_form']['nip']??''))!=='';
            case 'customer_orders':
                return $this->cached($ctx,'customer_orders',function () use ($order) { return trim((string)$order['email'])===''?1:(int)$this->db->fetchColumn('SELECT COUNT(*) FROM om_orders WHERE email=:email',['email'=>$order['email']]); });
            case 'paid': return (bool)(int)$order['paid'];
            case 'payment_state': return self::paymentState($order);
            case 'payment_method': case 'payment_method_text': return (string)$details['payment_method'];
            case 'cod': return (bool)$details['cash_on_delivery'];
            case 'amount_due': return (int)$details['amount_due_cents']/100;
            case 'delivery_method': return (string)($details['delivery']??'');
            case 'pickup_point': return trim((string)($details['pickup']??''))!=='' || (bool)preg_match('/paczkomat|punkt|locker|pickup|automat paczkowy/iu',(string)($details['delivery']??''));
            case 'country': return (string)$order['shipping_address']['country'];
            case 'postal_code': return (string)$order['shipping_address']['postal_code'];
            case 'city': return (string)$order['shipping_address']['city'];
            case 'shipping_cost': return (int)($details['shipping_cents']??0)/100;
            case 'sku': return array_values(array_filter(array_map(static function ($item) { return trim((string)($item['sku']??'')); },$items),'strlen'));
            case 'product_name': return array_map(static function ($item) { return (string)($item['name']??''); },$items);
            case 'item_lines': return count($items);
            case 'item_quantity': return array_sum(array_map(static function ($item) { return max(0,(int)($item['quantity']??0)); },$items));
            case 'has_receipt': return in_array('receipt',array_column($this->documents($ctx),'kind'),true);
            case 'has_invoice': return in_array('invoice',array_column($this->documents($ctx),'kind'),true);
            case 'has_correction': return (bool)array_intersect(['receipt_correction','invoice_correction'],array_column($this->documents($ctx),'kind'));
            case 'fiscal_printed':
                return $this->cached($ctx,'fiscal_printed',function () use ($order) { try { return (bool)$this->db->fetchColumn("SELECT id FROM print_fiscal_jobs WHERE order_id=:order AND status='printed' LIMIT 1",['order'=>$order['id']]); } catch (\Throwable $error) { return false; } });
            case 'has_shipment': return (bool)$this->activeShipments($ctx);
            case 'shipment_count': return count($this->activeShipments($ctx));
            case 'carrier_account': return array_map(static function ($shipment) { return (string)$shipment['carrier_account_id']; },$this->activeShipments($ctx));
            case 'shipment_stage':
                $shipment=null;
                foreach ($this->shipments($ctx) as $candidate) {
                    if (!empty($event['shipment_id']) ? (int)$candidate['id']===(int)$event['shipment_id'] : true) { $shipment=$candidate; break; }
                }
                return $shipment?(string)OrderShipmentService::presentation($shipment,$order)['status_tone']:null;
            case 'has_tracking':
                foreach ($this->activeShipments($ctx) as $shipment) { if (trim((string)$shipment['tracking'])!=='' && strpos((string)$shipment['tracking'],'PENDING:')!==0) { return true; } }
                return false;
            case 'tracking_sent':
                foreach ($this->shipments($ctx) as $shipment) {
                    $payload=json_decode((string)($shipment['payload_json']??''),true);
                    if (($payload['meta']['source_publication']['state']??'')==='received') { return true; }
                }
                return false;
            case 'label_printed':
                return $this->cached($ctx,'label_printed',function () use ($order) { try { return (bool)$this->db->fetchColumn("SELECT j.id FROM print_agent_jobs j JOIN om_shipments s ON s.id=j.shipment_id WHERE s.order_id=:order AND j.status='printed' LIMIT 1",['order'=>$order['id']]); } catch (\Throwable $error) { return false; } });
            case 'previous_status': return isset($event['previous_status_id'])?(string)$event['previous_status_id']:null;
            case 'status_source': return isset($event['status_source'])?(string)$event['status_source']:null;
            case 'document_kind': return isset($event['document_kind'])?(string)$event['document_kind']:null;
            case 'weekday': return (new \DateTimeImmutable('now',new \DateTimeZone('Europe/Warsaw')))->format('N');
            case 'hour': return true;
        }
        return null;
    }

    private static function paymentState(array $order): string
    {
        $details=$order['details']; $total=(int)$order['total_cents']; $paid=(int)$details['amount_paid_cents'];
        if (!empty($details['cash_on_delivery'])) { return 'cod'; }
        if ($total>0 && $paid>$total) { return 'overpaid'; }
        if ((int)$order['paid'] || ($total>0 && $paid>=$total)) { return 'paid'; }
        return $paid>0?'partial':'unpaid';
    }

    private function cached(array &$ctx,string $key,callable $loader)
    {
        if (!array_key_exists($key,$ctx['cache'])) { $ctx['cache'][$key]=$loader(); }
        return $ctx['cache'][$key];
    }

    private function documents(array &$ctx): array
    {
        $orderId=(int)$ctx['order']['id'];
        return $this->cached($ctx,'documents',function () use ($orderId) { return $this->db->fetchAll('SELECT id,kind,number,series_id FROM om_documents WHERE order_id=:order ORDER BY id DESC',['order'=>$orderId]); });
    }

    private function shipments(array &$ctx): array
    {
        $orderId=(int)$ctx['order']['id'];
        return $this->cached($ctx,'shipments',function () use ($orderId) { return $this->db->fetchAll('SELECT s.*,ca.provider carrier_provider FROM om_shipments s LEFT JOIN om_carrier_accounts ca ON ca.id=s.carrier_account_id WHERE s.order_id=:order ORDER BY s.id DESC',['order'=>$orderId]); });
    }

    private function activeShipments(array &$ctx): array
    {
        return array_values(array_filter($this->shipments($ctx),static function (array $shipment): bool { return !in_array(strtoupper((string)$shipment['state']),self::CANCELLED,true); }));
    }

    public static function splitList(string $value): array
    {
        $items=[];
        foreach (preg_split('/[,;\n]+/u',$value)?:[] as $item) {
            $item=trim($item);
            if ($item!=='' && !in_array(mb_strtolower($item,'UTF-8'),array_map(static function ($existing) { return mb_strtolower($existing,'UTF-8'); },$items),true)) { $items[]=$item; }
        }
        return $items;
    }

    /* ---------- Actions ---------- */

    private function perform(array $action,array &$ctx,array $rule,array $item,string $eventKey,string $actor): array
    {
        $order=$ctx['order']; $orderId=(int)$order['id']; $params=$action['params']??[];
        $ok=static function (string $message): array { return ['state'=>'ok','message'=>$message]; };
        $skip=static function (string $message): array { return ['state'=>'skipped','message'=>$message]; };
        switch ($action['type']) {
            case 'set_status':
                $statusId=(int)$params['status_id']; $this->repo->requireStatus($statusId);
                $name=$this->optionLabel('statuses',(string)$statusId);
                if ((int)$order['status_id']===$statusId) { return $skip('Status „'.$name.'” był już ustawiony'); }
                $this->repo->changeStatus($orderId,$statusId,$actor);
                return $ok('Zmieniono status na „'.$name.'”');
            case 'add_tags':
            case 'remove_tags':
                $current=self::splitList((string)$order['tags']);
                $lower=array_map(static function ($tag) { return mb_strtolower($tag,'UTF-8'); },$current);
                $changes=self::splitList((string)$params['tags']);
                if ($action['type']==='add_tags') {
                    $next=$current;
                    foreach ($changes as $tag) { if (!in_array(mb_strtolower($tag,'UTF-8'),$lower,true)) { $next[]=$tag; } }
                } else {
                    $remove=array_map(static function ($tag) { return mb_strtolower($tag,'UTF-8'); },$changes);
                    $next=array_values(array_filter($current,static function ($tag) use ($remove) { return !in_array(mb_strtolower($tag,'UTF-8'),$remove,true); }));
                }
                if ($next===$current) { return $skip($action['type']==='add_tags'?'Tagi były już dodane':'Brak tagów do usunięcia'); }
                $tags=mb_substr(implode(', ',$next),0,1000,'UTF-8');
                $this->db->update('om_orders',['tags'=>$tags,'updated_at'=>gmdate('Y-m-d H:i:s')],'id=:id',['id'=>$orderId]);
                return $ok(($action['type']==='add_tags'?'Dodano tagi: ':'Usunięto tagi: ').implode(', ',$changes));
            case 'append_note':
                $text=trim($this->render((string)$params['text'],$ctx));
                $note=trim((string)($order['note']??''));
                $this->db->update('om_orders',['note'=>mb_substr(($note!==''?$note."\n":'').$text,0,10000,'UTF-8'),'updated_at'=>gmdate('Y-m-d H:i:s')],'id=:id',['id'=>$orderId]);
                return $ok('Dopisano notatkę');
            case 'add_event':
                $this->repo->event($orderId,mb_substr($this->render((string)$params['text'],$ctx),0,1000,'UTF-8'),$actor);
                return $ok('Dodano wpis do historii');
            case 'set_paid':
                $paid=$params['state']==='paid';
                $changed=$this->repo->patchOrder($orderId,['paid'=>$paid?1:0],['amount_paid_cents'=>$paid?(int)$order['total_cents']:0],$actor,$paid?'Automatyzacja oznaczyła zamówienie jako opłacone.':'Automatyzacja oznaczyła zamówienie jako nieopłacone.');
                return $changed?$ok($paid?'Oznaczono jako opłacone':'Oznaczono jako nieopłacone'):$skip('Płatność miała już ten stan');
            case 'set_document_preference':
                $kind=$params['kind']==='invoice'?'invoice':'receipt';
                $changed=$this->repo->patchOrder($orderId,[],['document_preference'=>$kind,'invoice_required'=>$kind==='invoice'?1:0],$actor,'Automatyzacja ustawiła dokument sprzedaży: '.($kind==='invoice'?'faktura':'paragon').'.');
                return $changed?$ok('Ustawiono dokument: '.($kind==='invoice'?'faktura':'paragon')):$skip('Dokument sprzedaży był już ustawiony');
            case 'set_payment_method':
                $method=$this->db->fetch('SELECT * FROM om_payment_methods WHERE id=:id AND enabled=1',['id'=>(int)$params['payment_method_id']]);
                if (!$method) { throw new InvalidArgumentException('Wybrana metoda płatności jest wyłączona lub usunięta.'); }
                $orderFields=(int)$method['is_cod']?['paid'=>0]:[];
                $detailFields=['payment_method'=>(string)$method['name'],'cash_on_delivery'=>(int)$method['is_cod']]+((int)$method['is_cod']?['amount_paid_cents'=>0]:[]);
                $changed=$this->repo->patchOrder($orderId,$orderFields,$detailFields,$actor,'Automatyzacja ustawiła metodę płatności: '.$method['name'].'.');
                return $changed?$ok('Ustawiono metodę płatności „'.$method['name'].'”'):$skip('Metoda płatności była już ustawiona');
            case 'set_delivery':
                $changed=$this->repo->patchOrder($orderId,[],['delivery'=>mb_substr((string)$params['delivery'],0,255,'UTF-8')],$actor,'Automatyzacja zmieniła metodę dostawy na: '.$params['delivery'].'.');
                return $changed?$ok('Zmieniono metodę dostawy'):$skip('Metoda dostawy była już ustawiona');
            case 'issue_receipt':
                return $this->issueDocument($ctx,'receipt',(int)$params['series_id'],!empty($params['print']),$rule,$eventKey,$actor);
            case 'issue_invoice':
                return $this->issueDocument($ctx,'invoice',(int)$params['series_id'],false,$rule,$eventKey,$actor);
            case 'issue_preferred':
                $kind=$order['details']['document_preference']==='invoice'?'invoice':'receipt';
                return $this->issueDocument($ctx,$kind,(int)($kind==='invoice'?$params['invoice_series_id']:$params['receipt_series_id']),!empty($params['print']),$rule,$eventKey,$actor);
            case 'print_fiscal':
                $receipt=$this->db->fetch("SELECT id,number,series_id FROM om_documents WHERE order_id=:order AND kind='receipt' ORDER BY id DESC LIMIT 1",['order'=>$orderId]);
                if (!$receipt) { return $skip('Brak wystawionego paragonu do druku'); }
                $printerId=(int)$params['printer_id'] ?: $this->seriesPrinter((int)$receipt['series_id']);
                if ($printerId<1) { throw new InvalidArgumentException('Seria paragonu nie ma przypisanej drukarki fiskalnej.'); }
                return $this->queueFiscal($orderId,$printerId,(int)$receipt['id'],(string)$receipt['number'],$actor);
            case 'create_shipment':
                return $this->createShipment($ctx,$params,$rule,$eventKey,$actor);
            case 'refresh_shipments':
                $active=array_slice($this->activeShipments($ctx),0,5);
                if (!$active) { return $skip('Brak aktywnych przesyłek'); }
                $service=new OrderShipmentService($this->repo);
                foreach ($active as $shipment) { $service->refresh((int)$shipment['id'],$actor); }
                return $ok('Odświeżono przesyłki: '.count($active));
            case 'publish_tracking':
                return $this->publishTracking($ctx,$params,$actor);
            case 'print_label':
                [$stationId,$printer]=array_pad(explode('|',(string)$params['printer'],2),2,'');
                $printAgents=new PrintAgentRepository($this->db); $printAgents->ensureSchema();
                $jobs=$printAgents->queueOrderLabels($orderId,$params['scope']==='all'?'all':'newest',(int)$stationId,$printer,(float)$params['width'],(float)$params['height'],$actor,PrintAgentRepository::apiBase());
                $this->repo->event($orderId,'Dodano '.count($jobs).' etykiet do kolejki druku ('.implode(', ',$jobs).').',$actor);
                return $ok('Etykiety w kolejce druku: '.count($jobs));
            case 'email_customer':
            case 'email_address':
                $to=$action['type']==='email_customer'?trim((string)($order['email'] ?: $order['shipping_address']['email'])):(string)$params['to'];
                if ($to==='' || !filter_var($to,FILTER_VALIDATE_EMAIL)) { return $skip('Brak poprawnego adresu e-mail'); }
                $subject=trim(preg_replace('/[\r\n]+/',' ',$this->render((string)$params['subject'],$ctx))??'');
                $body=$this->render((string)$params['body'],$ctx);
                if (!(new MailService())->send($to,$subject,nl2br(htmlspecialchars($body,ENT_QUOTES,'UTF-8')),$body)) { throw new RuntimeException('Serwer poczty nie przyjął wiadomości; zapisano ją w logu poczty.'); }
                $this->repo->event($orderId,'Wysłano e-mail „'.$subject.'” na adres '.$to.'.',$actor);
                return $ok('Wysłano e-mail na '.$to);
            case 'webhook':
                $status=$this->webhook((string)$params['url'],$ctx,$rule);
                return $ok('Webhook odpowiedział HTTP '.$status);
            case 'accept_order':
                return $this->acceptOrder($order,$actor);
            case 'run_rule':
                $target=$this->rule((int)$params['rule_id']);
                if (!$target || !$target['enabled']) { return $skip('Wskazana automatyzacja jest wstrzymana lub usunięta'); }
                $chainKey=$target['id'].':'.$orderId;
                if (isset($this->chain[$chainKey])) { return $skip('„'.$target['name'].'” była już wykonana w tym łańcuchu'); }
                if ($this->depth>=self::MAX_DEPTH) { return $skip('Zbyt głęboki łańcuch automatyzacji'); }
                $nested=$this->context($orderId,$ctx['trigger'],$ctx['event']);
                if (!$this->matches($target,$nested)) { return $skip('Warunki „'.$target['name'].'” nie są spełnione'); }
                $this->chain[$chainKey]=true;
                $previousDepth=$this->depth; $this->depth++;
                try { $outcome=$this->execute($target,$nested,$item,$eventKey==='once'?bin2hex(random_bytes(16)):$eventKey.'-'.$target['id']); }
                finally { $this->depth=$previousDepth; }
                if ($outcome['state']==='error') { throw new RuntimeException('„'.$target['name'].'” zakończyła się błędem.'); }
                return $ok('Uruchomiono „'.$target['name'].'”');
            case 'stop':
                return ['state'=>'stop','message'=>'Zatrzymano kolejne automatyzacje'];
        }
        throw new InvalidArgumentException('Nieobsługiwany efekt automatyzacji.');
    }

    private function render(string $text,array $ctx): string
    {
        $order=$ctx['order'];
        $tracking='';
        foreach ($this->activeShipments($ctx) as $shipment) {
            if (strpos((string)$shipment['tracking'],'PENDING:')!==0) { $tracking=(string)$shipment['tracking']; break; }
        }
        return strtr($text,[
            '{id}'=>(string)$order['id'],'{numer_zamowienia}'=>(string)$order['external_id'],'{kupujacy}'=>(string)$order['buyer_name'],'{email}'=>(string)$order['email'],'{telefon}'=>(string)$order['phone'],
            '{kwota}'=>number_format((int)$order['total_cents']/100,2,',',' '),'{waluta}'=>(string)$order['currency'],'{status}'=>(string)$order['status_name'],'{platforma}'=>self::PLATFORMS[$order['platform']]??(string)$order['platform'],
            '{konto}'=>(string)$order['account_name'],'{dostawa}'=>(string)($order['details']['delivery']??''),'{platnosc}'=>(string)$order['details']['payment_method'],'{numer_przesylki}'=>$tracking,
            '{data_zamowienia}'=>(string)$order['ordered_at'],'{dzisiaj}'=>(new \DateTimeImmutable('now',new \DateTimeZone('Europe/Warsaw')))->format('Y-m-d'),
        ]);
    }

    private function requestKey(array $rule,int $orderId,string $purpose,string $eventKey): string
    {
        return hash('sha256','automation|'.$rule['id'].'|'.$orderId.'|'.$purpose.'|'.$eventKey);
    }

    private function seriesPrinter(int $seriesId): int
    {
        $series=$this->db->fetch('SELECT fiscal_printer_id FROM om_series WHERE id=:id',['id'=>$seriesId]);
        if (!$series) { return 0; }
        return $series['fiscal_printer_id']===null?(int)($this->repo->setting('receipt_printer')['printer_id']??0):(int)$series['fiscal_printer_id'];
    }

    private function issueDocument(array &$ctx,string $kind,int $seriesId,bool $print,array $rule,string $eventKey,string $actor): array
    {
        $orderId=(int)$ctx['order']['id'];
        $label=$kind==='invoice'?'Faktura':'Paragon';
        if (in_array($kind,array_column($this->documents($ctx),'kind'),true)) { return ['state'=>'skipped','message'=>$label.' dla zamówienia już istnieje']; }
        $series=$seriesId>0?$this->db->fetch('SELECT * FROM om_series WHERE id=:id AND kind=:kind',['id'=>$seriesId,'kind'=>$kind]):OrderDocumentService::defaultSeries($this->db,$kind);
        if (!$series) { throw new InvalidArgumentException('Wybrana seria dokumentu nie istnieje.'); }
        $service=new OrderDocumentService($this->repo);
        $payload=OrderDocumentService::orderPayload($this->repo,$ctx['order'],$kind,$this->requestKey($rule,$orderId,'document-'.$kind,$eventKey),$series)+['series_id'=>$series['id']];
        $documentId=$service->issue($orderId,$payload,$actor);
        $number=(string)$this->db->fetchColumn('SELECT number FROM om_documents WHERE id=:id',['id'=>$documentId]);
        $ctx['cache']=[];
        $message=($kind==='invoice'?'Wystawiono fakturę ':'Wystawiono paragon ').$number;
        if ($kind==='invoice') {
            $ksef=new KsefService($this->repo); $ksef->ensureSchema();
            $ksefMessage=$ksef->autoSend($documentId,$actor);
            if ($ksefMessage!==null) { $message.=' · '.$ksefMessage; }
        }
        $settings=json_decode((string)($series['document_settings_json']??''),true)?:[];
        if ($kind==='receipt' && $print && empty($settings['non_fiscal'])) {
            $printerId=$series['fiscal_printer_id']===null?(int)($this->repo->setting('receipt_printer')['printer_id']??0):(int)$series['fiscal_printer_id'];
            if ($printerId>0) { $message.=' · '.$this->queueFiscal($orderId,$printerId,$documentId,$number,$actor)['message']; }
        }
        return ['state'=>'ok','message'=>$message];
    }

    private function queueFiscal(int $orderId,int $printerId,int $documentId,string $number,string $actor): array
    {
        $printAgents=new PrintAgentRepository($this->db); $printAgents->ensureSchema();
        if ($this->db->fetchColumn('SELECT id FROM print_fiscal_jobs WHERE order_id=:order',['order'=>$orderId])) { return ['state'=>'skipped','message'=>'Paragon jest już w kolejce fiskalnej']; }
        $jobId=$printAgents->queueFiscalReceipt($orderId,$printerId,$actor,$documentId);
        $this->repo->event($orderId,'Dodano paragon '.$number.' do kolejki drukarki fiskalnej (zadanie '.$jobId.').',$actor);
        return ['state'=>'ok','message'=>'paragon '.$number.' w kolejce drukarki'];
    }

    private function createShipment(array &$ctx,array $params,array $rule,string $eventKey,string $actor): array
    {
        $order=$ctx['order']; $orderId=(int)$order['id'];
        if (empty($params['allow_multiple']) && $this->activeShipments($ctx)) { return ['state'=>'skipped','message'=>'Zamówienie ma już aktywną przesyłkę']; }
        $defaults=OrderShipmentService::defaults($this->repo);
        $accounts=$this->repo->carrierAccounts();
        $suggestion=OrderShipmentService::suggestion($this->repo,$order,$accounts,$defaults);
        $carrierId=(int)$params['carrier_account_id'] ?: (int)$suggestion['carrier_account_id'];
        $account=null;
        foreach ($accounts as $candidate) { if ((int)$candidate['id']===$carrierId && (int)$candidate['enabled']) { $account=$candidate; break; } }
        if (!$account) { throw new InvalidArgumentException('Brak aktywnego konta nadawczego dla tej przesyłki.'); }
        $size=$params['package']==='auto'?$suggestion['preset']:$params['package'];
        $package=$defaults['presets'][$size]??$suggestion['package'];
        $service=trim((string)$params['service']);
        if ($service==='') { $service=\App\Services\Shipping\ShippingProviders::get($this->repo,(string)$account['provider'])->preferredService(['id'=>(int)$account['id'],'public'=>json_decode((string)$account['public_config_json'],true)?:[]],$order,$defaults); }
        $address=$order['shipping_address'];
        $cod=!empty($order['details']['cash_on_delivery']);
        $input=['length'=>$package['length'],'width'=>$package['width'],'height'=>$package['height'],'weight'=>$package['weight'],'cash_on_delivery'=>$cod?'1':'','cod_amount'=>number_format(((int)$order['details']['amount_due_cents'] ?: (int)$order['total_cents'])/100,2,'.',''),'request_key'=>$this->requestKey($rule,$orderId,'shipment',$eventKey),'shipping_service'=>$service,'shipment_content'=>(string)$defaults['content'],
            'receiver_name'=>$address['name'],'receiver_email'=>$address['email'],'receiver_phone'=>$address['phone'],'receiver_street'=>$address['street'],'receiver_building'=>$address['building'],'receiver_postal_code'=>$address['postal_code'],'receiver_city'=>$address['city'],'receiver_country'=>$address['country']];
        $shipmentId=(new OrderShipmentService($this->repo))->create($orderId,(int)$account['id'],$input,$actor);
        $ctx['cache']=[];
        $tracking=(string)$this->db->fetchColumn('SELECT tracking FROM om_shipments WHERE id=:id',['id'=>$shipmentId]);
        return ['state'=>'ok','message'=>'Nadano przesyłkę przez '.$account['name'].(strpos($tracking,'PENDING:')===0?'':' ('.$tracking.')')];
    }

    private function publishTracking(array &$ctx,array $params,string $actor): array
    {
        $order=$ctx['order'];
        if ($order['platform']==='manual') { return ['state'=>'skipped','message'=>'Zamówienie własne nie ma marketplace']; }
        $shipment=null;
        foreach ($this->activeShipments($ctx) as $candidate) {
            if (trim((string)$candidate['tracking'])!=='' && strpos((string)$candidate['tracking'],'PENDING:')!==0) { $shipment=$candidate; break; }
        }
        if (!$shipment) { return ['state'=>'skipped','message'=>'Brak nadanego numeru przesyłki']; }
        $code=(string)$params['carrier']; $other=(string)($params['carrier_other']??'');
        if ($code==='auto') {
            $presentation=OrderShipmentService::presentation($shipment,$order);
            $haystack=mb_strtolower($presentation['carrier'].' '.$presentation['service'].' '.($order['details']['delivery']??''),'UTF-8');
            $code='other'; $other=(string)$presentation['carrier'];
            foreach (['inpost'=>['inpost','paczkomat'],'dpd'=>['dpd'],'gls'=>['gls'],'dhl'=>['dhl'],'ups'=>['ups'],'fedex'=>['fedex'],'orlen'=>['orlen'],'pocztex'=>['pocztex','poczta']] as $candidate=>$needles) {
                foreach ($needles as $needle) { if (strpos($haystack,$needle)!==false) { $code=$candidate; break 2; } }
            }
        }
        $message=(new OrderMarketplaceShipmentService($this->repo))->publishShipment((int)$shipment['id'],$code,$other,$actor);
        $ctx['cache']=[];
        return ['state'=>'ok','message'=>$message];
    }

    private function acceptOrder(array $order,string $actor): array
    {
        if (!in_array($order['platform'],['empik','mediamarkt'],true)) { return ['state'=>'skipped','message'=>'Akceptacja dotyczy tylko Empik i MediaMarkt']; }
        if (strtoupper((string)$order['remote_status'])!=='WAITING_ACCEPTANCE') { return ['state'=>'skipped','message'=>'Zamówienie nie oczekuje na akceptację']; }
        $integration=$order['platform']==='empik'?new EmpikService():new MediaMarktService();
        $source=null;
        foreach ($integration->listAccounts() as $candidate) { if ((int)$candidate['id']===(int)$order['account_source_id'] && !empty($candidate['is_active'])) { $source=$candidate; break; } }
        if (!$source) { throw new RuntimeException('Konto źródłowe marketplace jest nieaktywne.'); }
        $integration->acceptOrder($source,is_array($order['details']['raw']??null)?$order['details']['raw']:[]);
        $this->repo->event((int)$order['id'],'Zaakceptowano zamówienie w Mirakl (OR21).',$actor);
        return ['state'=>'ok','message'=>'Zaakceptowano zamówienie w marketplace'];
    }

    private function webhook(string $url,array $ctx,array $rule): int
    {
        $parts=parse_url($url);
        $host=strtolower((string)($parts['host']??'')); $port=(int)($parts['port']??443);
        if (strtolower((string)($parts['scheme']??''))!=='https' || $host==='' || isset($parts['user']) || !in_array($port,[443,8443],true)) { throw new InvalidArgumentException('Webhook wymaga publicznego adresu https:// (port 443 lub 8443).'); }
        $addresses=filter_var($host,FILTER_VALIDATE_IP)?[$host]:(gethostbynamel($host)?:[]);
        if (!$addresses) { throw new InvalidArgumentException('Nie można rozwiązać adresu webhooka.'); }
        foreach ($addresses as $address) {
            if (!filter_var($address,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) { throw new InvalidArgumentException('Webhook nie może wskazywać adresu prywatnego ani lokalnego.'); }
        }
        $order=$ctx['order'];
        $payload=['event'=>$ctx['trigger'],'rule'=>['id'=>$rule['id'],'name'=>$rule['name']],'sent_at'=>gmdate('c'),'order'=>[
            'id'=>(int)$order['id'],'external_id'=>$order['external_id'],'platform'=>$order['platform'],'account'=>$order['account_name'],'status'=>$order['status_name'],'remote_status'=>$order['remote_status'],
            'ordered_at'=>$order['ordered_at'],'total'=>number_format((int)$order['total_cents']/100,2,'.',''),'currency'=>$order['currency'],'paid'=>(bool)(int)$order['paid'],'payment_method'=>$order['details']['payment_method'],'cash_on_delivery'=>(bool)$order['details']['cash_on_delivery'],
            'buyer'=>['name'=>$order['buyer_name'],'email'=>$order['email'],'phone'=>$order['phone']],'delivery'=>['method'=>$order['details']['delivery']??'','pickup'=>$order['details']['pickup']??'','address'=>$order['shipping_address']],
            'items'=>array_map(static function ($item) { return ['name'=>(string)($item['name']??''),'sku'=>(string)($item['sku']??''),'quantity'=>(int)($item['quantity']??0),'unit_price'=>number_format((int)($item['unit_cents']??0)/100,2,'.','')]; },(array)($order['details']['items']??[])),
            'tags'=>self::splitList((string)$order['tags']),
        ]];
        $curl=curl_init($url);
        if ($curl===false) { throw new RuntimeException('Nie można uruchomić połączenia webhooka.'); }
        curl_setopt_array($curl,[
            CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>8,CURLOPT_RESOLVE=>[$host.':'.$port.':'.$addresses[0]],CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','User-Agent: SalesCenter-Automation/1.0','X-SalesCenter-Event: '.$ctx['trigger']],
        ]);
        if (defined('CURLOPT_PROTOCOLS')) { curl_setopt($curl,CURLOPT_PROTOCOLS,CURLPROTO_HTTPS); }
        curl_exec($curl);
        $error=curl_error($curl); $status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if ($error!=='') { throw new RuntimeException('Webhook nie odpowiedział: '.$error); }
        if ($status<200 || $status>=300) { throw new RuntimeException('Webhook zwrócił HTTP '.$status.'.'); }
        return $status;
    }
}
