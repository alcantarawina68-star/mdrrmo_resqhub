<x-layouts.dashboard title="Reports & Analytics">
    @php
        $maxTrend = max(1, collect($trend)->max('total'));
        $maxBarangay = max(1, $barangays[0]['total'] ?? 1);
        $peakDay = collect($trend)->sortByDesc('total')->first();
    @endphp

    <x-page-header description="Incident volume, verification time and breakdowns by location, type and source." />

    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <form method="GET" action="{{ route('dashboard.reports') }}" class="flex flex-wrap items-end gap-2">
            <div class="field">
                <label class="label" for="from">From</label>
                <input id="from" type="date" name="from" class="input" value="{{ $from }}">
            </div>
            <div class="field">
                <label class="label" for="to">To</label>
                <input id="to" type="date" name="to" class="input" value="{{ $to }}">
            </div>
            <button type="submit" class="btn btn-secondary">Apply</button>
            @if ($from || $to)
                <a href="{{ route('dashboard.reports') }}" class="btn btn-tertiary">Clear</a>
            @endif
        </form>
        <div class="flex items-center gap-2">
            <a href="{{ route('dashboard.reports.export', request()->query()) }}" class="btn btn-secondary">Export CSV</a>
            <a href="{{ route('dashboard.reports.export.pdf', request()->query()) }}" class="btn btn-secondary">Export PDF</a>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-5">
        <div class="card p-3 sm:p-5"><p class="panel-title">Total</p><p class="mono mt-2 text-2xl font-semibold text-fg sm:text-3xl">{{ $summary['total'] }}</p></div>
        <div class="card p-3 sm:p-5"><p class="panel-title">Under verification</p><p class="mono mt-2 text-2xl font-semibold text-warning sm:text-3xl">{{ $summary['under_verification'] }}</p></div>
        <div class="card p-3 sm:p-5"><p class="panel-title">Ongoing</p><p class="mono mt-2 text-2xl font-semibold text-secondary sm:text-3xl">{{ $summary['active'] }}</p></div>
        <div class="card p-3 sm:p-5"><p class="panel-title">Closed</p><p class="mono mt-2 text-2xl font-semibold text-success sm:text-3xl">{{ $summary['closed'] }}</p></div>
        <div class="card p-3 sm:p-5">
            <p class="panel-title">Avg verify</p>
            <p class="mono mt-2 text-xl font-semibold text-fg sm:text-2xl">{{ $summary['average_response_minutes'] ?? '—' }}<span class="text-xs font-normal text-muted"> min</span></p>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card p-5">
            <p class="panel-title mb-4">Daily trend (last 30 days)</p>
            @if (empty($trend))
                <p class="py-8 text-center text-sm text-muted">No incidents in this period.</p>
            @else
                <div class="flex h-40 gap-px sm:h-48 sm:gap-1" role="img"
                    aria-label="Daily incident totals for the last {{ count($trend) }} days. Peak: {{ $peakDay['label'] ?? 'n/a' }} with {{ $peakDay['total'] ?? 0 }} incidents.">
                    @foreach ($trend as $day)
                        <div class="group relative flex-1 flex flex-col justify-end" title="{{ $day['label'] }}: {{ $day['total'] }}">
                            <div class="rounded-sm bg-primary/70 transition-colors duration-150 hover:bg-primary"
                                style="height: {{ max(2, round(($day['total'] / $maxTrend) * 100)) }}%">
                                <span class="sr-only">{{ $day['label'] }}: {{ $day['total'] }} incidents</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
            <div class="mt-2 flex justify-between text-xs text-muted">
                <span class="mono">{{ $trend[0]['label'] ?? '' }}</span>
                <span class="mono">{{ $trend[count($trend) - 1]['label'] ?? '' }}</span>
            </div>
        </div>

        <div class="card p-5">
            <p class="panel-title mb-4">By barangay</p>
            <div class="divide-y divide-border">
                @forelse ($barangays as $row)
                    <div class="flex items-center justify-between gap-2 py-2 text-sm sm:gap-4">
                        <span class="truncate">{{ $row['barangay'] }}</span>
                        <div class="flex items-center gap-3">
                            <div class="h-1.5 w-20 rounded-full bg-bg sm:w-32" role="img"
                                aria-label="{{ $row['barangay'] }}: {{ $row['total'] }} incidents">
                                <div class="h-full rounded-full bg-secondary" style="width: {{ max(1, round(($row['total'] / $maxBarangay) * 100)) }}%"></div>
                            </div>
                            <span class="mono w-8 text-right text-muted">{{ $row['total'] }}</span>
                        </div>
                    </div>
                @empty
                    <p class="py-4 text-sm text-muted">No incidents in this period.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="card mt-6 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="panel-title">SMS delivery log</p>
                <p class="mt-1 text-sm text-muted">Every message the platform tried to send, with delivery status and failures.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('dashboard.sms.export') }}" class="btn btn-secondary">Export CSV</a>
                <a href="{{ route('dashboard.sms.export.pdf') }}" class="btn btn-secondary">Export PDF</a>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card p-5">
            <p class="panel-title mb-4">By type</p>
            <div class="space-y-4">
                @foreach ($summary['by_type'] as $group)
                    <div>
                        <p class="label mb-1">{{ $group['label'] }}</p>
                        <div class="divide-y divide-border">
                            @foreach ($group['types'] as $row)
                                <div class="flex items-center justify-between py-2 text-sm">
                                    <span>{{ $row['label'] }}</span>
                                    <span class="mono font-semibold text-fg">{{ $row['total'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card p-5">
            <p class="panel-title mb-4">By source</p>
            <div class="divide-y divide-border">
                @foreach ($summary['by_source'] as $row)
                    <div class="flex items-center justify-between py-1.5 text-sm">
                        <span>{{ $row['label'] }}</span>
                        <span class="mono font-semibold text-fg">{{ $row['total'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts.dashboard>
