/* Szablony druku: eksport zaznaczonych zamówień z listy oraz edytor szablonów z podglądem. */
(() => {
  'use strict';
  const config = document.getElementById('om-config');

  const flash = message => {
    const node = document.createElement('div');
    node.className = 'pt-flash'; node.textContent = message;
    document.body.appendChild(node);
    setTimeout(() => node.remove(), 3200);
  };

  // Lista zamówień: eksport zaznaczonych wg szablonu.
  const bulkButton = document.querySelector('[data-pt-bulk-export]');
  bulkButton?.addEventListener('click', event => {
    const select = document.querySelector('[data-pt-bulk-template]');
    const checked = document.querySelectorAll('input[name="ids[]"]:checked').length;
    if (!select || !select.value) { event.preventDefault(); flash('Wybierz szablon druku.'); select?.focus(); return; }
    if (!checked) { event.preventDefault(); flash('Zaznacz zamówienia do eksportu.'); return; }
    // Eksport otwiera nową kartę — bieżąca strona się nie przeładowuje, więc loader musi zniknąć (starsze przeglądarki bez event.submitter).
    setTimeout(() => { if (typeof window.hidePageLoader === 'function') window.hidePageLoader(); }, 400);
  });

  // Karta zamówienia: zamknij menu drukowania po kliknięciu obok.
  document.addEventListener('click', event => {
    document.querySelectorAll('.pt-detail-menu[open], .pt-new-menu[open]').forEach(menu => { if (!menu.contains(event.target)) menu.open = false; });
  });

  document.querySelectorAll('form[data-pt-confirm]').forEach(form => form.addEventListener('submit', event => {
    if (!window.confirm(form.dataset.ptConfirm)) event.preventDefault();
  }));

  const editor = document.querySelector('[data-pt-editor]');
  if (!editor) return;

  const formatSelect = editor.querySelector('[data-pt-format]');
  const modeSelect = editor.querySelector('[data-pt-mode]');
  const columns = editor.querySelector('[data-pt-columns]');
  const frame = editor.querySelector('[data-pt-preview-frame]');
  const table = editor.querySelector('[data-pt-preview-table]');
  const status = editor.querySelector('[data-pt-preview-status]');
  const source = editor.querySelector('[data-pt-preview-source]');
  let lastTarget = editor.querySelector('textarea[name="body"]');

  const applyFormat = () => {
    const format = formatSelect.value;
    editor.querySelectorAll('[data-pt-for]').forEach(section => {
      const active = section.dataset.ptFor.split(' ').includes(format);
      section.hidden = !active;
      // Pola ukrytej sekcji nie są wysyłane, ale zostają w formularzu do ponownego przełączenia.
      section.querySelectorAll('input,select,textarea').forEach(input => { if (!input.closest('[data-pt-column-template]')) input.disabled = !active || input.dataset.readonly === '1'; });
    });
    if (format === 'csv') lastTarget = columns.querySelector('input[name="csv_value[]"]') || lastTarget;
    else lastTarget = editor.querySelector('textarea[name="body"]');
  };
  const applyMode = () => {
    editor.querySelectorAll('[data-pt-for-mode]').forEach(node => { node.hidden = modeSelect.value !== node.dataset.ptForMode; });
  };
  editor.querySelectorAll('input:disabled,select:disabled,textarea:disabled').forEach(input => { input.dataset.readonly = '1'; });
  formatSelect.addEventListener('change', () => { applyFormat(); schedulePreview(); });
  modeSelect?.addEventListener('change', applyMode);
  applyFormat(); applyMode();

  // Wstawianie pól w miejscu kursora.
  editor.addEventListener('focusin', event => { if (event.target.matches('[data-pt-target]')) lastTarget = event.target; });
  const insertText = (target, text, caretOffset) => {
    if (!target || target.disabled) { flash('Kliknij najpierw pole treści lub wartość kolumny.'); return; }
    const start = target.selectionStart ?? target.value.length;
    const end = target.selectionEnd ?? start;
    target.value = target.value.slice(0, start) + text + target.value.slice(end);
    const caret = start + (caretOffset ?? text.length);
    target.focus();
    target.setSelectionRange(caret, caret);
    target.dispatchEvent(new Event('input', { bubbles: true }));
  };
  editor.querySelectorAll('[data-pt-insert]').forEach(button => button.addEventListener('click', () => {
    const field = button.dataset.ptInsert;
    if (field.startsWith('#each ')) {
      const open = `{{${field}}}\n`;
      insertText(lastTarget, `${open}\n{{/each}}`, open.length);
    } else if (field === 'raw.') {
      insertText(lastTarget, '{{raw.}}', 6);
    } else {
      insertText(lastTarget, `{{${field}}}`);
    }
  }));

  const search = editor.querySelector('[data-pt-field-search]');
  search?.addEventListener('input', () => {
    const query = search.value.trim().toLowerCase();
    editor.querySelectorAll('.pt-field-group').forEach(group => {
      let visible = 0;
      group.querySelectorAll('.pt-field').forEach(field => {
        const match = !query || field.textContent.toLowerCase().includes(query);
        field.hidden = !match; if (match) visible++;
      });
      group.hidden = visible === 0;
      if (query && visible) group.open = true;
    });
  });

  // Kolumny CSV: dodawanie, usuwanie, przeciąganie.
  const columnTemplate = editor.querySelector('[data-pt-column-template]');
  editor.querySelector('[data-pt-add-column]')?.addEventListener('click', () => {
    const row = columnTemplate.content.firstElementChild.cloneNode(true);
    columns.appendChild(row);
    row.querySelector('input').focus();
  });
  columns?.addEventListener('click', event => {
    const remove = event.target.closest('[data-pt-remove-column]');
    if (!remove) return;
    remove.closest('[data-pt-column]').remove();
    schedulePreview();
  });
  let dragged = null;
  columns?.addEventListener('dragstart', event => {
    const row = event.target.closest('[data-pt-column]');
    if (!row) return;
    dragged = row; row.classList.add('is-dragging');
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', '');
  });
  columns?.addEventListener('dragover', event => {
    if (!dragged) return;
    event.preventDefault();
    const over = event.target.closest('[data-pt-column]');
    if (!over || over === dragged) return;
    const box = over.getBoundingClientRect();
    columns.insertBefore(dragged, event.clientY > box.top + box.height / 2 ? over.nextSibling : over);
  });
  columns?.addEventListener('dragend', () => { dragged?.classList.remove('is-dragging'); dragged = null; schedulePreview(); });

  // Podgląd na żywo (bez zapisu).
  let timer = null; let sequence = 0;
  const escapeHtml = value => String(value).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
  async function preview() {
    const current = ++sequence;
    const data = new FormData(editor);
    // Wyłączone pola (tylko podgląd) też muszą trafić do renderowania.
    editor.querySelectorAll('[data-readonly="1"]').forEach(input => {
      if (!input.name || input.closest('[hidden]') || (input.type === 'checkbox' && !input.checked)) return;
      if (input.name.endsWith('[]')) data.append(input.name, input.value); else data.set(input.name, input.value);
    });
    data.set('preview_order', source.value);
    data.set('csrf', config?.dataset.csrf || data.get('csrf') || '');
    status.classList.remove('is-error');
    status.textContent = 'Generowanie podglądu…';
    try {
      const response = await fetch(editor.dataset.previewUrl, { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const result = await response.json().catch(() => null);
      if (current !== sequence) return;
      if (!response.ok || !result) throw new Error(result?.error || `Nie udało się wygenerować podglądu (HTTP ${response.status}).`);
      if (result.rows) {
        frame.hidden = true; table.hidden = false;
        const rows = result.rows;
        const hasHeader = editor.querySelector('input[name="header_row"]')?.checked;
        const head = hasHeader && rows.length ? rows[0] : null;
        const body = head ? rows.slice(1) : rows;
        table.innerHTML = `<table>${head ? `<thead><tr>${head.map(cell => `<th>${escapeHtml(cell)}</th>`).join('')}</tr></thead>` : ''}<tbody>${body.map(row => `<tr>${row.map(cell => `<td>${escapeHtml(cell)}</td>`).join('')}</tr>`).join('')}</tbody></table>`;
        status.textContent = `${body.length} wierszy w podglądzie`;
      } else {
        table.hidden = true; frame.hidden = false;
        frame.srcdoc = result.html;
        status.textContent = 'Podgląd aktualny · w eksporcie PDF otworzy się okno druku („Zapisz jako PDF”)';
      }
    } catch (error) {
      if (current !== sequence) return;
      status.classList.add('is-error');
      status.textContent = error.message;
    }
  }
  function schedulePreview() { clearTimeout(timer); timer = setTimeout(preview, 600); }
  editor.addEventListener('input', schedulePreview);
  editor.addEventListener('change', event => { if (event.target !== source) schedulePreview(); });
  source.addEventListener('change', preview);
  editor.querySelector('[data-pt-preview-refresh]').addEventListener('click', preview);
  preview();

  // Ostrzeżenie przed utratą niezapisanych zmian.
  let dirty = false;
  editor.addEventListener('input', () => { dirty = true; });
  editor.addEventListener('submit', () => { dirty = false; });
  window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
})();
