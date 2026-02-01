<?php

namespace App\Enums;

/**
 * Status of an employee adjustment.
 */
enum AdjustmentStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case OnHold = 'on_hold';
    case Cancelled = 'cancelled';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Completed => 'Completed',
            self::OnHold => 'On Hold',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Check if adjustments with this status should be applied to payroll.
     */
    public function isApplicable(): bool
    {
        return $this === self::Active;
    }

    /**
     * Get the allowed status transitions from this status.
     *
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Active => [self::OnHold, self::Completed, self::Cancelled],
            self::OnHold => [self::Active, self::Cancelled],
            self::Completed => [],
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
     * Get the badge color class for this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Completed => 'blue',
            self::OnHold => 'amber',
            self::Cancelled => 'slate',
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
