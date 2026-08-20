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
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=categories&action=index">Kategorie</a></li>
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

      <form method="post" action="{if isset($category.id)}{$baseUrl}?controller=categories&action=update&id={$category.id}{else}{$baseUrl}?controller=categories&action=store{/if}">
        {if isset($category.id)}
          <input type="hidden" name="id" value="{$category.id}">
        {/if}

        <div class="card card-primary card-outline mb-4">
          <div class="card-header">
            <h3 class="card-title">Podstawowe dane</h3>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-5">
                <label for="name" class="form-label">Nazwa kategorii</label>
                <input type="text" class="form-control" id="name" name="name" value="{$category.name|default:''|escape}" required>
              </div>
              <div class="col-md-3">
                <label for="sku_prefix" class="form-label">Przedrostek SKU</label>
                <input type="text" class="form-control" id="sku_prefix" name="sku_prefix" value="{$category.sku_prefix|default:''|escape}" placeholder="np. AGD" required>
                <div class="form-text">Np. AGD-000123.</div>
              </div>
              <div class="col-md-4">
                <label for="end_offers_below_quantity" class="form-label">Zakanczaj aukcje przy sztuk</label>
                <input type="number" min="0" step="1" class="form-control" id="end_offers_below_quantity" name="end_offers_below_quantity" value="{$category.end_offers_below_quantity|default:''|escape}" placeholder="np. 2">
                <div class="form-text">Na razie tylko zapis do kategorii.</div>
              </div>
              <div class="col-md-5">
                <label class="form-label">Slug</label>
                <input type="text" class="form-control" value="Generowany automatycznie na podstawie nazwy" disabled>
              </div>
              <div class="col-12">
                <label for="description" class="form-label">Opis</label>
                <textarea class="form-control" id="description" name="description" rows="4" placeholder="Opcjonalny opis kategorii">{$category.description|default:''|escape}</textarea>
              </div>
            </div>
          </div>
        </div>

        <div class="card card-outline border-primary-subtle mb-4">
          <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="card-title mb-0">Mapowanie Allegro</h3>
            <span class="badge text-bg-primary">Allegro</span>
          </div>
          <div class="card-body">
            <div class="row g-3 align-items-end">
              <div class="col-md-8">
                <label for="allegro_category_search" class="form-label">Wyszukaj kategorie Allegro</label>
                <div class="input-group">
                  <input type="text" class="form-control" id="allegro_category_search" placeholder="np. laptop, koszulka">
                  <button type="button" class="btn btn-outline-primary" id="allegro_category_search_btn">Szukaj</button>
                </div>
              </div>
              <div class="col-md-4">
                <label for="allegro_category_id" class="form-label">Allegro category ID</label>
                <input type="text" class="form-control" id="allegro_category_id" name="allegro_category_id" value="{$category.allegro_category_id|default:''|escape}" placeholder="ID kategorii Allegro">
              </div>
              <div class="col-12">
                <div id="allegro_category_selected" class="small text-secondary"></div>
              </div>
              <div class="col-md-6">
                <div class="border rounded p-2 h-100">
                  <div class="small fw-semibold mb-2">Wyniki</div>
                  <div id="allegro_category_results" class="list-group"></div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="border rounded p-2 h-100">
                  <div class="small fw-semibold mb-2">Drzewko wynikow</div>
                  <div id="allegro_category_tree" class="small"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="card card-outline border-success-subtle mb-4">
          <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="card-title mb-0">Mapowanie Empik</h3>
            <span class="badge text-bg-success">Empik</span>
          </div>
          <div class="card-body">
            <div class="row g-3 align-items-end">
              <div class="col-md-8">
                <label for="empik_category_search" class="form-label">Wyszukaj kategorie Empik</label>
                <div class="input-group">
                  <input type="text" class="form-control" id="empik_category_search" placeholder="np. etui, portfel, kubek">
                  <button type="button" class="btn btn-outline-success" id="empik_category_search_btn">Szukaj Empik</button>
                </div>
                <div class="form-text">Wyszukiwanie jest budowane z drzewa kategorii Mirakl/Empik.</div>
              </div>
              <div class="col-md-4">
                <label for="empik_category_id" class="form-label">Empik category ID</label>
                <input type="text" class="form-control" id="empik_category_id" name="empik_category_id" value="{$category.empik_category_id|default:''|escape}" placeholder="Kod kategorii Empik">
              </div>
              <div class="col-12">
                <div id="empik_category_selected" class="small text-secondary"></div>
              </div>
              <div class="col-md-6">
                <div class="border rounded p-2 border-success-subtle h-100">
                  <div class="small fw-semibold mb-2">Wyniki Empik</div>
                  <div id="empik_category_results" class="list-group"></div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="border rounded p-2 border-success-subtle h-100">
                  <div class="small fw-semibold mb-2">Drzewko Empik</div>
                  <div id="empik_category_tree" class="small"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="card card-outline border-danger-subtle mb-4">
          <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="card-title mb-0">Mapowanie MediaMarkt</h3>
            <span class="badge text-bg-danger">MediaMarkt</span>
          </div>
          <div class="card-body">
            <div class="row g-3 align-items-end">
              <div class="col-md-8">
                <label for="mediamarkt_category_search" class="form-label">Wyszukaj kategorie MediaMarkt</label>
                <div class="input-group">
                  <input type="text" class="form-control" id="mediamarkt_category_search" placeholder="np. etui, portfel, kubek">
                  <button type="button" class="btn btn-outline-danger" id="mediamarkt_category_search_btn">Szukaj MediaMarkt</button>
                </div>
                <div class="form-text">Wyszukiwanie jest budowane z drzewa kategorii Mirakl/MediaMarkt.</div>
              </div>
              <div class="col-md-4">
                <label for="mediamarkt_category_id" class="form-label">MediaMarkt category ID</label>
                <input type="text" class="form-control" id="mediamarkt_category_id" name="mediamarkt_category_id" value="{$category.mediamarkt_category_id|default:''|escape}" placeholder="Kod kategorii MediaMarkt">
              </div>
              <div class="col-12">
                <div id="mediamarkt_category_selected" class="small text-secondary"></div>
              </div>
              <div class="col-md-6">
                <div class="border rounded p-2 border-danger-subtle h-100">
                  <div class="small fw-semibold mb-2">Wyniki MediaMarkt</div>
                  <div id="mediamarkt_category_results" class="list-group"></div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="border rounded p-2 border-danger-subtle h-100">
                  <div class="small fw-semibold mb-2">Drzewko MediaMarkt</div>
                  <div id="mediamarkt_category_tree" class="small"></div>
                </div>
              </div>
            </div>
          </div>
        </div>


        <div class="card card-outline border-warning-subtle mb-4">
          <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="card-title mb-0">Mapowanie Temu</h3>
            <span class="badge text-bg-warning">Temu OpenAPI</span>
          </div>
          <div class="card-body">
            <div class="small text-secondary mb-3">Pierwszy wynik pochodzi bezposrednio z rekomendacji Temu. System przeszukuje rowniez szersze drzewo Seller OpenAPI i pokazuje do 40 kategorii zgodnych z wpisana fraza.</div>
            <div class="row g-3 align-items-end">
              <div class="col-md-8">
                <label for="temu_category_search" class="form-label">Wyszukaj kategorie Temu</label>
                <div class="input-group">
                  <input type="text" class="form-control" id="temu_category_search" placeholder="Nazwa produktu albo pelny link temu.com">
                  <button type="button" class="btn btn-outline-warning" id="temu_category_search_btn">Szukaj Temu</button>
                </div>
                <div class="form-text">Podaj dokladna nazwe produktu albo wklej link kategorii z temu.com. Z linku system pobierze tytul i wyszuka odpowiadajaca mu kategorie Seller OpenAPI.</div>
              </div>
              <div class="col-md-4">
                <label for="temu_category_id" class="form-label">Temu Seller category ID</label>
                <input type="text" class="form-control" id="temu_category_id" name="temu_category_id" value="{$category.temu_category_id|default:''|escape}" placeholder="np. 123456789">
                <div class="form-text text-warning-emphasis">To ID z Seller OpenAPI. Numer z koncowki publicznego adresu temu.com, np. 2642, oznacza inna taksonomie.</div>
              </div>
              <div class="col-12"><div id="temu_category_selected" class="small text-secondary"></div></div>
              <div class="col-md-6">
                <div class="border rounded p-2 border-warning-subtle h-100">
                  <div class="small fw-semibold mb-2">Wyniki Temu</div>
                  <div id="temu_category_results" class="list-group"></div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="border rounded p-2 border-warning-subtle h-100">
                  <div class="small fw-semibold mb-2">Sciezki kategorii</div>
                  <div id="temu_category_tree" class="small"></div>
                </div>
              </div>
              <div class="col-md-4">
                <label for="temu_category_name" class="form-label">Nazwa kategorii Temu</label>
                <input type="text" class="form-control" id="temu_category_name" name="temu_category_name" value="{$category.temu_category_name|default:''|escape}" placeholder="np. Etui i pokrowce">
              </div>
              <div class="col-md-4">
                <label for="temu_category_path" class="form-label">Sciezka kategorii Temu</label>
                <input type="text" class="form-control" id="temu_category_path" name="temu_category_path" value="{$category.temu_category_path|default:''|escape}" placeholder="np. Elektronika > Telefony > Etui">
              </div>
              <div class="col-12">
                <label for="temu_category_parameters" class="form-label">Parametry kategorii Temu (JSON)</label>
                <textarea class="form-control font-monospace" id="temu_category_parameters" name="temu_category_parameters" rows="8">{$category.temu_category_parameters|default:''|escape}</textarea>
                <div class="form-text" id="temu_parameters_status">JSON jest uzupelniany automatycznie po wyborze kategorii; nadal mozna go poprawic recznie.</div>
              </div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-footer d-flex justify-content-between">
            <a href="{$baseUrl}?controller=categories&action=index" class="btn btn-outline-secondary">Wroc do listy</a>
            <button type="submit" class="btn btn-primary">Zapisz kategorie</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</main>

<script>
  (function () {
    function escapeHtml(value) {
      return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function normalizedPath(item) {
      if (item && item.path) {
        return String(item.path);
      }
      return item && item.name ? String(item.name) : '';
    }

    function temuSearchFromValue(value) {
      var raw = String(value || '').trim();
      if (!/^https?:\/\//i.test(raw)) {
        return raw;
      }
      try {
        var parsed = new URL(raw);
        if (!/(^|\.)temu\.com$/i.test(parsed.hostname)) {
          return raw;
        }
        var title = String(parsed.searchParams.get('title') || '').trim();
        if (title) {
          return title;
        }
        var slug = parsed.pathname.split('/').filter(Boolean).pop() || '';
        slug = slug.replace(/\.html$/i, '').replace(/-o\d+-\d+$/i, '').replace(/[-_]+/g, ' ').trim();
        return slug || raw;
      } catch (error) {
        return raw;
      }
    }

    function buildTree(items) {
      var root = { children: {} };

      for (var i = 0; i < items.length; i++) {
        var item = items[i];
        var path = normalizedPath(item);
        if (!path) {
          continue;
        }

        var parts = path.split('>').map(function (part) { return part.trim(); }).filter(Boolean);
        if (!parts.length) {
          continue;
        }

        var node = root;
        for (var p = 0; p < parts.length; p++) {
          var part = parts[p];
          if (!node.children[part]) {
            node.children[part] = { children: {}, ids: [] };
          }
          node = node.children[part];
          if (p === parts.length - 1 && item.id) {
            node.ids.push(String(item.id));
          }
        }
      }

      return root;
    }

    function renderTreeNode(node) {
      var keys = Object.keys(node.children || {});
      if (!keys.length) {
        return '';
      }

      keys.sort();
      var html = '<ul class="mb-1" style="padding-left: 1rem;">';
      for (var i = 0; i < keys.length; i++) {
        var name = keys[i];
        var child = node.children[name];
        var idsText = child.ids && child.ids.length ? ' <span class="text-secondary">(' + escapeHtml(child.ids.join(', ')) + ')</span>' : '';
        html += '<li><span>' + escapeHtml(name) + '</span>' + idsText;
        html += renderTreeNode(child);
        html += '</li>';
      }
      html += '</ul>';

      return html;
    }


    function initMarketplaceSearch(config) {
      var input = document.getElementById(config.inputId);
      var button = document.getElementById(config.buttonId);
      var results = document.getElementById(config.resultsId);
      var tree = document.getElementById(config.treeId);
      var selectedLabel = document.getElementById(config.selectedId);
      var categoryIdInput = document.getElementById(config.categoryInputId);
      var resultById = {};
      var currentController = null;

      if (!input || !button || !results || !tree || !selectedLabel || !categoryIdInput) {
        return;
      }

      function setSelectedText() {
        var id = categoryIdInput.value.trim();
        if (!id) {
          selectedLabel.textContent = 'Brak przypisanej kategorii ' + config.marketName + '.';
          return;
        }

        var item = resultById[id];
        if (item) {
          selectedLabel.textContent = 'Wybrane ' + config.marketName + ' ID: ' + id + ' | Sciezka: ' + normalizedPath(item);
          return;
        }

        selectedLabel.textContent = 'Wybrane ' + config.marketName + ' ID: ' + id;
      }

      function renderResults(items) {
        resultById = {};

        if (!items || !items.length) {
          results.innerHTML = '<div class="list-group-item text-secondary">' + escapeHtml(config.emptyMessage || 'Brak wynikow.') + '</div>';
          tree.innerHTML = '<div class="text-secondary">Brak drzewa do wyswietlenia.</div>';
          return;
        }

        var html = '';
        for (var i = 0; i < items.length; i++) {
          var item = items[i];
          resultById[String(item.id)] = item;
          var path = normalizedPath(item);

          html += '<button type="button" class="list-group-item list-group-item-action" data-id="' + escapeHtml(item.id) + '">'
            + '<strong>' + escapeHtml(path) + '</strong>'
            + (item.recommended ? ' <span class="badge text-bg-warning ms-1">Najlepsza rekomendacja Temu</span>' : '')
            + '<div class="small text-secondary">ID: ' + escapeHtml(item.id) + (item.leaf ? ' | koncowa' : '') + (item.match_kind === 'related' ? ' | kategoria powiazana' : '') + (item.match_kind === 'taxonomy' ? ' | zgodna z fraza' : '') + '</div>'
            + '</button>';
        }
        results.innerHTML = html;

        var treeData = buildTree(items);
        var treeHtml = renderTreeNode(treeData);
        tree.innerHTML = treeHtml || '<div class="text-secondary">Brak drzewa do wyswietlenia.</div>';

        var currentId = categoryIdInput.value.trim();
        if (config.autoApplyMatch && currentId && resultById[currentId] && typeof config.onSelect === 'function') {
          config.onSelect(resultById[currentId]);
        } else if (config.autoSelectFirst && items[0] && typeof config.onSelect === 'function') {
          categoryIdInput.value = String(items[0].id || '');
          setSelectedText();
          config.onSelect(items[0]);
        }
      }

      function doSearch() {
        var rawSearch = input.value.trim();
        var search = typeof config.normalizeSearch === 'function' ? config.normalizeSearch(rawSearch) : rawSearch;
        if (search && search !== rawSearch) {
          input.value = search;
        }
        if (search.length < 2) {
          results.innerHTML = '<div class="list-group-item text-secondary">Wpisz minimum 2 znaki.</div>';
          tree.innerHTML = '<div class="text-secondary">Brak drzewa do wyswietlenia.</div>';
          resultById = {};
          setSelectedText();
          return;
        }

        if (currentController && typeof currentController.abort === 'function') {
          currentController.abort();
        }

        currentController = typeof AbortController !== 'undefined' ? new AbortController() : null;
        button.disabled = true;
        results.innerHTML = '<div class="list-group-item text-secondary">Pobieranie...</div>';
        tree.innerHTML = '<div class="text-secondary">Budowanie drzewa...</div>';

        var url = '{$baseUrl|escape:"javascript"}?controller=' + encodeURIComponent(config.controller) + '&action=categories&search=' + encodeURIComponent(search);
        fetch(url, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          signal: currentController ? currentController.signal : undefined
        })
          .then(function (response) {
            return response.text().then(function (raw) {
              var data = null;
              try {
                data = raw ? JSON.parse(raw) : null;
              } catch (parseError) {
                var message = 'Serwer zwrocil nieoczekiwana odpowiedz (HTTP ' + response.status + ').';
                if (raw) {
                  message += ' ' + raw.replace(/<[^>]*>/g, ' ').trim().slice(0, 200);
                }
                throw new Error(message);
              }

              if (!response.ok && (!data || !data.error)) {
                throw new Error('Serwer zwrocil blad HTTP ' + response.status + '.');
              }

              return data || {};
            });
          })
          .then(function (data) {
            if (data && data.error) {
              results.innerHTML = '<div class="list-group-item text-danger">' + escapeHtml(data.error) + '</div>';
              tree.innerHTML = '<div class="text-danger">Nie udalo sie pobrac drzewa.</div>';
              return;
            }
            renderResults(data.items || []);
            setSelectedText();
          })
          .catch(function (error) {
            if (error && error.name === 'AbortError') {
              return;
            }
            var detail = error && error.message ? error.message : ('Blad pobierania danych z ' + config.marketName + '.');
            results.innerHTML = '<div class="list-group-item text-danger">' + escapeHtml(detail) + '</div>';
            tree.innerHTML = '<div class="text-danger">Blad budowania drzewa.</div>';
          })
          .finally(function () {
            button.disabled = false;
          });
      }

      button.addEventListener('click', doSearch);
      input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          doSearch();
        }
      });

      results.addEventListener('click', function (event) {
        var target = event.target;
        while (target && target !== results && !target.getAttribute('data-id')) {
          target = target.parentNode;
        }

        if (!target || target === results) {
          return;
        }

        var id = target.getAttribute('data-id') || '';
        categoryIdInput.value = id;
        setSelectedText();
        if (typeof config.onSelect === 'function') {
          config.onSelect(resultById[id] || { id: id, name: '', path: '' });
        }
      });

      categoryIdInput.addEventListener('input', function () {
        setSelectedText();
        if (typeof config.onIdChange === 'function') {
          config.onIdChange(categoryIdInput.value);
        }
      });
      setSelectedText();
    }

    initMarketplaceSearch({
      controller: 'allegro',
      marketName: 'Allegro',
      inputId: 'allegro_category_search',
      buttonId: 'allegro_category_search_btn',
      resultsId: 'allegro_category_results',
      treeId: 'allegro_category_tree',
      selectedId: 'allegro_category_selected',
      categoryInputId: 'allegro_category_id'
    });

    initMarketplaceSearch({
      controller: 'empik',
      marketName: 'Empik',
      inputId: 'empik_category_search',
      buttonId: 'empik_category_search_btn',
      resultsId: 'empik_category_results',
      treeId: 'empik_category_tree',
      selectedId: 'empik_category_selected',
      categoryInputId: 'empik_category_id'
    });

    initMarketplaceSearch({
      controller: 'mediamarkt',
      marketName: 'MediaMarkt',
      inputId: 'mediamarkt_category_search',
      buttonId: 'mediamarkt_category_search_btn',
      resultsId: 'mediamarkt_category_results',
      treeId: 'mediamarkt_category_tree',
      selectedId: 'mediamarkt_category_selected',
      categoryInputId: 'mediamarkt_category_id'
    });

    var temuPathInput = document.getElementById('temu_category_path');
    var temuNameInput = document.getElementById('temu_category_name');
    var temuParametersInput = document.getElementById('temu_category_parameters');
    var temuParametersStatus = document.getElementById('temu_parameters_status');
    var temuSelectedLabel = document.getElementById('temu_category_selected');
    var temuIdLookupTimer = null;
    var temuIdLookupController = null;

    function loadTemuParameters(item) {
      var categoryId = item && item.id ? String(item.id) : '';
      if (!categoryId || !temuParametersInput) return;
      if (temuNameInput) temuNameInput.value = item.name || '';
      if (temuPathInput) temuPathInput.value = item.path || item.name || '';
      if (temuParametersStatus) {
        temuParametersStatus.className = 'form-text text-secondary';
        temuParametersStatus.textContent = 'Pobieranie parametrow kategorii Temu...';
      }
      fetch('{$baseUrl|escape:"javascript"}?controller=temu&action=parameters&id=' + encodeURIComponent(categoryId), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (response) {
          return response.text().then(function (raw) {
            var data;
            try {
              data = raw ? JSON.parse(raw) : {};
            } catch (parseError) {
              throw new Error(raw ? raw.replace(/<[^>]*>/g, ' ').trim().slice(0, 300) : ('Blad HTTP ' + response.status + '.'));
            }
            if (!response.ok && !data.error) throw new Error('Blad HTTP ' + response.status + '.');
            return data;
          });
        })
        .then(function (data) {
          if (data.error) throw new Error(data.error);
          var items = data.items || [];
          temuParametersInput.value = JSON.stringify(items, null, 2);
          if (temuParametersStatus) {
            temuParametersStatus.className = 'form-text text-success';
            temuParametersStatus.textContent = 'Pobrano ' + items.length + ' parametrow z Temu.';
          }
        })
        .catch(function (error) {
          if (temuParametersStatus) {
            temuParametersStatus.className = 'form-text text-danger';
            temuParametersStatus.textContent = error.message || 'Nie udalo sie pobrac parametrow Temu.';
          }
        });
    }

    function recognizeTemuCategoryId(rawId) {
      var categoryId = String(rawId || '').trim();
      if (temuIdLookupTimer) {
        window.clearTimeout(temuIdLookupTimer);
      }
      if (temuIdLookupController && typeof temuIdLookupController.abort === 'function') {
        temuIdLookupController.abort();
      }
      if (!categoryId) {
        return;
      }
      if (/^https?:\/\//i.test(categoryId)) {
        var searchFromUrl = temuSearchFromValue(categoryId);
        var temuSearchInput = document.getElementById('temu_category_search');
        var temuSearchButton = document.getElementById('temu_category_search_btn');
        var temuIdInput = document.getElementById('temu_category_id');
        if (temuSearchInput && searchFromUrl && searchFromUrl !== categoryId) {
          temuSearchInput.value = searchFromUrl;
          if (temuIdInput) temuIdInput.value = '';
          if (temuNameInput) temuNameInput.value = '';
          if (temuPathInput) temuPathInput.value = '';
          if (temuParametersInput) temuParametersInput.value = '';
          if (temuSelectedLabel) temuSelectedLabel.textContent = 'Wybierz kategorie Seller OpenAPI z wynikow ponizej.';
          if (temuParametersStatus) {
            temuParametersStatus.className = 'form-text text-secondary';
            temuParametersStatus.textContent = 'Rozpoznano z linku: ' + searchFromUrl + '. Wyszukiwanie kategorii Seller OpenAPI...';
          }
          if (temuSearchButton) temuSearchButton.click();
          return;
        }
      }
      if (!/^\d+$/.test(categoryId)) {
        if (temuParametersStatus) {
          temuParametersStatus.className = 'form-text text-danger';
          temuParametersStatus.textContent = 'ID kategorii Temu musi zawierac same cyfry.';
        }
        return;
      }
      if (temuNameInput) temuNameInput.value = '';
      if (temuPathInput) temuPathInput.value = '';
      if (temuParametersInput) temuParametersInput.value = '';

      temuIdLookupTimer = window.setTimeout(function () {
        temuIdLookupController = typeof AbortController !== 'undefined' ? new AbortController() : null;
        if (temuParametersStatus) {
          temuParametersStatus.className = 'form-text text-secondary';
          temuParametersStatus.textContent = 'Rozpoznawanie kategorii Temu ID ' + categoryId + '...';
        }
        fetch('{$baseUrl|escape:"javascript"}?controller=temu&action=category&id=' + encodeURIComponent(categoryId), {
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          signal: temuIdLookupController ? temuIdLookupController.signal : undefined
        })
          .then(function (response) {
            return response.text().then(function (raw) {
              var data;
              try {
                data = raw ? JSON.parse(raw) : {};
              } catch (parseError) {
                throw new Error(raw ? raw.replace(/<[^>]*>/g, ' ').trim().slice(0, 300) : ('Blad HTTP ' + response.status + '.'));
              }
              if (!response.ok && !data.error) throw new Error('Blad HTTP ' + response.status + '.');
              return data;
            });
          })
          .then(function (data) {
            if (data.error) throw new Error(data.error);
            var item = data.item || null;
            if (!item || String(item.id || '') !== categoryId) {
              throw new Error('Temu nie zwrocilo danych tej kategorii.');
            }
            if (temuSelectedLabel) {
              temuSelectedLabel.textContent = 'Wybrane Temu ID: ' + categoryId + ' | Sciezka: ' + normalizedPath(item);
            }
            loadTemuParameters(item);
          })
          .catch(function (error) {
            if (error && error.name === 'AbortError') return;
            if (temuParametersStatus) {
              temuParametersStatus.className = 'form-text text-danger';
              temuParametersStatus.textContent = error.message || 'Nie udalo sie rozpoznac kategorii Temu.';
            }
          });
      }, 600);
    }

    initMarketplaceSearch({
      controller: 'temu',
      marketName: 'Temu',
      inputId: 'temu_category_search',
      buttonId: 'temu_category_search_btn',
      resultsId: 'temu_category_results',
      treeId: 'temu_category_tree',
      selectedId: 'temu_category_selected',
      categoryInputId: 'temu_category_id',
      emptyMessage: 'Temu nie zwrocilo rekomendacji. Sprobuj pelnej nazwy produktu, najlepiej po angielsku.',
      autoApplyMatch: true,
      autoSelectFirst: true,
      onSelect: loadTemuParameters,
      onIdChange: recognizeTemuCategoryId,
      normalizeSearch: temuSearchFromValue
    });

    if (temuPathInput && temuNameInput) {
      temuPathInput.addEventListener('blur', function () {
        var path = String(temuPathInput.value || '').trim();
        var currentName = String(temuNameInput.value || '').trim();
        if (!path || currentName) {
          return;
        }

        var parts = path.split('>').map(function (part) { return part.trim(); }).filter(Boolean);
        if (parts.length) {
          temuNameInput.value = parts[parts.length - 1];
        }
      });
    }
  })();
</script>
