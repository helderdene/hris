<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantMember
{
    /**
     * Handle an incoming request.
     *
     * Verifies that the authenticated user is a member of the current tenant
     * (or is a Super Admin). This middleware should be applied to all tenant
     * subdomain routes to ensure proper access control.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = tenant();

        // If no user is authenticated, let auth middleware handle it
        if ($user === null) {
            return $next($request);
        }

        // If no tenant is resolved, this middleware shouldn't be applied
        if ($tenant === null) {
            return $next($request);
        }

        // Check if user is authorized to access this tenant
        if (! Gate::forUser($user)->allows('tenant-member')) {
            // User does not have access to this tenant
            abort(403, 'You do not have access to this organization.');
        }

        return $next($request);
    }
}
