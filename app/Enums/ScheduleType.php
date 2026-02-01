<?php

namespace App\Enums;

/**
 * Schedule types that define how work hours are organized.
 */
enum ScheduleType: string
{
    case Fixed = 'fixed';
    case Flexible = 'flexible';
    case Shifting = 'shifting';
    case Compressed = 'compressed';

    /**
     * Get a human-readable label for the schedule type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed',
            self::Flexible => 'Flexible',
            self::Shifting => 'Shifting',
            self::Compressed => 'Compressed',
        };
    }

    /**
     * Get all available schedule types as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid schedule type.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Try to create a schedule type from a string value.
     */
    public static function tryFromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
