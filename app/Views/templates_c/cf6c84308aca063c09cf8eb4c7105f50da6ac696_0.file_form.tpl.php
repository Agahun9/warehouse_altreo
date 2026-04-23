<?php
/* Smarty version 5.8.0, created on 2026-04-17 10:19:12
  from 'file:categories/form.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.8.0',
  'unifunc' => 'content_69e1ed00330a55_20811997',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'cf6c84308aca063c09cf8eb4c7105f50da6ac696' => 
    array (
      0 => 'categories/form.tpl',
      1 => 1774525780,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69e1ed00330a55_20811997 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/pfuuseajvz/domains/magazyn.altreo.pl/public_html/crm/new_version/app/Views/templates/categories';
?><main class="app-main">
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-6">
          <h3 class="mb-0"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('contentTitle'), ENT_QUOTES, 'UTF-8', true);?>
</h3>
          <p class="text-secondary mb-0"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('pageDescription'), ENT_QUOTES, 'UTF-8', true);?>
</p>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-end">
            <li class="breadcrumb-item"><a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=index">Start</a></li>
            <li class="breadcrumb-item"><a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=categories&action=index">Kategorie</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('breadcrumbCurrent'), ENT_QUOTES, 'UTF-8', true);?>
</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      <?php if ($_smarty_tpl->getValue('flashSuccess')) {?>
        <div class="alert alert-success"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('flashSuccess'), ENT_QUOTES, 'UTF-8', true);?>
</div>
      <?php }?>
      <?php if ($_smarty_tpl->getValue('flashError')) {?>
        <div class="alert alert-danger"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('flashError'), ENT_QUOTES, 'UTF-8', true);?>
</div>
      <?php }?>

      <div class="card card-primary card-outline">
        <div class="card-header">
          <h3 class="card-title">Formularz kategorii</h3>
        </div>
        <form method="post" action="<?php if ((true && (true && null !== ($_smarty_tpl->getValue('category')['id'] ?? null)))) {
echo $_smarty_tpl->getValue('baseUrl');?>
?controller=categories&action=update&id=<?php echo $_smarty_tpl->getValue('category')['id'];
} else {
echo $_smarty_tpl->getValue('baseUrl');?>
?controller=categories&action=store<?php }?>">
          <?php if ((true && (true && null !== ($_smarty_tpl->getValue('category')['id'] ?? null)))) {?>
            <input type="hidden" name="id" value="<?php echo $_smarty_tpl->getValue('category')['id'];?>
">
          <?php }?>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label for="name" class="form-label">Nazwa kategorii</label>
                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('category')['name'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
" required>
              </div>
              <div class="col-md-6">
                <label for="sku_prefix" class="form-label">Przedrostek SKU</label>
                <input type="text" class="form-control" id="sku_prefix" name="sku_prefix" value="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('category')['sku_prefix'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
" placeholder="np. AGD" required>
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
                <input type="text" class="form-control" id="allegro_category_id" name="allegro_category_id" value="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('category')['allegro_category_id'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
" placeholder="ID kategorii Allegro">
                <div class="form-text">Mozesz wpisac ID recznie lub wybrac z listy ponizej.</div>
              </div>
              <div class="col-12">
                <div id="allegro_category_selected" class="small text-secondary mb-2"></div>
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
                <label class="form-label">Slug</label>
                <input type="text" class="form-control" value="Generowany automatycznie na podstawie nazwy" disabled>
              </div>
              <div class="col-12">
                <label for="description" class="form-label">Opis</label>
                <textarea class="form-control" id="description" name="description" rows="5" placeholder="Opcjonalny opis kategorii"><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('category')['description'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</textarea>
              </div>
            </div>
          </div>
          <div class="card-footer d-flex justify-content-between">
            <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=categories&action=index" class="btn btn-outline-secondary">Wroc do listy</a>
            <button type="submit" class="btn btn-primary">Zapisz kategorie</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</main>

<?php echo '<script'; ?>
>
  (function () {
    var input = document.getElementById('allegro_category_search');
    var button = document.getElementById('allegro_category_search_btn');
    var results = document.getElementById('allegro_category_results');
    var tree = document.getElementById('allegro_category_tree');
    var selectedLabel = document.getElementById('allegro_category_selected');
    var categoryIdInput = document.getElementById('allegro_category_id');
    var resultById = {};

    if (!input || !button || !results || !tree || !selectedLabel || !categoryIdInput) {
      return;
    }

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

    function setSelectedText() {
      var id = categoryIdInput.value.trim();
      if (!id) {
        selectedLabel.textContent = 'Brak przypisanej kategorii Allegro.';
        return;
      }

      var item = resultById[id];
      if (item) {
        selectedLabel.textContent = 'Wybrane Allegro ID: ' + id + ' | Sciezka: ' + normalizedPath(item);
        return;
      }

      selectedLabel.textContent = 'Wybrane Allegro ID: ' + id;
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
      if (!search) {
        results.innerHTML = '';
        tree.innerHTML = '';
        resultById = {};
        setSelectedText();
        return;
      }

      results.innerHTML = '<div class="list-group-item text-secondary">Pobieranie...</div>';
      tree.innerHTML = '<div class="text-secondary">Budowanie drzewa...</div>';

      var url = '<?php echo strtr((string)$_smarty_tpl->getValue('baseUrl'), array("\\" => "\\\\", "'" => "\\'", "\"" => "\\\"", "\r" => "\\r", 
						"\n" => "\\n", "</" => "<\/", "<!--" => "<\!--", "<s" => "<\s", "<S" => "<\S",
						"`" => "\\`", "\${" => "\\\$\{"));?>
?controller=allegro&action=categories&force=1&search=' + encodeURIComponent(search);
      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
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
        .catch(function () {
          results.innerHTML = '<div class="list-group-item text-danger">Blad pobierania danych z Allegro.</div>';
          tree.innerHTML = '<div class="text-danger">Blad budowania drzewa.</div>';
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
  })();
<?php echo '</script'; ?>
>
<?php }
}
