<main class="app-main">
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-6">
          <h3 class="mb-0">{$contentTitle|escape}</h3>
          <p class="text-secondary mb-0">{$pageDescription|escape}</p>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-end">
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=index">Start</a></li>
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=accountingwarehouse&action=index">Magazyn ksiegowy</a></li>
            <li class="breadcrumb-item active" aria-current="page">{$breadcrumbCurrent|escape}</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      {if $flashSuccess}<div class="alert alert-success">{$flashSuccess|escape}</div>{/if}
      {if $flashError}<div class="alert alert-danger">{$flashError|escape}</div>{/if}

      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">Dodaj lub rozbuduj pozycje ksiegowa</h3></div>
        <div class="card-body">
          <form method="post" action="{$baseUrl}?controller=accountingwarehouse&action=savemacro" class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Nazwa pozycji ksiegowej</label>
              <input type="text" class="form-control" name="macro_name" placeholder="np. wysylka, etui, t-shirt" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Jednostka</label>
              <input type="text" class="form-control" name="macro_unit" value="szt.">
            </div>
            <div class="col-md-2">
              <label class="form-label">Typ</label>
              <select class="form-select" name="macro_item_kind">
                <option value="towar">towar</option>
                <option value="koszt">koszt</option>
                <option value="korekta">korekta</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Aliasy i rozpoznawane frazy</label>
              <input type="text" class="form-control" name="macro_aliases" placeholder="np. usluga kurierska, kurier, dostawa, przesylka">
              <div class="small text-secondary mt-1">Mozesz wpisac wiele aliasow oddzielonych przecinkiem, srednikiem albo nowa linia.</div>
            </div>
            <div class="col-12 d-flex gap-2">
              <button type="submit" class="btn btn-primary">Zapisz pozycje ksiegowa</button>
              <a href="{$baseUrl}?controller=accountingwarehouse&action=create" class="btn btn-outline-secondary">Wroc do dodawania dokumentu</a>
            </div>
          </form>
        </div>
      </div>

      {if $editMacro}
        <div class="card mb-4 border-primary">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Edytuj pozycje ksiegowa: {$editMacro.name|escape}</h3>
            <a href="{$baseUrl}?controller=accountingwarehouse&action=macros" class="btn btn-sm btn-outline-secondary">Anuluj</a>
          </div>
          <div class="card-body">
            <form method="post" action="{$baseUrl}?controller=accountingwarehouse&action=updatemacro" class="row g-3">
              <input type="hidden" name="id" value="{$editMacro.id}">
              <div class="col-md-4">
                <label class="form-label">Nazwa pozycji ksiegowej</label>
                <input type="text" class="form-control" name="macro_name" value="{$editMacro.name|escape}" required>
              </div>
              <div class="col-md-2">
                <label class="form-label">Jednostka</label>
                <input type="text" class="form-control" name="macro_unit" value="{$editMacro.unit|escape}">
              </div>
              <div class="col-md-2">
                <label class="form-label">Typ</label>
                <select class="form-select" name="macro_item_kind">
                  <option value="towar"{if $editMacro.item_kind eq 'towar'} selected{/if}>towar</option>
                  <option value="koszt"{if $editMacro.item_kind eq 'koszt'} selected{/if}>koszt</option>
                  <option value="korekta"{if $editMacro.item_kind eq 'korekta'} selected{/if}>korekta</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Aliasy i rozpoznawane frazy</label>
                <input type="text" class="form-control" name="macro_aliases" value="{foreach $editMacro.aliases as $alias}{$alias.source_name|escape}{if !$alias@last}, {/if}{/foreach}">
                <div class="small text-secondary mt-1">Aliasy wpisuj po przecinkach, np. `usluga kurierska, kurier, dostawa`.</div>
              </div>
              <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
                <a href="{$baseUrl}?controller=accountingwarehouse&action=macros" class="btn btn-outline-secondary">Anuluj</a>
              </div>
            </form>
          </div>
        </div>
      {/if}

      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0">Zdefiniowane pozycje ksiegowe</h3>
          <span class="badge text-bg-secondary">{$macros|@count} pozycji</span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-hover table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Pozycja ksiegowa</th>
                  <th>Typ</th>
                  <th>Jednostka</th>
                  <th>Aliasy</th>
                  <th class="text-end">Liczba aliasow</th>
                  <th class="text-end">Akcje</th>
                </tr>
              </thead>
              <tbody>
                {foreach $macros as $macro}
                  <tr>
                    <td class="fw-semibold">{$macro.name|escape}</td>
                    <td>{$macro.item_kind|default:'towar'|escape}</td>
                    <td>{$macro.unit|escape}</td>
                    <td>
                      {assign var=aliases value=$macro.aliases_list|default:''}
                      {if $aliases ne ''}
                        {$aliases|replace:'||':', '|escape}
                      {else}
                        <span class="text-secondary">brak aliasow</span>
                      {/if}
                    </td>
                    <td class="text-end">{$macro.aliases_count}</td>
                    <td class="text-end">
                      <a href="{$baseUrl}?controller=accountingwarehouse&action=macros&id={$macro.id}" class="btn btn-sm btn-outline-primary">Edytuj</a>
                      <form method="post" action="{$baseUrl}?controller=accountingwarehouse&action=deletemacro" class="d-inline" onsubmit="return confirm('Usunac pozycje ksiegowa {$macro.name|escape:'javascript'}?');">
                        <input type="hidden" name="id" value="{$macro.id}">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Usun</button>
                      </form>
                    </td>
                  </tr>
                {foreachelse}
                  <tr><td colspan="6" class="text-center py-3">Brak zdefiniowanych makr.</td></tr>
                {/foreach}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
