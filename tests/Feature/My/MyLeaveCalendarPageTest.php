<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\My\MyLeaveCalendarController;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

function bindTenantForCalendar(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createUserWithRoleForCalendar(Tenant $tenant, TenantUserRole $role): User
{
    $user = User::factory()->create();
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

it('renders My/Leave/Calendar component', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForCalendar($tenant);

    $user = createUserWithRoleForCalendar($tenant, TenantUserRole::Employee);
    $this->actingAs($user);

    $controller = new MyLeaveCalendarController;
    $request = Request::create('/my/leave/calendar', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller($request);

    $reflection = new ReflectionClass($response);
    $componentProperty = $reflection->getProperty('component');
    $componentProperty->setAccessible(true);

    expect($componentProperty->getValue($response))->toBe('My/Leave/Calendar');
});

it('returns employee with department info', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForCalendar($tenant);

    $user = createUserWithRoleForCalendar($tenant, TenantUserRole::Employee);
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $controller = new MyLeaveCalendarController;
    $request = Request::create('/my/leave/calendar', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller($request);

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['employee'])->not->toBeNull()
        ->and($props['employee']['id'])->toBe($employee->id)
        ->and($props['employee'])->toHaveKey('department_id')
        ->and($props['filters'])->toHaveKeys(['year', 'month']);
});

it('returns null employee when no profile', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForCalendar($tenant);

    $user = createUserWithRoleForCalendar($tenant, TenantUserRole::Employee);
    $this->actingAs($user);

    $controller = new MyLeaveCalendarController;
    $request = Request::create('/my/leave/calendar', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller($request);

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['employee'])->toBeNull();
});

it('includes leave types for legend', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForCalendar($tenant);

    $user = createUserWithRoleForCalendar($tenant, TenantUserRole::Employee);
    $this->actingAs($user);

    $controller = new MyLeaveCalendarController;
    $request = Request::create('/my/leave/calendar', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller($request);

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['leaveTypes'])->toBeInstanceOf(\Illuminate\Support\Collection::class);
});
