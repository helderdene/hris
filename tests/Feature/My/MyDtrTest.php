<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\My\MyDtrController;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

function bindTenantForDtr(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createDtrTestUser(Tenant $tenant, TenantUserRole $role): User
{
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

function invokeMyDtrController(Request $request): \Inertia\Response
{
    $controller = app()->make(MyDtrController::class);

    return $controller($request);
}

function getInertiaProps(\Inertia\Response $response): array
{
    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);

    return $propsProperty->getValue($response);
}

function getInertiaComponentForDtr(\Inertia\Response $response): string
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

it('renders My/Dtr component', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForDtr($tenant);

    $user = createDtrTestUser($tenant, TenantUserRole::Employee);
    $this->actingAs($user);

    $request = Request::create('/my/dtr', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = invokeMyDtrController($request);

    expect(getInertiaComponentForDtr($response))->toBe('My/Dtr');
});

it('returns required prop keys', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForDtr($tenant);

    $user = createDtrTestUser($tenant, TenantUserRole::Employee);
    $this->actingAs($user);

    $request = Request::create('/my/dtr', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = invokeMyDtrController($request);
    $props = getInertiaProps($response);

    expect($props)->toHaveKey('records')
        ->and($props)->toHaveKey('summary')
        ->and($props)->toHaveKey('currentMonth')
        ->and($props)->toHaveKey('hasEmployeeProfile');
});

it('handles user without employee profile gracefully', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForDtr($tenant);

    $user = createDtrTestUser($tenant, TenantUserRole::Employee);
    $this->actingAs($user);

    $request = Request::create('/my/dtr', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = invokeMyDtrController($request);
    $props = getInertiaProps($response);

    expect($props['hasEmployeeProfile'])->toBeFalse()
        ->and($props['summary'])->toBeNull();
});

it('defaults to current month', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForDtr($tenant);

    $user = createDtrTestUser($tenant, TenantUserRole::Employee);
    $this->actingAs($user);

    $request = Request::create('/my/dtr', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = invokeMyDtrController($request);
    $props = getInertiaProps($response);

    expect($props['currentMonth'])->toBe(now()->format('Y-m'));
});

it('accepts month filter parameter', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForDtr($tenant);

    $user = createDtrTestUser($tenant, TenantUserRole::Employee);
    $this->actingAs($user);

    $request = Request::create('/my/dtr', 'GET', ['month' => '2025-06']);
    $request->setUserResolver(fn () => $user);

    $response = invokeMyDtrController($request);
    $props = getInertiaProps($response);

    expect($props['currentMonth'])->toBe('2025-06');
});

it('returns summary data when employee has DTR records', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForDtr($tenant);

    $user = createDtrTestUser($tenant, TenantUserRole::Employee);
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $startOfMonth = now()->startOfMonth();
    for ($i = 0; $i < 3; $i++) {
        DailyTimeRecord::factory()->create([
            'employee_id' => $employee->id,
            'date' => $startOfMonth->copy()->addDays($i),
        ]);
    }

    $request = Request::create('/my/dtr', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = invokeMyDtrController($request);
    $props = getInertiaProps($response);

    expect($props['hasEmployeeProfile'])->toBeTrue()
        ->and($props['summary'])->not->toBeNull()
        ->and($props['summary'])->toHaveKey('attendance')
        ->and($props['summary'])->toHaveKey('time_summary');
});

it('only returns records for the authenticated employee', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForDtr($tenant);

    $user = createDtrTestUser($tenant, TenantUserRole::Employee);
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $otherEmployee = Employee::factory()->create();
    $this->actingAs($user);

    $today = now()->startOfMonth();

    for ($i = 0; $i < 2; $i++) {
        DailyTimeRecord::factory()->create([
            'employee_id' => $employee->id,
            'date' => $today->copy()->addDays($i),
        ]);
    }

    for ($i = 0; $i < 3; $i++) {
        DailyTimeRecord::factory()->create([
            'employee_id' => $otherEmployee->id,
            'date' => $today->copy()->addDays($i),
        ]);
    }

    $request = Request::create('/my/dtr', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = invokeMyDtrController($request);
    $props = getInertiaProps($response);

    $records = $props['records']['data'];
    $recordCollection = collect($records->resolve());

    expect($recordCollection)->toHaveCount(2);
    $recordCollection->each(function ($record) use ($employee) {
        expect($record['employee_id'])->toBe($employee->id);
    });
});
