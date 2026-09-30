<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RequireReauthentication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordConfirmController extends Controller
{
    public function show(): View
    {
        return view('auth.confirm-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ], [
            'password.required' => 'Your password is required to continue.',
        ]);

        $user = $request->user();

        if ($user === null || ! Hash::check($request->input('password'), $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                'password' => 'The provided password does not match our records.',
            ]);
        }

        RequireReauthentication::markConfirmed($request);

        if ($request->session()->has(RequireReauthentication::PENDING_KEY)) {
            return redirect()->route('password.confirm.resume');
        }

        $redirectTo = $request->session()->pull('auth.redirect_to');

        return $redirectTo !== null
            ? redirect()->to($redirectTo)
            : redirect()->route('dashboard');
    }

    /**
     * Replay the sensitive request that was interrupted by the confirmation.
     *
     * The pending request is dispatched by a self-submitting form rather than
     * re-dispatched through the kernel, so the target action runs exactly once
     * and with its own middleware, validation and redirects intact.
     */
    public function resume(Request $request): View
    {
        $pending = $request->session()->get(RequireReauthentication::PENDING_KEY);

        if (! is_array($pending)) {
            return view('auth.confirm-password-resume', ['pending' => null]);
        }

        // Already dispatched once. A validation failure sends the user back
        // here, and resubmitting the same payload would loop.
        if ($request->session()->pull(RequireReauthentication::PENDING_CONSUMED_KEY, false)) {
            return view('auth.confirm-password-resume', ['pending' => null]);
        }

        $request->session()->put(RequireReauthentication::PENDING_CONSUMED_KEY, true);

        return view('auth.confirm-password-resume', ['pending' => $pending]);
    }
}
