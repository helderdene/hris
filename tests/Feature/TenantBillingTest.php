<?php

use App\Enums\Module;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantAddon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Tenant trial states', function () {
    it('detects tenant on trial', function () {
        $tenant = Tenant::factory()->withTrial(14)->create();

        expect($tenant->onTrial())->toBeTrue();
        expect($tenant->trialExpired())->toBeFalse();
        expect($tenant->hasActiveAccess())->toBeTrue();
    });

    it('detects expired trial', function () {
        $tenant = Tenant::factory()->withExpiredTrial()->create();

        expect($tenant->onTrial())->toBeFalse();
        expect($tenant->trialExpired())->toBeTrue();
        expect($tenant->hasActiveAccess())->toBeFalse();
    });

    it('detects no trial set', function () {
        $tenant = Tenant::factory()->create(['trial_ends_at' => null]);

        expect($tenant->onTrial())->toBeFalse();
        expect($tenant->trialExpired())->toBeFalse();
    });
});

describe('Tenant subscription states', function () {
    it('detects active subscription', function () {
        $tenant = Tenant::factory()->create();
        Subscription::factory()->active()->create([
            'tenant_id' => $tenant->id,
            'name' => 'default',
        ]);

        expect($tenant->subscribed())->toBeTrue();
        expect($tenant->hasActiveAccess())->toBeTrue();
    });

    it('detects no subscription', function () {
        $tenant = Tenant::factory()->create();

        expect($tenant->subscribed())->toBeFalse();
    });

    it('returns subscription by name', function () {
        $tenant = Tenant::factory()->create();
        Subscription::factory()->active()->create([
            'tenant_id' => $tenant->id,
            'name' => 'default',
        ]);

        expect($tenant->subscription('default'))->toBeInstanceOf(Subscription::class);
        expect($tenant->subscription('nonexistent'))->toBeNull();
    });

    it('has active access with subscription but expired trial', function () {
        $tenant = Tenant::factory()->withExpiredTrial()->create();
        Subscription::factory()->active()->create([
            'tenant_id' => $tenant->id,
            'name' => 'default',
        ]);

        expect($tenant->hasActiveAccess())->toBeTrue();
    });
});

describe('Tenant module access', function () {
    it('has module when plan includes it', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();

        expect($tenant->hasModule(Module::HrManagement))->toBeTrue();
        expect($tenant->hasModule(Module::Recruitment))->toBeFalse();
    });

    it('has no modules when no plan assigned', function () {
        $tenant = Tenant::factory()->create(['plan_id' => null]);

        expect($tenant->hasModule(Module::HrManagement))->toBeFalse();
        expect($tenant->availableModules())->toBeEmpty();
    });

    it('returns available module values', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();

        $modules = $tenant->availableModules();
        expect($modules)->toHaveCount(10);
        expect($modules)->toContain('hr_management');
    });
});

describe('Tenant effective limits with addons', function () {
    it('returns base plan limit when no addons', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();

        expect($tenant->effectiveLimit('max_employees'))->toBe(50);
    });

    it('returns null when no plan assigned', function () {
        $tenant = Tenant::factory()->create(['plan_id' => null]);

        expect($tenant->effectiveLimit('max_employees'))->toBeNull();
    });

    it('returns unlimited (-1) for enterprise limits', function () {
        $plan = Plan::factory()->enterprise()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();

        expect($tenant->effectiveLimit('max_employees'))->toBe(-1);
    });

    it('adds employee addon extra units to base limit', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();
        TenantAddon::factory()->employeeSlots(2)->create(['tenant_id' => $tenant->id]);

        expect($tenant->effectiveLimit('max_employees'))->toBe(70); // 50 + (2 × 10)
    });

    it('adds biometric device addon extra units to base limit', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();
        TenantAddon::factory()->biometricDevices(3)->create(['tenant_id' => $tenant->id]);

        expect($tenant->effectiveLimit('max_biometric_devices'))->toBe(5); // 2 + 3
    });

    it('ignores inactive addons in effective limit', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();
        TenantAddon::factory()->employeeSlots(2)->inactive()->create(['tenant_id' => $tenant->id]);

        expect($tenant->effectiveLimit('max_employees'))->toBe(50); // no addon effect
    });

    it('ignores expired addons in effective limit', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();
        TenantAddon::factory()->employeeSlots(2)->expired()->create(['tenant_id' => $tenant->id]);

        expect($tenant->effectiveLimit('max_employees'))->toBe(50); // no addon effect
    });

    it('returns base limit for non-addon limit keys', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();

        expect($tenant->effectiveLimit('max_admin_users'))->toBe(3);
    });
});

describe('Tenant addon relationships', function () {
    it('has addons relationship', function () {
        $tenant = Tenant::factory()->create();
        TenantAddon::factory()->employeeSlots()->create(['tenant_id' => $tenant->id]);

        expect($tenant->addons)->toHaveCount(1);
    });

    it('has active addons relationship', function () {
        $tenant = Tenant::factory()->create();
        TenantAddon::factory()->employeeSlots()->create(['tenant_id' => $tenant->id]);
        TenantAddon::factory()->employeeSlots()->inactive()->create(['tenant_id' => $tenant->id]);

        expect($tenant->activeAddons)->toHaveCount(1);
    });
});
