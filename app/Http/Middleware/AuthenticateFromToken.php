<?php

namespace App\Http\Middleware;

use App\Models\TenantRedirectToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateFromToken
{
    /**
     * Handle an incoming request.
     *
     * Validates a redirect token from the main domain and authenticates the user.
     * The token is single-use and is deleted after successful authentication.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tokenString = $request->query('token');

        if ($tokenString === null) {
            return $next($request);
        }

        // Find and validate the token
        $token = TenantRedirectToken::findValidToken($tokenString);

        if ($token === null) {
            // Token is invalid or expired, continue without authentication
            return $next($request);
        }

        // Get the current tenant from the app container
        $currentTenant = tenant();

        // Verify the token is for the current tenant
        if ($currentTenant === null || $token->tenant_id !== $currentTenant->id) {
            // Token is for a different tenant
            return $next($request);
        }

        // Verify the user has membership in the current tenant
        $user = $token->user;
        $isMember = $user->tenants()->where('tenants.id', $currentTenant->id)->exists();

        if (! $isMember) {
            // User no longer has access to this tenant
            $token->delete();

            return $next($request);
        }

        // Authenticate the user
        Auth::login($user);

        // Delete the token (single use)
        $token->delete();

        // Remove only the token from the URL, preserve other query params (like created)
        $queryParams = $request->query();
        unset($queryParams['token']);

        $cleanUrl = $request->url();
        if (! empty($queryParams)) {
            $cleanUrl .= '?'.http_build_query($queryParams);
        }

        return redirect($cleanUrl);
    }
}
