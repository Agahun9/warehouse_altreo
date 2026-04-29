<?php
/* Smarty version 5.8.0, created on 2026-04-28 10:19:14
  from 'file:csv_templates/title_generator.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.8.0',
  'unifunc' => 'content_69f06d8266be43_54200901',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'e4e4d9adc211cbda30d04c5462841c3a8b41a8c6' => 
    array (
      0 => 'csv_templates/title_generator.tpl',
      1 => 1777355171,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69f06d8266be43_54200901 (\Smarty\Template $_smarty_tpl) {
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
      <style>
        .csv-title-generator-hero {
          display: grid;
          grid-template-columns: minmax(0, 1fr) auto;
          gap: 1rem 1.25rem;
          align-items: start;
        }

        .csv-title-generator-hero-copy {
          min-width: 0;
        }

        .csv-title-generator-hero-copy .card-title,
        .csv-title-generator-help .card-title {
          line-height: 1.2;
        }

        .csv-title-generator-hero-copy .text-secondary,
        .csv-title-generator-help ol {
          overflow-wrap: anywhere;
          word-break: break-word;
        }

        .csv-title-generator-actions {
          display: flex;
          flex-wrap: wrap;
          justify-content: flex-end;
          gap: 0.75rem;
          min-width: min(100%, 22rem);
        }

        .csv-title-generator-actions .btn {
          white-space: nowrap;
        }

        .csv-title-generator-help ol li + li {
          margin-top: 0.35rem;
        }

        @media (max-width: 767.98px) {
          .csv-title-generator-hero {
            grid-template-columns: minmax(0, 1fr);
          }

          .csv-title-generator-actions {
            min-width: 0;
            justify-content: stretch;
          }

          .csv-title-generator-actions .btn {
            flex: 1 1 100%;
            white-space: normal;
          }
        }
      </style>

      <?php if ($_smarty_tpl->getValue('flashSuccess')) {?><div class="alert alert-success"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('flashSuccess'), ENT_QUOTES, 'UTF-8', true);?>
</div><?php }?>
      <?php if ($_smarty_tpl->getValue('flashError')) {?><div class="alert alert-danger"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('flashError'), ENT_QUOTES, 'UTF-8', true);?>
</div><?php }?>

      <div class="card mb-4">
        <div class="card-body csv-title-generator-hero">
          <div class="csv-title-generator-hero-copy">
            <h3 class="card-title mb-1">Szablony tytulow</h3>
            <div class="text-secondary small">Szablony do pola <code>product.generated_title</code> uzywanego podczas eksportu CSV.</div>
          </div>
          <div class="csv-title-generator-actions">
            <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=csvtemplates&action=index" class="btn btn-outline-secondary">Szablony CSV</a>
            <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=csvtemplates&action=createtitle" class="btn btn-primary">Dodaj szablon tytulu</a>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-body csv-title-generator-help">
          <h3 class="card-title mb-2">Jak tego uzyc</h3>
          <ol class="small mb-0 ps-3">
            <li>W szablonie CSV dodaj kolumne typu <code>field</code>.</li>
            <li>Jako zrodlo wybierz <code>product.generated_title</code>.</li>
            <li>Na liscie produktow, przy eksporcie CSV, wybierz szablon tytulu i wpisz <code>Kolekcje do tytulu</code>.</li>
            <li>Generator sam pobierze z rozszyfrowanych parametrow Allegro dedykowany model i dedykowana marke.</li>
          </ol>
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
                  <th>Wzor</th>
                  <th>Utworzono</th>
                  <th>Zmieniono</th>
                  <th class="text-end">Akcje</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($_smarty_tpl->getValue('titleTemplates')) {?>
                  <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('titleTemplates'), 'titleTemplate');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('titleTemplate')->value) {
$foreach0DoElse = false;
?>
                    <tr>
                      <td class="fw-semibold"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('titleTemplate')['name'], ENT_QUOTES, 'UTF-8', true);?>
</td>
                      <td><?php echo htmlspecialchars((string)$_smarty_tpl->getSmarty()->getModifierCallback('truncate')((($tmp = $_smarty_tpl->getValue('titleTemplate')['description'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp),120), ENT_QUOTES, 'UTF-8', true);?>
</td>
                      <td>
                        <pre class="bg-light border rounded p-2 small mb-0"><code><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('titleTemplate')['template_body'] ?? null)===null||$tmp==='' ? $_smarty_tpl->getValue('titleTemplate')['pattern'] ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</code></pre>
                      </td>
                      <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('titleTemplate')['created_at'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
                      <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('titleTemplate')['updated_at'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
                      <td class="text-end">
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
                      </td>
                    </tr>
                  <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                <?php } else { ?>
                  <tr><td colspan="6" class="text-center py-4">Brak szablonow tytulow.</td></tr>
                <?php }?>
              </tbody>
            </table>
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
