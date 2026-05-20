<label class="form-label fw-bold">Parametry Empik (parametry oddzielone znakiem | ):</label>
<div id="empik_custom_params">
  {if $product.param_empik}
    {foreach from=$product.param_empik key=ename item=eval}
      <div class="empik-param-row mb-2 d-flex align-items-center gap-2">
        <input type="text" name="empik_custom_name[]" value="{$ename|escape:'html'}" class="form-control" placeholder="Nazwa parametru" />
        <input type="text" name="empik_custom_value[]" value="{$eval|escape:'html'}" class="form-control" placeholder="Wartość" />
        <button type="button" class="btn btn-sm btn-danger remove-empik-param">Usuń</button>
      </div>
    {/foreach}
  {else}
    <div class="empik-param-row mb-2 d-flex align-items-center gap-2">
      <input type="text" name="empik_custom_name[]" value="" class="form-control" placeholder="Nazwa parametru" />
      <input type="text" name="empik_custom_value[]" value="" class="form-control" placeholder="Wartość" />
      <button type="button" class="btn btn-sm btn-danger remove-empik-param">Usuń</button>
    </div>
  {/if}
</div>
<div class="mt-2">
  <button type="button" id="addEmpikParamBtn" class="btn btn-sm btn-outline-primary">Dodaj parametr Empik</button>
</div>

{literal}
<script>
  (function(){
    const container = document.getElementById('empik_custom_params');
    const addBtn = document.getElementById('addEmpikParamBtn');

    function escapeHtmlAttr(s) {
      return String(s).replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function createRow(name = '', value = '') {
      const row = document.createElement('div');
      row.className = 'empik-param-row mb-2 d-flex align-items-center gap-2';
      row.innerHTML = "\n        <input type=\"text\" name=\"empik_custom_name[]\" value=\"" + escapeHtmlAttr(name) + "\" class=\"form-control\" placeholder=\"Nazwa parametru\" />\n        <input type=\"text\" name=\"empik_custom_value[]\" value=\"" + escapeHtmlAttr(value) + "\" class=\"form-control\" placeholder=\"Wartość\" />\n        <button type=\"button\" class=\"btn btn-sm btn-danger remove-empik-param\">Usuń</button>\n      ";
      return row;
    }

    addBtn.addEventListener('click', function() {
      container.appendChild(createRow('', ''));
    });

    container.addEventListener('click', function(e) {
      if (e.target && e.target.classList.contains('remove-empik-param')) {
        const row = e.target.closest('.empik-param-row');
        if (row) row.remove();
      }
    });
  })();
</script>
{/literal}
