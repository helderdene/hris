<?php

use App\Enums\AdjustmentCategory;
use App\Enums\AdjustmentFrequency;
use App\Enums\AdjustmentStatus;
use App\Enums\AdjustmentType;
use App\Enums\RecurringInterval;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\EmployeeAdjustmentController;
use App\Http\Requests\StoreEmployeeAdjustmentRequest;
use App\Http\Requests\UpdateAdjustmentStatusRequest;
use App\Http\Requests\UpdateEmployeeAdjustmentRequest;
use App\Models\Employee;
use App\Models\EmployeeAdjustment;
use App\Models\PayrollPeriod;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

function bindTenantContextForAdjustment(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createTenantUserForAdjustment(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
{
    $user = User::factory()->create($userAttributes);
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

function createStoreAdjustmentRequest(array $data, User $user): StoreEmployeeAdjustmentRequest
{
    $request = StoreEmployeeAdjustmentRequest::create('/api/adjustments', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new StoreEmployeeAdjustmentRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

function createUpdateAdjustmentRequest(array $data, User $user): UpdateEmployeeAdjustmentRequest
{
    $request = UpdateEmployeeAdjustmentRequest::create('/api/adjustments/1', 'PUT', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $rules = (new UpdateEmployeeAdjustmentRequest)->rules();
    $validator = Validator::make($data, $rules);

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

function createUpdateAdjustmentStatusRequest(array $data, User $user): UpdateAdjustmentStatusRequest
{
    $request = UpdateAdjustmentStatusRequest::create('/api/adjustments/1/status', 'PATCH', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new UpdateAdjustmentStatusRequest)->rules());

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('Adjustment API Index', function () {
    it('returns adjustment list for authorized users', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $hrManager = createTenantUserForAdjustment($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $employee = Employee::factory()->create();
        EmployeeAdjustment::factory()->forEmployee($employee)->oneTimeAllowance()->create();
        EmployeeAdjustment::factory()->forEmployee($employee)->recurringDeduction()->create();

        $controller = new EmployeeAdjustmentController;
        $request = Request::create('/api/adjustments', 'GET');
        $response = $controller->index($request);

        expect($response->count())->toBe(2);
    });

    it('filters adjustments by status', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $hrManager = createTenantUserForAdjustment($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        EmployeeAdjustment::factory()->active()->create();
        EmployeeAdjustment::factory()->completed()->create();

        $controller = new EmployeeAdjustmentController;
        $request = Request::create('/api/adjustments', 'GET', ['status' => 'active']);
        $response = $controller->index($request);

        expect($response->count())->toBe(1);
    });

    it('filters adjustments by category', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $hrManager = createTenantUserForAdjustment($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        EmployeeAdjustment::factory()->oneTimeAllowance()->create();
        EmployeeAdjustment::factory()->recurringDeduction()->create();

        $controller = new EmployeeAdjustmentController;
        $request = Request::create('/api/adjustments', 'GET', ['category' => 'earning']);
        $response = $controller->index($request);

        expect($response->count())->toBe(1);
    });

    it('filters adjustments by adjustment type', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $hrManager = createTenantUserForAdjustment($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        EmployeeAdjustment::factory()->create([
            'adjustment_type' => AdjustmentType::AllowanceTransportation,
        ]);
        EmployeeAdjustment::factory()->create([
            'adjustment_type' => AdjustmentType::AllowanceMeal,
        ]);

        $controller = new EmployeeAdjustmentController;
        $request = Request::create('/api/adjustments', 'GET', ['adjustment_type' => 'allowance_transportation']);
        $response = $controller->index($request);

        expect($response->count())->toBe(1);
    });

    it('filters adjustments by frequency', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $hrManager = createTenantUserForAdjustment($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        EmployeeAdjustment::factory()->oneTimeAllowance()->create();
        EmployeeAdjustment::factory()->recurringDeduction()->create();

        $controller = new EmployeeAdjustmentController;
        $request = Request::create('/api/adjustments', 'GET', ['frequency' => 'recurring']);
        $response = $controller->index($request);

        expect($response->count())->toBe(1);
    });

    it('filters adjustments by employee', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $hrManager = createTenantUserForAdjustment($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();

        EmployeeAdjustment::factory()->forEmployee($employee1)->count(2)->create();
        EmployeeAdjustment::factory()->forEmployee($employee2)->create();

        $controller = new EmployeeAdjustmentController;
        $request = Request::create('/api/adjustments', 'GET', ['employee_id' => $employee1->id]);
        $response = $controller->index($request);

        expect($response->count())->toBe(2);
    });
});

describe('Adjustment API Store', function () {
    it('creates a new one-time adjustment', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $hrManager = createTenantUserForAdjustment($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $employee = Employee::factory()->create();
        $payrollPeriod = PayrollPeriod::factory()->create();

        $adjustmentData = [
            'employee_id' => $employee->id,
            'adjustment_category' => AdjustmentCategory::Earning,
            'adjustment_type' => AdjustmentType::AllowanceTransportation,
            'adjustment_code' => 'ADJ-TEST-001',
            'name' => 'Transportation Allowance',
            'frequency' => AdjustmentFrequency::OneTime,
            'amount' => 5000,
            'target_payroll_period_id' => $payrollPeriod->id,
            'description' => 'Transportation allowance for January',
            'status' => AdjustmentStatus::Active,
            'created_by' => $hrManager->id,
        ];

        $adjustment = EmployeeAdjustment::create($adjustmentData);

        expect($adjustment->adjustment_type)->toBe(AdjustmentType::AllowanceTransportation);
        expect($adjustment->status)->toBe(AdjustmentStatus::Active);
        expect($adjustment->frequency)->toBe(AdjustmentFrequency::OneTime);

        $this->assertDatabaseHas('employee_adjustments', [
            'employee_id' => $employee->id,
            'adjustment_type' => 'allowance_transportation',
        ]);
    });

    it('creates a new recurring adjustment with balance tracking', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $hrManager = createTenantUserForAdjustment($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $employee = Employee::factory()->create();

        $adjustmentData = [
            'employee_id' => $employee->id,
            'adjustment_category' => AdjustmentCategory::Deduction,
            'adjustment_type' => AdjustmentType::LoanSalaryAdvance,
            'adjustment_code' => 'ADJ-LOAN-001',
            'name' => 'Salary Advance',
            'frequency' => AdjustmentFrequency::Recurring,
            'amount' => 2000,
            'has_balance_tracking' => true,
            'total_amount' => 20000,
            'remaining_balance' => 20000,
            'recurring_interval' => RecurringInterval::EveryPeriod,
            'recurring_start_date' => '2026-01-01',
            'description' => 'Salary advance repayment',
            'status' => AdjustmentStatus::Active,
            'created_by' => $hrManager->id,
        ];

        $adjustment = EmployeeAdjustment::create($adjustmentData);

        expect($adjustment->adjustment_type)->toBe(AdjustmentType::LoanSalaryAdvance);
        expect($adjustment->total_amount)->toBe('20000.00');
        expect($adjustment->remaining_balance)->toBe('20000.00');
        expect($adjustment->has_balance_tracking)->toBeTrue();

        $this->assertDatabaseHas('employee_adjustments', [
            'employee_id' => $employee->id,
            'adjustment_type' => 'loan_salary_advance',
            'total_amount' => '20000.00',
        ]);
    });

    it('validates required fields', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $hrManager = createTenantUserForAdjustment($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $validator = Validator::make([], (new StoreEmployeeAdjustmentRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('employee_id'))->toBeTrue();
        expect($validator->errors()->has('adjustment_type'))->toBeTrue();
        expect($validator->errors()->has('amount'))->toBeTrue();
    });
});

describe('Adjustment API Show', function () {
    it('returns adjustment details', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $hrManager = createTenantUserForAdjustment($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $adjustment = EmployeeAdjustment::factory()->oneTimeAllowance()->create();

        $controller = new EmployeeAdjustmentController;
        $response = $controller->show($tenant->slug, $adjustment);

        $data = $response->resolve();
        expect($data['id'])->toBe($adjustment->id);
        expect($data['adjustment_category'])->toBe('earning');
    });
});

describe('Adjustment API Update', function () {
    it('updates adjustment details', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $hrManager = createTenantUserForAdjustment($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $adjustment = EmployeeAdjustment::factory()->active()->create([
            'amount' => 2000,
        ]);

        $data = [
            'amount' => 2500,
            'description' => 'Updated adjustment amount',
        ];

        $controller = new EmployeeAdjustmentController;
        $request = createUpdateAdjustmentRequest($data, $hrManager);
        $response = $controller->update($request, $tenant->slug, $adjustment);

        $responseData = $response->resolve();
        expect($responseData['amount'])->toBe(2500.0);

        $adjustment->refresh();
        expect($adjustment->amount)->toBe('2500.00');
    });

    it('rejects updates to completed adjustments', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $hrManager = createTenantUserForAdjustment($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $adjustment = EmployeeAdjustment::factory()->completed()->create();

        $data = [
            'amount' => 2500,
        ];

        $controller = new EmployeeAdjustmentController;
        $request = createUpdateAdjustmentRequest($data, $hrManager);

        expect(fn () => $controller->update($request, $tenant->slug, $adjustment))
            ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
    });

    it('allows updates to on-hold adjustments', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $hrManager = createTenantUserForAdjustment($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $adjustment = EmployeeAdjustment::factory()->onHold()->create([
            'amount' => 2000,
        ]);

        $data = [
            'amount' => 2500,
        ];

        $controller = new EmployeeAdjustmentController;
        $request = createUpdateAdjustmentRequest($data, $hrManager);
        $response = $controller->update($request, $tenant->slug, $adjustment);

        $adjustment->refresh();
        expect($adjustment->amount)->toBe('2500.00');
    });
});

describe('Adjustment API Status Update', function () {
    it('updates adjustment status to on_hold', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $hrManager = createTenantUserForAdjustment($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $adjustment = EmployeeAdjustment::factory()->active()->create();

        $data = [
            'status' => 'on_hold',
            'notes' => 'Temporary hold',
        ];

        $controller = new EmployeeAdjustmentController;
        $request = createUpdateAdjustmentStatusRequest($data, $hrManager);
        $response = $controller->updateStatus($request, $tenant->slug, $adjustment);

        $responseData = $response->resolve();
        expect($responseData['status'])->toBe('on_hold');

        $adjustment->refresh();
        expect($adjustment->status)->toBe(AdjustmentStatus::OnHold);
    });

    it('updates adjustment status from on_hold to active', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $hrManager = createTenantUserForAdjustment($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $adjustment = EmployeeAdjustment::factory()->onHold()->create();

        $data = [
            'status' => 'active',
        ];

        $controller = new EmployeeAdjustmentController;
        $request = createUpdateAdjustmentStatusRequest($data, $hrManager);
        $response = $controller->updateStatus($request, $tenant->slug, $adjustment);

        $adjustment->refresh();
        expect($adjustment->status)->toBe(AdjustmentStatus::Active);
    });

    it('updates adjustment status to completed', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $hrManager = createTenantUserForAdjustment($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $adjustment = EmployeeAdjustment::factory()->active()->create();

        $data = [
            'status' => 'completed',
        ];

        $controller = new EmployeeAdjustmentController;
        $request = createUpdateAdjustmentStatusRequest($data, $hrManager);
        $response = $controller->updateStatus($request, $tenant->slug, $adjustment);

        $adjustment->refresh();
        expect($adjustment->status)->toBe(AdjustmentStatus::Completed);
    });

    it('updates adjustment status to cancelled', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $hrManager = createTenantUserForAdjustment($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $adjustment = EmployeeAdjustment::factory()->active()->create();

        $data = [
            'status' => 'cancelled',
            'notes' => 'Cancellation reason',
        ];

        $controller = new EmployeeAdjustmentController;
        $request = createUpdateAdjustmentStatusRequest($data, $hrManager);
        $response = $controller->updateStatus($request, $tenant->slug, $adjustment);

        $adjustment->refresh();
        expect($adjustment->status)->toBe(AdjustmentStatus::Cancelled);
    });

    it('rejects invalid status transitions', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $hrManager = createTenantUserForAdjustment($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $adjustment = EmployeeAdjustment::factory()->completed()->create();

        // Completed adjustments cannot transition back to active
        $currentStatus = $adjustment->status;
        expect($currentStatus->canTransitionTo(AdjustmentStatus::Active))->toBeFalse();
    });
});

describe('Adjustment API Delete', function () {
    it('deletes an adjustment without applications', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $hrManager = createTenantUserForAdjustment($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $adjustment = EmployeeAdjustment::factory()->create();

        $controller = new EmployeeAdjustmentController;
        $response = $controller->destroy($tenant->slug, $adjustment);

        expect($response->getStatusCode())->toBe(200);

        // The model uses SoftDeletes - record should be hard deleted since it has no applications
        $this->assertDatabaseMissing('employee_adjustments', [
            'id' => $adjustment->id,
            'deleted_at' => null,
        ]);
    });

    it('rejects deletion of adjustment with applications', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $hrManager = createTenantUserForAdjustment($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $adjustment = EmployeeAdjustment::factory()->recurringDeduction()->create([
            'total_amount' => 10000,
            'remaining_balance' => 8000,
        ]);

        $payrollPeriod = PayrollPeriod::factory()->create();

        $adjustment->applications()->create([
            'payroll_period_id' => $payrollPeriod->id,
            'amount' => 2000,
            'balance_before' => 10000,
            'balance_after' => 8000,
            'applied_at' => now(),
            'status' => 'applied',
        ]);

        $controller = new EmployeeAdjustmentController;
        $response = $controller->destroy($tenant->slug, $adjustment);

        expect($response->getStatusCode())->toBe(422);
        expect($response->getData(true)['message'])->toContain('applied to payroll');

        $this->assertDatabaseHas('employee_adjustments', ['id' => $adjustment->id]);
    });
});

describe('Employee Adjustments Endpoint', function () {
    it('returns adjustments for specific employee', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $hrManager = createTenantUserForAdjustment($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();

        EmployeeAdjustment::factory()->forEmployee($employee1)->count(2)->create();
        EmployeeAdjustment::factory()->forEmployee($employee2)->create();

        $controller = new EmployeeAdjustmentController;
        $request = Request::create('/api/employees/1/adjustments', 'GET');
        $response = $controller->employeeAdjustments($request, $tenant->slug, $employee1);

        expect($response->count())->toBe(2);
    });

    it('filters employee adjustments by status', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $hrManager = createTenantUserForAdjustment($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $employee = Employee::factory()->create();

        EmployeeAdjustment::factory()->forEmployee($employee)->active()->create();
        EmployeeAdjustment::factory()->forEmployee($employee)->completed()->create();

        $controller = new EmployeeAdjustmentController;
        $request = Request::create('/api/employees/1/adjustments', 'GET', ['status' => 'active']);
        $response = $controller->employeeAdjustments($request, $tenant->slug, $employee);

        expect($response->count())->toBe(1);
    });

    it('filters employee adjustments by category', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $hrManager = createTenantUserForAdjustment($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $employee = Employee::factory()->create();

        EmployeeAdjustment::factory()->forEmployee($employee)->oneTimeAllowance()->create();
        EmployeeAdjustment::factory()->forEmployee($employee)->recurringDeduction()->create();

        $controller = new EmployeeAdjustmentController;
        $request = Request::create('/api/employees/1/adjustments', 'GET', ['category' => 'earning']);
        $response = $controller->employeeAdjustments($request, $tenant->slug, $employee);

        expect($response->count())->toBe(1);
    });

    it('returns only active adjustments when requested', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $hrManager = createTenantUserForAdjustment($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $employee = Employee::factory()->create();

        EmployeeAdjustment::factory()->forEmployee($employee)->active()->create();
        EmployeeAdjustment::factory()->forEmployee($employee)->onHold()->create();
        EmployeeAdjustment::factory()->forEmployee($employee)->completed()->create();

        $controller = new EmployeeAdjustmentController;
        $request = Request::create('/api/employees/1/adjustments', 'GET', ['active_only' => true]);
        $response = $controller->employeeAdjustments($request, $tenant->slug, $employee);

        expect($response->count())->toBe(1);
    });
});

describe('Period Adjustments Endpoint', function () {
    it('returns adjustments applicable to a payroll period', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $hrManager = createTenantUserForAdjustment($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $payrollPeriod = PayrollPeriod::factory()->create([
            'cutoff_start' => '2026-01-01',
            'cutoff_end' => '2026-01-15',
        ]);

        // One-time adjustment for this period - should be included
        EmployeeAdjustment::factory()->oneTime()->active()->create([
            'target_payroll_period_id' => $payrollPeriod->id,
        ]);

        // Recurring adjustment with start date before period - should be included
        EmployeeAdjustment::factory()->recurring()->active()->create([
            'recurring_start_date' => '2025-12-01',
            'recurring_end_date' => null,
        ]);

        // Completed adjustment - should not be included
        EmployeeAdjustment::factory()->recurring()->completed()->create([
            'recurring_start_date' => '2026-01-05',
        ]);

        $controller = new EmployeeAdjustmentController;
        $request = Request::create('/api/payroll-periods/1/adjustments', 'GET');
        $response = $controller->periodAdjustments($request, $tenant->slug, $payrollPeriod);

        expect($response->count())->toBe(2);
    });
});

describe('Adjustment Model', function () {
    it('determines if adjustment type supports balance tracking', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $loanAdjustment = EmployeeAdjustment::factory()->salaryAdvance()->create();

        $allowanceAdjustment = EmployeeAdjustment::factory()->transportationAllowance()->create();

        expect($loanAdjustment->has_balance_tracking)->toBeTrue();
        expect($loanAdjustment->adjustment_type->supportsBalanceTracking())->toBeTrue();
        expect($allowanceAdjustment->has_balance_tracking)->toBeFalse();
        expect($allowanceAdjustment->adjustment_type->supportsBalanceTracking())->toBeFalse();
    });

    it('calculates correct amount for one-time adjustment', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $adjustment = EmployeeAdjustment::factory()->oneTimeAllowance()->create([
            'amount' => 5000,
        ]);

        expect($adjustment->getAmountForPeriod())->toBe(5000.0);
    });

    it('calculates correct amount for recurring adjustment with balance', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $adjustment = EmployeeAdjustment::factory()->salaryAdvance()->create([
            'amount' => 2000,
            'total_amount' => 20000,
            'remaining_balance' => 1500, // Less than regular amount
        ]);

        // Should return the remaining balance since it's less than the regular amount
        expect($adjustment->getAmountForPeriod())->toBe(1500.0);
    });

    it('records application and updates balance', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $payrollPeriod = PayrollPeriod::factory()->create();

        $adjustment = EmployeeAdjustment::factory()->salaryAdvance()->create([
            'amount' => 2000,
            'total_amount' => 10000,
            'remaining_balance' => 10000,
        ]);

        $application = $adjustment->recordApplication($payrollPeriod, null, 2000);

        expect($application->amount)->toBe('2000.00');
        expect($application->balance_before)->toBe('10000.00');
        expect($application->balance_after)->toBe('8000.00');

        $adjustment->refresh();
        expect($adjustment->remaining_balance)->toBe('8000.00');
    });

    it('marks adjustment as completed when balance reaches zero', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $payrollPeriod = PayrollPeriod::factory()->create();

        $adjustment = EmployeeAdjustment::factory()->salaryAdvance()->create([
            'amount' => 2000,
            'total_amount' => 2000,
            'remaining_balance' => 2000,
        ]);

        $adjustment->recordApplication($payrollPeriod, null, 2000);

        $adjustment->refresh();
        expect($adjustment->remaining_balance)->toBe('0.00');
        expect($adjustment->status)->toBe(AdjustmentStatus::Completed);
    });

    it('checks if recurring adjustment is applicable for period', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAdjustment($tenant);

        $payrollPeriod = PayrollPeriod::factory()->create([
            'cutoff_start' => '2026-01-01',
            'cutoff_end' => '2026-01-15',
        ]);

        // Active recurring adjustment within date range - should be included
        $activeAdjustment = EmployeeAdjustment::factory()->recurring()->active()->create([
            'recurring_start_date' => '2026-01-05',
            'recurring_end_date' => null,
        ]);

        // Recurring adjustment that ended before period - should not be included
        $expiredAdjustment = EmployeeAdjustment::factory()->recurring()->active()->create([
            'recurring_start_date' => '2025-12-01',
            'recurring_end_date' => '2025-12-31',
        ]);

        // Completed adjustment - should not be included
        $completedAdjustment = EmployeeAdjustment::factory()->recurring()->completed()->create([
            'recurring_start_date' => '2026-01-05',
        ]);

        expect($activeAdjustment->isApplicableForPeriod($payrollPeriod))->toBeTrue();
        expect($expiredAdjustment->isApplicableForPeriod($payrollPeriod))->toBeFalse();
        expect($completedAdjustment->isApplicableForPeriod($payrollPeriod))->toBeFalse();
    });
});

describe('Adjustment Status Transitions', function () {
    it('allows valid status transitions from Active', function () {
        expect(AdjustmentStatus::Active->canTransitionTo(AdjustmentStatus::OnHold))->toBeTrue();
        expect(AdjustmentStatus::Active->canTransitionTo(AdjustmentStatus::Completed))->toBeTrue();
        expect(AdjustmentStatus::Active->canTransitionTo(AdjustmentStatus::Cancelled))->toBeTrue();
    });

    it('allows valid status transitions from OnHold', function () {
        expect(AdjustmentStatus::OnHold->canTransitionTo(AdjustmentStatus::Active))->toBeTrue();
        expect(AdjustmentStatus::OnHold->canTransitionTo(AdjustmentStatus::Cancelled))->toBeTrue();
    });

    it('prevents invalid status transitions from Completed', function () {
        expect(AdjustmentStatus::Completed->canTransitionTo(AdjustmentStatus::Active))->toBeFalse();
        expect(AdjustmentStatus::Completed->canTransitionTo(AdjustmentStatus::OnHold))->toBeFalse();
        expect(AdjustmentStatus::Completed->canTransitionTo(AdjustmentStatus::Cancelled))->toBeFalse();
    });

    it('prevents invalid status transitions from Cancelled', function () {
        expect(AdjustmentStatus::Cancelled->canTransitionTo(AdjustmentStatus::Active))->toBeFalse();
        expect(AdjustmentStatus::Cancelled->canTransitionTo(AdjustmentStatus::OnHold))->toBeFalse();
        expect(AdjustmentStatus::Cancelled->canTransitionTo(AdjustmentStatus::Completed))->toBeFalse();
    });
});
