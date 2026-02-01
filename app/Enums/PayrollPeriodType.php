<?php

namespace App\Enums;

/**
 * Types of payroll periods defining the nature of the payroll run.
 */
enum PayrollPeriodType: string
{
    case Regular = 'regular';
    case Supplemental = 'supplemental';
    case ThirteenthMonth = 'thirteenth_month';
    case FinalPay = 'final_pay';

    /**
     * Get a human-readable label for the period type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Regular',
            self::Supplemental => 'Supplemental',
            self::ThirteenthMonth => '13th Month Pay',
            self::FinalPay => 'Final Pay',
        };
    }

    /**
     * Get a description for the period type.
     */
    public function description(): string
    {
        return match ($this) {
            self::Regular => 'Standard payroll period with regular earnings and deductions',
            self::Supplemental => 'Additional payroll for bonuses, adjustments, or corrections',
            self::ThirteenthMonth => 'Mandatory 13th month pay computation',
            self::FinalPay => 'Settlement payroll for separated employees',
        };
    }

    /**
     * Get the default period type from a cycle type.
     */
    public static function fromCycleType(PayrollCycleType $cycleType): self
    {
        return match ($cycleType) {
            PayrollCycleType::SemiMonthly, PayrollCycleType::Monthly => self::Regular,
            PayrollCycleType::Supplemental => self::Supplemental,
            PayrollCycleType::ThirteenthMonth => self::ThirteenthMonth,
            PayrollCycleType::FinalPay => self::FinalPay,
        };
    }

    /**
     * Get all available period types as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid period type.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}
