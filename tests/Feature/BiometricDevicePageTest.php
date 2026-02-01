<?php

/**
 * Tests for the Biometric Device Listing Page
 *
 * These tests verify the Inertia page rendering, filtering, and access control
 * for the biometric device management feature.
 *
 * Note: These tests call controllers directly to test permissions and response data
 * without triggering full HTTP response rendering which requires Vite manifest.
 */

use App\Enums\TenantUserRole;
use App\Http\Controllers\OrganizationController;
use App\Models\BiometricDevice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForBiometricDevicePage(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForBiometricDevicePage(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to extract Inertia response data without triggering full HTTP response.
 * Uses reflection to access the protected props property.
 */
function getInertiaResponseDataForBiometricDevices(\Inertia\Response $response): array
{
    $reflection = new ReflectionClass($response);
    $property = $reflection->getProperty('props');
    $property->setAccessible(true);

    return $property->getValue($response);
}

/**
 * Helper to get the Inertia component name.
 * Uses reflection to access the protected component property.
 */
function getInertiaComponentForBiometricDevices(\Inertia\Response $response): string
{
    $reflection = new ReflectionClass($response);
    $property = $reflection->getProperty('component');
    $property->setAccessible(true);

    return $property->getValue($response);
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('Biometric Device Listing Page', function () {
    it('renders devices page with device data', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForBiometricDevicePage($tenant);

        $admin = createTenantUserForBiometricDevicePage($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $workLocation = WorkLocation::factory()->create(['name' => 'Main Office']);
        BiometricDevice::factory()->count(3)->create([
            'work_location_id' => $workLocation->id,
        ]);

        $controller = new OrganizationController;
        $request = Request::create('/organization/devices', 'GET');
        app()->instance('request', $request);

        $response = $controller->devicesIndex($request);

        expect($response)->toBeInstanceOf(\Inertia\Response::class);
        expect(getInertiaComponentForBiometricDevices($response))->toBe('Organization/Devices/Index');

        $data = getInertiaResponseDataForBiometricDevices($response);
        expect($data)->toHaveKey('devices');
        expect($data)->toHaveKey('workLocations');
        expect($data)->toHaveKey('statusCounts');
        expect(count($data['devices']))->toBe(3);
    });

    it('displays status summary cards with correct counts', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForBiometricDevicePage($tenant);

        $admin = createTenantUserForBiometricDevicePage($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $workLocation = WorkLocation::factory()->create();

        // Create devices with different statuses
        BiometricDevice::factory()->online()->count(2)->create([
            'work_location_id' => $workLocation->id,
        ]);
        BiometricDevice::factory()->offline()->count(3)->create([
            'work_location_id' => $workLocation->id,
        ]);

        $controller = new OrganizationController;
        $request = Request::create('/organization/devices', 'GET');
        app()->instance('request', $request);

        $response = $controller->devicesIndex($request);

        $data = getInertiaResponseDataForBiometricDevices($response);
        expect($data)->toHaveKey('statusCounts');
        expect($data['statusCounts']['total'])->toBe(5);
        expect($data['statusCounts']['online'])->toBe(2);
        expect($data['statusCounts']['offline'])->toBe(3);
    });

    it('filters devices by status via query parameter', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForBiometricDevicePage($tenant);

        $admin = createTenantUserForBiometricDevicePage($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $workLocation = WorkLocation::factory()->create();

        BiometricDevice::factory()->online()->count(2)->create([
            'work_location_id' => $workLocation->id,
        ]);
        BiometricDevice::factory()->offline()->create([
            'work_location_id' => $workLocation->id,
        ]);

        $controller = new OrganizationController;
        $request = Request::create('/organization/devices', 'GET', ['status' => 'online']);
        app()->instance('request', $request);

        $response = $controller->devicesIndex($request);

        $data = getInertiaResponseDataForBiometricDevices($response);
        expect(count($data['devices']))->toBe(2);
        expect($data['filters']['status'])->toBe('online');
    });

    it('filters devices by work location via query parameter', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForBiometricDevicePage($tenant);

        $admin = createTenantUserForBiometricDevicePage($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $location1 = WorkLocation::factory()->create(['name' => 'Location 1']);
        $location2 = WorkLocation::factory()->create(['name' => 'Location 2']);

        BiometricDevice::factory()->count(2)->create([
            'work_location_id' => $location1->id,
        ]);
        BiometricDevice::factory()->count(3)->create([
            'work_location_id' => $location2->id,
        ]);

        $controller = new OrganizationController;
        // Pass work_location_id as a string to simulate browser query params
        $request = Request::create('/organization/devices', 'GET', ['work_location_id' => (string) $location1->id]);
        app()->instance('request', $request);

        $response = $controller->devicesIndex($request);

        $data = getInertiaResponseDataForBiometricDevices($response);
        expect(count($data['devices']))->toBe(2);
        expect($data['filters']['work_location_id'])->toBe((string) $location1->id);
    });

    it('denies access to unauthorized users', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForBiometricDevicePage($tenant);

        // Employee role should not have access
        $employee = createTenantUserForBiometricDevicePage($tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        // Verify Gate does not authorize employee
        expect(Gate::allows('can-manage-organization'))->toBeFalse();
    });

    it('passes work locations for filter dropdown', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForBiometricDevicePage($tenant);

        $admin = createTenantUserForBiometricDevicePage($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create active and inactive locations
        WorkLocation::factory()->create(['name' => 'Active Location', 'status' => 'active']);
        WorkLocation::factory()->create(['name' => 'Inactive Location', 'status' => 'inactive']);

        $controller = new OrganizationController;
        $request = Request::create('/organization/devices', 'GET');
        app()->instance('request', $request);

        $response = $controller->devicesIndex($request);

        $data = getInertiaResponseDataForBiometricDevices($response);
        // Only active locations should be in workLocations for filter
        expect(count($data['workLocations']))->toBe(1);
    });
});
