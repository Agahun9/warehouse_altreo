<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <title>{$pageTitle|escape}</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 24px;
      color: #111827;
    }

    .summary {
      margin-bottom: 20px;
      line-height: 1.5;
    }

    table {
      border-collapse: collapse;
      width: 100%;
    }

    th, td {
      border: 1px solid #d1d5db;
      padding: 8px 10px;
      text-align: left;
      vertical-align: top;
      font-size: 14px;
    }

    th {
      background: #f3f4f6;
    }

    .ok {
      color: #065f46;
      font-weight: 700;
    }

    .error {
      color: #991b1b;
      font-weight: 700;
    }
  </style>
</head>
<body>
  <div class="summary">
    <div><strong>Operacja:</strong> {$operationLabel|default:'Odjecie stanu'|escape}</div>
    <div><strong>Zamowienie:</strong> #{$result.order_id}</div>
    <div><strong>Wartosc:</strong> {$result.order_total|string_format:'%.2f'} {$result.currency|escape}</div>
    <div><strong>Liczba pozycji:</strong> {$result.items_count}</div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Status</th>
        <th>SKU</th>
        <th>Produkt magazynowy</th>
        <th>Pozycja z zamowienia</th>
        <th>Sygnatura</th>
        <th>{$quantityLabel|default:'Odjeto'|escape}</th>
        <th>Stan przed</th>
        <th>Stan po</th>
        <th>Info</th>
      </tr>
    </thead>
    <tbody>
      {foreach $result.deductions as $row}
        <tr>
          <td>{if $row.status eq 'ok'}<span class="ok">OK</span>{else}<span class="error">BLAD</span>{/if}</td>
          <td>{$row.sku|default:'-'|escape}</td>
          <td>{$row.product_name|default:'-'|escape}</td>
          <td>{$row.source_name|default:'-'|escape}</td>
          <td>{$row.signature|default:'-'|escape}</td>
          <td>{$row.deducted_qty|default:'-'|escape}</td>
          <td>{$row.before_qty|default:'-'|escape}</td>
          <td>{$row.after_qty|default:'-'|escape}</td>
          <td>{$row.message|default:'-'|escape}</td>
        </tr>
      {foreachelse}
        <tr>
          <td colspan="9">Brak zmian do pokazania.</td>
        </tr>
      {/foreach}
    </tbody>
  </table>
</body>
</html>
