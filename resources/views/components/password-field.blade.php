@props([
    'name',
    'label',
    'id' => null,
    'autocomplete' => 'current-password',
    'required' => false,
    'hint' => null,
    'minlength' => null,
    'errorKey' => null,
    'autofocus' => false,
])

@php
    $id ??= $name;
    $errorKey ??= $name;
    $error = $errors->first($errorKey);
@endphp

<div class="field">
    <label class="label" for="{{ $id }}">{{ $label }}</label>
    <div class="relative" x-data="{ show: false }">
        <input id="{{ $id }}" name="{{ $name }}" :type="show ? 'text' : 'password'" class="input pr-11 @error($errorKey) border-danger/60 @enderror"
            value="{{ old($name) }}" autocomplete="{{ $autocomplete }}" @if ($required) required @endif
            @if ($minlength) minlength="{{ $minlength }}" @endif @if ($autofocus) autofocus @endif
            @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>
        <button type="button" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-muted hover:text-fg"
            @click="show = !show" :aria-pressed="show" :aria-label="show ? 'Hide password' : 'Show password'">
            <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
            <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
        </button>
    </div>
    @if ($hint)
        <p class="mt-1 text-xs text-muted">{{ $hint }}</p>
    @endif
    @error($errorKey)
        <span id="{{ $id }}-error" class="mt-1 block text-xs text-danger">{{ $message }}</span>
    @enderror
</div>
