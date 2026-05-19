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

      {capture assign=itemOptions}<option value="">Wybierz pozycje ksiegowa</option>{foreach $stockItems as $stockItem}<option value="{$stockItem.name|escape}">{$stockItem.name|escape} ({$stockItem.quantity|string_format:'%.0f'} {$stockItem.unit|escape})</option>{/foreach}{/capture}

      <div class="card mb-4 border-primary">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0">Formularz wyjscia z magazynu</h3>
          <a href="{$baseUrl}?controller=accountingwarehouse&action=issues" class="btn btn-sm btn-outline-secondary">Raport miesieczny</a>
        </div>
        <div class="card-body">
          <form method="post" action="{$baseUrl}?controller=accountingwarehouse&action=storeissue" id="issueForm">
            <div class="row g-3 mb-3">
              <div class="col-md-4"><label class="form-label">Numer dokumentu wyjscia</label><input type="text" class="form-control" name="issue_document_number" value="{$formData.issue_header.document_number|escape}"></div>
              <div class="col-md-4"><label class="form-label">Data dokumentu</label><input type="date" class="form-control" name="issue_issue_date" value="{$formData.issue_header.issue_date|escape}"></div>
              <div class="col-md-4"><label class="form-label">Data wyjscia</label><input type="date" class="form-control" name="issue_sale_date" value="{$formData.issue_header.sale_date|escape}"></div>
              <div class="col-md-3">
                <label class="form-label">Waluta</label>
                <select class="form-select" name="issue_currency">
                  {foreach $currencyOptions as $currencyOption}
                    <option value="{$currencyOption|escape}"{if $formData.issue_header.currency eq $currencyOption} selected{/if}>{$currencyOption|escape}</option>
                  {/foreach}
                </select>
              </div>
              <div class="col-md-9"><label class="form-label">Uwagi</label><input type="text" class="form-control" name="issue_notes" value="{$formData.issue_header.notes|escape}"></div>
            </div>

            <div class="alert alert-light border small mb-3">
              Rozchod zapisuje, która sztuka wyszla z magazynu, z jakiej faktury zrodlowej pochodzi i z jaka data zostala wydana.
            </div>

            <div id="issueRows" class="d-grid gap-3">
              {foreach $formData.issue_lines as $line}
                <div class="border rounded p-3 aw-issue-row">
                  <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                      <label class="form-label">Pozycja ksiegowa</label>
                      <select class="form-select item-name-select issue-item-select" name="issue_canonical_name[]">
                        {$itemOptions nofilter}
                      </select>
                      <script>document.currentScript.parentElement.querySelector('select[name="issue_canonical_name[]"]').value = '{$line.canonical_name|escape:'javascript'}';</script>
                    </div>
                    <div class="col-md-2"><label class="form-label">Ilosc sztuk</label><input type="number" step="1" min="1" class="form-control issue-quantity-input" name="issue_quantity[]" value="{$line.quantity|string_format:'%.0f'}"></div>
                    <div class="col-md-2"><label class="form-label">Na magazynie</label><input type="text" class="form-control issue-stock-available" value="-" readonly></div>
                    <div class="col-md-2"><label class="form-label">Cena / szt.</label><input type="text" class="form-control issue-unit-price" value="-" readonly></div>
                    <div class="col-md-2"><label class="form-label">Wartosc pozycji</label><input type="text" class="form-control issue-selected-value" value="-" readonly></div>
                  </div>
                  <input type="hidden" name="issue_original_name[]" value="">
                  <input type="hidden" name="issue_unit[]" value="szt.">
                </div>
              {/foreach}
            </div>

            <div class="border-top pt-3 mt-3">
              <div class="row g-2 justify-content-end">
                <div class="col-md-2"><label class="form-label">Suma sztuk</label><input type="text" class="form-control" id="issueDocumentTotalQuantity" value="0" readonly></div>
                <div class="col-md-3"><label class="form-label">Wartosc calego dokumentu</label><input type="text" class="form-control" id="issueDocumentTotalGross" value="0.00 PLN brutto" readonly></div>
              </div>
            </div>

            <button type="button" class="btn btn-outline-secondary mt-3" id="addIssueRow">Dodaj pozycje</button>
            <button type="submit" class="btn btn-primary mt-3">Zapisz wyjscie z magazynu</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <template id="issueRowTemplate">
    <div class="border rounded p-3 aw-issue-row">
      <div class="row g-2 align-items-end">
        <div class="col-md-4"><label class="form-label">Pozycja ksiegowa</label><select class="form-select item-name-select issue-item-select js-issue-canonical-name">{$itemOptions nofilter}</select></div>
        <div class="col-md-2"><label class="form-label">Ilosc sztuk</label><input type="number" step="1" min="1" class="form-control issue-quantity-input js-issue-quantity" value="1"></div>
        <div class="col-md-2"><label class="form-label">Na magazynie</label><input type="text" class="form-control issue-stock-available" value="-" readonly></div>
        <div class="col-md-2"><label class="form-label">Cena / szt.</label><input type="text" class="form-control issue-unit-price" value="-" readonly></div>
        <div class="col-md-2"><label class="form-label">Wartosc pozycji</label><input type="text" class="form-control issue-selected-value" value="-" readonly></div>
      </div>
      <input type="hidden" name="issue_original_name[]" value="">
      <input type="hidden" name="issue_unit[]" value="szt.">
    </div>
  </template>

  <script>
    (function () {
      var stockItems = {$stockItems|json_encode nofilter};
      var stockMap = {};
      for (var stockIndex = 0; stockIndex < stockItems.length; stockIndex++) {
        var stockItem = stockItems[stockIndex] || {};
        var stockName = String(stockItem.name || '');
        if (stockName === '') {
          continue;
        }

        var quantity = parseFloat(stockItem.quantity || 0) || 0;
        var totalGross = parseFloat(stockItem.total_gross || 0) || 0;
        stockMap[stockName] = {
          quantity: quantity,
          totalGross: totalGross,
          unitGross: quantity > 0 ? (totalGross / quantity) : 0
        };
      }

      var wrap = document.getElementById('issueRows');
      var button = document.getElementById('addIssueRow');
      var template = document.getElementById('issueRowTemplate');
      var totalQuantityNode = document.getElementById('issueDocumentTotalQuantity');
      var totalGrossNode = document.getElementById('issueDocumentTotalGross');
      if (!wrap || !button || !template) {
        return;
      }

      function formatMoney(value) {
        return (Math.round(value * 100) / 100).toFixed(2);
      }

      function selectedIssueItems() {
        var rows = wrap.querySelectorAll('.aw-issue-row');
        var selected = {};

        for (var index = 0; index < rows.length; index++) {
          var select = rows[index].querySelector('.issue-item-select');
          var value = select ? String(select.value || '') : '';
          if (value !== '') {
            selected[value] = (selected[value] || 0) + 1;
          }
        }

        return selected;
      }

      function syncIssueSelectOptions() {
        var rows = wrap.querySelectorAll('.aw-issue-row');
        var selected = selectedIssueItems();

        for (var rowIndex = 0; rowIndex < rows.length; rowIndex++) {
          var row = rows[rowIndex];
          var select = row.querySelector('.issue-item-select');
          if (!select) {
            continue;
          }

          var currentValue = String(select.value || '');
          for (var optionIndex = 0; optionIndex < select.options.length; optionIndex++) {
            var option = select.options[optionIndex];
            var optionValue = String(option.value || '');
            if (optionValue === '') {
              option.disabled = false;
              continue;
            }

            option.disabled = !!selected[optionValue] && optionValue !== currentValue;
          }
        }
      }

      function updateIssueTotals() {
        if (!totalQuantityNode || !totalGrossNode) {
          return;
        }

        var rows = wrap.querySelectorAll('.aw-issue-row');
        var totalQuantity = 0;
        var totalGross = 0;

        for (var index = 0; index < rows.length; index++) {
          var row = rows[index];
          var select = row.querySelector('.issue-item-select');
          var quantityInput = row.querySelector('.issue-quantity-input');
          if (!select || !quantityInput) {
            continue;
          }

          var item = stockMap[String(select.value || '')] || null;
          var quantity = parseInt(quantityInput.value, 10) || 0;
          if (!item || quantity <= 0) {
            continue;
          }

          totalQuantity += quantity;
          totalGross += quantity * item.unitGross;
        }

        totalQuantityNode.value = String(totalQuantity);
        totalGrossNode.value = formatMoney(totalGross) + ' PLN brutto';
      }

      function updateIssueRow(row) {
        if (!row) {
          return;
        }

        var select = row.querySelector('.issue-item-select');
        var quantityInput = row.querySelector('.issue-quantity-input');
        var stockAvailableInput = row.querySelector('.issue-stock-available');
        var unitPriceInput = row.querySelector('.issue-unit-price');
        var selectedValueInput = row.querySelector('.issue-selected-value');
        if (!select || !quantityInput || !stockAvailableInput || !unitPriceInput || !selectedValueInput) {
          return;
        }

        syncIssueSelectOptions();

        var item = stockMap[String(select.value || '')] || null;
        if (!item) {
          quantityInput.value = '';
          quantityInput.removeAttribute('max');
          stockAvailableInput.value = '-';
          unitPriceInput.value = '-';
          selectedValueInput.value = '-';
          updateIssueTotals();
          return;
        }

        var maxQuantity = Math.max(0, Math.floor(item.quantity));
        quantityInput.max = String(maxQuantity);
        if (!quantityInput.value || parseInt(quantityInput.value, 10) < 1) {
          quantityInput.value = maxQuantity > 0 ? '1' : '0';
        }

        if (parseInt(quantityInput.value, 10) > maxQuantity) {
          quantityInput.value = String(maxQuantity);
        }

        var selectedQuantity = parseInt(quantityInput.value, 10) || 0;
        var selectedGross = selectedQuantity * item.unitGross;
        stockAvailableInput.value = maxQuantity + ' szt.';
        unitPriceInput.value = formatMoney(item.unitGross) + ' PLN';
        selectedValueInput.value = formatMoney(selectedGross) + ' PLN';
        updateIssueTotals();
      }

      button.addEventListener('click', function () {
        var clone = template.content.firstElementChild.cloneNode(true);
        clone.querySelector('.js-issue-canonical-name').name = 'issue_canonical_name[]';
        clone.querySelector('.js-issue-quantity').name = 'issue_quantity[]';
        clone.querySelector('.js-issue-canonical-name').value = '';
        wrap.appendChild(clone);
        updateIssueRow(clone);
        syncIssueSelectOptions();
      });

      wrap.addEventListener('change', function (event) {
        if (event.target && event.target.classList.contains('issue-item-select')) {
          updateIssueRow(event.target.closest('.aw-issue-row'));
          syncIssueSelectOptions();
        }
      });

      wrap.addEventListener('input', function (event) {
        if (event.target && event.target.classList.contains('issue-quantity-input')) {
          updateIssueRow(event.target.closest('.aw-issue-row'));
        }
      });

      var rows = wrap.querySelectorAll('.aw-issue-row');
      for (var rowIndex = 0; rowIndex < rows.length; rowIndex++) {
        updateIssueRow(rows[rowIndex]);
      }
      syncIssueSelectOptions();
    })();
  </script>
</main>
