<label class="form-label fw-bold">Parametry Morele:</label>
{* Iteracja po wszystkich cechach *}
{foreach from=$morele_parameters.category_characteristics item=char}
  <div class="mb-3">
    <label class="form-label fw-bold" for="char_{$char.characteristics_id}">
      {$char.characteristics_group_name} - {$char.characteristics_name}
    </label>

    {* Pole wyszukiwania *}
    <input type="text" class="form-control mb-2 live-search" placeholder="Szukaj..."
      data-target="char_{$char.characteristics_id}" />

    <input type="hidden" name="morele_param_type[{$char.characteristics_id}]" value="99" />

    <select name="morele_param[{$char.characteristics_id}]" id="char_{$char.characteristics_id}"
      class="form-select">
      <option value="">-- Wybierz --</option>
      {foreach from=$char.characteristics_values item=val}
        {assign var=option_value value="{$char.characteristics_name}:{$val.characteristic_value_name}"}
        <option value="{$option_value}" {if isset($product.param_morele[$char.characteristics_id|cat:'|99|']) 
    && $product.param_morele[$char.characteristics_id|cat:'|99|'] == $option_value}selected{/if}>
        {$val.characteristic_value_name}
      </option>
      {/foreach}
    </select>
  </div>
  {/foreach}

  <script>
    document.querySelectorAll('.live-search').forEach(function(input) {
      input.addEventListener('input', function() {
        const targetId = this.dataset.target;
        const select = document.getElementById(targetId);
        const filter = this.value.toLowerCase();

        Array.from(select.options).forEach(option => {
          if (option.value === "") return; // zachowaj "-- Wybierz --"
          option.hidden = !option.text.toLowerCase().includes(
          filter); // ukrywa niedopasowane
        });

        // Jeśli jakieś opcje pasują, automatycznie wybierz pierwszą widoczną (opcjonalne)
        const firstVisible = Array.from(select.options).find(opt => !opt.hidden && opt
          .value !== "");
        if (firstVisible) select.value = firstVisible.value;
      });
    });
  </script>
