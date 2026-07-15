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
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=accountingwarehouse&action=export">Eksport XLSX</a></li>
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
          <h3 class="card-title mb-0">Zamowienia do zestawienia OSS</h3>
        </div>
        <div class="card-body">
          <form method="post" action="{$baseUrl}?controller=accountingwarehouse&action=ossgenerate">
            <div class="d-flex flex-wrap gap-2 mb-3">
              <button type="submit" class="btn btn-primary">Generuj</button>
              <button type="button" class="btn btn-outline-secondary" id="ossSelectAll">Zaznacz wszystko</button>
              <button type="button" class="btn btn-outline-secondary" id="ossClearAll">Odznacz wszystko</button>
            </div>

            <div class="table-responsive">
              <table class="table text-start align-middle table-bordered table-hover mb-0">
                <thead>
                  <tr class="text-dark">
                    <th scope="col"><input class="form-check-input" type="checkbox" id="ossCheckAll"></th>
                    <th scope="col">Dane</th>
                    <th scope="col"></th>
                  </tr>
                </thead>
                <tbody>
                  {foreach $orders as $order}
                    <tr>
                      <td><input class="form-check-input oss-order-checkbox" type="checkbox" name="order_id[]" value="{$order.order_id}"></td>
                      <td>{$order.delivery_fullname|escape}</td>
                      <td><a class="btn btn-sm btn-primary" href="https://altreo.sellasist.pl/admin/orders/edit/{$order.order_id}" target="_blank" rel="noreferrer">Przejdz</a></td>
                    </tr>
                  {foreachelse}
                    <tr>
                      <td colspan="3" class="text-center text-secondary">Brak zamowien do wyswietlenia.</td>
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
    var checkAll = document.getElementById('ossCheckAll');
    var selectAllBtn = document.getElementById('ossSelectAll');
    var clearAllBtn = document.getElementById('ossClearAll');

    function setAll(checked) {
      document.querySelectorAll('.oss-order-checkbox').forEach(function (checkbox) {
        checkbox.checked = checked;
      });
    }

    if (checkAll) {
      checkAll.addEventListener('change', function () {
        setAll(checkAll.checked);
      });
    }
    if (selectAllBtn) {
      selectAllBtn.addEventListener('click', function () {
        setAll(true);
      });
    }
    if (clearAllBtn) {
      clearAllBtn.addEventListener('click', function () {
        setAll(false);
      });
    }
  })();
</script>
