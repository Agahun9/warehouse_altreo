{assign var=kindIcons value=['message'=>'bi-chat-dots','dispute'=>'bi-people','claim'=>'bi-shield-exclamation','incident'=>'bi-exclamation-triangle','return'=>'bi-arrow-return-left','note'=>'bi-sticky']}
<div class="ms-workspace{if $thread} has-thread{/if}">
  <aside class="ms-sections" aria-label="Sekcje wiadomości">
    <a class="ms-section-all{if $filters.platform eq '' and $filters.kind eq ''} active{/if}" href="index.php?controller=messages{if $filters.status ne 'open'}&status={$filters.status|escape:'url'}{/if}">
      <i class="bi bi-inboxes"></i><span>Wszystkie kanały</span>{if $counters.all.open}<b>{$counters.all.open}</b>{/if}
    </a>
    {assign var=anyPlatform value=false}
    {foreach $platforms as $code=>$platform}
      {if $platform.accounts or $counters.platforms[$code].total}
      {assign var=anyPlatform value=true}
      <div class="ms-section ms-p-{$code}">
        <a class="ms-section-head{if $filters.platform eq $code and $filters.kind eq ''} active{/if}" href="index.php?controller=messages&platform={$code}{if $filters.status ne 'open'}&status={$filters.status|escape:'url'}{/if}">
          <span class="ms-dot"></span><span>{$platform.label|escape}</span>{if $counters.platforms[$code].open}<b>{$counters.platforms[$code].open}</b>{/if}
        </a>
        {foreach $platform.kinds as $kind}
          {assign var=kc value=$counters.platforms[$code].kinds[$kind]}
          <a class="ms-section-kind{if $filters.platform eq $code and $filters.kind eq $kind} active{/if}" href="index.php?controller=messages&platform={$code}&kind={$kind}{if $filters.status ne 'open'}&status={$filters.status|escape:'url'}{/if}">
            <i class="bi {$kindIcons[$kind]}"></i><span>{$kindLabels[$kind]}</span>{if $kc.open}<b>{$kc.open}</b>{else}<small>{$kc.total}</small>{/if}
          </a>
        {/foreach}
        {if !$platform.settings.enabled}<small class="ms-section-note">Synchronizacja wyłączona</small>{/if}
      </div>
      {/if}
    {/foreach}
    {if !$anyPlatform}
      <div class="ms-section-empty"><i class="bi bi-plug"></i><p>Podłącz kanał sprzedaży w <a href="orders.php?tab=accounts">Konta i import</a> – wiadomości, uwagi do zamówień i zwroty pojawią się tutaj automatycznie.</p></div>
    {/if}
    <div class="ms-queue">
      <span><b>{$counters.statuses.new|default:0}</b>nowe</span>
      <span><b>{$counters.statuses.waiting|default:0}</b>do odpowiedzi</span>
      <a href="index.php?controller=messages&status=due" class="{if $counters.overdue}is-alert{/if}"><b>{$counters.overdue}</b>terminy ≤ 48 h</a>
    </div>
  </aside>

  <section class="om-panel ms-list" aria-label="Lista wątków">
    <form class="ms-listbar" method="get" action="index.php">
      <input type="hidden" name="controller" value="messages">
      {if $filters.platform}<input type="hidden" name="platform" value="{$filters.platform|escape}">{/if}
      {if $filters.kind}<input type="hidden" name="kind" value="{$filters.kind|escape}">{/if}
      <label class="ms-search"><i class="bi bi-search"></i><input type="search" name="q" value="{$filters.q|escape}" placeholder="Klient, temat, nr zamówienia…"></label>
      <select name="status" aria-label="Status" onchange="this.form.submit()">
        <option value="open" {if $filters.status eq 'open'}selected{/if}>Do obsługi</option>
        {foreach $statuses as $code=>$status}<option value="{$code}" {if $filters.status eq $code}selected{/if}>{$status[0]}</option>{/foreach}
        <option value="due" {if $filters.status eq 'due'}selected{/if}>Z terminem (reklamacje)</option>
        <option value="all" {if $filters.status eq 'all'}selected{/if}>Wszystkie</option>
      </select>
      {if $accounts|count > 1}
      <select name="connection" aria-label="Konto" onchange="this.form.submit()">
        <option value="0">Wszystkie konta</option>
        {foreach $accounts as $account}{if $filters.platform eq '' or $filters.platform eq $account.platform}<option value="{$account.id}" {if $filters.connection eq $account.id}selected{/if}>{$account.name|escape}</option>{/if}{/foreach}
      </select>
      {/if}
    </form>
    <form method="post" action="index.php?controller=messages&action=save" class="ms-bulk" data-ms-bulk>
      <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="bulk_status"><input type="hidden" name="back" value="{$backQuery|escape}">
      <div class="ms-list-head">
        <span><strong>{$listing.total}</strong> {if $listing.total eq 1}wątek{else}wątków{/if}</span>
        {if $canWrite && $listing.rows}
        <span class="ms-bulk-actions" hidden data-ms-bulk-actions><span data-ms-selected>0</span> zazn. <select name="status">{foreach $statuses as $code=>$status}<option value="{$code}">{$status[0]}</option>{/foreach}</select><button class="om-btn om-small">Ustaw</button></span>
        {/if}
      </div>
      <div class="ms-rows">
      {foreach $listing.rows as $row}
        <div class="ms-row{if $thread && $thread.id eq $row.id} active{/if} is-{$row.status}">
          {if $canWrite}<input type="checkbox" name="ids[]" value="{$row.id}" aria-label="Zaznacz wątek" data-ms-check>{/if}
          <a class="ms-row-link" href="index.php?controller=messages&id={$row.id}{if $filterQuery}&{$filterQuery|escape}{/if}{if $listing.page > 1}&page={$listing.page}{/if}">
            <span class="ms-row-top">
              <span class="ms-badge ms-p-{$row.platform}">{$platformLabels[$row.platform]|default:$row.platform|escape}</span>
              <i class="bi {$kindIcons[$row.kind]|default:'bi-chat'}" title="{$kindLabels[$row.kind]|default:''}"></i>
              <strong class="ms-row-customer">{$row.customer_name|default:$row.customer_login|default:'—'|escape}</strong>
              <time>{$row.last_label|escape}</time>
            </span>
            <span class="ms-row-subject">{$row.subject|escape}</span>
            <span class="ms-row-preview">{if $row.last_author eq 'seller'}<i class="bi bi-reply"></i> {/if}{$row.last_preview|escape|truncate:140:'…'}</span>
            <span class="ms-row-tags">
              <span class="ms-status" style="--ms-c:{$statuses[$row.status][1]|default:'#64748b'}">{$statuses[$row.status][0]|default:$row.status|escape}</span>
              {if $row.due_label}<span class="ms-due{if $row.due_soon} is-soon{/if}"><i class="bi bi-alarm"></i> {$row.due_label}</span>{/if}
              {if $row.order_external_id}<span class="ms-tag"><i class="bi bi-bag"></i> {$row.order_external_id|escape|truncate:18:'…'}</span>{/if}
              {if $accounts|count > 1}<span class="ms-tag">{$row.connection_name|default:''|escape}</span>{/if}
            </span>
          </a>
        </div>
      {foreachelse}
        <div class="om-empty ms-empty"><i class="bi bi-chat-square-heart"></i><h3>{if $filters.status eq 'open'}Wszystko obsłużone{else}Brak wątków{/if}</h3><p>{if $accounts}Nowe wiadomości pobierają się automatycznie co kilka minut.{else}Brak podłączonych kont z wiadomościami.{/if}</p></div>
      {/foreach}
      </div>
    </form>
    {if $listing.pages > 1}
    <nav class="ms-pagination">
      {if $listing.page > 1}<a class="om-btn om-small" href="index.php?controller=messages{if $filterQuery}&{$filterQuery|escape}{/if}&page={$listing.page-1}"><i class="bi bi-chevron-left"></i></a>{/if}
      <span>Strona {$listing.page} z {$listing.pages}</span>
      {if $listing.page < $listing.pages}<a class="om-btn om-small" href="index.php?controller=messages{if $filterQuery}&{$filterQuery|escape}{/if}&page={$listing.page+1}"><i class="bi bi-chevron-right"></i></a>{/if}
    </nav>
    {/if}
  </section>

  {if $thread}{include file='messages/thread.tpl'}{/if}
</div>
