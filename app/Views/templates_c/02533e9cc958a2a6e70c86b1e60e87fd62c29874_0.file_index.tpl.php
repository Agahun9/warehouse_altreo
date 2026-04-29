<?php
/* Smarty version 5.8.0, created on 2026-04-28 21:41:23
  from 'file:index.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.8.0',
  'unifunc' => 'content_69f10d63cb8396_72873134',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '02533e9cc958a2a6e70c86b1e60e87fd62c29874' => 
    array (
      0 => 'index.tpl',
      1 => 1777405237,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69f10d63cb8396_72873134 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/pfuuseajvz/domains/magazyn.altreo.pl/public_html/crm/new_version/app/Views/templates';
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

      <?php $_smarty_tpl->assign('allegroQueueTotal', $_smarty_tpl->getValue('allegroQueueStats')['pending']+$_smarty_tpl->getValue('allegroQueueStats')['processing']+$_smarty_tpl->getValue('allegroQueueStats')['done']+$_smarty_tpl->getValue('allegroQueueStats')['error']+$_smarty_tpl->getValue('allegroQueueStats')['retry'], false, NULL);?>
      <?php $_smarty_tpl->assign('allegroQueueRemaining', $_smarty_tpl->getValue('allegroQueueStats')['pending']+$_smarty_tpl->getValue('allegroQueueStats')['processing']+$_smarty_tpl->getValue('allegroQueueStats')['retry'], false, NULL);?>
      <?php $_smarty_tpl->assign('sellasistFailureTotal', (($tmp = $_smarty_tpl->getValue('sellasistFailureStats')['total_count'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp), false, NULL);?>
      <?php $_smarty_tpl->assign('sellasistFailureLast24h', (($tmp = $_smarty_tpl->getValue('sellasistFailureStats')['last_24h_count'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp), false, NULL);?>
      <?php if ($_smarty_tpl->getValue('allegroQueueTotal') > 0) {?>
        <?php $_smarty_tpl->assign('allegroQueueDonePercent', ($_smarty_tpl->getValue('allegroQueueStats')['done']*100)/$_smarty_tpl->getValue('allegroQueueTotal'), false, NULL);?>
        <?php $_smarty_tpl->assign('allegroQueueRemainingPercent', ($_smarty_tpl->getValue('allegroQueueRemaining')*100)/$_smarty_tpl->getValue('allegroQueueTotal'), false, NULL);?>
        <?php $_smarty_tpl->assign('allegroQueueErrorPercent', ($_smarty_tpl->getValue('allegroQueueStats')['error']*100)/$_smarty_tpl->getValue('allegroQueueTotal'), false, NULL);?>
      <?php } else { ?>
        <?php $_smarty_tpl->assign('allegroQueueDonePercent', 0, false, NULL);?>
        <?php $_smarty_tpl->assign('allegroQueueRemainingPercent', 0, false, NULL);?>
        <?php $_smarty_tpl->assign('allegroQueueErrorPercent', 0, false, NULL);?>
      <?php }?>

      <style>
        .dashboard-focus-card {
          position: relative;
          overflow: hidden;
          border: 0;
          border-radius: 1.25rem;
          color: #fff;
          box-shadow: 0 20px 45px rgba(15, 23, 42, 0.14);
        }

        .dashboard-focus-card::before {
          content: '';
          position: absolute;
          inset: auto -10% -35% auto;
          width: 13rem;
          height: 13rem;
          border-radius: 50%;
          background: rgba(255, 255, 255, 0.12);
        }

        .dashboard-focus-card::after {
          content: '';
          position: absolute;
          inset: -30% auto auto -10%;
          width: 10rem;
          height: 10rem;
          border-radius: 50%;
          background: rgba(255, 255, 255, 0.08);
        }

        .dashboard-focus-card .card-body {
          position: relative;
          z-index: 1;
          padding: 1.35rem;
        }

        .dashboard-focus-card-sellasist {
          background: linear-gradient(135deg, #0f766e 0%, #14b8a6 55%, #67e8f9 100%);
        }

        .dashboard-focus-card-allegro {
          background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 52%, #60a5fa 100%);
        }

        .dashboard-focus-label {
          text-transform: uppercase;
          letter-spacing: 0.08em;
          font-size: 0.74rem;
          font-weight: 700;
          opacity: 0.82;
        }

        .dashboard-focus-value {
          font-size: 2.5rem;
          font-weight: 700;
          line-height: 1;
        }

        .dashboard-focus-chip {
          display: inline-flex;
          align-items: center;
          gap: 0.35rem;
          padding: 0.35rem 0.75rem;
          border-radius: 999px;
          background: rgba(255, 255, 255, 0.14);
          color: #fff;
          font-size: 0.8rem;
          font-weight: 700;
        }

        .dashboard-mini-stat {
          border-radius: 0.95rem;
          background: rgba(255, 255, 255, 0.12);
          padding: 0.85rem 0.9rem;
          min-height: 100%;
        }

        .dashboard-mini-stat strong {
          display: block;
          font-size: 1.2rem;
          color: #fff;
        }

        .dashboard-focus-note {
          color: rgba(255, 255, 255, 0.84);
          font-size: 0.84rem;
        }

        .dashboard-sellasist-chart-wrap {
          margin: 0.4rem 0 1rem;
          padding: 0.9rem 0.9rem 0.65rem;
          border-radius: 1rem;
          background: rgba(255, 255, 255, 0.12);
        }

        .dashboard-sellasist-chart {
          width: 100%;
          height: auto;
          display: block;
        }

        .dashboard-sellasist-grid {
          stroke: rgba(255, 255, 255, 0.18);
          stroke-width: 1;
        }

        .dashboard-sellasist-line-orders {
          fill: none;
          stroke: #f8fafc;
          stroke-width: 3;
          stroke-linecap: round;
          stroke-linejoin: round;
        }

        .dashboard-sellasist-line-value {
          fill: none;
          stroke: #fde047;
          stroke-width: 3;
          stroke-linecap: round;
          stroke-linejoin: round;
        }

        .dashboard-sellasist-legend {
          display: flex;
          flex-wrap: wrap;
          gap: 0.85rem;
          margin-bottom: 0.55rem;
        }

        .dashboard-sellasist-legend-item {
          display: inline-flex;
          align-items: center;
          gap: 0.4rem;
          font-size: 0.8rem;
          font-weight: 600;
          color: rgba(255, 255, 255, 0.88);
        }

        .dashboard-sellasist-legend-line {
          width: 1.25rem;
          height: 0.2rem;
          border-radius: 999px;
          display: inline-block;
        }

        .dashboard-sellasist-legend-orders {
          background: #f8fafc;
        }

        .dashboard-sellasist-legend-value {
          background: #fde047;
        }

        .dashboard-sellasist-labels {
          display: grid;
          grid-template-columns: repeat(7, minmax(0, 1fr));
          gap: 0.35rem;
          margin-top: 0.45rem;
        }

        .dashboard-sellasist-labels span {
          text-align: center;
          font-size: 0.72rem;
          color: rgba(255, 255, 255, 0.74);
        }

        .dashboard-allegro-progress {
          display: flex;
          overflow: hidden;
          height: 0.7rem;
          border-radius: 999px;
          background: rgba(255, 255, 255, 0.18);
          box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.12);
        }

        .dashboard-allegro-progress-done {
          background: #198754;
        }

        .dashboard-allegro-progress-remaining {
          background: #f59e0b;
        }

        .dashboard-allegro-progress-error {
          background: #dc3545;
        }

        .dashboard-allegro-chip {
          display: inline-flex;
          align-items: center;
          gap: 0.35rem;
          padding: 0.24rem 0.62rem;
          border-radius: 999px;
          background: rgba(255, 255, 255, 0.14);
          color: #fff;
          font-size: 0.84rem;
          font-weight: 600;
        }

        .dashboard-sellasist-failures .card-header {
          display: flex;
          flex-wrap: wrap;
          align-items: flex-start;
          justify-content: space-between;
          gap: 0.75rem;
        }

        .dashboard-sellasist-failures-title {
          min-width: min(100%, 18rem);
          flex: 1 1 18rem;
        }

        .dashboard-sellasist-failures-title .card-title {
          float: none;
          margin: 0 0 0.25rem;
          line-height: 1.25;
        }

        .dashboard-sellasist-failures .badge {
          flex: 0 0 auto;
          white-space: nowrap;
        }
      </style>

      <div class="row">
        <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('stats'), 'stat');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('stat')->value) {
$foreach0DoElse = false;
?>
          <div class="col-xl-3 col-lg-4 col-sm-6">
            <div class="small-box text-bg-<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('stat')['theme'], ENT_QUOTES, 'UTF-8', true);?>
">
              <div class="inner">
                <h3><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('stat')['value'], ENT_QUOTES, 'UTF-8', true);?>
</h3>
                <p><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('stat')['label'], ENT_QUOTES, 'UTF-8', true);?>
</p>
              </div>
              <div class="small-box-icon"><i class="bi <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('stat')['icon'], ENT_QUOTES, 'UTF-8', true);?>
"></i></div>
              <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=index" class="small-box-footer link-light link-underline-opacity-0">Szczegoly <i class="bi bi-arrow-right-short"></i></a>
            </div>
          </div>
        <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
      </div>

      <div class="row">
        <div class="col-xl-4">
          <div class="card dashboard-focus-card dashboard-focus-card-sellasist mb-4">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                <div>
                  <div class="dashboard-focus-label">Sellasist dzisiaj</div>
                  <div class="dashboard-focus-value mt-2"><?php echo (($tmp = $_smarty_tpl->getValue('sellasistTodayStats')['orders_count'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp);?>
</div>
                </div>
                <span class="dashboard-focus-chip">magazyn</span>
              </div>

              <div class="dashboard-sellasist-chart-wrap">
                <div class="dashboard-sellasist-legend">
                  <span class="dashboard-sellasist-legend-item"><span class="dashboard-sellasist-legend-line dashboard-sellasist-legend-orders"></span>Zamowienia</span>
                  <span class="dashboard-sellasist-legend-item"><span class="dashboard-sellasist-legend-line dashboard-sellasist-legend-value"></span>Wartosc produktow</span>
                </div>

                <svg class="dashboard-sellasist-chart" viewBox="0 0 320 132" aria-label="Wykres Sellasist z ostatnich dni">
                  <line class="dashboard-sellasist-grid" x1="8" y1="18" x2="312" y2="18"></line>
                  <line class="dashboard-sellasist-grid" x1="8" y1="64" x2="312" y2="64"></line>
                  <line class="dashboard-sellasist-grid" x1="8" y1="114" x2="312" y2="114"></line>
                  <polyline class="dashboard-sellasist-line-orders" points="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('sellasistChart')['orders_points'], ENT_QUOTES, 'UTF-8', true);?>
"></polyline>
                  <polyline class="dashboard-sellasist-line-value" points="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('sellasistChart')['value_points'], ENT_QUOTES, 'UTF-8', true);?>
"></polyline>
                </svg>

                <div class="dashboard-sellasist-labels">
                  <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('sellasistDailySeries'), 'point');
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('point')->value) {
$foreach1DoElse = false;
?>
                    <span><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('point')['label'], ENT_QUOTES, 'UTF-8', true);?>
</span>
                  <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                </div>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-6">
                  <div class="dashboard-mini-stat">
                    <span class="dashboard-focus-label d-block mb-1">Dzis zamowienia</span>
                    <strong><?php echo (($tmp = $_smarty_tpl->getValue('sellasistTodayStats')['orders_count'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp);?>
</strong>
                    <span class="dashboard-focus-note">skala: 0-<?php echo (($tmp = $_smarty_tpl->getValue('sellasistChart')['y_axis_orders'][1] ?? null)===null||$tmp==='' ? 1 ?? null : $tmp);?>
</span>
                  </div>
                </div>
                <div class="col-6">
                  <div class="dashboard-mini-stat">
                    <span class="dashboard-focus-label d-block mb-1">Dzis wartosc</span>
                    <strong><?php echo sprintf('%.2f',(($tmp = $_smarty_tpl->getValue('sellasistTodayStats')['total_value'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp));?>
</strong>
                    <span class="dashboard-focus-note"><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('sellasistTodayStats')['currency'] ?? null)===null||$tmp==='' ? 'PLN' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
, bez wysylki</span>
                  </div>
                </div>
              </div>

              <div class="dashboard-focus-note">Wykres pokazuje ostatnie 7 dni. Zolty wykres liczy tylko wartosc produktow z zamowien, bez kosztow wysylki.</div>
            </div>
          </div>

          <div class="card mb-4 border-danger dashboard-sellasist-failures">
            <div class="card-header">
              <div class="dashboard-sellasist-failures-title">
                <h3 class="card-title mb-1">Bledne wywolania Sellasist</h3>
                <div class="small text-secondary">Ostatnie nieudane wejscia na odejmowanie i dodawanie stanu.</div>
              </div>
              <span class="badge text-bg-danger">Ostatnie 24h: <?php echo $_smarty_tpl->getValue('sellasistFailureLast24h');?>
</span>
            </div>
            <div class="card-body">
              <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
                <div>
                  <div class="small text-secondary">Wszystkie bledy</div>
                  <div class="h4 mb-0"><?php echo $_smarty_tpl->getValue('sellasistFailureTotal');?>
</div>
                </div>
                <div class="text-sm-end">
                  <div class="small text-secondary">Ostatnie</div>
                  <div class="fw-semibold"><?php if ((($tmp = $_smarty_tpl->getValue('sellasistFailureStats')['latest_at'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp)) {
echo htmlspecialchars((string)$_smarty_tpl->getValue('sellasistFailureStats')['latest_at'], ENT_QUOTES, 'UTF-8', true);
} else { ?>-<?php }?></div>
                </div>
              </div>

              <?php if ($_smarty_tpl->getValue('sellasistFailureStats')['latest']) {?>
                <div class="list-group list-group-flush border rounded mb-3">
                  <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('sellasistFailureStats')['latest'], 'failure');
$foreach2DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('failure')->value) {
$foreach2DoElse = false;
?>
                    <div class="list-group-item px-3 py-2">
                      <div class="d-flex justify-content-between gap-2">
                        <span class="fw-semibold"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('failure')['operation_label'], ENT_QUOTES, 'UTF-8', true);
if ((($tmp = $_smarty_tpl->getValue('failure')['order_id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp)) {?> #<?php echo $_smarty_tpl->getValue('failure')['order_id'];
}?></span>
                        <span class="small text-secondary text-nowrap"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('failure')['created_at'], ENT_QUOTES, 'UTF-8', true);?>
</span>
                      </div>
                      <div class="small text-secondary">
                        <?php if ((($tmp = $_smarty_tpl->getValue('failure')['request_method'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp)) {
echo htmlspecialchars((string)$_smarty_tpl->getValue('failure')['request_method'], ENT_QUOTES, 'UTF-8', true);
}?>
                        <?php if ((($tmp = $_smarty_tpl->getValue('failure')['response_status'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp)) {?> <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('failure')['response_status'], ENT_QUOTES, 'UTF-8', true);
}?>
                      </div>
                      <div class="small text-secondary"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('failure')['error_message'], ENT_QUOTES, 'UTF-8', true);?>
</div>
                    </div>
                  <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                </div>
              <?php } else { ?>
                <div class="alert alert-success mb-3">Brak zapisanych blednych wywolan Sellasist.</div>
              <?php }?>

              <?php if ($_smarty_tpl->getValue('currentUser')['permission_level'] != 'read') {?>
                <?php if ($_smarty_tpl->getValue('currentUser')['role'] == 'admin' || $_smarty_tpl->getSmarty()->getModifierCallback('in_array')('sellasist',$_smarty_tpl->getValue('currentUser')['modules'])) {?>
                  <form method="post" action="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=index&action=clearsellasistfailures" onsubmit="return confirm('Usunac wszystkie zapisane bledne wywolania Sellasist z dashboardu?');">
                    <button type="submit" class="btn btn-sm btn-outline-danger" <?php if ($_smarty_tpl->getValue('sellasistFailureTotal') <= 0) {?>disabled<?php }?>>
                      <i class="bi bi-trash"></i> Usun bledy Sellasist
                    </button>
                  </form>
                <?php }?>
              <?php }?>
            </div>
          </div>

          <div class="card dashboard-focus-card dashboard-focus-card-allegro mb-4">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                <div>
                  <div class="dashboard-focus-label">Kolejka Allegro</div>
                  <div class="h3 mb-1 mt-2">Zostalo <?php echo $_smarty_tpl->getValue('allegroQueueRemaining');?>
</div>
                  <div class="dashboard-focus-note">Zrobione <?php echo $_smarty_tpl->getValue('allegroQueueStats')['done'];?>
 z <?php echo $_smarty_tpl->getValue('allegroQueueTotal');?>
</div>
                </div>
                <span class="dashboard-allegro-chip"><?php echo sprintf('%.0f',$_smarty_tpl->getValue('allegroQueueDonePercent'));?>
%</span>
              </div>

              <div class="dashboard-allegro-progress mb-3" aria-label="Postep kolejki Allegro">
                <?php if ($_smarty_tpl->getValue('allegroQueueDonePercent') > 0) {?><div class="dashboard-allegro-progress-done" style="width: <?php echo sprintf('%.2f',$_smarty_tpl->getValue('allegroQueueDonePercent'));?>
%;"></div><?php }?>
                <?php if ($_smarty_tpl->getValue('allegroQueueRemainingPercent') > 0) {?><div class="dashboard-allegro-progress-remaining" style="width: <?php echo sprintf('%.2f',$_smarty_tpl->getValue('allegroQueueRemainingPercent'));?>
%;"></div><?php }?>
                <?php if ($_smarty_tpl->getValue('allegroQueueErrorPercent') > 0) {?><div class="dashboard-allegro-progress-error" style="width: <?php echo sprintf('%.2f',$_smarty_tpl->getValue('allegroQueueErrorPercent'));?>
%;"></div><?php }?>
              </div>

              <div class="row g-3 small mb-3">
                <div class="col-6">
                  <div class="dashboard-mini-stat">
                    <div class="dashboard-focus-label mb-1">Oczekuje + ponow</div>
                    <strong><?php echo $_smarty_tpl->getValue('allegroQueueStats')['pending']+$_smarty_tpl->getValue('allegroQueueStats')['retry'];?>
</strong>
                  </div>
                </div>
                <div class="col-6">
                  <div class="dashboard-mini-stat">
                    <div class="dashboard-focus-label mb-1">W toku</div>
                    <strong><?php echo $_smarty_tpl->getValue('allegroQueueStats')['processing'];?>
</strong>
                  </div>
                </div>
                <div class="col-6">
                  <div class="dashboard-mini-stat">
                    <div class="dashboard-focus-label mb-1">Bledy</div>
                    <strong><?php echo $_smarty_tpl->getValue('allegroQueueStats')['error'];?>
</strong>
                  </div>
                </div>
                <div class="col-6">
                  <div class="dashboard-mini-stat">
                    <div class="dashboard-focus-label mb-1">Gotowe</div>
                    <strong><?php echo $_smarty_tpl->getValue('allegroQueueStats')['done'];?>
</strong>
                  </div>
                </div>
              </div>

              <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=index" class="btn btn-sm btn-light">Otworz Allegro</a>
              <?php if ($_smarty_tpl->getValue('currentUser')['role'] == 'admin' || $_smarty_tpl->getSmarty()->getModifierCallback('in_array')('empik',$_smarty_tpl->getValue('currentUser')['modules'])) {?>
                <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=empik&action=index" class="btn btn-sm btn-outline-light">Otworz Empik</a>
              <?php }?>
            </div>
          </div>

          <div class="card mb-4">
            <div class="card-header"><h3 class="card-title mb-0">Szybkie akcje</h3></div>
            <div class="card-body d-grid gap-2">
              <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=products&action=create" class="btn btn-primary">Dodaj produkt</a>
              <?php if ($_smarty_tpl->getValue('currentUser')['role'] == 'admin') {?>
                <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=categories&action=create" class="btn btn-outline-dark">Dodaj kategorie</a>
                <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=admin&action=users" class="btn btn-outline-secondary">Zarzadzaj uzytkownikami</a>
              <?php }?>
            </div>
          </div>

          <div class="card mb-4">
            <div class="card-header"><h3 class="card-title mb-0">Priorytety na dzis</h3></div>
            <div class="card-body p-0">
              <ul class="list-group list-group-flush">
                <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('activities'), 'activity');
$foreach3DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('activity')->value) {
$foreach3DoElse = false;
?>
                  <li class="list-group-item"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('activity'), ENT_QUOTES, 'UTF-8', true);?>
</li>
                <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
              </ul>
            </div>
          </div>
        </div>

        <div class="col-xl-8">
          <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h3 class="card-title mb-0">Historia zmian produktow</h3>
              <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=products&action=index" class="btn btn-sm btn-outline-primary">Otworz liste produktow</a>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Produkt</th>
                      <th>Akcja</th>
                      <th>Kto</th>
                      <th>Co sie zmienilo</th>
                      <th>Kiedy</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($_smarty_tpl->getValue('recentProductChanges')) {?>
                      <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('recentProductChanges'), 'change');
$foreach4DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('change')->value) {
$foreach4DoElse = false;
?>
                        <tr>
                          <td>
                            <div class="fw-semibold"><?php if ((($tmp = $_smarty_tpl->getValue('change')['product_name_snapshot'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp)) {
echo htmlspecialchars((string)$_smarty_tpl->getValue('change')['product_name_snapshot'], ENT_QUOTES, 'UTF-8', true);
} else { ?>Produkt #<?php echo $_smarty_tpl->getValue('change')['product_id'];
}?></div>
                            <div class="small text-secondary">
                              <span class="me-2">ID: <?php echo $_smarty_tpl->getValue('change')['product_id'];?>
</span>
                              <span class="badge text-bg-secondary"><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('change')['product_sku_snapshot'] ?? null)===null||$tmp==='' ? 'brak SKU' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</span>
                            </div>
                          </td>
                          <td><span class="badge text-bg-info"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('change')['action_label'], ENT_QUOTES, 'UTF-8', true);?>
</span></td>
                          <td><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('change')['actor_display'], ENT_QUOTES, 'UTF-8', true);?>
</td>
                          <td class="small"><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('change')['summary'] ?? null)===null||$tmp==='' ? 'Zapisano zmiany.' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
                          <td class="text-nowrap"><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('change')['created_at'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
                        </tr>
                      <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                    <?php } else { ?>
                      <tr>
                        <td colspan="5" class="text-center py-3">Brak historii zmian produktow.</td>
                      </tr>
                    <?php }?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <?php if ($_smarty_tpl->getValue('currentUser')['role'] == 'admin') {?>
        <div class="row">
          <div class="col-12">
            <div class="card mb-4">
              <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Ostatnio dodani uzytkownicy</h3>
                <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=admin&action=users" class="btn btn-sm btn-outline-dark">Panel admina</a>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-sm table-striped table-hover table-bordered mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>ID</th>
                        <th>Uzytkownik</th>
                        <th>Rola</th>
                        <th>Dostep</th>
                        <th>Status</th>
                        <th>Utworzono</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if ($_smarty_tpl->getValue('recentUsers')) {?>
                        <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('recentUsers'), 'user');
$foreach5DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('user')->value) {
$foreach5DoElse = false;
?>
                          <tr>
                            <td><?php echo $_smarty_tpl->getValue('user')['id'];?>
</td>
                            <td>
                              <div class="fw-semibold"><?php if ((($tmp = $_smarty_tpl->getValue('user')['first_name'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp) != '' || (($tmp = $_smarty_tpl->getValue('user')['last_name'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp) != '') {
echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('user')['first_name'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
 <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('user')['last_name'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);
} else {
echo htmlspecialchars((string)$_smarty_tpl->getValue('user')['email'], ENT_QUOTES, 'UTF-8', true);
}?></div>
                              <div class="small text-secondary"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('user')['email'], ENT_QUOTES, 'UTF-8', true);?>
</div>
                            </td>
                            <td><span class="badge text-bg-<?php if ($_smarty_tpl->getValue('user')['role'] == 'admin') {?>dark<?php } else { ?>secondary<?php }?>"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('user')['role'], ENT_QUOTES, 'UTF-8', true);?>
</span></td>
                            <td><span class="badge text-bg-<?php if ($_smarty_tpl->getValue('user')['permission_level'] == 'read') {?>warning<?php } else { ?>primary<?php }?>"><?php if ($_smarty_tpl->getValue('user')['permission_level'] == 'read') {?>odczyt<?php } else { ?>edycja<?php }?></span></td>
                            <td>
                              <?php if ($_smarty_tpl->getValue('user')['is_active']) {?><span class="badge text-bg-success">aktywne</span><?php } else { ?><span class="badge text-bg-warning">nieaktywne</span><?php }?>
                              <?php if ($_smarty_tpl->getValue('user')['is_blocked']) {?><span class="badge text-bg-danger ms-1">zablokowane</span><?php }?>
                            </td>
                            <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('user')['created_at'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
                          </tr>
                        <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                      <?php } else { ?>
                        <tr>
                          <td colspan="6" class="text-center py-3">Brak danych o uzytkownikach.</td>
                        </tr>
                      <?php }?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php }?>
    </div>
  </div>
</main>
<?php }
}
