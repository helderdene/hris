<?php

namespace App\Http\Middleware;

use App\Enums\Module;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    /**
     * Handle an incoming request.
     *
     * Verifies that the current tenant's plan includes at least one of the
     * specified modules. Super Admins bypass this check entirely.
     *
     * Usage in routes: `->middleware('module:recruitment,onboarding_preboarding')`
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$modules  One or more module values (e.g., 'recruitment', 'training_development')
     */
    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        $user = $request->user();

        // Super Admin bypasses all module checks
        if ($user !== null && $user->isSuperAdmin()) {
            return $next($request);
        }

        $tenant = tenant();

        if ($tenant === null) {
            abort(403, 'No organization context available.');
        }

        // Trial tenants get access to all modules
        if ($tenant->onTrial()) {
            return $next($request);
        }

        foreach ($modules as $module) {
            $moduleEnum = Module::tryFrom($module);

            if ($moduleEnum !== null && $tenant->hasModule($moduleEnum)) {
                return $next($request);
            }
        }

        if ($request->expectsJson()) {
            abort(403, 'Your plan does not include this module.');
        }

        return redirect('/billing/upgrade');
    }
}
