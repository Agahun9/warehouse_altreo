<form class="om-label-print" method="post" title="Format etykiety: {$labelActionPrinterDefault.width|default:100}×{$labelActionPrinterDefault.height|default:150} mm (ustawienia → Drukowanie)" action="index.php?controller=orders&action=save">
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
          {foreach $station.printers as $printer}<option value="{$station.id}|{$printer|escape}" {if ($labelPrinterDefault.target|default:'') eq ($station.id|cat:'|'|cat:$printer)}selected{/if}>{$printer|escape}</option>{/foreach}
        </optgroup>
      {/if}
    {/foreach}
  </select>
  <button class="om-btn om-small om-primary"><i class="bi bi-printer-fill"></i> Drukuj</button>
</form>
