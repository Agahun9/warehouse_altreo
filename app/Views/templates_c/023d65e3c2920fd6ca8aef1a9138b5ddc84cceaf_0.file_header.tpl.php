<?php
/* Smarty version 5.8.0, created on 2026-04-23 22:18:33
  from 'file:layout/header.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.8.0',
  'unifunc' => 'content_69ea7e992f2bd7_51931907',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '023d65e3c2920fd6ca8aef1a9138b5ddc84cceaf' => 
    array (
      0 => 'layout/header.tpl',
      1 => 1776975199,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69ea7e992f2bd7_51931907 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/pfuuseajvz/domains/magazyn.altreo.pl/public_html/crm/new_version/app/Views/templates/layout';
?><!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('appName'), ENT_QUOTES, 'UTF-8', true);?>
 | <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('pageTitle') ?? null)===null||$tmp==='' ? 'Dashboard' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?php echo $_smarty_tpl->getValue('assetBase');?>
/css/adminlte.css">
  <style>
    .table thead th {
      vertical-align: middle;
      white-space: nowrap;
    }

    .table td {
      vertical-align: middle;
    }

    .quick-search-wrap {
      position: relative;
      width: min(460px, 100%);
    }

    .quick-search-results {
      position: absolute;
      top: calc(100% + 4px);
      left: 0;
      right: 0;
      background: #fff;
      border: 1px solid rgba(0, 0, 0, 0.12);
      border-radius: 0.5rem;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
      z-index: 1050;
      max-height: 320px;
      overflow-y: auto;
      display: none;
    }

    .quick-search-item {
      display: block;
      padding: 0.5rem 0.75rem;
      text-decoration: none;
      color: inherit;
      border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    }

    .quick-search-item:last-child {
      border-bottom: 0;
    }

    .quick-search-item:hover {
      background: rgba(13, 110, 253, 0.08);
    }

    .topbar-user-link {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      max-width: min(280px, 32vw);
      white-space: nowrap;
    }

    .topbar-user-name {
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .topbar-user-role {
      flex-shrink: 0;
    }

    @media (max-width: 991.98px) {
      .topbar-user-link {
        max-width: 150px;
      }

      .topbar-user-role {
        display: none;
      }
    }

    @media (max-width: 767.98px) {
      .topbar-user-link {
        max-width: 110px;
      }
    }
  </style>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
  <div class="app-wrapper">
    <nav class="app-header navbar navbar-expand bg-body">
      <div class="container-fluid">
        <ul class="navbar-nav">
          <li class="nav-item">
            <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="bi bi-list"></i></a>
          </li>
          <?php if ($_smarty_tpl->getValue('currentUser')) {?>
            <li class="nav-item d-none d-md-block"><a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=index" class="nav-link">Start</a></li>
            <?php if ($_smarty_tpl->getValue('currentUser')['role'] == 'admin' || $_smarty_tpl->getSmarty()->getModifierCallback('in_array')('products',$_smarty_tpl->getValue('currentUser')['modules'])) {?>
              <li class="nav-item d-none d-md-block"><a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=products&action=index" class="nav-link">Produkty</a></li>
            <?php }?>
            <?php if ($_smarty_tpl->getValue('currentUser')['role'] == 'admin' || $_smarty_tpl->getSmarty()->getModifierCallback('in_array')('allegro',$_smarty_tpl->getValue('currentUser')['modules'])) {?>
              <li class="nav-item d-none d-md-block"><a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=index" class="nav-link">Allegro</a></li>
            <?php }?>
            <?php if ($_smarty_tpl->getValue('currentUser')['role'] == 'admin' || $_smarty_tpl->getSmarty()->getModifierCallback('in_array')('empik',$_smarty_tpl->getValue('currentUser')['modules'])) {?>
              <li class="nav-item d-none d-md-block"><a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=empik&action=index" class="nav-link">Empik</a></li>
            <?php }?>
            <?php if ($_smarty_tpl->getValue('currentUser')['role'] == 'admin' || $_smarty_tpl->getSmarty()->getModifierCallback('in_array')('sellasist',$_smarty_tpl->getValue('currentUser')['modules'])) {?>
              <li class="nav-item d-none d-md-block"><a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=sellasist&action=zbieranie" class="nav-link">Sellasist</a></li>
            <?php }?>
            <?php if ($_smarty_tpl->getValue('currentUser')['role'] == 'admin') {?>
              <li class="nav-item d-none d-md-block"><a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=categories&action=index" class="nav-link">Kategorie</a></li>
              <li class="nav-item d-none d-md-block"><a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=csvtemplates&action=index" class="nav-link">Szablony CSV</a></li>
              <li class="nav-item d-none d-md-block"><a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=admin&action=users" class="nav-link">Admin</a></li>
              <li class="nav-item d-none d-md-block"><a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=admin&action=automation" class="nav-link">Administracja</a></li>
            <?php }?>
          <?php }?>
        </ul>

        <?php if ($_smarty_tpl->getValue('currentUser') && ($_smarty_tpl->getValue('currentUser')['role'] == 'admin' || $_smarty_tpl->getSmarty()->getModifierCallback('in_array')('products',$_smarty_tpl->getValue('currentUser')['modules']))) {?>
          <div class="quick-search-wrap mx-2 flex-grow-1">
            <form method="get" action="<?php echo $_smarty_tpl->getValue('baseUrl');?>
" id="globalProductSearchForm" autocomplete="off">
              <input type="hidden" name="controller" value="products">
              <input type="hidden" name="action" value="index">
              <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input
                  type="search"
                  name="filter_global"
                  id="globalProductSearchInput"
                  class="form-control"
                  placeholder="Szybkie wyszukiwanie SKU lub nazwy..."
                  minlength="2"
                >
              </div>
            </form>
            <div id="globalProductSearchResults" class="quick-search-results" aria-live="polite"></div>
          </div>
        <?php }?>

        <ul class="navbar-nav ms-auto">
          <?php if ($_smarty_tpl->getValue('currentUser')) {?>
            <li class="nav-item">
              <span class="nav-link text-secondary topbar-user-link">
                <span class="topbar-user-name">
                  <?php if ((($tmp = $_smarty_tpl->getValue('currentUser')['first_name'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp) != '' || (($tmp = $_smarty_tpl->getValue('currentUser')['last_name'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp) != '') {?>
                    <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('currentUser')['first_name'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
 <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('currentUser')['last_name'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>

                  <?php } else { ?>
                    <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('currentUser')['email'], ENT_QUOTES, 'UTF-8', true);?>

                  <?php }?>
                </span>
                <span class="badge topbar-user-role text-bg-<?php if ($_smarty_tpl->getValue('currentUser')['role'] == 'admin') {?>dark<?php } else { ?>secondary<?php }?>"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('currentUser')['role'], ENT_QUOTES, 'UTF-8', true);?>
</span>
              </span>
            </li>
            <li class="nav-item"><a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=auth&action=logout" class="nav-link">Wyloguj</a></li>
          <?php } else { ?>
            <li class="nav-item"><a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=auth&action=login" class="nav-link">Logowanie</a></li>
            <li class="nav-item"><a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=auth&action=register" class="nav-link">Rejestracja</a></li>
          <?php }?>
        </ul>
      </div>
    </nav>

    <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
      <div class="sidebar-brand">
        <a href="<?php if ($_smarty_tpl->getValue('currentUser')) {
echo $_smarty_tpl->getValue('baseUrl');?>
?controller=index<?php } else {
echo $_smarty_tpl->getValue('baseUrl');?>
?controller=auth&action=login<?php }?>" class="brand-link">
          <img src="<?php echo $_smarty_tpl->getValue('assetBase');?>
/assets/img/AdminLTELogo.png" alt="Logo" class="brand-image opacity-75 shadow">
          <span class="brand-text fw-light"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('appName'), ENT_QUOTES, 'UTF-8', true);?>
</span>
        </a>
      </div>

      <div class="sidebar-wrapper">
        <nav class="mt-2">
          <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation" data-accordion="false">
            <?php if ($_smarty_tpl->getValue('currentUser')) {?>
              <li class="nav-item">
                <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=index" class="nav-link<?php if ($_smarty_tpl->getValue('currentController') == 'index') {?> active<?php }?>">
                  <i class="nav-icon bi bi-speedometer2"></i>
                  <p>Dashboard</p>
                </a>
              </li>
              <?php if ($_smarty_tpl->getValue('currentUser')['role'] == 'admin' || $_smarty_tpl->getSmarty()->getModifierCallback('in_array')('products',$_smarty_tpl->getValue('currentUser')['modules'])) {?>
                <li class="nav-item">
                  <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=products&action=index" class="nav-link<?php if ($_smarty_tpl->getValue('currentController') == 'products') {?> active<?php }?>">
                    <i class="nav-icon bi bi-box-seam"></i>
                    <p>Lista produktow</p>
                  </a>
                </li>
              <?php }?>
              <?php if ($_smarty_tpl->getValue('currentUser')['role'] == 'admin' || $_smarty_tpl->getSmarty()->getModifierCallback('in_array')('allegro',$_smarty_tpl->getValue('currentUser')['modules'])) {?>
                <li class="nav-item">
                  <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=index" class="nav-link<?php if ($_smarty_tpl->getValue('currentController') == 'allegro') {?> active<?php }?>">
                    <i class="nav-icon bi bi-shop"></i>
                    <p>Allegro</p>
                  </a>
                </li>
              <?php }?>
              <?php if ($_smarty_tpl->getValue('currentUser')['role'] == 'admin' || $_smarty_tpl->getSmarty()->getModifierCallback('in_array')('empik',$_smarty_tpl->getValue('currentUser')['modules'])) {?>
                <li class="nav-item">
                  <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=empik&action=index" class="nav-link<?php if ($_smarty_tpl->getValue('currentController') == 'empik') {?> active<?php }?>">
                    <i class="nav-icon bi bi-bag"></i>
                    <p>Empik</p>
                  </a>
                </li>
              <?php }?>
              <?php if ($_smarty_tpl->getValue('currentUser')['role'] == 'admin' || $_smarty_tpl->getSmarty()->getModifierCallback('in_array')('sellasist',$_smarty_tpl->getValue('currentUser')['modules'])) {?>
                <li class="nav-item">
                  <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=sellasist&action=zbieranie" class="nav-link<?php if ($_smarty_tpl->getValue('currentController') == 'sellasist') {?> active<?php }?>">
                    <i class="nav-icon bi bi-bag-check"></i>
                    <p>Sellasist</p>
                  </a>
                </li>
              <?php }?>
              <?php if ($_smarty_tpl->getValue('currentUser')['role'] == 'admin') {?>
                <li class="nav-item">
                  <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=categories&action=index" class="nav-link<?php if ($_smarty_tpl->getValue('currentController') == 'categories') {?> active<?php }?>">
                    <i class="nav-icon bi bi-tags"></i>
                    <p>Lista kategorii</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=csvtemplates&action=index" class="nav-link<?php if ($_smarty_tpl->getValue('currentController') == 'csvtemplates') {?> active<?php }?>">
                    <i class="nav-icon bi bi-file-earmark-spreadsheet"></i>
                    <p>Szablony CSV</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=admin&action=users" class="nav-link<?php if ($_smarty_tpl->getValue('currentController') == 'admin' && $_smarty_tpl->getValue('currentAction') != 'automation') {?> active<?php }?>">
                    <i class="nav-icon bi bi-people"></i>
                    <p>Uzytkownicy</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=admin&action=automation" class="nav-link<?php if ($_smarty_tpl->getValue('currentController') == 'admin' && $_smarty_tpl->getValue('currentAction') == 'automation') {?> active<?php }?>">
                    <i class="nav-icon bi bi-clock-history"></i>
                    <p>Administracja</p>
                  </a>
                </li>
              <?php }?>
            <?php } else { ?>
              <li class="nav-item">
                <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=auth&action=login" class="nav-link<?php if ($_smarty_tpl->getValue('currentController') == 'auth' && $_smarty_tpl->getValue('currentAction') == 'login') {?> active<?php }?>">
                  <i class="nav-icon bi bi-box-arrow-in-right"></i>
                  <p>Logowanie</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=auth&action=register" class="nav-link<?php if ($_smarty_tpl->getValue('currentController') == 'auth' && $_smarty_tpl->getValue('currentAction') == 'register') {?> active<?php }?>">
                  <i class="nav-icon bi bi-person-plus"></i>
                  <p>Rejestracja</p>
                </a>
              </li>
            <?php }?>
          </ul>
        </nav>
      </div>
    </aside>
<?php }
}
