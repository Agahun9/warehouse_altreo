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

    .contours-upload-dropzone.is-busy {
      opacity: .72;
      pointer-events: none;
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
            <div class="small text-uppercase fw-semibold text-success mb-2">Baza danych</div>
            <h4 class="mb-1">Nazwy obrysow</h4>
            <p class="text-secondary mb-0">Tutaj zarzadzasz sama lista nazw obrysow. Pliki i foldery mozesz trzymac osobno, a system zapamietuje tylko nazwe potrzebna na produkcie.</p>
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
              <h5 class="mb-1">Dodaj obrys</h5>
              <div class="small text-secondary">Dodaj nowa nazwe obrysu widoczna potem na formularzu produktu.</div>
            </div>
            <div class="card-body">
              <form method="post" action="{$baseUrl}?controller=products&action=createcontourdirectory" class="contours-manager-form">
                <div>
                  <label for="contourNewName" class="form-label">Nazwa obrysu</label>
                  <input type="text" id="contourNewName" name="name" class="form-control" placeholder="np. iphone_16_clear" required>
                </div>
                <button type="submit" class="btn btn-success">Dodaj obrys</button>
              </form>
            </div>
          </div>

          <div class="card contours-manager-card mb-4">
            <div class="card-header">
              <h5 class="mb-1">Import folderow</h5>
              <div class="small text-secondary">Mozesz wskazac wiele folderow naraz. System odczyta ich nazwy i doda tylko brakujace.</div>
            </div>
            <div class="card-body">
              <form method="post" action="{$baseUrl}?controller=products&action=uploadcontourfiles" class="contours-manager-form" enctype="multipart/form-data">
                <input type="hidden" name="contour_directory_names" id="contourDirectoryNames" value="">
                <div>
                  <label for="contourDirectoryFiles" class="form-label">Foldery do porownania</label>
                  <label class="contours-upload-dropzone" id="contourDirectoryDropzone">
                    <input type="file" id="contourDirectoryFiles" name="contour_directory_files[]" class="form-control" webkitdirectory directory multiple>
                    <div class="fw-semibold mb-2">Upusc tutaj kilka folderow naraz</div>
                    <div class="small text-secondary">Mozesz przeciagnac wiele katalogow jednoczesnie albo kliknac i wybrac folder.</div>
                    <div class="small text-secondary mt-2" id="contourDirectoryFilesSummary">Nie wybrano jeszcze folderow.</div>
                  </label>
                  <div class="form-text">Wrzucone foldery beda porownane z baza. Duplikaty zostana pominiete.</div>
                  <div class="small mt-2 d-none" id="contourDirectoryImportStatus" role="status" aria-live="polite"></div>
                </div>
                <button type="submit" class="btn btn-success" id="contourDirectoryImportButton">Importuj foldery</button>
              </form>
            </div>
          </div>

          <div class="card contours-manager-card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <div>
                <h5 class="mb-1">Obrysy</h5>
                <div class="small text-secondary">Lacznie: {$contourDirectories|@count}</div>
              </div>
            </div>
            <div class="card-body">
              <div class="contours-directory-search">
                <input type="text" id="contoursDirectorySearch" class="form-control" placeholder="Wyszukaj obrys">
              </div>
              <div class="contours-directory-list">
                {if $contourDirectories}
                  {foreach $contourDirectories as $directory}
                    <a href="{$baseUrl}?controller=products&action=contoursmanager&directory={$directory.name|escape:'url'}" class="contours-directory-item{if $selectedContourDirectory eq $directory.name} active{/if}" data-directory-item data-directory-name="{$directory.name|lower|escape}">
                      <div class="contours-directory-name">{$directory.name|escape}</div>
                      <div class="contours-directory-meta">
                        Przypisanych produktow: {$directory.products_count|escape}
                        {if $directory.modified_at}
                          <span class="mx-1">•</span> Ostatnia zmiana: {$directory.modified_at|escape}
                        {/if}
                      </div>
                    </a>
                  {/foreach}
                {else}
                  <div class="text-secondary small">Brak zapisanych nazw obrysow.</div>
                {/if}
              </div>
              <div class="contours-directory-empty mt-3 d-none" id="contoursDirectoryEmpty">Brak obrysow pasujacych do wyszukiwania.</div>
            </div>
          </div>
        </div>

        <div class="col-xl-8">
          <div class="card contours-manager-card mb-4">
            <div class="card-header">
              <h5 class="mb-1">Wybrany obrys</h5>
              <div class="small text-secondary">
                {if $selectedContourDirectory}
                  Aktualnie pracujesz na: <strong>{$selectedContourDirectory|escape}</strong>
                {else}
                  Wybierz obrys z listy po lewej.
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
                        <label for="contourRename" class="form-label">Zmien nazwe obrysu</label>
                        <input type="text" id="contourRename" name="new_name" class="form-control" value="{$selectedContourDirectory|escape}" required>
                      </div>
                      <button type="submit" class="btn btn-primary">Zapisz nowa nazwe</button>
                    </form>
                  </div>
                  <div class="col-lg-6">
                    <form method="post" action="{$baseUrl}?controller=products&action=deletecontourdirectory" class="contours-manager-form" onsubmit="return confirm('Usunac obrys {$selectedContourDirectory|escape:'javascript'} z listy? Produkty z tym obrysem zostana wyczyszczone.');">
                      <input type="hidden" name="directory" value="{$selectedContourDirectory|escape}">
                      <div class="small text-secondary">
                        Usuniecie obrysu wyczysci jego nazwe w produktach, ktore sa do niego przypiete.
                      </div>
                      <button type="submit" class="btn btn-outline-danger">Usun obrys</button>
                    </form>
                  </div>
                </div>
              {else}
                <div class="text-secondary">Brak wybranego obrysu.</div>
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
    var directoryFilesInput = document.getElementById('contourDirectoryFiles');
    var directoryNamesInput = document.getElementById('contourDirectoryNames');
    var directoryFilesSummary = document.getElementById('contourDirectoryFilesSummary');
    var directoryDropzone = document.getElementById('contourDirectoryDropzone');
    var directoryImportStatus = document.getElementById('contourDirectoryImportStatus');
    var directoryImportButton = document.getElementById('contourDirectoryImportButton');
    var importForm = directoryFilesInput ? directoryFilesInput.form : null;
    var searchInput = document.getElementById('contoursDirectorySearch');
    var directoryItems = Array.prototype.slice.call(document.querySelectorAll('[data-directory-item]'));
    var emptyState = document.getElementById('contoursDirectoryEmpty');
    var stagedDirectoryNames = [];

    function setDirectoryImportStatus(message, type) {
      if (!directoryImportStatus) {
        return;
      }

      directoryImportStatus.classList.remove('d-none', 'text-secondary', 'text-success', 'text-danger');
      directoryImportStatus.classList.add(type === 'error' ? 'text-danger' : (type === 'success' ? 'text-success' : 'text-secondary'));
      directoryImportStatus.textContent = message || '';
    }

    function uniqueSortedNames(names) {
      var seen = {};
      var result = [];
      (names || []).forEach(function (name) {
        var value = String(name || '').trim();
        if (!value || seen[value]) {
          return;
        }
        seen[value] = true;
        result.push(value);
      });

      return result.sort(function (left, right) {
        return left.localeCompare(right);
      });
    }

    function syncDirectoryNamesInput() {
      if (directoryNamesInput) {
        directoryNamesInput.value = stagedDirectoryNames.length ? JSON.stringify(stagedDirectoryNames) : '';
      }
    }

    function renderDirectoryImportSummary() {
      if (!directoryFilesInput || !directoryFilesSummary) {
        return;
      }

      if (stagedDirectoryNames.length) {
        directoryFilesSummary.textContent = 'Wykryte foldery: ' + stagedDirectoryNames.join(', ');
        syncDirectoryNamesInput();
        return;
      }

      var files = directoryFilesInput.files ? Array.prototype.slice.call(directoryFilesInput.files) : [];
      if (!files.length) {
        directoryFilesSummary.textContent = 'Nie wybrano jeszcze folderow.';
        syncDirectoryNamesInput();
        return;
      }

      var uniqueFolders = {};
      files.forEach(function (file) {
        var relativePath = String(file.webkitRelativePath || file.name || '');
        var normalizedPath = relativePath.replace(/\\/g, '/');
        var folderName = normalizedPath.split('/')[0] || '';
        if (folderName) {
          uniqueFolders[folderName] = true;
        }
      });

      var names = uniqueSortedNames(Object.keys(uniqueFolders));
      if (!names.length) {
        directoryFilesSummary.textContent = 'Wybrano pliki, ale nie udalo sie wykryc nazw folderow.';
        syncDirectoryNamesInput();
        return;
      }

      stagedDirectoryNames = names;
      directoryFilesSummary.textContent = 'Wykryte foldery: ' + names.join(', ');
      syncDirectoryNamesInput();
    }

    function applyDroppedDirectoryNames(names) {
      stagedDirectoryNames = uniqueSortedNames(names);
      renderDirectoryImportSummary();
      setDirectoryImportStatus('', 'info');
    }

    function extractDroppedDirectoryNames(items) {
      var result = [];
      Array.prototype.slice.call(items || []).forEach(function (item) {
        if (!item) {
          return;
        }

        var entry = typeof item.webkitGetAsEntry === 'function' ? item.webkitGetAsEntry() : null;
        if (entry && entry.isDirectory && entry.name) {
          result.push(entry.name);
          return;
        }

        var file = typeof item.getAsFile === 'function' ? item.getAsFile() : null;
        if (!file) {
          return;
        }

        var relativePath = String(file.webkitRelativePath || file.name || '');
        var normalizedPath = relativePath.replace(/\\/g, '/');
        var folderName = normalizedPath.split('/')[0] || '';
        if (folderName) {
          result.push(folderName);
        }
      });

      return uniqueSortedNames(result);
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

    if (directoryFilesInput) {
      directoryFilesInput.addEventListener('change', function () {
        stagedDirectoryNames = [];
        renderDirectoryImportSummary();
        setDirectoryImportStatus('', 'info');
      });
      renderDirectoryImportSummary();
    }

    if (directoryDropzone) {
      ['dragenter', 'dragover'].forEach(function (eventName) {
        directoryDropzone.addEventListener(eventName, function (event) {
          event.preventDefault();
          directoryDropzone.classList.add('is-dragover');
        });
      });

      ['dragleave', 'dragend', 'drop'].forEach(function (eventName) {
        directoryDropzone.addEventListener(eventName, function (event) {
          event.preventDefault();
          directoryDropzone.classList.remove('is-dragover');
        });
      });

      directoryDropzone.addEventListener('drop', function (event) {
        var items = event.dataTransfer && event.dataTransfer.items ? event.dataTransfer.items : [];
        var names = extractDroppedDirectoryNames(items);
        if (!names.length) {
          setDirectoryImportStatus('Nie udalo sie wykryc nazw folderow z upuszczonych elementow.', 'error');
          return;
        }

        applyDroppedDirectoryNames(names);
      });
    }

    if (importForm && window.fetch && window.FormData) {
      importForm.addEventListener('submit', function (event) {
        var files = directoryFilesInput && directoryFilesInput.files ? Array.prototype.slice.call(directoryFilesInput.files) : [];
        var hasNames = stagedDirectoryNames.length > 0 || (directoryNamesInput && directoryNamesInput.value);
        if (!hasNames && !files.length) {
          return;
        }

        event.preventDefault();
        syncDirectoryNamesInput();
        setDirectoryImportStatus('Trwa import folderow...', 'info');

        if (directoryImportButton) {
          directoryImportButton.disabled = true;
        }
        if (directoryDropzone) {
          directoryDropzone.classList.add('is-busy');
        }

        fetch(importForm.action, {
          method: 'POST',
          body: new FormData(importForm),
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
              throw new Error(result.payload.error || ('Import nie udal sie. Kod: ' + result.status));
            }

            setDirectoryImportStatus(result.payload.message || 'Foldery zostaly zaimportowane.', 'success');
            window.setTimeout(function () {
              window.location.href = result.payload.redirect || window.location.href;
            }, 350);
          })
          .catch(function (error) {
            setDirectoryImportStatus(error && error.message ? error.message : 'Nie udalo sie zaimportowac folderow.', 'error');
          })
          .finally(function () {
            if (directoryImportButton) {
              directoryImportButton.disabled = false;
            }
            if (directoryDropzone) {
              directoryDropzone.classList.remove('is-busy');
            }
          });
      });
    }

    if (searchInput) {
      searchInput.addEventListener('input', filterDirectories);
      filterDirectories();
    }
  })();
</script>
