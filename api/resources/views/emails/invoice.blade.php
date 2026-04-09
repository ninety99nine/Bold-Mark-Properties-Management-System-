@extends('emails.layout')

@section('title', 'Invoice ' . $invoice->invoice_number)
@section('section_label', $invoice->chargeType->name . ' Invoice')

@section('content')
  <h1 style="margin:0 0 8px;font-size:22px;font-weight:700;color:#1E2740;line-height:1.3;">
    {{ $invoice->invoice_number }}
  </h1>
  <p style="margin:0 0 24px;font-size:14px;color:#717B99;">
    {{ $invoice->chargeType->name }} · {{ $invoice->unit->estate->name }} · Unit {{ $invoice->unit->unit_number }}
  </p>

  <!-- Invoice card -->
  <table cellpadding="0" cellspacing="0" border="0" width="100%"
    style="margin-bottom:28px;border:1px solid #DCDEE8;border-radius:6px;overflow:hidden;">

    <!-- Header row -->
    <tr>
      <td style="background-color:#F8FBFF;padding:16px 20px;border-bottom:1px solid #DCDEE8;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td>
              <p style="margin:0;font-size:14px;font-weight:700;color:#1E2740;">Bold Mark Properties</p>
              <p style="margin:2px 0 0;font-size:11px;color:#717B99;">Property Management Services</p>
            </td>
            <td align="right">
              <p style="margin:0;font-size:13px;font-weight:700;color:#1E2740;">{{ $invoice->invoice_number }}</p>
              <p style="margin:2px 0 0;font-size:11px;color:#717B99;">Tax Invoice</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- Bill to + meta -->
    <tr>
      <td style="padding:16px 20px;border-bottom:1px solid #DCDEE8;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td width="50%" style="vertical-align:top;">
              <p style="margin:0 0 4px;font-size:10px;font-weight:700;color:#717B99;text-transform:uppercase;letter-spacing:0.5px;">Bill To</p>
              <p style="margin:0;font-size:13px;font-weight:700;color:#1E2740;">{{ $billedTo->full_name }}</p>
              <p style="margin:2px 0 0;font-size:12px;color:#717B99;text-transform:capitalize;">{{ $invoice->billed_to_type->value }}</p>
              <p style="margin:2px 0 0;font-size:12px;color:#717B99;">{{ $billedTo->email }}</p>
            </td>
            <td width="50%" style="vertical-align:top;text-align:right;">
              <p style="margin:0 0 4px;font-size:12px;color:#717B99;">
                Invoice Date: <strong style="color:#1E2740;">{{ $invoice->created_at->format('d M Y') }}</strong>
              </p>
              <p style="margin:0 0 4px;font-size:12px;color:#717B99;">
                Due Date: <strong style="color:#1E2740;">{{ $invoice->due_date->format('d M Y') }}</strong>
              </p>
              <p style="margin:0;font-size:12px;color:#717B99;">
                Period: <strong style="color:#1E2740;">{{ $invoice->billing_period->format('F Y') }}</strong>
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- Line item -->
    <tr>
      <td style="padding:0;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <thead>
            <tr style="background-color:#F8FBFF;">
              <th style="padding:10px 20px;font-size:10px;font-weight:700;color:#717B99;text-transform:uppercase;text-align:left;letter-spacing:0.5px;">Description</th>
              <th style="padding:10px 20px;font-size:10px;font-weight:700;color:#717B99;text-transform:uppercase;text-align:right;letter-spacing:0.5px;">Amount</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td style="padding:12px 20px;font-size:13px;color:#1E2740;border-top:1px solid #DCDEE8;">
                <strong>{{ $invoice->chargeType->name }} — Unit {{ $invoice->unit->unit_number }}</strong><br>
                <span style="font-size:11px;color:#717B99;">{{ $invoice->billing_period->format('F Y') }}</span>
              </td>
              <td style="padding:12px 20px;font-size:13px;font-weight:700;color:#1E2740;text-align:right;border-top:1px solid #DCDEE8;white-space:nowrap;">
                R {{ number_format($invoice->amount, 0, '.', ' ') }}
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr style="background-color:#F8FBFF;">
              <td style="padding:12px 20px;font-size:14px;font-weight:700;color:#1E2740;border-top:1px solid #DCDEE8;">Total Due</td>
              <td style="padding:12px 20px;font-size:14px;font-weight:700;color:#1E2740;text-align:right;border-top:1px solid #DCDEE8;white-space:nowrap;">
                R {{ number_format($invoice->amount, 0, '.', ' ') }}
              </td>
            </tr>
          </tfoot>
        </table>
      </td>
    </tr>

  </table>

  <!-- Payment note -->
  <table cellpadding="0" cellspacing="0" border="0" width="100%"
    style="margin-bottom:24px;border:1px solid #DCDEE8;border-radius:6px;background-color:#F8FBFF;">
    <tr>
      <td style="padding:14px 20px;">
        <p style="margin:0 0 4px;font-size:12px;font-weight:700;color:#1E2740;">Payment Instructions</p>
        <p style="margin:0;font-size:12px;color:#717B99;line-height:1.6;">
          Please use your invoice number <strong style="color:#1E2740;">{{ $invoice->invoice_number }}</strong>
          as the payment reference when making your EFT payment. Payment is due by
          <strong style="color:#1E2740;">{{ $invoice->due_date->format('d M Y') }}</strong>.
        </p>
      </td>
    </tr>
  </table>

  <p style="margin:0;font-size:12px;color:#A0AEC0;line-height:1.6;">
    If you have any questions about this invoice, please contact Bold Mark Properties at
    <a href="mailto:info@boldmarkprop.co.za" style="color:#D89B4B;">info@boldmarkprop.co.za</a>.
  </p>
@endsection
