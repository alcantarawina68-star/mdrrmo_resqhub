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
    public function resume(Request $request): View|RedirectResponse
    {
        $pending = $request->session()->pull(RequireReauthentication::PENDING_KEY);

        if (! is_array($pending)) {
            if (! $request->session()->pull(RequireReauthentication::PENDING_CONSUMED_KEY, false)) {
                return view('auth.confirm-password-resume', ['pending' => null]);
            }

            $request->session()->reflash();

            return redirect()->to($this->originAfterReplay($request));
        }

        $request->session()->put(RequireReauthentication::PENDING_CONSUMED_KEY, true);

        return view('auth.confirm-password-resume', ['pending' => $pending]);
    }

    /**
     * Resolve where to send the user once a deferred request has been replayed.
     *
     * The replayed action is submitted from this page, so a `back()` inside the
     * action points back here instead of at the page the user started from.
     * Forwarding the user to the page they submitted from both reports the
     * outcome of the action and stops a resubmission loop.
     */
    private function originAfterReplay(Request $request): string
    {
        $origin = $request->session()->pull('auth.redirect_to');

        if ($origin === null || $origin === route('password.confirm.resume')) {
            return route('dashboard');
        }

        return $origin;
    }
}
