<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\My\MyLeaveController;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

function bindTenantForLeave(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createUserWithRoleForLeave(Tenant $tenant, TenantUserRole $role): User
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

it('renders My/Leave/Index component', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForLeave($tenant);

    $user = createUserWithRoleForLeave($tenant, TenantUserRole::Employee);
    $this->actingAs($user);

    $controller = new MyLeaveController;
    $request = Request::create('/my/leave', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller->index($request);

    $reflection = new ReflectionClass($response);
    $componentProperty = $reflection->getProperty('component');
    $componentProperty->setAccessible(true);

    expect($componentProperty->getValue($response))->toBe('My/Leave/Index');
});

it('returns employee leave applications and balances', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForLeave($tenant);

    $user = createUserWithRoleForLeave($tenant, TenantUserRole::Employee);
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $leaveType = LeaveType::factory()->create(['is_active' => true]);
    LeaveBalance::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'year' => now()->year,
    ]);
    LeaveApplication::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
    ]);
    LeaveApplication::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
    ]);

    $controller = new MyLeaveController;
    $request = Request::create('/my/leave', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller->index($request);

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['employee'])->not->toBeNull()
        ->and($props['employee']['id'])->toBe($employee->id)
        ->and($props['balances'])->toHaveCount(1)
        ->and($props['applications'])->toHaveCount(2)
        ->and($props['leaveTypes'])->not->toBeEmpty();
});

it('returns empty data when no employee profile', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForLeave($tenant);

    $user = createUserWithRoleForLeave($tenant, TenantUserRole::Employee);
    $this->actingAs($user);

    $controller = new MyLeaveController;
    $request = Request::create('/my/leave', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller->index($request);

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['employee'])->toBeNull()
        ->and($props['balances'])->toBeEmpty()
        ->and($props['applications'])->toBeEmpty();
});

it('includes leave statuses in props', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForLeave($tenant);

    $user = createUserWithRoleForLeave($tenant, TenantUserRole::Employee);
    $this->actingAs($user);

    $controller = new MyLeaveController;
    $request = Request::create('/my/leave', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller->index($request);

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['statuses'])->not->toBeEmpty()
        ->and($props['filters'])->toHaveKeys(['status', 'year']);
});
