<?php

namespace App\Http\Middleware;

use App\Services\Tenant\TenantDatabaseManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SwitchTenantDatabase
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
     * Switches the tenant database connection to the resolved tenant's schema.
     * This middleware must run after ResolveTenant middleware.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get the tenant from the app container (set by ResolveTenant middleware)
        $tenant = tenant();

        if ($tenant !== null) {
            $this->databaseManager->switchConnection($tenant);
        }

        return $next($request);
    }
}
