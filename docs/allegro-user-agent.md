# Allegro User-Agent

Źródło: https://apps.developer.allegro.pl/user-agent

Allegro wymaga własnego nagłówka `User-Agent` we wszystkich wywołaniach API. Po 30 czerwca 2026 r. żądania bez prawidłowego nagłówka mają być odrzucane.

Format:

```text
ApplicationName/Version (+DocumentationURL)
```

W tej aplikacji skonfigurowano:

```text
accra_shop-magazyn-nowy/2026.09.08 (+https://magazyn.altreo.pl/crm/new_version/allegro-app-info.php)
```

Wartości znajdują się w `app/Config/app.php`, w sekcji `allegro`. `application_name` musi dokładnie odpowiadać nazwie widocznej przy danym kluczu w panelu Allegro Developer Apps. Numer wersji można aktualizować przy wydaniach bez ponownej rejestracji. Adres dokumentacji musi być publiczny i identyfikować właściciela integracji.

Każde konto Allegro przechowuje nazwę aplikacji przypisaną do swojego `client_id`. Można wybrać `accra_shop magazyn nowy` albo `Accra_shop Magazyn` podczas dodawania i edycji konta. Spacje są zamieniane na myślniki dopiero podczas budowania nagłówka.

`AllegroService` usuwa ewentualny obcy lub domyślny nagłówek i dodaje dokładnie jeden skonfigurowany `User-Agent` do żądań REST API, OAuth oraz Wysyłam z Allegro. Ten sam identyfikator jest używany przy pobieraniu obrazów ofert.
