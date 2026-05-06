<?php

namespace App\Enums;

/**
 * Preferred deduction cadence for a loan application.
 *
 * Labels are surfaced in the employee-facing loan form.
 */
enum LoanDeductionSchedule: string
{
    case MonthlyFifteenthAndThirtieth = 'monthly_15_30';
    case TwiceMonthlyThirtieth = 'twice_monthly_30';

    /**
     * Get a human-readable label for the schedule.
     */
    public function label(): string
    {
        return match ($this) {
            self::MonthlyFifteenthAndThirtieth => 'Once a month / every 15th & 30th',
            self::TwiceMonthlyThirtieth => 'Twice a month / every 30th',
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
