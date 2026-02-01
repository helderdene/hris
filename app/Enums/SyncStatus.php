<?php

namespace App\Enums;

/**
 * Status of employee-to-device synchronization.
 */
enum SyncStatus: string
{
    case Pending = 'pending';
    case Syncing = 'syncing';
    case Synced = 'synced';
    case Failed = 'failed';

    /**
     * Get a human-readable label for the sync status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Syncing => 'Syncing',
            self::Synced => 'Synced',
            self::Failed => 'Failed',
        };
    }

    /**
     * Get all available sync statuses as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid sync status.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Check if this status indicates the sync needs to be attempted.
     */
    public function needsSync(): bool
    {
        return match ($this) {
            self::Pending, self::Failed => true,
            self::Syncing, self::Synced => false,
        };
    }
}
