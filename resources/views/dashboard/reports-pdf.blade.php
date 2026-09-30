<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Incident Report - ResQHub</title>
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
        <h1>Incident Report</h1>
        <div class="muted">ResQHub · {{ site_setting('agency_short_name') }} · {{ site_setting('municipality') }}</div>
    </div>

    <p class="meta">
        @if ($from && $to)
            Period: {{ \Illuminate\Support\Carbon::parse($from)->format('M j, Y') }} – {{ \Illuminate\Support\Carbon::parse($to)->format('M j, Y') }}
        @else
            Period: All time
        @endif
        &nbsp;·&nbsp; Generated {{ $generatedAt->format('M j, Y g:i A') }} &nbsp;·&nbsp; Records: {{ $incidents->count() }}
    </p>

    @if ($incidents->isEmpty())
        <p class="empty">No incidents found in this period.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Incident No.</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Source</th>
                    <th>Reporter</th>
                    <th>Reported At</th>
                    <th>Verified At</th>
                    <th>Resolved At</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($incidents as $incident)
                    <tr>
                        <td>{{ $incident->incident_number }}</td>
                        <td>{{ $incident->incident_type?->label() }}</td>
                        <td>{{ $incident->status?->label() }}</td>
                        <td>{{ $incident->location_label ?? '—' }}</td>
                        <td>{{ $incident->source?->label() }}</td>
                        <td>{{ $incident->is_anonymous ? 'Anonymous' : ($incident->reporter?->name ?? '—') }}</td>
                        <td>{{ $incident->reported_at?->format('M j, Y g:i A') }}</td>
                        <td>{{ $incident->verified_at?->format('M j, Y g:i A') ?? '—' }}</td>
                        <td>{{ $incident->resolved_at?->format('M j, Y g:i A') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="footer">This report was generated from the ResQHub Incident Reporting System.</p>
</body>
</html>
