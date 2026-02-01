<?php

namespace App\Enums;

/**
 * Difficulty levels for training courses.
 */
enum CourseLevel: string
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';

    /**
     * Get a human-readable label for the level.
     */
    public function label(): string
    {
        return match ($this) {
            self::Beginner => 'Beginner',
            self::Intermediate => 'Intermediate',
            self::Advanced => 'Advanced',
        };
    }

    /**
     * Get all available level values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid level.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}
