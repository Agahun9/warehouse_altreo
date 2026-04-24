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

      <div class="card card-primary card-outline">
        <div class="card-header">
          <h3 class="card-title">Formularz kategorii</h3>
        </div>
        <form method="post" action="{if isset($category.id)}{$baseUrl}?controller=categories&action=update&id={$category.id}{else}{$baseUrl}?controller=categories&action=store{/if}">
          {if isset($category.id)}
            <input type="hidden" name="id" value="{$category.id}">
          {/if}
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label for="name" class="form-label">Nazwa kategorii</label>
                <input type="text" class="form-control" id="name" name="name" value="{$category.name|default:''|escape}" required>
              </div>
              <div class="col-md-6">
                <label for="sku_prefix" class="form-label">Przedrostek SKU</label>
                <input type="text" class="form-control" id="sku_prefix" name="sku_prefix" value="{$category.sku_prefix|default:''|escape}" placeholder="np. AGD" required>
                <div class="form-text">Uzywany do automatycznego nadawania SKU, np. AGD-000123.</div>
              </div>
              <div class="col-md-6">
                <label for="allegro_category_search" class="form-label">Wyszukaj kategorie Allegro</label>
                <div class="input-group">
                  <input type="text" class="form-control" id="allegro_category_search" placeholder="np. laptop, koszulka">
                  <button type="button" class="btn btn-outline-primary" id="allegro_category_search_btn">Szukaj</button>
                </div>
              </div>
              <div class="col-md-6">
                <label for="allegro_category_id" class="form-label">Allegro category ID</label>
                <input type="text" class="form-control" id="allegro_category_id" name="allegro_category_id" value="{$category.allegro_category_id|default:''|escape}" placeholder="ID kategorii Allegro">
                <div class="form-text">Mozesz wpisac ID recznie lub wybrac z listy ponizej.</div>
              </div>
              <div class="col-md-6">
                <label for="empik_category_search" class="form-label">Wyszukaj kategorie Empik</label>
                <div class="input-group">
                  <input type="text" class="form-control" id="empik_category_search" placeholder="np. etui, portfel, kubek">
                  <button type="button" class="btn btn-outline-success" id="empik_category_search_btn">Szukaj Empik</button>
                </div>
              </div>
              <div class="col-md-6">
                <label for="empik_category_id" class="form-label">Empik category ID</label>
                <input type="text" class="form-control" id="empik_category_id" name="empik_category_id" value="{$category.empik_category_id|default:''|escape}" placeholder="Kod kategorii Empik">
                <div class="form-text">Wyszukiwanie jest budowane z drzewa kategorii Mirakl/Empik.</div>
              </div>
              <div class="col-12">
                <div id="allegro_category_selected" class="small text-secondary mb-2"></div>
              </div>
              <div class="col-12">
                <div id="empik_category_selected" class="small text-secondary mb-2"></div>
              </div>
              <div class="col-md-6">
                <div class="border rounded p-2">
                  <div class="small fw-semibold mb-2">Wyniki</div>
                  <div id="allegro_category_results" class="list-group"></div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="border rounded p-2">
                  <div class="small fw-semibold mb-2">Drzewko wynikow</div>
                  <div id="allegro_category_tree" class="small"></div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="border rounded p-2 border-success-subtle">
                  <div class="small fw-semibold mb-2">Wyniki Empik</div>
                  <div id="empik_category_results" class="list-group"></div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="border rounded p-2 border-success-subtle">
                  <div class="small fw-semibold mb-2">Drzewko Empik</div>
                  <div id="empik_category_tree" class="small"></div>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Slug</label>
                <input type="text" class="form-control" value="Generowany automatycznie na podstawie nazwy" disabled>
              </div>
              <div class="col-12">
                <label for="description" class="form-label">Opis</label>
                <textarea class="form-control" id="description" name="description" rows="5" placeholder="Opcjonalny opis kategorii">{$category.description|default:''|escape}</textarea>
              </div>
            </div>
          </div>
          <div class="card-footer d-flex justify-content-between">
            <a href="{$baseUrl}?controller=categories&action=index" class="btn btn-outline-secondary">Wroc do listy</a>
            <button type="submit" class="btn btn-primary">Zapisz kategorie</button>
          </div>
        </form>
      </div>
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
          results.innerHTML = '<div class="list-group-item text-secondary">Brak wynikow.</div>';
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
            + '<div class="small text-secondary">ID: ' + escapeHtml(item.id) + (item.leaf ? ' | koncowa' : '') + '</div>'
            + '</button>';
        }
        results.innerHTML = html;

        var treeData = buildTree(items);
        var treeHtml = renderTreeNode(treeData);
        tree.innerHTML = treeHtml || '<div class="text-secondary">Brak drzewa do wyswietlenia.</div>';
      }

      function doSearch() {
        var search = input.value.trim();
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
          .then(function (response) { return response.json(); })
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
            results.innerHTML = '<div class="list-group-item text-danger">Blad pobierania danych z ' + escapeHtml(config.marketName) + '.</div>';
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
      });

      categoryIdInput.addEventListener('input', setSelectedText);
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
  })();
</script>
