(() => {
  'use strict';
  const PARTY_KEYS = ['company', 'first_name', 'last_name', 'street', 'building', 'postal_code', 'city', 'country'];
  const normalizeNip = value => String(value || '').trim().replace(/^PL/i, '').replace(/\D/g, '');
  const validNip = nip => {
    if (!/^[1-9]((\d[1-9])|([1-9]\d))\d{7}$/.test(nip)) return false;
    const sum = [6, 5, 7, 2, 3, 4, 5, 6, 7].reduce((acc, weight, i) => acc + weight * Number(nip[i]), 0);
    return sum % 11 === Number(nip[9]);
  };
  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));

  document.querySelectorAll('[data-doc-edit-form]').forEach(form => {
    const party = name => form.querySelector(`[data-party="${name}"]`);
    const buyer = party('buyer');
    const recipient = party('recipient');
    if (!buyer || !recipient) return;
    const field = (box, key) => box.querySelector(`[data-field="${key}"]`);
    const values = box => Object.fromEntries([...box.querySelectorAll('[data-field]')].map(input => [input.dataset.field, input.value.trim()]));
    const nipInput = buyer.querySelector('[data-party-nip]');
    const lookupButton = buyer.querySelector('[data-gus-lookup]');
    const lookupBox = buyer.querySelector('[data-gus-result]');
    const sameBox = recipient.querySelector('[data-recipient-same]');
    const isInvoice = /^invoice/.test(form.dataset.docKind || '');

    // Ten sam format co OrderDocumentService::partyText() — podgląd bloku drukowanego na dokumencie.
    const partyText = (data, isRecipient) => {
      const company = data.company || '';
      const person = [data.first_name, data.last_name].filter(Boolean).join(' ');
      const nip = isRecipient ? '' : (data.nip || '');
      const lines = [company || person];
      if (company && person && !nip && !company.toLowerCase().includes(person.toLowerCase())) lines.push(person);
      if (nip) lines.push(`NIP: ${nip}`);
      lines.push([data.street, data.building].filter(Boolean).join(' '));
      lines.push([data.postal_code, data.city].filter(Boolean).join(' '));
      if (data.country && data.country.toUpperCase() !== 'PL') lines.push(data.country.toUpperCase());
      if (isRecipient) {
        if (data.phone) lines.push(`Tel: ${data.phone}`);
        if (data.pickup) lines.push(`Punkt odbioru: ${data.pickup}`);
        if (data.delivery) lines.push(`Dostawa: ${data.delivery}`);
      }
      return lines.filter(Boolean).join('\n');
    };
    const buyerValues = () => ({ ...values(buyer), nip: nipInput.value.trim() });
    const renderPreview = () => {
      const buyerPreview = form.querySelector('[data-party-preview="buyer"]');
      const recipientPreview = form.querySelector('[data-party-preview="recipient"]');
      if (buyerPreview) buyerPreview.textContent = partyText(buyerValues(), false) || '—';
      if (recipientPreview) recipientPreview.textContent = partyText(values(recipient), true) || '—';
    };

    const copyBuyerToRecipient = () => {
      const data = values(buyer);
      PARTY_KEYS.forEach(key => { const target = field(recipient, key); if (target) target.value = data[key] || ''; });
    };
    const setSame = on => {
      recipient.classList.toggle('is-same', on);
      recipient.querySelectorAll('[data-sync]').forEach(input => { input.readOnly = on; });
      if (on) copyBuyerToRecipient();
      renderPreview();
    };
    const sameAtStart = () => {
      const b = values(buyer), r = values(recipient);
      return Boolean(b.company || b.first_name || b.last_name) && PARTY_KEYS.every(key => (b[key] || '') === (r[key] || ''));
    };
    if (sameBox) {
      sameBox.checked = sameAtStart();
      setSame(sameBox.checked);
      sameBox.addEventListener('change', () => setSame(sameBox.checked));
    }
    recipient.querySelector('[data-copy-buyer]')?.addEventListener('click', () => { copyBuyerToRecipient(); renderPreview(); });

    const checkNip = () => {
      const raw = nipInput.value.trim();
      const country = (field(buyer, 'country')?.value || 'PL').toUpperCase();
      const polish = country === 'PL' || /^PL/i.test(raw);
      nipInput.setCustomValidity(raw && polish && !validNip(normalizeNip(raw)) ? 'Nieprawidłowy NIP — suma kontrolna się nie zgadza.' : '');
      nipInput.closest('label')?.classList.toggle('is-invalid', Boolean(nipInput.validationMessage));
      if (lookupButton) lookupButton.disabled = !validNip(normalizeNip(raw));
    };
    nipInput.addEventListener('input', checkNip);
    nipInput.addEventListener('blur', () => { const nip = normalizeNip(nipInput.value); if (validNip(nip)) nipInput.value = nip; checkNip(); renderPreview(); });
    nipInput.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); if (!lookupButton.disabled) lookupButton.click(); } });
    checkNip();

    form.querySelectorAll('[data-postal]').forEach(input => input.addEventListener('blur', () => {
      const country = (input.closest('[data-party]')?.querySelector('[data-country]')?.value || 'PL').toUpperCase();
      const digits = input.value.replace(/\D/g, '');
      if (country === 'PL' && digits.length === 5) input.value = `${digits.slice(0, 2)}-${digits.slice(2)}`;
      renderPreview();
    }));
    form.querySelectorAll('[data-country]').forEach(input => input.addEventListener('input', () => { input.value = input.value.toUpperCase(); }));

    form.addEventListener('input', event => {
      if (sameBox?.checked && buyer.contains(event.target)) copyBuyerToRecipient();
      if (event.target.closest('[data-party]')) renderPreview();
    });

    // GUS / Biała lista: wypełnia pola nabywcy, zaznacza zmienione i pozwala cofnąć.
    let undo = null;
    const showLookup = html => { lookupBox.innerHTML = html; lookupBox.hidden = false; };
    lookupButton?.addEventListener('click', async () => {
      const nip = normalizeNip(nipInput.value);
      if (!validNip(nip)) { checkNip(); nipInput.reportValidity(); return; }
      lookupButton.disabled = true;
      lookupButton.classList.add('is-loading');
      showLookup('<div class="sc-de-lookup-state"><i class="bi bi-hourglass-split"></i> Szukam firmy w rejestrach…</div>');
      try {
        const response = await fetch(`index.php?controller=orders&action=companylookup&nip=${encodeURIComponent(nip)}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.company) throw new Error(data.error || `Błąd HTTP ${response.status}`);
        const company = data.company;
        undo = { nip: nipInput.value, ...values(buyer) };
        const fill = { ...company, nip };
        const changed = [];
        [...PARTY_KEYS, 'nip'].forEach(key => {
          const input = key === 'nip' ? nipInput : field(buyer, key);
          if (!input || fill[key] === undefined) return;
          const next = String(fill[key] || '');
          // Imię i nazwisko zostają, gdy rejestr ich nie podał (np. spółka albo Biała lista).
          if (!next && (key === 'first_name' || key === 'last_name')) return;
          if (input.value.trim() !== next) { input.value = next; changed.push(input); }
        });
        changed.forEach(input => { input.closest('label')?.classList.remove('is-filled'); void input.offsetWidth; input.closest('label')?.classList.add('is-filled'); });
        if (sameBox?.checked) copyBuyerToRecipient();
        checkNip(); renderPreview();
        const address = [[company.street, company.building].filter(Boolean).join(' '), [company.postal_code, company.city].filter(Boolean).join(' ')].filter(Boolean).join(', ');
        const vat = company.vat_status ? `<span class="sc-de-badge ${company.vat_status === 'Czynny' ? 'is-ok' : 'is-warn'}">VAT: ${escapeHtml(company.vat_status)}</span>` : '';
        const ids = [company.regon ? `REGON ${escapeHtml(company.regon)}` : '', company.krs ? `KRS ${escapeHtml(company.krs)}` : ''].filter(Boolean).join(' · ');
        const warnings = (company.warnings || []).map(text => `<li>${escapeHtml(text)}</li>`).join('');
        showLookup(`<div class="sc-de-lookup-card${company.active === false ? ' is-ended' : ''}">
          <div class="sc-de-lookup-head"><i class="bi bi-patch-check-fill"></i><strong>${escapeHtml(company.company)}</strong>${vat}</div>
          ${company.first_name || company.last_name ? `<p><i class="bi bi-person"></i> ${escapeHtml([company.first_name, company.last_name].filter(Boolean).join(' '))}</p>` : ''}
          ${address ? `<p><i class="bi bi-geo-alt"></i> ${escapeHtml(address)}</p>` : ''}
          ${ids ? `<p class="sc-de-lookup-ids">${ids}</p>` : ''}
          ${warnings ? `<ul class="sc-de-lookup-warn">${warnings}</ul>` : ''}
          <div class="sc-de-lookup-foot"><small>Źródło: ${escapeHtml((company.sources || []).join(' + ') || 'rejestr')} · ${changed.length ? `uzupełniono ${changed.length} ${changed.length === 1 ? 'pole' : changed.length < 5 ? 'pola' : 'pól'}` : 'dane bez zmian'}</small>${changed.length ? '<button type="button" class="om-btn om-small" data-gus-undo><i class="bi bi-arrow-counterclockwise"></i> Cofnij</button>' : ''}</div>
        </div>`);
      } catch (error) {
        showLookup(`<div class="sc-de-lookup-state is-error"><i class="bi bi-exclamation-triangle"></i> ${escapeHtml(error.message || 'Nie udało się pobrać danych.')}</div>`);
      } finally {
        lookupButton.classList.remove('is-loading');
        checkNip();
      }
    });
    lookupBox?.addEventListener('click', event => {
      if (!event.target.closest('[data-gus-undo]') || !undo) return;
      nipInput.value = undo.nip;
      PARTY_KEYS.forEach(key => { const input = field(buyer, key); if (input) input.value = undo[key] || ''; });
      undo = null; lookupBox.hidden = true;
      if (sameBox?.checked) copyBuyerToRecipient();
      checkNip(); renderPreview();
    });

    form.addEventListener('submit', event => {
      const b = values(buyer);
      const companyInput = field(buyer, 'company');
      let message = '';
      if (!b.company && !(b.first_name || b.last_name)) message = 'Podaj nazwę firmy albo imię i nazwisko nabywcy.';
      else if (isInvoice && nipInput.value.trim() && !b.company) message = 'Nabywca z NIP: podaj nazwę firmy.';
      companyInput.setCustomValidity(message);
      if (!form.checkValidity()) { event.preventDefault(); form.reportValidity(); companyInput.addEventListener('input', () => companyInput.setCustomValidity(''), { once: true }); return; }
      // Blokada podwójnego wysłania; wartość klikniętego przycisku (after_save) trafia do ukrytego pola.
      const submitter = event.submitter;
      if (submitter?.name) {
        const hidden = document.createElement('input');
        hidden.type = 'hidden'; hidden.name = submitter.name; hidden.value = submitter.value;
        form.append(hidden);
      }
      if (submitter?.value === 'ksef') submitter.innerHTML = '<i class="bi bi-hourglass-split"></i> Zapisywanie i wysyłka do KSeF…';
      setTimeout(() => form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; }), 0);
    });
    renderPreview();
  });
})();
