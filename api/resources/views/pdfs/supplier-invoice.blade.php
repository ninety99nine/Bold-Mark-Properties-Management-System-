<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>{{ $invoice->grv_number }}</title>
  @php
    $et = $community?->entity_type;
    $et = $et instanceof \BackedEnum ? $et->value : $et;
    $entityLabel = [
      'body_corporate' => 'Body Corporate',
      'hoa'            => 'Home Owners Association',
      'share_block'    => 'Share Block',
    ][$et] ?? '';

    // Supplier address lines (structured fields, else the free-text address).
    $supplierAddress = collect([
      $supplier?->address_line_1,
      $supplier?->address_line_2,
      $supplier?->suburb,
      $supplier?->town,
      $supplier?->postal_code,
    ])->filter(fn ($l) => trim((string) $l) !== '')->values();
    if ($supplierAddress->isEmpty() && $supplier?->address) {
      $supplierAddress = collect(preg_split('/,\s*/', (string) $supplier->address))
        ->filter(fn ($l) => trim($l) !== '')->values();
    }

    $money = fn ($v) => 'R ' . number_format((float) $v, 2, '.', ' ');
    $plain = fn ($v) => number_format((float) $v, 2, '.', ' ');
  @endphp
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9.5px; color: #222; background: #fff; }
    .page { padding: 28px 34px; }
    table { border-collapse: collapse; }
    .w100 { width: 100%; }
    .right { text-align: right; }
    .bold { font-weight: bold; }

    .head-cell { vertical-align: top; }
    .logo-box { background: #e9e7d4; padding: 10px 14px; display: inline-block; }
    .logo-primary { font-size: 18px; font-weight: bold; letter-spacing: 2px; color: #223; }
    .head-line { font-size: 9.5px; line-height: 1.4; }
    .head-line .k { font-weight: bold; }

    hr.rule { border: none; border-top: 1px solid #d5d5d5; margin: 14px 0; }

    .party { font-size: 9.5px; line-height: 1.55; vertical-align: top; }
    .party .h { font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }

    .invoice-title { font-size: 26px; font-weight: bold; letter-spacing: 1px; color: #111; }
    .summary th, .summary td { border: 1px solid #cfcfcf; padding: 6px 10px; font-size: 9px; text-align: center; }
    .summary th { background: #f3f3f3; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }

    .items { width: 100%; margin-top: 16px; }
    .items thead th { background: #2b2b2b; color: #fff; font-size: 9px; font-weight: bold; padding: 7px 10px; text-align: left; }
    .items thead th.num { text-align: right; }
    .items tbody td { padding: 8px 10px; font-size: 9.5px; border-bottom: 1px solid #e2e2e2; vertical-align: top; }
    .items tbody td.num { text-align: right; white-space: nowrap; }

    .bottom { width: 100%; margin-top: 24px; }
    .bank-box { border: 1px solid #cfcfcf; padding: 12px 14px; font-size: 9px; line-height: 1.65; }
    .bank-box .h { font-weight: bold; margin-bottom: 4px; }
    .totals { width: 100%; }
    .totals td { border: 1px solid #cfcfcf; padding: 7px 10px; font-size: 9.5px; }
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
        @if(!empty($companyLogoPath))
          <img src="{{ $companyLogoPath }}" alt="{{ $organization?->display_name }}" style="max-height: 70px; max-width: 230px;">
        @endif
      </td>
      <td class="head-cell" style="width: 37%;">
        <div class="head-line">
          <span class="bold">{{ $community?->name }}@if($entityLabel) {{ $entityLabel }}@endif</span><br>
          @if($community?->registration_number)<span class="k">Reg No:</span> {{ $community->registration_number }}<br>@endif
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

  {{-- ══════════ Supplier info + title ══════════ --}}
  <table class="w100" style="margin-top: 8px;">
    <tr>
      <td class="party" style="width: 32%;">
        <div class="h">Supplier Information</div>
        {{ $supplier?->name }}<br>
        @if($invoice->our_reference)OUR REFERENCE: {{ $invoice->our_reference }}<br>@endif
        @if($invoice->supplier_reference)SUPPLIER REFERENCE: {{ $invoice->supplier_reference }}<br>@endif
        @if($supplier?->email)Email: {{ $supplier->email }}@endif
      </td>
      <td class="party" style="width: 30%;">
        <div class="h">Supplier Address</div>
        @forelse($supplierAddress as $line)
          {{ $line }}@if(!$loop->last),@endif<br>
        @empty
          &nbsp;
        @endforelse
      </td>
      <td class="right" style="width: 38%; vertical-align: top;">
        <div class="invoice-title">SUPPLIER TAX INVOICE</div>
      </td>
    </tr>
  </table>

  {{-- ══════════ Summary box ══════════ --}}
  <table class="summary" style="margin-top: 14px; margin-left: auto;">
    <tr>
      <th>Invoice No.</th>
      <th>Invoice Date</th>
      <th>Due Date</th>
      <th>Invoice Total</th>
    </tr>
    <tr>
      <td>{{ $invoice->grv_number }}</td>
      <td>{{ ($invoice->invoice_date ?? $invoice->created_at)->format('Y-m-d') }}</td>
      <td>{{ optional($invoice->due_date)->format('Y-m-d') ?? ($invoice->invoice_date ?? $invoice->created_at)->format('Y-m-d') }}</td>
      <td class="bold">{{ $money($invoice->total) }}</td>
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
      @foreach($invoice->items as $item)
        <tr>
          <td>{{ $item->ledger?->name ?? $item->account_name ?? 'Account' }}</td>
          <td>{{ $item->description ?? $item->ledger?->name }}</td>
          <td class="num">{{ number_format($item->quantity, 2) }}</td>
          <td class="num">{{ $plain($item->unit_price) }}</td>
          <td class="num">{{ $plain($item->discount) }}</td>
          <td class="num">{{ $plain($item->tax_amount) }}</td>
          <td class="num">{{ $plain($item->line_total) }}</td>
        </tr>
      @endforeach
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
              Bank Name: {{ $supplier?->bank_name ?? '—' }}<br>
              Account Number: {{ $supplier?->account_number ?? '—' }}<br>
              Branch Code: {{ $supplier?->branch_code ?? '—' }}
            </td>
            <td style="vertical-align: top;">
              Reference: {{ $invoice->supplier_reference ?? '' }}<br>
              Account Holder: {{ $supplier?->name }}<br>
              Account Type: {{ ucfirst($supplier?->account_type instanceof \BackedEnum ? $supplier->account_type->value : ($supplier?->account_type ?? 'Current')) }}<br>
              Branch Name: {{ $supplier?->branch_name ?? '—' }}
            </td>
          </tr></table>
        </div>
      </td>
      <td style="width: 42%; vertical-align: top;">
        <table class="totals">
          <tr>
            <td>Sub-Total excl.</td>
            <td class="val">{{ $plain($invoice->subtotal) }}</td>
          </tr>
          <tr>
            <td>Discount excl.</td>
            <td class="val">{{ $plain($invoice->discount) }}</td>
          </tr>
          <tr>
            <td>Sub-Total excl. (after discount)</td>
            <td class="val">{{ $plain($invoice->subtotal) }}</td>
          </tr>
          <tr class="grand">
            <td>TOTAL</td>
            <td class="val">{{ $money($invoice->total) }}</td>
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
