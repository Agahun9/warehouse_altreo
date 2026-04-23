<?php
/* Smarty version 5.8.0, created on 2026-04-17 10:26:34
  from 'file:csv_templates/title_form.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.8.0',
  'unifunc' => 'content_69e1eeba39d366_57234480',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'f276a3c6c1a59e1472e244e9a7c33972573e60c5' => 
    array (
      0 => 'csv_templates/title_form.tpl',
      1 => 1774552148,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69e1eeba39d366_57234480 (\Smarty\Template $_smarty_tpl) {
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
            <li class="breadcrumb-item"><a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=csvtemplates&action=index">Szablony CSV</a></li>
            <li class="breadcrumb-item"><a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=csvtemplates&action=titlegenerator">Generator tytulow</a></li>
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

      <form method="post" action="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('formAction'), ENT_QUOTES, 'UTF-8', true);?>
" id="title-template-form">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('titleTemplate')['id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
">

        <div class="card mb-4">
          <div class="card-header"><h3 class="card-title mb-0">Dane glowne</h3></div>
          <div class="card-body row g-3">
            <div class="col-md-6">
              <label class="form-label">Nazwa szablonu tytulu</label>
              <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('titleTemplate')['name'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Opis</label>
              <input type="text" name="description" class="form-control" value="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('titleTemplate')['description'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
">
            </div>
            <div class="col-12">
              <label class="form-label">Wzor tytulu</label>
              <textarea name="template_body" id="templateBody" class="form-control" rows="5" required><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('titleTemplate')['template_body'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</textarea>
              <div class="form-text">Przyklad: <code>Etui na Telefon <?php ob_start();?>{<?php $_prefixVariable1 = ob_get_clean();
echo $_prefixVariable1;?>
field:product.allegro_parameter.123} <?php ob_start();?>{<?php $_prefixVariable2 = ob_get_clean();
echo $_prefixVariable2;?>
field:product.allegro_parameter.456} wzory <?php ob_start();?>{<?php $_prefixVariable3 = ob_get_clean();
echo $_prefixVariable3;?>
option:collection_name}</code></div>
            </div>
          </div>
        </div>

        <div class="card mb-4">
          <div class="card-header"><h3 class="card-title mb-0">Wstaw token z listy</h3></div>
          <div class="card-body">
            <div class="row g-2 align-items-end">
              <div class="col-md-8">
                <label class="form-label">Dostepne tokeny</label>
                <select id="tokenSelect" class="form-select">
                  <option value="">Wybierz token do wstawienia</option>
                  <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('availableTitleTokens'), 'label', false, 'token');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('token')->value => $_smarty_tpl->getVariable('label')->value) {
$foreach0DoElse = false;
?>
                    <option value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('token'), ENT_QUOTES, 'UTF-8', true);?>
"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('label'), ENT_QUOTES, 'UTF-8', true);?>
 - <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('token'), ENT_QUOTES, 'UTF-8', true);?>
</option>
                  <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                </select>
              </div>
              <div class="col-md-4 d-grid">
                <button type="button" id="insertTokenBtn" class="btn btn-outline-primary">Wstaw do wzoru</button>
              </div>
            </div>
            <div class="mt-3">
              <div class="small fw-semibold mb-2">Szybkie tokeny eksportowe</div>
              <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-sm btn-outline-secondary js-quick-token" data-token="<?php ob_start();?>{<?php $_prefixVariable4 = ob_get_clean();
echo $_prefixVariable4;?>
option:collection_name}">Kolekcja</button>
                <button type="button" class="btn btn-sm btn-outline-secondary js-quick-token" data-token="<?php ob_start();?>{<?php $_prefixVariable5 = ob_get_clean();
echo $_prefixVariable5;?>
option:price_to_csv}">Cena z eksportu</button>
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between mb-4">
          <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=csvtemplates&action=titlegenerator" class="btn btn-outline-secondary">Wroc do generatora</a>
          <button type="submit" class="btn btn-primary">Zapisz szablon tytulu</button>
        </div>
      </form>
    </div>
  </div>
</main>

<?php echo '<script'; ?>
>
(function () {
  var textarea = document.getElementById('templateBody');
  var select = document.getElementById('tokenSelect');
  var insertButton = document.getElementById('insertTokenBtn');
  var quickButtons = document.querySelectorAll('.js-quick-token');

  function insertAtCursor(token) {
    if (!textarea || !token) {
      return;
    }

    var start = textarea.selectionStart || 0;
    var end = textarea.selectionEnd || 0;
    var current = textarea.value || '';
    textarea.value = current.slice(0, start) + token + current.slice(end);
    textarea.focus();
    var caret = start + token.length;
    textarea.setSelectionRange(caret, caret);
  }

  if (insertButton && select) {
    insertButton.addEventListener('click', function () {
      if (select.value) {
        insertAtCursor(select.value);
      }
    });
  }

  for (var i = 0; i < quickButtons.length; i++) {
    quickButtons[i].addEventListener('click', function () {
      insertAtCursor(this.getAttribute('data-token') || '');
    });
  }
})();
<?php echo '</script'; ?>
>
<?php }
}
