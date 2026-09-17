<section id="om-ksef" class="om-panel om-pad om-top om-ksef">
  <div class="om-docs-panel-title"><div><span class="om-eyebrow">KSeF · KRAJOWY SYSTEM E-FAKTUR</span><h3>Konta KSeF</h3><p>Dodaj osobne konto dla każdej firmy (NIP). Konto przypisujesz do serii faktur i korekt w zakładce Dokumenty — faktura trafia do KSeF kontem swojej serii.</p></div><span class="om-chip">{$ksefAccounts|count} {if $ksefAccounts|count eq 1}konto{elseif $ksefAccounts|count >= 2 and $ksefAccounts|count <= 4}konta{else}kont{/if}</span></div>
  <div class="om-ksef-accounts">
  {foreach $ksefAccounts as $account}
    <details id="om-ksef-{$account.id}" class="om-ksef-account">
      <summary>
        <span class="om-ksef-account-main"><strong>{$account.name|escape}</strong><small>NIP {$account.nip|escape} · {if $account.series}serie: {foreach $account.series as $seriesName}{$seriesName|escape}{if !$seriesName@last}, {/if}{/foreach}{else}nieprzypisane do serii{/if}</small></span>
        <span class="om-ksef-account-meta">{if !$account.ready}<span class="om-chip om-chip-idle">brak tokena</span>{/if}{if $account.auto_send}<span class="om-chip om-chip-on">auto-wysyłka</span>{/if}<span class="om-ksef-mode om-ksef-mode-{$account.environment|escape}"><i class="bi {if $account.environment eq 'production'}bi-broadcast{else}bi-cone-striped{/if}"></i> {if $account.environment eq 'production'}Produkcja{else}Sandbox{/if}</span><i class="bi bi-chevron-down oc-chevron"></i></span>
      </summary>
      <div class="om-ksef-account-body">
        {include file='orders/ksef_account_form.tpl' account=$account}
        {if $canWrite}<div class="om-actions om-top">
          {if $account.ready}<form method="post" action="?controller=orders&action=save"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="ksef_test"><input type="hidden" name="tab" value="general"><input type="hidden" name="ksef_account_id" value="{$account.id}"><button class="om-btn om-small"><i class="bi bi-plug"></i> Testuj połączenie</button></form>{/if}
          <form method="post" action="?controller=orders&action=save" data-confirm-action="Usunąć konto KSeF „{$account.name|escape}”? Historia wysyłek zostanie zachowana."><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="ksef_account_delete"><input type="hidden" name="tab" value="general"><input type="hidden" name="ksef_account_id" value="{$account.id}"><button class="om-btn om-small om-danger-outline" {if $account.series}disabled title="Najpierw odłącz konto od serii w zakładce Dokumenty"{/if}><i class="bi bi-trash"></i> Usuń konto</button></form>
        </div>{/if}
      </div>
    </details>
  {foreachelse}<p class="om-muted">Nie dodano jeszcze konta KSeF.</p>{/foreach}
  </div>
  {if $canWrite}<details class="om-fiscal-add"{if !$ksefAccounts} open{/if}><summary><i class="bi bi-plus-lg"></i> Dodaj konto KSeF</summary>{include file='orders/ksef_account_form.tpl' account=null}</details>{/if}
  <small class="om-muted om-top" style="display:block">Token generujesz w Aplikacji Podatnika KSeF właściwego środowiska, z uprawnieniem do wystawiania faktur. Tokeny są szyfrowane i nigdy nie są wyświetlane. NIP sprzedawcy na fakturze musi być zgodny z NIP konta. NIP nabywcy jest odczytywany z linii „NIP: …” w danych nabywcy — bez niej faktura trafia jako wystawiona dla konsumenta.</small>
</section>
