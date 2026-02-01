<?php

namespace App\Enums;

/**
 * Overall status of a participant's evaluation process.
 */
enum EvaluationStatus: string
{
    case NotStarted = 'not_started';
    case SelfInProgress = 'self_in_progress';
    case AwaitingReviewers = 'awaiting_reviewers';
    case Reviewing = 'reviewing';
    case Calibration = 'calibration';
    case Completed = 'completed';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::SelfInProgress => 'Self-Evaluation In Progress',
            self::AwaitingReviewers => 'Awaiting Reviewers',
            self::Reviewing => 'Under Review',
            self::Calibration => 'Calibration',
            self::Completed => 'Completed',
        };
    }

    /**
     * Get a description for the status.
     */
    public function description(): string
    {
        return match ($this) {
            self::NotStarted => 'Evaluation has not yet begun',
            self::SelfInProgress => 'Employee is completing their self-evaluation',
            self::AwaitingReviewers => 'Self-evaluation submitted, waiting for peer and manager feedback',
            self::Reviewing => 'All reviews are being collected',
            self::Calibration => 'Ready for final score calibration by HR/Manager',
            self::Completed => 'Evaluation is complete and results are available',
        };
    }

    /**
     * Get the CSS color class for UI display.
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::NotStarted => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
            self::SelfInProgress => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
            self::AwaitingReviewers => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
            self::Reviewing => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
            self::Calibration => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
            self::Completed => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        };
    }

    /**
     * Get the allowed transitions from this status.
     *
     * @return array<EvaluationStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::NotStarted => [self::SelfInProgress],
            self::SelfInProgress => [self::AwaitingReviewers],
            self::AwaitingReviewers => [self::Reviewing],
            self::Reviewing => [self::Calibration],
            self::Calibration => [self::Completed],
            self::Completed => [], // Terminal state
        };
    }

    /**
     * Check if transition to another status is allowed.
     */
    public function canTransitionTo(EvaluationStatus $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    /**
     * Check if the employee can still modify their self-evaluation.
     */
    public function canEditSelfEvaluation(): bool
    {
        return match ($this) {
            self::NotStarted, self::SelfInProgress => true,
            default => false,
        };
    }

    /**
     * Check if results are visible to the employee.
     */
    public function areResultsVisible(): bool
    {
        return $this === self::Completed;
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
