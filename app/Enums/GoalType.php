<?php

namespace App\Enums;

/**
 * Types of goals supported in the system.
 *
 * Distinguishes between OKR (Objectives and Key Results) and SMART goal methodologies.
 */
enum GoalType: string
{
    case OkrObjective = 'okr_objective';
    case SmartGoal = 'smart_goal';

    /**
     * Get a human-readable label for the goal type.
     */
    public function label(): string
    {
        return match ($this) {
            self::OkrObjective => 'OKR Objective',
            self::SmartGoal => 'SMART Goal',
        };
    }

    /**
     * Get a description for the goal type.
     */
    public function description(): string
    {
        return match ($this) {
            self::OkrObjective => 'An objective with measurable key results',
            self::SmartGoal => 'A Specific, Measurable, Achievable, Relevant, Time-bound goal',
        };
    }

    /**
     * Get the CSS color class for type badges.
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::OkrObjective => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
            self::SmartGoal => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300',
        };
    }

    /**
     * Get all available types as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid type.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}
