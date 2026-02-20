<?php

namespace App\Providers;

use App\Authorization\RolePermissions;
use App\Enums\Permission;
use App\Enums\TenantUserRole;
use App\Events\ComplianceAssignmentCompleted;
use App\Events\ComplianceAssignmentCreated;
use App\Events\ComplianceAssignmentOverdue;
use App\Events\EmployeeCreated;
use App\Events\EmployeeDepartmentChanged;
use App\Events\EmployeePositionChanged;
use App\Events\ProfilePhotoUploaded;
use App\Listeners\EvaluateComplianceOnDepartmentChange;
use App\Listeners\EvaluateComplianceOnEmployeeCreate;
use App\Listeners\EvaluateComplianceOnPositionChange;
use App\Listeners\HandleComplianceAssignmentCompleted;
use App\Listeners\HandleComplianceAssignmentCreated;
use App\Listeners\HandleComplianceAssignmentOverdue;
use App\Listeners\InitializeBiometricSyncRecords;
use App\Listeners\SyncProfilePhotoToDevices;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Observers\EmployeeObserver;
use App\Policies\TenantPolicy;
use App\Services\FeatureGateService;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantDatabaseManager::class, function ($app) {
            return new TenantDatabaseManager;
        });

        $this->app->bind(FeatureGateService::class, function ($app) {
            return new FeatureGateService(tenant() ?? new Tenant);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Disable data wrapping for API resources (required for Inertia)
        JsonResource::withoutWrapping();

        Employee::observe(EmployeeObserver::class);

        $this->registerGates();
        $this->registerPolicies();
        $this->registerEventListeners();
    }

    /**
     * Register event listeners.
     */
    protected function registerEventListeners(): void
    {
        Event::listen(
            ProfilePhotoUploaded::class,
            SyncProfilePhotoToDevices::class
        );

        // Biometric sync record initialization
        Event::listen(
            EmployeeCreated::class,
            InitializeBiometricSyncRecords::class
        );

        // Compliance training event listeners
        Event::listen(
            EmployeeCreated::class,
            EvaluateComplianceOnEmployeeCreate::class
        );

        Event::listen(
            EmployeeDepartmentChanged::class,
            EvaluateComplianceOnDepartmentChange::class
        );

        Event::listen(
            EmployeePositionChanged::class,
            EvaluateComplianceOnPositionChange::class
        );

        Event::listen(
            ComplianceAssignmentCreated::class,
            HandleComplianceAssignmentCreated::class
        );

        Event::listen(
            ComplianceAssignmentCompleted::class,
            HandleComplianceAssignmentCompleted::class
        );

        Event::listen(
            ComplianceAssignmentOverdue::class,
            HandleComplianceAssignmentOverdue::class
        );
    }

    /**
     * Register authorization gates for multi-tenant access control.
     */
    protected function registerGates(): void
    {
        $this->registerBaseGates();
        $this->registerPermissionGates();
        $this->registerCompositeGates();
        $this->registerDocumentGates();
    }

    /**
     * Register base authorization gates (super-admin, tenant-admin, tenant-member).
     */
    protected function registerBaseGates(): void
    {
        // Gate: super-admin - checks is_super_admin flag on user
        Gate::define('super-admin', function (User $user): bool {
            return $user->isSuperAdmin();
        });

        // Alias: is-super-admin - same check as super-admin
        Gate::define('is-super-admin', function (User $user): bool {
            return $user->isSuperAdmin();
        });

        // Gate: tenant-admin - checks TenantUser role='admin' for current tenant
        // Super admins implicitly pass this gate
        Gate::define('tenant-admin', function (User $user): bool {
            if ($user->isSuperAdmin()) {
                return true;
            }

            $tenant = tenant();

            if ($tenant === null) {
                return false;
            }

            $membership = $user->tenants()->where('tenants.id', $tenant->id)->first();

            if ($membership === null) {
                return false;
            }

            $role = $membership->pivot->role;

            // Handle both enum and string values for backward compatibility
            if ($role instanceof TenantUserRole) {
                return $role === TenantUserRole::Admin;
            }

            return $role === 'admin';
        });

        // Gate: tenant-member - checks user belongs to current tenant
        // Super admins implicitly pass this gate
        Gate::define('tenant-member', function (User $user): bool {
            if ($user->isSuperAdmin()) {
                return true;
            }

            $tenant = tenant();

            if ($tenant === null) {
                return false;
            }

            return $user->tenants()->where('tenants.id', $tenant->id)->exists();
        });
    }

    /**
     * Register permission-based gates for role authorization.
     *
     * Each gate checks Super Admin first (bypass all), then verifies
     * the user's role in the current tenant has the required permission.
     */
    protected function registerPermissionGates(): void
    {
        // Employees module gates
        $this->registerPermissionGate('can-view-employees', Permission::EmployeesView);
        $this->registerPermissionGate('can-create-employees', Permission::EmployeesCreate);
        $this->registerPermissionGate('can-edit-employees', Permission::EmployeesEdit);
        $this->registerPermissionGate('can-delete-employees', Permission::EmployeesDelete);

        // Payroll module gates
        $this->registerPermissionGate('can-view-payroll', Permission::PayrollView);
        $this->registerPermissionGate('can-process-payroll', Permission::PayrollProcess);

        // Leaves module gates
        $this->registerPermissionGate('can-view-leaves', Permission::LeavesView);
        $this->registerPermissionGate('can-approve-leaves', Permission::LeavesApprove);

        // Attendance module gate
        $this->registerPermissionGate('can-view-attendance', Permission::AttendanceView);

        // Reports module gate
        $this->registerPermissionGate('can-view-reports', Permission::ReportsView);

        // Settings module gate
        $this->registerPermissionGate('can-manage-settings', Permission::SettingsManage);

        // Users module gate
        $this->registerPermissionGate('can-manage-users', Permission::UsersManage);

        // Organization module gate
        $this->registerPermissionGate('can-manage-organization', Permission::OrganizationManage);

        // Holidays module gate
        $this->registerPermissionGate('can-manage-holidays', Permission::HolidaysManage);

        // Training module gates
        $this->registerPermissionGate('can-view-training', Permission::TrainingView);
        $this->registerPermissionGate('can-manage-training', Permission::TrainingManage);

        // Compliance training gates (uses training permissions)
        $this->registerPermissionGate('can-view-compliance', Permission::TrainingView);
        $this->registerPermissionGate('can-manage-compliance', Permission::TrainingManage);

        // Audit logs gate
        $this->registerPermissionGate('can-view-audit-logs', Permission::AuditLogsView);

        // Biometric devices gates (based on organization management permission)
        Gate::define('view-biometric-devices', function (User $user): bool {
            if ($user->isSuperAdmin()) {
                return true;
            }

            $tenant = tenant();

            if ($tenant === null) {
                return false;
            }

            $role = $user->getRoleInTenant($tenant);

            if ($role === null) {
                return false;
            }

            // Admin, HR Manager, HR Staff, and HR Consultant can view devices
            return in_array($role, [
                TenantUserRole::Admin,
                TenantUserRole::HrManager,
                TenantUserRole::HrStaff,
                TenantUserRole::HrConsultant,
            ], true);
        });

        Gate::define('manage-biometric-devices', function (User $user): bool {
            if ($user->isSuperAdmin()) {
                return true;
            }

            $tenant = tenant();

            if ($tenant === null) {
                return false;
            }

            $role = $user->getRoleInTenant($tenant);

            if ($role === null) {
                return false;
            }

            // Only Admin and HR Manager can manage devices
            return in_array($role, [
                TenantUserRole::Admin,
                TenantUserRole::HrManager,
            ], true);
        });
    }

    /**
     * Register composite gates that combine multiple permissions.
     *
     * These gates are useful for API endpoints that require a
     * user to have all CRUD permissions for a module.
     */
    protected function registerCompositeGates(): void
    {
        // Gate: can-view-hr-analytics - for HR Analytics Dashboard access
        // Admin, HR Manager, and Supervisor can access the analytics dashboard
        Gate::define('can-view-hr-analytics', function (User $user): bool {
            if ($user->isSuperAdmin()) {
                return true;
            }

            $tenant = tenant();

            if ($tenant === null) {
                return false;
            }

            $role = $user->getRoleInTenant($tenant);

            if ($role === null) {
                return false;
            }

            // Admin, HR Manager, and Supervisor can view analytics
            // Supervisor will have their data scoped to their department
            return in_array($role, [
                TenantUserRole::Admin,
                TenantUserRole::HrManager,
                TenantUserRole::Supervisor,
            ], true);
        });

        // Gate: can-view-performance-analytics - for Performance Analytics Dashboard access
        // All roles can view - employees and supervisors see their department only
        Gate::define('can-view-performance-analytics', function (User $user): bool {
            if ($user->isSuperAdmin()) {
                return true;
            }

            $tenant = tenant();

            if ($tenant === null) {
                return false;
            }

            $role = $user->getRoleInTenant($tenant);

            if ($role === null) {
                return false;
            }

            // All authenticated users can view performance analytics
            // Data scoping is handled in the controller
            return in_array($role, [
                TenantUserRole::Admin,
                TenantUserRole::HrManager,
                TenantUserRole::HrStaff,
                TenantUserRole::Supervisor,
                TenantUserRole::Employee,
            ], true);
        });

        // Gate: can-manage-employees - requires all employee permissions
        // This gate is used by EmployeeController for full CRUD access
        Gate::define('can-manage-employees', function (User $user): bool {
            // Super Admin bypasses all permission checks
            if ($user->isSuperAdmin()) {
                return true;
            }

            // Get the current tenant context
            $tenant = tenant();

            // No tenant context means no tenant-level permissions
            if ($tenant === null) {
                return false;
            }

            // Get the user's role in the current tenant
            $role = $user->getRoleInTenant($tenant);

            // User is not a member of this tenant
            if ($role === null) {
                return false;
            }

            // Check if the role has all required employee permissions
            return RolePermissions::roleHasPermission($role, Permission::EmployeesView)
                && RolePermissions::roleHasPermission($role, Permission::EmployeesCreate)
                && RolePermissions::roleHasPermission($role, Permission::EmployeesEdit)
                && RolePermissions::roleHasPermission($role, Permission::EmployeesDelete);
        });
    }

    /**
     * Register document-related authorization gates.
     *
     * These gates control access to employee and company documents
     * based on user roles and relationships.
     */
    protected function registerDocumentGates(): void
    {
        /**
         * Gate: can-view-employee-documents
         *
         * Allows viewing employee documents based on role:
         * - HR Manager, HR Staff: Can view all employee documents
         * - Supervisor: Can view direct reports' documents only
         * - Employee: Can view own documents only
         */
        Gate::define('can-view-employee-documents', function (User $user, Employee $employee): bool {
            // Super Admin bypasses all permission checks
            if ($user->isSuperAdmin()) {
                return true;
            }

            $tenant = tenant();

            if ($tenant === null) {
                return false;
            }

            $role = $user->getRoleInTenant($tenant);

            if ($role === null) {
                return false;
            }

            // Admin, HR Manager, and HR Staff can view all employee documents
            if (in_array($role, [
                TenantUserRole::Admin,
                TenantUserRole::HrManager,
                TenantUserRole::HrStaff,
                TenantUserRole::HrConsultant,
            ], true)) {
                return true;
            }

            // Supervisor can view direct reports' documents
            if ($role === TenantUserRole::Supervisor) {
                // Find the supervisor's employee record
                $supervisorEmployee = Employee::where('user_id', $user->id)->first();

                if ($supervisorEmployee === null) {
                    return false;
                }

                // Check if the target employee is a direct report
                return $employee->supervisor_id === $supervisorEmployee->id;
            }

            // Employee can view their own documents
            if ($role === TenantUserRole::Employee) {
                return $employee->user_id === $user->id;
            }

            return false;
        });

        /**
         * Gate: can-manage-employee-documents
         *
         * Allows managing (upload, delete) employee documents:
         * - HR Manager and HR Staff only
         */
        Gate::define('can-manage-employee-documents', function (User $user, ?Employee $employee = null): bool {
            // Super Admin bypasses all permission checks
            if ($user->isSuperAdmin()) {
                return true;
            }

            $tenant = tenant();

            if ($tenant === null) {
                return false;
            }

            $role = $user->getRoleInTenant($tenant);

            if ($role === null) {
                return false;
            }

            // Only Admin, HR Manager, and HR Staff can manage employee documents
            return in_array($role, [
                TenantUserRole::Admin,
                TenantUserRole::HrManager,
                TenantUserRole::HrStaff,
            ], true);
        });

        /**
         * Gate: can-view-company-documents
         *
         * All authenticated tenant users can view company documents.
         */
        Gate::define('can-view-company-documents', function (User $user): bool {
            // Super Admin bypasses all permission checks
            if ($user->isSuperAdmin()) {
                return true;
            }

            $tenant = tenant();

            if ($tenant === null) {
                return false;
            }

            // All tenant members can view company documents
            return $user->tenants()->where('tenants.id', $tenant->id)->exists();
        });

        /**
         * Gate: can-manage-company-documents
         *
         * Only HR Manager and HR Staff can manage company documents.
         */
        Gate::define('can-manage-company-documents', function (User $user): bool {
            // Super Admin bypasses all permission checks
            if ($user->isSuperAdmin()) {
                return true;
            }

            $tenant = tenant();

            if ($tenant === null) {
                return false;
            }

            $role = $user->getRoleInTenant($tenant);

            if ($role === null) {
                return false;
            }

            // Only Admin, HR Manager, and HR Staff can manage company documents
            return in_array($role, [
                TenantUserRole::Admin,
                TenantUserRole::HrManager,
                TenantUserRole::HrStaff,
            ], true);
        });
    }

    /**
     * Register a single permission-based gate.
     *
     * The gate callback:
     * 1. Checks if user is Super Admin (bypass all checks)
     * 2. Checks if tenant context exists
     * 3. Gets user's role in the current tenant
     * 4. Verifies the role has the required permission
     */
    protected function registerPermissionGate(string $gateName, Permission $permission): void
    {
        Gate::define($gateName, function (User $user) use ($permission): bool {
            // Super Admin bypasses all permission checks
            if ($user->isSuperAdmin()) {
                return true;
            }

            // Get the current tenant context
            $tenant = tenant();

            // No tenant context means no tenant-level permissions
            if ($tenant === null) {
                return false;
            }

            // Get the user's role in the current tenant
            $role = $user->getRoleInTenant($tenant);

            // User is not a member of this tenant
            if ($role === null) {
                return false;
            }

            // Check if the role has the required permission
            return RolePermissions::roleHasPermission($role, $permission);
        });
    }

    /**
     * Register model policies.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(Tenant::class, TenantPolicy::class);
    }
}
