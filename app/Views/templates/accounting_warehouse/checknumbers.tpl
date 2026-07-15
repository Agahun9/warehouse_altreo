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
        <div class="card-header"><h3 class="card-title mb-0">Wklej liste numerow faktur</h3></div>
        <div class="card-body">
          <form method="post" action="{$baseUrl}?controller=accountingwarehouse&action=checknumbers">
            <div class="mb-3">
              <label class="form-label">Numery faktur (jeden numer w kazdej linii)</label>
              <textarea class="form-control" name="numbers" rows="12" placeholder="FS-EC-26-06-00425&#10;557/06/2026&#10;FS 153/MAG/06/2026&#10;...">{$rawList|escape}</textarea>
            </div>
            <button type="submit" class="btn btn-primary">Sprawdz</button>
          </form>
        </div>
      </div>

      {if $results}
        <div class="row g-3 mb-3">
          <div class="col-md-3">
            <div class="card text-center">
              <div class="card-body">
                <div class="fs-3 fw-semibold">{$results|@count}</div>
                <div class="text-secondary small">sprawdzonych numerow</div>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card text-center border-success">
              <div class="card-body">
                <div class="fs-3 fw-semibold text-success">{$foundCount}</div>
                <div class="text-secondary small">znalezionych w magazynie</div>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card text-center border-danger">
              <div class="card-body">
                <div class="fs-3 fw-semibold text-danger">{$missingCount}</div>
                <div class="text-secondary small">brakujacych</div>
              </div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><h3 class="card-title mb-0">Wynik</h3></div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm table-hover table-bordered align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Numer z listy</th>
                    <th>Status</th>
                    <th>Dopasowane dokumenty w magazynie</th>
                  </tr>
                </thead>
                <tbody>
                  {foreach $results as $result}
                    <tr class="{if !$result.found}table-danger{/if}">
                      <td class="fw-semibold">{$result.query|escape}</td>
                      <td>
                        {if $result.found}
                          <span class="badge text-bg-success">znaleziono</span>
                        {else}
                          <span class="badge text-bg-danger">brak</span>
                        {/if}
                      </td>
                      <td>
                        {if $result.found}
                          {foreach $result.documents as $document}
                            <div class="mb-1">
                              <a href="{$baseUrl}?controller=accountingwarehouse&action=show&id={$document.id}">#{$document.id} {$document.document_number|escape}</a>
                              <span class="text-secondary small"> - {$document.supplier_name|default:'-'|escape}, {$document.sale_date|default:$document.issue_date|default:'-'|escape}</span>
                            </div>
                          {/foreach}
                        {else}
                          <span class="text-secondary">-</span>
                        {/if}
                      </td>
                    </tr>
                  {/foreach}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      {/if}
    </div>
  </div>
</main>
