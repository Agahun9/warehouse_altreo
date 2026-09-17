<main class="app-main"><div class="sc-page">
  <h1 class="h3 mb-3">Firma</h1>
  {if $flashSuccess}<div class="alert alert-success">{$flashSuccess|escape}</div>{/if}
  {if $flashError}<div class="alert alert-danger">{$flashError|escape}</div>{/if}
  <div class="card"><div class="card-body">
    <form method="post" action="{$baseUrl}?controller=account&action=company">
      <input type="hidden" name="csrf" value="{$csrf|escape}">
      <div class="row g-3">
        <div class="col-md-8"><label class="form-label" for="name">Nazwa firmy</label><input class="form-control" id="name" name="name" maxlength="200" value="{$tenant.name|escape}" required {if !$canManage}disabled{/if}></div>
        <div class="col-md-4"><label class="form-label" for="nip">NIP</label><input class="form-control" id="nip" name="nip" maxlength="30" value="{$tenant.nip|default:''|escape}" {if !$canManage}disabled{/if}></div>
      </div>
      <p class="text-secondary small mt-3 mb-0">Identyfikator firmy: {$tenant.id} · utworzona {$tenant.created_at|escape} UTC. Dane sprzedawcy na dokumentach ustawisz w Centrum zamówień → Ustawienia ogólne / Dokumenty.</p>
      {if $canManage}<button class="btn btn-primary mt-3" type="submit">Zapisz</button>{/if}
    </form>
  </div></div>
</div></main>
