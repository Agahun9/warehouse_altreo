(() => {
  'use strict';

  /* ---------- Buttons outside the builder: order header menu and list bulk action ---------- */
  document.querySelectorAll('[data-oa-run-menu]').forEach(menu => {
    document.addEventListener('click', event => { if (menu.open && !menu.contains(event.target)) menu.open = false; });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') menu.open = false; });
    menu.querySelectorAll('a').forEach(link => link.addEventListener('click', () => { menu.open = false; }));
  });
  document.querySelectorAll('[data-oa-bulk-run]').forEach(button => button.addEventListener('click', event => {
    const select = button.form?.querySelector('[data-oa-bulk-rule]');
    const checked = button.form ? button.form.querySelectorAll('input[name="ids[]"]:checked').length : 0;
    if (!select?.value) { event.preventDefault(); select?.focus(); window.alert('Wybierz automatyzację do uruchomienia.'); return; }
    if (!checked) { event.preventDefault(); window.alert('Zaznacz zamówienia, dla których chcesz uruchomić automatyzację.'); return; }
    const option = select.selectedOptions[0];
    const warning = option?.dataset.confirm === '1' ? ' Ta automatyzacja może wystawić dokumenty, nadać przesyłki albo wysłać wiadomości.' : '';
    if (!window.confirm(`Uruchomić „${option?.textContent || ''}” dla zaznaczonych zamówień (${checked})?${warning}`)) event.preventDefault();
  }));

  const form = document.querySelector('[data-oa-form]');
  if (!form) return;

  /* ---------- Helpers ---------- */
  const readJson = selector => { try { return JSON.parse(form.querySelector(selector)?.textContent || 'null'); } catch (_) { return null; } };
  const catalog = readJson('[data-oa-catalog]') || {};
  ['triggers', 'conditions', 'actions', 'operators', 'placeholders'].forEach(key => { catalog[key] = catalog[key] || {}; });
  const ruleId = Number(form.querySelector('input[name="rule_id"]')?.value) || 0;
  const nameInput = form.querySelector('[data-oa-name]');
  const enabledInput = form.querySelector('[data-oa-enabled]');
  let uid = 0;
  let dirty = false;

  const el = (tag, attrs = {}, children = []) => {
    const node = document.createElement(tag);
    Object.entries(attrs).forEach(([key, value]) => {
      if (value === null || value === undefined || value === false) return;
      if (key === 'class') node.className = value;
      else if (key === 'text') node.textContent = value;
      else if (value === true) node.setAttribute(key, '');
      else node.setAttribute(key, String(value));
    });
    [children].flat(3).forEach(child => {
      if (child === null || child === undefined || child === false) return;
      node.append(child instanceof Node ? child : document.createTextNode(String(child)));
    });
    return node;
  };
  const icon = name => el('i', { class: `bi ${name}`, 'aria-hidden': 'true' });
  const lcFirst = text => (text.length > 1 && text[1] === text[1].toLowerCase() ? text[0].toLowerCase() + text.slice(1) : text);
  const isColor = value => typeof value === 'string' && /^#[0-9a-f]{6}$/i.test(value);
  const optionLabel = (options, value) => (options || []).find(option => String(option[0]) === String(value))?.[1] ?? String(value);
  const entries = object => Object.entries(object || {});

  const buildSelect = (options, value, attrs = {}) => {
    const select = el('select', attrs);
    const groups = new Map();
    (options || []).forEach(option => {
      const [optionValue, label, extra] = option;
      const node = el('option', { value: optionValue, text: label });
      if (String(optionValue) === String(value ?? '')) node.selected = true;
      if (extra && !isColor(extra)) {
        if (!groups.has(extra)) { const group = el('optgroup', { label: extra }); groups.set(extra, group); select.append(group); }
        groups.get(extra).append(node);
      } else {
        select.append(node);
      }
    });
    return select;
  };
  const iconButton = (name, label, disabled, onClick, extraClass = '') => {
    const button = el('button', { type: 'button', class: `oa-icon-btn ${extraClass}`.trim(), title: label, 'aria-label': label, disabled }, icon(name));
    button.addEventListener('click', onClick);
    return button;
  };
  const segment = (choices, value, onChange, label) => {
    const wrap = el('div', { class: 'oa-segment', role: 'group', 'aria-label': label });
    choices.forEach(([choice, text]) => {
      const button = el('button', { type: 'button', class: choice === value ? 'is-active' : '', 'aria-pressed': String(choice === value), text });
      button.addEventListener('click', () => onChange(choice));
      wrap.append(button);
    });
    return wrap;
  };

  /* ---------- State ---------- */
  const state = { triggers: [], conditions: [], actions: [], options: {} };
  const statusByName = (...needles) => {
    const options = catalog.conditions.status?.options || [];
    for (const needle of needles) {
      const hit = options.find(option => String(option[1]).toLowerCase().includes(needle));
      if (hit) return String(hit[0]);
    }
    return options[0] ? String(options[0][0]) : '';
  };
  const templates = {
    paid_to_pack: () => ({ name: 'Opłacone → do spakowania', triggers: ['paid'], conditions: [{ field: 'payment_state', op: 'in', value: ['paid'] }], actions: [{ type: 'set_status', params: { status_id: statusByName('spakow') } }, { type: 'add_tags', params: { tags: 'opłacone' } }], options: { run_limit: 'once' } }),
    preferred_document: () => ({ name: 'Dokument sprzedaży po opłaceniu', triggers: ['paid'], conditions: [{ field: 'payment_state', op: 'in', value: ['paid'] }, { field: 'has_receipt', op: 'is', value: 'no' }, { field: 'has_invoice', op: 'is', value: 'no' }], actions: [{ type: 'issue_preferred', params: {} }], options: { run_limit: 'once', stop_on_error: true } }),
    company_invoice: () => ({ name: 'Firma z NIP → faktura', triggers: ['order_created', 'details_changed'], conditions: [{ field: 'has_tax_id', op: 'is', value: 'yes' }, { field: 'document_preference', op: 'in', value: ['receipt'] }], actions: [{ type: 'set_document_preference', params: { kind: 'invoice' } }, { type: 'add_event', params: { text: 'Klient podał NIP — ustawiono fakturę.' } }] }),
    cod_flag: () => ({ name: 'Pobranie → oznacz zamówienie', triggers: ['order_created'], conditions: [{ field: 'cod', op: 'is', value: 'yes' }], actions: [{ type: 'add_tags', params: { tags: 'pobranie' } }, { type: 'append_note', params: { text: 'Pobranie: {kwota} {waluta} — sprawdź kwotę przed nadaniem.' } }], options: { run_limit: 'once' } }),
    ship_ready: () => ({ name: 'Gotowe do wysyłki → nadaj i drukuj', triggers: ['status'], conditions: [{ field: 'status', op: 'in', value: [statusByName('gotowe', 'wysył')] }, { field: 'has_shipment', op: 'is', value: 'no' }], actions: [{ type: 'create_shipment', params: { carrier_account_id: '0', package: 'auto' } }, { type: 'print_label', params: {} }, { type: 'publish_tracking', params: { carrier: 'auto' } }], options: { stop_on_error: true } }),
    tracking_shipped: () => ({ name: 'Numer w marketplace → Wysłane', triggers: ['tracking_sent'], conditions: [], actions: [{ type: 'set_status', params: { status_id: statusByName('wysłane') } }] }),
    delivered_done: () => ({ name: 'Doręczone → Zakończone', triggers: ['shipment_status'], conditions: [{ field: 'shipment_stage', op: 'in', value: ['delivered'] }], actions: [{ type: 'set_status', params: { status_id: statusByName('zakończ') } }] }),
    unpaid_reminder: () => ({ name: 'Nieopłacone 48 h → przypomnienie', triggers: ['scheduled'], conditions: [{ field: 'payment_state', op: 'in', value: ['unpaid'] }, { field: 'status', op: 'in', value: [statusByName('nowe')] }], actions: [{ type: 'email_customer', params: { subject: 'Przypomnienie o płatności za zamówienie {numer_zamowienia}', body: 'Dzień dobry {kupujacy},\n\nnie odnotowaliśmy jeszcze płatności {kwota} {waluta} za zamówienie {numer_zamowienia}. Jeśli płatność została już wykonana, prosimy zignorować tę wiadomość.\n\nPozdrawiamy' } }, { type: 'add_tags', params: { tags: 'przypomnienie o płatności' } }], options: { delay: { value: 48, unit: 'hours', from: 'ordered' } } }),
    abroad: () => ({ name: 'Wysyłka za granicę', triggers: ['order_created'], conditions: [{ field: 'country', op: 'not_in', value: ['PL'] }], actions: [{ type: 'add_tags', params: { tags: 'zagranica' } }, { type: 'add_event', params: { text: 'Wysyłka zagraniczna: sprawdź przewoźnika i dokumenty.' } }], options: { run_limit: 'once' } }),
    manual_packed: () => ({ name: 'Spakowane', triggers: ['manual'], conditions: [], actions: [{ type: 'set_status', params: { status_id: statusByName('gotowe', 'wysył') } }, { type: 'add_tags', params: { tags: 'spakowane' } }], options: { button_order: true, button_list: true } }),
  };
  const load = rule => {
    const options = rule.options || {};
    nameInput.value = rule.name || '';
    enabledInput.checked = rule.enabled !== false;
    state.triggers = (Array.isArray(rule.triggers) ? rule.triggers : []).filter(key => catalog.triggers[key]);
    state.conditions = (Array.isArray(rule.conditions) ? rule.conditions : []).map((condition, index) => ({ field: String(condition.field || ''), op: String(condition.op || ''), value: Array.isArray(condition.value) ? condition.value.map(String) : condition.value ?? '', join: index > 0 && (condition.join || (options.match === 'any' ? 'or' : 'and')) === 'or' ? 'or' : 'and' }));
    state.actions = (Array.isArray(rule.actions) ? rule.actions : []).map(action => ({ type: String(action.type || ''), params: { ...(action.params || {}) } }));
    state.options = {
      match: 'all',
      run_limit: options.run_limit === 'once' ? 'once' : 'every',
      button_order: Boolean(options.button_order), button_list: Boolean(options.button_list), stop_on_error: Boolean(options.stop_on_error),
      delay: { value: 24, unit: 'hours', from: 'status', ...(options.delay || {}) },
    };
    if (!state.actions.length) state.actions.push({ type: '', params: {} });
  };
  const stored = readJson('[data-oa-rule]');
  const templateKey = nameInput.dataset.template || '';
  if (stored && typeof stored === 'object') load(stored);
  else if (templates[templateKey]) load({ enabled: true, ...templates[templateKey]() });
  else load({ enabled: true });

  /* ---------- 1. Triggers ---------- */
  const triggersBox = form.querySelector('[data-oa-triggers]');
  const delayBox = form.querySelector('[data-oa-delay]');
  const renderTriggers = () => {
    const groups = new Map();
    entries(catalog.triggers).forEach(([key, definition]) => {
      if (!groups.has(definition.group)) groups.set(definition.group, []);
      groups.get(definition.group).push([key, definition]);
    });
    triggersBox.replaceChildren();
    groups.forEach((items, group) => {
      const grid = el('div', { class: 'oa-trigger-options' });
      items.forEach(([key, definition]) => {
        const input = el('input', { type: 'checkbox', value: key });
        input.checked = state.triggers.includes(key);
        const tile = el('label', { class: `oa-trigger-tile${input.checked ? ' is-checked' : ''}`, title: definition.hint }, [
          input,
          el('span', { class: 'oa-tile-icon' }, icon(definition.icon)),
          el('span', { class: 'oa-tile-text' }, [el('strong', { text: definition.label }), el('small', { text: definition.hint })]),
        ]);
        input.addEventListener('change', () => {
          state.triggers = input.checked ? [...new Set([...state.triggers, key])] : state.triggers.filter(item => item !== key);
          tile.classList.toggle('is-checked', input.checked);
          renderDelay(); renderConditions(); changed();
        });
        grid.append(tile);
      });
      triggersBox.append(el('div', { class: 'oa-trigger-group' }, [el('small', { text: group }), grid]));
    });
  };
  const renderDelay = () => {
    const visible = state.triggers.includes('scheduled');
    delayBox.hidden = !visible;
    if (!visible) return;
    const delay = state.options.delay;
    const value = el('input', { type: 'number', min: '1', max: '100000', step: '1', value: delay.value, 'aria-label': 'Liczba jednostek czasu' });
    const unit = buildSelect(entries(catalog.delay_units), delay.unit, { 'aria-label': 'Jednostka czasu' });
    const from = buildSelect(entries(catalog.delay_from), delay.from, { 'aria-label': 'Licz od' });
    value.addEventListener('input', () => { delay.value = value.value; changed(); });
    unit.addEventListener('change', () => { delay.unit = unit.value; changed(); });
    from.addEventListener('change', () => { delay.from = from.value; changed(); });
    delayBox.replaceChildren(
      el('div', { class: 'oa-delay-row' }, [icon('bi-hourglass-split'), el('span', { text: 'Uruchom, gdy minie' }), value, unit, el('span', { text: 'od' }), from]),
      el('small', { text: 'Każde zamówienie raz na każdy moment odniesienia (np. każdą zmianę statusu). Sprawdzane co minutę przez cron, maksymalnie 60 dni.' }),
    );
  };

  /* ---------- 2. Conditions ---------- */
  const conditionsBox = form.querySelector('[data-oa-conditions]');
  const matchBox = form.querySelector('[data-oa-match]');
  let openMulti = null;
  const closeMulti = () => {
    if (!openMulti) return;
    openMulti.querySelector('.oa-multi-menu').hidden = true;
    openMulti.querySelector('.oa-multi-toggle').setAttribute('aria-expanded', 'false');
    openMulti = null;
  };
  document.addEventListener('click', event => { if (openMulti && !openMulti.contains(event.target)) closeMulti(); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMulti(); });

  const defaultValue = (definition, op) => {
    if (definition.type === 'select') return [];
    if (definition.type === 'bool') return 'yes';
    if (definition.type === 'time') return ['08:00', '16:00'];
    if (definition.type === 'number' && op === 'between') return ['', ''];
    return '';
  };
  const multiSelect = (options, condition) => {
    if (!Array.isArray(condition.value)) condition.value = condition.value ? [String(condition.value)] : [];
    const wrap = el('div', { class: 'oa-multi' });
    const toggle = el('button', { type: 'button', class: 'oa-multi-toggle', 'aria-haspopup': 'listbox', 'aria-expanded': 'false', disabled: !options.length });
    const menu = el('div', { class: 'oa-multi-menu', hidden: true });
    const paint = () => {
      const selected = options.filter(option => condition.value.includes(String(option[0])));
      toggle.replaceChildren();
      if (!options.length) toggle.append(el('span', { class: 'oa-multi-placeholder', text: 'Brak dostępnych wartości' }));
      else if (!selected.length) toggle.append(el('span', { class: 'oa-multi-placeholder', text: 'Wybierz wartości…' }));
      selected.slice(0, 4).forEach(option => toggle.append(el('span', { class: 'oa-chip' }, [isColor(option[2]) ? el('i', { style: `background:${option[2]}` }) : null, option[1]])));
      if (selected.length > 4) toggle.append(el('span', { class: 'oa-chip is-more', text: `+${selected.length - 4}` }));
    };
    const list = el('div', { class: 'oa-multi-list', role: 'listbox', 'aria-multiselectable': 'true' });
    const inputs = [];
    options.forEach(option => {
      const value = String(option[0]);
      const input = el('input', { type: 'checkbox', value });
      input.checked = condition.value.includes(value);
      input.addEventListener('change', () => {
        condition.value = input.checked ? [...new Set([...condition.value, value])] : condition.value.filter(item => item !== value);
        paint(); changed();
      });
      inputs.push(input);
      list.append(el('label', { class: 'oa-multi-option' }, [input, isColor(option[2]) ? el('i', { class: 'oa-dot', style: `background:${option[2]}` }) : null, el('span', { text: option[1] })]));
    });
    const search = options.length > 8 ? el('input', { type: 'search', placeholder: 'Szukaj…', 'aria-label': 'Szukaj wartości' }) : null;
    search?.addEventListener('input', () => {
      const query = search.value.trim().toLowerCase();
      list.querySelectorAll('.oa-multi-option').forEach(row => { row.hidden = Boolean(query) && !row.textContent.toLowerCase().includes(query); });
    });
    const bulk = (checked) => {
      list.querySelectorAll('.oa-multi-option').forEach((row, index) => { if (!row.hidden) inputs[index].checked = checked; });
      condition.value = inputs.filter(input => input.checked).map(input => input.value);
      paint(); changed();
    };
    const selectAll = el('button', { type: 'button', text: 'Zaznacz widoczne' });
    const clear = el('button', { type: 'button', text: 'Wyczyść' });
    selectAll.addEventListener('click', () => bulk(true));
    clear.addEventListener('click', () => bulk(false));
    menu.append(...[search, list, el('div', { class: 'oa-multi-actions' }, [selectAll, clear])].filter(Boolean));
    toggle.addEventListener('click', () => {
      if (openMulti === wrap) { closeMulti(); return; }
      closeMulti();
      menu.hidden = false; toggle.setAttribute('aria-expanded', 'true'); openMulti = wrap;
      (search || inputs[0])?.focus();
    });
    wrap.append(toggle, menu);
    paint();
    return wrap;
  };
  const renderValue = (cell, definition, condition) => {
    cell.replaceChildren();
    cell.className = 'oa-cond-value';
    if (definition.type === 'select') { cell.append(multiSelect(definition.options || [], condition)); return; }
    if (definition.type === 'bool') {
      const select = buildSelect([['yes', 'Tak'], ['no', 'Nie']], condition.value, { 'aria-label': 'Wartość warunku' });
      select.addEventListener('change', () => { condition.value = select.value; changed(); });
      cell.append(select); return;
    }
    if (definition.type === 'time') {
      if (!Array.isArray(condition.value)) condition.value = ['08:00', '16:00'];
      cell.classList.add('is-pair');
      [0, 1].forEach(index => {
        const input = el('input', { type: 'time', value: condition.value[index], 'aria-label': index ? 'Do godziny' : 'Od godziny' });
        input.addEventListener('input', () => { condition.value[index] = input.value; changed(); });
        if (index) cell.append(el('span', { text: '–' }));
        cell.append(input);
      });
      return;
    }
    if (definition.type === 'number') {
      const unit = definition.unit === 'h' ? 'godz.' : '';
      const make = index => {
        const input = el('input', { type: 'text', inputmode: 'decimal', placeholder: unit ? 'np. 24' : 'np. 100,00', 'aria-label': 'Wartość warunku' });
        input.value = index === undefined ? condition.value : condition.value[index] ?? '';
        input.addEventListener('input', () => { if (index === undefined) condition.value = input.value; else condition.value[index] = input.value; changed(); });
        return input;
      };
      if (condition.op === 'between') {
        if (!Array.isArray(condition.value)) condition.value = ['', ''];
        cell.classList.add('is-pair');
        cell.append(make(0), el('span', { text: '–' }), make(1));
      } else {
        if (Array.isArray(condition.value)) condition.value = '';
        cell.append(make());
      }
      if (unit) cell.append(el('span', { class: 'oa-unit', text: unit }));
      return;
    }
    if (['empty', 'not_empty'].includes(condition.op)) { condition.value = ''; cell.append(el('span', { class: 'oa-cond-na', text: 'bez wartości' })); return; }
    const input = el('input', { type: 'text', maxlength: definition.type === 'list' ? 1000 : 500, placeholder: definition.type === 'list' ? 'np. wartość1, wartość2' : 'Wpisz tekst', 'aria-label': 'Wartość warunku' });
    input.value = Array.isArray(condition.value) ? '' : condition.value;
    input.addEventListener('input', () => { condition.value = input.value; changed(); });
    cell.append(input);
  };
  const conditionRow = (condition, index) => {
    const definition = catalog.conditions[condition.field];
    const row = el('div', { class: `oa-cond${definition ? '' : ' is-new'}` });
    const fieldOptions = [['', 'Wybierz, co sprawdzić…'], ...entries(catalog.conditions).map(([key, item]) => [key, item.label, item.group])];
    const field = buildSelect(fieldOptions, condition.field, { 'aria-label': `Warunek ${index + 1}` });
    field.addEventListener('change', () => {
      const next = catalog.conditions[field.value];
      condition.field = next ? field.value : '';
      condition.op = next ? Object.keys(catalog.operators[next.type] || {})[0] : '';
      condition.value = next ? defaultValue(next, condition.op) : '';
      renderConditions(); changed();
      const row = conditionsBox.querySelectorAll('.oa-cond')[index];
      row?.querySelector('.oa-cond-value input, .oa-cond-value select, .oa-multi-toggle, select + select')?.focus();
    });
    const remove = iconButton('bi-x-lg', 'Usuń warunek', false, () => { state.conditions.splice(index, 1); if (state.conditions[0]) state.conditions[0].join = 'and'; renderConditions(); changed(); }, 'is-danger');
    if (!definition) {
      row.append(field, el('span', { class: 'oa-cond-placeholder', text: 'Wybierz pole, aby ustawić warunek' }), remove);
      return row;
    }
    const operators = entries(catalog.operators[definition.type]);
    let operator;
    if (operators.length === 1) {
      condition.op = operators[0][0];
      operator = el('span', { class: 'oa-cond-op-static', text: operators[0][1] });
    } else {
      if (!operators.some(([key]) => key === condition.op)) condition.op = operators[0][0];
      operator = buildSelect(operators, condition.op, { 'aria-label': 'Operator' });
      operator.addEventListener('change', () => {
        const wasBetween = condition.op === 'between';
        condition.op = operator.value;
        if (definition.type === 'number' && wasBetween !== (condition.op === 'between')) condition.value = defaultValue(definition, condition.op);
        renderValue(valueCell, definition, condition); changed();
      });
    }
    const valueCell = el('div', { class: 'oa-cond-value' });
    renderValue(valueCell, definition, condition);
    row.append(field, operator, valueCell, remove);
    const notes = [];
    if (definition.events && !definition.events.some(event => state.triggers.includes(event))) {
      notes.push(el('span', { class: 'is-warning' }, [icon('bi-exclamation-triangle'), ` Działa tylko z wyzwalaczem: ${definition.events.map(event => catalog.triggers[event]?.label || event).join(', ')}.`]));
    }
    if (definition.hint) notes.push(el('span', {}, [icon('bi-info-circle'), ` ${definition.hint}`]));
    if (definition.unit === 'h') notes.push(el('span', {}, [icon('bi-clock'), ' Wartość w godzinach.']));
    if (notes.length) row.append(el('div', { class: 'oa-cond-hint' }, notes));
    return row;
  };
  const syncMatch = () => {
    const joins = new Set(state.conditions.slice(1).map(condition => condition.join));
    state.options.match = joins.size > 1 ? 'mixed' : joins.has('or') ? 'any' : 'all';
  };
  const renderConditions = () => {
    closeMulti();
    conditionsBox.replaceChildren();
    if (!state.conditions.length) {
      conditionsBox.append(el('div', { class: 'oa-conditions-empty' }, [icon('bi-infinity'), ' Brak warunków — reguła obejmie każde zamówienie z wybranego zdarzenia.']));
    }
    syncMatch();
    state.conditions.forEach((condition, index) => {
      if (index > 0) {
        const isOr = condition.join === 'or';
        const joiner = el('button', { type: 'button', class: `oa-cond-joiner${isOr ? ' is-or' : ''}`, title: `Kliknij, aby zmienić na ${isOr ? 'ORAZ' : 'LUB'}`, 'aria-label': `Łącznik przed warunkiem ${index + 1}: ${isOr ? 'LUB' : 'ORAZ'} — kliknij, aby zmienić`, text: isOr ? 'LUB' : 'ORAZ' });
        joiner.addEventListener('click', () => {
          condition.join = isOr ? 'and' : 'or';
          renderConditions(); changed();
          conditionsBox.querySelectorAll('.oa-cond-joiner')[index - 1]?.focus();
        });
        conditionsBox.append(joiner);
      }
      conditionsBox.append(conditionRow(condition, index));
    });
    matchBox.replaceChildren();
    matchBox.hidden = state.conditions.length < 2;
    matchBox.append(...segment([['all', 'Wszystkie'], ['any', 'Dowolny']], state.options.match, value => {
      state.conditions.forEach((condition, index) => { condition.join = index > 0 && value === 'any' ? 'or' : 'and'; });
      renderConditions(); changed();
    }, 'Łączenie warunków').childNodes);
  };
  form.querySelector('[data-oa-add-condition]')?.addEventListener('click', () => {
    syncMatch();
    state.conditions.push({ field: '', op: '', value: '', join: state.options.match === 'any' ? 'or' : 'and' });
    renderConditions(); changed();
    const selects = conditionsBox.querySelectorAll('.oa-cond > select');
    selects[selects.length - 1]?.focus();
  });

  /* ---------- 3. Actions ---------- */
  const actionsBox = form.querySelector('[data-oa-actions]');
  const defaultsFor = type => {
    const params = {};
    (catalog.actions[type]?.params || []).forEach(param => { if (param.default !== undefined) params[param.key] = param.default; });
    return params;
  };
  const placeholderPicker = (control, params, key) => {
    const picker = el('details', { class: 'oa-placeholders' }, el('summary', {}, [icon('bi-braces'), ' Wstaw dane zamówienia']));
    const list = el('div', { class: 'oa-placeholder-list' });
    entries(catalog.placeholders).forEach(([token, label]) => {
      const button = el('button', { type: 'button', title: token }, [el('code', { text: token }), el('span', { text: label })]);
      button.addEventListener('click', () => {
        const start = control.selectionStart ?? control.value.length;
        const end = control.selectionEnd ?? start;
        control.setRangeText(token, start, end, 'end');
        params[key] = control.value; control.focus(); changed();
      });
      list.append(button);
    });
    picker.append(list);
    return picker;
  };
  const paramField = (param, action, rerender) => {
    const params = action.params;
    if (params[param.key] === undefined && param.default !== undefined) params[param.key] = param.default;
    const value = params[param.key] ?? '';
    const id = `oa-param-${uid += 1}`;
    if (param.type === 'checkbox') {
      const input = el('input', { type: 'checkbox', id });
      input.checked = Boolean(value) && value !== '0';
      input.addEventListener('change', () => { params[param.key] = input.checked; changed(); });
      return el('label', { class: 'oa-param oa-param-check is-wide', for: id }, [input, el('span', { text: param.label })]);
    }
    let control;
    if (param.type === 'select') {
      let options = (param.options || []).filter(option => !(param.key === 'rule_id' && Number(option[0]) === ruleId));
      if (!options.some(option => String(option[0]) === String(value))) {
        options = [['', options.length ? 'Wybierz…' : 'Brak dostępnych opcji — skonfiguruj je najpierw'], ...options];
        if (!param.required && options.length > 1) params[param.key] = String(options[1][0]);
      }
      control = buildSelect(options, params[param.key], { id });
      control.addEventListener('change', () => { params[param.key] = control.value; changed(); if (param.key === 'carrier') rerender(); });
    } else if (param.type === 'textarea') {
      control = el('textarea', { id, rows: '5', maxlength: param.max || 5000, placeholder: param.placeholder || '' });
      control.value = value;
      control.addEventListener('input', () => { params[param.key] = control.value; changed(); });
    } else {
      const type = { number: 'number', email: 'email', url: 'url' }[param.type] || 'text';
      control = el('input', { id, type, placeholder: param.placeholder || '', maxlength: type === 'number' ? null : param.max || 500, min: type === 'number' ? param.min : null, max: type === 'number' ? param.max : null, step: type === 'number' ? '0.1' : null });
      control.value = value;
      control.addEventListener('input', () => { params[param.key] = control.value; changed(); });
    }
    const wide = param.type === 'textarea' || param.placeholders || ['tags', 'url', 'delivery', 'service', 'printer'].includes(param.key);
    const field = el('div', { class: `oa-param${wide ? ' is-wide' : ''}` }, [el('label', { for: id, text: `${param.label}${param.required ? ' *' : ''}` }), control]);
    if (param.placeholders) field.append(placeholderPicker(control, params, param.key));
    if (param.key === 'carrier_other') field.hidden = params.carrier !== 'other';
    return field;
  };
  const actionStep = (action, index) => {
    const definition = catalog.actions[action.type];
    const rerender = () => { renderActions(); };
    const typeOptions = [['', 'Wybierz efekt…'], ...entries(catalog.actions).map(([key, item]) => [key, item.label, item.group])];
    const type = buildSelect(typeOptions, action.type, { 'aria-label': `Efekt ${index + 1}` });
    type.addEventListener('change', () => { action.type = type.value; action.params = defaultsFor(type.value); renderActions(); changed(); actionsBox.querySelectorAll('.oa-step-head select')[index]?.focus(); });
    const move = offset => {
      const target = index + offset;
      [state.actions[index], state.actions[target]] = [state.actions[target], state.actions[index]];
      renderActions(); changed();
    };
    const tools = el('div', { class: 'oa-step-tools' }, [
      iconButton('bi-arrow-up', 'Przesuń krok wyżej', index === 0, () => move(-1)),
      iconButton('bi-arrow-down', 'Przesuń krok niżej', index === state.actions.length - 1, () => move(1)),
      iconButton('bi-copy', 'Duplikuj krok', !definition, () => { state.actions.splice(index + 1, 0, { type: action.type, params: JSON.parse(JSON.stringify(action.params)) }); renderActions(); changed(); }),
      iconButton('bi-trash', 'Usuń krok', false, () => { state.actions.splice(index, 1); if (!state.actions.length) state.actions.push({ type: '', params: {} }); renderActions(); changed(); }, 'is-danger'),
    ]);
    const card = el('div', { class: `oa-step-card${definition ? '' : ' is-new'}${definition?.warning ? ' is-warning' : ''}` }, [
      el('div', { class: 'oa-step-head' }, [el('span', { class: 'oa-step-type-icon' }, icon(definition?.icon || 'bi-plus-circle-dotted')), type, tools]),
    ]);
    if (definition) {
      const body = el('div', { class: 'oa-step-body' });
      definition.params.forEach(param => body.append(paramField(param, action, rerender)));
      if (!definition.params.length) body.append(el('p', { class: 'oa-step-empty', text: 'Ten efekt nie wymaga ustawień.' }));
      if (definition.hint) body.append(el('p', { class: 'oa-step-note is-hint' }, [icon('bi-info-circle'), el('span', { text: definition.hint })]));
      if (definition.warning) body.append(el('p', { class: 'oa-step-note is-warning' }, [icon('bi-exclamation-triangle'), el('span', { text: definition.warning })]));
      card.append(body);
    }
    return el('li', { class: 'oa-step' }, [el('span', { class: 'oa-step-bullet', text: index + 1 }), card]);
  };
  const renderActions = () => {
    actionsBox.replaceChildren(...state.actions.map(actionStep));
  };
  form.querySelector('[data-oa-add-action]')?.addEventListener('click', () => {
    state.actions.push({ type: '', params: {} });
    renderActions(); changed();
    const selects = actionsBox.querySelectorAll('.oa-step-head select');
    selects[selects.length - 1]?.focus();
  });

  /* ---------- 4. Settings ---------- */
  const settingsBox = form.querySelector('[data-oa-settings]');
  const optionToggle = (key, title, hint, iconName) => {
    const input = el('input', { type: 'checkbox' });
    input.checked = Boolean(state.options[key]);
    input.addEventListener('change', () => { state.options[key] = input.checked; changed(); });
    return el('label', { class: 'oa-toggle' }, [input, el('span', { class: 'oa-switch-ui', 'aria-hidden': 'true' }), el('span', { class: 'oa-toggle-text' }, [el('strong', {}, [icon(iconName), ` ${title}`]), el('small', { text: hint })])]);
  };
  const renderSettings = () => {
    settingsBox.replaceChildren(
      el('div', { class: 'oa-setting' }, [
        el('div', {}, [el('strong', { text: 'Limit wykonań' }), el('small', { text: 'Ile razy reguła może zadziałać dla jednego zamówienia. Uruchomienie z przycisku zawsze się wykona, jeśli warunki są spełnione.' })]),
        segment([['once', 'Raz na zamówienie'], ['every', 'Przy każdym zdarzeniu']], state.options.run_limit, value => { state.options.run_limit = value; renderSettings(); changed(); }, 'Limit wykonań'),
      ]),
      el('div', { class: 'oa-toggle-list' }, [
        optionToggle('button_order', 'Przycisk w szczegółach zamówienia', 'Pojawi się w menu „Automaty” w nagłówku zamówienia.', 'bi-hand-index-thumb'),
        optionToggle('button_list', 'Akcja masowa na liście zamówień', 'Uruchomisz ją dla zaznaczonych zamówień (do 50 naraz).', 'bi-list-check'),
        optionToggle('stop_on_error', 'Zatrzymaj po błędzie kroku', 'Gdy krok się nie powiedzie, kolejne efekty nie zostaną wykonane.', 'bi-sign-stop'),
      ]),
    );
  };

  /* ---------- Summary, validation, submit ---------- */
  const sentence = form.querySelector('[data-oa-sentence]');
  const errorsBox = form.querySelector('[data-oa-errors]');
  const describeCondition = condition => {
    const definition = catalog.conditions[condition.field];
    const operator = catalog.operators[definition.type]?.[condition.op] || '';
    const label = lcFirst(definition.label);
    if (definition.type === 'select') return `${label} ${operator} ${(condition.value || []).map(value => optionLabel(definition.options, value)).join(', ') || '…'}`;
    if (definition.type === 'bool') return `${label}: ${condition.value === 'yes' ? 'tak' : 'nie'}`;
    if (definition.type === 'time') return `${label} ${condition.value[0]}–${condition.value[1]}`;
    if (definition.type === 'number' && condition.op === 'between') return `${label} od ${condition.value[0] || '…'} do ${condition.value[1] || '…'}`;
    if (['empty', 'not_empty'].includes(condition.op)) return `${label} ${operator}`;
    return `${label} ${operator} ${definition.type === 'number' ? condition.value || '…' : `„${condition.value || '…'}”`}`;
  };
  const updateSentence = () => {
    const triggers = state.triggers.map(key => lcFirst(catalog.triggers[key].label));
    const conditions = state.conditions.filter(condition => catalog.conditions[condition.field]).map((condition, index) => `${index ? (condition.join === 'or' ? ' lub ' : ' oraz ') : ''}${describeCondition(condition)}`);
    const actions = state.actions.filter(action => catalog.actions[action.type]).map(action => lcFirst(catalog.actions[action.type].label));
    sentence.replaceChildren(
      el('span', { class: 'oa-sentence-label', text: 'Podsumowanie' }),
      el('span', {}, [
        'Gdy ', el('b', { class: 'is-when', text: triggers.length ? triggers.join(' lub ') : 'wybierz wyzwalacz' }),
        conditions.length ? [', jeżeli ', el('b', { class: 'is-if', text: conditions.join('') })] : ' (każde zamówienie)',
        ' → ', el('b', { class: 'is-then', text: actions.length ? actions.join(', potem ') : 'dodaj efekt' }), '.',
      ]),
    );
  };
  function changed() { dirty = true; updateSentence(); errorsBox.hidden = true; }
  nameInput.addEventListener('input', () => { dirty = true; });
  enabledInput.addEventListener('change', () => { dirty = true; });
  window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });

  form.addEventListener('submit', event => {
    const errors = [];
    const name = nameInput.value.trim();
    if (!name) errors.push('Podaj nazwę automatyzacji.');
    if (!state.triggers.length) errors.push('Wybierz przynajmniej jeden wyzwalacz.');
    if (state.triggers.includes('scheduled') && !(Number(state.options.delay.value) >= 1)) errors.push('Ustaw czas wyzwalacza „Upływ czasu”.');
    state.conditions.forEach((condition, index) => {
      const definition = catalog.conditions[condition.field];
      if (!definition) { errors.push(`Warunek ${index + 1}: wybierz, co sprawdzić, albo usuń pusty warunek.`); return; }
      const missing = definition.type === 'select' ? !condition.value.length
        : definition.type === 'number' ? (Array.isArray(condition.value) ? condition.value.some(value => String(value).trim() === '') : String(condition.value).trim() === '')
          : ['text', 'list'].includes(definition.type) && !['empty', 'not_empty'].includes(condition.op) && !String(condition.value).trim();
      if (missing) errors.push(`Warunek „${definition.label}”: uzupełnij wartość.`);
    });
    const actions = state.actions.filter(action => action.type);
    if (!actions.length) errors.push('Dodaj przynajmniej jeden efekt.');
    state.actions.forEach((action, index) => {
      if (!action.type) { if (actions.length) errors.push(`Krok ${index + 1}: wybierz efekt albo usuń pusty krok.`); return; }
      const definition = catalog.actions[action.type];
      definition.params.forEach(param => {
        if (param.required && param.type !== 'checkbox' && !String(action.params[param.key] ?? '').trim()) errors.push(`Krok ${index + 1} „${definition.label}”: uzupełnij „${param.label}”.`);
      });
    });
    if (errors.length) {
      event.preventDefault();
      errorsBox.replaceChildren(icon('bi-exclamation-octagon'), el('ul', {}, errors.slice(0, 6).map(error => el('li', { text: error }))));
      errorsBox.hidden = false;
      return;
    }
    form.querySelector('[data-oa-json]').value = JSON.stringify({ name, enabled: enabledInput.checked, triggers: state.triggers, conditions: state.conditions, actions, options: state.options });
    dirty = false;
  });

  renderTriggers(); renderDelay(); renderConditions(); renderActions(); renderSettings(); updateSentence();
  if (!nameInput.value) nameInput.focus({ preventScroll: true });
})();
