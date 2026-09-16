# Altreo Print Agent

Mała aplikacja tray dla Windows i macOS. Odpytuje serwer przez HTTPS, pobiera
zadania druku, weryfikuje plik PDF, drukuje bez okna dialogowego i raportuje
wynik. Windows używa osadzonego SumatraPDF, macOS korzysta z systemowego CUPS.

## Co jest gotowe

- działanie w tle i ikona w trayu/menu barze (Avalonia),
- autostart użytkownika: rejestr `HKCU` na Windows i LaunchAgent na macOS,
- polling co 2–300 sekund,
- token stanowiska w nagłówku `Authorization: Bearer ...`,
- automatyczne zgłaszanie do panelu listy drukarek i drukarki domyślnej,
- wybór konkretnej drukarki etykiet i własnego papieru, domyślnie 100 x 150 mm,
- akcje drukowania najnowszej albo wszystkich etykiet zamówienia,
- wykrywanie zainstalowanych sieciowych drukarek Posnet/Trio i osobna seria lokalna dla każdej z nich,
- ręczna konfiguracja adresu IP i portu Posnet bez instalowania jej jako drukarki systemowej,
- kontrolowany wydruk testowy niefiskalny bezpośrednio z okna ustawień,
- obsługa paragonów fiskalnych Posnet 1.01 przez TCP/IP z kontrolą CRC, stanu urządzenia, papieru i stawek VAT,
- rozdzielone profile `sandbox` i `production` z osobnymi katalogami konfiguracji,
- pobieranie wyłącznie przez HTTPS (HTTP jest opcją tylko do testów),
- limit PDF 100 MB i kontrola sygnatury `%PDF-`,
- bezpieczne przekazywanie parametrów procesu bez powłoki systemowej,
- druk Windows: `SumatraPDF.exe -silent -print-to ...`,
- druk macOS: `lpstat` i `lp`,
- statusy `processing`, `printed`, `error`, `printer_offline`,
- retry końcowego raportu statusu i log w katalogu danych aplikacji,
- blokada uruchomienia dwóch kopii agenta.

`printed` oznacza, że systemowy spooler przyjął zadanie. Ani SumatraPDF, ani
CUPS nie mogą wiarygodnie potwierdzić, że kartka fizycznie wyszła z drukarki.
Awaria urządzenia już po przyjęciu zadania może być widoczna tylko w kolejce
systemowej.

## Uruchomienie deweloperskie

Wymagany jest .NET SDK 9.

```bash
dotnet restore AltreoPrintAgent.sln
dotnet test AltreoPrintAgent.sln -c Release
dotnet run --project src/AltreoPrintAgent/AltreoPrintAgent.csproj
```

Przy pierwszym uruchomieniu wpisz:

1. adres API pokazany w panelu, np. `https://magazyn.altreo.pl/crm/new_version/print-agent-api.php`,
2. token przypisany do konkretnego stanowiska,
3. nazwę stanowiska,
4. interwał pollingu i opcję autostartu.

Nazwa drukarki musi być identyczna jak w systemie. Na Windows można ją
sprawdzić w Ustawieniach drukarek lub poleceniem SumatraPDF `-list-printers`.
Na macOS użyj `lpstat -p`.

Konfiguracja i log znajdują się w:

- Windows: `%APPDATA%\AltreoPrintAgent\production\` albo `...\sandbox\`,
- macOS: `~/Library/Application Support/AltreoPrintAgent/production/` albo `/sandbox/`.

Posnet Trio musi mieć ustawiony `Interfejs PC = TCP/IP` oraz port w menu urządzenia.
Adres i port wpisuje się w ustawieniach agenta. Agent zgłasza urządzenie do panelu
bez konieczności instalowania go jako zwykłej drukarki systemowej. Przycisk
`Drukuj test niefiskalny Posnet` sprawdza gotowość urządzenia i drukuje trzy linie
testowe bez rejestrowania sprzedaży.
Z terminala `--test-posnet-receipt 192.168.1.15 6666` drukuje syntetyczny paragon
sandboxowy o kwocie 1,00 PLN, wyraźnie oznaczony jako niefiskalny.
Numer widoczny w panelu jest
lokalnym numerem serii przypisanym do urządzenia; właściwy numer paragonu fiskalnego
nadaje wyłącznie drukarka.

Sandbox drukuje na Posnet oznaczony paragon niefiskalny bez rejestrowania sprzedaży. Jeden
agent w profilu produkcyjnym pobiera zarówno zadania produkcyjne, jak i sandboxowe;
profil sandboxowy nigdy nie pobiera zadań fiskalnych. Tryb wybiera się przy drukarce
w panelu zamówień. Profil produkcyjny obsługuje protokół Posnet 1.01 bezpośrednio po TCP/IP. Nie ponawia
automatycznie niejednoznacznego zadania po przerwaniu połączenia, aby nie wystawić
drugiego paragonu. Taki przypadek wymaga sprawdzenia wydruku i ręcznego rozstrzygnięcia.
Na urządzeniu 192.168.1.15:6666 potwierdzono wydruk testowy niefiskalny; faktyczny
paragon fiskalny wymaga osobnej weryfikacji na realnym zamówieniu oraz kontroli
mapowania stawek VAT ustawionych w drukarce. Sam test niefiskalny nie fiskalizuje sprzedaży.

## Pojedynczy EXE dla Windows

Na Windows, w PowerShell 7 lub Windows PowerShell 5.1:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\build-windows.ps1 -Profile production
.\scripts\build-windows.ps1 -Profile sandbox
```

Skrypt pobiera oficjalną, przenośną SumatraPDF 3.6.1, sprawdza SHA-256, osadza
ją jako zasób i publikuje samowystarczalny plik:

```text
dist\production\win-x64\AltreoPrintAgent.exe
dist\sandbox\win-x64\AltreoPrintAgent-Sandbox.exe
```

Użytkownik końcowy nie potrzebuje .NET ani osobnej instalacji SumatraPDF.
Przy pierwszym druku osadzony program jest wypakowywany do lokalnego katalogu
aplikacji. Wersja ARM64: `./scripts/build-windows.ps1 -Runtime win-arm64`.

EXE nie jest podpisany cyfrowo. Do dystrybucji firmowej warto podpisać go
certyfikatem Authenticode, inaczej Windows SmartScreen może pokazać ostrzeżenie.
Informacje licencyjne SumatraPDF są w `THIRD_PARTY_NOTICES.md`.

## Build dla macOS

Na Macu z Apple Silicon:

```bash
./scripts/build-macos.sh osx-arm64 production
./scripts/build-macos.sh osx-arm64 sandbox
```

Dla Maca Intel użyj `osx-x64`. Wynikiem jest:

```text
dist/production/osx-arm64/Altreo Print Agent.app
dist/sandbox/osx-arm64/Altreo Print Agent Sandbox.app
```

Pakiet `.app` jest pojedynczym elementem widocznym dla użytkownika. Wewnątrz ma
samowystarczalny plik wykonywalny i wymagane natywne biblioteki Avalonia. Skrypt
wykonuje lokalny podpis ad-hoc. Do dystrybucji poza własnymi komputerami
potrzebny jest certyfikat Apple Developer ID i notarization.

## Kontrakt API

Wszystkie endpointy wymagają nagłówka:

```http
Authorization: Bearer TOKEN_STANOWISKA
X-Station-Name: MAGAZYN-01
X-Agent-Environment: sandbox
```

### Test połączenia

```http
GET /api/print-agent/health
200 {"ok":true,"station":"MAGAZYN-01"}
```

### Następne zadanie

```http
GET /api/print-agent/jobs/next
```

Brak zadania to `204 No Content`. Zadanie:

```json
{
  "id": "d4f25355-5d4e-4a76-916a-4c7f63195074",
  "pdfUrl": "https://magazyn.altreo.pl/print/pdf/abc?signature=...",
  "printerName": "Zebra ZD421",
  "printSettings": "fit,paper=100mm x 150mm"
}
```

`printSettings` jest opcjonalnym ciągiem ustawień CLI SumatraPDF. Na macOS agent
zamienia wymiar niestandardowy na opcję `media=Custom...` CUPS. Serwer powinien atomowo zarezerwować zadanie podczas odpowiedzi,
aby dwa agenty nie wydrukowały go równocześnie.

### Lista drukarek i heartbeat

Agent raz na minutę wysyła `POST /api/print-agent/stations/heartbeat` z listą
drukarek zainstalowanych na danym komputerze. Dzięki temu operator wybiera w
panelu prawdziwą drukarkę, zamiast ręcznie wpisywać jej nazwę.

### Raport statusu

```http
POST /api/print-agent/jobs/d4f25355-5d4e-4a76-916a-4c7f63195074/status
Content-Type: application/json

{
  "status": "printed",
  "message": "Zadanie przekazane do kolejki drukarki.",
  "printerName": "Zebra ZD421",
  "stationName": "MAGAZYN-01",
  "reportedAt": "2026-09-15T08:00:00Z"
}
```

Przykładowy backend PHP 8.2/MySQL 8 znajduje się w
`examples/php/print-agent.php`, a tabele w `examples/php/schema.sql`. Endpoint
celowo nie przywraca automatycznie starych zadań `processing` do kolejki — takie
przywracanie po zerwaniu raportu mogłoby spowodować drugi wydruk. Administrator
powinien ręcznie rozstrzygać stare zadania.

Przykładowe dodanie zadania po stronie magazynu:

```php
$id = $pdo->query('SELECT UUID()')->fetchColumn();
$stmt = $pdo->prepare('INSERT INTO print_agent_jobs
    (id, station_id, pdf_url, printer_name, print_settings)
    VALUES (?, ?, ?, ?, ?)');
$stmt->execute([$id, $stationId, $signedPdfUrl, 'Zebra ZD421', 'fit,paper=A6']);
```

URL PDF powinien być krótko ważnym, podpisanym adresem HTTPS. Token stanowiska
nie jest wysyłany przy pobieraniu PDF. Każde stanowisko powinno mieć osobny,
losowy token (minimum 32 bajty), przechowywany na serwerze wyłącznie jako SHA-256.

## Struktura

- `src/AltreoPrintAgent` — aplikacja,
- `tests/AltreoPrintAgent.Tests` — testy,
- `scripts` — build Windows/macOS,
- `examples/php` — referencyjne API dla magazyn.altreo.pl.
