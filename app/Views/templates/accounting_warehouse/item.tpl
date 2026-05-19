<main class="app-main">
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-6">
          <h3 class="mb-0">{$contentTitle|escape}: {$item.name|escape}</h3>
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
          <div class="row g-3">
            <div class="col-md-3">
              <div class="small text-secondary">Pozycja ksiegowa</div>
              <div class="fw-semibold">{$item.name|escape}</div>
            </div>
            <div class="col-md-2">
              <div class="small text-secondary">Typ</div>
              <div>{$item.item_kind|default:'towar'|escape}</div>
            </div>
            <div class="col-md-2">
              <div class="small text-secondary">Sztuki</div>
              <div>{$item.quantity|string_format:'%.3f'} {$item.unit|escape}</div>
            </div>
            <div class="col-md-2">
              <div class="small text-secondary">Netto</div>
              <div>{$item.total_net|string_format:'%.2f'} PLN</div>
            </div>
            <div class="col-md-2">
              <div class="small text-secondary">Brutto</div>
              <div>{$item.total_gross|string_format:'%.2f'} PLN</div>
            </div>
            <div class="col-md-1">
              <div class="small text-secondary">Ruchy</div>
              <div>{$item.movements_count}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0">Zrodlowe nazwy w tej pozycji</h3>
          <a href="{$baseUrl}?controller=accountingwarehouse&action=index" class="btn btn-sm btn-outline-secondary">Wroc</a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-hover table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Zrodlo</th>
                  <th class="text-end">Dokumenty</th>
                  <th class="text-end">Wiersze</th>
                  <th class="text-end">Sztuki</th>
                  <th class="text-end">Netto</th>
                  <th class="text-end">Brutto</th>
                  <th>Przepnij na</th>
                </tr>
              </thead>
              <tbody>
                {foreach $item.sources as $source}
                  <tr{if $item.name eq 'pozostale'} class="table-warning"{/if}>
                    <td>
                      <div class="fw-semibold">{$source.source_name|escape}</div>
                      <div class="small text-secondary">ostatnia sprzedaz: {$source.last_sale_date|default:'-'|escape}</div>
                    </td>
                    <td class="text-end">{$source.documents_count}</td>
                    <td class="text-end">{$source.rows_count}</td>
                    <td class="text-end">{$source.quantity|string_format:'%.3f'} {$source.unit|escape}</td>
                    <td class="text-end">{$source.total_net|string_format:'%.2f'} PLN</td>
                    <td class="text-end">{$source.total_gross|string_format:'%.2f'} PLN</td>
                    <td style="min-width: 320px;">
                      <form method="post" action="{$baseUrl}?controller=accountingwarehouse&action=reassignitemsource" class="d-flex gap-2 align-items-center">
                        <input type="hidden" name="id" value="{$item.id}">
                        <input type="hidden" name="source_name" value="{$source.source_name|escape}">
                        <select class="form-select form-select-sm" name="target_canonical_name" required>
                          <option value="">Wybierz pozycje ksiegowa</option>
                          {foreach $itemSuggestions as $suggestion}
                            <option value="{$suggestion|escape}"{if $suggestion eq $item.name} selected{/if}>{$suggestion|escape}</option>
                          {/foreach}
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary">Zmien</button>
                      </form>
                    </td>
                  </tr>
                {foreachelse}
                  <tr><td colspan="7" class="text-center py-3">Brak zrodlowych nazw dla tej pozycji.</td></tr>
                {/foreach}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3 class="card-title mb-0">Ostatnie wiersze dokumentow</h3></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Dokument</th>
                  <th>Zrodlo</th>
                  <th class="text-end">Sztuki</th>
                  <th class="text-end">Netto</th>
                  <th class="text-end">Brutto</th>
                  <th>Data</th>
                </tr>
              </thead>
              <tbody>
                {foreach $item.recent_lines as $line}
                  <tr>
                    <td><a href="{$baseUrl}?controller=accountingwarehouse&action=show&id={$line.document_id}">{$line.document_number|default:'bez numeru'|escape}</a></td>
                    <td>{$line.original_name|default:$line.canonical_name|escape}</td>
                    <td class="text-end">{$line.quantity|string_format:'%.3f'} {$line.unit|escape}</td>
                    <td class="text-end">{$line.line_net|string_format:'%.2f'} PLN</td>
                    <td class="text-end">{$line.line_gross|string_format:'%.2f'} PLN</td>
                    <td>{$line.sale_date|default:$line.issue_date|default:'-'|escape}</td>
                  </tr>
                {foreachelse}
                  <tr><td colspan="6" class="text-center py-3">Brak ruchow dla tej pozycji.</td></tr>
                {/foreach}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
