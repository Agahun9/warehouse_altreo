<div class="container-fluid py-4 computers-products-page">
  <ul class="nav nav-tabs mb-4">
    <li class="nav-item">
      <a class="nav-link{if $computerTab eq 'products'} active{/if}" href="{$baseUrl}?controller=computers&action=products">Produkty</a>
    </li>
    <li class="nav-item">
      <a class="nav-link{if $computerTab eq 'components'} active{/if}" href="{$baseUrl}?controller=computers&action=components">Komponenty</a>
    </li>
    <li class="nav-item">
      <a class="nav-link{if $computerTab eq 'csvtemplates'} active{/if}" href="{$baseUrl}?controller=computers&action=csvtemplates">Szablony CSV</a>
    </li>
    <li class="nav-item">
      <a class="nav-link{if $computerTab eq 'titletemplates'} active{/if}" href="{$baseUrl}?controller=computers&action=titletemplates">Szablony tytułów</a>
    </li>
  </ul>
  <!-- Nagłówek strony -->
  <div class="d-flex align-items-center mb-4 justify-content-between">
    <div class="me-3">
      <span class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width:48px; height:48px; font-size:2rem;"><i class="bi bi-box-seam"></i></span>
    </div>
    <div>
      <h1 class="h3 mb-0">Panel produktów</h1>
      <small class="text-muted">Zarządzaj produktami, komponentami i wariantami</small>
    </div>
    <div class="ms-3">
      <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#variantsPanel" aria-expanded="false" aria-controls="variantsPanel" id="toggleVariantsBtn">
        <i class="bi bi-layout-sidebar-inset-reverse"></i> Warianty
      </button>
    </div>
  </div>

  <!-- Komunikaty -->
  {if $success}
    <div class="alert alert-success alert-dismissible fade show shadow mb-4" role="alert" id="successAlert">
      <i class="bi bi-check-circle me-2"></i>{$success|escape:'html'}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  {/if}
  {if $errors}
    <div class="alert alert-danger alert-dismissible fade show shadow mb-4" role="alert">
      <i class="bi bi-exclamation-triangle me-2"></i>
      <ul class="mb-0 ps-3">
        {foreach from=$errors item=err}
          <li>{$err|escape:'html'}</li>
        {/foreach}
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  {/if}

  <div class="row g-4">
    <!-- Panel generowania wariantów (domyślnie zwinięty) -->
    <div class="col-12 col-lg-4 collapse" id="variantsPanel">
      <div class="card shadow-sm h-100">
        <div class="card-header bg-primary text-white">
          <i class="bi bi-layers me-2"></i>Generowanie wariantów produktów
        </div>
        <div class="card-body">
          <form method="post" action="">
            {foreach from=$grouped key=category item=comps}
              <fieldset class="mb-3 border rounded p-3">
                <legend class="float-none w-auto px-2 fs-6 fw-bold">{$category|escape:'html'}</legend>
                <button type="button" class="btn btn-sm btn-outline-primary mb-2 toggle-category-btn" data-category="cat_{$category|escape:'html'|replace:' ':'_'}"><i class="bi bi-arrow-repeat"></i> Odwróć zaznaczenie</button>
                <div class="d-flex flex-wrap gap-2">
                  {foreach from=$comps item=comp}
                    <label class="form-check-label" style="min-width:180px;">
                      <input type="checkbox" name="components[]" value="{$comp.id}" class="form-check-input cat_{$category|escape:'html'|replace:' ':'_'}" />
                      {$comp.name|escape:'html'} <span class="text-muted">({$comp.price} zł)</span>
                    </label>
                  {/foreach}
                </div>
              </fieldset>
            {/foreach}
            <div class="mb-3">
              <label for="profit" class="form-label">Marża (profit) w zł:</label>
              <input type="number" step="0.01" id="profit" name="profit" value="{$profit|default:0}" class="form-control" />
            </div>
            <div class="mb-3">
              <label for="title_template_id" class="form-label">Szablon tytułu aukcji:</label>
              <select id="title_template_id" name="title_template_id" class="form-select">
                <option value="0">Domyślny tytuł wariantu</option>
                {foreach from=$titleTemplates item=titleTemplate}
                  <option value="{$titleTemplate.id}"{if $selectedTitleTemplateId|default:0 eq $titleTemplate.id} selected{/if}>{$titleTemplate.name|escape:'html'}</option>
                {/foreach}
              </select>
              <div class="form-text">Używa osobnych szablonów tytułów z zakładki Komputery.</div>
            </div>
            
            <button type="submit" name="create_variants" class="btn btn-primary w-100"><i class="bi bi-plus-circle me-1"></i>Generuj warianty produktów</button>
          </form>
        </div>
      </div>
    </div>

  <!-- Panel filtrów i akcji masowych + produkty -->
  <!-- Domyślnie: pełna szerokość (col-lg-12) aby uniknąć migania/layout shift przy ładowaniu -->
  <div class="col-12 col-lg-12" id="productsCol">
      <div class="card shadow-sm mb-4">
        {* <div class="card-header bg-light fw-bold"><i class="bi bi-funnel me-2"></i>Filtruj produkty</div> <a href="https://magazyn.altreo.pl/crm/allegro_api_synchro.php?action=ACCRA_SHOP_COMPUTERS" class="btn btn-sm btn-outline-secondary float-end"><i class="bi bi-arrow-clockwise"></i> Synchronizuj z ACCRA_SHOP</a> *}
        <div class="card-body pb-2">
          <form method="get" action="{$baseUrl}" class="row g-3 align-items-end">
            <input type="hidden" name="controller" value="computers" />
            <input type="hidden" name="action" value="products" />
            <input type="hidden" name="per_page" id="filter_per_page_input" value="{$per_page}" />
            <input type="hidden" name="page" id="filter_page_input" value="{$current_page}" />
            <div class="col-md-4">
              <label for="filter_name" class="form-label fw-bold">Nazwa produktu</label>
              <input type="text" id="filter_name" name="filter_name" value="{$filterName|escape:'html'}" class="form-control" placeholder="Wpisz nazwę..." />
            </div>
            <div class="col-md-2">
              <label for="filter_market_accounts" class="form-label fw-bold">Wystawione aukcje na kontach</label>
              <select id="filter_market_accounts" name="filter_market_accounts[]" class="form-control" multiple size="7">
                <optgroup label="Ogólne">
                  <option value="1" {if in_array('1', $filterMarketAccounts)}selected{/if}>Wystawione gdziekolwiek</option>
                  <option value="0" {if in_array('0', $filterMarketAccounts)}selected{/if}>Bez aktywnych aukcji</option>
                </optgroup>
                <optgroup label="Allegro">
                  {foreach from=$allegroMarketAccounts item=marketAccount}
                    <option value="{$marketAccount.filter_value|escape:'html'}" {if $marketAccount.selected}selected{/if}>{$marketAccount.name|escape:'html'}</option>
                  {foreachelse}
                    <option value="" disabled>Brak aktywnych kont Allegro</option>
                  {/foreach}
                </optgroup>
                <optgroup label="Empik">
                  {foreach from=$empikMarketAccounts item=marketAccount}
                    <option value="{$marketAccount.filter_value|escape:'html'}" {if $marketAccount.selected}selected{/if}>{$marketAccount.name|escape:'html'}</option>
                  {foreachelse}
                    <option value="" disabled>Brak aktywnych kont Empik</option>
                  {/foreach}
                </optgroup>
                <optgroup label="Erli">
                  {foreach from=$erliMarketAccounts item=marketAccount}
                    <option value="{$marketAccount.filter_value|escape:'html'}" {if $marketAccount.selected}selected{/if}>{$marketAccount.name|escape:'html'}</option>
                  {foreachelse}
                    <option value="" disabled>Brak aktywnych kont Erli</option>
                  {/foreach}
                </optgroup>
                <optgroup label="Morele">
                  {foreach from=$moreleMarketAccounts item=marketAccount}
                    <option value="{$marketAccount.filter_value|escape:'html'}" {if $marketAccount.selected}selected{/if}>{$marketAccount.name|escape:'html'}</option>
                  {foreachelse}
                    <option value="" disabled>Brak aktywnych ofert Morele</option>
                  {/foreach}
                </optgroup>
              </select>
              <div class="form-text">Ctrl/Cmd pozwala wybrac kilka kont.</div>
            </div>
<div class="col-md-6">
  <label class="form-label fw-bold">Komponenty (można wiele):</label>
  <div class="border rounded p-2 bg-light" 
       style="overflow-y: auto; resize: vertical; height: 150px;">
    
    {assign var="currentCategory" value=""}
    <div class="row">
      {foreach from=$components item=comp}
        {if $comp.category != $currentCategory}
          {assign var="currentCategory" value=$comp.category}
          <div class="col-12 mt-2 mb-1">
            <strong>{$comp.category|escape:'html'}</strong>
          </div>
        {/if}

        <div class="col-md-6">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="filter_components[]" value="{$comp.id}"
                   {if in_array($comp.id, $filterComponents)}checked{/if} id="filter_comp_{$comp.id}" />
            <label class="form-check-label small" for="filter_comp_{$comp.id}">
              {$comp.name|escape:'html'}
            </label>
          </div>
        </div>
      {/foreach}
    </div>

  </div>
</div>


            <div class="col-md-2 d-grid gap-2">
              <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Filtruj</button>
              <a href="{$baseUrl}?controller=computers&action=products" class="btn btn-outline-secondary">Wyczysc</a>
            </div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
          <span class="fw-bold"><i class="bi bi-table me-2"></i>Lista produktów</span>
          <div>
            <button type="button" id="select_all_btn" class="btn btn-sm btn-outline-secondary me-1"><i class="bi bi-check2-square"></i> Zaznacz tę stronę</button>
            <button type="button" id="select_all_filtered_btn" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-check2-all"></i> Zaznacz na wszystkich stronach</button>
            <button type="button" id="deselect_all_btn" class="btn btn-sm btn-outline-secondary"><i class="bi bi-square"></i> Odznacz wszystkie</button>
          </div>
        </div>
        <div class="card-body pb-0">
          <!-- Top pagination: page links + per_page + counter -->
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center">
              <nav aria-label="Pagination top">
                <ul class="pagination pagination-sm mb-0 me-3">
                  {foreach from=$page_links item=pl}
                    {if $pl.ellipsis}
                      <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                    {else}
                      <li class="page-item {if $pl.is_current}active{/if}"><a class="page-link" href="{$pagination_base_query}page={$pl.num}&per_page={$per_page}">{$pl.num}</a></li>
                    {/if}
                  {/foreach}
                </ul>
              </nav>
              <label class="form-label small mb-0 me-2">Pokaż:</label>
              <select id="per_page_select" class="form-select form-select-sm d-inline-block" style="width:auto;">
                {foreach from=[10,20,50,100,1000,10000] item=pp}
                  <option value="{$pp}" {if $pp == $per_page}selected{/if}>{$pp}</option>
                {/foreach}
              </select>
            </div>
            <div class="small text-muted">
              Wyświetlono {$shown_count} z {$total_products} produktów
            </div>
          </div>
          <form method="post" action="" enctype="multipart/form-data" id="productsBulkForm">
            <input type="hidden" name="selection_scope" id="selection_scope" value="page" />
            <input type="hidden" name="selection_filter_name" value="{$filterName|escape:'html'}" />
            {foreach from=$filterComponents item=filterComponentId}
              <input type="hidden" name="selection_filter_components[]" value="{$filterComponentId}" />
            {/foreach}
            {foreach from=$filterMarketAccounts item=filterMarketAccount}
              <input type="hidden" name="selection_filter_market_accounts[]" value="{$filterMarketAccount|escape:'html'}" />
            {/foreach}
            <div id="excluded_product_ids"></div>
            <div id="all_filtered_selection_notice" class="alert alert-primary py-2 px-3 mb-3 d-none" role="status">
              Zaznaczono wszystkie produkty zgodne z bieżącymi filtrami: <strong>{$total_products}</strong>.
              Odznaczone ręcznie pozycje zostaną pominięte.
            </div>
            <div class="mb-3">
              <div class="dropdown d-inline-block me-2">
                <button class="btn btn-primary dropdown-toggle" type="button" id="bulkActionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="bi bi-lightning me-1"></i>Akcje masowe
                </button>
                <ul class="dropdown-menu" aria-labelledby="bulkActionsDropdown">
<li><a class="dropdown-item" href="#" onclick="setBulkAction('delete'); return false;">
    <i class="bi bi-trash me-1 text-danger"></i>Usuń wybrane
</a></li>

<li><a class="dropdown-item" href="#" onclick="setBulkAction('change_profit'); return false;">
    <i class="bi bi-percent me-1"></i>Zmień marżę
</a></li>


<li><a class="dropdown-item" href="#" onclick="setBulkAction('replace_name'); return false;">
    <i class="bi bi-type me-1"></i>Znajdź i zamień w nazwie
</a></li>

<li><a class="dropdown-item" href="#" onclick="setBulkAction('change_images'); return false;">
    <i class="bi bi-images me-1"></i>Zmień grafikę
</a></li>

<li><a class="dropdown-item" href="#" onclick="setBulkAction('set_ean'); return false;">
    <i class="bi bi-file-earmark-bar-graph me-1"></i>Export CSV z do EAN
</a></li>

<li><a class="dropdown-item" href="#" onclick="setBulkAction('import_ean'); return false;">
    <i class="bi bi-file-earmark-arrow-up me-1"></i>Import CSV z EAN
</a></li>

<li><a class="dropdown-item" href="#" onclick="setBulkAction('update_price'); return false;">
    <i class="bi bi-cash-stack me-1"></i>Aktualizuj ceny z magazynem
</a></li>

<li><hr class="dropdown-divider"></li>

<li><a class="dropdown-item" href="#" onclick="setBulkAction('add_component'); return false;">
    <i class="bi bi-plus-circle me-1"></i>Dodaj komponent
</a></li>

<li><a class="dropdown-item" href="#" onclick="setBulkAction('replace_component'); return false;">
    <i class="bi bi-arrow-repeat me-1"></i>Zamień komponent
</a></li>

<li><a class="dropdown-item" href="#" onclick="setBulkAction('remove_component'); return false;">
    <i class="bi bi-dash-circle me-1"></i>Usuń komponent
</a></li>
                </ul>
              </div>
              <button type="submit" class="btn btn-success"><i class="bi bi-play-circle me-1"></i>Wykonaj akcję</button>
              <div class="d-inline-flex align-items-center gap-2 ms-2">
                <select name="csv_template_id" class="form-select" style="width:220px" aria-label="Szablon eksportu CSV">
                  <option value="">Szablon eksportu CSV...</option>
                  {foreach from=$csvTemplates item=csvTemplate}
                    <option value="{$csvTemplate.id}">{$csvTemplate.name|escape:'html'} ({$csvTemplate.columns_count})</option>
                  {/foreach}
                </select>
                <button type="submit" formaction="{$baseUrl}?controller=computers&action=exportcsv" formmethod="post" class="btn btn-outline-primary">
                  <i class="bi bi-file-earmark-spreadsheet me-1"></i>Eksportuj CSV
                </button>
                <a href="{$baseUrl}?controller=computers&action=csvtemplates" class="btn btn-outline-secondary" title="Szablony CSV">
                  <i class="bi bi-gear"></i>
                </a>
              </div>
            </div>
            <input type="hidden" name="bulk_action" id="bulk_action" value="" />
            <div id="price_marketplace_selected_inputs"></div>
            <div id="bulk_action_fields" style="display:none; margin-bottom:20px;">
              <div id="profit_field" style="display:none; max-width: 300px;">
                <label for="bulk_profit" class="form-label">Nowa marża (profit) w zł:</label>
                <input type="number" step="0.01" id="bulk_profit" name="bulk_profit" class="form-control" />
                <p class="mt-2 text-muted small">Zaznaczone produkty otrzymają nową wartość marży (profit). Cena produktu zostanie automatycznie przeliczona.</p>
              </div>
              <div id="replace_name_fields" style="display:none; max-width: 400px;">
                <label for="bulk_find" class="form-label">Znajdź w nazwie:</label>
                <input type="text" id="bulk_find" name="bulk_find" class="form-control mb-2" />
                <label for="bulk_replace" class="form-label">Zamień na:</label>
                <input type="text" id="bulk_replace" name="bulk_replace" class="form-control" />
                <p class="mt-2 text-muted small">Wszystkie zaznaczone produkty będą miały w nazwie podmieniony wskazany tekst.</p>
              </div>
              <div id="change_images_field" style="display:none; max-width: 400px;">
                <label for="bulk_img" class="form-label">Wybierz plik obrazu:</label>
                <input type="file" id="bulk_img" name="bulk_img" accept=".jpg,.jpeg,.png,.gif,.webp" class="form-control" />
                <div class="form-text mt-2">Wybierz cel aktualizacji obrazu:</div>
                <select name="bulk_img_target" id="bulk_img_target" class="form-select form-select-sm mt-1" style="max-width:200px;">
                  <option value="img">ALLEGRO (img)</option>
                  <option value="img_morele">Morele (img_morele)</option>
                  <option value="img_empik">Empik (img_empik)</option>
                </select>
                <p class="mt-2 text-muted small">Wybrany obrazek zostanie ustawiony dla wszystkich zaznaczonych produktów (zastąpi obecny).</p>
              </div>
              <div id="import_ean_field" style="display:none; max-width: 400px;">
                <label for="CSV_ean" class="form-label">Wybierz plik CSV z EAN:</label>
                <input type="file" id="CSV_ean" type="file" name="csv_file" accept=".csv" class="form-control" />
                <p class="mt-2 text-muted small">Wyślij plik CSV z EAN do systemu. Tej operacji nie można cofnąć!</p>
              </div>
              <div id="set_ean_field" style="display:none; max-width: 400px;">
                <p class="mb-2">Zaznaczone produkty zostaną wyeksportowane do pliku CSV (Do uzupełnienia EAN).</p>
              </div>
              <div id="delete_field" style="display:none; max-width: 400px;">
                <p class="mb-2 text-danger">Zaznaczone produkty zostaną trwale usunięte z systemu. Tej operacji nie można cofnąć!</p>
              </div>
               <div id="update_price_field" style="display:none; max-width: 400px;">
                <p class="mb-2 text-danger">Synchronizuj ceny produktów z magazynem. Tej operacji nie można cofnąć!</p>
              </div>
              <div id="add_component_field" style="display:none; max-width: 400px;">
                <label for="bulk_add_comp_id" class="form-label">Wybierz komponent do dodania:</label>
                <select name="bulk_add_comp_id" id="bulk_add_comp_id" class="form-select bulk-component-select">
                  <option value="">-- Wybierz komponent --</option>
                  {foreach from=$grouped item=comps key=category}
                    <optgroup label="{$category|escape:'html'}">
                      {foreach from=$comps item=comp}
                        <option value="{$comp.id}" title="{$comp.name_title|escape:'html'} ({$comp.price|number_format:2:',':'.'} zł)">{$comp.name|escape:'html'} ({$comp.price|number_format:2:',':'.'} zł)</option>
                      {/foreach}
                    </optgroup>
                  {/foreach}
                </select>
                <p class="mt-2 text-muted small">Wybrany komponent zostanie dodany do wszystkich zaznaczonych produktów (jeśli go jeszcze nie mają).</p>
              </div>
              <div id="replace_component_field" style="display:none; max-width: 400px;">
                <label for="bulk_replace_from_id" class="form-label">Komponent źródłowy (do zamiany):</label>
                <select name="bulk_replace_from_id" id="bulk_replace_from_id" class="form-select bulk-component-select mb-3">
                  <option value="">-- Wybierz komponent --</option>
                  {foreach from=$grouped item=comps key=category}
                    <optgroup label="{$category|escape:'html'}">
                      {foreach from=$comps item=comp}
                        <option value="{$comp.id}" title="{$comp.name_title|escape:'html'} ({$comp.price|number_format:2:',':'.'} zł)">{$comp.name|escape:'html'} ({$comp.price|number_format:2:',':'.'} zł)</option>
                      {/foreach}
                    </optgroup>
                  {/foreach}
                </select>
                <label for="bulk_replace_to_id" class="form-label">Komponent docelowy (na co zamienić):</label>
                <select name="bulk_replace_to_id" id="bulk_replace_to_id" class="form-select bulk-component-select">
                  <option value="">-- Wybierz komponent --</option>
                  {foreach from=$grouped item=comps key=category}
                    <optgroup label="{$category|escape:'html'}">
                      {foreach from=$comps item=comp}
                        <option value="{$comp.id}" title="{$comp.name_title|escape:'html'} ({$comp.price|number_format:2:',':'.'} zł)">{$comp.name|escape:'html'} ({$comp.price|number_format:2:',':'.'} zł)</option>
                      {/foreach}
                    </optgroup>
                  {/foreach}
                </select>
                <p class="mt-2 text-muted small">Będą zamienione tylko te produkty, które mają już komponent źródłowy. Cena zostanie automatycznie przeliczona.</p>
              </div>
              <div id="remove_component_field" style="display:none; max-width: 400px;">
                <label for="bulk_remove_comp_id" class="form-label">Wybierz komponent do usunięcia:</label>
                <select name="bulk_remove_comp_id" id="bulk_remove_comp_id" class="form-select bulk-component-select">
                  <option value="">-- Wybierz komponent --</option>
                  {foreach from=$grouped item=comps key=category}
                    <optgroup label="{$category|escape:'html'}">
                      {foreach from=$comps item=comp}
                        <option value="{$comp.id}" title="{$comp.name_title|escape:'html'} ({$comp.price|number_format:2:',':'.'} zł)">{$comp.name|escape:'html'} ({$comp.price|number_format:2:',':'.'} zł)</option>
                      {/foreach}
                    </optgroup>
                  {/foreach}
                </select>
                <p class="mt-2 text-muted small">Wybrany komponent zostanie usunięty ze wszystkich zaznaczonych produktów (jeśli go mają). Cena zostanie automatycznie przeliczona.</p>
              </div>
            </div>
            <div class="row g-3">
              {foreach from=$products item=prod}
                {assign var="component_price_sum" value=0}
                {foreach from=$prod.components item=comp}
                  {assign var="component_price_sum" value=$component_price_sum + $comp.price}
                {/foreach}
                {assign var="allegro_price" value=$prod.price_allegro|default:''}
                {assign var="calculation_price" value=$prod.price}
                {if $allegro_price != ''}
                  {assign var="calculation_price" value=$allegro_price}
                {/if}

{assign var="commission" value=$calculation_price*0.0246}
{assign var="commission_highlight" value=$calculation_price*0.0431}
{assign var="profit_net" value=$calculation_price - $component_price_sum - $commission}
{assign var="profit_highlight_net" value=$calculation_price - $component_price_sum - $commission_highlight}

                <div class="col-12">
                  <div class="card mb-2 border border-primary-subtle shadow-sm product-card">
                    <div class="card-body p-3">
                      <div class="d-flex flex-wrap align-items-center justify-content-between">
                        <div class="d-flex align-items-center mb-2 mb-md-0">
                          <input type="checkbox" name="product_ids[]" value="{$prod.id}" class="product_checkbox me-2" data-price-market-accounts="{foreach from=$prod.allegro_accounts item=allegroAccount}allegro:{$allegroAccount.account_id|escape:'html'}|Allegro {$allegroAccount.account_name|escape:'html'}||{/foreach}{foreach from=$prod.empik_accounts item=empikAccount}empik:{$empikAccount.account_id|escape:'html'}|Empik {$empikAccount.account_name|escape:'html'}||{/foreach}{foreach from=$prod.erli_accounts item=erliAccount}erli:{$erliAccount.account_id|escape:'html'}|Erli {$erliAccount.account_name|escape:'html'}||{/foreach}{foreach from=$prod.morele_accounts item=moreleAccount}morele:{$moreleAccount.account_id|escape:'html'}|Morele {$moreleAccount.account_name|escape:'html'}||{/foreach}" />
                          <span class="text-muted small me-3">ID: {$prod.id}</span>
                        
              {if $prod.offerid != '' && $prod.offerid != 0}<a href="https://allegro.pl/oferta/{$prod.offerid}"><strong class="product-title" data-prod-id="{$prod.id}">{$prod.name|escape:'html'}</strong> -- {$prod.offerid}</a>
              {else}
                <strong class="product-title" data-prod-id="{$prod.id}">{$prod.name|escape:'html'}</strong>
              {/if}
                          {if $prod.allegro_accounts|@count > 0}
                            <span class="ms-2 d-inline-flex flex-wrap gap-1 align-items-center">
                              {foreach from=$prod.allegro_accounts item=allegroAccount}
                                <a href="https://allegro.pl/oferta/{$allegroAccount.offer_id|escape:'url'}" target="_blank" rel="noreferrer" class="badge text-bg-warning text-decoration-none" title="SKU: {$allegroAccount.sku|escape:'html'}">
                                  {$allegroAccount.account_name|escape:'html'}
                                  {if $allegroAccount.price_amount != ''}
                                    {$allegroAccount.price_amount|number_format:2:',':'.'} zl
                                  {/if}
                                </a>
                              {/foreach}
                            </span>
                          {/if}
                          {if $prod.erli_accounts|@count > 0}
                            <span class="ms-2 d-inline-flex flex-wrap gap-1 align-items-center">
                              {foreach from=$prod.erli_accounts item=erliAccount}
                                <a href="{$erliAccount.erli_url|escape:'html'}" target="_blank" rel="noreferrer" class="badge text-bg-success text-decoration-none" title="SKU: {$erliAccount.sku|escape:'html'} | External ID: {$erliAccount.external_id|escape:'html'}">
                                  Erli {$erliAccount.account_name|escape:'html'}
                                  {if $erliAccount.price_amount != ''}
                                    {$erliAccount.price_amount|number_format:2:',':'.'} zl
                                  {/if}
                                </a>
                              {/foreach}
                            </span>
                          {/if}
                          {if $prod.empik_accounts|@count > 0}
                            <span class="ms-2 d-inline-flex flex-wrap gap-1 align-items-center">
                              {foreach from=$prod.empik_accounts item=empikAccount}
                                <a href="{$empikAccount.empik_url|escape:'html'}" target="_blank" rel="noreferrer" class="badge text-bg-info text-decoration-none" title="SKU: {$empikAccount.sku|escape:'html'} | Oferta: {$empikAccount.offer_id|escape:'html'}">
                                  Empik {$empikAccount.account_name|escape:'html'}
                                  {if $empikAccount.price_amount != ''}
                                    {$empikAccount.price_amount|number_format:2:',':'.'} zl
                                  {/if}
                                </a>
                              {/foreach}
                            </span>
                          {/if}
                          {if $prod.morele_accounts|@count > 0}
                            <span class="ms-2 d-inline-flex flex-wrap gap-1 align-items-center">
                              {foreach from=$prod.morele_accounts item=moreleAccount}
                                <a href="{$moreleAccount.morele_url|escape:'html'}" target="_blank" rel="noreferrer" class="badge text-bg-primary text-decoration-none" title="SKU: {$moreleAccount.sku|escape:'html'} | Oferta: {$moreleAccount.external_id|escape:'html'}">
                                  Morele {$moreleAccount.account_name|escape:'html'}
                                  {if $moreleAccount.price_amount != ''}
                                    {$moreleAccount.price_amount|number_format:2:',':'.'} zl
                                  {/if}
                                </a>
                              {/foreach}
                            </span>
                          {/if}
                          <span class="product-title-counter ms-2 small text-muted" data-prod-id="{$prod.id}"></span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                          {if $prod.img}
                            Allegro
                            <img src="{$productsImageBase}/{$prod.img|escape:'html'}" alt="Obrazek" class="img-fluid product-img-thumb rounded border" style="max-height:48px; max-width:64px; object-fit:contain; cursor:pointer;" data-img="{$productsImageBase}/{$prod.img|escape:'html'}" />
                          {/if}
                          <input type="file" name="products[{$prod.id}][img_file]" accept=".jpg,.jpeg,.png,.gif,.webp" class="form-control form-control-sm mb-1" style="max-width:140px;" />

                          <!-- Morele image -->
                          {if $prod.img_morele}
                            Morele
                            <img src="{$productsImageBase}/{$prod.img_morele|escape:'html'}" alt="Obrazek Morele" class="img-fluid product-img-thumb rounded border" style="max-height:48px; max-width:64px; object-fit:contain; cursor:pointer; margin-left:6px;" data-img="{$productsImageBase}/{$prod.img_morele|escape:'html'}" />
                          {/if}
                          <input type="file" name="products[{$prod.id}][img_morele_file]" accept=".jpg,.jpeg,.png,.gif,.webp" class="form-control form-control-sm" style="max-width:140px;" />

                          <!-- Empik image -->
                          {if $prod.img_empik}
                            Empik
                            <img src="{$productsImageBase}/{$prod.img_empik|escape:'html'}" alt="Obrazek Empik" class="img-fluid product-img-thumb rounded border" style="max-height:48px; max-width:64px; object-fit:contain; cursor:pointer; margin-left:6px;" data-img="{$productsImageBase}/{$prod.img_empik|escape:'html'}" />
                          {/if}
                          <input type="file" name="products[{$prod.id}][img_empik_file]" accept=".jpg,.jpeg,.png,.gif,.webp" class="form-control form-control-sm" style="max-width:140px;" />
                        </div>
                      </div>
                      <div class="row mt-2">
                        <div class="col-12 col-lg-8 mb-2">
                          <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-2 align-middle" style="min-width:340px; background:#fff;">
                              <tbody>
                           <tr>
    <th class="bg-light">Cena (zł)</th>
    <td class="product-price-cell{if $allegro_price != '' && $prod.price != $allegro_price} product-price-cell--mismatch{/if}">
        <div class="market-price-list">
          <div class="market-price-row market-price-row--warehouse">
            <span class="market-price-logo market-price-logo--warehouse">
              <img src="https://cdn-icons-png.flaticon.com/256/3361/3361571.png" alt="Magazyn">
            </span>
            <span class="market-price-name">Magazyn</span>
            <span class="market-price-value">{$prod.price|number_format:2:',':'.'} zl</span>
          </div>

          {if $prod.allegro_accounts|@count > 0}
            {foreach from=$prod.allegro_accounts item=allegroAccount}
              <a href="https://allegro.pl/oferta/{$allegroAccount.offer_id|escape:'url'}" target="_blank" rel="noreferrer" class="market-price-row market-price-row--link market-price-row--allegro">
                <span class="market-price-logo">
                  <img src="https://allegro.pl/favicon.ico" alt="Allegro">
                </span>
                <span class="market-price-name">Allegro <strong>{$allegroAccount.account_name|escape:'html'}</strong></span>
                <span class="market-price-value">
                  {if $allegroAccount.price_amount != ''}
                    {$allegroAccount.price_amount|number_format:2:',':'.'} zl
                  {else}
                    cena ?
                  {/if}
                </span>
              </a>
            {/foreach}
          {else}
            <div class="market-price-row market-price-row--muted">
              <span class="market-price-logo">
                <img src="https://allegro.pl/favicon.ico" alt="Allegro">
              </span>
              <span class="market-price-name">Allegro</span>
              <span class="market-price-value">brak aktywnej oferty</span>
            </div>
          {/if}

          {foreach from=$prod.empik_accounts item=empikAccount}
            <a href="{$empikAccount.empik_url|escape:'html'}" target="_blank" rel="noreferrer" class="market-price-row market-price-row--link market-price-row--empik">
              <span class="market-price-logo">
                <img src="https://www.empik.com//b/mp/img/favicons/favicon-96x96.png" alt="Empik">
              </span>
              <span class="market-price-name">Empik <strong>{$empikAccount.account_name|escape:'html'}</strong></span>
              <span class="market-price-value">
                {if $empikAccount.price_amount != ''}
                  {$empikAccount.price_amount|number_format:2:',':'.'} zl
                {else}
                  cena ?
                {/if}
              </span>
            </a>
          {/foreach}

          {foreach from=$prod.erli_accounts item=erliAccount}
            <a href="{$erliAccount.erli_url|escape:'html'}" target="_blank" rel="noreferrer" class="market-price-row market-price-row--link market-price-row--erli">
              <span class="market-price-logo">
                <img src="https://erli.pl/favicon.ico" alt="Erli">
              </span>
              <span class="market-price-name">Erli <strong>{$erliAccount.account_name|escape:'html'}</strong></span>
              <span class="market-price-value">
                {if $erliAccount.price_amount != ''}
                  {$erliAccount.price_amount|number_format:2:',':'.'} zl
                {else}
                  cena ?
                {/if}
              </span>
            </a>
          {/foreach}

          {foreach from=$prod.morele_accounts item=moreleAccount}
            <a href="{$moreleAccount.morele_url|escape:'html'}" target="_blank" rel="noreferrer" class="market-price-row market-price-row--link market-price-row--morele">
              <span class="market-price-logo">
                <img src="https://www.morele.net/favicon.ico" alt="Morele">
              </span>
              <span class="market-price-name">Morele <strong>{$moreleAccount.account_name|escape:'html'}</strong></span>
              <span class="market-price-value">
                {if $moreleAccount.price_amount != ''}
                  {$moreleAccount.price_amount|number_format:2:',':'.'} zl
                {else}
                  cena ?
                {/if}
              </span>
            </a>
          {/foreach}
        </div>
    </td>

    <th class="bg-light">Cena podzespołów</th>
    <td>{$component_price_sum|number_format:2:',':'.'}</td>
</tr>

                                <tr>
                                  <th class="bg-light">Prowizja 2.46%</th>
                                  <td>{$commission|number_format:2:',':'.'}</td>
                                  <th class="bg-light">Prowizja 4.31%</th>
                                  <td>{$commission_highlight|number_format:2:',':'.'}</td>
                                </tr>
                                
                                <tr>
                                  <th class="bg-light">Zysk bez wyróżnienia</th>
                                  <td>{$profit_net|number_format:2:',':'.'}</td>
                                  <th class="bg-light">Zysk z wyróżnieniem</th>
                                  {if $profit_highlight_net < 350}
                            <td style="color:red; font-weight:bold;">{$profit_highlight_net|number_format:2:',':'.'}</td>
                            {else}
                                      <td>{$profit_highlight_net|number_format:2:',':'.'}</td>
                            {/if}

                                </tr>
                              </tbody>
                            </table>
                          </div>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4 mb-2">
                          <label class="form-label mb-0">Marża (zł):</label>
                          <input type="number" step="0.01" name="products[{$prod.id}][profit]" value="{$prod.profit|default:0}" class="form-control form-control-sm" />
                          <label class="form-label mb-0">EAN:</label>
                          <input type="number" name="products[{$prod.id}][EAN]" value="{$prod.EAN|default:0}" class="form-control form-control-sm" />
                        </div>
                        
                        <div class="col-12 col-lg-8 mb-2">
                          <label class="form-label mb-0">Nazwa produktu (edycja):</label>
                          <textarea name="products[{$prod.id}][name]" rows="2" class="form-control form-control-sm">{$prod.name|escape:'html'}</textarea>
                          <!-- licznik znaków pojawi się automatycznie przez JS -->
                        </div>
                        <div class="col-12 col-lg-8 mb-2">
                          <label class="form-label mb-0">Komponenty:</label>
                          <div style="max-height:120px; overflow-y:auto; border:1px solid #ddd; padding:5px; border-radius:4px; background:#f8f9fa;">
                            {foreach from=$components item=comp}
                              <label style="display:block; margin-bottom:4px; font-size:0.9em;">
                                <input type="checkbox" name="products[{$prod.id}][components][]" value="{$comp.id}"
                                  {if in_array($comp.id, $prod.component_ids)}checked{/if} />
                                {$comp.name|escape:'html'}
                              </label>
                            {/foreach}
                          </div>
                        </div>
                        <div class="col-12 col-lg-4 mb-2 d-flex flex-column gap-2">
                          <button type="submit" name="save_product" value="{$prod.id}" class="btn btn-sm btn-success w-100"><i class="bi bi-save"></i> Zapisz</button>
                          <a href="{$baseUrl}?controller=computers&action=products&delete_id={$prod.id}" onclick="return confirm('Czy na pewno chcesz usunąć produkt ID {$prod.id}?')" class="btn btn-sm btn-danger w-100"><i class="bi bi-trash"></i> Usuń</a>
                        </div>
                      </div>
                      <input type="hidden" name="products[{$prod.id}][img]" value="{$prod.img|escape:'html'}" />
                      <input type="hidden" name="products[{$prod.id}][img_morele]" value="{$prod.img_morele|escape:'html'}" />
                      <input type="hidden" name="products[{$prod.id}][img_empik]" value="{$prod.img_empik|escape:'html'}" />
                    </div>
                  </div>
                </div>
              {/foreach}
            </div>
          </form>
          <div class="d-flex justify-content-between align-items-center mt-3 px-2">
            <div>
              {if $current_page > 1}
                <a href="{$pagination_base_query}page={$current_page-1}&per_page={$per_page}" class="btn btn-sm btn-outline-primary me-1">&laquo; Poprzednia</a>
              {/if}
              {if $current_page < $total_pages}
                <a href="{$pagination_base_query}page={$current_page+1}&per_page={$per_page}" class="btn btn-sm btn-outline-primary">Następna &raquo;</a>
              {/if}
            </div>
            <div class="d-flex align-items-center">
              <nav aria-label="Pagination bottom">
                <ul class="pagination pagination-sm mb-0 me-3">
                  {foreach from=$page_links item=pl}
                    {if $pl.ellipsis}
                      <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                    {else}
                      <li class="page-item {if $pl.is_current}active{/if}"><a class="page-link" href="{$pagination_base_query}page={$pl.num}&per_page={$per_page}">{$pl.num}</a></li>
                    {/if}
                  {/foreach}
                </ul>
              </nav>
              <div class="small text-muted me-2">Strona {$current_page} / {$total_pages}</div>
              <div class="input-group input-group-sm" style="width:150px;">
                <input type="number" id="goto_page_input" class="form-control" min="1" max="{$total_pages}" placeholder="Nr strony" />
                <button id="goto_page_btn" class="btn btn-outline-secondary">Idź</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal do powiększania obrazka -->
<div class="modal fade" id="imgModal" tabindex="-1" aria-labelledby="imgModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="imgModalLabel">Podgląd obrazka</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <img id="imgModalImg" src="" alt="Podgląd" style="max-width:100%; max-height:60vh;" />
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="priceMarketplaceModal" tabindex="-1" aria-labelledby="priceMarketplaceModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="priceMarketplaceModalLabel">Aktualizacja cen marketplace</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">Wybierz konta, na ktorych zaktualizowac cene z magazynu.</p>
        <div id="priceMarketplaceAccounts" class="d-flex flex-column gap-2"></div>
        <div id="priceMarketplaceEmpty" class="alert alert-warning mb-0 d-none">
          Zaznaczone produkty nie maja aktywnych ofert na kontach marketplace.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
        <button type="button" class="btn btn-primary" id="confirmPriceMarketplaceUpdate">Aktualizuj ceny</button>
      </div>
    </div>
  </div>
</div>

<style>
  .product-img-thumb:hover {
    cursor: pointer;
    filter: brightness(0.95) drop-shadow(0 0 2px #007bff);
  }
  textarea.form-control {
    resize: vertical;
    font-size: 0.95rem;
  }
  .table td, .table th {
    vertical-align: middle;
  }
  .product-price-cell {
    min-width: 250px;
  }
  .product-price-cell--mismatch .market-price-row--warehouse .market-price-value,
  .product-price-cell--mismatch .market-price-row--allegro .market-price-value {
    color: #dc3545;
    font-weight: 700;
  }
  .market-price-list {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
  }
  .market-price-row {
    display: grid;
    grid-template-columns: 24px minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.45rem;
    min-height: 30px;
    padding: 0.28rem 0.45rem;
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 0.45rem;
    background: #fff;
    color: #1f2937;
  }
  .market-price-row--link {
    text-decoration: none;
    transition: border-color 150ms ease, background-color 150ms ease, transform 150ms ease;
  }
  .market-price-row--link:hover {
    border-color: rgba(13, 110, 253, 0.35);
    background: #f8fbff;
    color: #0b63d6;
    transform: translateY(-1px);
  }
  .market-price-row--warehouse {
    background: #f8fafc;
  }
  .market-price-row--allegro {
    background: #fffaf0;
  }
  .market-price-row--empik {
    background: #f3fbff;
  }
  .market-price-row--erli {
    background: #f3fff7;
  }
  .market-price-row--muted {
    background: #f8f9fa;
    color: #6c757d;
  }
  .market-price-logo {
    width: 22px;
    height: 22px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #fff;
    border: 1px solid rgba(15, 23, 42, 0.08);
    overflow: hidden;
  }
  .market-price-logo img {
    width: 16px;
    height: 16px;
    object-fit: contain;
  }
  .market-price-logo--warehouse img {
    width: 15px;
    height: 15px;
  }
  .market-price-name {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .market-price-value {
    font-weight: 600;
    color: #111827;
    white-space: nowrap;
    text-align: right;
  }
  .card-header {
    font-size: 1.1rem;
    font-weight: 500;
  }

  /* Fancy translucent & animated styles for variants panel and products column */
  /* Smoothly animate grid column width changes */
  /* Transitions are disabled by default to prevent layout shift on initial load.
     They are enabled after initialization by adding `transitions-enabled` to body. */
  .row > [class*="col-"] {
    -webkit-transition: none;
    transition: none;
  }
  body.transitions-enabled .row > [class*="col-"] {
    -webkit-transition: all 450ms cubic-bezier(.2,.8,.2,1);
    transition: all 450ms cubic-bezier(.2,.8,.2,1);
  }

  /* Glass effect for the variants card + subtle slide/fade */
  #variantsPanel .card {
    background: rgba(255,255,255,0.86);
    -webkit-backdrop-filter: blur(6px);
    backdrop-filter: blur(6px);
    border: 1px solid rgba(0,0,0,0.06);
    box-shadow: 0 6px 18px rgba(7,55,120,0.08);
    -webkit-transition: transform 360ms cubic-bezier(.2,.8,.2,1), opacity 360ms ease;
    transition: transform 360ms cubic-bezier(.2,.8,.2,1), opacity 360ms ease;
    transform-origin: left center;
  }
  /* Hidden state: slightly shifted and translucent */
  #variantsPanel:not(.show) .card {
    transform: translateX(-8px) scale(.995);
    opacity: 0.0;
  }
  /* Visible state */
  #variantsPanel.show .card {
    transform: translateX(0) scale(1);
    opacity: 1;
  }

  /* Toggle button icon rotation */
  #toggleVariantsBtn i {
    display: inline-block;
    -webkit-transition: transform 320ms ease;
    transition: transform 320ms ease;
  }
  #toggleVariantsBtn[aria-expanded="true"] i {
    transform: rotate(180deg) translateY(1px);
  }

  /* Make product cards float-in with slight elevation on load/resize */
  .card.mb-2.border-primary-subtle {
    -webkit-transition: transform 300ms ease, box-shadow 300ms ease;
    transition: transform 300ms ease, box-shadow 300ms ease;
  }
  .card.mb-2.border-primary-subtle:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 24px rgba(7,55,120,0.08);
  }

  /* Wyraźniejsze karty produktów */
  .product-card {
    background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
    border-width: 1px;
    border-color: rgba(13,110,253,0.08);
    padding: 6px;
    border-radius: 10px;
    overflow: visible;
  }
  .product-card .card-body {
    background: transparent;
    padding: 12px;
  }
  .product-card + .product-card {
    margin-top: 10px;
  }
  .product-card .product-title {
    color: #0b63d6;
    font-size: 1.02rem;
  }

  /* Selected state when checkbox is checked */
  .product-card.selected {
    border-left: 6px solid rgba(13,110,253,0.18);
    box-shadow: 0 8px 30px rgba(11,99,214,0.06);
    transform: translateY(-6px);
    background: linear-gradient(180deg, #f7fbff 0%, #ffffff 100%);
  }
  .product-card .product-img-thumb {
    border: 1px solid rgba(0,0,0,0.06);
    background: #fff;
    padding: 4px;
  }

  /* Zebra striping: co druga karta będzie trochę ciemniejsza */
  /* Row containing product cards uses .row.g-3 */
  .row.g-3 > div.col-12:nth-child(odd) .product-card {
    background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
  }
  .row.g-3 > div.col-12:nth-child(even) .product-card {
    background: linear-gradient(180deg, #e9f2ff 0%, #e2eefc 100%);
    border-color: rgba(11,99,214,0.14);
    box-shadow: 0 6px 20px rgba(11,99,214,0.04);
  }
  /* Ensure selected state overrides striping */
  .product-card.selected {
    border-left: 6px solid rgba(13,110,253,0.22);
    box-shadow: 0 10px 34px rgba(11,99,214,0.09);
    transform: translateY(-6px);
    background: linear-gradient(180deg, #fffdfa 0%, #ffffff 100%) !important;
  }

  /* Style dla selectów komponentów - pełna szerokość i lepsze wyświetlanie */
  .bulk-component-select {
    min-width: 450px;
    max-width: 100%;
  }
  
  @media (max-width: 768px) {
    .bulk-component-select {
      min-width: 100%;
    }
  }
</style>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Automatycznie chowaj alert po 5 sek
    setTimeout(() => {
      const alert = document.getElementById("successAlert");
      if (alert) {
        alert.classList.remove("show");
        alert.classList.add("fade");
        setTimeout(() => alert.remove(), 1000);
      }
    }, 5000);

    // Checkbox "Zaznacz wszystko"
    var checkAll = document.getElementById('check_all');
    if (checkAll) {
      checkAll.addEventListener('change', function() {
        const checked = this.checked;
        document.querySelectorAll('input.product_checkbox').forEach(chk => {
          chk.checked = checked;
        });
      });
    }
    // Przycisk zaznacz wszystkie
    var selectAllBtn = document.getElementById('select_all_btn');
    if (selectAllBtn) {
      selectAllBtn.addEventListener('click', function() {
        setFilteredSelectionMode(false);
        document.querySelectorAll('input.product_checkbox').forEach(chk => {
          chk.checked = true;
          updateProductCardSelection(chk);
        });
        if (checkAll) checkAll.checked = true;
      });
    }
    var selectAllFilteredBtn = document.getElementById('select_all_filtered_btn');
    if (selectAllFilteredBtn) {
      selectAllFilteredBtn.addEventListener('click', function() {
        setFilteredSelectionMode(true);
        document.querySelectorAll('input.product_checkbox').forEach(function(chk) {
          chk.checked = true;
          updateProductCardSelection(chk);
        });
        if (checkAll) checkAll.checked = true;
      });
    }
    // Przycisk odznacz wszystkie
    var deselectAllBtn = document.getElementById('deselect_all_btn');
    if (deselectAllBtn) {
      deselectAllBtn.addEventListener('click', function() {
        setFilteredSelectionMode(false);
        document.querySelectorAll('input.product_checkbox').forEach(chk => {
          chk.checked = false;
          updateProductCardSelection(chk);
        });
        if (checkAll) checkAll.checked = false;
      });
    }

    function setFilteredSelectionMode(enabled) {
      var scope = document.getElementById('selection_scope');
      var notice = document.getElementById('all_filtered_selection_notice');
      var excluded = document.getElementById('excluded_product_ids');
      if (scope) scope.value = enabled ? 'filtered' : 'page';
      if (notice) notice.classList.toggle('d-none', !enabled);
      if (excluded) excluded.innerHTML = '';
    }

    function syncFilteredSelectionExclusion(checkbox) {
      var scope = document.getElementById('selection_scope');
      var excluded = document.getElementById('excluded_product_ids');
      if (!scope || scope.value !== 'filtered' || !excluded) return;
      var selector = 'input[data-excluded-product-id="' + checkbox.value + '"]';
      var existing = excluded.querySelector(selector);
      if (!checkbox.checked && !existing) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'excluded_product_ids[]';
        input.value = checkbox.value;
        input.setAttribute('data-excluded-product-id', checkbox.value);
        excluded.appendChild(input);
      } else if (checkbox.checked && existing) {
        existing.remove();
      }
    }

    // Funkcja pomocnicza: zaktualizuj klasę .selected dla karty produktu
    function updateProductCardSelection(checkbox) {
      var card = checkbox.closest('.product-card');
      if (!card) return;
      if (checkbox.checked) {
        card.classList.add('selected');
      } else {
        card.classList.remove('selected');
      }
    }

    // Initial bind: dla istniejących checkboxów ustaw klasę zgodnie ze stanem
    document.querySelectorAll('input.product_checkbox').forEach(function(chk) {
      updateProductCardSelection(chk);
      chk.addEventListener('change', function() {
        updateProductCardSelection(chk);
        syncFilteredSelectionExclusion(chk);
      });
    });

    // Zaznaczanie z użyciem Shift
    let lastChecked = null;
    const productCheckboxes = Array.from(document.querySelectorAll('input.product_checkbox'));
    productCheckboxes.forEach((chk, idx) => {
      chk.addEventListener('click', function(e) {
        if (e.shiftKey && lastChecked !== null) {
          const lastIdx = productCheckboxes.indexOf(lastChecked);
          const thisIdx = idx;
          const [start, end] = [lastIdx, thisIdx].sort((a, b) => a - b);
          const checkedState = this.checked;
          for (let i = start; i <= end; i++) {
            productCheckboxes[i].checked = checkedState;
            updateProductCardSelection(productCheckboxes[i]);
            syncFilteredSelectionExclusion(productCheckboxes[i]);
          }
        }
        updateProductCardSelection(chk);
        syncFilteredSelectionExclusion(chk);
        lastChecked = this;
      });
    });

    // Liczniki znaków dla wszystkich textarea nazw produktów
    document.querySelectorAll('textarea[name^="products"][name$="[name]"]').forEach(function(textarea) {
      var counter = document.createElement('div');
      counter.className = 'text-end small mt-1 product-name-counter';
      textarea.parentNode.appendChild(counter);
      function updateCounter() {
        var len = textarea.value.length;
        counter.textContent = len + ' / 75';
        if (len > 75) {
          counter.classList.add('text-danger', 'fw-bold');
        } else {
          counter.classList.remove('text-danger', 'fw-bold');
        }
      }
      textarea.addEventListener('input', updateCounter);
      updateCounter();
    });

    // Liczniki znaków dla tytułów produktów (podgląd)
    document.querySelectorAll('.product-title').forEach(function(titleEl) {
      var prodId = titleEl.getAttribute('data-prod-id');
      var counter = document.querySelector('.product-title-counter[data-prod-id="' + prodId + '"]');
      function updateTitleCounter() {
        var len = titleEl.textContent.length;
        counter.textContent = len + ' / 75';
        if (len > 75) {
          titleEl.classList.add('text-danger', 'fw-bold');
          counter.classList.add('text-danger', 'fw-bold');
        } else {
          titleEl.classList.remove('text-danger', 'fw-bold');
          counter.classList.remove('text-danger', 'fw-bold');
        }
      }
      updateTitleCounter();
    });

    // Dodaj powiększanie obrazka w modalu
    document.querySelectorAll('.product-img-thumb').forEach(function(img) {
      img.addEventListener('click', function() {
        var modalImg = document.getElementById('imgModalImg');
        modalImg.src = this.getAttribute('data-img');
        var modal = new bootstrap.Modal(document.getElementById('imgModal'));
        modal.show();
      });
    });

    // Odwróć zaznaczenie z danej kategorii
    document.querySelectorAll('.toggle-category-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var catClass = this.getAttribute('data-category');
        document.querySelectorAll('input[type=checkbox].' + catClass).forEach(function(chk) {
          chk.checked = !chk.checked;
        });
      });
    });
    
    // Obsługa rozszerzania listy produktów gdy panel wariantów jest zwinięty
    var variantsPanel = document.getElementById('variantsPanel');
    var productsCol = document.getElementById('productsCol');
    var toggleBtn = document.getElementById('toggleVariantsBtn');
    function updateProductsWidth() {
      if (!variantsPanel) return;
      // jeśli panel ma klasę show -> widoczny
      var isShown = variantsPanel.classList.contains('show');
      if (productsCol) {
        if (!isShown) {
          // rozszerz do pełnej szerokości na dużych ekranach
          productsCol.classList.remove('col-lg-8');
          productsCol.classList.add('col-lg-12');
        } else {
          productsCol.classList.remove('col-lg-12');
          productsCol.classList.add('col-lg-8');
        }
      }
      if (toggleBtn) {
        toggleBtn.setAttribute('aria-expanded', isShown ? 'true' : 'false');
      }
    }
    // Inicjalnie ustaw szerokość zgodnie ze stanem collapse
    updateProductsWidth();
    // Włącz animacje po krótkim timeout, żeby uniknąć widocznego przeskoku przy ładowaniu
    setTimeout(function() {
      document.body.classList.add('transitions-enabled');
    }, 120);
    // Nasłuchuj eventów Bootstrapa (show.bs.collapse / hide.bs.collapse)
    if (variantsPanel) {
      variantsPanel.addEventListener('shown.bs.collapse', updateProductsWidth);
      variantsPanel.addEventListener('hidden.bs.collapse', updateProductsWidth);
    }

    // Ustaw aria-expanded oraz klasę na body dla dodatkowych styli animacji
    if (toggleBtn && variantsPanel) {
      variantsPanel.addEventListener('shown.bs.collapse', function() {
        toggleBtn.setAttribute('aria-expanded', 'true');
        document.body.classList.add('variants-expanded');
      });
      variantsPanel.addEventListener('hidden.bs.collapse', function() {
        toggleBtn.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('variants-expanded');
      });
    }
  });

  function selectedProductCheckboxes() {
    return Array.from(document.querySelectorAll('input.product_checkbox:checked'));
  }

  function marketplaceAccountsForSelectedProducts() {
    var accounts = new Map();
    selectedProductCheckboxes().forEach(function (checkbox) {
      var raw = checkbox.getAttribute('data-price-market-accounts') || '';
      raw.split('||').forEach(function (entry) {
        entry = entry.trim();
        if (!entry) return;
        var parts = entry.split('|');
        var value = (parts[0] || '').trim();
        var label = (parts[1] || value).trim();
        if (value && !accounts.has(value)) {
          accounts.set(value, label);
        }
      });
    });
    return accounts;
  }

  function renderPriceMarketplaceAccounts(accounts) {
    var container = document.getElementById('priceMarketplaceAccounts');
    var empty = document.getElementById('priceMarketplaceEmpty');
    var confirmBtn = document.getElementById('confirmPriceMarketplaceUpdate');
    if (!container || !empty || !confirmBtn) return false;

    container.innerHTML = '';
    accounts.forEach(function (label, value) {
      var id = 'price_market_account_' + value.replace(/[^a-zA-Z0-9_-]+/g, '_');
      var wrapper = document.createElement('div');
      wrapper.className = 'form-check';
      wrapper.innerHTML = '<input class="form-check-input js-price-market-account" type="checkbox" checked value="'
        + escapeHtml(value) + '" id="' + escapeHtml(id) + '">'
        + '<label class="form-check-label" for="' + escapeHtml(id) + '">' + escapeHtml(label) + '</label>';
      container.appendChild(wrapper);
    });

    var hasAccounts = accounts.size > 0;
    empty.classList.toggle('d-none', hasAccounts);
    confirmBtn.disabled = !hasAccounts;
    return hasAccounts;
  }

  function accountsMapFromList(list) {
    var accounts = new Map();
    (list || []).forEach(function (item) {
      var value = String(item.value || '').trim();
      var label = String(item.label || value).trim();
      if (value && !accounts.has(value)) {
        accounts.set(value, label);
      }
    });
    return accounts;
  }

  function populatePriceMarketplaceModal() {
    return renderPriceMarketplaceAccounts(marketplaceAccountsForSelectedProducts());
  }

  function currentSelectionFormData() {
    var form = document.getElementById('productsBulkForm');
    var data = new FormData();
    if (!form) return data;

    ['selection_scope', 'selection_filter_name'].forEach(function (name) {
      var input = form.querySelector('[name="' + name + '"]');
      if (input) data.append(name, input.value || '');
    });
    form.querySelectorAll('[name="selection_filter_components[]"], [name="selection_filter_market_accounts[]"], [name="excluded_product_ids[]"], input.product_checkbox:checked').forEach(function (input) {
      data.append(input.name, input.value || '');
    });
    data.append('price_market_accounts', '1');
    return data;
  }

  function writeSelectedPriceMarketplaceInputs() {
    var target = document.getElementById('price_marketplace_selected_inputs');
    if (!target) return 0;
    target.innerHTML = '';
    var selected = Array.from(document.querySelectorAll('.js-price-market-account:checked'));
    selected.forEach(function (checkbox) {
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'bulk_price_market_accounts[]';
      input.value = checkbox.value;
      target.appendChild(input);
    });
    return selected.length;
  }

  function showPriceMarketplaceModal() {
    var scope = document.getElementById('selection_scope');
    var modalNode = document.getElementById('priceMarketplaceModal');
    var showModal = function () {
      if (modalNode && window.bootstrap && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalNode).show();
      }
    };

    if (scope && scope.value === 'filtered') {
      var container = document.getElementById('priceMarketplaceAccounts');
      var empty = document.getElementById('priceMarketplaceEmpty');
      var confirmBtn = document.getElementById('confirmPriceMarketplaceUpdate');
      if (container) container.innerHTML = '<div class="text-secondary small">Sprawdzam konta dla wszystkich zaznaczonych produktow...</div>';
      if (empty) empty.classList.add('d-none');
      if (confirmBtn) confirmBtn.disabled = true;
      showModal();

      fetch('{$baseUrl}?controller=computers&action=products&price_market_accounts=1', {
        method: 'POST',
        body: currentSelectionFormData(),
        credentials: 'same-origin'
      })
        .then(function (response) { return response.json(); })
        .then(function (payload) {
          renderPriceMarketplaceAccounts(accountsMapFromList(payload.accounts || []));
        })
        .catch(function () {
          if (container) container.innerHTML = '';
          if (empty) {
            empty.textContent = 'Nie udalo sie pobrac kont dla zaznaczonych produktow.';
            empty.classList.remove('d-none');
          }
        });
      return;
    }

    populatePriceMarketplaceModal();
    showModal();
  }

  function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
    });
  }

  var confirmPriceMarketplaceUpdate = document.getElementById('confirmPriceMarketplaceUpdate');
  if (confirmPriceMarketplaceUpdate) {
    confirmPriceMarketplaceUpdate.addEventListener('click', function () {
      if (writeSelectedPriceMarketplaceInputs() <= 0) {
        return;
      }
      var form = document.getElementById('productsBulkForm');
      if (form) {
        form.submit();
      }
    });
  }

  var productsBulkForm = document.getElementById('productsBulkForm');
  if (productsBulkForm) {
    productsBulkForm.addEventListener('submit', function (event) {
      var actionInput = document.getElementById('bulk_action');
      var selectedInputs = document.querySelectorAll('#price_marketplace_selected_inputs input[name="bulk_price_market_accounts[]"]');
      if (actionInput && actionInput.value === 'update_price' && selectedInputs.length === 0) {
        event.preventDefault();
        showPriceMarketplaceModal();
      }
    });
  }

  function setBulkAction(action) {
    document.getElementById('bulk_action').value = action;
    var selectedPriceMarkets = document.getElementById('price_marketplace_selected_inputs');
    if (selectedPriceMarkets) {
      selectedPriceMarkets.innerHTML = '';
    }
    document.getElementById('bulk_action_fields').style.display = 'block';
    document.getElementById('profit_field').style.display = 'none';
    document.getElementById('replace_name_fields').style.display = 'none';
    document.getElementById('change_images_field').style.display = 'none';
    document.getElementById('set_ean_field').style.display = 'none';
    document.getElementById('import_ean_field').style.display = 'none';
    document.getElementById('delete_field').style.display = 'none';
    document.getElementById('update_price_field').style.display = 'none';
    document.getElementById('add_component_field').style.display = 'none';
    document.getElementById('replace_component_field').style.display = 'none';
    document.getElementById('remove_component_field').style.display = 'none';
    if (action === 'change_profit') {
      document.getElementById('profit_field').style.display = 'block';
    } else if (action === 'replace_name') {
      document.getElementById('replace_name_fields').style.display = 'block';
    } else if (action === 'change_images') {
      document.getElementById('change_images_field').style.display = 'block';
    } else if (action === 'delete') {
      document.getElementById('delete_field').style.display = 'block';
    } else if (action === 'set_ean') {
      document.getElementById('set_ean_field').style.display = 'block';
    }
    else if (action === 'import_ean') {
      document.getElementById('import_ean_field').style.display = 'block';
    }
    else if (action === 'update_price') {
      document.getElementById('update_price_field').style.display = 'block';
      showPriceMarketplaceModal();
    }
    else if (action === 'add_component') {
      document.getElementById('add_component_field').style.display = 'block';
    }
    else if (action === 'replace_component') {
      document.getElementById('replace_component_field').style.display = 'block';
    }
    else if (action === 'remove_component') {
      document.getElementById('remove_component_field').style.display = 'block';
    }
  }

  // Obsługa zmiany per_page (JS)
  document.addEventListener('DOMContentLoaded', function() {
    var perPageSelect = document.getElementById('per_page_select');
    if (perPageSelect) {
      perPageSelect.addEventListener('change', function() {
        var val = this.value;
        // Zbuduj nowy URL zachowując inne parametry, ustaw page=1
        var params = new URLSearchParams(window.location.search);
        params.set('controller', 'computers');
        params.set('action', 'products');
        params.set('per_page', val);
        params.set('page', 1);
        window.location.search = params.toString();
      });
    }
  });

  // Goto page (manual) handler
  document.addEventListener('DOMContentLoaded', function() {
    var gotoInput = document.getElementById('goto_page_input');
    var gotoBtn = document.getElementById('goto_page_btn');
    function gotoPage() {
      var val = parseInt(gotoInput.value, 10);
      if (!val || val < 1) return;
      var max = {$total_pages};
      if (val > max) val = max;
      var params = new URLSearchParams(window.location.search);
      params.set('controller', 'computers');
      params.set('action', 'products');
      params.set('page', val);
      // keep existing per_page if any
      window.location.search = params.toString();
    }
    if (gotoBtn && gotoInput) {
      gotoBtn.addEventListener('click', function(e) {
        e.preventDefault();
        gotoPage();
      });
      gotoInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          gotoPage();
        }
      });
    }
  });

  // Synchronize filter form with per_page and reset page to 1 on submit
  document.addEventListener('DOMContentLoaded', function() {
    var filterForm = document.querySelector('form[method="get"].row.g-3');
    var perPageSelect = document.getElementById('per_page_select');
    var filterPerPageInput = document.getElementById('filter_per_page_input');
    var filterPageInput = document.getElementById('filter_page_input');
    if (filterForm) {
      filterForm.addEventListener('submit', function() {
        if (perPageSelect && filterPerPageInput) {
          filterPerPageInput.value = perPageSelect.value;
        }
        if (filterPageInput) {
          filterPageInput.value = 1; // reset to first page when applying filter
        }
      });
    }
  });
</script>
