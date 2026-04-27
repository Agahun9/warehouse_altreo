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

        .allegro-topbar-actions {
          display: flex;
          flex-wrap: wrap;
          gap: 0.5rem;
          justify-content: flex-end;
          margin-left: auto;
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
        <div class="col-xl-3 col-md-6">
          <div class="card h-100">
            <div class="card-body">
              <div class="text-secondary small">Wszystkie oferty</div>
              <div class="display-6 fw-semibold">{$stats.all}</div>
            </div>
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="card h-100">
            <div class="card-body">
              <div class="text-secondary small">Aktywne</div>
              <div class="display-6 fw-semibold text-success">{$stats.active}</div>
            </div>
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="card h-100">
            <div class="card-body">
              <div class="text-secondary small">Zakonczone</div>
              <div class="display-6 fw-semibold text-warning">{$stats.ended}</div>
            </div>
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="card h-100">
            <div class="card-body">
              <div class="text-secondary small">Nieaktywne</div>
              <div class="display-6 fw-semibold text-secondary">{$stats.inactive}</div>
            </div>
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
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
                <a href="{$baseUrl}?controller=allegro&action=index&page=1&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&status={$filters.status|escape:'url'}&duplicates={$filters.duplicates|escape:'url'}&queue_status=done&linked={$filters.linked|escape:'url'}&market={$filters.market|escape:'url'}&invoice={$filters.invoice|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}" class="allegro-queue-mini-link text-success">gotowe {$queueStats.done}</a>
              </div>
              <div class="allegro-queue-progress" aria-label="Stan kolejki Allegro">
                {if $queuePendingPercent > 0}<div class="allegro-queue-progress-part is-pending" style="width: {$queuePendingPercent|string_format:'%.2f'}%;"></div>{/if}
                {if $queueRetryPercent > 0}<div class="allegro-queue-progress-part is-retry" style="width: {$queueRetryPercent|string_format:'%.2f'}%;"></div>{/if}
                {if $queueProcessingPercent > 0}<div class="allegro-queue-progress-part is-processing" style="width: {$queueProcessingPercent|string_format:'%.2f'}%;"></div>{/if}
                {if $queueDonePercent > 0}<div class="allegro-queue-progress-part is-done" style="width: {$queueDonePercent|string_format:'%.2f'}%;"></div>{/if}
                {if $queueErrorPercent > 0}<div class="allegro-queue-progress-part is-error" style="width: {$queueErrorPercent|string_format:'%.2f'}%;"></div>{/if}
              </div>
              <div class="allegro-queue-mini-meta">
                <a href="{$baseUrl}?controller=allegro&action=index&page=1&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&status={$filters.status|escape:'url'}&duplicates={$filters.duplicates|escape:'url'}&queue_status=pending&linked={$filters.linked|escape:'url'}&market={$filters.market|escape:'url'}&invoice={$filters.invoice|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}" class="allegro-queue-mini-link text-warning">Oczekuje: {$queueStats.pending}</a>
                <a href="{$baseUrl}?controller=allegro&action=index&page=1&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&status={$filters.status|escape:'url'}&duplicates={$filters.duplicates|escape:'url'}&queue_status=retry&linked={$filters.linked|escape:'url'}&market={$filters.market|escape:'url'}&invoice={$filters.invoice|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}" class="allegro-queue-mini-link text-warning">Ponów: {$queueStats.retry}</a>
                <a href="{$baseUrl}?controller=allegro&action=index&page=1&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&status={$filters.status|escape:'url'}&duplicates={$filters.duplicates|escape:'url'}&queue_status=error&linked={$filters.linked|escape:'url'}&market={$filters.market|escape:'url'}&invoice={$filters.invoice|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}" class="allegro-queue-mini-link text-danger">Błąd: {$queueStats.error}</a>
              </div>
              <div class="d-flex flex-wrap gap-2 mt-3">
                <form method="post" action="{$baseUrl}?controller=allegro&action=clearqueue" onsubmit="return confirm('Wyczyścić statusy gotowe, błędy i ponów? Oczekujące zostaną.');">
                  <input type="hidden" name="mode" value="statuses">
                  <button type="submit" class="btn btn-sm btn-outline-secondary">Wyczyść statusy</button>
                </form>
                <form method="post" action="{$baseUrl}?controller=allegro&action=clearqueue" onsubmit="return confirm('Usunąć całą kolejkę Allegro?');">
                  <input type="hidden" name="mode" value="all">
                  <button type="submit" class="btn btn-sm btn-outline-danger">Usuń całą kolejkę</button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card allegro-queue-card mb-4 d-none">
        <div class="card-body">
          <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-2">
            <div>
              <div class="small text-secondary">Kolejka zmian Allegro</div>
              <div class="fw-semibold">{$queueTotal} zadań łącznie</div>
            </div>
            <div class="small text-secondary">Do zrobienia teraz: <span class="fw-semibold text-dark">{$queueStats.pending+$queueStats.retry}</span></div>
          </div>

          <div class="allegro-queue-progress mb-2" aria-label="Stan kolejki Allegro">
            {if $queuePendingPercent > 0}<div class="allegro-queue-progress-part is-pending" style="width: {$queuePendingPercent|string_format:'%.2f'}%;"></div>{/if}
            {if $queueRetryPercent > 0}<div class="allegro-queue-progress-part is-retry" style="width: {$queueRetryPercent|string_format:'%.2f'}%;"></div>{/if}
            {if $queueProcessingPercent > 0}<div class="allegro-queue-progress-part is-processing" style="width: {$queueProcessingPercent|string_format:'%.2f'}%;"></div>{/if}
            {if $queueDonePercent > 0}<div class="allegro-queue-progress-part is-done" style="width: {$queueDonePercent|string_format:'%.2f'}%;"></div>{/if}
            {if $queueErrorPercent > 0}<div class="allegro-queue-progress-part is-error" style="width: {$queueErrorPercent|string_format:'%.2f'}%;"></div>{/if}
          </div>

          <div class="row g-3">
            <div class="col-xl col-md-4 col-6">
              <div class="allegro-queue-stat">
                <div class="small text-secondary">Oczekuje</div>
                <div class="fs-4 fw-semibold text-warning"><a href="{$baseUrl}?controller=allegro&action=index&page=1&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&status={$filters.status|escape:'url'}&duplicates={$filters.duplicates|escape:'url'}&queue_status=pending&linked={$filters.linked|escape:'url'}&market={$filters.market|escape:'url'}&invoice={$filters.invoice|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}" class="text-warning text-decoration-none">{$queueStats.pending}</a></div>
              </div>
            </div>
            <div class="col-xl col-md-4 col-6">
              <div class="allegro-queue-stat">
                <div class="small text-secondary">Ponów</div>
                <div class="fs-4 fw-semibold text-warning"><a href="{$baseUrl}?controller=allegro&action=index&page=1&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&status={$filters.status|escape:'url'}&duplicates={$filters.duplicates|escape:'url'}&queue_status=retry&linked={$filters.linked|escape:'url'}&market={$filters.market|escape:'url'}&invoice={$filters.invoice|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}" class="text-warning text-decoration-none">{$queueStats.retry}</a></div>
              </div>
            </div>
            <div class="col-xl col-md-4 col-6">
              <div class="allegro-queue-stat">
                <div class="small text-secondary">Gotowe</div>
                <div class="fs-4 fw-semibold text-success"><a href="{$baseUrl}?controller=allegro&action=index&page=1&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&status={$filters.status|escape:'url'}&duplicates={$filters.duplicates|escape:'url'}&queue_status=done&linked={$filters.linked|escape:'url'}&market={$filters.market|escape:'url'}&invoice={$filters.invoice|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}" class="text-success text-decoration-none">{$queueStats.done}</a></div>
              </div>
            </div>
            <div class="col-xl col-md-4 col-12">
              <div class="allegro-queue-stat">
                <div class="small text-secondary">Błąd</div>
                <div class="fs-4 fw-semibold text-danger"><a href="{$baseUrl}?controller=allegro&action=index&page=1&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&status={$filters.status|escape:'url'}&duplicates={$filters.duplicates|escape:'url'}&queue_status=error&linked={$filters.linked|escape:'url'}&market={$filters.market|escape:'url'}&invoice={$filters.invoice|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}" class="text-danger text-decoration-none">{$queueStats.error}</a></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <div class="allegro-topbar">
            <div class="allegro-topbar-copy">
              <div class="allegro-topbar-title">Oferty Allegro</div>
              <div class="allegro-topbar-meta">
                <span class="allegro-topbar-chip">Lacznie {$totalOffers} ofert</span>
                <span>strona {$page} z {$totalPages}</span>
                {if $duplicatesOnly}<span class="allegro-topbar-chip">widok: tylko duble</span>{/if}
              </div>
            </div>
            <div class="allegro-topbar-actions">
              <a href="{$autoEndOffersUrl|escape}" target="_blank" rel="noopener" class="btn btn-outline-warning">Cron konczenia</a>
              <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#allegroBulkModal">Akcje masowe</button>
            </div>
          </div>
        </div>
        <div class="card-body border-bottom">
          <form method="get" action="{$baseUrl}" class="row g-2">
            <input type="hidden" name="controller" value="allegro">
            <input type="hidden" name="action" value="index">
            <input type="hidden" name="sort_by" value="{$sortBy|escape}">
            <input type="hidden" name="sort_dir" value="{$sortDir|escape}">
            <input type="hidden" name="queue_status" value="{$queueStatusFilter|escape}">
            <div class="col-xl-2 col-md-6">
              <label class="form-label">Konto</label>
              <select name="account_id" class="form-select">
                <option value="">Wszystkie</option>
                {foreach $accounts as $account}
                  <option value="{$account.id}"{if $filters.account_id == $account.id} selected{/if}>{$account.name|escape}</option>
                {/foreach}
              </select>
            </div>
            <div class="col-xl-2 col-md-6">
              <label class="form-label">Szukaj</label>
              <input type="text" name="q" value="{$filters.q|escape}" class="form-control" placeholder='offer id / nazwa / SKU, kilka ID oddziel przecinkiem'>
              <div class="form-text">Mozesz wkleic kilka ID oddzielonych przecinkiem lub srednikiem. Negacja: wpisz <code>-etui</code>, <code>!etui</code> albo <code>-"iphone 15"</code>, aby wykluczyc fraze z wynikow.</div>
            </div>
            <div class="col-xl-2 col-md-4">
              <label class="form-label">SKU</label>
              <input type="text" name="sku" value="{$filters.sku|escape}" class="form-control">
              <div class="form-text">Negacja: <code>-ABC</code> albo <code>!ABC</code>.</div>
            </div>
            <div class="col-xl-2 col-md-4">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <option value="">Wszystkie</option>
                <option value="ACTIVE"{if $filters.status eq 'ACTIVE'} selected{/if}>ACTIVE</option>
                <option value="ENDED"{if $filters.status eq 'ENDED'} selected{/if}>ENDED</option>
                <option value="INACTIVE"{if $filters.status eq 'INACTIVE'} selected{/if}>INACTIVE</option>
              </select>
            </div>
            <div class="col-xl-2 col-md-4">
              <label class="form-label">Powiazanie</label>
              <select name="linked" class="form-select">
                <option value="">Wszystkie</option>
                <option value="1"{if $filters.linked eq '1'} selected{/if}>Powiazane</option>
                <option value="0"{if $filters.linked eq '0'} selected{/if}>Bez magazynu</option>
              </select>
            </div>
            <div class="col-xl-2 col-md-4">
              <label class="form-label">Duble</label>
              <select name="duplicates" class="form-select">
                <option value="">Wszystkie</option>
                <option value="1"{if $filters.duplicates eq '1'} selected{/if}>Tylko duble</option>
              </select>
            </div>
            <div class="col-xl-2 col-md-4">
              <label class="form-label">Rynek</label>
              <input type="text" name="market" value="{$filters.market|escape}" class="form-control" placeholder="np. allegro-pl">
              <div class="form-text">Negacja: <code>-allegro-cz</code> albo <code>!allegro-cz</code>.</div>
            </div>
            <div class="col-xl-2 col-md-4">
              <label class="form-label">Faktura</label>
              <select name="invoice" class="form-select">
                <option value="">Wszystkie</option>
                <option value="VAT"{if $filters.invoice eq 'VAT'} selected{/if}>VAT</option>
                <option value="NO_INVOICE"{if $filters.invoice eq 'NO_INVOICE'} selected{/if}>Brak</option>
              </select>
            </div>
            <div class="col-xl-4 col-md-3">
              <label class="form-label">Stan magazyn</label>
              <div class="row g-2">
                <div class="col-6">
                  <input type="number" min="0" step="1" name="warehouse_quantity_from" value="{$filters.warehouse_quantity_from|escape}" class="form-control" placeholder="od">
                </div>
                <div class="col-6">
                  <input type="number" min="0" step="1" name="warehouse_quantity_to" value="{$filters.warehouse_quantity_to|escape}" class="form-control" placeholder="do">
                </div>
              </div>
              <div class="form-text">Mozesz wpisac samo <code>0</code>, tylko <code>od</code>, tylko <code>do</code> albo oba pola.</div>
            </div>
            <div class="col-xl-2 col-md-4">
              <label class="form-label">Na strone</label>
              <select name="per_page" class="form-select">
                <option value="50"{if $perPage eq 50} selected{/if}>50</option>
                <option value="100"{if $perPage eq 100} selected{/if}>100</option>
                <option value="200"{if $perPage eq 200} selected{/if}>200</option>
                <option value="5000"{if $perPage eq 5000} selected{/if}>5000</option>
                <option value="10000"{if $perPage eq 10000} selected{/if}>10000</option>
              </select>
            </div>
            <div class="col-12 d-flex gap-2">
              <button type="submit" class="btn btn-primary">Filtruj</button>
              <a href="{$baseUrl}?controller=allegro&action=index" class="btn btn-outline-secondary">Reset</a>
            </div>
          </form>
        </div>

        <div class="card-body border-bottom py-2">
          <div class="d-flex flex-wrap gap-2 align-items-center">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="offers-select-page">Zaznacz strone</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="offers-clear-page">Odznacz strone</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="offers-invert-page">Odwroc zaznaczenie</button>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#allegroBulkModal">Otworz akcje masowe</button>
            <span class="small text-secondary">Dziala tez zaznaczanie zakresem z klawiszem Shift.</span>
          </div>
        </div>

        <div class="card-body border-bottom py-3 allegro-pagination-shell">
          <div class="allegro-pagination-panel">
            <div class="small text-secondary">Strona {$page} z {$totalPages}</div>
            <div class="allegro-pagination-bar">
              <div class="allegro-pagination-buttons">
                {assign var=prevPage value=$page-1}
                {assign var=nextPage value=$page+1}
                <a class="btn btn-sm btn-outline-secondary{if $page <= 1} disabled{/if}" href="{$baseUrl}?controller=allegro&action=index&page=1&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&status={$filters.status|escape:'url'}&duplicates={$filters.duplicates|escape:'url'}&linked={$filters.linked|escape:'url'}&market={$filters.market|escape:'url'}&invoice={$filters.invoice|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}&queue_status={$filters.queue_status|escape:'url'}">Pierwsza</a>
                <a class="btn btn-sm btn-outline-secondary{if $page <= 1} disabled{/if}" href="{$baseUrl}?controller=allegro&action=index&page={$prevPage}&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&status={$filters.status|escape:'url'}&duplicates={$filters.duplicates|escape:'url'}&linked={$filters.linked|escape:'url'}&market={$filters.market|escape:'url'}&invoice={$filters.invoice|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}&queue_status={$filters.queue_status|escape:'url'}">Poprzednia</a>
                {foreach $pageWindow as $pageItem}
                  {if $pageItem.type eq 'page'}
                    <a class="btn btn-sm {if $pageItem.is_current}btn-primary{else}btn-outline-secondary{/if}" href="{$baseUrl}?controller=allegro&action=index&page={$pageItem.value}&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&status={$filters.status|escape:'url'}&duplicates={$filters.duplicates|escape:'url'}&linked={$filters.linked|escape:'url'}&market={$filters.market|escape:'url'}&invoice={$filters.invoice|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}&queue_status={$filters.queue_status|escape:'url'}">{$pageItem.value}</a>
                  {else}
                    <span class="btn btn-sm btn-outline-secondary disabled">...</span>
                  {/if}
                {/foreach}
                <a class="btn btn-sm btn-outline-secondary{if $page >= $totalPages} disabled{/if}" href="{$baseUrl}?controller=allegro&action=index&page={$nextPage}&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&status={$filters.status|escape:'url'}&duplicates={$filters.duplicates|escape:'url'}&linked={$filters.linked|escape:'url'}&market={$filters.market|escape:'url'}&invoice={$filters.invoice|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}&queue_status={$filters.queue_status|escape:'url'}">Nastepna</a>
                <a class="btn btn-sm btn-outline-secondary{if $page >= $totalPages} disabled{/if}" href="{$baseUrl}?controller=allegro&action=index&page={$totalPages}&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&status={$filters.status|escape:'url'}&duplicates={$filters.duplicates|escape:'url'}&linked={$filters.linked|escape:'url'}&market={$filters.market|escape:'url'}&invoice={$filters.invoice|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}&queue_status={$filters.queue_status|escape:'url'}">Ostatnia</a>
              </div>

              <form method="get" action="{$baseUrl}" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="controller" value="allegro">
                <input type="hidden" name="action" value="index">
                <input type="hidden" name="per_page" value="{$perPage|escape}">
                <input type="hidden" name="sort_by" value="{$sortBy|escape}">
                <input type="hidden" name="sort_dir" value="{$sortDir|escape}">
                <input type="hidden" name="account_id" value="{$filters.account_id|escape}">
                <input type="hidden" name="q" value="{$filters.q|escape}">
                <input type="hidden" name="sku" value="{$filters.sku|escape}">
                <input type="hidden" name="status" value="{$filters.status|escape}">
                <input type="hidden" name="duplicates" value="{$filters.duplicates|escape}">
                <input type="hidden" name="linked" value="{$filters.linked|escape}">
                <input type="hidden" name="market" value="{$filters.market|escape}">
                <input type="hidden" name="invoice" value="{$filters.invoice|escape}">
                <input type="hidden" name="warehouse_quantity_from" value="{$filters.warehouse_quantity_from|escape}">
                <input type="hidden" name="warehouse_quantity_to" value="{$filters.warehouse_quantity_to|escape}">
                <input type="hidden" name="queue_status" value="{$queueStatusFilter|escape}">
                <span class="small text-secondary">Przejdz do strony</span>
                <input type="number" min="1" max="{$totalPages|escape}" name="page" value="{$page|escape}" class="form-control form-control-sm" style="width:110px;">
                <button type="submit" class="btn btn-sm btn-outline-primary">Idz</button>
              </form>
            </div>
          </div>
        </div>

        <div class="modal fade" id="allegroBulkModal" tabindex="-1" aria-labelledby="allegroBulkModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-fullscreen modal-dialog-scrollable">
            <div class="modal-content">
              <div class="modal-header">
                <div>
                  <h5 class="modal-title" id="allegroBulkModalLabel">Akcje masowe Allegro</h5>
                  <div class="small text-secondary">Zlecasz paczke do worker-a bez zabierania miejsca na liscie ofert.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
              </div>
              <div class="modal-body bg-light-subtle">
          <style>
            .bulk-ops-shell {
              background: linear-gradient(180deg, rgba(255,255,255,0.92), rgba(248,249,250,0.96));
              border: 1px solid rgba(13, 110, 253, 0.12);
              border-radius: 1rem;
              padding: 1rem;
            }

            .bulk-ops-step {
              border: 1px solid rgba(0, 0, 0, 0.08);
              border-radius: 0.9rem;
              background: #fff;
              padding: 0.9rem;
              height: 100%;
            }

            .bulk-ops-step-title {
              font-size: 0.78rem;
              letter-spacing: 0.06em;
              text-transform: uppercase;
              color: #6c757d;
              margin-bottom: 0.45rem;
            }

            .bulk-ops-choice {
              border: 1px solid rgba(0, 0, 0, 0.08);
              border-radius: 0.85rem;
              padding: 0.75rem 0.9rem;
              background: #fff;
            }

            .bulk-ops-choice.active {
              border-color: rgba(13, 110, 253, 0.45);
              box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.08);
              background: rgba(13, 110, 253, 0.03);
            }

            .bulk-ops-summary {
              border-radius: 0.85rem;
              background: #0f172a;
              color: #e2e8f0;
              padding: 0.85rem 1rem;
            }

            .bulk-ops-summary strong {
              color: #fff;
            }

            .bulk-ops-hidden {
              display: none !important;
            }
          </style>
          <form method="post" action="{$baseUrl}?controller=allegro&action=queue" class="row g-3" id="allegro-bulk-form">
            <input type="hidden" name="account_id" value="{$filters.account_id|escape}">
            <input type="hidden" name="q" value="{$filters.q|escape}">
            <input type="hidden" name="sku" value="{$filters.sku|escape}">
            <input type="hidden" name="status" value="{$filters.status|escape}">
            <input type="hidden" name="duplicates" value="{$filters.duplicates|escape}">
            <input type="hidden" name="linked" value="{$filters.linked|escape}">
            <input type="hidden" name="market" value="{$filters.market|escape}">
            <input type="hidden" name="invoice" value="{$filters.invoice|escape}">
            <input type="hidden" name="warehouse_quantity_from" value="{$filters.warehouse_quantity_from|escape}">
            <input type="hidden" name="warehouse_quantity_to" value="{$filters.warehouse_quantity_to|escape}">
            <input type="hidden" name="queue_status" value="{$queueStatusFilter|escape}">
            <input type="hidden" name="return_url" value="{$currentListUrl|escape}">
            <div class="col-12">
              <div class="bulk-ops-shell">
                <div class="row g-3">
                  <div class="col-xl-4">
                    <div class="bulk-ops-step h-100">
                      <div class="bulk-ops-step-title">Krok 1</div>
                      <div class="fw-semibold mb-2">Wybierz zakres zmian</div>
                      <div class="d-grid gap-2">
                        <label class="bulk-ops-choice js-selection-choice" for="selection_scope_filtered">
                          <div class="form-check">
                            <input class="form-check-input" type="radio" name="selection_scope" id="selection_scope_filtered" value="filtered">
                            <span class="form-check-label fw-semibold">Wszystkie z filtrowania</span>
                          </div>
                          <div class="small text-secondary mt-1">Bierze caly wynik filtrowania z bazy, bez znaczenia ustawienia "Na strone".</div>
                        </label>
                        <label class="bulk-ops-choice active js-selection-choice" for="selection_scope_selected">
                          <div class="form-check">
                            <input class="form-check-input" type="radio" name="selection_scope" id="selection_scope_selected" value="selected" checked>
                            <span class="form-check-label fw-semibold">Tylko zaznaczone na liscie</span>
                          </div>
                          <div class="small text-secondary mt-1">Uzywa checkboxow z tabeli. Dobre do recznego wyboru kilku lub kilkudziesieciu ofert.</div>
                        </label>
                      </div>
                    </div>
                  </div>

                  <div class="col-xl-4">
                    <div class="bulk-ops-step h-100">
                      <div class="bulk-ops-step-title">Krok 2</div>
                      <div class="fw-semibold mb-2">Wybierz typ operacji</div>
                      <label class="form-label">Operacja</label>
                      <select name="operation" class="form-select mb-3" id="bulk-operation-select" required>
                        <option value="replace_name">Nazwa: znajdz i zamien</option>
                        <option value="set_name">Nazwa: ustaw recznie</option>
                        <option value="set_sku">Ustaw SKU</option>
                        <option value="set_price">Cena: ustaw recznie</option>
                        <option value="set_price_from_product">Cena: z magazynu</option>
                        <option value="set_category_parameters">Kategoria i parametry</option>
                        <option value="set_delivery">Dostawa: ustaw czas wysylki</option>
                        <option value="set_invoice">Faktura: ustaw opcje</option>
                        <option value="link_product_auto">Produkt Allegro: auto</option>
                        <option value="link_product_id">Produkt Allegro: ustaw ID</option>
                        <option value="clear_queue">Usuń z kolejki</option>
                        <option value="remove_from_system">Usuń z systemu</option>
                        <option value="remove_from_system_forever">Usuń z systemu na zawsze</option>
                        <option value="end_offer">Zakoncz oferty</option>
                        <option value="resume_offer">Wznow oferty</option>
                      </select>
                      <div class="bulk-ops-summary">
                        <div class="small text-uppercase mb-1" style="letter-spacing:0.08em;">Co zrobi ta operacja</div>
                        <div class="fw-semibold mb-1" id="bulk-operation-title">Nazwa: znajdz i zamien</div>
                        <div class="small mb-0" id="bulk-operation-description">Podmieni wskazana fraze w nazwach ofert. Dobre do masowego poprawiania literowek, marek albo dopiskow.</div>
                      </div>
                    </div>
                  </div>

                  <div class="col-xl-4">
                    <div class="bulk-ops-step h-100">
                      <div class="bulk-ops-step-title">Krok 3</div>
                      <div class="fw-semibold mb-2">Uzupelnij tylko potrzebne pola</div>

                      <div class="mb-3 js-bulk-field" data-ops="set_name,set_price">
                        <label class="form-label" id="bulk-value-label" for="bulk-value-input">Wartosc</label>
                        <input type="text" name="value" class="form-control" id="bulk-value-input" placeholder="nowa nazwa / cena / SKU">
                        <div class="form-text" id="bulk-value-help">Wpisz nowa wartosc dla zaznaczonych ofert.</div>
                      </div>

                      <div class="mb-3 js-bulk-field" data-ops="set_sku">
                        <label class="form-label" for="bulk-sku-input">Ustaw SKU</label>
                        <input type="text" class="form-control mb-2" id="bulk-sku-input" placeholder="Wpisz SKU recznie lub wybierz produkt z magazynu">
                        <input type="hidden" name="warehouse_product_id" id="bulk-warehouse-product-id" value="">
                        <div class="small text-secondary mb-2">Mozesz wpisac SKU recznie albo wyszukac produkt z magazynu i kliknac wynik. Wybrany produkt ustawi jego SKU.</div>
                        <div class="row g-2">
                          <div class="col-12">
                            <input type="text" class="form-control" id="bulk-warehouse-search" placeholder="Szukaj produktu z magazynu po SKU lub nazwie">
                          </div>
                          <div class="col-12">
                            <div class="border rounded p-2 bg-body-tertiary small bulk-ops-hidden" id="bulk-warehouse-selected"></div>
                            <div class="list-group small mt-2 bulk-ops-hidden" id="bulk-warehouse-suggestions"></div>
                          </div>
                        </div>
                      </div>

                      <div class="row g-2 js-bulk-field" data-ops="replace_name">
                        <div class="col-12">
                          <label class="form-label" for="bulk-search-input">Znajdz</label>
                          <input type="text" name="search" class="form-control" id="bulk-search-input" placeholder="fraza do zamiany">
                        </div>
                        <div class="col-12">
                          <label class="form-label" for="bulk-replace-input">Zamien na</label>
                          <input type="text" name="replace" class="form-control" id="bulk-replace-input" placeholder="nowa fraza">
                        </div>
                        <div class="col-12">
                          <div class="form-text">Podmieja tylko ten fragment nazwy, ktory wpiszesz w polu "Znajdz".</div>
                        </div>
                      </div>

                      <div class="mb-3 js-bulk-field" data-ops="link_product_id">
                        <label class="form-label" for="bulk-product-id-input">Allegro product ID</label>
                        <input type="text" name="product_id" class="form-control" id="bulk-product-id-input" placeholder="ID produktu z katalogu Allegro">
                        <div class="form-text">Uzyj, gdy znasz konkretny identyfikator produktu Allegro.</div>
                      </div>

                      <div class="mb-3 js-bulk-field" data-ops="set_delivery">
                        <label class="form-label" for="bulk-delivery-input">Czas wysylki</label>
                        <select name="delivery_value" class="form-select" id="bulk-delivery-input">
                          <option value="">Wybierz czas wysylki</option>
                          <option value="PT0H">Natychmiast</option>
                          <option value="PT24H">24H</option>
                          <option value="PT48H">48H</option>
                          <option value="PT72H">72H</option>
                          <option value="P7D">Do 7 dni</option>
                        </select>
                        <div class="form-text">Nadaje jeden czas realizacji dla calej paczki ofert.</div>
                      </div>

                      <div class="mb-3 js-bulk-field" data-ops="set_invoice">
                        <label class="form-label" for="bulk-invoice-input">Opcja faktury</label>
                        <select name="invoice_value" class="form-select" id="bulk-invoice-input">
                          <option value="">Wybierz opcje faktury</option>
                          <option value="VAT">Faktura VAT</option>
                          <option value="NO_INVOICE">Bez faktury</option>
                          <option value="B2B">B2B</option>
                        </select>
                        <div class="form-text">Przydatne przy porzadkowaniu wielu ofert po jednym standardzie sprzedazy.</div>
                      </div>

                      <div class="mb-3 js-bulk-field" data-ops="set_category_parameters">
                        <div class="border rounded p-3 bg-body-tertiary">
                          <div class="fw-semibold mb-2">Sugerowana kategoria z pierwszej oferty</div>
                          <div class="small text-secondary mb-2">Mozesz skorzystac z kategorii pierwszej zaznaczonej oferty z listy albo wybrac produkt przez wyszukiwarke magazynowa i na tej podstawie podpowiemy inna kategorie Allegro.</div>
                          <div class="row g-2">
                            <div class="col-12">
                              <label class="form-label" for="bulk-category-product-search">Wyszukaj produkt magazynowy</label>
                              <input type="text" class="form-control" id="bulk-category-product-search" placeholder="Szukaj produktu po SKU lub nazwie">
                              <div class="form-text">Podpowiedzi z wyszukiwarki nie zmieniaja zakresu akcji masowej. Sluza tylko do zasugerowania kategorii.</div>
                            </div>
                            <div class="col-12">
                              <div class="border rounded p-2 bg-white small bulk-ops-hidden" id="bulk-category-source-selected"></div>
                              <div class="list-group small mt-2 bulk-ops-hidden" id="bulk-category-product-suggestions"></div>
                            </div>
                          </div>
                          <div class="border rounded p-2 bg-white mt-3 small" id="bulk-category-suggestion-box">
                            Brak sugerowanej kategorii. Zaznacz najpierw oferte z listy albo wybierz produkt w wyszukiwarce.
                          </div>
                        </div>
                      </div>

                      <div class="mb-3 js-bulk-field" data-ops="set_category_parameters">
                        <label class="form-label" for="bulk-category-search-input">Wyszukaj lub popraw kategorie Allegro</label>
                        <div class="input-group">
                          <input type="text" class="form-control" id="bulk-category-search-input" placeholder="np. etui iphone, szklo hartowane">
                          <button type="button" class="btn btn-outline-primary" id="bulk-category-search-btn">Szukaj</button>
                        </div>
                        <input type="hidden" name="category_id" id="bulk-category-id" value="">
                        <div class="list-group small mt-2 bulk-ops-hidden" id="bulk-category-search-results"></div>
                        <div class="border rounded p-2 bg-body-tertiary mt-2 small" id="bulk-category-selected-box">Nie wybrano jeszcze kategorii Allegro.</div>
                      </div>

                      <div class="mb-3 js-bulk-field" data-ops="set_category_parameters">
                        <label class="form-label">Parametry dla wybranej kategorii</label>
                        <div class="small text-secondary mb-2">Po wybraniu kategorii wczytamy parametry Allegro i bedziesz mogl je zmienic przed dodaniem ofert do kolejki.</div>
                        <div class="border rounded p-3 bg-body-tertiary" id="bulk-category-parameters-box">
                          <div class="text-secondary">Najpierw wybierz kategorie Allegro.</div>
                        </div>
                      </div>

                      <div class="mb-0 js-bulk-field" data-ops="replace_name,set_name,set_sku,set_price,set_price_from_product,set_category_parameters,set_delivery,set_invoice,link_product_auto,link_product_id,end_offer,resume_offer">
                        <div class="form-text pt-2">Paczka bierze teraz caly wynik filtrowania bez sztucznego limitu.</div>
                      </div>
                    </div>
                  </div>

                  <div class="col-12">
                    <div class="bulk-ops-step">
                      <div class="bulk-ops-step-title">Opcjonalnie</div>
                      <div class="fw-semibold mb-2">Reczne ID ofert</div>
                      <label class="form-label" for="bulk-manual-ids">Offer ID lub lokalne ID</label>
                      <textarea name="manual_offer_ids" rows="2" class="form-control" id="bulk-manual-ids" placeholder="Zostaw puste, zeby uzyc filtrowania albo checkboxow. Wklej ID po spacji, przecinku albo w nowych liniach, jesli chcesz zrobic paczke z palca."></textarea>
                      <div class="form-text">Jesli tu cos wpiszesz, to te ID maja pierwszenstwo nad checkboxami i nad filtrowaniem.</div>
                    </div>
                  </div>

                  <div class="col-12">
                    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                      <div class="small text-secondary">
                        Zmiany ida przez worker, zeby setki i tysiace rekordow nie blokowaly panelu.
                        <span id="selected-offers-counter">Zaznaczone na stronie: 0</span>
                      </div>
                      <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="small text-secondary" id="bulk-submit-hint">Masowa podmiana nazwy w calym filtrowaniu.</span>
                        <button type="submit" class="btn btn-dark px-4">Dodaj do kolejki</button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </form>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zamknij</button>
              </div>
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-striped table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:44px;">
                  <input type="checkbox" class="form-check-input" id="offers-check-all" title="Zaznacz / odznacz widoczne">
                </th>
                <th><a href="{$sortUrls.images|escape}" class="link-dark text-decoration-none">Grafiki {if $sortIndicators.images eq 'asc'}&uarr;{elseif $sortIndicators.images eq 'desc'}&darr;{else}&harr;{/if}</a></th>
                <th><a href="{$sortUrls.account|escape}" class="link-dark text-decoration-none">Konto {if $sortIndicators.account eq 'asc'}&uarr;{elseif $sortIndicators.account eq 'desc'}&darr;{else}&harr;{/if}</a></th>
                <th><a href="{$sortUrls.name|escape}" class="link-dark text-decoration-none">Nazwa {if $sortIndicators.name eq 'asc'}&uarr;{elseif $sortIndicators.name eq 'desc'}&darr;{else}&harr;{/if}</a></th>
                <th><a href="{$sortUrls.warehouse_quantity|escape}" class="link-dark text-decoration-none">Magazyn {if $sortIndicators.warehouse_quantity eq 'asc'}&uarr;{elseif $sortIndicators.warehouse_quantity eq 'desc'}&darr;{else}&harr;{/if}</a></th>
                <th><a href="{$sortUrls.price|escape}" class="link-dark text-decoration-none">Cena / VAT {if $sortIndicators.price eq 'asc'}&uarr;{elseif $sortIndicators.price eq 'desc'}&darr;{else}&harr;{/if}</a></th>
                <th>Duble</th>
                <th><a href="{$sortUrls.status|escape}" class="link-dark text-decoration-none">Status i rynki {if $sortIndicators.status eq 'asc'}&uarr;{elseif $sortIndicators.status eq 'desc'}&darr;{else}&harr;{/if}</a></th>
                <th><a href="{$sortUrls.sold|escape}" class="link-dark text-decoration-none">Stany / sprzedaz {if $sortIndicators.sold eq 'asc'}&uarr;{elseif $sortIndicators.sold eq 'desc'}&darr;{else}&harr;{/if}</a></th>
                <th><a href="{$sortUrls.linked|escape}" class="link-dark text-decoration-none">Dane {if $sortIndicators.linked eq 'asc'}&uarr;{elseif $sortIndicators.linked eq 'desc'}&darr;{else}&harr;{/if}</a></th>
                <th><a href="{$sortUrls.synced|escape}" class="link-dark text-decoration-none">Pobrane {if $sortIndicators.synced eq 'asc'}&uarr;{elseif $sortIndicators.synced eq 'desc'}&darr;{else}&harr;{/if}</a></th>
                <th><a href="{$sortUrls.updated|escape}" class="link-dark text-decoration-none">Zmieniono {if $sortIndicators.updated eq 'asc'}&uarr;{elseif $sortIndicators.updated eq 'desc'}&darr;{else}&harr;{/if}</a></th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {foreach $offers as $offer}
                <tr class="js-offer-row {if $offer.queue_meta.row_class}{$offer.queue_meta.row_class|escape}{/if}" data-offer-id="{$offer.offer_id|escape}">
                  <td class="text-center">
                    <input type="checkbox" class="form-check-input js-offer-select" name="selected_offer_ids[]" value="{$offer.id|escape}" form="allegro-bulk-form" data-offer-id="{$offer.offer_id|escape}" data-offer-name="{$offer.name|escape}" data-offer-category-id="{$offer.category_id|default:''|escape}" data-offer-category-name="{$offer.category_name|default:''|escape}" data-offer-allegro-url="https://allegro.pl/oferta/{$offer.offer_id|escape}" data-warehouse-product-id="{$offer.warehouse_product_id|default:''|escape}" data-warehouse-product-name="{$offer.warehouse_product_name|default:''|escape}" data-warehouse-sku="{$offer.warehouse_sku|default:''|escape}" data-warehouse-category-id="{$offer.warehouse_category_id|default:''|escape}" data-warehouse-category-name="{$offer.warehouse_category_name|default:''|escape}" data-warehouse-category-allegro-id="{$offer.warehouse_category_allegro_id|default:''|escape}"{if $offer.duplicate_meta.is_duplicate && !$offer.duplicate_meta.can_end_offer} disabled title="Najstarsza oferta w grupie dubli nie moze zostac zakonczona"{/if}>
                  </td>
                  <td style="width:80px;">
                    {if $offer.primary_image_url}
                      <img src="{$offer.primary_image_url|escape}" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:8px;">
                    {else}
                      <span class="text-secondary small">brak</span>
                    {/if}
                    <div class="small text-secondary mt-1">grafik: {$offer.image_count|default:0|escape}</div>
                  </td>
                  <td>{$offer.account_name|escape}</td>
                  <td>
                    <div class="fw-semibold">{$offer.name|escape}</div>
                    <div class="small text-secondary">offer: {$offer.offer_id|escape}</div>
                    {if $offer.category_name}<div class="small text-secondary">{$offer.category_name|escape}</div>{/if}
                    <div class="small text-secondary">produkt Allegro: {$offer.allegro_product_id|default:'-'|escape}</div>
                    {if $offer.queue_meta.has_queue_entry}
                      <div class="small mt-2">
                        <span class="badge {$offer.queue_meta.badge_class|escape}">{$offer.queue_meta.status_label|escape}</span>
                        {if $offer.queue_meta.operation}<span class="text-secondary ms-1">{$offer.queue_meta.operation|escape}</span>{/if}
                      </div>
                      {if $offer.queue_meta.error_message}
                        <div class="small text-danger mt-1">{$offer.queue_meta.error_message|truncate:140|escape}</div>
                      {/if}
                    {/if}
                  </td>
                  <td>
                    <div><span class="text-secondary small">Allegro SKU</span><br><code>{$offer.sku|default:'-'|escape}</code></div>
                    <div class="mt-1"><span class="text-secondary small">Magazyn</span><br>
                      {if $offer.warehouse_product_id}
                        <span class="fw-semibold">#{$offer.warehouse_product_id|escape} {$offer.warehouse_product_name|default:'-'|escape}</span><br>
                        <code>{$offer.warehouse_sku|default:'-'|escape}</code>
                      {else}
                        <span class="text-danger">brak powiazania</span>
                      {/if}
                    </div>
                  </td>
                  <td>
                    <div class="fw-semibold">
                      {if $offer.price_amount !== null}
                        {$offer.price_amount|escape} {$offer.price_currency|escape}
                      {else}
                        <span class="text-secondary">-</span>
                      {/if}
                    </div>
                    <div class="small text-secondary">faktura: {$offer.invoice_type|default:'-'|escape}</div>
                    <div class="small text-secondary">VAT: {if $offer.invoice_type eq 'VAT'}tak{else}nie{/if}</div>
                    {if $offer.marketplace_price_entries}
                      <div class="small text-secondary mt-1">
                        {foreach $offer.marketplace_price_entries as $marketPrice}
                          <div>{$marketPrice.label|escape}: {$marketPrice.price|escape}{if $marketPrice.currency} {$marketPrice.currency|escape}{/if}</div>
                        {/foreach}
                      </div>
                    {/if}
                  </td>
                  <td class="small">
                    {if $offer.duplicate_meta.is_duplicate}
                      <div><span class="badge text-bg-danger">Dubel</span></div>
                      <div class="mt-1">Inne oferty: {$offer.duplicate_meta.duplicate_count|escape}</div>
                      <div>Najstarsza: <code>{$offer.duplicate_meta.oldest_offer_id|default:'-'|escape}</code>{if $offer.duplicate_meta.is_oldest} <span class="text-danger">(ta oferta)</span>{/if}</div>
                      <div class="mt-1">
                        {if $offer.duplicate_meta.can_end_offer}
                          <span class="text-success">Do zakończenia: tak</span>
                        {else}
                          <span class="text-danger">Do zakończenia: nie, zostaje jako najstarsza</span>
                        {/if}
                      </div>
                      <div class="mt-1">
                        {foreach $offer.duplicate_meta.peer_details as $peer}
                          <div><code>{$peer.offer_id|escape}</code> <span class="text-secondary">({$peer.status|escape})</span></div>
                        {foreachelse}
                          <div class="text-secondary">Brak innych ofert w tej grupie.</div>
                        {/foreach}
                      </div>
                    {else}
                      <span class="text-secondary">Brak</span>
                    {/if}
                  </td>
                  <td>
                    <div><span class="badge text-bg-light border">{$offer.status_label|default:'-'|escape}</span></div>
                    <div class="small text-secondary mt-1">
                      {if $offer.marketplace_entries}
                        {foreach $offer.marketplace_entries as $market}
                          <span class="badge text-bg-light border me-1 mb-1">{$market.label|escape}</span>
                        {/foreach}
                      {else}
                        rynki: -
                      {/if}
                    </div>
                  </td>
                  <td>
                    <div class="small">Allegro: {$offer.stock_available|default:'-'|escape}</div>
                    <div class="small">Sprzedane: {$offer.stock_sold|default:'-'|escape}</div>
                    <div class="small">Magazyn: {$offer.warehouse_quantity|default:'-'|escape}</div>
                  </td>
                  <td class="small">
                    <div>parametry: {$offer.parameters|@count}</div>
                    <div>grafik: {$offer.image_count|default:0|escape}</div>
                    <div>lokalizacja: {$offer.warehouse_localization|default:'-'|escape}</div>
                    <div>powiazanie: {if $offer.warehouse_product_id}tak{else}nie{/if}</div>
                    {if $offer.queue_meta.has_queue_entry}
                      <div class="mt-1">zadanie: {$offer.queue_meta.status_label|escape}</div>
                      {if $offer.queue_meta.updated_at}<div>ostatnio: {$offer.queue_meta.updated_at|escape}</div>{/if}
                      {if $offer.queue_meta.status eq 'error' || $offer.queue_meta.status eq 'retry'}<div>proby: {$offer.queue_meta.attempts|escape}</div>{/if}
                    {/if}
                  </td>
                  <td class="small">{$offer.last_synced_at|escape}</td>
                  <td class="small">{$offer.updated_at|escape}</td>
                  <td>
                    <div class="d-grid gap-2">
                      <a href="{$baseUrl}?controller=allegro&action=offer&id={$offer.id}&return_url={$currentListUrl|escape:'url'}" class="btn btn-sm btn-outline-primary">Szczegoly oferty</a>
                      <a href="https://allegro.pl/oferta/{$offer.offer_id|escape}" target="_blank" rel="noreferrer" class="btn btn-sm btn-outline-secondary">Przejdz do Allegro</a>
                    </div>
                  </td>
                </tr>
              {foreachelse}
                <tr><td colspan="13" class="text-center text-secondary py-4">Brak ofert dla wybranych filtrow.</td></tr>
              {/foreach}
            </tbody>
          </table>
        </div>

        <div class="card-footer allegro-pagination-shell">
          <div class="allegro-pagination-panel">
            <div class="small text-secondary">Paginacja jest po stronie bazy, a akcje masowe ida do worker-a w tle.</div>
            <div class="allegro-pagination-buttons">
              <a class="btn btn-sm btn-outline-secondary{if $page <= 1} disabled{/if}" href="{$baseUrl}?controller=allegro&action=index&page={$prevPage}&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&status={$filters.status|escape:'url'}&duplicates={$filters.duplicates|escape:'url'}&linked={$filters.linked|escape:'url'}&market={$filters.market|escape:'url'}&invoice={$filters.invoice|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}&queue_status={$filters.queue_status|escape:'url'}">Poprzednia</a>
              <a class="btn btn-sm btn-outline-secondary{if $page >= $totalPages} disabled{/if}" href="{$baseUrl}?controller=allegro&action=index&page={$nextPage}&per_page={$perPage}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&status={$filters.status|escape:'url'}&duplicates={$filters.duplicates|escape:'url'}&linked={$filters.linked|escape:'url'}&market={$filters.market|escape:'url'}&invoice={$filters.invoice|escape:'url'}&warehouse_quantity_from={$filters.warehouse_quantity_from|escape:'url'}&warehouse_quantity_to={$filters.warehouse_quantity_to|escape:'url'}&queue_status={$filters.queue_status|escape:'url'}">Nastepna</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<script>
  (function () {
    var bulkForm = document.getElementById('allegro-bulk-form');
    var operationSelect = document.getElementById('bulk-operation-select');
    var categoryProductSearchInput = document.getElementById('bulk-category-product-search');
    var categoryProductSuggestions = document.getElementById('bulk-category-product-suggestions');
    var categorySourceSelected = document.getElementById('bulk-category-source-selected');
    var categorySuggestionBox = document.getElementById('bulk-category-suggestion-box');
    var categorySearchInput = document.getElementById('bulk-category-search-input');
    var categorySearchButton = document.getElementById('bulk-category-search-btn');
    var categorySearchResults = document.getElementById('bulk-category-search-results');
    var categoryIdInput = document.getElementById('bulk-category-id');
    var categorySelectedBox = document.getElementById('bulk-category-selected-box');
    var categoryParametersBox = document.getElementById('bulk-category-parameters-box');
    var checkboxes = Array.prototype.slice.call(document.querySelectorAll('.js-offer-select'));
    var searchTimer = null;
    var suggestedCategory = null;

    if (!bulkForm || !operationSelect || !categorySuggestionBox || !categorySelectedBox || !categoryParametersBox) {
      return;
    }

    function escapeHtml(value) {
      return String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function isCategoryOperation() {
      return operationSelect.value === 'set_category_parameters';
    }

    function firstCheckedOfferMeta() {
      var checked = checkboxes.find(function (item) { return item.checked; });
      if (!checked) {
        return null;
      }

      return {
        offerName: checked.getAttribute('data-offer-name') || '',
        offerCategoryName: checked.getAttribute('data-offer-category-name') || '',
        offerCategoryId: checked.getAttribute('data-offer-category-id') || '',
        warehouseProductName: checked.getAttribute('data-warehouse-product-name') || ''
      };
    }

    function renderSuggestedCategory() {
      if (!suggestedCategory || !suggestedCategory.id) {
        categorySuggestionBox.innerHTML = 'Brak sugerowanej kategorii. Zaznacz najpierw oferte z lista albo wybierz produkt w wyszukiwarce.';
        return;
      }

      categorySuggestionBox.innerHTML = ''
        + '<div class="fw-semibold">Sugerowana kategoria Allegro</div>'
        + '<div><code>' + escapeHtml(suggestedCategory.id) + '</code> ' + escapeHtml(suggestedCategory.path || suggestedCategory.name || '') + '</div>'
        + '<div class="small text-secondary mt-1">' + escapeHtml(suggestedCategory.sourceLabel || '') + '</div>'
        + '<button type="button" class="btn btn-sm btn-outline-primary mt-2" id="bulk-use-suggested-category">Uzyj tej kategorii</button>';

      var useButton = document.getElementById('bulk-use-suggested-category');
      if (useButton) {
        useButton.addEventListener('click', function () {
          selectCategory(suggestedCategory);
        });
      }
    }

    function refreshSuggestedCategoryFromSelection() {
      if (!isCategoryOperation()) {
        return;
      }

      if (categoryProductSearchInput && categoryProductSearchInput.value.trim() !== '') {
        return;
      }

      var meta = firstCheckedOfferMeta();
      suggestedCategory = meta && meta.offerCategoryId ? {
        id: meta.offerCategoryId,
        name: meta.offerCategoryName || '',
        path: meta.offerCategoryName || '',
        sourceLabel: 'Na podstawie kategorii pierwszej zaznaczonej oferty: ' + (meta.offerName || '')
      } : null;
      renderSuggestedCategory();
    }

    function renderCategoryParameters(items) {
      if (!items || !items.length) {
        categoryParametersBox.innerHTML = '<div class="text-secondary">Wybrana kategoria nie zwrocila parametrow.</div>';
        return;
      }

      var html = '<div class="row g-3">';
      items.forEach(function (item) {
        var pid = String(item.id || '');
        if (!pid) {
          return;
        }

        var restrictions = item.restrictions && typeof item.restrictions === 'object' ? item.restrictions : {};
        var multiple = !!item.multiple || item.type === 'multidictionary' || restrictions.multipleChoices === true || restrictions.multipleChoices === 1;
        var dict = Array.isArray(item.dictionary) ? item.dictionary : [];
        html += '<div class="col-md-6"><div class="border rounded p-3 bg-white h-100">';
        html += '<label class="form-label fw-semibold">' + escapeHtml(item.name || pid);
        if (item.required) {
          html += ' <span class="badge text-bg-warning text-dark">wymagany</span>';
        }
        html += item.describes_product ? ' <span class="badge text-bg-light border">produkt</span>' : ' <span class="badge text-bg-light border">oferta</span>';
        html += '</label>';

        if (dict.length && multiple) {
          if (dict.length > 12) {
            html += '<input type="text" class="form-control form-control-sm mb-2 js-category-param-filter" data-filter-target="bulk-category-param-list-' + escapeHtml(pid) + '" placeholder="Szukaj na liscie wartosci">';
          }
          html += '<div class="d-flex flex-column gap-2" id="bulk-category-param-list-' + escapeHtml(pid) + '" style="max-height: 220px; overflow: auto;">';
          dict.forEach(function (option, index) {
            var optId = String(option.id || option.value || '');
            var inputId = 'bulk-category-param-' + pid + '-' + index;
            html += '<div class="form-check js-category-param-option" data-filter-label="' + escapeHtml((option.value || optId).toLowerCase()) + '"><input class="form-check-input" type="checkbox" id="' + inputId + '" name="category_parameters[' + escapeHtml(pid) + '][]" value="' + escapeHtml(optId) + '"><label class="form-check-label" for="' + inputId + '">' + escapeHtml(option.value || optId) + '</label></div>';
          });
          html += '</div>';
        } else if (dict.length) {
          html += '<select class="form-select" name="category_parameters[' + escapeHtml(pid) + ']"><option value="">Wybierz wartosc</option>';
          dict.forEach(function (option) {
            var optId = String(option.id || option.value || '');
            html += '<option value="' + escapeHtml(optId) + '">' + escapeHtml(option.value || optId) + '</option>';
          });
          html += '</select>';
        } else if (multiple) {
          html += '<textarea class="form-control" name="category_parameters[' + escapeHtml(pid) + ']" rows="3" placeholder="Kazda wartosc w osobnej linii"></textarea>';
        } else if (item.type === 'integer' || item.type === 'float' || item.type === 'number') {
          html += '<input type="number" step="any" class="form-control" name="category_parameters[' + escapeHtml(pid) + ']" value="">';
        } else {
          html += '<input type="text" class="form-control" name="category_parameters[' + escapeHtml(pid) + ']" value="">';
        }

        html += '</div></div>';
      });
      html += '</div>';
      categoryParametersBox.innerHTML = html;

      Array.prototype.slice.call(categoryParametersBox.querySelectorAll('.js-category-param-filter')).forEach(function (input) {
        input.addEventListener('input', function () {
          var targetId = input.getAttribute('data-filter-target') || '';
          var container = targetId ? document.getElementById(targetId) : null;
          var phrase = input.value ? input.value.trim().toLowerCase() : '';
          if (!container) {
            return;
          }

          Array.prototype.slice.call(container.querySelectorAll('.js-category-param-option')).forEach(function (option) {
            var haystack = option.getAttribute('data-filter-label') || '';
            option.style.display = phrase === '' || haystack.indexOf(phrase) !== -1 ? '' : 'none';
          });
        });
      });
    }

    function loadCategoryParameters(categoryId) {
      categoryParametersBox.innerHTML = '<div class="text-secondary">Wczytuje parametry kategorii...</div>';
      fetch('{$baseUrl|escape:"javascript"}?controller=allegro&action=parameters&id=' + encodeURIComponent(categoryId), { credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          renderCategoryParameters(data && data.items ? data.items : []);
        })
        .catch(function () {
          categoryParametersBox.innerHTML = '<div class="text-danger">Blad pobierania parametrow kategorii.</div>';
        });
    }

    function selectCategory(item) {
      if (!item || !item.id) {
        return;
      }

      categoryIdInput.value = item.id;
      categorySelectedBox.innerHTML = '<div class="fw-semibold">Wybrana kategoria</div><div><code>' + escapeHtml(item.id) + '</code> ' + escapeHtml(item.path || item.name || '') + '</div>';
      if (categorySearchInput) {
        categorySearchInput.value = item.path || item.name || '';
      }
      loadCategoryParameters(item.id);
    }

    function renderCategorySearchResults(items) {
      if (!items || !items.length) {
        categorySearchResults.innerHTML = '<div class="list-group-item text-secondary">Brak kategorii pasujacych do wyszukiwania.</div>';
        categorySearchResults.classList.remove('bulk-ops-hidden');
        return;
      }

      var html = '';
      items.forEach(function (item) {
        html += '<button type="button" class="list-group-item list-group-item-action js-category-search-result" data-id="' + escapeHtml(item.id || '') + '" data-name="' + escapeHtml(item.name || '') + '" data-path="' + escapeHtml(item.path || item.name || '') + '"><div class="fw-semibold"><code>' + escapeHtml(item.id || '') + '</code> ' + escapeHtml(item.name || '') + '</div><div class="small text-secondary">' + escapeHtml(item.path || item.name || '') + '</div></button>';
      });
      categorySearchResults.innerHTML = html;
      categorySearchResults.classList.remove('bulk-ops-hidden');

      Array.prototype.slice.call(categorySearchResults.querySelectorAll('.js-category-search-result')).forEach(function (button) {
        button.addEventListener('click', function () {
          selectCategory({
            id: button.getAttribute('data-id') || '',
            name: button.getAttribute('data-name') || '',
            path: button.getAttribute('data-path') || ''
          });
          categorySearchResults.classList.add('bulk-ops-hidden');
        });
      });
    }

    function searchAllegroCategories() {
      var phrase = categorySearchInput ? categorySearchInput.value.trim() : '';
      if (phrase.length < 2) {
        return;
      }

      fetch('{$baseUrl|escape:"javascript"}?controller=allegro&action=categories&search=' + encodeURIComponent(phrase), { credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          renderCategorySearchResults(data && data.items ? data.items : []);
        })
        .catch(function () {
          categorySearchResults.innerHTML = '<div class="list-group-item text-danger">Blad pobierania kategorii Allegro.</div>';
          categorySearchResults.classList.remove('bulk-ops-hidden');
        });
    }

    function renderCategoryProductSuggestions(items) {
      if (!items || !items.length) {
        categoryProductSuggestions.innerHTML = '<div class="list-group-item text-secondary">Brak pasujacych produktow.</div>';
        categoryProductSuggestions.classList.remove('bulk-ops-hidden');
        return;
      }

      var html = '';
      items.forEach(function (item) {
        html += '<button type="button" class="list-group-item list-group-item-action js-category-product-suggestion" data-id="' + escapeHtml(item.id || '') + '" data-sku="' + escapeHtml(item.sku || '') + '" data-name="' + escapeHtml(item.product_name || '') + '" data-category-name="' + escapeHtml(item.category_name || '') + '" data-category-allegro-id="' + escapeHtml(item.category_allegro_id || '') + '"><div class="fw-semibold">' + escapeHtml(item.product_name || '-') + '</div><div class="small text-secondary">#' + escapeHtml(item.id || '') + ' | ' + escapeHtml(item.sku || '-') + ' | kategoria: ' + escapeHtml(item.category_name || '-') + '</div></button>';
      });
      categoryProductSuggestions.innerHTML = html;
      categoryProductSuggestions.classList.remove('bulk-ops-hidden');

      Array.prototype.slice.call(categoryProductSuggestions.querySelectorAll('.js-category-product-suggestion')).forEach(function (button) {
        button.addEventListener('click', function () {
          var item = {
            id: button.getAttribute('data-id') || '',
            sku: button.getAttribute('data-sku') || '',
            product_name: button.getAttribute('data-name') || '',
            category_name: button.getAttribute('data-category-name') || '',
            category_allegro_id: button.getAttribute('data-category-allegro-id') || ''
          };

          categorySourceSelected.innerHTML = '<div class="fw-semibold">Produkt do sugestii kategorii</div><div>#' + escapeHtml(item.id) + ' | ' + escapeHtml(item.sku || '-') + '</div><div class="text-secondary">' + escapeHtml(item.product_name || '-') + '</div><div class="small text-secondary mt-1">Kategoria magazynowa: ' + escapeHtml(item.category_name || '-') + '</div>';
          categorySourceSelected.classList.remove('bulk-ops-hidden');
          suggestedCategory = item.category_allegro_id ? {
            id: item.category_allegro_id,
            name: item.category_name || '',
            path: item.category_name || '',
            sourceLabel: 'Na podstawie produktu z wyszukiwania: ' + (item.product_name || '')
          } : null;
          renderSuggestedCategory();
          categoryProductSuggestions.classList.add('bulk-ops-hidden');
        });
      });
    }

    function searchCategoryProducts() {
      var query = categoryProductSearchInput ? categoryProductSearchInput.value.trim() : '';
      var meta = firstCheckedOfferMeta();
      var offerName = meta && meta.offerName ? meta.offerName : '';
      if (query.length < 2 && offerName.length < 3) {
        categoryProductSuggestions.classList.add('bulk-ops-hidden');
        return;
      }

      fetch('{$baseUrl|escape:"javascript"}?controller=allegro&action=warehouseproducts&q=' + encodeURIComponent(query) + '&offer_name=' + encodeURIComponent(offerName), { credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          renderCategoryProductSuggestions(data && data.items ? data.items : []);
        })
        .catch(function () {
          categoryProductSuggestions.innerHTML = '<div class="list-group-item text-danger">Blad pobierania produktow magazynowych.</div>';
          categoryProductSuggestions.classList.remove('bulk-ops-hidden');
        });
    }

    operationSelect.addEventListener('change', refreshSuggestedCategoryFromSelection);
    checkboxes.forEach(function (item) {
      item.addEventListener('change', refreshSuggestedCategoryFromSelection);
    });

    if (categoryProductSearchInput) {
      categoryProductSearchInput.addEventListener('input', function () {
        if (categoryProductSearchInput.value.trim() === '') {
          categorySourceSelected.innerHTML = '';
          categorySourceSelected.classList.add('bulk-ops-hidden');
          refreshSuggestedCategoryFromSelection();
        }
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(searchCategoryProducts, 220);
      });
    }

    if (categorySearchButton) {
      categorySearchButton.addEventListener('click', searchAllegroCategories);
    }

    if (categorySearchInput) {
      categorySearchInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          searchAllegroCategories();
        }
      });
    }

    bulkForm.addEventListener('submit', function (event) {
      if (isCategoryOperation() && (!categoryIdInput || !categoryIdInput.value.trim())) {
        event.preventDefault();
        window.alert('Wybierz kategorie Allegro przed dodaniem zmian do kolejki.');
      }
    });

    refreshSuggestedCategoryFromSelection();
  })();

  (function () {
    var bulkForm = document.getElementById('allegro-bulk-form');
    if (!bulkForm) {
      return;
    }

    var checkboxes = Array.prototype.slice.call(document.querySelectorAll('.js-offer-select'));
    var checkAll = document.getElementById('offers-check-all');
    var selectPage = document.getElementById('offers-select-page');
    var clearPage = document.getElementById('offers-clear-page');
    var invertPage = document.getElementById('offers-invert-page');
    var counter = document.getElementById('selected-offers-counter');
    var operationSelect = document.getElementById('bulk-operation-select');
    var operationTitle = document.getElementById('bulk-operation-title');
    var operationDescription = document.getElementById('bulk-operation-description');
    var submitHint = document.getElementById('bulk-submit-hint');
    var valueLabel = document.getElementById('bulk-value-label');
    var valueInput = document.getElementById('bulk-value-input');
    var valueHelp = document.getElementById('bulk-value-help');
    var bulkSkuInput = document.getElementById('bulk-sku-input');
    var warehouseSearchInput = document.getElementById('bulk-warehouse-search');
    var warehouseSuggestions = document.getElementById('bulk-warehouse-suggestions');
    var warehouseSelected = document.getElementById('bulk-warehouse-selected');
    var warehouseProductIdInput = document.getElementById('bulk-warehouse-product-id');
    var selectionChoices = Array.prototype.slice.call(document.querySelectorAll('.js-selection-choice'));
    var selectionScopeInputs = Array.prototype.slice.call(bulkForm.querySelectorAll('input[name="selection_scope"]'));
    var bulkFields = Array.prototype.slice.call(document.querySelectorAll('.js-bulk-field'));
    var lastChecked = null;
    var warehouseSearchTimer = null;
    var operationMeta = {
      replace_name: {
        title: 'Nazwa: znajdz i zamien',
        description: 'Podmieni wskazana fraze w nazwach ofert. Dobre do masowego poprawiania literowek, marek albo dopiskow.',
        hint: 'Masowa podmiana nazwy',
        valueLabel: 'Wartosc',
        valuePlaceholder: '',
        valueHelp: ''
      },
      set_name: {
        title: 'Nazwa: ustaw recznie',
        description: 'Nadpisze cala nazwe oferty jedna, nowa wartoscia.',
        hint: 'Pelne nadpisanie nazwy',
        valueLabel: 'Nowa nazwa',
        valuePlaceholder: 'np. Etui MagSafe iPhone 15 Pro czarne',
        valueHelp: 'Ta wartosc zastapi cala nazwe oferty.'
      },
      set_sku: {
        title: 'Ustaw SKU',
        description: 'Mozesz wpisac SKU recznie albo wybrac produkt z magazynu. Po kliknieciu produktu ustawimy jego SKU na ofertach Allegro.',
        hint: 'Ustawienie SKU recznie albo z magazynu',
        valueLabel: 'Wartosc',
        valuePlaceholder: '',
        valueHelp: ''
      },
      set_price: {
        title: 'Cena: ustaw recznie',
        description: 'Ustawi jedna, wspolna cene dla calej paczki ofert.',
        hint: 'Reczne ustawienie ceny',
        valueLabel: 'Nowa cena',
        valuePlaceholder: 'np. 49.99',
        valueHelp: 'Wpisz kwote brutto w formacie 49.99.'
      },
      set_price_from_product: {
        title: 'Cena: z magazynu',
        description: 'Pobierze cene brutto z przypietego produktu magazynowego i wysle ja na Allegro.',
        hint: 'Cena pobrana z magazynu',
        valueLabel: 'Wartosc',
        valuePlaceholder: '',
        valueHelp: ''
      },
      set_category_parameters: {
        title: 'Kategoria i parametry',
        description: 'Na podstawie pierwszej zaznaczonej oferty wybierzesz sugerowana kategorie, a potem ustawisz parametry Allegro.',
        hint: 'Zmiana kategorii i parametrow Allegro',
        valueLabel: 'Wartosc',
        valuePlaceholder: '',
        valueHelp: ''
      },
      set_delivery: {
        title: 'Dostawa: ustaw czas wysylki',
        description: 'Ustawi wspolny czas realizacji wysylki dla calej zaznaczonej paczki ofert.',
        hint: 'Ustawienie czasu wysylki',
        valueLabel: 'Wartosc',
        valuePlaceholder: '',
        valueHelp: ''
      },
      set_invoice: {
        title: 'Faktura: ustaw opcje',
        description: 'Zmienisz sposob wystawiania faktury dla calego zestawu ofert jednym ruchem.',
        hint: 'Ustawienie opcji faktury',
        valueLabel: 'Wartosc',
        valuePlaceholder: '',
        valueHelp: ''
      },
      link_product_auto: {
        title: 'Produkt Allegro: auto',
        description: 'Sprobujemy automatycznie dopiac produkt katalogowy Allegro po danych oferty, EAN albo nazwie.',
        hint: 'Automatyczne laczenie z katalogiem Allegro',
        valueLabel: 'Wartosc',
        valuePlaceholder: '',
        valueHelp: ''
      },
      link_product_id: {
        title: 'Produkt Allegro: ustaw ID',
        description: 'Podepnie wskazany produkt katalogowy Allegro po konkretnym ID.',
        hint: 'Reczne przypiecie produktu Allegro',
        valueLabel: 'Wartosc',
        valuePlaceholder: '',
        valueHelp: ''
      },
      clear_queue: {
        title: 'Usuń z kolejki',
        description: 'Wyczyści aktywne wpisy kolejki tylko dla zaznaczonych ofert albo dla bieżącego filtrowania. Nie rusza całej kolejki globalnie.',
        hint: 'Czyszczenie wpisów kolejki z bieżącego zakresu',
        valueLabel: 'Wartość',
        valuePlaceholder: '',
        valueHelp: ''
      },
      remove_from_system: {
        title: 'Usuń z systemu',
        description: 'Usunie ofertę tylko lokalnie z tabeli allegro_offers. Jeśli Allegro nadal ją zwróci przy syncu, pojawi się znowu.',
        hint: 'Lokalne usunięcie oferty z systemu',
        valueLabel: 'Wartość',
        valuePlaceholder: '',
        valueHelp: ''
      },
      remove_from_system_forever: {
        title: 'Usuń z systemu na zawsze',
        description: 'Najpierw dopilnuje zakończenia oferty, potem usunie ją lokalnie i doda do trwałych wykluczeń, więc sync już jej nie pobierze.',
        hint: 'Trwałe usunięcie i blokada ponownego pobrania',
        valueLabel: 'Wartość',
        valuePlaceholder: '',
        valueHelp: ''
      },
      end_offer: {
        title: 'Zakoncz oferty',
        description: 'Wysle polecenie zakonczenia wskazanych ofert Allegro.',
        hint: 'Zakonczenie ofert',
        valueLabel: 'Wartosc',
        valuePlaceholder: '',
        valueHelp: ''
      },
      resume_offer: {
        title: 'Wznow oferty',
        description: 'Wysle polecenie wznowienia wskazanych ofert Allegro.',
        hint: 'Wznowienie ofert',
        valueLabel: 'Wartosc',
        valuePlaceholder: '',
        valueHelp: ''
      }
    };

    function updateCounter() {
      var selectable = checkboxes.filter(function (item) { return !item.disabled; });
      var checked = selectable.filter(function (item) { return item.checked; }).length;
      if (counter) {
        counter.textContent = 'Zaznaczone na stronie: ' + checked;
      }
      if (checkAll) {
        checkAll.disabled = selectable.length === 0;
        checkAll.checked = selectable.length > 0 && checked === selectable.length;
        checkAll.indeterminate = checked > 0 && checked < selectable.length;
      }
    }

    function setAll(state) {
      checkboxes.forEach(function (item) {
        if (item.disabled) {
          return;
        }
        item.checked = state;
      });
      updateCounter();
    }

    function invertAll() {
      checkboxes.forEach(function (item) {
        if (item.disabled) {
          return;
        }
        item.checked = !item.checked;
      });
      updateCounter();
    }

    function selectedScope() {
      var current = bulkForm.querySelector('input[name="selection_scope"]:checked');
      return current ? current.value : 'selected';
    }

    function refreshSelectionChoices() {
      selectionChoices.forEach(function (item) {
        var input = item.querySelector('input[name="selection_scope"]');
        item.classList.toggle('active', !!(input && input.checked));
      });
    }

    function refreshBulkOperationUI() {
      var operation = operationSelect ? operationSelect.value : 'replace_name';
      var meta = operationMeta[operation] || operationMeta.replace_name;

      if (operationTitle) {
        operationTitle.textContent = meta.title;
      }
      if (operationDescription) {
        operationDescription.textContent = meta.description;
      }
      if (submitHint) {
        submitHint.textContent = meta.hint + ' dla ' + (selectedScope() === 'selected' ? 'zaznaczonych ofert.' : 'calego filtrowania.');
      }
      if (valueLabel) {
        valueLabel.textContent = meta.valueLabel || 'Wartosc';
      }
      if (valueInput) {
        valueInput.placeholder = meta.valuePlaceholder || '';
      }
      if (valueHelp) {
        valueHelp.textContent = meta.valueHelp || '';
      }

      bulkFields.forEach(function (field) {
        var ops = String(field.getAttribute('data-ops') || '').split(',');
        var visible = ops.indexOf(operation) !== -1;
        field.classList.toggle('bulk-ops-hidden', !visible);
      });

      if (operation !== 'set_sku' && warehouseSuggestions) {
        warehouseSuggestions.classList.add('bulk-ops-hidden');
      }
      if (operation !== 'set_sku' && warehouseSelected) {
        warehouseSelected.classList.add('bulk-ops-hidden');
      }
    }

    function firstCheckedOfferMeta() {
      var checked = checkboxes.find(function (item) { return item.checked; });
      if (!checked) {
        return null;
      }

      return {
        name: checked.getAttribute('data-offer-name') || '',
        offerId: checked.getAttribute('data-offer-id') || ''
      };
    }

    function clearWarehouseSelection() {
      if (warehouseProductIdInput) {
        warehouseProductIdInput.value = '';
      }
      if (warehouseSelected) {
        warehouseSelected.innerHTML = '';
        warehouseSelected.classList.add('bulk-ops-hidden');
      }
    }

    function renderWarehouseSelection(item) {
      if (!warehouseSelected || !warehouseProductIdInput) {
        return;
      }

      warehouseProductIdInput.value = item && item.id ? String(item.id) : '';
      if (!item || !item.id) {
        clearWarehouseSelection();
        return;
      }

      warehouseSelected.innerHTML = ''
        + '<div class="fw-semibold">Wybrany produkt magazynowy</div>'
        + '<div>#' + String(item.id) + ' | ' + String(item.sku || '-') + '</div>'
        + '<div class="text-secondary">' + String(item.product_name || '-') + '</div>'
        + '<button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="bulk-clear-warehouse-selection">Wyczysc wybor</button>';
      warehouseSelected.classList.remove('bulk-ops-hidden');

      var clearBtn = document.getElementById('bulk-clear-warehouse-selection');
      if (clearBtn) {
        clearBtn.addEventListener('click', function () {
          clearWarehouseSelection();
        });
      }
    }

    function renderWarehouseSuggestions(items) {
      if (!warehouseSuggestions) {
        return;
      }

      if (!items || !items.length) {
        warehouseSuggestions.innerHTML = '<div class="list-group-item text-secondary">Brak pasujacych produktow.</div>';
        warehouseSuggestions.classList.remove('bulk-ops-hidden');
        return;
      }

      var html = '';
      items.forEach(function (item) {
        html += ''
          + '<button type="button" class="list-group-item list-group-item-action js-warehouse-suggestion"'
          + ' data-id="' + String(item.id || '') + '"'
          + ' data-sku="' + String(item.sku || '').replace(/"/g, '&quot;') + '"'
          + ' data-name="' + String(item.product_name || '').replace(/"/g, '&quot;') + '">'
          + '  <div class="fw-semibold">' + String(item.product_name || '-') + '</div>'
          + '  <div class="small text-secondary">#' + String(item.id || '') + ' | ' + String(item.sku || '-') + ' | stan: ' + String(item.quantity || 0) + '</div>'
          + '</button>';
      });
      warehouseSuggestions.innerHTML = html;
      warehouseSuggestions.classList.remove('bulk-ops-hidden');

      Array.prototype.slice.call(warehouseSuggestions.querySelectorAll('.js-warehouse-suggestion')).forEach(function (button) {
        button.addEventListener('click', function () {
          var item = {
            id: button.getAttribute('data-id') || '',
            sku: button.getAttribute('data-sku') || '',
            product_name: button.getAttribute('data-name') || ''
          };
          if (bulkSkuInput) {
            bulkSkuInput.value = item.sku || '';
          }
          if (valueInput) {
            valueInput.value = item.sku || '';
          }
          renderWarehouseSelection(item);
        });
      });
    }

    function searchWarehouseProducts() {
      if (!warehouseSearchInput || !warehouseSuggestions) {
        return;
      }

      var query = warehouseSearchInput.value ? warehouseSearchInput.value.trim() : '';
      var selectedMeta = firstCheckedOfferMeta();
      var offerName = selectedMeta && selectedMeta.name ? selectedMeta.name : '';

      if (query.length < 2 && offerName.length < 3) {
        warehouseSuggestions.classList.add('bulk-ops-hidden');
        return;
      }

      var url = '{$baseUrl|escape:"javascript"}?controller=allegro&action=warehouseproducts&q='
        + encodeURIComponent(query)
        + '&offer_name=' + encodeURIComponent(offerName);

      fetch(url, { credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          renderWarehouseSuggestions(data && data.items ? data.items : []);
        })
        .catch(function () {
          warehouseSuggestions.innerHTML = '<div class="list-group-item text-danger">Blad pobierania produktow magazynowych.</div>';
          warehouseSuggestions.classList.remove('bulk-ops-hidden');
        });
    }

    checkboxes.forEach(function (item, index) {
      item.addEventListener('click', function (event) {
        if (item.disabled) {
          event.preventDefault();
          return;
        }
        if (event.shiftKey && lastChecked !== null) {
          var start = Math.min(lastChecked, index);
          var end = Math.max(lastChecked, index);
          var targetState = item.checked;
          for (var i = start; i <= end; i++) {
            if (checkboxes[i].disabled) {
              continue;
            }
            checkboxes[i].checked = targetState;
          }
        }
        lastChecked = index;
        updateCounter();
        if (operationSelect && operationSelect.value === 'set_sku') {
          searchWarehouseProducts();
        }
      });
    });

    if (checkAll) {
      checkAll.addEventListener('change', function () {
        setAll(!!checkAll.checked);
      });
    }

    selectionScopeInputs.forEach(function (input) {
      input.addEventListener('change', function () {
        refreshSelectionChoices();
        refreshBulkOperationUI();
      });
    });

    if (operationSelect) {
      operationSelect.addEventListener('change', refreshBulkOperationUI);
    }

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
      var selectedCount = checkboxes.filter(function (item) { return !item.disabled && item.checked; }).length;
      var operation = operationSelect ? operationSelect.value : '';
      var manualIdsField = bulkForm.querySelector('textarea[name="manual_offer_ids"]');
      var manualIdsValue = manualIdsField ? manualIdsField.value.trim() : '';
      var searchField = bulkForm.querySelector('input[name="search"]');
      var valueField = bulkForm.querySelector('input[name="value"]');
      var productIdField = bulkForm.querySelector('input[name="product_id"]');

      if (scope === 'selected' && selectedCount === 0) {
        event.preventDefault();
        window.alert('Zaznacz przynajmniej jedna oferte albo przelacz zakres na "Wszystkie z filtrowania".');
        return;
      }

      if ((operation === 'set_name' || operation === 'set_price') && (!valueField || !valueField.value.trim())) {
        event.preventDefault();
        window.alert('Uzupelnij wymagana wartosc dla wybranej operacji.');
        return;
      }

      if (operation === 'set_sku') {
        var manualSkuValue = bulkSkuInput ? bulkSkuInput.value.trim() : '';
        var selectedWarehouseProductId = warehouseProductIdInput ? warehouseProductIdInput.value.trim() : '';
        if (valueField) {
          valueField.value = manualSkuValue;
        }
        if (manualSkuValue === '' && selectedWarehouseProductId === '') {
          event.preventDefault();
          window.alert('Wpisz SKU recznie albo wybierz produkt z magazynu.');
          return;
        }
      }

      if (operation === 'replace_name' && (!searchField || !searchField.value.trim())) {
        event.preventDefault();
        window.alert('Uzupelnij fraze do zamiany.');
        return;
      }

      if (operation === 'link_product_id' && (!productIdField || !productIdField.value.trim())) {
        event.preventDefault();
        window.alert('Podaj Allegro product ID.');
        return;
      }

      if (operation === 'set_delivery') {
        var deliveryField = bulkForm.querySelector('select[name="delivery_value"]');
        if (!deliveryField || !deliveryField.value.trim()) {
          event.preventDefault();
          window.alert('Wybierz czas wysylki.');
          return;
        }
      }

      if (operation === 'set_invoice') {
        var invoiceField = bulkForm.querySelector('select[name="invoice_value"]');
        if (!invoiceField || !invoiceField.value.trim()) {
          event.preventDefault();
          window.alert('Wybierz opcje faktury.');
          return;
        }
      }

      if (operation === 'remove_from_system' && !window.confirm('Usunąć wybrane oferty tylko lokalnie z systemu? Przy kolejnym syncu mogą wrócić.')) {
        event.preventDefault();
        return;
      }

      if (operation === 'remove_from_system_forever' && !window.confirm('Usunąć oferty z systemu na zawsze? System będzie sprawdzał, czy oferta jest zakończona, a potem zablokuje jej ponowne pobranie.')) {
        event.preventDefault();
        return;
      }

      if (manualIdsValue !== '' && scope === 'selected') {
        event.preventDefault();
        window.alert('Uzywasz jednoczesnie recznych ID i trybu "Tylko zaznaczone". Zostaw jedno z tych zrodel wyboru.');
      }
    });

    if (bulkSkuInput) {
      bulkSkuInput.addEventListener('input', function () {
        if (valueInput) {
          valueInput.value = bulkSkuInput.value;
        }
        if (warehouseProductIdInput && bulkSkuInput.value.trim() !== '') {
          warehouseProductIdInput.value = '';
          if (warehouseSelected) {
            warehouseSelected.classList.add('bulk-ops-hidden');
          }
        }
      });
    }

    if (warehouseSearchInput) {
      warehouseSearchInput.addEventListener('input', function () {
        window.clearTimeout(warehouseSearchTimer);
        warehouseSearchTimer = window.setTimeout(searchWarehouseProducts, 220);
      });
    }

    updateCounter();
    refreshSelectionChoices();
    refreshBulkOperationUI();
  })();

  (function () {
    var rows = Array.prototype.slice.call(document.querySelectorAll('.js-offer-row'));
    if (!rows.length) {
      return;
    }

    rows.forEach(function (row) {
      row.style.cursor = 'pointer';
      row.addEventListener('click', function (event) {
        var interactive = event.target.closest('a, button, input, textarea, select, label');
        if (interactive) {
          return;
        }
        var checkbox = row.querySelector('.js-offer-select');
        if (checkbox && !checkbox.disabled) {
          checkbox.click();
        }
      });
    });
  })();
</script>
