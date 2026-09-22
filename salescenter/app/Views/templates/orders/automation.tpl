{assign var=oaView value=$automation|default:[]}
{assign var=oaStats value=$oaView.stats|default:['active'=>0,'paused'=>0,'runs_24h'=>0,'errors_24h'=>0]}
{assign var=oaRules value=$rules|default:[]}
<section class="oa" data-oa-page>
  <header class="oa-hero">
    <div class="oa-hero-main">
      <span class="oa-hero-icon"><i class="bi bi-lightning-charge-fill"></i></span>
      <div>
        <div class="oa-eyebrow">AUTOMATYZACJE ZAMÓWIEŃ</div>
        <h2>Akcje automatyczne</h2>
        <p>Po lewej wyzwalacz i warunki, po prawej efekty wykonywane po kolei. Reguły reagują na import, płatność, statusy, dokumenty i przesyłki, działają po czasie albo z przycisku w zamówieniu.</p>
      </div>
    </div>
    <div class="oa-hero-stats" aria-label="Podsumowanie automatyzacji">
      <div><strong>{$oaStats.active}</strong><span>aktywne</span></div>
      <div><strong>{$oaStats.paused}</strong><span>wstrzymane</span></div>
      <div><strong>{$oaStats.runs_24h}</strong><span>wykonań / 24 h</span></div>
      <div class="{if $oaStats.errors_24h}is-error{/if}"><strong>{$oaStats.errors_24h}</strong><span>błędów / 24 h</span></div>
    </div>
    {if $canWrite}<a class="om-btn om-primary oa-hero-button" href="?controller=orders&tab=rules&rule=new#oa-editor"><i class="bi bi-plus-lg"></i> Nowa automatyzacja</a>{/if}
  </header>

  {if $canWrite && ($oaView.edit|default:null)}{include file='orders/automation_editor.tpl'}{/if}

  <div class="oa-layout">
    <div class="oa-main">
      <div class="oa-section-title">
        <h3>Twoje automatyzacje <span>{$oaRules|count}</span></h3>
        <p>Dla każdego zdarzenia reguły są sprawdzane od góry do dołu.</p>
      </div>
      <div class="oa-rules">
      {foreach $oaRules as $r}
        <article class="oa-rule{if !$r.enabled} is-paused{/if}" id="oa-rule-{$r.id}">
          <header class="oa-rule-head">
            {if $canWrite}<form method="post" action="?controller=orders&action=save"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="toggle_rule"><input type="hidden" name="tab" value="rules"><input type="hidden" name="rule_id" value="{$r.id}"><input type="hidden" name="enabled" value="{if $r.enabled}0{else}1{/if}"><button type="submit" class="oa-switch{if $r.enabled} is-on{/if}" role="switch" aria-checked="{if $r.enabled}true{else}false{/if}" title="{if $r.enabled}Wstrzymaj automatyzację{else}Włącz automatyzację{/if}" aria-label="{if $r.enabled}Wstrzymaj{else}Włącz{/if}: {$r.name|escape}"></button></form>{else}<span class="oa-switch{if $r.enabled} is-on{/if}" aria-hidden="true"></span>{/if}
            <span class="oa-rule-no" title="Kolejność wykonywania">{$r.number}</span>
            <div class="oa-rule-title">
              <h4>{$r.name|escape}</h4>
              <div class="oa-badges">
                {if !$r.enabled}<span class="oa-badge is-paused"><i class="bi bi-pause-fill"></i> Wstrzymana</span>{/if}
                {if $r.options.run_limit eq 'once'}<span class="oa-badge is-once"><i class="bi bi-1-circle"></i> Raz na zamówienie</span>{else}<span class="oa-badge"><i class="bi bi-arrow-repeat"></i> Przy każdym zdarzeniu</span>{/if}
                {if $r.options.match eq 'any' && $r.conditions}<span class="oa-badge"><i class="bi bi-signpost-split"></i> Dowolny warunek</span>{elseif $r.options.match eq 'mixed'}<span class="oa-badge"><i class="bi bi-signpost-split"></i> Warunki ORAZ / LUB</span>{/if}
                {if $r.options.button_order}<span class="oa-badge is-button"><i class="bi bi-hand-index-thumb"></i> Przycisk w zamówieniu</span>{/if}
                {if $r.options.button_order && $r.options.shortcut}<span class="oa-badge is-button" title="Skrót klawiszowy w zamówieniu"><i class="bi bi-keyboard"></i> {$r.options.shortcut|escape}</span>{/if}
                {if $r.variants gt 1}<span class="oa-badge" title="Reguły o tej samej nazwie tworzą jeden przycisk — uruchamia się pierwsza (od góry), której warunki pasują."><i class="bi bi-diagram-2"></i> Wariant · {$r.variants} o tej nazwie</span>{/if}
                {if $r.options.button_list}<span class="oa-badge is-button"><i class="bi bi-list-check"></i> Akcja na liście</span>{/if}
                {if $r.options.stop_on_error}<span class="oa-badge"><i class="bi bi-sign-stop"></i> Stop po błędzie</span>{/if}
              </div>
            </div>
            {if $canWrite}<div class="oa-rule-tools">
              <a class="oa-icon-btn" href="?controller=orders&tab=rules&rule={$r.id}#oa-editor" title="Edytuj" aria-label="Edytuj {$r.name|escape}"><i class="bi bi-pencil"></i></a>
              <form method="post" action="?controller=orders&action=save"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="rule_move"><input type="hidden" name="tab" value="rules"><input type="hidden" name="rule_id" value="{$r.id}"><input type="hidden" name="direction" value="up"><button class="oa-icon-btn" type="submit" title="Wykonuj wcześniej" aria-label="Przesuń w górę" {if $r@first}disabled{/if}><i class="bi bi-arrow-up"></i></button></form>
              <form method="post" action="?controller=orders&action=save"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="rule_move"><input type="hidden" name="tab" value="rules"><input type="hidden" name="rule_id" value="{$r.id}"><input type="hidden" name="direction" value="down"><button class="oa-icon-btn" type="submit" title="Wykonuj później" aria-label="Przesuń w dół" {if $r@last}disabled{/if}><i class="bi bi-arrow-down"></i></button></form>
              <form method="post" action="?controller=orders&action=save"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="rule_duplicate"><input type="hidden" name="tab" value="rules"><input type="hidden" name="rule_id" value="{$r.id}"><button class="oa-icon-btn" type="submit" title="Duplikuj" aria-label="Duplikuj {$r.name|escape}"><i class="bi bi-copy"></i></button></form>
              <form method="post" action="?controller=orders&action=save" data-confirm-action="Usunąć automatyzację „{$r.name|escape}” i jej dziennik wykonań? Tej operacji nie można cofnąć."><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="rule_delete"><input type="hidden" name="tab" value="rules"><input type="hidden" name="rule_id" value="{$r.id}"><button class="oa-icon-btn is-danger" type="submit" title="Usuń" aria-label="Usuń {$r.name|escape}"><i class="bi bi-trash"></i></button></form>
            </div>{/if}
          </header>
          <div class="oa-flow">
            <div class="oa-flow-side oa-when">
              <div class="oa-flow-label"><i class="bi bi-lightning-charge-fill"></i> KIEDY</div>
              <div class="oa-trigger-chips">{foreach $r.trigger_items as $t}<span class="oa-trigger-chip"><i class="bi {$t.icon|escape}"></i>{$t.label|escape}</span>{/foreach}</div>
              {if $r.delay_label}<p class="oa-delay-note"><i class="bi bi-hourglass-split"></i> {$r.delay_label|escape}</p>{/if}
              <div class="oa-flow-label is-if"><i class="bi bi-funnel-fill"></i> JEŻELI{if $r.condition_items|count gt 1} · {if $r.options.match eq 'any'}DOWOLNY{elseif $r.options.match eq 'mixed'}ORAZ / LUB{else}WSZYSTKIE{/if}{/if}</div>
              <ul class="oa-cond-list">{foreach $r.condition_items as $c}<li>{if !$c@first && $r.options.match eq 'mixed'}{if $r.conditions[$c@index].join eq 'or'}<span class="oa-cond-join is-or">LUB</span>{else}<span class="oa-cond-join">ORAZ</span>{/if}{/if}{$c|escape}</li>{foreachelse}<li class="is-any">Każde zamówienie</li>{/foreach}</ul>
            </div>
            <div class="oa-flow-arrow" aria-hidden="true"><span><i class="bi bi-arrow-right"></i></span></div>
            <div class="oa-flow-side oa-then">
              <div class="oa-flow-label"><i class="bi bi-play-circle-fill"></i> WYKONAJ PO KOLEI</div>
              <ol class="oa-step-list">{foreach $r.action_items as $a}<li class="{if $a.warning}is-warning{/if}"><span class="oa-step-no">{$a@iteration}</span><span class="oa-step-icon"><i class="bi {$a.icon|escape}"></i></span><span class="oa-step-text"><strong>{$a.label|escape}</strong>{if $a.detail}<small title="{$a.detail|escape}">{$a.detail|escape}</small>{/if}</span></li>{/foreach}</ol>
            </div>
          </div>
          <footer class="oa-rule-foot">
            <span><i class="bi bi-activity"></i>{$r.runs_total} wykonań</span>
            {if $r.runs_errors}<span class="is-error"><i class="bi bi-exclamation-triangle"></i>{$r.runs_errors} z błędami</span>{/if}
            <span><i class="bi bi-clock-history"></i>{if $r.last_run_at}Ostatnio {$r.last_run_at|escape} UTC{else}Jeszcze nie uruchomiono{/if}</span>
          </footer>
        </article>
      {foreachelse}
        <div class="oa-empty">
          <div class="oa-empty-flow" aria-hidden="true"><span class="is-when"><i class="bi bi-lightning-charge-fill"></i></span><i class="bi bi-arrow-right"></i><span class="is-if"><i class="bi bi-funnel-fill"></i></span><i class="bi bi-arrow-right"></i><span class="is-then"><i class="bi bi-check2-all"></i></span></div>
          <h3>Zbuduj pierwszą automatyzację</h3>
          <p>Na przykład: gdy zamówienie zostanie opłacone i jest z Allegro, przenieś je do pakowania, dodaj tag i wystaw paragon — jeden krok po drugim.</p>
          {if $canWrite}<a class="om-btn om-primary" href="?controller=orders&tab=rules&rule=new#oa-editor"><i class="bi bi-plus-lg"></i> Nowa automatyzacja</a>{/if}
        </div>
      {/foreach}
      </div>
    </div>

    <aside class="oa-side">
      {if $canWrite}<section class="oa-panel">
        <h3><i class="bi bi-magic"></i> Szybki start</h3>
        <p>Gotowe przepisy do dopasowania. Otworzą się w edytorze — nic nie zapisze się bez Twojej zgody.</p>
        <div class="oa-template-list">
          {foreach [ ['paid_to_pack','bi-cash-coin','Opłacone → do spakowania','Status i tag po potwierdzeniu płatności'], ['preferred_document','bi-receipt','Dokument po opłaceniu','Paragon lub faktura zgodnie z wyborem klienta'], ['company_invoice','bi-building','Firma z NIP → faktura','Ustaw fakturę, gdy klient podał NIP'], ['cod_flag','bi-truck','Pobranie → oznacz','Tag i notatka z kwotą do pobrania'], ['ship_ready','bi-box-seam','Gotowe → nadaj i drukuj','Przesyłka, etykieta i numer do marketplace'], ['tracking_shipped','bi-cloud-check','Numer w marketplace → Wysłane','Zmień status po przekazaniu numeru'], ['delivered_done','bi-house-check','Doręczone → Zakończone','Reaguj na status od przewoźnika'], ['unpaid_reminder','bi-hourglass-split','Nieopłacone 48 h → e-mail','Przypomnienie o płatności po czasie'], ['abroad','bi-globe2','Wysyłka za granicę','Tag dla zamówień spoza Polski'], ['manual_packed','bi-hand-index-thumb','Przycisk „Spakowane”','Ręczny przycisk w zamówieniu i na liście']] as $tpl}<a class="oa-template" href="?controller=orders&tab=rules&rule=new&template={$tpl[0]}#oa-editor"><span class="oa-template-icon"><i class="bi {$tpl[1]}"></i></span><span><strong>{$tpl[2]}</strong><small>{$tpl[3]}</small></span><i class="bi bi-chevron-right"></i></a>{/foreach}
        </div>
      </section>{/if}

      <section class="oa-panel">
        <h3><i class="bi bi-journal-check"></i> Dziennik wykonań</h3>
        <p>Ostatnie uruchomienia wszystkich automatyzacji.</p>
        <div class="oa-log-list">
          {foreach $oaView.log|default:[] as $run}
            <article class="oa-log-item is-{$run.result|escape}">
              <span class="oa-log-dot" title="{$run.result_label|escape}"></span>
              <div>
                <strong>{$run.rule_name|escape}</strong> <a href="?controller=orders&id={$run.order_id}#om-automation">#{$run.order_id}</a>
                <small>{$run.created_at|escape} UTC · {$run.trigger_label|escape} · <b>{$run.result_label|escape}</b></small>
                {if $run.message}<p title="{$run.message|escape}">{$run.message|escape}</p>{/if}
              </div>
            </article>
          {foreachelse}
            <div class="oa-log-empty"><i class="bi bi-inbox"></i> Brak wykonań. Wpisy pojawią się po pierwszym zdarzeniu.</div>
          {/foreach}
        </div>
      </section>

      <section class="oa-panel oa-help">
        <h3><i class="bi bi-info-circle"></i> Jak to działa</h3>
        <ol>
          <li><b>Wyzwalacz</b> — zdarzenie, np. nowe lub opłacone zamówienie. Możesz zaznaczyć kilka.</li>
          <li><b>Warunki</b> — wszystkie muszą być spełnione albo wystarczy dowolny.</li>
          <li><b>Efekty</b> — kroki wykonywane po kolei. Efekt może wywołać kolejne automatyzacje (maks. 4 poziomy, bez zapętleń).</li>
          <li><b>Przyciski</b> — reguły oznaczone przyciskiem uruchomisz w zamówieniu lub dla zaznaczonych na liście.</li>
        </ol>
        <p class="oa-help-note"><i class="bi bi-hourglass-split"></i> „Upływ czasu” wymaga crona <code>php bin/orders-sync.php</code> uruchamianego co minutę.</p>
      </section>
    </aside>
  </div>
</section>
