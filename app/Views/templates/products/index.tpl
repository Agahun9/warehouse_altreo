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

  <style>
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
      width: 130px;
      min-width: 130px;
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

    .product-contours-cell {
      width: 100px;
      min-width: 100px;
      max-width: 100px;
      padding-left: .2rem !important;
      padding-right: .2rem !important;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .product-price-cell {
      width: 100px;
      min-width: 100px;
      max-width: 100px;
      white-space: nowrap;
      line-height: 1.2;
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

    @media (max-width: 1400px) {
      .product-name-cell {
        min-width: 180px;
      }

      .quick-edit-input {
        font-size: .82rem;
        padding: .32rem .45rem;
      }
    }
  </style>

  <div class="app-content">
    <div class="container-fluid">
      {if $flashSuccess}<div class="alert alert-success">{$flashSuccess|escape}</div>{/if}
      {if $flashError}<div class="alert alert-danger">{$flashError|escape}</div>{/if}

      <div class="card mb-4">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <h3 class="card-title mb-1">Filtry i sortowanie</h3>
            <div class="text-secondary small">Klikaj naglowki kolumn, aby przelaczac: ASC, DESC, reset.</div>
          </div>
          <div class="d-flex gap-2">
            <a href="{$csvImportUrl|escape}" class="btn btn-outline-primary">Import CSV</a>
            <a href="{$baseUrl}?controller=products&action=create&return_url={$currentListUrl|escape:'url'}" class="btn btn-success">Dodaj produkt</a>
         
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h3 class="card-title mb-0">Wszystkie produkty</h3>
            <div class="small text-secondary">Lacznie {$totalProducts} produktow, strona {$page} z {$totalPages}</div>
          </div>
          <span class="badge text-bg-primary">{$totalProducts}</span>
        </div>
        
        <!-- Panel akcji masowych -->
        <div id="bulkActionsPanel" class="card-body bg-light border-bottom" style="display: none;">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="text-secondary">
              <strong id="bulkSelectedCount">0</strong> produktów zaznaczonych
            </div>
            <div class="btn-group" role="group">
              <button type="button" id="bulkCopyBtn" class="btn btn-sm btn-outline-success" title="Skopiuj wszystkie zaznaczone produkty">
                <i class="bi bi-files"></i> Kopiuj zaznaczone
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
            <form method="get" action="{$baseUrl}">
              <input type="hidden" name="controller" value="products">
              <input type="hidden" name="action" value="index">
              <input type="hidden" name="sort_by" value="{$sortBy|default:''|escape}">
              <input type="hidden" name="sort_dir" value="{$sortDir|default:''|escape}">
              <input type="hidden" name="filter_global" value="{$filters.global|default:''|escape}">
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
                  </div>
                {if $totalPages > 1}
                  {assign var=prevPage value=$page-1}
                  {assign var=nextPage value=$page+1}
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    <a class="btn btn-sm btn-outline-secondary{if $page <= 1} disabled{/if}" href="{$baseUrl}?controller=products&action=index&page=1&per_page={$perPage}&filter_id={$filters.id|escape:'url'}&filter_global={$filters.global|escape:'url'}&filter_sku={$filters.sku|escape:'url'}&filter_product_name={$filters.product_name|escape:'url'}&filter_category_id={$filters.category_id|escape:'url'}&filter_quantity={$filters.quantity|escape:'url'}&filter_localization={$filters.localization|escape:'url'}&filter_with_glass={$filters.with_glass|escape:'url'}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}">Pierwsza</a>
                    <a class="btn btn-sm btn-outline-secondary{if $page <= 1} disabled{/if}" href="{$baseUrl}?controller=products&action=index&page={$prevPage}&per_page={$perPage}&filter_id={$filters.id|escape:'url'}&filter_global={$filters.global|escape:'url'}&filter_sku={$filters.sku|escape:'url'}&filter_product_name={$filters.product_name|escape:'url'}&filter_category_id={$filters.category_id|escape:'url'}&filter_quantity={$filters.quantity|escape:'url'}&filter_localization={$filters.localization|escape:'url'}&filter_with_glass={$filters.with_glass|escape:'url'}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}">Poprzednia</a>
                    {foreach $pageWindow as $pageItem}
                      {if $pageItem.type eq 'page'}
                        <a class="btn btn-sm {if $pageItem.is_current}btn-primary{else}btn-outline-secondary{/if}" href="{$baseUrl}?controller=products&action=index&page={$pageItem.value}&per_page={$perPage}&filter_id={$filters.id|escape:'url'}&filter_global={$filters.global|escape:'url'}&filter_sku={$filters.sku|escape:'url'}&filter_product_name={$filters.product_name|escape:'url'}&filter_category_id={$filters.category_id|escape:'url'}&filter_quantity={$filters.quantity|escape:'url'}&filter_localization={$filters.localization|escape:'url'}&filter_with_glass={$filters.with_glass|escape:'url'}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}">{$pageItem.value}</a>
                      {else}
                        <span class="px-1 text-secondary">...</span>
                      {/if}
                    {/foreach}
                    <a class="btn btn-sm btn-outline-secondary{if $page >= $totalPages} disabled{/if}" href="{$baseUrl}?controller=products&action=index&page={$nextPage}&per_page={$perPage}&filter_id={$filters.id|escape:'url'}&filter_global={$filters.global|escape:'url'}&filter_sku={$filters.sku|escape:'url'}&filter_product_name={$filters.product_name|escape:'url'}&filter_category_id={$filters.category_id|escape:'url'}&filter_quantity={$filters.quantity|escape:'url'}&filter_localization={$filters.localization|escape:'url'}&filter_with_glass={$filters.with_glass|escape:'url'}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}">Nastepna</a>
                    <a class="btn btn-sm btn-outline-secondary{if $page >= $totalPages} disabled{/if}" href="{$baseUrl}?controller=products&action=index&page={$totalPages}&per_page={$perPage}&filter_id={$filters.id|escape:'url'}&filter_global={$filters.global|escape:'url'}&filter_sku={$filters.sku|escape:'url'}&filter_product_name={$filters.product_name|escape:'url'}&filter_category_id={$filters.category_id|escape:'url'}&filter_quantity={$filters.quantity|escape:'url'}&filter_localization={$filters.localization|escape:'url'}&filter_with_glass={$filters.with_glass|escape:'url'}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}">Ostatnia</a>
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
                    <th class="product-compact-cell" style="width:36px;"><input type="checkbox" id="selectAllProducts"></th>
                    <th class="product-compact-cell" style="width: 4%; min-width: 52px;">
                      <a href="{$sortUrls.id|escape}" class="link-dark text-decoration-none">ID {if $sortIndicators.id eq 'asc'}&uarr;{elseif $sortIndicators.id eq 'desc'}&darr;{else}&harr;{/if}</a>
                    </th>
                    <th class="product-sku-cell">
                      <a href="{$sortUrls.sku|escape}" class="link-dark text-decoration-none">SKU {if $sortIndicators.sku eq 'asc'}&uarr;{elseif $sortIndicators.sku eq 'desc'}&darr;{else}&harr;{/if}</a>
                    </th>
                    <th class="product-name-cell">
                      <a href="{$sortUrls.product_name|escape}" class="link-dark text-decoration-none">Nazwa {if $sortIndicators.product_name eq 'asc'}&uarr;{elseif $sortIndicators.product_name eq 'desc'}&darr;{else}&harr;{/if}</a>
                    </th>
                    <th style="width: 100px; min-width: 100px;">
                      <a href="{$sortUrls.category|escape}" class="link-dark text-decoration-none">Kategoria {if $sortIndicators.category eq 'asc'}&uarr;{elseif $sortIndicators.category eq 'desc'}&darr;{else}&harr;{/if}</a>
                    </th>
                    <th class="product-compact-cell"><a href="{$sortUrls.quantity|escape}" class="link-dark text-decoration-none">Ilosc {if $sortIndicators.quantity eq 'asc'}&uarr;{elseif $sortIndicators.quantity eq 'desc'}&darr;{else}&harr;{/if}</a></th>
                    <th style="width: 100px; min-width: 100px;"><a href="{$sortUrls.localization|escape}" class="link-dark text-decoration-none">Lokalizacja {if $sortIndicators.localization eq 'asc'}&uarr;{elseif $sortIndicators.localization eq 'desc'}&darr;{else}&harr;{/if}</a></th>
                    <th style="width: 100px; min-width: 100px;">Wymiary</th>
                    <th class="product-contours-cell">Obrys</th>
                    <th class="product-compact-cell">Zdjecie</th>
                    <th class="product-price-cell">Cena</th>
                    <th class="product-compact-cell">Utworzono</th>
                    <th class="product-compact-cell">Zmieniono</th>
                    <th class="text-end product-actions-cell">Akcje</th>
                  </tr>
                  <tr>
                    <th></th>
                    <th><input type="text" name="filter_id" value="{$filters.id|default:''|escape}" class="form-control form-control-sm" placeholder="np. 15"></th>
                    <th><input type="text" name="filter_sku" value="{$filters.sku|default:''|escape}" class="form-control form-control-sm" placeholder="fragment SKU"></th>
                    <th><input type="text" name="filter_product_name" value="{$filters.product_name|default:''|escape}" class="form-control form-control-sm" placeholder="nazwa produktu"></th>
                    <th>
                      <select name="filter_category_id" class="form-select form-select-sm">
                        <option value="">wszystkie</option>
                        {foreach $categories as $category}
                          <option value="{$category.id}"{if $filters.category_id|default:'' eq $category.id} selected{/if}>{$category.name|escape}</option>
                        {/foreach}
                      </select>
                    </th>
                    <th><input type="text" name="filter_quantity" value="{$filters.quantity|default:''|escape}" class="form-control form-control-sm" placeholder="np. 10 lub 10-50"></th>
                    <th><input type="text" name="filter_localization" value="{$filters.localization|default:''|escape}" class="form-control form-control-sm" placeholder="lokalizacja"></th>
                    <th>
                      <select name="filter_with_glass" class="form-select form-select-sm">
                        <option value=""{if $withGlassFilter eq ''} selected{/if}>szklo: wszystkie</option>
                        <option value="1"{if $withGlassFilter eq '1'} selected{/if}>produkty ze szklem</option>
                        <option value="0"{if $withGlassFilter eq '0'} selected{/if}>produkty bez szkła</option>
                      </select>
                    </th>
                    <th class="text-end" colspan="6">
                    <a href="{$clearFiltersUrl|escape}" class="btn btn-sm btn-warning ">Wyczysc filtry</a><button type="submit" class="btn btn-sm btn-primary" style="margin-left:10px;">Filtruj</button></th>
                  </tr>
                </thead>
                <tbody>
                  {if $products}
                    {foreach $products as $product}
                      <tr class="js-quick-edit-row" data-product-id="{$product.id}">
                        <td><input type="checkbox" class="js-export-checkbox" value="{$product.id}"></td>
                        <td class="product-compact-cell">{$product.id}</td>
                        <td class="product-sku-cell">
                          <span class="badge text-bg-secondary">{$product.sku|escape}</span>
                          {if $product.custom_fields.old_sku|default:'' !== ''}
                            <span class="product-sku-secondary">OLD_SKU: {$product.custom_fields.old_sku|escape}</span>
                          {/if}
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
                          {if $hasSharedPeers || ($product.derived_stock_enabled|default:false)}
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
                        <td>
                          <input type="number" min="0" class="quick-edit-input quick-edit-number js-quick-edit-field" data-field="quantity" value="{$product.quantity|escape}" aria-label="Ilosc">
                        </td>
                        <td>
                          <input type="text" class="quick-edit-input js-quick-edit-field" data-field="localization" value="{$product.localization|default:''|escape}" aria-label="Lokalizacja">
                        </td>
                        <td>
                          <input type="text" class="quick-edit-input js-quick-edit-field" data-field="dimensions" value="{$product.dimensions|default:''|escape}" aria-label="Wymiary">
                        </td>
                        <td class="product-contours-cell" title="{$product.contours|default:'-'|escape}">{$product.contours|default:'-'|escape}</td>
                        <td class="product-compact-cell">{if $product.img}<a href="{$product.img|regex_replace:'/\\s*\\|\\s*.*/':''|escape}" target="_blank" rel="noreferrer">Podglad</a>{else}-{/if}</td>
                        <td class="product-price-cell">
                          <div><strong>B:</strong> {$product.price_gross}</div>
                          <div class="small text-secondary"><strong>N:</strong> {$product.price_net}</div>
                        </td>
                        <td class="product-compact-cell">{$product.created_at|default:'-'}</td>
                        <td class="js-updated-at-cell product-compact-cell">{$product.updated_at|default:'-'}</td>
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
                    <tr><td colspan="14" class="text-center py-4">Brak produktow do wyswietlenia.</td></tr>
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
              <a class="btn btn-sm btn-outline-secondary{if $page <= 1} disabled{/if}" href="{$baseUrl}?controller=products&action=index&page={$page-1}&per_page={$perPage}&filter_id={$filters.id|escape:'url'}&filter_global={$filters.global|escape:'url'}&filter_sku={$filters.sku|escape:'url'}&filter_product_name={$filters.product_name|escape:'url'}&filter_category_id={$filters.category_id|escape:'url'}&filter_quantity={$filters.quantity|escape:'url'}&filter_localization={$filters.localization|escape:'url'}&filter_with_glass={$filters.with_glass|escape:'url'}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}">Poprzednia</a>
              <a class="btn btn-sm btn-outline-secondary{if $page >= $totalPages} disabled{/if}" href="{$baseUrl}?controller=products&action=index&page={$page+1}&per_page={$perPage}&filter_id={$filters.id|escape:'url'}&filter_global={$filters.global|escape:'url'}&filter_sku={$filters.sku|escape:'url'}&filter_product_name={$filters.product_name|escape:'url'}&filter_category_id={$filters.category_id|escape:'url'}&filter_quantity={$filters.quantity|escape:'url'}&filter_localization={$filters.localization|escape:'url'}&filter_with_glass={$filters.with_glass|escape:'url'}&sort_by={$sortBy|escape:'url'}&sort_dir={$sortDir|escape:'url'}">Nastepna</a>
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
      <form method="post" action="{$baseUrl}?controller=csvtemplates&action=exportcsv" id="csvExportForm">
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
              {foreach $exportTemplates as $tpl}
                <option value="{$tpl.id}">{$tpl.name|escape}</option>
              {/foreach}
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label d-block">Zakres eksportu</label>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="export_mode" id="exportSelected" value="selected" checked>
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
                <input type="text" name="collection_name" class="form-control form-control-sm" placeholder="np. Marble">
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
                <label class="form-label small">Kolekcja numeracja</label>
                <input type="text" name="image_collection_code" class="form-control form-control-sm" placeholder="np. A">
              </div>
              <div class="col-md-8">
                <label class="form-label small">Nazwa kolekcji dla obrazow</label>
                <input type="text" name="image_collection_name" class="form-control form-control-sm" placeholder="np. KOLEKCJA">
              </div>
              <div class="col-md-6">
                <label class="form-label small">Dopisanie do nazwy obrazow</label>
                <input type="text" name="image_title_suffix" class="form-control form-control-sm" placeholder="np. smooth">
              </div>
                <div class="col-md-6">
                <label class="form-label small">Cena </label>
                <input type="text" name="price_to_csv" class="form-control form-control-sm" placeholder="35">
              </div>
              <div class="col-md-2">
                <label class="form-label small">Ilosc zdjec</label>
                <input type="number" min="0" name="image_count" class="form-control form-control-sm" value="0">
              </div>
              <div class="col-md-2">
                <label class="form-label small">Ilosc miniatur</label>
                <input type="number" min="0" name="thumbnail_count" class="form-control form-control-sm" value="0">
              </div>
              <div class="col-md-2">
                <label class="form-label small">Grid/mockup</label>
                <input type="number" min="0" name="grid_count" class="form-control form-control-sm" value="0">
              </div>
              <div class="col-12">
                <label class="form-label small">Bazowy katalog</label>
                <input type="text" name="image_base_directory" class="form-control form-control-sm" value="T:\wygnerowane_do_EU\">
              </div>
            </div>
            <div class="form-text">
              W szablonie uzyj pola <code>product.allegro_parameters</code> dla parametrow Allegro oraz
              <code>images</code> albo <code>product.generated_images</code> dla listy sciezek obrazow.
            </div>
          </div>
          <div id="selectedProductIdsContainer"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
          <button type="submit" class="btn btn-primary"{if !$exportTemplates} disabled{/if}>Generuj CSV</button>
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
  var selectedContainer = document.getElementById('selectedProductIdsContainer');
  var exportSelected = document.getElementById('exportSelected');

  // Bulk operations
  var bulkCopyBtn = document.getElementById('bulkCopyBtn');
  var bulkCategoryBtn = document.getElementById('bulkCategoryBtn');
  var bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
  var bulkExportBtn = document.getElementById('bulkExportBtn');
  var bulkActionsPanel = document.getElementById('bulkActionsPanel');


  var bulkCategoryModal = new bootstrap.Modal(document.getElementById('bulkCategoryModal'));
  var bulkDeleteModal = new bootstrap.Modal(document.getElementById('bulkDeleteModal'));
  var bulkCopyModal = new bootstrap.Modal(document.getElementById('bulkCopyModal'));

  var bulkCategoryForm = document.getElementById('bulkCategoryForm');
  var bulkDeleteForm = document.getElementById('bulkDeleteForm');
  var bulkCopyForm = document.getElementById('bulkCopyForm');
  var quickUpdateUrl = '{$baseUrl|escape:"javascript"}?controller=products&action=quickupdate';
  var lastCheckedCheckbox = null;

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
    if (bulkActionsPanel) {
      if (count > 0) {
        bulkActionsPanel.style.display = 'block';
      } else {
        bulkActionsPanel.style.display = 'none';
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
  }

  if (selectAll) {
    selectAll.addEventListener('change', function () {
      for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = selectAll.checked;
      }
      updateCount();
    });
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
      var productList = '<ul>';
      for (var i = 0; i < products.length; i++) {
        productList += '<li>' + products[i].sku + ' - ' + products[i].name + '</li>';
      }
      productList += '</ul>';
      document.getElementById('bulkCopyProductList').innerHTML = productList;

      var container = document.getElementById('bulkCopyProductIdsContainer');
      container.innerHTML = '';
      for (var i = 0; i < ids.length; i++) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'product_ids[]';
        input.value = ids[i];
        container.appendChild(input);
      }

      bulkCopyModal.show();
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
      // Ensure "selected" mode is checked
      if (exportSelected) {
        exportSelected.checked = true;
      }
      // Update the selected count in the modal
      var countSpan = document.getElementById('selectedCount');
      if (countSpan) {
        countSpan.textContent = String(ids.length);
      }
      // Show the CSV export modal
      var csvModal = new bootstrap.Modal(document.getElementById('csvExportModal'));
      csvModal.show();
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
      }
    });
  }

  updateCount();
});
</script>
