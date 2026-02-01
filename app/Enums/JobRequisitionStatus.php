<?php

namespace App\Enums;

/**
 * Status of a job requisition request.
 *
 * Represents the workflow states from draft through final decision.
 */
enum JobRequisitionStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending Approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Get the badge color class for this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::Pending => 'amber',
            self::Approved => 'green',
            self::Rejected => 'red',
            self::Cancelled => 'slate',
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
            self::Draft => [self::Pending, self::Cancelled],
            self::Pending => [self::Approved, self::Rejected, self::Cancelled],
            self::Approved => [],
            self::Rejected => [],
            self::Cancelled => [],
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
        return in_array($this, [self::Approved, self::Rejected, self::Cancelled], true);
    }

    /**
     * Check if the requisition can be edited in this status.
     */
    public function canBeEdited(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Check if the requisition can be cancelled in this status.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this, [self::Draft, self::Pending], true);
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
