@php($inr = fn ($v) => '₹'.number_format((float) $v, 2))
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Invoice {{ $invoice['invoice_number'] }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  *{box-sizing:border-box} body{font-family:"Helvetica Neue",Arial,sans-serif;color:#1f2937;margin:0;padding:32px;font-size:13px;background:#fff}
  .wrap{max-width:860px;margin:0 auto} h1{margin:0;font-size:26px;letter-spacing:.5px} .muted{color:#6b7280}
  .head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid #dc2626;padding-bottom:16px;margin-bottom:20px}
  .brand{font-size:22px;font-weight:800;color:#111827}.brand span{color:#dc2626}
  .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px}
  .box h4{margin:0 0 6px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#6b7280}
  table{width:100%;border-collapse:collapse;margin-top:8px} th{background:#111827;color:#fff;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.5px;padding:8px}
  td{padding:8px;border-bottom:1px solid #e5e7eb;vertical-align:top} td.r,th.r{text-align:right}
  .totals{margin-left:auto;width:320px;margin-top:16px}.totals td{border:none;padding:4px 8px}.totals tr.grand td{font-size:16px;font-weight:700;border-top:2px solid #111827;padding-top:8px}
  .foot{margin-top:40px;border-top:1px solid #e5e7eb;padding-top:12px;font-size:11px}
  @media print{body{padding:0}.noprint{display:none}}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <div>
      <div class="brand">{{ $invoice['seller']['name'] }}<span>.</span></div>
      <div class="muted">{{ $invoice['seller']['address'] }}</div>
      <div class="muted">GSTIN: {{ $invoice['seller']['gstin'] }} · {{ $invoice['seller']['email'] }} · {{ $invoice['seller']['phone'] }}</div>
    </div>
    <div style="text-align:right">
      <h1>TAX INVOICE</h1>
      <div><strong>{{ $invoice['invoice_number'] }}</strong></div>
      <div class="muted">Date: {{ $invoice['invoice_date'] }}</div>
      <div class="muted">Order: {{ $invoice['order_number'] }}</div>
    </div>
  </div>

  <div class="grid">
    @foreach (['Billed to' => $invoice['billing_address'], 'Shipped to' => $invoice['shipping_address']] as $title => $a)
      <div class="box">
        <h4>{{ $title }}</h4>
        <strong>{{ $a['name'] ?? '' }}</strong><br>
        {{ $a['line1'] ?? '' }}@if(!empty($a['line2'])), {{ $a['line2'] }}@endif<br>
        {{ $a['city'] ?? '' }}, {{ $a['state'] ?? '' }} {{ $a['postal_code'] ?? '' }}<br>
        Phone: {{ $a['phone'] ?? '' }}
      </div>
    @endforeach
    <div class="box">
      <h4>Payment</h4>
      {{ $invoice['payment_method'] }}<br>
      Status: {{ $invoice['payment_status'] }}<br>
      @if($invoice['transaction_id']) Txn: {{ $invoice['transaction_id'] }} @endif
    </div>
  </div>

  <table>
    <thead><tr><th>#</th><th>Item</th><th>HSN</th><th class="r">Qty</th><th class="r">Rate</th><th class="r">Discount</th><th class="r">Taxable</th><th class="r">GST</th><th class="r">Total</th></tr></thead>
    <tbody>
    @foreach ($invoice['items'] as $i => $item)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td><strong>{{ $item['name'] }}</strong><br><span class="muted">SKU {{ $item['sku'] }}@if($item['part_number']) · Part {{ $item['part_number'] }}@endif</span></td>
        <td>{{ $item['hsn'] }}</td>
        <td class="r">{{ $item['quantity'] }}</td>
        <td class="r">{{ $inr($item['unit_price']) }}</td>
        <td class="r">{{ $inr($item['discount']) }}</td>
        <td class="r">{{ $inr($item['taxable_value']) }}</td>
        <td class="r">{{ $inr($item['tax_amount']) }}<br><span class="muted">{{ rtrim(rtrim(number_format($item['tax_rate'], 2), '0'), '.') }}% {{ $invoice['tax_split'] }}</span></td>
        <td class="r">{{ $inr($item['total']) }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>

  <table class="totals">
    <tr><td>Subtotal</td><td class="r">{{ $inr($invoice['totals']['subtotal']) }}</td></tr>
    @if($invoice['totals']['discount'] > 0)
      <tr><td>Discount @if($invoice['coupon_code'])({{ $invoice['coupon_code'] }})@endif</td><td class="r">−{{ $inr($invoice['totals']['discount']) }}</td></tr>
    @endif
    <tr><td>Shipping</td><td class="r">{{ $invoice['totals']['shipping'] > 0 ? $inr($invoice['totals']['shipping']) : 'Free' }}</td></tr>
    <tr><td>GST ({{ $invoice['tax_split'] }})</td><td class="r">{{ $inr($invoice['totals']['tax']) }}</td></tr>
    <tr class="grand"><td>Grand total</td><td class="r">{{ $inr($invoice['totals']['grand_total']) }}</td></tr>
  </table>

  <div class="foot muted">
    This is a computer-generated invoice and does not require a signature. Goods once sold are subject to our return policy.<br>
    Thank you for shopping with {{ $invoice['seller']['name'] }}.
  </div>
  <p class="noprint" style="text-align:center;margin-top:24px"><button onclick="window.print()" style="padding:10px 20px;background:#dc2626;color:#fff;border:0;border-radius:6px;cursor:pointer">Print / Save as PDF</button></p>
</div>
</body>
</html>
