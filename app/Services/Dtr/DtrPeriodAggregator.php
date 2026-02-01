<?php

namespace App\Services\Dtr;

use App\Enums\DtrStatus;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregates daily time records for reporting periods.
 */
class DtrPeriodAggregator
{
    /**
     * Get summary statistics for an employee over a date range.
     *
     * @return array<string, mixed>
     */
    public function getSummary(Employee $employee, Carbon $startDate, Carbon $endDate): array
    {
        $records = DailyTimeRecord::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        return $this->aggregateRecords($records, $startDate, $endDate);
    }

    /**
     * Get summary statistics for a collection of DTR records.
     *
     * @param  Collection<int, DailyTimeRecord>  $records
     * @return array<string, mixed>
     */
    public function aggregateRecords(Collection $records, Carbon $startDate, Carbon $endDate): array
    {
        $totalDays = $startDate->diffInDays($endDate) + 1;

        $presentDays = $records->where('status', DtrStatus::Present)->count();
        $absentDays = $records->where('status', DtrStatus::Absent)->count();
        $holidayDays = $records->where('status', DtrStatus::Holiday)->count();
        $restDays = $records->where('status', DtrStatus::RestDay)->count();
        $noScheduleDays = $records->where('status', DtrStatus::NoSchedule)->count();

        $totalWorkMinutes = $records->sum('total_work_minutes');
        $totalBreakMinutes = $records->sum('total_break_minutes');
        $totalLateMinutes = $records->sum('late_minutes');
        $totalUndertimeMinutes = $records->sum('undertime_minutes');
        $totalOvertimeMinutes = $records->sum('overtime_minutes');
        $approvedOvertimeMinutes = $records->where('overtime_approved', true)->sum('overtime_minutes');
        $totalNightDiffMinutes = $records->sum('night_diff_minutes');

        $lateDays = $records->where('late_minutes', '>', 0)->count();
        $undertimeDays = $records->where('undertime_minutes', '>', 0)->count();
        $overtimeDays = $records->where('overtime_minutes', '>', 0)->count();
        $needsReviewCount = $records->where('needs_review', true)->count();

        return [
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'total_days' => $totalDays,
            ],
            'attendance' => [
                'present_days' => $presentDays,
                'absent_days' => $absentDays,
                'holiday_days' => $holidayDays,
                'rest_days' => $restDays,
                'no_schedule_days' => $noScheduleDays,
                'attendance_rate' => $this->calculateAttendanceRate($presentDays, $absentDays),
            ],
            'time_summary' => [
                'total_work_minutes' => $totalWorkMinutes,
                'total_work_hours' => round($totalWorkMinutes / 60, 2),
                'total_break_minutes' => $totalBreakMinutes,
                'total_break_hours' => round($totalBreakMinutes / 60, 2),
                'average_daily_work_minutes' => $presentDays > 0 ? round($totalWorkMinutes / $presentDays) : 0,
                'average_daily_work_hours' => $presentDays > 0 ? round($totalWorkMinutes / $presentDays / 60, 2) : 0,
            ],
            'late_undertime' => [
                'total_late_minutes' => $totalLateMinutes,
                'total_late_hours' => round($totalLateMinutes / 60, 2),
                'late_days' => $lateDays,
                'total_undertime_minutes' => $totalUndertimeMinutes,
                'total_undertime_hours' => round($totalUndertimeMinutes / 60, 2),
                'undertime_days' => $undertimeDays,
            ],
            'overtime' => [
                'total_overtime_minutes' => $totalOvertimeMinutes,
                'total_overtime_hours' => round($totalOvertimeMinutes / 60, 2),
                'approved_overtime_minutes' => $approvedOvertimeMinutes,
                'approved_overtime_hours' => round($approvedOvertimeMinutes / 60, 2),
                'pending_overtime_minutes' => $totalOvertimeMinutes - $approvedOvertimeMinutes,
                'pending_overtime_hours' => round(($totalOvertimeMinutes - $approvedOvertimeMinutes) / 60, 2),
                'overtime_days' => $overtimeDays,
            ],
            'night_differential' => [
                'total_night_diff_minutes' => $totalNightDiffMinutes,
                'total_night_diff_hours' => round($totalNightDiffMinutes / 60, 2),
            ],
            'review' => [
                'needs_review_count' => $needsReviewCount,
            ],
        ];
    }

    /**
     * Get daily breakdown for an employee over a date range.
     *
     * @param  Collection<int, DailyTimeRecord>  $records
     * @return Collection<int, array<string, mixed>>
     */
    public function getDailyBreakdown(Collection $records): Collection
    {
        return $records->map(function (DailyTimeRecord $record) {
            return [
                'id' => $record->id,
                'date' => $record->date->toDateString(),
                'day_of_week' => $record->date->englishDayOfWeek,
                'status' => $record->status->value,
                'status_label' => $record->status->label(),
                'first_in' => $record->first_in?->format('H:i'),
                'last_out' => $record->last_out?->format('H:i'),
                'total_work_hours' => $record->total_work_hours,
                'late_minutes' => $record->late_minutes,
                'undertime_minutes' => $record->undertime_minutes,
                'overtime_minutes' => $record->overtime_minutes,
                'overtime_approved' => $record->overtime_approved,
                'night_diff_minutes' => $record->night_diff_minutes,
                'needs_review' => $record->needs_review,
                'review_reason' => $record->review_reason,
            ];
        });
    }

    /**
     * Calculate attendance rate percentage.
     */
    protected function calculateAttendanceRate(int $presentDays, int $absentDays): float
    {
        $totalWorkDays = $presentDays + $absentDays;

        if ($totalWorkDays === 0) {
            return 100.0;
        }

        return round(($presentDays / $totalWorkDays) * 100, 2);
    }

    /**
     * Get department-wide summary.
     *
     * @return array<string, mixed>
     */
    public function getDepartmentSummary(int $departmentId, Carbon $startDate, Carbon $endDate): array
    {
        $records = DailyTimeRecord::query()
            ->whereHas('employee', function ($query) use ($departmentId) {
                $query->where('department_id', $departmentId);
            })
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        $employeeCount = $records->pluck('employee_id')->unique()->count();
        $aggregated = $this->aggregateRecords($records, $startDate, $endDate);

        return array_merge($aggregated, [
            'department_id' => $departmentId,
            'employee_count' => $employeeCount,
        ]);
    }
}
