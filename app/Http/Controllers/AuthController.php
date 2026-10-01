<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');

        $user = User::where('email', $credentials['email'])->first();

        if ($user !== null && $user->hasActiveSession()) {
            throw ValidationException::withMessages([
                'email' => 'This account is already logged in on another device. Please log out there first or wait for the session to expire.',
            ]);
        }

        if ($user !== null && ! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => 'Your account is suspended or inactive.',
            ]);
        }

        if ($user !== null && $user->session_id !== null) {
            $user->forceFill(['session_id' => null])->save();
        }

        if (! Auth::guard('web')->attempt($credentials, false)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $user = Auth::user();

        $request->session()->regenerate();

        $user->forceFill(['session_id' => $request->session()->getId()])->save();

        if ($user->hasRole(UserRole::Superadmin, UserRole::Admin, UserRole::Encoder, UserRole::Responder)) {
            return redirect()->intended(route('dashboard'));
        }

        return redirect()->intended(route('my-reports'));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $user = User::create([
            ...$request->validated(),
            'role' => UserRole::CommunityUser,
            'status' => UserStatus::Active,
        ]);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $user->forceFill(['session_id' => $request->session()->getId()])->save();

        return redirect()->route('report.create');
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($user !== null) {
            $user->forceFill(['session_id' => null])->save();
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(ForgotPasswordRequest $request): RedirectResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    public function showResetPassword(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'session_id' => null,
                ])->save();

                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => [__($status)]]);
    }
}
