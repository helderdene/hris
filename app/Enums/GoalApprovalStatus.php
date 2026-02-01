<?php

namespace App\Enums;

/**
 * Approval status states for goals.
 *
 * Tracks whether a goal requires approval and its current approval state.
 */
enum GoalApprovalStatus: string
{
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * Get a human-readable label for the approval status.
     */
    public function label(): string
    {
        return match ($this) {
            self::NotRequired => 'Not Required',
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * Get a description for the approval status.
     */
    public function description(): string
    {
        return match ($this) {
            self::NotRequired => 'This goal does not require manager approval',
            self::Pending => 'Awaiting manager approval',
            self::Approved => 'Goal has been approved by manager',
            self::Rejected => 'Goal has been rejected by manager',
        };
    }

    /**
     * Get the CSS color class for approval status badges.
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::NotRequired => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
            self::Pending => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
            self::Approved => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
            self::Rejected => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        };
    }

    /**
     * Get all available approval statuses as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid approval status.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}
