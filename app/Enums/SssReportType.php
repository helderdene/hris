<?php

namespace App\Enums;

/**
 * Types of SSS compliance reports for regulatory submission.
 *
 * R3 - Monthly Contribution Collection List
 * R5 - Quarterly Loan Amortization
 * SBR - Statement of Billing/Remittance
 * ECL - Electronic Collection List
 */
enum SssReportType: string
{
    case R3 = 'r3';
    case R5 = 'r5';
    case Sbr = 'sbr';
    case Ecl = 'ecl';

    /**
     * Get a human-readable label for the report type.
     */
    public function label(): string
    {
        return match ($this) {
            self::R3 => 'R3 - Monthly Contribution Collection List',
            self::R5 => 'R5 - Quarterly Loan Amortization',
            self::Sbr => 'SBR - Statement of Billing/Remittance',
            self::Ecl => 'ECL - Electronic Collection List',
        };
    }

    /**
     * Get a short label for the report type.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::R3 => 'R3',
            self::R5 => 'R5',
            self::Sbr => 'SBR',
            self::Ecl => 'ECL',
        };
    }

    /**
     * Get the description of the report type.
     */
    public function description(): string
    {
        return match ($this) {
            self::R3 => 'Monthly list of employee and employer SSS contributions for remittance.',
            self::R5 => 'Quarterly summary of SSS loan amortization payments deducted from payroll.',
            self::Sbr => 'Proof of payment summary showing total contributions remitted.',
            self::Ecl => 'Fixed-width format file for bank tellering and EPRS submission.',
        };
    }

    /**
     * Get the period type required for this report.
     */
    public function periodType(): string
    {
        return match ($this) {
            self::R3, self::Sbr, self::Ecl => 'monthly',
            self::R5 => 'quarterly',
        };
    }

    /**
     * Check if this report requires monthly period selection.
     */
    public function isMonthlyReport(): bool
    {
        return $this->periodType() === 'monthly';
    }

    /**
     * Check if this report requires quarterly period selection.
     */
    public function isQuarterlyReport(): bool
    {
        return $this->periodType() === 'quarterly';
    }

    /**
     * Get all available report types as options for forms.
     *
     * @return array<array{value: string, label: string, description: string, periodType: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'shortLabel' => $type->shortLabel(),
            'description' => $type->description(),
            'periodType' => $type->periodType(),
        ], self::cases());
    }
}
