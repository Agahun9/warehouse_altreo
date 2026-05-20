<label class="form-label fw-bold">Parametry EU:</label>

{foreach from=$parameters item=param}
  <div class="mb-3">
    {* Zmienna pomocnicza do klasy CSS *}
    {assign var=labelClass value="form-label"}
    {if $param.required}
      {assign var=labelClass value="form-label text-danger fw-bold"}
    {/if}

    <label class="{$labelClass}" for="param_{$param.id}">
      {$param.name}
      {if $param.required}(wymagany){/if}
    </label>

    {* Szukanie wartości parametru *}
    {assign var=pval value=''}
    {assign var=pvals value=[]}
    {foreach from=$product.param key=fullKey item=val}
      {if strpos($fullKey, "{$param.id}|") === 0}
        {assign var=pval value=$val}
        {assign var=pvals value=$val}
      {/if}
    {/foreach}

    {* Checkboxy *}
    {if $param.type == 'multidictionary' || ($param.type == 'dictionary' && $param.restrictions.multipleChoices == 1)}
      <div class="form-check">
        {foreach from=$param.dictionary item=option}
          {assign var=checked value=false}
          {foreach from=$pvals item=val}
            {if $val == $option.id}
              {assign var=checked value=true}
            {/if}
          {/foreach}

          <div class="form-check mb-1">
            <input type="hidden" name="param_type[{$param.id}]" value="3" />
            <input class="form-check-input" type="checkbox" name="param[{$param.id}][]"
              id="param_{$param.id}_{$option.id}" value="{$option.id}" {if $checked}checked{/if}>
            <label class="form-check-label" for="param_{$param.id}_{$option.id}">
              {$option.value}
            </label>
          </div>
        {/foreach}
      </div>

      {* Select *}
    {elseif $param.type == 'dictionary'}
      <input type="hidden" name="param_type[{$param.id}]" value="3" />
      <select name="param[{$param.id}]" id="param_{$param.id}" class="form-select">
        <option value="">-- Wybierz --</option>
        {foreach from=$param.dictionary item=option}
          <option value="{$option.id}" {if $pval == $option.id}selected{/if}>
            {$option.value}
          </option>
        {/foreach}
      </select>

      {* Boolean *}
    {elseif $param.type == 'boolean'}
      <input type="hidden" name="param_type[{$param.id}]" value="3" />
      <select name="param[{$param.id}]" id="param_{$param.id}" class="form-select">
        <option value="">-- Wybierz --</option>
        <option value="true" {if $pval == 'true'}selected{/if}>Tak</option>
        <option value="false" {if $pval == 'false'}selected{/if}>Nie</option>
      </select>

      {* Liczby *}
    {elseif $param.type == 'integer' || $param.type == 'float'}
      <input type="hidden" name="param_type[{$param.id}]" value="1" />
      <input type="text" name="param[{$param.id}]" id="param_{$param.id}" value="{$pval}" step="any"
        class="form-control" />

      {* Tekst *}
    {elseif $param.type == 'string'}
      <input type="hidden" name="param_type[{$param.id}]" value="2" />
      <input type="text" name="param[{$param.id}]" id="param_{$param.id}" value="{$pval}"
        class="form-control" />

    {else}
      <div class="form-text text-muted">
        Nieobsługiwany typ parametru: <code>{$param.type}</code>
      </div>
    {/if}
  </div>
{/foreach}
