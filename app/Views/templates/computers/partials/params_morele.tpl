{assign var=moreleChars value=$morele_parameters.category_characteristics|default:null}
<div class="market-params market-params--morele">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
      <div class="fw-bold fs-6">Parametry Morele</div>
      <div class="small text-muted">
        {if !empty($morele_parameters_meta.category_id)}Kategoria API: <code>{$morele_parameters_meta.category_id|escape:'html'}</code>{/if}
        {if !empty($morele_parameters_meta.source)}{if !empty($morele_parameters_meta.category_id)} | {/if}Źródło: {$morele_parameters_meta.source|escape:'html'}{/if}
        {if !empty($morele_parameters_meta.component_category)} | Komponenty: <strong>{$morele_parameters_meta.component_category|escape:'html'}</strong>{/if}
      </div>
    </div>
    <div class="small text-muted">{if $moreleChars}{count($moreleChars)}{else}0{/if} cech</div>
  </div>

  {if $morele_parameters_error|default:''}
    <div class="alert alert-warning py-2 mb-3">{$morele_parameters_error|escape:'html'}</div>
  {/if}

  {if $moreleChars}
    <input type="hidden" name="params_morele_loaded" value="1" />
    <div class="mb-3">
      <input type="text" class="form-control js-market-param-filter" data-target=".js-morele-param-card" placeholder="Szukaj po grupie, nazwie lub ID parametru Morele...">
    </div>
    {if $morele_parameters_meta.used_count|default:0 > 0 && $morele_parameters_meta.unused_count|default:0 > 0}
      <div class="alert alert-light border d-flex justify-content-between align-items-center gap-2 py-2">
        <span>Widoczne: <strong>{$morele_parameters_meta.used_count}</strong> cech używanych w istniejących komponentach.</span>
        <button type="button" class="btn btn-sm btn-outline-secondary js-toggle-unused-params" data-hidden-count="{$morele_parameters_meta.unused_count}">
          Pokaż pozostałe ({$morele_parameters_meta.unused_count})
        </button>
      </div>
    {/if}

    <div class="row g-3">
      {foreach from=$moreleChars item=char}
        <div class="col-12 col-xl-6 js-morele-param-card{if !$char.is_used && $morele_parameters_meta.used_count|default:0 > 0} d-none{/if}"
             data-unused="{if $char.is_used}0{else}1{/if}"
             data-filter-text="{$char.characteristics_group_name|escape:'html'} {$char.characteristics_name|escape:'html'} {$char.characteristics_id|escape:'html'}">
          <div class="border rounded-3 p-3 h-100 bg-white">
            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
              <label class="form-label fw-semibold mb-0" for="char_{$char.characteristics_id}">
                {$char.characteristics_group_name} - {$char.characteristics_name}
              </label>
              <span class="badge bg-light text-dark border">ID {$char.characteristics_id}</span>
            </div>

            <input type="text" class="form-control mb-2 live-search" placeholder="Filtruj opcje..." data-target="char_{$char.characteristics_id}" />
            <input type="hidden" name="morele_param_type[{$char.characteristics_id}]" value="99" />

            <select name="morele_param[{$char.characteristics_id}]" id="char_{$char.characteristics_id}" class="form-select">
              <option value="">-- Wybierz --</option>
              {foreach from=$char.characteristics_values item=val}
                {assign var=option_value value="{$char.characteristics_name}:{$val.characteristic_value_name}"}
                <option value="{$option_value}" {if isset($product.param_morele[$char.characteristics_id|cat:'|99|']) && $product.param_morele[$char.characteristics_id|cat:'|99|'] == $option_value}selected{/if}>{$val.characteristic_value_name}</option>
              {/foreach}
            </select>
          </div>
        </div>
      {/foreach}
    </div>
  {else}
    <div class="alert alert-light border mb-0">Brak parametrów Morele do wyświetlenia.</div>
  {/if}
</div>

{literal}
<script>
  (function () {
    document.querySelectorAll('.live-search').forEach(function (input) {
      if (input.dataset.bound === '1') {
        return;
      }
      input.dataset.bound = '1';
      input.addEventListener('input', function () {
        var targetId = this.dataset.target;
        var select = document.getElementById(targetId);
        var filter = String(this.value || '').toLowerCase();
        if (!select) {
          return;
        }

        Array.from(select.options).forEach(function (option, index) {
          if (index === 0 || option.value === '') {
            option.hidden = false;
            return;
          }
          option.hidden = option.text.toLowerCase().indexOf(filter) === -1;
        });
      });
    });

    document.querySelectorAll('.js-market-param-filter').forEach(function (input) {
      if (input.dataset.filterBound === '1') {
        return;
      }
      input.dataset.filterBound = '1';
      input.addEventListener('input', function () {
        var phrase = String(this.value || '').toLowerCase().trim();
        var selector = this.getAttribute('data-target') || '';
        var root = this.closest('.market-params');
        if (!root || !selector) {
          return;
        }
        root.querySelectorAll(selector).forEach(function (card) {
          var text = String(card.getAttribute('data-filter-text') || '').toLowerCase();
          var isUnused = card.getAttribute('data-unused') === '1';
          var showUnused = root.classList.contains('show-unused-params');
          var matches = phrase === '' || text.indexOf(phrase) !== -1;
          card.classList.toggle('d-none', !matches || (phrase === '' && isUnused && !showUnused));
        });
      });
    });
    document.querySelectorAll('.js-toggle-unused-params').forEach(function (button) {
      if (button.dataset.bound === '1') return;
      button.dataset.bound = '1';
      button.addEventListener('click', function () {
        var root = this.closest('.market-params');
        var show = root.classList.toggle('show-unused-params');
        root.querySelectorAll('[data-unused="1"]').forEach(function (card) {
          card.classList.toggle('d-none', !show);
        });
        this.textContent = show ? 'Ukryj pozostałe' : 'Pokaż pozostałe (' + this.dataset.hiddenCount + ')';
      });
    });
  })();
</script>
{/literal}
