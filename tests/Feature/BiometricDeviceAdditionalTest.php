<?php

/**
 * Additional strategic tests for BiometricDevice feature
 *
 * These tests fill critical gaps identified during Task Group 5 review:
 * - WorkLocation cascade delete behavior
 * - Combined filters (status + location)
 * - API show endpoint
 * - BiometricDeviceResource computed properties
 * - Empty state behavior
 */

use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\BiometricDeviceController;
use App\Http\Controllers\OrganizationController;
use App\Http\Resources\BiometricDeviceResource;
use App\Models\BiometricDevice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForBiometricDeviceAdditional(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForBiometricDeviceAdditional(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to extract Inertia response data.
 */
function getInertiaPropsForBiometricDeviceAdditional(\Inertia\Response $response): array
{
    $reflection = new ReflectionClass($response);
    $property = $reflection->getProperty('props');
    $property->setAccessible(true);

    return $property->getValue($response);
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('WorkLocation Cascade Delete', function () {
    it('deletes associated biometric devices when work location is deleted', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricDeviceAdditional($tenant);

        $workLocation = WorkLocation::factory()->create();

        // Create devices associated with this location
        $device1 = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
            'device_identifier' => 'DEV-CASCADE-001',
        ]);
        $device2 = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
            'device_identifier' => 'DEV-CASCADE-002',
        ]);

        $device1Id = $device1->id;
        $device2Id = $device2->id;

        // Verify devices exist
        $this->assertDatabaseHas('biometric_devices', ['id' => $device1Id]);
        $this->assertDatabaseHas('biometric_devices', ['id' => $device2Id]);

        // Delete work location
        $workLocation->delete();

        // Verify devices are cascade deleted
        $this->assertDatabaseMissing('biometric_devices', ['id' => $device1Id]);
        $this->assertDatabaseMissing('biometric_devices', ['id' => $device2Id]);
    });
});

describe('Combined Filters', function () {
    it('supports filtering by both status and work location together', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricDeviceAdditional($tenant);

        $admin = createTenantUserForBiometricDeviceAdditional($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $location1 = WorkLocation::factory()->create(['name' => 'Location 1']);
        $location2 = WorkLocation::factory()->create(['name' => 'Location 2']);

        // Create mixed devices
        BiometricDevice::factory()->online()->create(['work_location_id' => $location1->id]);
        BiometricDevice::factory()->online()->create(['work_location_id' => $location1->id]);
        BiometricDevice::factory()->offline()->create(['work_location_id' => $location1->id]);
        BiometricDevice::factory()->online()->create(['work_location_id' => $location2->id]);
        BiometricDevice::factory()->offline()->create(['work_location_id' => $location2->id]);

        $controller = new BiometricDeviceController;

        // Filter by both online status AND location1
        $request = Request::create('/api/organization/devices', 'GET', [
            'status' => 'online',
            'work_location_id' => $location1->id,
        ]);
        $response = $controller->index($request);

        expect($response->count())->toBe(2);

        // Verify all returned devices are online AND at location1
        foreach ($response as $deviceResource) {
            $data = $deviceResource->toArray(request());
            expect($data['status'])->toBe('online');
            expect($data['work_location_id'])->toBe($location1->id);
        }
    });
});

describe('API Show Endpoint', function () {
    it('returns single device with full details via show endpoint', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricDeviceAdditional($tenant);

        $admin = createTenantUserForBiometricDeviceAdditional($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $workLocation = WorkLocation::factory()->create(['name' => 'Main Office', 'code' => 'MO']);

        $device = BiometricDevice::factory()->online()->create([
            'name' => 'Reception Device',
            'device_identifier' => 'DEV-SHOW-001',
            'work_location_id' => $workLocation->id,
            'connection_started_at' => Carbon::now()->subHour(),
        ]);

        $controller = new BiometricDeviceController;
        $request = Request::create("/api/biometric-devices/{$device->id}", 'GET');
        $request->setRouteResolver(fn () => (new \Illuminate\Routing\Route('GET', '/api/biometric-devices/{deviceId}', []))->bind($request)->setParameter('deviceId', $device->id));
        $response = $controller->show($request);

        $data = $response->toArray(request());

        expect($data['id'])->toBe($device->id);
        expect($data['name'])->toBe('Reception Device');
        expect($data['device_identifier'])->toBe('DEV-SHOW-001');
        expect($data['status'])->toBe('online');
        expect($data['status_label'])->toBe('Online');
        expect($data)->toHaveKey('work_location');
        expect($data['work_location']['name'])->toBe('Main Office');
        expect($data['work_location']['code'])->toBe('MO');
        expect($data)->toHaveKey('uptime_seconds');
        expect($data)->toHaveKey('uptime_human');
    });
});

describe('BiometricDeviceResource Computed Properties', function () {
    it('includes last_seen_human in resource response', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricDeviceAdditional($tenant);

        $workLocation = WorkLocation::factory()->create();

        $device = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
            'last_seen_at' => Carbon::now()->subMinutes(5),
        ]);

        $device->load('workLocation');
        $resource = new BiometricDeviceResource($device);
        $data = $resource->toArray(request());

        expect($data)->toHaveKey('last_seen_human');
        expect($data['last_seen_human'])->toContain('minutes ago');
    });

    it('returns null for last_seen_human when last_seen_at is null', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricDeviceAdditional($tenant);

        $workLocation = WorkLocation::factory()->create();

        $device = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
            'last_seen_at' => null,
        ]);

        $device->load('workLocation');
        $resource = new BiometricDeviceResource($device);
        $data = $resource->toArray(request());

        expect($data['last_seen_human'])->toBeNull();
    });
});

describe('Empty State', function () {
    it('handles page rendering with no devices gracefully', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForBiometricDeviceAdditional($tenant);

        $admin = createTenantUserForBiometricDeviceAdditional($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create a work location but no devices
        WorkLocation::factory()->create();

        $controller = new OrganizationController;
        $request = Request::create('/organization/devices', 'GET');
        app()->instance('request', $request);

        $response = $controller->devicesIndex($request);

        $data = getInertiaPropsForBiometricDeviceAdditional($response);

        // Devices is a resource collection - check its count
        expect($data['devices'])->toBeInstanceOf(\Illuminate\Http\Resources\Json\AnonymousResourceCollection::class);
        expect($data['devices']->count())->toBe(0);
        expect($data['statusCounts']['total'])->toBe(0);
        expect($data['statusCounts']['online'])->toBe(0);
        expect($data['statusCounts']['offline'])->toBe(0);
    });
});

describe('Factory States', function () {
    it('factory inactive state creates device with is_active false', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricDeviceAdditional($tenant);

        $workLocation = WorkLocation::factory()->create();

        $device = BiometricDevice::factory()->inactive()->create([
            'work_location_id' => $workLocation->id,
        ]);

        expect($device->is_active)->toBeFalse();

        // Verify scopeActive excludes it
        $activeDevices = BiometricDevice::active()->get();
        expect($activeDevices->contains($device))->toBeFalse();
    });
});
