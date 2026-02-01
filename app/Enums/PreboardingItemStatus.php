<?php

namespace App\Enums;

/**
 * Status of an individual preboarding checklist item.
 */
enum PreboardingItemStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Submitted => 'Submitted',
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
            self::Submitted => 'amber',
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
            self::Pending => [self::Submitted],
            self::Submitted => [self::Approved, self::Rejected],
            self::Approved => [],
            self::Rejected => [self::Submitted],
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
