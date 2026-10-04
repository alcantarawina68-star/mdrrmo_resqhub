<?php

namespace App\Http\Controllers;

use App\Support\NotificationFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * The alert history for the signed-in user, newest first.
     */
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()
                ->notifications()
                ->paginate(NotificationFeed::LIMIT * 3)
                ->withQueryString(),
        ]);
    }

    /**
     * Badge count plus the alerts behind it, so the dropdown renders from this
     * one response instead of asking for the list after the count.
     */
    public function feed(Request $request): JsonResponse
    {
        return response()->json(NotificationFeed::feed($request->user()));
    }

    /**
     * Mark one alert as read. Scoped through the signed-in user's own
     * notifications so an id from someone else is a 404, never an edit.
     */
    public function read(Request $request, string $notification): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $user->notifications()->findOrFail($notification)->markAsRead();

        if ($request->expectsJson()) {
            return response()->json(['unread' => NotificationFeed::unreadCount($user)]);
        }

        return back();
    }

    public function readAll(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $user->unreadNotifications->markAsRead();

        if ($request->expectsJson()) {
            return response()->json(['unread' => 0]);
        }

        return back()->with('status', 'All notifications marked as read.');
    }
}
