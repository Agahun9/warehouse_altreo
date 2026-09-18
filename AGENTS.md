# AGENTS.md — zasady pracy w `magazyn_new`

## Cel i zakres

To repozytorium zawiera wewnętrzny system magazynowo-sprzedażowy ALTREO. Przy każdej zmianie wybieraj najmniejszy możliwy zakres analizy i edycji. Nie skanuj całego projektu, jeśli nazwa modułu, kontrolera, akcji, szablonu albo klasy pozwala wskazać właściwe pliki.

## Architektura projektu

Aplikacja jest monolitem PHP z prostym, własnym układem MVC:

- `index.php` definiuje `BASE_PATH`, ładuje `app/bootstrap.php` i uruchamia `App\Core\Application`.
- `app/Core/Application.php` jest routerem. Standardowe strony mają postać `index.php?controller=<moduł>&action=<metoda>`; nazwa akcji odpowiada publicznej metodzie kontrolera.
- `app/Controllers/*Controller.php` obsługują HTTP, autoryzację, walidację wejścia i wybierają widok/usługę.
- `app/Models/*Repository.php` zawierają dostęp do MySQL przez `App\Core\Database`/PDO. W tym projekcie wiele repozytoriów tworzy lub uzupełnia schemat w `ensureSchema()`.
- `app/Services/*.php` zawierają logikę domenową, import/eksport, pocztę oraz integracje z marketplace'ami.
- `app/Views/templates/**/*.tpl` to źródłowe widoki Smarty. `Controller::render()` dokłada wspólny `layout/header.tpl` i `layout/footer.tpl`.
- `dist/` zawiera gotowe, bezpośrednio serwowane CSS, JS i obrazy. Nie znaleziono osobnego procesu bundlowania ani konfiguracji Node; część CSS/JS jest osadzona bezpośrednio w plikach `.tpl`.
- Konfiguracja aplikacji i bazy jest w `app/Config/`; nie ujawniaj ani nie kopiuj danych dostępowych z tych plików do odpowiedzi, logów lub testów.

Autoload klas `App\...` jest realizowany ręcznie w `app/bootstrap.php` zgodnie ze ścieżką `app/<Namespace>.php`. Smarty jest ładowane z `vendor/autoload.php`, jeśli istnieje, albo z dołączonego `smarty-5.8.0`.

## Mapa projektu

- `index.php` -> główny webowy punkt wejścia.
- `index.html` -> przekierowanie do `index.php`.
- `app/bootstrap.php` -> autoload klas aplikacji i Smarty.
- `app/Core/Application.php` -> mapa kontrolerów oraz jawne trasy API.
- `app/Core/Controller.php` -> sesja, JWT, uprawnienia modułów, odpowiedzi API i renderowanie Smarty.
- `app/Core/Database.php` -> singleton PDO, zapytania, transakcje i blokady MySQL.
- `app/Core/Config.php`, `app/Config/app.php`, `app/Config/database.php` -> odczyt i wartości konfiguracji.
- `app/Controllers` -> warstwa HTTP; zwykle pierwszy plik backendu dla konkretnego ekranu lub akcji.
- `app/Models` -> repozytoria, zapytania SQL i runtime'owe `ensureSchema()`.
- `app/Services` -> logika domenowa oraz klienci Allegro, Empik, ERLI, MediaMarkt, Morele, Sellasist i Temu.
- `app/Database/auth.sql`, `app/Database/products.sql` -> bazowe definicje SQL; nie są kompletną historią schematu, bo część tabel i kolumn powstaje w repozytoriach.
- `app/Views/templates` -> źródłowe widoki Smarty podzielone według modułów.
- `app/Views/templates/layout` -> wspólny nagłówek, menu, globalne style/skrypty i stopka.
- `app/Views/templates/computers/partials` -> fragmenty pól parametrów marketplace dla modułu komputerów.
- `app/Views/templates_c`, `app/Views/cache` -> wygenerowane pliki Smarty; nigdy nie edytuj i nie analizuj ich jako źródła.
- `dist/css` -> AdminLTE i wspólny motyw `liquid-glass.css`.
- `dist/js` -> gotowe skrypty.
- `dist/assets/img` -> statyczne zasoby interfejsu.
- `app/Storage/imports`, `app/Storage/logs`, `app/Storage/mail` -> dane robocze i logi; pomijaj w wyszukiwaniu.
- `uploads`, `img_components`, `img_computers_products` -> pliki użytkowników i duże zbiory obrazów; nie skanuj bez bezpośredniej potrzeby.
- `tests/marketplace_account_deletion_test.php` -> samodzielny test usuwania kont marketplace; nie jest pełnym zestawem testów całej aplikacji.
- `docs/allegro-user-agent.md` -> dokumentacja User-Agent Allegro.
- `salescenter/` -> osobna aplikacja centrum sprzedaży (zamówienia) z własną bazą, konfiguracją i testami; nie współdzieli kodu z `app/`.
- `allegro_api_access.php` -> przekierowanie callbacku OAuth Allegro do głównego routera.
- `allegro-app-info.php` -> publiczna strona identyfikacyjna integracji Allegro.
- `fix_marketplace_sku_collation.php` -> jednorazowy, kosztowny skrypt CLI zmieniający collation; nie uruchamiaj podczas zwykłych testów.
- `smarty-5.8.0`, `php` -> dołączone zależności/runtime; traktuj jak kod zewnętrzny.
- `OBRYSY_GENERATOR`, `temporary` -> duże lub robocze zasoby niezwiązane ze standardowym przepływem MVC.

## Gdzie szukać funkcji aplikacji

- Produkty, stany, pola własne, parametry ofert i obrysy: `ProductController.php`, `ProductRepository.php`, powiązane repozytoria `Product*`, `SharedStockGroupRepository.php`, `DerivedStockLinkRepository.php`, następnie `templates/products/`.
- Komputery, komponenty, warianty, prywatne kategorie komputerów oraz komputerowe CSV/tytuły: `ComputersController.php`, repozytoria zaczynające się od `Computer`, `templates/computers/`.
- Ogólne szablony/import/eksport CSV: `CsvTemplateController.php`, `CsvExportService.php`, repozytoria `Csv*`, `templates/csv_templates/`.
- Kategorie produktów i mapowania marketplace: `CategoryController.php`, `CategoryRepository.php`, `templates/categories/`.
- Marketplace: odpowiednia para `app/Controllers/<Marketplace>Controller.php` + `app/Services/<Marketplace>Service.php`; zapis/cache/kolejki są zwykle w `app/Models/<Marketplace>StorageRepository.php`, a UI w katalogu szablonów o tej samej nazwie.
- Zamówienia: wyłącznie w osobnej aplikacji `salescenter/` (patrz `salescenter/README.md`); główna aplikacja nie ma już centrum zamówień.
- Sellasist i synchronizacja stanów: `SellasistController.php`, `SellasistService.php`, repozytoria `Sellasist*` oraz `templates/sellasist/`.
- Magazyn księgowy, XML/XLSX, dokumenty i rozliczenia: `AccountingWarehouseController.php`, `AccountingWarehouseRepository.php`, usługi `AccountingWarehouse*`, `templates/accounting_warehouse/`.
- Użytkownicy, logowanie, JWT, role i uprawnienia: `AuthController.php`, `AdministrationController.php`, `UserRepository.php`, `JwtService.php`, `TokenService.php`, `templates/auth/` i `templates/administration/`.
- Ustawienia integracji i automatyzacje: `AdministrationController.php`, `SettingRepository.php`, `templates/administration/automation.tpl`.
- Taskboard: `TaskboardController.php`, `TaskboardRepository.php`, `templates/taskboard/`.
- Czas pracy: `WorkTimeController.php`, `WorkTimeRepository.php`, `templates/worktime/`.
- Biblioteka mediów: `MediaController.php`, `MediaRepository.php`, `templates/media/`.
- Szablony druku: `PrintTemplateController.php`, `templates/printtemplates/`.
- Dashboard i wspólna nawigacja: `IndexController.php`, `templates/index.tpl`, `templates/layout/header.tpl` i `footer.tpl`.

Jawne REST-owe trasy API znajdują się w `Application::handleApiRoutes()` i obecnie dotyczą `api/products/search`, `api/products/sku/<sku>`, `api/products/<id>` oraz `api/export/csv/<id>`. Pozostałe wywołania JSON/AJAX zwykle nadal używają `controller` + `action`, więc szukaj nazwy akcji w kontrolerze i w `fetch`/URL szablonu lub skryptu.

## Lokalizowanie pliku przy małej zmianie

1. Ustal moduł z URL, nazwy ekranu, komunikatu, pola albo integracji.
2. Zacznij od jednego kontrolera i odpowiadającego mu katalogu `app/Views/templates/<moduł>/`.
3. W kontrolerze znajdź dokładną metodę akcji oraz wywołanie `render()`, `jsonResponse()` lub używaną usługę/repozytorium.
4. Szukaj symbolicznie, np. `rg -n "nazwaAkcji|nazwaMetody|tekst UI" app/Controllers app/Views/templates app/Services app/Models dist/js dist/css`.
5. Śledź tylko bezpośrednie `use`, `new`, wywołania metod, nazwę szablonu i odwołania do endpointu. Nie otwieraj kolejnych plików, jeśli aktualne pliki wystarczają do bezpiecznej zmiany.
6. Przy błędzie SQL szukaj nazwy tabeli/kolumny najpierw w właściwym `*Repository.php`, jego `ensureSchema()` i dopiero potem w `app/Database/*.sql`.
7. Przy zmianie UI sprawdź najpierw właściwy `.tpl`. Otwórz `layout/header.tpl`, `layout/footer.tpl` lub pliki `dist/*` tylko wtedy, gdy element jest wspólny albo szablon jawnie odwołuje się do danego assetu.

Do wyszukiwania zawsze dodawaj konkretne katalogi lub wykluczenia. Nie uruchamiaj szerokiego `rg`/`find` od korzenia bez filtrów.

## Zasady wykonywania zmian

- Przy małym zadaniu analizuj najpierw wyłącznie pliki bezpośrednio związane z zadaniem. Domyślny cel to edycja 1–3 plików.
- Przed większą zmianą wypisz sobie minimalny łańcuch: punkt wejścia/router -> kontroler -> usługa/repozytorium -> szablon/asset -> test. Otwieraj tylko elementy potrzebne z tego łańcucha.
- Nie refaktoryzuj bez wyraźnej prośby. Nie przenoś klas, nie rozbijaj dużych kontrolerów i nie wprowadzaj frameworka „przy okazji”.
- Nie zmieniaj działającej architektury, publicznych metod kontrolerów, parametrów URL, formatu JSON ani API marketplace bez konieczności wynikającej z zadania.
- Nie poprawiaj formatowania, nazw, komentarzy ani sąsiedniego kodu niezwiązanego z zadaniem. Zachowaj styl istniejącego pliku, także starszą składnię tablic tam, gdzie jest używana.
- Nie edytuj wygenerowanych plików `app/Views/templates_c`; źródłem są pliki `.tpl`.
- Nie zmieniaj plików konfiguracyjnych z sekretami ani danych w `app/Storage`/`uploads`, chyba że zadanie wskazuje je wprost.
- Operacje synchronizacji marketplace mogą zmieniać zewnętrzne oferty, ceny lub stany. Do diagnozy preferuj odczyt, fixture/test albo tryb preview; nie uruchamiaj produkcyjnej synchronizacji zapisu bez wyraźnego polecenia.
- Nie wykonuj kosztownego `fix_marketplace_sku_collation.php` ani innych jednorazowych operacji bazodanowych jako zwykłej walidacji.
- Zastane niezwiązane zmiany w `git status` pozostaw nietknięte.

## Katalogi wyłączone z rutynowego skanowania

Nie przeszukuj ani nie czytaj rekurencyjnie: `.git`, `.vs`, `.vscode`, `vendor`, `node_modules`, `smarty-5.8.0`, `php`, `app/Views/templates_c`, `app/Views/cache`, `app/Storage`, `uploads`, `temporary`, `OBRYSY_GENERATOR`, `img_components`, `img_computers_products`, buildów, cache, logów, backupów i wygenerowanych podglądów. Wejdź do takiego katalogu tylko wtedy, gdy użytkownik wskazuje konkretny zasób lub błąd jednoznacznie z niego pochodzi.

## Weryfikacja zmian

- Dla każdego zmienionego pliku PHP uruchom co najmniej `php -l <plik>`.
- Dla zmiany w `.tpl` sprawdź składnię/pełne renderowanie tylko danego widoku, jeśli istnieje odpowiedni test. Wykonaj ukierunkowaną kontrolę użytych zmiennych i powiązanych akcji zamiast uruchamiać nieistniejący globalny build.
- Dla CSS/JS sprawdź tylko zmieniony ekran i jego konsolę/przepływ. Nie ma potrzeby przebudowy całego `dist`, ponieważ pliki są serwowane bezpośrednio.
- Nie uruchamiaj testów wymagających prawdziwych kont marketplace, produkcyjnej bazy, crona lub zapisu zewnętrznego, o ile zadanie nie wymaga testu live i nie daje do niego upoważnienia.
- W podsumowaniu rozróżnij: lint/statyczny test, test na danych syntetycznych oraz faktyczną weryfikację w przeglądarce, bazie lub zewnętrznym API.
