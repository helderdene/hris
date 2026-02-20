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
     * Process attendance data and create log records across all matching tenants.
     */
    public function process(AttendanceLogData $data): ?AttendanceLog
    {
        $devicesWithTenants = $this->findAllDevicesWithTenant($data->deviceIdentifier);

        if (empty($devicesWithTenants)) {
            Log::warning('Unknown biometric device', [
                'device_identifier' => $data->deviceIdentifier,
            ]);

            return null;
        }

        $firstLog = null;

        foreach ($devicesWithTenants as [$device, $tenant]) {
            $this->tenantManager->switchConnection($tenant);
            app()->instance('tenant', $tenant);

            $device->update([
                'last_seen_at' => now(),
                'status' => 'online',
            ]);

            $employee = Employee::where('employee_number', $data->employeeCode)->first();

            if ($employee === null) {
                Log::info('Attendance log for unmatched employee', [
                    'employee_code' => $data->employeeCode,
                    'tenant' => $tenant->slug,
                ]);
            }

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

            $firstLog ??= $log;
        }

        return $firstLog;
    }

    /**
     * Find all biometric devices matching the identifier across all tenants.
     *
     * @return array<array{0: BiometricDevice, 1: Tenant}>
     */
    private function findAllDevicesWithTenant(string $deviceIdentifier): array
    {
        $results = [];
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            try {
                $this->tenantManager->switchConnection($tenant);

                $device = BiometricDevice::where('device_identifier', $deviceIdentifier)
                    ->where('is_active', true)
                    ->first();

                if ($device !== null) {
                    $results[] = [$device, $tenant];
                }
            } catch (\Throwable $e) {
                Log::debug("AttendanceLog: skipping tenant {$tenant->slug}", [
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }

        return $results;
    }
}
