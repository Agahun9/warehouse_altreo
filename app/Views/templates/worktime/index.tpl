<main class="app-main">
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
          <h3 class="mb-0">{$contentTitle|escape}</h3>
          <p class="text-secondary mb-0">{$pageDescription|escape}</p>
        </div>
        <button type="button" class="btn btn-primary" id="worktime-add-button" data-bs-toggle="modal" data-bs-target="#workTimeModal">
          <i class="bi bi-plus-circle me-1"></i> Dodaj wejscie / wyjscie
        </button>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      {if $flashSuccess}<div class="alert alert-success">{$flashSuccess|escape}</div>{/if}
      {if $flashError}<div class="alert alert-danger">{$flashError|escape}</div>{/if}

      <div class="card mb-4">
        <div class="card-body">
          <form method="get" action="{$baseUrl}" class="row g-3 align-items-end">
            <input type="hidden" name="controller" value="worktime">
            <input type="hidden" name="action" value="index">
            <div class="col-md-3">
              <label class="form-label" for="worktime-month">Miesiac</label>
              <input type="month" class="form-control" id="worktime-month" name="month" value="{$selectedMonth|escape}">
            </div>
            {if $isWorkTimeManager}
              <div class="col-md-5">
                <label class="form-label" for="worktime-user-filter">Pracownik</label>
                <select class="form-select" id="worktime-user-filter" name="user_id">
                  <option value="0">Wszyscy pracownicy</option>
                  {foreach $users as $user}
                    <option value="{$user.id|escape}"{if $selectedUserId eq $user.id} selected{/if}>{if $user.first_name or $user.last_name}{$user.first_name|escape} {$user.last_name|escape} — {/if}{$user.email|escape}</option>
                  {/foreach}
                </select>
              </div>
            {/if}
            <div class="col-md-auto"><button type="submit" class="btn btn-outline-primary">Pokaz zestawienie</button></div>
          </form>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-lg-4">
          <div class="card h-100">
            <div class="card-body">
              <div class="text-secondary small text-uppercase">Lacznie w miesiacu</div>
              <div class="display-5 fw-bold text-primary">{$totalHours|string_format:'%.2f'} h</div>
              <div class="text-secondary">Liczone automatycznie z godzin od–do · {$selectedMonth|escape}</div>
            </div>
          </div>
        </div>
        <div class="col-lg-8">
          <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Podsumowanie miesieczne</h3></div>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Pracownik</th><th>Dni pracy</th><th>Wpisy</th><th class="text-end">Godziny</th></tr></thead>
                <tbody>
                  {foreach $summaries as $summary}
                    <tr>
                      <td>{if $summary.first_name or $summary.last_name}<span class="fw-semibold">{$summary.first_name|escape} {$summary.last_name|escape}</span><br>{/if}<span class="small text-secondary">{$summary.email|escape}</span></td>
                      <td>{$summary.work_days|escape}</td><td>{$summary.entry_count|escape}</td>
                      <td class="text-end fw-bold">{$summary.total_hours|string_format:'%.2f'} h</td>
                    </tr>
                  {foreachelse}<tr><td colspan="4" class="text-center text-secondary py-4">Brak godzin w tym miesiacu.</td></tr>{/foreach}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">Wejscia i wyjscia</h3></div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Data</th>{if $isWorkTimeManager}<th>Pracownik</th>{/if}<th>Od</th><th>Do</th><th>Razem</th><th>Notatka</th><th>Ostatnia zmiana</th><th></th></tr></thead>
            <tbody>
              {foreach $entries as $entry}
                <tr>
                  <td class="fw-semibold">{$entry.work_date|escape}</td>
                  {if $isWorkTimeManager}<td>{if $entry.first_name or $entry.last_name}{$entry.first_name|escape} {$entry.last_name|escape}<br>{/if}<span class="small text-secondary">{$entry.email|escape}</span></td>{/if}
                  <td><span class="badge text-bg-light border fs-6">{$entry.start_time_display|default:'—'|escape}</span></td>
                  <td><span class="badge text-bg-light border fs-6">{$entry.end_time_display|default:'—'|escape}</span></td>
                  <td><span class="badge text-bg-primary fs-6">{$entry.hours|string_format:'%.2f'} h</span></td>
                  <td style="white-space:pre-wrap;max-width:480px;">{$entry.note|default:'—'|escape}</td>
                  <td class="small text-secondary">{$entry.updated_at|escape}</td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-2">
                      <button type="button" class="btn btn-sm btn-outline-primary js-worktime-edit" data-bs-toggle="modal" data-bs-target="#workTimeModal" data-id="{$entry.id|escape}" data-user-id="{$entry.user_id|escape}" data-date="{$entry.work_date|escape}" data-start-time="{$entry.start_time|default:''|escape}" data-end-time="{$entry.end_time|default:''|escape}" data-note="{$entry.note|escape:'htmlall'}">Edytuj</button>
                      {if $isWorkTimeManager}
                        <form method="post" action="{$baseUrl}?controller=worktime&action=delete" class="m-0 js-worktime-delete">
                          <input type="hidden" name="id" value="{$entry.id|escape}">
                          <input type="hidden" name="return_month" value="{$selectedMonth|escape}">
                          <input type="hidden" name="return_user_id" value="{$selectedUserId|escape}">
                          <button type="submit" class="btn btn-sm btn-outline-danger">Usun</button>
                        </form>
                      {/if}
                    </div>
                  </td>
                </tr>
              {foreachelse}<tr><td colspan="{if $isWorkTimeManager}8{else}7{/if}" class="text-center text-secondary py-5">Brak wpisow dla wybranego miesiaca.</td></tr>{/foreach}
            </tbody>
          </table>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center gap-3">
          <h3 class="card-title mb-0">Dziennik zmian</h3>
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#worktime-audit-log" aria-expanded="false" aria-controls="worktime-audit-log">
            Pokaz / ukryj
          </button>
        </div>
        <div class="collapse" id="worktime-audit-log">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th>Kiedy</th><th>Wpis</th>{if $isWorkTimeManager}<th>Pracownik</th><th>Wykonal</th>{/if}<th>Operacja</th><th>Zapisane dane</th></tr></thead>
              <tbody>
                {foreach $auditLogs as $log}
                  <tr>
                    <td class="text-nowrap">{$log.created_at|escape}</td>
                    <td>#{$log.entry_id|escape}</td>
                    {if $isWorkTimeManager}<td>{if $log.owner_first_name or $log.owner_last_name}{$log.owner_first_name|escape} {$log.owner_last_name|escape}<br>{/if}<span class="small text-secondary">{$log.owner_email|escape}</span></td><td>{$log.actor_email|escape}</td>{/if}
                    <td>{if $log.action eq 'create'}<span class="badge text-bg-success">Utworzenie</span>{elseif $log.action eq 'delete'}<span class="badge text-bg-danger">Usuniecie</span>{else}<span class="badge text-bg-warning">Edycja</span>{/if}</td>
                    <td><details><summary class="small" style="cursor:pointer;">Pokaz szczegoly</summary>{if $log.old_data_json}<div class="small text-secondary mt-2">Przed:</div><pre class="small mb-2" style="white-space:pre-wrap;">{$log.old_data_json|escape}</pre>{/if}<div class="small text-secondary">Po:</div><pre class="small mb-0" style="white-space:pre-wrap;">{$log.new_data_json|escape}</pre></details></td>
                  </tr>
                {foreachelse}<tr><td colspan="{if $isWorkTimeManager}6{else}4{/if}" class="text-center text-secondary py-4">Brak zmian do wyswietlenia.</td></tr>{/foreach}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<div class="modal fade" id="workTimeModal" tabindex="-1" aria-labelledby="workTimeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="{$baseUrl}?controller=worktime&action=save" id="worktime-form">
        <div class="modal-header"><h5 class="modal-title" id="workTimeModalLabel">Dodaj wejscie / wyjscie</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button></div>
        <div class="modal-body">
          <input type="hidden" name="id" id="worktime-entry-id" value="">
          <input type="hidden" name="return_month" value="{$selectedMonth|escape}">
          <input type="hidden" name="return_user_id" value="{$selectedUserId|escape}">
          {if $isWorkTimeManager}
            <div class="mb-3"><label class="form-label" for="worktime-entry-user">Pracownik</label><select class="form-select" name="user_id" id="worktime-entry-user" required>{foreach $users as $user}<option value="{$user.id|escape}"{if $selectedUserId eq $user.id} selected{/if}>{if $user.first_name or $user.last_name}{$user.first_name|escape} {$user.last_name|escape} — {/if}{$user.email|escape}</option>{/foreach}</select></div>
          {/if}
          <div class="mb-3"><label class="form-label" for="worktime-entry-date">Data pracy</label><input type="date" class="form-control" name="work_date" id="worktime-entry-date" value="{$smarty.now|date_format:'%Y-%m-%d'}" required></div>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="worktime-entry-start">Przyszedl o</label><input type="time" class="form-control" name="start_time" id="worktime-entry-start" value="07:00" required></div>
            <div class="col-md-6"><label class="form-label" for="worktime-entry-end">Wyszedl o</label><input type="time" class="form-control" name="end_time" id="worktime-entry-end" value="16:00" required></div>
          </div>
          <div class="form-text mb-3">System sam policzy czas pracy, np. 07:00–16:00 = 9 godzin.</div>
          <div><label class="form-label" for="worktime-entry-note">Notatka</label><textarea class="form-control" name="note" id="worktime-entry-note" rows="3" maxlength="500" placeholder="Opcjonalnie: co bylo robione"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button><button type="submit" class="btn btn-primary">Zapisz czas pracy</button></div>
      </form>
    </div>
  </div>
</div>

<script>
  (function () {
    var modalTitle = document.getElementById('workTimeModalLabel');
    var idInput = document.getElementById('worktime-entry-id');
    var userInput = document.getElementById('worktime-entry-user');
    var dateInput = document.getElementById('worktime-entry-date');
    var startInput = document.getElementById('worktime-entry-start');
    var endInput = document.getElementById('worktime-entry-end');
    var noteInput = document.getElementById('worktime-entry-note');
    var addButton = document.getElementById('worktime-add-button');
    var defaultDate = '{$smarty.now|date_format:'%Y-%m-%d'|escape:'javascript'}';

    function normalizeTime(value, fallback) {
      value = value || '';
      if (/^\d{2}:\d{2}/.test(value)) return value.substring(0, 5);
      return fallback;
    }

    if (addButton) {
      addButton.addEventListener('click', function () {
        modalTitle.textContent = 'Dodaj wejscie / wyjscie';
        idInput.value = '';
        dateInput.value = defaultDate;
        startInput.value = '07:00';
        endInput.value = '16:00';
        noteInput.value = '';
      });
    }

    Array.prototype.slice.call(document.querySelectorAll('.js-worktime-edit')).forEach(function (button) {
      button.addEventListener('click', function () {
        modalTitle.textContent = 'Edytuj wejscie / wyjscie';
        idInput.value = button.getAttribute('data-id') || '';
        dateInput.value = button.getAttribute('data-date') || '';
        startInput.value = normalizeTime(button.getAttribute('data-start-time'), '07:00');
        endInput.value = normalizeTime(button.getAttribute('data-end-time'), '16:00');
        noteInput.value = button.getAttribute('data-note') || '';
        if (userInput) userInput.value = button.getAttribute('data-user-id') || '';
      });
    });

    Array.prototype.slice.call(document.querySelectorAll('.js-worktime-delete')).forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!confirm('Usunac ten wpis czasu pracy? Operacja zostanie zapisana w dzienniku.')) {
          event.preventDefault();
        }
      });
    });
  })();
</script>
