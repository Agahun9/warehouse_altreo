<div class="market-params market-params--eu">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
      <div class="fw-bold fs-6">Parametry EU</div>
      <div class="small text-muted">
        {if !empty($parameters_meta.category_id)}Kategoria API: <code>{$parameters_meta.category_id|escape:'html'}</code>{/if}
        {if !empty($parameters_meta.source)}{if !empty($parameters_meta.category_id)} | {/if}Źródło: {$parameters_meta.source|escape:'html'}{/if}
      </div>
    </div>
    <div class="small text-muted">{count($parameters)} parametrów</div>
  </div>

  {if $parameters_error|default:''}
    <div class="alert alert-warning py-2 mb-3">{$parameters_error|escape:'html'}</div>
  {/if}

  {if $parameters}
    <div class="mb-3">
      <input type="text" class="form-control js-market-param-filter" data-target=".js-eu-param-card" placeholder="Szukaj po nazwie lub ID parametru EU...">
    </div>

    <div class="row g-3">
      {foreach from=$parameters item=param}
        {assign var=labelClass value="form-label fw-semibold"}
        {if $param.required}
          {assign var=labelClass value="form-label fw-semibold text-danger"}
        {/if}

        {assign var=pval value=''}
        {assign var=pvals value=[]}
        {if isset($product.param_values_by_id[$param.id])}
          {assign var=pval value=$product.param_values_by_id[$param.id]}
          {assign var=pvals value=$product.param_values_by_id[$param.id]}
        {/if}

        <div class="col-12 col-xl-6 js-eu-param-card" data-filter-text="{$param.name|escape:'html'} {$param.id|escape:'html'} {$param.type|escape:'html'}">
          <div class="border rounded-3 p-3 h-100 bg-white">
            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
              <label class="{$labelClass} mb-0" for="param_{$param.id}">
                {$param.name}
                {if $param.required}<span class="small">(wymagany)</span>{/if}
              </label>
              <span class="badge bg-light text-dark border">ID {$param.id}</span>
            </div>

            {if $param.type == 'multidictionary' || ($param.type == 'dictionary' && $param.restrictions.multipleChoices == 1)}
              <input type="hidden" name="param_type[{$param.id}]" value="3" />
              <div class="param-option-box">
                {foreach from=$param.dictionary item=option}
                  {assign var=checked value=false}
                  {foreach from=$pvals item=val}
                    {if $val == $option.id}
                      {assign var=checked value=true}
                    {/if}
                  {/foreach}
                  <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" name="param[{$param.id}][]" id="param_{$param.id}_{$option.id}" value="{$option.id}" {if $checked}checked{/if}>
                    <label class="form-check-label" for="param_{$param.id}_{$option.id}">{$option.value}</label>
                  </div>
                {/foreach}
              </div>
              <div class="form-text">Wielokrotny wybór z listy.</div>
            {elseif $param.type == 'dictionary'}
              <input type="hidden" name="param_type[{$param.id}]" value="3" />
              <select name="param[{$param.id}]" id="param_{$param.id}" class="form-select">
                <option value="">-- Wybierz --</option>
                {foreach from=$param.dictionary item=option}
                  <option value="{$option.id}" {if $pval == $option.id}selected{/if}>{$option.value}</option>
                {/foreach}
              </select>
            {elseif $param.type == 'boolean'}
              <input type="hidden" name="param_type[{$param.id}]" value="3" />
              <select name="param[{$param.id}]" id="param_{$param.id}" class="form-select">
                <option value="">-- Wybierz --</option>
                <option value="true" {if $pval == 'true'}selected{/if}>Tak</option>
                <option value="false" {if $pval == 'false'}selected{/if}>Nie</option>
              </select>
            {elseif $param.type == 'integer' || $param.type == 'float'}
              <input type="hidden" name="param_type[{$param.id}]" value="1" />
              <input type="text" name="param[{$param.id}]" id="param_{$param.id}" value="{$pval|escape:'html'}" step="any" class="form-control" />
            {elseif $param.type == 'string'}
              <input type="hidden" name="param_type[{$param.id}]" value="2" />
              <input type="text" name="param[{$param.id}]" id="param_{$param.id}" value="{$pval|escape:'html'}" class="form-control" />
            {else}
              <div class="form-text text-muted">Nieobsługiwany typ parametru: <code>{$param.type}</code></div>
            {/if}

            <div class="small text-secondary mt-2">Typ: {$param.type|escape:'html'}</div>
          </div>
        </div>
      {/foreach}
    </div>
  {else}
    <div class="alert alert-light border mb-0">Brak parametrów EU do wyświetlenia.</div>
  {/if}
</div>

{literal}
<script>
  (function () {
    document.querySelectorAll('.js-market-param-filter').forEach(function (input) {
      if (input.dataset.bound === '1') {
        return;
      }
      input.dataset.bound = '1';
      input.addEventListener('input', function () {
        var phrase = String(this.value || '').toLowerCase().trim();
        var selector = this.getAttribute('data-target') || '';
        var root = this.closest('.market-params');
        if (!root || !selector) {
          return;
        }
        root.querySelectorAll(selector).forEach(function (card) {
          var text = String(card.getAttribute('data-filter-text') || '').toLowerCase();
          card.style.display = phrase === '' || text.indexOf(phrase) !== -1 ? '' : 'none';
        });
      });
    });
  })();
</script>
{/literal}
