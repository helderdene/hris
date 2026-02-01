<?php

namespace App\Enums;

/**
 * Employment status for tracking employee lifecycle.
 */
enum EmploymentStatus: string
{
    case Active = 'active';
    case Resigned = 'resigned';
    case Terminated = 'terminated';
    case Retired = 'retired';
    case EndOfContract = 'end_of_contract';
    case Deceased = 'deceased';

    /**
     * Get a human-readable label for the employment status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Resigned => 'Resigned',
            self::Terminated => 'Terminated',
            self::Retired => 'Retired',
            self::EndOfContract => 'End of Contract',
            self::Deceased => 'Deceased',
        };
    }

    /**
     * Get all available employment status values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid employment status.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Try to create an employment status from a string value.
     */
    public static function tryFromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
