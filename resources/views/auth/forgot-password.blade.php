<x-layouts.guest title="Forgot password">
    <h1 class="text-xl font-semibold text-fg">Forgot password</h1>
    <p class="mt-1 text-sm text-muted">Enter your email and we'll send you a link to reset your password.</p>

    @if (session('status'))
        <div class="mt-4 border border-primary/30 bg-primary/10 px-4 py-3 text-sm text-primary" role="status">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mt-4 border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
        @csrf

        <div class="field">
            <label class="label" for="email">Email</label>
            <input id="email" type="email" name="email" class="input @error('email') border-danger/60 @enderror" value="{{ old('email') }}" required autofocus autocomplete="email">
            @error('email')
                <span class="mt-1 block text-xs text-danger">{{ $message }}</span>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-full">Send reset link</button>
    </form>

    <p class="mt-6 border-t border-border pt-4 text-sm text-muted">
        Remembered it? <a href="{{ route('login') }}" class="font-medium text-primary">Log in</a>.
    </p>
</x-layouts.guest>