<?php

namespace App\Services\Payroll;

use App\Enums\EarningType;
use App\Enums\HolidayType;
use App\Enums\PayrollCycleType;
use App\Enums\PayType;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollPeriod;
use Illuminate\Support\Collection;

/**
 * Calculates earnings for payroll computation.
 *
 * Computes basic pay, overtime pay, night differential, holiday pay,
 * and other earnings based on DTR data and employee compensation.
 */
class EarningsCalculator
{
    public function __construct(
        protected PayrollRateCalculator $rateCalculator,
        protected DtrAggregationService $dtrService,
        protected AdjustmentService $adjustmentService
    ) {}

    /**
     * Calculate all earnings for an employee in a payroll period.
     *
     * @return array{
     *     basic_pay: float,
     *     overtime_pay: float,
     *     night_diff_pay: float,
     *     holiday_pay: float,
     *     allowances_total: float,
     *     bonuses_total: float,
     *     gross_pay: float,
     *     line_items: Collection
     * }
     */
    public function calculate(Employee $employee, PayrollPeriod $period, array $dtrSummary): array
    {
        $compensation = $employee->compensation;

        if (! $compensation) {
            return $this->emptyResult();
        }

        $lineItems = collect();

        $basicPay = $this->calculateBasicPay($employee, $period, $dtrSummary, $lineItems);
        $overtimePay = $this->calculateOvertimePay($employee, $dtrSummary, $lineItems);
        $nightDiffPay = $this->calculateNightDifferentialPay($employee, $dtrSummary, $lineItems);
        $holidayPay = $this->calculateHolidayPay($employee, $dtrSummary, $lineItems);
        $allowancesTotal = $this->calculateAllowances($employee, $period, $lineItems);
        $bonusesTotal = $this->calculateBonuses($employee, $period, $lineItems);

        $grossPay = $basicPay + $overtimePay + $nightDiffPay + $holidayPay + $allowancesTotal + $bonusesTotal;

        return [
            'basic_pay' => round($basicPay, 2),
            'overtime_pay' => round($overtimePay, 2),
            'night_diff_pay' => round($nightDiffPay, 2),
            'holiday_pay' => round($holidayPay, 2),
            'allowances_total' => round($allowancesTotal, 2),
            'bonuses_total' => round($bonusesTotal, 2),
            'gross_pay' => round($grossPay, 2),
            'line_items' => $lineItems,
        ];
    }

    /**
     * Calculate basic pay based on pay type and attendance.
     */
    protected function calculateBasicPay(
        Employee $employee,
        PayrollPeriod $period,
        array $dtrSummary,
        Collection $lineItems
    ): float {
        $compensation = $employee->compensation;
        $basicPay = (float) $compensation->basic_pay;
        $payType = $compensation->pay_type;

        $daysWorked = $dtrSummary['days_worked'];
        $absentDays = $dtrSummary['absent_days'];
        $lateMinutes = $dtrSummary['total_late_minutes'];
        $undertimeMinutes = $dtrSummary['total_undertime_minutes'];

        $dailyRate = $this->rateCalculator->calculateDailyRate($basicPay, $payType);
        $minuteRate = $this->rateCalculator->calculateMinuteRate($dailyRate);

        $cycleType = $period->payrollCycle?->cycle_type ?? PayrollCycleType::SemiMonthly;
        $isSecondCutoff = $period->period_number % 2 === 0;

        $calculatedBasicPay = match ($payType) {
            PayType::Monthly => $cycleType === PayrollCycleType::SemiMonthly
                ? $basicPay / 2
                : $basicPay,
            PayType::SemiMonthly => $basicPay,
            PayType::Daily => $dailyRate * $daysWorked,
            PayType::Weekly => $basicPay * ceil($daysWorked / 5),
        };

        $lineItems->push([
            'earning_type' => EarningType::BasicPay,
            'earning_code' => 'BASIC',
            'description' => 'Basic Pay',
            'quantity' => $payType === PayType::Daily ? $daysWorked : 1,
            'quantity_unit' => $payType === PayType::Daily ? 'days' : 'period',
            'rate' => $payType === PayType::Daily ? $dailyRate : $calculatedBasicPay,
            'multiplier' => 1.00,
            'amount' => $calculatedBasicPay,
            'is_taxable' => true,
        ]);

        $absenceDeduction = 0;
        if ($absentDays > 0 && in_array($payType, [PayType::Monthly, PayType::SemiMonthly])) {
            $absenceDeduction = $this->rateCalculator->calculateAbsenceDeduction($dailyRate, $absentDays);
            $calculatedBasicPay -= $absenceDeduction;

            $lineItems->push([
                'earning_type' => EarningType::Adjustment,
                'earning_code' => 'ABSENT',
                'description' => 'Absence Deduction',
                'quantity' => $absentDays,
                'quantity_unit' => 'days',
                'rate' => $dailyRate,
                'multiplier' => -1.00,
                'amount' => -$absenceDeduction,
                'is_taxable' => true,
            ]);
        }

        $tardinessDeduction = 0;
        if (($lateMinutes + $undertimeMinutes) > 0) {
            $tardinessDeduction = $this->rateCalculator->calculateTardinessDeduction(
                $minuteRate,
                $lateMinutes,
                $undertimeMinutes
            );
            $calculatedBasicPay -= $tardinessDeduction;

            $lineItems->push([
                'earning_type' => EarningType::Adjustment,
                'earning_code' => 'TARDINESS',
                'description' => 'Late/Undertime Deduction',
                'quantity' => $lateMinutes + $undertimeMinutes,
                'quantity_unit' => 'minutes',
                'rate' => $minuteRate,
                'multiplier' => -1.00,
                'amount' => -$tardinessDeduction,
                'is_taxable' => true,
            ]);
        }

        return max(0, $calculatedBasicPay);
    }

    /**
     * Calculate overtime pay.
     */
    protected function calculateOvertimePay(Employee $employee, array $dtrSummary, Collection $lineItems): float
    {
        $overtimeMinutes = $dtrSummary['total_overtime_minutes'];

        if ($overtimeMinutes <= 0) {
            return 0;
        }

        $hourlyRate = $this->rateCalculator->getHourlyRate($employee);
        $multiplier = PayrollRateCalculator::OT_MULTIPLIER_REGULAR;

        $overtimePay = $this->rateCalculator->calculateOvertimePay(
            $overtimeMinutes,
            $hourlyRate,
            $multiplier
        );

        if ($overtimePay > 0) {
            $hours = round($overtimeMinutes / 60, 2);
            $lineItems->push([
                'earning_type' => EarningType::Overtime,
                'earning_code' => 'OT_REG',
                'description' => 'Regular Overtime',
                'quantity' => $hours,
                'quantity_unit' => 'hours',
                'rate' => $hourlyRate,
                'multiplier' => $multiplier,
                'amount' => $overtimePay,
                'is_taxable' => true,
            ]);
        }

        return $overtimePay;
    }

    /**
     * Calculate night differential pay.
     */
    protected function calculateNightDifferentialPay(Employee $employee, array $dtrSummary, Collection $lineItems): float
    {
        $nightDiffMinutes = $dtrSummary['total_night_diff_minutes'];

        if ($nightDiffMinutes <= 0) {
            return 0;
        }

        $hourlyRate = $this->rateCalculator->getHourlyRate($employee);

        $nightDiffPay = $this->rateCalculator->calculateNightDifferentialPay(
            $nightDiffMinutes,
            $hourlyRate
        );

        if ($nightDiffPay > 0) {
            $hours = round($nightDiffMinutes / 60, 2);
            $lineItems->push([
                'earning_type' => EarningType::NightDifferential,
                'earning_code' => 'ND',
                'description' => 'Night Differential',
                'quantity' => $hours,
                'quantity_unit' => 'hours',
                'rate' => $hourlyRate * PayrollRateCalculator::NIGHT_DIFFERENTIAL_RATE,
                'multiplier' => 1.00,
                'amount' => $nightDiffPay,
                'is_taxable' => true,
            ]);
        }

        return $nightDiffPay;
    }

    /**
     * Calculate holiday pay.
     */
    protected function calculateHolidayPay(Employee $employee, array $dtrSummary, Collection $lineItems): float
    {
        $holidayRecords = $dtrSummary['holiday_records'] ?? collect();

        if ($holidayRecords->isEmpty()) {
            return 0;
        }

        $dailyRate = $this->rateCalculator->getDailyRate($employee);
        $totalHolidayPay = 0;

        $groupedByType = $holidayRecords->groupBy(fn ($record) => $record['holiday']->holiday_type->value);

        foreach ($groupedByType as $type => $records) {
            $holidayType = HolidayType::from($type);
            $daysCount = $records->count();
            $multiplier = $this->rateCalculator->getHolidayMultiplier($holidayType);

            $holidayPay = $this->rateCalculator->calculateHolidayPay(
                $dailyRate,
                $holidayType,
                $daysCount
            );

            $totalHolidayPay += $holidayPay;

            $lineItems->push([
                'earning_type' => EarningType::HolidayPay,
                'earning_code' => 'HOLIDAY_'.strtoupper($type),
                'description' => $holidayType->label().' Pay',
                'quantity' => $daysCount,
                'quantity_unit' => 'days',
                'rate' => $dailyRate,
                'multiplier' => $multiplier,
                'amount' => $holidayPay,
                'is_taxable' => true,
            ]);
        }

        return $totalHolidayPay;
    }

    /**
     * Calculate allowances from employee adjustments.
     */
    protected function calculateAllowances(Employee $employee, PayrollPeriod $period, Collection $lineItems): float
    {
        return $this->adjustmentService->calculateAllowanceAdjustments($employee, $period, $lineItems);
    }

    /**
     * Calculate bonuses from employee adjustments.
     */
    protected function calculateBonuses(Employee $employee, PayrollPeriod $period, Collection $lineItems): float
    {
        return $this->adjustmentService->calculateBonusAdjustments($employee, $period, $lineItems);
    }

    /**
     * Return empty result when no compensation data available.
     *
     * @return array<string, mixed>
     */
    protected function emptyResult(): array
    {
        return [
            'basic_pay' => 0,
            'overtime_pay' => 0,
            'night_diff_pay' => 0,
            'holiday_pay' => 0,
            'allowances_total' => 0,
            'bonuses_total' => 0,
            'gross_pay' => 0,
            'line_items' => collect(),
        ];
    }
}
