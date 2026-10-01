(() => {
  'use strict';
  const root = document.querySelector('[data-documents-panel]');
  if (!root) return;
  const tabs = [...root.querySelectorAll('[data-docs-tab]')];
  const panels = [...root.querySelectorAll('[data-docs-panel]')];
  const storageKey = 'sc-document-panel:' + location.pathname;
  const activate = (name, remember = true) => {
    tabs.forEach(tab => {
      const selected = tab.dataset.docsTab === name;
      tab.classList.toggle('is-active', selected);
      tab.setAttribute('aria-selected', String(selected));
      tab.tabIndex = selected ? 0 : -1;
    });
    panels.forEach(panel => { panel.hidden = panel.dataset.docsPanel !== name; });
    if (remember) { try { sessionStorage.setItem(storageKey, name); } catch (_) {} }
  };
  const openHash = () => {
    if (location.hash.startsWith('#om-series-')) {
      activate('series');
      const series = document.getElementById(location.hash.slice(1));
      if (series) { series.open = true; series.scrollIntoView({ block: 'start' }); }
    } else if (location.hash === '#sc-docs-series') activate('series');
    else if (location.hash === '#sc-docs-history') activate('history');
  };
  let initial = 'history';
  try { if (sessionStorage.getItem(storageKey) === 'series') initial = 'series'; } catch (_) {}
  activate(initial, false);
  openHash();
  window.addEventListener('hashchange', openHash);
  tabs.forEach((tab, index) => {
    tab.addEventListener('click', event => {
      event.preventDefault();
      activate(tab.dataset.docsTab);
      history.replaceState(null, '', tab.getAttribute('href'));
    });
    tab.addEventListener('keydown', event => {
      let next;
      if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
      else if (event.key === 'ArrowLeft') next = (index + tabs.length - 1) % tabs.length;
      else if (event.key === 'Home') next = 0;
      else if (event.key === 'End') next = tabs.length - 1;
      else return;
      event.preventDefault();
      tabs[next].click();
      tabs[next].focus();
    });
  });
  root.querySelectorAll('[data-docs-add-series]').forEach(button => button.addEventListener('click', () => {
    activate('series');
    const form = root.querySelector('[data-docs-new-series]');
    if (form) {
      form.open = true;
      form.scrollIntoView({ block: 'start', behavior: 'smooth' });
      form.querySelector('[name="name"]')?.focus({ preventScroll: true });
    }
  }));
  const rows = [...root.querySelectorAll('[data-document-row]')];
  const search = root.querySelector('[data-docs-search]');
  const filters = [...root.querySelectorAll('[data-docs-kind]')];
  const result = root.querySelector('[data-docs-result]');
  const empty = root.querySelector('[data-docs-no-match]');
  let kind = 'all';
  const normalize = value => String(value).toLocaleLowerCase('pl').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/ł/g, 'l');
  const filterRows = () => {
    const query = normalize(search?.value || '').trim();
    let count = 0;
    rows.forEach(row => {
      const matchesKind = kind === 'all' || (kind === 'correction' ? row.dataset.documentKind.endsWith('_correction') : row.dataset.documentKind === kind);
      row.hidden = !matchesKind || !normalize(row.dataset.documentSearch).includes(query);
      if (!row.hidden) count++;
    });
    if (result) result.textContent = count + ' / ' + rows.length;
    if (empty) empty.hidden = count > 0 || rows.length === 0;
  };
  search?.addEventListener('input', filterRows);
  filters.forEach(button => button.addEventListener('click', () => {
    kind = button.dataset.docsKind;
    filters.forEach(item => { item.classList.toggle('is-active', item === button); item.setAttribute('aria-pressed', String(item === button)); });
    filterRows();
  }));
  root.querySelectorAll('[data-docs-series-form]').forEach(form => {
    const kindInput = form.querySelector('[name="kind"]');
    const prefix = form.querySelector('[name="prefix"]');
    let autoPrefix = form.hasAttribute('data-new-series-form');
    prefix?.addEventListener('input', () => { autoPrefix = false; });
    const updateKind = () => {
      const selectedKind = kindInput?.value || 'invoice';
      form.querySelectorAll('[data-series-for]').forEach(field => { field.hidden = !field.dataset.seriesFor.split(' ').includes(selectedKind); });
      const printer = form.querySelector('[name="fiscal_printer_id"]');
      if (printer && selectedKind !== 'receipt') printer.value = '0';
      const correction = form.querySelector('[name="correct_series_id"]');
      const correctionKind = selectedKind === 'invoice' ? 'invoice_correction' : selectedKind === 'receipt' ? 'receipt_correction' : '';
      correction?.querySelectorAll('[data-correction-kind]').forEach(option => { option.disabled = option.dataset.correctionKind !== correctionKind; });
      if (correction?.selectedOptions[0]?.disabled) correction.value = '0';
      if (autoPrefix && prefix) prefix.value = ({ invoice: 'FV', receipt: 'PA', invoice_correction: 'KF', receipt_correction: 'KP' })[selectedKind];
    };
    const updatePreview = () => {
      const preview = form.querySelector('[data-number-preview]');
      if (!preview) return;
      const value = name => form.querySelector('[name="' + name + '"]')?.value || '';
      const now = new Date();
      const number = (value('next_number') || '1').padStart(Number(value('document_number_length')) || 0, '0');
      const parts = [value('prefix'), number, value('numbering_format') === 'MONTHLY' ? String(now.getMonth() + 1).padStart(2, '0') : '', String(now.getFullYear()), value('suffix')];
      preview.textContent = parts.filter(Boolean).join('/');
    };
    const updateConditional = () => {
      form.querySelectorAll('[data-series-when]').forEach(field => {
        field.hidden = form.querySelector('[name="' + field.dataset.seriesWhen + '"]')?.value !== 'static';
      });
    };
    kindInput?.addEventListener('change', () => { updateKind(); updatePreview(); });
    form.addEventListener('input', updatePreview);
    form.addEventListener('change', () => { updateConditional(); updatePreview(); });
    form.addEventListener('invalid', event => {
      let parent = event.target.parentElement;
      while (parent && parent !== form) { if (parent.tagName === 'DETAILS') parent.open = true; parent = parent.parentElement; }
    }, true);
    updateKind();
    updateConditional();
    updatePreview();
  });
  filterRows();
})();
