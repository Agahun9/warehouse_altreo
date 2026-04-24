<?php
/* Smarty version 5.8.0, created on 2026-04-23 11:27:40
  from 'file:allegro/index.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.8.0',
  'unifunc' => 'content_69e9e60c50e0e1_84259441',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '54d38f9d655b661a92fdec3277b15f15c5cec84c' => 
    array (
      0 => 'allegro/index.tpl',
      1 => 1776936456,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69e9e60c50e0e1_84259441 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/pfuuseajvz/domains/magazyn.altreo.pl/public_html/crm/new_version/app/Views/templates/allegro';
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
        .allegro-pagination-shell {
          display: flex;
          justify-content: flex-end;
        }

        .allegro-pagination-bar {
          display: flex;
          flex-wrap: wrap;
          gap: 0.75rem;
          align-items: center;
          justify-content: flex-end;
          width: 100%;
        }

        .allegro-pagination-panel {
          display: flex;
          flex-wrap: wrap;
          gap: 0.75rem;
          align-items: center;
          justify-content: flex-end;
          margin-left: auto;
          padding: 0.85rem 1rem;
          border: 1px solid rgba(0, 0, 0, 0.08);
          border-radius: 1rem;
          background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(248,249,250,0.98));
        }

        .allegro-pagination-buttons {
          display: flex;
          flex-wrap: wrap;
          gap: 0.35rem;
          justify-content: flex-end;
        }

        .allegro-topbar {
          display: flex;
          flex-wrap: wrap;
          gap: 0.75rem;
          align-items: center;
          justify-content: space-between;
        }

        .allegro-topbar-copy {
          display: flex;
          flex-direction: column;
          gap: 0.2rem;
        }

        .allegro-topbar-title {
          font-size: 1.2rem;
          font-weight: 700;
          line-height: 1.15;
          color: #17202a;
        }

        .allegro-topbar-meta {
          display: flex;
          flex-wrap: wrap;
          gap: 0.5rem;
          align-items: center;
          color: #6c757d;
          font-size: 0.92rem;
        }

        .allegro-topbar-chip {
          display: inline-flex;
          align-items: center;
          gap: 0.35rem;
          padding: 0.2rem 0.6rem;
          border-radius: 999px;
          background: #f3f5f7;
          color: #334155;
          font-weight: 600;
        }

        .allegro-topbar-actions {
          display: flex;
          flex-wrap: wrap;
          gap: 0.5rem;
          justify-content: flex-end;
          margin-left: auto;
        }

        .allegro-queue-progress {
          display: flex;
          overflow: hidden;
          height: 0.6rem;
          border-radius: 999px;
          background: #e9ecef;
          box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.08);
        }

        .allegro-queue-progress-part {
          height: 100%;
          min-width: 0;
        }

        .allegro-queue-progress-part.is-pending,
        .allegro-queue-progress-part.is-retry {
          background: #f59e0b;
        }

        .allegro-queue-progress-part.is-processing {
          background: #0d6efd;
        }

        .allegro-queue-progress-part.is-done {
          background: #198754;
        }

        .allegro-queue-progress-part.is-error {
          background: #dc3545;
        }

        .allegro-queue-mini {
          border: 1px solid rgba(15, 23, 42, 0.08);
          border-radius: 0.85rem;
          background:
            radial-gradient(circle at top right, rgba(13, 110, 253, 0.10), transparent 36%),
            linear-gradient(180deg, rgba(255,255,255,0.98), rgba(248,249,250,0.98));
        }

        .allegro-queue-mini-total {
          font-size: 1.55rem;
          font-weight: 700;
          line-height: 1;
          color: #17202a;
        }

        .allegro-queue-mini-percent {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          min-width: 3.2rem;
          padding: 0.22rem 0.55rem;
          border-radius: 999px;
          background: #eef2f6;
          color: #334155;
          font-size: 0.82rem;
          font-weight: 700;
        }

        .allegro-queue-mini-meta {
          display: flex;
          flex-wrap: wrap;
          gap: 0.45rem;
          margin-top: 0.7rem;
          font-size: 0.82rem;
        }

        .allegro-queue-mini-link {
          color: inherit;
          text-decoration: none;
        }

        .allegro-queue-mini-link:hover {
          text-decoration: underline;
        }
      </style>

      <?php $_smarty_tpl->assign('queueTotal', $_smarty_tpl->getValue('queueStats')['pending']+$_smarty_tpl->getValue('queueStats')['processing']+$_smarty_tpl->getValue('queueStats')['done']+$_smarty_tpl->getValue('queueStats')['error']+$_smarty_tpl->getValue('queueStats')['retry'], false, NULL);?>
      <?php $_smarty_tpl->assign('queueRemaining', $_smarty_tpl->getValue('queueStats')['pending']+$_smarty_tpl->getValue('queueStats')['retry']+$_smarty_tpl->getValue('queueStats')['processing'], false, NULL);?>
      <?php if ($_smarty_tpl->getValue('queueTotal') > 0) {?>
        <?php $_smarty_tpl->assign('queuePendingPercent', ($_smarty_tpl->getValue('queueStats')['pending']*100)/$_smarty_tpl->getValue('queueTotal'), false, NULL);?>
        <?php $_smarty_tpl->assign('queueProcessingPercent', ($_smarty_tpl->getValue('queueStats')['processing']*100)/$_smarty_tpl->getValue('queueTotal'), false, NULL);?>
        <?php $_smarty_tpl->assign('queueDonePercent', ($_smarty_tpl->getValue('queueStats')['done']*100)/$_smarty_tpl->getValue('queueTotal'), false, NULL);?>
        <?php $_smarty_tpl->assign('queueErrorPercent', ($_smarty_tpl->getValue('queueStats')['error']*100)/$_smarty_tpl->getValue('queueTotal'), false, NULL);?>
        <?php $_smarty_tpl->assign('queueRetryPercent', ($_smarty_tpl->getValue('queueStats')['retry']*100)/$_smarty_tpl->getValue('queueTotal'), false, NULL);?>
      <?php } else { ?>
        <?php $_smarty_tpl->assign('queuePendingPercent', 0, false, NULL);?>
        <?php $_smarty_tpl->assign('queueProcessingPercent', 0, false, NULL);?>
        <?php $_smarty_tpl->assign('queueDonePercent', 0, false, NULL);?>
        <?php $_smarty_tpl->assign('queueErrorPercent', 0, false, NULL);?>
        <?php $_smarty_tpl->assign('queueRetryPercent', 0, false, NULL);?>
      <?php }?>
      <?php $_smarty_tpl->assign('queueStatusFilter', (($tmp = $_smarty_tpl->getValue('filters')['queue_status'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), false, NULL);?>

      <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
          <div class="card h-100">
            <div class="card-body">
              <div class="text-secondary small">Wszystkie oferty</div>
              <div class="display-6 fw-semibold"><?php echo $_smarty_tpl->getValue('stats')['all'];?>
</div>
            </div>
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="card h-100">
            <div class="card-body">
              <div class="text-secondary small">Aktywne</div>
              <div class="display-6 fw-semibold text-success"><?php echo $_smarty_tpl->getValue('stats')['active'];?>
</div>
            </div>
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="card h-100">
            <div class="card-body">
              <div class="text-secondary small">Zakonczone</div>
              <div class="display-6 fw-semibold text-warning"><?php echo $_smarty_tpl->getValue('stats')['ended'];?>
</div>
            </div>
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="card h-100">
            <div class="card-body">
              <div class="text-secondary small">Nieaktywne</div>
              <div class="display-6 fw-semibold text-secondary"><?php echo $_smarty_tpl->getValue('stats')['inactive'];?>
</div>
            </div>
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="card h-100 allegro-queue-mini">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <div>
                  <div class="text-secondary small">Kolejka</div>
                  <div class="allegro-queue-mini-total"><?php echo $_smarty_tpl->getValue('queueRemaining');?>
</div>
                </div>
                <span class="allegro-queue-mini-percent"><?php echo sprintf('%.0f',$_smarty_tpl->getValue('queueDonePercent'));?>
%</span>
              </div>
              <div class="small text-secondary mb-2">
                Zostalo <?php echo $_smarty_tpl->getValue('queueRemaining');?>
,
                <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=index&page=1&per_page=<?php echo $_smarty_tpl->getValue('perPage');?>
&sort_by=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortBy'));?>
&sort_dir=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortDir'));?>
&account_id=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['account_id']);?>
&q=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['q']);?>
&sku=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['sku']);?>
&status=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['status']);?>
&duplicates=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['duplicates']);?>
&queue_status=done&linked=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['linked']);?>
&market=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['market']);?>
&invoice=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['invoice']);?>
&warehouse_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['warehouse_quantity']);?>
&allegro_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['allegro_quantity']);?>
" class="allegro-queue-mini-link text-success">gotowe <?php echo $_smarty_tpl->getValue('queueStats')['done'];?>
</a>
              </div>
              <div class="allegro-queue-progress" aria-label="Stan kolejki Allegro">
                <?php if ($_smarty_tpl->getValue('queuePendingPercent') > 0) {?><div class="allegro-queue-progress-part is-pending" style="width: <?php echo sprintf('%.2f',$_smarty_tpl->getValue('queuePendingPercent'));?>
%;"></div><?php }?>
                <?php if ($_smarty_tpl->getValue('queueRetryPercent') > 0) {?><div class="allegro-queue-progress-part is-retry" style="width: <?php echo sprintf('%.2f',$_smarty_tpl->getValue('queueRetryPercent'));?>
%;"></div><?php }?>
                <?php if ($_smarty_tpl->getValue('queueProcessingPercent') > 0) {?><div class="allegro-queue-progress-part is-processing" style="width: <?php echo sprintf('%.2f',$_smarty_tpl->getValue('queueProcessingPercent'));?>
%;"></div><?php }?>
                <?php if ($_smarty_tpl->getValue('queueDonePercent') > 0) {?><div class="allegro-queue-progress-part is-done" style="width: <?php echo sprintf('%.2f',$_smarty_tpl->getValue('queueDonePercent'));?>
%;"></div><?php }?>
                <?php if ($_smarty_tpl->getValue('queueErrorPercent') > 0) {?><div class="allegro-queue-progress-part is-error" style="width: <?php echo sprintf('%.2f',$_smarty_tpl->getValue('queueErrorPercent'));?>
%;"></div><?php }?>
              </div>
              <div class="allegro-queue-mini-meta">
                <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=index&page=1&per_page=<?php echo $_smarty_tpl->getValue('perPage');?>
&sort_by=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortBy'));?>
&sort_dir=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortDir'));?>
&account_id=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['account_id']);?>
&q=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['q']);?>
&sku=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['sku']);?>
&status=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['status']);?>
&duplicates=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['duplicates']);?>
&queue_status=pending&linked=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['linked']);?>
&market=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['market']);?>
&invoice=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['invoice']);?>
&warehouse_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['warehouse_quantity']);?>
&allegro_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['allegro_quantity']);?>
" class="allegro-queue-mini-link text-warning">Oczekuje: <?php echo $_smarty_tpl->getValue('queueStats')['pending'];?>
</a>
                <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=index&page=1&per_page=<?php echo $_smarty_tpl->getValue('perPage');?>
&sort_by=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortBy'));?>
&sort_dir=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortDir'));?>
&account_id=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['account_id']);?>
&q=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['q']);?>
&sku=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['sku']);?>
&status=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['status']);?>
&duplicates=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['duplicates']);?>
&queue_status=retry&linked=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['linked']);?>
&market=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['market']);?>
&invoice=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['invoice']);?>
&warehouse_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['warehouse_quantity']);?>
&allegro_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['allegro_quantity']);?>
" class="allegro-queue-mini-link text-warning">Ponów: <?php echo $_smarty_tpl->getValue('queueStats')['retry'];?>
</a>
                <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=index&page=1&per_page=<?php echo $_smarty_tpl->getValue('perPage');?>
&sort_by=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortBy'));?>
&sort_dir=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortDir'));?>
&account_id=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['account_id']);?>
&q=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['q']);?>
&sku=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['sku']);?>
&status=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['status']);?>
&duplicates=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['duplicates']);?>
&queue_status=error&linked=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['linked']);?>
&market=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['market']);?>
&invoice=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['invoice']);?>
&warehouse_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['warehouse_quantity']);?>
&allegro_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['allegro_quantity']);?>
" class="allegro-queue-mini-link text-danger">Błąd: <?php echo $_smarty_tpl->getValue('queueStats')['error'];?>
</a>
              </div>
              <div class="d-flex flex-wrap gap-2 mt-3">
                <form method="post" action="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=clearqueue" onsubmit="return confirm('Wyczyścić statusy gotowe, błędy i ponów? Oczekujące zostaną.');">
                  <input type="hidden" name="mode" value="statuses">
                  <button type="submit" class="btn btn-sm btn-outline-secondary">Wyczyść statusy</button>
                </form>
                <form method="post" action="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=clearqueue" onsubmit="return confirm('Usunąć całą kolejkę Allegro?');">
                  <input type="hidden" name="mode" value="all">
                  <button type="submit" class="btn btn-sm btn-outline-danger">Usuń całą kolejkę</button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card allegro-queue-card mb-4 d-none">
        <div class="card-body">
          <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-2">
            <div>
              <div class="small text-secondary">Kolejka zmian Allegro</div>
              <div class="fw-semibold"><?php echo $_smarty_tpl->getValue('queueTotal');?>
 zadań łącznie</div>
            </div>
            <div class="small text-secondary">Do zrobienia teraz: <span class="fw-semibold text-dark"><?php echo $_smarty_tpl->getValue('queueStats')['pending']+$_smarty_tpl->getValue('queueStats')['retry'];?>
</span></div>
          </div>

          <div class="allegro-queue-progress mb-2" aria-label="Stan kolejki Allegro">
            <?php if ($_smarty_tpl->getValue('queuePendingPercent') > 0) {?><div class="allegro-queue-progress-part is-pending" style="width: <?php echo sprintf('%.2f',$_smarty_tpl->getValue('queuePendingPercent'));?>
%;"></div><?php }?>
            <?php if ($_smarty_tpl->getValue('queueRetryPercent') > 0) {?><div class="allegro-queue-progress-part is-retry" style="width: <?php echo sprintf('%.2f',$_smarty_tpl->getValue('queueRetryPercent'));?>
%;"></div><?php }?>
            <?php if ($_smarty_tpl->getValue('queueProcessingPercent') > 0) {?><div class="allegro-queue-progress-part is-processing" style="width: <?php echo sprintf('%.2f',$_smarty_tpl->getValue('queueProcessingPercent'));?>
%;"></div><?php }?>
            <?php if ($_smarty_tpl->getValue('queueDonePercent') > 0) {?><div class="allegro-queue-progress-part is-done" style="width: <?php echo sprintf('%.2f',$_smarty_tpl->getValue('queueDonePercent'));?>
%;"></div><?php }?>
            <?php if ($_smarty_tpl->getValue('queueErrorPercent') > 0) {?><div class="allegro-queue-progress-part is-error" style="width: <?php echo sprintf('%.2f',$_smarty_tpl->getValue('queueErrorPercent'));?>
%;"></div><?php }?>
          </div>

          <div class="row g-3">
            <div class="col-xl col-md-4 col-6">
              <div class="allegro-queue-stat">
                <div class="small text-secondary">Oczekuje</div>
                <div class="fs-4 fw-semibold text-warning"><a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=index&page=1&per_page=<?php echo $_smarty_tpl->getValue('perPage');?>
&sort_by=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortBy'));?>
&sort_dir=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortDir'));?>
&account_id=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['account_id']);?>
&q=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['q']);?>
&sku=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['sku']);?>
&status=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['status']);?>
&duplicates=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['duplicates']);?>
&queue_status=pending&linked=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['linked']);?>
&market=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['market']);?>
&invoice=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['invoice']);?>
&warehouse_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['warehouse_quantity']);?>
&allegro_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['allegro_quantity']);?>
" class="text-warning text-decoration-none"><?php echo $_smarty_tpl->getValue('queueStats')['pending'];?>
</a></div>
              </div>
            </div>
            <div class="col-xl col-md-4 col-6">
              <div class="allegro-queue-stat">
                <div class="small text-secondary">Ponów</div>
                <div class="fs-4 fw-semibold text-warning"><a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=index&page=1&per_page=<?php echo $_smarty_tpl->getValue('perPage');?>
&sort_by=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortBy'));?>
&sort_dir=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortDir'));?>
&account_id=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['account_id']);?>
&q=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['q']);?>
&sku=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['sku']);?>
&status=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['status']);?>
&duplicates=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['duplicates']);?>
&queue_status=retry&linked=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['linked']);?>
&market=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['market']);?>
&invoice=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['invoice']);?>
&warehouse_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['warehouse_quantity']);?>
&allegro_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['allegro_quantity']);?>
" class="text-warning text-decoration-none"><?php echo $_smarty_tpl->getValue('queueStats')['retry'];?>
</a></div>
              </div>
            </div>
            <div class="col-xl col-md-4 col-6">
              <div class="allegro-queue-stat">
                <div class="small text-secondary">Gotowe</div>
                <div class="fs-4 fw-semibold text-success"><a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=index&page=1&per_page=<?php echo $_smarty_tpl->getValue('perPage');?>
&sort_by=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortBy'));?>
&sort_dir=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortDir'));?>
&account_id=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['account_id']);?>
&q=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['q']);?>
&sku=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['sku']);?>
&status=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['status']);?>
&duplicates=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['duplicates']);?>
&queue_status=done&linked=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['linked']);?>
&market=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['market']);?>
&invoice=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['invoice']);?>
&warehouse_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['warehouse_quantity']);?>
&allegro_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['allegro_quantity']);?>
" class="text-success text-decoration-none"><?php echo $_smarty_tpl->getValue('queueStats')['done'];?>
</a></div>
              </div>
            </div>
            <div class="col-xl col-md-4 col-12">
              <div class="allegro-queue-stat">
                <div class="small text-secondary">Błąd</div>
                <div class="fs-4 fw-semibold text-danger"><a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=index&page=1&per_page=<?php echo $_smarty_tpl->getValue('perPage');?>
&sort_by=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortBy'));?>
&sort_dir=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortDir'));?>
&account_id=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['account_id']);?>
&q=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['q']);?>
&sku=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['sku']);?>
&status=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['status']);?>
&duplicates=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['duplicates']);?>
&queue_status=error&linked=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['linked']);?>
&market=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['market']);?>
&invoice=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['invoice']);?>
&warehouse_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['warehouse_quantity']);?>
&allegro_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['allegro_quantity']);?>
" class="text-danger text-decoration-none"><?php echo $_smarty_tpl->getValue('queueStats')['error'];?>
</a></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <div class="allegro-topbar">
            <div class="allegro-topbar-copy">
              <div class="allegro-topbar-title">Oferty Allegro</div>
              <div class="allegro-topbar-meta">
                <span class="allegro-topbar-chip">Lacznie <?php echo $_smarty_tpl->getValue('totalOffers');?>
 ofert</span>
                <span>strona <?php echo $_smarty_tpl->getValue('page');?>
 z <?php echo $_smarty_tpl->getValue('totalPages');?>
</span>
                <?php if ($_smarty_tpl->getValue('duplicatesOnly')) {?><span class="allegro-topbar-chip">widok: tylko duble</span><?php }?>
              </div>
            </div>
            <div class="allegro-topbar-actions">
              <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#allegroBulkModal">Akcje masowe</button>
            </div>
          </div>
        </div>
        <div class="card-body border-bottom">
          <form method="get" action="<?php echo $_smarty_tpl->getValue('baseUrl');?>
" class="row g-2">
            <input type="hidden" name="controller" value="allegro">
            <input type="hidden" name="action" value="index">
            <input type="hidden" name="sort_by" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('sortBy'), ENT_QUOTES, 'UTF-8', true);?>
">
            <input type="hidden" name="sort_dir" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('sortDir'), ENT_QUOTES, 'UTF-8', true);?>
">
            <input type="hidden" name="queue_status" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('queueStatusFilter'), ENT_QUOTES, 'UTF-8', true);?>
">
            <div class="col-xl-2 col-md-6">
              <label class="form-label">Konto</label>
              <select name="account_id" class="form-select">
                <option value="">Wszystkie</option>
                <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('accounts'), 'account');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('account')->value) {
$foreach0DoElse = false;
?>
                  <option value="<?php echo $_smarty_tpl->getValue('account')['id'];?>
"<?php if ($_smarty_tpl->getValue('filters')['account_id'] == $_smarty_tpl->getValue('account')['id']) {?> selected<?php }?>><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('account')['name'], ENT_QUOTES, 'UTF-8', true);?>
</option>
                <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
              </select>
            </div>
            <div class="col-xl-2 col-md-6">
              <label class="form-label">Szukaj</label>
              <input type="text" name="q" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('filters')['q'], ENT_QUOTES, 'UTF-8', true);?>
" class="form-control" placeholder='offer id / nazwa / SKU, kilka ID oddziel przecinkiem'>
              <div class="form-text">Mozesz wkleic kilka ID oddzielonych przecinkiem lub srednikiem. Negacja: wpisz <code>-etui</code>, <code>!etui</code> albo <code>-"iphone 15"</code>, aby wykluczyc fraze z wynikow.</div>
            </div>
            <div class="col-xl-2 col-md-4">
              <label class="form-label">SKU</label>
              <input type="text" name="sku" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('filters')['sku'], ENT_QUOTES, 'UTF-8', true);?>
" class="form-control">
              <div class="form-text">Negacja: <code>-ABC</code> albo <code>!ABC</code>.</div>
            </div>
            <div class="col-xl-2 col-md-4">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <option value="">Wszystkie</option>
                <option value="ACTIVE"<?php if ($_smarty_tpl->getValue('filters')['status'] == 'ACTIVE') {?> selected<?php }?>>ACTIVE</option>
                <option value="ENDED"<?php if ($_smarty_tpl->getValue('filters')['status'] == 'ENDED') {?> selected<?php }?>>ENDED</option>
                <option value="INACTIVE"<?php if ($_smarty_tpl->getValue('filters')['status'] == 'INACTIVE') {?> selected<?php }?>>INACTIVE</option>
              </select>
            </div>
            <div class="col-xl-2 col-md-4">
              <label class="form-label">Powiazanie</label>
              <select name="linked" class="form-select">
                <option value="">Wszystkie</option>
                <option value="1"<?php if ($_smarty_tpl->getValue('filters')['linked'] == '1') {?> selected<?php }?>>Powiazane</option>
                <option value="0"<?php if ($_smarty_tpl->getValue('filters')['linked'] == '0') {?> selected<?php }?>>Bez magazynu</option>
              </select>
            </div>
            <div class="col-xl-2 col-md-4">
              <label class="form-label">Duble</label>
              <select name="duplicates" class="form-select">
                <option value="">Wszystkie</option>
                <option value="1"<?php if ($_smarty_tpl->getValue('filters')['duplicates'] == '1') {?> selected<?php }?>>Tylko duble</option>
              </select>
            </div>
            <div class="col-xl-2 col-md-4">
              <label class="form-label">Rynek</label>
              <input type="text" name="market" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('filters')['market'], ENT_QUOTES, 'UTF-8', true);?>
" class="form-control" placeholder="np. allegro-pl">
              <div class="form-text">Negacja: <code>-allegro-cz</code> albo <code>!allegro-cz</code>.</div>
            </div>
            <div class="col-xl-2 col-md-4">
              <label class="form-label">Faktura</label>
              <select name="invoice" class="form-select">
                <option value="">Wszystkie</option>
                <option value="VAT"<?php if ($_smarty_tpl->getValue('filters')['invoice'] == 'VAT') {?> selected<?php }?>>VAT</option>
                <option value="NO_INVOICE"<?php if ($_smarty_tpl->getValue('filters')['invoice'] == 'NO_INVOICE') {?> selected<?php }?>>Brak</option>
              </select>
            </div>
            <div class="col-xl-2 col-md-4">
              <label class="form-label">Stan magazyn</label>
              <input type="text" name="warehouse_quantity" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('filters')['warehouse_quantity'], ENT_QUOTES, 'UTF-8', true);?>
" class="form-control" placeholder="np. 0-5">
              <div class="form-text">Negacja: <code>-0-5</code>, <code>!0</code>.</div>
            </div>
            <div class="col-xl-2 col-md-4">
              <label class="form-label">Stan Allegro</label>
              <input type="text" name="allegro_quantity" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('filters')['allegro_quantity'], ENT_QUOTES, 'UTF-8', true);?>
" class="form-control" placeholder="np. 0-5">
            </div>
            <div class="col-xl-2 col-md-4">
              <label class="form-label">Na strone</label>
              <select name="per_page" class="form-select">
                <option value="50"<?php if ($_smarty_tpl->getValue('perPage') == 50) {?> selected<?php }?>>50</option>
                <option value="100"<?php if ($_smarty_tpl->getValue('perPage') == 100) {?> selected<?php }?>>100</option>
                <option value="200"<?php if ($_smarty_tpl->getValue('perPage') == 200) {?> selected<?php }?>>200</option>
                <option value="5000"<?php if ($_smarty_tpl->getValue('perPage') == 5000) {?> selected<?php }?>>5000</option>
                <option value="10000"<?php if ($_smarty_tpl->getValue('perPage') == 10000) {?> selected<?php }?>>10000</option>
              </select>
            </div>
            <div class="col-12 d-flex gap-2">
              <button type="submit" class="btn btn-primary">Filtruj</button>
              <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=index" class="btn btn-outline-secondary">Reset</a>
            </div>
          </form>
        </div>

        <div class="card-body border-bottom py-2">
          <div class="d-flex flex-wrap gap-2 align-items-center">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="offers-select-page">Zaznacz strone</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="offers-clear-page">Odznacz strone</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="offers-invert-page">Odwroc zaznaczenie</button>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#allegroBulkModal">Otworz akcje masowe</button>
            <span class="small text-secondary">Dziala tez zaznaczanie zakresem z klawiszem Shift.</span>
          </div>
        </div>

        <div class="card-body border-bottom py-3 allegro-pagination-shell">
          <div class="allegro-pagination-panel">
            <div class="small text-secondary">Strona <?php echo $_smarty_tpl->getValue('page');?>
 z <?php echo $_smarty_tpl->getValue('totalPages');?>
</div>
            <div class="allegro-pagination-bar">
              <div class="allegro-pagination-buttons">
                <?php $_smarty_tpl->assign('prevPage', $_smarty_tpl->getValue('page')-1, false, NULL);?>
                <?php $_smarty_tpl->assign('nextPage', $_smarty_tpl->getValue('page')+1, false, NULL);?>
                <a class="btn btn-sm btn-outline-secondary<?php if ($_smarty_tpl->getValue('page') <= 1) {?> disabled<?php }?>" href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=index&page=1&per_page=<?php echo $_smarty_tpl->getValue('perPage');?>
&sort_by=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortBy'));?>
&sort_dir=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortDir'));?>
&account_id=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['account_id']);?>
&q=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['q']);?>
&sku=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['sku']);?>
&status=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['status']);?>
&duplicates=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['duplicates']);?>
&linked=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['linked']);?>
&market=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['market']);?>
&invoice=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['invoice']);?>
&warehouse_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['warehouse_quantity']);?>
&allegro_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['allegro_quantity']);?>
&queue_status=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['queue_status']);?>
">Pierwsza</a>
                <a class="btn btn-sm btn-outline-secondary<?php if ($_smarty_tpl->getValue('page') <= 1) {?> disabled<?php }?>" href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=index&page=<?php echo $_smarty_tpl->getValue('prevPage');?>
&per_page=<?php echo $_smarty_tpl->getValue('perPage');?>
&sort_by=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortBy'));?>
&sort_dir=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortDir'));?>
&account_id=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['account_id']);?>
&q=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['q']);?>
&sku=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['sku']);?>
&status=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['status']);?>
&duplicates=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['duplicates']);?>
&linked=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['linked']);?>
&market=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['market']);?>
&invoice=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['invoice']);?>
&warehouse_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['warehouse_quantity']);?>
&allegro_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['allegro_quantity']);?>
&queue_status=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['queue_status']);?>
">Poprzednia</a>
                <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('pageWindow'), 'pageItem');
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('pageItem')->value) {
$foreach1DoElse = false;
?>
                  <?php if ($_smarty_tpl->getValue('pageItem')['type'] == 'page') {?>
                    <a class="btn btn-sm <?php if ($_smarty_tpl->getValue('pageItem')['is_current']) {?>btn-primary<?php } else { ?>btn-outline-secondary<?php }?>" href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=index&page=<?php echo $_smarty_tpl->getValue('pageItem')['value'];?>
&per_page=<?php echo $_smarty_tpl->getValue('perPage');?>
&sort_by=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortBy'));?>
&sort_dir=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortDir'));?>
&account_id=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['account_id']);?>
&q=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['q']);?>
&sku=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['sku']);?>
&status=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['status']);?>
&duplicates=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['duplicates']);?>
&linked=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['linked']);?>
&market=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['market']);?>
&invoice=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['invoice']);?>
&warehouse_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['warehouse_quantity']);?>
&allegro_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['allegro_quantity']);?>
&queue_status=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['queue_status']);?>
"><?php echo $_smarty_tpl->getValue('pageItem')['value'];?>
</a>
                  <?php } else { ?>
                    <span class="btn btn-sm btn-outline-secondary disabled">...</span>
                  <?php }?>
                <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                <a class="btn btn-sm btn-outline-secondary<?php if ($_smarty_tpl->getValue('page') >= $_smarty_tpl->getValue('totalPages')) {?> disabled<?php }?>" href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=index&page=<?php echo $_smarty_tpl->getValue('nextPage');?>
&per_page=<?php echo $_smarty_tpl->getValue('perPage');?>
&sort_by=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortBy'));?>
&sort_dir=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortDir'));?>
&account_id=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['account_id']);?>
&q=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['q']);?>
&sku=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['sku']);?>
&status=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['status']);?>
&duplicates=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['duplicates']);?>
&linked=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['linked']);?>
&market=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['market']);?>
&invoice=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['invoice']);?>
&warehouse_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['warehouse_quantity']);?>
&allegro_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['allegro_quantity']);?>
&queue_status=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['queue_status']);?>
">Nastepna</a>
                <a class="btn btn-sm btn-outline-secondary<?php if ($_smarty_tpl->getValue('page') >= $_smarty_tpl->getValue('totalPages')) {?> disabled<?php }?>" href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=index&page=<?php echo $_smarty_tpl->getValue('totalPages');?>
&per_page=<?php echo $_smarty_tpl->getValue('perPage');?>
&sort_by=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortBy'));?>
&sort_dir=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortDir'));?>
&account_id=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['account_id']);?>
&q=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['q']);?>
&sku=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['sku']);?>
&status=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['status']);?>
&duplicates=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['duplicates']);?>
&linked=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['linked']);?>
&market=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['market']);?>
&invoice=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['invoice']);?>
&warehouse_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['warehouse_quantity']);?>
&allegro_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['allegro_quantity']);?>
&queue_status=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['queue_status']);?>
">Ostatnia</a>
              </div>

              <form method="get" action="<?php echo $_smarty_tpl->getValue('baseUrl');?>
" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="controller" value="allegro">
                <input type="hidden" name="action" value="index">
                <input type="hidden" name="per_page" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('perPage'), ENT_QUOTES, 'UTF-8', true);?>
">
                <input type="hidden" name="sort_by" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('sortBy'), ENT_QUOTES, 'UTF-8', true);?>
">
                <input type="hidden" name="sort_dir" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('sortDir'), ENT_QUOTES, 'UTF-8', true);?>
">
                <input type="hidden" name="account_id" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('filters')['account_id'], ENT_QUOTES, 'UTF-8', true);?>
">
                <input type="hidden" name="q" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('filters')['q'], ENT_QUOTES, 'UTF-8', true);?>
">
                <input type="hidden" name="sku" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('filters')['sku'], ENT_QUOTES, 'UTF-8', true);?>
">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('filters')['status'], ENT_QUOTES, 'UTF-8', true);?>
">
                <input type="hidden" name="duplicates" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('filters')['duplicates'], ENT_QUOTES, 'UTF-8', true);?>
">
                <input type="hidden" name="linked" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('filters')['linked'], ENT_QUOTES, 'UTF-8', true);?>
">
                <input type="hidden" name="market" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('filters')['market'], ENT_QUOTES, 'UTF-8', true);?>
">
                <input type="hidden" name="invoice" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('filters')['invoice'], ENT_QUOTES, 'UTF-8', true);?>
">
                <input type="hidden" name="warehouse_quantity" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('filters')['warehouse_quantity'], ENT_QUOTES, 'UTF-8', true);?>
">
                <input type="hidden" name="allegro_quantity" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('filters')['allegro_quantity'], ENT_QUOTES, 'UTF-8', true);?>
">
                <input type="hidden" name="queue_status" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('queueStatusFilter'), ENT_QUOTES, 'UTF-8', true);?>
">
                <span class="small text-secondary">Przejdz do strony</span>
                <input type="number" min="1" max="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('totalPages'), ENT_QUOTES, 'UTF-8', true);?>
" name="page" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('page'), ENT_QUOTES, 'UTF-8', true);?>
" class="form-control form-control-sm" style="width:110px;">
                <button type="submit" class="btn btn-sm btn-outline-primary">Idz</button>
              </form>
            </div>
          </div>
        </div>

        <div class="modal fade" id="allegroBulkModal" tabindex="-1" aria-labelledby="allegroBulkModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-fullscreen modal-dialog-scrollable">
            <div class="modal-content">
              <div class="modal-header">
                <div>
                  <h5 class="modal-title" id="allegroBulkModalLabel">Akcje masowe Allegro</h5>
                  <div class="small text-secondary">Zlecasz paczke do worker-a bez zabierania miejsca na liscie ofert.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
              </div>
              <div class="modal-body bg-light-subtle">
          <style>
            .bulk-ops-shell {
              background: linear-gradient(180deg, rgba(255,255,255,0.92), rgba(248,249,250,0.96));
              border: 1px solid rgba(13, 110, 253, 0.12);
              border-radius: 1rem;
              padding: 1rem;
            }

            .bulk-ops-step {
              border: 1px solid rgba(0, 0, 0, 0.08);
              border-radius: 0.9rem;
              background: #fff;
              padding: 0.9rem;
              height: 100%;
            }

            .bulk-ops-step-title {
              font-size: 0.78rem;
              letter-spacing: 0.06em;
              text-transform: uppercase;
              color: #6c757d;
              margin-bottom: 0.45rem;
            }

            .bulk-ops-choice {
              border: 1px solid rgba(0, 0, 0, 0.08);
              border-radius: 0.85rem;
              padding: 0.75rem 0.9rem;
              background: #fff;
            }

            .bulk-ops-choice.active {
              border-color: rgba(13, 110, 253, 0.45);
              box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.08);
              background: rgba(13, 110, 253, 0.03);
            }

            .bulk-ops-summary {
              border-radius: 0.85rem;
              background: #0f172a;
              color: #e2e8f0;
              padding: 0.85rem 1rem;
            }

            .bulk-ops-summary strong {
              color: #fff;
            }

            .bulk-ops-hidden {
              display: none !important;
            }
          </style>
          <form method="post" action="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=queue" class="row g-3" id="allegro-bulk-form">
            <input type="hidden" name="account_id" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('filters')['account_id'], ENT_QUOTES, 'UTF-8', true);?>
">
            <input type="hidden" name="q" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('filters')['q'], ENT_QUOTES, 'UTF-8', true);?>
">
            <input type="hidden" name="sku" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('filters')['sku'], ENT_QUOTES, 'UTF-8', true);?>
">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('filters')['status'], ENT_QUOTES, 'UTF-8', true);?>
">
            <input type="hidden" name="duplicates" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('filters')['duplicates'], ENT_QUOTES, 'UTF-8', true);?>
">
            <input type="hidden" name="linked" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('filters')['linked'], ENT_QUOTES, 'UTF-8', true);?>
">
            <input type="hidden" name="market" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('filters')['market'], ENT_QUOTES, 'UTF-8', true);?>
">
            <input type="hidden" name="invoice" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('filters')['invoice'], ENT_QUOTES, 'UTF-8', true);?>
">
            <input type="hidden" name="warehouse_quantity" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('filters')['warehouse_quantity'], ENT_QUOTES, 'UTF-8', true);?>
">
            <input type="hidden" name="allegro_quantity" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('filters')['allegro_quantity'], ENT_QUOTES, 'UTF-8', true);?>
">
            <input type="hidden" name="queue_status" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('queueStatusFilter'), ENT_QUOTES, 'UTF-8', true);?>
">
            <input type="hidden" name="return_url" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('currentListUrl'), ENT_QUOTES, 'UTF-8', true);?>
">
            <div class="col-12">
              <div class="bulk-ops-shell">
                <div class="row g-3">
                  <div class="col-xl-4">
                    <div class="bulk-ops-step h-100">
                      <div class="bulk-ops-step-title">Krok 1</div>
                      <div class="fw-semibold mb-2">Wybierz zakres zmian</div>
                      <div class="d-grid gap-2">
                        <label class="bulk-ops-choice js-selection-choice" for="selection_scope_filtered">
                          <div class="form-check">
                            <input class="form-check-input" type="radio" name="selection_scope" id="selection_scope_filtered" value="filtered">
                            <span class="form-check-label fw-semibold">Wszystkie z filtrowania</span>
                          </div>
                          <div class="small text-secondary mt-1">Bierze caly wynik filtrowania z bazy, bez znaczenia ustawienia "Na strone".</div>
                        </label>
                        <label class="bulk-ops-choice active js-selection-choice" for="selection_scope_selected">
                          <div class="form-check">
                            <input class="form-check-input" type="radio" name="selection_scope" id="selection_scope_selected" value="selected" checked>
                            <span class="form-check-label fw-semibold">Tylko zaznaczone na liscie</span>
                          </div>
                          <div class="small text-secondary mt-1">Uzywa checkboxow z tabeli. Dobre do recznego wyboru kilku lub kilkudziesieciu ofert.</div>
                        </label>
                      </div>
                    </div>
                  </div>

                  <div class="col-xl-4">
                    <div class="bulk-ops-step h-100">
                      <div class="bulk-ops-step-title">Krok 2</div>
                      <div class="fw-semibold mb-2">Wybierz typ operacji</div>
                      <label class="form-label">Operacja</label>
                      <select name="operation" class="form-select mb-3" id="bulk-operation-select" required>
                        <option value="replace_name">Nazwa: znajdz i zamien</option>
                        <option value="set_name">Nazwa: ustaw recznie</option>
                        <option value="set_sku">Ustaw SKU</option>
                        <option value="set_price">Cena: ustaw recznie</option>
                        <option value="set_price_from_product">Cena: z magazynu</option>
                        <option value="set_category_parameters">Kategoria i parametry</option>
                        <option value="set_delivery">Dostawa: ustaw czas wysylki</option>
                        <option value="set_invoice">Faktura: ustaw opcje</option>
                        <option value="link_product_auto">Produkt Allegro: auto</option>
                        <option value="link_product_id">Produkt Allegro: ustaw ID</option>
                        <option value="clear_queue">Usuń z kolejki</option>
                        <option value="remove_from_system">Usuń z systemu</option>
                        <option value="remove_from_system_forever">Usuń z systemu na zawsze</option>
                        <option value="end_offer">Zakoncz oferty</option>
                        <option value="resume_offer">Wznow oferty</option>
                      </select>
                      <div class="bulk-ops-summary">
                        <div class="small text-uppercase mb-1" style="letter-spacing:0.08em;">Co zrobi ta operacja</div>
                        <div class="fw-semibold mb-1" id="bulk-operation-title">Nazwa: znajdz i zamien</div>
                        <div class="small mb-0" id="bulk-operation-description">Podmieni wskazana fraze w nazwach ofert. Dobre do masowego poprawiania literowek, marek albo dopiskow.</div>
                      </div>
                    </div>
                  </div>

                  <div class="col-xl-4">
                    <div class="bulk-ops-step h-100">
                      <div class="bulk-ops-step-title">Krok 3</div>
                      <div class="fw-semibold mb-2">Uzupelnij tylko potrzebne pola</div>

                      <div class="mb-3 js-bulk-field" data-ops="set_name,set_price">
                        <label class="form-label" id="bulk-value-label" for="bulk-value-input">Wartosc</label>
                        <input type="text" name="value" class="form-control" id="bulk-value-input" placeholder="nowa nazwa / cena / SKU">
                        <div class="form-text" id="bulk-value-help">Wpisz nowa wartosc dla zaznaczonych ofert.</div>
                      </div>

                      <div class="mb-3 js-bulk-field" data-ops="set_sku">
                        <label class="form-label" for="bulk-sku-input">Ustaw SKU</label>
                        <input type="text" class="form-control mb-2" id="bulk-sku-input" placeholder="Wpisz SKU recznie lub wybierz produkt z magazynu">
                        <input type="hidden" name="warehouse_product_id" id="bulk-warehouse-product-id" value="">
                        <div class="small text-secondary mb-2">Mozesz wpisac SKU recznie albo wyszukac produkt z magazynu i kliknac wynik. Wybrany produkt ustawi jego SKU.</div>
                        <div class="row g-2">
                          <div class="col-12">
                            <input type="text" class="form-control" id="bulk-warehouse-search" placeholder="Szukaj produktu z magazynu po SKU lub nazwie">
                          </div>
                          <div class="col-12">
                            <div class="border rounded p-2 bg-body-tertiary small bulk-ops-hidden" id="bulk-warehouse-selected"></div>
                            <div class="list-group small mt-2 bulk-ops-hidden" id="bulk-warehouse-suggestions"></div>
                          </div>
                        </div>
                      </div>

                      <div class="row g-2 js-bulk-field" data-ops="replace_name">
                        <div class="col-12">
                          <label class="form-label" for="bulk-search-input">Znajdz</label>
                          <input type="text" name="search" class="form-control" id="bulk-search-input" placeholder="fraza do zamiany">
                        </div>
                        <div class="col-12">
                          <label class="form-label" for="bulk-replace-input">Zamien na</label>
                          <input type="text" name="replace" class="form-control" id="bulk-replace-input" placeholder="nowa fraza">
                        </div>
                        <div class="col-12">
                          <div class="form-text">Podmieja tylko ten fragment nazwy, ktory wpiszesz w polu "Znajdz".</div>
                        </div>
                      </div>

                      <div class="mb-3 js-bulk-field" data-ops="link_product_id">
                        <label class="form-label" for="bulk-product-id-input">Allegro product ID</label>
                        <input type="text" name="product_id" class="form-control" id="bulk-product-id-input" placeholder="ID produktu z katalogu Allegro">
                        <div class="form-text">Uzyj, gdy znasz konkretny identyfikator produktu Allegro.</div>
                      </div>

                      <div class="mb-3 js-bulk-field" data-ops="set_delivery">
                        <label class="form-label" for="bulk-delivery-input">Czas wysylki</label>
                        <select name="delivery_value" class="form-select" id="bulk-delivery-input">
                          <option value="">Wybierz czas wysylki</option>
                          <option value="PT0H">Natychmiast</option>
                          <option value="PT24H">24H</option>
                          <option value="PT48H">48H</option>
                          <option value="PT72H">72H</option>
                          <option value="P7D">Do 7 dni</option>
                        </select>
                        <div class="form-text">Nadaje jeden czas realizacji dla calej paczki ofert.</div>
                      </div>

                      <div class="mb-3 js-bulk-field" data-ops="set_invoice">
                        <label class="form-label" for="bulk-invoice-input">Opcja faktury</label>
                        <select name="invoice_value" class="form-select" id="bulk-invoice-input">
                          <option value="">Wybierz opcje faktury</option>
                          <option value="VAT">Faktura VAT</option>
                          <option value="NO_INVOICE">Bez faktury</option>
                          <option value="B2B">B2B</option>
                        </select>
                        <div class="form-text">Przydatne przy porzadkowaniu wielu ofert po jednym standardzie sprzedazy.</div>
                      </div>

                      <div class="mb-3 js-bulk-field" data-ops="set_category_parameters">
                        <div class="border rounded p-3 bg-body-tertiary">
                          <div class="fw-semibold mb-2">Sugerowana kategoria z pierwszej oferty</div>
                          <div class="small text-secondary mb-2">Mozesz skorzystac z kategorii pierwszej zaznaczonej oferty z listy albo wybrac produkt przez wyszukiwarke magazynowa i na tej podstawie podpowiemy inna kategorie Allegro.</div>
                          <div class="row g-2">
                            <div class="col-12">
                              <label class="form-label" for="bulk-category-product-search">Wyszukaj produkt magazynowy</label>
                              <input type="text" class="form-control" id="bulk-category-product-search" placeholder="Szukaj produktu po SKU lub nazwie">
                              <div class="form-text">Podpowiedzi z wyszukiwarki nie zmieniaja zakresu akcji masowej. Sluza tylko do zasugerowania kategorii.</div>
                            </div>
                            <div class="col-12">
                              <div class="border rounded p-2 bg-white small bulk-ops-hidden" id="bulk-category-source-selected"></div>
                              <div class="list-group small mt-2 bulk-ops-hidden" id="bulk-category-product-suggestions"></div>
                            </div>
                          </div>
                          <div class="border rounded p-2 bg-white mt-3 small" id="bulk-category-suggestion-box">
                            Brak sugerowanej kategorii. Zaznacz najpierw oferte z listy albo wybierz produkt w wyszukiwarce.
                          </div>
                        </div>
                      </div>

                      <div class="mb-3 js-bulk-field" data-ops="set_category_parameters">
                        <label class="form-label" for="bulk-category-search-input">Wyszukaj lub popraw kategorie Allegro</label>
                        <div class="input-group">
                          <input type="text" class="form-control" id="bulk-category-search-input" placeholder="np. etui iphone, szklo hartowane">
                          <button type="button" class="btn btn-outline-primary" id="bulk-category-search-btn">Szukaj</button>
                        </div>
                        <input type="hidden" name="category_id" id="bulk-category-id" value="">
                        <div class="list-group small mt-2 bulk-ops-hidden" id="bulk-category-search-results"></div>
                        <div class="border rounded p-2 bg-body-tertiary mt-2 small" id="bulk-category-selected-box">Nie wybrano jeszcze kategorii Allegro.</div>
                      </div>

                      <div class="mb-3 js-bulk-field" data-ops="set_category_parameters">
                        <label class="form-label">Parametry dla wybranej kategorii</label>
                        <div class="small text-secondary mb-2">Po wybraniu kategorii wczytamy parametry Allegro i bedziesz mogl je zmienic przed dodaniem ofert do kolejki.</div>
                        <div class="border rounded p-3 bg-body-tertiary" id="bulk-category-parameters-box">
                          <div class="text-secondary">Najpierw wybierz kategorie Allegro.</div>
                        </div>
                      </div>

                      <div class="mb-0 js-bulk-field" data-ops="replace_name,set_name,set_sku,set_price,set_price_from_product,set_category_parameters,set_delivery,set_invoice,link_product_auto,link_product_id,end_offer,resume_offer">
                        <div class="form-text pt-2">Paczka bierze teraz caly wynik filtrowania bez sztucznego limitu.</div>
                      </div>
                    </div>
                  </div>

                  <div class="col-12">
                    <div class="bulk-ops-step">
                      <div class="bulk-ops-step-title">Opcjonalnie</div>
                      <div class="fw-semibold mb-2">Reczne ID ofert</div>
                      <label class="form-label" for="bulk-manual-ids">Offer ID lub lokalne ID</label>
                      <textarea name="manual_offer_ids" rows="2" class="form-control" id="bulk-manual-ids" placeholder="Zostaw puste, zeby uzyc filtrowania albo checkboxow. Wklej ID po spacji, przecinku albo w nowych liniach, jesli chcesz zrobic paczke z palca."></textarea>
                      <div class="form-text">Jesli tu cos wpiszesz, to te ID maja pierwszenstwo nad checkboxami i nad filtrowaniem.</div>
                    </div>
                  </div>

                  <div class="col-12">
                    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                      <div class="small text-secondary">
                        Zmiany ida przez worker, zeby setki i tysiace rekordow nie blokowaly panelu.
                        <span id="selected-offers-counter">Zaznaczone na stronie: 0</span>
                      </div>
                      <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="small text-secondary" id="bulk-submit-hint">Masowa podmiana nazwy w calym filtrowaniu.</span>
                        <button type="submit" class="btn btn-dark px-4">Dodaj do kolejki</button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </form>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zamknij</button>
              </div>
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-striped table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:44px;">
                  <input type="checkbox" class="form-check-input" id="offers-check-all" title="Zaznacz / odznacz widoczne">
                </th>
                <th><a href="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('sortUrls')['images'], ENT_QUOTES, 'UTF-8', true);?>
" class="link-dark text-decoration-none">Grafiki <?php if ($_smarty_tpl->getValue('sortIndicators')['images'] == 'asc') {?>&uarr;<?php } elseif ($_smarty_tpl->getValue('sortIndicators')['images'] == 'desc') {?>&darr;<?php } else { ?>&harr;<?php }?></a></th>
                <th><a href="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('sortUrls')['account'], ENT_QUOTES, 'UTF-8', true);?>
" class="link-dark text-decoration-none">Konto <?php if ($_smarty_tpl->getValue('sortIndicators')['account'] == 'asc') {?>&uarr;<?php } elseif ($_smarty_tpl->getValue('sortIndicators')['account'] == 'desc') {?>&darr;<?php } else { ?>&harr;<?php }?></a></th>
                <th><a href="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('sortUrls')['name'], ENT_QUOTES, 'UTF-8', true);?>
" class="link-dark text-decoration-none">Nazwa <?php if ($_smarty_tpl->getValue('sortIndicators')['name'] == 'asc') {?>&uarr;<?php } elseif ($_smarty_tpl->getValue('sortIndicators')['name'] == 'desc') {?>&darr;<?php } else { ?>&harr;<?php }?></a></th>
                <th><a href="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('sortUrls')['warehouse_quantity'], ENT_QUOTES, 'UTF-8', true);?>
" class="link-dark text-decoration-none">Magazyn <?php if ($_smarty_tpl->getValue('sortIndicators')['warehouse_quantity'] == 'asc') {?>&uarr;<?php } elseif ($_smarty_tpl->getValue('sortIndicators')['warehouse_quantity'] == 'desc') {?>&darr;<?php } else { ?>&harr;<?php }?></a></th>
                <th><a href="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('sortUrls')['price'], ENT_QUOTES, 'UTF-8', true);?>
" class="link-dark text-decoration-none">Cena / VAT <?php if ($_smarty_tpl->getValue('sortIndicators')['price'] == 'asc') {?>&uarr;<?php } elseif ($_smarty_tpl->getValue('sortIndicators')['price'] == 'desc') {?>&darr;<?php } else { ?>&harr;<?php }?></a></th>
                <th>Duble</th>
                <th><a href="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('sortUrls')['status'], ENT_QUOTES, 'UTF-8', true);?>
" class="link-dark text-decoration-none">Status i rynki <?php if ($_smarty_tpl->getValue('sortIndicators')['status'] == 'asc') {?>&uarr;<?php } elseif ($_smarty_tpl->getValue('sortIndicators')['status'] == 'desc') {?>&darr;<?php } else { ?>&harr;<?php }?></a></th>
                <th><a href="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('sortUrls')['sold'], ENT_QUOTES, 'UTF-8', true);?>
" class="link-dark text-decoration-none">Stany / sprzedaz <?php if ($_smarty_tpl->getValue('sortIndicators')['sold'] == 'asc') {?>&uarr;<?php } elseif ($_smarty_tpl->getValue('sortIndicators')['sold'] == 'desc') {?>&darr;<?php } else { ?>&harr;<?php }?></a></th>
                <th><a href="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('sortUrls')['linked'], ENT_QUOTES, 'UTF-8', true);?>
" class="link-dark text-decoration-none">Dane <?php if ($_smarty_tpl->getValue('sortIndicators')['linked'] == 'asc') {?>&uarr;<?php } elseif ($_smarty_tpl->getValue('sortIndicators')['linked'] == 'desc') {?>&darr;<?php } else { ?>&harr;<?php }?></a></th>
                <th><a href="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('sortUrls')['synced'], ENT_QUOTES, 'UTF-8', true);?>
" class="link-dark text-decoration-none">Pobrane <?php if ($_smarty_tpl->getValue('sortIndicators')['synced'] == 'asc') {?>&uarr;<?php } elseif ($_smarty_tpl->getValue('sortIndicators')['synced'] == 'desc') {?>&darr;<?php } else { ?>&harr;<?php }?></a></th>
                <th><a href="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('sortUrls')['updated'], ENT_QUOTES, 'UTF-8', true);?>
" class="link-dark text-decoration-none">Zmieniono <?php if ($_smarty_tpl->getValue('sortIndicators')['updated'] == 'asc') {?>&uarr;<?php } elseif ($_smarty_tpl->getValue('sortIndicators')['updated'] == 'desc') {?>&darr;<?php } else { ?>&harr;<?php }?></a></th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('offers'), 'offer');
$foreach2DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('offer')->value) {
$foreach2DoElse = false;
?>
                <tr class="js-offer-row <?php if ($_smarty_tpl->getValue('offer')['queue_meta']['row_class']) {
echo htmlspecialchars((string)$_smarty_tpl->getValue('offer')['queue_meta']['row_class'], ENT_QUOTES, 'UTF-8', true);
}?>" data-offer-id="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('offer')['offer_id'], ENT_QUOTES, 'UTF-8', true);?>
">
                  <td class="text-center">
                    <input type="checkbox" class="form-check-input js-offer-select" name="selected_offer_ids[]" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('offer')['id'], ENT_QUOTES, 'UTF-8', true);?>
" form="allegro-bulk-form" data-offer-id="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('offer')['offer_id'], ENT_QUOTES, 'UTF-8', true);?>
" data-offer-name="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('offer')['name'], ENT_QUOTES, 'UTF-8', true);?>
" data-offer-category-id="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('offer')['category_id'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
" data-offer-category-name="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('offer')['category_name'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
" data-offer-allegro-url="https://allegro.pl/oferta/<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('offer')['offer_id'], ENT_QUOTES, 'UTF-8', true);?>
" data-warehouse-product-id="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('offer')['warehouse_product_id'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
" data-warehouse-product-name="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('offer')['warehouse_product_name'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
" data-warehouse-sku="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('offer')['warehouse_sku'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
" data-warehouse-category-id="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('offer')['warehouse_category_id'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
" data-warehouse-category-name="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('offer')['warehouse_category_name'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
" data-warehouse-category-allegro-id="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('offer')['warehouse_category_allegro_id'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
"<?php if ($_smarty_tpl->getValue('offer')['duplicate_meta']['is_duplicate'] && !$_smarty_tpl->getValue('offer')['duplicate_meta']['can_end_offer']) {?> disabled title="Najstarsza oferta w grupie dubli nie moze zostac zakonczona"<?php }?>>
                  </td>
                  <td style="width:80px;">
                    <?php if ($_smarty_tpl->getValue('offer')['primary_image_url']) {?>
                      <img src="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('offer')['primary_image_url'], ENT_QUOTES, 'UTF-8', true);?>
" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:8px;">
                    <?php } else { ?>
                      <span class="text-secondary small">brak</span>
                    <?php }?>
                    <div class="small text-secondary mt-1">grafik: <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('offer')['image_count'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
                  </td>
                  <td><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('offer')['account_name'], ENT_QUOTES, 'UTF-8', true);?>
</td>
                  <td>
                    <div class="fw-semibold"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('offer')['name'], ENT_QUOTES, 'UTF-8', true);?>
</div>
                    <div class="small text-secondary">offer: <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('offer')['offer_id'], ENT_QUOTES, 'UTF-8', true);?>
</div>
                    <?php if ($_smarty_tpl->getValue('offer')['category_name']) {?><div class="small text-secondary"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('offer')['category_name'], ENT_QUOTES, 'UTF-8', true);?>
</div><?php }?>
                    <div class="small text-secondary">produkt Allegro: <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('offer')['allegro_product_id'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
                    <?php if ($_smarty_tpl->getValue('offer')['queue_meta']['has_queue_entry']) {?>
                      <div class="small mt-2">
                        <span class="badge <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('offer')['queue_meta']['badge_class'], ENT_QUOTES, 'UTF-8', true);?>
"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('offer')['queue_meta']['status_label'], ENT_QUOTES, 'UTF-8', true);?>
</span>
                        <?php if ($_smarty_tpl->getValue('offer')['queue_meta']['operation']) {?><span class="text-secondary ms-1"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('offer')['queue_meta']['operation'], ENT_QUOTES, 'UTF-8', true);?>
</span><?php }?>
                      </div>
                      <?php if ($_smarty_tpl->getValue('offer')['queue_meta']['error_message']) {?>
                        <div class="small text-danger mt-1"><?php echo htmlspecialchars((string)$_smarty_tpl->getSmarty()->getModifierCallback('truncate')($_smarty_tpl->getValue('offer')['queue_meta']['error_message'],140), ENT_QUOTES, 'UTF-8', true);?>
</div>
                      <?php }?>
                    <?php }?>
                  </td>
                  <td>
                    <div><span class="text-secondary small">Allegro SKU</span><br><code><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('offer')['sku'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</code></div>
                    <div class="mt-1"><span class="text-secondary small">Magazyn</span><br>
                      <?php if ($_smarty_tpl->getValue('offer')['warehouse_product_id']) {?>
                        <span class="fw-semibold">#<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('offer')['warehouse_product_id'], ENT_QUOTES, 'UTF-8', true);?>
 <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('offer')['warehouse_product_name'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</span><br>
                        <code><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('offer')['warehouse_sku'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</code>
                      <?php } else { ?>
                        <span class="text-danger">brak powiazania</span>
                      <?php }?>
                    </div>
                  </td>
                  <td>
                    <div class="fw-semibold">
                      <?php if ($_smarty_tpl->getValue('offer')['price_amount'] !== null) {?>
                        <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('offer')['price_amount'], ENT_QUOTES, 'UTF-8', true);?>
 <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('offer')['price_currency'], ENT_QUOTES, 'UTF-8', true);?>

                      <?php } else { ?>
                        <span class="text-secondary">-</span>
                      <?php }?>
                    </div>
                    <div class="small text-secondary">faktura: <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('offer')['invoice_type'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
                    <div class="small text-secondary">VAT: <?php if ($_smarty_tpl->getValue('offer')['invoice_type'] == 'VAT') {?>tak<?php } else { ?>nie<?php }?></div>
                    <?php if ($_smarty_tpl->getValue('offer')['marketplace_price_entries']) {?>
                      <div class="small text-secondary mt-1">
                        <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('offer')['marketplace_price_entries'], 'marketPrice');
$foreach3DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('marketPrice')->value) {
$foreach3DoElse = false;
?>
                          <div><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('marketPrice')['label'], ENT_QUOTES, 'UTF-8', true);?>
: <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('marketPrice')['price'], ENT_QUOTES, 'UTF-8', true);
if ($_smarty_tpl->getValue('marketPrice')['currency']) {?> <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('marketPrice')['currency'], ENT_QUOTES, 'UTF-8', true);
}?></div>
                        <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                      </div>
                    <?php }?>
                  </td>
                  <td class="small">
                    <?php if ($_smarty_tpl->getValue('offer')['duplicate_meta']['is_duplicate']) {?>
                      <div><span class="badge text-bg-danger">Dubel</span></div>
                      <div class="mt-1">Inne oferty: <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('offer')['duplicate_meta']['duplicate_count'], ENT_QUOTES, 'UTF-8', true);?>
</div>
                      <div>Najstarsza: <code><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('offer')['duplicate_meta']['oldest_offer_id'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</code><?php if ($_smarty_tpl->getValue('offer')['duplicate_meta']['is_oldest']) {?> <span class="text-danger">(ta oferta)</span><?php }?></div>
                      <div class="mt-1">
                        <?php if ($_smarty_tpl->getValue('offer')['duplicate_meta']['can_end_offer']) {?>
                          <span class="text-success">Do zakończenia: tak</span>
                        <?php } else { ?>
                          <span class="text-danger">Do zakończenia: nie, zostaje jako najstarsza</span>
                        <?php }?>
                      </div>
                      <div class="mt-1">
                        <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('offer')['duplicate_meta']['peer_details'], 'peer');
$foreach4DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('peer')->value) {
$foreach4DoElse = false;
?>
                          <div><code><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('peer')['offer_id'], ENT_QUOTES, 'UTF-8', true);?>
</code> <span class="text-secondary">(<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('peer')['status'], ENT_QUOTES, 'UTF-8', true);?>
)</span></div>
                        <?php
}
if ($foreach4DoElse) {
?>
                          <div class="text-secondary">Brak innych ofert w tej grupie.</div>
                        <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                      </div>
                    <?php } else { ?>
                      <span class="text-secondary">Brak</span>
                    <?php }?>
                  </td>
                  <td>
                    <div><span class="badge text-bg-light border"><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('offer')['status_label'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</span></div>
                    <div class="small text-secondary mt-1">
                      <?php if ($_smarty_tpl->getValue('offer')['marketplace_entries']) {?>
                        <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('offer')['marketplace_entries'], 'market');
$foreach5DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('market')->value) {
$foreach5DoElse = false;
?>
                          <span class="badge text-bg-light border me-1 mb-1"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('market')['label'], ENT_QUOTES, 'UTF-8', true);?>
</span>
                        <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                      <?php } else { ?>
                        rynki: -
                      <?php }?>
                    </div>
                  </td>
                  <td>
                    <div class="small">Allegro: <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('offer')['stock_available'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
                    <div class="small">Sprzedane: <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('offer')['stock_sold'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
                    <div class="small">Magazyn: <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('offer')['warehouse_quantity'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
                  </td>
                  <td class="small">
                    <div>parametry: <?php echo $_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('offer')['parameters']);?>
</div>
                    <div>grafik: <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('offer')['image_count'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
                    <div>lokalizacja: <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('offer')['warehouse_localization'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
                    <div>powiazanie: <?php if ($_smarty_tpl->getValue('offer')['warehouse_product_id']) {?>tak<?php } else { ?>nie<?php }?></div>
                    <?php if ($_smarty_tpl->getValue('offer')['queue_meta']['has_queue_entry']) {?>
                      <div class="mt-1">zadanie: <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('offer')['queue_meta']['status_label'], ENT_QUOTES, 'UTF-8', true);?>
</div>
                      <?php if ($_smarty_tpl->getValue('offer')['queue_meta']['updated_at']) {?><div>ostatnio: <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('offer')['queue_meta']['updated_at'], ENT_QUOTES, 'UTF-8', true);?>
</div><?php }?>
                      <?php if ($_smarty_tpl->getValue('offer')['queue_meta']['status'] == 'error' || $_smarty_tpl->getValue('offer')['queue_meta']['status'] == 'retry') {?><div>proby: <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('offer')['queue_meta']['attempts'], ENT_QUOTES, 'UTF-8', true);?>
</div><?php }?>
                    <?php }?>
                  </td>
                  <td class="small"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('offer')['last_synced_at'], ENT_QUOTES, 'UTF-8', true);?>
</td>
                  <td class="small"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('offer')['updated_at'], ENT_QUOTES, 'UTF-8', true);?>
</td>
                  <td>
                    <div class="d-grid gap-2">
                      <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=offer&id=<?php echo $_smarty_tpl->getValue('offer')['id'];?>
&return_url=<?php echo rawurlencode((string)$_smarty_tpl->getValue('currentListUrl'));?>
" class="btn btn-sm btn-outline-primary">Szczegoly oferty</a>
                      <a href="https://allegro.pl/oferta/<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('offer')['offer_id'], ENT_QUOTES, 'UTF-8', true);?>
" target="_blank" rel="noreferrer" class="btn btn-sm btn-outline-secondary">Przejdz do Allegro</a>
                    </div>
                  </td>
                </tr>
              <?php
}
if ($foreach2DoElse) {
?>
                <tr><td colspan="13" class="text-center text-secondary py-4">Brak ofert dla wybranych filtrow.</td></tr>
              <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
            </tbody>
          </table>
        </div>

        <div class="card-footer allegro-pagination-shell">
          <div class="allegro-pagination-panel">
            <div class="small text-secondary">Paginacja jest po stronie bazy, a akcje masowe ida do worker-a w tle.</div>
            <div class="allegro-pagination-buttons">
              <a class="btn btn-sm btn-outline-secondary<?php if ($_smarty_tpl->getValue('page') <= 1) {?> disabled<?php }?>" href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=index&page=<?php echo $_smarty_tpl->getValue('prevPage');?>
&per_page=<?php echo $_smarty_tpl->getValue('perPage');?>
&sort_by=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortBy'));?>
&sort_dir=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortDir'));?>
&account_id=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['account_id']);?>
&q=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['q']);?>
&sku=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['sku']);?>
&status=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['status']);?>
&duplicates=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['duplicates']);?>
&linked=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['linked']);?>
&market=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['market']);?>
&invoice=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['invoice']);?>
&warehouse_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['warehouse_quantity']);?>
&allegro_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['allegro_quantity']);?>
&queue_status=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['queue_status']);?>
">Poprzednia</a>
              <a class="btn btn-sm btn-outline-secondary<?php if ($_smarty_tpl->getValue('page') >= $_smarty_tpl->getValue('totalPages')) {?> disabled<?php }?>" href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=index&page=<?php echo $_smarty_tpl->getValue('nextPage');?>
&per_page=<?php echo $_smarty_tpl->getValue('perPage');?>
&sort_by=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortBy'));?>
&sort_dir=<?php echo rawurlencode((string)$_smarty_tpl->getValue('sortDir'));?>
&account_id=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['account_id']);?>
&q=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['q']);?>
&sku=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['sku']);?>
&status=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['status']);?>
&duplicates=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['duplicates']);?>
&linked=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['linked']);?>
&market=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['market']);?>
&invoice=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['invoice']);?>
&warehouse_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['warehouse_quantity']);?>
&allegro_quantity=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['allegro_quantity']);?>
&queue_status=<?php echo rawurlencode((string)$_smarty_tpl->getValue('filters')['queue_status']);?>
">Nastepna</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<?php echo '<script'; ?>
>
  (function () {
    var bulkForm = document.getElementById('allegro-bulk-form');
    var operationSelect = document.getElementById('bulk-operation-select');
    var categoryProductSearchInput = document.getElementById('bulk-category-product-search');
    var categoryProductSuggestions = document.getElementById('bulk-category-product-suggestions');
    var categorySourceSelected = document.getElementById('bulk-category-source-selected');
    var categorySuggestionBox = document.getElementById('bulk-category-suggestion-box');
    var categorySearchInput = document.getElementById('bulk-category-search-input');
    var categorySearchButton = document.getElementById('bulk-category-search-btn');
    var categorySearchResults = document.getElementById('bulk-category-search-results');
    var categoryIdInput = document.getElementById('bulk-category-id');
    var categorySelectedBox = document.getElementById('bulk-category-selected-box');
    var categoryParametersBox = document.getElementById('bulk-category-parameters-box');
    var checkboxes = Array.prototype.slice.call(document.querySelectorAll('.js-offer-select'));
    var searchTimer = null;
    var suggestedCategory = null;

    if (!bulkForm || !operationSelect || !categorySuggestionBox || !categorySelectedBox || !categoryParametersBox) {
      return;
    }

    function escapeHtml(value) {
      return String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function isCategoryOperation() {
      return operationSelect.value === 'set_category_parameters';
    }

    function firstCheckedOfferMeta() {
      var checked = checkboxes.find(function (item) { return item.checked; });
      if (!checked) {
        return null;
      }

      return {
        offerName: checked.getAttribute('data-offer-name') || '',
        offerCategoryName: checked.getAttribute('data-offer-category-name') || '',
        offerCategoryId: checked.getAttribute('data-offer-category-id') || '',
        warehouseProductName: checked.getAttribute('data-warehouse-product-name') || ''
      };
    }

    function renderSuggestedCategory() {
      if (!suggestedCategory || !suggestedCategory.id) {
        categorySuggestionBox.innerHTML = 'Brak sugerowanej kategorii. Zaznacz najpierw oferte z lista albo wybierz produkt w wyszukiwarce.';
        return;
      }

      categorySuggestionBox.innerHTML = ''
        + '<div class="fw-semibold">Sugerowana kategoria Allegro</div>'
        + '<div><code>' + escapeHtml(suggestedCategory.id) + '</code> ' + escapeHtml(suggestedCategory.path || suggestedCategory.name || '') + '</div>'
        + '<div class="small text-secondary mt-1">' + escapeHtml(suggestedCategory.sourceLabel || '') + '</div>'
        + '<button type="button" class="btn btn-sm btn-outline-primary mt-2" id="bulk-use-suggested-category">Uzyj tej kategorii</button>';

      var useButton = document.getElementById('bulk-use-suggested-category');
      if (useButton) {
        useButton.addEventListener('click', function () {
          selectCategory(suggestedCategory);
        });
      }
    }

    function refreshSuggestedCategoryFromSelection() {
      if (!isCategoryOperation()) {
        return;
      }

      if (categoryProductSearchInput && categoryProductSearchInput.value.trim() !== '') {
        return;
      }

      var meta = firstCheckedOfferMeta();
      suggestedCategory = meta && meta.offerCategoryId ? {
        id: meta.offerCategoryId,
        name: meta.offerCategoryName || '',
        path: meta.offerCategoryName || '',
        sourceLabel: 'Na podstawie kategorii pierwszej zaznaczonej oferty: ' + (meta.offerName || '')
      } : null;
      renderSuggestedCategory();
    }

    function renderCategoryParameters(items) {
      if (!items || !items.length) {
        categoryParametersBox.innerHTML = '<div class="text-secondary">Wybrana kategoria nie zwrocila parametrow.</div>';
        return;
      }

      var html = '<div class="row g-3">';
      items.forEach(function (item) {
        var pid = String(item.id || '');
        if (!pid) {
          return;
        }

        var restrictions = item.restrictions && typeof item.restrictions === 'object' ? item.restrictions : {};
        var multiple = !!item.multiple || item.type === 'multidictionary' || restrictions.multipleChoices === true || restrictions.multipleChoices === 1;
        var dict = Array.isArray(item.dictionary) ? item.dictionary : [];
        html += '<div class="col-md-6"><div class="border rounded p-3 bg-white h-100">';
        html += '<label class="form-label fw-semibold">' + escapeHtml(item.name || pid);
        if (item.required) {
          html += ' <span class="badge text-bg-warning text-dark">wymagany</span>';
        }
        html += item.describes_product ? ' <span class="badge text-bg-light border">produkt</span>' : ' <span class="badge text-bg-light border">oferta</span>';
        html += '</label>';

        if (dict.length && multiple) {
          if (dict.length > 12) {
            html += '<input type="text" class="form-control form-control-sm mb-2 js-category-param-filter" data-filter-target="bulk-category-param-list-' + escapeHtml(pid) + '" placeholder="Szukaj na liscie wartosci">';
          }
          html += '<div class="d-flex flex-column gap-2" id="bulk-category-param-list-' + escapeHtml(pid) + '" style="max-height: 220px; overflow: auto;">';
          dict.forEach(function (option, index) {
            var optId = String(option.id || option.value || '');
            var inputId = 'bulk-category-param-' + pid + '-' + index;
            html += '<div class="form-check js-category-param-option" data-filter-label="' + escapeHtml((option.value || optId).toLowerCase()) + '"><input class="form-check-input" type="checkbox" id="' + inputId + '" name="category_parameters[' + escapeHtml(pid) + '][]" value="' + escapeHtml(optId) + '"><label class="form-check-label" for="' + inputId + '">' + escapeHtml(option.value || optId) + '</label></div>';
          });
          html += '</div>';
        } else if (dict.length) {
          html += '<select class="form-select" name="category_parameters[' + escapeHtml(pid) + ']"><option value="">Wybierz wartosc</option>';
          dict.forEach(function (option) {
            var optId = String(option.id || option.value || '');
            html += '<option value="' + escapeHtml(optId) + '">' + escapeHtml(option.value || optId) + '</option>';
          });
          html += '</select>';
        } else if (multiple) {
          html += '<textarea class="form-control" name="category_parameters[' + escapeHtml(pid) + ']" rows="3" placeholder="Kazda wartosc w osobnej linii"></textarea>';
        } else if (item.type === 'integer' || item.type === 'float' || item.type === 'number') {
          html += '<input type="number" step="any" class="form-control" name="category_parameters[' + escapeHtml(pid) + ']" value="">';
        } else {
          html += '<input type="text" class="form-control" name="category_parameters[' + escapeHtml(pid) + ']" value="">';
        }

        html += '</div></div>';
      });
      html += '</div>';
      categoryParametersBox.innerHTML = html;

      Array.prototype.slice.call(categoryParametersBox.querySelectorAll('.js-category-param-filter')).forEach(function (input) {
        input.addEventListener('input', function () {
          var targetId = input.getAttribute('data-filter-target') || '';
          var container = targetId ? document.getElementById(targetId) : null;
          var phrase = input.value ? input.value.trim().toLowerCase() : '';
          if (!container) {
            return;
          }

          Array.prototype.slice.call(container.querySelectorAll('.js-category-param-option')).forEach(function (option) {
            var haystack = option.getAttribute('data-filter-label') || '';
            option.style.display = phrase === '' || haystack.indexOf(phrase) !== -1 ? '' : 'none';
          });
        });
      });
    }

    function loadCategoryParameters(categoryId) {
      categoryParametersBox.innerHTML = '<div class="text-secondary">Wczytuje parametry kategorii...</div>';
      fetch('<?php echo strtr((string)$_smarty_tpl->getValue('baseUrl'), array("\\" => "\\\\", "'" => "\\'", "\"" => "\\\"", "\r" => "\\r", 
						"\n" => "\\n", "</" => "<\/", "<!--" => "<\!--", "<s" => "<\s", "<S" => "<\S",
						"`" => "\\`", "\${" => "\\\$\{"));?>
?controller=allegro&action=parameters&id=' + encodeURIComponent(categoryId), { credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          renderCategoryParameters(data && data.items ? data.items : []);
        })
        .catch(function () {
          categoryParametersBox.innerHTML = '<div class="text-danger">Blad pobierania parametrow kategorii.</div>';
        });
    }

    function selectCategory(item) {
      if (!item || !item.id) {
        return;
      }

      categoryIdInput.value = item.id;
      categorySelectedBox.innerHTML = '<div class="fw-semibold">Wybrana kategoria</div><div><code>' + escapeHtml(item.id) + '</code> ' + escapeHtml(item.path || item.name || '') + '</div>';
      if (categorySearchInput) {
        categorySearchInput.value = item.path || item.name || '';
      }
      loadCategoryParameters(item.id);
    }

    function renderCategorySearchResults(items) {
      if (!items || !items.length) {
        categorySearchResults.innerHTML = '<div class="list-group-item text-secondary">Brak kategorii pasujacych do wyszukiwania.</div>';
        categorySearchResults.classList.remove('bulk-ops-hidden');
        return;
      }

      var html = '';
      items.forEach(function (item) {
        html += '<button type="button" class="list-group-item list-group-item-action js-category-search-result" data-id="' + escapeHtml(item.id || '') + '" data-name="' + escapeHtml(item.name || '') + '" data-path="' + escapeHtml(item.path || item.name || '') + '"><div class="fw-semibold"><code>' + escapeHtml(item.id || '') + '</code> ' + escapeHtml(item.name || '') + '</div><div class="small text-secondary">' + escapeHtml(item.path || item.name || '') + '</div></button>';
      });
      categorySearchResults.innerHTML = html;
      categorySearchResults.classList.remove('bulk-ops-hidden');

      Array.prototype.slice.call(categorySearchResults.querySelectorAll('.js-category-search-result')).forEach(function (button) {
        button.addEventListener('click', function () {
          selectCategory({
            id: button.getAttribute('data-id') || '',
            name: button.getAttribute('data-name') || '',
            path: button.getAttribute('data-path') || ''
          });
          categorySearchResults.classList.add('bulk-ops-hidden');
        });
      });
    }

    function searchAllegroCategories() {
      var phrase = categorySearchInput ? categorySearchInput.value.trim() : '';
      if (phrase.length < 2) {
        return;
      }

      fetch('<?php echo strtr((string)$_smarty_tpl->getValue('baseUrl'), array("\\" => "\\\\", "'" => "\\'", "\"" => "\\\"", "\r" => "\\r", 
						"\n" => "\\n", "</" => "<\/", "<!--" => "<\!--", "<s" => "<\s", "<S" => "<\S",
						"`" => "\\`", "\${" => "\\\$\{"));?>
?controller=allegro&action=categories&search=' + encodeURIComponent(phrase), { credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          renderCategorySearchResults(data && data.items ? data.items : []);
        })
        .catch(function () {
          categorySearchResults.innerHTML = '<div class="list-group-item text-danger">Blad pobierania kategorii Allegro.</div>';
          categorySearchResults.classList.remove('bulk-ops-hidden');
        });
    }

    function renderCategoryProductSuggestions(items) {
      if (!items || !items.length) {
        categoryProductSuggestions.innerHTML = '<div class="list-group-item text-secondary">Brak pasujacych produktow.</div>';
        categoryProductSuggestions.classList.remove('bulk-ops-hidden');
        return;
      }

      var html = '';
      items.forEach(function (item) {
        html += '<button type="button" class="list-group-item list-group-item-action js-category-product-suggestion" data-id="' + escapeHtml(item.id || '') + '" data-sku="' + escapeHtml(item.sku || '') + '" data-name="' + escapeHtml(item.product_name || '') + '" data-category-name="' + escapeHtml(item.category_name || '') + '" data-category-allegro-id="' + escapeHtml(item.category_allegro_id || '') + '"><div class="fw-semibold">' + escapeHtml(item.product_name || '-') + '</div><div class="small text-secondary">#' + escapeHtml(item.id || '') + ' | ' + escapeHtml(item.sku || '-') + ' | kategoria: ' + escapeHtml(item.category_name || '-') + '</div></button>';
      });
      categoryProductSuggestions.innerHTML = html;
      categoryProductSuggestions.classList.remove('bulk-ops-hidden');

      Array.prototype.slice.call(categoryProductSuggestions.querySelectorAll('.js-category-product-suggestion')).forEach(function (button) {
        button.addEventListener('click', function () {
          var item = {
            id: button.getAttribute('data-id') || '',
            sku: button.getAttribute('data-sku') || '',
            product_name: button.getAttribute('data-name') || '',
            category_name: button.getAttribute('data-category-name') || '',
            category_allegro_id: button.getAttribute('data-category-allegro-id') || ''
          };

          categorySourceSelected.innerHTML = '<div class="fw-semibold">Produkt do sugestii kategorii</div><div>#' + escapeHtml(item.id) + ' | ' + escapeHtml(item.sku || '-') + '</div><div class="text-secondary">' + escapeHtml(item.product_name || '-') + '</div><div class="small text-secondary mt-1">Kategoria magazynowa: ' + escapeHtml(item.category_name || '-') + '</div>';
          categorySourceSelected.classList.remove('bulk-ops-hidden');
          suggestedCategory = item.category_allegro_id ? {
            id: item.category_allegro_id,
            name: item.category_name || '',
            path: item.category_name || '',
            sourceLabel: 'Na podstawie produktu z wyszukiwania: ' + (item.product_name || '')
          } : null;
          renderSuggestedCategory();
          categoryProductSuggestions.classList.add('bulk-ops-hidden');
        });
      });
    }

    function searchCategoryProducts() {
      var query = categoryProductSearchInput ? categoryProductSearchInput.value.trim() : '';
      var meta = firstCheckedOfferMeta();
      var offerName = meta && meta.offerName ? meta.offerName : '';
      if (query.length < 2 && offerName.length < 3) {
        categoryProductSuggestions.classList.add('bulk-ops-hidden');
        return;
      }

      fetch('<?php echo strtr((string)$_smarty_tpl->getValue('baseUrl'), array("\\" => "\\\\", "'" => "\\'", "\"" => "\\\"", "\r" => "\\r", 
						"\n" => "\\n", "</" => "<\/", "<!--" => "<\!--", "<s" => "<\s", "<S" => "<\S",
						"`" => "\\`", "\${" => "\\\$\{"));?>
?controller=allegro&action=warehouseproducts&q=' + encodeURIComponent(query) + '&offer_name=' + encodeURIComponent(offerName), { credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          renderCategoryProductSuggestions(data && data.items ? data.items : []);
        })
        .catch(function () {
          categoryProductSuggestions.innerHTML = '<div class="list-group-item text-danger">Blad pobierania produktow magazynowych.</div>';
          categoryProductSuggestions.classList.remove('bulk-ops-hidden');
        });
    }

    operationSelect.addEventListener('change', refreshSuggestedCategoryFromSelection);
    checkboxes.forEach(function (item) {
      item.addEventListener('change', refreshSuggestedCategoryFromSelection);
    });

    if (categoryProductSearchInput) {
      categoryProductSearchInput.addEventListener('input', function () {
        if (categoryProductSearchInput.value.trim() === '') {
          categorySourceSelected.innerHTML = '';
          categorySourceSelected.classList.add('bulk-ops-hidden');
          refreshSuggestedCategoryFromSelection();
        }
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(searchCategoryProducts, 220);
      });
    }

    if (categorySearchButton) {
      categorySearchButton.addEventListener('click', searchAllegroCategories);
    }

    if (categorySearchInput) {
      categorySearchInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          searchAllegroCategories();
        }
      });
    }

    bulkForm.addEventListener('submit', function (event) {
      if (isCategoryOperation() && (!categoryIdInput || !categoryIdInput.value.trim())) {
        event.preventDefault();
        window.alert('Wybierz kategorie Allegro przed dodaniem zmian do kolejki.');
      }
    });

    refreshSuggestedCategoryFromSelection();
  })();

  (function () {
    var bulkForm = document.getElementById('allegro-bulk-form');
    if (!bulkForm) {
      return;
    }

    var checkboxes = Array.prototype.slice.call(document.querySelectorAll('.js-offer-select'));
    var checkAll = document.getElementById('offers-check-all');
    var selectPage = document.getElementById('offers-select-page');
    var clearPage = document.getElementById('offers-clear-page');
    var invertPage = document.getElementById('offers-invert-page');
    var counter = document.getElementById('selected-offers-counter');
    var operationSelect = document.getElementById('bulk-operation-select');
    var operationTitle = document.getElementById('bulk-operation-title');
    var operationDescription = document.getElementById('bulk-operation-description');
    var submitHint = document.getElementById('bulk-submit-hint');
    var valueLabel = document.getElementById('bulk-value-label');
    var valueInput = document.getElementById('bulk-value-input');
    var valueHelp = document.getElementById('bulk-value-help');
    var bulkSkuInput = document.getElementById('bulk-sku-input');
    var warehouseSearchInput = document.getElementById('bulk-warehouse-search');
    var warehouseSuggestions = document.getElementById('bulk-warehouse-suggestions');
    var warehouseSelected = document.getElementById('bulk-warehouse-selected');
    var warehouseProductIdInput = document.getElementById('bulk-warehouse-product-id');
    var selectionChoices = Array.prototype.slice.call(document.querySelectorAll('.js-selection-choice'));
    var selectionScopeInputs = Array.prototype.slice.call(bulkForm.querySelectorAll('input[name="selection_scope"]'));
    var bulkFields = Array.prototype.slice.call(document.querySelectorAll('.js-bulk-field'));
    var lastChecked = null;
    var warehouseSearchTimer = null;
    var operationMeta = {
      replace_name: {
        title: 'Nazwa: znajdz i zamien',
        description: 'Podmieni wskazana fraze w nazwach ofert. Dobre do masowego poprawiania literowek, marek albo dopiskow.',
        hint: 'Masowa podmiana nazwy',
        valueLabel: 'Wartosc',
        valuePlaceholder: '',
        valueHelp: ''
      },
      set_name: {
        title: 'Nazwa: ustaw recznie',
        description: 'Nadpisze cala nazwe oferty jedna, nowa wartoscia.',
        hint: 'Pelne nadpisanie nazwy',
        valueLabel: 'Nowa nazwa',
        valuePlaceholder: 'np. Etui MagSafe iPhone 15 Pro czarne',
        valueHelp: 'Ta wartosc zastapi cala nazwe oferty.'
      },
      set_sku: {
        title: 'Ustaw SKU',
        description: 'Mozesz wpisac SKU recznie albo wybrac produkt z magazynu. Po kliknieciu produktu ustawimy jego SKU na ofertach Allegro.',
        hint: 'Ustawienie SKU recznie albo z magazynu',
        valueLabel: 'Wartosc',
        valuePlaceholder: '',
        valueHelp: ''
      },
      set_price: {
        title: 'Cena: ustaw recznie',
        description: 'Ustawi jedna, wspolna cene dla calej paczki ofert.',
        hint: 'Reczne ustawienie ceny',
        valueLabel: 'Nowa cena',
        valuePlaceholder: 'np. 49.99',
        valueHelp: 'Wpisz kwote brutto w formacie 49.99.'
      },
      set_price_from_product: {
        title: 'Cena: z magazynu',
        description: 'Pobierze cene brutto z przypietego produktu magazynowego i wysle ja na Allegro.',
        hint: 'Cena pobrana z magazynu',
        valueLabel: 'Wartosc',
        valuePlaceholder: '',
        valueHelp: ''
      },
      set_category_parameters: {
        title: 'Kategoria i parametry',
        description: 'Na podstawie pierwszej zaznaczonej oferty wybierzesz sugerowana kategorie, a potem ustawisz parametry Allegro.',
        hint: 'Zmiana kategorii i parametrow Allegro',
        valueLabel: 'Wartosc',
        valuePlaceholder: '',
        valueHelp: ''
      },
      set_delivery: {
        title: 'Dostawa: ustaw czas wysylki',
        description: 'Ustawi wspolny czas realizacji wysylki dla calej zaznaczonej paczki ofert.',
        hint: 'Ustawienie czasu wysylki',
        valueLabel: 'Wartosc',
        valuePlaceholder: '',
        valueHelp: ''
      },
      set_invoice: {
        title: 'Faktura: ustaw opcje',
        description: 'Zmienisz sposob wystawiania faktury dla calego zestawu ofert jednym ruchem.',
        hint: 'Ustawienie opcji faktury',
        valueLabel: 'Wartosc',
        valuePlaceholder: '',
        valueHelp: ''
      },
      link_product_auto: {
        title: 'Produkt Allegro: auto',
        description: 'Sprobujemy automatycznie dopiac produkt katalogowy Allegro po danych oferty, EAN albo nazwie.',
        hint: 'Automatyczne laczenie z katalogiem Allegro',
        valueLabel: 'Wartosc',
        valuePlaceholder: '',
        valueHelp: ''
      },
      link_product_id: {
        title: 'Produkt Allegro: ustaw ID',
        description: 'Podepnie wskazany produkt katalogowy Allegro po konkretnym ID.',
        hint: 'Reczne przypiecie produktu Allegro',
        valueLabel: 'Wartosc',
        valuePlaceholder: '',
        valueHelp: ''
      },
      clear_queue: {
        title: 'Usuń z kolejki',
        description: 'Wyczyści aktywne wpisy kolejki tylko dla zaznaczonych ofert albo dla bieżącego filtrowania. Nie rusza całej kolejki globalnie.',
        hint: 'Czyszczenie wpisów kolejki z bieżącego zakresu',
        valueLabel: 'Wartość',
        valuePlaceholder: '',
        valueHelp: ''
      },
      remove_from_system: {
        title: 'Usuń z systemu',
        description: 'Usunie ofertę tylko lokalnie z tabeli allegro_offers. Jeśli Allegro nadal ją zwróci przy syncu, pojawi się znowu.',
        hint: 'Lokalne usunięcie oferty z systemu',
        valueLabel: 'Wartość',
        valuePlaceholder: '',
        valueHelp: ''
      },
      remove_from_system_forever: {
        title: 'Usuń z systemu na zawsze',
        description: 'Najpierw dopilnuje zakończenia oferty, potem usunie ją lokalnie i doda do trwałych wykluczeń, więc sync już jej nie pobierze.',
        hint: 'Trwałe usunięcie i blokada ponownego pobrania',
        valueLabel: 'Wartość',
        valuePlaceholder: '',
        valueHelp: ''
      },
      end_offer: {
        title: 'Zakoncz oferty',
        description: 'Wysle polecenie zakonczenia wskazanych ofert Allegro.',
        hint: 'Zakonczenie ofert',
        valueLabel: 'Wartosc',
        valuePlaceholder: '',
        valueHelp: ''
      },
      resume_offer: {
        title: 'Wznow oferty',
        description: 'Wysle polecenie wznowienia wskazanych ofert Allegro.',
        hint: 'Wznowienie ofert',
        valueLabel: 'Wartosc',
        valuePlaceholder: '',
        valueHelp: ''
      }
    };

    function updateCounter() {
      var selectable = checkboxes.filter(function (item) { return !item.disabled; });
      var checked = selectable.filter(function (item) { return item.checked; }).length;
      if (counter) {
        counter.textContent = 'Zaznaczone na stronie: ' + checked;
      }
      if (checkAll) {
        checkAll.disabled = selectable.length === 0;
        checkAll.checked = selectable.length > 0 && checked === selectable.length;
        checkAll.indeterminate = checked > 0 && checked < selectable.length;
      }
    }

    function setAll(state) {
      checkboxes.forEach(function (item) {
        if (item.disabled) {
          return;
        }
        item.checked = state;
      });
      updateCounter();
    }

    function invertAll() {
      checkboxes.forEach(function (item) {
        if (item.disabled) {
          return;
        }
        item.checked = !item.checked;
      });
      updateCounter();
    }

    function selectedScope() {
      var current = bulkForm.querySelector('input[name="selection_scope"]:checked');
      return current ? current.value : 'selected';
    }

    function refreshSelectionChoices() {
      selectionChoices.forEach(function (item) {
        var input = item.querySelector('input[name="selection_scope"]');
        item.classList.toggle('active', !!(input && input.checked));
      });
    }

    function refreshBulkOperationUI() {
      var operation = operationSelect ? operationSelect.value : 'replace_name';
      var meta = operationMeta[operation] || operationMeta.replace_name;

      if (operationTitle) {
        operationTitle.textContent = meta.title;
      }
      if (operationDescription) {
        operationDescription.textContent = meta.description;
      }
      if (submitHint) {
        submitHint.textContent = meta.hint + ' dla ' + (selectedScope() === 'selected' ? 'zaznaczonych ofert.' : 'calego filtrowania.');
      }
      if (valueLabel) {
        valueLabel.textContent = meta.valueLabel || 'Wartosc';
      }
      if (valueInput) {
        valueInput.placeholder = meta.valuePlaceholder || '';
      }
      if (valueHelp) {
        valueHelp.textContent = meta.valueHelp || '';
      }

      bulkFields.forEach(function (field) {
        var ops = String(field.getAttribute('data-ops') || '').split(',');
        var visible = ops.indexOf(operation) !== -1;
        field.classList.toggle('bulk-ops-hidden', !visible);
      });

      if (operation !== 'set_sku' && warehouseSuggestions) {
        warehouseSuggestions.classList.add('bulk-ops-hidden');
      }
      if (operation !== 'set_sku' && warehouseSelected) {
        warehouseSelected.classList.add('bulk-ops-hidden');
      }
    }

    function firstCheckedOfferMeta() {
      var checked = checkboxes.find(function (item) { return item.checked; });
      if (!checked) {
        return null;
      }

      return {
        name: checked.getAttribute('data-offer-name') || '',
        offerId: checked.getAttribute('data-offer-id') || ''
      };
    }

    function clearWarehouseSelection() {
      if (warehouseProductIdInput) {
        warehouseProductIdInput.value = '';
      }
      if (warehouseSelected) {
        warehouseSelected.innerHTML = '';
        warehouseSelected.classList.add('bulk-ops-hidden');
      }
    }

    function renderWarehouseSelection(item) {
      if (!warehouseSelected || !warehouseProductIdInput) {
        return;
      }

      warehouseProductIdInput.value = item && item.id ? String(item.id) : '';
      if (!item || !item.id) {
        clearWarehouseSelection();
        return;
      }

      warehouseSelected.innerHTML = ''
        + '<div class="fw-semibold">Wybrany produkt magazynowy</div>'
        + '<div>#' + String(item.id) + ' | ' + String(item.sku || '-') + '</div>'
        + '<div class="text-secondary">' + String(item.product_name || '-') + '</div>'
        + '<button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="bulk-clear-warehouse-selection">Wyczysc wybor</button>';
      warehouseSelected.classList.remove('bulk-ops-hidden');

      var clearBtn = document.getElementById('bulk-clear-warehouse-selection');
      if (clearBtn) {
        clearBtn.addEventListener('click', function () {
          clearWarehouseSelection();
        });
      }
    }

    function renderWarehouseSuggestions(items) {
      if (!warehouseSuggestions) {
        return;
      }

      if (!items || !items.length) {
        warehouseSuggestions.innerHTML = '<div class="list-group-item text-secondary">Brak pasujacych produktow.</div>';
        warehouseSuggestions.classList.remove('bulk-ops-hidden');
        return;
      }

      var html = '';
      items.forEach(function (item) {
        html += ''
          + '<button type="button" class="list-group-item list-group-item-action js-warehouse-suggestion"'
          + ' data-id="' + String(item.id || '') + '"'
          + ' data-sku="' + String(item.sku || '').replace(/"/g, '&quot;') + '"'
          + ' data-name="' + String(item.product_name || '').replace(/"/g, '&quot;') + '">'
          + '  <div class="fw-semibold">' + String(item.product_name || '-') + '</div>'
          + '  <div class="small text-secondary">#' + String(item.id || '') + ' | ' + String(item.sku || '-') + ' | stan: ' + String(item.quantity || 0) + '</div>'
          + '</button>';
      });
      warehouseSuggestions.innerHTML = html;
      warehouseSuggestions.classList.remove('bulk-ops-hidden');

      Array.prototype.slice.call(warehouseSuggestions.querySelectorAll('.js-warehouse-suggestion')).forEach(function (button) {
        button.addEventListener('click', function () {
          var item = {
            id: button.getAttribute('data-id') || '',
            sku: button.getAttribute('data-sku') || '',
            product_name: button.getAttribute('data-name') || ''
          };
          if (bulkSkuInput) {
            bulkSkuInput.value = item.sku || '';
          }
          if (valueInput) {
            valueInput.value = item.sku || '';
          }
          renderWarehouseSelection(item);
        });
      });
    }

    function searchWarehouseProducts() {
      if (!warehouseSearchInput || !warehouseSuggestions) {
        return;
      }

      var query = warehouseSearchInput.value ? warehouseSearchInput.value.trim() : '';
      var selectedMeta = firstCheckedOfferMeta();
      var offerName = selectedMeta && selectedMeta.name ? selectedMeta.name : '';

      if (query.length < 2 && offerName.length < 3) {
        warehouseSuggestions.classList.add('bulk-ops-hidden');
        return;
      }

      var url = '<?php echo strtr((string)$_smarty_tpl->getValue('baseUrl'), array("\\" => "\\\\", "'" => "\\'", "\"" => "\\\"", "\r" => "\\r", 
						"\n" => "\\n", "</" => "<\/", "<!--" => "<\!--", "<s" => "<\s", "<S" => "<\S",
						"`" => "\\`", "\${" => "\\\$\{"));?>
?controller=allegro&action=warehouseproducts&q='
        + encodeURIComponent(query)
        + '&offer_name=' + encodeURIComponent(offerName);

      fetch(url, { credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          renderWarehouseSuggestions(data && data.items ? data.items : []);
        })
        .catch(function () {
          warehouseSuggestions.innerHTML = '<div class="list-group-item text-danger">Blad pobierania produktow magazynowych.</div>';
          warehouseSuggestions.classList.remove('bulk-ops-hidden');
        });
    }

    checkboxes.forEach(function (item, index) {
      item.addEventListener('click', function (event) {
        if (item.disabled) {
          event.preventDefault();
          return;
        }
        if (event.shiftKey && lastChecked !== null) {
          var start = Math.min(lastChecked, index);
          var end = Math.max(lastChecked, index);
          var targetState = item.checked;
          for (var i = start; i <= end; i++) {
            if (checkboxes[i].disabled) {
              continue;
            }
            checkboxes[i].checked = targetState;
          }
        }
        lastChecked = index;
        updateCounter();
        if (operationSelect && operationSelect.value === 'set_sku') {
          searchWarehouseProducts();
        }
      });
    });

    if (checkAll) {
      checkAll.addEventListener('change', function () {
        setAll(!!checkAll.checked);
      });
    }

    selectionScopeInputs.forEach(function (input) {
      input.addEventListener('change', function () {
        refreshSelectionChoices();
        refreshBulkOperationUI();
      });
    });

    if (operationSelect) {
      operationSelect.addEventListener('change', refreshBulkOperationUI);
    }

    if (selectPage) {
      selectPage.addEventListener('click', function () {
        setAll(true);
      });
    }

    if (clearPage) {
      clearPage.addEventListener('click', function () {
        setAll(false);
      });
    }

    if (invertPage) {
      invertPage.addEventListener('click', function () {
        invertAll();
      });
    }

    bulkForm.addEventListener('submit', function (event) {
      var selectedScope = bulkForm.querySelector('input[name="selection_scope"]:checked');
      var scope = selectedScope ? selectedScope.value : 'selected';
      var selectedCount = checkboxes.filter(function (item) { return !item.disabled && item.checked; }).length;
      var operation = operationSelect ? operationSelect.value : '';
      var manualIdsField = bulkForm.querySelector('textarea[name="manual_offer_ids"]');
      var manualIdsValue = manualIdsField ? manualIdsField.value.trim() : '';
      var searchField = bulkForm.querySelector('input[name="search"]');
      var valueField = bulkForm.querySelector('input[name="value"]');
      var productIdField = bulkForm.querySelector('input[name="product_id"]');

      if (scope === 'selected' && selectedCount === 0) {
        event.preventDefault();
        window.alert('Zaznacz przynajmniej jedna oferte albo przelacz zakres na "Wszystkie z filtrowania".');
        return;
      }

      if ((operation === 'set_name' || operation === 'set_price') && (!valueField || !valueField.value.trim())) {
        event.preventDefault();
        window.alert('Uzupelnij wymagana wartosc dla wybranej operacji.');
        return;
      }

      if (operation === 'set_sku') {
        var manualSkuValue = bulkSkuInput ? bulkSkuInput.value.trim() : '';
        var selectedWarehouseProductId = warehouseProductIdInput ? warehouseProductIdInput.value.trim() : '';
        if (valueField) {
          valueField.value = manualSkuValue;
        }
        if (manualSkuValue === '' && selectedWarehouseProductId === '') {
          event.preventDefault();
          window.alert('Wpisz SKU recznie albo wybierz produkt z magazynu.');
          return;
        }
      }

      if (operation === 'replace_name' && (!searchField || !searchField.value.trim())) {
        event.preventDefault();
        window.alert('Uzupelnij fraze do zamiany.');
        return;
      }

      if (operation === 'link_product_id' && (!productIdField || !productIdField.value.trim())) {
        event.preventDefault();
        window.alert('Podaj Allegro product ID.');
        return;
      }

      if (operation === 'set_delivery') {
        var deliveryField = bulkForm.querySelector('select[name="delivery_value"]');
        if (!deliveryField || !deliveryField.value.trim()) {
          event.preventDefault();
          window.alert('Wybierz czas wysylki.');
          return;
        }
      }

      if (operation === 'set_invoice') {
        var invoiceField = bulkForm.querySelector('select[name="invoice_value"]');
        if (!invoiceField || !invoiceField.value.trim()) {
          event.preventDefault();
          window.alert('Wybierz opcje faktury.');
          return;
        }
      }

      if (operation === 'remove_from_system' && !window.confirm('Usunąć wybrane oferty tylko lokalnie z systemu? Przy kolejnym syncu mogą wrócić.')) {
        event.preventDefault();
        return;
      }

      if (operation === 'remove_from_system_forever' && !window.confirm('Usunąć oferty z systemu na zawsze? System będzie sprawdzał, czy oferta jest zakończona, a potem zablokuje jej ponowne pobranie.')) {
        event.preventDefault();
        return;
      }

      if (manualIdsValue !== '' && scope === 'selected') {
        event.preventDefault();
        window.alert('Uzywasz jednoczesnie recznych ID i trybu "Tylko zaznaczone". Zostaw jedno z tych zrodel wyboru.');
      }
    });

    if (bulkSkuInput) {
      bulkSkuInput.addEventListener('input', function () {
        if (valueInput) {
          valueInput.value = bulkSkuInput.value;
        }
        if (warehouseProductIdInput && bulkSkuInput.value.trim() !== '') {
          warehouseProductIdInput.value = '';
          if (warehouseSelected) {
            warehouseSelected.classList.add('bulk-ops-hidden');
          }
        }
      });
    }

    if (warehouseSearchInput) {
      warehouseSearchInput.addEventListener('input', function () {
        window.clearTimeout(warehouseSearchTimer);
        warehouseSearchTimer = window.setTimeout(searchWarehouseProducts, 220);
      });
    }

    updateCounter();
    refreshSelectionChoices();
    refreshBulkOperationUI();
  })();

  (function () {
    var rows = Array.prototype.slice.call(document.querySelectorAll('.js-offer-row'));
    if (!rows.length) {
      return;
    }

    rows.forEach(function (row) {
      row.style.cursor = 'pointer';
      row.addEventListener('click', function (event) {
        var interactive = event.target.closest('a, button, input, textarea, select, label');
        if (interactive) {
          return;
        }
        var checkbox = row.querySelector('.js-offer-select');
        if (checkbox && !checkbox.disabled) {
          checkbox.click();
        }
      });
    });
  })();
<?php echo '</script'; ?>
>
<?php }
}
