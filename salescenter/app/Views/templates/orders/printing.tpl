<div class="om-section-heading"><div><h2>Automatyczny druk etykiet</h2><p>Stanowiska pobierają zadania w tle, a każda etykieta trafia na drukarkę wybraną przy przesyłce.</p></div><span class="om-chip">{$printStations|count} stanowisk</span></div>
<div class="om-two-col">
  <section class="om-panel om-pad">
    <h3>Stanowiska i drukarki</h3>
    {foreach $printStations as $station}
      <article class="om-rule">
        <div class="om-rule-icon"><i class="bi bi-pc-display"></i></div>
        <div>
          <h4>{$station.name|escape} <span class="om-chip">{if !$station.enabled}wyłączone{elseif $station.online}online{else}offline{/if}</span></h4>
          <small>Ostatni kontakt (UTC): {$station.last_seen_at|default:'jeszcze nigdy'|escape}{if $station.agent_version} · agent {$station.agent_version|escape}{/if}</small>
          {if $station.printers}<p>{foreach $station.printers as $printer}<code>{$printer|escape}</code>{if !$printer@last}, {/if}{/foreach}</p>{else}<p class="om-muted">Agent nie przesłał jeszcze listy drukarek.</p>{/if}
          {if $canWrite}<div class="om-actions"><form method="post" action="?controller=orders&action=save"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="print_station_toggle"><input type="hidden" name="tab" value="printing"><input type="hidden" name="station_id" value="{$station.id}"><input type="hidden" name="enabled" value="{if $station.enabled}0{else}1{/if}"><button class="om-btn om-small">{if $station.enabled}Wyłącz{else}Włącz{/if}</button></form><form method="post" action="?controller=orders&action=save" data-confirm-action="Wygenerować nowy token? Obecny agent utraci dostęp do czasu wpisania nowego tokenu."><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="print_station_token"><input type="hidden" name="tab" value="printing"><input type="hidden" name="station_id" value="{$station.id}"><button class="om-btn om-small">Nowy token</button></form><form method="post" action="?controller=orders&action=save" data-confirm-action="Usunąć tego agenta? Token zostanie natychmiast unieważniony, a zakończona historia jego wydruków i przypisane drukarki fiskalne zostaną trwale usunięte."><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="print_station_delete"><input type="hidden" name="tab" value="printing"><input type="hidden" name="station_id" value="{$station.id}"><button class="om-btn om-small om-danger-outline"><i class="bi bi-trash"></i> Usuń</button></form></div>{/if}
        </div>
      </article>
    {foreachelse}<div class="om-empty"><i class="bi bi-printer"></i><h3>Dodaj pierwsze stanowisko</h3><p>Po wpisaniu tokenu agent sam zgłosi zainstalowane drukarki.</p></div>{/foreach}
  </section>
  <section class="om-panel om-pad">
    <h3>Podłącz agent</h3>
    <div class="sc-agent-download">
      <p><strong>1. Pobierz aplikację agenta druku</strong> na komputer przy drukarce:</p>
      <div class="om-actions">
        <a class="om-btn om-primary" href="downloads/PrintAgent-Windows-x64.zip" download><i class="bi bi-windows"></i> Windows (64-bit)</a>
        <a class="om-btn om-primary" href="downloads/PrintAgent-macOS-AppleSilicon.zip" download><i class="bi bi-apple"></i> macOS (Apple M1–M4)</a>
      </div>
      <p class="om-muted">Windows: rozpakuj i uruchom <code>AltreoPrintAgent.exe</code> (przy ostrzeżeniu SmartScreen: „Więcej informacji → Uruchom mimo to”). macOS: rozpakuj, przeciągnij aplikację do „Aplikacje”, pierwsze uruchomienie przez prawy klik → „Otwórz”.</p>
    </div>
    <p><strong>2.</strong> Utwórz stanowisko, skopiuj jednorazowo pokazany token i wpisz go w aplikacji agenta druku razem z adresem API poniżej.</p>
    {if $canWrite}<form class="om-form" method="post" action="?controller=orders&action=save"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="print_station_create"><input type="hidden" name="tab" value="printing"><label>Nazwa stanowiska<input name="station_name" maxlength="150" required placeholder="Np. Pakowanie 1"></label><button class="om-btn om-primary"><i class="bi bi-plus-lg"></i> Utwórz i pokaż token</button></form>{/if}
    {if $printAgentToken}<div class="om-alert om-top"><strong>Token stanowiska (pokazywany tylko raz):</strong><div class="om-actions"><input type="text" readonly value="{$printAgentToken|escape}" onfocus="this.select()" spellcheck="false" style="flex:1;min-width:0;font-family:monospace"><button type="button" class="om-btn om-small" data-copy-order="{$printAgentToken|escape}"><i class="bi bi-clipboard"></i> <span>Kopiuj</span></button></div></div>{/if}
    <div class="om-alert om-top"><strong>Adres API do wpisania w agencie:</strong><div class="om-actions"><code>{$printAgentApiUrl|escape}</code><button type="button" class="om-btn om-small" data-copy-order="{$printAgentApiUrl|escape}"><i class="bi bi-clipboard"></i> <span>Kopiuj</span></button></div></div>
    <p class="om-muted">W polu „Nazwa stanowiska” w aplikacji agenta używaj tylko liter bez polskich znaków (np. Pakowanie-1, nie Pakowanie główne) – inaczej agent zgłosi błąd „Request headers must contain only ASCII characters”.</p>
    <p class="om-muted">Stanowisko jest online, gdy kontaktowało się z serwerem w ciągu ostatnich 3 minut. Token jest przechowywany wyłącznie jako skrót SHA-256.</p>
  </section>
</div>
<section class="om-panel om-pad om-top">
  <div class="om-fiscal-heading"><div><span class="om-eyebrow">PARAGONY</span><h3>Drukarki fiskalne Posnet</h3><p>Urządzenia połączone z agentem. Ustawienia paragonów znajdziesz w zakładce <a href="?controller=orders&tab=documents">Dokumenty</a>.</p></div><span class="om-chip">{$printFiscalPrinters|count} {if $printFiscalPrinters|count eq 1}drukarka{elseif $printFiscalPrinters|count >= 2 and $printFiscalPrinters|count <= 4}drukarki{else}drukarek{/if}</span></div>
  <div class="om-fiscal-help"><i class="bi bi-info-circle"></i><span>Test Posnet uruchomisz w aplikacji agenta druku. Przy błędzie „No route to host” sprawdź adres IP, port Interfejsu PC (zwykle 6666) i uprawnienie agenta w macOS: Ustawienia systemowe → Prywatność i ochrona → Sieć lokalna.</span></div>
  <div class="om-fiscal-list">
  {foreach $printFiscalPrinters as $printer}
    <article class="om-fiscal-card">
      <div class="om-fiscal-card-head"><div class="om-fiscal-icon"><i class="bi bi-receipt-cutoff"></i></div><div class="om-fiscal-identity"><h4>{$printer.name|escape}</h4><p><code>{$printer.host|escape}:{$printer.port}</code><span>·</span>{$printer.station_name|escape}{if $printer.serial_number}<span>·</span>S/N {$printer.serial_number|escape}{/if}</p></div><div class="om-fiscal-badges"><span class="om-fiscal-badge {if $printer.station_online}is-active{/if}">Agent {if $printer.station_online}online{else}offline{/if}</span><span class="om-fiscal-badge {if $printer.enabled}is-active{/if}">{if $printer.enabled}Aktywna{else}Wyłączona{/if}</span>{if $receiptPrinterSettings.printer_id eq $printer.id}<span class="om-fiscal-badge is-assigned">Przypisana do paragonów</span>{/if}</div></div>
      <form class="om-fiscal-settings" method="post" action="?controller=orders&action=save">
      <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="fiscal_printer_configure"><input type="hidden" name="tab" value="printing"><input type="hidden" name="fiscal_printer_id" value="{$printer.id}">
      <label>Seria lokalna<input name="receipt_series" value="{$printer.receipt_series|escape}" maxlength="40" required></label>
      <label>Tryb<select name="environment"><option value="sandbox" {if $printer.environment eq 'sandbox'}selected{/if}>SANDBOX — wydruk niefiskalny</option><option value="production" {if $printer.environment eq 'production'}selected{/if}>PRODUKCJA — paragon fiskalny</option></select></label>
      <label class="om-check"><input type="checkbox" name="enabled" value="1" {if $printer.enabled}checked{/if}> Aktywna</label>
      {if $canWrite}<button class="om-btn om-small">Zapisz</button>{/if}
      </form>
      {if $canWrite}<form class="om-fiscal-delete" method="post" action="?controller=orders&action=save" data-confirm-action="Usunąć drukarkę {$printer.name|escape}? Zostanie odłączona od serii paragonów. Historia zakończonych wydruków pozostanie dostępna."><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="fiscal_printer_delete"><input type="hidden" name="tab" value="printing"><input type="hidden" name="fiscal_printer_id" value="{$printer.id}"><button class="om-btn om-small om-danger-outline" type="submit"><i class="bi bi-trash"></i> Usuń drukarkę</button></form>{/if}
    </article>
  {foreachelse}<div class="om-empty"><i class="bi bi-receipt-cutoff"></i><h3>Brak drukarki Posnet</h3><p>Wpisz adres IP i port Interfejsu PC w ustawieniach agenta oraz połącz go z tym stanowiskiem. Możesz też dodać drukarkę ręcznie poniżej.</p></div>{/foreach}
  </div>
  {if $canWrite}<details class="om-fiscal-add"><summary><i class="bi bi-plus-circle"></i> Dodaj drukarkę ręcznie</summary><p>Użyj tej opcji, gdy agent nie zgłosił urządzenia automatycznie.</p><form class="om-form" method="post" action="?controller=orders&action=save"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="fiscal_printer_add"><input type="hidden" name="tab" value="printing"><label>Stanowisko<select name="station_id" required><option value="">Wybierz stanowisko</option>{foreach $printStations as $station}{if $station.enabled}<option value="{$station.id}">{$station.name|escape}</option>{/if}{/foreach}</select></label><label>Nazwa<input name="fiscal_printer_name" value="Posnet Trio" maxlength="150" required></label><label>IP / host<input name="fiscal_printer_host" placeholder="192.168.1.45" maxlength="255" required></label><label>Port Interfejsu PC<input name="fiscal_printer_port" type="number" min="1" max="65535" value="6666" required></label><button class="om-btn om-primary">Dodaj drukarkę</button></form></details>{/if}
</section>
<section class="om-panel om-top">
  <table class="om-table"><thead><tr><th>Utworzono (UTC)</th><th>Stanowisko</th><th>Drukarka</th><th>Przesyłka</th><th>Status</th><th>Komunikat</th></tr></thead><tbody>
  {foreach $printJobs as $job}<tr><td>{$job.created_at|escape}</td><td>{$job.station_name|escape}</td><td>{$job.printer_name|escape}</td><td>{if $job.order_id}<a href="?controller=orders&id={$job.order_id}">#{$job.shipment_id}</a>{else}#{$job.shipment_id}{/if}</td><td><span class="om-chip">{if $job.status eq 'queued'}w kolejce{elseif $job.status eq 'processing'}drukowanie{elseif $job.status eq 'printed'}wydrukowano{elseif $job.status eq 'printer_offline'}drukarka offline{else}błąd{/if}</span></td><td>{$job.status_message|default:'—'|escape}</td></tr>{foreachelse}<tr><td colspan="6"><div class="om-empty"><i class="bi bi-clock-history"></i><h3>Kolejka jest pusta</h3><p>Przycisk „Drukuj” pojawia się przy gotowej etykiecie przesyłki.</p></div></td></tr>{/foreach}
  </tbody></table>
</section>
<section class="om-panel om-top">
  <table class="om-table"><thead><tr><th>Utworzono (UTC)</th><th>Drukarka fiskalna</th><th>Zamówienie</th><th>Seria lokalna</th><th>Tryb</th><th>Status</th><th>Numer fiskalny / komunikat</th></tr></thead><tbody>
  {foreach $printFiscalJobs as $job}<tr><td>{$job.created_at|escape}</td><td>{$job.printer_name|escape}{if $job.printer_deleted_at} · usunięta drukarka{elseif !$job.printer_enabled} · wyłączona{/if}</td><td><a href="?controller=orders&id={$job.order_id}">#{$job.order_id}</a></td><td>{$job.local_number|escape}</td><td>{if $job.environment eq 'production'}PRODUKCJA{else}sandbox{/if}</td><td>{$job.status|escape}</td><td>{if $job.fiscal_number}{$job.fiscal_number|escape} · {/if}{$job.status_message|escape}</td></tr>{foreachelse}<tr><td colspan="7"><div class="om-empty"><i class="bi bi-receipt"></i><h3>Brak zadań fiskalnych</h3></div></td></tr>{/foreach}
  </tbody></table>
</section>
