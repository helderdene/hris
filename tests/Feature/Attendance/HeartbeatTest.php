<?php

use App\Enums\DeviceStatus;
use App\Jobs\MarkOfflineDevicesJob;
use App\Models\BiometricDevice;
use App\Models\Tenant;
use App\Models\WorkLocation;
use App\Services\Attendance\HeartbeatProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForHeartbeat(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('HeartbeatProcessor', function () {
    it('processes valid heartbeat and updates device status', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForHeartbeat($tenant);

        $workLocation = WorkLocation::factory()->create();
        $device = BiometricDevice::factory()->create([
            'device_identifier' => '2582493',
            'work_location_id' => $workLocation->id,
            'is_active' => true,
            'status' => DeviceStatus::Offline,
            'last_seen_at' => null,
            'connection_started_at' => null,
        ]);

        $payload = json_encode([
            'operator' => 'HeartBeat',
            'info' => [
                'facesluiceId' => '2582493',
            ],
        ]);

        $processor = app(HeartbeatProcessor::class);
        $result = $processor->process($payload);

        expect($result)->toBeTrue();

        $device->refresh();
        expect($device->status)->toBe(DeviceStatus::Online)
            ->and($device->last_seen_at)->not->toBeNull()
            ->and($device->connection_started_at)->not->toBeNull();
    });

    it('does not overwrite existing connection_started_at', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForHeartbeat($tenant);

        $workLocation = WorkLocation::factory()->create();
        $existingConnectionTime = now()->subHours(2);
        $device = BiometricDevice::factory()->create([
            'device_identifier' => '2582493',
            'work_location_id' => $workLocation->id,
            'is_active' => true,
            'status' => DeviceStatus::Online,
            'last_seen_at' => now()->subMinute(),
            'connection_started_at' => $existingConnectionTime,
        ]);

        $payload = json_encode([
            'operator' => 'HeartBeat',
            'info' => [
                'facesluiceId' => '2582493',
            ],
        ]);

        $processor = app(HeartbeatProcessor::class);
        $processor->process($payload);

        $device->refresh();
        expect($device->connection_started_at->format('Y-m-d H:i:s'))
            ->toBe($existingConnectionTime->format('Y-m-d H:i:s'));
    });

    it('returns false for unknown device identifier', function () {
        Tenant::factory()->create();

        $payload = json_encode([
            'operator' => 'HeartBeat',
            'info' => [
                'facesluiceId' => 'UNKNOWN-DEVICE',
            ],
        ]);

        $processor = app(HeartbeatProcessor::class);
        $result = $processor->process($payload);

        expect($result)->toBeFalse();
    });

    it('returns false for malformed JSON payload', function () {
        $processor = app(HeartbeatProcessor::class);

        expect($processor->process('not json'))->toBeFalse();
        expect($processor->process('{invalid}'))->toBeFalse();
    });

    it('returns false when device identifier cannot be extracted', function () {
        $processor = app(HeartbeatProcessor::class);

        $payload = json_encode(['operator' => 'HeartBeat', 'info' => []]);

        expect($processor->process($payload))->toBeFalse();
    });

    it('handles top-level facesluiceId format', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForHeartbeat($tenant);

        $workLocation = WorkLocation::factory()->create();
        $device = BiometricDevice::factory()->create([
            'device_identifier' => '9999999',
            'work_location_id' => $workLocation->id,
            'is_active' => true,
            'status' => DeviceStatus::Offline,
        ]);

        $payload = json_encode([
            'facesluiceId' => '9999999',
        ]);

        $processor = app(HeartbeatProcessor::class);
        $result = $processor->process($payload);

        expect($result)->toBeTrue();

        $device->refresh();
        expect($device->status)->toBe(DeviceStatus::Online);
    });
});

describe('MarkOfflineDevicesJob', function () {
    it('marks stale online devices as offline', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForHeartbeat($tenant);

        $workLocation = WorkLocation::factory()->create();

        // Stale device — last seen 5 minutes ago
        $staleDevice = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
            'status' => DeviceStatus::Online,
            'last_seen_at' => now()->subMinutes(5),
            'connection_started_at' => now()->subHour(),
        ]);

        (new MarkOfflineDevicesJob)->handle(app(\App\Services\Tenant\TenantDatabaseManager::class));

        $staleDevice->refresh();
        expect($staleDevice->status)->toBe(DeviceStatus::Offline)
            ->and($staleDevice->connection_started_at)->toBeNull();
    });

    it('leaves recently seen devices online', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForHeartbeat($tenant);

        $workLocation = WorkLocation::factory()->create();

        // Fresh device — last seen 1 minute ago
        $freshDevice = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
            'status' => DeviceStatus::Online,
            'last_seen_at' => now()->subMinute(),
            'connection_started_at' => now()->subHour(),
        ]);

        (new MarkOfflineDevicesJob)->handle(app(\App\Services\Tenant\TenantDatabaseManager::class));

        $freshDevice->refresh();
        expect($freshDevice->status)->toBe(DeviceStatus::Online)
            ->and($freshDevice->connection_started_at)->not->toBeNull();
    });

    it('does not affect already offline devices', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForHeartbeat($tenant);

        $workLocation = WorkLocation::factory()->create();

        $offlineDevice = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
            'status' => DeviceStatus::Offline,
            'last_seen_at' => now()->subHour(),
            'connection_started_at' => null,
        ]);

        (new MarkOfflineDevicesJob)->handle(app(\App\Services\Tenant\TenantDatabaseManager::class));

        $offlineDevice->refresh();
        expect($offlineDevice->status)->toBe(DeviceStatus::Offline);
    });
});
