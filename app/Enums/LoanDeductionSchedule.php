<?php

namespace App\Enums;

/**
 * Preferred deduction cadence for a loan application.
 *
 * Labels are surfaced in the employee-facing loan form. Wording follows
 * standard PH payroll usage: 15th & 30th = semi-monthly (twice a month),
 * 30th only = monthly (once a month).
 */
enum LoanDeductionSchedule: string
{
    case SemiMonthly = 'semi_monthly';
    case Monthly = 'monthly';

    /**
     * Get a human-readable label for the schedule.
     */
    public function label(): string
    {
        return match ($this) {
            self::SemiMonthly => 'Twice a month / every 15th & 30th',
            self::Monthly => 'Once a month / every 30th',
        };
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases()
        );
    }
}
