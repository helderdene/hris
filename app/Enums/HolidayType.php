<?php

namespace App\Enums;

/**
 * Types of holidays in the Philippine holiday calendar.
 *
 * Each holiday type has a specific pay rate premium based on DOLE guidelines.
 */
enum HolidayType: string
{
    case Regular = 'regular';
    case SpecialNonWorking = 'special_non_working';
    case SpecialWorking = 'special_working';
    case Double = 'double';

    /**
     * Get a human-readable label for the holiday type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Regular Holiday',
            self::SpecialNonWorking => 'Special Non-Working Day',
            self::SpecialWorking => 'Special Working Day',
            self::Double => 'Double Holiday',
        };
    }

    /**
     * Get the premium pay rate percentage for this holiday type.
     *
     * Returns the total pay rate percentage (base + premium).
     * For Double holidays, returns a default value that can be overridden by tenant settings.
     *
     * @param  int|null  $tenantDoubleRate  Optional custom rate for double holidays from tenant settings
     */
    public function premiumRate(?int $tenantDoubleRate = null): int
    {
        return match ($this) {
            self::Regular => 200,           // 100% base + 100% premium
            self::SpecialNonWorking => 130, // 100% base + 30% premium
            self::SpecialWorking => 100,    // No premium, tracking only
            self::Double => $tenantDoubleRate ?? 300, // Configurable, default 300%
        };
    }

    /**
     * Get the premium pay rate using the current tenant's settings.
     *
     * This is a convenience method that automatically retrieves the double holiday
     * rate from the current tenant context. Falls back to the default rate (300%)
     * if no tenant is available or if the rate is not configured.
     */
    public function premiumRateForTenant(): int
    {
        $tenant = tenant();

        if ($this === self::Double && $tenant !== null) {
            return $this->premiumRate($tenant->getDoubleHolidayRate());
        }

        return $this->premiumRate();
    }

    /**
     * Get all available holiday types as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid holiday type.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Try to create a holiday type from a string value.
     */
    public static function tryFromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
