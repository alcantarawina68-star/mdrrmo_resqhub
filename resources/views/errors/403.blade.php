<x-layouts.guest title="Access denied">
    <div class="text-center">
        <p class="mono text-5xl font-bold text-danger">403</p>
        <h1 class="mt-3 text-xl font-semibold text-fg">Access denied</h1>
        <p class="mt-2 text-sm text-muted">You do not have permission to view this page. Contact the MDRRMO if you believe this is a mistake.</p>
        <a href="{{ route('home') }}" class="btn btn-primary mt-6 w-full">Back to live map</a>
    </div>
</x-layouts.guest>