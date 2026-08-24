<main class="app-main">
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="products-page-header">
        <div class="products-page-header-shell">
          <div>
            <div class="products-page-kicker">Lista produktow</div>
            <h3 class="products-page-title">{$contentTitle|escape}</h3>
            <p class="products-page-description">{$pageDescription|escape}</p>
          </div>
          <ol class="breadcrumb products-page-breadcrumb">
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=index">Start</a></li>
            <li class="breadcrumb-item active" aria-current="page">{$breadcrumbCurrent|escape}</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <style>
    .products-page-header {
      padding: 1.25rem 0 1.5rem;
    }

    .products-page-header-shell {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
      padding: 1.35rem 1.5rem;
      border: 1px solid rgba(15, 23, 42, .08);
      border-radius: 1rem;
      background: linear-gradient(135deg, #ffffff 0%, #f5f9ff 100%);
      box-shadow: 0 14px 30px rgba(15, 23, 42, .06);
    }

    .products-page-kicker {
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      margin-bottom: .55rem;
      font-size: .72rem;
      font-weight: 700;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: #0d6efd;
    }

    .products-page-title {
      margin: 0;
      font-size: 1.7rem;
      font-weight: 700;
      color: #1f2937;
    }

    .products-page-description {
      margin: .45rem 0 0;
      max-width: 720px;
      color: #6b7280;
      font-size: .98rem;
      line-height: 1.55;
    }

    .products-page-breadcrumb {
      margin: 0;
      padding: .45rem .7rem;
      border-radius: 999px;
      background: rgba(255, 255, 255, .88);
      border: 1px solid rgba(13, 110, 253, .10);
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, .9);
    }

    .products-page-breadcrumb .breadcrumb-item,
    .products-page-breadcrumb .breadcrumb-item a {
      font-size: .85rem;
      color: #64748b;
      text-decoration: none;
    }

    .products-page-breadcrumb .breadcrumb-item.active {
      color: #0f172a;
      font-weight: 600;
    }

    .products-toolbar-card,
    .products-list-card {
      border: 1px solid rgba(15, 23, 42, .08);
      border-radius: 1rem;
      overflow: hidden;
      box-shadow: 0 14px 28px rgba(15, 23, 42, .05);
    }

    .products-toolbar-card .card-body,
    .products-list-card .card-header {
      padding: 1rem 1.2rem;
    }

    .products-section-title {
      margin: 0;
      font-size: 1.05rem;
      font-weight: 700;
      color: #1f2937;
    }

    .products-section-subtitle {
      margin-top: .25rem;
      color: #6b7280;
      font-size: .88rem;
      line-height: 1.45;
    }

    .products-list-card .card-header {
      background: linear-gradient(180deg, rgba(248, 250, 252, .96) 0%, rgba(255, 255, 255, 1) 100%);
      border-bottom: 1px solid rgba(15, 23, 42, .08);
    }

    .products-total-badge {
      min-width: 3rem;
      padding: .55rem .8rem;
      border-radius: 999px;
      font-size: .9rem;
      font-weight: 700;
      box-shadow: inset 0 -1px 0 rgba(255, 255, 255, .18);
    }

    .csv-help-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 1rem;
      height: 1rem;
      margin-left: .2rem;
      border: 1px solid rgba(13, 110, 253, .28);
      border-radius: 999px;
      background: rgba(13, 110, 253, .08);
      color: #0d6efd;
      font-size: .68rem;
      font-weight: 800;
      line-height: 1;
      cursor: help;
      vertical-align: text-top;
      transition: background-color .15s ease, border-color .15s ease, color .15s ease, box-shadow .15s ease;
    }

    .csv-help-icon:hover,
    .csv-help-icon:focus {
      background: #0d6efd;
      border-color: #0d6efd;
      color: #fff;
      box-shadow: 0 0 0 .16rem rgba(13, 110, 253, .14);
      outline: none;
    }

    .products-table {
      table-layout: auto;
      width: 100%;
    }

    .products-table th,
    .products-table td {
      vertical-align: top;
      padding: .45rem .5rem;
    }

    .products-table th {
      white-space: nowrap;
    }

    .product-sku-cell {
      width: 180px;
      min-width: 180px;
    }

    .product-name-cell {
      width: 520px;
      min-width: 520px;
    }

    .product-compact-cell {
      width: 3%;
      white-space: nowrap;
    }

    .product-actions-cell {
      width: 1%;
      white-space: nowrap;
    }

    .product-price-cell {
      width: 220px;
      min-width: 220px;
      white-space: normal;
      line-height: 1.3;
    }

    .product-quantity-cell {
      width: 118px;
      min-width: 118px;
    }

    .product-sales-cell {
      width: 150px;
      min-width: 150px;
      white-space: normal;
      line-height: 1.25;
    }

    .product-timestamps-cell {
      width: 170px;
      min-width: 170px;
      white-space: normal;
      line-height: 1.22;
    }

    .product-timestamp-stack {
      display: flex;
      flex-direction: column;
      gap: .2rem;
    }

    .product-timestamp-item {
      display: flex;
      flex-direction: column;
      gap: .04rem;
    }

    .product-timestamp-label {
      font-size: .62rem;
      font-weight: 700;
      letter-spacing: .04em;
      text-transform: uppercase;
      color: #6b7280;
    }

    .product-timestamp-value {
      color: #1f2937;
      word-break: break-word;
      font-size: .72rem;
    }

    .product-sku-secondary {
      display: block;
      margin-top: .2rem;
      font-size: .74rem;
      color: #6c757d;
      word-break: break-word;
      white-space: normal;
    }

    .quick-edit-input {
      border-radius: .65rem;
      border: 1px solid rgba(15, 23, 42, .12);
      padding: .38rem .55rem;
      font-size: .88rem;
      background: #fff;
      width: 100%;
      min-width: 0;
    }

    .quick-edit-input:focus {
      outline: none;
      border-color: rgba(13, 110, 253, .5);
      box-shadow: 0 0 0 .18rem rgba(13, 110, 253, .12);
    }

    .quick-edit-number {
      max-width: 88px;
    }

    .quick-edit-actions {
      display: flex;
      justify-content: flex-end;
      gap: .35rem;
      flex-wrap: wrap;
    }

    .quick-edit-save.is-saving {
      opacity: .7;
      pointer-events: none;
    }

    .quick-edit-status {
      display: block;
      margin-top: .3rem;
      font-size: .74rem;
      color: #6c757d;
      min-height: 1rem;
    }

    .quick-edit-status.is-error {
      color: #b42318;
    }

    .quick-edit-status.is-success {
      color: #027a48;
    }

    .products-category-filter {
      min-width: 180px;
    }

    .products-category-filter-search {
      margin-bottom: .35rem;
    }

    .products-category-filter-search .form-control {
      border-radius: .65rem;
    }

    .products-category-filter select {
      min-height: 120px;
      border-radius: .75rem;
    }

    .products-filter-hint {
      display: block;
      margin-top: .3rem;
      font-size: .72rem;
      color: #6b7280;
      line-height: 1.35;
    }

    .js-quick-edit-row {
      transition: background-color .24s ease, box-shadow .24s ease;
    }

    .js-quick-edit-row.quick-edit-row-success > td {
      background: rgba(18, 183, 106, .13) !important;
    }

    .js-quick-edit-row.quick-edit-row-error > td {
      background: rgba(240, 68, 56, .12) !important;
    }

    .product-relation-stack {
      display: flex;
      flex-direction: column;
      gap: .35rem;
      margin-top: .45rem;
    }

    .product-relation-item {
      display: flex;
      align-items: flex-start;
      gap: .45rem;
      padding: .35rem .5rem;
      border-radius: .6rem;
      background: rgba(15, 23, 42, .04);
      line-height: 1.35;
    }

    .product-relation-label {
      flex: 0 0 auto;
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .04em;
      padding-top: .05rem;
      color: #52606d;
    }

    .product-relation-item.shared .product-relation-label {
      color: #9a6700;
    }

    .product-relation-item.derived .product-relation-label {
      color: #0b7285;
    }

    .product-relation-value {
      font-size: .82rem;
      color: #4b5563;
    }

    .product-dimensions-stack {
      display: flex;
      flex-direction: column;
      gap: .24rem;
    }

    .product-contours-inline {
      display: block;
      font-size: .71rem;
      color: #64748b;
      word-break: break-word;
      line-height: 1.25;
    }

    .product-sku-meta {
      display: flex;
      align-items: flex-start;
      gap: .55rem;
      padding: .2rem;
      border-radius: .7rem;
      cursor: pointer;
      transition: background-color .18s ease, box-shadow .18s ease;
    }

    .product-sku-meta:hover {
      background: rgba(15, 23, 42, .04);
    }

    .product-sku-main {
      min-width: 0;
    }

    .product-checkbox {
      appearance: none;
      -webkit-appearance: none;
      width: 1.02rem;
      height: 1.02rem;
      margin: 0;
      border-radius: .32rem;
      border: 1px solid rgba(15, 23, 42, .22);
      background: #fff;
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, .92);
      cursor: pointer;
      position: relative;
      transition: border-color .16s ease, background-color .16s ease, box-shadow .16s ease, transform .16s ease;
      flex: 0 0 auto;
    }

    .product-checkbox:hover {
      border-color: rgba(37, 99, 235, .5);
      box-shadow: 0 0 0 .16rem rgba(37, 99, 235, .10);
    }

    .product-checkbox:checked {
      background: linear-gradient(180deg, #2563eb 0%, #1d4ed8 100%);
      border-color: #1d4ed8;
      box-shadow: 0 0 0 .16rem rgba(37, 99, 235, .12);
    }

    .product-checkbox:checked::after {
      content: "";
      position: absolute;
      left: .31rem;
      top: .12rem;
      width: .22rem;
      height: .46rem;
      border: solid #fff;
      border-width: 0 2px 2px 0;
      transform: rotate(45deg);
    }

    .product-checkbox:focus-visible {
      outline: none;
      box-shadow: 0 0 0 .2rem rgba(37, 99, 235, .18);
    }

    .product-select-all {
      display: flex;
      align-items: center;
      gap: .45rem;
      min-width: 0;
    }

    .product-select-all-label {
      font-size: .74rem;
      font-weight: 600;
      color: #64748b;
      user-select: none;
    }

    .product-id-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 2rem;
      padding: .14rem .38rem;
      border-radius: 999px;
      background: rgba(15, 23, 42, .08);
      color: #334155;
      font-size: .72rem;
      font-weight: 700;
    }

    .product-price-stack {
      display: flex;
      flex-direction: column;
      gap: .38rem;
    }

    .product-price-main {
      display: flex;
      flex-direction: column;
      gap: .12rem;
    }

    .product-price-widget {
      display: block;
    }

    .product-price-toggle {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      padding: .16rem .38rem;
      border-radius: 999px;
      background: rgba(15, 23, 42, .06);
      border: 1px solid rgba(15, 23, 42, .08);
      color: #0f172a;
      cursor: pointer;
      list-style: none;
      user-select: none;
      font-size: .72rem;
      font-weight: 700;
    }

    .product-price-toggle::-webkit-details-marker {
      display: none;
    }

    .product-price-toggle:hover {
      background: rgba(15, 23, 42, .09);
      border-color: rgba(15, 23, 42, .14);
    }

    .product-price-summary-main {
      color: #0f172a;
      font-weight: 800;
    }

    .product-price-summary-net {
      color: #64748b;
      font-size: .67rem;
      font-weight: 600;
    }

    .product-price-toggle-caret {
      color: #64748b;
      font-size: .6rem;
      transition: transform .18s ease;
    }

    .product-price-widget[open] .product-price-toggle-caret {
      transform: rotate(180deg);
    }

    .product-price-details {
      margin-top: .32rem;
      padding-left: .15rem;
    }

    .product-allegro-widget {
      margin-top: .45rem;
    }

    .product-allegro-toggle {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      padding: .16rem .4rem;
      border-radius: 999px;
      background: rgba(255, 90, 0, .10);
      border: 1px solid rgba(255, 90, 0, .18);
      color: #9a3412;
      font-size: .7rem;
      font-weight: 700;
      cursor: pointer;
      list-style: none;
      user-select: none;
    }

    .product-allegro-toggle::-webkit-details-marker {
      display: none;
    }

    .product-allegro-toggle:hover {
      background: rgba(255, 90, 0, .14);
      border-color: rgba(255, 90, 0, .28);
    }

    .product-allegro-toggle-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 1.15rem;
      height: 1.15rem;
      border-radius: 999px;
      background: #ff5a00;
      color: #fff;
      font-size: .68rem;
      font-weight: 800;
      line-height: 1;
    }

    .product-allegro-toggle-count {
      color: #0f172a;
      font-weight: 800;
    }

    .product-allegro-toggle-caret {
      color: #64748b;
      font-size: .62rem;
      transition: transform .18s ease;
    }

    .product-allegro-widget[open] .product-allegro-toggle-caret {
      transform: rotate(180deg);
    }

    .product-allegro-links {
      margin-top: .45rem;
      display: flex;
      flex-direction: column;
      gap: .24rem;
    }

    .product-allegro-links a {
      text-decoration: none;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .45rem;
      padding: .24rem .38rem;
      border-radius: .55rem;
      background: linear-gradient(180deg, rgba(248, 250, 252, .98) 0%, rgba(241, 245, 249, .96) 100%);
      border: 1px solid rgba(15, 23, 42, .08);
      color: #1f2937;
      transition: background-color .18s ease, border-color .18s ease, transform .18s ease, box-shadow .18s ease;
    }

    .product-allegro-links a:hover {
      background: linear-gradient(180deg, rgba(239, 246, 255, .98) 0%, rgba(219, 234, 254, .96) 100%);
      border-color: rgba(59, 130, 246, .24);
      box-shadow: 0 8px 18px rgba(37, 99, 235, .12);
      transform: translateY(-1px);
    }

    .product-allegro-links-empty {
      display: inline-flex;
      align-items: center;
      padding: .18rem .38rem;
      border-radius: 999px;
      background: rgba(148, 163, 184, .12);
      color: #64748b;
      font-size: .68rem;
      line-height: 1.2;
    }

    .product-sales-stack {
      display: flex;
      flex-direction: column;
      gap: .35rem;
    }

    .product-sales-metric {
      display: flex;
      flex-direction: column;
      gap: .08rem;
      padding: .35rem .45rem;
      border-radius: .5rem;
      background: rgba(248, 250, 252, .95);
      border: 1px solid rgba(15, 23, 42, .08);
    }

    .product-sales-label {
      color: #64748b;
      font-size: .66rem;
      font-weight: 700;
      text-transform: uppercase;
    }

    .product-sales-value {
      color: #0f172a;
      font-size: .82rem;
      font-weight: 800;
    }

    .product-sales-value.muted {
      color: #dc2626;
    }

    .product-allegro-account {
      display: flex;
      flex-direction: column;
      min-width: 0;
    }

    .product-allegro-account-name {
      font-size: .71rem;
      font-weight: 700;
      color: #0f172a;
      line-height: 1.2;
      word-break: break-word;
    }

    .product-allegro-count {
      flex: 0 0 auto;
      min-width: 2rem;
      padding: .12rem .38rem;
      border-radius: 999px;
      background: #0f172a;
      color: #fff;
      font-size: .68rem;
      font-weight: 700;
      text-align: center;
    }

    @media (max-width: 1400px) {
      .product-name-cell {
        min-width: 180px;
      }

      .quick-edit-input {
        font-size: .82rem;
        padding: .32rem .45rem;
      }
    }

    @media (max-width: 767.98px) {
      .products-page-header-shell {
        padding: 1rem;
        flex-direction: column;
      }

      .products-page-title {
        font-size: 1.35rem;
      }

      .products-page-breadcrumb {
        align-self: flex-start;
      }

      .products-toolbar-card .card-body,
      .products-list-card .card-header {
        padding: .9rem 1rem;
      }
    }
  </style>

  <div class="app-content">
    <div class="container-fluid">
      {if $flashSuccess}<div class="alert alert-success">{$flashSuccess|escape}</div>{/if}
      {if $flashError}<div class="alert alert-danger">{$flashError|escape}</div>{/if}

      <div class="card mb-4 products-toolbar-card">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <h3 class="products-section-title">Filtry i sortowanie</h3>
            <div class="products-section-subtitle">Klikaj naglowki kolumn, aby przelaczac: ASC, DESC, reset.</div>
          </div>
          <div class="d-flex gap-2">
            <a href="{$csvImportUrl|escape}" class="btn btn-outline-primary">Import CSV</a>
            <a href="{$baseUrl}?controller=products&action=contoursmanager" class="btn btn-outline-dark">Manager obrysow</a>
            <a href="{$baseUrl}?controller=products&action=create&return_url={$currentListUrl|escape:'url'}" class="btn btn-success">Dodaj produkt</a>
         
          </div>
        </div>
      </div>

      <div class="card products-list-card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h3 class="products-section-title">Wszystkie produkty</h3>
            <div class="products-section-subtitle">Lacznie {$totalProducts} produktow, strona {$page} z {$totalPages}</div>
          </div>
          <span class="badge text-bg-primary products-total-badge">{$totalProducts}</span>
        </div>
        
        <!-- Panel akcji masowych -->
        <div id="bulkActionsPanel" class="card-body bg-light border-bottom">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="text-secondary">
              <strong id="bulkSelectedCount">0</strong> produktów zaznaczonych
            </div>
            <div class="btn-group" role="group">
              <button type="button" id="bulkCopyBtn" class="btn btn-sm btn-outline-success" title="Skopiuj wszystkie zaznaczone produkty">
                <i class="bi bi-files"></i> Kopiuj zaznaczone
              </button>
              <button type="button" id="bulkCopySharedBtn" class="btn btn-sm btn-outline-success" title="Skopiuj zaznaczone i od razu powiaz jako wspolne">
                <i class="bi bi-copy"></i> Kopiuj jako wspolny
              </button>
              <button type="button" id="bulkSharedBtn" class="btn btn-sm btn-outline-warning" title="Polacz zaznaczone produkty jako wspolne">
                <i class="bi bi-diagram-3"></i> Polacz jako wspolne
              </button>
              <button type="button" id="bulkCategoryBtn" class="btn btn-sm btn-outline-primary" title="Przypisz kategorię">
                <i class="bi bi-tag"></i> Zmień kategorię
              </button>
              <button type="button" id="bulkExportBtn" class="btn btn-sm btn-outline-info" title="Eksportuj zaznaczone do CSV">
                <i class="bi bi-file-earmark-spreadsheet"></i> Eksport CSV
              </button>
              <button type="button" id="bulkDeleteBtn" class="btn btn-sm btn-outline-danger" title="Usuń zaznaczone - wymaga potwierdzenia">
                <i class="bi bi-trash"></i> Usuń zaznaczone
              </button>
              <button type="button" id="bulkCancelBtn" class="btn btn-sm btn-outline-secondary">Anuluj</button>
            </div>
          </div>
        </div>

        <div class="card-body p-0">
          <div class="table-responsive">
            <form method="get" action="{$baseUrl}" id="productsFiltersForm" data-loader-label="Ladowanie listy produktow...">
              <input type="hidden" name="controller" value="products">
              <input type="hidden" name="action" value="index">
              <input type="hidden" name="sort_by" value="{$sortBy|default:''|escape}">
              <input type="hidden" name="sort_dir" value="{$sortDir|default:''|escape}">
              <input type="hidden" name="filter_global" value="{$filters.global|default:''|escape}">
              <input type="hidden" name="filter_category_id" id="filterCategoryIdSerialized" value="{$filters.category_id|default:''|escape}">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 px-3 pt-3">
                  <div class="d-flex align-items-center gap-2">
                    <span class="small text-secondary">Na strone</span>
                    <select name="per_page" class="form-select form-select-sm" style="width:120px;">
                    <option value="50"{if $perPage == 50} selected{/if}>50</option>
                    <option value="100"{if $perPage == 100} selected{/if}>100</option>
                    <option value="200"{if $perPage == 200} selected{/if}>200</option>
                    <option value="500"{if $perPage == 500} selected{/if}>500</option>
                    <option value="1000"{if $perPage == 1000} selected{/if}>1000</option>
                    </select>
                  </div>
                  <div class="small text-secondary">
                    Negacja filtrow: wpisz `!tekst`, np. `!9D` oznacza "nie zawiera 9D".
                    W nazwie: spacja = AND, `|` = OR, `!` = wyklucz, cudzyslow = fraza.
                  </div>
                {if $totalPages > 1}
                  {assign var=prevPage value=$page-1}
                  {assign var=nextPage value=$page+1}
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    <a class="btn btn-sm btn-outline-secondary{if $page <= 1} disabled{/if}" href="{$baseUrl}?controller=products&action=index&page=1&per_page={$perPage}&filter_id={$filters.id|escape:'url'}&filter_global={$filters.global|escape:'url'}&filter_sku={$filters.sku|escape:'url'}&filter_product_name={$filters.product_name|escape:'url'}&filter_category_id={$filters.category_id|escape:'url'}&filter_quantity={$filters.quantity|escape:'url'}&filter_localization={$filters.localization|escape:'url'}&filter_with_glass={$filters.with_glass|escape:'url'}&filter_contours={$filters.contours|default:''|escape:'url'}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}">Pierwsza</a>
                    <a class="btn btn-sm btn-outline-secondary{if $page <= 1} disabled{/if}" href="{$baseUrl}?controller=products&action=index&page={$prevPage}&per_page={$perPage}&filter_id={$filters.id|escape:'url'}&filter_global={$filters.global|escape:'url'}&filter_sku={$filters.sku|escape:'url'}&filter_product_name={$filters.product_name|escape:'url'}&filter_category_id={$filters.category_id|escape:'url'}&filter_quantity={$filters.quantity|escape:'url'}&filter_localization={$filters.localization|escape:'url'}&filter_with_glass={$filters.with_glass|escape:'url'}&filter_contours={$filters.contours|default:''|escape:'url'}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}">Poprzednia</a>
                    {foreach $pageWindow as $pageItem}
                      {if $pageItem.type eq 'page'}
                        <a class="btn btn-sm {if $pageItem.is_current}btn-primary{else}btn-outline-secondary{/if}" href="{$baseUrl}?controller=products&action=index&page={$pageItem.value}&per_page={$perPage}&filter_id={$filters.id|escape:'url'}&filter_global={$filters.global|escape:'url'}&filter_sku={$filters.sku|escape:'url'}&filter_product_name={$filters.product_name|escape:'url'}&filter_category_id={$filters.category_id|escape:'url'}&filter_quantity={$filters.quantity|escape:'url'}&filter_localization={$filters.localization|escape:'url'}&filter_with_glass={$filters.with_glass|escape:'url'}&filter_contours={$filters.contours|default:''|escape:'url'}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}">{$pageItem.value}</a>
                      {else}
                        <span class="px-1 text-secondary">...</span>
                      {/if}
                    {/foreach}
                    <a class="btn btn-sm btn-outline-secondary{if $page >= $totalPages} disabled{/if}" href="{$baseUrl}?controller=products&action=index&page={$nextPage}&per_page={$perPage}&filter_id={$filters.id|escape:'url'}&filter_global={$filters.global|escape:'url'}&filter_sku={$filters.sku|escape:'url'}&filter_product_name={$filters.product_name|escape:'url'}&filter_category_id={$filters.category_id|escape:'url'}&filter_quantity={$filters.quantity|escape:'url'}&filter_localization={$filters.localization|escape:'url'}&filter_with_glass={$filters.with_glass|escape:'url'}&filter_contours={$filters.contours|default:''|escape:'url'}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}">Nastepna</a>
                    <a class="btn btn-sm btn-outline-secondary{if $page >= $totalPages} disabled{/if}" href="{$baseUrl}?controller=products&action=index&page={$totalPages}&per_page={$perPage}&filter_id={$filters.id|escape:'url'}&filter_global={$filters.global|escape:'url'}&filter_sku={$filters.sku|escape:'url'}&filter_product_name={$filters.product_name|escape:'url'}&filter_category_id={$filters.category_id|escape:'url'}&filter_quantity={$filters.quantity|escape:'url'}&filter_localization={$filters.localization|escape:'url'}&filter_with_glass={$filters.with_glass|escape:'url'}&filter_contours={$filters.contours|default:''|escape:'url'}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}">Ostatnia</a>
                    <div class="d-flex align-items-center gap-2 ms-2">
                      <span class="small text-secondary">Przejdz do</span>
                      <input type="number" min="1" max="{$totalPages|escape}" name="page" value="{$page|escape}" class="form-control form-control-sm" style="width:110px;">
                    </div>
                  </div>
                {else}
                  <input type="hidden" name="page" value="1">
                {/if}
              </div>

              <table class="table table-sm table-striped table-hover table-bordered align-middle mb-0 products-table">
                <thead class="table-light">
                  <tr>
                    <th class="product-sku-cell">
                      <div class="d-flex flex-column gap-1">
                        <a href="{$sortUrls.id|escape}" class="link-dark text-decoration-none">ID {if $sortIndicators.id eq 'asc'}&uarr;{elseif $sortIndicators.id eq 'desc'}&darr;{else}&harr;{/if}</a>
                        <a href="{$sortUrls.sku|escape}" class="link-dark text-decoration-none">SKU {if $sortIndicators.sku eq 'asc'}&uarr;{elseif $sortIndicators.sku eq 'desc'}&darr;{else}&harr;{/if}</a>
                      </div>
                    </th>
                    <th class="product-name-cell">
                      <a href="{$sortUrls.product_name|escape}" class="link-dark text-decoration-none">Nazwa {if $sortIndicators.product_name eq 'asc'}&uarr;{elseif $sortIndicators.product_name eq 'desc'}&darr;{else}&harr;{/if}</a>
                    </th>
                    <th style="width: 100px; min-width: 100px;">
                      <a href="{$sortUrls.category|escape}" class="link-dark text-decoration-none">Kategoria {if $sortIndicators.category eq 'asc'}&uarr;{elseif $sortIndicators.category eq 'desc'}&darr;{else}&harr;{/if}</a>
                    </th>
                    <th class="product-quantity-cell"><a href="{$sortUrls.quantity|escape}" class="link-dark text-decoration-none">Ilosc {if $sortIndicators.quantity eq 'asc'}&uarr;{elseif $sortIndicators.quantity eq 'desc'}&darr;{else}&harr;{/if}</a></th>
                    <th style="width: 100px; min-width: 100px;"><a href="{$sortUrls.localization|escape}" class="link-dark text-decoration-none">Lokalizacja {if $sortIndicators.localization eq 'asc'}&uarr;{elseif $sortIndicators.localization eq 'desc'}&darr;{else}&harr;{/if}</a></th>
                    <th class="product-sales-cell">
                      <div class="d-flex flex-column gap-1">
                        <a href="{$sortUrls.active_auction_count|escape}" class="link-dark text-decoration-none">Aukcje {if $sortIndicators.active_auction_count eq 'asc'}&uarr;{elseif $sortIndicators.active_auction_count eq 'desc'}&darr;{else}&harr;{/if}</a>
                        <a href="{$sortUrls.last_sale_date|escape}" class="link-dark text-decoration-none">Ost. sprzedaz {if $sortIndicators.last_sale_date eq 'asc'}&uarr;{elseif $sortIndicators.last_sale_date eq 'desc'}&darr;{else}&harr;{/if}</a>
                      </div>
                    </th>
                    <th style="width: 140px; min-width: 140px;">Wymiary / obrys</th>
                    <th class="product-compact-cell">Zdjecie</th>
                    <th class="product-price-cell">Cena / daty</th>
                    <th class="text-end product-actions-cell">Akcje</th>
                  </tr>
                  <tr>
                    <th>
                      <div class="d-flex flex-column gap-2">
                        <div class="form-check mb-0">
                          <label class="product-select-all" for="selectAllProducts">
                            <input type="checkbox" id="selectAllProducts" class="product-checkbox">
                            <span class="product-select-all-label">zaznacz</span>
                          </label>
                        </div>
                        <input type="text" name="filter_id" value="{$filters.id|default:''|escape}" class="form-control form-control-sm" placeholder="ID, np. 15">
                        <input type="text" name="filter_sku" value="{$filters.sku|default:''|escape}" class="form-control form-control-sm" placeholder="fragment SKU">
                      </div>
                    </th>
                    <th>
                      <input type="text" name="filter_product_name" value="{$filters.product_name|default:''|escape}" class="form-control form-control-sm" placeholder='np. damska meska | "szklo hartowane" !czarna'>
                      <span class="products-filter-hint">Spacja = AND, `|` = OR, `!` = bez frazy.</span>
                    </th>
                    <th>
                      <div class="products-category-filter">
                        <div class="products-category-filter-search">
                          <input type="text" id="filterCategorySearch" class="form-control form-control-sm" placeholder="szukaj kategorii">
                        </div>
                        <select id="filterCategoryIdsUi" class="form-select form-select-sm" multiple>
                        {foreach $categories as $category}
                          <option value="{$category.id}" data-category-label="{$category.name|lower|escape}"{if in_array($category.id, $selectedCategoryIds|default:[])} selected{/if}>{$category.name|escape}</option>
                        {/foreach}
                        </select>
                        <span class="products-filter-hint">Mozesz zaznaczyc wiele kategorii jednoczesnie.</span>
                      </div>
                    </th>
                    <th class="product-quantity-cell"><input type="text" name="filter_quantity" value="{$filters.quantity|default:''|escape}" class="form-control form-control-sm" placeholder="np. 10 lub 10-50"></th>
                    <th><input type="text" name="filter_localization" value="{$filters.localization|default:''|escape}" class="form-control form-control-sm" placeholder="lokalizacja"></th>
                    <th>
                      <select name="filter_with_glass" class="form-select form-select-sm">
                        <option value=""{if $withGlassFilter eq ''} selected{/if}>szklo: wszystkie</option>
                        <option value="1"{if $withGlassFilter eq '1'} selected{/if}>produkty ze szklem</option>
                        <option value="0"{if $withGlassFilter eq '0'} selected{/if}>produkty bez szkła</option>
                      </select>
                      <select name="filter_contours" class="form-select form-select-sm mt-2">
                        <option value=""{if $contoursFilter eq ''} selected{/if}>obrys: wszystkie</option>
                        <option value="1"{if $contoursFilter eq '1'} selected{/if}>ma obrys</option>
                        <option value="0"{if $contoursFilter eq '0'} selected{/if}>nie ma obrysu</option>
                      </select>
                    </th>
                    <th class="text-end" colspan="5">
                    <a href="{$clearFiltersUrl|escape}" class="btn btn-sm btn-warning ">Wyczysc filtry</a><button type="submit" class="btn btn-sm btn-primary" style="margin-left:10px;">Filtruj</button></th>
                  </tr>
                </thead>
                <tbody>
                  {if $products}
                    {foreach $products as $product}
                      <tr class="js-quick-edit-row" data-product-id="{$product.id}">
                        <td class="product-sku-cell">
                          <div class="product-sku-meta js-product-select-toggle" role="button" tabindex="0" aria-label="Zaznacz produkt {$product.sku|escape}">
                            <input type="checkbox" class="js-export-checkbox product-checkbox mt-1" value="{$product.id}">
                            <div class="product-sku-main">
                              <span class="product-id-badge">#{$product.id}</span>
                              <div class="mt-1"><span class="badge text-bg-secondary">{$product.sku|escape}</span></div>
                              {if $product.custom_fields.old_sku|default:'' !== ''}
                                <span class="product-sku-secondary">OLD_SKU: {$product.custom_fields.old_sku|escape}</span>
                              {/if}
                            </div>
                          </div>
                        </td>
                        <td class="product-name-cell" style="white-space: normal;">
                          <input type="text" class="quick-edit-input js-quick-edit-field" data-field="product_name" value="{$product.product_name|escape}" aria-label="Nazwa produktu">
                          {assign var="hasSharedPeers" value=false}
                          {if $product.shared_stock_enabled|default:false}
                            {foreach $product.shared_stock_group_members|default:[] as $member}
                              {if $member.id neq $product.id}
                                {assign var="hasSharedPeers" value=true}
                              {/if}
                            {/foreach}
                          {/if}
                          {if $hasSharedPeers || ($product.derived_stock_enabled|default:false) || ($product.has_derived_dependents|default:false)}
                            <div class="product-relation-stack">
                              {if $hasSharedPeers}
                                <div class="product-relation-item shared">
                                  <div class="product-relation-label">Wspolny</div>
                                  <div class="product-relation-value">
                                    {assign var="sharedSeparator" value=""}
                                    {foreach $product.shared_stock_group_members|default:[] as $member}
                                      {if $member.id neq $product.id}
                                        {$sharedSeparator}{$member.product_name|escape}
                                        {assign var="sharedSeparator" value=", "}
                                      {/if}
                                    {/foreach}
                                  </div>
                                </div>
                              {/if}
                              {if $product.has_derived_dependents|default:false}
                                <div class="product-relation-item derived">
                                  <div class="product-relation-label">Powiazanie</div>
                                  <div class="product-relation-value">Ma pochodne</div>
                                </div>
                              {/if}
                              {if $product.derived_stock_enabled|default:false}
                                <div class="product-relation-item derived">
                                  <div class="product-relation-label">Pochodny</div>
                                  <div class="product-relation-value">
                                    {assign var="derivedSeparator" value=""}
                                    {foreach $product.derived_stock_sources|default:[] as $source}
                                      {$derivedSeparator}{$source.product_name|escape}
                                      {assign var="derivedSeparator" value=", "}
                                    {/foreach}
                                  </div>
                                </div>
                              {/if}
                            </div>
                          {/if}
                          <div class="small text-secondary mt-2">{$product.description|default:'-'|truncate:80|escape}</div>
                          <span class="quick-edit-status js-quick-edit-status"></span>
                        </td>
                        <td >{if $product.category_name}<span class="badge text-bg-info">{$product.category_name|escape}</span>{else}-{/if}</td>
                        <td class="product-quantity-cell">
                          <input type="number" min="0" class="quick-edit-input quick-edit-number js-quick-edit-field" data-field="quantity" value="{$product.quantity|escape}" aria-label="Ilosc">
                        </td>
                        <td>
                          <input type="text" class="quick-edit-input js-quick-edit-field" data-field="localization" value="{$product.localization|default:''|escape}" aria-label="Lokalizacja">
                          <details class="product-allegro-widget">
                            <summary class="product-allegro-toggle">
                              <span class="product-allegro-toggle-icon">A</span>
                              <span>Allegro</span>
                              <span class="product-allegro-toggle-count">{$product.allegro_offer_total|default:0}</span>
                              <span class="product-allegro-toggle-caret">▼</span>
                            </summary>
                            {if $product.allegro_offers_by_account|default:[]}
                              <div class="product-allegro-links">
                                {foreach $product.allegro_offers_by_account as $accountOffer}
                                  <a href="{$accountOffer.url|escape}">
                                    <span class="product-allegro-account">
                                      <span class="product-allegro-account-name">{$accountOffer.account_name|default:'Konto Allegro'|escape}</span>
                                    </span>
                                    <span class="product-allegro-count">{$accountOffer.count|default:0}</span>
                                  </a>
                                {/foreach}
                              </div>
                            {else}
                              <div class="product-allegro-links">
                                <span class="product-allegro-links-empty">Brak aktywnych ofert Allegro</span>
                              </div>
                            {/if}
                          </details>
                        </td>
                        <td class="product-sales-cell">
                          <div class="product-sales-stack">
                            <div class="product-sales-metric">
                              <span class="product-sales-label">Wystawione aukcje</span>
                              <span class="product-sales-value">{$product.active_auction_count|default:0}</span>
                            </div>
                            <div class="product-sales-metric">
                              <span class="product-sales-label">Ostatnia sprzedaz</span>
                              {if $product.last_sale_date|default:'' !== ''}
                                <span class="product-sales-value">{$product.last_sale_date|escape}</span>
                              {else}
                                <span class="product-sales-value muted">Brak</span>
                              {/if}
                            </div>
                          </div>
                        </td>
                        <td>
                          <div class="product-dimensions-stack">
                            <input type="text" class="quick-edit-input js-quick-edit-field" data-field="dimensions" value="{$product.dimensions|default:''|escape}" aria-label="Wymiary">
                            <span class="product-contours-inline">Obrys: {$product.contours|default:'-'|escape}</span>
                          </div>
                        </td>
                        <td class="product-compact-cell">{if $product.img}<a href="{$product.img|regex_replace:'/\\s*\\|\\s*.*/':''|escape}" target="_blank" rel="noreferrer">Podglad</a>{else}-{/if}</td>
                        <td class="product-price-cell">
                          <div class="product-price-stack">
                            <details class="product-price-widget">
                              <summary class="product-price-toggle">
                                <span class="product-price-summary-main">B: {$product.price_gross}</span>
                                <span class="product-price-summary-net">N: {$product.price_net}</span>
                                <span class="product-price-toggle-caret">▼</span>
                              </summary>
                              <div class="product-price-details">
                                <div class="product-price-main">
                                  <div><strong>Brutto:</strong> {$product.price_gross}</div>
                                  <div class="small text-secondary"><strong>Netto:</strong> {$product.price_net}</div>
                                </div>
                              </div>
                            </details>
                            <div class="product-timestamp-stack">
                              <div class="product-timestamp-item">
                                <span class="product-timestamp-label">Utworzono</span>
                                <span class="product-timestamp-value">{$product.created_at|default:'-'}</span>
                              </div>
                              <div class="product-timestamp-item">
                                <span class="product-timestamp-label">Zmieniono</span>
                                <span class="js-updated-at-cell product-timestamp-value">{$product.updated_at|default:'-'}</span>
                              </div>
                            </div>
                          </div>
                        </td>
                        <td class="text-end product-actions-cell">
                          <div class="quick-edit-actions">
                            <button type="button" class="btn btn-sm btn-primary quick-edit-save js-quick-edit-save">Zapisz</button>
                            <a href="{$baseUrl}?controller=products&action=edit&id={$product.id}&return_url={$currentListUrl|escape:'url'}" class="btn btn-sm btn-outline-primary">Edytuj</a>
                            <a href="{$baseUrl}?controller=products&action=copy&id={$product.id}&return_url={$currentListUrl|escape:'url'}" class="btn btn-sm btn-outline-success">Kopiuj</a>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteProduct({$product.id})">Usun</button>
                          </div>
                        </td>
                  </tr>
                    {/foreach}
                  {else}
                    <tr><td colspan="10" class="text-center py-4">Brak produktow do wyswietlenia.</td></tr>
                  {/if}
                </tbody>
              </table>
            </form>
          </div>
        </div>
        {if $totalPages > 1}
          <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="small text-secondary">Strona {$page} / {$totalPages}</span>
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <a class="btn btn-sm btn-outline-secondary{if $page <= 1} disabled{/if}" href="{$baseUrl}?controller=products&action=index&page={$page-1}&per_page={$perPage}&filter_id={$filters.id|escape:'url'}&filter_global={$filters.global|escape:'url'}&filter_sku={$filters.sku|escape:'url'}&filter_product_name={$filters.product_name|escape:'url'}&filter_category_id={$filters.category_id|escape:'url'}&filter_quantity={$filters.quantity|escape:'url'}&filter_localization={$filters.localization|escape:'url'}&filter_with_glass={$filters.with_glass|escape:'url'}&filter_contours={$filters.contours|default:''|escape:'url'}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}">Poprzednia</a>
              <a class="btn btn-sm btn-outline-secondary{if $page >= $totalPages} disabled{/if}" href="{$baseUrl}?controller=products&action=index&page={$page+1}&per_page={$perPage}&filter_id={$filters.id|escape:'url'}&filter_global={$filters.global|escape:'url'}&filter_sku={$filters.sku|escape:'url'}&filter_product_name={$filters.product_name|escape:'url'}&filter_category_id={$filters.category_id|escape:'url'}&filter_quantity={$filters.quantity|escape:'url'}&filter_localization={$filters.localization|escape:'url'}&filter_with_glass={$filters.with_glass|escape:'url'}&filter_contours={$filters.contours|default:''|escape:'url'}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}">Nastepna</a>
            </div>
          </div>
        {/if}
      </div>
    </div>
  </div>
</main>

<div class="modal fade" id="csvExportModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" action="{$baseUrl}?controller=csvtemplates&action=exportcsv" id="csvExportForm" data-no-page-loader="1">
        <div class="modal-header">
          <h5 class="modal-title">Eksport CSV</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          {if !$exportTemplates}
            <div class="alert alert-warning mb-3">
              Brak zapisanych szablonow eksportu. Najpierw utworz szablon w module
              <a href="{$baseUrl}?controller=csvtemplates&action=index" class="alert-link">Szablony CSV</a>.
            </div>
          {/if}
          <div class="mb-3">
            <label class="form-label">Szablon eksportu</label>
            <select name="template_id" class="form-select" required{if !$exportTemplates} disabled{/if}>
              <option value="">Wybierz szablon</option>
              {foreach $exportTemplateGroups as $templateGroup}
                <optgroup label="{$templateGroup.label|escape}">
                  {foreach $templateGroup.items as $tpl}
                    <option value="{$tpl.id}">{$tpl.name|escape}</option>
                  {/foreach}
                </optgroup>
              {/foreach}
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label d-block">Zakres eksportu</label>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="export_mode" id="exportFiltered" value="filtered" checked>
              <label class="form-check-label" for="exportFiltered">Wyfiltrowane produkty</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="export_mode" id="exportSelected" value="selected">
              <label class="form-check-label" for="exportSelected">Zaznaczone produkty (<span id="selectedCount">0</span>)</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="export_mode" id="exportAll" value="all">
              <label class="form-check-label" for="exportAll">Wszystkie produkty</label>
            </div>
          </div>
          <hr>
          <div class="mb-3">
            <label class="form-label d-block">Generator tytulu CSV</label>
            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label small">Szablon tytulu</label>
                <select name="title_template_id" class="form-select form-select-sm">
                  <option value="">Brak</option>
                  {foreach $titleTemplates as $titleTemplate}
                    <option value="{$titleTemplate.id|escape}">{$titleTemplate.name|escape}</option>
                  {/foreach}
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label small">Kolekcja do tytulu</label>
                <input type="text" name="collection_name" id="csvExportCollectionName" class="form-control form-control-sm" placeholder="np. Marble">
              </div>
              <div class="col-12">
                <div id="csvGeneratedTitlePreview" class="border rounded-3 p-3 bg-light">
                  <div class="d-flex justify-content-between gap-2 align-items-center flex-wrap">
                    <strong>Podglad tytulu z pierwszego zaznaczonego produktu</strong>
                    <span id="csvGeneratedTitleLength" class="badge text-bg-secondary">0 / 75</span>
                  </div>
                  <div id="csvGeneratedTitlePreviewText" class="small mt-2 text-secondary">Wybierz szablon tytulu i zaznacz produkt, aby zobaczyc podglad.</div>
                </div>
              </div>
            </div>
            <div class="form-text">
              W szablonie CSV wybierz pole <code>product.generated_title</code>, aby zapisac gotowy tytul do kolumny.
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label d-block">Opcje obrazow EasyUploader</label>
            <div class="row g-2">
              <div class="col-md-4">
                <label class="form-label small">Kolekcja numeracja <span class="csv-help-icon" tabindex="0" role="button" title="Start numeracji kolekcji. Token {ldelim}{ldelim}collection_code{rdelim}{rdelim} da wpisana wartosc, a {ldelim}{ldelim}collection_code_index{rdelim}{rdelim} zwiekszy numer wedlug indeksu grafiki." data-bs-toggle="tooltip">i</span></label>
                <input type="text" name="image_collection_code" class="form-control form-control-sm" placeholder="np. A100">
              </div>
              <div class="col-md-4">
                <label class="form-label small">Zakres kolejki <span class="csv-help-icon" tabindex="0" role="button" title="Zakres do gridow i tokenow kolejki, np. A100-A250 albo TT510B-TT550B. Na tej podstawie auto wylicza grid i liczbe zdjec." data-bs-toggle="tooltip">i</span></label>
                <input type="text" name="image_queue_range" class="form-control form-control-sm" placeholder="np. TT510B-TT550B">
              </div>
              <div class="col-md-4">
                <label class="form-label small">Wzory miniatur <span class="csv-help-icon" tabindex="0" role="button" title="Lista albo zakres wzorow dla tokenu {ldelim}{ldelim}queue_item{rdelim}{rdelim}, np. A100 A200 A234, AK020-AK040 albo TB510B-TB522B. Zakres da wszystkie wzory po kolei." data-bs-toggle="tooltip">i</span></label>
                <input type="text" name="thumbnail_pattern_list" class="form-control form-control-sm" placeholder="np. TB510B-TB522B lub A100 A200 A234">
              </div>
              <div class="col-md-4">
                <label class="form-label small">Grid <span class="csv-help-icon" tabindex="0" role="button" title="Uklad grafik w gridzie, np. 3x2. Przycisk Auto dobiera grid do zakresu kolejki." data-bs-toggle="tooltip">i</span></label>
                <div class="input-group input-group-sm">
                  <input type="text" name="grid_layout" class="form-control form-control-sm" placeholder="np. 3x2">
                  <button type="button" class="btn btn-outline-secondary" id="csvExportGridAutoBtn">Auto</button>
                </div>
              </div>
              <div class="col-12">
                <div class="small text-secondary" id="csvExportGridHint">Wpisz zakres kolejki, aby wstepnie wyliczyc grid. Pole grid mozesz potem zmienic recznie.</div>
              </div>
              <div class="col-md-8">
                <label class="form-label small">Cena <span class="csv-help-icon" tabindex="0" role="button" title="Wartosc dostepna w szablonach jako product.price_to_csv oraz {ldelim}{ldelim}price{rdelim}{rdelim} w makrach obrazow." data-bs-toggle="tooltip">i</span></label>
                <input type="text" name="price_to_csv" class="form-control form-control-sm" placeholder="Cena">
              </div>
              <div class="col-md-4">
                <label class="form-label small">Ilosc miniatur <span class="csv-help-icon" tabindex="0" role="button" title="Ile miniatur wygenerowac. Przy wpisaniu wzorow miniatur ustawia sie automatycznie." data-bs-toggle="tooltip">i</span></label>
                <input type="number" min="0" name="thumbnail_count" class="form-control form-control-sm" value="0">
              </div>
              <div class="col-md-4">
                <label class="form-label small">Ilosc mockupow / gridow <span class="csv-help-icon" tabindex="0" role="button" title="Ile pozycji wygenerowac z makra mockupow. Token {ldelim}{ldelim}index{rdelim}{rdelim} oznacza numer mockupu." data-bs-toggle="tooltip">i</span></label>
                <input type="number" min="0" name="mockup_count" class="form-control form-control-sm" value="0">
              </div>
              <div class="col-md-4">
                <label class="form-label small">Ilosc zdjec <span class="csv-help-icon" tabindex="0" role="button" title="Ile pozycji wygenerowac z makra zdjec. Token {ldelim}{ldelim}index{rdelim}{rdelim} oznacza numer zdjecia." data-bs-toggle="tooltip">i</span></label>
                <input type="number" min="0" name="image_count" class="form-control form-control-sm" value="0">
              </div>
              <div class="col-12">
                <label class="form-label small">Bazowy katalog <span class="csv-help-icon" tabindex="0" role="button" title="Wartosc podstawiana pod token {ldelim}{ldelim}base_directory{rdelim}{rdelim} w makrach obrazow." data-bs-toggle="tooltip">i</span></label>
                <input type="text" name="image_base_directory" class="form-control form-control-sm" value="T:\wygenrowane_do_EU\">
              </div>
            </div>
            <div class="form-text">
              Makra i uklad sekcji dla pola <code>images</code> / <code>product.generated_images</code> ustawiasz w szablonie CSV. Wzory miniatur moga byc lista albo zakresem, np. <code>AK020-AK040</code>; liczba miniatur ustawi sie wtedy automatycznie, a biezacy wzor bedzie dostepny w makrze jako <code>{ldelim}{ldelim}queue_item{rdelim}{rdelim}</code>.
            </div>
          </div>
          <div class="d-flex justify-content-end gap-2 mt-3">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
            <button type="submit" class="btn btn-primary"{if !$exportTemplates} disabled{/if}>Generuj CSV</button>
          </div>
          <div class="mt-4">
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
              <label class="form-label mb-0">Ostatnie ustawienia eksportu</label>
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <button type="button" id="csvExportRecentPresetsRefresh" class="btn btn-sm btn-outline-secondary">Odswiez</button>
                <span id="csvExportRecentPresetsStatus" class="small text-secondary">Lista zaladuje sie po otwarciu okna.</span>
              </div>
            </div>
            <div id="csvExportRecentPresets" class="mt-2"></div>
          </div>
          <div id="selectedProductIdsContainer"></div>
          <input type="hidden" name="filter_id" value="{$filters.id|default:''|escape}">
          <input type="hidden" name="filter_global" value="{$filters.global|default:''|escape}">
          <input type="hidden" name="filter_sku" value="{$filters.sku|default:''|escape}">
          <input type="hidden" name="filter_product_name" value="{$filters.product_name|default:''|escape}">
          <input type="hidden" name="filter_category_id" value="{$filters.category_id|default:''|escape}">
          <input type="hidden" name="filter_quantity" value="{$filters.quantity|default:''|escape}">
          <input type="hidden" name="filter_localization" value="{$filters.localization|default:''|escape}">
          <input type="hidden" name="filter_with_glass" value="{$filters.with_glass|default:''|escape}">
          <input type="hidden" name="filter_contours" value="{$filters.contours|default:''|escape}">
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Bulk Category Modal -->
<div class="modal fade" id="bulkCategoryModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Przypisz kategorię do zaznaczonych produktów</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="bulkCategoryForm" method="post" action="{$baseUrl}?controller=products&action=bulkcategory">
        <input type="hidden" name="return_url" value="{$currentListUrl|escape}">
        <div class="modal-body">
          <p><strong>Zaznaczonych produktów: <span id="bulkCategoryCount">0</span></strong></p>
          <div class="mb-3">
            <label class="form-label">Kategoria</label>
            <select name="category_id" class="form-select" required>
              <option value="">-- Wybierz kategorię --</option>
              {foreach $categories as $category}
                <option value="{$category.id|escape}">{$category.name|escape}</option>
              {/foreach}
            </select>
          </div>
          <div id="bulkCategoryProductList"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
          <button type="submit" class="btn btn-primary">Przypisz kategorię</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Bulk Delete Modal -->
<div class="modal fade" id="bulkDeleteModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">⚠️ USUWANIE PRODUKTÓW</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="bulkDeleteForm" method="post" action="{$baseUrl}?controller=products&action=bulkdelete">
        <input type="hidden" name="return_url" value="{$currentListUrl|escape}">
        <div class="modal-body">
          <div class="alert alert-danger" role="alert">
            <strong>UWAGA!</strong> Będziesz usuwać <strong><span id="bulkDeleteCount">0</span></strong> produktów. Tej operacji nie można cofnąć, ale usunięte zostaną tylko zaznaczone rekordy.
          </div>
          <div class="mb-3" style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; padding: 10px;">
            <p class="mb-2"><strong>Produkty do usunięcia:</strong></p>
            <div id="bulkDeleteProductList"></div>
          </div>
          <div class="mb-3">
            <label class="form-label"><strong>Wpisz "USUWAM" aby potwierdzić:</strong></label>
            <input type="text" name="confirmation" class="form-control" placeholder="USUWAM" required>
            <div class="form-text" style="color: #dc3545;">Pola musi zawierać dokładnie: USUWAM</div>
          </div>
          <div id="bulkDeleteProductIdsContainer"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
          <button type="submit" class="btn btn-danger">USUŃ produkty</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Bulk Copy Modal -->
<div class="modal fade" id="bulkCopyModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Kopiuj zaznaczone produkty</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="bulkCopyForm" method="post" action="{$baseUrl}?controller=products&action=bulkcopy">
        <input type="hidden" name="return_url" value="{$currentListUrl|escape}">
        <div class="modal-body">
          <p><strong>Produktów do skopiowania: <span id="bulkCopyCount">0</span></strong></p>
          <div id="bulkCopyProductList"></div>
          <div id="bulkCopyProductIdsContainer"></div>
          <div class="alert alert-info" role="alert">
            Każdy produkt zostanie skopiowany z wszystkimi parametrami, polami custom, parametrami Allegro i grupami magazynowymi.
            Do nazwy zostanie dodany sufiks " kopia".
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
          <button type="submit" class="btn btn-primary">Kopiuj produkty</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="bulkCopySharedModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Kopiuj zaznaczone jako wspolne</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="bulkCopySharedForm" method="post" action="{$baseUrl}?controller=products&action=bulkcopyshared">
        <input type="hidden" name="return_url" value="{$currentListUrl|escape}">
        <div class="modal-body">
          <p><strong>Produktów do skopiowania: <span id="bulkCopySharedCount">0</span></strong></p>
          <div id="bulkCopySharedProductList"></div>
          <div id="bulkCopySharedProductIdsContainer"></div>
          <div class="alert alert-warning" role="alert">
            Kazda kopia zostanie od razu podpieta jako produkt wspolny z oryginalem. Nowa kopia nie dostanie relacji pochodnych.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
          <button type="submit" class="btn btn-warning">Kopiuj jako wspolne</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="bulkSharedModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Polacz zaznaczone jako wspolne</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="bulkSharedForm" method="post" action="{$baseUrl}?controller=products&action=bulkshared">
        <input type="hidden" name="return_url" value="{$currentListUrl|escape}">
        <div class="modal-body">
          <p><strong>Produktów do polaczenia: <span id="bulkSharedCount">0</span></strong></p>
          <div id="bulkSharedProductList"></div>
          <div id="bulkSharedProductIdsContainer"></div>
          <div class="alert alert-info" role="alert">
            Zaznaczone produkty trafia do jednej grupy wspolnego stanu. Jesli ktorys z nich ma stan pochodny, ta relacja zostanie usunieta.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
          <button type="submit" class="btn btn-primary">Polacz jako wspolne</button>
        </div>
      </form>
    </div>
  </div>
</div>

<form id="deleteForm" method="post" action="{$baseUrl}?controller=products&action=delete" style="display: none;">
  <input type="hidden" name="id" id="deleteId">
  <input type="hidden" name="return_url" value="{$currentListUrl|escape}">
</form>

<script>

function deleteProduct(productId) {
  if (confirm('Usunac tylko ten produkt? Powiazane i pochodne rekordy maja zostac w systemie.')) {
    document.getElementById('deleteId').value = productId;
    document.getElementById('deleteForm').submit();
  }
}

document.addEventListener('DOMContentLoaded', function() {

  
  var selectAll = document.getElementById('selectAllProducts');
  var checkboxes = document.querySelectorAll('.js-export-checkbox');
  var quickEditRows = document.querySelectorAll('.js-quick-edit-row');
  var selectedCount = document.getElementById('selectedCount');
  var exportForm = document.getElementById('csvExportForm');
  var csvExportModalEl = document.getElementById('csvExportModal');
  var selectedContainer = document.getElementById('selectedProductIdsContainer');
  var exportSelected = document.getElementById('exportSelected');
  var exportFiltered = document.getElementById('exportFiltered');
  var exportTemplateSelect = document.querySelector('select[name="template_id"]');
  var titleTemplateSelect = document.querySelector('select[name="title_template_id"]');
  var collectionNameInput = document.getElementById('csvExportCollectionName');
  var imageCollectionCodeInput = document.querySelector('input[name="image_collection_code"]');
  var imageQueueRangeInput = document.querySelector('input[name="image_queue_range"]');
  var thumbnailPatternListInput = document.querySelector('input[name="thumbnail_pattern_list"]');
  var gridLayoutInput = document.querySelector('input[name="grid_layout"]');
  var gridAutoButton = document.getElementById('csvExportGridAutoBtn');
  var gridHint = document.getElementById('csvExportGridHint');
  var priceToCsvInput = document.querySelector('input[name="price_to_csv"]');
  var thumbnailCountInput = document.querySelector('input[name="thumbnail_count"]');
  var mockupCountInput = document.querySelector('input[name="mockup_count"]');
  var imageCountInput = document.querySelector('input[name="image_count"]');
  var imageBaseDirectoryInput = document.querySelector('input[name="image_base_directory"]');
  var generatedTitlePreview = document.getElementById('csvGeneratedTitlePreviewText');
  var generatedTitleLength = document.getElementById('csvGeneratedTitleLength');
  var generatedTitlePreviewBox = document.getElementById('csvGeneratedTitlePreview');
  var generatedTitlePreviewUrl = '{$baseUrl|escape:"javascript"}?controller=products&action=previewgeneratedtitle';
  var csvExportPresetsUrl = '{$baseUrl|escape:"javascript"}?controller=csvtemplates&action=exportpresets';
  var csvExportRecentPresets = document.getElementById('csvExportRecentPresets');
  var csvExportRecentPresetsStatus = document.getElementById('csvExportRecentPresetsStatus');
  var csvExportRecentPresetsRefresh = document.getElementById('csvExportRecentPresetsRefresh');
  var productsFiltersForm = document.getElementById('productsFiltersForm');
  var categoryFilterSerialized = document.getElementById('filterCategoryIdSerialized');
  var categoryFilterSearch = document.getElementById('filterCategorySearch');
  var categoryFilterUi = document.getElementById('filterCategoryIdsUi');

  if (window.bootstrap && bootstrap.Tooltip) {
    var tooltipTriggers = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    for (var tooltipIndex = 0; tooltipIndex < tooltipTriggers.length; tooltipIndex++) {
      new bootstrap.Tooltip(tooltipTriggers[tooltipIndex]);
    }
  }

  // Bulk operations
  var bulkCopyBtn = document.getElementById('bulkCopyBtn');
  var bulkCopySharedBtn = document.getElementById('bulkCopySharedBtn');
  var bulkSharedBtn = document.getElementById('bulkSharedBtn');
  var bulkCategoryBtn = document.getElementById('bulkCategoryBtn');
  var bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
  var bulkExportBtn = document.getElementById('bulkExportBtn');
  var bulkActionsPanel = document.getElementById('bulkActionsPanel');


  var bulkCategoryModal = new bootstrap.Modal(document.getElementById('bulkCategoryModal'));
  var bulkDeleteModal = new bootstrap.Modal(document.getElementById('bulkDeleteModal'));
  var bulkCopyModal = new bootstrap.Modal(document.getElementById('bulkCopyModal'));
  var bulkCopySharedModal = new bootstrap.Modal(document.getElementById('bulkCopySharedModal'));
  var bulkSharedModal = new bootstrap.Modal(document.getElementById('bulkSharedModal'));

  var bulkCategoryForm = document.getElementById('bulkCategoryForm');
  var bulkDeleteForm = document.getElementById('bulkDeleteForm');
  var bulkCopyForm = document.getElementById('bulkCopyForm');
  var bulkCopySharedForm = document.getElementById('bulkCopySharedForm');
  var bulkSharedForm = document.getElementById('bulkSharedForm');
  var quickUpdateUrl = '{$baseUrl|escape:"javascript"}?controller=products&action=quickupdate';
  var lastCheckedCheckbox = null;
  var csvRecentPresetItems = [];

  function syncCategoryFilterValue() {
    if (!categoryFilterSerialized || !categoryFilterUi) {
      return;
    }

    var values = [];
    for (var i = 0; i < categoryFilterUi.options.length; i++) {
      if (categoryFilterUi.options[i].selected) {
        values.push(String(categoryFilterUi.options[i].value || ''));
      }
    }

    categoryFilterSerialized.value = values.join(',');
  }

  function filterCategoryOptions() {
    if (!categoryFilterSearch || !categoryFilterUi) {
      return;
    }

    var query = String(categoryFilterSearch.value || '').trim().toLowerCase();
    for (var i = 0; i < categoryFilterUi.options.length; i++) {
      var option = categoryFilterUi.options[i];
      var label = String(option.getAttribute('data-category-label') || option.text || '').toLowerCase();
      option.hidden = query !== '' && label.indexOf(query) === -1;
    }
  }

  function setQuickEditStatus(row, message, state) {
    if (!row) {
      return;
    }

    var status = row.querySelector('.js-quick-edit-status');
    if (!status) {
      return;
    }

    status.textContent = message || '';
    status.classList.remove('is-error', 'is-success');
    if (state === 'error') {
      status.classList.add('is-error');
    } else if (state === 'success') {
      status.classList.add('is-success');
    }
  }

  function highlightQuickEditRow(row, state) {
    if (!row) {
      return;
    }

    row.classList.remove('quick-edit-row-success', 'quick-edit-row-error');
    if (state === 'success') {
      row.classList.add('quick-edit-row-success');
    } else if (state === 'error') {
      row.classList.add('quick-edit-row-error');
    }

    window.setTimeout(function () {
      row.classList.remove('quick-edit-row-success', 'quick-edit-row-error');
    }, 1800);
  }

  function collectQuickEditPayload(row) {
    var payload = {
      id: row.getAttribute('data-product-id') || ''
    };
    var fields = row.querySelectorAll('.js-quick-edit-field');
    for (var i = 0; i < fields.length; i++) {
      payload[fields[i].getAttribute('data-field')] = fields[i].value;
    }
    return payload;
  }

  function renderSelectedProductsList(products) {
    var productList = '<ul>';
    for (var i = 0; i < products.length; i++) {
      productList += '<li>' + products[i].sku + ' - ' + products[i].name + '</li>';
    }
    productList += '</ul>';
    return productList;
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function parseQueueRange(value) {
    var normalized = String(value || '').trim().toUpperCase();
    if (normalized === '') {
      return { range: '', from: '', to: '', count: 0 };
    }

    var match = normalized.match(/^([A-Z]*)(\d+)([A-Z]*)\s*-\s*([A-Z]*)(\d+)([A-Z]*)$/);
    if (!match) {
      return { range: normalized, from: normalized, to: normalized, count: 1 };
    }

    var prefixFrom = String(match[1] || '');
    var suffixFrom = String(match[3] || '');
    var prefixTo = String(match[4] || prefixFrom);
    var numberFrom = Number(match[2] || 0);
    var numberTo = Number(match[5] || 0);
    var suffixTo = String(match[6] || '');

    if (prefixFrom !== prefixTo || suffixFrom !== suffixTo || numberTo < numberFrom) {
      return { range: normalized, from: normalized, to: normalized, count: 1 };
    }

    var fromValue = prefixFrom + String(match[2] || '') + suffixFrom;
    var toValue = prefixTo + String(match[5] || '') + suffixTo;
    return {
      range: fromValue + '-' + toValue,
      from: fromValue,
      to: toValue,
      count: (numberTo - numberFrom) + 1
    };
  }

  function parseThumbnailPatternList(value) {
    var normalized = String(value || '').trim().toUpperCase();
    if (normalized === '') {
      return [];
    }

    var rawItems = normalized.split(/[\s,;]+/);
    var uniqueItems = [];
    var seen = {};

    function addItem(item) {
      var normalizedItem = String(item || '').trim();
      if (normalizedItem === '' || seen[normalizedItem]) {
        return;
      }

      seen[normalizedItem] = true;
      uniqueItems.push(normalizedItem);
    }

    function addRange(prefix, fromNumber, toNumber, padLength, suffix) {
      for (var number = fromNumber; number <= toNumber; number++) {
        var numberPart = String(number);
        while (numberPart.length < padLength) {
          numberPart = '0' + numberPart;
        }
        addItem(prefix + numberPart + String(suffix || ''));
      }
    }

    for (var itemIndex = 0; itemIndex < rawItems.length; itemIndex++) {
      var item = String(rawItems[itemIndex] || '').trim();
      if (item === '') {
        continue;
      }

      var rangeMatch = item.match(/^([A-Z]*)(\d+)([A-Z]*)\s*-\s*([A-Z]*)(\d+)([A-Z]*)$/);
      if (rangeMatch) {
        var prefixFrom = String(rangeMatch[1] || '');
        var suffixFrom = String(rangeMatch[3] || '');
        var prefixTo = String(rangeMatch[4] || prefixFrom);
        var numberFrom = Number(rangeMatch[2] || 0);
        var numberTo = Number(rangeMatch[5] || 0);
        var suffixTo = String(rangeMatch[6] || '');
        if (prefixFrom === prefixTo && suffixFrom === suffixTo && numberTo >= numberFrom) {
          addRange(prefixFrom, numberFrom, numberTo, String(rangeMatch[2] || '').length, suffixFrom);
          continue;
        }
      }

      addItem(item);
    }

    return uniqueItems;
  }

  function suggestedGridLayout(count) {
    var total = Math.max(0, Number(count || 0));
    var variants = [
      { layout: '3x2', capacity: 6 },
      { layout: '4x2', capacity: 8 },
      { layout: '3x3', capacity: 9 },
      { layout: '4x3', capacity: 12 },
      { layout: '5x3', capacity: 15 },
      { layout: '5x4', capacity: 20 },
      { layout: '6x3', capacity: 18 },
      { layout: '6x4', capacity: 24 },
      { layout: '6x5', capacity: 30 }
    ];
    var targetGraphics = 10;
    var fallback = { layout: '', graphics: 0, capacity: 0 };

    if (total <= 0) {
      return fallback;
    }

    for (var i = 0; i < variants.length; i++) {
      var graphics = Math.ceil(total / variants[i].capacity);
      if (graphics <= targetGraphics) {
        return {
          layout: variants[i].layout,
          graphics: graphics,
          capacity: variants[i].capacity
        };
      }
    }

    var last = variants[variants.length - 1];
    return {
      layout: last.layout,
      graphics: Math.ceil(total / last.capacity),
      capacity: last.capacity
    };
  }

  function parseGridLayoutValue(value) {
    var normalized = String(value || '').trim().toLowerCase();
    var match = normalized.match(/^(\d+)\s*x\s*(\d+)$/);
    if (!match) {
      return { layout: normalized, columns: 0, rows: 0, capacity: 0 };
    }

    var columns = Math.max(0, Number(match[1] || 0));
    var rows = Math.max(0, Number(match[2] || 0));
    return {
      layout: columns > 0 && rows > 0 ? (String(columns) + 'x' + String(rows)) : normalized,
      columns: columns,
      rows: rows,
      capacity: columns * rows
    };
  }

  function syncImageCountWithQueueAndGrid() {
    if (!imageCountInput) {
      return;
    }

    var queue = parseQueueRange(imageQueueRangeInput ? imageQueueRangeInput.value : '');
    if (!queue.count) {
      imageCountInput.value = '0';
      return;
    }

    var parsedGrid = parseGridLayoutValue(gridLayoutInput ? gridLayoutInput.value : '');
    if (!parsedGrid.capacity) {
      var fallbackSuggestion = suggestedGridLayout(queue.count);
      imageCountInput.value = String(fallbackSuggestion && fallbackSuggestion.graphics ? fallbackSuggestion.graphics : 0);
      return;
    }

    imageCountInput.value = String(Math.ceil(queue.count / parsedGrid.capacity));
  }

  function updateGridHint(count, suggestion, manual) {
    if (!gridHint) {
      return;
    }

    if (!count) {
      gridHint.textContent = 'Wpisz zakres kolejki, aby wstepnie wyliczyc grid. Pole grid mozesz potem zmienic recznie.';
      return;
    }

    var layout = suggestion && suggestion.layout ? suggestion.layout : '-';
    var graphics = suggestion && suggestion.graphics ? suggestion.graphics : 0;

    gridHint.textContent = manual
      ? ('Zakres obejmuje ' + String(count) + ' pozycji. Autopodpowiedz to ' + layout
        + ' i wyjdzie z tego ok. ' + String(graphics) + ' grafik, ale obecny grid zostal ustawiony recznie.')
      : ('Zakres obejmuje ' + String(count) + ' pozycji. Wstepnie wyliczony grid: ' + layout
        + '. Wyjdzie z tego ok. ' + String(graphics) + ' grafik. Mozesz go zmienic recznie.');
  }

  function recalculateGridSuggestion(forceApply) {
    var queue = parseQueueRange(imageQueueRangeInput ? imageQueueRangeInput.value : '');
    var suggested = suggestedGridLayout(queue.count);
    var manualOverride = gridLayoutInput && gridLayoutInput.dataset.manualOverride === '1';

    if (gridLayoutInput && suggested.layout !== '' && (forceApply || !manualOverride || String(gridLayoutInput.value || '').trim() === '')) {
      gridLayoutInput.value = suggested.layout;
      gridLayoutInput.dataset.manualOverride = forceApply ? '0' : (manualOverride ? '1' : '0');
      manualOverride = gridLayoutInput.dataset.manualOverride === '1';
    }

    updateGridHint(queue.count, suggested, manualOverride);
    syncImageCountWithQueueAndGrid();
  }

  function refreshGridStateWithoutChangingValues() {
    var queue = parseQueueRange(imageQueueRangeInput ? imageQueueRangeInput.value : '');
    var suggested = suggestedGridLayout(queue.count);
    if (gridLayoutInput) {
      gridLayoutInput.dataset.manualOverride = '1';
    }
    updateGridHint(queue.count, suggested, true);
  }

  function fillSelectedProductsContainer(containerId, ids) {
    var container = document.getElementById(containerId);
    if (!container) {
      return;
    }

    container.innerHTML = '';
    for (var i = 0; i < ids.length; i++) {
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'product_ids[]';
      input.value = ids[i];
      container.appendChild(input);
    }
  }

  function saveQuickEditRow(row) {
    if (!row || !window.fetch) {
      return;
    }

    var saveButton = row.querySelector('.js-quick-edit-save');
    var payload = collectQuickEditPayload(row);
    setQuickEditStatus(row, 'Zapisywanie...', '');
    row.classList.remove('quick-edit-row-success', 'quick-edit-row-error');

    if (saveButton) {
      saveButton.classList.add('is-saving');
      saveButton.disabled = true;
    }

    fetch(quickUpdateUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: new URLSearchParams(payload).toString()
    })
      .then(function (response) {
        return response.text().then(function (text) {
          var data = {};
          try {
            data = text ? JSON.parse(text) : {};
          } catch (error) {
            data = { error: text || ('HTTP ' + response.status) };
          }
          if (!response.ok) {
            throw new Error(data && data.error ? data.error : ('HTTP ' + response.status));
          }
          return data;
        });
      })
      .then(function (data) {
        var item = data && data.item ? data.item : null;
        if (!item) {
          throw new Error('Brak danych po zapisie.');
        }

        var updatedAtCell = row.querySelector('.js-updated-at-cell');
        if (updatedAtCell && item.updated_at) {
          updatedAtCell.textContent = item.updated_at;
        }

        var nameField = row.querySelector('.js-quick-edit-field[data-field="product_name"]');
        var quantityField = row.querySelector('.js-quick-edit-field[data-field="quantity"]');
        var localizationField = row.querySelector('.js-quick-edit-field[data-field="localization"]');
        var dimensionsField = row.querySelector('.js-quick-edit-field[data-field="dimensions"]');

        if (nameField) {
          nameField.value = item.product_name || '';
        }
        if (quantityField) {
          quantityField.value = typeof item.quantity !== 'undefined' ? item.quantity : 0;
        }
        if (localizationField) {
          localizationField.value = item.localization || '';
        }
        if (dimensionsField) {
          dimensionsField.value = item.dimensions || '';
        }

        setQuickEditStatus(row, 'Zapisano.', 'success');
        highlightQuickEditRow(row, 'success');
      })
      .catch(function (error) {
        setQuickEditStatus(row, error && error.message ? error.message : 'Nie udalo sie zapisac zmian.', 'error');
        highlightQuickEditRow(row, 'error');
      })
      .finally(function () {
        if (saveButton) {
          saveButton.classList.remove('is-saving');
          saveButton.disabled = false;
        }
      });
  }

  function getSelectedIds() {
    var ids = [];
    for (var i = 0; i < checkboxes.length; i++) {
      if (checkboxes[i].checked) {
        ids.push(checkboxes[i].value);
      }
    }
    return ids;
  }

  function getSelectedProductInfo() {
    var products = [];
    for (var i = 0; i < checkboxes.length; i++) {
      if (checkboxes[i].checked) {
        var row = checkboxes[i].closest('tr');
        var sku = row.querySelector('td:nth-child(3)').textContent.trim();
        var nameInput = row.querySelector('.js-quick-edit-field[data-field="product_name"]');
        var name = nameInput ? String(nameInput.value || '').trim() : row.querySelector('td:nth-child(4)').textContent.trim();
        products.push({ id: checkboxes[i].value, sku: sku, name: name.substring(0, 50) + (name.length > 50 ? '...' : '') });
      }
    }
    return products;
  }

  function updateBulkActionsPanel() {
    var count = 0;
    for (var i = 0; i < checkboxes.length; i++) {
      if (checkboxes[i].checked) {
        count++;
      }
    }
    var bulkSelectedCount = document.getElementById('bulkSelectedCount');
    if (bulkSelectedCount) {
      bulkSelectedCount.textContent = String(count);
    }
  }

  function updateCount() {
    var count = 0;
    for (var i = 0; i < checkboxes.length; i++) {
      if (checkboxes[i].checked) {
        count++;
      }
    }

    if (selectedCount) {
      selectedCount.textContent = String(count);
    }

    updateBulkActionsPanel();
    updateGeneratedTitlePreview();
  }

  function firstSelectedId() {
    for (var i = 0; i < checkboxes.length; i++) {
      if (checkboxes[i].checked) {
        return checkboxes[i].value;
      }
    }

    return '';
  }

  function setGeneratedTitlePreviewState(text, length) {
    if (!generatedTitlePreview || !generatedTitleLength) {
      return;
    }

    generatedTitlePreview.textContent = text || '';
    generatedTitleLength.textContent = String(length || 0) + ' / 75';
    generatedTitleLength.classList.remove('text-bg-secondary', 'text-bg-danger');
    generatedTitleLength.classList.add((length || 0) > 75 ? 'text-bg-danger' : 'text-bg-secondary');

    if (generatedTitlePreviewBox) {
      generatedTitlePreviewBox.classList.remove('border-danger', 'bg-danger-subtle');
      if ((length || 0) > 75) {
        generatedTitlePreviewBox.classList.add('border-danger', 'bg-danger-subtle');
      }
    }
  }

  function updateGeneratedTitlePreview() {
    if (!window.fetch || !generatedTitlePreview || !generatedTitleLength) {
      return;
    }

    var productId = firstSelectedId();
    var titleTemplateId = titleTemplateSelect ? String(titleTemplateSelect.value || '').trim() : '';
    var collectionName = collectionNameInput ? String(collectionNameInput.value || '').trim() : '';

    if (!productId) {
      setGeneratedTitlePreviewState('Wybierz produkt, aby zobaczyc podglad.', 0);
      return;
    }

    if (!titleTemplateId) {
      setGeneratedTitlePreviewState('Wybierz szablon tytulu, aby zobaczyc podglad.', 0);
      return;
    }

    setGeneratedTitlePreviewState('Liczenie podgladu tytulu...', 0);

    fetch(
      generatedTitlePreviewUrl
        + '&product_id=' + encodeURIComponent(productId)
        + '&title_template_id=' + encodeURIComponent(titleTemplateId)
        + '&collection_name=' + encodeURIComponent(collectionName)
        + '&image_queue_range=' + encodeURIComponent(imageQueueRangeInput ? String(imageQueueRangeInput.value || '').trim() : '')
        + '&grid_layout=' + encodeURIComponent(gridLayoutInput ? String(gridLayoutInput.value || '').trim() : ''),
      { headers: { 'Accept': 'application/json' } }
    )
      .then(function (response) {
        return response.text().then(function (text) {
          var data = {};
          try {
            data = text ? JSON.parse(text) : {};
          } catch (error) {
            data = { message: text || ('HTTP ' + response.status) };
          }
          if (!response.ok) {
            throw new Error(data && data.message ? data.message : ('HTTP ' + response.status));
          }
          return data;
        });
      })
      .then(function (data) {
        setGeneratedTitlePreviewState(String(data && data.title ? data.title : ''), Number(data && data.length ? data.length : 0));
      })
      .catch(function (error) {
        setGeneratedTitlePreviewState(error && error.message ? error.message : 'Nie udalo sie pobrac podgladu tytulu.', 0);
      });
  }

  function setSelectValueIfExists(selectElement, value) {
    if (!selectElement) {
      return false;
    }

    var normalizedValue = String(value || '');
    var hasOption = false;
    for (var optionIndex = 0; optionIndex < selectElement.options.length; optionIndex++) {
      if (String(selectElement.options[optionIndex].value || '') === normalizedValue) {
        hasOption = true;
        break;
      }
    }

    selectElement.value = hasOption ? normalizedValue : '';
    return hasOption;
  }

  function applyRecentExportPresetByIndex(index) {
    var item = csvRecentPresetItems[index];
    if (!item) {
      return;
    }

    var hasTemplate = setSelectValueIfExists(exportTemplateSelect, item.template_id || '');
    setSelectValueIfExists(titleTemplateSelect, item.title_template_id || '');
    if (collectionNameInput) {
      collectionNameInput.value = String(item.collection_name || '');
    }
    if (imageCollectionCodeInput) {
      imageCollectionCodeInput.value = String(item.image_collection_code || '');
    }
    if (imageQueueRangeInput) {
      imageQueueRangeInput.value = String(item.image_queue_range || '');
    }
    if (thumbnailPatternListInput) {
      thumbnailPatternListInput.value = String(item.thumbnail_pattern_list || '');
    }
    if (gridLayoutInput) {
      gridLayoutInput.value = String(item.grid_layout || '');
      gridLayoutInput.dataset.manualOverride = '1';
    }
    if (priceToCsvInput) {
      priceToCsvInput.value = String(item.price_to_csv || '');
    }
    if (thumbnailCountInput) {
      thumbnailCountInput.value = String(item.thumbnail_count || 0);
    }
    if (mockupCountInput) {
      mockupCountInput.value = String(item.mockup_count || 0);
    }
    if (imageCountInput) {
      imageCountInput.value = String(item.image_count || 0);
    }
    if (imageBaseDirectoryInput) {
      imageBaseDirectoryInput.value = String(item.image_base_directory || '');
    }

    if (csvExportRecentPresetsStatus) {
      csvExportRecentPresetsStatus.textContent = hasTemplate
        ? 'Wczytano ustawienia do formularza.'
        : 'Wczytano ustawienia, ale powiazany szablon eksportu nie istnieje juz na liscie.';
    }

    updateGeneratedTitlePreview();
    refreshGridStateWithoutChangingValues();
  }

  function applyPartialRecentExportPresetByIndex(index) {
    var item = csvRecentPresetItems[index];
    if (!item) {
      return;
    }

    if (imageCollectionCodeInput) {
      imageCollectionCodeInput.value = String(item.image_collection_code || '');
    }
    if (imageQueueRangeInput) {
      imageQueueRangeInput.value = String(item.image_queue_range || '');
    }
    if (thumbnailPatternListInput) {
      thumbnailPatternListInput.value = String(item.thumbnail_pattern_list || '');
    }
    if (gridLayoutInput) {
      gridLayoutInput.value = String(item.grid_layout || '');
      gridLayoutInput.dataset.manualOverride = '1';
    }
    if (thumbnailCountInput) {
      thumbnailCountInput.value = String(item.thumbnail_count || 0);
    }
    if (mockupCountInput) {
      mockupCountInput.value = String(item.mockup_count || 0);
    }
    if (imageCountInput) {
      imageCountInput.value = String(item.image_count || 0);
    }
    if (imageBaseDirectoryInput) {
      imageBaseDirectoryInput.value = String(item.image_base_directory || '');
    }

    if (csvExportRecentPresetsStatus) {
      csvExportRecentPresetsStatus.textContent = 'Wczytano ustawienia makra obrazow.';
    }

    refreshGridStateWithoutChangingValues();
  }

  function scheduleRecentExportPresetsReload() {
    window.setTimeout(loadRecentExportPresets, 600);
    window.setTimeout(loadRecentExportPresets, 1800);
  }

  function renderRecentExportPresets(items) {
    if (!csvExportRecentPresets) {
      return;
    }

    csvRecentPresetItems = Array.isArray(items) ? items : [];

    if (csvRecentPresetItems.length === 0) {
      csvExportRecentPresets.innerHTML = '<div class="small text-secondary border rounded-3 p-3">Brak zapisanych ustawien eksportu.</div>';
      return;
    }

    var html = '';
    for (var i = 0; i < csvRecentPresetItems.length; i++) {
      var item = csvRecentPresetItems[i] || {};
      var templateName = item.template_name ? String(item.template_name) : 'Bez nazwy';
      var titleName = item.title_template_name ? String(item.title_template_name) : 'Brak';
      var createdAt = item.created_at ? String(item.created_at) : '';

      html += ''
        + '<div class="border rounded-3 px-3 py-2 mb-2 bg-light-subtle">'
        + '<div class="row g-2 align-items-center">'
        + '<div class="col-lg-4">'
        + '<div class="fw-semibold small">' + escapeHtml(templateName) + '</div>'
        + '<div class="small text-secondary text-truncate">Tytul: ' + escapeHtml(titleName) + '</div>'
        + '</div>'
        + '<div class="col-lg-5">'
        + '<div class="small text-secondary text-truncate">Kol. tytul: ' + escapeHtml(item.collection_name || '-') + '</div>'
        + '<div class="small text-secondary text-truncate">Kol. nr: ' + escapeHtml(item.image_collection_code || '-') + ' | Kolejka: ' + escapeHtml(item.image_queue_range || '-') + ' | Grid: ' + escapeHtml(item.grid_layout || '-') + '</div>'
        + '<div class="small text-secondary text-truncate">Wzory miniatur: ' + escapeHtml(item.thumbnail_pattern_list || '-') + '</div>'
        + '<div class="small text-secondary text-truncate">Min: ' + escapeHtml(item.thumbnail_count || 0) + ' | Mock: ' + escapeHtml(item.mockup_count || 0) + ' | Zdj: ' + escapeHtml(item.image_count || 0) + '</div>'
        + '</div>'
        + '<div class="col-lg-3">'
        + '<div class="d-flex justify-content-lg-end gap-2 flex-wrap">'
        + '<button type="button" class="btn btn-sm btn-primary js-csv-recent-preset-full" data-preset-index="' + i + '">Wszystko</button>'
        + '<button type="button" class="btn btn-sm btn-outline-primary js-csv-recent-preset-partial" data-preset-index="' + i + '">Makro</button>'
        + '</div>'
        + '<div class="small text-secondary text-lg-end mt-1">' + escapeHtml(createdAt) + '</div>'
        + '</div>'
        + '</div>'
        + '</div>';
    }

    csvExportRecentPresets.innerHTML = html;

  }

  function loadRecentExportPresets() {
    if (!window.fetch || !csvExportRecentPresets) {
      return;
    }

    if (csvExportRecentPresetsStatus) {
      csvExportRecentPresetsStatus.textContent = 'Ladowanie...';
    }

    fetch(csvExportPresetsUrl, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
      .then(function (response) {
        return response.text().then(function (text) {
          var data = {};
          try {
            data = text ? JSON.parse(text) : {};
          } catch (error) {
            data = { error: text || ('HTTP ' + response.status) };
          }
          if (!response.ok) {
            throw new Error(data && data.error ? data.error : ('HTTP ' + response.status));
          }
          return data;
        });
      })
      .then(function (data) {
        renderRecentExportPresets(data && data.items ? data.items : []);
        if (csvExportRecentPresetsStatus) {
          csvExportRecentPresetsStatus.textContent = 'Kliknij, aby wczytac ustawienia.';
        }
      })
      .catch(function (error) {
        renderRecentExportPresets([]);
        if (csvExportRecentPresetsStatus) {
          csvExportRecentPresetsStatus.textContent = error && error.message ? error.message : 'Nie udalo sie wczytac historii eksportu.';
        }
      });
  }

  if (selectAll) {
    selectAll.addEventListener('change', function () {
      for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = selectAll.checked;
      }
      updateCount();
    });
  }

  var productSelectToggles = document.querySelectorAll('.js-product-select-toggle');
  for (var toggleIndex = 0; toggleIndex < productSelectToggles.length; toggleIndex++) {
    (function (toggle) {
      var checkbox = toggle.querySelector('.js-export-checkbox');
      if (!checkbox) {
        return;
      }

      function triggerCheckboxToggle() {
        checkbox.checked = !checkbox.checked;
        lastCheckedCheckbox = checkbox;
        updateCount();
      }

      toggle.addEventListener('click', function (event) {
        var target = event.target;
        if (target && target.closest('input, a, button, label')) {
          return;
        }

        triggerCheckboxToggle();
      });

      toggle.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' && event.key !== ' ') {
          return;
        }

        event.preventDefault();
        triggerCheckboxToggle();
      });
    })(productSelectToggles[toggleIndex]);
  }

  for (var i = 0; i < checkboxes.length; i++) {
    checkboxes[i].addEventListener('click', function (event) {
      if (event.shiftKey && lastCheckedCheckbox && lastCheckedCheckbox !== this) {
        var start = -1;
        var end = -1;

        for (var checkboxIndex = 0; checkboxIndex < checkboxes.length; checkboxIndex++) {
          if (checkboxes[checkboxIndex] === lastCheckedCheckbox) {
            start = checkboxIndex;
          }
          if (checkboxes[checkboxIndex] === this) {
            end = checkboxIndex;
          }
        }

        if (start !== -1 && end !== -1) {
          var checkedState = this.checked;
          var from = Math.min(start, end);
          var to = Math.max(start, end);

          for (var rangeIndex = from; rangeIndex <= to; rangeIndex++) {
            checkboxes[rangeIndex].checked = checkedState;
          }
        }
      }

      lastCheckedCheckbox = this;
      updateCount();
    });

  }

  for (var r = 0; r < quickEditRows.length; r++) {
    (function (row) {
      var saveButton = row.querySelector('.js-quick-edit-save');
      var fields = row.querySelectorAll('.js-quick-edit-field');

      if (saveButton) {
        saveButton.addEventListener('click', function () {
          saveQuickEditRow(row);
        });
      }

      for (var f = 0; f < fields.length; f++) {
        fields[f].addEventListener('keydown', function (event) {
          if (event.key === 'Enter') {
            event.preventDefault();
            saveQuickEditRow(row);
          }
        });
      }
    })(quickEditRows[r]);
  }

  // Bulk Copy handler
  if (bulkCopyBtn) {
    bulkCopyBtn.addEventListener('click', function () {
      var ids = getSelectedIds();
      var products = getSelectedProductInfo();
      
      if (ids.length === 0) {
        alert('Zaznacz produkty do skopiowania.');
        return;
      }

      document.getElementById('bulkCopyCount').textContent = ids.length;
      document.getElementById('bulkCopyProductList').innerHTML = renderSelectedProductsList(products);
      fillSelectedProductsContainer('bulkCopyProductIdsContainer', ids);

      bulkCopyModal.show();
    });
  }

  if (bulkCopySharedBtn) {
    bulkCopySharedBtn.addEventListener('click', function () {
      var ids = getSelectedIds();
      var products = getSelectedProductInfo();

      if (ids.length === 0) {
        alert('Zaznacz produkty do skopiowania jako wspolne.');
        return;
      }

      document.getElementById('bulkCopySharedCount').textContent = ids.length;
      document.getElementById('bulkCopySharedProductList').innerHTML = renderSelectedProductsList(products);
      fillSelectedProductsContainer('bulkCopySharedProductIdsContainer', ids);

      bulkCopySharedModal.show();
    });
  }

  if (bulkSharedBtn) {
    bulkSharedBtn.addEventListener('click', function () {
      var ids = getSelectedIds();
      var products = getSelectedProductInfo();

      if (ids.length < 2) {
        alert('Zaznacz przynajmniej dwa produkty do polaczenia jako wspolne.');
        return;
      }

      document.getElementById('bulkSharedCount').textContent = ids.length;
      document.getElementById('bulkSharedProductList').innerHTML = renderSelectedProductsList(products);
      fillSelectedProductsContainer('bulkSharedProductIdsContainer', ids);

      bulkSharedModal.show();
    });
  }

  // Bulk Category handler
  if (bulkCategoryBtn) {
    bulkCategoryBtn.addEventListener('click', function () {
      var ids = getSelectedIds();
      var products = getSelectedProductInfo();
      
      if (ids.length === 0) {
        alert('Zaznacz produkty do przypisania kategorii.');
        return;
      }

      document.getElementById('bulkCategoryCount').textContent = ids.length;
      var productList = '<ul style="max-height: 200px; overflow-y: auto;">';
      for (var i = 0; i < products.length; i++) {
        productList += '<li>' + products[i].sku + ' - ' + products[i].name + '</li>';
      }
      productList += '</ul>';
      document.getElementById('bulkCategoryProductList').innerHTML = productList;

      var container = document.getElementById('bulkCategoryForm');
      var existingInputs = container.querySelectorAll('input[name="product_ids[]"]');
      for (var i = 0; i < existingInputs.length; i++) {
        existingInputs[i].remove();
      }

      for (var i = 0; i < ids.length; i++) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'product_ids[]';
        input.value = ids[i];
        container.appendChild(input);
      }

      bulkCategoryModal.show();
    });
  }

  // Bulk Delete handler
  if (bulkDeleteBtn) {
    bulkDeleteBtn.addEventListener('click', function () {
      var ids = getSelectedIds();
      var products = getSelectedProductInfo();
      
      if (ids.length === 0) {
        alert('Zaznacz produkty do usunięcia.');
        return;
      }

      document.getElementById('bulkDeleteCount').textContent = ids.length;
      var productList = '<ul style="max-height: 200px; overflow-y: auto;">';
      for (var i = 0; i < products.length; i++) {
        productList += '<li><strong>' + products[i].sku + '</strong> - ' + products[i].name + '</li>';
      }
      productList += '</ul>';
      document.getElementById('bulkDeleteProductList').innerHTML = productList;

      var container = document.getElementById('bulkDeleteProductIdsContainer');
      container.innerHTML = '';
      for (var i = 0; i < ids.length; i++) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'product_ids[]';
        input.value = ids[i];
        container.appendChild(input);
      }

      // Reset confirmation field
      document.querySelector('input[name="confirmation"]').value = '';

      bulkDeleteModal.show();
    });
  }

  // Bulk Export handler
  if (bulkExportBtn) {
    bulkExportBtn.addEventListener('click', function () {
      var ids = getSelectedIds();
      if (ids.length === 0) {
        alert('Zaznacz produkty do eksportu.');
        return;
      }
      // Update the selected count in the modal
      var countSpan = document.getElementById('selectedCount');
      if (countSpan) {
        countSpan.textContent = String(ids.length);
      }
      // Show the CSV export modal
      var csvModal = new bootstrap.Modal(document.getElementById('csvExportModal'));
      csvModal.show();
      updateGeneratedTitlePreview();
    });
  }

  if (csvExportModalEl) {
    csvExportModalEl.addEventListener('show.bs.modal', function () {
      loadRecentExportPresets();
      recalculateGridSuggestion(false);
    });
  }

  if (csvExportRecentPresets) {
    csvExportRecentPresets.addEventListener('click', function (event) {
      var fullButton = event.target.closest('.js-csv-recent-preset-full');
      if (fullButton) {
        event.preventDefault();
        applyRecentExportPresetByIndex(Number(fullButton.getAttribute('data-preset-index') || '-1'));
        return;
      }

      var partialButton = event.target.closest('.js-csv-recent-preset-partial');
      if (partialButton) {
        event.preventDefault();
        applyPartialRecentExportPresetByIndex(Number(partialButton.getAttribute('data-preset-index') || '-1'));
      }
    });
  }

  if (csvExportRecentPresetsRefresh) {
    csvExportRecentPresetsRefresh.addEventListener('click', function () {
      loadRecentExportPresets();
    });
  }

  if (titleTemplateSelect) {
    titleTemplateSelect.addEventListener('change', updateGeneratedTitlePreview);
  }

  if (collectionNameInput) {
    collectionNameInput.addEventListener('input', updateGeneratedTitlePreview);
  }

  if (imageQueueRangeInput) {
    imageQueueRangeInput.addEventListener('input', function () {
      recalculateGridSuggestion(false);
      updateGeneratedTitlePreview();
    });
  }

  if (thumbnailPatternListInput) {
    thumbnailPatternListInput.addEventListener('input', function () {
      if (thumbnailCountInput) {
        thumbnailCountInput.value = String(parseThumbnailPatternList(thumbnailPatternListInput.value).length);
      }
    });
  }

  if (gridLayoutInput) {
    gridLayoutInput.dataset.manualOverride = '0';
    gridLayoutInput.addEventListener('input', function () {
      gridLayoutInput.dataset.manualOverride = '1';
      var queueCount = parseQueueRange(imageQueueRangeInput ? imageQueueRangeInput.value : '').count;
      updateGridHint(queueCount, suggestedGridLayout(queueCount), true);
      syncImageCountWithQueueAndGrid();
      updateGeneratedTitlePreview();
    });
  }

  if (gridAutoButton) {
    gridAutoButton.addEventListener('click', function () {
      if (gridLayoutInput) {
        gridLayoutInput.dataset.manualOverride = '0';
      }
      recalculateGridSuggestion(true);
      updateGeneratedTitlePreview();
    });
  }

  // Bulk Cancel handler
  var bulkCancelBtn = document.getElementById('bulkCancelBtn');
  if (bulkCancelBtn) {
    bulkCancelBtn.addEventListener('click', function () {
      // Uncheck all product checkboxes
      for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = false;
      }
      // Uncheck select all checkbox
      if (selectAll) {
        selectAll.checked = false;
      }
      updateCount();
    });
  }

  // Prevent form submission if confirmation not correct
  if (bulkDeleteForm) {
    bulkDeleteForm.addEventListener('submit', function (event) {
      var confirmationInput = document.querySelector('input[name="confirmation"]');
      if (!confirmationInput || confirmationInput.value !== 'USUWAM') {
        event.preventDefault();
        alert('Wpisz dokładnie "USUWAM" aby potwierdzić usunięcie produktów.');
        return false;
      }
    });
  }

  if (exportForm) {
    exportForm.addEventListener('submit', function (event) {
      selectedContainer.innerHTML = '';

      if (exportSelected && exportSelected.checked) {
        var ids = [];
        for (var i = 0; i < checkboxes.length; i++) {
          if (checkboxes[i].checked) {
            ids.push(checkboxes[i].value);
          }
        }

        if (ids.length === 0) {
          event.preventDefault();
          alert('Zaznacz produkty lub wybierz opcje "Wszystkie produkty".');
          return;
        }

        for (var j = 0; j < ids.length; j++) {
          var input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'product_ids[]';
          input.value = ids[j];
          selectedContainer.appendChild(input);
        }
      } else if (exportFiltered && exportFiltered.checked) {
        return;
      }

      if (csvExportRecentPresetsStatus) {
        csvExportRecentPresetsStatus.textContent = 'Zapisywanie ustawien eksportu...';
      }
      scheduleRecentExportPresetsReload();
    });
  }

  if (categoryFilterUi) {
    categoryFilterUi.addEventListener('change', syncCategoryFilterValue);
    syncCategoryFilterValue();
  }

  if (categoryFilterSearch) {
    categoryFilterSearch.addEventListener('input', filterCategoryOptions);
    filterCategoryOptions();
  }

  if (productsFiltersForm) {
    productsFiltersForm.addEventListener('submit', syncCategoryFilterValue);
  }

  recalculateGridSuggestion(false);
  updateCount();
});
</script>
