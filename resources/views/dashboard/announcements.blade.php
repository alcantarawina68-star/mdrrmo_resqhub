<x-layouts.dashboard title="Announcements">
    <x-page-header description="Publish official advisories for the public map and advisories page." />

    <div class="grid gap-6 lg:grid-cols-3" x-data="announcementsPage()">
        <div class="lg:col-span-2">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-base font-semibold text-fg">Published announcements</h2>
                <div class="flex items-center gap-2">
                    <span class="mono text-xs text-muted">{{ $announcements->total() }} total</span>
                    <a href="{{ route('dashboard.announcements.export') }}" class="btn btn-tertiary !px-2 !py-1 text-xs">Export CSV</a>
                    <a href="{{ route('dashboard.announcements.export.pdf') }}" class="btn btn-tertiary !px-2 !py-1 text-xs">Export PDF</a>
                </div>
            </div>

            <div class="divide-y divide-border rounded-lg border border-border bg-surface">
                @forelse ($announcements as $announcement)
                    <article class="flex items-start justify-between gap-4 px-5 py-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="chip chip-{{ $announcement->severity->value }}">{{ $announcement->severity->label() }}</span>
                                <span class="chip chip-{{ $announcement->category->value }}">{{ $announcement->category->label() }}</span>
                                <span class="mono ml-auto text-xs text-muted">{{ $announcement->published_at->format('M j, Y g:i A') }}</span>
                            </div>
                            <h3 class="mt-2 font-semibold text-fg">{{ $announcement->title }}</h3>
                            <p class="mt-1 line-clamp-2 text-sm text-muted">{{ $announcement->content }}</p>
                            @if ($announcement->expires_at)
                                <p class="mt-1 text-xs text-muted">Expires {{ $announcement->expires_at->format('M j, Y') }}</p>
                            @endif
                        </div>
                        <button type="button" class="btn btn-tertiary shrink-0 text-danger"
                            @click="confirmDelete({{ $announcement->id }}, '{{ $announcement->title }}')"
                            aria-haspopup="dialog">
                            Delete
                        </button>
                        <form id="delete-announcement-{{ $announcement->id }}" method="POST"
                            action="{{ route('dashboard.announcements.destroy', $announcement) }}" class="hidden">
                            @csrf
                            @method('DELETE')
                        </form>
                    </article>
                @empty
                    <p class="p-8 text-center text-sm text-muted">No announcements published yet.</p>
                @endforelse
            </div>

            <div class="mt-4">{{ $announcements->links() }}</div>
        </div>

        <div>
            <h2 class="mb-3 text-base font-semibold text-fg">Publish an announcement</h2>
            <form method="POST" action="{{ route('dashboard.announcements.store') }}" class="card flex flex-col gap-4 p-5">
                @csrf

                <div class="field">
                    <label class="label" for="title">Title</label>
                    <input id="title" type="text" name="title" class="input" value="{{ old('title') }}" required>
                </div>

                <div class="field">
                    <label class="label" for="category">Category</label>
                    <select id="category" name="category" class="select" required>
                        @foreach ($categories as $value => $label)
                            <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label class="label" for="severity">Severity</label>
                    <select id="severity" name="severity" class="select" required>
                        @foreach ($severities as $value => $label)
                            <option value="{{ $value }}" @selected(old('severity') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label class="label" for="content">Content</label>
                    <textarea id="content" name="content" rows="6" class="textarea" required>{{ old('content') }}</textarea>
                </div>

                <div class="field">
                    <label class="label" for="expires_at">Expires at <span class="normal-case">(optional)</span></label>
                    <input id="expires_at" type="datetime-local" name="expires_at" class="input" value="{{ old('expires_at') }}">
                </div>

                <button type="submit" class="btn btn-primary w-full">Publish</button>
            </form>
        </div>

        <template x-teleport="body">
            <div x-show="show" x-cloak x-transition.opacity
                class="fixed inset-0 z-[90] flex items-center justify-center bg-black/50 p-4">
                <div x-ref="dialog" x-show="show" x-transition @click.self="cancel()" @keydown.escape.window="cancel()" @keydown.tab="trap($event)"
                    class="w-full max-w-md rounded-lg border border-border bg-surface p-6 shadow-lg" role="dialog"
                    tabindex="-1" aria-modal="true" aria-labelledby="delete-dialog-title" aria-describedby="delete-dialog-description">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-danger/10 text-danger">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </span>
                        <div class="min-w-0">
                            <h2 id="delete-dialog-title" class="text-base font-semibold text-fg">Delete announcement</h2>
                            <p id="delete-dialog-description" class="mt-1 text-sm text-muted">
                                Are you sure you want to delete
                                <span class="font-medium text-fg" x-text="'“' + pendingTitle + '”'"></span>?
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
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('announcementsPage', () => ({
                ...ResqHub.confirmDialog(),
                pendingId: null,
                pendingTitle: '',
                confirmDelete(id, title) {
                    this.pendingId = id;
                    this.pendingTitle = title;
                    this.open();
                },
                cancel() {
                    this.close();
                    this.pendingId = null;
                },
                cancel() {
                    this.show = false;
                    this.pendingId = null;
                },
                submit() {
                    if (!this.pendingId) return;
                    const form = document.getElementById('delete-announcement-' + this.pendingId);
                    if (form) form.submit();
                },
            }));
        });
    </script>
</x-layouts.dashboard>
