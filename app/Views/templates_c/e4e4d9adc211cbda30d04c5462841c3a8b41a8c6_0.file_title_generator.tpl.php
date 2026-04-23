<?php
/* Smarty version 5.8.0, created on 2026-04-17 10:26:32
  from 'file:csv_templates/title_generator.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.8.0',
  'unifunc' => 'content_69e1eeb86a0475_05037990',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'e4e4d9adc211cbda30d04c5462841c3a8b41a8c6' => 
    array (
      0 => 'csv_templates/title_generator.tpl',
      1 => 1774552119,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69e1eeb86a0475_05037990 (\Smarty\Template $_smarty_tpl) {
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
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('breadcrumbCurrent'), ENT_QUOTES, 'UTF-8', true);?>
</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      <div class="card mb-4">
        <div class="card-body">
          <h3 class="card-title mb-2">Jak tego uzyc</h3>
          <ol class="small mb-0 ps-3">
            <li>W szablonie CSV dodaj kolumne typu <code>field</code>.</li>
            <li>Jako zrodlo wybierz <code>product.generated_title</code>.</li>
            <li>Na liscie produktow, przy eksporcie CSV, wybierz szablon tytulu i wpisz <code>Kolekcje do tytulu</code>.</li>
            <li>Generator sam pobierze z rozszyfrowanych parametrow Allegro dedykowany model i dedykowana marke.</li>
          </ol>
          <div class="mt-3">
            <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=csvtemplates&action=createtitle" class="btn btn-primary">Dodaj szablon tytulu</a>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('titleTemplates'), 'titleTemplate', false, 'templateKey');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('templateKey')->value => $_smarty_tpl->getVariable('titleTemplate')->value) {
$foreach0DoElse = false;
?>
          <div class="col-lg-6">
            <div class="card h-100">
              <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('titleTemplate')['name'], ENT_QUOTES, 'UTF-8', true);?>
</h3>
                <span class="badge text-bg-secondary"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('templateKey'), ENT_QUOTES, 'UTF-8', true);?>
</span>
              </div>
              <div class="card-body">
                <p class="mb-2"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('titleTemplate')['description'], ENT_QUOTES, 'UTF-8', true);?>
</p>
                <div class="mb-3">
                  <div class="small fw-semibold mb-1">Wzor:</div>
                  <pre class="bg-light border rounded p-2 small mb-0"><code><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('titleTemplate')['template_body'] ?? null)===null||$tmp==='' ? $_smarty_tpl->getValue('titleTemplate')['pattern'] ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</code></pre>
                </div>
                <div class="mb-3">
                  <div class="small fw-semibold mb-1">Przyklad:</div>
                  <div class="border rounded p-2 bg-light"><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('titleTemplate')['example'] ?? null)===null||$tmp==='' ? 'Ustalany dynamicznie podczas eksportu' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
                </div>
                <div class="d-flex gap-2">
                  <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=csvtemplates&action=edittitle&id=<?php echo $_smarty_tpl->getValue('titleTemplate')['id'];?>
" class="btn btn-sm btn-outline-primary">Edytuj</a>
                  <form method="post" action="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=csvtemplates&action=deletetitle" class="d-inline" onsubmit="return confirm('Usunac szablon tytulu <?php echo strtr((string)$_smarty_tpl->getValue('titleTemplate')['name'], array("\\" => "\\\\", "'" => "\\'", "\"" => "\\\"", "\r" => "\\r", 
						"\n" => "\\n", "</" => "<\/", "<!--" => "<\!--", "<s" => "<\s", "<S" => "<\S",
						"`" => "\\`", "\${" => "\\\$\{"));?>
?');">
                    <input type="hidden" name="id" value="<?php echo $_smarty_tpl->getValue('titleTemplate')['id'];?>
">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Usun</button>
                  </form>
                </div>
              </div>
            </div>
          </div>
        <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
      </div>

      <div class="card mt-4">
        <div class="card-header"><h3 class="card-title mb-0">Dostepne tokeny</h3></div>
        <div class="card-body">
          <div class="row g-2">
            <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('availableTitleTokens'), 'label', false, 'token');
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('token')->value => $_smarty_tpl->getVariable('label')->value) {
$foreach1DoElse = false;
?>
              <div class="col-lg-6">
                <div class="border rounded p-2 small">
                  <code><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('token'), ENT_QUOTES, 'UTF-8', true);?>
</code><br>
                  <span class="text-secondary"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('label'), ENT_QUOTES, 'UTF-8', true);?>
</span>
                </div>
              </div>
            <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-between mt-4 mb-4">
        <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=csvtemplates&action=index" class="btn btn-outline-secondary">Wroc do listy</a>
        <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=products&action=index" class="btn btn-primary">Przejdz do eksportu produktow</a>
      </div>
    </div>
  </div>
</main>
<?php }
}
