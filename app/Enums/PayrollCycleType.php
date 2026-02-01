<?php

namespace App\Enums;

/**
 * Types of payroll cycles defining the frequency and purpose of payroll runs.
 */
enum PayrollCycleType: string
{
    case SemiMonthly = 'semi_monthly';
    case Monthly = 'monthly';
    case Supplemental = 'supplemental';
    case ThirteenthMonth = 'thirteenth_month';
    case FinalPay = 'final_pay';

    /**
     * Get a human-readable label for the cycle type.
     */
    public function label(): string
    {
        return match ($this) {
            self::SemiMonthly => 'Semi-Monthly',
            self::Monthly => 'Monthly',
            self::Supplemental => 'Supplemental',
            self::ThirteenthMonth => '13th Month Pay',
            self::FinalPay => 'Final Pay',
        };
    }

    /**
     * Get a description for the cycle type.
     */
    public function description(): string
    {
        return match ($this) {
            self::SemiMonthly => 'Payroll processed twice a month (e.g., 1st-15th and 16th-end)',
            self::Monthly => 'Payroll processed once a month',
            self::Supplemental => 'Additional payroll run for bonuses, adjustments, or corrections',
            self::ThirteenthMonth => 'Mandatory 13th month pay for Philippine employees',
            self::FinalPay => 'Settlement payroll for separated employees',
        };
    }

    /**
     * Get the number of periods per year for this cycle type.
     */
    public function periodsPerYear(): ?int
    {
        return match ($this) {
            self::SemiMonthly => 24,
            self::Monthly => 12,
            self::Supplemental => null, // Variable
            self::ThirteenthMonth => 1,
            self::FinalPay => null, // Variable
        };
    }

    /**
     * Check if this cycle type generates regular recurring periods.
     */
    public function isRecurring(): bool
    {
        return match ($this) {
            self::SemiMonthly, self::Monthly => true,
            self::Supplemental, self::ThirteenthMonth, self::FinalPay => false,
        };
    }

    /**
     * Get all available cycle types as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid cycle type.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}
