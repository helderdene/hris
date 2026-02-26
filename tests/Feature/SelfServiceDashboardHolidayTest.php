<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\My\SelfServiceDashboardController;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function bindTenantForDashboardHoliday(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createEmployeeForDashboardHoliday(Tenant $tenant, array $employeeAttributes = []): array
{
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, [
        'role' => TenantUserRole::Employee->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    $employee = Employee::factory()->create(array_merge(
        ['user_id' => $user->id],
        $employeeAttributes,
    ));

    return [$user, $employee];
}

function getInertiaPropsForDashboardHoliday(\Inertia\Response $response): array
{
    $reflection = new ReflectionClass($response);
    $property = $reflection->getProperty('props');
    $property->setAccessible(true);

    return $property->getValue($response);
}

function callDashboardController(User $user): \Inertia\Response
{
    $controller = app(SelfServiceDashboardController::class);
    $request = Request::create('/my/dashboard', 'GET');
    $request->setUserResolver(fn () => $user);
    app()->instance('request', $request);

    return $controller($request);
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    Carbon::setTestNow(Carbon::parse('2026-02-23'));
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('Upcoming holidays on self-service dashboard', function () {
    it('returns upcoming national holidays within 3 days', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantForDashboardHoliday($tenant);

        [$user, $employee] = createEmployeeForDashboardHoliday($tenant);

        Holiday::factory()
            ->national()
            ->regular()
            ->forDate('2026-02-24')
            ->create(['name' => 'EDSA Anniversary']);

        $this->actingAs($user);
        $response = callDashboardController($user);

        $props = getInertiaPropsForDashboardHoliday($response);
        expect($props['upcomingHolidays'])->toHaveCount(1);
        expect($props['upcomingHolidays'][0]['name'])->toBe('EDSA Anniversary');
        expect($props['upcomingHolidays'][0]['holiday_type'])->toBe('regular');
        expect($props['upcomingHolidays'][0]['holiday_type_label'])->toBe('Regular Holiday');
        expect($props['upcomingHolidays'][0]['is_working'])->toBeFalse();
    });

    it('returns special working holidays with is_working as true', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantForDashboardHoliday($tenant);

        [$user, $employee] = createEmployeeForDashboardHoliday($tenant);

        Holiday::factory()
            ->national()
            ->specialWorking()
            ->forDate('2026-02-25')
            ->create(['name' => 'Special Working Holiday']);

        $this->actingAs($user);
        $response = callDashboardController($user);

        $props = getInertiaPropsForDashboardHoliday($response);
        expect($props['upcomingHolidays'])->toHaveCount(1);
        expect($props['upcomingHolidays'][0]['is_working'])->toBeTrue();
    });

    it('includes location-specific holidays for employees at that location', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantForDashboardHoliday($tenant);

        $location = WorkLocation::factory()->create();

        [$user, $employee] = createEmployeeForDashboardHoliday($tenant, [
            'work_location_id' => $location->id,
        ]);

        Holiday::factory()
            ->local($location)
            ->regular()
            ->forDate('2026-02-24')
            ->create(['name' => 'Local Festival']);

        Holiday::factory()
            ->national()
            ->regular()
            ->forDate('2026-02-25')
            ->create(['name' => 'National Day']);

        $this->actingAs($user);
        $response = callDashboardController($user);

        $props = getInertiaPropsForDashboardHoliday($response);
        expect($props['upcomingHolidays'])->toHaveCount(2);

        $names = array_column($props['upcomingHolidays'], 'name');
        expect($names)->toContain('Local Festival');
        expect($names)->toContain('National Day');
    });

    it('excludes location-specific holidays for employees at a different location', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantForDashboardHoliday($tenant);

        $locationA = WorkLocation::factory()->create();
        $locationB = WorkLocation::factory()->create();

        [$user, $employee] = createEmployeeForDashboardHoliday($tenant, [
            'work_location_id' => $locationA->id,
        ]);

        Holiday::factory()
            ->local($locationB)
            ->regular()
            ->forDate('2026-02-24')
            ->create(['name' => 'Other Location Festival']);

        $this->actingAs($user);
        $response = callDashboardController($user);

        $props = getInertiaPropsForDashboardHoliday($response);
        expect($props['upcomingHolidays'])->toHaveCount(0);
    });

    it('excludes holidays beyond 3 days from now', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantForDashboardHoliday($tenant);

        [$user, $employee] = createEmployeeForDashboardHoliday($tenant);

        Holiday::factory()
            ->national()
            ->regular()
            ->forDate('2026-02-27')
            ->create(['name' => 'Far Away Holiday']);

        $this->actingAs($user);
        $response = callDashboardController($user);

        $props = getInertiaPropsForDashboardHoliday($response);
        expect($props['upcomingHolidays'])->toHaveCount(0);
    });

    it('excludes past holidays and today', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantForDashboardHoliday($tenant);

        [$user, $employee] = createEmployeeForDashboardHoliday($tenant);

        Holiday::factory()
            ->national()
            ->regular()
            ->forDate('2026-02-22')
            ->create(['name' => 'Yesterday Holiday']);

        Holiday::factory()
            ->national()
            ->regular()
            ->forDate('2026-02-23')
            ->create(['name' => 'Today Holiday']);

        $this->actingAs($user);
        $response = callDashboardController($user);

        $props = getInertiaPropsForDashboardHoliday($response);
        expect($props['upcomingHolidays'])->toHaveCount(0);
    });

    it('shows only national holidays for employees without a work location', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantForDashboardHoliday($tenant);

        [$user, $employee] = createEmployeeForDashboardHoliday($tenant, [
            'work_location_id' => null,
        ]);

        $location = WorkLocation::factory()->create();

        Holiday::factory()
            ->national()
            ->regular()
            ->forDate('2026-02-24')
            ->create(['name' => 'National Day']);

        Holiday::factory()
            ->local($location)
            ->regular()
            ->forDate('2026-02-25')
            ->create(['name' => 'Local Festival']);

        $this->actingAs($user);
        $response = callDashboardController($user);

        $props = getInertiaPropsForDashboardHoliday($response);
        expect($props['upcomingHolidays'])->toHaveCount(1);
        expect($props['upcomingHolidays'][0]['name'])->toBe('National Day');
    });
});
