<?php

namespace App\Enums;

/**
 * Types of documents that can be requested by employees.
 */
enum DocumentRequestType: string
{
    case Coe = 'coe';
    case EmploymentVerification = 'employment_verification';
    case ItrCopy = 'itr_copy';
    case PayslipCopy = 'payslip_copy';
    case Other = 'other';

    /**
     * Get a human-readable label for the document type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Coe => 'Certificate of Employment',
            self::EmploymentVerification => 'Employment Verification',
            self::ItrCopy => 'ITR Copy',
            self::PayslipCopy => 'Payslip Copy',
            self::Other => 'Other',
        };
    }

    /**
     * Get all available types as an array of values.
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
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => [
            'value' => $type->value,
            'label' => $type->label(),
        ], self::cases());
    }
}
