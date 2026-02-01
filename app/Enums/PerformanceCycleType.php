<?php

namespace App\Enums;

/**
 * Types of performance cycles defining the frequency and purpose of evaluations.
 */
enum PerformanceCycleType: string
{
    case Annual = 'annual';
    case MidYear = 'mid_year';
    case Probationary = 'probationary';
    case ProjectBased = 'project_based';

    /**
     * Get a human-readable label for the cycle type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Annual => 'Annual',
            self::MidYear => 'Mid-Year',
            self::Probationary => 'Probationary',
            self::ProjectBased => 'Project-Based',
        };
    }

    /**
     * Get a description for the cycle type.
     */
    public function description(): string
    {
        return match ($this) {
            self::Annual => 'Yearly comprehensive performance review',
            self::MidYear => 'Semi-annual performance check-in',
            self::Probationary => 'Evaluation during employee probation period',
            self::ProjectBased => 'Performance review tied to specific projects',
        };
    }

    /**
     * Get the number of instances per year for this cycle type.
     */
    public function instancesPerYear(): ?int
    {
        return match ($this) {
            self::Annual => 1,
            self::MidYear => 2,
            self::Probationary => null, // Variable - depends on hire dates
            self::ProjectBased => null, // Variable - depends on projects
        };
    }

    /**
     * Check if this cycle type generates regular recurring instances.
     */
    public function isRecurring(): bool
    {
        return match ($this) {
            self::Annual, self::MidYear => true,
            self::Probationary, self::ProjectBased => false,
        };
    }

    /**
     * Get all available cycle types as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid cycle type.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}
