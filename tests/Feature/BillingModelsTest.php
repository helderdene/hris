<?php

use App\Enums\AddonType;
use App\Enums\Module;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\PlanModule;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantAddon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Plan model', function () {
    it('can be created with factory', function () {
        $plan = Plan::factory()->create();

        expect($plan)->toBeInstanceOf(Plan::class);
        expect($plan->is_active)->toBeTrue();
        expect($plan->is_custom)->toBeFalse();
        expect($plan->limits)->toBeArray();
    });

    it('has prices relationship', function () {
        $plan = Plan::factory()->create();
        PlanPrice::factory()->create(['plan_id' => $plan->id]);

        expect($plan->prices)->toHaveCount(1);
    });

    it('has modules relationship', function () {
        $plan = Plan::factory()->starter()->create();

        expect($plan->modules)->toHaveCount(10);
    });

    it('checks if plan has a module', function () {
        $plan = Plan::factory()->starter()->create();

        expect($plan->hasModule(Module::HrManagement))->toBeTrue();
        expect($plan->hasModule(Module::Recruitment))->toBeFalse();
    });

    it('gets limit value', function () {
        $plan = Plan::factory()->starter()->create();

        expect($plan->getLimit('max_employees'))->toBe(50);
        expect($plan->getLimit('nonexistent', 'default'))->toBe('default');
    });

    it('scopes to active plans', function () {
        Plan::factory()->create(['is_active' => true]);
        Plan::factory()->create(['slug' => 'inactive-plan', 'is_active' => false]);

        expect(Plan::active()->count())->toBe(1);
    });

    it('scopes to standard plans', function () {
        Plan::factory()->create(['is_custom' => false]);
        Plan::factory()->custom()->create();

        expect(Plan::standard()->count())->toBe(1);
    });

    it('has tenants relationship', function () {
        $plan = Plan::factory()->create();
        Tenant::factory()->withPlan($plan)->create();

        expect($plan->tenants)->toHaveCount(1);
    });
});

describe('PlanPrice model', function () {
    it('belongs to a plan', function () {
        $price = PlanPrice::factory()->create();

        expect($price->plan)->toBeInstanceOf(Plan::class);
    });

    it('casts is_active to boolean', function () {
        $price = PlanPrice::factory()->create(['is_active' => true]);

        expect($price->is_active)->toBeBool();
    });
});

describe('PlanModule model', function () {
    it('belongs to a plan', function () {
        $plan = Plan::factory()->create();
        $module = PlanModule::create([
            'plan_id' => $plan->id,
            'module' => Module::HrManagement->value,
        ]);

        expect($module->plan)->toBeInstanceOf(Plan::class);
    });
});

describe('Subscription model', function () {
    it('can be created with factory', function () {
        $subscription = Subscription::factory()->create();

        expect($subscription)->toBeInstanceOf(Subscription::class);
    });

    it('casts paymongo_status to enum', function () {
        $subscription = Subscription::factory()->create();

        expect($subscription->paymongo_status)->toBeInstanceOf(SubscriptionStatus::class);
    });

    it('belongs to a tenant', function () {
        $subscription = Subscription::factory()->create();

        expect($subscription->tenant)->toBeInstanceOf(Tenant::class);
    });

    it('belongs to a plan price', function () {
        $subscription = Subscription::factory()->create();

        expect($subscription->planPrice)->toBeInstanceOf(PlanPrice::class);
    });

    it('identifies active subscription', function () {
        $subscription = Subscription::factory()->active()->create();

        expect($subscription->active())->toBeTrue();
        expect($subscription->cancelled())->toBeFalse();
    });

    it('identifies cancelled subscription on grace period', function () {
        $subscription = Subscription::factory()->cancelled()->create();

        expect($subscription->cancelled())->toBeTrue();
        expect($subscription->onGracePeriod())->toBeTrue();
        expect($subscription->active())->toBeTrue();
    });

    it('identifies expired subscription', function () {
        $subscription = Subscription::factory()->expired()->create();

        expect($subscription->active())->toBeFalse();
        expect($subscription->cancelled())->toBeTrue();
        expect($subscription->onGracePeriod())->toBeFalse();
    });
});

describe('TenantAddon model', function () {
    it('can be created with factory', function () {
        $addon = TenantAddon::factory()->create();

        expect($addon)->toBeInstanceOf(TenantAddon::class);
    });

    it('casts type to enum', function () {
        $addon = TenantAddon::factory()->employeeSlots()->create();

        expect($addon->type)->toBe(AddonType::EmployeeSlots);
    });

    it('calculates extra units for employee slots', function () {
        $addon = TenantAddon::factory()->employeeSlots(3)->create();

        expect($addon->extraUnits())->toBe(30); // 3 × 10
    });

    it('calculates extra units for biometric devices', function () {
        $addon = TenantAddon::factory()->biometricDevices(5)->create();

        expect($addon->extraUnits())->toBe(5); // 5 × 1
    });

    it('calculates monthly cost', function () {
        $addon = TenantAddon::factory()->employeeSlots(2)->create();

        expect($addon->monthlyCost())->toBe(5000); // 2 × 2500
    });

    it('scopes to active addons', function () {
        $tenant = Tenant::factory()->create();
        TenantAddon::factory()->employeeSlots()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        TenantAddon::factory()->employeeSlots()->create(['tenant_id' => $tenant->id, 'is_active' => false]);
        TenantAddon::factory()->employeeSlots()->expired()->create(['tenant_id' => $tenant->id]);

        expect(TenantAddon::active()->count())->toBe(1);
    });

    it('belongs to a tenant', function () {
        $addon = TenantAddon::factory()->create();

        expect($addon->tenant)->toBeInstanceOf(Tenant::class);
    });
});
