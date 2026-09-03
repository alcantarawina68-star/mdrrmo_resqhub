<x-layouts.dashboard title="Announcements">
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-base font-semibold text-fg">Published announcements</h2>
                <span class="mono text-xs text-muted">{{ $announcements->total() }} total</span>
            </div>

            <div class="divide-y divide-border border border-border bg-surface">
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
                        <form method="POST" action="{{ route('dashboard.announcements.destroy', $announcement) }}" class="shrink-0" onsubmit="return confirm('Delete this announcement?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-tertiary text-danger">Delete</button>
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
            <form method="POST" action="{{ route('dashboard.announcements.store') }}" class="flex flex-col gap-4 border border-border bg-surface p-5">
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
    </div>
</x-layouts.dashboard>
