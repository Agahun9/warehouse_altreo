<div class="sc-auth-card wide">
    <div class="sc-auth-brand"><span>S</span> {$appName|escape}</div>
    <h1 class="h4 mb-1">Załóż konto firmy</h1>
    <p class="text-secondary mb-3">Każda firma ma własne, odizolowane dane: zamówienia, statusy, numerację dokumentów, KSeF i ustawienia.</p>
    {if $flashSuccess}<div class="alert alert-success">{$flashSuccess|escape}</div>{/if}
    {if $flashError}<div class="alert alert-danger">{$flashError|escape}</div>{/if}
    <form method="post" action="{$baseUrl}?controller=auth&action=register">
      <input type="hidden" name="csrf" value="{$csrf|escape}">
      <div class="row g-3">
        <div class="col-md-8"><label class="form-label" for="company">Nazwa firmy</label><input class="form-control" id="company" name="company" maxlength="200" value="{$form.company|escape}" required></div>
        <div class="col-md-4"><label class="form-label" for="nip">NIP <small class="text-secondary">(opcjonalnie)</small></label><input class="form-control" id="nip" name="nip" maxlength="30" value="{$form.nip|escape}"></div>
        <div class="col-md-6"><label class="form-label" for="name">Imię i nazwisko</label><input class="form-control" id="name" name="name" maxlength="150" value="{$form.name|escape}" autocomplete="name" required></div>
        <div class="col-md-6"><label class="form-label" for="email">E-mail (login)</label><input class="form-control" id="email" type="email" name="email" maxlength="190" value="{$form.email|escape}" autocomplete="username" required></div>
        <div class="col-md-6"><label class="form-label" for="password">Hasło <small class="text-secondary">(min. 10 znaków)</small></label><input class="form-control" id="password" type="password" name="password" minlength="10" autocomplete="new-password" required></div>
        <div class="col-md-6"><label class="form-label" for="password_confirm">Powtórz hasło</label><input class="form-control" id="password_confirm" type="password" name="password_confirm" minlength="10" autocomplete="new-password" required></div>
        <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="terms" name="terms" value="1" required><label class="form-check-label" for="terms">Akceptuję regulamin i politykę prywatności.</label></div></div>
      </div>
      <button class="btn btn-primary w-100 mt-3" type="submit">Utwórz konto</button>
    </form>
    <div class="mt-3 small"><a href="{$baseUrl}?controller=auth&action=login">Masz już konto? Zaloguj się</a></div>
</div>
