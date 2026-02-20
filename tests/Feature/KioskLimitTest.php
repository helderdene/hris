<?php

use App\Models\Kiosk;
use App\Models\Plan;
use App\Models\Tenant;
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

describe('Kiosk limit enforcement', function () {
    it('allows creating kiosk when under limit', function () {
        $plan = Plan::factory()->starter()->create();
        $plan->update(['limits' => array_merge($plan->limits, ['max_kiosks' => 1])]);
        $tenant = Tenant::factory()->withPlan($plan)->withTrial(14)->create();
        app()->instance('tenant', $tenant);

        // Starter has max_kiosks = 1, no kiosks created yet
        $gate = new FeatureGateService($tenant);

        expect($gate->isWithinKioskLimit())->toBeTrue();
    });

    it('blocks creating kiosk when at limit', function () {
        $plan = Plan::factory()->starter()->create();
        $plan->update(['limits' => array_merge($plan->limits, ['max_kiosks' => 1])]);
        $tenant = Tenant::factory()->withPlan($plan)->withTrial(14)->create();
        app()->instance('tenant', $tenant);

        // Starter has max_kiosks = 1
        Kiosk::factory()->create();

        $gate = new FeatureGateService($tenant);

        expect($gate->isWithinKioskLimit())->toBeFalse();
    });

    it('allows unlimited kiosks with -1 limit', function () {
        $plan = Plan::factory()->enterprise()->create();
        $plan->update(['limits' => array_merge($plan->limits, ['max_kiosks' => -1])]);
        $tenant = Tenant::factory()->withPlan($plan)->withTrial(14)->create();
        app()->instance('tenant', $tenant);

        Kiosk::factory()->count(20)->create();

        $gate = new FeatureGateService($tenant);

        expect($gate->isWithinKioskLimit())->toBeTrue();
    });

    it('professional plan allows 5 kiosks', function () {
        $plan = Plan::factory()->professional()->create();
        $plan->update(['limits' => array_merge($plan->limits, ['max_kiosks' => 5])]);
        $tenant = Tenant::factory()->withPlan($plan)->withTrial(14)->create();
        app()->instance('tenant', $tenant);

        Kiosk::factory()->count(4)->create();

        $gate = new FeatureGateService($tenant);
        expect($gate->isWithinKioskLimit())->toBeTrue();

        Kiosk::factory()->create();
        $gate = new FeatureGateService($tenant);
        expect($gate->isWithinKioskLimit())->toBeFalse();
    });

    it('returns true when plan has no kiosk limit defined', function () {
        $plan = Plan::factory()->starter()->create();
        $limits = $plan->limits;
        unset($limits['max_kiosks']);
        $plan->update(['limits' => $limits]);

        $tenant = Tenant::factory()->withPlan($plan)->withTrial(14)->create();
        app()->instance('tenant', $tenant);

        // No max_kiosks in plan limits, so getEffectiveLimit returns null (unlimited)
        Kiosk::factory()->count(10)->create();

        $gate = new FeatureGateService($tenant);

        expect($gate->isWithinKioskLimit())->toBeTrue();
    });
});
