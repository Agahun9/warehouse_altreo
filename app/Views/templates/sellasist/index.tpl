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
        <div class="card-body pb-0">
          <ul class="nav nav-tabs">
            <li class="nav-item">
              <a class="nav-link{if $sellasistTab eq 'zbieranie'} active{/if}" href="{$baseUrl}?controller=sellasist&action=zbieranie">Zbieranie</a>
            </li>
          </ul>
        </div>
      </div>

      {if not $sellasistConfigured}
        <div class="alert alert-warning">
          Brak konfiguracji Sellasist API. Uzupelnij dane w <a href="{$baseUrl}?controller=admin&action=automation" class="alert-link">Administracja</a>.
        </div>
      {/if}

      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0">Lista zamowien do zbierania</h3>
          <span class="small text-secondary">
            status pobierania: {$sellasistPickingStatusId|escape}, po wydruku:
            {if $sellasistPrintedStatusId > 0}
              zmiana na {$sellasistPrintedStatusId|escape}
            {else}
              bez zmiany statusu
            {/if}
          </span>
        </div>
        <div class="card-body">
          <form method="post" action="{$baseUrl}?controller=sellasist&action=stickers" target="_blank" id="sellasistPickingForm">
            <div class="d-flex flex-wrap gap-2 mb-3">
              <button type="submit" class="btn btn-primary">Generuj stickers</button>
              <button type="button" class="btn btn-outline-secondary" id="sellasistSelectAll">Zaznacz wszystko</button>
              <button type="button" class="btn btn-outline-secondary" id="sellasistClearAll">Odznacz wszystko</button>
            </div>

            <div class="table-responsive">
              <table class="table table-sm table-hover table-bordered align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="width: 48px;">
                      <input type="checkbox" class="form-check-input" id="sellasistToggleAll">
                    </th>
                    <th>ID</th>
                    <th>Klient</th>
                    <th>Dostawa</th>
                    <th>Konto</th>
                    <th>Produkty</th>
                    <th>Komentarz</th>
                  </tr>
                </thead>
                <tbody>
                  {foreach $orders as $order}
                    <tr>
                      <td class="text-center">
                        <input type="checkbox" class="form-check-input sellasist-order-checkbox" name="order_id[]" value="{$order.id}">
                      </td>
                      <td class="fw-semibold">{$order.id}</td>
                      <td>{$order.customer_name|default:'-'|escape}</td>
                      <td>{$order.delivery_name|default:'-'|escape}</td>
                      <td>{$order.creator|default:'-'|escape}</td>
                      <td>
                        <div>{$order.item_count} szt. / pozycji</div>
                        {if $order.items_summary neq ''}
                          <div class="small text-secondary">{$order.items_summary|escape}</div>
                        {/if}
                      </td>
                      <td>{$order.comment|default:'-'|escape}</td>
                    </tr>
                  {foreachelse}
                    <tr>
                      <td colspan="7" class="text-center text-secondary py-4">Brak zamowien do zbierania.</td>
                    </tr>
                  {/foreach}
                </tbody>
              </table>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</main>
<script>
  (function () {
    var toggleAll = document.getElementById('sellasistToggleAll');
    var selectAll = document.getElementById('sellasistSelectAll');
    var clearAll = document.getElementById('sellasistClearAll');

    function checkboxes() {
      return document.querySelectorAll('.sellasist-order-checkbox');
    }

    function setAll(checked) {
      var items = checkboxes();
      for (var i = 0; i < items.length; i++) {
        items[i].checked = checked;
      }
      if (toggleAll) {
        toggleAll.checked = checked;
      }
    }

    if (toggleAll) {
      toggleAll.addEventListener('change', function () {
        setAll(toggleAll.checked);
      });
    }

    if (selectAll) {
      selectAll.addEventListener('click', function () {
        setAll(true);
      });
    }

    if (clearAll) {
      clearAll.addEventListener('click', function () {
        setAll(false);
      });
    }
  })();
</script>
