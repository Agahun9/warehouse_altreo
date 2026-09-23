<main class="app-main om ms">
<div class="om-shell">
  <header class="om-hero ms-hero">
    <div><div class="om-eyebrow">SPRZEDAŻ · KOMUNIKACJA</div><h1>Log Morele</h1><p>Tymczasowy log diagnostyczny centrum komunikacji Morele: surowe żądania, odpowiedzi API i decyzje synchronizacji. Tokeny i nagłówki nie są zapisywane. Plik trzyma ostatnie 512 KB.</p></div>
    <div class="om-hero-actions">
      <a class="om-btn" href="index.php?controller=messages&tab=settings"><i class="bi bi-arrow-left"></i> Ustawienia</a>
      {if $logSize > 0}<a class="om-btn" href="index.php?controller=messages&action=log&download=1"><i class="bi bi-download"></i> Pobierz plik</a>{/if}
      {if $canWrite && $logSize > 0}
      <form method="post" action="index.php?controller=messages&action=save">
        <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="log_clear">
        <button class="om-btn om-primary" type="submit"><i class="bi bi-trash"></i> Wyczyść log</button>
      </form>
      {/if}
    </div>
  </header>
  {if $flashSuccess}<div class="om-alert success">{$flashSuccess|escape}</div>{/if}
  {if $flashError}<div class="om-alert error">{$flashError|escape}</div>{/if}
  <section class="om-panel om-pad">
    {if $logSize > 0}
      <p class="ms-note">Rozmiar pliku: {($logSize/1024)|string_format:"%.1f"} KB. Log zapisuje się, dopóki w ustawieniach Morele zaznaczony jest „Log diagnostyczny”.</p>
      <pre class="ms-log">{$logContent|escape}</pre>
    {else}
      <p class="ms-note">Log jest pusty. Zaznacz „Log diagnostyczny” w ustawieniach Morele, kliknij „Synchronizuj” przy koncie Morele i wróć tutaj.</p>
    {/if}
  </section>
</div>
</main>
