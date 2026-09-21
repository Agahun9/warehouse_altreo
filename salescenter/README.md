# SalesCenter – centrum zamówień SaaS

Samodzielna kopia centrum zamówień z aplikacji magazynowej ALTREO, przerobiona na SaaS:
wiele firm, rejestracja, logowanie i zespół. Działa na **osobnej, nowej bazie danych** i nie korzysta
z żadnych plików ani tabel aplikacji magazynowej.

## Instalacja

1. Wgraj cały katalog `salescenter/` na serwer (np. jako osobną domenę lub subdomenę).
   Wymagania: PHP 7.4+ (pdo_mysql, curl, json, openssl, mbstring, dom), MySQL 5.7+/MariaDB 10.2+.
2. Uzupełnij **`app/Config/database.php`**: `host`, `database`, `username`, `password`.
   Użytkownik bazy potrzebuje uprawnień SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX.
3. Sprawdź **`app/Config/app.php`**:
   - `public_base_url` – pełny adres `index.php`, np. `https://panel.twojadomena.pl/index.php`
     (linki resetu hasła i adresy dla agenta druku),
   - `encryption_key` – wygenerowany już losowo; **nie zmieniaj go** po uruchomieniu,
   - `install_key` – klucz instalatora, `mail_from`, `registration_enabled`.
4. Utwórz tabele – jedną z metod:
   - przeglądarka: `https://twoja-domena/install.php?key=<install_key>` → „Utwórz tabele”,
   - SSH: `php bin/install.php`,
   - ręcznie: import `database/schema.sql` (tylko tabele globalne; tabele firmy powstaną przy rejestracji).
5. Otwórz aplikację i zarejestruj pierwszą firmę (`index.php?controller=auth&action=register`).
6. Cron co minutę (import i automatyzacje czasowe wszystkich firm):

   ```cron
   * * * * * /usr/bin/php /sciezka/do/salescenter/bin/orders-sync.php >> /sciezka/poza/www/salescenter-sync.log 2>&1
   ```

Katalogi `app/Views/templates_c`, `app/Views/cache` i `app/Storage` muszą być zapisywalne przez PHP.
Pliki `app/Config/app.php` i `app/Config/database.php` są w `.gitignore`; w repozytorium są tylko `*.example.php`.

## Jak działa wielofirmowość

- **Tabele globalne** (`sc_tenants`, `sc_users`, `sc_password_resets`) – firmy i konta użytkowników.
- **Każda firma ma własny komplet tabel** w tej samej bazie: `t{ID}_om_orders`, `t{ID}_om_documents`,
  `t{ID}_om_series`, `t{ID}_print_agent_stations` itd. Dzięki temu firma ma własną numerację zamówień
  i dokumentów, statusy, automatyzacje, konta KSeF, przewoźników i drukarki.
- Kod centrum zamówień jest prawie identyczny z oryginałem i dalej używa nazw `om_*`/`print_*`.
  `App\Core\Database::query()` podmienia je na tabele aktywnej firmy (`App\Core\Tenant`).
  **Zapytanie do tabel firmy bez zalogowanej firmy jest blokowane** – pominięty warunek w SQL
  nie może ujawnić danych innej firmy. Dzięki temu poprawki z aplikacji magazynowej można przenosić prawie 1:1.
- Blokady synchronizacji MySQL (`GET_LOCK`) mają w nazwie bazę i firmę.
- Token agenta druku ma prefiks firmy (`t12.…`); API agenta wybiera po nim firmę, a autoryzuje hash całego tokenu.
- Tabele nowej firmy tworzą się przy rejestracji; brakujące kolumny po aktualizacji kodu uzupełnia
  `ensureSchema()` przy wejściu do panelu, `bin/orders-sync.php` oraz `bin/install.php`.

## Role

| Rola | Centrum zamówień | Zespół i dane firmy |
|---|---|---|
| Właściciel | edycja | tak (także role właścicieli) |
| Administrator | edycja | tak (bez zmian kont właścicieli) |
| Pracownik | edycja albo tylko podgląd | nie |

Firma zawsze ma co najmniej jednego aktywnego właściciela. Po 8 błędnych logowaniach konto blokuje się na 15 minut.
Zmiana hasła wylogowuje pozostałe sesje użytkownika.

## Integracje (Centrum zamówień → Konta i import)

| Kanał | Jak łączy firma | Import | Numer przesyłki do źródła |
|---|---|---|---|
| Allegro | logowanie kontem Allegro (OAuth) | tak | tak (+ Wysyłam z Allegro) |
| Empik, MediaMarkt (Mirakl) | klucz API z panelu | tak (+ auto-akceptacja) | tak |
| ERLI | klucz API („Własna integracja po API”) | tak | tak |
| PrestaShop | adres sklepu + klucz webservice | tak | tak (order_carriers) |
| WooCommerce | logowanie do WordPressa (wc-auth) albo klucze REST | tak | notatka dla klienta |
| Altreo.pl | adres sklepu + token z panelu sklepu (Ustawienia → SalesCenter) | tak | tak (status „Wysłane” + e-mail do klienta) |
| Temu | App Key + App Secret + Access Token | beta | nie (API wymaga ręcznego nadania) |
| Morele | Client ID + Client Secret | beta – brak publicznej specyfikacji zamówień | nie |
| Własny sklep | token API, sklep wysyła zamówienia (`api.php/v1`) | w czasie rzeczywistym | odczyt statusu i numeru przez API |

Każda karta w module ma instrukcję krok po kroku i linki do paneli. Dane dostępowe są szyfrowane
(`encryption_key`) i zapisane w tabeli firmy `t{ID}_om_connections`. Nowe połączenie pobiera zamówienia
z 7 dni; **„Pobierz starsze zamówienia”** w ustawieniach połączenia importuje wszystko od wybranej daty
(maks. 3 lata) – postęp widać na stronie, a resztę dokończy cron.

### Jednorazowo: aplikacja Allegro (Administracja SalesCenter)

Allegro łączy konta wyłącznie przez zarejestrowaną aplikację. Rejestruje ją raz główny administrator w
**Administracja SalesCenter → Aplikacja Allegro** (menu widoczne tylko dla niego): instrukcja, adres przekierowania,
Client ID i Client Secret – SalesCenter sprawdza je w Allegro i zapisuje zaszyfrowane dla wszystkich firm.
Firmy nie widzą tych ustawień: w karcie **Allegro** mają tylko „Zaloguj przez Allegro”.

## Administracja SalesCenter

`index.php?controller=administration` – tylko główny administrator (pierwsze konto). Aplikacja Allegro i globalne
zadania cron. Stare linki cron `?controller=cron&action=run…` nadal działają.

### WooCommerce – logowanie bez kluczy

Wymaga `public_base_url` z `https://` (sklep wysyła klucze na `woocommerce-callback.php`). Jeśli firewall sklepu
zablokuje ten krok, moduł podpowie połączenie ręcznymi kluczami REST.

### Altreo.pl

Sklep altreo.pl ma wbudowane API dla SalesCenter (`/api/salescenter/ping`, `GET /api/salescenter/orders`,
`POST /api/salescenter/orders/{numer}/shipment`), autoryzowane tokenem z panelu sklepu (Ustawienia → SalesCenter).
Status źródłowy zamówienia to kod statusu sklepu (`nowe`, `w_realizacji`, `wyslane`, `zrealizowane`, `anulowane`) –
zmapuj go w zakładce „Statusy”. Przekazanie numeru przesyłki ustawia w sklepie status „Wysłane” i wysyła klientowi e-mail.

### API własnego sklepu

`POST api.php/v1/orders`, `GET api.php/v1/orders/{id}`, `GET api.php/v1/orders?updated_since=…`, `GET api.php/v1/statuses`,
`GET api.php/v1/ping`; nagłówek `Authorization: Bearer <token>`. Gdy serwer nie obsługuje ścieżek po `api.php`,
użyj `api.php?route=v1/orders`. Pełny opis z przykładem JSON jest w karcie „Własny sklep (API)”.

## Wiadomości (menu **Sprzedaż → Wiadomości**)

Skrzynka dla wszystkich podłączonych kont z podziałem na marketplace i rodzaj sprawy. Licznik w menu pokazuje wątki
„Nowa” i „Do odpowiedzi”. Synchronizacja działa w tym samym cronie co import zamówień, a każde konto ma własny
interwał (domyślnie 5 min). Przycisk **Synchronizuj teraz** pobiera wszystko od razu.

| Marketplace | Sekcje | Działania | API |
|---|---|---|---|
| Allegro | Wiadomości, Dyskusje, Reklamacje | odpowiedź, prośba o zakończenie dyskusji, decyzja o zwrocie produktu, **decyzja w reklamacji** (uznanie: zwrot / częściowy zwrot / wymiana / naprawa; odrzucenie z powodem), oznaczanie jako przeczytane | `/messaging/*` (public.v1), `/sale/issues/*` (beta.v1) – uprawnienia `allegro:api:messaging` i `allegro:api:disputes` |
| Empik, MediaMarkt | Wiadomości, Incydenty | odpowiedź do klienta i/lub operatora, odpowiedź na incydent (wątek zamówienia albo nowy wątek), **oznaczenie incydentu jako rozwiązanego** z powodem | Mirakl M10/M11/M12, OR43, OR11 `has_incident`, OR64, RE01 |
| Morele (beta) | Wiadomości (pytania, reklamacje, zwroty 14-dniowe), Uwagi do zamówień | odpowiedź | `/communication-center/*` – specyfikacja OpenAPI pod `GET /v1/docs` (Bearer); wysyłka `POST /communication-center/message` z `typeId`, `resourceIdentifier`, `identifier`, `messageBody` |
| ERLI | Uwagi do zamówień, Zwroty (powód, pozycje, komentarz, konto do zwrotu) | tylko odczyt i statusy – API ERLI nie ma rozmów ani odpowiedzi na zwroty | `comment` i `returns[]` z zamówień (`/orders/_search`) |
| PrestaShop | Wiadomości (formularz kontaktowy i wiadomości do zamówień) | odpowiedź (zapis w wątku klienta; PrestaShop nie wysyła wtedy e-maila) | webservice `customer_threads`, `customer_messages` (GET) i `customer_messages` (POST, XML) |
| WooCommerce | Uwagi do zamówień | odpowiedź jako „notatka dla klienta” – sklep wysyła ją e-mailem | `POST /orders/{id}/notes` (`customer_note: true`) |
| Temu, Własny sklep (API) | Uwagi do zamówień | tylko odczyt i statusy – brak publicznego API wiadomości | dane zamówień z importu |

Uwagi kupujących do zamówień (pole uwagi/wiadomości przy zakupie) mają też Allegro, Empik, MediaMarkt i Morele;
odpowiedź na nie trafia do Centrum wiadomości Allegro (nowa wiadomość do kupującego o zamówieniu) albo do wątku zamówienia
Mirakl (OR43). Uwagi i zwroty pochodzą z już zaimportowanych zamówień, więc nie zużywają limitów API.

Statusy wątku: **Nowa** → **Do odpowiedzi** (po otwarciu) → **Odpowiedziano** / **Autoodpowiedź** / **Zamknięta**.
Nowa wiadomość klienta przywraca status „Do odpowiedzi”. Sprawa zamknięta w marketplace zamyka wątek, a odpowiedź
wysłana w panelu marketplace oznacza wątek jako „Odpowiedziano”.

**Autoodpowiedzi** (zakładka w Wiadomościach) to reguły ze zdarzeniem: pierwsza wiadomość, każda wiadomość, wiadomość
poza godzinami pracy, brak odpowiedzi po czasie albo nowa dyskusja/reklamacja/incydent. Można je zawęzić do marketplace,
konta, rodzaju wątku i słów kluczowych. Treść obsługuje znaczniki `{klient}`, `{zamowienie}`, `{temat}`, `{numer}`,
`{platforma}`, `{konto}`, `{godziny}` i `{podpis}`. Zabezpieczenia: najwyżej jedna odpowiedź na wiadomość, przerwa
między autoodpowiedziami w wątku, tylko wiadomości nowsze niż włączenie reguły (maks. 72 h), a autoodpowiedzi trzeba
dodatkowo włączyć dla każdego marketplace w **Ustawieniach marketplace** (tam też godziny pracy, podpis, interwał i zakres
synchronizacji). Dziennik wysłanych autoodpowiedzi jest pod listą reguł.

## Agent druku

- Paczki do pobrania: `downloads/PrintAgent-Windows-x64.zip`, `downloads/PrintAgent-macOS-AppleSilicon.zip`
  (linki w zakładce **Drukarki**). Pliki `.zip` nie są w gicie – wgraj je na serwer razem z aplikacją.
- Kod źródłowy: `print-agent/` (kopia `ALTREO_PRINT_APP`, .NET 9 / Avalonia). Budowanie: `print-agent/scripts/`.

## Zakres na tym etapie

- Wiadomości Morele działają w trybie beta: API centrum komunikacji nie ma publicznej dokumentacji, a autora wiadomości
  (klient / sprzedawca) rozpoznajemy po nadawcy pierwszej wiadomości w wątku. Załączników z marketplace'ów nie pobieramy
  (widoczne są nazwy plików), a odpowiedzi z SalesCenter nie mają załączników.
- Temu i Morele działają w trybie beta (Morele nie publikuje specyfikacji zamówień; Temu różni pola między regionami).
  Po pierwszym imporcie sprawdź zamówienie – surowe dane są w szczegółach zamówienia.
- Aplikacja agenta druku ma jeszcze nazwę „Altreo Print Agent” – zmiana wymaga przebudowania.
- Brak panelu super-administratora SaaS, płatności za abonament i usuwania firmy.

## Testy (SQLite w pamięci, bez sieci)

```sh
php tests/orders_test.php
php tests/print_agent_test.php
php tests/tenant_test.php
php tests/integrations_test.php
php tests/shipping_test.php
php tests/messages_test.php
```

Szczegóły funkcji centrum zamówień: `docs/orders.md` (dokumentacja z aplikacji magazynowej).
