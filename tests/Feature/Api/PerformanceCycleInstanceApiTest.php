<?php

use App\Enums\PerformanceCycleInstanceStatus;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\PerformanceCycleInstanceController;
use App\Http\Requests\GeneratePerformanceCycleInstancesRequest;
use App\Http\Requests\StorePerformanceCycleInstanceRequest;
use App\Http\Requests\UpdatePerformanceCycleInstanceRequest;
use App\Http\Requests\UpdatePerformanceCycleInstanceStatusRequest;
use App\Models\PerformanceCycle;
use App\Models\PerformanceCycleInstance;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PerformanceCycleInstanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForPerformanceCycleInstance(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForPerformanceCycleInstance(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated store instance request.
 */
function createStorePerformanceCycleInstanceRequest(array $data, User $user): StorePerformanceCycleInstanceRequest
{
    $request = StorePerformanceCycleInstanceRequest::create('/api/organization/performance-cycle-instances', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    // Skip validation for testing, use simplified rules
    $rules = [
        'performance_cycle_id' => ['required', 'integer'],
        'name' => ['required', 'string', 'max:255'],
        'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        'instance_number' => ['required', 'integer', 'min:1', 'max:12'],
        'start_date' => ['required', 'date'],
        'end_date' => ['required', 'date', 'after:start_date'],
        'notes' => ['nullable', 'string'],
    ];

    $validator = Validator::make($data, $rules);
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated update instance request.
 */
function createUpdatePerformanceCycleInstanceRequest(array $data, User $user, int $instanceId): UpdatePerformanceCycleInstanceRequest
{
    $request = UpdatePerformanceCycleInstanceRequest::create("/api/organization/performance-cycle-instances/{$instanceId}", 'PUT', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    // Skip validation for testing
    $rules = [
        'name' => ['required', 'string', 'max:255'],
        'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        'instance_number' => ['required', 'integer', 'min:1', 'max:12'],
        'start_date' => ['required', 'date'],
        'end_date' => ['required', 'date', 'after:start_date'],
        'notes' => ['nullable', 'string'],
    ];

    $validator = Validator::make($data, $rules);
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated generate instances request.
 */
function createGeneratePerformanceCycleInstancesRequest(array $data, User $user): GeneratePerformanceCycleInstancesRequest
{
    $request = GeneratePerformanceCycleInstancesRequest::create('/api/organization/performance-cycle-instances/generate', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $rules = [
        'performance_cycle_id' => ['required', 'integer'],
        'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        'overwrite' => ['boolean'],
    ];

    $validator = Validator::make($data, $rules);
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated update status request.
 */
function createUpdatePerformanceCycleInstanceStatusRequest(array $data, User $user): UpdatePerformanceCycleInstanceStatusRequest
{
    $request = UpdatePerformanceCycleInstanceStatusRequest::create('/api/organization/performance-cycle-instances/1/status', 'PATCH', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $rules = [
        'status' => ['required', 'string'],
    ];

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

describe('PerformanceCycleInstance API', function () {
    it('returns filtered instances list on index', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycleInstance($tenant);

        $admin = createTenantUserForPerformanceCycleInstance($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $cycle = PerformanceCycle::factory()->annual()->create();

        PerformanceCycleInstance::factory()->for($cycle, 'performanceCycle')->draft()->create([
            'name' => 'Annual Review 2026',
            'year' => 2026,
        ]);

        PerformanceCycleInstance::factory()->for($cycle, 'performanceCycle')->active()->create([
            'name' => 'Annual Review 2025',
            'year' => 2025,
        ]);

        PerformanceCycleInstance::factory()->for($cycle, 'performanceCycle')->closed()->create([
            'name' => 'Annual Review 2024',
            'year' => 2024,
        ]);

        $controller = new PerformanceCycleInstanceController(new PerformanceCycleInstanceService);

        // Test without filters - returns all
        $request = Request::create('/api/organization/performance-cycle-instances', 'GET');
        $response = $controller->index($request);
        expect($response->count())->toBe(3);

        // Test filter by year
        $yearRequest = Request::create('/api/organization/performance-cycle-instances', 'GET', ['year' => 2026]);
        $yearResponse = $controller->index($yearRequest);
        expect($yearResponse->count())->toBe(1);
        expect($yearResponse->first()->year)->toBe(2026);

        // Test filter by status
        $statusRequest = Request::create('/api/organization/performance-cycle-instances', 'GET', ['status' => 'active']);
        $statusResponse = $controller->index($statusRequest);
        expect($statusResponse->count())->toBe(1);
        expect($statusResponse->first()->name)->toBe('Annual Review 2025');
    });

    it('creates instance manually', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycleInstance($tenant);

        $admin = createTenantUserForPerformanceCycleInstance($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $cycle = PerformanceCycle::factory()->annual()->create();

        $controller = new PerformanceCycleInstanceController(new PerformanceCycleInstanceService);

        $instanceData = [
            'performance_cycle_id' => $cycle->id,
            'name' => 'Annual Performance Review 2026',
            'year' => 2026,
            'instance_number' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'notes' => 'Test notes',
        ];

        $storeRequest = createStorePerformanceCycleInstanceRequest($instanceData, $admin);
        $response = $controller->store($storeRequest);

        expect($response->getStatusCode())->toBe(201);

        $data = json_decode($response->getContent(), true);
        expect($data['name'])->toBe('Annual Performance Review 2026');
        expect($data['year'])->toBe(2026);
        expect($data['status'])->toBe('draft');

        $this->assertDatabaseHas('performance_cycle_instances', [
            'name' => 'Annual Performance Review 2026',
            'year' => 2026,
        ]);
    });

    it('generates one instance for annual cycle', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycleInstance($tenant);

        $admin = createTenantUserForPerformanceCycleInstance($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $cycle = PerformanceCycle::factory()->annual()->create();

        $controller = new PerformanceCycleInstanceController(new PerformanceCycleInstanceService);

        $generateData = [
            'performance_cycle_id' => $cycle->id,
            'year' => 2026,
            'overwrite' => false,
        ];

        $generateRequest = createGeneratePerformanceCycleInstancesRequest($generateData, $admin);
        $response = $controller->generate($generateRequest);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['count'])->toBe(1);

        // Verify instance was created with correct dates
        $instance = PerformanceCycleInstance::where('performance_cycle_id', $cycle->id)
            ->where('year', 2026)
            ->first();

        expect($instance)->not->toBeNull();
        expect($instance->instance_number)->toBe(1);
        expect($instance->start_date->format('Y-m-d'))->toBe('2026-01-01');
        expect($instance->end_date->format('Y-m-d'))->toBe('2026-12-31');
    });

    it('generates two instances for mid-year cycle', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycleInstance($tenant);

        $admin = createTenantUserForPerformanceCycleInstance($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $cycle = PerformanceCycle::factory()->midYear()->create();

        $controller = new PerformanceCycleInstanceController(new PerformanceCycleInstanceService);

        $generateData = [
            'performance_cycle_id' => $cycle->id,
            'year' => 2026,
            'overwrite' => false,
        ];

        $generateRequest = createGeneratePerformanceCycleInstancesRequest($generateData, $admin);
        $response = $controller->generate($generateRequest);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['count'])->toBe(2);

        // Verify first half instance
        $firstHalf = PerformanceCycleInstance::where('performance_cycle_id', $cycle->id)
            ->where('year', 2026)
            ->where('instance_number', 1)
            ->first();

        expect($firstHalf)->not->toBeNull();
        expect($firstHalf->start_date->format('Y-m-d'))->toBe('2026-01-01');
        expect($firstHalf->end_date->format('Y-m-d'))->toBe('2026-06-30');

        // Verify second half instance
        $secondHalf = PerformanceCycleInstance::where('performance_cycle_id', $cycle->id)
            ->where('year', 2026)
            ->where('instance_number', 2)
            ->first();

        expect($secondHalf)->not->toBeNull();
        expect($secondHalf->start_date->format('Y-m-d'))->toBe('2026-07-01');
        expect($secondHalf->end_date->format('Y-m-d'))->toBe('2026-12-31');
    });

    it('overwrites draft instances when overwrite flag is set', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycleInstance($tenant);

        $admin = createTenantUserForPerformanceCycleInstance($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $cycle = PerformanceCycle::factory()->annual()->create();

        // Create existing draft instance
        $existingInstance = PerformanceCycleInstance::factory()
            ->for($cycle, 'performanceCycle')
            ->draft()
            ->create([
                'year' => 2026,
                'instance_number' => 1,
                'name' => 'Old Instance',
            ]);

        $controller = new PerformanceCycleInstanceController(new PerformanceCycleInstanceService);

        $generateData = [
            'performance_cycle_id' => $cycle->id,
            'year' => 2026,
            'overwrite' => true,
        ];

        $generateRequest = createGeneratePerformanceCycleInstancesRequest($generateData, $admin);
        $response = $controller->generate($generateRequest);

        expect($response->getStatusCode())->toBe(200);

        // Verify old instance is deleted (soft delete)
        expect(PerformanceCycleInstance::find($existingInstance->id))->toBeNull();

        // Verify new instance was created
        $newInstance = PerformanceCycleInstance::where('performance_cycle_id', $cycle->id)
            ->where('year', 2026)
            ->first();

        expect($newInstance)->not->toBeNull();
        expect($newInstance->name)->not->toBe('Old Instance');
    });

    it('skips existing instances when overwrite is false', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycleInstance($tenant);

        $admin = createTenantUserForPerformanceCycleInstance($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $cycle = PerformanceCycle::factory()->annual()->create();

        // Create existing draft instance
        PerformanceCycleInstance::factory()
            ->for($cycle, 'performanceCycle')
            ->draft()
            ->create([
                'year' => 2026,
                'instance_number' => 1,
                'name' => 'Existing Instance',
            ]);

        $controller = new PerformanceCycleInstanceController(new PerformanceCycleInstanceService);

        $generateData = [
            'performance_cycle_id' => $cycle->id,
            'year' => 2026,
            'overwrite' => false,
        ];

        $generateRequest = createGeneratePerformanceCycleInstancesRequest($generateData, $admin);
        $response = $controller->generate($generateRequest);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['count'])->toBe(0);

        // Verify existing instance is preserved
        $instance = PerformanceCycleInstance::where('performance_cycle_id', $cycle->id)
            ->where('year', 2026)
            ->first();

        expect($instance->name)->toBe('Existing Instance');
    });

    it('returns error for non-recurring cycle', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycleInstance($tenant);

        $admin = createTenantUserForPerformanceCycleInstance($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $cycle = PerformanceCycle::factory()->probationary()->create();

        $controller = new PerformanceCycleInstanceController(new PerformanceCycleInstanceService);

        $generateData = [
            'performance_cycle_id' => $cycle->id,
            'year' => 2026,
            'overwrite' => false,
        ];

        $generateRequest = createGeneratePerformanceCycleInstancesRequest($generateData, $admin);
        $response = $controller->generate($generateRequest);

        expect($response->getStatusCode())->toBe(422);

        $data = json_decode($response->getContent(), true);
        expect($data['message'])->toContain('recurring');
    });

    it('transitions status from draft to active', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycleInstance($tenant);

        $admin = createTenantUserForPerformanceCycleInstance($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $cycle = PerformanceCycle::factory()->annual()->create();
        $instance = PerformanceCycleInstance::factory()
            ->for($cycle, 'performanceCycle')
            ->draft()
            ->create();

        $controller = new PerformanceCycleInstanceController(new PerformanceCycleInstanceService);

        $statusRequest = createUpdatePerformanceCycleInstanceStatusRequest(['status' => 'active'], $admin);
        $response = $controller->updateStatus($statusRequest, $instance);

        // Resource was returned successfully
        expect($response)->toBeInstanceOf(\App\Http\Resources\PerformanceCycleInstanceResource::class);

        $instance->refresh();
        expect($instance->status)->toBe(PerformanceCycleInstanceStatus::Active);
        expect($instance->activated_at)->not->toBeNull();
    });

    it('transitions status from active to in_evaluation', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycleInstance($tenant);

        $admin = createTenantUserForPerformanceCycleInstance($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $cycle = PerformanceCycle::factory()->annual()->create();
        $instance = PerformanceCycleInstance::factory()
            ->for($cycle, 'performanceCycle')
            ->active()
            ->create();

        $controller = new PerformanceCycleInstanceController(new PerformanceCycleInstanceService);

        $statusRequest = createUpdatePerformanceCycleInstanceStatusRequest(['status' => 'in_evaluation'], $admin);
        $response = $controller->updateStatus($statusRequest, $instance);

        // Resource was returned successfully
        expect($response)->toBeInstanceOf(\App\Http\Resources\PerformanceCycleInstanceResource::class);

        $instance->refresh();
        expect($instance->status)->toBe(PerformanceCycleInstanceStatus::InEvaluation);
        expect($instance->evaluation_started_at)->not->toBeNull();
    });

    it('transitions status from in_evaluation to closed', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycleInstance($tenant);

        $admin = createTenantUserForPerformanceCycleInstance($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $cycle = PerformanceCycle::factory()->annual()->create();
        $instance = PerformanceCycleInstance::factory()
            ->for($cycle, 'performanceCycle')
            ->inEvaluation()
            ->create();

        $controller = new PerformanceCycleInstanceController(new PerformanceCycleInstanceService);

        $statusRequest = createUpdatePerformanceCycleInstanceStatusRequest(['status' => 'closed'], $admin);
        $response = $controller->updateStatus($statusRequest, $instance);

        // Resource was returned successfully
        expect($response)->toBeInstanceOf(\App\Http\Resources\PerformanceCycleInstanceResource::class);

        $instance->refresh();
        expect($instance->status)->toBe(PerformanceCycleInstanceStatus::Closed);
        expect($instance->closed_at)->not->toBeNull();
        expect($instance->closed_by)->toBe($admin->id);
    });

    it('rejects invalid status transitions', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycleInstance($tenant);

        $admin = createTenantUserForPerformanceCycleInstance($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $cycle = PerformanceCycle::factory()->annual()->create();
        $instance = PerformanceCycleInstance::factory()
            ->for($cycle, 'performanceCycle')
            ->draft()
            ->create();

        $controller = new PerformanceCycleInstanceController(new PerformanceCycleInstanceService);

        // Try to transition from draft directly to closed (invalid)
        $statusRequest = createUpdatePerformanceCycleInstanceStatusRequest(['status' => 'closed'], $admin);

        expect(fn () => $controller->updateStatus($statusRequest, $instance))
            ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    });

    it('updates instance when in draft status', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycleInstance($tenant);

        $admin = createTenantUserForPerformanceCycleInstance($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $cycle = PerformanceCycle::factory()->annual()->create();
        $instance = PerformanceCycleInstance::factory()
            ->for($cycle, 'performanceCycle')
            ->draft()
            ->create([
                'name' => 'Original Name',
                'year' => 2026,
            ]);

        $controller = new PerformanceCycleInstanceController(new PerformanceCycleInstanceService);

        $updateData = [
            'name' => 'Updated Name',
            'year' => 2026,
            'instance_number' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'notes' => 'Updated notes',
        ];

        $updateRequest = createUpdatePerformanceCycleInstanceRequest($updateData, $admin, $instance->id);
        $response = $controller->update($updateRequest, $instance);

        $data = $response->toArray(request());
        expect($data['name'])->toBe('Updated Name');
        expect($data['notes'])->toBe('Updated notes');
    });

    it('soft deletes instance when in draft status', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycleInstance($tenant);

        $admin = createTenantUserForPerformanceCycleInstance($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $cycle = PerformanceCycle::factory()->annual()->create();
        $instance = PerformanceCycleInstance::factory()
            ->for($cycle, 'performanceCycle')
            ->draft()
            ->create();

        $controller = new PerformanceCycleInstanceController(new PerformanceCycleInstanceService);

        $response = $controller->destroy($instance);

        expect($response->getStatusCode())->toBe(204);

        expect(PerformanceCycleInstance::find($instance->id))->toBeNull();
        expect(PerformanceCycleInstance::withTrashed()->find($instance->id))->not->toBeNull();
    });

    it('prevents deletion of non-draft instances', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycleInstance($tenant);

        $admin = createTenantUserForPerformanceCycleInstance($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $cycle = PerformanceCycle::factory()->annual()->create();
        $instance = PerformanceCycleInstance::factory()
            ->for($cycle, 'performanceCycle')
            ->active()
            ->create();

        $controller = new PerformanceCycleInstanceController(new PerformanceCycleInstanceService);

        $response = $controller->destroy($instance);

        expect($response->getStatusCode())->toBe(422);

        // Verify instance still exists
        expect(PerformanceCycleInstance::find($instance->id))->not->toBeNull();
    });
});
