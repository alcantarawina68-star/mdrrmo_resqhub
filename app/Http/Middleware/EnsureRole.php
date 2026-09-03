<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role?->value, $roles, true)) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error('You are not authorized to perform this action.', 403);
            }

            abort(403);
        }

        return $next($request);
    }
}
