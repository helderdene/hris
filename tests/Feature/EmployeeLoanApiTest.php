<?php

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\EmployeeLoanController;
use App\Http\Requests\RecordLoanPaymentRequest;
use App\Http\Requests\StoreEmployeeLoanRequest;
use App\Http\Requests\UpdateEmployeeLoanRequest;
use App\Http\Requests\UpdateLoanStatusRequest;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

function bindTenantContextForLoan(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createTenantUserForLoan(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
{
    $user = User::factory()->create($userAttributes);
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

function createStoreLoanRequest(array $data, User $user): StoreEmployeeLoanRequest
{
    $request = StoreEmployeeLoanRequest::create('/api/loans', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new StoreEmployeeLoanRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

function createUpdateLoanRequest(array $data, User $user): UpdateEmployeeLoanRequest
{
    $request = UpdateEmployeeLoanRequest::create('/api/loans/1', 'PUT', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $rules = (new UpdateEmployeeLoanRequest)->rules();
    $validator = Validator::make($data, $rules);

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

function createUpdateStatusRequest(array $data, User $user): UpdateLoanStatusRequest
{
    $request = UpdateLoanStatusRequest::create('/api/loans/1/status', 'PATCH', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new UpdateLoanStatusRequest)->rules());

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

function createRecordPaymentRequest(array $data, User $user): RecordLoanPaymentRequest
{
    $request = RecordLoanPaymentRequest::create('/api/loans/1/payment', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new RecordLoanPaymentRequest)->rules());

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

describe('Loan API Index', function () {
    it('returns loan list for authorized users', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLoan($tenant);

        $hrManager = createTenantUserForLoan($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $employee = Employee::factory()->create();
        EmployeeLoan::factory()->forEmployee($employee)->sssSalary()->create();
        EmployeeLoan::factory()->forEmployee($employee)->pagibigMpl()->create();

        $controller = new EmployeeLoanController;
        $request = Request::create('/api/loans', 'GET');
        $response = $controller->index($request);

        expect($response->count())->toBe(2);
    });

    it('filters loans by status', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLoan($tenant);

        $hrManager = createTenantUserForLoan($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        EmployeeLoan::factory()->active()->create();
        EmployeeLoan::factory()->completed()->create();

        $controller = new EmployeeLoanController;
        $request = Request::create('/api/loans', 'GET', ['status' => 'active']);
        $response = $controller->index($request);

        expect($response->count())->toBe(1);
    });

    it('filters loans by loan type', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLoan($tenant);

        $hrManager = createTenantUserForLoan($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        EmployeeLoan::factory()->sssSalary()->create();
        EmployeeLoan::factory()->pagibigMpl()->create();

        $controller = new EmployeeLoanController;
        $request = Request::create('/api/loans', 'GET', ['loan_type' => 'sss_salary']);
        $response = $controller->index($request);

        expect($response->count())->toBe(1);
    });
});

describe('Loan API Store', function () {
    it('creates a new loan', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLoan($tenant);

        $hrManager = createTenantUserForLoan($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $employee = Employee::factory()->create();

        // Test loan creation directly via the model
        $loanData = [
            'employee_id' => $employee->id,
            'loan_type' => LoanType::SssSalary,
            'loan_code' => 'SSS-TEST-001',
            'principal_amount' => 50000,
            'interest_rate' => 0.10,
            'monthly_deduction' => 2291.67,
            'term_months' => 24,
            'total_amount' => 55000,
            'remaining_balance' => 55000,
            'total_paid' => 0,
            'start_date' => '2026-01-01',
            'status' => LoanStatus::Active,
            'created_by' => $hrManager->id,
        ];

        $loan = EmployeeLoan::create($loanData);

        expect($loan->loan_code)->toBe('SSS-TEST-001');
        expect($loan->status)->toBe(LoanStatus::Active);

        $this->assertDatabaseHas('employee_loans', [
            'loan_code' => 'SSS-TEST-001',
            'employee_id' => $employee->id,
        ]);
    });

    it('validates required fields', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLoan($tenant);

        $hrManager = createTenantUserForLoan($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $validator = Validator::make([], (new StoreEmployeeLoanRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('employee_id'))->toBeTrue();
        expect($validator->errors()->has('loan_type'))->toBeTrue();
        expect($validator->errors()->has('loan_code'))->toBeTrue();
        expect($validator->errors()->has('principal_amount'))->toBeTrue();
        expect($validator->errors()->has('monthly_deduction'))->toBeTrue();
        expect($validator->errors()->has('total_amount'))->toBeTrue();
        expect($validator->errors()->has('start_date'))->toBeTrue();
    });
});

describe('Loan API Show', function () {
    it('returns loan details', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLoan($tenant);

        $hrManager = createTenantUserForLoan($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $loan = EmployeeLoan::factory()->sssSalary()->create();

        $controller = new EmployeeLoanController;
        $response = $controller->show($loan);

        $data = $response->resolve();
        expect($data['id'])->toBe($loan->id);
        expect($data['loan_type'])->toBe('sss_salary');
    });
});

describe('Loan API Update', function () {
    it('updates loan details', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLoan($tenant);

        $hrManager = createTenantUserForLoan($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $loan = EmployeeLoan::factory()->active()->create([
            'monthly_deduction' => 2000,
        ]);

        $data = [
            'monthly_deduction' => 2500,
            'notes' => 'Updated deduction amount',
        ];

        $controller = new EmployeeLoanController;
        $request = createUpdateLoanRequest($data, $hrManager);
        $response = $controller->update($request, $loan);

        $responseData = $response->resolve();
        expect($responseData['monthly_deduction'])->toBe(2500.0);

        $loan->refresh();
        expect($loan->monthly_deduction)->toBe('2500.00');
    });

    it('rejects updates to completed loans', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLoan($tenant);

        $hrManager = createTenantUserForLoan($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $loan = EmployeeLoan::factory()->completed()->create();

        $data = [
            'monthly_deduction' => 2500,
        ];

        $controller = new EmployeeLoanController;
        $request = createUpdateLoanRequest($data, $hrManager);

        expect(fn () => $controller->update($request, $loan))
            ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
    });
});

describe('Loan API Status Update', function () {
    it('updates loan status', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLoan($tenant);

        $hrManager = createTenantUserForLoan($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $loan = EmployeeLoan::factory()->active()->create();

        $data = [
            'status' => 'on_hold',
            'notes' => 'Temporary hold',
        ];

        $controller = new EmployeeLoanController;
        $request = createUpdateStatusRequest($data, $hrManager);
        $response = $controller->updateStatus($request, $loan);

        $responseData = $response->resolve();
        expect($responseData['status'])->toBe('on_hold');

        $loan->refresh();
        expect($loan->status)->toBe(LoanStatus::OnHold);
    });

    it('rejects invalid status transitions', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLoan($tenant);

        $hrManager = createTenantUserForLoan($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $loan = EmployeeLoan::factory()->completed()->create();

        // Completed loans cannot transition back to active
        $currentStatus = $loan->status;
        expect($currentStatus->canTransitionTo(LoanStatus::Active))->toBeFalse();
    });
});

describe('Loan API Record Payment', function () {
    it('records a manual payment', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLoan($tenant);

        $hrManager = createTenantUserForLoan($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $loan = EmployeeLoan::factory()->active()->create([
            'remaining_balance' => 10000,
            'total_paid' => 0,
        ]);

        $data = [
            'amount' => 2000,
            'payment_date' => '2026-01-15',
            'payment_source' => 'manual',
            'notes' => 'Early payment',
        ];

        $controller = new EmployeeLoanController;
        $request = createRecordPaymentRequest($data, $hrManager);
        $response = $controller->recordPayment($request, $loan);

        $responseData = $response->getData(true);
        expect($responseData['message'])->toBe('Payment recorded successfully.');
        expect((float) $responseData['payment']['amount'])->toBe(2000.0);

        $loan->refresh();
        expect($loan->remaining_balance)->toBe('8000.00');
        expect($loan->total_paid)->toBe('2000.00');
    });

    it('rejects payment exceeding balance', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLoan($tenant);

        $hrManager = createTenantUserForLoan($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $loan = EmployeeLoan::factory()->active()->create([
            'remaining_balance' => 5000,
        ]);

        // Validate that the request rejects amounts exceeding balance
        $data = [
            'amount' => 10000,
            'payment_date' => '2026-01-15',
        ];

        $rules = (new RecordLoanPaymentRequest)->setLoan($loan)->rules();
        $validator = Validator::make($data, $rules);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('amount'))->toBeTrue();
    });

    it('rejects payment on completed loan', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLoan($tenant);

        $hrManager = createTenantUserForLoan($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $loan = EmployeeLoan::factory()->completed()->create();

        $data = [
            'amount' => 1000,
            'payment_date' => '2026-01-15',
        ];

        $controller = new EmployeeLoanController;
        $request = createRecordPaymentRequest($data, $hrManager);
        $response = $controller->recordPayment($request, $loan);

        expect($response->getStatusCode())->toBe(422);
        expect($response->getData(true)['message'])->toContain('completed');
    });
});

describe('Loan API Delete', function () {
    it('deletes a loan without payments', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLoan($tenant);

        $hrManager = createTenantUserForLoan($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $loan = EmployeeLoan::factory()->create();

        $controller = new EmployeeLoanController;
        $controller->destroy($loan);

        $this->assertSoftDeleted('employee_loans', ['id' => $loan->id]);
    });

    it('rejects deletion of loan with payments', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLoan($tenant);

        $hrManager = createTenantUserForLoan($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $loan = EmployeeLoan::factory()->create();
        $loan->payments()->create([
            'amount' => 1000,
            'balance_before' => 10000,
            'balance_after' => 9000,
            'payment_date' => now(),
            'payment_source' => 'manual',
        ]);

        $controller = new EmployeeLoanController;
        $response = $controller->destroy($loan);

        expect($response->getStatusCode())->toBe(422);
        expect($response->getData(true)['message'])->toContain('payments');

        $this->assertNotSoftDeleted('employee_loans', ['id' => $loan->id]);
    });
});

describe('Employee Loans Endpoint', function () {
    it('returns loans for specific employee', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLoan($tenant);

        $hrManager = createTenantUserForLoan($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();

        EmployeeLoan::factory()->forEmployee($employee1)->count(2)->create();
        EmployeeLoan::factory()->forEmployee($employee2)->create();

        $controller = new EmployeeLoanController;
        $request = Request::create('/api/employees/1/loans', 'GET');
        $response = $controller->employeeLoans($request, $employee1);

        expect($response->count())->toBe(2);
    });
});
