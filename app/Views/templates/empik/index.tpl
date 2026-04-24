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

      <div class="row g-4 mb-4">
        <div class="col-xl-4">
          <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h3 class="card-title mb-0">Konta Empik</h3>
              <span class="badge text-bg-secondary">{$accounts|@count}</span>
            </div>
            <div class="card-body">
              {if $currentUser.role eq 'admin'}
                <form method="post" action="{$baseUrl}?controller=empik&action=saveaccount" class="row g-3">
                  <input type="hidden" name="account_id" value="">
                  <div class="col-12">
                    <label class="form-label">Nazwa konta</label>
                    <input type="text" class="form-control" name="name" placeholder="np. Empik PL" required>
                  </div>
                  <div class="col-12">
                    <label class="form-label">Instance URL</label>
                    <input type="url" class="form-control" name="api_url" placeholder="https://xxxx.mirakl.net" required>
                  </div>
                  <div class="col-12">
                    <label class="form-label">API key</label>
                    <input type="text" class="form-control" name="api_key" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">shop_id</label>
                    <input type="number" min="1" step="1" class="form-control" name="shop_id" placeholder="opcjonalnie">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Locale</label>
                    <input type="text" class="form-control" name="locale" value="pl_PL">
                  </div>
                  <div class="col-12">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="is_active" value="1" id="empik_active_default" checked>
                      <label class="form-check-label" for="empik_active_default">Konto aktywne</label>
                    </div>
                  </div>
                  <div class="col-12">
                    <button type="submit" class="btn btn-primary">Zapisz konto</button>
                  </div>
                </form>
              {else}
                <div class="text-secondary small">Konfiguracja konta Empik jest dostepna tylko dla administratora.</div>
              {/if}
            </div>
          </div>
        </div>

        <div class="col-xl-8">
          <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h3 class="card-title mb-0">Synchronizacja i konta</h3>
              <span class="small text-secondary">Mirakl Seller API</span>
            </div>
            <div class="card-body">
              <div class="alert alert-light border small text-secondary">
                Empik korzysta z Mirakl. Autoryzacja odbywa sie przez naglowek <code>Authorization</code> z API key, bez OAuth.
                Kategorie pobierane sa z drzewa <code>/api/hierarchies</code>, a oferty z <code>/api/offers</code>.
              </div>

              <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle">
                  <thead class="table-light">
                    <tr>
                      <th>Konto</th>
                      <th>Adres API</th>
                      <th>Status</th>
                      <th>Ostatni sync</th>
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
                          <div>{$account.last_sync_at|default:'-'|escape}</div>
                          <div class="small text-secondary">blad: {$account.last_error_at|default:'-'|escape}</div>
                        </td>
                        <td class="text-nowrap">
                          <div class="d-grid gap-2">
                            <a href="{$baseUrl}?controller=empik&action=sync&account={$account.slug|escape:'url'}" class="btn btn-sm btn-outline-primary">Synchronizuj</a>
                            {if $currentUser.role eq 'admin'}
                              <form method="post" action="{$baseUrl}?controller=empik&action=saveaccount" class="d-grid">
                                <input type="hidden" name="account_id" value="{$account.id|escape}">
                                <input type="hidden" name="name" value="{$account.name|escape}">
                                <input type="hidden" name="api_url" value="{$account.api_url|escape}">
                                <input type="hidden" name="api_key" value="{$account.api_key|escape}">
                                <input type="hidden" name="shop_id" value="{$account.shop_id|default:''|escape}">
                                <input type="hidden" name="locale" value="{$account.locale|default:'pl_PL'|escape}">
                                <input type="hidden" name="is_active" value="{if $account.is_active}0{else}1{/if}">
                                <button type="submit" class="btn btn-sm {if $account.is_active}btn-outline-warning{else}btn-outline-success{/if}">
                                  {if $account.is_active}Wylacz konto{else}Wlacz konto{/if}
                                </button>
                              </form>
                            {/if}
                          </div>
                        </td>
                      </tr>
                    {foreachelse}
                      <tr><td colspan="5" class="text-center py-4 text-secondary">Brak skonfigurowanych kont Empik.</td></tr>
                    {/foreach}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">
          <h3 class="card-title mb-0">Filtry ofert</h3>
        </div>
        <div class="card-body">
          <form method="get" action="{$baseUrl}" class="row g-3">
            <input type="hidden" name="controller" value="empik">
            <input type="hidden" name="action" value="index">
            <div class="col-md-3">
              <label class="form-label">Konto</label>
              <select name="account_id" class="form-select">
                <option value="">Wszystkie</option>
                {foreach $accounts as $account}
                  <option value="{$account.id}"{if $filters.account_id|default:'' == $account.id} selected{/if}>{$account.name|escape}</option>
                {/foreach}
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Fraza</label>
              <input type="text" name="q" class="form-control" value="{$filters.q|default:''|escape}" placeholder="tytul, opis, kategoria">
            </div>
            <div class="col-md-2">
              <label class="form-label">SKU / product_id</label>
              <input type="text" name="sku" class="form-control" value="{$filters.sku|default:''|escape}">
            </div>
            <div class="col-md-2">
              <label class="form-label">State code</label>
              <input type="text" name="state" class="form-control" value="{$filters.state|default:''|escape}" placeholder="np. OPEN">
            </div>
            <div class="col-md-2">
              <label class="form-label">Aktywna</label>
              <select name="active" class="form-select">
                <option value="">Wszystkie</option>
                <option value="1"{if $filters.active|default:'' eq '1'} selected{/if}>Tak</option>
                <option value="0"{if $filters.active|default:'' eq '0'} selected{/if}>Nie</option>
              </select>
            </div>
            <div class="col-12 d-flex gap-2">
              <button type="submit" class="btn btn-primary">Filtruj</button>
              <a href="{$baseUrl}?controller=empik&action=index" class="btn btn-outline-secondary">Reset</a>
            </div>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
          <h3 class="card-title mb-0">Oferty Empik</h3>
          <div class="small text-secondary">Liczba ofert w bazie: {$totalOffers}</div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-striped table-hover table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>ID</th>
                  <th>Konto</th>
                  <th>Tytul</th>
                  <th>SKU</th>
                  <th>Kategoria</th>
                  <th>Stan</th>
                  <th>Aktywna</th>
                  <th>Ilosc</th>
                  <th>Cena</th>
                  <th>Sync</th>
                  <th class="text-end">Akcje</th>
                </tr>
              </thead>
              <tbody>
                {foreach $offers as $offer}
                  <tr>
                    <td>{$offer.offer_id}</td>
                    <td>{$offer.account_name|escape}</td>
                    <td>
                      <div class="fw-semibold">{$offer.product_title|default:'-'|escape}</div>
                      <div class="small text-secondary">product_id: {$offer.product_id|default:'-'|escape}</div>
                    </td>
                    <td>
                      <div>shop: <code>{$offer.shop_sku|default:'-'|escape}</code></div>
                      <div class="small text-secondary">product: <code>{$offer.product_sku|default:'-'|escape}</code></div>
                    </td>
                    <td>
                      <div>{$offer.category_label|default:'-'|escape}</div>
                      <div class="small text-secondary"><code>{$offer.category_code|default:'-'|escape}</code></div>
                    </td>
                    <td><span class="badge text-bg-secondary">{$offer.state_code|default:'-'|escape}</span></td>
                    <td>
                      {if $offer.active}
                        <span class="badge text-bg-success">Tak</span>
                      {else}
                        <span class="badge text-bg-danger">Nie</span>
                      {/if}
                    </td>
                    <td>{$offer.quantity|default:'-'|escape}</td>
                    <td>{if isset($offer.price) and $offer.price ne ''}{$offer.price|escape} {$offer.currency_iso_code|default:''|escape}{else}-{/if}</td>
                    <td>{$offer.last_synced_at|default:'-'|escape}</td>
                    <td class="text-end">
                      <a href="{$baseUrl}?controller=empik&action=offer&id={$offer.id}" class="btn btn-sm btn-outline-primary">Szczegoly</a>
                    </td>
                  </tr>
                {foreachelse}
                  <tr>
                    <td colspan="11" class="text-center py-4 text-secondary">Brak ofert. Skonfiguruj konto i uruchom synchronizacje.</td>
                  </tr>
                {/foreach}
              </tbody>
            </table>
          </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div class="small text-secondary">Strona {$page} z {$totalPages}</div>
          <div class="btn-group">
            <a class="btn btn-sm btn-outline-secondary{if $page <= 1} disabled{/if}" href="{$baseUrl}?controller=empik&action=index&page={$prevPage}&per_page={$perPage}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&state={$filters.state|escape:'url'}&active={$filters.active|escape:'url'}">Poprzednia</a>
            <a class="btn btn-sm btn-outline-secondary{if $page >= $totalPages} disabled{/if}" href="{$baseUrl}?controller=empik&action=index&page={$nextPage}&per_page={$perPage}&account_id={$filters.account_id|escape:'url'}&q={$filters.q|escape:'url'}&sku={$filters.sku|escape:'url'}&state={$filters.state|escape:'url'}&active={$filters.active|escape:'url'}">Nastepna</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
