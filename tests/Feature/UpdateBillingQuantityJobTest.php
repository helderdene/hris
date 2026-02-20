<?php

use App\Jobs\UpdateBillingQuantityJob;
use App\Models\Employee;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\PayMongoSubscriptionService;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('UpdateBillingQuantityJob', function () {
    it('updates employee_count_cache on tenant', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();
        app()->instance('tenant', $tenant);

        Employee::factory()->count(5)->create(['employment_status' => 'active']);
        Employee::factory()->count(2)->create(['employment_status' => 'resigned']);

        $databaseManager = mock(TenantDatabaseManager::class);
        $databaseManager->shouldReceive('switchConnection')->once()->with($tenant);

        $job = new UpdateBillingQuantityJob($tenant);
        $job->handle($databaseManager);

        $tenant->refresh();
        expect($tenant->employee_count_cache)->toBe(5);
    });

    it('updates cache and subscription quantity for active subscription', function () {
        // Prevent observer from dispatching jobs during factory creation
        Employee::withoutEvents(function () {
            $plan = Plan::factory()->starter()->create();
            $tenant = Tenant::factory()->withPlan($plan)->create();
            app()->instance('tenant', $tenant);

            $price = PlanPrice::factory()->create(['plan_id' => $plan->id]);
            $subscription = Subscription::factory()->active()->create([
                'tenant_id' => $tenant->id,
                'name' => 'default',
                'plan_price_id' => $price->id,
                'quantity' => 1,
            ]);

            Employee::factory()->count(3)->create(['employment_status' => 'active']);

            test()->tenant = $tenant;
            test()->subscription = $subscription;
        });

        $tenant = test()->tenant;
        $subscription = test()->subscription;

        $databaseManager = mock(TenantDatabaseManager::class);
        $databaseManager->shouldReceive('switchConnection')->once();

        // Use a full Mockery mock for the service
        $serviceMock = Mockery::mock(PayMongoSubscriptionService::class);
        $serviceMock->shouldReceive('updateQuantity')
            ->once()
            ->with(
                Mockery::on(fn ($sub) => $sub->id === $subscription->id),
                3
            );
        app()->instance(PayMongoSubscriptionService::class, $serviceMock);

        $job = new UpdateBillingQuantityJob($tenant);
        $job->handle($databaseManager);

        $tenant->refresh();
        expect($tenant->employee_count_cache)->toBe(3);
    });

    it('handles missing subscription gracefully', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();
        app()->instance('tenant', $tenant);

        Employee::factory()->count(2)->create(['employment_status' => 'active']);

        $databaseManager = mock(TenantDatabaseManager::class);
        $databaseManager->shouldReceive('switchConnection')->once();

        $job = new UpdateBillingQuantityJob($tenant);
        $job->handle($databaseManager);

        $tenant->refresh();
        expect($tenant->employee_count_cache)->toBe(2);
    });
});
