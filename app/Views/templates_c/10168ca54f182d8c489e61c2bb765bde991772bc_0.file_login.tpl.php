<?php
/* Smarty version 5.8.0, created on 2026-04-17 10:19:06
  from 'file:auth/login.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.8.0',
  'unifunc' => 'content_69e1ecfa8a52a4_88124283',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '10168ca54f182d8c489e61c2bb765bde991772bc' => 
    array (
      0 => 'auth/login.tpl',
      1 => 1774521794,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69e1ecfa8a52a4_88124283 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/pfuuseajvz/domains/magazyn.altreo.pl/public_html/crm/new_version/app/Views/templates/auth';
?><main class="app-main">
  <div class="app-content-header"><div class="container-fluid"><div class="row"><div class="col-sm-6"><h3 class="mb-0"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('contentTitle'), ENT_QUOTES, 'UTF-8', true);?>
</h3><p class="text-secondary mb-0"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('pageDescription'), ENT_QUOTES, 'UTF-8', true);?>
</p></div><div class="col-sm-6"><ol class="breadcrumb float-sm-end"><li class="breadcrumb-item"><a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=index">Start</a></li><li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('breadcrumbCurrent'), ENT_QUOTES, 'UTF-8', true);?>
</li></ol></div></div></div></div>
  <div class="app-content"><div class="container-fluid">
    <?php if ($_smarty_tpl->getValue('flashSuccess')) {?><div class="alert alert-success"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('flashSuccess'), ENT_QUOTES, 'UTF-8', true);?>
</div><?php }?>
    <?php if ($_smarty_tpl->getValue('flashError')) {?><div class="alert alert-danger"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('flashError'), ENT_QUOTES, 'UTF-8', true);?>
</div><?php }?>
    <div class="row justify-content-center"><div class="col-lg-6"><div class="card card-primary card-outline"><div class="card-header"><h3 class="card-title">Logowanie</h3></div><form method="post" action="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=auth&action=authenticate"><div class="card-body"><div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control" type="email" id="email" name="email" required></div><div class="mb-3"><label class="form-label" for="password">Haslo</label><input class="form-control" type="password" id="password" name="password" required></div></div><div class="card-footer d-flex justify-content-between"><div><a class="btn btn-outline-secondary" href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=auth&action=register">Rejestracja</a> <a class="btn btn-link" href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=auth&action=forgotPassword">Zapomnialem hasla</a></div><button class="btn btn-primary" type="submit">Zaloguj</button></div></form></div></div></div>
  </div></div>
</main>
<?php }
}
