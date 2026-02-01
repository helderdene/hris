<?php

namespace App\Enums;

/**
 * Employment types that a position or employee can have.
 */
enum EmploymentType: string
{
    case Regular = 'regular';
    case Probationary = 'probationary';
    case Contractual = 'contractual';
    case Consultant = 'consultant';
    case Intern = 'intern';
    case ProjectBased = 'project_based';

    /**
     * Get a human-readable label for the employment type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Regular',
            self::Probationary => 'Probationary',
            self::Contractual => 'Contractual',
            self::Consultant => 'Consultant',
            self::Intern => 'Intern',
            self::ProjectBased => 'Project-based',
        };
    }

    /**
     * Get all available employment types as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid employment type.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Try to create an employment type from a string value.
     */
    public static function tryFromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
