<?php

use App\Models\Tenant;
use App\Models\WorkLocation;
use App\Services\Kiosk\LocationVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('LocationVerificationService', function () {
    it('passes when location_check is none', function () {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $location = WorkLocation::factory()->create(['location_check' => 'none']);
        $service = app(LocationVerificationService::class);
        $request = Request::create('/test', 'POST');

        $result = $service->verify($request, $location, null);

        expect($result['passed'])->toBeTrue();
    });

    it('verifies IP matches CIDR range', function () {
        $service = app(LocationVerificationService::class);

        expect($service->verifyIp('192.168.1.50', ['192.168.1.0/24']))->toBeTrue();
        expect($service->verifyIp('10.0.0.1', ['192.168.1.0/24']))->toBeFalse();
    });

    it('verifies IP matches exact address', function () {
        $service = app(LocationVerificationService::class);

        expect($service->verifyIp('192.168.1.1', ['192.168.1.1']))->toBeTrue();
        expect($service->verifyIp('192.168.1.2', ['192.168.1.1']))->toBeFalse();
    });

    it('verifies GPS within geofence radius', function () {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $service = app(LocationVerificationService::class);

        // Makati coordinates
        $location = WorkLocation::factory()->create([
            'latitude' => 14.5547,
            'longitude' => 121.0244,
            'geofence_radius' => 500, // 500 meters
        ]);

        // Within radius (~100m away)
        expect($service->verifyGps(14.5557, 121.0244, 50.0, $location))->toBeTrue();

        // Outside radius (~2km away)
        expect($service->verifyGps(14.5747, 121.0244, 50.0, $location))->toBeFalse();
    });

    it('rejects GPS with accuracy greater than 200m', function () {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $service = app(LocationVerificationService::class);

        $location = WorkLocation::factory()->create([
            'latitude' => 14.5547,
            'longitude' => 121.0244,
            'geofence_radius' => 500,
        ]);

        // Even though coordinates are at the exact location, accuracy is too low
        expect($service->verifyGps(14.5547, 121.0244, 250.0, $location))->toBeFalse();
    });

    it('fails IP check when location_check is ip and IP not in whitelist', function () {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $location = WorkLocation::factory()->create([
            'location_check' => 'ip',
            'ip_whitelist' => ['10.0.0.0/8'],
        ]);

        $service = app(LocationVerificationService::class);
        $request = Request::create('/test', 'POST', [], [], [], ['REMOTE_ADDR' => '192.168.1.1']);

        $result = $service->verify($request, $location, null);

        expect($result['passed'])->toBeFalse();
    });

    it('passes IP check when IP is in whitelist', function () {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $location = WorkLocation::factory()->create([
            'location_check' => 'ip',
            'ip_whitelist' => ['127.0.0.0/8'],
        ]);

        $service = app(LocationVerificationService::class);
        $request = Request::create('/test', 'POST');

        $result = $service->verify($request, $location, null);

        expect($result['passed'])->toBeTrue();
    });
});
