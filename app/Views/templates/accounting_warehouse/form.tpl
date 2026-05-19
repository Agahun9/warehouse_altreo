<main class="app-main">
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-6">
          <h3 class="mb-0">{$contentTitle|escape}</h3>
          <p class="text-secondary mb-0">{$pageDescription|escape}</p>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-end">
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=index">Start</a></li>
            <li class="breadcrumb-item"><a href="{$baseUrl}?controller=accountingwarehouse&action=index">Magazyn ksiegowy</a></li>
            <li class="breadcrumb-item active" aria-current="page">{$breadcrumbCurrent|escape}</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      {if $flashSuccess}<div class="alert alert-success">{$flashSuccess|escape}</div>{/if}
      {if $flashError}<div class="alert alert-danger">{$flashError|escape}</div>{/if}

      {capture assign=itemOptions}{foreach $itemSuggestions as $suggestion}<option value="{$suggestion|escape}">{$suggestion|escape}</option>{/foreach}{/capture}
      {capture assign=currencyOptionsHtml}{foreach $currencyOptions as $currencyOption}<option value="{$currencyOption|escape}">{$currencyOption|escape}</option>{/foreach}{/capture}

      {if $isEdit}
        {assign var=edit value=$formData.edit_document}
        <div class="card mb-4">
          <div class="card-header"><h3 class="card-title mb-0">Edycja dokumentu</h3></div>
          <div class="card-body">
            <form method="post" action="{$baseUrl}?controller=accountingwarehouse&action=update">
              <input type="hidden" name="id" value="{$edit.id}">
              <input type="hidden" name="edit_source_type" value="{$edit.source_type|escape}">
              <input type="hidden" name="edit_document_kind" value="{$edit.document_kind|escape}">
              <input type="hidden" name="edit_xml_filename" value="{$edit.xml_filename|escape}">
              <input type="hidden" name="edit_xml_hash" value="{$edit.xml_hash|escape}">
              <input type="hidden" name="edit_xml_payload" value="{$edit.xml_payload|escape}">

              <div class="row g-3 mb-3">
                <div class="col-md-4"><label class="form-label">Numer dokumentu</label><input type="text" class="form-control" name="edit_document_number" value="{$edit.document_number|escape}"></div>
                <div class="col-md-4"><label class="form-label">Dostawca</label><input type="text" class="form-control" name="edit_supplier_name" value="{$edit.supplier_name|escape}"></div>
                <div class="col-md-4"><label class="form-label">NIP</label><input type="text" class="form-control" name="edit_supplier_tax_id" value="{$edit.supplier_tax_id|escape}"></div>
                <div class="col-md-3"><label class="form-label">Data wystawienia</label><input type="date" class="form-control" name="edit_issue_date" value="{$edit.issue_date|escape}"></div>
                <div class="col-md-3"><label class="form-label">Data sprzedazy</label><input type="date" class="form-control" name="edit_sale_date" value="{$edit.sale_date|escape}"></div>
                <div class="col-md-3">
                  <label class="form-label">Waluta</label>
                  <select class="form-select" name="edit_currency">
                    {foreach $currencyOptions as $currencyOption}
                      <option value="{$currencyOption|escape}"{if $edit.currency eq $currencyOption} selected{/if}>{$currencyOption|escape}</option>
                    {/foreach}
                  </select>
                </div>
                <div class="col-md-3"><label class="form-label">Uwagi</label><input type="text" class="form-control" name="edit_notes" value="{$edit.notes|escape}"></div>
              </div>

              <div class="alert alert-light border small mb-3">
                Kolumny pozycji: `Ilosc` to liczba sztuk lub jednostek, `Netto / szt.` i `Brutto / szt.` to cena jednostkowa, `VAT %` przelicza netto i brutto automatycznie. `Suma netto` i `Suma brutto` pokazuja wartosc calego wiersza.
              </div>

              <div id="editRows" class="d-grid gap-3">
                {foreach $edit.lines as $line}
                  <div class="border rounded p-3 aw-line-row">
                    <div class="row g-2 align-items-end">
                      <div class="col-md-4"><label class="form-label">Opis z faktury</label><input type="text" class="form-control" name="edit_original_name[]" value="{$line.original_name|escape}"></div>
                      <div class="col-md-3">
                        <label class="form-label">Pozycja ksiegowa</label>
                        <div class="input-group">
                          <select class="form-select canonical-select item-name-select" name="edit_canonical_name[]">
                            {$itemOptions nofilter}
                          </select>
                          <button type="button" class="btn btn-outline-secondary btn-sm refresh-item-names" title="Odswiez pozycje ksiegowe" aria-label="Odswiez pozycje ksiegowe">↻</button>
                        </div>
                      </div>
                      <div class="col-md-1"><label class="form-label">Ilosc</label><input type="number" step="0.001" class="form-control" name="edit_quantity[]" value="{$line.quantity|string_format:'%.3f'}"></div>
                      <div class="col-md-1"><label class="form-label">Jedn.</label><input type="text" class="form-control" name="edit_unit[]" value="{$line.unit|escape}"></div>
                      <div class="col-md-1"><label class="form-label">Netto / szt.</label><input type="number" step="0.01" class="form-control line-calc-input" data-field="net" name="edit_unit_net[]" value="{$line.unit_net|string_format:'%.2f'}"></div>
                      <div class="col-md-1"><label class="form-label">Brutto / szt.</label><input type="number" step="0.01" class="form-control line-calc-input" data-field="gross" name="edit_unit_gross[]" value="{$line.unit_gross|string_format:'%.2f'}"></div>
                      <div class="col-md-1"><label class="form-label">VAT %</label><input type="number" step="0.01" class="form-control line-calc-input" data-field="vat" name="edit_vat_rate[]" value="{$line.vat_rate|string_format:'%.2f'}"></div>
                    </div>
                    <div class="row g-2 mt-2">
                      <div class="col-md-2 offset-md-8"><label class="form-label">Suma netto</label><input type="text" class="form-control line-total-net" value="{($line.quantity * $line.unit_net)|string_format:'%.2f'}" readonly></div>
                      <div class="col-md-2"><label class="form-label">Suma brutto</label><input type="text" class="form-control line-total-gross" value="{($line.quantity * $line.unit_gross)|string_format:'%.2f'}" readonly></div>
                    </div>
                    <script>document.currentScript.parentElement.querySelector('select[name="edit_canonical_name[]"]').value = '{$line.canonical_name|escape:'javascript'}';</script>
                  </div>
                {/foreach}
              </div>

              <div class="border-top pt-3 mt-3">
                <div class="row g-2 justify-content-end">
                  <div class="col-md-2"><label class="form-label">Suma dokumentu netto</label><input type="text" class="form-control document-total-net" value="0.00" readonly></div>
                  <div class="col-md-2"><label class="form-label">Suma dokumentu brutto</label><input type="text" class="form-control document-total-gross" value="0.00" readonly></div>
                </div>
              </div>

              <button type="button" class="btn btn-outline-secondary mt-3" id="addEditRow">Dodaj pozycje</button>
              <button type="submit" class="btn btn-primary mt-3">Zapisz zmiany</button>
            </form>
          </div>
        </div>
      {else}
        <div class="row g-4">
          <div class="col-12">
            <div class="card mb-4">
              <div class="card-header"><h3 class="card-title mb-0">Import faktury XML</h3></div>
              <div class="card-body">
                <div class="d-flex justify-content-end gap-2 mb-3">
                  <a href="{$baseUrl}?controller=accountingwarehouse&action=macros" class="btn btn-sm btn-outline-secondary">Zarzadzaj pozycjami ksiegowymi i aliasami</a>
                </div>
                <form method="post" action="{$baseUrl}?controller=accountingwarehouse&action=previewxml" enctype="multipart/form-data" id="xmlUploadForm">
                  <label class="form-label">Pliki XML</label>
                  <input type="file" class="form-control mb-3" name="invoice_xml[]" id="xmlUploadInput" accept=".xml,text/xml,application/xml" multiple required>
                  <div class="small text-secondary mb-2">Mozesz wrzucic wiele plikow naraz. Kazdy dokument pokazemy osobno, sprawdzimy duplikaty i pozwolimy masowo zmieniac pozycje ksiegowe.</div>
                  <div class="small text-secondary mb-3">Przy duzych paczkach system dzieli wysylke automatycznie na mniejsze partie, wiec nie musisz ograniczac sie do 20 plikow naraz.</div>
                  <div class="alert alert-info d-none mb-3" id="xmlBatchProgress"></div>
                  <button type="submit" class="btn btn-primary">Wczytaj XML-e</button>
                </form>
              </div>
            </div>

            {if $xmlPreview}
              <div class="card mb-4 border-info">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                  <h3 class="card-title mb-0">Podglad importu XML</h3>
                  <span class="badge text-bg-secondary">Wczytano faktur: {$xmlPreview|@count}</span>
                </div>
                <div class="card-body">
                  <div class="alert alert-warning mb-3 d-none">
                    Korekty z XML sa tylko oznaczane do sprawdzenia. Przy zapisie system automatycznie je pominie i zapisze wyłącznie zwykle przyjecia.
                  </div>
                  <div class="alert alert-warning mb-3">
                    Korekty z XML sa tylko oznaczane do sprawdzenia. Przy zapisie system automatycznie je pominie i zapisze tylko zwykle przyjecia.
                  </div>
                  <form method="post" action="{$baseUrl}?controller=accountingwarehouse&action=storexml" id="xmlPreviewForm">
                    {foreach $xmlPreview as $documentIndex => $document}
                      <div class="border rounded p-3 mb-4 xml-document-block supplier-lookup-block{if $document.header.document_kind|default:'receipt' eq 'adjustment'} border-warning border-3 bg-warning-subtle{/if}" data-xml-hash="{$document.header.xml_hash|escape}">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                          <div>
                            <div class="fw-semibold">Faktura {$documentIndex+1} z {$xmlPreview|@count}: {$document.header.document_number|default:'bez numeru'|escape}</div>
                            <div class="small text-secondary">{$document.header.supplier_name|default:'-'|escape} | sprzedaz: {$document.header.sale_date|default:'-'|escape}</div>
                          </div>
                          <div class="d-flex flex-wrap gap-2">
                            {if $document.header.document_kind|default:'receipt' eq 'adjustment'}
                              <span class="badge text-bg-warning">KOREKTA XML - BEDZIE POMINIETA</span>
                            {else}
                              <span class="badge text-bg-success">PRZYJECIE DO IMPORTU</span>
                            {/if}
                            <span class="badge text-bg-info">plik: {$document.header.xml_filename|escape}</span>
                          </div>
                        </div>

                        {if $document.header.document_kind|default:'receipt' eq 'adjustment'}
                          <div class="alert alert-warning border-warning mb-3">
                            Ten XML wyglada na korekte. Zostawiamy go w podgladzie do kontroli, ale przy zapisie zostanie automatycznie pominiety.
                          </div>
                        {/if}

                        <div class="duplicate-alert mb-3{if empty($document.duplicate)} d-none{/if}">
                          <div class="alert alert-danger mb-0">
                            {if !empty($document.duplicate)}
                              Duplikat dokumentu. Istnieje juz dokument #{$document.duplicate.id} ({$document.duplicate.document_number|default:'bez numeru'|escape}).
                            {/if}
                          </div>
                        </div>

                        <div class="row g-3 mb-3">
                          <div class="col-md-3"><label class="form-label">Numer dokumentu</label><input type="text" class="form-control document-number-input" name="xml_documents[{$documentIndex}][document_number]" value="{$document.header.document_number|escape}"></div>
                          <div class="col-md-2">
                            <label class="form-label">Rodzaj dokumentu</label>
                            <select class="form-select" name="xml_documents[{$documentIndex}][document_kind]">
                              <option value="receipt"{if $document.header.document_kind|default:'receipt' eq 'receipt'} selected{/if}>przyjecie</option>
                              <option value="adjustment"{if $document.header.document_kind|default:'receipt' eq 'adjustment'} selected{/if}>korekta</option>
                            </select>
                          </div>
                          <div class="col-md-3 position-relative">
                            <label class="form-label">Dostawca</label>
                            <input type="text" class="form-control supplier-name-input" name="xml_documents[{$documentIndex}][supplier_name]" value="{$document.header.supplier_name|escape}" autocomplete="off">
                            <div class="list-group position-absolute w-100 shadow-sm supplier-name-results" style="z-index: 20; display: none;"></div>
                          </div>
                          <div class="col-md-2 position-relative">
                            <label class="form-label">NIP</label>
                            <input type="text" class="form-control supplier-tax-id-input" name="xml_documents[{$documentIndex}][supplier_tax_id]" value="{$document.header.supplier_tax_id|escape}" autocomplete="off">
                            <div class="list-group position-absolute w-100 shadow-sm supplier-tax-id-results" style="z-index: 20; display: none;"></div>
                          </div>
                          <div class="col-md-1"><label class="form-label">Data wystawienia</label><input type="date" class="form-control" name="xml_documents[{$documentIndex}][issue_date]" value="{$document.header.issue_date|escape}"></div>
                          <div class="col-md-1"><label class="form-label">Data sprzedazy</label><input type="date" class="form-control" name="xml_documents[{$documentIndex}][sale_date]" value="{$document.header.sale_date|escape}"></div>
                          <div class="col-md-2">
                            <label class="form-label">Waluta</label>
                            <select class="form-select" name="xml_documents[{$documentIndex}][currency]">
                              {foreach $currencyOptions as $currencyOption}
                                <option value="{$currencyOption|escape}"{if $document.header.currency eq $currencyOption} selected{/if}>{$currencyOption|escape}</option>
                              {/foreach}
                            </select>
                          </div>
                        </div>
                        <div class="supplier-validation mb-3 small text-danger"></div>

                        <input type="hidden" name="xml_documents[{$documentIndex}][xml_filename]" value="{$document.header.xml_filename|escape}">
                        <input type="hidden" name="xml_documents[{$documentIndex}][xml_hash]" value="{$document.header.xml_hash|escape}">
                        <input type="hidden" name="xml_documents[{$documentIndex}][xml_payload_base64]" value="{$document.header.xml_payload_base64|escape}">

                        <div class="alert alert-light border small mb-3">
                          Kolumny pozycji: `Ilosc` to liczba sztuk lub jednostek, `Netto / szt.` i `Brutto / szt.` to cena jednostkowa, `VAT %` przelicza wartosci automatycznie. `Suma netto` i `Suma brutto` pokazuja wartosc calego wiersza.
                          Jesli system oznaczyl dokument jako `korekta`, pokazujemy go tylko do sprawdzenia i nie zapisujemy go z tego ekranu.
                        </div>

                        {foreach $document.lines as $lineIndex => $line}
                          <div class="border rounded p-3 mb-3 aw-line-row{if $line.highlight_unassigned|default:false} bg-warning-subtle{/if}" data-classification-confidence="{$line.classification_confidence|default:'low'|escape}">
                            <div class="fw-semibold mb-2">{$line.original_name|escape}</div>
                            <div class="row g-2 align-items-end">
                              <div class="col-md-4">
                                <label class="form-label">Pozycja ksiegowa</label>
                                <div class="input-group">
                                  <select class="form-select canonical-select item-name-select" name="xml_documents[{$documentIndex}][lines][{$lineIndex}][canonical_name]" data-document-index="{$documentIndex}">
                                    {$itemOptions nofilter}
                                  </select>
                                  <button type="button" class="btn btn-outline-secondary btn-sm refresh-item-names" title="Odswiez pozycje ksiegowe" aria-label="Odswiez pozycje ksiegowe">↻</button>
                                </div>
                              </div>
                              <div class="col-md-2"><label class="form-label">Ilosc</label><input type="number" step="0.001" class="form-control" name="xml_documents[{$documentIndex}][lines][{$lineIndex}][quantity]" value="{$line.quantity|string_format:'%.3f'}"></div>
                              <div class="col-md-2"><label class="form-label">Netto / szt.</label><input type="number" step="0.01" class="form-control line-calc-input" data-field="net" name="xml_documents[{$documentIndex}][lines][{$lineIndex}][unit_net]" value="{$line.unit_net|string_format:'%.2f'}"></div>
                              <div class="col-md-2"><label class="form-label">Brutto / szt.</label><input type="number" step="0.01" class="form-control line-calc-input" data-field="gross" name="xml_documents[{$documentIndex}][lines][{$lineIndex}][unit_gross]" value="{$line.unit_gross|string_format:'%.2f'}"></div>
                              <div class="col-md-2"><label class="form-label">VAT %</label><input type="number" step="0.01" class="form-control line-calc-input" data-field="vat" name="xml_documents[{$documentIndex}][lines][{$lineIndex}][vat_rate]" value="{$line.vat_rate|string_format:'%.2f'}"></div>
                            </div>
                            <div class="row g-2 mt-2">
                              <div class="col-md-2 offset-md-8"><label class="form-label">Suma netto</label><input type="text" class="form-control line-total-net" value="{$line.line_net|string_format:'%.2f'}" readonly></div>
                              <div class="col-md-2"><label class="form-label">Suma brutto</label><input type="text" class="form-control line-total-gross" value="{$line.line_gross|string_format:'%.2f'}" readonly></div>
                            </div>
                            <input type="hidden" name="xml_documents[{$documentIndex}][lines][{$lineIndex}][original_name]" value="{$line.original_name|escape}">
                            <input type="hidden" name="xml_documents[{$documentIndex}][lines][{$lineIndex}][unit]" value="{$line.unit|escape}">
                            <script>document.currentScript.parentElement.querySelector('select[name="xml_documents[{$documentIndex}][lines][{$lineIndex}][canonical_name]"]').value = '{$line.canonical_name|escape:'javascript'}';</script>
                          </div>
                        {/foreach}
                        <div class="border-top pt-3 mt-3">
                          <div class="row g-2 justify-content-end">
                            <div class="col-md-2"><label class="form-label">Suma faktury netto</label><input type="text" class="form-control document-total-net" value="{$document.header.total_net|string_format:'%.2f'}" readonly></div>
                            <div class="col-md-2"><label class="form-label">Suma faktury brutto</label><input type="text" class="form-control document-total-gross" value="{$document.header.total_gross|string_format:'%.2f'}" readonly></div>
                          </div>
                        </div>
                      </div>
                    {/foreach}
                    <button type="submit" class="btn btn-info text-white">Zapisz zwykle dokumenty XML i pomin korekty</button>
                  </form>
                </div>
              </div>
            {/if}
          </div>

          <div class="col-12">
            <div class="card mb-4">
              <div class="card-header"><h3 class="card-title mb-0">Reczna faktura</h3></div>
              <div class="card-body">
                <form method="post" action="{$baseUrl}?controller=accountingwarehouse&action=storemanual" id="manualInvoiceForm" class="supplier-lookup-block">
                  <div class="duplicate-alert mb-3 d-none"><div class="alert alert-danger mb-0"></div></div>
                  <div class="row g-3 mb-3">
                    <div class="col-md-4"><label class="form-label">Numer faktury</label><input type="text" class="form-control document-number-input" name="manual_document_number" value="{$formData.manual_header.document_number|escape}"></div>
                    <div class="col-md-4 position-relative">
                      <label class="form-label">Dostawca</label>
                      <input type="text" class="form-control supplier-name-input" name="manual_supplier_name" id="manualSupplierName" value="{$formData.manual_header.supplier_name|escape}" autocomplete="off">
                      <div id="manualSupplierNameResults" class="list-group position-absolute w-100 shadow-sm supplier-name-results" style="z-index: 20; display: none;"></div>
                    </div>
                    <div class="col-md-4 position-relative">
                      <label class="form-label">NIP</label>
                      <input type="text" class="form-control supplier-tax-id-input" name="manual_supplier_tax_id" id="manualSupplierTaxId" value="{$formData.manual_header.supplier_tax_id|escape}" autocomplete="off">
                      <div id="manualSupplierTaxIdResults" class="list-group position-absolute w-100 shadow-sm supplier-tax-id-results" style="z-index: 20; display: none;"></div>
                    </div>
                    <div class="col-md-3"><label class="form-label">Data wystawienia</label><input type="date" class="form-control" name="manual_issue_date" value="{$formData.manual_header.issue_date|escape}"></div>
                    <div class="col-md-3"><label class="form-label">Data sprzedazy</label><input type="date" class="form-control" name="manual_sale_date" value="{$formData.manual_header.sale_date|escape}"></div>
                    <div class="col-md-3">
                      <label class="form-label">Waluta</label>
                      <select class="form-select" name="manual_currency">
                        {foreach $currencyOptions as $currencyOption}
                          <option value="{$currencyOption|escape}"{if $formData.manual_header.currency eq $currencyOption} selected{/if}>{$currencyOption|escape}</option>
                        {/foreach}
                      </select>
                    </div>
                    <div class="col-md-3"><label class="form-label">Uwagi</label><input type="text" class="form-control" name="manual_notes" value="{$formData.manual_header.notes|escape}"></div>
                  </div>
                  <div class="supplier-validation mb-3 small text-danger"></div>

                  <div class="alert alert-light border small mb-3">
                    Kolumny pozycji: `Ilosc` to liczba sztuk lub jednostek, `Netto / szt.` i `Brutto / szt.` to cena jednostkowa, `VAT %` przelicza wartosci automatycznie. `Suma netto` i `Suma brutto` pokazuja wartosc calego wiersza. Pozycje ksiegowe wybierasz z gotowych pozycji ksiegowych.
                  </div>

                  <div id="manualRows" class="d-grid gap-3">
                    {foreach $formData.manual_lines as $line}
                      <div class="border rounded p-3 aw-line-row">
                        <div class="row g-2 align-items-end">
                          <div class="col-md-4"><label class="form-label">Opis z faktury</label><input type="text" class="form-control" name="manual_original_name[]" value="{$line.original_name|escape}"></div>
                          <div class="col-md-3">
                            <label class="form-label">Pozycja ksiegowa</label>
                            <div class="input-group">
                              <select class="form-select canonical-select item-name-select" name="manual_canonical_name[]">
                                {$itemOptions nofilter}
                              </select>
                              <button type="button" class="btn btn-outline-secondary btn-sm refresh-item-names" title="Odswiez pozycje ksiegowe" aria-label="Odswiez pozycje ksiegowe">↻</button>
                            </div>
                          </div>
                          <div class="col-md-1"><label class="form-label">Ilosc</label><input type="number" step="0.001" class="form-control" name="manual_quantity[]" value="{$line.quantity|string_format:'%.3f'}"></div>
                          <div class="col-md-1"><label class="form-label">Jedn.</label><input type="text" class="form-control" name="manual_unit[]" value="{$line.unit|escape}"></div>
                          <div class="col-md-1"><label class="form-label">Netto / szt.</label><input type="number" step="0.01" class="form-control line-calc-input" data-field="net" name="manual_unit_net[]" value="{$line.unit_net|string_format:'%.2f'}"></div>
                          <div class="col-md-1"><label class="form-label">Brutto / szt.</label><input type="number" step="0.01" class="form-control line-calc-input" data-field="gross" name="manual_unit_gross[]" value="{$line.unit_gross|string_format:'%.2f'}"></div>
                          <div class="col-md-1"><label class="form-label">VAT %</label><input type="number" step="0.01" class="form-control line-calc-input" data-field="vat" name="manual_vat_rate[]" value="{$line.vat_rate|string_format:'%.2f'}"></div>
                        </div>
                        <div class="row g-2 mt-2">
                          <div class="col-md-2 offset-md-8"><label class="form-label">Suma netto</label><input type="text" class="form-control line-total-net" value="{($line.quantity * $line.unit_net)|string_format:'%.2f'}" readonly></div>
                          <div class="col-md-2"><label class="form-label">Suma brutto</label><input type="text" class="form-control line-total-gross" value="{($line.quantity * $line.unit_gross)|string_format:'%.2f'}" readonly></div>
                        </div>
                        <script>document.currentScript.parentElement.querySelector('select[name="manual_canonical_name[]"]').value = '{$line.canonical_name|escape:'javascript'}';</script>
                      </div>
                    {/foreach}
                  </div>
                  <div class="border-top pt-3 mt-3">
                    <div class="row g-2 justify-content-end">
                      <div class="col-md-2"><label class="form-label">Suma faktury netto</label><input type="text" class="form-control document-total-net" value="0.00" readonly></div>
                      <div class="col-md-2"><label class="form-label">Suma faktury brutto</label><input type="text" class="form-control document-total-gross" value="0.00" readonly></div>
                    </div>
                  </div>
                  <button type="button" class="btn btn-outline-secondary mt-3" id="addManualRow">Dodaj pozycje</button>
                  <button type="submit" class="btn btn-success mt-3">Zapisz reczna fakture</button>
                </form>
              </div>
            </div>
          </div>

          <div class="col-12">
            <div class="card mb-4">
              <div class="card-header"><h3 class="card-title mb-0">Korekta stanu</h3></div>
              <div class="card-body">
                <form method="post" action="{$baseUrl}?controller=accountingwarehouse&action=storeadjustment">
                  <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Numer korekty</label><input type="text" class="form-control" name="adjustment_document_number" value="{$defaultAdjustmentNumber|escape}"></div>
                    <div class="col-md-6"><label class="form-label">Data</label><input type="date" class="form-control" name="adjustment_date" value="{$todayDate|escape}"></div>
                    <div class="col-md-6"><label class="form-label">Opis zrodla</label><input type="text" class="form-control" name="adjustment_original_name"></div>
                    <div class="col-md-6">
                      <label class="form-label">Pozycja ksiegowa</label>
                      <div class="input-group">
                        <select class="form-select item-name-select" name="adjustment_canonical_name">
                          <option value="">Wybierz pozycje ksiegowa</option>
                          {$itemOptions nofilter}
                        </select>
                        <button type="button" class="btn btn-outline-secondary btn-sm refresh-item-names" title="Odswiez pozycje ksiegowe" aria-label="Odswiez pozycje ksiegowe">↻</button>
                      </div>
                    </div>
                    <div class="col-md-3"><label class="form-label">Zmiana ilosci</label><input type="number" step="0.001" class="form-control" name="adjustment_quantity"></div>
                    <div class="col-md-2"><label class="form-label">Jedn.</label><input type="text" class="form-control" name="adjustment_unit" value="szt."></div>
                    <div class="col-md-2"><label class="form-label">VAT %</label><input type="number" step="0.01" class="form-control line-calc-input" data-field="vat" name="adjustment_vat_rate" value="23.00"></div>
                    <div class="col-md-2"><label class="form-label">Netto / szt.</label><input type="number" step="0.01" class="form-control line-calc-input" data-field="net" name="adjustment_unit_net" value="0.00"></div>
                    <div class="col-md-3"><label class="form-label">Brutto / szt.</label><input type="number" step="0.01" class="form-control line-calc-input" data-field="gross" name="adjustment_unit_gross" value="0.00"></div>
                    <div class="col-12"><label class="form-label">Uwagi</label><input type="text" class="form-control" name="adjustment_notes"></div>
                  </div>
                  <button type="submit" class="btn btn-warning mt-3">Zapisz korekte</button>
                </form>
              </div>
            </div>
          </div>

        </div>
      {/if}
    </div>
  </div>

  <template id="rowTemplate">
    <div class="border rounded p-3 aw-line-row">
      <div class="row g-2 align-items-end">
        <div class="col-md-4"><label class="form-label">Opis z faktury</label><input type="text" class="form-control js-original-name"></div>
        <div class="col-md-3">
          <label class="form-label">Pozycja ksiegowa</label>
          <div class="input-group">
            <select class="form-select canonical-select item-name-select js-canonical-name">{$itemOptions nofilter}</select>
            <button type="button" class="btn btn-outline-secondary btn-sm refresh-item-names" title="Odswiez pozycje ksiegowe" aria-label="Odswiez pozycje ksiegowe">↻</button>
          </div>
        </div>
        <div class="col-md-1"><label class="form-label">Ilosc</label><input type="number" step="0.001" class="form-control js-quantity" value="1.000"></div>
        <div class="col-md-1"><label class="form-label">Jedn.</label><input type="text" class="form-control js-unit" value="szt."></div>
        <div class="col-md-1"><label class="form-label">Netto / szt.</label><input type="number" step="0.01" class="form-control line-calc-input js-unit-net" data-field="net" value="0.00"></div>
        <div class="col-md-1"><label class="form-label">Brutto / szt.</label><input type="number" step="0.01" class="form-control line-calc-input js-unit-gross" data-field="gross" value="0.00"></div>
        <div class="col-md-1"><label class="form-label">VAT %</label><input type="number" step="0.01" class="form-control line-calc-input js-vat-rate" data-field="vat" value="23.00"></div>
      </div>
      <div class="row g-2 mt-2">
        <div class="col-md-2 offset-md-8"><label class="form-label">Suma netto</label><input type="text" class="form-control line-total-net" value="0.00" readonly></div>
        <div class="col-md-2"><label class="form-label">Suma brutto</label><input type="text" class="form-control line-total-gross" value="0.00" readonly></div>
      </div>
    </div>
  </template>

  <script>
    (function () {
      var baseUrl = '{$baseUrl|escape:'javascript'}';
      var itemOptionsHtml = {$itemOptions|json_encode nofilter};

      function toDecimal(value) {
        var normalized = String(value || '').replace(',', '.').trim();
        var parsed = parseFloat(normalized);
        return Number.isFinite(parsed) ? parsed : 0;
      }

      function formatDecimal(value, digits) {
        var factor = Math.pow(10, digits);
        return (Math.round(value * factor) / factor).toFixed(digits);
      }

      function syncLineValues(row, changedField) {
        if (!row) {
          return;
        }

        var netInput = row.querySelector('[data-field="net"]');
        var grossInput = row.querySelector('[data-field="gross"]');
        var vatInput = row.querySelector('[data-field="vat"]');
        if (!netInput || !grossInput || !vatInput) {
          return;
        }

        var vat = toDecimal(vatInput.value);
        var multiplier = 1 + (vat / 100);
        var net = toDecimal(netInput.value);
        var gross = toDecimal(grossInput.value);

        if (changedField === 'net') {
          grossInput.value = formatDecimal(net * multiplier, 2);
          return;
        }

        if (changedField === 'gross') {
          netInput.value = multiplier !== 0 ? formatDecimal(gross / multiplier, 2) : formatDecimal(gross, 2);
          return;
        }

        if (changedField === 'vat') {
          if (net !== 0) {
            grossInput.value = formatDecimal(net * multiplier, 2);
          } else if (gross !== 0) {
            netInput.value = multiplier !== 0 ? formatDecimal(gross / multiplier, 2) : formatDecimal(gross, 2);
          }
        }

        updateLineTotals(row);
        updateDocumentTotals(row.closest('.xml-document-block') || row.closest('form'));
      }

      function ensureLineValues(row) {
        if (!row) {
          return;
        }

        var netInput = row.querySelector('[data-field="net"]');
        var grossInput = row.querySelector('[data-field="gross"]');
        var vatInput = row.querySelector('[data-field="vat"]');
        if (!netInput || !grossInput || !vatInput) {
          return;
        }

        var net = toDecimal(netInput.value);
        var gross = toDecimal(grossInput.value);
        var vat = toDecimal(vatInput.value);
        var multiplier = 1 + (vat / 100);

        if (net === 0 && gross !== 0) {
          netInput.value = multiplier !== 0 ? formatDecimal(gross / multiplier, 2) : formatDecimal(gross, 2);
        } else if (gross === 0 && net !== 0) {
          grossInput.value = formatDecimal(net * multiplier, 2);
        }

        updateLineTotals(row);
      }

      function updateLineTotals(row) {
        if (!row) {
          return;
        }

        var quantityInput = row.querySelector('input[name*="quantity"], .js-quantity');
        var netInput = row.querySelector('[data-field="net"]');
        var grossInput = row.querySelector('[data-field="gross"]');
        var totalNetInput = row.querySelector('.line-total-net');
        var totalGrossInput = row.querySelector('.line-total-gross');
        if (!quantityInput || !netInput || !grossInput || !totalNetInput || !totalGrossInput) {
          return;
        }

        var quantity = toDecimal(quantityInput.value);
        var unitNet = toDecimal(netInput.value);
        var unitGross = toDecimal(grossInput.value);
        totalNetInput.value = formatDecimal(quantity * unitNet, 2);
        totalGrossInput.value = formatDecimal(quantity * unitGross, 2);
      }

      function updateDocumentTotals(scope) {
        if (!scope) {
          return;
        }

        var rows = scope.querySelectorAll('.aw-line-row');
        var totalNet = 0;
        var totalGross = 0;
        for (var index = 0; index < rows.length; index++) {
          var lineNet = rows[index].querySelector('.line-total-net');
          var lineGross = rows[index].querySelector('.line-total-gross');
          totalNet += toDecimal(lineNet ? lineNet.value : 0);
          totalGross += toDecimal(lineGross ? lineGross.value : 0);
        }

        var totalNetField = scope.querySelector('.document-total-net');
        var totalGrossField = scope.querySelector('.document-total-gross');
        if (totalNetField) {
          totalNetField.value = formatDecimal(totalNet, 2);
        }
        if (totalGrossField) {
          totalGrossField.value = formatDecimal(totalGross, 2);
        }
      }

      document.addEventListener('input', function (event) {
        if (event.target && event.target.classList && event.target.classList.contains('line-calc-input')) {
          syncLineValues(event.target.closest('.aw-line-row'), event.target.getAttribute('data-field'));
        }
        if (event.target && event.target.name && event.target.name.indexOf('quantity') !== -1) {
          var quantityRow = event.target.closest('.aw-line-row');
          updateLineTotals(quantityRow);
          updateDocumentTotals(quantityRow.closest('.xml-document-block') || quantityRow.closest('form'));
        }
      });

      function duplicateRow(containerId, buttonId, prefix) {
        var wrap = document.getElementById(containerId);
        var button = document.getElementById(buttonId);
        var template = document.getElementById('rowTemplate');
        if (!wrap || !button || !template) {
          return;
        }

        function nextIndex() {
          return wrap.querySelectorAll('.aw-line-row').length;
        }

        button.addEventListener('click', function () {
          var index = nextIndex();
          var clone = template.content.firstElementChild.cloneNode(true);
          clone.querySelector('.js-original-name').name = prefix + 'original_name[]';
          clone.querySelector('.js-canonical-name').name = prefix + 'canonical_name[]';
          clone.querySelector('.js-quantity').name = prefix + 'quantity[]';
          clone.querySelector('.js-unit').name = prefix + 'unit[]';
          clone.querySelector('.js-unit-net').name = prefix + 'unit_net[]';
          clone.querySelector('.js-unit-gross').name = prefix + 'unit_gross[]';
          clone.querySelector('.js-vat-rate').name = prefix + 'vat_rate[]';
          var canonicalSelect = clone.querySelector('.js-canonical-name');
          if (canonicalSelect) {
            canonicalSelect.value = 'pozostale';
          }
          wrap.appendChild(clone);
          ensureLineValues(clone);
          updateDocumentTotals(wrap.closest('form'));
        });
      }

      duplicateRow('manualRows', 'addManualRow', 'manual_');
      duplicateRow('editRows', 'addEditRow', 'edit_');


      function normalized(value) {
        return String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
      }

      function bindSupplierBlock(root) {
        if (!root) {
          return;
        }

        var nameInput = root.querySelector('.supplier-name-input');
        var taxIdInput = root.querySelector('.supplier-tax-id-input');
        var nameResults = root.querySelector('.supplier-name-results');
        var taxResults = root.querySelector('.supplier-tax-id-results');
        var validation = root.querySelector('.supplier-validation');
        var duplicateAlert = root.querySelector('.duplicate-alert');
        var documentNumberInput = root.querySelector('.document-number-input');
        var xmlHash = root.getAttribute('data-xml-hash') || '';
        var lookupEndpoint = baseUrl + '?controller=accountingwarehouse&action=supplierlookup';
        var resolveEndpoint = baseUrl + '?controller=accountingwarehouse&action=supplierresolve';
        var duplicateEndpoint = baseUrl + '?controller=accountingwarehouse&action=documentduplicatecheck';
        var timer = null;
        var duplicateTimer = null;

        if (!nameInput || !taxIdInput) {
          return;
        }

        function showValidation(message, isError) {
          if (!validation) {
            return;
          }

          validation.textContent = message || '';
          validation.classList.toggle('text-danger', !!isError);
          validation.classList.toggle('text-success', !isError && message !== '');
        }

        function hideResults(results) {
          if (!results) {
            return;
          }

          results.style.display = 'none';
          results.innerHTML = '';
        }

        function choose(item) {
          nameInput.value = item.supplier_name || '';
          taxIdInput.value = item.supplier_tax_id || '';
          nameInput.classList.remove('is-invalid');
          taxIdInput.classList.remove('is-invalid');
          hideResults(nameResults);
          hideResults(taxResults);
          showValidation(item.source === 'official' ? 'Dostawca uzupelniony automatycznie po NIP z oficjalnego wykazu MF.' : '', false);
          checkDuplicate();
        }

        function renderResults(results, items) {
          if (!results) {
            return;
          }

          if (!items || items.length === 0) {
            hideResults(results);
            return;
          }

          var html = '';
          for (var index = 0; index < items.length; index++) {
            var item = items[index];
            html += '<button type="button" class="list-group-item list-group-item-action supplier-result-item"'
              + ' data-name="' + String(item.supplier_name || '').replace(/"/g, '&quot;') + '"'
              + ' data-tax-id="' + String(item.supplier_tax_id || '').replace(/"/g, '&quot;') + '">'
              + '<div class="fw-semibold">' + String(item.supplier_name || '') + '</div>'
              + '<div class="small text-secondary">NIP: ' + String(item.supplier_tax_id || '-') + '</div>'
              + '</button>';
          }

          results.innerHTML = html;
          results.style.display = 'block';
        }

        function maybeAutofill(query, items, mode) {
          if (!items || items.length === 0) {
            return false;
          }

          if (items.length === 1) {
            choose(items[0]);
            return true;
          }

          for (var index = 0; index < items.length; index++) {
            var item = items[index];
            if (mode === 'tax_id' && normalized(item.supplier_tax_id) === normalized(query)) {
              choose(item);
              return true;
            }

            if (mode === 'name' && normalized(item.supplier_name) === normalized(query)) {
              choose(item);
              return true;
            }
          }

          return false;
        }

        function validateSupplierState() {
          var nameValue = nameInput.value.trim();
          var taxValue = taxIdInput.value.replace(/\D+/g, '');
          if (nameValue !== '' && taxValue === '') {
            nameInput.classList.add('is-invalid');
            showValidation('Nie znaleziono dostawcy w bazie. Uzupelnij NIP, a system sprobuje pobrac firme automatycznie.', true);
            return;
          }

          nameInput.classList.remove('is-invalid');
          taxIdInput.classList.remove('is-invalid');
          if (validation && validation.classList.contains('text-danger')) {
            showValidation('', false);
          }
        }

        function resolveOfficialSupplier() {
          var taxValue = taxIdInput.value.replace(/\D+/g, '');
          if (taxValue.length !== 10) {
            return;
          }

          fetch(resolveEndpoint + '&tax_id=' + encodeURIComponent(taxValue) + '&supplier_name=' + encodeURIComponent(nameInput.value.trim()), {
            headers: { 'Accept': 'application/json' }
          })
            .then(function (response) { return response.ok ? response.json() : { item: null }; })
            .then(function (payload) {
              if (payload.item) {
                choose(payload.item);
                return;
              }

              if (nameInput.value.trim() !== '') {
                showValidation('Nie znaleziono firmy po tym NIP-ie. Sprawdz numer lub wpisz poprawna nazwe dostawcy.', true);
              }
              taxIdInput.classList.add('is-invalid');
            })
            .catch(function () {});
        }

        function searchLocal(mode, query, results) {
          fetch(lookupEndpoint + '&mode=' + encodeURIComponent(mode) + '&q=' + encodeURIComponent(query), {
            headers: { 'Accept': 'application/json' }
          })
            .then(function (response) { return response.ok ? response.json() : { items: [] }; })
            .then(function (payload) {
              var items = payload.items || [];
              var autoFilled = maybeAutofill(query, items, mode);
              if (mode === 'name' && items.length === 0) {
                validateSupplierState();
              }

              if (!autoFilled) {
                renderResults(results, items);
              }
            })
            .catch(function () {
              hideResults(results);
            });
        }

        function checkDuplicate() {
          if (!duplicateAlert || !documentNumberInput) {
            return;
          }

          var documentNumber = documentNumberInput.value.trim();
          var supplierName = nameInput.value.trim();
          var supplierTaxId = taxIdInput.value.trim();
          if (documentNumber === '' || (supplierName === '' && supplierTaxId === '') && xmlHash === '') {
            duplicateAlert.classList.add('d-none');
            return;
          }

          if (duplicateTimer) {
            clearTimeout(duplicateTimer);
          }

          duplicateTimer = setTimeout(function () {
            fetch(
              duplicateEndpoint
                + '&document_number=' + encodeURIComponent(documentNumber)
                + '&supplier_name=' + encodeURIComponent(supplierName)
                + '&supplier_tax_id=' + encodeURIComponent(supplierTaxId)
                + '&xml_hash=' + encodeURIComponent(xmlHash),
              { headers: { 'Accept': 'application/json' } }
            )
              .then(function (response) { return response.ok ? response.json() : { duplicate: null }; })
              .then(function (payload) {
                var duplicate = payload.duplicate || null;
                var box = duplicateAlert.querySelector('.alert');
                if (!box) {
                  return;
                }

                if (!duplicate) {
                  duplicateAlert.classList.add('d-none');
                  box.textContent = '';
                  return;
                }

                box.textContent = 'Duplikat dokumentu. Istnieje juz dokument #' + duplicate.id + ' (' + (duplicate.document_number || 'bez numeru') + ').';
                duplicateAlert.classList.remove('d-none');
              })
              .catch(function () {});
          }, 220);
        }

        nameInput.addEventListener('input', function () {
          var query = nameInput.value.trim();
          if (timer) {
            clearTimeout(timer);
          }

          timer = setTimeout(function () {
            if (query.length >= 2) {
              searchLocal('name', query, nameResults);
            } else {
              hideResults(nameResults);
            }
            validateSupplierState();
            checkDuplicate();
          }, 180);
        });

        taxIdInput.addEventListener('input', function () {
          var query = taxIdInput.value.replace(/\D+/g, '');
          if (timer) {
            clearTimeout(timer);
          }

          timer = setTimeout(function () {
            if (query.length >= 2) {
              searchLocal('tax_id', query, taxResults);
            } else {
              hideResults(taxResults);
            }

            if (query.length === 10) {
              resolveOfficialSupplier();
            }
            checkDuplicate();
          }, 180);
        });

        function bindResults(results) {
          if (!results) {
            return;
          }

          results.addEventListener('click', function (event) {
            var button = event.target.closest('.supplier-result-item');
            if (!button) {
              return;
            }

            choose({
              supplier_name: button.getAttribute('data-name') || '',
              supplier_tax_id: button.getAttribute('data-tax-id') || '',
              source: 'local'
            });
          });
        }

        bindResults(nameResults);
        bindResults(taxResults);

        document.addEventListener('click', function (event) {
          if (nameResults && !nameResults.contains(event.target) && event.target !== nameInput) {
            hideResults(nameResults);
          }
          if (taxResults && !taxResults.contains(event.target) && event.target !== taxIdInput) {
            hideResults(taxResults);
          }
        });

        if (documentNumberInput) {
          documentNumberInput.addEventListener('input', checkDuplicate);
        }

        validateSupplierState();
        checkDuplicate();
      }

      var supplierBlocks = document.querySelectorAll('.supplier-lookup-block');
      for (var supplierIndex = 0; supplierIndex < supplierBlocks.length; supplierIndex++) {
        bindSupplierBlock(supplierBlocks[supplierIndex]);
      }

      (function () {
        var uploadForm = document.getElementById('xmlUploadForm');
        var uploadInput = document.getElementById('xmlUploadInput');
        var progressBox = document.getElementById('xmlBatchProgress');
        if (!uploadForm || !uploadInput || !progressBox) {
          return;
        }

        function setProgress(message, isError) {
          progressBox.textContent = message || '';
          progressBox.classList.remove('d-none', 'alert-info', 'alert-danger', 'alert-success');
          progressBox.classList.add(isError ? 'alert-danger' : 'alert-info');
        }

        uploadForm.addEventListener('submit', function (event) {
          var files = uploadInput.files ? Array.prototype.slice.call(uploadInput.files) : [];
          if (files.length <= 20) {
            return;
          }

          event.preventDefault();

          var chunkSize = 20;
          var endpoint = uploadForm.getAttribute('action') || (baseUrl + '?controller=accountingwarehouse&action=previewxml');
          var chunks = [];
          for (var index = 0; index < files.length; index += chunkSize) {
            chunks.push(files.slice(index, index + chunkSize));
          }

          var currentChunk = 0;

          function uploadNextChunk() {
            if (currentChunk >= chunks.length) {
              progressBox.classList.remove('alert-info', 'alert-danger');
              progressBox.classList.add('alert-success');
              progressBox.textContent = 'Wczytano ' + files.length + ' plikow XML. Otwieram podglad.';
                              window.location.href = baseUrl + '?controller=accountingwarehouse&action=create&show_preview=1';
                              return;
                            }

            var formData = new FormData();
            formData.append('batch_mode', '1');
            formData.append('clear_stored_preview', currentChunk === 0 ? '1' : '0');
            for (var fileIndex = 0; fileIndex < chunks[currentChunk].length; fileIndex++) {
              formData.append('invoice_xml[]', chunks[currentChunk][fileIndex], chunks[currentChunk][fileIndex].name);
            }

            setProgress('Wczytywanie partii ' + (currentChunk + 1) + ' z ' + chunks.length + '...', false);
            fetch(endpoint, {
              method: 'POST',
              body: formData,
              headers: { 'Accept': 'application/json' }
            })
              .then(function (response) {
                if (!response.ok) {
                  return response.json().then(function (payload) {
                    throw new Error(payload.error || 'Nie udalo sie wczytac partii XML.');
                  });
                }

                return response.json();
              })
              .then(function () {
                currentChunk += 1;
                uploadNextChunk();
              })
              .catch(function (error) {
                setProgress(error && error.message ? error.message : 'Nie udalo sie wczytac partii XML.', true);
              });
          }

          uploadNextChunk();
        });
      })();

      var allRows = document.querySelectorAll('.aw-line-row');
      for (var rowIndex = 0; rowIndex < allRows.length; rowIndex++) {
        ensureLineValues(allRows[rowIndex]);
        updateAssignmentHighlight(allRows[rowIndex]);
      }

      var xmlDocuments = document.querySelectorAll('.xml-document-block');
      for (var documentIndex = 0; documentIndex < xmlDocuments.length; documentIndex++) {
        updateDocumentTotals(xmlDocuments[documentIndex]);
      }

      var manualInvoiceForm = document.getElementById('manualInvoiceForm');
      if (manualInvoiceForm) {
        updateDocumentTotals(manualInvoiceForm);
      }

      var editRows = document.getElementById('editRows');
      if (editRows) {
        updateDocumentTotals(editRows.closest('form'));
      }

      (function () {
        var itemNamesEndpoint = baseUrl + '?controller=accountingwarehouse&action=itemnames';

        function escapeHtml(value) {
          return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
        }

        function refreshItemSelect(select) {
          if (!select) {
            return;
          }

          var button = select.parentElement ? select.parentElement.querySelector('.refresh-item-names') : null;
          var currentValue = select.value;
          var row = select.closest('.aw-line-row');
          var sourceNameNode = row ? row.querySelector('.fw-semibold, input[name*="original_name"], input[name$="original_name[]"], .js-original-name') : null;
          var sourceName = '';
          if (sourceNameNode) {
            sourceName = sourceNameNode.value !== undefined ? sourceNameNode.value : sourceNameNode.textContent;
          }
          if (button) {
            button.disabled = true;
            button.textContent = '...';
          }

          fetch(itemNamesEndpoint + '&source_name=' + encodeURIComponent(String(sourceName || '').trim()), {
            headers: { 'Accept': 'application/json' }
          })
            .then(function (response) { return response.ok ? response.json() : { items: [] }; })
            .then(function (payload) {
              var items = Array.isArray(payload.items) ? payload.items : [];
              var options = '';
              for (var index = 0; index < items.length; index++) {
                options += '<option value="' + escapeHtml(items[index]) + '">' + escapeHtml(items[index]) + '</option>';
              }
              select.innerHTML = options;
              var suggestion = String(payload.suggestion || '').trim();
              if (suggestion !== '' && items.indexOf(suggestion) !== -1) {
                select.value = suggestion;
              } else {
                select.value = currentValue;
              }

              if (row) {
                updateAssignmentHighlight(row, String(payload.confidence || 'low'));
              }
            })
            .catch(function () {})
            .finally(function () {
              if (button) {
                button.disabled = false;
                button.textContent = '↻';
              }
            });
        }

        function updateAssignmentHighlight(row, confidenceOverride) {
          if (!row) {
            return;
          }

          var select = row.querySelector('.item-name-select');
          if (!select) {
            return;
          }

          var selectedValue = String(select.value || '').trim();
          var hasMatchingOption = false;
          for (var optionIndex = 0; optionIndex < select.options.length; optionIndex++) {
            if (String(select.options[optionIndex].value || '').trim() === selectedValue && selectedValue !== '') {
              hasMatchingOption = true;
              break;
            }
          }

          var confidence = String(confidenceOverride || row.getAttribute('data-classification-confidence') || 'low');
          var shouldHighlight = selectedValue === '' || selectedValue === 'pozostale' || !hasMatchingOption || confidence === 'low';
          row.classList.toggle('bg-warning-subtle', shouldHighlight);
        }

        document.addEventListener('click', function (event) {
          var button = event.target.closest('.refresh-item-names');
          if (!button) {
            return;
          }

          var select = button.parentElement ? button.parentElement.querySelector('.item-name-select') : null;
          refreshItemSelect(select);
        });
      })();
    })();
  </script>
</main>
