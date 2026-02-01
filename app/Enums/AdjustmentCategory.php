<?php

namespace App\Enums;

/**
 * Category of a payroll adjustment.
 *
 * Determines whether the adjustment adds to (Earning)
 * or subtracts from (Deduction) the employee's pay.
 */
enum AdjustmentCategory: string
{
    case Earning = 'earning';
    case Deduction = 'deduction';

    /**
     * Get a human-readable label for the category.
     */
    public function label(): string
    {
        return match ($this) {
            self::Earning => 'Earning',
            self::Deduction => 'Deduction',
        };
    }

    /**
     * Get the badge color class for this category.
     */
    public function color(): string
    {
        return match ($this) {
            self::Earning => 'green',
            self::Deduction => 'red',
        };
    }

    /**
     * Get all available categories as an array of values.
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
        return array_map(fn (self $category) => [
            'value' => $category->value,
            'label' => $category->label(),
            'color' => $category->color(),
        ], self::cases());
    }
}
