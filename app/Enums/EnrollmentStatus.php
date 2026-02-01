<?php

namespace App\Enums;

/**
 * Status values for training enrollments.
 */
enum EnrollmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Attended = 'attended';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Approval',
            self::Confirmed => 'Confirmed',
            self::Attended => 'Attended',
            self::NoShow => 'No Show',
            self::Cancelled => 'Cancelled',
            self::Rejected => 'Rejected',
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
     * Check if the enrollment is pending approval.
     */
    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Check if the enrollment is active (counts towards capacity).
     */
    public function isActive(): bool
    {
        return $this === self::Confirmed;
    }

    /**
     * Check if the enrollment is in a final state (cannot be changed).
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Attended, self::NoShow, self::Cancelled, self::Rejected], true);
    }

    /**
     * Check if the enrollment can be approved.
     */
    public function canBeApproved(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Check if the enrollment can be rejected.
     */
    public function canBeRejected(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Check if the enrollment can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed], true);
    }

    /**
     * Check if attendance can be marked.
     */
    public function canMarkAttendance(): bool
    {
        return $this === self::Confirmed;
    }

    /**
     * Get the badge color class for this status.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Confirmed => 'blue',
            self::Attended => 'green',
            self::NoShow => 'red',
            self::Cancelled => 'gray',
            self::Rejected => 'red',
        };
    }
}
