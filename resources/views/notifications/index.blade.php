@php
    $unreadTotal = auth()->user()->unreadNotifications()->count();
@endphp

<x-layouts.app title="Notifications">
    <div class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1>Notifications</h1>
                <p class="mt-1 text-sm text-muted">
                    Incident alerts for your role. {{ $unreadTotal }} unread.
                </p>
            </div>

            @if ($unreadTotal > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary">Mark all as read</button>
                </form>
            @endif
        </div>

        @if ($notifications->isEmpty())
            <div class="card p-8 text-center">
                <p class="text-sm text-muted">No alerts yet.</p>
                <p class="mt-1 text-xs text-muted">
                    You are notified when an incident is reported, verified, reassigned, or changes status.
                </p>
            </div>
        @else
            <ul class="space-y-2">
                @foreach ($notifications as $notification)
                    @php
                        $data = $notification->data;
                        $isUnread = $notification->read_at === null;
                        $target = $data['url'] ?? route('notifications.index');
                    @endphp
                    <li @class([
                        'card border-l-4 p-4',
                        'border-l-primary' => $isUnread,
                        'opacity-70' => ! $isUnread,
                    ])>
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-fg">
                                    {{ $data['title'] ?? 'Update' }}
                                    @if ($isUnread)
                                        <span class="ml-1 inline-block h-2 w-2 rounded-full bg-primary align-middle"></span>
                                        <span class="sr-only">Unread</span>
                                    @endif
                                </p>
                                @if (! empty($data['body']))
                                    <p class="mt-1 text-sm text-muted">{{ $data['body'] }}</p>
                                @endif
                                <p class="mt-1 text-xs text-muted">{{ $notification->created_at->diffForHumans() }}</p>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <a href="{{ $target }}" class="btn btn-tertiary !text-xs">Open incident</a>
                                @if ($isUnread)
                                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-tertiary !text-xs">Mark read</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="mt-6">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</x-layouts.app>
