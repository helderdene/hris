<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    /**
     * Handle an incoming request.
     *
     * Verifies that the current tenant has an active subscription or valid trial.
     * Super Admins bypass this check entirely.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Super Admin bypasses all subscription checks
        if ($user !== null && $user->isSuperAdmin()) {
            return $next($request);
        }

        $tenant = tenant();

        if ($tenant === null) {
            abort(403, 'No organization context available.');
        }

        if (! $tenant->hasActiveAccess()) {
            if ($request->expectsJson()) {
                abort(403, 'Your subscription is not active.');
            }

            return redirect('/billing');
        }

        return $next($request);
    }
}
