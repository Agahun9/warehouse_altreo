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
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=accountingwarehouse&action=documents">Dokumenty</a></li>
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
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0">Dokument #{$document.id}</h3>
          <div class="d-flex gap-2">
            <a href="{$baseUrl}?controller=accountingwarehouse&action=documents" class="btn btn-sm btn-outline-dark">Wroc do listy</a>
            <a href="{$baseUrl}?controller=accountingwarehouse&action=edit&id={$document.id}" class="btn btn-sm btn-primary">Edytuj</a>
          </div>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-3"><strong>Numer:</strong><br>{$document.document_number|default:'-'|escape}</div>
            <div class="col-md-3"><strong>Dostawca:</strong><br>{$document.supplier_name|default:'-'|escape}</div>
            <div class="col-md-2"><strong>NIP:</strong><br>{$document.supplier_tax_id|default:'-'|escape}</div>
            <div class="col-md-2"><strong>Data sprzedazy:</strong><br>{$document.sale_date|default:'-'|escape}</div>
            <div class="col-md-2"><strong>Data wystawienia:</strong><br>{$document.issue_date|default:'-'|escape}</div>
            <div class="col-md-2"><strong>Typ:</strong><br>{if $document.document_kind eq 'adjustment'}korekta{elseif $document.source_type eq 'xml'}xml{else}reczne{/if}</div>
            <div class="col-md-3"><strong>Netto:</strong><br>{$document.total_net|string_format:'%.2f'} {$document.currency|escape}</div>
            <div class="col-md-3"><strong>Brutto:</strong><br>{$document.total_gross|string_format:'%.2f'} {$document.currency|escape}</div>
            <div class="col-md-6"><strong>Uwagi:</strong><br>{$document.notes|default:'-'|escape}</div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3 class="card-title mb-0">Pozycje dokumentu</h3></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Oryginalna nazwa</th>
                  <th>Pozycja ksiegowa</th>
                  <th class="text-end">Ilosc</th>
                  <th class="text-end">Netto/szt.</th>
                  <th class="text-end">Brutto/szt.</th>
                  <th class="text-end">Netto</th>
                  <th class="text-end">Brutto</th>
                </tr>
              </thead>
              <tbody>
                {foreach $document.lines as $line}
                  <tr>
                    <td>{$line.original_name|escape}</td>
                    <td>{$line.item_name|escape}</td>
                    <td class="text-end">{$line.quantity|string_format:'%.3f'} {$line.unit|escape}</td>
                    <td class="text-end">{$line.unit_net|string_format:'%.2f'} PLN</td>
                    <td class="text-end">{$line.unit_gross|string_format:'%.2f'} PLN</td>
                    <td class="text-end">{$line.line_net|string_format:'%.2f'} PLN</td>
                    <td class="text-end">{$line.line_gross|string_format:'%.2f'} PLN</td>
                  </tr>
                {foreachelse}
                  <tr><td colspan="7" class="text-center py-3">Brak pozycji.</td></tr>
                {/foreach}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
