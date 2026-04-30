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
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=csvtemplates&action=index">Szablony CSV</a></li>
            <li class="breadcrumb-item active" aria-current="page">{$breadcrumbCurrent|escape}</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      <style>
        .csv-title-generator-hero {
          display: grid;
          grid-template-columns: minmax(0, 1fr) auto;
          gap: 1rem 1.25rem;
          align-items: start;
        }

        .csv-title-generator-hero-copy {
          min-width: 0;
        }

        .csv-title-generator-hero-copy .card-title,
        .csv-title-generator-help .card-title {
          line-height: 1.2;
        }

        .csv-title-generator-hero-copy .text-secondary,
        .csv-title-generator-help ol {
          overflow-wrap: anywhere;
          word-break: break-word;
        }

        .csv-title-generator-actions {
          display: flex;
          flex-wrap: wrap;
          justify-content: flex-end;
          gap: 0.75rem;
          min-width: min(100%, 22rem);
        }

        .csv-title-generator-actions .btn {
          white-space: nowrap;
        }

        .csv-title-generator-help ol li + li {
          margin-top: 0.35rem;
        }

        @media (max-width: 767.98px) {
          .csv-title-generator-hero {
            grid-template-columns: minmax(0, 1fr);
          }

          .csv-title-generator-actions {
            min-width: 0;
            justify-content: stretch;
          }

          .csv-title-generator-actions .btn {
            flex: 1 1 100%;
            white-space: normal;
          }
        }
      </style>

      {if $flashSuccess}<div class="alert alert-success">{$flashSuccess|escape}</div>{/if}
      {if $flashError}<div class="alert alert-danger">{$flashError|escape}</div>{/if}
      {assign var=canWriteCsvTemplates value=$currentUser.role eq 'admin' or $currentUser.module_permissions.csvtemplates|default:'' eq 'edit'}

      <div class="card mb-4">
        <div class="card-body csv-title-generator-hero">
          <div class="csv-title-generator-hero-copy">
            <h3 class="card-title mb-1">Szablony tytulow</h3>
            <div class="text-secondary small">Szablony do pola <code>product.generated_title</code> uzywanego podczas eksportu CSV.</div>
          </div>
          <div class="csv-title-generator-actions">
            <a href="{$baseUrl}?controller=csvtemplates&action=index" class="btn btn-outline-secondary">Szablony CSV</a>
            {if $canWriteCsvTemplates}
              <a href="{$baseUrl}?controller=csvtemplates&action=createtitle" class="btn btn-primary">Dodaj szablon tytulu</a>
            {/if}
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-body csv-title-generator-help">
          <h3 class="card-title mb-2">Jak tego uzyc</h3>
          <ol class="small mb-0 ps-3">
            <li>W szablonie CSV dodaj kolumne typu <code>field</code>.</li>
            <li>Jako zrodlo wybierz <code>product.generated_title</code>.</li>
            <li>Na liscie produktow, przy eksporcie CSV, wybierz szablon tytulu i wpisz <code>Kolekcje do tytulu</code>.</li>
            <li>Generator sam pobierze z rozszyfrowanych parametrow Allegro dedykowany model i dedykowana marke.</li>
          </ol>
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
                  <th>Wzor</th>
                  <th>Utworzono</th>
                  <th>Zmieniono</th>
                  <th class="text-end">Akcje</th>
                </tr>
              </thead>
              <tbody>
                {if $titleTemplates}
                  {foreach $titleTemplates as $titleTemplate}
                    <tr>
                      <td class="fw-semibold">{$titleTemplate.name|escape}</td>
                      <td>{$titleTemplate.description|default:'-'|truncate:120|escape}</td>
                      <td>
                        <pre class="bg-light border rounded p-2 small mb-0"><code>{$titleTemplate.template_body|default:$titleTemplate.pattern|escape}</code></pre>
                      </td>
                      <td>{$titleTemplate.created_at|default:'-'|escape}</td>
                      <td>{$titleTemplate.updated_at|default:'-'|escape}</td>
                      <td class="text-end">
                        {if $canWriteCsvTemplates}
                          <a href="{$baseUrl}?controller=csvtemplates&action=edittitle&id={$titleTemplate.id}" class="btn btn-sm btn-outline-primary">Edytuj</a>
                          <form method="post" action="{$baseUrl}?controller=csvtemplates&action=deletetitle" class="d-inline" onsubmit="return confirm('Usunac szablon tytulu {$titleTemplate.name|escape:'javascript'}?');">
                            <input type="hidden" name="id" value="{$titleTemplate.id}">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Usun</button>
                          </form>
                        {else}
                          <span class="badge text-bg-light border">Odczyt</span>
                        {/if}
                      </td>
                    </tr>
                  {/foreach}
                {else}
                  <tr><td colspan="6" class="text-center py-4">Brak szablonow tytulow.</td></tr>
                {/if}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-between mt-4 mb-4">
        <a href="{$baseUrl}?controller=csvtemplates&action=index" class="btn btn-outline-secondary">Wroc do listy</a>
        <a href="{$baseUrl}?controller=products&action=index" class="btn btn-primary">Przejdz do eksportu produktow</a>
      </div>
    </div>
  </div>
</main>
