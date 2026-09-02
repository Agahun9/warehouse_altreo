<div class="container-fluid py-4">
  <ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link" href="{$baseUrl}?controller=computers&action=products">Produkty</a></li>
    <li class="nav-item"><a class="nav-link" href="{$baseUrl}?controller=computers&action=components">Komponenty</a></li>
    <li class="nav-item"><a class="nav-link active" href="{$baseUrl}?controller=computers&action=csvtemplates">Szablony CSV</a></li>
    <li class="nav-item"><a class="nav-link" href="{$baseUrl}?controller=computers&action=titletemplates">Szablony tytułów</a></li>
  </ul>

  <form method="post" action="{$baseUrl}?controller=computers&action=savecsvtemplate" id="computerCsvTemplateForm">
    <input type="hidden" name="id" value="{$template.id}">
    <div class="card shadow-sm mb-4">
      <div class="card-header"><strong>Ustawienia szablonu</strong></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Nazwa</label><input class="form-control" name="name" value="{$template.name|escape:'html'}" required></div>
          <div class="col-md-3"><label class="form-label">Prefiks pliku</label><input class="form-control" name="filename_prefix" value="{$template.filename_prefix|escape:'html'}" required></div>
          <div class="col-md-2"><label class="form-label">Separator</label>
            <select class="form-select" name="delimiter">
              <option value=";" {if $template.delimiter eq ';'}selected{/if}>średnik ;</option>
              <option value="," {if $template.delimiter eq ','}selected{/if}>przecinek ,</option>
              <option value="|" {if $template.delimiter eq '|'}selected{/if}>kreska |</option>
            </select>
          </div>
          <div class="col-md-2"><label class="form-label">Kodowanie</label>
            <select class="form-select" name="encoding"><option value="UTF-8" {if $template.encoding eq 'UTF-8'}selected{/if}>UTF-8</option><option value="WINDOWS-1250" {if $template.encoding eq 'WINDOWS-1250'}selected{/if}>Windows-1250</option></select>
          </div>
          <div class="col-md-1 d-flex align-items-end"><div class="form-check mb-2"><input type="checkbox" class="form-check-input" name="add_bom" value="1" id="addBom" {if $template.add_bom}checked{/if}><label for="addBom" class="form-check-label">BOM</label></div></div>
          <div class="col-md-2 d-flex align-items-end"><div class="form-check mb-2"><input type="checkbox" class="form-check-input" name="is_active" value="1" id="isActive" {if $template.is_active|default:1}checked{/if}><label for="isActive" class="form-check-label">Aktywny</label></div></div>
          <div class="col-12"><label class="form-label">Opis</label><textarea class="form-control" name="description" rows="2">{$template.description|escape:'html'}</textarea></div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <strong>Generator opisu</strong>
          <div class="small text-muted">Opis jest używany przez kolumny ze źródłem „Opis z szablonu komputera”.</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshDescriptionPreview">Pobierz podgląd produktu</button>
      </div>
      <div class="card-body">
        <div class="row g-2 align-items-end mb-3">
          <div class="col-lg-8 position-relative">
            <label class="form-label">Produkt do podglądu</label>
            <input type="search" class="form-control" id="descriptionProductSearch" autocomplete="off" placeholder="Wpisz ID, nazwę, SKU albo EAN...">
            <input type="hidden" id="descriptionPreviewProductId" value="">
            <div id="descriptionProductResults" class="list-group position-absolute start-0 end-0 shadow" style="z-index:1050;display:none;max-height:320px;overflow:auto"></div>
          </div>
          <div class="col-lg-4">
            <div class="small text-muted" id="selectedDescriptionProduct">Nie wybrano produktu.</div>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-12">
            <div class="d-flex flex-wrap gap-2 mb-2">
              <button type="button" class="btn btn-sm btn-outline-primary description-command" data-command="bold"><strong>B</strong></button>
              <button type="button" class="btn btn-sm btn-outline-primary description-block" data-block="h2">H2</button>
              <button type="button" class="btn btn-sm btn-outline-primary description-block" data-block="h3">H3</button>
              <button type="button" class="btn btn-sm btn-outline-primary description-block" data-block="p">Akapit</button>
              <button type="button" class="btn btn-sm btn-outline-primary description-command" data-command="insertUnorderedList">Lista</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="showDescriptionSource">HTML</button>
            </div>
            <div class="border border-primary-subtle rounded bg-primary-subtle p-2 mb-2">
              <div class="small fw-semibold mb-2"><i class="bi bi-signpost-split me-1"></i>Warunek według konkretnego komponentu</div>
              <div class="row g-2">
                <div class="col-lg-4">
                  <input type="search" class="form-control form-control-sm mb-1" id="descriptionConditionSearch" placeholder="Szukaj komponentu...">
                  <select class="form-select form-select-sm" id="descriptionConditionIf">
                    <option value="">Komponent dla IF...</option>
                  </select>
                </div>
                <div class="col-lg-4">
                  <div class="small text-muted mb-1">Opcjonalny ELSE IF</div>
                  <select class="form-select form-select-sm" id="descriptionConditionElseIf">
                    <option value="">Bez ELSE IF</option>
                  </select>
                </div>
                <div class="col-lg-4 d-flex flex-wrap align-content-end gap-1">
                  <button type="button" class="btn btn-sm btn-primary" id="insertDescriptionCondition">Wstaw cały IF / ELSE</button>
                  <button type="button" class="btn btn-sm btn-outline-primary description-condition-tag" data-tag="elseif">Wstaw ELSE IF</button>
                  <button type="button" class="btn btn-sm btn-outline-primary description-condition-tag" data-tag="else">Wstaw ELSE</button>
                  <button type="button" class="btn btn-sm btn-outline-primary description-condition-tag" data-tag="endif">Zakończ IF</button>
                </div>
              </div>
              <div class="form-text">Wybierz komponent. Podczas eksportu pojawi się tylko treść pierwszej pasującej gałęzi. Warunki można sprawdzić w podglądzie produktu.</div>
            </div>
            <div class="border border-primary-subtle rounded bg-primary-subtle p-2 mb-2">
              <div class="small fw-semibold mb-2"><i class="bi bi-collection me-1"></i>Warunek: dowolny z wielu komponentów naraz</div>
              <div class="row g-2">
                <div class="col-lg-6">
                  <input type="search" class="form-control form-control-sm mb-1" id="descriptionConditionMultiSearch" placeholder="Szukaj komponentu...">
                  <div class="border rounded bg-white p-2" id="descriptionConditionMultiList" style="max-height:180px;overflow:auto"></div>
                </div>
                <div class="col-lg-6 d-flex flex-wrap align-content-start gap-1">
                  <button type="button" class="btn btn-sm btn-primary" id="insertDescriptionConditionMulti">Wstaw IF (którykolwiek zaznaczony)</button>
                  <button type="button" class="btn btn-sm btn-outline-primary" id="insertDescriptionConditionMultiElseIf">Wstaw jako ELSE IF</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" id="clearDescriptionConditionMulti">Odznacz wszystkie</button>
                </div>
              </div>
              <div class="form-text">Zaznacz kilka komponentów (np. warianty dysku), aby warunek był prawdziwy, gdy produkt ma <strong>którykolwiek</strong> z nich, np. <code>{ldelim}% if has_component_in(29,28,27,26) %{rdelim}</code>.</div>
            </div>
            <div class="border rounded bg-light p-2 mb-2">
              <div class="small fw-semibold mb-2">Parametry Allegro, Empik, MediaMarkt i Morele</div>
              <div class="d-flex flex-wrap gap-2">
                <input type="search" class="form-control form-control-sm" id="descriptionParameterSearch" style="max-width:300px" placeholder="Szukaj parametru, kategorii lub ID...">
                <select class="form-select form-select-sm" id="descriptionParameterSelect" style="max-width:440px">
                  <option value="">Wybierz parametr...</option>
                </select>
                <button type="button" class="btn btn-sm btn-outline-primary" id="insertDescriptionParameter">Wstaw parametr</button>
              </div>
              <div class="form-text">Lista zawiera parametry zapisane w komponentach. W opisie zostanie użyta wartość parametru komponentu przypisanego do produktu.</div>
            </div>
            <div class="border rounded bg-light p-2 mb-2">
              <div class="small fw-semibold mb-2">Pola produktu i komponentów</div>
              <div class="d-flex flex-wrap gap-2">
                <input type="search" class="form-control form-control-sm" id="descriptionTokenSearch" style="max-width:300px" placeholder="Szukaj pola produktu lub komponentu...">
                <select class="form-select form-select-sm" id="descriptionTokenSelect" style="max-width:440px">
                  <option value="">Wybierz pole...</option>
                </select>
                <button type="button" class="btn btn-sm btn-outline-primary" id="insertDescriptionToken">Wstaw pole</button>
              </div>
              <div class="row g-2 mt-1 d-none" id="descriptionImageOptions">
                <div class="col-sm-3 col-lg-2">
                  <label class="form-label small mb-1" for="descriptionImageWidth">Szerokość</label>
                  <input type="text" class="form-control form-control-sm" id="descriptionImageWidth" value="100%" placeholder="np. 100%, 600px, auto">
                </div>
                <div class="col-sm-3 col-lg-2">
                  <label class="form-label small mb-1" for="descriptionImageHeight">Wysokość</label>
                  <input type="text" class="form-control form-control-sm" id="descriptionImageHeight" value="auto" placeholder="np. auto, 400px">
                </div>
                <div class="col-sm-3 col-lg-2">
                  <label class="form-label small mb-1" for="descriptionImageFit">Dopasowanie</label>
                  <select class="form-select form-select-sm" id="descriptionImageFit">
                    <option value="contain">contain</option>
                    <option value="cover">cover</option>
                    <option value="fill">fill</option>
                    <option value="none">none</option>
                  </select>
                </div>
                <div class="col-sm-6 col-lg-4">
                  <label class="form-label small mb-1" for="descriptionImageAlt">Tekst ALT</label>
                  <input type="text" class="form-control form-control-sm" id="descriptionImageAlt" placeholder="Opis zdjęcia (opcjonalnie)">
                </div>
                <div class="col-12">
                  <div class="form-text">Dla pola zdjęcia zostanie wstawiony znacznik <code>&lt;img src=&quot;{ldelim}{ldelim}pole_zdjecia{rdelim}{rdelim}&quot;&gt;</code>. Wymiary mogą mieć wartości np. <code>100%</code>, <code>600px</code> lub <code>auto</code>.</div>
                </div>
              </div>
            </div>
            <input type="hidden" name="description_template" id="descriptionTemplateInput" value="{$template.description_template|default:''|escape:'html'}">
            <div class="form-control bg-white" id="descriptionVisualEditor" contenteditable="true" style="min-height:460px;max-height:720px;overflow:auto"></div>
            <textarea class="form-control font-monospace mt-2 d-none" rows="18" id="descriptionSourceEditor">{$template.description_template|default:''|escape:'html'}</textarea>
            <div class="form-text">
              Tekst jest formatowany bezpośrednio w edytorze. Tokeny, np.
              <code>{ldelim}{ldelim}product.name{rdelim}{rdelim}</code>, zostaną podmienione podczas eksportu.
              Przycisk „HTML” służy do zaawansowanej edycji warunków.
            </div>
          </div>
          <div class="col-12">
            <div class="border rounded bg-white p-3">
              <div class="small fw-semibold text-uppercase text-muted mb-2">Podgląd na wybranym produkcie</div>
              <div id="descriptionTemplatePreview"><span class="text-muted">Wyszukaj i wybierz produkt powyżej.</span></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Kolumny CSV</strong>
        <button type="button" class="btn btn-sm btn-primary" id="addCsvColumn"><i class="bi bi-plus-circle"></i> Dodaj kolumnę</button>
      </div>
      <div class="card-body p-0">
        <div class="small text-muted p-3 pb-2">
          <strong>Formatowanie</strong> (opcjonalnie, kolumna „Formatowanie”): <code>upper</code> – zamiana na wielkie litery,
          <code>lower</code> – zamiana na małe litery, <code>ucfirst</code> – pierwsza litera tekstu jako wielka,
          <code>trim</code> – usunięcie spacji z początku i końca, <code>date:Y-m-d</code> – format daty,
          <code>number:2:,: </code> – format liczbowy: 2 miejsca, przecinek dziesiętny, spacja tysięczna,
          <code>length:2000</code> – obcięcie tekstu do maksymalnie 2000 znaków.
          Typ <strong>Warunek</strong> pozwala wpisać wartość zależną od pola, np. jeśli <code>MONITOR: nazwa</code>
          zawiera <code>24</code>, wpisz kategorię zestawu, w przeciwnym razie inną wartość.
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th style="width:45px"></th><th>Nagłówek</th><th style="width:130px">Typ</th><th>Źródło / wartość / warunek</th><th style="width:190px">Formatowanie</th><th style="width:70px"></th></tr></thead>
            <tbody id="csvColumnsBody"></tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex justify-content-between">
        <a href="{$baseUrl}?controller=computers&action=csvtemplates" class="btn btn-outline-secondary">Anuluj</a>
        <button class="btn btn-success">Zapisz szablon</button>
      </div>
    </div>
  </form>
</div>

<script>
(function () {
  const initialColumns = {$columnsJson nofilter};
  const sourceOptions = {$sourceOptionsJson nofilter};
  const descriptionTokens = {$descriptionTokensJson nofilter};
  const descriptionParameterTokens = {$descriptionParameterTokensJson nofilter};
  const descriptionConditionComponents = {$descriptionConditionComponentsJson nofilter};
  const descriptionProductSearchUrl = '{$baseUrl|escape:"javascript"}?controller=computers&action=searchcsvpreviewproducts';
  const descriptionPreviewUrl = '{$baseUrl|escape:"javascript"}?controller=computers&action=previewcsvdescription';
{literal}
  const body = document.getElementById('csvColumnsBody');
  const descriptionInput = document.getElementById('descriptionTemplateInput');
  const descriptionVisualEditor = document.getElementById('descriptionVisualEditor');
  const descriptionSourceEditor = document.getElementById('descriptionSourceEditor');
  const descriptionPreview = document.getElementById('descriptionTemplatePreview');
  const descriptionTokenSearch = document.getElementById('descriptionTokenSearch');
  const descriptionTokenSelect = document.getElementById('descriptionTokenSelect');
  const descriptionImageOptions = document.getElementById('descriptionImageOptions');
  const descriptionImageWidth = document.getElementById('descriptionImageWidth');
  const descriptionImageHeight = document.getElementById('descriptionImageHeight');
  const descriptionImageFit = document.getElementById('descriptionImageFit');
  const descriptionImageAlt = document.getElementById('descriptionImageAlt');
  const descriptionParameterSearch = document.getElementById('descriptionParameterSearch');
  const descriptionParameterSelect = document.getElementById('descriptionParameterSelect');
  const descriptionConditionSearch = document.getElementById('descriptionConditionSearch');
  const descriptionConditionIf = document.getElementById('descriptionConditionIf');
  const descriptionConditionElseIf = document.getElementById('descriptionConditionElseIf');
  const descriptionProductSearch = document.getElementById('descriptionProductSearch');
  const descriptionProductResults = document.getElementById('descriptionProductResults');
  const descriptionPreviewProductId = document.getElementById('descriptionPreviewProductId');
  const selectedDescriptionProduct = document.getElementById('selectedDescriptionProduct');
  let descriptionSearchTimer = null;
  let descriptionEditorRange = null;

  function sourceSelect(value) {
    const select = document.createElement('select');
    select.className = 'form-select form-select-sm source-select';
    select.name = 'column_value[]';
    if (value && !Object.prototype.hasOwnProperty.call(sourceOptions, value)) {
      const legacyOption = document.createElement('option');
      legacyOption.value = value;
      if (value.startsWith('mediamarkt_param:')) {
        legacyOption.textContent = 'MediaMarkt - PC parametr: ' + value.slice('mediamarkt_param:'.length);
      } else if (value.startsWith('mediamarkt_set_pc_param:')) {
        legacyOption.textContent = 'MediaMarkt - zestaw PC parametr: ' + value.slice('mediamarkt_set_pc_param:'.length);
      } else {
        legacyOption.textContent = 'Aktualnie zapisane źródło: ' + value;
      }
      legacyOption.selected = true;
      select.appendChild(legacyOption);
    }
    Object.entries(sourceOptions).forEach(([key, label]) => {
      const option = document.createElement('option');
      option.value = key;
      option.textContent = label;
      option.selected = key === value;
      select.appendChild(option);
    });
    return select;
  }

  function searchableSourcePicker(value, selectName = 'column_value[]') {
    const wrapper = document.createElement('div');
    wrapper.className = 'd-grid gap-1';
    const search = document.createElement('input');
    search.type = 'search';
    search.className = 'form-control form-control-sm';
    search.placeholder = 'Szukaj pola, komponentu, zdjęcia...';
    const select = sourceSelect(value);
    select.name = selectName;
    select.size = 1;

    search.addEventListener('input', function () {
      const query = this.value.trim().toLocaleLowerCase('pl');
      let firstVisible = null;
      Array.from(select.options).forEach(function (option) {
        const matches = query === ''
          || option.textContent.toLocaleLowerCase('pl').includes(query)
          || option.value.toLocaleLowerCase('pl').includes(query);
        option.hidden = !matches;
        option.disabled = !matches;
        if (matches && firstVisible === null) firstVisible = option;
      });
      if (query !== '') {
        select.size = Math.min(8, Math.max(2, Array.from(select.options).filter((option) => !option.hidden).length));
        if (select.selectedOptions.length === 0 || select.selectedOptions[0].hidden) {
          if (firstVisible) firstVisible.selected = true;
        }
      } else {
        select.size = 1;
        Array.from(select.options).forEach((option) => {
          option.hidden = false;
          option.disabled = false;
        });
      }
    });
    search.addEventListener('focus', function () {
      if (this.value.trim() !== '') this.dispatchEvent(new Event('input'));
    });
    search.addEventListener('blur', function () {
      window.setTimeout(() => { select.size = 1; }, 150);
    });
    select.addEventListener('change', function () {
      select.size = 1;
    });

    wrapper.appendChild(search);
    wrapper.appendChild(select);
    return wrapper;
  }

  function hiddenInput(name, value = '') {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    return input;
  }

  function appendEmptyConditionInputs(cell) {
    cell.appendChild(hiddenInput('column_condition_source[]'));
    cell.appendChild(hiddenInput('column_condition_operator[]'));
    cell.appendChild(hiddenInput('column_condition_value[]'));
    cell.appendChild(hiddenInput('column_condition_else_value[]'));
  }

  function addRow(column = {header: '', type: 'source', value: '', format: ''}) {
    const row = document.createElement('tr');
    row.innerHTML = '<td class="text-center text-nowrap"><button type="button" class="btn btn-sm btn-link p-0 move-up" title="W górę">↑</button> <button type="button" class="btn btn-sm btn-link p-0 move-down" title="W dół">↓</button></td>'
      + '<td><input class="form-control form-control-sm" name="column_header[]" required></td>'
      + '<td><select class="form-select form-select-sm type-select" name="column_type[]"><option value="source">Źródło</option><option value="static">Stała</option><option value="template">Łączenie pól</option><option value="conditional">Warunek</option></select></td>'
      + '<td class="value-cell"></td>'
      + '<td><input type="text" class="form-control form-control-sm format-input" name="column_format[]" placeholder="np. upper, date:Y-m-d" title="upper, lower, ucfirst, trim, date:Y-m-d, number:2:,: , length:2000"></td>'
      + '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-column">×</button></td>';
    row.querySelector('input').value = column.header || '';
    row.querySelector('.type-select').value = ['source', 'static', 'template', 'conditional'].includes(column.type) ? column.type : 'source';
    row.querySelector('.format-input').value = column.format || '';
    body.appendChild(row);

    function renderValue() {
      const cell = row.querySelector('.value-cell');
      cell.innerHTML = '';
      const selectedType = row.querySelector('.type-select').value;
      if (selectedType === 'static') {
        const input = document.createElement('textarea');
        input.className = 'form-control form-control-sm';
        input.name = 'column_value[]';
        input.rows = 1;
        input.value = column.type === 'static' ? (column.value || '') : '';
        cell.appendChild(input);
        appendEmptyConditionInputs(cell);
      } else if (selectedType === 'template') {
        const wrapper = document.createElement('div');
        wrapper.className = 'row g-2';
        wrapper.innerHTML = '<div class="col-lg-8"><textarea class="form-control form-control-sm template-value" name="column_value[]" rows="3" placeholder="{{product.name}}&#10;{{component.GPU.name_spec}}"></textarea></div>'
          + '<div class="col-lg-4"><div class="d-grid gap-1"><input type="search" class="form-control form-control-sm template-field-search" placeholder="Szukaj pola..."><select class="form-select form-select-sm template-field"></select><button type="button" class="btn btn-sm btn-outline-primary insert-template-field">Dodaj pole</button></div></div>'
          + '<div class="col-12"><div class="form-text">Możesz łączyć pola z tekstem stałym, np. <code>{{product.name}} - EAN: {{product.EAN}}</code>.</div></div>';
        const textarea = wrapper.querySelector('.template-value');
        textarea.value = column.type === 'template' ? (column.value || '') : '';
        const fieldSelect = wrapper.querySelector('.template-field');
        Object.entries(sourceOptions).forEach(([key, label]) => {
          const option = document.createElement('option');
          option.value = key;
          option.textContent = label;
          fieldSelect.appendChild(option);
        });
        const fieldSearch = wrapper.querySelector('.template-field-search');
        fieldSearch.addEventListener('input', function () {
          const query = this.value.trim().toLocaleLowerCase('pl');
          let firstVisible = null;
          Array.from(fieldSelect.options).forEach(function (option) {
            const matches = query === ''
              || option.textContent.toLocaleLowerCase('pl').includes(query)
              || option.value.toLocaleLowerCase('pl').includes(query);
            option.hidden = !matches;
            option.disabled = !matches;
            if (matches && firstVisible === null) firstVisible = option;
          });
          fieldSelect.size = query === ''
            ? 1
            : Math.min(8, Math.max(2, Array.from(fieldSelect.options).filter((option) => !option.hidden).length));
          if (firstVisible && (fieldSelect.selectedOptions.length === 0 || fieldSelect.selectedOptions[0].hidden)) {
            firstVisible.selected = true;
          }
        });
        fieldSelect.addEventListener('change', function () {
          fieldSelect.size = 1;
        });
        wrapper.querySelector('.insert-template-field').addEventListener('click', function () {
          const token = '{{' + fieldSelect.value + '}}';
          const start = textarea.selectionStart;
          const end = textarea.selectionEnd;
          textarea.setRangeText(token, start, end, 'end');
          textarea.focus();
        });
        cell.appendChild(wrapper);
        appendEmptyConditionInputs(cell);
      } else if (selectedType === 'conditional') {
        const condition = column.type === 'conditional' && column.condition ? column.condition : {};
        const wrapper = document.createElement('div');
        wrapper.className = 'row g-2';
        const sourceCol = document.createElement('div');
        sourceCol.className = 'col-lg-4';
        const sourceLabel = document.createElement('label');
        sourceLabel.className = 'form-label small mb-1';
        sourceLabel.textContent = 'Sprawdź pole';
        sourceCol.appendChild(sourceLabel);
        sourceCol.appendChild(searchableSourcePicker(condition.source || 'component.MONITOR.name', 'column_condition_source[]'));

        const operatorCol = document.createElement('div');
        operatorCol.className = 'col-lg-2';
        operatorCol.innerHTML = '<label class="form-label small mb-1">Warunek</label>';
        const operatorSelect = document.createElement('select');
        operatorSelect.className = 'form-select form-select-sm';
        operatorSelect.name = 'column_condition_operator[]';
        [
          ['contains', 'zawiera'],
          ['not_contains', 'nie zawiera'],
          ['equals', 'równe'],
          ['not_equals', 'różne'],
          ['empty', 'puste'],
          ['not_empty', 'niepuste'],
        ].forEach(function(item) {
          const option = document.createElement('option');
          option.value = item[0];
          option.textContent = item[1];
          option.selected = item[0] === (condition.operator || 'contains');
          operatorSelect.appendChild(option);
        });
        operatorCol.appendChild(operatorSelect);

        const matchCol = document.createElement('div');
        matchCol.className = 'col-lg-2';
        matchCol.innerHTML = '<label class="form-label small mb-1">Tekst</label><input type="text" class="form-control form-control-sm" name="column_condition_value[]" placeholder="np. 24">';
        matchCol.querySelector('input').value = condition.value || '';

        const thenCol = document.createElement('div');
        thenCol.className = 'col-lg-2';
        thenCol.innerHTML = '<label class="form-label small mb-1">Jeśli TAK</label><textarea class="form-control form-control-sm" name="column_value[]" rows="1" placeholder="np. Zestaw"></textarea>';
        thenCol.querySelector('textarea').value = column.value || '';

        const elseCol = document.createElement('div');
        elseCol.className = 'col-lg-2';
        elseCol.innerHTML = '<label class="form-label small mb-1">Jeśli NIE</label><textarea class="form-control form-control-sm" name="column_condition_else_value[]" rows="1" placeholder="np. Komputer"></textarea>';
        elseCol.querySelector('textarea').value = condition.else_value || '';

        const helpCol = document.createElement('div');
        helpCol.className = 'col-12';
        helpCol.innerHTML = '<div class="form-text">Przykład: pole <code>MONITOR: nazwa</code>, warunek <code>zawiera</code>, tekst <code>24</code>. Eksport wpisze wartość z „Jeśli TAK”, a w przeciwnym razie z „Jeśli NIE”.</div>';

        wrapper.appendChild(sourceCol);
        wrapper.appendChild(operatorCol);
        wrapper.appendChild(matchCol);
        wrapper.appendChild(thenCol);
        wrapper.appendChild(elseCol);
        wrapper.appendChild(helpCol);
        cell.appendChild(wrapper);
      } else {
        cell.appendChild(searchableSourcePicker(column.type === 'source' ? (column.value || '') : 'product.name'));
        appendEmptyConditionInputs(cell);
      }
    }
    renderValue();
    row.querySelector('.type-select').addEventListener('change', function () {
      column = {type: this.value, value: ''};
      renderValue();
    });
    row.querySelector('.remove-column').addEventListener('click', () => row.remove());
    row.querySelector('.move-up').addEventListener('click', () => {
      if (row.previousElementSibling) body.insertBefore(row, row.previousElementSibling);
    });
    row.querySelector('.move-down').addEventListener('click', () => {
      if (row.nextElementSibling) body.insertBefore(row.nextElementSibling, row);
    });
  }

  initialColumns.forEach(addRow);
  document.getElementById('addCsvColumn').addEventListener('click', () => addRow());

  Object.entries(descriptionTokens).forEach(([key, label]) => {
    const option = document.createElement('option');
    option.value = key;
    option.textContent = label;
    descriptionTokenSelect.appendChild(option);
  });
  function filterDescriptionSelect(search, select) {
    const query = search.value.trim().toLocaleLowerCase('pl');
    let firstMatch = null;
    Array.from(select.options).forEach(function (option, index) {
      const matches = index === 0 || query === ''
        || option.textContent.toLocaleLowerCase('pl').includes(query)
        || option.value.toLocaleLowerCase('pl').includes(query);
      option.hidden = !matches;
      option.disabled = !matches;
      if (index > 0 && matches && firstMatch === null) firstMatch = option;
    });
    if (firstMatch && (!select.value || select.selectedOptions[0].hidden)) {
      firstMatch.selected = true;
    }
  }
  descriptionTokenSearch.addEventListener('input', function () {
    filterDescriptionSelect(this, descriptionTokenSelect);
    updateDescriptionImageOptions();
  });
  descriptionTokenSelect.addEventListener('change', function () {
    updateDescriptionImageOptions();
  });
  Object.entries(descriptionParameterTokens).forEach(([key, label]) => {
    const option = document.createElement('option');
    option.value = key;
    option.textContent = label;
    descriptionParameterSelect.appendChild(option);
  });

  descriptionParameterSearch.addEventListener('input', function () {
    filterDescriptionSelect(this, descriptionParameterSelect);
  });
  document.getElementById('insertDescriptionParameter').addEventListener('click', function () {
    if (!descriptionParameterSelect.value) return;
    insertHtmlAtCursor('{{' + descriptionParameterSelect.value + '}}');
  });

  function syncDescriptionInput() {
    descriptionInput.value = descriptionVisualEditor.innerHTML;
    descriptionSourceEditor.value = descriptionVisualEditor.innerHTML;
  }

  function saveDescriptionEditorRange() {
    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) return;
    const range = selection.getRangeAt(0);
    const container = range.commonAncestorContainer.nodeType === Node.ELEMENT_NODE
      ? range.commonAncestorContainer
      : range.commonAncestorContainer.parentElement;
    if (container && descriptionVisualEditor.contains(container)) {
      descriptionEditorRange = range.cloneRange();
    }
  }

  function insertHtmlAtCursor(html) {
    if (!descriptionSourceEditor.classList.contains('d-none')) {
      const start = descriptionSourceEditor.selectionStart;
      const end = descriptionSourceEditor.selectionEnd;
      descriptionSourceEditor.setRangeText(html, start, end, 'end');
      descriptionInput.value = descriptionSourceEditor.value;
      descriptionSourceEditor.focus();
      return;
    }

    descriptionVisualEditor.focus();
    const selection = window.getSelection();
    let range = descriptionEditorRange;
    if (!range || !descriptionVisualEditor.contains(range.commonAncestorContainer)) {
      range = document.createRange();
      range.selectNodeContents(descriptionVisualEditor);
      range.collapse(false);
    }
    selection.removeAllRanges();
    selection.addRange(range);
    range.deleteContents();
    const fragment = range.createContextualFragment(html);
    const lastNode = fragment.lastChild;
    range.insertNode(fragment);
    if (lastNode) {
      range.setStartAfter(lastNode);
      range.collapse(true);
      selection.removeAllRanges();
      selection.addRange(range);
      descriptionEditorRange = range.cloneRange();
    }
    syncDescriptionInput();
  }

  function conditionTag(directive) {
    // Wrapping the {% ... %} marker in an HTML comment keeps it in place when it sits directly inside a
    // <table>/<tbody> (e.g. around a <tr>): browsers foster-parent stray text nodes out of tables when the
    // visual editor's HTML is re-parsed, but they leave comment nodes untouched.
    return '<!--{% ' + directive + ' %}-->';
  }

  const CONDITION_MARKER_RE = /(?:<!--\s*)?(\{%\s*(?:if\s+[^%]+?|elseif\s+[^%]+?|else\s+if\s+[^%]+?|else|endif)\s*%\})(?:\s*-->)?/gi;

  function normalizeDescriptionConditionMarkers(html) {
    // Any {% if/elseif/else/endif %} marker that isn't already wrapped in an HTML comment gets wrapped here,
    // BEFORE the string is ever handed to the browser's HTML parser (via innerHTML). Older templates saved
    // with bare markers directly inside a table would otherwise get their markers foster-parented out of the
    // table on every page load, silently breaking the condition. Already-wrapped markers pass through unchanged.
    return (html || '').replace(CONDITION_MARKER_RE, '<!--$1-->');
  }

  Object.entries(descriptionConditionComponents).forEach(([token, label]) => {
    [descriptionConditionIf, descriptionConditionElseIf].forEach(function(select) {
      const option = document.createElement('option');
      option.value = token;
      option.textContent = label;
      select.appendChild(option);
    });
  });

  descriptionConditionSearch.addEventListener('input', function() {
    const query = this.value.trim().toLocaleLowerCase('pl');
    [descriptionConditionIf, descriptionConditionElseIf].forEach(function(select) {
      let firstMatch = null;
      Array.from(select.options).forEach(function(option, index) {
        const matches = index === 0 || query === ''
          || option.textContent.toLocaleLowerCase('pl').includes(query)
          || option.value.toLocaleLowerCase('pl').includes(query);
        option.hidden = !matches;
        option.disabled = !matches;
        if (index > 0 && matches && firstMatch === null) firstMatch = option;
      });
      if (query !== '' && firstMatch && (!select.value || select.selectedOptions[0].hidden)) {
        firstMatch.selected = true;
      }
    });
  });

  document.getElementById('insertDescriptionCondition').addEventListener('click', function() {
    const ifToken = descriptionConditionIf.value;
    if (!ifToken) {
      alert('Najpierw wybierz komponent dla IF.');
      return;
    }
    const elseIfToken = descriptionConditionElseIf.value;
    let block = conditionTag('if ' + ifToken) + '<p>Treść, gdy komputer ma wybrany komponent.</p>';
    if (elseIfToken && elseIfToken !== ifToken) {
      block += conditionTag('elseif ' + elseIfToken) + '<p>Treść dla komponentu z ELSE IF.</p>';
    }
    block += conditionTag('else') + '<p>Treść, gdy żaden warunek nie pasuje.</p>' + conditionTag('endif');
    insertHtmlAtCursor(block);
  });

  document.querySelectorAll('.description-condition-tag').forEach(function(button) {
    button.addEventListener('click', function() {
      const tag = button.dataset.tag;
      if (tag === 'else') {
        insertHtmlAtCursor(conditionTag('else'));
        return;
      }
      if (tag === 'endif') {
        insertHtmlAtCursor(conditionTag('endif'));
        return;
      }
      const token = descriptionConditionElseIf.value || descriptionConditionIf.value;
      if (!token) {
        alert('Najpierw wybierz komponent.');
        return;
      }
      insertHtmlAtCursor(conditionTag('elseif ' + token));
    });
  });

  const descriptionConditionMultiSearch = document.getElementById('descriptionConditionMultiSearch');
  const descriptionConditionMultiList = document.getElementById('descriptionConditionMultiList');
  const descriptionConditionMultiCheckboxes = [];

  Object.entries(descriptionConditionComponents).forEach(([token, label]) => {
    const componentId = token.replace(/^has_component\./, '');
    if (!componentId) return;
    const wrapper = document.createElement('div');
    wrapper.className = 'form-check';
    const input = document.createElement('input');
    input.type = 'checkbox';
    input.className = 'form-check-input';
    input.value = componentId;
    input.id = 'descriptionConditionMultiCheckbox' + componentId;
    const labelEl = document.createElement('label');
    labelEl.className = 'form-check-label small';
    labelEl.htmlFor = input.id;
    labelEl.textContent = label;
    wrapper.appendChild(input);
    wrapper.appendChild(labelEl);
    descriptionConditionMultiList.appendChild(wrapper);
    descriptionConditionMultiCheckboxes.push({
      id: componentId,
      searchText: (label + ' ' + token).toLocaleLowerCase('pl'),
      wrapper: wrapper,
      input: input,
    });
  });

  descriptionConditionMultiSearch.addEventListener('input', function() {
    const query = this.value.trim().toLocaleLowerCase('pl');
    descriptionConditionMultiCheckboxes.forEach(function(item) {
      item.wrapper.style.display = (query === '' || item.searchText.includes(query)) ? '' : 'none';
    });
  });

  function selectedDescriptionConditionMultiIds() {
    return descriptionConditionMultiCheckboxes
      .filter(function(item) { return item.input.checked; })
      .map(function(item) { return item.id; });
  }

  function descriptionConditionMultiToken() {
    const ids = selectedDescriptionConditionMultiIds();
    if (ids.length === 0) return '';
    if (ids.length === 1) return 'has_component.' + ids[0];
    return 'has_component_in(' + ids.join(',') + ')';
  }

  document.getElementById('insertDescriptionConditionMulti').addEventListener('click', function() {
    const token = descriptionConditionMultiToken();
    if (!token) {
      alert('Zaznacz przynajmniej jeden komponent.');
      return;
    }
    const block = conditionTag('if ' + token) + '<p>Treść, gdy produkt ma którykolwiek z zaznaczonych komponentów.</p>'
      + conditionTag('else') + '<p>Treść, gdy żaden warunek nie pasuje.</p>' + conditionTag('endif');
    insertHtmlAtCursor(block);
  });

  document.getElementById('insertDescriptionConditionMultiElseIf').addEventListener('click', function() {
    const token = descriptionConditionMultiToken();
    if (!token) {
      alert('Zaznacz przynajmniej jeden komponent.');
      return;
    }
    insertHtmlAtCursor(conditionTag('elseif ' + token));
  });

  document.getElementById('clearDescriptionConditionMulti').addEventListener('click', function() {
    descriptionConditionMultiCheckboxes.forEach(function(item) { item.input.checked = false; });
  });

  function isDescriptionImageToken(token) {
    return /^main_img_/.test(token)
      || /^product_image\./.test(token)
      || /^component_image\./.test(token)
      || /^image\./.test(token);
  }

  function updateDescriptionImageOptions() {
    descriptionImageOptions.classList.toggle('d-none', !isDescriptionImageToken(descriptionTokenSelect.value));
  }

  function safeImageDimension(value, fallback) {
    value = String(value || '').trim().toLowerCase();
    if (/^\d+(?:\.\d+)?$/.test(value)) return value + 'px';
    return /^(auto|\d+(?:\.\d+)?(?:px|%|em|rem|vw|vh))$/.test(value) ? value : fallback;
  }

  function escapeHtmlAttribute(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function escapePreviewText(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  async function renderDescriptionPreview() {
    const productId = descriptionPreviewProductId.value;
    if (!productId) {
      descriptionPreview.innerHTML = '<span class="text-muted">Wyszukaj i wybierz produkt powyżej.</span>';
      return;
    }

    descriptionPreview.innerHTML = '<div class="text-muted py-4 text-center"><span class="spinner-border spinner-border-sm me-2"></span>Pobieranie danych produktu...</div>';
    const payload = new FormData();
    payload.append('product_id', productId);
    syncDescriptionInput();
    payload.append('description_template', descriptionInput.value);

    try {
      const response = await fetch(descriptionPreviewUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: payload
      });
      const data = await response.json();
      if (!response.ok || data.error) {
        throw new Error(data.error || 'Nie udało się wygenerować podglądu.');
      }
      descriptionPreview.innerHTML = data.html || '<span class="text-muted">Wygenerowany opis jest pusty.</span>';
    } catch (error) {
      descriptionPreview.innerHTML = '<div class="alert alert-danger mb-0">' + escapePreviewText(error.message) + '</div>';
    }
  }

  document.getElementById('insertDescriptionToken').addEventListener('click', function () {
    if (!descriptionTokenSelect.value) return;
    const token = '{{' + descriptionTokenSelect.value + '}}';
    if (!isDescriptionImageToken(descriptionTokenSelect.value)) {
      insertHtmlAtCursor(token);
      return;
    }
    const width = safeImageDimension(descriptionImageWidth.value, '100%');
    const height = safeImageDimension(descriptionImageHeight.value, 'auto');
    const fit = ['contain', 'cover', 'fill', 'none'].includes(descriptionImageFit.value)
      ? descriptionImageFit.value
      : 'contain';
    const selectedOption = descriptionTokenSelect.selectedOptions[0];
    const defaultAlt = selectedOption ? selectedOption.textContent.trim() : 'Zdjęcie produktu';
    const alt = escapeHtmlAttribute(descriptionImageAlt.value.trim() || defaultAlt);
    insertHtmlAtCursor('<img src="' + token + '" alt="' + alt + '" title="' + alt + '" style="width:' + width
      + ';height:' + height + ';object-fit:' + fit + ';max-width:100%;">');
  });
  document.querySelectorAll('.description-command').forEach((button) => {
    button.addEventListener('click', function () {
      descriptionVisualEditor.focus();
      document.execCommand(this.dataset.command, false, null);
      syncDescriptionInput();
    });
  });
  document.querySelectorAll('.description-block').forEach((button) => {
    button.addEventListener('click', function () {
      descriptionVisualEditor.focus();
      document.execCommand('formatBlock', false, this.dataset.block);
      syncDescriptionInput();
    });
  });
  document.getElementById('showDescriptionSource').addEventListener('click', function () {
    const sourceVisible = !descriptionSourceEditor.classList.contains('d-none');
    if (sourceVisible) {
      descriptionVisualEditor.innerHTML = normalizeDescriptionConditionMarkers(descriptionSourceEditor.value);
      descriptionSourceEditor.classList.add('d-none');
      descriptionVisualEditor.classList.remove('d-none');
      this.textContent = 'HTML';
      syncDescriptionInput();
    } else {
      syncDescriptionInput();
      descriptionVisualEditor.classList.add('d-none');
      descriptionSourceEditor.classList.remove('d-none');
      this.textContent = 'Edytor';
    }
  });
  descriptionSourceEditor.addEventListener('input', function () {
    descriptionInput.value = this.value;
  });
  descriptionVisualEditor.addEventListener('input', syncDescriptionInput);
  descriptionVisualEditor.addEventListener('keyup', saveDescriptionEditorRange);
  descriptionVisualEditor.addEventListener('mouseup', saveDescriptionEditorRange);
  descriptionVisualEditor.addEventListener('focus', saveDescriptionEditorRange);
  document.getElementById('refreshDescriptionPreview').addEventListener('click', renderDescriptionPreview);

  descriptionProductSearch.addEventListener('input', function () {
    window.clearTimeout(descriptionSearchTimer);
    const query = this.value.trim();
    descriptionPreviewProductId.value = '';
    selectedDescriptionProduct.textContent = 'Nie wybrano produktu.';
    if (query.length < 2) {
      descriptionProductResults.style.display = 'none';
      descriptionPreview.innerHTML = '<span class="text-muted">Wyszukaj i wybierz produkt powyżej.</span>';
      return;
    }

    descriptionSearchTimer = window.setTimeout(async function () {
      try {
        const response = await fetch(descriptionProductSearchUrl + '&q=' + encodeURIComponent(query), {
          credentials: 'same-origin'
        });
        const data = await response.json();
        descriptionProductResults.innerHTML = '';
        (data.products || []).forEach(function (product) {
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'list-group-item list-group-item-action';
          button.innerHTML = '<div class="fw-semibold">' + escapePreviewText(product.name) + '</div>'
            + '<div class="small text-muted">ID: ' + escapePreviewText(product.id)
            + ' · SKU: ' + escapePreviewText(product.sku || '-')
            + ' · EAN: ' + escapePreviewText(product.EAN || '-') + '</div>';
          button.addEventListener('click', function () {
            descriptionPreviewProductId.value = product.id;
            descriptionProductSearch.value = product.name;
            selectedDescriptionProduct.textContent = 'Wybrano ID ' + product.id + ': ' + product.name;
            descriptionProductResults.style.display = 'none';
            renderDescriptionPreview();
          });
          descriptionProductResults.appendChild(button);
        });
        if (!(data.products || []).length) {
          descriptionProductResults.innerHTML = '<div class="list-group-item text-muted">Brak wyników.</div>';
        }
        descriptionProductResults.style.display = 'block';
      } catch (error) {
        descriptionProductResults.innerHTML = '<div class="list-group-item text-danger">' + escapePreviewText(error.message) + '</div>';
        descriptionProductResults.style.display = 'block';
      }
    }, 250);
  });

  document.addEventListener('click', function (event) {
    if (!descriptionProductResults.contains(event.target) && event.target !== descriptionProductSearch) {
      descriptionProductResults.style.display = 'none';
    }
  });
  descriptionVisualEditor.innerHTML = normalizeDescriptionConditionMarkers(descriptionInput.value);
  syncDescriptionInput();
  document.getElementById('computerCsvTemplateForm').addEventListener('submit', function () {
    if (!descriptionSourceEditor.classList.contains('d-none')) {
      descriptionInput.value = descriptionSourceEditor.value;
    } else {
      syncDescriptionInput();
    }
    descriptionInput.value = normalizeDescriptionConditionMarkers(descriptionInput.value);
  });
{/literal}
})();
</script>
