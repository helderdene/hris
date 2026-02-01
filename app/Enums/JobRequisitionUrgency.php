<?php

namespace App\Enums;

/**
 * Urgency level for a job requisition.
 */
enum JobRequisitionUrgency: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    /**
     * Get a human-readable label for the urgency.
     */
    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Normal => 'Normal',
            self::High => 'High',
            self::Urgent => 'Urgent',
        };
    }

    /**
     * Get the badge color class for this urgency.
     */
    public function color(): string
    {
        return match ($this) {
            self::Low => 'slate',
            self::Normal => 'blue',
            self::High => 'amber',
            self::Urgent => 'red',
        };
    }

    /**
     * Get all available urgency levels as an array of values.
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
        return array_map(fn (self $urgency) => [
            'value' => $urgency->value,
            'label' => $urgency->label(),
            'color' => $urgency->color(),
        ], self::cases());
    }
}
