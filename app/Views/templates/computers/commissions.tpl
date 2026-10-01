<div class="container-fluid py-4">
  <ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link{if $computerTab eq 'products'} active{/if}" href="{$baseUrl}?controller=computers&action=products">Produkty</a></li>
    <li class="nav-item"><a class="nav-link{if $computerTab eq 'components'} active{/if}" href="{$baseUrl}?controller=computers&action=components">Komponenty</a></li>
    <li class="nav-item"><a class="nav-link{if $computerTab eq 'csvtemplates'} active{/if}" href="{$baseUrl}?controller=computers&action=csvtemplates">Szablony CSV</a></li>
    <li class="nav-item"><a class="nav-link{if $computerTab eq 'commissions'} active{/if}" href="{$baseUrl}?controller=computers&action=commissions">Prowizje</a></li>
    <li class="nav-item"><a class="nav-link{if $computerTab eq 'titletemplates'} active{/if}" href="{$baseUrl}?controller=computers&action=titletemplates">Szablony tytułów</a></li>
  </ul>

  {if $success}<div class="alert alert-success">{$success|escape:'html'}</div>{/if}
  {if $errors}<div class="alert alert-danger"><ul class="mb-0">{foreach from=$errors item=error}<li>{$error|escape:'html'}</li>{/foreach}</ul></div>{/if}

  <div class="card" style="max-width: 640px;">
    <div class="card-body">
      <h5 class="card-title mb-2">Prowizje marketplace</h5>
      <p class="text-muted small mb-3">
        Cena na marketplace = cena magazynowa / (1 − prowizja), zaokrąglona do pełnych dziesiątek.
        Zarobek = cena na marketplace − prowizja − suma cen podzespołów.
        Prowizja 0% oznacza wysyłanie ceny magazynowej bez zmian.
      </p>
      <form method="post" action="{$baseUrl}?controller=computers&action=commissions">
        <table class="table table-sm align-middle mb-3">
          <thead>
            <tr>
              <th>Marketplace</th>
              <th style="width: 180px;">Prowizja (%)</th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$marketplaceLabels key=marketKey item=marketLabel}
              <tr>
                <td><label for="commission_{$marketKey}" class="mb-0">{$marketLabel|escape:'html'}</label></td>
                <td>
                  <input type="number" id="commission_{$marketKey}" name="bulk_commission[{$marketKey}]" class="form-control form-control-sm" min="0" max="49.99" step="0.01" value="{$marketplaceCommissions[$marketKey]|default:0}" />
                </td>
              </tr>
            {/foreach}
          </tbody>
        </table>
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Zapisz prowizje</button>
      </form>
    </div>
  </div>
</div>
