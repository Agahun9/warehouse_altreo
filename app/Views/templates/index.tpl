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

      {assign var=allegroQueueTotal value=$allegroQueueStats.pending+$allegroQueueStats.processing+$allegroQueueStats.done+$allegroQueueStats.error+$allegroQueueStats.retry}
      {assign var=allegroQueueRemaining value=$allegroQueueStats.pending+$allegroQueueStats.processing+$allegroQueueStats.retry}
      {if $allegroQueueTotal > 0}
        {assign var=allegroQueueDonePercent value=($allegroQueueStats.done*100)/$allegroQueueTotal}
        {assign var=allegroQueueRemainingPercent value=($allegroQueueRemaining*100)/$allegroQueueTotal}
        {assign var=allegroQueueErrorPercent value=($allegroQueueStats.error*100)/$allegroQueueTotal}
      {else}
        {assign var=allegroQueueDonePercent value=0}
        {assign var=allegroQueueRemainingPercent value=0}
        {assign var=allegroQueueErrorPercent value=0}
      {/if}

      <style>
        .dashboard-focus-card {
          position: relative;
          overflow: hidden;
          border: 0;
          border-radius: 1.25rem;
          color: #fff;
          box-shadow: 0 20px 45px rgba(15, 23, 42, 0.14);
        }

        .dashboard-focus-card::before {
          content: '';
          position: absolute;
          inset: auto -10% -35% auto;
          width: 13rem;
          height: 13rem;
          border-radius: 50%;
          background: rgba(255, 255, 255, 0.12);
        }

        .dashboard-focus-card::after {
          content: '';
          position: absolute;
          inset: -30% auto auto -10%;
          width: 10rem;
          height: 10rem;
          border-radius: 50%;
          background: rgba(255, 255, 255, 0.08);
        }

        .dashboard-focus-card .card-body {
          position: relative;
          z-index: 1;
          padding: 1.35rem;
        }

        .dashboard-focus-card-sellasist {
          background: linear-gradient(135deg, #0f766e 0%, #14b8a6 55%, #67e8f9 100%);
        }

        .dashboard-focus-card-allegro {
          background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 52%, #60a5fa 100%);
        }

        .dashboard-focus-label {
          text-transform: uppercase;
          letter-spacing: 0.08em;
          font-size: 0.74rem;
          font-weight: 700;
          opacity: 0.82;
        }

        .dashboard-focus-value {
          font-size: 2.5rem;
          font-weight: 700;
          line-height: 1;
        }

        .dashboard-focus-chip {
          display: inline-flex;
          align-items: center;
          gap: 0.35rem;
          padding: 0.35rem 0.75rem;
          border-radius: 999px;
          background: rgba(255, 255, 255, 0.14);
          color: #fff;
          font-size: 0.8rem;
          font-weight: 700;
        }

        .dashboard-mini-stat {
          border-radius: 0.95rem;
          background: rgba(255, 255, 255, 0.12);
          padding: 0.85rem 0.9rem;
          min-height: 100%;
        }

        .dashboard-mini-stat strong {
          display: block;
          font-size: 1.2rem;
          color: #fff;
        }

        .dashboard-focus-note {
          color: rgba(255, 255, 255, 0.84);
          font-size: 0.84rem;
        }

        .dashboard-sellasist-chart-wrap {
          margin: 0.4rem 0 1rem;
          padding: 0.9rem 0.9rem 0.65rem;
          border-radius: 1rem;
          background: rgba(255, 255, 255, 0.12);
        }

        .dashboard-sellasist-chart {
          width: 100%;
          height: auto;
          display: block;
        }

        .dashboard-sellasist-grid {
          stroke: rgba(255, 255, 255, 0.18);
          stroke-width: 1;
        }

        .dashboard-sellasist-line-orders {
          fill: none;
          stroke: #f8fafc;
          stroke-width: 3;
          stroke-linecap: round;
          stroke-linejoin: round;
        }

        .dashboard-sellasist-line-value {
          fill: none;
          stroke: #fde047;
          stroke-width: 3;
          stroke-linecap: round;
          stroke-linejoin: round;
        }

        .dashboard-sellasist-legend {
          display: flex;
          flex-wrap: wrap;
          gap: 0.85rem;
          margin-bottom: 0.55rem;
        }

        .dashboard-sellasist-legend-item {
          display: inline-flex;
          align-items: center;
          gap: 0.4rem;
          font-size: 0.8rem;
          font-weight: 600;
          color: rgba(255, 255, 255, 0.88);
        }

        .dashboard-sellasist-legend-line {
          width: 1.25rem;
          height: 0.2rem;
          border-radius: 999px;
          display: inline-block;
        }

        .dashboard-sellasist-legend-orders {
          background: #f8fafc;
        }

        .dashboard-sellasist-legend-value {
          background: #fde047;
        }

        .dashboard-sellasist-labels {
          display: grid;
          grid-template-columns: repeat(7, minmax(0, 1fr));
          gap: 0.35rem;
          margin-top: 0.45rem;
        }

        .dashboard-sellasist-labels span {
          text-align: center;
          font-size: 0.72rem;
          color: rgba(255, 255, 255, 0.74);
        }

        .dashboard-allegro-progress {
          display: flex;
          overflow: hidden;
          height: 0.7rem;
          border-radius: 999px;
          background: rgba(255, 255, 255, 0.18);
          box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.12);
        }

        .dashboard-allegro-progress-done {
          background: #198754;
        }

        .dashboard-allegro-progress-remaining {
          background: #f59e0b;
        }

        .dashboard-allegro-progress-error {
          background: #dc3545;
        }

        .dashboard-allegro-chip {
          display: inline-flex;
          align-items: center;
          gap: 0.35rem;
          padding: 0.24rem 0.62rem;
          border-radius: 999px;
          background: rgba(255, 255, 255, 0.14);
          color: #fff;
          font-size: 0.84rem;
          font-weight: 600;
        }
      </style>

      <div class="row">
        {foreach $stats as $stat}
          <div class="col-xl-3 col-lg-4 col-sm-6">
            <div class="small-box text-bg-{$stat.theme|escape}">
              <div class="inner">
                <h3>{$stat.value|escape}</h3>
                <p>{$stat.label|escape}</p>
              </div>
              <div class="small-box-icon"><i class="bi {$stat.icon|escape}"></i></div>
              <a href="{$baseUrl}?controller=index" class="small-box-footer link-light link-underline-opacity-0">Szczegoly <i class="bi bi-arrow-right-short"></i></a>
            </div>
          </div>
        {/foreach}
      </div>

      <div class="row">
        <div class="col-xl-4">
          <div class="card dashboard-focus-card dashboard-focus-card-sellasist mb-4">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                <div>
                  <div class="dashboard-focus-label">Sellasist dzisiaj</div>
                  <div class="dashboard-focus-value mt-2">{$sellasistTodayStats.orders_count|default:0}</div>
                </div>
                <span class="dashboard-focus-chip">magazyn</span>
              </div>

              <div class="dashboard-sellasist-chart-wrap">
                <div class="dashboard-sellasist-legend">
                  <span class="dashboard-sellasist-legend-item"><span class="dashboard-sellasist-legend-line dashboard-sellasist-legend-orders"></span>Zamowienia</span>
                  <span class="dashboard-sellasist-legend-item"><span class="dashboard-sellasist-legend-line dashboard-sellasist-legend-value"></span>Wartosc produktow</span>
                </div>

                <svg class="dashboard-sellasist-chart" viewBox="0 0 320 132" aria-label="Wykres Sellasist z ostatnich dni">
                  <line class="dashboard-sellasist-grid" x1="8" y1="18" x2="312" y2="18"></line>
                  <line class="dashboard-sellasist-grid" x1="8" y1="64" x2="312" y2="64"></line>
                  <line class="dashboard-sellasist-grid" x1="8" y1="114" x2="312" y2="114"></line>
                  <polyline class="dashboard-sellasist-line-orders" points="{$sellasistChart.orders_points|escape}"></polyline>
                  <polyline class="dashboard-sellasist-line-value" points="{$sellasistChart.value_points|escape}"></polyline>
                </svg>

                <div class="dashboard-sellasist-labels">
                  {foreach $sellasistDailySeries as $point}
                    <span>{$point.label|escape}</span>
                  {/foreach}
                </div>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-6">
                  <div class="dashboard-mini-stat">
                    <span class="dashboard-focus-label d-block mb-1">Dzis zamowienia</span>
                    <strong>{$sellasistTodayStats.orders_count|default:0}</strong>
                    <span class="dashboard-focus-note">skala: 0-{$sellasistChart.y_axis_orders[1]|default:1}</span>
                  </div>
                </div>
                <div class="col-6">
                  <div class="dashboard-mini-stat">
                    <span class="dashboard-focus-label d-block mb-1">Dzis wartosc</span>
                    <strong>{$sellasistTodayStats.total_value|default:0|string_format:'%.2f'}</strong>
                    <span class="dashboard-focus-note">{$sellasistTodayStats.currency|default:'PLN'|escape}, bez wysylki</span>
                  </div>
                </div>
              </div>

              <div class="dashboard-focus-note">Wykres pokazuje ostatnie 7 dni. Zolty wykres liczy tylko wartosc produktow z zamowien, bez kosztow wysylki.</div>
            </div>
          </div>

          <div class="card dashboard-focus-card dashboard-focus-card-allegro mb-4">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                <div>
                  <div class="dashboard-focus-label">Kolejka Allegro</div>
                  <div class="h3 mb-1 mt-2">Zostalo {$allegroQueueRemaining}</div>
                  <div class="dashboard-focus-note">Zrobione {$allegroQueueStats.done} z {$allegroQueueTotal}</div>
                </div>
                <span class="dashboard-allegro-chip">{$allegroQueueDonePercent|string_format:'%.0f'}%</span>
              </div>

              <div class="dashboard-allegro-progress mb-3" aria-label="Postep kolejki Allegro">
                {if $allegroQueueDonePercent > 0}<div class="dashboard-allegro-progress-done" style="width: {$allegroQueueDonePercent|string_format:'%.2f'}%;"></div>{/if}
                {if $allegroQueueRemainingPercent > 0}<div class="dashboard-allegro-progress-remaining" style="width: {$allegroQueueRemainingPercent|string_format:'%.2f'}%;"></div>{/if}
                {if $allegroQueueErrorPercent > 0}<div class="dashboard-allegro-progress-error" style="width: {$allegroQueueErrorPercent|string_format:'%.2f'}%;"></div>{/if}
              </div>

              <div class="row g-3 small mb-3">
                <div class="col-6">
                  <div class="dashboard-mini-stat">
                    <div class="dashboard-focus-label mb-1">Oczekuje + ponow</div>
                    <strong>{$allegroQueueStats.pending + $allegroQueueStats.retry}</strong>
                  </div>
                </div>
                <div class="col-6">
                  <div class="dashboard-mini-stat">
                    <div class="dashboard-focus-label mb-1">W toku</div>
                    <strong>{$allegroQueueStats.processing}</strong>
                  </div>
                </div>
                <div class="col-6">
                  <div class="dashboard-mini-stat">
                    <div class="dashboard-focus-label mb-1">Bledy</div>
                    <strong>{$allegroQueueStats.error}</strong>
                  </div>
                </div>
                <div class="col-6">
                  <div class="dashboard-mini-stat">
                    <div class="dashboard-focus-label mb-1">Gotowe</div>
                    <strong>{$allegroQueueStats.done}</strong>
                  </div>
                </div>
              </div>

              <a href="{$baseUrl}?controller=allegro&action=index" class="btn btn-sm btn-light">Otworz Allegro</a>
              {if $currentUser.role eq 'admin' or in_array('empik', $currentUser.modules)}
                <a href="{$baseUrl}?controller=empik&action=index" class="btn btn-sm btn-outline-light">Otworz Empik</a>
              {/if}
            </div>
          </div>

          <div class="card mb-4">
            <div class="card-header"><h3 class="card-title mb-0">Szybkie akcje</h3></div>
            <div class="card-body d-grid gap-2">
              {if $currentUser.role eq 'admin' or $currentUser.module_permissions.products|default:'' eq 'edit'}
                <a href="{$baseUrl}?controller=products&action=create" class="btn btn-primary">Dodaj produkt</a>
              {/if}
              {if $currentUser.role eq 'admin' or $currentUser.module_permissions.categories|default:'' eq 'edit'}
                <a href="{$baseUrl}?controller=categories&action=create" class="btn btn-outline-dark">Dodaj kategorie</a>
              {/if}
              {if $currentUser.role eq 'admin'}
                <a href="{$baseUrl}?controller=admin&action=users" class="btn btn-outline-secondary">Zarzadzaj uzytkownikami</a>
              {/if}
            </div>
          </div>

          <div class="card mb-4">
            <div class="card-header"><h3 class="card-title mb-0">Priorytety na dzis</h3></div>
            <div class="card-body p-0">
              <ul class="list-group list-group-flush">
                {foreach $activities as $activity}
                  <li class="list-group-item">{$activity|escape}</li>
                {/foreach}
              </ul>
            </div>
          </div>
        </div>

        <div class="col-xl-8">
          <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h3 class="card-title mb-0">Historia zmian produktow</h3>
              <a href="{$baseUrl}?controller=products&action=index" class="btn btn-sm btn-outline-primary">Otworz liste produktow</a>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Produkt</th>
                      <th>Akcja</th>
                      <th>Kto</th>
                      <th>Co sie zmienilo</th>
                      <th>Kiedy</th>
                    </tr>
                  </thead>
                  <tbody>
                    {if $recentProductChanges}
                      {foreach $recentProductChanges as $change}
                        <tr>
                          <td>
                            <div class="fw-semibold">{if $change.product_name_snapshot|default:''}{$change.product_name_snapshot|escape}{else}Produkt #{$change.product_id}{/if}</div>
                            <div class="small text-secondary">
                              <span class="me-2">ID: {$change.product_id}</span>
                              <span class="badge text-bg-secondary">{$change.product_sku_snapshot|default:'brak SKU'|escape}</span>
                            </div>
                          </td>
                          <td><span class="badge text-bg-info">{$change.action_label|escape}</span></td>
                          <td>{$change.actor_display|escape}</td>
                          <td class="small">{$change.summary|default:'Zapisano zmiany.'|escape}</td>
                          <td class="text-nowrap">{$change.created_at|default:'-'|escape}</td>
                        </tr>
                      {/foreach}
                    {else}
                      <tr>
                        <td colspan="5" class="text-center py-3">Brak historii zmian produktow.</td>
                      </tr>
                    {/if}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      {if $currentUser.role eq 'admin'}
        <div class="row">
          <div class="col-12">
            <div class="card mb-4 border-warning">
              <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                  <h3 class="card-title mb-0">Bledne wywolania Sellasist</h3>
                  <div class="small text-secondary mt-1">
                    Ostatnie nieudane wejscia na odejmowanie i dodawanie stanu. Ostatnie 24h:
                    <strong>{$sellasistFailedRequestsSummary.total|default:0}</strong>
                    {if $sellasistFailedRequestsSummary.latest_at|default:'' neq ''}
                      , ostatnie: {$sellasistFailedRequestsSummary.latest_at|escape}
                    {/if}
                  </div>
                </div>
                <a href="{$baseUrl}?controller=sellasist&action=zbieranie" class="btn btn-sm btn-outline-warning">Otworz Sellasist</a>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-sm table-striped table-hover table-bordered mb-0 align-middle">
                    <thead class="table-light">
                      <tr>
                        <th>Kiedy</th>
                        <th>Operacja</th>
                        <th>Zamowienie</th>
                        <th>Metoda</th>
                        <th>Status</th>
                        <th>Blad</th>
                        <th>IP</th>
                      </tr>
                    </thead>
                    <tbody>
                      {if $sellasistFailedRequests}
                        {foreach $sellasistFailedRequests as $failure}
                          <tr>
                            <td class="text-nowrap">{$failure.created_at|default:'-'|escape}</td>
                            <td>
                              <span class="badge text-bg-{if $failure.operation eq 'add_stock'}info{else}warning{/if}">
                                {$failure.operation|escape}
                              </span>
                            </td>
                            <td>
                              {if $failure.order_id|default:0 > 0}
                                <strong>#{$failure.order_id}</strong>
                              {else}
                                <span class="text-secondary">brak</span>
                              {/if}
                            </td>
                            <td><code>{$failure.request_method|default:'-'|escape}</code></td>
                            <td><span class="badge text-bg-danger">{$failure.response_status|default:'-'|escape}</span></td>
                            <td class="small">{$failure.error_message|default:'-'|escape}</td>
                            <td class="small text-nowrap">{$failure.remote_addr|default:'-'|escape}</td>
                          </tr>
                        {/foreach}
                      {else}
                        <tr>
                          <td colspan="7" class="text-center py-3">Brak zapisanych blednych wywolan Sellasist.</td>
                        </tr>
                      {/if}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12">
            <div class="card mb-4">
              <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Ostatnio dodani uzytkownicy</h3>
                <a href="{$baseUrl}?controller=admin&action=users" class="btn btn-sm btn-outline-dark">Panel admina</a>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-sm table-striped table-hover table-bordered mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>ID</th>
                        <th>Uzytkownik</th>
                        <th>Rola</th>
                        <th>Dostep</th>
                        <th>Status</th>
                        <th>Utworzono</th>
                      </tr>
                    </thead>
                    <tbody>
                      {if $recentUsers}
                        {foreach $recentUsers as $user}
                          <tr>
                            <td>{$user.id}</td>
                            <td>
                              <div class="fw-semibold">{if $user.first_name|default:'' neq '' or $user.last_name|default:'' neq ''}{$user.first_name|default:''|escape} {$user.last_name|default:''|escape}{else}{$user.email|escape}{/if}</div>
                              <div class="small text-secondary">{$user.email|escape}</div>
                            </td>
                            <td><span class="badge text-bg-{if $user.role eq 'admin'}dark{else}secondary{/if}">{$user.role|escape}</span></td>
                            <td><span class="badge text-bg-{if $user.permission_level eq 'read'}warning{else}primary{/if}">{if $user.permission_level eq 'read'}odczyt{else}edycja{/if}</span></td>
                            <td>
                              {if $user.is_active}<span class="badge text-bg-success">aktywne</span>{else}<span class="badge text-bg-warning">nieaktywne</span>{/if}
                              {if $user.is_blocked}<span class="badge text-bg-danger ms-1">zablokowane</span>{/if}
                            </td>
                            <td>{$user.created_at|default:'-'|escape}</td>
                          </tr>
                        {/foreach}
                      {else}
                        <tr>
                          <td colspan="6" class="text-center py-3">Brak danych o uzytkownikach.</td>
                        </tr>
                      {/if}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      {/if}
    </div>
  </div>
</main>
