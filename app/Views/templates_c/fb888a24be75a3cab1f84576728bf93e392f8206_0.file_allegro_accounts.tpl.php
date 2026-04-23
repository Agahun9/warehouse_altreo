<?php
/* Smarty version 5.8.0, created on 2026-04-17 10:06:38
  from 'file:admin/allegro_accounts.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.8.0',
  'unifunc' => 'content_69e1ea0e02b588_60533800',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'fb888a24be75a3cab1f84576728bf93e392f8206' => 
    array (
      0 => 'admin/allegro_accounts.tpl',
      1 => 1776412430,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69e1ea0e02b588_60533800 (\Smarty\Template $_smarty_tpl) {
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
            <li class="breadcrumb-item"><a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=admin&action=users">Admin</a></li>
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
          <h3 class="card-title mb-0">Konfiguracja kont</h3>
          <div class="d-flex gap-2">
            <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=admin&action=automation" class="btn btn-sm btn-outline-primary">Administracja</a>
            <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=index" class="btn btn-sm btn-outline-secondary">Wroc do ofert</a>
          </div>
        </div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-lg-4">
              <form method="post" action="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=saveaccount" class="row g-3">
                <input type="hidden" name="account_id" value="">
                <div class="col-12">
                  <label class="form-label">Nazwa konta</label>
                  <input type="text" name="name" class="form-control" placeholder="np. altreo-market" required>
                </div>
                <div class="col-12">
                  <label class="form-label">Client ID</label>
                  <input type="text" name="client_id" class="form-control" required>
                </div>
                <div class="col-12">
                  <label class="form-label">Client Secret</label>
                  <input type="text" name="client_secret" class="form-control" required>
                </div>
                <div class="col-12">
                  <label class="form-label">Redirect URI</label>
                  <input type="url" name="redirect_uri" class="form-control" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('defaultRedirectUri'), ENT_QUOTES, 'UTF-8', true);?>
" required>
                </div>
                <div class="col-12">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="allegro_active_default" checked>
                    <label class="form-check-label" for="allegro_active_default">Konto aktywne</label>
                  </div>
                </div>
                <div class="col-12">
                  <button type="submit" class="btn btn-primary">Zapisz konto</button>
                </div>
              </form>
            </div>
            <div class="col-lg-8">
              <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle">
                  <thead class="table-light">
                    <tr>
                      <th>Konto</th>
                      <th>Status</th>
                      <th>Token</th>
                      <th>Sync</th>
                      <th>Trigger cron</th>
                      <th>Akcje</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('accounts'), 'account');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('account')->value) {
$foreach0DoElse = false;
?>
                      <tr>
                        <td>
                          <div class="fw-semibold"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('account')['name'], ENT_QUOTES, 'UTF-8', true);?>
</div>
                          <div class="small text-secondary">slug: <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('account')['slug'], ENT_QUOTES, 'UTF-8', true);?>
</div>
                        </td>
                        <td>
                          <?php if ($_smarty_tpl->getValue('account')['is_active']) {?>
                            <span class="badge text-bg-success">Aktywne</span>
                          <?php } else { ?>
                            <span class="badge text-bg-secondary">Nieaktywne</span>
                          <?php }?>
                          <?php if ($_smarty_tpl->getValue('account')['is_running']) {?>
                            <div class="small text-warning mt-1">Sync trwa, offset <?php echo (($tmp = $_smarty_tpl->getValue('account')['offer_offset'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp);?>
</div>
                          <?php }?>
                          <?php if ($_smarty_tpl->getValue('account')['sync_last_error_message']) {?>
                            <div class="small text-danger mt-1"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('account')['sync_last_error_message'], ENT_QUOTES, 'UTF-8', true);?>
</div>
                          <?php }?>
                        </td>
                        <td>
                          <?php if ($_smarty_tpl->getValue('account')['token_expires_at']) {?>
                            <div class="small">wazny do</div>
                            <div><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('account')['token_expires_at'], ENT_QUOTES, 'UTF-8', true);?>
</div>
                          <?php } else { ?>
                            <span class="text-secondary">brak autoryzacji</span>
                          <?php }?>
                        </td>
                        <td>
                          <div class="small">pelny: <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('account')['last_full_sync_at'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
                          <div class="small">ostatni OK: <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('account')['last_success_at'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
                        </td>
                        <td style="min-width: 280px;">
                          <div class="small text-secondary mb-1">sync</div>
                          <input type="text" class="form-control form-control-sm mb-2" readonly value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('account')['trigger_url'], ENT_QUOTES, 'UTF-8', true);?>
">
                          <div class="small text-secondary mb-1">maintenance + kolejka</div>
                          <input type="text" class="form-control form-control-sm" readonly value="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=maintenance&account=<?php echo rawurlencode((string)$_smarty_tpl->getValue('account')['slug']);?>
&sync=1&queue_limit=100">
                        </td>
                        <td class="text-nowrap">
                          <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=connect&id=<?php echo $_smarty_tpl->getValue('account')['id'];?>
" class="btn btn-sm btn-primary">Autoryzuj</a>
                          <a href="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('account')['trigger_url'], ENT_QUOTES, 'UTF-8', true);?>
" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noreferrer">Sync</a>
                          <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=refreshtoken&account=<?php echo rawurlencode((string)$_smarty_tpl->getValue('account')['slug']);?>
" class="btn btn-sm btn-outline-info">Refresh</a>
                        </td>
                      </tr>
                    <?php
}
if ($foreach0DoElse) {
?>
                      <tr><td colspan="6" class="text-center text-secondary py-4">Brak skonfigurowanych kont Allegro.</td></tr>
                    <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<?php }
}
