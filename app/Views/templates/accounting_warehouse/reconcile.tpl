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
        <div class="card-header"><h3 class="card-title mb-0">Wgraj pliki od ksiegowej</h3></div>
        <div class="card-body">
          <p class="text-secondary">Wgraj plik XLS lub XLSX z zestawieniem Towarow i/lub plik XLS lub XLSX z zestawieniem Kosztow (mozna wgrac tylko jeden z nich). Kazdy plik powinien miec w pierwszym wierszu naglowki kolumn (np. Numer dok. S, Kontrahent, Netto, Brutto, NIP), tak jak w eksporcie z programu ksiegowego.</p>
          <form method="post" action="{$baseUrl}?controller=accountingwarehouse&action=reconcilerun" enctype="multipart/form-data">
            <div class="row g-3 align-items-end">
              <div class="col-md-4">
                <label class="form-label">Plik "Towary" (XLS/XLSX)</label>
                <input type="file" class="form-control" name="goods_xlsx" accept=".xls,.xlsx">
              </div>
              <div class="col-md-4">
                <label class="form-label">Plik "Koszt" (XLS/XLSX)</label>
                <input type="file" class="form-control" name="costs_xlsx" accept=".xls,.xlsx">
              </div>
              <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">Porownaj</button>
              </div>
            </div>
          </form>
        </div>
      </div>

      {if $report}
        <div class="card mb-4">
          <div class="card-header"><h3 class="card-title mb-0">Wynik porownania</h3></div>
          <div class="card-body">
            <div class="row g-3 mb-3">
              <div class="col-md-2"><div class="card text-center"><div class="card-body p-2"><div class="fs-4 fw-semibold text-success">{$report.matched_ok}</div><div class="text-secondary small">Zgodne</div></div></div></div>
              <div class="col-md-2"><div class="card text-center"><div class="card-body p-2"><div class="fs-4 fw-semibold text-danger">{$report.missing_in_warehouse|@count}</div><div class="text-secondary small">Brak w magazynie</div></div></div></div>
              <div class="col-md-2"><div class="card text-center"><div class="card-body p-2"><div class="fs-4 fw-semibold text-warning">{$report.type_mismatches|@count}</div><div class="text-secondary small">Zla kategoria</div></div></div></div>
              <div class="col-md-2"><div class="card text-center"><div class="card-body p-2"><div class="fs-4 fw-semibold text-info">{$report.mixed_documents|@count}</div><div class="text-secondary small">Mieszane</div></div></div></div>
            </div>

            <label class="form-label">Raport tekstowy (do skopiowania)</label>
            <textarea class="form-control" rows="20" readonly onclick="this.select()">{$report.summary_text}</textarea>
          </div>
        </div>

        {if $report.missing_in_warehouse}
        <div class="card mb-4">
          <div class="card-header"><h3 class="card-title mb-0">Brak w magazynie</h3></div>
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead><tr><th>Numer</th><th>Typ u ksiegowej</th><th>Kontrahent</th><th>Netto</th><th>Brutto</th></tr></thead>
              <tbody>
                {foreach $report.missing_in_warehouse as $row}
                  <tr>
                    <td>{$row.document_number|escape}</td>
                    <td>{$row.expected_type|escape}</td>
                    <td>{$row.contractor|escape}</td>
                    <td>{$row.net|string_format:"%.2f"}</td>
                    <td>{$row.gross|string_format:"%.2f"}</td>
                  </tr>
                {/foreach}
              </tbody>
            </table>
          </div>
        </div>
        {/if}

        {if $report.type_mismatches}
        <div class="card mb-4">
          <div class="card-header"><h3 class="card-title mb-0">Zla kategoria (towar/koszt)</h3></div>
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead><tr><th>Numer</th><th>Kontrahent</th><th>U ksiegowej</th><th>W magazynie</th><th></th></tr></thead>
              <tbody>
                {foreach $report.type_mismatches as $row}
                  <tr>
                    <td>{$row.document_number|escape}</td>
                    <td>{$row.contractor|escape}</td>
                    <td>{$row.expected_type|escape}</td>
                    <td>{$row.local_classification|escape}</td>
                    <td><a href="{$baseUrl}?controller=accountingwarehouse&action=show&id={$row.local_document_id}">podgląd</a></td>
                  </tr>
                {/foreach}
              </tbody>
            </table>
          </div>
        </div>
        {/if}

        {if $report.mixed_documents}
        <div class="card mb-4">
          <div class="card-header"><h3 class="card-title mb-0">Mieszane (towar + koszt w jednym dokumencie) - podzial dla ksiegowej</h3></div>
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead>
                <tr>
                  <th>Numer</th><th>Kontrahent</th>
                  <th>Towar netto</th><th>Towar brutto</th>
                  <th>Koszt (pozycja)</th><th>Koszt netto</th><th>Koszt brutto</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {foreach $report.mixed_documents as $row}
                  <tr>
                    <td>{$row.document_number|escape}</td>
                    <td>{$row.contractor|escape}</td>
                    <td>{$row.goods_net|string_format:"%.2f"}</td>
                    <td>{$row.goods_gross|string_format:"%.2f"}</td>
                    <td>{$row.cost_names_label|escape}</td>
                    <td>{$row.cost_net|string_format:"%.2f"}</td>
                    <td>{$row.cost_gross|string_format:"%.2f"}</td>
                    <td><a href="{$baseUrl}?controller=accountingwarehouse&action=show&id={$row.local_document_id}">podgląd</a></td>
                  </tr>
                {/foreach}
              </tbody>
            </table>
          </div>
        </div>
        {/if}

        {if $report.processed_goods}
        <div class="card mb-4">
          <div class="card-header">
            <h3 class="card-title mb-0">
              <a data-bs-toggle="collapse" href="#processedGoods" role="button" aria-expanded="false">
                Przetworzone faktury - Towary ({$report.processed_goods|@count})
              </a>
            </h3>
          </div>
          <div class="collapse" id="processedGoods">
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <thead><tr><th>Numer</th><th>Kontrahent</th><th>NIP</th><th>Netto</th><th>Brutto</th><th>Status</th></tr></thead>
                <tbody>
                  {foreach $report.processed_goods as $row}
                    <tr>
                      <td>{$row.document_number|escape}</td>
                      <td>{$row.contractor|escape}</td>
                      <td>{$row.nip|escape}</td>
                      <td>{$row.net|string_format:"%.2f"}</td>
                      <td>{$row.gross|string_format:"%.2f"}</td>
                      <td>{$row.status|escape}</td>
                    </tr>
                  {/foreach}
                </tbody>
              </table>
            </div>
          </div>
        </div>
        {/if}

        {if $report.processed_costs}
        <div class="card mb-4">
          <div class="card-header">
            <h3 class="card-title mb-0">
              <a data-bs-toggle="collapse" href="#processedCosts" role="button" aria-expanded="false">
                Przetworzone faktury - Koszt ({$report.processed_costs|@count})
              </a>
            </h3>
          </div>
          <div class="collapse" id="processedCosts">
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <thead><tr><th>Numer</th><th>Kontrahent</th><th>NIP</th><th>Netto</th><th>Brutto</th><th>Status</th></tr></thead>
                <tbody>
                  {foreach $report.processed_costs as $row}
                    <tr>
                      <td>{$row.document_number|escape}</td>
                      <td>{$row.contractor|escape}</td>
                      <td>{$row.nip|escape}</td>
                      <td>{$row.net|string_format:"%.2f"}</td>
                      <td>{$row.gross|string_format:"%.2f"}</td>
                      <td>{$row.status|escape}</td>
                    </tr>
                  {/foreach}
                </tbody>
              </table>
            </div>
          </div>
        </div>
        {/if}
      {/if}
    </div>
  </div>
</main>
