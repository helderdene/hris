<?php

namespace App\Services;

use App\Enums\PayrollCycleType;
use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollPeriodType;
use App\Models\PayrollCycle;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Service for generating and managing payroll periods.
 *
 * This service provides methods to generate payroll periods for a given cycle
 * and year, handling different cycle types (semi-monthly, monthly) and
 * calculating appropriate cutoff dates and pay dates.
 */
class PayrollPeriodService
{
    /**
     * Generate payroll periods for a cycle and year.
     *
     * @param  bool  $overwriteExisting  Whether to delete existing periods first
     * @return Collection<int, PayrollPeriod>
     *
     * @throws \InvalidArgumentException If the cycle type doesn't support generation
     */
    public function generatePeriodsForYear(
        PayrollCycle $cycle,
        int $year,
        bool $overwriteExisting = false
    ): Collection {
        if (! $cycle->isRecurring()) {
            throw new \InvalidArgumentException(
                "Cannot generate recurring periods for {$cycle->cycle_type->label()} cycles."
            );
        }

        if ($overwriteExisting) {
            // Force delete to avoid unique constraint issues with soft-deleted records
            PayrollPeriod::query()
                ->forCycle($cycle->id)
                ->forYear($year)
                ->byStatus(PayrollPeriodStatus::Draft)
                ->forceDelete();
        }

        return match ($cycle->cycle_type) {
            PayrollCycleType::SemiMonthly => $this->generateSemiMonthlyPeriods($cycle, $year),
            PayrollCycleType::Monthly => $this->generateMonthlyPeriods($cycle, $year),
            default => collect(),
        };
    }

    /**
     * Generate semi-monthly payroll periods (24 periods per year).
     *
     * @return Collection<int, PayrollPeriod>
     */
    public function generateSemiMonthlyPeriods(PayrollCycle $cycle, int $year): Collection
    {
        $periods = collect();
        $rules = $cycle->cutoff_rules ?? PayrollCycle::getDefaultCutoffRules(PayrollCycleType::SemiMonthly);

        $firstHalfRules = $rules['first_half'] ?? [
            'start_day' => 1,
            'end_day' => 15,
            'pay_day' => 25,
            'pay_day_adjustment' => 'before',
        ];

        $secondHalfRules = $rules['second_half'] ?? [
            'start_day' => 16,
            'end_day' => 'last',
            'pay_day' => 10,
            'pay_day_month_offset' => 1,
            'pay_day_adjustment' => 'before',
        ];

        for ($month = 1; $month <= 12; $month++) {
            // First half of month (Period 1, 3, 5, ... 23)
            $periodNumber = ($month - 1) * 2 + 1;
            $firstHalfPeriod = $this->createPeriodIfNotExists(
                $cycle,
                $year,
                $periodNumber,
                $month,
                $firstHalfRules,
                true
            );

            if ($firstHalfPeriod) {
                $periods->push($firstHalfPeriod);
            }

            // Second half of month (Period 2, 4, 6, ... 24)
            $periodNumber = ($month - 1) * 2 + 2;
            $secondHalfPeriod = $this->createPeriodIfNotExists(
                $cycle,
                $year,
                $periodNumber,
                $month,
                $secondHalfRules,
                false
            );

            if ($secondHalfPeriod) {
                $periods->push($secondHalfPeriod);
            }
        }

        return $periods;
    }

    /**
     * Generate monthly payroll periods (12 periods per year).
     *
     * @return Collection<int, PayrollPeriod>
     */
    public function generateMonthlyPeriods(PayrollCycle $cycle, int $year): Collection
    {
        $periods = collect();
        $rules = $cycle->cutoff_rules ?? PayrollCycle::getDefaultCutoffRules(PayrollCycleType::Monthly);

        $startDay = $rules['start_day'] ?? 1;
        $endDay = $rules['end_day'] ?? 'last';
        $payDay = $rules['pay_day'] ?? 30;
        $payDayAdjustment = $rules['pay_day_adjustment'] ?? 'before';

        for ($month = 1; $month <= 12; $month++) {
            $cutoffStart = Carbon::create($year, $month, $startDay);
            $cutoffEnd = $this->calculateEndDay($year, $month, $endDay);
            $payDate = $this->calculatePayDate($year, $month, $payDay, 0, $payDayAdjustment);

            // Check if period already exists
            $existingPeriod = PayrollPeriod::query()
                ->forCycle($cycle->id)
                ->forYear($year)
                ->where('period_number', $month)
                ->first();

            if ($existingPeriod) {
                continue;
            }

            $period = PayrollPeriod::create([
                'payroll_cycle_id' => $cycle->id,
                'name' => $this->generatePeriodName($year, $month, PayrollCycleType::Monthly),
                'period_type' => PayrollPeriodType::Regular,
                'year' => $year,
                'period_number' => $month,
                'cutoff_start' => $cutoffStart,
                'cutoff_end' => $cutoffEnd,
                'pay_date' => $payDate,
                'status' => PayrollPeriodStatus::Draft,
            ]);

            $periods->push($period);
        }

        return $periods;
    }

    /**
     * Create a semi-monthly period if it doesn't already exist.
     *
     * @param  array<string, mixed>  $rules
     */
    protected function createPeriodIfNotExists(
        PayrollCycle $cycle,
        int $year,
        int $periodNumber,
        int $month,
        array $rules,
        bool $isFirstHalf
    ): ?PayrollPeriod {
        // Check if period already exists
        $existingPeriod = PayrollPeriod::query()
            ->forCycle($cycle->id)
            ->forYear($year)
            ->where('period_number', $periodNumber)
            ->first();

        if ($existingPeriod) {
            return null;
        }

        $startDay = $rules['start_day'] ?? ($isFirstHalf ? 1 : 16);
        $endDay = $rules['end_day'] ?? ($isFirstHalf ? 15 : 'last');
        $payDay = $rules['pay_day'] ?? ($isFirstHalf ? 25 : 10);
        $payDayMonthOffset = $rules['pay_day_month_offset'] ?? 0;
        $payDayAdjustment = $rules['pay_day_adjustment'] ?? 'before';

        $cutoffStart = Carbon::create($year, $month, $startDay);
        $cutoffEnd = $this->calculateEndDay($year, $month, $endDay);
        $payDate = $this->calculatePayDate($year, $month, $payDay, $payDayMonthOffset, $payDayAdjustment);

        return PayrollPeriod::create([
            'payroll_cycle_id' => $cycle->id,
            'name' => $this->generatePeriodName($year, $periodNumber, PayrollCycleType::SemiMonthly),
            'period_type' => PayrollPeriodType::Regular,
            'year' => $year,
            'period_number' => $periodNumber,
            'cutoff_start' => $cutoffStart,
            'cutoff_end' => $cutoffEnd,
            'pay_date' => $payDate,
            'status' => PayrollPeriodStatus::Draft,
        ]);
    }

    /**
     * Calculate the end day for a period, handling 'last' for end of month.
     */
    protected function calculateEndDay(int $year, int $month, int|string $endDay): Carbon
    {
        if ($endDay === 'last') {
            return Carbon::create($year, $month, 1)->endOfMonth()->startOfDay();
        }

        $maxDay = Carbon::create($year, $month, 1)->daysInMonth;
        $day = min((int) $endDay, $maxDay);

        return Carbon::create($year, $month, $day);
    }

    /**
     * Calculate the pay date, handling month offset and adjustments.
     */
    protected function calculatePayDate(
        int $year,
        int $month,
        int $payDay,
        int $monthOffset,
        string $adjustment
    ): Carbon {
        $payMonth = $month + $monthOffset;
        $payYear = $year;

        // Handle year overflow
        if ($payMonth > 12) {
            $payMonth = $payMonth - 12;
            $payYear++;
        }

        $maxDay = Carbon::create($payYear, $payMonth, 1)->daysInMonth;
        $day = min($payDay, $maxDay);

        $payDate = Carbon::create($payYear, $payMonth, $day);

        // Adjust if pay date falls on weekend
        if ($adjustment === 'before' && $payDate->isWeekend()) {
            // Move to previous Friday
            while ($payDate->isWeekend()) {
                $payDate->subDay();
            }
        } elseif ($adjustment === 'after' && $payDate->isWeekend()) {
            // Move to next Monday
            while ($payDate->isWeekend()) {
                $payDate->addDay();
            }
        }

        return $payDate;
    }

    /**
     * Generate a human-readable name for a period.
     */
    protected function generatePeriodName(int $year, int $periodNumber, PayrollCycleType $cycleType): string
    {
        if ($cycleType === PayrollCycleType::Monthly) {
            $monthName = Carbon::create($year, $periodNumber, 1)->format('F');

            return "{$monthName} {$year}";
        }

        // Semi-monthly: Calculate month and half
        $month = (int) ceil($periodNumber / 2);
        $isFirstHalf = $periodNumber % 2 === 1;
        $monthName = Carbon::create($year, $month, 1)->format('F');
        $half = $isFirstHalf ? '1st Half' : '2nd Half';

        return "{$monthName} {$year} - {$half}";
    }

    /**
     * Get the current open period for a cycle.
     */
    public function getCurrentOpenPeriod(PayrollCycle $cycle): ?PayrollPeriod
    {
        return PayrollPeriod::query()
            ->forCycle($cycle->id)
            ->byStatus(PayrollPeriodStatus::Open)
            ->orderBy('cutoff_start', 'desc')
            ->first();
    }

    /**
     * Find the period that contains a specific date.
     */
    public function findPeriodForDate(PayrollCycle $cycle, Carbon $date): ?PayrollPeriod
    {
        return PayrollPeriod::query()
            ->forCycle($cycle->id)
            ->containingDate($date->toDateString())
            ->first();
    }

    /**
     * Get summary statistics for periods in a year.
     *
     * @return array{
     *     total_periods: int,
     *     by_status: array<string, int>,
     *     total_gross: float,
     *     total_net: float,
     *     total_employees_paid: int
     * }
     */
    public function getYearSummary(PayrollCycle $cycle, int $year): array
    {
        $periods = PayrollPeriod::query()
            ->forCycle($cycle->id)
            ->forYear($year)
            ->get();

        $byStatus = [];
        foreach (PayrollPeriodStatus::cases() as $status) {
            $byStatus[$status->value] = $periods->where('status', $status)->count();
        }

        return [
            'total_periods' => $periods->count(),
            'by_status' => $byStatus,
            'total_gross' => (float) $periods->sum('total_gross'),
            'total_net' => (float) $periods->sum('total_net'),
            'total_employees_paid' => $periods->where('status', PayrollPeriodStatus::Closed)->sum('employee_count'),
        ];
    }
}
