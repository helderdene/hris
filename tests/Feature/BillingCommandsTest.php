<?php

use App\Enums\TenantUserRole;
use App\Jobs\UpdateBillingQuantityJob;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TrialExpiredNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

describe('billing:sync-quantities command', function () {
    it('dispatches jobs for tenants with active subscriptions', function () {
        Bus::fake();

        $plan = Plan::factory()->starter()->create();
        $price = PlanPrice::factory()->create(['plan_id' => $plan->id]);

        $tenant = Tenant::factory()->withPlan($plan)->create();
        Subscription::factory()->active()->create([
            'tenant_id' => $tenant->id,
            'name' => 'default',
            'plan_price_id' => $price->id,
        ]);

        // Tenant without subscription and no trial should not get a job
        Tenant::factory()->create(['trial_ends_at' => null]);

        $this->artisan('billing:sync-quantities')
            ->assertSuccessful();

        Bus::assertDispatched(UpdateBillingQuantityJob::class, 1);
    });

    it('dispatches jobs for tenants on trial', function () {
        Bus::fake();

        $plan = Plan::factory()->starter()->create();
        Tenant::factory()->withPlan($plan)->withTrial(14)->create();

        $this->artisan('billing:sync-quantities')
            ->assertSuccessful();

        Bus::assertDispatched(UpdateBillingQuantityJob::class, 1);
    });

    it('filters by tenant ID', function () {
        Bus::fake();

        $plan = Plan::factory()->starter()->create();
        $target = Tenant::factory()->withPlan($plan)->withTrial(14)->create();
        Tenant::factory()->withPlan($plan)->withTrial(14)->create();

        $this->artisan("billing:sync-quantities --tenant={$target->id}")
            ->assertSuccessful();

        Bus::assertDispatched(UpdateBillingQuantityJob::class, 1);
    });
});

describe('billing:check-expired-trials command', function () {
    it('sends notifications to admin users of expired trials', function () {
        Notification::fake();

        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->withExpiredTrial()->create();

        $admin = User::factory()->create();
        $admin->tenants()->attach($tenant->id, [
            'role' => TenantUserRole::Admin->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        $this->artisan('billing:check-expired-trials')
            ->assertSuccessful();

        Notification::assertSentTo($admin, TrialExpiredNotification::class);
    });

    it('skips already-notified tenants', function () {
        Notification::fake();

        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->withExpiredTrial()->create([
            'trial_expired_notified_at' => now()->subDay(),
        ]);

        $admin = User::factory()->create();
        $admin->tenants()->attach($tenant->id, [
            'role' => TenantUserRole::Admin->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        $this->artisan('billing:check-expired-trials')
            ->assertSuccessful();

        Notification::assertNothingSent();
    });

    it('skips tenants with active subscription', function () {
        Notification::fake();

        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->withExpiredTrial()->create();
        $price = PlanPrice::factory()->create(['plan_id' => $plan->id]);
        Subscription::factory()->active()->create([
            'tenant_id' => $tenant->id,
            'name' => 'default',
            'plan_price_id' => $price->id,
        ]);

        $admin = User::factory()->create();
        $admin->tenants()->attach($tenant->id, [
            'role' => TenantUserRole::Admin->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        $this->artisan('billing:check-expired-trials')
            ->assertSuccessful();

        Notification::assertNothingSent();
    });

    it('marks tenant as notified after sending', function () {
        Notification::fake();

        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->withExpiredTrial()->create();

        $admin = User::factory()->create();
        $admin->tenants()->attach($tenant->id, [
            'role' => TenantUserRole::Admin->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        $this->artisan('billing:check-expired-trials')
            ->assertSuccessful();

        $tenant->refresh();
        expect($tenant->trial_expired_notified_at)->not->toBeNull();
    });
});
