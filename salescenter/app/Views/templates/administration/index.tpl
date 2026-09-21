<main class="app-main"><div class="sc-page">
  <h1 class="h3 mb-1">Administracja SalesCenter</h1>
  <p class="text-secondary">Ustawienia całej platformy. Widzi je tylko główny administrator – firmy korzystające z SalesCenter nie mają tu dostępu.</p>
  {if $flashSuccess}<div class="alert alert-success">{$flashSuccess|escape}</div>{/if}
  {if $flashError}<div class="alert alert-danger">{$flashError|escape}</div>{/if}
  <div class="card mb-4" id="allegro-app"><div class="card-body">
    <h2 class="h5">Aplikacja Allegro</h2>
    {if $allegroApp.configured}
      <p class="mb-2"><span class="badge text-bg-success">Włączona</span> Client ID <code>{$allegroApp.client_id_hint|escape}</code>{if $allegroApp.source eq 'file'} (z pliku <code>app/Config/app.php</code>){elseif $allegroApp.saved_by} · zapisał {$allegroApp.saved_by|escape}{if $allegroApp.saved_at}, {$allegroApp.saved_at|escape}{/if}{/if}</p>
      <p class="text-secondary small">Każda firma łączy konto Allegro samym logowaniem: Konta i import → Dodaj kanał → Allegro → „Zaloguj przez Allegro”.</p>
    {else}
      <p class="mb-2"><span class="badge text-bg-warning">Nie skonfigurowana</span> Firmy nie mogą jeszcze łączyć kont Allegro.</p>
    {/if}
    {if $allegroApp.source neq 'file'}
    <details{if !$allegroApp.configured} open{/if}>
      <summary class="mb-2">{if $allegroApp.configured}Zmień aplikację Allegro{else}Skonfiguruj aplikację Allegro (jednorazowo){/if}</summary>
      <ol class="small">
        <li>Otwórz <a href="https://apps.developer.allegro.pl/new" target="_blank" rel="noopener noreferrer">apps.developer.allegro.pl</a> i zaloguj się kontem Allegro.</li>
        <li>„Zarejestruj nową aplikację”, nazwa np. SalesCenter, typ <strong>„Aplikacja będzie miała dostęp do przeglądarki”</strong>.</li>
        <li>Adres URI do przekierowania: <code>{$allegroApp.redirect_uri|escape}</code></li>
        <li>Zaznacz uprawnienia do zamówień, przesyłek, wiadomości i ofert, zapisz i skopiuj Client ID oraz Client Secret.</li>
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
