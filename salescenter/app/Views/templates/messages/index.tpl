<main class="app-main om ms">
<div class="om-shell">
  <header class="om-hero ms-hero">
    <div><div class="om-eyebrow">SPRZEDAŻ · KOMUNIKACJA</div><h1>Wiadomości</h1><p>Wiadomości, dyskusje, reklamacje i incydenty ze wszystkich podłączonych marketplace'ów.</p></div>
    <div class="om-hero-actions">
      {if $canWrite && $accounts}
      <form method="post" action="index.php?controller=messages&action=save" data-ms-sync>
        <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="sync">
        <input type="hidden" name="back" value="tab={$tab|escape}{if $filterQuery}&{$filterQuery|escape}{/if}{if $thread}&id={$thread.id}{/if}">
        <button class="om-btn om-primary" type="submit"><i class="bi bi-arrow-repeat"></i> Synchronizuj teraz</button>
      </form>
      {/if}
    </div>
  </header>
  {if $flashSuccess}<div class="om-alert success">{$flashSuccess|escape}</div>{/if}
  {if $flashError}<div class="om-alert error">{$flashError|escape}</div>{/if}
  <nav class="om-tabs ms-tabs">
    <a href="index.php?controller=messages" class="{if $tab eq 'inbox'}selected{/if}"><i class="bi bi-inbox"></i> Skrzynka{if $counters.all.open > 0} <b class="ms-count">{$counters.all.open}</b>{/if}</a>
    <a href="index.php?controller=messages&tab=rules" class="{if $tab eq 'rules'}selected{/if}"><i class="bi bi-robot"></i> Autoodpowiedzi</a>
    <a href="index.php?controller=messages&tab=settings" class="{if $tab eq 'settings'}selected{/if}"><i class="bi bi-sliders"></i> Ustawienia marketplace</a>
  </nav>
  {if $tab eq 'inbox'}{include file='messages/inbox.tpl'}
  {elseif $tab eq 'rules'}{include file='messages/rules.tpl'}
  {else}{include file='messages/settings.tpl'}{/if}
</div>
</main>
<script src="dist/js/messages.js?v=20260918-1"></script>
