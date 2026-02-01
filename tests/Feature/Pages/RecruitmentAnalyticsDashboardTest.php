<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\RecruitmentAnalyticsDashboardController;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RecruitmentAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForRecruitmentAnalytics(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForRecruitmentAnalytics(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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

describe('RecruitmentAnalyticsDashboardController', function () {
    describe('authorization', function () {
        it('allows access to Admin users', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForRecruitmentAnalytics($tenant);

            $admin = createTenantUserForRecruitmentAnalytics($tenant, TenantUserRole::Admin);
            $this->actingAs($admin);

            $controller = new RecruitmentAnalyticsDashboardController(
                new RecruitmentAnalyticsService
            );
            $response = $controller->index(request());

            $reflection = new ReflectionClass($response);
            $componentProperty = $reflection->getProperty('component');
            $componentProperty->setAccessible(true);

            expect($componentProperty->getValue($response))->toBe('Recruitment/AnalyticsDashboard');
        });

        it('allows access to HR Manager users', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForRecruitmentAnalytics($tenant);

            $hrManager = createTenantUserForRecruitmentAnalytics($tenant, TenantUserRole::HrManager);
            $this->actingAs($hrManager);

            $controller = new RecruitmentAnalyticsDashboardController(
                new RecruitmentAnalyticsService
            );
            $response = $controller->index(request());

            $reflection = new ReflectionClass($response);
            $componentProperty = $reflection->getProperty('component');
            $componentProperty->setAccessible(true);

            expect($componentProperty->getValue($response))->toBe('Recruitment/AnalyticsDashboard');
        });

        it('allows access to Supervisor users with scoped data', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForRecruitmentAnalytics($tenant);

            $department = Department::factory()->create();
            $supervisor = createTenantUserForRecruitmentAnalytics($tenant, TenantUserRole::Supervisor);
            Employee::factory()->create([
                'user_id' => $supervisor->id,
                'department_id' => $department->id,
            ]);

            $this->actingAs($supervisor);

            $controller = new RecruitmentAnalyticsDashboardController(
                new RecruitmentAnalyticsService
            );
            $response = $controller->index(request());

            $reflection = new ReflectionClass($response);
            $componentProperty = $reflection->getProperty('component');
            $componentProperty->setAccessible(true);

            expect($componentProperty->getValue($response))->toBe('Recruitment/AnalyticsDashboard');
        });

        it('denies access to Employee role users', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForRecruitmentAnalytics($tenant);

            $employee = createTenantUserForRecruitmentAnalytics($tenant, TenantUserRole::Employee);
            $this->actingAs($employee);

            $controller = new RecruitmentAnalyticsDashboardController(
                new RecruitmentAnalyticsService
            );

            expect(fn () => $controller->index(request()))
                ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
        });

        it('denies access to HR Staff users', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForRecruitmentAnalytics($tenant);

            $hrStaff = createTenantUserForRecruitmentAnalytics($tenant, TenantUserRole::HrStaff);
            $this->actingAs($hrStaff);

            $controller = new RecruitmentAnalyticsDashboardController(
                new RecruitmentAnalyticsService
            );

            expect(fn () => $controller->index(request()))
                ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
        });
    });

    describe('data structure', function () {
        it('returns all required props for the dashboard', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForRecruitmentAnalytics($tenant);

            $admin = createTenantUserForRecruitmentAnalytics($tenant, TenantUserRole::Admin);
            $this->actingAs($admin);

            $controller = new RecruitmentAnalyticsDashboardController(
                new RecruitmentAnalyticsService
            );
            $response = $controller->index(request());

            $reflection = new ReflectionClass($response);
            $propsProperty = $reflection->getProperty('props');
            $propsProperty->setAccessible(true);
            $props = $propsProperty->getValue($response);

            // Verify required props are present
            expect($props)->toHaveKey('filters')
                ->and($props)->toHaveKey('departments')
                ->and($props)->toHaveKey('summary');

            // Verify summary structure
            expect($props['summary'])->toHaveKey('activeRequisitions')
                ->and($props['summary'])->toHaveKey('openPositions')
                ->and($props['summary'])->toHaveKey('totalApplications')
                ->and($props['summary'])->toHaveKey('avgTimeToFill')
                ->and($props['summary'])->toHaveKey('offerAcceptanceRate');
        });

        it('returns deferred props for heavy metrics', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForRecruitmentAnalytics($tenant);

            $admin = createTenantUserForRecruitmentAnalytics($tenant, TenantUserRole::Admin);
            $this->actingAs($admin);

            $controller = new RecruitmentAnalyticsDashboardController(
                new RecruitmentAnalyticsService
            );
            $response = $controller->index(request());

            $reflection = new ReflectionClass($response);
            $propsProperty = $reflection->getProperty('props');
            $propsProperty->setAccessible(true);
            $props = $propsProperty->getValue($response);

            // Deferred props should be DeferProp instances
            expect($props['funnelMetrics'])->toBeInstanceOf(\Inertia\DeferProp::class);
            expect($props['timeToFillMetrics'])->toBeInstanceOf(\Inertia\DeferProp::class);
            expect($props['sourceEffectiveness'])->toBeInstanceOf(\Inertia\DeferProp::class);
            expect($props['offerMetrics'])->toBeInstanceOf(\Inertia\DeferProp::class);
            expect($props['requisitionMetrics'])->toBeInstanceOf(\Inertia\DeferProp::class);
            expect($props['interviewMetrics'])->toBeInstanceOf(\Inertia\DeferProp::class);
            expect($props['hiringVelocityTrend'])->toBeInstanceOf(\Inertia\DeferProp::class);
        });
    });

    describe('department filtering', function () {
        it('scopes data to supervisor department only', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForRecruitmentAnalytics($tenant);

            $dept1 = Department::factory()->create();
            $dept2 = Department::factory()->create();

            $supervisor = createTenantUserForRecruitmentAnalytics($tenant, TenantUserRole::Supervisor);
            Employee::factory()->create([
                'user_id' => $supervisor->id,
                'department_id' => $dept1->id,
            ]);

            $this->actingAs($supervisor);

            $controller = new RecruitmentAnalyticsDashboardController(
                new RecruitmentAnalyticsService
            );
            $response = $controller->index(request());

            $reflection = new ReflectionClass($response);
            $propsProperty = $reflection->getProperty('props');
            $propsProperty->setAccessible(true);
            $props = $propsProperty->getValue($response);

            // Verify filters contain the supervisor's department
            expect($props['filters']['departmentIds'])->toBe([$dept1->id]);
        });

        it('allows admin to filter by specific departments', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForRecruitmentAnalytics($tenant);

            $dept1 = Department::factory()->create();
            $dept2 = Department::factory()->create();

            $admin = createTenantUserForRecruitmentAnalytics($tenant, TenantUserRole::Admin);
            $this->actingAs($admin);

            $request = request();
            $request->merge(['department_ids' => (string) $dept1->id]);

            $controller = new RecruitmentAnalyticsDashboardController(
                new RecruitmentAnalyticsService
            );
            $response = $controller->index($request);

            $reflection = new ReflectionClass($response);
            $propsProperty = $reflection->getProperty('props');
            $propsProperty->setAccessible(true);
            $props = $propsProperty->getValue($response);

            // Should show filtered department
            expect($props['filters']['departmentIds'])->toBe([$dept1->id]);
        });

        it('returns null departmentIds when no filter applied for admin', function () {
            $tenant = Tenant::factory()->create(['slug' => 'acme']);
            bindTenantContextForRecruitmentAnalytics($tenant);

            $admin = createTenantUserForRecruitmentAnalytics($tenant, TenantUserRole::Admin);
            $this->actingAs($admin);

            $controller = new RecruitmentAnalyticsDashboardController(
                new RecruitmentAnalyticsService
            );
            $response = $controller->index(request());

            $reflection = new ReflectionClass($response);
            $propsProperty = $reflection->getProperty('props');
            $propsProperty->setAccessible(true);
            $props = $propsProperty->getValue($response);

            // Should be null when no filter
            expect($props['filters']['departmentIds'])->toBeNull();
        });
    });
});
