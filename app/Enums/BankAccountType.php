<?php

namespace App\Enums;

/**
 * Bank account type for employee compensation bank details.
 */
enum BankAccountType: string
{
    case Savings = 'savings';
    case Checking = 'checking';

    /**
     * Get a human-readable label for the bank account type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Savings => 'Savings',
            self::Checking => 'Checking',
        };
    }

    /**
     * Get all available bank account type values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid bank account type.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Try to create a bank account type from a string value.
     */
    public static function tryFromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
