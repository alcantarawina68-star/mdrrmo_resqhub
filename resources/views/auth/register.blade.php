<x-layouts.guest title="Register">
    <h1 class="text-xl font-semibold text-fg">Register</h1>
    <p class="mt-1 text-sm text-muted">Create an account to submit and track incident reports.</p>

    @if ($errors->any())
        <div class="mt-4 border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

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

        <div class="field">
            <label class="label" for="password">Password</label>
            <div class="relative" x-data="{ show: false }">
                <input id="password" :type="show ? 'text' : 'password'" name="password" class="input pr-11 @error('password') border-danger/60 @enderror" required autocomplete="new-password">
                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-muted hover:text-fg" aria-label="Toggle password visibility" tabindex="-1">
                    <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                    <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                </button>
            </div>
            @error('password')
                <span class="mt-1 block text-xs text-danger">{{ $message }}</span>
            @enderror
        </div>

        <div class="field">
            <label class="label" for="password_confirmation">Confirm password</label>
            <div class="relative" x-data="{ show: false }">
                <input id="password_confirmation" :type="show ? 'text' : 'password'" name="password_confirmation" class="input pr-11 @error('password') border-danger/60 @enderror" required autocomplete="new-password">
                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-muted hover:text-fg" aria-label="Toggle password visibility" tabindex="-1">
                    <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                    <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                </button>
            </div>
            @error('password')
                <span class="mt-1 block text-xs text-danger">{{ $message }}</span>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-full">Create account</button>
    </form>

    <p class="mt-6 border-t border-border pt-4 text-sm text-muted">
        Already registered? <a href="{{ route('login') }}" class="font-medium text-primary">Log in</a>.
    </p>
</x-layouts.guest>
