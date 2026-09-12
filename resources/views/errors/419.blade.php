<x-layouts.guest title="Session expired">
    <div class="text-center">
        <p class="mono text-5xl font-bold text-warning">419</p>
        <h1 class="mt-3 text-xl font-semibold text-fg">Session expired</h1>
        <p class="mt-2 text-sm text-muted">Your session has expired. Refresh to get a fresh session, then try again.</p>
        <a href="{{ url()->current() }}" class="btn btn-primary mt-6 w-full">Reload page</a>
    </div>
</x-layouts.guest>