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
    <p>Utwórz stanowisko, skopiuj jednorazowo pokazany token i wpisz go w aplikacji Altreo Print Agent.</p>
    {if $canWrite}<form class="om-form" method="post" action="?controller=orders&action=save"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="print_station_create"><input type="hidden" name="tab" value="printing"><label>Nazwa stanowiska<input name="station_name" maxlength="150" required placeholder="Np. Pakowanie 1"></label><button class="om-btn om-primary"><i class="bi bi-plus-lg"></i> Utwórz i pokaż token</button></form>{/if}
    <div class="om-alert om-top"><strong>Adres API do wpisania w agencie:</strong><br><code>{$printAgentApiUrl|escape}</code></div>
    <p class="om-muted">Stanowisko jest online, gdy kontaktowało się z serwerem w ciągu ostatnich 3 minut. Token jest przechowywany wyłącznie jako skrót SHA-256.</p>
  </section>
</div>
<section class="om-panel om-pad om-top">
  <div class="om-panel-heading"><h3>Drukarki fiskalne Posnet</h3><span class="om-chip">{$printFiscalPrinters|count} wykrytych</span></div>
  <p class="om-muted">Agent zgłasza Posnet z adresu IP i portu wpisanych w aplikacji (nie trzeba instalować jej jako drukarki systemowej). Każde urządzenie ma osobną serię lokalną. Przypisz aktywną drukarkę do paragonów w zakładce <a href="?controller=orders&tab=documents">Dokumenty</a>.</p>
  {foreach $printFiscalPrinters as $printer}
    <form class="om-mapping" method="post" action="?controller=orders&action=save">
      <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="fiscal_printer_configure"><input type="hidden" name="tab" value="printing"><input type="hidden" name="fiscal_printer_id" value="{$printer.id}">
      <div><strong>{$printer.name|escape}</strong> {if $receiptPrinterSettings.printer_id eq $printer.id}<span class="om-chip">Przypisana do paragonów</span>{/if}<p>{$printer.host|escape}:{$printer.port} · stanowisko {$printer.station_name|escape}{if $printer.serial_number} · S/N {$printer.serial_number|escape}{/if}</p></div>
      <label>Seria<input name="receipt_series" value="{$printer.receipt_series|escape}" maxlength="40" required></label>
      <label>Tryb<select name="environment"><option value="sandbox" {if $printer.environment eq 'sandbox'}selected{/if}>SANDBOX — wydruk niefiskalny</option><option value="production" {if $printer.environment eq 'production'}selected{/if}>PRODUKCJA — paragon fiskalny</option></select></label>
      <label class="om-check"><input type="checkbox" name="enabled" value="1" {if $printer.enabled}checked{/if}> Aktywna</label>
      {if $canWrite}<button class="om-btn om-small">Zapisz</button>{/if}
    </form>
  {foreachelse}<div class="om-empty"><i class="bi bi-receipt-cutoff"></i><h3>Brak drukarki Posnet</h3><p>Wpisz adres IP i port Interfejsu PC w ustawieniach agenta oraz połącz go z tym stanowiskiem. Możesz też dodać drukarkę ręcznie poniżej.</p></div>{/foreach}
  {if $canWrite}<form class="om-form om-top" method="post" action="?controller=orders&action=save"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="fiscal_printer_add"><input type="hidden" name="tab" value="printing"><label>Stanowisko<select name="station_id" required><option value="">Wybierz stanowisko</option>{foreach $printStations as $station}{if $station.enabled}<option value="{$station.id}">{$station.name|escape}</option>{/if}{/foreach}</select></label><label>Nazwa<input name="fiscal_printer_name" value="Posnet Trio" maxlength="150" required></label><label>IP / host<input name="fiscal_printer_host" placeholder="192.168.1.45" maxlength="255" required></label><label>Port Interfejsu PC<input name="fiscal_printer_port" type="number" min="1" max="65535" value="6666" required></label><button class="om-btn om-primary">Dodaj ręcznie</button></form>{/if}
</section>
<section class="om-panel om-top">
  <table class="om-table"><thead><tr><th>Utworzono (UTC)</th><th>Stanowisko</th><th>Drukarka</th><th>Przesyłka</th><th>Status</th><th>Komunikat</th></tr></thead><tbody>
  {foreach $printJobs as $job}<tr><td>{$job.created_at|escape}</td><td>{$job.station_name|escape}</td><td>{$job.printer_name|escape}</td><td>{if $job.order_id}<a href="?controller=orders&id={$job.order_id}">#{$job.shipment_id}</a>{else}#{$job.shipment_id}{/if}</td><td><span class="om-chip">{if $job.status eq 'queued'}w kolejce{elseif $job.status eq 'processing'}drukowanie{elseif $job.status eq 'printed'}wydrukowano{elseif $job.status eq 'printer_offline'}drukarka offline{else}błąd{/if}</span></td><td>{$job.status_message|default:'—'|escape}</td></tr>{foreachelse}<tr><td colspan="6"><div class="om-empty"><i class="bi bi-clock-history"></i><h3>Kolejka jest pusta</h3><p>Przycisk „Drukuj” pojawia się przy gotowej etykiecie przesyłki.</p></div></td></tr>{/foreach}
  </tbody></table>
</section>
<section class="om-panel om-top">
  <table class="om-table"><thead><tr><th>Utworzono (UTC)</th><th>Drukarka fiskalna</th><th>Zamówienie</th><th>Seria lokalna</th><th>Tryb</th><th>Status</th><th>Numer fiskalny / komunikat</th></tr></thead><tbody>
  {foreach $printFiscalJobs as $job}<tr><td>{$job.created_at|escape}</td><td>{$job.printer_name|escape}</td><td><a href="?controller=orders&id={$job.order_id}">#{$job.order_id}</a></td><td>{$job.local_number|escape}</td><td>{if $job.environment eq 'production'}PRODUKCJA{else}sandbox{/if}</td><td>{$job.status|escape}</td><td>{if $job.fiscal_number}{$job.fiscal_number|escape} · {/if}{$job.status_message|escape}</td></tr>{foreachelse}<tr><td colspan="7"><div class="om-empty"><i class="bi bi-receipt"></i><h3>Brak zadań fiskalnych</h3></div></td></tr>{/foreach}
  </tbody></table>
</section>
