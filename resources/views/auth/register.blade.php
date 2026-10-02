<x-layouts.guest title="Register">
    <h1 class="text-xl font-semibold text-fg">Register</h1>
    <p class="mt-1 text-sm text-muted">Create an account to submit and track incident reports.</p>

    <x-error-summary />


    <form method="POST" action="{{ route('register.submit') }}" class="mt-6 space-y-4">
        @csrf

        <div class="field">
            <label class="label" for="name">Full name</label>
            <input id="name" type="text" name="name" class="input @error('name') border-danger/60 @enderror" value="{{ old('name') }}" required autofocus autocomplete="name">
            @error('name')
                <span class="mt-1 block text-xs text-danger">{{ $message }}</span>
            @enderror
        </div>

        <div class="field">
            <label class="label" for="email">Email</label>
            <input id="email" type="email" name="email" class="input @error('email') border-danger/60 @enderror" value="{{ old('email') }}" required autocomplete="email">
            @error('email')
                <span class="mt-1 block text-xs text-danger">{{ $message }}</span>
            @enderror
        </div>

        <div class="field">
            <label class="label" for="contact_number">Contact number <span class="normal-case">(optional)</span></label>
            <input id="contact_number" type="tel" name="contact_number" class="input @error('contact_number') border-danger/60 @enderror" value="{{ old('contact_number') }}" placeholder="0917 123 4567">
            @error('contact_number')
                <span class="mt-1 block text-xs text-danger">{{ $message }}</span>
            @enderror
        </div>

        <div class="field">
            <label class="label" for="barangay">Barangay <span class="normal-case">(optional)</span></label>
            <select id="barangay" name="barangay" class="select @error('barangay') border-danger/60 @enderror">
                <option value="">Select barangay</option>
                @foreach (\App\Support\CamalBarangays::all() as $barangay)
                    <option value="{{ $barangay }}" @selected(old('barangay') === $barangay)>{{ $barangay }}</option>
                @endforeach
            </select>
            @error('barangay')
                <span class="mt-1 block text-xs text-danger">{{ $message }}</span>
            @enderror
        </div>

        <x-password-field name="password" label="Password" autocomplete="new-password" required minlength="8" hint="Use at least 8 characters." />
        <x-password-field name="password_confirmation" label="Confirm password" autocomplete="new-password" required error-key="password" />

        <button type="submit" class="btn btn-primary w-full">Create account</button>
    </form>

    <p class="mt-6 border-t border-border pt-4 text-sm text-muted">
        Already registered? <a href="{{ route('login') }}" class="font-medium text-primary">Log in</a>.
    </p>
</x-layouts.guest>
