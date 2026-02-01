<?php

namespace App\Enums;

/**
 * Methods for accruing leave balances.
 *
 * Different leave types may accrue differently based on company policy
 * or statutory requirements.
 */
enum AccrualMethod: string
{
    case Annual = 'annual';
    case Monthly = 'monthly';
    case TenureBased = 'tenure_based';
    case None = 'none';

    /**
     * Get a human-readable label for the accrual method.
     */
    public function label(): string
    {
        return match ($this) {
            self::Annual => 'Annual (credited at start of year)',
            self::Monthly => 'Monthly (accrues each month)',
            self::TenureBased => 'Tenure-Based (increases with service)',
            self::None => 'None (entitlement per occurrence)',
        };
    }

    /**
     * Get a short label for the accrual method.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Annual => 'Annual',
            self::Monthly => 'Monthly',
            self::TenureBased => 'Tenure-Based',
            self::None => 'None',
        };
    }

    /**
     * Get all available accrual methods as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid accrual method.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Try to create an accrual method from a string value.
     */
    public static function tryFromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
