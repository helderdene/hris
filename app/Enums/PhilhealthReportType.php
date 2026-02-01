<?php

namespace App\Enums;

/**
 * Types of PhilHealth compliance reports for regulatory submission.
 *
 * RF1 - Electronic Remittance Form (Monthly Contribution List)
 * ER2 - Employer Remittance Report (Employee Member Details)
 * MDR - Member Data Record (New Employee Registrations)
 */
enum PhilhealthReportType: string
{
    case Rf1 = 'rf1';
    case Er2 = 'er2';
    case Mdr = 'mdr';

    /**
     * Get a human-readable label for the report type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Rf1 => 'RF1 - Electronic Remittance Form',
            self::Er2 => 'ER2 - Employer Remittance Report',
            self::Mdr => 'MDR - Member Data Record',
        };
    }

    /**
     * Get a short label for the report type.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Rf1 => 'RF1',
            self::Er2 => 'ER2',
            self::Mdr => 'MDR',
        };
    }

    /**
     * Get the description of the report type.
     */
    public function description(): string
    {
        return match ($this) {
            self::Rf1 => 'Monthly list of employee and employer PhilHealth contributions for remittance.',
            self::Er2 => 'Complete employee member details including personal information and employment data.',
            self::Mdr => 'Registration form for newly hired employees to be submitted to PhilHealth.',
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
     * Check if this report supports custom date ranges.
     */
    public function supportsDateRange(): bool
    {
        return match ($this) {
            self::Rf1 => false,
            self::Er2 => false,
            self::Mdr => true,
        };
    }

    /**
     * Get all available report types as options for forms.
     *
     * @return array<array{value: string, label: string, shortLabel: string, description: string, periodType: string, supportsDateRange: bool}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'shortLabel' => $type->shortLabel(),
            'description' => $type->description(),
            'periodType' => $type->periodType(),
            'supportsDateRange' => $type->supportsDateRange(),
        ], self::cases());
    }
}
