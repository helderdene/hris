<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncAllToDeviceRequest;
use App\Http\Requests\SyncEmployeeRequest;
use App\Http\Requests\UnsyncEmployeeRequest;
use App\Http\Resources\EmployeeDeviceSyncResource;
use App\Jobs\BulkSyncEmployeesToDeviceJob;
use App\Models\BiometricDevice;
use App\Models\Employee;
use App\Services\Biometric\EmployeeSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * API controller for managing biometric device synchronization.
 *
 * Handles sync status queries and triggers for employees and devices.
 */
class BiometricSyncController extends Controller
{
    public function __construct(
        protected EmployeeSyncService $syncService
    ) {}

    /**
     * Get the sync status for all employees on a device.
     *
     * Note: $tenant parameter is captured from subdomain but not used directly.
     */
    public function deviceSyncStatus(BiometricDevice $device): JsonResponse
    {
        Gate::authorize('view-biometric-devices');

        $syncStatuses = $this->syncService->getDeviceSyncStatus($device);

        $totalEmployees = Employee::query()
            ->active()
            ->where('work_location_id', $device->work_location_id)
            ->count();

        $syncedCount = $syncStatuses->where('status.value', 'synced')->count();
        $pendingCount = $syncStatuses->where('status.value', 'pending')->count();
        $failedCount = $syncStatuses->where('status.value', 'failed')->count();

        return response()->json([
            'data' => EmployeeDeviceSyncResource::collection($syncStatuses),
            'meta' => [
                'total_employees' => $totalEmployees,
                'synced_count' => $syncedCount,
                'pending_count' => $pendingCount,
                'failed_count' => $failedCount,
                'device_id' => $device->id,
                'device_name' => $device->name,
            ],
        ]);
    }

    /**
     * Trigger sync for all employees to a device.
     *
     * Note: $tenant parameter is captured from subdomain but not used directly.
     */
    public function syncAllToDevice(
        SyncAllToDeviceRequest $request,
        BiometricDevice $device
    ): JsonResponse {
        Gate::authorize('manage-biometric-devices');

        $validated = $request->validated();
        $immediate = $validated['immediate'] ?? false;
        $employeeIds = $validated['employee_ids'] ?? null;

        if ($immediate) {
            $syncRecords = $this->syncService->syncAllEmployeesToDevice(
                $device,
                $employeeIds,
                immediate: true
            );

            return response()->json([
                'message' => 'Sync completed',
                'data' => EmployeeDeviceSyncResource::collection($syncRecords),
            ]);
        }

        // Queue for background processing
        $tenantModel = tenant();

        BulkSyncEmployeesToDeviceJob::dispatch(
            $device->id,
            $tenantModel->id,
            $employeeIds
        );

        return response()->json([
            'message' => 'Sync job queued',
            'data' => [
                'queued' => true,
                'device_id' => $device->id,
            ],
        ], 202);
    }

    /**
     * Get the sync status for an employee across all devices.
     *
     * Note: $tenant parameter is captured from subdomain but not used directly.
     */
    public function employeeSyncStatus(Employee $employee): JsonResponse
    {
        Gate::authorize('can-view-employee-documents', $employee);

        $syncStatuses = $this->syncService->getEmployeeSyncStatus($employee);

        $totalDevices = $employee->getDevicesToSyncTo()->count();
        $syncedCount = $syncStatuses->where('status.value', 'synced')->count();
        $pendingCount = $syncStatuses->where('status.value', 'pending')->count();
        $failedCount = $syncStatuses->where('status.value', 'failed')->count();

        return response()->json([
            'data' => EmployeeDeviceSyncResource::collection($syncStatuses),
            'meta' => [
                'total_devices' => $totalDevices,
                'synced_count' => $syncedCount,
                'pending_count' => $pendingCount,
                'failed_count' => $failedCount,
                'employee_id' => $employee->id,
                'employee_name' => $employee->full_name,
            ],
        ]);
    }

    /**
     * Verify an employee's actual presence on each device via MQTT SearchPerson.
     *
     * Queries devices and updates sync records to reflect real-time device state.
     *
     * Note: $tenant parameter is captured from subdomain but not used directly.
     */
    public function verifyEmployeeDevices(Employee $employee): JsonResponse
    {
        Gate::authorize('can-view-employee-documents', $employee);

        $syncStatuses = $this->syncService->verifyEmployeeOnDevices($employee);

        return response()->json([
            'data' => EmployeeDeviceSyncResource::collection($syncStatuses),
        ]);
    }

    /**
     * Trigger sync for an employee to specific devices or all devices.
     *
     * Note: $tenant parameter is captured from subdomain but not used directly.
     */
    public function syncEmployeeToDevices(
        SyncEmployeeRequest $request,
        Employee $employee
    ): JsonResponse {
        Gate::authorize('can-manage-employee-documents', $employee);

        $validated = $request->validated();
        $immediate = $validated['immediate'] ?? false;
        $deviceIds = $validated['device_ids'] ?? null;

        if ($deviceIds !== null) {
            // Sync to specific devices
            $devices = BiometricDevice::whereIn('id', $deviceIds)->get();
            $syncRecords = collect();

            foreach ($devices as $device) {
                $syncRecords->push(
                    $this->syncService->syncEmployeeToDevice($employee, $device, $immediate)
                );
            }
        } else {
            // Sync to all devices at work location
            $syncRecords = $this->syncService->syncEmployeeToAllDevices($employee, $immediate);
        }

        $message = $immediate ? 'Sync completed' : 'Sync jobs queued';

        return response()->json([
            'message' => $message,
            'data' => EmployeeDeviceSyncResource::collection($syncRecords),
        ], $immediate ? 200 : 202);
    }

    /**
     * Unsync (delete) an employee from a specific biometric device.
     *
     * Note: $tenant parameter is captured from subdomain but not used directly.
     */
    public function unsyncEmployeeFromDevice(
        UnsyncEmployeeRequest $request,
        Employee $employee
    ): JsonResponse {
        Gate::authorize('can-manage-employee-documents', $employee);

        $device = BiometricDevice::findOrFail($request->validated('device_id'));

        $syncRecord = $this->syncService->unsyncEmployeeFromDevice($employee, $device);

        return response()->json([
            'message' => 'Unsync completed',
            'data' => new EmployeeDeviceSyncResource($syncRecord),
        ]);
    }
}
