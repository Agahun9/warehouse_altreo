<?php
/* Smarty version 5.8.0, created on 2026-04-19 21:27:22
  from 'file:index.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.8.0',
  'unifunc' => 'content_69e52c9aab9136_53408955',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '02533e9cc958a2a6e70c86b1e60e87fd62c29874' => 
    array (
      0 => 'index.tpl',
      1 => 1776626810,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69e52c9aab9136_53408955 (\Smarty\Template $_smarty_tpl) {
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
        .dashboard-allegro-card {
          border: 1px solid rgba(15, 23, 42, 0.08);
          border-radius: 1rem;
          background:
            radial-gradient(circle at top right, rgba(13, 110, 253, 0.12), transparent 34%),
            linear-gradient(180deg, rgba(255,255,255,0.98), rgba(248,249,250,0.98));
        }

        .dashboard-allegro-progress {
          display: flex;
          overflow: hidden;
          height: 0.7rem;
          border-radius: 999px;
          background: #e9ecef;
          box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.08);
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
          background: #f3f5f7;
          color: #334155;
          font-size: 0.84rem;
          font-weight: 600;
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
        <div class="col-xl-8">
          <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h3 class="card-title mb-0">Ostatnio aktualizowane produkty</h3>
              <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=products&action=index" class="btn btn-sm btn-outline-primary">Przejdz do produktow</a>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-hover table-striped table-bordered mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>ID</th>
                      <th>SKU</th>
                      <th>Nazwa</th>
                      <th>Kategoria</th>
                      <th>Ilosc</th>
                      <th>Zmieniono</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($_smarty_tpl->getValue('recentProducts')) {?>
                      <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('recentProducts'), 'product');
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('product')->value) {
$foreach1DoElse = false;
?>
                        <tr>
                          <td><?php echo $_smarty_tpl->getValue('product')['id'];?>
</td>
                          <td><span class="badge text-bg-secondary"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('product')['sku'], ENT_QUOTES, 'UTF-8', true);?>
</span></td>
                          <td class="fw-semibold"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('product')['product_name'], ENT_QUOTES, 'UTF-8', true);?>
</td>
                          <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('product')['category_name'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
                          <td><?php echo $_smarty_tpl->getValue('product')['quantity'];?>
</td>
                          <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('product')['updated_at'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
                        </tr>
                      <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                    <?php } else { ?>
                      <tr>
                        <td colspan="6" class="text-center py-3">Brak danych o produktach.</td>
                      </tr>
                    <?php }?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-4">
          <div class="card dashboard-allegro-card mb-4">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                <div>
                  <div class="small text-secondary">Kolejka Allegro</div>
                  <div class="h5 mb-1">Zostało <?php echo $_smarty_tpl->getValue('allegroQueueRemaining');?>
</div>
                  <div class="small text-secondary">Zrobione <?php echo $_smarty_tpl->getValue('allegroQueueStats')['done'];?>
 z <?php echo $_smarty_tpl->getValue('allegroQueueTotal');?>
</div>
                </div>
                <span class="dashboard-allegro-chip"><?php echo sprintf('%.0f',$_smarty_tpl->getValue('allegroQueueDonePercent'));?>
%</span>
              </div>

              <div class="dashboard-allegro-progress mb-3" aria-label="Postęp kolejki Allegro">
                <?php if ($_smarty_tpl->getValue('allegroQueueDonePercent') > 0) {?><div class="dashboard-allegro-progress-done" style="width: <?php echo sprintf('%.2f',$_smarty_tpl->getValue('allegroQueueDonePercent'));?>
%;"></div><?php }?>
                <?php if ($_smarty_tpl->getValue('allegroQueueRemainingPercent') > 0) {?><div class="dashboard-allegro-progress-remaining" style="width: <?php echo sprintf('%.2f',$_smarty_tpl->getValue('allegroQueueRemainingPercent'));?>
%;"></div><?php }?>
                <?php if ($_smarty_tpl->getValue('allegroQueueErrorPercent') > 0) {?><div class="dashboard-allegro-progress-error" style="width: <?php echo sprintf('%.2f',$_smarty_tpl->getValue('allegroQueueErrorPercent'));?>
%;"></div><?php }?>
              </div>

              <div class="row g-2 small mb-3">
                <div class="col-6">
                  <div class="text-secondary">Oczekuje + ponów</div>
                  <div class="fw-semibold"><?php echo $_smarty_tpl->getValue('allegroQueueStats')['pending']+$_smarty_tpl->getValue('allegroQueueStats')['retry'];?>
</div>
                </div>
                <div class="col-6">
                  <div class="text-secondary">W toku</div>
                  <div class="fw-semibold"><?php echo $_smarty_tpl->getValue('allegroQueueStats')['processing'];?>
</div>
                </div>
                <div class="col-6">
                  <div class="text-secondary">Błędy</div>
                  <div class="fw-semibold text-danger"><?php echo $_smarty_tpl->getValue('allegroQueueStats')['error'];?>
</div>
                </div>
                <div class="col-6">
                  <div class="text-secondary">Gotowe</div>
                  <div class="fw-semibold text-success"><?php echo $_smarty_tpl->getValue('allegroQueueStats')['done'];?>
</div>
                </div>
              </div>

              <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=index" class="btn btn-sm btn-outline-primary">Otwórz Allegro</a>
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
$foreach2DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('activity')->value) {
$foreach2DoElse = false;
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
      </div>

      <div class="row">
        <div class="col-12">
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
$foreach3DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('change')->value) {
$foreach3DoElse = false;
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
                        <th>Email</th>
                        <th>Rola</th>
                        <th>Status</th>
                        <th>Blokada</th>
                        <th>Utworzono</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if ($_smarty_tpl->getValue('recentUsers')) {?>
                        <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('recentUsers'), 'user');
$foreach4DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('user')->value) {
$foreach4DoElse = false;
?>
                          <tr>
                            <td><?php echo $_smarty_tpl->getValue('user')['id'];?>
</td>
                            <td><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('user')['email'], ENT_QUOTES, 'UTF-8', true);?>
</td>
                            <td><span class="badge text-bg-<?php if ($_smarty_tpl->getValue('user')['role'] == 'admin') {?>dark<?php } else { ?>secondary<?php }?>"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('user')['role'], ENT_QUOTES, 'UTF-8', true);?>
</span></td>
                            <td><?php if ($_smarty_tpl->getValue('user')['is_active']) {?><span class="badge text-bg-success">aktywne</span><?php } else { ?><span class="badge text-bg-warning">nieaktywne</span><?php }?></td>
                            <td><?php if ($_smarty_tpl->getValue('user')['is_blocked']) {?><span class="badge text-bg-danger">zablokowane</span><?php } else { ?><span class="badge text-bg-success">odblokowane</span><?php }?></td>
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
