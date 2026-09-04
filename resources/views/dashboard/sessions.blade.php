<x-layouts.dashboard title="Active Sessions">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-muted">Monitor and end active device sessions across the platform. Ending a session logs that device out immediately.</p>
    </div>

    @if ($sessions->isEmpty())
        <div class="border border-border bg-surface p-8 text-center text-sm text-muted">
            There are no active sessions right now.
        </div>
    @else
        <div class="overflow-x-auto border border-border bg-surface">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead>
                    <tr>
                        <th class="table-head">User</th>
                        <th class="table-head">Device</th>
                        <th class="table-head">Last activity</th>
                        <th class="table-head text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @php
                        $perUser = $sessions->groupBy('user_id');
                        $currentUserId = (int) auth()->id();
                    @endphp
                    @foreach ($perUser as $userId => $userSessions)
                        @php
                            $first = $userSessions->first();
                        @endphp
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium text-fg">{{ $first->name }}</p>
                                <p class="mono text-xs text-muted">{{ $first->email }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="chip chip-{{ $first->role }}">{{ \App\Enums\UserRole::from($first->role)->label() }}</span>
                                <span class="ml-2 text-xs text-muted">{{ $userSessions->count() }} device{{ $userSessions->count() !== 1 ? 's' : '' }}</span>
                            </td>
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3 text-right">
                                @if ((int) $first->user_id !== $currentUserId)
                                    <form method="POST" action="{{ route('dashboard.sessions.logout-user', $userId) }}" class="inline" onsubmit="return confirm('Log out this user from all devices?')">
                                        @csrf
                                        <button type="submit" class="btn btn-tertiary text-danger">Log out all devices</button>
                                    </form>
                                @endif
                            </td>
                        </tr>

                        @foreach ($userSessions as $session)
                            <tr class="bg-bg/40">
                                <td class="px-4 py-2 text-xs text-muted" colspan="2">
                                    <span class="mono">{{ $session->session_id }}</span>
                                    @if ($session->session_id === request()->session()->getId())
                                        <span class="chip chip-active ml-2">This device</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-xs text-muted">
                                    {{ \Illuminate\Support\Carbon::createFromTimestamp($session->last_activity)->diffForHumans() }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    @if ($session->session_id !== request()->session()->getId())
                                        <form method="POST" action="{{ route('dashboard.sessions.terminate', $session->session_id) }}" class="inline" onsubmit="return confirm('End this device session?')">
                                            @csrf
                                            <button type="submit" class="btn btn-tertiary text-danger">End session</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.dashboard>
