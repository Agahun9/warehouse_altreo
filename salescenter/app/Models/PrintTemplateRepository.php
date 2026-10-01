<?php

declare(strict_types=1);
namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;

/** Szablony wydruku/eksportu zamówień (PDF, HTML, CSV) — tabela firmy om_print_templates. */
final class PrintTemplateRepository
{
    public const FORMATS = ['pdf'=>'PDF','html'=>'HTML','csv'=>'CSV'];
    public const PAGE_SIZES = ['A4'=>'A4 (210×297 mm)','A5'=>'A5 (148×210 mm)','A6'=>'A6 (105×148 mm)','label100x150'=>'Etykieta 100×150 mm','Letter'=>'Letter'];
    private const SEEDED_SETTING = 'print_templates_seeded';
    /** Podnieś przy dodaniu/zmianie przykładu z 'since' = nowa wersja — istniejące firmy dostaną go automatycznie.
     *  Zmieniony przykład podmienia się tylko tam, gdzie treść jest nietknięta ('upgrade_from' = sha1(body."\0".css) starych wersji). */
    private const EXAMPLES_VERSION = 6;
    /** @var Database */
    private $db;

    public function __construct(Database $db) { $this->db = $db; }

    public function ensureSchema(): void
    {
        $sqlite = $this->db->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $id = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $suffix = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';
        $this->db->query("CREATE TABLE IF NOT EXISTS om_print_templates (id $id, name VARCHAR(150) NOT NULL, format VARCHAR(10) NOT NULL, description VARCHAR(500) NOT NULL DEFAULT '', content_json LONGTEXT NOT NULL, position INTEGER NOT NULL DEFAULT 0, active INTEGER NOT NULL DEFAULT 1, created_at VARCHAR(30) NOT NULL, updated_at VARCHAR(30) NOT NULL)$suffix");
        $columns = $sqlite ? array_column($this->db->fetchAll('PRAGMA table_info(om_print_templates)'),'name') : array_column($this->db->fetchAll('SHOW COLUMNS FROM om_print_templates'),'Field');
        if (!in_array('active',$columns,true)) {
            try { $this->db->query('ALTER TABLE om_print_templates ADD COLUMN active INTEGER NOT NULL DEFAULT 1'); }
            catch (\PDOException $e) { if ((int)($e->errorInfo[1] ?? 0) !== 1060) { throw $e; } }
        }
        // Przykłady tylko raz na firmę — usunięte przez użytkownika nie wracają same; nowe wersje dodają tylko nowe przykłady.
        $current = '{"version":'.self::EXAMPLES_VERSION.'}';
        $stored = $this->db->fetchColumn('SELECT value_json FROM om_settings WHERE setting_key=:k',['k'=>self::SEEDED_SETTING]);
        if ($stored === $current) { return; }
        try {
            $this->db->transaction(function () use ($stored,$current) {
                if ($stored === false || $stored === null) {
                    // Klucz główny ustawienia blokuje podwójne dodanie przy równoległych pierwszych wejściach.
                    $this->db->insert('om_settings',['setting_key'=>self::SEEDED_SETTING,'value_json'=>$current]);
                    $fresh = !(int)$this->db->fetchColumn('SELECT COUNT(*) FROM om_print_templates');
                    $this->insertExamples($fresh ? 0 : self::EXAMPLES_VERSION);
                    return;
                }
                $version = (int)((json_decode((string)$stored,true) ?: [])['version'] ?? 1);
                if ($version >= self::EXAMPLES_VERSION) { return; }
                if (!$this->db->update('om_settings',['value_json'=>$current],'setting_key=:k AND value_json=:old',['k'=>self::SEEDED_SETTING,'old'=>$stored])) { return; }
                $this->insertExamples($version);
            });
        } catch (\PDOException $e) {
            if (!$this->db->fetchColumn('SELECT value_json FROM om_settings WHERE setting_key=:k',['k'=>self::SEEDED_SETTING])) { throw $e; }
        }
    }

    public function all(): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM om_print_templates ORDER BY position,id');
        return array_map([$this,'hydrate'],$rows);
    }

    public function find(int $id): ?array
    {
        $row = $this->db->fetch('SELECT * FROM om_print_templates WHERE id=:id',['id'=>$id]);
        return $row ? $this->hydrate($row) : null;
    }

    /** @param bool|null $active null = bez zmiany (nowy szablon: aktywny) */
    public function save(int $id,string $name,string $format,string $description,array $content,?bool $active=null): int
    {
        $name = trim($name); $description = trim($description);
        if ($name === '' || mb_strlen($name) > 150) { throw new InvalidArgumentException('Podaj nazwę szablonu (maks. 150 znaków).'); }
        if (!isset(self::FORMATS[$format])) { throw new InvalidArgumentException('Wybierz format: PDF, HTML albo CSV.'); }
        if (mb_strlen($description) > 500) { throw new InvalidArgumentException('Opis może mieć maks. 500 znaków.'); }
        $content = self::normalizeContent($format,$content);
        $json = json_encode($content,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        if (strlen($json) > 400000) { throw new InvalidArgumentException('Szablon jest za duży (maks. ok. 400 KB).'); }
        $now = gmdate('Y-m-d H:i:s');
        $data = ['name'=>$name,'format'=>$format,'description'=>$description,'content_json'=>$json,'updated_at'=>$now];
        if ($active !== null) { $data['active'] = $active ? 1 : 0; }
        if ($id > 0) {
            if (!$this->find($id)) { throw new InvalidArgumentException('Nie znaleziono szablonu.'); }
            $this->db->update('om_print_templates',$data,'id=:id',['id'=>$id]);
            return $id;
        }
        $data['position'] = (int)$this->db->fetchColumn('SELECT COALESCE(MAX(position),0)+1 FROM om_print_templates');
        $data['created_at'] = $now;
        return (int)$this->db->insert('om_print_templates',$data);
    }

    public function setActive(int $id,bool $active): void
    {
        if (!$this->find($id)) { throw new InvalidArgumentException('Nie znaleziono szablonu.'); }
        $this->db->update('om_print_templates',['active'=>$active ? 1 : 0,'updated_at'=>gmdate('Y-m-d H:i:s')],'id=:id',['id'=>$id]);
    }

    /** Szablony do menu eksportu (bez nieaktywnych). */
    public function active(): array
    {
        return array_values(array_filter($this->all(),static function (array $template): bool { return $template['active']; }));
    }

    public function duplicate(int $id): int
    {
        $template = $this->find($id);
        if (!$template) { throw new InvalidArgumentException('Nie znaleziono szablonu.'); }
        return $this->save(0,mb_substr($template['name'].' (kopia)',0,150),$template['format'],$template['description'],$template['content'],$template['active']);
    }

    public function delete(int $id): void
    {
        if (!$this->db->delete('om_print_templates','id=:id',['id'=>$id])) { throw new InvalidArgumentException('Nie znaleziono szablonu.'); }
    }

    /** Dodaje brakujące przykładowe szablony (po nazwie); zwraca liczbę dodanych. */
    public function restoreExamples(): int
    {
        $existing = array_flip(array_column($this->db->fetchAll('SELECT name FROM om_print_templates'),'name'));
        $added = 0;
        foreach (self::examples() as $example) {
            if (isset($existing[$example['name']])) { continue; }
            $this->save(0,$example['name'],$example['format'],$example['description'],$example['content']);
            $added++;
        }
        return $added;
    }

    public static function normalizeContent(string $format,array $content): array
    {
        if ($format === 'csv') {
            $columns = [];
            foreach ((array)($content['columns'] ?? []) as $column) {
                if (!is_array($column)) { continue; }
                $header = mb_substr(trim((string)($column['header'] ?? '')),0,200);
                $value = mb_substr((string)($column['value'] ?? ''),0,2000);
                if ($header === '' && trim($value) === '') { continue; }
                $columns[] = ['header'=>$header,'value'=>$value];
            }
            if (!$columns) { throw new InvalidArgumentException('Dodaj co najmniej jedną kolumnę CSV.'); }
            if (count($columns) > 200) { throw new InvalidArgumentException('Maksymalnie 200 kolumn CSV.'); }
            $separator = (string)($content['separator'] ?? ';');
            return [
                'columns'=>$columns,
                'rows'=>($content['rows'] ?? 'order') === 'item' ? 'item' : 'order',
                'separator'=>in_array($separator,[';',',','tab','|'],true) ? $separator : ';',
                'header_row'=>!empty($content['header_row']),
                'bom'=>!empty($content['bom']),
            ];
        }
        $pageSize = (string)($content['page_size'] ?? 'A4');
        $margin = (float)str_replace(',','.',(string)($content['margin'] ?? '12'));
        return [
            'mode'=>($content['mode'] ?? 'per_order') === 'list' ? 'list' : 'per_order',
            'page_size'=>isset(self::PAGE_SIZES[$pageSize]) ? $pageSize : 'A4',
            'orientation'=>($content['orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait',
            'margin'=>max(0,min(40,round($margin,1))),
            'page_break'=>!empty($content['page_break']),
            'body'=>(string)($content['body'] ?? ''),
            'css'=>(string)($content['css'] ?? ''),
        ];
    }

    private function hydrate(array $row): array
    {
        $row['id'] = (int)$row['id'];
        $row['active'] = (int)($row['active'] ?? 1) === 1;
        $content = json_decode((string)$row['content_json'],true);
        $row['content'] = self::normalizeContentSafe((string)$row['format'],is_array($content) ? $content : []);
        unset($row['content_json']);
        $row['format_label'] = self::FORMATS[$row['format']] ?? strtoupper((string)$row['format']);
        return $row;
    }

    private static function normalizeContentSafe(string $format,array $content): array
    {
        try { return self::normalizeContent($format,$content); }
        catch (InvalidArgumentException $e) { return $format === 'csv' ? ['columns'=>[],'rows'=>'order','separator'=>';','header_row'=>true,'bom'=>true] : self::normalizeContent($format,[]); }
    }

    /** Dodaje przykłady nowsze niż $sinceVersion, których nazwy jeszcze nie ma. */
    private function insertExamples(int $sinceVersion): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $position = (int)$this->db->fetchColumn('SELECT COALESCE(MAX(position),0) FROM om_print_templates');
        $existing = array_flip(array_column($this->db->fetchAll('SELECT name FROM om_print_templates'),'name'));
        foreach (self::examples() as $example) {
            if (($example['since'] ?? 1) <= $sinceVersion) { continue; }
            if (isset($existing[$example['name']])) { $this->upgradeExample($example,$now); continue; }
            $this->db->insert('om_print_templates',[
                'name'=>$example['name'],'format'=>$example['format'],'description'=>$example['description'],
                'content_json'=>json_encode(self::normalizeContent($example['format'],$example['content']),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
                'position'=>++$position,'created_at'=>$now,'updated_at'=>$now,
            ]);
        }
    }

    private function upgradeExample(array $example,string $now): void
    {
        if (empty($example['upgrade_from'])) { return; }
        foreach ($this->db->fetchAll('SELECT id,content_json FROM om_print_templates WHERE name=:name',['name'=>$example['name']]) as $row) {
            $content = json_decode((string)$row['content_json'],true);
            if (!is_array($content) || !in_array(sha1((string)($content['body'] ?? '')."\0".(string)($content['css'] ?? '')),$example['upgrade_from'],true)) { continue; }
            $this->db->update('om_print_templates',[
                'format'=>$example['format'],'description'=>$example['description'],'updated_at'=>$now,
                'content_json'=>json_encode(self::normalizeContent($example['format'],$example['content']),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
            ],'id=:id',['id'=>(int)$row['id']]);
        }
    }

    /** Przykładowe szablony tworzone dla każdej nowej firmy. */
    public static function examples(): array
    {
        $baseCss = "body{font-family:'Source Sans 3',Arial,sans-serif;font-size:11px;color:#1f2937}\nh1{font-size:20px;margin:0 0 4px}\nh2{font-size:13px;margin:16px 0 6px;text-transform:uppercase;letter-spacing:.04em;color:#475569}\ntable{width:100%;border-collapse:collapse}\nth,td{border:1px solid #cbd5e1;padding:5px 6px;text-align:left;vertical-align:top}\nth{background:#f1f5f9;font-weight:700}\n.right{text-align:right}\n.muted{color:#64748b}\n.box{border:1px solid #cbd5e1;border-radius:6px;padding:8px 10px}\n.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}\n.pre{white-space:pre-line}";
        return [
            [
                'name'=>'Potwierdzenie zamówienia','format'=>'pdf',
                'description'=>'Jedna strona A4 na zamówienie: sprzedawca, kupujący, adres dostawy, produkty i podsumowanie płatności.',
                'content'=>['mode'=>'per_order','page_size'=>'A4','orientation'=>'portrait','margin'=>14,'page_break'=>true,'css'=>$baseCss."\n.head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #1f2937;padding-bottom:10px;margin-bottom:14px}\n.totals{width:45%;margin:12px 0 0 auto}\n.totals td{border:0;border-bottom:1px solid #e2e8f0}\n.totals tr:last-child td{font-size:14px;font-weight:700;border-bottom:0}",
                    'body'=><<<'HTML'
<div class="head">
  <div>
    <h1>Potwierdzenie zamówienia</h1>
    <div>Nr <strong>{{order.number}}</strong> · {{order.platform_label}} ({{order.account}})</div>
    <div class="muted">Data zamówienia: {{order.date}}</div>
  </div>
  <div class="right">
    <strong>{{seller.name}}</strong><br>
    <span class="pre">{{seller.address}}</span><br>
    {{#if seller.nip}}NIP: {{seller.nip}}<br>{{/if}}
    {{seller.email}} {{seller.phone}}
  </div>
</div>
<div class="grid">
  <div class="box">
    <h2>Kupujący</h2>
    <strong>{{buyer.name}}</strong><br>
    {{buyer.email}}<br>{{buyer.phone}}
    {{#if invoice.required}}
      <h2>Dane do faktury</h2>
      {{#if invoice.company}}<strong>{{invoice.company}}</strong><br>{{/if}}
      {{invoice.name}}<br>{{invoice.street_full}}<br>{{invoice.postal_code}} {{invoice.city}}<br>
      {{#if invoice.nip}}NIP: {{invoice.nip}}{{/if}}
    {{/if}}
  </div>
  <div class="box">
    <h2>Dostawa</h2>
    <strong>{{shipping.method}}</strong><br>
    {{#if shipping.pickup_point}}Punkt odbioru: {{shipping.pickup_point}}<br>{{/if}}
    {{shipping.name}}<br>{{shipping.street_full}}<br>{{shipping.postal_code}} {{shipping.city}}, {{shipping.country}}<br>
    tel. {{shipping.phone}}
  </div>
</div>
<h2>Produkty</h2>
<table>
  <thead><tr><th>Lp.</th><th>Nazwa</th><th>SKU</th><th class="right">Ilość</th><th class="right">Cena</th><th class="right">Wartość</th></tr></thead>
  <tbody>
  {{#each items}}
    <tr><td>{{item.lp}}</td><td>{{item.name}}</td><td>{{item.sku}}</td><td class="right">{{item.quantity}}</td><td class="right">{{item.unit_price}}</td><td class="right">{{item.total_price}}</td></tr>
  {{/each}}
  </tbody>
</table>
<table class="totals">
  <tr><td>Produkty</td><td class="right">{{order.items_total}} {{order.currency}}</td></tr>
  <tr><td>Dostawa</td><td class="right">{{order.shipping_cost}} {{order.currency}}</td></tr>
  {{#if order.adjustment_cents}}<tr><td>{{order.adjustment_label}}</td><td class="right">{{order.adjustment}} {{order.currency}}</td></tr>{{/if}}
  <tr><td>Razem</td><td class="right">{{order.total}} {{order.currency}}</td></tr>
</table>
<p>Płatność: <strong>{{payment.method}}</strong> · {{payment.status}}{{#if payment.cod}} · do pobrania {{payment.due_amount}} {{order.currency}}{{/if}}</p>
{{#if order.buyer_note}}<div class="box"><strong>Wiadomość od kupującego:</strong> {{order.buyer_note}}</div>{{/if}}
HTML
                ],
            ],
            [
                'name'=>'Lista kompletacyjna','format'=>'pdf',
                'description'=>'Zbiorcza lista produktów do zebrania z magazynu dla zaznaczonych zamówień (sumy po SKU).',
                'content'=>['mode'=>'list','page_size'=>'A4','orientation'=>'portrait','margin'=>12,'page_break'=>false,'css'=>$baseCss."\n.check{width:28px;text-align:center}\n.check span{display:inline-block;width:14px;height:14px;border:1.5px solid #334155;border-radius:3px}\n.qty{font-size:15px;font-weight:700;text-align:center;width:60px}\nimg{width:38px;height:38px;object-fit:contain}",
                    'body'=><<<'HTML'
<h1>Lista kompletacyjna</h1>
<p class="muted">Wygenerowano {{print.datetime}} · {{print.user}} · zamówień: {{print.count}} · sztuk razem: {{print.items_quantity}}</p>
<table>
  <thead><tr><th class="check"></th><th></th><th>Produkt</th><th>SKU / EAN</th><th class="qty">Ilość</th><th>Zamówienia</th></tr></thead>
  <tbody>
  {{#each products}}
    <tr>
      <td class="check"><span></span></td>
      <td>{{#if product.image_url}}<img src="{{product.image_url}}" alt="">{{/if}}</td>
      <td><strong>{{product.name}}</strong></td>
      <td>{{product.sku}}{{#if product.ean}}<br><span class="muted">{{product.ean}}</span>{{/if}}</td>
      <td class="qty">{{product.quantity}}</td>
      <td class="muted">{{product.orders}}</td>
    </tr>
  {{/each}}
  </tbody>
</table>
HTML
                ],
            ],
            [
                'name'=>'Lista zamówień','format'=>'pdf',
                'description'=>'Tabela zaznaczonych zamówień w poziomie: klient, adres, dostawa, płatność, kwota i produkty.',
                'content'=>['mode'=>'list','page_size'=>'A4','orientation'=>'landscape','margin'=>10,'page_break'=>false,'css'=>$baseCss."\ntd{font-size:10px}\ntr{page-break-inside:avoid}",
                    'body'=><<<'HTML'
<h1>Zestawienie zamówień</h1>
<p class="muted">{{print.datetime}} · zamówień: {{print.count}} · suma: {{print.total}}</p>
<table>
  <thead><tr><th>#</th><th>Numer / data</th><th>Klient</th><th>Adres dostawy</th><th>Dostawa</th><th>Płatność</th><th>Produkty</th><th class="right">Kwota</th><th>Status</th></tr></thead>
  <tbody>
  {{#each orders}}
    <tr>
      <td>{{loop.index}}</td>
      <td><strong>{{order.number}}</strong><br><span class="muted">{{order.date}}<br>{{order.platform_label}}</span></td>
      <td>{{buyer.name}}<br><span class="muted">{{buyer.phone}}</span></td>
      <td>{{shipping.address}}</td>
      <td>{{shipping.method}}{{#if shipping.pickup_point}}<br><span class="muted">{{shipping.pickup_point}}</span>{{/if}}{{#if order.tracking_numbers}}<br>{{order.tracking_numbers}}{{/if}}</td>
      <td>{{payment.method}}<br><span class="muted">{{payment.status}}</span></td>
      <td>{{#each items}}{{item.quantity}} × {{item.name}}{{#unless loop.last}}<br>{{/unless}}{{/each}}</td>
      <td class="right"><strong>{{order.total}} {{order.currency}}</strong></td>
      <td>{{order.status}}</td>
    </tr>
  {{/each}}
  </tbody>
</table>
HTML
                ],
            ],
            [
                'name'=>'Etykieta adresowa 100×150','format'=>'pdf',
                'description'=>'Etykieta na drukarkę termiczną: odbiorca, telefon, metoda dostawy i lista produktów.',
                'content'=>['mode'=>'per_order','page_size'=>'label100x150','orientation'=>'portrait','margin'=>4,'page_break'=>true,'css'=>"body{font-family:Arial,sans-serif;font-size:11px;color:#000}\n.label{border:2px solid #000;border-radius:4px;padding:6px;height:138mm;display:flex;flex-direction:column;gap:6px}\n.to{font-size:16px;line-height:1.3}\n.big{font-size:22px;font-weight:700;letter-spacing:.03em}\n.row{border-top:1px dashed #000;padding-top:5px}\n.small{font-size:9px}",
                    'body'=><<<'HTML'
<div class="label">
  <div class="small">Nadawca: {{seller.name}}</div>
  <div class="row to">
    <strong>{{shipping.name}}</strong><br>
    {{shipping.street_full}}<br>
    <span class="big">{{shipping.postal_code}}</span> {{shipping.city}}<br>
    tel. {{shipping.phone}}
  </div>
  <div class="row"><strong>{{shipping.method}}</strong>{{#if shipping.pickup_point}}<br>Punkt: {{shipping.pickup_point}}{{/if}}</div>
  {{#if payment.cod}}<div class="row big">POBRANIE {{payment.due_amount}} {{order.currency}}</div>{{/if}}
  <div class="row small">Zam. {{order.number}} · {{order.date_only}}<br>{{#each items}}{{item.quantity}}× {{item.sku}} {{item.name|truncate:40}}<br>{{/each}}</div>
</div>
HTML
                ],
            ],
            [
                'name'=>'Raport zamówień (HTML)','format'=>'html',
                'description'=>'Plik HTML do wysłania mailem lub archiwizacji — karty zamówień z pełnymi danymi.',
                'content'=>['mode'=>'list','page_size'=>'A4','orientation'=>'portrait','margin'=>12,'page_break'=>false,'css'=>$baseCss."\nbody{background:#f8fafc}\n.card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;margin:0 0 14px;page-break-inside:avoid}\n.card h3{margin:0 0 6px;font-size:15px}\n.tag{display:inline-block;padding:2px 8px;border-radius:99px;background:#eef2ff;color:#3730a3;font-size:10px}",
                    'body'=><<<'HTML'
<h1>Raport zamówień</h1>
<p class="muted">Wygenerowano {{print.datetime}} przez {{print.user}} · zamówień: {{print.count}} · suma: {{print.total}}</p>
{{#each orders}}
<div class="card">
  <h3>{{order.number}} <span class="tag">{{order.status}}</span></h3>
  <div class="muted">{{order.date}} · {{order.platform_label}} / {{order.account}}</div>
  <div class="grid" style="margin-top:8px">
    <div><strong>{{buyer.name}}</strong><br>{{buyer.email}}<br>{{buyer.phone}}</div>
    <div>{{shipping.method}}<br>{{shipping.address}}{{#if order.tracking_numbers}}<br>Przesyłka: {{order.tracking_numbers}}{{/if}}</div>
  </div>
  <table style="margin-top:8px">
    {{#each items}}<tr><td>{{item.name}}</td><td>{{item.sku}}</td><td class="right">{{item.quantity}} × {{item.unit_price}}</td><td class="right">{{item.total_price}}</td></tr>{{/each}}
    <tr><td colspan="3" class="right">Dostawa</td><td class="right">{{order.shipping_cost}}</td></tr>
    <tr><th colspan="3" class="right">Razem</th><th class="right">{{order.total}} {{order.currency}}</th></tr>
  </table>
  {{#if order.notes_text}}<p class="pre muted">Notatki: {{order.notes_text}}</p>{{/if}}
</div>
{{/each}}
HTML
                ],
            ],
            [
                'name'=>'Wydruk komputerów','format'=>'pdf','since'=>6,'upgrade_from'=>['bb1898b8fbbc981a5a1bf22a9a0c34f0a6939a6e','5fac3956e8602c1941384c68cc46bde2e99f9f4e','881d07cf4518f8dbf195aabcf7fc212af74c7f7e'],
                'description'=>'Karty kompletacji 9 na stronę A4: numer zamówienia i #ID, wysyłka, produkty do odhaczenia, specyfikacja z drugiej notatki (każdy element „/” w nowej linii).',
                'content'=>['mode'=>'list','page_size'=>'A4','orientation'=>'portrait','margin'=>8,'page_break'=>false,'css'=><<<'CSS'
body{font-family:Arial,sans-serif;color:#000}
.cards{display:grid;grid-template-columns:repeat(3,1fr);grid-auto-rows:90mm;gap:3mm}
.card{border:.4mm solid #000;border-radius:1.5mm;padding:2mm 2.2mm;overflow:hidden;break-inside:avoid;page-break-inside:avoid;display:flex;flex-direction:column;gap:1.1mm;font-size:2.4mm;line-height:1.25}
.head{font-size:3mm;font-weight:700;word-break:break-all;border-bottom:.3mm solid #000;padding-bottom:.8mm}
.meta{color:#333;font-size:2.2mm}
.addr b{font-size:2.5mm}
.item{display:flex;gap:1.2mm;align-items:center;border-top:.2mm dashed #888;padding-top:1mm}
.item .box{flex:0 0 3.4mm;height:3.4mm;border:.3mm solid #000}
.item .qty{flex:0 0 auto;font-size:2.9mm}
.item img{flex:0 0 7mm;width:7mm;height:7mm;object-fit:contain}
.item .name{display:flex;flex-direction:column;font-size:2.3mm;line-height:1.2}
.item small{font-size:2mm;color:#333}
.spec{flex:1 1 auto;min-height:0;overflow:hidden;white-space:pre-line;border:.3mm solid #000;border-left-width:1.1mm;border-radius:1mm;padding:1.2mm 1.8mm;font-size:2.85mm;line-height:1.32;background:#f3f3f3}
.spec::first-line{font-weight:700}
.comment{font-size:2.2mm;border-top:.2mm dashed #888;padding-top:.8mm}
CSS
                    ,'body'=><<<'HTML'
<div class="cards">
{{#each orders}}
  <div class="card">
    <div class="head">{{order.number}} / {{order.internal_number}}</div>
    <div class="meta">{{order.date}} · {{payment.status}} · {{order.platform_label}}</div>
    <div class="addr">
      <b>{{shipping.method}}</b>{{#if shipping.pickup_point}} · {{shipping.pickup_point}}{{/if}}<br>
      {{shipping.name}}{{#if shipping.company}}, {{shipping.company}}{{/if}} · {{shipping.phone}}<br>
      {{shipping.street_full}}, {{shipping.postal_code}} {{shipping.city}}
      {{#if invoice.company}}<br>FV: {{invoice.company}}{{#if invoice.nip}}, NIP {{invoice.nip}}{{/if}}{{/if}}
    </div>
    {{#each items}}
    <div class="item">
      <span class="box"></span>
      <b class="qty">{{item.quantity}} szt.</b>
      {{#if item.image_url}}<img src="{{item.image_url}}" alt="">{{/if}}
      <span class="name">{{item.name}}<small>{{item.sku}} · {{item.total_price}} {{order.currency}}</small></span>
    </div>
    {{/each}}
    {{#if notes.1.body}}<div class="spec">{{notes.1.body|replace:"Specyfikacja":""|lines:" / "}}</div>{{/if}}
    {{#if order.buyer_note}}<div class="comment"><b>Komentarz:</b> {{order.buyer_note}}</div>{{/if}}
  </div>
{{/each}}
</div>
HTML
                ],
            ],
            [
                'name'=>'Eksport zamówień (CSV)','format'=>'csv',
                'description'=>'Jeden wiersz na zamówienie — do Excela lub systemu księgowego.',
                'content'=>['rows'=>'order','separator'=>';','header_row'=>true,'bom'=>true,'columns'=>[
                    ['header'=>'Numer','value'=>'{{order.number}}'],['header'=>'ID','value'=>'{{order.id}}'],['header'=>'Data','value'=>'{{order.date}}'],
                    ['header'=>'Źródło','value'=>'{{order.platform_label}}'],['header'=>'Konto','value'=>'{{order.account}}'],['header'=>'Status','value'=>'{{order.status}}'],
                    ['header'=>'Klient','value'=>'{{buyer.name}}'],['header'=>'E-mail','value'=>'{{buyer.email}}'],['header'=>'Telefon','value'=>'{{buyer.phone}}'],
                    ['header'=>'Ulica','value'=>'{{shipping.street_full}}'],['header'=>'Kod','value'=>'{{shipping.postal_code}}'],['header'=>'Miasto','value'=>'{{shipping.city}}'],['header'=>'Kraj','value'=>'{{shipping.country}}'],
                    ['header'=>'Dostawa','value'=>'{{shipping.method}}'],['header'=>'Punkt odbioru','value'=>'{{shipping.pickup_point}}'],['header'=>'Płatność','value'=>'{{payment.method}}'],['header'=>'Opłacone','value'=>'{{payment.paid}}'],
                    ['header'=>'Produkty','value'=>'{{order.items_summary}}'],['header'=>'Koszt dostawy','value'=>'{{order.shipping_cost}}'],['header'=>'Kwota','value'=>'{{order.total}}'],['header'=>'Waluta','value'=>'{{order.currency}}'],
                    ['header'=>'NIP','value'=>'{{invoice.nip}}'],['header'=>'Firma','value'=>'{{invoice.company}}'],['header'=>'Numer przesyłki','value'=>'{{order.tracking_numbers}}'],['header'=>'Faktura','value'=>'{{order.invoice_number}}'],
                ]],
            ],
            [
                'name'=>'Eksport pozycji (CSV)','format'=>'csv',
                'description'=>'Jeden wiersz na produkt w zamówieniu — do analizy sprzedaży po SKU.',
                'content'=>['rows'=>'item','separator'=>';','header_row'=>true,'bom'=>true,'columns'=>[
                    ['header'=>'Numer zamówienia','value'=>'{{order.number}}'],['header'=>'Data','value'=>'{{order.date_only}}'],['header'=>'Źródło','value'=>'{{order.platform_label}}'],
                    ['header'=>'Klient','value'=>'{{buyer.name}}'],['header'=>'Lp.','value'=>'{{item.lp}}'],['header'=>'SKU','value'=>'{{item.sku}}'],['header'=>'EAN','value'=>'{{item.ean}}'],
                    ['header'=>'Nazwa','value'=>'{{item.name}}'],['header'=>'Ilość','value'=>'{{item.quantity}}'],['header'=>'Cena brutto','value'=>'{{item.unit_price}}'],
                    ['header'=>'Wartość brutto','value'=>'{{item.total_price}}'],['header'=>'VAT','value'=>'{{item.vat}}'],['header'=>'Waluta','value'=>'{{order.currency}}'],
                ]],
            ],
        ];
    }
}
