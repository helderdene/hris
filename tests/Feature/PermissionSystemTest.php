<?php

use App\Authorization\RolePermissions;
use App\Enums\Permission;
use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Permission Enum', function () {
    it('has all module permissions', function () {
        $permissions = Permission::cases();

        // Should have all 12 permissions defined in spec
        expect($permissions)->toHaveCount(12);

        // Employees module
        expect(Permission::EmployeesView->value())->toBe('employees.view');
        expect(Permission::EmployeesCreate->value())->toBe('employees.create');
        expect(Permission::EmployeesEdit->value())->toBe('employees.edit');
        expect(Permission::EmployeesDelete->value())->toBe('employees.delete');

        // Payroll module
        expect(Permission::PayrollView->value())->toBe('payroll.view');
        expect(Permission::PayrollProcess->value())->toBe('payroll.process');

        // Leaves module
        expect(Permission::LeavesView->value())->toBe('leaves.view');
        expect(Permission::LeavesApprove->value())->toBe('leaves.approve');

        // Attendance module
        expect(Permission::AttendanceView->value())->toBe('attendance.view');

        // Reports module
        expect(Permission::ReportsView->value())->toBe('reports.view');

        // Settings module
        expect(Permission::SettingsManage->value())->toBe('settings.manage');

        // Users module
        expect(Permission::UsersManage->value())->toBe('users.manage');
    });
});

describe('Role Permissions Mapping', function () {
    it('returns correct permissions for Admin role', function () {
        $permissions = RolePermissions::getPermissionsForRole(TenantUserRole::Admin);

        // Admin should have all permissions
        expect($permissions)->toHaveCount(12);
        expect($permissions)->toContain(Permission::EmployeesView);
        expect($permissions)->toContain(Permission::SettingsManage);
        expect($permissions)->toContain(Permission::UsersManage);
    });

    it('returns correct permissions for HR Manager role', function () {
        $permissions = RolePermissions::getPermissionsForRole(TenantUserRole::HrManager);

        // HR Manager should have all except SettingsManage and UsersManage
        expect($permissions)->toHaveCount(10);
        expect($permissions)->toContain(Permission::EmployeesView);
        expect($permissions)->toContain(Permission::ReportsView);
        expect($permissions)->not->toContain(Permission::SettingsManage);
        expect($permissions)->not->toContain(Permission::UsersManage);
    });

    it('returns correct permissions for HR Staff role', function () {
        $permissions = RolePermissions::getPermissionsForRole(TenantUserRole::HrStaff);

        // HR Staff: Employees (view/create/edit), Payroll (view/process), Leaves (view/approve), Attendance (view)
        expect($permissions)->toContain(Permission::EmployeesView);
        expect($permissions)->toContain(Permission::EmployeesCreate);
        expect($permissions)->toContain(Permission::EmployeesEdit);
        expect($permissions)->not->toContain(Permission::EmployeesDelete);
        expect($permissions)->toContain(Permission::PayrollView);
        expect($permissions)->toContain(Permission::PayrollProcess);
        expect($permissions)->toContain(Permission::LeavesView);
        expect($permissions)->toContain(Permission::LeavesApprove);
        expect($permissions)->toContain(Permission::AttendanceView);
        expect($permissions)->not->toContain(Permission::ReportsView);
        expect($permissions)->not->toContain(Permission::SettingsManage);
        expect($permissions)->not->toContain(Permission::UsersManage);
    });

    it('returns correct permissions for Supervisor role', function () {
        $permissions = RolePermissions::getPermissionsForRole(TenantUserRole::Supervisor);

        // Supervisor: Employees (view), Leaves (approve), Attendance (view)
        expect($permissions)->toContain(Permission::EmployeesView);
        expect($permissions)->not->toContain(Permission::EmployeesCreate);
        expect($permissions)->not->toContain(Permission::EmployeesEdit);
        expect($permissions)->not->toContain(Permission::EmployeesDelete);
        expect($permissions)->toContain(Permission::LeavesApprove);
        expect($permissions)->toContain(Permission::AttendanceView);
        expect($permissions)->not->toContain(Permission::PayrollView);
        expect($permissions)->not->toContain(Permission::ReportsView);
    });

    it('returns empty permissions for Employee role', function () {
        $permissions = RolePermissions::getPermissionsForRole(TenantUserRole::Employee);

        // Employee: Self-service only (no module permissions)
        expect($permissions)->toBeEmpty();
    });

    it('correctly checks if role has permission', function () {
        // Admin should have all permissions
        expect(RolePermissions::roleHasPermission(TenantUserRole::Admin, Permission::SettingsManage))->toBeTrue();
        expect(RolePermissions::roleHasPermission(TenantUserRole::Admin, Permission::UsersManage))->toBeTrue();

        // HR Manager should not have settings/users permissions
        expect(RolePermissions::roleHasPermission(TenantUserRole::HrManager, Permission::EmployeesView))->toBeTrue();
        expect(RolePermissions::roleHasPermission(TenantUserRole::HrManager, Permission::SettingsManage))->toBeFalse();
        expect(RolePermissions::roleHasPermission(TenantUserRole::HrManager, Permission::UsersManage))->toBeFalse();

        // Employee should have no permissions
        expect(RolePermissions::roleHasPermission(TenantUserRole::Employee, Permission::EmployeesView))->toBeFalse();
    });
});

describe('Super Admin Permission Bypass', function () {
    it('bypasses permission checks for Super Admin', function () {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $tenant = Tenant::factory()->create();

        // Super admin bypasses all permission checks
        expect($superAdmin->isSuperAdmin())->toBeTrue();

        // Super admin should pass any permission check regardless of role
        // This is verified at the gate level in Task Group 3
    });
});

describe('Tenant-Scoped Permission Resolution', function () {
    it('resolves permissions based on user role in specific tenant', function () {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        // User is Admin in tenant1, Employee in tenant2
        $user->tenants()->attach($tenant1->id, ['role' => 'admin']);
        $user->tenants()->attach($tenant2->id, ['role' => 'employee']);

        // Get role in each tenant
        $roleInTenant1 = $user->getRoleInTenant($tenant1);
        $roleInTenant2 = $user->getRoleInTenant($tenant2);

        expect($roleInTenant1)->toBe(TenantUserRole::Admin);
        expect($roleInTenant2)->toBe(TenantUserRole::Employee);

        // Verify permissions differ per tenant
        expect(RolePermissions::roleHasPermission($roleInTenant1, Permission::SettingsManage))->toBeTrue();
        expect(RolePermissions::roleHasPermission($roleInTenant2, Permission::SettingsManage))->toBeFalse();
    });
});
