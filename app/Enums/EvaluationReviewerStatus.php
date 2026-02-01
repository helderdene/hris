<?php

namespace App\Enums;

/**
 * Status of an individual reviewer's evaluation progress.
 */
enum EvaluationReviewerStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Declined = 'declined';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Submitted => 'Submitted',
            self::Declined => 'Declined',
        };
    }

    /**
     * Get a description for the status.
     */
    public function description(): string
    {
        return match ($this) {
            self::Pending => 'Reviewer has been invited but has not started',
            self::InProgress => 'Reviewer has started but not yet submitted',
            self::Submitted => 'Reviewer has completed and submitted their evaluation',
            self::Declined => 'Reviewer has declined to provide feedback',
        };
    }

    /**
     * Get the CSS color class for UI display.
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
            self::InProgress => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
            self::Submitted => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
            self::Declined => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        };
    }

    /**
     * Check if the reviewer can still modify their response.
     */
    public function isEditable(): bool
    {
        return match ($this) {
            self::Pending, self::InProgress => true,
            self::Submitted, self::Declined => false,
        };
    }

    /**
     * Get the allowed transitions from this status.
     *
     * @return array<EvaluationReviewerStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::InProgress, self::Declined],
            self::InProgress => [self::Submitted, self::Declined],
            self::Submitted => [], // Terminal state
            self::Declined => [], // Terminal state
        };
    }

    /**
     * Check if transition to another status is allowed.
     */
    public function canTransitionTo(EvaluationReviewerStatus $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
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
