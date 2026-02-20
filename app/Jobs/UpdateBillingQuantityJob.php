<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Models\Tenant;
use App\Services\Billing\PayMongoSubscriptionService;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sync tenant employee count to PayMongo subscription plan amount.
 */
class UpdateBillingQuantityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(public Tenant $tenant) {}

    /**
     * Execute the job.
     */
    public function handle(TenantDatabaseManager $databaseManager): void
    {
        $databaseManager->switchConnection($this->tenant);
        app()->instance('tenant', $this->tenant);

        $activeCount = Employee::where('employment_status', 'active')->count();

        $this->tenant->update(['employee_count_cache' => $activeCount]);

        $subscription = $this->tenant->subscription('default');
        if ($subscription && $subscription->active()) {
            try {
                $service = app(PayMongoSubscriptionService::class);
                $service->updateQuantity($subscription, $activeCount);
            } catch (\Throwable $e) {
                Log::error('Failed to update PayMongo quantity', [
                    'tenant_id' => $this->tenant->id,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        }
    }
}
