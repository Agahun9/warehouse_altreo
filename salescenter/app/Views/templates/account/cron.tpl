<main class="app-main"><div class="sc-page">
  <h1 class="h3 mb-1">Globalne zadania cron</h1>
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
