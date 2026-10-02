<x-layouts.dashboard title="Overview">
    @if (isset($analytics))
        <x-page-header description="Welcome back, {{ auth()->user()->name }}. Here is a snapshot of your users and active sessions." />

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

                <div class="divide-y divide-border rounded-lg border border-border bg-surface">
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
                <div class="divide-y divide-border rounded-lg border border-border bg-surface">
                    @foreach ($analytics['sessions']['online_by_role'] as $row)
                        <div class="flex items-center justify-between px-4 py-3 text-sm">
                            <span class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-success" aria-hidden="true"></span>
                                <span class="text-fg">{{ $row['label'] }}</span>
                            </span>
                            <span class="mono font-semibold text-fg">{{ $row['total'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <x-charts.columns label="Signups (last 30 days)" :items="$analytics['signup_trend']" value-label="new users">
                <a href="{{ route('dashboard.users') }}" class="text-xs text-primary hover:underline">Manage users</a>
            </x-charts.columns>

            <x-charts.bars label="Role mix" :items="$analytics['users']['by_role']" value-label="users" />
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

            <div class="card overflow-x-auto">
                <table class="w-full table-auto">
                    <thead>
                        <tr>
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
        <x-page-header description="Welcome back, {{ auth()->user()->name }}. Here is what needs attention today.">
            <x-slot:actions>
                @if (auth()->user()->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Encoder))
                    <a href="{{ route('dashboard.caller') }}" class="btn btn-primary">Encode Caller Report</a>
                @endif
            </x-slot:actions>
        </x-page-header>

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

        @php
            $typeTotals = collect($summary['by_type'])
                ->map(fn (array $group) => [
                    'label' => $group['label'],
                    'total' => collect($group['types'])->sum('total'),
                ])
                ->sortByDesc('total')
                ->values()
                ->all();

            $sourceTotals = collect($summary['by_source'])
                ->map(fn (array $row) => ['label' => $row['label'], 'total' => $row['total']])
                ->all();
        @endphp

        <div class="mt-6">
            <x-charts.columns label="Incidents reported · last 30 days" :items="$trend" value-label="incidents">
                <a href="{{ route('dashboard.reports') }}" class="text-xs text-primary hover:underline">Full reports</a>
            </x-charts.columns>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <x-charts.bars label="By incident type" :items="$typeTotals" value-label="incidents" />
            <x-charts.bars label="By source" :items="$sourceTotals" value-label="reports" />
            <x-charts.bars label="Top barangays" :items="$barangays" value-label="incidents" />
        </div>

        @if ($runsOperations)
            <div class="mt-6">
                <x-charts.bars label="Open incidents by assigned unit" :items="$units" value-label="open incidents"
                    empty="No open incidents right now." />
            </div>
        @endif

        <div class="mt-6 grid items-start gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-fg">Latest incidents</h2>
                    <a href="{{ route('dashboard.incidents') }}" class="btn btn-tertiary !px-0">View all</a>
                </div>

                <div class="card">
                    <div class="overflow-x-auto">
                    <table class="w-full table-auto">
                        <thead>
                            <tr>
                                <th class="table-head">Incident</th>
                                <th class="table-head">Barangay</th>
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
                                    <td class="table-cell"><x-status-chip :status="$incident->status" /></td>
                                </tr>
                            @empty
                                <tr><td class="table-cell text-center text-muted" colspan="3">No incidents reported yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>

            <div>
                {{-- Same heading row and card as the incidents panel, so the two
                     columns line up instead of the taller button offsetting them. --}}
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-fg">By status</h2>
                    <a href="{{ route('dashboard.incidents') }}" class="btn btn-tertiary !px-0">View all</a>
                </div>

                <div class="card divide-y divide-border">
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
