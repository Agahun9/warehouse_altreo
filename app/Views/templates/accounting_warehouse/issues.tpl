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
            <input type="hidden" name="action" value="issues">
            <div class="col-md-3">
              <label class="form-label">Miesiac</label>
              <input type="month" class="form-control" name="month" value="{$selectedMonth|escape}">
            </div>
            <div class="col-md-3 d-flex gap-2">
              <button type="submit" class="btn btn-primary">Pokaz raport</button>
              <a href="{$baseUrl}?controller=accountingwarehouse&action=exportissuesxlsx&month={$selectedMonth|escape:'url'}" class="btn btn-outline-success">Eksport XLSX</a>
              <a href="{$baseUrl}?controller=accountingwarehouse&action=issuecreate" class="btn btn-outline-secondary">Nowe wyjscie</a>
            </div>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3 class="card-title mb-0">Rozchod za {$selectedMonth|escape}</h3></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-hover table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Data</th>
                  <th>Dokument wyjscia</th>
                  <th>Pozycja ksiegowa</th>
                  <th>Z jakiej FV</th>
                  <th class="text-end">Sztuki</th>
                  <th class="text-end">Wartosc rozchodu</th>
                </tr>
              </thead>
              <tbody>
                {foreach $issueRows as $row}
                  <tr>
                    <td>{$row.issue_date|default:'-'|escape}</td>
                    <td><a href="{$baseUrl}?controller=accountingwarehouse&action=show&id={$row.issue_document_id}">{$row.issue_document_number|default:'bez numeru'|escape}</a></td>
                    <td>{$row.item_name|escape}</td>
                    <td>
                      <div class="fw-semibold">{$row.source_document_number|default:'brak przypisania'|escape}</div>
                      <div class="small text-secondary">{$row.source_supplier_name|default:'-'|escape}</div>
                    </td>
                    <td class="text-end">{$row.quantity|string_format:'%.0f'}</td>
                    <td class="text-end">{$row.issue_value|string_format:'%.2f'} PLN</td>
                  </tr>
                {foreachelse}
                  <tr><td colspan="6" class="text-center py-3">Brak wyjsc z magazynu w tym miesiacu.</td></tr>
                {/foreach}
                {if $issueRows}
                  {assign var="issuesTotalQuantity" value=0}
                  {assign var="issuesTotalGross" value=0}
                  {foreach $issueRows as $row}
                    {assign var="issuesTotalQuantity" value=$issuesTotalQuantity + $row.quantity}
                    {assign var="issuesTotalGross" value=$issuesTotalGross + $row.issue_value}
                  {/foreach}
                  <tr class="table-light fw-semibold">
                    <td colspan="4" class="text-end">Suma</td>
                    <td class="text-end">{$issuesTotalQuantity|string_format:'%.0f'}</td>
                    <td class="text-end">{$issuesTotalGross|string_format:'%.2f'} PLN</td>
                  </tr>
                {/if}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
