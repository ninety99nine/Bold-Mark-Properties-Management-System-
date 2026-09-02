<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credit Note {{ $creditNote->credit_note_number }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#1a2744;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e2e8f0;">
                    <tr>
                        <td style="background:#1a2744;padding:24px 32px;">
                            <h1 style="margin:0;color:#ffffff;font-size:20px;">{{ $creditNote->organization?->name ?? config('app.name') }}</h1>
                            <p style="margin:4px 0 0;color:#e8a040;font-size:14px;font-weight:bold;">Credit Note</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px;font-size:15px;">Dear {{ $billedTo->full_name ?? 'Customer' }},</p>
                            <p style="margin:0 0 20px;font-size:14px;line-height:1.6;color:#475569;">
                                Please find below the details of credit note
                                <strong>{{ $creditNote->credit_note_number }}</strong> raised on your account
                                @if($creditNote->unit?->community) at {{ $creditNote->unit->community->name }}@endif.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;margin-bottom:20px;">
                                <tr>
                                    <td style="padding:6px 0;color:#64748b;">Credit Note No.</td>
                                    <td style="padding:6px 0;text-align:right;font-weight:bold;">{{ $creditNote->credit_note_number }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0;color:#64748b;">Date</td>
                                    <td style="padding:6px 0;text-align:right;">{{ $creditNote->credit_note_date?->format('d M Y') }}</td>
                                </tr>
                                @if($creditNote->reason)
                                <tr>
                                    <td style="padding:6px 0;color:#64748b;">Reason</td>
                                    <td style="padding:6px 0;text-align:right;">{{ $creditNote->reason }}</td>
                                </tr>
                                @endif
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;border-collapse:collapse;">
                                <thead>
                                    <tr style="background:#f8fafc;">
                                        <th align="left"  style="padding:10px;border-bottom:2px solid #1a2744;">Account</th>
                                        <th align="left"  style="padding:10px;border-bottom:2px solid #1a2744;">Description</th>
                                        <th align="right" style="padding:10px;border-bottom:2px solid #1a2744;">Qty</th>
                                        <th align="right" style="padding:10px;border-bottom:2px solid #1a2744;">Unit Price</th>
                                        <th align="right" style="padding:10px;border-bottom:2px solid #1a2744;">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($creditNote->items as $item)
                                    <tr>
                                        <td style="padding:10px;border-bottom:1px solid #e2e8f0;">{{ $item->ledger?->name ?? '—' }}</td>
                                        <td style="padding:10px;border-bottom:1px solid #e2e8f0;">{{ $item->description }}</td>
                                        <td align="right" style="padding:10px;border-bottom:1px solid #e2e8f0;">{{ number_format($item->quantity, 2) }}</td>
                                        <td align="right" style="padding:10px;border-bottom:1px solid #e2e8f0;">R {{ number_format($item->amount, 2) }}</td>
                                        <td align="right" style="padding:10px;border-bottom:1px solid #e2e8f0;">R {{ number_format($item->line_total, 2) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;margin-top:16px;">
                                <tr>
                                    <td style="padding:4px 10px;color:#64748b;" align="right">Sub-total</td>
                                    <td style="padding:4px 10px;width:120px;" align="right">R {{ number_format($creditNote->subtotal, 2) }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px 10px;color:#64748b;" align="right">VAT</td>
                                    <td style="padding:4px 10px;" align="right">R {{ number_format($creditNote->vat_amount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 10px;font-weight:bold;color:#1a2744;border-top:2px solid #1a2744;" align="right">TOTAL</td>
                                    <td style="padding:8px 10px;font-weight:bold;color:#1a2744;border-top:2px solid #1a2744;" align="right">R {{ number_format($creditNote->amount, 2) }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f8fafc;padding:20px 32px;color:#94a3b8;font-size:12px;text-align:center;">
                            This is an automated message from {{ $creditNote->organization?->name ?? config('app.name') }}.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
