<?php

namespace App\Enums;

/**
 * Categories for organizing competencies within an organization.
 */
enum CompetencyCategory: string
{
    case Core = 'core';
    case Technical = 'technical';
    case Leadership = 'leadership';
    case Interpersonal = 'interpersonal';
    case Analytical = 'analytical';

    /**
     * Get a human-readable label for the category.
     */
    public function label(): string
    {
        return match ($this) {
            self::Core => 'Core',
            self::Technical => 'Technical',
            self::Leadership => 'Leadership',
            self::Interpersonal => 'Interpersonal',
            self::Analytical => 'Analytical',
        };
    }

    /**
     * Get a description for the category.
     */
    public function description(): string
    {
        return match ($this) {
            self::Core => 'Essential competencies required for all employees',
            self::Technical => 'Job-specific technical skills and knowledge',
            self::Leadership => 'Competencies for leading and managing others',
            self::Interpersonal => 'Communication and relationship-building skills',
            self::Analytical => 'Problem-solving and decision-making abilities',
        };
    }

    /**
     * Get all available categories as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid category.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}
