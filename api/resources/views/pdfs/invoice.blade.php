<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $invoice->invoice_number }}</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
      font-size: 12px;
      color: #1E2740;
      background: #ffffff;
    }
    .page { padding: 40px 48px; }

    /* Header */
    .header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 32px;
      padding-bottom: 24px;
      border-bottom: 2px solid #1F3A5C;
    }
    .brand-name {
      font-size: 20px;
      font-weight: 800;
      color: #1F3A5C;
      letter-spacing: 2px;
      text-transform: uppercase;
    }
    .brand-subtitle {
      font-size: 10px;
      color: #D89B4B;
      letter-spacing: 3px;
      text-transform: uppercase;
      margin-top: 2px;
    }
    .invoice-label {
      text-align: right;
    }
    .invoice-label h1 {
      font-size: 22px;
      font-weight: 700;
      color: #1F3A5C;
    }
    .invoice-label .tax-label {
      font-size: 10px;
      color: #717B99;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-top: 2px;
    }

    /* Bill To + Meta */
    .meta-row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 32px;
    }
    .bill-to h4 {
      font-size: 9px;
      font-weight: 700;
      color: #717B99;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      margin-bottom: 6px;
    }
    .bill-to .name { font-size: 14px; font-weight: 700; color: #1E2740; }
    .bill-to .role { font-size: 11px; color: #717B99; margin-top: 2px; text-transform: capitalize; }
    .bill-to .email { font-size: 11px; color: #717B99; margin-top: 2px; }
    .invoice-meta { text-align: right; }
    .invoice-meta table { margin-left: auto; }
    .invoice-meta td { font-size: 12px; padding: 2px 0; }
    .invoice-meta .label { color: #717B99; padding-right: 16px; }
    .invoice-meta .value { color: #1E2740; font-weight: 600; }

    /* Line items table */
    .items-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 0;
    }
    .items-table thead tr {
      background-color: #F8FBFF;
      border-top: 1px solid #DCDEE8;
      border-bottom: 1px solid #DCDEE8;
    }
    .items-table th {
      padding: 10px 16px;
      font-size: 9px;
      font-weight: 700;
      color: #717B99;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      text-align: left;
    }
    .items-table th.right { text-align: right; }
    .items-table tbody td {
      padding: 14px 16px;
      font-size: 12px;
      color: #1E2740;
      border-bottom: 1px solid #DCDEE8;
    }
    .items-table tbody .desc-sub {
      font-size: 10px;
      color: #717B99;
      margin-top: 2px;
    }
    .items-table tbody .amount { text-align: right; font-weight: 700; white-space: nowrap; }
    .items-table tfoot tr { background-color: #F8FBFF; }
    .items-table tfoot td {
      padding: 12px 16px;
      font-size: 13px;
      font-weight: 700;
      color: #1E2740;
      border-top: 2px solid #1F3A5C;
    }
    .items-table tfoot .total-amount {
      text-align: right;
      font-size: 14px;
      color: #1F3A5C;
      white-space: nowrap;
    }

    /* Outer border */
    .invoice-box {
      border: 1px solid #DCDEE8;
      border-radius: 6px;
      overflow: hidden;
      margin-bottom: 28px;
    }
    .invoice-box-header {
      background-color: #F8FBFF;
      padding: 14px 16px;
      border-bottom: 1px solid #DCDEE8;
    }

    /* Payment note */
    .payment-note {
      background-color: #F8FBFF;
      border: 1px solid #DCDEE8;
      border-radius: 6px;
      padding: 14px 16px;
      margin-bottom: 24px;
    }
    .payment-note h4 {
      font-size: 11px;
      font-weight: 700;
      color: #1E2740;
      margin-bottom: 4px;
    }
    .payment-note p { font-size: 11px; color: #717B99; line-height: 1.6; }
    .payment-note strong { color: #1E2740; }

    /* Footer */
    .footer {
      margin-top: 40px;
      padding-top: 16px;
      border-top: 1px solid #DCDEE8;
    }
    .footer p { font-size: 10px; color: #A0AEC0; line-height: 1.6; }
    .amber { color: #D89B4B; }
  </style>
</head>
<body>
<div class="page">

  <!-- Header -->
  <div class="header">
    <div>
      <div class="brand-name">Bold Mark</div>
      <div class="brand-subtitle">Properties</div>
    </div>
    <div class="invoice-label">
      <h1>{{ $invoice->invoice_number }}</h1>
      <div class="tax-label">Tax Invoice</div>
    </div>
  </div>

  <!-- Bill To + Meta -->
  <div class="meta-row">
    <div class="bill-to">
      <h4>Bill To</h4>
      <div class="name">{{ $billedTo?->full_name ?? '—' }}</div>
      <div class="role">{{ $invoice->billed_to_type->value }}</div>
      <div class="email">{{ $billedTo?->email ?? '—' }}</div>
    </div>
    <div class="invoice-meta">
      <table>
        <tr>
          <td class="label">Invoice Date</td>
          <td class="value">{{ $invoice->created_at->format('d M Y') }}</td>
        </tr>
        <tr>
          <td class="label">Due Date</td>
          <td class="value">{{ $invoice->due_date->format('d M Y') }}</td>
        </tr>
        <tr>
          <td class="label">Period</td>
          <td class="value">{{ $invoice->billing_period->format('F Y') }}</td>
        </tr>
        <tr>
          <td class="label">Estate</td>
          <td class="value">{{ $invoice->unit->estate->name }}</td>
        </tr>
        <tr>
          <td class="label">Unit</td>
          <td class="value">{{ $invoice->unit->unit_number }}</td>
        </tr>
      </table>
    </div>
  </div>

  <!-- Line items -->
  <div class="invoice-box">
    <table class="items-table">
      <thead>
        <tr>
          <th>Description</th>
          <th class="right">Amount</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>
            <strong>{{ $invoice->chargeType->name }} — Unit {{ $invoice->unit->unit_number }}</strong>
            <div class="desc-sub">{{ $invoice->billing_period->format('F Y') }}</div>
          </td>
          <td class="amount">R {{ number_format($invoice->amount, 0, '.', ' ') }}</td>
        </tr>
      </tbody>
      <tfoot>
        <tr>
          <td>Total Due</td>
          <td class="total-amount">R {{ number_format($invoice->amount, 0, '.', ' ') }}</td>
        </tr>
      </tfoot>
    </table>
  </div>

  <!-- Payment note -->
  <div class="payment-note">
    <h4>Payment Instructions</h4>
    <p>
      Please use your invoice number <strong>{{ $invoice->invoice_number }}</strong>
      as the payment reference when making your EFT payment.
      Payment is due by <strong>{{ $invoice->due_date->format('d M Y') }}</strong>.
    </p>
  </div>

  <!-- Footer -->
  <div class="footer">
    <p>
      <strong style="color:#1E2740;">Bold Mark Properties (Pty) Ltd</strong> &nbsp;·&nbsp;
      112 Boeing Rd, Bedfordview, Johannesburg &nbsp;·&nbsp;
      NAMA-9141 &nbsp;·&nbsp; PPRA Registered: 202603011001590
    </p>
    <p style="margin-top:4px;">
      info@boldmarkprop.co.za &nbsp;·&nbsp; www.boldmarkprop.co.za
    </p>
    <p style="margin-top:8px;color:#DCDEE8;">
      © {{ date('Y') }} Bold Mark Properties. All rights reserved.
    </p>
  </div>

</div>
</body>
</html>
