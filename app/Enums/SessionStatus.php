<?php

namespace App\Enums;

/**
 * Status values for training sessions.
 */
enum SessionStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
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
     * Check if the session is visible to employees for enrollment.
     */
    public function isEnrollable(): bool
    {
        return $this === self::Scheduled;
    }

    /**
     * Check if the session is visible to employees (browsable).
     */
    public function isVisibleToEmployees(): bool
    {
        return in_array($this, [self::Scheduled, self::InProgress, self::Completed], true);
    }

    /**
     * Check if the session can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this, [self::Draft, self::Scheduled], true);
    }

    /**
     * Check if the session can be edited.
     */
    public function canBeEdited(): bool
    {
        return in_array($this, [self::Draft, self::Scheduled], true);
    }

    /**
     * Get the badge color class for this status.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Scheduled => 'blue',
            self::InProgress => 'yellow',
            self::Completed => 'green',
            self::Cancelled => 'red',
        };
    }
}
