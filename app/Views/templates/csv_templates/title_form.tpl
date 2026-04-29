<main class="app-main">
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-6">
          <h3 class="mb-0">{$contentTitle|escape}</h3>
          <p class="text-secondary mb-0">{$pageDescription|escape}</p>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-end">
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=index">Start</a></li>
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=csvtemplates&action=index">Szablony CSV</a></li>
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=csvtemplates&action=titlegenerator">Generator tytulow</a></li>
            <li class="breadcrumb-item active" aria-current="page">{$breadcrumbCurrent|escape}</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      {if $flashSuccess}<div class="alert alert-success">{$flashSuccess|escape}</div>{/if}
      {if $flashError}<div class="alert alert-danger">{$flashError|escape}</div>{/if}

      <form method="post" action="{$formAction|escape}" id="title-template-form">
        <input type="hidden" name="id" value="{$titleTemplate.id|default:0|escape}">

        <div class="card mb-4">
          <div class="card-header"><h3 class="card-title mb-0">Dane glowne</h3></div>
          <div class="card-body row g-3">
            <div class="col-md-6">
              <label class="form-label">Nazwa szablonu tytulu</label>
              <input type="text" name="name" class="form-control" value="{$titleTemplate.name|default:''|escape}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Opis</label>
              <input type="text" name="description" class="form-control" value="{$titleTemplate.description|default:''|escape}">
            </div>
            <div class="col-12">
              <label class="form-label">Wzor tytulu</label>
              <textarea name="template_body" id="templateBody" class="form-control" rows="5" required>{$titleTemplate.template_body|default:''|escape}</textarea>
              <div class="form-text">Przyklad: <code>Etui na Telefon {{ldelim}}field:product.allegro_parameter.123{rdelim} {{ldelim}}field:product.allegro_parameter.456{rdelim} wzory {{ldelim}}option:collection_name{rdelim}</code></div>
              <div class="form-text">Znajdz i zamien w tokenie: <code>{{ldelim}}field:product.allegro_parameter.249512+Czarny-Czarna{rdelim}</code> zamieni <code>Czarny</code> na <code>Czarna</code> w wartosci tego pola.</div>
              <div class="form-text">Wiele zamian naraz: <code>{{ldelim}}field:product.allegro_parameter.249512+Czarny-Czarna+Bialy-Biala+Niebieski-Niebieska{rdelim}</code>.</div>
              <div class="form-text">Makra: <code>{{ldelim}}field:product.allegro_parameter.249512=upper{rdelim}</code>.aaaa</div>

              <div class="form-text mt-2"><strong>Jak tego uzywac:</strong></div>
              <div class="form-text">1. Zwykla wartosc pola: <code>{{ldelim}}field:product.sku{rdelim}</code> albo <code>{{ldelim}}field:product.allegro_parameter.11484{rdelim}</code>.</div>
              <div class="form-text">2. Opcja z eksportu: <code>{{ldelim}}option:collection_name{rdelim}</code> albo <code>{{ldelim}}option:price_to_csv{rdelim}</code>.</div>
              <div class="form-text">3. Zamiana tekstu w wartosci: po nazwie pola dodaj <code>+stare-nowe</code>, np. <code>{{ldelim}}field:product.allegro_parameter.249512+Czarny-Czarna{rdelim}</code>.</div>
              <div class="form-text">4. Formatowanie: na koncu dodaj <code>=format</code>, np. <code>{{ldelim}}field:product.allegro_parameter.11484=upper{rdelim}</code>.</div>
              <div class="form-text">5. Kolejnosc jest taka: najpierw pole, potem opcjonalne zamiany <code>+...</code>, a na koncu opcjonalny format <code>=...</code>.</div>
              <div class="form-text mt-2">Formatowanie w tokenie po znaku <code>=</code>:
                <code>upper</code> - wielkie litery,
                <code>lower</code> - male litery,
                <code>trim</code> - usuniecie spacji z poczatku i konca,
                <code>date:Y-m-d</code> - format daty,
                <code>number:2:,: </code> - liczba z 2 miejscami, przecinkiem dziesietnym i spacjami tysiecznymi.
              </div>
              <div class="form-text">Przyklad formatu: <code>{{ldelim}}field:product.allegro_parameter.11484=upper{rdelim}</code></div>
              <div class="form-text">Mozesz tez laczyc zamiany z formatem: <code>{{ldelim}}field:product.allegro_parameter.249512+Czarny-Czarna=upper{rdelim}</code></div>
            </div>
          </div>
        </div>

        <div class="card mb-4">
          <div class="card-header"><h3 class="card-title mb-0">Wstaw token z listy</h3></div>
          <div class="card-body">
            <div class="row g-2 align-items-end">
              <div class="col-md-8">
                <label class="form-label">Szukaj tokenu</label>
                <input type="text" id="tokenSearch" class="form-control mb-2" placeholder="Wpisz fragment nazwy albo tokenu">
                <label class="form-label">Dostepne tokeny</label>
                <select id="tokenSelect" class="form-select">
                  <option value="">Wybierz token do wstawienia</option>
                  {foreach $availableTitleTokens as $token => $label}
                    <option value="{$token|escape}">{$label|escape} - {$token|escape}</option>
                  {/foreach}
                </select>
              </div>
              <div class="col-md-4 d-grid">
                <button type="button" id="insertTokenBtn" class="btn btn-outline-primary">Wstaw do wzoru</button>
              </div>
            </div>
            <div class="mt-3">
              <div class="small fw-semibold mb-2">Szybkie tokeny eksportowe</div>
              <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-sm btn-outline-secondary js-quick-token" data-token="{{ldelim}}option:collection_name{rdelim}">Kolekcja</button>
                <button type="button" class="btn btn-sm btn-outline-secondary js-quick-token" data-token="{{ldelim}}option:price_to_csv{rdelim}">Cena z eksportu</button>
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between mb-4">
          <a href="{$baseUrl}?controller=csvtemplates&action=titlegenerator" class="btn btn-outline-secondary">Wroc do generatora</a>
          <button type="submit" class="btn btn-primary">Zapisz szablon tytulu</button>
        </div>
      </form>
    </div>
  </div>
</main>

<script>
(function () {
  var textarea = document.getElementById('templateBody');
  var select = document.getElementById('tokenSelect');
  var tokenSearch = document.getElementById('tokenSearch');
  var insertButton = document.getElementById('insertTokenBtn');
  var quickButtons = document.querySelectorAll('.js-quick-token');

  function insertAtCursor(token) {
    if (!textarea || !token) {
      return;
    }

    var start = textarea.selectionStart || 0;
    var end = textarea.selectionEnd || 0;
    var current = textarea.value || '';
    textarea.value = current.slice(0, start) + token + current.slice(end);
    textarea.focus();
    var caret = start + token.length;
    textarea.setSelectionRange(caret, caret);
  }

  if (insertButton && select) {
    insertButton.addEventListener('click', function () {
      if (select.value) {
        insertAtCursor(select.value);
      }
    });
  }

  for (var i = 0; i < quickButtons.length; i++) {
    quickButtons[i].addEventListener('click', function () {
      insertAtCursor(this.getAttribute('data-token') || '');
    });
  }

  if (tokenSearch && select) {
    tokenSearch.addEventListener('input', function () {
      var query = String(tokenSearch.value || '').toLowerCase().trim();
      var options = select.querySelectorAll('option');
      for (var optionIndex = 0; optionIndex < options.length; optionIndex++) {
        if (optionIndex === 0) {
          options[optionIndex].hidden = false;
          continue;
        }

        var haystack = String(options[optionIndex].textContent || '').toLowerCase();
        options[optionIndex].hidden = query !== '' && haystack.indexOf(query) === -1;
      }
    });
  }
})();
</script>
