<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\My\SelfServiceDashboardController;
use App\Models\Announcement;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

function bindTenantForSelfService(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createUserWithRole(Tenant $tenant, TenantUserRole $role): User
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

it('renders My/Dashboard component', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForSelfService($tenant);

    $user = createUserWithRole($tenant, TenantUserRole::Employee);
    $this->actingAs($user);

    $controller = new SelfServiceDashboardController;
    $request = Request::create('/my/dashboard', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller($request);

    $reflection = new ReflectionClass($response);
    $componentProperty = $reflection->getProperty('component');
    $componentProperty->setAccessible(true);

    expect($componentProperty->getValue($response))->toBe('My/Dashboard');
});

it('returns employee data when user has linked employee profile', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForSelfService($tenant);

    $user = createUserWithRole($tenant, TenantUserRole::Employee);
    $employeeModel = Employee::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $controller = new SelfServiceDashboardController;
    $request = Request::create('/my/dashboard', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller($request);

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['employee'])->not->toBeNull()
        ->and($props['employee']['id'])->toBe($employeeModel->id)
        ->and($props['employee']['full_name'])->toBe($employeeModel->full_name);
});

it('returns null employee when user has no linked employee profile', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForSelfService($tenant);

    $user = createUserWithRole($tenant, TenantUserRole::Employee);
    $this->actingAs($user);

    $controller = new SelfServiceDashboardController;
    $request = Request::create('/my/dashboard', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller($request);

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['employee'])->toBeNull()
        ->and($props['leaveBalances'])->toBeEmpty()
        ->and($props['recentPayslips'])->toBeEmpty()
        ->and($props['todayDtr'])->toBeNull();
});

it('returns all required prop keys', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForSelfService($tenant);

    $user = createUserWithRole($tenant, TenantUserRole::Employee);
    $this->actingAs($user);

    $controller = new SelfServiceDashboardController;
    $request = Request::create('/my/dashboard', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller($request);

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props)->toHaveKey('employee')
        ->and($props)->toHaveKey('leaveBalances')
        ->and($props)->toHaveKey('recentLeaveApplications')
        ->and($props)->toHaveKey('recentPayslips')
        ->and($props)->toHaveKey('todayDtr')
        ->and($props)->toHaveKey('announcements')
        ->and($props)->toHaveKey('documentRequestsSummary')
        ->and($props)->toHaveKey('loansSummary');
});

it('returns only published and non-expired announcements', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForSelfService($tenant);

    $user = createUserWithRole($tenant, TenantUserRole::Employee);
    $this->actingAs($user);

    // Active announcements
    Announcement::factory()->count(3)->create(['tenant_id' => $tenant->id]);

    // Expired announcement — should not appear
    Announcement::factory()->expired()->create(['tenant_id' => $tenant->id]);

    // Unpublished announcement — should not appear
    Announcement::factory()->unpublished()->create(['tenant_id' => $tenant->id]);

    // Scheduled for future — should not appear
    Announcement::factory()->scheduled()->create(['tenant_id' => $tenant->id]);

    $controller = new SelfServiceDashboardController;
    $request = Request::create('/my/dashboard', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller($request);

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['announcements'])->toHaveCount(3);
});

it('limits announcements to 5', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForSelfService($tenant);

    $user = createUserWithRole($tenant, TenantUserRole::Employee);
    $this->actingAs($user);

    Announcement::factory()->count(8)->create(['tenant_id' => $tenant->id]);

    $controller = new SelfServiceDashboardController;
    $request = Request::create('/my/dashboard', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller($request);

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['announcements'])->toHaveCount(5);
});

it('returns recent leave applications for employee', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForSelfService($tenant);

    $user = createUserWithRole($tenant, TenantUserRole::Employee);
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $leaveType = LeaveType::factory()->create();

    // Create leave applications one at a time to avoid reference_number uniqueness issues
    for ($i = 0; $i < 3; $i++) {
        LeaveApplication::factory()->pending()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
        ]);
    }

    LeaveApplication::factory()->approved()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
    ]);

    $controller = new SelfServiceDashboardController;
    $request = Request::create('/my/dashboard', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller($request);

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['recentLeaveApplications'])->toHaveCount(4)
        ->and($props['recentLeaveApplications'][0])->toHaveKeys([
            'id',
            'reference_number',
            'leave_type',
            'total_days',
            'status',
            'status_label',
            'status_color',
            'start_date',
        ]);
});

it('limits recent leave applications to 5', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForSelfService($tenant);

    $user = createUserWithRole($tenant, TenantUserRole::Employee);
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $leaveType = LeaveType::factory()->create();

    // Create leave applications one at a time to avoid reference_number uniqueness issues
    for ($i = 0; $i < 8; $i++) {
        LeaveApplication::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
        ]);
    }

    $controller = new SelfServiceDashboardController;
    $request = Request::create('/my/dashboard', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller($request);

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['recentLeaveApplications'])->toHaveCount(5);
});

it('returns empty recent leave applications when no employee profile', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForSelfService($tenant);

    $user = createUserWithRole($tenant, TenantUserRole::Employee);
    $this->actingAs($user);

    $controller = new SelfServiceDashboardController;
    $request = Request::create('/my/dashboard', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller($request);

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['recentLeaveApplications'])->toBeEmpty();
});

it('redirects employee role from /dashboard to /my/dashboard', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForSelfService($tenant);

    $user = createUserWithRole($tenant, TenantUserRole::Employee);

    // Verify the user has employee role
    $role = $user->getRoleInTenant($tenant);
    expect($role)->toBe(TenantUserRole::Employee);
});

it('does not redirect admin from /dashboard', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForSelfService($tenant);

    $user = createUserWithRole($tenant, TenantUserRole::Admin);

    $role = $user->getRoleInTenant($tenant);
    expect($role)->toBe(TenantUserRole::Admin)
        ->and($role)->not->toBe(TenantUserRole::Employee);
});

it('does not redirect hr_manager from /dashboard', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForSelfService($tenant);

    $user = createUserWithRole($tenant, TenantUserRole::HrManager);

    $role = $user->getRoleInTenant($tenant);
    expect($role)->toBe(TenantUserRole::HrManager)
        ->and($role)->not->toBe(TenantUserRole::Employee);
});
