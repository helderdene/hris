<?php

namespace App\Enums;

/**
 * Philippine government contribution types for payroll deductions.
 */
enum ContributionType: string
{
    case Sss = 'sss';
    case Philhealth = 'philhealth';
    case Pagibig = 'pagibig';

    /**
     * Get a human-readable label for the contribution type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Sss => 'SSS',
            self::Philhealth => 'PhilHealth',
            self::Pagibig => 'Pag-IBIG',
        };
    }

    /**
     * Get the full name of the contribution type.
     */
    public function fullName(): string
    {
        return match ($this) {
            self::Sss => 'Social Security System',
            self::Philhealth => 'Philippine Health Insurance Corporation',
            self::Pagibig => 'Home Development Mutual Fund',
        };
    }

    /**
     * Get all available contribution type values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid contribution type.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Get all contribution types as options for forms.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            self::cases()
        );
    }
}
