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

      <style>
        .administration-hero {
          overflow: hidden;
          border: 0;
          border-radius: 1.25rem;
          background:
            radial-gradient(circle at top right, rgba(255, 255, 255, 0.18), transparent 28%),
            linear-gradient(135deg, #0f172a 0%, #1d4ed8 50%, #60a5fa 100%);
          color: #fff;
          box-shadow: 0 20px 50px rgba(15, 23, 42, 0.16);
        }

        .administration-hero .card-body {
          padding: 1.6rem;
        }

        .administration-hero-title {
          margin: 0 0 0.45rem;
          font-size: 1.5rem;
          font-weight: 700;
          line-height: 1.1;
        }

        .administration-hero-copy {
          max-width: 52rem;
          margin: 0;
          color: rgba(255, 255, 255, 0.84);
          line-height: 1.55;
        }

        .administration-hero-actions {
          display: flex;
          flex-wrap: wrap;
          gap: 0.65rem;
          justify-content: flex-end;
        }

        .administration-hero .btn-light {
          border: 0;
          font-weight: 600;
        }

        .administration-summary-card,
        .administration-panel,
        .administration-form-card {
          border-radius: 1rem;
          border: 1px solid rgba(15, 23, 42, 0.08);
          box-shadow: 0 12px 30px rgba(15, 23, 42, 0.05);
        }

        .administration-summary-card .card-body {
          padding: 1.15rem 1.2rem;
        }

        .administration-summary-label {
          color: #64748b;
          font-size: 0.86rem;
        }

        .administration-summary-value {
          margin-top: 0.35rem;
          font-size: 1.9rem;
          font-weight: 700;
          line-height: 1;
          color: #0f172a;
        }

        .administration-summary-meta {
          margin-top: 0.5rem;
          color: #64748b;
          font-size: 0.86rem;
        }

        .administration-pill-nav {
          display: flex;
          flex-wrap: wrap;
          gap: 0.6rem;
        }

        .administration-pill-nav .nav-link {
          border-radius: 999px;
          padding: 0.6rem 0.95rem;
          font-weight: 600;
        }

        .administration-accordion .accordion-item {
          border: 1px solid rgba(15, 23, 42, 0.08);
          border-radius: 1rem;
          overflow: hidden;
          box-shadow: 0 12px 30px rgba(15, 23, 42, 0.04);
        }

        .administration-accordion .accordion-button {
          font-weight: 700;
          padding: 1rem 1.15rem;
        }

        .administration-accordion .accordion-button:not(.collapsed) {
          color: #0f172a;
          background: linear-gradient(180deg, #f8fbff, #eef4ff);
          box-shadow: none;
        }

        .administration-note {
          border-radius: 1rem;
          border: 1px solid rgba(15, 23, 42, 0.08);
          background: linear-gradient(180deg, #ffffff, #f8fafc);
        }

        .administration-market-tabs .nav-link {
          font-weight: 600;
        }

        .administration-market-tabs .nav-link.active {
          background: #0d6efd;
          color: #fff;
        }

        .administration-inline-code input.form-control[readonly] {
          background: #f8fafc;
        }

        .administration-backup-box {
          border-radius: 1rem;
          border: 1px solid rgba(15, 23, 42, 0.08);
          background: linear-gradient(180deg, #ffffff, #f8fafc);
          padding: 1rem;
          height: 100%;
        }

        @media (max-width: 991.98px) {
          .administration-hero-actions {
            justify-content: flex-start;
          }
        }
      </style>

      <div class="card administration-hero mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
          <div>
            <h3 class="administration-hero-title">Centrum administracji i integracji</h3>
            <p class="administration-hero-copy">W jednym miejscu masz konta marketplace, token API, Sellasist, crony i maintenance. Zostawilem wszystkie dotychczasowe funkcje, ale sa teraz poukladane w osobne sekcje, zeby latwiej bylo znalezc to, czego akurat potrzebujesz.</p>
          </div>
          <div class="administration-hero-actions">
            <a href="{$baseUrl}?controller=administration&action=users" class="btn btn-light btn-sm">Uzytkownicy</a>
            <a href="{$baseUrl}?controller=allegro&action=index" class="btn btn-outline-light btn-sm">Allegro</a>
            <a href="{$baseUrl}?controller=empik&action=index" class="btn btn-outline-light btn-sm">Empik</a>
            <a href="{$baseUrl}?controller=erli&action=index" class="btn btn-outline-light btn-sm">Erli</a>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
          <div class="card administration-summary-card h-100">
            <div class="card-body">
              <div class="administration-summary-label">Wszystkie konta</div>
              <div class="administration-summary-value">{$accounts|@count + $empikAccounts|@count + $erliAccounts|@count + 1}</div>
              <div class="administration-summary-meta">Allegro {$accounts|@count} | Empik {$empikAccounts|@count} | Erli {$erliAccounts|@count} | Morele 1</div>
            </div>
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="card administration-summary-card h-100">
            <div class="card-body">
              <div class="administration-summary-label">Kolejki marketplace</div>
              <div class="administration-summary-value text-warning">{$queueStats.pending + $queueStats.retry + $empikQueueStats.pending + $empikQueueStats.retry + $erliQueueStats.pending + $erliQueueStats.retry + $moreleQueueStats.pending + $moreleQueueStats.retry}</div>
              <div class="administration-summary-meta">Allegro {$queueStats.pending + $queueStats.retry} | Empik {$empikQueueStats.pending + $empikQueueStats.retry} | Erli {$erliQueueStats.pending + $erliQueueStats.retry} | Morele {$moreleQueueStats.pending + $moreleQueueStats.retry} | bledy {$queueStats.error + $empikQueueStats.error + $erliQueueStats.error + $moreleQueueStats.error}</div>
            </div>
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="card administration-summary-card h-100">
            <div class="card-body">
              <div class="administration-summary-label">Token API produktow</div>
              <div class="administration-summary-value {if $apiBearerToken|default:'' neq ''}text-success{else}text-danger{/if}">{if $apiBearerToken|default:'' neq ''}OK{else}Brak{/if}</div>
              <div class="administration-summary-meta">{if $apiBearerToken|default:'' neq ''}Aplikacje zewnetrzne moga sie autoryzowac bearer tokenem.{else}Skonfiguruj token przed laczeniem aplikacji.{/if}</div>
            </div>
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="card administration-summary-card h-100">
            <div class="card-body">
              <div class="administration-summary-label">Szybki plan</div>
              <div class="administration-summary-value text-primary">Crony</div>
              <div class="administration-summary-meta">Allegro i Erli: kolejki co minute, sync/maintenance co 5-15 minut</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card administration-panel mb-4">
        <div class="card-body">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
              <h3 class="h5 mb-1">Szybkie przejscia</h3>
              <div class="text-secondary small">Najczesciej uzywane akcje administracyjne i techniczne.</div>
            </div>
            <div class="administration-pill-nav">
              <a class="nav-link active" href="#headingMarketplaces">Marketplace</a>
              <a class="nav-link" href="#headingIntegrations">Integracje</a>
              <a class="nav-link" href="#headingAutomation">Crony</a>
              <a class="nav-link" href="#headingMaintenance">Maintenance</a>
            </div>
          </div>
        </div>
      </div>

      <div class="accordion administration-accordion" id="administrationAccordion">
        <div class="accordion-item mb-4">
          <h2 class="accordion-header" id="headingMarketplaces">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMarketplaces" aria-expanded="true" aria-controls="collapseMarketplaces">
              Marketplace i konta
            </button>
          </h2>
          <div id="collapseMarketplaces" class="accordion-collapse collapse show" aria-labelledby="headingMarketplaces" data-bs-parent="#administrationAccordion">
            <div class="accordion-body">
              <div class="alert alert-light border administration-note mb-4">
                Tutaj trzymasz wszystkie konfiguracje kont. Allegro ma autoryzacje OAuth i linki cron per konto, a Empik i Erli korzystaja z kluczy API.
              </div>

              <ul class="nav nav-pills administration-market-tabs mb-4" id="marketplaceTabs" role="tablist">
                <li class="nav-item" role="presentation">
                  <button class="nav-link active" id="allegro-tab" data-bs-toggle="pill" data-bs-target="#allegro-pane" type="button" role="tab" aria-controls="allegro-pane" aria-selected="true">Allegro</button>
                </li>
                <li class="nav-item" role="presentation">
                  <button class="nav-link" id="empik-tab" data-bs-toggle="pill" data-bs-target="#empik-pane" type="button" role="tab" aria-controls="empik-pane" aria-selected="false">Empik</button>
                </li>
                <li class="nav-item" role="presentation">
                  <button class="nav-link" id="erli-tab" data-bs-toggle="pill" data-bs-target="#erli-pane" type="button" role="tab" aria-controls="erli-pane" aria-selected="false">Erli</button>
                </li>
                <li class="nav-item" role="presentation">
                  <button class="nav-link" id="morele-tab" data-bs-toggle="pill" data-bs-target="#morele-pane" type="button" role="tab" aria-controls="morele-pane" aria-selected="false">Morele</button>
                </li>
                <li class="nav-item" role="presentation">
                  <button class="nav-link" id="temu-tab" data-bs-toggle="pill" data-bs-target="#temu-pane" type="button" role="tab" aria-controls="temu-pane" aria-selected="false">Temu</button>
                </li>
              </ul>

              <div class="tab-content" id="marketplaceTabsContent">
                <div class="tab-pane fade show active" id="allegro-pane" role="tabpanel" aria-labelledby="allegro-tab" tabindex="0">
                  <div class="row g-4">
                    <div class="col-xl-4">
                      <div class="card administration-form-card h-100">
                        <div class="card-header bg-white">
                          <h3 class="card-title mb-0">Nowe konto Allegro</h3>
                        </div>
                        <div class="card-body">
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
                      </div>
                    </div>
                    <div class="col-xl-8">
                      <div class="card administration-panel h-100">
                        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                          <h3 class="card-title mb-0">Konta Allegro</h3>
                          <a href="{$baseUrl}?controller=allegro&action=index" class="btn btn-sm btn-outline-secondary">Przejdz do ofert</a>
                        </div>
                        <div class="card-body">
                          <div class="alert alert-light border small text-secondary">
                            Refresh tokena dziala poprawnie nawet wtedy, gdy nowy <code>access_token</code> ma nadal krotki termin waznosci. To normalne zachowanie po stronie Allegro.
                          </div>
                          <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0">
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
                                    <td class="administration-inline-code" style="min-width: 280px;">
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
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#editAllegroAccount{$account.id|escape}" aria-expanded="false" aria-controls="editAllegroAccount{$account.id|escape}">Edytuj</button>
                                        <form method="post" action="{$baseUrl}?controller=allegro&action=saveaccount" class="d-grid">
                                          <input type="hidden" name="account_id" value="{$account.id|escape}">
                                          <input type="hidden" name="name" value="{$account.name|escape}">
                                          <input type="hidden" name="client_id" value="{$account.client_id|escape}">
                                          <input type="hidden" name="redirect_uri" value="{$account.redirect_uri|escape}">
                                          <input type="hidden" name="is_active" value="{if $account.is_active}0{else}1{/if}">
                                          <button type="submit" class="btn btn-sm {if $account.is_active}btn-outline-warning{else}btn-outline-success{/if}">
                                            {if $account.is_active}Wylacz konto{else}Wlacz konto{/if}
                                          </button>
                                        </form>
                                      </div>
                                    </td>
                                  </tr>
                                  <tr>
                                    <td colspan="6" class="bg-light p-0">
                                      <form method="post" action="{$baseUrl}?controller=allegro&action=saveaccount" class="row g-3 collapse p-3" id="editAllegroAccount{$account.id|escape}">
                                        <input type="hidden" name="account_id" value="{$account.id|escape}">
                                        <div class="col-lg-3">
                                          <label class="form-label">Nazwa konta</label>
                                          <input type="text" name="name" class="form-control form-control-sm" value="{$account.name|escape}" required>
                                        </div>
                                        <div class="col-lg-3">
                                          <label class="form-label">Client ID</label>
                                          <input type="text" name="client_id" class="form-control form-control-sm" value="{$account.client_id|escape}" required>
                                        </div>
                                        <div class="col-lg-3">
                                          <label class="form-label">Client Secret</label>
                                          <input type="text" name="client_secret" class="form-control form-control-sm" value="" placeholder="Zostaw puste, aby nie zmieniac">
                                        </div>
                                        <div class="col-lg-3">
                                          <label class="form-label">Redirect URI</label>
                                          <input type="url" name="redirect_uri" class="form-control form-control-sm" value="{$account.redirect_uri|escape}" required>
                                        </div>
                                        <div class="col-lg-3">
                                          <div class="form-check mt-lg-4">
                                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="allegroEditActive{$account.id|escape}" {if $account.is_active}checked{/if}>
                                            <label class="form-check-label" for="allegroEditActive{$account.id|escape}">Konto aktywne</label>
                                          </div>
                                        </div>
                                        <div class="col-lg-9 d-flex justify-content-end align-items-end">
                                          <button type="submit" class="btn btn-sm btn-primary">Zapisz zmiany Allegro</button>
                                        </div>
                                      </form>
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

                <div class="tab-pane fade" id="temu-pane" role="tabpanel" aria-labelledby="temu-tab" tabindex="0">
                  <div class="row g-4">
                    <div class="col-xl-6">
                      <div class="card administration-form-card h-100">
                        <div class="card-header bg-white">
                          <h3 class="card-title mb-0">Polaczenie Temu</h3>
                        </div>
                        <div class="card-body">
                          <form method="post" action="{$baseUrl}?controller=administration&action=savetemu" class="row g-3">
                            <div class="col-12">
                              <label class="form-label">Adres API</label>
                              <input type="url" name="temu_api_url" class="form-control" value="{$temuApiUrl|escape}" placeholder="https://...">
                            </div>
                            <div class="col-md-6">
                              <label class="form-label">App Key</label>
                              <input type="text" name="temu_app_key" class="form-control" value="{$temuAppKey|escape}">
                            </div>
                            <div class="col-md-6">
                              <label class="form-label">App Secret</label>
                              <input type="text" name="temu_app_secret" class="form-control" value="{$temuAppSecret|escape}">
                            </div>
                            <div class="col-md-6">
                              <label class="form-label">Access Token</label>
                              <input type="text" name="temu_access_token" class="form-control" value="{$temuAccessToken|escape}">
                            </div>
                            <div class="col-md-3">
                              <label class="form-label">Shop ID</label>
                              <input type="text" name="temu_shop_id" class="form-control" value="{$temuShopId|escape}">
                            </div>
                            <div class="col-md-3">
                              <label class="form-label">Region</label>
                              <input type="text" name="temu_region" class="form-control" value="{$temuRegion|default:'PL'|escape}" placeholder="PL">
                            </div>
                            <div class="col-12">
                              <div class="small text-secondary">
                                Ten etap przygotowuje system pod integracje Temu: zapis polaczenia, tokenow i mapowania kategorii. Pobieranie ofert i aukcji dodamy pozniej.
                              </div>
                            </div>
                            <div class="col-12">
                              <button type="submit" class="btn btn-primary">Zapisz polaczenie Temu</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                    <div class="col-xl-6">
                      <div class="card administration-panel h-100">
                        <div class="card-header">
                          <h3 class="card-title mb-0">Jak teraz dziala Temu</h3>
                        </div>
                        <div class="card-body">
                          <div class="alert alert-light border administration-note mb-3">
                            Integracja jest przygotowana pod dalsza rozbudowe, ale juz teraz laczy system z konfiguracja Temu i pozwala przypisywac kategorie Temu do kategorii magazynowych.
                          </div>
                          <ul class="small text-secondary mb-0">
                            <li>Ustawienia polaczenia zapisujesz tutaj, w administracji.</li>
                            <li>Kategorie Temu mapujesz w edycji kategorii magazynowej.</li>
                            <li>Definicje parametrow kategorii Temu mozna zapisac jako JSON przy kategorii.</li>
                            <li>Import aukcji i synchronizacje ofert zostawiamy na kolejny etap.</li>
                          </ul>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="tab-pane fade" id="empik-pane" role="tabpanel" aria-labelledby="empik-tab" tabindex="0">
                  <div class="row g-4">
                    <div class="col-xl-4">
                      <div class="card administration-form-card h-100">
                        <div class="card-header bg-white">
                          <h3 class="card-title mb-0">Nowe konto Empik</h3>
                        </div>
                        <div class="card-body">
                          <form method="post" action="{$baseUrl}?controller=administration&action=saveempik" class="row g-3">
                            <input type="hidden" name="account_id" value="">
                            <div class="col-12">
                              <label class="form-label">Nazwa konta</label>
                              <input type="text" name="name" class="form-control" placeholder="np. Empik PL" required>
                            </div>
                            <div class="col-12">
                              <label class="form-label">Instance URL</label>
                              <input type="url" name="api_url" class="form-control" placeholder="https://twoja-instancja.mirakl.net" required>
                            </div>
                            <div class="col-12">
                              <label class="form-label">API Key</label>
                              <input type="text" name="api_key" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                              <label class="form-label">shop_id</label>
                              <input type="number" min="1" step="1" name="shop_id" class="form-control" placeholder="opcjonalnie">
                            </div>
                            <div class="col-md-6">
                              <label class="form-label">Locale</label>
                              <input type="text" name="locale" class="form-control" value="pl_PL">
                            </div>
                            <div class="col-12">
                              <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="empik_active_default" checked>
                                <label class="form-check-label" for="empik_active_default">Konto aktywne</label>
                              </div>
                            </div>
                            <div class="col-12">
                              <button type="submit" class="btn btn-primary">Zapisz Empik API</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                    <div class="col-xl-8">
                      <div class="card administration-panel h-100">
                        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                          <h3 class="card-title mb-0">Konta Empik</h3>
                          <a href="{$baseUrl}?controller=empik&action=index" class="btn btn-sm btn-outline-secondary">Przejdz do Empik</a>
                        </div>
                        <div class="card-body">
                          <div class="alert alert-light border small text-secondary">
                            Empik Marketplace dziala na Mirakl Seller API. Wpisz adres instancji Mirakl, API key i opcjonalny <code>shop_id</code>, jesli sprzedawca ma kilka sklepow.
                          </div>
                          <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0">
                              <thead class="table-light">
                                <tr>
                                  <th>Konto</th>
                                  <th>API</th>
                                  <th>Status</th>
                                  <th>Sync</th>
                                  <th>Akcje</th>
                                </tr>
                              </thead>
                              <tbody>
                                {foreach $empikAccounts as $account}
                                  <tr>
                                    <td>
                                      <div class="fw-semibold">{$account.name|escape}</div>
                                      <div class="small text-secondary">slug: {$account.slug|escape}</div>
                                    </td>
                                    <td>
                                      <div class="small">{$account.api_url|escape}</div>
                                      <div class="small text-secondary">shop_id: {$account.shop_id|default:'-'|escape} | locale: {$account.locale|default:'pl_PL'|escape}</div>
                                    </td>
                                    <td>
                                      {if $account.is_active}
                                        <span class="badge text-bg-success">Aktywne</span>
                                      {else}
                                        <span class="badge text-bg-secondary">Nieaktywne</span>
                                      {/if}
                                      {if $account.last_error_message}
                                        <div class="small text-danger mt-1">{$account.last_error_message|escape}</div>
                                      {/if}
                                    </td>
                                    <td>
                                      <div class="small">ostatni sync: {$account.last_sync_at|default:'-'|escape}</div>
                                      <div class="small text-secondary">ostatni blad: {$account.last_error_at|default:'-'|escape}</div>
                                    </td>
                                    <td class="text-nowrap">
                                      <div class="d-grid gap-2">
                                        <a href="{$baseUrl}?controller=empik&action=sync&account={$account.slug|escape:'url'}" class="btn btn-sm btn-outline-primary">Synchronizuj</a>
                                        <a href="{$baseUrl}?controller=empik&action=index&account_id={$account.id|escape:'url'}" class="btn btn-sm btn-outline-secondary">Oferty</a>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#editEmpikAccount{$account.id|escape}" aria-expanded="false" aria-controls="editEmpikAccount{$account.id|escape}">Edytuj</button>
                                        <form method="post" action="{$baseUrl}?controller=administration&action=saveempik" class="d-grid">
                                          <input type="hidden" name="account_id" value="{$account.id|escape}">
                                          <input type="hidden" name="name" value="{$account.name|escape}">
                                          <input type="hidden" name="api_url" value="{$account.api_url|escape}">
                                          <input type="hidden" name="shop_id" value="{$account.shop_id|default:''|escape}">
                                          <input type="hidden" name="locale" value="{$account.locale|default:'pl_PL'|escape}">
                                          <input type="hidden" name="is_active" value="{if $account.is_active}0{else}1{/if}">
                                          <button type="submit" class="btn btn-sm {if $account.is_active}btn-outline-warning{else}btn-outline-success{/if}">
                                            {if $account.is_active}Wylacz konto{else}Wlacz konto{/if}
                                          </button>
                                        </form>
                                      </div>
                                    </td>
                                  </tr>
                                  <tr>
                                    <td colspan="5" class="bg-light p-0">
                                      <form method="post" action="{$baseUrl}?controller=administration&action=saveempik" class="row g-3 collapse p-3" id="editEmpikAccount{$account.id|escape}">
                                        <input type="hidden" name="account_id" value="{$account.id|escape}">
                                        <div class="col-lg-3">
                                          <label class="form-label">Nazwa konta</label>
                                          <input type="text" name="name" class="form-control form-control-sm" value="{$account.name|escape}" required>
                                        </div>
                                        <div class="col-lg-3">
                                          <label class="form-label">Instance URL</label>
                                          <input type="url" name="api_url" class="form-control form-control-sm" value="{$account.api_url|escape}" required>
                                        </div>
                                        <div class="col-lg-3">
                                          <label class="form-label">API Key</label>
                                          <input type="text" name="api_key" class="form-control form-control-sm" value="" placeholder="Zostaw puste, aby nie zmieniac">
                                        </div>
                                        <div class="col-lg-1">
                                          <label class="form-label">shop_id</label>
                                          <input type="number" min="1" step="1" name="shop_id" class="form-control form-control-sm" value="{$account.shop_id|default:''|escape}">
                                        </div>
                                        <div class="col-lg-2">
                                          <label class="form-label">Locale</label>
                                          <input type="text" name="locale" class="form-control form-control-sm" value="{$account.locale|default:'pl_PL'|escape}">
                                        </div>
                                        <div class="col-lg-3">
                                          <div class="form-check mt-lg-4">
                                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="empikEditActive{$account.id|escape}" {if $account.is_active}checked{/if}>
                                            <label class="form-check-label" for="empikEditActive{$account.id|escape}">Konto aktywne</label>
                                          </div>
                                        </div>
                                        <div class="col-lg-9 d-flex justify-content-end align-items-end">
                                          <button type="submit" class="btn btn-sm btn-primary">Zapisz zmiany Empik</button>
                                        </div>
                                      </form>
                                    </td>
                                  </tr>
                                {foreachelse}
                                  <tr><td colspan="5" class="text-center text-secondary py-4">Brak skonfigurowanych kont Empik.</td></tr>
                                {/foreach}
                              </tbody>
                            </table>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="tab-pane fade" id="erli-pane" role="tabpanel" aria-labelledby="erli-tab" tabindex="0">
                  <div class="row g-4">
                    <div class="col-xl-4">
                      <div class="card administration-form-card h-100">
                        <div class="card-header bg-white">
                          <h3 class="card-title mb-0">Nowe konto Erli</h3>
                        </div>
                        <div class="card-body">
                          <form method="post" action="{$baseUrl}?controller=administration&action=saveerli" class="row g-3">
                            <input type="hidden" name="account_id" value="">
                            <div class="col-12">
                              <label class="form-label">Nazwa konta</label>
                              <input type="text" name="name" class="form-control" placeholder="np. Erli PL" required>
                            </div>
                            <div class="col-12">
                              <label class="form-label">API URL</label>
                              <input type="url" name="api_url" class="form-control" value="https://erli.pl/svc/shop-api" required>
                            </div>
                            <div class="col-12">
                              <label class="form-label">API Key</label>
                              <input type="text" name="api_key" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                              <label class="form-label">Cennik dostawy</label>
                              <input type="text" name="default_price_list_tag" class="form-control" placeholder="opcjonalny tag">
                            </div>
                            <div class="col-md-3">
                              <label class="form-label">Wysylka dni</label>
                              <input type="number" min="1" step="1" name="default_dispatch_days" class="form-control" value="1">
                            </div>
                            <div class="col-md-3">
                              <label class="form-label">Waga g</label>
                              <input type="number" min="1" step="1" name="default_weight_g" class="form-control" placeholder="np. 250">
                            </div>
                            <div class="col-12">
                              <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="erli_active_default" checked>
                                <label class="form-check-label" for="erli_active_default">Konto aktywne</label>
                              </div>
                            </div>
                            <div class="col-12">
                              <button type="submit" class="btn btn-primary">Zapisz Erli API</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                    <div class="col-xl-8">
                      <div class="card administration-panel h-100">
                        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                          <h3 class="card-title mb-0">Konta Erli</h3>
                          <a href="{$baseUrl}?controller=erli&action=index" class="btn btn-sm btn-outline-secondary">Przejdz do Erli</a>
                        </div>
                        <div class="card-body">
                          <div class="alert alert-light border small text-secondary">
                            Erli korzysta z API produktowego. Konfigurujesz tu adres API, klucz oraz domyslne parametry potrzebne przy pracy na produktach i ich aktualizacjach.
                          </div>
                          <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0">
                              <thead class="table-light">
                                <tr>
                                  <th>Konto</th>
                                  <th>API</th>
                                  <th>Status</th>
                                  <th>Sync</th>
                                  <th>Akcje</th>
                                </tr>
                              </thead>
                              <tbody>
                                {foreach $erliAccounts as $account}
                                  <tr>
                                    <td>
                                      <div class="fw-semibold">{$account.name|escape}</div>
                                      <div class="small text-secondary">slug: {$account.slug|escape}</div>
                                    </td>
                                    <td>
                                      <div class="small">{$account.api_url|escape}</div>
                                      <div class="small text-secondary">cennik: {$account.default_price_list_tag|default:'-'|escape} | wysylka: {$account.default_dispatch_days|default:1|escape}d</div>
                                    </td>
                                    <td>
                                      {if $account.is_active}
                                        <span class="badge text-bg-success">Aktywne</span>
                                      {else}
                                        <span class="badge text-bg-secondary">Nieaktywne</span>
                                      {/if}
                                      {if $account.last_error_message}
                                        <div class="small text-danger mt-1">{$account.last_error_message|escape}</div>
                                      {/if}
                                    </td>
                                    <td>
                                      <div>{$account.last_sync_at|default:'-'|escape}</div>
                                      <div class="small text-secondary">blad: {$account.last_error_at|default:'-'|escape}</div>
                                      {if $account.sync_after_external_id|default:'' neq ''}
                                        <div class="small text-primary">offset: {$account.sync_after_external_id|escape}</div>
                                      {/if}
                                      <input type="text" class="form-control form-control-sm mt-2" readonly value="{$baseUrl}?controller=erli&action=sync&format=json&account={$account.slug|escape:'url'}&amp;max_batches=2&amp;page_limit=50">
                                    </td>
                                    <td class="text-nowrap">
                                      <div class="d-grid gap-2">
                                        <a href="{$baseUrl}?controller=erli&action=sync&account={$account.slug|escape:'url'}" class="btn btn-sm btn-outline-primary">Synchronizuj</a>
                                        <a href="{$baseUrl}?controller=erli&action=index&account_id={$account.id|escape:'url'}" class="btn btn-sm btn-outline-secondary">Produkty</a>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#editErliAccount{$account.id|escape}" aria-expanded="false" aria-controls="editErliAccount{$account.id|escape}">Edytuj</button>
                                        <form method="post" action="{$baseUrl}?controller=administration&action=saveerli" class="d-grid">
                                          <input type="hidden" name="account_id" value="{$account.id|escape}">
                                          <input type="hidden" name="name" value="{$account.name|escape}">
                                          <input type="hidden" name="api_url" value="{$account.api_url|escape}">
                                          <input type="hidden" name="default_price_list_tag" value="{$account.default_price_list_tag|default:''|escape}">
                                          <input type="hidden" name="default_dispatch_days" value="{$account.default_dispatch_days|default:1|escape}">
                                          <input type="hidden" name="default_weight_g" value="{$account.default_weight_g|default:''|escape}">
                                          <input type="hidden" name="is_active" value="{if $account.is_active}0{else}1{/if}">
                                          <button type="submit" class="btn btn-sm {if $account.is_active}btn-outline-warning{else}btn-outline-success{/if}">
                                            {if $account.is_active}Wylacz konto{else}Wlacz konto{/if}
                                          </button>
                                        </form>
                                      </div>
                                    </td>
                                  </tr>
                                  <tr>
                                    <td colspan="5" class="bg-light p-0">
                                      <form method="post" action="{$baseUrl}?controller=administration&action=saveerli" class="row g-3 collapse p-3" id="editErliAccount{$account.id|escape}">
                                        <input type="hidden" name="account_id" value="{$account.id|escape}">
                                        <div class="col-lg-3">
                                          <label class="form-label">Nazwa konta</label>
                                          <input type="text" name="name" class="form-control form-control-sm" value="{$account.name|escape}" required>
                                        </div>
                                        <div class="col-lg-3">
                                          <label class="form-label">API URL</label>
                                          <input type="url" name="api_url" class="form-control form-control-sm" value="{$account.api_url|escape}" required>
                                        </div>
                                        <div class="col-lg-3">
                                          <label class="form-label">API Key</label>
                                          <input type="text" name="api_key" class="form-control form-control-sm" value="" placeholder="Zostaw puste, aby nie zmieniac">
                                        </div>
                                        <div class="col-lg-3">
                                          <label class="form-label">Cennik dostawy</label>
                                          <input type="text" name="default_price_list_tag" class="form-control form-control-sm" value="{$account.default_price_list_tag|default:''|escape}">
                                        </div>
                                        <div class="col-lg-2">
                                          <label class="form-label">Wysylka dni</label>
                                          <input type="number" min="1" step="1" name="default_dispatch_days" class="form-control form-control-sm" value="{$account.default_dispatch_days|default:1|escape}">
                                        </div>
                                        <div class="col-lg-2">
                                          <label class="form-label">Waga g</label>
                                          <input type="number" min="1" step="1" name="default_weight_g" class="form-control form-control-sm" value="{$account.default_weight_g|default:''|escape}">
                                        </div>
                                        <div class="col-lg-3">
                                          <div class="form-check mt-lg-4">
                                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="erliEditActive{$account.id|escape}" {if $account.is_active}checked{/if}>
                                            <label class="form-check-label" for="erliEditActive{$account.id|escape}">Konto aktywne</label>
                                          </div>
                                        </div>
                                        <div class="col-lg-5 d-flex justify-content-end align-items-end">
                                          <button type="submit" class="btn btn-sm btn-primary">Zapisz zmiany Erli</button>
                                        </div>
                                      </form>
                                    </td>
                                  </tr>
                                {foreachelse}
                                  <tr><td colspan="5" class="text-center text-secondary py-4">Brak skonfigurowanych kont Erli.</td></tr>
                                {/foreach}
                              </tbody>
                            </table>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="tab-pane fade" id="morele-pane" role="tabpanel" aria-labelledby="morele-tab" tabindex="0">
                  <div class="row g-4">
                    <div class="col-xl-5">
                      <div class="card administration-form-card h-100">
                        <div class="card-header bg-white">
                          <h3 class="card-title mb-0">API Morele i fallback komputerow</h3>
                        </div>
                        <div class="card-body">
                          <form method="post" action="{$baseUrl}?controller=administration&action=savemorele" class="row g-3">
                            <div class="col-12">
                              <label class="form-label">API URL Morele</label>
                              <input type="url" name="morele_api_url" class="form-control" value="{$moreleApiUrl|default:'https://api-marketplace.morele.net'|escape}" placeholder="https://api-marketplace.morele.net">
                              <div class="form-text">Domyslnie endpoint marketplace Morele. Ten tryb dziala jak w starym module: rejestracja / refresh tokena, a potem pobranie cech kategorii.</div>
                            </div>
                            <div class="col-12">
                              <label class="form-label">Konto Morele</label>
                              <input type="text" name="morele_account" class="form-control" value="{$moreleAccount|escape}" placeholder="np. kontakt@pc-masters.pl">
                            </div>
                            <div class="col-md-6">
                              <label class="form-label">Client ID Morele</label>
                              <input type="text" name="morele_client_id" class="form-control" value="{$moreleClientId|escape}" placeholder="np. 3230">
                            </div>
                            <div class="col-md-6">
                              <label class="form-label">Client Secret Morele</label>
                              <input type="text" name="morele_client_secret" class="form-control" value="{$moreleClientSecret|escape}" placeholder="Wklej Client Secret">
                            </div>
                            <div class="col-md-6">
                              <label class="form-label">Kategoria Morele dla komputerow</label>
                              <input type="number" min="1" step="1" name="computers_morele_category_id" class="form-control" value="{$computersMoreleCategoryId|escape}">
                              <div class="form-text">Domyslnie <code>672</code>.</div>
                            </div>
                            <div class="col-md-6">
                              <label class="form-label">Kategoria Empik dla komputerow</label>
                              <input type="text" name="computers_empik_category_id" class="form-control" value="{$computersEmpikCategoryId|escape}">
                              <div class="form-text">Domyslnie <code>21-16-1</code>.</div>
                            </div>
                            <div class="col-12">
                              <button type="submit" class="btn btn-primary">Zapisz ustawienia Morele</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                    <div class="col-xl-7">
                      <div class="card administration-panel h-100">
                        <div class="card-header bg-white">
                          <h3 class="card-title mb-0">Jak to dziala</h3>
                        </div>
                        <div class="card-body">
                          <div class="alert alert-light border small text-secondary mb-3">
                            Ta sekcja zasila ekran <code>Komputery -&gt; Komponenty</code>. Morele dziala teraz w logice starego modulu: <code>Basic client_id:client_secret -&gt; /auth/register lub /auth/refresh -&gt; Bearer access_token -&gt; /offer/category/features/{ldelim}id{rdelim}</code>. Empik fallback bierze podane tutaj ID kategorii.
                          </div>
                          <div class="row g-3">
                            <div class="col-md-6">
                              <div class="border rounded p-3 h-100">
                                <div class="fw-semibold mb-2">Aktywne fallbacki</div>
                                <div class="small mb-1">Morele: <code>{$computersMoreleCategoryId|escape}</code></div>
                                <div class="small">Empik: <code>{$computersEmpikCategoryId|escape}</code></div>
                              </div>
                            </div>
                            <div class="col-md-6">
                              <div class="border rounded p-3 h-100">
                                <div class="fw-semibold mb-2">Stan konfiguracji API</div>
                                <div class="small mb-1">URL: {if $moreleApiUrl neq ''}<span class="text-success">uzupelniony</span>{else}<span class="text-danger">brak</span>{/if}</div>
                                <div class="small mb-1">Client ID: {if $moreleClientId neq ''}<span class="text-success">uzupelniony</span>{else}<span class="text-danger">brak</span>{/if}</div>
                                <div class="small">Client Secret: {if $moreleClientSecret neq ''}<span class="text-success">uzupelniony</span>{else}<span class="text-danger">brak</span>{/if}</div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="accordion-item mb-4">
          <h2 class="accordion-header" id="headingIntegrations">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseIntegrations" aria-expanded="false" aria-controls="collapseIntegrations">
              Integracje i API
            </button>
          </h2>
          <div id="collapseIntegrations" class="accordion-collapse collapse" aria-labelledby="headingIntegrations" data-bs-parent="#administrationAccordion">
            <div class="accordion-body">
              <div class="row g-4">
                <div class="col-xl-6">
                  <div class="card administration-panel h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                      <h3 class="card-title mb-0">API produktow dla aplikacji</h3>
                      <span class="badge text-bg-primary">Nowe</span>
                    </div>
                    <div class="card-body">
                      <form method="post" action="{$baseUrl}?controller=administration&action=saveapi" class="row g-3">
                        <div class="col-12">
                          <label class="form-label" for="api-bearer-token">Token API</label>
                          <input type="text" class="form-control" id="api-bearer-token" name="api_bearer_token" value="{$apiBearerToken|escape}" placeholder="Wpisz wlasny token lub kliknij Generuj">
                          <div class="form-text">To jest osobny token dla aplikacji laczacych sie z magazynem. Nie ma zwiazku z Allegro, Empik, Erli ani logowaniem uzytkownika.</div>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2">
                          <button type="submit" class="btn btn-primary">Zapisz token API</button>
                          <button type="button" class="btn btn-outline-secondary" id="generateApiToken">Generuj token</button>
                          <button type="button" class="btn btn-outline-secondary" id="copyApiToken">Kopiuj token</button>
                        </div>
                      </form>

                      <hr class="my-4">

                      <div class="mb-3 administration-inline-code">
                        <label class="form-label">Bazowy adres API</label>
                        <input type="text" class="form-control" readonly value="{$apiBaseUrl|escape}">
                      </div>
                      <div class="mb-3 administration-inline-code">
                        <label class="form-label">Wyszukiwanie produktow</label>
                        <input type="text" class="form-control" readonly value="{$apiBaseUrl|escape}/api/products/search?q=szyba&amp;limit=20">
                        <div class="form-text">Szuka po SKU, nazwie, EAN i kategorii.</div>
                      </div>
                      <div class="mb-3 administration-inline-code">
                        <label class="form-label">Produkt po ID</label>
                        <input type="text" class="form-control" readonly value="{$apiBaseUrl|escape}/api/products/123">
                      </div>
                      <div class="administration-inline-code">
                        <label class="form-label">Produkt po SKU</label>
                        <input type="text" class="form-control" readonly value="{$apiBaseUrl|escape}/api/products/sku/ABC-001">
                      </div>

                      <div class="small text-secondary mt-4">
                        Uzyj naglowka <code>Authorization: Bearer TWOJ_TOKEN</code>. Jesli token jest pusty, API zwroci blad autoryzacji.
                      </div>
                    </div>
                  </div>
                </div>

                <div class="col-xl-6">
                  <div class="card administration-panel h-100">
                    <div class="card-header">
                      <h3 class="card-title mb-0">Sellasist API</h3>
                    </div>
                    <div class="card-body">
                      <form method="post" action="{$baseUrl}?controller=administration&action=savesellasist" class="row g-3">
                        <div class="col-lg-5">
                          <label class="form-label" for="sellasist-base-url">Adres Sellasist</label>
                          <input type="url" class="form-control" id="sellasist-base-url" name="sellasist_base_url" value="{$sellasistBaseUrl|escape}" placeholder="https://altreo.sellasist.pl">
                        </div>
                        <div class="col-lg-7">
                          <label class="form-label" for="sellasist-api-key">API Key</label>
                          <input type="text" class="form-control" id="sellasist-api-key" name="sellasist_api_key" value="{$sellasistApiKey|escape}" placeholder="Wklej klucz API Sellasist">
                        </div>
                        <div class="col-lg-6">
                          <label class="form-label" for="sellasist-picking-status-id">Status do pobierania na Zbieranie</label>
                          <input type="number" min="1" step="1" class="form-control" id="sellasist-picking-status-id" name="sellasist_picking_status_id" value="{$sellasistPickingStatusId|escape}" placeholder="23">
                        </div>
                        <div class="col-lg-6">
                          <label class="form-label" for="sellasist-printed-status-id">Status po wydruku stickersow</label>
                          <input type="number" min="0" step="1" class="form-control" id="sellasist-printed-status-id" name="sellasist_printed_status_id" value="{$sellasistPrintedStatusId|escape}" placeholder="3">
                        </div>
                        <div class="col-12">
                          <div class="small text-secondary">
                            Dane sa uzywane przez modul Sellasist, zakladke Zbieranie i generowanie naklejek. Wpisz <code>0</code> w polu statusu po wydruku, aby zostawic zamowienie na obecnym statusie.
                          </div>
                        </div>
                        <div class="col-12">
                          <button type="submit" class="btn btn-primary">Zapisz ustawienia Sellasist</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="accordion-item mb-4">
          <h2 class="accordion-header" id="headingAutomation">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAutomation" aria-expanded="false" aria-controls="collapseAutomation">
              Crony, worker i automatyzacje
            </button>
          </h2>
          <div id="collapseAutomation" class="accordion-collapse collapse" aria-labelledby="headingAutomation" data-bs-parent="#administrationAccordion">
            <div class="accordion-body">
              <div class="row g-4 mb-4">
                <div class="col-xl-4">
                  <div class="card administration-panel h-100">
                    <div class="card-body">
                      <div class="fw-semibold mb-2">Jak to ustawic</div>
                      <div class="small text-secondary mb-2">Ustaw osobno szybkie workery kolejek i wolniejsze maintenance. Workery przepychaja juz dodane zadania, a maintenance robi sync i dorzuca nowe aktualizacje.</div>
                      <div class="small text-secondary">Kolejki Allegro, Empik, Erli: co 1 minute.</div>
                      <div class="small text-secondary">Allegro: co 5 minut.</div>
                      <div class="small text-secondary">Empik: co 10 minut.</div>
                      <div class="small text-secondary">Erli: co 10 minut.</div>
                      <div class="small text-secondary mt-2">Automatyczne konczenie ofert Allegro ustaw osobno tylko wtedy, gdy ma dzialac prog konczenia ofert po stanie magazynowym.</div>
                    </div>
                  </div>
                </div>
                <div class="col-xl-8">
                  <div class="card administration-panel h-100">
                    <div class="card-header">
                      <h3 class="card-title mb-0">Gotowe komendy cron</h3>
                    </div>
                    <div class="card-body">
                      <div class="mb-3 administration-inline-code">
                        <label class="form-label">Allegro kolejka - co 1 minute</label>
                        <input type="text" class="form-control" readonly value="/usr/bin/curl --silent &quot;{$automation.queue_worker|escape}&quot; &gt;/dev/null 2&gt;&amp;1">
                      </div>
                      <div class="mb-3 administration-inline-code">
                        <label class="form-label">Empik kolejka - co 1 minute</label>
                        <input type="text" class="form-control" readonly value="/usr/bin/curl --silent &quot;{$empikAutomation.queue_worker|escape}&quot; &gt;/dev/null 2&gt;&amp;1">
                      </div>
                      <div class="mb-3 administration-inline-code">
                        <label class="form-label">Erli kolejka - co 1 minute</label>
                        <input type="text" class="form-control" readonly value="/usr/bin/curl --silent &quot;{$erliAutomation.queue_worker|escape}&quot; &gt;/dev/null 2&gt;&amp;1">
                      </div>
                      <hr>
                      <div class="mb-3 administration-inline-code">
                        <label class="form-label">Allegro maintenance - co 5 minut</label>
                        <input type="text" class="form-control" readonly value="/usr/bin/curl --silent &quot;{$automation.full_maintenance|escape}&quot; &gt;/dev/null 2&gt;&amp;1" id="globalMaintenanceUrl">
                      </div>
                      <div class="mb-3 administration-inline-code">
                        <label class="form-label">Empik maintenance - co 10 minut</label>
                        <input type="text" class="form-control" readonly value="/usr/bin/curl --silent &quot;{$empikAutomation.maintenance|escape}&quot; &gt;/dev/null 2&gt;&amp;1">
                      </div>
                      <div class="mb-3 administration-inline-code">
                        <label class="form-label">Erli maintenance - co 10 minut</label>
                        <input type="text" class="form-control" readonly value="/usr/bin/curl --silent &quot;{$erliAutomation.maintenance|escape}&quot; &gt;/dev/null 2&gt;&amp;1">
                      </div>
                      <div class="mb-3 administration-inline-code">
                        <label class="form-label">Morele pobieranie aukcji - co 10 minut</label>
                        <input type="text" class="form-control" readonly value="/usr/bin/curl --silent &quot;{$moreleAutomation.maintenance|escape}&quot; &gt;/dev/null 2&gt;&amp;1">
                      </div>
                      <div class="administration-inline-code">
                        <label class="form-label">Opcjonalnie: konczenie ofert Allegro - co 30 minut</label>
                        <input type="text" class="form-control" readonly value="/usr/bin/curl --silent &quot;{$automation.auto_end_offers|escape}&quot; &gt;/dev/null 2&gt;&amp;1">
                        <div class="form-text">Ustaw tylko jesli ma automatycznie dodawac do kolejki oferty Allegro do zakonczenia.</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="accordion-item mb-4">
          <h2 class="accordion-header" id="headingMaintenance">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMaintenance" aria-expanded="false" aria-controls="collapseMaintenance">
              Maintenance i sprzatanie
            </button>
          </h2>
          <div id="collapseMaintenance" class="accordion-collapse collapse" aria-labelledby="headingMaintenance" data-bs-parent="#administrationAccordion">
            <div class="accordion-body">
              <div class="row g-4 mb-4">
                <div class="col-xl-6">
                  <div class="administration-backup-box">
                    <h3 class="h5 mb-2">Kopia magazynu ksiegowego</h3>
                    <p class="text-secondary small mb-3">Pobiera pelny JSON z rekordami modulu `Magazyn ksiegowy`: pozycje, aliasy, dokumenty i linie.</p>
                    <a href="{$baseUrl}?controller=accountingwarehouse&action=exportbackup" class="btn btn-outline-success">Pobierz pelna kopie JSON</a>
                  </div>
                </div>
                <div class="col-xl-6">
                  <div class="administration-backup-box">
                    <h3 class="h5 mb-2">Przywroc kopie magazynu ksiegowego</h3>
                    <p class="text-secondary small mb-3">Wgranie pliku nadpisze wszystkie aktualne rekordy tego modulu, wiec to jest operacja serwisowa tylko dla administratora.</p>
                    <form method="post" action="{$baseUrl}?controller=accountingwarehouse&action=importbackup" enctype="multipart/form-data" onsubmit="return confirm('Przywrocic pelna kopie? Operacja nadpisze wszystkie obecne rekordy magazynu ksiegowego.');">
                      <input type="file" name="backup_file" accept=".json,application/json" class="form-control mb-2" required>
                      <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="confirm_restore" value="1" id="confirmAccountingWarehouseRestoreAdmin" required>
                        <label class="form-check-label small" for="confirmAccountingWarehouseRestoreAdmin">
                          Potwierdzam nadpisanie wszystkich danych modulu magazynu ksiegowego.
                        </label>
                      </div>
                      <button type="submit" class="btn btn-outline-danger">Wgraj kopie i odtworz rekordy</button>
                    </form>
                  </div>
                </div>
                <div class="col-xl-6">
                  <div class="administration-backup-box">
                    <h3 class="h5 mb-2">Kopia modulu produktow</h3>
                    <p class="text-secondary small mb-3">Pobiera pelny JSON z produktami, kategoriami, polami wlasnymi, parametrami Allegro/Empik/Temu, grupami wspolnego stanu i powiazaniami stanow pochodnych.</p>
                    <a href="{$baseUrl}?controller=products&action=exportbackup" class="btn btn-outline-success">Pobierz pelna kopie JSON produktow</a>
                  </div>
                </div>
                <div class="col-xl-6">
                  <div class="administration-backup-box">
                    <h3 class="h5 mb-2">Przywroc kopie modulu produktow</h3>
                    <p class="text-secondary small mb-3">Wgranie pliku nadpisze rekordy produktow, parametrow, pol wlasnych, grup wspolnego stanu i powiazan pochodnych. Uzywaj tylko jako operacji serwisowej.</p>
                    <form method="post" action="{$baseUrl}?controller=products&action=importbackup" enctype="multipart/form-data" onsubmit="return confirm('Przywrocic pelna kopie produktow? Operacja nadpisze obecne rekordy modulu produktow.');">
                      <input type="file" name="backup_file" accept=".json,application/json" class="form-control mb-2" required>
                      <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="confirm_restore" value="1" id="confirmProductsRestoreAdmin" required>
                        <label class="form-check-label small" for="confirmProductsRestoreAdmin">
                          Potwierdzam nadpisanie danych modulu produktow wraz z parametrami i powiazaniami.
                        </label>
                      </div>
                      <button type="submit" class="btn btn-outline-danger">Wgraj kopie i odtworz produkty</button>
                    </form>
                  </div>
                </div>
                <div class="col-xl-12">
                  <div class="administration-backup-box">
                    <h3 class="h5 mb-2">Import SQL starego modulu ALTREO</h3>
                    <p class="text-secondary small mb-3">Wgraj pliki <code>pr_products_altreo.sql</code>, <code>pr_components_altreo.sql</code> i <code>pr_altreo_template.sql</code>. Import obsluguje dumpy z phpMyAdmina i zapisuje tylko rekordy do tabel modulu komputerow.</p>
                    <form method="post" action="{$baseUrl}?controller=administration&action=importaltreosql" enctype="multipart/form-data" class="row g-3" onsubmit="return confirm('Zaimportowac pliki SQL ALTREO do modulu komputerow?');">
                      <div class="col-lg-4">
                        <label class="form-label" for="altreo-products-sql">Produkty</label>
                        <input type="file" id="altreo-products-sql" name="altreo_products_sql" accept=".sql,application/sql,text/sql,text/plain" class="form-control">
                        <div class="form-text"><code>pr_products_altreo.sql</code></div>
                      </div>
                      <div class="col-lg-4">
                        <label class="form-label" for="altreo-components-sql">Komponenty</label>
                        <input type="file" id="altreo-components-sql" name="altreo_components_sql" accept=".sql,application/sql,text/sql,text/plain" class="form-control">
                        <div class="form-text"><code>pr_components_altreo.sql</code></div>
                      </div>
                      <div class="col-lg-4">
                        <label class="form-label" for="altreo-template-sql">Template</label>
                        <input type="file" id="altreo-template-sql" name="altreo_template_sql" accept=".sql,application/sql,text/sql,text/plain" class="form-control">
                        <div class="form-text"><code>pr_altreo_template.sql</code></div>
                      </div>
                      <div class="col-lg-4">
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" name="clear_altreo_tables" value="1" id="clearAltreoTablesBeforeImport">
                          <label class="form-check-label small" for="clearAltreoTablesBeforeImport">
                            Najpierw wyczysc tabele komputerow, komponentow i szablonow ALTREO.
                          </label>
                        </div>
                      </div>
                      <div class="col-12 d-flex justify-content-between gap-2 align-items-center flex-wrap">
                        <div class="small text-secondary">Kolumny ze starego dumpa, ktorych nie ma w aktualnym schemacie, sa pomijane zamiast przerywac import.</div>
                        <button type="submit" class="btn btn-outline-primary">Wgraj SQL ALTREO</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>

              <div class="card administration-panel border-warning-subtle">
                <div class="card-header">
                  <h3 class="card-title mb-0">Sprzatanie bazy</h3>
                </div>
                <div class="card-body">
                  <form method="post" action="{$baseUrl}?controller=administration&action=cleanup" class="row g-3 align-items-end">
                    <div class="col-lg-4">
                      <label class="form-label" for="cleanup-queue-done-days">Usun zakonczona kolejke starsza niz dni</label>
                      <input type="number" min="0" step="1" class="form-control" id="cleanup-queue-done-days" name="queue_done_days" value="14">
                    </div>
                    <div class="col-lg-4">
                      <label class="form-label" for="cleanup-queue-error-days">Usun bledy i retry starsze niz dni</label>
                      <input type="number" min="0" step="1" class="form-control" id="cleanup-queue-error-days" name="queue_error_days" value="30">
                    </div>
                    <div class="col-lg-4">
                      <label class="form-label" for="cleanup-deleted-products-days">Usun produkty skasowane starsze niz dni</label>
                      <input type="number" min="0" step="1" class="form-control" id="cleanup-deleted-products-days" name="deleted_products_days" value="30">
                    </div>
                    <div class="col-12">
                      <div class="small text-secondary">
                        Ten przycisk czysci stare wpisy z <code>allegro_offer_change_queue</code>, odpina oferty Allegro od produktow po soft-delecie i usuwa stare logi zmian wraz z produktami oznaczonymi jako skasowane.
                      </div>
                    </div>
                    <div class="col-12">
                      <div class="small text-secondary">
                        Mozesz wpisac <code>0</code>, aby sprzatac od razu, bez czekania pelnej doby.
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
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<script>
  (function () {
    var apiTokenInput = document.getElementById('api-bearer-token');
    var generateApiTokenBtn = document.getElementById('generateApiToken');
    var copyApiTokenBtn = document.getElementById('copyApiToken');

    function randomToken(length) {
      var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789-_';
      var result = '';
      var cryptoObj = window.crypto || window.msCrypto;

      if (cryptoObj && cryptoObj.getRandomValues) {
        var values = new Uint32Array(length);
        cryptoObj.getRandomValues(values);
        for (var i = 0; i < length; i++) {
          result += chars.charAt(values[i] % chars.length);
        }
        return result;
      }

      for (var j = 0; j < length; j++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
      }
      return result;
    }

    if (generateApiTokenBtn && apiTokenInput) {
      generateApiTokenBtn.addEventListener('click', function () {
        apiTokenInput.value = 'mag_' + randomToken(40);
      });
    }

    if (copyApiTokenBtn && apiTokenInput) {
      copyApiTokenBtn.addEventListener('click', function () {
        apiTokenInput.select();
        apiTokenInput.setSelectionRange(0, apiTokenInput.value.length);
        try {
          document.execCommand('copy');
        } catch (error) {
        }
      });
    }
  })();
</script>
