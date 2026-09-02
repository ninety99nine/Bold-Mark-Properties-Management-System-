<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>{{ $levelTitle }} — {{ $unit->customer_code }}</title>
  @php
    $et = $community->entity_type;
    $et = $et instanceof \BackedEnum ? $et->value : $et;
    $entityLabel = [
      'body_corporate' => 'Body Corporate',
      'hoa'            => 'Home Owners Association',
      'share_block'    => 'Share Block',
    ][$et] ?? '';

    $money = fn ($v) => 'R ' . number_format((float) $v, 2, '.', ' ');

    $bank = null;
    try { $bank = $community->bankAccounts()->first(); } catch (\Throwable $e) { $bank = null; }

    $today = \Carbon\Carbon::parse($ageingDate)->format('d F Y');

    // Per-level letter copy.
    $intro = [
      'Reminder'         => 'This is a friendly reminder that your account currently reflects an outstanding balance. We kindly request that you settle the amount below at your earliest convenience.',
      '1st Notice'       => 'Our records indicate that your account is in arrears. This is a first notice requesting that the outstanding balance reflected below be settled immediately.',
      '2nd Notice'       => 'Despite our previous notice, your account remains in arrears. This is a second notice. Please settle the outstanding balance reflected below without further delay.',
      'Final Notice'     => 'This is a FINAL NOTICE. Your account remains in arrears and, failing settlement, the matter may be escalated and handed over for collection.',
      'Letter of Demand' => 'This letter serves as a formal LETTER OF DEMAND. You are required to settle the full outstanding balance reflected below within 14 (fourteen) days of the date of this letter, failing which the matter may be handed over to our attorneys and/or referred to the CSOS for adjudication, without further notice. All legal costs incurred will be for your account.',
    ][$levelTitle] ?? 'Your account is in arrears. Please settle the outstanding balance reflected below.';
  @endphp
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #1a2744; }
    .page { padding: 30px 38px; }
    table { border-collapse: collapse; }
    .w100 { width: 100%; }
    .right { text-align: right; }
    .bold { font-weight: bold; }
    .muted { color: #555; }

    .logo-box { background: #1a2744; padding: 12px 16px; display: inline-block; }
    .logo-primary { font-size: 18px; font-weight: bold; letter-spacing: 2px; color: #fff; }
    .logo-amber { color: #e8a040; }
    .logo-sub { font-size: 8px; letter-spacing: 5px; color: #cbd3e2; margin-top: 2px; }
    .head-line { font-size: 9.5px; line-height: 1.45; }
    .head-line .k { font-weight: bold; }

    hr.rule { border: none; border-top: 2px solid #e8a040; margin: 16px 0; }

    .subject { font-size: 15px; font-weight: bold; color: #1a2744; margin: 18px 0 6px; text-transform: uppercase; letter-spacing: 0.5px; }
    .addressee { margin: 14px 0; line-height: 1.5; }
    .body-copy { line-height: 1.6; margin: 12px 0; text-align: justify; }

    .ageing { width: 100%; margin: 16px 0; }
    .ageing th, .ageing td { border: 1px solid #cfcfcf; padding: 6px 8px; font-size: 9px; text-align: right; }
    .ageing th { background: #eef1f5; font-weight: bold; color: #1a2744; }
    .ageing td.lbl, .ageing th.lbl { text-align: left; }
    .ageing tr.total td { background: #1a2744; color: #fff; font-weight: bold; }

    .bank-box { border: 1px solid #cfcfcf; padding: 10px 12px; font-size: 9px; line-height: 1.6; margin-top: 8px; }
    .bank-box .h { font-weight: bold; margin-bottom: 4px; color: #1a2744; }

    .sign { margin-top: 28px; line-height: 1.6; }
    .footer { margin-top: 26px; text-align: center; font-size: 8.5px; color: #888; }
  </style>
</head>
<body>
<div class="page">

  {{-- Letterhead --}}
  <table class="w100">
    <tr>
      <td style="width: 45%; vertical-align: top;">
        <div class="logo-box">
          <div class="logo-primary">BOLD <span class="logo-amber">MARK</span></div>
          <div class="logo-sub">PROPERTIES</div>
        </div>
      </td>
      <td class="right head-line" style="width: 55%; vertical-align: top;">
        <span class="bold">{{ $community->name }}@if($entityLabel) {{ $entityLabel }}@endif</span><br>
        @if($community->registration_number)<span class="k">Reg No:</span> {{ $community->registration_number }}<br>@endif
        <span class="k">Email:</span> info@boldmarkprop.co.za<br>
        <span class="k">Contact:</span> 011 824 9671
      </td>
    </tr>
  </table>

  <hr class="rule">

  {{-- Date + addressee --}}
  <div class="right muted">{{ $today }}</div>
  <div class="addressee">
    <span class="bold">{{ $owner?->full_name ?? $unit->customer_code }}</span><br>
    @if($unit->customer_code)Account: {{ $unit->customer_code }}<br>@endif
    Unit No: {{ $unit->unit_number }}<br>
    @if($owner?->email)Email: {{ $owner->email }}@endif
  </div>

  {{-- Subject --}}
  <div class="subject">{{ $levelTitle }}</div>

  <div class="body-copy">Dear {{ $owner?->full_name ?? 'Customer' }},</div>
  <div class="body-copy">{{ $intro }}</div>

  {{-- Ageing breakdown --}}
  <table class="ageing">
    <thead>
      <tr>
        <th class="lbl">Account</th>
        <th>120+ Days</th>
        <th>90 Days</th>
        <th>60 Days</th>
        <th>30 Days</th>
        <th>Current</th>
        <th>Balance</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td class="lbl">{{ $unit->customer_code }}</td>
        <td>{{ $money($row['120_plus']) }}</td>
        <td>{{ $money($row['90_days']) }}</td>
        <td>{{ $money($row['60_days']) }}</td>
        <td>{{ $money($row['30_days']) }}</td>
        <td>{{ $money($row['current']) }}</td>
        <td class="bold">{{ $money($row['balance']) }}</td>
      </tr>
      <tr class="total">
        <td class="lbl">Total Due</td>
        <td colspan="5"></td>
        <td>{{ $money($row['balance']) }}</td>
      </tr>
    </tbody>
  </table>

  {{-- Banking details --}}
  <div class="bank-box">
    <div class="h">BANKING DETAILS</div>
    Account Holder: {{ $community->name }}<br>
    @if($bank)Bank: {{ $bank->bank_name ?? '—' }} &nbsp;|&nbsp; Account No: {{ $bank->account_number ?? '—' }}<br>@endif
    Reference: {{ $unit->customer_code }}
  </div>

  <div class="sign">
    Yours faithfully,<br><br>
    <span class="bold">The Managing Agent</span><br>
    on behalf of {{ $community->name }}<br>
    Bold Mark Properties
  </div>

  <div class="footer">
    This notice was generated on {{ now()->format('d F Y') }}. Please disregard if payment has already been made.<br>
    Powered by Bold Mark Properties
  </div>

</div>
</body>
</html>
