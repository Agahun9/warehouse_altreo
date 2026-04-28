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
              <div class="border rounded bg-light-subtle p-3 mt-3">
                <div class="small fw-semibold mb-2">Jak tego uzywac</div>
                <div class="small text-secondary mb-2">Przyklad calego wzoru:</div>
                <div class="small mb-3">
                  <code>Etui na Telefon {{ldelim}}field:product.allegro_parameter.123{rdelim} {{ldelim}}field:product.allegro_parameter.456{rdelim} wzory {{ldelim}}option:collection_name{rdelim}</code>
                </div>

                <div class="small fw-semibold mb-2">Podstawowa skladnia tokenow</div>
                <ul class="small text-secondary ps-3 mb-3">
                  <li>Pole produktu: <code>{{ldelim}}field:product.sku{rdelim}</code> albo <code>{{ldelim}}field:product.allegro_parameter.11484{rdelim}</code></li>
                  <li>Opcja z eksportu: <code>{{ldelim}}option:collection_name{rdelim}</code> albo <code>{{ldelim}}option:price_to_csv{rdelim}</code></li>
                  <li>Zamiana tekstu: po nazwie pola dodaj <code>+stare-nowe</code></li>
                  <li>Formatowanie: na koncu dodaj <code>=format</code></li>
                </ul>

                <div class="small fw-semibold mb-2">Kolejnosc</div>
                <div class="small text-secondary mb-3">
                  Najpierw wpisz pole, potem opcjonalne zamiany <code>+...</code>, a na koncu opcjonalny format <code>=...</code>.
                </div>

                <div class="small fw-semibold mb-2">Przyklady</div>
                <ul class="small text-secondary ps-3 mb-3">
                  <li>Zwykla wartosc: <code>{{ldelim}}field:product.allegro_parameter.249512{rdelim}</code></li>
                  <li>Jedna zamiana: <code>{{ldelim}}field:product.allegro_parameter.249512+Czarny-Czarna{rdelim}</code></li>
                  <li>Wiele zamian: <code>{{ldelim}}field:product.allegro_parameter.249512+Czarny-Czarna+Bialy-Biala+Niebieski-Niebieska{rdelim}</code></li>
                  <li>Samo formatowanie: <code>{{ldelim}}field:product.allegro_parameter.11484=upper{rdelim}</code></li>
                  <li>Zamiany i format razem: <code>{{ldelim}}field:product.allegro_parameter.249512+Czarny-Czarna=upper{rdelim}</code></li>
                </ul>

                <div class="small fw-semibold mb-2">Dostepne formaty</div>
                <ul class="small text-secondary ps-3 mb-0">
                  <li><code>upper</code> - zamienia tekst na wielkie litery</li>
                  <li><code>lower</code> - zamienia tekst na male litery</li>
                  <li><code>trim</code> - usuwa spacje z poczatku i konca</li>
                  <li><code>date:Y-m-d</code> - formatuje date</li>
                  <li><code>number:2:,: </code> - formatuje liczbe: 2 miejsca po przecinku, przecinek dziesietny, spacja tysieczna</li>
                </ul>
              </div>
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
