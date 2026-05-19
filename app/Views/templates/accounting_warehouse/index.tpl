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

      <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
          <div class="small-box text-bg-primary">
            <div class="inner"><h3>{$overview.items_on_stock|default:0}</h3><p>Pozycje na stanie</p></div>
            <div class="small-box-icon"><i class="bi bi-box-seam"></i></div>
          </div>
        </div>
        <div class="col-xl-3 col-sm-6">
          <div class="small-box text-bg-success">
            <div class="inner"><h3>{$overview.total_quantity|default:0|string_format:'%.3f'}</h3><p>Suma sztuk</p></div>
            <div class="small-box-icon"><i class="bi bi-123"></i></div>
          </div>
        </div>
        <div class="col-xl-3 col-sm-6">
          <div class="small-box text-bg-dark">
            <div class="inner"><h3>{$overview.total_net|default:0|string_format:'%.2f'}</h3><p>Wartosc netto</p></div>
            <div class="small-box-icon"><i class="bi bi-cash-stack"></i></div>
          </div>
        </div>
        <div class="col-xl-3 col-sm-6">
          <div class="small-box text-bg-warning">
            <div class="inner"><h3>{$overview.total_gross|default:0|string_format:'%.2f'}</h3><p>Wartosc brutto</p></div>
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
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Pozycja</th>
                      <th class="text-end">Sztuki</th>
                      <th class="text-end">Netto</th>
                      <th class="text-end">Brutto</th>
                      <th class="text-end">Ruchy</th>
                    </tr>
                  </thead>
                  <tbody>
                    {foreach $stockSummary as $item}
                      <tr>
                        <td><a href="{$baseUrl}?controller=accountingwarehouse&action=item&id={$item.id}">{$item.name|escape}</a></td>
                        <td class="text-end">{$item.quantity|string_format:'%.3f'} {$item.unit|escape}</td>
                        <td class="text-end">{$item.total_net|string_format:'%.2f'} PLN</td>
                        <td class="text-end">{$item.total_gross|string_format:'%.2f'} PLN</td>
                        <td class="text-end">{$item.movements_count}</td>
                      </tr>
                    {foreachelse}
                      <tr><td colspan="5" class="text-center py-3">Brak pozycji w magazynie ksiegowym.</td></tr>
                    {/foreach}
                  </tbody>
                </table>
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
