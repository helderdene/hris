<?php

namespace App\Enums;

/**
 * Status states for development plans.
 *
 * Tracks the lifecycle of a development plan from draft through completion or cancellation.
 */
enum DevelopmentPlanStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending Approval',
            self::Approved => 'Approved',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Get a description for the status.
     */
    public function description(): string
    {
        return match ($this) {
            self::Draft => 'Plan is being drafted and not yet submitted',
            self::PendingApproval => 'Plan is awaiting manager approval',
            self::Approved => 'Plan has been approved by manager',
            self::InProgress => 'Development activities are underway',
            self::Completed => 'All development activities have been completed',
            self::Cancelled => 'Plan has been cancelled',
        };
    }

    /**
     * Get the CSS color class for status badges.
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
            self::PendingApproval => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
            self::Approved => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
            self::InProgress => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300',
            self::Completed => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
            self::Cancelled => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
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

    /**
     * Check if the status allows editing.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Approved, self::InProgress], true);
    }

    /**
     * Check if the status is a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * Check if the status allows adding activities.
     */
    public function canAddActivities(): bool
    {
        return in_array($this, [self::Approved, self::InProgress], true);
    }
}
