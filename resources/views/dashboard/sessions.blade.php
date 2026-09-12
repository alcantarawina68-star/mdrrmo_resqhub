<x-layouts.dashboard title="Active Sessions">
    <div x-data="sessionsPage()">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-muted">Monitor and end active device sessions across the platform. Ending a session logs that device out immediately.</p>
        </div>

        @if ($sessions->isEmpty())
            <div class="border border-border bg-surface p-8 text-center text-sm text-muted">
                There are no active sessions right now.
            </div>
        @else
            @php
                $perUser = $sessions->groupBy('user_id');
                $currentUserId = (int) auth()->id();
            @endphp

            <div class="space-y-4 md:hidden">
                @foreach ($perUser as $userId => $userSessions)
                    @php($first = $userSessions->first())
                    <div class="border border-border bg-surface p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-fg">{{ $first->name }}</p>
                                <p class="mono truncate text-xs text-muted">{{ $first->email }}</p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1">
                                <span class="chip chip-{{ $first->role }}">{{ \App\Enums\UserRole::from($first->role)->label() }}</span>
                                <span class="mono text-xs text-muted">{{ $userSessions->count() }} device{{ $userSessions->count() !== 1 ? 's' : '' }}</span>
                            </div>
                        </div>
                        <div class="mt-3 divide-y divide-border border-t border-border">
                            @foreach ($userSessions as $session)
                                <div class="flex items-center justify-between gap-3 py-2.5 text-xs">
                                    <div class="min-w-0">
                                        <p class="mono truncate text-muted">{{ $session->session_id }}</p>
                                        @if ($session->session_id === request()->session()->getId())
                                            <span class="chip chip-active mt-1">This device</span>
                                        @endif
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <span class="mono text-muted">{{ \Illuminate\Support\Carbon::createFromTimestamp($session->last_activity)->diffForHumans() }}</span>
                                        @if ($session->session_id !== request()->session()->getId())
                                            <button type="button" class="btn btn-tertiary px-0 text-danger"
                                                @click="confirmTerminate('{{ $first->name }}', '{{ $session->session_id }}')"
                                                aria-haspopup="dialog">
                                                End
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if ((int) $first->user_id !== $currentUserId)
                            <button type="button" class="btn btn-tertiary mt-3 px-0 text-danger"
                                @click="confirmLogoutUser('{{ $first->name }}', '{{ $userId }}')"
                                aria-haspopup="dialog">
                                Log out all devices
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="hidden overflow-x-auto border border-border bg-surface md:block">
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
                                        <form id="logout-user-{{ $userId }}" method="POST" action="{{ route('dashboard.sessions.logout-user', $userId) }}" class="hidden">
                                            @csrf
                                        </form>
                                        <button type="button" class="btn btn-tertiary text-danger"
                                            @click="confirmLogoutUser('{{ $first->name }}', '{{ $userId }}')"
                                            aria-haspopup="dialog">
                                            Log out all devices
                                        </button>
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
                                            <form id="terminate-{{ $session->session_id }}" method="POST" action="{{ route('dashboard.sessions.terminate', $session->session_id) }}" class="hidden">
                                                @csrf
                                            </form>
                                            <button type="button" class="btn btn-tertiary text-danger"
                                                @click="confirmTerminate('{{ $first->name }}', '{{ $session->session_id }}')"
                                                aria-haspopup="dialog">
                                                End session
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <template x-teleport="body">
            <div x-show="show" x-cloak x-transition.opacity
                class="fixed inset-0 z-[90] flex items-center justify-center bg-black/50 p-4">
                <div x-show="show" x-transition @click.self="cancel()" @keydown.escape.window="cancel()"
                    class="w-full max-w-md rounded-lg border border-border bg-surface p-6 shadow-lg" role="dialog"
                    aria-modal="true" aria-labelledby="session-dialog-title">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-danger/10 text-danger">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                        </span>
                        <div class="min-w-0">
                            <h2 id="session-dialog-title" class="text-base font-semibold text-fg" x-text="title"></h2>
                            <p class="mt-1 text-sm text-muted">
                                <span x-text="message"></span>
                                This action will end the session immediately.
                            </p>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" class="btn btn-tertiary" @click="cancel()">Cancel</button>
                        <button type="button" class="btn btn-danger" x-text="confirmLabel" @click="submit()"></button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('sessionsPage', () => ({
                show: false,
                title: '',
                message: '',
                confirmLabel: 'Confirm',
                pendingFormId: null,
                confirmLogoutUser(name, userId) {
                    this.pendingFormId = 'logout-user-' + userId;
                    this.title = 'Log out all devices';
                    this.message = 'Are you sure you want to log ' + name + ' out from all of their devices?';
                    this.confirmLabel = 'Log out';
                    this.show = true;
                },
                confirmTerminate(name, sessionId) {
                    this.pendingFormId = 'terminate-' + sessionId;
                    this.title = 'End device session';
                    this.message = 'Are you sure you want to end this device session for ' + name + '?';
                    this.confirmLabel = 'End session';
                    this.show = true;
                },
                cancel() {
                    this.show = false;
                    this.pendingFormId = null;
                },
                submit() {
                    if (!this.pendingFormId) return;
                    const form = document.getElementById(this.pendingFormId);
                    if (form) form.submit();
                },
            }));
        });
    </script>
</x-layouts.dashboard>
