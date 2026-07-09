<div class="container-fluid py-4">
  <ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link{if $computerTab eq 'products'} active{/if}" href="{$baseUrl}?controller=computers&action=products">Produkty</a></li>
    <li class="nav-item"><a class="nav-link{if $computerTab eq 'components'} active{/if}" href="{$baseUrl}?controller=computers&action=components">Komponenty</a></li>
    <li class="nav-item"><a class="nav-link{if $computerTab eq 'csvtemplates'} active{/if}" href="{$baseUrl}?controller=computers&action=csvtemplates">Szablony CSV</a></li>
    <li class="nav-item"><a class="nav-link{if $computerTab eq 'titletemplates'} active{/if}" href="{$baseUrl}?controller=computers&action=titletemplates">Szablony tytułów</a></li>
  </ul>

  {if $errors}<div class="alert alert-danger"><ul class="mb-0">{foreach from=$errors item=error}<li>{$error|escape:'html'}</li>{/foreach}</ul></div>{/if}

  <form method="post" action="{$formAction|escape:'html'}" id="computerTitleTemplateForm">
    <input type="hidden" name="id" value="{$titleTemplate.id|default:0}">
    <div class="card shadow-sm mb-4">
      <div class="card-header"><strong>Dane główne</strong></div>
      <div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label">Nazwa</label><input type="text" name="name" class="form-control" value="{$titleTemplate.name|default:''|escape:'html'}" required></div>
        <div class="col-md-6"><label class="form-label">Opis</label><input type="text" name="description" class="form-control" value="{$titleTemplate.description|default:''|escape:'html'}"></div>
        <div class="col-12">
          <label class="form-label">Wzór tytułu</label>
          <textarea name="template_body" id="templateBody" class="form-control" rows="5" required>{$titleTemplate.template_body|default:''|escape:'html'}</textarea>
          <div class="form-text">Przykład: <code>{{ldelim}}field:product.components.CPU{rdelim} {{ldelim}}field:product.components.GPU{rdelim} {{ldelim}}field:product.components.RAM{rdelim}</code></div>
          <div class="form-text">Działa też formatowanie i podmiany, np. <code>{{ldelim}}field:product.components.OBUDOWA+Gaming-GAMING=upper{rdelim}</code></div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm mb-4">
      <div class="card-header"><strong>Wstaw token</strong></div>
      <div class="card-body">
        <div class="row g-2 align-items-end">
          <div class="col-md-7">
            <label class="form-label">Szukaj tokenu</label>
            <input type="text" id="tokenSearch" class="form-control mb-2" placeholder="Wpisz fragment nazwy albo tokenu">
            <label class="form-label">Dostępne tokeny</label>
            <select id="tokenSelect" class="form-select">
              <option value="">Wybierz token do wstawienia</option>
              {foreach $availableTitleTokens as $token => $label}
                <option value="{$token|escape:'html'}">{$label|escape:'html'} - {$token|escape:'html'}</option>
              {/foreach}
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Formatowanie</label>
            <select id="tokenFormat" class="form-select">
              <option value="">Bez formatowania</option>
              <option value="ucfirst">ucfirst</option>
              <option value="upper">upper</option>
              <option value="lower">lower</option>
              <option value="trim">trim</option>
            </select>
          </div>
          <div class="col-md-2 d-grid">
            <button type="button" id="insertTokenBtn" class="btn btn-outline-primary">Wstaw</button>
          </div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Przykład na istniejącym produkcie</strong>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshTitlePreview">Odśwież podgląd</button>
      </div>
      <div class="card-body">
        <div class="row g-3 align-items-end mb-3">
          <div class="col-lg-8 position-relative">
            <label class="form-label">Produkt do podglądu</label>
            <input type="search" class="form-control" id="titlePreviewProductSearch" autocomplete="off" placeholder="Wpisz ID, nazwę, SKU albo EAN...">
            <input type="hidden" id="titlePreviewProductId" value="">
            <div id="titlePreviewProductResults" class="list-group position-absolute start-0 end-0 shadow" style="z-index:1050;display:none;max-height:320px;overflow:auto"></div>
          </div>
          <div class="col-lg-4">
            <div class="small text-muted" id="selectedTitlePreviewProduct">Nie wybrano produktu.</div>
          </div>
        </div>

        <div class="border rounded bg-light p-3">
          <div class="d-flex justify-content-between align-items-center gap-3 mb-2">
            <div class="small fw-semibold text-uppercase text-muted">Wygenerowany tytuł</div>
            <div id="titlePreviewLength" class="badge text-bg-secondary">0 / 75</div>
          </div>
          <div id="titlePreviewOutput" class="fw-semibold">Wyszukaj i wybierz produkt powyżej.</div>
          <div id="titlePreviewWarning" class="small text-danger mt-2 d-none">Tytuł przekracza 75 znaków.</div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-between">
      <a href="{$baseUrl}?controller=computers&action=titletemplates" class="btn btn-outline-secondary">Wróć</a>
      <button type="submit" class="btn btn-primary">Zapisz szablon tytułu</button>
    </div>
  </form>
</div>

<script>
(function () {
  var textarea = document.getElementById('templateBody');
  var select = document.getElementById('tokenSelect');
  var tokenSearch = document.getElementById('tokenSearch');
  var insertButton = document.getElementById('insertTokenBtn');
  var formatSelect = document.getElementById('tokenFormat');
  var previewProductSearch = document.getElementById('titlePreviewProductSearch');
  var previewProductId = document.getElementById('titlePreviewProductId');
  var previewResults = document.getElementById('titlePreviewProductResults');
  var previewSelected = document.getElementById('selectedTitlePreviewProduct');
  var previewOutput = document.getElementById('titlePreviewOutput');
  var previewLength = document.getElementById('titlePreviewLength');
  var previewWarning = document.getElementById('titlePreviewWarning');
  var refreshPreviewButton = document.getElementById('refreshTitlePreview');
  var searchUrl = '{$baseUrl|escape:"javascript"}?controller=computers&action=searchcsvpreviewproducts';
  var previewUrl = '{$baseUrl|escape:"javascript"}?controller=computers&action=previewtitletemplate';
  var previewTimer = null;

  function insertAtCursor(token) {
    if (!textarea || !token) return;
    var start = textarea.selectionStart || 0;
    var end = textarea.selectionEnd || 0;
    var current = textarea.value || '';
    textarea.value = current.slice(0, start) + token + current.slice(end);
    textarea.focus();
    var caret = start + token.length;
    textarea.setSelectionRange(caret, caret);
  }

  function withSelectedFormat(token) {
    var format = formatSelect ? String(formatSelect.value || '') : '';
    if (format && /\}\}$/.test(token)) return token.replace(/\}\}$/, '=' + format + '}}');
    return token;
  }

  if (insertButton && select) {
    insertButton.addEventListener('click', function () {
      if (select.value) insertAtCursor(withSelectedFormat(select.value));
    });
  }

  if (tokenSearch && select) {
    tokenSearch.addEventListener('input', function () {
      var query = String(tokenSearch.value || '').toLowerCase().trim();
      var options = select.querySelectorAll('option');
      for (var i = 0; i < options.length; i++) {
        if (i === 0) {
          options[i].hidden = false;
          continue;
        }
        var haystack = String(options[i].textContent || '').toLowerCase();
        options[i].hidden = query !== '' && haystack.indexOf(query) === -1;
      }
    });
  }

  function setPreviewLength(length, limit) {
    if (!previewLength) return;
    previewLength.textContent = String(length) + ' / ' + String(limit);
    previewLength.className = length > limit ? 'badge text-bg-danger' : 'badge text-bg-success';
    if (previewWarning) {
      previewWarning.classList.toggle('d-none', !(length > limit));
    }
  }

  function renderPreviewState(title, length, limit) {
    if (previewOutput) previewOutput.textContent = title || 'Brak wyniku podglądu.';
    setPreviewLength(length || 0, limit || 75);
  }

  function runTitlePreview() {
    if (!previewProductId || !previewProductId.value || !textarea) return;

    var formData = new FormData();
    formData.append('product_id', previewProductId.value);
    formData.append('template_body', textarea.value || '');

    fetch(previewUrl, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
      .then(function (response) { return response.json(); })
      .then(function (payload) {
        if (payload && !payload.error) {
          renderPreviewState(String(payload.title || ''), Number(payload.length || 0), Number(payload.limit || 75));
        }
      })
      .catch(function () {});
  }

  function selectPreviewProduct(product) {
    if (!product) return;
    if (previewProductId) previewProductId.value = String(product.id || '');
    if (previewProductSearch) previewProductSearch.value = String(product.name || '');
    if (previewSelected) {
      previewSelected.textContent = 'ID ' + String(product.id || '') + ' • ' + String(product.name || '') + (product.sku ? ' • SKU: ' + String(product.sku) : '');
    }
    if (previewResults) previewResults.style.display = 'none';
    runTitlePreview();
  }

  if (previewProductSearch && previewResults) {
    previewProductSearch.addEventListener('input', function () {
      var query = String(previewProductSearch.value || '').trim();
      if (previewTimer) window.clearTimeout(previewTimer);
      if (query.length < 2) {
        previewResults.style.display = 'none';
        return;
      }

      previewTimer = window.setTimeout(function () {
        fetch(searchUrl + '&q=' + encodeURIComponent(query), { credentials: 'same-origin' })
          .then(function (response) { return response.json(); })
          .then(function (payload) {
            var products = payload && payload.products ? payload.products : [];
            previewResults.innerHTML = '';
            if (!products.length) {
              previewResults.style.display = 'none';
              return;
            }

            for (var i = 0; i < products.length; i++) {
              (function (product) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'list-group-item list-group-item-action';
                button.innerHTML = '<div class="fw-semibold">' + String(product.name || '') + '</div>'
                  + '<div class="small text-muted">ID ' + String(product.id || '')
                  + (product.sku ? ' • SKU: ' + String(product.sku) : '')
                  + (product.EAN ? ' • EAN: ' + String(product.EAN) : '') + '</div>';
                button.addEventListener('click', function () {
                  selectPreviewProduct(product);
                });
                previewResults.appendChild(button);
              })(products[i]);
            }

            previewResults.style.display = 'block';
          })
          .catch(function () {
            previewResults.style.display = 'none';
          });
      }, 250);
    });
  }

  if (textarea) {
    textarea.addEventListener('input', function () {
      if (previewProductId && previewProductId.value) runTitlePreview();
    });
  }

  if (refreshPreviewButton) {
    refreshPreviewButton.addEventListener('click', function () {
      runTitlePreview();
    });
  }
})();
</script>
