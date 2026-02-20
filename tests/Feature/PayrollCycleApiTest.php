<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\PayrollCycleController;
use App\Http\Requests\StorePayrollCycleRequest;
use App\Http\Requests\UpdatePayrollCycleRequest;
use App\Models\PayrollCycle;
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
function bindTenantContextForPayrollCycle(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForPayrollCycle(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated store payroll cycle request.
 */
function createStorePayrollCycleRequest(array $data, User $user): StorePayrollCycleRequest
{
    $request = StorePayrollCycleRequest::create('/api/organization/payroll-cycles', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new StorePayrollCycleRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated update payroll cycle request.
 */
function createUpdatePayrollCycleRequest(array $data, User $user): UpdatePayrollCycleRequest
{
    $request = UpdatePayrollCycleRequest::create('/api/organization/payroll-cycles/1', 'PUT', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new UpdatePayrollCycleRequest)->rules());
    $validator->validate();

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

describe('PayrollCycle API', function () {
    it('returns payroll cycle list on index', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayrollCycle($tenant);

        $hrManager = createTenantUserForPayrollCycle($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        PayrollCycle::factory()->semiMonthly()->create(['name' => 'Semi-Monthly Payroll']);
        PayrollCycle::factory()->monthly()->create(['name' => 'Monthly Payroll']);
        PayrollCycle::factory()->supplemental()->create(['name' => 'Supplemental Payroll']);

        $controller = new PayrollCycleController;
        $request = Request::create('/api/organization/payroll-cycles', 'GET');
        $response = $controller->index($request);

        expect($response->count())->toBe(3);
    });

    it('filters cycles by status on index', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayrollCycle($tenant);

        $hrManager = createTenantUserForPayrollCycle($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        PayrollCycle::factory()->semiMonthly()->active()->create();
        PayrollCycle::factory()->monthly()->active()->create();
        PayrollCycle::factory()->supplemental()->inactive()->create();

        $controller = new PayrollCycleController;

        // Filter by active status
        $activeRequest = Request::create('/api/organization/payroll-cycles', 'GET', ['status' => 'active']);
        $activeResponse = $controller->index($activeRequest);
        expect($activeResponse->count())->toBe(2);

        // Filter by inactive status
        $inactiveRequest = Request::create('/api/organization/payroll-cycles', 'GET', ['status' => 'inactive']);
        $inactiveResponse = $controller->index($inactiveRequest);
        expect($inactiveResponse->count())->toBe(1);
    });

    it('creates payroll cycle with validation', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayrollCycle($tenant);

        $hrManager = createTenantUserForPayrollCycle($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $controller = new PayrollCycleController;

        $cycleData = [
            'name' => 'Semi-Monthly Payroll',
            'code' => 'SEMI-MONTHLY',
            'cycle_type' => 'semi_monthly',
            'description' => 'Regular semi-monthly payroll cycle',
            'status' => 'active',
            'is_default' => true,
        ];

        $storeRequest = createStorePayrollCycleRequest($cycleData, $hrManager);
        $response = $controller->store($storeRequest);

        expect($response->getStatusCode())->toBe(201);

        $data = json_decode($response->getContent(), true);
        expect($data['name'])->toBe('Semi-Monthly Payroll');
        expect($data['code'])->toBe('SEMI-MONTHLY');
        expect($data['cycle_type'])->toBe('semi_monthly');
        expect($data['cycle_type_label'])->toBe('Semi-Monthly');
        expect($data['is_default'])->toBeTrue();
        expect($data['is_recurring'])->toBeTrue();
        expect($data['periods_per_year'])->toBe(24);

        // Verify the cycle was created in the database
        $createdCycle = PayrollCycle::where('code', 'SEMI-MONTHLY')->first();
        expect($createdCycle)->not->toBeNull();
        expect($createdCycle->cutoff_rules)->not->toBeNull(); // Default rules should be set
    });

    it('sets default cutoff rules when creating cycle', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayrollCycle($tenant);

        $hrManager = createTenantUserForPayrollCycle($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $controller = new PayrollCycleController;

        $cycleData = [
            'name' => 'Semi-Monthly Payroll',
            'code' => 'SEMI-MONTHLY-2',
            'cycle_type' => 'semi_monthly',
            'status' => 'active',
            'is_default' => false,
        ];

        $storeRequest = createStorePayrollCycleRequest($cycleData, $hrManager);
        $controller->store($storeRequest);

        $createdCycle = PayrollCycle::where('code', 'SEMI-MONTHLY-2')->first();
        expect($createdCycle->cutoff_rules)->toHaveKey('first_half');
        expect($createdCycle->cutoff_rules)->toHaveKey('second_half');
    });

    it('updates payroll cycle', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayrollCycle($tenant);

        $hrManager = createTenantUserForPayrollCycle($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $controller = new PayrollCycleController;

        $cycle = PayrollCycle::factory()->semiMonthly()->create([
            'name' => 'Original Name',
            'code' => 'ORIGINAL-CODE',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'description' => 'New description',
        ];

        $updateRequest = createUpdatePayrollCycleRequest($updateData, $hrManager);
        $response = $controller->update($updateRequest, $cycle);

        $data = $response->toArray(request());
        expect($data['name'])->toBe('Updated Name');
        expect($data['description'])->toBe('New description');

        $this->assertDatabaseHas('payroll_cycles', [
            'id' => $cycle->id,
            'name' => 'Updated Name',
        ]);
    });

    it('clears other default cycles when setting a new default', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayrollCycle($tenant);

        $hrManager = createTenantUserForPayrollCycle($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $controller = new PayrollCycleController;

        // Create an existing default cycle
        $existingDefault = PayrollCycle::factory()->semiMonthly()->default()->create();

        // Create a new cycle and set it as default
        $cycleData = [
            'name' => 'New Default Cycle',
            'code' => 'NEW-DEFAULT',
            'cycle_type' => 'monthly',
            'status' => 'active',
            'is_default' => true,
        ];

        $storeRequest = createStorePayrollCycleRequest($cycleData, $hrManager);
        $controller->store($storeRequest);

        // Verify the old default was cleared
        $existingDefault->refresh();
        expect($existingDefault->is_default)->toBeFalse();

        // Verify the new cycle is default
        $newCycle = PayrollCycle::where('code', 'NEW-DEFAULT')->first();
        expect($newCycle->is_default)->toBeTrue();
    });

    it('soft deletes payroll cycle', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayrollCycle($tenant);

        $hrManager = createTenantUserForPayrollCycle($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $controller = new PayrollCycleController;

        $cycle = PayrollCycle::factory()->semiMonthly()->create([
            'name' => 'Cycle to Delete',
        ]);

        $response = $controller->destroy($cycle);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['message'])->toBe('Payroll cycle deleted successfully.');

        // Verify soft delete
        $this->assertSoftDeleted('payroll_cycles', [
            'id' => $cycle->id,
        ]);

        expect(PayrollCycle::find($cycle->id))->toBeNull();
        expect(PayrollCycle::withTrashed()->find($cycle->id))->not->toBeNull();
    });

    it('prevents unauthorized user from creating payroll cycle', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayrollCycle($tenant);

        $employee = createTenantUserForPayrollCycle($tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        $controller = new PayrollCycleController;

        $cycleData = [
            'name' => 'Unauthorized Cycle',
            'code' => 'UNAUTHORIZED',
            'cycle_type' => 'semi_monthly',
            'status' => 'active',
            'is_default' => false,
        ];

        $storeRequest = createStorePayrollCycleRequest($cycleData, $employee);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $controller->store($storeRequest);
    });

    it('validates required fields when creating payroll cycle', function () {
        $rules = (new StorePayrollCycleRequest)->rules();

        // Test missing required fields
        $validator = Validator::make([], $rules);
        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('name'))->toBeTrue();
        expect($validator->errors()->has('code'))->toBeTrue();
        expect($validator->errors()->has('cycle_type'))->toBeTrue();

        // Test invalid cycle type
        $invalidTypeValidator = Validator::make([
            'name' => 'Test Cycle',
            'code' => 'TEST-CYCLE',
            'cycle_type' => 'invalid_type',
        ], $rules);

        expect($invalidTypeValidator->fails())->toBeTrue();
        expect($invalidTypeValidator->errors()->has('cycle_type'))->toBeTrue();

        // Test valid data passes
        $validValidator = Validator::make([
            'name' => 'Valid Cycle',
            'code' => 'VALID-CYCLE',
            'cycle_type' => 'semi_monthly',
        ], $rules);

        expect($validValidator->fails())->toBeFalse();
    });

    it('validates unique code constraint', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayrollCycle($tenant);

        PayrollCycle::factory()->create(['code' => 'EXISTING-CODE']);

        $rules = (new StorePayrollCycleRequest)->rules();

        $validator = Validator::make([
            'name' => 'Duplicate Code Cycle',
            'code' => 'EXISTING-CODE',
            'cycle_type' => 'semi_monthly',
        ], $rules);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('code'))->toBeTrue();
    });

    it('returns cycle details with show', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayrollCycle($tenant);

        $hrManager = createTenantUserForPayrollCycle($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $cycle = PayrollCycle::factory()->semiMonthly()->default()->create([
            'name' => 'Test Semi-Monthly',
            'code' => 'TEST-SEMI',
        ]);

        $controller = new PayrollCycleController;
        $response = $controller->show($cycle);

        $data = $response->toArray(request());
        expect($data['id'])->toBe($cycle->id);
        expect($data['name'])->toBe('Test Semi-Monthly');
        expect($data['code'])->toBe('TEST-SEMI');
        expect($data['cycle_type'])->toBe('semi_monthly');
        expect($data['cycle_type_label'])->toBe('Semi-Monthly');
        expect($data['is_default'])->toBeTrue();
        expect($data['is_recurring'])->toBeTrue();
        expect($data['periods_per_year'])->toBe(24);
    });
});
