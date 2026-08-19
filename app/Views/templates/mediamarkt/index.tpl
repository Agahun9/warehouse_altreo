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

      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
          <h3 class="card-title mb-0">Synchronizacja i kolejka</h3>
          <div class="d-flex flex-wrap gap-2">
            <a href="{$baseUrl}?controller=administration&action=automation" class="btn btn-sm btn-outline-secondary">Zarzadzaj kontami w administracji</a>
            <a href="{$baseUrl}?controller=mediamarkt&action=sync" class="btn btn-sm btn-outline-primary">Synchronizuj aktywne konto</a>
            <a href="{$baseUrl}?controller=mediamarkt&action=processqueue" class="btn btn-sm btn-outline-success">Uruchom worker kolejki</a>
          </div>
        </div>
        <div class="card-body">
          <div class="alert alert-light border small text-secondary">
            MediaMarkt dziala przez Mirakl Seller API. Konfiguracja kont jest dostepna tylko w <a href="{$baseUrl}?controller=administration&action=automation" class="alert-link">Administracji</a>. W tym widoku worker korzysta z dedykowanych importow Mirakl dla ceny i stanow oraz z aktualizacji ofert dla aktywacji, wznawiania i zmian opisu.
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-3">
              <div class="border rounded p-3 h-100">
                <div class="small text-secondary">Oczekuje</div>
                <div class="fs-4 fw-semibold text-warning"><a class="text-warning text-decoration-none" href="{$baseUrl}?controller=mediamarkt&action=index&queue_status=pending">{$queueStats.pending}</a></div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="border rounded p-3 h-100">
                <div class="small text-secondary">Ponow</div>
                <div class="fs-4 fw-semibold text-warning"><a class="text-warning text-decoration-none" href="{$baseUrl}?controller=mediamarkt&action=index&queue_status=retry">{$queueStats.retry}</a></div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="border rounded p-3 h-100">
                <div class="small text-secondary">Gotowe</div>
                <div class="fs-4 fw-semibold text-success"><a class="text-success text-decoration-none" href="{$baseUrl}?controller=mediamarkt&action=index&queue_status=done">{$queueStats.done}</a></div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="border rounded p-3 h-100">
                <div class="small text-secondary">Blad</div>
                <div class="fs-4 fw-semibold text-danger"><a class="text-danger text-decoration-none" href="{$baseUrl}?controller=mediamarkt&action=index&queue_status=error">{$queueStats.error}</a></div>
              </div>
            </div>
          </div>

          <div class="d-flex gap-2">
            <form method="post" action="{$baseUrl}?controller=mediamarkt&action=clearqueue">
              <input type="hidden" name="mode" value="statuses">
              <button type="submit" class="btn btn-sm btn-outline-secondary">Wyczysc statusy zakonczone</button>
            </form>
            <form method="post" action="{$baseUrl}?controller=mediamarkt&action=clearqueue">
              <input type="hidden" name="mode" value="all">
              <button type="submit" class="btn btn-sm btn-outline-danger">Wyczysc cala kolejke</button>
            </form>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">
          <h3 class="card-title mb-0">Filtry ofert</h3>
        </div>
        <div class="card-body">
          <form method="get" action="{$baseUrl}" class="row g-3">
            <input type="hidden" name="controller" value="mediamarkt">
            <input type="hidden" name="action" value="index">
            <input type="hidden" name="sort_by" value="{$sortBy|escape}">
            <input type="hidden" name="sort_dir" value="{$sortDir|escape}">
            <div class="col-md-2">
              <label class="form-label">Konto</label>
              <select name="account_id" class="form-select">
                <option value="">Wszystkie</option>
                {foreach $accounts as $account}
                  <option value="{$account.id}"{if $filters.account_id|default:'' == $account.id} selected{/if}>{$account.name|escape}</option>
                {/foreach}
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">Fraza</label>
              <input type="text" name="q" class="form-control" value="{$filters.q|default:''|escape}" placeholder="offer id, tytul, opis">
            </div>
            <div class="col-md-2">
              <label class="form-label">SKU / product_id</label>
              <input type="text" name="sku" class="form-control" value="{$filters.sku|default:''|escape}">
            </div>
            <div class="col-md-2">
              <label class="form-label">State code</label>
              <input type="text" name="state" class="form-control" value="{$filters.state|default:''|escape}" placeholder="np. OPEN">
            </div>
            <div class="col-md-2">
              <label class="form-label">Aktywna</label>
              <select name="active" class="form-select">
                <option value="">Wszystkie</option>
                <option value="1"{if $filters.active|default:'' eq '1'} selected{/if}>Tak</option>
                <option value="0"{if $filters.active|default:'' eq '0'} selected{/if}>Nie</option>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">Kolejka</label>
              <select name="queue_status" class="form-select">
                <option value="">Wszystkie</option>
                <option value="pending"{if $filters.queue_status|default:'' eq 'pending'} selected{/if}>pending</option>
                <option value="retry"{if $filters.queue_status|default:'' eq 'retry'} selected{/if}>retry</option>
                <option value="processing"{if $filters.queue_status|default:'' eq 'processing'} selected{/if}>processing</option>
                <option value="done"{if $filters.queue_status|default:'' eq 'done'} selected{/if}>done</option>
                <option value="error"{if $filters.queue_status|default:'' eq 'error'} selected{/if}>error</option>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">Powiazanie</label>
              <select name="linked" class="form-select">
                <option value="">Wszystkie</option>
                <option value="1"{if $filters.linked|default:'' eq '1'} selected{/if}>Powiazane</option>
                <option value="0"{if $filters.linked|default:'' eq '0'} selected{/if}>Bez magazynu</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Blad kolejki</label>
              <input type="text" name="error_query" class="form-control" value="{$filters.error_query|default:''|escape}" placeholder="fragment komunikatu">
            </div>
            <div class="col-md-3">
              <label class="form-label">Stan magazyn od / do</label>
              <div class="row g-2">
                <div class="col-6">
                  <input type="number" min="0" step="1" name="warehouse_quantity_from" class="form-control" value="{$filters.warehouse_quantity_from|default:''|escape}" placeholder="od">
                </div>
                <div class="col-6">
                  <input type="number" min="0" step="1" name="warehouse_quantity_to" class="form-control" value="{$filters.warehouse_quantity_to|default:''|escape}" placeholder="do">
                </div>
              </div>
            </div>
            <div class="col-md-2">
              <label class="form-label">Na strone</label>
              <select name="per_page" class="form-select">
                <option value="50"{if $perPage eq 50} selected{/if}>50</option>
                <option value="100"{if $perPage eq 100} selected{/if}>100</option>
                <option value="200"{if $perPage eq 200} selected{/if}>200</option>
                <option value="5000"{if $perPage eq 5000} selected{/if}>5000</option>
              </select>
            </div>
            <div class="col-12 d-flex gap-2">
              <button type="submit" class="btn btn-primary">Filtruj</button>
              <a href="{$baseUrl}?controller=mediamarkt&action=index" class="btn btn-outline-secondary">Reset</a>
            </div>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <h3 class="card-title mb-0">Oferty MediaMarkt</h3>
            <div class="small text-secondary">Lacznie {$totalOffers} ofert | strona {$page} z {$totalPages}</div>
          </div>
          <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#mediamarktBulkModal">Akcje masowe</button>
        </div>

        <div class="card-body border-bottom py-2">
          <div class="d-flex flex-wrap gap-2 align-items-center">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="mediamarkt-select-page">Zaznacz strone</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="mediamarkt-clear-page">Odznacz strone</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="mediamarkt-invert-page">Odwroc zaznaczenie</button>
          </div>
        </div>

        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-striped table-hover table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width:40px;"></th>
                  <th><a href="{$sortUrls.id|escape}" class="text-decoration-none text-dark">ID</a></th>
                  <th><a href="{$sortUrls.account|escape}" class="text-decoration-none text-dark">Konto</a></th>
                  <th><a href="{$sortUrls.title|escape}" class="text-decoration-none text-dark">Tytul</a></th>
                  <th><a href="{$sortUrls.sku|escape}" class="text-decoration-none text-dark">SKU</a></th>
                  <th><a href="{$sortUrls.category|escape}" class="text-decoration-none text-dark">Kategoria</a></th>
                  <th><a href="{$sortUrls.state|escape}" class="text-decoration-none text-dark">Stan</a></th>
                  <th><a href="{$sortUrls.active|escape}" class="text-decoration-none text-dark">Aktywna</a></th>
                  <th><a href="{$sortUrls.warehouse_quantity|escape}" class="text-decoration-none text-dark">Magazyn</a></th>
                  <th><a href="{$sortUrls.quantity|escape}" class="text-decoration-none text-dark">MediaMarkt</a></th>
                  <th><a href="{$sortUrls.price|escape}" class="text-decoration-none text-dark">Cena</a></th>
                  <th><a href="{$sortUrls.queue_status|escape}" class="text-decoration-none text-dark">Kolejka</a></th>
                  <th><a href="{$sortUrls.synced|escape}" class="text-decoration-none text-dark">Sync</a></th>
                  <th class="text-end">Akcje</th>
                </tr>
              </thead>
              <tbody>
                {foreach $offers as $offer}
                  {assign var=queueMeta value=$offer.queue_meta|default:[]}
                  <tr>
                    <td class="text-center">
                      <input class="form-check-input mediamarkt-offer-checkbox" type="checkbox" value="{$offer.id|escape}">
                    </td>
                    <td>{$offer.offer_id}</td>
                    <td>{$offer.account_name|escape}</td>
                    <td>
                      <div class="fw-semibold">{$offer.product_title|default:'-'|escape}</div>
                      <div class="small text-secondary">product_id: {$offer.product_id|default:'-'|escape}</div>
                    </td>
                    <td>
                      <div>shop: <code>{$offer.shop_sku|default:'-'|escape}</code></div>
                      <div class="small text-secondary">product: <code>{$offer.product_sku|default:'-'|escape}</code></div>
                      {if $offer.warehouse_sku|default:'' ne ''}
                        <div class="small text-success">mag: <code>{$offer.warehouse_sku|escape}</code></div>
                      {/if}
                    </td>
                    <td>
                      <div>{$offer.category_label|default:'-'|escape}</div>
                      <div class="small text-secondary"><code>{$offer.category_code|default:'-'|escape}</code></div>
                    </td>
                    <td><span class="badge text-bg-secondary">{$offer.state_code|default:'-'|escape}</span></td>
                    <td>
                      {if $offer.active}
                        <span class="badge text-bg-success">Tak</span>
                      {else}
                        <span class="badge text-bg-danger">Nie</span>
                      {/if}
                    </td>
                    <td>{if $offer.warehouse_quantity|default:'' ne ''}{$offer.warehouse_quantity|escape}{else}-{/if}</td>
                    <td>{$offer.quantity|default:'-'|escape}</td>
                    <td>{if isset($offer.price) and $offer.price ne ''}{$offer.price|escape} {$offer.currency_iso_code|default:''|escape}{else}-{/if}</td>
                    <td>
                      {if $queueMeta.status|default:'' ne ''}
                        <div><span class="badge text-bg-light border">{$queueMeta.status|escape}</span></div>
                        {if $queueMeta.error_message|default:'' ne ''}
                          <div class="small text-danger mt-1">{$queueMeta.error_message|truncate:80|escape}</div>
                        {/if}
                      {else}
                        <span class="text-secondary">-</span>
                      {/if}
                    </td>
                    <td>{$offer.last_synced_at|default:'-'|escape}</td>
                    <td class="text-end">
                      <a href="{$baseUrl}?controller=mediamarkt&action=offer&id={$offer.id}" class="btn btn-sm btn-outline-primary">Szczegoly</a>
                    </td>
                  </tr>
                {foreachelse}
                  <tr>
                    <td colspan="14" class="text-center py-4 text-secondary">Brak ofert. Skonfiguruj konto w administracji i uruchom synchronizacje.</td>
                  </tr>
                {/foreach}
              </tbody>
            </table>
          </div>
        </div>

        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div class="btn-group">
            {assign var=prevPage value=$page-1}
            {assign var=nextPage value=$page+1}
            <a class="btn btn-sm btn-outline-secondary{if $page <= 1} disabled{/if}" href="{$baseUrl}?controller=mediamarkt&action=index&page=1&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&state={$filters.state|escape:'url'}&active={$filters.active|escape:'url'}&queue_status={$filters.queue_status|escape:'url'}&error_query={$filters.error_query|escape:'url'}&linked={$filters.linked|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}">Pierwsza</a>
            <a class="btn btn-sm btn-outline-secondary{if $page <= 1} disabled{/if}" href="{$baseUrl}?controller=mediamarkt&action=index&page={$prevPage}&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&state={$filters.state|escape:'url'}&active={$filters.active|escape:'url'}&queue_status={$filters.queue_status|escape:'url'}&error_query={$filters.error_query|escape:'url'}&linked={$filters.linked|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}">Poprzednia</a>
            {foreach $pageWindow as $pageItem}
              {if $pageItem.type eq 'page'}
                <a class="btn btn-sm {if $pageItem.is_current}btn-primary{else}btn-outline-secondary{/if}" href="{$baseUrl}?controller=mediamarkt&action=index&page={$pageItem.value}&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&state={$filters.state|escape:'url'}&active={$filters.active|escape:'url'}&queue_status={$filters.queue_status|escape:'url'}&error_query={$filters.error_query|escape:'url'}&linked={$filters.linked|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}">{$pageItem.value}</a>
              {else}
                <span class="btn btn-sm btn-outline-secondary disabled">...</span>
              {/if}
            {/foreach}
            <a class="btn btn-sm btn-outline-secondary{if $page >= $totalPages} disabled{/if}" href="{$baseUrl}?controller=mediamarkt&action=index&page={$nextPage}&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&state={$filters.state|escape:'url'}&active={$filters.active|escape:'url'}&queue_status={$filters.queue_status|escape:'url'}&error_query={$filters.error_query|escape:'url'}&linked={$filters.linked|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}">Nastepna</a>
            <a class="btn btn-sm btn-outline-secondary{if $page >= $totalPages} disabled{/if}" href="{$baseUrl}?controller=mediamarkt&action=index&page={$totalPages}&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&state={$filters.state|escape:'url'}&active={$filters.active|escape:'url'}&queue_status={$filters.queue_status|escape:'url'}&error_query={$filters.error_query|escape:'url'}&linked={$filters.linked|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}">Ostatnia</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="mediamarktBulkModal" tabindex="-1" aria-labelledby="mediamarktBulkModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="mediamarktBulkModalLabel">Akcje masowe MediaMarkt</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
        </div>
        <form method="post" action="{$baseUrl}?controller=mediamarkt&action=queue" id="mediamarkt-bulk-form">
          <div class="modal-body">
            <input type="hidden" name="account_id" value="{$filters.account_id|escape}">
            <input type="hidden" name="q" value="{$filters.q|escape}">
            <input type="hidden" name="sku" value="{$filters.sku|escape}">
            <input type="hidden" name="state" value="{$filters.state|escape}">
            <input type="hidden" name="active" value="{$filters.active|escape}">
            <input type="hidden" name="queue_status" value="{$filters.queue_status|escape}">
            <input type="hidden" name="error_query" value="{$filters.error_query|escape}">
            <input type="hidden" name="linked" value="{$filters.linked|escape}">
            <input type="hidden" name="warehouse_quantity_from" value="{$filters.warehouse_quantity_from|escape}">
            <input type="hidden" name="warehouse_quantity_to" value="{$filters.warehouse_quantity_to|escape}">
            <input type="hidden" name="return_url" value="{$currentListUrl|escape}">

            <div class="mb-3">
              <label class="form-label">Zakres</label>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="selection_scope" id="mediamarkt_selection_filtered" value="filtered">
                <label class="form-check-label" for="mediamarkt_selection_filtered">Wszystkie z aktualnego filtrowania</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="selection_scope" id="mediamarkt_selection_selected" value="selected" checked>
                <label class="form-check-label" for="mediamarkt_selection_selected">Tylko zaznaczone na liscie</label>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Operacja</label>
              <select class="form-select" name="operation" id="mediamarkt-operation" required>
                <option value="set_price">Cena: ustaw recznie</option>
                <option value="set_price_from_product">Cena: z magazynu</option>
                <option value="set_stock_from_product">Stan: z magazynu</option>
                <option value="set_description">Opis: ustaw recznie</option>
                <option value="replace_description">Opis: znajdz i zamien</option>
                <option value="end_offer">Zakoncz oferty</option>
                <option value="resume_offer">Wznow oferty</option>
                <option value="clear_queue">Usun z kolejki</option>
                <option value="remove_from_system">Usun z systemu lokalnie</option>
              </select>
              <div class="form-text">W MediaMarktu przez Mirakl podmieniamy opisy, ceny i stany. Tytul oferty nie jest tutaj wystawiony jako pole importu oferty.</div>
            </div>

            <div class="mb-3 mediamarkt-bulk-field" data-ops="set_price,set_description">
              <label class="form-label">Wartosc</label>
              <input type="text" class="form-control" name="value" placeholder="nowa cena lub opis">
            </div>

            <div class="mb-3 mediamarkt-bulk-field d-none" data-ops="replace_description">
              <label class="form-label">Szukaj</label>
              <input type="text" class="form-control mb-2" name="search" placeholder="szukana fraza">
              <label class="form-label">Zamien na</label>
              <input type="text" class="form-control" name="replace" placeholder="nowa fraza">
            </div>

            <div class="small text-secondary">
              Operacje z ceny i stanu ida przez dedykowane importy Mirakl. Konczenie i wznawianie idzie przez aktualizacje oferty.
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
            <button type="submit" class="btn btn-primary">Dodaj do kolejki</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    (function () {
      var checkboxesSelector = '.mediamarkt-offer-checkbox';
      var form = document.getElementById('mediamarkt-bulk-form');
      var operation = document.getElementById('mediamarkt-operation');

      function pageCheckboxes() {
        return Array.prototype.slice.call(document.querySelectorAll(checkboxesSelector));
      }

      function syncSelectedIds() {
        if (!form) {
          return;
        }

        Array.prototype.slice.call(form.querySelectorAll('input[name="selected_offer_ids[]"]')).forEach(function (node) {
          node.parentNode.removeChild(node);
        });

        pageCheckboxes().forEach(function (checkbox) {
          if (!checkbox.checked) {
            return;
          }

          var hidden = document.createElement('input');
          hidden.type = 'hidden';
          hidden.name = 'selected_offer_ids[]';
          hidden.value = checkbox.value || '';
          form.appendChild(hidden);
        });
      }

      function setCheckedState(mode) {
        pageCheckboxes().forEach(function (checkbox) {
          if (mode === 'invert') {
            checkbox.checked = !checkbox.checked;
          } else {
            checkbox.checked = mode === 'check';
          }
        });
        syncSelectedIds();
      }

      function updateBulkFields() {
        if (!operation) {
          return;
        }

        var selected = String(operation.value || '');
        Array.prototype.slice.call(document.querySelectorAll('.mediamarkt-bulk-field')).forEach(function (node) {
          var ops = String(node.getAttribute('data-ops') || '').split(',');
          var visible = ops.indexOf(selected) !== -1;
          node.classList.toggle('d-none', !visible);
        });
      }

      var selectPage = document.getElementById('mediamarkt-select-page');
      var clearPage = document.getElementById('mediamarkt-clear-page');
      var invertPage = document.getElementById('mediamarkt-invert-page');

      if (selectPage) {
        selectPage.addEventListener('click', function () { setCheckedState('check'); });
      }
      if (clearPage) {
        clearPage.addEventListener('click', function () { setCheckedState('clear'); });
      }
      if (invertPage) {
        invertPage.addEventListener('click', function () { setCheckedState('invert'); });
      }

      pageCheckboxes().forEach(function (checkbox) {
        checkbox.addEventListener('change', syncSelectedIds);
      });

      if (operation) {
        operation.addEventListener('change', updateBulkFields);
        updateBulkFields();
      }

      syncSelectedIds();
    })();
  </script>
</main>
