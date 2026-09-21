<link rel="stylesheet" href="dist/css/integrations.css?v=20260918-ship">
{assign var=sv value=$shippingView|default:['selected'=>0,'add'=>'']}
<section class="sc-int" data-integrations data-open-add="{$sv.add|escape}" data-manual="0" data-picker-title="Dodaj konto nadawcze" data-picker-sub="Wybierz, przez kogo chcesz nadawać paczki." data-setup-prefix="Podłącz: ">
  <div class="sc-int-head">
    <div>
      <div class="sc-int-eyebrow">MODUŁ PRZESYŁEK</div>
      <h2>Konta nadawcze</h2>
      <p>Podłącz przewoźników, brokerów i wysyłkę na umowach marketplace’ów – nadasz paczkę prosto z zamówienia. Dane dostępowe są szyfrowane i widoczne tylko dla Twojej firmy.</p>
    </div>
    {if $canWrite}<button class="sc-btn sc-btn-primary" type="button" data-add-open><i class="bi bi-plus-lg"></i> Dodaj konto nadawcze</button>{/if}
  </div>

  {if $carrierAccounts|count}
  <div class="sc-conn-list">
    {foreach $shippingProviders as $sp}{foreach $sp.accounts as $c}
      <article class="sc-conn{if $sv.selected eq $c.id} is-open{/if}" id="sc-carrier-{$c.id}">
        <div class="sc-conn-main">
          <span class="sc-logo" style="--brand: {$sp.color|escape}; color: {$sp.ink|escape}">{$sp.badge.text|escape}</span>
          <div class="sc-conn-title">
            <h3>{$c.name|escape}</h3>
            <div class="sc-conn-sub">{$sp.label|escape}{if $c.source_name} · {$sp.source_label|escape}: {$c.source_name|escape}{/if}{if isset($c.public.environment) && $c.public.environment eq 'sandbox'} · sandbox{/if}{if !empty($c.public.posting_point_id)} · punkt nadania #{$c.public.posting_point_id|escape}{/if}</div>
          </div>
          <div class="sc-conn-stats">
            {if $c.enabled}<span class="sc-pill ok"><i class="bi bi-check-circle-fill"></i> Aktywne</span>{else}<span class="sc-pill off">Wyłączone</span>{/if}
            {if $sp.source_platform && !$c.source_name}<span class="sc-pill err"><i class="bi bi-exclamation-triangle-fill"></i> Brak konta {$sp.source_label|escape}</span>{/if}
            {if $sp.capabilities.source_tracking eq 'auto'}<span class="sc-pill on"><i class="bi bi-cloud-check"></i> Numer do źródła sam</span>{/if}
          </div>
          <div class="sc-conn-kpi"><strong>{$c.shipment_count|default:0}</strong><span>przesyłek</span></div>
          <div class="sc-conn-kpi"><strong>{if !empty($c.last_shipment_at)}{$c.last_shipment_at|date_format:'%d.%m %H:%M'}{else}—{/if}</strong><span>ostatnie nadanie (UTC)</span></div>
          <div class="sc-conn-actions">
            <button type="button" class="sc-btn sc-btn-ghost" data-toggle-conn aria-expanded="{if $sv.selected eq $c.id}true{else}false{/if}"><i class="bi bi-sliders"></i> Ustawienia</button>
          </div>
        </div>

        <div class="sc-conn-settings">
          <div class="sc-settings-grid">
            <section class="sc-card">
              <h4><i class="bi bi-stars"></i> Co potrafi</h4>
              <ul class="sc-cap-list">
                <li class="{if $sp.capabilities.label}is-on{/if}"><i class="bi bi-file-earmark-pdf"></i> Etykiety PDF</li>
                <li class="{if $sp.capabilities.tracking}is-on{/if}"><i class="bi bi-broadcast-pin"></i> Śledzenie statusu</li>
                <li class="{if $sp.capabilities.cancel}is-on{/if}"><i class="bi bi-x-octagon"></i> Anulowanie z panelu</li>
                <li class="{if $sp.capabilities.valuation}is-on{/if}"><i class="bi bi-calculator"></i> Wycena na żywo</li>
                <li class="{if $sp.capabilities.cod ne 'none'}is-on{/if}"><i class="bi bi-cash-coin"></i> {if $sp.capabilities.cod eq 'form'}Pobranie ustawiane w formularzu{elseif $sp.capabilities.cod eq 'order'}Pobranie wg zamówienia{else}Bez pobrania{/if}</li>
                <li class="{if $sp.capabilities.source_tracking eq 'auto'}is-on{/if}"><i class="bi bi-cloud-check"></i> {if $sp.capabilities.source_tracking eq 'auto'}Numer trafia do źródła automatycznie{else}Numer do źródła przyciskiem w zamówieniu{/if}</li>
                {if $sp.capabilities.pickup_protocol}<li class="is-on"><i class="bi bi-clipboard-check"></i> Protokół odbioru przez kuriera</li>{/if}
              </ul>
            </section>
            <section class="sc-card">
              <h4><i class="bi bi-info-circle"></i> Jak to działa</h4>
              <ul class="sc-info-list">{foreach $sp.info as $line}<li>{$line|escape}</li>{/foreach}</ul>
              {if $sp.docs}<div class="sc-links"><a href="{$sp.docs|escape}" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right"></i> Dokumentacja API</a></div>{/if}
            </section>
            {if !empty($c.public.posting_points)}
            <section class="sc-card">
              <h4><i class="bi bi-geo-alt"></i> Punkty nadania ({$c.public.posting_points|count})</h4>
              <ul class="sc-info-list sc-points">{foreach $c.public.posting_points as $pt}<li><strong>#{$pt.id|escape} {$pt.name|escape}</strong>{if $pt.default} <span class="sc-pill ok">domyślny</span>{/if}<small>{$pt.address|escape}</small></li>{/foreach}</ul>
              <p class="sc-note"><i class="bi bi-arrow-repeat"></i> Lista odświeża się przy zapisie połączenia.</p>
            </section>
            {/if}
            <section class="sc-card">
              <h4><i class="bi bi-plug"></i> Połączenie</h4>
              {if $canWrite}
                <form method="post" action="index.php?controller=orders&action=save" class="sc-fields" autocomplete="off">
                  <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="carrier_account"><input type="hidden" name="tab" value="shipments"><input type="hidden" name="provider" value="{$sp.key|escape}"><input type="hidden" name="carrier_account_id" value="{$c.id}">
                  {include file='orders/shipping_fields.tpl' sp=$sp account=$c}
                  <button class="sc-btn">Zapisz zmiany</button>
                </form>
                <div class="sc-row">
                  <form method="post" action="index.php?controller=orders&action=save"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="carrier_account_toggle"><input type="hidden" name="tab" value="shipments"><input type="hidden" name="carrier_account_id" value="{$c.id}"><input type="hidden" name="enabled" value="{if $c.enabled}0{else}1{/if}"><button class="sc-btn">{if $c.enabled}<i class="bi bi-pause-circle"></i> Wyłącz{else}<i class="bi bi-play-circle"></i> Włącz{/if}</button></form>
                  {if !$c.shipment_count}<form method="post" action="index.php?controller=orders&action=save" onsubmit="return confirm('Odłączyć „{$c.name|escape:'javascript'|escape}”?')"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="carrier_account_delete"><input type="hidden" name="tab" value="shipments"><input type="hidden" name="carrier_account_id" value="{$c.id}"><button class="sc-btn sc-btn-danger"><i class="bi bi-x-circle"></i> Odłącz</button></form>{/if}
                </div>
                {if $c.shipment_count}<p class="sc-note"><i class="bi bi-info-circle"></i> Konto ma przesyłki, więc można je tylko wyłączyć – statusy i etykiety pozostaną dostępne.</p>{/if}
              {/if}
              <p class="sc-note">Zaktualizowano: {$c.updated_at|escape} UTC</p>
            </section>
          </div>
        </div>
      </article>
    {/foreach}{/foreach}
  </div>
  {else}
    <div class="sc-empty">
      <div class="sc-empty-logos">{foreach $shippingProviders as $sp}<span class="sc-logo sm" style="--brand: {$sp.color|escape}; color: {$sp.ink|escape}">{$sp.badge.text|escape}</span>{/foreach}</div>
      <h3>Podłącz pierwsze konto nadawcze</h3>
      <p>Wysyłam z Allegro i Wysyłam z Erli korzystają z kont podłączonych w zakładce Konta, a przewoźnicy – z klucza API. Przy każdym operatorze znajdziesz instrukcję krok po kroku.</p>
      {if $canWrite}<button class="sc-btn sc-btn-primary" type="button" data-add-open><i class="bi bi-plus-lg"></i> Dodaj konto nadawcze</button>{/if}
    </div>
  {/if}

  {if $canWrite}
  <dialog class="sc-dialog" data-add-dialog aria-labelledby="sc-carrier-add-title">
    <div class="sc-dialog-head">
      <div><h3 id="sc-carrier-add-title" data-step-title>Dodaj konto nadawcze</h3><p data-step-sub>Wybierz, przez kogo chcesz nadawać paczki.</p></div>
      <button type="button" class="sc-icon-btn" data-add-close aria-label="Zamknij"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="sc-picker" data-picker>
      {foreach ['Umowy marketplace','Przewoźnicy i brokerzy'] as $group}
        <h4>{$group}</h4>
        <div class="sc-picker-grid">
        {foreach $shippingProviders as $sp}{if $sp.group eq $group}
          <button type="button" class="sc-tile" data-pick="{$sp.key|escape}">
            <span class="sc-logo" style="--brand: {$sp.color|escape}; color: {$sp.ink|escape}">{$sp.badge.text|escape}</span>
            <strong>{$sp.label|escape}</strong>
            <small>{if $sp.source_platform}Konto {$sp.source_label|escape}{else}Klucz API{/if}{if $sp.accounts|count} · podłączono {$sp.accounts|count}{/if}</small>
          </button>
        {/if}{/foreach}
        </div>
      {/foreach}
    </div>

    {foreach $shippingProviders as $sp}
    <div class="sc-setup" data-setup="{$sp.key|escape}" data-title="{$sp.label|escape}" data-sub="{$sp.description|escape}" hidden>
      <button type="button" class="sc-back" data-back><i class="bi bi-arrow-left"></i> Wszyscy operatorzy</button>
      <div class="sc-setup-grid">
        <div class="sc-guide">
          <div class="sc-guide-head"><span class="sc-logo" style="--brand: {$sp.color|escape}; color: {$sp.ink|escape}">{$sp.badge.text|escape}</span><div><strong>Jak podłączyć {$sp.label|escape}</strong><small>{$sp.features|join:' · '|escape}</small></div></div>
          <ol class="sc-steps">{foreach $sp.steps as $step}<li>{$step|escape}</li>{/foreach}</ol>
          {if $sp.info|count}<div class="sc-tips">{foreach $sp.info as $tip}<p><i class="bi bi-lightbulb"></i> {$tip|escape}</p>{/foreach}</div>{/if}
          {if $sp.docs}<div class="sc-links"><a href="{$sp.docs|escape}" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right"></i> Dokumentacja API</a></div>{/if}
        </div>
        <div class="sc-connect">
          {if $sp.source_platform && !$sp.source_accounts}
            <div class="sc-allegro-setup">
              <div class="sc-setup-badge"><i class="bi bi-1-circle-fill"></i> Najpierw podłącz konto {$sp.source_label|escape}</div>
              <p>{$sp.label|escape} korzysta z autoryzacji konta sprzedaży. Dodaj je w zakładce Konta, a potem wróć tutaj.</p>
              <a class="sc-btn sc-btn-primary" href="?controller=orders&tab=accounts&add={$sp.source_platform|escape:'url'}"><i class="bi bi-plug"></i> Podłącz {$sp.source_label|escape}</a>
            </div>
          {else}
            <form method="post" action="index.php?controller=orders&action=save" class="sc-fields" data-connect-form autocomplete="off">
              <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="carrier_account"><input type="hidden" name="tab" value="shipments"><input type="hidden" name="provider" value="{$sp.key|escape}">
              {include file='orders/shipping_fields.tpl' sp=$sp account=null}
              <button class="sc-btn sc-btn-primary" data-connect-submit><i class="bi bi-plug"></i> {if $sp.source_platform}Sprawdź i podłącz{else}Zapisz i podłącz{/if}</button>
              <p class="sc-note"><i class="bi bi-shield-lock"></i> Klucze są szyfrowane. Ta sama nazwa połączenia nadpisze dane istniejącego konta.</p>
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
