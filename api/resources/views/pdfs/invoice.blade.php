<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>{{ $invoice->invoice_number }}</title>
  @php
    $community = $invoice->unit->community;
    $et = $community->entity_type;
    $et = $et instanceof \BackedEnum ? $et->value : $et;
    $entityLabel = [
      'body_corporate' => 'Body Corporate',
      'hoa'            => 'Home Owners Association',
      'share_block'    => 'Share Block',
    ][$et] ?? '';

    $addressLines = collect(preg_split('/,\s*/', (string) ($billedTo?->address ?? '')))
      ->filter(fn ($l) => trim($l) !== '')->values();

    $reference = $invoice->unit->customer_code;
    $bank      = $invoice->bankAccount;

    $subtotal  = $invoice->subtotal ?? $invoice->amount;
    $vat       = $invoice->vat_amount ?? 0;
    $money     = fn ($v) => 'R ' . number_format((float) $v, 2, '.', ' ');
    $plain     = fn ($v) => number_format((float) $v, 2, '.', ' ');
  @endphp
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'DejaVu Sans', Arial, sans-serif;
      font-size: 9.5px;
      color: #222;
      background: #fff;
    }
    .page { padding: 28px 34px; }

    table { border-collapse: collapse; }
    .w100 { width: 100%; }
    .right { text-align: right; }
    .center { text-align: center; }
    .bold { font-weight: bold; }
    .muted { color: #555; }

    /* ── Header (logo / community / address) ── */
    .head-cell { vertical-align: top; }
    .logo-box {
      background: #e9e7d4;
      padding: 10px 14px;
      display: inline-block;
    }
    .logo-primary { font-size: 18px; font-weight: bold; letter-spacing: 2px; color: #223; }
    .logo-amber   { color: #d9812a; }
    .logo-sub     { font-size: 9px; letter-spacing: 5px; color: #223; margin-top: 2px; }
    .head-line { font-size: 9.5px; line-height: 1.4; }
    .head-line .k { font-weight: bold; }

    hr.rule { border: none; border-top: 1px solid #d5d5d5; margin: 14px 0; }

    /* ── Bill to ── */
    .billto-box { background: #ededed; padding: 12px 14px; }
    .billto-box .name { font-weight: bold; font-size: 10.5px; }
    .billto-line { line-height: 1.55; font-size: 9.5px; }
    .billto-right { font-size: 9.5px; line-height: 1.65; padding-left: 16px; vertical-align: top; }

    /* ── Invoice title + summary ── */
    .invoice-title { font-size: 26px; font-weight: bold; letter-spacing: 1px; color: #111; }
    .summary { border-collapse: collapse; }
    .summary th, .summary td { border: 1px solid #cfcfcf; padding: 6px 10px; font-size: 9px; text-align: center; }
    .summary th { background: #f3f3f3; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }

    /* ── Line items ── */
    .items { width: 100%; margin-top: 16px; }
    .items thead th {
      background: #2b2b2b; color: #fff; font-size: 9px; font-weight: bold;
      padding: 7px 10px; text-align: left; text-transform: none;
    }
    .items thead th.num { text-align: right; }
    .items tbody td { padding: 8px 10px; font-size: 9.5px; border-bottom: 1px solid #e2e2e2; vertical-align: top; }
    .items tbody td.num { text-align: right; white-space: nowrap; }

    /* ── Bottom (banking + totals) ── */
    .bottom { width: 100%; margin-top: 24px; }
    .bank-box { border: 1px solid #cfcfcf; padding: 12px 14px; font-size: 9px; line-height: 1.65; }
    .bank-box .h { font-weight: bold; margin-bottom: 4px; }
    .totals { width: 100%; }
    .totals td { border: 1px solid #cfcfcf; padding: 7px 10px; font-size: 9.5px; }
    .totals .lbl { color: #333; }
    .totals .val { text-align: right; white-space: nowrap; }
    .totals .grand td { font-size: 13px; font-weight: bold; }

    .footer { margin-top: 24px; text-align: center; font-size: 9px; color: #888; }
  </style>
</head>
<body>
<div class="page">

  {{-- ══════════ Header ══════════ --}}
  <table class="w100">
    <tr>
      <td class="head-cell" style="width: 38%;">
        <div class="logo-box">
          <div class="logo-primary">BOLD <span class="logo-amber">MARK</span></div>
          <div class="logo-sub">PROPERTIES</div>
        </div>
      </td>
      <td class="head-cell" style="width: 37%;">
        <div class="head-line">
          <span class="bold">{{ $community->name }}@if($entityLabel) {{ $entityLabel }}@endif</span><br>
          @if($community->registration_number)<span class="k">Reg No:</span> {{ $community->registration_number }}<br>@endif
          <span class="k">Email.:</span> info@boldmarkprop.co.za<br>
          <span class="k">Contact No.:</span> 0118249671
        </div>
      </td>
      <td class="head-cell right" style="width: 25%;">
        <div class="head-line">
          <span class="bold">ADDRESS</span><br>
          112 Boeing Rd<br>
          Bedfordview<br>
          Johannesburg<br>
          Gauteng<br>
          2007
        </div>
      </td>
    </tr>
  </table>

  <hr class="rule">

  {{-- ══════════ Bill to ══════════ --}}
  <table class="w100">
    <tr>
      <td style="width: 50%; vertical-align: top;">
        <div class="billto-box">
          <div class="name">{{ $billedTo?->full_name ?? '—' }}</div>
          <div class="billto-line">
            @forelse($addressLines as $line)
              {{ $line }}@if(!$loop->last),@endif<br>
            @empty
              &nbsp;
            @endforelse
          </div>
        </div>
      </td>
      <td class="billto-right" style="width: 50%;">
        <span class="bold">Unit No {{ $invoice->unit->unit_number }}</span><br>
        @if($reference)Reference: {{ $reference }}<br>@endif
        @if($billedTo?->phone)Tel: {{ $billedTo->phone }}<br>@endif
        @if($billedTo?->email)Email: {{ $billedTo->email }}@endif
      </td>
    </tr>
  </table>

  {{-- ══════════ Invoice title + summary ══════════ --}}
  <table class="w100" style="margin-top: 24px;">
    <tr>
      <td style="vertical-align: bottom;">
        <div class="invoice-title">INVOICE</div>
      </td>
      <td class="right" style="vertical-align: bottom;">
        <table class="summary" style="margin-left: auto;">
          <tr>
            <th>Invoice No.</th>
            <th>Invoice Date</th>
            <th>Due Date</th>
            <th>Invoice Total</th>
          </tr>
          <tr>
            <td>{{ $invoice->invoice_number }}</td>
            <td>{{ ($invoice->invoice_date ?? $invoice->created_at)->format('Y-m-d') }}</td>
            <td>{{ $invoice->due_date->format('Y-m-d') }}</td>
            <td class="bold">{{ $money($invoice->amount) }}</td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  {{-- ══════════ Line items ══════════ --}}
  <table class="items">
    <thead>
      <tr>
        <th style="width: 20%;">Account</th>
        <th style="width: 34%;">Description</th>
        <th class="num" style="width: 8%;">Qty</th>
        <th class="num" style="width: 12%;">Unit Price</th>
        <th class="num" style="width: 8%;">Disc</th>
        <th class="num" style="width: 8%;">Tax</th>
        <th class="num" style="width: 10%;">Total</th>
      </tr>
    </thead>
    <tbody>
      @if($invoice->items->isNotEmpty())
        @foreach($invoice->items as $item)
          <tr>
            <td>{{ $item->ledger?->name ?? 'Account' }}</td>
            <td>{{ $item->description ?? $item->ledger?->name }}</td>
            <td class="num">{{ number_format($item->quantity, 2) }}</td>
            <td class="num">{{ $plain($item->amount) }}</td>
            <td class="num">0.00</td>
            <td class="num">{{ $plain($item->tax_amount) }}</td>
            <td class="num">{{ $plain($item->line_total) }}</td>
          </tr>
        @endforeach
      @else
        <tr>
          <td>{{ $invoice->ledger?->name ?? 'Account' }}</td>
          <td>{{ $invoice->ledger?->name ?? 'Charge' }} — {{ $invoice->billing_period->format('F Y') }}</td>
          <td class="num">1.00</td>
          <td class="num">{{ $plain($invoice->amount) }}</td>
          <td class="num">0.00</td>
          <td class="num">0.00</td>
          <td class="num">{{ $plain($invoice->amount) }}</td>
        </tr>
      @endif
    </tbody>
  </table>

  {{-- ══════════ Banking details + totals ══════════ --}}
  <table class="bottom">
    <tr>
      <td style="width: 58%; vertical-align: top; padding-right: 24px;">
        <div class="bank-box">
          <div class="h">BANKING DETAILS</div>
          <table class="w100"><tr>
            <td style="vertical-align: top; width: 55%;">
              Bank Name: {{ $bank?->bank_name ?? '—' }}<br>
              Account Number: {{ $bank?->account_number ?? '—' }}<br>
              @if($reference)Reference: {{ $reference }}@endif
            </td>
            <td style="vertical-align: top;">
              Account Holder: {{ $community->name }}<br>
              Account Type: {{ ucfirst($bank?->type instanceof \BackedEnum ? $bank->type->value : ($bank?->type ?? 'Current')) }}
            </td>
          </tr></table>
        </div>
      </td>
      <td style="width: 42%; vertical-align: top;">
        <table class="totals">
          <tr>
            <td class="lbl">Sub-Total excl.</td>
            <td class="val">{{ $plain($subtotal) }}</td>
          </tr>
          <tr>
            <td class="lbl">Discount excl.</td>
            <td class="val">0.00</td>
          </tr>
          <tr>
            <td class="lbl">Sub-Total excl. (after discount)</td>
            <td class="val">{{ $plain($subtotal) }}</td>
          </tr>
          <tr class="grand">
            <td class="lbl">TOTAL</td>
            <td class="val">{{ $money($invoice->amount) }}</td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <div class="footer">
    Page 1/1<br>
    Powered by Bold Mark Properties
  </div>

</div>
</body>
</html>
