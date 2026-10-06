(() => {
  'use strict';
  const config = document.getElementById('om-config');
  if (!config) return;
  const all = document.getElementById('om-select-all');
  const orderChecks = [...document.querySelectorAll('input[name="ids[]"]')];
  const selectedCount = document.querySelector('[data-selected-count]');
  const updateSelected = () => {
    const count = orderChecks.filter(input => input.checked).length;
    if (selectedCount) selectedCount.textContent = `${count} zaznaczonych`;
    if (all) {
      all.indeterminate = count > 0 && count < orderChecks.length;
      all.checked = orderChecks.length > 0 && count === orderChecks.length;
    }
  };
  all?.addEventListener('change', () => { orderChecks.forEach(c => { c.checked = all.checked; }); updateSelected(); });
  orderChecks.forEach(input => input.addEventListener('change', updateSelected));
  const endpoint = action => `index.php?controller=orders&action=${action}`;
  async function request(action, body, retry = true) {
    let response;
    try {
      response = await fetch(endpoint(action), body ? {
        method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
        body: new URLSearchParams({ ...body, csrf: config.dataset.csrf })
      } : { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } });
    } catch (_) { throw new Error('Brak połączenia z serwerem aplikacji. Sprawdź sieć i ponów pobieranie.'); }
    let data;
    try { data = await response.json(); } catch (_) { data = null; }
    if (response.status === 419 && data?.code === 'CSRF_EXPIRED' && retry) {
      const session = await request('session', null, false);
      config.dataset.csrf = session.csrf;
      document.querySelectorAll('input[name="csrf"]').forEach(input => { input.value = session.csrf; });
      return request(action, body, false);
    }
    if (!response.ok || response.redirected || !data) {
      const auth = response.status === 401 || (response.redirected && response.url.includes('controller=auth'));
      const fallback = auth ? 'Sesja logowania do aplikacji wygasła.'
        : response.status === 403 ? 'Serwer odmówił dostępu (HTTP 403). Sprawdź uprawnienia lub reguły serwera.'
        : response.status === 502 || response.status === 504 ? `Serwer nie zakończył żądania na czas (HTTP ${response.status}). Ponów próbę.`
        : `Błąd serwera aplikacji (HTTP ${response.status}). Odpowiedź nie zawiera poprawnego wyniku.`;
      const error = new Error((data?.error || fallback) + (data?.reference ? ` [ID: ${data.reference}]` : ''));
      error.code = data?.code || (auth ? 'AUTH_REQUIRED' : 'SERVER_ERROR'); throw error;
    }
    return data;
  }
  document.querySelectorAll('[data-order-section]').forEach(button => button.addEventListener('click', () => {
    const section = document.getElementById(button.dataset.orderSection);
    if (!section) return;
    if (section.tagName === 'DETAILS') section.open = true;
    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    section.querySelector('input,select,textarea')?.focus({ preventScroll: true });
  }));
  // Pole z błędem w zwiniętej sekcji <details> – rozwiń ją, inaczej przeglądarka po cichu blokuje zapis.
  document.addEventListener('invalid', event => {
    let details = event.target.closest?.('details');
    while (details) { details.open = true; details = details.parentElement?.closest('details'); }
  }, true);
  document.querySelectorAll('[data-confirm-shipment]').forEach(form => form.addEventListener('submit', event => {
    const slot = form.querySelector('[data-pickup-options]:not([hidden]) [data-pickup-slot-field]:not([hidden]) [data-pickup-slot]');
    const pickup = slot?.value ? `\n\nPodjazd kuriera: ${slot.selectedOptions[0]?.textContent || slot.value}` : '';
    if (!window.confirm(`Utworzyć przesyłkę u wybranego operatora? Ta operacja może naliczyć opłatę.${pickup}`)) event.preventDefault();
  }));
  document.querySelectorAll('[data-source-shipment]').forEach(form => {
    const carrier = form.querySelector('select[name="source_carrier"]');
    const other = form.querySelector('[data-other-carrier]');
    const updateOther = () => {
      const visible = carrier?.value === 'other';
      if (other) { other.hidden = !visible; other.required = visible; }
    };
    carrier?.addEventListener('change', updateOther); updateOther();
    form.addEventListener('submit', event => {
      const label = carrier?.selectedOptions[0]?.textContent || 'wybranego przewoźnika';
      if (!window.confirm(`Przekazać numer przesyłki do źródła zamówienia jako ${label}?`)) event.preventDefault();
    });
  });
  document.querySelectorAll('[data-confirm-action]').forEach(form => form.addEventListener('submit', event => {
    if (!window.confirm(form.dataset.confirmAction || 'Potwierdzić operację?')) event.preventDefault();
  }));
  document.querySelectorAll('[data-double-confirm]').forEach(form => form.addEventListener('submit', event => {
    const [first, second] = (form.dataset.doubleConfirm || '').split('||');
    if (!window.confirm(first || 'Potwierdzić operację?')) { event.preventDefault(); return; }
    if (!window.confirm(second || 'Potwierdź ponownie, aby usunąć na trwałe.')) event.preventDefault();
  }));
  document.querySelectorAll('[data-confirm-click]').forEach(button => button.addEventListener('click', event => {
    if (!window.confirm(button.dataset.confirmClick || 'Potwierdzić operację?')) event.preventDefault();
  }));
  document.querySelectorAll('[data-series-filter-form] select').forEach(select => select.addEventListener('change', () => select.form.submit()));
  document.querySelectorAll('[data-dialog-open]').forEach(button => button.addEventListener('click', () => document.getElementById(button.dataset.dialogOpen)?.showModal()));
  document.querySelectorAll('dialog [data-dialog-close]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
  document.querySelectorAll('[data-doc-items]').forEach(container => {
    const money = value => { const n = Number(String(value ?? '').trim().replace(/\s/g, '').replace(',', '.')); return Number.isFinite(n) ? n : 0; };
    const reindex = () => container.querySelectorAll('.om-doc-item-row').forEach((row, index) => row.querySelectorAll('[name]').forEach(field => { field.name = field.name.replace(/items\[\d+\]/, `items[${index}]`); }));
    const update = () => container.querySelectorAll('.om-doc-item-row').forEach(row => {
      const qty = Math.max(0, money(row.querySelector('[data-doc-qty]')?.value));
      const price = money(row.querySelector('[data-doc-price]')?.value);
      const output = row.querySelector('[data-doc-line-total]');
      if (output) output.textContent = `${(qty * price).toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} PLN`;
    });
    container.addEventListener('input', update);
    const form = container.closest('form');
    form?.querySelector('[data-doc-add-item]')?.addEventListener('click', () => {
      const row = container.querySelector('.om-doc-item-row').cloneNode(true);
      row.querySelectorAll('input').forEach(input => { input.value = input.hasAttribute('data-doc-qty') ? '1' : input.hasAttribute('data-doc-price') ? '0.00' : ''; });
      container.append(row); reindex(); update(); row.querySelector('input').focus();
    });
    container.addEventListener('click', event => {
      const remove = event.target.closest('[data-doc-remove-item]');
      if (!remove || container.children.length === 1) return;
      remove.closest('.om-doc-item-row').remove(); reindex(); update();
    });
    update();
  });
  document.querySelectorAll('[data-auto-order-settings]').forEach(form => {
    const state = form.querySelector('[data-autosave-state]');
    let timer = 0, requestVersion = 0;
    const setState = (message, kind = '') => {
      if (!state) return;
      state.className = `om-editor-autosave-state ${kind}`.trim();
      state.innerHTML = `<i class="bi ${kind === 'error' ? 'bi-exclamation-triangle' : kind === 'saving' ? 'bi-cloud-arrow-up' : 'bi-cloud-check'}"></i> ${message}`;
    };
    const save = async () => {
      clearTimeout(timer);
      const version = ++requestVersion;
      setState('Zapisywanie…', 'saving');
      const body = Object.fromEntries(new FormData(form).entries());
      try {
        const data = await request('orderautosave', body);
        if (version !== requestVersion) return;
        setState(`Zapisano ${data.saved_at || ''}`.trim(), 'saved');
      } catch (error) {
        if (version === requestVersion) setState(error.message, 'error');
      }
    };
    const schedule = () => { clearTimeout(timer); timer = setTimeout(save, 600); };
    form.querySelector('select[name="status_id"]')?.addEventListener('change', save);
    form.querySelectorAll('input[name="tags"]').forEach(field => {
      field.addEventListener('input', schedule);
      field.addEventListener('blur', save);
    });
    form.addEventListener('submit', event => { event.preventDefault(); save(); });
  });

  document.querySelectorAll('[data-order-notes]').forEach(panel => {
    const list = panel.querySelector('[data-notes-list]');
    const count = panel.querySelector('[data-notes-count]');
    const errorBox = panel.querySelector('[data-notes-error]');
    const addForm = panel.querySelector('[data-note-add]');
    const canWrite = panel.dataset.canWrite === '1';
    let notes = [];
    try { notes = JSON.parse(panel.dataset.notes || '[]'); } catch (_) { notes = []; }
    const node = (tag, className, text) => { const el = document.createElement(tag); if (className) el.className = className; if (text !== undefined) el.textContent = text; return el; };
    const button = (icon, label, action, extra = '') => { const el = node('button', `oc-note-btn ${extra}`.trim()); el.type = 'button'; el.dataset.noteAction = action; el.title = label; el.setAttribute('aria-label', label); el.innerHTML = `<i class="bi ${icon}"></i>`; return el; };
    const showError = message => { errorBox.hidden = !message; errorBox.textContent = message || ''; };
    const sourceLabel = source => source === 'user' || source === 'legacy' || !source ? '' : source.startsWith('webhook:') ? 'Webhook' : source.startsWith('rule:') ? 'Automatyzacja' : '';
    const render = () => {
      count.textContent = String(notes.length);
      if (!notes.length) { list.replaceChildren(node('li', 'oc-notes-empty', 'Brak notatek do zamówienia.')); return; }
      list.replaceChildren(...notes.map(note => {
        const item = node('li', `oc-note-item${note.source && note.source !== 'user' && note.source !== 'legacy' ? ' is-auto' : ''}`); item.dataset.noteId = note.id;
        const meta = node('div', 'oc-note-meta');
        const origin = sourceLabel(note.source);
        if (origin) meta.append(node('span', 'oc-note-source', origin));
        const when = String(note.updated_at || note.created_at || '').slice(0, 16);
        const stamp = node('span', '', [note.author, when].filter(Boolean).join(' · ')); stamp.title = `${note.author ? note.author + ' · ' : ''}${note.updated_at || note.created_at} UTC`;
        meta.append(stamp);
        if (canWrite) { const tools = node('span', 'oc-note-tools'); tools.append(button('bi-pencil', 'Edytuj notatkę', 'edit'), button('bi-trash', 'Usuń notatkę', 'delete', 'is-danger')); meta.append(tools); }
        const body = node('p', 'oc-note-body', note.body); body.title = 'Kliknij, aby rozwinąć / zwinąć';
        item.append(meta, body);
        return item;
      }));
    };
    const send = async (payload) => {
      showError('');
      const data = await request('ordernote', { order_id: panel.dataset.orderId, ...payload });
      notes = Array.isArray(data.notes) ? data.notes : [];
      render();
    };
    addForm?.addEventListener('submit', async event => {
      event.preventDefault();
      const field = addForm.elements.body; const submit = addForm.querySelector('button');
      if (!field.value.trim()) return;
      submit.disabled = true;
      try { await send({ op: 'add', body: field.value }); field.value = ''; }
      catch (error) { showError(error.message); }
      finally { submit.disabled = false; }
    });
    addForm?.elements.body.addEventListener('keydown', event => { if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) { event.preventDefault(); addForm.requestSubmit(); } });
    list.addEventListener('click', async event => {
      const body = event.target.closest('.oc-note-body');
      if (body) { body.classList.toggle('is-open'); return; }
      const trigger = event.target.closest('[data-note-action]');
      if (!trigger) return;
      const item = trigger.closest('[data-note-id]'); const id = item.dataset.noteId;
      const note = notes.find(entry => String(entry.id) === id);
      if (!note) return;
      if (trigger.dataset.noteAction === 'delete') {
        if (!window.confirm('Usunąć tę notatkę?')) return;
        try { await send({ op: 'delete', note_id: id }); } catch (error) { showError(error.message); }
        return;
      }
      if (trigger.dataset.noteAction === 'cancel') { render(); return; }
      if (trigger.dataset.noteAction === 'edit') {
        const editor = node('textarea', 'oc-note-editor'); editor.value = note.body; editor.rows = Math.min(12, Math.max(3, note.body.split('\n').length + 1)); editor.maxLength = 10000;
        const actions = node('div', 'oc-note-edit-actions');
        const saveButton = node('button', 'oc-note-save', 'Zapisz'); saveButton.type = 'button'; saveButton.dataset.noteAction = 'save';
        const cancelButton = node('button', 'oc-note-cancel', 'Anuluj'); cancelButton.type = 'button'; cancelButton.dataset.noteAction = 'cancel';
        actions.append(saveButton, cancelButton);
        item.querySelector('.oc-note-body').replaceWith(editor); item.append(actions);
        editor.addEventListener('keydown', keyEvent => {
          if (keyEvent.key === 'Escape') render();
          if (keyEvent.key === 'Enter' && (keyEvent.ctrlKey || keyEvent.metaKey)) { keyEvent.preventDefault(); saveButton.click(); }
        });
        editor.focus();
        return;
      }
      if (trigger.dataset.noteAction === 'save') {
        const editor = item.querySelector('.oc-note-editor');
        if (!editor.value.trim()) { showError('Notatka nie może być pusta. Aby ją usunąć, użyj kosza.'); return; }
        trigger.disabled = true;
        try { await send({ op: 'update', note_id: id, body: editor.value }); } catch (error) { showError(error.message); trigger.disabled = false; }
      }
    });
    render();
  });

  const filterToggle = document.querySelector('[data-filters-toggle]');
  const filterDrawer = document.querySelector('[data-filter-drawer]');
  filterToggle?.addEventListener('click', () => {
    const open = filterDrawer.classList.toggle('is-open');
    filterToggle.setAttribute('aria-expanded', String(open));
  });

  const table = document.querySelector('[data-configurable-table]');
  const columnList = document.querySelector('[data-column-list]');
  const columnsPanel = document.querySelector('[data-columns-panel]');
  const columnsBackdrop = document.querySelector('[data-columns-backdrop]');
  const storageKey = 'altreo-orders-list-v2';
  const columnDefaults = [
    ['summary', 170, true], ['deadline', 95, false], ['order', 170, false], ['buyer', 190, false], ['deadline_buyer', 230, true], ['products', 300, true], ['amount', 130, false],
    ['status', 145, false], ['payment', 130, false], ['delivery', 170, false], ['fulfillment', 180, false], ['checkout', 200, true],
    ['source', 135, false], ['tags', 160, true], ['date', 110, false]
  ];
  const defaultView = () => ({
    order: columnDefaults.map(column => column[0]),
    visible: columnDefaults.filter(column => column[2]).map(column => column[0]),
    widths: Object.fromEntries(columnDefaults.map(column => [column[0], column[1]])),
    roomy: false
  });
  const normalizeView = saved => {
    const fallback = defaultView();
    if (!saved || typeof saved !== 'object') return fallback;
    const keys = fallback.order;
    const order = [...new Set([...(Array.isArray(saved.order) ? saved.order : []), ...keys])].filter(key => keys.includes(key));
    let visible = Array.isArray(saved.visible) ? saved.visible.filter(key => keys.includes(key)) : fallback.visible;
    // Widok zapisany przed kolumną „Zamówienie”: zastąp nią numer, status i datę, wstawiając ją w miejsce numeru.
    if (Array.isArray(saved.order) && !saved.order.includes('summary')) {
      visible = [...visible.filter(key => !['order', 'status', 'date'].includes(key)), 'summary'];
      order.splice(order.indexOf('summary'), 1);
      order.splice(Math.max(0, order.indexOf('order')), 0, 'summary');
    }
    // Nowa kolumna „Realizacja do” pojawia się zaraz za kolumną „Zamówienie”.
    if (Array.isArray(saved.order) && !saved.order.includes('deadline')) {
      if (!visible.includes('deadline')) visible.push('deadline');
      order.splice(order.indexOf('deadline'), 1);
      order.splice(order.indexOf('summary') + 1, 0, 'deadline');
    }
    // Połączone kolumny: „Realizacja do + Klient” zastępuje obie składowe, a „Kwota + dostawa + płatność” kwotę i dostawę/płatność.
    const merge = (key, parts) => {
      if (!Array.isArray(saved.order) || saved.order.includes(key)) return;
      const anchor = order.find(item => parts.includes(item) && visible.includes(item)) || parts.find(item => order.includes(item));
      order.splice(order.indexOf(key), 1);
      order.splice(anchor ? order.indexOf(anchor) : order.length, 0, key);
      visible = [...visible.filter(item => !parts.includes(item)), key];
    };
    merge('deadline_buyer', ['deadline', 'buyer']);
    merge('checkout', ['amount', 'fulfillment', 'payment', 'delivery']);
    const widths = { ...fallback.widths };
    Object.entries(saved.widths && typeof saved.widths === 'object' ? saved.widths : {}).forEach(([key, value]) => {
      if (keys.includes(key) && Number.isFinite(Number(value))) widths[key] = Math.max(40, Math.min(420, Number(value)));
    });
    return { order, visible: visible.length ? visible : ['order'], widths, roomy: Boolean(saved.roomy) };
  };
  const loadView = () => {
    try { return normalizeView(JSON.parse(localStorage.getItem(storageKey) || '{}')); } catch (_) { return defaultView(); }
  };
  let view = loadView();
  const saveView = () => {
    try { localStorage.setItem(storageKey, JSON.stringify(view)); } catch (_) { /* Private mode can block storage. */ }
  };
  const applyView = () => {
    if (!table || !columnList) return;
    const rows = table.querySelectorAll('tr');
    view.order.forEach(key => {
      const option = columnList.querySelector(`[data-column-option="${key}"]`);
      if (option) columnList.append(option);
      rows.forEach(row => {
        const cell = row.querySelector(`:scope > [data-col="${key}"]`);
        if (cell) row.append(cell);
      });
    });
    columnDefaults.forEach(([key]) => {
      const visible = view.visible.includes(key);
      table.querySelectorAll(`[data-col="${key}"]`).forEach(cell => {
        cell.hidden = !visible;
        const width = Math.max(40, Math.min(420, Number(view.widths[key]) || 140));
        cell.style.setProperty('--om-col-width', `${width}px`);
      });
      const toggle = columnList.querySelector(`[data-column-toggle="${key}"]`);
      const range = columnList.querySelector(`[data-column-width="${key}"]`);
      if (toggle) toggle.checked = visible;
      if (range) {
        range.value = String(view.widths[key]);
        const output = range.closest('[data-column-option]')?.querySelector('output');
        if (output) output.textContent = `${range.value}px`;
      }
    });
    // Zaokrąglone krawędzie wiersza-karty trafiają na pierwszą i ostatnią widoczną komórkę (ukryte kolumny zostają w DOM).
    table.querySelectorAll('tbody tr').forEach(row => {
      const cells = [...row.children].filter(cell => !cell.hidden);
      [...row.children].forEach(cell => cell.classList.remove('is-edge-first', 'is-edge-last'));
      cells[0]?.classList.add('is-edge-first');
      cells[cells.length - 1]?.classList.add('is-edge-last');
    });
    table.classList.toggle('is-roomy', view.roomy);
    const emptyCell = table.querySelector('.om-empty-row td');
    if (emptyCell) emptyCell.colSpan = view.visible.length + (all ? 1 : 0);
  };
  columnList?.querySelectorAll('[data-column-toggle]').forEach(toggle => toggle.addEventListener('change', () => {
    const key = toggle.dataset.columnToggle;
    view.visible = toggle.checked ? [...new Set([...view.visible, key])] : view.visible.filter(item => item !== key);
    if (!view.visible.length) { view.visible = ['order']; }
    applyView(); saveView();
  }));
  columnList?.querySelectorAll('[data-column-width]').forEach(range => range.addEventListener('input', () => {
    view.widths[range.dataset.columnWidth] = Number(range.value);
    applyView(); saveView();
  }));
  let draggedColumn = null;
  columnList?.addEventListener('dragstart', event => {
    draggedColumn = event.target.closest('[data-column-option]');
    draggedColumn?.classList.add('is-dragging');
  });
  columnList?.addEventListener('dragover', event => {
    event.preventDefault();
    const target = event.target.closest('[data-column-option]');
    if (!draggedColumn || !target || target === draggedColumn) return;
    const box = target.getBoundingClientRect();
    columnList.insertBefore(draggedColumn, event.clientY < box.top + box.height / 2 ? target : target.nextSibling);
  });
  columnList?.addEventListener('dragend', () => {
    draggedColumn?.classList.remove('is-dragging');
    draggedColumn = null;
    view.order = [...columnList.querySelectorAll('[data-column-option]')].map(option => option.dataset.columnOption);
    applyView(); saveView();
  });
  const openColumns = () => {
    if (!columnsPanel || !columnsBackdrop) return;
    columnsBackdrop.hidden = false;
    requestAnimationFrame(() => columnsPanel.classList.add('is-open'));
    columnsPanel.setAttribute('aria-hidden', 'false');
    document.body.classList.add('om-body-lock');
  };
  const closeColumns = () => {
    if (!columnsPanel || !columnsBackdrop) return;
    columnsPanel.classList.remove('is-open');
    columnsPanel.setAttribute('aria-hidden', 'true');
    columnsBackdrop.hidden = true;
    document.body.classList.remove('om-body-lock');
  };
  document.querySelector('[data-columns-open]')?.addEventListener('click', openColumns);
  document.querySelectorAll('[data-columns-close]').forEach(button => button.addEventListener('click', closeColumns));
  columnsBackdrop?.addEventListener('click', closeColumns);
  document.addEventListener('keydown', event => { if (event.key === 'Escape') closeColumns(); });
  document.querySelector('[data-columns-reset]')?.addEventListener('click', () => { view = defaultView(); applyView(); saveView(); });
  // Formularz „Dodaj drukarkę ręcznie”: model podpowiada domyślną nazwę i port (Posnet 6666, Novitus 6001), o ile nie zostały zmienione.
  document.querySelectorAll('[data-fiscal-protocol]').forEach(select => select.addEventListener('change', () => {
    const form = select.closest('form');
    const option = select.selectedOptions[0];
    const name = form?.querySelector('[data-fiscal-name]');
    const port = form?.querySelector('[data-fiscal-port]');
    const options = [...select.options];
    if (name && (!name.value.trim() || options.some(item => item.dataset.name === name.value))) name.value = option.dataset.name;
    if (port && (!port.value || options.some(item => item.dataset.port === port.value))) port.value = option.dataset.port;
  }));
  document.querySelector('[data-columns-export]')?.addEventListener('click', () => {
    const payload = { type: 'altreo-orders-columns', version: 1, exported_at: new Date().toISOString(), view };
    const url = URL.createObjectURL(new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = `kolumny-zamowien-${new Date().toISOString().slice(0, 10)}.json`;
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  });
  document.querySelector('[data-columns-import]')?.addEventListener('change', async event => {
    const input = event.target;
    const file = input.files?.[0];
    input.value = '';
    if (!file) return;
    try {
      const data = JSON.parse(await file.text());
      const saved = data && data.type === 'altreo-orders-columns' ? data.view : data;
      if (!saved || typeof saved !== 'object' || !Array.isArray(saved.order) || !Array.isArray(saved.visible)) throw new Error('Plik nie zawiera ustawień kolumn listy zamówień.');
      view = normalizeView(saved);
      applyView(); saveView();
    } catch (error) {
      window.alert(error instanceof SyntaxError ? 'Nieprawidłowy plik JSON.' : (error.message || 'Nie udało się wczytać ustawień kolumn.'));
    }
  });
  document.querySelector('[data-density-toggle]')?.addEventListener('click', () => { view.roomy = !view.roomy; applyView(); saveView(); });
  const paintStar = (orderId, starred) => {
    document.querySelectorAll(`.om-star[data-star-order="${orderId}"]`).forEach(star => {
      star.classList.toggle('is-active', starred);
      star.setAttribute('aria-pressed', starred ? 'true' : 'false');
      star.title = starred ? 'Usuń gwiazdkę' : 'Oznacz gwiazdką';
      const icon = star.querySelector('i');
      icon?.classList.toggle('bi-star-fill', starred);
      icon?.classList.toggle('bi-star', !starred);
      star.closest('tr')?.classList.toggle('is-starred', starred);
      const scope = star.closest('[data-star-scope]');
      scope?.classList.toggle('is-starred', starred);
      const banner = scope?.querySelector('[data-star-banner]');
      if (banner) banner.hidden = !starred;
    });
  };
  document.querySelectorAll('[data-star-order]').forEach(button => button.addEventListener('click', async event => {
    event.preventDefault(); event.stopPropagation();
    if (button.disabled || button.dataset.busy) return;
    const orderId = button.dataset.starOrder;
    const star = document.querySelector(`.om-star[data-star-order="${orderId}"]`);
    const starred = button.hasAttribute('data-star-remove') ? false : !(star || button).classList.contains('is-active');
    button.dataset.busy = '1';
    paintStar(orderId, starred);
    try {
      const data = await request('orderstar', { order_id: orderId, starred: starred ? '1' : '' });
      paintStar(orderId, !!data.starred);
    } catch (error) {
      paintStar(orderId, !starred);
      window.alert(error.message || 'Nie udało się zapisać gwiazdki.');
    } finally { delete button.dataset.busy; }
  }));
  const copyText = async value => {
    if (navigator.clipboard && window.isSecureContext) { await navigator.clipboard.writeText(value); return; }
    const field = document.createElement('textarea');
    field.value = value; field.setAttribute('readonly', ''); field.style.position = 'fixed'; field.style.opacity = '0';
    document.body.append(field); field.select();
    if (!document.execCommand('copy')) { field.remove(); throw new Error('copy failed'); }
    field.remove();
  };
  document.querySelectorAll('[data-copy-order]').forEach(button => button.addEventListener('click', async () => {
    const label = button.querySelector('span');
    const original = label?.textContent || '';
    try {
      await copyText(button.dataset.copyOrder || '');
      button.classList.add('is-copied');
      if (label) label.textContent = 'Skopiowano';
      window.setTimeout(() => { button.classList.remove('is-copied'); if (label) label.textContent = original; }, 1400);
    } catch (_) {
      if (label) label.textContent = 'Nie udało się';
      window.setTimeout(() => { if (label) label.textContent = original; }, 1600);
    }
  }));
  document.querySelectorAll('[data-copy-target]').forEach(button => button.addEventListener('click', async () => {
    const label = button.querySelector('span');
    const original = label?.textContent || '';
    try {
      await copyText(document.querySelector(button.dataset.copyTarget)?.textContent || '');
      if (label) label.textContent = 'Skopiowano';
    } catch (_) {
      if (label) label.textContent = 'Nie udało się';
    }
    window.setTimeout(() => { if (label) label.textContent = original; }, 1600);
  }));
  const rowIgnores = event => event.target.closest('.om-select-cell, .om-star, [data-copy-order], a, button, input, select, textarea, label');
  document.querySelectorAll('tr[data-order-url]').forEach(row => {
    // Kółko myszy (środkowy przycisk) otwiera zamówienie w nowej karcie.
    row.addEventListener('mousedown', event => { if (event.button === 1 && !rowIgnores(event)) event.preventDefault(); });
    row.addEventListener('auxclick', event => {
      if (event.button !== 1 || rowIgnores(event)) return;
      event.preventDefault();
      window.open(row.dataset.orderUrl, '_blank', 'noopener');
    });
  });
  document.querySelectorAll('tr[data-order-url]').forEach(row => row.addEventListener('click', event => {
    if (event.target.closest('.om-select-cell, .om-star, [data-copy-order], input, select, textarea, label')) return;
    if (event.target.closest('.om-order-number') || window.getSelection()?.toString()) return;
    event.preventDefault();
    if (event.metaKey || event.ctrlKey) { window.open(row.dataset.orderUrl, '_blank'); return; }
    window.location.href = row.dataset.orderUrl;
  }));
  document.querySelectorAll('[data-flag-image]').forEach(image => image.addEventListener('error', () => { image.remove(); }, { once: true }));
  document.querySelectorAll('[data-product-image]').forEach(image => image.addEventListener('error', () => {
    image.parentElement?.classList.add('is-missing');
  }, { once: true }));
  (() => {
    const thumbs = [...document.querySelectorAll('tr[data-order-url] .om-product-thumb')].filter(thumb => thumb.querySelector('img'));
    if (!thumbs.length || !window.matchMedia('(hover: hover)').matches) return;
    const card = document.createElement('div');
    card.className = 'om-thumb-preview'; card.setAttribute('aria-hidden', 'true');
    card.innerHTML = '<div class="om-thumb-preview-image"><img alt=""></div><div class="om-thumb-preview-info"><span data-p="order"></span><strong data-p="buyer"></strong><p data-p="product"></p><small data-p="note"></small></div>';
    document.body.append(card);
    const image = card.querySelector('img');
    const field = name => card.querySelector(`[data-p="${name}"]`);
    let showTimer = 0; let hideTimer = 0; let current = null;
    const place = thumb => {
      const rect = thumb.getBoundingClientRect();
      const width = card.offsetWidth; const height = card.offsetHeight; const gap = 12;
      let left = rect.right + gap;
      if (left + width > window.innerWidth - 8) left = Math.max(8, rect.left - width - gap);
      let top = rect.top + rect.height / 2 - height / 2;
      top = Math.max(8, Math.min(top, window.innerHeight - height - 8));
      card.style.left = `${left}px`; card.style.top = `${top}px`;
      // Karta „wyrasta” z miniatury.
      card.style.transformOrigin = `${rect.left + rect.width / 2 - left}px ${rect.top + rect.height / 2 - top}px`;
    };
    const show = thumb => {
      const row = thumb.closest('tr[data-order-url]'); const source = thumb.querySelector('img');
      if (!row || !source || thumb.classList.contains('is-missing')) return;
      current = thumb;
      image.src = source.currentSrc || source.src;
      field('order').textContent = row.dataset.previewOrder || '';
      field('buyer').textContent = row.dataset.previewBuyer || '';
      field('product').textContent = thumb.parentElement?.querySelector('strong')?.textContent || '';
      const note = row.dataset.previewNote || '';
      field('note').textContent = note; field('note').hidden = note === '';
      card.classList.remove('is-open'); card.style.display = 'block';
      place(thumb);
      requestAnimationFrame(() => { if (current === thumb) card.classList.add('is-open'); });
    };
    const hide = () => {
      current = null; card.classList.remove('is-open');
      window.clearTimeout(hideTimer);
      hideTimer = window.setTimeout(() => { if (!current) card.style.display = 'none'; }, 180);
    };
    thumbs.forEach(thumb => {
      thumb.addEventListener('mouseenter', () => { window.clearTimeout(showTimer); window.clearTimeout(hideTimer); showTimer = window.setTimeout(() => show(thumb), 120); });
      thumb.addEventListener('mouseleave', () => { window.clearTimeout(showTimer); hide(); });
    });
    window.addEventListener('scroll', () => { if (current) hide(); }, { passive: true, capture: true });
  })();
  document.querySelectorAll('[data-new-order-form]').forEach(form => {
    const items = form.querySelector('[data-new-items]');
    const currency = form.querySelector('[data-new-currency]');
    const shipping = form.querySelector('[data-new-shipping]');
    const totalInput = form.querySelector('[data-new-total-input]');
    const money = value => { const number = Number(String(value || '0').replace(/\s/g, '').replace(',', '.')); return Number.isFinite(number) ? number : 0; };
    const formatted = value => `${value.toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency.value}`;
    const update = () => {
      let total = money(shipping.value);
      items.querySelectorAll('.om-new-item').forEach(row => {
        const line = Math.max(0, money(row.querySelector('[data-new-qty]').value)) * Math.max(0, money(row.querySelector('[data-new-price]').value));
        total += line; row.querySelector('[data-new-line-total]').textContent = formatted(line);
      });
      totalInput.value = total.toFixed(2); form.querySelector('[data-new-total]').textContent = formatted(total);
    };
    const reindex = () => items.querySelectorAll('.om-new-item').forEach((row, index) => row.querySelectorAll('[name]').forEach(field => { field.name = field.name.replace(/items\[\d+\]/, `items[${index}]`); }));
    items.addEventListener('input', update); shipping.addEventListener('input', update); currency.addEventListener('change', update);
    form.querySelector('[data-add-new-item]').addEventListener('click', () => {
      const row = items.querySelector('.om-new-item').cloneNode(true);
      row.querySelectorAll('input').forEach(input => { input.value = input.hasAttribute('data-new-qty') ? '1' : input.hasAttribute('data-new-price') ? '0.00' : ''; });
      items.append(row); reindex(); update(); row.querySelector('input').focus();
    });
    items.addEventListener('click', event => {
      const remove = event.target.closest('[data-remove-new-item]');
      if (!remove || items.children.length === 1) return;
      remove.closest('.om-new-item').remove(); reindex(); update();
    });
    const payment = form.querySelector('[data-new-payment]');
    const cod = form.querySelector('[data-new-cod]');
    payment.addEventListener('change', () => { cod.checked = payment.selectedOptions[0]?.dataset.cod === '1'; });
    cod.addEventListener('change', () => { if (cod.checked) { const option = payment.querySelector('option[data-cod="1"]'); if (option) payment.value = option.value; } });
    update();
  });
  document.querySelectorAll('[data-inline-order-form]').forEach(form => {
    const money = value => {
      const parsed = Number(String(value ?? '').trim().replace(/\s/g, '').replace(',', '.'));
      return Number.isFinite(parsed) ? parsed : 0;
    };
    const currencyField = form.querySelector('input[name="currency"]');
    const formatMoney = value => `${value.toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${(currencyField?.value || 'PLN').trim().toUpperCase()}`;
    const updateTotals = () => {
      form.querySelectorAll('.om-inline-product-row').forEach(row => {
        const quantity = Math.max(0, money(row.querySelector('[data-line-quantity]')?.value));
        const price = money(row.querySelector('[data-line-price]')?.value);
        const output = row.querySelector('[data-line-total]');
        if (output) output.textContent = formatMoney(quantity * price);
      });
      const total = Math.max(0, money(form.querySelector('input[name="total"]')?.value));
      const paid = Math.max(0, money(form.querySelector('input[name="amount_paid"]')?.value));
      const due = Math.max(0, total - paid);
      const cod = form.querySelector('input[name="cash_on_delivery"]')?.checked;
      const totalOutput = form.querySelector('[data-order-total]');
      const paidOutput = form.querySelector('[data-order-paid]');
      const dueOutput = form.querySelector('[data-order-due]');
      const dueLabel = form.querySelector('[data-order-due-label]');
      const dueBox = form.querySelector('[data-order-due-box]');
      if (totalOutput) totalOutput.textContent = formatMoney(total);
      if (paidOutput) paidOutput.textContent = formatMoney(paid);
      if (dueOutput) dueOutput.textContent = formatMoney(due);
      if (dueLabel) dueLabel.textContent = cod ? 'Do pobrania' : 'Do zapłaty';
      dueBox?.classList.toggle('is-due', due > 0);
    };
    form.addEventListener('input', event => {
      if (event.target.closest('[data-line-quantity],[data-line-price]') || event.target.matches('input[name="total"],input[name="amount_paid"],input[name="currency"],input[name="cash_on_delivery"]')) updateTotals();
    });
    const documentPreference = form.querySelector('[data-document-preference]');
    const invoiceFields = form.querySelector('[data-invoice-fields]');
    const documentCallout = form.querySelector('[data-document-callout]');
    const updateDocumentPreference = () => {
      const invoice = documentPreference?.value === 'invoice';
      if (invoiceFields) invoiceFields.hidden = !invoice;
      if (documentCallout) {
        documentCallout.classList.toggle('invoice', invoice);
        documentCallout.classList.toggle('receipt', !invoice);
        const icon = documentCallout.querySelector('i');
        if (icon) icon.className = `bi ${invoice ? 'bi-file-earmark-check' : 'bi-receipt-cutoff'}`;
        const title = documentCallout.querySelector('strong');
        if (title) title.textContent = invoice ? 'Faktura wymagana' : 'Paragon';
      }
    };
    documentPreference?.addEventListener('change', updateDocumentPreference);
    form.querySelector('[data-copy-delivery]')?.addEventListener('click', () => {
      const pairs = { invoice_name: 'shipping_name', invoice_street: 'shipping_street', invoice_building: 'shipping_building', invoice_postal_code: 'shipping_postal_code', invoice_city: 'shipping_city', invoice_country: 'shipping_country' };
      Object.entries(pairs).forEach(([target, source]) => {
        const field = form.querySelector(`[name="${target}"]`);
        const value = form.querySelector(`[name="${source}"]`)?.value ?? '';
        if (!field || field.value === value) return;
        field.value = value;
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
    const setOrderField = (name, value) => {
      const field = form.querySelector(`[name="${name}"]`);
      if (!field || field.value === value) return false;
      field.value = value;
      field.dispatchEvent(new Event('input', { bubbles: true }));
      field.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    };
    form.querySelector('[data-copy-billing-to-delivery]')?.addEventListener('click', () => {
      const billing = name => form.querySelector(`[name="invoice_${name}"]`)?.value.trim() || '';
      const name = billing('name') || billing('company');
      if (!name && !billing('street') && !billing('city')) return;
      setOrderField('shipping_name', name);
      ['street', 'building', 'postal_code', 'city', 'country'].forEach(key => setOrderField(`shipping_${key}`, billing(key)));
    });
    const lookupInvoice = form.querySelector('[data-lookup-invoice]');
    const lookupResult = form.querySelector('[data-lookup-invoice-result]');
    lookupInvoice?.addEventListener('click', async () => {
      const raw = form.querySelector('[name="invoice_nip"]')?.value || '';
      const nip = raw.trim().replace(/^PL/i, '').replace(/\D/g, '');
      if (!/^\d{10}$/.test(nip)) { lookupResult.textContent = 'Podaj 10 cyfr NIP.'; lookupResult.classList.add('is-error'); return; }
      lookupInvoice.disabled = true;
      lookupResult.classList.remove('is-error');
      lookupResult.textContent = 'Sprawdzam rejestry…';
      try {
        const response = await fetch(`index.php?controller=orders&action=companylookup&nip=${encodeURIComponent(nip)}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.company) throw new Error(data.error || `Błąd HTTP ${response.status}`);
        const company = data.company;
        let changed = 0;
        const mapped = { street: 'street', building: 'building', postal_code: 'postal_code', city: 'city', country: 'country' };
        Object.entries(mapped).forEach(([target, source]) => {
          const value = String(company[source] || '').trim();
          if (value && setOrderField(`invoice_${target}`, value)) changed++;
        });
        const person = [company.first_name, company.last_name].filter(Boolean).join(' ').trim();
        if (!form.querySelector('[name="invoice_company"]')?.value.trim() && company.company && setOrderField('invoice_company', String(company.company).trim())) changed++;
        if (!form.querySelector('[name="invoice_name"]')?.value.trim() && person && setOrderField('invoice_name', person)) changed++;
        if (setOrderField('invoice_nip', nip)) changed++;
        const source = (company.sources || []).join(' + ') || 'rejestr';
        lookupResult.textContent = `${changed ? `Uzupełniono ${changed} ${changed === 1 ? 'pole' : changed < 5 ? 'pola' : 'pól'}.` : 'Dane bez zmian.'} Źródło: ${source}.`;
      } catch (error) {
        lookupResult.textContent = error.message || 'Nie udało się pobrać danych.';
        lookupResult.classList.add('is-error');
      } finally {
        lookupInvoice.disabled = false;
      }
    });

    const productsTable = form.querySelector('[data-products-table]');
    // Nazwa produktu zawija się i rośnie w dół, żeby zawsze była widoczna w całości (bez nowych linii).
    const fitProductName = field => { if (!field.offsetParent) return; field.style.height = 'auto'; field.style.height = `${field.scrollHeight + 2}px`; };
    const fitProductNames = () => form.querySelectorAll('[data-product-name]').forEach(fitProductName);
    form.addEventListener('keydown', event => { if (event.key === 'Enter' && event.target.matches('[data-product-name]')) event.preventDefault(); });
    form.addEventListener('input', event => {
      const field = event.target.closest('[data-product-name]');
      if (!field) return;
      if (/[\r\n]/.test(field.value)) field.value = field.value.replace(/\s*[\r\n]+\s*/g, ' ');
      fitProductName(field);
    });
    window.addEventListener('resize', fitProductNames);
    requestAnimationFrame(fitProductNames);
    const rowTemplate = productsTable?.querySelector('[data-product-row-template]');
    const reindexProductRows = () => {
      productsTable?.querySelectorAll('.om-inline-product-row').forEach((row, index) => {
        row.querySelectorAll('[name]').forEach(field => { field.name = field.name.replace(/items\[[^\]]*\]/, `items[${index}]`); });
      });
    };
    form.querySelector('[data-add-product-row]')?.addEventListener('click', () => {
      if (!rowTemplate || !productsTable) return;
      const row = rowTemplate.content.firstElementChild.cloneNode(true);
      const shippingRow = productsTable.querySelector('.oc-product-shipping');
      if (shippingRow) shippingRow.before(row); else productsTable.append(row);
      reindexProductRows();
      updateTotals();
      row.querySelector('[data-product-name]')?.focus();
      fitProductNames();
    });
    productsTable?.addEventListener('click', event => {
      const remove = event.target.closest('[data-remove-product-row]');
      if (!remove) return;
      if (productsTable.querySelectorAll('.om-inline-product-row').length <= 1) return;
      remove.closest('.om-inline-product-row')?.remove();
      reindexProductRows();
      updateTotals();
    });

    const dataGrid = form.querySelector('#om-data-grid');
    const dataSavebar = form.querySelector('[data-data-savebar]');
    if (dataGrid && dataSavebar) {
      const revealDataSavebar = () => { dataSavebar.hidden = false; };
      dataGrid.addEventListener('input', revealDataSavebar);
      dataGrid.addEventListener('change', revealDataSavebar);
    }

    updateTotals();
  });
  document.querySelectorAll('[data-preset-list]').forEach(list => {
    const template = list.closest('form').querySelector('[data-preset-template]');
    const addButton = list.closest('.om-preset-sizes').querySelector('[data-preset-add]');
    let next = list.querySelectorAll('[data-preset-row]').length;
    const syncRemove = () => {
      const rows = list.querySelectorAll('[data-preset-row]');
      rows.forEach(row => { row.querySelector('[data-preset-remove]').disabled = rows.length < 2; });
      addButton.hidden = rows.length >= 30;
    };
    addButton.addEventListener('click', () => {
      const index = `n${next++}`;
      const row = template.content.firstElementChild.cloneNode(true);
      row.querySelectorAll('[name]').forEach(field => { field.name = field.name.replace('__i__', index); });
      list.append(row);
      row.querySelector('.om-preset-name').focus();
      syncRemove();
    });
    list.addEventListener('click', event => {
      const remove = event.target.closest('[data-preset-remove]');
      if (!remove || list.querySelectorAll('[data-preset-row]').length < 2) return;
      remove.closest('[data-preset-row]').remove();
      syncRemove();
    });
    syncRemove();
  });
  // Mapowanie metod dostawy: poprawnie zmapowane wiersze są domyślnie ukryte.
  document.querySelectorAll('[data-map-toggle]').forEach(button => {
    const section = button.closest('.om-delivery-map');
    button.addEventListener('click', () => {
      const show = button.getAttribute('aria-expanded') !== 'true';
      section?.querySelectorAll('[data-map-ok]').forEach(row => { row.hidden = !show; });
      section?.querySelectorAll('[data-map-allok]').forEach(row => { row.hidden = show; });
      button.setAttribute('aria-expanded', show ? 'true' : 'false');
      button.querySelector('span').textContent = show ? button.dataset.labelHide : button.dataset.labelShow;
      button.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
  });
  // Mapowanie metod dostawy: lista kurierów/usług wybranego konta (np. Apaczka → DPD, DHL, InPost…).
  const mapServiceCache = new Map();
  document.querySelectorAll('[data-delivery-map]').forEach(form => {
    const carrier = form.querySelector('[data-map-carrier]');
    const service = form.querySelector('[data-map-service]');
    if (!carrier || !service) return;
    let current = service.dataset.current || '', loadId = 0;
    const load = async () => {
      const accountId = Number(carrier.value);
      const id = ++loadId;
      if (accountId < 1) { service.replaceChildren(new Option(accountId < 0 ? '—' : 'Usługa wg zamówienia', '')); service.disabled = true; return; }
      service.disabled = true;
      service.replaceChildren(new Option('Pobieram usługi…', current));
      try {
        if (!mapServiceCache.has(accountId)) mapServiceCache.set(accountId, request(`shippingoptions&order_id=${encodeURIComponent(form.dataset.orderId || '0')}&carrier_account_id=${accountId}`).catch(error => { mapServiceCache.delete(accountId); throw error; }));
        const data = await mapServiceCache.get(accountId);
        if (id !== loadId) return;
        const options = [new Option(data.automatic || 'Wybierz kuriera / usługę…', '')];
        const groups = new Map();
        (data.options || []).forEach(item => {
          const label = item.carrier || 'Usługi';
          if (!groups.has(label)) { const group = document.createElement('optgroup'); group.label = label; groups.set(label, group); options.push(group); }
          groups.get(label).append(new Option(item.name, item.value, false, String(item.value) === current));
        });
        if (current && !(data.options || []).some(item => String(item.value) === current)) options.push(new Option(`Zapisana usługa ${current}`, current, false, true));
        service.replaceChildren(...options);
        service.value = current;
      } catch (error) {
        if (id !== loadId) return;
        service.replaceChildren(new Option(current ? `Zapisana usługa ${current}` : 'Nie udało się pobrać usług', current));
        service.title = error.message;
      }
      service.disabled = false;
    };
    carrier.addEventListener('change', () => { current = ''; load(); });
    service.addEventListener('change', () => { current = service.value; });
    load();
  });
  document.querySelectorAll('[data-smart-shipment]').forEach(form => {
    const preset = form.querySelector('[data-package-preset]');
    const fields = Object.fromEntries([...form.querySelectorAll('[data-package-field]')].map(field => [field.dataset.packageField, field]));
    const applyPreset = () => {
      const option = preset?.selectedOptions[0];
      if (!option || option.value === 'custom') return;
      Object.keys(fields).forEach(key => { if (option.dataset[key]) fields[key].value = option.dataset[key]; });
      // Programowa zmiana wartości nie wywołuje zdarzenia input, więc wycenę trzeba odświeżyć ręcznie.
      scheduleValuation();
    };
    preset?.addEventListener('change', applyPreset);
    Object.values(fields).forEach(field => field.addEventListener('input', () => { if (preset) preset.value = 'custom'; }));
    const carrier = form.querySelector('[data-carrier-select]');
    const service = form.querySelector('[data-shipping-service]');
    const serviceStatus = form.querySelector('[data-shipping-service-status]');
    const codToggle = form.querySelector('[data-cod-toggle]');
    const codAmount = form.querySelector('[data-cod-amount]');
    const shippingPrice = form.querySelector('[data-shipping-price]');
    const orderId = form.querySelector('input[name="order_id"]')?.value || '';
    const serviceCache = new Map();
    let serviceRequest = 0;
    let valuationRequest = 0, valuationTimer = 0, lastPrices = null;
    const pickupBox = form.querySelector('[data-pickup-options]');
    const pickupMode = form.querySelector('[data-pickup-mode]');
    const pickupSlot = form.querySelector('[data-pickup-slot]');
    const pickupSlotField = form.querySelector('[data-pickup-slot-field]');
    const pickupStatus = form.querySelector('[data-pickup-status]');
    const pickupDay = new Intl.DateTimeFormat('pl-PL', { weekday: 'long', day: 'numeric', month: 'long' });
    let pickupRequest = 0, pickupSupported = false;
    const pickupNote = text => { if (pickupStatus) pickupStatus.textContent = text; };
    // Apaczka: termin podjazdu wybierany przy tworzeniu przesyłki (API nie pozwala zamówić kuriera później).
    const loadPickup = async () => {
      if (!pickupBox || !pickupSlot) return;
      const requestId = ++pickupRequest;
      pickupBox.hidden = !pickupSupported;
      if (pickupMode) pickupMode.disabled = !pickupSupported;
      const courier = pickupSupported && pickupMode?.value === 'COURIER';
      if (pickupSlotField) pickupSlotField.hidden = !courier;
      pickupSlot.disabled = true; pickupSlot.required = false;
      if (!courier) { pickupNote(pickupMode?.value === 'SELF' ? 'Kurier nie przyjedzie – paczkę zaniesiesz do punktu.' : 'Kurier tylko dla usług, które go wymagają (najbliższy termin).'); return; }
      if (!service?.value) { pickupSlot.replaceChildren(new Option('Najpierw wybierz usługę', '')); pickupNote('Terminy pobiorę po wyborze usługi.'); return; }
      pickupSlot.replaceChildren(new Option('Pobieram terminy…', '')); pickupNote('Pobieram wolne terminy z Apaczki…');
      try {
        const data = await request('shippingpickup', { order_id: orderId, carrier_account_id: carrier?.value || '', shipping_service: service.value });
        if (requestId !== pickupRequest) return;
        const slots = data.slots || [];
        pickupSlot.replaceChildren(...(slots.length ? slots.map(slot => {
          const day = new Date(`${slot.date}T12:00:00`);
          return new Option(`${Number.isNaN(day.getTime()) ? slot.date : pickupDay.format(day)} · ${slot.hours_from}–${slot.hours_to}`, `${slot.date}|${slot.hours_from}|${slot.hours_to}`);
        }) : [new Option('Brak wolnych terminów', '')]));
        pickupNote(slots.length ? `${slots.length} ${slots.length === 1 ? 'termin' : 'terminy'} do wyboru` : 'Apaczka nie zwróciła terminów dla tej usługi.');
      } catch (error) {
        if (requestId !== pickupRequest) return;
        pickupSlot.replaceChildren(new Option('Nie udało się pobrać terminów', ''));
        pickupNote(error.message);
      }
      // Pusty wybór blokuje wysłanie formularza, żeby nie utworzyć przesyłki bez zamówionego podjazdu.
      pickupSlot.disabled = false; pickupSlot.required = true;
    };
    const serviceMessage = (message, icon = 'bi-info-circle') => {
      if (!serviceStatus) return;
      const symbol = document.createElement('i'); symbol.className = `bi ${icon}`;
      serviceStatus.replaceChildren(symbol, document.createTextNode(` ${message}`));
    };
    const renderServices = (data, prices = null, selectedValue = '') => {
      if (!service) return;
      service.replaceChildren();
      const valuation = !!data.valuation;
      form.dataset.valuation = valuation ? '1' : '';
      if (data.automatic) {
        const automatic = document.createElement('option');
        automatic.value = '';
        automatic.textContent = data.automatic;
        service.append(automatic);
      }
      const groups = new Map();
      const options = (data.options || []).filter(item => !valuation || prices === null || prices[String(item.value)]);
      options.forEach(item => {
        const carrierName = item.carrier || 'Pozostałe';
        let group = groups.get(carrierName);
        if (!group) { group = document.createElement('optgroup'); group.label = carrierName; groups.set(carrierName, group); service.append(group); }
        const option = document.createElement('option'); option.value = item.value;
        const price = prices?.[String(item.value)]?.price_gross;
        option.textContent = `${item.name}${price ? ` — ${price} brutto` : ''}`; group.append(option);
      });
      const delivery = (service.dataset.delivery || '').toLowerCase();
      const preferred = data.preferred || '';
      if (selectedValue && [...service.options].some(option => option.value === selectedValue)) service.value = selectedValue;
      else if (preferred && [...service.options].some(option => option.value === preferred)) service.value = preferred;
      else if (!data.automatic) {
        const keyword = ['inpost','dpd','gls','dhl','ups','poczta'].find(word => delivery.includes(word));
        const match = keyword ? [...service.options].find(option => option.textContent.toLowerCase().includes(keyword)) : null;
        if (match) service.value = match.value;
      }
      service.required = !data.automatic;
      service.disabled = false;
      const codNote = data.cod === 'form' && codToggle?.checked ? ' · z pobraniem' : data.cod === 'order' ? ' · pobranie wg zamówienia' : '';
      const postcode = form.querySelector('[name="receiver_postal_code"]')?.value || '';
      serviceMessage((data.automatic ? `${options.length} metod konta · automatyczna metoda zamówienia jest zalecana` : !valuation ? `${options.length} dostępnych usług` : prices !== null ? `${options.length} usług dostępnych dla kodu ${postcode}` : `${options.length} usług — sprawdzam dostępność i ceny`) + codNote, prices !== null || !valuation ? 'bi-check2-circle' : 'bi-arrow-repeat');
      if (valuation && prices === null) scheduleValuation();
      if (!valuation && shippingPrice) shippingPrice.textContent = 'Ten operator nie udostępnia wyceny na żywo';
      pickupSupported = !!data.pickup;
      if (pickupBox && (pickupBox.hidden === pickupSupported || (pickupMode?.value === 'COURIER' && service.value !== pickupSlot?.dataset.service))) { if (pickupSlot) pickupSlot.dataset.service = service.value; loadPickup(); }
    };
    const loadServices = async () => {
      if (!service) return;
      const accountId = carrier?.value || '';
      const requestId = ++serviceRequest;
      if (!accountId) { service.replaceChildren(new Option('Najpierw wybierz konto', '')); service.disabled = true; serviceMessage('Lista zależy od wybranego konta'); return; }
      service.disabled = true; service.replaceChildren(new Option('Pobieram dostępne usługi…', '')); serviceMessage('Pobieram listę z konta operatora…', 'bi-arrow-repeat');
      try {
        let data = serviceCache.get(accountId);
        if (!data) { data = await request(`shippingoptions&order_id=${encodeURIComponent(orderId)}&carrier_account_id=${encodeURIComponent(accountId)}`); serviceCache.set(accountId, data); }
        if (requestId !== serviceRequest) return;
        renderServices(data);
      } catch (error) {
        if (requestId !== serviceRequest) return;
        service.replaceChildren(new Option('Nie udało się pobrać usług', '')); service.disabled = false;
        serviceMessage(error.message, 'bi-exclamation-triangle');
      }
    };
    const updateProvider = () => {
      const provider = carrier?.selectedOptions[0]?.dataset.provider || '';
      form.dataset.provider = provider;
      form.dataset.valuation = '';
      pickupSupported = false; loadPickup();
      if (shippingPrice) shippingPrice.textContent = provider ? 'Pobieram usługi operatora…' : 'Wybierz konto i usługę';
    };
    const updateCod = () => {
      if (codAmount) { codAmount.disabled = !codToggle?.checked; codAmount.required = !!codToggle?.checked; }
      scheduleValuation();
    };
    const scheduleValuation = () => {
      clearTimeout(valuationTimer);
      valuationTimer = setTimeout(loadValuation, 350);
    };
    const loadValuation = async () => {
      if (!shippingPrice || !form.dataset.valuation) return;
      const requestId = ++valuationRequest;
      shippingPrice.textContent = 'Sprawdzam dostępne usługi i ceny…';
      const body = Object.fromEntries(new FormData(form).entries());
      body.shipping_service = '';
      try {
        const data = await request('shippingvaluation', body);
        if (requestId !== valuationRequest) return;
        const accountId = carrier?.value || '';
        const definitions = serviceCache.get(accountId);
        const selectedValue = service?.value || '';
        if (definitions) renderServices(definitions, data.prices || {}, selectedValue);
        lastPrices = data.prices || {};
        showSelectedPrice();
      } catch (error) {
        if (requestId === valuationRequest) shippingPrice.textContent = `Brak wyceny: ${error.message}`;
      }
    };
    const showSelectedPrice = () => {
      if (!shippingPrice || !form.dataset.valuation || lastPrices === null) return;
      const selectedPrice = lastPrices[String(service?.value || '')]?.price_gross;
      shippingPrice.textContent = selectedPrice ? `Cena nadania: ${selectedPrice} brutto` : 'Brak dostępnej usługi dla podanych danych';
    };
    carrier?.addEventListener('change', () => { lastPrices = null; valuationRequest++; clearTimeout(valuationTimer); updateProvider(); loadServices(); });
    // Wycena nie zależy od wybranej usługi (zwraca ceny wszystkich), więc przy zmianie usługi tylko pokazujemy cenę.
    service?.addEventListener('change', showSelectedPrice);
    service?.addEventListener('change', () => { if (pickupSlot) pickupSlot.dataset.service = service.value; loadPickup(); });
    pickupMode?.addEventListener('change', () => { if (pickupSlot) pickupSlot.dataset.service = service?.value || ''; loadPickup(); });
    codToggle?.addEventListener('change', updateCod);
    codAmount?.addEventListener('input', scheduleValuation);
    Object.values(fields).forEach(field => field.addEventListener('input', scheduleValuation));
    form.querySelectorAll('[name="receiver_postal_code"],[name="receiver_city"],[name="receiver_country"],[name="receiver_street"],[name="receiver_building"]').forEach(field => field.addEventListener('input', scheduleValuation));
    updateProvider();
    updateCod();
    loadServices();
  });
  applyView();

  // Układ karty zamówienia: kolejność bloków i kart danych (przeciągnij i upuść), zapisywana na koncie użytkownika.
  (() => {
    const root = document.querySelector('[data-order-layout]');
    const toggle = root?.querySelector('[data-layout-toggle]');
    if (!root || !toggle) return;
    const MAIN = ['om-notes', 'om-inline-data', 'om-messages', 'om-shipping', 'om-documents', 'om-payment-info', 'om-automation', 'om-history', 'om-raw-debug'];
    const grid = root.querySelector('#om-data-grid');
    const groups = {
      main: { container: root, items: () => [...root.children].filter(el => MAIN.includes(el.id)), key: el => el.id },
      cards: { container: grid, items: () => grid ? [...grid.querySelectorAll(':scope > [data-layout-card]')] : [], key: el => el.dataset.layoutCard }
    };
    const defaults = { main: groups.main.items(), cards: groups.cards.items() };
    let saved = {};
    try { saved = JSON.parse(root.dataset.orderLayout || '{}') || {}; } catch (_) { saved = {}; }
    const apply = (name, order) => {
      const group = groups[name];
      const present = defaults[name].filter(el => el.isConnected);
      if (!group.container || present.length < 2) return;
      const byKey = new Map(present.map(el => [group.key(el), el]));
      const final = (Array.isArray(order) ? order : []).map(key => byKey.get(key)).filter(Boolean);
      // Bloki spoza zapisanego układu (np. nowe) trafiają za swojego domyślnego poprzednika.
      present.forEach((el, index) => {
        if (final.includes(el)) return;
        const before = present.slice(0, index).reverse().find(prev => final.includes(prev));
        final.splice(before ? final.indexOf(before) + 1 : 0, 0, el);
      });
      const marker = document.createComment('order-layout');
      group.container.insertBefore(marker, group.items()[0] || null);
      final.forEach(el => group.container.insertBefore(el, marker));
      marker.remove();
    };
    apply('main', saved.main); apply('cards', saved.cards);

    let bar = null; let dragged = null; let saveTimer = null;
    const current = name => groups[name].items().map(groups[name].key);
    const save = (extra = {}) => {
      clearTimeout(saveTimer);
      if (bar) bar.querySelector('[data-layout-status]').textContent = 'Zapisywanie…';
      saveTimer = setTimeout(async () => {
        try {
          await request('orderlayout', { main: current('main').join(','), cards: current('cards').join(','), ...extra });
          if (bar) bar.querySelector('[data-layout-status]').textContent = 'Zapisano na Twoim koncie';
        } catch (error) {
          if (bar) bar.querySelector('[data-layout-status]').textContent = error.message || 'Nie udało się zapisać układu.';
        }
      }, 250);
    };
    const groupOf = el => (el.matches('[data-layout-card]') ? 'cards' : 'main');
    const movable = () => [...groups.main.items(), ...groups.cards.items()];
    const onDragStart = event => {
      event.stopPropagation();
      dragged = event.currentTarget;
      dragged.classList.add('is-dragging');
      event.dataTransfer.effectAllowed = 'move';
      try { event.dataTransfer.setData('text/plain', ''); } catch (_) { /* Starsze przeglądarki. */ }
    };
    const onDragOver = event => {
      if (!dragged) return;
      const target = event.currentTarget;
      if (groupOf(target) !== groupOf(dragged)) return;
      event.preventDefault(); event.stopPropagation();
      event.dataTransfer.dropEffect = 'move';
      if (target === dragged || target.contains(dragged)) return;
      const rect = target.getBoundingClientRect();
      const after = groupOf(target) === 'cards'
        ? (event.clientY > rect.bottom - 8 || (event.clientY >= rect.top && event.clientX > rect.left + rect.width / 2))
        : event.clientY > rect.top + rect.height / 2;
      const parent = target.parentNode;
      if (after) { if (target.nextSibling !== dragged) parent.insertBefore(dragged, target.nextSibling); }
      else if (target.previousSibling !== dragged) parent.insertBefore(dragged, target);
    };
    const onDragEnd = event => {
      event.stopPropagation();
      if (!dragged) return;
      dragged.classList.remove('is-dragging');
      dragged = null;
      save();
    };
    const stopToggle = event => { if (root.classList.contains('oc-layout-editing')) event.preventDefault(); };
    const setEditing = on => {
      root.classList.toggle('oc-layout-editing', on);
      toggle.classList.toggle('is-active', on);
      movable().forEach(el => {
        el.draggable = on;
        ['dragstart', 'dragover', 'dragend'].forEach((type, i) => el[on ? 'addEventListener' : 'removeEventListener'](type, [onDragStart, onDragOver, onDragEnd][i]));
      });
      root.querySelectorAll('.oc-disclosure > summary').forEach(summary => summary[on ? 'addEventListener' : 'removeEventListener']('click', stopToggle));
      if (on && !bar) {
        bar = document.createElement('div');
        bar.className = 'oc-layout-bar';
        bar.innerHTML = '<i class="bi bi-arrows-move"></i><span><strong>Tryb układu</strong> Przeciągnij bloki i karty danych w wybrane miejsce.</span><em data-layout-status></em><button type="button" class="om-btn om-small" data-layout-reset><i class="bi bi-arrow-counterclockwise"></i> Domyślny</button><button type="button" class="om-btn om-primary om-small" data-layout-done><i class="bi bi-check2"></i> Gotowe</button>';
        bar.querySelector('[data-layout-done]').addEventListener('click', () => setEditing(false));
        bar.querySelector('[data-layout-reset]').addEventListener('click', () => {
          apply('main', defaults.main.map(groups.main.key)); apply('cards', defaults.cards.map(groups.cards.key));
          save({ reset: '1' });
        });
        root.querySelector('.oc-header')?.after(bar);
      }
      if (bar) bar.hidden = !on;
    };
    toggle.addEventListener('click', () => setEditing(!root.classList.contains('oc-layout-editing')));
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && root.classList.contains('oc-layout-editing')) setEditing(false); });
  })();

  // Czytnik kodów kreskowych (klawiatura): naklejka z „Zbierania” ma kod ESP:o:<id> — skan otwiera zamówienie.
  (() => {
    const pattern = /ESP\W?o\W?(\d+)$/i;
    let buffer = '', first = 0, last = 0, target = null, idleTimer = null;
    const reset = () => { buffer = ''; first = 0; target = null; clearTimeout(idleTimer); };
    const tryOpen = () => {
      const match = buffer.match(pattern);
      // Skaner wpisuje cały kod w ułamku sekundy; ręczne pisanie jest dużo wolniejsze.
      const fast = buffer.length >= 6 && (last - first) / (buffer.length - 1) < 45;
      if (!match || !fast) return false;
      if (target && 'value' in target && typeof target.value === 'string') {
        const index = target.value.lastIndexOf(buffer);
        if (index >= 0) target.value = target.value.slice(0, index) + target.value.slice(index + buffer.length);
      }
      reset();
      window.location.href = `?controller=orders&id=${match[1]}`;
      return true;
    };
    document.addEventListener('keydown', event => {
      if (event.ctrlKey || event.metaKey || event.altKey) return;
      const now = performance.now();
      if (event.key === 'Enter' || event.key === 'Tab') {
        if (buffer && tryOpen()) { event.preventDefault(); event.stopPropagation(); }
        reset();
        return;
      }
      if (event.key.length !== 1) return;
      if (buffer && now - last > 100) reset();
      if (!buffer) { first = now; target = event.target; }
      buffer += event.key; last = now;
      // Skanery bez sufiksu Enter: otwórz po krótkiej przerwie od ostatniego znaku.
      clearTimeout(idleTimer);
      idleTimer = setTimeout(() => { if (!tryOpen()) reset(); }, 150);
    }, true);
  })();

})();
