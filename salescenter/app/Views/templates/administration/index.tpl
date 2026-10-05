<main class="app-main"><div class="sc-page">
  <h1 class="h3 mb-1">Administracja SalesCenter</h1>
  <p class="text-secondary">Ustawienia całej platformy. Widzi je tylko główny administrator – firmy korzystające z SalesCenter nie mają tu dostępu.</p>
  {if $flashSuccess}<div class="alert alert-success">{$flashSuccess|escape}</div>{/if}
  {if $flashError}<div class="alert alert-danger">{$flashError|escape}</div>{/if}
  <div class="card mb-4" id="notes"><div class="card-body">
    <h2 class="h5">Notatki</h2>
    <p class="text-secondary small">Twoje notatki, np. linki webhooków do magazynu (odejmowanie / dodawanie sztuk). Widoczne tylko tutaj.</p>
    <form method="post" action="index.php?controller=administration&action=savenotes">
      <input type="hidden" name="csrf" value="{$csrf|escape}">
      <textarea name="notes" class="form-control font-monospace mb-2" rows="8" maxlength="20000" spellcheck="false">{$adminNotes|escape}</textarea>
      <button class="btn btn-primary">Zapisz notatki</button>
    </form>
  </div></div>
  <div class="card mb-4" id="warehouse-api"><div class="card-body">
    <h2 class="h5">API zbierania magazynowego</h2>
    <p class="text-secondary small">Token jest przypisany do firmy <strong>{$warehouseApiTenant|escape}</strong> i ograniczony do pobierania jej zamówień z wybranego statusu oraz zmiany statusu po wydruku. Wygenerowanie nowego unieważni poprzedni token dla tego API.</p>
    <dl class="row small mb-3">
      <dt class="col-sm-3">Adres API</dt><dd class="col-sm-9"><code>{$warehouseApiBase|escape}</code></dd>
      <dt class="col-sm-3">Pobieranie</dt><dd class="col-sm-9"><code>{$warehouseApiPickingUrl|escape}</code></dd>
      <dt class="col-sm-3">Autoryzacja</dt><dd class="col-sm-9"><code>Authorization: Bearer &lt;token&gt;</code></dd>
      {if $warehouseApi}<dt class="col-sm-3">Token zapisany</dt><dd class="col-sm-9">{if $warehouseApi.status eq 'active'}Aktywny{else}Nieaktywny{/if}, końcówka <code>…{$warehouseApi.token_hint|escape}</code></dd>{/if}
    </dl>
    {if $warehouseApiToken}
      <div class="alert alert-success">
        <strong>Skopiuj token teraz — później nie będzie można go odczytać.</strong>
        <input class="form-control font-monospace mt-2" value="{$warehouseApiToken|escape}" readonly onclick="this.select()" aria-label="Nowy token API zbierania">
      </div>
    {/if}
    <form method="post" action="index.php?controller=administration&action=warehouseapitoken" onsubmit="return confirm('Nowy token unieważni poprzedni token API zbierania. Wygenerować?')">
      <input type="hidden" name="csrf" value="{$csrf|escape}">
      <button class="btn btn-primary">{if $warehouseApi}Wygeneruj nowy token{else}Wygeneruj token API{/if}</button>
    </form>
    <p class="small text-secondary mt-2 mb-0">Wklej token do ustawień zbierania SalesCenter w CRM magazynu. W polu adresu podaj adres bazowy tej aplikacji, np. <code>https://magazyn.altreo.pl/crm/new_version/salescenter</code>.</p>
  </div></div>
  <div class="card mb-4" id="allegro-app"><div class="card-body">
    <h2 class="h5">Aplikacja Allegro</h2>
    {if $allegroApp.configured}
      <p class="mb-2"><span class="badge text-bg-success">Włączona</span> Client ID <code>{$allegroApp.client_id_hint|escape}</code>{if $allegroApp.source eq 'file'} (z pliku <code>app/Config/app.php</code>){elseif $allegroApp.saved_by} · zapisał {$allegroApp.saved_by|escape}{if $allegroApp.saved_at}, {$allegroApp.saved_at|pl_time|escape}{/if}{/if}</p>
      <p class="text-secondary small">Każda firma łączy konto Allegro samym logowaniem: Konta i import → Dodaj kanał → Allegro → „Zaloguj przez Allegro”.</p>
    {else}
      <p class="mb-2"><span class="badge text-bg-warning">Nie skonfigurowana</span> Firmy nie mogą jeszcze łączyć kont Allegro.</p>
    {/if}
    <p class="mb-2 small">Link do dokumentacji aplikacji (do formularza Allegro): <a href="{$allegroApp.docs_url|escape}" target="_blank" rel="noopener noreferrer"><code>{$allegroApp.docs_url|escape}</code></a></p>
    {if $allegroApp.source neq 'file'}
    <details{if !$allegroApp.configured} open{/if}>
      <summary class="mb-2">{if $allegroApp.configured}Zmień aplikację Allegro{else}Skonfiguruj aplikację Allegro (jednorazowo){/if}</summary>
      <ol class="small">
        <li>Otwórz <a href="https://apps.developer.allegro.pl/new" target="_blank" rel="noopener noreferrer">apps.developer.allegro.pl</a> i zaloguj się kontem Allegro.</li>
        <li>„Zarejestruj nową aplikację”, nazwa np. SalesCenter, typ <strong>„Aplikacja będzie miała dostęp do przeglądarki”</strong>.</li>
        <li>Adres URI do przekierowania: <code>{$allegroApp.redirect_uri|escape}</code></li>
        <li>Adres dokumentacji / strony aplikacji: <code>{$allegroApp.docs_url|escape}</code></li>
        <li>Zaznacz uprawnienia do zamówień, przesyłek, wiadomości i ofert oraz <strong>odczyt profilu konta (<code>allegro:api:profile:read</code>)</strong> – bez niego połączenie kończy się błędem 403. Zapisz i skopiuj Client ID oraz Client Secret.</li>
      </ol>
      <form method="post" action="index.php?controller=administration&action=allegroapp" class="row g-2">
        <input type="hidden" name="csrf" value="{$csrf|escape}">
        <div class="col-md-5"><label class="form-label" for="allegro-client-id">Client ID</label><input id="allegro-client-id" name="client_id" class="form-control" autocomplete="off" required></div>
        <div class="col-md-5"><label class="form-label" for="allegro-client-secret">Client Secret</label><input id="allegro-client-secret" name="client_secret" type="password" class="form-control" autocomplete="off" required></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Sprawdź i zapisz</button></div>
        <div class="col-12 small text-secondary">Client Secret jest szyfrowany. Zmiana aplikacji wymaga ponownego zalogowania kont Allegro we wszystkich firmach.</div>
      </form>
    </details>
    {/if}
  </div></div>
  <h2 class="h4 mb-1">Globalne zadania cron</h2>
  <p class="text-secondary">Oba zadania obejmują wszystkie aktywne firmy i ich połączone konta. Linki zawierają tajne klucze — wklej je tylko do własnego harmonogramu.</p>
  <div class="card mb-3"><div class="card-body">
    <h2 class="h5">Odświeżanie tokenów</h2>
    <p>Uruchamiaj raz dziennie. Odświeża tokeny wszystkich aktywnych połączeń Allegro i Morele. Pozostałe integracje używają kluczy API albo odświeżają dostęp podczas własnych żądań.</p>
    <label class="form-label" for="cron-tokens">Link do crona</label>
    <input id="cron-tokens" class="form-control font-monospace" readonly value="{$cronUrls.tokens|escape}">
    <p class="small text-secondary mt-2 mb-1">Harmonogram: <code>0 3 * * *</code>. Do pola „Komenda” w panelu serwera wklej:</p>
    <input class="form-control font-monospace" readonly value="{$cronCommands.tokens|escape}">
  </div></div>
  <div class="card"><div class="card-body">
    <h2 class="h5">Pobieranie zamówień</h2>
    <p>Uruchamiaj co minutę. Sprawdza zamówienia wszystkich aktywnych firm oraz wykonuje zaplanowane automatyzacje. Równoległy przebieg zostanie pominięty.</p>
    <label class="form-label" for="cron-orders">Link do crona</label>
    <input id="cron-orders" class="form-control font-monospace" readonly value="{$cronUrls.orders|escape}">
    <p class="small text-secondary mt-2 mb-1">Harmonogram: <code>* * * * *</code>. Do pola „Komenda” w panelu serwera wklej:</p>
    <input class="form-control font-monospace" readonly value="{$cronCommands.orders|escape}">
  </div></div>
</div></main>
