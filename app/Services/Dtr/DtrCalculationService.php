<?php

namespace App\Services\Dtr;

use App\Enums\DtrStatus;
use App\Enums\HolidayType;
use App\Models\AttendanceLog;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\TimeRecordPunch;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Main orchestrator for DTR calculations.
 * Processes raw attendance logs into computed daily time records.
 */
class DtrCalculationService
{
    public function __construct(
        protected ScheduleResolver $scheduleResolver,
        protected PunchPairProcessor $punchPairProcessor,
        protected TimeCalculator $timeCalculator
    ) {}

    /**
     * Calculate and save DTR for an employee on a specific date.
     */
    public function calculateForDate(Employee $employee, Carbon $date): DailyTimeRecord
    {
        // Check for existing record
        $existingRecord = DailyTimeRecord::query()
            ->where('employee_id', $employee->id)
            ->where('date', $date->toDateString())
            ->first();

        // Get employee's schedule for this date
        $scheduleData = $this->scheduleResolver->resolve($employee, $date);
        $schedule = $scheduleData['schedule'];
        $shiftName = $scheduleData['shift_name'];

        // Get attendance logs and run inference once (collapse duplicates + match to schedule)
        $logs = $this->getAttendanceLogsForDate($employee, $date, $schedule, $shiftName);
        $droppedPunchCount = 0;

        if ($logs->isNotEmpty()) {
            $inferResult = $this->inferDirectionsForLogs($logs, $schedule, $date, $shiftName);
            $logs = $inferResult['logs'];
            $droppedPunchCount = $inferResult['droppedCount'];
        }

        // Determine status and calculate times
        $dtrData = $this->buildDtrData($employee, $date, $schedule, $shiftName, $logs, $droppedPunchCount);

        // Create or update the record
        if ($existingRecord !== null) {
            $existingRecord->update($dtrData);
            $dtr = $existingRecord;
        } else {
            $dtr = DailyTimeRecord::create($dtrData);
        }

        // Save punch records (logs already have inferred directions)
        $this->savePunchRecords($dtr, $logs);

        return $dtr->fresh(['employee', 'workSchedule', 'punches']);
    }

    /**
     * Calculate DTR for a date range.
     *
     * @return Collection<int, DailyTimeRecord>
     */
    public function calculateForDateRange(Employee $employee, Carbon $startDate, Carbon $endDate): Collection
    {
        $records = collect();
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $records->push($this->calculateForDate($employee, $currentDate->copy()));
            $currentDate->addDay();
        }

        return $records;
    }

    /**
     * Build DTR data array from schedule and logs.
     *
     * @param  Collection<int, AttendanceLog>  $logs
     * @return array<string, mixed>
     */
    protected function buildDtrData(
        Employee $employee,
        Carbon $date,
        ?\App\Models\WorkSchedule $schedule,
        ?string $shiftName,
        Collection $logs,
        int $droppedPunchCount = 0
    ): array {
        // No schedule assigned
        if ($schedule === null) {
            return $this->buildNoScheduleData($employee, $date, $logs);
        }

        // Check if this is a rest day
        if (! $this->scheduleResolver->isWorkDay($schedule, $date)) {
            return $this->buildRestDayData($employee, $date, $schedule, $logs);
        }

        // Check if this is a holiday
        $holiday = $this->getHolidayForDate($employee, $date);
        if ($holiday !== null) {
            return $this->buildHolidayData($employee, $date, $schedule, $shiftName, $logs, $holiday);
        }

        // No attendance logs - absent
        if ($logs->isEmpty()) {
            return $this->buildAbsentData($employee, $date, $schedule);
        }

        // Process punch pairs (logs already have inferred directions from calculateForDate)
        $punchResult = $this->punchPairProcessor->process($logs);

        return $this->buildPresentData($employee, $date, $schedule, $shiftName, $punchResult, $droppedPunchCount);
    }

    /**
     * Build data for when no schedule is assigned.
     *
     * @param  Collection<int, AttendanceLog>  $logs
     * @return array<string, mixed>
     */
    protected function buildNoScheduleData(Employee $employee, Carbon $date, Collection $logs): array
    {
        $firstIn = null;
        $lastOut = null;
        $totalWorkMinutes = 0;

        if ($logs->isNotEmpty()) {
            $punchResult = $this->punchPairProcessor->process($logs);
            $firstIn = $punchResult['first_in'];
            $lastOut = $punchResult['last_out'];
            $totalWorkMinutes = $this->punchPairProcessor->calculateTotalWorkMinutes($punchResult['pairs']);
        }

        return [
            'employee_id' => $employee->id,
            'date' => $date->toDateString(),
            'work_schedule_id' => null,
            'shift_name' => null,
            'status' => DtrStatus::NoSchedule,
            'first_in' => $firstIn,
            'last_out' => $lastOut,
            'total_work_minutes' => $totalWorkMinutes,
            'total_break_minutes' => 0,
            'late_minutes' => 0,
            'undertime_minutes' => 0,
            'overtime_minutes' => 0,
            'overtime_approved' => false,
            'night_diff_minutes' => 0,
            'remarks' => null,
            'needs_review' => true,
            'review_reason' => 'No schedule assigned',
            'computed_at' => now(),
        ];
    }

    /**
     * Build data for rest day (not a work day).
     *
     * @param  Collection<int, AttendanceLog>  $logs
     * @return array<string, mixed>
     */
    protected function buildRestDayData(
        Employee $employee,
        Carbon $date,
        \App\Models\WorkSchedule $schedule,
        Collection $logs
    ): array {
        $firstIn = null;
        $lastOut = null;
        $totalWorkMinutes = 0;
        $overtimeMinutes = 0;

        // If employee worked on rest day, calculate as OT
        if ($logs->isNotEmpty()) {
            $punchResult = $this->punchPairProcessor->process($logs);
            $firstIn = $punchResult['first_in'];
            $lastOut = $punchResult['last_out'];
            $totalWorkMinutes = $this->punchPairProcessor->calculateTotalWorkMinutes($punchResult['pairs']);
            $overtimeMinutes = $totalWorkMinutes; // All work on rest day is OT
        }

        return [
            'employee_id' => $employee->id,
            'date' => $date->toDateString(),
            'work_schedule_id' => $schedule->id,
            'shift_name' => null,
            'status' => DtrStatus::RestDay,
            'first_in' => $firstIn,
            'last_out' => $lastOut,
            'total_work_minutes' => $totalWorkMinutes,
            'total_break_minutes' => 0,
            'late_minutes' => 0,
            'undertime_minutes' => 0,
            'overtime_minutes' => $overtimeMinutes,
            'overtime_approved' => false,
            'night_diff_minutes' => 0,
            'remarks' => $totalWorkMinutes > 0 ? 'Worked on rest day' : null,
            'needs_review' => $totalWorkMinutes > 0,
            'review_reason' => $totalWorkMinutes > 0 ? 'Work on rest day - OT pending approval' : null,
            'computed_at' => now(),
        ];
    }

    /**
     * Build data for holiday.
     *
     * @param  Collection<int, AttendanceLog>  $logs
     * @return array<string, mixed>
     */
    protected function buildHolidayData(
        Employee $employee,
        Carbon $date,
        \App\Models\WorkSchedule $schedule,
        ?string $shiftName,
        Collection $logs,
        Holiday $holiday
    ): array {
        $firstIn = null;
        $lastOut = null;
        $totalWorkMinutes = 0;
        $overtimeMinutes = 0;
        $nightDiffMinutes = 0;

        if ($logs->isNotEmpty()) {
            $punchResult = $this->punchPairProcessor->process($logs);
            $firstIn = $punchResult['first_in'];
            $lastOut = $punchResult['last_out'];
            $totalWorkMinutes = $this->punchPairProcessor->calculateTotalWorkMinutes($punchResult['pairs']);
            $overtimeMinutes = $totalWorkMinutes; // All work on holiday can be considered OT

            // Calculate night differential
            $workPeriods = $this->timeCalculator->convertPunchPairsToWorkPeriods($punchResult['pairs']);
            $nightDiffMinutes = $this->timeCalculator->calculateNightDifferential($workPeriods, $schedule);
        }

        $remarks = $holiday->name;
        if ($holiday->holiday_type === HolidayType::Regular) {
            $remarks .= ' (Regular Holiday)';
        } elseif ($holiday->holiday_type === HolidayType::Special) {
            $remarks .= ' (Special Non-Working Holiday)';
        }

        return [
            'employee_id' => $employee->id,
            'date' => $date->toDateString(),
            'work_schedule_id' => $schedule->id,
            'shift_name' => $shiftName,
            'status' => DtrStatus::Holiday,
            'first_in' => $firstIn,
            'last_out' => $lastOut,
            'total_work_minutes' => $totalWorkMinutes,
            'total_break_minutes' => 0,
            'late_minutes' => 0,
            'undertime_minutes' => 0,
            'overtime_minutes' => $overtimeMinutes,
            'overtime_approved' => false,
            'night_diff_minutes' => $nightDiffMinutes,
            'remarks' => $remarks,
            'needs_review' => $totalWorkMinutes > 0,
            'review_reason' => $totalWorkMinutes > 0 ? 'Holiday work - OT pending approval' : null,
            'computed_at' => now(),
        ];
    }

    /**
     * Build data for absent (no attendance logs on a work day).
     *
     * @return array<string, mixed>
     */
    protected function buildAbsentData(
        Employee $employee,
        Carbon $date,
        \App\Models\WorkSchedule $schedule
    ): array {
        return [
            'employee_id' => $employee->id,
            'date' => $date->toDateString(),
            'work_schedule_id' => $schedule->id,
            'shift_name' => null,
            'status' => DtrStatus::Absent,
            'first_in' => null,
            'last_out' => null,
            'total_work_minutes' => 0,
            'total_break_minutes' => 0,
            'late_minutes' => 0,
            'undertime_minutes' => 0,
            'overtime_minutes' => 0,
            'overtime_approved' => false,
            'night_diff_minutes' => 0,
            'remarks' => null,
            'needs_review' => false,
            'review_reason' => null,
            'computed_at' => now(),
        ];
    }

    /**
     * Build data for present (normal work day with attendance).
     *
     * @param  array<string, mixed>  $punchResult
     * @return array<string, mixed>
     */
    protected function buildPresentData(
        Employee $employee,
        Carbon $date,
        \App\Models\WorkSchedule $schedule,
        ?string $shiftName,
        array $punchResult,
        int $droppedPunchCount = 0
    ): array {
        $firstIn = $punchResult['first_in'];
        $lastOut = $punchResult['last_out'];
        $pairs = $punchResult['pairs'];
        $breakPairs = $punchResult['break_pairs'] ?? [];
        $unpairedIn = $punchResult['unpaired_in'];

        $totalWorkMinutes = $this->punchPairProcessor->calculateTotalWorkMinutes($pairs);

        // Use actual break minutes from break pairs if available, otherwise calculate from gaps
        $totalBreakMinutes = ! empty($breakPairs)
            ? $this->punchPairProcessor->calculateActualBreakMinutes($breakPairs)
            : $this->punchPairProcessor->calculateBreakMinutes($pairs);

        // If single IN/OUT with no breaks recorded, deduct mandatory break from work minutes
        $mandatoryBreakDeducted = false;
        if (count($pairs) === 1 && empty($breakPairs) && $totalBreakMinutes === 0) {
            $mandatoryBreak = $this->getMandatoryBreakMinutes($schedule, $shiftName);
            if ($mandatoryBreak > 0 && $this->workSpansBreakPeriod($firstIn, $lastOut, $schedule, $date, $shiftName)) {
                $totalBreakMinutes = $mandatoryBreak;
                $totalWorkMinutes = max(0, $totalWorkMinutes - $mandatoryBreak);
                $mandatoryBreakDeducted = true;
            }
        }

        // Calculate late
        $lateMinutes = 0;
        if ($firstIn !== null) {
            $lateMinutes = $this->timeCalculator->calculateLate($firstIn, $schedule, $date, $shiftName);
        }

        // Calculate undertime
        $undertimeMinutes = 0;
        if ($lastOut !== null) {
            $undertimeMinutes = $this->timeCalculator->calculateUndertime($lastOut, $schedule, $date, $shiftName);
        }

        // Calculate overtime
        $overtimeMinutes = 0;
        if ($lastOut !== null) {
            $overtimeMinutes = $this->timeCalculator->calculateOvertime($lastOut, $totalWorkMinutes, $schedule, $date, $shiftName);
        }

        // Calculate night differential
        $workPeriods = $this->timeCalculator->convertPunchPairsToWorkPeriods($pairs);
        $nightDiffMinutes = $this->timeCalculator->calculateNightDifferential($workPeriods, $schedule);

        // Determine if review is needed
        $needsReview = false;
        $reviewReason = null;

        if ($droppedPunchCount > 0) {
            $needsReview = true;
            $reviewReason = $droppedPunchCount.' attendance scan(s) could not be matched to schedule';
        } elseif ($unpairedIn !== null) {
            $needsReview = true;
            $reviewReason = 'Missing time-out';
        } elseif ($lastOut === null && $firstIn !== null) {
            $needsReview = true;
            $reviewReason = 'Missing time-out';
        }

        return [
            'employee_id' => $employee->id,
            'date' => $date->toDateString(),
            'work_schedule_id' => $schedule->id,
            'shift_name' => $shiftName,
            'status' => DtrStatus::Present,
            'first_in' => $firstIn,
            'last_out' => $lastOut,
            'total_work_minutes' => $totalWorkMinutes,
            'total_break_minutes' => $totalBreakMinutes,
            'late_minutes' => $lateMinutes,
            'undertime_minutes' => $undertimeMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'overtime_approved' => false,
            'night_diff_minutes' => $nightDiffMinutes,
            'remarks' => null,
            'needs_review' => $needsReview,
            'review_reason' => $reviewReason,
            'computed_at' => now(),
        ];
    }

    /**
     * Get attendance logs for an employee on a specific date.
     *
     * For cross-midnight schedules, extends the query window into the next
     * calendar day (up to schedule end + 2hr grace) to capture late punch-outs.
     * Also excludes early-morning punches that belong to the previous day's
     * cross-midnight schedule.
     *
     * @return Collection<int, AttendanceLog>
     */
    protected function getAttendanceLogsForDate(
        Employee $employee,
        Carbon $date,
        ?WorkSchedule $schedule = null,
        ?string $shiftName = null
    ): Collection {
        $query = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->orderBy('logged_at');

        // Determine query start: exclude early-morning punches claimed by the previous day's cross-midnight schedule
        $queryStart = $this->getQueryStartExcludingPreviousDayOverflow($employee, $date);

        if ($schedule !== null) {
            $startTime = $this->scheduleResolver->getScheduledStartTime($schedule, $date, $shiftName);
            $endTime = $this->scheduleResolver->getScheduledEndTime($schedule, $date, $shiftName);

            // Tighten the query start to exclude punches that are far before schedule start.
            // Allow up to 3 hours early arrival grace before shift start.
            if ($startTime !== null) {
                $earliestReasonable = $startTime->copy()->subHours(3);
                if ($earliestReasonable->gt($queryStart)) {
                    $queryStart = $earliestReasonable;
                }
            }

            if ($startTime && $endTime && $endTime->gt($date->copy()->endOfDay())) {
                // Cross-midnight: fetch from query start through end + 2hr grace
                $graceEnd = $endTime->copy()->addHours(2);

                return $query
                    ->where('logged_at', '>=', $queryStart)
                    ->where('logged_at', '<=', $graceEnd)
                    ->get();
            }
        }

        return $query
            ->where('logged_at', '>=', $queryStart)
            ->where('logged_at', '<=', $date->copy()->endOfDay())
            ->get();
    }

    /**
     * Determine the earliest timestamp to include for a given date by checking
     * whether the previous day had a cross-midnight schedule whose grace window
     * extends into this date.
     */
    protected function getQueryStartExcludingPreviousDayOverflow(Employee $employee, Carbon $date): Carbon
    {
        $prevDate = $date->copy()->subDay();
        $prevScheduleData = $this->scheduleResolver->resolve($employee, $prevDate);
        $prevSchedule = $prevScheduleData['schedule'];
        $prevShiftName = $prevScheduleData['shift_name'];

        if ($prevSchedule !== null) {
            $prevEnd = $this->scheduleResolver->getScheduledEndTime($prevSchedule, $prevDate, $prevShiftName);

            if ($prevEnd !== null && $prevEnd->gte($date->copy()->startOfDay())) {
                return $prevEnd->copy()->addHours(2);
            }
        }

        return $date->copy()->startOfDay();
    }

    /**
     * Check if there's a holiday for the employee on the given date.
     */
    protected function getHolidayForDate(Employee $employee, Carbon $date): ?Holiday
    {
        return Holiday::query()
            ->where('date', $date->toDateString())
            ->where(function ($query) use ($employee) {
                $query->where('is_national', true);

                if ($employee->work_location_id !== null) {
                    $query->orWhere('work_location_id', $employee->work_location_id);
                }
            })
            ->first();
    }

    /**
     * Save punch records linked to the DTR.
     *
     * @param  Collection<int, AttendanceLog>  $logs
     */
    protected function savePunchRecords(DailyTimeRecord $dtr, Collection $logs): void
    {
        // Delete existing punch records
        TimeRecordPunch::where('daily_time_record_id', $dtr->id)->delete();

        if ($logs->isEmpty()) {
            return;
        }

        // Process logs and create punch records (directions already inferred)
        $punchResult = $this->punchPairProcessor->process($logs);
        $punchRecords = $this->punchPairProcessor->getPunchRecords(
            $punchResult['pairs'],
            $punchResult['break_pairs']
        );

        foreach ($punchRecords as $record) {
            TimeRecordPunch::create([
                'daily_time_record_id' => $dtr->id,
                'attendance_log_id' => $record['attendance_log_id'],
                'punch_type' => $record['punch_type'],
                'punched_at' => $record['punched_at'],
                'is_valid' => true,
                'invalidation_reason' => null,
            ]);
        }
    }

    /**
     * Collapse duplicate scans and infer directions for attendance logs
     * that lack a direction field.
     *
     * Uses schedule-event matching when a schedule is available, with
     * simple alternating as a fallback for no-schedule cases.
     *
     * @param  Collection<int, AttendanceLog>  $logs
     * @return array{logs: Collection<int, AttendanceLog>, droppedCount: int}
     */
    protected function inferDirectionsForLogs(
        Collection $logs,
        ?\App\Models\WorkSchedule $schedule,
        Carbon $date,
        ?string $shiftName
    ): array {
        // Collapse duplicate FR scans (e.g. double-taps within 3 minutes)
        $logs = $this->punchPairProcessor->collapseDuplicateScans($logs);

        // Build expected schedule events if a schedule is available
        $scheduleEvents = $this->buildScheduleEvents($schedule, $date, $shiftName);

        if (! empty($scheduleEvents)) {
            // Match punches to schedule events by proximity
            return $this->punchPairProcessor->matchToSchedule($logs, $scheduleEvents);
        }

        // Fallback: simple alternating (no schedule available)
        $this->punchPairProcessor->inferDirections($logs);

        return ['logs' => $logs, 'droppedCount' => 0];
    }

    /**
     * Build expected schedule events (shift start/end, break start/end)
     * for matching against actual punches.
     *
     * @return array<int, array{time: Carbon, direction: \App\Enums\PunchType}>
     */
    protected function buildScheduleEvents(
        ?\App\Models\WorkSchedule $schedule,
        Carbon $date,
        ?string $shiftName
    ): array {
        if ($schedule === null) {
            return [];
        }

        $start = $this->scheduleResolver->getScheduledStartTime($schedule, $date, $shiftName);
        $end = $this->scheduleResolver->getScheduledEndTime($schedule, $date, $shiftName);

        if ($start === null || $end === null) {
            return [];
        }

        $events = [
            ['time' => $start, 'direction' => \App\Enums\PunchType::In],
        ];

        // Add break events if break is configured
        $config = $schedule->time_configuration ?? [];
        $breakStartTime = null;
        $breakDuration = null;

        if ($schedule->schedule_type === \App\Enums\ScheduleType::Shifting && $shiftName !== null) {
            foreach ($config['shifts'] ?? [] as $shift) {
                if (($shift['name'] ?? '') === $shiftName) {
                    $breakStartTime = $shift['break']['start_time'] ?? null;
                    $breakDuration = $shift['break']['duration_minutes'] ?? null;

                    break;
                }
            }
        } else {
            $breakStartTime = $config['break']['start_time'] ?? null;
            $breakDuration = $config['break']['duration_minutes'] ?? null;
        }

        if ($breakStartTime !== null && $breakDuration !== null) {
            $breakStart = $this->parseTimeOnDate($breakStartTime, $date);
            $breakEnd = $breakStart->copy()->addMinutes((int) $breakDuration);

            $events[] = ['time' => $breakStart, 'direction' => \App\Enums\PunchType::Out];
            $events[] = ['time' => $breakEnd, 'direction' => \App\Enums\PunchType::In];
        }

        $events[] = ['time' => $end, 'direction' => \App\Enums\PunchType::Out];

        return $events;
    }

    /**
     * Parse a time string onto a specific date.
     */
    protected function parseTimeOnDate(string $time, Carbon $date): Carbon
    {
        $parts = explode(':', $time);

        return $date->copy()->setTime((int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0), 0);
    }

    /**
     * Get mandatory break duration in minutes from schedule configuration.
     */
    protected function getMandatoryBreakMinutes(\App\Models\WorkSchedule $schedule, ?string $shiftName): int
    {
        $config = $schedule->time_configuration ?? [];

        // For shifting schedules, get break from specific shift
        if ($schedule->schedule_type === \App\Enums\ScheduleType::Shifting && $shiftName !== null) {
            $shifts = $config['shifts'] ?? [];
            foreach ($shifts as $shift) {
                if (($shift['name'] ?? '') === $shiftName) {
                    return (int) ($shift['break']['duration_minutes'] ?? 0);
                }
            }
        }

        // For other schedule types, get break from top-level config
        return (int) ($config['break']['duration_minutes'] ?? 0);
    }

    /**
     * Check if the work period spans the scheduled break time.
     */
    protected function workSpansBreakPeriod(
        ?Carbon $firstIn,
        ?Carbon $lastOut,
        \App\Models\WorkSchedule $schedule,
        Carbon $date,
        ?string $shiftName
    ): bool {
        if ($firstIn === null || $lastOut === null) {
            return false;
        }

        $config = $schedule->time_configuration ?? [];
        $breakStartTime = null;

        // For shifting schedules, get break start from specific shift
        if ($schedule->schedule_type === \App\Enums\ScheduleType::Shifting && $shiftName !== null) {
            $shifts = $config['shifts'] ?? [];
            foreach ($shifts as $shift) {
                if (($shift['name'] ?? '') === $shiftName) {
                    $breakStartTime = $shift['break']['start_time'] ?? null;
                    break;
                }
            }
        } else {
            $breakStartTime = $config['break']['start_time'] ?? null;
        }

        // If no break start time configured, assume break is mandatory for full-day work
        if ($breakStartTime === null) {
            // If worked more than 5 hours, assume break should be taken
            $workedMinutes = $firstIn->diffInMinutes($lastOut);

            return $workedMinutes > 300; // 5 hours
        }

        // Parse break start time on the work date
        $timeParts = explode(':', $breakStartTime);
        $breakStart = $date->copy()->setTime((int) $timeParts[0], (int) ($timeParts[1] ?? 0), 0);

        // Check if work period spans the break start time
        return $firstIn->lte($breakStart) && $lastOut->gt($breakStart);
    }
}
