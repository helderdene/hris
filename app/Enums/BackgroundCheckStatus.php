<?php

namespace App\Enums;

/**
 * Status of a background check through its lifecycle.
 */
enum BackgroundCheckStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Passed = 'passed';
    case Failed = 'failed';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Passed => 'Passed',
            self::Failed => 'Failed',
        };
    }

    /**
     * Get the badge color class for this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'slate',
            self::InProgress => 'amber',
            self::Passed => 'green',
            self::Failed => 'red',
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
