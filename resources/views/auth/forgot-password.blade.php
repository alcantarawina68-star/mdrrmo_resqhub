<x-layouts.guest title="Forgot password">
    <h1 class="text-xl font-semibold text-fg">Forgot password</h1>
    <p class="mt-1 text-sm text-muted">Enter your email and we'll send you a link to reset your password.</p>

    <x-status-message />
    <x-error-summary />

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
        @csrf

        <div class="field">
            <label class="label" for="email">Email</label>
            <input id="email" type="email" name="email" class="input @error('email') border-danger/60 @enderror" value="{{ old('email') }}" required autofocus autocomplete="email"
                @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')
                <span id="email-error" class="mt-1 block text-xs text-danger">{{ $message }}</span>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-full">Send reset link</button>
    </form>

    <p class="mt-6 border-t border-border pt-4 text-sm text-muted">
        Remembered it? <a href="{{ route('login') }}" class="font-medium text-primary">Log in</a>.
    </p>
</x-layouts.guest>
