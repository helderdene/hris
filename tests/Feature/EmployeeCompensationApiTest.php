<?php

/**
 * API Tests for Employee Compensation Management
 *
 * Tests the GET and POST endpoints for managing employee compensation,
 * including authorization, validation, and history tracking.
 *
 * Note: These tests call controllers directly following the pattern from OrgChartTest.php
 * since tenant subdomain routing requires special handling in tests.
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
function createCompensationTenantUser(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
function bindCompensationTenantContext(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a validated StoreEmployeeCompensationRequest.
 */
function createValidatedCompensationRequest(array $data): StoreEmployeeCompensationRequest
{
    $request = StoreEmployeeCompensationRequest::create(
        '/api/employees/1/compensation',
        'POST',
        $data
    );
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    // Get the rules and validate
    $rules = (new StoreEmployeeCompensationRequest)->rules();
    $validator = Validator::make($data, $rules);

    // Set the validator on the request (via reflection since it's protected)
    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

beforeEach(function () {
    // Set up the main domain for the tests
    config(['app.main_domain' => 'kasamahr.test']);

    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('GET /api/employees/{employee}/compensation', function () {
    it('returns current compensation and history for an employee', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCompensationTenantContext($tenant);

        $admin = createCompensationTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $compensation = EmployeeCompensation::factory()
            ->withBankAccount()
            ->create([
                'employee_id' => $employee->id,
                'basic_pay' => 50000.00,
                'pay_type' => PayType::Monthly,
            ]);

        // Create some history records
        CompensationHistory::factory()->initial()->create([
            'employee_id' => $employee->id,
            'new_basic_pay' => 40000.00,
            'new_pay_type' => PayType::Monthly,
            'effective_date' => now()->subYear(),
            'ended_at' => now()->subMonths(6),
        ]);

        CompensationHistory::factory()->current()->create([
            'employee_id' => $employee->id,
            'previous_basic_pay' => 40000.00,
            'new_basic_pay' => 50000.00,
            'previous_pay_type' => PayType::Monthly,
            'new_pay_type' => PayType::Monthly,
            'effective_date' => now()->subMonths(6),
        ]);

        $controller = new EmployeeCompensationController;
        $response = $controller->index($employee);

        $data = $response->getData(true);

        expect($data['data']['compensation'])->not->toBeNull();
        expect($data['data']['compensation']['basic_pay'])->toBe('50000.00');
        expect($data['data']['history'])->toHaveCount(2);
    });

    it('returns null compensation when employee has no compensation record', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCompensationTenantContext($tenant);

        $admin = createCompensationTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();

        $controller = new EmployeeCompensationController;
        $response = $controller->index($employee);

        $data = $response->getData(true);

        expect($data['data']['compensation'])->toBeNull();
        expect($data['data']['history'])->toBeEmpty();
    });
});

describe('POST /api/employees/{employee}/compensation', function () {
    it('creates a new compensation record for an employee', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCompensationTenantContext($tenant);

        $admin = createCompensationTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();

        // Create a validated request
        $requestData = [
            'basic_pay' => 55000.00,
            'pay_type' => 'monthly',
            'effective_date' => now()->format('Y-m-d'),
            'bank_name' => 'BDO',
            'account_name' => 'John Doe',
            'account_number' => '1234567890',
            'account_type' => 'savings',
            'remarks' => 'Initial compensation setup',
        ];

        $request = createValidatedCompensationRequest($requestData);

        $controller = new EmployeeCompensationController;
        $response = $controller->store($request, $employee);

        expect($response->getStatusCode())->toBe(201);

        $data = $response->getData(true);
        expect($data['data']['compensation']['basic_pay'])->toBe('55000.00');
        expect($data['data']['compensation']['pay_type'])->toBe('monthly');

        // Verify compensation was created in database
        expect(EmployeeCompensation::where('employee_id', $employee->id)->exists())->toBeTrue();

        // Verify history was created
        $history = CompensationHistory::where('employee_id', $employee->id)->first();
        expect($history)->not->toBeNull();
        expect($history->previous_basic_pay)->toBeNull();
        expect($history->new_basic_pay)->toBe('55000.00');
        expect($history->ended_at)->toBeNull();
    });

    it('updates existing compensation and creates history record', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCompensationTenantContext($tenant);

        $admin = createCompensationTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();

        // Create existing compensation
        $compensation = EmployeeCompensation::factory()->create([
            'employee_id' => $employee->id,
            'basic_pay' => 50000.00,
            'pay_type' => PayType::Monthly,
        ]);

        // Create current history record
        $currentHistory = CompensationHistory::factory()->current()->create([
            'employee_id' => $employee->id,
            'new_basic_pay' => 50000.00,
            'new_pay_type' => PayType::Monthly,
            'effective_date' => now()->subMonths(6),
        ]);

        // Create a validated request
        $requestData = [
            'basic_pay' => 60000.00,
            'pay_type' => 'semi_monthly',
            'effective_date' => now()->format('Y-m-d'),
            'remarks' => 'Salary increase',
        ];

        $request = createValidatedCompensationRequest($requestData);

        $controller = new EmployeeCompensationController;
        $response = $controller->store($request, $employee);

        expect($response->getStatusCode())->toBe(201);

        // Verify compensation was updated
        $compensation->refresh();
        expect($compensation->basic_pay)->toBe('60000.00');
        expect($compensation->pay_type)->toBe(PayType::SemiMonthly);

        // Verify old history was ended
        $currentHistory->refresh();
        expect($currentHistory->ended_at)->not->toBeNull();

        // Verify new history was created
        $newHistory = CompensationHistory::where('employee_id', $employee->id)
            ->whereNull('ended_at')
            ->first();

        expect($newHistory)->not->toBeNull();
        expect($newHistory->previous_basic_pay)->toBe('50000.00');
        expect($newHistory->new_basic_pay)->toBe('60000.00');
        expect($newHistory->previous_pay_type)->toBe(PayType::Monthly);
        expect($newHistory->new_pay_type)->toBe(PayType::SemiMonthly);
    });
});

describe('Authorization', function () {
    it('denies access to unauthorized users', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCompensationTenantContext($tenant);

        // Create user with employee role (not admin or hr_manager)
        $employee_user = createCompensationTenantUser($tenant, TenantUserRole::Employee);
        $this->actingAs($employee_user);

        $employee = Employee::factory()->create();

        // Test that Gate denies access
        expect(Gate::allows('can-manage-employees'))->toBeFalse();
    });

    it('allows admin to access compensation endpoints', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCompensationTenantContext($tenant);

        $admin = createCompensationTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();

        // Test that Gate allows access for admin
        expect(Gate::allows('can-manage-employees'))->toBeTrue();

        $controller = new EmployeeCompensationController;
        $response = $controller->index($employee);

        expect($response->getStatusCode())->toBe(200);
    });

    it('allows super admin to access compensation endpoints', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCompensationTenantContext($tenant);

        $superAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($superAdmin);

        $employee = Employee::factory()->create();

        // Test that Gate allows access for super admin
        expect(Gate::allows('can-manage-employees'))->toBeTrue();

        $controller = new EmployeeCompensationController;
        $response = $controller->index($employee);

        expect($response->getStatusCode())->toBe(200);
    });
});

describe('Validation', function () {
    it('validates required fields when creating compensation', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCompensationTenantContext($tenant);

        $admin = createCompensationTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Use the request's rules method to validate empty data
        $request = new StoreEmployeeCompensationRequest;
        $validator = Validator::make([], $request->rules(), $request->messages());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('basic_pay'))->toBeTrue();
        expect($validator->errors()->has('pay_type'))->toBeTrue();
        expect($validator->errors()->has('effective_date'))->toBeTrue();
    });

    it('validates pay_type is a valid enum value', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCompensationTenantContext($tenant);

        $admin = createCompensationTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $request = new StoreEmployeeCompensationRequest;
        $validator = Validator::make(
            [
                'basic_pay' => 50000.00,
                'pay_type' => 'invalid_pay_type',
                'effective_date' => now()->format('Y-m-d'),
            ],
            $request->rules(),
            $request->messages()
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('pay_type'))->toBeTrue();
    });

    it('validates account_type is a valid enum value when provided', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCompensationTenantContext($tenant);

        $admin = createCompensationTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $request = new StoreEmployeeCompensationRequest;
        $validator = Validator::make(
            [
                'basic_pay' => 50000.00,
                'pay_type' => 'monthly',
                'effective_date' => now()->format('Y-m-d'),
                'account_type' => 'invalid_account_type',
            ],
            $request->rules(),
            $request->messages()
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('account_type'))->toBeTrue();
    });
});
