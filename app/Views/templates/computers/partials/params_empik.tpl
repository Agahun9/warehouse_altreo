{assign var=empikParamsList value=$empik_parameters|default:null}
<div class="market-params market-params--empik"
     data-options-url="{$baseUrl}?controller=computers&amp;action=empikparameteroptions">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
      <div class="fw-bold fs-6">Parametry Empik</div>
      <div class="small text-muted">
        {if !empty($empik_parameters_meta.category_id)}Kategoria API: <code>{$empik_parameters_meta.category_id|escape:'html'}</code>{/if}
        {if !empty($empik_parameters_meta.source)}{if !empty($empik_parameters_meta.category_id)} | {/if}Źródło: {$empik_parameters_meta.source|escape:'html'}{/if}
        {if !empty($empik_parameters_meta.component_category)} | Komponenty: <strong>{$empik_parameters_meta.component_category|escape:'html'}</strong>{/if}
      </div>
    </div>
    <div class="small text-muted">{if $empikParamsList}{count($empikParamsList)}{else}0{/if} parametrów</div>
  </div>

  {if $empik_parameters_error|default:''}
    <div class="alert alert-warning py-2 mb-3">{$empik_parameters_error|escape:'html'}</div>
  {/if}

  {if $empikParamsList}
    <input type="hidden" name="params_empik_loaded" value="1" />
    <div class="mb-3">
      <input type="text" class="form-control js-market-param-filter" data-target=".js-empik-param-card" placeholder="Szukaj po nazwie lub ID parametru Empik...">
    </div>
    {if $empik_parameters_meta.used_count|default:0 > 0 && $empik_parameters_meta.unused_count|default:0 > 0}
      <div class="alert alert-light border d-flex justify-content-between align-items-center gap-2 py-2">
        <span>Widoczne: <strong>{$empik_parameters_meta.used_count}</strong> parametrów używanych w istniejących komponentach.</span>
        <button type="button" class="btn btn-sm btn-outline-secondary js-toggle-unused-params" data-hidden-count="{$empik_parameters_meta.unused_count}">
          Pokaż pozostałe ({$empik_parameters_meta.unused_count})
        </button>
      </div>
    {/if}

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

        <div class="col-12 col-xl-6 js-empik-param-card{if !$param.is_used && $empik_parameters_meta.used_count|default:0 > 0} d-none{/if}"
             data-unused="{if $param.is_used}0{else}1{/if}"
             data-filter-text="{$param.name|escape:'html'} {$param.id|escape:'html'} {$param.type|escape:'html'}">
          <div class="border rounded-3 p-3 h-100 bg-white">
            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
              <label class="form-label fw-semibold mb-0" for="empik_param_{$param.id}">
                {$param.name}
              </label>
              <span class="badge bg-light text-dark border">ID {$param.id}</span>
            </div>

            {if $param.option_lookup}
              <div class="empik-option-lookup"
                   data-attribute-id="{$param.id|escape:'html'}"
                   data-multiple="{if $param.multiple}1{else}0{/if}">
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-search"></i></span>
                  <input type="search"
                         class="form-control js-empik-option-search"
                         placeholder="Szukaj dostępnych wartości dla: {$param.name|escape:'html'}"
                         autocomplete="off">
                  <button type="button" class="btn btn-outline-secondary js-empik-option-clear">Wyczyść</button>
                </div>
                <div class="form-text">Po kliknięciu pokazujemy dostępne propozycje. Wpisz tekst, aby zawęzić wyniki tylko dla tego parametru.</div>
                <div class="list-group mt-2 js-empik-option-results d-none"></div>
                <div class="mt-2 js-empik-option-selection">
                  {if $param.multiple}
                    {if $storedValue neq ''}
                      {assign var=storedOptions value=$storedValue|split:" | "}
                      {foreach from=$storedOptions item=storedOption}
                        {if $storedOption neq ''}
                          <span class="badge text-bg-primary me-1 mb-1 empik-selected-option">
                            <span>{$storedOption|escape:'html'}</span>
                            <input type="hidden" name="empik_parameters[{$param.id}][]" value="{$storedOption|escape:'html'}">
                            <button type="button" class="btn-close btn-close-white ms-1 js-remove-empik-option" aria-label="Usuń"></button>
                          </span>
                        {/if}
                      {/foreach}
                    {/if}
                  {else}
                    <input type="hidden" name="empik_parameters[{$param.id}]" class="js-empik-option-value" value="{$storedValue|escape:'html'}">
                    <div class="alert alert-light border py-2 px-3 mb-0 js-empik-single-selection {if $storedValue eq ''}d-none{/if}">
                      Wybrano: <strong class="js-empik-selected-label">{$storedValue|escape:'html'}</strong>
                    </div>
                  {/if}
                </div>
              </div>
            {elseif $param.type == 'multidictionary' || ($param.type == 'dictionary' && $param.multiple)}
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
              <select name="empik_parameters[{$param.id}]" id="empik_param_{$param.id}" class="form-select">
                <option value="">-- Wybierz --</option>
                {foreach from=$param.dictionary item=option}
                  <option value="{$option.id}" {if $storedValue == $option.id || $storedValue == $option.value}selected{/if}>{$option.value}</option>
                {/foreach}
              </select>
            {elseif $param.type == 'integer' || $param.type == 'number'}
              <input type="number" step="any" class="form-control" name="empik_parameters[{$param.id}]" id="empik_param_{$param.id}" value="{$storedValue|escape:'html'}" />
            {elseif $param.type == 'textarea'}
              <textarea class="form-control" name="empik_parameters[{$param.id}]" id="empik_param_{$param.id}" rows="3" placeholder="Wpisz wartości ręcznie, każdą w osobnej linii">{$storedValue|escape:'html'}</textarea>
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
    var empikRoot = document.querySelector('.market-params--empik');
    var optionsUrl = empikRoot ? empikRoot.getAttribute('data-options-url') : '';

    function escapeHtml(value) {
      return String(value || '').replace(/[&<>"']/g, function (character) {
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[character];
      });
    }

    function addSelectedOption(lookup, id, label) {
      var multiple = lookup.getAttribute('data-multiple') === '1';
      var selection = lookup.querySelector('.js-empik-option-selection');
      if (!selection) return;

      if (!multiple) {
        var valueInput = selection.querySelector('.js-empik-option-value');
        var notice = selection.querySelector('.js-empik-single-selection');
        var labelNode = selection.querySelector('.js-empik-selected-label');
        if (valueInput) valueInput.value = label;
        if (labelNode) labelNode.textContent = label;
        if (notice) notice.classList.remove('d-none');
        return;
      }

      var existing = Array.from(selection.querySelectorAll('input[type="hidden"]')).some(function (input) {
        return input.value === label || input.dataset.optionId === id;
      });
      if (existing) return;

      var badge = document.createElement('span');
      badge.className = 'badge text-bg-primary me-1 mb-1 empik-selected-option';
      badge.innerHTML = '<span>' + escapeHtml(label) + '</span>'
        + '<input type="hidden" name="empik_parameters[' + escapeHtml(lookup.getAttribute('data-attribute-id')) + '][]"'
        + ' value="' + escapeHtml(label) + '" data-option-id="' + escapeHtml(id) + '">'
        + '<button type="button" class="btn-close btn-close-white ms-1 js-remove-empik-option" aria-label="Usuń"></button>';
      selection.appendChild(badge);
    }

    document.querySelectorAll('.empik-option-lookup').forEach(function (lookup, lookupIndex) {
      if (lookup.dataset.bound === '1') return;
      lookup.dataset.bound = '1';
      var input = lookup.querySelector('.js-empik-option-search');
      var results = lookup.querySelector('.js-empik-option-results');
      var clear = lookup.querySelector('.js-empik-option-clear');
      var timer = null;
      var requestNumber = 0;

      function hideResults() {
        results.classList.add('d-none');
        results.innerHTML = '';
      }

      function search(forceInitial) {
        var phrase = String(input.value || '').trim();
        if (!optionsUrl) {
          hideResults();
          return;
        }

        var currentRequest = ++requestNumber;
        results.classList.remove('d-none');
        results.innerHTML = '<div class="list-group-item text-muted">Szukam…</div>';
        var resultLimit = phrase === '' ? 10 : 60;
        var url = optionsUrl + '&attribute_id=' + encodeURIComponent(lookup.getAttribute('data-attribute-id'))
          + '&q=' + encodeURIComponent(phrase) + '&limit=' + resultLimit;
        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
          .then(function (response) {
            return response.json().then(function (data) {
              if (!response.ok) throw new Error(data.error || 'Błąd pobierania wartości.');
              return data;
            });
          })
          .then(function (data) {
            if (currentRequest !== requestNumber) return;
            var items = Array.isArray(data.items) ? data.items : [];
            results.innerHTML = '';
            if (!items.length) {
              results.innerHTML = '<div class="list-group-item text-muted">Brak pasujących wartości.</div>';
              return;
            }
            items.forEach(function (option) {
              var id = String(option.id || option.value || '');
              var label = String(option.value || option.id || '');
              var button = document.createElement('button');
              button.type = 'button';
              button.className = 'list-group-item list-group-item-action d-flex justify-content-between gap-2';
              button.innerHTML = '<span>' + escapeHtml(label) + '</span><small class="text-muted">' + escapeHtml(id) + '</small>';
              button.addEventListener('click', function () {
                addSelectedOption(lookup, id, label);
                if (lookup.getAttribute('data-multiple') === '1') {
                  button.classList.add('active');
                  button.disabled = true;
                  input.focus();
                } else {
                  input.value = '';
                  hideResults();
                }
              });
              results.appendChild(button);
            });
          })
          .catch(function (error) {
            if (currentRequest !== requestNumber) return;
            results.innerHTML = '<div class="list-group-item text-danger">' + escapeHtml(error.message) + '</div>';
          });
      }

      input.addEventListener('input', function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(function () { search(false); }, 250);
      });
      input.addEventListener('focus', function () {
        if (results.classList.contains('d-none')) {
          search(true);
        }
      });
      clear.addEventListener('click', function () {
        input.value = '';
        hideResults();
        var selection = lookup.querySelector('.js-empik-option-selection');
        if (lookup.getAttribute('data-multiple') === '1') {
          selection.querySelectorAll('.empik-selected-option').forEach(function (badge) { badge.remove(); });
        } else {
          var valueInput = selection.querySelector('.js-empik-option-value');
          var notice = selection.querySelector('.js-empik-single-selection');
          if (valueInput) valueInput.value = '';
          if (notice) notice.classList.add('d-none');
        }
      });
      lookup.addEventListener('click', function (event) {
        var remove = event.target.closest('.js-remove-empik-option');
        if (remove) remove.closest('.empik-selected-option').remove();
      });

      var parameterCard = lookup.closest('.js-empik-param-card');
      if (!parameterCard || parameterCard.getAttribute('data-unused') !== '1') {
        window.setTimeout(function () {
          search(true);
        }, 50 + (lookupIndex * 40));
      }
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
