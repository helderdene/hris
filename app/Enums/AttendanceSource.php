<?php

namespace App\Enums;

/**
 * Source of attendance log entries indicating how the clock event was recorded.
 */
enum AttendanceSource: string
{
    case Biometric = 'biometric';
    case Kiosk = 'kiosk';
    case SelfService = 'self_service';
    case Manual = 'manual';

    /**
     * Get a human-readable label for the attendance source.
     */
    public function label(): string
    {
        return match ($this) {
            self::Biometric => 'Biometric',
            self::Kiosk => 'Kiosk',
            self::SelfService => 'Self-Service',
            self::Manual => 'Manual',
        };
    }

    /**
     * Get all available source values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
