<?php

namespace App\Enums;

/**
 * Gender restrictions for leave types.
 *
 * Some statutory leaves are gender-specific (e.g., Maternity for female,
 * Paternity for male employees).
 */
enum GenderRestriction: string
{
    case Male = 'male';
    case Female = 'female';

    /**
     * Get a human-readable label for the gender restriction.
     */
    public function label(): string
    {
        return match ($this) {
            self::Male => 'Male Only',
            self::Female => 'Female Only',
        };
    }

    /**
     * Get all available gender restrictions as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid gender restriction.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Try to create a gender restriction from a string value.
     */
    public static function tryFromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
