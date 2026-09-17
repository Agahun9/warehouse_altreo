{if $currentUser}
    <footer class="app-footer">
      <strong>&copy; {$currentYear} {$appName|escape}</strong> · {$currentUser.tenant.name|escape}
    </footer>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
  <script>
    (function () {
      var loader = document.getElementById('appPageLoader');
      var loaderText = document.getElementById('appPageLoaderText');
      var closeBtn = document.getElementById('appPageLoaderCloseBtn');
      var body = document.body;
      var hideTimer = null;
      var showTimer = null;
      var stuckTimer = null;
      var loaderVisible = false;
      var loaderDelayMs = 100;
      var loaderStuckDelayMs = 5000;
      var loaderEnabled = true;

      function clearStuckTimer() {
        if (stuckTimer) {
          window.clearTimeout(stuckTimer);
          stuckTimer = null;
        }
        if (closeBtn) {
          closeBtn.classList.remove('is-visible');
        }
      }

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

          clearStuckTimer();
          stuckTimer = window.setTimeout(function () {
            if (closeBtn) {
              closeBtn.classList.add('is-visible');
            }
          }, loaderStuckDelayMs);
        }, loaderDelayMs);
      }

      function hideLoader() {
        if (showTimer) {
          window.clearTimeout(showTimer);
          showTimer = null;
        }

        clearStuckTimer();

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

      if (closeBtn) {
        closeBtn.addEventListener('click', function () {
          hideLoader();
        });
      }

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

  </script>
  <script src="{$assetBase}/js/adminlte.js"></script>
  <script>
    // Zapamiętuje zwinięcie lewego panelu (klasę przełącza AdminLTE po kliknięciu ☰).
    (function () {
      document.querySelectorAll('[data-lte-toggle="sidebar"]').forEach(function (button) {
        button.addEventListener('click', function () {
          window.setTimeout(function () {
            try { localStorage.setItem('sc-sidebar', document.body.classList.contains('sidebar-collapse') ? 'collapsed' : 'expanded'); } catch (e) {}
          }, 50);
        });
      });
    })();
  </script>
{else}
  </div>
{/if}
</body>
</html>
