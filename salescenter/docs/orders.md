# Centrum zamówień

Osobna, pełnoekranowa aplikacja: `orders.php`. Korzysta z istniejącego logowania, ale nie renderuje menu ani układu magazynu. Tabele z prefiksem `om_` powstają przy pierwszym wejściu. Wymagany MySQL/InnoDB, PHP 7.4+ z PDO MySQL, cURL, JSON, OpenSSL i mbstring oraz istniejące Smarty. Moduł uprawnień `orders` rejestrowany jest przez `Core/Config.php`, również na instalacjach ze starszym, lokalnym plikiem konfiguracji. Administrator ma dostęp; pozostałym użytkownikom nadaje się odczyt albo edycję w administracji.

## Uruchomienie na serwerze

1. Wgraj nowe pliki oraz zmienione klasy i nawigację. Nie ma zmian w konfiguracji sekretów.
2. Otwórz **Centrum zamówień → Konta i import → Wczytaj konta z integracji**. Operacja odczytuje istniejące aktywne konta Allegro, Erli, Empik, MediaMarkt i Morele. Nie tworzy kont na platformach.
3. Dla obsługiwanych kont wybierz **Test: ostatnie 7 dni**. To import prawdziwych danych do lokalnych tabel. Ponowienie nie dodaje duplikatów. Jeden przebieg pobiera jedną stronę na konto; komunikat informuje o dalszych stronach.
4. Włącz import bieżący na wybranych kontach.
5. Ustaw cron co minutę, podstawiając rzeczywistą ścieżkę instalacji i PHP:

   ```cron
   * * * * * /usr/bin/php /sciezka/do/aplikacji/bin/orders-sync.php >> /sciezka/poza/www/orders-sync.log 2>&1
   ```

   Skrypt jest dostępny wyłącznie z CLI. W otwartym panelu użytkownika z prawem edycji dodatkowo działa odczyt co 60 sekund; sam otwarty panel nie zastępuje crona. Po synchronizacji kliknij „Odśwież widok”, aby zobaczyć nowe dane. Błąd jednego konta nie przerywa innych; automatyczne ponowienie błędnego konta ma minimum 120 sekund przerwy.

6. Uzupełnij mapowania statusów, dane sprzedawcy i serie numeracji. Przed dokumentem sprawdź nabywcę (karta „Nabywca” w zamówieniu – osobna od danych dostawy; puste pola = dane dostawy) oraz VAT każdej pozycji. Faktura i paragon drukują osobno „Nabywca” i „Dane dostawy”. Kwota pierwotnego dokumentu musi odpowiadać zamówieniu z dostawą.

## Gwarancje implementacji

- Identyfikacja kont: `(platform, source_id)`; zamówień: unikalny indeks `(account_id, external_id)`, z rozróżnianiem wielkości liter na MySQL. To samo ID na dwóch kontach oznacza dwa różne zamówienia.
- Ograniczenie dat na poziomie zapytań API oraz ponownie przy zapisie. Nigdy nie zapisujemy nowego zamówienia starszego niż dokładnie 7 × 24 godziny. Zamówienie już zaimportowane jest aktualizowane także później (np. płatność potwierdzona kilka dni po zakupie). Empik i MediaMarkt (Mirakl OR11) filtrują wyłącznie po dacie aktualizacji (`start_update_date`/`end_update_date`), bez filtra daty utworzenia, więc zmiany starszych zamówień docierają do lokalnej bazy. Data brakująca lub niepoprawna zatrzymuje stronę, zamiast przyjmować dzisiejszą. Daty przechowywane i pokazywane są w UTC; data wystawienia dokumentu jest w strefie Europe/Warsaw.
- Pierwszy przebieg i test obejmują 7 dni. Kolejne zakończone przebiegi stosują filtr aktualizacji z pięciominutowym nakładaniem. Stały koniec okna i kursor są zapisywane między stronami. Zakończenie pełnego przebiegu przesuwa znacznik synchronizacji. Błąd go nie przesuwa. Duże konta wymagają wielu uruchomień; import nie jest webhookiem ani gwarancją czasu rzeczywistego.
- MySQL advisory lock izoluje synchronizację konta. Transakcje oraz blokady wierszy chronią import i lokalne zmiany statusów. Ręczna zmiana statusu (także przez automat) trwale chroni lokalny status przed mapowaniem podczas importu.
- Wywołania zamówień: Allegro `GET /order/checkout-forms`; Empik i MediaMarkt `GET /api/orders`; Erli `POST /orders/_search`. Import pozostaje tylko do odczytu. Po pobraniu etykiety numer przesyłki jest idempotentnie przekazywany do źródła: Allegro `POST /order/checkout-forms/{id}/shipments`, Empik/MediaMarkt (Mirakl OR23) `PUT /api/orders/{id}/tracking`, Erli `POST /shipping/external`. Wysyłam z Allegro dopisuje numer do zamówienia samodzielnie. Nie zmieniamy automatycznie statusu zamówienia na wysłane.
- Empik i MediaMarkt (Mirakl) dostarczają zamówienie w stanie `WAITING_ACCEPTANCE` bez pełnych danych dostawy; pełne dane (adres, potwierdzenie płatności) pojawiają się dopiero po akceptacji zamówienia w Mirakl. Zakładka „Konta” pozwala włączyć per-konto automatyczną akceptację (`om_accounts.auto_accept`) — gdy jest włączona, każdy przebieg synchronizacji akceptuje takie zamówienie przez Mirakl OR21 (`PUT /api/orders/{id}/accept`, wszystkie pozycje `accepted:true`) zaraz po imporcie; pełne dane trafiają do lokalnego zamówienia przy kolejnym przebiegu. Identyfikator pozycji pochodzi z pola OR11 `order_line_id`. Płatność Mirakl jest potwierdzona, gdy OR11 zwraca `customer_debited_date` (pole `customer_debited` istnieje tylko jako filtr zapytania); zmiana płatności trafia do historii zamówienia. Domyślnie ustawienie jest wyłączone. Błąd akceptacji pojedynczego zamówienia jest logowany w jego historii i nie przerywa importu pozostałych zamówień na stronie.
- Automatyzacje (zakładka **Automatyzacje**, `OrderAutomationService`): reguła = wyzwalacze → warunki → efekty wykonywane po kolei. Wyzwalacze: nowe zamówienie, import/odświeżenie, edycja danych, zmiana statusu (ręczna, mapowanie, automat), zmiana statusu w marketplace, opłacenie i cofnięcie płatności, wystawienie dokumentu, utworzenie przesyłki, zmiana statusu przesyłki, przekazanie numeru do marketplace, upływ czasu (od zmiany statusu, złożenia lub pobrania; cron `bin/orders-sync.php`, maks. 60 dni, raz na moment odniesienia) i uruchomienie ręczne. Około 50 warunków (konto, kanał, status, wartość, waluta, tagi, klient, NIP, płatność i metoda, pobranie, dostawa, kraj, kod pocztowy, SKU z `*`, liczba sztuk, dokumenty, przesyłki, etap u przewoźnika, druk, dzień tygodnia, godzina itd.) łączonych przez „wszystkie” lub „dowolny”. Efekty: status, tagi, notatka, wpis historii, oznaczenie płatności, metoda płatności/dostawy, dokument sprzedaży, wystawienie paragonu/faktury (także według wyboru klienta, z drukiem fiskalnym), nadanie przesyłki, odświeżenie, numer do marketplace, druk etykiety, e-mail do klienta lub na adres, webhook HTTPS (bez adresów prywatnych), akceptacja Mirakl, uruchomienie innej reguły i zatrzymanie kolejnych. Limit „raz na zamówienie” albo „przy każdym zdarzeniu”; opcja zatrzymania po błędzie kroku. Zdarzenia zgłoszone w transakcji wykonują się po commit, a wycofane transakcje ich nie uruchamiają. Efekty mogą wywołać kolejne zdarzenia (maks. 4 poziomy, reguła raz na zamówienie w jednym łańcuchu — bez pętli). Zmiany płatności/dostawy z automatu są chronione przed synchronizacją jak ręczna edycja. Reguły z przyciskiem są dostępne w menu ⚡ zamówienia oraz jako akcja masowa listy (do 50 zamówień); ręczne uruchomienie sprawdza warunki, ale pomija limit. Panel „Reguły dla tego zamówienia” pokazuje dopasowanie warunek po warunku bez wykonania, a dziennik (`om_rule_runs`) wynik każdego kroku. Starsze reguły `{pole: wartość}` są odczytywane w nowym formacie bez migracji.
- Dokumenty mają niezmienne kopie danych i pozycji, unikalne numery oraz klucz chroniący ponowienie tego samego formularza. Numeracja jest blokowana transakcyjnie; wzory: `{N}`, `{YYYY}`, `{MM}`. Licznik nie resetuje się automatycznie. Kwoty zapisywane w groszach. VAT jest wyliczany od wartości brutto pozycji. Kolejna korekta odwołuje się do dokumentu pierwotnego, pokazuje ostatni stan przed zmianą, pełny stan po i różnicę. Błędna korekta nie zużywa numeru.
- Wszystkie akcje zapisujące przez HTTP wymagają prawa edycji, POST i tokenu CSRF. Widoki uciekają dane klienta, SQL używa parametrów, sekrety kont nie trafiają do HTML. Szczegółowe odpowiedzi błędów API nie są wyświetlane użytkownikowi.

## Moduły przesyłek

Każdy operator to osobna klasa w `app/Services/Shipping/` dziedzicząca po `ShippingProvider`, zarejestrowana w `ShippingProviders::CLASSES`. `OrderShipmentService` jest wspólnym orkiestratorem: wybiera konto nadawcze, pilnuje idempotencji (`request_key`), zapisuje `om_shipments`, historię i zdarzenia automatyzacji. Zakładka **Przesyłki**, formularz nadania w zamówieniu, przyciski (anuluj, etykieta, protokół, „wyślij nr do źródła”) i JS działają na podstawie `definition()` modułu — bez warunków na nazwę operatora.

| Moduł (`provider`) | Konto | Usługi | Etykieta | Status | Anulowanie | Pobranie | Numer do źródła |
|---|---|---|---|---|---|---|---|
| Wysyłam z Allegro (`allegro_wza`) | konto Allegro z zakładki Konta | z konta, domyślnie z zamówienia | tak | tak | nie | z zamówienia | ręcznie |
| Wysyłam z Erli (`erli_shipping`) | sklep ERLI z zakładki Konta (jego klucz API) | `GET /dictionaries/shippingMethods`, domyślnie z zamówienia | `shipping.waybills` | `GET /shipping/parcels/{id}` | `DELETE /shipping/parcels/{id}` | z zamówienia | automatycznie |
| InPost ShipX (`inpost_shipx`) | organizacja + token | Paczkomat / Kurier | tak | tak | nie | nie | ręcznie |
| Apaczka (`apaczka`) | App ID + Secret | `service_structure` + wycena | tak | tak | tak | z formularza | ręcznie |

Zakładka **Przesyłki** korzysta z tego samego wzorca i JS co **Konta** (`orders/shipping_carriers.tpl`, `dist/js/integrations.js`): lista kont nadawczych z rozwijanymi ustawieniami (możliwości, instrukcja, punkty nadania, edycja z hasłem „bez zmian”, włącz/wyłącz, odłączenie tylko bez przesyłek) oraz okno „Dodaj konto nadawcze” z kafelkami i instrukcją krok po kroku (`steps` w `definition()`).

Nowy operator: klasa z `definition()` (etykieta, opis, pola formularza, możliwości, instrukcje), `services()`, `create()`, `refresh()`, `label()` i opcjonalnie `cancel()`/`valuation()`/`preferredService()`/`matchOrder()`, a potem wpis w `ShippingProviders::CLASSES`. Pola typu `password` trafiają zaszyfrowane do `secret_config_json`, a `source_account` wiąże konto nadawcze z kontem sprzedaży.

Wysyłam z Erli: `POST /shipping/parcels/` (wymiary w mm, waga w g). Odbiorcę i punkt odbioru ERLI bierze z zamówienia. Gdy metoda w zamówieniu jest grupą (np. `erliKurier24InPost`), wybierany jest najmniejszy wariant wagowy, który mieści paczkę. Przy zapisie konta pobierane są punkty nadania (`GET /shipping/postingPoints`), co przy okazji sprawdza klucz API. Protokół odbioru: `GET /shipping/pickupProtocols`. Klucz API jest wysyłany tylko do domen `erli.pl`.

## KSeF (Krajowy System e-Faktur)

- **Kod:** `KsefClient` (API KSeF 2.0: uwierzytelnienie tokenem KSeF, sesja interaktywna, status, UPO), `KsefService` (konta KSeF w `om_ksef_accounts`, XML FA(3), historia wysyłek w `om_ksef_submissions`, blokady), szablony `orders/ksef_settings.tpl`, `orders/ksef_account_form.tpl`, `orders/ksef_document.tpl`, style `dist/css/orders-ksef.css`. Schematy XSD FA(3) leżą lokalnie w `app/Support/ksef/`; każdy XML jest walidowany przed wysyłką.
- **Konta (wiele firm):** w *Ustawieniach ogólnych → Konta KSeF* dodajesz konto na każdą firmę: nazwa, NIP, tryb **Sandbox** (`https://api-test.ksef.mf.gov.pl/v2`) albo **Produkcja** (`https://api.ksef.mf.gov.pl/v2`), osobne szyfrowane tokeny dla obu trybów, podstawa zwolnienia z VAT i automatyczna wysyłka. Przełączenie konta na produkcję wymaga potwierdzenia. Token dostępowy jest buforowany (zaszyfrowany) per konto i tryb.
- **Przypisanie do numeracji:** w zakładce *Dokumenty* każda seria faktur/korekt ma pole „Konto KSeF”. Faktura jest wysyłana kontem swojej serii; korekta bez własnego konta dziedziczy konto faktury korygowanej. NIP sprzedawcy na dokumencie musi być równy NIP konta — inaczej wysyłka jest blokowana przed kontaktem z KSeF. Konta przypisanego do serii nie można usunąć.
- **Migracja:** wcześniejsza pojedyncza konfiguracja (`om_settings.ksef`) jest automatycznie zamieniana na jedno konto przypisane do wszystkich serii faktur i korekt.
- **Wysyłka:** przycisk „Wyślij do KSeF” przy fakturze/korekcie (lista dokumentów i szczegóły zamówienia) albo automatycznie po wystawieniu, gdy konto serii ma włączoną automatyczną wysyłkę (także z automatyzacji). Błąd wysyłki nigdy nie cofa wystawionego dokumentu — trafia do historii zamówienia i statusu dokumentu. Status i UPO pobierane są kontem użytym do wysyłki. Przycisk „XML” pobiera podgląd FA(3) bez wysyłki.
- **Mapowanie:** stawki 23/8/5 → P_13_1–3, 0 → „0 KR”, zw → P_13_7 (wymaga podstawy zwolnienia w koncie), np → „np I”. Ceny są brutto (P_9B/P_11A). Nabywca: pierwsza linia to nazwa, linia „NIP: …” (z poprawną sumą kontrolną) daje NIP, samodzielny 2-literowy kod to kraj; brak NIP → `BrakID` (konsument). Korekta zawiera wiersze „przed” (`StanPrzed`) i „po”, a `DaneFaKorygowanej` wskazuje fakturę pierwotną z numerem KSeF z tego samego trybu (inaczej `NrKSeFN`).
- **Blokady:** dokumentu przyjętego lub przetwarzanego w produkcyjnym KSeF nie można edytować ani usunąć — należy wystawić korektę. Wysyłki sandboksowe niczego nie blokują.
- **Weryfikacja:** `tests/orders_test.php` testuje XML (XSD), RSA-OAEP, migrację, wiele kont i pełny przepływ na atrapie API. Pełnego uwierzytelnienia prawdziwym tokenem ani wysyłki do sandboksu/produkcji nie wykonano w trakcie implementacji.

## Zakres jeszcze nieukończony

- **Morele:** obecna integracja projektu obsługuje oferty i tylko jedno konto. Nie znaleziono w niej kontraktu Orders API; brak potwierdzonej publicznej specyfikacji zamówień. Konto jest widoczne, lecz import Morele jest jawnie zablokowany. Wiele kont Morele i ich import wymagają dokumentacji z panelu sprzedawcy; nie zostały zaimplementowane.
- **Nadawanie paczek:** podjazd kuriera nie jest jeszcze obsługiwany. Anulowanie z panelu działa dla modułów, które je deklarują (Apaczka, Wysyłam z Erli). Wysyłam z Erli nie był testowany na prawdziwym sklepie — jest zgodny ze specyfikacją `swagger.json` i przetestowany na atrapie HTTP.
- **Fiskalizacja:** paragony drukuje drukarka fiskalna Posnet przez agenta druku; wydruki A4 paragonów są oznaczone jako niefiskalne. Faktury w walucie obcej nie są jeszcze wysyłane do KSeF (brak kursu i kwot VAT w PLN).
- **Testy produkcyjne:** nie wykonano wywołań do rzeczywistych kont, importu do produkcyjnej bazy ani instalacji crona. Nie zweryfikowano wizualnie w przeglądarce — powierzchnia przeglądarki była niedostępna w sesji. Renderowanie wszystkich gałęzi Smarty sprawdzono automatycznie. Nie wykonywano testów blokad pod obciążeniem na MySQL.
- Duże okna Allegro są automatycznie dzielone przed osiągnięciem limitu stronicowania. Skrajny przypadek ponad 9500 zamówień w jednej sekundzie zatrzyma konto z błędem zamiast pomijać dane.

## Weryfikacja lokalna

```sh
php tests/orders_test.php
php tests/shipping_test.php
node --check dist/js/orders.js
node --check dist/js/orders-automation.js
php tests/messages_test.php
node --check dist/js/messages.js
```

Testy korzystają wyłącznie z SQLite w pamięci i atrap transportu: granica 7 dni, duplikaty i konta, mapowanie, ręczny status, automatyzacje (walidacja, kolejność kroków, łańcuchy bez pętli, commit/rollback, przyciski, harmonogram, blokada webhooka na adres prywatny, zapis kolejności), podgląd bez skutków, kwoty/VAT, numeracja, ponowienie dokumentu, kolejne korekty, niezmienność danych, strony importu, błędy, nakładanie okien, wyłączenie konta, wyszukiwarka, puste widoki, XSS i kompilacja wszystkich zakładek oraz wydruku korekty. Nie odczytują poświadczeń ani nie łączą się z marketplace.

Opcjonalnie `OM_PREVIEW_DIR=/tmp/om-preview php tests/orders_test.php` zapisuje statyczne widoki na syntetycznych danych do wskazanego katalogu; pliki nie służą do wykonywania operacji.

## Źródła adapterów

- Allegro: https://developer.allegro.pl/documentation/#operation/getListOfOrdersUsingGET
- Wysyłam z Allegro: https://developer.allegro.pl/tutorials/jak-zarzadzac-przesylkami-przez-wysylam-z-allegro-LRVjK7K21sY
- Erli: https://erli.pl/svc/shop-api/doc/reference/ i https://erli.pl/svc/shop-api/doc/swagger.json
- Mirakl OR11: https://developer.mirakl.com/content/product/mmp/rest/seller/openapi3/orders/or11
- Allegro – Centrum wiadomości i Dyskusje/reklamacje: https://developer.allegro.pl/swagger.yaml (`/messaging/*`, `/sale/issues/*`)
- Mirakl – wiadomości (M10/M11/M12, OR43): https://developer.mirakl.com/content/product/mmp/rest/seller/openapi3/messages
- Mirakl – incydenty (OR64) i powody (RE01): https://developer.mirakl.com/content/product/mmp/rest/seller/openapi3/incidents
- ERLI – zamówienia (`comment`, `returns[]`), brak API wiadomości: https://erli.pl/svc/shop-api/doc/swagger.json
- PrestaShop – webservice `customer_threads` / `customer_messages`: https://devdocs.prestashop-project.org/8/webservice/resources/customer_threads/
- WooCommerce – notatki zamówienia: https://woocommerce.github.io/woocommerce-rest-api-docs/#create-an-order-note
- Morele – `/communication-center/*`: specyfikacja OpenAPI `GET https://api-marketplace.morele.net/v1/docs` (Bearer; w panelu marketplace.morele.net/docs). Wysyłka: `POST /communication-center/message` (JSON: `typeId`, `resourceIdentifier`, `identifier` = `thread.id`, `messageBody`) – te same pola co odpowiedź w panelu sprzedawcy; przy błędzie diagnostyka wypisuje schemat z `/v1/docs`
- InPost ShipX: https://dokumentacja-inpost.atlassian.net/wiki/spaces/PL/pages/11731061
- Apaczka Web API v2: https://panel.apaczka.pl/dokumentacja_api_v2.php
