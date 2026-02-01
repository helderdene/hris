<?php

namespace App\Enums;

/**
 * Status of biometric devices indicating their connection state.
 */
enum DeviceStatus: string
{
    case Online = 'online';
    case Offline = 'offline';

    /**
     * Get a human-readable label for the device status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Offline => 'Offline',
        };
    }

    /**
     * Get all available device statuses as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid device status.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Try to create a device status from a string value.
     */
    public static function tryFromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
