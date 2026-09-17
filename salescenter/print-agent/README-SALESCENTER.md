# Agent druku dla SalesCenter

Kopia projektu `ALTREO_PRINT_APP` (Avalonia, .NET 9) – ta sama aplikacja obsługuje SalesCenter.

- Adres API do wpisania w agencie: `https://<adres SalesCenter>/print-agent-api.php` (widoczny w zakładce Drukarki).
- Token stanowiska tworzysz w SalesCenter → Drukarki. Token zawiera identyfikator firmy, więc jeden serwer obsługuje wiele firm.
- Gotowe paczki do pobrania: `../downloads/PrintAgent-Windows-x64.zip`, `../downloads/PrintAgent-macOS-AppleSilicon.zip`.

Budowanie nowych wersji: `scripts/build-windows.ps1` i `scripts/build-macos.sh` (wymaga .NET SDK 9). Nazwa aplikacji (`Altreo Print Agent`)
pochodzi z plików projektu; zmiana brandingu wymaga przebudowania.
