{assign var=documentKindLabels value=['invoice'=>'Faktura','receipt'=>'Paragon','invoice_correction'=>'Korekta faktury','receipt_correction'=>'Korekta paragonu']}
<div class="om-section-heading om-docs-intro"><div><span class="om-eyebrow">SPRZEDAŻ · DOKUMENTY</span><h2>Dokumenty sprzedaży</h2><p>Zarządzaj seriami i danymi sprzedawcy. Wystawione dokumenty znajdziesz poniżej.</p></div><span class="om-chip">A4 · podgląd i wydruk</span></div>
<section class="om-docs-guide">
  <div class="om-docs-guide-icon"><i class="bi bi-printer"></i></div>
  <div><strong>Druk paragonów</strong><p>Drukarkę przypisujesz do serii paragonów. Faktury i korekty nie trafiają do kolejki Posnet.</p></div>
  <div class="om-docs-guide-modes"><span><b>PRODUKCJA</b> fiskalizuje sprzedaż</span><span><b>SANDBOX</b> drukuje niefiskalnie</span></div>
  <a class="om-btn om-small" href="?controller=orders&tab=printing">Ustawienia drukarek <i class="bi bi-arrow-right"></i></a>
</section>
<div class="om-docs-workspace">
<section class="om-panel om-pad om-docs-series-panel">
  <div class="om-docs-panel-title"><div><span class="om-eyebrow">01 · NUMERACJA</span><h3>Serie dokumentów</h3><p>Każda seria ma własny licznik i przypisanie drukarki.</p></div><span class="om-chip">{$series|count} {if $series|count eq 1}seria{elseif $series|count >= 2 and $series|count <= 4}serie{else}serii{/if}</span></div>
  <div class="om-series-list">
  {foreach $series as $s}
    <details class="om-series-item">
      <summary>
        <span class="om-series-summary-main"><span class="om-series-badge om-series-badge-{$s.kind}">{$documentKindLabels[$s.kind]|default:$s.kind}</span><strong>{if !empty($s.numbering.color)}<span class="om-series-color" style="background:{$s.numbering.color|escape}" aria-hidden="true"></span>{/if}{$s.name|escape}</strong><span class="om-series-pattern">{$s.pattern|escape}</span></span>
        <span class="om-series-summary-meta"><span class="om-series-next">Następny numer <b>{$s.next_number}</b></span>{if $s.document_count}<span class="om-chip">{$s.document_count} dok.</span>{else}<span class="om-chip om-chip-idle">nieużywana</span>{/if}<i class="bi bi-chevron-down oc-chevron"></i></span>
      </summary>
      <div class="om-series-body">
        <form class="om-form" method="post" action="?controller=orders&action=save">
          <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="series_update"><input type="hidden" name="tab" value="documents"><input type="hidden" name="series_id" value="{$s.id}">
          <label>Nazwa serii<input name="name" value="{$s.name|escape}" maxlength="100" required {if !$canWrite}disabled{/if}></label>
          <label>Typ<select name="kind" {if !$canWrite}disabled{/if}><option value="invoice" {if $s.kind eq 'invoice'}selected{/if}>Faktura</option><option value="receipt" {if $s.kind eq 'receipt'}selected{/if}>Paragon</option><option value="invoice_correction" {if $s.kind eq 'invoice_correction'}selected{/if}>Korekta faktury</option><option value="receipt_correction" {if $s.kind eq 'receipt_correction'}selected{/if}>Korekta paragonu</option></select></label>
          <label>Wzór numeru<input name="pattern" value="{$s.pattern|escape}" maxlength="100" required {if !$canWrite}disabled{/if}></label>
          {include file='orders/series_numbering.tpl' numbering=$s.numbering|default:[]}
          <label>Następny numer<input name="next_number" type="number" min="1" max="100000000" value="{$s.next_number}" required {if !$canWrite}disabled{/if}></label>
          {if $s.kind eq 'receipt'}<label>Drukarka fiskalna<select name="fiscal_printer_id" {if !$canWrite}disabled{/if}><option value="0" {if !$s.effective_printer_id}selected{/if}>Bez automatycznego druku</option>{foreach $printFiscalPrinters as $printer}{if $printer.enabled or $printer.id eq $s.effective_printer_id}<option value="{$printer.id}" {if $printer.id eq $s.effective_printer_id}selected{/if}>{$printer.name|escape} · {$printer.host|escape}:{$printer.port} · {if $printer.enabled}{if $printer.environment eq 'production'}PRODUKCJA{else}SANDBOX{/if}{else}nieaktywna — wybierz inną{/if}</option>{/if}{/foreach}</select></label>{/if}
          {if $s.document_count}<small class="om-muted">Seria ma wystawione dokumenty — nie można zmienić typu ani cofnąć licznika.</small>{/if}
          {if $canWrite}<button class="om-btn om-small">Zapisz serię</button>{/if}
        </form>
        {if $canWrite}
        <form class="om-series-delete" method="post" action="?controller=orders&action=save" {if !$s.document_count}data-confirm-action="Usunąć serię „{$s.name|escape}”? Tej operacji nie można cofnąć."{/if}>
          <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="series_delete"><input type="hidden" name="tab" value="documents"><input type="hidden" name="series_id" value="{$s.id}">
          {if $s.document_count}<button class="om-btn om-small" type="button" disabled title="Seria ma wystawione dokumenty — nie można jej usunąć"><i class="bi bi-trash"></i> Usuń serię</button>
          {else}<button class="om-btn om-small om-danger-outline" type="submit"><i class="bi bi-trash"></i> Usuń serię</button>{/if}
        </form>
        {/if}
      </div>
    </details>
  {foreachelse}<p class="om-muted">Dodaj osobne serie dla faktur, paragonów i korekt.</p>{/foreach}
  </div>
  {if $canWrite}<details class="om-fiscal-add om-docs-add-series"><summary><i class="bi bi-plus-lg"></i> Dodaj nową serię</summary><form class="om-form om-top" method="post" action="?controller=orders&action=save"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="series"><input type="hidden" name="tab" value="documents"><label>Nazwa serii<input name="name" placeholder="Faktury — sklep główny" required></label><label>Typ<select name="kind"><option value="invoice">Faktura</option><option value="receipt">Paragon</option><option value="invoice_correction">Korekta faktury</option><option value="receipt_correction">Korekta paragonu</option></select></label><label>Wzór numeru<input name="pattern" value="FV/{literal}{YYYY}/{MM}/{N}{/literal}" required></label>{include file='orders/series_numbering.tpl' numbering=[]}<label>Pierwszy / kolejny numer<input name="next_number" type="number" min="1" value="1" required></label><label>Drukarka dla serii paragonów<select name="fiscal_printer_id"><option value="0">Bez automatycznego druku</option>{foreach $printFiscalPrinters as $printer}{if $printer.enabled}<option value="{$printer.id}">{$printer.name|escape} · {$printer.host|escape}:{$printer.port}</option>{/if}{/foreach}</select></label><small class="om-muted">Dla faktur i korekt pozostaw „Bez automatycznego druku”. {literal}{N} — kolejny numer · {YYYY} — rok · {MM} — miesiąc.{/literal}</small><button class="om-btn om-primary">Dodaj serię</button></form></details>{/if}
</section>
<section class="om-panel om-pad om-docs-seller-panel"><details class="om-seller-details"><summary><span class="om-seller-icon"><i class="bi bi-building"></i></span><span class="om-seller-summary"><span class="om-eyebrow">02 · SPRZEDAWCA</span><strong>Dane sprzedawcy</strong><small>{$seller.name|default:'Uzupełnij nazwę firmy'|escape}</small><small>NIP {$seller.nip|default:'—'|escape}</small></span><span class="om-seller-edit">Edytuj <i class="bi bi-chevron-down"></i></span></summary><div class="om-seller-body"><form class="om-form" method="post" action="?controller=orders&action=save"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="seller"><input type="hidden" name="tab" value="documents"><label>Nazwa firmy<input name="name" value="{$seller.name|escape}" required></label><label>NIP<input name="nip" value="{$seller.nip|escape}" required></label><label>Adres<textarea name="address" required>{$seller.address|escape}</textarea></label><label>Rachunek bankowy<input name="bank" value="{$seller.bank|escape}"></label>{if $canWrite}<button class="om-btn om-primary">Zapisz dane sprzedawcy</button>{/if}</form><p class="om-muted om-top">Zmiany dotyczą tylko nowych dokumentów.</p></div></details></section>
</div>
<section class="om-panel om-top">
  <div class="om-panel-heading om-doc-heading">
    <div><span class="om-eyebrow">03 · HISTORIA</span><h3>Ostatnie dokumenty</h3><p>Podgląd A4, edycja i powiązane zamówienie w jednym miejscu.</p></div>
    <form method="get" class="om-doc-filter" data-series-filter-form><input type="hidden" name="controller" value="orders"><input type="hidden" name="tab" value="documents"><label>Seria<select name="series_id"><option value="0">Wszystkie serie</option>{foreach $series as $s}<option value="{$s.id}" {if $documentSeriesFilter eq $s.id}selected{/if}>{$s.name|escape}</option>{/foreach}</select></label><button class="om-btn om-small" type="submit">Filtruj</button></form>
    <span class="om-chip">{$documents|count} z ostatnich 100</span>
  </div>
  <div class="om-doc-list">
  {foreach $documents as $d}
    <details class="om-doc-item">
      <summary>
        <span class="om-doc-summary-main"><strong>{$d.number|escape}</strong><span class="om-series-badge om-series-badge-{$d.kind}">{$documentKindLabels[$d.kind]|default:$d.kind}</span>{if $d.has_correction}<span class="om-chip om-chip-idle">skorygowany</span>{/if}</span>
        <span class="om-doc-summary-meta"><span><a href="?controller=orders&id={$d.order_id}">Zam. #{$d.order_id}</a> · {$d.series_name|default:'—'|escape}</span><span>{$d.created_at|escape} UTC</span><strong>{($d.gross_cents/100)|string_format:'%.2f'} {$d.currency|escape}</strong></span>
        <i class="bi bi-chevron-down oc-chevron"></i>
      </summary>
      <div class="om-doc-body">
        <div class="om-actions"><a class="om-btn om-small" href="?controller=orders&action=printdocument&id={$d.id}" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf"></i> Podgląd A4 ↗</a></div>
        {if $canWrite}
        <details class="om-doc-edit"><summary><i class="bi bi-pencil"></i> Edytuj dokument</summary>
          <form class="om-form om-doc-edit-form" method="post" action="?controller=orders&action=save">
            <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="document_update"><input type="hidden" name="tab" value="documents"><input type="hidden" name="document_id" value="{$d.id}">
            <label>Nabywca<textarea name="buyer" required>{$d.buyer|escape}</textarea></label>
            <fieldset class="om-new-items"><legend>Pozycje</legend>
              <div class="om-new-items-head"><span>Zmień pozycje dokumentu. Numer i seria pozostają bez zmian.</span><button class="om-btn om-small" type="button" data-doc-add-item><i class="bi bi-plus-lg"></i> Dodaj pozycję</button></div>
              <div data-doc-items>
              {foreach $d.items as $i=>$it}
              <div class="om-doc-item-row">
                <label>Nazwa<input name="items[{$i}][name]" value="{$it.name|escape}" required></label>
                <label>Ilość<input type="number" min="0" step="1" name="items[{$i}][quantity]" value="{$it.quantity}" required data-doc-qty></label>
                <label>Cena brutto<input inputmode="decimal" name="items[{$i}][price]" value="{$it.price}" required data-doc-price></label>
                <label>VAT<select name="items[{$i}][vat]">{foreach ['23','8','5','0','zw','np'] as $v}<option value="{$v}" {if $it.vat eq $v}selected{/if}>{$v}{if $v ne 'zw' and $v ne 'np'}%{/if}</option>{/foreach}</select></label>
                <strong data-doc-line-total>{$it.price} PLN</strong>
                <button class="om-icon-btn" type="button" data-doc-remove-item aria-label="Usuń pozycję"><i class="bi bi-trash"></i></button>
              </div>
              {/foreach}
              </div>
            </fieldset>
            <button class="om-btn om-primary om-small">Zapisz zmiany w dokumencie</button>
          </form>
        </details>
        <form method="post" action="?controller=orders&action=save" class="om-doc-delete" {if !$d.has_correction}data-double-confirm="Usunąć dokument {$d.number|escape}? Tej operacji nie można cofnąć.||Potwierdź jeszcze raz: dokument {$d.number|escape} zostanie trwale usunięty."{/if}>
          <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="document_delete"><input type="hidden" name="tab" value="documents"><input type="hidden" name="document_id" value="{$d.id}">
          {if $d.has_correction}<button class="om-btn om-small" type="button" disabled title="Do tego dokumentu wystawiono korektę — usuń najpierw korektę"><i class="bi bi-trash"></i> Usuń dokument</button>
          {else}<button class="om-btn om-small om-danger-outline" type="submit"><i class="bi bi-trash"></i> Usuń dokument</button>{/if}
        </form>
        {/if}
      </div>
    </details>
  {foreachelse}<div class="om-empty"><i class="bi bi-file-earmark-text"></i><h3>Brak dokumentów w tym widoku</h3><p>{if $documentSeriesFilter}Wybierz „Wszystkie serie”, aby zobaczyć pozostałe dokumenty.{else}Dokument wystawisz w szczegółach zamówienia.{/if}</p></div>{/foreach}
  </div>
</section>
