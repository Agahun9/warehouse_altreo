<div class="om-section-heading"><div><h2>Ustawienia ogólne</h2><p>Dane firmy, zespół, Twoje hasło oraz konta KSeF.</p></div></div>
<div class="om-two-col sc-general-grid">
  <section id="sc-company" class="om-panel om-pad">
    <div class="om-docs-panel-title"><div><span class="om-eyebrow">KONTO</span><h3>Firma</h3><p>Identyfikator firmy: {$tenant.id|default:''} · utworzona {$tenant.created_at|default:''|escape} UTC. Dane sprzedawcy na dokumentach ustawisz w zakładce Dokumenty.</p></div></div>
    <form class="om-form" method="post" action="index.php?controller=account&action=company">
      <input type="hidden" name="csrf" value="{$csrf|escape}">
      <label>Nazwa firmy<input name="name" maxlength="200" value="{$tenant.name|default:''|escape}" required {if !$canManageTenant}disabled{/if}></label>
      <label>NIP<input name="nip" maxlength="30" value="{$tenant.nip|default:''|escape}" {if !$canManageTenant}disabled{/if}></label>
      {if $canManageTenant}<button class="om-btn om-primary"><i class="bi bi-check2"></i> Zapisz</button>{/if}
    </form>
  </section>
  <section id="sc-password" class="om-panel om-pad">
    <div class="om-docs-panel-title"><div><span class="om-eyebrow">KONTO</span><h3>Moje hasło</h3><p>Po zmianie hasła pozostałe sesje zostaną wylogowane.</p></div></div>
    <form class="om-form" method="post" action="index.php?controller=account&action=password">
      <input type="hidden" name="csrf" value="{$csrf|escape}">
      <label>Obecne hasło<input type="password" name="current_password" autocomplete="current-password" required></label>
      <label>Nowe hasło (min. 10 znaków)<input type="password" name="password" minlength="10" autocomplete="new-password" required></label>
      <label>Powtórz nowe hasło<input type="password" name="password_confirm" minlength="10" autocomplete="new-password" required></label>
      <button class="om-btn om-primary"><i class="bi bi-key"></i> Zmień hasło</button>
    </form>
  </section>
</div>
{if $canManageTenant}
<section id="sc-team" class="om-panel om-pad om-top sc-team">
  <div class="om-docs-panel-title"><div><span class="om-eyebrow">KONTO</span><h3>Zespół</h3><p>Właściciel i administrator mają pełny dostęp. Pracownik może mieć edycję albo tylko podgląd centrum zamówień.</p></div><span class="om-chip">{$teamUsers|count} os.</span></div>
  <div class="om-table-wrap"><table class="om-table sc-team-table">
    <thead><tr><th>Użytkownik</th><th>Rola</th><th>Dostęp</th><th>Zablokowany</th><th>Nowe hasło</th><th>Ostatnie logowanie</th><th></th></tr></thead>
    <tbody>
    {foreach $teamUsers as $u}
      {assign var=isSelf value=$u.id eq $currentUser.id}
      {assign var=protectedOwner value=$currentUser.role ne 'owner' and $u.role eq 'owner'}
      <tr>
        <td>{if not $protectedOwner}<form id="user-{$u.id}" method="post" action="index.php?controller=account&action=saveuser"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="update"><input type="hidden" name="user_id" value="{$u.id}"></form>
          <input form="user-{$u.id}" name="name" maxlength="150" value="{$u.name|escape}" aria-label="Imię i nazwisko {$u.name|escape}" required>{else}<strong>{$u.name|escape}</strong>{/if}<small>{$u.email|escape}</small></td>
        <td>{if $protectedOwner}{$teamRoles[$u.role]|escape}{else}{if $isSelf}<input type="hidden" form="user-{$u.id}" name="role" value="{$u.role|escape}">{/if}<select form="user-{$u.id}" name="role" aria-label="Rola {$u.name|escape}" {if $isSelf}disabled{/if}>{foreach $teamRoles as $code=>$label}{if $currentUser.role eq 'owner' or $code ne 'owner'}<option value="{$code}" {if $u.role eq $code}selected{/if}>{$label}</option>{/if}{/foreach}</select>{/if}</td>
        <td>{if $protectedOwner}Edycja{else}<select form="user-{$u.id}" name="access" aria-label="Dostęp {$u.name|escape}"><option value="edit" {if $u.access eq 'edit'}selected{/if}>Edycja</option><option value="read" {if $u.access eq 'read'}selected{/if}>Tylko podgląd</option></select>{/if}</td>
        <td>{if $protectedOwner}Nie{else}<input type="checkbox" form="user-{$u.id}" name="is_blocked" value="1" aria-label="Zablokuj {$u.name|escape}" {if $u.is_blocked}checked{/if} {if $isSelf}disabled{/if}>{/if}</td>
        <td>{if $protectedOwner}—{else}<input type="password" form="user-{$u.id}" name="new_password" minlength="10" autocomplete="new-password" aria-label="Nowe hasło {$u.name|escape}" placeholder="bez zmian">{/if}</td>
        <td><small>{$u.last_login_at|default:'—'|escape}</small></td>
        <td class="sc-team-actions">{if not $protectedOwner}<button class="om-btn om-small om-primary" form="user-{$u.id}" type="submit">Zapisz</button>
          {if not $isSelf}<form method="post" action="index.php?controller=account&action=saveuser" onsubmit="return confirm('Usunąć użytkownika?')"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="delete"><input type="hidden" name="user_id" value="{$u.id}"><button class="om-btn om-small om-danger-outline" type="submit" aria-label="Usuń {$u.name|escape}"><i class="bi bi-trash"></i></button></form>{/if}{/if}</td>
      </tr>
    {/foreach}
    </tbody>
  </table></div>
  <form class="om-form sc-team-new" method="post" action="index.php?controller=account&action=saveuser">
    <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="create">
    <h4>Dodaj użytkownika</h4>
    <div class="sc-team-new-grid">
      <label>Imię i nazwisko<input name="name" maxlength="150" required></label>
      <label>E-mail<input type="email" name="email" maxlength="190" required></label>
      <label>Hasło startowe<input type="password" name="password" minlength="10" autocomplete="new-password" required></label>
      <label>Rola<select name="role"><option value="member">Pracownik</option><option value="admin">Administrator</option></select></label>
      <label>Dostęp pracownika<select name="access"><option value="edit">Edycja</option><option value="read">Tylko podgląd</option></select></label>
      <button class="om-btn om-primary"><i class="bi bi-person-plus"></i> Dodaj</button>
    </div>
  </form>
</section>
{/if}
{include file='orders/ksef_settings.tpl'}
