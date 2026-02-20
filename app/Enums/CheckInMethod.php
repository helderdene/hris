<?php

namespace App\Enums;

/**
 * Method used for visitor check-in at the premises.
 */
enum CheckInMethod: string
{
    case Biometric = 'biometric';
    case Kiosk = 'kiosk';
    case Manual = 'manual';

    /**
     * Get a human-readable label for the check-in method.
     */
    public function label(): string
    {
        return match ($this) {
            self::Biometric => 'Biometric',
            self::Kiosk => 'Kiosk',
            self::Manual => 'Manual',
        };
    }

    /**
     * Get all available method values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
