<div class="om-cod-bank-accounts" id="om-cod-bank-accounts">
  <div class="om-cod-bank-heading"><h4 class="om-preset-sub"><i class="bi bi-bank"></i> Rachunki dla pobrań</h4><p>Przypiszesz je do kanałów w zakładce Konta i import. Apaczka użyje rachunku przypisanego do konta, z którego przyszło zamówienie.</p></div>
  {foreach $codBankSettings.accounts as $bankId=>$bank}
    <form method="post" action="?controller=orders&action=save#om-cod-bank-accounts" class="om-cod-bank-form">
      <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="tab" value="shipments"><input type="hidden" name="cod_bank_id" value="{$bankId|escape}">
      <label>Nazwa rachunku<input name="cod_bank_name" maxlength="100" value="{$bank.name|escape}" {if !$canWrite}disabled{/if} required></label>
      <label>Numer rachunku<input name="cod_bank_number" inputmode="numeric" maxlength="30" autocomplete="off" value="{$bank.number|escape}" {if !$canWrite}disabled{/if} required></label>
      {if $canWrite}<div><button class="om-btn om-primary" name="operation" value="cod_bank_save">Zapisz rachunek</button> <button class="om-btn om-danger-outline" name="operation" value="cod_bank_delete" formnovalidate onclick="return confirm('Usunąć ten rachunek pobrań?')">Usuń</button></div>{/if}
    </form>
  {/foreach}
  {if $canWrite}<form method="post" action="?controller=orders&action=save#om-cod-bank-accounts" class="om-cod-bank-form">
    <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="tab" value="shipments"><input type="hidden" name="operation" value="cod_bank_save">
    <label>Nazwa nowego rachunku<input name="cod_bank_name" maxlength="100" placeholder="np. Allegro" required></label>
    <label>Numer rachunku<input name="cod_bank_number" inputmode="numeric" maxlength="30" autocomplete="off" placeholder="PL00 0000 0000 0000 0000 0000 0000" required></label>
    <div><button class="om-btn om-primary"><i class="bi bi-plus-lg"></i> Dodaj rachunek</button></div>
  </form>{/if}
</div>
