<?php

use App\Jobs\SyncVisitorToDeviceJob;
use App\Models\BiometricDevice;
use App\Models\Tenant;
use App\Models\Visitor;
use App\Models\VisitorDeviceSync;
use App\Models\WorkLocation;
use App\Services\Visitor\VisitorDeviceSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
    config(['database.connections.tenant' => config('database.connections.sqlite')]);
    DB::connection('tenant')->setPdo(DB::connection()->getPdo());
    DB::connection('tenant')->setReadPdo(DB::connection()->getReadPdo());
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->tenant = Tenant::factory()->withTrial()->create();
    app()->instance('tenant', $this->tenant);
});

describe('Visitor Device Sync', function () {
    it('creates a device sync record', function () {
        Queue::fake();

        $visitor = Visitor::factory()->withPhoto()->create();
        $location = WorkLocation::factory()->create();
        $device = BiometricDevice::factory()->online()->forWorkLocation($location)->create();

        $service = app(VisitorDeviceSyncService::class);
        $sync = $service->syncVisitorToDevice($visitor, $device);

        expect($sync)->toBeInstanceOf(VisitorDeviceSync::class);
        expect($sync->visitor_id)->toBe($visitor->id);
        expect($sync->biometric_device_id)->toBe($device->id);
        expect($sync->status)->toBe('pending');

        $this->assertDatabaseHas('visitor_device_syncs', [
            'visitor_id' => $visitor->id,
            'biometric_device_id' => $device->id,
            'status' => 'pending',
        ]);
    });

    it('dispatches sync job', function () {
        Queue::fake();

        $visitor = Visitor::factory()->withPhoto()->create();
        $location = WorkLocation::factory()->create();
        $device = BiometricDevice::factory()->online()->forWorkLocation($location)->create();

        $service = app(VisitorDeviceSyncService::class);
        $service->syncVisitorToDevice($visitor, $device);

        Queue::assertPushed(SyncVisitorToDeviceJob::class, function ($job) {
            return true;
        });
    });

    it('syncs to all devices at a location', function () {
        Queue::fake();

        $visitor = Visitor::factory()->withPhoto()->create();
        $location = WorkLocation::factory()->create();

        $device1 = BiometricDevice::factory()->online()->forWorkLocation($location)->create();
        $device2 = BiometricDevice::factory()->online()->forWorkLocation($location)->create();

        // Create an offline device that should not be synced
        BiometricDevice::factory()->offline()->forWorkLocation($location)->create();

        $service = app(VisitorDeviceSyncService::class);
        $syncs = $service->syncVisitorToLocationDevices($visitor, $location);

        expect($syncs)->toHaveCount(2);
        expect(VisitorDeviceSync::count())->toBe(2);

        $this->assertDatabaseHas('visitor_device_syncs', [
            'visitor_id' => $visitor->id,
            'biometric_device_id' => $device1->id,
        ]);
        $this->assertDatabaseHas('visitor_device_syncs', [
            'visitor_id' => $visitor->id,
            'biometric_device_id' => $device2->id,
        ]);
    });

    it('creates sync record with correct visitor and device IDs', function () {
        Queue::fake();

        $visitor = Visitor::factory()->withPhoto()->create();
        $location = WorkLocation::factory()->create();
        $device = BiometricDevice::factory()->online()->forWorkLocation($location)->create();

        $service = app(VisitorDeviceSyncService::class);
        $sync = $service->syncVisitorToDevice($visitor, $device);

        expect($sync->visitor_id)->toBe($visitor->id);
        expect($sync->biometric_device_id)->toBe($device->id);
        expect($sync->status)->toBe('pending');
        expect($sync->last_synced_at)->toBeNull();
        expect($sync->last_error)->toBeNull();
    });
});
