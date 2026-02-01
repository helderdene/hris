<?php

/**
 * Tests for Organization Navigation and Integration (Task Group 7)
 *
 * These tests verify:
 * - Organization sidebar section appears with correct items (authorization-based visibility)
 * - Navigation authorization to Organization sub-pages works correctly
 * - Breadcrumb structure is correct on Organization pages
 * - Page access respects authorization (can_manage_organization permission)
 */

use App\Authorization\RolePermissions;
use App\Enums\Permission;
use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantForNavTests(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createUserForNavTests(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
{
    $user = User::factory()->create($userAttributes);
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
});

describe('Organization Navigation and Integration', function () {
    it('sidebar visibility depends on can_manage_organization permission', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForNavTests($tenant);

        // Admin should see Organization section
        $admin = createUserForNavTests($tenant, TenantUserRole::Admin);
        expect(Gate::forUser($admin)->allows('can-manage-organization'))->toBeTrue();

        // HR Manager should see Organization section
        $hrManager = createUserForNavTests($tenant, TenantUserRole::HrManager);
        expect(Gate::forUser($hrManager)->allows('can-manage-organization'))->toBeTrue();

        // Employee should NOT see Organization section
        $employee = createUserForNavTests($tenant, TenantUserRole::Employee);
        expect(Gate::forUser($employee)->denies('can-manage-organization'))->toBeTrue();

        // Supervisor should NOT see Organization section
        $supervisor = createUserForNavTests($tenant, TenantUserRole::Supervisor);
        expect(Gate::forUser($supervisor)->denies('can-manage-organization'))->toBeTrue();

        // HR Staff should see Organization section (they have the permission)
        $hrStaff = createUserForNavTests($tenant, TenantUserRole::HrStaff);
        $hasPermission = RolePermissions::roleHasPermission(TenantUserRole::HrStaff, Permission::OrganizationManage);
        expect(Gate::forUser($hrStaff)->allows('can-manage-organization'))->toBe($hasPermission);
    });

    it('allows navigation to all Organization sub-pages for authorized users', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-org']);
        bindTenantForNavTests($tenant);

        $admin = createUserForNavTests($tenant, TenantUserRole::Admin);

        // All Organization routes should be accessible to admin
        $routes = [
            'organization.departments.index',
            'organization.positions.index',
            'organization.salary-grades.index',
            'organization.locations.index',
            'organization.org-chart',
        ];

        foreach ($routes as $routeName) {
            // Routes exist and can be generated with tenant parameter
            expect(route($routeName, ['tenant' => $tenant->slug]))->toBeString();
        }

        // All routes should be accessible via the can-manage-organization gate
        expect(Gate::forUser($admin)->allows('can-manage-organization'))->toBeTrue();
    });

    it('denies Employee access to Organization pages via gate', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForNavTests($tenant);

        $employee = createUserForNavTests($tenant, TenantUserRole::Employee);

        // Employee should be denied by the gate
        expect(Gate::forUser($employee)->denies('can-manage-organization'))->toBeTrue();

        // This would cause authorization exception if attempted
        // The gate is enforced in all Organization controllers
    });

    it('Organization pages have correct breadcrumb URL structure', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-org']);
        bindTenantForNavTests($tenant);

        // Verify that Organization pages follow the correct breadcrumb pattern:
        // Dashboard > Organization > [Current Page]

        // This test verifies the route structure and naming conventions
        // which are used in the breadcrumb definitions in each Vue component

        $expectedRoutes = [
            'organization.departments.index' => '/organization/departments',
            'organization.positions.index' => '/organization/positions',
            'organization.salary-grades.index' => '/organization/salary-grades',
            'organization.locations.index' => '/organization/locations',
            'organization.org-chart' => '/organization/org-chart',
        ];

        foreach ($expectedRoutes as $routeName => $expectedPath) {
            // Verify routes are named correctly and can be generated
            $url = route($routeName, ['tenant' => $tenant->slug], false);

            // Verify URL structure follows /organization/{section}
            expect($url)->toBe($expectedPath);
        }

        // Verify dashboard link format
        expect(route('tenant.dashboard', ['tenant' => $tenant->slug], false))->toBe('/dashboard');
    });
});
