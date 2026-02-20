<?php

use App\Models\BiometricDevice;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Services\FeatureGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('Biometric device limit enforcement', function () {
    it('allows creating device when under limit', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->withTrial(14)->create();
        app()->instance('tenant', $tenant);

        BiometricDevice::factory()->create();

        $gate = new FeatureGateService($tenant);

        expect($gate->isWithinDeviceLimit())->toBeTrue();
    });

    it('blocks creating device when at limit', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->withTrial(14)->create();
        app()->instance('tenant', $tenant);

        BiometricDevice::factory()->count(2)->create();

        $gate = new FeatureGateService($tenant);

        expect($gate->isWithinDeviceLimit())->toBeFalse();
    });

    it('allows unlimited devices with -1 limit', function () {
        $plan = Plan::factory()->enterprise()->create();
        $tenant = Tenant::factory()->withPlan($plan)->withTrial(14)->create();
        app()->instance('tenant', $tenant);

        BiometricDevice::factory()->count(50)->create();

        $gate = new FeatureGateService($tenant);

        expect($gate->isWithinDeviceLimit())->toBeTrue();
    });

    it('increases limit with active device add-on', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->withTrial(14)->create();
        app()->instance('tenant', $tenant);

        TenantAddon::factory()->biometricDevices(1)->create(['tenant_id' => $tenant->id]);

        BiometricDevice::factory()->count(2)->create();

        $gate = new FeatureGateService($tenant);

        expect($gate->getEffectiveLimit('max_biometric_devices'))->toBe(3);
        expect($gate->isWithinDeviceLimit())->toBeTrue();
    });
});
