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

      {assign var="stockTowar" value=[]}
      {assign var="stockKoszt" value=[]}
      {assign var="stockTowarCount" value=0}
      {assign var="stockKosztCount" value=0}
      {assign var="stockTowarQuantity" value=0}
      {assign var="stockKosztQuantity" value=0}
      {assign var="stockTowarNet" value=0}
      {assign var="stockKosztNet" value=0}
      {assign var="stockTowarGross" value=0}
      {assign var="stockKosztGross" value=0}
      {foreach $stockSummary as $item}
        {if $item.item_kind|default:''|lower eq 'towar'}
          {$stockTowar[] = $item}
          {assign var="stockTowarCount" value=$stockTowarCount + 1}
          {assign var="stockTowarQuantity" value=$stockTowarQuantity + $item.quantity}
          {assign var="stockTowarNet" value=$stockTowarNet + $item.total_net}
          {assign var="stockTowarGross" value=$stockTowarGross + $item.total_gross}
        {elseif $item.item_kind|default:''|lower eq 'koszt'}
          {$stockKoszt[] = $item}
          {assign var="stockKosztCount" value=$stockKosztCount + 1}
          {assign var="stockKosztQuantity" value=$stockKosztQuantity + $item.quantity}
          {assign var="stockKosztNet" value=$stockKosztNet + $item.total_net}
          {assign var="stockKosztGross" value=$stockKosztGross + $item.total_gross}
        {/if}
      {/foreach}

      <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
          <div class="small-box text-bg-primary" id="stockSummaryBoxCount" data-towar-value="{$stockTowarCount}" data-koszt-value="{$stockKosztCount}">
            <div class="inner"><h3 id="stockSummaryValueCount">{$stockTowarCount}</h3><p id="stockSummaryLabelCount">Pozycje typu towar</p></div>
            <div class="small-box-icon"><i class="bi bi-box-seam"></i></div>
          </div>
        </div>
        <div class="col-xl-3 col-sm-6">
          <div class="small-box text-bg-success" id="stockSummaryBoxQuantity" data-towar-value="{$stockTowarQuantity|string_format:'%.3f'}" data-koszt-value="{$stockKosztQuantity|string_format:'%.3f'}">
            <div class="inner"><h3 id="stockSummaryValueQuantity">{$stockTowarQuantity|string_format:'%.3f'}</h3><p id="stockSummaryLabelQuantity">Suma sztuk towarow</p></div>
            <div class="small-box-icon"><i class="bi bi-123"></i></div>
          </div>
        </div>
        <div class="col-xl-3 col-sm-6">
          <div class="small-box text-bg-dark" id="stockSummaryBoxNet" data-towar-value="{$stockTowarNet|string_format:'%.2f'}" data-koszt-value="{$stockKosztNet|string_format:'%.2f'}">
            <div class="inner"><h3 id="stockSummaryValueNet">{$stockTowarNet|string_format:'%.2f'}</h3><p id="stockSummaryLabelNet">Suma towarow netto</p></div>
            <div class="small-box-icon"><i class="bi bi-cash-stack"></i></div>
          </div>
        </div>
        <div class="col-xl-3 col-sm-6">
          <div class="small-box text-bg-warning" id="stockSummaryBoxGross" data-towar-value="{$stockTowarGross|string_format:'%.2f'}" data-koszt-value="{$stockKosztGross|string_format:'%.2f'}">
            <div class="inner"><h3 id="stockSummaryValueGross">{$stockTowarGross|string_format:'%.2f'}</h3><p id="stockSummaryLabelGross">Suma towarow brutto</p></div>
            <div class="small-box-icon"><i class="bi bi-receipt"></i></div>
          </div>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-xl-4">
          <div class="card mb-4">
            <div class="card-header"><h3 class="card-title mb-0">Akcje</h3></div>
            <div class="card-body d-grid gap-2">
              <a href="{$baseUrl}?controller=accountingwarehouse&action=create" class="btn btn-primary">Dodaj fakture lub XML</a>
              <a href="{$baseUrl}?controller=accountingwarehouse&action=documents" class="btn btn-outline-dark">Lista dokumentow</a>
              <a href="{$baseUrl}?controller=accountingwarehouse&action=issuecreate" class="btn btn-outline-primary">Wyjscie z magazynu</a>
              <a href="{$baseUrl}?controller=accountingwarehouse&action=issues" class="btn btn-outline-dark">Raport wyjsc</a>
              <a href="{$baseUrl}?controller=accountingwarehouse&action=macros" class="btn btn-outline-secondary">Pozycje ksiegowe i aliasy</a>
            </div>
          </div>
        </div>

        <div class="col-xl-8">
          <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h3 class="card-title mb-0">Stan magazynu ksiegowego</h3>
              <span class="badge text-bg-secondary">Dokumenty: {$overview.documents_count|default:0}</span>
            </div>
            <div class="card-body border-bottom">
              <ul class="nav nav-pills gap-2" id="accountingWarehouseStockTabs" role="tablist">
                <li class="nav-item" role="presentation">
                  <button class="btn btn-sm btn-primary active" id="stock-towar-tab" data-bs-toggle="pill" data-bs-target="#stock-towar-pane" type="button" role="tab" aria-controls="stock-towar-pane" aria-selected="true">Towar ({$stockTowar|@count})</button>
                </li>
                <li class="nav-item" role="presentation">
                  <button class="btn btn-sm btn-outline-secondary" id="stock-koszt-tab" data-bs-toggle="pill" data-bs-target="#stock-koszt-pane" type="button" role="tab" aria-controls="stock-koszt-pane" aria-selected="false">Koszt ({$stockKoszt|@count})</button>
                </li>
              </ul>
            </div>
            <div class="card-body p-0">
              <div class="tab-content">
                <div class="tab-pane fade show active" id="stock-towar-pane" role="tabpanel" aria-labelledby="stock-towar-tab" tabindex="0">
                  <div class="table-responsive">
                    <table class="table table-sm table-hover table-bordered align-middle mb-0 js-stock-table">
                      <thead class="table-light">
                        <tr>
                          <th>Pozycja</th>
                          <th class="text-end"><button type="button" class="btn btn-link btn-sm text-decoration-none p-0 js-stock-sort" data-sort-key="quantity">Sztuki</button></th>
                          <th class="text-end">Netto</th>
                          <th class="text-end"><button type="button" class="btn btn-link btn-sm text-decoration-none p-0 js-stock-sort" data-sort-key="gross">Brutto</button></th>
                        </tr>
                      </thead>
                      <tbody>
                        {foreach $stockTowar as $item}
                          <tr data-quantity="{$item.quantity|escape}" data-gross="{$item.total_gross|escape}">
                            <td><a href="{$baseUrl}?controller=accountingwarehouse&action=item&id={$item.id}">{$item.name|escape}</a></td>
                            <td class="text-end">{$item.quantity|string_format:'%.3f'} {$item.unit|escape}</td>
                            <td class="text-end">{$item.total_net|string_format:'%.2f'} PLN</td>
                            <td class="text-end">{$item.total_gross|string_format:'%.2f'} PLN</td>
                          </tr>
                        {foreachelse}
                          <tr><td colspan="4" class="text-center py-3">Brak pozycji typu towar w magazynie ksiegowym.</td></tr>
                        {/foreach}
                      </tbody>
                    </table>
                  </div>
                </div>
                <div class="tab-pane fade" id="stock-koszt-pane" role="tabpanel" aria-labelledby="stock-koszt-tab" tabindex="0">
                  <div class="table-responsive">
                    <table class="table table-sm table-hover table-bordered align-middle mb-0 js-stock-table">
                      <thead class="table-light">
                        <tr>
                          <th>Pozycja</th>
                          <th class="text-end"><button type="button" class="btn btn-link btn-sm text-decoration-none p-0 js-stock-sort" data-sort-key="quantity">Sztuki</button></th>
                          <th class="text-end">Netto</th>
                          <th class="text-end"><button type="button" class="btn btn-link btn-sm text-decoration-none p-0 js-stock-sort" data-sort-key="gross">Brutto</button></th>
                        </tr>
                      </thead>
                      <tbody>
                        {foreach $stockKoszt as $item}
                          <tr data-quantity="{$item.quantity|escape}" data-gross="{$item.total_gross|escape}">
                            <td><a href="{$baseUrl}?controller=accountingwarehouse&action=item&id={$item.id}">{$item.name|escape}</a></td>
                            <td class="text-end">{$item.quantity|string_format:'%.3f'} {$item.unit|escape}</td>
                            <td class="text-end">{$item.total_net|string_format:'%.2f'} PLN</td>
                            <td class="text-end">{$item.total_gross|string_format:'%.2f'} PLN</td>
                          </tr>
                        {foreachelse}
                          <tr><td colspan="4" class="text-center py-3">Brak pozycji typu koszt w magazynie ksiegowym.</td></tr>
                        {/foreach}
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="card mb-4">
            <div class="card-header"><h3 class="card-title mb-0">Ostatnie ruchy</h3></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Pozycja</th>
                      <th>Dokument</th>
                      <th class="text-end">Ilosc</th>
                      <th class="text-end">Netto</th>
                      <th>Data</th>
                    </tr>
                  </thead>
                  <tbody>
                    {foreach $recentMovements as $movement}
                      <tr>
                        <td>
                          <div class="fw-semibold">{$movement.item_name|escape}</div>
                          <div class="small text-secondary">{$movement.original_name|escape}</div>
                        </td>
                        <td><a href="{$baseUrl}?controller=accountingwarehouse&action=show&id={$movement.document_id}">{$movement.document_number|default:'bez numeru'|escape}</a></td>
                        <td class="text-end">{$movement.quantity|string_format:'%.3f'} {$movement.unit|escape}</td>
                        <td class="text-end">{$movement.line_net|string_format:'%.2f'} PLN</td>
                        <td>{$movement.sale_date|default:$movement.issue_date|default:$movement.created_at|escape}</td>
                      </tr>
                    {foreachelse}
                      <tr><td colspan="5" class="text-center py-3">Brak ruchow.</td></tr>
                    {/foreach}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<script>
  (function () {
    var countValue = document.getElementById('stockSummaryValueCount');
    var countLabel = document.getElementById('stockSummaryLabelCount');
    var quantityValue = document.getElementById('stockSummaryValueQuantity');
    var quantityLabel = document.getElementById('stockSummaryLabelQuantity');
    var netValue = document.getElementById('stockSummaryValueNet');
    var netLabel = document.getElementById('stockSummaryLabelNet');
    var grossValue = document.getElementById('stockSummaryValueGross');
    var grossLabel = document.getElementById('stockSummaryLabelGross');
    var towarTab = document.getElementById('stock-towar-tab');
    var kosztTab = document.getElementById('stock-koszt-tab');

    function switchSummary(kind) {
      if (!countValue || !quantityValue || !netValue || !grossValue) {
        return;
      }

      if (kind === 'koszt') {
        countValue.textContent = '{$stockKosztCount}';
        countLabel.textContent = 'Pozycje typu koszt';
        quantityValue.textContent = '{$stockKosztQuantity|string_format:"%.3f"}';
        quantityLabel.textContent = 'Suma sztuk kosztow';
        netValue.textContent = '{$stockKosztNet|string_format:"%.2f"}';
        netLabel.textContent = 'Suma kosztow netto';
        grossValue.textContent = '{$stockKosztGross|string_format:"%.2f"}';
        grossLabel.textContent = 'Suma kosztow brutto';
        return;
      }

      countValue.textContent = '{$stockTowarCount}';
      countLabel.textContent = 'Pozycje typu towar';
      quantityValue.textContent = '{$stockTowarQuantity|string_format:"%.3f"}';
      quantityLabel.textContent = 'Suma sztuk towarow';
      netValue.textContent = '{$stockTowarNet|string_format:"%.2f"}';
      netLabel.textContent = 'Suma towarow netto';
      grossValue.textContent = '{$stockTowarGross|string_format:"%.2f"}';
      grossLabel.textContent = 'Suma towarow brutto';
    }

    if (towarTab) {
      towarTab.addEventListener('click', function () {
        switchSummary('towar');
        towarTab.classList.remove('btn-outline-secondary');
        towarTab.classList.add('btn-primary');
        if (kosztTab) {
          kosztTab.classList.remove('btn-primary');
          kosztTab.classList.add('btn-outline-secondary');
        }
      });
    }

    if (kosztTab) {
      kosztTab.addEventListener('click', function () {
        switchSummary('koszt');
        kosztTab.classList.remove('btn-outline-secondary');
        kosztTab.classList.add('btn-primary');
        if (towarTab) {
          towarTab.classList.remove('btn-primary');
          towarTab.classList.add('btn-outline-secondary');
        }
      });
    }

    switchSummary('towar');

    var tables = document.querySelectorAll('.js-stock-table');
    if (!tables.length) {
      return;
    }

    function sortTable(table, key, direction) {
      var body = table.querySelector('tbody');
      if (!body) {
        return;
      }

      var rows = Array.prototype.slice.call(body.querySelectorAll('tr'));
      var sortableRows = rows.filter(function (row) {
        return row.hasAttribute('data-' + key);
      });

      sortableRows.sort(function (left, right) {
        var leftValue = parseFloat(left.getAttribute('data-' + key) || '0') || 0;
        var rightValue = parseFloat(right.getAttribute('data-' + key) || '0') || 0;
        if (leftValue === rightValue) {
          return 0;
        }

        return direction === 'asc' ? leftValue - rightValue : rightValue - leftValue;
      });

      sortableRows.forEach(function (row) {
        body.appendChild(row);
      });
    }

    tables.forEach(function (table) {
      var sortButtons = table.querySelectorAll('.js-stock-sort');
      var sortState = {
        quantity: 'desc',
        gross: 'desc'
      };

      sortButtons.forEach(function (button) {
        button.addEventListener('click', function () {
          var key = button.getAttribute('data-sort-key') || 'quantity';
          var direction = sortState[key] === 'desc' ? 'asc' : 'desc';
          sortState[key] = direction;
          sortTable(table, key, direction);
        });
      });
    });
  })();
</script>
