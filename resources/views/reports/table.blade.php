<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} - ResQHub</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; margin: 0; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .header { border-bottom: 2px solid #111827; padding-bottom: 10px; margin-bottom: 14px; }
        .header .muted { color: #6b7280; font-size: 10px; }
        .meta { margin: 0 0 14px; font-size: 10px; color: #374151; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #d1d5db; padding: 5px 6px; text-align: left; vertical-align: top; word-wrap: break-word; }
        th { background: #f3f4f6; font-size: 9px; text-transform: uppercase; letter-spacing: 0.3px; }
        .right { text-align: right; }
        .muted { color: #6b7280; }
        .empty { text-align: center; color: #6b7280; padding: 24px 0; }
        .footer { margin-top: 14px; font-size: 9px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        <div class="muted">ResQHub &middot; {{ site_setting('agency_short_name') }} &middot; {{ site_setting('municipality') }}</div>
    </div>

    <p class="meta">
        @if ($from && $to)
            Period: {{ \Illuminate\Support\Carbon::parse($from)->format('M j, Y') }} &ndash; {{ \Illuminate\Support\Carbon::parse($to)->format('M j, Y') }}
        @elseif ($from)
            Period: from {{ \Illuminate\Support\Carbon::parse($from)->format('M j, Y') }}
        @elseif ($to)
            Period: up to {{ \Illuminate\Support\Carbon::parse($to)->format('M j, Y') }}
        @else
            Period: All time
        @endif
        &nbsp;&middot;&nbsp; Generated {{ $generatedAt->format('M j, Y g:i A') }} &nbsp;&middot;&nbsp; Records: {{ $recordCount }}
        @if ($truncated)
            &nbsp;&middot;&nbsp; <strong>Showing the first {{ $recordCount }} records. Narrow the filters or use the CSV export for the full set.</strong>
        @endif
    </p>

    @if (empty($rows))
        <p class="empty">{{ $emptyMessage }}</p>
    @else
        <table>
            <thead>
                <tr>
                    @foreach ($headings as $heading)
                        <th>{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="footer">This report was generated from the ResQHub Incident Reporting System.</p>
</body>
</html>