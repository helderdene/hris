<?php

use App\Enums\LeaveApprovalDecision;
use App\Enums\TenantUserRole;
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
function bindTenantForBrowser(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForBrowser(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
{
    $user = User::factory()->create(array_merge([
        'password' => bcrypt('password'),
    ], $userAttributes));
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
function createEmployeeForBrowser(User $user, array $attributes = []): Employee
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

it('loads the action center dashboard successfully', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForBrowser($tenant);

    $admin = createTenantUserForBrowser($tenant, TenantUserRole::Admin);
    createEmployeeForBrowser($admin);

    $this->actingAs($admin);

    $page = visit('/dashboard');

    $page->assertSee('Welcome to')
        ->assertSee('Action Center')
        ->assertNoJavascriptErrors();
})->skip('Browser testing requires Playwright installation');

it('displays priority alerts for overdue approvals', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForBrowser($tenant);

    $admin = createTenantUserForBrowser($tenant, TenantUserRole::Admin);
    $approverEmployee = createEmployeeForBrowser($admin);

    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create();

    $leaveApplication = LeaveApplication::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
    ]);

    // Create an overdue approval
    LeaveApplicationApproval::factory()->create([
        'leave_application_id' => $leaveApplication->id,
        'approver_employee_id' => $approverEmployee->id,
        'decision' => LeaveApprovalDecision::Pending,
        'created_at' => Carbon::now()->subHours(60),
    ]);

    $this->actingAs($admin);

    $page = visit('/dashboard');

    $page->assertSee('Priority Alerts')
        ->assertSee('Overdue')
        ->assertNoJavascriptErrors();
})->skip('Browser testing requires Playwright installation');

it('shows inline approval dialog when approve button is clicked', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForBrowser($tenant);

    $admin = createTenantUserForBrowser($tenant, TenantUserRole::Admin);
    $approverEmployee = createEmployeeForBrowser($admin);

    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create();

    $leaveApplication = LeaveApplication::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
    ]);

    LeaveApplicationApproval::factory()->create([
        'leave_application_id' => $leaveApplication->id,
        'approver_employee_id' => $approverEmployee->id,
        'decision' => LeaveApprovalDecision::Pending,
        'created_at' => Carbon::now()->subHours(60),
    ]);

    $this->actingAs($admin);

    $page = visit('/dashboard');

    $page->click('Approve')
        ->assertSee('Approve Request')
        ->assertNoJavascriptErrors();
})->skip('Browser testing requires Playwright installation');

it('shows inline reject dialog when reject button is clicked', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForBrowser($tenant);

    $admin = createTenantUserForBrowser($tenant, TenantUserRole::Admin);
    $approverEmployee = createEmployeeForBrowser($admin);

    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create();

    $leaveApplication = LeaveApplication::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
    ]);

    LeaveApplicationApproval::factory()->create([
        'leave_application_id' => $leaveApplication->id,
        'approver_employee_id' => $approverEmployee->id,
        'decision' => LeaveApprovalDecision::Pending,
        'created_at' => Carbon::now()->subHours(60),
    ]);

    $this->actingAs($admin);

    $page = visit('/dashboard');

    $page->click('Reject')
        ->assertSee('Reject Request')
        ->assertSee('Reason for rejection')
        ->assertNoJavascriptErrors();
})->skip('Browser testing requires Playwright installation');

it('displays pending actions with correct counts', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForBrowser($tenant);

    $admin = createTenantUserForBrowser($tenant, TenantUserRole::Admin);
    createEmployeeForBrowser($admin);

    $this->actingAs($admin);

    $page = visit('/dashboard');

    $page->assertSee('Pending Actions')
        ->assertSee('Leave Requests')
        ->assertSee('Job Requisitions')
        ->assertNoJavascriptErrors();
})->skip('Browser testing requires Playwright installation');

it('displays quick actions section with navigation links', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForBrowser($tenant);

    $admin = createTenantUserForBrowser($tenant, TenantUserRole::Admin);
    createEmployeeForBrowser($admin);

    $this->actingAs($admin);

    $page = visit('/dashboard');

    $page->assertSee('Quick Actions')
        ->assertSee('Add Employee')
        ->assertSee('Invite Member')
        ->assertSee('HR Analytics')
        ->assertNoJavascriptErrors();
})->skip('Browser testing requires Playwright installation');

it('displays notifications hub with loading skeleton', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForBrowser($tenant);

    $admin = createTenantUserForBrowser($tenant, TenantUserRole::Admin);
    createEmployeeForBrowser($admin);

    $this->actingAs($admin);

    $page = visit('/dashboard');

    $page->assertSee('Notifications')
        ->assertNoJavascriptErrors();
})->skip('Browser testing requires Playwright installation');

it('displays activity feed with loading skeleton', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForBrowser($tenant);

    $admin = createTenantUserForBrowser($tenant, TenantUserRole::Admin);
    createEmployeeForBrowser($admin);

    $this->actingAs($admin);

    $page = visit('/dashboard');

    $page->assertSee('Activity Feed')
        ->assertNoJavascriptErrors();
})->skip('Browser testing requires Playwright installation');

it('shows connection status indicator', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForBrowser($tenant);

    $admin = createTenantUserForBrowser($tenant, TenantUserRole::Admin);
    createEmployeeForBrowser($admin);

    $this->actingAs($admin);

    $page = visit('/dashboard');

    // The connection status should show Live, Polling, or Offline
    $page->assertNoJavascriptErrors();
})->skip('Browser testing requires Playwright installation');

it('is responsive on mobile viewport', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForBrowser($tenant);

    $admin = createTenantUserForBrowser($tenant, TenantUserRole::Admin);
    createEmployeeForBrowser($admin);

    $this->actingAs($admin);

    $page = visit('/dashboard')
        ->on()->mobile();

    $page->assertSee('Welcome to')
        ->assertNoJavascriptErrors();
})->skip('Browser testing requires Playwright installation');

it('works correctly in dark mode', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForBrowser($tenant);

    $admin = createTenantUserForBrowser($tenant, TenantUserRole::Admin);
    createEmployeeForBrowser($admin);

    $this->actingAs($admin);

    $page = visit('/dashboard')
        ->inDarkMode();

    $page->assertSee('Welcome to')
        ->assertNoJavascriptErrors();
})->skip('Browser testing requires Playwright installation');
