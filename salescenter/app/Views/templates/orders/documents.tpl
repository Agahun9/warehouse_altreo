{assign var=documentKindLabels value=['invoice'=>'Faktura','receipt'=>'Paragon','invoice_correction'=>'Korekta faktury','receipt_correction'=>'Korekta paragonu']}
<div class="sc-docs" data-documents-panel>
  <header class="sc-docs-heading"><div><span class="sc-docs-kicker"><i class="bi bi-file-earmark-text"></i> DOKUMENTY SPRZEDAŻY</span><h2>Dokumenty pod kontrolą</h2><p>Historia sprzedaży, numeracja i dane firmy w jednym panelu.</p></div>{if $canWrite}<button class="om-btn om-primary" type="button" data-docs-add-series><i class="bi bi-plus-lg"></i> Nowa seria</button>{/if}</header>
  <div class="sc-docs-overview" aria-label="Podsumowanie dokumentów">
    <article class="sc-docs-metric"><span class="sc-docs-metric-icon"><i class="bi bi-files"></i></span><div><strong>{$documents|count}</strong><span>Dokumenty w widoku</span><small>Ostatnie dokumenty z wybranej serii</small></div></article>
    <article class="sc-docs-metric"><span class="sc-docs-metric-icon"><i class="bi bi-collection"></i></span><div><strong>{$series|count}</strong><span>Serie dokumentów</span><small>Oddzielne formaty i liczniki</small></div></article>
    <article class="sc-docs-metric"><span class="sc-docs-metric-icon"><i class="bi bi-printer"></i></span><div><strong>{$activeFiscalPrinterCount}</strong><span>Aktywne drukarki paragonów</span><small>Urządzenia przypisywane do serii</small></div></article>
  </div>
  <nav class="sc-docs-tabs" role="tablist" aria-label="Panel dokumentów"><a href="#sc-docs-history" id="sc-docs-history-tab" class="is-active" role="tab" aria-controls="sc-docs-history" aria-selected="true" data-docs-tab="history"><i class="bi bi-journal-text"></i> Wystawione dokumenty <b>{$documents|count}</b></a><a href="#sc-docs-series" id="sc-docs-series-tab" role="tab" aria-controls="sc-docs-series" aria-selected="false" data-docs-tab="series"><i class="bi bi-sliders"></i> Serie i ustawienia <b>{$series|count}</b></a></nav>
  <div id="sc-docs-history" class="sc-docs-panel" role="tabpanel" aria-labelledby="sc-docs-history-tab" data-docs-panel="history">
<section class="om-panel">
  <div class="sc-docs-panel-head">
    <div><h3>Wystawione dokumenty</h3><p>Ostatnie 100 dokumentów. Rozwiń wiersz, aby zobaczyć szczegóły i dostępne czynności.</p></div>
    <form method="get" class="om-doc-filter" data-series-filter-form><input type="hidden" name="controller" value="orders"><input type="hidden" name="tab" value="documents"><label>Seria<select name="series_id"><option value="0">Wszystkie serie</option>{foreach $series as $s}<option value="{$s.id}" {if $documentSeriesFilter eq $s.id}selected{/if}>{$s.name|escape}</option>{/foreach}</select></label><button class="om-btn om-small" type="submit">Filtruj</button></form>
  </div>
  <div class="sc-docs-toolbar">
    <label class="sc-docs-search"><i class="bi bi-search" aria-hidden="true"></i><input type="search" placeholder="Numer dokumentu, seria lub zamówienie…" aria-label="Szukaj w widocznych dokumentach" data-docs-search></label>
    <div class="sc-docs-kind" aria-label="Rodzaj dokumentów"><button type="button" class="is-active" data-docs-kind="all" aria-pressed="true">Wszystkie</button><button type="button" data-docs-kind="invoice" aria-pressed="false">Faktury</button><button type="button" data-docs-kind="receipt" aria-pressed="false">Paragony</button><button type="button" data-docs-kind="correction" aria-pressed="false">Korekty</button></div>
    <span class="sc-docs-results" data-docs-result aria-live="polite">{$documents|count} / {$documents|count}</span>
  </div>
  <div class="om-doc-list">
  {foreach $documents as $d}
    <details class="om-doc-item" data-document-row data-document-kind="{$d.kind|escape}" data-document-search="{$d.number|escape} {$d.series_name|default:''|escape} {$d.order_id}">
      <summary>
        <span class="om-doc-summary-main"><strong>{$d.number|escape}</strong><span class="sc-docs-document-tags"><span class="om-series-badge om-series-badge-{$d.kind}">{$documentKindLabels[$d.kind]|default:$d.kind}</span>{if $d.has_correction}<span class="sc-docs-state">Skorygowany</span>{/if}
          {if $d.kind eq 'receipt' and !empty($d.fiscal_job)}<span class="sc-docs-state {if $d.fiscal_job.status eq 'printed'}is-success{elseif $d.fiscal_job.status eq 'error' or $d.fiscal_job.status eq 'printer_offline'}is-error{/if}">{if $d.fiscal_job.printer_protocol|default:'' eq 'novitus'}Novitus{else}Posnet{/if} · {if $d.fiscal_job.status eq 'printed'}wydrukowano{elseif $d.fiscal_job.status eq 'queued'}w kolejce{elseif $d.fiscal_job.status eq 'processing'}drukowanie{else}błąd druku{/if}</span>{/if}
          {assign var=documentSubmission value=$ksefSubmissions[$d.id]|default:null}{if $documentSubmission and $documentSubmission.state eq 'accepted'}<span class="sc-docs-state is-success">KSeF · przyjęto</span>{/if}
        </span></span>
        <span class="om-doc-summary-meta"><span><a href="?controller=orders&id={$d.order_id}">Zamówienie #{$d.order_id}</a> · {$d.series_name|default:'—'|escape}</span><time>{$d.created_at|pl_time|escape}</time></span>
        <span class="sc-docs-doc-amount">{($d.gross_cents/100)|string_format:'%.2f'} <small>{$d.currency|escape}</small></span>
        <span class="sc-docs-doc-actions"><a class="sc-docs-preview" href="?controller=orders&action=printdocument&id={$d.id}" target="_blank" rel="noopener" aria-label="Podgląd dokumentu {$d.number|escape} w nowej karcie" title="Podgląd A4"><i class="bi bi-file-earmark-text"></i></a><i class="bi bi-chevron-down oc-chevron"></i></span>
      </summary>
      <div class="om-doc-body">
        <div class="om-actions"><a class="om-btn om-small" href="?controller=orders&action=printdocument&id={$d.id}" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf"></i> Podgląd A4 ↗</a>{if $canWrite}<a class="om-btn om-small" href="?controller=orders&action=correctdocument&id={$d.id}"><i class="bi bi-arrow-counterclockwise"></i> Wystaw korektę</a>{/if}</div>
        {if $d.kind eq 'receipt'}
        {assign var=fiscalJob value=$d.fiscal_job|default:null}
        {if $fiscalJob}
          <div class="om-fiscal-help"><i class="bi bi-printer"></i><div><strong>{if $fiscalJob.printer_protocol|default:'' eq 'novitus'}Novitus{else}Posnet{/if} · {$fiscalJob.printer_name|escape} · {if $fiscalJob.status eq 'queued'}Oczekuje na agenta{elseif $fiscalJob.status eq 'processing'}Drukowanie / oczekiwanie na wynik{elseif $fiscalJob.status eq 'printed'}Wydruk potwierdzony przez drukarkę{elseif $fiscalJob.status eq 'printer_offline'}Drukarka offline{else}Błąd druku{/if}</strong><p>{$fiscalJob.status_message|escape}{if $fiscalJob.fiscal_number} · Numer fiskalny: {$fiscalJob.fiscal_number|escape}{/if}{if $fiscalJob.status eq 'printed' && !empty($fiscalJob.reported_at)} · Potwierdzenie: {$fiscalJob.reported_at|pl_time|escape}{/if}</p><a href="?controller=orders&tab=printing">Sprawdź stanowisko i kolejkę druku</a>{if !empty($fiscalJob.retry_allowed)}<p>Ta próba zatrzymała się przed rozpoczęciem transakcji fiskalnej. Nie wystawiła paragonu na drukarce. Popraw stawki A–G przed ponowieniem.</p>{elseif $fiscalJob.status eq 'error' or $fiscalJob.status eq 'printer_offline' or $fiscalJob.status eq 'processing'}<p>Przed ponownym drukiem sprawdź urządzenie — paragon mógł zostać zapisany mimo braku potwierdzenia. Ponowne wysłanie jest zablokowane.</p>{/if}</div></div>
          {if $canWrite && !empty($fiscalJob.retry_allowed)}<form class="om-top" method="post" action="?controller=orders&action=save" data-confirm-action="Ponowić fiskalny wydruk tego paragonu na tej samej drukarce po poprawieniu stawek VAT? Poprzednia próba zakończyła się przed rozpoczęciem transakcji."><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="document_remote_retry"><input type="hidden" name="tab" value="documents"><input type="hidden" name="document_id" value="{$d.id}"><button class="om-btn om-primary om-small" type="submit"><i class="bi bi-arrow-repeat"></i> Ponów druk po poprawieniu stawek</button></form>{/if}
        {elseif $canWrite}
          <form class="om-form om-top" method="post" action="?controller=orders&action=save" data-confirm-action="Wysłać paragon {$d.number|escape} do wybranej drukarki fiskalnej? Tryb PRODUKCJA fiskalizuje sprzedaż, SANDBOX drukuje niefiskalnie.">
            <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="document_remote_print"><input type="hidden" name="tab" value="documents"><input type="hidden" name="document_id" value="{$d.id}">
            {assign var=remotePrinterCount value=0}
            <label>Drukarka fiskalna<select name="fiscal_printer_id" required><option value="">Wybierz drukarkę</option>{foreach $printFiscalPrinters as $printer}{assign var=printerAllowed value=($printer.enabled && (!isset($printer.station_enabled) or $printer.station_enabled) && (empty($d.non_fiscal) or $printer.environment eq 'sandbox'))}{if $printerAllowed}{assign var=remotePrinterCount value=$remotePrinterCount+1}<option value="{$printer.id}" {if ($d.effective_printer_id|default:0) eq $printer.id}selected{/if}>{$printer.name|escape} · {$printer.station_name|escape} · {if $printer.environment eq 'production'}PRODUKCJA — fiskalny{else}SANDBOX — niefiskalny{/if}{if !$printer.station_online} · agent offline{/if}</option>{/if}{/foreach}</select></label>
            {if !$remotePrinterCount}<div class="om-muted">{if !$printFiscalPrinters}W tej firmie nie ma zarejestrowanej drukarki fiskalnej. Dodaj urządzenie lub połącz agenta w <a href="?controller=orders&tab=printing">Drukowaniu</a>.{elseif !empty($d.non_fiscal)}Seria tego dokumentu jest oznaczona jako niefiskalna. Drukarka produkcyjna nie może jej drukować. Jeśli chcesz paragon fiskalny, wyłącz „Dokument niefiskalny” w <a href="?controller=orders&tab=documents#om-series-{$d.series_id}">ustawieniach tej serii</a>. Do testu wybierz drukarkę w trybie SANDBOX.{else}Brak dostępnej drukarki. W <a href="?controller=orders&tab=printing">Drukowaniu</a> włącz drukarkę oraz jej stanowisko.{/if}</div>{/if}
            <button class="om-btn om-primary om-small" type="submit" {if !$remotePrinterCount}disabled{/if}><i class="bi bi-printer"></i> Drukuj zdalnie na drukarce fiskalnej</button>
            <small class="om-muted">Wysyła zapisane pozycje tego dokumentu do agenta, bez wystawiania nowego dokumentu. Test niefiskalny agenta nie potwierdza poprawności stawek i danych paragonu fiskalnego.</small>
          </form>
        {/if}
        {/if}
        {include file='orders/ksef_document.tpl' doc=$d ksefTab='documents' ksefOrderId=0}
        {if $canWrite}
        {if $d.kind ne 'receipt' or empty($d.fiscal_job)}
        <details class="om-doc-edit"><summary><i class="bi bi-pencil"></i> Edytuj dokument</summary>
          <form class="om-form om-doc-edit-form" method="post" action="?controller=orders&action=save">
            <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="document_update"><input type="hidden" name="tab" value="documents"><input type="hidden" name="document_id" value="{$d.id}">
            <label>Nabywca<textarea name="buyer" required>{$d.buyer|escape}</textarea></label>
            <label>Dane dostawy<textarea name="recipient">{$d.recipient|default:""|escape}</textarea></label>
            <label>Dodatkowa informacja<textarea name="additional_info" maxlength="2000" placeholder="Drukowana na dole faktury">{$d.additional_info|default:""|escape}</textarea></label>
            <fieldset class="om-new-items"><legend>Pozycje</legend>
              <div class="om-new-items-head"><span>Zmień pozycje dokumentu. Numer i seria pozostają bez zmian.</span><button class="om-btn om-small" type="button" data-doc-add-item><i class="bi bi-plus-lg"></i> Dodaj pozycję</button></div>
              <div data-doc-items>
              {foreach $d.items as $i=>$it}
              <div class="om-doc-item-row">
                <label>Nazwa<input name="items[{$i}][name]" value="{$it.name|escape}" required><input type="hidden" name="items[{$i}][sku]" value="{$it.sku|escape}"><input type="hidden" name="items[{$i}][ean]" value="{$it.ean|escape}"></label>
                <label>Ilość<input type="number" min="0" step="1" name="items[{$i}][quantity]" value="{$it.quantity}" required data-doc-qty></label>
                <label>Cena brutto<input inputmode="decimal" name="items[{$i}][price]" value="{$it.price}" required data-doc-price></label>
                <label>VAT<select name="items[{$i}][vat]">{foreach ['23','8','7','5','0','zw','np'] as $v}<option value="{$v}" {if $it.vat eq $v}selected{/if}>{$v}{if $v ne 'zw' and $v ne 'np'}%{/if}</option>{/foreach}</select></label>
                <strong data-doc-line-total>{$it.price} PLN</strong>
                <button class="om-icon-btn" type="button" data-doc-remove-item aria-label="Usuń pozycję"><i class="bi bi-trash"></i></button>
              </div>
              {/foreach}
              </div>
            </fieldset>
            <button class="om-btn om-primary om-small">Zapisz zmiany w dokumencie</button>
          </form>
        </details>
        {if !$d.has_correction}<form method="post" action="?controller=orders&action=save" class="om-doc-delete" {if !$d.has_correction}data-double-confirm="Usunąć dokument {$d.number|escape}? Tej operacji nie można cofnąć.||Potwierdź jeszcze raz: dokument {$d.number|escape} zostanie trwale usunięty."{/if}>
          <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="document_delete"><input type="hidden" name="tab" value="documents"><input type="hidden" name="document_id" value="{$d.id}">
          <button class="om-btn om-small om-danger-outline" type="submit"><i class="bi bi-trash"></i> Usuń dokument</button>
        </form>{/if}
        {/if}
        {/if}
      </div>
    </details>
  {foreachelse}<div class="om-empty"><i class="bi bi-file-earmark-text"></i><h3>Brak dokumentów w tym widoku</h3><p>{if $documentSeriesFilter}Wybierz „Wszystkie serie”, aby zobaczyć pozostałe dokumenty.{else}Dokument wystawisz w szczegółach zamówienia.{/if}</p></div>{/foreach}
    <div class="sc-docs-no-match" data-docs-no-match hidden><i class="bi bi-search"></i>Brak dokumentów pasujących do wybranych filtrów.</div>
  </div>
</section>
  </div>
  <div id="sc-docs-series" class="sc-docs-panel" role="tabpanel" aria-labelledby="sc-docs-series-tab" data-docs-panel="series">
<section class="om-panel om-pad om-docs-series-panel">
  <div class="om-docs-panel-title"><div><h3>Serie dokumentów</h3><p>Rozwiń serię, aby zmienić jej ustawienia. Dane i licznik są przypisane osobno do każdej serii.</p></div><span class="om-chip">{$series|count} {if $series|count eq 1}seria{elseif $series|count >= 2 and $series|count <= 4}serie{else}serii{/if}</span></div>
  <div class="om-series-list">
  {foreach $series as $s}
    <details class="om-series-item" id="om-series-{$s.id}">
      <summary>
        <span class="om-series-summary-main"><span class="om-series-badge om-series-badge-{$s.kind}">{$documentKindLabels[$s.kind]|default:$s.kind}</span><strong>{if !empty($s.numbering.color)}<span class="om-series-color" style="background:{$s.numbering.color|escape}" aria-hidden="true"></span>{/if}{$s.name|escape}</strong><span class="om-series-pattern">{$s.pattern|escape}</span></span>
        <span class="om-series-summary-meta"><span class="om-series-next">Kolejny numer <b>{$s.next_number}</b></span><span class="sc-docs-series-count">{$s.document_count|default:0} dok.</span><i class="bi bi-chevron-down oc-chevron"></i></span>
      </summary>
      <div class="om-series-body">
        <form class="om-form" method="post" action="?controller=orders&action=save" data-docs-series-form>
          <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="series_update"><input type="hidden" name="tab" value="documents"><input type="hidden" name="series_id" value="{$s.id}">
          <label>Nazwa serii<input name="name" value="{$s.name|escape}" maxlength="100" required {if !$canWrite}disabled{/if}></label>
          {if $s.document_count}<label>Rodzaj dokumentu<input type="text" value="{$documentKindLabels[$s.kind]|default:$s.kind|escape}" readonly><input type="hidden" name="kind" value="{$s.kind|escape}"></label>
          {else}<label>Rodzaj dokumentu<select name="kind" {if !$canWrite}disabled{/if}><option value="invoice" {if $s.kind eq 'invoice'}selected{/if}>Faktura</option><option value="receipt" {if $s.kind eq 'receipt'}selected{/if}>Paragon</option><option value="invoice_correction" {if $s.kind eq 'invoice_correction'}selected{/if}>Korekta faktury</option><option value="receipt_correction" {if $s.kind eq 'receipt_correction'}selected{/if}>Korekta paragonu</option></select></label>{/if}
          <label>Kolejny numer dokumentu<input name="next_number" type="number" min="1" max="100000000" value="{$s.next_number}" required {if !$canWrite}disabled{/if}></label>
          <label data-series-for="receipt">Drukarka paragonów<select name="fiscal_printer_id" {if !$canWrite}disabled{/if}><option value="0" {if !$s.effective_printer_id}selected{/if}>Bez automatycznego druku</option>{foreach $printFiscalPrinters as $printer}{if $printer.enabled && $printer.station_enabled}<option value="{$printer.id}" {if $printer.id eq $s.effective_printer_id}selected{/if}>{$printer.name|escape} · {$printer.host|escape}:{$printer.port} · {if $printer.environment eq 'production'}PRODUKCJA{else}SANDBOX{/if}</option>{/if}{/foreach}</select></label>
          {include file='orders/series_numbering.tpl' numbering=$s.numbering|default:[] numberingPattern=$s.pattern newSeries=false}
          {include file='orders/series_document_settings.tpl' documentSettings=$s.document_settings|default:[]}
          {if $s.document_count}<small class="om-muted sc-docs-series-note">Wystawiono już dokumenty w tej serii. Jej rodzaj oraz wcześniejsze numery są zachowane.</small>{/if}
          <div class="sc-docs-series-footer"><span>Ustawienia dotyczą nowych dokumentów.</span>{if $canWrite}<button class="om-btn om-primary" type="submit"><i class="bi bi-check2"></i> Zapisz ustawienia serii</button>{/if}</div>
        </form>
        {if $canWrite and !$s.document_count}
        <form class="om-series-delete" method="post" action="?controller=orders&action=save" {if !$s.document_count}data-confirm-action="Usunąć serię „{$s.name|escape}”? Tej operacji nie można cofnąć."{/if}>
          <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="series_delete"><input type="hidden" name="tab" value="documents"><input type="hidden" name="series_id" value="{$s.id}">
          <button class="om-btn om-small om-danger-outline" type="submit"><i class="bi bi-trash"></i> Usuń pustą serię</button>
        </form>
        {/if}
      </div>
    </details>
  {foreachelse}<p class="om-muted">Dodaj osobne serie dla faktur, paragonów i korekt.</p>{/foreach}
  </div>
  {if $canWrite}<details class="om-fiscal-add om-docs-add-series" data-docs-new-series><summary><i class="bi bi-plus-circle"></i> Dodaj nową serię dokumentów</summary>
    <form class="om-form sc-docs-new-form" method="post" action="?controller=orders&action=save" data-docs-series-form data-new-series-form>
      <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="series"><input type="hidden" name="tab" value="documents">
      <label>Nazwa serii<input name="name" maxlength="100" placeholder="np. Faktury — sklep główny" required></label>
      <label>Rodzaj dokumentu<select name="kind"><option value="invoice">Faktura</option><option value="receipt">Paragon</option><option value="invoice_correction">Korekta faktury</option><option value="receipt_correction">Korekta paragonu</option></select></label>
      <label>Pierwszy numer dokumentu<input name="next_number" type="number" min="1" max="100000000" value="1" required></label>
      <label data-series-for="receipt">Drukarka paragonów<select name="fiscal_printer_id"><option value="0">Bez automatycznego druku</option>{foreach $printFiscalPrinters as $printer}{if $printer.enabled && $printer.station_enabled}<option value="{$printer.id}">{$printer.name|escape} · {if $printer.environment eq 'production'}PRODUKCJA{else}SANDBOX{/if}</option>{/if}{/foreach}</select></label>
      {include file='orders/series_numbering.tpl' numbering=[] newSeries=true}
      {include file='orders/series_document_settings.tpl' documentSettings=[]}
      <div class="sc-docs-series-footer"><span>Seria otrzyma oddzielny licznik dokumentów.</span><button class="om-btn om-primary" type="submit"><i class="bi bi-plus-lg"></i> Utwórz serię</button></div>
    </form>
  </details>{/if}
  <div class="sc-docs-series-help"><i class="bi bi-printer"></i><div><strong>Automatyczny druk paragonów</strong><p>Przypisz drukarkę do serii paragonów. Tryb produkcyjny wystawia paragon fiskalny, a sandbox służy do wydruków niefiskalnych.</p></div><a class="om-btn om-small" href="?controller=orders&tab=printing">Drukarki i stanowiska <i class="bi bi-arrow-up-right"></i></a></div>
</section>
  </div>
</div>
<script src="dist/js/orders-documents.js?v=20261001-1" defer></script>
