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
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=accountingwarehouse&action=index">Magazyn ksiegowy</a></li>
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

      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0">Wszystkie dokumenty</h3>
          <div class="d-flex gap-2">
            <a href="{$baseUrl}?controller=accountingwarehouse&action=create" class="btn btn-primary btn-sm">Dodaj dokument</a>
          </div>
        </div>
        <div class="card-body border-bottom">
          <form method="get" action="{$baseUrl}" class="row g-3 align-items-end">
            <input type="hidden" name="controller" value="accountingwarehouse">
            <input type="hidden" name="action" value="documents">
            <div class="col-md-3">
              <label class="form-label">Numer dokumentu</label>
              <input type="text" class="form-control" name="document_number" value="{$filters.document_number|default:''|escape}" placeholder="Szukaj po numerze">
            </div>
            <div class="col-md-3">
              <label class="form-label">Dostawca</label>
              <input type="text" class="form-control" name="supplier_name" value="{$filters.supplier_name|default:''|escape}" placeholder="Szukaj po nazwie dostawcy">
            </div>
            <div class="col-md-2">
              <label class="form-label">NIP</label>
              <input type="text" class="form-control" name="supplier_tax_id" value="{$filters.supplier_tax_id|default:''|escape}" placeholder="Szukaj po NIP">
            </div>
            <div class="col-md-2">
              <label class="form-label">Typ dokumentu</label>
              <select class="form-select" name="document_kind">
                <option value=""{if $filters.document_kind|default:'' eq ''} selected{/if}>Wszystkie</option>
                <option value="receipt"{if $filters.document_kind eq 'receipt'} selected{/if}>towar</option>
                <option value="koszt"{if $filters.document_kind eq 'koszt'} selected{/if}>koszt</option>
                <option value="adjustment"{if $filters.document_kind eq 'adjustment'} selected{/if}>korekta</option>
                <option value="issue"{if $filters.document_kind eq 'issue'} selected{/if}>wyjscie</option>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">Rodzaj (towar/koszt/korekta)</label>
              <select class="form-select" name="invoice_type">
                <option value=""{if $filters.invoice_type|default:'' eq ''} selected{/if}>Wszystkie</option>
                <option value="towar"{if $filters.invoice_type eq 'towar'} selected{/if}>towar</option>
                <option value="koszt"{if $filters.invoice_type eq 'koszt'} selected{/if}>koszt</option>
                <option value="korekta"{if $filters.invoice_type eq 'korekta'} selected{/if}>korekta</option>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">Zrodlo</label>
              <select class="form-select" name="source_type">
                <option value=""{if $filters.source_type|default:'' eq ''} selected{/if}>Wszystkie</option>
                <option value="manual"{if $filters.source_type eq 'manual'} selected{/if}>reczne</option>
                <option value="xml"{if $filters.source_type eq 'xml'} selected{/if}>xml</option>
                <option value="legacy_sql"{if $filters.source_type eq 'legacy_sql'} selected{/if}>stary sql</option>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">Waluta</label>
              <select class="form-select" name="currency">
                <option value=""{if $filters.currency|default:'' eq ''} selected{/if}>Wszystkie</option>
                {foreach $currencyOptions as $currencyOption}
                  <option value="{$currencyOption|escape}"{if $filters.currency eq $currencyOption} selected{/if}>{$currencyOption|escape}</option>
                {/foreach}
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">Data sprzedazy/wystawienia od</label>
              <input type="date" class="form-control" name="date_from" value="{$filters.date_from|default:''|escape}">
            </div>
            <div class="col-md-2">
              <label class="form-label">Data sprzedazy/wystawienia do</label>
              <input type="date" class="form-control" name="date_to" value="{$filters.date_to|default:''|escape}">
            </div>
            <div class="col-md-2">
              <label class="form-label">Data dodania od</label>
              <input type="date" class="form-control" name="added_from" value="{$filters.added_from|default:''|escape}">
            </div>
            <div class="col-md-2">
              <label class="form-label">Data dodania do</label>
              <input type="date" class="form-control" name="added_to" value="{$filters.added_to|default:''|escape}">
            </div>
            <div class="col-md-2">
              <label class="form-label">Brutto od</label>
              <input type="text" inputmode="decimal" class="form-control" name="amount_min" value="{$filters.amount_min|default:''|escape}" placeholder="0.00">
            </div>
            <div class="col-md-2">
              <label class="form-label">Brutto do</label>
              <input type="text" inputmode="decimal" class="form-control" name="amount_max" value="{$filters.amount_max|default:''|escape}" placeholder="0.00">
            </div>
            <div class="col-md-4">
              <label class="form-label">Uwagi</label>
              <input type="text" class="form-control" name="notes" value="{$filters.notes|default:''|escape}" placeholder="Szukaj w uwagach">
            </div>
            <div class="col-md-2">
              <label class="form-label">Na stronie</label>
              <select class="form-select" name="per_page">
                {foreach $allowedPerPage as $perPageOption}
                  <option value="{$perPageOption}"{if $perPage eq $perPageOption} selected{/if}>{$perPageOption}</option>
                {/foreach}
              </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
              <button type="submit" class="btn btn-primary">Szukaj</button>
              <a href="{$baseUrl}?controller=accountingwarehouse&action=documents" class="btn btn-outline-secondary">Wyczysc</a>
            </div>
          </form>
          <div class="mt-2">
            <a href="{$baseUrl}?controller=accountingwarehouse&action=documents&added_from={$smarty.now|date_format:'%Y-%m-%d'}&added_to={$smarty.now|date_format:'%Y-%m-%d'}" class="btn btn-sm btn-outline-info">Dodane dzisiaj</a>
          </div>
        </div>

        <form method="post" action="{$baseUrl}?controller=accountingwarehouse&action=bulkdelete" id="bulkDeleteForm" onsubmit="return confirm('Usunac zaznaczone dokumenty? Tej operacji nie mozna cofnac.');">
          <input type="hidden" name="return_query" value="{$filterQuery|escape}{if $filterQuery}&{/if}page={$page}&per_page={$perPage}">

          <div class="card-body border-bottom d-flex justify-content-between align-items-center py-2">
            <div class="small text-secondary">Zaznaczono: <span id="selectedCount">0</span></div>
            <button type="submit" class="btn btn-outline-danger btn-sm" id="bulkDeleteBtn" disabled>Usun zaznaczone</button>
          </div>

          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm table-hover table-bordered align-middle mb-0" id="documentsTable">
                <thead class="table-light">
                  <tr>
                    <th style="width: 2.5rem;"><input type="checkbox" id="selectAllCheckbox" title="Zaznacz wszystkie na stronie"></th>
                    <th>ID</th>
                    <th>Numer</th>
                    <th>Dostawca</th>
                    <th>NIP</th>
                    <th>Typ</th>
                    <th>Rodzaj</th>
                    <th>Sprzedaz</th>
                    <th>Wystawienie</th>
                    <th>Data dodania</th>
                    <th class="text-end">Netto</th>
                    <th class="text-end">Brutto</th>
                    <th class="text-end">Akcje</th>
                  </tr>
                </thead>
                <tbody>
                  {foreach $documents as $document}
                    <tr>
                      <td><input type="checkbox" class="row-checkbox" name="document_ids[]" value="{$document.id}"></td>
                      <td>{$document.id}</td>
                      <td>
                        <div class="fw-semibold">{$document.document_number|default:'bez numeru'|escape}</div>
                        <div class="small text-secondary">pozycje: {$document.lines_count}</div>
                      </td>
                      <td>{$document.supplier_name|default:'-'|escape}</td>
                      <td>{$document.supplier_tax_id|default:'-'|escape}</td>
                      <td>
                        <span class="badge text-bg-{if $document.document_kind eq 'issue'}primary{elseif $document.document_kind eq 'adjustment'}warning{elseif $document.document_kind eq 'koszt'}info{elseif $document.source_type eq 'xml'}info{elseif $document.source_type eq 'legacy_sql'}dark{else}secondary{/if}">
                          {if $document.document_kind eq 'issue'}wyjscie{elseif $document.document_kind eq 'adjustment'}korekta{elseif $document.document_kind eq 'koszt'}koszt{elseif $document.source_type eq 'xml'}xml{elseif $document.source_type eq 'legacy_sql'}stary sql{else}reczne{/if}
                        </span>
                      </td>
                      <td><span class="badge text-bg-{if $document.invoice_type eq 'koszt'}info{elseif $document.invoice_type eq 'korekta'}warning{else}secondary{/if}">{$document.invoice_type|default:'towar'|escape}</span></td>
                      <td>{$document.sale_date|default:'-'|escape}</td>
                      <td>{$document.issue_date|default:'-'|escape}</td>
                      <td>{$document.created_at|date_format:'%Y-%m-%d %H:%M'|default:'-'}</td>
                      <td class="text-end">{$document.total_net|string_format:'%.2f'} {$document.currency|escape}</td>
                      <td class="text-end">{$document.total_gross|string_format:'%.2f'} {$document.currency|escape}</td>
                      <td class="text-end">
                        <a href="{$baseUrl}?controller=accountingwarehouse&action=show&id={$document.id}" class="btn btn-sm btn-outline-primary">Podglad</a>
                        {if $document.document_kind neq 'issue'}
                          <a href="{$baseUrl}?controller=accountingwarehouse&action=edit&id={$document.id}" class="btn btn-sm btn-outline-secondary">Edytuj</a>
                        {/if}
                      </td>
                    </tr>
                  {foreachelse}
                    <tr><td colspan="13" class="text-center py-3">Brak dokumentow.</td></tr>
                  {/foreach}
                </tbody>
              </table>
            </div>
          </div>
        </form>

        <div class="card-body d-flex justify-content-between align-items-center">
          <div class="small text-secondary">
            Strona {$page} z {$totalPages} - lacznie {$totalDocuments} dokumentow.
          </div>
          <nav aria-label="Paginacja dokumentow">
            <ul class="pagination pagination-sm mb-0">
              <li class="page-item{if $page <= 1} disabled{/if}">
                <a class="page-link" href="{$baseUrl}?controller=accountingwarehouse&action=documents{if $filterQuery}&{$filterQuery}{/if}&per_page={$perPage}&page={$page - 1}">Poprzednia</a>
              </li>
              <li class="page-item disabled"><span class="page-link">{$page} / {$totalPages}</span></li>
              <li class="page-item{if $page >= $totalPages} disabled{/if}">
                <a class="page-link" href="{$baseUrl}?controller=accountingwarehouse&action=documents{if $filterQuery}&{$filterQuery}{/if}&per_page={$perPage}&page={$page + 1}">Nastepna</a>
              </li>
            </ul>
          </nav>
        </div>
      </div>
    </div>
  </div>
</main>

<script>
(function () {
  var table = document.getElementById('documentsTable');
  if (!table) {
    return;
  }

  var selectAll = document.getElementById('selectAllCheckbox');
  var bulkBtn = document.getElementById('bulkDeleteBtn');
  var selectedCountEl = document.getElementById('selectedCount');
  var lastCheckedIndex = null;

  function rowCheckboxes() {
    return Array.prototype.slice.call(table.querySelectorAll('.row-checkbox'));
  }

  function updateState() {
    var boxes = rowCheckboxes();
    var checkedCount = boxes.filter(function (box) { return box.checked; }).length;
    selectedCountEl.textContent = String(checkedCount);
    bulkBtn.disabled = checkedCount === 0;
    selectAll.checked = boxes.length > 0 && checkedCount === boxes.length;
    selectAll.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
  }

  table.addEventListener('click', function (event) {
    var target = event.target;
    if (!target.classList || !target.classList.contains('row-checkbox')) {
      return;
    }

    var boxes = rowCheckboxes();
    var currentIndex = boxes.indexOf(target);

    if (event.shiftKey && lastCheckedIndex !== null) {
      var start = Math.min(lastCheckedIndex, currentIndex);
      var end = Math.max(lastCheckedIndex, currentIndex);
      for (var i = start; i <= end; i++) {
        boxes[i].checked = target.checked;
      }
    }

    lastCheckedIndex = currentIndex;
    updateState();
  });

  selectAll.addEventListener('change', function () {
    var boxes = rowCheckboxes();
    boxes.forEach(function (box) {
      box.checked = selectAll.checked;
    });
    updateState();
  });

  updateState();
})();
</script>
