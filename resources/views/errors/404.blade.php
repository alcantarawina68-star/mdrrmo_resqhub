<x-layouts.guest title="Page not found">
    <div class="text-center">
        <p class="mono text-5xl font-bold text-primary">404</p>
        <h1 class="mt-3 text-xl font-semibold text-fg">Page not found</h1>
        <p class="mt-2 text-sm text-muted">The page you are looking for doesn't exist or has been moved.</p>
        <a href="{{ route('home') }}" class="btn btn-primary mt-6 w-full">Back to live map</a>
    </div>
</x-layouts.guest>