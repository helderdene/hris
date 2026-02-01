<?php

namespace App\Enums;

/**
 * Options for how salary information is displayed on a job posting.
 */
enum SalaryDisplayOption: string
{
    case ExactRange = 'exact_range';
    case RangeOnly = 'range_only';
    case Hidden = 'hidden';
    case Negotiable = 'negotiable';

    /**
     * Get a human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::ExactRange => 'Show Exact Range',
            self::RangeOnly => 'Show Range Only',
            self::Hidden => 'Hidden',
            self::Negotiable => 'Negotiable',
        };
    }

    /**
     * Get all available values.
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
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $option) => [
            'value' => $option->value,
            'label' => $option->label(),
        ], self::cases());
    }
}
