<?php

namespace App\Services\Payroll;

use App\Enums\DtrStatus;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregates DTR data for payroll computation.
 *
 * Provides methods to summarize attendance records for a payroll period,
 * including regular work, overtime, night differential, and holiday work.
 */
class DtrAggregationService
{
    /**
     * Aggregate DTR data for an employee within a payroll period.
     *
     * @return array{
     *     days_worked: float,
     *     total_regular_minutes: int,
     *     total_late_minutes: int,
     *     total_undertime_minutes: int,
     *     total_overtime_minutes: int,
     *     total_night_diff_minutes: int,
     *     absent_days: float,
     *     holiday_days: float,
     *     holiday_records: Collection,
     *     dtr_records: Collection
     * }
     */
    public function aggregateForPeriod(Employee $employee, PayrollPeriod $period): array
    {
        return $this->aggregate(
            $employee,
            $period->cutoff_start,
            $period->cutoff_end
        );
    }

    /**
     * Aggregate DTR data for an employee within a date range.
     *
     * @return array{
     *     days_worked: float,
     *     total_regular_minutes: int,
     *     total_late_minutes: int,
     *     total_undertime_minutes: int,
     *     total_overtime_minutes: int,
     *     total_night_diff_minutes: int,
     *     absent_days: float,
     *     holiday_days: float,
     *     holiday_records: Collection,
     *     dtr_records: Collection
     * }
     */
    public function aggregate(Employee $employee, Carbon $startDate, Carbon $endDate): array
    {
        $records = DailyTimeRecord::query()
            ->with('overtimeRequest')
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        $holidays = $this->getHolidaysInRange($startDate, $endDate, $employee->work_location_id);

        return $this->computeAggregates($records, $holidays);
    }

    /**
     * Compute aggregates from DTR records.
     *
     * @param  Collection<int, DailyTimeRecord>  $records
     * @param  Collection<int, Holiday>  $holidays
     * @return array<string, mixed>
     */
    protected function computeAggregates(Collection $records, Collection $holidays): array
    {
        $presentRecords = $records->whereIn('status', [DtrStatus::Present, DtrStatus::Holiday]);
        $absentRecords = $records->where('status', DtrStatus::Absent);

        $daysWorked = $presentRecords->count();
        $absentDays = $absentRecords->count();

        $totalRegularMinutes = $records->sum('total_work_minutes');
        $totalLateMinutes = $records->sum('late_minutes');
        $totalUndertimeMinutes = $records->sum('undertime_minutes');

        $totalOvertimeMinutes = $records
            ->where('overtime_approved', true)
            ->filter(fn ($record) => $record->overtime_request_id !== null)
            ->sum(function ($record) {
                $approved = $record->overtimeRequest?->expected_minutes ?? 0;

                return min($record->overtime_minutes, $approved);
            });

        $totalNightDiffMinutes = $records->sum('night_diff_minutes');

        $holidayRecords = $this->getHolidayWorkRecords($records, $holidays);
        $holidayDays = $holidayRecords->count();

        return [
            'days_worked' => (float) $daysWorked,
            'total_regular_minutes' => (int) $totalRegularMinutes,
            'total_late_minutes' => (int) $totalLateMinutes,
            'total_undertime_minutes' => (int) $totalUndertimeMinutes,
            'total_overtime_minutes' => (int) $totalOvertimeMinutes,
            'total_night_diff_minutes' => (int) $totalNightDiffMinutes,
            'absent_days' => (float) $absentDays,
            'holiday_days' => (float) $holidayDays,
            'holiday_records' => $holidayRecords,
            'dtr_records' => $records,
        ];
    }

    /**
     * Get holidays within a date range.
     *
     * @return Collection<int, Holiday>
     */
    protected function getHolidaysInRange(Carbon $startDate, Carbon $endDate, ?int $workLocationId): Collection
    {
        $query = Holiday::query()
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()]);

        if ($workLocationId) {
            $query->where(function ($q) use ($workLocationId) {
                $q->where('is_national', true)
                    ->orWhere('work_location_id', $workLocationId);
            });
        } else {
            $query->where('is_national', true);
        }

        return $query->get();
    }

    /**
     * Match DTR records with holiday data.
     *
     * @param  Collection<int, DailyTimeRecord>  $records
     * @param  Collection<int, Holiday>  $holidays
     * @return Collection<int, array{dtr: DailyTimeRecord, holiday: Holiday}>
     */
    protected function getHolidayWorkRecords(Collection $records, Collection $holidays): Collection
    {
        $holidayDates = $holidays->keyBy(fn (Holiday $h) => $h->date->toDateString());

        return $records
            ->filter(function (DailyTimeRecord $record) use ($holidayDates) {
                return $holidayDates->has($record->date->toDateString())
                    && $record->status === DtrStatus::Holiday;
            })
            ->map(function (DailyTimeRecord $record) use ($holidayDates) {
                return [
                    'dtr' => $record,
                    'holiday' => $holidayDates->get($record->date->toDateString()),
                ];
            });
    }

    /**
     * Get overtime breakdown by type (regular, rest day, holiday).
     *
     * @param  Collection<int, DailyTimeRecord>  $records
     * @param  Collection<int, Holiday>  $holidays
     * @return array<string, int>
     */
    public function getOvertimeBreakdown(Collection $records, Collection $holidays): array
    {
        $holidayDates = $holidays->keyBy(fn (Holiday $h) => $h->date->toDateString());

        $breakdown = [
            'regular' => 0,
            'rest_day' => 0,
            'special_holiday' => 0,
            'regular_holiday' => 0,
            'double_holiday' => 0,
        ];

        foreach ($records->where('overtime_approved', true)->filter(fn ($r) => $r->overtime_request_id !== null) as $record) {
            $dateString = $record->date->toDateString();
            $approved = $record->overtimeRequest?->expected_minutes ?? 0;
            $overtimeMinutes = min($record->overtime_minutes, $approved);

            if ($overtimeMinutes <= 0) {
                continue;
            }

            if ($holidayDates->has($dateString)) {
                $holiday = $holidayDates->get($dateString);
                $key = match ($holiday->holiday_type->value) {
                    'double' => 'double_holiday',
                    'regular' => 'regular_holiday',
                    default => 'special_holiday',
                };
                $breakdown[$key] += $overtimeMinutes;
            } elseif ($record->status === DtrStatus::RestDay) {
                $breakdown['rest_day'] += $overtimeMinutes;
            } else {
                $breakdown['regular'] += $overtimeMinutes;
            }
        }

        return $breakdown;
    }
}
