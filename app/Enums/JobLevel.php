<?php

namespace App\Enums;

/**
 * Job levels that a position can have within an organization.
 */
enum JobLevel: string
{
    case Junior = 'junior';
    case Mid = 'mid';
    case Senior = 'senior';
    case Lead = 'lead';
    case Manager = 'manager';
    case Director = 'director';
    case Executive = 'executive';

    /**
     * Get a human-readable label for the job level.
     */
    public function label(): string
    {
        return match ($this) {
            self::Junior => 'Junior',
            self::Mid => 'Mid',
            self::Senior => 'Senior',
            self::Lead => 'Lead',
            self::Manager => 'Manager',
            self::Director => 'Director',
            self::Executive => 'Executive',
        };
    }

    /**
     * Get all available job levels as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid job level.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Try to create a job level from a string value.
     */
    public static function tryFromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
