<?php

/**
 * Integration Tests for Compensation Records Feature
 *
 * These tests fill critical gaps in the existing test coverage:
 * - End-to-end workflows (create employee -> add compensation -> update -> verify history)
 * - Multiple sequential updates creating proper history chains
 * - Edge cases for bank account fields via API
 * - Validation edge cases
 * - HR Manager authorization
 */

use App\Enums\PayType;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\EmployeeCompensationController;
use App\Http\Requests\StoreEmployeeCompensationRequest;
use App\Models\CompensationHistory;
use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createIntegrationTestUser(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to bind tenant to the application container for tests.
 */
function bindIntegrationTestTenantContext(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a validated StoreEmployeeCompensationRequest.
 */
function createIntegrationValidatedRequest(array $data): StoreEmployeeCompensationRequest
{
    $request = StoreEmployeeCompensationRequest::create(
        '/api/employees/1/compensation',
        'POST',
        $data
    );
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    $rules = (new StoreEmployeeCompensationRequest)->rules();
    $validator = Validator::make($data, $rules);

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

describe('End-to-End Workflows', function () {
    it('creates new employee and adds initial compensation successfully', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindIntegrationTestTenantContext($tenant);

        $admin = createIntegrationTestUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Step 1: Create new employee
        $employee = Employee::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        // Verify no compensation exists initially
        expect($employee->compensation)->toBeNull();
        expect($employee->compensationHistory)->toBeEmpty();

        // Step 2: Add initial compensation
        $requestData = [
            'basic_pay' => 45000.00,
            'pay_type' => 'monthly',
            'effective_date' => now()->format('Y-m-d'),
            'remarks' => 'Initial hire compensation',
        ];

        $request = createIntegrationValidatedRequest($requestData);
        $controller = new EmployeeCompensationController;
        $response = $controller->store($request, 'test-tenant', $employee);

        expect($response->getStatusCode())->toBe(201);

        // Step 3: Verify compensation was created
        $employee->refresh();
        expect($employee->compensation)->not->toBeNull();
        expect($employee->compensation->basic_pay)->toBe('45000.00');
        expect($employee->compensation->pay_type)->toBe(PayType::Monthly);

        // Step 4: Verify initial history record was created
        $history = CompensationHistory::where('employee_id', $employee->id)->get();
        expect($history)->toHaveCount(1);
        expect($history->first()->previous_basic_pay)->toBeNull();
        expect($history->first()->new_basic_pay)->toBe('45000.00');
        expect($history->first()->ended_at)->toBeNull();
        expect($history->first()->remarks)->toBe('Initial hire compensation');
    });

    it('updates compensation multiple times and creates proper history chain', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindIntegrationTestTenantContext($tenant);

        $admin = createIntegrationTestUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $controller = new EmployeeCompensationController;

        // First compensation (initial setup)
        $request1 = createIntegrationValidatedRequest([
            'basic_pay' => 40000.00,
            'pay_type' => 'monthly',
            'effective_date' => now()->subMonths(6)->format('Y-m-d'),
            'remarks' => 'Initial hire',
        ]);
        $controller->store($request1, 'test-tenant', $employee);

        // Refresh the employee to pick up the newly created compensation relationship
        $employee = Employee::find($employee->id);

        // Second update (raise)
        $request2 = createIntegrationValidatedRequest([
            'basic_pay' => 50000.00,
            'pay_type' => 'monthly',
            'effective_date' => now()->subMonths(3)->format('Y-m-d'),
            'remarks' => 'Performance raise',
        ]);
        $controller->store($request2, 'test-tenant', $employee);

        // Refresh again to pick up the updated compensation
        $employee = Employee::find($employee->id);

        // Third update (pay type change)
        $request3 = createIntegrationValidatedRequest([
            'basic_pay' => 55000.00,
            'pay_type' => 'semi_monthly',
            'effective_date' => now()->format('Y-m-d'),
            'remarks' => 'Promotion and pay type adjustment',
        ]);
        $controller->store($request3, 'test-tenant', $employee);

        // Verify current compensation reflects latest update
        $employee->refresh();
        expect($employee->compensation->basic_pay)->toBe('55000.00');
        expect($employee->compensation->pay_type)->toBe(PayType::SemiMonthly);

        // Verify history chain is correctly structured
        $history = CompensationHistory::where('employee_id', $employee->id)
            ->orderBy('created_at', 'asc')
            ->get();

        expect($history)->toHaveCount(3);

        // First record (should be ended)
        expect($history[0]->previous_basic_pay)->toBeNull();
        expect($history[0]->new_basic_pay)->toBe('40000.00');
        expect($history[0]->ended_at)->not->toBeNull();

        // Second record (should be ended)
        expect($history[1]->previous_basic_pay)->toBe('40000.00');
        expect($history[1]->new_basic_pay)->toBe('50000.00');
        expect($history[1]->ended_at)->not->toBeNull();

        // Third record (current - not ended)
        expect($history[2]->previous_basic_pay)->toBe('50000.00');
        expect($history[2]->new_basic_pay)->toBe('55000.00');
        expect($history[2]->previous_pay_type)->toBe(PayType::Monthly);
        expect($history[2]->new_pay_type)->toBe(PayType::SemiMonthly);
        expect($history[2]->ended_at)->toBeNull();

        // Verify only one current record exists at any time
        $currentRecords = CompensationHistory::where('employee_id', $employee->id)
            ->whereNull('ended_at')
            ->get();
        expect($currentRecords)->toHaveCount(1);
    });
});

describe('Bank Account Edge Cases', function () {
    it('creates compensation with all bank account fields null via API', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindIntegrationTestTenantContext($tenant);

        $admin = createIntegrationTestUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();

        $requestData = [
            'basic_pay' => 35000.00,
            'pay_type' => 'weekly',
            'effective_date' => now()->format('Y-m-d'),
            // All bank fields omitted (null)
        ];

        $request = createIntegrationValidatedRequest($requestData);
        $controller = new EmployeeCompensationController;
        $response = $controller->store($request, 'test-tenant', $employee);

        expect($response->getStatusCode())->toBe(201);

        $employee->refresh();
        expect($employee->compensation->bank_name)->toBeNull();
        expect($employee->compensation->account_name)->toBeNull();
        expect($employee->compensation->account_number)->toBeNull();
        expect($employee->compensation->account_type)->toBeNull();
    });

    it('clears bank account fields when updating compensation', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindIntegrationTestTenantContext($tenant);

        $admin = createIntegrationTestUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();

        // Create initial compensation with bank account
        EmployeeCompensation::factory()
            ->withBankAccount()
            ->create([
                'employee_id' => $employee->id,
                'basic_pay' => 50000.00,
                'pay_type' => PayType::Monthly,
                'bank_name' => 'BDO',
                'account_number' => '1234567890',
            ]);

        CompensationHistory::factory()->current()->create([
            'employee_id' => $employee->id,
            'new_basic_pay' => 50000.00,
            'new_pay_type' => PayType::Monthly,
        ]);

        // Re-fetch employee to pick up compensation relationship
        $employee = Employee::find($employee->id);

        // Update compensation without bank account fields (should clear them)
        $requestData = [
            'basic_pay' => 55000.00,
            'pay_type' => 'monthly',
            'effective_date' => now()->format('Y-m-d'),
            'bank_name' => null,
            'account_name' => null,
            'account_number' => null,
            'account_type' => null,
        ];

        $request = createIntegrationValidatedRequest($requestData);
        $controller = new EmployeeCompensationController;
        $response = $controller->store($request, 'test-tenant', $employee);

        expect($response->getStatusCode())->toBe(201);

        $employee->refresh();
        expect($employee->compensation->basic_pay)->toBe('55000.00');
        expect($employee->compensation->bank_name)->toBeNull();
        expect($employee->compensation->account_number)->toBeNull();
    });
});

describe('Validation Edge Cases', function () {
    it('rejects negative basic pay values', function () {
        $request = new StoreEmployeeCompensationRequest;
        $validator = Validator::make(
            [
                'basic_pay' => -1000.00,
                'pay_type' => 'monthly',
                'effective_date' => now()->format('Y-m-d'),
            ],
            $request->rules(),
            $request->messages()
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('basic_pay'))->toBeTrue();
        expect($validator->errors()->first('basic_pay'))->toContain('cannot be negative');
    });

    it('accepts zero as valid basic pay', function () {
        $request = new StoreEmployeeCompensationRequest;
        $validator = Validator::make(
            [
                'basic_pay' => 0,
                'pay_type' => 'monthly',
                'effective_date' => now()->format('Y-m-d'),
            ],
            $request->rules(),
            $request->messages()
        );

        expect($validator->fails())->toBeFalse();
    });

    it('validates bank field max lengths', function () {
        $request = new StoreEmployeeCompensationRequest;
        $validator = Validator::make(
            [
                'basic_pay' => 50000.00,
                'pay_type' => 'monthly',
                'effective_date' => now()->format('Y-m-d'),
                'bank_name' => str_repeat('A', 101), // Exceeds 100 char limit
                'account_number' => str_repeat('1', 51), // Exceeds 50 char limit
            ],
            $request->rules(),
            $request->messages()
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('bank_name'))->toBeTrue();
        expect($validator->errors()->has('account_number'))->toBeTrue();
    });
});

describe('Authorization Edge Cases', function () {
    it('allows hr_manager to access compensation endpoints', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindIntegrationTestTenantContext($tenant);

        $hrManager = createIntegrationTestUser($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $employee = Employee::factory()->create();

        expect(Gate::allows('can-manage-employees'))->toBeTrue();

        $controller = new EmployeeCompensationController;
        $response = $controller->index($employee);

        expect($response->getStatusCode())->toBe(200);
    });
});

describe('Currency Default', function () {
    it('defaults currency to PHP when creating compensation', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindIntegrationTestTenantContext($tenant);

        $admin = createIntegrationTestUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();

        $requestData = [
            'basic_pay' => 50000.00,
            'pay_type' => 'monthly',
            'effective_date' => now()->format('Y-m-d'),
        ];

        $request = createIntegrationValidatedRequest($requestData);
        $controller = new EmployeeCompensationController;
        $controller->store($request, 'test-tenant', $employee);

        $employee->refresh();
        expect($employee->compensation->currency)->toBe('PHP');
    });
});
