<main class="app-main">
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row g-3 align-items-center">
        <div class="col-lg-8">
          <h3 class="mb-1">{$contentTitle|escape}</h3>
          <p class="text-secondary mb-0">{$pageDescription|escape}</p>
        </div>
        <div class="col-lg-4">
          <ol class="breadcrumb float-lg-end mb-0">
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=index">Start</a></li>
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=products&action=index">Produkty</a></li>
            <li class="breadcrumb-item active" aria-current="page">{$breadcrumbCurrent|escape}</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <style>
    .contours-manager-shell {
      --cm-accent: #14532d;
      --cm-accent-soft: rgba(20, 83, 45, 0.08);
      --cm-line: rgba(15, 23, 42, 0.08);
    }

    .contours-manager-hero {
      border: 1px solid var(--cm-line);
      border-radius: 1rem;
      padding: 1.25rem 1.35rem;
      background:
        radial-gradient(circle at top right, rgba(34, 197, 94, 0.16), transparent 28%),
        linear-gradient(135deg, #ffffff 0%, #f3fbf5 100%);
      box-shadow: 0 14px 32px rgba(15, 23, 42, 0.06);
    }

    .contours-manager-card {
      border: 1px solid var(--cm-line);
      border-radius: 1rem;
      overflow: hidden;
      box-shadow: 0 14px 28px rgba(15, 23, 42, 0.05);
      background: #fff;
    }

    .contours-manager-card .card-header {
      background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
      border-bottom: 1px solid var(--cm-line);
    }

    .contours-directory-list {
      display: grid;
      gap: .5rem;
      max-height: 68vh;
      overflow: auto;
      padding-right: .2rem;
    }

    .contours-directory-item {
      display: block;
      border: 1px solid rgba(15, 23, 42, 0.08);
      border-radius: .8rem;
      padding: .65rem .8rem;
      text-decoration: none;
      color: inherit;
      background: #fff;
      transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }

    .contours-directory-item:hover {
      transform: translateY(-1px);
      border-color: rgba(20, 83, 45, 0.24);
      box-shadow: 0 10px 20px rgba(20, 83, 45, 0.08);
    }

    .contours-directory-item.active {
      border-color: rgba(20, 83, 45, 0.34);
      background: linear-gradient(135deg, rgba(20, 83, 45, 0.06), rgba(34, 197, 94, 0.07));
      box-shadow: 0 14px 24px rgba(20, 83, 45, 0.09);
    }

    .contours-directory-name {
      font-weight: 600;
      font-size: .92rem;
      color: #111827;
      word-break: break-word;
      line-height: 1.3;
    }

    .contours-directory-meta {
      margin-top: .2rem;
      font-size: .78rem;
      color: #6b7280;
    }

    .contours-directory-search {
      margin-bottom: .85rem;
    }

    .contours-directory-search .form-control {
      border-radius: .85rem;
    }

    .contours-directory-empty {
      padding: 1rem;
      border: 1px dashed rgba(15, 23, 42, 0.12);
      border-radius: .85rem;
      text-align: center;
      color: #6b7280;
      font-size: .88rem;
      background: #fafafa;
    }

    .contours-manager-form {
      display: grid;
      gap: .9rem;
    }

    .contours-manager-form .form-control {
      border-radius: .8rem;
    }

    .contours-upload-dropzone {
      position: relative;
      border: 2px dashed rgba(20, 83, 45, 0.26);
      border-radius: 1rem;
      padding: 1.4rem;
      background: linear-gradient(180deg, rgba(240, 253, 244, 0.9), #fff);
      text-align: center;
      transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
    }

    .contours-upload-dropzone.is-dragover {
      border-color: #15803d;
      box-shadow: 0 14px 30px rgba(21, 128, 61, 0.12);
      transform: translateY(-1px);
    }

    .contours-upload-dropzone input[type=file] {
      position: absolute;
      inset: 0;
      opacity: 0;
      cursor: pointer;
    }

    .contours-file-list {
      display: grid;
      gap: .7rem;
    }

    .contours-file-item {
      display: flex;
      justify-content: space-between;
      gap: 1rem;
      border: 1px solid rgba(15, 23, 42, 0.07);
      border-radius: .85rem;
      padding: .75rem .9rem;
      background: #fff;
    }

    .contours-file-item strong {
      display: block;
      color: #111827;
      word-break: break-word;
    }

    .contours-file-meta {
      font-size: .82rem;
      color: #6b7280;
    }
  </style>

  <div class="app-content contours-manager-shell">
    <div class="container-fluid">
      {if $flashSuccess}<div class="alert alert-success">{$flashSuccess|escape}</div>{/if}
      {if $flashError}<div class="alert alert-danger">{$flashError|escape}</div>{/if}

      <div class="contours-manager-hero mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
          <div>
            <div class="small text-uppercase fw-semibold text-success mb-2">OBRYSY_GENERATOR</div>
            <h4 class="mb-1">Foldery obrysow i wrzutka plikow</h4>
            <p class="text-secondary mb-0">Wybierz folder z listy, zmien mu nazwe, usun go albo przerzuc pliki bezposrednio do wybranego katalogu.</p>
            {if $selectedContourDirectory}
              <div class="small mt-2 {if $selectedContourDirectoryWritable}text-success{else}text-danger{/if}">
                {if $selectedContourDirectoryWritable}
                  Wybrany folder ma uprawnienia do zapisu.
                {else}
                  Wybrany folder nie ma uprawnien do zapisu albo serwer nie moze do niego wejsc.
                {/if}
              </div>
            {/if}
          </div>
          <div class="d-flex gap-2">
            <a href="{$baseUrl}?controller=products&action=index" class="btn btn-outline-secondary">Wroc do produktow</a>
          </div>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-xl-4">
          <div class="card contours-manager-card mb-4">
            <div class="card-header">
              <h5 class="mb-1">Dodaj folder</h5>
              <div class="small text-secondary">Utworz nowy katalog obrysu widoczny potem na formularzu produktu.</div>
            </div>
            <div class="card-body">
              <form method="post" action="{$baseUrl}?controller=products&action=createcontourdirectory" class="contours-manager-form">
                <div>
                  <label for="contourNewName" class="form-label">Nazwa folderu</label>
                  <input type="text" id="contourNewName" name="name" class="form-control" placeholder="np. iphone_16_clear" required>
                </div>
                <button type="submit" class="btn btn-success">Dodaj folder</button>
              </form>
            </div>
          </div>

          <div class="card contours-manager-card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <div>
                <h5 class="mb-1">Foldery</h5>
                <div class="small text-secondary">Lacznie: {$contourDirectories|@count}</div>
              </div>
            </div>
            <div class="card-body">
              <div class="contours-directory-search">
                <input type="text" id="contoursDirectorySearch" class="form-control" placeholder="Wyszukaj folder obrysu">
              </div>
              <div class="contours-directory-list">
                {if $contourDirectories}
                  {foreach $contourDirectories as $directory}
                    <a href="{$baseUrl}?controller=products&action=contoursmanager&directory={$directory.name|escape:'url'}" class="contours-directory-item{if $selectedContourDirectory eq $directory.name} active{/if}" data-directory-item data-directory-name="{$directory.name|lower|escape}">
                      <div class="contours-directory-name">{$directory.name|escape}</div>
                      <div class="contours-directory-meta">
                        Plikow: {$directory.files_count|escape}
                        {if $directory.modified_at}
                          <span class="mx-1">•</span> Ostatnia zmiana: {$directory.modified_at|escape}
                        {/if}
                      </div>
                    </a>
                  {/foreach}
                {else}
                  <div class="text-secondary small">Brak folderow w katalogu OBRYSY_GENERATOR.</div>
                {/if}
              </div>
              <div class="contours-directory-empty mt-3 d-none" id="contoursDirectoryEmpty">Brak folderow pasujacych do wyszukiwania.</div>
            </div>
          </div>
        </div>

        <div class="col-xl-8">
          <div class="card contours-manager-card mb-4">
            <div class="card-header">
              <h5 class="mb-1">Wybrany folder</h5>
              <div class="small text-secondary">
                {if $selectedContourDirectory}
                  Aktualnie pracujesz na: <strong>{$selectedContourDirectory|escape}</strong>
                {else}
                  Wybierz folder z listy po lewej.
                {/if}
              </div>
            </div>
            <div class="card-body">
              {if $selectedContourDirectory}
                <div class="row g-4">
                  <div class="col-lg-6">
                    <form method="post" action="{$baseUrl}?controller=products&action=renamecontourdirectory" class="contours-manager-form">
                      <input type="hidden" name="directory" value="{$selectedContourDirectory|escape}">
                      <div>
                        <label for="contourRename" class="form-label">Zmien nazwe folderu</label>
                        <input type="text" id="contourRename" name="new_name" class="form-control" value="{$selectedContourDirectory|escape}" required>
                      </div>
                      <button type="submit" class="btn btn-primary">Zapisz nowa nazwe</button>
                    </form>
                  </div>
                  <div class="col-lg-6">
                    <form method="post" action="{$baseUrl}?controller=products&action=deletecontourdirectory" class="contours-manager-form" onsubmit="return confirm('Usunac folder {$selectedContourDirectory|escape:'javascript'} wraz z cala zawartoscia?');">
                      <input type="hidden" name="directory" value="{$selectedContourDirectory|escape}">
                      <div class="small text-secondary">
                        Usuwanie dziala rekurencyjnie, wiec skasuje tez pliki i podfoldery z wybranego obrysu.
                      </div>
                      <button type="submit" class="btn btn-outline-danger">Usun folder</button>
                    </form>
                  </div>
                </div>
              {else}
                <div class="text-secondary">Brak wybranego folderu.</div>
              {/if}
            </div>
          </div>

          <div class="card contours-manager-card mb-4">
            <div class="card-header">
              <h5 class="mb-1">Przerzuc aby wgrac</h5>
              <div class="small text-secondary">Mozesz wrzucic wiele plikow naraz do wybranego folderu obrysu.</div>
            </div>
            <div class="card-body">
              {if $selectedContourDirectory}
                <form method="post" action="{$contoursUploadUrl|escape}" enctype="multipart/form-data" class="contours-manager-form" id="contoursUploadForm" data-no-page-loader="1">
                  <input type="hidden" name="directory" value="{$selectedContourDirectory|escape}">
                  <label class="contours-upload-dropzone" id="contoursUploadDropzone">
                    <input type="file" name="contour_files[]" id="contourFiles" multiple>
                    <div class="fw-semibold mb-2">Przerzuc pliki tutaj albo kliknij, aby wybrac</div>
                    <div class="small text-secondary">Pliki zostana zapisane bezposrednio w folderze <strong>{$selectedContourDirectory|escape}</strong>.</div>
                    <div class="small mt-2 text-secondary" id="contourFilesSummary">Nie wybrano jeszcze plikow.</div>
                  </label>
                  <div class="small mt-2 d-none" id="contourUploadStatus" role="status" aria-live="polite"></div>
                  <div>
                    <button type="submit" class="btn btn-success">Wgraj pliki</button>
                  </div>
                </form>
              {else}
                <div class="text-secondary">Najpierw wybierz folder, do ktorego chcesz wrzucic pliki.</div>
              {/if}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<script>
  (function () {
    var input = document.getElementById('contourFiles');
    var summary = document.getElementById('contourFilesSummary');
    var dropzone = document.getElementById('contoursUploadDropzone');
    var uploadForm = document.getElementById('contoursUploadForm');
    var uploadStatus = document.getElementById('contourUploadStatus');
    var searchInput = document.getElementById('contoursDirectorySearch');
    var directoryItems = Array.prototype.slice.call(document.querySelectorAll('[data-directory-item]'));
    var emptyState = document.getElementById('contoursDirectoryEmpty');

    function setUploadStatus(message, type) {
      if (!uploadStatus) {
        return;
      }

      uploadStatus.classList.remove('d-none', 'text-secondary', 'text-success', 'text-danger');
      uploadStatus.classList.add(type === 'error' ? 'text-danger' : (type === 'success' ? 'text-success' : 'text-secondary'));
      uploadStatus.textContent = message || '';
    }

    function renderSummary() {
      if (!summary || !input) {
        return;
      }

      var files = input.files ? Array.prototype.slice.call(input.files) : [];
      if (!files.length) {
        summary.textContent = 'Nie wybrano jeszcze plikow.';
        return;
      }

      summary.textContent = 'Wybrane pliki: ' + files.map(function (file) {
        return file.name;
      }).join(', ');
    }

    function filterDirectories() {
      if (!searchInput || !directoryItems.length) {
        return;
      }

      var query = String(searchInput.value || '').trim().toLowerCase();
      var visibleCount = 0;

      directoryItems.forEach(function (item) {
        var name = String(item.getAttribute('data-directory-name') || '');
        var matches = query === '' || name.indexOf(query) !== -1;
        item.classList.toggle('d-none', !matches);
        if (matches) {
          visibleCount += 1;
        }
      });

      if (emptyState) {
        emptyState.classList.toggle('d-none', visibleCount !== 0);
      }
    }

    if (input) {
      input.addEventListener('change', renderSummary);
    }

    if (searchInput) {
      searchInput.addEventListener('input', filterDirectories);
      filterDirectories();
    }

    if (dropzone) {
      ['dragenter', 'dragover'].forEach(function (eventName) {
        dropzone.addEventListener(eventName, function (event) {
          event.preventDefault();
          dropzone.classList.add('is-dragover');
        });
      });

      ['dragleave', 'drop'].forEach(function (eventName) {
        dropzone.addEventListener(eventName, function (event) {
          event.preventDefault();
          dropzone.classList.remove('is-dragover');
        });
      });

      dropzone.addEventListener('drop', function (event) {
        if (!input || !event.dataTransfer || !event.dataTransfer.files) {
          return;
        }

        input.files = event.dataTransfer.files;
        renderSummary();
      });
    }

    if (uploadForm && input && window.fetch && window.FormData) {
      uploadForm.addEventListener('submit', function (event) {
        if (!input.files || !input.files.length) {
          return;
        }

        event.preventDefault();
        setUploadStatus('Trwa wysylanie plikow...', 'info');

        var submitButton = uploadForm.querySelector('button[type="submit"]');
        if (submitButton) {
          submitButton.disabled = true;
        }

        fetch(uploadForm.action, {
          method: 'POST',
          body: new FormData(uploadForm),
          credentials: 'same-origin',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }
        })
          .then(function (response) {
            return response.json()
              .catch(function () {
                return { error: 'Serwer zwrocil nieczytelna odpowiedz.', status: response.status };
              })
              .then(function (payload) {
                return { ok: response.ok, status: response.status, payload: payload || {} };
              });
          })
          .then(function (result) {
            if (!result.ok || result.payload.error) {
              throw new Error(result.payload.error || ('Upload nie udal sie. Kod: ' + result.status));
            }

            setUploadStatus(result.payload.message || 'Pliki zostaly wgrane.', 'success');
            input.value = '';
            renderSummary();

            window.setTimeout(function () {
              window.location.href = result.payload.redirect || window.location.href;
            }, 450);
          })
          .catch(function (error) {
            setUploadStatus(error && error.message ? error.message : 'Przegladarka zablokowala wysylke lub serwer odrzucil upload.', 'error');
          })
          .finally(function () {
            if (submitButton) {
              submitButton.disabled = false;
            }
          });
      });
    }
  })();
</script>
