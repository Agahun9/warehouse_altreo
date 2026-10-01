{assign var=ptEdit value=$printTemplateView.edit|default:null}
{if !$ptEdit}
<div class="om-section-heading"><div><h2>Szablony druku</h2><p>Zaznacz zamówienia na liście, wybierz szablon i wyeksportuj je do PDF, HTML albo CSV. Szablony możesz dowolnie tworzyć, kopiować i edytować.</p></div>
  {if $canWrite}<div class="pt-head-actions">
    <form method="post" action="?controller=orders&action=save"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="print_template_restore"><input type="hidden" name="tab" value="templates"><button class="om-btn" title="Dodaje brakujące przykładowe szablony, nie zmienia Twoich"><i class="bi bi-arrow-counterclockwise"></i> Przywróć przykłady</button></form>
    <details class="pt-new-menu"><summary class="om-btn om-primary"><i class="bi bi-plus-lg"></i> Nowy szablon</summary><div>
      <a href="?controller=orders&tab=templates&template=new&format=pdf"><span class="pt-format pt-format-pdf">PDF</span><span><strong>Dokument PDF</strong><small>Wydruk lub zapis do PDF — strony A4, A5, A6, etykiety</small></span></a>
      <a href="?controller=orders&tab=templates&template=new&format=html"><span class="pt-format pt-format-html">HTML</span><span><strong>Plik HTML</strong><small>Plik do pobrania, wysłania mailem lub archiwizacji</small></span></a>
      <a href="?controller=orders&tab=templates&template=new&format=csv"><span class="pt-format pt-format-csv">CSV</span><span><strong>Arkusz CSV</strong><small>Kolumny do Excela, księgowości lub hurtowni</small></span></a>
    </div></details>
  </div>{/if}
</div>
<div class="pt-how om-panel om-pad"><i class="bi bi-lightbulb"></i><div><strong>Jak eksportować?</strong> W zakładce <a href="orders.php">Zamówienia</a> zaznacz zamówienia, na pasku akcji wybierz <em>Szablon druku / eksport</em> i kliknij <em>Eksportuj</em>. PDF otworzy się w nowej karcie z oknem druku — wybierz „Zapisz jako PDF”. HTML i CSV pobiorą się jako plik. Pojedyncze zamówienie wydrukujesz też z karty zamówienia (ikona drukarki).</div></div>
<div class="pt-grid">
  {foreach $printTemplates as $pt}
  <article class="pt-card om-panel{if !$pt.active} is-inactive{/if}">
    <div class="pt-card-top"><span class="pt-format pt-format-{$pt.format|escape}">{$pt.format_label|escape}</span><span class="pt-card-meta">{if $pt.format eq 'csv'}{if $pt.content.rows eq 'item'}wiersz na pozycję{else}wiersz na zamówienie{/if} · {$pt.content.columns|count} kol.{else}{if $pt.content.mode eq 'list'}zestawienie{else}strona na zamówienie{/if} · {$printTemplateView.pageSizes[$pt.content.page_size]|default:$pt.content.page_size|escape}{if $pt.content.orientation eq 'landscape'} poziomo{/if}{/if}</span>
      {if $canWrite}<form method="post" action="?controller=orders&action=save" class="pt-status-form"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="print_template_active"><input type="hidden" name="tab" value="templates"><input type="hidden" name="template_id" value="{$pt.id}"><input type="hidden" name="active" value="{if $pt.active}0{else}1{/if}"><button class="pt-status{if $pt.active} is-active{/if}" title="{if $pt.active}Kliknij, aby wyłączyć — szablon zniknie z eksportu zamówień{else}Kliknij, aby włączyć — szablon pojawi się w eksporcie zamówień{/if}"><i class="bi {if $pt.active}bi-check-circle-fill{else}bi-pause-circle{/if}"></i> {if $pt.active}Aktywny{else}Nieaktywny{/if}</button></form>{else}<span class="pt-status{if $pt.active} is-active{/if}"><i class="bi {if $pt.active}bi-check-circle-fill{else}bi-pause-circle{/if}"></i> {if $pt.active}Aktywny{else}Nieaktywny{/if}</span>{/if}
    </div>
    <h3>{if $canWrite}<a href="?controller=orders&tab=templates&template={$pt.id}">{$pt.name|escape}</a>{else}{$pt.name|escape}{/if}</h3>
    <p>{$pt.description|default:'Bez opisu.'|escape}</p>
    <div class="pt-card-foot"><small>Zmieniono {$pt.updated_at|escape} UTC</small>
      {if $canWrite}<div class="pt-card-actions">
        <a class="om-btn om-small" href="?controller=orders&tab=templates&template={$pt.id}"><i class="bi bi-pencil"></i> Edytuj</a>
        <form method="post" action="?controller=orders&action=save"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="print_template_duplicate"><input type="hidden" name="tab" value="templates"><input type="hidden" name="template_id" value="{$pt.id}"><button class="om-icon-btn" title="Duplikuj" aria-label="Duplikuj {$pt.name|escape}"><i class="bi bi-copy"></i></button></form>
        <form method="post" action="?controller=orders&action=save" data-pt-confirm="Usunąć szablon „{$pt.name|escape}”? Tej operacji nie można cofnąć."><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="print_template_delete"><input type="hidden" name="tab" value="templates"><input type="hidden" name="template_id" value="{$pt.id}"><button class="om-icon-btn pt-danger" title="Usuń" aria-label="Usuń {$pt.name|escape}"><i class="bi bi-trash"></i></button></form>
      </div>{/if}
    </div>
  </article>
  {foreachelse}
  <div class="om-panel om-pad om-empty"><i class="bi bi-file-earmark-ruled"></i><h3>Brak szablonów</h3><p>Utwórz nowy szablon albo przywróć przykładowe.</p></div>
  {/foreach}
</div>
{else}
{assign var=ptc value=$ptEdit.content}
<div class="pt-editor-head">
  <a class="om-icon-btn" href="?controller=orders&tab=templates" title="Wróć do listy szablonów" aria-label="Wróć"><i class="bi bi-arrow-left"></i></a>
  <div><small class="om-eyebrow">SZABLON DRUKU</small><h2>{if $ptEdit.id}{$ptEdit.name|escape}{else}Nowy szablon{/if}</h2></div>
  {if $ptEdit.id && $canWrite}<div class="pt-head-actions">
    <form method="post" action="?controller=orders&action=save"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="print_template_duplicate"><input type="hidden" name="tab" value="templates"><input type="hidden" name="template_id" value="{$ptEdit.id}"><button class="om-btn"><i class="bi bi-copy"></i> Duplikuj</button></form>
    <form method="post" action="?controller=orders&action=save" data-pt-confirm="Usunąć ten szablon? Tej operacji nie można cofnąć."><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="print_template_delete"><input type="hidden" name="tab" value="templates"><input type="hidden" name="template_id" value="{$ptEdit.id}"><button class="om-btn om-danger-outline"><i class="bi bi-trash"></i> Usuń</button></form>
  </div>{/if}
</div>
<form method="post" action="?controller=orders&action=save" class="pt-editor" data-pt-editor data-preview-url="index.php?controller=orders&action=printtemplatepreview">
  <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="print_template_save"><input type="hidden" name="tab" value="templates"><input type="hidden" name="template_id" value="{$ptEdit.id}">
  <div class="pt-editor-main">
    <section class="om-panel om-pad pt-basics">
      <label class="pt-wide">Nazwa szablonu<input name="name" maxlength="150" required value="{$ptEdit.name|escape}" placeholder="Np. Potwierdzenie zamówienia" {if !$canWrite}disabled{/if}></label>
      <label>Format<select name="format" data-pt-format {if !$canWrite}disabled{/if}>{foreach $printTemplateView.formats as $fKey=>$fLabel}<option value="{$fKey}" {if $ptEdit.format eq $fKey}selected{/if}>{$fLabel}</option>{/foreach}</select></label>
      <label class="pt-check pt-active-toggle"><input type="checkbox" name="active" value="1" {if $ptEdit.active|default:true}checked{/if} {if !$canWrite}disabled{/if}> Aktywny — widoczny w eksporcie zamówień</label>
      <label class="pt-wide">Opis (widoczny na liście szablonów)<input name="description" maxlength="500" value="{$ptEdit.description|escape}" {if !$canWrite}disabled{/if}></label>
    </section>

    <section class="om-panel om-pad pt-doc-settings" data-pt-for="pdf html">
      <h3><i class="bi bi-file-earmark-richtext"></i> Układ dokumentu</h3>
      <div class="pt-settings-grid">
        <label>Tryb<select name="mode" data-pt-mode {if !$canWrite}disabled{/if}><option value="per_order" {if $ptc.mode|default:'per_order' eq 'per_order'}selected{/if}>Osobno każde zamówienie</option><option value="list" {if $ptc.mode|default:'' eq 'list'}selected{/if}>Jedno zestawienie wszystkich (pętla orders)</option></select></label>
        <label>Rozmiar strony<select name="page_size" {if !$canWrite}disabled{/if}>{foreach $printTemplateView.pageSizes as $psKey=>$psLabel}<option value="{$psKey}" {if ($ptc.page_size|default:'A4') eq $psKey}selected{/if}>{$psLabel}</option>{/foreach}</select></label>
        <label>Orientacja<select name="orientation" {if !$canWrite}disabled{/if}><option value="portrait" {if ($ptc.orientation|default:'portrait') eq 'portrait'}selected{/if}>Pionowo</option><option value="landscape" {if ($ptc.orientation|default:'') eq 'landscape'}selected{/if}>Poziomo</option></select></label>
        <label>Margines (mm)<input name="margin" inputmode="decimal" value="{$ptc.margin|default:12}" {if !$canWrite}disabled{/if}></label>
        <label class="pt-check" data-pt-for-mode="per_order"><input type="checkbox" name="page_break" value="1" {if $ptc.page_break|default:true}checked{/if} {if !$canWrite}disabled{/if}> Każde zamówienie na nowej stronie</label>
      </div>
      <label class="pt-code-label">Treść szablonu (HTML)<textarea name="body" rows="22" spellcheck="false" class="pt-code" data-pt-target {if !$canWrite}disabled{/if}>{$ptc.body|default:''|escape}</textarea></label>
      <details class="pt-css" {if $ptc.css|default:'' neq ''}open{/if}><summary><i class="bi bi-palette"></i> Style CSS</summary><textarea name="css" rows="10" spellcheck="false" class="pt-code" data-pt-target {if !$canWrite}disabled{/if}>{$ptc.css|default:''|escape}</textarea></details>
    </section>

    <section class="om-panel om-pad pt-csv-settings" data-pt-for="csv">
      <h3><i class="bi bi-filetype-csv"></i> Kolumny CSV</h3>
      <div class="pt-settings-grid">
        <label>Wiersze<select name="rows" {if !$canWrite}disabled{/if}><option value="order" {if ($ptc.rows|default:'order') eq 'order'}selected{/if}>Jeden wiersz na zamówienie</option><option value="item" {if ($ptc.rows|default:'') eq 'item'}selected{/if}>Jeden wiersz na pozycję (produkt)</option></select></label>
        <label>Separator<select name="separator" {if !$canWrite}disabled{/if}>{foreach [';'=>'Średnik ; (Excel PL)',','=>'Przecinek ,','tab'=>'Tabulator','|'=>'Pionowa kreska |'] as $sepKey=>$sepLabel}<option value="{$sepKey|escape}" {if ($ptc.separator|default:';') eq $sepKey}selected{/if}>{$sepLabel}</option>{/foreach}</select></label>
        <label class="pt-check"><input type="checkbox" name="header_row" value="1" {if $ptc.header_row|default:true}checked{/if} {if !$canWrite}disabled{/if}> Wiersz nagłówków</label>
        <label class="pt-check"><input type="checkbox" name="bom" value="1" {if $ptc.bom|default:true}checked{/if} {if !$canWrite}disabled{/if}> UTF-8 z BOM (polskie znaki w Excelu)</label>
      </div>
      <div class="pt-columns" data-pt-columns>
        <div class="pt-columns-head"><span></span><span>Nagłówek kolumny</span><span>Wartość — pola i tekst, np. {literal}{{shipping.postal_code}} {{shipping.city}}{/literal}</span><span></span></div>
        {foreach $ptc.columns|default:[] as $col}
        <div class="pt-column" data-pt-column><i class="bi bi-grip-vertical pt-grip" draggable="true" title="Przeciągnij, aby zmienić kolejność"></i><input name="csv_header[]" value="{$col.header|escape}" placeholder="Nagłówek" {if !$canWrite}disabled{/if}><input name="csv_value[]" value="{$col.value|escape}" class="pt-code" data-pt-target placeholder="{literal}{{order.number}}{/literal}" {if !$canWrite}disabled{/if}>{if $canWrite}<button type="button" class="om-icon-btn" data-pt-remove-column title="Usuń kolumnę" aria-label="Usuń kolumnę"><i class="bi bi-x-lg"></i></button>{/if}</div>
        {/foreach}
      </div>
      {if $canWrite}<button type="button" class="om-btn om-small" data-pt-add-column><i class="bi bi-plus-lg"></i> Dodaj kolumnę</button>{/if}
      <template data-pt-column-template><div class="pt-column" data-pt-column><i class="bi bi-grip-vertical pt-grip" draggable="true" title="Przeciągnij, aby zmienić kolejność"></i><input name="csv_header[]" placeholder="Nagłówek"><input name="csv_value[]" class="pt-code" data-pt-target placeholder="{literal}{{order.number}}{/literal}"><button type="button" class="om-icon-btn" data-pt-remove-column title="Usuń kolumnę" aria-label="Usuń kolumnę"><i class="bi bi-x-lg"></i></button></div></template>
    </section>

    <section class="om-panel om-pad pt-preview">
      <div class="pt-preview-head"><h3><i class="bi bi-eye"></i> Podgląd</h3>
        <label>Dane<select data-pt-preview-source><option value="sample">Przykładowe zamówienia (dane testowe)</option>{if $printTemplateView.previewOrders}<option value="recent">5 ostatnich zamówień</option><optgroup label="Zamówienie">{foreach $printTemplateView.previewOrders as $po}<option value="{$po.id}">#{$po.id} · {$po.external_id|escape|truncate:24:'…'} · {$po.buyer_name|escape|truncate:28:'…'}</option>{/foreach}</optgroup>{/if}</select></label>
        <button type="button" class="om-btn om-small" data-pt-preview-refresh><i class="bi bi-arrow-clockwise"></i> Odśwież</button>
      </div>
      <div class="pt-preview-status" data-pt-preview-status></div>
      <iframe class="pt-preview-frame" data-pt-preview-frame sandbox title="Podgląd szablonu"></iframe>
      <div class="pt-preview-table" data-pt-preview-table hidden></div>
    </section>

    {if $canWrite}<div class="pt-editor-footer"><a class="om-btn" href="?controller=orders&tab=templates">Anuluj</a><button class="om-btn om-primary"><i class="bi bi-check2"></i> Zapisz szablon</button></div>{/if}
  </div>

  <aside class="pt-palette om-panel" aria-label="Pola zamówienia">
    <div class="pt-palette-head"><strong>Pola zamówienia</strong><small>Kliknij, aby wstawić w miejscu kursora</small><input type="search" placeholder="Szukaj pola…" data-pt-field-search aria-label="Szukaj pola"></div>
    <div class="pt-palette-body">
      {foreach $printTemplateView.catalog as $groupName=>$fields}
      <details class="pt-field-group" {if $fields@first}open{/if}><summary>{$groupName|escape}</summary>
        {foreach $fields as $field}<button type="button" class="pt-field" data-pt-insert="{$field[0]|escape}" title="{$field[1]|escape}{if $field[2] neq ''} — np. {$field[2]|escape}{/if}"><code>{$field[0]|escape}</code><span>{$field[1]|escape}</span></button>{/foreach}
      </details>
      {/foreach}
    </div>
    <details class="pt-syntax"><summary><i class="bi bi-question-circle"></i> Składnia szablonu</summary>
{literal}<dl>
  <dt>{{order.number}}</dt><dd>wstawia wartość pola (bezpiecznie, bez HTML)</dd>
  <dt>{{#each items}} … {{/each}}</dt><dd>powtarza blok dla każdej pozycji; w środku {{item.name}}, {{loop.index}}</dd>
  <dt>{{#each orders}} … {{/each}}</dt><dd>w trybie „zestawienie” — pętla po zaznaczonych zamówieniach; w środku działają wszystkie pola zamówienia</dd>
  <dt>{{#if invoice.required}} … {{else}} … {{/if}}</dt><dd>warunek; {{#unless pole}} — odwrotność</dd>
  <dt>{{item.name|upper}}</dt><dd>filtry: upper, lower, trim, truncate:40, default:"brak", nl2br, replace:"co":"na co", lines:"/" (dzieli tekst na linie)</dd>
  <dt>{{notes.1.body}}</dt><dd>konkretny element listy bez pętli (od 0: notes.0 = pierwsza notatka)</dd>
  <dt>{{raw.buyer.login}}</dt><dd>dowolne pole z oryginalnych danych źródła (patrz „Dane źródłowe” w zamówieniu)</dd>
</dl>{/literal}
    </details>
  </aside>
</form>
{/if}
