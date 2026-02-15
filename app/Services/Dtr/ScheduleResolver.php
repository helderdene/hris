<?php

namespace App\Services\Dtr;

use App\Models\Employee;
use App\Models\EmployeeScheduleAssignment;
use App\Models\WorkSchedule;
use Carbon\Carbon;

/**
 * Resolves the effective work schedule for an employee on a given date.
 */
class ScheduleResolver
{
    /**
     * Get the employee's schedule assignment for a specific date.
     *
     * @return array{schedule: WorkSchedule|null, assignment: EmployeeScheduleAssignment|null, shift_name: string|null}
     */
    public function resolve(Employee $employee, Carbon $date): array
    {
        $assignment = EmployeeScheduleAssignment::query()
            ->where('employee_id', $employee->id)
            ->where('effective_date', '<=', $date->toDateString())
            ->where(function ($query) use ($date) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date->toDateString());
            })
            ->orderBy('effective_date', 'desc')
            ->first();

        if ($assignment === null) {
            return [
                'schedule' => null,
                'assignment' => null,
                'shift_name' => null,
            ];
        }

        $schedule = WorkSchedule::find($assignment->work_schedule_id);

        return [
            'schedule' => $schedule,
            'assignment' => $assignment,
            'shift_name' => $assignment->shift_name,
        ];
    }

    /**
     * Check if a given date is a work day for the schedule.
     */
    public function isWorkDay(WorkSchedule $schedule, Carbon $date): bool
    {
        $dayOfWeek = strtolower($date->englishDayOfWeek);
        $config = $schedule->time_configuration;

        // Check if work_days is defined in time configuration
        if (isset($config['work_days']) && is_array($config['work_days'])) {
            return in_array($dayOfWeek, $config['work_days'], true);
        }

        // For shifting schedules, check if shifts are defined
        if (isset($config['shifts']) && is_array($config['shifts'])) {
            // Shifting schedules typically work all days, but specific days
            // would be determined by the shift assignment
            return true;
        }

        // Default to weekdays (Monday-Friday)
        return ! in_array($dayOfWeek, ['saturday', 'sunday'], true);
    }

    /**
     * Get the scheduled start time for the given date and schedule.
     */
    public function getScheduledStartTime(WorkSchedule $schedule, Carbon $date, ?string $shiftName = null): ?Carbon
    {
        $config = $schedule->time_configuration;

        // Fixed schedule
        if (isset($config['start_time'])) {
            return $this->parseTimeOnDate($config['start_time'], $date);
        }

        // Flexible schedule - use core hours start
        if (isset($config['core_hours']['start_time'])) {
            return $this->parseTimeOnDate($config['core_hours']['start_time'], $date);
        }

        // Shifting schedule - find the matching shift
        if (isset($config['shifts']) && $shiftName !== null) {
            foreach ($config['shifts'] as $shift) {
                if ($shift['name'] === $shiftName) {
                    return $this->parseTimeOnDate($shift['start_time'], $date);
                }
            }
        }

        return null;
    }

    /**
     * Get the scheduled end time for the given date and schedule.
     */
    public function getScheduledEndTime(WorkSchedule $schedule, Carbon $date, ?string $shiftName = null): ?Carbon
    {
        $config = $schedule->time_configuration;
        $dayOfWeek = strtolower($date->englishDayOfWeek);

        // Check for half-day Saturday
        if ($dayOfWeek === 'saturday' && isset($config['half_day_saturday']) && $config['half_day_saturday']) {
            if (isset($config['saturday_end_time'])) {
                return $this->parseTimeOnDate($config['saturday_end_time'], $date);
            }
        }

        // Fixed schedule
        if (isset($config['end_time'])) {
            $endTime = $this->parseTimeOnDate($config['end_time'], $date);

            // Handle cross-midnight schedules (e.g., 17:00-00:00)
            if (isset($config['start_time'])) {
                $startTime = $this->parseTimeOnDate($config['start_time'], $date);
                if ($endTime->lte($startTime)) {
                    $endTime->addDay();
                }
            }

            return $endTime;
        }

        // Flexible schedule - use core hours end
        if (isset($config['core_hours']['end_time'])) {
            $endTime = $this->parseTimeOnDate($config['core_hours']['end_time'], $date);

            // Handle cross-midnight core hours
            if (isset($config['core_hours']['start_time'])) {
                $startTime = $this->parseTimeOnDate($config['core_hours']['start_time'], $date);
                if ($endTime->lte($startTime)) {
                    $endTime->addDay();
                }
            }

            return $endTime;
        }

        // Shifting schedule - find the matching shift
        if (isset($config['shifts']) && $shiftName !== null) {
            foreach ($config['shifts'] as $shift) {
                if ($shift['name'] === $shiftName) {
                    $endTime = $this->parseTimeOnDate($shift['end_time'], $date);

                    // Handle cross-midnight shifts
                    if ($endTime->lt($this->parseTimeOnDate($shift['start_time'], $date))) {
                        $endTime->addDay();
                    }

                    return $endTime;
                }
            }
        }

        return null;
    }

    /**
     * Get the required work minutes for the schedule.
     */
    public function getRequiredWorkMinutes(WorkSchedule $schedule, Carbon $date): int
    {
        $config = $schedule->time_configuration;
        $dayOfWeek = strtolower($date->englishDayOfWeek);

        // Check for half-day Saturday
        if ($dayOfWeek === 'saturday' && isset($config['half_day_saturday']) && $config['half_day_saturday']) {
            // Typically 4 hours for half-day
            return 240;
        }

        // Flexible schedule with explicit hours
        if (isset($config['required_hours_per_day'])) {
            return (int) $config['required_hours_per_day'] * 60;
        }

        // Compressed schedule with daily hours
        if (isset($config['daily_hours'])) {
            // Check for half-day in compressed schedule
            if (isset($config['half_day']['enabled']) && $config['half_day']['enabled']) {
                if ($config['half_day']['day'] === $dayOfWeek) {
                    return (int) ($config['half_day']['hours'] ?? 4) * 60;
                }
            }

            return (int) $config['daily_hours'] * 60;
        }

        // Calculate from start/end times for fixed schedules
        $start = $this->getScheduledStartTime($schedule, $date);
        $end = $this->getScheduledEndTime($schedule, $date);

        if ($start && $end) {
            $totalMinutes = $start->diffInMinutes($end);

            // Subtract break time
            $breakMinutes = $this->getBreakDuration($schedule);

            return max(0, $totalMinutes - $breakMinutes);
        }

        // Default to 8 hours
        return 480;
    }

    /**
     * Get the break duration in minutes for the schedule.
     */
    public function getBreakDuration(WorkSchedule $schedule, ?string $shiftName = null): int
    {
        $config = $schedule->time_configuration;

        // Direct break configuration
        if (isset($config['break']['duration_minutes'])) {
            return (int) $config['break']['duration_minutes'];
        }

        // Shifting schedule - get break from specific shift
        if (isset($config['shifts']) && $shiftName !== null) {
            foreach ($config['shifts'] as $shift) {
                if ($shift['name'] === $shiftName && isset($shift['break']['duration_minutes'])) {
                    return (int) $shift['break']['duration_minutes'];
                }
            }
        }

        // Default to no break if none configured
        return 0;
    }

    /**
     * Parse a time string onto a specific date.
     */
    protected function parseTimeOnDate(string $time, Carbon $date): Carbon
    {
        $timeParts = explode(':', $time);
        $hours = (int) ($timeParts[0] ?? 0);
        $minutes = (int) ($timeParts[1] ?? 0);

        return $date->copy()->setTime($hours, $minutes, 0);
    }
}
