<?php

use App\Models\Plan;
use App\Models\PlanPrice;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('PlanSeeder', function () {
    it('creates 3 plans', function () {
        $this->seed(PlanSeeder::class);

        expect(Plan::count())->toBe(3);
    });

    it('creates starter plan with 10 modules', function () {
        $this->seed(PlanSeeder::class);

        $plan = Plan::where('slug', 'starter')->first();
        expect($plan)->not->toBeNull();
        expect($plan->modules)->toHaveCount(10);
    });

    it('creates professional plan with 18 modules', function () {
        $this->seed(PlanSeeder::class);

        $plan = Plan::where('slug', 'professional')->first();
        expect($plan)->not->toBeNull();
        expect($plan->modules)->toHaveCount(18);
    });

    it('creates enterprise plan with 22 modules', function () {
        $this->seed(PlanSeeder::class);

        $plan = Plan::where('slug', 'enterprise')->first();
        expect($plan)->not->toBeNull();
        expect($plan->modules)->toHaveCount(22);
    });

    it('creates correct prices for starter', function () {
        $this->seed(PlanSeeder::class);

        $plan = Plan::where('slug', 'starter')->first();
        $monthly = $plan->prices->where('billing_interval', 'monthly')->first();
        $yearly = $plan->prices->where('billing_interval', 'yearly')->first();

        expect($monthly->price_per_unit)->toBe(5000);
        expect($yearly->price_per_unit)->toBe(50000);
    });

    it('creates correct prices for professional', function () {
        $this->seed(PlanSeeder::class);

        $plan = Plan::where('slug', 'professional')->first();
        $monthly = $plan->prices->where('billing_interval', 'monthly')->first();
        $yearly = $plan->prices->where('billing_interval', 'yearly')->first();

        expect($monthly->price_per_unit)->toBe(10000);
        expect($yearly->price_per_unit)->toBe(100000);
    });

    it('creates correct prices for enterprise', function () {
        $this->seed(PlanSeeder::class);

        $plan = Plan::where('slug', 'enterprise')->first();
        $monthly = $plan->prices->where('billing_interval', 'monthly')->first();
        $yearly = $plan->prices->where('billing_interval', 'yearly')->first();

        expect($monthly->price_per_unit)->toBe(15000);
        expect($yearly->price_per_unit)->toBe(150000);
    });

    it('is idempotent on re-run', function () {
        $this->seed(PlanSeeder::class);
        $this->seed(PlanSeeder::class);

        expect(Plan::count())->toBe(3);
        expect(PlanPrice::count())->toBe(6);

        $starter = Plan::where('slug', 'starter')->first();
        expect($starter->modules)->toHaveCount(10);
    });

    it('sets correct limits for starter', function () {
        $this->seed(PlanSeeder::class);

        $plan = Plan::where('slug', 'starter')->first();
        expect($plan->getLimit('max_employees'))->toBe(50);
        expect($plan->getLimit('max_admin_users'))->toBe(3);
        expect($plan->getLimit('max_biometric_devices'))->toBe(2);
    });

    it('sets unlimited limits for enterprise', function () {
        $this->seed(PlanSeeder::class);

        $plan = Plan::where('slug', 'enterprise')->first();
        expect($plan->getLimit('max_employees'))->toBe(-1);
        expect($plan->getLimit('max_admin_users'))->toBe(-1);
        expect($plan->getLimit('max_biometric_devices'))->toBe(-1);
    });
});
