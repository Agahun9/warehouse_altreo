<link rel="stylesheet" href="dist/css/stats.css?v=20261012-1">
<main class="app-main st" data-st-root data-endpoint="{$baseUrl}?controller=stats&action=data" data-tenant="{$statsTenantId}">
<div class="st-shell">
  <header class="st-hero">
    <div>
      <div class="st-eyebrow">SPRZEDAŻ · ANALIZA</div>
      <h1>Statystyki sprzedaży</h1>
      <p data-st-period>Wczytywanie danych…</p>
    </div>
    <div class="st-hero-actions">
      <span class="st-updated" data-st-updated></span>
      <button type="button" class="st-btn" data-st-refresh><i class="bi bi-arrow-clockwise"></i> Odśwież</button>
    </div>
  </header>
  {if $flashError}<div class="st-alert">{$flashError|escape}</div>{/if}

  <section class="st-filters" aria-label="Filtry statystyk">
    <div class="st-seg st-presets" data-st-presets role="group" aria-label="Zakres dat">
      <button type="button" data-value="today">Dziś</button>
      <button type="button" data-value="yesterday">Wczoraj</button>
      <button type="button" data-value="7d">7 dni</button>
      <button type="button" data-value="30d">30 dni</button>
      <button type="button" data-value="90d">90 dni</button>
      <button type="button" data-value="month">Ten miesiąc</button>
      <button type="button" data-value="prev_month">Poprzedni miesiąc</button>
      <button type="button" data-value="year">Ten rok</button>
    </div>
    <div class="st-dates">
      <input type="date" data-st-from aria-label="Data od">
      <span aria-hidden="true">–</span>
      <input type="date" data-st-to aria-label="Data do">
    </div>
    <div class="st-menu" data-st-menu="accounts">
      <button type="button" class="st-menu-btn" aria-haspopup="true" aria-expanded="false"><i class="bi bi-plug"></i><span data-st-menu-label>Wszystkie konta</span><i class="bi bi-chevron-down"></i></button>
      <div class="st-menu-panel" hidden></div>
    </div>
    <div class="st-menu" data-st-menu="statuses">
      <button type="button" class="st-menu-btn" aria-haspopup="true" aria-expanded="false"><i class="bi bi-diagram-3"></i><span data-st-menu-label>Statusy sprzedaży</span><i class="bi bi-chevron-down"></i></button>
      <div class="st-menu-panel" hidden></div>
    </div>
    <select class="st-select" data-st-paid aria-label="Płatność">
      <option value="">Każda płatność</option>
      <option value="1">Tylko opłacone</option>
      <option value="0">Tylko nieopłacone</option>
    </select>
    <select class="st-select" data-st-currency aria-label="Waluta kwot" hidden></select>
    <div class="st-seg" data-st-group role="group" aria-label="Grupowanie osi czasu">
      <button type="button" data-value="auto">Auto</button>
      <button type="button" data-value="day">Dni</button>
      <button type="button" data-value="week">Tygodnie</button>
      <button type="button" data-value="month">Miesiące</button>
    </div>
    <div class="st-seg st-seg-metric" data-st-metric role="group" aria-label="Miara na wykresach">
      <button type="button" data-value="units">Sztuki</button>
      <button type="button" data-value="orders">Zamówienia</button>
      <button type="button" data-value="revenue">Przychód</button>
    </div>
  </section>

  <div class="st-alert" data-st-error role="alert" hidden></div>

  <div class="st-body is-loading" data-st-body aria-busy="true">
    <section class="st-kpis" data-st-kpis aria-label="Najważniejsze liczby"></section>

    <section class="st-card st-insights" aria-label="Wnioski">
      <div class="st-card-head"><div><h2><i class="bi bi-lightbulb"></i> Wnioski z okresu</h2></div></div>
      <ul data-st-insights></ul>
    </section>

    <section class="st-card">
      <div class="st-card-head">
        <div><h2 data-st-timeline-title>Sprzedaż w czasie</h2><p data-st-timeline-sub></p></div>
        <div class="st-card-tools"><button type="button" class="st-link" data-st-toggle-table>Pokaż tabelę</button><button type="button" class="st-link" data-st-csv="timeline"><i class="bi bi-download"></i> CSV</button></div>
      </div>
      <div class="st-legend" data-st-timeline-legend></div>
      <div class="st-chart" data-st-timeline></div>
      <div class="st-table-wrap st-timeline-table" data-st-timeline-table hidden></div>
    </section>

    <div class="st-grid">
      <section class="st-card">
        <div class="st-card-head"><div><h2>Konta</h2><p data-st-accounts-sub></p></div></div>
        <div class="st-bars" data-st-accounts-bars></div>
      </section>
      <section class="st-card">
        <div class="st-card-head"><div><h2>Kiedy kupują klienci</h2><p data-st-heatmap-sub></p></div></div>
        <div data-st-heatmap></div>
      </section>
    </div>

    <section class="st-card">
      <div class="st-card-head">
        <div><h2>Konta — szczegóły</h2><p>Kliknij nagłówek kolumny, żeby posortować.</p></div>
        <div class="st-card-tools"><button type="button" class="st-link" data-st-csv="accounts"><i class="bi bi-download"></i> CSV</button></div>
      </div>
      <div class="st-table-wrap" data-st-accounts-table></div>
    </section>

    <section class="st-card">
      <div class="st-card-head"><div><h2>Struktura zamówień</h2><p data-st-minis-sub></p></div></div>
      <div class="st-minis">
        <div><h3>Statusy</h3><div class="st-bars" data-st-mini="statuses"></div></div>
        <div><h3>Platformy</h3><div class="st-bars" data-st-mini="platforms"></div></div>
        <div><h3>Płatność</h3><div class="st-bars" data-st-mini="payment"></div></div>
        <div><h3>Dostawa</h3><div class="st-bars" data-st-mini="delivery"></div></div>
        <div><h3>Wielkość zamówienia</h3><div class="st-bars" data-st-mini="basket"></div></div>
      </div>
    </section>

    <section class="st-card">
      <div class="st-card-head">
        <div><h2>Produkty</h2><p data-st-products-sub></p></div>
        <div class="st-card-tools">
          <label class="st-search"><i class="bi bi-search"></i><input type="search" data-st-product-search placeholder="Szukaj produktu lub SKU" aria-label="Szukaj produktu lub SKU"></label>
          <button type="button" class="st-link" data-st-csv="products"><i class="bi bi-download"></i> CSV</button>
        </div>
      </div>
      <div class="st-table-wrap" data-st-products></div>
      <div class="st-more" data-st-products-more hidden><button type="button" class="st-btn">Pokaż więcej</button></div>
    </section>
  </div>
</div>
<div class="st-tip" data-st-tip role="status" hidden></div>
<script type="application/json" id="st-options">{$statsOptionsJson nofilter}</script>
<script src="dist/js/stats.js?v=20261012-1"></script>
</main>
