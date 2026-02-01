<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\PerformanceCycleController;
use App\Http\Requests\StorePerformanceCycleRequest;
use App\Http\Requests\UpdatePerformanceCycleRequest;
use App\Models\PerformanceCycle;
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
function bindTenantContextForPerformanceCycle(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForPerformanceCycle(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated store performance cycle request.
 */
function createStorePerformanceCycleRequest(array $data, User $user): StorePerformanceCycleRequest
{
    $request = StorePerformanceCycleRequest::create('/api/organization/performance-cycles', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new StorePerformanceCycleRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated update performance cycle request.
 */
function createUpdatePerformanceCycleRequest(array $data, User $user, int $cycleId): UpdatePerformanceCycleRequest
{
    $request = UpdatePerformanceCycleRequest::create("/api/organization/performance-cycles/{$cycleId}", 'PUT', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());
    $request->setRouteResolver(fn () => new class($cycleId)
    {
        private int $id;

        public function __construct(int $id)
        {
            $this->id = $id;
        }

        public function parameter($name)
        {
            return $this->id;
        }
    });

    // Override the unique rule for testing
    $rules = (new UpdatePerformanceCycleRequest)->rules();
    $rules['code'] = ['required', 'string', 'max:50'];

    $validator = Validator::make($data, $rules);
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

describe('PerformanceCycle API', function () {
    it('returns filtered performance cycles list on index', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycle($tenant);

        $admin = createTenantUserForPerformanceCycle($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        PerformanceCycle::factory()->annual()->create([
            'name' => 'Annual Review',
            'code' => 'ANNUAL-001',
            'status' => 'active',
        ]);

        PerformanceCycle::factory()->midYear()->create([
            'name' => 'Mid-Year Review',
            'code' => 'MID-001',
            'status' => 'active',
        ]);

        PerformanceCycle::factory()->create([
            'name' => 'Inactive Cycle',
            'code' => 'INA-001',
            'status' => 'inactive',
        ]);

        $controller = new PerformanceCycleController;

        // Test without filters - returns all
        $request = Request::create('/api/organization/performance-cycles', 'GET');
        $response = $controller->index($request);
        expect($response->count())->toBe(3);

        // Test filter by status
        $activeRequest = Request::create('/api/organization/performance-cycles', 'GET', ['status' => 'active']);
        $activeResponse = $controller->index($activeRequest);
        expect($activeResponse->count())->toBe(2);

        // Test filter by cycle_type
        $annualRequest = Request::create('/api/organization/performance-cycles', 'GET', ['cycle_type' => 'annual']);
        $annualResponse = $controller->index($annualRequest);
        expect($annualResponse->count())->toBe(1);
        expect($annualResponse->first()->name)->toBe('Annual Review');
    });

    it('creates an annual performance cycle', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycle($tenant);

        $admin = createTenantUserForPerformanceCycle($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new PerformanceCycleController;

        $cycleData = [
            'name' => 'Annual Performance Review',
            'code' => 'APR-2026',
            'cycle_type' => 'annual',
            'description' => 'Annual employee performance evaluation',
            'status' => 'active',
            'is_default' => true,
        ];

        $storeRequest = createStorePerformanceCycleRequest($cycleData, $admin);
        $response = $controller->store($storeRequest);

        expect($response->getStatusCode())->toBe(201);

        $data = json_decode($response->getContent(), true);
        expect($data['name'])->toBe('Annual Performance Review');
        expect($data['code'])->toBe('APR-2026');
        expect($data['cycle_type'])->toBe('annual');
        expect($data['is_recurring'])->toBeTrue();
        expect($data['instances_per_year'])->toBe(1);

        $this->assertDatabaseHas('performance_cycles', [
            'name' => 'Annual Performance Review',
            'code' => 'APR-2026',
        ]);
    });

    it('creates a mid-year performance cycle', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycle($tenant);

        $admin = createTenantUserForPerformanceCycle($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new PerformanceCycleController;

        $cycleData = [
            'name' => 'Mid-Year Performance Review',
            'code' => 'MYR-2026',
            'cycle_type' => 'mid_year',
            'description' => 'Mid-year check-in evaluation',
            'status' => 'active',
            'is_default' => false,
        ];

        $storeRequest = createStorePerformanceCycleRequest($cycleData, $admin);
        $response = $controller->store($storeRequest);

        expect($response->getStatusCode())->toBe(201);

        $data = json_decode($response->getContent(), true);
        expect($data['cycle_type'])->toBe('mid_year');
        expect($data['is_recurring'])->toBeTrue();
        expect($data['instances_per_year'])->toBe(2);
    });

    it('creates a probationary performance cycle', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycle($tenant);

        $admin = createTenantUserForPerformanceCycle($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new PerformanceCycleController;

        $cycleData = [
            'name' => 'Probationary Review',
            'code' => 'PROB-001',
            'cycle_type' => 'probationary',
            'description' => 'Probation period evaluation',
            'status' => 'active',
            'is_default' => false,
        ];

        $storeRequest = createStorePerformanceCycleRequest($cycleData, $admin);
        $response = $controller->store($storeRequest);

        expect($response->getStatusCode())->toBe(201);

        $data = json_decode($response->getContent(), true);
        expect($data['cycle_type'])->toBe('probationary');
        expect($data['is_recurring'])->toBeFalse();
        expect($data['instances_per_year'])->toBeNull();
    });

    it('validates required fields', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycle($tenant);

        $admin = createTenantUserForPerformanceCycle($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $rules = (new StorePerformanceCycleRequest)->rules();
        $validator = Validator::make([], $rules);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('name'))->toBeTrue();
        expect($validator->errors()->has('code'))->toBeTrue();
        expect($validator->errors()->has('cycle_type'))->toBeTrue();
    });

    it('validates unique code constraint', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycle($tenant);

        $admin = createTenantUserForPerformanceCycle($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        PerformanceCycle::factory()->create(['code' => 'DUP-001']);

        $rules = (new StorePerformanceCycleRequest)->rules();
        $validator = Validator::make([
            'name' => 'Duplicate Code Cycle',
            'code' => 'DUP-001',
            'cycle_type' => 'annual',
            'status' => 'active',
        ], $rules);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('code'))->toBeTrue();
    });

    it('validates cycle_type enum', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycle($tenant);

        $admin = createTenantUserForPerformanceCycle($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $rules = (new StorePerformanceCycleRequest)->rules();
        $validator = Validator::make([
            'name' => 'Test Cycle',
            'code' => 'TEST-001',
            'cycle_type' => 'invalid_type',
            'status' => 'active',
        ], $rules);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('cycle_type'))->toBeTrue();
    });

    it('returns cycle with instance count on show', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycle($tenant);

        $admin = createTenantUserForPerformanceCycle($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $cycle = PerformanceCycle::factory()->annual()->create([
            'name' => 'Annual Review',
            'code' => 'ANN-001',
        ]);

        $controller = new PerformanceCycleController;
        $response = $controller->show($tenant->slug, $cycle);
        $data = $response->toArray(request());

        expect($data['name'])->toBe('Annual Review');
        expect($data['code'])->toBe('ANN-001');
        expect($data['cycle_type'])->toBe('annual');
        expect($data['cycle_type_label'])->toBe('Annual');
    });

    it('updates performance cycle configuration', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycle($tenant);

        $admin = createTenantUserForPerformanceCycle($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new PerformanceCycleController;

        $cycle = PerformanceCycle::factory()->create([
            'name' => 'Original Name',
            'code' => 'ORI-001',
            'status' => 'active',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'code' => 'ORI-001',
            'cycle_type' => 'annual',
            'status' => 'inactive',
            'description' => 'Updated description',
            'is_default' => true,
        ];

        $updateRequest = createUpdatePerformanceCycleRequest($updateData, $admin, $cycle->id);
        $response = $controller->update($updateRequest, $tenant->slug, $cycle);

        $data = $response->toArray(request());
        expect($data['name'])->toBe('Updated Name');
        expect($data['status'])->toBe('inactive');
        expect($data['description'])->toBe('Updated description');

        $this->assertDatabaseHas('performance_cycles', [
            'id' => $cycle->id,
            'name' => 'Updated Name',
            'status' => 'inactive',
        ]);
    });

    it('soft deletes performance cycle', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycle($tenant);

        $admin = createTenantUserForPerformanceCycle($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new PerformanceCycleController;

        $cycle = PerformanceCycle::factory()->create([
            'name' => 'To Be Deleted',
            'code' => 'DEL-001',
        ]);

        $response = $controller->destroy($tenant->slug, $cycle);

        expect($response->getStatusCode())->toBe(200);

        // Verify soft delete
        expect(PerformanceCycle::find($cycle->id))->toBeNull();
        expect(PerformanceCycle::withTrashed()->find($cycle->id))->not->toBeNull();
    });

    it('resets is_default on other cycles when setting a new default', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycle($tenant);

        $admin = createTenantUserForPerformanceCycle($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new PerformanceCycleController;

        // Create first cycle as default
        $firstCycle = PerformanceCycle::factory()->annual()->create([
            'name' => 'First Default',
            'code' => 'FIRST-001',
            'is_default' => true,
        ]);

        // Create second cycle as new default
        $cycleData = [
            'name' => 'New Default',
            'code' => 'NEW-001',
            'cycle_type' => 'annual',
            'status' => 'active',
            'is_default' => true,
        ];

        $storeRequest = createStorePerformanceCycleRequest($cycleData, $admin);
        $controller->store($storeRequest);

        // Refresh first cycle
        $firstCycle->refresh();

        expect($firstCycle->is_default)->toBeFalse();

        $this->assertDatabaseHas('performance_cycles', [
            'code' => 'NEW-001',
            'is_default' => true,
        ]);
    });
});
