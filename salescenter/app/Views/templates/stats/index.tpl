<link rel="stylesheet" href="dist/css/stats.css?v=20261012-2">
<main class="app-main st" data-st-root data-endpoint="{$baseUrl}?controller=stats&action=data" data-tenant="{$statsTenantId}">
<div class="st-shell">
  <header class="st-hero">
    <div class="st-hero-text">
      <h1>Statystyki sprzedaży</h1>
      <p data-st-period>Wczytywanie danych…</p>
    </div>
    <div class="st-toolbar" aria-label="Filtry statystyk">
      <div class="st-menu" data-st-menu="range">
        <button type="button" class="st-menu-btn" aria-haspopup="true" aria-expanded="false"><i class="bi bi-calendar3"></i><span data-st-menu-label>Ostatnie 30 dni</span><i class="bi bi-chevron-down"></i></button>
        <div class="st-menu-panel st-range-panel" hidden>
          <div class="st-range-list" data-st-presets>
            <button type="button" data-value="today">Dziś<i class="bi bi-check2"></i></button>
            <button type="button" data-value="yesterday">Wczoraj<i class="bi bi-check2"></i></button>
            <button type="button" data-value="7d">Ostatnie 7 dni<i class="bi bi-check2"></i></button>
            <button type="button" data-value="30d">Ostatnie 30 dni<i class="bi bi-check2"></i></button>
            <button type="button" data-value="90d">Ostatnie 90 dni<i class="bi bi-check2"></i></button>
            <button type="button" data-value="month">Ten miesiąc<i class="bi bi-check2"></i></button>
            <button type="button" data-value="prev_month">Poprzedni miesiąc<i class="bi bi-check2"></i></button>
            <button type="button" data-value="year">Ten rok<i class="bi bi-check2"></i></button>
          </div>
          <div class="st-range-custom">
            <span>Własny zakres</span>
            <div class="st-dates">
              <input type="date" data-st-from aria-label="Data od">
              <span aria-hidden="true">–</span>
              <input type="date" data-st-to aria-label="Data do">
            </div>
          </div>
        </div>
      </div>
      <div class="st-menu" data-st-menu="accounts">
        <button type="button" class="st-menu-btn" aria-haspopup="true" aria-expanded="false"><i class="bi bi-shop"></i><span data-st-menu-label>Wszystkie konta</span><i class="bi bi-chevron-down"></i></button>
        <div class="st-menu-panel" hidden></div>
      </div>
      <div class="st-menu" data-st-menu="statuses">
        <button type="button" class="st-menu-btn" aria-haspopup="true" aria-expanded="false"><i class="bi bi-funnel"></i><span data-st-menu-label>Statusy sprzedaży</span><i class="bi bi-chevron-down"></i></button>
        <div class="st-menu-panel" hidden></div>
      </div>
      <div class="st-menu" data-st-menu="more">
        <button type="button" class="st-menu-btn" aria-haspopup="true" aria-expanded="false"><i class="bi bi-sliders"></i><span data-st-menu-label>Więcej</span><i class="bi bi-chevron-down"></i></button>
        <div class="st-menu-panel st-more-panel" hidden>
          <label class="st-field">Płatność
            <select class="st-select" data-st-paid>
              <option value="">Każda</option>
              <option value="1">Tylko opłacone</option>
              <option value="0">Tylko nieopłacone</option>
            </select>
          </label>
          <label class="st-field" data-st-currency-field hidden>Waluta kwot
            <select class="st-select" data-st-currency></select>
          </label>
        </div>
      </div>
      <button type="button" class="st-icon-btn" data-st-refresh title="Odśwież dane" aria-label="Odśwież dane"><i class="bi bi-arrow-clockwise"></i></button>
    </div>
  </header>
  {if $flashError}<div class="st-alert">{$flashError|escape}</div>{/if}
  <div class="st-alert" data-st-error role="alert" hidden></div>

  <div class="st-nav">
    <div class="st-tabs" data-st-tabs role="tablist" aria-label="Widoki statystyk">
      <button type="button" role="tab" data-value="overview"><i class="bi bi-speedometer2"></i> Przegląd</button>
      <button type="button" role="tab" data-value="accounts"><i class="bi bi-shop"></i> Konta</button>
      <button type="button" role="tab" data-value="products"><i class="bi bi-box-seam"></i> Produkty</button>
      <button type="button" role="tab" data-value="orders"><i class="bi bi-receipt"></i> Zamówienia</button>
    </div>
    <div class="st-nav-metric">
      <span>Pokaż</span>
      <div class="st-seg" data-st-metric role="group" aria-label="Miara na wykresach">
        <button type="button" data-value="units">Sztuki</button>
        <button type="button" data-value="orders">Zamówienia</button>
        <button type="button" data-value="revenue">Przychód</button>
      </div>
    </div>
  </div>

  <div class="st-body is-loading" data-st-body aria-busy="true">
    <div data-st-panel="overview">
      <section class="st-heroes" data-st-kpis aria-label="Najważniejsze liczby"></section>
      <section class="st-facts" data-st-facts aria-label="Wskaźniki zamówień"></section>

      <section class="st-card">
        <div class="st-card-head">
          <div><h2 data-st-timeline-title>Sprzedaż w czasie</h2><p data-st-timeline-sub></p></div>
          <div class="st-card-tools">
            <div class="st-seg st-seg-small" data-st-group role="group" aria-label="Grupowanie osi czasu">
              <button type="button" data-value="day">Dni</button>
              <button type="button" data-value="week">Tygodnie</button>
              <button type="button" data-value="month">Miesiące</button>
            </div>
            <label class="st-switch"><input type="checkbox" data-st-split><span>Podział na konta</span></label>
          </div>
        </div>
        <div class="st-legend" data-st-timeline-legend></div>
        <div class="st-chart" data-st-timeline></div>
        <div class="st-card-foot">
          <button type="button" class="st-link" data-st-toggle-table><i class="bi bi-table"></i> <span>Pokaż tabelę</span></button>
          <button type="button" class="st-link" data-st-csv="timeline"><i class="bi bi-download"></i> Pobierz CSV</button>
        </div>
        <div class="st-table-wrap st-timeline-table" data-st-timeline-table hidden></div>
      </section>

      <div class="st-grid">
        <section class="st-card">
          <div class="st-card-head"><div><h2>Wnioski z okresu</h2><p>Najważniejsze obserwacje dla wybranych filtrów.</p></div></div>
          <ul class="st-insights" data-st-insights></ul>
        </section>
        <div class="st-stack">
          <section class="st-card">
            <div class="st-card-head"><div><h2>Najlepsze konta</h2></div><button type="button" class="st-link" data-st-goto="accounts">Wszystkie konta <i class="bi bi-arrow-right"></i></button></div>
            <div class="st-bars" data-st-top-accounts></div>
          </section>
          <section class="st-card">
            <div class="st-card-head"><div><h2>Najlepsze produkty</h2></div><button type="button" class="st-link" data-st-goto="products">Wszystkie produkty <i class="bi bi-arrow-right"></i></button></div>
            <div class="st-bars st-bars-stacked" data-st-top-products></div>
          </section>
        </div>
      </div>
    </div>

    <div data-st-panel="accounts" hidden>
      <section class="st-card">
        <div class="st-card-head">
          <div><h2>Konta sprzedażowe</h2><p data-st-accounts-sub></p></div>
          <div class="st-card-tools"><button type="button" class="st-link" data-st-csv="accounts"><i class="bi bi-download"></i> Pobierz CSV</button></div>
        </div>
        <div class="st-table-wrap" data-st-accounts-table></div>
      </section>
    </div>

    <div data-st-panel="products" hidden>
      <section class="st-card">
        <div class="st-card-head">
          <div><h2>Produkty</h2><p data-st-products-sub></p></div>
          <div class="st-card-tools">
            <label class="st-search"><i class="bi bi-search"></i><input type="search" data-st-product-search placeholder="Szukaj produktu lub SKU" aria-label="Szukaj produktu lub SKU"></label>
            <button type="button" class="st-link" data-st-csv="products"><i class="bi bi-download"></i> Pobierz CSV</button>
          </div>
        </div>
        <div class="st-table-wrap" data-st-products></div>
        <div class="st-more" data-st-products-more hidden><button type="button" class="st-btn">Pokaż więcej</button></div>
      </section>
    </div>

    <div data-st-panel="orders" hidden>
      <section class="st-card">
        <div class="st-card-head"><div><h2>Kiedy kupują klienci</h2><p data-st-heatmap-sub></p></div></div>
        <div data-st-heatmap></div>
      </section>
      <div class="st-tiles">
        <section class="st-card"><div class="st-card-head"><div><h2>Statusy</h2><p>Wszystkie zamówienia okresu, także pominięte w sprzedaży.</p></div></div><div class="st-bars st-bars-stacked" data-st-mini="statuses"></div></section>
        <section class="st-card"><div class="st-card-head"><div><h2>Platformy</h2></div></div><div class="st-bars st-bars-stacked" data-st-mini="platforms"></div></section>
        <section class="st-card"><div class="st-card-head"><div><h2>Płatność</h2></div></div><div class="st-bars st-bars-stacked" data-st-mini="payment"></div></section>
        <section class="st-card"><div class="st-card-head"><div><h2>Dostawa</h2></div></div><div class="st-bars st-bars-stacked" data-st-mini="delivery"></div></section>
        <section class="st-card"><div class="st-card-head"><div><h2>Wielkość zamówienia</h2></div></div><div class="st-bars st-bars-stacked" data-st-mini="basket"></div></section>
      </div>
    </div>
  </div>
</div>
<div class="st-tip" data-st-tip role="status" hidden></div>
<script type="application/json" id="st-options">{$statsOptionsJson nofilter}</script>
<script src="dist/js/stats.js?v=20261012-2"></script>
</main>
