<?php

namespace App\Enums;

/**
 * Status states for KPI assignments.
 *
 * Tracks the lifecycle of a KPI assignment from initial assignment
 * through active tracking to completion.
 */
enum KpiAssignmentStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
        };
    }

    /**
     * Get a description for the status.
     */
    public function description(): string
    {
        return match ($this) {
            self::Pending => 'KPI has been assigned but tracking has not started',
            self::InProgress => 'KPI is being actively tracked with progress updates',
            self::Completed => 'KPI tracking is complete and finalized',
        };
    }

    /**
     * Get the CSS color class for status badges.
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
            self::InProgress => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
            self::Completed => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        };
    }

    /**
     * Get all available statuses as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid status.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}
