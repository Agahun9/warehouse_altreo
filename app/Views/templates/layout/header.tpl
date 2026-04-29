<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>{$appName|escape} | {$pageTitle|default:'Dashboard'|escape}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="{$assetBase}/css/adminlte.css">
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

    .sidebar-menu .taskboard-submenu .nav-link {
      margin-left: 1rem;
      padding-left: 1.15rem;
      font-size: 0.88rem;
      border-left: 1px solid rgba(255, 255, 255, 0.18);
    }

    .sidebar-menu .taskboard-submenu .nav-link p {
      font-size: 0.88rem;
    }

    .sidebar-menu .taskboard-submenu .nav-icon {
      width: 1rem;
      font-size: 0.72rem;
      opacity: 0.78;
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
          {if $currentUser}
            <li class="nav-item d-none d-md-block"><a href="{$baseUrl}?controller=index" class="nav-link">Start</a></li>
            {if $currentUser.role eq 'admin' or in_array('products', $currentUser.modules)}
              <li class="nav-item d-none d-md-block"><a href="{$baseUrl}?controller=products&action=index" class="nav-link">Produkty</a></li>
            {/if}
            {if $currentUser.role eq 'admin' or in_array('allegro', $currentUser.modules)}
              <li class="nav-item d-none d-md-block"><a href="{$baseUrl}?controller=allegro&action=index" class="nav-link">Allegro</a></li>
            {/if}
            {if $currentUser.role eq 'admin' or in_array('empik', $currentUser.modules)}
              <li class="nav-item d-none d-md-block"><a href="{$baseUrl}?controller=empik&action=index" class="nav-link">Empik</a></li>
            {/if}
            {if $currentUser.role eq 'admin' or in_array('sellasist', $currentUser.modules)}
              <li class="nav-item d-none d-md-block"><a href="{$baseUrl}?controller=sellasist&action=zbieranie" class="nav-link">Sellasist</a></li>
            {/if}
            {if $currentUser.role eq 'admin' or in_array('printtemplates', $currentUser.modules)}
              <li class="nav-item d-none d-md-block"><a href="http://192.168.1.149/tinyfilemanager.php?p=" class="nav-link" target="_blank" rel="noreferrer">Szablon druku</a></li>
            {/if}
            {if $currentUser.role eq 'admin'}
              <li class="nav-item d-none d-md-block"><a href="{$baseUrl}?controller=categories&action=index" class="nav-link">Kategorie</a></li>
              <li class="nav-item d-none d-md-block"><a href="{$baseUrl}?controller=csvtemplates&action=index" class="nav-link">Szablony CSV</a></li>
              <li class="nav-item d-none d-md-block"><a href="{$baseUrl}?controller=admin&action=users" class="nav-link">Admin</a></li>
              <li class="nav-item d-none d-md-block"><a href="{$baseUrl}?controller=admin&action=automation" class="nav-link">Administracja</a></li>
            {/if}
          {/if}
        </ul>

        {if $currentUser && ($currentUser.role eq 'admin' or in_array('products', $currentUser.modules))}
          <div class="quick-search-wrap mx-2 flex-grow-1">
            <form method="get" action="{$baseUrl}" id="globalProductSearchForm" autocomplete="off">
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
        {/if}

        <ul class="navbar-nav ms-auto">
          {if $currentUser}
            <li class="nav-item">
              <span class="nav-link text-secondary topbar-user-link">
                <span class="topbar-user-name">
                  {if $currentUser.first_name|default:'' neq '' or $currentUser.last_name|default:'' neq ''}
                    {$currentUser.first_name|default:''|escape} {$currentUser.last_name|default:''|escape}
                  {else}
                    {$currentUser.email|escape}
                  {/if}
                </span>
                <span class="badge topbar-user-role text-bg-{if $currentUser.role eq 'admin'}dark{else}secondary{/if}">{$currentUser.role|escape}</span>
              </span>
            </li>
            <li class="nav-item"><a href="{$baseUrl}?controller=auth&action=logout" class="nav-link">Wyloguj</a></li>
          {else}
            <li class="nav-item"><a href="{$baseUrl}?controller=auth&action=login" class="nav-link">Logowanie</a></li>
            <li class="nav-item"><a href="{$baseUrl}?controller=auth&action=register" class="nav-link">Rejestracja</a></li>
          {/if}
        </ul>
      </div>
    </nav>

    <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
      <div class="sidebar-brand">
        <a href="{if $currentUser}{$baseUrl}?controller=index{else}{$baseUrl}?controller=auth&action=login{/if}" class="brand-link">
          <img src="{$assetBase}/assets/img/AdminLTELogo.png" alt="Logo" class="brand-image opacity-75 shadow">
          <span class="brand-text fw-light">{$appName|escape}</span>
        </a>
      </div>

      <div class="sidebar-wrapper">
        <nav class="mt-2">
          <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation" data-accordion="false">
            {if $currentUser}
              <li class="nav-item">
                <a href="{$baseUrl}?controller=index" class="nav-link{if $currentController eq 'index'} active{/if}">
                  <i class="nav-icon bi bi-speedometer2"></i>
                  <p>Dashboard</p>
                </a>
              </li>
              {if $currentUser.role eq 'admin' or in_array('products', $currentUser.modules)}
                <li class="nav-item">
                  <a href="{$baseUrl}?controller=products&action=index" class="nav-link{if $currentController eq 'products'} active{/if}">
                    <i class="nav-icon bi bi-box-seam"></i>
                    <p>Lista produktow</p>
                  </a>
                </li>
              {/if}
              {if $currentUser.role eq 'admin' or in_array('allegro', $currentUser.modules)}
                <li class="nav-item">
                  <a href="{$baseUrl}?controller=allegro&action=index" class="nav-link{if $currentController eq 'allegro'} active{/if}">
                    <i class="nav-icon bi bi-shop"></i>
                    <p>Allegro</p>
                  </a>
                </li>
              {/if}
              {if $currentUser.role eq 'admin' or in_array('empik', $currentUser.modules)}
                <li class="nav-item">
                  <a href="{$baseUrl}?controller=empik&action=index" class="nav-link{if $currentController eq 'empik'} active{/if}">
                    <i class="nav-icon bi bi-bag"></i>
                    <p>Empik</p>
                  </a>
                </li>
              {/if}
              {if $currentUser.role eq 'admin' or in_array('sellasist', $currentUser.modules)}
                <li class="nav-item">
                  <a href="{$baseUrl}?controller=sellasist&action=zbieranie" class="nav-link{if $currentController eq 'sellasist'} active{/if}">
                    <i class="nav-icon bi bi-bag-check"></i>
                    <p>Sellasist</p>
                  </a>
                </li>
              {/if}
              {if $currentUser.role eq 'admin' or in_array('printtemplates', $currentUser.modules)}
                <li class="nav-item">
                  <a href="#" class="nav-link">
                    <i class="nav-icon bi bi-printer"></i>
                    <p>
                      Szablon druku
                      <i class="nav-arrow bi bi-chevron-right"></i>
                    </p>
                  </a>
                  <ul class="nav nav-treeview taskboard-submenu">
                    <li class="nav-item">
                      <a href="http://192.168.1.149/UV_template.php?template=clear18_" class="nav-link" target="_blank" rel="noreferrer">
                        <i class="nav-icon bi bi-box-arrow-up-right"></i>
                        <p>UV Template</p>
                      </a>
                    </li>
                    <li class="nav-item">
                      <a href="http://192.168.1.149/tinyfilemanager.php?p=" class="nav-link" target="_blank" rel="noreferrer">
                        <i class="nav-icon bi bi-folder2-open"></i>
                        <p>Tiny File Manager</p>
                      </a>
                    </li>
                  </ul>
                </li>
              {/if}
              {if $currentUser.role eq 'admin' or in_array('taskboard', $currentUser.modules)}
                <li class="nav-item{if $currentController eq 'taskboard'} menu-open{/if}">
                  <a href="{$baseUrl}?controller=taskboard&action=index" class="nav-link{if $currentController eq 'taskboard'} active{/if}">
                    <i class="nav-icon bi bi-kanban"></i>
                    <p>
                      Taskboard
                      <i class="nav-arrow bi bi-chevron-right"></i>
                    </p>
                  </a>
                  <ul class="nav nav-treeview taskboard-submenu">
                    {foreach $taskboardMenuBoards as $menuBoard}
                      <li class="nav-item">
                        <a href="{$baseUrl}?controller=taskboard&action=index&board_id={$menuBoard.id}" class="nav-link{if $currentController eq 'taskboard' and $taskboardMenuSelectedBoardId eq $menuBoard.id} active{/if}">
                          <i class="nav-icon bi bi-circle" style="color: {$menuBoard.accent_color|default:'#0d6efd'|escape};"></i>
                          <p>{$menuBoard.name|truncate:24|escape}</p>
                        </a>
                      </li>
                    {foreachelse}
                      <li class="nav-item">
                        <a href="{$baseUrl}?controller=taskboard&action=index" class="nav-link{if $currentController eq 'taskboard'} active{/if}">
                          <i class="nav-icon bi bi-circle"></i>
                          <p>Brak tablic</p>
                        </a>
                      </li>
                    {/foreach}
                    {if $taskboardMenuCanWrite}
                      <li class="nav-item">
                        <a href="{$baseUrl}?controller=taskboard&action=index&create_board=1" class="nav-link">
                          <i class="nav-icon bi bi-plus-circle"></i>
                          <p>Dodaj nowa tablice</p>
                        </a>
                      </li>
                    {/if}
                  </ul>
                </li>
              {/if}
              {if $currentUser.role eq 'admin'}
                <li class="nav-item">
                  <a href="{$baseUrl}?controller=categories&action=index" class="nav-link{if $currentController eq 'categories'} active{/if}">
                    <i class="nav-icon bi bi-tags"></i>
                    <p>Lista kategorii</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="{$baseUrl}?controller=csvtemplates&action=index" class="nav-link{if $currentController eq 'csvtemplates'} active{/if}">
                    <i class="nav-icon bi bi-file-earmark-spreadsheet"></i>
                    <p>Szablony CSV</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="{$baseUrl}?controller=admin&action=users" class="nav-link{if $currentController eq 'admin' and $currentAction neq 'automation'} active{/if}">
                    <i class="nav-icon bi bi-people"></i>
                    <p>Uzytkownicy</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="{$baseUrl}?controller=admin&action=automation" class="nav-link{if $currentController eq 'admin' and $currentAction eq 'automation'} active{/if}">
                    <i class="nav-icon bi bi-clock-history"></i>
                    <p>Administracja</p>
                  </a>
                </li>
              {/if}
            {else}
              <li class="nav-item">
                <a href="{$baseUrl}?controller=auth&action=login" class="nav-link{if $currentController eq 'auth' and $currentAction eq 'login'} active{/if}">
                  <i class="nav-icon bi bi-box-arrow-in-right"></i>
                  <p>Logowanie</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="{$baseUrl}?controller=auth&action=register" class="nav-link{if $currentController eq 'auth' and $currentAction eq 'register'} active{/if}">
                  <i class="nav-icon bi bi-person-plus"></i>
                  <p>Rejestracja</p>
                </a>
              </li>
            {/if}
          </ul>
        </nav>
      </div>
    </aside>
