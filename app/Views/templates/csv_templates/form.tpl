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
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=csvtemplates&action=index">Szablony CSV</a></li>
            <li class="breadcrumb-item active" aria-current="page">{$breadcrumbCurrent|escape}</li>
          </ol>
        </div>
      </div>
    </div>
  </div>
  <div class="app-content">
    <div class="container-fluid">
      {if $flashSuccess}<div class="alert alert-success">{$flashSuccess|escape}</div>{/if}
      {if $flashError}<div class="alert alert-danger">{$flashError|escape}</div>{/if}

      <style>
        #csv-template-form {
          --csv-shell-bg: linear-gradient(180deg, #f7f8fc 0%, #eef2f8 100%);
          --csv-card-bg: rgba(255, 255, 255, 0.96);
          --csv-border: rgba(24, 39, 75, 0.12);
          --csv-border-strong: rgba(24, 39, 75, 0.18);
          --csv-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
          --csv-shadow-soft: 0 10px 24px rgba(15, 23, 42, 0.05);
          --csv-title: #122033;
          --csv-text: #334155;
          --csv-muted: #64748b;
          --csv-accent: #0d6efd;
          --csv-accent-soft: rgba(13, 110, 253, 0.08);
          background: var(--csv-shell-bg);
          border: 1px solid rgba(255, 255, 255, 0.7);
          border-radius: 28px;
          padding: 1.25rem;
          box-shadow: var(--csv-shadow);
        }

        #csv-template-form > .card,
        #csv-template-form > div > .card {
          margin-bottom: 1.15rem !important;
        }

        #csv-template-form .card {
          border: 1px solid var(--csv-border);
          border-radius: 22px;
          overflow: hidden;
          background: var(--csv-card-bg);
          box-shadow: var(--csv-shadow-soft);
        }

        #csv-template-form .card-header {
          background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(241, 245, 249, 0.96));
          border-bottom: 1px solid var(--csv-border);
          padding: 1rem 1.2rem;
        }

        #csv-template-form .card-title {
          color: var(--csv-title);
          font-size: 1.03rem;
          font-weight: 700;
          letter-spacing: 0.01em;
        }

        #csv-template-form .card-body {
          padding: 1.2rem;
        }

        #csv-template-form label.form-label {
          color: var(--csv-title);
          font-size: 0.82rem;
          font-weight: 700;
          letter-spacing: 0.03em;
          text-transform: uppercase;
          margin-bottom: 0.45rem;
        }

        #csv-template-form .form-control,
        #csv-template-form .form-select {
          border-radius: 14px;
          border: 1px solid rgba(148, 163, 184, 0.4);
          background: rgba(255, 255, 255, 0.98);
          box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.75);
        }

        #csv-template-form .form-control:focus,
        #csv-template-form .form-select:focus {
          border-color: rgba(13, 110, 253, 0.5);
          box-shadow: 0 0 0 0.22rem rgba(13, 110, 253, 0.12);
        }

        #csv-template-form .form-text,
        #csv-template-form .text-secondary,
        #csv-template-form .small {
          color: var(--csv-muted) !important;
        }

        #csv-template-form .h6,
        #csv-template-form h4.h6 {
          color: var(--csv-title);
          font-size: 0.92rem;
          font-weight: 800;
          margin-bottom: 0.55rem;
        }

        #csv-template-form code {
          background: rgba(15, 23, 42, 0.06);
          color: #0f172a;
          border-radius: 8px;
          padding: 0.12rem 0.35rem;
        }

        #csv-template-form pre {
          border-radius: 16px !important;
          background: #f8fafc !important;
        }

        #csv-template-form .alert {
          border-radius: 18px;
          border-width: 1px;
        }

        #csv-template-form .alert-info {
          background: linear-gradient(180deg, rgba(13, 202, 240, 0.1), rgba(13, 202, 240, 0.04));
          border-color: rgba(13, 202, 240, 0.2);
        }

        #csv-template-form .description-template-card,
        #csv-template-form .column-card {
          border-radius: 20px;
          border: 1px solid var(--csv-border-strong);
          background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(248,250,252,0.96));
          box-shadow: 0 16px 30px rgba(15, 23, 42, 0.06);
        }

        #csv-template-form .description-template-card .card-body,
        #csv-template-form .column-card .card-body {
          padding: 1rem;
        }

        #csv-template-form .description-template-card {
          position: relative;
        }

        #csv-template-form .description-template-card::before,
        #csv-template-form .column-card::before {
          content: "";
          display: block;
          height: 4px;
          background: linear-gradient(90deg, rgba(13,110,253,0.95), rgba(14,165,233,0.55));
        }

        #csv-template-form .description-section-card {
          overflow: hidden;
          border-radius: 14px !important;
          border: 1px solid rgba(148, 163, 184, 0.28) !important;
          background: #ffffff !important;
          padding: 0 !important;
          box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
        }

        #csv-template-form .description-section-header {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 0.75rem;
          padding: 0.62rem 0.72rem;
          border-bottom: 1px solid rgba(148, 163, 184, 0.18);
          background: #f8fafc;
        }

        #csv-template-form .description-section-title {
          display: inline-flex;
          align-items: center;
          gap: 0.5rem;
          min-width: 0;
          color: var(--csv-title);
          font-size: 0.8rem;
          font-weight: 800;
          letter-spacing: 0.04em;
          text-transform: uppercase;
        }

        #csv-template-form .description-section-title .js-drag-handle {
          cursor: move;
          color: #475569 !important;
        }

        #csv-template-form .description-section-controls {
          display: flex;
          align-items: center;
          gap: 0.5rem;
          flex-wrap: wrap;
          justify-content: flex-end;
        }

        #csv-template-form .description-section-controls .desc-layout {
          min-width: 180px;
        }

        #csv-template-form .description-section-body {
          padding: 0.72rem;
        }

        #csv-template-form .description-section-pane {
          border: 1px solid rgba(148, 163, 184, 0.22) !important;
          border-radius: 12px !important;
          background: #ffffff;
          padding: 0.62rem !important;
        }

        #csv-template-form .description-section-card > .row > div > .border,
        #csv-template-form .column-card .border.rounded {
          border-color: rgba(148, 163, 184, 0.28) !important;
          border-radius: 16px !important;
          background: rgba(255,255,255,0.72);
        }

        #csv-template-form #descriptionTemplatesBuilder > .card,
        #csv-template-form #columnsBuilder > .card {
          transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }

        #csv-template-form #descriptionTemplatesBuilder > .card:hover,
        #csv-template-form #columnsBuilder > .card:hover {
          transform: translateY(-1px);
          box-shadow: 0 20px 36px rgba(15, 23, 42, 0.08);
          border-color: rgba(13, 110, 253, 0.22);
        }

        #csv-template-form .is-dragging {
          opacity: 0.55;
          transform: scale(0.99);
        }

        #csv-template-form .drag-insert-before,
        #csv-template-form .drag-insert-after {
          position: relative;
        }

        #csv-template-form .drag-insert-before::before,
        #csv-template-form .drag-insert-after::after {
          content: "";
          position: absolute;
          left: 14px;
          right: 14px;
          height: 4px;
          border-radius: 999px;
          background: linear-gradient(90deg, rgba(13,110,253,1), rgba(14,165,233,0.8));
          box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.12);
          pointer-events: none;
        }

        #csv-template-form .drag-insert-before::before {
          top: -8px;
        }

        #csv-template-form .drag-insert-after::after {
          bottom: -8px;
        }

        #csv-template-form .drag-placeholder {
          border: 2px dashed rgba(13, 110, 253, 0.45);
          border-radius: 18px;
          background:
            linear-gradient(180deg, rgba(13,110,253,0.05), rgba(14,165,233,0.08));
          min-height: 28px;
          margin: 0.15rem 0;
          position: relative;
          overflow: hidden;
        }

        #csv-template-form .drag-placeholder::before {
          content: "Upusc tutaj";
          position: absolute;
          inset: 0;
          display: flex;
          align-items: center;
          justify-content: center;
          color: rgba(13, 110, 253, 0.9);
          font-size: 0.72rem;
          font-weight: 800;
          letter-spacing: 0.08em;
          text-transform: uppercase;
        }

        #csv-template-form .description-section-card .small.fw-semibold,
        #csv-template-form .column-card .small.fw-semibold {
          color: var(--csv-title) !important;
          font-weight: 800 !important;
          letter-spacing: 0.02em;
        }

        #csv-template-form .description-template-card .desc-template-label-preview {
          display: inline-flex;
          align-items: center;
          min-height: 2rem;
          padding: 0.22rem 0.7rem;
          border-radius: 999px;
          background: rgba(15, 23, 42, 0.06);
          color: var(--csv-title);
          font-weight: 700;
        }

        #csv-template-form .description-section-card .btn-outline-danger,
        #csv-template-form .column-card .btn-outline-danger {
          border-radius: 12px;
        }

        #csv-template-form .description-sections-list,
        #csv-template-form #columnsBuilder,
        #csv-template-form #descriptionTemplatesBuilder {
          gap: 0.72rem !important;
        }

        #csv-template-form .desc-editor-shell {
          display: grid;
          gap: 0.42rem;
        }

        #csv-template-form .desc-format-toolbar {
          display: flex;
          flex-wrap: wrap;
          gap: 0.28rem;
          align-items: center;
          padding: 0.35rem;
          border: 1px solid rgba(148, 163, 184, 0.22);
          border-radius: 12px;
          background: rgba(248, 250, 252, 0.86);
        }

        #csv-template-form .desc-format-toolbar .btn {
          min-width: 2.15rem;
          padding: 0.2rem 0.42rem;
          line-height: 1.1;
        }

        #csv-template-form .desc-token-tools {
          padding: 0.55rem !important;
        }

        #csv-template-form .desc-text-input {
          display: none;
        }

        #csv-template-form .desc-editor-shell.is-source-mode .desc-visual-editor {
          display: none;
        }

        #csv-template-form .desc-editor-shell.is-source-mode .desc-text-input {
          display: block;
          font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
        }

        #csv-template-form .desc-source-toggle.is-active {
          color: #ffffff;
          background: var(--csv-accent);
        }

        #csv-template-form .desc-visual-editor {
          border: 1px solid rgba(148, 163, 184, 0.4);
          border-radius: 14px;
          background: rgba(255, 255, 255, 0.98);
          box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.75);
          color: var(--csv-text);
          padding: 0.65rem 0.75rem;
          min-height: 96px;
          outline: none;
        }

        #csv-template-form .desc-visual-editor:focus {
          border-color: rgba(13, 110, 253, 0.5);
          box-shadow: 0 0 0 0.22rem rgba(13, 110, 253, 0.12);
        }

        #csv-template-form .desc-visual-editor h1,
        #csv-template-form .desc-visual-editor h2,
        #csv-template-form .desc-visual-editor h3 {
          color: var(--csv-title);
          margin: 0.25rem 0 0.45rem;
          line-height: 1.2;
        }

        #csv-template-form .desc-visual-editor h1 {
          font-size: 1.7rem;
        }

        #csv-template-form .desc-visual-editor h2 {
          font-size: 1.38rem;
        }

        #csv-template-form .desc-visual-editor h3 {
          font-size: 1.12rem;
        }

        #csv-template-form .desc-format-button.is-active {
          color: #ffffff;
          background: var(--csv-accent);
          border-color: var(--csv-accent);
        }

        #csv-template-form .desc-token-pill {
          display: inline-flex;
          align-items: center;
          max-width: 100%;
          margin: 0 0.14rem;
          padding: 0.08rem 0.42rem;
          border: 1px solid rgba(13, 110, 253, 0.22);
          border-radius: 999px;
          background: rgba(13, 110, 253, 0.08);
          color: #0b5ed7;
          font-size: 0.86em;
          font-weight: 700;
          white-space: nowrap;
        }

        #csv-template-form .btn {
          border-radius: 14px;
          font-weight: 600;
        }

        #csv-template-form .btn-sm {
          border-radius: 12px;
        }

        #csv-template-form .desc-image-index {
          background: linear-gradient(180deg, #ffffff, #f8fbff);
          border-color: rgba(13, 110, 253, 0.22);
        }

        #csv-template-form .desc-image-index-label {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          min-height: 2rem;
          padding: 0.2rem 0.7rem;
          border-radius: 999px;
          background: var(--csv-accent-soft);
          color: var(--csv-accent);
          font-size: 0.84rem;
          font-weight: 800;
          letter-spacing: 0.08em;
        }

        #csv-template-form .tutorial-grid > [class*="col-"] > *:not(style):not(script) {
          height: 100%;
        }

        #csv-template-form .csv-tutorial-block {
          height: 100%;
          border: 1px solid rgba(148, 163, 184, 0.22);
          border-radius: 18px;
          background: linear-gradient(180deg, rgba(255,255,255,0.95), rgba(248,250,252,0.92));
          padding: 1rem;
        }

        #csv-template-form .csv-tutorial-block ul,
        #csv-template-form .csv-tutorial-block ol,
        #csv-template-form .csv-tutorial-block p {
          margin-bottom: 0;
        }

        #csv-template-form .csv-builder-intro {
          border: 1px dashed rgba(13, 110, 253, 0.22);
          border-radius: 16px;
          background: rgba(13, 110, 253, 0.04);
          padding: 0.9rem 1rem;
        }

        #csv-template-form .csv-builder-intro + .alert {
          margin-top: 0.85rem;
        }

        #csv-template-form .computed-json-wrap,
        #csv-template-form .desc-left-text,
        #csv-template-form .desc-right-text,
        #csv-template-form .desc-single-text {
          scroll-margin-top: 90px;
        }

        #csv-template-form .csv-template-actions {
          position: sticky;
          bottom: 12px;
          z-index: 20;
          margin-top: 1.5rem;
          padding: 1rem 1.1rem;
          border: 1px solid rgba(148, 163, 184, 0.24);
          border-radius: 20px;
          background: rgba(255, 255, 255, 0.92);
          backdrop-filter: blur(14px);
          box-shadow: 0 18px 36px rgba(15, 23, 42, 0.12);
        }

        #csv-template-form .csv-template-actions .btn {
          min-width: 150px;
        }

        #csv-template-form .csv-template-preview textarea {
          font-family: Consolas, "Courier New", monospace;
          font-size: 0.88rem;
          border-radius: 18px;
        }

        @media (max-width: 991.98px) {
          #csv-template-form {
            padding: 0.9rem;
            border-radius: 22px;
          }

          #csv-template-form .card-body,
          #csv-template-form .card-header {
            padding: 0.95rem;
          }

          #csv-template-form .csv-template-actions {
            position: static;
            padding: 0.9rem;
          }

          #csv-template-form .csv-template-actions,
          #csv-template-form .csv-template-actions > div {
            gap: 0.75rem !important;
          }

          #csv-template-form .csv-template-actions .btn,
          #csv-template-form .csv-template-actions a {
            width: 100%;
            min-width: 0;
          }
        }

        @media (max-width: 767.98px) {
          #csv-template-form .card-title {
            font-size: 0.95rem;
          }

          #csv-template-form .description-section-card,
          #csv-template-form .column-card .card-body,
          #csv-template-form .description-template-card .card-body {
            padding: 0.8rem !important;
          }

          #csv-template-form .description-section-card {
            padding: 0 !important;
          }

          #csv-template-form .description-section-header,
          #csv-template-form .description-section-controls {
            align-items: stretch;
            flex-direction: column;
          }

          #csv-template-form .description-section-controls .desc-layout,
          #csv-template-form .description-section-controls .btn {
            width: 100%;
          }
        }
      </style>

      <form method="post" action="{$formAction|escape}" id="csv-template-form">
        <input type="hidden" name="id" value="{$template.id|default:0|escape}">
        <input type="hidden" name="columns_payload" id="columnsPayload" value="[]">
        <input type="hidden" name="description_templates_payload" id="descriptionTemplatesPayload" value="[]">

        <div class="card mb-4">
          <div class="card-header"><h3 class="card-title mb-0">Dane glowne</h3></div>
          <div class="card-body row g-3">
            <div class="col-md-4">
              <label class="form-label">Nazwa szablonu</label>
              <input type="text" name="name" class="form-control" value="{$template.name|default:''|escape}" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Opis</label>
              <input type="text" name="description" class="form-control" value="{$template.description|default:''|escape}">
            </div>
            <div class="col-md-4">
              <label class="form-label">Kategoria</label>
              <select name="category_id" class="form-select">
                <option value="0">Bez kategorii</option>
                {foreach $templateCategories as $category}
                  <option value="{$category.id}"{if $template.category_id|default:0 == $category.id} selected{/if}>{$category.name|escape}</option>
                {/foreach}
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Separator</label>
              <select name="delimiter" class="form-select">
                <option value=","{if $template.delimiter|default:';' eq ','} selected{/if}>,</option>
                <option value=";"{if $template.delimiter|default:';' eq ';'} selected{/if}>;</option>
                <option value="|"{if $template.delimiter|default:';' eq '|'} selected{/if}>|</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Kodowanie</label>
              <select name="encoding" class="form-select">
                <option value="UTF-8"{if $template.encoding|default:'UTF-8' eq 'UTF-8'} selected{/if}>UTF-8</option>
                <option value="WINDOWS-1250"{if $template.encoding|default:'UTF-8' eq 'WINDOWS-1250'} selected{/if}>Windows-1250</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">BOM</label>
              <select name="add_bom" class="form-select">
                <option value="1"{if $template.add_bom|default:1} selected{/if}>Tak</option>
                <option value="0"{if !$template.add_bom|default:1} selected{/if}>Nie</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Separator tablic</label>
              <input type="text" name="array_separator" class="form-control" value="{$template.array_separator|default:'|'|escape}">
            </div>
          </div>
        </div>

        <div class="card mb-4">
          <div class="card-header"><h3 class="card-title mb-0">Tutorial uzycia</h3></div>
          <div class="card-body">
            <div class="row g-3 tutorial-grid">
              <div class="col-lg-6">
                <div class="csv-tutorial-block">
                <h4 class="h6">1. Jak zbudowac prosty szablon</h4>
                <ol class="small mb-0 ps-3">
                  <li>Wpisz nazwe szablonu, np. <code>Google Merchant</code> albo <code>Eksport magazynowy</code>.</li>
                  <li>Ustaw separator CSV, kodowanie i opcjonalnie BOM.</li>
                  <li>Dodaj kolumny przyciskiem <code>Dodaj kolumne</code>.</li>
                  <li>Dla kazdej kolumny podaj naglowek i wybierz typ zrodla: <code>field</code>, <code>static</code> albo <code>computed</code>.</li>
                  <li>Zapisz szablon albo uzyj przycisku <code>Podglad CSV</code>, aby sprawdzic pierwsze rekordy.</li>
                </ol>
                </div>
              </div>
              <div class="col-lg-6">
                <div class="csv-tutorial-block">
                <h4 class="h6">2. Typy kolumn</h4>
                <ul class="small mb-0">
                  <li><code>field</code>: pobiera wartosc z produktu, np. <code>product.sku</code>, <code>product.product_name</code>, <code>product.category_name</code>.</li>
                  <li><code>static</code>: wpisuje stala wartosc do kazdego wiersza, np. <code>PLN</code>, <code>nowy</code>, <code>ALTREO</code>.</li>
                  <li><code>computed</code>: wylicza wartosc z kilku pol lub transformacji, np. laczy cene z waluta albo zamienia tekst na wielkie litery.</li>
                </ul>
                </div>
              </div>
              <div class="col-lg-6">
                <div class="csv-tutorial-block">
                <h4 class="h6">3. Przyklady pol i relacji</h4>
                <ul class="small mb-0">
                  <li><code>product.sku</code> - SKU produktu</li>
                  <li><code>product.product_name</code> - nazwa produktu</li>
                  <li><code>product.price_gross</code> - cena brutto</li>
                  <li><code>product.category_name</code> - nazwa kategorii</li>
                  <li><code>product.images[0].url</code> - pierwszy obrazek</li>
                  <li><code>product.images</code> - wszystkie obrazki, polaczone separatorem tablicy</li>
                  <li><code>product.allegro_parameters</code> - wszystkie parametry Allegro w jednej komorce, kazdy w nowej linii</li>
                  <li><code>product.allegro_compatibility_list</code> - sekcja Allegro Pasuje do, kazdy model telefonu w nowej linii</li>
                  <li><code>product.allegro_parameters_eu</code> - wszystkie parametry Allegro w formacie EU: <code>parameter_id|type|value|</code></li>
                  <li><code>product.empik_parameters</code> - wszystkie parametry Empik w jednej komorce, kazdy w nowej linii</li>
                  <li><code>product.empik_parameter.600</code> - pojedynczy parametr Empik po ID, np. Producent</li>
                  <li><code>product.mediamarkt_parameters</code> - wszystkie parametry MediaMarkt w jednej komorce</li>
                  <li><code>product.mediamarkt_parameter.ID</code> - pojedynczy parametr MediaMarkt po ID</li>
                  <li><code>images</code> lub <code>product.generated_images</code> (oraz <code>product.generated_images[1..16]</code>) - generowana lista / pojedyncze sciezki obrazow EasyUploader. <strong>Uwaga:</strong> dzialaja tylko, gdy przy eksporcie CSV ustawisz liczby Zdjecia/Miniatury/Mockupy - sam szablon tego nie definiuje.</li>
                  <li><code>queue_item</code> - miniatura wpisywana podczas eksportu CSV</li>
                </ul>
                </div>
              </div>
              <div class="col-lg-6">
                <div class="csv-tutorial-block">
                <h4 class="h6">4. Funkcje computed</h4>
                <div class="small">
                  <p class="mb-2">W argumencie JSON mozesz uzywac tokenow typu <code>field:product.price_gross</code>.</p>
                  <p class="mb-1"><strong>Przyklad concat:</strong></p>
                  <pre class="bg-light border rounded p-2 small"><code>{ldelim}"separator":" ","parts":["field:product.price_gross","PLN"]{rdelim}</code></pre>
                  <p class="mb-1"><strong>Przyklad concat z parametrem Allegro:</strong></p>
                  <pre class="bg-light border rounded p-2 small"><code>{ldelim}"separator":" | ","parts":["field:product.product_name","field:product.allegro_parameter.11748"]{rdelim}</code></pre>
                  <p class="mb-1"><strong>Przyklad upper:</strong></p>
                  <pre class="bg-light border rounded p-2 small"><code>{ldelim}"value":"field:product.product_name"{rdelim}</code></pre>
                  <p class="mb-0 text-secondary">Dokladny klucz parametru bierzesz z listy pol ponizej. Przy polach Allegro, Empik i MediaMarkt pokazuje sie nazwa parametru, jego ID oraz nazwy kategorii, z ktorych jest brany.</p>
                </div>
                </div>
              </div>
              <div class="col-lg-6">
                <div class="csv-tutorial-block">
                <h4 class="h6">5. Mapowanie wartosci</h4>
                <div class="small">
                  <p class="mb-2">Mapowanie zamienia jedna wartosc na inna po obliczeniu kolumny.</p>
                  <ul class="mb-0">
                    <li><code>0</code> -> <code>Brak</code></li>
                    <li><code>1</code> -> <code>Dostepny</code></li>
                    <li><code>23.00</code> -> <code>23%</code></li>
                  </ul>
                </div>
                </div>
              </div>
              <div class="col-lg-6">
                <div class="csv-tutorial-block">
                <h4 class="h6">6. Warunki</h4>
                <div class="small">
                  <p class="mb-2">Możesz dodać wiele warunków. Są sprawdzane od góry i wygrywa pierwszy pasujący.</p>
                  <p class="mb-1">Przykład normalizacji koloru:</p>
                  <ul class="mb-0">
                    <li><code>zawiera czarny</code> → <code>Czarna</code></li>
                    <li><code>zawiera biały</code> → <code>Biała</code></li>
                    <li><code>zawiera czerwony</code> → <code>Czerwona</code></li>
                    <li>„Gdy nic nie pasuje” zostaw puste, aby zachować oryginalną wartość.</li>
                  </ul>
                </div>
                </div>
              </div>
              <div class="col-lg-6">
                <div class="csv-tutorial-block">
                <h4 class="h6">7. Formatowanie</h4>
                <ul class="small mb-0">
                  <li><code>upper</code> - zamiana na wielkie litery</li>
                  <li><code>lower</code> - zamiana na male litery</li>
                  <li><code>ucfirst</code> - pierwsza litera tekstu jako wielka</li>
                  <li><code>trim</code> - usuniecie spacji z poczatku i konca</li>
                  <li><code>date:Y-m-d</code> - format daty</li>
                  <li><code>number:2:,: </code> - format liczbowy: 2 miejsca, przecinek dziesietny, spacja tysieczna</li>
                  <li><code>length:2000</code> - obciecie tekstu do maksymalnie 2000 znakow</li>
                </ul>
                </div>
              </div>
              <div class="col-lg-6">
                <div class="csv-tutorial-block">
                <h4 class="h6">8. Jak wykonac eksport</h4>
                <ol class="small mb-0 ps-3">
                  <li>Zapisz szablon.</li>
                  <li>Przejdz do listy produktow.</li>
                  <li>Zaznacz wybrane produkty albo wybierz eksport wszystkich.</li>
                  <li>Jesli uzywasz pola <code>images</code>, uzupelnij w modalu eksportu kolekcje, ilosci zdjec, miniatur i mockupow oraz makra sciezek.</li>
                  <li>Kliknij <code>Eksport CSV</code>, wybierz szablon i wygeneruj plik.</li>
                </ol>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Szablony opisu</h3>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addDescriptionTemplateBtn">Dodaj opis</button>
          </div>
          <div class="card-body">
            <div class="small text-secondary mb-2 csv-builder-intro">
              Tutaj budujesz gotowe opisy HTML skladane z sekcji jak w Allegro. Kazdy zapisany opis pojawi sie potem na liscie pol jako
              <code>Opis CSV: nazwa</code>.
            </div>
            <div class="alert alert-info py-2 px-3 small">
              Dla obrazow wybierasz zrodlo <code>IMG_EU</code> i numer zdjecia, np. 1, 2, 3. Teksty obsluguja tokeny
              <code>{ldelim}{ldelim}field:product.sku{rdelim}{rdelim}</code>,
              <code>{ldelim}{ldelim}field:product.product_name{rdelim}{rdelim}</code>,
              <code>{ldelim}{ldelim}option:collection_name{rdelim}{rdelim}</code>.
            </div>
            <div id="descriptionTemplatesBuilder" class="d-grid gap-3"></div>
          </div>
        </div>

        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Definicja kolumn (drag & drop)</h3>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-sm btn-outline-secondary" id="addPresetColumnBtn">Dodaj przyklad</button>
              <button type="button" class="btn btn-sm btn-outline-primary" id="addColumnBtn">Dodaj kolumne</button>
            </div>
          </div>
          <div class="card-body">
            <div class="small text-secondary mb-2 csv-builder-intro">
              Przeciagnij wiersze za uchwyt, aby zmienic kolejnosc. Dla funkcji obliczanych uzywaj tokenow typu
              <code>field:product.price_gross</code> lub <code>field:product.category_name</code>.
            </div>
            <div class="alert alert-info py-2 px-3 small">
              Dla parametrow Allegro wpisuj w JSON dokladnie klucz z listy, np. <code>field:product.allegro_parameter.11748</code>.
              Na liscie pola pokazujemy nazwe parametru, jego ID i kategorie, z ktorych pochodzi.
            </div>
            <div id="columnsBuilder" class="d-grid gap-2"></div>
          </div>
        </div>

        <div class="card mb-4">
          <div class="card-header"><h3 class="card-title mb-0">Presety</h3></div>
          <div class="card-body d-flex flex-wrap gap-2">
            {foreach $presets as $presetKey => $preset}
              <a href="{$baseUrl}?controller=csvtemplates&action=create&preset={$presetKey|escape}" class="btn btn-outline-secondary btn-sm">{$preset.name|escape}</a>
            {/foreach}
          </div>
        </div>

        {if $previewCsv}
          <div class="card mb-4 csv-template-preview">
            <div class="card-header"><h3 class="card-title mb-0">Podglad CSV (10 rekordow)</h3></div>
            <div class="card-body">
              <textarea class="form-control" rows="14" readonly>{$previewCsv|escape}</textarea>
            </div>
          </div>
        {/if}

        <div class="d-flex justify-content-between align-items-center flex-wrap csv-template-actions">
          <a href="{$baseUrl}?controller=csvtemplates&action=index" class="btn btn-outline-secondary">Wroc do listy</a>
          <div class="d-flex gap-2 flex-wrap">
            <a href="{$baseUrl}?controller=csvtemplates&action=titlegenerator" class="btn btn-outline-secondary">Generator tytulow</a>
            <button type="submit" formaction="{$baseUrl}?controller=csvtemplates&action=preview" class="btn btn-outline-dark">Podglad CSV</button>
            <button type="submit" class="btn btn-primary">Zapisz szablon</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</main>

<script>
(function () {
  var baseAvailableFields = {$availableFieldsJson nofilter};
  var availableFields = Object.assign({}, baseAvailableFields);
  var availableFunctions = {$availableFunctionsJson nofilter};
  var initialColumns = {$templateColumnsJson nofilter};
  var initialDescriptionTemplates = {$descriptionTemplatesJson nofilter};
  var descriptionImageSources = {$descriptionImageSourcesJson nofilter};

  function debounce(fn, wait) {
    var timeoutId = null;
    return function () {
      var context = this;
      var args = arguments;
      window.clearTimeout(timeoutId);
      timeoutId = window.setTimeout(function () {
        fn.apply(context, args);
      }, wait);
    };
  }

  var descTemplateCounter = 0;
  var builder = document.getElementById('columnsBuilder');
  var descriptionBuilder = document.getElementById('descriptionTemplatesBuilder');
  var addButton = document.getElementById('addColumnBtn');
  var addPresetButton = document.getElementById('addPresetColumnBtn');
  var addDescriptionTemplateButton = document.getElementById('addDescriptionTemplateBtn');
  var form = document.getElementById('csv-template-form');
  var payloadInput = document.getElementById('columnsPayload');
  var descriptionPayloadInput = document.getElementById('descriptionTemplatesPayload');
  var currentDrag = null;
  var currentDragType = '';
  var currentDragPlaceholder = null;
  var armedDragItem = null;

  if (!builder || !descriptionBuilder || !addButton || !form || !payloadInput || !descriptionPayloadInput) {
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

  function clearDragIndicators(scope) {
    var root = scope || document;
    var marked = root.querySelectorAll('.drag-insert-before, .drag-insert-after');
    for (var i = 0; i < marked.length; i++) {
      marked[i].classList.remove('drag-insert-before', 'drag-insert-after');
    }
  }

  function ensureDragPlaceholder(parent, referenceNode, height) {
    if (!parent) {
      return null;
    }

    if (!currentDragPlaceholder) {
      currentDragPlaceholder = document.createElement('div');
      currentDragPlaceholder.className = 'drag-placeholder';
    }

    var normalizedHeight = 28;
    if (height && height > 0) {
      normalizedHeight = Math.max(24, Math.min(42, Math.round(height * 0.16)));
    }

    currentDragPlaceholder.style.height = normalizedHeight + 'px';
    if (referenceNode) {
      parent.insertBefore(currentDragPlaceholder, referenceNode);
    } else {
      parent.appendChild(currentDragPlaceholder);
    }

    return currentDragPlaceholder;
  }

  function removeDragPlaceholder() {
    if (currentDragPlaceholder && currentDragPlaceholder.parentNode) {
      currentDragPlaceholder.parentNode.removeChild(currentDragPlaceholder);
    }
  }

  function startDragItem(item, dragType) {
    currentDrag = item;
    currentDragType = dragType || '';
    item.classList.add('is-dragging');
    item.dataset.dragging = '1';
  }

  function endDragItem() {
    var activeDragged = document.querySelectorAll('.is-dragging, [data-dragging="1"]');
    for (var i = 0; i < activeDragged.length; i++) {
      activeDragged[i].classList.remove('is-dragging');
      activeDragged[i].dataset.dragging = '0';
    }

    currentDrag = null;
    currentDragType = '';
    armedDragItem = null;
    clearDragIndicators(document);
    removeDragPlaceholder();
  }

  function forceEndDragItemSoon() {
    window.setTimeout(function () {
      endDragItem();
    }, 0);
  }

  function computeInsertPosition(event, target) {
    var rect = target.getBoundingClientRect();
    var offset = event.clientY - rect.top;
    return offset < (rect.height / 2) ? 'before' : 'after';
  }

  function moveDraggedItem(parent, target, position) {
    if (!currentDrag || !parent || !target || currentDrag === target) {
      return;
    }

    if (position === 'before') {
      parent.insertBefore(currentDrag, target);
      return;
    }

    if (target.nextSibling) {
      parent.insertBefore(currentDrag, target.nextSibling);
    } else {
      parent.appendChild(currentDrag);
    }
  }

  function armDragFromHandle(card) {
    armedDragItem = card || null;
  }

  function canStartDrag(card) {
    return armedDragItem === card;
  }

  function descriptionTextScopeFromNode(node) {
    if (!node || !node.closest) {
      return null;
    }

    return node.closest('.desc-left-text, .desc-right-text, .desc-single-text');
  }

  function descriptionActiveEditorFromNode(node) {
    var scope = descriptionTextScopeFromNode(node);
    return scope ? scope.querySelector('.desc-visual-editor') : null;
  }

  function fieldOptions(selected) {
    var html = '<option value="">Wybierz pole</option>';
    Object.keys(availableFields).forEach(function (key) {
      var isSelected = String(selected || '') === key ? ' selected' : '';
      var label = tokenSearchFieldLabel(key, availableFields[key]);
      var title = fieldOptionTitle(key, availableFields[key]);
      html += '<option value="' + escapeHtml(key) + '"' + isSelected + ' title="' + escapeHtml(title) + '">' + escapeHtml(label) + '</option>';
    });
    return html;
  }

  function fieldInsertOptions() {
    var html = '<option value="">Wybierz pole do wstawienia</option>';
    Object.keys(availableFields).forEach(function (key) {
      var label = tokenSearchFieldLabel(key, availableFields[key]);
      var title = fieldOptionTitle(key, availableFields[key]);
      html += '<option value="' + escapeHtml(key) + '" title="' + escapeHtml(title) + '">' + escapeHtml(label) + '</option>';
    });
    return html;
  }

  function compactFieldLabel(key, label) {
    var fieldKey = String(key || '');
    var fieldLabel = String(label || fieldKey);
    var allegroMatch = fieldKey.match(/^product\.allegro_parameter\.([^.]+)$/);
    var empikMatch = fieldKey.match(/^product\.empik_parameter\.([^.]+)$/);
    var mediamarktMatch = fieldKey.match(/^product\.mediamarkt_parameter\.([^.]+)$/);

    if (allegroMatch || empikMatch || mediamarktMatch) {
      var marketplace = allegroMatch ? 'Allegro' : (empikMatch ? 'Empik' : 'MediaMarkt');
      var parameterId = allegroMatch ? allegroMatch[1] : (empikMatch ? empikMatch[1] : mediamarktMatch[1]);
      var name = fieldLabel
        .replace(/^Allegro:\s*/i, '')
        .replace(/^Empik:\s*/i, '')
        .replace(/^MediaMarkt:\s*/i, '')
        .replace(/\s*\[[^\]]+\].*$/, '')
        .replace(/\s*\|\s*Kategorie:.*$/, '')
        .trim();

      if (!name || name === parameterId) {
        name = 'parametr';
      }

      return marketplace + ' ' + name + ' [' + parameterId + ']';
    }

    return fieldLabel;
  }

  function fieldOptionTitle(key, label) {
    return String(label || key || '') + ' | {ldelim}{ldelim}field:' + String(key || '') + '{rdelim}{rdelim}';
  }

  function tokenSearchFieldLabel(key, label) {
    var compactLabel = compactFieldLabel(key, label);
    var fieldLabel = String(label || '');
    var categoriesMatch = fieldLabel.match(/\|\s*Kategorie:\s*(.+)$/i);
    if (categoriesMatch && categoriesMatch[1]) {
      return compactLabel + ' | Kategorie: ' + categoriesMatch[1];
    }

    return compactLabel;
  }

  function functionOptions(selected) {
    var html = '';
    Object.keys(availableFunctions).forEach(function (key) {
      var isSelected = String(selected || 'concat') === key ? ' selected' : '';
      html += '<option value="' + escapeHtml(key) + '"' + isSelected + '>' + escapeHtml(availableFunctions[key]) + '</option>';
    });
    return html;
  }

  function slugifyDescriptionKey(value) {
    return String(value || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '');
  }

  function descriptionImageSourceOptions(selected) {
    var html = '<option value="">Wybierz zrodlo</option>';
    Object.keys(descriptionImageSources || {}).forEach(function (key) {
      var isSelected = String(selected || '') === key ? ' selected' : '';
      html += '<option value="' + escapeHtml(key) + '"' + isSelected + '>' + escapeHtml(descriptionImageSources[key]) + '</option>';
    });
    return html;
  }

  function descriptionSectionLayoutOptions(selected) {
    var options = {
      image: 'Samo zdjecie',
      image_image: 'Zdjecie + zdjecie',
      image_text: 'Zdjecie + tekst',
      text_image: 'Tekst + zdjecie',
      text: 'Tekst',
      text_text: 'Tekst + tekst',
      html: 'Wlasny kod HTML'
    };
    var html = '';
    Object.keys(options).forEach(function (key) {
      var isSelected = String(selected || 'text') === key ? ' selected' : '';
      html += '<option value="' + escapeHtml(key) + '"' + isSelected + '>' + escapeHtml(options[key]) + '</option>';
    });
    return html;
  }

  function descriptionTokenOptions() {
    var html = '<option value="">Wybierz token</option>';
    html += '<option value="{ldelim}{ldelim}option:collection_name{rdelim}{rdelim}">Opcja: Kolekcja wpisana przy eksporcie</option>';
    html += '<option value="{ldelim}{ldelim}option:price_to_csv{rdelim}{rdelim}">Opcja: Cena wpisana przy eksporcie</option>';
    Object.keys(availableFields).forEach(function (key) {
      var token = '{ldelim}{ldelim}field:' + key + '{rdelim}{rdelim}';
      var label = tokenSearchFieldLabel(key, availableFields[key]);
      var title = fieldOptionTitle(key, availableFields[key]);
      html += '<option value="' + escapeHtml(token) + '" title="' + escapeHtml(title) + '">' + escapeHtml(label) + '</option>';
    });
    return html;
  }

  function descriptionTokenRegex(flags) {
    var open = String.fromCharCode(123) + String.fromCharCode(123);
    var close = String.fromCharCode(125) + String.fromCharCode(125);
    return new RegExp(open + '\\s*(field|option):\\s*([^' + String.fromCharCode(125) + ']+)' + close, flags || '');
  }

  function descriptionTokenLabel(token) {
    var value = String(token || '');
    var match = value.match(descriptionTokenRegex('i'));
    if (!match) {
      return value;
    }

    var kind = String(match[1] || '').toLowerCase();
    var key = String(match[2] || '').trim();
    if (kind === 'field') {
      return compactFieldLabel(key, availableFields[key] || key);
    }

    var optionLabels = {
      collection_name: 'Opcja Kolekcja',
      price_to_csv: 'Opcja Cena'
    };

    return optionLabels[key] || ('Opcja ' + key);
  }

  function descriptionTokenPillHtml(token) {
    return '<span class="desc-token-pill" contenteditable="false" data-token="' + escapeHtml(token) + '">' + escapeHtml(descriptionTokenLabel(token)) + '</span>';
  }

  function descriptionEditorHtmlFromValue(value) {
    var html = escapeHtml(value || '');
    html = html.replace(/&lt;\s*(\/?)\s*(h1|h2|h3|strong|b|em|i|u)\s*&gt;/gi, '<$1$2>');
    html = html.replace(/&lt;\s*br\s*\/?\s*&gt;/gi, '<br>');
    html = html.replace(/&lt;\s*p\s*&gt;/gi, '<br>');
    html = html.replace(/&lt;\s*\/\s*p\s*&gt;/gi, '');
    html = html.replace(/&lt;\s*div\s*&gt;/gi, '<br>');
    html = html.replace(/&lt;\s*\/\s*div\s*&gt;/gi, '');
    html = html.replace(/\r?\n/g, '<br>');
    html = html.replace(descriptionTokenRegex('gi'), function (token) {
      return descriptionTokenPillHtml(token);
    });
    return html || '<br>';
  }

  function descriptionEditorValue(editor) {
    if (!editor) {
      return '';
    }

    var clone = editor.cloneNode(true);
    var pills = clone.querySelectorAll('.desc-token-pill');
    for (var index = 0; index < pills.length; index++) {
      pills[index].parentNode.replaceChild(document.createTextNode(pills[index].getAttribute('data-token') || pills[index].textContent || ''), pills[index]);
    }

    return String(clone.innerHTML || '')
      .replace(/<div><br><\/div>/gi, '<br>')
      .replace(/<p><br><\/p>/gi, '<br>')
      .replace(/<div>/gi, '<br>')
      .replace(/<\/div>/gi, '')
      .replace(/<p>/gi, '<br>')
      .replace(/<\/p>/gi, '')
      .replace(/&nbsp;/gi, ' ')
      .trim();
  }

  function syncDescriptionEditor(scope) {
    if (!scope) {
      return;
    }

    var editor = scope.querySelector('.desc-visual-editor');
    var textarea = scope.querySelector('.desc-text-input');
    if (editor && textarea) {
      textarea.value = descriptionEditorValue(editor);
    }
  }

  function syncAllDescriptionEditors(root) {
    var editors = (root || document).querySelectorAll('.desc-visual-editor');
    for (var index = 0; index < editors.length; index++) {
      syncDescriptionEditor(descriptionTextScopeFromNode(editors[index]));
    }
  }

  function focusDescriptionEditor(editor) {
    if (!editor) {
      return;
    }

    editor.focus();
  }

  function insertHtmlIntoDescriptionEditor(editor, html) {
    if (!editor) {
      return;
    }

    focusDescriptionEditor(editor);
    document.execCommand('insertHTML', false, html);
    syncDescriptionEditor(descriptionTextScopeFromNode(editor));
    refreshDescriptionFormatState(editor);
  }

  function closestEditorBlock(node, editor) {
    var current = node && node.nodeType === 3 ? node.parentNode : node;
    while (current && current !== editor) {
      var tagName = current.tagName ? current.tagName.toLowerCase() : '';
      if (tagName === 'h1' || tagName === 'h2' || tagName === 'h3') {
        return tagName;
      }
      current = current.parentNode;
    }

    return '';
  }

  function currentDescriptionFormat(editor) {
    if (!editor) {
      return { block: '', bold: false, italic: false, underline: false };
    }

    var selection = window.getSelection ? window.getSelection() : null;
    var node = selection && selection.rangeCount ? selection.anchorNode : null;

    return {
      block: closestEditorBlock(node, editor),
      bold: document.queryCommandState ? document.queryCommandState('bold') : false,
      italic: document.queryCommandState ? document.queryCommandState('italic') : false,
      underline: document.queryCommandState ? document.queryCommandState('underline') : false
    };
  }

  function refreshDescriptionFormatState(editor) {
    var scope = descriptionTextScopeFromNode(editor);
    if (!scope) {
      return;
    }

    var state = currentDescriptionFormat(editor);
    var buttons = scope.querySelectorAll('.desc-format-button');
    for (var index = 0; index < buttons.length; index++) {
      var format = String(buttons[index].getAttribute('data-format') || '');
      var active = false;
      if (format === 'bold') {
        active = state.bold;
      } else if (format === 'italic') {
        active = state.italic;
      } else if (format === 'underline') {
        active = state.underline;
      } else {
        active = state.block === format;
      }
      buttons[index].classList.toggle('is-active', active);
    }
  }

  function applyDescriptionFormat(button, editor) {
    if (!button || !editor) {
      return;
    }

    focusDescriptionEditor(editor);
    var format = String(button.getAttribute('data-format') || '');
    if (format === 'bold') {
      document.execCommand('bold', false, null);
    } else if (format === 'italic') {
      document.execCommand('italic', false, null);
    } else if (format === 'underline') {
      document.execCommand('underline', false, null);
    } else if (format === 'h1' || format === 'h2' || format === 'h3') {
      var state = currentDescriptionFormat(editor);
      document.execCommand('formatBlock', false, state.block === format ? 'div' : '<' + format + '>');
    }

    syncDescriptionEditor(descriptionTextScopeFromNode(editor));
    refreshDescriptionFormatState(editor);
  }

  document.addEventListener('selectionchange', function () {
    var selection = window.getSelection ? window.getSelection() : null;
    if (!selection || !selection.rangeCount) {
      return;
    }

    var node = selection.anchorNode;
    var editor = node && node.nodeType === 3 ? node.parentNode : node;
    if (editor && editor.closest) {
      editor = editor.closest('.desc-visual-editor');
    }

    if (editor && descriptionBuilder && descriptionBuilder.contains(editor)) {
      refreshDescriptionFormatState(editor);
    }
  });

  function mappingRowsHtml(mappings) {
    if (!Array.isArray(mappings) || mappings.length === 0) {
      mappings = [{ from_value: '', to_value: '' }];
    }

    return mappings.map(function (map) {
      return '<div class="row g-2 mapping-row mb-1">'
        + '<div class="col"><input type="text" class="form-control form-control-sm map-from" placeholder="from" value="' + escapeHtml(map.from_value || '') + '"></div>'
        + '<div class="col"><input type="text" class="form-control form-control-sm map-to" placeholder="to" value="' + escapeHtml(map.to_value || '') + '"></div>'
        + '<div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger remove-mapping">x</button></div>'
        + '</div>';
    }).join('');
  }

  function imageLayoutRowsHtml(layout) {
    if (!Array.isArray(layout) || layout.length === 0) {
      layout = [
        { type: 'thumbnail', value: '' },
        { type: 'mockup', value: '' },
        { type: 'image', value: '' }
      ];
    }

    return layout.map(function (item) {
      var type = String(item && item.type !== undefined && item.type !== null ? item.type : 'static');
      var value = String(item && item.value !== undefined && item.value !== null ? item.value : '');
      var staticStyle = type === 'static' ? '' : ' style="display:none;"';
      return '<div class="border rounded p-2 mb-2 bg-light image-layout-item" draggable="true">'
        + '<div class="d-flex justify-content-between align-items-center gap-2">'
        + '<span class="text-secondary small js-drag-handle" style="cursor:move;">:: przeciagnij</span>'
        + '<button type="button" class="btn btn-sm btn-outline-danger remove-image-layout-item">Usun</button>'
        + '</div>'
        + '<div class="row g-2 mt-1">'
        + '<div class="col-md-4"><label class="form-label small">Typ sekcji</label><select class="form-select form-select-sm image-layout-type">'
        + '<option value="thumbnail"' + (type === 'thumbnail' ? ' selected' : '') + '>Miniatura</option>'
        + '<option value="mockup"' + (type === 'mockup' ? ' selected' : '') + '>Mockupy / gridy</option>'
        + '<option value="image"' + (type === 'image' ? ' selected' : '') + '>Zdjecia</option>'
        + '<option value="static"' + (type === 'static' ? ' selected' : '') + '>Stala wartosc</option>'
        + '</select></div>'
        + '<div class="col-md-8 image-layout-static-wrap"' + staticStyle + '><label class="form-label small">Stala wartosc</label><input type="text" class="form-control form-control-sm image-layout-value" value="' + escapeHtml(value) + '" placeholder="np. ---"></div>'
        + '</div>'
        + '</div>';
    }).join('');
  }

  function descriptionImagePickerHtml(slot, label) {
    var imageSlot = slot || {};
    var sourceValue = imageSlot.source || 'IMG_EU';
    var imageIndex = parseInt(imageSlot.index || 1, 10);
    if (!imageIndex || imageIndex < 1) {
      imageIndex = 1;
    }
    return '<div class="row g-2">'
      + '<div class="col-12"><div class="small fw-semibold mb-1">' + escapeHtml(label || 'Obraz') + '</div></div>'
      + '<div class="col-lg-7"><label class="form-label small">Zrodlo obrazu</label><select class="form-select form-select-sm desc-image-source">' + descriptionImageSourceOptions(sourceValue) + '</select></div>'
      + '<div class="col-lg-5">'
      + '<label class="form-label small desc-image-index-label">ZDJECIE ' + escapeHtml(imageIndex) + '</label>'
      + '<input type="number" min="1" step="1" class="form-control form-control-lg fw-bold text-center desc-image-index" value="' + escapeHtml(imageIndex) + '" style="font-size:1.4rem;min-height:58px;">'
      + '</div>'
      + '</div>';
  }

  function descriptionTextEditorHtml(value, placeholder, label) {
    return '<div class="small fw-semibold mb-1">' + escapeHtml(label || 'Tekst') + '</div>'
      + '<div class="desc-editor-shell">'
      + '<div class="desc-format-toolbar" aria-label="Formatowanie tekstu">'
      + '<button type="button" class="btn btn-sm btn-outline-secondary desc-format-button" data-format="h1">H1</button>'
      + '<button type="button" class="btn btn-sm btn-outline-secondary desc-format-button" data-format="h2">H2</button>'
      + '<button type="button" class="btn btn-sm btn-outline-secondary desc-format-button" data-format="h3">H3</button>'
      + '<button type="button" class="btn btn-sm btn-outline-secondary desc-format-button fw-bold" data-format="bold">B</button>'
      + '<button type="button" class="btn btn-sm btn-outline-secondary desc-format-button fst-italic" data-format="italic">I</button>'
      + '<button type="button" class="btn btn-sm btn-outline-secondary desc-format-button text-decoration-underline" data-format="underline">U</button>'
      + '<button type="button" class="btn btn-sm btn-outline-secondary desc-source-toggle ms-auto">Kod HTML</button>'
      + '</div>'
      + '<div class="desc-visual-editor" contenteditable="true" data-placeholder="' + escapeHtml(placeholder || 'Wpisz tekst lub tokeny') + '">' + descriptionEditorHtmlFromValue(value || '') + '</div>'
      + '<textarea class="form-control form-control-sm desc-text-input" rows="8" placeholder="Wklej tutaj wlasny kod HTML">' + escapeHtml(value || '') + '</textarea>'
      + '<div class="border rounded bg-light-subtle desc-token-tools">'
      + '<div class="small fw-semibold mb-1">Wstaw token</div>'
      + '<div class="row g-2 align-items-end">'
      + '<div class="col-lg-4"><label class="form-label small mb-1">Wyszukiwarka</label><input type="text" class="form-control form-control-sm desc-token-search" placeholder="Szukaj np. sku, allegro, opis"></div>'
      + '<div class="col-lg-6"><label class="form-label small mb-1">Pole / opcja</label><select class="form-select form-select-sm desc-token-select">' + descriptionTokenOptions() + '</select></div>'
      + '<div class="col-lg-2 d-grid"><button type="button" class="btn btn-sm btn-outline-secondary desc-insert-token">Wstaw</button></div>'
      + '</div>'
      + '</div>'
      + '</div>'
      + '<div class="form-text">Formatowanie zapisuje bezpieczne tagi HTML. Tokeny: <code>{ldelim}{ldelim}field:product.product_name{rdelim}{rdelim}</code>, <code>{ldelim}{ldelim}field:product.sku{rdelim}{rdelim}</code>, <code>{ldelim}{ldelim}option:collection_name{rdelim}{rdelim}</code>.</div>';
  }

  function conditionRowsHtml(conditions) {
    if (!Array.isArray(conditions) || conditions.length === 0) {
      conditions = [{ field: '', operator: 'contains', value: '', then: '' }];
    }
    var operators = [
      ['contains', 'zawiera'], ['eq', 'równa się'], ['neq', 'nie równa się'],
      ['gt', '>'], ['gte', '>='], ['lt', '<'], ['lte', '<=']
    ];
    return conditions.map(function (condition) {
      var selectedOperator = condition.operator || 'contains';
      var operatorHtml = operators.map(function (operator) {
        return '<option value="' + operator[0] + '"' + (operator[0] === selectedOperator ? ' selected' : '') + '>' + operator[1] + '</option>';
      }).join('');
      return '<div class="row g-2 condition-row mb-2">'
        + '<div class="col-md-3"><select class="form-select form-select-sm cond-op">' + operatorHtml + '</select></div>'
        + '<div class="col-md-4"><input type="text" class="form-control form-control-sm cond-value" placeholder="szukana wartość, np. czarny" value="' + escapeHtml(condition.value || '') + '"></div>'
        + '<div class="col-md-4"><input type="text" class="form-control form-control-sm cond-then" placeholder="wynik, np. Czarna" value="' + escapeHtml(condition.then || '') + '"></div>'
        + '<div class="col-md-1 d-grid"><button type="button" class="btn btn-sm btn-outline-danger remove-condition" title="Usuń warunek">×</button></div>'
        + '</div>';
    }).join('');
  }

  function descriptionImageLabelToSource(label) {
    var found = null;
    Object.keys(descriptionImageSources || {}).forEach(function (key) {
      if (!found && String(descriptionImageSources[key]).trim() === String(label).trim()) {
        found = key;
      }
    });
    return found;
  }

  function parseDescSlotFromCellHtml(cellHtml) {
    var trimmed = String(cellHtml || '').trim();
    if (/^<img\b[^>]*>$/i.test(trimmed)) {
      var srcMatch = trimmed.match(/src\s*=\s*["']\[ZDJECIE:\s*(.+?)\s*nr\s*(\d+)\]["']/i);
      if (srcMatch) {
        var label = srcMatch[1].trim();
        var index = parseInt(srcMatch[2], 10) || 1;
        return { type: 'image', source: descriptionImageLabelToSource(label) || 'IMG_EU', index: index };
      }
    }
    return { type: 'text', html: trimmed };
  }

  function parseDescriptionHtmlIntoSections(rawHtml) {
    var container = document.createElement('div');
    container.innerHTML = String(rawHtml || '');
    Array.prototype.forEach.call(container.querySelectorAll('style'), function (styleEl) {
      styleEl.remove();
    });

    var defaultImage = { source: 'IMG_EU', index: 1 };
    var sections = [];

    Array.prototype.forEach.call(container.children, function (node) {
      var row = node.tagName === 'TABLE' ? node.querySelector('tr') : null;
      if (row) {
        var cells = row.children;
        if (cells.length === 2) {
          var left = parseDescSlotFromCellHtml(cells[0].innerHTML);
          var right = parseDescSlotFromCellHtml(cells[1].innerHTML);
          var layout = 'text_text';
          if (left.type === 'image' && right.type === 'image') layout = 'image_image';
          else if (left.type === 'image' && right.type === 'text') layout = 'image_text';
          else if (left.type === 'text' && right.type === 'image') layout = 'text_image';

          sections.push({
            layout: layout,
            left_text: left.type === 'text' ? left.html : '',
            right_text: right.type === 'text' ? right.html : '',
            left_image: left.type === 'image' ? { source: left.source, index: left.index } : defaultImage,
            right_image: right.type === 'image' ? { source: right.source, index: right.index } : defaultImage
          });
          return;
        }
        if (cells.length === 1) {
          var only = parseDescSlotFromCellHtml(cells[0].innerHTML);
          if (only.type === 'image') {
            sections.push({ layout: 'image', left_image: { source: only.source, index: only.index }, right_image: defaultImage });
          } else {
            sections.push({ layout: 'text', text: only.html, left_image: defaultImage, right_image: defaultImage });
          }
          return;
        }
      }

      var outer = node.outerHTML || '';
      if (outer.trim()) {
        sections.push({ layout: 'html', html: outer, left_image: defaultImage, right_image: defaultImage });
      }
    });

    return sections;
  }

  function createDescriptionSectionCard(section) {
    var data = section || {
      layout: 'text',
      text: '',
      left_text: '',
      right_text: '',
      html: '',
      left_image: { source: 'IMG_EU', index: 1 },
      right_image: { source: 'IMG_EU', index: 1 }
    };

    var card = document.createElement('div');
    card.className = 'border rounded p-3 bg-light-subtle description-section-card';
    card.draggable = true;
    card.innerHTML = ''
      + '<div class="description-section-header">'
      + '<div class="description-section-title"><span class="text-secondary small js-drag-handle">Przeciagnij sekcje</span></div>'
      + '<div class="description-section-controls">'
      + '<select class="form-select form-select-sm desc-layout">' + descriptionSectionLayoutOptions(data.layout || 'text') + '</select>'
      + '<button type="button" class="btn btn-sm btn-outline-danger remove-description-section">Usun sekcje</button>'
      + '</div>'
      + '</div>'
      + '<div class="description-section-body">'
      + '<div class="row g-2">'
      + '  <div class="col-12 col-lg-6 desc-left-image"><div class="description-section-pane h-100">' + descriptionImagePickerHtml(data.left_image || {}, 'Lewy obraz') + '</div></div>'
      + '  <div class="col-12 col-lg-6 desc-right-image"><div class="description-section-pane h-100">' + descriptionImagePickerHtml(data.right_image || {}, 'Prawy obraz') + '</div></div>'
      + '  <div class="col-12 col-lg-6 desc-left-text"><div class="description-section-pane h-100">' + descriptionTextEditorHtml(data.left_text || '', 'Lewy tekst', 'Lewy tekst') + '</div></div>'
      + '  <div class="col-12 col-lg-6 desc-right-text"><div class="description-section-pane h-100">' + descriptionTextEditorHtml(data.right_text || '', 'Prawy tekst', 'Prawy tekst') + '</div></div>'
      + '  <div class="col-12 desc-single-text"><div class="description-section-pane h-100">' + descriptionTextEditorHtml(data.text || '', 'Tresci sekcji', 'Tekst') + '</div></div>'
      + '  <div class="col-12 desc-raw-html"><div class="description-section-pane h-100">'
      + '    <div class="small fw-semibold mb-1">Wlasny kod HTML (wklejony 1:1, bez zadnych zmian)</div>'
      + '    <textarea class="form-control form-control-sm desc-html-raw" rows="12" placeholder="Wklej tutaj gotowy kod HTML sekcji" style="font-family: SFMono-Regular, Consolas, \'Liberation Mono\', Menlo, monospace;">' + escapeHtml(data.html || '') + '</textarea>'
      + '    <div class="form-text">Tokeny: <code>{ldelim}{ldelim}field:product.product_name{rdelim}{rdelim}</code>, <code>{ldelim}{ldelim}field:product.sku{rdelim}{rdelim}</code>, <code>{ldelim}{ldelim}option:collection_name{rdelim}{rdelim}</code>. Kod nie jest filtrowany - wstawiany jest dokladnie tak, jak zostal wklejony.</div>'
      + '  </div></div>'
      + '</div>'
      + '</div>';

    function updateVisibility() {
      var layout = card.querySelector('.desc-layout').value;
      var leftImage = card.querySelector('.desc-left-image');
      var rightImage = card.querySelector('.desc-right-image');
      var leftText = card.querySelector('.desc-left-text');
      var rightText = card.querySelector('.desc-right-text');
      var singleText = card.querySelector('.desc-single-text');
      var rawHtml = card.querySelector('.desc-raw-html');

      leftImage.style.display = (layout === 'image' || layout === 'image_image' || layout === 'image_text') ? '' : 'none';
      rightImage.style.display = (layout === 'image_image' || layout === 'text_image') ? '' : 'none';
      leftText.style.display = (layout === 'text_image' || layout === 'text_text') ? '' : 'none';
      rightText.style.display = (layout === 'image_text' || layout === 'text_text') ? '' : 'none';
      singleText.style.display = layout === 'text' ? '' : 'none';
      rawHtml.style.display = layout === 'html' ? '' : 'none';

      leftImage.style.order = '1';
      leftImage.className = layout === 'image' ? 'col-12 desc-left-image' : 'col-12 col-lg-6 desc-left-image';
      leftText.style.order = '1';
      rightImage.style.order = '2';
      rightText.style.order = '2';
      singleText.style.order = '1';
    }

    card.querySelector('.desc-layout').addEventListener('change', function () {
      updateVisibility();
      refreshAvailableFieldsFromDescriptions();
    });
    card.querySelector('.remove-description-section').addEventListener('click', function () {
      card.remove();
      refreshAvailableFieldsFromDescriptions();
    });
    card.addEventListener('input', function (event) {
      if (!event.target.classList.contains('desc-image-index')) {
        return;
      }

      var value = parseInt(event.target.value || 1, 10);
      if (!value || value < 1) {
        value = 1;
      }

      var scope = event.target.closest('.col-lg-5, .col-md-4, .col-12, .col-lg-6');
      var label = scope ? scope.querySelector('.desc-image-index-label') : null;
      if (label) {
        label.textContent = 'ZDJECIE ' + value;
      }
    });
    card.addEventListener('input', function (event) {
      if (event.target.classList.contains('desc-visual-editor')) {
        syncDescriptionEditor(descriptionTextScopeFromNode(event.target));
        refreshDescriptionFormatState(event.target);
        return;
      }

      if (event.target.classList.contains('desc-token-search')) {
        return;
      }

      refreshAvailableFieldsFromDescriptionsDebounced();
    });
    card.addEventListener('change', function (event) {
      if (event.target.classList.contains('desc-token-search')) {
        return;
      }

      refreshAvailableFieldsFromDescriptions();
    });
    card.addEventListener('input', function (event) {
      if (!event.target.classList.contains('desc-token-search')) {
        return;
      }

      var scope = descriptionTextScopeFromNode(event.target);
      var select = scope ? scope.querySelector('.desc-token-select') : null;
      if (!select) {
        return;
      }

      var query = String(event.target.value || '').toLowerCase().trim();
      var options = select.querySelectorAll('option');
      for (var optionIndex = 0; optionIndex < options.length; optionIndex++) {
        if (optionIndex === 0) {
          options[optionIndex].hidden = false;
          continue;
        }

        var haystack = String(options[optionIndex].textContent || '').toLowerCase();
        options[optionIndex].hidden = query !== '' && haystack.indexOf(query) === -1;
      }
    });
    card.addEventListener('mousedown', function (event) {
      if (event.target.classList.contains('desc-format-button')) {
        event.preventDefault();
      }
    });
    card.addEventListener('mouseup', function (event) {
      var editor = descriptionActiveEditorFromNode(event.target);
      if (editor) {
        refreshDescriptionFormatState(editor);
      }
    });
    card.addEventListener('click', function (event) {
      var scope = descriptionTextScopeFromNode(event.target);
      var editor = scope ? scope.querySelector('.desc-visual-editor') : null;

      if (event.target.classList.contains('desc-format-button')) {
        if (!editor) {
          return;
        }

        applyDescriptionFormat(event.target, editor);
        refreshAvailableFieldsFromDescriptions();
        return;
      }

      if (event.target.classList.contains('desc-insert-token')) {
        var select = scope ? scope.querySelector('.desc-token-select') : null;
        if (!editor || !select || !select.value) {
          return;
        }

        insertHtmlIntoDescriptionEditor(editor, descriptionTokenPillHtml(String(select.value || '')) + '&nbsp;');
        refreshAvailableFieldsFromDescriptions();
        return;
      }

      if (event.target.classList.contains('desc-source-toggle')) {
        var shell = event.target.closest('.desc-editor-shell');
        var textInput = shell ? shell.querySelector('.desc-text-input') : null;
        if (!shell || !editor || !textInput) {
          return;
        }

        var enteringSourceMode = !shell.classList.contains('is-source-mode');
        if (enteringSourceMode) {
          syncDescriptionEditor(scope);
          shell.classList.add('is-source-mode');
          event.target.classList.add('is-active');
          event.target.textContent = 'Edytor wizualny';
          textInput.focus();
        } else {
          editor.innerHTML = descriptionEditorHtmlFromValue(textInput.value);
          shell.classList.remove('is-source-mode');
          event.target.classList.remove('is-active');
          event.target.textContent = 'Kod HTML';
          refreshAvailableFieldsFromDescriptions();
        }
      }
    });
    var dragHandle = card.querySelector('.js-drag-handle');
    if (dragHandle) {
      dragHandle.addEventListener('mousedown', function () {
        armDragFromHandle(card);
      });
      dragHandle.addEventListener('touchstart', function () {
        armDragFromHandle(card);
      }, { passive: true });
    }

    card.addEventListener('dragstart', function (event) {
      if (!canStartDrag(card)) {
        event.preventDefault();
        return;
      }

      startDragItem(card, 'description-section');
      ensureDragPlaceholder(card.parentNode, card.nextSibling, card.offsetHeight);
      event.stopPropagation();
    });
    card.addEventListener('dragend', function (event) {
      endDragItem();
      event.stopPropagation();
    });
    card.addEventListener('dragover', function (event) {
      event.preventDefault();
      if (currentDragType !== 'description-section' || !currentDrag || currentDrag === card || !card.parentNode) {
        return;
      }

      clearDragIndicators(card.parentNode);
      var position = computeInsertPosition(event, card);
      card.classList.add(position === 'before' ? 'drag-insert-before' : 'drag-insert-after');
      ensureDragPlaceholder(card.parentNode, position === 'before' ? card : card.nextSibling, currentDrag.offsetHeight);
      event.stopPropagation();
    });
    card.addEventListener('dragleave', function (event) {
      if (!card.contains(event.relatedTarget)) {
        card.classList.remove('drag-insert-before', 'drag-insert-after');
      }
      event.stopPropagation();
    });
    card.addEventListener('drop', function (event) {
      event.preventDefault();
      var list = card.parentNode;
      if (currentDragType !== 'description-section' || !list || !currentDrag || currentDrag === card) {
        return;
      }

      var position = computeInsertPosition(event, card);
      moveDraggedItem(list, card, position);
      refreshAvailableFieldsFromDescriptions();
      clearDragIndicators(list);
      endDragItem();
      event.stopPropagation();
    });

    updateVisibility();
    return card;
  }

  function descTemplateSingleColumnSection(content) {
    content = String(content || '').trim();
    if (!content) {
      return '';
    }

    return '<table role="presentation" style="width:100%;border-collapse:collapse;margin:0 0 16px 0;"><tr><td style="vertical-align:top;">' + content + '</td></tr></table>';
  }

  function descTemplateTwoColumnSection(leftContent, rightContent) {
    leftContent = String(leftContent || '').trim();
    rightContent = String(rightContent || '').trim();
    if (!leftContent && !rightContent) {
      return '';
    }

    return '<table role="presentation" class="csv-desc-two-col" style="width:100%;border-collapse:collapse;margin:0 0 16px 0;"><tr>'
      + '<td style="width:50%;vertical-align:middle;padding-right:12px;">' + leftContent + '</td>'
      + '<td style="width:50%;vertical-align:middle;padding-left:12px;">' + rightContent + '</td>'
      + '</tr></table>';
  }

  function descTemplateImagePlaceholderHtml(slot) {
    slot = slot || {};
    var source = slot.source || '';
    if (!source) {
      return '';
    }

    var index = parseInt(slot.index || 1, 10) || 1;
    var label = (descriptionImageSources && descriptionImageSources[source]) ? descriptionImageSources[source] : source;

    return '<img src="[ZDJECIE: ' + escapeHtml(label) + ' nr ' + index + ']" alt="" style="display:block;max-width:100%;height:auto;">';
  }

  function buildDescriptionTemplateHtmlCode(sections) {
    var style = '<style>'
      + '@media only screen and (max-width: 640px) {ldelim}'
      + '.csv-desc-two-col,.csv-desc-two-col tbody,.csv-desc-two-col tr,.csv-desc-two-col td{ldelim}display:block!important;width:100%!important;{rdelim}'
      + '.csv-desc-two-col td{ldelim}padding-left:0!important;padding-right:0!important;padding-bottom:12px!important;{rdelim}'
      + '{rdelim}'
      + '</style>';

    var parts = (sections || []).map(function (section) {
      var leftImage = descTemplateImagePlaceholderHtml(section.left_image);
      var rightImage = descTemplateImagePlaceholderHtml(section.right_image);
      var text = section.text || '';
      var leftText = section.left_text || '';
      var rightText = section.right_text || '';

      switch (section.layout) {
        case 'image':
          return descTemplateSingleColumnSection(leftImage);
        case 'image_image':
          return descTemplateTwoColumnSection(leftImage, rightImage);
        case 'image_text':
          return descTemplateTwoColumnSection(leftImage, rightText);
        case 'text_image':
          return descTemplateTwoColumnSection(leftText, rightImage);
        case 'text_text':
          return descTemplateTwoColumnSection(leftText, rightText);
        case 'html':
          return String(section.html || '').trim();
        case 'text':
        default:
          return descTemplateSingleColumnSection(text);
      }
    }).filter(function (html) { return html !== ''; });

    if (!parts.length) {
      return '';
    }

    return style + parts.join('\n');
  }

  function collectDescriptionSectionData(card) {
    var leftSource = card.querySelector('.desc-left-image .desc-image-source');
    var leftIndex = card.querySelector('.desc-left-image .desc-image-index');
    var rightSource = card.querySelector('.desc-right-image .desc-image-source');
    var rightIndex = card.querySelector('.desc-right-image .desc-image-index');
    var leftText = card.querySelector('.desc-left-text .desc-text-input');
    var rightText = card.querySelector('.desc-right-text .desc-text-input');
    var singleText = card.querySelector('.desc-single-text .desc-text-input');
    var rawHtml = card.querySelector('.desc-html-raw');

    return {
      layout: card.querySelector('.desc-layout').value,
      text: singleText ? singleText.value : '',
      left_text: leftText ? leftText.value : '',
      right_text: rightText ? rightText.value : '',
      html: rawHtml ? rawHtml.value : '',
      left_image: {
        source: leftSource ? leftSource.value : '',
        index: leftIndex ? leftIndex.value : 1
      },
      right_image: {
        source: rightSource ? rightSource.value : '',
        index: rightIndex ? rightIndex.value : 1
      }
    };
  }

  function createDescriptionTemplateCard(template) {
    var data = template || { name: '', key: '', sections: [] };
    descTemplateCounter += 1;
    var descTemplateUid = descTemplateCounter;
    var card = document.createElement('div');
    card.className = 'card border description-template-card';
    card.draggable = true;
    card.innerHTML = ''
      + '<div class="card-body">'
      + '  <div class="row g-2 mb-3">'
      + '    <div class="col-md-5"><label class="form-label small">Nazwa opisu</label><input type="text" class="form-control form-control-sm desc-template-name" value="' + escapeHtml(data.name || '') + '" placeholder="np. Opis glowny"></div>'
      + '    <div class="col-md-4"><label class="form-label small">Klucz pola</label><input type="text" class="form-control form-control-sm desc-template-key" value="' + escapeHtml(data.key || '') + '" placeholder="np. opis_glowny"></div>'
      + '    <div class="col-md-3 d-flex align-items-end justify-content-end"><button type="button" class="btn btn-sm btn-outline-danger remove-description-template">Usun opis</button></div>'
      + '  </div>'
      + '  <div class="small text-secondary mb-3">To pole bedzie dostepne jako <span class="desc-template-label-preview">Opis CSV: ' + escapeHtml(data.name || '...') + '</span>.</div>'
      + '  <div class="form-check form-switch mb-3">'
      + '    <input type="checkbox" class="form-check-input desc-html-toggle" id="descHtmlToggle' + descTemplateUid + '">'
      + '    <label class="form-check-label small" for="descHtmlToggle' + descTemplateUid + '">Pokaz / edytuj HTML (caly kod opisu do skopiowania, eksportu lub recznej edycji)</label>'
      + '  </div>'
      + '  <div class="d-flex gap-2 flex-wrap mb-3 desc-sections-controls">'
      + '    <button type="button" class="btn btn-sm btn-outline-secondary add-description-section" data-layout="image">Samo zdjecie</button>'
      + '    <button type="button" class="btn btn-sm btn-outline-secondary add-description-section" data-layout="image_image">Zdjecie + zdjecie</button>'
      + '    <button type="button" class="btn btn-sm btn-outline-secondary add-description-section" data-layout="image_text">Zdjecie + tekst</button>'
      + '    <button type="button" class="btn btn-sm btn-outline-secondary add-description-section" data-layout="text_image">Tekst + zdjecie</button>'
      + '    <button type="button" class="btn btn-sm btn-outline-secondary add-description-section" data-layout="text">Tekst</button>'
      + '    <button type="button" class="btn btn-sm btn-outline-secondary add-description-section" data-layout="text_text">Tekst + tekst</button>'
      + '    <button type="button" class="btn btn-sm btn-outline-primary add-description-section" data-layout="html">Wlasny kod HTML</button>'
      + '  </div>'
      + '  <div class="description-sections-list d-grid gap-2 desc-sections-controls"></div>'
      + '  <div class="border rounded p-2 desc-html-view" style="display:none;">'
      + '    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">'
      + '      <div class="small fw-semibold">Pelny kod HTML tego opisu</div>'
      + '      <div class="d-flex gap-2">'
      + '        <button type="button" class="btn btn-sm btn-outline-secondary copy-desc-html">Kopiuj kod</button>'
      + '        <button type="button" class="btn btn-sm btn-outline-primary apply-desc-html">Zastosuj kod HTML</button>'
      + '      </div>'
      + '    </div>'
      + '    <div class="form-text mb-2">To jest kod szablonu (tokeny <code>{ldelim}{ldelim}field:...{rdelim}{rdelim}</code> nie sa tu jeszcze podstawione realnymi danymi produktu - podstawiaja sie dopiero przy eksporcie CSV). Mozesz edytowac ten kod bezposrednio i kliknac <strong>Zastosuj kod HTML</strong>, aby zamienic sekcje ponizej na Twoj wlasny kod (zastapi to wszystkie obecne sekcje tego opisu).</div>'
      + '    <textarea class="form-control form-control-sm desc-html-code" rows="12" spellcheck="false" style="font-family: SFMono-Regular, Consolas, \'Liberation Mono\', Menlo, monospace;"></textarea>'
      + '  </div>'
      + '</div>';

    var nameInput = card.querySelector('.desc-template-name');
    var keyInput = card.querySelector('.desc-template-key');
    var previewLabel = card.querySelector('.desc-template-label-preview');
    var sectionsList = card.querySelector('.description-sections-list');

    function syncTemplatePresentation() {
      if (!String(keyInput.value || '').trim()) {
        keyInput.value = slugifyDescriptionKey(nameInput.value || '');
      }
      if (previewLabel) {
        previewLabel.textContent = 'Opis CSV: ' + String(nameInput.value || '...');
      }
      refreshAvailableFieldsFromDescriptions();
    }

    if (Array.isArray(data.sections) && data.sections.length) {
      data.sections.forEach(function (section) {
        sectionsList.appendChild(createDescriptionSectionCard(section));
      });
    } else {
      sectionsList.appendChild(createDescriptionSectionCard({ layout: 'text', text: '', left_text: '', right_text: '', left_image: { source: 'IMG_EU', index: 1 }, right_image: { source: 'IMG_EU', index: 1 } }));
    }

    bindContainerDropzone(sectionsList, '.description-section-card', 'description-section', refreshAvailableFieldsFromDescriptions);

    var htmlToggle = card.querySelector('.desc-html-toggle');
    var htmlView = card.querySelector('.desc-html-view');
    var htmlCode = card.querySelector('.desc-html-code');
    var sectionsControls = card.querySelectorAll('.desc-sections-controls');

    if (htmlToggle) {
      htmlToggle.addEventListener('change', function () {
        if (htmlToggle.checked) {
          syncAllDescriptionEditors(card);
          var sections = [];
          var sectionCards = sectionsList.querySelectorAll('.description-section-card');
          for (var sIndex = 0; sIndex < sectionCards.length; sIndex++) {
            sections.push(collectDescriptionSectionData(sectionCards[sIndex]));
          }
          if (htmlCode) {
            htmlCode.value = buildDescriptionTemplateHtmlCode(sections);
          }
          for (var cIndex = 0; cIndex < sectionsControls.length; cIndex++) {
            sectionsControls[cIndex].style.display = 'none';
          }
          if (htmlView) {
            htmlView.style.display = '';
          }
        } else {
          for (var hIndex = 0; hIndex < sectionsControls.length; hIndex++) {
            sectionsControls[hIndex].style.display = '';
          }
          if (htmlView) {
            htmlView.style.display = 'none';
          }
        }
      });
    }

    var copyHtmlButton = card.querySelector('.copy-desc-html');
    if (copyHtmlButton && htmlCode) {
      copyHtmlButton.addEventListener('click', function () {
        htmlCode.focus();
        htmlCode.select();
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(htmlCode.value).catch(function () {});
        } else {
          try {
            document.execCommand('copy');
          } catch (error) {}
        }
      });
    }

    var applyHtmlButton = card.querySelector('.apply-desc-html');
    if (applyHtmlButton && htmlCode) {
      applyHtmlButton.addEventListener('click', function () {
        if (!window.confirm('To zastapi wszystkie obecne sekcje tego opisu tresci wklejonego kodu HTML. Kontynuowac?')) {
          return;
        }

        var rawValue = String(htmlCode.value || '');
        var parsedSections = parseDescriptionHtmlIntoSections(rawValue);

        if (!parsedSections.length) {
          parsedSections = [{
            layout: 'html',
            html: rawValue.trim(),
            left_image: { source: 'IMG_EU', index: 1 },
            right_image: { source: 'IMG_EU', index: 1 }
          }];
        }

        sectionsList.innerHTML = '';
        parsedSections.forEach(function (parsedSection) {
          sectionsList.appendChild(createDescriptionSectionCard(parsedSection));
        });

        if (htmlToggle) {
          htmlToggle.checked = false;
        }
        for (var acIndex = 0; acIndex < sectionsControls.length; acIndex++) {
          sectionsControls[acIndex].style.display = '';
        }
        if (htmlView) {
          htmlView.style.display = 'none';
        }

        refreshAvailableFieldsFromDescriptions();
      });
    }

    nameInput.addEventListener('input', syncTemplatePresentation);
    keyInput.addEventListener('input', refreshAvailableFieldsFromDescriptionsDebounced);
    card.querySelector('.remove-description-template').addEventListener('click', function () {
      card.remove();
      refreshAvailableFieldsFromDescriptions();
    });
    card.addEventListener('click', function (event) {
      if (!event.target.classList.contains('add-description-section')) {
        return;
      }

      sectionsList.appendChild(createDescriptionSectionCard({
        layout: String(event.target.getAttribute('data-layout') || 'text'),
        text: '',
        left_text: '',
        right_text: '',
        html: '',
        left_image: { source: 'IMG_EU', index: 1 },
        right_image: { source: 'IMG_EU', index: 1 }
      }));
      refreshAvailableFieldsFromDescriptions();
    });
    card.addEventListener('input', function (event) {
      if (event.target.classList.contains('desc-token-search')) {
        return;
      }

      refreshAvailableFieldsFromDescriptionsDebounced();
    });
    card.addEventListener('change', function (event) {
      if (event.target.classList.contains('desc-token-search')) {
        return;
      }

      refreshAvailableFieldsFromDescriptions();
    });
    var dragHandle = card.querySelector('.js-drag-handle');
    if (dragHandle) {
      dragHandle.addEventListener('mousedown', function () {
        armDragFromHandle(card);
      });
      dragHandle.addEventListener('touchstart', function () {
        armDragFromHandle(card);
      }, { passive: true });
    }

    card.addEventListener('dragstart', function (event) {
      if (!canStartDrag(card)) {
        event.preventDefault();
        return;
      }

      startDragItem(card, 'description-template');
      ensureDragPlaceholder(descriptionBuilder, card.nextSibling, card.offsetHeight);
    });
    card.addEventListener('dragend', function () {
      endDragItem();
    });
    card.addEventListener('dragover', function (event) {
      event.preventDefault();
      if (currentDragType !== 'description-template' || !currentDrag || currentDrag === card || !descriptionBuilder) {
        return;
      }

      clearDragIndicators(descriptionBuilder);
      var position = computeInsertPosition(event, card);
      card.classList.add(position === 'before' ? 'drag-insert-before' : 'drag-insert-after');
      ensureDragPlaceholder(descriptionBuilder, position === 'before' ? card : card.nextSibling, currentDrag.offsetHeight);
    });
    card.addEventListener('dragleave', function (event) {
      if (!card.contains(event.relatedTarget)) {
        card.classList.remove('drag-insert-before', 'drag-insert-after');
      }
    });
    card.addEventListener('drop', function (event) {
      event.preventDefault();
      if (currentDragType !== 'description-template' || !currentDrag || currentDrag === card) {
        return;
      }

      var position = computeInsertPosition(event, card);
      moveDraggedItem(descriptionBuilder, card, position);
      clearDragIndicators(descriptionBuilder);
      refreshAvailableFieldsFromDescriptions();
      endDragItem();
    });

    syncTemplatePresentation();
    return card;
  }

  function isGeneratedImagesFieldValue(fieldValue) {
    return fieldValue === 'images' || /^(product\.)?(generated_images|images_export)(\[\d+\])?$/.test(fieldValue || '');
  }

  function refreshGeneratedImagesVisibility() {
    var cards = builder.querySelectorAll('.column-card');
    var firstMatchCard = null;

    for (var i = 0; i < cards.length; i++) {
      var typeSelect = cards[i].querySelector('.col-type');
      var fieldSelect = cards[i].querySelector('.col-field');
      var isField = typeSelect && typeSelect.value === 'field';
      var isMatch = isField && fieldSelect && isGeneratedImagesFieldValue(fieldSelect.value);

      if (isMatch) {
        firstMatchCard = cards[i];
        break;
      }
    }

    for (var j = 0; j < cards.length; j++) {
      var card = cards[j];
      var typeSel = card.querySelector('.col-type');
      var fieldSel = card.querySelector('.col-field');
      var isFieldCol = typeSel && typeSel.value === 'field';
      var isMatchCol = isFieldCol && fieldSel && isGeneratedImagesFieldValue(fieldSel.value);
      var imageLayoutWrap = card.querySelector('.image-layout-wrap');
      var note = card.querySelector('.generated-images-note');

      if (!isMatchCol) {
        if (imageLayoutWrap) {
          imageLayoutWrap.style.display = 'none';
        }
        if (note) {
          note.style.display = 'none';
        }
        continue;
      }

      if (card === firstMatchCard) {
        if (imageLayoutWrap) {
          imageLayoutWrap.style.display = '';
        }
        if (note) {
          note.style.display = 'none';
        }
      } else {
        if (imageLayoutWrap) {
          imageLayoutWrap.style.display = 'none';
        }
        if (note) {
          var headerInput = firstMatchCard ? firstMatchCard.querySelector('.col-header') : null;
          var headerLabel = headerInput && headerInput.value ? headerInput.value : 'pierwsza kolumna z generowanymi zdjeciami';
          note.textContent = 'Ustawienia sciezek (makra, uklad sekcji) dla tego pola sa wspolne dla calego szablonu i skonfigurowane w kolumnie "' + headerLabel + '". Ta kolumna korzysta z nich automatycznie.';
          note.style.display = '';
        }
      }
    }
  }

  function createColumnCard(column) {
    var data = column || {
      header_name: '',
      source_type: 'field',
      source_value: '',
      settings: {},
      mappings: []
    };

    var condition = data.settings && data.settings.condition ? data.settings.condition : {};
    var conditionElse = (condition && Object.prototype.hasOwnProperty.call(condition, 'else')) ? condition['else'] : '';
    var conditions = data.settings && Array.isArray(data.settings.conditions) ? data.settings.conditions.slice() : [];
    if (!conditions.length && condition && condition.field) {
      conditions.push(condition);
    }
    if (!conditions.length && data.source_type === 'field' && data.source_value) {
      conditions.push({ field: data.source_value, operator: 'contains', value: '', then: '' });
    }
    if (data.settings && Object.prototype.hasOwnProperty.call(data.settings, 'condition_else')) {
      conditionElse = data.settings.condition_else;
    }
    var imageOptions = data.settings && data.settings.image_options ? data.settings.image_options : {};

    var card = document.createElement('div');
    card.className = 'card border column-card';
    card.draggable = true;
    card.innerHTML = ''
      + '<div class="card-body py-2">'
      + '  <div class="d-flex justify-content-between align-items-center mb-2">'
      + '    <span class="text-secondary small js-drag-handle" style="cursor:move;">:: przeciagnij</span>'
      + '    <div class="d-flex gap-2">'
      + '      <button type="button" class="btn btn-sm btn-outline-secondary duplicate-column">Duplikuj</button>'
      + '      <button type="button" class="btn btn-sm btn-outline-danger remove-column">Usun</button>'
      + '    </div>'
      + '  </div>'
      + '  <div class="row g-2">'
      + '    <div class="col-md-3"><label class="form-label small">Naglowek</label><input type="text" class="form-control form-control-sm col-header" value="' + escapeHtml(data.header_name) + '"></div>'
      + '    <div class="col-md-2"><label class="form-label small">Typ</label><select class="form-select form-select-sm col-type"><option value="field">field</option><option value="static">static</option><option value="computed">computed</option></select></div>'
      + '    <div class="col-md-3 field-wrap"><label class="form-label small">Pole</label><input type="text" class="form-control form-control-sm col-field-search mb-1" placeholder="Szukaj pola"><select class="form-select form-select-sm col-field">' + fieldOptions(data.source_value) + '</select></div>'
      + '    <div class="col-md-3 static-wrap"><label class="form-label small">Wartosc stala</label><input type="text" class="form-control form-control-sm col-static" value="' + escapeHtml(data.source_value) + '"></div>'
      + '    <div class="col-md-4 computed-wrap"><label class="form-label small">Funkcja</label><select class="form-select form-select-sm col-fn">' + functionOptions(data.settings.function) + '</select></div>'
      + '    <div class="col-md-4"><label class="form-label small">Format</label><input type="text" class="form-control form-control-sm col-format" placeholder="np. ucfirst, date:Y-m-d, number:2:,: lub length:2000" value="' + escapeHtml(data.settings.format || '') + '"></div>'
      + '    <div class="col-md-2"><label class="form-label small">Array sep.</label><input type="text" class="form-control form-control-sm col-array-sep" value="' + escapeHtml(data.settings.array_separator || '') + '"></div>'
      + '    <div class="col-12 computed-wrap computed-json-wrap"><label class="form-label small">Argumenty (JSON)</label><textarea class="form-control form-control-sm col-fn-args" rows="3">' + escapeHtml(JSON.stringify(data.settings.args || {}, null, 0)) + '</textarea><div class="border rounded bg-light-subtle p-2 mt-2"><div class="small fw-semibold mb-2">Wstaw parametr do JSON</div><div class="row g-2 align-items-end"><div class="col-lg-4"><label class="form-label small mb-1">Wyszukiwarka</label><input type="text" class="form-control form-control-sm col-fn-field-search" placeholder="Szukaj np. allegro_parameter, empik, sku"></div><div class="col-lg-6"><label class="form-label small mb-1">Pole / parametr</label><select class="form-select form-select-sm col-fn-field-insert">' + fieldInsertOptions() + '</select></div><div class="col-lg-2 d-grid"><button type="button" class="btn btn-sm btn-outline-secondary insert-field-token">Wstaw do JSON</button></div></div><div class="form-text mb-0">Wstawia token w formacie <code>field:product.sku</code> albo <code>field:product.allegro_parameter.11748</code> w miejscu kursora.</div></div></div>'
      + '  </div>'
      + '  <div class="border rounded p-2 mt-2">'
      + '    <div class="d-flex justify-content-between align-items-center gap-2 mb-2"><div><div class="small fw-semibold">Warunki dla wybranego pola</div><div class="form-text mt-0">Reguły sprawdzają pole wybrane u góry tej kolumny. Są wykonywane od góry i wygrywa pierwsza pasująca.</div></div><button type="button" class="btn btn-sm btn-outline-secondary add-condition">Dodaj warunek</button></div>'
      + '    <div class="condition-list">' + conditionRowsHtml(conditions) + '</div>'
      + '    <div class="row g-2 mt-1"><div class="col-md-9"></div><div class="col-md-3"><label class="form-label small mb-1">Gdy nic nie pasuje</label><input type="text" class="form-control form-control-sm cond-else" placeholder="pozostaw puste = oryginalna wartość" value="' + escapeHtml(conditionElse) + '"></div></div>'
      + '  </div>'
      + '  <div class="border rounded p-2 mt-2">'
      + '    <div class="d-flex justify-content-between align-items-center mb-2">'
      + '      <div class="small fw-semibold">Mapowanie wartosci</div>'
      + '      <button type="button" class="btn btn-sm btn-outline-secondary add-mapping">Dodaj mapowanie</button>'
      + '    </div>'
      + '    <div class="mapping-list">' + mappingRowsHtml(data.mappings) + '</div>'
      + '  </div>'
      + '  <div class="alert alert-secondary small mt-2 mb-0 generated-images-note" style="display:none;"></div>'
      + '  <div class="border rounded p-2 mt-2 image-layout-wrap" style="display:none;">'
      + '    <div class="alert alert-warning small mb-3">'
      + '      <strong>Uwaga:</strong> te pola (<code>product.generated_images</code> oraz <code>product.generated_images[1..16]</code>) generuja sciezki dopiero wtedy, gdy przy eksporcie CSV (w oknie eksportu produktow) zostana ustawione liczby <code>Zdjecia</code> / <code>Miniatury</code> / <code>Mockupy</code> (pola <code>image_count</code>, <code>thumbnail_count</code>, <code>mockup_count</code>) rowne lub wieksze od numeru, ktorego uzywasz w kolumnie. Sam szablon CSV tego nie ustawia - jesli te liczby nie zostana wpisane przy eksporcie (domyslnie 0), pole bedzie puste, nawet gdy makro ponizej jest poprawnie skonfigurowane. Ustawienia ponizej (makra, uklad sekcji) dotycza <strong>wszystkich</strong> kolumn z generowanymi zdjeciami w tym szablonie - konfigurujesz je tylko raz, w pierwszej takiej kolumnie.'
      + '    </div>'
      + '    <div class="row g-2 mb-3">'
      + '      <div class="col-12"><label class="form-label small">Makro miniatur</label><textarea class="form-control form-control-sm image-option-thumbnail-macro" rows="2">' + escapeHtml(imageOptions.thumbnail_macro || '{ldelim}{ldelim}base_directory{rdelim}{rdelim}\\{ldelim}{ldelim}contours{rdelim}{rdelim}\\{ldelim}{ldelim}collection_code{rdelim}{rdelim}\\miniatura_t_{ldelim}{ldelim}index{rdelim}{rdelim}.png') + '</textarea></div>'
      + '      <div class="col-12"><label class="form-label small">Makro mockupow / gridow</label><textarea class="form-control form-control-sm image-option-mockup-macro" rows="2">' + escapeHtml(imageOptions.mockup_macro || '{ldelim}{ldelim}base_directory{rdelim}{rdelim}\\{ldelim}{ldelim}contours{rdelim}{rdelim}\\mockup_{ldelim}{ldelim}index{rdelim}{rdelim}.jpg') + '</textarea></div>'
      + '      <div class="col-12"><label class="form-label small">Makro zdjec</label><textarea class="form-control form-control-sm image-option-image-macro" rows="2">' + escapeHtml(imageOptions.image_macro || '{ldelim}{ldelim}base_directory{rdelim}{rdelim}\\{ldelim}{ldelim}contours{rdelim}{rdelim}\\{ldelim}{ldelim}collection_code{rdelim}{rdelim}\\{ldelim}{ldelim}index{rdelim}{rdelim}.jpg') + '</textarea></div>'
      + '      <div class="col-12 small text-secondary">'
      + '        <div class="fw-semibold mb-1">Tokeny makr obrazow</div>'
      + '        <div><code>{ldelim}{ldelim}base_directory{rdelim}{rdelim}</code> - katalog bazowy eksportu.</div>'
      + '        <div><code>{ldelim}{ldelim}gutenberg_nick{rdelim}{rdelim}</code> - nick konta Gutenberg aktualnie zalogowanego uzytkownika.</div>'
      + '        <div><code>{ldelim}{ldelim}contours{rdelim}{rdelim}</code> - folder / wartosc z pola contours produktu.</div>'
      + '        <div><code>{ldelim}{ldelim}collection_code{rdelim}{rdelim}</code> - numer kolekcji wpisany przy eksporcie, np. <code>A100</code>.</div>'
      + '        <div><code>{ldelim}{ldelim}collection_code_index{rdelim}{rdelim}</code> - numeracja od kodu kolekcji, np. dla <code>A100</code>: <code>A100</code>, <code>A101</code>, <code>A102</code>.</div>'
      + '        <div><code>{ldelim}{ldelim}collection_name{rdelim}{rdelim}</code> - nazwa kolekcji wpisana przy eksporcie.</div>'
      + '        <div><code>{ldelim}{ldelim}queue_range{rdelim}{rdelim}</code> - zakres kolejki wpisany przy eksporcie, np. <code>A100-A250</code>.</div>'
      + '        <div><code>{ldelim}{ldelim}queue_from{rdelim}{rdelim}</code> - poczatek zakresu kolejki, np. <code>A100</code>.</div>'
      + '        <div><code>{ldelim}{ldelim}queue_to{rdelim}{rdelim}</code> - koniec zakresu kolejki, np. <code>A250</code>.</div>'
      + '        <div><code>{ldelim}{ldelim}queue_item{rdelim}{rdelim}</code> - aktualny numer wzoru dla danej miniatury, np. <code>A200</code> z pola wzorow miniatur wpisanego przy eksporcie.</div>'
      + '        <div><code>{ldelim}{ldelim}grid{rdelim}{rdelim}</code> lub <code>{ldelim}{ldelim}grid_layout{rdelim}{rdelim}</code> - grid wpisany / wyliczony przy eksporcie, np. <code>4x3</code>.</div>'
      + '        <div><code>{ldelim}{ldelim}grid_columns{rdelim}{rdelim}</code> - liczba kolumn grida.</div>'
      + '        <div><code>{ldelim}{ldelim}grid_rows{rdelim}{rdelim}</code> - liczba wierszy grida.</div>'
      + '        <div><code>{ldelim}{ldelim}index_grid_count{rdelim}{rdelim}</code> - ile finalnie wyjdzie grafik z zakresu kolejki i grida, np. <code>4</code>.</div>'
      + '        <div><code>{ldelim}{ldelim}product_name{rdelim}{rdelim}</code> - pelna nazwa produktu.</div>'
      + '        <div><code>{ldelim}{ldelim}product_slug{rdelim}{rdelim}</code> - nazwa produktu zamieniona na bezpieczny slug.</div>'
      + '        <div><code>{ldelim}{ldelim}sku{rdelim}{rdelim}</code> - aktualne SKU produktu.</div>'
      + '        <div><code>{ldelim}{ldelim}old_sku{rdelim}{rdelim}</code> - stare SKU produktu, jesli istnieje.</div>'
      + '        <div><code>{ldelim}{ldelim}price{rdelim}{rdelim}</code> - cena wpisana przy eksporcie.</div>'
      + '        <div><code>{ldelim}{ldelim}index{rdelim}{rdelim}</code> - numer elementu od 1.</div>'
      + '        <div><code>{ldelim}{ldelim}index0{rdelim}{rdelim}</code> - numer elementu od 0.</div>'
      + '      </div>'
      + '    </div>'
      + '    <div class="d-flex justify-content-between align-items-center mb-2">'
      + '      <div>'
      + '        <div class="small fw-semibold">Uklad sekcji obrazow</div>'
      + '        <div class="small text-secondary">Ustaw kolejnosc: miniatura, mockupy, zdjecia i stale teksty pomiedzy nimi.</div>'
      + '      </div>'
      + '      <div class="d-flex gap-2 flex-wrap">'
      + '        <button type="button" class="btn btn-sm btn-outline-secondary add-image-layout-item" data-layout-type="thumbnail">Dodaj miniature</button>'
      + '        <button type="button" class="btn btn-sm btn-outline-secondary add-image-layout-item" data-layout-type="mockup">Dodaj mockupy</button>'
      + '        <button type="button" class="btn btn-sm btn-outline-secondary add-image-layout-item" data-layout-type="image">Dodaj zdjecia</button>'
      + '        <button type="button" class="btn btn-sm btn-outline-primary add-image-layout-item" data-layout-type="static">Dodaj stala</button>'
      + '      </div>'
      + '    </div>'
      + '    <div class="image-layout-list">' + imageLayoutRowsHtml(data.settings.image_layout || []) + '</div>'
      + '  </div>'
      + '</div>';

    var typeSelect = card.querySelector('.col-type');
    typeSelect.value = data.source_type || 'field';

    function updateVisibility() {
      var type = typeSelect.value;
      var fieldValue = card.querySelector('.col-field').value;
      card.querySelector('.field-wrap').style.display = (type === 'field') ? '' : 'none';
      card.querySelector('.static-wrap').style.display = (type === 'static') ? '' : 'none';
      var computedFields = card.querySelectorAll('.computed-wrap');
      for (var i = 0; i < computedFields.length; i++) {
        computedFields[i].style.display = (type === 'computed') ? '' : 'none';
      }
      refreshGeneratedImagesVisibility();
    }

    typeSelect.addEventListener('change', updateVisibility);
    card.querySelector('.col-field').addEventListener('change', updateVisibility);
    updateVisibility();

    var fieldSearchInput = card.querySelector('.col-field-search');
    var fieldSelect = card.querySelector('.col-field');
    if (fieldSearchInput && fieldSelect) {
      fieldSearchInput.addEventListener('input', function () {
        var query = String(fieldSearchInput.value || '').toLowerCase().trim();
        var options = fieldSelect.querySelectorAll('option');
        for (var optionIndex = 0; optionIndex < options.length; optionIndex++) {
          if (optionIndex === 0) {
            options[optionIndex].hidden = false;
            continue;
          }

          var haystack = String(options[optionIndex].textContent || '').toLowerCase();
          options[optionIndex].hidden = query !== '' && haystack.indexOf(query) === -1;
        }
      });
    }

    var fnFieldSearchInput = card.querySelector('.col-fn-field-search');
    var fnFieldSelect = card.querySelector('.col-fn-field-insert');
    var fnArgsTextarea = card.querySelector('.col-fn-args');
    if (fnFieldSearchInput && fnFieldSelect) {
      fnFieldSearchInput.addEventListener('input', function () {
        var query = String(fnFieldSearchInput.value || '').toLowerCase().trim();
        var options = fnFieldSelect.querySelectorAll('option');
        for (var optionIndex = 0; optionIndex < options.length; optionIndex++) {
          if (optionIndex === 0) {
            options[optionIndex].hidden = false;
            continue;
          }

          var haystack = String(options[optionIndex].textContent || '').toLowerCase();
          options[optionIndex].hidden = query !== '' && haystack.indexOf(query) === -1;
        }
      });
    }

    card.querySelector('.remove-column').addEventListener('click', function () {
      card.remove();
      refreshGeneratedImagesVisibility();
    });

    card.querySelector('.duplicate-column').addEventListener('click', function () {
      builder.insertBefore(createColumnCard(collectCardData(card)), card.nextSibling);
      refreshGeneratedImagesVisibility();
    });

    card.querySelector('.add-mapping').addEventListener('click', function () {
      var list = card.querySelector('.mapping-list');
      var row = document.createElement('div');
      row.className = 'row g-2 mapping-row mb-1';
      row.innerHTML = '<div class="col"><input type="text" class="form-control form-control-sm map-from" placeholder="from"></div>'
        + '<div class="col"><input type="text" class="form-control form-control-sm map-to" placeholder="to"></div>'
        + '<div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger remove-mapping">x</button></div>';
      list.appendChild(row);
    });

    card.querySelector('.add-condition').addEventListener('click', function () {
      var list = card.querySelector('.condition-list');
      var wrapper = document.createElement('div');
      wrapper.innerHTML = conditionRowsHtml([{
        operator: 'contains',
        value: '',
        then: ''
      }]);
      list.appendChild(wrapper.firstChild);
    });

    card.addEventListener('click', function (event) {
      if (event.target.classList.contains('remove-mapping')) {
        var row = event.target.closest('.mapping-row');
        if (row) {
          row.remove();
        }
      }

      if (event.target.classList.contains('remove-condition')) {
        var conditionRow = event.target.closest('.condition-row');
        if (conditionRow) {
          conditionRow.remove();
        }
      }

      if (event.target.classList.contains('remove-image-layout-item')) {
        var layoutRow = event.target.closest('.image-layout-item');
        if (layoutRow) {
          layoutRow.remove();
        }
      }

      if (event.target.classList.contains('insert-field-token')) {
        if (!fnArgsTextarea || !fnFieldSelect || !fnFieldSelect.value) {
          return;
        }

        var token = 'field:' + fnFieldSelect.value;
        var start = typeof fnArgsTextarea.selectionStart === 'number' ? fnArgsTextarea.selectionStart : fnArgsTextarea.value.length;
        var end = typeof fnArgsTextarea.selectionEnd === 'number' ? fnArgsTextarea.selectionEnd : fnArgsTextarea.value.length;
        var currentValue = String(fnArgsTextarea.value || '');
        fnArgsTextarea.value = currentValue.slice(0, start) + token + currentValue.slice(end);
        fnArgsTextarea.focus();
        var caretPosition = start + token.length;
        if (typeof fnArgsTextarea.setSelectionRange === 'function') {
          fnArgsTextarea.setSelectionRange(caretPosition, caretPosition);
        }
      }

      if (event.target.classList.contains('add-image-layout-item')) {
        var list = card.querySelector('.image-layout-list');
        var layoutType = String(event.target.getAttribute('data-layout-type') || 'static');
        if (list) {
          var wrapper = document.createElement('div');
          wrapper.innerHTML = imageLayoutRowsHtml([{ type: layoutType, value: '' }]);
          list.appendChild(wrapper.firstChild);
          bindImageLayoutInteractions(card);
        }
      }
    });

    card.addEventListener('change', function (event) {
      if (!event.target.classList.contains('image-layout-type')) {
        return;
      }

      var item = event.target.closest('.image-layout-item');
      if (!item) {
        return;
      }

      var staticWrap = item.querySelector('.image-layout-static-wrap');
      if (staticWrap) {
        staticWrap.style.display = event.target.value === 'static' ? '' : 'none';
      }
    });

    var dragHandle = card.querySelector('.js-drag-handle');
    if (dragHandle) {
      dragHandle.addEventListener('mousedown', function () {
        armDragFromHandle(card);
      });
      dragHandle.addEventListener('touchstart', function () {
        armDragFromHandle(card);
      }, { passive: true });
    }

    card.addEventListener('dragstart', function (event) {
      if (!canStartDrag(card)) {
        event.preventDefault();
        return;
      }

      startDragItem(card, 'column-card');
      ensureDragPlaceholder(builder, card.nextSibling, card.offsetHeight);
    });

    card.addEventListener('dragend', function () {
      endDragItem();
    });

    card.addEventListener('dragover', function (event) {
      event.preventDefault();
      if (currentDragType !== 'column-card' || !currentDrag || currentDrag === card || !builder) {
        return;
      }

      clearDragIndicators(builder);
      var position = computeInsertPosition(event, card);
      card.classList.add(position === 'before' ? 'drag-insert-before' : 'drag-insert-after');
      ensureDragPlaceholder(builder, position === 'before' ? card : card.nextSibling, currentDrag.offsetHeight);
    });
    card.addEventListener('dragleave', function (event) {
      if (!card.contains(event.relatedTarget)) {
        card.classList.remove('drag-insert-before', 'drag-insert-after');
      }
    });

    card.addEventListener('drop', function (event) {
      event.preventDefault();
      if (currentDragType !== 'column-card' || !currentDrag || currentDrag === card) {
        return;
      }

      var position = computeInsertPosition(event, card);
      moveDraggedItem(builder, card, position);
      clearDragIndicators(builder);
      endDragItem();
      refreshGeneratedImagesVisibility();
    });

    function bindImageLayoutInteractions(scope) {
      var items = scope.querySelectorAll('.image-layout-item');
      for (var index = 0; index < items.length; index++) {
        (function (item) {
          if (item.dataset.imageLayoutBound === '1') {
            return;
          }

          item.dataset.imageLayoutBound = '1';
          var dragHandle = item.querySelector('.js-drag-handle');
          if (dragHandle) {
            dragHandle.addEventListener('mousedown', function () {
              armDragFromHandle(item);
            });
            dragHandle.addEventListener('touchstart', function () {
              armDragFromHandle(item);
            }, { passive: true });
          }

          item.addEventListener('dragstart', function (event) {
            if (!canStartDrag(item)) {
              event.preventDefault();
              event.stopPropagation();
              return;
            }

            startDragItem(item, 'image-layout-item');
            ensureDragPlaceholder(item.parentNode, item.nextSibling, item.offsetHeight);
            event.stopPropagation();
          });

          item.addEventListener('dragend', function (event) {
            endDragItem();
            event.stopPropagation();
          });

          item.addEventListener('dragover', function (event) {
            event.preventDefault();
            event.stopPropagation();
            var list = scope.querySelector('.image-layout-list');
            if (currentDragType !== 'image-layout-item' || !currentDrag || currentDrag === item || !list) {
              return;
            }

            clearDragIndicators(list);
            var position = computeInsertPosition(event, item);
            item.classList.add(position === 'before' ? 'drag-insert-before' : 'drag-insert-after');
            ensureDragPlaceholder(list, position === 'before' ? item : item.nextSibling, currentDrag.offsetHeight);
          });

          item.addEventListener('drop', function (event) {
            event.preventDefault();
            event.stopPropagation();
            var list = scope.querySelector('.image-layout-list');
            if (currentDragType !== 'image-layout-item' || !list || !currentDrag || currentDrag === item) {
              return;
            }

            var position = computeInsertPosition(event, item);
            moveDraggedItem(list, item, position);
            clearDragIndicators(list);
            endDragItem();
          });
        })(items[index]);
      }
    }

    bindImageLayoutInteractions(card);

    return card;
  }

  function collectCardData(card) {
    var type = card.querySelector('.col-type').value;
    var sourceValue = '';

    if (type === 'field') {
      sourceValue = card.querySelector('.col-field').value;
    } else if (type === 'static') {
      sourceValue = card.querySelector('.col-static').value;
    } else {
      sourceValue = 'computed';
    }

    var args = {};
    var argsRaw = card.querySelector('.col-fn-args').value;
    if (argsRaw.trim() !== '') {
      try {
        args = JSON.parse(argsRaw);
      } catch (error) {
        args = {};
      }
    }

    var conditions = [];
    var conditionRows = card.querySelectorAll('.condition-row');
    for (var c = 0; c < conditionRows.length; c++) {
      var conditionValue = conditionRows[c].querySelector('.cond-value').value;
      var conditionThen = conditionRows[c].querySelector('.cond-then').value;
      if (conditionValue !== '' || conditionThen !== '') {
        conditions.push({
          operator: conditionRows[c].querySelector('.cond-op').value,
          value: conditionValue,
          then: conditionThen
        });
      }
    }
    var conditionElse = card.querySelector('.cond-else').value;
    var condition = conditions.length ? Object.assign({}, conditions[0], { else: conditionElse }) : {};

    var mappings = [];
    var mappingRows = card.querySelectorAll('.mapping-row');
    for (var m = 0; m < mappingRows.length; m++) {
      var from = mappingRows[m].querySelector('.map-from').value.trim();
      var to = mappingRows[m].querySelector('.map-to').value;
      if (from !== '') {
        mappings.push({ from_value: from, to_value: to });
      }
    }

    var imageLayout = [];
    var imageLayoutRows = card.querySelectorAll('.image-layout-item');
    for (var i = 0; i < imageLayoutRows.length; i++) {
      var layoutTypeField = imageLayoutRows[i].querySelector('.image-layout-type');
      if (!layoutTypeField) {
        continue;
      }

      imageLayout.push({
        type: layoutTypeField.value,
        value: imageLayoutRows[i].querySelector('.image-layout-value') ? imageLayoutRows[i].querySelector('.image-layout-value').value : ''
      });
    }

    var imageOptions = {
      thumbnail_macro: card.querySelector('.image-option-thumbnail-macro') ? card.querySelector('.image-option-thumbnail-macro').value : '',
      mockup_macro: card.querySelector('.image-option-mockup-macro') ? card.querySelector('.image-option-mockup-macro').value : '',
      image_macro: card.querySelector('.image-option-image-macro') ? card.querySelector('.image-option-image-macro').value : ''
    };

    return {
      header_name: card.querySelector('.col-header').value,
      source_type: type,
      source_value: sourceValue,
      settings: {
        function: card.querySelector('.col-fn').value,
        args: args,
        format: card.querySelector('.col-format').value,
        array_separator: card.querySelector('.col-array-sep').value,
        image_layout: imageLayout,
        image_options: imageOptions,
        condition: condition,
        conditions: conditions,
        condition_else: conditionElse
      },
      mappings: mappings
    };
  }

  function collectDescriptionTemplates() {
    syncAllDescriptionEditors(descriptionBuilder);
    var cards = descriptionBuilder.querySelectorAll('.description-template-card');
    var templates = [];

    for (var i = 0; i < cards.length; i++) {
      var nameInput = cards[i].querySelector('.desc-template-name');
      var keyInput = cards[i].querySelector('.desc-template-key');
      var normalizedKey = keyInput ? String(keyInput.value || '').trim() : '';
      if (!normalizedKey) {
        normalizedKey = slugifyDescriptionKey(nameInput ? nameInput.value : '') || ('opis_' + (i + 1));
        if (keyInput) {
          keyInput.value = normalizedKey;
        }
      }
      var sections = [];
      var sectionCards = cards[i].querySelectorAll('.description-section-card');

      for (var sectionIndex = 0; sectionIndex < sectionCards.length; sectionIndex++) {
        sections.push(collectDescriptionSectionData(sectionCards[sectionIndex]));
      }

      templates.push({
        name: nameInput ? nameInput.value : '',
        key: normalizedKey,
        sections: sections
      });
    }

    return templates;
  }

  function updateSelectOptions(select, optionsHtml, selectedValue) {
    if (!select) {
      return;
    }

    select.innerHTML = optionsHtml;
    if (typeof selectedValue !== 'undefined') {
      select.value = selectedValue;
    }
  }

  function refreshAvailableFieldsFromDescriptions() {
    var nextFields = Object.assign({}, baseAvailableFields);
    var templateCards = descriptionBuilder.querySelectorAll('.description-template-card');
    var descriptionTemplates = collectDescriptionTemplates();

    descriptionTemplates.forEach(function (template, index) {
      var name = String(template && template.name ? template.name : '').trim();
      var key = String(template && template.key ? template.key : '').trim();
      if (!name) {
        return;
      }

      if (!key) {
        key = slugifyDescriptionKey(name) || ('opis_' + (index + 1));
        template.key = key;
      }

      if (templateCards[index]) {
        var keyInput = templateCards[index].querySelector('.desc-template-key');
        if (keyInput && !String(keyInput.value || '').trim()) {
          keyInput.value = key;
        }
      }

      nextFields['product.csv_description.' + key] = 'Opis CSV: ' + name;
    });

    availableFields = nextFields;

    var columnCards = builder.querySelectorAll('.column-card');
    for (var i = 0; i < columnCards.length; i++) {
      var fieldSelect = columnCards[i].querySelector('.col-field');
      var fnFieldSelect = columnCards[i].querySelector('.col-fn-field-insert');
      var fieldValue = fieldSelect ? fieldSelect.value : '';
      var fnFieldValue = fnFieldSelect ? fnFieldSelect.value : '';
      updateSelectOptions(fieldSelect, fieldOptions(fieldValue), fieldValue);
      updateSelectOptions(fnFieldSelect, fieldInsertOptions(), fnFieldValue);
    }

    var descriptionTokenSelects = descriptionBuilder.querySelectorAll('.desc-token-select');
    for (var tokenIndex = 0; tokenIndex < descriptionTokenSelects.length; tokenIndex++) {
      var tokenValue = descriptionTokenSelects[tokenIndex].value;
      updateSelectOptions(descriptionTokenSelects[tokenIndex], descriptionTokenOptions(), tokenValue);
    }
  }

  var refreshAvailableFieldsFromDescriptionsDebounced = debounce(refreshAvailableFieldsFromDescriptions, 250);

  function bindContainerDropzone(container, itemSelector, dragType, onDropCallback) {
    if (!container) {
      return;
    }

    container.addEventListener('dragover', function (event) {
      event.preventDefault();
      if (!currentDrag || currentDragType !== dragType) {
        return;
      }

      var target = event.target.closest(itemSelector);
      if (target) {
        return;
      }

      clearDragIndicators(container);
      ensureDragPlaceholder(container, null, currentDrag.offsetHeight);
    });

    container.addEventListener('drop', function (event) {
      event.preventDefault();
      if (!currentDrag || currentDragType !== dragType) {
        return;
      }

      var target = event.target.closest(itemSelector);
      if (target) {
        return;
      }

      container.appendChild(currentDrag);
      clearDragIndicators(container);
      if (typeof onDropCallback === 'function') {
        onDropCallback();
      }
      endDragItem();
    });

    container.addEventListener('dragleave', function (event) {
      if (!container.contains(event.relatedTarget)) {
        clearDragIndicators(container);
      }
    });
  }

  function collectColumns() {
    var cards = builder.querySelectorAll('.column-card');
    var columns = [];

    for (var i = 0; i < cards.length; i++) {
      columns.push(collectCardData(cards[i]));
    }

    return columns;
  }

  addButton.addEventListener('click', function () {
    builder.appendChild(createColumnCard());
    refreshGeneratedImagesVisibility();
  });

  if (addDescriptionTemplateButton) {
    addDescriptionTemplateButton.addEventListener('click', function () {
      descriptionBuilder.appendChild(createDescriptionTemplateCard());
      refreshAvailableFieldsFromDescriptions();
    });
  }

  if (addPresetButton) {
    addPresetButton.addEventListener('click', function () {
      builder.appendChild(createColumnCard({
        header_name: 'Cena z waluta',
        source_type: 'computed',
        source_value: 'computed',
        settings: {
          function: 'concat',
          args: {
            separator: ' ',
            parts: ['field:product.price_gross', 'PLN']
          },
          format: '',
          array_separator: '',
          condition: {}
        },
        mappings: []
      }));
      refreshGeneratedImagesVisibility();
    });
  }

  form.addEventListener('submit', function () {
    refreshAvailableFieldsFromDescriptions();
    payloadInput.value = JSON.stringify(collectColumns());
    descriptionPayloadInput.value = JSON.stringify(collectDescriptionTemplates());
  });

  if (Array.isArray(initialDescriptionTemplates) && initialDescriptionTemplates.length) {
    initialDescriptionTemplates.forEach(function (template) {
      descriptionBuilder.appendChild(createDescriptionTemplateCard(template));
    });
  }

  bindContainerDropzone(builder, '.column-card', 'column-card', refreshGeneratedImagesVisibility);
  bindContainerDropzone(descriptionBuilder, '.description-template-card', 'description-template', refreshAvailableFieldsFromDescriptions);

  document.addEventListener('drop', function () {
    forceEndDragItemSoon();
  });

  document.addEventListener('dragend', function () {
    forceEndDragItemSoon();
  });

  document.addEventListener('mouseup', function () {
    if (!currentDrag) {
      armedDragItem = null;
    }
  });

  document.addEventListener('touchend', function () {
    if (!currentDrag) {
      armedDragItem = null;
    }
  });

  refreshAvailableFieldsFromDescriptions();

  if (!Array.isArray(initialColumns) || initialColumns.length === 0) {
    builder.appendChild(createColumnCard());
  } else {
    initialColumns.forEach(function (column) {
      builder.appendChild(createColumnCard(column));
    });
  }
  refreshGeneratedImagesVisibility();
})();
</script>
