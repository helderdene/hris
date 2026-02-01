<?php

namespace App\Enums;

/**
 * Source of a job application.
 */
enum ApplicationSource: string
{
    case CareersPage = 'careers_page';
    case ManualEntry = 'manual_entry';
    case Referral = 'referral';

    /**
     * Get a human-readable label for the source.
     */
    public function label(): string
    {
        return match ($this) {
            self::CareersPage => 'Careers Page',
            self::ManualEntry => 'Manual Entry',
            self::Referral => 'Referral',
        };
    }

    /**
     * Get all available sources as an array of values.
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
        return array_map(fn (self $source) => [
            'value' => $source->value,
            'label' => $source->label(),
        ], self::cases());
    }
}
