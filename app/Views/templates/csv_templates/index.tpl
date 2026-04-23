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
      {if $flashSuccess}<div class="alert alert-success">{$flashSuccess|escape}</div>{/if}
      {if $flashError}<div class="alert alert-danger">{$flashError|escape}</div>{/if}

      <div class="card mb-4">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <h3 class="card-title mb-1">Szablony eksportu</h3>
            <div class="text-secondary small">Tworzenie i zarzadzanie konfiguracjami CSV do eksportu produktów.</div>
          </div>
          <div class="d-flex gap-2">
            <a href="{$baseUrl}?controller=csvtemplates&action=importproducts" class="btn btn-outline-primary">Import produktow</a>
            <a href="{$baseUrl}?controller=csvtemplates&action=titlegenerator" class="btn btn-outline-secondary">Generator tytulow</a>
            <a href="{$baseUrl}?controller=csvtemplates&action=create" class="btn btn-primary">Dodaj szablon</a>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">Presety</h3></div>
        <div class="card-body d-flex flex-wrap gap-2">
          {foreach $presets as $presetKey => $preset}
            <a href="{$baseUrl}?controller=csvtemplates&action=create&preset={$presetKey|escape}" class="btn btn-outline-secondary btn-sm">{$preset.name|escape}</a>
          {/foreach}
        </div>
      </div>

      <div class="card">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-striped table-hover table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Nazwa</th>
                  <th>Opis</th>
                  <th>Format</th>
                  <th>Kolumny</th>
                  <th>Utworzono</th>
                  <th>Zmieniono</th>
                  <th class="text-end">Akcje</th>
                </tr>
              </thead>
              <tbody>
                {if $templates}
                  {foreach $templates as $template}
                    <tr>
                      <td class="fw-semibold">{$template.name|escape}</td>
                      <td>{$template.description|default:'-'|truncate:120|escape}</td>
                      <td>
                        <div><span class="badge text-bg-light border">{$template.delimiter|default:';'|escape}</span></div>
                        <div class="small text-secondary mt-1">{$template.encoding|default:'UTF-8'|escape}{if $template.add_bom|default:0} + BOM{/if}</div>
                      </td>
                      <td><span class="badge text-bg-secondary">{$template.columns_count|default:0}</span></td>
                      <td>{$template.created_at|default:'-'|escape}</td>
                      <td>{$template.updated_at|default:'-'|escape}</td>
                      <td class="text-end">
                        <a href="{$baseUrl}?controller=csvtemplates&action=edit&id={$template.id}" class="btn btn-sm btn-outline-primary">Edytuj</a>
                        <form method="post" action="{$baseUrl}?controller=csvtemplates&action=duplicate" class="d-inline">
                          <input type="hidden" name="id" value="{$template.id}">
                          <button type="submit" class="btn btn-sm btn-outline-secondary">Duplikuj</button>
                        </form>
                        <form method="post" action="{$baseUrl}?controller=csvtemplates&action=delete" class="d-inline" onsubmit="return confirm('Usunac szablon {$template.name|escape:'javascript'}?');">
                          <input type="hidden" name="id" value="{$template.id}">
                          <button type="submit" class="btn btn-sm btn-outline-danger">Usun</button>
                        </form>
                      </td>
                    </tr>
                  {/foreach}
                {else}
                  <tr><td colspan="7" class="text-center py-4">Brak szablonow CSV.</td></tr>
                {/if}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
