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

        $redirectTo = $request->session()->pull('auth.redirect_to');

        return $redirectTo !== null
            ? redirect()->to($redirectTo)
            : redirect()->route('dashboard');
    }
}
