<div class="container-fluid py-4">
  <ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link" href="{$baseUrl}?controller=computers&action=products">Produkty</a></li>
    <li class="nav-item"><a class="nav-link" href="{$baseUrl}?controller=computers&action=components">Komponenty</a></li>
    <li class="nav-item"><a class="nav-link active" href="{$baseUrl}?controller=computers&action=csvtemplates">Szablony CSV</a></li>
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
              <select class="form-select form-select-sm" id="descriptionTokenSelect" style="max-width:360px">
                <option value="">Wstaw pole produktu lub komponentu...</option>
              </select>
              <button type="button" class="btn btn-sm btn-outline-primary description-command" data-command="bold"><strong>B</strong></button>
              <button type="button" class="btn btn-sm btn-outline-primary description-block" data-block="h2">H2</button>
              <button type="button" class="btn btn-sm btn-outline-primary description-block" data-block="h3">H3</button>
              <button type="button" class="btn btn-sm btn-outline-primary description-block" data-block="p">Akapit</button>
              <button type="button" class="btn btn-sm btn-outline-primary description-command" data-command="insertUnorderedList">Lista</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="showDescriptionSource">HTML</button>
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
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th style="width:45px"></th><th>Nagłówek</th><th style="width:140px">Typ</th><th>Źródło / wartość stała</th><th style="width:70px"></th></tr></thead>
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
  const descriptionProductSearchUrl = '{$baseUrl|escape:"javascript"}?controller=computers&action=searchcsvpreviewproducts';
  const descriptionPreviewUrl = '{$baseUrl|escape:"javascript"}?controller=computers&action=previewcsvdescription';
{literal}
  const body = document.getElementById('csvColumnsBody');
  const descriptionInput = document.getElementById('descriptionTemplateInput');
  const descriptionVisualEditor = document.getElementById('descriptionVisualEditor');
  const descriptionSourceEditor = document.getElementById('descriptionSourceEditor');
  const descriptionPreview = document.getElementById('descriptionTemplatePreview');
  const descriptionTokenSelect = document.getElementById('descriptionTokenSelect');
  const descriptionProductSearch = document.getElementById('descriptionProductSearch');
  const descriptionProductResults = document.getElementById('descriptionProductResults');
  const descriptionPreviewProductId = document.getElementById('descriptionPreviewProductId');
  const selectedDescriptionProduct = document.getElementById('selectedDescriptionProduct');
  let descriptionSearchTimer = null;

  function sourceSelect(value) {
    const select = document.createElement('select');
    select.className = 'form-select form-select-sm source-select';
    select.name = 'column_value[]';
    if (value && !Object.prototype.hasOwnProperty.call(sourceOptions, value)) {
      const legacyOption = document.createElement('option');
      legacyOption.value = value;
      legacyOption.textContent = 'Aktualnie zapisane źródło: ' + value;
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

  function addRow(column = {header: '', type: 'source', value: ''}) {
    const row = document.createElement('tr');
    row.innerHTML = '<td class="text-center text-nowrap"><button type="button" class="btn btn-sm btn-link p-0 move-up" title="W górę">↑</button> <button type="button" class="btn btn-sm btn-link p-0 move-down" title="W dół">↓</button></td>'
      + '<td><input class="form-control form-control-sm" name="column_header[]" required></td>'
      + '<td><select class="form-select form-select-sm type-select" name="column_type[]"><option value="source">Źródło</option><option value="static">Stała</option><option value="template">Łączenie pól</option></select></td>'
      + '<td class="value-cell"></td>'
      + '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-column">×</button></td>';
    row.querySelector('input').value = column.header || '';
    row.querySelector('.type-select').value = ['source', 'static', 'template'].includes(column.type) ? column.type : 'source';
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
      } else {
        cell.appendChild(searchableSourcePicker(column.type === 'source' ? (column.value || '') : 'product.name'));
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

  function syncDescriptionInput() {
    descriptionInput.value = descriptionVisualEditor.innerHTML;
    descriptionSourceEditor.value = descriptionVisualEditor.innerHTML;
  }

  function insertHtmlAtCursor(html) {
    descriptionVisualEditor.focus();
    document.execCommand('insertHTML', false, html);
    syncDescriptionInput();
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

  descriptionTokenSelect.addEventListener('change', function () {
    if (!this.value) return;
    insertHtmlAtCursor('{{' + this.value + '}}');
    this.value = '';
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
      descriptionVisualEditor.innerHTML = descriptionSourceEditor.value;
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
  descriptionVisualEditor.innerHTML = descriptionInput.value;
  syncDescriptionInput();
  document.getElementById('computerCsvTemplateForm').addEventListener('submit', function () {
    if (!descriptionSourceEditor.classList.contains('d-none')) {
      descriptionInput.value = descriptionSourceEditor.value;
    } else {
      syncDescriptionInput();
    }
  });
{/literal}
})();
</script>
