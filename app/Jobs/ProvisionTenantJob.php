<?php

namespace App\Jobs;

use App\Enums\TenantProvisioningStatus;
use App\Models\Tenant;
use App\Services\Billing\PayMongoCustomerService;
use App\Services\Tenant\TenantDatabaseManager;
use Database\Seeders\DocumentCategorySeeder;
use Database\Seeders\GovernmentContributionSeeder;
use Database\Seeders\HelpContentSeeder;
use Database\Seeders\ProficiencyLevelSeeder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job to provision a newly registered tenant's schema and seed data.
 *
 * The admin user is attached and the provisioning status cleared only at
 * the end, so until then the tenant is skipped by per-tenant scheduled work.
 */
class ProvisionTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 900;

    /**
     * Create a new job instance.
     *
     * @param  int  $tenantId  The tenant to provision
     * @param  int  $adminUserId  The user who becomes the tenant admin
     */
    public function __construct(
        public int $tenantId,
        public int $adminUserId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(TenantDatabaseManager $databaseManager): void
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            Log::warning('ProvisionTenantJob: Tenant not found', [
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        $databaseManager->createSchema($tenant);
        $databaseManager->migrateSchema($tenant);

        (new HelpContentSeeder)->run();
        (new DocumentCategorySeeder)->run();
        (new GovernmentContributionSeeder)->run();
        (new ProficiencyLevelSeeder)->run();

        try {
            app(PayMongoCustomerService::class)->createOrGet($tenant);
        } catch (Throwable $e) {
            Log::warning('Failed to create PayMongo customer during registration', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }

        $tenant->users()->syncWithoutDetaching([
            $this->adminUserId => ['role' => 'admin'],
        ]);

        $tenant->update(['provisioning_status' => null]);
    }

    /**
     * Mark the tenant as failed so its owner can retry from the registration page.
     */
    public function failed(?Throwable $exception): void
    {
        Tenant::whereKey($this->tenantId)
            ->where('provisioning_status', TenantProvisioningStatus::Pending)
            ->update(['provisioning_status' => TenantProvisioningStatus::Failed]);

        Log::error('ProvisionTenantJob failed', [
            'tenant_id' => $this->tenantId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
