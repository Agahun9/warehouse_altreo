<div class="om-section-heading"><div><h2>Ustawienia ogólne</h2><p>Domyślne wartości używane przy tworzeniu nowych zamówień.</p></div></div>
<div class="om-two-col">
  <section class="om-panel om-pad">
    <h3>Waluta nowych zamówień</h3>
    <p class="om-muted">Ta waluta będzie domyślnie zaznaczona w formularzu „Nowe zamówienie”. Nadal można ją zmienić przy tworzeniu konkretnego zamówienia.</p>
    {if $canWrite}<form class="om-form" method="post" action="?controller=orders&action=save">
      <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="order_general_settings"><input type="hidden" name="tab" value="general">
      <label>Domyślna waluta<select name="default_currency" required>{foreach ['PLN'=>'PLN — złoty','EUR'=>'EUR — euro','USD'=>'USD — dolar amerykański','GBP'=>'GBP — funt','CZK'=>'CZK — korona czeska','HUF'=>'HUF — forint','CHF'=>'CHF — frank','SEK'=>'SEK — korona szwedzka','NOK'=>'NOK — korona norweska','DKK'=>'DKK — korona duńska','RON'=>'RON — lej','BGN'=>'BGN — lew','UAH'=>'UAH — hrywna'] as $code=>$label}<option value="{$code}" {if $orderGeneralSettings.default_currency eq $code}selected{/if}>{$label}</option>{/foreach}</select></label>
      <button class="om-btn om-primary"><i class="bi bi-check2"></i> Zapisz</button>
    </form>{else}<p class="om-muted">Aktualna domyślna waluta: <strong>{$orderGeneralSettings.default_currency|escape}</strong></p>{/if}
  </section>
</div>
{include file='orders/ksef_settings.tpl'}
