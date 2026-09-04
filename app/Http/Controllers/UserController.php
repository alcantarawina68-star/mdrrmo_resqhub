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
            'canManageSuperadmins' => $request->user()->isSuperadmin(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $request->user()->isSuperadmin() && $request->input('role') === UserRole::Superadmin->value) {
            throw ValidationException::withMessages([
                'role' => 'Only a Super Admin can create a Super Admin account.',
            ]);
        }

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

        if (! $request->user()->isSuperadmin() && ($user->isSuperadmin() || $request->input('role') === UserRole::Superadmin->value)) {
            throw ValidationException::withMessages([
                'role' => 'Only a Super Admin can manage Super Admin accounts.',
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

        $this->guardLastSuperadmin($user, $data);

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

        if (! $request->user()->isSuperadmin() && $user->isSuperadmin()) {
            throw ValidationException::withMessages([
                'user' => 'Only a Super Admin can delete a Super Admin account.',
            ]);
        }

        if ($user->hasRole(UserRole::Superadmin) && $this->isLastActiveSuperadmin($user)) {
            throw ValidationException::withMessages([
                'user' => 'The last active Super Admin cannot be deleted.',
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

    private function guardLastSuperadmin(User $user, array $data): void
    {
        if (! $user->isSuperadmin() || ! $this->isLastActiveSuperadmin($user)) {
            return;
        }

        $roleBeingChanged = array_key_exists('role', $data) && $data['role'] !== UserRole::Superadmin->value;
        $statusBeingChanged = array_key_exists('status', $data) && $data['status'] !== UserStatus::Active->value;

        if ($roleBeingChanged || $statusBeingChanged) {
            throw ValidationException::withMessages([
                'user' => 'The last active Super Admin cannot be demoted or deactivated.',
            ]);
        }
    }

    private function isLastActiveSuperadmin(User $user): bool
    {
        return User::where('role', UserRole::Superadmin->value)
            ->where('status', UserStatus::Active->value)
            ->count() <= 1;
    }
}
