<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\My\MyLeaveApprovalController;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

function bindTenantForApprovals(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createUserWithRoleForApprovals(Tenant $tenant, TenantUserRole $role): User
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

it('renders My/LeaveApprovals/Index component', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForApprovals($tenant);

    $user = createUserWithRoleForApprovals($tenant, TenantUserRole::Employee);
    $this->actingAs($user);

    $controller = new MyLeaveApprovalController;
    $request = Request::create('/my/leave-approvals', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller($request);

    $reflection = new ReflectionClass($response);
    $componentProperty = $reflection->getProperty('component');
    $componentProperty->setAccessible(true);

    expect($componentProperty->getValue($response))->toBe('My/LeaveApprovals/Index');
});

it('returns summary counts', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForApprovals($tenant);

    $user = createUserWithRoleForApprovals($tenant, TenantUserRole::Employee);
    Employee::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $controller = new MyLeaveApprovalController;
    $request = Request::create('/my/leave-approvals', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller($request);

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['summary'])->toHaveKeys(['pending_count', 'approved_today', 'rejected_today'])
        ->and($props['summary']['pending_count'])->toBe(0)
        ->and($props['pendingApplications'])->toBeEmpty();
});

it('returns empty data when no employee profile', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForApprovals($tenant);

    $user = createUserWithRoleForApprovals($tenant, TenantUserRole::Employee);
    $this->actingAs($user);

    $controller = new MyLeaveApprovalController;
    $request = Request::create('/my/leave-approvals', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller($request);

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['employee'])->toBeNull()
        ->and($props['summary']['pending_count'])->toBe(0);
});
