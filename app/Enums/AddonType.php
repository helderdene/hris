<?php

namespace App\Enums;

/**
 * Types of purchasable add-on slots for tenant plans.
 */
enum AddonType: string
{
    case EmployeeSlots = 'employee_slots';
    case BiometricDevices = 'biometric_devices';

    /**
     * Get a human-readable label for the add-on type.
     */
    public function label(): string
    {
        return match ($this) {
            self::EmployeeSlots => 'Extra Employee Slots',
            self::BiometricDevices => 'Extra Biometric Devices',
        };
    }

    /**
     * Get all available add-on type values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid add-on type.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Try to create an add-on type from a string value.
     */
    public static function tryFromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }

    /**
     * Number of units granted per quantity purchased.
     */
    public function unitsPerQuantity(): int
    {
        return match ($this) {
            self::EmployeeSlots => 10,
            self::BiometricDevices => 1,
        };
    }

    /**
     * Default price per unit in centavos.
     */
    public function defaultPrice(): int
    {
        return match ($this) {
            self::EmployeeSlots => 2500,      // ₱25/mo per pack of 10
            self::BiometricDevices => 5000,   // ₱50/mo per device
        };
    }
}
