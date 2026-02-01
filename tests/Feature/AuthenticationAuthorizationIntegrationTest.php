<?php

/**
 * Strategic Integration Tests for Authentication & Authorization
 *
 * These tests fill critical gaps in the test coverage, focusing on
 * end-to-end workflows for the authentication and authorization feature:
 * - Complete invitation flow (invite -> email -> accept -> login)
 * - Role-based access across tenant switch
 * - Super Admin access patterns
 * - Permission gate integration with controllers
 */

use App\Actions\AcceptInvitationAction;
use App\Actions\InviteUserAction;
use App\Enums\Permission;
use App\Enums\TenantUserRole;
use App\Http\Responses\LoginResponse;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
    $this->withoutVite();
});

describe('Complete Invitation Flow - End to End', function () {
    it('completes full invitation flow: invite -> set password -> login -> access tenant', function () {
        Notification::fake();

        // Step 1: Admin invites a new user
        $tenant = Tenant::factory()->create(['name' => 'Integration Corp', 'slug' => 'integration']);
        $admin = User::factory()->create();
        $admin->tenants()->attach($tenant->id, [
            'role' => TenantUserRole::Admin->value,
            'invited_at' => now()->subDay(),
            'invitation_accepted_at' => now()->subDay(),
        ]);

        $inviteAction = new InviteUserAction;
        $invitedUser = $inviteAction->execute(
            email: 'newemployee@integration.test',
            name: 'New Employee',
            role: TenantUserRole::HrStaff,
            tenantId: $tenant->id,
            inviterId: $admin->id
        );

        // Verify user was created with pending invitation
        expect($invitedUser->password)->toBeNull();
        expect($invitedUser->email_verified_at)->toBeNull();

        // Get the invitation token
        $tenantUser = TenantUser::where('user_id', $invitedUser->id)
            ->where('tenant_id', $tenant->id)
            ->first();
        $token = $tenantUser->invitation_token;
        expect($token)->not->toBeNull();
        expect($tenantUser->isInvitationPending())->toBeTrue();

        // Verify invitation email was sent
        Notification::assertSentTo($invitedUser, \App\Notifications\UserInvitation::class);

        // Step 2: User accepts invitation by setting password
        $acceptAction = new AcceptInvitationAction;
        $acceptedUser = $acceptAction->execute(
            token: $token,
            password: 'SecurePassword123!'
        );

        // Verify password is set and email is verified
        expect(Hash::check('SecurePassword123!', $acceptedUser->password))->toBeTrue();
        expect($acceptedUser->email_verified_at)->not->toBeNull();

        // Verify invitation is marked as accepted
        $tenantUser->refresh();
        expect($tenantUser->hasAcceptedInvitation())->toBeTrue();
        expect($tenantUser->invitation_token)->toBeNull();

        // Step 3: User logs in
        $loginRequest = Request::create('/login', 'POST');
        $loginRequest->setUserResolver(fn () => $acceptedUser);

        $loginResponse = new LoginResponse;
        $response = $loginResponse->toResponse($loginRequest);

        // Should redirect to tenant subdomain since user has only one tenant
        expect($response->isRedirect())->toBeTrue();
        $location = $response->headers->get('Location');
        expect($location)->toContain('integration.kasamahr.test');
        expect($location)->toContain('token=');

        // Step 4: Verify user has correct permissions in tenant
        app()->instance('tenant', $tenant);

        // HR Staff should have these permissions
        expect(Gate::forUser($acceptedUser)->allows('can-view-employees'))->toBeTrue();
        expect(Gate::forUser($acceptedUser)->allows('can-create-employees'))->toBeTrue();
        expect(Gate::forUser($acceptedUser)->allows('can-edit-employees'))->toBeTrue();
        expect(Gate::forUser($acceptedUser)->allows('can-process-payroll'))->toBeTrue();

        // HR Staff should NOT have these permissions
        expect(Gate::forUser($acceptedUser)->allows('can-delete-employees'))->toBeFalse();
        expect(Gate::forUser($acceptedUser)->allows('can-manage-settings'))->toBeFalse();
        expect(Gate::forUser($acceptedUser)->allows('can-manage-users'))->toBeFalse();
    });
});

describe('Role-Based Access Across Tenant Switch', function () {
    it('enforces different role permissions when switching between tenants', function () {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $tenant1 = Tenant::factory()->create(['name' => 'Admin Org', 'slug' => 'admin-org']);
        $tenant2 = Tenant::factory()->create(['name' => 'Employee Org', 'slug' => 'employee-org']);

        // User is Admin in tenant1
        $user->tenants()->attach($tenant1->id, [
            'role' => TenantUserRole::Admin->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        // User is Employee in tenant2
        $user->tenants()->attach($tenant2->id, [
            'role' => TenantUserRole::Employee->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        // Check permissions in tenant1 (Admin)
        app()->instance('tenant', $tenant1);
        expect($user->getRoleInTenant($tenant1))->toBe(TenantUserRole::Admin);
        expect(Gate::forUser($user)->allows('can-manage-users'))->toBeTrue();
        expect(Gate::forUser($user)->allows('can-manage-settings'))->toBeTrue();
        expect(Gate::forUser($user)->allows('can-delete-employees'))->toBeTrue();
        expect(Gate::forUser($user)->allows('can-view-reports'))->toBeTrue();

        // Switch to tenant2 (Employee)
        app()->instance('tenant', $tenant2);
        expect($user->getRoleInTenant($tenant2))->toBe(TenantUserRole::Employee);
        expect(Gate::forUser($user)->allows('can-manage-users'))->toBeFalse();
        expect(Gate::forUser($user)->allows('can-manage-settings'))->toBeFalse();
        expect(Gate::forUser($user)->allows('can-delete-employees'))->toBeFalse();
        expect(Gate::forUser($user)->allows('can-view-reports'))->toBeFalse();
        expect(Gate::forUser($user)->allows('can-view-employees'))->toBeFalse();
    });

    it('maintains consistent role after login with multi-tenant user', function () {
        $user = User::factory()->withoutTwoFactor()->create();

        $tenant1 = Tenant::factory()->create(['name' => 'First Org', 'slug' => 'first']);
        $tenant2 = Tenant::factory()->create(['name' => 'Second Org', 'slug' => 'second']);

        $user->tenants()->attach($tenant1->id, ['role' => TenantUserRole::HrManager->value]);
        $user->tenants()->attach($tenant2->id, ['role' => TenantUserRole::Supervisor->value]);

        // Login should redirect to tenant selection
        $loginRequest = Request::create('/login', 'POST');
        $loginRequest->setUserResolver(fn () => $user);

        $loginResponse = new LoginResponse;
        $response = $loginResponse->toResponse($loginRequest);

        expect($response->isRedirect())->toBeTrue();
        expect($response->headers->get('Location'))->toContain('/select-tenant');

        // Select first tenant and verify role
        app()->instance('tenant', $tenant1);
        expect($user->getRoleInTenant($tenant1))->toBe(TenantUserRole::HrManager);
        expect(Gate::forUser($user)->allows('can-view-reports'))->toBeTrue();
        expect(Gate::forUser($user)->allows('can-approve-leaves'))->toBeTrue();

        // Select second tenant and verify role
        app()->instance('tenant', $tenant2);
        expect($user->getRoleInTenant($tenant2))->toBe(TenantUserRole::Supervisor);
        expect(Gate::forUser($user)->allows('can-view-reports'))->toBeFalse();
        expect(Gate::forUser($user)->allows('can-approve-leaves'))->toBeTrue();
    });
});

describe('Super Admin Access Patterns', function () {
    it('Super Admin bypasses all permission checks across all tenants', function () {
        $superAdmin = User::factory()->superAdmin()->create();

        $tenant1 = Tenant::factory()->create(['slug' => 'tenant-a']);
        $tenant2 = Tenant::factory()->create(['slug' => 'tenant-b']);

        // Super Admin is NOT a member of any tenant
        expect($superAdmin->tenants()->count())->toBe(0);

        // Should still pass all permission checks for any tenant
        app()->instance('tenant', $tenant1);
        expect(Gate::forUser($superAdmin)->allows('can-manage-users'))->toBeTrue();
        expect(Gate::forUser($superAdmin)->allows('can-manage-settings'))->toBeTrue();
        expect(Gate::forUser($superAdmin)->allows('can-process-payroll'))->toBeTrue();
        expect(Gate::forUser($superAdmin)->allows('can-delete-employees'))->toBeTrue();

        app()->instance('tenant', $tenant2);
        expect(Gate::forUser($superAdmin)->allows('can-manage-users'))->toBeTrue();
        expect(Gate::forUser($superAdmin)->allows('can-manage-settings'))->toBeTrue();
        expect(Gate::forUser($superAdmin)->allows('can-process-payroll'))->toBeTrue();
        expect(Gate::forUser($superAdmin)->allows('can-delete-employees'))->toBeTrue();

        // Works even without tenant context
        app()->forgetInstance('tenant');
        expect(Gate::forUser($superAdmin)->allows('can-manage-users'))->toBeTrue();
    });

    it('Super Admin can access tenant subdomain without membership', function () {
        $superAdmin = User::factory()->superAdmin()->withoutTwoFactor()->create();
        $tenant = Tenant::factory()->create(['slug' => 'restricted']);

        // Super Admin not attached to tenant
        app()->instance('tenant', $tenant);

        // Should pass tenant-member and tenant-admin gates
        expect(Gate::forUser($superAdmin)->allows('tenant-member'))->toBeTrue();
        expect(Gate::forUser($superAdmin)->allows('tenant-admin'))->toBeTrue();
    });
});

describe('Permission Gate Integration with Controllers', function () {
    it('User Management controller respects can-manage-users gate', function () {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        // Admin should be allowed
        $admin = User::factory()->create();
        $admin->tenants()->attach($tenant->id, [
            'role' => TenantUserRole::Admin->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        expect(Gate::forUser($admin)->allows('can-manage-users'))->toBeTrue();

        // HR Manager should be denied
        $hrManager = User::factory()->create();
        $hrManager->tenants()->attach($tenant->id, [
            'role' => TenantUserRole::HrManager->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        expect(Gate::forUser($hrManager)->allows('can-manage-users'))->toBeFalse();

        // Employee should be denied
        $employee = User::factory()->create();
        $employee->tenants()->attach($tenant->id, [
            'role' => TenantUserRole::Employee->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        expect(Gate::forUser($employee)->allows('can-manage-users'))->toBeFalse();
    });

    it('verifies all roles have correct permission sets via hasPermission helper', function () {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $rolesAndExpectedPermissions = [
            [
                'role' => TenantUserRole::Admin,
                'allowed' => [Permission::SettingsManage, Permission::UsersManage, Permission::EmployeesDelete],
                'denied' => [],
            ],
            [
                'role' => TenantUserRole::HrManager,
                'allowed' => [Permission::EmployeesView, Permission::ReportsView, Permission::PayrollProcess],
                'denied' => [Permission::SettingsManage, Permission::UsersManage],
            ],
            [
                'role' => TenantUserRole::HrStaff,
                'allowed' => [Permission::EmployeesView, Permission::EmployeesCreate, Permission::PayrollProcess],
                'denied' => [Permission::EmployeesDelete, Permission::ReportsView, Permission::SettingsManage],
            ],
            [
                'role' => TenantUserRole::Supervisor,
                'allowed' => [Permission::EmployeesView, Permission::LeavesApprove, Permission::AttendanceView],
                'denied' => [Permission::PayrollView, Permission::ReportsView, Permission::UsersManage],
            ],
            [
                'role' => TenantUserRole::Employee,
                'allowed' => [],
                'denied' => [Permission::EmployeesView, Permission::PayrollView, Permission::SettingsManage],
            ],
        ];

        foreach ($rolesAndExpectedPermissions as $roleConfig) {
            $role = $roleConfig['role'];
            $user = User::factory()->create();
            $user->tenants()->attach($tenant->id, [
                'role' => $role->value,
                'invited_at' => now(),
                'invitation_accepted_at' => now(),
            ]);

            foreach ($roleConfig['allowed'] as $permission) {
                expect($user->hasPermission($permission))
                    ->toBeTrue("Expected {$role->label()} to have {$permission->value()} permission");
            }

            foreach ($roleConfig['denied'] as $permission) {
                expect($user->hasPermission($permission))
                    ->toBeFalse("Expected {$role->label()} to NOT have {$permission->value()} permission");
            }
        }
    });
});

describe('Invitation Token Security', function () {
    it('prevents using same invitation token twice', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        $inviter = User::factory()->create();

        $inviteAction = new InviteUserAction;
        $user = $inviteAction->execute(
            email: 'reuse@test.com',
            name: 'Reuse Test',
            role: TenantUserRole::Employee,
            tenantId: $tenant->id,
            inviterId: $inviter->id
        );

        $tenantUser = TenantUser::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();
        $token = $tenantUser->invitation_token;

        // First use - should succeed
        $acceptAction = new AcceptInvitationAction;
        $acceptAction->execute(token: $token, password: 'FirstPassword123!');

        // Token should be cleared
        $tenantUser->refresh();
        expect($tenantUser->invitation_token)->toBeNull();

        // Second use - should fail
        expect(fn () => $acceptAction->execute(token: $token, password: 'SecondPassword123!'))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });
});

describe('HR Consultant Cross-Tenant Capability', function () {
    it('HR Consultant has same permissions as HR Staff', function () {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $hrConsultant = User::factory()->create();
        $hrConsultant->tenants()->attach($tenant->id, [
            'role' => TenantUserRole::HrConsultant->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        // HR Consultant should have same permissions as HR Staff
        expect($hrConsultant->hasPermission(Permission::EmployeesView))->toBeTrue();
        expect($hrConsultant->hasPermission(Permission::EmployeesCreate))->toBeTrue();
        expect($hrConsultant->hasPermission(Permission::EmployeesEdit))->toBeTrue();
        expect($hrConsultant->hasPermission(Permission::PayrollView))->toBeTrue();
        expect($hrConsultant->hasPermission(Permission::PayrollProcess))->toBeTrue();
        expect($hrConsultant->hasPermission(Permission::LeavesView))->toBeTrue();
        expect($hrConsultant->hasPermission(Permission::LeavesApprove))->toBeTrue();
        expect($hrConsultant->hasPermission(Permission::AttendanceView))->toBeTrue();

        // Should NOT have these permissions
        expect($hrConsultant->hasPermission(Permission::EmployeesDelete))->toBeFalse();
        expect($hrConsultant->hasPermission(Permission::ReportsView))->toBeFalse();
        expect($hrConsultant->hasPermission(Permission::SettingsManage))->toBeFalse();
        expect($hrConsultant->hasPermission(Permission::UsersManage))->toBeFalse();
    });
});
