<main class="app-main"><div class="sc-page">
  <h1 class="h3 mb-1">Zespół</h1>
  <p class="text-secondary">Właściciel i administrator mają pełny dostęp. Pracownik może mieć edycję albo tylko podgląd centrum zamówień.</p>
  {if $flashSuccess}<div class="alert alert-success">{$flashSuccess|escape}</div>{/if}
  {if $flashError}<div class="alert alert-danger">{$flashError|escape}</div>{/if}
  <div class="card mb-4"><div class="card-body table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>Użytkownik</th><th>Rola</th><th>Dostęp</th><th>Zablokowany</th><th>Nowe hasło</th><th>Ostatnie logowanie</th><th></th></tr></thead>
      <tbody>
      {foreach $users as $u}
        {assign var=isSelf value=$u.id eq $currentUser.id}
        {assign var=protectedOwner value=$currentUser.role ne 'owner' and $u.role eq 'owner'}
        <tr>
          <td>{if not $protectedOwner}<form id="user-{$u.id}" method="post" action="{$baseUrl}?controller=account&action=saveuser"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="update"><input type="hidden" name="user_id" value="{$u.id}"></form>
            <input class="form-control form-control-sm" form="user-{$u.id}" name="name" maxlength="150" value="{$u.name|escape}" aria-label="Imię i nazwisko {$u.name|escape}" required>{else}<strong>{$u.name|escape}</strong><br>{/if}<small class="text-secondary">{$u.email|escape}</small></td>
          <td>{if $protectedOwner}{$roles[$u.role]|escape}{else}{if $isSelf}<input type="hidden" form="user-{$u.id}" name="role" value="{$u.role|escape}">{/if}<select class="form-select form-select-sm" form="user-{$u.id}" name="role" aria-label="Rola {$u.name|escape}" {if $isSelf}disabled{/if}>{foreach $roles as $code=>$label}{if $currentUser.role eq 'owner' or $code ne 'owner'}<option value="{$code}" {if $u.role eq $code}selected{/if}>{$label}</option>{/if}{/foreach}</select>{/if}</td>
          <td>{if $protectedOwner}Edycja{else}<select class="form-select form-select-sm" form="user-{$u.id}" name="access" aria-label="Dostęp {$u.name|escape}"><option value="edit" {if $u.access eq 'edit'}selected{/if}>Edycja</option><option value="read" {if $u.access eq 'read'}selected{/if}>Tylko podgląd</option></select>{/if}</td>
          <td>{if $protectedOwner}Nie{else}<input class="form-check-input" type="checkbox" form="user-{$u.id}" name="is_blocked" value="1" aria-label="Zablokuj {$u.name|escape}" {if $u.is_blocked}checked{/if} {if $isSelf}disabled{/if}>{/if}</td>
          <td>{if $protectedOwner}—{else}<input class="form-control form-control-sm" type="password" form="user-{$u.id}" name="new_password" minlength="10" autocomplete="new-password" aria-label="Nowe hasło {$u.name|escape}" placeholder="bez zmian">{/if}</td>
          <td class="small text-secondary">{$u.last_login_at|default:'—'|escape}</td>
          <td class="text-nowrap">{if not $protectedOwner}<button class="btn btn-sm btn-primary" form="user-{$u.id}" type="submit">Zapisz</button>
            {if not $isSelf}<form class="d-inline" method="post" action="{$baseUrl}?controller=account&action=saveuser" onsubmit="return confirm('Usunąć użytkownika?')"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="delete"><input type="hidden" name="user_id" value="{$u.id}"><button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Usuń {$u.name|escape}"><i class="bi bi-trash"></i></button></form>{/if}{/if}</td>
        </tr>
      {/foreach}
      </tbody>
    </table>
  </div></div>
  <div class="card"><div class="card-body">
    <h2 class="h5">Dodaj użytkownika</h2>
    <form method="post" action="{$baseUrl}?controller=account&action=saveuser">
      <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="create">
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label" for="new-name">Imię i nazwisko</label><input class="form-control" id="new-name" name="name" maxlength="150" required></div>
        <div class="col-md-4"><label class="form-label" for="new-email">E-mail</label><input class="form-control" id="new-email" type="email" name="email" maxlength="190" required></div>
        <div class="col-md-4"><label class="form-label" for="new-password">Hasło startowe</label><input class="form-control" id="new-password" type="password" name="password" minlength="10" autocomplete="new-password" required></div>
        <div class="col-md-4"><label class="form-label" for="new-role">Rola</label><select class="form-select" id="new-role" name="role"><option value="member">Pracownik</option><option value="admin">Administrator</option></select></div>
        <div class="col-md-4"><label class="form-label" for="new-access">Dostęp pracownika</label><select class="form-select" id="new-access" name="access"><option value="edit">Edycja</option><option value="read">Tylko podgląd</option></select></div>
      </div>
      <button class="btn btn-primary mt-3" type="submit">Dodaj</button>
    </form>
  </div></div>
</div></main>
