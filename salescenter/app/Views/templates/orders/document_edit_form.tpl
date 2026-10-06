{* Formularz edycji wystawionego dokumentu. Parametry: doc, editTab, editOrderId, inDialog. *}
{assign var=bf value=$doc.buyer_fields|default:[]}{assign var=rf value=$doc.recipient_fields|default:[]}
{assign var=isInvoiceDoc value=($doc.kind eq 'invoice' or $doc.kind eq 'invoice_correction')}
{assign var=deKsefSub value=$ksefSubmissions[$doc.id]|default:null}{assign var=deKsefTarget value=$ksefTargets[$doc.id]|default:null}
{assign var=deKsefCanSend value=($isInvoiceDoc && $deKsefTarget && $deKsefTarget.ready && (!$deKsefSub or $deKsefSub.environment ne $deKsefTarget.environment or $deKsefSub.state eq 'rejected' or $deKsefSub.state eq 'error' or $deKsefSub.state eq 'sending'))}
<form class="om-form om-doc-edit-form sc-de" method="post" action="?controller=orders&action=save" data-doc-edit-form data-doc-kind="{$doc.kind|escape}">
  {if $inDialog|default:false}<div class="oc-doc-dialog-head"><div><small>{if $doc.kind eq 'receipt' or $doc.kind eq 'receipt_correction'}PARAGON{else}FAKTURA{/if}</small><h3 id="om-doc-edit-{$doc.kind|escape}-title">Edytuj dokument {$doc.number|escape}</h3></div><button class="om-icon-btn" type="button" data-dialog-close aria-label="Zamknij"><i class="bi bi-x-lg"></i></button></div>{/if}
  <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="document_update"><input type="hidden" name="tab" value="{$editTab|escape}">{if $editOrderId|default:0}<input type="hidden" name="order_id" value="{$editOrderId}">{/if}<input type="hidden" name="document_id" value="{$doc.id}">
  {if $isInvoiceDoc}
  <div class="sc-de-ksef">
    <i class="bi bi-cloud-arrow-up"></i>
    <span>{if !$deKsefSub}<strong>KSeF:</strong> nie wysłano{elseif $deKsefSub.state eq 'accepted'}<strong>KSeF:</strong> przyjęto · {$deKsefSub.ksef_number|escape}{elseif $deKsefSub.state eq 'processing'}<strong>KSeF:</strong> przetwarzanie{elseif $deKsefSub.state eq 'rejected'}<strong>KSeF:</strong> odrzucona — popraw dane i wyślij ponownie{else}<strong>KSeF:</strong> błąd wysyłki — popraw dane i wyślij ponownie{/if}{if $deKsefSub} <em class="om-ksef-env om-ksef-mode-{$deKsefSub.environment|escape}">{if $deKsefSub.environment eq 'production'}PRODUKCJA{else}SANDBOX{/if}</em>{/if}</span>
    {if !$deKsefTarget}<small>Seria nie ma przypisanego konta KSeF (Dokumenty → seria).</small>{elseif !$deKsefTarget.ready}<small>Konto „{$deKsefTarget.name|escape}” nie ma tokena dla trybu {if $deKsefTarget.environment eq 'production'}produkcja{else}sandbox{/if}.</small>{else}<small>Konto: {$deKsefTarget.name|escape}</small>{/if}
    {if $deKsefSub && ($deKsefSub.state eq 'rejected' or $deKsefSub.state eq 'error') && $deKsefSub.message}<p class="sc-de-ksef-error">{$deKsefSub.message|escape}</p>{/if}
  </div>
  {/if}
  <div class="sc-de-parties">
    <fieldset class="sc-de-party" data-party="buyer">
      <legend><i class="bi bi-building"></i> Nabywca</legend>
      <div class="sc-de-nip">
        <label>NIP<input name="buyer_f[nip]" value="{$bf.nip|default:''|escape}" maxlength="30" inputmode="numeric" autocomplete="off" placeholder="np. 525-267-47-98" data-party-nip></label>
        <button class="om-btn om-small" type="button" data-gus-lookup><i class="bi bi-search"></i> <span>Pobierz z GUS</span></button>
      </div>
      <div class="sc-de-lookup" data-gus-result hidden aria-live="polite"></div>
      <div class="sc-de-grid">
        <label class="is-wide">Nazwa firmy<input name="buyer_f[company]" value="{$bf.company|default:''|escape}" maxlength="255" autocomplete="organization" data-field="company"></label>
        <label>Imię<input name="buyer_f[first_name]" value="{$bf.first_name|default:''|escape}" maxlength="100" autocomplete="given-name" data-field="first_name"></label>
        <label>Nazwisko<input name="buyer_f[last_name]" value="{$bf.last_name|default:''|escape}" maxlength="150" autocomplete="family-name" data-field="last_name"></label>
        <label class="is-street">Ulica<input name="buyer_f[street]" value="{$bf.street|default:''|escape}" maxlength="150" autocomplete="address-line1" data-field="street"></label>
        <label class="is-short">Nr domu / lokalu<input name="buyer_f[building]" value="{$bf.building|default:''|escape}" maxlength="30" data-field="building"></label>
        <label class="is-short">Kod pocztowy<input name="buyer_f[postal_code]" value="{$bf.postal_code|default:''|escape}" maxlength="12" autocomplete="postal-code" placeholder="00-000" data-field="postal_code" data-postal></label>
        <label>Miasto<input name="buyer_f[city]" value="{$bf.city|default:''|escape}" maxlength="100" autocomplete="address-level2" data-field="city"></label>
        <label class="is-short">Kraj<input name="buyer_f[country]" value="{$bf.country|default:'PL'|escape}" maxlength="2" autocomplete="country" data-field="country" data-country></label>
      </div>
      <p class="sc-de-hint">Firma: nazwa + NIP. Osoba prywatna: imię i nazwisko, bez NIP.</p>
      <div class="sc-de-preview"><small>Na dokumencie</small><pre data-party-preview="buyer">{$doc.buyer|escape}</pre></div>
    </fieldset>
    <fieldset class="sc-de-party" data-party="recipient">
      <legend><i class="bi bi-truck"></i> Odbiorca (dostawa)</legend>
      <div class="sc-de-same-row">
        <label class="sc-de-check"><input type="checkbox" name="recipient_same" value="1" data-recipient-same> Taki sam jak nabywca</label>
        <button class="om-btn om-small" type="button" data-copy-buyer><i class="bi bi-copy"></i> Kopiuj z nabywcy</button>
      </div>
      <div class="sc-de-grid">
        <label class="is-wide">Nazwa firmy<input name="recipient_f[company]" value="{$rf.company|default:''|escape}" maxlength="255" data-field="company" data-sync></label>
        <label>Imię<input name="recipient_f[first_name]" value="{$rf.first_name|default:''|escape}" maxlength="100" data-field="first_name" data-sync></label>
        <label>Nazwisko<input name="recipient_f[last_name]" value="{$rf.last_name|default:''|escape}" maxlength="150" data-field="last_name" data-sync></label>
        <label class="is-street">Ulica<input name="recipient_f[street]" value="{$rf.street|default:''|escape}" maxlength="150" data-field="street" data-sync></label>
        <label class="is-short">Nr domu / lokalu<input name="recipient_f[building]" value="{$rf.building|default:''|escape}" maxlength="30" data-field="building" data-sync></label>
        <label class="is-short">Kod pocztowy<input name="recipient_f[postal_code]" value="{$rf.postal_code|default:''|escape}" maxlength="12" placeholder="00-000" data-field="postal_code" data-postal data-sync></label>
        <label>Miasto<input name="recipient_f[city]" value="{$rf.city|default:''|escape}" maxlength="100" data-field="city" data-sync></label>
        <label class="is-short">Kraj<input name="recipient_f[country]" value="{$rf.country|default:'PL'|escape}" maxlength="2" data-field="country" data-country data-sync></label>
        <label>Telefon<input name="recipient_f[phone]" value="{$rf.phone|default:''|escape}" maxlength="40" inputmode="tel" data-field="phone"></label>
        <label class="is-wide">Punkt odbioru<input name="recipient_f[pickup]" value="{$rf.pickup|default:''|escape}" maxlength="255" data-field="pickup"></label>
        <label class="is-wide">Metoda dostawy<input name="recipient_f[delivery]" value="{$rf.delivery|default:''|escape}" maxlength="255" data-field="delivery"></label>
      </div>
      <div class="sc-de-preview"><small>Na dokumencie</small><pre data-party-preview="recipient">{$doc.recipient|default:''|escape}</pre></div>
    </fieldset>
  </div>
  <label class="sc-de-info">Dodatkowa informacja<textarea name="additional_info" maxlength="2000" placeholder="Drukowana na dole dokumentu">{$doc.additional_info|default:""|escape}</textarea></label>
  <fieldset class="om-new-items"><legend>Pozycje</legend>
    <div class="om-new-items-head"><span>Zmień pozycje dokumentu. Numer i seria pozostają bez zmian.</span><button class="om-btn om-small" type="button" data-doc-add-item><i class="bi bi-plus-lg"></i> Dodaj pozycję</button></div>
    <div data-doc-items>
    {foreach $doc.items as $i=>$it}
    <div class="om-doc-item-row">
      <label>Nazwa<input name="items[{$i}][name]" value="{$it.name|escape}" required><input type="hidden" name="items[{$i}][sku]" value="{$it.sku|escape}"><input type="hidden" name="items[{$i}][ean]" value="{$it.ean|escape}"></label>
      <label>Ilość<input type="number" min="0" step="1" name="items[{$i}][quantity]" value="{$it.quantity}" required data-doc-qty></label>
      <label>Cena brutto<input inputmode="decimal" name="items[{$i}][price]" value="{$it.price}" required data-doc-price></label>
      <label>VAT<select name="items[{$i}][vat]">{foreach ['23','8','7','5','0','zw','np'] as $v}<option value="{$v}" {if $it.vat eq $v}selected{/if}>{$v}{if $v ne 'zw' and $v ne 'np'}%{/if}</option>{/foreach}</select></label>
      <strong data-doc-line-total>{$it.price} PLN</strong>
      <button class="om-icon-btn" type="button" data-doc-remove-item aria-label="Usuń pozycję"><i class="bi bi-trash"></i></button>
    </div>
    {/foreach}
    </div>
  </fieldset>
  <div class="oc-doc-dialog-foot sc-de-foot">
    {if $inDialog|default:false}<button class="om-btn om-small" type="button" data-dialog-close>Anuluj</button>{/if}
    <button class="om-btn {if $deKsefCanSend}om-small{else}om-primary om-small{/if}" type="submit" name="after_save" value=""><i class="bi bi-check2"></i> Zapisz zmiany</button>
    {if $deKsefCanSend}<button class="om-btn om-primary om-small" type="submit" name="after_save" value="ksef" {if $deKsefTarget.environment eq 'production'}data-confirm-click="Zapisać zmiany i wysłać {$doc.number|escape} do PRODUKCYJNEGO KSeF ({$deKsefTarget.name|escape})? Po przyjęciu faktury nie da się jej już edytować ani usunąć — tylko korekta."{/if}><i class="bi bi-send"></i> Zapisz i wyślij do KSeF · {if $deKsefTarget.environment eq 'production'}produkcja{else}sandbox{/if}</button>{/if}
  </div>
</form>
