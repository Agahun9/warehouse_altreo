<?php
/* Smarty version 5.8.0, created on 2026-04-23 09:20:36
  from 'file:layout/footer.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.8.0',
  'unifunc' => 'content_69e9c8442f13e9_29997749',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '502315d5d78695b6467a1f900dd9a75a1e10fc71' => 
    array (
      0 => 'layout/footer.tpl',
      1 => 1776928833,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69e9c8442f13e9_29997749 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/pfuuseajvz/domains/magazyn.altreo.pl/public_html/crm/new_version/app/Views/templates/layout';
?>    <footer class="app-footer">
      <div class="float-end d-none d-sm-inline">Created by Agahun</div>
      <strong>
        Copyright &copy; <?php echo $_smarty_tpl->getValue('currentYear');?>

        <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=index" class="text-decoration-none"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('appName'), ENT_QUOTES, 'UTF-8', true);?>
</a>.
      </strong>
      Wszelkie prawa zastrzezone.
    </footer>
  </div>

  <?php echo '<script'; ?>

    src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
    crossorigin="anonymous"
  ><?php echo '</script'; ?>
>
  <?php echo '<script'; ?>

    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"
    crossorigin="anonymous"
  ><?php echo '</script'; ?>
>
  <?php echo '<script'; ?>
>
    (function () {
      var input = document.getElementById('globalProductSearchInput');
      var results = document.getElementById('globalProductSearchResults');
      var form = document.getElementById('globalProductSearchForm');

      if (!input || !results || !form) {
        return;
      }

      var timer = null;
      var activeQuery = '';
      var endpoint = '<?php echo strtr((string)$_smarty_tpl->getValue('baseUrl'), array("\\" => "\\\\", "'" => "\\'", "\"" => "\\\"", "\r" => "\\r", 
						"\n" => "\\n", "</" => "<\/", "<!--" => "<\!--", "<s" => "<\s", "<S" => "<\S",
						"`" => "\\`", "\${" => "\\\$\{"));?>
?controller=products&action=quicksearch';

      function hideResults() {
        results.style.display = 'none';
        results.innerHTML = '';
      }

      function showResults(items, query) {
        if (!items || items.length === 0) {
          results.innerHTML = '<div class="px-3 py-2 text-secondary small">Brak wynikow</div>';
          results.style.display = 'block';
          return;
        }

        var html = '';
        for (var i = 0; i < items.length; i++) {
          var item = items[i];
          var sku = String(item.sku || '');
          var name = String(item.product_name || '');
          var oldSku = String(item.old_sku || '');
          html += '<a class="quick-search-item" href="<?php echo strtr((string)$_smarty_tpl->getValue('baseUrl'), array("\\" => "\\\\", "'" => "\\'", "\"" => "\\\"", "\r" => "\\r", 
						"\n" => "\\n", "</" => "<\/", "<!--" => "<\!--", "<s" => "<\s", "<S" => "<\S",
						"`" => "\\`", "\${" => "\\\$\{"));?>
?controller=products&action=index&filter_global=' + encodeURIComponent(query || sku) + '">';
          html += '<div class="fw-semibold">' + sku.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</div>';
          html += '<div class="small text-secondary">' + name.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</div>';
          if (oldSku) {
            html += '<div class="small text-secondary">OLD_SKU: ' + oldSku.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</div>';
          }
          html += '</a>';
        }

        results.innerHTML = html;
        results.style.display = 'block';
      }

      input.addEventListener('input', function () {
        var query = input.value.trim();

        if (timer) {
          clearTimeout(timer);
        }

        if (query.length < 2) {
          hideResults();
          return;
        }

        timer = setTimeout(function () {
          activeQuery = query;

          fetch(endpoint + '&q=' + encodeURIComponent(query), {
            headers: { 'Accept': 'application/json' }
          })
            .then(function (response) { return response.ok ? response.json() : { items: [] }; })
            .then(function (payload) {
              if (input.value.trim() !== activeQuery) {
                return;
              }

              showResults(payload.items || [], activeQuery);
            })
            .catch(function () {
              if (input.value.trim() === activeQuery) {
                hideResults();
              }
            });
        }, 180);
      });

      document.addEventListener('click', function (event) {
        if (!results.contains(event.target) && event.target !== input) {
          hideResults();
        }
      });

      form.addEventListener('submit', function () {
        hideResults();
      });
    })();
  <?php echo '</script'; ?>
>
  <?php echo '<script'; ?>
 src="<?php echo $_smarty_tpl->getValue('assetBase');?>
/js/adminlte.js"><?php echo '</script'; ?>
>
</body>
</html>
<?php }
}
