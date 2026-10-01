<?php
/** Szablony druku na izolowanej bazie w pamięci. Nie łączy się z bazą produkcyjną ani z marketplace. */
declare(strict_types=1);
define('BASE_PATH',dirname(__DIR__));
require BASE_PATH.'/app/bootstrap.php';
use App\Core\Database;
use App\Models\OrderRepository;
use App\Models\PrintTemplateRepository;
use App\Services\OrderNormalizer;
use App\Services\OrderPrintTemplateService;

$checks=0;
function check(bool $condition,string $label): void { global $checks; $checks++; if (!$condition) { throw new RuntimeException($label); } }
function rejects(callable $fn,string $label): void { try { $fn(); } catch (\Throwable $e) { check(true,$label); return; } check(false,$label); }
$reflection=new ReflectionClass(Database::class);
$db=$reflection->newInstanceWithoutConstructor();
$pdo=new PDO('sqlite::memory:'); $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
foreach (['pdo'=>$pdo,'config'=>['driver'=>'sqlite']] as $name=>$value) { $property=$reflection->getProperty($name); $property->setValue($db,$value); }
App\Core\Tenant::activate(1);
check(App\Core\Tenant::rewrite('SELECT * FROM om_print_templates')==='SELECT * FROM t1_om_print_templates','Templates table is tenant-scoped');

$repo=new OrderRepository($db); $repo->ensureSchema();
$templates=new PrintTemplateRepository($db); $templates->ensureSchema(); $templates->ensureSchema();
$examples=count(PrintTemplateRepository::examples());
check(count($templates->all())===$examples,'Examples seeded once');
check(count(array_unique(array_column($templates->all(),'format')))===3,'Examples cover PDF, HTML and CSV');
foreach ($templates->all() as $template) { $templates->delete($template['id']); }
$templates->ensureSchema();
check(count($templates->all())===0,'Deleted examples are not re-seeded automatically');
check($templates->restoreExamples()===$examples && $templates->restoreExamples()===0,'Restore adds only missing examples');

// Firma z wersją 1 przykładów dostaje tylko nowy przykład; usunięte starsze nie wracają.
$computers=array_values(array_filter($templates->all(),static function ($t) { return $t['name']==='Wydruk komputerów'; }));
check(count($computers)===1,'Computer printout example exists');
$templates->delete($computers[0]['id']);
$templates->delete(array_values(array_filter($templates->all(),static function ($t) { return $t['name']==='Lista kompletacyjna'; }))[0]['id']);
$repo->saveSetting('print_templates_seeded',['version'=>1]);
$templates->ensureSchema(); $templates->ensureSchema();
$names=array_column($templates->all(),'name');
check(count(array_keys($names,'Wydruk komputerów'))===1 && !in_array('Lista kompletacyjna',$names,true),'Upgrade adds only new examples, once');
// Firma oznaczona wersją 2 bez szablonu (stan pośredni na serwerze) też go dostaje.
$templates->delete(array_values(array_filter($templates->all(),static function ($t) { return $t['name']==='Wydruk komputerów'; }))[0]['id']);
$repo->saveSetting('print_templates_seeded',['version'=>2]);
$templates->ensureSchema();
check(in_array('Wydruk komputerów',array_column($templates->all(),'name'),true),'Tenants marked v2 without the example get it');
// Wersja 3 → 4: nietknięty stary szablon podmieniony, edytowany zostaje.
foreach ($templates->all() as $t) { if ($t['name']==='Wydruk komputerów') { $templates->delete($t['id']); } }
$oldContent=json_decode(gzuncompress(base64_decode('eNqlV01u4zYUvorgQYEJECm2k3QGchpglkWBYIAuujEgUCRlEaZIlaQSO4I3vVKPUPRefaQoWZScZIAuYknk9/6/98i0i0oSukgXNVWZVISqxfWiRjuaafZq17/dwYJUjAqDDJPCQqUyCjEDGxVSOwZrq6WXyhVF+0VqVEOvF1hrgOeSHNtCChMXqGL8mH5TDPFrjYSONVWs2GDJpUo/LZfL01YkzouWMF1zdEwLTg8b+xMTpii2LqSAbyqx2aE6Xd/Vh03nRZxLY2SV3lfVpkaEMLHrl9ZVZTUbWXeO2NjS2+QLIAM7VmNyj6sN4mwnYmZopdMcacqZoF5DlGjZKExbb5XTwqSoMdLu40aDPapiQg1iXA9h7BQjG/sTg05YMTTuotDpqlCR/+tCSry7umR1DVFkEIyiWl8nOeO8/woiAYmN9TEuKduVJhVSVYhbJTlSGCocsWoHHh/iF0ZMCfVa/rSxnx6/9iZ7NGHPI/3r5GfQb+jBxC4xKQY2ULXh1MAj1jXC4GaarKhTUitJGmx0ZlDOaTuymCO83ynZCJJ+Kopik7tap6v6EGnJGYksB0Z08ACbKo5qTdP+ZW4mMqr1aF/0iVZLz9jRM2ZCM0JT9CwZuaSJtJ4/ljjRLcT+TJVhGHEff8UI4RedIKkwZYxLxsnn1ZWP/XZZHz4Cr3vw6ivYeymBey6xFGr5olD9kfztIH/flRKXFO9zefDLrlH6atv3i7l3HC+bKvdSd1/PUj3HvRczin/QqavOLSENBSZjWVVAohmJw1b2Mj14srk8a/RbViByQeSSk075S+f9FwgOBpYdRjCTHoDgEeZI61+2C5eI7eJxK6Io2IBu98uwAcUQj08qekXVP3+/wDxkKI3a1gknAlJG1en0sL1xuLFMjyHQ9KfT9WVIjY42xETDlG30RUWDW938Ac96zXaeFNDxGUc55aEwvENI8DqLbjqrzqGOUdMhNKA63OMfR33896+9y0WPTSpqSkmcI53xTqBtP7EiGlA1w/umzmrJhAGsRX5vxN5EkuRMqiZQOQF3itt2e8OK0ynwaCQkUEU/8gLIBbk6egdGwueNHzOmjaLUZEXD+czmFFtLKDTP7Kw9ncZhYmaOH0uXUkzjCj/GFRwfGtPqDdyUuLH8yzoi6zeSxgSMTExnOZuvX05ZqEWw2mt4+vW7LXa48W7We+gPJL2HTnM+OD1P+TvJ7A5I13w2li59fjVrlPUCjtpIKwzoPr3B/nYRIW5Gu/3wAKVBUUZDZZyJkXNhc3enQu9peFgMhQe3KcJl5O42fVofjDpnzZDHcE74g8S619mDB4ACgWBA2eELUWpz5BQ+zzP+zs54lztrPvmzQcK47Ef61ST92Jqr97yxMqyy18xzoodZbU8ta9QnfgoeZd1tdaPB+TKkdWK1f59QYHL+jVpqgM4jvw0jvziY3hFfh+J63/wP6UYwk9UKuO/6oCMbbpSiAttafP69qdw8d2gjbde8Db+a+TFtw1Fi7bsamLi9sVTsSGh3LFEvnVTuzhAcT49P4JXZszSw1XPb4Qdu14Eey2J/5trPBG41pVQ2ar+A4YpoKMkQHDNXaTSQ8gLDex32UnE+deFZvxXi0LhBg3XXmzDA36RdQ+p1FuJ56jRH+KfNeuDHaHjfCLbPvk2H6qDevyxO/wETtAPv')),true);
$untouched=$templates->save(0,'Wydruk komputerów','pdf','stary',$oldContent);
$edited=$templates->save(0,'Wydruk komputerów','pdf','mój',['mode'=>'per_order','body'=>'moja treść']);
$repo->saveSetting('print_templates_seeded',['version'=>3]);
$templates->ensureSchema();
check($templates->find($untouched)['content']['mode']==='list' && strpos($templates->find($untouched)['content']['body'],'notes.1.body')!==false,'Untouched old example upgraded');
check($templates->find($edited)['content']['body']==='moja treść' && count(array_keys(array_column($templates->all(),'name'),'Wydruk komputerów'))===2,'Edited template kept, nothing duplicated');
$templates->delete($edited);
$templates->restoreExamples();
rejects(fn()=>$templates->save(0,'','pdf','',[]),'Name required');
rejects(fn()=>$templates->save(0,'X','docx','',[]),'Unknown format rejected');
rejects(fn()=>$templates->save(0,'X','csv','',['columns'=>[]]),'CSV needs a column');
$id=$templates->save(0,'Mój PDF','pdf','opis',['mode'=>'list','page_size'=>'A9','margin'=>'99','body'=>'x','page_break'=>1]);
$saved=$templates->find($id);
check($saved['content']['page_size']==='A4' && $saved['content']['margin']==40.0 && $saved['content']['mode']==='list','Content normalized');
$copy=$templates->duplicate($id);
check($templates->find($copy)['name']==='Mój PDF (kopia)','Duplicate');
$templates->save($id,'Mój PDF 2','html','',['body'=>'y']);
check($templates->find($id)['format']==='html' && $templates->find($id)['name']==='Mój PDF 2','Update');

// Status aktywny / nieaktywny.
check($templates->find($id)['active']===true,'New template active by default');
$templates->setActive($id,false);
check($templates->find($id)['active']===false && !in_array($id,array_column($templates->active(),'id'),true) && in_array($id,array_column($templates->all(),'id'),true),'Inactive hidden from export menu, kept in list');
$templates->save($id,'Mój PDF 2','html','',['body'=>'y']);
check($templates->find($id)['active']===false,'Save without status keeps it');
check($templates->find($templates->duplicate($id))['active']===false,'Duplicate keeps status');
$templates->save($id,'Mój PDF 2','html','',['body'=>'y'],true);
check($templates->find($id)['active']===true,'Save with status changes it');
rejects(fn()=>$templates->setActive(99999,true),'Unknown template status rejected');
// Tabela sprzed statusów (bez kolumny active) dostaje kolumnę, szablony zostają aktywne.
$legacyDb=$reflection->newInstanceWithoutConstructor();
$legacyPdo=new PDO('sqlite::memory:'); $legacyPdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $legacyPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
foreach (['pdo'=>$legacyPdo,'config'=>['driver'=>'sqlite']] as $name=>$value) { $property=$reflection->getProperty($name); $property->setValue($legacyDb,$value); }
(new OrderRepository($legacyDb))->ensureSchema();
$legacyDb->query("CREATE TABLE om_print_templates (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(150) NOT NULL, format VARCHAR(10) NOT NULL, description VARCHAR(500) NOT NULL DEFAULT '', content_json LONGTEXT NOT NULL, position INTEGER NOT NULL DEFAULT 0, created_at VARCHAR(30) NOT NULL, updated_at VARCHAR(30) NOT NULL)");
$legacyDb->insert('om_print_templates',['name'=>'Stary','format'=>'pdf','content_json'=>'{"body":"x"}','created_at'=>'2026-10-01 10:00:00','updated_at'=>'2026-10-01 10:00:00']);
$legacy=new PrintTemplateRepository($legacyDb); $legacy->ensureSchema(); $legacy->ensureSchema();
check(in_array('Stary',array_column($legacy->active(),'name'),true),'Legacy table migrated, templates stay active');

// Zamówienie testowe przez normalizer (jak import).
$now=strtotime('2026-09-08T12:00:00Z');
$raw=['id'=>'ALG-1','status'=>'READY_FOR_PROCESSING','buyer'=>['login'=>'anna_k','email'=>'test@example.invalid','firstName'=>'Anna','lastName'=>'<b>Testowa</b>'],'payment'=>['finishedAt'=>'2026-09-08T10:00:00Z'],'delivery'=>['address'=>['firstName'=>'Anna','lastName'=>'Testowa','street'=>'Testowa 12/3','zipCode'=>'00-001','city'=>'Warszawa','countryCode'=>'PL','phoneNumber'=>'+48123123123'],'method'=>['name'=>'InPost Paczkomat']],'invoice'=>['required'=>true,'address'=>['street'=>'Firmowa 8','zipCode'=>'00-002','city'=>'Warszawa','countryCode'=>'PL','company'=>['name'=>'Firma Test','taxId'=>'5252674798']]],'lineItems'=>[['boughtAt'=>'2026-09-08T10:00:00Z','offer'=>['id'=>'offer-1','name'=>'=SUM(A1)','external'=>['id'=>'SKU-01']],'quantity'=>2,'price'=>['amount'=>'12.30']],['boughtAt'=>'2026-09-08T10:00:00Z','offer'=>['id'=>'offer-2','name'=>'Talerz','external'=>['id'=>'SKU-02']],'quantity'=>1,'price'=>['amount'=>'5.00']]],'summary'=>['totalToPay'=>['amount'=>'29.60','currency'=>'PLN']]];
$repo->registerAccount('allegro',1,'Sklep A');
$repo->import(1,OrderNormalizer::normalize('allegro',$raw,$now-86400,$now));
$raw2=$raw; $raw2['id']='ALG-2'; $raw2['lineItems']=[$raw['lineItems'][0]]; $raw2['summary']['totalToPay']['amount']='24.60';
$repo->import(1,OrderNormalizer::normalize('allegro',$raw2,$now-86400,$now));
$db->insert('om_shipments',['order_id'=>1,'carrier'=>'inpost','tracking'=>'620000111','weight'=>'1','state'=>'confirmed','created_at'=>'2026-09-08 12:00:00']);
$db->insert('om_documents',['order_id'=>1,'series_id'=>1,'kind'=>'invoice','number'=>'FV/1/09/2026','request_key'=>'k1','snapshot_json'=>'{}','created_at'=>'2026-09-08 12:00:00']);
$repo->saveSetting('seller',['name'=>'Sprzedawca & Syn','nip'=>'123']);

$repo->addNote(1,'Specyfikacja zamówienia długa wersja','automat: Spec');
$repo->addNote(1,"Specyfikacja\nAMD Ryzen 7 9800X3D /\nnVidia RTX 5070 12GB / 32GB DDR5 /\n<b>WINDOWS</b> /",'automat: Spec');
$service=new OrderPrintTemplateService($repo);
rejects(fn()=>$service->contexts([]),'Empty selection rejected');
rejects(fn()=>$service->contexts([999]),'Unknown orders rejected');
$contexts=$service->contexts([2,1,1,999]);
check(count($contexts)===2 && $contexts[0]['order']['number']==='ALG-2','Selection order kept, duplicates and unknown skipped');
$c=$contexts[1];
check($c['order']['total']==='29,60' && $c['order']['items_total']==='29,60' && $c['order']['shipping_cost']==='0,00','Money fields');
check($c['order']['date']==='08.09.2026 12:00','Order date in Europe/Warsaw');
check($c['shipping']['postal_code']==='00-001' && $c['shipping']['street_full']==='Testowa 12/3' && $c['shipping']['method']==='InPost Paczkomat','Shipping fields');
check($c['invoice']['required']===true && $c['invoice']['nip']==='5252674798' && $c['invoice']['company']==='Firma Test','Invoice fields');
check($c['order']['tracking_numbers']==='620000111' && $c['order']['invoice_number']==='FV/1/09/2026','Shipment and document fields');
check($c['items'][1]['unit_price']==='5,00' && $c['items'][0]['total_price']==='24,60' && $c['buyer']['login']==='anna_k','Item fields and raw buyer login');
$globals=$service->globals($contexts,'Tester','T');
check($globals['print']['count']===2 && $globals['print']['items_quantity']===5 && $globals['print']['total']==='54,20 PLN','Print totals');
check($globals['products'][0]['sku']==='SKU-01' && $globals['products'][0]['quantity']===4 && $globals['products'][0]['orders']==='ALG-2, ALG-1','Picking list aggregates by SKU');
check($globals['seller']['name']==='Sprzedawca & Syn','Seller fields');

// Silnik szablonów.
$scopes=[$globals,$c];
check($service->render('{{buyer.name}}',$scopes,true)===htmlspecialchars($c['buyer']['name'],ENT_QUOTES),'Values escaped in HTML');
check(strpos($service->render('{{buyer.name}}',$scopes,true),'<b>')===false,'Marketplace data cannot inject tags');
check($service->render('{{#each items}}{{loop.index}}:{{item.sku}}{{#unless loop.last}},{{/unless}}{{/each}}',$scopes,true)==='1:SKU-01,2:SKU-02','Each loop with loop info');
check($service->render('{{#if invoice.required}}FV{{else}}PAR{{/if}}|{{#if payment.cod}}COD{{else}}NO{{/if}}',$scopes,true)==='FV|NO','If / else');
check($service->render('{{#each items as p}}{{p.name|lower|truncate:3}}{{/each}}',$scopes,false)==='=su…tal…','Alias and filters');
check($service->render('{{missing.field|default:"brak danych"}}{{raw.buyer.login|upper}}',$scopes,false)==='brak danychANNA_K','Default filter and raw access');
check($service->render('{{#each orders}}[{{order.number}}:{{#each items}}{{item.quantity}}{{/each}}]{{/each}}',[$globals],false)==='[ALG-2:2][ALG-1:21]','Nested loops over orders');
check($service->render('a {{/if}} {{#each items}}x',$scopes,false)==='a {{/if}} xx','Unbalanced tags degrade gracefully');
check($service->render('{{x|replace:"a":"o"|lines:" / "}}|{{x|lines}}',[['x'=>' a / WiFi/BT: c /']],false)==="o\nWiFi/BT: c|a\nWiFi\nBT: c",'replace and lines filters with colons in data');
check($service->render('{{x|lines:" / "}}',[['x'=>"Spec /\r\nA / B /\n\nC/D /"]],false)==="Spec\nA\nB\nC/D",'lines filter handles existing line breaks and trailing slashes');
check($service->render('{{notes.1.body}}',[['notes'=>[['body'=>'1'],['body'=>'2']]]],false)==='2','List index access');
check($service->render('{{shipping.address_lines|nl2br}}',$scopes,true)===nl2br(htmlspecialchars($c['shipping']['address_lines'],ENT_QUOTES),false),'nl2br after escaping');

// Wszystkie przykłady renderują się na danych testowych i syntetycznych.
foreach ([$contexts,$service->sampleContexts()] as $set) {
    $g=$service->globals($set,'Tester','T');
    foreach ($templates->all() as $template) {
        if ($template['format']==='csv') { $rows=$service->csvRows($template,$set,$g); check(count($rows)>1,'CSV example renders: '.$template['name']); continue; }
        $html=$service->renderDocument($template,$set,$g,'print','NONCE');
        check(strpos($html,'{{')===false,'No unresolved tags: '.$template['name']);
        check(strpos($html,'nonce="NONCE"')!==false && strpos($html,'@page{size:')!==false,'Print document wrapper: '.$template['name']);
    }
}
$label=$templates->all()[array_search('Etykieta adresowa 100×150',array_column($templates->all(),'name'),true)];
check(strpos($service->renderDocument($label,$contexts,$globals,'file'),'size:100mm 150mm')!==false,'Label page size');
check(substr_count($service->renderDocument($label,$contexts,$globals,'file'),'class="sc-sheet sc-break"')===2,'One sheet per order');
check(strpos($service->renderDocument($label,$contexts,$globals,'file'),'<script')===false,'HTML file has no injected script');

// Code 128: tabela wzorów i dekodowanie wygenerowanego SVG z powrotem do tekstu.
$table=OrderPrintTemplateService::CODE128;
check(count($table)===107 && count(array_unique($table))===107,'Code128 table complete and unique');
foreach ($table as $code=>$pattern) { check(array_sum(str_split($pattern))===($code===106?13:11),'Code128 pattern width '.$code); }
check($table[104]==='211214' && $table[106]==='2331112' && $table[33]==='111323','Code128 start B, stop and "A"');
$decode=static function (string $uri) use ($table): string {
    $svg=base64_decode(substr($uri,strlen('data:image/svg+xml;base64,')));
    preg_match_all('/M(\d+) 0h(\d+)/',$svg,$m,PREG_SET_ORDER);
    $widths=[]; $previousEnd=null;
    foreach ($m as $bar) { if ($previousEnd!==null) { $widths[]=(int)$bar[1]-$previousEnd; } $widths[]=(int)$bar[2]; $previousEnd=(int)$bar[1]+(int)$bar[2]; }
    $codes=[]; $flip=array_flip($table);
    for ($i=0; $i+6<=count($widths)-7; $i+=6) { $codes[]=$flip[implode('',array_slice($widths,$i,6))]; }
    check(implode('',array_slice($widths,-7))==='2331112','Barcode ends with stop');
    $start=array_shift($codes); $checksum=array_pop($codes);
    $sum=$start; foreach ($codes as $i=>$code) { $sum+=($i+1)*$code; }
    check($start===104 && $sum%103===$checksum,'Barcode start and checksum');
    return implode('',array_map(static function ($code) { return chr($code+32); },$codes));
};
check($decode(OrderPrintTemplateService::barcodeDataUri('ALG-1/x 9'))==='ALG-1/x 9','Barcode round-trip');
check($decode($c['order']['barcode_url'])==='ALG-1' && $decode($c['order']['id_barcode_url'])==='1','Order barcode fields');
check(OrderPrintTemplateService::barcodeDataUri('')==='' && OrderPrintTemplateService::barcodeDataUri('ąę')==='','Empty barcode for unsupported text');
$computerTemplate=array_values(array_filter($templates->all(),static function ($t) { return $t['name']==='Wydruk komputerów'; }))[0];
$computerHtml=$service->renderDocument($computerTemplate,$contexts,$globals,'print','N');
check(strpos($computerHtml,'data:image/svg')===false && substr_count($computerHtml,'class="card"')===2 && strpos($computerHtml,'ALG-1 / #1</div>')!==false,'Computer cards: no barcode, number with internal ID');
check(strpos($computerHtml,"<div class=\"spec\">AMD Ryzen 7 9800X3D\nnVidia RTX 5070 12GB\n32GB DDR5\n&lt;b&gt;WINDOWS&lt;/b&gt;</div>")!==false && substr_count($computerHtml,'class="spec"')===1 && strpos($computerHtml,'długa wersja')===false,'Spec from the second note only, one line per slash, escaped');
check(strpos($computerHtml,'grid-auto-rows:90mm')!==false && strpos($computerHtml,'size:210mm 297mm')!==false,'Nine cards per A4 page');

// CSV.
$csvTemplate=['name'=>'c','format'=>'csv','content'=>PrintTemplateRepository::normalizeContent('csv',['rows'=>'item','separator'=>';','header_row'=>1,'bom'=>1,'columns'=>[['header'=>'Nr','value'=>'{{order.number}}'],['header'=>'Nazwa','value'=>'{{item.name}}'],['header'=>'Kwota','value'=>'-{{item.unit_price}}']]])];
$rows=$service->csvRows($csvTemplate,$contexts,$globals);
check(count($rows)===4 && $rows[0]===['Nr','Nazwa','Kwota'],'CSV item rows with header');
check($rows[1][1]==="'=SUM(A1)" && $rows[1][2]==='-12,30','CSV formula injection neutralized, negative numbers kept');
$csv=$service->csv($csvTemplate,$contexts,$globals);
check(strncmp($csv,"\xEF\xBB\xBF",3)===0 && strpos($csv,"Nr;Nazwa;Kwota\n")!==false,'CSV BOM and separator');

// Widok edytora i listy kompiluje się w Smarty.
$smarty=App\Core\SmartyFactory::create();
$compileDir=sys_get_temp_dir().'/sc_pt_test_'.bin2hex(random_bytes(4)); mkdir($compileDir);
$smarty->setCompileDir($compileDir);
$view=['edit'=>null,'catalog'=>OrderPrintTemplateService::catalog(),'previewOrders'=>[['id'=>1,'external_id'=>'ALG-1','buyer_name'=>'Anna']],'formats'=>PrintTemplateRepository::FORMATS,'pageSizes'=>PrintTemplateRepository::PAGE_SIZES];
$smarty->assign(['printTemplates'=>$templates->all(),'printTemplateView'=>$view,'canWrite'=>true,'csrf'=>'t']);
$listHtml=$smarty->fetch('orders/print_templates.tpl');
check(strpos($listHtml,'Lista kompletacyjna')!==false && strpos($listHtml,'print_template_delete')!==false,'List view renders');
check(substr_count($listHtml,'name="operation" value="print_template_active"')===count($templates->all()) && substr_count($listHtml,'pt-card om-panel is-inactive')===count($templates->all())-count($templates->active()) && count($templates->active())<count($templates->all()),'Status toggles render, inactive cards marked');
foreach ([$templates->all()[0],$csvTemplate+['id'=>0,'description'=>'']] as $edit) {
    $view['edit']=$edit; $smarty->assign('printTemplateView',$view);
    $editHtml=$smarty->fetch('orders/print_templates.tpl');
    check(strpos($editHtml,'data-pt-editor')!==false && strpos($editHtml,'{{order.number}}')!==false && strpos($editHtml,'data-pt-insert="item.sku"')!==false,'Editor renders: '.$edit['name']);
}
array_map('unlink',glob($compileDir.'/*')?:[]); rmdir($compileDir);

echo "OK: $checks checks\n";
