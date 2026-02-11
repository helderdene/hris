<?php

namespace App\Services\Biometric;

use App\Enums\SyncStatus;
use App\Jobs\SyncEmployeeToDeviceJob;
use App\Models\BiometricDevice;
use App\Models\DeviceSyncLog;
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

        if ($immediate) {
            // Use Ack-waiting to ensure each message is processed before the next
            return $employees->map(
                fn (Employee $employee) => $this->performImmediateSyncWithAck(
                    $this->getOrCreateSyncRecord($employee, $device),
                    $employee,
                    $device
                )
            );
        }

        return $employees->map(
            fn (Employee $employee) => $this->syncEmployeeToDevice($employee, $device, false)
        );
    }

    /**
     * Unsync (delete) an employee from a specific device.
     *
     * Sends a DelPerson command and updates the sync record based on the result.
     */
    public function unsyncEmployeeFromDevice(Employee $employee, BiometricDevice $device): EmployeeDeviceSync
    {
        $syncRecord = EmployeeDeviceSync::where('employee_id', $employee->id)
            ->where('biometric_device_id', $device->id)
            ->firstOrFail();

        try {
            $syncLog = $this->deviceCommandService->deletePerson($device, $employee);

            if ($syncLog->status === DeviceSyncLog::STATUS_ACKNOWLEDGED) {
                $syncRecord->update([
                    'status' => SyncStatus::Pending,
                    'last_synced_at' => null,
                    'last_error' => null,
                ]);

                Log::info('Employee unsynced from device', [
                    'employee_id' => $employee->id,
                    'device_id' => $device->id,
                    'message_id' => $syncLog->message_id,
                ]);
            } elseif ($syncLog->status === DeviceSyncLog::STATUS_FAILED) {
                $errorMessage = $syncLog->response_payload['info']['result'] ?? 'Device returned error';
                $syncRecord->markFailed($errorMessage);

                Log::warning('Device rejected employee unsync', [
                    'employee_id' => $employee->id,
                    'device_id' => $device->id,
                    'message_id' => $syncLog->message_id,
                    'error' => $errorMessage,
                ]);
            } else {
                $syncRecord->markFailed('Device did not acknowledge within timeout period');

                Log::warning('Employee unsync Ack timeout', [
                    'employee_id' => $employee->id,
                    'device_id' => $device->id,
                    'message_id' => $syncLog->message_id,
                ]);
            }
        } catch (\Throwable $e) {
            $syncRecord->markFailed($e->getMessage());

            Log::error('Failed to unsync employee from device', [
                'employee_id' => $employee->id,
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $syncRecord->fresh()->load('biometricDevice');
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
     * Initialize sync records for all active employees at a device's work location.
     *
     * Used when a new biometric device is created to create pending sync records
     * for every active employee assigned to that location.
     *
     * @return Collection<int, EmployeeDeviceSync>
     */
    public function initializeSyncRecordsForDevice(BiometricDevice $device): Collection
    {
        $employees = Employee::query()
            ->active()
            ->where('work_location_id', $device->work_location_id)
            ->get();

        return $employees->map(
            fn (Employee $employee) => $this->getOrCreateSyncRecord($employee, $device)
        );
    }

    /**
     * Verify an employee's presence on each device via SearchPerson and update sync records.
     *
     * Queries each device the employee should be synced to, and updates the
     * EmployeeDeviceSync records to reflect the actual device state.
     *
     * @return Collection<int, EmployeeDeviceSync>
     */
    public function verifyEmployeeOnDevices(Employee $employee): Collection
    {
        $devices = $employee->getDevicesToSyncTo();

        if ($devices->isEmpty()) {
            return collect();
        }

        return $devices->map(function (BiometricDevice $device) use ($employee) {
            $syncRecord = $this->getOrCreateSyncRecord($employee, $device);

            try {
                $result = $this->deviceCommandService->searchPerson($device, $employee);

                if ($result['exists']) {
                    $syncRecord->markSynced();
                } else {
                    // Only downgrade to pending if currently marked as synced
                    if ($syncRecord->status === SyncStatus::Synced) {
                        $syncRecord->update(['status' => SyncStatus::Pending]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Device verification failed', [
                    'employee_id' => $employee->id,
                    'device_id' => $device->id,
                    'error' => $e->getMessage(),
                ]);
                // Leave current status unchanged on error
            }

            return $syncRecord->fresh()->load('biometricDevice');
        });
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
     * Check if an employee already exists on a device via SearchPerson.
     */
    protected function isAlreadyOnDevice(Employee $employee, BiometricDevice $device): bool
    {
        try {
            $result = $this->deviceCommandService->searchPerson($device, $employee);

            return $result['exists'];
        } catch (\Throwable $e) {
            Log::warning('SearchPerson check failed, proceeding with sync', [
                'employee_id' => $employee->id,
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Perform an immediate sync operation (fire-and-forget).
     */
    protected function performImmediateSync(
        EmployeeDeviceSync $syncRecord,
        Employee $employee,
        BiometricDevice $device
    ): EmployeeDeviceSync {
        try {
            if ($this->isAlreadyOnDevice($employee, $device)) {
                $syncRecord->markSynced();
                Log::info('Employee already on device, skipping sync', [
                    'employee_id' => $employee->id,
                    'device_id' => $device->id,
                ]);

                return $syncRecord->fresh();
            }

            $syncLog = $this->deviceCommandService->editPerson($device, $employee);
            $syncRecord->markSyncing($syncLog->message_id);
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
     * Perform an immediate sync and wait for device Ack before returning.
     *
     * Used in bulk sync to ensure the device processes each message sequentially.
     */
    protected function performImmediateSyncWithAck(
        EmployeeDeviceSync $syncRecord,
        Employee $employee,
        BiometricDevice $device
    ): EmployeeDeviceSync {
        try {
            if ($this->isAlreadyOnDevice($employee, $device)) {
                $syncRecord->markSynced();
                Log::info('Employee already on device, skipping sync', [
                    'employee_id' => $employee->id,
                    'device_id' => $device->id,
                ]);

                return $syncRecord->fresh();
            }

            $syncLog = $this->deviceCommandService->editPersonAndWaitForAck($device, $employee);
            $syncRecord->markSyncing($syncLog->message_id);

            if ($syncLog->status === \App\Models\DeviceSyncLog::STATUS_ACKNOWLEDGED) {
                $syncRecord->markSynced();
                Log::info('Employee synced to device (Ack received)', [
                    'employee_id' => $employee->id,
                    'device_id' => $device->id,
                    'message_id' => $syncLog->message_id,
                ]);
            } elseif ($syncLog->status === \App\Models\DeviceSyncLog::STATUS_FAILED) {
                $errorMessage = $syncLog->response_payload['info']['result'] ?? 'Device returned error';
                $syncRecord->markFailed($errorMessage);
                Log::warning('Device rejected employee sync', [
                    'employee_id' => $employee->id,
                    'device_id' => $device->id,
                    'message_id' => $syncLog->message_id,
                    'error' => $errorMessage,
                ]);
            } else {
                // Ack timeout — mark as synced optimistically
                $syncRecord->markSynced();
                Log::warning('Employee sync Ack timeout, marked as synced', [
                    'employee_id' => $employee->id,
                    'device_id' => $device->id,
                    'message_id' => $syncLog->message_id,
                ]);
            }
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
