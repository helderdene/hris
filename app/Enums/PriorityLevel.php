<?php

namespace App\Enums;

/**
 * Priority level for action center items.
 *
 * Used to categorize pending approvals and tasks by urgency.
 */
enum PriorityLevel: string
{
    case Critical = 'critical';  // Overdue items
    case High = 'high';          // Approaching deadline (24-48h)
    case Medium = 'medium';      // Standard pending items

    /**
     * Get a human-readable label for the priority level.
     */
    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Overdue',
            self::High => 'Approaching Deadline',
            self::Medium => 'Pending',
        };
    }

    /**
     * Get the badge color class for this priority level.
     */
    public function color(): string
    {
        return match ($this) {
            self::Critical => 'red',
            self::High => 'orange',
            self::Medium => 'amber',
        };
    }

    /**
     * Get the background color class for cards/alerts.
     */
    public function bgColor(): string
    {
        return match ($this) {
            self::Critical => 'bg-red-50 dark:bg-red-900/30',
            self::High => 'bg-orange-50 dark:bg-orange-900/30',
            self::Medium => 'bg-amber-50 dark:bg-amber-900/30',
        };
    }

    /**
     * Get the text color class for this priority level.
     */
    public function textColor(): string
    {
        return match ($this) {
            self::Critical => 'text-red-800 dark:text-red-200',
            self::High => 'text-orange-800 dark:text-orange-200',
            self::Medium => 'text-amber-800 dark:text-amber-200',
        };
    }

    /**
     * Get the border color class for this priority level.
     */
    public function borderColor(): string
    {
        return match ($this) {
            self::Critical => 'border-red-200 dark:border-red-800',
            self::High => 'border-orange-200 dark:border-orange-800',
            self::Medium => 'border-amber-200 dark:border-amber-800',
        };
    }

    /**
     * Get all available priority levels as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get options formatted for frontend components.
     *
     * @return array<array{value: string, label: string, color: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $priority) => [
            'value' => $priority->value,
            'label' => $priority->label(),
            'color' => $priority->color(),
        ], self::cases());
    }
}
