<?php

namespace App\Enums;

/**
 * Types of BIR compliance reports for regulatory submission.
 *
 * Form1601c - Monthly Remittance Return of Income Taxes Withheld on Compensation
 * Form1604cf - Annual Information Return of Income Taxes Withheld on Compensation
 * Form2316 - Certificate of Compensation Payment/Tax Withheld (per-employee)
 * Alphalist - Year-end compliance listing (schedules 7.1, 7.2, 7.3)
 */
enum BirReportType: string
{
    case Form1601c = '1601c';
    case Form1604cf = '1604cf';
    case Form2316 = '2316';
    case Alphalist = 'alphalist';

    /**
     * Get a human-readable label for the report type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Form1601c => '1601-C - Monthly Remittance Return of Income Taxes Withheld',
            self::Form1604cf => '1604-CF - Annual Information Return of Income Taxes Withheld',
            self::Form2316 => '2316 - Certificate of Compensation Payment/Tax Withheld',
            self::Alphalist => 'Alphalist - Year-End Compliance Listing',
        };
    }

    /**
     * Get a short label for the report type.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Form1601c => '1601-C',
            self::Form1604cf => '1604-CF',
            self::Form2316 => '2316',
            self::Alphalist => 'Alphalist',
        };
    }

    /**
     * Get the description of the report type.
     */
    public function description(): string
    {
        return match ($this) {
            self::Form1601c => 'Monthly list of employees with compensation income subject to withholding tax.',
            self::Form1604cf => 'Annual employer-level summary of total compensation and taxes withheld.',
            self::Form2316 => 'Per-employee certificate of annual compensation and tax withheld.',
            self::Alphalist => 'Year-end employee listing with schedules 7.1, 7.2, and 7.3.',
        };
    }

    /**
     * Get the period type required for this report.
     */
    public function periodType(): string
    {
        return match ($this) {
            self::Form1601c => 'monthly',
            self::Form1604cf, self::Form2316, self::Alphalist => 'annual',
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
     * Check if this report requires annual period selection.
     */
    public function isAnnualReport(): bool
    {
        return $this->periodType() === 'annual';
    }

    /**
     * Check if this is an employee certificate (can be generated per-employee).
     */
    public function isEmployeeCertificate(): bool
    {
        return $this === self::Form2316;
    }

    /**
     * Check if this report supports DAT export for BIR eFiling.
     */
    public function supportsDataExport(): bool
    {
        return in_array($this, [self::Form1604cf, self::Form2316, self::Alphalist], true);
    }

    /**
     * Get all available report types as options for forms.
     *
     * @return array<array{value: string, label: string, shortLabel: string, description: string, periodType: string, isEmployeeCertificate: bool, supportsDataExport: bool}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'shortLabel' => $type->shortLabel(),
            'description' => $type->description(),
            'periodType' => $type->periodType(),
            'isEmployeeCertificate' => $type->isEmployeeCertificate(),
            'supportsDataExport' => $type->supportsDataExport(),
        ], self::cases());
    }
}
