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

      <div class="card border-warning">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0">Automatyczne wyrownanie stanu</h3>
          <a href="{$baseUrl}?controller=accountingwarehouse&action=issues" class="btn btn-sm btn-outline-secondary">Raport wyjsc</a>
        </div>
        <div class="card-body">
          <div class="alert alert-light border small">
            Wpisujesz jedna kwote. System szuka pozycji typu <code>towar</code>, ktore nie mialy jeszcze wyjscia magazynowego,
            i odejmuje sztuki bez zmiany ceny jednostkowej tak, aby mozliwie najlepiej zblizyc sie do tej wartosci.
            Najpierw mozesz zobaczyc podglad, co zostanie zdjete i z jakiej FV. Dokument zapisze sie jako zwykle wyjscie magazynowe z numerem typu <code>WYR-...</code>.
          </div>

          <form method="post" action="{if $balancePreview}{$baseUrl}?controller=accountingwarehouse&action=storeissuebalance{else}{$baseUrl}?controller=accountingwarehouse&action=issuebalancecreate{/if}">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Numer dokumentu</label>
                <input type="text" class="form-control" name="balance_document_number" value="{$balanceFormData.document_number|escape}">
              </div>
              <div class="col-md-4">
                <label class="form-label">Data dokumentu</label>
                <input type="date" class="form-control" name="balance_issue_date" value="{$balanceFormData.issue_date|escape}">
              </div>
              <div class="col-md-4">
                <label class="form-label">Data wyjscia</label>
                <input type="date" class="form-control" name="balance_sale_date" value="{$balanceFormData.sale_date|escape}">
              </div>
              <div class="col-md-3">
                <label class="form-label">Waluta</label>
                <select class="form-select" name="balance_currency">
                  {foreach $currencyOptions as $currencyOption}
                    <option value="{$currencyOption|escape}"{if $balanceFormData.currency eq $currencyOption} selected{/if}>{$currencyOption|escape}</option>
                  {/foreach}
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Tryb kwoty</label>
                <select class="form-select" name="balance_target_mode">
                  <option value="gross"{if $balanceFormData.target_mode eq 'gross'} selected{/if}>Brutto</option>
                  <option value="net"{if $balanceFormData.target_mode eq 'net'} selected{/if}>Netto</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Kwota docelowa</label>
                <input type="number" step="0.01" class="form-control" name="balance_target_value" value="{$balanceFormData.target_value|escape}" placeholder="np. -267.00">
              </div>
              <div class="col-md-3">
                <label class="form-label">Uwagi</label>
                <input type="text" class="form-control" name="balance_notes" value="{$balanceFormData.notes|escape}">
              </div>
            </div>

            {if $balancePreview}
              <div class="card mt-4">
                <div class="card-header">
                  <strong>Podglad wyrownania</strong>
                </div>
                <div class="card-body">
                  <div class="row g-3 mb-3">
                    <div class="col-md-3">
                      <div class="small text-secondary">Cel {$balancePreview.requested_mode|escape}</div>
                      <div class="fw-semibold">{$balancePreview.requested_value|string_format:'%.2f'} PLN</div>
                    </div>
                    <div class="col-md-3">
                      <div class="small text-secondary">Osiagniete netto</div>
                      <div class="fw-semibold">{$balancePreview.actual_net|string_format:'%.2f'} PLN</div>
                    </div>
                    <div class="col-md-3">
                      <div class="small text-secondary">Osiagniete brutto</div>
                      <div class="fw-semibold">{$balancePreview.actual_gross|string_format:'%.2f'} PLN</div>
                    </div>
                    <div class="col-md-3">
                      <div class="small text-secondary">Suma sztuk</div>
                      <div class="fw-semibold">{$balancePreview.quantity|string_format:'%.0f'}</div>
                    </div>
                  </div>

                  <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                      <thead class="table-light">
                        <tr>
                          <th>Pozycja ksiegowa</th>
                          <th>Z jakiej FV</th>
                          <th class="text-end">Sztuki</th>
                          <th class="text-end">Netto / szt.</th>
                          <th class="text-end">Brutto / szt.</th>
                          <th class="text-end">Netto</th>
                          <th class="text-end">Brutto</th>
                        </tr>
                      </thead>
                      <tbody>
                        {foreach $balancePreview.preview_rows as $row}
                          <tr>
                            <td>
                              <div class="fw-semibold">{$row.canonical_name|escape}</div>
                              <div class="small text-secondary">{$row.original_name|default:'-'|escape}</div>
                            </td>
                            <td>
                              <div class="fw-semibold">{$row.source_document_number|default:'brak numeru'|escape}</div>
                              <div class="small text-secondary">{$row.source_supplier_name|default:'-'|escape}</div>
                              <div class="small text-secondary">{$row.source_date|default:'-'|escape}</div>
                            </td>
                            <td class="text-end">{$row.quantity|string_format:'%.0f'} {$row.unit|escape}</td>
                            <td class="text-end">{$row.unit_net|string_format:'%.2f'} PLN</td>
                            <td class="text-end">{$row.unit_gross|string_format:'%.2f'} PLN</td>
                            <td class="text-end">{$row.line_net|string_format:'%.2f'} PLN</td>
                            <td class="text-end">{$row.line_gross|string_format:'%.2f'} PLN</td>
                          </tr>
                        {/foreach}
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            {/if}

            <div class="mt-4 d-flex gap-2">
              {if $balancePreview}
                <button type="submit" class="btn btn-warning">Zapisz dokument</button>
                <a href="{$baseUrl}?controller=accountingwarehouse&action=issuebalancecreate" class="btn btn-outline-secondary">Nowe wyliczenie</a>
              {else}
                <button type="submit" class="btn btn-outline-warning">Przelicz podglad</button>
              {/if}
              <a href="{$baseUrl}?controller=accountingwarehouse&action=index" class="btn btn-outline-secondary">Wroc</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</main>
