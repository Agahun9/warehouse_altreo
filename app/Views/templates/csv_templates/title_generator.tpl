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
      <div class="card mb-4">
        <div class="card-body">
          <h3 class="card-title mb-2">Jak tego uzyc</h3>
          <ol class="small mb-0 ps-3">
            <li>W szablonie CSV dodaj kolumne typu <code>field</code>.</li>
            <li>Jako zrodlo wybierz <code>product.generated_title</code>.</li>
            <li>Na liscie produktow, przy eksporcie CSV, wybierz szablon tytulu i wpisz <code>Kolekcje do tytulu</code>.</li>
            <li>Generator sam pobierze z rozszyfrowanych parametrow Allegro dedykowany model i dedykowana marke.</li>
          </ol>
          <div class="mt-3">
            <a href="{$baseUrl}?controller=csvtemplates&action=createtitle" class="btn btn-primary">Dodaj szablon tytulu</a>
          </div>
        </div>
      </div>

      <div class="row g-3">
        {foreach $titleTemplates as $templateKey => $titleTemplate}
          <div class="col-lg-6">
            <div class="card h-100">
              <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">{$titleTemplate.name|escape}</h3>
                <span class="badge text-bg-secondary">{$templateKey|escape}</span>
              </div>
              <div class="card-body">
                <p class="mb-2">{$titleTemplate.description|escape}</p>
                <div class="mb-3">
                  <div class="small fw-semibold mb-1">Wzor:</div>
                  <pre class="bg-light border rounded p-2 small mb-0"><code>{$titleTemplate.template_body|default:$titleTemplate.pattern|escape}</code></pre>
                </div>
                <div class="mb-3">
                  <div class="small fw-semibold mb-1">Przyklad:</div>
                  <div class="border rounded p-2 bg-light">{$titleTemplate.example|default:'Ustalany dynamicznie podczas eksportu'|escape}</div>
                </div>
                <div class="d-flex gap-2">
                  <a href="{$baseUrl}?controller=csvtemplates&action=edittitle&id={$titleTemplate.id}" class="btn btn-sm btn-outline-primary">Edytuj</a>
                  <form method="post" action="{$baseUrl}?controller=csvtemplates&action=deletetitle" class="d-inline" onsubmit="return confirm('Usunac szablon tytulu {$titleTemplate.name|escape:'javascript'}?');">
                    <input type="hidden" name="id" value="{$titleTemplate.id}">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Usun</button>
                  </form>
                </div>
              </div>
            </div>
          </div>
        {/foreach}
      </div>

      <div class="card mt-4">
        <div class="card-header"><h3 class="card-title mb-0">Dostepne tokeny</h3></div>
        <div class="card-body">
          <div class="row g-2">
            {foreach $availableTitleTokens as $token => $label}
              <div class="col-lg-6">
                <div class="border rounded p-2 small">
                  <code>{$token|escape}</code><br>
                  <span class="text-secondary">{$label|escape}</span>
                </div>
              </div>
            {/foreach}
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
