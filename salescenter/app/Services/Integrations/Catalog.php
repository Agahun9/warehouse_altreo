<?php

declare(strict_types=1);
namespace App\Services\Integrations;

/**
 * Opis kanałów sprzedaży dla modułu „Integracje”: sposób łączenia, pola formularza,
 * instrukcje krok po kroku i obsługiwane funkcje.
 * auth: oauth (logowanie przez platformę), woo (logowanie do WordPressa lub klucze),
 *       fields (dane z panelu platformy), api (token dla własnego sklepu).
 */
final class Catalog
{
    public static function all(): array
    {
        return [
            'allegro' => [
                'label' => 'Allegro', 'group' => 'Marketplace', 'color' => '#ff5a00', 'logo' => 'A', 'auth' => 'oauth',
                'tagline' => 'Połącz konto logowaniem do Allegro.',
                'features' => ['Import zamówień', 'Numer przesyłki do Allegro', 'Wysyłam z Allegro', 'Pobieranie od daty'],
                'steps' => [
                    'Kliknij „Zaloguj przez Allegro”.',
                    'Zaloguj się na konto sprzedawcy Allegro (to, z którego chcesz pobierać zamówienia).',
                    'Na ekranie zgody kliknij „Zezwól” – wrócisz tu automatycznie, a konto pojawi się na liście.',
                ],
                'tips' => [
                    'Kolejne konto Allegro? Wyloguj się z Allegro w przeglądarce albo użyj okna prywatnego i połącz ponownie.',
                    'Połączenie jest ważne 3 miesiące od ostatniego użycia – SalesCenter odświeża je automatycznie.',
                ],
                'links' => [['https://allegro.pl/moje-allegro', 'Moje Allegro']],
                'fields' => [],
            ],
            'empik' => [
                'label' => 'Empik', 'group' => 'Marketplace', 'color' => '#e4002b', 'logo' => 'E', 'auth' => 'fields',
                'tagline' => 'Wklej klucz API z panelu EmpikPlace.',
                'features' => ['Import zamówień', 'Automatyczna akceptacja', 'Numer przesyłki do Empik', 'Pobieranie od daty'],
                'steps' => [
                    'Zaloguj się do panelu sprzedawcy: marketplace.empik.com.',
                    'Kliknij ikonę osoby w prawym górnym rogu i wybierz „Profil”.',
                    'Otwórz zakładkę „Klucz API” i kliknij „Wygeneruj nowy klucz”.',
                    'Skopiuj klucz i wklej go poniżej.',
                ],
                'tips' => ['Empik pozwala na jeden klucz na sklep – nowy klucz może unieważnić klucz używany w innym programie.', 'Nowe zamówienia Empik czekają na akceptację – włącz „Automatyczną akceptację”, aby od razu dostawać pełny adres.'],
                'links' => [['https://marketplace.empik.com', 'Panel EmpikPlace'], ['https://www.pomoc.empikplace.com/portal/pl/kb/articles/jak-wygenerowa%C4%87-klucz-api', 'Pomoc Empik: klucz API']],
                'fields' => [
                    ['name' => 'api_key', 'label' => 'Klucz API', 'type' => 'password', 'secret' => true, 'required' => true, 'placeholder' => 'np. 1a2b3c4d-…'],
                    ['name' => 'shop_id', 'label' => 'ID sklepu (opcjonalnie)', 'type' => 'text', 'advanced' => true, 'help' => 'Tylko gdy jeden login obsługuje kilka sklepów.'],
                ],
            ],
            'mediamarkt' => [
                'label' => 'MediaMarkt', 'group' => 'Marketplace', 'color' => '#df0000', 'logo' => 'MM', 'auth' => 'fields',
                'tagline' => 'Wklej klucz API z panelu MediaMarkt Saturn (Mirakl).',
                'features' => ['Import zamówień', 'Automatyczna akceptacja', 'Numer przesyłki do MediaMarkt', 'Pobieranie od daty'],
                'steps' => [
                    'Zaloguj się do panelu sprzedawcy: mediamarktsaturn.mirakl.net.',
                    'Kliknij swoją nazwę w prawym górnym rogu i wybierz „Ustawienia użytkownika” (My user settings).',
                    'Przejdź do zakładki „Klucz API” (API Key) i kliknij „Generuj nowy klucz”.',
                    'Skopiuj klucz i wklej go poniżej.',
                ],
                'tips' => ['Sprzedajesz w kilku krajach (osobne sklepy)? Dodaj osobne połączenie z ID sklepu dla każdego z nich.'],
                'links' => [['https://mediamarktsaturn.mirakl.net', 'Panel MediaMarkt Saturn']],
                'fields' => [
                    ['name' => 'api_key', 'label' => 'Klucz API', 'type' => 'password', 'secret' => true, 'required' => true],
                    ['name' => 'shop_id', 'label' => 'ID sklepu (opcjonalnie)', 'type' => 'text', 'advanced' => true],
                    ['name' => 'api_url', 'label' => 'Adres API (opcjonalnie)', 'type' => 'text', 'advanced' => true, 'placeholder' => 'https://mediamarktsaturn.mirakl.net'],
                ],
            ],
            'erli' => [
                'label' => 'ERLI', 'group' => 'Marketplace', 'color' => '#6d28d9', 'logo' => 'e', 'auth' => 'fields',
                'tagline' => 'Wklej klucz API z panelu sklepu ERLI.',
                'features' => ['Import zamówień', 'Numer przesyłki do ERLI', 'Pobieranie od daty'],
                'steps' => [
                    'Zaloguj się do panelu sprzedawcy ERLI (erli.pl → Twój sklep).',
                    'Wejdź w „Metoda integracji” i wybierz „Własna integracja po API”.',
                    'Skopiuj widoczny tam klucz API i wklej go poniżej.',
                ],
                'tips' => ['Jeśli sklep jest podłączony do innego integratora, zmiana metody integracji może go odłączyć – upewnij się przed zmianą.'],
                'links' => [['https://erli.pl', 'ERLI'], ['https://erli.pl/svc/shop-api/doc', 'Dokumentacja API ERLI']],
                'fields' => [['name' => 'api_key', 'label' => 'Klucz API', 'type' => 'password', 'secret' => true, 'required' => true]],
            ],
            'morele' => [
                'label' => 'Morele', 'group' => 'Marketplace', 'color' => '#0f766e', 'logo' => 'M', 'auth' => 'fields',
                'tagline' => 'Wklej Client ID i Client Secret z panelu Morele Marketplace.',
                'features' => ['Import zamówień', 'Pobieranie od daty'],
                'steps' => [
                    'Zaloguj się do panelu Morele Marketplace.',
                    'Otwórz zakładkę „API” i kliknij „Wygeneruj nowe API”.',
                    'Wpisz nazwę, np. „SalesCenter”, i kliknij „Dodaj”.',
                    'Z „Listy wygenerowanych API” skopiuj Client ID i Client Secret i wklej je poniżej.',
                ],
                'tips' => ['Wygeneruj osobne API tylko dla SalesCenter. Morele wydaje token dla danego Client ID tylko raz, więc danych używanych już w innym programie nie da się podłączyć ponownie.', 'Numer przesyłki wpisz w panelu Morele.'],
                'links' => [['https://marketplace.morele.net/docs', 'Dokumentacja API Morele']],
                'fields' => [
                    ['name' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'required' => true],
                    ['name' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'secret' => true, 'required' => true],
                ],
            ],
            'temu' => [
                'label' => 'Temu', 'group' => 'Marketplace', 'color' => '#fb7701', 'logo' => 'T', 'auth' => 'fields', 'beta' => true,
                'tagline' => 'Wklej App Key, App Secret i Access Token z Temu Seller Center.',
                'features' => ['Import zamówień (beta)', 'Pobieranie od daty'],
                'steps' => [
                    'Zaloguj się do Temu Seller Center i otwórz Temu Partner Platform (partner-eu.temu.com) tym samym kontem.',
                    'W „My Apps” utwórz aplikację typu „Self-developed” (dla własnego sklepu) i zaznacz uprawnienia do zamówień (Order).',
                    'Skopiuj App Key i App Secret aplikacji.',
                    'W Seller Center przejdź do „Account → Authorization management”, autoryzuj aplikację dla sklepu i skopiuj Access Token.',
                    'Wklej trzy wartości poniżej.',
                ],
                'tips' => ['Temu nie pozwala na logowanie aplikacji zewnętrznych bez klucza – to jednorazowa konfiguracja.', 'Access Token ma datę ważności widoczną w Seller Center. Po wygaśnięciu wklej nowy w ustawieniach połączenia.', 'Numer przesyłki wpisz w Seller Center.'],
                'links' => [['https://partner-eu.temu.com', 'Temu Partner Platform (EU)'], ['https://seller-eu.temu.com', 'Temu Seller Center']],
                'fields' => [
                    ['name' => 'app_key', 'label' => 'App Key', 'type' => 'text', 'required' => true],
                    ['name' => 'app_secret', 'label' => 'App Secret', 'type' => 'password', 'secret' => true, 'required' => true],
                    ['name' => 'access_token', 'label' => 'Access Token', 'type' => 'password', 'secret' => true, 'required' => true],
                    ['name' => 'api_url', 'label' => 'Adres API (opcjonalnie)', 'type' => 'text', 'advanced' => true, 'placeholder' => \App\Services\TemuService::DEFAULT_API_URL],
                ],
            ],
            'prestashop' => [
                'label' => 'PrestaShop', 'group' => 'Sklep internetowy', 'color' => '#df0067', 'logo' => 'P', 'auth' => 'fields',
                'tagline' => 'Adres sklepu i klucz webservice (PrestaShop 1.7, 8, 9).',
                'features' => ['Import zamówień', 'Numer przesyłki do sklepu', 'Pobieranie od daty'],
                'steps' => [
                    'W panelu PrestaShop wejdź w „Zaawansowane → Webservice” (w starszych wersjach „Parametry zaawansowane → Webservice”).',
                    'Ustaw „Włącz webservice PrestaShop” na TAK i zapisz.',
                    'Kliknij „Dodaj nowy klucz webservice”, a potem „Wygeneruj”. Opis: SalesCenter, status: TAK.',
                    'W uprawnieniach zaznacz GET (Pokaż) dla: addresses, carriers, configurations, countries, currencies, customers, order_carriers, order_states, orders; dla order_carriers zaznacz też PUT (Zmień).',
                    'Zapisz, skopiuj klucz i wklej go poniżej razem z adresem sklepu.',
                ],
                'tips' => ['Test zwraca błąd 404? Włącz „Przyjazne adresy URL” (Preferencje → Ruch i SEO) i zapisz ustawienia, aby sklep wygenerował reguły .htaccess.', 'Sklep musi działać na https://.'],
                'links' => [['https://devdocs.prestashop-project.org/9/webservice/tutorials/creating-access/', 'Dokumentacja: klucz webservice']],
                'fields' => [
                    ['name' => 'shop_url', 'label' => 'Adres sklepu', 'type' => 'url', 'required' => true, 'placeholder' => 'https://mojsklep.pl'],
                    ['name' => 'api_key', 'label' => 'Klucz webservice', 'type' => 'password', 'secret' => true, 'required' => true],
                ],
            ],
            'woocommerce' => [
                'label' => 'WooCommerce', 'group' => 'Sklep internetowy', 'color' => '#7f54b3', 'logo' => 'W', 'auth' => 'woo',
                'tagline' => 'Podaj adres sklepu i zaloguj się do WordPressa – klucze utworzą się same.',
                'features' => ['Import zamówień', 'Numer przesyłki jako notatka dla klienta', 'Pobieranie od daty'],
                'steps' => [
                    'Wpisz adres sklepu i kliknij „Zaloguj do WordPress i połącz”.',
                    'Zaloguj się jako administrator sklepu (lub menedżer sklepu).',
                    'Na ekranie WooCommerce kliknij „Zatwierdź” – wrócisz tu z gotowym połączeniem.',
                ],
                'tips' => ['Wymagane: https:// i „Bezpośrednie odnośniki” (Ustawienia → Bezpośrednie odnośniki) inne niż „Prosty”.', 'Wolisz ręcznie? WooCommerce → Ustawienia → Zaawansowane → REST API → „Dodaj klucz”, uprawnienia „Odczyt/Zapis”, a następnie wklej Consumer key i Consumer secret w „Połącz kluczami”.'],
                'links' => [['https://woocommerce.com/document/woocommerce-rest-api/', 'Dokumentacja WooCommerce REST API']],
                'fields' => [
                    ['name' => 'shop_url', 'label' => 'Adres sklepu', 'type' => 'url', 'required' => true, 'placeholder' => 'https://mojsklep.pl'],
                    ['name' => 'consumer_key', 'label' => 'Consumer key', 'type' => 'text', 'manual' => true, 'placeholder' => 'ck_…'],
                    ['name' => 'consumer_secret', 'label' => 'Consumer secret', 'type' => 'password', 'secret' => true, 'manual' => true, 'placeholder' => 'cs_…'],
                ],
            ],
            'altreo' => [
                'label' => 'Altreo.pl', 'group' => 'Sklep internetowy', 'color' => '#2954e8', 'logo' => 'AL', 'auth' => 'fields',
                'tagline' => 'Adres sklepu altreo.pl i token API z panelu sklepu.',
                'features' => ['Import zamówień', 'Numer przesyłki do sklepu + e-mail do klienta', 'Pobieranie od daty'],
                'steps' => [
                    'Zaloguj się do panelu administracyjnego sklepu altreo.pl.',
                    'Wejdź w „Ustawienia” → zakładka „SalesCenter” i kliknij „Wygeneruj token”.',
                    'Skopiuj token i wklej go poniżej razem z adresem sklepu.',
                ],
                'tips' => [
                    'Status źródłowy to kod statusu sklepu: nowe, w_realizacji, wyslane, zrealizowane, anulowane – zmapuj je w „Statusy” na statusy wewnętrzne.',
                    'Przekazanie numeru przesyłki zmienia status zamówienia w sklepie na „Wysłane” i wysyła klientowi e-mail z numerem.',
                    'Wygenerowanie nowego tokenu w sklepie unieważnia stary – wklej wtedy nowy w ustawieniach połączenia.',
                ],
                'links' => [['https://altreo.pl/admin/ustawienia#salescenter', 'Panel altreo.pl: SalesCenter']],
                'fields' => [
                    ['name' => 'shop_url', 'label' => 'Adres sklepu', 'type' => 'url', 'required' => true, 'placeholder' => 'https://altreo.pl'],
                    ['name' => 'api_key', 'label' => 'Token API', 'type' => 'password', 'secret' => true, 'required' => true],
                ],
            ],
            'api' => [
                'label' => 'Własny sklep (API)', 'group' => 'Sklep internetowy', 'color' => '#0f172a', 'logo' => '{ }', 'auth' => 'api',
                'tagline' => 'Twój sklep wysyła zamówienia do SalesCenter przez proste REST API.',
                'features' => ['Zamówienia w czasie rzeczywistym', 'Odczyt statusu i numeru przesyłki', 'Dowolna platforma'],
                'steps' => [
                    'Kliknij „Utwórz token API” i skopiuj token (pokazujemy go tylko raz).',
                    'Przekaż programiście adres API i dokumentację z tej karty.',
                    'Sklep wysyła każde nowe lub zmienione zamówienie metodą POST /v1/orders. Powtórzenie nie tworzy duplikatu.',
                    'Opcjonalnie sklep odczytuje status i numer przesyłki z GET /v1/orders/{id}.',
                ],
                'tips' => ['Token dotyczy tylko Twojej firmy. Wygenerowanie nowego natychmiast unieważnia stary.'],
                'links' => [],
                'fields' => [],
            ],
        ];
    }

    public static function get(string $platform): array
    {
        $all = self::all();
        if (!isset($all[$platform])) { throw new \InvalidArgumentException('Nieznany kanał sprzedaży.'); }
        return $all[$platform] + ['platform' => $platform, 'beta' => false];
    }
}
