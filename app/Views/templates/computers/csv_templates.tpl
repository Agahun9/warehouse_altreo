<div class="container-fluid py-4">
  <ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link" href="{$baseUrl}?controller=computers&action=products">Produkty</a></li>
    <li class="nav-item"><a class="nav-link" href="{$baseUrl}?controller=computers&action=components">Komponenty</a></li>
    <li class="nav-item"><a class="nav-link active" href="{$baseUrl}?controller=computers&action=csvtemplates">Szablony CSV</a></li>
    <li class="nav-item"><a class="nav-link" href="{$baseUrl}?controller=computers&action=titletemplates">Szablony tytułów</a></li>
  </ul>

  {if $success}<div class="alert alert-success">{$success|escape:'html'}</div>{/if}
  {if $errors}
    <div class="alert alert-danger"><ul class="mb-0">{foreach from=$errors item=error}<li>{$error|escape:'html'}</li>{/foreach}</ul></div>
  {/if}

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h3 mb-1">Szablony CSV komputerów</h1>
      <div class="text-muted">Osobne od szablonów głównego magazynu. Eksport działa na zaznaczonych komputerach.</div>
    </div>
    <a href="{$baseUrl}?controller=computers&action=products" class="btn btn-outline-primary">Przejdź do eksportu</a>
  </div>

  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle mb-0">
        <thead class="table-light">
          <tr><th>Nazwa</th><th>Status</th><th>Opis</th><th>Format</th><th>Kolumny</th><th>Zmodyfikowano</th><th class="text-end">Akcje</th></tr>
        </thead>
        <tbody>
          {foreach from=$templates item=template}
            <tr>
              <td class="fw-semibold">{$template.name|escape:'html'} {if $template.is_system}<span class="badge text-bg-primary">gotowy</span>{/if}</td>
              <td>
                {if $template.is_active}
                  <span class="badge text-bg-success">Aktywny</span>
                {else}
                  <span class="badge text-bg-secondary">Nieaktywny</span>
                {/if}
              </td>
              <td>{$template.description|default:'-'|escape:'html'}</td>
              <td>{$template.encoding|escape:'html'}, separator <code>{$template.delimiter|escape:'html'}</code>{if $template.add_bom} + BOM{/if}</td>
              <td><span class="badge text-bg-secondary">{$template.columns_count}</span></td>
              <td>{$template.updated_at|escape:'html'}</td>
              <td class="text-end text-nowrap">
                <form method="post" action="{$baseUrl}?controller=computers&action=togglecsvtemplateactive" class="d-inline">
                  <input type="hidden" name="id" value="{$template.id}">
                  <input type="hidden" name="is_active" value="{if $template.is_active}0{else}1{/if}">
                  <button class="btn btn-sm {if $template.is_active}btn-outline-warning{else}btn-outline-success{/if}">{if $template.is_active}Dezaktywuj{else}Aktywuj{/if}</button>
                </form>
                <a href="{$baseUrl}?controller=computers&action=editcsvtemplate&id={$template.id}" class="btn btn-sm btn-outline-primary">Edytuj</a>
                <form method="post" action="{$baseUrl}?controller=computers&action=duplicatecsvtemplate" class="d-inline">
                  <input type="hidden" name="id" value="{$template.id}">
                  <button class="btn btn-sm btn-outline-secondary">Duplikuj</button>
                </form>
                {if !$template.is_system}
                  <form method="post" action="{$baseUrl}?controller=computers&action=deletecsvtemplate" class="d-inline" onsubmit="return confirm('Usunąć ten szablon?');">
                    <input type="hidden" name="id" value="{$template.id}">
                    <button class="btn btn-sm btn-outline-danger">Usuń</button>
                  </form>
                {/if}
              </td>
            </tr>
          {/foreach}
        </tbody>
      </table>
    </div>
  </div>
</div>
