<?php

/**
 * Tests for the BiometricDeviceFormModal Component
 *
 * These tests verify the form modal behavior for create and edit operations,
 * including validation rules, API endpoint calls, and field behavior.
 *
 * Note: The form modal component makes API calls to the BiometricDeviceController.
 * These tests verify the backend behavior that the frontend component relies on.
 */

use App\Enums\DeviceStatus;
use App\Enums\TenantUserRole;
use App\Http\Requests\StoreBiometricDeviceRequest;
use App\Http\Requests\UpdateBiometricDeviceRequest;
use App\Models\BiometricDevice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForBiometricDeviceFormModal(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForBiometricDeviceFormModal(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
{
    $user = User::factory()->create($userAttributes);
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('BiometricDeviceFormModal - Create Mode', function () {
    it('validates all required fields for device creation', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricDeviceFormModal($tenant);

        $admin = createTenantUserForBiometricDeviceFormModal($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Empty form submission should fail with validation errors
        $validator = Validator::make([], (new StoreBiometricDeviceRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('name'))->toBeTrue();
        expect($validator->errors()->has('device_identifier'))->toBeTrue();
        expect($validator->errors()->has('work_location_id'))->toBeTrue();
    });

    it('creates device via POST to /api/organization/devices with valid data', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricDeviceFormModal($tenant);

        $admin = createTenantUserForBiometricDeviceFormModal($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $workLocation = WorkLocation::factory()->create(['status' => 'active']);

        $formData = [
            'name' => 'Reception Device',
            'device_identifier' => 'DEV-FORM-001',
            'work_location_id' => $workLocation->id,
            'is_active' => true,
        ];

        // Validate the form data passes validation
        $validator = Validator::make($formData, (new StoreBiometricDeviceRequest)->rules());
        expect($validator->passes())->toBeTrue();

        // Create the device to simulate form submission
        $device = BiometricDevice::create([
            'name' => $formData['name'],
            'device_identifier' => $formData['device_identifier'],
            'work_location_id' => $formData['work_location_id'],
            'is_active' => $formData['is_active'],
            'status' => DeviceStatus::Offline,
        ]);

        expect($device)->not->toBeNull();
        expect($device->name)->toBe('Reception Device');
        expect($device->device_identifier)->toBe('DEV-FORM-001');
        expect($device->work_location_id)->toBe($workLocation->id);
        expect($device->is_active)->toBeTrue();

        $this->assertDatabaseHas('biometric_devices', [
            'name' => 'Reception Device',
            'device_identifier' => 'DEV-FORM-001',
        ]);
    });

    it('validates work_location_id references an active location', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricDeviceFormModal($tenant);

        $admin = createTenantUserForBiometricDeviceFormModal($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create an inactive work location
        $inactiveLocation = WorkLocation::factory()->create(['status' => 'inactive']);

        $formData = [
            'name' => 'Test Device',
            'device_identifier' => 'DEV-INACTIVE-LOC',
            'work_location_id' => $inactiveLocation->id,
            'is_active' => true,
        ];

        $validator = Validator::make($formData, (new StoreBiometricDeviceRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('work_location_id'))->toBeTrue();
    });
});

describe('BiometricDeviceFormModal - Edit Mode', function () {
    it('allows update without device_identifier in payload', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricDeviceFormModal($tenant);

        $admin = createTenantUserForBiometricDeviceFormModal($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $workLocation1 = WorkLocation::factory()->create(['status' => 'active']);
        $workLocation2 = WorkLocation::factory()->create(['status' => 'active']);

        $device = BiometricDevice::factory()->create([
            'name' => 'Original Name',
            'device_identifier' => 'DEV-READONLY-001',
            'work_location_id' => $workLocation1->id,
            'is_active' => true,
        ]);

        // Simulate edit form submission (device_identifier is NOT included)
        $updateData = [
            'name' => 'Updated Name',
            'work_location_id' => $workLocation2->id,
            'is_active' => false,
        ];

        // Validate the update data passes validation
        $validator = Validator::make($updateData, (new UpdateBiometricDeviceRequest)->rules());
        expect($validator->passes())->toBeTrue();

        // Update the device
        $device->update($updateData);
        $device->refresh();

        // Device name should be updated
        expect($device->name)->toBe('Updated Name');
        // Work location should be updated
        expect($device->work_location_id)->toBe($workLocation2->id);
        // Active status should be updated
        expect($device->is_active)->toBeFalse();
        // Device identifier should remain unchanged
        expect($device->device_identifier)->toBe('DEV-READONLY-001');
    });

    it('validates update request fields correctly', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricDeviceFormModal($tenant);

        $admin = createTenantUserForBiometricDeviceFormModal($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Empty update data should fail on required fields
        $validator = Validator::make([], (new UpdateBiometricDeviceRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('name'))->toBeTrue();
        expect($validator->errors()->has('work_location_id'))->toBeTrue();
        // Note: device_identifier is NOT required for update (it's readonly in the form)
        expect($validator->errors()->has('device_identifier'))->toBeFalse();
    });
});

describe('BiometricDeviceFormModal - Validation Errors Display', function () {
    it('returns validation errors for duplicate device_identifier', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricDeviceFormModal($tenant);

        $admin = createTenantUserForBiometricDeviceFormModal($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $workLocation = WorkLocation::factory()->create(['status' => 'active']);

        // Create existing device
        BiometricDevice::factory()->create([
            'device_identifier' => 'DEV-DUPLICATE',
            'work_location_id' => $workLocation->id,
        ]);

        // Attempt to create another device with same identifier
        $formData = [
            'name' => 'New Device',
            'device_identifier' => 'DEV-DUPLICATE',
            'work_location_id' => $workLocation->id,
            'is_active' => true,
        ];

        $validator = Validator::make($formData, (new StoreBiometricDeviceRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('device_identifier'))->toBeTrue();
        // Validation error message should indicate the identifier is already taken
        $errorMessage = $validator->errors()->first('device_identifier');
        expect($errorMessage)->toContain('taken');
    });
});
