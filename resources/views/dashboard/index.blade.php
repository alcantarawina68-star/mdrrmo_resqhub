<x-layouts.dashboard title="Overview">
    @if (isset($analytics))
        <div class="mb-6">
            <p class="text-sm text-muted">Welcome back, {{ auth()->user()->name }}. Here is a snapshot of your users and active sessions.</p>
        </div>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="card p-5">
                <p class="panel-title">Total users</p>
                <p class="mono mt-2 text-2xl font-semibold text-fg sm:text-3xl">{{ $analytics['users']['total'] }}</p>
            </div>
            <div class="card p-5">
                <p class="panel-title">Active users</p>
                <p class="mono mt-2 text-2xl font-semibold text-success sm:text-3xl">{{ $analytics['users']['active'] }}</p>
            </div>
            <div class="card p-5">
                <p class="panel-title">Suspended</p>
                <p class="mono mt-2 text-2xl font-semibold text-danger sm:text-3xl">{{ $analytics['users']['suspended'] }}</p>
            </div>
            <div class="card p-5">
                <p class="panel-title">New users · 30d</p>
                <p class="mono mt-2 text-2xl font-semibold text-secondary sm:text-3xl">{{ $analytics['users']['new_30_days'] }}</p>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-fg">Users by role</h2>
                    <a href="{{ route('dashboard.users') }}" class="btn btn-tertiary !px-0">View all</a>
                </div>

                <div class="divide-y divide-border border border-border bg-surface">
                    @foreach ($analytics['users']['by_role'] as $row)
                        <div class="flex items-center justify-between px-4 py-3 text-sm">
                            <span class="text-fg">{{ $row['label'] }}</span>
                            <span class="mono font-semibold text-fg">{{ $row['total'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div>
                <h2 class="mb-3 text-base font-semibold text-fg">Online by role</h2>
                <div class="divide-y divide-border border border-border bg-surface">
                    @foreach ($analytics['sessions']['online_by_role'] as $row)
                        <div class="flex items-center justify-between px-4 py-3 text-sm">
                            <span class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-success"></span>
                                <span class="text-fg">{{ $row['label'] }}</span>
                            </span>
                            <span class="mono font-semibold text-fg">{{ $row['total'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <div class="card p-5">
                <p class="panel-title">Online now</p>
                <p class="mono mt-2 text-2xl font-semibold text-success sm:text-3xl">{{ $analytics['sessions']['online_now'] }}</p>
            </div>
            <div class="card p-5">
                <p class="panel-title">Active sessions · last 24h</p>
                <p class="mono mt-2 text-2xl font-semibold text-fg sm:text-3xl">{{ $analytics['sessions']['sessions_24h'] }}</p>
            </div>
            <div class="card p-5">
                <p class="panel-title">Active sessions · last 7d</p>
                <p class="mono mt-2 text-2xl font-semibold text-secondary sm:text-3xl">{{ $analytics['sessions']['sessions_7d'] }}</p>
            </div>
        </div>

        <div class="mt-6">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-base font-semibold text-fg">Online users</h2>
                <a href="{{ route('dashboard.sessions') }}" class="btn btn-tertiary !px-0">Manage sessions</a>
            </div>

            <div class="overflow-x-auto border border-border bg-surface">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="bg-bg dark:bg-surface">
                            <th class="table-head">User</th>
                            <th class="table-head hidden sm:table-cell">Role</th>
                            <th class="table-head hidden md:table-cell">IP address</th>
                            <th class="table-head">Last activity</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($analytics['online_users'] as $session)
                            <tr>
                                <td class="table-cell">
                                    <span class="font-medium text-fg">{{ $session->name }}</span>
                                    <p class="text-xs text-muted">{{ $session->email }}</p>
                                </td>
                                <td class="table-cell hidden sm:table-cell">{{ \App\Enums\UserRole::tryFrom($session->role)?->label() ?? $session->role }}</td>
                                <td class="mono hidden text-xs text-muted md:table-cell">{{ $session->ip_address }}</td>
                                <td class="mono text-xs text-muted">{{ \Illuminate\Support\Carbon::createFromTimestamp($session->last_activity)->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td class="table-cell text-center text-muted" colspan="4">No users online right now.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
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
                <p class="panel-title">Under verification</p>
                <p class="mono mt-2 text-2xl font-semibold text-warning sm:text-3xl">{{ $summary['under_verification'] }}</p>
            </div>
            <div class="card p-5">
                <p class="panel-title">Ongoing</p>
                <p class="mono mt-2 text-2xl font-semibold text-secondary sm:text-3xl">{{ $summary['active'] }}</p>
            </div>
            <div class="card p-5">
                <p class="panel-title">Closed</p>
                <p class="mono mt-2 text-2xl font-semibold text-success sm:text-3xl">{{ $summary['closed'] }}</p>
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
                                <th class="table-head hidden sm:table-cell">Classification</th>
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
                                    <td class="table-cell hidden sm:table-cell"><x-classification-badge :classification="$incident->priority" /></td>
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
    @endif
</x-layouts.dashboard>
