<?php
/* Smarty version 5.8.0, created on 2026-04-23 22:23:04
  from 'file:categories/index.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.8.0',
  'unifunc' => 'content_69ea7fa858c691_01707626',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '6561e12d064ccd9e70d2ea244a63aed8a9063cdd' => 
    array (
      0 => 'categories/index.tpl',
      1 => 1776975198,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69ea7fa858c691_01707626 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/pfuuseajvz/domains/magazyn.altreo.pl/public_html/crm/new_version/app/Views/templates/categories';
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
      <?php if ($_smarty_tpl->getValue('flashSuccess')) {?>
        <div class="alert alert-success"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('flashSuccess'), ENT_QUOTES, 'UTF-8', true);?>
</div>
      <?php }?>
      <?php if ($_smarty_tpl->getValue('flashError')) {?>
        <div class="alert alert-danger"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('flashError'), ENT_QUOTES, 'UTF-8', true);?>
</div>
      <?php }?>

      <div class="card mb-4">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <h3 class="card-title mb-1">Wszystkie kategorie</h3>
            <div class="text-secondary small">Kategorie sa wykorzystywane przy tworzeniu i edycji produktow.</div>
          </div>
          <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=categories&action=create" class="btn btn-success">Dodaj kategorie</a>
        </div>
      </div>

      <div class="card">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-striped table-hover table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>ID</th>
                  <th>Nazwa</th>
                  <th>Slug</th>
                  <th>Prefix SKU</th>
                  <th>Allegro ID</th>
                  <th>Empik ID</th>
                  <th>Opis</th>
                  <th>Produkty</th>
                  <th>Utworzono</th>
                  <th>Zmieniono</th>
                  <th class="text-end">Akcje</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($_smarty_tpl->getValue('categories')) {?>
                  <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('categories'), 'category');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('category')->value) {
$foreach0DoElse = false;
?>
                    <tr>
                      <td><?php echo $_smarty_tpl->getValue('category')['id'];?>
</td>
                      <td class="fw-semibold"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('category')['name'], ENT_QUOTES, 'UTF-8', true);?>
</td>
                      <td><code><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('category')['slug'], ENT_QUOTES, 'UTF-8', true);?>
</code></td>
                      <td><span class="badge text-bg-dark"><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('category')['sku_prefix'] ?? null)===null||$tmp==='' ? 'PRD' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</span></td>
                      <td><?php if ($_smarty_tpl->getValue('category')['allegro_category_id']) {?><code><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('category')['allegro_category_id'], ENT_QUOTES, 'UTF-8', true);?>
</code><?php } else { ?>-<?php }?></td>
                      <td><?php if ($_smarty_tpl->getValue('category')['empik_category_id']) {?><code><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('category')['empik_category_id'], ENT_QUOTES, 'UTF-8', true);?>
</code><?php } else { ?>-<?php }?></td>
                      <td><?php echo htmlspecialchars((string)$_smarty_tpl->getSmarty()->getModifierCallback('truncate')((($tmp = $_smarty_tpl->getValue('category')['description'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp),100), ENT_QUOTES, 'UTF-8', true);?>
</td>
                      <td><span class="badge text-bg-secondary"><?php echo (($tmp = $_smarty_tpl->getValue('category')['products_count'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp);?>
</span></td>
                      <td><?php echo (($tmp = $_smarty_tpl->getValue('category')['created_at'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp);?>
</td>
                      <td><?php echo (($tmp = $_smarty_tpl->getValue('category')['updated_at'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp);?>
</td>
                      <td class="text-end">
                        <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=categories&action=edit&id=<?php echo $_smarty_tpl->getValue('category')['id'];?>
" class="btn btn-sm btn-outline-primary">Edytuj</a>
                        <?php if ((($tmp = $_smarty_tpl->getValue('category')['products_count'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp) > 0) {?>
                          <button type="button" class="btn btn-sm btn-outline-danger" disabled>Usun</button>
                        <?php } else { ?>
                          <form method="post" action="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=categories&action=delete" class="d-inline">
                            <input type="hidden" name="id" value="<?php echo $_smarty_tpl->getValue('category')['id'];?>
">
                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Usunac te kategorie?');">Usun</button>
                          </form>
                        <?php }?>
                      </td>
                    </tr>
                  <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                <?php } else { ?>
                  <tr>
                    <td colspan="11" class="text-center py-4">Brak kategorii do wyswietlenia.</td>
                  </tr>
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
