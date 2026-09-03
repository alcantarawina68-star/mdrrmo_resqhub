<x-layouts.app title="My Reports">
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1>My Reports</h1>
                <p class="mt-1 text-sm text-muted">Track the status of reports you submitted.</p>
            </div>
            <a href="{{ route('report.create') }}" class="btn btn-primary">Submit a Report</a>
        </div>

        @if ($incidents->isEmpty())
            <div class="border border-border bg-surface p-8 text-center">
                <p class="text-sm text-muted">You have not submitted any reports yet.</p>
                <a href="{{ route('report.create') }}" class="btn btn-primary mt-4">Submit your first report</a>
            </div>
        @else
            <div class="border border-border bg-surface">
                <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="bg-bg dark:bg-surface">
                            <th class="table-head">Incident</th>
                            <th class="table-head hidden sm:table-cell">Reported</th>
                            <th class="table-head">Status</th>
                            <th class="table-head hidden md:table-cell"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($incidents as $incident)
                            <tr>
                                <td class="table-cell">
                                    <p class="mono text-xs text-muted">{{ $incident->incident_number }}</p>
                                    <p class="mt-0.5 text-sm font-medium text-fg">{{ $incident->incident_type->label() }}</p>
                                    <p class="text-xs text-muted">{{ $incident->location_label ?? 'Location pending' }}</p>
                                </td>
                                <td class="table-cell hidden sm:table-cell">
                                    <p class="mono text-xs text-muted">{{ $incident->reported_at?->format('M j, g:i A') }}</p>
                                </td>
                                <td class="table-cell">
                                    <x-status-chip :status="$incident->status" />
                                </td>
                                <td class="table-cell hidden text-right md:table-cell">
                                    @if (in_array($incident->status->value, \App\Enums\IncidentStatus::publiclyVisibleValues(), true))
                                        <a href="{{ route('incidents.show', $incident) }}" class="btn btn-tertiary !px-1">View</a>
                                    @else
                                        <span class="text-xs text-muted">Not public yet</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>

            <div class="mt-4">{{ $incidents->links() }}</div>
        @endif
    </div>
</x-layouts.app>
