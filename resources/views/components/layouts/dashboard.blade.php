@props(['title' => 'Operations'])

@php
    $user = auth()->user();
    $isOperator = $user->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Encoder);
    $isAdmin = $user->hasRole(\App\Enums\UserRole::Superadmin, \App\Enums\UserRole::Admin);
    $isSuperadmin = $user->hasRole(\App\Enums\UserRole::Superadmin);
    $moreActive = request()->routeIs(
        'dashboard.users',
        'dashboard.settings',
        'dashboard.sessions',
        'dashboard.announcements',
        'dashboard.reports',
    );
    $navLinks = [
        ['label' => 'Overview', 'href' => route('dashboard'), 'active' => request()->routeIs('dashboard')],
        [
            'label' => 'Incidents',
            'href' => route('dashboard.incidents'),
            'active' => request()->routeIs('dashboard.incidents*') && ! request()->routeIs('dashboard.incidents.show'),
        ],
    ];
    if ($isOperator) {
        $navLinks[] = ['label' => 'Caller Report', 'href' => route('dashboard.caller'), 'active' => request()->routeIs('dashboard.caller')];
        $navLinks[] = ['label' => 'Announcements', 'href' => route('dashboard.announcements'), 'active' => request()->routeIs('dashboard.announcements')];
        $navLinks[] = ['label' => 'Reports & Analytics', 'href' => route('dashboard.reports'), 'active' => request()->routeIs('dashboard.reports')];
    }
    if ($isAdmin) {
        $navLinks[] = ['label' => 'Users', 'href' => route('dashboard.users'), 'active' => request()->routeIs('dashboard.users')];
        $navLinks[] = ['label' => 'Site Info', 'href' => route('dashboard.settings'), 'active' => request()->routeIs('dashboard.settings')];
    }
    if ($isSuperadmin) {
        $navLinks[] = ['label' => 'Sessions', 'href' => route('dashboard.sessions'), 'active' => request()->routeIs('dashboard.sessions')];
    }
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · ResQHub Operations</title>
    <meta name="description" content="ResQHub operations dashboard for {{ site_setting('agency_short_name') }}.">
    <meta name="theme-color" content="#1b4d3e">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('darkMode');
                var on = stored === null
                    ? window.matchMedia('(prefers-color-scheme: dark)').matches
                    : stored === 'true';
                if (on) {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="flex min-h-screen">
        <aside class="hidden w-64 shrink-0 flex-col border-r border-border bg-surface lg:flex">
            <div class="flex h-16 items-center gap-3 border-b border-border px-5">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary text-sm font-bold text-white">RQ</span>
                <span>
                    <span class="block text-sm font-semibold text-fg">ResQHub</span>
                    <span class="block text-xs text-muted">Operations</span>
                </span>
            </div>

            <x-nav-links :links="$navLinks" label="Operations" class="flex flex-1 flex-col gap-1 p-3" />

            <div class="border-t border-border p-4">
                <p class="truncate text-sm font-medium text-fg">{{ $user->name }}</p>
                <p class="truncate text-xs text-muted">{{ $user->role?->label() }}</p>
                <div class="mt-3 flex items-center gap-2">
                    <button type="button" class="flex h-11 w-11 items-center justify-center rounded-lg border border-border bg-surface" aria-label="Toggle dark mode"
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
                    <button type="button" class="flex h-11 w-11 items-center justify-center rounded-lg border border-border bg-surface" aria-label="Open navigation" aria-expanded="false" aria-controls="dashboard-mobile-menu"
                        x-data :aria-expanded="$store.dashNav.open" @click.stop="$store.dashNav.open = !$store.dashNav.open">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
                <h1 class="truncate text-lg font-semibold text-fg">{{ $title }}</h1>
                <div class="flex items-center gap-2">
                    <button type="button" class="flex h-11 w-11 items-center justify-center rounded-lg border border-border bg-surface lg:hidden" aria-label="Toggle dark mode"
                        x-data @click="$store.darkMode.toggle()">
                        <svg x-show="!$store.darkMode.on" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                        <svg x-show="$store.darkMode.on" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                    </button>
                    <a href="{{ route('home') }}" class="btn btn-secondary hidden sm:inline-flex">Public Map</a>
                </div>
            </header>

            <div id="dashboard-mobile-menu" x-data x-show="$store.dashNav.open" x-cloak @keydown.escape.window="$store.dashNav.open = false" @click.outside="$store.dashNav.open = false" class="fixed inset-x-0 top-16 z-40 border-b border-border bg-surface shadow-lg lg:hidden">
                <div @click="$store.dashNav.open = false">
                    <x-nav-links :links="$navLinks" label="Operations" class="flex flex-col gap-1 px-4 py-3" />
                    <form method="POST" action="{{ route('logout') }}" class="px-4 pb-3">
                        @csrf
                        <button type="submit" class="btn btn-tertiary w-full justify-start">Log out</button>
                    </form>
                </div>
            </div>

            <x-flash-messages width="w-full" />

            <main class="flex-1 p-4 pb-24 sm:p-6 sm:pb-24 lg:pb-6">
                {{ $slot }}
            </main>
        </div>
    </div>

    <nav class="bottom-nav lg:hidden" aria-label="Operations">
        <a href="{{ route('dashboard') }}" class="bottom-nav-link {{ request()->routeIs('dashboard') ? 'bottom-nav-link-active' : '' }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l9-8 9 8M5 10v10a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1V10" /></svg>
            <span>Overview</span>
        </a>
        <a href="{{ route('dashboard.incidents') }}" class="bottom-nav-link {{ request()->routeIs('dashboard.incidents*') ? 'bottom-nav-link-active' : '' }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" /></svg>
            <span>Incidents</span>
        </a>
        @if ($isOperator)
            <a href="{{ route('dashboard.caller') }}" class="bottom-nav-link {{ request()->routeIs('dashboard.caller') ? 'bottom-nav-link-active' : '' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h2.28a2 2 0 011.9 1.37L9 6l-2 4m0 0l4 4m0 0l4 2V6a2 2 0 00-2-2h-1.28a2 2 0 00-2 2M3 10h16a2 2 0 012 2v7a2 2 0 01-2 2H5a2 2 0 01-2-2v-7a2 2 0 012-2z" /></svg>
                <span>Caller</span>
            </a>
        @endif
        <button type="button" class="bottom-nav-link {{ $moreActive ? 'bottom-nav-link-active' : '' }}" aria-label="More operations pages" aria-controls="dashboard-mobile-menu"
            x-data :aria-expanded="$store.dashNav.open" @click.stop="$store.dashNav.open = !$store.dashNav.open">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01" /></svg>
            <span>More</span>
        </button>
    </nav>

    <div x-data x-cloak class="pointer-events-none fixed inset-x-0 top-20 z-[100] flex flex-col items-end gap-2 px-4" role="region" aria-label="Notifications" aria-live="polite">
        <template x-for="toast in $store.toasts.items" :key="toast.id">
            <div class="toast" :class="{
                    'border-success/30 bg-success/10 text-success': toast.type === 'success',
                    'border-danger/30 bg-danger/10 text-danger': toast.type === 'danger',
                    'border-warning/40 bg-warning/15 text-warning-fg': toast.type === 'warning',
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
