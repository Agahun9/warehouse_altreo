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

6. Uzupełnij mapowania statusów, dane sprzedawcy i serie numeracji. Przed dokumentem sprawdź nabywcę oraz VAT każdej pozycji. Kwota pierwotnego dokumentu musi odpowiadać zamówieniu z dostawą.

## Gwarancje implementacji

- Identyfikacja kont: `(platform, source_id)`; zamówień: unikalny indeks `(account_id, external_id)`, z rozróżnianiem wielkości liter na MySQL. To samo ID na dwóch kontach oznacza dwa różne zamówienia.
- Ograniczenie dat na poziomie zapytań API oraz ponownie przy zapisie. Nigdy nie zapisujemy nowego zamówienia starszego niż dokładnie 7 × 24 godziny. Data brakująca lub niepoprawna zatrzymuje stronę, zamiast przyjmować dzisiejszą. Daty przechowywane i pokazywane są w UTC; data wystawienia dokumentu jest w strefie Europe/Warsaw.
- Pierwszy przebieg i test obejmują 7 dni. Kolejne zakończone przebiegi stosują filtr aktualizacji z pięciominutowym nakładaniem. Stały koniec okna i kursor są zapisywane między stronami. Zakończenie pełnego przebiegu przesuwa znacznik synchronizacji. Błąd go nie przesuwa. Duże konta wymagają wielu uruchomień; import nie jest webhookiem ani gwarancją czasu rzeczywistego.
- MySQL advisory lock izoluje synchronizację konta. Transakcje oraz blokady wierszy chronią import i lokalne zmiany statusów. Ręczna zmiana statusu (także przez automat) trwale chroni lokalny status przed mapowaniem podczas importu.
- Wywołania zamówień: Allegro `GET /order/checkout-forms`; Empik i MediaMarkt `GET /api/orders`; Erli `POST /orders/_search` (wyszukiwanie, bez zmiany zamówień). Odświeżenie tokenu OAuth jest dozwolone. Nie wywołujemy akceptacji, zmian statusów, numerów przesyłek ani załączników marketplace.
- Reguły: import/odświeżenie lub ręczna zmiana statusu; wszystkie warunki są łączone przez AND. Dostępne warunki to konto, status i potwierdzenie płatności. Akcje to status, tag i wpis do historii. Dopasowanie jest liczone według stanu na początku zdarzenia. Reguły wykonywane są według ID; przy kilku zmianach statusu ostatnia pasująca reguła wygrywa. Akcje nie uruchamiają rekurencyjnie kolejnych reguł. Reguła importu wykonuje się najwyżej raz na zamówienie; może stać się kwalifikowalna dopiero po późniejszym potwierdzeniu płatności. Test w szczegółach pokazuje dopasowanie bez wykonania.
- Dokumenty mają niezmienne kopie danych i pozycji, unikalne numery oraz klucz chroniący ponowienie tego samego formularza. Numeracja jest blokowana transakcyjnie; wzory: `{N}`, `{YYYY}`, `{MM}`. Licznik nie resetuje się automatycznie. Kwoty zapisywane w groszach. VAT jest wyliczany od wartości brutto pozycji. Kolejna korekta odwołuje się do dokumentu pierwotnego, pokazuje ostatni stan przed zmianą, pełny stan po i różnicę. Błędna korekta nie zużywa numeru.
- Wszystkie akcje zapisujące przez HTTP wymagają prawa edycji, POST i tokenu CSRF. Widoki uciekają dane klienta, SQL używa parametrów, sekrety kont nie trafiają do HTML. Szczegółowe odpowiedzi błędów API nie są wyświetlane użytkownikowi.

## Zakres jeszcze nieukończony

- **Morele:** obecna integracja projektu obsługuje oferty i tylko jedno konto. Nie znaleziono w niej kontraktu Orders API; brak potwierdzonej publicznej specyfikacji zamówień. Konto jest widoczne, lecz import Morele jest jawnie zablokowany. Wiele kont Morele i ich import wymagają dokumentacji z panelu sprzedawcy; nie zostały zaimplementowane.
- **Nadawanie paczek:** przygotowano osobne, szyfrowane konfiguracje Wysyłam z Allegro, InPost ShipX i Apaczka/Alsendo oraz stanowisko wysyłki w szczegółach zamówienia. Formularz tworzy przesyłkę u wybranego operatora po wyraźnym potwierdzeniu możliwej opłaty, zapisuje jej identyfikator, pozwala odświeżyć status i pobrać etykietę PDF. Wysyłam z Allegro korzysta z propozycji nadania przypisanej do zamówienia; ShipX działa w trybie uproszczonym; Apaczka używa Web API v2. Podjazd kuriera i anulowanie zlecenia nie są jeszcze obsługiwane.
- **Fiskalizacja / KSeF:** dokumenty i korekty są lokalne, z podglądem HTML A4 oraz drukowaniem/zapisem PDF przez przeglądarkę. Paragony mają oznaczenie „niefiskalny”. Nie ma obsługi drukarki fiskalnej, KSeF ani deklaracji gotowości do pełnego obiegu podatkowego.
- **Testy produkcyjne:** nie wykonano wywołań do rzeczywistych kont, importu do produkcyjnej bazy ani instalacji crona. Nie zweryfikowano wizualnie w przeglądarce — powierzchnia przeglądarki była niedostępna w sesji. Renderowanie wszystkich gałęzi Smarty sprawdzono automatycznie. Nie wykonywano testów blokad pod obciążeniem na MySQL.
- Duże okna Allegro są automatycznie dzielone przed osiągnięciem limitu stronicowania. Skrajny przypadek ponad 9500 zamówień w jednej sekundzie zatrzyma konto z błędem zamiast pomijać dane.

## Weryfikacja lokalna

```sh
php tests/orders_test.php
node --check dist/js/orders.js
```

Testy korzystają wyłącznie z SQLite w pamięci i atrap transportu: granica 7 dni, duplikaty i konta, mapowanie, ręczny status, reguły, podgląd bez skutków, kwoty/VAT, numeracja, ponowienie dokumentu, kolejne korekty, niezmienność danych, strony importu, błędy, nakładanie okien, wyłączenie konta, wyszukiwarka, puste widoki, XSS i kompilacja wszystkich zakładek oraz wydruku korekty. Nie odczytują poświadczeń ani nie łączą się z marketplace.

Opcjonalnie `OM_PREVIEW_DIR=/tmp/om-preview php tests/orders_test.php` zapisuje statyczne widoki na syntetycznych danych do wskazanego katalogu; pliki nie służą do wykonywania operacji.

## Źródła adapterów

- Allegro: https://developer.allegro.pl/documentation/#operation/getListOfOrdersUsingGET
- Wysyłam z Allegro: https://developer.allegro.pl/tutorials/jak-zarzadzac-przesylkami-przez-wysylam-z-allegro-LRVjK7K21sY
- Erli: https://erli.pl/svc/shop-api/doc/reference/ i https://erli.pl/svc/shop-api/doc/swagger.json
- Mirakl OR11: https://developer.mirakl.com/content/product/mmp/rest/seller/openapi3/orders/or11
- InPost ShipX: https://dokumentacja-inpost.atlassian.net/wiki/spaces/PL/pages/11731061
- Apaczka Web API v2: https://panel.apaczka.pl/dokumentacja_api_v2.php
