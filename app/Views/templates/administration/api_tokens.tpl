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
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=administration&action=automation">Administracja</a></li>
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
        .api-admin-card {
          border-radius: 1.15rem;
          border: 1px solid rgba(15, 23, 42, 0.08);
          box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
        }
        .api-new-token {
          border-radius: 1rem;
          border: 2px solid #16a34a;
          background: rgba(22, 163, 74, 0.06);
        }
        .api-new-token code {
          font-size: 1rem;
          word-break: break-all;
        }
        .api-tabs .nav-link {
          border-radius: 999px;
        }
      </style>

      <ul class="nav nav-pills api-tabs mb-3 gap-2">
        <li class="nav-item"><a class="nav-link active" href="{$baseUrl}?controller=administration&action=apitokens"><i class="bi bi-key me-1"></i>Tokeny API</a></li>
        <li class="nav-item"><a class="nav-link" href="{$baseUrl}?controller=administration&action=apidocs"><i class="bi bi-journal-code me-1"></i>Instrukcja API</a></li>
      </ul>

      {if $newApiToken}
        <div class="card api-new-token mb-4">
          <div class="card-body">
            <h5 class="mb-2"><i class="bi bi-shield-lock me-1"></i>Nowy token: {$newApiToken.name|escape}</h5>
            <p class="mb-2 text-secondary">Skopiuj token teraz. Ze wzgledow bezpieczenstwa w bazie zapisany jest tylko jego skrot i nie da sie go pozniej wyswietlic ponownie.</p>
            <div class="d-flex flex-wrap align-items-center gap-2">
              <code id="api-new-token-value" class="p-2 bg-white border rounded flex-grow-1">{$newApiToken.token|escape}</code>
              <button type="button" class="btn btn-success" data-copy-target="api-new-token-value"><i class="bi bi-clipboard me-1"></i>Kopiuj</button>
            </div>
          </div>
        </div>
      {/if}

      <div class="row g-4">
        <div class="col-xl-4">
          <div class="card api-admin-card h-100">
            <div class="card-header"><strong>Wygeneruj token</strong></div>
            <div class="card-body">
              <form method="post" action="{$baseUrl}?controller=administration&action=createapitoken" class="row g-3">
                <div class="col-12">
                  <label class="form-label" for="api-token-name">Nazwa / aplikacja</label>
                  <input type="text" class="form-control" id="api-token-name" name="name" maxlength="190" required placeholder="np. Sklep B2B, Hurtownia XYZ">
                  <div class="form-text">Osobny token dla kazdej aplikacji pozwala odebrac dostep jednej z nich bez wplywu na pozostale.</div>
                </div>
                <div class="col-12">
                  <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Generuj token</button>
                </div>
              </form>
              <hr>
              <div class="small text-secondary">
                Token daje dostep <strong>tylko do odczytu</strong> produktow i kategorii.
                Adres API: <code>{$apiIndexUrl|escape}?controller=api&amp;action=products&amp;category_id=ID</code>.
                Pelna instrukcja do przeslania integratorowi jest w zakladce <a href="{$baseUrl}?controller=administration&action=apidocs">Instrukcja API</a>.
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-8">
          <div class="card api-admin-card h-100">
            <div class="card-header"><strong>Tokeny</strong> <span class="text-secondary">({$apiTokens|@count})</span></div>
            <div class="card-body p-0">
              {if $apiTokens|@count eq 0}
                <p class="text-secondary m-3">Brak tokenow. Wygeneruj pierwszy token po lewej stronie.</p>
              {else}
                <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0">
                    <thead>
                      <tr>
                        <th>Nazwa</th>
                        <th>Token</th>
                        <th>Status</th>
                        <th>Ostatnie uzycie</th>
                        <th>Utworzony</th>
                        <th class="text-end">Akcje</th>
                      </tr>
                    </thead>
                    <tbody>
                      {foreach $apiTokens as $apiToken}
                        <tr>
                          <td><strong>{$apiToken.name|escape}</strong></td>
                          <td><code>{$apiToken.token_hint|escape}</code></td>
                          <td>
                            {if $apiToken.is_active}
                              <span class="badge text-bg-success">Aktywny</span>
                            {else}
                              <span class="badge text-bg-secondary">Uniewazniony</span>
                              {if $apiToken.revoked_at}<div class="small text-secondary">{$apiToken.revoked_at|escape}</div>{/if}
                            {/if}
                          </td>
                          <td>
                            {if $apiToken.last_used_at}
                              {$apiToken.last_used_at|escape}
                              <div class="small text-secondary">{$apiToken.last_used_ip|escape} &middot; {$apiToken.usage_count|escape} zapytan</div>
                            {else}
                              <span class="text-secondary">nigdy</span>
                            {/if}
                          </td>
                          <td>
                            {$apiToken.created_at|escape}
                            {if $apiToken.created_by_name}<div class="small text-secondary">{$apiToken.created_by_name|escape}</div>{/if}
                          </td>
                          <td class="text-end text-nowrap">
                            {if $apiToken.is_active}
                              <form method="post" action="{$baseUrl}?controller=administration&action=revokeapitoken" class="d-inline" onsubmit="return confirm('Uniewaznic token? Aplikacja uzywajaca go straci dostep.');">
                                <input type="hidden" name="id" value="{$apiToken.id|escape}">
                                <button type="submit" class="btn btn-sm btn-outline-warning"><i class="bi bi-slash-circle me-1"></i>Uniewaznij</button>
                              </form>
                            {/if}
                            <form method="post" action="{$baseUrl}?controller=administration&action=deleteapitoken" class="d-inline" onsubmit="return confirm('Usunac token na stale?');">
                              <input type="hidden" name="id" value="{$apiToken.id|escape}">
                              <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                          </td>
                        </tr>
                      {/foreach}
                    </tbody>
                  </table>
                </div>
              {/if}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<script>
  document.querySelectorAll('[data-copy-target]').forEach(function (button) {
    button.addEventListener('click', function () {
      var target = document.getElementById(button.getAttribute('data-copy-target'));
      if (!target) {
        return;
      }
      var text = target.value !== undefined ? target.value : target.textContent;
      navigator.clipboard.writeText(text.trim()).then(function () {
        var original = button.innerHTML;
        button.innerHTML = '<i class="bi bi-check2 me-1"></i>Skopiowano';
        setTimeout(function () { button.innerHTML = original; }, 1800);
      });
    });
  });
</script>
