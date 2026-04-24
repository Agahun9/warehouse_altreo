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

  <div class="app-content">
    <div class="container-fluid">
      {if $flashSuccess}
        <div class="alert alert-success">{$flashSuccess|escape}</div>
      {/if}
      {if $flashError}
        <div class="alert alert-danger">{$flashError|escape}</div>
      {/if}

      <div class="card mb-4">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <h3 class="card-title mb-1">Wszystkie kategorie</h3>
            <div class="text-secondary small">Kategorie sa wykorzystywane przy tworzeniu i edycji produktow.</div>
          </div>
          <a href="{$baseUrl}?controller=categories&action=create" class="btn btn-success">Dodaj kategorie</a>
        </div>
      </div>

      <div class="card">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-striped table-hover table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>ID</th>
                  <th>Nazwa</th>
                  <th>Slug</th>
                  <th>Prefix SKU</th>
                  <th>Allegro ID</th>
                  <th>Empik ID</th>
                  <th>Opis</th>
                  <th>Produkty</th>
                  <th>Utworzono</th>
                  <th>Zmieniono</th>
                  <th class="text-end">Akcje</th>
                </tr>
              </thead>
              <tbody>
                {if $categories}
                  {foreach $categories as $category}
                    <tr>
                      <td>{$category.id}</td>
                      <td class="fw-semibold">{$category.name|escape}</td>
                      <td><code>{$category.slug|escape}</code></td>
                      <td><span class="badge text-bg-dark">{$category.sku_prefix|default:'PRD'|escape}</span></td>
                      <td>{if $category.allegro_category_id}<code>{$category.allegro_category_id|escape}</code>{else}-{/if}</td>
                      <td>{if $category.empik_category_id}<code>{$category.empik_category_id|escape}</code>{else}-{/if}</td>
                      <td>{$category.description|default:'-'|truncate:100|escape}</td>
                      <td><span class="badge text-bg-secondary">{$category.products_count|default:0}</span></td>
                      <td>{$category.created_at|default:'-'}</td>
                      <td>{$category.updated_at|default:'-'}</td>
                      <td class="text-end">
                        <a href="{$baseUrl}?controller=categories&action=edit&id={$category.id}" class="btn btn-sm btn-outline-primary">Edytuj</a>
                        {if $category.products_count|default:0 > 0}
                          <button type="button" class="btn btn-sm btn-outline-danger" disabled>Usun</button>
                        {else}
                          <form method="post" action="{$baseUrl}?controller=categories&action=delete" class="d-inline">
                            <input type="hidden" name="id" value="{$category.id}">
                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Usunac te kategorie?');">Usun</button>
                          </form>
                        {/if}
                      </td>
                    </tr>
                  {/foreach}
                {else}
                  <tr>
                    <td colspan="11" class="text-center py-4">Brak kategorii do wyswietlenia.</td>
                  </tr>
                {/if}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

