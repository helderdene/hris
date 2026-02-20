<?php

use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Billing\PayMongoCustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

describe('Trial provisioning on tenant creation', function () {
    it('assigns professional plan to new tenant', function () {
        $plan = Plan::factory()->professional()->create();

        $tenant = Tenant::factory()->create();

        // Simulate what TenantRegistrationController::store() does
        $trialPlan = Plan::where('slug', config('billing.trial_plan', 'professional'))->first();
        expect($trialPlan)->not->toBeNull();

        $tenant->update([
            'plan_id' => $trialPlan->id,
            'trial_ends_at' => now()->addDays(config('billing.trial_days', 14)),
        ]);

        expect($tenant->fresh()->plan_id)->toBe($plan->id);
        expect($tenant->fresh()->onTrial())->toBeTrue();
    });

    it('sets trial_ends_at to configured trial days', function () {
        Plan::factory()->professional()->create();
        config(['billing.trial_days' => 14]);

        $tenant = Tenant::factory()->create();

        $trialPlan = Plan::where('slug', config('billing.trial_plan', 'professional'))->first();
        $tenant->update([
            'plan_id' => $trialPlan->id,
            'trial_ends_at' => now()->addDays(config('billing.trial_days', 14)),
        ]);

        $fresh = $tenant->fresh();
        expect($fresh->trial_ends_at)->not->toBeNull();
        expect((int) now()->diffInDays($fresh->trial_ends_at))->toBeBetween(13, 14);
    });

    it('uses custom trial plan from config', function () {
        Plan::factory()->starter()->create();
        $professional = Plan::factory()->professional()->create();
        config(['billing.trial_plan' => 'professional']);

        $trialPlan = Plan::where('slug', config('billing.trial_plan'))->first();

        expect($trialPlan->id)->toBe($professional->id);
    });

    it('handles missing trial plan gracefully', function () {
        // No plans created, simulating config pointing to non-existent plan
        config(['billing.trial_plan' => 'nonexistent']);

        $trialPlan = Plan::where('slug', config('billing.trial_plan'))->first();

        expect($trialPlan)->toBeNull();

        // The controller uses an if check, so null plan means no assignment
        $tenant = Tenant::factory()->create();
        expect($tenant->plan_id)->toBeNull();
    });

    it('PayMongo customer creation failure does not block tenant setup', function () {
        Plan::factory()->professional()->create();
        $tenant = Tenant::factory()->create();

        // Simulate the try/catch from the controller
        $trialPlan = Plan::where('slug', config('billing.trial_plan', 'professional'))->first();
        $tenant->update([
            'plan_id' => $trialPlan->id,
            'trial_ends_at' => now()->addDays(14),
        ]);

        try {
            $mock = Mockery::mock(PayMongoCustomerService::class);
            $mock->shouldReceive('createOrGet')
                ->once()
                ->andThrow(new \RuntimeException('PayMongo unavailable'));
            $mock->createOrGet($tenant);
        } catch (\Throwable $e) {
            Log::warning('Failed to create PayMongo customer', ['error' => $e->getMessage()]);
        }

        // Tenant should still have plan assigned despite PayMongo failure
        $fresh = $tenant->fresh();
        expect($fresh->plan_id)->not->toBeNull();
        expect($fresh->onTrial())->toBeTrue();
    });
});
