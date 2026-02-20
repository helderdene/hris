<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Jobs\UpdateBillingQuantityJob;
use App\Models\Tenant;
use Illuminate\Console\Command;

class SyncBillingQuantitiesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:sync-quantities
                            {--tenant= : Specific tenant ID to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync employee counts and update PayMongo plan amounts for all active subscriptions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        if ($tenantId) {
            $tenants = Tenant::where('id', $tenantId)->get();
        } else {
            $tenants = Tenant::where(function ($query) {
                $query->whereHas('subscriptions', fn ($q) => $q->where('paymongo_status', SubscriptionStatus::Active->value))
                    ->orWhereNotNull('trial_ends_at');
            })->get();
        }

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found to process.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            UpdateBillingQuantityJob::dispatch($tenant);
            $this->info("Dispatched sync for: {$tenant->name}");
        }

        $this->info("Dispatched {$tenants->count()} billing sync job(s).");

        return self::SUCCESS;
    }
}
