<?php

namespace App\Services\Dtr;

use App\Enums\ScheduleType;
use App\Models\WorkSchedule;
use Carbon\Carbon;

/**
 * Calculates time-based values (late, undertime, overtime, night differential).
 */
class TimeCalculator
{
    public function __construct(
        protected ScheduleResolver $scheduleResolver
    ) {}

    /**
     * Calculate late minutes based on first punch-in time and schedule.
     */
    public function calculateLate(
        Carbon $firstIn,
        WorkSchedule $schedule,
        Carbon $date,
        ?string $shiftName = null
    ): int {
        $scheduleStart = $this->scheduleResolver->getScheduledStartTime($schedule, $date, $shiftName);

        if ($scheduleStart === null) {
            return 0;
        }

        // For flexible schedules, use core hours start time
        if ($schedule->schedule_type === ScheduleType::Flexible) {
            $config = $schedule->time_configuration;
            if (isset($config['core_hours']['start_time'])) {
                $scheduleStart = $this->parseTimeOnDate($config['core_hours']['start_time'], $date);
            }
        }

        // Calculate late minutes
        if ($firstIn->gt($scheduleStart)) {
            return $scheduleStart->diffInMinutes($firstIn);
        }

        return 0;
    }

    /**
     * Calculate undertime minutes based on last punch-out time and schedule.
     */
    public function calculateUndertime(
        Carbon $lastOut,
        WorkSchedule $schedule,
        Carbon $date,
        ?string $shiftName = null
    ): int {
        $scheduleEnd = $this->scheduleResolver->getScheduledEndTime($schedule, $date, $shiftName);

        if ($scheduleEnd === null) {
            return 0;
        }

        // For flexible schedules, use core hours end time
        if ($schedule->schedule_type === ScheduleType::Flexible) {
            $config = $schedule->time_configuration;
            if (isset($config['core_hours']['end_time'])) {
                $scheduleEnd = $this->parseTimeOnDate($config['core_hours']['end_time'], $date);
            }
        }

        // Calculate undertime minutes
        if ($lastOut->lt($scheduleEnd)) {
            return $lastOut->diffInMinutes($scheduleEnd);
        }

        return 0;
    }

    /**
     * Calculate overtime minutes based on work done after scheduled end time.
     */
    public function calculateOvertime(
        Carbon $lastOut,
        int $totalWorkMinutes,
        WorkSchedule $schedule,
        Carbon $date,
        ?string $shiftName = null
    ): int {
        $scheduleEnd = $this->scheduleResolver->getScheduledEndTime($schedule, $date, $shiftName);
        $requiredMinutes = $this->scheduleResolver->getRequiredWorkMinutes($schedule, $date);

        if ($scheduleEnd === null) {
            return 0;
        }

        // Check overtime rules threshold
        $config = $schedule->overtime_rules ?? [];
        $dailyThresholdMinutes = ((int) ($config['daily_threshold_hours'] ?? 8)) * 60;

        // OT is calculated when:
        // 1. Employee worked past scheduled end time
        // 2. Total work exceeds daily threshold
        $overtimeMinutes = 0;

        // Calculate OT from work beyond schedule end time
        if ($lastOut->gt($scheduleEnd)) {
            $overtimeMinutes = $scheduleEnd->diffInMinutes($lastOut);
        }

        // Also check if total work exceeds threshold
        $excessWorkMinutes = max(0, $totalWorkMinutes - $dailyThresholdMinutes);
        if ($excessWorkMinutes > $overtimeMinutes) {
            $overtimeMinutes = $excessWorkMinutes;
        }

        return $overtimeMinutes;
    }

    /**
     * Calculate night differential minutes based on work during ND hours.
     *
     * @param  array<int, array{in: Carbon, out: Carbon}>  $workPeriods
     */
    public function calculateNightDifferential(
        array $workPeriods,
        WorkSchedule $schedule
    ): int {
        $ndConfig = $schedule->night_differential ?? [];

        // Check if night differential is enabled
        if (! isset($ndConfig['enabled']) || ! $ndConfig['enabled']) {
            return 0;
        }

        $ndStartTime = $ndConfig['start_time'] ?? '22:00';
        $ndEndTime = $ndConfig['end_time'] ?? '06:00';

        $totalNdMinutes = 0;

        foreach ($workPeriods as $period) {
            if (! isset($period['in']) || ! isset($period['out'])) {
                continue;
            }

            $workStart = $period['in'];
            $workEnd = $period['out'];

            // Get ND period for the work date
            $ndStart = $this->parseTimeOnDate($ndStartTime, $workStart);
            $ndEnd = $this->parseTimeOnDate($ndEndTime, $workStart);

            // ND period typically crosses midnight (22:00 - 06:00)
            if ($ndEnd->lt($ndStart)) {
                $ndEnd->addDay();
            }

            // Calculate overlap between work period and ND period
            $totalNdMinutes += $this->calculateOverlap($workStart, $workEnd, $ndStart, $ndEnd);

            // Also check the previous day's ND period (for early morning shifts)
            $prevNdStart = $ndStart->copy()->subDay();
            $prevNdEnd = $ndEnd->copy()->subDay();
            $totalNdMinutes += $this->calculateOverlap($workStart, $workEnd, $prevNdStart, $prevNdEnd);
        }

        return $totalNdMinutes;
    }

    /**
     * Calculate the overlap in minutes between two time ranges.
     */
    protected function calculateOverlap(Carbon $start1, Carbon $end1, Carbon $start2, Carbon $end2): int
    {
        $overlapStart = $start1->gt($start2) ? $start1 : $start2;
        $overlapEnd = $end1->lt($end2) ? $end1 : $end2;

        if ($overlapStart->gte($overlapEnd)) {
            return 0;
        }

        return $overlapStart->diffInMinutes($overlapEnd);
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

    /**
     * Convert punch pairs array to work periods with Carbon objects.
     *
     * @param  array<int, array{in: mixed, out: mixed|null}>  $punchPairs
     * @return array<int, array{in: Carbon, out: Carbon}>
     */
    public function convertPunchPairsToWorkPeriods(array $punchPairs): array
    {
        $workPeriods = [];

        foreach ($punchPairs as $pair) {
            if ($pair['in'] === null || $pair['out'] === null) {
                continue;
            }

            $workPeriods[] = [
                'in' => Carbon::parse($pair['in']->logged_at),
                'out' => Carbon::parse($pair['out']->logged_at),
            ];
        }

        return $workPeriods;
    }
}
