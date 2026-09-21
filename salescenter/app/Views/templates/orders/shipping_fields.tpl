{* Pola konta nadawczego wg definition()['fields']. Parametry: sp (moduł), account (edytowane konto lub null). *}
{foreach $sp.fields as $f}
  {assign var=current value=''}{if isset($f.default)}{assign var=current value=$f.default}{/if}
  {if $f.name eq 'name' && $account}{assign var=current value=$account.name}{elseif $account && isset($account.public[$f.name])}{assign var=current value=$account.public[$f.name]}{/if}
  <label>{$f.label|escape}
  {if $f.type eq 'source_account'}
    <select name="{$f.name|escape}" {if !empty($f.required)}required{/if}><option value="">Wybierz konto</option>{foreach $sp.source_accounts as $a}<option value="{$a.id}" {if $account && isset($account.public.order_account_id) && $account.public.order_account_id eq $a.id}selected{/if}>{$a.name|escape}</option>{/foreach}</select>
  {elseif $f.type eq 'select'}
    <select name="{$f.name|escape}">{foreach $f.options as $ov=>$ol}<option value="{$ov|escape}" {if $ov eq $current}selected{/if}>{$ol|escape}</option>{/foreach}</select>
  {elseif $f.type eq 'password'}
    <input type="password" name="{$f.name|escape}" autocomplete="new-password" {if $account}placeholder="•••••• (bez zmian)"{elseif !empty($f.required)}required{/if}>
  {else}
    <input {if $f.type eq 'number'}type="number" min="1"{else}type="text"{/if} name="{$f.name|escape}" value="{$current|escape}" autocomplete="off" {if !empty($f.max)}maxlength="{$f.max}"{/if} {if !empty($f.required)}required{/if}>
  {/if}
  {if !empty($f.help)}<small>{$f.help|escape}</small>{/if}
  </label>
{/foreach}
