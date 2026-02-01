<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateFromMainDomain
{
    /**
     * Handle an incoming request.
     *
     * Validates that authenticated users on tenant subdomains have membership
     * in the current tenant. This middleware ensures that session-based
     * authentication from the main domain is properly verified for tenant access.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = tenant();

        // If no user is authenticated, let other middleware handle it
        if ($user === null) {
            return $next($request);
        }

        // If no tenant is resolved, this middleware shouldn't be applied
        if ($tenant === null) {
            return $next($request);
        }

        // Verify the authenticated user has membership in the current tenant
        $isMember = $user->tenants()->where('tenants.id', $tenant->id)->exists();

        if (! $isMember) {
            // User does not have access to this tenant
            // Log them out and redirect to main domain login
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $mainDomain = config('app.main_domain', 'kasamahr.test');
            $scheme = $request->secure() ? 'https' : 'http';

            return redirect()->away("{$scheme}://{$mainDomain}/login")
                ->with('error', 'You do not have access to this organization.');
        }

        // Store the current tenant membership role in session for easy access
        $membership = $user->tenants()->where('tenants.id', $tenant->id)->first();

        if ($membership !== null) {
            session(['current_tenant_role' => $membership->pivot->role]);
        }

        return $next($request);
    }
}
