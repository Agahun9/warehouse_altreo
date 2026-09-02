<div class="container-fluid py-4 component-import-preview-page">
  <ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link" href="{$baseUrl}?controller=computers&action=products">Produkty</a></li>
    <li class="nav-item"><a class="nav-link active" href="{$baseUrl}?controller=computers&action=components">Komponenty</a></li>
    <li class="nav-item"><a class="nav-link" href="{$baseUrl}?controller=computers&action=csvtemplates">Szablony CSV</a></li>
    <li class="nav-item"><a class="nav-link" href="{$baseUrl}?controller=computers&action=titletemplates">Szablony tytułów</a></li>
  </ul>

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div class="d-flex align-items-center">
      <span class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;font-size:1.6rem">
        <i class="bi bi-filetype-json"></i>
      </span>
      <div>
        <h1 class="h3 mb-1">Podgląd importu JSON</h1>
        <div class="text-muted small">
          Plik: <strong>{$importFilename|escape:'html'}</strong> · wczytano {$importCreatedAt|escape:'html'}
        </div>
      </div>
    </div>
    <a class="btn btn-outline-secondary" href="{$baseUrl}?controller=computers&action=components">
      <i class="bi bi-x-circle me-1"></i>Anuluj import
    </a>
  </div>

  {if $previewErrors}
    <div class="alert alert-danger shadow-sm" role="alert">
      <div class="fw-semibold mb-2"><i class="bi bi-exclamation-triangle me-1"></i>Sprawdź dane przed zapisem</div>
      <ul class="mb-0 ps-3">
        {foreach from=$previewErrors item=error}
          <li>{$error|escape:'html'}</li>
        {/foreach}
      </ul>
    </div>
  {/if}

  <div class="alert alert-info shadow-sm">
    <i class="bi bi-info-circle me-1"></i>
    Zaznaczone mapy zostaną zapisane w komponencie. Możesz edytować JSON, użyć przycisku
    <strong>Przelicz podsumowanie</strong>, a dopiero potem zatwierdzić aktualizację.
    Przy pojedynczym parametrze zaznacz <strong>Pomiń</strong>, aby pozostawić jego obecną wartość.
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
      <div class="card h-100 shadow-sm"><div class="card-body">
        <div class="small text-muted">Komponenty</div><div class="fs-3 fw-semibold">{$previewSummary.components}</div>
      </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="card h-100 shadow-sm border-primary"><div class="card-body">
        <div class="small text-muted">Wybrane mapy</div><div class="fs-3 fw-semibold text-primary">{$previewSummary.selected_profiles}</div>
        <div class="small text-muted">z {$previewSummary.profiles}</div>
      </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="card h-100 shadow-sm border-success"><div class="card-body">
        <div class="small text-muted">Dodawane</div><div class="fs-3 fw-semibold text-success">{$previewSummary.selected_added}</div>
      </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="card h-100 shadow-sm border-warning"><div class="card-body">
        <div class="small text-muted">Zmieniane</div><div class="fs-3 fw-semibold text-warning">{$previewSummary.selected_changed}</div>
      </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="card h-100 shadow-sm border-danger"><div class="card-body">
        <div class="small text-muted">Usuwane</div><div class="fs-3 fw-semibold text-danger">{$previewSummary.selected_removed}</div>
      </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="card h-100 shadow-sm"><div class="card-body">
        <div class="small text-muted">Bez zmian</div><div class="fs-3 fw-semibold text-secondary">{$previewSummary.selected_unchanged}</div>
      </div></div>
    </div>
  </div>

  <form method="post" action="{$baseUrl}?controller=computers&action=applycomponentsimport" id="componentImportPreviewForm">
    <input type="hidden" name="token" value="{$importToken|escape:'html'}">

    {foreach from=$previewItems item=item}
      <section class="card shadow-sm mb-4{if !$item.exists} border-danger{/if}">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div>
            <strong>{$item.name|default:'Bez nazwy'|escape:'html'}</strong>
            <span class="badge text-bg-secondary ms-1">ID {$item.id}</span>
            {if $item.category}<span class="text-muted ms-2">{$item.category|escape:'html'}</span>{/if}
          </div>
          {if $item.exists}
            <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Komponent znaleziony</span>
          {else}
            <span class="badge text-bg-danger"><i class="bi bi-x-circle me-1"></i>Brak komponentu — zapis pominięty</span>
          {/if}
        </div>

        <div class="card-body">
          {if !$item.profiles}
            <div class="text-muted">Ten rekord nie zawiera map parametrów do aktualizacji.</div>
          {/if}

          {foreach from=$item.profiles item=profile name=profiles}
            <div class="border rounded p-3{if !$smarty.foreach.profiles.last} mb-3{/if}{if $profile.invalid} border-danger{/if}">
              <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div class="form-check form-switch">
                  <input class="form-check-input js-import-profile" type="checkbox"
                    id="profile_{$item.index}_{$profile.column|escape:'html'}"
                    name="apply_profiles[{$item.index}][{$profile.column|escape:'html'}]" value="1"
                    {if $profile.selected}checked{/if}{if !$item.exists || $profile.invalid} disabled{/if}>
                  <label class="form-check-label fw-semibold" for="profile_{$item.index}_{$profile.column|escape:'html'}">
                    Zapisz mapę: {$profile.label|escape:'html'}
                  </label>
                </div>
                <div class="d-flex flex-wrap gap-1">
                  <span class="badge text-bg-success">+ {$profile.applied_counts.added}</span>
                  <span class="badge text-bg-warning">zmiana {$profile.applied_counts.changed}</span>
                  <span class="badge text-bg-danger">usunięcie {$profile.applied_counts.removed}</span>
                  <span class="badge text-bg-secondary">bez zmian {$profile.counts.unchanged}</span>
                  {if $profile.skipped_count > 0}<span class="badge text-bg-info">pomijane {$profile.skipped_count}</span>{/if}
                </div>
              </div>

              {if $profile.counts.removed > 0}
                <div class="alert alert-warning py-2 small">
                  <i class="bi bi-exclamation-triangle me-1"></i>
                  W nowej mapie brakuje {$profile.counts.removed} {if $profile.counts.removed == 1}parametru{else}parametrów{/if}; po zapisie zostaną usunięte z tej mapy.
                </div>
              {/if}

              <div class="row g-3">
                <div class="col-xl-7">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="small fw-semibold">Co zmieni się: stara wartość → nowa wartość</div>
                    <button type="button" class="btn btn-sm btn-link text-decoration-none js-toggle-unchanged">Pokaż bez zmian</button>
                  </div>
                  <div class="table-responsive border rounded import-diff-table-wrap">
                    <table class="table table-sm table-hover align-middle mb-0 import-diff-table">
                      <thead class="table-light">
                        <tr><th>Parametr</th><th>Status</th><th>Było</th><th>Będzie</th><th class="text-center">Pomiń</th></tr>
                      </thead>
                      <tbody>
                        {foreach from=$profile.diffs item=diff}
                          <tr class="js-import-diff-row{if $diff.status eq 'unchanged'} js-unchanged-row d-none{/if}{if $diff.skipped} table-secondary{/if}">
                            <td class="fw-semibold">{$diff.key|escape:'html'}</td>
                            <td>
                              {if $diff.status eq 'added'}
                                <span class="badge text-bg-success">Dodany</span>
                              {elseif $diff.status eq 'changed'}
                                <span class="badge text-bg-warning">Zmieniony</span>
                              {elseif $diff.status eq 'removed'}
                                <span class="badge text-bg-danger">Usuwany</span>
                              {else}
                                <span class="badge text-bg-secondary">Bez zmian</span>
                              {/if}
                            </td>
                            <td class="text-break import-value">{$diff.old|escape:'html'}</td>
                            <td class="text-break import-value">{$diff.new|escape:'html'}</td>
                            <td class="text-center">
                              {if $diff.status ne 'unchanged'}
                                <div class="form-check d-inline-block m-0" title="Zachowaj obecną wartość tego parametru">
                                  <input class="form-check-input js-skip-parameter" type="checkbox"
                                    name="skip_parameters[{$item.index}][{$profile.column|escape:'html'}][]"
                                    value="{$diff.key|escape:'html'}"
                                    aria-label="Pomiń zmianę parametru {$diff.key|escape:'html'}"
                                    {if $diff.skipped}checked{/if}>
                                </div>
                              {/if}
                            </td>
                          </tr>
                        {/foreach}
                        {if !$profile.diffs}
                          <tr><td colspan="5" class="text-center text-muted py-3">Obie mapy są puste.</td></tr>
                        {/if}
                      </tbody>
                    </table>
                  </div>
                </div>
                <div class="col-xl-5">
                  <label class="form-label small fw-semibold" for="editor_{$item.index}_{$profile.column|escape:'html'}">Edytuj docelową mapę JSON</label>
                  <textarea class="form-control font-monospace import-json-editor{if $profile.invalid} is-invalid{/if}"
                    id="editor_{$item.index}_{$profile.column|escape:'html'}"
                    name="parameter_json[{$item.index}][{$profile.column|escape:'html'}]"
                    rows="15" spellcheck="false">{$profile.editor_json|escape:'html'}</textarea>
                  {if $profile.invalid}<div class="invalid-feedback">Popraw składnię JSON i przelicz podsumowanie.</div>{/if}
                  <div class="form-text">Mapa musi być obiektem JSON, np. <code>{ldelim}"Nazwa": "wartość"{rdelim}</code>.</div>
                </div>
              </div>
            </div>
          {/foreach}
        </div>
      </section>
    {/foreach}

    <div class="sticky-bottom bg-body border-top shadow-lg py-3 px-2 mt-4 component-import-actions">
      <div class="container-fluid d-flex flex-wrap justify-content-end gap-2">
        <a class="btn btn-outline-secondary" href="{$baseUrl}?controller=computers&action=components">Anuluj</a>
        <button type="submit" class="btn btn-outline-primary"
          formaction="{$baseUrl}?controller=computers&action=componentsimportpreview&amp;token={$importToken|escape:'url'}">
          <i class="bi bi-arrow-repeat me-1"></i>Przelicz podsumowanie
        </button>
        <button type="submit" class="btn btn-success" id="confirmComponentImport">
          <i class="bi bi-database-check me-1"></i>Zapisz wybrane zmiany
        </button>
      </div>
    </div>
  </form>
</div>

<style>
  .component-import-preview-page .import-diff-table-wrap { max-height: 430px; overflow: auto; }
  .component-import-preview-page .import-diff-table thead { position: sticky; top: 0; z-index: 1; }
  .component-import-preview-page .import-value { min-width: 150px; max-width: 320px; white-space: pre-wrap; }
  .component-import-preview-page .import-json-editor { min-height: 360px; font-size: .82rem; line-height: 1.4; }
  .component-import-preview-page .component-import-actions { z-index: 1010; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.js-toggle-unchanged').forEach(function (button) {
    button.addEventListener('click', function () {
      var profile = button.closest('.border.rounded.p-3');
      if (!profile) return;
      var rows = profile.querySelectorAll('.js-unchanged-row');
      var show = Array.prototype.some.call(rows, function (row) { return row.classList.contains('d-none'); });
      rows.forEach(function (row) { row.classList.toggle('d-none', !show); });
      button.textContent = show ? 'Ukryj bez zmian' : 'Pokaż bez zmian';
    });
  });

  document.querySelectorAll('.js-skip-parameter').forEach(function (checkbox) {
    checkbox.addEventListener('change', function () {
      var row = checkbox.closest('.js-import-diff-row');
      if (row) row.classList.toggle('table-secondary', checkbox.checked);
    });
  });

  var form = document.getElementById('componentImportPreviewForm');
  var confirmButton = document.getElementById('confirmComponentImport');
  if (form && confirmButton) {
    form.addEventListener('submit', function (event) {
      if (event.submitter !== confirmButton) return;
      var selected = form.querySelectorAll('.js-import-profile:checked').length;
      if (selected === 0) {
        event.preventDefault();
        window.alert('Zaznacz przynajmniej jedną mapę parametrów do zapisania.');
        return;
      }
      if (!window.confirm('Zapisać ' + selected + ' wybranych map parametrów w komponentach?')) {
        event.preventDefault();
      }
    });
  }
});
</script>
