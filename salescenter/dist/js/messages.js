/* SalesCenter – Wiadomości: szablony odpowiedzi, podgląd reguł, liczniki znaków, zaznaczanie wątków. */
(function () {
  'use strict';

  function parse(value, fallback) {
    try { return JSON.parse(value || ''); } catch (e) { return fallback; }
  }

  function fill(template, data) {
    return String(template || '').replace(/\{(klient|zamowienie|temat|numer|platforma|konto|godziny|podpis)\}/g, function (match, key) {
      return data[key] !== undefined && data[key] !== null ? String(data[key]) : '';
    }).replace(/[ \t]+\n/g, '\n').trim();
  }

  function insertAtCursor(field, text) {
    var start = field.selectionStart || 0;
    var end = field.selectionEnd || 0;
    field.value = field.value.slice(0, start) + text + field.value.slice(end);
    field.selectionStart = field.selectionEnd = start + text.length;
    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.focus();
  }

  // Wątek: szablony z reguł, licznik znaków, przewinięcie do ostatniej wiadomości.
  var thread = document.querySelector('[data-ms-thread]');
  if (thread) {
    var data = parse(thread.getAttribute('data-ms-thread'), {});
    var templates = parse(thread.getAttribute('data-ms-templates'), []);
    var text = thread.querySelector('[data-ms-text]');
    var picker = thread.querySelector('[data-ms-insert]');
    var counter = thread.querySelector('[data-ms-counter]');
    var conversation = thread.querySelector('[data-ms-conversation]');
    if (conversation) { conversation.scrollTop = conversation.scrollHeight; }
    if (picker) {
      if (!templates.length) { picker.hidden = true; }
      templates.forEach(function (item, index) {
        var option = document.createElement('option');
        option.value = String(index);
        option.textContent = item.name;
        picker.appendChild(option);
      });
      picker.addEventListener('change', function () {
        var item = templates[Number(picker.value)];
        if (item && text) { text.value = fill(item.text, data); text.dispatchEvent(new Event('input')); text.focus(); }
        picker.value = '';
      });
    }
    if (text && counter) {
      var update = function () {
        var max = Number(text.getAttribute('maxlength') || 0);
        counter.textContent = text.value.length + (max ? ' / ' + max : '') + ' znaków';
        counter.classList.toggle('is-over', max > 0 && text.value.length > max);
      };
      text.addEventListener('input', update);
      update();
    }
    var decision = thread.querySelector('[data-ms-decision-select]');
    var refund = thread.querySelector('[data-ms-refund]');
    if (decision && refund) {
      decision.addEventListener('change', function () {
        var partial = decision.value === 'ACCEPTED_PARTIAL_REFUND';
        refund.hidden = !partial;
        refund.querySelector('input').required = partial;
      });
    }
  }

  // Zaznaczanie wątków do zmiany statusu.
  var bulk = document.querySelector('[data-ms-bulk]');
  if (bulk) {
    var actions = bulk.querySelector('[data-ms-bulk-actions]');
    var selected = bulk.querySelector('[data-ms-selected]');
    bulk.addEventListener('change', function (event) {
      if (!event.target.matches('[data-ms-check]') || !actions) { return; }
      var count = bulk.querySelectorAll('[data-ms-check]:checked').length;
      actions.hidden = count === 0;
      selected.textContent = String(count);
    });
  }

  // Edytor reguły: gotowe szablony, znaczniki, podgląd.
  var form = document.querySelector('[data-ms-rule-form]');
  if (form) {
    var template = form.querySelector('[data-ms-template]');
    var preview = form.querySelector('[data-ms-preview]');
    var sample = { klient: 'Jan Kowalski', zamowienie: '8a3f-1204', temat: 'Pytanie o zamówienie', numer: '12/2026', platforma: 'Allegro', konto: 'Allegro · Sklep', godziny: 'pon.–pt. 08:00–16:00', podpis: 'Pozdrawiamy,\nZespół obsługi klienta' };
    var refresh = function () { if (preview && template) { preview.textContent = fill(template.value, sample); } };
    if (template) { template.addEventListener('input', refresh); refresh(); }
    form.querySelectorAll('[data-ms-placeholder]').forEach(function (button) {
      button.addEventListener('click', function () { insertAtCursor(template, button.getAttribute('data-ms-placeholder')); });
    });
    var platform = form.querySelector('[data-ms-rule-platform]');
    var account = form.querySelector('[name="connection_id"]');
    var filterAccounts = function () {
      if (!platform || !account) { return; }
      Array.prototype.forEach.call(account.options, function (option) {
        var other = option.value !== '0' && platform.value !== '' && option.getAttribute('data-platform') !== platform.value;
        option.hidden = other;
        if (other && option.selected) { account.value = '0'; }
      });
    };
    if (platform) { platform.addEventListener('change', filterAccounts); filterAccounts(); }

    var presets = {
      ack: { name: 'Potwierdzenie otrzymania wiadomości', trigger: 'first_message', kinds: ['message'], text: 'Dzień dobry {klient},\n\ndziękujemy za wiadomość. Odpowiemy najszybciej, jak to możliwe – zwykle w ciągu kilku godzin w dni robocze ({godziny}).\n\n{podpis}' },
      hours: { name: 'Poza godzinami pracy', trigger: 'outside_hours', kinds: ['message', 'dispute', 'claim'], cooldown: 12, text: 'Dzień dobry,\n\ndziękujemy za wiadomość. Nasze biuro obsługi pracuje w godzinach {godziny}. Odpowiemy w najbliższym dniu roboczym.\n\n{podpis}' },
      claim: { name: 'Reklamacja przyjęta do rozpatrzenia', trigger: 'new_case', kinds: ['claim'], platform: 'allegro', text: 'Dzień dobry {klient},\n\npotwierdzamy otrzymanie reklamacji {numer}. Rozpatrzymy ją niezwłocznie, nie później niż w ustawowym terminie 14 dni. Jeśli będziemy potrzebować zdjęć lub dodatkowych informacji, napiszemy w tej rozmowie.\n\n{podpis}' },
      dispute: { name: 'Dyskusja – pierwsza odpowiedź', trigger: 'new_case', kinds: ['dispute'], platform: 'allegro', text: 'Dzień dobry {klient},\n\nprzykro nam, że pojawił się problem z zamówieniem. Sprawdzamy sprawę i wrócimy z rozwiązaniem najszybciej, jak to możliwe.\n\n{podpis}' },
      incident: { name: 'Incydent – kontakt z klientem', trigger: 'new_case', kinds: ['incident'], text: 'Dzień dobry {klient},\n\notrzymaliśmy zgłoszenie dotyczące zamówienia {zamowienie}. Przykro nam z powodu problemu – już go analizujemy i wkrótce przekażemy informację o rozwiązaniu.\n\n{podpis}' },
      shipping: { name: 'Pytanie o wysyłkę', trigger: 'any_message', kinds: ['message'], keywords: 'kiedy wysyłka, kiedy wyślecie, numer przesyłki, status zamówienia, gdzie paczka', delay: 15, text: 'Dzień dobry {klient},\n\ndziękujemy za pytanie. Sprawdzamy status zamówienia {zamowienie} i wkrótce odpiszemy z informacją o wysyłce. Numer przesyłki pojawia się też w szczegółach zamówienia zaraz po nadaniu paczki.\n\n{podpis}' }
    };
    document.querySelectorAll('[data-ms-preset]').forEach(function (button) {
      button.addEventListener('click', function () {
        var preset = presets[button.getAttribute('data-ms-preset')];
        if (!preset) { return; }
        form.querySelector('[name="name"]').value = preset.name;
        form.querySelector('[name="trigger_name"]').value = preset.trigger;
        form.querySelector('[name="keywords"]').value = preset.keywords || '';
        form.querySelector('[name="delay_minutes"]').value = String(preset.delay || 0);
        form.querySelector('[name="cooldown_hours"]').value = String(preset.cooldown || 24);
        if (platform) { platform.value = preset.platform || ''; filterAccounts(); }
        form.querySelectorAll('[name="kinds[]"]').forEach(function (box) { box.checked = preset.kinds.indexOf(box.value) !== -1; });
        template.value = preset.text;
        refresh();
        template.focus();
      });
    });
  }
})();
