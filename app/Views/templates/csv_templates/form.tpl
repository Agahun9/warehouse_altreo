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

      <form method="post" action="{$formAction|escape}" id="csv-template-form">
        <input type="hidden" name="id" value="{$template.id|default:0|escape}">
        <input type="hidden" name="columns_payload" id="columnsPayload" value="[]">

        <div class="card mb-4">
          <div class="card-header"><h3 class="card-title mb-0">Dane glowne</h3></div>
          <div class="card-body row g-3">
            <div class="col-md-6">
              <label class="form-label">Nazwa szablonu</label>
              <input type="text" name="name" class="form-control" value="{$template.name|default:''|escape}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Opis</label>
              <input type="text" name="description" class="form-control" value="{$template.description|default:''|escape}">
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
            <div class="row g-3">
              <div class="col-lg-6">
                <h4 class="h6">1. Jak zbudowac prosty szablon</h4>
                <ol class="small mb-0 ps-3">
                  <li>Wpisz nazwe szablonu, np. <code>Google Merchant</code> albo <code>Eksport magazynowy</code>.</li>
                  <li>Ustaw separator CSV, kodowanie i opcjonalnie BOM.</li>
                  <li>Dodaj kolumny przyciskiem <code>Dodaj kolumne</code>.</li>
                  <li>Dla kazdej kolumny podaj naglowek i wybierz typ zrodla: <code>field</code>, <code>static</code> albo <code>computed</code>.</li>
                  <li>Zapisz szablon albo uzyj przycisku <code>Podglad CSV</code>, aby sprawdzic pierwsze rekordy.</li>
                </ol>
              </div>
              <div class="col-lg-6">
                <h4 class="h6">2. Typy kolumn</h4>
                <ul class="small mb-0">
                  <li><code>field</code>: pobiera wartosc z produktu, np. <code>product.sku</code>, <code>product.product_name</code>, <code>product.category_name</code>.</li>
                  <li><code>static</code>: wpisuje stala wartosc do kazdego wiersza, np. <code>PLN</code>, <code>nowy</code>, <code>ALTREO</code>.</li>
                  <li><code>computed</code>: wylicza wartosc z kilku pol lub transformacji, np. laczy cene z waluta albo zamienia tekst na wielkie litery.</li>
                </ul>
              </div>
              <div class="col-lg-6">
                <h4 class="h6">3. Przyklady pol i relacji</h4>
                <ul class="small mb-0">
                  <li><code>product.sku</code> - SKU produktu</li>
                  <li><code>product.product_name</code> - nazwa produktu</li>
                  <li><code>product.price_gross</code> - cena brutto</li>
                  <li><code>product.category_name</code> - nazwa kategorii</li>
                  <li><code>product.images[0].url</code> - pierwszy obrazek</li>
                  <li><code>product.images</code> - wszystkie obrazki, polaczone separatorem tablicy</li>
                  <li><code>product.allegro_parameters</code> - wszystkie parametry Allegro w jednej komorce, kazdy w nowej linii</li>
                  <li><code>product.allegro_parameters_eu</code> - wszystkie parametry Allegro w formacie EU: <code>parameter_id|type|value|</code></li>
                  <li><code>product.empik_parameters</code> - wszystkie parametry Empik w jednej komorce, kazdy w nowej linii</li>
                  <li><code>product.empik_parameter.600</code> - pojedynczy parametr Empik po ID, np. Producent</li>
                  <li><code>images</code> lub <code>product.generated_images</code> - generowana lista sciezek obrazow EasyUploader</li>
                </ul>
              </div>
              <div class="col-lg-6">
                <h4 class="h6">4. Funkcje computed</h4>
                <div class="small">
                  <p class="mb-2">W argumencie JSON mozesz uzywac tokenow typu <code>field:product.price_gross</code>.</p>
                  <p class="mb-1"><strong>Przyklad concat:</strong></p>
                  <pre class="bg-light border rounded p-2 small"><code>{ldelim}"separator":" ","parts":["field:product.price_gross","PLN"]{rdelim}</code></pre>
                  <p class="mb-1"><strong>Przyklad concat z parametrem Allegro:</strong></p>
                  <pre class="bg-light border rounded p-2 small"><code>{ldelim}"separator":" | ","parts":["field:product.product_name","field:product.allegro_parameter.11748"]{rdelim}</code></pre>
                  <p class="mb-1"><strong>Przyklad upper:</strong></p>
                  <pre class="bg-light border rounded p-2 small"><code>{ldelim}"value":"field:product.product_name"{rdelim}</code></pre>
                  <p class="mb-0 text-secondary">Dokladny klucz parametru bierzesz z listy pol ponizej. Przy polach Allegro i Empik pokazuje sie teraz nazwa parametru, jego ID oraz nazwy kategorii, z ktorych jest brany.</p>
                </div>
              </div>
              <div class="col-lg-6">
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
              <div class="col-lg-6">
                <h4 class="h6">6. Warunki</h4>
                <div class="small">
                  <p class="mb-2">Warunek nadpisuje wynik kolumny, gdy wskazane pole spelni porownanie.</p>
                  <p class="mb-1">Przyklad:</p>
                  <ul class="mb-0">
                    <li><code>field</code>: <code>product.price_gross</code></li>
                    <li><code>operator</code>: <code>gt</code></li>
                    <li><code>value</code>: <code>1000</code></li>
                    <li><code>then</code>: <code>Premium</code></li>
                    <li><code>else</code>: <code>Standard</code></li>
                  </ul>
                </div>
              </div>
              <div class="col-lg-6">
                <h4 class="h6">7. Formatowanie</h4>
                <ul class="small mb-0">
                  <li><code>upper</code> - zamiana na wielkie litery</li>
                  <li><code>lower</code> - zamiana na male litery</li>
                  <li><code>trim</code> - usuniecie spacji z poczatku i konca</li>
                  <li><code>date:Y-m-d</code> - format daty</li>
                  <li><code>number:2:,: </code> - format liczbowy: 2 miejsca, przecinek dziesietny, spacja tysieczna</li>
                </ul>
              </div>
              <div class="col-lg-6">
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

        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Definicja kolumn (drag & drop)</h3>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-sm btn-outline-secondary" id="addPresetColumnBtn">Dodaj przyklad</button>
              <button type="button" class="btn btn-sm btn-outline-primary" id="addColumnBtn">Dodaj kolumne</button>
            </div>
          </div>
          <div class="card-body">
            <div class="small text-secondary mb-2">
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
          <div class="card mb-4">
            <div class="card-header"><h3 class="card-title mb-0">Podglad CSV (10 rekordow)</h3></div>
            <div class="card-body">
              <textarea class="form-control" rows="14" readonly>{$previewCsv|escape}</textarea>
            </div>
          </div>
        {/if}

        <div class="d-flex justify-content-between mb-4">
          <a href="{$baseUrl}?controller=csvtemplates&action=index" class="btn btn-outline-secondary">Wroc do listy</a>
          <div class="d-flex gap-2">
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
  var availableFields = {$availableFieldsJson nofilter};
  var availableFunctions = {$availableFunctionsJson nofilter};
  var initialColumns = {$templateColumnsJson nofilter};

  var builder = document.getElementById('columnsBuilder');
  var addButton = document.getElementById('addColumnBtn');
  var addPresetButton = document.getElementById('addPresetColumnBtn');
  var form = document.getElementById('csv-template-form');
  var payloadInput = document.getElementById('columnsPayload');

  if (!builder || !addButton || !form || !payloadInput) {
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

  function fieldOptions(selected) {
    var html = '<option value="">Wybierz pole</option>';
    Object.keys(availableFields).forEach(function (key) {
      var isSelected = String(selected || '') === key ? ' selected' : '';
      html += '<option value="' + escapeHtml(key) + '"' + isSelected + '>' + escapeHtml(availableFields[key]) + '</option>';
    });
    return html;
  }

  function fieldInsertOptions() {
    var html = '<option value="">Wybierz pole do wstawienia</option>';
    Object.keys(availableFields).forEach(function (key) {
      html += '<option value="' + escapeHtml(key) + '">' + escapeHtml(availableFields[key]) + '</option>';
    });
    return html;
  }

  function functionOptions(selected) {
    var html = '';
    Object.keys(availableFunctions).forEach(function (key) {
      var isSelected = String(selected || 'concat') === key ? ' selected' : '';
      html += '<option value="' + escapeHtml(key) + '"' + isSelected + '>' + escapeHtml(availableFunctions[key]) + '</option>';
    });
    return html;
  }

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
      var type = String(item && item.type ? item.type : 'static');
      var value = String(item && item.value ? item.value : '');
      var staticStyle = type === 'static' ? '' : ' style="display:none;"';
      return '<div class="border rounded p-2 mb-2 bg-light image-layout-item" draggable="true">'
        + '<div class="d-flex justify-content-between align-items-center gap-2">'
        + '<span class="text-secondary small" style="cursor:move;">:: przeciagnij</span>'
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
    var imageOptions = data.settings && data.settings.image_options ? data.settings.image_options : {};

    var card = document.createElement('div');
    card.className = 'card border column-card';
    card.draggable = true;
    card.innerHTML = ''
      + '<div class="card-body py-2">'
      + '  <div class="d-flex justify-content-between align-items-center mb-2">'
      + '    <span class="text-secondary small" style="cursor:move;">:: przeciagnij</span>'
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
      + '    <div class="col-md-4 computed-wrap"><label class="form-label small">Argumenty (JSON)</label><textarea class="form-control form-control-sm col-fn-args" rows="2">' + escapeHtml(JSON.stringify(data.settings.args || {}, null, 0)) + '</textarea><div class="row g-1 mt-1"><div class="col-12"><input type="text" class="form-control form-control-sm col-fn-field-search" placeholder="Szukaj pola do JSON"></div><div class="col-8"><select class="form-select form-select-sm col-fn-field-insert">' + fieldInsertOptions() + '</select></div><div class="col-4 d-grid"><button type="button" class="btn btn-sm btn-outline-secondary insert-field-token">Wstaw do JSON</button></div></div><div class="form-text">Wstawi token w formacie <code>field:product.sku</code> w miejscu kursora.</div></div>'
      + '    <div class="col-md-4"><label class="form-label small">Format</label><input type="text" class="form-control form-control-sm col-format" placeholder="np. date:Y-m-d lub number:2:,: " value="' + escapeHtml(data.settings.format || '') + '"></div>'
      + '    <div class="col-md-2"><label class="form-label small">Array sep.</label><input type="text" class="form-control form-control-sm col-array-sep" value="' + escapeHtml(data.settings.array_separator || '') + '"></div>'
      + '  </div>'
      + '  <div class="border rounded p-2 mt-2">'
      + '    <div class="small fw-semibold mb-2">Warunek</div>'
      + '    <div class="row g-2">'
      + '      <div class="col-md-3"><input type="text" class="form-control form-control-sm cond-field" placeholder="field path" value="' + escapeHtml(condition.field || '') + '"></div>'
      + '      <div class="col-md-2"><select class="form-select form-select-sm cond-op"><option value="eq">==</option><option value="neq">!=</option><option value="gt">></option><option value="gte">>=</option><option value="lt"><</option><option value="lte"><=</option><option value="contains">contains</option></select></div>'
      + '      <div class="col-md-2"><input type="text" class="form-control form-control-sm cond-value" placeholder="wartosc" value="' + escapeHtml(condition.value || '') + '"></div>'
      + '      <div class="col-md-2"><input type="text" class="form-control form-control-sm cond-then" placeholder="then" value="' + escapeHtml(condition.then || '') + '"></div>'
      + '      <div class="col-md-3"><input type="text" class="form-control form-control-sm cond-else" placeholder="else" value="' + escapeHtml(conditionElse) + '"></div>'
      + '    </div>'
      + '  </div>'
      + '  <div class="border rounded p-2 mt-2">'
      + '    <div class="d-flex justify-content-between align-items-center mb-2">'
      + '      <div class="small fw-semibold">Mapowanie wartosci</div>'
      + '      <button type="button" class="btn btn-sm btn-outline-secondary add-mapping">Dodaj mapowanie</button>'
      + '    </div>'
      + '    <div class="mapping-list">' + mappingRowsHtml(data.mappings) + '</div>'
      + '  </div>'
      + '  <div class="border rounded p-2 mt-2 image-layout-wrap" style="display:none;">'
      + '    <div class="row g-2 mb-3">'
      + '      <div class="col-12"><label class="form-label small">Makro miniatur</label><textarea class="form-control form-control-sm image-option-thumbnail-macro" rows="2">' + escapeHtml(imageOptions.thumbnail_macro || '{ldelim}{ldelim}base_directory{rdelim}{rdelim}\\{ldelim}{ldelim}contours{rdelim}{rdelim}\\{ldelim}{ldelim}collection_code{rdelim}{rdelim}\\miniatura_t_{ldelim}{ldelim}index{rdelim}{rdelim}.png') + '</textarea></div>'
      + '      <div class="col-12"><label class="form-label small">Makro mockupow / gridow</label><textarea class="form-control form-control-sm image-option-mockup-macro" rows="2">' + escapeHtml(imageOptions.mockup_macro || '{ldelim}{ldelim}base_directory{rdelim}{rdelim}\\{ldelim}{ldelim}contours{rdelim}{rdelim}\\mockup_{ldelim}{ldelim}index{rdelim}{rdelim}.jpg') + '</textarea></div>'
      + '      <div class="col-12"><label class="form-label small">Makro zdjec</label><textarea class="form-control form-control-sm image-option-image-macro" rows="2">' + escapeHtml(imageOptions.image_macro || '{ldelim}{ldelim}base_directory{rdelim}{rdelim}\\{ldelim}{ldelim}contours{rdelim}{rdelim}\\{ldelim}{ldelim}collection_code{rdelim}{rdelim}\\{ldelim}{ldelim}index{rdelim}{rdelim}.jpg') + '</textarea></div>'
      + '      <div class="col-12 small text-secondary">Tokeny: {ldelim}{ldelim}base_directory{rdelim}{rdelim}, {ldelim}{ldelim}contours{rdelim}{rdelim}, {ldelim}{ldelim}collection_code{rdelim}{rdelim}, {ldelim}{ldelim}collection_name{rdelim}{rdelim}, {ldelim}{ldelim}product_name{rdelim}{rdelim}, {ldelim}{ldelim}product_slug{rdelim}{rdelim}, {ldelim}{ldelim}sku{rdelim}{rdelim}, {ldelim}{ldelim}old_sku{rdelim}{rdelim}, {ldelim}{ldelim}price{rdelim}{rdelim}, {ldelim}{ldelim}index{rdelim}{rdelim}, {ldelim}{ldelim}index0{rdelim}{rdelim}</div>'
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

    var condOp = card.querySelector('.cond-op');
    condOp.value = condition.operator || 'eq';

    function updateVisibility() {
      var type = typeSelect.value;
      var fieldValue = card.querySelector('.col-field').value;
      card.querySelector('.field-wrap').style.display = (type === 'field') ? '' : 'none';
      card.querySelector('.static-wrap').style.display = (type === 'static') ? '' : 'none';
      var computedFields = card.querySelectorAll('.computed-wrap');
      for (var i = 0; i < computedFields.length; i++) {
        computedFields[i].style.display = (type === 'computed') ? '' : 'none';
      }
      var imageLayoutWrap = card.querySelector('.image-layout-wrap');
      if (imageLayoutWrap) {
        imageLayoutWrap.style.display = (type === 'field' && (fieldValue === 'product.generated_images' || fieldValue === 'images' || fieldValue === 'generated_images')) ? '' : 'none';
      }
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
    });

    card.querySelector('.duplicate-column').addEventListener('click', function () {
      builder.insertBefore(createColumnCard(collectCardData(card)), card.nextSibling);
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

    card.addEventListener('click', function (event) {
      if (event.target.classList.contains('remove-mapping')) {
        var row = event.target.closest('.mapping-row');
        if (row) {
          row.remove();
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

    card.addEventListener('dragstart', function () {
      card.classList.add('opacity-50');
      card.dataset.dragging = '1';
    });

    card.addEventListener('dragend', function () {
      card.classList.remove('opacity-50');
      card.dataset.dragging = '0';
    });

    card.addEventListener('dragover', function (event) {
      event.preventDefault();
    });

    card.addEventListener('drop', function (event) {
      event.preventDefault();
      var dragging = builder.querySelector('.column-card[data-dragging="1"]');
      if (!dragging || dragging === card) {
        return;
      }

      builder.insertBefore(dragging, card);
    });

    function bindImageLayoutInteractions(scope) {
      var items = scope.querySelectorAll('.image-layout-item');
      for (var index = 0; index < items.length; index++) {
        (function (item) {
          if (item.dataset.imageLayoutBound === '1') {
            return;
          }

          item.dataset.imageLayoutBound = '1';
          item.addEventListener('dragstart', function (event) {
            item.classList.add('opacity-50');
            item.dataset.dragging = '1';
            event.stopPropagation();
          });

          item.addEventListener('dragend', function (event) {
            item.classList.remove('opacity-50');
            item.dataset.dragging = '0';
            event.stopPropagation();
          });

          item.addEventListener('dragover', function (event) {
            event.preventDefault();
            event.stopPropagation();
          });

          item.addEventListener('drop', function (event) {
            event.preventDefault();
            event.stopPropagation();
            var list = scope.querySelector('.image-layout-list');
            var dragging = list ? list.querySelector('.image-layout-item[data-dragging="1"]') : null;
            if (!list || !dragging || dragging === item) {
              return;
            }

            list.insertBefore(dragging, item);
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

    var conditionField = card.querySelector('.cond-field').value.trim();
    var condition = {};
    if (conditionField !== '') {
      condition = {
        field: conditionField,
        operator: card.querySelector('.cond-op').value,
        value: card.querySelector('.cond-value').value,
        then: card.querySelector('.cond-then').value,
        else: card.querySelector('.cond-else').value
      };
    }

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
        condition: condition
      },
      mappings: mappings
    };
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
  });

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
    });
  }

  form.addEventListener('submit', function () {
    payloadInput.value = JSON.stringify(collectColumns());
  });

  if (!Array.isArray(initialColumns) || initialColumns.length === 0) {
    builder.appendChild(createColumnCard());
  } else {
    initialColumns.forEach(function (column) {
      builder.appendChild(createColumnCard(column));
    });
  }
})();
</script>
