<x-layouts.guest title="Log in">
    <h1 class="text-xl font-semibold text-fg">Log in</h1>
    <p class="mt-1 text-sm text-muted">Access your reports or the operations dashboard.</p>

    <form method="POST" action="{{ route('login.submit') }}" class="mt-6 space-y-4">
        @csrf

        <div class="field">
            <label class="label" for="email">Email</label>
            <input id="email" type="email" name="email" class="input" value="{{ old('email') }}" required autofocus autocomplete="email">
        </div>

        <div class="field">
            <label class="label" for="password">Password</label>
            <input id="password" type="password" name="password" class="input" required autocomplete="current-password">
        </div>

        <label class="flex cursor-pointer items-center gap-2 text-sm">
            <input type="checkbox" name="remember" class="h-4 w-4 accent-[var(--color-primary)]">
            Remember me
        </label>

        <button type="submit" class="btn btn-primary w-full">Log in</button>
    </form>

    <p class="mt-6 border-t border-border pt-4 text-sm text-muted">
        No account yet? <a href="{{ route('register') }}" class="font-medium text-primary">Register as a community user</a>.
    </p>
</x-layouts.guest>
