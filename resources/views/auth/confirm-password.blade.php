<x-layouts.guest title="Confirm Password">
    <h1 class="text-xl font-semibold text-fg">Confirm your password</h1>
    <p class="mt-1 text-sm text-muted">This action is sensitive. Please re-enter your password to continue.</p>

    <x-error-summary />

    <form method="POST" action="{{ route('password.confirm.submit') }}" class="mt-6 space-y-4">
        @csrf

        <x-password-field name="password" label="Password" autocomplete="current-password" required autofocus />

        <button type="submit" class="btn btn-primary w-full">Confirm</button>
    </form>
</x-layouts.guest>
