<main class="app-main">
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-6">
          <h3 class="mb-0">{$contentTitle|escape}</h3>
          <p class="text-secondary mb-0">{$pageDescription|escape}</p>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-end">
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=index">Start</a></li>
            <li class="breadcrumb-item active" aria-current="page">{$breadcrumbCurrent|escape}</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      {if $flashSuccess}<div class="alert alert-success">{$flashSuccess|escape}</div>{/if}
      {if $flashError}<div class="alert alert-danger">{$flashError|escape}</div>{/if}
      {assign var=canWriteCsvTemplates value=$currentUser.role eq 'admin' or $currentUser.module_permissions.csvtemplates|default:'' eq 'edit'}

      <div class="card mb-4">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <h3 class="card-title mb-1">Szablony eksportu</h3>
            <div class="text-secondary small">Tworzenie i zarzadzanie konfiguracjami CSV do eksportu produktów.</div>
          </div>
          <div class="d-flex gap-2">
            {if $canWriteCsvTemplates}
              <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#csvTemplateCategoriesModal">Kategorie</button>
              <a href="{$baseUrl}?controller=csvtemplates&action=importproducts" class="btn btn-outline-primary">Import produktow</a>
              <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#csvTemplateImportModal">Import ustawien</button>
            {/if}
            <a href="{$baseUrl}?controller=csvtemplates&action=titlegenerator" class="btn btn-outline-secondary">Generator tytulow</a>
            {if $canWriteCsvTemplates}
              <a href="{$baseUrl}?controller=csvtemplates&action=create" class="btn btn-primary">Dodaj szablon</a>
            {else}
              <span class="badge text-bg-warning align-self-center">Tryb odczytu</span>
            {/if}
          </div>
        </div>
      </div>

      {if $canWriteCsvTemplates}
      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">Presety</h3></div>
        <div class="card-body d-flex flex-wrap gap-2">
          {foreach $presets as $presetKey => $preset}
            <a href="{$baseUrl}?controller=csvtemplates&action=create&preset={$presetKey|escape}" class="btn btn-outline-secondary btn-sm">{$preset.name|escape}</a>
          {/foreach}
        </div>
      </div>
      {/if}

      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">Kategorie</h3></div>
        <div class="card-body">
          <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 g-2">
            <div class="col">
              <a href="{$baseUrl}?controller=csvtemplates&action=index&filter_category=all&filter_name={$nameFilter|escape:'url'}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}" class="card h-100 text-decoration-none text-reset{if $categorySelected and $categoryFilter eq 'all'} border-primary bg-primary-subtle{/if}">
                <div class="card-body text-center py-3">
                  <div class="fw-semibold">Wszystkie</div>
                  <div class="small text-secondary">{$totalTemplatesCount|default:0} szablonow</div>
                </div>
              </a>
            </div>
            {foreach $templateCategories as $category}
              <div class="col">
                <a href="{$baseUrl}?controller=csvtemplates&action=index&filter_category={$category.id}&filter_name={$nameFilter|escape:'url'}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}" class="card h-100 text-decoration-none text-reset{if $categorySelected and $categoryFilter == $category.id} border-primary bg-primary-subtle{/if}">
                  <div class="card-body text-center py-3">
                    <div class="fw-semibold text-truncate">{$category.name|escape}</div>
                    <div class="small text-secondary">{$category.templates_count|default:0} szablonow</div>
                  </div>
                </a>
              </div>
            {/foreach}
            {if $uncategorizedTemplatesCount > 0}
              <div class="col">
                <a href="{$baseUrl}?controller=csvtemplates&action=index&filter_category=none&filter_name={$nameFilter|escape:'url'}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}" class="card h-100 text-decoration-none text-reset{if $categorySelected and $categoryFilter eq 'none'} border-primary bg-primary-subtle{/if}">
                  <div class="card-body text-center py-3">
                    <div class="fw-semibold">Bez kategorii</div>
                    <div class="small text-secondary">{$uncategorizedTemplatesCount|default:0} szablonow</div>
                  </div>
                </a>
              </div>
            {/if}
          </div>
          {if !$categorySelected}
            <div class="text-secondary small mt-3 mb-0">Wybierz kategorie (lub "Wszystkie"), aby zobaczyc liste szablonow.</div>
          {/if}
        </div>
      </div>

      {if $categorySelected}
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div><h3 class="card-title mb-1">Lista szablonow</h3><div class="small text-secondary">Kliknij naglowek kolumny, aby zmienic sortowanie.</div></div>
          <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-sm btn-outline-dark" id="csvTemplatesBulkExportButton" data-bs-toggle="modal" data-bs-target="#csvTemplatesBulkExportModal" disabled>Pobierz zaznaczone</button>
            <a href="{$baseUrl}?controller=csvtemplates&action=index" class="btn btn-sm btn-outline-secondary">Wroc do kategorii</a>
          </div>
        </div>
        <form method="get" action="{$baseUrl}" id="csvTemplatesFiltersForm">
          <input type="hidden" name="controller" value="csvtemplates"><input type="hidden" name="action" value="index">
          <input type="hidden" name="sort_by" value="{$sortBy|escape}"><input type="hidden" name="sort_dir" value="{$sortDir|escape}">
        </form>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-striped table-hover table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="text-center" style="width:42px;"><input type="checkbox" class="form-check-input" id="csvTemplatesSelectAll" aria-label="Zaznacz wszystkie szablony na liscie"></th>
                  <th><a class="text-decoration-none text-reset d-flex justify-content-between" href="{$baseUrl}?controller=csvtemplates&action=index&filter_name={$nameFilter|escape:'url'}&filter_category={$categoryFilter|escape:'url'}&sort_by=name&sort_dir={if $sortBy eq 'name' and $sortDir eq 'asc'}desc{else}asc{/if}">Nazwa {if $sortBy eq 'name'}<span>{if $sortDir eq 'asc'}↑{else}↓{/if}</span>{/if}</a></th>
                  <th><a class="text-decoration-none text-reset d-flex justify-content-between" href="{$baseUrl}?controller=csvtemplates&action=index&filter_name={$nameFilter|escape:'url'}&filter_category={$categoryFilter|escape:'url'}&sort_by=category&sort_dir={if $sortBy eq 'category' and $sortDir eq 'asc'}desc{else}asc{/if}">Kategoria {if $sortBy eq 'category'}<span>{if $sortDir eq 'asc'}↑{else}↓{/if}</span>{/if}</a></th>
                  <th>Opis</th>
                  <th>Format</th>
                  <th><a class="text-decoration-none text-reset" href="{$baseUrl}?controller=csvtemplates&action=index&filter_name={$nameFilter|escape:'url'}&filter_category={$categoryFilter|escape:'url'}&sort_by=columns&sort_dir={if $sortBy eq 'columns' and $sortDir eq 'asc'}desc{else}asc{/if}">Kolumny {if $sortBy eq 'columns'}{if $sortDir eq 'asc'}↑{else}↓{/if}{/if}</a></th>
                  <th><a class="text-decoration-none text-reset" href="{$baseUrl}?controller=csvtemplates&action=index&filter_name={$nameFilter|escape:'url'}&filter_category={$categoryFilter|escape:'url'}&sort_by=created_at&sort_dir={if $sortBy eq 'created_at' and $sortDir eq 'asc'}desc{else}asc{/if}">Utworzono {if $sortBy eq 'created_at'}{if $sortDir eq 'asc'}↑{else}↓{/if}{/if}</a></th>
                  <th><a class="text-decoration-none text-reset" href="{$baseUrl}?controller=csvtemplates&action=index&filter_name={$nameFilter|escape:'url'}&filter_category={$categoryFilter|escape:'url'}&sort_by=updated_at&sort_dir={if $sortBy eq 'updated_at' and $sortDir eq 'asc'}desc{else}asc{/if}">Zmieniono {if $sortBy eq 'updated_at'}{if $sortDir eq 'asc'}↑{else}↓{/if}{/if}</a></th>
                  <th class="text-end">Akcje</th>
                </tr>
                <tr>
                  <th></th>
                  <th><input form="csvTemplatesFiltersForm" type="search" name="filter_name" class="form-control form-control-sm" value="{$nameFilter|escape}" placeholder="Szukaj po nazwie..."></th>
                  <th><select form="csvTemplatesFiltersForm" name="filter_category" class="form-select form-select-sm" onchange="document.getElementById('csvTemplatesFiltersForm').submit()"><option value="all"{if $categoryFilter eq 'all'} selected{/if}>Wszystkie kategorie</option>{foreach $templateCategories as $category}<option value="{$category.id}"{if $categoryFilter == $category.id} selected{/if}>{$category.name|escape} ({$category.templates_count|default:0})</option>{/foreach}<option value="none"{if $categoryFilter eq 'none'} selected{/if}>Bez kategorii</option></select></th>
                  <th colspan="5"></th>
                  <th class="text-end"><button form="csvTemplatesFiltersForm" type="submit" class="btn btn-sm btn-primary">Filtruj</button></th>
                </tr>
              </thead>
              <tbody>
                {if $templates}
                  {foreach $templates as $template}
                    <tr>
                      <td class="text-center"><input type="checkbox" class="form-check-input csv-template-select" value="{$template.id}" aria-label="Zaznacz szablon {$template.name|escape}"></td>
                      <td class="fw-semibold">{$template.name|escape}</td>
                      <td>{if $template.category_name}<span class="badge text-bg-primary">{$template.category_name|escape}</span>{else}<span class="text-secondary">Bez kategorii</span>{/if}</td>
                      <td>{$template.description|default:'-'|truncate:120|escape}</td>
                      <td>
                        <div><span class="badge text-bg-light border">{$template.delimiter|default:';'|escape}</span></div>
                        <div class="small text-secondary mt-1">{$template.encoding|default:'UTF-8'|escape}{if $template.add_bom|default:0} + BOM{/if}</div>
                      </td>
                      <td><span class="badge text-bg-secondary">{$template.columns_count|default:0}</span></td>
                      <td>{$template.created_at|default:'-'|escape}</td>
                      <td>{$template.updated_at|default:'-'|escape}</td>
                      <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-dark csv-template-csv-btn" data-bs-toggle="modal" data-bs-target="#csvTemplateCsvModal" data-template-id="{$template.id}" data-template-name="{$template.name|escape}">Eksport</button>
                        {if $canWriteCsvTemplates}
                          <a href="{$baseUrl}?controller=csvtemplates&action=edit&id={$template.id}" class="btn btn-sm btn-outline-primary">Edytuj</a>
                          <form method="post" action="{$baseUrl}?controller=csvtemplates&action=duplicate" class="d-inline">
                            <input type="hidden" name="id" value="{$template.id}">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Duplikuj</button>
                          </form>
                          <form method="post" action="{$baseUrl}?controller=csvtemplates&action=delete" class="d-inline" onsubmit="return confirm('Usunac szablon {$template.name|escape:'javascript'}?');">
                            <input type="hidden" name="id" value="{$template.id}">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Usun</button>
                          </form>
                        {else}
                          <span class="badge text-bg-light border">Odczyt</span>
                        {/if}
                      </td>
                    </tr>
                  {/foreach}
                {else}
                  <tr><td colspan="9" class="text-center py-4">Brak szablonow CSV.</td></tr>
                {/if}
              </tbody>
            </table>
          </div>
        </div>
      </div>
      {/if}
    </div>
  </div>
</main>

{if $canWriteCsvTemplates}
<div class="modal fade" id="csvTemplateCategoriesModal" tabindex="-1" aria-labelledby="csvTemplateCategoriesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <div><h5 class="modal-title" id="csvTemplateCategoriesModalLabel">Kategorie szablonow</h5><div class="small text-secondary">Dodawaj i porzadkuj grupy szablonow CSV.</div></div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <div class="modal-body">
        <form method="post" action="{$baseUrl}?controller=csvtemplates&action=storecategory" class="input-group mb-4">
          <input type="text" name="name" class="form-control" placeholder="Nazwa nowej kategorii" maxlength="190" required autofocus>
          <button type="submit" class="btn btn-primary">Dodaj kategorie</button>
        </form>
        <div class="list-group list-group-flush border rounded">
          {foreach $templateCategories as $category}
            <div class="list-group-item d-flex justify-content-between align-items-center gap-3">
              <div><div class="fw-semibold">{$category.name|escape}</div><div class="small text-secondary">Szablony: {$category.templates_count|default:0}</div></div>
              <form method="post" action="{$baseUrl}?controller=csvtemplates&action=deletecategory" onsubmit="return confirm('Usunac kategorie {$category.name|escape:'javascript'}? Szablony pozostana bez kategorii.');">
                <input type="hidden" name="id" value="{$category.id}"><button type="submit" class="btn btn-sm btn-outline-danger">Usun</button>
              </form>
            </div>
          {foreachelse}<div class="p-4 text-center text-secondary">Nie utworzono jeszcze zadnej kategorii.</div>{/foreach}
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button></div>
    </div>
  </div>
</div>
{/if}

{if $canWriteCsvTemplates}
<div class="modal fade" id="csvTemplateImportModal" tabindex="-1" aria-labelledby="csvTemplateImportModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="post" action="{$baseUrl}?controller=csvtemplates&action=importtemplate" enctype="multipart/form-data" id="csvTemplateImportForm">
        <div class="modal-header">
          <div>
            <h5 class="modal-title" id="csvTemplateImportModalLabel">Wgraj ustawienia szablonu</h5>
            <div class="small text-secondary">Obslugiwane sa pliki ustawien CSV i JSON wyeksportowane z tego modulu.</div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="csvTemplateImportFile" class="form-label">Plik ustawien</label>
            <input type="file" name="template_file" id="csvTemplateImportFile" class="form-control" accept=".csv,.json,text/csv,application/json" required>
            <div class="form-text">Maksymalny rozmiar pliku: 5 MB.</div>
          </div>
          <div class="mb-3">
            <label class="form-label d-block">Sposob wczytania</label>
            <div class="form-check">
              <input class="form-check-input csv-template-import-mode" type="radio" name="import_template_mode" id="csvTemplateImportCreate" value="create" checked>
              <label class="form-check-label" for="csvTemplateImportCreate">Wczytaj jako nowy szablon</label>
            </div>
            <div class="form-check mt-2">
              <input class="form-check-input csv-template-import-mode" type="radio" name="import_template_mode" id="csvTemplateImportUpdate" value="update">
              <label class="form-check-label" for="csvTemplateImportUpdate">Wczytaj do edycji istniejacego szablonu</label>
            </div>
          </div>
          <div class="mb-3 d-none" id="csvTemplateImportTargetWrap">
            <label for="csvTemplateImportTarget" class="form-label">Szablon do edycji</label>
            <select name="target_template_id" id="csvTemplateImportTarget" class="form-select">
              <option value="">Wybierz szablon...</option>
              {foreach $allTemplatesForSelect as $templateOption}
                <option value="{$templateOption.id}">{$templateOption.name|escape}</option>
              {/foreach}
            </select>
          </div>
          <div class="alert alert-info mb-0 py-2 px-3 small">
            Plik najpierw wypelni formularz. Zmiany w bazie zostana zapisane dopiero po ich sprawdzeniu i kliknieciu <strong>Zapisz szablon</strong>.
            Przy wczytywaniu do istniejacego szablonu plik bez opisow pozostawi jego obecne opisy bez zmian.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
          <button type="submit" class="btn btn-primary">Wczytaj do formularza</button>
        </div>
      </form>
    </div>
  </div>
</div>
{/if}

<div class="modal fade" id="csvTemplatesBulkExportModal" tabindex="-1" aria-labelledby="csvTemplatesBulkExportModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="post" action="{$baseUrl}?controller=csvtemplates&action=exporttemplatesarchive" id="csvTemplatesBulkExportForm">
        <div class="modal-header">
          <div>
            <h5 class="modal-title" id="csvTemplatesBulkExportModalLabel">Pobierz zaznaczone szablony</h5>
            <div class="small text-secondary"><span id="csvTemplatesBulkExportCount">0</span> zaznaczonych szablonow</div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
        </div>
        <div class="modal-body">
          <p class="mb-3">Wybierz format plikow w paczce ZIP.</p>
          <div id="csvTemplatesBulkExportIds"></div>
          <div class="row g-3">
            <div class="col-sm-6">
              <div class="border rounded p-3 h-100">
                <div class="fw-semibold mb-2">CSV</div>
                <div class="d-grid gap-2">
                  <button type="submit" name="export_choice" value="csv_0" class="btn btn-sm btn-outline-primary">Bez opisow</button>
                  <button type="submit" name="export_choice" value="csv_1" class="btn btn-sm btn-primary">Z opisami</button>
                </div>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="border rounded p-3 h-100">
                <div class="fw-semibold mb-2">JSON</div>
                <div class="d-grid gap-2">
                  <button type="submit" name="export_choice" value="json_0" class="btn btn-sm btn-outline-primary">Bez opisow</button>
                  <button type="submit" name="export_choice" value="json_1" class="btn btn-sm btn-primary">Z opisami</button>
                </div>
              </div>
            </div>
            <div class="col-12">
              <div class="border rounded p-3">
                <div class="fw-semibold mb-2">CSV + JSON</div>
                <div class="d-flex gap-2 flex-wrap">
                  <button type="submit" name="export_choice" value="both_0" class="btn btn-sm btn-outline-dark">Oba formaty bez opisow</button>
                  <button type="submit" name="export_choice" value="both_1" class="btn btn-sm btn-dark">Oba formaty z opisami</button>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="csvTemplateCsvModal" tabindex="-1" aria-labelledby="csvTemplateCsvModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="csvTemplateCsvModalLabel">Eksportuj ustawienia szablonu</h5>
          <div class="small text-secondary" id="csvTemplateCsvName"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">Wybierz format oraz zakres danych.</p>
        <div class="small text-secondary mb-3">Wariant z opisami zawiera pole <strong>Opis</strong> oraz rozbudowane <strong>Szablony opisu</strong>. Wariant bez opisow jest mniejszy i zawiera pozostale ustawienia oraz wszystkie kolumny. Plik JSON zawiera dodatkowo aktualna liste pol i parametrow dostepnych w bazie, aby mozna bylo przekazac go do uzupelnienia przez ChatGPT.</div>
        <div class="row g-3">
          <div class="col-sm-6">
            <div class="border rounded p-3 h-100">
              <div class="fw-semibold mb-2">CSV</div>
              <div class="d-grid gap-2">
                <a href="#" class="btn btn-sm btn-outline-primary" id="csvTemplateCsvWithoutDescriptions">Bez opisow</a>
                <a href="#" class="btn btn-sm btn-primary" id="csvTemplateCsvWithDescriptions">Z opisami</a>
              </div>
            </div>
          </div>
          <div class="col-sm-6">
            <div class="border rounded p-3 h-100">
              <div class="fw-semibold mb-2">JSON</div>
              <div class="d-grid gap-2">
                <a href="#" class="btn btn-sm btn-outline-primary" id="csvTemplateJsonWithoutDescriptions">Bez opisow</a>
                <a href="#" class="btn btn-sm btn-primary" id="csvTemplateJsonWithDescriptions">Z opisami</a>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var modal = document.getElementById('csvTemplateCsvModal');
  if (!modal) {
    return;
  }

  modal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    if (!button) {
      return;
    }

    var templateId = String(button.getAttribute('data-template-id') || '');
    var templateName = String(button.getAttribute('data-template-name') || '');
    var csvExportUrl = '{$baseUrl|escape:"javascript"}?controller=csvtemplates&action=exporttemplatecsv&id=' + encodeURIComponent(templateId) + '&include_descriptions=';
    var jsonExportUrl = '{$baseUrl|escape:"javascript"}?controller=csvtemplates&action=exporttemplatejson&id=' + encodeURIComponent(templateId) + '&include_descriptions=';

    document.getElementById('csvTemplateCsvName').textContent = templateName;
    document.getElementById('csvTemplateCsvWithoutDescriptions').href = csvExportUrl + '0';
    document.getElementById('csvTemplateCsvWithDescriptions').href = csvExportUrl + '1';
    document.getElementById('csvTemplateJsonWithoutDescriptions').href = jsonExportUrl + '0';
    document.getElementById('csvTemplateJsonWithDescriptions').href = jsonExportUrl + '1';
  });

  var selectAll = document.getElementById('csvTemplatesSelectAll');
  var templateCheckboxes = Array.prototype.slice.call(document.querySelectorAll('.csv-template-select'));
  var bulkExportButton = document.getElementById('csvTemplatesBulkExportButton');
  var bulkExportModal = document.getElementById('csvTemplatesBulkExportModal');
  var bulkExportIds = document.getElementById('csvTemplatesBulkExportIds');
  var bulkExportCount = document.getElementById('csvTemplatesBulkExportCount');

  function selectedTemplateIds() {
    return templateCheckboxes.filter(function (checkbox) {
      return checkbox.checked;
    }).map(function (checkbox) {
      return checkbox.value;
    });
  }

  function refreshBulkSelection() {
    var selectedCount = selectedTemplateIds().length;
    if (bulkExportButton) {
      bulkExportButton.disabled = selectedCount === 0;
      bulkExportButton.textContent = selectedCount > 0
        ? 'Pobierz zaznaczone (' + selectedCount + ')'
        : 'Pobierz zaznaczone';
    }
    if (selectAll) {
      selectAll.checked = templateCheckboxes.length > 0 && selectedCount === templateCheckboxes.length;
      selectAll.indeterminate = selectedCount > 0 && selectedCount < templateCheckboxes.length;
    }
  }

  if (selectAll) {
    selectAll.addEventListener('change', function () {
      templateCheckboxes.forEach(function (checkbox) {
        checkbox.checked = selectAll.checked;
      });
      refreshBulkSelection();
    });
  }
  templateCheckboxes.forEach(function (checkbox) {
    checkbox.addEventListener('change', refreshBulkSelection);
  });
  if (bulkExportModal) {
    bulkExportModal.addEventListener('show.bs.modal', function (event) {
      var ids = selectedTemplateIds();
      if (ids.length === 0) {
        event.preventDefault();
        return;
      }

      bulkExportIds.innerHTML = '';
      ids.forEach(function (id) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'template_ids[]';
        input.value = id;
        bulkExportIds.appendChild(input);
      });
      bulkExportCount.textContent = String(ids.length);
    });
  }
  refreshBulkSelection();

  var importModes = document.querySelectorAll('.csv-template-import-mode');
  var targetWrap = document.getElementById('csvTemplateImportTargetWrap');
  var targetSelect = document.getElementById('csvTemplateImportTarget');
  function refreshImportTarget() {
    if (!targetWrap || !targetSelect) {
      return;
    }
    var updateMode = document.getElementById('csvTemplateImportUpdate').checked;
    targetWrap.classList.toggle('d-none', !updateMode);
    targetSelect.required = updateMode;
    if (!updateMode) {
      targetSelect.value = '';
    }
  }
  for (var i = 0; i < importModes.length; i++) {
    importModes[i].addEventListener('change', refreshImportTarget);
  }
  refreshImportTarget();
}());
</script>
