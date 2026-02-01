<?php

namespace App\Enums;

/**
 * Frequency of a payroll adjustment.
 *
 * Determines whether an adjustment is applied once
 * or repeats across multiple payroll periods.
 */
enum AdjustmentFrequency: string
{
    case OneTime = 'one_time';
    case Recurring = 'recurring';

    /**
     * Get a human-readable label for the frequency.
     */
    public function label(): string
    {
        return match ($this) {
            self::OneTime => 'One-Time',
            self::Recurring => 'Recurring',
        };
    }

    /**
     * Get the badge color class for this frequency.
     */
    public function color(): string
    {
        return match ($this) {
            self::OneTime => 'blue',
            self::Recurring => 'purple',
        };
    }

    /**
     * Get all available frequencies as an array of values.
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
        return array_map(fn (self $frequency) => [
            'value' => $frequency->value,
            'label' => $frequency->label(),
            'color' => $frequency->color(),
        ], self::cases());
    }
}
