<div class="container-fluid py-4">
  <ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link{if $computerTab eq 'products'} active{/if}" href="{$baseUrl}?controller=computers&action=products">Produkty</a></li>
    <li class="nav-item"><a class="nav-link{if $computerTab eq 'components'} active{/if}" href="{$baseUrl}?controller=computers&action=components">Komponenty</a></li>
    <li class="nav-item"><a class="nav-link{if $computerTab eq 'csvtemplates'} active{/if}" href="{$baseUrl}?controller=computers&action=csvtemplates">Szablony CSV</a></li>
    <li class="nav-item"><a class="nav-link{if $computerTab eq 'titletemplates'} active{/if}" href="{$baseUrl}?controller=computers&action=titletemplates">Szablony tytułów</a></li>
  </ul>

  {if $success}<div class="alert alert-success">{$success|escape:'html'}</div>{/if}
  {if $errors}<div class="alert alert-danger"><ul class="mb-0">{foreach from=$errors item=error}<li>{$error|escape:'html'}</li>{/foreach}</ul></div>{/if}

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h3 mb-1">Szablony tytułów komputerów</h1>
      <div class="text-muted">Osobne od ogólnych szablonów CSV. Działają tylko przy tworzeniu wariantów komputerowych.</div>
    </div>
    <a href="{$baseUrl}?controller=computers&action=createtitletemplate" class="btn btn-primary">Dodaj szablon tytułu</a>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <div class="fw-semibold mb-2">Jak używać</div>
      <ol class="small mb-0 ps-3">
        <li>Dodaj tutaj osobny szablon tytułu dla komputerów.</li>
        <li>Wejdź w `Komputery → Produkty → Warianty`.</li>
        <li>Wybierz szablon tytułu i wygeneruj warianty.</li>
      </ol>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle mb-0">
        <thead class="table-light">
          <tr><th>Nazwa</th><th>Opis</th><th>Wzór</th><th>Utworzono</th><th>Zmodyfikowano</th><th class="text-end">Akcje</th></tr>
        </thead>
        <tbody>
          {foreach from=$titleTemplates item=titleTemplate}
            <tr>
              <td class="fw-semibold">{$titleTemplate.name|escape:'html'}</td>
              <td>{$titleTemplate.description|default:'-'|escape:'html'}</td>
              <td><pre class="bg-light border rounded p-2 small mb-0"><code>{$titleTemplate.template_body|escape:'html'}</code></pre></td>
              <td>{$titleTemplate.created_at|default:'-'|escape:'html'}</td>
              <td>{$titleTemplate.updated_at|default:'-'|escape:'html'}</td>
              <td class="text-end text-nowrap">
                <a href="{$baseUrl}?controller=computers&action=edittitletemplate&id={$titleTemplate.id}" class="btn btn-sm btn-outline-primary">Edytuj</a>
                <form method="post" action="{$baseUrl}?controller=computers&action=deletetitletemplate" class="d-inline" onsubmit="return confirm('Usunąć szablon tytułu {$titleTemplate.name|escape:'javascript'}?');">
                  <input type="hidden" name="id" value="{$titleTemplate.id}">
                  <button type="submit" class="btn btn-sm btn-outline-danger">Usuń</button>
                </form>
              </td>
            </tr>
          {foreachelse}
            <tr><td colspan="6" class="text-center py-4">Brak szablonów tytułów komputerów.</td></tr>
          {/foreach}
        </tbody>
      </table>
    </div>
  </div>
</div>
