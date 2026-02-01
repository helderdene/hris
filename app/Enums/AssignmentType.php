<?php

namespace App\Enums;

/**
 * Assignment type for tracking employee assignment changes.
 */
enum AssignmentType: string
{
    case Position = 'position';
    case Department = 'department';
    case Location = 'location';
    case Supervisor = 'supervisor';

    /**
     * Get a human-readable label for the assignment type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Position => 'Position',
            self::Department => 'Department',
            self::Location => 'Work Location',
            self::Supervisor => 'Supervisor',
        };
    }

    /**
     * Get all available assignment type values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid assignment type.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Try to create an assignment type from a string value.
     */
    public static function tryFromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
