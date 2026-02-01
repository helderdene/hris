<?php

namespace App\Enums;

/**
 * Types of earnings in payroll computation.
 *
 * Each earning type has specific tax treatment and computation rules.
 */
enum EarningType: string
{
    case BasicPay = 'basic_pay';
    case Overtime = 'overtime';
    case NightDifferential = 'night_differential';
    case HolidayPay = 'holiday_pay';
    case Allowance = 'allowance';
    case Bonus = 'bonus';
    case Adjustment = 'adjustment';

    /**
     * Get a human-readable label for the earning type.
     */
    public function label(): string
    {
        return match ($this) {
            self::BasicPay => 'Basic Pay',
            self::Overtime => 'Overtime',
            self::NightDifferential => 'Night Differential',
            self::HolidayPay => 'Holiday Pay',
            self::Allowance => 'Allowance',
            self::Bonus => 'Bonus',
            self::Adjustment => 'Adjustment',
        };
    }

    /**
     * Check if this earning type is taxable by default.
     *
     * Note: Actual taxability may depend on specific regulations
     * and threshold amounts (e.g., de minimis benefits).
     */
    public function isTaxableByDefault(): bool
    {
        return match ($this) {
            self::BasicPay, self::Overtime, self::NightDifferential, self::HolidayPay, self::Bonus, self::Adjustment => true,
            self::Allowance => false,
        };
    }

    /**
     * Get the sort order for display purposes.
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::BasicPay => 1,
            self::Overtime => 2,
            self::NightDifferential => 3,
            self::HolidayPay => 4,
            self::Allowance => 5,
            self::Bonus => 6,
            self::Adjustment => 7,
        };
    }

    /**
     * Get all available earning types as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid earning type.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}
