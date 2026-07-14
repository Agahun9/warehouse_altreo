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

      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0">Wszystkie dokumenty</h3>
          <div class="d-flex gap-2">
            <a href="{$baseUrl}?controller=accountingwarehouse&action=create" class="btn btn-primary btn-sm">Dodaj dokument</a>
          </div>
        </div>
        <div class="card-body border-bottom">
          <form method="get" action="{$baseUrl}" class="row g-3 align-items-end">
            <input type="hidden" name="controller" value="accountingwarehouse">
            <input type="hidden" name="action" value="documents">
            <div class="col-md-5">
              <label class="form-label">Dostawca</label>
              <input type="text" class="form-control" name="supplier_name" value="{$filters.supplier_name|default:''|escape}" placeholder="Szukaj po nazwie dostawcy">
            </div>
            <div class="col-md-3">
              <label class="form-label">NIP</label>
              <input type="text" class="form-control" name="supplier_tax_id" value="{$filters.supplier_tax_id|default:''|escape}" placeholder="Szukaj po NIP">
            </div>
            <div class="col-md-4 d-flex gap-2">
              <button type="submit" class="btn btn-primary">Szukaj</button>
              <a href="{$baseUrl}?controller=accountingwarehouse&action=documents" class="btn btn-outline-secondary">Wyczysc</a>
            </div>
          </form>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-hover table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>ID</th>
                  <th>Numer</th>
                  <th>Dostawca</th>
                  <th>NIP</th>
                  <th>Typ</th>
                  <th>Sprzedaz</th>
                  <th>Wystawienie</th>
                  <th class="text-end">Netto</th>
                  <th class="text-end">Brutto</th>
                  <th class="text-end">Akcje</th>
                </tr>
              </thead>
              <tbody>
                {foreach $documents as $document}
                  <tr>
                    <td>{$document.id}</td>
                    <td>
                      <div class="fw-semibold">{$document.document_number|default:'bez numeru'|escape}</div>
                      <div class="small text-secondary">pozycje: {$document.lines_count}</div>
                    </td>
                    <td>{$document.supplier_name|default:'-'|escape}</td>
                    <td>{$document.supplier_tax_id|default:'-'|escape}</td>
                    <td>
                      <span class="badge text-bg-{if $document.document_kind eq 'issue'}primary{elseif $document.document_kind eq 'adjustment'}warning{elseif $document.document_kind eq 'koszt'}info{elseif $document.source_type eq 'xml'}info{elseif $document.source_type eq 'legacy_sql'}dark{else}secondary{/if}">
                        {if $document.document_kind eq 'issue'}wyjscie{elseif $document.document_kind eq 'adjustment'}korekta{elseif $document.document_kind eq 'koszt'}koszt{elseif $document.source_type eq 'xml'}xml{elseif $document.source_type eq 'legacy_sql'}stary sql{else}reczne{/if}
                      </span>
                    </td>
                    <td>{$document.sale_date|default:'-'|escape}</td>
                    <td>{$document.issue_date|default:'-'|escape}</td>
                    <td class="text-end">{$document.total_net|string_format:'%.2f'} {$document.currency|escape}</td>
                    <td class="text-end">{$document.total_gross|string_format:'%.2f'} {$document.currency|escape}</td>
                    <td class="text-end">
                      <a href="{$baseUrl}?controller=accountingwarehouse&action=show&id={$document.id}" class="btn btn-sm btn-outline-primary">Podglad</a>
                      {if $document.document_kind neq 'issue'}
                        <a href="{$baseUrl}?controller=accountingwarehouse&action=edit&id={$document.id}" class="btn btn-sm btn-outline-secondary">Edytuj</a>
                      {/if}
                      <form method="post" action="{$baseUrl}?controller=accountingwarehouse&action=delete" class="d-inline" onsubmit="return confirm('Usunac dokument nr {$document.document_number|default:$document.id|escape:'javascript'}?');">
                        <input type="hidden" name="id" value="{$document.id}">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Usun</button>
                      </form>
                    </td>
                  </tr>
                {foreachelse}
                  <tr><td colspan="10" class="text-center py-3">Brak dokumentow.</td></tr>
                {/foreach}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
