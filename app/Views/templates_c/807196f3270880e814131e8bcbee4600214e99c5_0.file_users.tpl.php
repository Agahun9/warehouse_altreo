<?php
/* Smarty version 5.8.0, created on 2026-04-17 10:27:41
  from 'file:admin/users.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.8.0',
  'unifunc' => 'content_69e1eefd97af16_15946400',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '807196f3270880e814131e8bcbee4600214e99c5' => 
    array (
      0 => 'admin/users.tpl',
      1 => 1776413683,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69e1eefd97af16_15946400 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/pfuuseajvz/domains/magazyn.altreo.pl/public_html/crm/new_version/app/Views/templates/admin';
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
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0">Lista kont</h3>
          <div class="d-flex gap-2 align-items-center">
            <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=admin&action=automation" class="btn btn-sm btn-outline-primary">Administracja</a>
            <span class="badge text-bg-primary"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('users'));?>
 kont</span>
          </div>
        </div>
        <div class="card-body">
          <p class="text-secondary mb-0">Mozesz edytowac role, blokade i moduly uzytkownika. Usuwanie jest dostepne dla kont innych niz aktualnie zalogowany admin.</p>
        </div>
      </div>

      <div class="row">
        <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('users'), 'user');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('user')->value) {
$foreach0DoElse = false;
?>
          <div class="col-xl-6">
            <div class="card mb-4">
              <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('user')['email'], ENT_QUOTES, 'UTF-8', true);?>
</h3>
                <div class="d-flex gap-2 align-items-center">
                  <span class="badge text-bg-<?php if ($_smarty_tpl->getValue('user')['role'] == 'admin') {?>dark<?php } else { ?>secondary<?php }?>"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('user')['role'], ENT_QUOTES, 'UTF-8', true);?>
</span>
                  <span class="badge text-bg-secondary">ID <?php echo $_smarty_tpl->getValue('user')['id'];?>
</span>
                </div>
              </div>

              <form method="post" action="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=admin&action=updateUser">
                <input type="hidden" name="id" value="<?php echo $_smarty_tpl->getValue('user')['id'];?>
">

                <div class="card-body">
                  <div class="row g-3">
                    <div class="col-md-4">
                      <label class="form-label">Rola</label>
                      <select name="role" class="form-select">
                        <option value="user"<?php if ($_smarty_tpl->getValue('user')['role'] == 'user') {?> selected<?php }?>>user</option>
                        <option value="admin"<?php if ($_smarty_tpl->getValue('user')['role'] == 'admin') {?> selected<?php }?>>admin</option>
                      </select>
                    </div>

                    <div class="col-md-4">
                      <label class="form-label">Status</label>
                      <input class="form-control" value="<?php if ($_smarty_tpl->getValue('user')['is_active']) {?>aktywne<?php } else { ?>nieaktywne<?php }?>" disabled>
                    </div>

                    <div class="col-md-4">
                      <label class="form-label">Blokada</label>
                      <select name="is_blocked" class="form-select">
                        <option value="0"<?php if (!$_smarty_tpl->getValue('user')['is_blocked']) {?> selected<?php }?>>odblokowany</option>
                        <option value="1"<?php if ($_smarty_tpl->getValue('user')['is_blocked']) {?> selected<?php }?>>zablokowany</option>
                      </select>
                    </div>

                    <div class="col-12">
                      <label class="form-label">Dostep do modulow</label>
                      <div class="table-responsive border rounded p-2">
                        <table class="table table-sm table-borderless mb-0">
                          <tbody>
                            <tr>
                              <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('modules'), 'module');
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('module')->value) {
$foreach1DoElse = false;
?>
                                <td>
                                  <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="modules[]" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('module')['code'], ENT_QUOTES, 'UTF-8', true);?>
" id="mod_<?php echo $_smarty_tpl->getValue('user')['id'];?>
_<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('module')['code'], ENT_QUOTES, 'UTF-8', true);?>
"<?php if ($_smarty_tpl->getSmarty()->getModifierCallback('in_array')($_smarty_tpl->getValue('module')['code'],$_smarty_tpl->getValue('user')['modules'])) {?> checked<?php }?>>
                                    <label class="form-check-label" for="mod_<?php echo $_smarty_tpl->getValue('user')['id'];?>
_<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('module')['code'], ENT_QUOTES, 'UTF-8', true);?>
"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('module')['name'], ENT_QUOTES, 'UTF-8', true);?>
</label>
                                  </div>
                                </td>
                              <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>

                    <div class="col-12">
                      <label class="form-label">Nowe haslo</label>
                      <input type="password" class="form-control" name="new_password" placeholder="pozostaw puste, aby nie zmieniac">
                    </div>
                  </div>
                </div>

                <div class="card-footer d-flex justify-content-between align-items-center">
                  <div>
                    <?php if ($_smarty_tpl->getValue('user')['id'] != $_smarty_tpl->getValue('currentAdminId')) {?>
                      <button
                        type="submit"
                        class="btn btn-outline-danger"
                        formaction="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=admin&action=deleteUser"
                        formmethod="post"
                        onclick="return confirm('Usunac uzytkownika <?php echo strtr((string)$_smarty_tpl->getValue('user')['email'], array("\\" => "\\\\", "'" => "\\'", "\"" => "\\\"", "\r" => "\\r", 
						"\n" => "\\n", "</" => "<\/", "<!--" => "<\!--", "<s" => "<\s", "<S" => "<\S",
						"`" => "\\`", "\${" => "\\\$\{"));?>
?');"
                      >
                        Usun uzytkownika
                      </button>
                    <?php } else { ?>
                      <button type="button" class="btn btn-outline-danger" disabled>To Twoje konto</button>
                    <?php }?>
                  </div>
                  <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
                </div>
              </form>
            </div>
          </div>
        <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
      </div>
    </div>
  </div>
</main>

<?php }
}
