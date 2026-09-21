<link rel="stylesheet" href="dist/css/integrations.css?v=20260918-ship">
{assign var=ig value=$integrations}
<section class="sc-int" data-integrations data-open-add="{$ig.add|escape}" data-manual="{if $ig.manual}1{else}0{/if}">
  <div class="sc-int-head">
    <div>
      <div class="sc-int-eyebrow">MODUŁ INTEGRACJI</div>
      <h2>Kanały sprzedaży</h2>
      <p>Połącz marketplace’y i sklepy – zamówienia trafią do jednej kolejki. Każde połączenie należy tylko do Twojej firmy.</p>
    </div>
    {if $canWrite}<button class="sc-btn sc-btn-primary" type="button" data-add-open><i class="bi bi-plus-lg"></i> Dodaj kanał sprzedaży</button>{/if}
  </div>

  {if $ig.apiToken}
    <div class="sc-token-box">
      <div><strong><i class="bi bi-key"></i> Token API – skopiuj teraz</strong><span>Ze względów bezpieczeństwa nie pokażemy go ponownie.</span></div>
      <div class="sc-copy"><code data-copy-source>{$ig.apiToken.token|escape}</code><button type="button" class="sc-btn" data-copy><i class="bi bi-clipboard"></i> Kopiuj</button></div>
    </div>
  {/if}

  {if $ig.connections|count}
  <div class="sc-conn-list">
    {foreach $ig.connections as $c}
      {assign var=acc value=$c.account}
      <article class="sc-conn{if $ig.selected eq $c.id} is-open{/if}" id="sc-connection-{$c.id}">
        <div class="sc-conn-main">
          <span class="sc-logo" style="--brand: {$c.meta.color|escape}">{$c.meta.logo|escape}</span>
          <div class="sc-conn-title">
            <h3>{$c.name|escape}</h3>
            <div class="sc-conn-sub">{$c.meta.label|escape}{if !empty($c.meta.beta)} <span class="sc-beta">beta</span>{/if}{if $c.public.login|default:'' neq ''} · {$c.public.login|escape}{/if}{if $c.public.shop_url|default:'' neq ''} · {$c.public.shop_url|replace:'https://':''|escape}{/if}</div>
          </div>
          <div class="sc-conn-stats">
            {if $c.status eq 'active'}<span class="sc-pill ok"><i class="bi bi-check-circle-fill"></i> Połączono</span>
            {elseif $c.status eq 'pending'}<span class="sc-pill wait"><i class="bi bi-hourglass-split"></i> Czeka na zatwierdzenie</span>
            {else}<span class="sc-pill err"><i class="bi bi-exclamation-triangle-fill"></i> Wymaga uwagi</span>{/if}
            {if $acc}
              {if $c.platform neq 'api'}<span class="sc-pill {if $acc.enabled}on{else}off{/if}">{if $acc.enabled}Auto-import: wł.{else}Auto-import: wył.{/if}</span>{/if}
              {if $acc.import_from}<span class="sc-pill wait" data-backfill-badge="{$acc.id}"><i class="bi bi-cloud-download"></i> Pobieranie od {$acc.import_from|date_format:'%d.%m.%Y'}</span>{/if}
            {/if}
          </div>
          <div class="sc-conn-kpi"><strong>{$c.order_count}</strong><span>zamówień</span></div>
          <div class="sc-conn-kpi"><strong>{if $acc && $acc.last_sync}{$acc.last_sync|date_format:'%d.%m %H:%M'}{else}—{/if}</strong><span>ostatni import (UTC)</span></div>
          <div class="sc-conn-actions">
            {if $canWrite && $acc && $c.platform neq 'api' && $c.status eq 'active'}<form method="post" action="index.php?controller=integrations&action=fetchnow"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="connection_id" value="{$c.id}"><button class="sc-btn sc-btn-primary"><i class="bi bi-arrow-clockwise"></i> Pobierz zamówienia teraz</button></form>{/if}
            <button type="button" class="sc-btn sc-btn-ghost" data-toggle-conn aria-expanded="{if $ig.selected eq $c.id}true{else}false{/if}"><i class="bi bi-sliders"></i> Ustawienia</button>
          </div>
        </div>
        {if $c.last_error}<div class="sc-conn-error"><i class="bi bi-exclamation-octagon"></i> {$c.last_error|escape}</div>{/if}
        {if $acc && $acc.last_error}<div class="sc-conn-error"><i class="bi bi-exclamation-octagon"></i> Ostatni import: {$acc.last_error|escape}</div>{/if}

        <div class="sc-conn-settings">
          <div class="sc-settings-grid">
            {if $c.platform neq 'api' && $acc}
            <section class="sc-card">
              <h4><i class="bi bi-clock-history"></i> Pobierz starsze zamówienia</h4>
              <p>Wybierz datę – cron pobierze zamówienia złożone od tego dnia. Istniejące zamówienia zostaną odświeżone, bez duplikatów.</p>
              {if $canWrite}<form method="post" action="index.php?controller=integrations&action=backfill" class="sc-inline-form">
                <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="connection_id" value="{$c.id}">
                <label>Od dnia<input type="date" name="from_date" max="{$ig.today}" required></label>
                <button class="sc-btn sc-btn-primary"><i class="bi bi-cloud-download"></i> Zaplanuj import</button>
              </form>{/if}
              {if $acc.import_from}<p class="sc-note"><i class="bi bi-info-circle"></i> Cron pobiera zamówienia od {$acc.import_from|date_format:'%d.%m.%Y'} co minutę.</p>{/if}
            </section>
            <section class="sc-card">
              <h4><i class="bi bi-toggles"></i> Automatyzacja</h4>
              {if $canWrite}<form method="post" action="index.php?controller=integrations&action=settings" class="sc-switches">
                <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="connection_id" value="{$c.id}">
                <label class="sc-switch"><input type="checkbox" name="enabled" value="1" {if $acc.enabled}checked{/if}><span></span><div><strong>Automatyczny import</strong><small>Nowe i zmienione zamówienia co minutę (zadanie w tle).</small></div></label>
                {if $c.platform eq 'empik' || $c.platform eq 'mediamarkt'}<label class="sc-switch"><input type="checkbox" name="auto_accept" value="1" {if $acc.auto_accept}checked{/if}><span></span><div><strong>Automatyczna akceptacja</strong><small>Akceptuje nowe zamówienia, aby od razu pobrać adres i płatność.</small></div></label>{/if}
                <button class="sc-btn">Zapisz</button>
              </form>{/if}
            </section>
            {/if}

            {if $c.platform eq 'api'}
            <section class="sc-card sc-card-wide">
              <h4><i class="bi bi-code-slash"></i> API Twojego sklepu</h4>
              <dl class="sc-dl"><dt>Adres API</dt><dd><code>{$ig.apiBase|escape}</code></dd><dt>Autoryzacja</dt><dd><code>Authorization: Bearer &lt;token&gt;</code> (token kończy się na …{$c.public.token_hint|default:''|escape})</dd></dl>
              {include file='orders/integrations_api_docs.tpl'}
              {if $canWrite}<form method="post" action="index.php?controller=integrations&action=apitoken" onsubmit="return confirm('Nowy token natychmiast unieważni obecny. Kontynuować?')"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="connection_id" value="{$c.id}"><button class="sc-btn"><i class="bi bi-arrow-clockwise"></i> Wygeneruj nowy token</button></form>{/if}
            </section>
            {/if}

            <section class="sc-card">
              <h4><i class="bi bi-plug"></i> Połączenie</h4>
              {if $canWrite}<form method="post" action="index.php?controller=integrations&action=update" class="sc-fields">
                <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="connection_id" value="{$c.id}">
                <label>Nazwa w SalesCenter<input name="name" maxlength="150" value="{$c.name|escape}" required></label>
                {assign var=def value=$ig.catalog[$c.platform]}
                {if $def.auth eq 'fields' || ($def.auth eq 'woo' && $c.public.consumer_key|default:'' neq '')}
                  {foreach $def.fields as $f}{if empty($f.manual) || $def.auth eq 'woo'}
                    <label>{$f.label|escape}<input name="{$f.name}" type="{if $f.type eq 'password'}password{else}text{/if}" autocomplete="off" {if empty($f.secret)}value="{$c.public[$f.name]|default:''|escape}"{else}placeholder="•••••• (bez zmian)"{/if}></label>
                  {/if}{/foreach}
                  {if $def.auth eq 'woo'}<input type="hidden" name="manual" value="1">{/if}
                {/if}
                <button class="sc-btn">Zapisz zmiany</button>
              </form>
              <div class="sc-row">
                {if $c.platform neq 'api' && $c.status neq 'pending'}<form method="post" action="index.php?controller=integrations&action=test"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="connection_id" value="{$c.id}"><button class="sc-btn"><i class="bi bi-activity"></i> Testuj połączenie</button></form>{/if}
                {if $c.platform eq 'allegro'}<form method="post" action="index.php?controller=integrations&action=allegroconnect"><input type="hidden" name="csrf" value="{$csrf|escape}"><button class="sc-btn"><i class="bi bi-box-arrow-in-right"></i> Zaloguj ponownie</button></form>{/if}
                <form method="post" action="index.php?controller=integrations&action=disconnect" onsubmit="return confirm('Odłączyć „{$c.name|escape:'javascript'|escape}”? Pobrane zamówienia zostaną w SalesCenter.')"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="connection_id" value="{$c.id}"><button class="sc-btn sc-btn-danger"><i class="bi bi-x-circle"></i> Odłącz</button></form>
              </div>
              {/if}
              <p class="sc-note">Sprawdzono: {$c.last_check_at|default:'—'|escape} UTC · dodano {$c.created_at|escape} UTC</p>
            </section>
          </div>
        </div>
      </article>
    {/foreach}
  </div>
  {else}
    <div class="sc-empty">
      <div class="sc-empty-logos">{foreach $ig.catalog as $code=>$p}<span class="sc-logo sm" style="--brand: {$p.color|escape}">{$p.logo|escape}</span>{/foreach}</div>
      <h3>Podłącz pierwszy kanał sprzedaży</h3>
      <p>Allegro łączysz jednym logowaniem, pozostałe kanały – kluczem z panelu sprzedawcy. Przy każdej platformie znajdziesz instrukcję krok po kroku.</p>
      {if $canWrite}<button class="sc-btn sc-btn-primary" type="button" data-add-open><i class="bi bi-plus-lg"></i> Dodaj kanał sprzedaży</button>{/if}
    </div>
  {/if}

  {if $canWrite}
  <dialog class="sc-dialog" data-add-dialog aria-labelledby="sc-add-title">
    <div class="sc-dialog-head">
      <div><h3 id="sc-add-title" data-step-title>Dodaj kanał sprzedaży</h3><p data-step-sub>Wybierz, skąd chcesz pobierać zamówienia.</p></div>
      <button type="button" class="sc-icon-btn" data-add-close aria-label="Zamknij"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="sc-picker" data-picker>
      {foreach ['Marketplace','Sklep internetowy'] as $group}
        <h4>{$group}</h4>
        <div class="sc-picker-grid">
        {foreach $ig.catalog as $code=>$p}{if $p.group eq $group}
          <button type="button" class="sc-tile" data-pick="{$code}">
            <span class="sc-logo" style="--brand: {$p.color|escape}">{$p.logo|escape}</span>
            <strong>{$p.label|escape}{if !empty($p.beta)} <span class="sc-beta">beta</span>{/if}</strong>
            <small>{if $p.auth eq 'oauth'}Logowanie kontem{elseif $p.auth eq 'woo'}Logowanie do sklepu{elseif $p.auth eq 'api'}Token API{else}Klucz z panelu{/if}</small>
          </button>
        {/if}{/foreach}
        </div>
      {/foreach}
    </div>

    {foreach $ig.catalog as $code=>$p}
    <div class="sc-setup" data-setup="{$code}" data-title="{$p.label|escape}" data-sub="{$p.tagline|escape}" hidden>
      <button type="button" class="sc-back" data-back><i class="bi bi-arrow-left"></i> Wszystkie kanały</button>
      <div class="sc-setup-grid">
        <div class="sc-guide">
          <div class="sc-guide-head"><span class="sc-logo" style="--brand: {$p.color|escape}">{$p.logo|escape}</span><div><strong>Jak połączyć {$p.label|escape}</strong><small>{$p.features|join:' · '|escape}</small></div></div>
          <ol class="sc-steps">{foreach $p.steps as $step}<li>{$step|escape}</li>{/foreach}</ol>
          {if $p.tips|count}<div class="sc-tips">{foreach $p.tips as $tip}<p><i class="bi bi-lightbulb"></i> {$tip|escape}</p>{/foreach}</div>{/if}
          {if $p.links|count}<div class="sc-links">{foreach $p.links as $link}<a href="{$link[0]|escape}" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right"></i> {$link[1]|escape}</a>{/foreach}</div>{/if}
        </div>
        <div class="sc-connect">
          {if $p.auth eq 'oauth'}
            {if $ig.allegroConfigured}
              <form method="post" action="index.php?controller=integrations&action=allegroconnect" class="sc-oauth">
                <input type="hidden" name="csrf" value="{$csrf|escape}">
                <p>Zostaniesz przeniesiony na stronę Allegro. Nie podajesz nam hasła – Allegro przekazuje tylko uprawnienie do zamówień.</p>
                <button class="sc-btn sc-btn-brand" style="--brand: {$p.color|escape}"><i class="bi bi-box-arrow-in-right"></i> Zaloguj przez Allegro</button>
              </form>
            {else}
              <div class="sc-allegro-setup">
                <div class="sc-setup-badge"><i class="bi bi-hourglass-split"></i> Logowanie Allegro nie jest jeszcze włączone</div>
                {if $currentUser.is_headmaster}
                  <p>Włącz je raz dla wszystkich firm w <a href="index.php?controller=administration#allegro-app">Administracja SalesCenter → Aplikacja Allegro</a>. Potem wrócisz tu i klikniesz „Zaloguj przez Allegro”.</p>
                {else}
                  <p>Administrator SalesCenter jeszcze nie włączył połączeń z Allegro. Spróbuj ponownie później.</p>
                {/if}
              </div>
            {/if}
          {elseif $p.auth eq 'api'}
            <form method="post" action="index.php?controller=integrations&action=apitoken" class="sc-fields">
              <input type="hidden" name="csrf" value="{$csrf|escape}">
              <label>Nazwa sklepu w SalesCenter<input name="name" maxlength="150" placeholder="np. Sklep mojafirma.pl" required></label>
              <button class="sc-btn sc-btn-primary"><i class="bi bi-key"></i> Utwórz token API</button>
            </form>
            <div class="sc-mini-docs"><strong>Adres API:</strong> <code>{$ig.apiBase|escape}</code>{include file='orders/integrations_api_docs.tpl'}</div>
          {elseif $p.auth eq 'woo'}
            <div data-woo-auto{if $ig.manual} hidden{/if}>
              <form method="post" action="index.php?controller=integrations&action=woostart" class="sc-fields">
                <input type="hidden" name="csrf" value="{$csrf|escape}">
                <label>Adres sklepu<input name="shop_url" type="url" placeholder="https://mojsklep.pl" required></label>
                <button class="sc-btn sc-btn-brand" style="--brand: {$p.color|escape}" {if !$ig.callbackHttps}disabled title="Wymaga otwarcia SalesCenter przez https://"{/if}><i class="bi bi-wordpress"></i> Zaloguj do WordPress i połącz</button>
              </form>
              <button type="button" class="sc-link-btn" data-woo-manual>Wolisz wkleić klucze REST API ręcznie?</button>
            </div>
            <div data-woo-keys{if !$ig.manual} hidden{/if}>
              <form method="post" action="index.php?controller=integrations&action=connect" class="sc-fields">
                <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="platform" value="woocommerce"><input type="hidden" name="manual" value="1">
                {foreach $p.fields as $f}<label>{$f.label|escape}<input name="{$f.name}" type="{if $f.type eq 'password'}password{elseif $f.type eq 'url'}url{else}text{/if}" placeholder="{$f.placeholder|default:''|escape}" autocomplete="off" required></label>{/foreach}
                <button class="sc-btn sc-btn-primary"><i class="bi bi-plug"></i> Połącz kluczami</button>
              </form>
              <button type="button" class="sc-link-btn" data-woo-auto-back>Wróć do logowania przez WordPress</button>
            </div>
          {else}
            <form method="post" action="index.php?controller=integrations&action=connect" class="sc-fields" data-connect-form>
              <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="platform" value="{$code}">
              {foreach $p.fields as $f}{if empty($f.advanced)}
                <label>{$f.label|escape}<input name="{$f.name}" type="{if $f.type eq 'password'}password{elseif $f.type eq 'url'}url{else}text{/if}" placeholder="{$f.placeholder|default:''|escape}" autocomplete="off" {if !empty($f.required)}required{/if}>{if !empty($f.help)}<small>{$f.help|escape}</small>{/if}</label>
              {/if}{/foreach}
              {assign var=hasAdvanced value=false}{foreach $p.fields as $f}{if !empty($f.advanced)}{assign var=hasAdvanced value=true}{/if}{/foreach}
              {if $hasAdvanced}<details class="sc-advanced"><summary>Ustawienia zaawansowane</summary>
                {foreach $p.fields as $f}{if !empty($f.advanced)}<label>{$f.label|escape}<input name="{$f.name}" type="text" placeholder="{$f.placeholder|default:''|escape}" autocomplete="off">{if !empty($f.help)}<small>{$f.help|escape}</small>{/if}</label>{/if}{/foreach}
              </details>{/if}
              <label>Nazwa w SalesCenter <small>(opcjonalnie – nadamy ją automatycznie)</small><input name="name" maxlength="150"></label>
              <button class="sc-btn sc-btn-primary" data-connect-submit><i class="bi bi-plug"></i> Sprawdź i połącz</button>
              <p class="sc-note"><i class="bi bi-shield-lock"></i> Dane dostępowe są szyfrowane i widoczne tylko dla Twojej firmy.</p>
            </form>
          {/if}
        </div>
      </div>
    </div>
    {/foreach}
  </dialog>
  {/if}
</section>
<script src="dist/js/integrations.js?v=20260918-ship" defer></script>
