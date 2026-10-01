# Kontrola Posnet przed produkcją — 1 października 2026

Nie wystawiono ani nie wydrukowano rzeczywistego paragonu. Nie uruchomiono agenta, pollingu, crona ani pobierania zadań produkcyjnych. Nie zmieniono danych w bazie produkcyjnej ani konfiguracji urządzenia.

## Najważniejszy wynik: mapy VAT są różne

Domyślna mapa w ustawieniach aplikacji odpowiada przesłanemu zestawowi:

| Litera | Przesłane ustawienie | Odczyt rzeczywistego Posnet |
|---|---|---|
| A | 23% | 23% |
| B | 8% | 8% |
| C | 7% | 5% |
| D | 5% | 0% |
| E | 0% | zw |
| F | 0% | nieaktywna |
| G | zw | nieaktywna |

Odczyt dotyczy urządzenia wskazanego w lokalnej konfiguracji agenta: 192.168.1.15:6666. Wykonano wyłącznie `sdev`, `sprn`, `scomm`, `vatget`. CRC odpowiedzi poprawne; `ds0`, `pr0`, `ts0` — urządzenie gotowe, bez otwartej transakcji. `100,00` oznacza zwolnienie, `101,00` nieaktywną stawkę.

**Przed fiskalizacją ustawienia aplikacji i urządzenia muszą być zgodne.** Nie programowano stawek urządzenia. Agent porównuje wszystkie A–G przed `trinit`; różnica zatrzymuje wydruk. W ustawieniach można też wybrać „nieaktywna”. Przy kilku jednakowych stawkach wybierana jest pierwsza litera w kolejności A–G, czyli E dla 0% w przesłanym zestawie.

## Poprawione elementy

- Konfiguracja A–G osobno dla każdej drukarki, zapis w `vat_rates_json`; nowa kolumna dodawana przez `ensureSchema()`.
- VAT 7% w walidacji zamówień, dokumentach, ustawieniach serii, formularzach i agencie; brak zamiany 7% na domyślne 23%.
- Konfiguracja VAT utrwalana w zadaniu druku. Zmiana trybu/VAT zablokowana przy zadaniach `queued`/`processing`.
- Dokument wystawiany przez formularz i szybkie wystawienie trafia do kolejki w tej samej transakcji bazy; błąd walidacji cofa dokument, numerację i zadanie. Sam zapis nie drukuje — drukowanie pozostaje działaniem istniejącego agenta po świadomym wystawieniu przez operatora.
- Ochrona przed ponownym zadaniem dla jednego zamówienia; blokada wiersza zamówienia w MySQL.
- Serie niefiskalne nie trafiają do fiskalnej kolejki także przy zwykłym formularzu dokumentu.
- Edycja/usunięcie paragonu przekazanego do Posnet są blokowane.
- Kwoty w groszach, suma pozycji, PLN, dodatnia ilość, nieujemne ceny, nazwy, stawki i NIP kontrolowane w przepływie. NIP przekazywany przed zakończeniem paragonu.
- Brak ponawiania paragonu po utracie odpowiedzi końcowej; ponawiany może być tylko raport wyniku do serwera.
- Błąd konfiguracji VAT zwracany jako wynik błędu agenta, zamiast pozostawienia zadania w przetwarzaniu przez nieobsłużony wyjątek.

## Weryfikacja

- PHP lint: zmienione pliki PHP — poprawne.
- `php salescenter/tests/print_agent_test.php`: 55 kontroli, SQLite w pamięci; także render ustawień A–G, VAT 7%, błędne dane, wycofanie dokumentu/numeracji po błędzie kolejki i podwójne żądanie.
- `php salescenter/tests/orders_test.php`: 242 kontrole; fixture zgłasza ostrzeżenia o brakujących polach `payment_info`/`raw_debug`. Testy przeszły; nie poprawiano niezwiązanego fixture.
- .NET: 20 testów agenta, w tym emulator TCP wyłącznie na loopback. Potwierdzone indeksy stawek, grosze, ilości, NIP, płatność i suma końcowa. Różna mapa nie wywołuje `trinit`. Utrata odpowiedzi na `trend` nie powoduje drugiego `trend` ani anulowania potencjalnie zapisanej sprzedaży.
- Rzeczywiste urządzenie: wyłącznie odczyt stanu i stawek, bez wydruku.
- Nie wykonano wdrożenia ani testu wystawienia w przeglądarce produkcyjnej.

## Wdrożenie i pierwszy paragon

Należy wdrożyć zmienione pliki aplikacji oraz zaktualizować agenta do **1.1.1**. Starszy agent nie egzekwuje pełnego porównania mapy. Paczki w `salescenter/downloads/PrintAgent-Windows-x64.zip` i `PrintAgent-macOS-AppleSilicon.zip` są przygotowane do aktualizacji; nie uruchamiano ich. Paczka Windows została zbudowana na macOS i nie była uruchamiana na Windows.

W zakładce Drukowanie sprawdzić A–G, stanowisko, aktywność i tryb drukarki; w Dokumentach przypisanie drukarki do właściwej serii. Ostateczne uruchomienie produkcyjnego paragonu pozostaje po stronie operatora. Po kliknięciu sprawdzić rzeczywisty wydruk oraz końcowy status zadania Posnet.

Znane ograniczenia zachowane w aktualnym przepływie: wyłącznie PLN i całkowite ilości; pozycje ujemne (w tym osobna ujemna linia rabatu) oraz `np` odrzucane w fiskalnej kolejce. Przy takim błędzie zwykłe/szybkie wystawienie teraz cofa dokument. Referencja „numer dokumentu zam:#ID” ma limit 30 znaków; nazwa pozycji na Posnet skracana do 40 znaków. NIP na paragonie w tym przepływie ograniczony do 450 zł brutto. Oddzielny przepływ automatyzacji dokumentów zachowano — operacje automatyczne nie były uruchamiane.

Zastane zmiany użytkownika zachowano. Nie wykonano commitowania ani publikacji. Wersje plików kontrolera/repozytorium mogą zawierać wcześniejsze, niezwiązane zmiany z tego checkoutu.
