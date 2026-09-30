<x-layouts.guest title="Reset password">
    <h1 class="text-xl font-semibold text-fg">Reset password</h1>
    <p class="mt-1 text-sm text-muted">Choose a new password for {{ $email }}.</p>

    <x-error-summary />

    <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ $email }}">

        <x-password-field name="password" label="New password" autocomplete="new-password" required minlength="8" />
        <x-password-field name="password_confirmation" label="Confirm new password" autocomplete="new-password" required error-key="password" />

        <button type="submit" class="btn btn-primary w-full">Reset password</button>
    </form>

    <p class="mt-6 border-t border-border pt-4 text-sm text-muted">
        Changed your mind? <a href="{{ route('login') }}" class="font-medium text-primary">Back to log in</a>.
    </p>
</x-layouts.guest>
