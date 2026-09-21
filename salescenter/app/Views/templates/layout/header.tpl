<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <title>{$appName|escape} | {$pageTitle|default:'Panel'|escape}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/svg+xml" href="{$assetBase}/assets/img/warehouse-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="{$assetBase}/css/adminlte.css">
  <link rel="stylesheet" href="{$assetBase}/css/liquid-glass.css?v=20260630-7">
  {if $currentController eq 'orders' or $currentController eq 'index' or $currentController eq 'messages'}
    <link rel="stylesheet" href="{$assetBase}/css/orders.css?v=20260918-6">
  {/if}
  {if $currentController eq 'messages'}
    <link rel="stylesheet" href="{$assetBase}/css/messages.css?v=20260918-1">
  {/if}
  <style>
    /*
     * Kompaktowy interfejs bez CSS zoom. Zmniejszenie bazowego rem skaluje
     * typografie, formularze i odstepy, nie psujac obliczen szerokosci AdminLTE.
     */
    html {
      font-size: 80%;
    }

    .app-wrapper {
      --lte-sidebar-width: 205px;
    }

    .app-sidebar .sidebar-menu .nav-link {
      padding-left: 0.65rem;
      padding-right: 0.65rem;
    }

    .app-sidebar .sidebar-menu .nav-icon {
      margin-right: 0.35rem;
    }

    .app-page-loader {
      position: fixed;
      inset: 0;
      z-index: 20000;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
      visibility: hidden;
      opacity: 0;
      pointer-events: none;
      background: rgba(248, 250, 252, 0.78);
      backdrop-filter: blur(4px);
      transition: opacity 0.16s ease, visibility 0.16s ease;
    }

    .app-page-loader.is-active {
      visibility: visible;
      opacity: 1;
      pointer-events: auto;
    }

    .app-page-loader-card {
      display: flex;
      align-items: center;
      gap: 1rem;
      min-width: 260px;
      max-width: 440px;
      padding: 1rem 1.25rem;
      color: #1f2937;
      background: rgba(255, 255, 255, 0.96);
      border: 1px solid rgba(15, 23, 42, 0.12);
      border-radius: 0.8rem;
      box-shadow: 0 18px 50px rgba(15, 23, 42, 0.18);
    }

    .app-page-loader-spinner {
      width: 2rem;
      height: 2rem;
      flex: 0 0 auto;
    }

    .app-page-loader-title {
      font-weight: 700;
    }

    .app-page-loader-text {
      margin-top: 0.15rem;
      color: #64748b;
      font-size: 0.9rem;
    }

    .app-page-loader-close {
      margin-left: auto;
      flex: 0 0 auto;
      display: none;
      white-space: nowrap;
    }

    .app-page-loader-close.is-visible {
      display: inline-block;
    }

    body.page-is-loading {
      cursor: wait;
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

    .quick-search-item {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 0.65rem;
      align-items: center;
    }

    .quick-search-item-main {
      min-width: 0;
      color: inherit;
      text-decoration: none;
    }

    .quick-search-topline {
      display: flex;
      flex-wrap: wrap;
      gap: 0.4rem;
      align-items: center;
      margin-bottom: 0.15rem;
    }

    .quick-search-sku,
    .quick-search-old-sku,
    .quick-search-meta-chip {
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      max-width: 100%;
      padding: 0.08rem 0.38rem;
      overflow: hidden;
      font-size: 0.78rem;
      line-height: 1.35;
      text-overflow: ellipsis;
      white-space: nowrap;
      border-radius: 0.35rem;
    }

    .quick-search-sku {
      font-weight: 700;
      color: #0f172a;
      background: #e0f2fe;
    }

    .quick-search-old-sku {
      color: #334155;
      background: #f1f5f9;
    }

    .quick-search-name {
      overflow: hidden;
      font-weight: 600;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .quick-search-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 0.35rem;
      margin-top: 0.25rem;
      color: #64748b;
    }

    .quick-search-meta-chip {
      background: #f8fafc;
    }

    .quick-search-actions {
      flex: 0 0 auto;
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

    .app-brand-link {
      display: flex;
      align-items: center;
      justify-content: flex-start;
      gap: 0.55rem;
      width: 100%;
      text-align: left;
    }

    .app-brand-text {
      margin: 0;
      line-height: 1;
      font-weight: 600;
      letter-spacing: 0;
    }
  </style>
  <style>
    .sc-auth { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; background: linear-gradient(135deg, #eef2ff 0%, #f8fafc 60%, #ecfeff 100%); }
    .sc-auth-card { width: 100%; max-width: 460px; background: #fff; border: 1px solid rgba(15,23,42,.08); border-radius: 1rem; box-shadow: 0 24px 60px rgba(15,23,42,.12); padding: 2rem; }
    .sc-auth-card.wide { max-width: 620px; }
    .sc-auth-brand { display: flex; align-items: center; gap: .6rem; font-weight: 800; font-size: 1.35rem; margin-bottom: 1.25rem; color: #0f172a; }
    .sc-auth-brand span { display: inline-flex; width: 2.2rem; height: 2.2rem; border-radius: .6rem; align-items: center; justify-content: center; color: #fff; background: #4f46e5; }
    .sc-page { padding: 1.5rem; max-width: 1100px; }
    .sc-tenant-name { font-weight: 700; color: #0f172a; }
  </style>

  <style>
    /* SalesCenter – lewy panel */
    .sc-app .app-wrapper { --lte-sidebar-width: 236px; }
    .sc-sidebar { display: flex; flex-direction: column; }
    .sc-sidebar .sidebar-wrapper { flex: 1 1 auto; overflow-y: auto; overflow-x: hidden; }
    .sc-sidebar .sidebar-menu { padding: .4rem .65rem .8rem; }
    .sc-brand { display: flex !important; align-items: center; gap: .7rem; padding: .95rem 1rem !important; overflow: hidden; white-space: nowrap; }
    .sc-brand-mark { flex: 0 0 36px; width: 36px; height: 36px; display: grid; place-items: center; border-radius: 11px; font-weight: 800; font-size: 1.1rem; color: #fff; background: linear-gradient(135deg, #818cf8, #38bdf8); box-shadow: 0 8px 20px rgba(56,189,248,.28); }
    .sc-brand .app-brand-copy { min-width: 0; }
    .sc-brand .app-brand-subtitle { max-width: 160px; overflow: hidden; text-overflow: ellipsis; text-transform: none; letter-spacing: 0; font-size: .72rem; }
    .sc-nav-header { padding: .95rem .7rem .3rem !important; font-size: .64rem !important; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: rgba(191,219,254,.55) !important; white-space: nowrap; }
    .sc-sidebar .sidebar-menu .nav-link { display: flex; align-items: center; white-space: nowrap; overflow: hidden; }
    .sc-sidebar .sidebar-menu .nav-link p { margin: 0 0 0 .15rem; overflow: hidden; text-overflow: ellipsis; }
    .sc-nav-badge { margin-left: auto; min-width: 20px; padding: 1px 6px; border-radius: 10px; background: #f59e0b; color: #1f2937; font-size: .7rem; font-weight: 800; text-align: center; line-height: 1.5; }
    body.sc-app.sidebar-collapse .sc-sidebar .nav-link { position: relative; }
    @media (min-width: 992px) { body.sc-app.sidebar-collapse .sc-nav-badge { position: absolute; top: 3px; right: 6px; min-width: 16px; padding: 0 4px; font-size: .6rem; } }
    .sc-sidebar-footer { padding: .6rem .65rem .9rem; border-top: 1px solid rgba(255,255,255,.12); }
    .sc-logout { width: 100%; display: flex; align-items: center; min-height: 40px; padding: .56rem .68rem; border: 1px solid transparent; border-radius: 11px; background: transparent; color: rgba(239,246,255,.78); font-weight: 570; white-space: nowrap; overflow: hidden; }
    .sc-logout p { margin: 0 0 0 .15rem; font-size: .9rem; }
    .sc-logout .nav-icon { width: 22px; margin-right: .48rem; color: rgba(224,231,255,.72); }
    .sc-logout:hover { color: #fff; background: rgba(248,113,113,.18); border-color: rgba(255,255,255,.1); }
    /* Tryb zwinięty: równa kolumna ikon, bez tekstów i nagłówków */
    @media (min-width: 992px) {
      body.sc-app.sidebar-collapse .sc-sidebar .app-brand-copy,
      body.sc-app.sidebar-collapse .sc-sidebar .nav-link p,
      body.sc-app.sidebar-collapse .sc-sidebar .sc-logout p { display: none !important; }
      /* Stała kolumna ikon – bez rozsuwania po najechaniu; nazwy w podpowiedziach (title). */
      body.sc-app.sidebar-collapse .sc-sidebar,
      body.sc-app.sidebar-collapse .sc-sidebar:hover { min-width: 4.6rem !important; max-width: 4.6rem !important; }
      body.sc-app.sidebar-collapse .sc-sidebar .nav-link { width: auto !important; }
      body.sc-app.sidebar-collapse .sc-nav-header { display: block !important; height: 1px; padding: 0 !important; margin: .55rem .9rem; overflow: hidden; font-size: 0 !important; background: rgba(255,255,255,.14); }
      body.sc-app.sidebar-collapse .sc-brand { justify-content: center !important; padding: .95rem 0 !important; }
      body.sc-app.sidebar-collapse .sc-sidebar .sidebar-menu,
      body.sc-app.sidebar-collapse .sc-sidebar-footer { padding-left: .55rem; padding-right: .55rem; }
      body.sc-app.sidebar-collapse .sc-sidebar .nav-link,
      body.sc-app.sidebar-collapse .sc-logout { justify-content: center; padding-left: 0 !important; padding-right: 0 !important; }
      body.sc-app.sidebar-collapse .sc-sidebar .nav-icon { margin-right: 0 !important; font-size: 1.08rem !important; }
      body.sc-app.sidebar-collapse .sc-sidebar-footer form { display: flex; justify-content: center; }
      body.sc-app.sidebar-collapse .sc-logout { width: 100%; }
      body.sc-app.sidebar-collapse .sc-logout .nav-icon { display: inline-grid; place-items: center; }
      body.sc-app.sidebar-collapse .sc-sidebar .nav-link.active { box-shadow: inset 0 0 0 1px rgba(255,255,255,.18), 0 8px 20px rgba(15,23,42,.2); }
    }
  </style>
</head>
{if $currentUser}
<body class="layout-fixed sidebar-expand-lg sidebar-mini bg-body-tertiary sc-app{if !empty($detail)} sidebar-collapse sc-order-detail{/if}">
  {literal}<script>try{if(document.body.classList.contains('sc-order-detail')){throw 0;}if(localStorage.getItem('sc-sidebar')==='collapsed'||(localStorage.getItem('sc-sidebar')===null&&window.innerWidth<1400)){document.body.classList.add('sidebar-collapse');}}catch(e){}</script>{/literal}
  <div class="app-page-loader" id="appPageLoader" aria-hidden="true" aria-live="polite">
    <div class="app-page-loader-card" role="status">
      <div class="spinner-border text-primary app-page-loader-spinner" aria-hidden="true"></div>
      <div>
        <div class="app-page-loader-title">Ładowanie</div>
        <div class="app-page-loader-text" id="appPageLoaderText">Trwa pobieranie danych.</div>
      </div>
      <button type="button" class="btn btn-sm btn-outline-secondary app-page-loader-close" id="appPageLoaderCloseBtn">Zamknij ładowanie</button>
    </div>
  </div>
  <div class="app-wrapper">
    <nav class="app-header navbar navbar-expand bg-body">
      <div class="container-fluid">
        <ul class="navbar-nav">
          <li class="nav-item"><a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="bi bi-list"></i></a></li>
          <li class="nav-item d-none d-md-block"><span class="nav-link sc-tenant-name"><i class="bi bi-building"></i> {$currentUser.tenant.name|escape}</span></li>
        </ul>
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <span class="nav-link text-secondary topbar-user-link">
              <span class="topbar-user-name">{$currentUser.name|default:$currentUser.email|escape}</span>
              <span class="badge topbar-user-role text-bg-{if $currentUser.role eq 'owner'}dark{else}secondary{/if}">{$currentUser.role_label|escape}</span>
            </span>
          </li>
        </ul>
      </div>
    </nav>

    <aside class="app-sidebar sc-sidebar">
      <div class="sidebar-brand">
        <a href="orders.php" class="brand-link app-brand-link sc-brand" title="{$appName|escape}">
          <span class="sc-brand-mark">S</span>
          <span class="app-brand-copy">
            <span class="brand-text app-brand-text">{$appName|escape}</span>
            <span class="app-brand-subtitle">{$currentUser.tenant.name|escape|truncate:26:'…'}</span>
          </span>
        </a>
      </div>
      <div class="sidebar-wrapper">
        <nav class="mt-1">
          <ul class="nav sidebar-menu flex-column" role="navigation">
            <li class="nav-header sc-nav-header">Sprzedaż</li>
            <li class="nav-item"><a href="orders.php" class="nav-link{if ($currentController eq 'orders' or $currentController eq 'index') and ($tab|default:'list') eq 'list'} active{/if}" title="Zamówienia"><i class="nav-icon bi bi-inbox"></i><p>Zamówienia</p></a></li>
            <li class="nav-item"><a href="orders.php?tab=new" class="nav-link{if ($currentController eq 'orders' or $currentController eq 'index') and ($tab|default:'') eq 'new'} active{/if}" title="Nowe zamówienie"><i class="nav-icon bi bi-plus-circle"></i><p>Nowe zamówienie</p></a></li>
            <li class="nav-item"><a href="{$baseUrl}?controller=messages" class="nav-link{if $currentController eq 'messages'} active{/if}" title="Wiadomości{if $messagesOpenCount|default:0 > 0} ({$messagesOpenCount} do obsługi){/if}"><i class="nav-icon bi bi-chat-left-text"></i><p>Wiadomości</p>{if $messagesOpenCount|default:0 > 0}<span class="sc-nav-badge">{if $messagesOpenCount > 99}99+{else}{$messagesOpenCount}{/if}</span>{/if}</a></li>
            <li class="nav-item"><a href="orders.php?tab=documents" class="nav-link{if ($currentController eq 'orders' or $currentController eq 'index') and ($tab|default:'') eq 'documents'} active{/if}" title="Dokumenty"><i class="nav-icon bi bi-file-earmark-text"></i><p>Dokumenty</p></a></li>
            <li class="nav-header sc-nav-header">Konfiguracja</li>
            <li class="nav-item"><a href="orders.php?tab=accounts" class="nav-link{if ($currentController eq 'orders' or $currentController eq 'index') and ($tab|default:'') eq 'accounts'} active{/if}" title="Konta i import"><i class="nav-icon bi bi-plug"></i><p>Konta i import</p></a></li>
            <li class="nav-item"><a href="orders.php?tab=shipments" class="nav-link{if ($currentController eq 'orders' or $currentController eq 'index') and ($tab|default:'') eq 'shipments'} active{/if}" title="Przesyłki"><i class="nav-icon bi bi-box-seam"></i><p>Przesyłki</p></a></li>
            <li class="nav-item"><a href="orders.php?tab=rules" class="nav-link{if ($currentController eq 'orders' or $currentController eq 'index') and ($tab|default:'') eq 'rules'} active{/if}" title="Automatyzacje"><i class="nav-icon bi bi-lightning-charge"></i><p>Automatyzacje</p></a></li>
            <li class="nav-item"><a href="orders.php?tab=statuses" class="nav-link{if ($currentController eq 'orders' or $currentController eq 'index') and ($tab|default:'') eq 'statuses'} active{/if}" title="Statusy"><i class="nav-icon bi bi-diagram-3"></i><p>Statusy</p></a></li>
            <li class="nav-item"><a href="orders.php?tab=payments" class="nav-link{if ($currentController eq 'orders' or $currentController eq 'index') and ($tab|default:'') eq 'payments'} active{/if}" title="Płatności"><i class="nav-icon bi bi-credit-card"></i><p>Płatności</p></a></li>
            <li class="nav-item"><a href="orders.php?tab=printing" class="nav-link{if ($currentController eq 'orders' or $currentController eq 'index') and ($tab|default:'') eq 'printing'} active{/if}" title="Drukarki"><i class="nav-icon bi bi-printer"></i><p>Drukarki</p></a></li>
            <li class="nav-item"><a href="orders.php?tab=general" class="nav-link{if ($currentController eq 'orders' or $currentController eq 'index') and ($tab|default:'') eq 'general'} active{/if}" title="Ustawienia ogólne"><i class="nav-icon bi bi-sliders"></i><p>Ustawienia ogólne</p></a></li>
            {if $currentUser.is_headmaster}
            <li class="nav-header sc-nav-header">Administracja SalesCenter</li>
            <li class="nav-item"><a href="{$baseUrl}?controller=administration&action=index" class="nav-link{if $currentController eq 'administration' or $currentController eq 'cron'} active{/if}" title="Administracja SalesCenter"><i class="nav-icon bi bi-shield-lock"></i><p>Administracja</p></a></li>
            {/if}
          </ul>
        </nav>
      </div>
      <div class="sc-sidebar-footer">
        <form method="post" action="{$baseUrl}?controller=auth&action=logout"><input type="hidden" name="csrf" value="{$layoutCsrf|escape}"><button type="submit" class="nav-link sc-logout" title="Wyloguj"><i class="nav-icon bi bi-box-arrow-left"></i><p>Wyloguj</p></button></form>
      </div>
    </aside>
{else}
<body class="bg-body-tertiary sc-guest">
  <div class="sc-auth">
{/if}
