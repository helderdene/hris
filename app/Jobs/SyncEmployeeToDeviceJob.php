<?php

namespace App\Jobs;

use App\Models\BiometricDevice;
use App\Models\Employee;
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
 * Job to sync a single employee to a biometric device.
 *
 * Handles tenant context switching and retry logic for failed syncs.
 */
class SyncEmployeeToDeviceJob implements ShouldQueue
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
     */
    public function __construct(
        public int $employeeId,
        public int $deviceId,
        public int $tenantId
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
            Log::warning('SyncEmployeeToDeviceJob: Tenant not found', [
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        // Switch to tenant database
        $databaseManager->switchConnection($tenant);
        app()->instance('tenant', $tenant);

        $employee = Employee::find($this->employeeId);
        $device = BiometricDevice::find($this->deviceId);

        if ($employee === null || $device === null) {
            Log::warning('SyncEmployeeToDeviceJob: Employee or device not found', [
                'employee_id' => $this->employeeId,
                'device_id' => $this->deviceId,
            ]);

            return;
        }

        $syncService->processPendingSync($employee, $device);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SyncEmployeeToDeviceJob failed', [
            'employee_id' => $this->employeeId,
            'device_id' => $this->deviceId,
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
        ]);
    }
}
