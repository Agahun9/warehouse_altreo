<div class="sc-auth-card">
    <div class="sc-auth-brand"><span>S</span> {$appName|escape}</div>
    <h1 class="h4 mb-1">Ustaw nowe hasło</h1>
    {if $flashSuccess}<div class="alert alert-success">{$flashSuccess|escape}</div>{/if}
    {if $flashError}<div class="alert alert-danger">{$flashError|escape}</div>{/if}
    {if $tokenValid}
    <form method="post" action="{$baseUrl}?controller=auth&action=reset">
      <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="token" value="{$token|escape}">
      <div class="mb-3"><label class="form-label" for="password">Nowe hasło <small class="text-secondary">(min. 10 znaków)</small></label><input class="form-control" id="password" type="password" name="password" minlength="10" autocomplete="new-password" required></div>
      <div class="mb-3"><label class="form-label" for="password_confirm">Powtórz hasło</label><input class="form-control" id="password_confirm" type="password" name="password_confirm" minlength="10" autocomplete="new-password" required></div>
      <button class="btn btn-primary w-100" type="submit">Zapisz hasło</button>
    </form>
    {else}
    <div class="alert alert-warning">Link resetu hasła jest nieprawidłowy lub wygasł.</div>
    <a class="btn btn-outline-primary w-100" href="{$baseUrl}?controller=auth&action=forgot">Wyślij nowy link</a>
    {/if}
</div>
