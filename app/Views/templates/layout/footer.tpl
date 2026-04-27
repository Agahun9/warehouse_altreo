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
      var loader = document.getElementById('appPageLoader');
      var loaderText = document.getElementById('appPageLoaderText');
      var body = document.body;
      var hideTimer = null;
      var showTimer = null;
      var loaderVisible = false;
      var loaderDelayMs = 700;
      var loaderEnabled = {if !$currentUser || $currentUser.loader_enabled|default:1}true{else}false{/if};

      function setReadyState() {
        body.classList.add('app-ready');
      }

      function showLoader(message) {
        if (!loader || !loaderEnabled) {
          return;
        }

        if (hideTimer) {
          window.clearTimeout(hideTimer);
          hideTimer = null;
        }

        if (showTimer) {
          window.clearTimeout(showTimer);
          showTimer = null;
        }

        if (loaderText) {
          loaderText.textContent = message || 'Trwa pobieranie danych i odswiezanie ekranu.';
        }

        showTimer = window.setTimeout(function () {
          loader.classList.add('is-active');
          loader.setAttribute('aria-hidden', 'false');
          body.classList.add('page-is-loading');
          loaderVisible = true;
        }, loaderDelayMs);
      }

      function hideLoader() {
        if (showTimer) {
          window.clearTimeout(showTimer);
          showTimer = null;
        }

        if (!loader) {
          setReadyState();
          return;
        }

        if (loaderVisible) {
          loader.classList.remove('is-active');
          loader.setAttribute('aria-hidden', 'true');
          body.classList.remove('page-is-loading');
          loaderVisible = false;
        }

        hideTimer = window.setTimeout(function () {
          setReadyState();
        }, 40);
      }

      function shouldHandleLink(link, event) {
        if (!link || event.defaultPrevented) {
          return false;
        }

        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
          return false;
        }

        if (link.hasAttribute('download')) {
          return false;
        }

        if ((link.getAttribute('target') || '').toLowerCase() === '_blank') {
          return false;
        }

        if (link.getAttribute('data-no-page-loader') === '1') {
          return false;
        }

        var href = String(link.getAttribute('href') || '').trim();
        if (href === '' || href === '#' || href.indexOf('javascript:') === 0) {
          return false;
        }

        try {
          var url = new URL(link.href, window.location.href);
          if (url.origin !== window.location.origin) {
            return false;
          }

          if (url.href === window.location.href) {
            return false;
          }
        } catch (error) {
          return false;
        }

        return true;
      }

      window.showPageLoader = showLoader;
      window.hidePageLoader = hideLoader;

      window.addEventListener('load', hideLoader);
      window.addEventListener('pageshow', hideLoader);

      document.addEventListener('click', function (event) {
        var link = event.target.closest('a');
        if (!shouldHandleLink(link, event)) {
          return;
        }

        showLoader(link.getAttribute('data-loader-label') || 'Ladowanie strony...');
      });

      document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || event.defaultPrevented) {
          return;
        }

        if (form.getAttribute('data-no-page-loader') === '1') {
          return;
        }

        var target = (form.getAttribute('target') || '').toLowerCase();
        if (target !== '' && target !== '_self') {
          return;
        }

        showLoader(form.getAttribute('data-loader-label') || 'Ladowanie danych...');
      });
    })();

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
      var productsIndexUrl = '{$baseUrl|escape:'javascript'}?controller=products&action=index';
      var productsEditBaseUrl = '{$baseUrl|escape:'javascript'}?controller=products&action=edit&id=';

      function escapeHtml(value) {
        return String(value || '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
      }

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
          var id = String(item.id || '');
          var sku = String(item.sku || '');
          var name = String(item.product_name || '');
          var oldSku = String(item.old_sku || '');
          var quantity = String(item.quantity != null ? item.quantity : '-');
          var localization = String(item.localization || '-');
          var filterUrl = productsIndexUrl + '&filter_global=' + encodeURIComponent(query || sku);
          var editUrl = productsEditBaseUrl + encodeURIComponent(id);

          html += '<div class="quick-search-item">';
          html += '<a class="quick-search-item-main" href="' + filterUrl + '">';
          html += '<div class="quick-search-topline">';
          html += '<span class="quick-search-sku">' + escapeHtml(sku || 'BRAK SKU') + '</span>';
          if (oldSku) {
            html += '<span class="quick-search-old-sku">OLD_SKU: ' + escapeHtml(oldSku) + '</span>';
          }
          html += '</div>';
          html += '<div class="quick-search-name">' + escapeHtml(name) + '</div>';
          html += '<div class="quick-search-meta">';
          html += '<span class="quick-search-meta-chip"><i class="bi bi-box-seam"></i> szt.: ' + escapeHtml(quantity) + '</span>';
          html += '<span class="quick-search-meta-chip"><i class="bi bi-geo-alt"></i> ' + escapeHtml(localization) + '</span>';
          html += '</div>';
          html += '</a>';
          html += '<div class="quick-search-actions">';
          if (id) {
            html += '<a class="btn btn-sm btn-primary" href="' + editUrl + '">Edytuj</a>';
          }
          html += '</div>';
          html += '</div>';
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
          results.innerHTML = '<div class="px-3 py-2 text-secondary small">Szukanie produktow...</div>';
          results.style.display = 'block';

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
