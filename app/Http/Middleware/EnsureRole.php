<?php

namespace App\Http\Middleware;

use App\Enums\TenantUserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * Verifies that the authenticated user has one of the specified roles
     * in the current tenant. Super Admins bypass this check entirely.
     *
     * Usage in routes: `->middleware('ensure-role:admin,hr_manager')`
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles  One or more role values (e.g., 'admin', 'hr_manager')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Super Admin bypasses all role checks
        if ($user !== null && $user->isSuperAdmin()) {
            return $next($request);
        }

        // User must be authenticated
        if ($user === null) {
            abort(403, 'You must be authenticated to access this resource.');
        }

        // Get current tenant context
        $tenant = tenant();

        if ($tenant === null) {
            abort(403, 'No organization context available.');
        }

        // Get user's role in the current tenant
        $userRole = $user->getRoleInTenant($tenant);

        if ($userRole === null) {
            abort(403, 'You are not a member of this organization.');
        }

        // Check if user's role is in the list of allowed roles
        $allowedRoles = $this->parseRoles($roles);

        if (! in_array($userRole, $allowedRoles, true)) {
            abort(403, 'You do not have the required role to access this resource.');
        }

        return $next($request);
    }

    /**
     * Parse role strings into TenantUserRole enum instances.
     *
     * @param  array<string>  $roles
     * @return array<TenantUserRole>
     */
    private function parseRoles(array $roles): array
    {
        $parsedRoles = [];

        foreach ($roles as $role) {
            $tenantRole = TenantUserRole::tryFrom($role);

            if ($tenantRole !== null) {
                $parsedRoles[] = $tenantRole;
            }
        }

        return $parsedRoles;
    }
}
