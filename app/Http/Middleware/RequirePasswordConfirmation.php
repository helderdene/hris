<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant-safe password confirmation middleware.
 *
 * Unlike Laravel's built-in `password.confirm` middleware, this returns
 * a 423 JSON response for AJAX/Inertia requests instead of redirecting
 * to the main domain's password confirmation page (which causes CORS errors
 * on tenant subdomains).
 */
class RequirePasswordConfirmation
{
    public function handle(Request $request, Closure $next): Response
    {
        $confirmedAt = $request->session()->get('auth.password_confirmed_at', 0);
        $timeout = config('auth.password_timeout', 10800);

        if ((Date::now()->unix() - $confirmedAt) < $timeout) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Password confirmation required.',
        ], 423);
    }
}
