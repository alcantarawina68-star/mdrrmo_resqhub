<x-layouts.dashboard title="Overview">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-muted">Welcome back, {{ auth()->user()->name }}. Here is what needs attention today.</p>
        @if (auth()->user()->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Encoder))
            <a href="{{ route('dashboard.caller') }}" class="btn btn-primary">Encode Caller Report</a>
        @endif
    </div>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="card p-5">
            <p class="panel-title">Incidents today</p>
            <p class="mono mt-2 text-2xl font-semibold text-fg sm:text-3xl">{{ $today }}</p>
        </div>
        <div class="card p-5">
            <p class="panel-title">Pending verification</p>
            <p class="mono mt-2 text-2xl font-semibold text-warning sm:text-3xl">{{ $summary['pending'] }}</p>
        </div>
        <div class="card p-5">
            <p class="panel-title">Ongoing</p>
            <p class="mono mt-2 text-2xl font-semibold text-secondary sm:text-3xl">{{ $summary['active'] }}</p>
        </div>
        <div class="card p-5">
            <p class="panel-title">Resolved</p>
            <p class="mono mt-2 text-2xl font-semibold text-success sm:text-3xl">{{ $summary['resolved'] }}</p>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-base font-semibold text-fg">Latest incidents</h2>
                <a href="{{ route('dashboard.incidents') }}" class="btn btn-tertiary !px-0">View all</a>
            </div>

            <div class="border border-border bg-surface">
                <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="bg-bg dark:bg-surface">
                            <th class="table-head">Incident</th>
                            <th class="table-head">Barangay</th>
                            <th class="table-head hidden sm:table-cell">Priority</th>
                            <th class="table-head">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($latest as $incident)
                            <tr>
                                <td class="table-cell">
                                    <a href="{{ route('dashboard.incidents.show', $incident) }}" class="font-medium text-primary hover:underline">
                                        {{ $incident->incident_type->label() }}
                                    </a>
                                    <p class="mono text-xs text-muted">{{ $incident->incident_number }}</p>
                                </td>
                                <td class="table-cell">{{ $incident->location_label ?? '—' }}</td>
                                <td class="table-cell hidden sm:table-cell"><x-priority-badge :priority="$incident->priority" /></td>
                                <td class="table-cell"><x-status-chip :status="$incident->status" /></td>
                            </tr>
                        @empty
                            <tr><td class="table-cell text-center text-muted" colspan="4">No incidents reported yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <div>
            <h2 class="mb-3 text-base font-semibold text-fg">By status</h2>
            <div class="divide-y divide-border border border-border bg-surface">
                @foreach ($summary['by_status'] as $row)
                    <div class="flex items-center justify-between px-4 py-3 text-sm">
                        <span class="flex items-center gap-2">
                            <x-status-chip :status="$row['value']" />
                        </span>
                        <span class="mono font-semibold text-fg">{{ $row['total'] }}</span>
                    </div>
                @endforeach
            </div>

            @if ($summary['average_response_minutes'] !== null)
                <p class="mt-4 text-xs text-muted">
                    Average verification time:
                    <span class="mono font-semibold text-fg">{{ $summary['average_response_minutes'] }} min</span>
                </p>
            @endif
        </div>
    </div>
</x-layouts.dashboard>
