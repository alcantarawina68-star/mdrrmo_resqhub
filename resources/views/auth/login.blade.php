<x-layouts.guest title="Log in">
    <h1 class="text-xl font-semibold text-fg">Log in</h1>
    <p class="mt-1 text-sm text-muted">Access your reports or the operations dashboard.</p>

    <x-status-message />
    <x-error-summary />

    <form method="POST" action="{{ route('login.submit') }}" class="mt-6 space-y-4">
        @csrf

        <div class="field">
            <label class="label" for="email">Email</label>
            <input id="email" type="email" name="email" class="input @error('email') border-danger/60 @enderror" value="{{ old('email') }}" required autofocus autocomplete="email"
                @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')
                <span id="email-error" class="mt-1 block text-xs text-danger">{{ $message }}</span>
            @enderror
        </div>

        <x-password-field name="password" label="Password" autocomplete="current-password" required autofocus />

        <button type="submit" class="btn btn-primary w-full">Log in</button>
    </form>

    <p class="mt-6 border-t border-border pt-4 text-sm text-muted">
        <a href="{{ route('password.request') }}" class="font-medium text-primary">Forgot your password?</a>
    </p>

    <p class="mt-2 text-sm text-muted">
        No account yet? <a href="{{ route('register') }}" class="font-medium text-primary">Register as a community user</a>.
    </p>
</x-layouts.guest>
