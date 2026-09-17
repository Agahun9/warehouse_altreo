// SalesCenter – moduł integracji: okno dodawania kanału, ustawienia połączeń i postęp pobierania od daty.
(() => {
  const root = document.querySelector('[data-integrations]');
  if (!root) return;
  const dialog = root.querySelector('[data-add-dialog]');
  const picker = dialog?.querySelector('[data-picker]');
  const title = dialog?.querySelector('[data-step-title]');
  const sub = dialog?.querySelector('[data-step-sub]');

  const showPicker = () => {
    if (!dialog) return;
    dialog.querySelectorAll('[data-setup]').forEach(el => { el.hidden = true; });
    picker.hidden = false;
    title.textContent = 'Dodaj kanał sprzedaży';
    sub.textContent = 'Wybierz, skąd chcesz pobierać zamówienia.';
  };
  const showSetup = code => {
    const setup = dialog?.querySelector(`[data-setup="${CSS.escape(code)}"]`);
    if (!setup) return showPicker();
    picker.hidden = true;
    dialog.querySelectorAll('[data-setup]').forEach(el => { el.hidden = el !== setup; });
    title.textContent = `Połącz: ${setup.dataset.title}`;
    sub.textContent = setup.dataset.sub;
    setTimeout(() => setup.querySelector('input:not([type=hidden])')?.focus(), 30);
  };
  const open = code => {
    if (!dialog) return;
    code ? showSetup(code) : showPicker();
    if (!dialog.open) dialog.showModal();
  };

  root.querySelectorAll('[data-add-open]').forEach(b => b.addEventListener('click', () => open('')));
  dialog?.querySelector('[data-add-close]')?.addEventListener('click', () => dialog.close());
  dialog?.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
  dialog?.querySelectorAll('[data-pick]').forEach(b => b.addEventListener('click', () => showSetup(b.dataset.pick)));
  dialog?.querySelectorAll('[data-back]').forEach(b => b.addEventListener('click', showPicker));
  dialog?.querySelector('[data-woo-manual]')?.addEventListener('click', () => {
    dialog.querySelector('[data-woo-auto]').hidden = true; dialog.querySelector('[data-woo-keys]').hidden = false;
  });
  dialog?.querySelector('[data-woo-auto-back]')?.addEventListener('click', () => {
    dialog.querySelector('[data-woo-auto]').hidden = false; dialog.querySelector('[data-woo-keys]').hidden = true;
  });
  dialog?.querySelectorAll('[data-connect-form]').forEach(form => form.addEventListener('submit', () => {
    const button = form.querySelector('[data-connect-submit]');
    if (button) { button.disabled = true; button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sprawdzam połączenie…'; }
  }));
  if (root.dataset.openAdd) open(root.dataset.openAdd);

  // Rozwijanie ustawień połączenia.
  root.querySelectorAll('[data-toggle-conn]').forEach(button => button.addEventListener('click', () => {
    const card = button.closest('.sc-conn');
    const openNow = !card.classList.contains('is-open');
    card.classList.toggle('is-open', openNow);
    button.setAttribute('aria-expanded', String(openNow));
  }));
  const selected = document.querySelector('.sc-conn.is-open');
  if (selected) selected.scrollIntoView({ block: 'start' });

  // Kopiowanie tokenu API.
  root.querySelectorAll('[data-copy]').forEach(button => button.addEventListener('click', async () => {
    const text = button.parentElement.querySelector('[data-copy-source]')?.textContent || '';
    try { await navigator.clipboard.writeText(text); button.innerHTML = '<i class="bi bi-check2"></i> Skopiowano'; }
    catch (e) { window.prompt('Skopiuj token:', text); }
  }));

})();
