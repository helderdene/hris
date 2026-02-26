<?php

use App\Enums\DtrStatus;
use App\Enums\TenantUserRole;
use App\Http\Controllers\EmployeeController;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Models\EvaluationSummary;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\PerformanceCycle;
use App\Models\PerformanceCycleInstance;
use App\Models\PerformanceCycleParticipant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function bindTenantForSummary(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createSummaryTestUser(Tenant $tenant, TenantUserRole $role): User
{
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

function getSummaryInertiaProps(\Inertia\Response $response): array
{
    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);

    return $propsProperty->getValue($response);
}

function resolveSummaryDeferredProp(mixed $prop): mixed
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

it('includes summaryData as a deferred prop', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForSummary($tenant);

    $user = createSummaryTestUser($tenant, TenantUserRole::Admin);
    $this->actingAs($user);

    $employee = Employee::factory()->create();

    $controller = app()->make(EmployeeController::class);
    $response = $controller->show($employee);

    $props = getSummaryInertiaProps($response);

    expect($props)->toHaveKey('summaryData');
});

it('returns summary data with correct structure', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForSummary($tenant);

    $user = createSummaryTestUser($tenant, TenantUserRole::Admin);
    $this->actingAs($user);

    $employee = Employee::factory()->create();

    $controller = app()->make(EmployeeController::class);
    $response = $controller->show($employee);

    $props = getSummaryInertiaProps($response);
    $summaryData = resolveSummaryDeferredProp($props['summaryData']);

    expect($summaryData)->toHaveKeys([
        'period',
        'attendance',
        'overtime',
        'leave_balances',
        'performance',
        'performance_growth',
    ]);
    expect($summaryData['period'])->toBe('this_month');
    expect($summaryData['attendance'])->toHaveKeys([
        'days_present',
        'days_absent',
        'days_on_leave',
        'total_late_minutes',
        'total_late_formatted',
    ]);
    expect($summaryData['overtime'])->toHaveKeys([
        'approved_hours',
        'approved_hours_formatted',
        'request_count',
        'total_overtime_minutes',
    ]);
});

it('returns correct attendance recap for the current month', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForSummary($tenant);

    $user = createSummaryTestUser($tenant, TenantUserRole::Admin);
    $this->actingAs($user);

    $employee = Employee::factory()->create();

    // Create DTR records for this month
    DailyTimeRecord::factory()->forDate(now()->startOfMonth())->create([
        'employee_id' => $employee->id,
        'status' => DtrStatus::Present,
        'late_minutes' => 15,
    ]);
    DailyTimeRecord::factory()->forDate(now()->startOfMonth()->addDay())->create([
        'employee_id' => $employee->id,
        'status' => DtrStatus::Present,
        'late_minutes' => 10,
    ]);
    DailyTimeRecord::factory()->forDate(now()->startOfMonth()->addDays(2))->absent()->create([
        'employee_id' => $employee->id,
    ]);

    $controller = app()->make(EmployeeController::class);
    $response = $controller->show($employee);

    $props = getSummaryInertiaProps($response);
    $summaryData = resolveSummaryDeferredProp($props['summaryData']);

    expect($summaryData['attendance']['days_present'])->toBe(2);
    expect($summaryData['attendance']['days_absent'])->toBe(1);
    expect($summaryData['attendance']['total_late_minutes'])->toBe(25);
});

it('returns correct overtime summary with approved requests', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForSummary($tenant);

    $user = createSummaryTestUser($tenant, TenantUserRole::Admin);
    $this->actingAs($user);

    $employee = Employee::factory()->create();

    OvertimeRequest::factory()->approved()->create([
        'employee_id' => $employee->id,
        'overtime_date' => now()->startOfMonth(),
        'expected_minutes' => 120,
    ]);
    OvertimeRequest::factory()->approved()->create([
        'employee_id' => $employee->id,
        'overtime_date' => now()->startOfMonth()->addDay(),
        'expected_minutes' => 60,
    ]);
    // Rejected request should not count
    OvertimeRequest::factory()->rejected()->create([
        'employee_id' => $employee->id,
        'overtime_date' => now()->startOfMonth()->addDays(2),
        'expected_minutes' => 90,
    ]);

    $controller = app()->make(EmployeeController::class);
    $response = $controller->show($employee);

    $props = getSummaryInertiaProps($response);
    $summaryData = resolveSummaryDeferredProp($props['summaryData']);

    expect($summaryData['overtime']['request_count'])->toBe(2);
    expect($summaryData['overtime']['approved_hours'])->toBe(3.0);
});

it('returns leave balances for the current year', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForSummary($tenant);

    $user = createSummaryTestUser($tenant, TenantUserRole::Admin);
    $this->actingAs($user);

    $employee = Employee::factory()->create();

    $leaveType = LeaveType::factory()->serviceIncentiveLeave()->create();
    LeaveBalance::factory()
        ->forYear(now()->year)
        ->withEarned(10)
        ->withUsed(3)
        ->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
        ]);

    $controller = app()->make(EmployeeController::class);
    $response = $controller->show($employee);

    $props = getSummaryInertiaProps($response);
    $summaryData = resolveSummaryDeferredProp($props['summaryData']);

    expect($summaryData['leave_balances'])->toHaveCount(1);
    expect($summaryData['leave_balances'][0])->toHaveKeys([
        'leave_type',
        'total_credits',
        'used',
        'pending',
        'available',
    ]);
    expect($summaryData['leave_balances'][0]['used'])->toBe(3.0);
});

it('returns performance summary from latest cycle', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForSummary($tenant);

    $user = createSummaryTestUser($tenant, TenantUserRole::Admin);
    $this->actingAs($user);

    $employee = Employee::factory()->create();

    $cycle = PerformanceCycle::factory()->annual()->active()->create();
    $instance = PerformanceCycleInstance::factory()->active()->create([
        'performance_cycle_id' => $cycle->id,
    ]);
    $participant = PerformanceCycleParticipant::factory()->completed()->create([
        'performance_cycle_instance_id' => $instance->id,
        'employee_id' => $employee->id,
    ]);
    EvaluationSummary::factory()->calibrated()->create([
        'performance_cycle_participant_id' => $participant->id,
    ]);

    $controller = app()->make(EmployeeController::class);
    $response = $controller->show($employee);

    $props = getSummaryInertiaProps($response);
    $summaryData = resolveSummaryDeferredProp($props['summaryData']);

    expect($summaryData['performance'])->not->toBeNull();
    expect($summaryData['performance'])->toHaveKeys([
        'final_overall_score',
        'final_rating',
        'final_rating_label',
        'kpi_achievement',
        'goal_progress',
        'cycle_name',
    ]);
    expect($summaryData['performance']['final_overall_score'])->toBeFloat();
});

it('returns performance growth data ordered by cycle date', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForSummary($tenant);

    $user = createSummaryTestUser($tenant, TenantUserRole::Admin);
    $this->actingAs($user);

    $employee = Employee::factory()->create();
    $cycle = PerformanceCycle::factory()->annual()->active()->create();

    // First cycle instance (earlier)
    $instance1 = PerformanceCycleInstance::factory()->closed()->create([
        'performance_cycle_id' => $cycle->id,
        'name' => 'H1 2025',
        'start_date' => '2025-01-01',
        'end_date' => '2025-06-30',
    ]);
    $participant1 = PerformanceCycleParticipant::factory()->completed()->create([
        'performance_cycle_instance_id' => $instance1->id,
        'employee_id' => $employee->id,
    ]);
    EvaluationSummary::factory()->calibrated()->create([
        'performance_cycle_participant_id' => $participant1->id,
    ]);

    // Second cycle instance (later)
    $instance2 = PerformanceCycleInstance::factory()->active()->create([
        'performance_cycle_id' => $cycle->id,
        'name' => 'H2 2025',
        'start_date' => '2025-07-01',
        'end_date' => '2025-12-31',
    ]);
    $participant2 = PerformanceCycleParticipant::factory()->completed()->create([
        'performance_cycle_instance_id' => $instance2->id,
        'employee_id' => $employee->id,
    ]);
    EvaluationSummary::factory()->calibrated()->create([
        'performance_cycle_participant_id' => $participant2->id,
    ]);

    $controller = app()->make(EmployeeController::class);
    $response = $controller->show($employee);

    $props = getSummaryInertiaProps($response);
    $summaryData = resolveSummaryDeferredProp($props['summaryData']);

    expect($summaryData['performance_growth'])->toHaveCount(2);
    expect($summaryData['performance_growth'][0]['cycle_name'])->toBe('H1 2025');
    expect($summaryData['performance_growth'][1]['cycle_name'])->toBe('H2 2025');
});

it('returns empty state when employee has no data', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForSummary($tenant);

    $user = createSummaryTestUser($tenant, TenantUserRole::Admin);
    $this->actingAs($user);

    $employee = Employee::factory()->create();

    $controller = app()->make(EmployeeController::class);
    $response = $controller->show($employee);

    $props = getSummaryInertiaProps($response);
    $summaryData = resolveSummaryDeferredProp($props['summaryData']);

    expect($summaryData['attendance']['days_present'])->toBe(0);
    expect($summaryData['attendance']['days_absent'])->toBe(0);
    expect($summaryData['attendance']['days_on_leave'])->toBe(0);
    expect($summaryData['attendance']['total_late_minutes'])->toBe(0);
    expect($summaryData['overtime']['request_count'])->toBe(0);
    expect($summaryData['overtime']['approved_hours'])->toBe(0.0);
    expect($summaryData['leave_balances'])->toBeEmpty();
    expect($summaryData['performance'])->toBeNull();
    expect($summaryData['performance_growth'])->toBeEmpty();
});

it('counts approved leave days as days on leave in attendance recap', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForSummary($tenant);

    $user = createSummaryTestUser($tenant, TenantUserRole::Admin);
    $this->actingAs($user);

    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->serviceIncentiveLeave()->create();

    LeaveApplication::factory()->approved()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => now()->startOfMonth(),
        'end_date' => now()->startOfMonth()->addDays(2),
        'total_days' => 3,
    ]);

    $controller = app()->make(EmployeeController::class);
    $response = $controller->show($employee);

    $props = getSummaryInertiaProps($response);
    $summaryData = resolveSummaryDeferredProp($props['summaryData']);

    expect($summaryData['attendance']['days_on_leave'])->toBe(3);
});

it('denies access to unauthorized users', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForSummary($tenant);

    $user = createSummaryTestUser($tenant, TenantUserRole::Employee);
    $this->actingAs($user);

    $employee = Employee::factory()->create();

    $controller = app()->make(EmployeeController::class);

    expect(fn () => $controller->show($employee))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
});
