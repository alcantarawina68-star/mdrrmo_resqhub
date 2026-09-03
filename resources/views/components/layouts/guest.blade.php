@props(['title' => 'ResQHub'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · ResQHub</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="flex min-h-screen flex-col items-center justify-center bg-bg px-4 py-12">
        <div class="mb-8 flex items-center gap-4">
            <a href="{{ route('home') }}" class="flex items-center gap-3 no-underline">
                <span class="flex h-11 w-11 items-center justify-center bg-primary text-base font-bold text-white">RQ</span>
                <span>
                    <span class="block text-xl font-semibold text-fg">ResQHub</span>
                    <span class="block text-xs text-muted">MDRRMO Camalig</span>
                </span>
            </a>
            <button type="button" class="btn btn-tertiary !px-2" aria-label="Toggle dark mode"
                x-data @click="$store.darkMode.toggle()">
                <svg x-show="!$store.darkMode.on" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                <svg x-show="$store.darkMode.on" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
            </button>
        </div>

        <div class="w-full max-w-md border border-border bg-surface p-8">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
