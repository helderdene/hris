<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\Tenant\TenantDatabaseManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateExternalApi
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        protected TenantDatabaseManager $databaseManager
    ) {}

    /**
     * Handle an incoming request.
     *
     * Combines tenant resolution, API token authentication, and database switching
     * for external API requests. Expects Bearer token in Authorization header
     * and tenant slug as a route parameter.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $tenantSlug = $request->route('tenantSlug');
        $tenant = Tenant::where('slug', $tenantSlug)->first();

        if ($tenant === null) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        if (! $tenant->hasValidApiToken($token)) {
            return response()->json(['message' => 'Invalid API token.'], 401);
        }

        // Bind tenant to container and switch database
        app()->instance('tenant', $tenant);
        $this->databaseManager->switchConnection($tenant);

        // Record usage asynchronously-safe (simple timestamp update)
        $tenant->recordApiTokenUsage();

        // Remove tenantSlug from route params so it doesn't get passed to controllers
        $request->route()?->forgetParameter('tenantSlug');

        return $next($request);
    }
}
