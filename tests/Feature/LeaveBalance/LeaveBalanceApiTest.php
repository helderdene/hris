<?php

use App\Enums\EmploymentStatus;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\LeaveBalanceController;
use App\Http\Requests\AdjustLeaveBalanceRequest;
use App\Http\Requests\InitializeBalancesRequest;
use App\Http\Requests\ProcessYearEndRequest;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindLeaveBalanceTenantContext(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createLeaveBalanceTenantUser(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated adjust balance request.
 */
function createAdjustBalanceRequest(array $data, User $user): AdjustLeaveBalanceRequest
{
    $request = AdjustLeaveBalanceRequest::create('/api/organization/leave-balances/1/adjust', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new AdjustLeaveBalanceRequest)->rules());
    if ($validator->fails()) {
        throw new \Illuminate\Validation\ValidationException($validator);
    }

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated initialize balances request.
 */
function createInitializeBalancesRequest(array $data, User $user): InitializeBalancesRequest
{
    $request = InitializeBalancesRequest::create('/api/organization/leave-balances/initialize', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new InitializeBalancesRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated process year end request.
 */
function createProcessYearEndRequest(array $data, User $user): ProcessYearEndRequest
{
    $request = ProcessYearEndRequest::create('/api/organization/leave-balances/process-year-end', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new ProcessYearEndRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('LeaveBalanceController index', function () {
    it('lists leave balances', function () {
        $tenant = Tenant::factory()->create();
        bindLeaveBalanceTenantContext($tenant);

        $admin = createLeaveBalanceTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $leaveType = LeaveType::factory()->create();

        LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
        ]);

        $controller = app(LeaveBalanceController::class);
        $request = Request::create('/api/organization/leave-balances', 'GET', ['year' => now()->year]);

        $response = $controller->index($request);

        expect($response->count())->toBeGreaterThan(0);
    });

    it('filters by year', function () {
        $tenant = Tenant::factory()->create();
        bindLeaveBalanceTenantContext($tenant);

        $admin = createLeaveBalanceTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $leaveType = LeaveType::factory()->create();

        LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
        ]);

        $leaveType2 = LeaveType::factory()->create();
        LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType2->id,
            'year' => now()->year - 1,
        ]);

        $controller = app(LeaveBalanceController::class);
        $request = Request::create('/api/organization/leave-balances', 'GET', ['year' => now()->year]);

        $response = $controller->index($request);

        expect($response->count())->toBe(1);
        expect($response->first()->year)->toBe(now()->year);
    });

    it('filters by employee', function () {
        $tenant = Tenant::factory()->create();
        bindLeaveBalanceTenantContext($tenant);

        $admin = createLeaveBalanceTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();
        $leaveType = LeaveType::factory()->create();

        LeaveBalance::factory()->create([
            'employee_id' => $employee1->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
        ]);

        $leaveType2 = LeaveType::factory()->create();
        LeaveBalance::factory()->create([
            'employee_id' => $employee2->id,
            'leave_type_id' => $leaveType2->id,
            'year' => now()->year,
        ]);

        $controller = app(LeaveBalanceController::class);
        $request = Request::create('/api/organization/leave-balances', 'GET', [
            'year' => now()->year,
            'employee_id' => $employee1->id,
        ]);

        $response = $controller->index($request);

        expect($response->count())->toBe(1);
        expect($response->first()->employee_id)->toBe($employee1->id);
    });

    it('filters by leave type', function () {
        $tenant = Tenant::factory()->create();
        bindLeaveBalanceTenantContext($tenant);

        $admin = createLeaveBalanceTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $leaveType1 = LeaveType::factory()->create();
        $leaveType2 = LeaveType::factory()->create();

        LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType1->id,
            'year' => now()->year,
        ]);

        LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType2->id,
            'year' => now()->year,
        ]);

        $controller = app(LeaveBalanceController::class);
        $request = Request::create('/api/organization/leave-balances', 'GET', [
            'year' => now()->year,
            'leave_type_id' => $leaveType1->id,
        ]);

        $response = $controller->index($request);

        expect($response->count())->toBe(1);
        expect($response->first()->leave_type_id)->toBe($leaveType1->id);
    });
});

describe('LeaveBalanceController summary', function () {
    it('returns aggregated statistics', function () {
        $tenant = Tenant::factory()->create();
        bindLeaveBalanceTenantContext($tenant);

        $admin = createLeaveBalanceTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $leaveType = LeaveType::factory()->create();

        LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'earned' => 15,
            'used' => 5,
        ]);

        $controller = app(LeaveBalanceController::class);
        $request = Request::create('/api/organization/leave-balances/summary', 'GET', ['year' => now()->year]);

        $response = $controller->summary($request);
        $data = json_decode($response->getContent(), true);

        expect($data)->toHaveKeys(['year', 'total_employees', 'total_credits', 'total_used', 'total_pending', 'total_available', 'utilization_rate']);
        expect($data['year'])->toBe(now()->year);
    });
});

describe('LeaveBalanceController adjust', function () {
    it('adjusts balance with credit', function () {
        $tenant = Tenant::factory()->create();
        bindLeaveBalanceTenantContext($tenant);

        $admin = createLeaveBalanceTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $leaveType = LeaveType::factory()->create();
        $balance = LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'adjustments' => 0,
        ]);

        $controller = app(LeaveBalanceController::class);
        $request = createAdjustBalanceRequest([
            'adjustment_type' => 'credit',
            'days' => 5,
            'reason' => 'Manual credit adjustment for testing purposes',
        ], $admin);

        $response = $controller->adjust($request, $balance);
        $data = json_decode($response->getContent(), true);

        expect($data['message'])->toBe('Balance adjusted successfully.');

        $balance->refresh();
        expect((float) $balance->adjustments)->toBe(5.0);
    });

    it('adjusts balance with debit', function () {
        $tenant = Tenant::factory()->create();
        bindLeaveBalanceTenantContext($tenant);

        $admin = createLeaveBalanceTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $leaveType = LeaveType::factory()->create();
        $balance = LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'adjustments' => 10,
        ]);

        $controller = app(LeaveBalanceController::class);
        $request = createAdjustBalanceRequest([
            'adjustment_type' => 'debit',
            'days' => 3,
            'reason' => 'Manual debit adjustment for testing purposes',
        ], $admin);

        $response = $controller->adjust($request, $balance);

        $balance->refresh();
        expect((float) $balance->adjustments)->toBe(7.0);
    });

    it('validates required fields', function () {
        $tenant = Tenant::factory()->create();
        bindLeaveBalanceTenantContext($tenant);

        $admin = createLeaveBalanceTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        expect(fn () => createAdjustBalanceRequest([], $admin))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('validates days must be greater than zero', function () {
        $tenant = Tenant::factory()->create();
        bindLeaveBalanceTenantContext($tenant);

        $admin = createLeaveBalanceTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        expect(fn () => createAdjustBalanceRequest([
            'adjustment_type' => 'credit',
            'days' => 0,
            'reason' => 'Testing validation',
        ], $admin))->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('validates reason minimum length', function () {
        $tenant = Tenant::factory()->create();
        bindLeaveBalanceTenantContext($tenant);

        $admin = createLeaveBalanceTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        expect(fn () => createAdjustBalanceRequest([
            'adjustment_type' => 'credit',
            'days' => 5,
            'reason' => 'Short',
        ], $admin))->toThrow(\Illuminate\Validation\ValidationException::class);
    });
});

describe('LeaveBalanceController initialize', function () {
    it('initializes balances for all active employees', function () {
        $tenant = Tenant::factory()->create();
        bindLeaveBalanceTenantContext($tenant);

        $admin = createLeaveBalanceTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Employee::factory()->count(3)->create([
            'employment_status' => EmploymentStatus::Active,
            'hire_date' => now()->subYear(),
        ]);
        LeaveType::factory()->create(['is_active' => true]);

        $controller = app(LeaveBalanceController::class);
        $request = createInitializeBalancesRequest([
            'year' => now()->year,
        ], $admin);

        $response = $controller->initialize($request);
        $data = json_decode($response->getContent(), true);

        expect($data)->toHaveKeys(['message', 'employees_count', 'balances_count']);
        expect(LeaveBalance::forYear(now()->year)->count())->toBeGreaterThan(0);
    });

    it('initializes balances for a specific employee', function () {
        $tenant = Tenant::factory()->create();
        bindLeaveBalanceTenantContext($tenant);

        $admin = createLeaveBalanceTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'hire_date' => now()->subYear(),
        ]);
        $otherEmployee = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'hire_date' => now()->subYear(),
        ]);
        LeaveType::factory()->create(['is_active' => true]);

        $controller = app(LeaveBalanceController::class);
        $request = createInitializeBalancesRequest([
            'year' => now()->year,
            'employee_id' => $employee->id,
        ], $admin);

        $response = $controller->initialize($request);

        expect(LeaveBalance::forEmployee($employee)->forYear(now()->year)->count())->toBeGreaterThan(0);
        expect(LeaveBalance::forEmployee($otherEmployee)->forYear(now()->year)->count())->toBe(0);
    });
});

describe('LeaveBalanceController processYearEnd', function () {
    it('processes year-end carry-over', function () {
        $tenant = Tenant::factory()->create();
        bindLeaveBalanceTenantContext($tenant);

        $admin = createLeaveBalanceTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
        ]);
        $leaveType = LeaveType::factory()->create([
            'allow_carry_over' => true,
            'max_carry_over_days' => 10,
        ]);

        LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year - 1,
            'earned' => 15,
            'used' => 5,
            'pending' => 0,
            'adjustments' => 0,
            'brought_forward' => 0,
            'expired' => 0,
        ]);

        $controller = app(LeaveBalanceController::class);
        $request = createProcessYearEndRequest([
            'year' => now()->year - 1,
        ], $admin);

        $response = $controller->processYearEnd($request);
        $data = json_decode($response->getContent(), true);

        expect($data)->toHaveKeys(['message', 'year', 'carried_over', 'forfeited', 'initialized']);
    });
});

describe('LeaveBalanceController employeeBalances', function () {
    it('returns employee-specific balances', function () {
        $tenant = Tenant::factory()->create();
        bindLeaveBalanceTenantContext($tenant);

        $admin = createLeaveBalanceTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $leaveType1 = LeaveType::factory()->create();
        $leaveType2 = LeaveType::factory()->create();

        LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType1->id,
            'year' => now()->year,
        ]);

        LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType2->id,
            'year' => now()->year,
        ]);

        $controller = app(LeaveBalanceController::class);
        $request = Request::create("/api/employees/{$employee->id}/leave-balances", 'GET', ['year' => now()->year]);

        $response = $controller->employeeBalances($request, $employee);

        expect($response->count())->toBe(2);
    });
});
