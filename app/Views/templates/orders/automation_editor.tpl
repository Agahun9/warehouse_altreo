<section class="oa-editor" id="oa-editor">
  <form method="post" action="?controller=orders&action=save" data-oa-form novalidate>
    <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="rule_save"><input type="hidden" name="tab" value="rules"><input type="hidden" name="rule_id" value="{$automation.edit.id}"><input type="hidden" name="rule_json" value="" data-oa-json>
    <script type="application/json" data-oa-catalog>{$automation.catalog_json}</script>
    <script type="application/json" data-oa-rule>{$automation.rule_json}</script>
    <header class="oa-editor-head">
      <div class="oa-editor-title">
        <span class="oa-eyebrow">{if $automation.edit.id}EDYCJA AUTOMATYZACJI #{$automation.edit.id}{else}NOWA AUTOMATYZACJA{/if}</span>
        <input class="oa-name-input" data-oa-name data-template="{$automation.template|escape}" maxlength="150" placeholder="Nazwij regułę, np. Opłacone → do spakowania" aria-label="Nazwa automatyzacji" autocomplete="off">
      </div>
      <div class="oa-editor-head-actions">
        <label class="oa-toggle is-compact"><input type="checkbox" data-oa-enabled><span class="oa-switch-ui" aria-hidden="true"></span><span><strong>Aktywna</strong></span></label>
        <a class="om-btn" href="?controller=orders&tab=rules"><i class="bi bi-x-lg"></i> Zamknij</a>
      </div>
    </header>
    <div class="oa-builder">
      <div class="oa-col oa-col-when">
        <section class="oa-block">
          <header class="oa-block-head"><span class="oa-block-no">1</span><div><h3>Wyzwalacz</h3><p>Kiedy sprawdzić regułę. Możesz zaznaczyć kilka zdarzeń.</p></div></header>
          <div class="oa-trigger-grid" data-oa-triggers></div>
          <div class="oa-delay" data-oa-delay hidden></div>
        </section>
        <section class="oa-block">
          <header class="oa-block-head"><span class="oa-block-no is-if">2</span><div><h3>Warunki</h3><p>Bez warunków reguła obejmie każde zamówienie.</p></div><div class="oa-segment" data-oa-match role="group" aria-label="Sposób łączenia warunków"></div></header>
          <div class="oa-conditions" data-oa-conditions></div>
          <button type="button" class="oa-add" data-oa-add-condition><i class="bi bi-plus-lg"></i> Dodaj warunek</button>
        </section>
      </div>
      <div class="oa-builder-arrow" aria-hidden="true"><span><i class="bi bi-arrow-right"></i></span></div>
      <div class="oa-col oa-col-then">
        <section class="oa-block">
          <header class="oa-block-head"><span class="oa-block-no is-then">3</span><div><h3>Efekty</h3><p>Kroki wykonują się jeden po drugim, w tej kolejności.</p></div></header>
          <ol class="oa-steps" data-oa-actions></ol>
          <button type="button" class="oa-add is-then" data-oa-add-action><i class="bi bi-plus-lg"></i> Dodaj efekt</button>
        </section>
        <section class="oa-block">
          <header class="oa-block-head"><span class="oa-block-no is-muted">4</span><div><h3>Ustawienia i przyciski</h3><p>Limit wykonań oraz miejsca, z których uruchomisz regułę ręcznie.</p></div></header>
          <div class="oa-settings" data-oa-settings></div>
        </section>
      </div>
    </div>
    <footer class="oa-editor-foot">
      <div class="oa-sentence" data-oa-sentence aria-live="polite"></div>
      <div class="oa-editor-foot-actions">
        <div class="oa-editor-errors" data-oa-errors role="alert" hidden></div>
        <a class="om-btn" href="?controller=orders&tab=rules">Anuluj</a>
        <button class="om-btn om-primary" type="submit"><i class="bi bi-check2"></i> Zapisz automatyzację</button>
      </div>
    </footer>
  </form>
</section>
