<main class="app-main"><div class="sc-page" style="max-width:560px">
  <h1 class="h3 mb-3">Moje hasło</h1>
  {if $flashSuccess}<div class="alert alert-success">{$flashSuccess|escape}</div>{/if}
  {if $flashError}<div class="alert alert-danger">{$flashError|escape}</div>{/if}
  <div class="card"><div class="card-body">
    <form method="post" action="{$baseUrl}?controller=account&action=password">
      <input type="hidden" name="csrf" value="{$csrf|escape}">
      <div class="mb-3"><label class="form-label" for="current_password">Obecne hasło</label><input class="form-control" id="current_password" type="password" name="current_password" autocomplete="current-password" required></div>
      <div class="mb-3"><label class="form-label" for="password">Nowe hasło <small class="text-secondary">(min. 10 znaków)</small></label><input class="form-control" id="password" type="password" name="password" minlength="10" autocomplete="new-password" required></div>
      <div class="mb-3"><label class="form-label" for="password_confirm">Powtórz nowe hasło</label><input class="form-control" id="password_confirm" type="password" name="password_confirm" minlength="10" autocomplete="new-password" required></div>
      <button class="btn btn-primary" type="submit">Zmień hasło</button>
    </form>
  </div></div>
</div></main>
