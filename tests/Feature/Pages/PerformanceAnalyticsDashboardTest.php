<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\PerformanceAnalyticsDashboardController;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PerformanceAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForPerformanceAnalytics(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForPerformanceAnalytics(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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

describe('PerformanceAnalyticsDashboardController', function () {
    describe('authorization', function () {
        it('allows access to Admin users', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForPerformanceAnalytics($tenant);

            $admin = createTenantUserForPerformanceAnalytics($tenant, TenantUserRole::Admin);
            $this->actingAs($admin);

            $controller = new PerformanceAnalyticsDashboardController(
                new PerformanceAnalyticsService
            );
            $response = $controller(request());

            $reflection = new ReflectionClass($response);
            $componentProperty = $reflection->getProperty('component');
            $componentProperty->setAccessible(true);

            expect($componentProperty->getValue($response))->toBe('Performance/AnalyticsDashboard');
        });

        it('allows access to HR Manager users', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForPerformanceAnalytics($tenant);

            $hrManager = createTenantUserForPerformanceAnalytics($tenant, TenantUserRole::HrManager);
            $this->actingAs($hrManager);

            $controller = new PerformanceAnalyticsDashboardController(
                new PerformanceAnalyticsService
            );
            $response = $controller(request());

            $reflection = new ReflectionClass($response);
            $componentProperty = $reflection->getProperty('component');
            $componentProperty->setAccessible(true);

            expect($componentProperty->getValue($response))->toBe('Performance/AnalyticsDashboard');
        });

        it('allows access to HR Staff users', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForPerformanceAnalytics($tenant);

            $hrStaff = createTenantUserForPerformanceAnalytics($tenant, TenantUserRole::HrStaff);
            $this->actingAs($hrStaff);

            $controller = new PerformanceAnalyticsDashboardController(
                new PerformanceAnalyticsService
            );
            $response = $controller(request());

            $reflection = new ReflectionClass($response);
            $componentProperty = $reflection->getProperty('component');
            $componentProperty->setAccessible(true);

            expect($componentProperty->getValue($response))->toBe('Performance/AnalyticsDashboard');
        });

        it('allows access to Supervisor users with scoped data', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForPerformanceAnalytics($tenant);

            $department = Department::factory()->create();
            $supervisor = createTenantUserForPerformanceAnalytics($tenant, TenantUserRole::Supervisor);
            Employee::factory()->create([
                'user_id' => $supervisor->id,
                'department_id' => $department->id,
            ]);

            $this->actingAs($supervisor);

            $controller = new PerformanceAnalyticsDashboardController(
                new PerformanceAnalyticsService
            );
            $response = $controller(request());

            $reflection = new ReflectionClass($response);
            $componentProperty = $reflection->getProperty('component');
            $componentProperty->setAccessible(true);

            expect($componentProperty->getValue($response))->toBe('Performance/AnalyticsDashboard');
        });

        it('allows access to Employee users with scoped data', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForPerformanceAnalytics($tenant);

            $department = Department::factory()->create();
            $employee = createTenantUserForPerformanceAnalytics($tenant, TenantUserRole::Employee);
            Employee::factory()->create([
                'user_id' => $employee->id,
                'department_id' => $department->id,
            ]);

            $this->actingAs($employee);

            $controller = new PerformanceAnalyticsDashboardController(
                new PerformanceAnalyticsService
            );
            $response = $controller(request());

            $reflection = new ReflectionClass($response);
            $componentProperty = $reflection->getProperty('component');
            $componentProperty->setAccessible(true);

            expect($componentProperty->getValue($response))->toBe('Performance/AnalyticsDashboard');
        });
    });

    describe('data structure', function () {
        it('returns all required props for the dashboard', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForPerformanceAnalytics($tenant);

            $admin = createTenantUserForPerformanceAnalytics($tenant, TenantUserRole::Admin);
            $this->actingAs($admin);

            $controller = new PerformanceAnalyticsDashboardController(
                new PerformanceAnalyticsService
            );
            $response = $controller(request());

            $reflection = new ReflectionClass($response);
            $propsProperty = $reflection->getProperty('props');
            $propsProperty->setAccessible(true);
            $props = $propsProperty->getValue($response);

            // Verify required props are present
            expect($props)->toHaveKey('filters')
                ->and($props)->toHaveKey('departments')
                ->and($props)->toHaveKey('summary');

            // Verify summary structure
            expect($props['summary'])->toHaveKey('totalEvaluations')
                ->and($props['summary'])->toHaveKey('completedEvaluations')
                ->and($props['summary'])->toHaveKey('averageRating')
                ->and($props['summary'])->toHaveKey('activeDevelopmentPlans')
                ->and($props['summary'])->toHaveKey('activeGoals')
                ->and($props['summary'])->toHaveKey('goalsAchieved');
        });

        it('returns deferred props for heavy metrics', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForPerformanceAnalytics($tenant);

            $admin = createTenantUserForPerformanceAnalytics($tenant, TenantUserRole::Admin);
            $this->actingAs($admin);

            $controller = new PerformanceAnalyticsDashboardController(
                new PerformanceAnalyticsService
            );
            $response = $controller(request());

            $reflection = new ReflectionClass($response);
            $propsProperty = $reflection->getProperty('props');
            $propsProperty->setAccessible(true);
            $props = $propsProperty->getValue($response);

            // Deferred props should be DeferProp instances
            expect($props['evaluationCompletion'])->toBeInstanceOf(\Inertia\DeferProp::class);
            expect($props['ratingDistribution'])->toBeInstanceOf(\Inertia\DeferProp::class);
            expect($props['ratingTrends'])->toBeInstanceOf(\Inertia\DeferProp::class);
            expect($props['developmentPlans'])->toBeInstanceOf(\Inertia\DeferProp::class);
            expect($props['goalAchievement'])->toBeInstanceOf(\Inertia\DeferProp::class);
            expect($props['kpiAchievement'])->toBeInstanceOf(\Inertia\DeferProp::class);
            expect($props['byDepartment'])->toBeInstanceOf(\Inertia\DeferProp::class);
        });
    });

    describe('department filtering', function () {
        it('scopes data to supervisor department only', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForPerformanceAnalytics($tenant);

            $dept1 = Department::factory()->create();
            $dept2 = Department::factory()->create();

            $supervisor = createTenantUserForPerformanceAnalytics($tenant, TenantUserRole::Supervisor);
            Employee::factory()->create([
                'user_id' => $supervisor->id,
                'department_id' => $dept1->id,
            ]);

            $this->actingAs($supervisor);

            $controller = new PerformanceAnalyticsDashboardController(
                new PerformanceAnalyticsService
            );
            $response = $controller(request());

            $reflection = new ReflectionClass($response);
            $propsProperty = $reflection->getProperty('props');
            $propsProperty->setAccessible(true);
            $props = $propsProperty->getValue($response);

            // Verify filters contain the supervisor's department
            expect($props['filters']['departmentIds'])->toBe([$dept1->id]);
        });

        it('scopes data to employee department only', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForPerformanceAnalytics($tenant);

            $dept1 = Department::factory()->create();
            $dept2 = Department::factory()->create();

            $employee = createTenantUserForPerformanceAnalytics($tenant, TenantUserRole::Employee);
            Employee::factory()->create([
                'user_id' => $employee->id,
                'department_id' => $dept1->id,
            ]);

            $this->actingAs($employee);

            $controller = new PerformanceAnalyticsDashboardController(
                new PerformanceAnalyticsService
            );
            $response = $controller(request());

            $reflection = new ReflectionClass($response);
            $propsProperty = $reflection->getProperty('props');
            $propsProperty->setAccessible(true);
            $props = $propsProperty->getValue($response);

            // Verify filters contain the employee's department
            expect($props['filters']['departmentIds'])->toBe([$dept1->id]);
        });

        it('allows admin to filter by specific departments', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForPerformanceAnalytics($tenant);

            $dept1 = Department::factory()->create();
            $dept2 = Department::factory()->create();

            $admin = createTenantUserForPerformanceAnalytics($tenant, TenantUserRole::Admin);
            $this->actingAs($admin);

            $request = request();
            $request->merge(['department_ids' => (string) $dept1->id]);

            $controller = new PerformanceAnalyticsDashboardController(
                new PerformanceAnalyticsService
            );
            $response = $controller($request);

            $reflection = new ReflectionClass($response);
            $propsProperty = $reflection->getProperty('props');
            $propsProperty->setAccessible(true);
            $props = $propsProperty->getValue($response);

            // Should include selected department filter
            expect($props['filters']['departmentIds'])->toBe([$dept1->id]);
        });

        it('returns null department filter for admin without selection', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForPerformanceAnalytics($tenant);

            $admin = createTenantUserForPerformanceAnalytics($tenant, TenantUserRole::Admin);
            $this->actingAs($admin);

            $controller = new PerformanceAnalyticsDashboardController(
                new PerformanceAnalyticsService
            );
            $response = $controller(request());

            $reflection = new ReflectionClass($response);
            $propsProperty = $reflection->getProperty('props');
            $propsProperty->setAccessible(true);
            $props = $propsProperty->getValue($response);

            // Admin without filter should have null departmentIds
            expect($props['filters']['departmentIds'])->toBeNull();
        });
    });

    describe('date filtering', function () {
        it('accepts date range filters', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForPerformanceAnalytics($tenant);

            $admin = createTenantUserForPerformanceAnalytics($tenant, TenantUserRole::Admin);
            $this->actingAs($admin);

            $request = request();
            $request->merge([
                'start_date' => '2025-01-01',
                'end_date' => '2025-12-31',
            ]);

            $controller = new PerformanceAnalyticsDashboardController(
                new PerformanceAnalyticsService
            );
            $response = $controller($request);

            $reflection = new ReflectionClass($response);
            $propsProperty = $reflection->getProperty('props');
            $propsProperty->setAccessible(true);
            $props = $propsProperty->getValue($response);

            expect($props['filters']['startDate'])->toBe('2025-01-01');
            expect($props['filters']['endDate'])->toBe('2025-12-31');
        });
    });
});
