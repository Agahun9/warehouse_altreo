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
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=administration&action=automation">Administracja</a></li>
            <li class="breadcrumb-item active" aria-current="page">{$breadcrumbCurrent|escape}</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      <style>
        .api-admin-card {
          border-radius: 1.15rem;
          border: 1px solid rgba(15, 23, 42, 0.08);
          box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
        }
        .api-tabs .nav-link {
          border-radius: 999px;
        }
        .api-doc-text {
          max-height: 75vh;
          overflow: auto;
          margin: 0;
          padding: 1.25rem 1.5rem;
          background: #0f172a;
          color: #e2e8f0;
          border-radius: 0 0 1.15rem 1.15rem;
          font-size: 0.85rem;
          line-height: 1.55;
          white-space: pre-wrap;
          word-break: break-word;
        }
        .api-categories-table {
          max-height: 75vh;
          overflow: auto;
        }
      </style>

      <ul class="nav nav-pills api-tabs mb-3 gap-2">
        <li class="nav-item"><a class="nav-link" href="{$baseUrl}?controller=administration&action=apitokens"><i class="bi bi-key me-1"></i>Tokeny API</a></li>
        <li class="nav-item"><a class="nav-link active" href="{$baseUrl}?controller=administration&action=apidocs"><i class="bi bi-journal-code me-1"></i>Instrukcja API</a></li>
      </ul>

      <div class="row g-4">
        <div class="col-xxl-8">
          <div class="card api-admin-card">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
              <div>
                <strong>Instrukcja dla integratora</strong>
                <div class="small text-secondary">Skopiuj albo pobierz i przeslij razem z tokenem (token wyslij osobnym kanalem).</div>
              </div>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary btn-sm" id="api-doc-copy"><i class="bi bi-clipboard me-1"></i>Kopiuj instrukcje</button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="api-doc-download"><i class="bi bi-download me-1"></i>Pobierz .md</button>
              </div>
            </div>
<pre class="api-doc-text" id="api-doc-text"># ALTREO Magazyn - API produktow (v1)

API tylko do odczytu. Pozwala pobrac pelna liste produktow z wybranej kategorii
(dane podstawowe, EAN, stan magazynowy, ceny, zdjecia, pola wlasne oraz parametry
marketplace: Allegro, Empik, MediaMarkt, Temu).


## 1. Adres bazowy

    {$apiIndexUrl|escape}

Wszystkie wywolania to zapytania GET na ten adres z parametrami `controller=api` i `action=...`.
Odpowiedzi sa w formacie JSON (UTF-8).


## 2. Autoryzacja

Kazde zapytanie musi zawierac token API wydany przez administratora magazynu.
Preferowany sposob - naglowek HTTP:

    Authorization: Bearer &lt;TOKEN&gt;

Alternatywy (gdy serwer/proxy usuwa naglowek Authorization):

    X-Api-Token: &lt;TOKEN&gt;
    ...&amp;api_token=&lt;TOKEN&gt;          (parametr URL - najmniej zalecany, trafia do logow)

Token traktuj jak haslo: nie umieszczaj go w kodzie frontendu/przegladarki, trzymaj po stronie serwera.


## 3. Endpointy

### 3.1. Lista kategorii (tu znajdziesz ID kategorii)

    GET {$apiIndexUrl|escape}?controller=api&amp;action=categories

Zwraca: `items[]` z polami `id`, `name`, `slug`, `sku_prefix`, `description`,
`allegro_category_id`, `empik_category_id`, `mediamarkt_category_id`,
`temu_category_id`, `temu_category_name`, `temu_category_path`, `products_count`.

### 3.2. Produkty z kategorii (glowny endpoint)

    GET {$apiIndexUrl|escape}?controller=api&amp;action=products&amp;category_id=5&amp;page=1&amp;per_page=100

Parametry:

| Parametr        | Wymagany | Opis |
|-----------------|----------|------|
| category_id     | TAK      | ID kategorii. Mozna podac kilka po przecinku: `5,7,12` |
| page            | nie      | Numer strony, od 1 (domyslnie 1) |
| per_page        | nie      | Liczba produktow na strone, 1-500 (domyslnie 100) |
| updated_since   | nie      | Tylko produkty zmienione od daty, np. `2026-01-31` lub `2026-01-31T12:00:00` |
| in_stock        | nie      | `1` = tylko produkty z iloscia &gt; 0 |
| parameter_names | nie      | `0` = nie dociagaj nazw parametrow Allegro (szybciej). Domyslnie `1` |

Produkty sa sortowane rosnaco po `id`, wiec stronicowanie jest stabilne.

### 3.3. Pojedynczy produkt

    GET {$apiIndexUrl|escape}?controller=api&amp;action=product&amp;id=123
    GET {$apiIndexUrl|escape}?controller=api&amp;action=product&amp;sku=ABC-001

Zwraca `item` w tej samej strukturze co element listy z 3.2.


## 4. Struktura odpowiedzi - lista produktow

{literal}{
  "api_version": "1.0",
  "category_ids": [5],
  "filters": { "updated_since": null, "in_stock": false },
  "pagination": {
    "page": 1,
    "per_page": 100,
    "total": 248,
    "total_pages": 3,
    "has_next": true
  },
  "count": 100,
  "items": [ PRODUKT, PRODUKT, ... ]
}{/literal}


## 5. Struktura produktu

{literal}{
  "id": 123,
  "sku": "ABC-001",
  "ean": "5901234567890",
  "name": "Nazwa produktu",
  "description": "Opis produktu (HTML/tekst)",
  "category": {
    "id": 5,
    "name": "Szklo hartowane",
    "slug": "szklo-hartowane",
    "sku_prefix": "SZK",
    "description": "",
    "allegro_category_id": "12345",
    "empik_category_id": "",
    "mediamarkt_category_id": "",
    "temu_category_id": "",
    "temu_category_name": "",
    "temu_category_path": ""
  },
  "stock": {
    "quantity": 14,
    "localization": "A-03-2",
    "shared_stock": {
      "enabled": false,
      "group_id": null,
      "members": []
    },
    "derived_stock": {
      "enabled": false,
      "sources": [],
      "dependents_count": 0
    }
  },
  "price": {
    "net": 81.30,
    "gross": 100.00,
    "vat_rate": 23.00,
    "currency": "PLN"
  },
  "dimensions": "10x20x2",
  "contours": "",
  "images": [
    { "position": 1, "url": "https://...", "absolute_url": "https://..." }
  ],
  "custom_fields": {
    "kolor": "czarny",
    "producent": "XYZ"
  },
  "parameters": {
    "allegro": [
      {
        "id": "11323",
        "name": "Stan",
        "unit": null,
        "value": ["11323_1"],
        "value_labels": ["Nowy"]
      }
    ],
    "allegro_compatibility_list": [],
    "empik":      [ { "id": "PARAM_ID", "value": "..." } ],
    "mediamarkt": [ { "id": "PARAM_ID", "value": "..." } ],
    "temu":       [ { "id": "PARAM_ID", "value": "..." } ]
  },
  "extra_fields": {},
  "created_at": "2026-01-10 09:12:00",
  "updated_at": "2026-02-01 14:30:00"
}{/literal}

Opis pol:

- `stock.quantity` - aktualny stan do sprzedazy. Jesli produkt jest w grupie wspolnego
  stanu (`shared_stock.enabled = true`), to jest to stan calej grupy, a `members` zawiera
  pozostale produkty tej grupy.
- `stock.derived_stock` - produkt, ktorego stan wynika z innych produktow (np. zestaw).
- `price.net` / `price.gross` - ceny w PLN, `vat_rate` w procentach.
- `images` - kolejnosc = kolejnosc zdjec w magazynie; `absolute_url` zawsze jest pelnym adresem.
- `custom_fields` - pola wlasne produktu w formie `slug_pola: wartosc`.
- `parameters.allegro[].value` - wartosc zapisana w magazynie (tekst, liczba lub ID wartosci
  slownikowych Allegro); `value_labels` - czytelne etykiety; `name` - nazwa parametru.
  `name`/`value_labels` moga byc puste, jesli slownik Allegro jest chwilowo niedostepny.
- `parameters.empik|mediamarkt|temu` - parametry w formie `id` + `value` (jak w danym marketplace).
- `extra_fields` - dodatkowe kolumny produktu, jesli w magazynie zostaly dodane nowe pola.
- Pola tekstowe bez wartosci maja postac pustego stringa `""`.


## 6. Zalecany sposob pobierania

Pelna lista kategorii:

    page = 1
    powtarzaj:
        GET ...&amp;action=products&amp;category_id=ID&amp;page=page&amp;per_page=200
        zapisz items
        jesli pagination.has_next == false -&gt; koniec
        page = page + 1

Synchronizacja przyrostowa: zapamietaj czas rozpoczecia pobierania i przy kolejnym
uruchomieniu dodaj `updated_since=&lt;ten czas&gt;`.
Uwaga: zmiana stanu we wspolnej grupie stanow nie zawsze zmienia `updated_at` produktu,
dlatego pelna synchronizacje stanow (bez `updated_since`) wykonuj np. raz na godzine.

Nie wysylaj rownolegle wiecej niz 2-3 zapytan naraz.


## 7. Kody odpowiedzi i bledy

| Kod | Znaczenie |
|-----|-----------|
| 200 | OK |
| 400 | Brak/niepoprawny parametr (np. brak `category_id`) |
| 401 | Brak tokenu |
| 403 | Token niepoprawny lub uniewazniony |
| 404 | Nie znaleziono kategorii/produktu |
| 405 | Metoda inna niz GET |
| 500 | Blad serwera |

Blad ma postac:

{literal}    { "error": "Opis bledu" }{/literal}


## 8. Przyklady

### curl

    curl -H "Authorization: Bearer &lt;TOKEN&gt;" \
      "{$apiIndexUrl|escape}?controller=api&amp;action=products&amp;category_id=5&amp;page=1&amp;per_page=200"

### PHP

{literal}    $token = 'TOKEN';
    $page = 1;
    $all = [];
    do {
        $url = '{/literal}{$apiIndexUrl|escape}{literal}?controller=api&action=products&category_id=5&per_page=200&page=' . $page;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
            CURLOPT_TIMEOUT => 120,
        ]);
        $data = json_decode(curl_exec($ch), true);
        curl_close($ch);
        $all = array_merge($all, $data['items']);
        $page++;
    } while (!empty($data['pagination']['has_next']));{/literal}

### JavaScript (Node 18+)

{literal}    const token = 'TOKEN';
    let page = 1, all = [], data;
    do {
      const res = await fetch('{/literal}{$apiIndexUrl|escape}{literal}?controller=api&action=products&category_id=5&per_page=200&page=' + page, {
        headers: { Authorization: 'Bearer ' + token }
      });
      if (!res.ok) throw new Error((await res.json()).error);
      data = await res.json();
      all.push(...data.items);
      page++;
    } while (data.pagination.has_next);{/literal}

### Python

{literal}    import requests
    token = "TOKEN"
    page, items = 1, []
    while True:
        r = requests.get("{/literal}{$apiIndexUrl|escape}{literal}", params={
            "controller": "api", "action": "products",
            "category_id": 5, "per_page": 200, "page": page,
        }, headers={"Authorization": f"Bearer {token}"}, timeout=120)
        r.raise_for_status()
        data = r.json()
        items += data["items"]
        if not data["pagination"]["has_next"]:
            break
        page += 1{/literal}
</pre>
          </div>
        </div>

        <div class="col-xxl-4">
          <div class="card api-admin-card">
            <div class="card-header">
              <strong>ID kategorii</strong>
              <div class="small text-secondary">Linki JSON dzialaja w tej przegladarce dzieki zalogowanej sesji; aplikacja zewnetrzna potrzebuje tokenu.</div>
            </div>
            <div class="card-body p-0 api-categories-table">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Kategoria</th>
                    <th class="text-end">Produkty</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  {foreach $apiCategories as $apiCategory}
                    <tr>
                      <td><code>{$apiCategory.id|escape}</code></td>
                      <td>{$apiCategory.name|escape}</td>
                      <td class="text-end">{$apiCategory.products_count|escape}</td>
                      <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="{$baseUrl}?controller=api&action=products&category_id={$apiCategory.id|escape:'url'}&per_page=20" title="Podglad JSON"><i class="bi bi-filetype-json"></i></a>
                      </td>
                    </tr>
                  {foreachelse}
                    <tr><td colspan="4" class="text-secondary p-3">Brak kategorii.</td></tr>
                  {/foreach}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<script>
  (function () {
    var source = document.getElementById('api-doc-text');
    var copyButton = document.getElementById('api-doc-copy');
    var downloadButton = document.getElementById('api-doc-download');
    if (!source) {
      return;
    }

    copyButton.addEventListener('click', function () {
      navigator.clipboard.writeText(source.textContent).then(function () {
        var original = copyButton.innerHTML;
        copyButton.innerHTML = '<i class="bi bi-check2 me-1"></i>Skopiowano';
        setTimeout(function () { copyButton.innerHTML = original; }, 1800);
      });
    });

    downloadButton.addEventListener('click', function () {
      var blob = new Blob([source.textContent], { type: 'text/markdown;charset=utf-8' });
      var link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = 'altreo-api-produkty.md';
      document.body.appendChild(link);
      link.click();
      link.remove();
      setTimeout(function () { URL.revokeObjectURL(link.href); }, 1000);
    });
  })();
</script>
