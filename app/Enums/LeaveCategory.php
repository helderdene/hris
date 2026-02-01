<?php

namespace App\Enums;

/**
 * Categories of leave types.
 *
 * Statutory leaves are mandated by Philippine law.
 * Company leaves are custom leaves provided by the employer.
 * Special leaves are for specific circumstances.
 */
enum LeaveCategory: string
{
    case Statutory = 'statutory';
    case Company = 'company';
    case Special = 'special';

    /**
     * Get a human-readable label for the leave category.
     */
    public function label(): string
    {
        return match ($this) {
            self::Statutory => 'Statutory Leave',
            self::Company => 'Company Leave',
            self::Special => 'Special Leave',
        };
    }

    /**
     * Get all available leave categories as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid leave category.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Try to create a leave category from a string value.
     */
    public static function tryFromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
