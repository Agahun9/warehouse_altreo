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
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=empik&action=index">Empik</a></li>
            <li class="breadcrumb-item active" aria-current="page">{$breadcrumbCurrent|escape}</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0">Podsumowanie oferty</h3>
          <a href="{$baseUrl}?controller=empik&action=index" class="btn btn-sm btn-outline-secondary">Wroc do listy</a>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-3"><strong>Offer ID:</strong><br>{$offer.offer_id|escape}</div>
            <div class="col-md-3"><strong>Konto:</strong><br>{$offer.account_name|escape}</div>
            <div class="col-md-3"><strong>State:</strong><br>{$offer.state_code|default:'-'|escape}</div>
            <div class="col-md-3"><strong>Aktywna:</strong><br>{if $offer.active}Tak{else}Nie{/if}</div>
            <div class="col-md-3"><strong>shop_sku:</strong><br><code>{$offer.shop_sku|default:'-'|escape}</code></div>
            <div class="col-md-3"><strong>product_sku:</strong><br><code>{$offer.product_sku|default:'-'|escape}</code></div>
            <div class="col-md-3"><strong>product_id:</strong><br><code>{$offer.product_id|default:'-'|escape}</code></div>
            <div class="col-md-3"><strong>Ilosc:</strong><br>{$offer.quantity|default:'-'|escape}</div>
            <div class="col-md-4"><strong>Kategoria:</strong><br>{$offer.category_label|default:'-'|escape}<br><code>{$offer.category_code|default:'-'|escape}</code></div>
            <div class="col-md-4"><strong>Cena:</strong><br>{if isset($offer.price) and $offer.price ne ''}{$offer.price|escape} {$offer.currency_iso_code|default:''|escape}{else}-{/if}</div>
            <div class="col-md-4"><strong>Cena calkowita:</strong><br>{if isset($offer.total_price) and $offer.total_price ne ''}{$offer.total_price|escape} {$offer.currency_iso_code|default:''|escape}{else}-{/if}</div>
            <div class="col-12"><strong>Tytul:</strong><br>{$offer.product_title|default:'-'|escape}</div>
            <div class="col-12"><strong>Opis:</strong><br><div class="small text-secondary">{$offer.description|default:'-'|escape}</div></div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title mb-0">Surowe dane JSON</h3>
        </div>
        <div class="card-body">
          <pre class="small mb-0" style="white-space: pre-wrap;">{$offerJsonPretty|default:'{}'|escape}</pre>
        </div>
      </div>
    </div>
  </div>
</main>
