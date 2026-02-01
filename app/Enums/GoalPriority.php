<?php

namespace App\Enums;

/**
 * Priority levels for goals.
 *
 * Indicates the importance and urgency of a goal.
 */
enum GoalPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    /**
     * Get a human-readable label for the priority level.
     */
    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::Critical => 'Critical',
        };
    }

    /**
     * Get a description for the priority level.
     */
    public function description(): string
    {
        return match ($this) {
            self::Low => 'Nice to have, lower urgency',
            self::Medium => 'Standard priority goal',
            self::High => 'Important goal requiring focused attention',
            self::Critical => 'Highest priority, requires immediate attention',
        };
    }

    /**
     * Get the CSS color class for priority badges.
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::Low => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
            self::Medium => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
            self::High => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
            self::Critical => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        };
    }

    /**
     * Get all available priority levels as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid priority level.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Get numeric weight for sorting.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Critical => 4,
        };
    }
}
