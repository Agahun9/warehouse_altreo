{assign var=selectedPriority value=$priorityDefinitions[$selectedTask.priority]|default:$priorityDefinitions.medium}
<div class="taskboard-detail-card is-animated" data-task-detail-root="1" data-task-id="{$selectedTask.id}" data-board-id="{$selectedBoard.id}">
  {if $canWriteTaskboard}
    <form method="post" action="{$baseUrl}?controller=taskboard&action=updatetask" data-taskboard-autosave-form>
      <input type="hidden" name="board_id" value="{$selectedBoard.id}">
      <input type="hidden" name="task_id" value="{$selectedTask.id}">
      <input type="hidden" name="status" value="{$selectedTask.status|escape}">

      <div class="taskboard-detail-hero">
        <label class="form-label small text-secondary mb-1" for="taskboardDetailTitle"><i class="bi bi-pencil-square"></i> Nazwa zadania</label>
        <input type="text" id="taskboardDetailTitle" name="title" class="form-control taskboard-detail-title-input" value="{$selectedTask.title|escape}" required data-task-title-input>
        <div class="taskboard-detail-badges">
          <span class="taskboard-detail-badge"><i class="bi bi-kanban"></i> {$selectedBoard.name|escape}</span>
          <span class="taskboard-detail-badge"><i class="bi bi-flag"></i> {$selectedPriority.label|escape}</span>
          <span class="taskboard-detail-badge"><i class="bi bi-person"></i> {if $selectedTask.assigned_user_first_name|default:'' neq '' or $selectedTask.assigned_user_last_name|default:'' neq ''}{$selectedTask.assigned_user_first_name|default:''|escape} {$selectedTask.assigned_user_last_name|default:''|escape}{else}{$selectedTask.assigned_user_email|default:'Bez przypisania'|escape}{/if}</span>
          <span class="taskboard-detail-badge"><i class="bi bi-clock-history"></i> {$selectedTask.created_at|default:'-'|escape}</span>
        </div>
      </div>

      <div class="taskboard-detail-toolbar">
        <div class="taskboard-detail-meta-card">
          <label class="form-label" for="taskboardDetailPriority"><i class="bi bi-flag"></i> Priorytet</label>
          <select id="taskboardDetailPriority" name="priority" class="form-select">
            {foreach $priorityDefinitions as $priorityKey => $priorityMeta}
              <option value="{$priorityKey|escape}"{if $selectedTask.priority eq $priorityKey} selected{/if}>{$priorityMeta.label|escape}</option>
            {/foreach}
          </select>
        </div>
        <div class="taskboard-detail-meta-card">
          <label class="form-label" for="taskboardDetailAssignedUser"><i class="bi bi-person"></i> Osoba</label>
          <select id="taskboardDetailAssignedUser" name="assigned_user_id" class="form-select">
            <option value="">Bez przypisania</option>
            {foreach $activeUsers as $user}
              <option value="{$user.id}"{if $selectedTask.assigned_user_id|default:0 eq $user.id} selected{/if}>
                {if $user.first_name|default:'' neq '' or $user.last_name|default:'' neq ''}{$user.first_name|default:''|escape} {$user.last_name|default:''|escape}{else}{$user.email|escape}{/if}
              </option>
            {/foreach}
          </select>
        </div>
        <div class="taskboard-detail-meta-card">
          <label class="form-label" for="taskboardDetailDueAt"><i class="bi bi-alarm"></i> Termin</label>
          <input type="datetime-local" id="taskboardDetailDueAt" name="due_at" class="form-control" value="{if $selectedTask.due_at|default:'' neq ''}{$selectedTask.due_at|date_format:'%Y-%m-%dT%H:%M'}{/if}">
        </div>
      </div>

      <div class="taskboard-subcopy small mb-3">
        Utworzone: {$selectedTask.created_at|default:'-'|escape}
        {if $selectedTask.completed_at|default:'' neq ''}<br>Zakonczone: {$selectedTask.completed_at|escape}{/if}
      </div>
      <div class="taskboard-autosave-status mb-2" data-taskboard-autosave-status>Zapis automatyczny</div>

      <div class="taskboard-detail-section">
        <div class="taskboard-section-title">
          <div class="taskboard-section-heading">
            <span class="taskboard-section-icon -green"><i class="bi bi-check2-square"></i></span>
            <h5 class="h6 mb-0">Podzadania</h5>
          </div>
          <span class="badge text-bg-light">{$subtasksByTaskId[$selectedTask.id]|@count}</span>
        </div>
        <div class="taskboard-checklist" id="taskboardChecklist">
          {if $subtasksByTaskId[$selectedTask.id]|default:false}
            {foreach $subtasksByTaskId[$selectedTask.id] as $subtask}
              <div class="taskboard-subtask-row{if $subtask.is_done|default:0} is-done{/if}" data-subtask-row="{$subtask.id}">
                <input type="checkbox" id="taskboardSubtask{$subtask.id}" class="taskboard-subtask-checkbox" data-subtask-id="{$subtask.id}" {if $subtask.is_done|default:0}checked{/if}>
                <label for="taskboardSubtask{$subtask.id}" class="taskboard-subtask-label flex-grow-1 mb-0">{$subtask.label|escape}</label>
                <button type="submit" class="btn btn-sm btn-outline-danger taskboard-item-delete" form="taskboard-subtask-delete-{$subtask.id}" title="Usun podzadanie">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            {/foreach}
          {else}
            <div class="small text-secondary">Brak checklisty dla tego zadania.</div>
          {/if}
        </div>
        <div class="input-group mt-3">
          <input type="text" id="taskboardDetailSubtaskNew" name="label" class="form-control" form="taskboard-subtask-create-{$selectedTask.id}" placeholder="Dodaj podzadanie" required>
          <button type="submit" class="btn btn-outline-primary" form="taskboard-subtask-create-{$selectedTask.id}">Dodaj</button>
        </div>
      </div>

      <div class="taskboard-detail-section">
        <div class="taskboard-section-title">
          <div class="taskboard-section-heading">
            <span class="taskboard-section-icon -blue"><i class="bi bi-card-text"></i></span>
            <h5 class="h6 mb-0">Opis</h5>
          </div>
        </div>
        <textarea id="taskboardDetailDescription" name="description" class="form-control" rows="4" placeholder="Dodaj opis, kontekst albo kroki...">{$selectedTask.description|default:''|escape}</textarea>
      </div>

      <div class="taskboard-detail-section">
        <div class="taskboard-section-title">
          <div class="taskboard-section-heading">
            <span class="taskboard-section-icon -amber"><i class="bi bi-chat-left-text"></i></span>
            <h5 class="h6 mb-0">Notatki</h5>
          </div>
          <span class="badge text-bg-light">{$notesByTaskId[$selectedTask.id]|@count}</span>
        </div>
        <textarea id="taskboardDetailNoteNew" name="note" class="form-control mb-2" rows="3" form="taskboard-note-create-{$selectedTask.id}" placeholder="Dodaj komentarz lub ustalenie..." required></textarea>
        <button type="submit" class="btn btn-outline-dark btn-sm w-100" form="taskboard-note-create-{$selectedTask.id}">Dodaj notatke</button>
        <div class="taskboard-note-list mt-3">
          {if $notesByTaskId[$selectedTask.id]|default:false}
            {foreach $notesByTaskId[$selectedTask.id] as $note}
              <div class="taskboard-note-item">
                <div class="taskboard-note-meta small mb-1 d-flex justify-content-between gap-2">
                  <span>{if $note.created_by_first_name|default:'' neq '' or $note.created_by_last_name|default:'' neq ''}{$note.created_by_first_name|default:''|escape} {$note.created_by_last_name|default:''|escape}{else}{$note.created_by_email|default:'System'|escape}{/if}</span>
                  <span class="d-inline-flex align-items-center gap-2">
                    {$note.created_at|default:'-'|escape}
                    <button type="submit" class="btn btn-sm btn-outline-danger taskboard-item-delete" form="taskboard-note-delete-{$note.id}" title="Usun notatke">
                      <i class="bi bi-trash"></i>
                    </button>
                  </span>
                </div>
                <div class="small">{$note.note|escape|nl2br}</div>
              </div>
            {/foreach}
          {else}
            <div class="small text-secondary">Brak notatek do tego zadania.</div>
          {/if}
        </div>
      </div>

      <div class="taskboard-detail-section">
        <div class="taskboard-section-title">
          <div class="taskboard-section-heading">
            <span class="taskboard-section-icon -violet"><i class="bi bi-paperclip"></i></span>
            <h5 class="h6 mb-0">Zdjecia i zalaczniki</h5>
          </div>
          <span class="badge text-bg-light">{$attachmentsByTaskId[$selectedTask.id]|@count}</span>
        </div>

        {if $attachmentsByTaskId[$selectedTask.id]|default:false}
          <div class="row g-2 mb-3">
            {foreach $attachmentsByTaskId[$selectedTask.id] as $attachment}
              {if $attachment.is_image|default:0}
                <div class="col-4">
                  <a href="./{$attachment.file_path|escape}" target="_blank" class="d-block" data-no-page-loader="1">
                    <img src="./{$attachment.file_path|escape}" alt="{$attachment.file_name|escape}" class="img-fluid rounded-3 border">
                  </a>
                </div>
              {/if}
            {/foreach}
          </div>
        {/if}

        <div class="alert alert-light border small mb-3">
          Wklej obraz przez <strong>Ctrl+V</strong> albo dodaj plik recznie.
        </div>
        <input type="file" id="taskboardDetailAttachmentNew" name="attachment" class="form-control" form="taskboard-attachment-upload-{$selectedTask.id}" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.txt" required>
        <button type="submit" class="btn btn-outline-primary btn-sm w-100 mt-2" form="taskboard-attachment-upload-{$selectedTask.id}">Dodaj plik</button>

        <div class="taskboard-attachment-list mt-3">
          {if $attachmentsByTaskId[$selectedTask.id]|default:false}
            {foreach $attachmentsByTaskId[$selectedTask.id] as $attachment}
              <div class="taskboard-attachment-item">
                <div class="flex-grow-1 min-w-0">
                  <a href="./{$attachment.file_path|escape}" target="_blank" class="taskboard-attachment-link" data-no-page-loader="1">{$attachment.file_name|truncate:44|escape}</a>
                  <div class="taskboard-attachment-meta small">{$attachment.mime_type|default:'plik'|escape} - {$attachment.file_size|default:0} B</div>
                </div>
                <button type="submit" class="btn btn-sm btn-outline-danger" form="taskboard-attachment-delete-{$attachment.id}">Usun</button>
              </div>
            {/foreach}
          {else}
            <div class="small text-secondary">Brak zalacznikow dla tego zadania.</div>
          {/if}
        </div>
      </div>

      <div class="taskboard-detail-section">
        <div class="d-grid gap-2">
          {if $selectedTask.completed_at|default:'' neq ''}
            <button
              type="button"
              class="btn btn-outline-warning"
              data-taskboard-restore
              data-task-id="{$selectedTask.id}"
              data-board-id="{$selectedBoard.id}"
            >
              <i class="bi bi-arrow-counterclockwise"></i> Przywroc z archiwum
            </button>
          {else}
            <button
              type="button"
              class="btn btn-outline-success"
              data-taskboard-done
              data-task-id="{$selectedTask.id}"
              data-board-id="{$selectedBoard.id}"
            >
              <i class="bi bi-archive"></i> Oznacz jako zrobione i przenies do archiwum
            </button>
          {/if}
        </div>
      </div>
    </form>

    <form method="post" action="{$baseUrl}?controller=taskboard&action=createsubtask" id="taskboard-subtask-create-{$selectedTask.id}" data-taskboard-subtask-form>
      <input type="hidden" name="board_id" value="{$selectedBoard.id}">
      <input type="hidden" name="task_id" value="{$selectedTask.id}">
      <input type="hidden" name="ajax" value="1">
    </form>

    {if $subtasksByTaskId[$selectedTask.id]|default:false}
      {foreach $subtasksByTaskId[$selectedTask.id] as $subtask}
        <form method="post" action="{$baseUrl}?controller=taskboard&action=deletesubtask" id="taskboard-subtask-delete-{$subtask.id}" data-taskboard-refresh-panel-form data-confirm-message="Usunac to podzadanie?">
          <input type="hidden" name="board_id" value="{$selectedBoard.id}">
          <input type="hidden" name="task_id" value="{$selectedTask.id}">
          <input type="hidden" name="subtask_id" value="{$subtask.id}">
          <input type="hidden" name="ajax" value="1">
        </form>
      {/foreach}
    {/if}

    <form method="post" action="{$baseUrl}?controller=taskboard&action=addnote" id="taskboard-note-create-{$selectedTask.id}" data-taskboard-refresh-panel-form>
      <input type="hidden" name="board_id" value="{$selectedBoard.id}">
      <input type="hidden" name="task_id" value="{$selectedTask.id}">
      <input type="hidden" name="ajax" value="1">
    </form>

    {if $notesByTaskId[$selectedTask.id]|default:false}
      {foreach $notesByTaskId[$selectedTask.id] as $note}
        <form method="post" action="{$baseUrl}?controller=taskboard&action=deletenote" id="taskboard-note-delete-{$note.id}" data-taskboard-refresh-panel-form data-confirm-message="Usunac te notatke?">
          <input type="hidden" name="board_id" value="{$selectedBoard.id}">
          <input type="hidden" name="task_id" value="{$selectedTask.id}">
          <input type="hidden" name="note_id" value="{$note.id}">
          <input type="hidden" name="ajax" value="1">
        </form>
      {/foreach}
    {/if}

    <form method="post" action="{$baseUrl}?controller=taskboard&action=uploadattachment" enctype="multipart/form-data" id="taskboard-attachment-upload-{$selectedTask.id}" data-taskboard-refresh-panel-form>
      <input type="hidden" name="board_id" value="{$selectedBoard.id}">
      <input type="hidden" name="task_id" value="{$selectedTask.id}">
      <input type="hidden" name="ajax" value="1">
    </form>

    {if $attachmentsByTaskId[$selectedTask.id]|default:false}
      {foreach $attachmentsByTaskId[$selectedTask.id] as $attachment}
        <form method="post" action="{$baseUrl}?controller=taskboard&action=deleteattachment" id="taskboard-attachment-delete-{$attachment.id}" data-taskboard-refresh-panel-form data-confirm-message="Usunac ten zalacznik?">
          <input type="hidden" name="board_id" value="{$selectedBoard.id}">
          <input type="hidden" name="task_id" value="{$selectedTask.id}">
          <input type="hidden" name="attachment_id" value="{$attachment.id}">
          <input type="hidden" name="ajax" value="1">
        </form>
      {/foreach}
    {/if}
  {else}
    <div class="taskboard-detail-hero">
      <div class="taskboard-detail-hero-title">{$selectedTask.title|escape}</div>
      <div class="taskboard-detail-badges">
        <span class="taskboard-detail-badge"><i class="bi bi-kanban"></i> {$selectedBoard.name|escape}</span>
        <span class="taskboard-detail-badge"><i class="bi bi-flag"></i> {$selectedPriority.label|escape}</span>
        <span class="taskboard-detail-badge"><i class="bi bi-person"></i> {if $selectedTask.assigned_user_first_name|default:'' neq '' or $selectedTask.assigned_user_last_name|default:'' neq ''}{$selectedTask.assigned_user_first_name|default:''|escape} {$selectedTask.assigned_user_last_name|default:''|escape}{else}{$selectedTask.assigned_user_email|default:'Bez przypisania'|escape}{/if}</span>
        <span class="taskboard-detail-badge"><i class="bi bi-clock-history"></i> {$selectedTask.created_at|default:'-'|escape}</span>
      </div>
    </div>

    <div class="taskboard-detail-section">
      <div class="taskboard-detail-toolbar">
        <div class="taskboard-detail-meta-card">
          <div class="form-label"><i class="bi bi-flag"></i> Priorytet</div>
          <div class="small fw-semibold">{$selectedPriority.label|escape}</div>
        </div>
        <div class="taskboard-detail-meta-card">
          <div class="form-label"><i class="bi bi-person"></i> Osoba</div>
          <div class="small fw-semibold">{if $selectedTask.assigned_user_first_name|default:'' neq '' or $selectedTask.assigned_user_last_name|default:'' neq ''}{$selectedTask.assigned_user_first_name|default:''|escape} {$selectedTask.assigned_user_last_name|default:''|escape}{else}{$selectedTask.assigned_user_email|default:'Bez przypisania'|escape}{/if}</div>
        </div>
        <div class="taskboard-detail-meta-card">
          <div class="form-label"><i class="bi bi-alarm"></i> Termin</div>
          <div class="small fw-semibold">{if $selectedTask.due_at|default:'' neq ''}{$selectedTask.due_at|date_format:"%d-%m-%Y %H:%M"}{else}Brak{/if}</div>
        </div>
      </div>
    </div>

    <div class="taskboard-detail-section">
      <div class="taskboard-section-title">
        <div class="taskboard-section-heading">
          <span class="taskboard-section-icon -green"><i class="bi bi-check2-square"></i></span>
          <h5 class="h6 mb-0">Podzadania</h5>
        </div>
      </div>
      <div class="taskboard-checklist">
        {if $subtasksByTaskId[$selectedTask.id]|default:false}
          {foreach $subtasksByTaskId[$selectedTask.id] as $subtask}
            <label class="taskboard-subtask-row{if $subtask.is_done|default:0} is-done{/if}">
              <input type="checkbox" class="taskboard-subtask-checkbox" {if $subtask.is_done|default:0}checked{/if} disabled>
              <span class="taskboard-subtask-label flex-grow-1">{$subtask.label|escape}</span>
            </label>
          {/foreach}
        {else}
          <div class="small text-secondary">Brak checklisty dla tego zadania.</div>
        {/if}
      </div>
    </div>

    <div class="taskboard-detail-section">
      <div class="taskboard-section-title">
        <div class="taskboard-section-heading">
          <span class="taskboard-section-icon -blue"><i class="bi bi-card-text"></i></span>
          <h5 class="h6 mb-0">Opis</h5>
        </div>
      </div>
      <div class="small">{$selectedTask.description|default:'Brak opisu.'|escape|nl2br}</div>
    </div>
  {/if}
</div>
