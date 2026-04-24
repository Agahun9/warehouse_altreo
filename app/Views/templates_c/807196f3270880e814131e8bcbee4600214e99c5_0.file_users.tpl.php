<?php
/* Smarty version 5.8.0, created on 2026-04-22 15:29:56
  from 'file:admin/users.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.8.0',
  'unifunc' => 'content_69e8cd54e0f4b1_25896096',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '807196f3270880e814131e8bcbee4600214e99c5' => 
    array (
      0 => 'admin/users.tpl',
      1 => 1776864591,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69e8cd54e0f4b1_25896096 (\Smarty\Template $_smarty_tpl) {
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

      <style>
        .users-admin-card {
          border-radius: 1.15rem;
          border: 1px solid rgba(15, 23, 42, 0.08);
          box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
        }

        .users-admin-summary {
          overflow: hidden;
          border-radius: 1.1rem;
          background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 52%, #60a5fa 100%);
          color: #fff;
          box-shadow: 0 18px 40px rgba(29, 78, 216, 0.18);
        }

        .users-admin-summary .card-body {
          padding: 1.4rem 1.55rem;
        }

        .users-admin-summary-title {
          margin: 0 0 0.35rem;
          font-size: 1.35rem;
          font-weight: 700;
          line-height: 1.1;
        }

        .users-admin-summary-text {
          max-width: 46rem;
          margin: 0;
          color: rgba(255, 255, 255, 0.82);
          line-height: 1.5;
        }

        .users-admin-summary .badge {
          background: rgba(255, 255, 255, 0.16) !important;
          color: #fff !important;
        }

        .users-admin-summary-actions {
          display: flex;
          flex-wrap: wrap;
          align-items: center;
          justify-content: flex-end;
          gap: 0.6rem;
        }

        .users-admin-summary .btn-light {
          border: 0;
          font-weight: 600;
        }

        .users-admin-chip {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          min-height: 3rem;
          width: 100%;
          padding: 0.75rem 0.9rem;
          border: 1px solid rgba(15, 23, 42, 0.08);
          border-radius: 0.95rem;
          background: #fff;
          font-weight: 600;
        }

        .users-admin-modules {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
          gap: 0.75rem;
        }

        .users-admin-module {
          display: flex;
          align-items: center;
          gap: 0.65rem;
          min-height: 4rem;
          padding: 0.95rem 1rem;
          border: 1px solid rgba(15, 23, 42, 0.08);
          border-radius: 1rem;
          background: linear-gradient(180deg, #fff, #f8fafc);
        }

        .users-admin-module input {
          width: 1.1rem;
          height: 1.1rem;
          flex: 0 0 auto;
        }

        .users-admin-module span {
          font-weight: 600;
          line-height: 1.2;
        }

        .users-admin-meta {
          display: flex;
          flex-wrap: wrap;
          gap: 0.5rem;
          justify-content: flex-end;
        }

        .users-admin-head {
          display: flex;
          flex-wrap: wrap;
          align-items: flex-start;
          justify-content: space-between;
          gap: 1rem;
        }

        .users-admin-identity {
          min-width: 0;
          flex: 1 1 18rem;
        }

        .users-admin-name {
          margin: 0 0 0.25rem;
          font-size: 1.2rem;
          font-weight: 700;
          line-height: 1.2;
          color: #0f172a;
        }

        .users-admin-email {
          color: #64748b;
          word-break: break-word;
        }

        .users-admin-meta .badge {
          padding: 0.45rem 0.7rem;
          font-size: 0.78rem;
          border-radius: 999px;
        }

        @media (max-width: 991.98px) {
          .users-admin-summary-actions {
            justify-content: flex-start;
          }

          .users-admin-meta {
            justify-content: flex-start;
          }
        }
      </style>

      <div class="card users-admin-summary mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
          <div>
            <h3 class="users-admin-summary-title">Lista kont</h3>
            <p class="users-admin-summary-text">Edytujesz dane osobowe, role, status konta, dostep do modulow oraz tryb odczyt lub edycja.</p>
          </div>
          <div class="users-admin-summary-actions">
            <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=admin&action=automation" class="btn btn-light btn-sm">Administracja</a>
            <span class="badge"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('users'));?>
 kont</span>
          </div>
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
            <div class="card users-admin-card mb-4">
              <div class="card-header bg-white border-0 pt-4 px-4">
                <div class="users-admin-head">
                  <div class="users-admin-identity">
                    <h3 class="users-admin-name"><?php if ((($tmp = $_smarty_tpl->getValue('user')['first_name'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp) != '' || (($tmp = $_smarty_tpl->getValue('user')['last_name'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp) != '') {
echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('user')['first_name'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
 <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('user')['last_name'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);
} else {
echo htmlspecialchars((string)$_smarty_tpl->getValue('user')['email'], ENT_QUOTES, 'UTF-8', true);
}?></h3>
                    <div class="users-admin-email"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('user')['email'], ENT_QUOTES, 'UTF-8', true);?>
</div>
                  </div>
                  <div class="users-admin-meta">
                    <span class="badge text-bg-<?php if ($_smarty_tpl->getValue('user')['role'] == 'admin') {?>dark<?php } else { ?>secondary<?php }?>"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('user')['role'], ENT_QUOTES, 'UTF-8', true);?>
</span>
                    <span class="badge text-bg-<?php if ($_smarty_tpl->getValue('user')['permission_level'] == 'read') {?>warning<?php } else { ?>primary<?php }?>"><?php if ($_smarty_tpl->getValue('user')['permission_level'] == 'read') {?>odczyt<?php } else { ?>edycja<?php }?></span>
                    <span class="badge text-bg-secondary">ID <?php echo $_smarty_tpl->getValue('user')['id'];?>
</span>
                  </div>
                </div>
              </div>

              <form method="post" action="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=admin&action=updateUser">
                <input type="hidden" name="id" value="<?php echo $_smarty_tpl->getValue('user')['id'];?>
">

                <div class="card-body px-4 pt-2">
                  <div class="row g-3 mb-4">
                    <div class="col-md-6">
                      <label class="form-label">Imie</label>
                      <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('user')['first_name'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
">
                    </div>

                    <div class="col-md-6">
                      <label class="form-label">Nazwisko</label>
                      <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('user')['last_name'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
">
                    </div>

                    <div class="col-md-4">
                      <label class="form-label">Rola</label>
                      <select name="role" class="form-select">
                        <option value="user"<?php if ($_smarty_tpl->getValue('user')['role'] == 'user') {?> selected<?php }?>>user</option>
                        <option value="admin"<?php if ($_smarty_tpl->getValue('user')['role'] == 'admin') {?> selected<?php }?>>admin</option>
                      </select>
                    </div>

                    <div class="col-md-4">
                      <label class="form-label">Zakres dostepu</label>
                      <select name="permission_level" class="form-select">
                        <option value="edit"<?php if ((($tmp = $_smarty_tpl->getValue('user')['permission_level'] ?? null)===null||$tmp==='' ? 'edit' ?? null : $tmp) == 'edit') {?> selected<?php }?>>moze edytowac</option>
                        <option value="read"<?php if ((($tmp = $_smarty_tpl->getValue('user')['permission_level'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp) == 'read') {?> selected<?php }?>>tylko odczyt</option>
                      </select>
                    </div>

                    <div class="col-md-4">
                      <label class="form-label">Status konta</label>
                      <select name="is_active" class="form-select">
                        <option value="1"<?php if ($_smarty_tpl->getValue('user')['is_active']) {?> selected<?php }?>>aktywne</option>
                        <option value="0"<?php if (!$_smarty_tpl->getValue('user')['is_active']) {?> selected<?php }?>>nieaktywne</option>
                      </select>
                    </div>
                  </div>

                  <div class="row g-3 mb-4">
                    <div class="col-md-6">
                      <label class="form-label">Blokada logowania</label>
                      <select name="is_blocked" class="form-select">
                        <option value="0"<?php if (!$_smarty_tpl->getValue('user')['is_blocked']) {?> selected<?php }?>>odblokowany</option>
                        <option value="1"<?php if ($_smarty_tpl->getValue('user')['is_blocked']) {?> selected<?php }?>>zablokowany</option>
                      </select>
                    </div>

                    <div class="col-md-6">
                      <label class="form-label">Nowe haslo</label>
                      <input type="password" class="form-control" name="new_password" placeholder="pozostaw puste, aby nie zmieniac">
                    </div>
                  </div>

                  <div class="mb-3">
                    <label class="form-label fs-5 fw-semibold mb-3">Dostep do modulow</label>
                    <div class="users-admin-modules">
                      <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('modules'), 'module');
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('module')->value) {
$foreach1DoElse = false;
?>
                        <label class="users-admin-module" for="mod_<?php echo $_smarty_tpl->getValue('user')['id'];?>
_<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('module')['code'], ENT_QUOTES, 'UTF-8', true);?>
">
                          <input class="form-check-input mt-0" type="checkbox" name="modules[]" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('module')['code'], ENT_QUOTES, 'UTF-8', true);?>
" id="mod_<?php echo $_smarty_tpl->getValue('user')['id'];?>
_<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('module')['code'], ENT_QUOTES, 'UTF-8', true);?>
"<?php if ($_smarty_tpl->getSmarty()->getModifierCallback('in_array')($_smarty_tpl->getValue('module')['code'],$_smarty_tpl->getValue('user')['modules'])) {?> checked<?php }?>>
                          <span><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('module')['name'], ENT_QUOTES, 'UTF-8', true);?>
</span>
                        </label>
                      <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                    </div>
                  </div>
                </div>

                <div class="card-footer bg-white border-0 d-flex justify-content-between align-items-center px-4 pb-4">
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
                  <button type="submit" class="btn btn-primary px-4">Zapisz zmiany</button>
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
