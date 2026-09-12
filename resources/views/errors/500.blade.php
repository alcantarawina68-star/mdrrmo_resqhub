<x-layouts.guest title="Something went wrong">
    <div class="text-center">
        <p class="mono text-5xl font-bold text-danger">500</p>
        <h1 class="mt-3 text-xl font-semibold text-fg">Something went wrong</h1>
        <p class="mt-2 text-sm text-muted">An unexpected error occurred. Please try again in a moment.</p>
        <a href="{{ route('home') }}" class="btn btn-primary mt-6 w-full">Back to live map</a>
    </div>
</x-layouts.guest>