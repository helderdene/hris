<?php

namespace App\Enums;

/**
 * Progress of a final pay release or COE issuance during offboarding.
 */
enum OffboardingStepStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';

    /**
     * Get all available statuses as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
