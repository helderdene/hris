<?php

namespace App\Jobs;

use App\Models\BiometricDevice;
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
 * Job to sync multiple employees to a biometric device.
 *
 * Can sync all employees at a work location or a specific subset.
 */
class BulkSyncEmployeesToDeviceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int>
     */
    public array $backoff = [60, 300, 900];

    /**
     * Create a new job instance.
     *
     * @param  array<int>|null  $employeeIds  Specific employee IDs to sync, or null for all at location
     */
    public function __construct(
        public int $deviceId,
        public int $tenantId,
        public ?array $employeeIds = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        TenantDatabaseManager $databaseManager,
        EmployeeSyncService $syncService
    ): void {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            Log::warning('BulkSyncEmployeesToDeviceJob: Tenant not found', [
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        // Switch to tenant database
        $databaseManager->switchConnection($tenant);
        app()->instance('tenant', $tenant);

        $device = BiometricDevice::find($this->deviceId);

        if ($device === null) {
            Log::warning('BulkSyncEmployeesToDeviceJob: Device not found', [
                'device_id' => $this->deviceId,
            ]);

            return;
        }

        $syncRecords = $syncService->syncAllEmployeesToDevice(
            $device,
            $this->employeeIds,
            immediate: true
        );

        Log::info('BulkSyncEmployeesToDeviceJob completed', [
            'device_id' => $this->deviceId,
            'tenant_id' => $this->tenantId,
            'synced_count' => $syncRecords->count(),
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('BulkSyncEmployeesToDeviceJob failed', [
            'device_id' => $this->deviceId,
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
        ]);
    }
}
