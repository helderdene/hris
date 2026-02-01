<?php

namespace App\Services\Attendance;

use App\DataTransferObjects\AttendanceLogData;
use App\Models\AttendanceLog;
use App\Models\BiometricDevice;
use App\Models\Employee;
use App\Models\Tenant;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Support\Facades\Log;

/**
 * Processes parsed MQTT attendance data into database records.
 *
 * Handles tenant resolution, employee matching, and log creation.
 */
class AttendanceLogProcessor
{
    public function __construct(
        private TenantDatabaseManager $tenantManager
    ) {}

    /**
     * Process attendance data and create a log record.
     */
    public function process(AttendanceLogData $data): ?AttendanceLog
    {
        $deviceWithTenant = $this->findDeviceWithTenant($data->deviceIdentifier);

        if ($deviceWithTenant === null) {
            Log::warning('Unknown biometric device', [
                'device_identifier' => $data->deviceIdentifier,
            ]);

            return null;
        }

        [$device, $tenant] = $deviceWithTenant;

        // Switch to tenant context
        $this->tenantManager->switchConnection($tenant);
        app()->instance('tenant', $tenant);

        // Update device last seen timestamp
        $device->update(['last_seen_at' => now()]);

        // Find employee by code
        $employee = Employee::where('employee_number', $data->employeeCode)->first();

        if ($employee === null) {
            Log::info('Attendance log for unmatched employee', [
                'employee_code' => $data->employeeCode,
                'tenant' => $tenant->slug,
            ]);
        }

        // Create attendance log
        $log = AttendanceLog::create([
            'biometric_device_id' => $device->id,
            'employee_id' => $employee?->id,
            'device_person_id' => $data->devicePersonId,
            'device_record_id' => $data->deviceRecordId,
            'employee_code' => $data->employeeCode,
            'confidence' => $data->confidence,
            'verify_status' => $data->verifyStatus,
            'logged_at' => $data->loggedAt,
            'direction' => $data->direction,
            'person_name' => $data->personName,
            'captured_photo' => $data->capturedPhoto,
            'raw_payload' => $data->rawPayload,
        ]);

        Log::info('Attendance log created', [
            'log_id' => $log->id,
            'employee_id' => $employee?->id,
            'tenant' => $tenant->slug,
            'device' => $device->name,
        ]);

        return $log;
    }

    /**
     * Find a biometric device across all tenants.
     *
     * @return array{0: BiometricDevice, 1: Tenant}|null
     */
    private function findDeviceWithTenant(string $deviceIdentifier): ?array
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $this->tenantManager->switchConnection($tenant);

            $device = BiometricDevice::where('device_identifier', $deviceIdentifier)
                ->where('is_active', true)
                ->first();

            if ($device !== null) {
                return [$device, $tenant];
            }
        }

        return null;
    }
}
