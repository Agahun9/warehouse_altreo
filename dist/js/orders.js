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
  const buttons = [...document.querySelectorAll('[data-sync]')];
  const result = document.getElementById('om-sync-result');
  let busy = false, polling = true;
  const endpoint = action => `index.php?controller=orders&action=${action}`;
  function showError(error) {
    result.hidden = false; result.className = 'om-alert error'; result.textContent = error.message;
    if (error.code === 'AUTH_REQUIRED') {
      polling = false;
      const login = document.createElement('a'); login.href = 'index.php?controller=auth&action=login';
      login.textContent = ' Zaloguj się'; result.append(login);
    }
    if (error.code === 'ACCESS_DENIED') polling = false;
  }
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
        : response.status === 502 || response.status === 504 ? `Serwer nie zakończył pobierania na czas (HTTP ${response.status}). Ponów dla jednego konta; zapisane zamówienia nie zostaną zdublowane.`
        : `Błąd serwera aplikacji (HTTP ${response.status}). Odpowiedź nie zawiera poprawnego wyniku synchronizacji.`;
      const error = new Error((data?.error || fallback) + (data?.reference ? ` [ID: ${data.reference}]` : ''));
      error.code = data?.code || (auth ? 'AUTH_REQUIRED' : 'SERVER_ERROR'); throw error;
    }
    return data;
  }
  async function sync(test = false, account = '', automatic = false) {
    if (busy || config.dataset.write !== '1') return;
    busy = true; buttons.forEach(b => { b.disabled = true; });
    try {
      const session = await request('session');
      config.dataset.csrf = session.csrf;
      document.querySelectorAll('input[name="csrf"]').forEach(input => { input.value = session.csrf; });
      const accounts = account ? session.accounts.filter(a => String(a.id) === account) : session.accounts.filter(a => a.enabled);
      if (automatic && !accounts.length) return;
      result.hidden = false; result.className = 'om-alert'; result.replaceChildren();
      if (!accounts.length) { result.textContent = 'Brak kont z włączonym importem. Otwórz „Konta i import”.'; return; }
      // One account per HTTP request keeps a slow integration from timing out all other accounts.
      let anyError = false;
      for (const a of accounts) {
        const line = document.createElement('div'); line.textContent = `${a.name}: pobieranie…`; result.append(line);
        try {
          const data = await request('sync', { test: test ? '1' : '', account_id: String(a.id) });
          if (!Array.isArray(data.results)) throw new Error('Niepoprawny format odpowiedzi synchronizacji.');
          line.textContent = data.results.length ? data.results.map(r => `${r.account}: ${r.message}`).join(' · ') : `${a.name}: oczekiwanie na termin ponowienia lub import wstrzymany.`;
          anyError ||= data.results.some(r => r.error);
        } catch (error) {
          if (error.code === 'AUTH_REQUIRED' || error.code === 'ACCESS_DENIED') throw error;
          anyError = true; line.textContent = `${a.name}: ${error.message}`;
        }
      }
      result.className = 'om-alert' + (anyError ? ' error' : ' success');
      const link = document.createElement('a'); link.href = window.location.href; link.textContent = ' Odśwież zamówienia'; result.append(link);
    } catch (error) { showError(error); }
    finally { busy = false; buttons.forEach(b => { b.disabled = false; }); }
  }
  buttons.forEach(b => b.addEventListener('click', () => sync(b.dataset.sync === 'test', b.dataset.account || '')));
  document.querySelectorAll('[data-order-section]').forEach(button => button.addEventListener('click', () => {
    const section = document.getElementById(button.dataset.orderSection);
    if (!section) return;
    if (section.tagName === 'DETAILS') section.open = true;
    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    section.querySelector('input,select,textarea')?.focus({ preventScroll: true });
  }));
  document.querySelectorAll('[data-confirm-shipment]').forEach(form => form.addEventListener('submit', event => {
    if (!window.confirm('Utworzyć przesyłkę u wybranego operatora? Ta operacja może naliczyć opłatę.')) event.preventDefault();
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
  document.querySelectorAll('[data-confirm-action]').forEach(form => form.addEventListener('submit', event => {
    if (!window.confirm(form.dataset.confirmAction || 'Wykonać tę operację?')) event.preventDefault();
  }));

  document.querySelectorAll('[data-auto-order-settings]').forEach(form => {
    const state = form.querySelector('[data-autosave-state]');
    const note = form.querySelector('textarea[name="note"]');
    const topNote = document.querySelector('.om-editor-note-body');
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
        if (topNote && note) {
          const paragraph = document.createElement('p');
          paragraph.textContent = note.value.trim() || 'Brak notatek do zamówienia.';
          if (!note.value.trim()) paragraph.className = 'om-muted';
          topNote.replaceChildren(paragraph);
        }
      } catch (error) {
        if (version === requestVersion) setState(error.message, 'error');
      }
    };
    const schedule = () => { clearTimeout(timer); timer = setTimeout(save, 600); };
    form.querySelector('select[name="status_id"]')?.addEventListener('change', save);
    form.querySelectorAll('input[name="tags"],textarea[name="note"]').forEach(field => {
      field.addEventListener('input', schedule);
      field.addEventListener('blur', save);
    });
    form.addEventListener('submit', event => { event.preventDefault(); save(); });
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
    ['order', 170, true], ['buyer', 190, true], ['products', 300, true], ['amount', 130, true],
    ['status', 145, true], ['payment', 130, false], ['delivery', 170, false], ['source', 135, false],
    ['tags', 160, false], ['date', 150, true]
  ];
  const defaultView = () => ({
    order: columnDefaults.map(column => column[0]),
    visible: columnDefaults.filter(column => column[2]).map(column => column[0]),
    widths: Object.fromEntries(columnDefaults.map(column => [column[0], column[1]])),
    roomy: false
  });
  const loadView = () => {
    const fallback = defaultView();
    try {
      const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
      const keys = fallback.order;
      const order = [...new Set([...(Array.isArray(saved.order) ? saved.order : []), ...keys])].filter(key => keys.includes(key));
      const visible = Array.isArray(saved.visible) ? saved.visible.filter(key => keys.includes(key)) : fallback.visible;
      return { order, visible: visible.length ? visible : ['order'], widths: { ...fallback.widths, ...(saved.widths || {}) }, roomy: Boolean(saved.roomy) };
    } catch (_) { return fallback; }
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
        const width = Math.max(90, Math.min(420, Number(view.widths[key]) || 140));
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
  document.querySelector('[data-density-toggle]')?.addEventListener('click', () => { view.roomy = !view.roomy; applyView(); saveView(); });
  document.querySelectorAll('.om-star').forEach(button => button.addEventListener('click', () => {
    button.classList.toggle('is-active');
    button.querySelector('i')?.classList.toggle('bi-star-fill');
    button.querySelector('i')?.classList.toggle('bi-star');
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
  document.querySelectorAll('[data-product-image]').forEach(image => image.addEventListener('error', () => {
    image.parentElement?.classList.add('is-missing');
  }, { once: true }));
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
    form.querySelectorAll('[data-line-quantity],[data-line-price],input[name="total"],input[name="amount_paid"],input[name="currency"],input[name="cash_on_delivery"]').forEach(field => field.addEventListener('input', updateTotals));
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
    updateTotals();
  });
  document.querySelectorAll('[data-smart-shipment]').forEach(form => {
    const preset = form.querySelector('[data-package-preset]');
    const fields = Object.fromEntries([...form.querySelectorAll('[data-package-field]')].map(field => [field.dataset.packageField, field]));
    const applyPreset = () => {
      const option = preset?.selectedOptions[0];
      if (!option || option.value === 'custom') return;
      Object.keys(fields).forEach(key => { if (option.dataset[key]) fields[key].value = option.dataset[key]; });
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
    let valuationRequest = 0, valuationTimer = 0;
    const serviceMessage = (message, icon = 'bi-info-circle') => {
      if (!serviceStatus) return;
      const symbol = document.createElement('i'); symbol.className = `bi ${icon}`;
      serviceStatus.replaceChildren(symbol, document.createTextNode(` ${message}`));
    };
    const renderServices = (data, prices = null, selectedValue = '') => {
      if (!service) return;
      service.replaceChildren();
      const provider = data.provider || '';
      if (provider === 'allegro_wza') {
        const automatic = document.createElement('option');
        automatic.value = '';
        automatic.textContent = 'Automatycznie z zamówienia Allegro';
        service.append(automatic);
      }
      const groups = new Map();
      const options = (data.options || []).filter(item => provider !== 'apaczka' || prices === null || prices[String(item.value)]);
      options.forEach(item => {
        const carrierName = item.carrier || 'Pozostałe';
        let group = groups.get(carrierName);
        if (!group) { group = document.createElement('optgroup'); group.label = carrierName; groups.set(carrierName, group); service.append(group); }
        const option = document.createElement('option'); option.value = item.value;
        const price = prices?.[String(item.value)]?.price_gross;
        option.textContent = `${item.name}${price ? ` — ${price} brutto` : ''}`; group.append(option);
      });
      const delivery = (service.dataset.delivery || '').toLowerCase();
      const preferred = provider === 'inpost_shipx' ? service.dataset.defaultInpost : provider === 'apaczka' ? service.dataset.defaultApaczka : '';
      if (selectedValue && [...service.options].some(option => option.value === selectedValue)) service.value = selectedValue;
      else if (preferred && [...service.options].some(option => option.value === preferred)) service.value = preferred;
      else if (provider !== 'allegro_wza') {
        const keyword = ['inpost','dpd','gls','dhl','ups','poczta'].find(word => delivery.includes(word));
        const match = keyword ? [...service.options].find(option => option.textContent.toLowerCase().includes(keyword)) : null;
        if (match) service.value = match.value;
      }
      service.required = provider !== 'allegro_wza';
      service.disabled = false;
      const codNote = provider === 'apaczka' && codToggle?.checked ? ' · z pobraniem' : '';
      const postcode = form.querySelector('[name="receiver_postal_code"]')?.value || '';
      serviceMessage((provider === 'allegro_wza' ? `${options.length} metod konta · automatyczna metoda zamówienia jest zalecana` : prices !== null ? `${options.length} usług dostępnych dla kodu ${postcode}` : `${options.length} usług — sprawdzam dostępność i ceny`) + codNote, prices !== null || provider === 'allegro_wza' ? 'bi-check2-circle' : 'bi-arrow-repeat');
      if (provider === 'apaczka' && prices === null) scheduleValuation();
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
      if (shippingPrice && provider !== 'apaczka') shippingPrice.textContent = 'Wycena na żywo jest dostępna dla Apaczki';
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
      if (!shippingPrice || form.dataset.provider !== 'apaczka') return;
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
        const selectedPrice = data.prices?.[String(service?.value || '')]?.price_gross;
        shippingPrice.textContent = selectedPrice ? `Cena nadania: ${selectedPrice} brutto` : 'Brak dostępnej usługi dla podanych danych';
      } catch (error) {
        if (requestId === valuationRequest) shippingPrice.textContent = `Brak wyceny: ${error.message}`;
      }
    };
    carrier?.addEventListener('change', () => { updateProvider(); loadServices(); });
    service?.addEventListener('change', () => { updateProvider(); scheduleValuation(); });
    codToggle?.addEventListener('change', updateCod);
    codAmount?.addEventListener('input', scheduleValuation);
    Object.values(fields).forEach(field => field.addEventListener('input', scheduleValuation));
    form.querySelectorAll('[name="receiver_postal_code"],[name="receiver_city"],[name="receiver_country"],[name="receiver_street"],[name="receiver_building"]').forEach(field => field.addEventListener('input', scheduleValuation));
    updateProvider();
    updateCod();
    loadServices();
  });
  applyView();

  if (config.dataset.write === '1') setInterval(() => { if (!document.hidden && polling) sync(false, '', true); }, 60000);
})();
