<?php

namespace App\Http\Middleware;

use App\Authorization\RolePermissions;
use App\Enums\Permission;
use App\Enums\TenantUserRole;
use App\Models\Tenant;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Laravel\Fortify\Features;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'features' => [
                'twoFactorAuthentication' => Features::enabled(Features::twoFactorAuthentication()),
            ],
            // Use lazy evaluation so tenant is resolved after ResolveTenant middleware runs
            'tenant' => fn () => $this->getTenantContext($request),
        ];
    }

    /**
     * Get the tenant context data for the current request.
     *
     * Returns tenant branding information and the current user's role
     * when on a tenant subdomain. Returns null on the main domain.
     *
     * @return array<string, mixed>|null
     */
    protected function getTenantContext(Request $request): ?array
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        if (! $tenant) {
            return null;
        }

        $user = $request->user();
        $userRole = null;

        // Get the user's role and permissions in this tenant if authenticated
        $canManageUsers = false;
        $canManageOrganization = false;
        $canManageEmployees = false;
        $canViewAuditLogs = false;

        if ($user) {
            $membership = $user->tenants()
                ->where('tenants.id', $tenant->id)
                ->first();

            if ($membership) {
                $userRole = $membership->pivot->role;

                // Convert to enum if it's a string
                $roleEnum = $userRole instanceof TenantUserRole
                    ? $userRole
                    : TenantUserRole::tryFrom($userRole);

                // Check if user can manage users (Admin only, or Super Admin)
                $canManageUsers = $user->is_super_admin
                    || ($roleEnum && $roleEnum === TenantUserRole::Admin);

                // Check if user can manage organization (Admin, HR Manager, or Super Admin)
                $canManageOrganization = $user->is_super_admin
                    || ($roleEnum && RolePermissions::roleHasPermission($roleEnum, Permission::OrganizationManage));

                // Check if user can manage employees (requires all employee permissions)
                // This matches the can-manage-employees gate: Admin and HR Manager
                $canManageEmployees = $user->is_super_admin
                    || ($roleEnum && $this->hasAllEmployeePermissions($roleEnum));

                // Check if user can view audit logs (Admin only, or Super Admin)
                $canViewAuditLogs = $user->is_super_admin
                    || ($roleEnum && RolePermissions::roleHasPermission($roleEnum, Permission::AuditLogsView));
            }
        }

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'logo_url' => $tenant->logo_path,
            'primary_color' => $tenant->primary_color ?? '#3b82f6', // Default to blue
            'user_role' => $userRole,
            'can_manage_users' => $canManageUsers,
            'can_manage_organization' => $canManageOrganization,
            'can_manage_employees' => $canManageEmployees,
            'can_view_audit_logs' => $canViewAuditLogs,
        ];
    }

    /**
     * Check if a role has all employee permissions (view, create, edit, delete).
     */
    protected function hasAllEmployeePermissions(TenantUserRole $role): bool
    {
        return RolePermissions::roleHasPermission($role, Permission::EmployeesView)
            && RolePermissions::roleHasPermission($role, Permission::EmployeesCreate)
            && RolePermissions::roleHasPermission($role, Permission::EmployeesEdit)
            && RolePermissions::roleHasPermission($role, Permission::EmployeesDelete);
    }
}
