<x-layouts.dashboard title="Incidents">
    <x-page-header description="Filter, review and manage every reported incident." />

    <div class="mb-5">
        <form method="GET" action="{{ route('dashboard.incidents') }}" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="field">
                <label class="label" for="status">Status</label>
                <select id="status" name="status" class="select">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="label" for="type">Type</label>
                <x-incident-type-select name="type" id="type" :required="false" placeholder="All types" :value="request('type')" />
            </div>
            <div class="field col-span-2 lg:col-span-1">
                <label class="label" for="search">Search</label>
                <input id="search" type="search" name="search" class="input" value="{{ request('search') }}" placeholder="ID, description, barangay">
            </div>
            <div class="col-span-2 flex items-end gap-2 lg:col-span-1">
                <button type="submit" class="btn btn-secondary">Filter</button>
                @if (request()->hasAny('status', 'type', 'search'))
                    <a href="{{ route('dashboard.incidents') }}" class="btn btn-tertiary">Clear</a>
                @endif
            </div>
            <div class="col-span-2 flex items-end gap-2 lg:col-span-1 lg:justify-end">
                @if (auth()->user()->hasRole(...\App\Enums\UserRole::operationsRoles()))
                    <a href="{{ route('dashboard.incidents.export', request()->query()) }}" class="btn btn-primary">Export CSV</a>
                    <a href="{{ route('dashboard.incidents.export.pdf', request()->query()) }}" class="btn btn-primary">Export PDF</a>
                @endif
            </div>
        </form>
    </div>

    <div class="card">
        <div class="overflow-x-auto">
        <table class="w-full table-auto">
            <thead>
                <tr>
                    <th class="table-head">Incident</th>
                    <th class="table-head hidden md:table-cell">Barangay</th>
                    <th class="table-head">Status</th>
                    <th class="table-head hidden lg:table-cell">Reported</th>
                    <th class="table-head"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($incidents as $incident)
                    <tr>
                        <td class="table-cell">
                            <p class="font-medium text-fg">{{ $incident->incident_type->label() }}</p>
                            <p class="mono text-xs text-muted">{{ $incident->incident_number }}</p>
                        </td>
                        <td class="table-cell hidden md:table-cell">{{ $incident->location_label ?? '—' }}</td>
                        <td class="table-cell"><x-status-chip :status="$incident->status" /></td>
                        <td class="table-cell hidden lg:table-cell"><span class="mono text-xs text-muted">{{ $incident->reported_at?->format('M j, g:i A') }}</span></td>
                        <td class="table-cell text-right">
                            <a href="{{ route('dashboard.incidents.show', $incident) }}" class="btn btn-tertiary !px-1">Manage</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="table-cell p-8 text-center text-muted" colspan="5">
                            No incidents match the current filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div class="mt-4">{{ $incidents->links() }}</div>
</x-layouts.dashboard>
