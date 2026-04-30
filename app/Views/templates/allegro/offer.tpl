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
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=allegro&action=index">Allegro</a></li>
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
        .offer-warehouse-selected {
          border: 1px solid rgba(13, 110, 253, 0.14);
          border-radius: 0.9rem;
          background: rgba(13, 110, 253, 0.04);
          padding: 0.9rem 1rem;
        }

        .offer-warehouse-selected-empty {
          border-style: dashed;
          background: rgba(248, 249, 250, 0.92);
        }
      </style>

      <div class="row g-4">
        <div class="col-xl-4">
          <div class="card">
            <div class="card-header"><h3 class="card-title mb-0">Podsumowanie</h3></div>
            <div class="card-body">
              <dl class="row mb-0">
                <dt class="col-5">Konto</dt><dd class="col-7">{$offer.account_name|escape}</dd>
                <dt class="col-5">Offer ID</dt><dd class="col-7"><code>{$offer.offer_id|escape}</code></dd>
                <dt class="col-5">SKU</dt><dd class="col-7"><code>{$offer.sku|default:'-'|escape}</code></dd>
                <dt class="col-5">Cena</dt><dd class="col-7">{$offer.price_amount|default:'-'|escape} {$offer.price_currency|default:''|escape}</dd>
                <dt class="col-5">Status</dt><dd class="col-7">{$offer.publication_status|default:'-'|escape}</dd>
                <dt class="col-5">Stan Allegro</dt><dd class="col-7">{$offer.stock_available|default:'-'|escape}</dd>
                <dt class="col-5">Sprzedane</dt><dd class="col-7">{$offer.stock_sold|default:'-'|escape}</dd>
                <dt class="col-5">Kategoria</dt><dd class="col-7">{$offer.category_name|default:'-'|escape}</dd>
                <dt class="col-5">Faktura</dt><dd class="col-7">{$offer.invoice_type|default:'-'|escape}</dd>
                <dt class="col-5">Rynki</dt>
                <dd class="col-7">
                  {if $offer.marketplaces}
                    {foreach $offer.marketplaces as $market}
                      <span class="badge text-bg-light border me-1 mb-1">{$market|escape}</span>
                    {/foreach}
                  {else}
                    -
                  {/if}
                </dd>
              </dl>
            </div>
          </div>

          <div class="card mt-4">
            <div class="card-header"><h3 class="card-title mb-0">Powiazanie z magazynem</h3></div>
            <div class="card-body">
              <div class="offer-warehouse-selected {if !$offer.warehouse_product_id}offer-warehouse-selected-empty{/if}">
                {if $offer.warehouse_product_id}
                  <div class="fw-semibold">Dopasowanie na zywo po SKU</div>
                  <div>#{$offer.warehouse_product_id|escape} | {$offer.warehouse_sku|default:'-'|escape}</div>
                  <div class="text-secondary">{$offer.warehouse_product_name|default:'-'|escape}</div>
                  <div class="small text-secondary mt-1">Stan: {$offer.warehouse_quantity|default:'-'|escape} | Cena brutto: {$offer.warehouse_price_gross|default:'-'|escape} | Lokalizacja: {$offer.warehouse_localization|default:'-'|escape}</div>
                {else}
                  <div class="text-danger">Brak produktu magazynowego z takim samym SKU.</div>
                  <div class="small text-secondary mt-1">Powiazanie nie jest nigdzie zapisywane. Widok sprawdza na zywo aktualne SKU oferty Allegro i szuka takiego samego SKU na liscie produktow.</div>
                {/if}
              </div>
            </div>
          </div>

          <div class="card mt-4">
            <div class="card-header"><h3 class="card-title mb-0">Szybkie akcje kolejki</h3></div>
            <div class="card-body">
              <form method="post" action="{$baseUrl}?controller=allegro&action=queue" class="row g-2">
                <div class="col-12">
                  <label class="form-label">Operacja</label>
                  <select name="operation" class="form-select">
                    <option value="set_name">Nazwa: ustaw recznie</option>
                    <option value="set_sku_manual">SKU: ustaw recznie</option>
                    <option value="set_sku_from_product">SKU: z magazynu</option>
                    <option value="set_price">Cena: ustaw recznie</option>
                    <option value="set_price_from_product">Cena: z magazynu</option>
                    <option value="link_product_auto">Produkt Allegro: auto</option>
                    <option value="link_product_id">Produkt Allegro: ustaw ID</option>
                    <option value="end_offer">Zakoncz</option>
                    <option value="resume_offer">Wznow</option>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label">Wartosc / ID produktu</label>
                  <input type="text" name="value" class="form-control mb-2" placeholder="nazwa / cena / SKU">
                  <input type="text" name="product_id" class="form-control" placeholder="Allegro product ID">
                </div>
                <div class="col-12">
                  <input type="hidden" name="manual_offer_ids" value="{$offer.offer_id|escape}">
                  <button type="submit" class="btn btn-dark">Dodaj te oferte do kolejki</button>
                </div>
              </form>
            </div>
          </div>

          <div class="card mt-4">
            <div class="card-header"><h3 class="card-title mb-0">Zdjecia</h3></div>
            <div class="card-body">
              <div class="row g-2">
                {foreach $offer.images as $image}
                  <div class="col-6">
                    <a href="{$image.url|escape}" target="_blank" rel="noreferrer">
                      <img src="{$image.url|escape}" alt="" class="img-fluid rounded border">
                    </a>
                  </div>
                {foreachelse}
                  <div class="col-12 text-secondary">Brak zapisanych zdjec.</div>
                {/foreach}
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-8">
          <div class="card mb-4">
            <div class="card-header"><h3 class="card-title mb-0">Parametry</h3></div>
            <div class="table-responsive">
              <table class="table table-sm table-striped align-middle mb-0">
                <thead class="table-light">
                  <tr><th>ID</th><th>Nazwa</th><th>Wartosci</th></tr>
                </thead>
                <tbody>
                  {foreach $offer.parameters as $parameter}
                    <tr>
                      <td><code>{$parameter.id|escape}</code></td>
                      <td>{$parameter.name|escape}</td>
                      <td>
                        {foreach $parameter.values as $value}
                          <span class="badge text-bg-light border me-1 mb-1">{$value|escape}</span>
                        {foreachelse}
                          <span class="text-secondary">-</span>
                        {/foreach}
                      </td>
                    </tr>
                  {foreachelse}
                    <tr><td colspan="3" class="text-center text-secondary py-4">Brak zapisanych parametrow.</td></tr>
                  {/foreach}
                </tbody>
              </table>
            </div>
          </div>

          <div class="card">
            <div class="card-header"><h3 class="card-title mb-0">Raw JSON</h3></div>
            <div class="card-body">
              <pre class="small mb-0" style="white-space: pre-wrap;">{$offerPayloadJson|escape}</pre>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
