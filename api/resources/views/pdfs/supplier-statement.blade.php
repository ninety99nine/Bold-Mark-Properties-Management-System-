<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Supplier Statement — {{ $supplierCode }}</title>
  @php
    $money = fn ($v) => 'R ' . number_format((float) $v, 2, '.', ' ');
    $amt   = fn ($v) => ((float) $v == 0.0) ? '0' : number_format((float) $v, 2, '.', '');
    $cum   = fn ($v) => number_format((float) $v, 2, '.', ' ');
    $age   = fn ($v) => number_format((float) $v, 2, '.', ' ');
  @endphp
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9.5px; color: #222; background: #fff; }
    .page { padding: 28px 34px 170px 34px; }

    table { border-collapse: collapse; }
    .w100 { width: 100%; }
    .right { text-align: right; }
    .bold { font-weight: bold; }

    /* Header */
    .head-cell { vertical-align: top; }
    .logo-box { background: #e9e7d4; padding: 10px 14px; display: inline-block; }
    .logo-primary { font-size: 18px; font-weight: bold; letter-spacing: 2px; color: #223; }
    .head-line { font-size: 9.5px; line-height: 1.4; }
    .head-line .k { font-weight: bold; }
    hr.rule { border: none; border-top: 1px solid #d5d5d5; margin: 14px 0; }

    /* Supplier block */
    .cust-box { background: #ededed; padding: 12px 14px; }
    .cust-box .name { font-weight: bold; font-size: 10.5px; }
    .cust-line { line-height: 1.55; font-size: 9.5px; }
    .cust-right { font-size: 9.5px; line-height: 1.65; padding-left: 16px; vertical-align: top; }

    /* Statement title + date */
    .stmt-title { font-size: 26px; font-weight: bold; letter-spacing: 1px; color: #111; }
    .date-box { border-collapse: collapse; }
    .date-box th, .date-box td { border: 1px solid #bfbfbf; padding: 6px 22px; font-size: 9px; text-align: center; }
    .date-box th { background: #f3f3f3; font-weight: bold; letter-spacing: 0.5px; }
    .date-box td { font-weight: bold; }

    /* Transactions */
    .txns { width: 100%; margin-top: 14px; }
    .txns thead th { background: #2b2b2b; color: #fff; font-size: 9px; font-weight: bold; padding: 7px 10px; text-align: left; }
    .txns thead th.num { text-align: right; }
    .txns tbody td { padding: 8px 10px; font-size: 9.5px; border-bottom: 1px solid #e2e2e2; vertical-align: top; }
    .txns tbody td.num { text-align: right; white-space: nowrap; }

    /* Footer block (ageing + total due) pinned to bottom */
    .footer-block { position: fixed; left: 34px; right: 34px; bottom: 46px; }
    .ageing { width: 100%; }
    .ageing td, .ageing th { border: 1px solid #cfcfcf; padding: 6px 10px; font-size: 9px; }
    .ageing th { text-align: left; font-weight: bold; }
    .ageing .val { text-align: right; }
    .total-row { width: 100%; margin-top: -1px; }
    .total-row .spacer { border: 1px solid #cfcfcf; }
    .total-cell { border: 1px solid #cfcfcf; padding: 10px 12px; vertical-align: top; width: 26%; }
    .total-cell .h { font-weight: bold; text-align: right; }
    .total-cell .amt { font-size: 15px; font-weight: bold; text-align: right; margin-top: 18px; }

    .page-foot { position: fixed; left: 0; right: 0; bottom: 18px; text-align: center; font-size: 9px; color: #888; }
  </style>
</head>
<body>
<div class="page">

  {{-- Header --}}
  <table class="w100">
    <tr>
      <td class="head-cell" style="width: 38%;">
        @if(!empty($companyLogoPath))
          <img src="{{ $companyLogoPath }}" alt="{{ $organization?->display_name }}" style="max-height: 70px; max-width: 230px;">
        @else
          <div class="logo-box">
            <div class="logo-primary">{{ $organization?->display_name ?? $community->name }}</div>
          </div>
        @endif
      </td>
      <td class="head-cell" style="width: 37%;">
        <div class="head-line">
          <span class="bold">{{ $community->name }}@if($entityLabel) {{ $entityLabel }}@endif</span><br>
          @if($community->registration_number)<span class="k">Reg No:</span> {{ $community->registration_number }}<br>@endif
          <span class="k">Email.:</span> {{ $organization?->contact_email ?? 'info@boldmarkprop.co.za' }}<br>
          <span class="k">Contact No.:</span> {{ $organization?->contact_phone ?? '0118249671' }}
        </div>
      </td>
      <td class="head-cell right" style="width: 25%;">
        <div class="head-line">
          <span class="bold">ADDRESS</span><br>
          {!! nl2br(e($community->address ?? '')) !!}
        </div>
      </td>
    </tr>
  </table>

  <hr class="rule">

  {{-- Supplier --}}
  <table class="w100">
    <tr>
      <td style="width: 50%; vertical-align: top;">
        <div class="cust-box">
          <div class="name">{{ $supplier->name ?? '—' }}</div>
          <div class="cust-line">
            @foreach($addressLines as $line)
              {{ $line }}@if(!$loop->last),@endif<br>
            @endforeach
          </div>
        </div>
      </td>
      <td class="cust-right" style="width: 50%;">
        @if($supplierCode)<span class="bold">Reference: {{ $supplierCode }}</span><br>@endif
        @if($supplier?->phone)Tel: {{ $supplier->phone }}<br>@endif
        @if($supplier?->email)Email: {{ $supplier->email }}@endif
      </td>
    </tr>
  </table>

  {{-- Statement title + date --}}
  <table class="w100" style="margin-top: 22px;">
    <tr>
      <td style="vertical-align: bottom;"><div class="stmt-title">STATEMENT</div></td>
      <td class="right" style="vertical-align: bottom;">
        <table class="date-box" style="margin-left: auto;">
          <tr><th>DATE</th></tr>
          <tr><td>{{ $statementDate }}</td></tr>
        </table>
      </td>
    </tr>
  </table>

  {{-- Transactions --}}
  <table class="txns">
    <thead>
      <tr>
        <th style="width: 12%;">Date</th>
        <th style="width: 22%;">Source</th>
        <th style="width: 34%;">Description</th>
        <th class="num" style="width: 10%;">Debit</th>
        <th class="num" style="width: 10%;">Credit</th>
        <th class="num" style="width: 12%;">Cumulative</th>
      </tr>
    </thead>
    <tbody>
      @foreach($rows as $r)
        <tr>
          <td>{{ $r['date'] }}</td>
          <td>{!! nl2br(e($r['source'])) !!}</td>
          <td>{{ $r['description'] }}</td>
          <td class="num">{{ $amt($r['debit']) }}</td>
          <td class="num">{{ $amt($r['credit']) }}</td>
          <td class="num">{{ $cum($r['cumulative']) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

{{-- Footer: ageing + total due --}}
<div class="footer-block">
  <table class="ageing">
    <tr>
      <th>120+ days</th><th>90+ days</th><th>60+ days</th><th>30+ days</th><th>Current</th>
    </tr>
    <tr>
      <td class="val">{{ $age($ageing['120_plus']) }}</td>
      <td class="val">{{ $age($ageing['90_days']) }}</td>
      <td class="val">{{ $age($ageing['60_days']) }}</td>
      <td class="val">{{ $age($ageing['30_days']) }}</td>
      <td class="val">{{ $age($ageing['current']) }}</td>
    </tr>
  </table>
  <table class="total-row">
    <tr>
      <td class="spacer" style="width: 74%;">&nbsp;</td>
      <td class="total-cell">
        <div class="h">Total Due</div>
        <div class="amt">{{ $money($totalDue) }}</div>
      </td>
    </tr>
  </table>
</div>

<div class="page-foot">
  Powered by {{ $organization?->display_name ?? 'Bold Mark Properties' }}
</div>

{{-- Reliable "Page X/Y" (CSS counter(pages) resolves to 0 in DomPDF). --}}
<script type="text/php">
  if (isset($pdf)) {
    $text = "Page {PAGE_NUM}/{PAGE_COUNT}";
    $font = $fontMetrics->getFont("DejaVu Sans", "normal");
    $size = 9;
    $w = $fontMetrics->getTextWidth($text, $font, $size);
    $x = ($pdf->get_width() - $w) / 2;
    $y = $pdf->get_height() - 30;
    $pdf->page_text($x, $y, $text, $font, $size, array(0.53, 0.53, 0.53));
  }
</script>

</body>
</html>
