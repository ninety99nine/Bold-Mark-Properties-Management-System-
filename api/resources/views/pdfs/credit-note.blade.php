<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Credit Note {{ $creditNote->credit_note_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #1a2744; font-size: 12px; margin: 0; padding: 0; }
        .wrap { padding: 32px 40px; }
        .header { border-bottom: 3px solid #e8a040; padding-bottom: 16px; margin-bottom: 24px; }
        .header .company { font-size: 20px; font-weight: bold; color: #1a2744; }
        .header .doc { font-size: 22px; font-weight: bold; color: #e8a040; text-align: right; }
        .meta-table { width: 100%; margin-bottom: 24px; }
        .meta-table td { vertical-align: top; width: 50%; }
        .label { color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; }
        .value { font-weight: bold; margin-bottom: 8px; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.lines th { background: #1a2744; color: #fff; padding: 8px 10px; text-align: left; font-size: 11px; }
        table.lines th.num, table.lines td.num { text-align: right; }
        table.lines td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; }
        .totals { width: 260px; margin-left: auto; margin-top: 16px; }
        .totals td { padding: 6px 10px; }
        .totals .grand td { border-top: 2px solid #1a2744; font-weight: bold; color: #1a2744; font-size: 14px; }
        .muted { color: #64748b; }
    </style>
</head>
<body>
    <div class="wrap">
        <table class="header" style="width:100%;">
            <tr>
                <td class="company">{{ $organization?->name ?? config('app.name') }}</td>
                <td class="doc">CREDIT NOTE</td>
            </tr>
        </table>

        <table class="meta-table">
            <tr>
                <td>
                    <div class="label">Billed To</div>
                    <div class="value">{{ $billedTo->full_name ?? '—' }}</div>
                    @if($creditNote->unit)
                        <div class="muted">Unit {{ $creditNote->unit->unit_number }}@if($creditNote->unit->community), {{ $creditNote->unit->community->name }}@endif</div>
                    @endif
                </td>
                <td>
                    <div class="label">Credit Note No.</div>
                    <div class="value">{{ $creditNote->credit_note_number }}</div>
                    <div class="label">Date</div>
                    <div class="value">{{ $creditNote->credit_note_date?->format('d M Y') }}</div>
                    @if($creditNote->appliedInvoice)
                        <div class="label">Applied to Invoice</div>
                        <div class="value">{{ $creditNote->appliedInvoice->invoice_number }}</div>
                    @endif
                    @if($creditNote->reason)
                        <div class="label">Reason</div>
                        <div class="value">{{ $creditNote->reason }}</div>
                    @endif
                    @if($creditNote->reference)
                        <div class="label">Reference</div>
                        <div class="value">{{ $creditNote->reference }}</div>
                    @endif
                </td>
            </tr>
        </table>

        <table class="lines">
            <thead>
                <tr>
                    <th>Account</th>
                    <th>Description</th>
                    <th class="num">Qty</th>
                    <th class="num">Unit Price</th>
                    <th class="num">Tax</th>
                    <th class="num">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($creditNote->items as $item)
                <tr>
                    <td>{{ $item->ledger?->name ?? '—' }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="num">{{ number_format($item->quantity, 2) }}</td>
                    <td class="num">R {{ number_format($item->amount, 2) }}</td>
                    <td class="num">R {{ number_format($item->tax_amount, 2) }}</td>
                    <td class="num">R {{ number_format($item->line_total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            <tr>
                <td class="muted">Sub-total</td>
                <td class="num" style="text-align:right;">R {{ number_format($creditNote->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td class="muted">VAT</td>
                <td class="num" style="text-align:right;">R {{ number_format($creditNote->vat_amount, 2) }}</td>
            </tr>
            <tr class="grand">
                <td>TOTAL</td>
                <td style="text-align:right;">R {{ number_format($creditNote->amount, 2) }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
