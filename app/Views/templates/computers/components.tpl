<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<div class="container-fluid py-4 computers-components-page">
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
  </ul>
  <!-- Nagłówek strony -->
  <div class="d-flex align-items-center mb-4">
    <div class="me-3">
      <span class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center"
        style="width:48px; height:48px; font-size:2rem;"><i class="bi bi-cpu"></i></span>
    </div>
    <div>
      <div class="d-flex align-items-center gap-3">
        <h1 class="h3 mb-0" id="mainComponentTitle">Panel komponentów</h1>
        <span id="mainComponentTitleCounter" class="small text-muted"></span>
      </div>
      <small class="text-muted">Zarządzaj komponentami, zdjęciami i kategoriami</small>
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

  <div class="accordion" id="accordionExample">
    <!-- Sekcja 1 -->
    <div class="accordion-item">
      <h2 class="accordion-header" id="headingOne">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne"
          aria-expanded="false" aria-controls="collapseOne">
          🔹 Szablony
        </button>
      </h2>
      <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne"
        data-bs-parent="#accordionExample">
        <div class="accordion-body" style="background:#f9fafb; border-radius:8px; padding:15px 20px;">
        {ldelim}% if GPU_description%{rdelim} Opis
        {ldelim}% else %{rdelim} opis
        {ldelim}% endif %{rdelim}<br>
        {ldelim}{ldelim}main_img_morele{rdelim}{rdelim}
        <br>
        {ldelim}{ldelim}main_img_allegro{rdelim}{rdelim}
        <br>
        {ldelim}{ldelim}CASE_img[0]{rdelim}{rdelim}
        {ldelim}{ldelim}CASE_img_morele[0]{rdelim}{rdelim}
        <br>
        {foreach $columns as $col}
           

          {ldelim}{ldelim}CATEGORY_{$col.COLUMN_NAME}{rdelim}{rdelim}<br>
          
        {/foreach}
{foreach $templates as $t}
<form method="post">
  <div style="margin-bottom:20px;">
    <label style="font-weight:600; margin-bottom:6px; display:block; color:#333;">{$t.name}:</label>

    <textarea id="template_{$t.id_template}" name="template_info">
      {$t.template nofilter}
    </textarea>
  </div>
  <input type="hidden" name="id_template" value="{$t.id_template}">
  <input type="submit" name="save_template" class="btn btn-primary mb-3" value="Zapisz zmiany">
</form>
{/foreach}



        </div>
      </div>

    </div>
    <div class="row g-4">

      <!-- Panel dodawania/edycji komponentu -->
      <div class="col-12 col-xl-5 col-xxl-4">
        <div class="card shadow-sm components-editor-card">
          <div class="card-header bg-primary text-white">
            <i class="bi bi-plus-circle me-2"></i>{if $editItem}Edytuj komponent{else}Dodaj nowy komponent{/if}
          </div>
          <div class="card-body">
            <form method="post" action="" enctype="multipart/form-data">
              <input type="hidden" name="id" value="{$editItem.id|default:''}" />
              <input type="hidden" name="img_old" value="{$editItem.img|escape:'html'}" />
              <input type="hidden" name="img_morele_old" value="{$editItem.img_morele|escape:'html'}" />
              <input type="hidden" name="img_empik_old" value="{$editItem.img_empik|escape:'html'}" />
              <div class="mb-3">
                <label for="name" class="form-label">Nazwa:</label>
                <input type="text" id="name" name="name" value="{$editItem.name|escape:'html'}" class="form-control"
                  required />
              </div>
              <div class="mb-3">
                <label for="name_title" class="form-label">Nazwa tytułu:</label>
                <input type="text" id="name_title" name="name_title" value="{$editItem.name_title|escape:'html'}"
                  class="form-control" />
              </div>
              <div class="mb-3">
                <label for="name_spec" class="form-label">Specyfikacja:</label>
                <input type="text" id="name_spec" name="name_spec" value="{$editItem.name_spec|escape:'html'}"
                  class="form-control" />
              </div>
              <div class="mb-3">
                <label for="price" class="form-label">Cena:</label>
                <input type="number" step="0.01" id="price" name="price" value="{$editItem.price|default:''}"
                  class="form-control" required />
              </div>
              <div class="mb-3">
                <label for="description" class="form-label">Opis Allegro:</label>
                <textarea id="description" name="description" class="form-control"
                  rows="3">{$editItem.description|escape:'html'}</textarea>
              </div>
              <div class="mb-3">
                <label for="description_morele" class="form-label">Opis Morele:</label>
                <textarea id="description_morele" name="description_morele" class="form-control"
                  rows="3">{$editItem.description_morele|escape:'html'}</textarea>
              </div>
              <div class="mb-3">
                <label for="description_empik" class="form-label">Opis Empik:</label>
                <textarea id="description_empik" name="description_empik" class="form-control"
                  rows="3">{$editItem.description_empik|escape:'html'}</textarea>
              </div>
              <div class="mb-4">
                <button class="btn btn-primary mb-3" type="button" data-bs-toggle="collapse"
                  data-bs-target="#parametersBox" aria-expanded="false" aria-controls="parametersBox">
                  Pokaż / ukryj parametry EU
                </button>
                <button class="btn btn-primary mb-3" type="button" data-bs-toggle="collapse"
                  data-bs-target="#parametersBoxMorele" aria-expanded="false" aria-controls="parametersBox">
                  Pokaż / ukryj parametry Morele
                </button>
                <button class="btn btn-primary mb-3" type="button" data-bs-toggle="collapse"
                  data-bs-target="#parametersBoxEmpik" aria-expanded="false" aria-controls="parametersBox">
                  Pokaż / ukryj parametry Empik
                </button>
                <div class="collapse" id="parametersBoxMorele">
                  <div class="ajax-params-placeholder" data-url="{$baseUrl}?controller=computers&action=components&ajax_params=morele{if $editItem}&edit_id={$editItem.id}{/if}">
                    <div class="text-center py-3"><div class="spinner-border spinner-border-sm" role="status"></div> Ładowanie...</div>
                  </div>
                </div>
                <div class="collapse" id="parametersBoxEmpik">
                  <div class="ajax-params-placeholder" data-url="{$baseUrl}?controller=computers&action=components&ajax_params=empik{if $editItem}&edit_id={$editItem.id}{/if}">
                    <div class="text-center py-3"><div class="spinner-border spinner-border-sm" role="status"></div> Ładowanie...</div>
                  </div>
                </div>
                <div class="collapse" id="parametersBox">
                  <div class="ajax-params-placeholder" data-url="{$baseUrl}?controller=computers&action=components&ajax_params=eu{if $editItem}&edit_id={$editItem.id}{/if}">
                    <div class="text-center py-3"><div class="spinner-border spinner-border-sm" role="status"></div> Ładowanie...</div>
                  </div>
                </div>
              </div>


              <div class="mb-3">
                <label for="img_file" class="form-label">Zdjęcia komponentu (max 16) ALLEGRO:</label>
                <input type="file" id="img_file" name="img_file[]" accept=".jpg,.jpeg,.png,.gif,.webp"
                  class="form-control" multiple />
              </div>
              <div class="mb-3">
                <label for="img_file_morele" class="form-label">Zdjęcia komponentu (max 16) morele:</label>
                <input type="file" id="img_file_morele" name="img_file_morele[]" accept=".jpg,.jpeg,.png,.gif,.webp"
                  class="form-control" multiple />
              </div>
              <div class="mb-3">
                <label for="img_file_empik" class="form-label">Zdjęcia komponentu (max 16) empik:</label>
                <input type="file" id="img_file_empik" name="img_file_empik[]" accept=".jpg,.jpeg,.png,.gif,.webp"
                  class="form-control" multiple />
              </div>
              {if $editItem.img}
                <div class="mb-3">
                  <label class="form-label">Aktualne zdjęcia:</label><br />
                  {assign var="imgList" value=$editItem.img|split:","}
                  {foreach from=$imgList item=imgFile}
                    {if $imgFile}
                      <div style="display:inline-block; margin:3px; text-align:center;">
                        <img src="{$imgFolder}/{$imgFile|escape:'html'}" alt="zdjęcie" class="component-img-thumb"
                          style="max-width:80px; max-height:80px; object-fit:contain; border:1px solid #ccc; padding:3px; border-radius:5px; display:block; margin-bottom:2px; cursor:pointer;"
                          data-img="{$imgFolder}/{$imgFile|escape:'html'}" />
                        <label style="font-size:0.9em;">
                          <input type="checkbox" name="remove_img[]" value="{$imgFile|escape:'html'}" /> Usuń
                        </label>
                      </div>
                    {/if}
                  {/foreach}
                </div>
              {/if}
              {if $editItem.img_morele}
                <div class="mb-3">
                  <label class="form-label">Aktualne zdjęcia Morele:</label><br />
                  {assign var="imgListMorele" value=$editItem.img_morele|split:","}
                  {foreach from=$imgListMorele item=imgFile}
                    {if $imgFile}
                      <div style="display:inline-block; margin:3px; text-align:center;">
                        <img src="{$imgFolder}/{$imgFile|escape:'html'}" alt="zdjęcie" class="component-img-thumb"
                          style="max-width:80px; max-height:80px; object-fit:contain; border:1px solid #ccc; padding:3px; border-radius:5px; display:block; margin-bottom:2px; cursor:pointer;"
                          data-img="{$imgFolder}/{$imgFile|escape:'html'}" />
                        <label style="font-size:0.9em;">
                          <input type="checkbox" name="remove_img_morele[]" value="{$imgFile|escape:'html'}" /> Usuń
                        </label>
                      </div>
                    {/if}
                  {/foreach}
                </div>
              {/if}
              {if $editItem.img_empik}
                <div class="mb-3">
                  <label class="form-label">Aktualne zdjęcia Empik:</label><br />
                  {assign var="imgListEmpik" value=$editItem.img_empik|split:","}
                  {foreach from=$imgListEmpik item=imgFile}
                    {if $imgFile}
                      <div style="display:inline-block; margin:3px; text-align:center;">
                        <img src="{$imgFolder}/{$imgFile|escape:'html'}" alt="zdjęcie" class="component-img-thumb"
                          style="max-width:80px; max-height:80px; object-fit:contain; border:1px solid #ccc; padding:3px; border-radius:5px; display:block; margin-bottom:2px; cursor:pointer;"
                          data-img="{$imgFolder}/{$imgFile|escape:'html'}" />
                        <label style="font-size:0.9em;">
                          <input type="checkbox" name="remove_img_empik[]" value="{$imgFile|escape:'html'}" /> Usuń
                        </label>
                      </div>
                    {/if}
                  {/foreach}
                </div>
              {/if}
              <div class="mb-3">
                <label for="category" class="form-label">Kategoria:</label>
                <input type="text" id="category" name="category" value="{$editItem.category|escape:'html'}"
                  class="form-control" />
              </div>
              <button type="submit" class="btn btn-success w-100">{if $editItem}<i class="bi bi-save"></i> Zapisz
                zmiany{else}<i class="bi bi-plus-circle"></i> Dodaj komponent
                {/if}</button>
              {if $editItem}
                <a href="{$baseUrl}?controller=computers&action=components" class="btn btn-secondary w-100 mt-2">Anuluj edycje</a>
              {/if}
            </form>
          </div>
        </div>
      </div>

      <!-- Panel lista komponentów -->
      <div class="col-12 col-xl-7 col-xxl-8">
        <div class="card shadow-sm">
          <div class="card-header bg-light fw-bold d-flex align-items-center justify-content-between">
            <span><i class="bi bi-table me-2"></i>Lista komponentów</span>
            <div>
              <button type="button" id="select_all_btn" class="btn btn-sm btn-outline-secondary me-1"><i
                  class="bi bi-check2-square"></i> Zaznacz wszystkie</button>
              <button type="button" id="deselect_all_btn" class="btn btn-sm btn-outline-secondary"><i
                  class="bi bi-square"></i> Odznacz wszystkie</button>
            </div>
          </div>
          <div class="card-body pb-0">
            <form method="post" action="" enctype="multipart/form-data" id="bulkActionForm">
              <div class="mb-3">
                <div class="dropdown d-inline-block me-2">
                  <button class="btn btn-primary dropdown-toggle" type="button" id="bulkActionsDropdown"
                    data-bs-toggle="dropdown" aria-expanded="false">
                    Akcje masowe
                  </button>
                  <ul class="dropdown-menu" aria-labelledby="bulkActionsDropdown">
          <li><a class="dropdown-item" href="#"
            onclick="setBulkAction('assign_image'); return false;">Przypisz
            grafikę Allegro</a></li>
          <li><a class="dropdown-item" href="#"
            onclick="setBulkAction('assign_image_morele'); return false;">Przypisz
            grafikę Morele</a></li>
          <li><a class="dropdown-item" href="#"
            onclick="setBulkAction('assign_image_empik'); return false;">Przypisz
            grafikę Empik</a></li>
                    <li><a class="dropdown-item" href="#"
                        onclick="setBulkAction('copy'); return false;">Kopiuj zaznaczone</a></li>
                    <li><a class="dropdown-item text-danger" href="#"
                        onclick="setBulkAction('delete'); return false;">Usuń zaznaczone</a></li>
                  </ul>
                </div>
                <button type="submit" class="btn btn-success"><i class="bi bi-play-circle me-1"></i>Wykonaj
                  akcję</button>
              </div>
              <input type="hidden" name="bulk_action" id="bulk_action" value="" />
              <div id="bulk_action_fields" style="display:none; margin-bottom:20px;">
                <div id="assign_image_field" style="display:none; max-width: 400px;">
                  <label for="bulk_img" class="form-label">Wybierz pliki obrazów (max 6):</label>
                  <input type="file" id="bulk_img" name="bulk_img[]" accept=".jpg,.jpeg,.png,.gif,.webp"
                    class="form-control" multiple />
                  <p class="mt-2 text-muted small">Wybrane obrazy zostaną przypisane do wszystkich zaznaczonych
                    komponentów (zastąpią obecne, max 6 plików).</p>
                </div>
                <div id="assign_image_morele_field" style="display:none; max-width: 400px;">
                  <label for="bulk_img_morele" class="form-label">Wybierz pliki obrazów Morele (max 6):</label>
                  <input type="file" id="bulk_img_morele" name="bulk_img_morele[]" accept=".jpg,.jpeg,.png,.gif,.webp"
                    class="form-control" multiple />
                  <p class="mt-2 text-muted small">Wybrane obrazy zostaną przypisane do wszystkich zaznaczonych
                    komponentów jako zdjęcia Morele (zastąpią obecne, max 6 plików).</p>
                </div>
                <div id="assign_image_empik_field" style="display:none; max-width: 400px;">
                  <label for="bulk_img_empik" class="form-label">Wybierz pliki obrazów Empik (max 6):</label>
                  <input type="file" id="bulk_img_empik" name="bulk_img_empik[]" accept=".jpg,.jpeg,.png,.gif,.webp"
                    class="form-control" multiple />
                  <p class="mt-2 text-muted small">Wybrane obrazy zostaną przypisane do wszystkich zaznaczonych
                    komponentów jako zdjęcia Empik (zastąpią obecne, max 6 plików).</p>
                </div>
                <div id="delete_field" style="display:none; max-width: 400px;">
                  <p class="mb-2 text-danger">Zaznaczone komponenty zostaną trwale usunięte z systemu. Tej operacji nie
                    można cofnąć!</p>
                </div>
                <div id="copy_field" style="display:none; max-width: 400px;">
                  <p class="mb-2 text-success">Zaznaczone komponenty zostaną skopiowane. W nazwie dodana będzie "-kopia".</p>
                </div>
              </div>
              <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered align-middle mb-0">
                  <thead class="table-primary">
                    <tr>
                      <th scope="col" style="width:3%;"><input type="checkbox" id="check_all" /></th>
                      <th scope="col" style="width:12%;">Nazwa</th>
                      <th scope="col" style="width:8%;">Cena</th>
                      <th scope="col" style="width:23%;">Obrazek</th>
                      <th scope="col" style="width:12%;">Akcje</th>
                    </tr>
                  </thead>
                  <tbody>
                    {assign var="currentCategory" value=""}
                    {foreach from=$items item=item}

                      {if $currentCategory != $item.category}
                        {assign var="currentCategory" value=$item.category}
                        <tr class="table-secondary">
                          <td colspan="5" class="fw-bold">Kategoria: {$currentCategory|escape:'html'}</td>
                        </tr>
                      {/if}

                      <tr class="clickable-row" data-comp-id="{$item.id}">
                        <td><input type="checkbox" class="component_checkbox" name="component_ids[]" value="{$item.id}"
                            data-price="{$item.price}" /></td>
                        <td>
                          <span class="component-title" data-comp-id="{$item.id}">{$item.name|escape:'html'}
                         
                          </span>
                          <br>
                          <span style="font-size: 11px;">{$item.name_spec|escape:'html'}</span>
                          <span class="component-title-counter ms-2 small text-muted" data-comp-id="{$item.id}"></span>
                        </td>
                        <td><strong>{$item.price} ZŁ</strong></td>
                        <td>
 
  <details class="photo-accordion">
    <summary>
      <span>🖼️ Zasoby (Morele / Empik / Allegro)</span>
    </summary>

    <div class="accordion-content">
      
      <div class="shop-row">
        <div style="display:flex; align-items:center; gap:8px;">
          <span style="font-size: 0.85rem; color: #555; font-weight: bold;">Morele</span>
          <span class="badge bg-primary" style="font-size: 0.7rem;">{$item.parameters_morele_count}</span>
        </div>
        <div class="img-wrapper">
          {if $item.img_morele}
            {assign var="imgListMorele" value=$item.img_morele|split:","}
            {foreach from=$imgListMorele item=imgFile}
              {if $imgFile}
                <img src="{$imgFolder}/{$imgFile|escape:'html'}" class="thumb-img" alt="morele" />
              {/if}
            {/foreach}
          {else}
            <span style="color:#bbb; font-size:0.75rem;">Brak zdjęć</span>
          {/if}
        </div>
      </div>

      <div class="shop-row">
        <div style="display:flex; align-items:center; gap:8px;">
          <span style="font-size: 0.85rem; color: #555; font-weight: bold;">Empik</span>
          <span class="badge bg-primary" style="font-size: 0.7rem;">{$item.parameters_empik_count}</span>
        </div>
        <div class="img-wrapper">
          {if $item.img_empik}
            {assign var="imgListEmpik" value=$item.img_empik|split:","}
            {foreach from=$imgListEmpik item=imgFile}
              {if $imgFile}
                <img src="{$imgFolder}/{$imgFile|escape:'html'}" class="thumb-img" alt="empik" />
              {/if}
            {/foreach}
          {else}
            <span style="color:#bbb; font-size:0.75rem;">Brak zdjęć</span>
          {/if}
        </div>
      </div>

      <div class="shop-row">
        <div style="display:flex; align-items:center; gap:8px;">
          <span style="font-size: 0.85rem; color: #555; font-weight: bold;">Allegro</span>
          <span class="badge bg-primary" style="font-size: 0.7rem;">{$item.parameters_eu_count}</span>
        </div>
        <div class="img-wrapper">
          {if $item.img}
            {assign var="imgList" value=$item.img|split:","}
            {foreach from=$imgList item=imgFile}
              {if $imgFile}
                <img src="{$imgFolder}/{$imgFile|escape:'html'}" class="thumb-img" alt="allegro" />
              {/if}
            {/foreach}
          {else}
            <span style="color:#bbb; font-size:0.75rem;">Brak zdjęć</span>
          {/if}
        </div>
      </div>

    </div>
  </details>
</td>
                        <td>
                          <a href="{$baseUrl}?controller=computers&action=components&edit_id={$item.id}" class="btn btn-sm btn-primary">Edytuj</a>
                          <a href="{$baseUrl}?controller=computers&action=components&delete_id={$item.id}" class="btn btn-sm btn-danger ms-1"
                            onclick="return confirm('Na pewno chcesz usunąć ten komponent?');">Usuń</a>
                        </td>
                      </tr>
                    {/foreach}
                  </tbody>

                </table>
                <div id="totalPriceBox">
                  <strong>Suma zaznaczonych: <span id="totalPrice">0</span> ZŁ</strong>
                </div>


              </div>
            </form>
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

  <style>
    .computers-components-page {
      max-width: none;
      padding-left: 24px;
      padding-right: 24px;
    }

    .components-editor-card .card-body {
      padding: 1.5rem;
    }

    .market-params {
      background: #f8fafc;
      border: 1px solid #dbe4f0;
      border-radius: 14px;
      padding: 16px;
      margin-top: 14px;
    }

    .market-params .param-option-box {
      max-height: 220px;
      overflow: auto;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      background: #fff;
      padding: 10px 12px;
    }

    .accordion-button,
    .btn,
    .form-control,
    .form-select {
      border-radius: 10px;
    }

    #parametersBox,
    #parametersBoxMorele,
    #parametersBoxEmpik {
      margin-top: 12px;
    }

    .component-img-thumb:hover {
      cursor: pointer;
      filter: brightness(0.95) drop-shadow(0 0 2px #007bff);
    }

    .table td,
    .table th {
      vertical-align: middle;
    }

    .card-header {
      font-size: 1.1rem;
      font-weight: 500;
    }

    @media (max-width: 767.98px) {
      .computers-components-page {
        padding-left: 12px;
        padding-right: 12px;
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

      // Licznik znaków w tytule głównym
      function updateMainComponentTitleCounter() {
        var title = document.getElementById('mainComponentTitle');
        var counter = document.getElementById('mainComponentTitleCounter');
        var len = title.textContent.length;
        counter.textContent = len + ' / 75';
        if (len > 75) {
          title.classList.add('text-danger', 'fw-bold');
          counter.classList.add('text-danger', 'fw-bold');
        } else {
          title.classList.remove('text-danger', 'fw-bold');
          counter.classList.remove('text-danger', 'fw-bold');
        }
      }
      updateMainComponentTitleCounter();

      // Liczniki znaków dla tytułów komponentów (podgląd)
      document.querySelectorAll('.component-title').forEach(function(titleEl) {
        var compId = titleEl.getAttribute('data-comp-id');
        var counter = document.querySelector('.component-title-counter[data-comp-id="' + compId + '"]');

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
      document.querySelectorAll('.component-img-thumb').forEach(function(img) {
        img.addEventListener('click', function() {
          var modalImg = document.getElementById('imgModalImg');
          modalImg.src = this.src;
          var modal = new bootstrap.Modal(document.getElementById('imgModal'));
          modal.show();
        });
      });

      // Checkbox "Zaznacz wszystko" i przyciski
      var checkAll = document.getElementById('check_all');
      if (checkAll) {
        checkAll.addEventListener('change', function() {
          const checked = this.checked;
          document.querySelectorAll('input.component_checkbox').forEach(chk => {
            chk.checked = checked;
          });
        });
      }
      var selectAllBtn = document.getElementById('select_all_btn');
      if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
          document.querySelectorAll('input.component_checkbox').forEach(chk => {
            chk.checked = true;
          });
          if (checkAll) checkAll.checked = true;
        });
      }
      var deselectAllBtn = document.getElementById('deselect_all_btn');
      if (deselectAllBtn) {
        deselectAllBtn.addEventListener('click', function() {
          document.querySelectorAll('input.component_checkbox').forEach(chk => {
            chk.checked = false;
          });
          if (checkAll) checkAll.checked = false;
        });
      }
      // Zmiana widoczności pól akcji masowych
      window.setBulkAction = function(action) {
        document.getElementById('bulk_action').value = action;
        document.getElementById('bulk_action_fields').style.display = 'block';
        document.getElementById('assign_image_field').style.display = 'none';
        document.getElementById('delete_field').style.display = 'none';
        document.getElementById('copy_field').style.display = 'none';
        var moreleField = document.getElementById('assign_image_morele_field');
        var empikField = document.getElementById('assign_image_empik_field');
        if (moreleField) moreleField.style.display = 'none';
        if (empikField) empikField.style.display = 'none';
        if (action === 'assign_image') {
          document.getElementById('assign_image_field').style.display = 'block';
        } else if (action === 'assign_image_morele') {
          if (moreleField) moreleField.style.display = 'block';
        } else if (action === 'assign_image_empik') {
          if (empikField) empikField.style.display = 'block';
        } else if (action === 'delete') {
          document.getElementById('delete_field').style.display = 'block';
        } else if (action === 'copy') {
          document.getElementById('copy_field').style.display = 'block';
        }
      }
      // Walidacja przed wysłaniem formularza akcji masowych
      const bulkForm = document.getElementById('bulkActionForm');
      if (bulkForm) {
        bulkForm.addEventListener('submit', function(e) {
          const action = document.getElementById('bulk_action').value;
          const checked = Array.from(document.querySelectorAll('input.component_checkbox:checked'));
          if (!action) {
            e.preventDefault();
            alert('Wybierz akcję masową.');
            return;
          }
          if (checked.length === 0) {
            e.preventDefault();
            alert('Zaznacz przynajmniej jeden komponent, aby wykonać akcję masową.');
            return;
          }
          if (action === 'assign_image' || action === 'assign_image_morele' || action === 'assign_image_empik') {
            const fileInput = action === 'assign_image' ? document.getElementById('bulk_img') : (action === 'assign_image_morele' ? document.getElementById('bulk_img_morele') : document.getElementById('bulk_img_empik'));
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
              e.preventDefault();
              alert('Wybierz pliki do przypisania w polu akcji masowej.');
              return;
            }
          }
        });
      }
    });
    document.addEventListener('DOMContentLoaded', function() {
      const totalBox = document.getElementById('totalPriceBox');
      const totalSpan = document.getElementById('totalPrice');
      let currentTotal = 0;

      function animateTotal(to) {
        const start = currentTotal;
        const duration = 400; // czas animacji w ms
        const startTime = performance.now();

        function step(now) {
          const progress = Math.min((now - startTime) / duration, 1);
          const value = start + (to - start) * progress;
          totalSpan.textContent = value.toFixed(2);

          if (progress < 1) {
            requestAnimationFrame(step);
          } else {
            currentTotal = to;
          }
        }
        requestAnimationFrame(step);
      }

      function updateTotal() {
        let total = 0;
        document.querySelectorAll('.component_checkbox:checked').forEach(cb => {
          total += parseFloat(cb.getAttribute('data-price')) || 0;
        });

        animateTotal(total);

        if (total > 0) {
          totalBox.classList.add('show');
        } else {
          totalBox.classList.remove('show');
        }
      }

      // Kliknięcie w wiersz zaznacza checkbox
      document.querySelectorAll('.clickable-row').forEach(row => {
        row.addEventListener('click', function(e) {
          if (e.target.type !== 'checkbox') {
            const cb = row.querySelector('.component_checkbox');
            cb.checked = !cb.checked;
            updateTotal();
          }
        });
      });

      // Kliknięcie samego checkboxa też aktualizuje sumę
      document.querySelectorAll('.component_checkbox').forEach(cb => {
        cb.addEventListener('change', updateTotal);
      });

      // Zaznacz wszystko
      const checkAll = document.getElementById('check_all');
      checkAll.addEventListener('change', function() {
        document.querySelectorAll('.component_checkbox').forEach(cb => cb.checked = checkAll.checked);
        updateTotal();
      });
    });
  </script>

  {literal}
    <style>
      #totalPriceBox {
        position: fixed;
        bottom: 10%;
        right: 20px;
        background: #0d6efd;
        color: #fff;
        padding: 12px 20px;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        font-size: 1.2rem;
        font-weight: bold;
        opacity: 0;
        transform: translateY(50px);
        transition: all 0.5s ease;
        z-index: 9999;
      }

      #totalPriceBox.show {
        opacity: 1;
        transform: translateY(0);
      }

      @keyframes countUp {
        0% {
          transform: scale(1);
        }

        50% {
          transform: scale(1.3);
        }

        100% {
          transform: scale(1);
        }
      }

      #totalPrice {
        display: inline-block;
        animation-duration: 0.5s;
        animation-fill-mode: forwards;
      }
      .photo-accordion {
      border: 1px solid #e0e0e0;
      border-radius: 8px;
      overflow: hidden;
      background: #f9f9f9;
      transition: all 0.3s ease;
      font-family: sans-serif;
    }
    .photo-accordion[open] {
      background: #fff;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .photo-accordion summary {
      padding: 10px 15px;
      list-style: none;
      cursor: pointer;
      font-weight: 600;
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #fff;
      color: #333;
    }
    .photo-accordion summary::-webkit-details-marker { display: none; } /* Ukrywa domyślną strzałkę w Chrome */
    
    .photo-accordion summary::after {
      content: '▼';
      font-size: 0.7rem;
      transition: transform 0.3s ease;
      color: #999;
    }
    .photo-accordion[open] summary::after {
      transform: rotate(180deg);
      content: '▲';
    }
    
    .accordion-content {
      padding: 15px;
      display: flex;
      flex-direction: column;
      gap: 15px;
      animation: slideDown 0.3s ease-out;
    }

    .shop-row {
      border-bottom: 1px solid #f0f0f0;
      padding-bottom: 10px;
    }
    .shop-row:last-child { border-bottom: none; }

    .img-wrapper {
      display: flex;
      flex-wrap: wrap;
      gap: 5px;
      margin-top: 5px;
    }
    
    .thumb-img {
      width: 42px; 
      height: 42px; 
      object-fit: contain; 
      border: 1px solid #ddd; 
      border-radius: 4px; 
      transition: transform 0.2s, border-color 0.2s;
    }
    .thumb-img:hover {
      transform: scale(1.15);
      border-color: #007bff;
      z-index: 10;
    }

    @keyframes slideDown {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    </style>

    


  {/literal}
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      function runInlineScripts(container) {
        const scripts = Array.from(container.querySelectorAll('script'));
        scripts.forEach(old => {
          const script = document.createElement('script');
          // copy attributes
          for (let i = 0; i < old.attributes.length; i++) {
            const a = old.attributes[i];
            script.setAttribute(a.name, a.value);
          }
          if (old.src) {
            script.src = old.src;
            script.async = false;
            document.head.appendChild(script);
          } else {
            script.textContent = old.textContent;
            // append to placeholder so it executes in document context
            container.appendChild(script);
          }
          // remove the original script tag to avoid duplicates
          old.parentNode && old.parentNode.removeChild(old);
        });
      }

      document.querySelectorAll('.ajax-params-placeholder').forEach(function(placeholder) {
        const rel = placeholder.dataset.url;
        const url = new URL(rel, window.location.href).toString();
        const collapse = placeholder.closest('.collapse');
        if (!collapse) return;
        collapse.addEventListener('show.bs.collapse', function() {
          if (placeholder.dataset.loaded) return;
          fetch(url, { credentials: 'same-origin' })
            .then(resp => resp.text().then(text => ({ status: resp.status, ok: resp.ok, text })))
            .then(({ status, ok, text }) => {
              if (!ok) {
                console.error('AJAX load params failed', status, text);
                placeholder.innerHTML = '<div class="text-danger">Błąd ładowania parametrów: ' + (text ? text : status) + '</div>';
                return;
              }
              placeholder.innerHTML = text;
              placeholder.dataset.loaded = '1';
              runInlineScripts(placeholder);
            })
            .catch(err => {
              console.error('Fetch error', err);
              placeholder.innerHTML = '<div class="text-danger">Błąd ładowania parametrów (fetch)</div>';
            });
        });
      });
    });
  </script>
  <!-- TinyMCE -->
<script src="https://cdn.tiny.cloud/1/eatvqy3fy8bse5nkpz765jj1jdjri3rikfn25dal7v7epjjy/tinymce/8/tinymce.min.js" referrerpolicy="origin" crossorigin="anonymous"></script>
<script>
tinymce.init({
  selector: 'textarea',
  height: 350,
  plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount code',
  toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | align lineheight | numlist bullist indent outdent | link image media table | styles | code removeformat',
  
  menubar: false,
  branding: false,
  content_style: "body { font-family: Arial, Helvetica, sans-serif; font-size: 14px; }",

  // 🔥 POZWÓL NA WSZYSTKIE STYLE INLINE
  valid_elements: '*[*]',
  extended_valid_elements: '*[*]',
  valid_styles: {
    '*': 'color,font-size,font-weight,font-style,text-decoration,background-color,border,border-radius,margin,margin-top,margin-bottom,margin-left,margin-right,padding,padding-top,padding-bottom,padding-left,padding-right,width,height,max-width,min-width,max-height,min-height,display,float,position,top,bottom,left,right'
  },

  // 🔧 Pozwala edytować style przez GUI (okno)
  style_formats_merge: true,
  style_formats: [
    { title: 'Obrazek 500px szerokości', selector: 'img', styles: { maxWidth: '500px' } },

  ]
});
</script>
