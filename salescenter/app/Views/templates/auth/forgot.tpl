<div class="sc-auth-card">
    <div class="sc-auth-brand"><span>S</span> {$appName|escape}</div>
    <h1 class="h4 mb-1">Reset hasła</h1>
    <p class="text-secondary mb-3">Wyślemy link do ustawienia nowego hasła.</p>
    {if $flashSuccess}<div class="alert alert-success">{$flashSuccess|escape}</div>{/if}
    {if $flashError}<div class="alert alert-danger">{$flashError|escape}</div>{/if}
    <form method="post" action="{$baseUrl}?controller=auth&action=forgot">
      <input type="hidden" name="csrf" value="{$csrf|escape}">
      <div class="mb-3"><label class="form-label" for="email">E-mail</label><input class="form-control" id="email" type="email" name="email" autocomplete="username" required autofocus></div>
      <button class="btn btn-primary w-100" type="submit">Wyślij link</button>
    </form>
    <div class="mt-3 small"><a href="{$baseUrl}?controller=auth&action=login">Wróć do logowania</a></div>
</div>
