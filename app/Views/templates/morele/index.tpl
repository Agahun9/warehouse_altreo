<main class="app-main">
  <style>
    .morele-id-cell {
      align-items: center;
      display: flex;
      gap: 6px;
      min-width: 120px;
    }
    .morele-thumb {
      background: #fff;
      border: 1px solid rgba(0, 0, 0, 0.12);
      border-radius: 4px;
      display: inline-block;
      flex: 0 0 28px;
      height: 28px;
      overflow: hidden;
      width: 28px;
    }
    .morele-thumb img {
      display: block;
      height: 100%;
      object-fit: contain;
      width: 100%;
    }
  </style>
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

      {assign var=queueTotal value=$queueStats.pending+$queueStats.processing+$queueStats.done+$queueStats.error+$queueStats.retry}
      <div class="row g-3 mb-3">
        <div class="col-md-3">
          <div class="card h-100"><div class="card-body">
            <div class="text-secondary small">Wszystkie oferty</div>
            <div class="display-6 fw-semibold">{$stats.all}</div>
          </div></div>
        </div>
        <div class="col-md-3">
          <div class="card h-100"><div class="card-body">
            <div class="text-secondary small">Aktywne</div>
            <div class="display-6 fw-semibold text-success">{$stats.active}</div>
          </div></div>
        </div>
        <div class="col-md-3">
          <div class="card h-100"><div class="card-body">
            <div class="text-secondary small">Nieaktywne</div>
            <div class="display-6 fw-semibold text-secondary">{$stats.inactive}</div>
          </div></div>
        </div>
        <div class="col-md-3">
          <div class="card h-100"><div class="card-body">
            <div class="text-secondary small">Kolejka</div>
            <div class="display-6 fw-semibold">{$queueTotal}</div>
            <div class="small text-muted">pending {$queueStats.pending}, retry {$queueStats.retry}, error {$queueStats.error}</div>
          </div></div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-body">
          <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <form method="get" action="{$baseUrl}" class="row g-2 flex-grow-1">
              <input type="hidden" name="controller" value="morele">
              <input type="hidden" name="action" value="index">
              <div class="col-md-4">
                <input type="search" name="q" value="{$filters.q|escape}" class="form-control" placeholder="Szukaj po nazwie, ID, SKU">
              </div>
              <div class="col-md-3">
                <input type="search" name="sku" value="{$filters.sku|escape}" class="form-control" placeholder="SKU">
              </div>
              <div class="col-md-2">
                <select name="status" class="form-select">
                  <option value="">Status</option>
                  <option value="active" {if $filters.status eq 'active'}selected{/if}>Aktywne</option>
                  <option value="inactive" {if $filters.status eq 'inactive'}selected{/if}>Nieaktywne</option>
                </select>
              </div>
              <div class="col-md-2">
                <select name="per_page" class="form-select">
                  <option value="50" {if $perPage eq 50}selected{/if}>50</option>
                  <option value="100" {if $perPage eq 100}selected{/if}>100</option>
                  <option value="200" {if $perPage eq 200}selected{/if}>200</option>
                  <option value="5000" {if $perPage eq 5000}selected{/if}>5000</option>
                  <option value="10000" {if $perPage eq 10000}selected{/if}>10000</option>
                </select>
              </div>
              <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i></button>
              </div>
            </form>
            <form method="post" action="{$baseUrl}?controller=morele&action=sync" class="d-inline">
              <button type="submit" class="btn btn-outline-primary"><i class="bi bi-arrow-repeat"></i> Synchronizuj</button>
            </form>
            <form method="post" action="{$baseUrl}?controller=morele&action=processqueue" class="d-inline">
              <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-play-fill"></i> Przetworz kolejke</button>
            </form>
          </div>
        </div>
      </div>

      <form method="post" action="{$baseUrl}?controller=morele&action=queue" id="moreleBulkForm">
        <input type="hidden" name="return_url" value="{$currentListUrl|escape:'html'}">
        <div class="card">
          <div class="card-header">
            <div class="d-flex flex-wrap gap-2 align-items-end">
              <div>
                <label class="form-label mb-1">Zakres</label>
                <select name="selection_scope" class="form-select form-select-sm">
                  <option value="selected">Zaznaczone</option>
                  <option value="filtered">Biezacy filtr</option>
                </select>
              </div>
              <div>
                <label class="form-label mb-1">Operacja</label>
                <select name="operation" class="form-select form-select-sm" id="moreleOperation">
                  <option value="set_price">Ustaw cene</option>
                  <option value="set_price_from_product">Cena z magazynu</option>
                </select>
              </div>
              <div>
                <label class="form-label mb-1">Wartosc</label>
                <input type="text" name="value" class="form-control form-control-sm" placeholder="np. 3865.00">
              </div>
              <div>
                <label class="form-label mb-1">Limit filtra</label>
                <input type="number" name="selection_limit" value="1000" min="1" max="5000" class="form-control form-control-sm">
              </div>
              <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle"></i> Dodaj do kolejki</button>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th style="width:36px;"><input type="checkbox" id="moreleSelectAll"></th>
                  <th>ID</th>
                  <th>Nazwa</th>
                  <th>SKU</th>
                  <th>Status</th>
                  <th class="text-end">Stan</th>
                  <th class="text-end">Cena</th>
                  <th>Kolejka</th>
                  <th>Sync</th>
                </tr>
              </thead>
              <tbody>
                {foreach from=$offers item=offer}
                  <tr>
                    <td><input type="checkbox" name="selected_offer_ids[]" value="{$offer.id}" class="morele-offer-checkbox"></td>
                    <td>
                      <div class="morele-id-cell">
                        {if $offer.thumbnail_url|default:'' neq ''}
                          <span class="morele-thumb">
                            <img src="{$offer.thumbnail_url|escape:'html'}" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                          </span>
                        {/if}
                        <a href="{$baseUrl}?controller=morele&action=offer&id={$offer.id}">{$offer.external_id|escape}</a>
                      </div>
                    </td>
                    <td>{$offer.product_name|default:'-'|escape}</td>
                    <td><code>{$offer.sku|default:'-'|escape}</code></td>
                    <td>
                      {if $offer.effective_status eq 'active'}
                        <span class="badge text-bg-success">active</span>
                      {else}
                        <span class="badge text-bg-secondary">{$offer.effective_status|default:'inactive'|escape}</span>
                      {/if}
                    </td>
                    <td class="text-end">{$offer.effective_quantity|default:'-'|escape}</td>
                    <td class="text-end">{if $offer.effective_price neq ''}{$offer.effective_price|number_format:2:',':'.'} zl{else}-{/if}</td>
                    <td>{$offer.queue_status|default:'-'|escape}</td>
                    <td class="small text-muted">{$offer.last_synced_at|default:'-'|escape}</td>
                  </tr>
                {foreachelse}
                  <tr><td colspan="9" class="text-center text-muted py-4">Brak ofert Morele.</td></tr>
                {/foreach}
              </tbody>
            </table>
          </div>
          <div class="card-footer d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <div class="text-muted small">Wyniki: {$totalOffers}, strona {$page}/{$totalPages}</div>
            <div class="btn-group">
              <a class="btn btn-sm btn-outline-secondary {if $page <= 1}disabled{/if}" href="{$baseUrl}?controller=morele&action=index&page={$prevPage}&per_page={$perPage}">Poprzednia</a>
              <a class="btn btn-sm btn-outline-secondary {if $page >= $totalPages}disabled{/if}" href="{$baseUrl}?controller=morele&action=index&page={$nextPage}&per_page={$perPage}">Nastepna</a>
            </div>
          </div>
        </div>
      </form>

      <form method="post" action="{$baseUrl}?controller=morele&action=clearqueue" class="mt-3 d-flex gap-2">
        <button type="submit" name="mode" value="statuses" class="btn btn-sm btn-outline-secondary">Wyczysc zakonczone statusy kolejki</button>
        <button type="submit" name="mode" value="all" class="btn btn-sm btn-outline-danger" onclick="return confirm('Usunac cala kolejke Morele?');">Wyczysc cala kolejke</button>
      </form>
    </div>
  </div>
</main>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var all = document.getElementById('moreleSelectAll');
    if (!all) return;
    all.addEventListener('change', function () {
      document.querySelectorAll('.morele-offer-checkbox').forEach(function (checkbox) {
        checkbox.checked = all.checked;
      });
    });
  });
</script>
