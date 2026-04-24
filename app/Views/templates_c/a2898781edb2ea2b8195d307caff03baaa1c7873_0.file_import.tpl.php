<?php
/* Smarty version 5.8.0, created on 2026-04-22 10:39:54
  from 'file:csv_templates/import.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.8.0',
  'unifunc' => 'content_69e8895a08e7e4_32458917',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'a2898781edb2ea2b8195d307caff03baaa1c7873' => 
    array (
      0 => 'csv_templates/import.tpl',
      1 => 1776847051,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69e8895a08e7e4_32458917 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/pfuuseajvz/domains/magazyn.altreo.pl/public_html/crm/new_version/app/Views/templates/csv_templates';
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
            <?php if ((($tmp = $_smarty_tpl->getValue('sourceContext') ?? null)===null||$tmp==='' ? '' ?? null : $tmp) == 'products') {?>
              <li class="breadcrumb-item"><a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=products&action=index">Produkty</a></li>
            <?php } else { ?>
              <li class="breadcrumb-item"><a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=csvtemplates&action=index">Szablony CSV</a></li>
            <?php }?>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('breadcrumbCurrent'), ENT_QUOTES, 'UTF-8', true);?>
</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      <?php if ($_smarty_tpl->getValue('flashSuccess')) {?><div class="alert alert-success"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('flashSuccess'), ENT_QUOTES, 'UTF-8', true);?>
</div><?php }?>
      <?php if ($_smarty_tpl->getValue('flashError')) {?><div class="alert alert-danger"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('flashError'), ENT_QUOTES, 'UTF-8', true);?>
</div><?php }?>

      <div class="card mb-4">
          <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <h3 class="card-title mb-1">Import z mapowaniem kolumn</h3>
            <div class="small text-secondary">Wczytaj plik CSV, sprawdz naglowki i wybierz, do jakiego pola produktu ma trafic kazda kolumna.</div>
          </div>
          <a href="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('backUrl'), ENT_QUOTES, 'UTF-8', true);?>
" class="btn btn-outline-secondary"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('backLabel'), ENT_QUOTES, 'UTF-8', true);?>
</a>
        </div>
      </div>

      <?php if ($_smarty_tpl->getValue('stage') == 'upload') {?>
        <div class="card">
          <div class="card-header"><h3 class="card-title mb-0">1. Wczytaj CSV</h3></div>
          <div class="card-body">
            <form method="post" action="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=csvtemplates&action=previewimport" enctype="multipart/form-data" class="row g-3">
              <input type="hidden" name="source" value="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('sourceContext') ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
">
              <div class="col-12">
                <label class="form-label">Tryb importu</label>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="card h-100 border-primary shadow-sm">
                      <div class="card-body">
                        <div class="form-check">
                          <input type="radio" name="import_mode" value="create" class="form-check-input"<?php if ((($tmp = $_smarty_tpl->getValue('importConfig')['import_mode'] ?? null)===null||$tmp==='' ? 'create' ?? null : $tmp) != 'update') {?> checked<?php }?>>
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
                          <input type="radio" name="import_mode" value="update" class="form-check-input"<?php if ((($tmp = $_smarty_tpl->getValue('importConfig')['import_mode'] ?? null)===null||$tmp==='' ? 'create' ?? null : $tmp) == 'update') {?> checked<?php }?>>
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
      <?php }?>

      <?php if ($_smarty_tpl->getValue('stage') == 'mapping') {?>
        <div class="card mb-4">
          <div class="card-header"><h3 class="card-title mb-0">2. Mapowanie kolumn</h3></div>
          <div class="card-body">
            <div class="row g-3 mb-3">
              <div class="col-md-4"><div class="small text-secondary">Wykryty separator</div><div class="fw-semibold"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('detectedDelimiter'), ENT_QUOTES, 'UTF-8', true);?>
</div></div>
              <div class="col-md-4"><div class="small text-secondary">Kodowanie</div><div class="fw-semibold"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('detectedEncoding'), ENT_QUOTES, 'UTF-8', true);?>
</div></div>
              <div class="col-md-4"><div class="small text-secondary">Kolumny</div><div class="fw-semibold"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('csvHeaders'));?>
</div></div>
            </div>

            <form method="post" action="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=csvtemplates&action=runimport">
              <input type="hidden" name="source" value="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('sourceContext') ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
">
              <input type="hidden" name="import_token" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('importToken'), ENT_QUOTES, 'UTF-8', true);?>
">
              <input type="hidden" name="import_mode" value="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('importConfig')['import_mode'] ?? null)===null||$tmp==='' ? 'create' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
">
              <input type="hidden" name="delimiter" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('importConfig')['delimiter'], ENT_QUOTES, 'UTF-8', true);?>
">
              <input type="hidden" name="encoding" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('importConfig')['encoding'], ENT_QUOTES, 'UTF-8', true);?>
">
              <input type="hidden" name="has_header" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('importConfig')['has_header'], ENT_QUOTES, 'UTF-8', true);?>
">

              <div class="card mb-4 border-0 bg-body-tertiary">
                <div class="card-body">
                  <div class="row g-3 align-items-end">
                    <div class="col-lg-4">
                      <label class="form-label">Zapisany profil importu</label>
                      <select name="import_profile_id" class="form-select">
                        <option value="0">Bez profilu</option>
                        <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, (($tmp = $_smarty_tpl->getValue('importProfiles') ?? null)===null||$tmp==='' ? array() ?? null : $tmp), 'profile');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('profile')->value) {
$foreach0DoElse = false;
?>
                          <option value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('profile')['id'], ENT_QUOTES, 'UTF-8', true);?>
"<?php if ((($tmp = $_smarty_tpl->getValue('selectedImportProfile')['id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp) == $_smarty_tpl->getValue('profile')['id']) {?> selected<?php }?>>
                            <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('profile')['name'], ENT_QUOTES, 'UTF-8', true);?>
 (<?php if ((($tmp = $_smarty_tpl->getValue('profile')['import_mode'] ?? null)===null||$tmp==='' ? 'create' ?? null : $tmp) == 'update') {?>aktualizacja<?php } else { ?>nowe produkty<?php }?>)
                          </option>
                        <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                      </select>
                      <div class="form-text">Po wyborze profilu mozesz wczytac jego mapowanie kolumn bez ponownego dodawania pliku.</div>
                    </div>
                    <div class="col-lg-4">
                      <label class="form-label">Kategoria docelowa</label>
                      <select name="target_category_id" class="form-select">
                        <option value="0">Bez wymuszenia kategorii</option>
                        <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('categories'), 'category');
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('category')->value) {
$foreach1DoElse = false;
?>
                          <option value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('category')['id'], ENT_QUOTES, 'UTF-8', true);?>
"<?php if ((($tmp = $_smarty_tpl->getValue('importConfig')['target_category_id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp) == $_smarty_tpl->getValue('category')['id']) {?> selected<?php }?>>
                            <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('category')['name'], ENT_QUOTES, 'UTF-8', true);?>
 (<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('category')['sku_prefix'] ?? null)===null||$tmp==='' ? 'PRD' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
)
                          </option>
                        <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                      </select>
                      <div class="form-text">Wybrana kategoria zostanie przypisana importowanym rekordom i posluzy do generowania SKU dla nowych produktow.</div>
                    </div>
                    <div class="col-lg-4">
                      <button type="submit" class="btn btn-outline-secondary w-100" formaction="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=csvtemplates&action=remapimport" formnovalidate>Wczytaj profil do mapowania</button>
                    </div>
                    <div class="col-lg-6">
                      <label class="form-label">Profil ustawien importu</label>
                      <input type="text" name="import_profile_name" class="form-control" value="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('selectedImportProfile')['name'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
" placeholder="np. Aktualizacja hurtowni etui">
                      <div class="form-text">Zapisze tryb importu, mapowanie kolumn, identyfikator aktualizacji, powiazania i reguly znajdz/zamien.</div>
                    </div>
                    <div class="col-lg-6">
                      <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="save_import_profile" value="1" id="save-import-profile"<?php if ((($tmp = $_smarty_tpl->getValue('selectedImportProfile')['id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp) > 0) {?> checked<?php }?>>
                        <label class="form-check-label" for="save-import-profile">
                          Zapisz lub zaktualizuj profil podczas uruchomienia importu
                        </label>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <?php if ((($tmp = $_smarty_tpl->getValue('importConfig')['target_category_id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp) > 0) {?>
                <div class="alert alert-info">
                  Wybrana kategoria docelowa zostanie wymuszona dla importowanych rekordow, a nowe produkty dostana SKU zgodne z jej prefiksem.
                </div>
              <?php }?>

              <div class="row g-3 mb-3">
                <?php if ((($tmp = $_smarty_tpl->getValue('importConfig')['import_mode'] ?? null)===null||$tmp==='' ? 'create' ?? null : $tmp) == 'update') {?>
                  <div class="col-lg-6">
                    <label class="form-label">Kolumna identyfikatora aktualizacji</label>
                    <div class="form-text mt-0 mb-2">Wybierz jedna kolumne, po ktorej import ma szukac istniejacego produktu do aktualizacji. Dziala dla mapowania do <code>ID</code>, <code>SKU</code>, <code>EAN</code> albo <code>Pole wlasne</code>.</div>
                  </div>
                <?php } else { ?>
                  <div class="col-lg-6">
                    <label class="form-label">Import nowych produktow</label>
                    <div class="form-text mt-0 mb-2">W tym trybie import tworzy nowe rekordy i pomija identyfikator aktualizacji, wiec nic nie zostanie nadpisane po SKU, ID ani polu wlasnym.</div>
                  </div>
                <?php }?>
                <div class="col-lg-6">
                  <label class="form-label">Kolumna klucza do laczenia po liscie OLD_SKU</label>
                  <select name="derived_link_old_sku_match_column" class="form-select">
                    <option value="">Domyslnie: custom field old_sku</option>
                    <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('csvHeaders'), 'header', false, 'index');
$foreach2DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('index')->value => $_smarty_tpl->getVariable('header')->value) {
$foreach2DoElse = false;
?>
                      <option value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('index'), ENT_QUOTES, 'UTF-8', true);?>
"<?php if ((($tmp = $_smarty_tpl->getValue('importConfig')['derived_link_old_sku_match_column'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp) == $_smarty_tpl->getValue('index')) {?> selected<?php }?>>
                        <?php echo $_smarty_tpl->getValue('index')+1;?>
. <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('header'), ENT_QUOTES, 'UTF-8', true);?>

                      </option>
                    <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
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
                      <?php if ((($tmp = $_smarty_tpl->getValue('importConfig')['import_mode'] ?? null)===null||$tmp==='' ? 'create' ?? null : $tmp) == 'update') {?>
                        <th style="width: 170px;">Identyfikator aktualizacji</th>
                      <?php }?>
                      <th style="width: 260px;">Powiazane produkty</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('csvHeaders'), 'header', false, 'index');
$foreach3DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('index')->value => $_smarty_tpl->getVariable('header')->value) {
$foreach3DoElse = false;
?>
                      <tr>
                        <td><?php echo $_smarty_tpl->getValue('index')+1;?>
</td>
                        <td class="fw-semibold"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('header'), ENT_QUOTES, 'UTF-8', true);?>
</td>
                        <td>
                          <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('csvSampleRows'), 'sample');
$foreach4DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('sample')->value) {
$foreach4DoElse = false;
?>
                            <div class="small text-secondary"><?php echo htmlspecialchars((string)$_smarty_tpl->getSmarty()->getModifierCallback('truncate')((($tmp = $_smarty_tpl->getValue('sample')[$_smarty_tpl->getValue('header')] ?? null)===null||$tmp==='' ? '' ?? null : $tmp),90), ENT_QUOTES, 'UTF-8', true);?>
</div>
                          <?php
}
if ($foreach4DoElse) {
?>
                            <span class="text-secondary small">Brak probek</span>
                          <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                        </td>
                        <td>
                          <select name="column_mapping[<?php echo $_smarty_tpl->getValue('index');?>
]" class="form-select form-select-sm">
                            <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('mappingOptions'), 'fieldLabel', false, 'fieldKey');
$foreach5DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('fieldKey')->value => $_smarty_tpl->getVariable('fieldLabel')->value) {
$foreach5DoElse = false;
?>
                              <option value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('fieldKey'), ENT_QUOTES, 'UTF-8', true);?>
"<?php if ((($tmp = $_smarty_tpl->getValue('importMapping')[$_smarty_tpl->getValue('index')] ?? null)===null||$tmp==='' ? '__skip__' ?? null : $tmp) == $_smarty_tpl->getValue('fieldKey')) {?> selected<?php }?>><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('fieldLabel'), ENT_QUOTES, 'UTF-8', true);?>
</option>
                            <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                          </select>
                          <div class="row g-2 mt-2">
                            <div class="col-md-6">
                              <label class="form-label small mb-1">Znajdz</label>
                              <input type="text" name="column_transforms[<?php echo $_smarty_tpl->getValue('index');?>
][find]" class="form-control form-control-sm" value="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('columnTransforms')[$_smarty_tpl->getValue('index')]['find'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
" placeholder="np. Samsung">
                            </div>
                            <div class="col-md-6">
                              <label class="form-label small mb-1">Zamien</label>
                              <input type="text" name="column_transforms[<?php echo $_smarty_tpl->getValue('index');?>
][replace]" class="form-control form-control-sm" value="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('columnTransforms')[$_smarty_tpl->getValue('index')]['replace'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
" placeholder="np. SAMSUNG">
                            </div>
                          </div>
                          <div class="form-text small mt-1">Regula dziala na wartosci tej kolumny przed mapowaniem do rekordu.</div>
                        </td>
                        <?php if ((($tmp = $_smarty_tpl->getValue('importConfig')['import_mode'] ?? null)===null||$tmp==='' ? 'create' ?? null : $tmp) == 'update') {?>
                          <td>
                            <div class="form-check">
                              <input class="form-check-input" type="radio" name="update_identifier_column" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('index'), ENT_QUOTES, 'UTF-8', true);?>
" id="update-identifier-column-<?php echo $_smarty_tpl->getValue('index');?>
"<?php if ((($tmp = $_smarty_tpl->getValue('importConfig')['update_identifier_column'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp) == $_smarty_tpl->getValue('index')) {?> checked<?php }?>>
                              <label class="form-check-label small" for="update-identifier-column-<?php echo $_smarty_tpl->getValue('index');?>
">
                                Uzyj tej kolumny
                              </label>
                            </div>
                          </td>
                        <?php }?>
                        <td>
                          <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="derived_link_columns[]" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('index'), ENT_QUOTES, 'UTF-8', true);?>
" id="derived-link-column-<?php echo $_smarty_tpl->getValue('index');?>
"<?php if ($_smarty_tpl->getSmarty()->getModifierCallback('in_array')($_smarty_tpl->getValue('index'),(($tmp = $_smarty_tpl->getValue('importConfig')['derived_link_columns'] ?? null)===null||$tmp==='' ? array() ?? null : $tmp))) {?> checked<?php }?>>
                            <label class="form-check-label small" for="derived-link-column-<?php echo $_smarty_tpl->getValue('index');?>
">
                              Grupuj produkty po tej kolumnie
                            </label>
                          </div>
                          <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="derived_link_old_sku_columns[]" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('index'), ENT_QUOTES, 'UTF-8', true);?>
" id="derived-link-old-sku-column-<?php echo $_smarty_tpl->getValue('index');?>
"<?php if ($_smarty_tpl->getSmarty()->getModifierCallback('in_array')($_smarty_tpl->getValue('index'),(($tmp = $_smarty_tpl->getValue('importConfig')['derived_link_old_sku_columns'] ?? null)===null||$tmp==='' ? array() ?? null : $tmp))) {?> checked<?php }?>>
                            <label class="form-check-label small" for="derived-link-old-sku-column-<?php echo $_smarty_tpl->getValue('index');?>
">
                              Lacz po liscie OLD_SKU jako powiazanie pochodne
                            </label>
                          </div>
                        </td>
                      </tr>
                    <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                  </tbody>
                </table>
              </div>

              <?php if ((($tmp = $_smarty_tpl->getValue('importConfig')['import_mode'] ?? null)===null||$tmp==='' ? 'create' ?? null : $tmp) == 'update') {?>
                <div class="alert alert-info">
                  Jesli wskazesz kolumne identyfikatora aktualizacji, import najpierw wyszuka produkt po tej jednej kolumnie. Gdy nic nie znajdzie, wraca do standardowego trybu: najpierw <code>SKU</code>, potem <code>ID produktu</code>. Gdy produktu nie ma, tworzy nowy rekord.
                </div>
              <?php } else { ?>
                <div class="alert alert-info">
                  Ten przebieg dziala jako osobny import nowych produktow: kazdy wiersz tworzy nowy rekord, a identyfikator aktualizacji jest calkowicie pominiety.
                </div>
              <?php }?>

              <div class="alert alert-secondary">
                Jesli zaznaczysz checkbox przy kolumnie, import po zapisaniu utworzy jedna grupe powiazanych produktow dla wszystkich rekordow z tym samym kodem z tej kolumny.
              </div>

              <div class="alert alert-secondary">
                Osobny checkbox <code>Lacz po liscie OLD_SKU jako powiazanie pochodne</code> traktuje wartosc kolumny jako liste typu <code>13636,13448</code>. Import szuka te rekordy po wybranej wyzej kolumnie klucza, a jesli jej nie ustawisz, po custom field <code>old_sku</code>.
              </div>

              <div class="d-flex justify-content-between gap-2 flex-wrap">
                <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=csvtemplates&action=importproducts<?php if ((($tmp = $_smarty_tpl->getValue('sourceContext') ?? null)===null||$tmp==='' ? '' ?? null : $tmp) != '') {?>&source=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sourceContext'));
}?>" class="btn btn-outline-secondary">Wgraj inny plik</a>
                <button type="submit" class="btn btn-primary">Uruchom import</button>
              </div>
            </form>
          </div>
        </div>
      <?php }?>

      <?php if ($_smarty_tpl->getValue('stage') == 'result') {?>
        <div class="card mb-4">
          <div class="card-header"><h3 class="card-title mb-0">3. Wynik importu</h3></div>
          <div class="card-body">
            <?php if ((($tmp = $_smarty_tpl->getValue('savedImportProfileId') ?? null)===null||$tmp==='' ? 0 ?? null : $tmp) > 0) {?>
              <div class="alert alert-success">
                Zapisano ustawienia profilu importu ID <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('savedImportProfileId'), ENT_QUOTES, 'UTF-8', true);?>
.
              </div>
            <?php }?>
            <div class="row g-3 mb-4">
              <div class="col-md-3"><div class="small text-secondary">Dodane</div><div class="display-6 fw-semibold"><?php echo (($tmp = $_smarty_tpl->getValue('importResult')['created'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp);?>
</div></div>
              <div class="col-md-3"><div class="small text-secondary">Zaktualizowane</div><div class="display-6 fw-semibold"><?php echo (($tmp = $_smarty_tpl->getValue('importResult')['updated'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp);?>
</div></div>
              <div class="col-md-3"><div class="small text-secondary">Pominiete</div><div class="display-6 fw-semibold"><?php echo (($tmp = $_smarty_tpl->getValue('importResult')['skipped'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp);?>
</div></div>
              <div class="col-md-3"><div class="small text-secondary">Bledy</div><div class="display-6 fw-semibold"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('importResult')['errors']);?>
</div></div>
            </div>

            <?php if ($_smarty_tpl->getValue('importResult')['warnings']) {?>
              <div class="alert alert-warning">
                <div class="fw-semibold mb-2">Uwagi</div>
                <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('importResult')['warnings'], 'warning');
$foreach6DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('warning')->value) {
$foreach6DoElse = false;
?>
                  <div class="small"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('warning'), ENT_QUOTES, 'UTF-8', true);?>
</div>
                <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
              </div>
            <?php }?>

            <?php if ($_smarty_tpl->getValue('importResult')['errors']) {?>
              <div class="alert alert-danger">
                <div class="fw-semibold mb-2">Bledy importu</div>
                <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('importResult')['errors'], 'error');
$foreach7DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('error')->value) {
$foreach7DoElse = false;
?>
                  <div class="small"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('error'), ENT_QUOTES, 'UTF-8', true);?>
</div>
                <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
              </div>
            <?php }?>

            <div class="d-flex justify-content-between gap-2 flex-wrap">
              <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=csvtemplates&action=importproducts<?php if ((($tmp = $_smarty_tpl->getValue('sourceContext') ?? null)===null||$tmp==='' ? '' ?? null : $tmp) != '') {?>&source=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sourceContext'));
}?>" class="btn btn-outline-secondary">Nowy import</a>
              <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=products&action=index" class="btn btn-primary">Przejdz do produktow</a>
            </div>
          </div>
        </div>
      <?php }?>
    </div>
  </div>
</main>
<?php }
}
