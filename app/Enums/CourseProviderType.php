<?php

namespace App\Enums;

/**
 * Provider types for training courses.
 */
enum CourseProviderType: string
{
    case Internal = 'internal';
    case External = 'external';

    /**
     * Get a human-readable label for the provider type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Internal',
            self::External => 'External',
        };
    }

    /**
     * Get all available provider type values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid provider type.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}
