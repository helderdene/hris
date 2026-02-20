<?php

use App\Enums\TenantUserRole;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FeatureGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('User limit enforcement', function () {
    it('allows inviting user when under limit', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->withTrial(14)->create();
        app()->instance('tenant', $tenant);

        // Starter limit is 3 admin users, attach 1
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id, [
            'role' => TenantUserRole::Admin->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        $gate = new FeatureGateService($tenant);

        expect($gate->isWithinUserLimit())->toBeTrue();
    });

    it('blocks inviting user when at admin limit', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->withTrial(14)->create();
        app()->instance('tenant', $tenant);

        // Starter limit is 3 admin users — fill all 3
        foreach (range(1, 3) as $i) {
            $user = User::factory()->create();
            $user->tenants()->attach($tenant->id, [
                'role' => TenantUserRole::Admin->value,
                'invited_at' => now(),
                'invitation_accepted_at' => now(),
            ]);
        }

        $gate = new FeatureGateService($tenant);

        expect($gate->isWithinUserLimit())->toBeFalse();
    });

    it('counts HR roles toward admin limit', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->withTrial(14)->create();
        app()->instance('tenant', $tenant);

        // Mix of admin/HR roles — all count
        $roles = [TenantUserRole::Admin, TenantUserRole::HrManager, TenantUserRole::HrStaff];
        foreach ($roles as $role) {
            $user = User::factory()->create();
            $user->tenants()->attach($tenant->id, [
                'role' => $role->value,
                'invited_at' => now(),
                'invitation_accepted_at' => now(),
            ]);
        }

        $gate = new FeatureGateService($tenant);

        expect($gate->isWithinUserLimit())->toBeFalse();
    });

    it('does not count employee role toward admin limit', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->withTrial(14)->create();
        app()->instance('tenant', $tenant);

        // 2 admins + 5 employees = still under 3 admin limit
        foreach (range(1, 2) as $i) {
            $user = User::factory()->create();
            $user->tenants()->attach($tenant->id, [
                'role' => TenantUserRole::Admin->value,
                'invited_at' => now(),
                'invitation_accepted_at' => now(),
            ]);
        }
        foreach (range(1, 5) as $i) {
            $user = User::factory()->create();
            $user->tenants()->attach($tenant->id, [
                'role' => TenantUserRole::Employee->value,
                'invited_at' => now(),
                'invitation_accepted_at' => now(),
            ]);
        }

        $gate = new FeatureGateService($tenant);

        expect($gate->isWithinUserLimit())->toBeTrue();
    });

    it('allows unlimited users with -1 limit', function () {
        $plan = Plan::factory()->enterprise()->create();
        $tenant = Tenant::factory()->withPlan($plan)->withTrial(14)->create();
        app()->instance('tenant', $tenant);

        foreach (range(1, 20) as $i) {
            $user = User::factory()->create();
            $user->tenants()->attach($tenant->id, [
                'role' => TenantUserRole::Admin->value,
                'invited_at' => now(),
                'invitation_accepted_at' => now(),
            ]);
        }

        $gate = new FeatureGateService($tenant);

        expect($gate->isWithinUserLimit())->toBeTrue();
    });
});
