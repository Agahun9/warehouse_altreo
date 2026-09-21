{assign var=autoOn value=false}
{assign var=kindHints value=['message'=>'Allegro, Empik, MediaMarkt, Morele, PrestaShop','dispute'=>'Allegro','claim'=>'Allegro','incident'=>'Empik, MediaMarkt','return'=>'ERLI – bez odpowiedzi','note'=>'odpowiedź: Allegro, Empik, MediaMarkt, WooCommerce']}
{foreach $platforms as $code=>$platform}{if $platform.settings.autoresponder}{assign var=autoOn value=true}{/if}{/foreach}
<div class="ms-rules{if $editRule} has-editor{/if}">
  <section class="om-panel ms-pad">
    <div class="ms-panel-title">
      <div><span class="om-eyebrow">AUTOODPOWIEDZI</span><h3>Reguły</h3><p>Reguły sprawdzane są po każdej synchronizacji, od góry. Dla jednej wiadomości klienta wysyłana jest najwyżej jedna autoodpowiedź, tylko na wiadomości nowsze niż włączenie reguły (maks. 72 h).</p></div>
      {if $canWrite}<a class="om-btn om-primary" href="index.php?controller=messages&tab=rules&rule=new"><i class="bi bi-plus-lg"></i> Nowa reguła</a>{/if}
    </div>
    {if !$autoOn}<div class="om-alert">Autoodpowiedzi są wyłączone we wszystkich marketplace'ach. Włącz je w <a href="index.php?controller=messages&tab=settings">Ustawieniach marketplace</a> – reguły działają tylko tam, gdzie przełącznik jest włączony.</div>{/if}
    <div class="om-table-wrap"><table class="om-table ms-rules-table">
      <thead><tr><th></th><th>Reguła</th><th>Kanał</th><th>Zdarzenie</th><th>Wysłano</th><th></th></tr></thead>
      <tbody>
      {foreach $rules as $rule}
        <tr class="{if !$rule.enabled}is-off{/if}{if $editRule && $editRule.id eq $rule.id} is-active{/if}">
          <td>{if $canWrite}<form method="post" action="index.php?controller=messages&action=save"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="rule_toggle"><input type="hidden" name="rule_id" value="{$rule.id}"><input type="hidden" name="back" value="tab=rules"><button class="ms-switch{if $rule.enabled} is-on{/if}" type="submit" aria-label="{if $rule.enabled}Wyłącz{else}Włącz{/if} regułę {$rule.name|escape}"><span></span></button></form>{else}{if $rule.enabled}✓{/if}{/if}</td>
          <td><a href="index.php?controller=messages&tab=rules&rule={$rule.id}"><strong>{$rule.name|escape}</strong></a><small>{foreach $rule.kinds as $kind}{$kindLabels[$kind]}{if !$kind@last}, {/if}{/foreach}{if $rule.keywords} · słowa: {$rule.keywords|escape|truncate:40:'…'}{/if}</small></td>
          <td>{if $rule.platform}{$platformLabels[$rule.platform]|escape}{else}Wszystkie{/if}{if $rule.connection_id}{foreach $accounts as $account}{if $account.id eq $rule.connection_id}<small>{$account.name|escape}</small>{/if}{/foreach}{/if}</td>
          <td>{$triggers[$rule.trigger_name]|default:$rule.trigger_name|escape}{if $rule.delay_minutes}<small>po {$rule.delay_minutes} min</small>{/if}</td>
          <td>{$rule.sent_count}</td>
          <td class="ms-row-actions">{if $canWrite}
            <form method="post" action="index.php?controller=messages&action=save"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="rule_move"><input type="hidden" name="rule_id" value="{$rule.id}"><input type="hidden" name="direction" value="-1"><input type="hidden" name="back" value="tab=rules"><button class="om-btn om-small" aria-label="W górę" {if $rule@first}disabled{/if}><i class="bi bi-arrow-up"></i></button></form>
            <form method="post" action="index.php?controller=messages&action=save"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="rule_move"><input type="hidden" name="rule_id" value="{$rule.id}"><input type="hidden" name="direction" value="1"><input type="hidden" name="back" value="tab=rules"><button class="om-btn om-small" aria-label="W dół" {if $rule@last}disabled{/if}><i class="bi bi-arrow-down"></i></button></form>
            <form method="post" action="index.php?controller=messages&action=save" onsubmit="return confirm('Usunąć regułę?')"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="rule_delete"><input type="hidden" name="rule_id" value="{$rule.id}"><input type="hidden" name="back" value="tab=rules"><button class="om-btn om-small om-danger-outline" aria-label="Usuń"><i class="bi bi-trash"></i></button></form>
          {/if}</td>
        </tr>
      {foreachelse}
        <tr><td colspan="6" class="ms-muted">Brak reguł. Utwórz pierwszą – np. potwierdzenie otrzymania wiadomości albo informację o godzinach pracy.</td></tr>
      {/foreach}
      </tbody>
    </table></div>
  </section>

  {if $editRule}
  {assign var=r value=$editRule}
  <section class="om-panel ms-pad ms-editor">
    <div class="ms-panel-title"><div><span class="om-eyebrow">{if $r.id}EDYCJA{else}NOWA REGUŁA{/if}</span><h3>{if $r.id}{$r.name|escape}{else}Nowa autoodpowiedź{/if}</h3></div><a class="om-btn om-small" href="index.php?controller=messages&tab=rules"><i class="bi bi-x-lg"></i></a></div>
    {if !$r.id}
    <div class="ms-presets"><span>Gotowe szablony:</span>
      <button type="button" class="om-btn om-small" data-ms-preset="ack">Potwierdzenie otrzymania</button>
      <button type="button" class="om-btn om-small" data-ms-preset="hours">Poza godzinami pracy</button>
      <button type="button" class="om-btn om-small" data-ms-preset="claim">Reklamacja przyjęta</button>
      <button type="button" class="om-btn om-small" data-ms-preset="dispute">Dyskusja – pierwsza odpowiedź</button>
      <button type="button" class="om-btn om-small" data-ms-preset="incident">Incydent Empik/MediaMarkt</button>
      <button type="button" class="om-btn om-small" data-ms-preset="shipping">Pytanie o wysyłkę</button>
    </div>
    {/if}
    <form class="om-form ms-rule-form" method="post" action="index.php?controller=messages&action=save" data-ms-rule-form>
      <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="rule_save"><input type="hidden" name="rule_id" value="{$r.id}">
      <div class="ms-form-grid">
        <label class="ms-span-2">Nazwa reguły<input name="name" maxlength="150" required value="{$r.name|escape}"></label>
        <label>Marketplace<select name="platform" data-ms-rule-platform><option value="">Wszystkie</option>{foreach $platformLabels as $code=>$label}<option value="{$code}" {if $r.platform eq $code}selected{/if}>{$label|escape}</option>{/foreach}</select></label>
        <label>Konto<select name="connection_id"><option value="0">Wszystkie konta</option>{foreach $accounts as $account}<option value="{$account.id}" data-platform="{$account.platform}" {if $r.connection_id eq $account.id}selected{/if}>{$account.name|escape}</option>{/foreach}</select></label>
        <fieldset class="ms-span-2 ms-kinds"><legend>Rodzaj wątku</legend>
          {foreach $kindLabels as $code=>$label}<label class="om-check"><input type="checkbox" name="kinds[]" value="{$code}" {foreach $r.kinds as $k}{if $k eq $code}checked{/if}{/foreach}> {$label} <small>{$kindHints[$code]|default:''}</small></label>{/foreach}
        </fieldset>
        <label>Zdarzenie<select name="trigger_name" required>{foreach $triggers as $code=>$label}<option value="{$code}" {if $r.trigger_name eq $code}selected{/if}>{$label|escape}</option>{/foreach}</select></label>
        <label>Opóźnienie (min)<input type="number" name="delay_minutes" min="0" max="10080" value="{$r.delay_minutes}"><small>Wysyłaj tylko, jeśli nikt nie odpowiedział w tym czasie.</small></label>
        <label>Słowa kluczowe (opcjonalnie)<input name="keywords" maxlength="1000" value="{$r.keywords|escape}" placeholder="np. faktura, wysyłka, zwrot"><small>Oddziel przecinkami – reguła zadziała, gdy temat lub treść zawiera którekolwiek.</small></label>
        <label>Przerwa między autoodpowiedziami w wątku (h)<input type="number" name="cooldown_hours" min="0" max="720" value="{$r.cooldown_hours}"></label>
        <label class="ms-span-2">Treść odpowiedzi<textarea name="template" rows="8" maxlength="2000" required data-ms-template>{$r.template|escape}</textarea></label>
        <div class="ms-span-2 ms-placeholders">{foreach $placeholders as $code=>$label}<button type="button" class="ms-chip-btn" data-ms-placeholder="{$code}" title="{$label|escape}">{$code}</button>{/foreach}</div>
        <div class="ms-span-2 ms-preview"><span>Podgląd (przykładowe dane)</span><div data-ms-preview></div></div>
        <label>Status po wysłaniu<select name="after_status">{foreach $afterStatuses as $code=>$label}<option value="{$code}" {if $r.after_status eq $code}selected{/if}>{$label|escape}</option>{/foreach}</select></label>
        <label class="om-check"><input type="checkbox" name="enabled" value="1" {if !$r.id || $r.enabled}checked{/if}> Reguła włączona</label>
      </div>
      {if $canWrite}<div class="ms-form-actions"><button class="om-btn om-primary" type="submit"><i class="bi bi-check2"></i> Zapisz regułę</button></div>{/if}
    </form>
  </section>
  {/if}
</div>

<section class="om-panel ms-pad om-top">
  <div class="ms-panel-title"><div><span class="om-eyebrow">DZIENNIK</span><h3>Ostatnie autoodpowiedzi</h3></div></div>
  <div class="om-table-wrap"><table class="om-table">
    <thead><tr><th>Data (UTC)</th><th>Reguła</th><th>Wątek</th><th>Wynik</th></tr></thead>
    <tbody>
    {foreach $runLog as $run}
      <tr><td>{$run.created_at|escape}</td><td>{$run.rule_name|default:'(usunięta)'|escape}</td><td><a href="index.php?controller=messages&id={$run.thread_id}">{if $run.platform}{$platformLabels[$run.platform]|default:$run.platform|escape} · {/if}{$run.customer_name|default:$run.customer_login|default:''|escape} – {$run.subject|default:''|escape|truncate:60:'…'}</a></td><td>{if $run.result eq 'sent'}<span class="ms-status" style="--ms-c:#10b981">wysłano</span>{else}<span class="ms-status" style="--ms-c:#ef4444">błąd</span> <small>{$run.message|escape}</small>{/if}</td></tr>
    {foreachelse}
      <tr><td colspan="4" class="ms-muted">Jeszcze nie wysłano żadnej autoodpowiedzi.</td></tr>
    {/foreach}
    </tbody>
  </table></div>
</section>
