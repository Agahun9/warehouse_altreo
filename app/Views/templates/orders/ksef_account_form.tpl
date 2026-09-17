{assign var=acc value=$account|default:['id'=>0,'name'=>'','environment'=>'sandbox','nip'=>'','auto_send'=>false,'exemption_basis'=>'','token_set'=>['sandbox'=>false,'production'=>false]]}
<form class="om-form" method="post" action="?controller=orders&action=save" autocomplete="off">
  <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="ksef_account"><input type="hidden" name="tab" value="general"><input type="hidden" name="ksef_account_id" value="{$acc.id}">
  <div class="om-ksef-grid">
    <label>Nazwa konta / firmy<input name="name" maxlength="150" value="{$acc.name|escape}" placeholder="np. ALTREO Sp. z o.o." required {if !$canWrite}disabled{/if}></label>
    <label>NIP firmy (kontekst KSeF)<input name="nip" inputmode="numeric" maxlength="20" value="{$acc.nip|escape}" required {if !$canWrite}disabled{/if}></label>
  </div>
  <div class="om-ksef-modes" role="radiogroup" aria-label="Tryb KSeF">
    <label class="om-ksef-option"><input type="radio" name="environment" value="sandbox" {if $acc.environment eq 'sandbox'}checked{/if} {if !$canWrite}disabled{/if}><span><strong><i class="bi bi-cone-striped"></i> Sandbox</strong><small>Środowisko testowe MF (api-test.ksef.mf.gov.pl). Faktury nie mają skutków podatkowych.</small><em>Token: {if $acc.token_set.sandbox}zapisany{else}brak{/if}</em></span></label>
    <label class="om-ksef-option is-production"><input type="radio" name="environment" value="production" {if $acc.environment eq 'production'}checked{/if} {if !$canWrite}disabled{/if}><span><strong><i class="bi bi-broadcast"></i> Produkcja</strong><small>Właściwy KSeF (api.ksef.mf.gov.pl). Wysłana faktura jest wystawiona w rozumieniu przepisów.</small><em>Token: {if $acc.token_set.production}zapisany{else}brak{/if}</em></span></label>
  </div>
  {if $acc.environment ne 'production'}<label class="om-check om-ksef-confirm"><input type="checkbox" name="production_confirm" value="1" {if !$canWrite}disabled{/if}> Potwierdzam przełączenie na produkcję — wymagane tylko przy wyborze trybu „Produkcja”.</label>{/if}
  <div class="om-ksef-grid">
    <label>Token KSeF — Sandbox<input type="password" name="token_sandbox" autocomplete="new-password" placeholder="{if $acc.token_set.sandbox}Zapisany — wpisz nowy, aby zmienić{else}Wklej token z KSeF (test){/if}" {if !$canWrite}disabled{/if}>{if $acc.token_set.sandbox && $canWrite}<span class="om-check"><input type="checkbox" name="token_sandbox_remove" value="1"> Usuń zapisany token</span>{/if}</label>
    <label>Token KSeF — Produkcja<input type="password" name="token_production" autocomplete="new-password" placeholder="{if $acc.token_set.production}Zapisany — wpisz nowy, aby zmienić{else}Wklej token z KSeF (produkcja){/if}" {if !$canWrite}disabled{/if}>{if $acc.token_set.production && $canWrite}<span class="om-check"><input type="checkbox" name="token_production_remove" value="1"> Usuń zapisany token</span>{/if}</label>
    <label>Podstawa zwolnienia z VAT (dla pozycji „zw”)<input name="exemption_basis" maxlength="256" value="{$acc.exemption_basis|escape}" placeholder="np. art. 113 ust. 1 ustawy o VAT" {if !$canWrite}disabled{/if}></label>
  </div>
  <label class="om-check"><input type="checkbox" name="auto_send" value="1" {if $acc.auto_send}checked{/if} {if !$canWrite}disabled{/if}> Wysyłaj automatycznie po wystawieniu faktury lub korekty w seriach tego konta (także z automatyzacji)</label>
  {if $canWrite}<button class="om-btn om-primary"><i class="bi bi-check2"></i> {if $acc.id}Zapisz konto{else}Dodaj konto KSeF{/if}</button>{/if}
</form>
