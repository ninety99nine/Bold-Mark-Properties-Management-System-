<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $communication->subject ?? 'Communication' }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #ececec;
            font-family: Arial, Helvetica, sans-serif;
            color: #4a4a4a;
            font-size: 14px;
        }
        .toolbar {
            padding: 12px 16px;
            display: flex;
            gap: 14px;
            align-items: center;
        }
        .toolbar button {
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            line-height: 0;
        }
        .toolbar svg { width: 28px; height: 28px; }
        .meta {
            padding: 4px 24px 20px;
            line-height: 1.7;
        }
        .meta strong { color: #333; }
        .email-card {
            background: #f7f7f7;
            border: 1px solid #e0e0e0;
            margin: 0 24px 40px;
            padding: 28px;
            max-width: 860px;
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()" title="Print">
            <svg viewBox="0 0 24 24" fill="none" stroke="#3f51b5" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        </button>
        <button type="button" onclick="window.print()" title="Save as PDF">
            <svg viewBox="0 0 24 24" fill="none" stroke="#c0392b" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><text x="7" y="18" font-size="6" fill="#c0392b" stroke="none">PDF</text></svg>
        </button>
    </div>

    <div class="meta">
        <div><strong>From:</strong> {{ $communication->from_name }} ({{ $communication->from_email }})</div>
        <div><strong>To:</strong> {{ $recipient->recipient_name }} ({{ $recipient->recipient_email }});</div>
        <div><strong>Subject:</strong> {{ $communication->subject }}</div>
        <div><strong>Date Sent:</strong> {{ $communication->created_at?->format('Y-m-d H:i') }}</div>
    </div>

    <div class="email-card">
        {!! $renderedBody !!}
    </div>
</body>
</html>
