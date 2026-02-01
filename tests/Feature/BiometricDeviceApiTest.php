<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\BiometricDeviceController;
use App\Http\Requests\StoreBiometricDeviceRequest;
use App\Http\Requests\UpdateBiometricDeviceRequest;
use App\Models\BiometricDevice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForBiometricDeviceApi(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForBiometricDeviceApi(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated store biometric device request.
 */
function createStoreBiometricDeviceRequestHelper(array $data, User $user): StoreBiometricDeviceRequest
{
    $request = StoreBiometricDeviceRequest::create('/api/organization/devices', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new StoreBiometricDeviceRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated update biometric device request.
 */
function createUpdateBiometricDeviceRequestHelper(array $data, User $user, int $deviceId): UpdateBiometricDeviceRequest
{
    $request = UpdateBiometricDeviceRequest::create("/api/organization/devices/{$deviceId}", 'PUT', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new UpdateBiometricDeviceRequest)->rules());
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

describe('BiometricDevice API', function () {
    it('index returns devices with work location data', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricDeviceApi($tenant);

        $admin = createTenantUserForBiometricDeviceApi($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $workLocation = WorkLocation::factory()->create(['name' => 'Main Office']);
        BiometricDevice::factory()->count(3)->create([
            'work_location_id' => $workLocation->id,
        ]);

        $controller = new BiometricDeviceController;
        $request = \Illuminate\Http\Request::create('/api/organization/devices', 'GET');
        $response = $controller->index($request);

        expect($response->count())->toBe(3);

        // Check that work location data is included
        $firstDevice = $response->first();
        $deviceData = $firstDevice->toArray(request());

        expect($deviceData)->toHaveKey('work_location');
        expect($deviceData['work_location']['name'])->toBe('Main Office');
    });

    it('store creates device with valid data and returns 201', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricDeviceApi($tenant);

        $admin = createTenantUserForBiometricDeviceApi($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $workLocation = WorkLocation::factory()->create(['status' => 'active']);

        $controller = new BiometricDeviceController;
        $storeRequest = createStoreBiometricDeviceRequestHelper([
            'name' => 'Entrance Device',
            'device_identifier' => 'DEV-API-001',
            'work_location_id' => $workLocation->id,
            'is_active' => true,
        ], $admin);

        $response = $controller->store($storeRequest);
        $data = json_decode($response->getContent(), true);

        expect($response->getStatusCode())->toBe(201);
        // Note: JsonResource::withoutWrapping() is enabled, so data is not wrapped
        expect($data['name'])->toBe('Entrance Device');
        expect($data['device_identifier'])->toBe('DEV-API-001');
        expect($data['work_location_id'])->toBe($workLocation->id);
        expect($data['is_active'])->toBeTrue();
        expect($data['status'])->toBe('offline'); // Default status

        // Verify in database
        $this->assertDatabaseHas('biometric_devices', [
            'name' => 'Entrance Device',
            'device_identifier' => 'DEV-API-001',
        ]);
    });

    it('store rejects duplicate device_identifier within tenant', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricDeviceApi($tenant);

        $admin = createTenantUserForBiometricDeviceApi($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $workLocation = WorkLocation::factory()->create(['status' => 'active']);

        // Create first device
        BiometricDevice::factory()->create([
            'device_identifier' => 'DEV-DUP-001',
            'work_location_id' => $workLocation->id,
        ]);

        // Attempt to create second device with same identifier
        $data = [
            'name' => 'Second Device',
            'device_identifier' => 'DEV-DUP-001',
            'work_location_id' => $workLocation->id,
            'is_active' => true,
        ];

        $request = StoreBiometricDeviceRequest::create('/api/organization/devices', 'POST', $data);
        $validator = Validator::make($data, (new StoreBiometricDeviceRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('device_identifier'))->toBeTrue();
    });

    it('store validates work_location_id references active location', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricDeviceApi($tenant);

        $admin = createTenantUserForBiometricDeviceApi($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create an inactive work location
        $inactiveLocation = WorkLocation::factory()->create(['status' => 'inactive']);

        $data = [
            'name' => 'Test Device',
            'device_identifier' => 'DEV-INACTIVE-001',
            'work_location_id' => $inactiveLocation->id,
            'is_active' => true,
        ];

        $request = StoreBiometricDeviceRequest::create('/api/organization/devices', 'POST', $data);
        $validator = Validator::make($data, (new StoreBiometricDeviceRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('work_location_id'))->toBeTrue();
    });

    it('update modifies device but device_identifier remains unchanged', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricDeviceApi($tenant);

        $admin = createTenantUserForBiometricDeviceApi($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $workLocation1 = WorkLocation::factory()->create(['status' => 'active']);
        $workLocation2 = WorkLocation::factory()->create(['status' => 'active']);

        $device = BiometricDevice::factory()->create([
            'name' => 'Original Name',
            'device_identifier' => 'DEV-ORIGINAL-001',
            'work_location_id' => $workLocation1->id,
            'is_active' => true,
        ]);

        $controller = new BiometricDeviceController;
        $updateRequest = createUpdateBiometricDeviceRequestHelper([
            'name' => 'Updated Name',
            'work_location_id' => $workLocation2->id,
            'is_active' => false,
        ], $admin, $device->id);

        $response = $controller->update($updateRequest, $device);
        $data = $response->toArray(request());

        expect($data['name'])->toBe('Updated Name');
        expect($data['work_location_id'])->toBe($workLocation2->id);
        expect($data['is_active'])->toBeFalse();

        // Device identifier should NOT be changed (not part of update request)
        expect($data['device_identifier'])->toBe('DEV-ORIGINAL-001');

        // Verify in database
        $device->refresh();
        expect($device->name)->toBe('Updated Name');
        expect($device->device_identifier)->toBe('DEV-ORIGINAL-001');
    });

    it('destroy removes device', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricDeviceApi($tenant);

        $admin = createTenantUserForBiometricDeviceApi($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $workLocation = WorkLocation::factory()->create();
        $device = BiometricDevice::factory()->create([
            'name' => 'Device To Delete',
            'device_identifier' => 'DEV-DELETE-001',
            'work_location_id' => $workLocation->id,
        ]);

        $deviceId = $device->id;

        $controller = new BiometricDeviceController;
        $response = $controller->destroy($device);
        $data = json_decode($response->getContent(), true);

        expect($response->getStatusCode())->toBe(200);
        expect($data['message'])->toContain('deleted');

        // Verify device is removed from database
        $this->assertDatabaseMissing('biometric_devices', [
            'id' => $deviceId,
        ]);
    });

    it('only authorized users can access device endpoints', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricDeviceApi($tenant);

        // Create users with different roles
        $admin = createTenantUserForBiometricDeviceApi($tenant, TenantUserRole::Admin);
        $hrManager = createTenantUserForBiometricDeviceApi($tenant, TenantUserRole::HrManager);
        $employee = createTenantUserForBiometricDeviceApi($tenant, TenantUserRole::Employee);
        $hrStaff = createTenantUserForBiometricDeviceApi($tenant, TenantUserRole::HrStaff);

        // Admin can access (has OrganizationManage permission)
        expect(Gate::forUser($admin)->allows('can-manage-organization'))->toBeTrue();

        // HR Manager can access (has OrganizationManage permission)
        expect(Gate::forUser($hrManager)->allows('can-manage-organization'))->toBeTrue();

        // Employee cannot access (no OrganizationManage permission)
        expect(Gate::forUser($employee)->allows('can-manage-organization'))->toBeFalse();

        // HR Staff cannot access
        expect(Gate::forUser($hrStaff)->allows('can-manage-organization'))->toBeFalse();
    });
});

describe('BiometricDevice API Filters', function () {
    it('supports filtering by status', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricDeviceApi($tenant);

        $admin = createTenantUserForBiometricDeviceApi($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $workLocation = WorkLocation::factory()->create();

        BiometricDevice::factory()->online()->create(['work_location_id' => $workLocation->id]);
        BiometricDevice::factory()->online()->create(['work_location_id' => $workLocation->id]);
        BiometricDevice::factory()->offline()->create(['work_location_id' => $workLocation->id]);

        $controller = new BiometricDeviceController;

        // Filter by online status
        $onlineRequest = \Illuminate\Http\Request::create('/api/organization/devices', 'GET', ['status' => 'online']);
        $onlineResponse = $controller->index($onlineRequest);
        expect($onlineResponse->count())->toBe(2);

        // Filter by offline status
        $offlineRequest = \Illuminate\Http\Request::create('/api/organization/devices', 'GET', ['status' => 'offline']);
        $offlineResponse = $controller->index($offlineRequest);
        expect($offlineResponse->count())->toBe(1);
    });

    it('supports filtering by work location', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricDeviceApi($tenant);

        $admin = createTenantUserForBiometricDeviceApi($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $location1 = WorkLocation::factory()->create(['name' => 'Location 1']);
        $location2 = WorkLocation::factory()->create(['name' => 'Location 2']);

        BiometricDevice::factory()->count(2)->create(['work_location_id' => $location1->id]);
        BiometricDevice::factory()->count(3)->create(['work_location_id' => $location2->id]);

        $controller = new BiometricDeviceController;

        // Filter by location 1
        $location1Request = \Illuminate\Http\Request::create('/api/organization/devices', 'GET', ['work_location_id' => $location1->id]);
        $location1Response = $controller->index($location1Request);
        expect($location1Response->count())->toBe(2);

        // Filter by location 2
        $location2Request = \Illuminate\Http\Request::create('/api/organization/devices', 'GET', ['work_location_id' => $location2->id]);
        $location2Response = $controller->index($location2Request);
        expect($location2Response->count())->toBe(3);
    });
});
