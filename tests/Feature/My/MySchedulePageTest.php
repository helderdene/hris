<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\My\MyScheduleController;
use App\Models\Employee;
use App\Models\EmployeeScheduleAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

function bindTenantForMySchedule(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createMyScheduleTestUser(Tenant $tenant, TenantUserRole $role): User
{
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

function invokeMyScheduleController(Request $request): \Inertia\Response
{
    $controller = app()->make(MyScheduleController::class);

    return $controller($request);
}

function getMyScheduleInertiaProps(\Inertia\Response $response): array
{
    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);

    return $propsProperty->getValue($response);
}

function getMyScheduleInertiaComponent(\Inertia\Response $response): string
{
    $reflection = new ReflectionClass($response);
    $componentProperty = $reflection->getProperty('component');
    $componentProperty->setAccessible(true);

    return $componentProperty->getValue($response);
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    \Illuminate\Support\Facades\Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

it('renders My/Schedule component', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForMySchedule($tenant);

    $user = createMyScheduleTestUser($tenant, TenantUserRole::Employee);
    $this->actingAs($user);

    $request = Request::create('/my/schedule', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = invokeMyScheduleController($request);

    expect(getMyScheduleInertiaComponent($response))->toBe('My/Schedule');
});

it('returns required prop keys', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForMySchedule($tenant);

    $user = createMyScheduleTestUser($tenant, TenantUserRole::Employee);
    $this->actingAs($user);

    $request = Request::create('/my/schedule', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = invokeMyScheduleController($request);
    $props = getMyScheduleInertiaProps($response);

    expect($props)->toHaveKey('scheduleHistory')
        ->and($props)->toHaveKey('currentSchedule')
        ->and($props)->toHaveKey('hasEmployeeProfile');
});

it('handles user without employee profile gracefully', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForMySchedule($tenant);

    $user = createMyScheduleTestUser($tenant, TenantUserRole::Employee);
    $this->actingAs($user);

    $request = Request::create('/my/schedule', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = invokeMyScheduleController($request);
    $props = getMyScheduleInertiaProps($response);

    expect($props['hasEmployeeProfile'])->toBeFalse()
        ->and($props['scheduleHistory'])->toBeEmpty()
        ->and($props['currentSchedule'])->toBeNull();
});

it('returns current and historical schedule data', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForMySchedule($tenant);

    $user = createMyScheduleTestUser($tenant, TenantUserRole::Employee);
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $schedule = WorkSchedule::factory()->active()->create();

    // Past assignment
    EmployeeScheduleAssignment::factory()->ended()->create([
        'employee_id' => $employee->id,
        'work_schedule_id' => $schedule->id,
        'shift_name' => 'Night',
    ]);

    // Current assignment
    EmployeeScheduleAssignment::factory()->active()->create([
        'employee_id' => $employee->id,
        'work_schedule_id' => $schedule->id,
        'shift_name' => 'Morning',
    ]);

    $request = Request::create('/my/schedule', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = invokeMyScheduleController($request);
    $props = getMyScheduleInertiaProps($response);

    expect($props['hasEmployeeProfile'])->toBeTrue()
        ->and($props['scheduleHistory'])->toHaveCount(2)
        ->and($props['currentSchedule'])->not->toBeNull()
        ->and($props['currentSchedule']['shift_name'])->toBe('Morning')
        ->and($props['currentSchedule']['is_current'])->toBeTrue();
});

it('includes time_configuration in schedule data', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForMySchedule($tenant);

    $user = createMyScheduleTestUser($tenant, TenantUserRole::Employee);
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $schedule = WorkSchedule::factory()->active()->create();

    EmployeeScheduleAssignment::factory()->active()->create([
        'employee_id' => $employee->id,
        'work_schedule_id' => $schedule->id,
    ]);

    $request = Request::create('/my/schedule', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = invokeMyScheduleController($request);
    $props = getMyScheduleInertiaProps($response);

    expect($props['currentSchedule'])->toHaveKey('time_configuration')
        ->and($props['currentSchedule']['time_configuration'])->not->toBeNull();
});
