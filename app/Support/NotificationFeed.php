<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Shapes database notifications for the header bell.
 *
 * Both the JSON feed endpoint and the server-rendered bell go through here, so
 * the markup the browser paints on first load and the markup the poll replaces
 * it with can never drift apart.
 */
class NotificationFeed
{
    /**
     * How many alerts the dropdown shows. Enough to recognise what happened
     * without turning the header into a second dashboard.
     */
    public const LIMIT = 8;

    /**
     * The most recent alerts, newest first.
     */
    public static function latest(User $user): Collection
    {
        return $user->notifications()->latest()->limit(self::LIMIT)->get();
    }

    public static function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public static function feed(User $user): array
    {
        return self::summary($user);
    }

    /**
     * @param  Collection<int, DatabaseNotification>  $notifications
     * @return array<int, array{id: string, title: string, body: string, url: string, readAt: ?string, createdAt: string, incidentNumber: ?string}>
     */
    public static function items(Collection $notifications): array
    {
        return $notifications
            ->map(fn ($notification) => [
                'id' => (string) $notification->id,
                'title' => $notification->data['title'] ?? 'Update',
                'body' => $notification->data['body'] ?? '',
                'url' => $notification->data['url'] ?? route('notifications.index'),
                'readAt' => $notification->read_at?->toIso8601String(),
                'createdAt' => $notification->created_at->diffForHumans(),
                'incidentNumber' => $notification->data['incidentNumber'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * The payload behind both the badge and the dropdown, so the browser makes
     * exactly one request per poll no matter which of the two it is rendering.
     *
     * @return array{unread: int, notifications: array<int, array<string, mixed>>}
     */
    public static function summary(User $user): array
    {
        return [
            'unread' => self::unreadCount($user),
            'notifications' => self::items(self::latest($user)),
        ];
    }
}
