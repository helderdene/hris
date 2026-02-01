<?php

namespace App\Enums;

/**
 * Decision status for a leave application approval step.
 */
enum LeaveApprovalDecision: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Skipped = 'skipped';

    /**
     * Get a human-readable label for the decision.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Skipped => 'Skipped',
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
            self::Skipped => 'slate',
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
     * Check if this decision allows the workflow to continue.
     */
    public function allowsContinuation(): bool
    {
        return in_array($this, [self::Approved, self::Skipped], true);
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
