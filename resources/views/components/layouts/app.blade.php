@props(['title' => 'ResQHub'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · ResQHub</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body>
    <header class="sticky top-0 z-40 border-b border-border bg-surface">
        <div class="mx-auto flex h-16 max-w-[1600px] items-center justify-between gap-4 px-4 sm:px-6">
            <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-3 no-underline">
                <span class="flex h-9 w-9 items-center justify-center bg-primary text-sm font-bold tracking-tight text-white">RQ</span>
                <span class="leading-tight">
                    <span class="block text-base font-semibold text-fg">ResQHub</span>
                    <span class="block text-xs text-muted">MDRRMO Camalig</span>
                </span>
            </a>

            <nav class="hidden items-center gap-1 md:flex" aria-label="Main">
                <a href="{{ route('home') }}" class="nav-link {{ request()->routeIs('home') ? 'nav-link-active' : '' }}">Live Map</a>
                <a href="{{ route('advisories') }}" class="nav-link {{ request()->routeIs('advisories') ? 'nav-link-active' : '' }}">Advisories</a>

                @auth
                    @if (auth()->user()->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Encoder, \App\Enums\UserRole::BarangayOfficial, \App\Enums\UserRole::Responder))
                        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard*') ? 'nav-link-active' : '' }}">Operations</a>
                    @endif
                @endauth
            </nav>

            <div class="hidden items-center gap-2 md:flex">
                @auth
                    <a href="{{ route('report.create') }}" class="btn btn-primary">Submit Report</a>
                    <a href="{{ route('my-reports') }}" class="nav-link {{ request()->routeIs('my-reports') ? 'nav-link-active' : '' }}">My Reports</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-tertiary">Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn btn-secondary">Log in</a>
                    <a href="{{ route('register') }}" class="btn btn-primary">Register</a>
                @endauth

                <button type="button" class="btn btn-tertiary" aria-label="Toggle dark mode"
                    x-data @click="$store.darkMode.toggle()">
                    <svg x-show="!$store.darkMode.on" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                    <svg x-show="$store.darkMode.on" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </button>
            </div>

            <div class="flex items-center gap-2 md:hidden">
                <button type="button" class="btn btn-tertiary !px-2" aria-label="Toggle dark mode"
                    x-data @click="$store.darkMode.toggle()">
                    <svg x-show="!$store.darkMode.on" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                    <svg x-show="$store.darkMode.on" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </button>

                <button type="button" class="flex h-11 w-11 items-center justify-center border border-border bg-surface" aria-label="Open menu"
                    x-data @click="$store.mobileMenu.open = !$store.mobileMenu.open">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>

        <div x-data x-show="$store.mobileMenu.open" x-cloak class="border-t border-border bg-surface md:hidden">
            <div class="flex flex-col gap-1 px-4 py-3">
                <a href="{{ route('home') }}" class="nav-link">Live Map</a>
                <a href="{{ route('advisories') }}" class="nav-link">Advisories</a>
                @auth
                    <a href="{{ route('report.create') }}" class="nav-link">Submit Report</a>
                    <a href="{{ route('my-reports') }}" class="nav-link">My Reports</a>
                    @if (auth()->user()->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Encoder, \App\Enums\UserRole::BarangayOfficial, \App\Enums\UserRole::Responder))
                        <a href="{{ route('dashboard') }}" class="nav-link">Operations</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}" class="mt-1">
                        @csrf
                        <button type="submit" class="btn btn-tertiary w-full justify-start">Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn btn-secondary mt-1">Log in</a>
                    <a href="{{ route('register') }}" class="btn btn-primary mt-1">Register</a>
                @endauth
            </div>
        </div>
    </header>

    @if (session('status'))
        <div class="border-b border-success/30 bg-success/10 px-4 py-3 text-sm text-success" role="status">
            <div class="mx-auto max-w-[1600px]">{{ session('status') }}</div>
        </div>
    @endif

    @if ($errors->any())
        <div class="border-b border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">
            <div class="mx-auto flex max-w-[1600px] items-start gap-2">
                <span class="font-semibold">Something needs attention.</span>
                <span>{{ $errors->first() }}</span>
            </div>
        </div>
    @endif

    <main class="pb-20 lg:pb-0">
        {{ $slot }}
    </main>

    <nav class="bottom-nav lg:hidden" aria-label="Mobile">
        <a href="{{ route('home') }}" class="bottom-nav-link {{ request()->routeIs('home') ? 'bottom-nav-link-active' : '' }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" /></svg>
            <span>Map</span>
        </a>
        <a href="{{ route('advisories') }}" class="bottom-nav-link {{ request()->routeIs('advisories') ? 'bottom-nav-link-active' : '' }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" /></svg>
            <span>Advisories</span>
        </a>
        @auth
            <a href="{{ route('report.create') }}" class="bottom-nav-link {{ request()->routeIs('report.create') ? 'bottom-nav-link-active' : '' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                <span>Report</span>
            </a>
            <a href="{{ route('my-reports') }}" class="bottom-nav-link {{ request()->routeIs('my-reports') ? 'bottom-nav-link-active' : '' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                <span>My Reports</span>
            </a>
        @else
            <a href="{{ route('login') }}" class="bottom-nav-link {{ request()->routeIs('login') ? 'bottom-nav-link-active' : '' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" /></svg>
                <span>Log in</span>
            </a>
        @endauth
    </nav>

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

    <footer class="mt-16 border-t border-border bg-surface py-6 pb-24 lg:pb-6">
        <div class="mx-auto flex max-w-[1600px] flex-col gap-1 px-4 text-xs text-muted sm:px-6 md:flex-row md:items-center md:justify-between">
            <p>ResQHub · Municipal Disaster Risk Reduction and Management Office · Camalig, Albay</p>
            <p class="mono">Emergency hotline: 0917 123 4567 · resqhub.ph</p>
        </div>
    </footer>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('mobileMenu', { open: false });
        });
    </script>
</body>
</html>
