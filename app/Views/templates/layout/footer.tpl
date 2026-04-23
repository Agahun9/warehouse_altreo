    <footer class="app-footer">
      <div class="float-end d-none d-sm-inline">Created by Agahun</div>
      <strong>
        Copyright &copy; {$currentYear}
        <a href="{$baseUrl}?controller=index" class="text-decoration-none">{$appName|escape}</a>.
      </strong>
      Wszelkie prawa zastrzezone.
    </footer>
  </div>

  <script
    src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
    crossorigin="anonymous"
  ></script>
  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"
    crossorigin="anonymous"
  ></script>
  <script>
    (function () {
      var input = document.getElementById('globalProductSearchInput');
      var results = document.getElementById('globalProductSearchResults');
      var form = document.getElementById('globalProductSearchForm');

      if (!input || !results || !form) {
        return;
      }

      var timer = null;
      var activeQuery = '';
      var endpoint = '{$baseUrl|escape:'javascript'}?controller=products&action=quicksearch';

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
          html += '<a class="quick-search-item" href="{$baseUrl|escape:'javascript'}?controller=products&action=index&filter_global=' + encodeURIComponent(query || sku) + '">';
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
  </script>
  <script src="{$assetBase}/js/adminlte.js"></script>
</body>
</html>
