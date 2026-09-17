{assign var=n value=$numbering|default:[]}
<fieldset class="om-series-settings">
  <legend>Format i ustawienia numeracji</legend>
  <label>Sposób numeracji<select name="numbering_mode" {if !$canWrite}disabled{/if}><option value="custom" {if !$n}selected{/if}>Własny wzór numeru</option><option value="standard" {if $n}selected{/if}>Miesięczna / roczna jak w Sellasist</option></select></label>
  <small class="om-muted">Dla własnego wzoru obowiązuje pole „Wzór numeru” powyżej. Poniższe ustawienia działają po wyborze numeracji miesięcznej / rocznej.</small>
  <label>Prefix<input name="prefix" maxlength="20" value="{$n.prefix|default:''|escape}" {if !$canWrite}disabled{/if}></label>
  <label>Suffix<input name="suffix" maxlength="20" value="{$n.suffix|default:''|escape}" {if !$canWrite}disabled{/if}></label>
  <label>Kolor serii<input name="color_series" type="color" value="{$n.color|default:'#64748b'|escape}" {if !$canWrite}disabled{/if}></label>
  <label>Format numeracji<select name="numbering_format" {if !$canWrite}disabled{/if}><option value="MONTHLY" {if ($n.format|default:'MONTHLY') eq 'MONTHLY'}selected{/if}>Miesięczna (1/09/2026)</option><option value="YEARLY" {if ($n.format|default:'') eq 'YEARLY'}selected{/if}>Roczna (1/2026)</option></select></label>
  <label>Numer początkowy<input name="numbering_start" type="number" min="1" max="100000000" value="{$n.start|default:1}" {if !$canWrite}disabled{/if}></label>
  <label>Minimalna długość numeru<input name="document_number_length" type="number" min="0" max="8" value="{$n.length|default:0}" {if !$canWrite}disabled{/if}></label>
  <label><input name="reset_numbering" type="checkbox" value="1" {if !empty($n.reset)}checked{/if} {if !$canWrite}disabled{/if}> Resetuj licznik w nowym miesiącu / roku</label>
  <label>Uwagi na dokumencie<textarea name="additional_text" maxlength="2000" {if !$canWrite}disabled{/if}>{$n.notes|default:''|escape}</textarea></label>
  <small class="om-muted">Numeracja standardowa utworzy wzór prefix/numer/miesiąc/rok lub prefix/numer/rok z podanym suffixem. Zmiany dotyczą nowych dokumentów.</small>
</fieldset>
