(() => {
  'use strict';

  /* Drag & drop of status groups and single statuses (tab "Statusy"). Saved as one layout via operation=status_reorder. */
  const board = document.querySelector('[data-status-board]');
  const saveForm = document.querySelector('[data-status-order-form]');
  if (!board || !saveForm) return;
  const layoutInput = saveForm.querySelector('[data-status-layout]');
  let dragged = null;
  let dirty = false;

  const statusWord = count => (count === 1 ? 'status' : count % 10 >= 2 && count % 10 <= 4 && (count % 100 < 12 || count % 100 > 14) ? 'statusy' : 'statusów');
  const groups = () => [...board.querySelectorAll(':scope > [data-status-group]')];
  const closestAfter = (items, y) => items.find(item => {
    const box = item.getBoundingClientRect();
    return y < box.top + box.height / 2;
  }) || null;

  const refresh = () => {
    groups().forEach(group => {
      const rows = group.querySelectorAll('[data-status-list] > [data-status-id]');
      const count = group.querySelector('[data-status-count]');
      if (count) count.textContent = `${rows.length} ${statusWord(rows.length)}`;
      group.classList.toggle('is-empty', rows.length === 0);
      rows.forEach(row => {
        const input = row.querySelector('input[name="group_name"]');
        if (input) input.value = group.dataset.statusGroup || '';
      });
    });
  };
  const markDirty = () => {
    dirty = true;
    saveForm.hidden = false;
    refresh();
  };

  /* Only the grip starts a drag, so inputs inside a row stay selectable. */
  board.addEventListener('pointerdown', event => {
    const handle = event.target.closest('[data-drag-handle]');
    if (!handle) return;
    const item = handle.closest('[data-status-id]') || handle.closest('[data-status-group]');
    if (item) item.draggable = true;
  });
  const release = () => board.querySelectorAll('[draggable="true"]').forEach(item => { item.draggable = false; });
  document.addEventListener('pointerup', () => { if (!dragged) release(); });

  board.addEventListener('dragstart', event => {
    const item = event.target.closest('[draggable="true"]');
    if (!item) { event.preventDefault(); return; }
    dragged = item;
    item.classList.add('is-dragging');
    board.classList.add(item.matches('[data-status-id]') ? 'is-dragging-status' : 'is-dragging-group');
    event.dataTransfer.effectAllowed = 'move';
    try { event.dataTransfer.setData('text/plain', item.dataset.statusId || item.dataset.statusGroup || ''); } catch (_) { /* older browsers */ }
  });
  board.addEventListener('dragover', event => {
    if (!dragged) return;
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    if (dragged.matches('[data-status-group]')) {
      const others = groups().filter(group => group !== dragged);
      const after = closestAfter(others, event.clientY);
      if (after !== dragged.nextElementSibling || (!after && board.lastElementChild !== dragged)) {
        if (after) board.insertBefore(dragged, after); else board.append(dragged);
        markDirty();
      }
      return;
    }
    const group = event.target.closest('[data-status-group]');
    const list = group?.querySelector('[data-status-list]');
    if (!list) return;
    const rows = [...list.querySelectorAll(':scope > [data-status-id]')].filter(row => row !== dragged);
    const after = closestAfter(rows, event.clientY);
    if (dragged.parentElement === list && (after === dragged.nextElementSibling || (!after && list.lastElementChild === dragged))) return;
    if (after) list.insertBefore(dragged, after); else list.append(dragged);
    markDirty();
  });
  board.addEventListener('drop', event => { if (dragged) event.preventDefault(); });
  board.addEventListener('dragend', () => {
    dragged?.classList.remove('is-dragging');
    board.classList.remove('is-dragging-status', 'is-dragging-group');
    dragged = null;
    release();
  });

  saveForm.addEventListener('submit', () => {
    layoutInput.value = JSON.stringify(groups().map(group => ({
      name: group.dataset.statusGroup || '',
      ids: [...group.querySelectorAll('[data-status-list] > [data-status-id]')].map(row => Number(row.dataset.statusId)),
    })).filter(group => group.ids.length));
    dirty = false;
  });
  saveForm.querySelector('[data-status-order-reset]')?.addEventListener('click', () => { dirty = false; location.reload(); });
  /* Saving a single status row would lose the unsaved order; warn before leaving the page. */
  window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
})();
