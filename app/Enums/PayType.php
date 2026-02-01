<?php

namespace App\Enums;

/**
 * Pay type for tracking employee compensation frequency.
 */
enum PayType: string
{
    case Monthly = 'monthly';
    case SemiMonthly = 'semi_monthly';
    case Weekly = 'weekly';
    case Daily = 'daily';

    /**
     * Get a human-readable label for the pay type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::SemiMonthly => 'Semi-Monthly',
            self::Weekly => 'Weekly',
            self::Daily => 'Daily',
        };
    }

    /**
     * Get all available pay type values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid pay type.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Try to create a pay type from a string value.
     */
    public static function tryFromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
