<?php

namespace App\Enums;

/**
 * Status states for payroll entries with state machine transition rules.
 *
 * State flow: draft -> computed -> reviewed -> approved -> paid
 */
enum PayrollEntryStatus: string
{
    case Draft = 'draft';
    case Computed = 'computed';
    case Reviewed = 'reviewed';
    case Approved = 'approved';
    case Paid = 'paid';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Computed => 'Computed',
            self::Reviewed => 'Reviewed',
            self::Approved => 'Approved',
            self::Paid => 'Paid',
        };
    }

    /**
     * Get a description for the status.
     */
    public function description(): string
    {
        return match ($this) {
            self::Draft => 'Entry created but not yet computed',
            self::Computed => 'Payroll has been calculated and ready for review',
            self::Reviewed => 'Payroll has been reviewed and verified',
            self::Approved => 'Payroll approved for payment',
            self::Paid => 'Payment has been processed',
        };
    }

    /**
     * Get the CSS color class for status badges.
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
            self::Computed => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
            self::Reviewed => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
            self::Approved => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
            self::Paid => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
        };
    }

    /**
     * Get the valid statuses this status can transition to.
     *
     * @return array<PayrollEntryStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Computed],
            self::Computed => [self::Reviewed, self::Draft],
            self::Reviewed => [self::Approved, self::Computed],
            self::Approved => [self::Paid, self::Reviewed],
            self::Paid => [],
        };
    }

    /**
     * Check if this status can transition to the given status.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Check if the entry can be edited in this status.
     */
    public function isEditable(): bool
    {
        return match ($this) {
            self::Draft, self::Computed => true,
            self::Reviewed, self::Approved, self::Paid => false,
        };
    }

    /**
     * Check if the entry can be recomputed in this status.
     */
    public function canRecompute(): bool
    {
        return match ($this) {
            self::Draft, self::Computed, self::Reviewed => true,
            self::Approved, self::Paid => false,
        };
    }

    /**
     * Check if the entry can be deleted in this status.
     */
    public function isDeletable(): bool
    {
        return $this === self::Draft;
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
