{assign var=empikParamsList value=$empik_parameters|default:null}
<div class="market-params market-params--empik">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
      <div class="fw-bold fs-6">Parametry Empik</div>
      <div class="small text-muted">
        {if !empty($empik_parameters_meta.category_id)}Kategoria API: <code>{$empik_parameters_meta.category_id|escape:'html'}</code>{/if}
        {if !empty($empik_parameters_meta.source)}{if !empty($empik_parameters_meta.category_id)} | {/if}Źródło: {$empik_parameters_meta.source|escape:'html'}{/if}
      </div>
    </div>
    <div class="small text-muted">{if $empikParamsList}{count($empikParamsList)}{else}0{/if} parametrów</div>
  </div>

  {if $empik_parameters_error|default:''}
    <div class="alert alert-warning py-2 mb-3">{$empik_parameters_error|escape:'html'}</div>
  {/if}

  {if $empikParamsList}
    <div class="mb-3">
      <input type="text" class="form-control js-market-param-filter" data-target=".js-empik-param-card" placeholder="Szukaj po nazwie lub ID parametru Empik...">
    </div>

    <div class="row g-3 mb-4">
      {foreach from=$empikParamsList item=param}
        {assign var=storedValue value=''}
        {assign var=storedValueNormalized value=[]}
        {assign var=paramNameLookup value=$param.name|lower}
        {if isset($product.param_empik[$param.name])}
          {assign var=storedValue value=$product.param_empik[$param.name]}
        {/if}
        {if isset($product.param_empik_normalized[$paramNameLookup])}
          {assign var=storedValueNormalized value=$product.param_empik_normalized[$paramNameLookup]}
        {/if}

        <div class="col-12 col-xl-6 js-empik-param-card" data-filter-text="{$param.name|escape:'html'} {$param.id|escape:'html'} {$param.type|escape:'html'}">
          <div class="border rounded-3 p-3 h-100 bg-white">
            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
              <label class="form-label fw-semibold mb-0" for="empik_param_{$param.id}">
                {$param.name}
              </label>
              <span class="badge bg-light text-dark border">ID {$param.id}</span>
            </div>

            {if $param.type == 'multidictionary' || ($param.type == 'dictionary' && $param.multiple)}
              <div class="param-option-box">
                {foreach from=$param.dictionary item=option}
                  {assign var=isChecked value=false}
                  {if $storedValue neq ''}
                    {if $storedValue == $option.id || $storedValue == $option.value}
                      {assign var=isChecked value=true}
                    {/if}
                  {/if}
                  {if !$isChecked && $storedValueNormalized}
                    {if in_array($option.id|lower, $storedValueNormalized) || in_array($option.value|lower, $storedValueNormalized)}
                      {assign var=isChecked value=true}
                    {/if}
                  {/if}
                  <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" name="empik_parameters[{$param.id}][]" id="empik_param_{$param.id}_{$option.id}" value="{$option.id}" {if $isChecked}checked{/if}>
                    <label class="form-check-label" for="empik_param_{$param.id}_{$option.id}">{$option.value}</label>
                  </div>
                {/foreach}
              </div>
              <div class="form-text">Wielokrotny wybór z listy.</div>
            {elseif $param.type == 'dictionary' && $param.dictionary}
              <input type="text" class="form-control mb-2 live-search" placeholder="Filtruj opcje..." data-target="empik_param_{$param.id}" />
              <select name="empik_parameters[{$param.id}]" id="empik_param_{$param.id}" class="form-select">
                <option value="">-- Wybierz --</option>
                {foreach from=$param.dictionary item=option}
                  <option value="{$option.id}" {if $storedValue == $option.id || $storedValue == $option.value}selected{/if}>{$option.value}</option>
                {/foreach}
              </select>
            {elseif $param.type == 'integer' || $param.type == 'number'}
              <input type="number" step="any" class="form-control" name="empik_parameters[{$param.id}]" id="empik_param_{$param.id}" value="{$storedValue|escape:'html'}" />
            {elseif $param.type == 'textarea'}
              <textarea class="form-control" name="empik_parameters[{$param.id}]" id="empik_param_{$param.id}" rows="3">{$storedValue|escape:'html'}</textarea>
            {else}
              <input type="text" class="form-control" name="empik_parameters[{$param.id}]" id="empik_param_{$param.id}" value="{$storedValue|escape:'html'}" />
            {/if}

            <div class="small text-secondary mt-2">
              Typ: {$param.type|escape:'html'}{if $param.multiple} | multiple{/if}{if $param.option_lookup} | lookup{/if}
            </div>
          </div>
        </div>
      {/foreach}
    </div>
  {else}
    <div class="alert alert-light border">Brak parametrów Empik do wyświetlenia.</div>
  {/if}

  <div class="border-top pt-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
      <div class="fw-semibold">Dodatkowe parametry Empik</div>
      <button type="button" id="addEmpikParamBtn" class="btn btn-sm btn-outline-primary">Dodaj własny parametr</button>
    </div>
    <div class="small text-muted mb-3">Tu możesz dopisać własne pary nazwa / wartość, które nie przyszły z API.</div>

    <div id="empik_custom_params">
      {assign var=hasCustomRows value=false}
      {if $product.param_empik}
        {foreach from=$product.param_empik key=ename item=eval}
          {assign var=isApiParam value=false}
          {if $empikParamsList}
            {foreach from=$empikParamsList item=param}
              {if $param.name == $ename}
                {assign var=isApiParam value=true}
              {/if}
            {/foreach}
          {/if}
          {if !$isApiParam}
            {assign var=hasCustomRows value=true}
            <div class="empik-param-row row g-2 align-items-center mb-2">
              <div class="col-md-5"><input type="text" name="empik_custom_name[]" value="{$ename|escape:'html'}" class="form-control" placeholder="Nazwa parametru" /></div>
              <div class="col-md-5"><input type="text" name="empik_custom_value[]" value="{$eval|escape:'html'}" class="form-control" placeholder="Wartość" /></div>
              <div class="col-md-2 d-grid"><button type="button" class="btn btn-sm btn-outline-danger remove-empik-param">Usuń</button></div>
            </div>
          {/if}
        {/foreach}
      {/if}

      {if !$hasCustomRows}
        <div class="empik-param-row row g-2 align-items-center mb-2">
          <div class="col-md-5"><input type="text" name="empik_custom_name[]" value="" class="form-control" placeholder="Nazwa parametru" /></div>
          <div class="col-md-5"><input type="text" name="empik_custom_value[]" value="" class="form-control" placeholder="Wartość" /></div>
          <div class="col-md-2 d-grid"><button type="button" class="btn btn-sm btn-outline-danger remove-empik-param">Usuń</button></div>
        </div>
      {/if}
    </div>
  </div>
</div>

{literal}
<script>
  (function(){
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
          card.style.display = phrase === '' || text.indexOf(phrase) !== -1 ? '' : 'none';
        });
      });
    });

    var container = document.getElementById('empik_custom_params');
    var addBtn = document.getElementById('addEmpikParamBtn');
    if (!container || !addBtn) {
      return;
    }

    function createRow(name, value) {
      var row = document.createElement('div');
      row.className = 'empik-param-row row g-2 align-items-center mb-2';
      row.innerHTML = ''
        + '<div class="col-md-5"><input type="text" name="empik_custom_name[]" value="' + String(name || '').replace(/"/g, '&quot;') + '" class="form-control" placeholder="Nazwa parametru"></div>'
        + '<div class="col-md-5"><input type="text" name="empik_custom_value[]" value="' + String(value || '').replace(/"/g, '&quot;') + '" class="form-control" placeholder="Wartość"></div>'
        + '<div class="col-md-2 d-grid"><button type="button" class="btn btn-sm btn-outline-danger remove-empik-param">Usuń</button></div>';
      return row;
    }

    addBtn.addEventListener('click', function () {
      container.appendChild(createRow('', ''));
    });

    container.addEventListener('click', function (event) {
      if (event.target && event.target.classList.contains('remove-empik-param')) {
        var row = event.target.closest('.empik-param-row');
        if (row) {
          row.remove();
        }
      }
    });
  })();
</script>
{/literal}
