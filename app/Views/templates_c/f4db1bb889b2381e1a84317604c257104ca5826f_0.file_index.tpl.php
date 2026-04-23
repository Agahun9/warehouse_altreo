<?php
/* Smarty version 5.8.0, created on 2026-04-17 09:07:40
  from 'file:csv_templates/index.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.8.0',
  'unifunc' => 'content_69e1dc3cee07d6_97118998',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'f4db1bb889b2381e1a84317604c257104ca5826f' => 
    array (
      0 => 'csv_templates/index.tpl',
      1 => 1776409273,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69e1dc3cee07d6_97118998 (\Smarty\Template $_smarty_tpl) {
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
            <h3 class="card-title mb-1">Szablony eksportu</h3>
            <div class="text-secondary small">Tworzenie i zarzadzanie konfiguracjami CSV do eksportu produktów.</div>
          </div>
          <div class="d-flex gap-2">
            <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=csvtemplates&action=importproducts" class="btn btn-outline-primary">Import produktow</a>
            <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=csvtemplates&action=titlegenerator" class="btn btn-outline-secondary">Generator tytulow</a>
            <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=csvtemplates&action=create" class="btn btn-primary">Dodaj szablon</a>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">Presety</h3></div>
        <div class="card-body d-flex flex-wrap gap-2">
          <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('presets'), 'preset', false, 'presetKey');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('presetKey')->value => $_smarty_tpl->getVariable('preset')->value) {
$foreach0DoElse = false;
?>
            <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=csvtemplates&action=create&preset=<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('presetKey'), ENT_QUOTES, 'UTF-8', true);?>
" class="btn btn-outline-secondary btn-sm"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('preset')['name'], ENT_QUOTES, 'UTF-8', true);?>
</a>
          <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
        </div>
      </div>

      <div class="card">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-striped table-hover table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Nazwa</th>
                  <th>Opis</th>
                  <th>Format</th>
                  <th>Kolumny</th>
                  <th>Utworzono</th>
                  <th>Zmieniono</th>
                  <th class="text-end">Akcje</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($_smarty_tpl->getValue('templates')) {?>
                  <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('templates'), 'template');
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('template')->value) {
$foreach1DoElse = false;
?>
                    <tr>
                      <td class="fw-semibold"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('template')['name'], ENT_QUOTES, 'UTF-8', true);?>
</td>
                      <td><?php echo htmlspecialchars((string)$_smarty_tpl->getSmarty()->getModifierCallback('truncate')((($tmp = $_smarty_tpl->getValue('template')['description'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp),120), ENT_QUOTES, 'UTF-8', true);?>
</td>
                      <td>
                        <div><span class="badge text-bg-light border"><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('template')['delimiter'] ?? null)===null||$tmp==='' ? ';' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</span></div>
                        <div class="small text-secondary mt-1"><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('template')['encoding'] ?? null)===null||$tmp==='' ? 'UTF-8' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);
if ((($tmp = $_smarty_tpl->getValue('template')['add_bom'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp)) {?> + BOM<?php }?></div>
                      </td>
                      <td><span class="badge text-bg-secondary"><?php echo (($tmp = $_smarty_tpl->getValue('template')['columns_count'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp);?>
</span></td>
                      <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('template')['created_at'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
                      <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('template')['updated_at'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
                      <td class="text-end">
                        <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=csvtemplates&action=edit&id=<?php echo $_smarty_tpl->getValue('template')['id'];?>
" class="btn btn-sm btn-outline-primary">Edytuj</a>
                        <form method="post" action="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=csvtemplates&action=duplicate" class="d-inline">
                          <input type="hidden" name="id" value="<?php echo $_smarty_tpl->getValue('template')['id'];?>
">
                          <button type="submit" class="btn btn-sm btn-outline-secondary">Duplikuj</button>
                        </form>
                        <form method="post" action="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=csvtemplates&action=delete" class="d-inline" onsubmit="return confirm('Usunac szablon <?php echo strtr((string)$_smarty_tpl->getValue('template')['name'], array("\\" => "\\\\", "'" => "\\'", "\"" => "\\\"", "\r" => "\\r", 
						"\n" => "\\n", "</" => "<\/", "<!--" => "<\!--", "<s" => "<\s", "<S" => "<\S",
						"`" => "\\`", "\${" => "\\\$\{"));?>
?');">
                          <input type="hidden" name="id" value="<?php echo $_smarty_tpl->getValue('template')['id'];?>
">
                          <button type="submit" class="btn btn-sm btn-outline-danger">Usun</button>
                        </form>
                      </td>
                    </tr>
                  <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                <?php } else { ?>
                  <tr><td colspan="7" class="text-center py-4">Brak szablonow CSV.</td></tr>
                <?php }?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<?php }
}
