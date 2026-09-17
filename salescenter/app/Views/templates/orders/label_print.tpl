<form method="post" action="index.php?controller=orders&action=save">
  <input type="hidden" name="csrf" value="{$csrf|escape}">
  <input type="hidden" name="operation" value="queue_label">
  <input type="hidden" name="order_id" value="{$detail.id}">
  <input type="hidden" name="shipment_id" value="{$p.id}">
  <input type="hidden" name="label_scope" value="shipment">
  <select name="printer_target" required aria-label="Drukarka etykiety">
    <option value="">Drukarka…</option>
    {foreach $printStations as $station}
      {if $station.enabled and $station.printers}
        <optgroup label="{$station.name|escape}{if not $station.online} — offline{/if}">
          {foreach $station.printers as $printer}<option value="{$station.id}|{$printer|escape}">{$printer|escape}</option>{/foreach}
        </optgroup>
      {/if}
    {/foreach}
  </select>
  <label class="om-label-size">Szer. <input name="label_width_mm" type="number" min="30" max="500" step="0.1" value="100" required aria-label="Szerokość etykiety w mm"> mm</label>
  <label class="om-label-size">Wys. <input name="label_height_mm" type="number" min="30" max="500" step="0.1" value="150" required aria-label="Wysokość etykiety w mm"> mm</label>
  <button class="om-btn om-small om-primary"><i class="bi bi-printer-fill"></i> Drukuj</button>
</form>
