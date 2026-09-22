<div class="om-section-heading"><div><h2>Ustaw płatności</h2><p>Przypisz wartości otrzymywane z marketplace'ów do metod używanych w managerze zamówień.</p></div><span class="om-chip">{$paymentSources|count} wartości źródłowych</span></div>
<div class="om-alert"><strong>Jak to działa:</strong> lista poniżej powstaje z aktualnie zapisanych zamówień. Przypisanie działa od razu na istniejące zamówienia i przy każdym kolejnym pobraniu. Ręcznie poprawione zamówienia pozostają bez zmian.</div>
<div class="om-two-col">
  <section class="om-panel om-pad">
    <h3>Własne metody płatności</h3>
    <p class="om-muted">Metoda oznaczona jako pobranie ustawi kwotę zapłaconą na zero i przekaże pobranie do formularza przesyłki.</p>
    {foreach $paymentMethods as $method}
      {if $canWrite}<form class="om-inline-form" method="post" action="?controller=orders&action=save">
        <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="payment_method"><input type="hidden" name="tab" value="payments"><input type="hidden" name="payment_method_id" value="{$method.id}">
        <input name="name" value="{$method.name|escape}" maxlength="100" required aria-label="Nazwa metody płatności">
        <label class="om-check"><input type="checkbox" name="is_cod" value="1" {if $method.is_cod}checked{/if}> Pobranie</label>
        <input class="om-position" type="number" name="position" value="{$method.position}" aria-label="Pozycja">
        <button class="om-btn om-small">Zapisz</button>
      </form>{else}<div class="om-mapping"><strong>{$method.name|escape}</strong>{if $method.is_cod}<span class="om-chip">Pobranie</span>{/if}</div>{/if}
    {/foreach}
    {if $canWrite}<form class="om-form om-top" method="post" action="?controller=orders&action=save">
      <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="payment_method"><input type="hidden" name="tab" value="payments">
      <label>Nowa własna metoda<input name="name" maxlength="100" placeholder="Np. Bon podarunkowy" required></label>
      <label class="om-check"><input type="checkbox" name="is_cod" value="1"> Jest płatnością przy odbiorze</label>
      <input type="hidden" name="position" value="100"><button class="om-btn om-primary"><i class="bi bi-plus-lg"></i> Dodaj metodę</button>
    </form>{/if}
  </section>
  <section class="om-panel om-pad">
    <h3>Metody znalezione w zamówieniach</h3>
    <p class="om-muted">Każda wartość jest przypisywana osobno dla konkretnego marketplace'u.</p>
    {foreach $paymentSources as $source}
      <div class="om-mapping">
        <div><span class="om-market {$source.platform|escape}">{$source.platform|escape}</span> <strong>{if $source.source_method neq ''}{$source.source_method|escape}{else}(brak informacji z API){/if}</strong><br><small>{if $source.orders_count}<a href="?controller=orders&amp;platform={$source.platform|escape:'url'}&amp;payment_source={if $source.source_method neq ''}{$source.source_method|escape:'url'}{else}__empty__{/if}">{$source.orders_count} zamówień →</a>{else}0 zamówień{/if}{if $source.payment_method_name} · przypisano: {$source.payment_method_name|escape}{/if}</small></div>
        {if $canWrite}<div class="om-actions"><form class="om-inline-form" method="post" action="?controller=orders&action=save">
          <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="payment_mapping"><input type="hidden" name="tab" value="payments"><input type="hidden" name="platform" value="{$source.platform|escape}"><input type="hidden" name="source_method" value="{$source.source_method|escape}">
          <select name="payment_method_id" required><option value="">Wybierz metodę</option>{foreach $paymentMethods as $method}{if $method.enabled}<option value="{$method.id}" {if $source.payment_method_id eq $method.id}selected{/if}>{$method.name|escape}</option>{/if}{/foreach}</select><button class="om-btn om-small">Przypisz</button>
        </form>{if $source.mapping_id}<form method="post" action="?controller=orders&action=save"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="unmap_payment"><input type="hidden" name="tab" value="payments"><input type="hidden" name="payment_mapping_id" value="{$source.mapping_id}"><button class="om-btn om-small" title="Usuń przypisanie"><i class="bi bi-x-lg"></i></button></form>{/if}</div>{/if}
      </div>
    {foreachelse}<div class="om-empty"><i class="bi bi-credit-card"></i><h3>Brak metod źródłowych</h3><p>Najpierw pobierz zamówienia z kont marketplace. Po synchronizacji pojawią się tutaj wartości przesłane przez ich API.</p></div>{/foreach}
  </section>
</div>
