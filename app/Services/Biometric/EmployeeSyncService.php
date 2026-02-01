<?php

namespace App\Services\Biometric;

use App\Enums\SyncStatus;
use App\Jobs\SyncEmployeeToDeviceJob;
use App\Models\BiometricDevice;
use App\Models\Employee;
use App\Models\EmployeeDeviceSync;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing employee-to-device synchronization.
 *
 * Orchestrates the sync process including job dispatching, status tracking,
 * and sync record management.
 */
class EmployeeSyncService
{
    public function __construct(
        protected DeviceCommandService $deviceCommandService
    ) {}

    /**
     * Sync an employee to a specific device.
     *
     * @param  bool  $immediate  If true, sync immediately; if false, queue for background processing
     */
    public function syncEmployeeToDevice(
        Employee $employee,
        BiometricDevice $device,
        bool $immediate = false
    ): EmployeeDeviceSync {
        $syncRecord = $this->getOrCreateSyncRecord($employee, $device);

        if ($immediate) {
            return $this->performImmediateSync($syncRecord, $employee, $device);
        }

        $this->dispatchSyncJob($employee, $device);

        return $syncRecord;
    }

    /**
     * Sync an employee to all devices at their work location.
     *
     * @return Collection<int, EmployeeDeviceSync>
     */
    public function syncEmployeeToAllDevices(Employee $employee, bool $immediate = false): Collection
    {
        $devices = $employee->getDevicesToSyncTo();

        if ($devices->isEmpty()) {
            Log::info('No devices to sync employee to', [
                'employee_id' => $employee->id,
                'work_location_id' => $employee->work_location_id,
            ]);

            return collect();
        }

        return $devices->map(
            fn (BiometricDevice $device) => $this->syncEmployeeToDevice($employee, $device, $immediate)
        );
    }

    /**
     * Sync all employees to a specific device.
     *
     * @param  array<int>|null  $employeeIds  Specific employee IDs to sync, or null for all at location
     * @return Collection<int, EmployeeDeviceSync>
     */
    public function syncAllEmployeesToDevice(
        BiometricDevice $device,
        ?array $employeeIds = null,
        bool $immediate = false
    ): Collection {
        $query = Employee::query()
            ->active()
            ->where('work_location_id', $device->work_location_id);

        if ($employeeIds !== null) {
            $query->whereIn('id', $employeeIds);
        }

        $employees = $query->get();

        return $employees->map(
            fn (Employee $employee) => $this->syncEmployeeToDevice($employee, $device, $immediate)
        );
    }

    /**
     * Initialize sync records for a new employee.
     *
     * Creates pending sync records for all devices at the employee's work location.
     *
     * @return Collection<int, EmployeeDeviceSync>
     */
    public function initializeSyncRecords(Employee $employee): Collection
    {
        $devices = $employee->getDevicesToSyncTo();

        return $devices->map(
            fn (BiometricDevice $device) => $this->getOrCreateSyncRecord($employee, $device)
        );
    }

    /**
     * Get the sync status for an employee across all devices.
     *
     * @return Collection<int, EmployeeDeviceSync>
     */
    public function getEmployeeSyncStatus(Employee $employee): Collection
    {
        return $employee->deviceSyncs()
            ->with('biometricDevice')
            ->get();
    }

    /**
     * Get the sync status for a device across all employees.
     *
     * @return Collection<int, EmployeeDeviceSync>
     */
    public function getDeviceSyncStatus(BiometricDevice $device): Collection
    {
        return $device->employeeDeviceSyncs()
            ->with('employee')
            ->get();
    }

    /**
     * Process pending sync for an employee-device pair.
     *
     * Used by the background job to perform the actual sync.
     */
    public function processPendingSync(Employee $employee, BiometricDevice $device): EmployeeDeviceSync
    {
        return $this->performImmediateSync(
            $this->getOrCreateSyncRecord($employee, $device),
            $employee,
            $device
        );
    }

    /**
     * Get or create a sync record for an employee-device pair.
     */
    protected function getOrCreateSyncRecord(Employee $employee, BiometricDevice $device): EmployeeDeviceSync
    {
        return EmployeeDeviceSync::firstOrCreate(
            [
                'employee_id' => $employee->id,
                'biometric_device_id' => $device->id,
            ],
            [
                'status' => SyncStatus::Pending,
            ]
        );
    }

    /**
     * Perform an immediate sync operation.
     */
    protected function performImmediateSync(
        EmployeeDeviceSync $syncRecord,
        Employee $employee,
        BiometricDevice $device
    ): EmployeeDeviceSync {
        try {
            $syncLog = $this->deviceCommandService->editPerson($device, $employee);
            $syncRecord->markSyncing($syncLog->message_id);

            // For now, we assume success if the message was sent
            // In a future iteration, we could wait for device acknowledgment
            $syncRecord->markSynced();

            Log::info('Employee synced to device', [
                'employee_id' => $employee->id,
                'device_id' => $device->id,
                'message_id' => $syncLog->message_id,
            ]);
        } catch (\Throwable $e) {
            $syncRecord->markFailed($e->getMessage());

            Log::error('Failed to sync employee to device', [
                'employee_id' => $employee->id,
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $syncRecord->fresh();
    }

    /**
     * Dispatch a background sync job.
     */
    protected function dispatchSyncJob(Employee $employee, BiometricDevice $device): void
    {
        $tenant = tenant();

        if ($tenant === null) {
            Log::warning('Cannot dispatch sync job: no tenant context', [
                'employee_id' => $employee->id,
                'device_id' => $device->id,
            ]);

            return;
        }

        SyncEmployeeToDeviceJob::dispatch(
            $employee->id,
            $device->id,
            $tenant->id
        );

        Log::info('Sync job dispatched', [
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'tenant_id' => $tenant->id,
        ]);
    }
}
