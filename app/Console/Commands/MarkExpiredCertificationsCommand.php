<?php

namespace App\Console\Commands;

use App\Enums\CertificationStatus;
use App\Models\Certification;
use App\Models\Tenant;
use App\Notifications\CertificationExpired;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MarkExpiredCertificationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'certifications:mark-expired
                            {--tenant= : Specific tenant ID to process}
                            {--notify : Send notification to employees when certifications expire}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark active certifications as expired when their expiry date has passed';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $shouldNotify = $this->option('notify');

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found to process.');

            return self::SUCCESS;
        }

        $totalExpired = 0;

        foreach ($tenants as $tenant) {
            $this->info("Processing tenant: {$tenant->name}");

            // Set tenant context
            app()->instance('tenant', $tenant);

            // Switch to tenant database
            $this->switchToTenantDatabase($tenant);

            $expiredCount = $this->markExpiredForTenant($shouldNotify);
            $totalExpired += $expiredCount;

            if ($expiredCount > 0) {
                $this->info("  Marked {$expiredCount} certification(s) as expired");
            }

            // Reset to default connection
            DB::purge('tenant');
        }

        $this->info("Total certifications marked as expired: {$totalExpired}");

        return self::SUCCESS;
    }

    /**
     * Mark expired certifications for a single tenant.
     */
    protected function markExpiredForTenant(bool $shouldNotify): int
    {
        $expiredCount = 0;

        // Get active certifications that have passed their expiry date
        $certifications = Certification::query()
            ->where('status', CertificationStatus::Active)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->startOfDay())
            ->with(['employee.user', 'certificationType'])
            ->get();

        foreach ($certifications as $certification) {
            $certification->markAsExpired();
            $expiredCount++;

            $this->info("  Marked as expired: {$certification->employee->full_name} - {$certification->certificationType->name}");

            // Send notification if requested
            if ($shouldNotify && $certification->employee?->user) {
                $certification->employee->user->notify(new CertificationExpired($certification));
            }
        }

        return $expiredCount;
    }

    /**
     * Switch to tenant's database.
     */
    protected function switchToTenantDatabase(Tenant $tenant): void
    {
        config([
            'database.connections.tenant.database' => $tenant->database_name,
        ]);

        DB::reconnect('tenant');
    }
}
