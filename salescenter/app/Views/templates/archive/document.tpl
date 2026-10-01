<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{$kindLabel|escape} {$document.number|escape}</title><style>{literal}
*{box-sizing:border-box}body{margin:0;background:#e8ecf1;color:#1a1a1a;font:12px Arial,Helvetica,sans-serif}
.toolbar{max-width:210mm;margin:18px auto;display:flex;justify-content:space-between;gap:12px;align-items:center}
.toolbar a,.toolbar button{padding:9px 14px;border:0;border-radius:6px;background:#2f3a4f;color:#fff;font:600 12px Arial;text-decoration:none;cursor:pointer}
.toolbar span{color:#5b6475}
.page{width:210mm;min-height:297mm;margin:0 auto 24px;background:#fff;padding:16mm 14mm;box-shadow:0 6px 24px #0002}
h1{font-size:20px;margin:0 0 4px}.muted{color:#6b7280}.stamp{display:inline-block;margin-top:6px;padding:3px 8px;border:1px solid #c7ccd6;border-radius:4px;font-size:10px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em}
.head{display:flex;justify-content:space-between;gap:20px;border-bottom:2px solid #1f2937;padding-bottom:10px;margin-bottom:14px}
.dates{text-align:right;line-height:1.6}
.parties{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:16px}
.parties h2{font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;margin:0 0 4px}
.parties div{line-height:1.5}
table{width:100%;border-collapse:collapse;margin-bottom:12px}th,td{border:1px solid #d4d8e0;padding:5px 6px;vertical-align:top}th{background:#f3f4f6;font-size:10px;text-transform:uppercase;letter-spacing:.03em}
.r{text-align:right;white-space:nowrap}
.totals{width:55%;margin-left:auto}.total{font-size:16px;font-weight:700;text-align:right;margin:8px 0 14px}
.meta{display:grid;grid-template-columns:max-content 1fr;gap:3px 12px;margin-top:10px}
details{margin-top:18px}pre{white-space:pre-wrap;word-break:break-word;font-size:10px;background:#f6f7f9;padding:8px}
@media screen{
body{background:#f3f5fa;color:#263548;line-height:1.6}
.toolbar{max-width:210mm;padding:0 4px;flex-wrap:wrap;font-size:12px}
.toolbar>span:last-child{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.toolbar a,.toolbar button{display:inline-flex;align-items:center;justify-content:center;min-height:38px;border-radius:8px;background:#5954e8;font:600 12px Arial;line-height:1.4}
.toolbar a{background:#fff;color:#4f46e5;border:1px solid #dce3ed}
.toolbar a:hover,.toolbar button:hover{filter:brightness(.95)}
.toolbar :is(a,button):focus-visible,summary:focus-visible{outline:3px solid #a5a0f4;outline-offset:3px}
.page{max-width:calc(100% - 24px);border:1px solid #dfe6ef;border-radius:12px;box-shadow:0 6px 24px #233b5a0a}
h1{font-size:24px;line-height:1.35;overflow-wrap:anywhere}
.head{border-bottom:2px solid #5954e8;padding-bottom:18px;margin-bottom:24px}
.stamp{background:#eeedff;color:#5954e8;border-color:#dddafa;padding:4px 9px;border-radius:6px}
.parties>div{padding:16px;background:#f8faff;border:1px solid #e3e9f2;border-radius:8px;overflow-wrap:anywhere}
.parties h2{color:#64748b;margin-bottom:8px}
.table-scroll{max-width:100%;overflow-x:auto}
th{background:#f5f7fc;color:#64748b}th,td{padding:8px;border-color:#e1e7ef}
.total{padding:14px 16px;background:#eeedff;color:#4338ca;border-radius:8px}
summary{cursor:pointer;color:#64748b;padding:10px 0}
pre{max-height:420px;overflow:auto;background:#0f172a;color:#e2e8f0;padding:14px;border-radius:8px}
}
@media screen and (max-width:700px){
.toolbar{margin:14px 12px;gap:10px}
.page{width:auto;min-height:0;padding:24px 18px;margin-bottom:16px}
.head{flex-direction:column;gap:12px}.dates{text-align:left}
.parties{grid-template-columns:1fr;gap:12px}
.table-scroll table{min-width:640px}.totals{width:100%}
.meta{grid-template-columns:minmax(0,1fr) minmax(0,1.5fr);overflow-wrap:anywhere}
}
@media print{body{background:#fff}.toolbar,details{display:none}.page{box-shadow:none;margin:0;width:auto;min-height:0;padding:0}}
{/literal}</style></head><body>
<div class="toolbar"><span>Archiwum Sellasist · dokument tylko do podglądu</span><span>{if $document.archive_order_id}<a href="index.php?controller=archive&id={$document.archive_order_id}">Zamówienie #{$document.order_remote_id}</a> {/if}<button type="button" onclick="window.print()">Drukuj / PDF</button></span></div>
<div class="page">
  <div class="head">
    <div><h1>{$kindLabel|escape} {$document.number|default:"#`$document.remote_id`"|escape}</h1>
      {if $document.related_number}<div class="muted">Dotyczy: {$document.related_number|escape}</div>{/if}
      <span class="stamp">Kopia z archiwum Sellasist</span></div>
    <div class="dates">
      {if $detail.issue_date|default:''}Data wystawienia: <strong>{$detail.issue_date|escape}</strong><br>{elseif $document.issue_date}Data: <strong>{$document.issue_date|escape}</strong><br>{/if}
      {if $detail.sale_date|default:''}Data sprzedaży: <strong>{$detail.sale_date|escape}</strong><br>{/if}
      {if $document.order_remote_id}Zamówienie Sellasist: <strong>#{$document.order_remote_id}</strong>{/if}
    </div>
  </div>
  {if $document.detail_state neq 1}<p class="muted">Szczegóły tego dokumentu nie zostały jeszcze pobrane z Sellasist{if $document.detail_state eq 2} (błąd pobierania){/if}. Widoczne są tylko dane z listy.</p>{/if}
  <div class="parties">
    <div><h2>Sprzedawca</h2>{if $seller}{foreach ['name','address','postcode','city','nip','email','phone'] as $k}{if $seller[$k]|default:'' neq ''}{if $k eq 'nip'}NIP: {/if}{$seller[$k]|escape}<br>{/if}{/foreach}{else}<span class="muted">—</span>{/if}</div>
    <div><h2>Nabywca</h2>{if $buyer}{foreach ['name','address','postcode','city','country','nip','email','phone'] as $k}{if $buyer[$k]|default:'' neq ''}{if $k eq 'nip'}NIP: {/if}{$buyer[$k]|escape}<br>{/if}{/foreach}{else}{$document.buyer_name|default:'—'|escape}{/if}</div>
  </div>
  {if $lines}
  <div class="table-scroll"><table><thead><tr><th>#</th><th>Nazwa</th><th class="r">Ilość</th><th class="r">Cena netto</th><th class="r">Cena brutto</th><th class="r">VAT</th><th class="r">Wartość netto</th><th class="r">Wartość brutto</th></tr></thead><tbody>
    {foreach $lines as $l}<tr><td>{$l@iteration}</td><td>{$l.name|escape}{if $l.discount neq '' && $l.discount neq 0}<br><span class="muted">rabat: {$l.discount|escape}</span>{/if}</td><td class="r">{$l.quantity}</td><td class="r">{if $l.price_net !== null}{$l.price_net|string_format:'%.2f'}{else}—{/if}</td><td class="r">{$l.price_gross|string_format:'%.2f'}</td><td class="r">{$l.vat|escape}{if $l.vat neq ''}%{/if}</td><td class="r">{$l.net|string_format:'%.2f'}</td><td class="r">{$l.gross|string_format:'%.2f'}</td></tr>{/foreach}
  </tbody></table></div>
  <table class="totals"><thead><tr><th>Stawka VAT</th><th class="r">Netto</th><th class="r">VAT</th><th class="r">Brutto</th></tr></thead><tbody>
    {foreach $vatSummary as $v}<tr><td>{$v.vat|escape}{if $v.vat neq ''}%{/if}</td><td class="r">{$v.net|string_format:'%.2f'}</td><td class="r">{($v.gross-$v.net)|string_format:'%.2f'}</td><td class="r">{$v.gross|string_format:'%.2f'}</td></tr>{/foreach}
  </tbody></table>
  {/if}
  <div class="total">Razem: {($document.total_cents/100)|string_format:'%.2f'} {$document.currency|escape}</div>
  <div class="meta">
    {if $detail.payment_method|default:''}<span class="muted">Sposób płatności</span><span>{$detail.payment_method|escape}</span>{/if}
    {if isset($detail.paid)}<span class="muted">Zapłacono</span><span>{$detail.paid|escape}</span>{/if}
    {if $detail.package_number|default:''}<span class="muted">Nr przesyłki</span><span>{$detail.package_number|escape}</span>{/if}
    {if $detail.exchange|default:''}<span class="muted">Kurs</span><span>{$detail.exchange|escape}{if $detail.exchange_date|default:''} z dnia {$detail.exchange_date|escape}{/if}</span>{/if}
    {if $detail.bdo_number|default:''}<span class="muted">BDO</span><span>{$detail.bdo_number|escape}</span>{/if}
  </div>
  <details><summary>Pełne dane z API Sellasist (JSON)</summary><pre>{$raw|escape}</pre></details>
</div>
</body></html>
