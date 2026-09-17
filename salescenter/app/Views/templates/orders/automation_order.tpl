{assign var=oaOrder value=$orderAutomation|default:['rules'=>[],'runs'=>[]]}
{assign var=oaMatches value=0}{foreach $oaOrder.rules as $ar}{if $ar.match}{assign var=oaMatches value=$oaMatches+1}{/if}{/foreach}
<details id="om-automation" class="oc-card oc-disclosure oa-order-panel">
  <summary><span class="oc-summary-icon"><i class="bi bi-lightning-charge"></i></span><span><small>AUTOMATYZACJE</small><strong>Reguły dla tego zamówienia</strong></span><span class="oc-summary-meta">{$oaMatches} z {$oaOrder.rules|count} pasuje teraz · {$oaOrder.runs|count} wykonań</span><i class="bi bi-chevron-down oc-chevron"></i></summary>
  <div class="oc-disclosure-body">
    <div class="oa-order-grid">
      <div class="oa-order-rules">
        <div class="oa-order-heading"><h3>Dopasowanie w tej chwili</h3><p>Podgląd bez wykonania. Warunki zależne od zdarzenia (np. poprzedni status) sprawdzane są dopiero przy tym zdarzeniu.</p></div>
        {foreach $oaOrder.rules as $ar}
          <article class="oa-order-rule{if $ar.match} is-match{/if}">
            <header>
              <span class="oa-match-dot" title="{if $ar.match}Warunki spełnione{else}Warunki niespełnione{/if}"><i class="bi {if $ar.match}bi-check-lg{else}bi-dash-lg{/if}"></i></span>
              <div><strong>{$ar.name|escape}</strong><small>{foreach $ar.triggers as $t}{$t.label|escape}{if !$t@last} · {/if}{/foreach}{if $ar.last_run_at} · ostatnio {$ar.last_run_at|escape} UTC{/if}</small></div>
              {if $canWrite}<button type="submit" form="oa-run-order" name="rule_id" value="{$ar.id}" class="om-btn om-small{if $ar.match} om-primary{/if}"{if $ar.confirm} data-confirm-click="Uruchomić automatyzację „{$ar.name|escape}” dla zamówienia #{$detail.id}? Może wystawić dokument, nadać przesyłkę albo wysłać wiadomość."{/if} title="Uruchom teraz — warunki zostaną sprawdzone"><i class="bi bi-play-fill"></i> Uruchom</button>{/if}
            </header>
            {if $ar.conditions}<ul>{foreach $ar.conditions as $c}<li class="is-{$c.state}">{if !$c@first}<span class="oa-cond-join{if $c.join eq 'or'} is-or{/if}">{if $c.join eq 'or'}LUB{else}ORAZ{/if}</span>{/if}<i class="bi {if $c.state eq 'pass'}bi-check-circle-fill{elseif $c.state eq 'fail'}bi-x-circle-fill{else}bi-lightning{/if}"></i><span>{$c.label|escape}{if $c.state eq 'event'} <em>(przy zdarzeniu)</em>{/if}</span></li>{/foreach}</ul>{else}<p class="oa-order-any">Bez warunków — obejmuje każde zamówienie.</p>{/if}
            <p class="oa-order-actions">{foreach $ar.actions as $a}<span><i class="bi {$a.icon|escape}"></i> {$a.label|escape}</span>{/foreach}</p>
          </article>
        {foreachelse}
          <div class="oa-order-empty"><i class="bi bi-lightning-charge"></i><p>Brak aktywnych automatyzacji.</p><a class="om-btn om-small" href="?controller=orders&tab=rules">Utwórz automatyzację</a></div>
        {/foreach}
      </div>
      <div class="oa-order-runs">
        <div class="oa-order-heading"><h3>Ostatnie wykonania</h3><p>Każdy krok z wynikiem: ✓ wykonano, ↷ pominięto, ✗ błąd.</p></div>
        {foreach $oaOrder.runs as $run}
          <article class="oa-run is-{$run.result|escape}">
            <header><strong>{$run.rule_name|escape}</strong><span class="oa-result is-{$run.result|escape}">{$run.result_label|escape}</span></header>
            <small>{$run.created_at|escape} UTC · {$run.trigger_label|escape}</small>
            {if $run.message}<p>{$run.message|escape}</p>{/if}
          </article>
        {foreachelse}
          <p class="oa-order-none">Żadna automatyzacja nie była jeszcze wykonana dla tego zamówienia.</p>
        {/foreach}
      </div>
    </div>
  </div>
</details>
