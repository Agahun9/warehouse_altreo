{assign var=n value=$numbering|default:[]}
<div class="sc-docs-numbering">
  <h4>Numeracja dokumentów</h4>
  {if $n or ($newSeries|default:false)}
    <input type="hidden" name="numbering_mode" value="standard">
    <input type="hidden" name="numbering_start" value="{$n.start|default:1}">
    <div class="om-series-settings">
      <label>Format numeru<select name="numbering_format" {if !$canWrite}disabled{/if}><option value="MONTHLY" {if ($n.format|default:'MONTHLY') eq 'MONTHLY'}selected{/if}>Miesięczny — numer / miesiąc / rok</option><option value="YEARLY" {if ($n.format|default:'') eq 'YEARLY'}selected{/if}>Roczny — numer / rok</option></select></label>
      <label>Oznaczenie przed numerem<input name="prefix" maxlength="20" value="{if $newSeries|default:false}FV{else}{$n.prefix|default:''|escape}{/if}" placeholder="np. FV" {if !$canWrite}disabled{/if}></label>
      <label>Oznaczenie po numerze <small>opcjonalnie</small><input name="suffix" maxlength="20" value="{$n.suffix|default:''|escape}" placeholder="np. SKLEP" {if !$canWrite}disabled{/if}></label>
      <label class="sc-docs-check"><input name="reset_numbering" type="checkbox" value="1" {if !empty($n.reset)}checked{/if} {if !$canWrite}disabled{/if}> Rozpoczynaj numerację od początku w nowym miesiącu / roku</label>
      <div class="sc-docs-number-preview"><span>Podgląd kolejnego numeru</span><code data-number-preview>—</code></div>
      <details class="sc-docs-optional"><summary>Dodatkowe ustawienia numeracji</summary><div class="sc-docs-optional-fields">
        <label>Minimalna liczba cyfr<input name="document_number_length" type="number" min="0" max="8" value="{$n.length|default:0}" {if !$canWrite}disabled{/if}><small class="om-muted">0 — bez zer na początku numeru.</small></label>
        <label>Kolor serii<input name="color_series" type="color" value="{$n.color|default:'#64748b'|escape}" {if !$canWrite}disabled{/if}></label>
        <label class="sc-docs-wide">Stała adnotacja na dokumentach<textarea name="additional_text" maxlength="2000" {if !$canWrite}disabled{/if}>{$n.notes|default:''|escape}</textarea></label>
      </div></details>
    </div>
  {else}
    <input type="hidden" name="numbering_mode" value="custom">
    <input type="hidden" name="pattern" value="{$numberingPattern|default:''|escape}">
    <div class="sc-docs-number-preview"><span>Obecny format tej serii</span><code>{$numberingPattern|default:''|escape}</code></div>
    <p class="om-muted">Format istniejącej serii jest zachowany. Kolejny numer ustawisz w polu powyżej.</p>
  {/if}
</div>
