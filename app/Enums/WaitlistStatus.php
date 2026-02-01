<?php

namespace App\Enums;

/**
 * Status values for training session waitlist entries.
 */
enum WaitlistStatus: string
{
    case Waiting = 'waiting';
    case Promoted = 'promoted';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Waiting',
            self::Promoted => 'Promoted',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Get all available status values as an array.
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

    /**
     * Check if the waitlist entry is still active (waiting for promotion).
     */
    public function isActive(): bool
    {
        return $this === self::Waiting;
    }

    /**
     * Check if the waitlist entry can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return $this === self::Waiting;
    }

    /**
     * Get the badge color class for this status.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Waiting => 'yellow',
            self::Promoted => 'green',
            self::Expired => 'gray',
            self::Cancelled => 'red',
        };
    }
}
