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
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=morele&action=index">Morele</a></li>
            <li class="breadcrumb-item active" aria-current="page">{$breadcrumbCurrent|escape}</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      <div class="card mb-3">
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-3">
              <div class="text-secondary small">ID oferty</div>
              <div class="fw-semibold">{$offer.external_id|escape}</div>
            </div>
            <div class="col-md-3">
              <div class="text-secondary small">SKU</div>
              <div><code>{$offer.sku|default:'-'|escape}</code></div>
            </div>
            <div class="col-md-2">
              <div class="text-secondary small">Status</div>
              <div>{$offer.effective_status|default:'-'|escape}</div>
            </div>
            <div class="col-md-2">
              <div class="text-secondary small">Stan</div>
              <div>{$offer.effective_quantity|default:'-'|escape}</div>
            </div>
            <div class="col-md-2">
              <div class="text-secondary small">Cena</div>
              <div>{if $offer.effective_price neq ''}{$offer.effective_price|number_format:2:',':'.'} zl{else}-{/if}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h5 class="card-title mb-0">Payload API</h5>
        </div>
        <div class="card-body">
          <pre class="mb-0 small bg-light border rounded p-3" style="white-space: pre-wrap;">{$payloadJsonPretty|escape}</pre>
        </div>
      </div>
    </div>
  </div>
</main>
