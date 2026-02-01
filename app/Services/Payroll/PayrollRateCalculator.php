<?php

namespace App\Services\Payroll;

use App\Enums\HolidayType;
use App\Enums\PayType;
use App\Models\Employee;
use App\Models\EmployeeCompensation;

/**
 * Calculates payroll rates based on employee compensation and DOLE guidelines.
 *
 * Provides methods to derive daily, hourly, and minute rates from basic salary,
 * as well as overtime and premium multipliers per Philippine labor law.
 */
class PayrollRateCalculator
{
    /**
     * Standard working days per month (DOLE standard).
     */
    public const WORKING_DAYS_PER_MONTH = 22;

    /**
     * Standard working hours per day.
     */
    public const WORKING_HOURS_PER_DAY = 8;

    /**
     * Standard working minutes per day.
     */
    public const WORKING_MINUTES_PER_DAY = 480;

    /**
     * Night differential premium rate (10% per DOLE).
     */
    public const NIGHT_DIFFERENTIAL_RATE = 0.10;

    /**
     * Overtime multipliers per DOLE Labor Code.
     */
    public const OT_MULTIPLIER_REGULAR = 1.25;

    public const OT_MULTIPLIER_REST_DAY = 1.30;

    public const OT_MULTIPLIER_SPECIAL_HOLIDAY = 1.30;

    public const OT_MULTIPLIER_REGULAR_HOLIDAY = 2.00;

    public const OT_MULTIPLIER_DOUBLE_HOLIDAY = 3.90;

    /**
     * Holiday work multipliers.
     */
    public const HOLIDAY_MULTIPLIER_SPECIAL = 1.30;

    public const HOLIDAY_MULTIPLIER_REGULAR = 2.00;

    public const HOLIDAY_MULTIPLIER_DOUBLE = 3.00;

    /**
     * Rest day work multiplier.
     */
    public const REST_DAY_MULTIPLIER = 1.30;

    /**
     * Get the daily rate for an employee.
     */
    public function getDailyRate(Employee $employee): float
    {
        $compensation = $employee->compensation;

        if (! $compensation) {
            return 0;
        }

        return $this->calculateDailyRate((float) $compensation->basic_pay, $compensation->pay_type);
    }

    /**
     * Calculate daily rate from basic pay and pay type.
     */
    public function calculateDailyRate(float $basicPay, PayType $payType): float
    {
        return match ($payType) {
            PayType::Monthly => round($basicPay / self::WORKING_DAYS_PER_MONTH, 4),
            PayType::SemiMonthly => round(($basicPay * 2) / self::WORKING_DAYS_PER_MONTH, 4),
            PayType::Weekly => round($basicPay / 5, 4),
            PayType::Daily => $basicPay,
        };
    }

    /**
     * Get the hourly rate for an employee.
     */
    public function getHourlyRate(Employee $employee): float
    {
        return round($this->getDailyRate($employee) / self::WORKING_HOURS_PER_DAY, 4);
    }

    /**
     * Calculate hourly rate from daily rate.
     */
    public function calculateHourlyRate(float $dailyRate): float
    {
        return round($dailyRate / self::WORKING_HOURS_PER_DAY, 4);
    }

    /**
     * Get the minute rate for an employee.
     */
    public function getMinuteRate(Employee $employee): float
    {
        return round($this->getDailyRate($employee) / self::WORKING_MINUTES_PER_DAY, 6);
    }

    /**
     * Calculate minute rate from daily rate.
     */
    public function calculateMinuteRate(float $dailyRate): float
    {
        return round($dailyRate / self::WORKING_MINUTES_PER_DAY, 6);
    }

    /**
     * Get overtime multiplier based on work context.
     *
     * @param  bool  $isRestDay  Whether work is on a rest day
     * @param  HolidayType|null  $holidayType  Type of holiday if applicable
     */
    public function getOvertimeMultiplier(bool $isRestDay = false, ?HolidayType $holidayType = null): float
    {
        if ($holidayType !== null) {
            return match ($holidayType) {
                HolidayType::Double => self::OT_MULTIPLIER_DOUBLE_HOLIDAY,
                HolidayType::Regular => self::OT_MULTIPLIER_REGULAR_HOLIDAY,
                HolidayType::SpecialNonWorking, HolidayType::SpecialWorking => self::OT_MULTIPLIER_SPECIAL_HOLIDAY,
            };
        }

        if ($isRestDay) {
            return self::OT_MULTIPLIER_REST_DAY;
        }

        return self::OT_MULTIPLIER_REGULAR;
    }

    /**
     * Get holiday work multiplier based on holiday type.
     */
    public function getHolidayMultiplier(HolidayType $holidayType): float
    {
        return match ($holidayType) {
            HolidayType::Double => self::HOLIDAY_MULTIPLIER_DOUBLE,
            HolidayType::Regular => self::HOLIDAY_MULTIPLIER_REGULAR,
            HolidayType::SpecialNonWorking, HolidayType::SpecialWorking => self::HOLIDAY_MULTIPLIER_SPECIAL,
        };
    }

    /**
     * Calculate overtime pay.
     *
     * @param  int  $minutes  Overtime minutes
     * @param  float  $hourlyRate  Employee's hourly rate
     * @param  float  $multiplier  Overtime multiplier
     */
    public function calculateOvertimePay(int $minutes, float $hourlyRate, float $multiplier = 1.25): float
    {
        $hours = $minutes / 60;

        return round($hours * $hourlyRate * $multiplier, 2);
    }

    /**
     * Calculate night differential pay.
     *
     * @param  int  $minutes  Night differential minutes
     * @param  float  $hourlyRate  Employee's hourly rate
     */
    public function calculateNightDifferentialPay(int $minutes, float $hourlyRate): float
    {
        $hours = $minutes / 60;

        return round($hours * $hourlyRate * self::NIGHT_DIFFERENTIAL_RATE, 2);
    }

    /**
     * Calculate holiday pay.
     *
     * @param  float  $dailyRate  Employee's daily rate
     * @param  HolidayType  $holidayType  Type of holiday
     * @param  float  $daysWorked  Number of days worked on holiday
     */
    public function calculateHolidayPay(float $dailyRate, HolidayType $holidayType, float $daysWorked = 1): float
    {
        $multiplier = $this->getHolidayMultiplier($holidayType);

        return round($dailyRate * $multiplier * $daysWorked, 2);
    }

    /**
     * Calculate basic pay for period based on pay type.
     *
     * @param  EmployeeCompensation  $compensation  Employee compensation record
     * @param  float  $daysWorked  Days worked in the period
     * @param  bool  $isSecondCutoff  Whether this is the second cutoff of semi-monthly
     */
    public function calculateBasicPay(
        EmployeeCompensation $compensation,
        float $daysWorked,
        bool $isSecondCutoff = false
    ): float {
        $basicPay = (float) $compensation->basic_pay;
        $payType = $compensation->pay_type;

        return match ($payType) {
            PayType::Monthly => $basicPay,
            PayType::SemiMonthly => $basicPay,
            PayType::Weekly => round($basicPay * ($daysWorked / 5), 2),
            PayType::Daily => round($this->calculateDailyRate($basicPay, $payType) * $daysWorked, 2),
        };
    }

    /**
     * Calculate deduction for absences.
     *
     * @param  float  $dailyRate  Employee's daily rate
     * @param  float  $absentDays  Number of absent days
     */
    public function calculateAbsenceDeduction(float $dailyRate, float $absentDays): float
    {
        return round($dailyRate * $absentDays, 2);
    }

    /**
     * Calculate deduction for late/undertime.
     *
     * @param  float  $minuteRate  Employee's minute rate
     * @param  int  $lateMinutes  Total late minutes
     * @param  int  $undertimeMinutes  Total undertime minutes
     */
    public function calculateTardinessDeduction(float $minuteRate, int $lateMinutes, int $undertimeMinutes): float
    {
        return round($minuteRate * ($lateMinutes + $undertimeMinutes), 2);
    }
}
