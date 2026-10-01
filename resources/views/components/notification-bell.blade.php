{{--
    Header alert bell: an unread badge plus a dropdown of recent alerts.

    Rendered only for operations roles (the audience IncidentService notifies)
    so a badge never sits at zero for someone who can never receive one.

    The initial payload is server-rendered into a JSON island rather than
    @js()-inlined into x-data, because @js() emits double quotes and would
    break the attribute — the same reason map-type-switch passes its layers
    through JSON.parse. Same reason the feed, read template and CSRF token
    live in data-* attributes: the only value inlined into x-data is the
    integer count, which is quote-free.

    notificationBell() in resources/js/app.js polls the feed, repaints this
    panel, marks items read without a page reload, and raises a toast when the
    unread count climbs.

    The panel is absolutely positioned inside this relative wrapper, so it needs
    no z-index above the Leaflet ceiling described in .ai/rules/views.md.
--}}
@use('App\Support\NotificationFeed')
@use('Illuminate\Support\Js')

@php
    $user = auth()->user();
    $showsNotifications = $user !== null && $user->isOperationsRole();
    $unreadCount = $showsNotifications ? NotificationFeed::unreadCount($user) : 0;
    $feedItems = $showsNotifications
        ? NotificationFeed::items(NotificationFeed::latest($user))
        : [];
@endphp

@if ($showsNotifications)
    <div x-data="notificationBell(@js($unreadCount))"
        data-feed-url="{{ route('notifications.feed') }}"
        data-read-url-template="{{ route('notifications.read', ['notification' => '__ID__']) }}"
        data-read-all-url="{{ route('notifications.read-all') }}"
        data-csrf-token="{{ csrf_token() }}"
        class="relative"
        @keydown.escape.window="open = false">
        <script type="application/json" data-notification-items>{!! Js::from($feedItems) !!}</script>

        <button type="button"
            class="relative flex h-11 w-11 items-center justify-center rounded-lg border border-border bg-surface"
            aria-haspopup="true"
            :aria-expanded="open"
            :aria-label="unread > 0 ? unread + ' unread notifications' : 'Notifications'"
            @click="toggle()">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span x-show="unread > 0" x-cloak
                class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-danger px-1 text-xs font-semibold text-white"
                x-text="unread > 99 ? '99+' : unread"></span>
        </button>

        <div x-show="open" x-cloak @click.outside="open = false"
            x-transition:enter="transition duration-150 ease-out"
            x-transition:enter-start="-translate-y-1 opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            class="absolute right-0 z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-border bg-surface shadow-lg">
            <div class="flex items-center justify-between gap-2 border-b border-border px-4 py-3">
                <p class="text-sm font-semibold text-fg">Notifications</p>
                <button type="button" x-show="unread > 0" x-cloak
                    class="text-xs font-medium text-primary hover:underline"
                    @click="markAllRead()">
                    Mark all read
                </button>
            </div>

            <ul class="max-h-96 divide-y divide-border overflow-y-auto">
                <template x-for="item in items" :key="item.id">
                    <li class="flex items-start gap-2">
                        <button type="button"
                            class="flex min-w-0 flex-1 items-start gap-2 px-4 py-3 text-left hover:bg-bg"
                            @click="openItem(item)">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="item.readAt ? 'bg-transparent' : 'bg-primary'"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm" :class="item.readAt ? 'text-muted' : 'font-semibold text-fg'" x-text="item.title"></span>
                                <span class="mt-0.5 block truncate text-xs text-muted" x-text="item.body"></span>
                                <span class="mt-0.5 block text-xs text-muted" x-text="item.createdAt"></span>
                            </span>
                        </button>
                        <button type="button" x-show="! item.readAt" x-cloak
                            class="shrink-0 self-center rounded-lg px-2 py-1 text-xs font-medium text-primary hover:bg-bg"
                            :aria-label="'Mark ' + item.title + ' as read'"
                            @click="markRead(item.id)">
                            Mark read
                        </button>
                    </li>
                </template>

                <li x-show="loaded && items.length === 0" x-cloak class="px-4 py-6 text-center text-sm text-muted">
                    No alerts yet.
                </li>
            </ul>

            <a href="{{ route('notifications.index') }}"
                class="block border-t border-border px-4 py-3 text-center text-sm font-medium text-primary hover:bg-bg">
                View all notifications
            </a>
        </div>
    </div>
@endif
