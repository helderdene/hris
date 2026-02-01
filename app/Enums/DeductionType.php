<?php

namespace App\Enums;

/**
 * Types of deductions in payroll computation.
 *
 * Covers government-mandated contributions, taxes, and other deductions.
 */
enum DeductionType: string
{
    case Sss = 'sss';
    case Philhealth = 'philhealth';
    case Pagibig = 'pagibig';
    case WithholdingTax = 'withholding_tax';
    case Loan = 'loan';
    case Other = 'other';

    /**
     * Get a human-readable label for the deduction type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Sss => 'SSS',
            self::Philhealth => 'PhilHealth',
            self::Pagibig => 'Pag-IBIG',
            self::WithholdingTax => 'Withholding Tax',
            self::Loan => 'Loan',
            self::Other => 'Other',
        };
    }

    /**
     * Check if this deduction type is a government-mandated contribution.
     */
    public function isGovernmentContribution(): bool
    {
        return match ($this) {
            self::Sss, self::Philhealth, self::Pagibig => true,
            self::WithholdingTax, self::Loan, self::Other => false,
        };
    }

    /**
     * Check if this deduction type has an employer share.
     */
    public function hasEmployerShare(): bool
    {
        return match ($this) {
            self::Sss, self::Philhealth, self::Pagibig => true,
            self::WithholdingTax, self::Loan, self::Other => false,
        };
    }

    /**
     * Check if this deduction type is pre-tax (deducted before tax calculation).
     *
     * In the Philippines, government contributions are deducted from gross
     * income before computing withholding tax.
     */
    public function isPreTax(): bool
    {
        return match ($this) {
            self::Sss, self::Philhealth, self::Pagibig => true,
            self::WithholdingTax, self::Loan, self::Other => false,
        };
    }

    /**
     * Get the sort order for display purposes.
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::Sss => 1,
            self::Philhealth => 2,
            self::Pagibig => 3,
            self::WithholdingTax => 4,
            self::Loan => 5,
            self::Other => 6,
        };
    }

    /**
     * Get all available deduction types as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid deduction type.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}
