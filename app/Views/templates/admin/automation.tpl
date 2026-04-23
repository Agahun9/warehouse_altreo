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
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=admin&action=users">Admin</a></li>
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

      <div class="row g-4 mb-4">
        <div class="col-lg-4">
          <div class="card h-100">
            <div class="card-body">
              <div class="text-secondary small">Kolejka oczekujaca</div>
              <div class="display-6 fw-semibold">{$queueStats.pending + $queueStats.retry}</div>
              <div class="small text-secondary">w toku: {$queueStats.processing} | bledy: {$queueStats.error}</div>
            </div>
          </div>
        </div>
        <div class="col-lg-8">
          <div class="card h-100">
            <div class="card-body">
              <div class="fw-semibold mb-2">Jak to ustawic</div>
              <div class="small text-secondary mb-2">Najlepiej dac dwa crony:</div>
              <div class="small text-secondary">1. Worker kolejki co 1 minute.</div>
              <div class="small text-secondary">2. Pelne maintenance z syncem co 5-15 minut.</div>
              <div class="small text-secondary mt-2">Jesli nie chcesz crona od razu, nizej masz tez auto-worker w przegladarce, ktory odpala sie co minute, gdy ten ekran jest otwarty.</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0">Konta Allegro</h3>
          <a href="{$baseUrl}?controller=allegro&action=index" class="btn btn-sm btn-outline-secondary">Wroc do ofert</a>
        </div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-lg-4">
              <form method="post" action="{$baseUrl}?controller=allegro&action=saveaccount" class="row g-3">
                <input type="hidden" name="account_id" value="">
                <div class="col-12">
                  <label class="form-label">Nazwa konta</label>
                  <input type="text" name="name" class="form-control" placeholder="np. altreo-market" required>
                </div>
                <div class="col-12">
                  <label class="form-label">Client ID</label>
                  <input type="text" name="client_id" class="form-control" required>
                </div>
                <div class="col-12">
                  <label class="form-label">Client Secret</label>
                  <input type="text" name="client_secret" class="form-control" required>
                </div>
                <div class="col-12">
                  <label class="form-label">Redirect URI</label>
                  <input type="url" name="redirect_uri" class="form-control" value="{$defaultRedirectUri|escape}" required>
                </div>
                <div class="col-12">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="allegro_active_default" checked>
                    <label class="form-check-label" for="allegro_active_default">Konto aktywne</label>
                  </div>
                </div>
                <div class="col-12">
                  <button type="submit" class="btn btn-primary">Zapisz konto</button>
                </div>
              </form>
            </div>
            <div class="col-lg-8">
              <div class="alert alert-light border small text-secondary">
                Refresh tokena dziala poprawnie nawet wtedy, gdy nowy <code>access_token</code> nadal wygasa jeszcze tego samego dnia.
                Allegro zwykle nadaje mu po prostu kolejny ograniczony czas waznosci, a nie wielodniowy termin.
              </div>
              <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle">
                  <thead class="table-light">
                    <tr>
                      <th>Konto</th>
                      <th>Status</th>
                      <th>Token</th>
                      <th>Sync</th>
                      <th>Trigger cron</th>
                      <th>Akcje</th>
                    </tr>
                  </thead>
                  <tbody>
                    {foreach $accounts as $account}
                      <tr>
                        <td>
                          <div class="fw-semibold">{$account.name|escape}</div>
                          <div class="small text-secondary">slug: {$account.slug|escape}</div>
                        </td>
                        <td>
                          {if $account.is_active}
                            <span class="badge text-bg-success">Aktywne</span>
                          {else}
                            <span class="badge text-bg-secondary">Nieaktywne</span>
                          {/if}
                          {if $account.is_running}
                            <div class="small text-warning mt-1">Sync trwa, offset {$account.offer_offset|default:0}</div>
                          {/if}
                          {if $account.sync_last_error_message}
                            <div class="small text-danger mt-1">{$account.sync_last_error_message|escape}</div>
                          {/if}
                        </td>
                        <td>
                          {if $account.token_expires_at}
                            <div class="small">wazny do</div>
                            <div>{$account.token_expires_at|escape}</div>
                            <div class="small text-secondary mt-1">odswiezono: {$account.token_updated_at|default:'-'|escape}</div>
                          {else}
                            <span class="text-secondary">brak autoryzacji</span>
                          {/if}
                        </td>
                        <td>
                          <div class="small">pelny: {$account.last_full_sync_at|default:'-'|escape}</div>
                          <div class="small">ostatni OK: {$account.last_success_at|default:'-'|escape}</div>
                        </td>
                        <td style="min-width: 280px;">
                          <div class="small text-secondary mb-1">sync</div>
                          <input type="text" class="form-control form-control-sm mb-2" readonly value="{$account.trigger_url|escape}">
                          <div class="small text-secondary mb-1">maintenance + kolejka</div>
                          <input type="text" class="form-control form-control-sm" readonly value="{$baseUrl}?controller=allegro&action=maintenance&account={$account.slug|escape:'url'}&sync=1&queue_limit=100">
                        </td>
                        <td class="text-nowrap">
                          <div class="d-grid gap-2">
                            <a href="{$baseUrl}?controller=allegro&action=connect&id={$account.id}" class="btn btn-sm btn-primary">Autoryzuj</a>
                            <a href="{$account.trigger_url|escape}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noreferrer">Sync</a>
                            <a href="{$baseUrl}?controller=allegro&action=refreshtoken&account={$account.slug|escape:'url'}" class="btn btn-sm btn-outline-info">Refresh token</a>
                            <form method="post" action="{$baseUrl}?controller=allegro&action=saveaccount" class="d-grid">
                              <input type="hidden" name="account_id" value="{$account.id|escape}">
                              <input type="hidden" name="name" value="{$account.name|escape}">
                              <input type="hidden" name="client_id" value="{$account.client_id|escape}">
                              <input type="hidden" name="client_secret" value="{$account.client_secret|escape}">
                              <input type="hidden" name="redirect_uri" value="{$account.redirect_uri|escape}">
                              <input type="hidden" name="is_active" value="{if $account.is_active}0{else}1{/if}">
                              <button type="submit" class="btn btn-sm {if $account.is_active}btn-outline-warning{else}btn-outline-success{/if}">
                                {if $account.is_active}Wylacz konto{else}Wlacz konto{/if}
                              </button>
                            </form>
                          </div>
                        </td>
                      </tr>
                    {foreachelse}
                      <tr><td colspan="6" class="text-center text-secondary py-4">Brak skonfigurowanych kont Allegro.</td></tr>
                    {/foreach}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4 border-warning-subtle">
        <div class="card-header">
          <h3 class="card-title mb-0">Sprzatanie bazy</h3>
        </div>
        <div class="card-body">
          <form method="post" action="{$baseUrl}?controller=admin&action=cleanup" class="row g-3 align-items-end">
            <div class="col-lg-4">
              <label class="form-label" for="cleanup-queue-done-days">Usun zakonczona kolejke starsza niz dni</label>
              <input type="number" min="1" step="1" class="form-control" id="cleanup-queue-done-days" name="queue_done_days" value="14">
            </div>
            <div class="col-lg-4">
              <label class="form-label" for="cleanup-queue-error-days">Usun bledy i retry starsze niz dni</label>
              <input type="number" min="1" step="1" class="form-control" id="cleanup-queue-error-days" name="queue_error_days" value="30">
            </div>
            <div class="col-lg-4">
              <label class="form-label" for="cleanup-deleted-products-days">Usun produkty skasowane starsze niz dni</label>
              <input type="number" min="1" step="1" class="form-control" id="cleanup-deleted-products-days" name="deleted_products_days" value="30">
            </div>
            <div class="col-12">
              <div class="small text-secondary">
                Ten przycisk czysci stare wpisy z <code>allegro_offer_change_queue</code>, odpina oferty Allegro od produktow, ktore sa juz oznaczone jako usuniete, i trwale usuwa stare produkty po soft-delecie razem z ich logami zmian.
              </div>
            </div>
            <div class="col-12 d-flex justify-content-between gap-2 align-items-center flex-wrap">
              <div class="small text-secondary">
                Uzywaj ostroznie: ostatni krok wykonuje trwale usuniecie produktow, ktore byly juz oznaczone jako skasowane.
              </div>
              <button type="submit" class="btn btn-outline-warning">Uruchom sprzatanie bazy</button>
            </div>
          </form>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">
          <h3 class="card-title mb-0">Sellasist API</h3>
        </div>
        <div class="card-body">
          <form method="post" action="{$baseUrl}?controller=admin&action=savesellasist" class="row g-3">
            <div class="col-lg-5">
              <label class="form-label" for="sellasist-base-url">Adres Sellasist</label>
              <input type="url" class="form-control" id="sellasist-base-url" name="sellasist_base_url" value="{$sellasistBaseUrl|escape}" placeholder="https://altreo.sellasist.pl">
            </div>
            <div class="col-lg-7">
              <label class="form-label" for="sellasist-api-key">API Key</label>
              <input type="text" class="form-control" id="sellasist-api-key" name="sellasist_api_key" value="{$sellasistApiKey|escape}" placeholder="Wklej klucz API Sellasist">
            </div>
            <div class="col-12">
              <div class="small text-secondary">
                Dane sa uzywane przez modul Sellasist, zakladke Zbieranie oraz generowanie naklejek. Domyslnie pobierane sa zamowienia ze statusu <code>23</code>, a po wygenerowaniu naklejek system zmienia status na <code>3</code>.
              </div>
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-primary">Zapisz ustawienia Sellasist</button>
            </div>
          </form>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">
          <h3 class="card-title mb-0">Globalne linki cron</h3>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Worker kolejki co 1 minute</label>
            <input type="text" class="form-control" readonly value="{$automation.queue_worker|escape}" id="globalQueueWorkerUrl">
          </div>
          <div class="mb-3">
            <label class="form-label">Pelne maintenance co 5-15 minut</label>
            <input type="text" class="form-control" readonly value="{$automation.full_maintenance|escape}" id="globalMaintenanceUrl">
          </div>
          <div class="mb-0">
            <label class="form-label">Same refresh tokenow</label>
            <input type="text" class="form-control" readonly value="{$automation.refresh_tokens|escape}">
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0">Auto-worker w panelu</h3>
          <span class="badge text-bg-info">Opcjonalny</span>
        </div>
        <div class="card-body">
          <div class="row g-3 align-items-end">
            <div class="col-lg-4">
              <label class="form-label">Tryb</label>
              <select id="browserWorkerMode" class="form-select">
                <option value="queue">Tylko kolejka</option>
                <option value="maintenance">Maintenance + sync</option>
              </select>
            </div>
            <div class="col-lg-3">
              <label class="form-label">Interwal sekund</label>
              <input type="number" min="30" step="30" id="browserWorkerInterval" class="form-control" value="60">
            </div>
            <div class="col-lg-5 d-flex gap-2">
              <button type="button" class="btn btn-primary" id="browserWorkerStart">Start</button>
              <button type="button" class="btn btn-outline-secondary" id="browserWorkerStop">Stop</button>
            </div>
            <div class="col-12">
              <div class="small text-secondary" id="browserWorkerStatus">Auto-worker zatrzymany.</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title mb-0">Linki per konto</h3>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Konto</th>
                <th>Status</th>
                <th>Sync</th>
                <th>Kolejka</th>
                <th>Maintenance</th>
              </tr>
            </thead>
            <tbody>
              {foreach $automation.accounts as $item}
                <tr>
                  <td>
                    <div class="fw-semibold">{$item.name|escape}</div>
                    <div class="small text-secondary">{$item.slug|escape}</div>
                  </td>
                  <td>
                    {if $item.is_active}
                      <span class="badge text-bg-success">Aktywne</span>
                    {else}
                      <span class="badge text-bg-secondary">Nieaktywne</span>
                    {/if}
                  </td>
                  <td><input type="text" class="form-control form-control-sm" readonly value="{$item.sync|escape}"></td>
                  <td><input type="text" class="form-control form-control-sm" readonly value="{$item.queue_only|escape}"></td>
                  <td><input type="text" class="form-control form-control-sm" readonly value="{$item.maintenance|escape}"></td>
                </tr>
              {foreachelse}
                <tr><td colspan="5" class="text-center text-secondary py-4">Brak kont Allegro do automatyzacji.</td></tr>
              {/foreach}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</main>
<script>
  (function () {
    var queueUrlInput = document.getElementById('globalQueueWorkerUrl');
    var maintenanceUrlInput = document.getElementById('globalMaintenanceUrl');
    var startBtn = document.getElementById('browserWorkerStart');
    var stopBtn = document.getElementById('browserWorkerStop');
    var modeInput = document.getElementById('browserWorkerMode');
    var intervalInput = document.getElementById('browserWorkerInterval');
    var statusNode = document.getElementById('browserWorkerStatus');
    var timer = null;

    function setStatus(text) {
      if (statusNode) {
        statusNode.textContent = text;
      }
    }

    function currentUrl() {
      if (modeInput && modeInput.value === 'maintenance' && maintenanceUrlInput) {
        return maintenanceUrlInput.value || '';
      }
      return queueUrlInput ? (queueUrlInput.value || '') : '';
    }

    function runOnce() {
      var url = currentUrl();
      if (!url) {
        setStatus('Brak URL do uruchomienia auto-workera.');
        return;
      }

      setStatus('Auto-worker uruchamia: ' + url);
      fetch(url, { credentials: 'same-origin' })
        .then(function (response) { return response.text().then(function (body) { return { ok: response.ok, body: body }; }); })
        .then(function (result) {
          var stamp = new Date().toLocaleTimeString();
          setStatus('Ostatnie odpalenie ' + stamp + ': ' + (result.ok ? 'OK' : 'blad') + ' | ' + result.body.substring(0, 220));
        })
        .catch(function (error) {
          setStatus('Blad auto-workera: ' + error);
        });
    }

    function startWorker() {
      stopWorker();
      var every = Math.max(30, parseInt(intervalInput && intervalInput.value ? intervalInput.value : '60', 10) || 60) * 1000;
      runOnce();
      timer = window.setInterval(runOnce, every);
      setStatus('Auto-worker wlaczony. Interwal: ' + (every / 1000) + ' s.');
    }

    function stopWorker() {
      if (timer) {
        window.clearInterval(timer);
        timer = null;
      }
      setStatus('Auto-worker zatrzymany.');
    }

    if (startBtn) {
      startBtn.addEventListener('click', startWorker);
    }
    if (stopBtn) {
      stopBtn.addEventListener('click', stopWorker);
    }
  })();
</script>
