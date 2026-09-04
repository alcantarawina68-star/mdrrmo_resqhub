<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SessionManagementController extends Controller
{
    public function index(): View
    {
        $sessions = DB::table('sessions')
            ->join('users', 'users.id', '=', 'sessions.user_id')
            ->select(
                'sessions.id as session_id',
                'sessions.user_id',
                'sessions.ip_address',
                'sessions.user_agent',
                'sessions.last_activity',
                'users.name',
                'users.email',
                'users.role',
            )
            ->orderByDesc('sessions.last_activity')
            ->limit(200)
            ->get();

        return view('dashboard.sessions', compact('sessions'));
    }

    public function terminate(Request $request, string $session): RedirectResponse
    {
        $sessionRow = DB::table('sessions')->where('id', $session)->first();

        if ($sessionRow === null) {
            return back()->with('status', 'That session has already ended.');
        }

        if ($request->session()->getId() === $session) {
            return back()->with('status', 'You cannot end your own active session here. Use logout instead.');
        }

        DB::table('sessions')->where('id', $session)->delete();

        $user = User::find($sessionRow->user_id);
        if ($user !== null && $user->session_id === $session) {
            $user->forceFill(['session_id' => null])->save();
        }

        return back()->with('status', 'The device session was ended.');
    }

    public function logoutUser(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('status', 'Use Log out to end your own session.');
        }

        DB::table('sessions')->where('user_id', $user->id)->delete();

        $user->forceFill(['session_id' => null])->save();

        return back()->with('status', $user->name.' was logged out from all devices.');
    }
}
