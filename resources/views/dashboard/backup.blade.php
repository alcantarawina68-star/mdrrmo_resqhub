<x-layouts.dashboard title="Backup">
    <x-page-header description="Download a restorable snapshot of the database and the uploaded evidence files." />

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card p-5">
            <p class="panel-title mb-3">What a backup contains</p>
            <ul class="space-y-2 text-sm text-muted">
                <li>
                    <span class="font-medium text-fg">database/dump.sql</span> &mdash; schema and data for
                    {{ count($preview['tables']) }} table{{ count($preview['tables']) === 1 ? '' : 's' }}.
                </li>
                <li>
                    <span class="font-medium text-fg">manifest.json</span> &mdash; when it was taken, who took it,
                    and the row count of every table.
                </li>
                <li>
                    <span class="font-medium text-fg">files/evidence</span> &mdash; {{ $preview['files'] }}
                    uploaded file{{ $preview['files'] === 1 ? '' : 's' }}
                    ({{ number_format($preview['bytes'] / 1024, 1) }} KB).
                </li>
            </ul>

            <p class="mt-4 border-t border-border pt-4 text-sm font-medium text-warning">
                A backup contains every user record, including password hashes and contact numbers.
                Store the download somewhere only you can reach.
            </p>

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <a href="{{ route('dashboard.backup.run') }}" class="btn btn-primary">Create backup</a>
                <p class="text-xs text-muted">You will be asked to confirm your password first.</p>
            </div>
        </div>

        <div class="card p-5">
            <p class="panel-title mb-3">Tables included</p>
            <div class="divide-y divide-border">
                @foreach ($preview['tables'] as $table)
                    <div class="flex items-center justify-between py-1.5 text-sm">
                        <span class="mono truncate">{{ $table['name'] }}</span>
                        <span class="mono ml-3 shrink-0 text-muted">{{ number_format($table['rows']) }}</span>
                    </div>
                @endforeach
            </div>

            <p class="panel-title mb-2 mt-5">Left out on purpose</p>
            <p class="mb-2 text-xs text-muted">
                Queues and cache rebuild themselves, and these tables hold credentials or live session state.
                Restoring a backup therefore signs every user out.
            </p>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($excluded as $table)
                    <span class="mono rounded bg-bg px-2 py-0.5 text-xs text-muted">{{ $table }}</span>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card mt-6 p-5">
        <p class="panel-title mb-2">Restoring</p>
        <p class="text-sm text-muted">
            Unzip the archive and import <span class="mono">database/dump.sql</span> into an empty database, then
            point the storage disk at the extracted <span class="mono">files/</span> folder. The dump wraps itself in
            <span class="mono">SET FOREIGN_KEY_CHECKS=0</span>, so table order does not matter.
        </p>
    </div>
</x-layouts.dashboard>