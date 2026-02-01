<?php

namespace App\Enums;

/**
 * Decision status for a probationary evaluation approval step.
 */
enum ProbationaryApprovalDecision: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case RevisionRequested = 'revision_requested';

    /**
     * Get a human-readable label for the decision.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::RevisionRequested => 'Revision Requested',
        };
    }

    /**
     * Get the badge color class for this decision.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'green',
            self::Rejected => 'red',
            self::RevisionRequested => 'orange',
        };
    }

    /**
     * Check if the decision is final (not pending).
     */
    public function isDecided(): bool
    {
        return $this !== self::Pending;
    }

    /**
     * Check if the decision is pending.
     */
    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Check if this is a final decision (approved or rejected).
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected]);
    }

    /**
     * Check if this decision allows the workflow to continue.
     */
    public function allowsContinuation(): bool
    {
        return $this === self::Approved;
    }

    /**
     * Get all available decisions as an array of values.
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
        return array_map(fn (self $decision) => [
            'value' => $decision->value,
            'label' => $decision->label(),
            'color' => $decision->color(),
        ], self::cases());
    }
}
