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

      <style>
        .allegro-pagination-shell {
          display: flex;
          justify-content: flex-end;
        }

        .allegro-pagination-bar {
          display: flex;
          flex-wrap: wrap;
          gap: 0.75rem;
          align-items: center;
          justify-content: flex-end;
          width: 100%;
        }

        .allegro-pagination-panel {
          display: flex;
          flex-wrap: wrap;
          gap: 0.75rem;
          align-items: center;
          justify-content: flex-end;
          margin-left: auto;
          padding: 0.85rem 1rem;
          border: 1px solid rgba(0, 0, 0, 0.08);
          border-radius: 1rem;
          background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(248,249,250,0.98));
        }

        .allegro-pagination-buttons {
          display: flex;
          flex-wrap: wrap;
          gap: 0.35rem;
          justify-content: flex-end;
        }

        .allegro-topbar {
          display: flex;
          flex-wrap: wrap;
          gap: 0.75rem;
          align-items: center;
          justify-content: space-between;
        }

        .allegro-topbar-copy {
          display: flex;
          flex-direction: column;
          gap: 0.2rem;
        }

        .allegro-topbar-title {
          font-size: 1.2rem;
          font-weight: 700;
          line-height: 1.15;
          color: #17202a;
        }

        .allegro-topbar-meta {
          display: flex;
          flex-wrap: wrap;
          gap: 0.5rem;
          align-items: center;
          color: #6c757d;
          font-size: 0.92rem;
        }

        .allegro-topbar-chip {
          display: inline-flex;
          align-items: center;
          gap: 0.35rem;
          padding: 0.2rem 0.6rem;
          border-radius: 999px;
          background: #f3f5f7;
          color: #334155;
          font-weight: 600;
        }

        .allegro-queue-progress {
          display: flex;
          overflow: hidden;
          height: 0.6rem;
          border-radius: 999px;
          background: #e9ecef;
          box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.08);
        }

        .allegro-queue-progress-part {
          height: 100%;
          min-width: 0;
        }

        .allegro-queue-progress-part.is-pending,
        .allegro-queue-progress-part.is-retry {
          background: #f59e0b;
        }

        .allegro-queue-progress-part.is-processing {
          background: #0d6efd;
        }

        .allegro-queue-progress-part.is-done {
          background: #198754;
        }

        .allegro-queue-progress-part.is-error {
          background: #dc3545;
        }

        .allegro-queue-mini {
          border: 1px solid rgba(15, 23, 42, 0.08);
          border-radius: 0.85rem;
          background:
            radial-gradient(circle at top right, rgba(13, 110, 253, 0.10), transparent 36%),
            linear-gradient(180deg, rgba(255,255,255,0.98), rgba(248,249,250,0.98));
        }

        .allegro-queue-mini-total {
          font-size: 1.55rem;
          font-weight: 700;
          line-height: 1;
          color: #17202a;
        }

        .allegro-queue-mini-percent {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          min-width: 3.2rem;
          padding: 0.22rem 0.55rem;
          border-radius: 999px;
          background: #eef2f6;
          color: #334155;
          font-size: 0.82rem;
          font-weight: 700;
        }

        .allegro-queue-mini-meta {
          display: flex;
          flex-wrap: wrap;
          gap: 0.45rem;
          margin-top: 0.7rem;
          font-size: 0.82rem;
        }

        .allegro-queue-mini-link {
          color: inherit;
          text-decoration: none;
        }

        .allegro-queue-mini-link:hover {
          text-decoration: underline;
        }
      </style>

      {assign var=queueTotal value=$queueStats.pending+$queueStats.processing+$queueStats.done+$queueStats.error+$queueStats.retry}
      {assign var=queueRemaining value=$queueStats.pending+$queueStats.retry+$queueStats.processing}
      {if $queueTotal > 0}
        {assign var=queuePendingPercent value=($queueStats.pending*100)/$queueTotal}
        {assign var=queueProcessingPercent value=($queueStats.processing*100)/$queueTotal}
        {assign var=queueDonePercent value=($queueStats.done*100)/$queueTotal}
        {assign var=queueErrorPercent value=($queueStats.error*100)/$queueTotal}
        {assign var=queueRetryPercent value=($queueStats.retry*100)/$queueTotal}
      {else}
        {assign var=queuePendingPercent value=0}
        {assign var=queueProcessingPercent value=0}
        {assign var=queueDonePercent value=0}
        {assign var=queueErrorPercent value=0}
        {assign var=queueRetryPercent value=0}
      {/if}
      {assign var=queueStatusFilter value=$filters.queue_status|default:''}
      <div class="row g-3 mb-4">
        <div class="col-xl-2 col-md-6">
          <div class="card h-100">
            <div class="card-body">
              <div class="text-secondary small">Wszystkie produkty</div>
              <div class="display-6 fw-semibold">{$stats.all}</div>
            </div>
          </div>
        </div>
        <div class="col-xl-2 col-md-6">
          <div class="card h-100">
            <div class="card-body">
              <div class="text-secondary small">Aktywne</div>
              <div class="display-6 fw-semibold text-success">{$stats.active}</div>
            </div>
          </div>
        </div>
        <div class="col-xl-2 col-md-6">
          <div class="card h-100">
            <div class="card-body">
              <div class="text-secondary small">Nieaktywne</div>
              <div class="display-6 fw-semibold text-secondary">{$stats.inactive}</div>
            </div>
          </div>
        </div>
        <div class="col-xl-2 col-md-6">
          <div class="card h-100">
            <div class="card-body">
              <div class="text-secondary small">Powiazane</div>
              <div class="display-6 fw-semibold text-primary">{$stats.linked}</div>
            </div>
          </div>
        </div>
        <div class="col-xl-2 col-md-6">
          <div class="card h-100">
            <div class="card-body">
              <div class="text-secondary small">Bez magazynu</div>
              <div class="display-6 fw-semibold text-warning">{$stats.unlinked}</div>
            </div>
          </div>
        </div>
        <div class="col-xl-2 col-md-6">
          <div class="card h-100 allegro-queue-mini">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <div>
                  <div class="text-secondary small">Kolejka</div>
                  <div class="allegro-queue-mini-total">{$queueRemaining}</div>
                </div>
                <span class="allegro-queue-mini-percent">{$queueDonePercent|string_format:'%.0f'}%</span>
              </div>
              <div class="small text-secondary mb-2">
                Zostalo {$queueRemaining},
                <a href="{$baseUrl}?controller=erli&action=index&page=1&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&status={$filters.status|escape:'url'}&queue_status=done&linked={$filters.linked|escape:'url'}&error_query={$filters.error_query|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}" class="allegro-queue-mini-link text-success">gotowe {$queueStats.done}</a>
              </div>
              <div class="allegro-queue-progress" aria-label="Stan kolejki Erli">
                {if $queuePendingPercent > 0}<div class="allegro-queue-progress-part is-pending" style="width: {$queuePendingPercent|string_format:'%.2f'}%;"></div>{/if}
                {if $queueRetryPercent > 0}<div class="allegro-queue-progress-part is-retry" style="width: {$queueRetryPercent|string_format:'%.2f'}%;"></div>{/if}
                {if $queueProcessingPercent > 0}<div class="allegro-queue-progress-part is-processing" style="width: {$queueProcessingPercent|string_format:'%.2f'}%;"></div>{/if}
                {if $queueDonePercent > 0}<div class="allegro-queue-progress-part is-done" style="width: {$queueDonePercent|string_format:'%.2f'}%;"></div>{/if}
                {if $queueErrorPercent > 0}<div class="allegro-queue-progress-part is-error" style="width: {$queueErrorPercent|string_format:'%.2f'}%;"></div>{/if}
              </div>
              <div class="allegro-queue-mini-meta">
                <a href="{$baseUrl}?controller=erli&action=index&page=1&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&status={$filters.status|escape:'url'}&queue_status=pending&linked={$filters.linked|escape:'url'}&error_query={$filters.error_query|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}" class="allegro-queue-mini-link text-warning">Oczekuje: {$queueStats.pending}</a>
                <a href="{$baseUrl}?controller=erli&action=index&page=1&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&status={$filters.status|escape:'url'}&queue_status=retry&linked={$filters.linked|escape:'url'}&error_query={$filters.error_query|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}" class="allegro-queue-mini-link text-warning">Ponow: {$queueStats.retry}</a>
                <a href="{$baseUrl}?controller=erli&action=index&page=1&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&status={$filters.status|escape:'url'}&queue_status=error&linked={$filters.linked|escape:'url'}&error_query={$filters.error_query|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}" class="allegro-queue-mini-link text-danger">Blad: {$queueStats.error}</a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
          <h3 class="card-title mb-0">Synchronizacja i kolejka</h3>
          <div class="d-flex flex-wrap gap-2">
            <a href="{$baseUrl}?controller=administration&action=automation" class="btn btn-sm btn-outline-secondary">Zarzadzaj kontami w administracji</a>
            <a href="{$baseUrl}?controller=erli&action=sync" class="btn btn-sm btn-outline-primary">Pobierz produkty z Erli</a>
            <a href="{$baseUrl}?controller=erli&action=processqueue" class="btn btn-sm btn-outline-success">Uruchom worker kolejki</a>
          </div>
        </div>
        <div class="card-body">
          <div class="alert alert-light border small text-secondary">
            Konfiguracja kont Erli jest dostepna tylko w <a href="{$baseUrl}?controller=administration&action=automation" class="alert-link">Administracji</a>. Modul pobiera produkty z Erli przez <code>POST /products/_search</code>, zapisuje lokalny snapshot do zarzadzania i wysyla zmiany do istniejacych ofert przez <code>PATCH /products/{literal}{externalId}{/literal}</code>. Magazyn sluzy tylko jako dodatkowe powiazanie po SKU.
          </div>

          <div class="d-flex gap-2">
            <form method="post" action="{$baseUrl}?controller=erli&action=clearqueue">
              <input type="hidden" name="mode" value="statuses">
              <button type="submit" class="btn btn-sm btn-outline-secondary">Wyczysc statusy</button>
            </form>
            <form method="post" action="{$baseUrl}?controller=erli&action=clearqueue">
              <input type="hidden" name="mode" value="all">
              <button type="submit" class="btn btn-sm btn-outline-danger">Usun cala kolejke</button>
            </form>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">
          <div class="allegro-topbar">
            <div class="allegro-topbar-copy">
              <div class="allegro-topbar-title">Produkty Erli</div>
              <div class="allegro-topbar-meta">
                <span class="allegro-topbar-chip">Lacznie {$totalProducts} produktow</span>
                <span>strona {$page} z {$totalPages}</span>
              </div>
            </div>
          </div>
        </div>
        <div class="card-body border-bottom">
          <form method="get" action="{$baseUrl}" class="row g-2" id="erliFiltersForm">
            <input type="hidden" name="controller" value="erli">
            <input type="hidden" name="action" value="index">
            <input type="hidden" name="sort_by" value="{$sortBy|escape}">
            <input type="hidden" name="sort_dir" value="{$sortDir|escape}">
            <input type="hidden" name="queue_status" value="{$queueStatusFilter|escape}">
            <div class="col-xl-2 col-md-6">
              <label class="form-label">Konto</label>
              <select name="account_id" class="form-select">
                <option value="">Wszystkie</option>
                {foreach $accounts as $account}
                  <option value="{$account.id}"{if $filters.account_id|default:'' == $account.id} selected{/if}>{$account.name|escape}</option>
                {/foreach}
              </select>
            </div>
            <div class="col-xl-2 col-md-6">
              <label class="form-label">Fraza</label>
              <input type="text" name="q" class="form-control" value="{$filters.q|default:''|escape}" placeholder="external id, sku, tytul">
            </div>
            <div class="col-xl-2 col-md-6">
              <label class="form-label">SKU</label>
              <input type="text" name="sku" class="form-control" value="{$filters.sku|default:''|escape}">
            </div>
            <div class="col-xl-2 col-md-6">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <option value="">Wszystkie</option>
                <option value="active"{if $filters.status|default:'' eq 'active'} selected{/if}>active</option>
                <option value="inactive"{if $filters.status|default:'' eq 'inactive'} selected{/if}>inactive</option>
              </select>
            </div>
            <div class="col-xl-2 col-md-6">
              <label class="form-label">Powiazanie</label>
              <select name="linked" class="form-select">
                <option value="">Wszystkie</option>
                <option value="1"{if $filters.linked|default:'' eq '1'} selected{/if}>Powiazane</option>
                <option value="0"{if $filters.linked|default:'' eq '0'} selected{/if}>Bez magazynu</option>
              </select>
            </div>
            <div class="col-xl-2 col-md-6">
              <label class="form-label">Kolejka</label>
              <select name="queue_status_visible" class="form-select" onchange="this.form.querySelector('input[name=queue_status]').value=this.value;">
                <option value=""{if $queueStatusFilter eq ''} selected{/if}>Wszystkie</option>
                <option value="pending"{if $queueStatusFilter eq 'pending'} selected{/if}>pending</option>
                <option value="retry"{if $queueStatusFilter eq 'retry'} selected{/if}>retry</option>
                <option value="processing"{if $queueStatusFilter eq 'processing'} selected{/if}>processing</option>
                <option value="done"{if $queueStatusFilter eq 'done'} selected{/if}>done</option>
                <option value="error"{if $queueStatusFilter eq 'error'} selected{/if}>error</option>
              </select>
            </div>
            <div class="col-xl-4 col-md-6">
              <label class="form-label">Blad kolejki</label>
              <input type="text" name="error_query" class="form-control" value="{$filters.error_query|default:''|escape}" placeholder="fragment komunikatu">
            </div>
            <div class="col-xl-3 col-md-6">
              <label class="form-label">Stan magazyn od / do</label>
              <div class="row g-2">
                <div class="col-6">
                  <input type="number" min="0" step="1" name="warehouse_quantity_from" class="form-control" value="{$filters.warehouse_quantity_from|default:''|escape}" placeholder="od">
                </div>
                <div class="col-6">
                  <input type="number" min="0" step="1" name="warehouse_quantity_to" class="form-control" value="{$filters.warehouse_quantity_to|default:''|escape}" placeholder="do">
                </div>
              </div>
            </div>
            <div class="col-xl-2 col-md-6">
              <label class="form-label">Na strone</label>
              <select name="per_page" class="form-select">
                <option value="50"{if $perPage == 50} selected{/if}>50</option>
                <option value="100"{if $perPage == 100} selected{/if}>100</option>
                <option value="200"{if $perPage == 200} selected{/if}>200</option>
                <option value="5000"{if $perPage == 5000} selected{/if}>5000</option>
                <option value="10000"{if $perPage == 10000} selected{/if}>10000</option>
              </select>
            </div>
            <div class="col-xl-3 col-md-6 d-flex align-items-end gap-2">
              <button type="submit" class="btn btn-primary">Filtruj</button>
              <a href="{$baseUrl}?controller=erli&action=index" class="btn btn-outline-secondary">Reset</a>
            </div>
          </form>
        </div>

        <div class="card-body border-bottom">
          <form method="post" action="{$baseUrl}?controller=erli&action=queue" id="erli-bulk-form">
            <input type="hidden" name="return_url" value="{$currentListUrl|escape}">
            <input type="hidden" name="account_id" value="{$filters.account_id|default:''|escape}">
            <input type="hidden" name="q" value="{$filters.q|default:''|escape}">
            <input type="hidden" name="sku" value="{$filters.sku|default:''|escape}">
            <input type="hidden" name="status" value="{$filters.status|default:''|escape}">
            <input type="hidden" name="queue_status" value="{$queueStatusFilter|escape}">
            <input type="hidden" name="error_query" value="{$filters.error_query|default:''|escape}">
            <input type="hidden" name="linked" value="{$filters.linked|default:''|escape}">
            <input type="hidden" name="warehouse_quantity_from" value="{$filters.warehouse_quantity_from|default:''|escape}">
            <input type="hidden" name="warehouse_quantity_to" value="{$filters.warehouse_quantity_to|default:''|escape}">

            <div class="row g-3">
              <div class="col-xl-3 col-md-6">
                <label class="form-label">Operacja</label>
                <select name="operation" class="form-select">
                  <option value="sync_product">Synchronizuj produkt</option>
                  <option value="set_price">Ustaw cene recznie</option>
                  <option value="set_price_from_product">Cena z magazynu</option>
                  <option value="set_title">Ustaw tytul</option>
                  <option value="set_title_from_product">Tytul z magazynu</option>
                  <option value="replace_title">Podmien fragment tytulu</option>
                  <option value="set_description">Ustaw opis</option>
                  <option value="replace_description">Podmien fragment opisu</option>
                  <option value="set_stock_from_product">Stan z magazynu</option>
                  <option value="activate_product">Wznow / aktywuj</option>
                  <option value="deactivate_product">Zakoncz / dezaktywuj</option>
                  <option value="clear_queue">Usun z kolejki</option>
                  <option value="remove_from_system">Usun lokalnie z systemu</option>
                </select>
              </div>
              <div class="col-xl-2 col-md-6">
                <label class="form-label">Wartosc</label>
                <input type="text" name="value" class="form-control" placeholder="cena, tytul, opis">
              </div>
              <div class="col-xl-2 col-md-6">
                <label class="form-label">Szukaj</label>
                <input type="text" name="search" class="form-control" placeholder="szukany tekst">
              </div>
              <div class="col-xl-2 col-md-6">
                <label class="form-label">Zamien</label>
                <input type="text" name="replace" class="form-control" placeholder="nowy tekst">
              </div>
              <div class="col-xl-3 col-md-6">
                <label class="form-label d-block">Zakres</label>
                <div class="d-flex flex-wrap gap-3 pt-2">
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="selection_scope" id="selection_scope_filtered" value="filtered">
                    <label class="form-check-label" for="selection_scope_filtered">Wszystkie z filtrowania</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="selection_scope" id="selection_scope_selected" value="selected" checked>
                    <label class="form-check-label" for="selection_scope_selected">Tylko zaznaczone</label>
                  </div>
                </div>
              </div>
              <div class="col-xl-3 col-md-6">
                <label class="form-label">Limit wyboru dla filtra</label>
                <input type="number" name="selection_limit" class="form-control" min="1" step="1" value="1000">
              </div>
              <div class="col-xl-9 col-md-6 d-flex align-items-end justify-content-between flex-wrap gap-2">
                <div class="small text-secondary">
                  Akcje "z magazynu" dzialaja po powiazaniu live po SKU. Zaznaczone: <span id="erli-selected-counter">0</span>
                </div>
                <div class="d-flex flex-wrap gap-2">
                  <button type="button" class="btn btn-sm btn-outline-secondary" id="erli-select-page">Zaznacz strone</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" id="erli-clear-page">Wyczysc strone</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" id="erli-invert-page">Odwroc strone</button>
                  <button type="submit" class="btn btn-success">Dodaj do kolejki</button>
                </div>
              </div>
            </div>
          </form>
        </div>

        {if $totalPages > 1}
          <div class="card-body border-bottom">
            <div class="allegro-pagination-shell">
              <div class="allegro-pagination-bar">
                <div class="allegro-pagination-panel">
                  <div class="small text-secondary">Wyniki {$totalProducts} • strona {$page} z {$totalPages}</div>
                  <div class="allegro-pagination-buttons">
                    <a class="btn btn-sm btn-outline-secondary{if $page <= 1} disabled{/if}" href="{$baseUrl}?controller=erli&action=index&page=1&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&status={$filters.status|escape:'url'}&queue_status={$queueStatusFilter|escape:'url'}&linked={$filters.linked|escape:'url'}&error_query={$filters.error_query|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}">Pierwsza</a>
                    <a class="btn btn-sm btn-outline-secondary{if $page <= 1} disabled{/if}" href="{$baseUrl}?controller=erli&action=index&page={$prevPage}&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&status={$filters.status|escape:'url'}&queue_status={$queueStatusFilter|escape:'url'}&linked={$filters.linked|escape:'url'}&error_query={$filters.error_query|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}">Poprzednia</a>
                    {foreach $pageWindow as $pageItem}
                      {if $pageItem.type eq 'ellipsis'}
                        <span class="btn btn-sm btn-outline-secondary disabled">...</span>
                      {else}
                        <a class="btn btn-sm {if $pageItem.is_current}btn-primary{else}btn-outline-secondary{/if}" href="{$baseUrl}?controller=erli&action=index&page={$pageItem.value}&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&status={$filters.status|escape:'url'}&queue_status={$queueStatusFilter|escape:'url'}&linked={$filters.linked|escape:'url'}&error_query={$filters.error_query|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}">{$pageItem.value}</a>
                      {/if}
                    {/foreach}
                    <a class="btn btn-sm btn-outline-secondary{if $page >= $totalPages} disabled{/if}" href="{$baseUrl}?controller=erli&action=index&page={$nextPage}&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&status={$filters.status|escape:'url'}&queue_status={$queueStatusFilter|escape:'url'}&linked={$filters.linked|escape:'url'}&error_query={$filters.error_query|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}">Nastepna</a>
                    <a class="btn btn-sm btn-outline-secondary{if $page >= $totalPages} disabled{/if}" href="{$baseUrl}?controller=erli&action=index&page={$totalPages}&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&status={$filters.status|escape:'url'}&queue_status={$queueStatusFilter|escape:'url'}&linked={$filters.linked|escape:'url'}&error_query={$filters.error_query|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}">Ostatnia</a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        {/if}

        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-hover table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>ID</th>
                  <th><a href="{$sortUrls.images|escape}" class="link-dark text-decoration-none">Grafiki {if $sortIndicators.images eq 'asc'}&uarr;{elseif $sortIndicators.images eq 'desc'}&darr;{else}&harr;{/if}</a></th>
                  <th><a href="{$sortUrls.account|escape}" class="link-dark text-decoration-none">Konto {if $sortIndicators.account eq 'asc'}&uarr;{elseif $sortIndicators.account eq 'desc'}&darr;{else}&harr;{/if}</a></th>
                  <th><a href="{$sortUrls.title|escape}" class="link-dark text-decoration-none">Tytul {if $sortIndicators.title eq 'asc'}&uarr;{elseif $sortIndicators.title eq 'desc'}&darr;{else}&harr;{/if}</a></th>
                  <th><a href="{$sortUrls.sku|escape}" class="link-dark text-decoration-none">SKU {if $sortIndicators.sku eq 'asc'}&uarr;{elseif $sortIndicators.sku eq 'desc'}&darr;{else}&harr;{/if}</a></th>
                  <th><a href="{$sortUrls.category|escape}" class="link-dark text-decoration-none">Kategoria {if $sortIndicators.category eq 'asc'}&uarr;{elseif $sortIndicators.category eq 'desc'}&darr;{else}&harr;{/if}</a></th>
                  <th><a href="{$sortUrls.status|escape}" class="link-dark text-decoration-none">Status {if $sortIndicators.status eq 'asc'}&uarr;{elseif $sortIndicators.status eq 'desc'}&darr;{else}&harr;{/if}</a></th>
                  <th><a href="{$sortUrls.quantity|escape}" class="link-dark text-decoration-none">Erli {if $sortIndicators.quantity eq 'asc'}&uarr;{elseif $sortIndicators.quantity eq 'desc'}&darr;{else}&harr;{/if}</a></th>
                  <th><a href="{$sortUrls.warehouse_quantity|escape}" class="link-dark text-decoration-none">Mag. {if $sortIndicators.warehouse_quantity eq 'asc'}&uarr;{elseif $sortIndicators.warehouse_quantity eq 'desc'}&darr;{else}&harr;{/if}</a></th>
                  <th><a href="{$sortUrls.price|escape}" class="link-dark text-decoration-none">Cena {if $sortIndicators.price eq 'asc'}&uarr;{elseif $sortIndicators.price eq 'desc'}&darr;{else}&harr;{/if}</a></th>
                  <th><a href="{$sortUrls.queue_status|escape}" class="link-dark text-decoration-none">Kolejka {if $sortIndicators.queue_status eq 'asc'}&uarr;{elseif $sortIndicators.queue_status eq 'desc'}&darr;{else}&harr;{/if}</a></th>
                  <th><a href="{$sortUrls.synced|escape}" class="link-dark text-decoration-none">Sync {if $sortIndicators.synced eq 'asc'}&uarr;{elseif $sortIndicators.synced eq 'desc'}&darr;{else}&harr;{/if}</a></th>
                </tr>
              </thead>
              <tbody>
                {foreach $products as $product}
                  <tr class="js-erli-row {if $product.queue_meta.row_class|default:''}{$product.queue_meta.row_class|escape}{/if}">
                    <td>
                      <label class="d-flex align-items-center gap-2 mb-0">
                        <input type="checkbox" class="js-erli-select" name="selected_product_ids[]" value="{$product.id|escape}" form="erli-bulk-form">
                        <span>{$product.id|escape}</span>
                      </label>
                    </td>
                    <td>
                      {if $product.primary_image_url}
                        <img src="{$product.primary_image_url|escape}" alt="" loading="lazy" decoding="async" style="width:56px;height:56px;object-fit:cover;border-radius:8px;">
                      {else}
                        <div class="d-flex align-items-center justify-content-center rounded border bg-light text-secondary" style="width:56px;height:56px;">brak</div>
                      {/if}
                      <div class="small text-secondary mt-1">grafik: {$product.image_count|default:0|escape}</div>
                    </td>
                    <td>
                      <div class="fw-semibold">{$product.account_name|escape}</div>
                      <div class="small text-secondary">{$product.external_id|escape}</div>
                    </td>
                    <td>
                      <div><a href="{$baseUrl}?controller=erli&action=product&id={$product.id|escape}" class="text-decoration-none">{$product.effective_title|default:'-'|truncate:90|escape}</a></div>
                      {if $product.queue_meta.operation|default:'' neq ''}
                        <div class="small text-secondary">operacja: {$product.queue_meta.operation|escape}</div>
                      {/if}
                      {if $product.last_error_message}
                        <div class="small text-danger mt-1">{$product.last_error_message|truncate:120|escape}</div>
                      {/if}
                    </td>
                    <td>
                      <div><code>{$product.sku|default:'-'|escape}</code></div>
                      <div class="small text-secondary">mag: {$product.warehouse_sku|default:'-'|escape}</div>
                    </td>
                    <td>{$product.category_name|default:'-'|escape}</td>
                    <td>
                      {if $product.effective_status eq 'active'}
                        <span class="badge text-bg-success">active</span>
                      {else}
                        <span class="badge text-bg-secondary">inactive</span>
                      {/if}
                    </td>
                    <td>{$product.effective_quantity|default:0|escape}</td>
                    <td>{$product.warehouse_quantity|default:'-'|escape}</td>
                    <td>{$product.effective_price|string_format:"%.2f"} zl</td>
                    <td>
                      {if $product.queue_meta.has_queue_entry|default:false}
                        <span class="badge {$product.queue_meta.badge_class|escape}">{$product.queue_meta.status_label|escape}</span>
                        {if $product.queue_meta.error_message|default:'' neq ''}
                          <div class="small text-danger mt-1">{$product.queue_meta.error_message|truncate:90|escape}</div>
                        {/if}
                      {else}
                        <span class="text-secondary">-</span>
                      {/if}
                    </td>
                    <td>
                      <div>{$product.last_synced_at|default:'-'|escape}</div>
                      <div class="small text-secondary">aktualizacja: {$product.remote_updated_at|default:'-'|escape}</div>
                    </td>
                  </tr>
                {foreachelse}
                  <tr><td colspan="12" class="text-center py-4 text-secondary">Brak produktow Erli. Najpierw pobierz je z API Erli.</td></tr>
                {/foreach}
              </tbody>
            </table>
          </div>
        </div>

        {if $totalPages > 1}
          <div class="card-footer">
            <div class="allegro-pagination-shell">
              <div class="allegro-pagination-bar">
                <div class="allegro-pagination-panel">
                  <div class="small text-secondary">Wyniki {$totalProducts} • strona {$page} z {$totalPages}</div>
                  <div class="allegro-pagination-buttons">
                    <a class="btn btn-sm btn-outline-secondary{if $page <= 1} disabled{/if}" href="{$baseUrl}?controller=erli&action=index&page={$prevPage}&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&status={$filters.status|escape:'url'}&queue_status={$queueStatusFilter|escape:'url'}&linked={$filters.linked|escape:'url'}&error_query={$filters.error_query|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}">Poprzednia</a>
                    <a class="btn btn-sm btn-outline-secondary{if $page >= $totalPages} disabled{/if}" href="{$baseUrl}?controller=erli&action=index&page={$nextPage}&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&status={$filters.status|escape:'url'}&queue_status={$queueStatusFilter|escape:'url'}&linked={$filters.linked|escape:'url'}&error_query={$filters.error_query|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}">Nastepna</a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        {/if}
      </div>
    </div>
  </div>
</main>

<script>
  (function () {
    var bulkForm = document.getElementById('erli-bulk-form');
    if (!bulkForm) {
      return;
    }

    var checkboxes = Array.prototype.slice.call(document.querySelectorAll('.js-erli-select'));
    var counter = document.getElementById('erli-selected-counter');
    var selectPage = document.getElementById('erli-select-page');
    var clearPage = document.getElementById('erli-clear-page');
    var invertPage = document.getElementById('erli-invert-page');
    var rows = Array.prototype.slice.call(document.querySelectorAll('.js-erli-row'));

    function updateCounter() {
      var count = checkboxes.filter(function (item) {
        return item.checked;
      }).length;

      if (counter) {
        counter.textContent = String(count);
      }
    }

    function setAll(state) {
      checkboxes.forEach(function (item) {
        item.checked = !!state;
      });
      updateCounter();
    }

    function invertAll() {
      checkboxes.forEach(function (item) {
        item.checked = !item.checked;
      });
      updateCounter();
    }

    checkboxes.forEach(function (item) {
      item.addEventListener('change', updateCounter);
    });

    rows.forEach(function (row) {
      row.style.cursor = 'pointer';
      row.addEventListener('click', function (event) {
        var interactive = event.target.closest('a, button, input, textarea, select, label');
        if (interactive) {
          return;
        }

        var checkbox = row.querySelector('.js-erli-select');
        if (checkbox) {
          checkbox.checked = !checkbox.checked;
          updateCounter();
        }
      });
    });

    if (selectPage) {
      selectPage.addEventListener('click', function () {
        setAll(true);
      });
    }

    if (clearPage) {
      clearPage.addEventListener('click', function () {
        setAll(false);
      });
    }

    if (invertPage) {
      invertPage.addEventListener('click', function () {
        invertAll();
      });
    }

    bulkForm.addEventListener('submit', function (event) {
      var selectedScope = bulkForm.querySelector('input[name="selection_scope"]:checked');
      var scope = selectedScope ? selectedScope.value : 'selected';
      var selectedCount = checkboxes.filter(function (item) { return item.checked; }).length;

      if (scope === 'selected' && selectedCount === 0) {
        event.preventDefault();
        window.alert('Zaznacz przynajmniej jeden produkt albo przelacz zakres na "Wszystkie z filtrowania".');
      }
    });

    updateCounter();
  })();
</script>
