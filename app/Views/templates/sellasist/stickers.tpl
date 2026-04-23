<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <title>{$pageTitle|default:'Naklejki Sellasist'|escape}</title>
  <style>
    * {
      box-sizing: border-box;
    }
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      color: #000;
      background: #fff;
    }
    .sheet-title {
      width: 8cm;
      height: 4.9cm;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 30px;
      page-break-inside: avoid;
    }
    .sticker {
      width: 8cm;
      height: 4.9cm;
      overflow: hidden;
      page-break-inside: avoid;
    }
    .barcode {
      width: 5cm;
    }
    .center-box {
      width: 7cm;
      text-align: center;
      margin:0 auto;
    }
    .right-rotated {
      text-decoration: underline;
      position: relative;
      float: right;
      top: 0.8cm;
      transform: rotate(90deg);
      margin-right: 0cm;
    }
    .left-rotated {
      text-decoration: underline;
      position: relative;
      float: left;
      top: 0.8cm;
      transform: rotate(90deg);
      margin-right: 0.8cm;
    }
    .glass-highlight {
      background: #000;
      color: #fff;
    }
    .big-glass-no {
      text-decoration: underline;
      position: relative;
      float: right;
      top: 0.2cm;
      right: 0.8cm;
      transform: rotate(90deg);
      font-size: 25px;
    }
    @media print {
      @page {
        size: auto;
        margin: 0;
      }
    }
  </style>
</head>
<body>
  {if $warnings}
    <div style="padding: 12px 18px; font-size: 14px;">
      {foreach $warnings as $warning}
        <div>{$warning|escape}</div>
      {/foreach}
    </div>
  {/if}
  <div class="sheet-title">ETUI</div>
  {foreach $glassStickers as $sticker}
    {for $i=1 to $sticker.qty}
      <div class="sticker">
        <div>
          <span><strong style="text-decoration: underline; position: relative; float: right; top: 0.8cm; right: 0.2cm; transform: rotate(90deg);">{$sticker.product.localization|escape}</strong></span><br>
          <div class="center-box">
            <span>{$sticker.order_id}{if $sticker.qty > 1 || $sticker.product_count > 1}<strong>-|-|-|-|-</strong>{/if}</span><br>
            <span>{$sticker.product.product_name|escape}</span><br>
          </div>
        </div>
        <hr>
        <span><strong style="text-decoration: underline; position: relative; float: right; top: 0.8cm; right: 0.8cm; font-size: 30px;">{$sticker.glass_number|escape}</strong></span>
      </div>
    {/for}
  {/foreach}
  <div class="sheet-title"> </div>
  {foreach $caseStickers as $sticker}
    {for $i=1 to $sticker.qty}
      <div class="sticker">
        <div>
          <span><strong class="left-rotated">{$sticker.account_short|escape}</strong></span>
          <span><strong class="right-rotated">{$sticker.product.localization|escape} {$sticker.carrier_short|escape}</strong></span><br>
          <div class="center-box">
            <img class="barcode" src="{$barcodeBaseUrl|escape}?format=svg&type=C128&height=50&code=ESP:o:{$sticker.order_id}">
            <br>
            <div>
              <span{if $sticker.glass eq 1} class="glass-highlight"{/if}>
                {$sticker.order_id}
                {if $sticker.qty > 1 || $sticker.product_count > 1}<strong>-|-|-|-|-</strong>{/if}
              </span><br>
              <div{if $sticker.glass eq 1} class="glass-highlight"{/if}>
                <span>{$sticker.product.product_name|escape}</span><br>
              </div>
            </div>
          </div>
        </div>
        <hr>
        <div style="width: 7cm;text-align:center;">
          <span>{$sticker.display_info|escape}</span>
        </div>
        <span><strong class="big-glass-no">{if $sticker.glass eq 1}{$sticker.glass_number|escape}{/if}</strong></span>
      </div>
    {/for}
  {/foreach}
  <script>
    window.addEventListener('load', function () {
      window.print();
    });
  </script>
</body>
</html>