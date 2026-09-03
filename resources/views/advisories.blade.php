<x-layouts.app title="Advisories">
    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1>Advisories</h1>
                <p class="mt-1 text-sm text-muted">Official announcements from the MDRRMO. Check back during weather events.</p>
            </div>
            @auth
                <a href="{{ route('report.create') }}" class="btn btn-primary">Submit Report</a>
            @endauth
        </div>

        @if ($announcements->isEmpty())
            <div class="border border-border bg-surface p-8 text-center">
                <p class="text-sm text-muted">No active advisories. Check back during weather events.</p>
            </div>
        @else
            <div class="divide-y divide-border border border-border bg-surface">
                @foreach ($announcements as $announcement)
                    <article class="p-6">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="chip chip-{{ $announcement->severity->value }}">
                                {{ $announcement->severity->label() }}
                            </span>
                            <span class="chip chip-{{ $announcement->category->value }}">
                                {{ $announcement->category->label() }}
                            </span>
                            <span class="mono ml-auto text-xs text-muted">
                                {{ $announcement->published_at->format('M j, Y g:i A') }}
                            </span>
                        </div>
                        <h2 class="mt-3">{{ $announcement->title }}</h2>
                        <p class="mt-2 whitespace-pre-line text-sm text-fg/90">{{ $announcement->content }}</p>
                        <p class="mt-3 text-xs text-muted">
                            Posted by {{ $announcement->user?->name ?? 'MDRRMO' }}
                            @if ($announcement->expires_at)
                                · expires {{ $announcement->expires_at->format('M j, Y') }}
                            @endif
                        </p>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.app>
