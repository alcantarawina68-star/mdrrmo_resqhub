<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireReauthentication
{
    private const CONFIRMED_AT_KEY = 'auth.password_confirmed_at';

    /**
     * The sensitive request that was interrupted, replayed once the password
     * is confirmed so the user does not have to submit everything twice.
     */
    public const PENDING_KEY = 'auth.pending_request';

    /**
     * Marks the pending request as already dispatched, so a validation failure
     * that bounces the user back here does not resubmit in a loop.
     */
    public const PENDING_CONSUMED_KEY = 'auth.pending_request_consumed';

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

        $this->deferRequest($request);

        session()->put('auth.redirect_to', $request->isMethod('GET')
            ? $request->fullUrl()
            : url()->previous());

        return redirect()->route('password.confirm');
    }

    /**
     * Stash a state-changing request so it can be completed after the password
     * is confirmed.
     *
     * Uploads and nested payloads cannot survive a session round trip, so those
     * requests fall back to the previous behaviour of returning the user to the
     * page they came from.
     */
    private function deferRequest(Request $request): void
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return;
        }

        $payload = $request->except(['_token']);

        $isReplayable = $request->file() === []
            && collect($payload)->every(fn ($value) => $value === null || is_scalar($value));

        if (! $isReplayable) {
            return;
        }

        session()->put(self::PENDING_KEY, [
            'method' => $request->getMethod(),
            'action' => $request->getRequestUri(),
            'payload' => $payload,
        ]);

        session()->forget(self::PENDING_CONSUMED_KEY);
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
