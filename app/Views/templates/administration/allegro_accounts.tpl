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
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=administration&action=users">Administracja</a></li>
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
          <h3 class="card-title mb-0">Konfiguracja kont</h3>
          <div class="d-flex gap-2">
            <a href="{$baseUrl}?controller=administration&action=automation" class="btn btn-sm btn-outline-primary">Administracja</a>
            <a href="{$baseUrl}?controller=allegro&action=index" class="btn btn-sm btn-outline-secondary">Wroc do ofert</a>
          </div>
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
                            <a href="{$baseUrl}?controller=allegro&action=refreshtoken&account={$account.slug|escape:'url'}" class="btn btn-sm btn-outline-info">Refresh</a>
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
    </div>
  </div>
</main>
