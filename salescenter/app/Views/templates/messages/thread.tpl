<section class="om-panel ms-thread" aria-label="Wątek" data-ms-thread='{$threadJson}' data-ms-templates='{$replyTemplatesJson}'>
  <header class="ms-thread-head">
    <div class="ms-thread-title">
      <a class="ms-back" href="index.php?controller=messages{if $filterQuery}&{$filterQuery|escape}{/if}" aria-label="Wróć do listy"><i class="bi bi-arrow-left"></i></a>
      <div>
        <div class="ms-thread-badges">
          <span class="ms-badge ms-p-{$thread.platform}">{$platformLabels[$thread.platform]|escape}</span>
          <span class="ms-tag"><i class="bi {$kindIcons[$thread.kind]|default:'bi-chat'}"></i> {$kindLabels[$thread.kind]|default:$thread.kind}</span>
          {foreach $accounts as $account}{if $account.id eq $thread.connection_id}<span class="ms-tag">{$account.name|escape}</span>{/if}{/foreach}
          {if $thread.remote_closed}<span class="ms-tag is-closed">Zamknięty w marketplace</span>{/if}
          {if $thread.due_label}<span class="ms-due{if $thread.due_soon} is-soon{/if}"><i class="bi bi-alarm"></i> termin {$thread.due_label}</span>{/if}
        </div>
        <h2>{$thread.subject|escape}</h2>
        {if $messages}{assign var=lastMsg value=$messages[$messages|@count-1]}
        <p class="ms-last-line">
          {if $lastMsg.author_role eq 'customer'}<span class="ms-answer is-wait"><i class="bi bi-hourglass-split"></i> Ostatnia wiadomość od klienta ({$lastMsg.time_label|escape}) – czeka na naszą odpowiedź</span>
          {elseif $lastMsg.author_role eq 'seller' && $lastMsg.source eq 'auto'}<span class="ms-answer is-auto"><i class="bi bi-robot"></i> Ostatnia wiadomość to autoodpowiedź ({$lastMsg.time_label|escape}) – nikt jeszcze nie odpisał osobiście</span>
          {elseif $lastMsg.author_role eq 'seller'}<span class="ms-answer is-done"><i class="bi bi-reply-fill"></i> Odpisaliśmy {$lastMsg.time_label|escape}{if $lastMsg.actor} – {$lastMsg.actor|escape}{/if}</span>
          {else}<span class="ms-answer"><i class="bi bi-info-circle"></i> Ostatni wpis: {$lastMsg.author_name|default:'system'|escape} ({$lastMsg.time_label|escape})</span>{/if}
        </p>
        {/if}
        <p><i class="bi bi-person"></i> {$thread.customer_name|default:$thread.customer_login|default:'Klient'|escape}
          {if $thread.order_external_id} · <i class="bi bi-bag"></i> {if $threadView.order_url}<a href="{$threadView.order_url}">zamówienie {$thread.order_external_id|escape}</a>{else}zamówienie {$thread.order_external_id|escape} <small>(nie ma go w SalesCenter)</small>{/if}{/if}
        </p>
      </div>
    </div>
    {if $canWrite}
    <form method="post" action="index.php?controller=messages&action=save" class="ms-status-form">
      <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="status"><input type="hidden" name="thread_id" value="{$thread.id}"><input type="hidden" name="back" value="{$backQuery|escape}">
      <div class="ms-status-pick" role="group" aria-label="Status wątku">
        {foreach $statuses as $code=>$status}<button class="ms-chip{if $thread.status eq $code} active{/if}" style="--ms-c:{$status[1]}" name="status" value="{$code}" title="Ustaw status: {$status[0]|escape}"{if $thread.status eq $code} aria-current="true"{/if}><span class="ms-chip-dot"></span>{$status[0]|escape}</button>{/foreach}
      </div>
    </form>
    {else}<span class="ms-status" style="--ms-c:{$statuses[$thread.status][1]}">{$statuses[$thread.status][0]}</span>{/if}
  </header>

  {if $threadView.meta_rows}
  <dl class="ms-meta">{foreach $threadView.meta_rows as $label=>$value}<div><dt>{$label|escape}</dt><dd>{$value|escape}</dd></div>{/foreach}</dl>
  {/if}
  {if $thread.kind eq 'incident' && !empty($thread.meta.lines)}
  <div class="ms-lines">
    <h3>Pozycje z otwartym incydentem</h3>
    {foreach $thread.meta.lines as $line}<div class="ms-line"><strong>{$line.title|escape}</strong><small>{if $line.sku}SKU {$line.sku|escape} · {/if}szt. {$line.quantity} · powód: {$line.reason_label|default:$line.reason_code|default:'—'|escape}</small></div>{/foreach}
  </div>
  {/if}

  <div class="ms-conversation" data-ms-conversation>
    {foreach $messages as $message}
      <article class="ms-msg is-{$message.author_role}{if $message.source eq 'auto'} is-auto{/if}">
        <header><strong>{if $message.author_role eq 'seller'}{if $message.source eq 'auto'}<i class="bi bi-robot"></i> {/if}{$message.actor|default:$message.author_name|default:'Sprzedawca'|escape}{elseif $message.author_role eq 'operator'}<i class="bi bi-shield-check"></i> {$message.author_name|default:'Marketplace'|escape}{elseif $message.author_role eq 'system'}<i class="bi bi-info-circle"></i> {$message.author_name|default:'System'|escape}{else}{$message.author_name|default:'Klient'|escape}{/if}</strong><time>{$message.time_label}</time></header>
        <div class="ms-msg-body">{$message.body|escape|nl2br}</div>
        {if $message.attachments}<ul class="ms-files">{foreach $message.attachments as $file}<li><i class="bi bi-paperclip"></i> {$file.name|default:'załącznik'|escape}</li>{/foreach}</ul>{/if}
      </article>
    {foreachelse}
      <p class="ms-muted">Brak treści wiadomości – zostanie pobrana przy najbliższej synchronizacji.</p>
    {/foreach}
  </div>

  {if $canWrite}
  <div class="ms-actions">
    {if !$threadView.can_reply}<p class="ms-readonly"><i class="bi bi-info-circle"></i> {$threadView.reply_hint|escape}</p>
    {elseif !$thread.remote_closed || $thread.platform eq 'allegro'}
    <form method="post" action="index.php?controller=messages&action=save" class="ms-reply" data-ms-reply>
      <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="reply"><input type="hidden" name="thread_id" value="{$thread.id}"><input type="hidden" name="back" value="{$backQuery|escape}">
      <div class="ms-reply-tools">
        <strong><i class="bi bi-reply"></i> Odpowiedź{if $thread.kind eq 'incident'} do klienta{/if}</strong>
        <select data-ms-insert aria-label="Wstaw szablon"><option value="">Wstaw szablon…</option></select>
      </div>
      <textarea name="text" rows="5" required {if $thread.platform eq 'allegro' && $thread.kind eq 'message'}maxlength="2000"{/if} placeholder="Napisz odpowiedź…" data-ms-text></textarea>
      <div class="ms-reply-options">
        {if $threadView.message_types}<label>Rodzaj<select name="message_type">{foreach $threadView.message_types as $code=>$label}<option value="{$code}">{$label|escape}</option>{/foreach}</select></label>{/if}
        {if $threadView.recipients}<span class="ms-recipients">Do: {foreach $threadView.recipients as $code=>$label}<label class="om-check"><input type="checkbox" name="recipients[]" value="{$code}" {if $code eq 'CUSTOMER' or $threadView.recipients|count eq 1}checked{/if}> {$label|escape}</label>{/foreach}</span>{/if}
        {if $thread.kind eq 'incident'}<small class="ms-muted">Wiadomość trafi do wątku zamówienia (albo utworzy nowy wątek OR43).</small>{/if}
        <label class="om-check"><input type="checkbox" name="signature" value="1" checked> Podpis</label>
        <label>Po wysłaniu<select name="after_status"><option value="answered">Odpowiedziano</option><option value="closed">Zamknij wątek</option></select></label>
        <span class="ms-counter" data-ms-counter></span>
        <button class="om-btn om-primary" type="submit"><i class="bi bi-send"></i> Wyślij</button>
      </div>
    </form>
    {/if}

    {if $threadView.decisions}
    <form method="post" action="index.php?controller=messages&action=save" class="ms-decision" data-ms-decision onsubmit="return confirm('Wysłać decyzję w reklamacji do Allegro? Tej operacji nie można cofnąć.')">
      <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="decision"><input type="hidden" name="thread_id" value="{$thread.id}"><input type="hidden" name="back" value="{$backQuery|escape}">
      <strong><i class="bi bi-hammer"></i> Decyzja w reklamacji</strong>
      <div class="ms-decision-grid">
        <label>Decyzja<select name="decision" required data-ms-decision-select><option value="">Wybierz…</option>{foreach $threadView.decisions as $group=>$options}<optgroup label="{$group|escape}">{foreach $options as $code=>$label}<option value="{$code}">{$label|escape}</option>{/foreach}</optgroup>{/foreach}</select></label>
        <label data-ms-refund hidden>Kwota zwrotu<input name="refund" inputmode="decimal" placeholder="np. 49.99"></label>
      </div>
      <label>Uzasadnienie dla kupującego<textarea name="message" rows="3" required placeholder="Opisz decyzję i dalsze kroki."></textarea></label>
      <button class="om-btn" type="submit"><i class="bi bi-check2-circle"></i> Wyślij decyzję</button>
    </form>
    {/if}

    {if $thread.kind eq 'incident' && !$thread.remote_closed && !empty($thread.meta.lines)}
    <form method="post" action="index.php?controller=messages&action=save" class="ms-decision" onsubmit="return confirm('Oznaczyć incydent jako rozwiązany w marketplace?')">
      <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="resolve_incident"><input type="hidden" name="thread_id" value="{$thread.id}"><input type="hidden" name="back" value="{$backQuery|escape}">
      <strong><i class="bi bi-check2-square"></i> Rozwiąż incydent</strong>
      <div class="ms-incident-lines">{foreach $thread.meta.lines as $line}<label class="om-check"><input type="checkbox" name="lines[]" value="{$line.id|escape}" checked> {$line.title|escape|truncate:60:'…'}</label>{/foreach}</div>
      {if $threadView.reasons}
        <label>Powód rozwiązania<select name="reason" required>{foreach $threadView.reasons as $code=>$label}<option value="{$code|escape}">{$label|escape}</option>{/foreach}</select></label>
      {else}
        {if $threadView.reasons_error}<p class="ms-muted">{$threadView.reasons_error|escape}</p>{/if}
        <label>Kod powodu (INCIDENT_CLOSE)<input name="reason" required maxlength="80"></label>
      {/if}
      <button class="om-btn" type="submit"><i class="bi bi-check2-all"></i> Oznacz jako rozwiązany</button>
    </form>
    {/if}
  </div>
  {/if}
</section>
