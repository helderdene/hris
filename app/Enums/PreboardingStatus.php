<?php

namespace App\Enums;

/**
 * Status of a preboarding checklist through its lifecycle.
 */
enum PreboardingStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Overdue = 'overdue';

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
        };
    }

    /**
     * Get the badge color class for this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'slate',
            self::InProgress => 'blue',
            self::Completed => 'green',
            self::Overdue => 'red',
        };
    }

    /**
     * Check if this is a terminal (final) status.
     */
    public function isTerminal(): bool
    {
        return $this === self::Completed;
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
