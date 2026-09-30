<x-layouts.guest title="Confirm Password">
    @if ($pending === null)
        <h1 class="text-xl font-semibold text-fg">Nothing to finish</h1>
        <p class="mt-1 text-sm text-muted">
            This request could not be completed automatically. Please go back and submit it again.
        </p>

        <x-error-summary />

        <a href="{{ route('dashboard') }}" class="btn btn-primary mt-6 w-full">Back to dashboard</a>
    @else
        <h1 class="text-xl font-semibold text-fg">Finishing your request</h1>
        <p class="mt-1 text-sm text-muted">Your password is confirmed. This will only take a moment.</p>

        <form method="POST" action="{{ $pending['action'] }}" class="mt-6" x-data x-init="$el.submit()">
            @csrf

            @foreach ($pending['payload'] as $key => $value)
                @if ($value !== null)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach

            @if (! in_array(strtoupper($pending['method']), ['GET', 'POST'], true))
                <input type="hidden" name="_method" value="{{ strtoupper($pending['method']) }}">
            @endif

            <button type="submit" class="btn btn-primary w-full">Continue</button>
        </form>
    @endif
</x-layouts.guest>
