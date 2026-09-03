<x-layouts.guest title="Register">
    <h1 class="text-xl font-semibold text-fg">Register</h1>
    <p class="mt-1 text-sm text-muted">Create an account to submit and track incident reports.</p>

    <form method="POST" action="{{ route('register.submit') }}" class="mt-6 space-y-4">
        @csrf

        <div class="field">
            <label class="label" for="name">Full name</label>
            <input id="name" type="text" name="name" class="input" value="{{ old('name') }}" required autofocus autocomplete="name">
        </div>

        <div class="field">
            <label class="label" for="email">Email</label>
            <input id="email" type="email" name="email" class="input" value="{{ old('email') }}" required autocomplete="email">
        </div>

        <div class="field">
            <label class="label" for="contact_number">Contact number <span class="normal-case">(optional)</span></label>
            <input id="contact_number" type="tel" name="contact_number" class="input" value="{{ old('contact_number') }}" placeholder="0917 123 4567">
        </div>

        <div class="field">
            <label class="label" for="barangay">Barangay <span class="normal-case">(optional)</span></label>
            <select id="barangay" name="barangay" class="select">
                <option value="">Select barangay</option>
                @foreach (\App\Support\CamalBarangays::all() as $barangay)
                    <option value="{{ $barangay }}" @selected(old('barangay') === $barangay)>{{ $barangay }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label class="label" for="password">Password</label>
            <input id="password" type="password" name="password" class="input" required autocomplete="new-password">
        </div>

        <div class="field">
            <label class="label" for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" class="input" required autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary w-full">Create account</button>
    </form>

    <p class="mt-6 border-t border-border pt-4 text-sm text-muted">
        Already registered? <a href="{{ route('login') }}" class="font-medium text-primary">Log in</a>.
    </p>
</x-layouts.guest>
