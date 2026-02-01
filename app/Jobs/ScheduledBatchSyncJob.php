<?php

namespace App\Jobs;

use App\Enums\SyncStatus;
use App\Models\EmployeeDeviceSync;
use App\Models\Tenant;
use App\Services\Biometric\EmployeeSyncService;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled job to process pending syncs across all tenants.
 *
 * Runs periodically to catch and retry failed syncs with exponential backoff.
 */
class ScheduledBatchSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * Maximum number of sync records to process per tenant per run.
     */
    protected int $batchSize = 50;

    /**
     * Execute the job.
     */
    public function handle(
        TenantDatabaseManager $databaseManager,
        EmployeeSyncService $syncService
    ): void {
        $tenants = Tenant::all();

        Log::info('ScheduledBatchSyncJob starting', [
            'tenant_count' => $tenants->count(),
        ]);

        foreach ($tenants as $tenant) {
            $this->processTenantsync($tenant, $databaseManager, $syncService);
        }

        Log::info('ScheduledBatchSyncJob completed');
    }

    /**
     * Process pending syncs for a single tenant.
     */
    protected function processTenantsync(
        Tenant $tenant,
        TenantDatabaseManager $databaseManager,
        EmployeeSyncService $syncService
    ): void {
        try {
            $databaseManager->switchConnection($tenant);
            app()->instance('tenant', $tenant);

            $pendingSyncs = EmployeeDeviceSync::query()
                ->needsSync()
                ->where(function ($query) {
                    // Only retry failed syncs after backoff period
                    $query->where('status', SyncStatus::Pending)
                        ->orWhere(function ($q) {
                            $q->where('status', SyncStatus::Failed)
                                ->where('last_attempted_at', '<', $this->getRetryThreshold());
                        });
                })
                ->with(['employee', 'biometricDevice'])
                ->limit($this->batchSize)
                ->get();

            if ($pendingSyncs->isEmpty()) {
                return;
            }

            Log::info('Processing pending syncs for tenant', [
                'tenant_id' => $tenant->id,
                'pending_count' => $pendingSyncs->count(),
            ]);

            foreach ($pendingSyncs as $sync) {
                $this->processSync($sync, $syncService);
            }
        } catch (\Throwable $e) {
            Log::error('Error processing tenant syncs', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process a single sync record.
     */
    protected function processSync(
        EmployeeDeviceSync $sync,
        EmployeeSyncService $syncService
    ): void {
        $employee = $sync->employee;
        $device = $sync->biometricDevice;

        if ($employee === null || $device === null) {
            Log::warning('Skipping sync: missing employee or device', [
                'sync_id' => $sync->id,
            ]);

            return;
        }

        if (! $device->is_active) {
            Log::info('Skipping sync: device is inactive', [
                'sync_id' => $sync->id,
                'device_id' => $device->id,
            ]);

            return;
        }

        $syncService->processPendingSync($employee, $device);
    }

    /**
     * Get the retry threshold based on retry count and exponential backoff.
     *
     * Backoff schedule: 1min, 5min, 15min, 1hour, 4hours
     */
    protected function getRetryThreshold(): \Carbon\Carbon
    {
        // For scheduled job, use a conservative 15-minute window
        return now()->subMinutes(15);
    }
}
