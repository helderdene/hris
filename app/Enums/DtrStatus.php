<?php

namespace App\Enums;

/**
 * Status for Daily Time Record entries.
 */
enum DtrStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Holiday = 'holiday';
    case RestDay = 'rest_day';
    case NoSchedule = 'no_schedule';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Absent => 'Absent',
            self::Holiday => 'Holiday',
            self::RestDay => 'Rest Day',
            self::NoSchedule => 'No Schedule',
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
     * Check if a given value is a valid status.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Determine if this status represents the employee being at work.
     */
    public function isWorking(): bool
    {
        return $this === self::Present || $this === self::Holiday;
    }
}
