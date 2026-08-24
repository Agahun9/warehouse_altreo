<main class="app-main">
  <style>
    .product-form-shell {
      --pf-accent: #1f6f78;
      --pf-soft: #eef8f8;
      --pf-soft-2: #f7fbfc;
      --pf-line: rgba(31, 111, 120, 0.14);
    }

    .product-form-hero {
      background:
        radial-gradient(circle at top right, rgba(32, 201, 151, 0.18), transparent 30%),
        linear-gradient(135deg, #0f4c5c 0%, #1f6f78 52%, #2f8f9d 100%);
      color: #fff;
      border-radius: 1rem;
      padding: 1.4rem;
      box-shadow: 0 18px 40px rgba(15, 76, 92, 0.16);
    }

    .product-form-hero .badge {
      background: rgba(255, 255, 255, 0.14);
      color: #fff;
      border: 1px solid rgba(255, 255, 255, 0.16);
    }

    .product-main-card {
      border: 1px solid var(--pf-line);
      border-radius: 1rem;
      overflow: hidden;
      box-shadow: 0 10px 28px rgba(15, 76, 92, 0.07);
    }

    .product-main-card .card-header {
      background: linear-gradient(180deg, var(--pf-soft) 0%, #fff 100%);
      border-bottom: 1px solid rgba(31, 111, 120, 0.1);
      padding: 1rem 1.25rem;
    }

    .product-section-box {
      border: 1px solid rgba(31, 111, 120, 0.1);
      border-radius: 1rem;
      background: #fff;
      box-shadow: 0 6px 18px rgba(15, 76, 92, 0.04);
      padding: 1rem;
      margin-bottom: 1rem;
    }

    .product-section-box.soft {
      background: var(--pf-soft-2);
    }

    .product-section-title {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
      margin-bottom: 0.9rem;
    }

    .product-section-title h5 {
      margin: 0;
      font-size: 1rem;
      font-weight: 700;
      color: #17353b;
    }

    .product-section-title p {
      margin: 0.2rem 0 0;
      color: #64767b;
      font-size: 0.9rem;
    }

    .product-section-chip {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      border-radius: 999px;
      padding: 0.35rem 0.7rem;
      background: rgba(31, 111, 120, 0.08);
      color: #1b4f58;
      font-size: 0.84rem;
      font-weight: 600;
      white-space: nowrap;
    }

    .product-form-shell .form-control,
    .product-form-shell .form-select {
      border-radius: 0.8rem;
      border-color: rgba(31, 111, 120, 0.16);
      box-shadow: none;
    }

    .product-form-shell .form-control:focus,
    .product-form-shell .form-select:focus {
      border-color: rgba(31, 111, 120, 0.4);
      box-shadow: 0 0 0 0.18rem rgba(31, 111, 120, 0.12);
    }

    .product-form-shell .form-label {
      font-weight: 600;
      color: #17353b;
    }

    .product-summary-box {
      position: sticky;
      top: 1rem;
      border: 1px solid rgba(31, 111, 120, 0.12);
      border-radius: 1rem;
      background: linear-gradient(180deg, #ffffff 0%, var(--pf-soft-2) 100%);
      box-shadow: 0 10px 28px rgba(15, 76, 92, 0.07);
      padding: 1rem;
    }

    .product-summary-row {
      display: flex;
      justify-content: space-between;
      gap: 1rem;
      padding: 0.7rem 0;
      border-bottom: 1px dashed rgba(31, 111, 120, 0.14);
      font-size: 0.92rem;
    }

    .product-summary-row:last-child {
      border-bottom: 0;
    }

    .product-tabs-bar {
      position: sticky;
      top: 0.75rem;
      z-index: 5;
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
      padding: 1rem 1.25rem 0;
      background: linear-gradient(180deg, rgba(247, 251, 252, 0.98) 0%, rgba(255, 255, 255, 0.95) 100%);
      border-bottom: 1px solid rgba(31, 111, 120, 0.12);
      backdrop-filter: blur(10px);
    }

    .product-tab-button {
      display: inline-flex;
      align-items: center;
      gap: 0.55rem;
      border: 1px solid rgba(31, 111, 120, 0.18);
      border-radius: 999px;
      padding: 0.8rem 1rem;
      background: #fff;
      color: #35555b;
      font-weight: 700;
      font-size: 0.92rem;
      line-height: 1;
      box-shadow: 0 6px 16px rgba(15, 76, 92, 0.06);
      transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease, background 0.18s ease, color 0.18s ease;
    }

    .product-tab-button:hover {
      transform: translateY(-1px);
      border-color: rgba(31, 111, 120, 0.32);
      color: #17353b;
    }

    .product-tab-button.active {
      background: linear-gradient(135deg, #0f4c5c 0%, #1f6f78 100%);
      color: #fff;
      border-color: transparent;
      box-shadow: 0 12px 24px rgba(15, 76, 92, 0.2);
    }

    .product-tab-button small {
      display: block;
      font-size: 0.72rem;
      font-weight: 500;
      opacity: 0.82;
    }

    .product-tab-panel {
      display: none;
      animation: productTabFade 0.22s ease;
    }

    .product-tab-panel.active {
      display: block;
    }

    .product-tab-stack {
      display: grid;
      gap: 1rem;
    }

    .product-section-box.accent {
      background: linear-gradient(180deg, #f4fbfc 0%, #ffffff 100%);
      border-color: rgba(31, 111, 120, 0.16);
      box-shadow: 0 12px 26px rgba(15, 76, 92, 0.08);
    }

    .product-inline-note {
      margin-bottom: 1rem;
      padding: 0.9rem 1rem;
      border-radius: 0.9rem;
      background: linear-gradient(135deg, rgba(31, 111, 120, 0.08) 0%, rgba(32, 201, 151, 0.08) 100%);
      border: 1px solid rgba(31, 111, 120, 0.14);
      color: #31555b;
      font-size: 0.92rem;
    }

    .product-mini-stat {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 0.75rem;
      margin-bottom: 1rem;
    }

    .product-mini-stat-card {
      border-radius: 0.9rem;
      padding: 0.85rem 0.95rem;
      background: #fff;
      border: 1px solid rgba(31, 111, 120, 0.12);
      box-shadow: 0 8px 18px rgba(15, 76, 92, 0.05);
    }

    .product-mini-stat-card span {
      display: block;
      color: #698186;
      font-size: 0.76rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      margin-bottom: 0.2rem;
    }

    .product-mini-stat-card strong {
      font-size: 1rem;
      color: #17353b;
    }

    .product-gallery-dropzone {
      border: 2px dashed rgba(31, 111, 120, 0.28);
      border-radius: 1rem;
      background: linear-gradient(180deg, rgba(238, 248, 248, 0.9), rgba(255, 255, 255, 0.98));
      padding: 1rem;
      text-align: center;
      cursor: pointer;
      transition: border-color 0.18s ease, transform 0.18s ease, box-shadow 0.18s ease;
    }

    .product-gallery-dropzone.is-dragover {
      border-color: #1f6f78;
      transform: translateY(-1px);
      box-shadow: 0 12px 24px rgba(15, 76, 92, 0.1);
    }

    .product-gallery-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
      gap: 0.85rem;
    }

    .product-gallery-item {
      border: 1px solid rgba(31, 111, 120, 0.14);
      border-radius: 1rem;
      background: #fff;
      overflow: hidden;
      box-shadow: 0 8px 18px rgba(15, 76, 92, 0.05);
      cursor: grab;
    }

    .product-gallery-item.dragging {
      opacity: 0.45;
    }

    .product-gallery-thumb {
      width: 100%;
      aspect-ratio: 1 / 1;
      object-fit: cover;
      display: block;
      background: #f3f7f8;
    }

    .product-gallery-meta {
      padding: 0.75rem;
      display: grid;
      gap: 0.5rem;
    }

    .product-gallery-url {
      font-size: 0.78rem;
      color: #61777d;
      word-break: break-all;
      min-height: 2.2rem;
    }

    .product-gallery-actions {
      display: flex;
      gap: 0.35rem;
      flex-wrap: wrap;
    }

    .product-gallery-empty {
      border-radius: 1rem;
      border: 1px dashed rgba(31, 111, 120, 0.2);
      padding: 1rem;
      color: #61777d;
      background: rgba(247, 251, 252, 0.9);
    }

    .product-sales-hero {
      border-radius: 1rem;
      padding: 1rem;
      background: linear-gradient(135deg, #12343b 0%, #1f6f78 60%, #249f8f 100%);
      color: #fff;
      box-shadow: 0 16px 34px rgba(15, 76, 92, 0.18);
      margin-bottom: 1rem;
    }

    .product-sales-kpis {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 0.75rem;
      margin-top: 1rem;
    }

    .product-sales-kpi {
      border-radius: 0.9rem;
      padding: 0.85rem;
      background: rgba(255, 255, 255, 0.12);
      border: 1px solid rgba(255, 255, 255, 0.16);
    }

    .product-sales-kpi span {
      display: block;
      font-size: 0.72rem;
      text-transform: uppercase;
      color: rgba(255, 255, 255, 0.72);
      margin-bottom: 0.2rem;
    }

    .product-sales-kpi strong {
      font-size: 1.15rem;
      color: #fff;
    }

    .product-sales-table th {
      color: #49656b;
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      background: rgba(238, 248, 248, 0.9);
    }

    .product-sales-qty {
      font-weight: 800;
      color: #17353b;
      font-size: 1rem;
    }

    .product-sales-event {
      border: 1px solid rgba(31, 111, 120, 0.12);
      border-radius: 0.9rem;
      padding: 0.85rem;
      background: #fff;
      box-shadow: 0 8px 18px rgba(15, 76, 92, 0.04);
    }

    .product-sales-event + .product-sales-event {
      margin-top: 0.65rem;
    }

    .contour-picker-results {
      max-height: 240px;
      overflow: auto;
      border: 1px solid rgba(31, 111, 120, 0.12);
      border-radius: 0.9rem;
      background: #fff;
      padding: 0.35rem;
    }

    .contour-picker-option {
      display: block;
      width: 100%;
      text-align: left;
      border: 0;
      background: transparent;
      border-radius: 0.7rem;
      padding: 0.6rem 0.75rem;
      color: #17353b;
    }

    .contour-picker-option:hover,
    .contour-picker-option.active {
      background: rgba(31, 111, 120, 0.08);
    }

    .empik-remote-select {
      min-height: 14.5rem;
    }

    @keyframes productTabFade {
      from {
        opacity: 0;
        transform: translateY(6px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @media (max-width: 1199.98px) {
      .product-summary-box {
        position: static;
      }
    }

    @media (max-width: 767.98px) {
      .product-tabs-bar {
        top: 0;
        padding-top: 0.85rem;
      }

      .product-tab-button {
        width: 100%;
        justify-content: flex-start;
      }

      .product-mini-stat {
        grid-template-columns: 1fr;
      }

      .product-sales-kpis {
        grid-template-columns: 1fr;
      }
    }
  </style>
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
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=products&action=index">Produkty</a></li>
            <li class="breadcrumb-item active" aria-current="page">{$breadcrumbCurrent|escape}</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      {if $flashSuccess}
        <div class="alert alert-success">{$flashSuccess|escape}</div>
      {/if}
      {if $flashError}
        <div class="alert alert-danger">{$flashError|escape}</div>
      {/if}

      <div class="product-form-shell">
        <div class="product-form-hero mb-4">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
              <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge rounded-pill px-3 py-2"><i class="bi bi-box-seam me-1"></i>Produkt</span>
                <span class="badge rounded-pill px-3 py-2"><i class="bi bi-pencil-square me-1"></i>{if isset($product.id)}Edycja{else}Nowy rekord{/if}</span>
              </div>
              <h3 class="h4 mb-2">{if $product.product_name|default:''}{$product.product_name|escape}{else}Uzupelnij dane produktu{/if}</h3>
              <p class="mb-0 text-white-50">Formularz zostal uporzadkowany w sekcje. Najczesciej uzywane pola sa na gorze, a rzeczy bardziej zaawansowane znajdziesz nizej.</p>
            </div>
            <div class="text-end">
              <div class="small text-white-50 mb-1">SKU</div>
              <div class="fs-5 fw-semibold" id="heroSku">{if $product.sku|default:''}{$product.sku|escape}{else}wygeneruje sie automatycznie{/if}</div>
            </div>
          </div>
        </div>

      <div class="card product-main-card">
        <div class="card-header">
          <h3 class="card-title mb-1">Formularz produktu</h3>
          <div class="small text-secondary">Uzupelnij dane podstawowe, a dodatkowe opcje rozdzielone sa na osobne, czytelniejsze bloki.</div>
        </div>
        <div class="product-tabs-bar" role="tablist" aria-label="Sekcje formularza produktu">
          <button type="button" class="product-tab-button active" data-product-tab-trigger="overview" aria-pressed="true">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Podstawy<small>Nazwa, magazyn, ceny</small></span>
          </button>
          <button type="button" class="product-tab-button" data-product-tab-trigger="relations" aria-pressed="false">
            <i class="bi bi-diagram-3-fill"></i>
            <span>Powiazania<small>Wspolny i pochodny stan</small></span>
          </button>
          <button type="button" class="product-tab-button" data-product-tab-trigger="attributes" aria-pressed="false">
            <i class="bi bi-sliders2"></i>
            <span>Pola wlasne<small>Cechy i dodatkowe dane</small></span>
          </button>
          {if isset($product.id)}
            <button type="button" class="product-tab-button" data-product-tab-trigger="sales-history" aria-pressed="false">
              <i class="bi bi-graph-up-arrow"></i>
              <span>Historia sprzedazy<small>Sellasist, dzienne sumy</small></span>
            </button>
          {/if}
          <button type="button" class="product-tab-button" data-product-tab-trigger="marketplace" aria-pressed="false">
            <i class="bi bi-shop"></i>
            <span>Allegro<small>Parametry i kopiowanie</small></span>
          </button>
          <button type="button" class="product-tab-button" data-product-tab-trigger="empik" aria-pressed="false">
            <i class="bi bi-bag"></i>
            <span>Empik<small>Parametry kategorii</small></span>
          </button>
          <button type="button" class="product-tab-button" data-product-tab-trigger="mediamarkt" aria-pressed="false">
            <i class="bi bi-tv"></i>
            <span>MediaMarkt<small>Parametry kategorii</small></span>
          </button>
          <button type="button" class="product-tab-button" data-product-tab-trigger="temu" aria-pressed="false">
            <i class="bi bi-grid-3x3-gap"></i>
            <span>Temu<small>Parametry kategorii</small></span>
          </button>
        </div>
        <form method="post" action="{$formAction|escape}" id="product-form">
          <input type="hidden" name="return_url" value="{$returnUrl|default:'./index.php?controller=products&action=index'|escape}">
        
          {if isset($product.id)}
            <input type="hidden" name="id" value="{$product.id}">
          {/if}

          <div class="card-body">
            <div class="row g-4">
              <div class="col-xl-8">
                <div class="product-tab-panel active" data-product-tab-panel="overview">
                  <div class="product-inline-note">
                    Tu sa najwazniejsze pola do codziennej pracy. Najpierw uzupelnij podstawy produktu, potem stan i ceny.
                  </div>
                  <div class="product-mini-stat">
                    <div class="product-mini-stat-card">
                      <span>SKU</span>
                      <strong id="overviewSku">{if $product.sku|default:''}{$product.sku|escape}{else}Automatyczne{/if}</strong>
                    </div>
                    <div class="product-mini-stat-card">
                      <span>Stan</span>
                      <strong id="overviewQuantity">{$product.quantity|default:0|escape}</strong>
                    </div>
                    <div class="product-mini-stat-card">
                      <span>Cena brutto</span>
                      <strong id="overviewPrice">{$product.price_gross|default:'0.00'|escape}</strong>
                    </div>
                  </div>
                  <div class="product-tab-stack">
                    <div class="product-section-box accent">
                      <div class="product-section-title">
                        <div>
                          <h5><i class="bi bi-stars me-2"></i>Podstawowe informacje</h5>
                          <p>Nazwa, opis, kategoria i glowny obrazek.</p>
                        </div>
                        <span class="product-section-chip"><i class="bi bi-check2-circle"></i>Start tutaj</span>
                      </div>
                      <div class="row g-3">
                        <div class="col-md-4">
                          <label for="sku" class="form-label">SKU</label>
                          <input type="text" class="form-control" id="sku" name="sku" value="{$product.sku|default:''|escape}" readonly>
                          <div class="form-text">SKU jest nadawane automatycznie na podstawie kategorii i po przypisaniu nie zmienia sie.</div>
                        </div>
                        <div class="col-md-4">
                          <label for="ean" class="form-label">EAN</label>
                          <input type="text" class="form-control" id="ean" name="ean" value="{$product.ean|default:''|escape}" inputmode="numeric" autocomplete="off" placeholder="np. 5901234123457">
                          <div class="form-text">Opcjonalnie. Dozwolone dlugosci: 8, 12, 13 lub 14 cyfr.</div>
                        </div>
                        <div class="col-md-4">
                          <label for="product_name" class="form-label">Nazwa produktu</label>
                          <input type="text" class="form-control" id="product_name" name="product_name" value="{$product.product_name|default:''|escape}" required>
                        </div>
                        <div class="col-12">
                          <label for="description" class="form-label">Opis</label>
                          <textarea class="form-control" id="description" name="description" rows="4" placeholder="Krotki opis produktu">{$product.description|default:''|escape}</textarea>
                        </div>
                        <div class="col-md-5">
                          <label for="category_id" class="form-label">Kategoria</label>
                          <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">Wybierz kategorie</option>
                            {foreach $categories as $category}
                              <option value="{$category.id}" data-sku-prefix="{$category.sku_prefix|default:'PRD'|escape}" data-allegro-category-id="{$category.allegro_category_id|default:''|escape}" data-empik-category-id="{$category.empik_category_id|default:''|escape}" data-mediamarkt-category-id="{$category.mediamarkt_category_id|default:''|escape}" data-temu-category-id="{$category.temu_category_id|default:''|escape}"{if $product.category_id|default:'' == $category.id} selected{/if}>{$category.name|escape}</option>
                            {/foreach}
                          </select>
                        </div>
                        <div class="col-md-3">
                          <label for="localization" class="form-label">Lokalizacja</label>
                          <input type="text" class="form-control" id="localization" name="localization" value="{$product.localization|default:''|escape}">
                        </div>
                        <div class="col-12">
                          <label for="img" class="form-label">Galeria grafik</label>
                          <input type="hidden" class="form-control" id="img" name="img" value="{$product.img|default:''|escape}">
                          <input type="file" id="productGalleryFileInput" class="d-none" accept="image/jpeg,image/png,image/webp,image/gif" multiple>

                          <div class="product-gallery-dropzone mb-3" id="productGalleryDropzone">
                            <div class="fw-semibold mb-1">Przeciagnij tu zdjecia albo kliknij, aby je dodac</div>
                            <div class="small text-secondary">Grafiki wrzucaja sie od razu do produktu. Potem mozesz je przesuwac, zmieniac kolejnosc i usuwac.</div>
                          </div>

                          <div class="small text-secondary mb-2" id="productGalleryStatus">Kolejnosc miniatur odpowiada kolejnosci zapisanej w produkcie.</div>
                          <div class="product-gallery-grid" id="productGalleryGrid"></div>
                        </div>
                      </div>
                    </div>

                    <div class="product-section-box">
                      <div class="product-section-title">
                        <div>
                          <h5><i class="bi bi-boxes me-2"></i>Magazyn i cechy</h5>
                          <p>Stan magazynowy, identyfikatory i cechy fizyczne produktu.</p>
                        </div>
                        <span class="product-section-chip"><i class="bi bi-sliders"></i>Operacyjne</span>
                      </div>
                      <div class="row g-3">
                        <div class="col-md-3">
                          <label for="quantity" class="form-label">Ilosc</label>
                          <input type="number" min="0" class="form-control" id="quantity" name="quantity" value="{$product.quantity|default:0|escape}">
                        </div>
                        <div class="col-md-3">
                          <label for="dimensions" class="form-label">Wymiary</label>
                          <input type="text" class="form-control" id="dimensions" name="dimensions" value="{$product.dimensions|default:''|escape}" placeholder="np. 120x80">
                        </div>
                        <div class="col-md-6">
                          <label for="contoursSearch" class="form-label">Obrys</label>
                          <input type="hidden" id="contours" name="contours" value="{$product.contours|default:''|escape}">
                          <input type="text" class="form-control mb-2" id="contoursSearch" value="{$product.contours|default:''|escape}" placeholder="Wyszukaj nazwe obrysu">
                          <div class="contour-picker-results" id="contourPickerResults">
                            {foreach $contourDirectories as $contourDirectory}
                              <button type="button" class="contour-picker-option{if $product.contours|default:'' == $contourDirectory} active{/if}" data-contour-option="{$contourDirectory|escape}">{$contourDirectory|escape}</button>
                            {foreachelse}
                              <div class="small text-secondary p-2">Brak zapisanych nazw obrysow.</div>
                            {/foreach}
                          </div>
                          <div class="form-text">Wybierasz nazwe obrysu z bazy. Kliknij wynik albo wyszukaj po nazwie.</div>
                        </div>
                      </div>
                    </div>

                    <div class="product-section-box soft">
                      <div class="product-section-title">
                        <div>
                          <h5><i class="bi bi-cash-coin me-2"></i>Ceny i VAT</h5>
                          <p>Wpisz netto lub brutto. Formularz sam przeliczy druga wartosc.</p>
                        </div>
                        <span class="product-section-chip"><i class="bi bi-calculator"></i>Automatyczne</span>
                      </div>
                      <div class="row g-3">
                        <div class="col-md-4">
                          <label for="vat_rate" class="form-label">VAT %</label>
                          <input type="number" step="0.01" min="0" class="form-control" id="vat_rate" name="vat_rate" value="{$product.vat_rate|default:'23.00'|escape}">
                        </div>
                        <div class="col-md-4">
                          <label for="price_net" class="form-label">Cena netto</label>
                          <input type="number" step="0.01" min="0" class="form-control" id="price_net" name="price_net" value="{$product.price_net|default:'0.00'|escape}">
                        </div>
                        <div class="col-md-4">
                          <label for="price_gross" class="form-label">Cena brutto</label>
                          <input type="number" step="0.01" min="0" class="form-control" id="price_gross" name="price_gross" value="{$product.price_gross|default:'0.00'|escape}">
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="product-tab-panel" data-product-tab-panel="relations">
                  <div class="product-inline-note">
                    Te ustawienia kontroluja wspolny stan i zaleznosci magazynowe. Traktuj je jako dwa rozne tryby pracy produktu.
                  </div>
                  <div class="product-tab-stack">
                    <div class="product-section-box soft">
                      <div class="product-section-title">
                        <div>
                          <h5><i class="bi bi-link-45deg me-2"></i>Powiazane produkty</h5>
                          <p>Produkty z tej grupy korzystaja z tego samego stanu magazynowego i lokalizacji. Tryb alternatywny wobec stanu pochodnego.</p>
                        </div>
                        <span class="product-section-chip"><i class="bi bi-diagram-3"></i>Wspolny stan</span>
                      </div>
                      <div class="product-section-box soft mb-3">
                        <div class="row g-2">
                          <div class="col-md-5">
                            <label class="form-label">Szukaj produktu do powiazania</label>
                            <input type="text" id="related-product-search" class="form-control form-control-sm" placeholder="Min. 2 znaki">
                          </div>
                          <div class="col-md-5">
                            <label class="form-label">Wyniki</label>
                            <select id="related-product-select" class="form-select form-select-sm">
                              <option value="">Wpisz fraze, aby wyszukac produkt...</option>
                            </select>
                          </div>
                          <div class="col-md-2 d-grid">
                            <label class="form-label d-none d-md-block">&nbsp;</label>
                            <button type="button" id="add-related-product-button" class="btn btn-outline-primary btn-sm">Powiaz</button>
                          </div>
                        </div>
                        <div class="form-text mt-2">Po zapisaniu wszystkie przypiete produkty beda dzielic te same pola: <strong>Ilosc</strong> i <strong>Lokalizacja</strong>.</div>
                      </div>
                      <div id="related-products-container" class="row g-3">
                        {if $relatedProducts}
                          {foreach $relatedProducts as $relatedProduct}
                            <div class="col-md-6 related-product-card" data-product-id="{$relatedProduct.id}">
                              <div class="border rounded p-3 h-100 bg-light-subtle">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                  <div>
                                    <div class="fw-semibold">{$relatedProduct.product_name|escape}</div>
                                    <div class="small text-secondary">#{$relatedProduct.id} | {$relatedProduct.sku|default:'-'|escape}</div>
                                  </div>
                                  <button type="button" class="btn btn-sm btn-outline-danger remove-related-product">Usun</button>
                                </div>
                                <input type="hidden" name="related_product_ids[]" value="{$relatedProduct.id}">
                                {if $relatedProduct.category_name|default:''}
                                  <div class="small text-secondary">Kategoria: {$relatedProduct.category_name|escape}</div>
                                {/if}
                              </div>
                            </div>
                          {/foreach}
                        {else}
                          <div class="col-12" id="noRelatedProducts">
                            <div class="alert alert-light border mb-0">Brak powiazanych produktow. Ten produkt korzysta z wlasnego stanu i lokalizacji.</div>
                          </div>
                        {/if}
                      </div>
                    </div>

                    <div class="product-section-box soft">
                      <div class="product-section-title">
                        <div>
                          <h5><i class="bi bi-signpost-split me-2"></i>Powiazanie stanu pochodnego</h5>
                          <p>Ten produkt bierze ilosc jako minimum z przypietych produktow i laczy ich lokalizacje przez "/". Tryb alternatywny wobec zwyklych powiazan.</p>
                        </div>
                        <span class="product-section-chip"><i class="bi bi-arrow-down-up"></i>Minimum</span>
                      </div>
                      <div class="product-section-box soft mb-3">
                        <div class="row g-2">
                          <div class="col-md-5">
                            <label class="form-label">Szukaj produktu zrodlowego</label>
                            <input type="text" id="derived-product-search" class="form-control form-control-sm" placeholder="Min. 2 znaki">
                          </div>
                          <div class="col-md-5">
                            <label class="form-label">Wyniki</label>
                            <select id="derived-product-select" class="form-select form-select-sm">
                              <option value="">Wpisz fraze, aby wyszukac produkt...</option>
                            </select>
                          </div>
                          <div class="col-md-2 d-grid">
                            <label class="form-label d-none d-md-block">&nbsp;</label>
                            <button type="button" id="add-derived-product-button" class="btn btn-outline-primary btn-sm">Dodaj</button>
                          </div>
                        </div>
                        <div class="form-text mt-2">Po zapisaniu produkt przejmie: <strong>Ilosc = najnizsza ilosc z przypietych</strong>, <strong>Lokalizacja = polaczone lokalizacje</strong> (np. A100 / S130).</div>
                      </div>
                      <div id="derived-products-container" class="row g-3">
                        {if $derivedStockSources}
                          {foreach $derivedStockSources as $derivedProduct}
                            <div class="col-md-6 derived-product-card" data-product-id="{$derivedProduct.id}">
                              <div class="border rounded p-3 h-100 bg-light-subtle">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                  <div>
                                    <div class="fw-semibold">{$derivedProduct.product_name|escape}</div>
                                    <div class="small text-secondary">#{$derivedProduct.id} | {$derivedProduct.sku|default:'-'|escape}</div>
                                  </div>
                                  <button type="button" class="btn btn-sm btn-outline-danger remove-derived-product">Usun</button>
                                </div>
                                <input type="hidden" name="derived_source_product_ids[]" value="{$derivedProduct.id}">
                                {if $derivedProduct.category_name|default:''}
                                  <div class="small text-secondary">Kategoria: {$derivedProduct.category_name|escape}</div>
                                {/if}
                              </div>
                            </div>
                          {/foreach}
                        {else}
                          <div class="col-12" id="noDerivedProducts">
                            <div class="alert alert-light border mb-0">Brak produktow zrodlowych. Ten produkt korzysta z wlasnej ilosci i lokalizacji.</div>
                          </div>
                        {/if}
                      </div>
                    </div>
                  </div>
                </div>

                <div class="product-tab-panel" data-product-tab-panel="attributes">
                <div class="product-section-box">
                  <div class="product-section-title">
                    <div>
                      <h5><i class="bi bi-tags me-2"></i>Pola wlasne produktu</h5>
                      <p>Dodajesz tylko te pola, ktorych chcesz uzyc dla tego produktu.</p>
                    </div>
                    <span class="product-section-chip"><i class="bi bi-plus-square"></i>Elastyczne</span>
                  </div>
                  <div class="product-section-box soft mb-3">
                    <div class="row g-2 align-items-end">
                      <div class="col-md-8">
                        <label class="form-label">Dodaj istniejace pole z listy</label>
                        <select id="existingCustomFieldSelect" class="form-select form-select-sm">
                          <option value="">Wybierz pole wlasne</option>
                          {foreach $customFieldDefinitions as $customFieldDefinition}
                            <option value="{$customFieldDefinition.id|escape}" data-name="{$customFieldDefinition.name|escape:'html'}">{$customFieldDefinition.name|escape}</option>
                          {/foreach}
                        </select>
                      </div>
                      <div class="col-md-4 d-grid">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="addExistingCustomFieldBtn">Dodaj pole do produktu</button>
                      </div>
                    </div>
                  </div>
                  <div id="assignedCustomFieldsContainer" class="row g-3">
                    {if $assignedCustomFields}
                      {foreach $assignedCustomFields as $assignedCustomField}
                        <div class="col-md-6 assigned-custom-field-card" data-definition-id="{$assignedCustomField.id}">
                          <div class="border rounded p-3 h-100 bg-light-subtle">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                              <label class="form-label mb-0" for="custom_field_{$assignedCustomField.id}">{$assignedCustomField.name|escape}</label>
                              <button type="button" class="btn btn-sm btn-outline-danger remove-assigned-custom-field">Usun</button>
                            </div>
                            <input
                              type="text"
                              class="form-control"
                              id="custom_field_{$assignedCustomField.id}"
                              name="custom_field_values[{$assignedCustomField.id}]"
                              value="{$assignedCustomField.value|default:''|escape}"
                              placeholder="Wpisz wartosc"
                            >
                          </div>
                        </div>
                      {/foreach}
                    {else}
                      <div class="col-12" id="noAssignedCustomFields">
                        <div class="alert alert-light border mb-0">Brak dodanych pol wlasnych dla tego produktu.</div>
                      </div>
                    {/if}
                  </div>
                  <div class="product-section-box soft mt-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <div>
                        <div class="fw-semibold">Dodaj nowe pole do systemu</div>
                        <div class="small text-secondary">Nowe pola beda widoczne przy kolejnych produktach i w eksporcie CSV.</div>
                      </div>
                      <button type="button" class="btn btn-sm btn-outline-primary" id="addCustomFieldRow">Dodaj pole</button>
                    </div>
                    <div id="newCustomFieldsContainer" class="d-grid gap-2">
                      <div class="row g-2 new-custom-field-row">
                        <div class="col-md-5">
                          <input type="text" class="form-control form-control-sm" name="new_custom_field_name[]" placeholder="Np. Kolor">
                        </div>
                        <div class="col-md-6">
                          <input type="text" class="form-control form-control-sm" name="new_custom_field_value[]" placeholder="Np. Czarny">
                        </div>
                        <div class="col-md-1 d-grid">
                          <button type="button" class="btn btn-sm btn-outline-danger remove-custom-field-row">x</button>
                        </div>
                      </div>
                    </div>
                  </div>
                  {if $customFieldDefinitions}
                    <div class="product-section-box soft mb-0">
                      <div class="fw-semibold mb-2 text-danger">Usuwanie pola z systemu</div>
                      <div class="small text-secondary mb-3">Usuniecie definicji skasuje to pole ze wszystkich produktow oraz z opcji CSV.</div>
                      <div class="row g-2">
                        <div class="col-md-8">
                          <select id="deleteCustomFieldDefinitionSelect" class="form-select form-select-sm">
                            <option value="">Wybierz pole do usuniecia z systemu</option>
                            {foreach $customFieldDefinitions as $customFieldDefinition}
                              <option value="{$customFieldDefinition.id|escape}">{$customFieldDefinition.name|escape}</option>
                            {/foreach}
                          </select>
                        </div>
                        <div class="col-md-4 d-grid">
                          <button type="button" class="btn btn-outline-danger btn-sm" id="deleteCustomFieldDefinitionBtn">Usun definicje pola</button>
                        </div>
                      </div>
                    </div>
                  {/if}
                </div>
                </div>

                {if isset($product.id)}
                <div class="product-tab-panel" data-product-tab-panel="sales-history">
                  <div class="product-sales-hero">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                      <div>
                        <div class="small text-white-50 mb-1">Historia sprzedazy z odjec Sellasist</div>
                        <h5 class="mb-1"><i class="bi bi-graph-up-arrow me-2"></i>{$product.product_name|default:'Produkt'|escape}</h5>
                        <div class="small text-white-50">Podsumowanie bazuje na wpisach historii zmian: odjeciach stanu magazynowego przez Sellasist.</div>
                      </div>
                      <span class="badge rounded-pill text-bg-light">SKU: {$product.sku|default:'-'|escape}</span>
                    </div>
                    <div class="product-sales-kpis">
                      <div class="product-sales-kpi">
                        <span>Lacznie zeszlo</span>
                        <strong>{$productSalesHistory.total_quantity|default:0|escape} szt.</strong>
                      </div>
                      <div class="product-sales-kpi">
                        <span>Dni ze sprzedaza</span>
                        <strong>{$productSalesHistory.days_count|default:0|escape}</strong>
                      </div>
                      <div class="product-sales-kpi">
                        <span>Ostatnia sprzedaz</span>
                        <strong>{if $productSalesHistory.last_sale_date|default:''}{$productSalesHistory.last_sale_date|escape}{else}Brak{/if}</strong>
                      </div>
                      <div class="product-sales-kpi">
                        <span>Srednio / dzien</span>
                        <strong>{$productSalesHistory.average_per_day|default:0|escape} szt.</strong>
                      </div>
                    </div>
                  </div>

                  {if $productSalesHistory.daily|default:[]}
                    <div class="row g-3">
                      <div class="col-lg-7">
                        <div class="product-section-box">
                          <div class="product-section-title">
                            <div>
                              <h5><i class="bi bi-calendar3 me-2"></i>Podsumowanie dzienne</h5>
                              <p>Ile sztuk tego produktu zeszlo kazdego dnia.</p>
                            </div>
                            {if $productSalesHistory.peak_day|default:[]}
                              <span class="product-section-chip"><i class="bi bi-trophy"></i>Top: {$productSalesHistory.peak_day.date|escape}, {$productSalesHistory.peak_day.quantity|escape} szt.</span>
                            {/if}
                          </div>
                          <div class="table-responsive">
                            <table class="table table-sm align-middle product-sales-table mb-0">
                              <thead>
                                <tr>
                                  <th>Dzien</th>
                                  <th class="text-end">Szt.</th>
                                  <th class="text-end">Zamowienia</th>
                                  <th>Sygnatury</th>
                                </tr>
                              </thead>
                              <tbody>
                                {foreach $productSalesHistory.daily as $salesDay}
                                  <tr>
                                    <td class="fw-semibold">{$salesDay.date|escape}</td>
                                    <td class="text-end"><span class="product-sales-qty">{$salesDay.quantity|escape}</span></td>
                                    <td class="text-end">{$salesDay.orders_count|escape}</td>
                                    <td class="small text-secondary">
                                      {if $salesDay.signatures_preview|default:''}
                                        {$salesDay.signatures_preview|escape}{if $salesDay.more_signatures_count|default:0 > 0} +{$salesDay.more_signatures_count|escape}{/if}
                                      {else}
                                        -
                                      {/if}
                                    </td>
                                  </tr>
                                {/foreach}
                              </tbody>
                            </table>
                          </div>
                        </div>
                      </div>
                      <div class="col-lg-5">
                        <div class="product-section-box soft">
                          <div class="product-section-title">
                            <div>
                              <h5><i class="bi bi-receipt-cutoff me-2"></i>Ostatnie zejscia</h5>
                              <p>Najnowsze odjecia z zamowien Sellasist.</p>
                            </div>
                            <span class="product-section-chip"><i class="bi bi-list-check"></i>{$productSalesHistory.events_count|default:0|escape} wpisow</span>
                          </div>
                          {foreach $productSalesHistory.events as $salesEvent}
                            <div class="product-sales-event">
                              <div class="d-flex justify-content-between gap-2 flex-wrap mb-1">
                                <strong>{$salesEvent.created_at|escape}</strong>
                                <span class="badge text-bg-success">{$salesEvent.quantity|escape} szt.</span>
                              </div>
                              <div class="small text-secondary mb-1">
                                Zamowienie: {if $salesEvent.order_id|default:''}#{$salesEvent.order_id|escape}{else}-{/if}
                                {if $salesEvent.signature|default:''} | sygnatura: {$salesEvent.signature|escape}{/if}
                              </div>
                              {if $salesEvent.source_name|default:''}
                                <div class="small">{$salesEvent.source_name|escape}</div>
                              {/if}
                            </div>
                          {/foreach}
                        </div>
                      </div>
                    </div>
                  {else}
                    <div class="product-section-box text-center py-5">
                      <div class="mb-3"><i class="bi bi-graph-up-arrow fs-1 text-secondary"></i></div>
                      <h5>Brak sprzedazy w historii Sellasist</h5>
                      <p class="text-secondary mb-0">Gdy Sellasist odejmie stan magazynowy dla tego produktu, pojawi sie tutaj dzienne podsumowanie.</p>
                    </div>
                  {/if}
                </div>
                {/if}

                <div class="product-tab-panel" data-product-tab-panel="marketplace">
                <div class="product-section-box">
                  <div class="product-section-title">
                    <div>
                      <h5><i class="bi bi-shop me-2"></i>Parametry Allegro</h5>
                      <p>Sekcja pomocnicza do wystawiania i eksportu. Laduje sie po wyborze kategorii powiazanej z Allegro.</p>
                    </div>
                    <span class="product-section-chip"><i class="bi bi-arrow-repeat"></i>Dynamiczne</span>
                  </div>
                  <div id="allegro-parameters-info" class="small text-secondary mb-3">Wybierz kategorie powiazana z Allegro, aby zaladowac parametry.</div>
                  <div class="product-section-box soft mb-3">
                    <div class="row g-2">
                      <div class="col-md-5">
                        <label class="form-label">Szukaj produktu do kopiowania</label>
                        <input type="text" id="copy-product-search" class="form-control form-control-sm" placeholder="Min. 2 znaki">
                      </div>
                      <div class="col-md-5">
                        <label class="form-label">Wyniki</label>
                        <select id="copy-product-select" class="form-select form-select-sm">
                          <option value="">Wpisz fraze, aby wyszukac produkt...</option>
                        </select>
                      </div>
                      <div class="col-md-2 d-grid">
                        <label class="form-label d-none d-md-block">&nbsp;</label>
                        <button type="button" id="copy-product-button" class="btn btn-outline-secondary btn-sm">Kopiuj</button>
                      </div>
                    </div>
                  </div>
                  <input type="hidden" name="allegro_compatibility_list_payload" id="allegroCompatibilityListPayload" value="">
                  <div class="product-section-box soft mb-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                      <div>
                        <div class="fw-semibold">Pasuje do</div>
                        <div class="small text-secondary">Oficjalna sekcja Allegro `compatibilityList` do wskazywania modeli telefonow.</div>
                      </div>
                    </div>
                    <div id="allegro-compatibility-info" class="small text-secondary mb-3">Sprawdzam, czy ta kategoria wspiera sekcje Pasuje do.</div>
                    <div id="allegro-compatibility-container" class="border rounded-4 p-3 bg-light-subtle"></div>
                  </div>
                  <div id="allegro-parameters-container" class="row g-3 border rounded-4 p-3 bg-light-subtle"></div>
                </div>
                </div>

                <div class="product-tab-panel" data-product-tab-panel="empik">
                <div class="product-section-box">
                  <div class="product-section-title">
                    <div>
                      <h5><i class="bi bi-bag me-2"></i>Parametry Empik</h5>
                      <p>Sekcja laduje atrybuty Mirakl/Empik na podstawie przypisanego `empik_category_id` kategorii produktu.</p>
                    </div>
                    <span class="product-section-chip"><i class="bi bi-diagram-2"></i>Z kategorii Empik</span>
                  </div>
                  <div id="empik-parameters-info" class="small text-secondary mb-3">Wybierz kategorie powiazana z Empik, aby zaladowac parametry.</div>
                  <div id="empik-parameters-container"></div>
                </div>
                </div>

                <div class="product-tab-panel" data-product-tab-panel="mediamarkt">
                <div class="product-section-box">
                  <div class="product-section-title">
                    <div>
                      <h5><i class="bi bi-tv me-2"></i>Parametry MediaMarkt</h5>
                      <p>Sekcja laduje atrybuty Mirakl/MediaMarkt na podstawie przypisanego `mediamarkt_category_id` kategorii produktu.</p>
                    </div>
                    <span class="product-section-chip"><i class="bi bi-diagram-2"></i>Z kategorii MediaMarkt</span>
                  </div>
                  <div id="mediamarkt-parameters-info" class="small text-secondary mb-3">Wybierz kategorie powiazana z MediaMarkt, aby zaladowac parametry.</div>
                  <div id="mediamarkt-parameters-container"></div>
                </div>
                </div>


                <div class="product-tab-panel" data-product-tab-panel="temu">
                <div class="product-section-box">
                  <div class="product-section-title">
                    <div>
                      <h5><i class="bi bi-grid-3x3-gap me-2"></i>Parametry Temu</h5>
                      <p>Sekcja laduje pola z JSON-a zapisanego przy kategorii Temu. To dziala juz bez pobierania ofert.</p>
                    </div>
                    <span class="product-section-chip"><i class="bi bi-sliders2"></i>Z kategorii Temu</span>
                  </div>
                  <div id="temu-parameters-info" class="small text-secondary mb-3">Wybierz kategorie powiazana z Temu, aby zaladowac parametry.</div>
                  <div id="temu-parameters-container" class="row g-3 border rounded-4 p-3 bg-light-subtle"></div>
                </div>
                </div>
              </div>

              <div class="col-xl-4">
                <div class="product-summary-box">
                  <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="rounded-circle d-inline-flex justify-content-center align-items-center" style="width:42px;height:42px;background:rgba(31,111,120,.12);color:#1f6f78;">
                      <i class="bi bi-layout-text-window-reverse fs-5"></i>
                    </div>
                    <div>
                      <div class="fw-semibold">Szybki podglad</div>
                      <div class="small text-secondary">Najwazniejsze dane bez przewijania.</div>
                    </div>
                  </div>
                  <div class="product-summary-row"><span class="text-secondary">SKU</span><strong id="summarySku">{if $product.sku|default:''}{$product.sku|escape}{else}-{/if}</strong></div>
                  <div class="product-summary-row"><span class="text-secondary">EAN</span><strong id="summaryEan">{if $product.ean|default:''}{$product.ean|escape}{else}-{/if}</strong></div>
                  <div class="product-summary-row"><span class="text-secondary">Nazwa</span><strong id="summaryName">{if $product.product_name|default:''}{$product.product_name|escape}{else}-{/if}</strong></div>
                  <div class="product-summary-row"><span class="text-secondary">Kategoria</span><strong id="summaryCategory">{if $product.category_id|default:''}{foreach $categories as $category}{if $product.category_id == $category.id}{$category.name|escape}{/if}{/foreach}{else}-{/if}</strong></div>
                  <div class="product-summary-row"><span class="text-secondary">Stan</span><strong id="summaryQuantity">{$product.quantity|default:0|escape}</strong></div>
                  <div class="product-summary-row"><span class="text-secondary">Cena brutto</span><strong id="summaryPrice">{$product.price_gross|default:'0.00'|escape}</strong></div>
                  <div class="d-grid gap-2 mt-4">
                    <button type="submit" name="save_action" value="return" class="btn btn-primary btn-lg"><i class="bi bi-floppy me-2"></i>Zapisz i wroc</button>
                    <button type="submit" name="save_action" value="stay" class="btn btn-outline-primary"><i class="bi bi-floppy2 me-2"></i>Zapisz i zostan</button>
                    <a href="{$returnUrl|default:'./index.php?controller=products&action=index'|escape}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Wroc do listy</a>
                  </div>
                </div>

                {if isset($product.id)}
                  <div class="product-summary-box mt-3">
                    <div class="d-flex align-items-center gap-2 mb-3">
                      <div class="rounded-circle d-inline-flex justify-content-center align-items-center" style="width:42px;height:42px;background:rgba(21,128,61,.12);color:#15803d;">
                        <i class="bi bi-clock-history fs-5"></i>
                      </div>
                      <div>
                        <div class="fw-semibold">Historia zmian</div>
                        <div class="small text-secondary">Ostatnie 10 zapisanych zmian tego produktu.</div>
                      </div>
                    </div>

                    {if $productHistory}
                      <div class="d-grid gap-3">
                        {foreach $productHistory as $historyEntry}
                          <div class="border rounded-4 p-3 bg-white">
                            <div class="d-flex justify-content-between gap-2 flex-wrap mb-2">
                              <span class="badge text-bg-light border">{$historyEntry.action_label|escape}</span>
                              <span class="small text-secondary">{$historyEntry.created_at|escape}</span>
                            </div>
                            <div class="small fw-semibold mb-1">{$historyEntry.actor_display|escape}</div>
                            <div class="small text-secondary mb-2">{$historyEntry.summary|default:'Zapisano zmiany.'|escape}</div>
                            {if $historyEntry.changes}
                              <div class="small">
                                {foreach $historyEntry.changes as $change}
                                  <div class="mb-1"><strong>{$change.label|escape}:</strong> {$change.before|escape} <span class="text-secondary">-></span> {$change.after|escape}</div>
                                {/foreach}
                              </div>
                            {/if}
                          </div>
                        {/foreach}
                      </div>
                    {else}
                      <div class="small text-secondary">Ten produkt nie ma jeszcze zapisanej historii zmian.</div>
                    {/if}
                  </div>
                {/if}
              </div>
            </div>
          </div>
          
        </form>
      </div>
      </div>
    </div>
  </div>
</main>
<script>
  (function () {
    var vatInput = document.getElementById('vat_rate');
    var netInput = document.getElementById('price_net');
    var grossInput = document.getElementById('price_gross');
    var quantityInput = document.getElementById('quantity');
    var categoryInput = document.getElementById('category_id');
    var skuInput = document.getElementById('sku');
    var eanInput = document.getElementById('ean');
    var allegroInfo = document.getElementById('allegro-parameters-info');
    var allegroContainer = document.getElementById('allegro-parameters-container');
    var allegroCompatibilityInfo = document.getElementById('allegro-compatibility-info');
    var allegroCompatibilityContainer = document.getElementById('allegro-compatibility-container');
    var allegroCompatibilityListPayload = document.getElementById('allegroCompatibilityListPayload');
    var empikInfo = document.getElementById('empik-parameters-info');
    var empikContainer = document.getElementById('empik-parameters-container');
    var mediamarktInfo = document.getElementById('mediamarkt-parameters-info');
    var mediamarktContainer = document.getElementById('mediamarkt-parameters-container');
    var temuInfo = document.getElementById('temu-parameters-info');
    var temuContainer = document.getElementById('temu-parameters-container');
    var copyProductSearch = document.getElementById('copy-product-search');
    var copyProductSelect = document.getElementById('copy-product-select');
    var copyProductButton = document.getElementById('copy-product-button');
    var relatedProductSearch = document.getElementById('related-product-search');
    var relatedProductSelect = document.getElementById('related-product-select');
    var addRelatedProductButton = document.getElementById('add-related-product-button');
    var relatedProductsContainer = document.getElementById('related-products-container');
    var noRelatedProducts = document.getElementById('noRelatedProducts');
    var derivedProductSearch = document.getElementById('derived-product-search');
    var derivedProductSelect = document.getElementById('derived-product-select');
    var addDerivedProductButton = document.getElementById('add-derived-product-button');
    var derivedProductsContainer = document.getElementById('derived-products-container');
    var noDerivedProducts = document.getElementById('noDerivedProducts');
    var addCustomFieldRowButton = document.getElementById('addCustomFieldRow');
    var newCustomFieldsContainer = document.getElementById('newCustomFieldsContainer');
    var existingCustomFieldSelect = document.getElementById('existingCustomFieldSelect');
    var addExistingCustomFieldBtn = document.getElementById('addExistingCustomFieldBtn');
    var assignedCustomFieldsContainer = document.getElementById('assignedCustomFieldsContainer');
    var noAssignedCustomFields = document.getElementById('noAssignedCustomFields');
    var deleteCustomFieldDefinitionSelect = document.getElementById('deleteCustomFieldDefinitionSelect');
    var deleteCustomFieldDefinitionBtn = document.getElementById('deleteCustomFieldDefinitionBtn');
    var summarySku = document.getElementById('summarySku');
    var summaryEan = document.getElementById('summaryEan');
    var summaryName = document.getElementById('summaryName');
    var summaryCategory = document.getElementById('summaryCategory');
    var summaryQuantity = document.getElementById('summaryQuantity');
    var summaryPrice = document.getElementById('summaryPrice');
    var overviewSku = document.getElementById('overviewSku');
    var overviewQuantity = document.getElementById('overviewQuantity');
    var overviewPrice = document.getElementById('overviewPrice');
    var heroSku = document.getElementById('heroSku');
    var tabButtons = document.querySelectorAll('[data-product-tab-trigger]');
    var tabPanels = document.querySelectorAll('[data-product-tab-panel]');
    var productForm = document.getElementById('product-form');
    var hasAssignedSku = {if isset($product.id) and $product.sku|default:'' neq ''}true{else}false{/if};
    var productId = '{if isset($product.id)}{$product.id}{/if}';
    var existingAllegroValues = {$allegroValuesJson|default:'{}'};
    var existingAllegroCompatibilityList = {$allegroCompatibilityListJson|default:'[]' nofilter};
    var existingEmpikValues = {$empikValuesJson|default:'{}'};
    var existingEmpikHiddenKeys = {$empikHiddenKeysJson|default:'[]' nofilter};
    var existingMediaMarktValues = {$mediamarktValuesJson|default:'{}'};
    var existingMediaMarktHiddenKeys = {$mediamarktHiddenKeysJson|default:'[]' nofilter};
    var existingTemuValues = {$temuValuesJson|default:'{}'};
    var currentAllegroItems = [];
    var currentAllegroCompatibilitySupport = null;
    var currentAllegroCompatibilityList = Array.isArray(existingAllegroCompatibilityList.items) ? existingAllegroCompatibilityList.items.slice() : [];
    var currentEmpikItems = [];
    var currentEmpikHiddenKeys = Array.isArray(existingEmpikHiddenKeys) ? existingEmpikHiddenKeys.slice() : [];
    var currentMediaMarktItems = [];
    var currentMediaMarktHiddenKeys = Array.isArray(existingMediaMarktHiddenKeys) ? existingMediaMarktHiddenKeys.slice() : [];
    var currentTemuItems = [];

    function toNumber(value) {
      value = String(value || '').replace(',', '.');
      var parsed = parseFloat(value);
      return isNaN(parsed) ? 0 : parsed;
    }

    function formatNumber(value) {
      return (Math.round(value * 100) / 100).toFixed(2);
    }

    function syncSummary() {
      if (summarySku && skuInput) {
        summarySku.textContent = skuInput.value && skuInput.value.trim() !== '' ? skuInput.value : '-';
      }
      if (summaryEan && eanInput) {
        summaryEan.textContent = eanInput.value && eanInput.value.trim() !== '' ? eanInput.value : '-';
      }
      if (overviewSku && skuInput) {
        overviewSku.textContent = skuInput.value && skuInput.value.trim() !== '' ? skuInput.value : 'Automatyczne';
      }
      if (heroSku && skuInput) {
        heroSku.textContent = skuInput.value && skuInput.value.trim() !== '' ? skuInput.value : 'wygeneruje sie automatycznie';
      }
      if (summaryName) {
        var productNameInput = document.getElementById('product_name');
        summaryName.textContent = productNameInput && productNameInput.value.trim() !== '' ? productNameInput.value : '-';
      }
      if (summaryQuantity && quantityInput) {
        summaryQuantity.textContent = quantityInput.value && quantityInput.value !== '' ? quantityInput.value : '0';
      }
      if (overviewQuantity && quantityInput) {
        overviewQuantity.textContent = quantityInput.value && quantityInput.value !== '' ? quantityInput.value : '0';
      }
      if (summaryPrice && grossInput) {
        summaryPrice.textContent = grossInput.value && grossInput.value !== '' ? grossInput.value : '0.00';
      }
      if (overviewPrice && grossInput) {
        overviewPrice.textContent = grossInput.value && grossInput.value !== '' ? grossInput.value : '0.00';
      }
      if (summaryCategory && categoryInput) {
        var selectedOption = categoryInput.options[categoryInput.selectedIndex];
        summaryCategory.textContent = selectedOption && selectedOption.value !== '' ? selectedOption.text : '-';
      }
    }

    function activateProductTab(tabName) {
      if (!tabName) {
        return;
      }

      for (var i = 0; i < tabButtons.length; i++) {
        var isActiveButton = tabButtons[i].getAttribute('data-product-tab-trigger') === tabName;
        tabButtons[i].classList.toggle('active', isActiveButton);
        tabButtons[i].setAttribute('aria-pressed', isActiveButton ? 'true' : 'false');
      }

      for (var j = 0; j < tabPanels.length; j++) {
        var isActivePanel = tabPanels[j].getAttribute('data-product-tab-panel') === tabName;
        tabPanels[j].classList.toggle('active', isActivePanel);
      }
    }

    function fromNet() {
      if (!vatInput || !netInput || !grossInput) {
        return;
      }
      var vat = toNumber(vatInput.value);
      var net = toNumber(netInput.value);
      grossInput.value = formatNumber(net * (1 + vat / 100));
    }

    function fromGross() {
      if (!vatInput || !netInput || !grossInput) {
        return;
      }
      var vat = toNumber(vatInput.value);
      var gross = toNumber(grossInput.value);
      var divider = 1 + vat / 100;
      netInput.value = formatNumber(divider > 0 ? gross / divider : gross);
    }

    function escapeHtml(value) {
      return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function fallbackSku() {
      if (!categoryInput || !skuInput || hasAssignedSku) {
        return;
      }
      var option = categoryInput.options[categoryInput.selectedIndex];
      var prefix = option ? String(option.getAttribute('data-sku-prefix') || 'PRD') : 'PRD';
      var now = new Date();
      var y = String(now.getFullYear()).slice(-2);
      var m = String(now.getMonth() + 1).padStart(2, '0');
      var d = String(now.getDate()).padStart(2, '0');
      var h = String(now.getHours()).padStart(2, '0');
      var min = String(now.getMinutes()).padStart(2, '0');
      skuInput.value = prefix + '-' + y + m + d + h + min;
    }

    function refreshSku() {
      if (!categoryInput || !skuInput || hasAssignedSku) {
        return;
      }

      var categoryId = categoryInput.value;
      if (!categoryId) {
        skuInput.value = '';
        return;
      }

      var url = '{$baseUrl|escape:"javascript"}?controller=products&action=nextsku&category_id=' + encodeURIComponent(categoryId);

      if (!window.fetch) {
        fallbackSku();
        return;
      }

      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (response) {
          if (!response.ok) {
            throw new Error('bad response');
          }
          return response.json();
        })
        .then(function (data) {
          if (data && data.sku) {
            skuInput.value = data.sku;
          } else {
            fallbackSku();
          }
        })
        .catch(function () {
          fallbackSku();
        });
    }

    function normalizeSelectedArray(value) {
      if (Array.isArray(value)) {
        return value;
      }
      if (value === null || typeof value === 'undefined' || value === '') {
        return [];
      }
      return [value];
    }

    function isValueSelected(value, selectedValues) {
      if (!Array.isArray(selectedValues)) {
        return false;
      }
      var normalized = String(value || '');
      for (var i = 0; i < selectedValues.length; i++) {
        if (String(selectedValues[i]) === normalized) {
          return true;
        }
      }
      return false;
    }

    function bindCustomFieldRowRemove(scope) {
      var root = scope || document;
      var buttons = root.querySelectorAll('.remove-custom-field-row');
      for (var i = 0; i < buttons.length; i++) {
        if (buttons[i].getAttribute('data-bound') === '1') {
          continue;
        }

        buttons[i].setAttribute('data-bound', '1');
        buttons[i].addEventListener('click', function () {
          var row = this.closest('.new-custom-field-row');
          if (!row || !newCustomFieldsContainer) {
            return;
          }

          if (newCustomFieldsContainer.querySelectorAll('.new-custom-field-row').length <= 1) {
            var inputs = row.querySelectorAll('input');
            for (var j = 0; j < inputs.length; j++) {
              inputs[j].value = '';
            }
            return;
          }

          row.remove();
        });
      }
    }

    function bindRelatedProductRemove(scope) {
      var root = scope || document;
      var buttons = root.querySelectorAll('.remove-related-product');
      for (var i = 0; i < buttons.length; i++) {
        if (buttons[i].getAttribute('data-bound') === '1') {
          continue;
        }

        buttons[i].setAttribute('data-bound', '1');
        buttons[i].addEventListener('click', function () {
          var card = this.closest('.related-product-card');
          if (!card) {
            return;
          }

          var productIdValue = String(card.getAttribute('data-product-id') || '');
          card.remove();

          if (relatedProductSelect && productIdValue !== '') {
            var option = relatedProductSelect.querySelector('option[value="' + productIdValue.replace(/"/g, '\\"') + '"]');
            if (option) {
              option.hidden = false;
              option.disabled = false;
            }
          }

          if (relatedProductsContainer && !relatedProductsContainer.querySelector('.related-product-card') && noRelatedProducts) {
            noRelatedProducts.style.display = '';
          }
        });
      }
    }

    function hideAssignedRelatedProductOptions() {
      if (!relatedProductSelect || !relatedProductsContainer) {
        return;
      }

      var cards = relatedProductsContainer.querySelectorAll('.related-product-card');
      for (var i = 0; i < cards.length; i++) {
        var productIdValue = String(cards[i].getAttribute('data-product-id') || '');
        if (productIdValue === '') {
          continue;
        }

        var option = relatedProductSelect.querySelector('option[value="' + productIdValue.replace(/"/g, '\\"') + '"]');
        if (option) {
          option.hidden = true;
          option.disabled = true;
        }
      }
    }


    function bindDerivedProductRemove(scope) {
      var root = scope || document;
      var buttons = root.querySelectorAll('.remove-derived-product');
      for (var i = 0; i < buttons.length; i++) {
        if (buttons[i].getAttribute('data-bound') === '1') {
          continue;
        }

        buttons[i].setAttribute('data-bound', '1');
        buttons[i].addEventListener('click', function () {
          var card = this.closest('.derived-product-card');
          if (!card) {
            return;
          }

          var productIdValue = String(card.getAttribute('data-product-id') || '');
          card.remove();

          if (derivedProductSelect && productIdValue !== '') {
            var option = derivedProductSelect.querySelector('option[value="' + productIdValue.replace(/"/g, '\\"') + '"]');
            if (option) {
              option.hidden = false;
              option.disabled = false;
            }
          }

          if (derivedProductsContainer && !derivedProductsContainer.querySelector('.derived-product-card') && noDerivedProducts) {
            noDerivedProducts.style.display = '';
          }
        });
      }
    }

    function hideAssignedDerivedProductOptions() {
      if (!derivedProductSelect || !derivedProductsContainer) {
        return;
      }

      var cards = derivedProductsContainer.querySelectorAll('.derived-product-card');
      for (var i = 0; i < cards.length; i++) {
        var productIdValue = String(cards[i].getAttribute('data-product-id') || '');
        if (productIdValue === '') {
          continue;
        }

        var option = derivedProductSelect.querySelector('option[value="' + productIdValue.replace(/"/g, '\\"') + '"]');
        if (option) {
          option.hidden = true;
          option.disabled = true;
        }
      }
    }
    function bindAssignedCustomFieldRemove(scope) {
      var root = scope || document;
      var buttons = root.querySelectorAll('.remove-assigned-custom-field');
      for (var i = 0; i < buttons.length; i++) {
        if (buttons[i].getAttribute('data-bound') === '1') {
          continue;
        }

        buttons[i].setAttribute('data-bound', '1');
        buttons[i].addEventListener('click', function () {
          var card = this.closest('.assigned-custom-field-card');
          if (!card) {
            return;
          }

          var definitionId = String(card.getAttribute('data-definition-id') || '');
          card.remove();

          if (existingCustomFieldSelect && definitionId !== '') {
            var option = existingCustomFieldSelect.querySelector('option[value="' + definitionId.replace(/"/g, '\\"') + '"]');
            if (option) {
              option.hidden = false;
              option.disabled = false;
            }
          }

          if (assignedCustomFieldsContainer && !assignedCustomFieldsContainer.querySelector('.assigned-custom-field-card') && noAssignedCustomFields) {
            noAssignedCustomFields.style.display = '';
          }
        });
      }
    }

    function clearSelectedRelatedProducts() {
      if (!relatedProductsContainer) {
        return;
      }

      var cards = relatedProductsContainer.querySelectorAll('.related-product-card');
      for (var i = 0; i < cards.length; i++) {
        cards[i].remove();
      }

      if (noRelatedProducts) {
        noRelatedProducts.style.display = '';
      }

      if (relatedProductSelect) {
        var options = relatedProductSelect.querySelectorAll('option');
        for (var j = 0; j < options.length; j++) {
          options[j].hidden = false;
          options[j].disabled = false;
        }
      }
    }

    function clearSelectedDerivedProducts() {
      if (!derivedProductsContainer) {
        return;
      }

      var cards = derivedProductsContainer.querySelectorAll('.derived-product-card');
      for (var i = 0; i < cards.length; i++) {
        cards[i].remove();
      }

      if (noDerivedProducts) {
        noDerivedProducts.style.display = '';
      }

      if (derivedProductSelect) {
        var options = derivedProductSelect.querySelectorAll('option');
        for (var j = 0; j < options.length; j++) {
          options[j].hidden = false;
          options[j].disabled = false;
        }
      }
    }

    function hideAssignedCustomFieldOptions() {
      if (!existingCustomFieldSelect || !assignedCustomFieldsContainer) {
        return;
      }

      var cards = assignedCustomFieldsContainer.querySelectorAll('.assigned-custom-field-card');
      for (var i = 0; i < cards.length; i++) {
        var definitionId = String(cards[i].getAttribute('data-definition-id') || '');
        if (definitionId === '') {
          continue;
        }

        var option = existingCustomFieldSelect.querySelector('option[value="' + definitionId.replace(/"/g, '\\"') + '"]');
        if (option) {
          option.hidden = true;
          option.disabled = true;
        }
      }
    }

    function normalizeParameterName(value) {
      return String(value || '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
    }

    function isFitsToParameter(item) {
      if (!item || typeof item !== 'object') {
        return false;
      }

      var normalizedName = normalizeParameterName(item.name || '');
      return normalizedName.indexOf('pasuje do') !== -1;
    }

    function normalizeMarketplaceTokenText(value) {
      return String(value || '')
        .toLowerCase()
        .replace(/[ąćęłńóśźż]/g, function (letter) {
          return {
            'ą': 'a',
            'ć': 'c',
            'ę': 'e',
            'ł': 'l',
            'ń': 'n',
            'ó': 'o',
            'ś': 's',
            'ź': 'z',
            'ż': 'z'
          }[letter] || letter;
        })
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
    }

    function isEmpikFreeTextIdentityParameter(item) {
      if (!item || typeof item !== 'object') {
        return false;
      }

      var normalized = normalizeMarketplaceTokenText(String(item.id || '') + ' ' + String(item.name || ''));
      var tokens = ['ean', 'gtin', 'isbn', 'issn', 'upc', 'sku', 'mpn'];
      for (var i = 0; i < tokens.length; i++) {
        var pattern = new RegExp('(^|[^a-z0-9])' + tokens[i] + '([^a-z0-9]|$)');
        if (pattern.test(normalized)) {
          return true;
        }
      }

      return normalized.indexOf('kod producenta') !== -1
        || normalized.indexOf('numer katalogowy') !== -1
        || normalized.indexOf('kod kreskowy') !== -1;
    }

    function empikDisplayNameKey(value) {
      return normalizeMarketplaceTokenText(String(value || '').replace(/\s*\(\s*\d+\s*\)\s*$/, ''));
    }

    function currentEmpikHiddenKeyMap() {
      var map = {};
      for (var i = 0; i < currentEmpikHiddenKeys.length; i++) {
        var key = String(currentEmpikHiddenKeys[i] || '').trim();
        if (key !== '') {
          map[key] = true;
        }
      }
      return map;
    }

    function isEmpikParameterHidden(item) {
      if (!item || typeof item !== 'object') {
        return false;
      }

      var key = empikDisplayNameKey(item.name || item.id || '');
      return key !== '' && !!currentEmpikHiddenKeyMap()[key];
    }

    function isMediaMarktFreeTextIdentityParameter(item) {
      if (!item || typeof item !== 'object') {
        return false;
      }

      var normalized = normalizeMarketplaceTokenText(String(item.id || '') + ' ' + String(item.name || ''));
      var tokens = ['ean', 'gtin', 'isbn', 'issn', 'upc', 'sku', 'mpn'];
      for (var i = 0; i < tokens.length; i++) {
        var pattern = new RegExp('(^|[^a-z0-9])' + tokens[i] + '([^a-z0-9]|$)');
        if (pattern.test(normalized)) {
          return true;
        }
      }

      return normalized.indexOf('kod producenta') !== -1
        || normalized.indexOf('numer katalogowy') !== -1
        || normalized.indexOf('kod kreskowy') !== -1;
    }

    function mediamarktDisplayNameKey(value) {
      return normalizeMarketplaceTokenText(String(value || '').replace(/\s*\(\s*\d+\s*\)\s*$/, ''));
    }

    function currentMediaMarktHiddenKeyMap() {
      var map = {};
      for (var i = 0; i < currentMediaMarktHiddenKeys.length; i++) {
        var key = String(currentMediaMarktHiddenKeys[i] || '').trim();
        if (key !== '') {
          map[key] = true;
        }
      }
      return map;
    }

    function isMediaMarktParameterHidden(item) {
      if (!item || typeof item !== 'object') {
        return false;
      }

      var key = mediamarktDisplayNameKey(item.name || item.id || '');
      return key !== '' && !!currentMediaMarktHiddenKeyMap()[key];
    }


    function renderMarketplaceParameterFields(containerNode, infoNode, items, values, inputName, emptyLabel, loadedLabel, singleDictionaryMode) {
      if (!containerNode) {
        return;
      }

      if (!items || !items.length) {
        containerNode.innerHTML = '';
        if (infoNode) {
          infoNode.textContent = emptyLabel;
        }
        return;
      }

      var html = '';
      for (var i = 0; i < items.length; i++) {
        var item = items[i] || {};
        var pid = String(item.id || '');
        var pName = String(item.name || 'Parametr');
        var pType = String(item.type || 'string');
        var required = !!item.required;
        var restrictions = item.restrictions && typeof item.restrictions === 'object' ? item.restrictions : {};
        var multiple = !!item.multiple || pType === 'multidictionary' || restrictions.multipleChoices === true || restrictions.multipleChoices === 1;
        var dict = Array.isArray(item.dictionary) ? item.dictionary : [];
        var dependsOnParameterId = String(item.depends_on_parameter_id || (item.options && item.options.dependsOnParameterId) || '');
        var optionLookup = !!item.option_lookup;
        var fitsToMode = multiple && dict.length && isFitsToParameter(item);
        var value = values && typeof values === 'object' ? values[pid] : '';
        var selectedValues = normalizeSelectedArray(value);

        var paramSearch = (pName + ' ' + pid + ' ' + pType).toLowerCase();
        html += '<div class="col-md-6 js-param-card" data-param-id="' + escapeHtml(pid) + '" data-param-type="' + escapeHtml(pType) + '" data-input-name="' + escapeHtml(inputName) + '" data-param-search="' + escapeHtml(paramSearch) + '" data-depends-on-parameter-id="' + escapeHtml(dependsOnParameterId) + '">';
        var labelClass = required ? 'form-label text-danger fw-bold' : 'form-label';
        html += '<label class="' + labelClass + '">' + escapeHtml(pName) + (required ? ' (wymagany)' : '') + '</label>';

        if (dict.length || (singleDictionaryMode === 'autocomplete' && optionLookup && !multiple)) {
          if (multiple) {
            html += '<div class="border rounded p-2">';
            if (fitsToMode) {
              html += '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">';
              html += '<div class="small fw-semibold text-dark js-fits-to-summary">Wybrano 0 z ' + dict.length + ' modeli</div>';
              html += '<div class="d-flex flex-wrap gap-2">';
              html += '<button type="button" class="btn btn-outline-secondary btn-sm js-fits-to-select-visible">Zaznacz widoczne</button>';
              html += '<button type="button" class="btn btn-outline-secondary btn-sm js-fits-to-clear">Wyczysc</button>';
              html += '</div>';
              html += '</div>';
              html += '<input type="text" class="form-control form-control-sm mb-2 js-param-option-filter" placeholder="Szukaj modelu telefonu..." data-param-id="' + escapeHtml(pid) + '">';
            } else {
              html += '<input type="text" class="form-control form-control-sm mb-2 js-param-option-filter" placeholder="Filtruj opcje..." data-param-id="' + escapeHtml(pid) + '">';
            }
            html += '<div class="js-param-options" style="max-height: 160px; overflow-y: auto; overflow-x: hidden; padding-right: 4px;">';
            for (var d = 0; d < dict.length; d++) {
              var option = dict[d] || {};
              var optId = String(option.id || '');
              var checked = isValueSelected(optId, selectedValues) ? ' checked' : '';
              var inputId = 'allegro_' + escapeHtml(pid) + '_' + d;
              var optionLabel = String(option.value || optId);
              var dependsOnValueIds = Array.isArray(option.depends_on_value_ids) ? option.depends_on_value_ids : [];
              html += '<div class="form-check js-param-option" data-option-label="' + escapeHtml(optionLabel.toLowerCase()) + '" data-depends-on-value-ids="' + escapeHtml(JSON.stringify(dependsOnValueIds)) + '">';
              html += '<input class="form-check-input" type="checkbox" id="' + inputId + '" name="' + inputName + '[' + escapeHtml(pid) + '][]" value="' + escapeHtml(optId) + '"' + checked + '>';
              html += '<label class="form-check-label" for="' + inputId + '">' + escapeHtml(optionLabel) + '</label>';
              html += '</div>';
            }
            html += '</div>';
            if (fitsToMode) {
              html += '<div class="small text-secondary mt-2 js-fits-to-selected-list"></div>';
            }
            html += '</div>';
            html += '<div class="form-text">' + (fitsToMode ? 'Lista modeli telefonow z Allegro. Mozesz wyszukiwac i zaznaczyc wiele pozycji.' : 'Wielokrotny wybor z listy.') + '</div>';
          } else {
            var singleValue = value === null || typeof value === 'undefined' ? '' : String(value);
            if (singleDictionaryMode === 'autocomplete') {
              html += '<select class="form-select form-select-sm empik-remote-select js-param-remote-select"'
                + ' data-attribute-id="' + escapeHtml(pid) + '"'
                + ' data-current-value="' + escapeHtml(singleValue) + '"'
                + ' name="' + inputName + '[' + escapeHtml(pid) + ']">'
                + '<option value="">Ladowanie wariantow Empik...</option>'
                + '</select>';
              html += '<div class="form-text js-param-autocomplete-status">Ladowanie pierwszych 10 wariantow z Empik...</div>';
            } else {
              html += '<input type="text" class="form-control form-control-sm mb-2 js-param-option-filter" placeholder="Filtruj opcje..." data-param-id="' + escapeHtml(pid) + '">';
              html += '<select class="form-select form-select-sm js-param-select" data-param-id="' + escapeHtml(pid) + '" name="' + inputName + '[' + escapeHtml(pid) + ']" style="max-height: 38px;">';
              html += '<option value="">Wybierz</option>';
              for (var s = 0; s < dict.length; s++) {
                var singleOption = dict[s] || {};
                var singleId = String(singleOption.id || '');
                var selected = singleValue === singleId ? ' selected' : '';
                var optionLabel = String(singleOption.value || singleId);
                var singleDependsOnValueIds = Array.isArray(singleOption.depends_on_value_ids) ? singleOption.depends_on_value_ids : [];
                html += '<option data-option-label="' + escapeHtml(optionLabel.toLowerCase()) + '" data-depends-on-value-ids="' + escapeHtml(JSON.stringify(singleDependsOnValueIds)) + '" value="' + escapeHtml(singleId) + '"' + selected + '>' + escapeHtml(optionLabel) + '</option>';
              }
              html += '</select>';
            }
          }
        } else if (pType === 'boolean' || pType === 'bool') {
          var boolValue = value === true || value === 1 || value === '1' || value === 'true' ? 'true' : (value === false || value === 0 || value === '0' || value === 'false' ? 'false' : '');
          html += '<select class="form-select form-select-sm" name="' + inputName + '[' + escapeHtml(pid) + ']">';
          html += '<option value="">-- Wybierz --</option>';
          html += '<option value="true"' + (boolValue === 'true' ? ' selected' : '') + '>Tak</option>';
          html += '<option value="false"' + (boolValue === 'false' ? ' selected' : '') + '>Nie</option>';
          html += '</select>';
        } else if (multiple) {
          var multilineValue = '';
          if (Array.isArray(value)) {
            multilineValue = value.join('\n');
          } else if (value !== null && typeof value !== 'undefined') {
            multilineValue = String(value);
          }
          html += '<textarea class="form-control" name="' + inputName + '[' + escapeHtml(pid) + ']" rows="3">' + escapeHtml(multilineValue) + '</textarea>';
          html += '<div class="form-text">Wpisz wiele wartosci, jedna w linii.</div>';
        } else if (pType === 'integer' || pType === 'float' || pType === 'number') {
          var numValue = value === null || typeof value === 'undefined' ? '' : String(value);
          html += '<input type="number" step="any" class="form-control" name="' + inputName + '[' + escapeHtml(pid) + ']" value="' + escapeHtml(numValue) + '">';
        } else {
          var textValue = value === null || typeof value === 'undefined' ? '' : String(value);
          html += '<input type="text" class="form-control" name="' + inputName + '[' + escapeHtml(pid) + ']" value="' + escapeHtml(textValue) + '">';
        }

        html += '<div class="small text-secondary mt-1">ID: ' + escapeHtml(pid) + ' | typ: ' + escapeHtml(pType) + (multiple ? ' | multiple' : '') + '</div>';
        if (dependsOnParameterId !== '') {
          html += '<div class="small text-primary mt-1 js-param-dependency-status">Lista zależna od parametru ID: ' + escapeHtml(dependsOnParameterId) + '</div>';
        }
        if (inputName === 'allegro_parameters') {
          html += '<div class="small text-secondary mt-1 js-param-import-current">CSV/import product.allegro_parameter.' + escapeHtml(pid) + ': <span class="text-muted">brak</span></div>';
        }
        html += '</div>';
      }

      containerNode.innerHTML = html;
      bindOptionFilters(containerNode);
      bindDependentParameterFields(containerNode);
      bindRemoteSelectMappings(containerNode);
      bindFitsToPickers(containerNode);
      bindParameterImportValueRefresh(containerNode);
      if (infoNode) {
        infoNode.textContent = loadedLabel;
      }
    }

    function renderEmpikComponentLikeParameterFields(containerNode, infoNode, items, values, emptyLabel, loadedLabel) {
      if (!containerNode) {
        return;
      }

      if (!items || !items.length) {
        containerNode.innerHTML = '<div class="alert alert-light border mb-0">Brak parametrów Empik do wyświetlenia.</div>';
        if (infoNode) {
          infoNode.textContent = emptyLabel;
        }
        return;
      }

      var selectedOption = categoryInput && categoryInput.options ? categoryInput.options[categoryInput.selectedIndex] : null;
      var empikCategoryId = selectedOption ? String(selectedOption.getAttribute('data-empik-category-id') || '') : '';
      var html = '';
      var hiddenKeyMap = currentEmpikHiddenKeyMap();
      var hiddenCount = 0;
      for (var hiddenIndex = 0; hiddenIndex < items.length; hiddenIndex++) {
        var hiddenItem = items[hiddenIndex] || {};
        var hiddenKey = empikDisplayNameKey(hiddenItem.name || hiddenItem.id || '');
        if (hiddenKey !== '' && hiddenKeyMap[hiddenKey]) {
          hiddenCount++;
        }
      }

      html += '<div class="market-params market-params--empik" data-options-url="{$baseUrl|escape:"javascript"}?controller=products&action=empikparameteroptions&category_id=' + encodeURIComponent(categoryInput ? categoryInput.value : '') + '">';
      html += '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">';
      html += '<div><div class="fw-bold fs-6">Parametry Empik</div><div class="small text-muted">';
      if (empikCategoryId) {
        html += 'Kategoria API: <code>' + escapeHtml(empikCategoryId) + '</code>';
      }
      html += '</div></div>';
      html += '<div class="d-flex flex-wrap align-items-center gap-2 js-empik-header-controls">';
      html += '<div class="small text-muted">' + items.length + ' parametrów</div>';
      if (hiddenCount > 0) {
        html += '<button type="button" class="btn btn-sm btn-outline-secondary js-empik-toggle-hidden" data-show-hidden="0">Pokaż ukryte (' + hiddenCount + ')</button>';
      }
      html += '</div>';
      html += '</div>';
      html += '<div class="mb-3">';
      html += '<input type="text" class="form-control js-market-param-filter" data-target=".js-empik-param-card" placeholder="Szukaj po nazwie lub ID parametru Empik...">';
      html += '</div>';
      html += '<div class="row g-3 mb-4">';

      var seenEmpikNames = {};
      for (var i = 0; i < items.length; i++) {
        var item = items[i] || {};
        var pid = String(item.id || '');
        var pName = String(item.name || 'Parametr');
        var displayNameKey = empikDisplayNameKey(pName);
        if (displayNameKey !== '' && seenEmpikNames[displayNameKey]) {
          continue;
        }
        if (displayNameKey !== '') {
          seenEmpikNames[displayNameKey] = true;
        }
        var pType = String(item.type || 'string').toLowerCase();
        var restrictions = item.restrictions && typeof item.restrictions === 'object' ? item.restrictions : {};
        var multiple = !!item.multiple || pType === 'multidictionary' || restrictions.multipleChoices === true || restrictions.multipleChoices === 1;
        var dict = Array.isArray(item.dictionary) ? item.dictionary : [];
        var optionLookup = !!item.option_lookup;
        if (isEmpikFreeTextIdentityParameter(item)) {
          pType = 'text';
          multiple = false;
          dict = [];
          optionLookup = false;
        }
        var isHidden = displayNameKey !== '' && !!hiddenKeyMap[displayNameKey];
        var rawValue = values && typeof values === 'object' && Object.prototype.hasOwnProperty.call(values, pid) ? values[pid] : '';
        var selectedValues = normalizeSelectedArray(rawValue);
        var storedValue = Array.isArray(rawValue) ? rawValue.join(' | ') : (rawValue === null || typeof rawValue === 'undefined' ? '' : String(rawValue));
        var cardText = (pName + ' ' + pid + ' ' + pType).toLowerCase();

        html += '<div class="col-12 col-xl-6 js-empik-param-card js-param-card' + (isHidden ? ' d-none' : '') + '" data-param-id="' + escapeHtml(pid) + '" data-param-key="' + escapeHtml(displayNameKey) + '" data-param-hidden-global="' + (isHidden ? '1' : '0') + '" data-param-type="' + escapeHtml(pType) + '" data-input-name="empik_parameters" data-filter-text="' + escapeHtml(cardText) + '">';
        html += '<div class="border rounded-3 p-3 h-100 bg-white">';
        html += '<div class="d-flex align-items-start justify-content-between gap-2 mb-2">';
        html += '<label class="form-label fw-semibold mb-0" for="empik_param_' + escapeHtml(pid) + '">' + escapeHtml(pName) + '</label>';
        html += '<div class="d-flex flex-wrap align-items-center justify-content-end gap-2">';
        html += '<span class="badge bg-light text-dark border">ID ' + escapeHtml(pid) + '</span>';
        html += '<button type="button" class="btn btn-sm ' + (isHidden ? 'btn-outline-success' : 'btn-outline-secondary') + ' js-empik-toggle-param-visibility" data-hidden="' + (isHidden ? '1' : '0') + '" data-param-id="' + escapeHtml(pid) + '" data-param-name="' + escapeHtml(pName) + '">' + (isHidden ? 'Przywróć' : 'Ukryj globalnie') + '</button>';
        html += '</div>';
        html += '</div>';

        if (optionLookup) {
          html += '<div class="empik-option-lookup" data-attribute-id="' + escapeHtml(pid) + '" data-multiple="' + (multiple ? '1' : '0') + '">';
          html += '<div class="input-group">';
          html += '<span class="input-group-text"><i class="bi bi-search"></i></span>';
          html += '<input type="search" class="form-control js-empik-option-search" placeholder="Szukaj dostępnych wartości dla: ' + escapeHtml(pName) + '" autocomplete="off">';
          html += '<button type="button" class="btn btn-outline-secondary js-empik-option-clear">Wyczyść</button>';
          html += '</div>';
          html += '<div class="form-text">Po kliknięciu pokazujemy dostępne propozycje. Wpisz tekst, aby zawęzić wyniki tylko dla tego parametru.</div>';
          html += '<div class="list-group mt-2 js-empik-option-results d-none"></div>';
          html += '<div class="mt-2 js-empik-option-selection">';
          if (multiple) {
            selectedValues.forEach(function (selectedValue) {
              html += '<span class="badge text-bg-primary me-1 mb-1 empik-selected-option">';
              html += '<span>' + escapeHtml(selectedValue) + '</span>';
              html += '<input type="hidden" name="empik_parameters[' + escapeHtml(pid) + '][]" value="' + escapeHtml(selectedValue) + '">';
              html += '<button type="button" class="btn-close btn-close-white ms-1 js-remove-empik-option" aria-label="Usuń"></button>';
              html += '</span>';
            });
          } else {
            html += '<input type="hidden" name="empik_parameters[' + escapeHtml(pid) + ']" class="js-empik-option-value" value="' + escapeHtml(storedValue) + '">';
            html += '<div class="alert alert-light border py-2 px-3 mb-0 js-empik-single-selection ' + (storedValue === '' ? 'd-none' : '') + '">';
            html += 'Wybrano: <strong class="js-empik-selected-label">' + escapeHtml(storedValue) + '</strong>';
            html += '</div>';
          }
          html += '</div></div>';
        } else if (pType === 'multidictionary' || (pType === 'dictionary' && multiple)) {
          html += '<div class="param-option-box">';
          for (var d = 0; d < dict.length; d++) {
            var option = dict[d] || {};
            var optId = String(option.id || '');
            var optionLabel = String(option.value || optId);
            var checked = isValueSelected(optId, selectedValues) || isValueSelected(optionLabel, selectedValues) ? ' checked' : '';
            var inputId = 'empik_param_' + escapeHtml(pid) + '_' + d;
            html += '<div class="form-check mb-1">';
            html += '<input class="form-check-input" type="checkbox" name="empik_parameters[' + escapeHtml(pid) + '][]" id="' + inputId + '" value="' + escapeHtml(optId) + '"' + checked + '>';
            html += '<label class="form-check-label" for="' + inputId + '">' + escapeHtml(optionLabel) + '</label>';
            html += '</div>';
          }
          html += '</div><div class="form-text">Wielokrotny wybór z listy.</div>';
        } else if (pType === 'dictionary' && dict.length) {
          html += '<select name="empik_parameters[' + escapeHtml(pid) + ']" id="empik_param_' + escapeHtml(pid) + '" class="form-select">';
          html += '<option value="">-- Wybierz --</option>';
          for (var s = 0; s < dict.length; s++) {
            var singleOption = dict[s] || {};
            var singleId = String(singleOption.id || '');
            var singleLabel = String(singleOption.value || singleId);
            var selected = storedValue === singleId || storedValue === singleLabel ? ' selected' : '';
            html += '<option value="' + escapeHtml(singleId) + '"' + selected + '>' + escapeHtml(singleLabel) + '</option>';
          }
          html += '</select>';
        } else if (pType === 'integer' || pType === 'number') {
          html += '<input type="number" step="any" class="form-control" name="empik_parameters[' + escapeHtml(pid) + ']" id="empik_param_' + escapeHtml(pid) + '" value="' + escapeHtml(storedValue) + '">';
        } else if (pType === 'textarea') {
          html += '<textarea class="form-control" name="empik_parameters[' + escapeHtml(pid) + ']" id="empik_param_' + escapeHtml(pid) + '" rows="3" placeholder="Wpisz wartości ręcznie, każdą w osobnej linii">' + escapeHtml(storedValue) + '</textarea>';
        } else {
          html += '<input type="text" class="form-control" name="empik_parameters[' + escapeHtml(pid) + ']" id="empik_param_' + escapeHtml(pid) + '" value="' + escapeHtml(storedValue) + '">';
        }

        html += '<div class="small text-secondary mt-2">Typ: ' + escapeHtml(pType) + (multiple ? ' | multiple' : '') + (optionLookup ? ' | lookup' : '') + '</div>';
        html += '</div></div>';
      }

      html += '</div></div>';
      containerNode.innerHTML = html;
      bindEmpikComponentLikeControls(containerNode);
      if (infoNode) {
        infoNode.textContent = loadedLabel;
      }
    }

    function bindEmpikComponentLikeControls(scopeNode) {
      if (!scopeNode) {
        return;
      }

      var root = scopeNode.querySelector('.market-params--empik');
      var optionsUrl = root ? root.getAttribute('data-options-url') : '';
      var showHidden = false;
      var visibilityUrl = '{$baseUrl|escape:"javascript"}?controller=products&action=empikparametervisibility';

      function refreshEmpikHiddenCards() {
        if (!root) {
          return;
        }

        var hiddenCount = 0;
        root.querySelectorAll('.js-empik-param-card').forEach(function (card) {
          var hidden = card.getAttribute('data-param-hidden-global') === '1';
          if (hidden) {
            hiddenCount++;
          }
          card.classList.toggle('d-none', hidden && !showHidden);
        });

        var headerControls = root.querySelector('.js-empik-header-controls');
        var toggleHiddenButton = root.querySelector('.js-empik-toggle-hidden');
        if (!toggleHiddenButton && hiddenCount > 0 && headerControls) {
          toggleHiddenButton = document.createElement('button');
          toggleHiddenButton.type = 'button';
          toggleHiddenButton.className = 'btn btn-sm btn-outline-secondary js-empik-toggle-hidden';
          toggleHiddenButton.setAttribute('data-show-hidden', showHidden ? '1' : '0');
          headerControls.appendChild(toggleHiddenButton);
        }

        if (!toggleHiddenButton) {
          return;
        }

        if (hiddenCount <= 0) {
          toggleHiddenButton.remove();
          return;
        }

        toggleHiddenButton.textContent = showHidden ? 'Ukryj schowane (' + hiddenCount + ')' : 'Pokaż ukryte (' + hiddenCount + ')';
        toggleHiddenButton.setAttribute('data-show-hidden', showHidden ? '1' : '0');
      }

      function applyEmpikHiddenKeys(nextKeys) {
        currentEmpikHiddenKeys = Array.isArray(nextKeys) ? nextKeys.slice() : [];
        var hiddenMap = currentEmpikHiddenKeyMap();

        scopeNode.querySelectorAll('.js-empik-param-card').forEach(function (card) {
          var key = String(card.getAttribute('data-param-key') || '').trim();
          var hidden = key !== '' && !!hiddenMap[key];
          card.setAttribute('data-param-hidden-global', hidden ? '1' : '0');

          var button = card.querySelector('.js-empik-toggle-param-visibility');
          if (button) {
            button.setAttribute('data-hidden', hidden ? '1' : '0');
            button.textContent = hidden ? 'Przywróć' : 'Ukryj globalnie';
            button.classList.toggle('btn-outline-success', hidden);
            button.classList.toggle('btn-outline-secondary', !hidden);
          }
        });

        refreshEmpikHiddenCards();
      }

      function addSelectedOption(lookup, id, label) {
        var multiple = lookup.getAttribute('data-multiple') === '1';
        var selection = lookup.querySelector('.js-empik-option-selection');
        if (!selection) {
          return;
        }

        if (!multiple) {
          var valueInput = selection.querySelector('.js-empik-option-value');
          var notice = selection.querySelector('.js-empik-single-selection');
          var labelNode = selection.querySelector('.js-empik-selected-label');
          if (valueInput) {
            valueInput.value = label;
          }
          if (labelNode) {
            labelNode.textContent = label;
          }
          if (notice) {
            notice.classList.remove('d-none');
          }
          return;
        }

        var exists = Array.prototype.slice.call(selection.querySelectorAll('input[type="hidden"]')).some(function (input) {
          return input.value === label || input.getAttribute('data-option-id') === id;
        });
        if (exists) {
          return;
        }

        var badge = document.createElement('span');
        badge.className = 'badge text-bg-primary me-1 mb-1 empik-selected-option';
        badge.innerHTML = '<span>' + escapeHtml(label) + '</span>'
          + '<input type="hidden" name="empik_parameters[' + escapeHtml(lookup.getAttribute('data-attribute-id')) + '][]" value="' + escapeHtml(label) + '" data-option-id="' + escapeHtml(id) + '">'
          + '<button type="button" class="btn-close btn-close-white ms-1 js-remove-empik-option" aria-label="Usuń"></button>';
        selection.appendChild(badge);
      }

      scopeNode.querySelectorAll('.empik-option-lookup').forEach(function (lookup, lookupIndex) {
        var input = lookup.querySelector('.js-empik-option-search');
        var results = lookup.querySelector('.js-empik-option-results');
        var clear = lookup.querySelector('.js-empik-option-clear');
        var timer = null;
        var requestNumber = 0;

        function hideResults() {
          if (!results) {
            return;
          }
          results.classList.add('d-none');
          results.innerHTML = '';
        }

        function search() {
          if (!input || !results || !optionsUrl) {
            hideResults();
            return;
          }

          var phrase = String(input.value || '').trim();
          var currentRequest = ++requestNumber;
          results.classList.remove('d-none');
          results.innerHTML = '<div class="list-group-item text-muted">Szukam...</div>';

          var url = optionsUrl + '&attribute_id=' + encodeURIComponent(lookup.getAttribute('data-attribute-id'))
            + '&q=' + encodeURIComponent(phrase)
            + '&limit=' + (phrase === '' ? 10 : 400);

          fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) {
              return response.json().then(function (data) {
                if (!response.ok) {
                  throw new Error(data.error || 'Błąd pobierania wartości.');
                }
                return data;
              });
            })
            .then(function (data) {
              if (currentRequest !== requestNumber) {
                return;
              }

              var optionItems = Array.isArray(data.items) ? data.items : [];
              results.innerHTML = '';
              if (!optionItems.length) {
                results.innerHTML = '<div class="list-group-item text-muted">Brak pasujących wartości.</div>';
                return;
              }

              optionItems.forEach(function (option) {
                var id = String(option.id || option.value || '');
                var label = String(option.value || option.id || '');
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'list-group-item list-group-item-action d-flex justify-content-between gap-2';
                button.innerHTML = '<span>' + escapeHtml(label) + '</span><small class="text-muted">' + escapeHtml(id) + '</small>';
                button.addEventListener('click', function () {
                  addSelectedOption(lookup, id, label);
                  if (lookup.getAttribute('data-multiple') === '1') {
                    button.classList.add('active');
                    button.disabled = true;
                    input.focus();
                  } else {
                    input.value = '';
                    hideResults();
                  }
                });
                results.appendChild(button);
              });
            })
            .catch(function (error) {
              if (currentRequest !== requestNumber) {
                return;
              }
              results.innerHTML = '<div class="list-group-item text-danger">' + escapeHtml(error.message) + '</div>';
            });
        }

        if (input) {
          input.addEventListener('input', function () {
            window.clearTimeout(timer);
            timer = window.setTimeout(search, 250);
          });
          input.addEventListener('focus', function () {
            if (results && results.classList.contains('d-none')) {
              search();
            }
          });
        }

        if (clear) {
          clear.addEventListener('click', function () {
            input.value = '';
            hideResults();
            var selection = lookup.querySelector('.js-empik-option-selection');
            if (!selection) {
              return;
            }
            if (lookup.getAttribute('data-multiple') === '1') {
              selection.querySelectorAll('.empik-selected-option').forEach(function (badge) {
                badge.remove();
              });
            } else {
              var valueInput = selection.querySelector('.js-empik-option-value');
              var notice = selection.querySelector('.js-empik-single-selection');
              if (valueInput) {
                valueInput.value = '';
              }
              if (notice) {
                notice.classList.add('d-none');
              }
            }
          });
        }

        lookup.addEventListener('click', function (event) {
          var remove = event.target.closest('.js-remove-empik-option');
          if (remove) {
            var badge = remove.closest('.empik-selected-option');
            if (badge) {
              badge.remove();
            }
          }
        });

        window.setTimeout(search, 50 + (lookupIndex * 40));
      });

      scopeNode.querySelectorAll('.js-market-param-filter').forEach(function (input) {
        input.addEventListener('input', function () {
          var phrase = String(this.value || '').toLowerCase().trim();
          var selector = this.getAttribute('data-target') || '';
          var marketRoot = this.closest('.market-params');
          if (!marketRoot || !selector) {
            return;
          }
          marketRoot.querySelectorAll(selector).forEach(function (card) {
            var text = String(card.getAttribute('data-filter-text') || '').toLowerCase();
            card.classList.toggle('d-none', phrase !== '' && text.indexOf(phrase) === -1);
          });
        });
      });

      if (root) {
        root.addEventListener('click', function (event) {
          var toggleHiddenButton = event.target.closest('.js-empik-toggle-hidden');
          if (toggleHiddenButton) {
            showHidden = !showHidden;
            refreshEmpikHiddenCards();
            return;
          }

          var visibilityButton = event.target.closest('.js-empik-toggle-param-visibility');
          if (!visibilityButton) {
            return;
          }

          var nextHidden = visibilityButton.getAttribute('data-hidden') !== '1';
          var payload = new URLSearchParams();
          payload.set('id', String(visibilityButton.getAttribute('data-param-id') || ''));
          payload.set('name', String(visibilityButton.getAttribute('data-param-name') || ''));
          payload.set('hidden', nextHidden ? '1' : '0');

          visibilityButton.disabled = true;
          fetch(visibilityUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: payload.toString()
          })
            .then(function (response) {
              return response.json().then(function (data) {
                if (!response.ok) {
                  throw new Error(data.error || 'Nie udalo sie zapisac ustawienia parametru.');
                }
                return data;
              });
            })
            .then(function (data) {
              applyEmpikHiddenKeys(data.hidden_keys || []);
            })
            .catch(function (error) {
              window.alert(error && error.message ? error.message : 'Nie udalo sie zapisac ustawienia parametru Empik.');
            })
            .finally(function () {
              visibilityButton.disabled = false;
            });
        });
      }

      refreshEmpikHiddenCards();
    }

    function renderMediaMarktComponentLikeParameterFields(containerNode, infoNode, items, values, emptyLabel, loadedLabel) {
      if (!containerNode) {
        return;
      }

      if (!items || !items.length) {
        containerNode.innerHTML = '<div class="alert alert-light border mb-0">Brak parametrów MediaMarkt do wyświetlenia.</div>';
        if (infoNode) {
          infoNode.textContent = emptyLabel;
        }
        return;
      }

      var selectedOption = categoryInput && categoryInput.options ? categoryInput.options[categoryInput.selectedIndex] : null;
      var mediamarktCategoryId = selectedOption ? String(selectedOption.getAttribute('data-mediamarkt-category-id') || '') : '';
      var html = '';
      var hiddenKeyMap = currentMediaMarktHiddenKeyMap();
      var hiddenCount = 0;
      for (var hiddenIndex = 0; hiddenIndex < items.length; hiddenIndex++) {
        var hiddenItem = items[hiddenIndex] || {};
        var hiddenKey = mediamarktDisplayNameKey(hiddenItem.name || hiddenItem.id || '');
        if (hiddenKey !== '' && hiddenKeyMap[hiddenKey]) {
          hiddenCount++;
        }
      }

      html += '<div class="market-params market-params--mediamarkt" data-options-url="{$baseUrl|escape:"javascript"}?controller=products&action=mediamarktparameteroptions&category_id=' + encodeURIComponent(categoryInput ? categoryInput.value : '') + '">';
      html += '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">';
      html += '<div><div class="fw-bold fs-6">Parametry MediaMarkt</div><div class="small text-muted">';
      if (mediamarktCategoryId) {
        html += 'Kategoria API: <code>' + escapeHtml(mediamarktCategoryId) + '</code>';
      }
      html += '</div></div>';
      html += '<div class="d-flex flex-wrap align-items-center gap-2 js-mediamarkt-header-controls">';
      html += '<div class="small text-muted">' + items.length + ' parametrów</div>';
      if (hiddenCount > 0) {
        html += '<button type="button" class="btn btn-sm btn-outline-secondary js-mediamarkt-toggle-hidden" data-show-hidden="0">Pokaż ukryte (' + hiddenCount + ')</button>';
      }
      html += '</div>';
      html += '</div>';
      html += '<div class="mb-3">';
      html += '<input type="text" class="form-control js-market-param-filter" data-target=".js-mediamarkt-param-card" placeholder="Szukaj po nazwie lub ID parametru MediaMarkt...">';
      html += '</div>';
      html += '<div class="row g-3 mb-4">';

      var seenMediaMarktNames = {};
      for (var i = 0; i < items.length; i++) {
        var item = items[i] || {};
        var pid = String(item.id || '');
        var pName = String(item.name || 'Parametr');
        var displayNameKey = mediamarktDisplayNameKey(pName);
        if (displayNameKey !== '' && seenMediaMarktNames[displayNameKey]) {
          continue;
        }
        if (displayNameKey !== '') {
          seenMediaMarktNames[displayNameKey] = true;
        }
        var pType = String(item.type || 'string').toLowerCase();
        var restrictions = item.restrictions && typeof item.restrictions === 'object' ? item.restrictions : {};
        var multiple = !!item.multiple || pType === 'multidictionary' || restrictions.multipleChoices === true || restrictions.multipleChoices === 1;
        var dict = Array.isArray(item.dictionary) ? item.dictionary : [];
        var optionLookup = !!item.option_lookup;
        if (isMediaMarktFreeTextIdentityParameter(item)) {
          pType = 'text';
          multiple = false;
          dict = [];
          optionLookup = false;
        }
        var isHidden = displayNameKey !== '' && !!hiddenKeyMap[displayNameKey];
        var rawValue = values && typeof values === 'object' && Object.prototype.hasOwnProperty.call(values, pid) ? values[pid] : '';
        var selectedValues = normalizeSelectedArray(rawValue);
        var storedValue = Array.isArray(rawValue) ? rawValue.join(' | ') : (rawValue === null || typeof rawValue === 'undefined' ? '' : String(rawValue));
        var cardText = (pName + ' ' + pid + ' ' + pType).toLowerCase();

        html += '<div class="col-12 col-xl-6 js-mediamarkt-param-card js-param-card' + (isHidden ? ' d-none' : '') + '" data-param-id="' + escapeHtml(pid) + '" data-param-key="' + escapeHtml(displayNameKey) + '" data-param-hidden-global="' + (isHidden ? '1' : '0') + '" data-param-type="' + escapeHtml(pType) + '" data-input-name="mediamarkt_parameters" data-filter-text="' + escapeHtml(cardText) + '">';
        html += '<div class="border rounded-3 p-3 h-100 bg-white">';
        html += '<div class="d-flex align-items-start justify-content-between gap-2 mb-2">';
        html += '<label class="form-label fw-semibold mb-0" for="mediamarkt_param_' + escapeHtml(pid) + '">' + escapeHtml(pName) + '</label>';
        html += '<div class="d-flex flex-wrap align-items-center justify-content-end gap-2">';
        html += '<span class="badge bg-light text-dark border">ID ' + escapeHtml(pid) + '</span>';
        html += '<button type="button" class="btn btn-sm ' + (isHidden ? 'btn-outline-success' : 'btn-outline-secondary') + ' js-mediamarkt-toggle-param-visibility" data-hidden="' + (isHidden ? '1' : '0') + '" data-param-id="' + escapeHtml(pid) + '" data-param-name="' + escapeHtml(pName) + '">' + (isHidden ? 'Przywróć' : 'Ukryj globalnie') + '</button>';
        html += '</div>';
        html += '</div>';

        if (optionLookup) {
          html += '<div class="mediamarkt-option-lookup" data-attribute-id="' + escapeHtml(pid) + '" data-multiple="' + (multiple ? '1' : '0') + '">';
          html += '<div class="input-group">';
          html += '<span class="input-group-text"><i class="bi bi-search"></i></span>';
          html += '<input type="search" class="form-control js-mediamarkt-option-search" placeholder="Szukaj dostępnych wartości dla: ' + escapeHtml(pName) + '" autocomplete="off">';
          html += '<button type="button" class="btn btn-outline-secondary js-mediamarkt-option-clear">Wyczyść</button>';
          html += '</div>';
          html += '<div class="form-text">Po kliknięciu pokazujemy dostępne propozycje. Wpisz tekst, aby zawęzić wyniki tylko dla tego parametru.</div>';
          html += '<div class="list-group mt-2 js-mediamarkt-option-results d-none"></div>';
          html += '<div class="mt-2 js-mediamarkt-option-selection">';
          if (multiple) {
            selectedValues.forEach(function (selectedValue) {
              html += '<span class="badge text-bg-primary me-1 mb-1 mediamarkt-selected-option">';
              html += '<span>' + escapeHtml(selectedValue) + '</span>';
              html += '<input type="hidden" name="mediamarkt_parameters[' + escapeHtml(pid) + '][]" value="' + escapeHtml(selectedValue) + '">';
              html += '<button type="button" class="btn-close btn-close-white ms-1 js-remove-mediamarkt-option" aria-label="Usuń"></button>';
              html += '</span>';
            });
          } else {
            html += '<input type="hidden" name="mediamarkt_parameters[' + escapeHtml(pid) + ']" class="js-mediamarkt-option-value" value="' + escapeHtml(storedValue) + '">';
            html += '<div class="alert alert-light border py-2 px-3 mb-0 js-mediamarkt-single-selection ' + (storedValue === '' ? 'd-none' : '') + '">';
            html += 'Wybrano: <strong class="js-mediamarkt-selected-label">' + escapeHtml(storedValue) + '</strong>';
            html += '</div>';
          }
          html += '</div></div>';
        } else if (pType === 'multidictionary' || (pType === 'dictionary' && multiple)) {
          html += '<div class="param-option-box">';
          for (var d = 0; d < dict.length; d++) {
            var option = dict[d] || {};
            var optId = String(option.id || '');
            var optionLabel = String(option.value || optId);
            var checked = isValueSelected(optId, selectedValues) || isValueSelected(optionLabel, selectedValues) ? ' checked' : '';
            var inputId = 'mediamarkt_param_' + escapeHtml(pid) + '_' + d;
            html += '<div class="form-check mb-1">';
            html += '<input class="form-check-input" type="checkbox" name="mediamarkt_parameters[' + escapeHtml(pid) + '][]" id="' + inputId + '" value="' + escapeHtml(optId) + '"' + checked + '>';
            html += '<label class="form-check-label" for="' + inputId + '">' + escapeHtml(optionLabel) + '</label>';
            html += '</div>';
          }
          html += '</div><div class="form-text">Wielokrotny wybór z listy.</div>';
        } else if (pType === 'dictionary' && dict.length) {
          html += '<select name="mediamarkt_parameters[' + escapeHtml(pid) + ']" id="mediamarkt_param_' + escapeHtml(pid) + '" class="form-select">';
          html += '<option value="">-- Wybierz --</option>';
          for (var s = 0; s < dict.length; s++) {
            var singleOption = dict[s] || {};
            var singleId = String(singleOption.id || '');
            var singleLabel = String(singleOption.value || singleId);
            var selected = storedValue === singleId || storedValue === singleLabel ? ' selected' : '';
            html += '<option value="' + escapeHtml(singleId) + '"' + selected + '>' + escapeHtml(singleLabel) + '</option>';
          }
          html += '</select>';
        } else if (pType === 'integer' || pType === 'number') {
          html += '<input type="number" step="any" class="form-control" name="mediamarkt_parameters[' + escapeHtml(pid) + ']" id="mediamarkt_param_' + escapeHtml(pid) + '" value="' + escapeHtml(storedValue) + '">';
        } else if (pType === 'textarea') {
          html += '<textarea class="form-control" name="mediamarkt_parameters[' + escapeHtml(pid) + ']" id="mediamarkt_param_' + escapeHtml(pid) + '" rows="3" placeholder="Wpisz wartości ręcznie, każdą w osobnej linii">' + escapeHtml(storedValue) + '</textarea>';
        } else {
          html += '<input type="text" class="form-control" name="mediamarkt_parameters[' + escapeHtml(pid) + ']" id="mediamarkt_param_' + escapeHtml(pid) + '" value="' + escapeHtml(storedValue) + '">';
        }

        html += '<div class="small text-secondary mt-2">Typ: ' + escapeHtml(pType) + (multiple ? ' | multiple' : '') + (optionLookup ? ' | lookup' : '') + '</div>';
        html += '</div></div>';
      }

      html += '</div></div>';
      containerNode.innerHTML = html;
      bindMediaMarktComponentLikeControls(containerNode);
      if (infoNode) {
        infoNode.textContent = loadedLabel;
      }
    }

    function bindMediaMarktComponentLikeControls(scopeNode) {
      if (!scopeNode) {
        return;
      }

      var root = scopeNode.querySelector('.market-params--mediamarkt');
      var optionsUrl = root ? root.getAttribute('data-options-url') : '';
      var showHidden = false;
      var visibilityUrl = '{$baseUrl|escape:"javascript"}?controller=products&action=mediamarktparametervisibility';

      function refreshMediaMarktHiddenCards() {
        if (!root) {
          return;
        }

        var hiddenCount = 0;
        root.querySelectorAll('.js-mediamarkt-param-card').forEach(function (card) {
          var hidden = card.getAttribute('data-param-hidden-global') === '1';
          if (hidden) {
            hiddenCount++;
          }
          card.classList.toggle('d-none', hidden && !showHidden);
        });

        var headerControls = root.querySelector('.js-mediamarkt-header-controls');
        var toggleHiddenButton = root.querySelector('.js-mediamarkt-toggle-hidden');
        if (!toggleHiddenButton && hiddenCount > 0 && headerControls) {
          toggleHiddenButton = document.createElement('button');
          toggleHiddenButton.type = 'button';
          toggleHiddenButton.className = 'btn btn-sm btn-outline-secondary js-mediamarkt-toggle-hidden';
          toggleHiddenButton.setAttribute('data-show-hidden', showHidden ? '1' : '0');
          headerControls.appendChild(toggleHiddenButton);
        }

        if (!toggleHiddenButton) {
          return;
        }

        if (hiddenCount <= 0) {
          toggleHiddenButton.remove();
          return;
        }

        toggleHiddenButton.textContent = showHidden ? 'Ukryj schowane (' + hiddenCount + ')' : 'Pokaż ukryte (' + hiddenCount + ')';
        toggleHiddenButton.setAttribute('data-show-hidden', showHidden ? '1' : '0');
      }

      function applyMediaMarktHiddenKeys(nextKeys) {
        currentMediaMarktHiddenKeys = Array.isArray(nextKeys) ? nextKeys.slice() : [];
        var hiddenMap = currentMediaMarktHiddenKeyMap();

        scopeNode.querySelectorAll('.js-mediamarkt-param-card').forEach(function (card) {
          var key = String(card.getAttribute('data-param-key') || '').trim();
          var hidden = key !== '' && !!hiddenMap[key];
          card.setAttribute('data-param-hidden-global', hidden ? '1' : '0');

          var button = card.querySelector('.js-mediamarkt-toggle-param-visibility');
          if (button) {
            button.setAttribute('data-hidden', hidden ? '1' : '0');
            button.textContent = hidden ? 'Przywróć' : 'Ukryj globalnie';
            button.classList.toggle('btn-outline-success', hidden);
            button.classList.toggle('btn-outline-secondary', !hidden);
          }
        });

        refreshMediaMarktHiddenCards();
      }

      function addSelectedOption(lookup, id, label) {
        var multiple = lookup.getAttribute('data-multiple') === '1';
        var selection = lookup.querySelector('.js-mediamarkt-option-selection');
        if (!selection) {
          return;
        }

        if (!multiple) {
          var valueInput = selection.querySelector('.js-mediamarkt-option-value');
          var notice = selection.querySelector('.js-mediamarkt-single-selection');
          var labelNode = selection.querySelector('.js-mediamarkt-selected-label');
          if (valueInput) {
            valueInput.value = label;
          }
          if (labelNode) {
            labelNode.textContent = label;
          }
          if (notice) {
            notice.classList.remove('d-none');
          }
          return;
        }

        var exists = Array.prototype.slice.call(selection.querySelectorAll('input[type="hidden"]')).some(function (input) {
          return input.value === label || input.getAttribute('data-option-id') === id;
        });
        if (exists) {
          return;
        }

        var badge = document.createElement('span');
        badge.className = 'badge text-bg-primary me-1 mb-1 mediamarkt-selected-option';
        badge.innerHTML = '<span>' + escapeHtml(label) + '</span>'
          + '<input type="hidden" name="mediamarkt_parameters[' + escapeHtml(lookup.getAttribute('data-attribute-id')) + '][]" value="' + escapeHtml(label) + '" data-option-id="' + escapeHtml(id) + '">'
          + '<button type="button" class="btn-close btn-close-white ms-1 js-remove-mediamarkt-option" aria-label="Usuń"></button>';
        selection.appendChild(badge);
      }

      scopeNode.querySelectorAll('.mediamarkt-option-lookup').forEach(function (lookup, lookupIndex) {
        var input = lookup.querySelector('.js-mediamarkt-option-search');
        var results = lookup.querySelector('.js-mediamarkt-option-results');
        var clear = lookup.querySelector('.js-mediamarkt-option-clear');
        var timer = null;
        var requestNumber = 0;

        function hideResults() {
          if (!results) {
            return;
          }
          results.classList.add('d-none');
          results.innerHTML = '';
        }

        function search() {
          if (!input || !results || !optionsUrl) {
            hideResults();
            return;
          }

          var phrase = String(input.value || '').trim();
          var currentRequest = ++requestNumber;
          results.classList.remove('d-none');
          results.innerHTML = '<div class="list-group-item text-muted">Szukam...</div>';

          var url = optionsUrl + '&attribute_id=' + encodeURIComponent(lookup.getAttribute('data-attribute-id'))
            + '&q=' + encodeURIComponent(phrase)
            + '&limit=' + (phrase === '' ? 10 : 400);

          fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) {
              return response.json().then(function (data) {
                if (!response.ok) {
                  throw new Error(data.error || 'Błąd pobierania wartości.');
                }
                return data;
              });
            })
            .then(function (data) {
              if (currentRequest !== requestNumber) {
                return;
              }

              var optionItems = Array.isArray(data.items) ? data.items : [];
              results.innerHTML = '';
              if (!optionItems.length) {
                results.innerHTML = '<div class="list-group-item text-muted">Brak pasujących wartości.</div>';
                return;
              }

              optionItems.forEach(function (option) {
                var id = String(option.id || option.value || '');
                var label = String(option.value || option.id || '');
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'list-group-item list-group-item-action d-flex justify-content-between gap-2';
                button.innerHTML = '<span>' + escapeHtml(label) + '</span><small class="text-muted">' + escapeHtml(id) + '</small>';
                button.addEventListener('click', function () {
                  addSelectedOption(lookup, id, label);
                  if (lookup.getAttribute('data-multiple') === '1') {
                    button.classList.add('active');
                    button.disabled = true;
                    input.focus();
                  } else {
                    input.value = '';
                    hideResults();
                  }
                });
                results.appendChild(button);
              });
            })
            .catch(function (error) {
              if (currentRequest !== requestNumber) {
                return;
              }
              results.innerHTML = '<div class="list-group-item text-danger">' + escapeHtml(error.message) + '</div>';
            });
        }

        if (input) {
          input.addEventListener('input', function () {
            window.clearTimeout(timer);
            timer = window.setTimeout(search, 250);
          });
          input.addEventListener('focus', function () {
            if (results && results.classList.contains('d-none')) {
              search();
            }
          });
        }

        if (clear) {
          clear.addEventListener('click', function () {
            input.value = '';
            hideResults();
            var selection = lookup.querySelector('.js-mediamarkt-option-selection');
            if (!selection) {
              return;
            }
            if (lookup.getAttribute('data-multiple') === '1') {
              selection.querySelectorAll('.mediamarkt-selected-option').forEach(function (badge) {
                badge.remove();
              });
            } else {
              var valueInput = selection.querySelector('.js-mediamarkt-option-value');
              var notice = selection.querySelector('.js-mediamarkt-single-selection');
              if (valueInput) {
                valueInput.value = '';
              }
              if (notice) {
                notice.classList.add('d-none');
              }
            }
          });
        }

        lookup.addEventListener('click', function (event) {
          var remove = event.target.closest('.js-remove-mediamarkt-option');
          if (remove) {
            var badge = remove.closest('.mediamarkt-selected-option');
            if (badge) {
              badge.remove();
            }
          }
        });

        window.setTimeout(search, 50 + (lookupIndex * 40));
      });

      scopeNode.querySelectorAll('.js-market-param-filter').forEach(function (input) {
        input.addEventListener('input', function () {
          var phrase = String(this.value || '').toLowerCase().trim();
          var selector = this.getAttribute('data-target') || '';
          var marketRoot = this.closest('.market-params');
          if (!marketRoot || !selector) {
            return;
          }
          marketRoot.querySelectorAll(selector).forEach(function (card) {
            var text = String(card.getAttribute('data-filter-text') || '').toLowerCase();
            card.classList.toggle('d-none', phrase !== '' && text.indexOf(phrase) === -1);
          });
        });
      });

      if (root) {
        root.addEventListener('click', function (event) {
          var toggleHiddenButton = event.target.closest('.js-mediamarkt-toggle-hidden');
          if (toggleHiddenButton) {
            showHidden = !showHidden;
            refreshMediaMarktHiddenCards();
            return;
          }

          var visibilityButton = event.target.closest('.js-mediamarkt-toggle-param-visibility');
          if (!visibilityButton) {
            return;
          }

          var nextHidden = visibilityButton.getAttribute('data-hidden') !== '1';
          var payload = new URLSearchParams();
          payload.set('id', String(visibilityButton.getAttribute('data-param-id') || ''));
          payload.set('name', String(visibilityButton.getAttribute('data-param-name') || ''));
          payload.set('hidden', nextHidden ? '1' : '0');

          visibilityButton.disabled = true;
          fetch(visibilityUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: payload.toString()
          })
            .then(function (response) {
              return response.json().then(function (data) {
                if (!response.ok) {
                  throw new Error(data.error || 'Nie udalo sie zapisac ustawienia parametru.');
                }
                return data;
              });
            })
            .then(function (data) {
              applyMediaMarktHiddenKeys(data.hidden_keys || []);
            })
            .catch(function (error) {
              window.alert(error && error.message ? error.message : 'Nie udalo sie zapisac ustawienia parametru MediaMarkt.');
            })
            .finally(function () {
              visibilityButton.disabled = false;
            });
        });
      }

      refreshMediaMarktHiddenCards();
    }


    function readParameterCurrentValue(card) {
      if (!card) {
        return {
          values: [],
          labels: []
        };
      }

      var inputName = String(card.getAttribute('data-input-name') || '');
      var parameterId = String(card.getAttribute('data-param-id') || '');
      var result = {
        values: [],
        labels: []
      };

      if (inputName === '' || parameterId === '') {
        return result;
      }

      var multiControls = card.querySelectorAll('input[name="' + cssEscape(inputName + '[' + parameterId + '][]') + '"]:checked');
      if (multiControls.length) {
        for (var i = 0; i < multiControls.length; i++) {
          result.values.push(String(multiControls[i].value || ''));
          result.labels.push(controlLabelText(multiControls[i]));
        }
        return result;
      }

      var control = card.querySelector('[name="' + cssEscape(inputName + '[' + parameterId + ']') + '"]');
      if (!control) {
        return result;
      }

      if (control.tagName && control.tagName.toLowerCase() === 'select') {
        var selectedOption = control.options && control.selectedIndex >= 0 ? control.options[control.selectedIndex] : null;
        if (String(control.value || '') !== '') {
          result.values.push(String(control.value || ''));
          result.labels.push(selectedOption ? String(selectedOption.textContent || '').trim() : '');
        }
        return result;
      }

      var rawValue = String(control.value || '').trim();
      if (rawValue !== '') {
        result.values.push(rawValue);
      }

      return result;
    }

    function cssEscape(value) {
      if (window.CSS && typeof window.CSS.escape === 'function') {
        return window.CSS.escape(value);
      }

      return String(value || '').replace(/["\\]/g, '\\$&');
    }

    function controlLabelText(control) {
      if (!control || !control.id) {
        return '';
      }

      var label = document.querySelector('label[for="' + cssEscape(control.id) + '"]');
      return label ? String(label.textContent || '').trim() : '';
    }

    function refreshParameterImportValue(card) {
      var target = card ? card.querySelector('.js-param-import-current') : null;
      if (!target) {
        return;
      }

      var current = readParameterCurrentValue(card);
      var parameterId = String(card.getAttribute('data-param-id') || '');
      target.innerHTML = '';
      target.appendChild(document.createTextNode('CSV/import product.allegro_parameter.' + parameterId + ': '));

      if (!current.values.length) {
        var empty = document.createElement('span');
        empty.className = 'text-muted';
        empty.textContent = 'brak';
        target.appendChild(empty);
        return;
      }

      var code = document.createElement('code');
      code.textContent = current.values.join('|');
      target.appendChild(code);

      var labels = current.labels.filter(function (label, index, list) {
        return label !== '' && current.values.indexOf(label) === -1 && list.indexOf(label) === index;
      });

      if (labels.length) {
        target.appendChild(document.createTextNode(' | wariant: ' + labels.join(', ')));
      }
    }

    function bindParameterImportValueRefresh(scopeNode) {
      if (!scopeNode) {
        return;
      }

      var cards = scopeNode.querySelectorAll('.js-param-card');
      for (var i = 0; i < cards.length; i++) {
        (function (card) {
          refreshParameterImportValue(card);

          var controls = card.querySelectorAll('select, input, textarea');
          for (var c = 0; c < controls.length; c++) {
            controls[c].addEventListener('change', function () {
              refreshParameterImportValue(card);
            });
            controls[c].addEventListener('input', function () {
              refreshParameterImportValue(card);
            });
          }
        })(cards[i]);
      }
    }


    function renderAllegroParameterFields(items, values) {
      renderMarketplaceParameterFields(allegroContainer, allegroInfo, items, values, 'allegro_parameters', 'Brak parametrow dla tej kategorii Allegro.', 'Parametry Allegro zaladowane.', 'select');
      currentAllegroItems = items || [];
    }

    function renderEmpikParameterFields(items, values) {
      renderEmpikComponentLikeParameterFields(empikContainer, empikInfo, items, values, 'Brak parametrow dla tej kategorii Empik.', 'Parametry Empik zaladowane.');
      currentEmpikItems = items || [];
    }

    function renderMediaMarktParameterFields(items, values) {
      renderMediaMarktComponentLikeParameterFields(mediamarktContainer, mediamarktInfo, items, values, 'Brak parametrow dla tej kategorii MediaMarkt.', 'Parametry MediaMarkt zaladowane.');
      currentMediaMarktItems = items || [];
    }


    function renderTemuParameterFields(items, values) {
      renderMarketplaceParameterFields(temuContainer, temuInfo, items, values, 'temu_parameters', 'Brak parametrow dla tej kategorii Temu.', 'Parametry Temu zaladowane.', 'select');
      currentTemuItems = items || [];
    }

    function bindOptionFilters(scopeNode) {
      if (!scopeNode) {
        return;
      }

      var filters = scopeNode.querySelectorAll('.js-param-option-filter');
      for (var i = 0; i < filters.length; i++) {
        filters[i].addEventListener('input', function () {
          var phrase = String(this.value || '').toLowerCase().trim();
          var card = this.closest('.js-param-card');
          if (!card) {
            return;
          }

          var checkboxOptions = card.querySelectorAll('.js-param-option');
          for (var c = 0; c < checkboxOptions.length; c++) {
            var option = checkboxOptions[c];
            option.setAttribute('data-text-filter-match', phrase === '' || String(option.getAttribute('data-option-label') || '').indexOf(phrase) !== -1 ? '1' : '0');
          }

          var select = card.querySelector('.js-param-select');
          if (select) {
            for (var o = 0; o < select.options.length; o++) {
              var opt = select.options[o];
              if (o === 0) {
                continue;
              }
              var optLabel = String(opt.getAttribute('data-option-label') || '').toLowerCase();
              opt.setAttribute('data-text-filter-match', phrase === '' || optLabel.indexOf(phrase) !== -1 ? '1' : '0');
            }
          }

          refreshDependentParameterCard(card, false);
        });
      }
    }

    function parseDependsOnValueIds(node) {
      if (!node) {
        return [];
      }

      try {
        var parsed = JSON.parse(String(node.getAttribute('data-depends-on-value-ids') || '[]'));
        return Array.isArray(parsed) ? parsed.map(String) : [];
      } catch (error) {
        return [];
      }
    }

    function valuesIntersect(first, second) {
      for (var i = 0; i < first.length; i++) {
        if (second.indexOf(String(first[i])) !== -1) {
          return true;
        }
      }
      return false;
    }

    function refreshDependentParameterCard(card, clearInvalidValues) {
      if (!card) {
        return;
      }

      var scopeNode = card.parentNode;
      var inputName = String(card.getAttribute('data-input-name') || '');
      var dependsOnParameterId = String(card.getAttribute('data-depends-on-parameter-id') || '');
      var selectedDependencyValues = [];

      if (dependsOnParameterId !== '' && scopeNode) {
        var controllerCard = scopeNode.querySelector('.js-param-card[data-input-name="' + cssEscape(inputName) + '"][data-param-id="' + cssEscape(dependsOnParameterId) + '"]');
        selectedDependencyValues = readParameterCurrentValue(controllerCard).values;
      }

      var visibleCount = 0;
      var checkboxOptions = card.querySelectorAll('.js-param-option');
      for (var i = 0; i < checkboxOptions.length; i++) {
        var optionRow = checkboxOptions[i];
        var requiredValues = parseDependsOnValueIds(optionRow);
        var dependencyMatch = dependsOnParameterId === '' || requiredValues.length === 0 || valuesIntersect(requiredValues, selectedDependencyValues);
        var textMatch = optionRow.getAttribute('data-text-filter-match') !== '0';
        var visible = dependencyMatch && textMatch;
        optionRow.style.display = visible ? '' : 'none';

        var checkbox = optionRow.querySelector('input[type="checkbox"]');
        if (!dependencyMatch && clearInvalidValues && checkbox && checkbox.checked) {
          checkbox.checked = false;
        }
        if (visible) {
          visibleCount++;
        }
      }

      var select = card.querySelector('.js-param-select');
      if (select) {
        for (var o = 0; o < select.options.length; o++) {
          var selectOption = select.options[o];
          if (o === 0) {
            selectOption.hidden = false;
            continue;
          }

          var selectRequiredValues = parseDependsOnValueIds(selectOption);
          var selectDependencyMatch = dependsOnParameterId === '' || selectRequiredValues.length === 0 || valuesIntersect(selectRequiredValues, selectedDependencyValues);
          var selectTextMatch = selectOption.getAttribute('data-text-filter-match') !== '0';
          selectOption.hidden = !(selectDependencyMatch && selectTextMatch);
          if (!selectDependencyMatch && clearInvalidValues && selectOption.selected) {
            select.value = '';
          }
          if (selectDependencyMatch && selectTextMatch) {
            visibleCount++;
          }
        }
      }

      var statusNode = card.querySelector('.js-param-dependency-status');
      if (statusNode && dependsOnParameterId !== '') {
        statusNode.textContent = selectedDependencyValues.length
          ? 'Modele zgodne z wybraną marką: ' + visibleCount
          : 'Najpierw wybierz markę w parametrze ID: ' + dependsOnParameterId;
      }

      refreshFitsToCard(card);
      refreshParameterImportValue(card);
    }

    function refreshAllDependentParameterCards(scopeNode, clearInvalidValues) {
      if (!scopeNode) {
        return;
      }

      var cards = scopeNode.querySelectorAll('.js-param-card');
      for (var i = 0; i < cards.length; i++) {
        refreshDependentParameterCard(cards[i], clearInvalidValues);
      }
    }

    function bindDependentParameterFields(scopeNode) {
      if (!scopeNode) {
        return;
      }

      scopeNode.addEventListener('change', function () {
        refreshAllDependentParameterCards(scopeNode, true);
      });
      refreshAllDependentParameterCards(scopeNode, true);
    }

    function bindRemoteSelectMappings(scopeNode) {
      if (!scopeNode) {
        return;
      }

      var selects = scopeNode.querySelectorAll('.js-param-remote-select');
      for (var i = 0; i < selects.length; i++) {
        var fetchRemoteSelectOptions = function (selectNode) {
          var attributeId = String(selectNode.getAttribute('data-attribute-id') || '');
          var currentValue = String(selectNode.getAttribute('data-current-value') || '');
          var categoryId = categoryInput ? String(categoryInput.value || '') : '';
          var statusNode = selectNode.parentNode ? selectNode.parentNode.querySelector('.js-param-autocomplete-status') : null;

          if (!attributeId || !categoryId) {
            return;
          }

          if (statusNode) {
            statusNode.textContent = 'Ladowanie pierwszych 10 wariantow z Empik...';
          }

          var url = '{$baseUrl|escape:"javascript"}?controller=products&action=empikparameteroptions&category_id=' + encodeURIComponent(categoryId) + '&attribute_id=' + encodeURIComponent(attributeId) + '&q=&limit=10';
          fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) {
              return response.text().then(function (rawText) {
                var parsed = {};
                try {
                  parsed = rawText ? JSON.parse(rawText) : {};
                } catch (e) {
                  parsed = { error: rawText || ('HTTP ' + response.status) };
                }
                if (!response.ok) {
                  throw new Error(parsed && parsed.error ? parsed.error : ('HTTP ' + response.status));
                }
                return parsed;
              });
            })
            .then(function (data) {
              var items = data && data.items ? data.items : [];
              var optionsHtml = '<option value="">Wybierz wariant</option>';
              var hasCurrentValue = false;
              for (var j = 0; j < items.length; j++) {
                var option = items[j] || {};
                var optionId = String(option.id || '');
                var optionLabel = String(option.value || optionId);
                var selected = currentValue !== '' && (currentValue === optionId || currentValue.toLowerCase() === optionLabel.toLowerCase());
                if (selected) {
                  hasCurrentValue = true;
                }
                optionsHtml += '<option value="' + escapeHtml(optionId) + '"' + (selected ? ' selected' : '') + '>' + escapeHtml(optionLabel) + ' [ID: ' + optionId + ']</option>';
              }

              if (currentValue !== '' && !hasCurrentValue) {
                optionsHtml = '<option value="' + escapeHtml(currentValue) + '" selected>Aktualna wartosc [ID: ' + escapeHtml(currentValue) + ']</option>' + optionsHtml;
              }

              selectNode.innerHTML = optionsHtml;
              if (statusNode) {
                statusNode.textContent = items.length ? ('Pokazano pierwsze ' + items.length + ' wariantow z Empik.') : 'Brak wariantow do pokazania.';
              }
            })
            .catch(function (error) {
              selectNode.innerHTML = '<option value="">Nie udalo sie zaladowac wariantow</option>';
              if (statusNode) {
                statusNode.textContent = error && error.message ? error.message : 'Nie udalo sie pobrac wariantow Empik.';
              }
            });
        };

        fetchRemoteSelectOptions(selects[i]);
      }
    }

    function refreshFitsToCard(card) {
      if (!card) {
        return;
      }

      var summaryNode = card.querySelector('.js-fits-to-summary');
      var listNode = card.querySelector('.js-fits-to-selected-list');
      if (!summaryNode && !listNode) {
        return;
      }

      var allOptions = card.querySelectorAll('.js-param-option');
      var checkedOptions = card.querySelectorAll('.js-param-option input[type="checkbox"]:checked');
      var selectedLabels = [];
      var visibleCount = 0;

      for (var v = 0; v < allOptions.length; v++) {
        if (allOptions[v].style.display !== 'none') {
          visibleCount++;
        }
      }

      for (var i = 0; i < checkedOptions.length; i++) {
        var checkbox = checkedOptions[i];
        var optionRow = checkbox.closest('.js-param-option');
        if (!optionRow) {
          continue;
        }

        var labelNode = optionRow.querySelector('label');
        selectedLabels.push(labelNode ? String(labelNode.textContent || '').trim() : String(checkbox.value || '').trim());
      }

      if (summaryNode) {
        summaryNode.textContent = 'Wybrano ' + checkedOptions.length + ' z ' + allOptions.length + ' modeli';
        if (visibleCount !== allOptions.length) {
          summaryNode.textContent += ' | widoczne: ' + visibleCount;
        }
      }

      if (listNode) {
        if (!selectedLabels.length) {
          listNode.textContent = 'Brak wybranych modeli.';
        } else {
          listNode.textContent = 'Wybrane: ' + selectedLabels.slice(0, 8).join(', ') + (selectedLabels.length > 8 ? ' +' + (selectedLabels.length - 8) : '');
        }
      }
    }

    function bindFitsToPickers(scopeNode) {
      if (!scopeNode) {
        return;
      }

      var cards = scopeNode.querySelectorAll('.js-param-card');
      for (var i = 0; i < cards.length; i++) {
        (function (card) {
          if (!card.querySelector('.js-fits-to-summary')) {
            return;
          }

          if (card.getAttribute('data-fits-to-bound') === '1') {
            refreshFitsToCard(card);
            return;
          }

          card.setAttribute('data-fits-to-bound', '1');
          refreshFitsToCard(card);

          var selectVisibleButton = card.querySelector('.js-fits-to-select-visible');
          if (selectVisibleButton) {
            selectVisibleButton.addEventListener('click', function () {
              var visibleCheckboxes = card.querySelectorAll('.js-param-option input[type="checkbox"]');
              for (var c = 0; c < visibleCheckboxes.length; c++) {
                var optionRow = visibleCheckboxes[c].closest('.js-param-option');
                if (optionRow && optionRow.style.display === 'none') {
                  continue;
                }
                visibleCheckboxes[c].checked = true;
              }
              refreshFitsToCard(card);
            });
          }

          var clearButton = card.querySelector('.js-fits-to-clear');
          if (clearButton) {
            clearButton.addEventListener('click', function () {
              var checkboxes = card.querySelectorAll('.js-param-option input[type="checkbox"]');
              for (var c = 0; c < checkboxes.length; c++) {
                checkboxes[c].checked = false;
              }
              refreshFitsToCard(card);
            });
          }

          card.addEventListener('change', function (event) {
            if (event.target && event.target.matches('.js-param-option input[type="checkbox"]')) {
              refreshFitsToCard(card);
            }
          });
        })(cards[i]);
      }
    }

    function syncCompatibilityPayload() {
      if (!allegroCompatibilityListPayload) {
        return;
      }

      if (!currentAllegroCompatibilitySupport || !Array.isArray(currentAllegroCompatibilityList) || !currentAllegroCompatibilityList.length) {
        allegroCompatibilityListPayload.value = '';
        return;
      }

      allegroCompatibilityListPayload.value = JSON.stringify({
        input_type: String(currentAllegroCompatibilitySupport.input_type || ''),
        items_type: String(currentAllegroCompatibilitySupport.items_type || ''),
        items: currentAllegroCompatibilityList
      });
    }

    function normalizeCompatibilityItem(item) {
      item = item && typeof item === 'object' ? item : {};
      var normalized = {
        id: String(item.id || ''),
        text: String(item.text || ''),
        additional_info: ''
      };

      if (Array.isArray(item.additionalInfo) && item.additionalInfo.length) {
        normalized.additional_info = String((item.additionalInfo[0] && item.additionalInfo[0].value) || '');
      } else if (typeof item.additional_info !== 'undefined') {
        normalized.additional_info = String(item.additional_info || '');
      }

      if (!normalized.text && normalized.id) {
        normalized.text = normalized.id;
      }

      return normalized;
    }

    function currentCompatibilityIds() {
      var ids = [];
      for (var i = 0; i < currentAllegroCompatibilityList.length; i++) {
        var item = normalizeCompatibilityItem(currentAllegroCompatibilityList[i]);
        if (item.id) {
          ids.push(item.id);
        }
      }
      return ids;
    }

    function updateCompatibilityAdditionalInfo(itemId, value) {
      for (var i = 0; i < currentAllegroCompatibilityList.length; i++) {
        var item = normalizeCompatibilityItem(currentAllegroCompatibilityList[i]);
        if (item.id !== itemId) {
          continue;
        }

        item.additional_info = String(value || '').trim();
        currentAllegroCompatibilityList[i] = item;
        syncCompatibilityPayload();
        return;
      }
    }

    function removeCompatibilityItem(itemId) {
      var nextItems = [];
      for (var i = 0; i < currentAllegroCompatibilityList.length; i++) {
        var item = normalizeCompatibilityItem(currentAllegroCompatibilityList[i]);
        if (item.id && item.id === itemId) {
          continue;
        }
        nextItems.push(item);
      }

      currentAllegroCompatibilityList = nextItems;
      renderCompatibilitySection();
    }

    function addCompatibilityItem(item) {
      var normalized = normalizeCompatibilityItem(item);
      if (!normalized.id) {
        return;
      }

      var existingIds = currentCompatibilityIds();
      if (existingIds.indexOf(normalized.id) !== -1) {
        return;
      }

      currentAllegroCompatibilityList.push(normalized);
      renderCompatibilitySection();
    }

    function renderCompatibilityResults(items) {
      var html = '<option value="">Wybierz pozycje z wynikow...</option>';
      var selectedIds = currentCompatibilityIds();

      for (var i = 0; i < items.length; i++) {
        var item = normalizeCompatibilityItem(items[i]);
        if (!item.id) {
          continue;
        }

        html += '<option value="' + escapeHtml(item.id) + '"'
          + ' data-text="' + escapeHtml(item.text) + '"'
          + (selectedIds.indexOf(item.id) !== -1 ? ' disabled' : '')
          + '>' + escapeHtml(item.text || item.id) + '</option>';
      }

      return html;
    }

    function renderCompatibilitySection() {
      if (!allegroCompatibilityContainer) {
        return;
      }

      syncCompatibilityPayload();

      if (!currentAllegroCompatibilitySupport) {
        allegroCompatibilityContainer.innerHTML = '<div class="small text-secondary">Ta kategoria nie ma aktywnej sekcji Pasuje do.</div>';
        return;
      }

      var inputType = String(currentAllegroCompatibilitySupport.input_type || '');
      var itemsType = String(currentAllegroCompatibilitySupport.items_type || '');
      var validationRules = currentAllegroCompatibilitySupport.validation_rules || {};
      var maxRows = parseInt(validationRules.maxRows || 0, 10);
      var html = '';

      if (inputType === 'ID') {
        html += '<div class="row g-2 mb-3">';
        html += '  <div class="col-md-8">';
        html += '    <label class="form-label">Wyszukaj model telefonu</label>';
        html += '    <input type="text" id="allegro-compatibility-search" class="form-control form-control-sm" placeholder="np. iPhone 15 Pro, Galaxy S24, Redmi Note 13">';
        html += '    <div class="form-text">Wyszukiwanie modeli telefonow z oficjalnej listy Allegro dla typu ' + escapeHtml(itemsType || '-') + '.</div>';
        html += '  </div>';
        html += '  <div class="col-md-4">';
        html += '    <label class="form-label">Wyniki</label>';
        html += '    <select id="allegro-compatibility-results" class="form-select form-select-sm"><option value="">Wpisz min. 3 znaki...</option></select>';
        html += '  </div>';
        html += '</div>';
      } else if (inputType === 'TEXT') {
        html += '<label class="form-label">Lista kompatybilnosci</label>';
        html += '<textarea id="allegro-compatibility-text" class="form-control" rows="6" placeholder="Jedna pozycja w jednej linii"></textarea>';
        html += '<div class="form-text">Kategoria wymaga tekstowej wersji Pasuje do.</div>';
      }

      html += '<div class="small text-secondary mb-2">Typ wejscia: ' + escapeHtml(inputType || '-') + (maxRows > 0 ? ' | limit pozycji: ' + maxRows : '') + '</div>';
      html += '<div id="allegro-compatibility-selected">';

      if (inputType === 'ID') {
        if (!currentAllegroCompatibilityList.length) {
          html += '<div class="alert alert-light border mb-0">Brak wybranych pozycji Pasuje do.</div>';
        } else {
          html += '<div class="row g-2">';
          for (var i = 0; i < currentAllegroCompatibilityList.length; i++) {
            var item = normalizeCompatibilityItem(currentAllegroCompatibilityList[i]);
            html += '<div class="col-12" data-compatibility-id="' + escapeHtml(item.id) + '">';
            html += '  <div class="border rounded p-3 bg-white">';
            html += '    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">';
            html += '      <div>';
            html += '        <div class="fw-semibold">' + escapeHtml(item.text || item.id) + '</div>';
            html += '        <div class="small text-secondary">' + escapeHtml(item.id) + '</div>';
            html += '      </div>';
            html += '      <button type="button" class="btn btn-sm btn-outline-danger js-remove-compatibility-item" data-compatibility-id="' + escapeHtml(item.id) + '">Usun</button>';
            html += '    </div>';
            html += '    <input type="text" class="form-control form-control-sm js-compatibility-additional-info" data-compatibility-id="' + escapeHtml(item.id) + '" value="' + escapeHtml(item.additional_info || '') + '" placeholder="Dodatkowa informacja, np. wersja 5G / 4G / rocznik serii">';
            html += '  </div>';
            html += '</div>';
          }
          html += '</div>';
        }
      }

      html += '</div>';

      allegroCompatibilityContainer.innerHTML = html;

      if (inputType === 'TEXT') {
        var compatibilityTextArea = document.getElementById('allegro-compatibility-text');
        if (compatibilityTextArea) {
          var lines = [];
          for (var l = 0; l < currentAllegroCompatibilityList.length; l++) {
            if (currentAllegroCompatibilityList[l] && currentAllegroCompatibilityList[l].text) {
              lines.push(String(currentAllegroCompatibilityList[l].text));
            }
          }
          compatibilityTextArea.value = lines.join('\n');
          compatibilityTextArea.addEventListener('input', function () {
            var splitLines = String(this.value || '').split(/\r\n|\r|\n/);
            var nextItems = [];
            for (var s = 0; s < splitLines.length; s++) {
              var text = String(splitLines[s] || '').trim();
              if (text !== '') {
                nextItems.push({ text: text });
              }
            }
            currentAllegroCompatibilityList = nextItems;
            syncCompatibilityPayload();
          });
        }
      }

      bindCompatibilitySectionInteractions();
      syncCompatibilityPayload();
    }

    function bindCompatibilitySectionInteractions() {
      if (!allegroCompatibilityContainer) {
        return;
      }

      var removeButtons = allegroCompatibilityContainer.querySelectorAll('.js-remove-compatibility-item');
      for (var i = 0; i < removeButtons.length; i++) {
        removeButtons[i].addEventListener('click', function () {
          removeCompatibilityItem(String(this.getAttribute('data-compatibility-id') || ''));
        });
      }

      var additionalInfoInputs = allegroCompatibilityContainer.querySelectorAll('.js-compatibility-additional-info');
      for (var j = 0; j < additionalInfoInputs.length; j++) {
        additionalInfoInputs[j].addEventListener('input', function () {
          updateCompatibilityAdditionalInfo(String(this.getAttribute('data-compatibility-id') || ''), this.value);
        });
      }

      var searchInput = document.getElementById('allegro-compatibility-search');
      var resultsSelect = document.getElementById('allegro-compatibility-results');
      if (searchInput && resultsSelect) {
        searchInput.addEventListener('input', function () {
          var phrase = String(this.value || '').trim();
          if (phrase.length < 3) {
            resultsSelect.innerHTML = '<option value="">Wpisz min. 3 znaki...</option>';
            return;
          }

          resultsSelect.innerHTML = '<option value="">Szukam...</option>';
          var query = '{$baseUrl|escape:"javascript"}?controller=products&action=allegrocompatibleproducts&category_id=' + encodeURIComponent(categoryInput ? categoryInput.value : '') + '&phrase=' + encodeURIComponent(phrase);
          fetch(query, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { return response.json(); })
            .then(function (data) {
              if (data && data.error) {
                resultsSelect.innerHTML = '<option value="">' + escapeHtml(data.error) + '</option>';
                return;
              }

              var items = data && data.items ? data.items : [];
              resultsSelect.innerHTML = renderCompatibilityResults(items);
            })
            .catch(function () {
              resultsSelect.innerHTML = '<option value="">Nie udalo sie pobrac wynikow</option>';
            });
        });

        resultsSelect.addEventListener('change', function () {
          var option = this.options[this.selectedIndex];
          var itemId = option ? String(option.value || '') : '';
          if (!itemId) {
            return;
          }

          addCompatibilityItem({
            id: itemId,
            text: String(option.getAttribute('data-text') || option.text || '')
          });
          this.value = '';
        });
      }
    }

    function loadAllegroCompatibilitySupport() {
      if (!categoryInput || !allegroCompatibilityInfo || !allegroCompatibilityContainer) {
        return;
      }

      var categoryId = String(categoryInput.value || '');
      if (!categoryId) {
        currentAllegroCompatibilitySupport = null;
        currentAllegroCompatibilityList = [];
        allegroCompatibilityInfo.textContent = 'Wybierz kategorie powiazana z Allegro, aby sprawdzic sekcje Pasuje do.';
        renderCompatibilitySection();
        return;
      }

      allegroCompatibilityInfo.textContent = 'Sprawdzam konfiguracje Pasuje do dla tej kategorii...';
      var url = '{$baseUrl|escape:"javascript"}?controller=products&action=allegrocompatibilitysupport&category_id=' + encodeURIComponent(categoryId) + '&include_values=1';
      if (productId) {
        url += '&id=' + encodeURIComponent(productId);
      }

      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (data && data.error) {
            currentAllegroCompatibilitySupport = null;
            currentAllegroCompatibilityList = [];
            allegroCompatibilityInfo.textContent = data.error;
            renderCompatibilitySection();
            return;
          }

          currentAllegroCompatibilitySupport = data && data.support ? data.support : null;
          currentAllegroCompatibilityList = data && data.values && Array.isArray(data.values.items) ? data.values.items.slice() : [];

          if (!currentAllegroCompatibilitySupport) {
            allegroCompatibilityInfo.textContent = 'Ta kategoria nie wspiera sekcji Pasuje do.';
          } else {
            allegroCompatibilityInfo.textContent = 'Sekcja Pasuje do jest dostepna dla tej kategorii.';
          }

          renderCompatibilitySection();
        })
        .catch(function () {
          currentAllegroCompatibilitySupport = null;
          currentAllegroCompatibilityList = [];
          allegroCompatibilityInfo.textContent = 'Nie udalo sie sprawdzic sekcji Pasuje do.';
          renderCompatibilitySection();
        });
    }

    var copySearchTimer = null;
    var relatedSearchTimer = null;
    var derivedSearchTimer = null;

    function renderCopyProducts(items) {
      if (!copyProductSelect) {
        return;
      }

      var html = '';
      for (var i = 0; i < items.length; i++) {
        var p = items[i] || {};
        var label = '#' + (p.id || '') + ' | ' + (p.sku || '') + ' | ' + (p.product_name || '');
        if (p.category_name) {
          label += ' | ' + p.category_name;
        }
        html += '<option value="' + escapeHtml(p.id) + '">' + escapeHtml(label) + '</option>';
      }
      copyProductSelect.innerHTML = html;
    }

    function searchCopyProducts() {
      if (!copyProductSearch || !copyProductSelect) {
        return;
      }

      var term = String(copyProductSearch.value || '').trim();
      if (term.length < 2) {
        copyProductSelect.innerHTML = '<option value="">Wpisz min. 2 znaki...</option>';
        return;
      }

      var url = '{$baseUrl|escape:"javascript"}?controller=products&action=copyproducts&exclude_id=' + encodeURIComponent(productId || '0') + '&search=' + encodeURIComponent(term);
      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (response) {
          return response.text().then(function (rawText) {
            var parsed = {};
            try {
              parsed = rawText ? JSON.parse(rawText) : {};
            } catch (e) {
              parsed = { error: rawText || ('HTTP ' + response.status) };
            }
            if (!response.ok) {
              throw new Error(parsed && parsed.error ? parsed.error : ('HTTP ' + response.status));
            }
            return parsed;
          });
        })
        .then(function (data) {
          var items = data && data.items ? data.items : [];
          renderCopyProducts(items);
        })
        .catch(function (error) {
          var message = error && error.message ? error.message : 'Blad wyszukiwania';
          copyProductSelect.innerHTML = '<option value="">' + escapeHtml(message) + '</option>';
        });
    }

    function renderRelatedProducts(items) {
      if (!relatedProductSelect) {
        return;
      }

      var html = '<option value="">Wybierz produkt...</option>';
      for (var i = 0; i < items.length; i++) {
        var p = items[i] || {};
        var label = '#' + (p.id || '') + ' | ' + (p.sku || '') + ' | ' + (p.product_name || '');
        if (p.category_name) {
          label += ' | ' + p.category_name;
        }
        if (p.shared_stock_enabled) {
          label += ' | wspolny stan';
        }
        html += '<option value="' + escapeHtml(p.id) + '" data-name="' + escapeHtml(p.product_name || '') + '" data-sku="' + escapeHtml(p.sku || '') + '" data-category="' + escapeHtml(p.category_name || '') + '">' + escapeHtml(label) + '</option>';
      }
      relatedProductSelect.innerHTML = html;
      hideAssignedRelatedProductOptions();
    }

    function selectedRelatedIds() {
      if (!relatedProductsContainer) {
        return [];
      }

      var ids = [];
      var inputs = relatedProductsContainer.querySelectorAll('input[name="related_product_ids[]"]');
      for (var i = 0; i < inputs.length; i++) {
        if (inputs[i].value) {
          ids.push(inputs[i].value);
        }
      }

      return ids;
    }

    function searchRelatedProducts() {
      if (!relatedProductSearch || !relatedProductSelect) {
        return;
      }

      var term = String(relatedProductSearch.value || '').trim();
      if (term.length < 2) {
        relatedProductSelect.innerHTML = '<option value="">Wpisz min. 2 znaki...</option>';
        return;
      }

      var ids = selectedRelatedIds();
      var query = '{$baseUrl|escape:"javascript"}?controller=products&action=relatedproducts&exclude_id=' + encodeURIComponent(productId || '0') + '&search=' + encodeURIComponent(term);
      for (var i = 0; i < ids.length; i++) {
        query += '&exclude_ids[]=' + encodeURIComponent(ids[i]);
      }

      fetch(query, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (response) {
          return response.text().then(function (rawText) {
            var parsed = {};
            try {
              parsed = rawText ? JSON.parse(rawText) : {};
            } catch (e) {
              parsed = { error: rawText || ('HTTP ' + response.status) };
            }
            if (!response.ok) {
              throw new Error(parsed && parsed.error ? parsed.error : ('HTTP ' + response.status));
            }
            return parsed;
          });
        })
        .then(function (data) {
          renderRelatedProducts(data && data.items ? data.items : []);
        })
        .catch(function (error) {
          var message = error && error.message ? error.message : 'Blad wyszukiwania';
          relatedProductSelect.innerHTML = '<option value="">' + escapeHtml(message) + '</option>';
        });
    }

    function addSelectedRelatedProduct() {
      if (!relatedProductSelect || !relatedProductsContainer) {
        return;
      }

      var option = relatedProductSelect.options[relatedProductSelect.selectedIndex];
      var selectedId = option ? String(option.value || '') : '';
      if (selectedId === '') {
        return;
      }

      if (noRelatedProducts) {
        noRelatedProducts.style.display = 'none';
      }

      var card = document.createElement('div');
      card.className = 'col-md-6 related-product-card';
      card.setAttribute('data-product-id', selectedId);

      var name = String(option.getAttribute('data-name') || option.text || '');
      var sku = String(option.getAttribute('data-sku') || '');
      var category = String(option.getAttribute('data-category') || '');

      card.innerHTML = ''
        + '<div class="border rounded p-3 h-100 bg-light-subtle">'
        + '  <div class="d-flex justify-content-between align-items-start gap-2 mb-2">'
        + '    <div>'
        + '      <div class="fw-semibold">' + escapeHtml(name) + '</div>'
        + '      <div class="small text-secondary">#' + escapeHtml(selectedId) + ' | ' + escapeHtml(sku || '-') + '</div>'
        + '    </div>'
        + '    <button type="button" class="btn btn-sm btn-outline-danger remove-related-product">Usun</button>'
        + '  </div>'
        + '  <input type="hidden" name="related_product_ids[]" value="' + escapeHtml(selectedId) + '">'
        + (category ? '<div class="small text-secondary">Kategoria: ' + escapeHtml(category) + '</div>' : '')
        + '</div>';

      relatedProductsContainer.appendChild(card);
      option.hidden = true;
      option.disabled = true;
      relatedProductSelect.value = '';
      bindRelatedProductRemove(card);
    }


    function renderDerivedProducts(items) {
      if (!derivedProductSelect) {
        return;
      }

      var html = '<option value="">Wybierz produkt...</option>';
      for (var i = 0; i < items.length; i++) {
        var p = items[i] || {};
        var label = '#' + (p.id || '') + ' | ' + (p.sku || '') + ' | ' + (p.product_name || '');
        if (p.category_name) {
          label += ' | ' + p.category_name;
        }
        html += '<option value="' + escapeHtml(p.id) + '" data-name="' + escapeHtml(p.product_name || '') + '" data-sku="' + escapeHtml(p.sku || '') + '" data-category="' + escapeHtml(p.category_name || '') + '">' + escapeHtml(label) + '</option>';
      }
      derivedProductSelect.innerHTML = html;
      hideAssignedDerivedProductOptions();
    }

    function selectedDerivedIds() {
      if (!derivedProductsContainer) {
        return [];
      }

      var ids = [];
      var inputs = derivedProductsContainer.querySelectorAll('input[name="derived_source_product_ids[]"]');
      for (var i = 0; i < inputs.length; i++) {
        if (inputs[i].value) {
          ids.push(inputs[i].value);
        }
      }

      return ids;
    }

    function searchDerivedProducts() {
      if (!derivedProductSearch || !derivedProductSelect) {
        return;
      }

      var term = String(derivedProductSearch.value || '').trim();
      if (term.length < 2) {
        derivedProductSelect.innerHTML = '<option value="">Wpisz min. 2 znaki...</option>';
        return;
      }

      var ids = selectedDerivedIds();
      var query = '{$baseUrl|escape:"javascript"}?controller=products&action=derivedproducts&exclude_id=' + encodeURIComponent(productId || '0') + '&search=' + encodeURIComponent(term);
      for (var i = 0; i < ids.length; i++) {
        query += '&exclude_ids[]=' + encodeURIComponent(ids[i]);
      }

      fetch(query, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (response) {
          return response.text().then(function (rawText) {
            var parsed = {};
            try {
              parsed = rawText ? JSON.parse(rawText) : {};
            } catch (e) {
              parsed = { error: rawText || ('HTTP ' + response.status) };
            }
            if (!response.ok) {
              throw new Error(parsed && parsed.error ? parsed.error : ('HTTP ' + response.status));
            }
            return parsed;
          });
        })
        .then(function (data) {
          renderDerivedProducts(data && data.items ? data.items : []);
        })
        .catch(function (error) {
          var message = error && error.message ? error.message : 'Blad wyszukiwania';
          derivedProductSelect.innerHTML = '<option value="">' + escapeHtml(message) + '</option>';
        });
    }

    function addSelectedDerivedProduct() {
      if (!derivedProductSelect || !derivedProductsContainer) {
        return;
      }

      var option = derivedProductSelect.options[derivedProductSelect.selectedIndex];
      var selectedId = option ? String(option.value || '') : '';
      if (selectedId === '') {
        return;
      }

      if (noDerivedProducts) {
        noDerivedProducts.style.display = 'none';
      }

      var card = document.createElement('div');
      card.className = 'col-md-6 derived-product-card';
      card.setAttribute('data-product-id', selectedId);

      var name = String(option.getAttribute('data-name') || option.text || '');
      var sku = String(option.getAttribute('data-sku') || '');
      var category = String(option.getAttribute('data-category') || '');

      card.innerHTML = ''
        + '<div class="border rounded p-3 h-100 bg-light-subtle">'
        + '  <div class="d-flex justify-content-between align-items-start gap-2 mb-2">'
        + '    <div>'
        + '      <div class="fw-semibold">' + escapeHtml(name) + '</div>'
        + '      <div class="small text-secondary">#' + escapeHtml(selectedId) + ' | ' + escapeHtml(sku || '-') + '</div>'
        + '    </div>'
        + '    <button type="button" class="btn btn-sm btn-outline-danger remove-derived-product">Usun</button>'
        + '  </div>'
        + '  <input type="hidden" name="derived_source_product_ids[]" value="' + escapeHtml(selectedId) + '">'
        + (category ? '<div class="small text-secondary">Kategoria: ' + escapeHtml(category) + '</div>' : '')
        + '</div>';

      derivedProductsContainer.appendChild(card);
      option.hidden = true;
      option.disabled = true;
      derivedProductSelect.value = '';
      bindDerivedProductRemove(card);
    }
    function copyParametersFromSelectedProduct() {
      if (!copyProductSelect || !copyProductSelect.value) {
        allegroInfo.textContent = 'Wybierz produkt do kopiowania parametrow.';
        return;
      }

      var sourceId = copyProductSelect.value;
      var url = '{$baseUrl|escape:"javascript"}?controller=products&action=copiedparameters&source_id=' + encodeURIComponent(sourceId);

      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (data && data.error) {
            allegroInfo.textContent = data.error;
            return;
          }

          existingAllegroValues = data && data.values ? data.values : {};
          currentAllegroCompatibilityList = data && data.compatibility_list && Array.isArray(data.compatibility_list.items) ? data.compatibility_list.items.slice() : [];
          renderAllegroParameterFields(currentAllegroItems, existingAllegroValues);
          renderCompatibilitySection();
          allegroInfo.textContent = 'Skopiowano parametry z wybranego produktu.';
        })
        .catch(function () {
          allegroInfo.textContent = 'Nie udalo sie skopiowac parametrow produktu.';
        });
    }
    function loadAllegroParameters() {
      if (!categoryInput || !allegroInfo || !allegroContainer) {
        return;
      }

      var categoryId = categoryInput.value;
      if (!categoryId) {
        allegroInfo.textContent = 'Wybierz kategorie powiazana z Allegro, aby zaladowac parametry.';
        allegroContainer.innerHTML = '';
        return;
      }

      var selected = categoryInput.options[categoryInput.selectedIndex];
      var allegroCategoryId = selected ? (selected.getAttribute('data-allegro-category-id') || '') : '';
      if (!allegroCategoryId) {
        allegroInfo.textContent = 'Ta kategoria nie ma przypisanego Allegro category ID.';
        allegroContainer.innerHTML = '';
        return;
      }

      allegroInfo.textContent = 'Pobieranie parametrow Allegro...';
      var url = '{$baseUrl|escape:"javascript"}?controller=products&action=allegroparameters&category_id=' + encodeURIComponent(categoryId) + '&include_values=1';
      if (productId) {
        url += '&id=' + encodeURIComponent(productId);
      }

      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (data && data.error) {
            allegroInfo.textContent = data.error;
            allegroContainer.innerHTML = '';
            return;
          }

          var items = data && data.items ? data.items : [];
          var values = data && data.values ? data.values : existingAllegroValues;
          renderAllegroParameterFields(items, values);
        })
        .catch(function () {
          allegroInfo.textContent = 'Nie udalo sie pobrac parametrow Allegro.';
          allegroContainer.innerHTML = '';
        });
    }

    if (netInput && grossInput && vatInput) {
      netInput.addEventListener('input', fromNet);
      grossInput.addEventListener('input', fromGross);
      vatInput.addEventListener('input', function () {
        if (document.activeElement === grossInput) {
          fromGross();
        } else {
          fromNet();
        }
      });
    }

    if (copyProductSearch) {
      copyProductSearch.addEventListener('input', function () {
        if (copySearchTimer) {
          clearTimeout(copySearchTimer);
        }
        copySearchTimer = setTimeout(searchCopyProducts, 250);
      });
    }

    if (copyProductButton) {
      copyProductButton.addEventListener('click', copyParametersFromSelectedProduct);
    }

    function loadEmpikParameters() {
      if (!categoryInput || !empikInfo || !empikContainer) {
        return;
      }

      var categoryId = categoryInput.value;
      if (!categoryId) {
        empikInfo.textContent = 'Wybierz kategorie powiazana z Empik, aby zaladowac parametry.';
        empikContainer.innerHTML = '';
        return;
      }

      var selected = categoryInput.options[categoryInput.selectedIndex];
      var empikCategoryId = selected ? (selected.getAttribute('data-empik-category-id') || '') : '';
      if (!empikCategoryId) {
        empikInfo.textContent = 'Ta kategoria nie ma przypisanego Empik category ID.';
        empikContainer.innerHTML = '';
        return;
      }

      empikInfo.textContent = 'Pobieranie parametrow Empik...';
      var url = '{$baseUrl|escape:"javascript"}?controller=products&action=empikparameters&category_id=' + encodeURIComponent(categoryId) + '&include_values=1';
      if (productId) {
        url += '&id=' + encodeURIComponent(productId);
      }

      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (data && data.error) {
            empikInfo.textContent = data.error;
            empikContainer.innerHTML = '';
            return;
          }

          var items = data && data.items ? data.items : [];
          var values = data && data.values ? data.values : existingEmpikValues;
          currentEmpikHiddenKeys = Array.isArray(data && data.hidden_keys) ? data.hidden_keys.slice() : currentEmpikHiddenKeys;
          renderEmpikParameterFields(items, values);
        })
        .catch(function () {
          empikInfo.textContent = 'Nie udalo sie pobrac parametrow Empik.';
          empikContainer.innerHTML = '';
        });
    }

    function loadMediaMarktParameters() {
      if (!categoryInput || !mediamarktInfo || !mediamarktContainer) {
        return;
      }

      var categoryId = categoryInput.value;
      if (!categoryId) {
        mediamarktInfo.textContent = 'Wybierz kategorie powiazana z MediaMarkt, aby zaladowac parametry.';
        mediamarktContainer.innerHTML = '';
        return;
      }

      var selected = categoryInput.options[categoryInput.selectedIndex];
      var mediamarktCategoryId = selected ? (selected.getAttribute('data-mediamarkt-category-id') || '') : '';
      if (!mediamarktCategoryId) {
        mediamarktInfo.textContent = 'Ta kategoria nie ma przypisanego MediaMarkt category ID.';
        mediamarktContainer.innerHTML = '';
        return;
      }

      mediamarktInfo.textContent = 'Pobieranie parametrow MediaMarkt...';
      var url = '{$baseUrl|escape:"javascript"}?controller=products&action=mediamarktparameters&category_id=' + encodeURIComponent(categoryId) + '&include_values=1';
      if (productId) {
        url += '&id=' + encodeURIComponent(productId);
      }

      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (data && data.error) {
            mediamarktInfo.textContent = data.error;
            mediamarktContainer.innerHTML = '';
            return;
          }

          var items = data && data.items ? data.items : [];
          var values = data && data.values ? data.values : existingMediaMarktValues;
          currentMediaMarktHiddenKeys = Array.isArray(data && data.hidden_keys) ? data.hidden_keys.slice() : currentMediaMarktHiddenKeys;
          renderMediaMarktParameterFields(items, values);
        })
        .catch(function () {
          mediamarktInfo.textContent = 'Nie udalo sie pobrac parametrow MediaMarkt.';
          mediamarktContainer.innerHTML = '';
        });
    }


    function loadTemuParameters() {
      if (!categoryInput || !temuInfo || !temuContainer) {
        return;
      }

      var categoryId = categoryInput.value;
      if (!categoryId) {
        temuInfo.textContent = 'Wybierz kategorie powiazana z Temu, aby zaladowac parametry.';
        temuContainer.innerHTML = '';
        return;
      }

      var selected = categoryInput.options[categoryInput.selectedIndex];
      var temuCategoryId = selected ? (selected.getAttribute('data-temu-category-id') || '') : '';
      if (!temuCategoryId) {
        temuInfo.textContent = 'Ta kategoria nie ma przypisanego Temu category ID.';
        temuContainer.innerHTML = '';
        return;
      }

      temuInfo.textContent = 'Pobieranie parametrow Temu...';
      var url = '{$baseUrl|escape:"javascript"}?controller=products&action=temuparameters&category_id=' + encodeURIComponent(categoryId) + '&include_values=1';
      if (productId) {
        url += '&id=' + encodeURIComponent(productId);
      }

      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (data && data.error) {
            temuInfo.textContent = data.error;
            temuContainer.innerHTML = '';
            return;
          }

          var items = data && data.items ? data.items : [];
          var values = data && data.values ? data.values : existingTemuValues;
          renderTemuParameterFields(items, values);
        })
        .catch(function () {
          temuInfo.textContent = 'Nie udalo sie pobrac parametrow Temu.';
          temuContainer.innerHTML = '';
        });
    }

    for (var t = 0; t < tabButtons.length; t++) {
      tabButtons[t].addEventListener('click', function () {
        activateProductTab(this.getAttribute('data-product-tab-trigger'));
      });
    }

    var productNameInput = document.getElementById('product_name');
    if (productNameInput) {
      productNameInput.addEventListener('input', syncSummary);
    }
    if (quantityInput) {
      quantityInput.addEventListener('input', syncSummary);
    }
    if (grossInput) {
      grossInput.addEventListener('input', syncSummary);
    }
    if (skuInput) {
      skuInput.addEventListener('input', syncSummary);
    }
    if (eanInput) {
      eanInput.addEventListener('input', syncSummary);
    }
    if (addCustomFieldRowButton && newCustomFieldsContainer) {
      addCustomFieldRowButton.addEventListener('click', function () {
        var row = document.createElement('div');
        row.className = 'row g-2 new-custom-field-row';
        row.innerHTML = ''
          + '<div class="col-md-5"><input type="text" class="form-control form-control-sm" name="new_custom_field_name[]" placeholder="Np. Marka"></div>'
          + '<div class="col-md-6"><input type="text" class="form-control form-control-sm" name="new_custom_field_value[]" placeholder="Np. Samsung"></div>'
          + '<div class="col-md-1 d-grid"><button type="button" class="btn btn-sm btn-outline-danger remove-custom-field-row">x</button></div>';
        newCustomFieldsContainer.appendChild(row);
        bindCustomFieldRowRemove(row);
      });
    }

    bindCustomFieldRowRemove(document);
    bindAssignedCustomFieldRemove(document);
    bindRelatedProductRemove(document);
    bindDerivedProductRemove(document);
    hideAssignedCustomFieldOptions();
    hideAssignedRelatedProductOptions();
    hideAssignedDerivedProductOptions();

    if (addExistingCustomFieldBtn && existingCustomFieldSelect && assignedCustomFieldsContainer) {
      addExistingCustomFieldBtn.addEventListener('click', function () {
        var option = existingCustomFieldSelect.options[existingCustomFieldSelect.selectedIndex];
        var definitionId = option ? String(option.value || '') : '';
        var name = option ? String(option.getAttribute('data-name') || option.text || '') : '';

        if (definitionId === '') {
          return;
        }

        if (noAssignedCustomFields) {
          noAssignedCustomFields.style.display = 'none';
        }

        var card = document.createElement('div');
        card.className = 'col-md-6 assigned-custom-field-card';
        card.setAttribute('data-definition-id', definitionId);
        card.innerHTML = ''
          + '<div class="border rounded p-3 h-100">'
          + '  <div class="d-flex justify-content-between align-items-center mb-2">'
          + '    <label class="form-label mb-0" for="custom_field_' + escapeHtml(definitionId) + '">' + escapeHtml(name) + '</label>'
          + '    <button type="button" class="btn btn-sm btn-outline-danger remove-assigned-custom-field">Usun</button>'
          + '  </div>'
          + '  <input type="text" class="form-control" id="custom_field_' + escapeHtml(definitionId) + '" name="custom_field_values[' + escapeHtml(definitionId) + ']" value="" placeholder="Wpisz wartosc">'
          + '</div>';
        assignedCustomFieldsContainer.appendChild(card);
        option.hidden = true;
        option.disabled = true;
        existingCustomFieldSelect.value = '';
        bindAssignedCustomFieldRemove(card);
      });
    }

    if (deleteCustomFieldDefinitionBtn && deleteCustomFieldDefinitionSelect) {
      deleteCustomFieldDefinitionBtn.addEventListener('click', function () {
        var definitionId = String(deleteCustomFieldDefinitionSelect.value || '');
        if (definitionId === '') {
          return;
        }

        if (!confirm('Usunac to pole z calego systemu i ze wszystkich produktow?')) {
          return;
        }

        var form = document.createElement('form');
        form.method = 'post';
        form.action = '{$baseUrl|escape:"javascript"}?controller=products&action=deletecustomfielddefinition';

        var idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = definitionId;
        form.appendChild(idInput);

        var productInput = document.createElement('input');
        productInput.type = 'hidden';
        productInput.name = 'product_id';
        productInput.value = productId || '0';
        form.appendChild(productInput);

        document.body.appendChild(form);
        form.submit();
      });
    }

    if (relatedProductSearch) {
      relatedProductSearch.addEventListener('input', function () {
        if (relatedSearchTimer) {
          clearTimeout(relatedSearchTimer);
        }
        relatedSearchTimer = setTimeout(searchRelatedProducts, 250);
      });
    }

    if (addRelatedProductButton) {
      addRelatedProductButton.addEventListener('click', addSelectedRelatedProduct);
    }

    if (derivedProductSearch) {
      derivedProductSearch.addEventListener('input', function () {
        if (derivedSearchTimer) {
          clearTimeout(derivedSearchTimer);
        }
        derivedSearchTimer = setTimeout(searchDerivedProducts, 250);
      });
    }

    if (addDerivedProductButton) {
      addDerivedProductButton.addEventListener('click', addSelectedDerivedProduct);
    }

    if (categoryInput && skuInput) {
      categoryInput.addEventListener('change', function () {
        refreshSku();
        loadAllegroParameters();
        loadAllegroCompatibilitySupport();
        loadEmpikParameters();
        loadMediaMarktParameters();
        loadTemuParameters();
        syncSummary();
      });

      if (!hasAssignedSku && (!skuInput.value || skuInput.value.trim() === '') && categoryInput.value) {
        refreshSku();
      }

      loadAllegroParameters();
      loadAllegroCompatibilitySupport();
      loadEmpikParameters();
      loadMediaMarktParameters();
      loadTemuParameters();
    } else {
      loadAllegroParameters();
      loadAllegroCompatibilitySupport();
      loadEmpikParameters();
      loadMediaMarktParameters();
      loadTemuParameters();
    }

    if (productForm) {
      productForm.addEventListener('submit', function () {
        syncCompatibilityPayload();
      });
    }

    var productGalleryInput = document.getElementById('img');
    var productGalleryDropzone = document.getElementById('productGalleryDropzone');
    var productGalleryFileInput = document.getElementById('productGalleryFileInput');
    var productGalleryGrid = document.getElementById('productGalleryGrid');
    var productGalleryStatus = document.getElementById('productGalleryStatus');
    var productGalleryItems = [];
    var draggedGalleryIndex = -1;
    var contoursInput = document.getElementById('contours');
    var contoursSearch = document.getElementById('contoursSearch');
    var contourPickerResults = document.getElementById('contourPickerResults');
    var contourDirectoryItems = {$contourDirectoriesJson|default:'[]' nofilter};

    function normalizeGalleryUrl(url) {
      return String(url || '').trim();
    }

    function syncGalleryInput() {
      if (!productGalleryInput) {
        return;
      }

      productGalleryInput.value = productGalleryItems.join(' | ');
    }

    function setGalleryStatus(message, isError) {
      if (!productGalleryStatus) {
        return;
      }

      productGalleryStatus.textContent = message;
      productGalleryStatus.classList.toggle('text-danger', !!isError);
      productGalleryStatus.classList.toggle('text-secondary', !isError);
    }

    function moveGalleryItem(fromIndex, toIndex) {
      if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0 || fromIndex >= productGalleryItems.length || toIndex >= productGalleryItems.length) {
        return;
      }

      var moved = productGalleryItems.splice(fromIndex, 1)[0];
      productGalleryItems.splice(toIndex, 0, moved);
      renderGallery();
    }

    function renderGallery() {
      if (!productGalleryGrid) {
        return;
      }

      syncGalleryInput();
      productGalleryGrid.innerHTML = '';

      if (!productGalleryItems.length) {
        var empty = document.createElement('div');
        empty.className = 'product-gallery-empty';
        empty.textContent = 'Brak grafik w galerii produktu.';
        productGalleryGrid.appendChild(empty);
        setGalleryStatus('Kolejnosc miniatur odpowiada kolejnosci zapisanej w produkcie.', false);
        return;
      }

      setGalleryStatus('Masz ' + productGalleryItems.length + ' grafik. Przeciagnij miniatury, aby ustawic kolejnosc.', false);

      productGalleryItems.forEach(function (url, index) {
        var item = document.createElement('div');
        item.className = 'product-gallery-item';
        item.draggable = true;
        item.setAttribute('data-gallery-index', String(index));
        item.innerHTML = ''
          + '<img src="' + escapeHtml(url) + '" alt="" class="product-gallery-thumb">'
          + '<div class="product-gallery-meta">'
          + '  <div class="product-gallery-url">' + escapeHtml(url) + '</div>'
          + '  <div class="product-gallery-actions">'
          + '    <button type="button" class="btn btn-sm btn-outline-secondary js-gallery-move-left"' + (index === 0 ? ' disabled' : '') + '>W lewo</button>'
          + '    <button type="button" class="btn btn-sm btn-outline-secondary js-gallery-move-right"' + (index === productGalleryItems.length - 1 ? ' disabled' : '') + '>W prawo</button>'
          + '    <a href="' + escapeHtml(url) + '" target="_blank" rel="noreferrer" class="btn btn-sm btn-outline-primary">Podglad</a>'
          + '    <button type="button" class="btn btn-sm btn-outline-danger js-gallery-remove">Usun</button>'
          + '  </div>'
          + '</div>';
        productGalleryGrid.appendChild(item);
      });
    }

    function uploadGalleryFiles(files) {
      if (!files || !files.length) {
        return;
      }

      var formData = new FormData();
      for (var i = 0; i < files.length; i++) {
        formData.append('images[]', files[i]);
      }

      setGalleryStatus('Wysylanie grafik...', false);

      fetch('{$productImagesUploadUrl|escape:"javascript"}', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (!data || data.error) {
            setGalleryStatus(data && data.error ? data.error : 'Nie udalo sie przeslac grafik.', true);
            return;
          }

          var items = data.items || [];
          for (var j = 0; j < items.length; j++) {
            if (items[j] && items[j].url) {
              var uploadedUrl = normalizeGalleryUrl(items[j].url);
              if (uploadedUrl && productGalleryItems.indexOf(uploadedUrl) === -1) {
                productGalleryItems.push(uploadedUrl);
              }
            }
          }
          renderGallery();
        })
        .catch(function () {
          setGalleryStatus('Nie udalo sie przeslac grafik.', true);
        });
    }

    if (productGalleryDropzone && productGalleryFileInput) {
      productGalleryDropzone.addEventListener('click', function () {
        productGalleryFileInput.click();
      });

      productGalleryFileInput.addEventListener('change', function () {
        uploadGalleryFiles(productGalleryFileInput.files);
        productGalleryFileInput.value = '';
      });

      ['dragenter', 'dragover'].forEach(function (eventName) {
        productGalleryDropzone.addEventListener(eventName, function (event) {
          event.preventDefault();
          productGalleryDropzone.classList.add('is-dragover');
        });
      });

      ['dragleave', 'drop'].forEach(function (eventName) {
        productGalleryDropzone.addEventListener(eventName, function (event) {
          event.preventDefault();
          productGalleryDropzone.classList.remove('is-dragover');
        });
      });

      productGalleryDropzone.addEventListener('drop', function (event) {
        var files = event.dataTransfer ? event.dataTransfer.files : null;
        uploadGalleryFiles(files);
      });
    }

    if (productGalleryGrid) {
      productGalleryGrid.addEventListener('click', function (event) {
        var card = event.target.closest('.product-gallery-item');
        if (!card) {
          return;
        }

        var index = parseInt(card.getAttribute('data-gallery-index') || '-1', 10);
        if (index < 0) {
          return;
        }

        if (event.target.classList.contains('js-gallery-remove')) {
          productGalleryItems.splice(index, 1);
          renderGallery();
          return;
        }

        if (event.target.classList.contains('js-gallery-move-left')) {
          moveGalleryItem(index, index - 1);
          return;
        }

        if (event.target.classList.contains('js-gallery-move-right')) {
          moveGalleryItem(index, index + 1);
        }
      });

      productGalleryGrid.addEventListener('dragstart', function (event) {
        var card = event.target.closest('.product-gallery-item');
        if (!card) {
          return;
        }

        draggedGalleryIndex = parseInt(card.getAttribute('data-gallery-index') || '-1', 10);
        card.classList.add('dragging');
      });

      productGalleryGrid.addEventListener('dragend', function (event) {
        var card = event.target.closest('.product-gallery-item');
        if (card) {
          card.classList.remove('dragging');
        }
      });

      productGalleryGrid.addEventListener('dragover', function (event) {
        event.preventDefault();
      });

      productGalleryGrid.addEventListener('drop', function (event) {
        event.preventDefault();
        var card = event.target.closest('.product-gallery-item');
        if (!card) {
          return;
        }

        var targetIndex = parseInt(card.getAttribute('data-gallery-index') || '-1', 10);
        if (draggedGalleryIndex >= 0 && targetIndex >= 0) {
          moveGalleryItem(draggedGalleryIndex, targetIndex);
        }
        draggedGalleryIndex = -1;
      });
    }

    try {
      var initialGalleryItems = {$productImagesJson|default:'[]' nofilter};
      if (Array.isArray(initialGalleryItems)) {
        for (var g = 0; g < initialGalleryItems.length; g++) {
          if (normalizeGalleryUrl(initialGalleryItems[g])) {
            productGalleryItems.push(normalizeGalleryUrl(initialGalleryItems[g]));
          }
        }
      }
    } catch (galleryInitError) {
      productGalleryItems = [];
    }

    renderGallery();

    function renderContourOptions() {
      if (!contourPickerResults || !Array.isArray(contourDirectoryItems)) {
        return;
      }

      var query = contoursSearch ? String(contoursSearch.value || '').trim().toLowerCase() : '';
      var selected = contoursInput ? String(contoursInput.value || '') : '';
      var filtered = contourDirectoryItems.filter(function (item) {
        return !query || String(item).toLowerCase().indexOf(query) !== -1;
      });

      contourPickerResults.innerHTML = '';

      if (!filtered.length) {
        var empty = document.createElement('div');
        empty.className = 'small text-secondary p-2';
        empty.textContent = 'Brak obrysow pasujacych do wyszukiwania.';
        contourPickerResults.appendChild(empty);
        return;
      }

      filtered.forEach(function (item) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'contour-picker-option' + (item === selected ? ' active' : '');
        button.setAttribute('data-contour-option', item);
        button.textContent = item;
        contourPickerResults.appendChild(button);
      });
    }

    if (contoursSearch) {
      contoursSearch.addEventListener('input', renderContourOptions);
    }

    if (contourPickerResults) {
      contourPickerResults.addEventListener('click', function (event) {
        var button = event.target.closest('[data-contour-option]');
        if (!button) {
          return;
        }

        var value = String(button.getAttribute('data-contour-option') || '');
        if (contoursInput) {
          contoursInput.value = value;
        }
        if (contoursSearch) {
          contoursSearch.value = value;
        }
        renderContourOptions();
      });
    }

    renderContourOptions();

    activateProductTab('overview');
    syncSummary();
  })();
</script>














