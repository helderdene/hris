<?php

namespace App\Enums;

/**
 * Status states for performance cycle instances with state machine transition rules.
 *
 * State flow: draft -> active -> in_evaluation -> closed
 */
enum PerformanceCycleInstanceStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case InEvaluation = 'in_evaluation';
    case Closed = 'closed';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::InEvaluation => 'In Evaluation',
            self::Closed => 'Closed',
        };
    }

    /**
     * Get a description for the status.
     */
    public function description(): string
    {
        return match ($this) {
            self::Draft => 'Instance is configured but not yet active',
            self::Active => 'Instance is active, participants can be assigned',
            self::InEvaluation => 'Evaluation period has started, reviews can be submitted',
            self::Closed => 'Evaluation is finalized and locked for changes',
        };
    }

    /**
     * Get the CSS color class for status badges.
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
            self::Active => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
            self::InEvaluation => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
            self::Closed => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        };
    }

    /**
     * Get the valid statuses this status can transition to.
     *
     * @return array<PerformanceCycleInstanceStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Active],
            self::Active => [self::InEvaluation, self::Draft],
            self::InEvaluation => [self::Closed, self::Active],
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
     * Check if the instance can be edited in this status.
     */
    public function isEditable(): bool
    {
        return match ($this) {
            self::Draft, self::Active => true,
            self::InEvaluation, self::Closed => false,
        };
    }

    /**
     * Check if the instance can be deleted in this status.
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
