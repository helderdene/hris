<?php

namespace App\Enums;

/**
 * Metric types for OKR key results.
 *
 * Defines how the key result value should be measured and displayed.
 */
enum KeyResultMetricType: string
{
    case Number = 'number';
    case Percentage = 'percentage';
    case Currency = 'currency';
    case Boolean = 'boolean';

    /**
     * Get a human-readable label for the metric type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Number => 'Number',
            self::Percentage => 'Percentage',
            self::Currency => 'Currency',
            self::Boolean => 'Yes/No',
        };
    }

    /**
     * Get a description for the metric type.
     */
    public function description(): string
    {
        return match ($this) {
            self::Number => 'A numeric value (e.g., 100 customers)',
            self::Percentage => 'A percentage value (e.g., 95%)',
            self::Currency => 'A monetary value (e.g., $10,000)',
            self::Boolean => 'A simple yes/no completion (e.g., Launch product)',
        };
    }

    /**
     * Get the CSS color class for metric type badges.
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::Number => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
            self::Percentage => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
            self::Currency => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
            self::Boolean => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
        };
    }

    /**
     * Get all available metric types as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid metric type.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Format a value according to this metric type.
     */
    public function formatValue(float $value, ?string $unit = null): string
    {
        return match ($this) {
            self::Number => number_format($value, 0).($unit ? " {$unit}" : ''),
            self::Percentage => number_format($value, 1).'%',
            self::Currency => ($unit ?? '$').number_format($value, 2),
            self::Boolean => $value >= 1 ? 'Yes' : 'No',
        };
    }
}
