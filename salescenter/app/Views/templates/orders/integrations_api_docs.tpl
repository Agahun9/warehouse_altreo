<details class="sc-api-docs">
  <summary><i class="bi bi-book"></i> Dokumentacja API dla programisty</summary>
  <div class="sc-api-body">
    <p>Wszystkie żądania: nagłówek <code>Authorization: Bearer TOKEN</code>, format JSON (UTF-8). Kwoty brutto jako tekst z kropką, daty w ISO 8601.</p>
    <table class="sc-api-table">
      <tr><td><span class="sc-method post">POST</span></td><td><code>/v1/orders</code></td><td>Dodaje lub aktualizuje zamówienie (albo <code>{literal}{"orders":[…]}{/literal}</code>, maks. 100). Ten sam <code>id</code> = aktualizacja, bez duplikatu.</td></tr>
      <tr><td><span class="sc-method get">GET</span></td><td><code>/v1/orders/{literal}{id}{/literal}</code></td><td>Status w SalesCenter, płatność, numery przesyłek i dokumentów.</td></tr>
      <tr><td><span class="sc-method get">GET</span></td><td><code>/v1/orders?updated_since=2026-09-01T00:00:00Z</code></td><td>Zamówienia zmienione od daty (maks. 100) – do aktualizacji statusów w sklepie.</td></tr>
      <tr><td><span class="sc-method get">GET</span></td><td><code>/v1/statuses</code></td><td>Lista statusów firmy.</td></tr>
      <tr><td><span class="sc-method get">GET</span></td><td><code>/v1/ping</code></td><td>Test tokenu.</td></tr>
    </table>
    <p><strong>Przykład – nowe zamówienie</strong> (wymagane: <code>id</code>, <code>created_at</code>, <code>items</code>):</p>
<pre class="sc-code">{literal}curl -X POST "{/literal}{$integrations.apiBase|escape}{literal}/orders" \
  -H "Authorization: Bearer TOKEN" -H "Content-Type: application/json" \
  -d '{
  "id": "10025",
  "created_at": "2026-09-17T10:15:00Z",
  "status": "nowe",
  "currency": "PLN",
  "total": "141.97",
  "paid": true,
  "payment_method": "Przelewy24",
  "cash_on_delivery": false,
  "shipping": { "method": "InPost Paczkomat", "price": "12.99", "pickup_point": "WAW01M" },
  "customer": { "email": "anna@example.com", "phone": "+48500600700", "note": "Proszę o fakturę" },
  "shipping_address": { "first_name": "Anna", "last_name": "Kowalska", "company": "",
    "street": "Prosta", "building": "12/3", "postal_code": "00-001", "city": "Warszawa", "country": "PL", "phone": "+48500600700" },
  "invoice": { "required": true, "company": "Firma Sp. z o.o.", "nip": "5252674798",
    "street": "Prosta", "building": "12", "postal_code": "00-001", "city": "Warszawa", "country": "PL" },
  "items": [
    { "name": "Kubek ceramiczny", "sku": "KUB-01", "quantity": 2, "price": "64.49", "vat": "23",
      "image_url": "https://mojsklep.pl/img/kubek.jpg" }
  ]
}'{/literal}</pre>
    <p><strong>Odpowiedź:</strong> <code>{literal}{"results":[{"id":"10025","local_id":57,"created":true}]}{/literal}</code> · błędy walidacji: HTTP 422 z polem <code>error</code>.</p>
    <p>Pola opcjonalne: <code>total</code> (gdy brak – suma pozycji i dostawy), <code>status</code>, <code>paid</code>, <code>vat</code> (23, 8, 5, 0, zw, np), <code>image_url</code>, <code>invoice</code>.</p>
  </div>
</details>
