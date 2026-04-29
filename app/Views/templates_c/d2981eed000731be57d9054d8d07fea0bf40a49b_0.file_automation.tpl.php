<?php
/* Smarty version 5.8.0, created on 2026-04-28 21:49:21
  from 'file:admin/automation.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.8.0',
  'unifunc' => 'content_69f10f41b1b361_56715887',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'd2981eed000731be57d9054d8d07fea0bf40a49b' => 
    array (
      0 => 'admin/automation.tpl',
      1 => 1777405713,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69f10f41b1b361_56715887 (\Smarty\Template $_smarty_tpl) {
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

      <div class="row g-4 mb-4">
        <div class="col-lg-4">
          <div class="card h-100">
            <div class="card-body">
              <div class="text-secondary small">Kolejka oczekujaca</div>
              <div class="display-6 fw-semibold"><?php echo $_smarty_tpl->getValue('queueStats')['pending']+$_smarty_tpl->getValue('queueStats')['retry'];?>
</div>
              <div class="small text-secondary">w toku: <?php echo $_smarty_tpl->getValue('queueStats')['processing'];?>
 | bledy: <?php echo $_smarty_tpl->getValue('queueStats')['error'];?>
</div>
            </div>
          </div>
        </div>
        <div class="col-lg-8">
          <div class="card h-100">
            <div class="card-body">
              <div class="fw-semibold mb-2">Jak to ustawic</div>
              <div class="small text-secondary mb-2">Najlepiej dac dwa crony:</div>
              <div class="small text-secondary">1. Worker kolejki co 1 minute.</div>
              <div class="small text-secondary">2. Pelne maintenance z syncem co 5-15 minut.</div>
              <div class="small text-secondary mt-2">Jesli nie chcesz crona od razu, nizej masz tez auto-worker w przegladarce, ktory odpala sie co minute, gdy ten ekran jest otwarty.</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0">Konta Allegro</h3>
          <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=index" class="btn btn-sm btn-outline-secondary">Wroc do ofert</a>
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
              <div class="alert alert-light border small text-secondary">
                Refresh tokena dziala poprawnie nawet wtedy, gdy nowy <code>access_token</code> nadal wygasa jeszcze tego samego dnia.
                Allegro zwykle nadaje mu po prostu kolejny ograniczony czas waznosci, a nie wielodniowy termin.
              </div>
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
                            <div class="small text-secondary mt-1">odswiezono: <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('account')['token_updated_at'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
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
                          <div class="d-grid gap-2">
                            <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=connect&id=<?php echo $_smarty_tpl->getValue('account')['id'];?>
" class="btn btn-sm btn-primary">Autoryzuj</a>
                            <a href="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('account')['trigger_url'], ENT_QUOTES, 'UTF-8', true);?>
" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noreferrer">Sync</a>
                            <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=refreshtoken&account=<?php echo rawurlencode((string)$_smarty_tpl->getValue('account')['slug']);?>
" class="btn btn-sm btn-outline-info">Refresh token</a>
                            <form method="post" action="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=allegro&action=saveaccount" class="d-grid">
                              <input type="hidden" name="account_id" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('account')['id'], ENT_QUOTES, 'UTF-8', true);?>
">
                              <input type="hidden" name="name" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('account')['name'], ENT_QUOTES, 'UTF-8', true);?>
">
                              <input type="hidden" name="client_id" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('account')['client_id'], ENT_QUOTES, 'UTF-8', true);?>
">
                              <input type="hidden" name="client_secret" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('account')['client_secret'], ENT_QUOTES, 'UTF-8', true);?>
">
                              <input type="hidden" name="redirect_uri" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('account')['redirect_uri'], ENT_QUOTES, 'UTF-8', true);?>
">
                              <input type="hidden" name="is_active" value="<?php if ($_smarty_tpl->getValue('account')['is_active']) {?>0<?php } else { ?>1<?php }?>">
                              <button type="submit" class="btn btn-sm <?php if ($_smarty_tpl->getValue('account')['is_active']) {?>btn-outline-warning<?php } else { ?>btn-outline-success<?php }?>">
                                <?php if ($_smarty_tpl->getValue('account')['is_active']) {?>Wylacz konto<?php } else { ?>Wlacz konto<?php }?>
                              </button>
                            </form>
                          </div>
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

      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0">Empik API</h3>
          <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=empik&action=index" class="btn btn-sm btn-outline-secondary">Wroc do Empik</a>
        </div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-lg-4">
              <form method="post" action="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=admin&action=saveempik" class="row g-3">
                <input type="hidden" name="account_id" value="">
                <div class="col-12">
                  <label class="form-label">Nazwa konta</label>
                  <input type="text" name="name" class="form-control" placeholder="np. Empik PL" required>
                </div>
                <div class="col-12">
                  <label class="form-label">Instance URL</label>
                  <input type="url" name="api_url" class="form-control" placeholder="https://twoja-instancja.mirakl.net" required>
                </div>
                <div class="col-12">
                  <label class="form-label">API Key</label>
                  <input type="text" name="api_key" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">shop_id</label>
                  <input type="number" min="1" step="1" name="shop_id" class="form-control" placeholder="opcjonalnie">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Locale</label>
                  <input type="text" name="locale" class="form-control" value="pl_PL">
                </div>
                <div class="col-12">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="empik_active_default" checked>
                    <label class="form-check-label" for="empik_active_default">Konto aktywne</label>
                  </div>
                </div>
                <div class="col-12">
                  <button type="submit" class="btn btn-primary">Zapisz Empik API</button>
                </div>
              </form>
            </div>
            <div class="col-lg-8">
              <div class="alert alert-light border small text-secondary">
                Empik Marketplace dziala na Mirakl Seller API. Wprowadz adres swojej instancji Mirakl oraz API key od Empik.
                Jesli sprzedawca ma kilka sklepow w tej samej instancji, mozesz dopisac takze <code>shop_id</code>.
              </div>
              <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle">
                  <thead class="table-light">
                    <tr>
                      <th>Konto</th>
                      <th>API</th>
                      <th>Status</th>
                      <th>Sync</th>
                      <th>Akcje</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('empikAccounts'), 'account');
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('account')->value) {
$foreach1DoElse = false;
?>
                      <tr>
                        <td>
                          <div class="fw-semibold"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('account')['name'], ENT_QUOTES, 'UTF-8', true);?>
</div>
                          <div class="small text-secondary">slug: <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('account')['slug'], ENT_QUOTES, 'UTF-8', true);?>
</div>
                        </td>
                        <td>
                          <div class="small"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('account')['api_url'], ENT_QUOTES, 'UTF-8', true);?>
</div>
                          <div class="small text-secondary">shop_id: <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('account')['shop_id'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
 | locale: <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('account')['locale'] ?? null)===null||$tmp==='' ? 'pl_PL' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
                        </td>
                        <td>
                          <?php if ($_smarty_tpl->getValue('account')['is_active']) {?>
                            <span class="badge text-bg-success">Aktywne</span>
                          <?php } else { ?>
                            <span class="badge text-bg-secondary">Nieaktywne</span>
                          <?php }?>
                          <?php if ($_smarty_tpl->getValue('account')['last_error_message']) {?>
                            <div class="small text-danger mt-1"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('account')['last_error_message'], ENT_QUOTES, 'UTF-8', true);?>
</div>
                          <?php }?>
                        </td>
                        <td>
                          <div class="small">ostatni sync: <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('account')['last_sync_at'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
                          <div class="small text-secondary">ostatni blad: <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('account')['last_error_at'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
                        </td>
                        <td class="text-nowrap">
                          <div class="d-grid gap-2">
                            <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=empik&action=sync&account=<?php echo rawurlencode((string)$_smarty_tpl->getValue('account')['slug']);?>
" class="btn btn-sm btn-outline-primary">Synchronizuj</a>
                            <a href="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=empik&action=index&account_id=<?php echo rawurlencode((string)$_smarty_tpl->getValue('account')['id']);?>
" class="btn btn-sm btn-outline-secondary">Oferty</a>
                            <form method="post" action="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=admin&action=saveempik" class="d-grid">
                              <input type="hidden" name="account_id" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('account')['id'], ENT_QUOTES, 'UTF-8', true);?>
">
                              <input type="hidden" name="name" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('account')['name'], ENT_QUOTES, 'UTF-8', true);?>
">
                              <input type="hidden" name="api_url" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('account')['api_url'], ENT_QUOTES, 'UTF-8', true);?>
">
                              <input type="hidden" name="api_key" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('account')['api_key'], ENT_QUOTES, 'UTF-8', true);?>
">
                              <input type="hidden" name="shop_id" value="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('account')['shop_id'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
">
                              <input type="hidden" name="locale" value="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('account')['locale'] ?? null)===null||$tmp==='' ? 'pl_PL' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
">
                              <input type="hidden" name="is_active" value="<?php if ($_smarty_tpl->getValue('account')['is_active']) {?>0<?php } else { ?>1<?php }?>">
                              <button type="submit" class="btn btn-sm <?php if ($_smarty_tpl->getValue('account')['is_active']) {?>btn-outline-warning<?php } else { ?>btn-outline-success<?php }?>">
                                <?php if ($_smarty_tpl->getValue('account')['is_active']) {?>Wylacz konto<?php } else { ?>Wlacz konto<?php }?>
                              </button>
                            </form>
                          </div>
                        </td>
                      </tr>
                    <?php
}
if ($foreach1DoElse) {
?>
                      <tr><td colspan="5" class="text-center text-secondary py-4">Brak skonfigurowanych kont Empik.</td></tr>
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

      <div class="card mb-4 border-warning-subtle">
        <div class="card-header">
          <h3 class="card-title mb-0">Sprzatanie bazy</h3>
        </div>
        <div class="card-body">
          <form method="post" action="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=admin&action=cleanup" class="row g-3 align-items-end">
            <div class="col-lg-4">
              <label class="form-label" for="cleanup-queue-done-days">Usun zakonczona kolejke starsza niz dni</label>
              <input type="number" min="0" step="1" class="form-control" id="cleanup-queue-done-days" name="queue_done_days" value="14">
            </div>
            <div class="col-lg-4">
              <label class="form-label" for="cleanup-queue-error-days">Usun bledy i retry starsze niz dni</label>
              <input type="number" min="0" step="1" class="form-control" id="cleanup-queue-error-days" name="queue_error_days" value="30">
            </div>
            <div class="col-lg-4">
              <label class="form-label" for="cleanup-deleted-products-days">Usun produkty skasowane starsze niz dni</label>
              <input type="number" min="0" step="1" class="form-control" id="cleanup-deleted-products-days" name="deleted_products_days" value="30">
            </div>
            <div class="col-12">
              <div class="small text-secondary">
                Ten przycisk czysci stare wpisy z <code>allegro_offer_change_queue</code>, odpina oferty Allegro od produktow, ktore sa juz oznaczone jako usuniete, i trwale usuwa stare produkty po soft-delecie razem z ich logami zmian.
              </div>
            </div>
            <div class="col-12">
              <div class="small text-secondary">
                Mozesz wpisac <code>0</code>, aby sprzatac od teraz, bez czekania 1 dnia.
              </div>
            </div>
            <div class="col-12 d-flex justify-content-between gap-2 align-items-center flex-wrap">
              <div class="small text-secondary">
                Uzywaj ostroznie: ostatni krok wykonuje trwale usuniecie produktow, ktore byly juz oznaczone jako skasowane.
              </div>
              <button type="submit" class="btn btn-outline-warning">Uruchom sprzatanie bazy</button>
            </div>
          </form>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">
          <h3 class="card-title mb-0">Sellasist API</h3>
        </div>
        <div class="card-body">
          <form method="post" action="<?php echo $_smarty_tpl->getValue('baseUrl');?>
?controller=admin&action=savesellasist" class="row g-3">
            <div class="col-lg-5">
              <label class="form-label" for="sellasist-base-url">Adres Sellasist</label>
              <input type="url" class="form-control" id="sellasist-base-url" name="sellasist_base_url" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('sellasistBaseUrl'), ENT_QUOTES, 'UTF-8', true);?>
" placeholder="https://altreo.sellasist.pl">
            </div>
            <div class="col-lg-7">
              <label class="form-label" for="sellasist-api-key">API Key</label>
              <input type="text" class="form-control" id="sellasist-api-key" name="sellasist_api_key" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('sellasistApiKey'), ENT_QUOTES, 'UTF-8', true);?>
" placeholder="Wklej klucz API Sellasist">
            </div>
            <div class="col-12">
              <div class="small text-secondary">
                Dane sa uzywane przez modul Sellasist, zakladke Zbieranie oraz generowanie naklejek. Domyslnie pobierane sa zamowienia ze statusu <code>23</code>, a po wygenerowaniu naklejek system zmienia status na <code>3</code>.
              </div>
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-primary">Zapisz ustawienia Sellasist</button>
            </div>
          </form>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">
          <h3 class="card-title mb-0">Globalne linki cron</h3>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Worker kolejki co 1 minute</label>
            <input type="text" class="form-control" readonly value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('automation')['queue_worker'], ENT_QUOTES, 'UTF-8', true);?>
" id="globalQueueWorkerUrl">
          </div>
          <div class="mb-3">
            <label class="form-label">Pelne maintenance co 5-15 minut</label>
            <input type="text" class="form-control" readonly value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('automation')['full_maintenance'], ENT_QUOTES, 'UTF-8', true);?>
" id="globalMaintenanceUrl">
          </div>
          <div class="mb-3">
            <label class="form-label">Cron konczenia ofert Allegro</label>
            <input type="text" class="form-control" readonly value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('automation')['auto_end_offers'], ENT_QUOTES, 'UTF-8', true);?>
">
            <div class="form-text">Dodaje do kolejki tylko oferty kwalifikujace sie do zakonczenia i nie dubluje aktywnych zadan end_offer.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Cron konczenia + wysylka maila</label>
            <input type="text" class="form-control" readonly value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('automation')['auto_end_offers'], ENT_QUOTES, 'UTF-8', true);?>
&amp;mail_to=twoj%40adres.pl">
          </div>
          <div class="mb-0">
            <label class="form-label">Same refresh tokenow</label>
            <input type="text" class="form-control" readonly value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('automation')['refresh_tokens'], ENT_QUOTES, 'UTF-8', true);?>
">
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0">Auto-worker w panelu</h3>
          <span class="badge text-bg-info">Opcjonalny</span>
        </div>
        <div class="card-body">
          <div class="row g-3 align-items-end">
            <div class="col-lg-4">
              <label class="form-label">Tryb</label>
              <select id="browserWorkerMode" class="form-select">
                <option value="queue">Tylko kolejka</option>
                <option value="maintenance">Maintenance + sync</option>
              </select>
            </div>
            <div class="col-lg-3">
              <label class="form-label">Interwal sekund</label>
              <input type="number" min="30" step="30" id="browserWorkerInterval" class="form-control" value="60">
            </div>
            <div class="col-lg-5 d-flex gap-2">
              <button type="button" class="btn btn-primary" id="browserWorkerStart">Start</button>
              <button type="button" class="btn btn-outline-secondary" id="browserWorkerStop">Stop</button>
            </div>
            <div class="col-12">
              <div class="small text-secondary" id="browserWorkerStatus">Auto-worker zatrzymany.</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title mb-0">Linki per konto</h3>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Konto</th>
                <th>Status</th>
                <th>Sync</th>
                <th>Kolejka</th>
                <th>Maintenance</th>
              </tr>
            </thead>
            <tbody>
              <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('automation')['accounts'], 'item');
$foreach2DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('item')->value) {
$foreach2DoElse = false;
?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['name'], ENT_QUOTES, 'UTF-8', true);?>
</div>
                    <div class="small text-secondary"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['slug'], ENT_QUOTES, 'UTF-8', true);?>
</div>
                  </td>
                  <td>
                    <?php if ($_smarty_tpl->getValue('item')['is_active']) {?>
                      <span class="badge text-bg-success">Aktywne</span>
                    <?php } else { ?>
                      <span class="badge text-bg-secondary">Nieaktywne</span>
                    <?php }?>
                  </td>
                  <td><input type="text" class="form-control form-control-sm" readonly value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['sync'], ENT_QUOTES, 'UTF-8', true);?>
"></td>
                  <td><input type="text" class="form-control form-control-sm" readonly value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['queue_only'], ENT_QUOTES, 'UTF-8', true);?>
"></td>
                  <td><input type="text" class="form-control form-control-sm" readonly value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['maintenance'], ENT_QUOTES, 'UTF-8', true);?>
"></td>
                </tr>
              <?php
}
if ($foreach2DoElse) {
?>
                <tr><td colspan="5" class="text-center text-secondary py-4">Brak kont Allegro do automatyzacji.</td></tr>
              <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</main>
<?php echo '<script'; ?>
>
  (function () {
    var queueUrlInput = document.getElementById('globalQueueWorkerUrl');
    var maintenanceUrlInput = document.getElementById('globalMaintenanceUrl');
    var startBtn = document.getElementById('browserWorkerStart');
    var stopBtn = document.getElementById('browserWorkerStop');
    var modeInput = document.getElementById('browserWorkerMode');
    var intervalInput = document.getElementById('browserWorkerInterval');
    var statusNode = document.getElementById('browserWorkerStatus');
    var timer = null;

    function setStatus(text) {
      if (statusNode) {
        statusNode.textContent = text;
      }
    }

    function currentUrl() {
      if (modeInput && modeInput.value === 'maintenance' && maintenanceUrlInput) {
        return maintenanceUrlInput.value || '';
      }
      return queueUrlInput ? (queueUrlInput.value || '') : '';
    }

    function runOnce() {
      var url = currentUrl();
      if (!url) {
        setStatus('Brak URL do uruchomienia auto-workera.');
        return;
      }

      setStatus('Auto-worker uruchamia: ' + url);
      fetch(url, { credentials: 'same-origin' })
        .then(function (response) { return response.text().then(function (body) { return { ok: response.ok, body: body }; }); })
        .then(function (result) {
          var stamp = new Date().toLocaleTimeString();
          setStatus('Ostatnie odpalenie ' + stamp + ': ' + (result.ok ? 'OK' : 'blad') + ' | ' + result.body.substring(0, 220));
        })
        .catch(function (error) {
          setStatus('Blad auto-workera: ' + error);
        });
    }

    function startWorker() {
      stopWorker();
      var every = Math.max(30, parseInt(intervalInput && intervalInput.value ? intervalInput.value : '60', 10) || 60) * 1000;
      runOnce();
      timer = window.setInterval(runOnce, every);
      setStatus('Auto-worker wlaczony. Interwal: ' + (every / 1000) + ' s.');
    }

    function stopWorker() {
      if (timer) {
        window.clearInterval(timer);
        timer = null;
      }
      setStatus('Auto-worker zatrzymany.');
    }

    if (startBtn) {
      startBtn.addEventListener('click', startWorker);
    }
    if (stopBtn) {
      stopBtn.addEventListener('click', stopWorker);
    }
  })();
<?php echo '</script'; ?>
>
<?php }
}
