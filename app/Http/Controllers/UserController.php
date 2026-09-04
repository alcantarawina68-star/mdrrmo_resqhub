<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate(15)->withQueryString();

        $activeSessionIds = DB::table('sessions')
            ->whereIn('user_id', $users->pluck('id'))
            ->where('last_activity', '>', now()->subMinutes(config('session.lifetime'))->getTimestamp())
            ->pluck('user_id')
            ->flip();

        return view('dashboard.users', [
            'users' => $users,
            'roles' => UserRole::labels(),
            'statuses' => UserStatus::labels(),
            'activeSessionIds' => $activeSessionIds,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'role' => ['required', Rule::in(UserRole::values())],
            'contact_number' => ['nullable', 'string', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'barangay' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(UserStatus::values())],
        ]);

        User::create($data);

        return back()->with('status', 'User account created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user()) && $request->hasAny(['role', 'status'])) {
            throw ValidationException::withMessages([
                'role' => 'You cannot change your own role or status.',
            ]);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', 'max:190', 'unique:users,email,'.$user->id],
            'password' => ['sometimes', 'nullable', Password::min(8)],
            'role' => ['sometimes', Rule::in(UserRole::values())],
            'contact_number' => ['sometimes', 'nullable', 'string', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'barangay' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(UserStatus::values())],
        ]);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return back()->with('status', 'User account updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own account.',
            ]);
        }

        $isLastActiveAdmin = $user->hasRole(UserRole::Admin)
            && User::where('role', UserRole::Admin->value)->where('status', UserStatus::Active->value)->count() <= 1;

        if ($isLastActiveAdmin) {
            throw ValidationException::withMessages([
                'user' => 'The last active administrator cannot be deleted.',
            ]);
        }

        $user->delete();

        return back()->with('status', 'User account deleted.');
    }
}
