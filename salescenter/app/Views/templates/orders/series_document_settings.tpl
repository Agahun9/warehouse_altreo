{assign var=ds value=$documentSettings|default:[]}
<details class="sc-docs-editor-block"><summary><i class="bi bi-sliders"></i><span>Zasady wystawiania<small>VAT, data sprzedaży, dostawa i płatności</small></span><i class="bi bi-chevron-down"></i></summary>
<fieldset class="om-series-settings">
  <legend>Rozliczenie dokumentu</legend>
  <label class="sc-docs-check" data-series-for="receipt"><input name="non_fiscal" type="checkbox" value="1" {if !empty($ds.non_fiscal)}checked{/if} {if !$canWrite}disabled{/if}> Paragon niefiskalny — bez kolejki drukarki</label>
  <label data-series-for="invoice receipt">Seria korekty<select name="correct_series_id" {if !$canWrite}disabled{/if}><option value="0">Domyślna seria korekt</option>{foreach $series as $correctionSeries}{if $correctionSeries.kind eq 'invoice_correction' or $correctionSeries.kind eq 'receipt_correction'}<option value="{$correctionSeries.id}" data-correction-kind="{$correctionSeries.kind|escape}" {if ($ds.correct_series_id|default:0) eq $correctionSeries.id}selected{/if}>{$correctionSeries.name|escape} ({if $correctionSeries.kind eq 'invoice_correction'}korekta faktury{else}korekta paragonu{/if})</option>{/if}{/foreach}</select></label>
  <label>Data sprzedaży<select name="sale_date_source" {if !$canWrite}disabled{/if}><option value="order" {if ($ds.sale_date_source|default:'order') eq 'order'}selected{/if}>Data zamówienia</option><option value="payment" {if ($ds.sale_date_source|default:'') eq 'payment'}selected{/if}>Data płatności (gdy brak — wystawienia)</option><option value="issue_date" {if ($ds.sale_date_source|default:'') eq 'issue_date'}selected{/if}>Data wystawienia</option></select></label>
  <label>Źródło VAT produktów<select name="vat_source" {if !$canWrite}disabled{/if}><option value="order" {if ($ds.vat_source|default:'order') eq 'order'}selected{/if}>Stawki z zamówienia</option><option value="static" {if ($ds.vat_source|default:'') eq 'static'}selected{/if}>Stała stawka</option></select></label>
  <label data-series-when="vat_source">Stała stawka VAT<select name="vat_rate" {if !$canWrite}disabled{/if}>{foreach ['23','8','7','5','0','zw','np'] as $vat}<option value="{$vat}" {if ($ds.vat_rate|default:'23') eq $vat}selected{/if}>{$vat}{if $vat ne 'zw' and $vat ne 'np'}%{/if}</option>{/foreach}</select></label>
  <label>VAT dostawy<select name="shipment_vat_type" {if !$canWrite}disabled{/if}><option value="order" {if ($ds.shipment_vat_type|default:'order') eq 'order'}selected{/if}>Stawka z ustawień dokumentów</option><option value="static" {if ($ds.shipment_vat_type|default:'') eq 'static'}selected{/if}>Stała stawka</option></select></label>
  <label data-series-when="shipment_vat_type">Stała stawka VAT dostawy<select name="shipment_vat" {if !$canWrite}disabled{/if}>{foreach ['23','8','7','5','0','zw','np'] as $vat}<option value="{$vat}" {if ($ds.shipment_vat|default:'23') eq $vat}selected{/if}>{$vat}{if $vat ne 'zw' and $vat ne 'np'}%{/if}</option>{/foreach}</select></label>
  <label>Nazwa pozycji dostawy<input name="shipment_name" maxlength="100" value="{$ds.shipment_name|default:'Dostawa'|escape}" {if !$canWrite}disabled{/if}></label>
  <label class="sc-docs-check"><input name="add_shipment_name" type="checkbox" value="1" {if !empty($ds.add_shipment_name)}checked{/if} {if !$canWrite}disabled{/if}> Dodaj metodę dostawy do nazwy pozycji</label>
  <label>Termin płatności<select name="payment_term_days" {if !$canWrite}disabled{/if}>{foreach ['0','3','5','7','10','14','21','30','45','60','90','120','365'] as $days}<option value="{$days}" {if ($ds.payment_term_days|default:'0') eq $days}selected{/if}>{if $days eq '0'}Zapłacono / bez terminu{else}{$days} dni{/if}</option>{/foreach}</select></label>
  <label>Podzielona płatność<select name="split_payment" {if !$canWrite}disabled{/if}><option value="0" {if ($ds.split_payment|default:'0') eq '0'}selected{/if}>Nie</option><option value="1" {if ($ds.split_payment|default:'0') eq '1'}selected{/if}>Tak</option></select></label>
  <label class="sc-docs-check"><input name="buyer_validation_disabled" type="checkbox" value="1" {if !empty($ds.buyer_validation_disabled)}checked{/if} {if !$canWrite}disabled{/if}> Wyłącz walidację danych kupującego</label>
</fieldset>
</details>
<details class="sc-docs-editor-block"><summary><i class="bi bi-building"></i><span>Sprzedawca i KSeF<small>Dane firmy widoczne na dokumentach tej serii</small></span><i class="bi bi-chevron-down"></i></summary>
<fieldset class="om-series-settings">
  <legend>Dane sprzedawcy</legend>
  <small class="om-muted">Te dane trafiają na fakturę/paragon i do KSeF. Zmiana dotyczy tylko nowych dokumentów.</small>
  <label class="sc-docs-wide" data-series-for="invoice invoice_correction">Konto KSeF<select name="ksef_account_id" {if !$canWrite}disabled{/if}><option value="0">Bez konta KSeF (korekty dziedziczą konto faktury)</option>{foreach $ksefAccounts|default:[] as $ksefAccount}<option value="{$ksefAccount.id}" {if ($ds.ksef_account_id|default:0) eq $ksefAccount.id}selected{/if}>{$ksefAccount.name|escape} · NIP {$ksefAccount.nip|escape} · {if $ksefAccount.environment eq 'production'}PRODUKCJA{else}SANDBOX{/if}</option>{/foreach}</select><small class="om-muted">Konta dodajesz w zakładce Ustawienia ogólne. Paragony nie są wysyłane do KSeF.</small></label>
  <label>Nazwa firmy<input name="seller_name" maxlength="200" value="{$ds.seller_name|default:$seller.name|default:''|escape}" {if !$canWrite}disabled{/if}></label>
  <label>NIP<input name="seller_nip" maxlength="30" value="{$ds.seller_nip|default:$seller.nip|default:''|escape}" {if !$canWrite}disabled{/if}></label>
  <label class="sc-docs-wide">Adres firmy<textarea name="seller_address" maxlength="1000" {if !$canWrite}disabled{/if}>{$ds.seller_address|default:$seller.address|default:''|escape}</textarea></label>
  <label>Rachunek bankowy<input name="seller_bank" maxlength="200" value="{$ds.seller_bank|default:$seller.bank|default:''|escape}" {if !$canWrite}disabled{/if}></label>
  <label>E-mail<input type="email" name="seller_email" maxlength="255" value="{$ds.seller_email|default:$seller.email|default:''|escape}" {if !$canWrite}disabled{/if}></label>
  <label>Telefon<input name="seller_phone" maxlength="16" value="{$ds.seller_phone|default:$seller.phone|default:''|escape}" {if !$canWrite}disabled{/if}></label>
  <details class="sc-docs-optional"><summary>Dodatkowe dane firmy i banku</summary><div class="sc-docs-optional-fields">
  <label>Nazwa banku<input name="seller_bank_name" maxlength="100" value="{$ds.seller_bank_name|default:$seller.bank_name|default:''|escape}" placeholder="np. mBank" {if !$canWrite}disabled{/if}></label>
  <label>SWIFT<input name="seller_swift" maxlength="11" value="{$ds.seller_swift|default:$seller.swift|default:''|escape}" placeholder="np. BREXPLPWMBK" {if !$canWrite}disabled{/if}></label>
  <label>REGON<input name="seller_regon" maxlength="14" value="{$ds.seller_regon|default:$seller.regon|default:''|escape}" {if !$canWrite}disabled{/if}></label>
  <label>KRS<input name="seller_krs" maxlength="10" value="{$ds.seller_krs|default:$seller.krs|default:''|escape}" {if !$canWrite}disabled{/if}></label>
  <label>BDO<input name="seller_bdo" maxlength="9" value="{$ds.seller_bdo|default:$seller.bdo|default:''|escape}" {if !$canWrite}disabled{/if}></label>
  </div></details>
</fieldset>
</details>
