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

      <div class="card mb-4">
        <div class="card-body">
          <form method="get" action="{$baseUrl}" class="row g-3 align-items-end">
            <input type="hidden" name="controller" value="accountingwarehouse">
            <input type="hidden" name="action" value="export">
            <div class="col-md-3">
              <label class="form-label">Data od</label>
              <input type="date" class="form-control" name="date_from" value="{$dateFrom|escape}">
            </div>
            <div class="col-md-3">
              <label class="form-label">Data do</label>
              <input type="date" class="form-control" name="date_to" value="{$dateTo|escape}">
            </div>
            <div class="col-md-3">
              <button type="submit" class="btn btn-primary">Odswiez podsumowanie</button>
            </div>
          </form>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-md-3">
          <div class="card h-100">
            <div class="card-header"><h3 class="card-title mb-0">Koszty</h3></div>
            <div class="card-body d-flex flex-column">
              <p class="text-secondary">Faktury oznaczone przy imporcie/edycji jako typ koszt (cala faktura to koszt).</p>
              <p class="fs-4 fw-semibold mb-3">{$counts.koszt} faktur</p>
              <a href="{$baseUrl}?controller=accountingwarehouse&action=exportcostsxlsx&date_from={$dateFrom|escape:'url'}&date_to={$dateTo|escape:'url'}" class="btn btn-outline-danger mt-auto" data-no-page-loader="1">Eksport XLSX - koszty</a>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card h-100">
            <div class="card-header"><h3 class="card-title mb-0">Towar</h3></div>
            <div class="card-body d-flex flex-column">
              <p class="text-secondary">Faktury typu towar z co najwyzej jedna pozycja typu koszt.</p>
              <p class="fs-4 fw-semibold mb-3">{$counts.towar} faktur</p>
              <a href="{$baseUrl}?controller=accountingwarehouse&action=exportgoodsxlsx&date_from={$dateFrom|escape:'url'}&date_to={$dateTo|escape:'url'}" class="btn btn-outline-success mt-auto" data-no-page-loader="1">Eksport XLSX - towar</a>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card h-100">
            <div class="card-header"><h3 class="card-title mb-0">Mieszane</h3></div>
            <div class="card-body d-flex flex-column">
              <p class="text-secondary">Faktury typu towar z co najmniej dwiema pozycjami typu koszt - rozpisane pozycja po pozycji.</p>
              <p class="fs-4 fw-semibold mb-3">{$counts.mieszane} faktur</p>
              <a href="{$baseUrl}?controller=accountingwarehouse&action=exportmixedxlsx&date_from={$dateFrom|escape:'url'}&date_to={$dateTo|escape:'url'}" class="btn btn-outline-warning mt-auto" data-no-page-loader="1">Eksport XLSX - mieszane</a>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card h-100">
            <div class="card-header"><h3 class="card-title mb-0">Korekty</h3></div>
            <div class="card-body d-flex flex-column">
              <p class="text-secondary">Faktury oznaczone jako korekta.</p>
              <p class="fs-4 fw-semibold mb-3">{$counts.korekta} faktur</p>
              <a href="{$baseUrl}?controller=accountingwarehouse&action=exportadjustmentsxlsx&date_from={$dateFrom|escape:'url'}&date_to={$dateTo|escape:'url'}" class="btn btn-outline-secondary mt-auto" data-no-page-loader="1">Eksport XLSX - korekty</a>
            </div>
          </div>
        </div>
      </div>

      <p class="text-secondary mt-3">Zakres {$dateFrom|escape} - {$dateTo|escape}: lacznie {$totalDocuments} faktur.</p>

      <div class="card mt-4">
        <div class="card-header"><h3 class="card-title mb-0">Diagnostyka danych (tymczasowy podglad)</h3></div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-md-4">
              <h4 class="h6">Pozycje wg Typu (item_kind)</h4>
              <ul class="list-unstyled mb-0">
                <li>towar: <strong>{$lineKindCounts.towar}</strong></li>
                <li>koszt: <strong>{$lineKindCounts.koszt}</strong></li>
                <li>korekta: <strong>{$lineKindCounts.korekta}</strong></li>
              </ul>
            </div>
            <div class="col-md-4">
              <h4 class="h6">Faktury wg document_kind</h4>
              <ul class="list-unstyled mb-0">
                {foreach $documentKindCounts as $kind => $count}
                  <li>{$kind|escape}: <strong>{$count}</strong></li>
                {/foreach}
              </ul>
            </div>
            <div class="col-md-4">
              <h4 class="h6">Liczba pozycji-koszt na fakture</h4>
              <ul class="list-unstyled mb-0">
                {foreach $costLineCountBreakdown as $numberOfCostLines => $documentsCount}
                  <li>{$numberOfCostLines} pozycji koszt: <strong>{$documentsCount}</strong> faktur</li>
                {/foreach}
              </ul>
            </div>
          </div>
          <hr>
          <h4 class="h6">Najczestsze nazwy pozycji oznaczonych jako koszt</h4>
          {if $itemsWithCostLines}
            <ul class="list-unstyled mb-0">
              {foreach $itemsWithCostLines as $name => $count}
                <li>{$name|escape}: <strong>{$count}</strong> wystapien</li>
              {/foreach}
            </ul>
          {else}
            <p class="text-secondary mb-0">Brak - zadna pozycja w tym zakresie nie ma item_kind = koszt.</p>
          {/if}
        </div>
      </div>
    </div>
  </div>
</main>
