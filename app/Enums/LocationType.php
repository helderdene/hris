<?php

namespace App\Enums;

/**
 * Types of work locations within an organization.
 */
enum LocationType: string
{
    case Headquarters = 'headquarters';
    case Branch = 'branch';
    case SatelliteOffice = 'satellite_office';
    case RemoteHub = 'remote_hub';
    case Warehouse = 'warehouse';
    case Factory = 'factory';

    /**
     * Get a human-readable label for the location type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Headquarters => 'Headquarters',
            self::Branch => 'Branch',
            self::SatelliteOffice => 'Satellite Office',
            self::RemoteHub => 'Remote Hub',
            self::Warehouse => 'Warehouse',
            self::Factory => 'Factory',
        };
    }

    /**
     * Get all available location types as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid location type.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Try to create a location type from a string value.
     */
    public static function tryFromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
