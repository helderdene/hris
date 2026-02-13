<?php

use App\Enums\LeaveApprovalDecision;
use App\Enums\TenantUserRole;
use App\Http\Controllers\ActionCenterDashboardController;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveApplicationApproval;
use App\Models\LeaveType;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForActionCenter(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForActionCenter(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
{
    $user = User::factory()->create($userAttributes);
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

/**
 * Helper to create an employee linked to a user.
 */
function createEmployeeForUser(User $user, array $attributes = []): Employee
{
    return Employee::factory()->create(array_merge([
        'user_id' => $user->id,
    ], $attributes));
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    // Run tenant-specific migrations for testing
    \Illuminate\Support\Facades\Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

/*
|--------------------------------------------------------------------------
| Authorization Tests
|--------------------------------------------------------------------------
*/

it('allows access to Admin users', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForActionCenter($tenant);

    $admin = createTenantUserForActionCenter($tenant, TenantUserRole::Admin);
    createEmployeeForUser($admin);
    $this->actingAs($admin);

    $controller = app(ActionCenterDashboardController::class);
    $response = $controller(request());

    $reflection = new ReflectionClass($response);
    $componentProperty = $reflection->getProperty('component');
    $componentProperty->setAccessible(true);

    expect($componentProperty->getValue($response))->toBe('TenantDashboard');
});

it('allows access to HR Manager users', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForActionCenter($tenant);

    $hrManager = createTenantUserForActionCenter($tenant, TenantUserRole::HrManager);
    createEmployeeForUser($hrManager);
    $this->actingAs($hrManager);

    $controller = app(ActionCenterDashboardController::class);
    $response = $controller(request());

    $reflection = new ReflectionClass($response);
    $componentProperty = $reflection->getProperty('component');
    $componentProperty->setAccessible(true);

    expect($componentProperty->getValue($response))->toBe('TenantDashboard');
});

it('allows access to HR Staff users', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForActionCenter($tenant);

    $hrStaff = createTenantUserForActionCenter($tenant, TenantUserRole::HrStaff);
    createEmployeeForUser($hrStaff);
    $this->actingAs($hrStaff);

    $controller = app(ActionCenterDashboardController::class);
    $response = $controller(request());

    $reflection = new ReflectionClass($response);
    $componentProperty = $reflection->getProperty('component');
    $componentProperty->setAccessible(true);

    expect($componentProperty->getValue($response))->toBe('TenantDashboard');
});

it('allows access to Supervisor users', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForActionCenter($tenant);

    $supervisor = createTenantUserForActionCenter($tenant, TenantUserRole::Supervisor);
    createEmployeeForUser($supervisor);
    $this->actingAs($supervisor);

    $controller = app(ActionCenterDashboardController::class);
    $response = $controller(request());

    $reflection = new ReflectionClass($response);
    $componentProperty = $reflection->getProperty('component');
    $componentProperty->setAccessible(true);

    expect($componentProperty->getValue($response))->toBe('TenantDashboard');
});

/*
|--------------------------------------------------------------------------
| Pending Actions Count Tests
|--------------------------------------------------------------------------
*/

it('returns correct pending leave approval count', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForActionCenter($tenant);

    $admin = createTenantUserForActionCenter($tenant, TenantUserRole::Admin);
    $approverEmployee = createEmployeeForUser($admin);
    $this->actingAs($admin);

    // Create employees with leave applications
    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create();

    // Create 3 pending leave approvals for this approver
    for ($i = 0; $i < 3; $i++) {
        $leaveApplication = LeaveApplication::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
        ]);

        LeaveApplicationApproval::factory()->create([
            'leave_application_id' => $leaveApplication->id,
            'approver_employee_id' => $approverEmployee->id,
            'decision' => LeaveApprovalDecision::Pending,
        ]);
    }

    // Create request with user resolver
    $request = request();
    $request->setUserResolver(fn () => $admin);

    $controller = app(ActionCenterDashboardController::class);
    $response = $controller($request);

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['pendingActions']['leaveApprovals'])->toBe(3);
});

it('returns zero counts when no pending approvals exist', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForActionCenter($tenant);

    $admin = createTenantUserForActionCenter($tenant, TenantUserRole::Admin);
    createEmployeeForUser($admin);
    $this->actingAs($admin);

    $controller = app(ActionCenterDashboardController::class);
    $response = $controller(request());

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['pendingActions']['leaveApprovals'])->toBe(0)
        ->and($props['pendingActions']['requisitionApprovals'])->toBe(0)
        ->and($props['pendingActions']['probationaryEvaluations'])->toBe(0)
        ->and($props['pendingActions']['documentRequests'])->toBe(0)
        ->and($props['pendingActions']['onboardingTasks'])->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Priority Items Tests
|--------------------------------------------------------------------------
*/

it('identifies overdue leave approvals as critical priority', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForActionCenter($tenant);

    $admin = createTenantUserForActionCenter($tenant, TenantUserRole::Admin);
    $approverEmployee = createEmployeeForUser($admin);
    $this->actingAs($admin);

    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create();

    $leaveApplication = LeaveApplication::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
    ]);

    // Create an overdue approval (created more than 48 hours ago)
    LeaveApplicationApproval::factory()->create([
        'leave_application_id' => $leaveApplication->id,
        'approver_employee_id' => $approverEmployee->id,
        'decision' => LeaveApprovalDecision::Pending,
        'created_at' => Carbon::now()->subHours(60),
    ]);

    $request = request();
    $request->setUserResolver(fn () => $admin);

    $controller = app(ActionCenterDashboardController::class);
    $response = $controller($request);

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['priorityItems'])->toHaveCount(1)
        ->and($props['priorityItems'][0]['priority'])->toBe('critical')
        ->and($props['priorityItems'][0]['type'])->toBe('leave_approval');
});

it('identifies approaching deadline approvals as high priority', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForActionCenter($tenant);

    $admin = createTenantUserForActionCenter($tenant, TenantUserRole::Admin);
    $approverEmployee = createEmployeeForUser($admin);
    $this->actingAs($admin);

    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create();

    $leaveApplication = LeaveApplication::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
    ]);

    // Create an approaching deadline approval (created 30 hours ago)
    LeaveApplicationApproval::factory()->create([
        'leave_application_id' => $leaveApplication->id,
        'approver_employee_id' => $approverEmployee->id,
        'decision' => LeaveApprovalDecision::Pending,
        'created_at' => Carbon::now()->subHours(30),
    ]);

    $request = request();
    $request->setUserResolver(fn () => $admin);

    $controller = app(ActionCenterDashboardController::class);
    $response = $controller($request);

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['priorityItems'])->toHaveCount(1)
        ->and($props['priorityItems'][0]['priority'])->toBe('high')
        ->and($props['priorityItems'][0]['type'])->toBe('leave_approval');
});

it('does not include decided approvals in priority items', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForActionCenter($tenant);

    $admin = createTenantUserForActionCenter($tenant, TenantUserRole::Admin);
    $approverEmployee = createEmployeeForUser($admin);
    $this->actingAs($admin);

    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create();

    $leaveApplication = LeaveApplication::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
    ]);

    // Create an already approved approval (even if it was created long ago)
    LeaveApplicationApproval::factory()->create([
        'leave_application_id' => $leaveApplication->id,
        'approver_employee_id' => $approverEmployee->id,
        'decision' => LeaveApprovalDecision::Approved,
        'created_at' => Carbon::now()->subHours(60),
        'decided_at' => Carbon::now(),
    ]);

    $controller = app(ActionCenterDashboardController::class);
    $response = $controller(request());

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['priorityItems'])->toHaveCount(0);
});

it('sorts priority items with critical first', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForActionCenter($tenant);

    $admin = createTenantUserForActionCenter($tenant, TenantUserRole::Admin);
    $approverEmployee = createEmployeeForUser($admin);
    $this->actingAs($admin);

    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create();

    // Create approaching deadline approval first
    $leaveApplication1 = LeaveApplication::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
    ]);
    LeaveApplicationApproval::factory()->create([
        'leave_application_id' => $leaveApplication1->id,
        'approver_employee_id' => $approverEmployee->id,
        'decision' => LeaveApprovalDecision::Pending,
        'created_at' => Carbon::now()->subHours(30),
    ]);

    // Create overdue approval second
    $leaveApplication2 = LeaveApplication::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
    ]);
    LeaveApplicationApproval::factory()->create([
        'leave_application_id' => $leaveApplication2->id,
        'approver_employee_id' => $approverEmployee->id,
        'decision' => LeaveApprovalDecision::Pending,
        'created_at' => Carbon::now()->subHours(60),
    ]);

    $request = request();
    $request->setUserResolver(fn () => $admin);

    $controller = app(ActionCenterDashboardController::class);
    $response = $controller($request);

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['priorityItems'])->toHaveCount(2)
        ->and($props['priorityItems'][0]['priority'])->toBe('critical')
        ->and($props['priorityItems'][1]['priority'])->toBe('high');
});

/*
|--------------------------------------------------------------------------
| Props Structure Tests
|--------------------------------------------------------------------------
*/

it('returns all required props for the dashboard', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForActionCenter($tenant);

    $admin = createTenantUserForActionCenter($tenant, TenantUserRole::Admin);
    createEmployeeForUser($admin);
    $this->actingAs($admin);

    $controller = app(ActionCenterDashboardController::class);
    $response = $controller(request());

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props)->toHaveKey('pendingActions')
        ->and($props)->toHaveKey('priorityItems')
        ->and($props)->toHaveKey('notifications')
        ->and($props)->toHaveKey('unreadNotificationCount')
        ->and($props)->toHaveKey('activityFeed')
        ->and($props)->toHaveKey('pendingLeaveDetails')
        ->and($props)->toHaveKey('pendingRequisitionDetails');

    // Verify pendingActions structure
    expect($props['pendingActions'])->toHaveKey('leaveApprovals')
        ->and($props['pendingActions'])->toHaveKey('requisitionApprovals')
        ->and($props['pendingActions'])->toHaveKey('probationaryEvaluations')
        ->and($props['pendingActions'])->toHaveKey('documentRequests')
        ->and($props['pendingActions'])->toHaveKey('onboardingTasks');
});

it('returns justCreated prop when query parameter is present', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForActionCenter($tenant);

    $admin = createTenantUserForActionCenter($tenant, TenantUserRole::Admin);
    createEmployeeForUser($admin);
    $this->actingAs($admin);

    // Create a request with the just_created query parameter
    $request = request();
    $request->merge(['just_created' => '1']);

    $controller = app(ActionCenterDashboardController::class);
    $response = $controller($request);

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['justCreated'])->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Model Priority Scopes Tests
|--------------------------------------------------------------------------
*/

it('LeaveApplicationApproval model correctly identifies overdue status', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForActionCenter($tenant);

    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create();
    $leaveApplication = LeaveApplication::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
    ]);

    $overdueApproval = LeaveApplicationApproval::factory()->create([
        'leave_application_id' => $leaveApplication->id,
        'approval_level' => 1,
        'decision' => LeaveApprovalDecision::Pending,
        'created_at' => Carbon::now()->subHours(60),
    ]);

    $recentApproval = LeaveApplicationApproval::factory()->create([
        'leave_application_id' => $leaveApplication->id,
        'approval_level' => 2,
        'decision' => LeaveApprovalDecision::Pending,
        'created_at' => Carbon::now()->subHours(10),
    ]);

    expect($overdueApproval->fresh()->is_overdue)->toBeTrue()
        ->and($recentApproval->fresh()->is_overdue)->toBeFalse();
});

it('LeaveApplicationApproval model correctly identifies approaching deadline', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForActionCenter($tenant);

    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create();
    $leaveApplication = LeaveApplication::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
    ]);

    $approachingApproval = LeaveApplicationApproval::factory()->create([
        'leave_application_id' => $leaveApplication->id,
        'approval_level' => 1,
        'decision' => LeaveApprovalDecision::Pending,
        'created_at' => Carbon::now()->subHours(30),
    ]);

    $recentApproval = LeaveApplicationApproval::factory()->create([
        'leave_application_id' => $leaveApplication->id,
        'approval_level' => 2,
        'decision' => LeaveApprovalDecision::Pending,
        'created_at' => Carbon::now()->subHours(10),
    ]);

    expect($approachingApproval->fresh()->is_approaching_deadline)->toBeTrue()
        ->and($recentApproval->fresh()->is_approaching_deadline)->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Inline Approval Service Tests
|--------------------------------------------------------------------------
| These tests use the services directly instead of HTTP endpoints
| since the API routes require the tenant subdomain which is complex
| to configure in feature tests.
*/

it('approves leave application via inline approval service', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForActionCenter($tenant);

    $admin = createTenantUserForActionCenter($tenant, TenantUserRole::Admin);
    $approverEmployee = createEmployeeForUser($admin);
    $this->actingAs($admin);

    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create();

    $leaveApplication = LeaveApplication::factory()->pending()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'current_approval_level' => 1,
        'total_approval_levels' => 1,
    ]);

    LeaveApplicationApproval::factory()->create([
        'leave_application_id' => $leaveApplication->id,
        'approver_employee_id' => $approverEmployee->id,
        'approval_level' => 1,
        'decision' => LeaveApprovalDecision::Pending,
    ]);

    $service = app(\App\Services\LeaveApplicationService::class);
    $result = $service->approve($leaveApplication, $approverEmployee, 'Approved via dashboard');

    expect($result)->not->toBeNull()
        ->and($result->status->value)->toBe('approved');
});

it('rejects leave application via inline approval service with reason', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForActionCenter($tenant);

    $admin = createTenantUserForActionCenter($tenant, TenantUserRole::Admin);
    $approverEmployee = createEmployeeForUser($admin);
    $this->actingAs($admin);

    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create();

    $leaveApplication = LeaveApplication::factory()->pending()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'current_approval_level' => 1,
        'total_approval_levels' => 1,
    ]);

    LeaveApplicationApproval::factory()->create([
        'leave_application_id' => $leaveApplication->id,
        'approver_employee_id' => $approverEmployee->id,
        'approval_level' => 1,
        'decision' => LeaveApprovalDecision::Pending,
    ]);

    $service = app(\App\Services\LeaveApplicationService::class);
    $result = $service->reject($leaveApplication, $approverEmployee, 'Insufficient leave balance');

    expect($result)->not->toBeNull()
        ->and($result->status->value)->toBe('rejected');
});

it('InlineApprovalController validates rejection requires reason', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForActionCenter($tenant);

    $admin = createTenantUserForActionCenter($tenant, TenantUserRole::Admin);
    $approverEmployee = createEmployeeForUser($admin);
    $this->actingAs($admin);

    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create();

    $leaveApplication = LeaveApplication::factory()->pending()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
    ]);

    LeaveApplicationApproval::factory()->create([
        'leave_application_id' => $leaveApplication->id,
        'approver_employee_id' => $approverEmployee->id,
        'decision' => LeaveApprovalDecision::Pending,
    ]);

    // Validate that the RejectLeaveRequest enforces the 'reason' field
    $formRequest = new \App\Http\Requests\Api\RejectLeaveRequest;
    $validator = \Illuminate\Support\Facades\Validator::make(
        [], // No reason provided
        $formRequest->rules(),
        $formRequest->messages()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('reason'))->toBeTrue();
    expect($validator->errors()->first('reason'))->toBe('Please provide a reason for rejection.');
});
