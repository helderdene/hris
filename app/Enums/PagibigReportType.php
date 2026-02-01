<?php

namespace App\Enums;

/**
 * Types of Pag-IBIG compliance reports for regulatory submission.
 *
 * MCRF - Monthly Contribution Remittance Form
 * STL - Short Term Loan amortization (MPL, Calamity)
 * HDL - Housing Loan amortization
 */
enum PagibigReportType: string
{
    case Mcrf = 'mcrf';
    case Stl = 'stl';
    case Hdl = 'hdl';

    /**
     * Get a human-readable label for the report type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Mcrf => 'MCRF - Monthly Contribution Remittance Form',
            self::Stl => 'STL - Short Term Loan Amortization',
            self::Hdl => 'HDL - Housing Loan Amortization',
        };
    }

    /**
     * Get a short label for the report type.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Mcrf => 'MCRF',
            self::Stl => 'STL',
            self::Hdl => 'HDL',
        };
    }

    /**
     * Get the description of the report type.
     */
    public function description(): string
    {
        return match ($this) {
            self::Mcrf => 'Monthly list of employee and employer Pag-IBIG contributions for remittance.',
            self::Stl => 'Monthly summary of short-term loan amortization payments (MPL, Calamity) deducted from payroll.',
            self::Hdl => 'Monthly summary of housing loan amortization payments deducted from payroll.',
        };
    }

    /**
     * Get the period type required for this report.
     */
    public function periodType(): string
    {
        return 'monthly';
    }

    /**
     * Check if this report requires monthly period selection.
     */
    public function isMonthlyReport(): bool
    {
        return true;
    }

    /**
     * Check if this report requires quarterly period selection.
     */
    public function isQuarterlyReport(): bool
    {
        return false;
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
