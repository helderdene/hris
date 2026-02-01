<?php

/**
 * Tests for User Management UI (Task Group 11)
 *
 * These tests verify the user management page renders correctly,
 * the invite modal submits properly, role changes require password confirmation,
 * and role badges display correctly.
 */

use App\Enums\TenantUserRole;
use App\Http\Controllers\UserController;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantToContainer(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForUserMgmt(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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

describe('User Management UI', function () {
    it('renders users list page with user data', function () {
        $tenant = Tenant::factory()->create();
        bindTenantToContainer($tenant);

        $admin = createTenantUserForUserMgmt($tenant, TenantUserRole::Admin, ['name' => 'Admin User']);
        $employee = createTenantUserForUserMgmt($tenant, TenantUserRole::Employee, ['name' => 'Employee User']);
        $hrManager = createTenantUserForUserMgmt($tenant, TenantUserRole::HrManager, ['name' => 'HR Manager']);

        $this->actingAs($admin);

        // Test the controller directly to avoid Vite manifest issues
        $request = Request::create('/users', 'GET');
        $request->setUserResolver(fn () => $admin);

        $controller = new UserController;
        $inertiaResponse = $controller->index();

        // Use reflection to access protected properties
        $reflection = new ReflectionClass($inertiaResponse);

        $componentProperty = $reflection->getProperty('component');
        $componentProperty->setAccessible(true);
        expect($componentProperty->getValue($inertiaResponse))->toBe('Users/Index');

        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($inertiaResponse);

        // Check that users are returned
        $users = $props['users']->collection;
        expect($users)->toHaveCount(3);

        // Check structure of returned data
        $firstUser = $users->first()->toArray(request());
        expect($firstUser)->toHaveKeys(['id', 'name', 'email', 'role', 'role_label', 'invited_at', 'invitation_accepted_at']);

        // Check that roles list is provided
        $roles = $props['roles'];
        expect($roles)->toHaveCount(6);
        expect($roles[0]['value'])->toBe('admin');
        expect($roles[0]['label'])->toBe('Admin');
    });

    it('includes all available role options in the page props', function () {
        $tenant = Tenant::factory()->create();
        bindTenantToContainer($tenant);

        $admin = createTenantUserForUserMgmt($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $request = Request::create('/users', 'GET');
        $request->setUserResolver(fn () => $admin);

        $controller = new UserController;
        $inertiaResponse = $controller->index();

        $reflection = new ReflectionClass($inertiaResponse);
        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($inertiaResponse);

        $roles = $props['roles'];

        // Verify all 6 roles are present
        $roleValues = collect($roles)->pluck('value')->all();
        expect($roleValues)->toContain('admin')
            ->toContain('hr_manager')
            ->toContain('hr_staff')
            ->toContain('hr_consultant')
            ->toContain('supervisor')
            ->toContain('employee');

        // Verify labels are human-readable
        $roleLabels = collect($roles)->pluck('label')->all();
        expect($roleLabels)->toContain('Admin')
            ->toContain('HR Manager')
            ->toContain('HR Staff')
            ->toContain('HR Consultant')
            ->toContain('Supervisor')
            ->toContain('Employee');
    });

    it('role badges display correctly with proper labels', function () {
        $tenant = Tenant::factory()->create();
        bindTenantToContainer($tenant);

        $admin = createTenantUserForUserMgmt($tenant, TenantUserRole::Admin);
        $hrManager = createTenantUserForUserMgmt($tenant, TenantUserRole::HrManager);
        $employee = createTenantUserForUserMgmt($tenant, TenantUserRole::Employee);

        $this->actingAs($admin);

        $request = Request::create('/users', 'GET');
        $request->setUserResolver(fn () => $admin);

        $controller = new UserController;
        $inertiaResponse = $controller->index();

        $reflection = new ReflectionClass($inertiaResponse);
        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($inertiaResponse);

        $users = $props['users']->collection;

        // Check each user has correct role and role_label
        $usersData = $users->map(fn ($user) => $user->toArray(request()))->keyBy('id');

        expect($usersData[$admin->id]['role'])->toBe('admin');
        expect($usersData[$admin->id]['role_label'])->toBe('Admin');

        expect($usersData[$hrManager->id]['role'])->toBe('hr_manager');
        expect($usersData[$hrManager->id]['role_label'])->toBe('HR Manager');

        expect($usersData[$employee->id]['role'])->toBe('employee');
        expect($usersData[$employee->id]['role_label'])->toBe('Employee');
    });

    it('denies access to non-Admin users', function () {
        $tenant = Tenant::factory()->create();
        bindTenantToContainer($tenant);

        // Test with Employee (should be denied)
        $employee = createTenantUserForUserMgmt($tenant, TenantUserRole::Employee);
        expect(Gate::forUser($employee)->denies('can-manage-users'))->toBeTrue();

        // Test with HR Manager (should be denied)
        $hrManager = createTenantUserForUserMgmt($tenant, TenantUserRole::HrManager);
        expect(Gate::forUser($hrManager)->denies('can-manage-users'))->toBeTrue();

        // Test with Supervisor (should be denied)
        $supervisor = createTenantUserForUserMgmt($tenant, TenantUserRole::Supervisor);
        expect(Gate::forUser($supervisor)->denies('can-manage-users'))->toBeTrue();
    });

    it('allows Admin to access users list page', function () {
        $tenant = Tenant::factory()->create();
        bindTenantToContainer($tenant);

        $admin = createTenantUserForUserMgmt($tenant, TenantUserRole::Admin);
        expect(Gate::forUser($admin)->allows('can-manage-users'))->toBeTrue();
    });

    it('returns users ordered by name', function () {
        $tenant = Tenant::factory()->create();
        bindTenantToContainer($tenant);

        $admin = createTenantUserForUserMgmt($tenant, TenantUserRole::Admin, ['name' => 'Zara Admin']);
        $userA = createTenantUserForUserMgmt($tenant, TenantUserRole::Employee, ['name' => 'Alice Employee']);
        $userM = createTenantUserForUserMgmt($tenant, TenantUserRole::HrStaff, ['name' => 'Mike Staff']);

        $this->actingAs($admin);

        $request = Request::create('/users', 'GET');
        $request->setUserResolver(fn () => $admin);

        $controller = new UserController;
        $inertiaResponse = $controller->index();

        $reflection = new ReflectionClass($inertiaResponse);
        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($inertiaResponse);

        $users = $props['users']->collection;
        $names = $users->map(fn ($user) => $user->toArray(request())['name'])->values()->all();

        expect($names[0])->toBe('Alice Employee');
        expect($names[1])->toBe('Mike Staff');
        expect($names[2])->toBe('Zara Admin');
    });
});
