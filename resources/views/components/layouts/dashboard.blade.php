@props(['title' => 'Operations'])

@php
    $user = auth()->user();
    $isOperator = $user->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Encoder);
    $isAdmin = $user->hasRole(\App\Enums\UserRole::Superadmin, \App\Enums\UserRole::Admin);
    $isSuperadmin = $user->hasRole(\App\Enums\UserRole::Superadmin);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · ResQHub Operations</title>
    <meta name="description" content="ResQHub operations dashboard for the MDRRMO of Camalaniugan, Cagayan.">
    <meta name="theme-color" content="#1b4d3e">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="flex min-h-screen">
        <aside class="hidden w-64 shrink-0 flex-col border-r border-border bg-surface lg:flex">
            <div class="flex h-16 items-center gap-3 border-b border-border px-5">
                <span class="flex h-9 w-9 items-center justify-center bg-primary text-sm font-bold text-white">RQ</span>
                <span>
                    <span class="block text-sm font-semibold text-fg">ResQHub</span>
                    <span class="block text-xs text-muted">Operations</span>
                </span>
            </div>

            <nav class="flex flex-1 flex-col gap-1 p-3" aria-label="Operations">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}">Overview</a>
                <a href="{{ route('dashboard.incidents') }}" class="nav-link {{ request()->routeIs('dashboard.incidents*') && ! request()->routeIs('dashboard.incidents.show') ? 'nav-link-active' : '' }}">Incidents</a>

                @if ($isOperator)
                    <a href="{{ route('dashboard.caller') }}" class="nav-link {{ request()->routeIs('dashboard.caller') ? 'nav-link-active' : '' }}">Caller Report</a>
                    <a href="{{ route('dashboard.announcements') }}" class="nav-link {{ request()->routeIs('dashboard.announcements') ? 'nav-link-active' : '' }}">Announcements</a>
                    <a href="{{ route('dashboard.reports') }}" class="nav-link {{ request()->routeIs('dashboard.reports') ? 'nav-link-active' : '' }}">Reports & Analytics</a>
                @endif

                @if ($isAdmin)
                    <a href="{{ route('dashboard.users') }}" class="nav-link {{ request()->routeIs('dashboard.users') ? 'nav-link-active' : '' }}">Users</a>
                @endif

                @if ($isSuperadmin)
                    <a href="{{ route('dashboard.sessions') }}" class="nav-link {{ request()->routeIs('dashboard.sessions') ? 'nav-link-active' : '' }}">Sessions</a>
                @endif
            </nav>

            <div class="border-t border-border p-4">
                <p class="truncate text-sm font-medium text-fg">{{ $user->name }}</p>
                <p class="truncate text-xs text-muted">{{ $user->role?->label() }}</p>
                <div class="mt-3 flex items-center gap-2">
                    <button type="button" class="btn btn-tertiary !px-2" aria-label="Toggle dark mode"
                        x-data @click="$store.darkMode.toggle()">
                        <svg x-show="!$store.darkMode.on" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                        <svg x-show="$store.darkMode.on" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                    </button>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-tertiary w-full justify-start">Log out</button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-border bg-surface px-4 sm:px-6">
                <div class="flex items-center gap-3 lg:hidden">
                    <button type="button" class="flex h-11 w-11 items-center justify-center border border-border bg-surface" aria-label="Open navigation" aria-expanded="false"
                        x-data :aria-expanded="$store.dashNav.open" @click="$store.dashNav.open = !$store.dashNav.open">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
                <h1 class="truncate text-lg font-semibold text-fg">{{ $title }}</h1>
                <div class="flex items-center gap-2">
                    <button type="button" class="btn btn-tertiary !px-2 lg:hidden" aria-label="Toggle dark mode"
                        x-data @click="$store.darkMode.toggle()">
                        <svg x-show="!$store.darkMode.on" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                        <svg x-show="$store.darkMode.on" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                    </button>
                    <a href="{{ route('home') }}" class="btn btn-secondary hidden sm:inline-flex">Public Map</a>
                </div>
            </header>

            <div x-data x-show="$store.dashNav.open" x-cloak @keydown.escape.window="$store.dashNav.open = false" class="border-b border-border bg-surface lg:hidden">
                <nav class="flex flex-col gap-1 p-3" @click="$store.dashNav.open = false">
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}">Overview</a>
                    <a href="{{ route('dashboard.incidents') }}" class="nav-link {{ request()->routeIs('dashboard.incidents*') && ! request()->routeIs('dashboard.incidents.show') ? 'nav-link-active' : '' }}">Incidents</a>
                    @if ($isOperator)
                        <a href="{{ route('dashboard.caller') }}" class="nav-link {{ request()->routeIs('dashboard.caller') ? 'nav-link-active' : '' }}">Caller Report</a>
                        <a href="{{ route('dashboard.announcements') }}" class="nav-link {{ request()->routeIs('dashboard.announcements') ? 'nav-link-active' : '' }}">Announcements</a>
                        <a href="{{ route('dashboard.reports') }}" class="nav-link {{ request()->routeIs('dashboard.reports') ? 'nav-link-active' : '' }}">Reports & Analytics</a>
                    @endif
                    @if ($isAdmin)
                        <a href="{{ route('dashboard.users') }}" class="nav-link {{ request()->routeIs('dashboard.users') ? 'nav-link-active' : '' }}">Users</a>
                    @endif

                    @if ($isSuperadmin)
                        <a href="{{ route('dashboard.sessions') }}" class="nav-link {{ request()->routeIs('dashboard.sessions') ? 'nav-link-active' : '' }}">Sessions</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}" class="mt-1">
                        @csrf
                        <button type="submit" class="btn btn-tertiary w-full justify-start">Log out</button>
                    </form>
                </nav>
            </div>

            @if (session('status'))
                <div class="border-b border-success/30 bg-success/10 px-4 py-3 text-sm text-success" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="border-b border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <main class="flex-1 p-4 sm:p-6">
                {{ $slot }}
            </main>
        </div>
    </div>

    <div x-data x-cloak class="pointer-events-none fixed inset-x-0 top-4 z-[100] flex flex-col items-end gap-2 px-4">
        <template x-for="toast in $store.toasts.items" :key="toast.id">
            <div class="toast" :class="{
                    'border-success/30 bg-success/10 text-success': toast.type === 'success',
                    'border-danger/30 bg-danger/10 text-danger': toast.type === 'danger',
                    'border-warning/40 bg-warning/15 text-[#7a5200]': toast.type === 'warning',
                    'border-border bg-surface text-fg': toast.type === 'info',
                }"
                x-show="toast.visible"
                x-transition:enter="transition duration-300 ease-out"
                x-transition:enter-start="translate-x-full opacity-0"
                x-transition:enter-end="translate-x-0 opacity-100"
                x-transition:leave="transition duration-200 ease-in"
                x-transition:leave-start="translate-x-0 opacity-100"
                x-transition:leave-end="translate-x-full opacity-0">
                <span class="flex-1 text-sm" x-text="toast.message"></span>
                <button type="button" class="shrink-0 text-muted hover:text-fg" @click="$store.toasts.dismiss(toast.id)">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </template>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('dashNav', { open: false });
        });
    </script>
</body>
</html>
