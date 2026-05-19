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
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=erli&action=index">Erli</a></li>
            <li class="breadcrumb-item active" aria-current="page">{$breadcrumbCurrent|escape}</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      <div class="row g-4">
        <div class="col-lg-5">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title mb-0">Podsumowanie</h3>
            </div>
            <div class="card-body">
              <dl class="row mb-0">
                <dt class="col-5">Konto</dt><dd class="col-7">{$product.account_name|default:'-'|escape}</dd>
                <dt class="col-5">External ID</dt><dd class="col-7"><code>{$product.external_id|default:'-'|escape}</code></dd>
                <dt class="col-5">SKU</dt><dd class="col-7"><code>{$product.sku|default:'-'|escape}</code></dd>
                <dt class="col-5">Status</dt><dd class="col-7">{$product.effective_status|default:'-'|escape}</dd>
                <dt class="col-5">Cena</dt><dd class="col-7">{$product.effective_price|string_format:"%.2f"} zl</dd>
                <dt class="col-5">Stan Erli</dt><dd class="col-7">{$product.effective_quantity|default:0|escape}</dd>
                <dt class="col-5">Magazyn</dt><dd class="col-7">{$product.warehouse_quantity|default:'-'|escape}</dd>
                <dt class="col-5">Kategoria</dt><dd class="col-7">{$product.category_name|default:'-'|escape}</dd>
                <dt class="col-5">Aktualizacja Erli</dt><dd class="col-7">{$product.remote_updated_at|default:'-'|escape}</dd>
                <dt class="col-5">Ostatni sync</dt><dd class="col-7">{$product.last_synced_at|default:'-'|escape}</dd>
                <dt class="col-5">Blad</dt><dd class="col-7">{$product.last_error_message|default:'-'|escape}</dd>
              </dl>
            </div>
          </div>
        </div>

        <div class="col-lg-7">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title mb-0">Payload Erli</h3>
            </div>
            <div class="card-body">
              <pre class="small mb-0" style="white-space: pre-wrap;">{$payloadJsonPretty|default:'{}'|escape}</pre>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
