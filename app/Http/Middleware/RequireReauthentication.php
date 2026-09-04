<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireReauthentication
{
    private const CONFIRMED_AT_KEY = 'auth.password_confirmed_at';

    private const WINDOW_MINUTES = 15;

    /**
     * Handle an incoming request.
     *
     * Require the user to have confirmed their password within the window
     * before allowing access to sensitive actions.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $this->unauthenticated($request);
        }

        $confirmedAt = $request->session()->get(self::CONFIRMED_AT_KEY);

        if (is_int($confirmedAt) && (time() - $confirmedAt) < (self::WINDOW_MINUTES * 60)) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => 'Re-authentication required to perform this action.',
            ], 423);
        }

        session()->put('auth.redirect_to', $request->isMethod('GET')
            ? $request->fullUrl()
            : url()->previous());

        return redirect()->route('password.confirm');
    }

    private function unauthenticated(Request $request): Response
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return redirect()->guest(route('login'));
    }

    public static function markConfirmed(Request $request): void
    {
        $request->session()->put(self::CONFIRMED_AT_KEY, time());
    }

    public static function forgetConfirmed(Request $request): void
    {
        $request->session()->forget(self::CONFIRMED_AT_KEY);
    }
}
