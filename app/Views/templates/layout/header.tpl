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
    :root {
      --app-accent: #0d6efd;
      --app-accent-soft: rgba(13, 110, 253, 0.12);
      --app-loader-bg: rgba(248, 250, 252, 0.82);
      --app-loader-surface: rgba(255, 255, 255, 0.92);
      --app-loader-shadow: 0 24px 60px rgba(15, 23, 42, 0.16);
    }

    body {
      transition: background-color 0.28s ease;
    }

    body.page-is-loading {
      overflow: hidden;
    }

    .app-main {
      opacity: 0;
      transform: translateY(10px);
      transition: opacity 0.35s ease, transform 0.35s ease;
      will-change: opacity, transform;
    }

    body.app-ready .app-main {
      opacity: 1;
      transform: translateY(0);
    }

    .card,
    .products-page-header-shell,
    .allegro-pagination-panel {
      transition: transform 0.24s ease, box-shadow 0.24s ease, opacity 0.24s ease;
    }

    .card:hover,
    .products-page-header-shell:hover,
    .allegro-pagination-panel:hover {
      transform: translateY(-1px);
    }

    .app-page-loader {
      position: fixed;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.25rem;
      background:
        radial-gradient(circle at top, rgba(13, 110, 253, 0.12), transparent 40%),
        var(--app-loader-bg);
      backdrop-filter: blur(8px);
      opacity: 0;
      visibility: hidden;
      pointer-events: none;
      transition: opacity 0.22s ease, visibility 0.22s ease;
      z-index: 2000;
    }

    .app-page-loader.is-active {
      opacity: 1;
      visibility: visible;
      pointer-events: auto;
    }

    .app-page-loader-card {
      min-width: min(360px, 92vw);
      max-width: 420px;
      padding: 1.15rem 1.2rem;
      border: 1px solid rgba(15, 23, 42, 0.08);
      border-radius: 1.15rem;
      background: var(--app-loader-surface);
      box-shadow: var(--app-loader-shadow);
    }

    .app-page-loader-top {
      display: flex;
      align-items: center;
      gap: 0.85rem;
    }

    .app-page-loader-spinner {
      width: 3rem;
      height: 3rem;
      flex: 0 0 auto;
      border-radius: 999px;
      border: 3px solid rgba(13, 110, 253, 0.16);
      border-top-color: var(--app-accent);
      animation: appPageLoaderSpin 0.9s linear infinite;
    }

    .app-page-loader-title {
      margin: 0;
      font-size: 1rem;
      font-weight: 700;
      color: #0f172a;
    }

    .app-page-loader-text {
      margin: 0.18rem 0 0;
      font-size: 0.88rem;
      color: #64748b;
    }

    .app-page-loader-progress {
      margin-top: 0.95rem;
      height: 0.45rem;
      overflow: hidden;
      border-radius: 999px;
      background: rgba(148, 163, 184, 0.18);
    }

    .app-page-loader-progress-bar {
      height: 100%;
      width: 38%;
      border-radius: inherit;
      background: linear-gradient(90deg, #0d6efd, #60a5fa);
      animation: appPageLoaderPulse 1.2s ease-in-out infinite;
      transform-origin: left center;
    }

    @keyframes appPageLoaderSpin {
      to {
        transform: rotate(360deg);
      }
    }

    @keyframes appPageLoaderPulse {
      0%,
      100% {
        transform: translateX(0) scaleX(0.85);
        opacity: 0.82;
      }

      50% {
        transform: translateX(145%) scaleX(1.25);
        opacity: 1;
      }
    }

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
      padding: 0.7rem 0.8rem;
      color: inherit;
      border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    }

    .quick-search-item:last-child {
      border-bottom: 0;
    }

    .quick-search-item:hover {
      background: rgba(13, 110, 253, 0.08);
    }

    .quick-search-item-main {
      display: block;
      color: inherit;
      text-decoration: none;
    }

    .quick-search-item-main:hover {
      color: inherit;
    }

    .quick-search-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.4rem;
      margin-top: 0.5rem;
    }

    .quick-search-topline {
      display: flex;
      flex-wrap: wrap;
      gap: 0.45rem;
      align-items: center;
      margin-bottom: 0.3rem;
    }

    .quick-search-sku {
      font-size: 0.74rem;
      line-height: 1;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: #0d6efd;
      background: rgba(13, 110, 253, 0.08);
      border: 1px solid rgba(13, 110, 253, 0.14);
      border-radius: 999px;
      padding: 0.24rem 0.48rem;
    }

    .quick-search-old-sku {
      font-size: 0.72rem;
      color: #6c757d;
    }

    .quick-search-name {
      font-size: 0.94rem;
      font-weight: 600;
      color: #17202a;
      line-height: 1.25;
    }

    .quick-search-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 0.45rem;
      margin-top: 0.45rem;
    }

    .quick-search-meta-chip {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      padding: 0.2rem 0.5rem;
      border-radius: 999px;
      background: #f3f5f7;
      color: #334155;
      font-size: 0.74rem;
      font-weight: 600;
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
  <div id="appPageLoader" class="app-page-loader" aria-live="polite" aria-hidden="true">
    <div class="app-page-loader-card">
      <div class="app-page-loader-top">
        <div class="app-page-loader-spinner" aria-hidden="true"></div>
        <div>
          <p class="app-page-loader-title">Ladowanie widoku</p>
          <p class="app-page-loader-text" id="appPageLoaderText">Trwa pobieranie danych i odswiezanie ekranu.</p>
        </div>
      </div>
      <div class="app-page-loader-progress" aria-hidden="true">
        <div class="app-page-loader-progress-bar"></div>
      </div>
    </div>
  </div>
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
                <button type="submit" class="btn btn-primary">Szukaj</button>
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
