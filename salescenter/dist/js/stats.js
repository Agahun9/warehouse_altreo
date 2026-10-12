/* SalesCenter – Statystyki sprzedaży: filtry, pobieranie raportu JSON i wykresy (SVG/HTML, bez bibliotek). */
(function () {
  'use strict';

  var root = document.querySelector('[data-st-root]');
  if (!root) { return; }

  var SVG_NS = 'http://www.w3.org/2000/svg';
  var MONTHS = ['sty', 'lut', 'mar', 'kwi', 'maj', 'cze', 'lip', 'sie', 'wrz', 'paź', 'lis', 'gru'];
  var MONTHS_FULL = ['styczeń', 'luty', 'marzec', 'kwiecień', 'maj', 'czerwiec', 'lipiec', 'sierpień', 'wrzesień', 'październik', 'listopad', 'grudzień'];
  var DAYS = ['Pn', 'Wt', 'Śr', 'Cz', 'Pt', 'So', 'Nd'];
  var DAYS_FULL = ['poniedziałek', 'wtorek', 'środa', 'czwartek', 'piątek', 'sobota', 'niedziela'];
  var DAYS_IN = ['w poniedziałki', 'we wtorki', 'w środy', 'w czwartki', 'w piątki', 'w soboty', 'w niedziele'];
  var METRICS = {
    units: { label: 'Sztuki', unit: 'szt.' },
    orders: { label: 'Zamówienia', unit: 'zam.' },
    revenue: { label: 'Przychód', unit: '' }
  };
  var GROUP_LABELS = { day: 'dziennie', week: 'tygodniowo', month: 'miesięcznie' };
  var PRESETS = ['today', 'yesterday', '7d', '30d', '90d', 'month', 'prev_month', 'year'];
  var SLOT_COUNT = 7;
  var ACCOUNT_BAR_LIMIT = 10;
  var PRODUCT_STEP = 15;

  var intFormat = new Intl.NumberFormat('pl-PL', { maximumFractionDigits: 0, useGrouping: 'always' });
  var oneFormat = new Intl.NumberFormat('pl-PL', { minimumFractionDigits: 1, maximumFractionDigits: 1, useGrouping: 'always' });
  var twoFormat = new Intl.NumberFormat('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2, useGrouping: 'always' });

  var options = parseJson((document.getElementById('st-options') || {}).textContent, { accounts: [], statuses: [], today: '' });
  var endpoint = root.getAttribute('data-endpoint') || 'index.php?controller=stats&action=data';
  var storeKey = 'sc-stats-' + (root.getAttribute('data-tenant') || '0');
  var body = root.querySelector('[data-st-body]');
  var errorBox = root.querySelector('[data-st-error]');
  var tip = root.querySelector('[data-st-tip]');
  var state = { preset: '30d', from: '', to: '', accounts: [], statuses: null, paid: '', currency: '', group: 'auto', metric: 'units' };
  var report = null;
  var requestId = 0;
  var loadTimer = null;
  var colorOf = {};
  var accountSort = { key: 'value', dir: -1 };
  var productSort = { key: 'units', dir: -1 };
  var productLimit = PRODUCT_STEP;
  var productQuery = '';
  var timelineWidth = 0;

  /* ---------- narzędzia ---------- */

  function parseJson(value, fallback) {
    try { return JSON.parse(value || '') || fallback; } catch (e) { return fallback; }
  }

  function q(selector) { return root.querySelector(selector); }

  function clear(node) { while (node.firstChild) { node.removeChild(node.firstChild); } return node; }

  /** Element HTML; teksty zawsze przez textContent (nazwy kont i produktów pochodzą z zewnątrz). */
  function el(tag, attrs, children) {
    var node = document.createElement(tag), key;
    for (key in (attrs || {})) { if (attrs[key] !== null && attrs[key] !== undefined) { node.setAttribute(key, attrs[key]); } }
    append(node, children);
    return node;
  }

  function append(node, children) {
    if (children === null || children === undefined) { return node; }
    if (!Array.isArray(children)) { children = [children]; }
    children.forEach(function (child) {
      if (child === null || child === undefined || child === false) { return; }
      node.appendChild(typeof child === 'object' ? child : document.createTextNode(String(child)));
    });
    return node;
  }

  function svg(tag, attrs, text) {
    var node = document.createElementNS(SVG_NS, tag), key;
    for (key in (attrs || {})) { node.setAttribute(key, attrs[key]); }
    if (text !== undefined) { node.textContent = text; }
    return node;
  }

  function sum(values) { return values.reduce(function (total, value) { return total + value; }, 0); }

  function maxOf(values) { return values.reduce(function (max, value) { return value > max ? value : max; }, 0); }

  function rowValue(row, metric) { return metric === 'revenue' ? (row.revenue_cents || 0) : (row[metric] || 0); }

  function money(cents, currency) { return twoFormat.format(cents / 100) + ' ' + (currency || report.currency); }

  function formatMetric(value, metric) {
    return metric === 'revenue' ? money(value) : intFormat.format(value) + ' ' + METRICS[metric].unit;
  }

  function formatAxis(value, metric) {
    var amount = metric === 'revenue' ? value / 100 : value;
    if (amount >= 1000000) { return oneFormat.format(amount / 1000000).replace(',0', '') + ' mln'; }
    if (amount >= 10000) { return oneFormat.format(amount / 1000).replace(',0', '') + ' tys.'; }
    return intFormat.format(amount);
  }

  function percent(share) {
    if (!isFinite(share)) { return '—'; }
    return (share > 0 && share < 0.001 ? '<0,1' : oneFormat.format(share * 100)) + '%';
  }

  function signed(value, formatter) { return (value > 0 ? '+' : value < 0 ? '−' : '') + formatter(Math.abs(value)); }

  function plural(count, one, few, many) {
    var mod10 = count % 10, mod100 = count % 100;
    if (count === 1) { return one; }
    return mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14) ? few : many;
  }

  function parseDate(iso) { var parts = String(iso).split('-'); return new Date(+parts[0], +parts[1] - 1, +parts[2]); }

  function isoDate(date) {
    return date.getFullYear() + '-' + ('0' + (date.getMonth() + 1)).slice(-2) + '-' + ('0' + date.getDate()).slice(-2);
  }

  function addDays(date, days) { var copy = new Date(date.getTime()); copy.setDate(copy.getDate() + days); return copy; }

  function validDate(iso) { return /^\d{4}-\d{2}-\d{2}$/.test(iso || '') && isoDate(parseDate(iso)) === iso; }

  function shortDate(iso, withYear) {
    var date = parseDate(iso);
    return date.getDate() + ' ' + MONTHS[date.getMonth()] + (withYear ? ' ' + date.getFullYear() : '');
  }

  function rangeLabel(from, to) {
    var a = parseDate(from), b = parseDate(to);
    if (from === to) { return shortDate(from, true); }
    if (a.getFullYear() !== b.getFullYear()) { return shortDate(from, true) + ' – ' + shortDate(to, true); }
    if (a.getMonth() !== b.getMonth()) { return shortDate(from) + ' – ' + shortDate(to, true); }
    return a.getDate() + '–' + shortDate(to, true);
  }

  function weekday(iso) { return (parseDate(iso).getDay() + 6) % 7; }

  function bucketTick(bucket) {
    var date = parseDate(bucket.from);
    if (report.filters.group === 'month') { return MONTHS[date.getMonth()] + ' ' + String(date.getFullYear()).slice(-2); }
    return shortDate(bucket.from);
  }

  function bucketTitle(bucket, group) {
    var date = parseDate(bucket.from);
    if (group === 'month') {
      var label = MONTHS_FULL[date.getMonth()] + ' ' + date.getFullYear();
      var last = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
      return date.getDate() === 1 && parseDate(bucket.to).getDate() === last ? label : label + ' (' + rangeLabel(bucket.from, bucket.to) + ')';
    }
    if (group === 'week') { return rangeLabel(bucket.from, bucket.to); }
    return DAYS_FULL[weekday(bucket.from)] + ', ' + shortDate(bucket.from, true);
  }

  function accountName(account) {
    return account.platform_label && account.name.toLowerCase().indexOf(account.platform_label.toLowerCase()) === -1 ? account.platform_label + ' · ' + account.name : account.name;
  }

  function swatch(color, line) {
    var node = el('span', { class: 'st-key' + (line ? ' is-line' : '') });
    node.style.background = color;
    return node;
  }

  function empty(text) { return el('div', { class: 'st-empty' }, text); }

  /* ---------- dymek ---------- */

  function showTip(event, build) {
    clear(tip);
    build(tip);
    tip.hidden = false;
    moveTip(event);
  }

  function moveTip(event) {
    var pad = 14, width = tip.offsetWidth, height = tip.offsetHeight;
    var x = event.clientX + pad, y = event.clientY + pad;
    if (x + width > window.innerWidth - 8) { x = event.clientX - width - pad; }
    if (y + height > window.innerHeight - 8) { y = event.clientY - height - pad; }
    tip.style.left = Math.max(8, x) + 'px';
    tip.style.top = Math.max(8, y) + 'px';
  }

  function hideTip() { tip.hidden = true; }

  function tipRow(color, name, value, line, total) {
    return el('div', { class: 'st-tip-row' + (total ? ' is-total' : '') }, [color ? swatch(color, line) : null, el('span', null, name), el('b', null, value)]);
  }

  /* ---------- zmiana względem poprzedniego okresu ---------- */

  function deltaChip(current, previous, config) {
    config = config || {};
    if (previous === null || previous === undefined) { return null; }
    var text, tone = '';
    if (config.points) {
      var diff = (current - previous) * 100;
      if (Math.abs(diff) < 0.05) { text = '0,0 pp'; }
      else { text = (diff > 0 ? '▲ +' : '▼ −') + oneFormat.format(Math.abs(diff)) + ' pp'; tone = (diff > 0) !== !!config.inverse ? 'good' : 'bad'; }
    } else if (!previous && !current) {
      text = '—';
    } else if (!previous) {
      text = '▲ nowe'; tone = config.inverse ? 'bad' : 'good';
    } else {
      var change = (current - previous) / previous;
      if (Math.abs(change) < 0.0005) { text = '0,0%'; }
      else { text = (change > 0 ? '▲ +' : '▼ −') + oneFormat.format(Math.abs(change) * 100) + '%'; tone = (change > 0) !== !!config.inverse ? 'good' : 'bad'; }
    }
    return el('span', { class: 'st-delta' + (tone ? ' is-' + tone : ''), title: config.title || 'Względem poprzedniego okresu' }, text);
  }

  /* ---------- stan filtrów ---------- */

  function today() { return validDate(options.today) ? parseDate(options.today) : new Date(); }

  function presetRange(name) {
    var now = today(), from = now, to = now;
    if (name === 'yesterday') { from = to = addDays(now, -1); }
    else if (name === '7d') { from = addDays(now, -6); }
    else if (name === '30d') { from = addDays(now, -29); }
    else if (name === '90d') { from = addDays(now, -89); }
    else if (name === 'month') { from = new Date(now.getFullYear(), now.getMonth(), 1); }
    else if (name === 'prev_month') { from = new Date(now.getFullYear(), now.getMonth() - 1, 1); to = new Date(now.getFullYear(), now.getMonth(), 0); }
    else if (name === 'year') { from = new Date(now.getFullYear(), 0, 1); }
    return [isoDate(from), isoDate(to)];
  }

  function idList(value, known) {
    var list = Array.isArray(value) ? value : String(value || '').split(',');
    var seen = {}, out = [];
    list.forEach(function (item) {
      var id = parseInt(item, 10);
      if (id > 0 && known[id] && !seen[id]) { seen[id] = true; out.push(id); }
    });
    return out;
  }

  function knownIds(list) { var map = {}; list.forEach(function (item) { map[item.id] = true; }); return map; }

  function defaultStatuses() {
    return options.statuses.filter(function (status) { return !status.excluded_default; }).map(function (status) { return status.id; });
  }

  function includedStatuses() { return state.statuses || defaultStatuses(); }

  function loadState() {
    var params = new URLSearchParams(window.location.search), source = {}, stored = null;
    var keys = ['preset', 'from', 'to', 'accounts', 'statuses', 'paid', 'currency', 'group', 'metric'];
    var fromUrl = keys.some(function (key) { return params.has(key); });
    if (fromUrl) { keys.forEach(function (key) { if (params.has(key)) { source[key] = params.get(key); } }); }
    else { try { stored = parseJson(window.localStorage.getItem(storeKey), null); } catch (e) { stored = null; } source = stored || {}; delete source.from; delete source.to; }

    if (validDate(source.from) && validDate(source.to) && (!source.preset || source.preset === 'custom')) {
      state.preset = 'custom'; state.from = source.from <= source.to ? source.from : source.to; state.to = source.from <= source.to ? source.to : source.from;
    } else {
      state.preset = PRESETS.indexOf(source.preset) !== -1 ? source.preset : '30d';
    }
    state.accounts = idList(source.accounts, knownIds(options.accounts));
    var statuses = source.statuses === undefined || source.statuses === null ? [] : idList(source.statuses, knownIds(options.statuses));
    state.statuses = statuses.length ? statuses : null;
    state.paid = source.paid === '0' || source.paid === '1' ? source.paid : '';
    state.currency = /^[A-Z]{3}$/.test(source.currency || '') ? source.currency : '';
    state.group = ['day', 'week', 'month'].indexOf(source.group) !== -1 ? source.group : 'auto';
    state.metric = METRICS[source.metric] ? source.metric : 'units';
    applyPreset();
  }

  function applyPreset() {
    if (state.preset === 'custom') { return; }
    var range = presetRange(state.preset);
    state.from = range[0]; state.to = range[1];
  }

  function saveState() {
    var params = new URLSearchParams(), saved = { preset: state.preset === 'custom' ? '30d' : state.preset, accounts: state.accounts, statuses: state.statuses, paid: state.paid, group: state.group, metric: state.metric, currency: state.currency };
    params.set('controller', 'stats');
    if (state.preset === 'custom') { params.set('from', state.from); params.set('to', state.to); }
    else { params.set('preset', state.preset); }
    if (state.accounts.length) { params.set('accounts', state.accounts.join(',')); }
    if (state.statuses) { params.set('statuses', state.statuses.join(',')); }
    if (state.paid) { params.set('paid', state.paid); }
    if (state.currency) { params.set('currency', state.currency); }
    if (state.group !== 'auto') { params.set('group', state.group); }
    if (state.metric !== 'units') { params.set('metric', state.metric); }
    try { window.history.replaceState(null, '', window.location.pathname + '?' + params.toString().replace(/%2C/g, ',')); } catch (e) { /* np. podgląd z pliku */ }
    try { window.localStorage.setItem(storeKey, JSON.stringify(saved)); } catch (e) { /* tryb prywatny */ }
  }

  function dataQuery() {
    var params = new URLSearchParams();
    params.set('from', state.from); params.set('to', state.to);
    if (state.accounts.length) { params.set('accounts', state.accounts.join(',')); }
    if (state.statuses) { params.set('statuses', state.statuses.join(',')); }
    if (state.paid) { params.set('paid', state.paid); }
    if (state.currency) { params.set('currency', state.currency); }
    params.set('group', state.group);
    return params.toString();
  }

  /* ---------- kontrolki filtrów ---------- */

  function setActive(container, value) {
    Array.prototype.forEach.call(container.querySelectorAll('button[data-value]'), function (button) {
      var active = button.getAttribute('data-value') === value;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  }

  function syncControls() {
    setActive(q('[data-st-presets]'), state.preset);
    setActive(q('[data-st-group]'), state.group);
    setActive(q('[data-st-metric]'), state.metric);
    q('[data-st-from]').value = state.from;
    q('[data-st-to]').value = state.to;
    q('[data-st-paid]').value = state.paid;
    syncMenu('accounts');
    syncMenu('statuses');
  }

  function menuParts(kind) {
    var menu = q('[data-st-menu="' + kind + '"]');
    return { menu: menu, button: menu.querySelector('.st-menu-btn'), label: menu.querySelector('[data-st-menu-label]'), panel: menu.querySelector('.st-menu-panel') };
  }

  function buildMenus() {
    var accounts = menuParts('accounts'), statuses = menuParts('statuses'), lastPlatform = null;
    append(accounts.panel, el('div', { class: 'st-menu-actions' }, [
      el('button', { type: 'button', class: 'st-link', 'data-st-menu-action': 'all' }, 'Zaznacz wszystkie'),
      el('button', { type: 'button', class: 'st-link', 'data-st-menu-action': 'none' }, 'Odznacz')
    ]));
    options.accounts.forEach(function (account) {
      if (account.platform_label !== lastPlatform) { lastPlatform = account.platform_label; accounts.panel.appendChild(el('div', { class: 'st-menu-group' }, account.platform_label || 'Inne')); }
      accounts.panel.appendChild(el('label', { class: 'st-menu-item' }, [
        el('input', { type: 'checkbox', value: account.id }),
        el('span', { title: account.name }, account.name)
      ]));
    });
    if (!options.accounts.length) { accounts.panel.appendChild(empty('Brak połączonych kont.')); }

    append(statuses.panel, el('div', { class: 'st-menu-actions' }, [
      el('button', { type: 'button', class: 'st-link', 'data-st-menu-action': 'default' }, 'Domyślne'),
      el('button', { type: 'button', class: 'st-link', 'data-st-menu-action': 'all' }, 'Wszystkie')
    ]));
    statuses.panel.appendChild(el('div', { class: 'st-menu-group' }, 'Statusy liczone do sprzedaży'));
    options.statuses.forEach(function (status) {
      var dot = el('span', { class: 'st-dot' });
      dot.style.background = status.color;
      statuses.panel.appendChild(el('label', { class: 'st-menu-item' }, [
        el('input', { type: 'checkbox', value: status.id }), dot,
        el('span', { title: status.name }, status.name),
        status.excluded_default ? el('small', null, 'domyślnie pomijany') : null
      ]));
    });

    ['accounts', 'statuses'].forEach(function (kind) {
      var parts = menuParts(kind);
      parts.button.addEventListener('click', function () { toggleMenu(kind, parts.panel.hidden); });
      parts.panel.addEventListener('change', function () { readMenu(kind); });
      parts.panel.addEventListener('click', function (event) {
        var action = event.target.closest ? event.target.closest('[data-st-menu-action]') : null;
        if (!action) { return; }
        var mode = action.getAttribute('data-st-menu-action'), defaults = defaultStatuses();
        Array.prototype.forEach.call(parts.panel.querySelectorAll('input[type=checkbox]'), function (input) {
          input.checked = mode === 'all' || (mode === 'default' && defaults.indexOf(+input.value) !== -1);
        });
        readMenu(kind);
      });
    });
    document.addEventListener('click', function (event) {
      ['accounts', 'statuses'].forEach(function (kind) { if (!menuParts(kind).menu.contains(event.target)) { toggleMenu(kind, false); } });
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') { toggleMenu('accounts', false); toggleMenu('statuses', false); }
    });
  }

  function toggleMenu(kind, open) {
    var parts = menuParts(kind);
    parts.panel.hidden = !open;
    parts.button.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) { toggleMenu(kind === 'accounts' ? 'statuses' : 'accounts', false); }
  }

  function checkedIds(panel) {
    return Array.prototype.filter.call(panel.querySelectorAll('input[type=checkbox]'), function (input) { return input.checked; }).map(function (input) { return +input.value; });
  }

  /** Stan filtrów -> pola wyboru i etykieta przycisku. */
  function syncMenu(kind) {
    var parts = menuParts(kind);
    var selected = kind === 'accounts' ? (state.accounts.length ? state.accounts : options.accounts.map(function (a) { return a.id; })) : includedStatuses();
    Array.prototype.forEach.call(parts.panel.querySelectorAll('input[type=checkbox]'), function (input) { input.checked = selected.indexOf(+input.value) !== -1; });
    labelMenu(kind, selected.length);
  }

  function labelMenu(kind, count) {
    var parts = menuParts(kind), list = kind === 'accounts' ? options.accounts : options.statuses, text, filtered;
    if (kind === 'accounts') {
      filtered = count !== list.length;
      text = !count ? 'Wybierz konta' : !filtered ? 'Wszystkie konta' : count === 1 ? list.filter(function (a) { return a.id === checkedIds(parts.panel)[0]; }).map(accountName)[0] : 'Konta: ' + count + ' z ' + list.length;
    } else {
      filtered = state.statuses !== null || !count;
      text = !count ? 'Wybierz statusy' : state.statuses === null ? 'Statusy sprzedaży' : count === list.length ? 'Wszystkie statusy' : 'Statusy: ' + count + ' z ' + list.length;
    }
    parts.label.textContent = text || 'Konta';
    parts.button.classList.toggle('is-filtered', !!filtered);
  }

  /** Pola wyboru -> stan filtrów; pusty wybór nie pobiera danych, tylko prosi o zaznaczenie. */
  function readMenu(kind) {
    var parts = menuParts(kind), ids = checkedIds(parts.panel);
    if (kind === 'accounts') {
      if (ids.length) { state.accounts = ids.length === options.accounts.length ? [] : ids; }
    } else if (ids.length) {
      var defaults = defaultStatuses();
      state.statuses = ids.length === defaults.length && ids.every(function (id) { return defaults.indexOf(id) !== -1; }) ? null : ids;
    }
    labelMenu(kind, ids.length);
    if (ids.length) { scheduleLoad(350); }
  }

  function bindControls() {
    q('[data-st-presets]').addEventListener('click', function (event) {
      var button = event.target.closest('button[data-value]');
      if (!button) { return; }
      state.preset = button.getAttribute('data-value');
      applyPreset(); syncControls(); scheduleLoad(0);
    });
    ['from', 'to'].forEach(function (name) {
      q('[data-st-' + name + ']').addEventListener('change', function (event) {
        if (!validDate(event.target.value)) { return; }
        state.preset = 'custom'; state[name] = event.target.value;
        if (state.from > state.to) { if (name === 'from') { state.to = state.from; } else { state.from = state.to; } }
        syncControls(); scheduleLoad(0);
      });
    });
    q('[data-st-paid]').addEventListener('change', function (event) { state.paid = event.target.value; scheduleLoad(0); });
    q('[data-st-currency]').addEventListener('change', function (event) { state.currency = event.target.value; scheduleLoad(0); });
    q('[data-st-group]').addEventListener('click', function (event) {
      var button = event.target.closest('button[data-value]');
      if (!button) { return; }
      state.group = button.getAttribute('data-value'); syncControls(); scheduleLoad(0);
    });
    q('[data-st-metric]').addEventListener('click', function (event) {
      var button = event.target.closest('button[data-value]');
      if (button) { setMetric(button.getAttribute('data-value')); }
    });
    q('[data-st-refresh]').addEventListener('click', function () { scheduleLoad(0); });
    q('[data-st-toggle-table]').addEventListener('click', function (event) {
      var table = q('[data-st-timeline-table]');
      table.hidden = !table.hidden;
      event.currentTarget.textContent = table.hidden ? 'Pokaż tabelę' : 'Ukryj tabelę';
    });
    q('[data-st-product-search]').addEventListener('input', function (event) {
      productQuery = event.target.value.trim().toLowerCase(); productLimit = PRODUCT_STEP;
      if (report) { renderProducts(); }
    });
    q('[data-st-products-more] button').addEventListener('click', function () { productLimit += 25; renderProducts(); });
    Array.prototype.forEach.call(root.querySelectorAll('[data-st-csv]'), function (button) {
      button.addEventListener('click', function () { if (report) { exportCsv(button.getAttribute('data-st-csv')); } });
    });
  }

  function setMetric(metric) {
    if (!METRICS[metric] || metric === state.metric) { return; }
    state.metric = metric;
    accountSort = { key: 'value', dir: -1 };
    syncControls(); saveState();
    if (report) { renderAll(); }
  }

  /* ---------- pobieranie ---------- */

  function scheduleLoad(delay) {
    window.clearTimeout(loadTimer);
    loadTimer = window.setTimeout(load, delay);
  }

  function showError(message) { errorBox.textContent = message; errorBox.hidden = false; }

  function load() {
    var id = ++requestId;
    saveState();
    body.classList.add('is-loading');
    body.setAttribute('aria-busy', 'true');
    hideTip();
    fetch(endpoint + (endpoint.indexOf('?') === -1 ? '?' : '&') + dataQuery(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (response) {
        return response.json().then(function (data) { return { ok: response.ok, status: response.status, data: data || {} }; }, function () { return { ok: false, status: response.status, data: {} }; });
      })
      .then(function (result) {
        if (id !== requestId) { return; }
        if (!result.ok) {
          if (result.status === 401 && result.data.login_url) { window.location.href = result.data.login_url; return; }
          showError(result.data.error || 'Nie udało się pobrać statystyk (HTTP ' + result.status + ').');
          return;
        }
        errorBox.hidden = true;
        report = result.data;
        productLimit = PRODUCT_STEP;
        renderAll();
      })
      .catch(function () { if (id === requestId) { showError('Nie udało się połączyć z serwerem. Sprawdź połączenie i spróbuj ponownie.'); } })
      .then(function () {
        if (id !== requestId) { return; }
        body.classList.remove('is-loading');
        body.setAttribute('aria-busy', 'false');
      });
  }

  /* ---------- kolory kont ---------- */

  /** Kolor idzie za kontem (kolejność ID), nie za miejscem w rankingu; ponad 7 kont -> reszta w „Pozostałe”. */
  function assignColors() {
    var ids = options.accounts.map(function (account) { return account.id; });
    if (ids.length > SLOT_COUNT) { ids = report.accounts.slice(0, SLOT_COUNT).map(function (account) { return account.id; }); }
    ids.sort(function (a, b) { return a - b; });
    colorOf = {};
    ids.forEach(function (id, index) { colorOf[id] = 'var(--st-s' + (index + 1) + ')'; });
  }

  function accountColor(id) { return colorOf[id] || 'var(--st-other)'; }

  function timelineSeries(metric) {
    var list = [], other = null;
    report.accounts.forEach(function (account) {
      var values = (report.series[account.id] || {})[metric];
      if (!values || !sum(values)) { return; }
      if (colorOf[account.id]) { list.push({ id: account.id, name: accountName(account), color: colorOf[account.id], values: values }); return; }
      if (!other) { other = { id: 0, name: 'Pozostałe konta', color: 'var(--st-other)', values: values.map(function () { return 0; }) }; }
      values.forEach(function (value, index) { other.values[index] += value; });
    });
    list.sort(function (a, b) { return a.id - b.id; });
    if (other) { list.push(other); }
    return list;
  }

  function bucketTotals(metric) {
    var totals = report.buckets.map(function () { return 0; });
    Object.keys(report.series).forEach(function (id) {
      (report.series[id][metric] || []).forEach(function (value, index) { totals[index] += value; });
    });
    return totals;
  }

  /* ---------- renderowanie ---------- */

  function renderAll() {
    assignColors();
    renderHeader();
    renderKpis();
    renderInsights();
    renderTimeline();
    renderAccountBars();
    renderHeatmap();
    renderAccountsTable();
    renderStatuses();
    renderMinis();
    renderProducts();
  }

  function renderHeader() {
    var period = report.period, previous = report.previous_period;
    var text = rangeLabel(period.from, period.to) + ' · ' + period.days + ' ' + plural(period.days, 'dzień', 'dni', 'dni');
    text += previous ? ' · porównanie z okresem ' + rangeLabel(previous.from, previous.to) : ' · bez porównania (zakres dłuższy niż rok)';
    q('[data-st-period]').textContent = text;
    q('[data-st-updated]').textContent = 'Stan na ' + String(report.generated_at || '').slice(11, 16) + ' · czas polski';

    var select = q('[data-st-currency]');
    clear(select);
    report.currencies.forEach(function (currency) {
      var option = el('option', { value: currency.code }, 'Kwoty w ' + currency.code + ' (' + intFormat.format(currency.orders) + ' zam.)');
      option.selected = currency.code === report.currency;
      select.appendChild(option);
    });
    select.hidden = report.currencies.length < 2;
  }

  function sparkline(values) {
    if (values.length < 3) { return null; }
    var width = 84, height = 26, max = Math.max(maxOf(values), 1), path = '';
    values.forEach(function (value, index) {
      path += (index ? 'L' : 'M') + (index / (values.length - 1) * width).toFixed(1) + ',' + (height - 2 - value / max * (height - 4)).toFixed(1);
    });
    var node = svg('svg', { class: 'st-kpi-spark', viewBox: '0 0 ' + width + ' ' + height, 'aria-hidden': 'true' });
    node.appendChild(svg('path', { d: path }));
    return node;
  }

  function renderKpis() {
    var host = clear(q('[data-st-kpis]'));
    var totals = report.totals, before = report.previous_totals, excluded = report.excluded, excludedBefore = report.previous_excluded;
    var all = totals.orders + excluded.orders, allBefore = before ? before.orders + excludedBefore.orders : 0;
    var others = report.currencies.filter(function (currency) { return currency.code !== report.currency; }).map(function (currency) {
      return money(currency.revenue_cents, currency.code);
    });
    var tiles = [
      { metric: 'units', label: 'Sprzedane sztuki', value: intFormat.format(totals.units), unit: 'szt.', current: totals.units, previous: before && before.units, spark: bucketTotals('units') },
      { metric: 'orders', label: 'Zamówienia', value: intFormat.format(totals.orders), current: totals.orders, previous: before && before.orders, spark: bucketTotals('orders') },
      { metric: 'revenue', label: 'Przychód brutto', value: twoFormat.format(totals.revenue_cents / 100), unit: report.currency, current: totals.revenue_cents, previous: before && before.revenue_cents, spark: bucketTotals('revenue'), note: 'z dostawą' + (others.length ? ' · oraz ' + others.join(', ') : '') },
      { label: 'Średnia wartość zamówienia', value: totals.orders_cur ? twoFormat.format(totals.revenue_cents / totals.orders_cur / 100) : '—', unit: totals.orders_cur ? report.currency : '', current: totals.orders_cur ? totals.revenue_cents / totals.orders_cur : 0, previous: before && (before.orders_cur ? before.revenue_cents / before.orders_cur : 0) },
      { label: 'Sztuk na zamówienie', value: totals.orders ? twoFormat.format(totals.units / totals.orders) : '—', current: totals.orders ? totals.units / totals.orders : 0, previous: before && (before.orders ? before.units / before.orders : 0) },
      { label: 'Poza sprzedażą', value: all ? percent(excluded.orders / all) : '—', current: all ? excluded.orders / all : 0, previous: before && (allBefore ? excludedBefore.orders / allBefore : 0), points: true, inverse: true, note: intFormat.format(excluded.orders) + ' zam. w pominiętych statusach' }
    ];
    tiles.forEach(function (tile) {
      var node = el(tile.metric ? 'button' : 'div', { class: 'st-kpi' + (tile.metric === state.metric ? ' is-active' : ''), type: tile.metric ? 'button' : null, 'aria-pressed': tile.metric ? String(tile.metric === state.metric) : null, title: tile.metric ? 'Pokaż wykresy dla miary: ' + METRICS[tile.metric].label : null }, [
        el('span', { class: 'st-kpi-label' }, tile.label),
        el('span', { class: 'st-kpi-value' }, [tile.value, tile.unit ? el('small', null, ' ' + tile.unit) : null]),
        el('span', { class: 'st-kpi-foot' }, [
          deltaChip(tile.current, tile.previous === null || tile.previous === undefined ? null : tile.previous, { points: tile.points, inverse: tile.inverse }),
          tile.spark ? sparkline(tile.spark) : null
        ]),
        tile.note ? el('span', { class: 'st-kpi-note' }, tile.note) : null
      ]);
      if (tile.metric) { node.addEventListener('click', function () { setMetric(tile.metric); }); }
      host.appendChild(node);
    });
  }

  /** Wnioski to lista zdań złożonych z tekstu i wyróżnień {b: '…'}. */
  function buildInsights() {
    var out = [], metric = state.metric, totals = report.totals, before = report.previous_totals;
    var current = rowValue(totals, metric), previous = before ? rowValue(before, metric) : null;
    var label = METRICS[metric].label;
    if (!totals.orders) {
      out.push({ icon: 'bi-inbox', parts: ['W tym okresie nie ma zamówień w wybranych kontach i statusach. Zmień zakres dat albo filtry.'] });
      if (report.excluded.orders) { out.push({ icon: 'bi-slash-circle', parts: [{ b: intFormat.format(report.excluded.orders) + ' zam.' }, ' ma status pominięty w sprzedaży — włącz go w filtrze „Statusy”, żeby je zobaczyć.'] }); }
      return out;
    }

    if (previous !== null && previous > 0) {
      var change = (current - previous) / previous, up = change >= 0;
      out.push({ icon: up ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow', tone: Math.abs(change) < 0.0005 ? '' : up ? 'good' : 'bad', parts: [
        { b: label + ': ' + signed(change * 100, oneFormat.format) + '%' }, ' względem poprzedniego okresu (' + formatMetric(previous, metric) + ' → ' + formatMetric(current, metric) + ').'
      ] });
    } else if (previous === 0) {
      out.push({ icon: 'bi-graph-up-arrow', tone: 'good', parts: ['W poprzednim okresie nie było sprzedaży — teraz ', { b: formatMetric(current, metric) }, '.'] });
    }

    var accounts = report.accounts.filter(function (account) { return rowValue(account, metric) > 0; }).sort(function (a, b) { return rowValue(b, metric) - rowValue(a, metric); });
    if (accounts.length && current > 0) {
      var leader = accounts[0];
      out.push({ icon: 'bi-trophy', parts: accounts.length === 1
        ? ['Całą sprzedaż okresu zrealizowało konto ', { b: accountName(leader) }, '.']
        : ['Najwięcej sprzedaje konto ', { b: accountName(leader) }, ': ' + formatMetric(rowValue(leader, metric), metric) + ', czyli ', { b: percent(rowValue(leader, metric) / current) }, ' całości.'] });
    }

    if (before && report.accounts.length > 1) {
      var best = null, worst = null;
      report.accounts.forEach(function (account) {
        var diff = rowValue(account, metric) - rowValue(account.previous || {}, metric);
        if (diff > 0 && (!best || diff > best.diff)) { best = { account: account, diff: diff }; }
        if (diff < 0 && (!worst || diff < worst.diff)) { worst = { account: account, diff: diff }; }
      });
      if (best || worst) {
        var parts = [];
        if (best) { parts.push('Największy wzrost: ', { b: accountName(best.account) }, ' (+' + formatMetric(best.diff, metric) + '). '); }
        if (worst) { parts.push('Największy spadek: ', { b: accountName(worst.account) }, ' (−' + formatMetric(-worst.diff, metric) + ').'); }
        out.push({ icon: 'bi-arrow-left-right', parts: parts });
      }
    }

    var series = bucketTotals(metric), group = report.filters.group;
    if (series.length >= 3 && current > 0) {
      var peak = series.indexOf(maxOf(series));
      var noun = { day: 'Najlepszy dzień', week: 'Najlepszy tydzień', month: 'Najlepszy miesiąc' }[group];
      out.push({ icon: 'bi-calendar-check', parts: [noun + ': ', { b: bucketTitle(report.buckets[peak], group) }, ' — ' + formatMetric(series[peak], metric) + ', przy średniej ', { b: metric === 'revenue' ? money(Math.round(current / report.period.days)) : oneFormat.format(current / report.period.days) + ' ' + METRICS[metric].unit }, ' dziennie.'] });
    }

    var heat = report.heatmap.orders, dayTotals = heat.map(sum), hourTotals = [], hour;
    for (hour = 0; hour < 24; hour++) { hourTotals.push(sum(heat.map(function (row) { return row[hour]; }))); }
    if (totals.orders >= 5) {
      var topDay = dayTotals.indexOf(maxOf(dayTotals)), windowStart = 0, windowSum = -1, start, value;
      for (start = 0; start <= 21; start++) {
        value = hourTotals[start] + hourTotals[start + 1] + hourTotals[start + 2];
        if (value > windowSum) { windowSum = value; windowStart = start; }
      }
      out.push({ icon: 'bi-clock-history', parts: ['Klienci zamawiają najczęściej ', { b: DAYS_IN[topDay] }, ' (' + percent(dayTotals[topDay] / totals.orders) + ' zamówień), a w ciągu doby między ', { b: windowStart + ':00 a ' + (windowStart + 3) + ':00' }, ' (' + percent(windowSum / totals.orders) + ').'] });
    }

    if (report.products.length && totals.units > 0) {
      var top = report.products[0], name = top.name.length > 70 ? top.name.slice(0, 69) + '…' : top.name;
      var parts2 = ['Najlepiej sprzedający się produkt: ', { b: name }, ' — ' + intFormat.format(top.units) + ' szt. (' + percent(top.units / totals.units) + ' sztuk).'];
      if (report.products_count > 5) {
        parts2.push(' Pięć najlepszych produktów daje ', { b: percent(sum(report.products.slice(0, 5).map(function (product) { return product.units; })) / totals.units) }, ' sprzedanych sztuk.');
      }
      out.push({ icon: 'bi-box-seam', parts: parts2 });
    }

    var excluded = report.excluded;
    if (excluded.orders > 0) {
      var all = totals.orders + excluded.orders, share = excluded.orders / all;
      var names = report.statuses.filter(function (status) { return !status.included && status.orders > 0; }).map(function (status) { return status.name; });
      var parts3 = [{ b: percent(share) }, ' zamówień (' + intFormat.format(excluded.orders) + ' z ' + intFormat.format(all) + ') ma status pominięty w sprzedaży: ' + names.slice(0, 4).join(', ') + (names.length > 4 ? '…' : '') + '.'];
      var worstAccount = null;
      report.accounts.forEach(function (account) {
        var count = account.orders + account.excluded_orders, rate = count ? account.excluded_orders / count : 0;
        if (count >= 10 && rate > share * 1.5 && (!worstAccount || rate > worstAccount.rate)) { worstAccount = { account: account, rate: rate }; }
      });
      if (worstAccount) { parts3.push(' Najwyższy odsetek: ', { b: accountName(worstAccount.account) }, ' (' + percent(worstAccount.rate) + ').'); }
      out.push({ icon: 'bi-slash-circle', tone: share >= 0.1 ? 'bad' : '', parts: parts3 });
    }

    var unpaid = report.payment.filter(function (row) { return row.key === 'unpaid'; })[0];
    if (unpaid && unpaid.orders > 0 && state.paid === '') {
      out.push({ icon: 'bi-hourglass-split', parts: [{ b: intFormat.format(unpaid.orders) + ' zam.' }, ' (' + percent(unpaid.orders / totals.orders) + ') czeka na płatność — nieopłacone i bez pobrania.'] });
    }
    return out;
  }

  function renderInsights() {
    var host = clear(q('[data-st-insights]'));
    buildInsights().forEach(function (insight) {
      var text = el('span');
      insight.parts.forEach(function (part) { text.appendChild(typeof part === 'string' ? document.createTextNode(part) : el('b', null, part.b)); });
      host.appendChild(el('li', { class: insight.tone ? 'is-' + insight.tone : null }, [el('i', { class: 'bi ' + insight.icon, 'aria-hidden': 'true' }), text]));
    });
  }

  function niceTicks(max, metric) {
    if (max <= 0) { return [0, 1]; }
    var raw = max / 4, power = Math.pow(10, Math.floor(Math.log(raw) / Math.LN10)), fraction = raw / power;
    var step = (fraction <= 1 ? 1 : fraction <= 2 ? 2 : fraction <= 2.5 ? 2.5 : fraction <= 5 ? 5 : 10) * power;
    if (metric !== 'revenue') { step = Math.max(1, Math.ceil(step)); }
    var ticks = [], value;
    for (value = 0; value < max + step * 0.999; value += step) { ticks.push(value); }
    return ticks;
  }

  function renderTimeline() {
    var host = q('[data-st-timeline]'), legend = clear(q('[data-st-timeline-legend]'));
    var metric = state.metric, buckets = report.buckets, group = report.filters.group;
    var series = timelineSeries(metric), totals = bucketTotals(metric);
    var previous = report.previous_series ? report.previous_series[metric].slice(0, buckets.length) : null;
    q('[data-st-timeline-sub]').textContent = METRICS[metric].label + ' według kont, ' + GROUP_LABELS[group] + (metric === 'revenue' ? ' · kwoty w ' + report.currency : '') + (previous ? ' · szara linia: poprzedni okres' : '');
    clear(host);
    renderTimelineTable(series, totals, previous);
    if (!sum(totals) && !(previous && sum(previous))) { host.appendChild(empty('Brak sprzedaży w tym okresie.')); return; }

    if (series.length > 1 || previous) {
      series.forEach(function (item) { legend.appendChild(el('span', null, [swatch(item.color), item.name])); });
      if (previous) { legend.appendChild(el('span', null, [swatch('var(--st-prev)', true), 'Poprzedni okres (' + rangeLabel(report.previous_period.from, report.previous_period.to) + ')'])); }
    }

    var width = Math.max(320, host.clientWidth || 900), height = 300;
    var margin = { left: 52, right: 14, top: 22, bottom: 28 };
    var plotWidth = width - margin.left - margin.right, plotHeight = height - margin.top - margin.bottom;
    var ticks = niceTicks(Math.max(maxOf(totals), previous ? maxOf(previous) : 0), metric), top = ticks[ticks.length - 1];
    var band = plotWidth / buckets.length, barWidth = Math.max(1.5, Math.min(24, band - (band > 10 ? 4 : band > 5 ? 2 : 0.5)));
    var baseline = margin.top + plotHeight;
    var chart = svg('svg', { viewBox: '0 0 ' + width + ' ' + height, height: height, role: 'img', 'aria-label': 'Wykres: ' + METRICS[metric].label + ' w czasie według kont. Te same dane są w tabeli pod wykresem.' });
    timelineWidth = width;

    ticks.forEach(function (tick) {
      var y = baseline - tick / top * plotHeight;
      chart.appendChild(svg('line', { class: tick ? 'st-gridline' : 'st-baseline', x1: margin.left, x2: width - margin.right, y1: y, y2: y }));
      chart.appendChild(svg('text', { x: margin.left - 8, y: y + 4, 'text-anchor': 'end' }, formatAxis(tick, metric)));
    });

    var bands = svg('g'), bars = svg('g'), labelStep = Math.max(1, Math.ceil(buckets.length / Math.max(1, Math.floor(plotWidth / 56))));
    buckets.forEach(function (bucket, index) {
      var x = margin.left + band * index, barX = x + (band - barWidth) / 2, cursor = baseline;
      var segments = series.filter(function (item) { return item.values[index] > 0; });
      bands.appendChild(svg('rect', { class: 'st-band', 'data-index': index, x: x, y: margin.top - 6, width: band, height: plotHeight + 6 }));
      segments.forEach(function (item, position) {
        var full = item.values[index] / top * plotHeight, gap = position > 0 && full >= 4 ? 2 : 0;
        var segmentHeight = Math.max(full - gap, 0.75), y = cursor - full, node;
        if (position === segments.length - 1) {
          var radius = Math.min(4, barWidth / 2, segmentHeight);
          node = svg('path', { d: 'M' + barX + ',' + (y + segmentHeight) + 'V' + (y + radius) + 'Q' + barX + ',' + y + ' ' + (barX + radius) + ',' + y + 'H' + (barX + barWidth - radius) + 'Q' + (barX + barWidth) + ',' + y + ' ' + (barX + barWidth) + ',' + (y + radius) + 'V' + (y + segmentHeight) + 'Z' });
        } else {
          node = svg('rect', { x: barX, y: y, width: barWidth, height: segmentHeight });
        }
        node.style.fill = item.color;
        node.style.pointerEvents = 'none';
        bars.appendChild(node);
        cursor -= full;
      });
      if (index % labelStep === 0) { chart.appendChild(svg('text', { x: x + band / 2, y: height - 8, 'text-anchor': 'middle' }, bucketTick(bucket))); }
    });
    chart.appendChild(bands);
    chart.appendChild(bars);

    if (previous && previous.length > 1) {
      var path = '';
      previous.forEach(function (value, index) { path += (index ? 'L' : 'M') + (margin.left + band * (index + 0.5)).toFixed(1) + ',' + (baseline - value / top * plotHeight).toFixed(1); });
      ['st-prev-halo', 'st-prev-line'].forEach(function (name) {
        var line = svg('path', { class: name, d: path });
        line.style.pointerEvents = 'none';
        chart.appendChild(line);
      });
    }

    var peak = totals.indexOf(maxOf(totals));
    if (totals[peak] > 0) {
      var peakX = Math.min(Math.max(margin.left + band * (peak + 0.5), margin.left + 30), width - margin.right - 30);
      var peakText = metric === 'revenue' ? intFormat.format(Math.round(totals[peak] / 100)) + ' ' + report.currency : intFormat.format(totals[peak]);
      chart.appendChild(svg('text', { class: 'st-peak', x: peakX, y: baseline - totals[peak] / top * plotHeight - 7, 'text-anchor': 'middle' }, peakText));
    }

    var hovered = -1;
    function hover(event) {
      var target = event.target, index = target && target.getAttribute ? target.getAttribute('data-index') : null;
      if (index === null) { return; }
      index = +index;
      if (index === hovered && !tip.hidden) { moveTip(event); return; }
      hovered = index;
      Array.prototype.forEach.call(bands.querySelectorAll('.is-hover'), function (node) { node.classList.remove('is-hover'); });
      target.classList.add('is-hover');
      showTip(event, function (box) {
        box.appendChild(el('div', { class: 'st-tip-title' }, bucketTitle(buckets[index], group)));
        series.forEach(function (item) { if (series.length === 1 || item.values[index] > 0) { box.appendChild(tipRow(item.color, item.name, formatMetric(item.values[index], metric))); } });
        if (series.length !== 1) { box.appendChild(tipRow(null, 'Razem', formatMetric(totals[index], metric), false, true)); }
        if (previous && report.previous_buckets[index]) {
          box.appendChild(tipRow('var(--st-prev)', 'Poprzednio: ' + (group === 'day' ? shortDate(report.previous_buckets[index].from) : rangeLabel(report.previous_buckets[index].from, report.previous_buckets[index].to)), formatMetric(previous[index] || 0, metric), true, series.length === 1));
        }
      });
    }
    bands.addEventListener('pointermove', hover);
    bands.addEventListener('pointerleave', function () {
      hovered = -1;
      Array.prototype.forEach.call(bands.querySelectorAll('.is-hover'), function (node) { node.classList.remove('is-hover'); });
      hideTip();
    });
    host.appendChild(chart);
  }

  function renderTimelineTable(series, totals, previous) {
    var host = clear(q('[data-st-timeline-table]')), metric = state.metric, group = report.filters.group;
    var table = el('table', { class: 'st-table' });
    var head = el('tr', null, [el('th', null, 'Okres')]);
    series.forEach(function (item) { head.appendChild(el('th', null, item.name)); });
    head.appendChild(el('th', null, 'Razem'));
    if (previous) { head.appendChild(el('th', null, 'Poprzedni okres')); }
    var tbody = el('tbody');
    report.buckets.forEach(function (bucket, index) {
      var row = el('tr', null, [el('td', null, bucketTitle(bucket, group))]);
      series.forEach(function (item) { row.appendChild(el('td', null, formatMetric(item.values[index], metric))); });
      row.appendChild(el('td', null, el('b', null, formatMetric(totals[index], metric))));
      if (previous) { row.appendChild(el('td', null, previous[index] === undefined ? '—' : formatMetric(previous[index], metric))); }
      tbody.appendChild(row);
    });
    append(table, [el('thead', null, head), tbody]);
    host.appendChild(table);
  }

  /** Lista poziomych słupków: nazwa, słupek, wartość wybranej miary i udział w sumie listy. */
  function barList(host, rows, config) {
    config = config || {};
    clear(host);
    var metric = state.metric, values = rows.map(function (row) { return rowValue(row, metric); });
    var max = maxOf(values), total = config.total !== undefined ? config.total : sum(values);
    if (!rows.length || max <= 0) { host.appendChild(empty('Brak danych w tym okresie.')); return; }
    rows.forEach(function (row, index) {
      var value = values[index], name = el('div', { class: 'st-bar-name' });
      if (config.mark) { name.appendChild(config.mark(row)); }
      name.appendChild(el('span', { title: row.name }, row.name));
      if (config.extra) { append(name, config.extra(row)); }
      var fill = el('span', { class: 'st-bar-fill' });
      fill.style.width = (value / max * 100).toFixed(2) + '%';
      if (value <= 0) { fill.style.display = 'none'; }
      if (config.color) { fill.style.background = config.color(row); }
      host.appendChild(el('div', { class: 'st-bar-row' + (config.muted && config.muted(row) ? ' is-muted' : '') }, [
        name,
        el('div', { class: 'st-bar-track' }, fill),
        el('div', { class: 'st-bar-value' }, [el('b', null, formatMetric(value, metric)), el('small', null, total > 0 ? percent(value / total) : '—'), config.delta ? config.delta(row) : null])
      ]));
    });
  }

  function renderAccountBars() {
    var metric = state.metric, host = q('[data-st-accounts-bars]');
    var rows = report.accounts.filter(function (account) { return rowValue(account, metric) > 0; }).sort(function (a, b) { return rowValue(b, metric) - rowValue(a, metric); });
    var total = sum(rows.map(function (row) { return rowValue(row, metric); }));
    q('[data-st-accounts-sub]').textContent = METRICS[metric].label + ' i udział konta w sprzedaży' + (report.previous_period ? ' · zmiana względem poprzedniego okresu' : '');
    barList(host, rows.slice(0, ACCOUNT_BAR_LIMIT).map(function (account) { return { name: account.name, account: account, units: account.units, orders: account.orders, revenue_cents: account.revenue_cents }; }), {
      total: total,
      mark: function (row) { return swatch(accountColor(row.account.id)); },
      extra: function (row) { return row.account.platform_label ? el('small', null, row.account.platform_label) : null; },
      color: function (row) { return accountColor(row.account.id); },
      delta: function (row) { return row.account.previous ? deltaChip(rowValue(row.account, metric), rowValue(row.account.previous, metric)) : null; }
    });
    if (rows.length > ACCOUNT_BAR_LIMIT) { host.appendChild(el('p', { class: 'st-note' }, 'Pokazano ' + ACCOUNT_BAR_LIMIT + ' z ' + rows.length + ' kont — wszystkie są w tabeli poniżej.')); }
  }

  function renderHeatmap() {
    var host = clear(q('[data-st-heatmap]')), metric = state.metric, grid = report.heatmap[metric];
    var max = maxOf(grid.map(maxOf)), peakDay = 0, peakHour = 0;
    q('[data-st-heatmap-sub]').textContent = METRICS[metric].label + ' według dnia tygodnia i godziny złożenia zamówienia (czas polski)';
    if (max <= 0) { host.appendChild(empty('Brak danych w tym okresie.')); return; }
    var wrap = el('div', { class: 'st-heat' }), hour;
    wrap.appendChild(el('div'));
    for (hour = 0; hour < 24; hour++) { wrap.appendChild(el('div', { class: 'st-heat-hour' }, hour % 3 === 0 ? String(hour) : '')); }
    grid.forEach(function (row, day) {
      wrap.appendChild(el('div', { class: 'st-heat-day' }, DAYS[day]));
      row.forEach(function (value, hourIndex) {
        if (value > grid[peakDay][peakHour]) { peakDay = day; peakHour = hourIndex; }
        var label = DAYS_FULL[day] + ', ' + hourIndex + ':00–' + (hourIndex + 1) + ':00: ' + formatMetric(value, metric);
        wrap.appendChild(el('div', { class: 'st-heat-cell', 'data-step': value > 0 ? 1 + Math.min(4, Math.floor(value / max * 5)) : 0, 'data-day': day, 'data-hour': hourIndex, 'aria-label': label }));
      });
    });
    function hover(event) {
      var cell = event.target.closest ? event.target.closest('.st-heat-cell') : null;
      if (!cell) { hideTip(); return; }
      var day = +cell.getAttribute('data-day'), hourIndex = +cell.getAttribute('data-hour');
      showTip(event, function (box) {
        box.appendChild(el('div', { class: 'st-tip-title' }, DAYS_FULL[day] + ', ' + hourIndex + ':00–' + (hourIndex + 1) + ':00'));
        ['units', 'orders', 'revenue'].forEach(function (key) { box.appendChild(tipRow(null, METRICS[key].label, formatMetric(report.heatmap[key][day][hourIndex], key))); });
      });
    }
    wrap.addEventListener('pointermove', hover);
    wrap.addEventListener('pointerleave', hideTip);
    host.appendChild(wrap);
    var scale = el('div', { class: 'st-heat-scale' }, [el('span', null, 'mniej')]);
    [1, 2, 3, 4, 5].forEach(function (step) { var mark = el('i'); mark.style.background = 'var(--st-h' + step + ')'; scale.appendChild(mark); });
    append(scale, [el('span', null, 'więcej'), el('span', null, 'Szczyt: ' + DAYS_FULL[peakDay] + ' ' + peakHour + ':00–' + (peakHour + 1) + ':00 · ' + formatMetric(grid[peakDay][peakHour], metric))]);
    host.appendChild(scale);
  }

  /** Tabela z sortowaniem po kliknięciu nagłówka. columns: {key, label, left, value(row), cell(row), foot} */
  function sortableTable(host, columns, rows, sort, rerender, footer, limit) {
    clear(host);
    var active = columns.filter(function (column) { return column.key === sort.key && column.value; })[0];
    if (active) {
      rows = rows.slice().sort(function (a, b) {
        var x = active.value(a), y = active.value(b);
        if (typeof x === 'string') { return sort.dir * x.localeCompare(y, 'pl'); }
        return sort.dir * (x - y);
      });
    }
    if (limit) { rows = rows.slice(0, limit); }
    var head = el('tr');
    columns.forEach(function (column) {
      var th = el('th', { class: (column.left ? 'is-left' : '') + (column.key === sort.key ? ' is-sorted' : ''), 'aria-sort': column.key === sort.key ? (sort.dir > 0 ? 'ascending' : 'descending') : null });
      if (column.value) {
        var button = el('button', { type: 'button' }, [column.label, column.key === sort.key ? el('i', { class: 'bi ' + (sort.dir > 0 ? 'bi-caret-up-fill' : 'bi-caret-down-fill'), 'aria-hidden': 'true' }) : null]);
        button.addEventListener('click', function () {
          if (sort.key === column.key) { sort.dir = -sort.dir; } else { sort.key = column.key; sort.dir = column.left ? 1 : -1; }
          rerender();
        });
        th.appendChild(button);
      } else { th.textContent = column.label; }
      head.appendChild(th);
    });
    var tbody = el('tbody');
    rows.forEach(function (row, index) {
      var tr = el('tr');
      columns.forEach(function (column) { tr.appendChild(append(el('td', { class: (column.left ? 'is-left ' : '') + (column.className || '') }), column.cell(row, index))); });
      tbody.appendChild(tr);
    });
    var table = append(el('table', { class: 'st-table' }), [el('thead', null, head), tbody]);
    if (footer) {
      var foot = el('tr');
      columns.forEach(function (column) { foot.appendChild(append(el('td', { class: column.left ? 'is-left' : '' }), column.foot ? column.foot() : '')); });
      table.appendChild(el('tfoot', null, foot));
    }
    host.appendChild(table);
    return rows;
  }

  function accountColumns() {
    var metric = state.metric, totals = report.totals, total = rowValue(totals, metric), hasPrevious = !!report.previous_totals;
    var excludedShare = function (account) { var count = account.orders + account.excluded_orders; return count ? account.excluded_orders / count : 0; };
    var columns = [
      { key: 'name', label: 'Konto', left: true, value: function (a) { return accountName(a); }, cell: function (a) { return el('div', { class: 'st-cell-name' }, [swatch(accountColor(a.id)), el('div', null, [a.name, el('small', null, a.platform_label || '—')])]); }, foot: function () { return 'Razem'; } },
      { key: 'units', label: 'Sztuki', value: function (a) { return a.units; }, cell: function (a) { return intFormat.format(a.units); }, foot: function () { return intFormat.format(totals.units); } },
      { key: 'orders', label: 'Zamówienia', value: function (a) { return a.orders; }, cell: function (a) { return intFormat.format(a.orders); }, foot: function () { return intFormat.format(totals.orders); } },
      { key: 'revenue', label: 'Przychód', value: function (a) { return a.revenue_cents; }, cell: function (a) {
        var codes = Object.keys(a.revenue);
        if (!codes.length) { return '—'; }
        return codes.map(function (code, index) { return index ? el('small', null, money(a.revenue[code], code)) : money(a.revenue[code], code); });
      }, foot: function () { return report.currencies.length ? report.currencies.map(function (c, index) { return index ? el('small', null, money(c.revenue_cents, c.code)) : money(c.revenue_cents, c.code); }) : '—'; } },
      { key: 'average', label: 'Śr. wartość zam.', value: function (a) { return a.orders_cur ? a.revenue_cents / a.orders_cur : 0; }, cell: function (a) { return a.orders_cur ? money(a.revenue_cents / a.orders_cur) : '—'; }, foot: function () { return totals.orders_cur ? money(totals.revenue_cents / totals.orders_cur) : '—'; } },
      { key: 'basket', label: 'Szt. / zam.', value: function (a) { return a.orders ? a.units / a.orders : 0; }, cell: function (a) { return a.orders ? twoFormat.format(a.units / a.orders) : '—'; }, foot: function () { return totals.orders ? twoFormat.format(totals.units / totals.orders) : '—'; } },
      { key: 'value', label: 'Udział: ' + METRICS[metric].label.toLowerCase(), value: function (a) { return rowValue(a, metric); }, cell: function (a) { return total > 0 ? percent(rowValue(a, metric) / total) : '—'; }, foot: function () { return total > 0 ? '100,0%' : '—'; } }
    ];
    if (hasPrevious) {
      columns.push({ key: 'change', label: 'Zmiana: ' + METRICS[metric].label.toLowerCase(), value: function (a) { return rowValue(a, metric) - rowValue(a.previous || {}, metric); }, cell: function (a) {
        var diff = rowValue(a, metric) - rowValue(a.previous || {}, metric);
        return [deltaChip(rowValue(a, metric), rowValue(a.previous || {}, metric)), el('small', null, signed(diff, function (v) { return formatMetric(v, metric); }))];
      }, foot: function () { return deltaChip(total, rowValue(report.previous_totals, metric)); } });
    }
    columns.push({ key: 'excluded', label: 'Poza sprzedażą', value: excludedShare, cell: function (a) { return a.excluded_orders ? [percent(excludedShare(a)), el('small', null, intFormat.format(a.excluded_orders) + ' zam.')] : '—'; }, foot: function () {
      var all = totals.orders + report.excluded.orders;
      return report.excluded.orders ? percent(report.excluded.orders / all) : '—';
    } });
    return columns;
  }

  function renderAccountsTable() {
    var host = q('[data-st-accounts-table]');
    if (!report.accounts.length) { clear(host).appendChild(empty('Brak zamówień w tym okresie.')); return; }
    sortableTable(host, accountColumns(), report.accounts, accountSort, renderAccountsTable, true);
  }

  function renderStatuses() {
    var rows = report.statuses.filter(function (status) { return status.orders > 0; });
    barList(q('[data-st-mini="statuses"]'), rows, {
      mark: function (status) { var dot = el('span', { class: 'st-dot' }); dot.style.background = status.color; return dot; },
      extra: function (status) { return status.included ? null : el('span', { class: 'st-tag' }, 'pominięty'); },
      muted: function (status) { return !status.included; }
    });
  }

  function renderMinis() {
    q('[data-st-minis-sub]').textContent = METRICS[state.metric].label + ' w zamówieniach liczonych do sprzedaży; lista statusów obejmuje także statusy pominięte';
    ['platforms', 'payment', 'delivery', 'basket'].forEach(function (key) {
      var rows = report[key] || [];
      if (key === 'payment') { rows = rows.filter(function (row) { return row.orders > 0; }); }
      barList(q('[data-st-mini="' + key + '"]'), rows);
    });
  }

  function productRows() {
    if (!productQuery) { return report.products; }
    return report.products.filter(function (product) { return (product.name + ' ' + product.sku).toLowerCase().indexOf(productQuery) !== -1; });
  }

  function renderProducts() {
    var host = q('[data-st-products]'), more = q('[data-st-products-more]'), totals = report.totals;
    var listed = report.products.length, all = report.products_count;
    q('[data-st-products-sub]').textContent = all ? intFormat.format(all) + ' ' + plural(all, 'produkt', 'produkty', 'produktów') + ' w okresie' + (all > listed ? ' · lista obejmuje ' + listed + ' najlepiej sprzedających się' : '') + ' · przychód z pozycji w ' + report.currency : 'Brak sprzedanych produktów w tym okresie.';
    var rows = productRows();
    if (!rows.length) { clear(host).appendChild(empty(productQuery ? 'Żaden produkt nie pasuje do wyszukiwania.' : 'Brak danych w tym okresie.')); more.hidden = true; return; }
    var maxUnits = maxOf(rows.map(function (product) { return product.units; }));
    sortableTable(host, [
      { key: 'rank', label: '#', left: true, className: 'st-rank', cell: function (product, index) { return String(index + 1); } },
      { key: 'name', label: 'Produkt', left: true, value: function (p) { return p.name; }, cell: function (p) { return el('div', { class: 'st-product-name' }, [p.name, p.sku ? el('small', null, 'SKU: ' + p.sku) : null]); } },
      { key: 'units', label: 'Sztuki', value: function (p) { return p.units; }, cell: function (p) {
        var bar = el('i'); bar.style.width = Math.max(2, Math.round(p.units / maxUnits * 120)) + 'px';
        return el('div', { class: 'st-inline-bar' }, [bar, el('span', null, intFormat.format(p.units))]);
      } },
      { key: 'orders', label: 'Zamówienia', value: function (p) { return p.orders; }, cell: function (p) { return intFormat.format(p.orders); } },
      { key: 'revenue_cents', label: 'Przychód', value: function (p) { return p.revenue_cents; }, cell: function (p) { return money(p.revenue_cents); } },
      { key: 'share', label: 'Udział w sztukach', cell: function (p) { return totals.units ? percent(p.units / totals.units) : '—'; } }
    ], rows, productSort, function () { productLimit = PRODUCT_STEP; renderProducts(); }, false, productLimit);
    more.hidden = rows.length <= productLimit;
    if (!more.hidden) { more.querySelector('button').textContent = 'Pokaż więcej (' + Math.min(productLimit, rows.length) + ' z ' + rows.length + ')'; }
  }

  /* ---------- eksport CSV ---------- */

  function csvCell(value) {
    if (typeof value === 'number') { return String(Math.round(value * 100) / 100).replace('.', ','); }
    var text = String(value === null || value === undefined ? '' : value);
    if (/^[=+\-@\t\r]/.test(text)) { text = "'" + text; } // nazwy z marketplace'ów nie mogą stać się formułą w Excelu
    return /[";\r\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
  }

  function exportCsv(kind) {
    var metric = state.metric, rows = [], header = [], totals = report.totals;
    if (kind === 'accounts') {
      header = ['Konto', 'Platforma', 'Sztuki', 'Zamówienia', 'Przychód ' + report.currency, 'Przychód w innych walutach', 'Śr. wartość zam. ' + report.currency, 'Szt. na zam.', 'Udział w sztukach %', 'Sztuki poprzedni okres', 'Zamówienia poprzedni okres', 'Zamówienia poza sprzedażą'];
      report.accounts.forEach(function (a) {
        var other = Object.keys(a.revenue).filter(function (code) { return code !== report.currency; }).map(function (code) { return money(a.revenue[code], code); }).join(', ');
        rows.push([a.name, a.platform_label, a.units, a.orders, a.revenue_cents / 100, other, a.orders_cur ? a.revenue_cents / a.orders_cur / 100 : '', a.orders ? a.units / a.orders : '', totals.units ? a.units / totals.units * 100 : '', a.previous ? a.previous.units : '', a.previous ? a.previous.orders : '', a.excluded_orders]);
      });
    } else if (kind === 'products') {
      header = ['Produkt', 'SKU', 'Sztuki', 'Zamówienia', 'Przychód ' + report.currency, 'Udział w sztukach %'];
      productRows().forEach(function (p) { rows.push([p.name, p.sku, p.units, p.orders, p.revenue_cents / 100, totals.units ? p.units / totals.units * 100 : '']); });
    } else {
      var series = timelineSeries(metric), sums = bucketTotals(metric), previous = report.previous_series ? report.previous_series[metric] : null;
      var scale = metric === 'revenue' ? 100 : 1;
      header = ['Od', 'Do'].concat(series.map(function (item) { return item.name; }), ['Razem']).concat(previous ? ['Poprzedni okres'] : []);
      report.buckets.forEach(function (bucket, index) {
        rows.push([bucket.from, bucket.to].concat(series.map(function (item) { return item.values[index] / scale; }), [sums[index] / scale]).concat(previous ? [previous[index] === undefined ? '' : previous[index] / scale] : []));
      });
      kind = 'czas_' + metric;
    }
    var csv = '﻿' + [header].concat(rows).map(function (row) { return row.map(csvCell).join(';'); }).join('\r\n');
    var link = el('a', { href: URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' })), download: 'statystyki_' + kind + '_' + report.period.from + '_' + report.period.to + '.csv' });
    document.body.appendChild(link);
    link.click();
    window.setTimeout(function () { URL.revokeObjectURL(link.href); document.body.removeChild(link); }, 500);
  }

  /* ---------- start ---------- */

  document.body.appendChild(tip);
  tip.classList.add('st');
  buildMenus();
  bindControls();
  loadState();
  syncControls();
  load();

  if (window.ResizeObserver) {
    var resizeFrame = 0;
    new ResizeObserver(function () {
      window.cancelAnimationFrame(resizeFrame);
      resizeFrame = window.requestAnimationFrame(function () {
        var host = q('[data-st-timeline]');
        if (report && host.clientWidth && Math.abs(host.clientWidth - timelineWidth) > 4) { renderTimeline(); }
      });
    }).observe(q('[data-st-timeline]'));
  }
  window.addEventListener('scroll', hideTip, { passive: true });
})();
