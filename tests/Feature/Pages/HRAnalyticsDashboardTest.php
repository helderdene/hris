<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\HRAnalyticsDashboardController;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EmployeeDashboardService;
use App\Services\HRAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForAnalyticsDashboard(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForAnalyticsDashboard(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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

    \Illuminate\Support\Facades\Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('HRAnalyticsDashboardController', function () {
    describe('authorization', function () {
        it('allows access to Admin users', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForAnalyticsDashboard($tenant);

            $admin = createTenantUserForAnalyticsDashboard($tenant, TenantUserRole::Admin);
            $this->actingAs($admin);

            $controller = new HRAnalyticsDashboardController(
                new HRAnalyticsService(new EmployeeDashboardService)
            );
            $response = $controller->index(request());

            $reflection = new ReflectionClass($response);
            $componentProperty = $reflection->getProperty('component');
            $componentProperty->setAccessible(true);

            expect($componentProperty->getValue($response))->toBe('Hr/AnalyticsDashboard');
        });

        it('allows access to HR Manager users', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForAnalyticsDashboard($tenant);

            $hrManager = createTenantUserForAnalyticsDashboard($tenant, TenantUserRole::HrManager);
            $this->actingAs($hrManager);

            $controller = new HRAnalyticsDashboardController(
                new HRAnalyticsService(new EmployeeDashboardService)
            );
            $response = $controller->index(request());

            $reflection = new ReflectionClass($response);
            $componentProperty = $reflection->getProperty('component');
            $componentProperty->setAccessible(true);

            expect($componentProperty->getValue($response))->toBe('Hr/AnalyticsDashboard');
        });

        it('allows access to Supervisor users with scoped data', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForAnalyticsDashboard($tenant);

            $department = Department::factory()->create();
            $supervisor = createTenantUserForAnalyticsDashboard($tenant, TenantUserRole::Supervisor);
            Employee::factory()->create([
                'user_id' => $supervisor->id,
                'department_id' => $department->id,
            ]);

            $this->actingAs($supervisor);

            $controller = new HRAnalyticsDashboardController(
                new HRAnalyticsService(new EmployeeDashboardService)
            );
            $response = $controller->index(request());

            $reflection = new ReflectionClass($response);
            $componentProperty = $reflection->getProperty('component');
            $componentProperty->setAccessible(true);

            expect($componentProperty->getValue($response))->toBe('Hr/AnalyticsDashboard');
        });

        it('denies access to Employee role users', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForAnalyticsDashboard($tenant);

            $employee = createTenantUserForAnalyticsDashboard($tenant, TenantUserRole::Employee);
            $this->actingAs($employee);

            $controller = new HRAnalyticsDashboardController(
                new HRAnalyticsService(new EmployeeDashboardService)
            );

            expect(fn () => $controller->index(request()))
                ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
        });

        it('denies access to HR Staff users', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForAnalyticsDashboard($tenant);

            $hrStaff = createTenantUserForAnalyticsDashboard($tenant, TenantUserRole::HrStaff);
            $this->actingAs($hrStaff);

            $controller = new HRAnalyticsDashboardController(
                new HRAnalyticsService(new EmployeeDashboardService)
            );

            expect(fn () => $controller->index(request()))
                ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
        });
    });

    describe('data structure', function () {
        it('returns all required props for the dashboard', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForAnalyticsDashboard($tenant);

            $admin = createTenantUserForAnalyticsDashboard($tenant, TenantUserRole::Admin);
            $this->actingAs($admin);

            Employee::factory()->count(5)->active()->create();

            $controller = new HRAnalyticsDashboardController(
                new HRAnalyticsService(new EmployeeDashboardService)
            );
            $response = $controller->index(request());

            $reflection = new ReflectionClass($response);
            $propsProperty = $reflection->getProperty('props');
            $propsProperty->setAccessible(true);
            $props = $propsProperty->getValue($response);

            // Verify required props are present
            expect($props)->toHaveKey('filters')
                ->and($props)->toHaveKey('departments')
                ->and($props)->toHaveKey('headcount');

            // Verify headcount structure
            expect($props['headcount'])->toHaveKey('total')
                ->and($props['headcount'])->toHaveKey('active')
                ->and($props['headcount'])->toHaveKey('newHires')
                ->and($props['headcount'])->toHaveKey('separations');
        });

        it('returns deferred props for heavy metrics', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForAnalyticsDashboard($tenant);

            $admin = createTenantUserForAnalyticsDashboard($tenant, TenantUserRole::Admin);
            $this->actingAs($admin);

            $controller = new HRAnalyticsDashboardController(
                new HRAnalyticsService(new EmployeeDashboardService)
            );
            $response = $controller->index(request());

            $reflection = new ReflectionClass($response);
            $propsProperty = $reflection->getProperty('props');
            $propsProperty->setAccessible(true);
            $props = $propsProperty->getValue($response);

            // Deferred props should be closures
            expect($props['attendance'])->toBeInstanceOf(\Inertia\DeferProp::class);
            expect($props['leave'])->toBeInstanceOf(\Inertia\DeferProp::class);
            expect($props['compensation'])->toBeInstanceOf(\Inertia\DeferProp::class);
            expect($props['recruitment'])->toBeInstanceOf(\Inertia\DeferProp::class);
            expect($props['performance'])->toBeInstanceOf(\Inertia\DeferProp::class);
        });
    });

    describe('department filtering', function () {
        it('scopes data to supervisor department only', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForAnalyticsDashboard($tenant);

            $dept1 = Department::factory()->create();
            $dept2 = Department::factory()->create();

            $supervisor = createTenantUserForAnalyticsDashboard($tenant, TenantUserRole::Supervisor);
            Employee::factory()->create([
                'user_id' => $supervisor->id,
                'department_id' => $dept1->id,
            ]);

            // Create employees in both departments
            Employee::factory()->count(5)->active()->create(['department_id' => $dept1->id]);
            Employee::factory()->count(3)->active()->create(['department_id' => $dept2->id]);

            $this->actingAs($supervisor);

            $controller = new HRAnalyticsDashboardController(
                new HRAnalyticsService(new EmployeeDashboardService)
            );
            $response = $controller->index(request());

            $reflection = new ReflectionClass($response);
            $propsProperty = $reflection->getProperty('props');
            $propsProperty->setAccessible(true);
            $props = $propsProperty->getValue($response);

            // Headcount should only include dept1 employees (5 + 1 supervisor = 6)
            expect($props['headcount']['total'])->toBe(6);
        });

        it('allows admin to filter by specific departments', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForAnalyticsDashboard($tenant);

            $dept1 = Department::factory()->create();
            $dept2 = Department::factory()->create();

            Employee::factory()->count(5)->active()->create(['department_id' => $dept1->id]);
            Employee::factory()->count(3)->active()->create(['department_id' => $dept2->id]);

            $admin = createTenantUserForAnalyticsDashboard($tenant, TenantUserRole::Admin);
            $this->actingAs($admin);

            $request = request();
            $request->merge(['department_ids' => (string) $dept1->id]);

            $controller = new HRAnalyticsDashboardController(
                new HRAnalyticsService(new EmployeeDashboardService)
            );
            $response = $controller->index($request);

            $reflection = new ReflectionClass($response);
            $propsProperty = $reflection->getProperty('props');
            $propsProperty->setAccessible(true);
            $props = $propsProperty->getValue($response);

            // Should only include dept1 employees
            expect($props['headcount']['total'])->toBe(5);
        });

        it('returns all data when no department filter applied for admin', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForAnalyticsDashboard($tenant);

            $dept1 = Department::factory()->create();
            $dept2 = Department::factory()->create();

            Employee::factory()->count(5)->active()->create(['department_id' => $dept1->id]);
            Employee::factory()->count(3)->active()->create(['department_id' => $dept2->id]);

            $admin = createTenantUserForAnalyticsDashboard($tenant, TenantUserRole::Admin);
            $this->actingAs($admin);

            $controller = new HRAnalyticsDashboardController(
                new HRAnalyticsService(new EmployeeDashboardService)
            );
            $response = $controller->index(request());

            $reflection = new ReflectionClass($response);
            $propsProperty = $reflection->getProperty('props');
            $propsProperty->setAccessible(true);
            $props = $propsProperty->getValue($response);

            // Should include all employees
            expect($props['headcount']['total'])->toBe(8);
        });
    });
});
