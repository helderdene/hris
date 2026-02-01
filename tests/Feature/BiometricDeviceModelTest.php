<?php

use App\Enums\DeviceStatus;
use App\Models\BiometricDevice;
use App\Models\Tenant;
use App\Models\WorkLocation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForBiometricDeviceModel(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

it('can create a biometric device with valid attributes', function () {
    $tenant = Tenant::factory()->create();
    bindTenantContextForBiometricDeviceModel($tenant);

    $workLocation = WorkLocation::factory()->create();

    $device = BiometricDevice::create([
        'name' => 'Main Entrance Device',
        'device_identifier' => 'DEV-001',
        'work_location_id' => $workLocation->id,
        'status' => DeviceStatus::Offline,
        'is_active' => true,
    ]);

    expect($device)->toBeInstanceOf(BiometricDevice::class);
    expect($device->name)->toBe('Main Entrance Device');
    expect($device->device_identifier)->toBe('DEV-001');
    expect($device->work_location_id)->toBe($workLocation->id);
    expect($device->status)->toBe(DeviceStatus::Offline);
    expect($device->is_active)->toBeTrue();

    $this->assertDatabaseHas('biometric_devices', [
        'name' => 'Main Entrance Device',
        'device_identifier' => 'DEV-001',
    ]);
});

it('enforces unique device_identifier constraint', function () {
    $tenant = Tenant::factory()->create();
    bindTenantContextForBiometricDeviceModel($tenant);

    $workLocation = WorkLocation::factory()->create();

    BiometricDevice::create([
        'name' => 'First Device',
        'device_identifier' => 'DEV-UNIQUE-001',
        'work_location_id' => $workLocation->id,
        'status' => DeviceStatus::Offline,
        'is_active' => true,
    ]);

    // Attempting to create another device with the same identifier should fail
    expect(fn () => BiometricDevice::create([
        'name' => 'Second Device',
        'device_identifier' => 'DEV-UNIQUE-001',
        'work_location_id' => $workLocation->id,
        'status' => DeviceStatus::Offline,
        'is_active' => true,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('has belongsTo relationship with WorkLocation', function () {
    $tenant = Tenant::factory()->create();
    bindTenantContextForBiometricDeviceModel($tenant);

    $workLocation = WorkLocation::factory()->create(['name' => 'Main Office']);

    $device = BiometricDevice::factory()->create([
        'work_location_id' => $workLocation->id,
    ]);

    expect($device->workLocation)->toBeInstanceOf(WorkLocation::class);
    expect($device->workLocation->name)->toBe('Main Office');
});

it('has hasMany relationship from WorkLocation to BiometricDevice', function () {
    $tenant = Tenant::factory()->create();
    bindTenantContextForBiometricDeviceModel($tenant);

    $workLocation = WorkLocation::factory()->create();

    BiometricDevice::factory()->count(3)->create([
        'work_location_id' => $workLocation->id,
    ]);

    expect($workLocation->biometricDevices)->toHaveCount(3);
    expect($workLocation->biometricDevices->first())->toBeInstanceOf(BiometricDevice::class);
});

it('scopeActive filters by is_active equals true', function () {
    $tenant = Tenant::factory()->create();
    bindTenantContextForBiometricDeviceModel($tenant);

    $workLocation = WorkLocation::factory()->create();

    BiometricDevice::factory()->create([
        'work_location_id' => $workLocation->id,
        'is_active' => true,
    ]);

    BiometricDevice::factory()->create([
        'work_location_id' => $workLocation->id,
        'is_active' => true,
    ]);

    BiometricDevice::factory()->create([
        'work_location_id' => $workLocation->id,
        'is_active' => false,
    ]);

    $activeDevices = BiometricDevice::active()->get();

    expect($activeDevices)->toHaveCount(2);
    expect($activeDevices->every(fn ($device) => $device->is_active))->toBeTrue();
});

it('casts status to DeviceStatus enum correctly', function () {
    $tenant = Tenant::factory()->create();
    bindTenantContextForBiometricDeviceModel($tenant);

    $workLocation = WorkLocation::factory()->create();

    $device = BiometricDevice::create([
        'name' => 'Test Device',
        'device_identifier' => 'DEV-CAST-001',
        'work_location_id' => $workLocation->id,
        'status' => DeviceStatus::Online,
        'is_active' => true,
    ]);

    $device->refresh();

    expect($device->status)->toBe(DeviceStatus::Online);
    expect($device->status->value)->toBe('online');
    expect($device->status->label())->toBe('Online');

    $device->update(['status' => DeviceStatus::Offline]);
    $device->refresh();

    expect($device->status)->toBe(DeviceStatus::Offline);
    expect($device->status->label())->toBe('Offline');
});

it('calculates uptime correctly when device is online', function () {
    $tenant = Tenant::factory()->create();
    bindTenantContextForBiometricDeviceModel($tenant);

    $workLocation = WorkLocation::factory()->create();

    // Device connected 2 hours ago
    $connectionStarted = Carbon::now()->subHours(2);

    $device = BiometricDevice::factory()->create([
        'work_location_id' => $workLocation->id,
        'status' => DeviceStatus::Online,
        'connection_started_at' => $connectionStarted,
    ]);

    // Allow for some variance in timing (within 5 seconds)
    expect($device->uptime_seconds)->toBeGreaterThanOrEqual(7195);
    expect($device->uptime_seconds)->toBeLessThanOrEqual(7205);

    // Check human readable format contains "hours" or "hour"
    expect($device->uptime_human)->toContain('hour');
});

it('returns null uptime when connection_started_at is null', function () {
    $tenant = Tenant::factory()->create();
    bindTenantContextForBiometricDeviceModel($tenant);

    $workLocation = WorkLocation::factory()->create();

    $device = BiometricDevice::factory()->create([
        'work_location_id' => $workLocation->id,
        'status' => DeviceStatus::Offline,
        'connection_started_at' => null,
    ]);

    expect($device->uptime_seconds)->toBeNull();
    expect($device->uptime_human)->toBeNull();
});
