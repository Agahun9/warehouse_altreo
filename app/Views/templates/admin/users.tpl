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

      <style>
        .users-admin-card {
          border-radius: 1.15rem;
          border: 1px solid rgba(15, 23, 42, 0.08);
          box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
        }

        .users-admin-summary {
          overflow: hidden;
          border-radius: 1.1rem;
          background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 52%, #60a5fa 100%);
          color: #fff;
          box-shadow: 0 18px 40px rgba(29, 78, 216, 0.18);
        }

        .users-admin-summary .card-body {
          padding: 1.4rem 1.55rem;
        }

        .users-admin-summary-title {
          margin: 0 0 0.35rem;
          font-size: 1.35rem;
          font-weight: 700;
          line-height: 1.1;
        }

        .users-admin-summary-text {
          max-width: 46rem;
          margin: 0;
          color: rgba(255, 255, 255, 0.82);
          line-height: 1.5;
        }

        .users-admin-summary .badge {
          background: rgba(255, 255, 255, 0.16) !important;
          color: #fff !important;
        }

        .users-admin-summary-actions {
          display: flex;
          flex-wrap: wrap;
          align-items: center;
          justify-content: flex-end;
          gap: 0.6rem;
        }

        .users-admin-summary .btn-light {
          border: 0;
          font-weight: 600;
        }

        .users-admin-chip {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          min-height: 3rem;
          width: 100%;
          padding: 0.75rem 0.9rem;
          border: 1px solid rgba(15, 23, 42, 0.08);
          border-radius: 0.95rem;
          background: #fff;
          font-weight: 600;
        }

        .users-admin-modules {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
          gap: 0.75rem;
        }

        .users-admin-module {
          display: flex;
          align-items: center;
          gap: 0.65rem;
          min-height: 4rem;
          padding: 0.95rem 1rem;
          border: 1px solid rgba(15, 23, 42, 0.08);
          border-radius: 1rem;
          background: linear-gradient(180deg, #fff, #f8fafc);
        }

        .users-admin-module input {
          width: 1.1rem;
          height: 1.1rem;
          flex: 0 0 auto;
        }

        .users-admin-module span {
          font-weight: 600;
          line-height: 1.2;
        }

        .users-admin-meta {
          display: flex;
          flex-wrap: wrap;
          gap: 0.5rem;
          justify-content: flex-end;
        }

        .users-admin-head {
          display: flex;
          flex-wrap: wrap;
          align-items: flex-start;
          justify-content: space-between;
          gap: 1rem;
        }

        .users-admin-identity {
          min-width: 0;
          flex: 1 1 18rem;
        }

        .users-admin-name {
          margin: 0 0 0.25rem;
          font-size: 1.2rem;
          font-weight: 700;
          line-height: 1.2;
          color: #0f172a;
        }

        .users-admin-email {
          color: #64748b;
          word-break: break-word;
        }

        .users-admin-meta .badge {
          padding: 0.45rem 0.7rem;
          font-size: 0.78rem;
          border-radius: 999px;
        }

        @media (max-width: 991.98px) {
          .users-admin-summary-actions {
            justify-content: flex-start;
          }

          .users-admin-meta {
            justify-content: flex-start;
          }
        }
      </style>

      <div class="card users-admin-summary mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
          <div>
            <h3 class="users-admin-summary-title">Lista kont</h3>
            <p class="users-admin-summary-text">Edytujesz dane osobowe, role, status konta, dostep do modulow oraz tryb odczyt lub edycja.</p>
          </div>
          <div class="users-admin-summary-actions">
            <a href="{$baseUrl}?controller=admin&action=automation" class="btn btn-light btn-sm">Administracja</a>
            <span class="badge">{$users|@count} kont</span>
          </div>
        </div>
      </div>

      <div class="row">
        {foreach $users as $user}
          <div class="col-xl-6">
            <div class="card users-admin-card mb-4">
              <div class="card-header bg-white border-0 pt-4 px-4">
                <div class="users-admin-head">
                  <div class="users-admin-identity">
                    <h3 class="users-admin-name">{if $user.first_name|default:'' neq '' or $user.last_name|default:'' neq ''}{$user.first_name|default:''|escape} {$user.last_name|default:''|escape}{else}{$user.email|escape}{/if}</h3>
                    <div class="users-admin-email">{$user.email|escape}</div>
                  </div>
                  <div class="users-admin-meta">
                    <span class="badge text-bg-{if $user.role eq 'admin'}dark{else}secondary{/if}">{$user.role|escape}</span>
                    <span class="badge text-bg-{if $user.permission_level eq 'read'}warning{else}primary{/if}">{if $user.permission_level eq 'read'}odczyt{else}edycja{/if}</span>
                    <span class="badge text-bg-secondary">ID {$user.id}</span>
                  </div>
                </div>
              </div>

              <form method="post" action="{$baseUrl}?controller=admin&action=updateUser">
                <input type="hidden" name="id" value="{$user.id}">

                <div class="card-body px-4 pt-2">
                  <div class="row g-3 mb-4">
                    <div class="col-md-6">
                      <label class="form-label">Imie</label>
                      <input type="text" name="first_name" class="form-control" value="{$user.first_name|default:''|escape}">
                    </div>

                    <div class="col-md-6">
                      <label class="form-label">Nazwisko</label>
                      <input type="text" name="last_name" class="form-control" value="{$user.last_name|default:''|escape}">
                    </div>

                    <div class="col-md-4">
                      <label class="form-label">Rola</label>
                      <select name="role" class="form-select">
                        <option value="user"{if $user.role eq 'user'} selected{/if}>user</option>
                        <option value="admin"{if $user.role eq 'admin'} selected{/if}>admin</option>
                      </select>
                    </div>

                    <div class="col-md-4">
                      <label class="form-label">Zakres dostepu</label>
                      <select name="permission_level" class="form-select">
                        <option value="edit"{if $user.permission_level|default:'edit' eq 'edit'} selected{/if}>moze edytowac</option>
                        <option value="read"{if $user.permission_level|default:'' eq 'read'} selected{/if}>tylko odczyt</option>
                      </select>
                    </div>

                    <div class="col-md-4">
                      <label class="form-label">Status konta</label>
                      <select name="is_active" class="form-select">
                        <option value="1"{if $user.is_active} selected{/if}>aktywne</option>
                        <option value="0"{if !$user.is_active} selected{/if}>nieaktywne</option>
                      </select>
                    </div>
                  </div>

                  <div class="row g-3 mb-4">
                    <div class="col-md-6">
                      <label class="form-label">Blokada logowania</label>
                      <select name="is_blocked" class="form-select">
                        <option value="0"{if !$user.is_blocked} selected{/if}>odblokowany</option>
                        <option value="1"{if $user.is_blocked} selected{/if}>zablokowany</option>
                      </select>
                    </div>

                    <div class="col-md-6">
                      <label class="form-label">Nowe haslo</label>
                      <input type="password" class="form-control" name="new_password" placeholder="pozostaw puste, aby nie zmieniac">
                    </div>
                  </div>

                  <div class="mb-3">
                    <label class="form-label fs-5 fw-semibold mb-3">Dostep do modulow</label>
                    <div class="users-admin-modules">
                      {foreach $modules as $module}
                        <label class="users-admin-module" for="mod_{$user.id}_{$module.code|escape}">
                          <input class="form-check-input mt-0" type="checkbox" name="modules[]" value="{$module.code|escape}" id="mod_{$user.id}_{$module.code|escape}"{if in_array($module.code, $user.modules)} checked{/if}>
                          <span>{$module.name|escape}</span>
                        </label>
                      {/foreach}
                    </div>
                  </div>
                </div>

                <div class="card-footer bg-white border-0 d-flex justify-content-between align-items-center px-4 pb-4">
                  <div>
                    {if $user.id neq $currentAdminId}
                      <button
                        type="submit"
                        class="btn btn-outline-danger"
                        formaction="{$baseUrl}?controller=admin&action=deleteUser"
                        formmethod="post"
                        onclick="return confirm('Usunac uzytkownika {$user.email|escape:'javascript'}?');"
                      >
                        Usun uzytkownika
                      </button>
                    {else}
                      <button type="button" class="btn btn-outline-danger" disabled>To Twoje konto</button>
                    {/if}
                  </div>
                  <button type="submit" class="btn btn-primary px-4">Zapisz zmiany</button>
                </div>
              </form>
            </div>
          </div>
        {/foreach}
      </div>
    </div>
  </div>
</main>
