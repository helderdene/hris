<?php

use App\Enums\Permission;
use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

/**
 * Helper to bind a tenant to the application container.
 */
function bindTenant(?Tenant $tenant): void
{
    if ($tenant !== null) {
        app()->instance('tenant', $tenant);
    } else {
        app()->forgetInstance('tenant');
    }
}

describe('Authorization Gates - Employee Management', function () {
    it('allows Admin to manage employees', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => TenantUserRole::Admin->value]);

        bindTenant($tenant);

        expect(Gate::forUser($user)->allows('can-view-employees'))->toBeTrue();
        expect(Gate::forUser($user)->allows('can-create-employees'))->toBeTrue();
        expect(Gate::forUser($user)->allows('can-edit-employees'))->toBeTrue();
        expect(Gate::forUser($user)->allows('can-delete-employees'))->toBeTrue();
    });

    it('allows HR Manager to manage employees', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => TenantUserRole::HrManager->value]);

        bindTenant($tenant);

        expect(Gate::forUser($user)->allows('can-view-employees'))->toBeTrue();
        expect(Gate::forUser($user)->allows('can-create-employees'))->toBeTrue();
        expect(Gate::forUser($user)->allows('can-edit-employees'))->toBeTrue();
        expect(Gate::forUser($user)->allows('can-delete-employees'))->toBeTrue();
    });

    it('allows HR Staff to view, create, and edit employees but not delete', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => TenantUserRole::HrStaff->value]);

        bindTenant($tenant);

        expect(Gate::forUser($user)->allows('can-view-employees'))->toBeTrue();
        expect(Gate::forUser($user)->allows('can-create-employees'))->toBeTrue();
        expect(Gate::forUser($user)->allows('can-edit-employees'))->toBeTrue();
        expect(Gate::forUser($user)->allows('can-delete-employees'))->toBeFalse();
    });

    it('allows Supervisor to view employees only', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => TenantUserRole::Supervisor->value]);

        bindTenant($tenant);

        expect(Gate::forUser($user)->allows('can-view-employees'))->toBeTrue();
        expect(Gate::forUser($user)->allows('can-create-employees'))->toBeFalse();
        expect(Gate::forUser($user)->allows('can-edit-employees'))->toBeFalse();
        expect(Gate::forUser($user)->allows('can-delete-employees'))->toBeFalse();
    });

    it('denies Employee role from managing employees', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => TenantUserRole::Employee->value]);

        bindTenant($tenant);

        expect(Gate::forUser($user)->allows('can-view-employees'))->toBeFalse();
        expect(Gate::forUser($user)->allows('can-create-employees'))->toBeFalse();
        expect(Gate::forUser($user)->allows('can-edit-employees'))->toBeFalse();
        expect(Gate::forUser($user)->allows('can-delete-employees'))->toBeFalse();
    });
});

describe('Authorization Gates - Payroll Processing', function () {
    it('allows Super Admin to process payroll regardless of tenant role', function () {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $tenant = Tenant::factory()->create();

        bindTenant($tenant);

        expect(Gate::forUser($superAdmin)->allows('can-view-payroll'))->toBeTrue();
        expect(Gate::forUser($superAdmin)->allows('can-process-payroll'))->toBeTrue();
    });

    it('allows HR Staff to process payroll', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => TenantUserRole::HrStaff->value]);

        bindTenant($tenant);

        expect(Gate::forUser($user)->allows('can-view-payroll'))->toBeTrue();
        expect(Gate::forUser($user)->allows('can-process-payroll'))->toBeTrue();
    });

    it('denies Supervisor from processing payroll', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => TenantUserRole::Supervisor->value]);

        bindTenant($tenant);

        expect(Gate::forUser($user)->allows('can-view-payroll'))->toBeFalse();
        expect(Gate::forUser($user)->allows('can-process-payroll'))->toBeFalse();
    });
});

describe('Authorization Gates - Leave Approval with Tenant Context', function () {
    it('allows HR Manager to approve leaves', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => TenantUserRole::HrManager->value]);

        bindTenant($tenant);

        expect(Gate::forUser($user)->allows('can-view-leaves'))->toBeTrue();
        expect(Gate::forUser($user)->allows('can-approve-leaves'))->toBeTrue();
    });

    it('allows Supervisor to approve leaves', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => TenantUserRole::Supervisor->value]);

        bindTenant($tenant);

        // Supervisor has LeavesApprove permission, but not LeavesView
        expect(Gate::forUser($user)->allows('can-view-leaves'))->toBeFalse();
        expect(Gate::forUser($user)->allows('can-approve-leaves'))->toBeTrue();
    });
});

describe('Authorization Gates - User Management (Admin Only)', function () {
    it('allows only Admin to manage users', function () {
        $admin = User::factory()->create();
        $hrManager = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $admin->tenants()->attach($tenant->id, ['role' => TenantUserRole::Admin->value]);
        $hrManager->tenants()->attach($tenant->id, ['role' => TenantUserRole::HrManager->value]);

        bindTenant($tenant);

        expect(Gate::forUser($admin)->allows('can-manage-users'))->toBeTrue();
        expect(Gate::forUser($hrManager)->allows('can-manage-users'))->toBeFalse();
    });

    it('allows only Admin to manage settings', function () {
        $admin = User::factory()->create();
        $hrManager = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $admin->tenants()->attach($tenant->id, ['role' => TenantUserRole::Admin->value]);
        $hrManager->tenants()->attach($tenant->id, ['role' => TenantUserRole::HrManager->value]);

        bindTenant($tenant);

        expect(Gate::forUser($admin)->allows('can-manage-settings'))->toBeTrue();
        expect(Gate::forUser($hrManager)->allows('can-manage-settings'))->toBeFalse();
    });
});

describe('Authorization Gates - No Tenant Context', function () {
    it('returns false when no tenant context is available', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => TenantUserRole::Admin->value]);

        // Ensure no tenant is bound
        bindTenant(null);

        expect(Gate::forUser($user)->allows('can-view-employees'))->toBeFalse();
        expect(Gate::forUser($user)->allows('can-process-payroll'))->toBeFalse();
        expect(Gate::forUser($user)->allows('can-manage-users'))->toBeFalse();
    });

    it('allows Super Admin even without tenant context', function () {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);

        // No tenant bound
        bindTenant(null);

        // Super Admin bypasses tenant requirement for permission checks
        expect(Gate::forUser($superAdmin)->allows('can-view-employees'))->toBeTrue();
        expect(Gate::forUser($superAdmin)->allows('can-process-payroll'))->toBeTrue();
        expect(Gate::forUser($superAdmin)->allows('can-manage-users'))->toBeTrue();
    });
});

describe('Authorization Gates - Reports and Attendance', function () {
    it('allows HR Manager to view reports', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => TenantUserRole::HrManager->value]);

        bindTenant($tenant);

        expect(Gate::forUser($user)->allows('can-view-reports'))->toBeTrue();
    });

    it('denies HR Staff from viewing reports', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => TenantUserRole::HrStaff->value]);

        bindTenant($tenant);

        expect(Gate::forUser($user)->allows('can-view-reports'))->toBeFalse();
    });

    it('allows Supervisor to view attendance', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => TenantUserRole::Supervisor->value]);

        bindTenant($tenant);

        expect(Gate::forUser($user)->allows('can-view-attendance'))->toBeTrue();
    });
});

describe('User Model Permission Helper Methods', function () {
    it('hasPermission checks permission in current tenant context', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => TenantUserRole::HrManager->value]);

        bindTenant($tenant);

        expect($user->hasPermission(Permission::EmployeesView))->toBeTrue();
        expect($user->hasPermission(Permission::SettingsManage))->toBeFalse();
    });

    it('hasPermissionInTenant checks permission in specific tenant', function () {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        $user->tenants()->attach($tenant1->id, ['role' => TenantUserRole::Admin->value]);
        $user->tenants()->attach($tenant2->id, ['role' => TenantUserRole::Employee->value]);

        // Admin has settings permission
        expect($user->hasPermissionInTenant(Permission::SettingsManage, $tenant1))->toBeTrue();
        // Employee does not
        expect($user->hasPermissionInTenant(Permission::SettingsManage, $tenant2))->toBeFalse();
    });

    it('convenience methods work correctly', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => TenantUserRole::HrStaff->value]);

        bindTenant($tenant);

        // HR Staff permissions
        expect($user->canViewEmployees())->toBeTrue();
        expect($user->canCreateEmployees())->toBeTrue();
        expect($user->canEditEmployees())->toBeTrue();
        expect($user->canDeleteEmployees())->toBeFalse();
        expect($user->canViewPayroll())->toBeTrue();
        expect($user->canProcessPayroll())->toBeTrue();
        expect($user->canViewLeaves())->toBeTrue();
        expect($user->canApproveLeaves())->toBeTrue();
        expect($user->canViewAttendance())->toBeTrue();
        expect($user->canViewReports())->toBeFalse();
        expect($user->canManageSettings())->toBeFalse();
        expect($user->canManageUsers())->toBeFalse();
    });

    it('Super Admin bypasses all permission checks via helper methods', function () {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);

        // No tenant context needed for Super Admin
        bindTenant(null);

        expect($superAdmin->hasPermission(Permission::SettingsManage))->toBeTrue();
        expect($superAdmin->canManageSettings())->toBeTrue();
        expect($superAdmin->canManageUsers())->toBeTrue();
        expect($superAdmin->canProcessPayroll())->toBeTrue();
    });
});
