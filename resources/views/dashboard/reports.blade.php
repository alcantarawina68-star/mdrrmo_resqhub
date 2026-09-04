<x-layouts.dashboard title="Reports & Analytics">
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
        <div class="card p-3 sm:p-5"><p class="panel-title">Pending</p><p class="mono mt-2 text-2xl font-semibold text-warning sm:text-3xl">{{ $summary['pending'] }}</p></div>
        <div class="card p-3 sm:p-5"><p class="panel-title">Ongoing</p><p class="mono mt-2 text-2xl font-semibold text-secondary sm:text-3xl">{{ $summary['active'] }}</p></div>
        <div class="card p-3 sm:p-5"><p class="panel-title">Resolved</p><p class="mono mt-2 text-2xl font-semibold text-success sm:text-3xl">{{ $summary['resolved'] }}</p></div>
        <div class="card p-3 sm:p-5">
            <p class="panel-title">Avg verify</p>
            <p class="mono mt-2 text-xl font-semibold text-fg sm:text-2xl">{{ $summary['average_response_minutes'] ?? '—' }}<span class="text-xs font-normal text-muted"> min</span></p>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="border border-border bg-surface p-5">
            <p class="panel-title mb-4">Daily trend (last 30 days)</p>
            <div class="flex h-40 gap-px sm:h-48 sm:gap-1">
                @php($max = max(1, collect($trend)->max('total')))
                @foreach ($trend as $day)
                    <div class="group relative flex-1 flex flex-col justify-end" title="{{ $day['label'] }}: {{ $day['total'] }}">
                        <div class="bg-primary/70 transition-colors duration-150 hover:bg-primary" style="height: {{ max(2, round(($day['total'] / $max) * 100)) }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex justify-between text-xs text-muted">
                <span class="mono">{{ $trend[0]['label'] ?? '' }}</span>
                <span class="mono">{{ $trend[count($trend) - 1]['label'] ?? '' }}</span>
            </div>
        </div>

        <div class="border border-border bg-surface p-5">
            <p class="panel-title mb-4">By barangay</p>
            <div class="divide-y divide-border">
                @forelse ($barangays as $row)
                    <div class="flex items-center justify-between gap-2 py-2 text-sm sm:gap-4">
                        <span class="truncate">{{ $row['barangay'] }}</span>
                        <div class="flex items-center gap-3">
                            <div class="h-1.5 w-20 bg-bg dark:bg-surface sm:w-32"><div class="h-full bg-secondary" style="width: {{ max(1, round(($row['total'] / max(1, $barangays[0]['total'])) * 100)) }}%"></div></div>
                            <span class="mono w-8 text-right text-muted">{{ $row['total'] }}</span>
                        </div>
                    </div>
                @empty
                    <p class="py-4 text-sm text-muted">No incidents in this period.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="border border-border bg-surface p-5">
            <p class="panel-title mb-4">By type</p>
            <div class="divide-y divide-border">
                @foreach ($summary['by_type'] as $row)
                    <div class="flex items-center justify-between py-2 text-sm">
                        <span>{{ $row['label'] }}</span>
                        <span class="mono font-semibold text-fg">{{ $row['total'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="border border-border bg-surface p-5">
            <p class="panel-title mb-4">By source & priority</p>
            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <p class="label mb-2">Source</p>
                    <div class="divide-y divide-border">
                        @foreach ($summary['by_source'] as $row)
                            <div class="flex items-center justify-between py-1.5 text-sm">
                                <span>{{ $row['label'] }}</span>
                                <span class="mono font-semibold text-fg">{{ $row['total'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div>
                    <p class="label mb-2">Priority</p>
                    <div class="divide-y divide-border">
                        @foreach ($summary['by_priority'] as $row)
                            <div class="flex items-center justify-between py-1.5 text-sm">
                                <span>{{ $row['label'] }}</span>
                                <span class="mono font-semibold text-fg">{{ $row['total'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.dashboard>
