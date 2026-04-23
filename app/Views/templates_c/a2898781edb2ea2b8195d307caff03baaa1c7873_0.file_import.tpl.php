<?php
/* Smarty version 5.8.0, created on 2026-04-17 09:32:16
  from 'file:csv_templates/import.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.8.0',
  'unifunc' => 'content_69e1e2006bf870_20574665',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'a2898781edb2ea2b8195d307caff03baaa1c7873' => 
    array (
      0 => 'csv_templates/import.tpl',
      1 => 1776411082,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69e1e2006bf870_20574665 (\Smarty\Template $_smarty_tpl) {
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
              <div class="col-lg-6">
                <label class="form-label">Kategoria docelowa</label>
                <select name="target_category_id" class="form-select">
                  <option value="0">Bez wymuszenia kategorii</option>
                  <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('categories'), 'category');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('category')->value) {
$foreach0DoElse = false;
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
                <div class="form-text">Wybranie kategorii tutaj przypisze ja importowanym rekordom i wygeneruje SKU zgodnie z prefiksem tej kategorii.</div>
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
              <input type="hidden" name="delimiter" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('importConfig')['delimiter'], ENT_QUOTES, 'UTF-8', true);?>
">
              <input type="hidden" name="encoding" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('importConfig')['encoding'], ENT_QUOTES, 'UTF-8', true);?>
">
              <input type="hidden" name="has_header" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('importConfig')['has_header'], ENT_QUOTES, 'UTF-8', true);?>
">
              <input type="hidden" name="target_category_id" value="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('importConfig')['target_category_id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
">

              <?php if ((($tmp = $_smarty_tpl->getValue('importConfig')['target_category_id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp) > 0) {?>
                <div class="alert alert-info">
                  Wybrana kategoria docelowa zostanie wymuszona dla importowanych rekordow, a nowe produkty dostana SKU zgodne z jej prefiksem.
                </div>
              <?php }?>

              <div class="table-responsive mb-4">
                <table class="table table-sm table-striped table-bordered align-middle">
                  <thead class="table-light">
                    <tr>
                      <th style="width: 60px;">#</th>
                      <th>Kolumna CSV</th>
                      <th>Przykladowe wartosci</th>
                      <th style="min-width: 280px;">Mapuj do pola</th>
                      <th style="width: 170px;">Powiazane produkty</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('csvHeaders'), 'header', false, 'index');
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('index')->value => $_smarty_tpl->getVariable('header')->value) {
$foreach1DoElse = false;
?>
                      <tr>
                        <td><?php echo $_smarty_tpl->getValue('index')+1;?>
</td>
                        <td class="fw-semibold"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('header'), ENT_QUOTES, 'UTF-8', true);?>
</td>
                        <td>
                          <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('csvSampleRows'), 'sample');
$foreach2DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('sample')->value) {
$foreach2DoElse = false;
?>
                            <div class="small text-secondary"><?php echo htmlspecialchars((string)$_smarty_tpl->getSmarty()->getModifierCallback('truncate')((($tmp = $_smarty_tpl->getValue('sample')[$_smarty_tpl->getValue('header')] ?? null)===null||$tmp==='' ? '' ?? null : $tmp),90), ENT_QUOTES, 'UTF-8', true);?>
</div>
                          <?php
}
if ($foreach2DoElse) {
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
$foreach3DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('fieldKey')->value => $_smarty_tpl->getVariable('fieldLabel')->value) {
$foreach3DoElse = false;
?>
                              <option value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('fieldKey'), ENT_QUOTES, 'UTF-8', true);?>
"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('fieldLabel'), ENT_QUOTES, 'UTF-8', true);?>
</option>
                            <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                          </select>
                        </td>
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
                        </td>
                      </tr>
                    <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                  </tbody>
                </table>
              </div>

              <div class="alert alert-info">
                Import aktualizuje istniejace produkty najpierw po <code>SKU</code>, a jesli go nie znajdzie, probuje po <code>ID produktu</code>. Gdy produktu nie ma, tworzy nowy rekord.
              </div>

              <div class="alert alert-secondary">
                Jesli zaznaczysz checkbox przy kolumnie, import po zapisaniu utworzy jedna grupe powiazanych produktow dla wszystkich rekordow z tym samym kodem z tej kolumny.
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
$foreach4DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('warning')->value) {
$foreach4DoElse = false;
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
$foreach5DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('error')->value) {
$foreach5DoElse = false;
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
