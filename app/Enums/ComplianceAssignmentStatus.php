<?php

namespace App\Enums;

/**
 * Status values for compliance training assignments.
 */
enum ComplianceAssignmentStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Overdue = 'overdue';
    case Expired = 'expired';
    case Exempted = 'exempted';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Overdue => 'Overdue',
            self::Expired => 'Expired',
            self::Exempted => 'Exempted',
        };
    }

    /**
     * Get the color for status display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::InProgress => 'blue',
            self::Completed => 'green',
            self::Overdue => 'red',
            self::Expired => 'orange',
            self::Exempted => 'purple',
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
     * Check if the assignment is active (can be worked on).
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::InProgress, self::Overdue], true);
    }

    /**
     * Check if the assignment is terminal (no further action needed).
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Expired, self::Exempted], true);
    }

    /**
     * Check if the status requires attention.
     */
    public function requiresAttention(): bool
    {
        return in_array($this, [self::Overdue, self::Pending], true);
    }
}
