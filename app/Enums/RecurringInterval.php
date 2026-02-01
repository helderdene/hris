<?php

namespace App\Enums;

/**
 * Interval for recurring payroll adjustments.
 *
 * Determines how often a recurring adjustment is applied.
 */
enum RecurringInterval: string
{
    case EveryPeriod = 'every_period';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';

    /**
     * Get a human-readable label for the interval.
     */
    public function label(): string
    {
        return match ($this) {
            self::EveryPeriod => 'Every Pay Period',
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
        };
    }

    /**
     * Get a description of the interval.
     */
    public function description(): string
    {
        return match ($this) {
            self::EveryPeriod => 'Applied on each payroll period',
            self::Monthly => 'Applied once per month',
            self::Quarterly => 'Applied once per quarter (3 months)',
        };
    }

    /**
     * Get all available intervals as an array of values.
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
     * @return array<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $interval) => [
            'value' => $interval->value,
            'label' => $interval->label(),
            'description' => $interval->description(),
        ], self::cases());
    }
}
