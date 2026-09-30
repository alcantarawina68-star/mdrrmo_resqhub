<x-layouts.dashboard title="Users">
    <x-page-header description="Manage accounts, roles and account status for everyone using ResQHub." />

    <div class="grid gap-6 lg:grid-cols-3" x-data="usersPage()">
        <div class="lg:col-span-2">
            <form method="GET" action="{{ route('dashboard.users') }}" class="mb-4 flex items-end gap-2">
                <div class="field flex-1">
                    <label class="label sr-only" for="search">Search</label>
                    <input id="search" type="search" name="search" class="input" value="{{ request('search') }}" placeholder="Search by name or email">
                </div>
                <div class="field">
                    <label class="label sr-only" for="role">Role</label>
                    <select id="role" name="role" class="select">
                        <option value="">All roles</option>
                        @foreach ($roles as $value => $label)
                            @if ($value === \App\Enums\UserRole::Superadmin->value && ! $canManageSuperadmins)
                                @continue
                            @endif
                            <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-secondary">Filter</button>
            </form>

            <div class="divide-y divide-border rounded-lg border border-border bg-surface">
                @forelse ($users as $user)
                    <details class="group px-5 py-4">
                        <summary class="flex cursor-pointer list-none items-center gap-4">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-fg">{{ $user->name }}</p>
                                <p class="mono truncate text-xs text-muted">{{ $user->email }}</p>
                            </div>
                            <span class="chip chip-{{ $user->role->value }}">{{ $user->role->label() }}</span>
                            <span class="chip chip-{{ $user->status->value }}">{{ $user->status->label() }}</span>
                            @if (isset($activeSessionIds[$user->id]))
                                <span class="chip chip-active" title="Currently online with an active session">Online</span>
                            @endif
                            <span class="text-xs text-muted transition-transform duration-150 group-open:rotate-90">&rsaquo;</span>
                        </summary>

                        <form method="POST" action="{{ route('dashboard.users.update', $user) }}" class="mt-4 grid gap-4 border-t border-border pt-4 sm:grid-cols-2">
                            @csrf
                            <div class="field sm:col-span-2">
                                <label class="label" for="name-{{ $user->id }}">Name</label>
                                <input id="name-{{ $user->id }}" type="text" name="name" class="input" value="{{ $user->name }}">
                            </div>
                            <div class="field sm:col-span-2">
                                <label class="label" for="email-{{ $user->id }}">Email</label>
                                <input id="email-{{ $user->id }}" type="email" name="email" class="input" value="{{ $user->email }}">
                            </div>
                            <div class="field">
                                <label class="label" for="role-{{ $user->id }}">Role</label>
                                <select id="role-{{ $user->id }}" name="role" class="select" {{ $user->is(auth()->user()) || ($user->isSuperadmin() && ! $canManageSuperadmins) ? 'disabled' : '' }}>
                                    @foreach ($roles as $value => $label)
                                        @if ($value === \App\Enums\UserRole::Superadmin->value && ! $canManageSuperadmins)
                                            @continue
                                        @endif
                                        <option value="{{ $value }}" @selected($user->role->value === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label class="label" for="status-{{ $user->id }}">Status</label>
                                <select id="status-{{ $user->id }}" name="status" class="select" {{ $user->is(auth()->user()) || ($user->isSuperadmin() && ! $canManageSuperadmins) ? 'disabled' : '' }}>
                                    @foreach ($statuses as $value => $label)
                                        <option value="{{ $value }}" @selected($user->status->value === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field sm:col-span-2">
                                <label class="label" for="contact-{{ $user->id }}">Contact number</label>
                                <input id="contact-{{ $user->id }}" type="tel" name="contact_number" class="input" value="{{ $user->contact_number }}">
                            </div>
                            <div class="flex items-center gap-2 sm:col-span-2">
                                <button type="submit" class="btn btn-secondary">Save</button>
                                @if (! $user->is(auth()->user()) && ! ($user->isSuperadmin() && ! $canManageSuperadmins))
                                    <button type="submit" form="reset-{{ $user->id }}" class="btn btn-tertiary">Reset password</button>
                                    <button type="button" class="btn btn-tertiary text-danger"
                                        @click="confirmDelete('{{ $user->name }}', '{{ $user->id }}')"
                                        aria-haspopup="dialog">
                                        Delete
                                    </button>
                                @endif
                            </div>
                        </form>

                        @if (! $user->is(auth()->user()) && ! ($user->isSuperadmin() && ! $canManageSuperadmins))
                            <form id="reset-{{ $user->id }}" method="POST" action="{{ route('dashboard.users.reset-password', $user) }}" class="hidden">
                                @csrf
                            </form>
                            <form id="delete-{{ $user->id }}" method="POST" action="{{ route('dashboard.users.destroy', $user) }}" class="hidden">
                                @csrf
                                @method('DELETE')
                            </form>
                        @endif
                    </details>
                @empty
                    <p class="p-8 text-center text-sm text-muted">No users match the current filters.</p>
                @endforelse
            </div>

            <div class="mt-4">{{ $users->links() }}</div>
        </div>

        <div>
            <h2 class="mb-3 text-base font-semibold text-fg">Create user</h2>
            <form method="POST" action="{{ route('dashboard.users.store') }}" class="card flex flex-col gap-4 p-5">
                @csrf

                <div class="field">
                    <label class="label" for="name">Name</label>
                    <input id="name" type="text" name="name" class="input" value="{{ old('name') }}" required>
                </div>

                <div class="field">
                    <label class="label" for="email">Email</label>
                    <input id="email" type="email" name="email" class="input" value="{{ old('email') }}" required>
                </div>

                <div class="field">
                    <label class="label" for="password">Temporary password</label>
                    <input id="password" type="password" name="password" class="input" required>
                </div>

                <div class="field">
                    <label class="label" for="role">Role</label>
                    <select id="role" name="role" class="select" required>
                        @foreach ($roles as $value => $label)
                            @if ($value === \App\Enums\UserRole::Superadmin->value && ! $canManageSuperadmins)
                                @continue
                            @endif
                            <option value="{{ $value }}" @selected(old('role', 'community_user') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label class="label" for="status">Status</label>
                    <select id="status" name="status" class="select" required>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', 'active') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label class="label" for="contact_number">Contact number</label>
                    <input id="contact_number" type="tel" name="contact_number" class="input" value="{{ old('contact_number') }}">
                </div>

                <button type="submit" class="btn btn-primary w-full">Create user</button>
            </form>
        </div>
    </div>

    <template x-teleport="body">
        <div x-show="show" x-cloak x-transition.opacity
            class="fixed inset-0 z-[90] flex items-center justify-center bg-black/50 p-4">
            <div x-ref="dialog" x-show="show" x-transition @click.self="cancel()" @keydown.escape.window="cancel()" @keydown.tab="trap($event)"
                class="w-full max-w-md rounded-lg border border-border bg-surface p-6 shadow-lg" role="dialog"
                tabindex="-1" aria-modal="true" aria-labelledby="delete-user-dialog-title" aria-describedby="delete-user-dialog-description">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-danger/10 text-danger">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </span>
                    <div class="min-w-0">
                        <h2 id="delete-user-dialog-title" class="text-base font-semibold text-fg">Delete user</h2>
                        <p id="delete-user-dialog-description" class="mt-1 text-sm text-muted">
                            Are you sure you want to delete
                            <span class="font-medium text-fg" x-text="'“' + pendingName + '”'"></span>?
                            This action cannot be undone.
                        </p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="btn btn-tertiary min-h-11" @click="cancel()">Cancel</button>
                    <button type="button" class="btn btn-danger" @click="submit()">Delete</button>
                </div>
            </div>
        </div>
    </template>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('usersPage', () => ({
                ...ResqHub.confirmDialog(),
                pendingId: null,
                pendingName: '',
                confirmDelete(name, id) {
                    this.pendingId = id;
                    this.pendingName = name;
                    this.open();
                },
                cancel() {
                    this.close();
                    this.pendingId = null;
                },
                submit() {
                    if (!this.pendingId) return;
                    const form = document.getElementById('delete-' + this.pendingId);
                    if (form) form.submit();
                },
            }));
        });
    </script>
</x-layouts.dashboard>
