<?php

namespace App\Services\Kiosk;

use App\Enums\AttendanceSource;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Kiosk;

class KioskClockService
{
    /**
     * Record a clock event from a kiosk terminal.
     */
    public function clock(Employee $employee, string $direction, Kiosk $kiosk): AttendanceLog
    {
        $log = AttendanceLog::create([
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_number,
            'person_name' => $employee->full_name,
            'device_person_id' => "kiosk-{$employee->id}",
            'direction' => $direction,
            'logged_at' => now(),
            'source' => AttendanceSource::Kiosk,
            'kiosk_id' => $kiosk->id,
            'biometric_device_id' => null,
        ]);

        $kiosk->update(['last_activity_at' => now()]);

        return $log;
    }

    /**
     * Record a clock event from self-service.
     */
    public function clockSelfService(Employee $employee, string $direction): AttendanceLog
    {
        return AttendanceLog::create([
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_number,
            'person_name' => $employee->full_name,
            'device_person_id' => "self-{$employee->id}",
            'direction' => $direction,
            'logged_at' => now(),
            'source' => AttendanceSource::SelfService,
            'biometric_device_id' => null,
            'kiosk_id' => null,
        ]);
    }

    /**
     * Check if the employee is within the cooldown period.
     *
     * Returns true if still in cooldown (should block), false if OK.
     */
    public function checkCooldown(Employee $employee, int $cooldownMinutes): bool
    {
        return AttendanceLog::where('employee_id', $employee->id)
            ->where('logged_at', '>=', now()->subMinutes($cooldownMinutes))
            ->exists();
    }

    /**
     * Get the last attendance punch for an employee.
     */
    public function getLastPunch(Employee $employee): ?AttendanceLog
    {
        return AttendanceLog::where('employee_id', $employee->id)
            ->orderByDesc('logged_at')
            ->first();
    }
}
