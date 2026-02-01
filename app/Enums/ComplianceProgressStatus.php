<?php

namespace App\Enums;

/**
 * Status values for compliance module progress tracking.
 */
enum ComplianceProgressStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    /**
     * Get the color for status display.
     */
    public function color(): string
    {
        return match ($this) {
            self::NotStarted => 'gray',
            self::InProgress => 'blue',
            self::Completed => 'green',
            self::Failed => 'red',
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
     * Check if the module can be worked on.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::NotStarted, self::InProgress, self::Failed], true);
    }

    /**
     * Check if the module is finished (successfully or not).
     */
    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
    }
}
