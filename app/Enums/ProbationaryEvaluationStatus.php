<?php

namespace App\Enums;

/**
 * Status of a probationary evaluation request.
 *
 * Represents the workflow states from pending through final decision.
 */
enum ProbationaryEvaluationStatus: string
{
    case Pending = 'pending';
    case Draft = 'draft';
    case Submitted = 'submitted';
    case HrReview = 'hr_review';
    case RevisionRequested = 'revision_requested';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::HrReview => 'Under HR Review',
            self::RevisionRequested => 'Revision Requested',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * Get the badge color class for this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'slate',
            self::Draft => 'blue',
            self::Submitted => 'amber',
            self::HrReview => 'purple',
            self::RevisionRequested => 'orange',
            self::Approved => 'green',
            self::Rejected => 'red',
        };
    }

    /**
     * Get the allowed status transitions from this status.
     *
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Draft],
            self::Draft => [self::Submitted],
            self::Submitted => [self::HrReview],
            self::HrReview => [self::Approved, self::Rejected, self::RevisionRequested],
            self::RevisionRequested => [self::Submitted],
            self::Approved => [],
            self::Rejected => [],
        };
    }

    /**
     * Check if this status can transition to the given status.
     */
    public function canTransitionTo(self $newStatus): bool
    {
        return in_array($newStatus, $this->allowedTransitions(), true);
    }

    /**
     * Check if this is a final (terminal) status.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected], true);
    }

    /**
     * Check if evaluation can be edited in this status.
     */
    public function canBeEdited(): bool
    {
        return in_array($this, [self::Pending, self::Draft, self::RevisionRequested], true);
    }

    /**
     * Check if evaluation is awaiting manager action.
     */
    public function isAwaitingManager(): bool
    {
        return in_array($this, [self::Pending, self::Draft, self::RevisionRequested], true);
    }

    /**
     * Check if evaluation is awaiting HR action.
     */
    public function isAwaitingHr(): bool
    {
        return in_array($this, [self::Submitted, self::HrReview], true);
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
     * Get options formatted for frontend select components.
     *
     * @return array<array{value: string, label: string, color: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $status) => [
            'value' => $status->value,
            'label' => $status->label(),
            'color' => $status->color(),
        ], self::cases());
    }
}
