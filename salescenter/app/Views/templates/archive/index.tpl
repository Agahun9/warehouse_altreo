<link rel="stylesheet" href="dist/css/archive.css?v=20261001-1">
<main class="app-main om arc">
<div class="om-shell">
  <header class="om-hero">
    <div><div class="om-eyebrow">SPRZEDAŻ · ARCHIWUM</div><h1>Archiwum Sellasist</h1><p>Stare zamówienia i dokumenty sprzedaży pobrane z Sellasist. Tylko do odczytu – nie trafiają do bieżącej kolejki.</p></div>
    <div class="om-hero-actions">
      {if $progress.configured}<span class="arc-live {if !$progress.enabled}is-idle{/if}" data-arc-live><i class="dot"></i><span data-arc-live-text>{if $progress.completed}Archiwum aktualne{elseif $progress.enabled}Import w tle{else}Import wstrzymany{/if}</span></span>{/if}
      {if $canWrite && $progress.configured && !$progress.completed}<button class="om-btn om-primary" type="button" data-arc-run><i class="bi bi-cloud-download"></i> <span>Pobieraj teraz</span></button>{/if}
    </div>
  </header>
  {if $flashSuccess}<div class="om-alert success">{$flashSuccess|escape}</div>{/if}
  {if $flashError}<div class="om-alert error">{$flashError|escape}</div>{/if}

  {if $progress.configured}
  <section class="om-panel arc-progress" data-arc-progress>
    <div>
      <small>Szczegóły pobrane</small>
      <strong><span data-arc-details-done>{$progress.details_done}</span> / <span data-arc-details-total>{$progress.details_total}</span></strong>
      <div class="arc-bar"><span data-arc-bar style="width:{$progress.details_percent}%"></span></div>
      <em data-arc-status>{if $progress.last_error}{$progress.last_error|escape}{elseif $progress.last_run}Ostatnia porcja: {$progress.last_run|escape} UTC{else}Czeka na pierwsze uruchomienie{/if}</em>
    </div>
    {foreach $progress.phases as $phase}
    <div class="arc-phase {if $phase.done}is-done{/if} {if $phase.error}has-error{/if}" data-arc-phase="{$phase.key}">
      <small>{$phase.label|escape}</small>
      <strong data-arc-phase-count>{$phase.stored}</strong>
      <em data-arc-phase-state>{if $phase.error}{$phase.error|escape}{elseif $phase.done}lista kompletna{else}pobieranie listy…{/if}</em>
    </div>
    {/foreach}
  </section>
  {/if}

  <nav class="om-tabs" aria-label="Sekcje archiwum">
    <a href="index.php?controller=archive" class="{if $tab eq 'orders'}selected{/if}"><i class="bi bi-archive"></i> Zamówienia <b>{$progress.stats.orders}</b></a>
    <a href="index.php?controller=archive&tab=documents" class="{if $tab eq 'documents'}selected{/if}"><i class="bi bi-file-earmark-text"></i> Dokumenty <b>{$progress.stats.documents}</b></a>
    <a href="index.php?controller=archive&tab=settings" class="{if $tab eq 'settings'}selected{/if}"><i class="bi bi-plug"></i> Połączenie i import</a>
  </nav>

  {if $tab eq 'orders' && $order}
    <section class="om-panel">
      <div class="om-panel-heading" style="padding:14px 16px">
        <div><h2>Zamówienie Sellasist #{$order.sellasist_id}{if $order.external_id} <small class="om-muted">· {$order.external_id|escape}</small>{/if}</h2>
          <p class="arc-summary"><span class="om-chip">{$order.status_name|default:'—'|escape}</span><span class="om-market">{$order.source|default:'sellasist'|escape}</span>{if $order.creator}<span class="om-muted">{$order.creator|escape}</span>{/if}<span class="om-muted">{$order.ordered_at|escape}</span>{if $order.detail_state eq 0}<span class="om-chip">szczegóły w kolejce</span>{elseif $order.detail_state eq 2}<span class="om-chip">nie udało się pobrać szczegółów</span>{/if}</p></div>
        <a class="om-btn" href="index.php?controller=archive"><i class="bi bi-arrow-left"></i> Wróć do listy</a>
      </div>
      <div class="arc-detail">
        <div class="arc-card"><h3>Klient</h3><dl>
          <dt>Nazwa</dt><dd>{$order.buyer_name|default:'—'|escape}</dd>
          {if $order.company}<dt>Firma</dt><dd>{$order.company|escape}</dd>{/if}
          {if $order.nip}<dt>NIP</dt><dd>{$order.nip|escape}</dd>{/if}
          <dt>E-mail</dt><dd>{if $order.email}<a href="mailto:{$order.email|escape}">{$order.email|escape}</a>{else}—{/if}</dd>
          <dt>Telefon</dt><dd>{$order.phone|default:'—'|escape}</dd>
          {if $view.comment}<dt>Komentarz</dt><dd>{$view.comment|escape}</dd>{/if}
        </dl></div>
        {foreach $view.addresses as $a}
        <div class="arc-card"><h3>{$a.label|escape}</h3><dl>
          <dt>Odbiorca</dt><dd>{$a.name|default:'—'|escape}{if $a.company}<br>{$a.company|escape}{/if}</dd>
          <dt>Adres</dt><dd>{$a.street|escape}<br>{$a.city|escape}</dd>
          {if $a.phone}<dt>Telefon</dt><dd>{$a.phone|escape}</dd>{/if}
          {if $a.nip}<dt>NIP</dt><dd>{$a.nip|escape}</dd>{/if}
        </dl></div>{/foreach}
        <div class="arc-card"><h3>Płatność i dostawa</h3><dl>
          <dt>Kwota</dt><dd><strong>{($order.total_cents/100)|string_format:'%.2f'} {$order.currency|escape}</strong></dd>
          <dt>Płatność</dt><dd>{$order.payment_name|default:'—'|escape}{if $order.cod} (pobranie){/if}</dd>
          <dt>Status płatności</dt><dd>{$order.payment_status|default:'—'|escape}{if $order.paid_cents} · wpłacono {($order.paid_cents/100)|string_format:'%.2f'}{/if}{if $view.paid_date} · {$view.paid_date|escape}{/if}</dd>
          <dt>Dostawa</dt><dd>{$order.delivery_name|default:'—'|escape}{if $view.shipping_total neq ''} · {$view.shipping_total|escape} {$order.currency|escape}{/if}</dd>
          {if $view.pickup}<dt>Punkt odbioru</dt><dd>{$view.pickup|escape}</dd>{/if}
          <dt>Nr nadania</dt><dd>{$order.tracking|default:'—'|escape}</dd>
          {if $order.shop}<dt>Sklep</dt><dd>{$order.shop|escape}</dd>{/if}
        </dl></div>
        <div class="arc-card"><h3>Dokumenty</h3>
          {if $order.documents}<div class="arc-docs">{foreach $order.documents as $doc}<a class="arc-doc {$doc.kind|escape}" href="index.php?controller=archive&action=document&id={$doc.id}" target="_blank" rel="noopener"><i class="bi bi-file-earmark-text"></i> {$docKinds[$doc.kind]|default:$doc.kind|escape} {$doc.number|default:"#`$doc.id`"|escape}</a>{/foreach}</div>
          {elseif $order.document_number}<p class="om-muted">Numer z zamówienia: {$order.document_number|escape} (dokument nie został jeszcze pobrany)</p>
          {else}<p class="om-muted">Brak pobranego numeru faktury lub paragonu.</p>{/if}
        </div>
      </div>
      {if $view.carts}
      <div class="arc-lines om-table-wrap"><table class="om-table"><thead><tr><th></th><th>Produkt</th><th>SKU / EAN</th><th class="om-right">Ilość</th><th class="om-right">Cena / szt.</th><th class="om-right">VAT</th><th class="om-right">Wartość</th></tr></thead><tbody>
        {foreach $view.carts as $c}<tr>
          <td>{if $c.image}<img src="{$c.image|escape}" alt="" loading="lazy" referrerpolicy="no-referrer">{/if}</td>
          <td><strong>{$c.name|escape}</strong>{foreach $c.notes as $n}<br><small class="om-muted">{$n|escape}</small>{/foreach}</td>
          <td><small>{foreach $c.codes as $code}{$code|escape}<br>{/foreach}</small></td>
          <td class="om-right">{$c.quantity|escape}</td>
          <td class="om-right">{$c.price|escape}</td>
          <td class="om-right">{$c.vat|escape}</td>
          <td class="om-right"><strong>{$c.total|escape}</strong></td>
        </tr>{/foreach}
      </tbody></table></div>
      {/if}
      {if $view.sections}
      <div class="arc-detail">
        {foreach $view.sections as $section}<div class="arc-card"><h3>{$section.label|escape}</h3><dl>{foreach $section.rows as $r}<dt>{$r[0]|escape}</dt><dd>{$r[1]|escape}</dd>{/foreach}</dl></div>{/foreach}
      </div>
      {/if}
      {if $orderRaw && $orderRaw neq '[]'}<details style="padding:0 16px 8px"><summary class="om-muted" style="cursor:pointer;padding-bottom:10px">Pełne dane z API Sellasist (JSON)</summary></details><pre class="arc-raw" hidden data-arc-raw>{$orderRaw|escape}</pre>{/if}
    </section>

  {elseif $tab eq 'orders'}
    <section class="om-panel om-order-list arc-orders">
      <div class="om-list-heading"><div><h2>Zamówienia z archiwum</h2><p><strong>{$listing.total}</strong> wyników{if $progress.stats.oldest} <span>·</span> od {$progress.stats.oldest|truncate:10:''|escape} do {$progress.stats.newest|truncate:10:''|escape}{/if}</p></div>
        <div class="om-list-heading-actions"><span class="om-result-range">Strona {$listing.page} z {$listing.pages}</span><a class="om-btn om-small" href="index.php?controller=archive&action=export{if $listQuery}&{$listQuery|escape}{/if}"><i class="bi bi-filetype-csv"></i> Eksport CSV</a></div></div>
      <form class="om-filter-form" method="get"><input type="hidden" name="controller" value="archive">
        <div class="om-listbar"><div class="om-search"><i class="bi bi-search"></i><input name="q" value="{$filters.q|escape}" placeholder="Szukaj po numerze, kliencie, produkcie lub dokumencie…" aria-label="Szukaj w archiwum" autofocus></div>
          <select name="status_id" aria-label="Status"><option value="">Wszystkie statusy</option>{foreach $facets.statuses as $s}<option value="{$s.status_id}" {if $filters.status_id neq '' && $filters.status_id eq $s.status_id}selected{/if}>{$s.status_name|default:"Status `$s.status_id`"|escape} ({$s.c})</option>{/foreach}</select>
          <button class="om-btn {if $activeFilterCount}is-filtered{/if}" type="button" data-arc-filters aria-expanded="{if $activeFilterCount}true{else}false{/if}"><i class="bi bi-funnel"></i> Filtry{if $activeFilterCount} ({$activeFilterCount}){/if}</button>
          <button class="om-btn om-primary" type="submit">Szukaj</button></div>
        <div class="om-filter-drawer {if $activeFilterCount}is-open{/if}" data-arc-drawer>
          <label>Źródło<select name="source"><option value="">Wszystkie</option>{foreach $facets.sources as $s}<option value="{$s.source|escape}" {if $filters.source eq $s.source}selected{/if}>{$s.source|escape} ({$s.c})</option>{/foreach}</select></label>
          <label>Płatność<select name="payment_status"><option value="">Dowolna</option>{foreach $facets.payment_statuses as $p}<option value="{$p|escape}" {if $filters.payment_status eq $p}selected{/if}>{$p|escape}</option>{/foreach}</select></label>
          <label>Dokument<select name="document"><option value="">Dowolnie</option><option value="1" {if $filters.document eq '1'}selected{/if}>Z dokumentem</option><option value="0" {if $filters.document eq '0'}selected{/if}>Bez dokumentu</option></select></label>
          <label>Data od<input type="date" name="date_from" value="{$filters.date_from|escape}"></label><label>Data do<input type="date" name="date_to" value="{$filters.date_to|escape}"></label>
          <label>Kwota od<input inputmode="decimal" name="amount_from" value="{$filters.amount_from|escape}" placeholder="0,00"></label><label>Kwota do<input inputmode="decimal" name="amount_to" value="{$filters.amount_to|escape}" placeholder="9999,00"></label>
          <label>Sortowanie<select name="sort">{foreach ['newest'=>'Najnowsze najpierw','oldest'=>'Najstarsze najpierw','amount_desc'=>'Kwota: malejąco','amount_asc'=>'Kwota: rosnąco','buyer'=>'Klient A–Z'] as $v=>$l}<option value="{$v}" {if $filters.sort eq $v}selected{/if}>{$l}</option>{/foreach}</select></label>
          <div class="om-filter-actions"><a href="index.php?controller=archive">Wyczyść filtry</a><button class="om-btn om-primary" type="submit">Zastosuj filtry</button></div>
        </div>
      </form>
      <div class="om-table-wrap"><table class="om-table"><thead><tr><th>Nr Sellasist</th><th>Klient</th><th>Przedmioty</th><th class="om-right">Kwota</th><th>Status</th><th>Płatność / dostawa</th><th>Źródło</th><th>Dokumenty</th><th>Data</th></tr></thead><tbody>
        {foreach $listing.rows as $o}<tr class="om-order-row" style="cursor:pointer" data-arc-href="index.php?controller=archive&id={$o.id}">
          <td><a class="om-order-number" href="index.php?controller=archive&id={$o.id}">{$o.sellasist_id}</a>{if $o.external_id}<small class="om-ellipsis om-muted">{$o.external_id|escape}</small>{/if}</td>
          <td><strong class="om-ellipsis">{$o.buyer_name|default:'—'|escape}</strong>{if $o.company}<small class="om-ellipsis">{$o.company|escape}</small>{/if}<small class="om-ellipsis om-muted">{$o.email|escape}</small>{if $o.phone}<small class="om-ellipsis om-muted">{$o.phone|escape}</small>{/if}</td>
          <td>{if $o.items_preview}<div class="arc-items" title="{$o.items_preview|escape}">{$o.items_preview|escape}</div>{elseif $o.detail_state eq 0}<small class="om-muted">pobieranie…</small>{else}<span class="om-muted">—</span>{/if}</td>
          <td class="om-right"><strong class="om-amount">{($o.total_cents/100)|string_format:'%.2f'} {$o.currency|escape}</strong></td>
          <td><span class="om-chip">{$o.status_name|default:"#`$o.status_id`"|escape}</span></td>
          <td><small class="om-ellipsis">{$o.payment_name|default:'—'|escape}{if $o.payment_status} · {$o.payment_status|escape}{/if}</small><small class="om-ellipsis om-muted">{$o.delivery_name|escape}</small>{if $o.tracking}<small class="om-ellipsis om-muted"><i class="bi bi-truck"></i> {$o.tracking|escape}</small>{/if}</td>
          <td><span class="om-market">{$o.source|default:'—'|escape}</span>{if $o.creator}<small class="om-ellipsis om-muted">{$o.creator|escape}</small>{/if}</td>
          <td><div class="arc-docs">{foreach $o.documents as $doc}<a class="arc-doc {$doc.kind|escape}" href="index.php?controller=archive&action=document&id={$doc.id}" target="_blank" rel="noopener" title="{$docKinds[$doc.kind]|default:''|escape}">{$doc.number|default:"#`$doc.id`"|escape}</a>{foreachelse}{if $o.document_number}<small class="om-muted">{$o.document_number|escape}</small>{else}<span class="om-muted">—</span>{/if}{/foreach}</div></td>
          <td class="om-date-cell"><span>{$o.ordered_at|truncate:16:''|escape}</span></td>
        </tr>{foreachelse}<tr><td colspan="9"><div class="om-empty"><i class="bi bi-archive"></i><h3>{if $progress.stats.orders}Nie znaleziono zamówień{else}Archiwum jest puste{/if}</h3><p>{if $progress.stats.orders}Zmień lub wyczyść filtry.{elseif $progress.configured}Import trwa w tle – pierwsze zamówienia pojawią się za chwilę.{else}Połącz konto Sellasist, aby pobrać archiwum.{/if}</p>{if !$progress.configured}<a class="om-btn om-primary" href="index.php?controller=archive&tab=settings">Połącz Sellasist →</a>{/if}</div></td></tr>{/foreach}
      </tbody></table></div>
      {include file='archive/pagination.tpl'}
    </section>

  {elseif $tab eq 'documents'}
    <section class="om-panel om-order-list arc-documents">
      <div class="om-list-heading"><div><h2>Dokumenty sprzedaży</h2><p><strong>{$listing.total}</strong> wyników {foreach $docKinds as $k=>$l}<span>·</span> {$l}: {$progress.stats.kinds[$k]|default:0} {/foreach}</p></div>
        <div class="om-list-heading-actions"><span class="om-result-range">Strona {$listing.page} z {$listing.pages}</span><a class="om-btn om-small" href="index.php?controller=archive&action=export&tab=documents{if $listQuery}&{$listQuery|escape}{/if}"><i class="bi bi-filetype-csv"></i> Eksport CSV</a></div></div>
      <form class="om-filter-form" method="get"><input type="hidden" name="controller" value="archive"><input type="hidden" name="tab" value="documents">
        <div class="om-listbar"><div class="om-search"><i class="bi bi-search"></i><input name="q" value="{$filters.q|escape}" placeholder="Numer dokumentu, nabywca, NIP, nr zamówienia, produkt…" aria-label="Szukaj dokumentów" autofocus></div>
          <select name="kind" aria-label="Rodzaj"><option value="">Wszystkie rodzaje</option>{foreach $docKinds as $k=>$l}<option value="{$k}" {if $filters.kind eq $k}selected{/if}>{$l}</option>{/foreach}</select>
          <button class="om-btn {if $activeFilterCount}is-filtered{/if}" type="button" data-arc-filters aria-expanded="{if $activeFilterCount}true{else}false{/if}"><i class="bi bi-funnel"></i> Filtry{if $activeFilterCount} ({$activeFilterCount}){/if}</button>
          <button class="om-btn om-primary" type="submit">Szukaj</button></div>
        <div class="om-filter-drawer {if $activeFilterCount}is-open{/if}" data-arc-drawer>
          <label>Data od<input type="date" name="date_from" value="{$filters.date_from|escape}"></label><label>Data do<input type="date" name="date_to" value="{$filters.date_to|escape}"></label>
          <label>Kwota od<input inputmode="decimal" name="amount_from" value="{$filters.amount_from|escape}"></label><label>Kwota do<input inputmode="decimal" name="amount_to" value="{$filters.amount_to|escape}"></label>
          <label>Sortowanie<select name="sort">{foreach ['newest'=>'Najnowsze najpierw','oldest'=>'Najstarsze najpierw','amount_desc'=>'Kwota: malejąco','amount_asc'=>'Kwota: rosnąco','number'=>'Numer A–Z'] as $v=>$l}<option value="{$v}" {if $filters.sort eq $v}selected{/if}>{$l}</option>{/foreach}</select></label>
          <div class="om-filter-actions"><a href="index.php?controller=archive&tab=documents">Wyczyść filtry</a><button class="om-btn om-primary" type="submit">Zastosuj filtry</button></div>
        </div>
      </form>
      <div class="om-table-wrap"><table class="om-table"><thead><tr><th>Numer</th><th>Rodzaj</th><th>Data wystawienia</th><th>Nabywca</th><th>Zamówienie</th><th class="om-right">Kwota brutto</th><th></th></tr></thead><tbody>
        {foreach $listing.rows as $doc}<tr>
          <td><a class="om-order-number" href="index.php?controller=archive&action=document&id={$doc.id}" target="_blank" rel="noopener">{$doc.number|default:"#`$doc.remote_id`"|escape}</a>{if $doc.related_number}<small class="om-muted">dotyczy: {$doc.related_number|escape}</small>{/if}</td>
          <td><span class="arc-doc {$doc.kind|escape}">{$docKinds[$doc.kind]|default:$doc.kind|escape}</span></td>
          <td>{$doc.issue_date|truncate:10:''|escape}</td>
          <td><strong class="om-ellipsis">{$doc.buyer_name|default:'—'|escape}</strong>{if $doc.buyer_nip}<small class="om-muted">NIP {$doc.buyer_nip|escape}</small>{/if}{if $doc.detail_state eq 0}<small class="om-muted">szczegóły w kolejce</small>{/if}</td>
          <td>{if $doc.archive_order_id}<a href="index.php?controller=archive&id={$doc.archive_order_id}">#{$doc.order_remote_id}</a>{elseif $doc.order_remote_id}#{$doc.order_remote_id}{else}<span class="om-muted">—</span>{/if}</td>
          <td class="om-right">{if $doc.detail_state eq 1}<strong>{($doc.total_cents/100)|string_format:'%.2f'} {$doc.currency|escape}</strong>{else}<span class="om-muted">—</span>{/if}</td>
          <td class="om-right"><a class="om-btn om-small" href="index.php?controller=archive&action=document&id={$doc.id}" target="_blank" rel="noopener"><i class="bi bi-printer"></i> Podgląd</a></td>
        </tr>{foreachelse}<tr><td colspan="7"><div class="om-empty"><i class="bi bi-file-earmark-text"></i><h3>Brak dokumentów</h3><p>{if $progress.stats.documents}Zmień lub wyczyść filtry.{else}Dokumenty pojawią się po pobraniu list z Sellasist.{/if}</p></div></td></tr>{/foreach}
      </tbody></table></div>
      {include file='archive/pagination.tpl'}
    </section>

  {else}
    <div class="arc-settings">
      <div class="arc-col">
      <section class="om-panel">
        <div class="arc-head"><span class="arc-ico {if $progress.configured}green{/if}"><i class="bi bi-plug"></i></span>
          <div><h2>Połączenie z Sellasist</h2><p>{if $progress.configured}Konto połączone{else}Podaj konto i klucz API, aby pobrać archiwum.{/if}</p></div>
          {if $progress.configured}<span class="arc-pill ok">Połączono</span>{else}<span class="arc-pill">Brak połączenia</span>{/if}</div>
        <div class="arc-body">
          {if $progress.configured}<div class="arc-account"><i class="bi bi-check-circle-fill"></i><div><strong>{$progress.account|escape}</strong><span>klucz {$progress.key_hint|escape}</span></div></div>{/if}
          {if $canWrite}
          <form class="arc-form" method="post" action="index.php?controller=archive&action=save" autocomplete="off">
            <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="connect">
            <label class="arc-field">Konto Sellasist<span class="arc-input"><i class="bi bi-shop"></i><input name="account" value="{$progress.account|default:''|escape}" placeholder="np. altreo albo altreo.sellasist.pl" required></span></label>
            <label class="arc-field"><span>Klucz API{if $progress.configured} <small>(puste = bez zmian)</small>{/if}</span><span class="arc-input"><i class="bi bi-key"></i><input type="password" name="api_key" {if !$progress.configured}required{/if} autocomplete="new-password" placeholder="{if $progress.configured}••••••••{else}Wklej klucz API{/if}"></span></label>
            <button class="om-btn om-primary" type="submit"><i class="bi bi-plug"></i> {if $progress.configured}Zapisz i sprawdź połączenie{else}Połącz i rozpocznij import{/if}</button>
          </form>
          {/if}
        </div>
      </section>
      <section class="om-panel">
        <div class="arc-head"><span class="arc-ico"><i class="bi bi-question-lg"></i></span><div><h2>Jak uzyskać klucz API</h2><p>Trzy kroki w panelu Sellasist</p></div></div>
        <div class="arc-body">
          <ol class="arc-steps"><li>Zaloguj się do Sellasist.</li><li><span>Wejdź w <strong>Integracje → Klucze API</strong> i wygeneruj klucz (wystarczą uprawnienia do odczytu zamówień i dokumentów).</span></li><li>Wklej klucz tutaj. Klucz jest zapisany w bazie w postaci zaszyfrowanej.</li></ol>
          <div class="arc-note"><i class="bi bi-shield-check"></i><div>Import tylko <strong>czyta</strong> dane z Sellasist – niczego tam nie zmienia. Pobiera wszystkie zamówienia (od najstarszych), faktury, faktury korygujące, paragony i korekty paragonów, a potem szczegóły każdego rekordu. Działa w tle (cron co minutę, porcje ok. 30 s) i dokańcza się sam; przy otwartej stronie przycisk „Pobieraj teraz” przyspiesza import. Po zakończeniu archiwum co 15 min dopisuje nowe rekordy.</div></div>
        </div>
      </section>
      </div>
      <div class="arc-col">
      <section class="om-panel">
        <div class="arc-head"><span class="arc-ico"><i class="bi bi-cloud-download"></i></span>
          <div><h2>Import w tle</h2><p>{if !$progress.configured}Najpierw połącz konto.{elseif $progress.completed}Wszystko pobrane.{elseif $progress.enabled}Włączony – trwa pobieranie.{else}Wstrzymany.{/if}</p></div>
          {if !$progress.configured}<span class="arc-pill">Nieaktywny</span>{elseif $progress.last_error}<span class="arc-pill err">Błąd</span>{elseif $progress.completed}<span class="arc-pill ok">Aktualne</span>{elseif $progress.enabled}<span class="arc-pill run">W toku</span>{else}<span class="arc-pill warn">Wstrzymany</span>{/if}</div>
        <div class="arc-body">
          <div class="arc-stats">
            <div class="arc-stat"><small>Zamówienia</small><strong>{$progress.stats.orders}</strong><em>szczegóły: {$progress.stats.orders_detailed}</em>{if $progress.stats.orders_failed}<em class="bad">błędy: {$progress.stats.orders_failed}</em>{/if}
              <div class="arc-bar"><span style="width:{if $progress.stats.orders}{($progress.stats.orders_detailed * 100 / $progress.stats.orders)|string_format:'%.0f'}{else}0{/if}%"></span></div></div>
            <div class="arc-stat"><small>Dokumenty</small><strong>{$progress.stats.documents}</strong><em>szczegóły: {$progress.stats.documents_detailed}</em>{if $progress.stats.documents_failed}<em class="bad">błędy: {$progress.stats.documents_failed}</em>{/if}
              <div class="arc-bar"><span style="width:{if $progress.stats.documents}{($progress.stats.documents_detailed * 100 / $progress.stats.documents)|string_format:'%.0f'}{else}0{/if}%"></span></div></div>
          </div>
          <dl class="arc-meta">
            <dt>Zakres dat</dt><dd>{if $progress.stats.oldest}{$progress.stats.oldest|escape} – {$progress.stats.newest|escape}{else}—{/if}</dd>
            <dt>Zapytania API</dt><dd>{$progress.requests}</dd>
            <dt>Ostatnia porcja</dt><dd>{$progress.last_run|default:'—'|escape}{if $progress.last_run} UTC{/if}</dd>
          </dl>
          {if $progress.last_error}<div class="arc-error"><i class="bi bi-exclamation-triangle-fill"></i><div><strong>Ostatni błąd:</strong> {$progress.last_error|escape}{if $progress.backoff} (ponowienie za {$progress.backoff} s){/if}</div></div>{/if}
        </div>
        {if $canWrite && $progress.configured}
        <form class="arc-actions" method="post" action="index.php?controller=archive&action=save"><input type="hidden" name="csrf" value="{$csrf|escape}">
          {if $progress.enabled}<button class="om-btn" name="operation" value="disable"><i class="bi bi-pause-circle"></i> Wstrzymaj import w tle</button>{else}<button class="om-btn om-primary" name="operation" value="enable"><i class="bi bi-play-circle"></i> Wznów import w tle</button>{/if}
          {if $progress.stats.orders_failed || $progress.stats.documents_failed}<button class="om-btn" name="operation" value="retry_failed"><i class="bi bi-arrow-repeat"></i> Ponów nieudane</button>{/if}
          <button class="om-btn" name="operation" value="restart" data-arc-confirm="Pobrać listy od początku? Dane nie zostaną zdublowane."><i class="bi bi-skip-backward"></i> Pobierz listy od nowa</button>
          <button class="om-btn" name="operation" value="refresh_details" data-arc-confirm="Pobrać ponownie szczegóły wszystkich rekordów? To może potrwać wiele godzin."><i class="bi bi-cloud-arrow-down"></i> Odśwież wszystkie szczegóły</button>
        </form>
        {/if}
      </section>
      {if $canWrite && $progress.configured}
      <section class="om-panel arc-danger">
        <div class="arc-head"><span class="arc-ico red"><i class="bi bi-trash"></i></span><div><h2>Wyczyść archiwum</h2><p>Usuwa wszystkie pobrane zamówienia i dokumenty z lokalnej bazy.</p></div></div>
        <form method="post" action="index.php?controller=archive&action=save"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="clear">
          <p>Dane w Sellasist pozostaną bez zmian. Aby potwierdzić, wpisz <strong>USUŃ</strong>.</p>
          <input name="confirm" placeholder="wpisz USUŃ" autocomplete="off"><button class="om-btn arc-danger-btn" type="submit"><i class="bi bi-trash"></i> Wyczyść archiwum</button>
        </form>
      </section>
      {/if}
      </div>
    </div>
  {/if}
</div>
</main>
<script>
(function () {
  var csrf = {$csrf|json_encode nofilter};
  var canRun = {if $canWrite && $progress.configured}true{else}false{/if};
  {literal}
  document.querySelectorAll('[data-arc-filters]').forEach(function (b) {
    b.addEventListener('click', function () { var d = b.closest('form').querySelector('[data-arc-drawer]'); if (d) { d.classList.toggle('is-open'); b.setAttribute('aria-expanded', d.classList.contains('is-open') ? 'true' : 'false'); } });
  });
  document.querySelectorAll('[data-arc-href]').forEach(function (row) {
    row.addEventListener('click', function (e) { if (e.target.closest('a,button,input,select')) return; location.href = row.getAttribute('data-arc-href'); });
  });
  document.querySelectorAll('[data-arc-confirm]').forEach(function (b) {
    b.addEventListener('click', function (e) { if (!confirm(b.getAttribute('data-arc-confirm'))) e.preventDefault(); });
  });
  var raw = document.querySelector('[data-arc-raw]');
  if (raw) { var det = raw.previousElementSibling; det.addEventListener('toggle', function () { raw.hidden = !det.open; }); }

  var box = document.querySelector('[data-arc-progress]');
  if (!box || !canRun) return;
  var running = false, stop = false, btn = document.querySelector('[data-arc-run]'), live = document.querySelector('[data-arc-live-text]');
  function paint(p) {
    if (!p) return;
    box.querySelector('[data-arc-details-done]').textContent = p.details_done;
    box.querySelector('[data-arc-details-total]').textContent = p.details_total;
    box.querySelector('[data-arc-bar]').style.width = p.details_percent + '%';
    box.querySelector('[data-arc-status]').textContent = p.last_error ? p.last_error + (p.backoff ? ' (ponowienie za ' + p.backoff + ' s)' : '') : (p.last_run ? 'Ostatnia porcja: ' + p.last_run + ' UTC' : 'Czeka na pierwsze uruchomienie');
    (p.phases || []).forEach(function (ph) {
      var el = box.querySelector('[data-arc-phase="' + ph.key + '"]'); if (!el) return;
      el.classList.toggle('is-done', ph.done); el.classList.toggle('has-error', !!ph.error);
      el.querySelector('[data-arc-phase-count]').textContent = ph.stored;
      el.querySelector('[data-arc-phase-state]').textContent = ph.error ? ph.error : (ph.done ? 'lista kompletna' : 'pobieranie listy…');
    });
    if (live) live.textContent = p.completed ? 'Archiwum aktualne' : (running ? 'Pobieranie…' : (p.enabled ? 'Import w tle' : 'Import wstrzymany'));
  }
  function step(run) {
    var body = new URLSearchParams(); body.set('csrf', csrf); body.set('run', run ? '1' : '0');
    return fetch('index.php?controller=archive&action=step', { method: 'POST', body: body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.error || ('HTTP ' + r.status)); return j; }); });
  }
  function loop() {
    if (stop) { running = false; setBtn(); return; }
    step(true).then(function (j) {
      paint(j.progress);
      var rep = j.report || {};
      if (j.progress && j.progress.completed) { stop = true; }
      var wait = rep.skipped === 'backoff' ? Math.min(60, (rep.retry_in || 30)) * 1000 : (rep.skipped === 'busy' ? 8000 : 500);
      setTimeout(loop, wait);
    }).catch(function (e) { box.querySelector('[data-arc-status]').textContent = 'Błąd: ' + e.message; setTimeout(loop, 15000); });
  }
  function setBtn() { if (btn) { btn.querySelector('span').textContent = running ? 'Zatrzymaj' : 'Pobieraj teraz'; } if (live) live.textContent = running ? 'Pobieranie…' : live.textContent; }
  if (btn) btn.addEventListener('click', function () {
    if (running) { stop = true; btn.querySelector('span').textContent = 'Zatrzymywanie…'; return; }
    running = true; stop = false; setBtn(); loop();
  });
  // Bez kliknięcia strona tylko odświeża postęp importu z crona.
  setInterval(function () { if (!running && !document.hidden) step(false).then(function (j) { paint(j.progress); }).catch(function () {}); }, 20000);
  {/literal}
})();
</script>
