<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\EmployeeController;
use App\Models\Employee;
use App\Models\EmployeeScheduleAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function bindTenantForScheduleHistory(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createScheduleHistoryTestUser(Tenant $tenant, TenantUserRole $role): User
{
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

function getScheduleHistoryInertiaProps(\Inertia\Response $response): array
{
    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);

    return $propsProperty->getValue($response);
}

/**
 * Resolve a deferred Inertia prop by extracting and invoking the callback.
 */
function resolveDeferredProp(mixed $prop): mixed
{
    $reflection = new ReflectionClass($prop);
    $callbackProp = $reflection->getProperty('callback');
    $callbackProp->setAccessible(true);

    return ($callbackProp->getValue($prop))();
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    \Illuminate\Support\Facades\Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

it('includes scheduleHistory as a deferred prop', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForScheduleHistory($tenant);

    $user = createScheduleHistoryTestUser($tenant, TenantUserRole::Admin);
    $this->actingAs($user);

    $employee = Employee::factory()->create();

    $controller = app()->make(EmployeeController::class);
    $response = $controller->show($employee);

    $props = getScheduleHistoryInertiaProps($response);

    expect($props)->toHaveKey('scheduleHistory');
});

it('returns schedule assignments with correct shape', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForScheduleHistory($tenant);

    $user = createScheduleHistoryTestUser($tenant, TenantUserRole::Admin);
    $this->actingAs($user);

    $employee = Employee::factory()->create();
    $schedule = WorkSchedule::factory()->active()->create();

    EmployeeScheduleAssignment::factory()->active()->create([
        'employee_id' => $employee->id,
        'work_schedule_id' => $schedule->id,
        'shift_name' => 'Morning',
    ]);

    $controller = app()->make(EmployeeController::class);
    $response = $controller->show($employee);

    $props = getScheduleHistoryInertiaProps($response);
    $resolved = collect(resolveDeferredProp($props['scheduleHistory']))->toArray();

    expect($resolved)->toHaveCount(1);
    expect($resolved[0])->toHaveKeys([
        'id',
        'schedule_name',
        'schedule_type',
        'shift_name',
        'effective_date',
        'end_date',
        'is_current',
        'is_upcoming',
    ]);
    expect($resolved[0]['schedule_name'])->toBe($schedule->name);
    expect($resolved[0]['shift_name'])->toBe('Morning');
    expect($resolved[0]['is_current'])->toBeTrue();
});

it('marks past assignments correctly', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForScheduleHistory($tenant);

    $user = createScheduleHistoryTestUser($tenant, TenantUserRole::Admin);
    $this->actingAs($user);

    $employee = Employee::factory()->create();
    $schedule = WorkSchedule::factory()->active()->create();

    EmployeeScheduleAssignment::factory()->ended()->create([
        'employee_id' => $employee->id,
        'work_schedule_id' => $schedule->id,
    ]);

    $controller = app()->make(EmployeeController::class);
    $response = $controller->show($employee);

    $props = getScheduleHistoryInertiaProps($response);
    $resolved = collect(resolveDeferredProp($props['scheduleHistory']))->toArray();

    expect($resolved)->toHaveCount(1);
    expect($resolved[0]['is_current'])->toBeFalse();
    expect($resolved[0]['is_upcoming'])->toBeFalse();
});

it('marks future assignments as upcoming', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForScheduleHistory($tenant);

    $user = createScheduleHistoryTestUser($tenant, TenantUserRole::Admin);
    $this->actingAs($user);

    $employee = Employee::factory()->create();
    $schedule = WorkSchedule::factory()->active()->create();

    EmployeeScheduleAssignment::factory()->future()->create([
        'employee_id' => $employee->id,
        'work_schedule_id' => $schedule->id,
    ]);

    $controller = app()->make(EmployeeController::class);
    $response = $controller->show($employee);

    $props = getScheduleHistoryInertiaProps($response);
    $resolved = collect(resolveDeferredProp($props['scheduleHistory']))->toArray();

    expect($resolved)->toHaveCount(1);
    expect($resolved[0]['is_current'])->toBeFalse();
    expect($resolved[0]['is_upcoming'])->toBeTrue();
});
