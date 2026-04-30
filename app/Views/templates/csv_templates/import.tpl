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
            {if $sourceContext|default:'' eq 'products'}
              <li class="breadcrumb-item"><a href="{$baseUrl}?controller=products&action=index">Produkty</a></li>
            {else}
              <li class="breadcrumb-item"><a href="{$baseUrl}?controller=csvtemplates&action=index">Szablony CSV</a></li>
            {/if}
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

      <div class="card mb-4">
          <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <h3 class="card-title mb-1">Import z mapowaniem kolumn</h3>
            <div class="small text-secondary">Wczytaj plik CSV, sprawdz naglowki i wybierz, do jakiego pola produktu ma trafic kazda kolumna.</div>
          </div>
          <a href="{$backUrl|escape}" class="btn btn-outline-secondary">{$backLabel|escape}</a>
        </div>
      </div>

      {if $stage eq 'upload'}
        <div class="card">
          <div class="card-header"><h3 class="card-title mb-0">1. Wczytaj CSV</h3></div>
          <div class="card-body">
            <form method="post" action="{$baseUrl}?controller=csvtemplates&action=previewimport" enctype="multipart/form-data" class="row g-3">
              <input type="hidden" name="source" value="{$sourceContext|default:''|escape}">
              <div class="col-12">
                <label class="form-label">Tryb importu</label>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="card h-100 border-primary shadow-sm">
                      <div class="card-body">
                        <div class="form-check">
                          <input type="radio" name="import_mode" value="create" class="form-check-input"{if $importConfig.import_mode|default:'create' neq 'update'} checked{/if}>
                          <span class="form-check-label fw-semibold">Import nowych produktow</span>
                        </div>
                        <div class="small text-secondary mt-2">Tworzy nowe rekordy. Nie uzywa identyfikatora aktualizacji i nie nadpisuje istniejacych produktow.</div>
                      </div>
                    </label>
                  </div>
                  <div class="col-md-6">
                    <label class="card h-100 border-warning shadow-sm">
                      <div class="card-body">
                        <div class="form-check">
                          <input type="radio" name="import_mode" value="update" class="form-check-input"{if $importConfig.import_mode|default:'create' eq 'update'} checked{/if}>
                          <span class="form-check-label fw-semibold">Import aktualizacyjny</span>
                        </div>
                        <div class="small text-secondary mt-2">Szuka istniejacych rekordow po wskazanym identyfikatorze, a potem po SKU lub ID produktu.</div>
                      </div>
                    </label>
                  </div>
                </div>
              </div>
              <div class="col-lg-6">
                <label class="form-label">Plik CSV</label>
                <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv" required>
              </div>
              <div class="col-lg-2">
                <label class="form-label">Separator</label>
                <select name="delimiter" class="form-select">
                  <option value="auto">Auto</option>
                  <option value=";">;</option>
                  <option value=",">,</option>
                  <option value="|">|</option>
                  <option value="	">TAB</option>
                </select>
              </div>
              <div class="col-lg-2">
                <label class="form-label">Kodowanie</label>
                <select name="encoding" class="form-select">
                  <option value="UTF-8">UTF-8</option>
                  <option value="WINDOWS-1250">Windows-1250</option>
                </select>
              </div>
              <div class="col-lg-2">
                <label class="form-label">Naglowek</label>
                <select name="has_header" class="form-select">
                  <option value="1">Pierwszy wiersz to naglowki</option>
                  <option value="0">Brak naglowkow</option>
                </select>
              </div>
              <div class="col-12">
                <div class="small text-secondary">
                  Lista pol do mapowania jest zgodna z modulem <code>Szablony CSV</code>, lacznie z polami wlasnymi i uzywanymi parametrami Allegro.
                </div>
              </div>
              <div class="col-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">Analizuj kolumny</button>
              </div>
            </form>
          </div>
        </div>
      {/if}

      {if $stage eq 'mapping'}
        <div class="card mb-4">
          <div class="card-header"><h3 class="card-title mb-0">2. Mapowanie kolumn</h3></div>
          <div class="card-body">
            <div class="row g-3 mb-3">
              <div class="col-md-4"><div class="small text-secondary">Wykryty separator</div><div class="fw-semibold">{$detectedDelimiter|escape}</div></div>
              <div class="col-md-4"><div class="small text-secondary">Kodowanie</div><div class="fw-semibold">{$detectedEncoding|escape}</div></div>
              <div class="col-md-4"><div class="small text-secondary">Kolumny</div><div class="fw-semibold">{count($csvHeaders)}</div></div>
            </div>

            <form method="post" action="{$baseUrl}?controller=csvtemplates&action=runimport">
              <input type="hidden" name="source" value="{$sourceContext|default:''|escape}">
              <input type="hidden" name="import_token" value="{$importToken|escape}">
              <input type="hidden" name="import_mode" value="{$importConfig.import_mode|default:'create'|escape}">
              <input type="hidden" name="delimiter" value="{$importConfig.delimiter|escape}">
              <input type="hidden" name="encoding" value="{$importConfig.encoding|escape}">
              <input type="hidden" name="has_header" value="{$importConfig.has_header|escape}">

              <div class="card mb-4 border-0 bg-body-tertiary">
                <div class="card-body">
                  <div class="row g-3 align-items-end">
                    <div class="col-lg-4">
                      <label class="form-label">Zapisany profil importu</label>
                      <select name="import_profile_id" class="form-select">
                        <option value="0">Bez profilu</option>
                        {foreach $importProfiles|default:[] as $profile}
                          <option value="{$profile.id|escape}"{if $selectedImportProfile.id|default:0 == $profile.id} selected{/if}>
                            {$profile.name|escape} ({if $profile.import_mode|default:'create' eq 'update'}aktualizacja{else}nowe produkty{/if})
                          </option>
                        {/foreach}
                      </select>
                      <div class="form-text">Po wyborze profilu mozesz wczytac jego mapowanie kolumn bez ponownego dodawania pliku.</div>
                    </div>
                    <div class="col-lg-4">
                      <label class="form-label">Kategoria docelowa</label>
                      <select name="target_category_id" class="form-select">
                        <option value="0">Bez wymuszenia kategorii</option>
                        {foreach $categories as $category}
                          <option value="{$category.id|escape}"{if $importConfig.target_category_id|default:0 == $category.id} selected{/if}>
                            {$category.name|escape} ({$category.sku_prefix|default:'PRD'|escape})
                          </option>
                        {/foreach}
                      </select>
                      <div class="form-text">Wybrana kategoria zostanie przypisana importowanym rekordom i posluzy do generowania SKU dla nowych produktow.</div>
                    </div>
                    <div class="col-lg-4">
                      <button type="submit" class="btn btn-outline-secondary w-100" formaction="{$baseUrl}?controller=csvtemplates&action=remapimport" formnovalidate>Wczytaj profil do mapowania</button>
                    </div>
                    <div class="col-lg-6">
                      <label class="form-label">Profil ustawien importu</label>
                      <input type="text" name="import_profile_name" class="form-control" value="{$selectedImportProfile.name|default:''|escape}" placeholder="np. Aktualizacja hurtowni etui">
                      <div class="form-text">Zapisze tryb importu, mapowanie kolumn, identyfikator aktualizacji, powiazania i reguly znajdz/zamien.</div>
                    </div>
                    <div class="col-lg-6">
                      <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="save_import_profile" value="1" id="save-import-profile"{if $selectedImportProfile.id|default:0 > 0} checked{/if}>
                        <label class="form-check-label" for="save-import-profile">
                          Zapisz lub zaktualizuj profil podczas uruchomienia importu
                        </label>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              {if $importConfig.target_category_id|default:0 > 0}
                <div class="alert alert-info">
                  Wybrana kategoria docelowa zostanie wymuszona dla importowanych rekordow, a nowe produkty dostana SKU zgodne z jej prefiksem.
                </div>
              {/if}

              <div class="row g-3 mb-3">
                {if $importConfig.import_mode|default:'create' eq 'update'}
                  <div class="col-lg-6">
                    <label class="form-label">Kolumna identyfikatora aktualizacji</label>
                    <div class="form-text mt-0 mb-2">Wybierz jedna kolumne, po ktorej import ma szukac istniejacego produktu do aktualizacji. Dziala dla mapowania do <code>ID</code>, <code>SKU</code>, <code>EAN</code> albo <code>Pole wlasne</code>.</div>
                  </div>
                {else}
                  <div class="col-lg-6">
                    <label class="form-label">Import nowych produktow</label>
                    <div class="form-text mt-0 mb-2">W tym trybie import tworzy nowe rekordy i pomija identyfikator aktualizacji, wiec nic nie zostanie nadpisane po SKU, ID ani polu wlasnym.</div>
                  </div>
                {/if}
                <div class="col-lg-6">
                  <label class="form-label">Kolumna klucza do laczenia po liscie OLD_SKU</label>
                  <select name="derived_link_old_sku_match_column" class="form-select">
                    <option value="">Domyslnie: custom field old_sku</option>
                    {foreach $csvHeaders as $index => $header}
                      <option value="{$index|escape}"{if $importConfig.derived_link_old_sku_match_column|default:'' eq $index} selected{/if}>
                        {$index+1}. {$header|escape}
                      </option>
                    {/foreach}
                  </select>
                  <div class="form-text">Wybierz kolumne, po ktorej wartosci z listy typu <code>13636,13448</code> maja szukac produktow do powiazania. Jesli nic nie wybierzesz, import szuka po custom field <code>old_sku</code>.</div>
                </div>
              </div>

              <div class="table-responsive mb-4">
                <table class="table table-sm table-striped table-bordered align-middle">
                  <thead class="table-light">
                    <tr>
                      <th style="width: 60px;">#</th>
                      <th>Kolumna CSV</th>
                      <th>Przykladowe wartosci</th>
                      <th style="min-width: 360px;">Mapuj do pola i transformacja</th>
                      {if $importConfig.import_mode|default:'create' eq 'update'}
                        <th style="width: 170px;">Identyfikator aktualizacji</th>
                      {/if}
                      <th style="width: 260px;">Powiazane produkty</th>
                    </tr>
                  </thead>
                  <tbody>
                    {foreach $csvHeaders as $index => $header}
                      <tr>
                        <td>{$index+1}</td>
                        <td class="fw-semibold">{$header|escape}</td>
                        <td>
                          {foreach $csvSampleRows as $sample}
                            <div class="small text-secondary">{$sample[$header]|default:''|truncate:90|escape}</div>
                          {foreachelse}
                            <span class="text-secondary small">Brak probek</span>
                          {/foreach}
                        </td>
                        <td>
                          <select name="column_mapping[{$index}]" class="form-select form-select-sm">
                            {foreach $mappingOptions as $fieldKey => $fieldLabel}
                              <option value="{$fieldKey|escape}"{if $importMapping[$index]|default:'__skip__' eq $fieldKey} selected{/if}>{$fieldLabel|escape}</option>
                            {/foreach}
                          </select>
                          <div class="row g-2 mt-2">
                            <div class="col-md-6">
                              <label class="form-label small mb-1">Znajdz</label>
                              <input type="text" name="column_transforms[{$index}][find]" class="form-control form-control-sm" value="{$columnTransforms[$index].find|default:''|escape}" placeholder="np. Samsung">
                            </div>
                            <div class="col-md-6">
                              <label class="form-label small mb-1">Zamien</label>
                              <input type="text" name="column_transforms[{$index}][replace]" class="form-control form-control-sm" value="{$columnTransforms[$index].replace|default:''|escape}" placeholder="np. SAMSUNG">
                            </div>
                          </div>
                          <div class="form-text small mt-1">Regula dziala na wartosci tej kolumny przed mapowaniem do rekordu.</div>
                        </td>
                        {if $importConfig.import_mode|default:'create' eq 'update'}
                          <td>
                            <div class="form-check">
                              <input class="form-check-input" type="radio" name="update_identifier_column" value="{$index|escape}" id="update-identifier-column-{$index}"{if $importConfig.update_identifier_column|default:'' eq $index} checked{/if}>
                              <label class="form-check-label small" for="update-identifier-column-{$index}">
                                Uzyj tej kolumny
                              </label>
                            </div>
                          </td>
                        {/if}
                        <td>
                          <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="derived_link_columns[]" value="{$index|escape}" id="derived-link-column-{$index}"{if in_array($index, $importConfig.derived_link_columns|default:[])} checked{/if}>
                            <label class="form-check-label small" for="derived-link-column-{$index}">
                              Grupuj produkty po tej kolumnie
                            </label>
                          </div>
                          <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="derived_link_old_sku_columns[]" value="{$index|escape}" id="derived-link-old-sku-column-{$index}"{if in_array($index, $importConfig.derived_link_old_sku_columns|default:[])} checked{/if}>
                            <label class="form-check-label small" for="derived-link-old-sku-column-{$index}">
                              Lacz po liscie OLD_SKU jako powiazanie pochodne
                            </label>
                          </div>
                        </td>
                      </tr>
                    {/foreach}
                  </tbody>
                </table>
              </div>

              {if $importConfig.import_mode|default:'create' eq 'update'}
                <div class="alert alert-info">
                  Jesli wskazesz kolumne identyfikatora aktualizacji, import najpierw wyszuka produkt po tej jednej kolumnie. Gdy nic nie znajdzie, wraca do standardowego trybu: najpierw <code>SKU</code>, potem <code>ID produktu</code>. Gdy produktu nie ma, tworzy nowy rekord.
                </div>
              {else}
                <div class="alert alert-info">
                  Ten przebieg dziala jako osobny import nowych produktow: kazdy wiersz tworzy nowy rekord, a identyfikator aktualizacji jest calkowicie pominiety.
                </div>
              {/if}

              <div class="alert alert-secondary">
                Jesli zaznaczysz checkbox przy kolumnie, import po zapisaniu utworzy jedna grupe powiazanych produktow dla wszystkich rekordow z tym samym kodem z tej kolumny.
              </div>

              <div class="alert alert-secondary">
                Osobny checkbox <code>Lacz po liscie OLD_SKU jako powiazanie pochodne</code> traktuje wartosc kolumny jako liste typu <code>13636,13448</code>. Import szuka te rekordy po wybranej wyzej kolumnie klucza, a jesli jej nie ustawisz, po custom field <code>old_sku</code>.
              </div>

              <div class="d-flex justify-content-between gap-2 flex-wrap">
                <a href="{$baseUrl}?controller=csvtemplates&action=importproducts{if $sourceContext|default:'' ne ''}&source={$sourceContext|escape:'url'}{/if}" class="btn btn-outline-secondary">Wgraj inny plik</a>
                <button type="submit" class="btn btn-primary">Uruchom import</button>
              </div>
            </form>
          </div>
        </div>
      {/if}

      {if $stage eq 'result'}
        <div class="card mb-4">
          <div class="card-header"><h3 class="card-title mb-0">3. Wynik importu</h3></div>
          <div class="card-body">
            {if $savedImportProfileId|default:0 > 0}
              <div class="alert alert-success">
                Zapisano ustawienia profilu importu ID {$savedImportProfileId|escape}.
              </div>
            {/if}
            <div class="row g-3 mb-4">
              <div class="col-md-3"><div class="small text-secondary">Dodane</div><div class="display-6 fw-semibold">{$importResult.created|default:0}</div></div>
              <div class="col-md-3"><div class="small text-secondary">Zaktualizowane</div><div class="display-6 fw-semibold">{$importResult.updated|default:0}</div></div>
              <div class="col-md-3"><div class="small text-secondary">Pominiete</div><div class="display-6 fw-semibold">{$importResult.skipped|default:0}</div></div>
              <div class="col-md-3"><div class="small text-secondary">Bledy</div><div class="display-6 fw-semibold">{$importResult.errors|@count}</div></div>
            </div>

            {if $importResult.warnings}
              <div class="alert alert-warning">
                <div class="fw-semibold mb-2">Uwagi</div>
                {foreach $importResult.warnings as $warning}
                  <div class="small">{$warning|escape}</div>
                {/foreach}
              </div>
            {/if}

            {if $importResult.errors}
              <div class="alert alert-danger">
                <div class="fw-semibold mb-2">Bledy importu</div>
                {foreach $importResult.errors as $error}
                  <div class="small">{$error|escape}</div>
                {/foreach}
              </div>
            {/if}

            <div class="d-flex justify-content-between gap-2 flex-wrap">
              <a href="{$baseUrl}?controller=csvtemplates&action=importproducts{if $sourceContext|default:'' ne ''}&source={$sourceContext|escape:'url'}{/if}" class="btn btn-outline-secondary">Nowy import</a>
              <a href="{$baseUrl}?controller=products&action=index" class="btn btn-primary">Przejdz do produktow</a>
            </div>
          </div>
        </div>
      {/if}
    </div>
  </div>
</main>
