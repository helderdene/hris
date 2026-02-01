<?php

namespace App\Enums;

/**
 * Status states for payroll periods with state machine transition rules.
 *
 * State flow: draft -> open -> processing -> closed
 */
enum PayrollPeriodStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Processing = 'processing';
    case Closed = 'closed';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Open => 'Open',
            self::Processing => 'Processing',
            self::Closed => 'Closed',
        };
    }

    /**
     * Get a description for the status.
     */
    public function description(): string
    {
        return match ($this) {
            self::Draft => 'Period is configured but not yet open for payroll processing',
            self::Open => 'Period is open for time entries and payroll data collection',
            self::Processing => 'Payroll computation is in progress',
            self::Closed => 'Payroll is finalized and locked for changes',
        };
    }

    /**
     * Get the CSS color class for status badges.
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
            self::Open => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
            self::Processing => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
            self::Closed => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        };
    }

    /**
     * Get the valid statuses this status can transition to.
     *
     * @return array<PayrollPeriodStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Open],
            self::Open => [self::Processing, self::Draft],
            self::Processing => [self::Closed, self::Open],
            self::Closed => [], // Terminal state - no transitions allowed
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
     * Check if the period can be edited in this status.
     */
    public function isEditable(): bool
    {
        return match ($this) {
            self::Draft, self::Open => true,
            self::Processing, self::Closed => false,
        };
    }

    /**
     * Check if the period can be deleted in this status.
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
