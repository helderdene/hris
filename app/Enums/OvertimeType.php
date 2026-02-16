<?php

namespace App\Enums;

/**
 * Type of overtime work performed.
 */
enum OvertimeType: string
{
    case Regular = 'regular';
    case RestDay = 'rest_day';
    case Holiday = 'holiday';

    /**
     * Get a human-readable label for the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Regular Overtime',
            self::RestDay => 'Rest Day Overtime',
            self::Holiday => 'Holiday Overtime',
        };
    }

    /**
     * Get the badge color class for this type.
     */
    public function color(): string
    {
        return match ($this) {
            self::Regular => 'blue',
            self::RestDay => 'purple',
            self::Holiday => 'orange',
        };
    }

    /**
     * Get the pay multiplier for this overtime type.
     */
    public function multiplier(): float
    {
        return match ($this) {
            self::Regular => 1.25,
            self::RestDay => 1.30,
            self::Holiday => 2.00,
        };
    }

    /**
     * Get all available types as an array of values.
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
     * @return array<array{value: string, label: string, color: string, multiplier: float}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'color' => $type->color(),
            'multiplier' => $type->multiplier(),
        ], self::cases());
    }
}
