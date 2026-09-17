<form class="oc-label-batch" method="post" action="index.php?controller=orders&action=save">
  <input type="hidden" name="csrf" value="{$csrf|escape}">
  <input type="hidden" name="operation" value="queue_label">
  <input type="hidden" name="order_id" value="{$detail.id}">
  <select name="printer_target" required aria-label="Drukarka etykiet">
    <option value="">Wybierz drukarkę etykiet…</option>
    {foreach $printStations as $station}
      {if $station.enabled and $station.printers}
        <optgroup label="{$station.name|escape}{if not $station.online} — offline{/if}">
          {foreach $station.printers as $printer}<option value="{$station.id}|{$printer|escape}">{$printer|escape}</option>{/foreach}
        </optgroup>
      {/if}
    {/foreach}
  </select>
  <label>Szerokość <input name="label_width_mm" type="number" min="30" max="500" step="0.1" value="100" required> mm</label>
  <label>Wysokość <input name="label_height_mm" type="number" min="30" max="500" step="0.1" value="150" required> mm</label>
  <button class="om-btn om-small om-primary" name="label_scope" value="newest"><i class="bi bi-printer"></i> Drukuj najnowszą</button>
  <button class="om-btn om-small" name="label_scope" value="all"><i class="bi bi-printers"></i> Drukuj wszystkie</button>
</form>
